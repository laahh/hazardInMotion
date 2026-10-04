<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Concerns\MembacaObds;
use DateTimeImmutable;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Deep dive satu insiden: tab kedua dashboard Incident Management & IPLS.
 *
 * Menjawab lima pertanyaan berurutan untuk satu insiden terpilih:
 *
 *   1. apa yang terjadi            -> header + kronologi
 *   2. barrier mana yang jebol     -> root cause & non-conformity per layer IPLS
 *   3. apakah sudah ada sinyalnya  -> aktivitas SAP 90 hari sebelumnya di lokasi
 *   4. siapa yang terlibat         -> riwayat coaching/observasi/beRecord/DMS/MCU
 *   5. apakah sudah ditangani      -> CAR per layer + pengulangan root cause
 *
 * SUMBERNYA TUJUH MATERIALIZED VIEW, bukan satu: mv_investigasi untuk insiden,
 * mv_inspeksi_hazard / mv_oak / mv_coaching / mv_observasi untuk sinyal SAP,
 * mv_dms_violation_report dan mv_berecord untuk riwayat pelanggaran, serta
 * mv_ftw_mcu untuk status MCU.
 *
 * SINYAL SAP BARU ADA SEJAK 1 JANUARI 2026. Keempat view SAP itu kosong
 * sebelum tanggal tersebut, jadi jendela 90 hari baru benar-benar penuh untuk
 * insiden sejak April 2026. Untuk insiden yang lebih awal jendelanya terpotong
 * dan minggu-minggu sebelum 2026 tercatat nol -- bukan karena sepi, melainkan
 * karena datanya memang belum ada. Lihat AWAL_SAP; keadaan ini ikut dikirim ke
 * tampilan lewat flag 'terpotong' supaya tidak disalahbaca.
 *
 * BATAS TANGGAL JENDELA DIAMBIL DULU DI PHP, BARU DISULAM KE SQL SEBAGAI
 * LITERAL, dan itu bukan gaya-gayaan. Versi pertama mengambil waktu kejadian
 * lewat CTE lalu memakainya sebagai batas (tanggal >= i.t - interval '91
 * days'); dengan bentuk itu Postgres tidak bisa memakai indeks tanggal dan
 * kuerinya memakan 26 detik untuk insiden di BMO 1 -- 16,5 detik di antaranya
 * hanya untuk mv_coaching yang isinya cuma 358 ribu baris. Dengan batas berupa
 * literal, indeksnya terpakai dan seluruh kueri selesai dalam 1,5 detik.
 * Timestamp-nya diformat dari objek DateTimeImmutable milik kita sendiri, jadi
 * aman disulam; site dan lokasi tetap dikirim sebagai parameter terikat karena
 * nilainya teks bebas. JANGAN dikembalikan ke bentuk CTE.
 */
final class IncidentDeepDiveController extends Controller
{
    use MembacaObds;

    /** Hasil per insiden ditahan; lihat catatan biaya kueri di docblock kelas. */
    private const CACHE_TTL = 900;

    /** Daftar insiden jarang berubah dan dipakai tiap kali tab dibuka. */
    private const CACHE_TTL_DAFTAR = 600;

    /** Tanggal paling awal data SAP tersedia; lihat docblock kelas. */
    private const AWAL_SAP = '2026-01-01';

    /** Jendela pengamatan sebelum kejadian, dalam hari. */
    private const JENDELA_HARI = 91;

    /** Insiden yang bisa dipilih: yang diinvestigasi sejak tahun ini. */
    private const AWAL_DAFTAR = '2026-01-01';

    /** Status temuan hazard yang tidak dianggap temuan sungguhan. */
    private const HAZARD_DIABAIKAN = "('BUKAN TEMUAN', 'DELETED')";

    /**
     * Daftar insiden untuk dropdown pemilih.
     */
    public function daftar(): JsonResponse
    {
        try {
            $isi = Cache::remember(
                'ohs-score-card.deep-dive.daftar.v1',
                self::CACHE_TTL_DAFTAR,
                fn (): array => $this->ambilDaftar()
            );
        } catch (Throwable $e) {
            return $this->gagalObds($e);
        }

        return response()->json(['ok' => true, 'insiden' => $isi]);
    }

    /**
     * Seluruh isi deep dive untuk satu insiden.
     */
    public function detail(Request $request, int $insiden): JsonResponse
    {
        if ($insiden <= 0) {
            return response()->json(['ok' => false, 'pesan' => 'ID investigasi tidak valid.']);
        }

        try {
            $isi = Cache::remember(
                'ohs-score-card.deep-dive.insiden.v1.' . $insiden,
                self::CACHE_TTL,
                fn (): ?array => $this->ambilDetail($insiden)
            );
        } catch (Throwable $e) {
            return $this->gagalObds($e);
        }

        if ($isi === null) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Insiden #' . $insiden . ' tidak ditemukan, atau statusnya DELETED.',
            ]);
        }

        return response()->json(['ok' => true] + $isi);
    }

    // ======================================================================
    // Pengambilan data
    // ======================================================================

    /** @return array<int, array<string, mixed>> */
    private function ambilDaftar(): array
    {
        $awal = self::AWAL_DAFTAR;

        $rows = $this->bacaObds(<<<SQL
            SELECT id_investigasi AS id,
                   to_char(COALESCE(tanggal_kejadian, ccr_tanggal_insiden), 'YYYY-MM-DD') AS tanggal,
                   COALESCE(site, ccr_site) AS site,
                   COALESCE(lokasi, ccr_lokasi) AS lokasi,
                   COALESCE(kategori_kecelakaan, 'Belum dikategorikan') AS kategori,
                   ccr_jenis_insiden AS jenis,
                   jsonb_array_length(COALESCE(rootcause, '[]')) AS temuan
            FROM bcbeats.mv_investigasi
            WHERE COALESCE(tanggal_kejadian, ccr_tanggal_insiden) >= DATE '{$awal}'
              AND status_investigasi = 'INVESTIGASI'
              AND id_investigasi IS NOT NULL
            ORDER BY COALESCE(tanggal_kejadian, ccr_tanggal_insiden) DESC
            SQL);

        return array_map(static fn (object $r): array => [
            'id' => (int) $r->id,
            'tanggal' => (string) $r->tanggal,
            'site' => trim((string) ($r->site ?? '')) ?: '-',
            'lokasi' => trim((string) ($r->lokasi ?? '')) ?: '-',
            'kategori' => (string) $r->kategori,
            'jenis' => trim((string) ($r->jenis ?? '')) ?: '-',
            'temuan' => (int) $r->temuan,
        ], $rows);
    }

    /** @return array<string, mixed>|null */
    private function ambilDetail(int $id): ?array
    {
        $inti = $this->ambilInti($id);

        if ($inti === null) {
            return null;
        }

        return [
            'header' => $inti['header'],
            'temuan' => $inti['temuan'],
            'car' => $inti['car'],
            'rekurensi' => $inti['rekurensi'],
            'biaya' => $inti['biaya'],
            'sinyal' => $this->ambilSinyal($id),
            'pekerja' => $this->ambilPekerja($id),
        ];
    }

    /**
     * Header, temuan layer, CAR, dan pengulangan root cause.
     *
     * @return array<string, mixed>|null
     */
    private function ambilInti(int $id): ?array
    {
        $row = $this->bacaSatuObds(<<<'SQL'
            WITH i AS (
                SELECT id_investigasi AS id,
                       COALESCE(tanggal_kejadian, ccr_tanggal_insiden) AS t,
                       COALESCE(site, ccr_site) AS site,
                       pja_bc, rootcause, tindakan_perbaikan, biaya
                FROM bcbeats.mv_investigasi
                WHERE id_investigasi = ? AND status_investigasi <> 'DELETED'
            ),
            ra AS (
                SELECT DISTINCT r->>'activity_layer' AS a, r->>'layer' AS l
                FROM i, jsonb_array_elements(COALESCE(i.rootcause, '[]')) r
                WHERE r->>'status_layer' = 'ROOT CAUSE'
            ),
            lain AS (
                SELECT m.id_investigasi AS id,
                       COALESCE(m.tanggal_kejadian, m.ccr_tanggal_insiden) AS t,
                       COALESCE(m.site, m.ccr_site) AS site,
                       m.pja_bc, r->>'activity_layer' AS a
                FROM bcbeats.mv_investigasi m, jsonb_array_elements(COALESCE(m.rootcause, '[]')) r
                WHERE r->>'status_layer' = 'ROOT CAUSE'
                  AND m.id_investigasi <> ?
                  AND m.status_investigasi <> 'DELETED'
            )
            SELECT
              (SELECT json_build_object(
                 'id', m.id_investigasi,
                 'waktu', to_char(COALESCE(m.tanggal_kejadian, m.ccr_tanggal_insiden), 'YYYY-MM-DD HH24:MI'),
                 'site', COALESCE(m.site, m.ccr_site),
                 'lokasi', COALESCE(m.lokasi, m.ccr_lokasi),
                 'detil', COALESCE(m.detil_lokasi, m.ccr_detil_lokasi),
                 'pja', m.pja_bc, 'pja_mitra', m.pja_mitra_kerja, 'perusahaan', m.perusahaan,
                 'kategori', COALESCE(m.kategori_kecelakaan, 'Belum dikategorikan'),
                 'jenis', m.ccr_jenis_insiden, 'status', m.status_investigasi, 'lpi', m.status_lpi,
                 'kronologi', left(COALESCE(m.kronologi_kecelakaan, m.ccr_kronologi), 3000),
                 'potensi', left(m.potensi_kejadian, 300),
                 'pic', m.ccr_nama_pic_investigasi)
               FROM bcbeats.mv_investigasi m WHERE m.id_investigasi = i.id) AS header,
              (SELECT json_agg(json_build_object(
                 'layer', r->>'layer', 'status', r->>'status_layer',
                 'aktivitas', r->>'activity_layer', 'klasifikasi', r->>'klasifikasi_layer',
                 'keterangan', left(r->>'keterangan_layer', 400)))
               FROM i, jsonb_array_elements(COALESCE(i.rootcause, '[]')) r) AS temuan,
              (SELECT json_agg(json_build_object(
                 'layer', c->>'layer_tindakan_perbaikan',
                 'aktivitas', c->>'detail_layer_tindakan_perbaikan',
                 'tindakan', left(trim(regexp_replace(c->>'tindakan_perbaikan_pencegahan', '\s+', ' ', 'g')), 260),
                 'status', c->>'status_perbaikan',
                 'target', left(c->>'target_waktu', 10),
                 'aktual', left(c->>'waktu_aktual_perbaikan', 10),
                 'pic', c->>'pic_perbaikan'))
               FROM i, jsonb_array_elements(COALESCE(i.tindakan_perbaikan, '[]')) c) AS car,
              (SELECT sum((b->>'biaya')::numeric)
               FROM i, jsonb_array_elements(COALESCE(i.biaya, '[]')) b) AS biaya,
              (SELECT json_agg(json_build_object('aktivitas', ra.a, 'layer', ra.l,
                 'pja_sebelum', (SELECT count(DISTINCT lain.id) FROM lain, i
                                 WHERE lain.a = ra.a AND lain.pja_bc = i.pja_bc AND lain.t < i.t),
                 'pja_sesudah', (SELECT count(DISTINCT lain.id) FROM lain, i
                                 WHERE lain.a = ra.a AND lain.pja_bc = i.pja_bc AND lain.t > i.t),
                 'site_sebelum', (SELECT count(DISTINCT lain.id) FROM lain, i
                                  WHERE lain.a = ra.a AND lain.site = i.site AND lain.t < i.t),
                 'site_sesudah', (SELECT count(DISTINCT lain.id) FROM lain, i
                                  WHERE lain.a = ra.a AND lain.site = i.site AND lain.t > i.t),
                 'daftar', (SELECT json_agg(x) FROM (
                      SELECT DISTINCT lain.id, to_char(lain.t, 'YYYY-MM-DD') AS d
                      FROM lain, i WHERE lain.a = ra.a AND lain.pja_bc = i.pja_bc
                      ORDER BY 2 DESC LIMIT 6) x)))
               FROM ra) AS rekurensi
            FROM i
            SQL, [$id, $id]);

        if ($row === null || $row->header === null) {
            return null;
        }

        return [
            'header' => $this->uraikanJson($row->header),
            'temuan' => $this->uraikanJson($row->temuan),
            'car' => $this->uraikanJson($row->car),
            'rekurensi' => $this->uraikanJson($row->rekurensi),
            'biaya' => $row->biaya === null ? null : (float) $row->biaya,
        ];
    }

    /**
     * Sinyal SAP 90 hari sebelum kejadian, di site dan lokasi yang sama.
     *
     * Minggu dihitung MUNDUR dari waktu kejadian: 0 berarti tujuh hari terakhir
     * sebelum insiden, 12 berarti dua belas minggu sebelumnya.
     *
     * @return array<string, mixed>
     */
    private function ambilSinyal(int $id): array
    {
        $insiden = $this->bacaSatuObds(<<<'SQL'
            SELECT COALESCE(tanggal_kejadian, ccr_tanggal_insiden) AS t,
                   COALESCE(site, ccr_site) AS site,
                   COALESCE(lokasi, ccr_lokasi) AS lokasi,
                   COALESCE(detil_lokasi, ccr_detil_lokasi) AS detil
            FROM bcbeats.mv_investigasi WHERE id_investigasi = ?
            SQL, [$id]);

        if ($insiden === null || $insiden->t === null) {
            return [];
        }

        $kejadian = new DateTimeImmutable((string) $insiden->t);
        $akhir = $kejadian->format('Y-m-d H:i:s');
        $mulai = $kejadian->modify('-' . self::JENDELA_HARI . ' days')->format('Y-m-d H:i:s');
        $abai = self::HAZARD_DIABAIKAN;

        $row = $this->bacaSatuObds(<<<SQL
            WITH h AS (
                SELECT floor(extract(epoch FROM TIMESTAMP '{$akhir}' - h.tanggal_laporan) / 604800)::int AS w,
                       (h.lokasi = ?) AS di_lokasi,
                       (h.lokasi = ? AND h.detil_lokasi = ?) AS di_detil,
                       h.tanggal_laporan AS tl, h.tanggal_janji_penyelesaian AS jp,
                       h.tanggal_aktual_penyelesaian AS ap, TIMESTAMP '{$akhir}' AS it,
                       h.ketidaksesuaian, h.deskripsi_temuan, h.detil_lokasi, h.nilai_resiko
                FROM bcbeats.mv_inspeksi_hazard h
                WHERE h.site = ?
                  AND h.tanggal_laporan < TIMESTAMP '{$akhir}'
                  AND h.tanggal_laporan >= TIMESTAMP '{$mulai}'
                  AND h.status_laporan NOT IN {$abai}
            ),
            o AS (SELECT floor(extract(epoch FROM TIMESTAMP '{$akhir}' - tanggal_submit) / 604800)::int AS w,
                         count(DISTINCT id_oak) AS n
                  FROM bcbeats.mv_oak
                  WHERE site = ? AND lokasi = ?
                    AND tanggal_submit < TIMESTAMP '{$akhir}' AND tanggal_submit >= TIMESTAMP '{$mulai}'
                  GROUP BY 1),
            c AS (SELECT floor(extract(epoch FROM TIMESTAMP '{$akhir}' - tanggal_coaching) / 604800)::int AS w,
                         count(*) AS n
                  FROM bcbeats.mv_coaching
                  WHERE site = ? AND lokasi = ?
                    AND tanggal_coaching < TIMESTAMP '{$akhir}' AND tanggal_coaching >= TIMESTAMP '{$mulai}'
                  GROUP BY 1),
            b AS (SELECT floor(extract(epoch FROM TIMESTAMP '{$akhir}' - tanggal_observasi) / 604800)::int AS w,
                         count(DISTINCT id_observasi) AS n
                  FROM bcbeats.mv_observasi
                  WHERE site = ? AND lokasi = ?
                    AND tanggal_observasi < TIMESTAMP '{$akhir}' AND tanggal_observasi >= TIMESTAMP '{$mulai}'
                  GROUP BY 1)
            SELECT
              (SELECT json_agg(json_build_object('w', w, 'lokasi', n, 'detil', d, 'site', s))
               FROM (SELECT w, count(*) FILTER (WHERE di_lokasi) AS n,
                            count(*) FILTER (WHERE di_detil) AS d, count(*) AS s
                     FROM h GROUP BY w) z) AS hazard,
              (SELECT json_agg(json_build_object('w', w, 'n', n)) FROM o) AS oak,
              (SELECT json_agg(json_build_object('w', w, 'n', n)) FROM c) AS coaching,
              (SELECT json_agg(json_build_object('w', w, 'n', n)) FROM b) AS observasi,
              (SELECT json_build_object('n', count(*), 'detil', count(*) FILTER (WHERE di_detil),
                      'terbuka', count(*) FILTER (WHERE ap IS NULL OR ap > it::date),
                      'menggantung', count(*) FILTER (WHERE (ap IS NULL OR ap > it::date) AND tl < it - interval '7 days'),
                      'lewat_target', count(*) FILTER (WHERE jp < it::date AND (ap IS NULL OR ap > it::date)))
               FROM h WHERE di_lokasi) AS lokasi,
              (SELECT json_build_object('n', count(*),
                      'terbuka', count(*) FILTER (WHERE ap IS NULL OR ap > it::date),
                      'lewat_target', count(*) FILTER (WHERE jp < it::date AND (ap IS NULL OR ap > it::date)))
               FROM h) AS site,
              (SELECT json_agg(x) FROM (
                  SELECT COALESCE(NULLIF(ketidaksesuaian, ''), '(tanpa kategori)') AS k, count(*) AS n
                  FROM h WHERE di_lokasi GROUP BY 1 ORDER BY 2 DESC LIMIT 8) x) AS tema,
              (SELECT json_agg(x) FROM (
                  SELECT to_char(tl, 'YYYY-MM-DD') AS d, detil_lokasi AS detil,
                         left(deskripsi_temuan, 200) AS deskripsi,
                         COALESCE(NULLIF(ketidaksesuaian, ''), '-') AS k,
                         nilai_resiko AS risiko, to_char(jp, 'YYYY-MM-DD') AS target,
                         (jp < it::date) AS lewat
                  FROM h WHERE di_lokasi AND (ap IS NULL OR ap > it::date) AND tl < it - interval '7 days'
                  ORDER BY di_detil DESC, (jp < it::date) DESC, tl DESC LIMIT 8) x) AS menggantung
            SQL, [
            $insiden->lokasi, $insiden->lokasi, $insiden->detil, $insiden->site,
            $insiden->site, $insiden->lokasi,
            $insiden->site, $insiden->lokasi,
            $insiden->site, $insiden->lokasi,
        ]);

        if ($row === null) {
            return [];
        }

        $row->mulai_jendela = $kejadian->modify('-' . self::JENDELA_HARI . ' days')->format('Y-m-d');
        $row->terpotong = $row->mulai_jendela < self::AWAL_SAP;

        return [
            'mulai_jendela' => $row->mulai_jendela,
            'terpotong' => (bool) $row->terpotong,
            'awal_sap' => self::AWAL_SAP,
            'hazard' => $this->uraikanJson($row->hazard),
            'oak' => $this->uraikanJson($row->oak),
            'coaching' => $this->uraikanJson($row->coaching),
            'observasi' => $this->uraikanJson($row->observasi),
            'lokasi' => $this->uraikanJson($row->lokasi),
            'site' => $this->uraikanJson($row->site),
            'tema' => $this->uraikanJson($row->tema),
            'menggantung' => $this->uraikanJson($row->menggantung),
        ];
    }

    /**
     * Riwayat tiap pekerja yang terlibat, pada 90 hari sebelum kejadian.
     *
     * beRecord dan insiden sebelumnya sengaja dihitung SEUMUR RIWAYAT, bukan 90
     * hari: keduanya catatan yang jarang terjadi, dan membatasinya ke tiga
     * bulan akan menyembunyikan justru pola yang dicari.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ambilPekerja(int $id): array
    {
        $row = $this->bacaSatuObds(<<<'SQL'
            WITH i AS (
                SELECT id_investigasi AS id,
                       COALESCE(tanggal_kejadian, ccr_tanggal_insiden) AS t,
                       pekerja_terlibat
                FROM bcbeats.mv_investigasi WHERE id_investigasi = ?
            ),
            p AS (
                SELECT DISTINCT ON (x->>'kode_sid')
                       x->>'kode_sid' AS sid, x->>'nama_pekerja' AS nama,
                       x->>'perusahaan' AS perusahaan, x->>'jabatan_fungsional' AS jabatan,
                       x->>'kategori_pekerja_terlibat' AS peran, i.t, i.id
                FROM i, jsonb_array_elements(COALESCE(i.pekerja_terlibat, '[]')) x
                WHERE COALESCE(x->>'kode_sid', '') <> ''
            )
            SELECT json_agg(json_build_object(
              'sid', sid, 'nama', nama, 'perusahaan', perusahaan, 'jabatan', jabatan, 'peran', peran,
              'coaching', (SELECT count(*) FROM bcbeats.mv_coaching c
                           WHERE c.kode_sid_coachee = p.sid
                             AND c.tanggal_coaching < p.t AND c.tanggal_coaching >= p.t - interval '90 days'),
              'observasi', (SELECT count(DISTINCT id_observasi) FROM bcbeats.mv_observasi o
                            WHERE o.kode_sid_personil_diobservasi = p.sid
                              AND o.tanggal_observasi < p.t AND o.tanggal_observasi >= p.t - interval '90 days'),
              'sap', (SELECT count(*) FROM bcbeats.mv_oak o
                      WHERE o.kode_sid_pelapor = p.sid
                        AND o.tanggal_submit < p.t AND o.tanggal_submit >= p.t - interval '90 days')
                   + (SELECT count(*) FROM bcbeats.mv_inspeksi_hazard h
                      WHERE h.kode_sid_pelapor = p.sid
                        AND h.tanggal_laporan < p.t AND h.tanggal_laporan >= p.t - interval '90 days'),
              'berecord', (SELECT json_agg(json_build_object(
                             'tanggal', to_char(tanggal_mulai_berecord, 'YYYY-MM-DD'),
                             'kategori', kategori_berecord, 'tipe', tipe_berecord)
                             ORDER BY tanggal_mulai_berecord DESC)
                           FROM bcsid.mv_berecord b
                           WHERE b.kode_sid = p.sid AND b.tanggal_mulai_berecord < p.t::date),
              'dms', (SELECT json_agg(json_build_object('pelanggaran', v, 'n', n)) FROM (
                        SELECT nama_pelanggaran AS v, count(*) AS n
                        FROM bcsid.mv_dms_violation_report d
                        WHERE d.kode_sid = p.sid
                          AND d.waktu_deteksi_alert < p.t AND d.waktu_deteksi_alert >= p.t - interval '90 days'
                        GROUP BY 1 ORDER BY 2 DESC) z),
              'insiden', (SELECT json_agg(json_build_object(
                            'id', m.id_investigasi,
                            'tanggal', to_char(COALESCE(m.tanggal_kejadian, m.ccr_tanggal_insiden), 'YYYY-MM-DD'),
                            'kategori', m.kategori_kecelakaan)
                            ORDER BY COALESCE(m.tanggal_kejadian, m.ccr_tanggal_insiden) DESC)
                          FROM bcbeats.mv_investigasi m, jsonb_array_elements(COALESCE(m.pekerja_terlibat, '[]')) y
                          WHERE y->>'kode_sid' = p.sid AND m.id_investigasi <> p.id
                            AND m.status_investigasi <> 'DELETED'
                            AND COALESCE(m.tanggal_kejadian, m.ccr_tanggal_insiden) < p.t),
              'mcu', (SELECT CASE
                        WHEN count(*) FILTER (WHERE tanggal_mulai <= p.t AND tanggal_kadaluarsa >= p.t) > 0 THEN 'berlaku'
                        WHEN count(*) > 0 THEN 'kadaluarsa'
                        ELSE 'tidak ada data' END
                      FROM bcsid.mv_ftw_mcu f WHERE f.kode_sid = p.sid))
              ORDER BY (peran = 'Korban/Pelaku') DESC, peran) AS pekerja
            FROM p
            SQL, [$id]);

        return $row === null ? [] : $this->uraikanJson($row->pekerja);
    }
}
