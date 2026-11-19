<?php

namespace App\Services\PetaRisiko\Drm;

use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use App\Services\SportEvaluation\RfidClickHouseClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Asal HIRA tiap baris baseline (ri): dokumen HIRA (id_hira_master master `drm_master_baris.idh`) beserta revisi,
 * departemen, aktivitas spesifik, dll. dari obds.mv_hira_master. Satu baris master bisa berasal dari beberapa
 * dokumen HIRA; dokumen yang memuat teks bahaya baris itu (cause bahaya_penyebab_potensial) ditandai `cocok`
 * dan didahulukan, sama seperti cara sync mengambil atribut risiko.
 */
class DrmHiraSumber
{
    public const MAKS_BARIS = 300;

    private const CACHE_DETIK = 3600;

    public function __construct(private readonly RfidClickHouseClient $ch)
    {
    }

    /**
     * @param  list<int>  $ri
     * @return array<int, list<array<string, mixed>>>  ri → dokumen HIRA
     */
    public function untuk(array $ri): array
    {
        $ri = array_slice(array_values(array_unique(array_filter(array_map('intval', $ri), fn ($x) => $x > 0))), 0, self::MAKS_BARIS);
        if ($ri === [] || ! DrmMasterDb::terisi()) {
            return [];
        }
        $baris = DB::table('drm_master_baris')->whereIn('ri', $ri)->get(['ri', 'idh', 'bahaya'])
            ->map(fn ($b) => ['ri' => (int) $b->ri, 'bahaya' => (string) $b->bahaya, 'idh' => self::pisahIdh((string) $b->idh)])->all();
        $idh = array_values(array_unique(array_merge(...array_column($baris, 'idh') ?: [[]])));
        sort($idh);

        return self::cocokkan($baris, $this->dokumen($idh));
    }

    /**
     * @param  list<array{ri: int, bahaya: string, idh: list<int>}>  $baris
     * @param  array<int, array<string, mixed>>  $dokumen  id_hira_master → header + 'bahaya' (teks cause)
     * @return array<int, list<array<string, mixed>>>
     */
    public static function cocokkan(array $baris, array $dokumen): array
    {
        $hasil = [];
        foreach ($baris as $b) {
            $kunci = DrmMaster::normTeks($b['bahaya']);
            $daftar = [];
            foreach ($b['idh'] as $id) {
                if (! isset($dokumen[$id])) {
                    $daftar[] = ['idh' => $id, 'ada' => false, 'cocok' => false];

                    continue;
                }
                $d = $dokumen[$id];
                $cocok = in_array($kunci, array_map([DrmMaster::class, 'normTeks'], (array) $d['bahaya']), true);
                unset($d['bahaya']);
                $daftar[] = [...$d, 'idh' => $id, 'ada' => true, 'cocok' => $cocok];
            }
            usort($daftar, fn ($x, $y) => [$y['cocok'], $y['ada'], (int) ($y['aktif'] ?? 0)] <=> [$x['cocok'], $x['ada'], (int) ($x['aktif'] ?? 0)] ?: $x['idh'] <=> $y['idh']);
            $hasil[$b['ri']] = $daftar;
        }

        return $hasil;
    }

    /** @return list<int> */
    public static function pisahIdh(string $idh): array
    {
        return array_values(array_unique(array_filter(array_map('intval', preg_split('/[^0-9]+/', $idh) ?: []), fn ($x) => $x > 0)));
    }

    /**
     * @param  list<int>  $idh
     * @return array<int, array<string, mixed>>
     */
    private function dokumen(array $idh): array
    {
        if ($idh === []) {
            return [];
        }

        return Cache::remember('peta-risiko:hira-sumber:v2:'.md5(implode(',', $idh)), self::CACHE_DETIK, function () use ($idh) {
            $rows = $this->ch->select('
SELECT id_hira_master AS idh, any(ifNull(revisi, 0)) AS rev, any(ifNull(departemen, \'\')) AS dept,
       any(ifNull(nama_aktivitas_spesifik, \'\')) AS spesifik, any(ifNull(nama_aktivitas, \'\')) AS aktivitas,
       any(ifNull(nama_sub_aktivitas, \'\')) AS sub, any(ifNull(bagian, \'\')) AS bagian, any(ifNull(perusahaan, \'\')) AS perusahaan,
       any(ifNull(site, \'[]\')) AS site, any(ifNull(status_aktif, \'\')) AS aktif, any(ifNull(klasifikasi_rutin, \'\')) AS rutin,
       any(ifNull(toString(toDate(tanggal_update)), \'\')) AS diperbarui,
       any(arrayMap(c -> JSONExtractString(c, \'bahaya_penyebab_potensial\'), JSONExtractArrayRaw(ifNull(causes, \'[]\')))) AS bahaya
FROM obds.mv_hira_master
WHERE id_hira_master IN ('.implode(', ', $idh).')
GROUP BY id_hira_master', 60);
            $peta = [];
            foreach ($rows as $r) {
                $situs = json_decode((string) $r['site'], true);
                $peta[(int) $r['idh']] = [
                    'rev' => (int) $r['rev'], 'dept' => trim((string) $r['dept']), 'spesifik' => trim((string) $r['spesifik']),
                    'aktivitas' => trim((string) $r['aktivitas']), 'sub' => trim((string) $r['sub']), 'bagian' => trim((string) $r['bagian']),
                    'perusahaan' => trim((string) $r['perusahaan']), 'site' => is_array($situs) ? array_values(array_map('strval', $situs)) : [],
                    'aktif' => (string) $r['aktif'] === '1', 'rutin' => (string) $r['rutin'], 'diperbarui' => (string) $r['diperbarui'],
                    'bahaya' => array_values(array_map('strval', (array) $r['bahaya'])),
                    'kosong' => false,
                ];
            }
            $sisa = array_values(array_diff($idh, array_keys($peta)));

            return $peta + $this->dokumenKosong($sisa);
        });
    }

    /**
     * Dokumen yang tidak ada di mv_hira_master: view itu hanya memuat HIRA yang punya isi (bahaya / site). Header-nya
     * tetap diambil dari tabel mentah hira_master agar terlihat dokumennya ada tetapi kosong. Rantai aktivitas
     * hira_activity: spesifik → parent = sub aktivitas → parent = aktivitas (sama dengan kolom mv_hira_master).
     *
     * @param  list<int>  $idh
     * @return array<int, array<string, mixed>>
     */
    private function dokumenKosong(array $idh): array
    {
        if ($idh === []) {
            return [];
        }
        $rows = $this->ch->select('
SELECT h.id AS idh, any(ifNull(h.rev, 0)) AS rev, any(ifNull(d.name, \'\')) AS dept, any(ifNull(a.name, \'\')) AS spesifik,
       any(ifNull(a2.name, \'\')) AS sub, any(ifNull(a3.name, \'\')) AS aktivitas, any(ifNull(h.is_active, \'\')) AS aktif,
       any(ifNull(toString(toDate(h.updated_date)), \'\')) AS diperbarui
FROM obds.hira_master h
LEFT JOIN obds.hira_department d ON d.id = h.hira_dept_id
LEFT JOIN obds.hira_activity a ON a.id = h.hira_activity_id
LEFT JOIN obds.hira_activity a2 ON a2.id = a.parent_id
LEFT JOIN obds.hira_activity a3 ON a3.id = a2.parent_id
WHERE h.id IN ('.implode(', ', $idh).')
GROUP BY h.id', 60);
        $peta = [];
        foreach ($rows as $r) {
            $peta[(int) $r['idh']] = [
                'rev' => (int) $r['rev'], 'dept' => trim((string) $r['dept']), 'spesifik' => trim((string) $r['spesifik']),
                'aktivitas' => trim((string) $r['aktivitas']), 'sub' => trim((string) $r['sub']), 'bagian' => '', 'perusahaan' => '',
                'site' => [], 'aktif' => (string) $r['aktif'] === '1', 'rutin' => '', 'diperbarui' => (string) $r['diperbarui'],
                'bahaya' => [], 'kosong' => true,
            ];
        }

        return $peta;
    }
}
