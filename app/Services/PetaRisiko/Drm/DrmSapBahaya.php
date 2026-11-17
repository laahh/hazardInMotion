<?php

namespace App\Services\PetaRisiko\Drm;

use App\Services\SportEvaluation\RfidClickHouseClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Laporan SAP yang menjadi bukti satu baris "kelompok aktivitas × bahaya-risiko" pada satu minggu kalender,
 * untuk modal kartu KPI. Aturan pemetaannya sama dengan sync (DrmSql):
 *   spesifik label : Hazard / Inspeksi dengan label ketidaksesuaian / sub (M09) yang memetakan ke baris ini
 *   spesifik teks  : Observasi / OAK dengan teks aktivitas (M11 / M12) yang memetakan ke baris ini
 *   kelompok       : Observasi / OAK dengan teks aktivitas yang (regex M02, kelompok pertama yang cocok)
 *                    masuk kelompok aktivitas baris ini — bukti tingkat kelompok
 * Hanya detail lokasi yang grup lokasinya memuat baris ini (gerbang M05) dan berada di cakupan (site / grup).
 * Status selesai = CLOSED / DONE (REJECT dihitung belum closed); BUKAN TEMUAN dan DELETED dibuang.
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
        $kunci = sprintf('peta-risiko:sap-bahaya:%d:%s:%s:%s:%d:%d', $hid, $kel, md5($site), md5($grup), $w, (int) @filemtime($this->repo->pathMeta()));

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
            'ringkas' => ['total' => 0, 'label' => 0, 'teks' => 0, 'kelompok' => 0, 'belum_closed' => 0, 'berulang' => 0, 'oak_stop' => 0], 'terpotong' => false];

        // Detail lokasi cakupan yang grup lokasinya memuat baris ini (gerbang M05).
        $grupMemuat = [];
        foreach ($m['grupBaris'] as $gid => $riList) {
            if (in_array($hid, $riList, true)) {
                $grupMemuat[(string) ($m['grup'][$gid] ?? '')] = true;
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

        $label = $teks = [];
        foreach ($m['label'] as $l) {
            if (in_array($hid, $l['ri'], true)) {
                $label[] = DrmMaster::crc(DrmMaster::norm($l['k']).' / '.DrmMaster::norm($l['sk']));
            }
        }
        foreach ([...$m['obs'], ...$m['oak']] as $t) {
            if (in_array($hid, $t['ri'], true)) {
                $teks[] = DrmMaster::crc($t['teks']);
            }
        }
        $pola = array_filter(array_map(fn (array $p) => (string) ($p[2] ?? ''), (array) $cfg['proses_bisnis']));

        $rows = $this->ch->select($this->sql(array_keys($dl), $dari, $sampai, $label, $teks, $pola, $kel, (array) $cfg['status_buang'], (array) $cfg['obs_tanpa_aktivitas']), 60);
        $terpotong = count($rows) > self::MAKS_BARIS;
        $rows = array_slice($rows, 0, self::MAKS_BARIS);

        // Berulang = ≥ ambang laporan dengan label sama di satu detail lokasi dalam minggu itu.
        $ambang = (int) $cfg['ambang_berulang'];
        $jumlahLabel = [];
        foreach ($rows as $r) {
            if ($r['cara'] === 'label') {
                $jumlahLabel[$r['dl'].'|'.$r['isi']] = ($jumlahLabel[$r['dl'].'|'.$r['isi']] ?? 0) + 1;
            }
        }

        $laporan = [];
        $ringkas = $kosong['ringkas'];
        foreach ($rows as $r) {
            $hazard = in_array($r['jenis'], ['HAZARD', 'INSPEKSI'], true);
            $belum = $hazard && ! in_array(strtoupper((string) $r['status']), self::STATUS_SELESAI, true);
            $ulang = $r['cara'] === 'label' && ($jumlahLabel[$r['dl'].'|'.$r['isi']] ?? 0) >= $ambang;
            $stop = (int) $r['stop'] === 1;
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
                'hasil_oak' => (string) $r['hasil_oak'],
                'pelapor' => (string) $r['pelapor'],
                'belum_closed' => $belum,
                'berulang' => $ulang,
                'oak_stop' => $stop,
            ];
        }

        return [...$kosong, 'ndl' => count($dl), 'laporan' => $laporan, 'ringkas' => $ringkas, 'terpotong' => $terpotong];
    }

    /**
     * @param  list<int>  $dl
     * @param  list<int>  $label  CRC label "k / sk" ternormalisasi
     * @param  list<int>  $teks  CRC teks aktivitas OBS / "aktivitas / sub" OAK ternormalisasi
     * @param  array<string, string>  $pola  kode kelompok → regex M02 (urutan config)
     */
    private function sql(array $dl, CarbonImmutable $dari, CarbonImmutable $sampai, array $label, array $teks, array $pola, string $kel, array $buang, array $tanpaAktivitas): string
    {
        $q = [RfidClickHouseClient::class, 'quote'];
        $norm = fn (string $x) => "trimBoth(replaceRegexpAll(lowerUTF8(ifNull({$x}, '')), '\\\\s+', ' '))";
        $daftar = fn (array $a) => $a === [] ? '0' : implode(',', array_map('intval', $a));
        // Kelompok teks = regex PERTAMA yang cocok (sama dengan okx / arrayFirst di DrmSql), bukan sekadar cocok.
        $pb = '['.implode(', ', array_map(fn ($k, $p) => '('.$q((string) $k).', '.$q('(?i)'.$p).')', array_keys($pola), $pola)).']';
        $kelompok = ! isset($pola[$kel]) ? '0' : 'NOT na AND arrayFirst(r -> match(tx, r.2), '.$pb.').1 = '.$q($kel);

        return '
SELECT * FROM (
  SELECT toString(`date time`) AS waktu, jenis_data AS jenis, toString(`id detail lokasi`) AS dl,
         lower(if(jenis_data = \'OBSERVASI\', ifNull(`aktivitas  OBS`, \'\'),
                  if(jenis_data = \'OBSERVASI AREA KRITIS\',
                     concat(ifNull(`aktivitas pekerjaan OAK`, \'\'), \' / \', ifNull(`sub aktivitas pekerjaan OAK`, \'\')), \'\'))) AS tx,
         multiSearchAny(lower(concat(ifNull(deskripsi, \'\'), \' \', ifNull(`umpan_balik  OBS`, \'\'), \' \', ifNull(tindakan, \'\'))),
                        ['.implode(', ', array_map($q, $tanpaAktivitas ?: ['-'])).']) AS na,
         if(jenis_data IN (\'HAZARD\', \'INSPEKSI\'), concat(trim(ifNull(ketidaksesuaian, \'\')), \' / \', trim(ifNull(subketidaksesuaian, \'\'))), tx) AS isi,
         multiIf(jenis_data IN (\'HAZARD\', \'INSPEKSI\') AND CRC32(concat('.$norm('ketidaksesuaian').', \' / \', '.$norm('subketidaksesuaian').')) IN ('.$daftar($label).'), \'label\',
                 jenis_data NOT IN (\'HAZARD\', \'INSPEKSI\') AND CRC32(trimBoth(replaceRegexpAll(tx, \'\\\\s+\', \' \'))) IN ('.$daftar($teks).'), \'teks\',
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
