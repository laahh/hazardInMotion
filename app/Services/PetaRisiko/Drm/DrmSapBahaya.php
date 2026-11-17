<?php

namespace App\Services\PetaRisiko\Drm;

use App\Services\SportEvaluation\RfidClickHouseClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Bukti SAP satu baris "kelompok aktivitas × bahaya-risiko" pada satu minggu kalender, untuk modal kartu KPI.
 * Aturan per jenis laporan sama dengan sync (DrmSql, 17 Nov 2026):
 *   label          : Hazard / Inspeksi berlabel (M09) yang memetakan ke bahaya ini            → bahaya
 *   label_kelompok : Hazard / Inspeksi berlabel SPESIFIK di detail lokasi itu (bahaya kandidatnya di grup
 *                    lokasi hanya dari ≤ N kelompok) yang kelompoknya = kelompok baris ini   → kelompok
 *   kelompok       : Observasi / OAK yang teks aktivitasnya (regex M02, pola pertama yang cocok) masuk
 *                    kelompok baris ini                                                         → kelompok
 * Semua laporan dari obds.ch_rfid_sap_cuti_all_site. Butir checklist OAK tidak dipakai (tabel itu tidak
 * memuatnya; oak_register / oak_detail_object berhenti tersinkron 28 Okt 2026). OBS tidak menandai bahaya spesifik. Hanya detail lokasi cakupan (site / grup) yang grup lokasinya memuat
 * bahaya ini (gerbang M05). Selesai = CLOSED / DONE (REJECT terbuka); BUKAN TEMUAN dan DELETED dibuang.
 */
class DrmSapBahaya
{
    private const STATUS_SELESAI = ['CLOSED', 'DONE'];

    private const MAKS_BARIS = 300;

    private const CACHE_DETIK = 600;

    public function __construct(private readonly RfidClickHouseClient $ch, private readonly DrmRepository $repo)
    {
    }

    /**
     * @return array{dari: string, sampai: string, ndl: int, laporan: list<array<string, mixed>>, ringkas: array<string, int>, terpotong: bool}
     */
    public function ambil(int $hid, string $kel, string $site, string $grup, int $w): array
    {
        $kunci = sprintf('peta-risiko:sap-bahaya:v2:%d:%s:%s:%s:%d:%d', $hid, $kel, md5($site), md5($grup), $w, (int) @filemtime($this->repo->pathMeta()));

        return Cache::remember($kunci, self::CACHE_DETIK, fn () => $this->hitung($hid, $kel, $site, $grup, $w));
    }

    /** @return array{dari: string, sampai: string, ndl: int, laporan: list<array<string, mixed>>, ringkas: array<string, int>, terpotong: bool} */
    private function hitung(int $hid, string $kel, string $site, string $grup, int $w): array
    {
        ['dl' => $kamusDl, 'minggu' => $minggu, 'sampai' => $sampaiData] = $this->kamus();
        $master = DrmMaster::muat();
        $m = $master->data;
        $cfg = $master->cfgEfektif((array) config('peta_risiko_drm'));

        $dari = CarbonImmutable::parse($minggu[$w] ?? $minggu[0]);
        $sampai = min($dari->addDays(6), CarbonImmutable::parse($sampaiData));
        $kosong = ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString(), 'ndl' => 0, 'laporan' => [],
            'ringkas' => ['total' => 0, 'label' => 0, 'label_kelompok' => 0, 'kelompok' => 0,
                'belum_closed' => 0, 'berulang' => 0, 'oak_stop' => 0], 'terpotong' => false];

        // Detail lokasi cakupan yang grup lokasinya memuat baris ini (gerbang M05).
        $namaGrup = array_map('strval', $m['grup']);
        $grupMemuat = [];
        foreach ($m['grupBaris'] as $gid => $riList) {
            if (in_array($hid, $riList, true)) {
                $grupMemuat[$namaGrup[$gid] ?? ''] = (int) $gid;
            }
        }
        $dl = [];
        foreach ($kamusDl as $id => [$s, $lokasi, $detlok, $g]) {
            if (($site === 'ALL' || $s === $site) && isset($grupMemuat[$g]) && ($grup === '' || $g === $grup)) {
                $dl[(int) $id] = [$s, $lokasi, $detlok, $g];
            }
        }
        if ($dl === []) {
            return $kosong;
        }

        [$labelBahaya, $labelSpesifik] = $this->labelHazard($master, $cfg, $hid, $kel, array_intersect_key($grupMemuat, array_flip(array_column($dl, 3))));
        $pola = array_filter(array_map(fn (array $p) => (string) ($p[2] ?? ''), (array) $cfg['proses_bisnis']));
        $semuaLabel = array_values(array_unique([...$labelBahaya, ...array_merge([], ...array_values($labelSpesifik))]));

        $sap = $this->ch->select($this->sql(array_keys($dl), $dari, $sampai, $semuaLabel, $pola, $kel, (array) $cfg['status_buang'], (array) $cfg['obs_tanpa_aktivitas']), 60);

        // Hazard: "label" bila label memetakan ke bahaya ini; selain itu "label_kelompok" bila label spesifik di grup DL itu.
        $isiLabel = array_flip($labelBahaya);
        $baris = [];
        foreach ($sap as $r) {
            if ($r['cara'] === 'hazard') {
                $g = $dl[(int) $r['dl']][3] ?? '';
                $r['cara'] = isset($isiLabel[(int) $r['kc']]) ? 'label' : (in_array((int) $r['kc'], $labelSpesifik[$g] ?? [], true) ? 'label_kelompok' : '');
            }
            if ($r['cara'] !== '') {
                $baris[] = $r;
            }
        }
        $terpotong = count($baris) > self::MAKS_BARIS;
        $baris = array_slice($baris, 0, self::MAKS_BARIS);

        // Berulang = ≥ ambang laporan dengan label sama di satu detail lokasi dalam minggu itu (hanya label bahaya ini).
        $ambang = (int) $cfg['ambang_berulang'];
        $jumlahLabel = [];
        foreach ($baris as $r) {
            if ($r['cara'] === 'label') {
                $jumlahLabel[$r['dl'].'|'.$r['isi']] = ($jumlahLabel[$r['dl'].'|'.$r['isi']] ?? 0) + 1;
            }
        }

        $laporan = [];
        $ringkas = $kosong['ringkas'];
        foreach ($baris as $r) {
            $hazard = in_array($r['jenis'], ['HAZARD', 'INSPEKSI'], true);
            $belum = $hazard && ! in_array(strtoupper((string) $r['status']), self::STATUS_SELESAI, true);
            $ulang = $r['cara'] === 'label' && ($jumlahLabel[$r['dl'].'|'.$r['isi']] ?? 0) >= $ambang;
            $stop = (int) ($r['stop'] ?? 0) === 1;
            [$s, $lokasi, $detlok] = $dl[(int) $r['dl']] ?? ['', '', ''];

            $ringkas['total']++;
            $ringkas[$r['cara']]++;
            $ringkas['belum_closed'] += (int) $belum;
            $ringkas['berulang'] += (int) $ulang;
            $ringkas['oak_stop'] += (int) $stop;
            $laporan[] = [
                'waktu' => substr((string) $r['waktu'], 0, 16),
                'jenis' => (string) $r['jenis'],
                'cara' => (string) $r['cara'],
                'lokasi' => trim("{$s} · {$lokasi} · {$detlok}", ' ·'),
                'isi' => (string) $r['isi'],
                'deskripsi' => (string) $r['deskripsi'],
                'status' => (string) $r['status'],
                'hasil_oak' => (string) ($r['hasil_oak'] ?? ''),
                'pelapor' => (string) ($r['pelapor'] ?? ''),
                'belum_closed' => $belum,
                'berulang' => $ulang,
                'oak_stop' => $stop,
            ];
        }

        return [...$kosong, 'ndl' => count($dl), 'laporan' => $laporan, 'ringkas' => $ringkas, 'terpotong' => $terpotong];
    }

    /**
     * Label Hazard / Inspeksi (CRC "k / sk" ternormalisasi): [label yang memetakan ke bahaya ini,
     * grup → label SPESIFIK di grup itu yang kelompok kandidatnya memuat kelompok baris ini].
     *
     * @param  array<string, int>  $grup  nama grup → id grup (grup DL cakupan yang memuat bahaya ini)
     * @return array{0: list<int>, 1: array<string, list<int>>}
     */
    private function labelHazard(DrmMaster $master, array $cfg, int $hid, string $kel, array $grup): array
    {
        $m = $master->data;
        $kelRi = $master->kelompokBaris((array) $cfg['proses_bisnis']);
        $maks = (int) $cfg['hazard_spesifik_maks_aktivitas'];
        $riGrup = array_map(fn (int $gid) => array_flip($m['grupBaris'][$gid] ?? []), $grup);

        $bahaya = [];
        $spesifik = array_fill_keys(array_keys($grup), []);
        foreach ($m['label'] as $l) {
            $crc = DrmMaster::crc(DrmMaster::norm($l['k']).' / '.DrmMaster::norm($l['sk']));
            if (in_array($hid, $l['ri'], true)) {
                $bahaya[] = $crc;
            }
            foreach ($riGrup as $g => $ri) {
                // Kandidat = baris label ∩ baris grup (sama dengan evh ⋈ basek lalu spes di DrmSql).
                $kels = [];
                foreach ($l['ri'] as $r) {
                    if (isset($ri[$r])) {
                        $kels[$kelRi[$r] ?? 'K0'] = true;
                    }
                }
                if ($kels !== [] && count($kels) <= $maks && isset($kels[$kel])) {
                    $spesifik[$g][] = $crc;
                }
            }
        }

        return [array_values(array_unique($bahaya)), $spesifik];
    }

    /** @param array<string, string> $pola kode kelompok → regex M02 (urutan config) */
    private function pb(array $pola): string
    {
        $q = [RfidClickHouseClient::class, 'quote'];

        return '['.implode(', ', array_map(fn ($k, $p) => '('.$q((string) $k).', '.$q('(?i)'.$p).')', array_keys($pola), $pola)).']';
    }

    /**
     * Laporan SAP (Hazard / Inspeksi / Observasi / OAK). cara: 'hazard' (kandidat label, dipilah di PHP) | 'kelompok'.
     *
     * @param  list<int>  $dl
     * @param  list<int>  $label  CRC label "k / sk" ternormalisasi (bahaya ini + label spesifik sekelompok)
     * @param  array<string, string>  $pola  kode kelompok → regex M02 (urutan config)
     */
    private function sql(array $dl, CarbonImmutable $dari, CarbonImmutable $sampai, array $label, array $pola, string $kel, array $buang, array $tanpaAktivitas): string
    {
        $q = [RfidClickHouseClient::class, 'quote'];
        $norm = fn (string $x) => "trimBoth(replaceRegexpAll(lowerUTF8(ifNull({$x}, '')), '\\\\s+', ' '))";
        $daftar = fn (array $a) => $a === [] ? '0' : implode(',', array_map('intval', $a));
        // Kelompok teks = regex PERTAMA yang cocok (sama dengan okx / arrayFirst di DrmSql), bukan sekadar cocok.
        $kelompok = ! isset($pola[$kel]) ? '0' : 'NOT na AND arrayFirst(r -> match(tx, r.2), '.$this->pb($pola).').1 = '.$q($kel);

        return '
SELECT * FROM (
  SELECT toString(`date time`) AS waktu, jenis_data AS jenis, toString(`id detail lokasi`) AS dl,
         lower(if(jenis_data = \'OBSERVASI\', ifNull(`aktivitas  OBS`, \'\'),
                  if(jenis_data = \'OBSERVASI AREA KRITIS\',
                     concat(ifNull(`aktivitas pekerjaan OAK`, \'\'), \' / \', ifNull(`sub aktivitas pekerjaan OAK`, \'\')), \'\'))) AS tx,
         multiSearchAny(lower(concat(ifNull(deskripsi, \'\'), \' \', ifNull(`umpan_balik  OBS`, \'\'), \' \', ifNull(tindakan, \'\'))),
                        ['.implode(', ', array_map($q, $tanpaAktivitas ?: ['-'])).']) AS na,
         if(jenis_data IN (\'HAZARD\', \'INSPEKSI\'), concat(trim(ifNull(ketidaksesuaian, \'\')), \' / \', trim(ifNull(subketidaksesuaian, \'\'))), tx) AS isi,
         CRC32(concat('.$norm('ketidaksesuaian').', \' / \', '.$norm('subketidaksesuaian').')) AS kc,
         multiIf(jenis_data IN (\'HAZARD\', \'INSPEKSI\') AND kc IN ('.$daftar($label).'), \'hazard\',
                 jenis_data NOT IN (\'HAZARD\', \'INSPEKSI\') AND trim(tx) NOT IN (\'\', \'/\') AND '.$kelompok.', \'kelompok\', \'\') AS cara,
         toUInt8(jenis_data = \'OBSERVASI AREA KRITIS\' AND positionCaseInsensitive(ifNull(`hasil OAK`, \'\'), \'stop\') > 0) AS stop,
         ifNull(`hasil OAK`, \'\') AS hasil_oak, ifNull(status, \'\') AS status,
         substring(ifNull(deskripsi, \'\'), 1, 400) AS deskripsi, ifNull(pelapor, \'\') AS pelapor
  FROM obds.ch_rfid_sap_cuti_all_site
  WHERE `id detail lokasi` IN ('.$daftar($dl).')
    AND `date time` >= toDate('.$q($dari->toDateString()).')
    AND `date time` < toDate('.$q($sampai->toDateString()).') + 1
    AND jenis_data IN (\'HAZARD\', \'INSPEKSI\', \'OBSERVASI\', \'OBSERVASI AREA KRITIS\')
    AND ifNull(status, \'\') NOT IN ('.implode(', ', array_map($q, $buang ?: ['-'])).')
)
WHERE cara != \'\'
ORDER BY waktu DESC
LIMIT '.(self::MAKS_BARIS + 1);
    }

    /**
     * Kamus detail lokasi + minggu dari meta snapshot, di-cache per versi snapshot (meta.json besar).
     *
     * @return array{dl: array<string, array{0: string, 1: string, 2: string, 3: string}>, minggu: list<string>, sampai: string}
     */
    private function kamus(): array
    {
        return Cache::remember('peta-risiko:kamus-dl:'.(int) @filemtime($this->repo->pathMeta()), 3600, function () {
            $meta = $this->repo->meta() ?? [];

            return [
                'dl' => (array) ($meta['beranda']['dl'] ?? []),
                'minggu' => (array) ($meta['beranda']['minggu'] ?? []),
                'sampai' => (string) ($meta['sampai'] ?? now()->toDateString()),
            ];
        });
    }
}
