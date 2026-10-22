<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Empat kartu "Penegakan Aturan & Alur Banned/Unban" per HARI INI, dihitung
 * mundur langsung dari scan RFID (lewat DmsRosterRfidReader) — TIDAK memakai
 * dms_roster_pola, yang hanya untuk analisis historis.
 *
 *   onsite    Periode kerja > 70 hari         → banned 14 hari, di hari ke-74
 *   beruntun  Bekerja > 13 hari berturut-turut → banned 1 hari off, di hari ke-14
 *   jam12     Waktu kerja > 12 jam             → highlight (peringatan)
 *   map1..3   Tidak sesuai mapping roster PT   → highlight (peringatan)
 *
 * Angka aturan di config dms_roster.reg_live.aturan. Kode shift per hari
 * diambil dari CHECK IN pertama dalam jendela N hari (default 180); hari
 * dengan off > 5 hari berturut-turut dianggap cuti (rule engine).
 *
 * Hari ini diperlakukan khusus: scan hari ini masih berjalan (dan materialized
 * view-nya di-refresh berkala), jadi karyawan yang BELUM scan hari ini dinilai
 * sampai kemarin — tidak dianggap off, supaya run kerjanya tidak terputus palsu.
 *
 * Master karyawan (nama, jabatan, kategori, perusahaan, populasi wajib_cek)
 * dibaca dari dms_roster_karyawan, dibatasi ke PT di config
 * dms_roster.reg_live.kode_pt (tujuh PT yang punya desain roster), dan tanpa
 * karyawan yang sudah tercatat di sid_roster_banned_master (sudah treatment).
 */
final class DmsRosterRegLiveService
{
    public const KODE = ['onsite', 'beruntun', 'jam12', 'map1', 'map2', 'map3'];

    public function __construct(
        private readonly DmsRosterRuleEngine $engine,
        private readonly DmsRosterRfidReader $rfid,
    ) {}

    /** Hasil terakhir yang berhasil — cadangan saat RFID tidak terjangkau. */
    private const KUNCI_TERAKHIR = 'dms_roster:aturan_live:terakhir:v1';

    /** Penanda gagal singkat supaya request berikutnya tidak menunggu OLAP lagi. */
    private const KUNCI_GAGAL = 'dms_roster:aturan_live:gagal:v1';

    private ?string $galat = null;

    /**
     * Hasil aturan banned untuk halaman. Urutan sumber:
     *   1. cache hasil segar (diisi request sebelumnya atau command terjadwal
     *      dms:sync-roster-rfid lewat segarkan());
     *   2. hitung langsung dari RFID;
     *   3. bila gagal, hasil TERAKHIR yang berhasil, ditandai 'basi' => true
     *      beserta alasan gagalnya — supaya kartu tetap berisi data dengan
     *      jam pengambilannya, bukan kosong.
     *
     * @return array<string, mixed>|null  null hanya bila belum pernah ada
     *         hasil sukses sama sekali; alasannya di galatTerakhir().
     */
    public function ambil(): ?array
    {
        $key = $this->kunciSegar();

        $tersimpan = Cache::get($key);
        if (is_array($tersimpan)) {
            return $tersimpan;
        }

        // Baru saja gagal: jangan biarkan tiap request menunggu timeout OLAP.
        $gagal = Cache::get(self::KUNCI_GAGAL);
        if (is_string($gagal)) {
            $this->galat = $gagal;

            return $this->cadangan();
        }

        return $this->segarkan() ?? $this->cadangan();
    }

    /**
     * Hitung ulang dari RFID sekarang juga dan simpan — dipanggil command
     * terjadwal supaya halaman web cukup membaca cache, tidak perlu
     * terhubung ke OLAP saat dibuka.
     *
     * @return array<string, mixed>|null
     */
    public function segarkan(?callable $lapor = null): ?array
    {
        $hasil = $this->hitung(CarbonImmutable::now()->startOfDay(), $lapor);

        if ($hasil === null) {
            Cache::put(self::KUNCI_GAGAL, $this->galat ?? 'tidak diketahui', 300);

            return null;
        }

        $hasil['basi'] = false;
        Cache::put($this->kunciSegar(), $hasil, (int) config('dms_roster.reg_live.cache_ttl', 900));
        Cache::put(self::KUNCI_TERAKHIR, $hasil, now()->addDays(7));
        Cache::forget(self::KUNCI_GAGAL);

        return $hasil;
    }

    /** Alasan percobaan terakhir ke RFID gagal (untuk ditampilkan). */
    public function galatTerakhir(): ?string
    {
        return $this->galat;
    }

    private function kunciSegar(): string
    {
        // Versi master banned ikut di kunci: begitu ada orang di-banned atau
        // dicabut, hasil langsung dihitung ulang tanpa menunggu TTL habis.
        return 'dms_roster:aturan_live:v10:'.CarbonImmutable::now()->toDateString().':'.$this->versiMasterBanned();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cadangan(): ?array
    {
        $terakhir = Cache::get(self::KUNCI_TERAKHIR);
        if (! is_array($terakhir)) {
            return null;
        }

        return ['basi' => true, 'galat' => $this->galat] + $terakhir;
    }

    /**
     * Aturan berbasis pola harian (onsite, beruntun, map1..3) untuk satu
     * karyawan — murni, tanpa DB.
     *
     * @param  array<int, string>  $hariScan  offset hari => 'P'|'M'; 0 = hari pertama jendela.
     * @param  int  $panjang  Panjang jendela; offset $panjang - 1 = hari ini.
     * @return array<string, array<string, mixed>|null>
     */
    public function nilai(array $hariScan, int $panjang, string $kategori, string $perusahaan = ''): array
    {
        $param = $this->engine->paramPerusahaan($perusahaan);
        $eval = $this->engine->evaluasi($this->rakitDeret($hariScan, $panjang), $kategori, $param['thr'], $param['map']);

        return $this->nilaiEvaluasi($eval, $param);
    }

    /**
     * Aturan waktu kerja maksimal untuk satu karyawan — murni, tanpa DB.
     * Hanya shift yang check-in-nya dalam N hari terakhir yang dinilai, karena
     * dampaknya ke waktu istirahat hari berikutnya.
     *
     * @param  array{masuk:string,keluar:string,gate_in:string,gate_out:string,n_lebih:int,n_shift:int}  $shift
     * @return array{menit:int,masuk:CarbonImmutable,keluar:CarbonImmutable,gate_in:string,gate_out:string,n_lebih:int,n_shift:int}|null
     */
    public function nilaiJamKerja(array $shift, CarbonImmutable $hariIni): ?array
    {
        $aturan = $this->aturan();
        $masuk = CarbonImmutable::parse($shift['masuk']);
        $keluar = CarbonImmutable::parse($shift['keluar']);
        $menit = (int) floor($masuk->diffInMinutes($keluar, true));

        $terkini = $masuk->gte($hariIni->subDays($aturan['jam_kerja_hari_terkini'] - 1));
        if (! $terkini || $menit <= $aturan['jam_kerja_maks'] * 60) {
            return null;
        }

        return [
            'menit' => $menit,
            'masuk' => $masuk,
            'keluar' => $keluar,
            'gate_in' => $shift['gate_in'],
            'gate_out' => $shift['gate_out'],
            'n_lebih' => $shift['n_lebih'],
            'n_shift' => $shift['n_shift'],
        ];
    }

    /**
     * Timeline harian satu karyawan langsung dari RFID: kode shift, tanda
     * aturan per hari, dan detail tap (gate, jam check-in/out, durasi).
     *
     * @param  int|null  $panjang  Jumlah hari mundur termasuk hari ini; null = reg_live.jendela_hari.
     * @return array<string, mixed>|null  null bila RFID tidak terjangkau.
     */
    public function timeline(string $sid, ?int $panjang = null): ?array
    {
        $hariIni = CarbonImmutable::now()->startOfDay();
        $panjang = $panjang === null ? $this->panjangJendela() : max(14, $panjang);
        $awal = $hariIni->subDays($panjang - 1);

        try {
            $k = DB::table('dms_roster_karyawan')
                ->where('kode_sid', $sid)
                ->first(['kode_sid', 'nama', 'jabatan_struktural as jabatan', 'kategori', 'perusahaan', 'kode_pt', 'site']);
            if ($k === null) {
                return ['ditemukan' => false];
            }

            $tap = $this->rfid->tapKaryawan($sid, $awal, $hariIni);
        } catch (Throwable $e) {
            Log::warning('DmsRoster timeline RFID gagal: '.$e->getMessage());

            return null;
        }

        $hari = $this->susunHari($tap, $awal, $panjang);
        $hariScan = [];
        foreach ($hari as $i => $h) {
            if ($h['kode'] !== 'o') {
                $hariScan[$i] = $h['kode'];
            }
        }

        $param = $this->engine->paramPerusahaan((string) $k->perusahaan);
        $eval = $this->engine->evaluasi($this->rakitDeret($hariScan, $panjang), (string) $k->kategori, $param['thr'], $param['map']);
        $merah = $this->tandaMerah($eval);
        $n = strlen($eval->pola);
        $maksMenit = $this->aturan()['jam_kerja_maks'] * 60;

        foreach ($hari as $i => &$h) {
            // Kode setelah normalisasi (off panjang jadi cuti); hari ini tanpa
            // scan tidak dinilai, ditandai 'x'.
            $h['kode'] = $i < $n ? $eval->pola[$i] : 'x';
            $h['merah'] = $merah[$i] ?? '';
            $h['kuning'] = $eval->yel[$i] ?? '';
            $h['lebih_jam'] = $h['durasi_menit'] !== null && $h['durasi_menit'] > $maksMenit;
        }
        unset($h);

        $temuan = [];
        foreach ($this->nilaiEvaluasi($eval, $param) as $kode => $run) {
            if ($run !== null) {
                $temuan[] = $kode;
            }
        }

        return [
            'ditemukan' => true,
            'sid' => (string) $k->kode_sid,
            'nama' => (string) $k->nama,
            'jabatan' => (string) ($k->jabatan ?: '—'),
            'kategori' => (string) $k->kategori,
            'perusahaan' => (string) $k->perusahaan,
            'pt' => (string) $k->kode_pt,
            'site' => (string) ($k->site ?: '—'),
            'blok' => $param['thr'] - 1,
            'jam_maks' => $this->aturan()['jam_kerja_maks'],
            'onsite_maks' => $this->aturan()['onsite_maks'],
            'longgar' => $eval->longgar,
            'awal' => $awal->toDateString(),
            'akhir' => $hariIni->toDateString(),
            'panjang' => $panjang,
            'temuan' => $temuan,
            'riwayat' => $this->riwayat($eval, $awal),
            'hari' => $hari,
        ];
    }

    /**
     * Calon karyawan untuk pencarian manual satu orang: SID / NIK persis
     * didahulukan, lalu nama atau SID yang mengandung teks. Dibatasi
     * $maks + 1 baris supaya pemanggil tahu hasilnya terpotong.
     *
     * @return list<array{sid:string,nama:string,jabatan:string,pt:string,site:string}>
     */
    public function cariKaryawan(string $teks, int $maks = 10): array
    {
        $teks = trim($teks);
        if (mb_strlen($teks) < 3) {
            return [];
        }
        $persis = mb_strtoupper($teks);
        $mirip = '%'.addcslashes($teks, '%_\\').'%';

        return DB::table('dms_roster_karyawan')
            ->where(static fn ($q) => $q
                ->where('kode_sid', $persis)
                ->orWhere('nik', $teks)
                ->orWhere('nama', 'like', $mirip)
                ->orWhere('kode_sid', 'like', $mirip))
            ->orderByRaw('CASE WHEN kode_sid = ? OR nik = ? THEN 0 ELSE 1 END', [$persis, $teks])
            ->orderBy('nama')
            ->limit($maks + 1)
            ->get(['kode_sid', 'nama', 'jabatan_struktural', 'kode_pt', 'site'])
            ->map(static fn (object $r): array => [
                'sid' => (string) $r->kode_sid,
                'nama' => (string) $r->nama,
                'jabatan' => (string) ($r->jabatan_struktural ?: '—'),
                'pt' => (string) ($r->kode_pt ?: '—'),
                'site' => (string) ($r->site ?: '—'),
            ])
            ->all();
    }

    /**
     * Pasangkan tap RFID per hari — murni, tanpa DB.
     *
     * Kode hari dari CHECK IN lolos pertama (P < batas menit pagi, M
     * sesudahnya). Sebuah check-in berpasangan dengan tap lolos berikutnya
     * bila tap itu CHECK OUT dan jaraknya < 24 jam — aturan yang sama dengan
     * DmsRosterRfidReader::shiftTerakhir(), jadi durasi di timeline dan di
     * kartu tidak mungkin beda. Shift hari itu = check-in pertama yang punya
     * pasangan (tap ganda di gate tidak membuatnya hilang). Semua tap,
     * termasuk yang tidak lolos, dilampirkan per tanggal untuk ditelusuri.
     *
     * @param  list<array{waktu:string,jenis:string,gate:string,lolos:bool}>  $tap  Urut waktu naik.
     * @return list<array<string, mixed>>
     */
    public function susunHari(array $tap, CarbonImmutable $awal, int $panjang): array
    {
        $batasPagi = (int) config('dms_roster.populasi.batas_menit_pagi', 720);
        $tap = array_map(static fn (array $t): array => $t + ['t' => CarbonImmutable::parse($t['waktu'])], $tap);
        $lolos = array_values(array_filter(
            $tap,
            static fn (array $t): bool => $t['lolos'] && in_array($t['jenis'], ['CHECK IN', 'CHECK OUT'], true),
        ));

        $perTanggal = [];
        $checkIn = [];
        foreach ($lolos as $j => $t) {
            if ($t['jenis'] !== 'CHECK IN') {
                continue;
            }
            $nx = $lolos[$j + 1] ?? null;
            $pasangan = $nx !== null && $nx['jenis'] === 'CHECK OUT' && $t['t']->diffInHours($nx['t'], true) < 24 ? $nx : null;
            $checkIn[$t['t']->toDateString()][] = ['masuk' => $t, 'keluar' => $pasangan];
        }
        foreach ($tap as $t) {
            $perTanggal[$t['t']->toDateString()][] = [
                'jam' => $t['t']->format('H:i'),
                'jenis' => $t['jenis'],
                'gate' => $t['gate'],
                'lolos' => $t['lolos'],
            ];
        }

        $hari = [];
        for ($i = 0; $i < $panjang; $i++) {
            $tgl = $awal->addDays($i);
            $iso = $tgl->toDateString();
            $daftar = $checkIn[$iso] ?? [];
            $pertama = $daftar[0]['masuk'] ?? null;

            $shift = $daftar[0] ?? null;
            foreach ($daftar as $c) {
                if ($c['keluar'] !== null) {
                    $shift = $c;
                    break;
                }
            }

            $masuk = $shift['masuk'] ?? null;
            $keluar = $shift['keluar'] ?? null;
            $menit = $masuk && $keluar ? (int) floor($masuk['t']->diffInMinutes($keluar['t'], true)) : null;
            $hari[] = [
                'iso' => $iso,
                'label' => $tgl->translatedFormat('D, j M Y'),
                'kode' => $pertama === null ? 'o' : ($pertama['t']->hour * 60 + $pertama['t']->minute < $batasPagi ? 'P' : 'M'),
                'masuk' => $masuk ? ['jam' => $masuk['t']->format('H:i'), 'gate' => $masuk['gate']] : null,
                'keluar' => $keluar ? [
                    'jam' => $keluar['t']->format('H:i'),
                    'tanggal' => $keluar['t']->translatedFormat('j M'),
                    'beda_hari' => $keluar['t']->toDateString() !== $iso,
                    'gate' => $keluar['gate'],
                ] : null,
                'durasi_menit' => $menit,
                'durasi' => $menit === null ? null : $this->durasi($menit),
                'tap' => $perTanggal[$iso] ?? [],
            ];
        }

        return $hari;
    }

    /**
     * @return array{per:string,diambil:string,jendela:int,populasi:int,daftar:array<string,list<array<string,mixed>>>}|null
     */
    private function hitung(CarbonImmutable $hariIni, ?callable $lapor = null): ?array
    {
        $aturan = $this->aturan();
        $panjang = $this->panjangJendela();
        $awal = $hariIni->subDays($panjang - 1);

        try {
            // Master dulu (MySQL), supaya RFID hanya ditarik untuk SID yang
            // memang dinilai — hasil query jadi ±1/6 ukurannya.
            $karyawan = DB::table('dms_roster_karyawan')
                ->where('wajib_cek', true)
                ->when(
                    config('dms_roster.reg_live.kode_pt', []) !== [],
                    static fn ($q) => $q->whereIn('kode_pt', config('dms_roster.reg_live.kode_pt')),
                )
                ->when(
                    (bool) config('dms_roster.reg_live.kecualikan_master_banned', true),
                    fn ($q) => $this->kecualikanMasterBanned($q),
                )
                ->select('kode_sid', 'nama', 'jabatan_struktural as jabatan', 'kategori', 'perusahaan', 'kode_pt', 'site')
                ->get();
            $sids = $karyawan->pluck('kode_sid')->map(static fn ($s): string => trim((string) $s))
                ->filter()->unique()->values()->all();

            $unbanTerakhir = config('dms_roster.reg_live.kecualikan_master_banned', true)
                ? DmsRosterBannedAktif::unbanTerakhir($sids)
                : [];
            $scan = $sids === [] ? [] : $this->rfid->kodeHarian($awal, $hariIni, $sids, $lapor);
            $mulai = microtime(true);
            $shift = $sids === [] ? [] : $this->rfid->shiftTerakhir(
                $hariIni->subDays($aturan['jam_kerja_jendela'] - 1), $hariIni, $aturan['jam_kerja_maks'], $sids,
            );
            if ($lapor !== null) {
                $lapor('shift '.$aturan['jam_kerja_jendela'].' hari terakhir', microtime(true) - $mulai, count($shift));
            }
        } catch (Throwable $e) {
            Log::warning('DmsRoster aturan banned live gagal: '.$e->getMessage());
            // Pesan pendek untuk kartu; teks lengkap tetap di log.
            $this->galat = mb_substr($e->getMessage(), 0, 200);

            return null;
        }

        $daftar = array_fill_keys(self::KODE, []);
        $tanggal = static fn (int $i): string => $awal->addDays($i)->translatedFormat('j M Y');
        $iso = static fn (int $i): string => $awal->addDays($i)->toDateString();

        foreach ($karyawan as $k) {
            $sid = (string) $k->kode_sid;
            $hariScan = $scan[$sid] ?? [];
            $dasar = [
                'sid' => $sid,
                'nama' => (string) $k->nama,
                'jabatan' => (string) ($k->jabatan ?: '—'),
                'kategori' => (string) $k->kategori,
                'perusahaan' => (string) $k->perusahaan,
                'pt' => (string) $k->kode_pt,
                'site' => (string) ($k->site ?: '—'),
                'scan_terakhir' => $hariScan === [] ? '—' : $tanggal(max(array_keys($hariScan))),
            ];

            if ($hariScan !== []) {
                foreach ($this->nilai($hariScan, $panjang, (string) $k->kategori, (string) $k->perusahaan) as $kode => $run) {
                    if ($run === null) {
                        continue;
                    }
                    // Sudah pernah di-banned & di-unban: periode on-site yang
                    // dimulai sebelum/pada tanggal unban itu adalah pelanggaran
                    // yang SAMA yang sudah ditindak — jangan dihitung lagi.
                    if ($kode === 'onsite' && isset($unbanTerakhir[$sid]) && $iso($run['mulai']) <= $unbanTerakhir[$sid]) {
                        continue;
                    }
                    $daftar[$kode][] = $dasar + $this->barisPola($kode, $run, $tanggal, $iso);
                }
            }

            $jam = isset($shift[$sid]) ? $this->nilaiJamKerja($shift[$sid], $hariIni) : null;
            if ($jam !== null) {
                $daftar['jam12'][] = $dasar + $this->barisJam($jam);
            }
        }

        foreach ($daftar as &$baris) {
            usort($baris, static fn (array $a, array $b): int => $b['urut'] <=> $a['urut']);
        }
        unset($baris);

        return [
            'per' => $hariIni->translatedFormat('j M Y'),
            'diambil' => CarbonImmutable::now()->translatedFormat('j M Y H:i'),
            'jendela' => $panjang,
            // Karyawan yang dinilai (7 PT, tanpa master banned) — pembagi persen tile.
            'populasi' => $karyawan->count(),
            'daftar' => $daftar,
        ];
    }

    /**
     * Kolom tabel untuk aturan berbasis pola. 'nilai' = angka utama yang
     * tampil, 'urut' = kunci pengurutan (terbesar dulu).
     *
     * @param  array<string, mixed>  $run
     * @param  callable(int): string  $tanggal
     * @return array<string, mixed>
     */
    private function barisPola(string $kode, array $run, callable $tanggal, callable $iso): array
    {
        $aturan = $this->aturan();
        $hariKe = static fn (int $ke): string => $tanggal($run['mulai'] + $ke - 1);

        $keterangan = match ($kode) {
            'onsite' => $run['hari'] >= $aturan['onsite_banned_hari_ke']
                ? 'Banned '.$aturan['onsite_banned_lama'].' hari sejak hari ke-'.$aturan['onsite_banned_hari_ke'].' ('.$hariKe($aturan['onsite_banned_hari_ke']).') — sudah jatuh tempo'
                : 'Banned '.$aturan['onsite_banned_lama'].' hari mulai hari ke-'.$aturan['onsite_banned_hari_ke'].' ('.$hariKe($aturan['onsite_banned_hari_ke']).')',
            'beruntun' => 'Banned 1 hari off di hari ke-'.$aturan['beruntun_banned_hari_ke'].' ('.$hariKe($aturan['beruntun_banned_hari_ke']).')',
            'map1' => 'Blok roster PT maks '.$run['blok'].' hari; terlewati sejak '.$tanggal($run['pada']),
            'map2' => 'Ganti shift Pagi↔Malam tanpa off pada '.$tanggal($run['pada']),
            'map3' => 'Shift Pagi setelah Malam pada '.$tanggal($run['pada']),
            default => '',
        };

        $baris = [
            'nilai' => ($run['terpotong'] ? '≥' : '').$run['hari'].' hari',
            'urut' => $run['hari'],
            'mulai' => $tanggal($run['mulai']),
            'selesai' => $tanggal($run['selesai']),
            'keterangan' => $keterangan,
        ];

        // Kartu "Karyawan Perlu Tindakan" butuh jadwal banned terpisah dari
        // kalimat keterangan: tanggal hari ke-74 dan apakah sudah lewat.
        if ($kode === 'onsite') {
            $baris['tgl_banned'] = $hariKe($aturan['onsite_banned_hari_ke']);
            $baris['jatuh_tempo'] = $run['hari'] >= $aturan['onsite_banned_hari_ke'];
            // Untuk penyisipan otomatis ke master banned (DmsRosterAutoBannedService):
            // tanggal pelanggaran = hari pertama melewati batas (hari ke-71).
            // Run yang terpotong awal jendela tidak tahu hari ke-71 aslinya,
            // jadi dipakai hari pertama jendela.
            $baris['tgl_pelanggaran_iso'] = $iso(
                $run['terpotong'] ? $run['mulai'] : $run['mulai'] + $aturan['onsite_maks'],
            );
        }

        return $baris;
    }

    /**
     * @param  array{menit:int,masuk:CarbonImmutable,keluar:CarbonImmutable,gate_in:string,gate_out:string,n_lebih:int,n_shift:int}  $jam
     * @return array<string, mixed>
     */
    private function barisJam(array $jam): array
    {
        return [
            'nilai' => $this->durasi($jam['menit']),
            'urut' => $jam['menit'],
            'mulai' => $jam['masuk']->translatedFormat('j M H:i').' · '.$jam['gate_in'],
            'selesai' => $jam['keluar']->translatedFormat('j M H:i').' · '.$jam['gate_out'],
            'keterangan' => $jam['n_lebih'].' dari '.$jam['n_shift'].' shift dalam '
                .$this->aturan()['jam_kerja_jendela'].' hari terakhir > '.$this->aturan()['jam_kerja_maks'].' jam',
        ];
    }

    /**
     * @param  array{thr:int,map:string}  $param
     * @return array<string, array<string, mixed>|null>
     */
    private function nilaiEvaluasi(DmsRosterEvaluation $eval, array $param): array
    {
        $aturan = $this->aturan();
        $reg = $this->engine->pelanggaranTerkini($eval, [
            'onsite' => $aturan['onsite_maks'],
            'kerja_beruntun' => $aturan['beruntun_maks'],
        ]);

        $hasil = [];
        foreach (['onsite' => $reg['reg2'], 'beruntun' => $reg['reg1']] as $kode => $run) {
            // Run yang menyentuh awal jendela panjang aslinya tidak diketahui:
            // tetap pelanggaran (minimal sepanjang jendela), ditandai "≥".
            $hasil[$kode] = $run === null ? null : $run + ['terpotong' => $run['mulai'] === 0];
        }

        foreach ($this->engine->mappingTerkini($eval, $param['thr'], $param['map']) as $kode => $run) {
            $hasil[$kode] = $run === null ? null : $run + ['terpotong' => false, 'blok' => $param['thr'] - 1];
        }

        return $hasil;
    }

    /**
     * Tanda merah per hari untuk timeline, memakai angka ATURAN (bukan ambang
     * replikasi referensi di rule engine): run on-site > onsite_maks dan run
     * kerja > beruntun_maks, di seluruh jendela.
     *
     * @return array<int, string>
     */
    private function tandaMerah(DmsRosterEvaluation $eval): array
    {
        $format = [
            'onsite' => 'Periode kerja %d hari (> %d) — banned 14 hari',
            'beruntun' => 'Bekerja %d hari berturut-turut (> %d) — banned 1 hari off',
        ];
        $merah = [];

        foreach ($this->runPelanggaran($eval) as $run) {
            for ($k = $run['mulai']; $k <= $run['selesai']; $k++) {
                $merah[$k] = sprintf($format[$run['kode']], $run['hari'], $run['batas']);
            }
        }

        return $merah;
    }

    /**
     * Kejadian pelanggaran di jendela, urut tanggal — run yang sama dengan
     * tanda merah per hari, jadi daftar dan kalender selalu sepakat. Run yang
     * menyentuh awal jendela ditandai "≥": panjang aslinya tidak diketahui.
     * Murni, tanpa DB.
     *
     * @return list<array{jenis:string,teks:string,mulai:string,selesai:string,hari:int}>
     */
    public function riwayat(DmsRosterEvaluation $eval, CarbonImmutable $awal): array
    {
        $jenis = [
            'onsite' => ['Periode kerja', '%s%d hari on-site tanpa cuti (maks %d)'],
            'beruntun' => ['Bekerja berturut-turut', '%s%d hari tanpa off (maks %d)'],
        ];
        $out = [];

        foreach ($this->runPelanggaran($eval) as $run) {
            [$judul, $format] = $jenis[$run['kode']];
            $out[] = [
                'jenis' => $judul,
                'teks' => sprintf($format, $run['mulai'] === 0 ? '≥ ' : '', $run['hari'], $run['batas']),
                'mulai' => $awal->addDays($run['mulai'])->translatedFormat('j M'),
                'selesai' => $awal->addDays($run['selesai'])->translatedFormat('j M'),
                'hari' => $run['mulai'],
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['hari'] <=> $b['hari']);

        return $out;
    }

    /**
     * Run on-site > onsite_maks dan run kerja > beruntun_maks (angka ATURAN)
     * di seluruh pola. Kategori longgar tidak punya pelanggaran.
     *
     * @return list<array{kode:string,mulai:int,selesai:int,hari:int,batas:int}>
     */
    private function runPelanggaran(DmsRosterEvaluation $eval): array
    {
        if ($eval->longgar) {
            return [];
        }

        $aturan = $this->aturan();
        $p = $eval->pola;
        $n = strlen($p);
        $out = [];

        foreach ([
            ['onsite', static fn (string $c): bool => $c !== 'c', $aturan['onsite_maks']],
            ['beruntun', static fn (string $c): bool => $c === 'P' || $c === 'M', $aturan['beruntun_maks']],
        ] as [$kode, $cocok, $batas]) {
            $i = 0;
            while ($i < $n) {
                if (! $cocok($p[$i])) {
                    $i++;

                    continue;
                }
                $j = $i;
                while ($j < $n && $cocok($p[$j])) {
                    $j++;
                }
                if ($j - $i > $batas) {
                    $out[] = ['kode' => $kode, 'mulai' => $i, 'selesai' => $j - 1, 'hari' => $j - $i, 'batas' => $batas];
                }
                $i = $j;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $hariScan  offset => 'P'|'M'
     */
    private function rakitDeret(array $hariScan, int $panjang): string
    {
        $deret = str_repeat('o', $panjang);
        foreach ($hariScan as $i => $kode) {
            if ($i >= 0 && $i < $panjang) {
                $deret[$i] = $kode === 'M' ? 'M' : 'P';
            }
        }

        // Belum scan hari ini = belum diketahui, bukan off.
        if ($deret[$panjang - 1] === 'o') {
            $deret = substr($deret, 0, -1);
        }

        return $deret;
    }

    private function durasi(int $menit): string
    {
        return intdiv($menit, 60).'j '.($menit % 60).'m';
    }

    /**
     * Buang karyawan yang masih punya kasus AKTIF di Master Roster Banned
     * (sudah ditindak tapi belum di-unban — lihat DmsRosterBannedAktif).
     * Yang kasusnya sudah di-unban tetap dinilai: kalau melanggar lagi, ia
     * muncul lagi dan boleh di-banned lagi. Dicocokkan lewat SID ATAU NIK
     * supaya orang yang SID-nya berganti tetap terkecualikan.
     *
     * whereNotNull di subquery wajib: satu NULL di daftar NOT IN membuat
     * seluruh perbandingan bernilai NULL dan semua baris ikut terbuang.
     */
    private function kecualikanMasterBanned(Builder $q): Builder
    {
        // Builder diberikan langsung (bukan closure): closure di whereNotIn
        // harus mengisi builder bawaan Laravel, nilai kembaliannya diabaikan.
        $sidAktif = DmsRosterBannedAktif::query()->whereNotNull('m.sid')->select('m.sid');
        $nikAktif = DmsRosterBannedAktif::query()->whereNotNull('m.nik')->where('m.nik', '<>', '')->select('m.nik');

        return $q
            ->whereNotIn('kode_sid', $sidAktif)
            ->where(static fn ($w) => $w->whereNull('nik')->orWhereNotIn('nik', $nikAktif));
    }

    /**
     * Sidik ringkas master banned + log unban (lihat DmsRosterBannedAktif::versi()).
     * Gagal dibaca = string kosong; kunci cache tetap valid.
     */
    private function versiMasterBanned(): string
    {
        if (! config('dms_roster.reg_live.kecualikan_master_banned', true)) {
            return '';
        }

        try {
            return DmsRosterBannedAktif::versi();
        } catch (Throwable $e) {
            Log::warning('DmsRoster versi master banned gagal: '.$e->getMessage());

            return '';
        }
    }

    private function panjangJendela(): int
    {
        return max(30, (int) config('dms_roster.reg_live.jendela_hari', 180));
    }

    /**
     * @return array<string, int>
     */
    private function aturan(): array
    {
        /** @var array<string, int> $aturan */
        $aturan = config('dms_roster.reg_live.aturan', []);

        return $aturan + [
            'onsite_maks' => 70,
            'onsite_banned_hari_ke' => 74,
            'onsite_banned_lama' => 14,
            'beruntun_maks' => 13,
            'beruntun_banned_hari_ke' => 14,
            'jam_kerja_maks' => 12,
            'jam_kerja_hari_terkini' => 3,
            'jam_kerja_jendela' => 7,
        ];
    }
}
