<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pelanggaran regulasi (REG-1/2/3) dan ketidaksesuaian mapping roster
 * (MAP-1/2/3) per HARI INI, dihitung mundur langsung dari scan RFID
 * (bcsid.mv_checkinout_rfid) — TIDAK memakai dms_roster_pola, yang hanya
 * untuk analisis historis dan sengaja berhenti di kemarin.
 *
 * Satu query menarik check-in pertama per SID per hari selama jendela N hari
 * terakhir (default 180, ±5 dtk untuk ±24 rb SID) sebagai kode P/M. Dari situ
 * dirakit deret P/M/o per karyawan lalu dinilai oleh DmsRosterRuleEngine
 * (off > 5 hari = cuti, ambang dan parameter PT dari config).
 *
 * Hari ini diperlakukan khusus: scan hari ini masih berjalan (dan materialized
 * view-nya di-refresh berkala), jadi karyawan yang BELUM scan hari ini dinilai
 * sampai kemarin — tidak dianggap off, supaya run kerjanya tidak terputus palsu.
 *
 * Master karyawan (nama, jabatan, kategori, perusahaan, populasi wajib_cek)
 * dibaca dari dms_roster_karyawan.
 */
final class DmsRosterRegLiveService
{
    public const KODE = ['reg1', 'reg2', 'reg3', 'map1', 'map2', 'map3'];

    public function __construct(
        private readonly DmsRosterRuleEngine $engine,
        private readonly PembatasanLVOlapQuery $olap,
    ) {}

    /**
     * @return array{per:string,diambil:string,jendela:int,daftar:array<string,list<array<string,mixed>>>}|null
     *         null bila RFID tidak terjangkau / query gagal.
     */
    public function ambil(): ?array
    {
        $hariIni = CarbonImmutable::now()->startOfDay();
        $key = 'dms_roster:reg_live:v2:'.$hariIni->toDateString();

        $tersimpan = Cache::get($key);
        if (is_array($tersimpan)) {
            return $tersimpan;
        }

        $hasil = $this->hitung($hariIni);
        // Kegagalan tidak di-cache supaya percobaan berikutnya langsung ke RFID lagi.
        if ($hasil !== null) {
            Cache::put($key, $hasil, (int) config('dms_roster.reg_live.cache_ttl', 900));
        }

        return $hasil;
    }

    /**
     * Penilaian satu karyawan — murni, tanpa DB.
     *
     * @param  array<int, string>  $hariScan  offset hari => 'P'|'M'; 0 = hari pertama jendela.
     * @param  int  $panjang  Panjang jendela; offset $panjang - 1 = hari ini.
     * @return array<string, array<string, mixed>|null>  Kunci sesuai self::KODE.
     */
    public function nilai(array $hariScan, int $panjang, string $kategori, string $perusahaan = ''): array
    {
        $param = $this->engine->paramPerusahaan($perusahaan);

        return $this->nilaiEvaluasi(
            $this->engine->evaluasi($this->rakitDeret($hariScan, $panjang), $kategori, $param['thr'], $param['map']),
            $param,
        );
    }

    /**
     * Timeline harian satu karyawan untuk jendela yang sama, langsung dari
     * RFID: kode shift, flag REG/MAP per hari, dan detail tap (gate, jam
     * check-in/out, durasi).
     *
     * @return array<string, mixed>|null  null bila RFID tidak terjangkau.
     */
    public function timeline(string $sid): ?array
    {
        $hariIni = CarbonImmutable::now()->startOfDay();
        $panjang = $this->panjangJendela();
        $awal = $hariIni->subDays($panjang - 1);

        try {
            $k = DB::table('dms_roster_karyawan')
                ->where('kode_sid', $sid)
                ->first(['kode_sid', 'nama', 'jabatan_struktural as jabatan', 'kategori', 'perusahaan', 'kode_pt', 'site']);
            if ($k === null) {
                return ['ditemukan' => false];
            }

            $tap = $this->tarikTapKaryawan($sid, $awal, $hariIni);
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
        $deret = $this->rakitDeret($hariScan, $panjang);
        $eval = $this->engine->evaluasi($deret, (string) $k->kategori, $param['thr'], $param['map']);
        $n = strlen($eval->pola);

        foreach ($hari as $i => &$h) {
            // Kode setelah normalisasi (off panjang jadi cuti); hari ini tanpa
            // scan tidak dinilai, ditandai 'x'.
            $h['kode'] = $i < $n ? $eval->pola[$i] : 'x';
            $h['merah'] = $eval->red[$i] ?? '';
            $h['kuning'] = $eval->yel[$i] ?? '';
        }
        unset($h);

        $temuan = [];
        foreach ($this->nilaiEvaluasi($eval, $param) as $kode => $run) {
            if ($run !== null) {
                $temuan[] = strtoupper(substr($kode, 0, 3)).'-'.substr($kode, 3);
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
            'temuan' => $temuan,
            'hari' => $hari,
        ];
    }

    /**
     * Pasangkan tap RFID per hari — murni, tanpa DB.
     *
     * Kode hari dari CHECK IN pertama yang lolos (P < batas menit pagi, M
     * sesudahnya). Check-out dipasangkan ke check-in itu: CHECK OUT lolos
     * pertama sesudahnya, sebelum check-in lolos berikutnya dan paling lama
     * 24 jam — sehingga check-out pagi milik shift malam kemarin tidak
     * tertukar ke shift hari ini. Semua tap (termasuk yang tidak lolos)
     * tetap dilampirkan per tanggal untuk ditelusuri.
     *
     * @param  list<array{waktu:string,jenis:string,gate:string,lolos:bool}>  $tap  Urut waktu naik.
     * @return list<array<string, mixed>>
     */
    public function susunHari(array $tap, CarbonImmutable $awal, int $panjang): array
    {
        $batasPagi = (int) config('dms_roster.populasi.batas_menit_pagi', 720);
        $tap = array_map(static fn (array $t): array => $t + ['t' => CarbonImmutable::parse($t['waktu'])], $tap);
        $checkIn = array_values(array_filter($tap, static fn (array $t): bool => $t['lolos'] && $t['jenis'] === 'CHECK IN'));
        $checkOut = array_values(array_filter($tap, static fn (array $t): bool => $t['lolos'] && $t['jenis'] === 'CHECK OUT'));

        $perTanggal = [];
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
            $masuk = null;
            $berikut = null;
            foreach ($checkIn as $j => $c) {
                if ($c['t']->toDateString() === $iso) {
                    $masuk = $c;
                    $berikut = $checkIn[$j + 1]['t'] ?? null;
                    break;
                }
            }

            $keluar = null;
            if ($masuk !== null) {
                $batas = $masuk['t']->addDay();
                if ($berikut !== null && $berikut->lt($batas)) {
                    $batas = $berikut;
                }
                foreach ($checkOut as $o) {
                    if ($o['t']->gt($masuk['t']) && $o['t']->lt($batas)) {
                        $keluar = $o;
                        break;
                    }
                }
            }

            $menitMasuk = $masuk ? $masuk['t']->hour * 60 + $masuk['t']->minute : null;
            $hari[] = [
                'iso' => $iso,
                'label' => $tgl->translatedFormat('D, j M Y'),
                'kode' => $masuk === null ? 'o' : ($menitMasuk < $batasPagi ? 'P' : 'M'),
                'masuk' => $masuk ? ['jam' => $masuk['t']->format('H:i'), 'gate' => $masuk['gate']] : null,
                'keluar' => $keluar ? [
                    'jam' => $keluar['t']->format('H:i'),
                    'tanggal' => $keluar['t']->translatedFormat('j M'),
                    'gate' => $keluar['gate'],
                ] : null,
                'durasi' => $keluar ? $this->durasi($masuk['t'], $keluar['t']) : null,
                'tap' => $perTanggal[$iso] ?? [],
            ];
        }

        return $hari;
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

    private function durasi(CarbonImmutable $masuk, CarbonImmutable $keluar): string
    {
        $menit = (int) floor($masuk->diffInMinutes($keluar, true));

        return intdiv($menit, 60).'j '.($menit % 60).'m';
    }

    /**
     * @param  array{thr:int,map:string}  $param
     * @return array<string, array<string, mixed>|null>
     */
    private function nilaiEvaluasi(DmsRosterEvaluation $eval, array $param): array
    {
        $hasil = [];

        foreach ($this->engine->pelanggaranTerkini($eval) as $kode => $run) {
            // Run yang menyentuh awal jendela panjang aslinya tidak diketahui:
            // REG-1/2 tetap pelanggaran (minimal sepanjang jendela), sedangkan
            // cuti terpotong tidak bisa dinilai "pendek", jadi REG-3 dilewati.
            $terpotong = $run !== null && $run['mulai'] === 0;
            $hasil[$kode] = $run === null || ($kode === 'reg3' && $terpotong)
                ? null
                : $run + ['terpotong' => $terpotong];
        }

        foreach ($this->engine->mappingTerkini($eval, $param['thr'], $param['map']) as $kode => $run) {
            $hasil[$kode] = $run === null ? null : $run + ['terpotong' => false, 'blok' => $param['thr'] - 1];
        }

        return $hasil;
    }

    /**
     * @return array{per:string,diambil:string,jendela:int,daftar:array<string,list<array<string,mixed>>>}|null
     */
    private function hitung(CarbonImmutable $hariIni): ?array
    {
        $panjang = $this->panjangJendela();
        $awal = $hariIni->subDays($panjang - 1);

        try {
            $scan = $this->tarikScan($awal, $hariIni);
            $karyawan = DB::table('dms_roster_karyawan')
                ->where('wajib_cek', true)
                ->select('kode_sid', 'nama', 'jabatan_struktural as jabatan', 'kategori', 'perusahaan', 'kode_pt', 'site')
                ->get();
        } catch (Throwable $e) {
            Log::warning('DmsRoster REG/MAP live gagal: '.$e->getMessage());

            return null;
        }

        $daftar = array_fill_keys(self::KODE, []);
        $tanggal = static fn (int $i): string => $awal->addDays($i)->translatedFormat('j M Y');

        foreach ($karyawan as $k) {
            $hariScan = $scan[(string) $k->kode_sid] ?? [];
            if ($hariScan === []) {
                continue;
            }

            foreach ($this->nilai($hariScan, $panjang, (string) $k->kategori, (string) $k->perusahaan) as $kode => $run) {
                if ($run === null) {
                    continue;
                }

                $daftar[$kode][] = [
                    'sid' => (string) $k->kode_sid,
                    'nama' => (string) $k->nama,
                    'jabatan' => (string) ($k->jabatan ?: '—'),
                    'kategori' => (string) $k->kategori,
                    'perusahaan' => (string) $k->perusahaan,
                    'pt' => (string) $k->kode_pt,
                    'site' => (string) ($k->site ?: '—'),
                    'hari' => $run['hari'],
                    'terpotong' => $run['terpotong'],
                    'mulai' => $tanggal($run['mulai']),
                    'selesai' => $tanggal($run['selesai']),
                    'keterangan' => $this->keterangan($kode, $run, $tanggal),
                    'scan_terakhir' => $tanggal(max(array_keys($hariScan))),
                ];
            }
        }

        // REG-3 dari cuti terpendek; sisanya dari run/blok terpanjang.
        foreach ($daftar as $kode => &$baris) {
            usort($baris, $kode === 'reg3'
                ? static fn (array $a, array $b): int => $a['hari'] <=> $b['hari']
                : static fn (array $a, array $b): int => $b['hari'] <=> $a['hari']);
        }
        unset($baris);

        return [
            'per' => $hariIni->translatedFormat('j M Y'),
            'diambil' => CarbonImmutable::now()->translatedFormat('j M Y H:i'),
            'jendela' => $panjang,
            'daftar' => $daftar,
        ];
    }

    /**
     * @param  array<string, mixed>  $run
     * @param  callable(int): string  $tanggal
     */
    private function keterangan(string $kode, array $run, callable $tanggal): string
    {
        return match ($kode) {
            'map1' => 'Blok roster PT maks '.$run['blok'].' hari; terlewati sejak '.$tanggal($run['pada']),
            'map2' => 'Ganti shift Pagi↔Malam tanpa off pada '.$tanggal($run['pada']),
            'map3' => 'Shift Pagi setelah Malam pada '.$tanggal($run['pada']),
            default => '',
        };
    }

    private function panjangJendela(): int
    {
        return max(30, (int) config('dms_roster.reg_live.jendela_hari', 180));
    }

    /**
     * Seluruh tap satu SID selama jendela (±2 tap/hari). Kolom kode_sid
     * dibandingkan apa adanya supaya indeksnya terpakai (±80 ms).
     *
     * @return list<array{waktu:string,jenis:string,gate:string,lolos:bool}>
     */
    private function tarikTapKaryawan(string $sid, CarbonImmutable $awal, CarbonImmutable $hariIni): array
    {
        $rows = $this->olap->select(
            "SELECT s.tanggal_checkinout AS waktu, s.jenis_checkinout AS jenis, s.gate,
                    upper(trim(coalesce(s.status_lolos, ''))) = ? AS lolos
             FROM bcsid.mv_checkinout_rfid s
             WHERE s.kode_sid = ?
               AND s.tanggal_checkinout >= ?
               AND s.tanggal_checkinout < ?
             ORDER BY s.tanggal_checkinout",
            [
                strtoupper((string) config('dms_roster.populasi.status_lolos_scan', 'PASSED')),
                $sid,
                $awal->toDateTimeString(),
                // Sehari lebih supaya check-out shift malam hari ini ikut terbaca.
                $hariIni->addDays(2)->toDateTimeString(),
            ],
            (int) config('dms_roster.reg_live.timeout_ms', 20000),
        );

        return array_map(static fn (object $r): array => [
            'waktu' => (string) $r->waktu,
            'jenis' => strtoupper(trim((string) $r->jenis)),
            'gate' => trim((string) ($r->gate ?? '')) ?: '—',
            'lolos' => (bool) $r->lolos,
        ], $rows);
    }

    /**
     * Kode shift per hari per SID ("offset+P/M", dipisah koma). Agregasi
     * dikerjakan di Postgres supaya yang dikirim cuma ±24 rb baris, bukan
     * jutaan scan. Batas Pagi/Malam sama dengan sinkronisasi pola (menit 720).
     *
     * @return array<string, array<int, string>>
     */
    private function tarikScan(CarbonImmutable $awal, CarbonImmutable $hariIni): array
    {
        $rows = $this->olap->select(
            "SELECT d.kode_sid,
                    string_agg(d.o::text || CASE WHEN d.m < ? THEN 'P' ELSE 'M' END, ',') AS hari
             FROM (
                 SELECT trim(s.kode_sid) AS kode_sid,
                        s.tanggal_checkinout::date - ?::date AS o,
                        MIN(extract(hour FROM s.tanggal_checkinout) * 60
                            + extract(minute FROM s.tanggal_checkinout)) AS m
                 FROM bcsid.mv_checkinout_rfid s
                 WHERE upper(trim(coalesce(s.status_lolos, ''))) = ?
                   AND s.jenis_checkinout = 'CHECK IN'
                   AND s.tanggal_checkinout >= ?
                   AND s.tanggal_checkinout < ?
                   AND s.kode_sid IS NOT NULL
                   AND trim(s.kode_sid) <> ''
                 GROUP BY 1, 2
             ) d
             GROUP BY d.kode_sid",
            [
                (int) config('dms_roster.populasi.batas_menit_pagi', 720),
                $awal->toDateString(),
                strtoupper((string) config('dms_roster.populasi.status_lolos_scan', 'PASSED')),
                $awal->toDateTimeString(),
                $hariIni->addDay()->toDateTimeString(),
            ],
            (int) config('dms_roster.reg_live.timeout_ms', 20000),
        );

        $out = [];
        foreach ($rows as $row) {
            $hari = [];
            foreach (explode(',', (string) $row->hari) as $sel) {
                $hari[(int) substr($sel, 0, -1)] = substr($sel, -1);
            }
            $out[(string) $row->kode_sid] = $hari;
        }

        return $out;
    }
}
