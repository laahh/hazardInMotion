<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\DmsRosterMasterUpload;
use App\Services\SportEvaluation\RfidClickHouseClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Data tab People / Org chart / Time off di Overview Roster — dibuat RINGAN:
 * penyaringan, penghitungan & paging dikerjakan database, PHP hanya menerima
 * baris yang ditampilkan (di prod: 21 ribu karyawan, 1,5 juta baris jadwal).
 *
 * - Karyawan aktif: ClickHouse `obds.bep_vw_safety_karyawan_aktif` (query per
 *   halaman / GROUP BY), bukan memuat semua karyawan ke PHP.
 * - Time off: MySQL. Detail per orang hanya untuk satu perusahaan (SID-nya lewat
 *   index kode_sid+tanggal); "semua perusahaan" cukup jumlah per perusahaan.
 * - Semua perusahaan & site ikut (termasuk PT Berau Coal, yang dikecualikan di kartu
 *   Cakupan Overview); hanya jabatan VISITOR yang tidak ikut.
 */
final class DmsRosterPeopleService
{
    private const T = 'obds.bep_vw_safety_karyawan_aktif';

    private const KUNCI_OPSI = 'dms_roster:people:opsi:v3';

    private const TTL_OPSI = 1800;

    private const TTL_DATA = 600;

    private const SID_BATCH = 1000;

    /** Urutan tingkat jabatan fungsional di Org chart (atas → bawah); lainnya di akhir. */
    public const URUTAN_JABATAN = [
        'Presiden Direktur', 'Direktur', 'General Manager', 'Manager', 'Superintendent',
        'Supervisor/Officer', 'Engineer/Specialist', 'Foreman/Group Leader', 'Administrator',
        'Technician', 'Mekanik', 'Operator', 'Security', 'Crew',
    ];

    public const LABEL_KODE = ['CT' => 'Cuti', 'S' => 'Sakit', 'I' => 'Izin'];

    public function __construct(private readonly RfidClickHouseClient $clickhouse)
    {
    }

    /**
     * Pilihan filter: perusahaan => [site => jumlah karyawan].
     * Null bila ClickHouse tidak terjangkau.
     *
     * @return array<string, array<string, int>>|null
     */
    public function opsi(): ?array
    {
        $ada = Cache::get(self::KUNCI_OPSI);
        if (is_array($ada)) {
            return $ada;
        }

        try {
            $rows = $this->clickhouse->select('SELECT '.self::KOLOM_PT.' AS pt, '.self::KOLOM_SITE.' AS site, count() AS n FROM '.self::T
                .' FINAL WHERE '.self::dasar().' GROUP BY pt, site');
        } catch (RuntimeException) {
            return null;
        }

        $out = [];
        foreach ($rows as $r) {
            $pt = (string) $r['pt'];
            $site = (string) $r['site'];
            if ($pt === '') {
                continue;
            }
            $out[$pt][$site === '' ? '-' : $site] = (int) $r['n'];
        }
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($out as &$s) {
            ksort($s, SORT_NATURAL | SORT_FLAG_CASE);
        }
        unset($s);
        Cache::put(self::KUNCI_OPSI, $out, self::TTL_OPSI);

        return $out;
    }

    /**
     * Satu halaman tab People.
     *
     * @param  array<string, array<string, int>>  $opsi
     * @return array{baris: list<array<string,mixed>>, total: int}
     */
    public function halaman(array $opsi, string $pt, string $site, string $cari, int $halaman, int $perHalaman, string $dept = ''): array
    {
        $where = self::saringan($opsi, $pt, $site, $cari, $dept);
        $total = (int) ($this->clickhouse->select('SELECT count() AS n FROM '.self::T.' FINAL WHERE '.$where)[0]['n'] ?? 0);
        $baris = $total === 0 ? [] : $this->clickhouse->select('SELECT '.self::KOLOM_ORANG.' FROM '.self::T.' FINAL WHERE '.$where
            .' ORDER BY pt, nama LIMIT '.max(1, $perHalaman).' OFFSET '.max(0, ($halaman - 1) * $perHalaman));

        return ['baris' => array_map([self::class, 'rapikan'], $baris), 'total' => $total];
    }

    /**
     * Grup Org chart (jumlah per perusahaan/site/departemen/jabatan) dari ClickHouse.
     * Nama orang ikut hanya bila satu perusahaan dipilih.
     *
     * @param  array<string, array<string, int>>  $opsi
     * @return list<array{perusahaan:string,site:string,departemen:string,jabatan:string,jumlah:int,orang:list<array<string,mixed>>}>
     */
    public function grupOrg(array $opsi, string $pt, string $site, string $dept = ''): array
    {
        $kunci = 'dms_roster:people:org:v2:'.md5($pt.'|'.$site.'|'.$dept);

        return Cache::remember($kunci, self::TTL_DATA, function () use ($opsi, $pt, $site, $dept): array {
            $where = self::saringan($opsi, $pt, $site, '', $dept);
            if ($pt === '') {
                $rows = $this->clickhouse->select('SELECT '.self::KOLOM_PT.' AS pt, '.self::KOLOM_SITE.' AS site, '.self::KOLOM_DEPT.' AS departemen, '
                    .self::KOLOM_JABATAN.' AS jab, count() AS n FROM '.self::T.' FINAL WHERE '.$where.' GROUP BY pt, site, departemen, jab');

                return array_map(static fn (array $r): array => [
                    'perusahaan' => (string) $r['pt'], 'site' => (string) $r['site'], 'departemen' => (string) $r['departemen'],
                    'jabatan' => (string) $r['jab'], 'jumlah' => (int) $r['n'], 'orang' => [],
                ], $rows);
            }

            return self::grupDariOrang(array_map([self::class, 'rapikan'], $this->clickhouse->select(
                'SELECT '.self::KOLOM_ORANG.' FROM '.self::T.' FINAL WHERE '.$where
            )));
        });
    }

    /**
     * Karyawan satu perusahaan (opsional site) — untuk detail Time off.
     *
     * @param  array<string, array<string, int>>  $opsi
     * @return list<array<string, mixed>>
     */
    public function karyawanPt(array $opsi, string $pt, string $site, string $dept = ''): array
    {
        return array_map([self::class, 'rapikan'], $this->clickhouse->select(
            'SELECT '.self::KOLOM_ORANG.' FROM '.self::T.' FINAL WHERE '.self::saringan($opsi, $pt, $site, '', $dept)
        ));
    }

    /**
     * Departemen (beserta jumlah karyawan) untuk perusahaan/site terpilih — pilihan filter.
     * Dihitung ClickHouse, di-cache 30 menit.
     *
     * @param  array<string, array<string, int>>  $opsi
     * @return array<string, int> departemen => jumlah ('-' = tanpa departemen)
     */
    public function departemen(array $opsi, string $pt, string $site): array
    {
        $kunci = 'dms_roster:people:dept:v1:'.md5($pt.'|'.$site);

        return Cache::remember($kunci, self::TTL_OPSI, function () use ($opsi, $pt, $site): array {
            $out = [];
            foreach ($this->clickhouse->select('SELECT '.self::KOLOM_DEPT.' AS d, count() AS n FROM '.self::T.' FINAL WHERE '
                .self::saringan($opsi, $pt, $site, '').' GROUP BY d') as $r) {
                $out[(string) $r['d'] === '' ? '-' : (string) $r['d']] = (int) $r['n'];
            }
            uksort($out, static fn ($a, $b): int => strnatcasecmp((string) $a, (string) $b));

            return $out;
        });
    }

    /**
     * Status roster untuk sekumpulan SID (satu halaman People) — query kecil lewat index kode_sid.
     *
     * @param  list<string>  $sids
     * @return array<string, array{disetujui:bool,menunggu:bool,otomatis:bool}>
     */
    public static function statusRoster(array $sids): array
    {
        if ($sids === []) {
            return [];
        }
        $rows = DB::table('dms_roster_master_jadwal as j')
            ->join('dms_roster_master_upload as u', 'u.id', '=', 'j.upload_id')
            ->where('u.status', '<>', DmsRosterMasterUpload::STATUS_REJECTED)
            ->whereIn('j.kode_sid', $sids)
            ->groupBy(DB::raw('UPPER(TRIM(j.kode_sid))'))
            ->selectRaw('UPPER(TRIM(j.kode_sid)) AS sid, MAX(u.status = ?) AS ok, MAX(u.status = ?) AS tunggu, MIN(u.diupload_oleh = ?) AS oto',
                [DmsRosterMasterUpload::STATUS_APPROVED, DmsRosterMasterUpload::STATUS_PENDING, DmsRosterOtomatisService::penanda()])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->sid] = ['disetujui' => (bool) $r->ok, 'menunggu' => (bool) $r->tunggu, 'otomatis' => (bool) $r->oto];
        }

        return $out;
    }

    /**
     * Hari CT/S/I yang BERLAKU untuk daftar SID dalam satu bulan: roster disetujui,
     * dan tidak ditimpa roster disetujui yang lebih akhir pada (SID, tanggal) yang
     * sama (aturan jadwalBerlaku). Per potongan 1000 SID lewat index kode_sid+tanggal.
     *
     * @param  list<string>  $sids
     * @return list<array{0:string,1:string,2:string}> [sid, tanggal, kode]
     */
    public static function tidakMasukSid(array $sids, CarbonImmutable $bulan): array
    {
        $kunci = 'dms_roster:people:tm:v1:'.$bulan->format('Y-m').':'.md5(implode(',', $sids)).':'.DmsRosterMasterCache::versi();

        return Cache::remember($kunci, self::TTL_DATA, static function () use ($sids, $bulan): array {
            $out = [];
            foreach (array_chunk($sids, self::SID_BATCH) as $potongan) {
                foreach (self::queryTidakMasuk($bulan)->whereIn('j.kode_sid', $potongan)
                    ->selectRaw('UPPER(TRIM(j.kode_sid)) AS sid, j.tanggal, j.kode')->cursor() as $r) {
                    $out[] = [(string) $r->sid, substr((string) $r->tanggal, 0, 10), strtoupper((string) $r->kode)];
                }
            }

            return $out;
        });
    }

    /**
     * Jumlah hari CT/S/I berlaku per perusahaan (ejaan di roster) satu bulan — untuk
     * "Semua perusahaan" (hasil kecil, dihitung MySQL).
     *
     * @return list<array{perusahaan:string,orang:int,CT:int,S:int,I:int,total:int}>
     */
    public static function tidakMasukPerPt(CarbonImmutable $bulan): array
    {
        $kunci = 'dms_roster:people:tm_pt:v1:'.$bulan->format('Y-m').':'.DmsRosterMasterCache::versi();

        return Cache::remember($kunci, self::TTL_DATA, static function () use ($bulan): array {
            $rows = self::queryTidakMasuk($bulan)
                ->groupBy(DB::raw("TRIM(COALESCE(j.perusahaan, ''))"))
                ->selectRaw("TRIM(COALESCE(j.perusahaan, '')) AS pt, COUNT(DISTINCT j.kode_sid) AS orang,
                    SUM(j.kode = 'CT') AS ct, SUM(j.kode = 'S') AS s, SUM(j.kode = 'I') AS i, COUNT(*) AS total")
                ->get();
            $out = [];
            foreach ($rows as $r) {
                if ((string) $r->pt === '') {
                    continue;
                }
                $out[] = ['perusahaan' => (string) $r->pt, 'orang' => (int) $r->orang, 'CT' => (int) $r->ct, 'S' => (int) $r->s, 'I' => (int) $r->i, 'total' => (int) $r->total];
            }
            usort($out, static fn (array $a, array $b): int => [$b['total'], $a['perusahaan']] <=> [$a['total'], $b['perusahaan']]);

            return $out;
        });
    }

    /**
     * Pohon Org chart dari grup: perusahaan → site → departemen → tingkat jabatan.
     *
     * @param  list<array{perusahaan:string,site:string,departemen:string,jabatan:string,jumlah:int,orang:list<array<string,mixed>>}>  $grup
     * @return list<array{nama:string,jumlah:int,site:list<array<string,mixed>>}>
     */
    public static function org(array $grup): array
    {
        $pohon = [];
        foreach ($grup as $g) {
            $site = $g['site'] === '' ? '-' : $g['site'];
            $dept = $g['departemen'] === '' ? 'Tanpa departemen' : $g['departemen'];
            $jab = $g['jabatan'] === '' ? 'Tanpa jabatan' : $g['jabatan'];
            $sel = &$pohon[$g['perusahaan']][$site][$dept][$jab];
            $sel ??= ['jumlah' => 0, 'orang' => []];
            $sel['jumlah'] += $g['jumlah'];
            array_push($sel['orang'], ...$g['orang']);
            unset($sel);
        }

        $urutJab = array_flip(self::URUTAN_JABATAN);
        $out = [];
        ksort($pohon, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($pohon as $pt => $sites) {
            ksort($sites, SORT_NATURAL | SORT_FLAG_CASE);
            $siteOut = [];
            foreach ($sites as $site => $depts) {
                ksort($depts, SORT_NATURAL | SORT_FLAG_CASE);
                $deptOut = [];
                foreach ($depts as $dept => $jabs) {
                    uksort($jabs, static fn ($a, $b): int => [$urutJab[$a] ?? 99, (string) $a] <=> [$urutJab[$b] ?? 99, (string) $b]);
                    $jabOut = [];
                    foreach ($jabs as $jab => $isi) {
                        usort($isi['orang'], static fn (array $x, array $y): int => strcasecmp($x['nama'], $y['nama']));
                        $jabOut[] = ['nama' => (string) $jab, 'jumlah' => $isi['jumlah'], 'orang' => $isi['orang']];
                    }
                    $deptOut[] = ['nama' => (string) $dept, 'jumlah' => array_sum(array_column($jabOut, 'jumlah')), 'jabatan' => $jabOut];
                }
                $siteOut[] = ['nama' => (string) $site, 'jumlah' => array_sum(array_column($deptOut, 'jumlah')), 'departemen' => $deptOut];
            }
            $out[] = ['nama' => (string) $pt, 'jumlah' => array_sum(array_column($siteOut, 'jumlah')), 'site' => $siteOut];
        }

        return $out;
    }

    /**
     * Grup Org chart dari daftar orang (satu perusahaan).
     *
     * @param  list<array<string, mixed>>  $orang
     * @return list<array{perusahaan:string,site:string,departemen:string,jabatan:string,jumlah:int,orang:list<array<string,mixed>>}>
     */
    public static function grupDariOrang(array $orang): array
    {
        $grup = [];
        foreach ($orang as $o) {
            $jab = $o['fungsional'] !== '' ? $o['fungsional'] : $o['jabatan'];
            $k = $o['perusahaan']."\0".$o['site']."\0".$o['departemen']."\0".$jab;
            $grup[$k] ??= ['perusahaan' => $o['perusahaan'], 'site' => $o['site'], 'departemen' => $o['departemen'], 'jabatan' => $jab, 'jumlah' => 0, 'orang' => []];
            $grup[$k]['jumlah']++;
            $grup[$k]['orang'][] = ['sid' => $o['sid'], 'nama' => $o['nama'], 'jabatan' => $o['jabatan']];
        }

        return array_values($grup);
    }

    /**
     * Hari tidak masuk (CT/S/I) satu bulan untuk karyawan terpilih.
     *
     * @param  list<array{0:string,1:string,2:string}>  $tidakMasuk  [sid, tanggal, kode]
     * @param  list<array<string, mixed>>  $karyawan
     * @return array{orang:list<array<string,mixed>>,per_hari:array<string,array<string,int>>,per_departemen:list<array<string,mixed>>,total:array<string,int>}
     */
    public static function timeOff(array $tidakMasuk, array $karyawan, CarbonImmutable $bulan): array
    {
        $dari = $bulan->startOfMonth()->toDateString();
        $sampai = $bulan->endOfMonth()->toDateString();
        $peta = [];
        foreach ($karyawan as $k) {
            $peta[mb_strtoupper($k['sid'])] = $k;
        }

        $orang = [];
        $perHari = [];
        $total = array_fill_keys(array_keys(self::LABEL_KODE), 0);
        foreach ($tidakMasuk as [$sid, $tanggal, $kode]) {
            if ($tanggal < $dari || $tanggal > $sampai || ! isset($peta[$sid])) {
                continue;
            }
            $k = $peta[$sid];
            $orang[$sid] ??= ['sid' => $k['sid'], 'nama' => $k['nama'], 'perusahaan' => $k['perusahaan'], 'site' => $k['site'],
                'departemen' => $k['departemen'], 'fungsional' => $k['fungsional'], 'hari' => [], 'jumlah' => array_fill_keys(array_keys(self::LABEL_KODE), 0)];
            $orang[$sid]['hari'][$tanggal] = $kode;
            $orang[$sid]['jumlah'][$kode] = ($orang[$sid]['jumlah'][$kode] ?? 0) + 1;
            $perHari[$tanggal][$kode] = ($perHari[$tanggal][$kode] ?? 0) + 1;
            $total[$kode] = ($total[$kode] ?? 0) + 1;
        }

        $orang = array_values($orang);
        foreach ($orang as &$o) {
            ksort($o['hari']);
        }
        unset($o);
        usort($orang, static fn (array $a, array $b): int => [array_sum($b['jumlah']), $a['nama']] <=> [array_sum($a['jumlah']), $b['nama']]);

        $dept = [];
        foreach ($karyawan as $k) {
            $d = $k['departemen'] === '' ? 'Tanpa departemen' : $k['departemen'];
            $dept[$d] ??= ['nama' => $d, 'karyawan' => 0, 'tidak_masuk' => 0, 'hari' => 0];
            $dept[$d]['karyawan']++;
        }
        foreach ($orang as $o) {
            $d = $o['departemen'] === '' ? 'Tanpa departemen' : $o['departemen'];
            $dept[$d]['tidak_masuk']++;
            $dept[$d]['hari'] += array_sum($o['jumlah']);
        }
        $dept = array_values(array_filter($dept, static fn (array $d): bool => $d['tidak_masuk'] > 0));
        usort($dept, static fn (array $a, array $b): int => [$b['hari'], $a['nama']] <=> [$a['hari'], $b['nama']]);
        ksort($perHari);

        return ['orang' => $orang, 'per_hari' => $perHari, 'per_departemen' => $dept, 'total' => $total];
    }

    /** "17-06-2026" (dd-mm-yyyy) atau "2026-06-17" → "2026-06-17"; selain itu null. */
    public static function tanggalMulai(string $nilai): ?string
    {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})/', $nilai, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? "{$m[3]}-{$m[2]}-{$m[1]}" : null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $nilai, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null;
        }

        return null;
    }

    /**
     * WHERE ClickHouse untuk filter: perusahaan/site terpilih (atau semua yang
     * diizinkan di $opsi), kata kunci. Nilai di-escape lewat quote().
     *
     * @param  array<string, array<string, int>>  $opsi
     */
    public static function saringan(array $opsi, string $pt, string $site, string $cari, string $dept = ''): string
    {
        $q = [RfidClickHouseClient::class, 'quote'];
        $w = [self::dasar()];
        if ($pt !== '') {
            $w[] = self::KOLOM_PT.' = '.$q($pt);
        } else {
            $w[] = self::KOLOM_PT.' IN ('.implode(',', array_map($q, array_keys($opsi) ?: [''])).')';
        }
        if ($site !== '') {
            $w[] = self::KOLOM_SITE.' = '.$q($site === '-' ? '' : $site);
        }
        if ($dept !== '') {
            $w[] = self::KOLOM_DEPT.' = '.$q($dept === '-' ? '' : $dept);
        }
        $cari = trim($cari);
        if ($cari !== '') {
            $pola = $q('%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $cari).'%');
            $w[] = "(kode_sid ILIKE {$pola} OR nama ILIKE {$pola} OR departement ILIKE {$pola} OR jabatan_fungsional ILIKE {$pola} OR jabatan_struktural ILIKE {$pola})";
        }

        return implode(' AND ', $w);
    }

    private const KOLOM_PT = "trimBoth(ifNull(nama_perusahaan, ''))";

    private const KOLOM_SITE = "trimBoth(ifNull(site_dedicated, ''))";

    private const KOLOM_DEPT = "trimBoth(ifNull(departement, ''))";

    private const KOLOM_JABATAN = "if(trimBoth(ifNull(jabatan_fungsional, '')) != '', trimBoth(ifNull(jabatan_fungsional, '')), trimBoth(ifNull(jabatan_struktural, '')))";

    private const KOLOM_ORANG = "upperUTF8(trimBoth(ifNull(kode_sid, ''))) AS sid, trimBoth(ifNull(nama, '')) AS nama,
        trimBoth(ifNull(nama_perusahaan, '')) AS pt, trimBoth(ifNull(site_dedicated, '')) AS site,
        trimBoth(ifNull(departement, '')) AS departemen, trimBoth(ifNull(divisi, '')) AS divisi,
        trimBoth(ifNull(jabatan_struktural, '')) AS jabatan, trimBoth(ifNull(jabatan_fungsional, '')) AS fungsional,
        trimBoth(ifNull(status_permit, '')) AS permit, trimBoth(ifNull(tanggal_hari_pertama, '')) AS mulai";

    /** Selalu: SID terisi, tanpa jabatan yang dikecualikan (VISITOR). */
    private static function dasar(): string
    {
        $kecuali = array_map([RfidClickHouseClient::class, 'quote'], DmsRosterKaryawanAktif::jabatanDikecualikan() ?: ['']);

        return "trimBoth(ifNull(kode_sid, '')) != '' AND upperUTF8(trimBoth(ifNull(jabatan_struktural, ''))) NOT IN (".implode(',', $kecuali).')';
    }

    /** @param  array<string, mixed>  $r */
    private static function rapikan(array $r): array
    {
        return [
            'sid' => (string) $r['sid'], 'nama' => (string) $r['nama'], 'perusahaan' => (string) $r['pt'], 'site' => (string) $r['site'],
            'departemen' => (string) $r['departemen'], 'divisi' => (string) $r['divisi'], 'jabatan' => (string) $r['jabatan'],
            'fungsional' => (string) $r['fungsional'], 'permit' => (string) $r['permit'], 'mulai' => self::tanggalMulai((string) $r['mulai']),
        ];
    }

    /** Baris CT/S/I berlaku (roster disetujui, tidak ditimpa roster disetujui yang lebih akhir). */
    private static function queryTidakMasuk(CarbonImmutable $bulan): \Illuminate\Database\Query\Builder
    {
        $urut = 'COALESCE(u.direview_pada, u.created_at)';
        $urut2 = 'COALESCE(u2.direview_pada, u2.created_at)';

        return DB::table('dms_roster_master_jadwal as j')
            ->join('dms_roster_master_upload as u', 'u.id', '=', 'j.upload_id')
            ->where('u.status', DmsRosterMasterUpload::STATUS_APPROVED)
            ->whereIn('j.kode', DmsRosterMasterCache::KODE_TIDAK_MASUK)
            ->whereBetween('j.tanggal', [$bulan->startOfMonth()->toDateString(), $bulan->endOfMonth()->toDateString()])
            ->whereNotExists(static function ($q) use ($urut, $urut2): void {
                $q->selectRaw('1')
                    ->from('dms_roster_master_jadwal as j2')
                    ->join('dms_roster_master_upload as u2', 'u2.id', '=', 'j2.upload_id')
                    ->whereColumn('j2.kode_sid', 'j.kode_sid')
                    ->whereColumn('j2.tanggal', 'j.tanggal')
                    ->whereColumn('j2.upload_id', '<>', 'j.upload_id')
                    ->where('u2.status', DmsRosterMasterUpload::STATUS_APPROVED)
                    ->whereRaw("({$urut2} > {$urut} OR ({$urut2} = {$urut} AND u2.id > u.id))");
            });
    }
}
