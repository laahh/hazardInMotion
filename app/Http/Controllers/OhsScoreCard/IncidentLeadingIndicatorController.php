<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Concerns\MembacaObds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Leading indicator mingguan: tab ketiga dashboard Incident Management & IPLS.
 *
 * Satu baris per site per minggu, berisi indikator leading (laporan hazard,
 * OAK, coaching, observasi, pelanggaran DMS) berdampingan dengan indikator
 * lagging (jumlah insiden). Uji lead-lag-nya dikerjakan di browser karena
 * datanya cuma ratusan baris dan hasilnya ikut berubah tiap ganti site.
 *
 * SELURUHNYA 2026 SAJA, dan itu bukan pilihan: keempat view SAP baru terisi
 * sejak 1 Januari 2026. Lihat AWAL.
 *
 * COUNT(DISTINCT) YANG SALAH TEMPAT MEMBUAT KUERI INI TIMEOUT. mv_oak berisi
 * 3,9 juta baris dan mv_observasi 1,75 juta, dan satu OAK muncul berkali-kali
 * (514 ribu baris hanya berisi 152 ribu id_oak berbeda) karena satu baris per
 * anggota tim. Menulis count(DISTINCT id_oak) langsung di dalam GROUP BY
 * site x minggu membuat kuerinya melewati 30 detik. Bentuk dua tahap --
 * SELECT DISTINCT dulu, baru dihitung -- menyelesaikan seluruh kueri dalam
 * sekitar 3,4 detik. Jangan disederhanakan kembali jadi satu tahap.
 *
 * MINGGU TERAKHIR SERING BELUM LENGKAP karena view-nya snapshot. Batasnya
 * ditentukan di sini (lihat batasMingguLengkap()) supaya grafik tidak
 * memperlihatkan penurunan tajam yang sebenarnya cuma data yang belum masuk.
 */
final class IncidentLeadingIndicatorController extends Controller
{
    use MembacaObds;

    /**
     * Agregasi mingguan ini memindai jutaan baris, sekitar 3,4 detik, dan
     * isinya hanya berubah sekali sehari saat materialized view di-refresh.
     */
    private const CACHE_TTL = 3600;

    private const CACHE_KEY = 'ohs-score-card.leading-indicator.v1';

    /** Data SAP baru ada sejak tanggal ini; lihat docblock kelas. */
    private const AWAL = '2026-01-01';

    /** Senin pertama yang dipakai sebagai awal deret mingguan. */
    private const AWAL_MINGGU = '2026-01-05';

    private const HAZARD_DIABAIKAN = "('BUKAN TEMUAN', 'DELETED')";

    /**
     * Kategori kecelakaan yang dianggap berkonsekuensi nyata, dipakai sebagai
     * deret pembanding di samping cacah insiden seluruhnya.
     */
    private const KATEGORI_BERKONSEKUENSI =
        "('First Aid', 'Medical Treatment Injury', 'Major Injury', 'Property Damage', 'Fire Case')";

    /**
     * Minggu dianggap belum lengkap bila totalnya di bawah ambang ini dikali
     * median mingguan. Nilainya longgar; yang dicari hanya ekor yang jelas
     * terpotong, bukan minggu yang kebetulan sepi.
     */
    private const AMBANG_LENGKAP = 0.6;

    public function data(): JsonResponse
    {
        try {
            $isi = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): array => $this->kumpulkan());
        } catch (Throwable $e) {
            return $this->gagalObds($e);
        }

        return response()->json(['ok' => true] + $isi);
    }

    // ======================================================================
    // Pengambilan data
    // ======================================================================

    /** @return array<string, mixed> */
    private function kumpulkan(): array
    {
        $rows = $this->ambilMingguan();

        $site = [];
        $minggu = [];

        foreach ($rows as $r) {
            $site[trim((string) $r->site)] = true;
            $minggu[(string) $r->minggu] = true;
        }

        $daftarSite = array_keys($site);
        sort($daftarSite);
        $petaSite = array_flip($daftarSite);

        $daftarMinggu = array_keys($minggu);
        sort($daftarMinggu);

        $batas = $this->batasMingguLengkap($rows, $daftarMinggu);

        return [
            'site' => $daftarSite,
            'minggu' => $daftarMinggu,
            'batas_lengkap' => $batas,
            'awal_sap' => self::AWAL,
            // Satu baris = [siteIdx, minggu, hazard, jatuh_tempo, tepat_waktu,
            //               lewat_target, pelapor, oak, coaching, observasi,
            //               dms, insiden, berkonsekuensi]
            'baris' => array_map(static fn (object $r): array => [
                $petaSite[trim((string) $r->site)],
                (string) $r->minggu,
                (int) $r->hazard, (int) $r->jatuh_tempo, (int) $r->tepat_waktu,
                (int) $r->lewat_target, (int) $r->pelapor, (int) $r->oak,
                (int) $r->coaching, (int) $r->observasi, (int) $r->dms,
                (int) $r->insiden, (int) $r->berkonsekuensi,
            ], $rows),
            'meta' => [
                'koneksi' => $this->koneksiObds(),
                'diambil' => now()->format('d/m/Y H:i'),
            ],
        ];
    }

    /**
     * Minggu terakhir yang datanya pantas dipercaya.
     *
     * Diperiksa per sumber utama: ekor yang totalnya jauh di bawah median
     * dipotong, lalu diambil yang paling konservatif di antara keempatnya.
     *
     * @param  array<int, object>  $rows
     * @param  array<int, string>  $daftarMinggu
     */
    private function batasMingguLengkap(array $rows, array $daftarMinggu): ?string
    {
        if ($daftarMinggu === []) {
            return null;
        }

        $batas = [];

        foreach (['hazard', 'oak', 'coaching', 'observasi'] as $kolom) {
            $total = [];

            foreach ($daftarMinggu as $m) {
                $total[$m] = 0;
            }

            foreach ($rows as $r) {
                $total[(string) $r->minggu] += (int) $r->{$kolom};
            }

            $nilai = array_values($total);
            sort($nilai);
            $median = $nilai[intdiv(count($nilai), 2)] ?? 0;

            $i = count($daftarMinggu) - 1;

            while ($i > 0 && $total[$daftarMinggu[$i]] < self::AMBANG_LENGKAP * $median) {
                $i--;
            }

            $batas[] = $daftarMinggu[$i];
        }

        sort($batas);

        return $batas[0];
    }

    /** @return array<int, object> */
    private function ambilMingguan(): array
    {
        $abai = self::HAZARD_DIABAIKAN;
        $konsekuensi = self::KATEGORI_BERKONSEKUENSI;
        $awal = self::AWAL;
        $awalMinggu = self::AWAL_MINGGU;

        return $this->bacaObds(<<<SQL
            WITH hz AS (
                SELECT site AS s, date_trunc('week', tanggal_laporan)::date AS w,
                       count(*) AS n,
                       count(*) FILTER (
                           WHERE tanggal_janji_penyelesaian IS NOT NULL
                             AND (tanggal_aktual_penyelesaian IS NOT NULL
                                  OR tanggal_janji_penyelesaian < current_date)) AS jatuh_tempo,
                       count(*) FILTER (WHERE tanggal_aktual_penyelesaian <= tanggal_janji_penyelesaian) AS tepat,
                       count(*) FILTER (
                           WHERE tanggal_aktual_penyelesaian IS NULL
                             AND tanggal_janji_penyelesaian < current_date) AS lewat
                FROM bcbeats.mv_inspeksi_hazard
                WHERE status_laporan NOT IN {$abai}
                GROUP BY 1, 2
            ),
            -- Dua tahap, bukan count(DISTINCT) langsung; lihat docblock kelas.
            pelapor AS (
                SELECT s, w, count(*) AS n FROM (
                    SELECT DISTINCT site AS s, date_trunc('week', tanggal_laporan)::date AS w, kode_sid_pelapor
                    FROM bcbeats.mv_inspeksi_hazard WHERE status_laporan NOT IN {$abai}) d
                GROUP BY 1, 2
            ),
            oak AS (
                SELECT s, w, count(*) AS n FROM (
                    SELECT DISTINCT id_oak, site AS s, date_trunc('week', tanggal_submit)::date AS w
                    FROM bcbeats.mv_oak) d
                GROUP BY 1, 2
            ),
            co AS (
                SELECT site AS s, date_trunc('week', tanggal_coaching)::date AS w, count(*) AS n
                FROM bcbeats.mv_coaching GROUP BY 1, 2
            ),
            ob AS (
                SELECT s, w, count(*) AS n FROM (
                    SELECT DISTINCT id_observasi, site AS s, date_trunc('week', tanggal_observasi)::date AS w
                    FROM bcbeats.mv_observasi) d
                GROUP BY 1, 2
            ),
            dm AS (
                SELECT site AS s, date_trunc('week', waktu_deteksi_alert)::date AS w, count(*) AS n
                FROM bcsid.mv_dms_violation_report
                WHERE waktu_deteksi_alert >= DATE '{$awal}' GROUP BY 1, 2
            ),
            inc AS (
                SELECT COALESCE(site, ccr_site) AS s,
                       date_trunc('week', COALESCE(tanggal_kejadian, ccr_tanggal_insiden))::date AS w,
                       count(*) AS n,
                       count(*) FILTER (WHERE kategori_kecelakaan IN {$konsekuensi}) AS berkonsekuensi
                FROM bcbeats.mv_investigasi
                WHERE COALESCE(tanggal_kejadian, ccr_tanggal_insiden) >= DATE '{$awal}'
                  AND status_investigasi <> 'DELETED'
                  AND COALESCE(kategori_kecelakaan, '') <> 'Test'
                GROUP BY 1, 2
            ),
            kunci AS (
                SELECT s, w FROM hz
                UNION SELECT s, w FROM oak
                UNION SELECT s, w FROM inc
                UNION SELECT s, w FROM dm
            )
            SELECT k.s AS site, to_char(k.w, 'YYYY-MM-DD') AS minggu,
                   COALESCE(hz.n, 0) AS hazard,
                   COALESCE(hz.jatuh_tempo, 0) AS jatuh_tempo,
                   COALESCE(hz.tepat, 0) AS tepat_waktu,
                   COALESCE(hz.lewat, 0) AS lewat_target,
                   COALESCE(pelapor.n, 0) AS pelapor,
                   COALESCE(oak.n, 0) AS oak,
                   COALESCE(co.n, 0) AS coaching,
                   COALESCE(ob.n, 0) AS observasi,
                   COALESCE(dm.n, 0) AS dms,
                   COALESCE(inc.n, 0) AS insiden,
                   COALESCE(inc.berkonsekuensi, 0) AS berkonsekuensi
            FROM kunci k
            LEFT JOIN hz USING (s, w)
            LEFT JOIN pelapor USING (s, w)
            LEFT JOIN oak USING (s, w)
            LEFT JOIN co USING (s, w)
            LEFT JOIN ob USING (s, w)
            LEFT JOIN dm USING (s, w)
            LEFT JOIN inc USING (s, w)
            WHERE k.w >= DATE '{$awalMinggu}' AND k.s IS NOT NULL
            ORDER BY k.s, k.w
            SQL);
    }
}
