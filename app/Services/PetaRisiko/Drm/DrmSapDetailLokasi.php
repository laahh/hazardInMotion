<?php

namespace App\Services\PetaRisiko\Drm;

use App\Services\SportEvaluation\RfidClickHouseClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Laporan SAP (Hazard, Inspeksi, Observasi, OAK) satu detail lokasi dalam satu minggu, untuk rincian
 * tabel detail lokasi di tab Group Lokasi. Dibaca langsung dari ClickHouse (ch_rfid_sap_cuti_all_site),
 * lalu tiap laporan diberi keterangan pemetaan menurut master DRM 2.1:
 *   kandidat = baris HIRA dari label ketidaksesuaian / subketidaksesuaian (M09) atau teks aktivitas OBS/OAK (M11/M12)
 *   lolos    = kandidat yang juga ada di baris grup lokasi detail lokasi itu (gerbang M05)
 * Status selesai = CLOSED / DONE (REJECT dihitung belum closed); BUKAN TEMUAN dan DELETED dibuang.
 */
class DrmSapDetailLokasi
{
    private const STATUS_SELESAI = ['CLOSED', 'DONE'];

    private const MAKS_BARIS = 500;

    private const CACHE_DETIK = 600;

    public function __construct(private readonly RfidClickHouseClient $ch)
    {
    }

    /**
     * @param  string  $grup  nama grup lokasi detail lokasi (dari snapshot)
     * @return array{dari: string, sampai: string, laporan: list<array<string, mixed>>, ringkas: array<string, int>, terpotong: bool}
     */
    public function ambil(int $dl, string $grup, CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $kunci = sprintf('peta-risiko:sap-dl:%d:%s:%s:%s', $dl, $dari->toDateString(), $sampai->toDateString(), md5($grup));

        return Cache::remember($kunci, self::CACHE_DETIK, fn () => $this->hitung($dl, $grup, $dari, $sampai));
    }

    /** @return array{dari: string, sampai: string, laporan: list<array<string, mixed>>, ringkas: array<string, int>, terpotong: bool} */
    private function hitung(int $dl, string $grup, CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $buang = implode(', ', array_map([RfidClickHouseClient::class, 'quote'], (array) config('peta_risiko_drm.status_buang', [])));
        $rows = $this->ch->select('
SELECT toString(`date time`) AS waktu, jenis_data AS jenis,
       ifNull(ketidaksesuaian, \'\') AS k, ifNull(subketidaksesuaian, \'\') AS sk,
       ifNull(`aktivitas  OBS`, \'\') AS obs, ifNull(`aktivitas pekerjaan OAK`, \'\') AS oak, ifNull(`sub aktivitas pekerjaan OAK`, \'\') AS oak_sub,
       ifNull(`hasil OAK`, \'\') AS hasil_oak, ifNull(status, \'\') AS status,
       substring(ifNull(deskripsi, \'\'), 1, 400) AS deskripsi, ifNull(pelapor, \'\') AS pelapor,
       ifNull(toString(toDate(tanggal_janji)), \'\') AS janji
FROM obds.ch_rfid_sap_cuti_all_site
WHERE `id detail lokasi` = '.$dl.'
  AND `date time` >= toDate('.RfidClickHouseClient::quote($dari->toDateString()).')
  AND `date time` < toDate('.RfidClickHouseClient::quote($sampai->toDateString()).') + 1
  AND jenis_data IN (\'HAZARD\', \'INSPEKSI\', \'OBSERVASI\', \'OBSERVASI AREA KRITIS\')
  AND ifNull(status, \'\') NOT IN ('.$buang.')
ORDER BY `date time` DESC
LIMIT '.(self::MAKS_BARIS + 1), 60);

        $terpotong = count($rows) > self::MAKS_BARIS;
        $rows = array_slice($rows, 0, self::MAKS_BARIS);
        [$petaLabel, $petaTeks, $barisGrup, $bahaya] = $this->petaMaster($grup);

        $laporan = [];
        $ringkas = ['total' => 0, 'lolos' => 0, 'luar_grup' => 0, 'tak_terpetakan' => 0, 'belum_closed' => 0, 'oak_stop' => 0];
        foreach ($rows as $r) {
            $hazard = in_array($r['jenis'], ['HAZARD', 'INSPEKSI'], true);
            $teks = match ($r['jenis']) {
                'OBSERVASI' => (string) $r['obs'],
                'OBSERVASI AREA KRITIS' => trim((string) $r['oak']).' / '.trim((string) $r['oak_sub']),
                default => '',
            };
            $kandidat = $hazard
                ? ($petaLabel[DrmMaster::crc(DrmMaster::norm((string) $r['k']).' / '.DrmMaster::norm((string) $r['sk']))] ?? [])
                : ($petaTeks[DrmMaster::crc($teks)] ?? []);
            $lolos = array_values(array_filter($kandidat, fn ($ri) => isset($barisGrup[$ri])));
            $pemetaan = $lolos !== [] ? 'lolos' : ($kandidat !== [] ? 'luar_grup' : 'tak_terpetakan');
            $belum = $hazard && ! in_array(strtoupper((string) $r['status']), self::STATUS_SELESAI, true);
            $stop = $r['jenis'] === 'OBSERVASI AREA KRITIS' && stripos((string) $r['hasil_oak'], 'stop') !== false;

            $ringkas['total']++;
            $ringkas[$pemetaan]++;
            $ringkas['belum_closed'] += (int) $belum;
            $ringkas['oak_stop'] += (int) $stop;
            $laporan[] = [
                'waktu' => substr((string) $r['waktu'], 0, 16),
                'jenis' => (string) $r['jenis'],
                'isi' => $hazard ? trim((string) $r['k']).' / '.trim((string) $r['sk']) : $teks,
                'deskripsi' => (string) $r['deskripsi'],
                'status' => (string) $r['status'],
                'hasil_oak' => (string) $r['hasil_oak'],
                'pelapor' => (string) $r['pelapor'],
                'janji' => in_array((string) $r['janji'], ['', '1970-01-01'], true) ? '' : (string) $r['janji'],
                'belum_closed' => $belum,
                'oak_stop' => $stop,
                'pemetaan' => $pemetaan,
                'n_kandidat' => count($kandidat),
                'bahaya' => array_map(fn ($ri) => $bahaya[$ri] ?? (string) $ri, array_slice($lolos, 0, 5)),
                'n_lolos' => count($lolos),
            ];
        }

        return ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString(), 'laporan' => $laporan, 'ringkas' => $ringkas, 'terpotong' => $terpotong];
    }

    /**
     * @return array{0: array<int, list<int>>, 1: array<int, list<int>>, 2: array<int, true>, 3: array<int, string>}
     *   [crc label → ri, crc teks OBS/OAK → ri, ri di grup lokasi, ri → "aktivitas: bahaya"]
     */
    private function petaMaster(string $grup): array
    {
        $m = DrmMaster::muat()->data;
        $label = $teks = [];
        foreach ($m['label'] as $l) {
            $label[DrmMaster::crc(DrmMaster::norm($l['k']).' / '.DrmMaster::norm($l['sk']))] = $l['ri'];
        }
        foreach ([...$m['obs'], ...$m['oak']] as $t) {
            $c = DrmMaster::crc($t['teks']);
            $teks[$c] = array_values(array_unique([...($teks[$c] ?? []), ...$t['ri']]));
        }
        $gid = array_search($grup, $m['grup'], true);
        $barisGrup = $gid === false ? [] : array_fill_keys($m['grupBaris'][$gid] ?? [], true);
        $bahaya = [];
        foreach ($m['baris'] as $b) {
            $bahaya[$b['ri']] = $b['aktivitas'].': '.$b['bahaya'];
        }

        return [$label, $teks, $barisGrup, $bahaya];
    }
}
