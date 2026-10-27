<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\DmsRosterMasterUpload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Plan Kesesuaian Roster — tab ketiga kartu Scorecard Ringkasan Roster.
 *
 * Per karyawan yang punya plan roster DISETUJUI (dms_roster_master_jadwal):
 *   1. masa lalu s/d hari ini = tap RFID (deret P/M/o yang sama dengan kartu
 *      aturan live, jadi hari ke-N hari ini identik);
 *   2. sesudah hari ini = kode plan (D1–D13 / DS = P, N1–N7 / NS = M, OFF/S/I = off,
 *      CT = cuti), sampai tanggal plan pertama yang kosong;
 *   3. deret gabungan dinormalisasi rule engine (off > 5 hari = cuti) lalu
 *      dijalankan maju dengan aturan banned reg_live.aturan (> 70 hari
 *      on-site, > 13 hari kerja berturut-turut).
 * Hari lampau di periode plan juga dicocokkan dengan tap (kepatuhan plan).
 *
 * Status (urutan prioritas):
 *   SUDAH_MELANGGAR  per hari ini sudah lewat batas (menurut RFID)
 *   AKAN_70 / AKAN_13  plan membuat karyawan lewat batas (tanggal paling awal menentukan)
 *   PLAN_HABIS       plan berakhir dalam plan_kesesuaian.segera_habis_hari (atau sudah lewat)
 *   AMAN             sampai akhir plan tetap dalam batas
 *   LONGGAR          kategori yang dikecualikan aturan (DmsRosterRuleEngine::isLonggar)
 *
 * Hanya tampilan — tidak menulis ke Master Roster Banned. Pola sumbernya sama
 * dengan DmsLongShiftService: dms:sync-roster-rfid (Tahap 9) memanggil
 * segarkan(), halaman cukup ambil() — request web di server tidak bisa ke OLAP.
 */
final class DmsRosterPlanKesesuaianService
{
    public const STATUS = ['SUDAH_MELANGGAR', 'AKAN_70', 'AKAN_13', 'PLAN_HABIS', 'AMAN', 'LONGGAR'];

    private const KUNCI_SEGAR = 'dms_roster:plan_kesesuaian:segar:v1';

    private const KUNCI_TERAKHIR = 'dms_roster:plan_kesesuaian:terakhir:v1';

    private const KUNCI_GAGAL = 'dms_roster:plan_kesesuaian:gagal:v1';

    private ?string $galat = null;

    public function __construct(
        private readonly DmsRosterRuleEngine $engine,
        private readonly DmsRosterRfidReader $rfid,
    ) {}

    /**
     * Cache segar → hitung langsung → hasil terakhir yang berhasil (ditandai
     * 'basi'). null hanya bila belum pernah berhasil.
     *
     * @return array<string, mixed>|null
     */
    public function ambil(): ?array
    {
        $tersimpan = Cache::get(self::KUNCI_SEGAR);
        if (is_array($tersimpan)) {
            return $tersimpan;
        }

        $gagal = Cache::get(self::KUNCI_GAGAL);
        if (is_string($gagal)) {
            $this->galat = $gagal;

            return $this->cadangan();
        }

        return $this->segarkan() ?? $this->cadangan();
    }

    /**
     * Hitung ulang dari plan + RFID sekarang juga dan simpan.
     *
     * @return array<string, mixed>|null
     */
    public function segarkan(): ?array
    {
        $hariIni = CarbonImmutable::now()->startOfDay();
        $awal = $hariIni->subDays(max(30, (int) config('dms_roster.reg_live.jendela_hari', 180)) - 1);

        try {
            $mulai = microtime(true);
            $plan = $this->planDisetujui($awal);
            $sids = array_keys($plan);
            $master = $sids === [] ? collect() : DB::table('dms_roster_karyawan')->whereIn('kode_sid', $sids)
                ->get(['kode_sid', 'kategori', 'kode_pt'])->keyBy(static fn ($r): string => strtoupper(trim((string) $r->kode_sid)));
            $banned = $sids === [] ? [] : array_flip(DmsRosterBannedAktif::query()->whereIn('m.sid', $sids)
                ->pluck('m.sid')->map(static fn ($s): string => strtoupper(trim((string) $s)))->all());
            $scan = $sids === [] ? [] : $this->rfid->kodeHarian($awal, $hariIni, $sids);
            // Durasi IN→OUT hanya untuk rentang timeline (aturan jam kerja).
            $awalJam = $hariIni->subDays($this->mundurMaks());
            $geser = (int) $awal->diffInDays($awalJam);
            $durasi = [];
            foreach ($this->rfid->durasiHarian($awalJam, $hariIni, $sids) as $sidJam => $hari) {
                foreach ($hari as $o => $menit) {
                    $durasi[strtoupper(trim((string) $sidJam))][$o + $geser] = $menit;
                }
            }
            $detik = microtime(true) - $mulai;
        } catch (Throwable $e) {
            $this->galat = mb_substr($e->getMessage(), 0, 200);
            Log::warning('DmsRoster plan kesesuaian gagal: '.$e->getMessage());
            Cache::put(self::KUNCI_GAGAL, $this->galat, 300);

            return null;
        }

        $baris = [];
        foreach ($plan as $sid => $p) {
            $m = $master->get($sid);
            $kategori = (string) ($m->kategori ?? '');
            $baris[] = $p['identitas'] + [
                'sid' => $sid,
                'pt' => (string) ($m->kode_pt ?? ''),
                'kategori' => $kategori,
                'banned_aktif' => isset($banned[$sid]),
            ] + $this->nilai($scan[$sid] ?? [], $awal, $hariIni, $p['jadwal'], $this->engine->isLonggar($kategori), $p['menit'], $durasi[$sid] ?? []);
        }
        usort($baris, static fn (array $a, array $b): int => [array_search($a['status'], self::STATUS, true), $a['tgl_langgar'] ?? '9999', -$a['hari_onsite'], $a['nama']]
            <=> [array_search($b['status'], self::STATUS, true), $b['tgl_langgar'] ?? '9999', -$b['hari_onsite'], $b['nama']]);

        $hasil = [
            'basi' => false,
            'hari_ini' => $hariIni->toDateString(),
            'diambil' => CarbonImmutable::now()->toDateTimeString(),
            'detik' => round($detik, 1),
            'parameter' => $this->aturan() + ['segera_habis_hari' => $this->segeraHabis(), 'jam_kerja_maks' => $this->jamMaks()],
            'ringkas' => $this->ringkas($baris),
            'baris' => $baris,
        ];

        Cache::put(self::KUNCI_SEGAR, $hasil, (int) config('dms_roster.plan_kesesuaian.cache_ttl', 4500));
        Cache::put(self::KUNCI_TERAKHIR, $hasil, now()->addDays(7));
        Cache::forget(self::KUNCI_GAGAL);

        return $hasil;
    }

    public function galatTerakhir(): ?string
    {
        return $this->galat;
    }

    /**
     * Penilaian satu karyawan — murni, tanpa DB.
     *
     * @param  array<int, string>  $hariScan  offset dari $awal => 'P'|'M' (hasil DmsRosterRfidReader::kodeHarian).
     * @param  array<string, string>  $jadwal  tanggal ISO => kode plan.
     * @param  array<string, int|null>  $menitPlan  tanggal ISO => menit kerja di plan.
     * @param  array<int, int>  $menitRfid  offset dari $awal => menit IN→OUT (DmsRosterRfidReader::durasiHarian).
     * @return array<string, mixed>
     */
    public function nilai(
        array $hariScan,
        CarbonImmutable $awal,
        CarbonImmutable $hariIni,
        array $jadwal,
        bool $longgar = false,
        array $menitPlan = [],
        array $menitRfid = [],
    ): array {
        $aturan = $this->aturan();
        $idxHariIni = (int) $awal->diffInDays($hariIni);
        $iso = static fn (int $i): string => $awal->addDays($i)->toDateString();

        // 1. RFID s/d kemarin; hari ini dari tap, atau plan bila belum tap.
        $deret = '';
        $sumber = '';
        for ($i = 0; $i < $idxHariIni; $i++) {
            $deret .= ($hariScan[$i] ?? null) === 'M' ? 'M' : (isset($hariScan[$i]) ? 'P' : 'o');
            $sumber .= 'r';
        }
        $kiniPlan = self::kodeDeret($jadwal[$iso($idxHariIni)] ?? null);
        if (isset($hariScan[$idxHariIni])) {
            $deret .= $hariScan[$idxHariIni] === 'M' ? 'M' : 'P';
            $sumber .= 'r';
        } elseif ($kiniPlan !== null) {
            $deret .= $kiniPlan;
            $sumber .= 'p';
        }
        // Hari ini belum tap & tidak ada plan: dinilai sampai kemarin, tanpa proyeksi.
        $kini = strlen($deret) - 1;

        // 2. Proyeksi: plan sesudah hari ini sampai tanggal plan pertama yang kosong.
        if ($kini === $idxHariIni) {
            for ($i = $idxHariIni + 1; ($c = self::kodeDeret($jadwal[$iso($i)] ?? null)) !== null; $i++) {
                $deret .= $c;
                $sumber .= 'p';
            }
        }

        // 3. Jalankan maju dengan pola yang sudah dinormalisasi (off panjang = cuti).
        $pola = $this->engine->normalisasiPola($deret);
        $n = strlen($pola);
        $onsite = [];
        $kerja = [];
        $tgl71 = null;
        $tgl14 = null;
        for ($i = 0, $on = 0, $ke = 0; $i < $n; $i++) {
            $on = $pola[$i] !== 'c' ? $on + 1 : 0;
            $ke = $pola[$i] === 'P' || $pola[$i] === 'M' ? $ke + 1 : 0;
            $onsite[$i] = $on;
            $kerja[$i] = $ke;
            if ($i > $kini && $tgl71 === null && $on === $aturan['onsite_maks'] + 1) {
                $tgl71 = $iso($i);
            }
            if ($i > $kini && $tgl14 === null && $ke === $aturan['beruntun_maks'] + 1) {
                $tgl14 = $iso($i);
            }
        }

        // Cuti pertama yang dijadwalkan plan sesudah hari ini (penyelesai pelanggaran).
        $cutiPlan = null;
        for ($i = $kini + 1; $i < $n && $cutiPlan === null; $i++) {
            $cutiPlan = $pola[$i] === 'c' ? $iso($i) : null;
        }

        $hariOnsite = $kini >= 0 ? $onsite[$kini] : 0;
        $hariBeruntun = $kini >= 0 ? $kerja[$kini] : 0;
        $sudahOnsite = $hariOnsite > $aturan['onsite_maks'];
        $sudahBeruntun = $hariBeruntun > $aturan['beruntun_maks'];
        $proyeksiSampai = $n - 1 > $idxHariIni ? $iso($n - 1) : null;
        $tanggalPlan = array_keys($jadwal);
        sort($tanggalPlan);
        $planSampai = $tanggalPlan === [] ? null : end($tanggalPlan);
        $batasHabis = $hariIni->addDays($this->segeraHabis())->toDateString();

        [$status, $tglLanggar] = match (true) {
            $longgar => ['LONGGAR', null],
            $sudahOnsite || $sudahBeruntun => ['SUDAH_MELANGGAR', $hariIni->toDateString()],
            $tgl71 !== null && ($tgl14 === null || $tgl71 <= $tgl14) => ['AKAN_70', $tgl71],
            $tgl14 !== null => ['AKAN_13', $tgl14],
            $proyeksiSampai === null || $proyeksiSampai < $batasHabis => ['PLAN_HABIS', null],
            default => ['AMAN', null],
        };

        // Timeline mundur sampai awal masa on-site berjalan (+3 hari konteks),
        // minimal timeline_mundur_hari, maksimal timeline_mundur_maks.
        $mundur = max(0, (int) config('dms_roster.plan_kesesuaian.timeline_mundur_hari', 21));
        $runMulai = $kini - $hariOnsite + 1;
        $tlAwal = max(0, $kini - $this->mundurMaks(), min($kini - $mundur, $runMulai - 3));

        $jam = $this->nilaiJam($menitRfid, $menitPlan, $jadwal, $tlAwal, $kini, $n, $iso);

        return [
            'status' => $status,
            'tgl_langgar' => $tglLanggar,
            'hari_onsite' => $hariOnsite,
            'hari_beruntun' => $hariBeruntun,
            // Run on-site sudah berjalan sejak awal jendela RFID → angka sebenarnya ≥ hari_onsite.
            'onsite_terpotong' => $kini >= 0 && $hariOnsite === $kini + 1,
            'cuti_plan' => $cutiPlan,
            'sudah_onsite' => $sudahOnsite,
            'sudah_beruntun' => $sudahBeruntun,
            'tgl_71' => $longgar ? null : $tgl71,
            'tgl_14' => $longgar ? null : $tgl14,
            'onsite_akhir' => $n > 0 ? $onsite[$n - 1] : 0,
            'proyeksi_sampai' => $proyeksiSampai,
            'plan_dari' => $tanggalPlan === [] ? null : $tanggalPlan[0],
            'plan_sampai' => $planSampai,
            'kepatuhan' => self::kepatuhan($hariScan, $awal, $hariIni, $jadwal),
            // Tiga pengecekan: sudah ATAU akan (menurut plan) melewati batas.
            'lebih_70' => ! $longgar && ($sudahOnsite || $tgl71 !== null),
            'lebih_13' => ! $longgar && ($sudahBeruntun || $tgl14 !== null),
            'lebih_jam' => $jam['rfid_lebih'] > 0 || $jam['plan_lebih'] > 0,
            'jam' => $jam,
            // Timeline: sebagian hari lampau + seluruh proyeksi plan.
            'tl_awal' => $n > 0 ? $iso($tlAwal) : null,
            'tl_kini' => $kini - $tlAwal,
            // Hitungan hari ke-N di sel pertama timeline; sel berikutnya dihitung di browser.
            'tl_on_awal' => $onsite[$tlAwal] ?? 0,
            'tl_ke_awal' => $kerja[$tlAwal] ?? 0,
            'tl_pola' => $n > 0 ? substr($pola, $tlAwal) : '',
            'tl_sumber' => $n > 0 ? substr($sumber, $tlAwal) : '',
            'tl_plan' => array_map(static fn (int $i): string => (string) ($jadwal[$iso($i)] ?? ''), $n > 0 ? range($tlAwal, $n - 1) : []),
            // Menit kerja per sel: tap IN→OUT untuk hari lampau, jam plan untuk proyeksi.
            'tl_menit' => array_map(
                static fn (int $i): ?int => $i <= $kini ? ($menitRfid[$i] ?? null) : ($menitPlan[$iso($i)] ?? null),
                $n > 0 ? range($tlAwal, $n - 1) : [],
            ),
        ];
    }

    /**
     * Aturan jam kerja > plan_kesesuaian.jam_kerja_maks: tap IN→OUT di rentang
     * timeline (hari lampau) dan jam kerja plan untuk hari kerja sesudah hari ini.
     *
     * @param  array<int, int>  $menitRfid
     * @param  array<string, int|null>  $menitPlan
     * @param  array<string, string>  $jadwal
     * @param  callable(int): string  $iso
     * @return array<string, mixed>
     */
    private function nilaiJam(array $menitRfid, array $menitPlan, array $jadwal, int $dari, int $kini, int $n, callable $iso): array
    {
        $maks = $this->jamMaks() * 60;
        $out = [
            'maks_jam' => $this->jamMaks(),
            'rfid_dinilai' => 0, 'rfid_lebih' => 0, 'rfid_terakhir' => null, 'rfid_terakhir_menit' => null, 'rfid_maks_menit' => null,
            'plan_dinilai' => 0, 'plan_lebih' => 0, 'plan_pertama' => null, 'plan_maks_menit' => null,
        ];

        for ($i = max(0, $dari); $i <= $kini; $i++) {
            $m = $menitRfid[$i] ?? null;
            if ($m === null) {
                continue;
            }
            $out['rfid_dinilai']++;
            $out['rfid_maks_menit'] = max($out['rfid_maks_menit'] ?? 0, $m);
            if ($m > $maks) {
                $out['rfid_lebih']++;
                $out['rfid_terakhir'] = $iso($i);
                $out['rfid_terakhir_menit'] = $m;
            }
        }

        for ($i = $kini + 1; $i < $n; $i++) {
            $tgl = $iso($i);
            $c = self::kodeDeret($jadwal[$tgl] ?? null);
            $m = $menitPlan[$tgl] ?? null;
            if (($c !== 'P' && $c !== 'M') || $m === null) {
                continue;
            }
            $out['plan_dinilai']++;
            $out['plan_maks_menit'] = max($out['plan_maks_menit'] ?? 0, $m);
            if ($m > $maks) {
                $out['plan_lebih']++;
                $out['plan_pertama'] ??= $tgl;
            }
        }

        return $out;
    }

    /**
     * Kode plan → karakter deret rule engine; null = bukan kode yang dikenal.
     */
    public static function kodeDeret(?string $kode): ?string
    {
        $k = strtoupper(trim((string) $kode));

        return match (true) {
            preg_match('/^(D\d{1,2}|DS)$/', $k) === 1 => 'P',
            preg_match('/^(N\d{1,2}|NS)$/', $k) === 1 => 'M',
            $k === 'CT' => 'c',
            in_array($k, ['OFF', 'OF', 'S', 'I'], true) => 'o',
            default => null,
        };
    }

    /**
     * Plan vs tap untuk tanggal plan yang sudah lewat (hari ini ikut bila
     * sudah tap). Beda shift (plan Day, tap malam) tetap dihitung hadir sesuai.
     *
     * @param  array<int, string>  $hariScan
     * @param  array<string, string>  $jadwal
     * @return array{dinilai:int,sesuai:int,kerja_tanpa_tap:int,tap_saat_off:int,beda_shift:int,persen:?float}
     */
    public static function kepatuhan(array $hariScan, CarbonImmutable $awal, CarbonImmutable $hariIni, array $jadwal): array
    {
        $out = ['dinilai' => 0, 'sesuai' => 0, 'kerja_tanpa_tap' => 0, 'tap_saat_off' => 0, 'beda_shift' => 0];
        $idxHariIni = (int) $awal->diffInDays($hariIni);
        foreach ($jadwal as $tgl => $kode) {
            $c = self::kodeDeret($kode);
            $i = (int) $awal->diffInDays(CarbonImmutable::parse($tgl), false);
            if ($c === null || $i < 0 || $i > $idxHariIni || ($i === $idxHariIni && ! isset($hariScan[$i]))) {
                continue;
            }
            $tap = $hariScan[$i] ?? null;
            $planKerja = $c === 'P' || $c === 'M';
            $out['dinilai']++;
            if ($planKerja && $tap === null) {
                $out['kerja_tanpa_tap']++;
            } elseif (! $planKerja && $tap !== null) {
                $out['tap_saat_off']++;
            } else {
                $out['sesuai']++;
                $out['beda_shift'] += $planKerja && $tap !== $c ? 1 : 0;
            }
        }

        return $out + ['persen' => $out['dinilai'] === 0 ? null : round($out['sesuai'] / $out['dinilai'] * 100, 1)];
    }

    /**
     * Saring + paginasi untuk endpoint JSON.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return array{baris: list<array<string, mixed>>, total: int, halaman: int, total_halaman: int}
     */
    public function saring(array $baris, string $status, string $q, string $pt, int $halaman, int $perHalaman, string $cek = ''): array
    {
        $q = mb_strtolower(trim($q));
        $hasil = array_values(array_filter($baris, static function (array $r) use ($status, $q, $pt, $cek): bool {
            if ($status !== '' && $r['status'] !== $status) {
                return false;
            }
            // cek = '70' | '13' | 'jam' → hanya yang melewati batas itu.
            if (in_array($cek, ['70', '13', 'jam'], true) && ! ($r['lebih_'.$cek] ?? false)) {
                return false;
            }
            if ($pt !== '' && $r['perusahaan'] !== $pt) {
                return false;
            }

            return $q === '' || str_contains(mb_strtolower($r['nama'].' '.$r['sid'].' '.$r['perusahaan'].' '.$r['jabatan'].' '.$r['site']), $q);
        }));

        $total = count($hasil);
        $totalHalaman = max(1, (int) ceil($total / max(1, $perHalaman)));
        $halaman = min(max(1, $halaman), $totalHalaman);

        return [
            'baris' => array_slice($hasil, ($halaman - 1) * $perHalaman, $perHalaman),
            'total' => $total,
            'halaman' => $halaman,
            'total_halaman' => $totalHalaman,
        ];
    }

    /**
     * Plan disetujui per SID mulai $awal. Untuk (SID, tanggal) yang ada di
     * beberapa roster, roster yang disetujui paling akhir menang — sama
     * dengan DmsRosterMasterService::jadwalBerlaku().
     *
     * @return array<string, array{identitas: array<string, string>, jadwal: array<string, string>}>
     */
    private function planDisetujui(CarbonImmutable $awal): array
    {
        $rows = DB::table('dms_roster_master_jadwal as j')
            ->join('dms_roster_master_upload as u', 'u.id', '=', 'j.upload_id')
            ->where('u.status', DmsRosterMasterUpload::STATUS_APPROVED)
            ->where('j.tanggal', '>=', $awal->toDateString())
            ->orderByRaw('COALESCE(u.direview_pada, u.created_at)')
            ->orderBy('u.id')
            ->get(['j.kode_sid', 'j.nama', 'j.perusahaan', 'j.jabatan', 'j.site', 'j.tanggal', 'j.kode', 'j.jam_masuk', 'j.jam_keluar', 'j.menit_kerja']);

        $plan = [];
        foreach ($rows as $r) {
            $sid = strtoupper(trim((string) $r->kode_sid));
            if ($sid === '') {
                continue;
            }
            $plan[$sid] ??= ['identitas' => [], 'jadwal' => [], 'menit' => []];
            // Identitas dari baris paling akhir (prioritas tertinggi).
            $plan[$sid]['identitas'] = [
                'nama' => (string) ($r->nama ?? ''),
                'perusahaan' => trim((string) ($r->perusahaan ?? '')),
                'jabatan' => (string) ($r->jabatan ?? ''),
                'site' => (string) ($r->site ?? ''),
            ];
            $tgl = CarbonImmutable::parse((string) $r->tanggal)->toDateString();
            $plan[$sid]['jadwal'][$tgl] = (string) $r->kode;
            $plan[$sid]['menit'][$tgl] = self::menitPlan($r->menit_kerja, $r->jam_masuk, $r->jam_keluar);
        }

        return $plan;
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, mixed>
     */
    private function ringkas(array $baris): array
    {
        $out = array_fill_keys(self::STATUS, 0) + ['karyawan' => count($baris), 'dinilai' => 0, 'sesuai' => 0, 'lebih_70' => 0, 'lebih_13' => 0, 'lebih_jam' => 0];
        foreach ($baris as $r) {
            $out[$r['status']]++;
            foreach (['lebih_70', 'lebih_13', 'lebih_jam'] as $k) {
                $out[$k] += ($r[$k] ?? false) ? 1 : 0;
            }
            $out['dinilai'] += $r['kepatuhan']['dinilai'];
            $out['sesuai'] += $r['kepatuhan']['sesuai'];
        }
        $out['persen_patuh'] = $out['dinilai'] === 0 ? null : round($out['sesuai'] / $out['dinilai'] * 100, 1);
        $out['perusahaan'] = array_values(array_unique(array_filter(array_column($baris, 'perusahaan'))));
        sort($out['perusahaan'], SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    /**
     * @return array{onsite_maks:int, beruntun_maks:int}
     */
    private function aturan(): array
    {
        $a = (array) config('dms_roster.reg_live.aturan', []);

        return [
            'onsite_maks' => (int) ($a['onsite_maks'] ?? 70),
            'beruntun_maks' => (int) ($a['beruntun_maks'] ?? 13),
        ];
    }

    private function jamMaks(): int
    {
        return max(1, (int) config('dms_roster.plan_kesesuaian.jam_kerja_maks', 9));
    }

    private function mundurMaks(): int
    {
        return max(7, (int) config('dms_roster.plan_kesesuaian.timeline_mundur_maks', 90));
    }

    /**
     * Menit kerja satu baris plan: menit_kerja bila ada, kalau tidak dari
     * jam masuk–keluar (keluar lebih awal = lewat tengah malam).
     */
    public static function menitPlan(mixed $menit, mixed $masuk, mixed $keluar): ?int
    {
        if ($menit !== null && $menit !== '') {
            return (int) $menit;
        }
        if ($masuk === null || $keluar === null || $masuk === '' || $keluar === '') {
            return null;
        }
        $keMenit = static function (string $jam): int {
            $b = explode(':', $jam);

            return (int) $b[0] * 60 + (int) ($b[1] ?? 0);
        };
        $selisih = $keMenit((string) $keluar) - $keMenit((string) $masuk);

        return $selisih <= 0 ? $selisih + 1440 : $selisih;
    }

    private function segeraHabis(): int
    {
        return max(0, (int) config('dms_roster.plan_kesesuaian.segera_habis_hari', 7));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cadangan(): ?array
    {
        $terakhir = Cache::get(self::KUNCI_TERAKHIR);

        return is_array($terakhir) ? ['basi' => true, 'galat' => $this->galat] + $terakhir : null;
    }
}
