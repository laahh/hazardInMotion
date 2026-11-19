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

    /** Naikkan bila bentuk respons berubah agar cache lama tidak terpakai. */
    private const VERSI_CACHE = 2;

    public function __construct(private readonly RfidClickHouseClient $ch)
    {
    }

    /**
     * @param  string  $grup  nama grup lokasi detail lokasi (dari snapshot)
     * @return array{dari: string, sampai: string, laporan: list<array<string, mixed>>, ringkas: array<string, int>, terpotong: bool}
     */
    public function ambil(int $dl, string $grup, CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $kunci = sprintf('peta-risiko:sap-dl:v%d:%d:%s:%s:%s', self::VERSI_CACHE, $dl, $dari->toDateString(), $sampai->toDateString(), md5($grup));

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
       substring(ifNull(deskripsi, \'\'), 1, 400) AS deskripsi, ifNull(pelapor, \'\') AS pelapor, ifNull(kode_sid_pelapor, \'\') AS sid,
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
        [$petaLabel, $petaTeks, $barisGrup, $bahaya, $petaOakAkt] = $this->petaMaster($grup);

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
                : array_values(array_unique([...($petaTeks[DrmMaster::crcTeks($teks)] ?? []),
                    // OAK juga lewat kurasi aktivitas OAK → aktivitas HIRA (config oak_aktivitas_hira).
                    ...($r['jenis'] === 'OBSERVASI AREA KRITIS' ? ($petaOakAkt[DrmMaster::crcTeks((string) $r['oak'])] ?? []) : [])]));
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
                'sid' => (string) $r['sid'],
                'janji' => in_array((string) $r['janji'], ['', '1970-01-01'], true) ? '' : (string) $r['janji'],
                'belum_closed' => $belum,
                'oak_stop' => $stop,
                'pemetaan' => $pemetaan,
                'n_kandidat' => count($kandidat),
                'bahaya' => array_map(fn ($ri) => $bahaya[$ri] ?? (string) $ri, array_slice($lolos, 0, 5)),
                'n_lolos' => count($lolos),
            ];
        }

        $pja = self::perPja($laporan, $this->anggotaPja(array_column($laporan, 'sid')));

        return ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString(), 'laporan' => $laporan, 'ringkas' => $ringkas, 'pja' => $pja, 'terpotong' => $terpotong];
    }

    /**
     * Laporan dikelompokkan per PJA pelapornya (penugasan aktif m_pja_emp). Pelapor anggota beberapa PJA dihitung
     * di setiap PJA-nya; pelapor yang bukan personil PJA mana pun masuk kelompok id '' (paling akhir).
     *
     * @param  list<array<string, mixed>>  $laporan
     * @param  array<string, list<array{id: string, nama: string, tipe: string, kategori: string}>>  $anggota  sid → PJA
     * @return list<array<string, mixed>>
     */
    public static function perPja(array $laporan, array $anggota): array
    {
        $bukan = [['id' => '', 'nama' => 'Bukan personil PJA', 'tipe' => '', 'kategori' => '']];
        $grup = [];
        foreach ($laporan as $l) {
            $sid = (string) ($l['sid'] ?? '');
            $kunciOrang = $sid !== '' ? $sid : 'nama:'.mb_strtolower(trim((string) $l['pelapor']));
            $lolos = (int) ($l['pemetaan'] === 'lolos');
            foreach ($anggota[$sid] ?? $bukan as $p) {
                $g = $grup[$p['id']] ?? ['id' => $p['id'], 'nama' => $p['nama'], 'tipe' => $p['tipe'], 'n' => 0, 'jenis' => [],
                    'lolos' => 0, 'belum_closed' => 0, 'oak_stop' => 0, 'pelapor' => []];
                $o = $g['pelapor'][$kunciOrang] ?? ['sid' => $sid, 'nama' => (string) $l['pelapor'], 'kategori' => $p['kategori'], 'n' => 0, 'jenis' => [], 'lolos' => 0];
                $o = self::tambahLaporan($o, (string) $l['jenis'], $lolos);
                $g = self::tambahLaporan($g, (string) $l['jenis'], $lolos);
                $g['belum_closed'] += (int) $l['belum_closed'];
                $g['oak_stop'] += (int) $l['oak_stop'];
                $g['pelapor'][$kunciOrang] = $o;
                $grup[$p['id']] = $g;
            }
        }
        $hasil = array_map(function (array $g): array {
            $orang = array_values($g['pelapor']);
            usort($orang, fn ($a, $b) => $b['n'] <=> $a['n'] ?: strcmp($a['nama'], $b['nama']));

            return [...$g, 'pelapor' => $orang];
        }, array_values($grup));
        usort($hasil, fn ($a, $b) => ($a['id'] === '') <=> ($b['id'] === '') ?: $b['n'] <=> $a['n'] ?: strcmp($a['nama'], $b['nama']));

        return $hasil;
    }

    /** @param  array<string, mixed>  $x */
    private static function tambahLaporan(array $x, string $jenis, int $lolos): array
    {
        $x['n']++;
        $x['jenis'][$jenis] = ($x['jenis'][$jenis] ?? 0) + 1;
        $x['lolos'] += $lolos;

        return $x;
    }

    /**
     * PJA aktif tiap SID pelapor (m_pja_emp → bep_vw_safety_all_karyawan), sama seperti personil di snapshot.
     *
     * @param  list<string>  $sid
     * @return array<string, list<array{id: string, nama: string, tipe: string, kategori: string}>>
     */
    private function anggotaPja(array $sid): array
    {
        $sid = array_values(array_unique(array_filter(array_map('strval', $sid), fn ($s) => $s !== '')));
        if ($sid === []) {
            return [];
        }
        $daftar = implode(', ', array_map([RfidClickHouseClient::class, 'quote'], $sid));
        $rows = $this->ch->select('
SELECT k.kode_sid AS sid, toString(pe.id_pja) AS id, ifNull(pj.name, \'\') AS nama, ifNull(t.name, \'\') AS tipe, ifNull(c.name, \'\') AS kategori
FROM (SELECT DISTINCT toInt64(id_pja) AS id_pja, toInt64(id_employee) AS id_emp, id_pja_type, category_id
      FROM obds.m_pja_emp WHERE is_active = 1) pe
JOIN (SELECT toInt64(id) AS id, any(kode_sid) AS kode_sid FROM obds.bep_vw_safety_all_karyawan
      GROUP BY id HAVING kode_sid IN ('.$daftar.')) k ON k.id = pe.id_emp
LEFT JOIN (SELECT id, any(name) AS name FROM obds.m_pja GROUP BY id) pj ON pj.id = pe.id_pja
LEFT JOIN (SELECT id, any(name) AS name FROM obds.m_pja_type GROUP BY id) t ON t.id = pe.id_pja_type
LEFT JOIN (SELECT id, any(name) AS name FROM obds.m_pja_category GROUP BY id) c ON c.id = pe.category_id', 60);
        $peta = [];
        foreach ($rows as $r) {
            $peta[(string) $r['sid']][] = ['id' => (string) $r['id'], 'nama' => (string) $r['nama'], 'tipe' => (string) $r['tipe'], 'kategori' => (string) $r['kategori']];
        }

        return $peta;
    }

    /**
     * @return array{0: array<int, list<int>>, 1: array<int, list<int>>, 2: array<int, true>, 3: array<int, string>, 4: array<int, list<int>>}
     *   [crc label → ri, crc teks OBS/OAK → ri, ri di grup lokasi, ri → "aktivitas: bahaya", crc aktivitas OAK → ri]
     */
    private function petaMaster(string $grup): array
    {
        $master = DrmMaster::muat();
        $m = $master->data;
        $label = $teks = [];
        foreach ($master->labelHazard($master->cfgEfektif((array) config('peta_risiko_drm'))) as $l) {
            $label[DrmMaster::crc(DrmMaster::norm($l['k']).' / '.DrmMaster::norm($l['sk']))] = $l['ri'];
        }
        foreach ([...$master->labelTeksAktivitas('obs'), ...$master->labelTeksAktivitas('oak')] as $t) {
            $teks[$t['crc']] = array_values(array_unique([...($teks[$t['crc']] ?? []), ...$t['ri']]));
        }
        $oakAkt = array_column($master->labelOakAktivitas($master->cfgEfektif((array) config('peta_risiko_drm'))), 'ri', 'crc');
        $gid = array_search($grup, $m['grup'], true);
        $barisGrup = $gid === false ? [] : array_fill_keys($m['grupBaris'][$gid] ?? [], true);
        $bahaya = [];
        foreach ($m['baris'] as $b) {
            $bahaya[$b['ri']] = $b['aktivitas'].': '.$b['bahaya'];
        }

        return [$label, $teks, $barisGrup, $bahaya, $oakAkt];
    }
}
