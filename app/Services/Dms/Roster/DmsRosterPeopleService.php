<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Services\SportEvaluation\RfidClickHouseClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Data tab People / Org chart / Time off di Overview Roster.
 *
 * Karyawan aktif dibaca dari salinan ClickHouse `obds.bep_vw_safety_karyawan_aktif`
 * (punya departemen & jabatan fungsional), di-cache 30 menit. Bila ClickHouse tidak
 * terjangkau, dipakai salinan Tahap 8 (tanpa departemen). Perusahaan/site yang
 * dikecualikan dari Overview dan jabatan VISITOR tidak ikut — sama dengan Overview.
 * Hari tidak masuk (CT/S/I) dari snapshot DmsRosterMasterCache (dihitung di latar).
 */
final class DmsRosterPeopleService
{
    private const KUNCI = 'dms_roster:people:v1';

    private const TTL = 1800;

    private const TTL_CADANGAN = 120;

    /** Urutan tingkat jabatan fungsional di Org chart (atas → bawah); lainnya di akhir. */
    public const URUTAN_JABATAN = [
        'Presiden Direktur', 'Direktur', 'General Manager', 'Manager', 'Superintendent',
        'Supervisor/Officer', 'Engineer/Specialist', 'Foreman/Group Leader', 'Administrator',
        'Technician', 'Mekanik', 'Operator', 'Security', 'Crew',
    ];

    public const LABEL_KODE = ['CT' => 'Cuti', 'S' => 'Sakit', 'I' => 'Izin'];

    private ?string $sumber = null;

    public function __construct(
        private readonly RfidClickHouseClient $clickhouse,
        private readonly DmsRosterKaryawanAktif $aktif,
    ) {}

    /** 'clickhouse' | 'tahap8' | null (belum dibaca / tidak ada). */
    public function sumber(): ?string
    {
        return $this->sumber;
    }

    /**
     * @return list<array{sid:string,nama:string,perusahaan:string,site:string,departemen:string,divisi:string,jabatan:string,fungsional:string,level:string,permit:string,mulai:?string}>|null
     */
    public function karyawan(): ?array
    {
        $ada = Cache::get(self::KUNCI);
        if (is_array($ada)) {
            $this->sumber = $ada['sumber'];

            return $ada['karyawan'];
        }

        $sumber = 'clickhouse';
        $karyawan = $this->dariClickHouse();
        if ($karyawan === null) {
            $sumber = 'tahap8';
            $karyawan = $this->dariTahap8();
            if ($karyawan === null) {
                return null;
            }
        }
        $karyawan = self::saringOverview($karyawan);
        Cache::put(self::KUNCI, ['sumber' => $sumber, 'karyawan' => $karyawan], $sumber === 'clickhouse' ? self::TTL : self::TTL_CADANGAN);
        $this->sumber = $sumber;

        return $karyawan;
    }

    /**
     * Pilihan filter: perusahaan => daftar site (urut).
     *
     * @param  list<array<string, mixed>>  $karyawan
     * @return array<string, list<string>>
     */
    public static function opsi(array $karyawan): array
    {
        $out = [];
        foreach ($karyawan as $k) {
            $out[$k['perusahaan']][$k['site'] === '' ? '-' : $k['site']] = true;
        }
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return array_map(static function (array $site): array {
            $s = array_keys($site);
            sort($s, SORT_NATURAL | SORT_FLAG_CASE);

            return $s;
        }, $out);
    }

    /**
     * @param  list<array<string, mixed>>  $karyawan
     * @return list<array<string, mixed>>
     */
    public static function saring(array $karyawan, string $pt = '', string $site = '', string $cari = '', string $departemen = ''): array
    {
        $cari = mb_strtolower(trim($cari));

        return array_values(array_filter($karyawan, static function (array $k) use ($pt, $site, $cari, $departemen): bool {
            if ($pt !== '' && $k['perusahaan'] !== $pt) {
                return false;
            }
            if ($site !== '' && ($k['site'] === '' ? '-' : $k['site']) !== $site) {
                return false;
            }
            if ($departemen !== '' && $k['departemen'] !== $departemen) {
                return false;
            }

            return $cari === '' || str_contains(mb_strtolower($k['sid'].' '.$k['nama'].' '.$k['departemen'].' '.$k['fungsional'].' '.$k['jabatan']), $cari);
        }));
    }

    /**
     * Pohon Org chart: perusahaan → site → departemen → tingkat jabatan → orang.
     * Tiap simpul membawa jumlah orang; orang di daun urut nama.
     *
     * @param  list<array<string, mixed>>  $karyawan
     * @return list<array{nama:string,jumlah:int,site:list<array{nama:string,jumlah:int,departemen:list<array{nama:string,jumlah:int,jabatan:list<array{nama:string,jumlah:int,orang:list<array<string,mixed>>}>}>}>}>
     */
    public static function org(array $karyawan): array
    {
        $pohon = [];
        foreach ($karyawan as $k) {
            $site = $k['site'] === '' ? '-' : $k['site'];
            $dept = $k['departemen'] === '' ? 'Tanpa departemen' : $k['departemen'];
            $jab = $k['fungsional'] !== '' ? $k['fungsional'] : ($k['jabatan'] !== '' ? $k['jabatan'] : 'Tanpa jabatan');
            $pohon[$k['perusahaan']][$site][$dept][$jab][] = $k;
        }

        $urutJab = array_flip(self::URUTAN_JABATAN);
        $hitung = static fn (array $a): int => array_sum(array_map('count', $a));
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
                    foreach ($jabs as $jab => $orang) {
                        usort($orang, static fn (array $x, array $y): int => strcasecmp($x['nama'], $y['nama']));
                        $jabOut[] = ['nama' => (string) $jab, 'jumlah' => count($orang), 'orang' => $orang];
                    }
                    $deptOut[] = ['nama' => (string) $dept, 'jumlah' => $hitung($jabs), 'jabatan' => $jabOut];
                }
                $siteOut[] = ['nama' => (string) $site, 'jumlah' => array_sum(array_column($deptOut, 'jumlah')), 'departemen' => $deptOut];
            }
            $out[] = ['nama' => (string) $pt, 'jumlah' => array_sum(array_column($siteOut, 'jumlah')), 'site' => $siteOut];
        }

        return $out;
    }

    /**
     * Hari tidak masuk (CT/S/I) satu bulan untuk karyawan terpilih.
     *
     * @param  list<array{0:string,1:string,2:string,3:string,4:string,5:string}>  $tidakMasuk  DmsRosterMasterCache::tidakMasuk()
     * @param  list<array<string, mixed>>  $karyawan  hasil saring() — penentu siapa yang dihitung
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
        foreach ($tidakMasuk as [, , $sid, $nama, $tanggal, $kode]) {
            if ($tanggal < $dari || $tanggal > $sampai || ! isset($peta[$sid])) {
                continue;
            }
            $k = $peta[$sid];
            $orang[$sid] ??= ['sid' => $k['sid'], 'nama' => $k['nama'] !== '' ? $k['nama'] : $nama, 'perusahaan' => $k['perusahaan'],
                'site' => $k['site'], 'departemen' => $k['departemen'], 'fungsional' => $k['fungsional'], 'hari' => [], 'jumlah' => array_fill_keys(array_keys(self::LABEL_KODE), 0)];
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

        // Cakupan per departemen: berapa orang yang pernah tidak masuk bulan ini dari total orang departemen.
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

    /**
     * @param  list<array<string, mixed>>  $karyawan
     * @return list<array<string, mixed>>
     */
    private static function saringOverview(array $karyawan): array
    {
        return array_values(array_filter($karyawan, static fn (array $k): bool => $k['perusahaan'] !== ''
            && ! DmsRosterOverviewPengecualian::perusahaan($k['perusahaan'])
            && ! DmsRosterOverviewPengecualian::site($k['site'])));
    }

    /** @return list<array<string, mixed>>|null */
    private function dariClickHouse(): ?array
    {
        try {
            $rows = $this->clickhouse->select(
                "SELECT trimBoth(ifNull(kode_sid, '')) AS sid, trimBoth(ifNull(nama, '')) AS nama,
                        trimBoth(ifNull(nama_perusahaan, '')) AS perusahaan, trimBoth(ifNull(site_dedicated, '')) AS site,
                        trimBoth(ifNull(departement, '')) AS departemen, trimBoth(ifNull(divisi, '')) AS divisi,
                        trimBoth(ifNull(jabatan_struktural, '')) AS jabatan, trimBoth(ifNull(jabatan_fungsional, '')) AS fungsional,
                        trimBoth(ifNull(level_jabatan, '')) AS level, trimBoth(ifNull(status_permit, '')) AS permit,
                        trimBoth(ifNull(tanggal_hari_pertama, '')) AS mulai
                 FROM obds.bep_vw_safety_karyawan_aktif FINAL"
            );
        } catch (Throwable $e) {
            Log::info('DmsRosterPeopleService: ClickHouse tidak terjangkau, memakai salinan Tahap 8.', ['galat' => mb_substr($e->getMessage(), 0, 200)]);

            return null;
        }

        $kecuali = array_flip(DmsRosterKaryawanAktif::jabatanDikecualikan());
        $out = [];
        foreach ($rows as $r) {
            $sid = mb_strtoupper((string) $r['sid']);
            if ($sid === '' || isset($out[$sid]) || isset($kecuali[mb_strtoupper((string) $r['jabatan'])])) {
                continue;
            }
            $out[$sid] = [
                'sid' => $sid, 'nama' => (string) $r['nama'], 'perusahaan' => (string) $r['perusahaan'], 'site' => (string) $r['site'],
                'departemen' => (string) $r['departemen'], 'divisi' => (string) $r['divisi'], 'jabatan' => (string) $r['jabatan'],
                'fungsional' => (string) $r['fungsional'], 'level' => (string) $r['level'], 'permit' => (string) $r['permit'],
                'mulai' => self::tanggalMulai((string) $r['mulai']),
            ];
        }

        return $out === [] ? null : array_values($out);
    }

    /** @return list<array<string, mixed>>|null */
    private function dariTahap8(): ?array
    {
        $semua = $this->aktif->semua();

        return $semua === null ? null : array_map(static fn (array $k): array => $k + [
            'departemen' => '', 'divisi' => '', 'fungsional' => '', 'level' => '', 'permit' => '', 'mulai' => null,
        ], $semua);
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
}
