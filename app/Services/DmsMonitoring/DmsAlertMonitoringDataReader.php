<?php

declare(strict_types=1);

namespace App\Services\DmsMonitoring;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Data mentah untuk /pra-operasi/dashboard (monitoring alert DMS L1/L2).
 *
 * SUMBER & CATATAN KEAMANAN QUERY (penting — lihat riwayat 504 di modul lain):
 *   - bcsid.mv_dms_alert: MATERIALIZED VIEW, terindeks, sumber UTAMA & teraman.
 *     Semua query di sini SELALU pakai window tanggal via waktu_deteksi.
 *   - bcsid.dms_violation_report_log, bcsid.dms_vehicle_status_alerts: FOREIGN
 *     TABLE (FDW). Dicek langsung via EXPLAIN: cost-nya FLAT/tidak reflektif
 *     (mirip masalah yang pernah bikin 504 di clean_data_fatigue_check), tapi
 *     query AGREGAT SEDERHANA (bukan loop per-SID/chunk) terbukti tetap
 *     cepat saat dieksekusi nyata. Karena itu SEMUA akses ke dua tabel ini
 *     WAJIB tetap agregat tunggal — JANGAN PERNAH di-chunk atau di-loop per SID.
 *   - bcsid.dms_alert (raw, bukan mv_dms_alert) dan bcsid.dms_spv_schedule
 *     SENGAJA TIDAK dipakai — dms_alert diketahui COUNT(*) timeout tanpa
 *     filter dan tidak ada jalan aman untuk resolusi identitas reviewer;
 *     dms_spv_schedule butuh join yang belum tervalidasi. Panel yang
 *     harusnya butuh ini (mis. performa per-reviewer) diganti dengan agregat
 *     per site dari mv_dms_alert saja.
 */
final class DmsAlertMonitoringDataReader
{
    /** Batas jumlah kategori pelanggaran yang ditampilkan di kuadran. */
    private const CATEGORY_LIMIT = 500;

    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $connectionSource,
    ) {}

    public function isUp(): bool
    {
        return $this->connectionSource->isUp();
    }

    /**
     * @return array{
     *     total:int, l1_reviewed:int, l1_confirmed:int, l1_dismissed:int, l1_belum:int,
     *     l2_reviewed:int, l2_confirmed:int, post_event_eligible:int
     * }
     */
    public function alertSummary(string $start, string $end): array
    {
        $empty = ['total' => 0, 'l1_reviewed' => 0, 'l1_confirmed' => 0, 'l1_dismissed' => 0, 'l1_belum' => 0, 'l2_reviewed' => 0, 'l2_confirmed' => 0, 'post_event_eligible' => 0];

        return $this->remember('summary', $start, $end, function () use ($start, $end, $empty): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return $empty;
            }

            $sql = "
                SELECT
                    count(*) AS total,
                    count(*) FILTER (WHERE sudah_direview_l1 = true) AS l1_reviewed,
                    count(*) FILTER (WHERE sudah_direview_l1 = true AND l1_context_status = true) AS l1_confirmed,
                    count(*) FILTER (WHERE sudah_direview_l1 = true AND l1_context_status = false) AS l1_dismissed,
                    count(*) FILTER (WHERE sudah_direview_l1 = false OR sudah_direview_l1 IS NULL) AS l1_belum,
                    count(*) FILTER (WHERE sudah_direview_l2 = true) AS l2_reviewed,
                    count(*) FILTER (WHERE sudah_direview_l2 = true AND l2_context_status = true) AS l2_confirmed,
                    count(*) FILTER (WHERE dihitung_untuk_laporan_pelanggaran = true) AS post_event_eligible
                FROM bcsid.mv_dms_alert
                WHERE waktu_deteksi >= ? AND waktu_deteksi < ?
            ";

            try {
                $row = DB::connection($connection)->selectOne($sql, [$start, $end]);
            } catch (Throwable $e) {
                report($e);

                return $empty;
            }

            return [
                'total' => (int) ($row->total ?? 0),
                'l1_reviewed' => (int) ($row->l1_reviewed ?? 0),
                'l1_confirmed' => (int) ($row->l1_confirmed ?? 0),
                'l1_dismissed' => (int) ($row->l1_dismissed ?? 0),
                'l1_belum' => (int) ($row->l1_belum ?? 0),
                'l2_reviewed' => (int) ($row->l2_reviewed ?? 0),
                'l2_confirmed' => (int) ($row->l2_confirmed ?? 0),
                'post_event_eligible' => (int) ($row->post_event_eligible ?? 0),
            ];
        });
    }

    /**
     * @return list<array{unit:string, site:string, total:int, confirmed:int}>
     */
    public function alertsByUnit(string $start, string $end, int $limit = 20): array
    {
        return $this->remember('by_unit.'.$limit, $start, $end, function () use ($start, $end, $limit): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT
                    COALESCE(NULLIF(TRIM(unit::text), ''), '-') AS unit,
                    COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site,
                    count(*) AS total,
                    count(*) FILTER (WHERE sudah_direview_l1 = true AND l1_context_status = true) AS confirmed
                FROM bcsid.mv_dms_alert
                WHERE waktu_deteksi >= ? AND waktu_deteksi < ?
                GROUP BY 1, 2
                ORDER BY total DESC
                LIMIT ?
            ";

            try {
                $rows = DB::connection($connection)->select($sql, [$start, $end, $limit]);
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static fn ($r): array => [
                'unit' => (string) $r->unit,
                'site' => (string) $r->site,
                'total' => (int) $r->total,
                'confirmed' => (int) $r->confirmed,
            ], $rows);
        });
    }

    /**
     * @return list<array{kode_sid:string, nama:string, total:int, confirmed:int}>
     */
    public function alertsByOperator(string $start, string $end, int $limit = 20): array
    {
        return $this->remember('by_operator.'.$limit, $start, $end, function () use ($start, $end, $limit): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT
                    UPPER(TRIM(kode_sid)) AS kode_sid,
                    COALESCE(NULLIF(TRIM(nama_driver_dms::text), ''), '-') AS nama,
                    count(*) AS total,
                    count(*) FILTER (WHERE sudah_direview_l1 = true AND l1_context_status = true) AS confirmed
                FROM bcsid.mv_dms_alert
                WHERE waktu_deteksi >= ? AND waktu_deteksi < ?
                  AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
                GROUP BY 1, 2
                ORDER BY total DESC
                LIMIT ?
            ";

            try {
                $rows = DB::connection($connection)->select($sql, [$start, $end, $limit]);
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static fn ($r): array => [
                'kode_sid' => (string) $r->kode_sid,
                'nama' => (string) $r->nama,
                'total' => (int) $r->total,
                'confirmed' => (int) $r->confirmed,
            ], $rows);
        });
    }

    /**
     * Kuadran DIHITUNG (bukan tabel lookup) — volume alert vs tingkat konfirmasi
     * L1, per kategori pelanggaran. Dipakai untuk plot kuadran: kategori dengan
     * volume tinggi + konfirmasi tinggi = prioritas nyata; volume tinggi +
     * konfirmasi rendah = kemungkinan alert kamera yang perlu dikalibrasi ulang.
     *
     * @return list<array{nama_pelanggaran:string, total:int, confirmed:int, confirmation_rate:float}>
     */
    public function categoryQuadrant(string $start, string $end): array
    {
        return $this->remember('quadrant', $start, $end, function () use ($start, $end): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT
                    COALESCE(NULLIF(TRIM(nama_pelanggaran::text), ''), '-') AS nama_pelanggaran,
                    count(*) AS total,
                    count(*) FILTER (WHERE sudah_direview_l1 = true AND l1_context_status = true) AS confirmed,
                    count(*) FILTER (WHERE sudah_direview_l1 = true) AS reviewed
                FROM bcsid.mv_dms_alert
                WHERE waktu_deteksi >= ? AND waktu_deteksi < ?
                GROUP BY 1
                ORDER BY total DESC
                LIMIT " . self::CATEGORY_LIMIT . "
            ";

            try {
                $rows = DB::connection($connection)->select($sql, [$start, $end]);
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static function ($r): array {
                $reviewed = (int) $r->reviewed;
                $confirmed = (int) $r->confirmed;

                return [
                    'nama_pelanggaran' => (string) $r->nama_pelanggaran,
                    'total' => (int) $r->total,
                    'confirmed' => $confirmed,
                    'confirmation_rate' => $reviewed > 0 ? round($confirmed / $reviewed * 100, 1) : 0.0,
                ];
            }, $rows);
        });
    }

    /**
     * @return list<string> UPPER(kode_sid) yang checkin RFID lolos dalam window
     */
    public function distinctCheckinSids(string $start, string $end): array
    {
        return $this->remember('rfid_sids', $start, $end, function () use ($start, $end): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT DISTINCT UPPER(TRIM(kode_sid)) AS sid
                FROM bcsid.mv_checkinout_rfid
                WHERE tanggal_checkinout >= ? AND tanggal_checkinout < ?
                  AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
                  AND UPPER(TRIM(jenis_checkinout::text)) IN ('IN','CHECKIN','CHECK-IN','CHECK_IN','CHECK IN','MASUK')
                  AND REPLACE(REPLACE(UPPER(TRIM(status_lolos::text)), ' ', ''), '-', '') IN ('PASSED','PASS','LOLOS','YA','YES','1','TRUE','T','Y')
            ";

            try {
                $rows = DB::connection($connection)->select($sql, [$start, $end]);
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static fn ($r): string => (string) $r->sid, $rows);
        });
    }

    /**
     * @return list<string> UPPER(kode_sid) yang punya minimal 1 alert dalam window
     */
    public function distinctAlertSids(string $start, string $end): array
    {
        return $this->remember('alert_sids', $start, $end, function () use ($start, $end): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT DISTINCT UPPER(TRIM(kode_sid)) AS sid
                FROM bcsid.mv_dms_alert
                WHERE waktu_deteksi >= ? AND waktu_deteksi < ?
                  AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
            ";

            try {
                $rows = DB::connection($connection)->select($sql, [$start, $end]);
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static fn ($r): string => (string) $r->sid, $rows);
        });
    }

    /**
     * Post Event — agregat TUNGGAL (bukan chunk/loop) ke bcsid.dms_violation_report_log.
     *
     * @return array{total:int, behazard:int, berecord:int, distinct_sids:list<string>}
     */
    public function postEventSummary(string $start, string $end): array
    {
        return $this->remember('post_event', $start, $end, function () use ($start, $end): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return ['total' => 0, 'behazard' => 0, 'berecord' => 0, 'distinct_sids' => []];
            }

            $totalsSql = "
                SELECT
                    count(*) AS total,
                    count(*) FILTER (WHERE report_type = 'BEHAZARD') AS behazard,
                    count(*) FILTER (WHERE report_type = 'BERECORD') AS berecord
                FROM bcsid.dms_violation_report_log
                WHERE created_at >= ? AND created_at < ?
            ";
            $sidsSql = '
                SELECT DISTINCT UPPER(TRIM(driver_sid)) AS sid
                FROM bcsid.dms_violation_report_log
                WHERE created_at >= ? AND created_at < ?
                  AND driver_sid IS NOT NULL AND TRIM(driver_sid) <> \'\'
            ';

            try {
                $totals = DB::connection($connection)->selectOne($totalsSql, [$start, $end]);
                $sidRows = DB::connection($connection)->select($sidsSql, [$start, $end]);
            } catch (Throwable $e) {
                report($e);

                return ['total' => 0, 'behazard' => 0, 'berecord' => 0, 'distinct_sids' => []];
            }

            return [
                'total' => (int) ($totals->total ?? 0),
                'behazard' => (int) ($totals->behazard ?? 0),
                'berecord' => (int) ($totals->berecord ?? 0),
                'distinct_sids' => array_map(static fn ($r): string => (string) $r->sid, $sidRows),
            ];
        });
    }

    /**
     * Unit yang sedang online — agregat TUNGGAL ke bcsid.dms_vehicle_status_alerts.
     */
    public function unitsOperatingNow(int $withinMinutes = 30): int
    {
        $cacheKey = 'dms_monitoring:units_operating:'.$withinMinutes;

        return Cache::remember($cacheKey, 60, function () use ($withinMinutes): int {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return 0;
            }

            $sql = "
                SELECT count(DISTINCT vehicle_no) AS total
                FROM bcsid.dms_vehicle_status_alerts
                WHERE last_online_at >= now() - (? || ' minutes')::interval
            ";

            try {
                $row = DB::connection($connection)->selectOne($sql, [$withinMinutes]);
            } catch (Throwable $e) {
                report($e);

                return 0;
            }

            return (int) ($row->total ?? 0);
        });
    }

    /**
     * Rata-rata waktu tanggap (deteksi -> review L1/L2) per site — proksi
     * "performa control room" tanpa identitas reviewer per-orang (raw
     * dms_alert sengaja dihindari, lihat catatan kelas).
     *
     * @return list<array{site:string, total_direview:int, avg_menit_l1:float|null, avg_menit_l2:float|null}>
     */
    public function turnaroundBySite(string $start, string $end): array
    {
        return $this->remember('turnaround_site', $start, $end, function () use ($start, $end): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT
                    COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site,
                    count(*) FILTER (WHERE sudah_direview_l1 = true) AS total_direview,
                    avg(EXTRACT(EPOCH FROM (waktu_review_l1 - waktu_deteksi)) / 60.0) FILTER (WHERE sudah_direview_l1 = true AND waktu_review_l1 IS NOT NULL) AS avg_menit_l1,
                    avg(EXTRACT(EPOCH FROM (waktu_review_l2 - waktu_deteksi)) / 60.0) FILTER (WHERE sudah_direview_l2 = true AND waktu_review_l2 IS NOT NULL) AS avg_menit_l2
                FROM bcsid.mv_dms_alert
                WHERE waktu_deteksi >= ? AND waktu_deteksi < ?
                GROUP BY 1
                ORDER BY total_direview DESC
            ";

            try {
                $rows = DB::connection($connection)->select($sql, [$start, $end]);
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static fn ($r): array => [
                'site' => (string) $r->site,
                'total_direview' => (int) $r->total_direview,
                'avg_menit_l1' => $r->avg_menit_l1 !== null ? round((float) $r->avg_menit_l1, 1) : null,
                'avg_menit_l2' => $r->avg_menit_l2 !== null ? round((float) $r->avg_menit_l2, 1) : null,
            ], $rows);
        });
    }

    /**
     * Alert yang sudah di-dismiss L1 (dianggap bukan pelanggaran nyata) dalam
     * window — kandidat populasi sampling QA false negative. Diacak di SQL
     * (ORDER BY random()) supaya representatif, dibatasi $limit.
     *
     * @param  list<string>  $excludeAlertIds  id_alert yang sudah pernah disampling
     * @return list<array{id_alert:string, kode_sid:string|null, nama_pelanggaran:string|null, unit:string|null, site:string|null, waktu_deteksi:string|null}>
     */
    public function dismissedL1AlertsForSampling(string $start, string $end, int $limit, array $excludeAlertIds = []): array
    {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return [];
        }

        $excludeSql = '';
        $bindings = [$start, $end];
        if ($excludeAlertIds !== []) {
            $placeholders = implode(',', array_fill(0, count($excludeAlertIds), '?'));
            $excludeSql = " AND id_alert NOT IN ({$placeholders})";
            $bindings = array_merge($bindings, $excludeAlertIds);
        }
        $bindings[] = $limit;

        $sql = "
            SELECT id_alert, UPPER(TRIM(kode_sid)) AS kode_sid, nama_pelanggaran, unit, site, waktu_deteksi
            FROM bcsid.mv_dms_alert
            WHERE waktu_deteksi >= ? AND waktu_deteksi < ?
              AND sudah_direview_l1 = true AND l1_context_status = false
              {$excludeSql}
            ORDER BY random()
            LIMIT ?
        ";

        try {
            $rows = DB::connection($connection)->select($sql, $bindings);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return array_map(static fn ($r): array => [
            'id_alert' => (string) $r->id_alert,
            'kode_sid' => $r->kode_sid !== null ? (string) $r->kode_sid : null,
            'nama_pelanggaran' => $r->nama_pelanggaran !== null ? trim((string) $r->nama_pelanggaran) : null,
            'unit' => $r->unit !== null ? trim((string) $r->unit) : null,
            'site' => $r->site !== null ? trim((string) $r->site) : null,
            'waktu_deteksi' => $r->waktu_deteksi !== null ? (string) $r->waktu_deteksi : null,
        ], $rows);
    }

    /**
     * Jumlah alert dismissed L1 dalam window — dipakai sebagai populasi (N) rumus Slovin.
     */
    public function dismissedL1Count(string $start, string $end): int
    {
        if (! $this->isUp()) {
            return 0;
        }

        $cacheKey = 'dms_monitoring:dismissed_l1_count:'.md5($start.'|'.$end);

        return Cache::remember($cacheKey, 1800, function () use ($start, $end): int {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return 0;
            }

            $sql = '
                SELECT count(*) AS total FROM bcsid.mv_dms_alert
                WHERE waktu_deteksi >= ? AND waktu_deteksi < ?
                  AND sudah_direview_l1 = true AND l1_context_status = false
            ';

            try {
                $row = DB::connection($connection)->selectOne($sql, [$start, $end]);
            } catch (Throwable $e) {
                report($e);

                return 0;
            }

            return (int) ($row->total ?? 0);
        });
    }

    private function remember(string $key, string $start, string $end, \Closure $callback): array
    {
        if (! $this->isUp()) {
            return [];
        }

        $cacheKey = 'dms_monitoring:'.$key.':'.md5($start.'|'.$end);

        /** @var array<mixed> */
        return Cache::remember($cacheKey, 1800, $callback);
    }
}
