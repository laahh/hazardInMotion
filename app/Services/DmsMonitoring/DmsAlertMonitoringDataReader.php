<?php

declare(strict_types=1);

namespace App\Services\DmsMonitoring;

use App\Services\Dms\DmsDashboardDataSource;
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
 *   - bcsid.dms_violation_report_log, bcsid.dms_vehicle_status_alerts,
 *     bcsid.dms_vehicle_statuses, bcsid.dms_vehicle: FOREIGN TABLE (FDW).
 *     Dicek langsung via EXPLAIN: cost-nya FLAT/tidak reflektif
 *     (mirip masalah yang pernah bikin 504 di clean_data_fatigue_check), tapi
 *     query AGREGAT SEDERHANA (bukan loop per-SID/chunk) terbukti tetap
 *     cepat saat dieksekusi nyata. Karena itu SEMUA akses ke tabel FDW ini
 *     WAJIB tetap agregat tunggal — JANGAN PERNAH di-chunk atau di-loop per SID.
 *   - Unit beroperasi diambil dari bcsid.dms_vehicle_statuses (speed_gps > 0),
 *     di-join ke bcsid.dms_vehicle untuk site/perusahaan.
 *   - bcsid.dms_alert (raw, bukan mv_dms_alert) dan bcsid.dms_spv_schedule
 *     SENGAJA TIDAK dipakai — dms_alert diketahui COUNT(*) timeout tanpa
 *     filter dan tidak ada jalan aman untuk resolusi identitas reviewer;
 *     dms_spv_schedule butuh join yang belum tervalidasi. Panel yang
 *     harusnya butuh ini (mis. performa per-reviewer) diganti dengan agregat
 *     per site dari mv_dms_alert saja.
 */
final class DmsAlertMonitoringDataReader implements DmsDashboardDataSource
{
    /** Batas lead time intervensi real time (detik) — 5 menit. */
    private const LEAD_TIME_THRESHOLD_SECONDS = 300;

    /** Kecepatan GPS minimum (exclusive) untuk dianggap unit bergerak/beroperasi. */
    private const OPERATING_MIN_SPEED = 0.0;

    /** Cap kecepatan GPS outlier (km/h). */
    private const OPERATING_MAX_SPEED = 80.0;

    /** Versi cache/logika unit beroperasi — bump saat sumber atau fallback berubah. */
    private const OPERATING_LOGIC_VERSION = 'v5';

    /** Batas jumlah kategori pelanggaran yang ditampilkan di kuadran. */
    private const CATEGORY_LIMIT = 500;

    /** Batas waktu query RFID (ms) supaya halaman tidak 504. */
    private const RFID_STATEMENT_TIMEOUT_MS = 8000;

    /** Batas waktu query FDW unit/post-event (ms). FDW sering tidak hormati timeout — first paint harus menghindari query ini. */
    private const FDW_STATEMENT_TIMEOUT_MS = 4000;

    private ?string $scopeSite = null;

    private ?string $scopePerusahaan = null;

    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $connectionSource,
    ) {}

    public function isUp(): bool
    {
        return $this->connectionSource->isUp();
    }

    public function applyScope(?string $site, ?string $perusahaan): void
    {
        $this->scopeSite = $this->sanitizeFilter($site);
        $this->scopePerusahaan = $this->sanitizeFilter($perusahaan);
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
                WHERE {$this->alertDateWhere()}
            ";

            $this->applyStatementTimeout($connection, self::RFID_STATEMENT_TIMEOUT_MS);
            try {
                $row = DB::connection($connection)->selectOne($sql, $this->alertDateBindings($start, $end));
            } catch (Throwable $e) {
                report($e);

                return $empty;
            } finally {
                $this->clearStatementTimeout($connection);
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
                WHERE {$this->alertDateWhere()}
                GROUP BY 1, 2
                ORDER BY total DESC
                LIMIT ?
            ";

            try {
                $rows = DB::connection($connection)->select($sql, array_merge($this->alertDateBindings($start, $end), [$limit]));
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
                WHERE {$this->alertDateWhere()}
                  AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
                GROUP BY 1, 2
                ORDER BY total DESC
                LIMIT ?
            ";

            try {
                $rows = DB::connection($connection)->select($sql, array_merge($this->alertDateBindings($start, $end), [$limit]));
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
                WHERE {$this->alertDateWhere()}
                GROUP BY 1
                ORDER BY total DESC
                LIMIT " . self::CATEGORY_LIMIT . "
            ";

            try {
                $rows = DB::connection($connection)->select($sql, $this->alertDateBindings($start, $end));
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
     * @return list<string> UPPER(kode_sid) yang ada di RFID pada window
     */
    public function distinctCheckinSids(string $start, string $end): array
    {
        return $this->remember('rfid_sids_v2', $start, $end, function () use ($start, $end): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT DISTINCT UPPER(TRIM(kode_sid)) AS sid
                FROM bcsid.mv_checkinout_rfid
                WHERE tanggal_checkinout >= ? AND tanggal_checkinout < ?
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
     * Jumlah SID unik yang check-in RFID dalam window, diiris jabatan_fungsional
     * yang namanya mengandung "Operator".
     *
     * Join ke snapshot karyawan aktif (crontable_bep_vw_m_karyawan_aktif, ~17 MB)
     * — BUKAN seq-scan bcsid.m_karyawan (~6 GB) dan BUKAN dump ribuan SID ke PHP.
     * RFID memakai index tanggal + kode_sid. Tidak memfilter jenis_checkinout
     * maupun status_lolos.
     */
    public function countOperatorCheckinsInRange(string $start, string $end): int
    {
        return $this->rememberScalarInt('rfid_fungsional_checkin_v2', $start, $end, function () use ($start, $end): int {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return 0;
            }

            $sql = "
                WITH ops AS MATERIALIZED (
                    SELECT kode_sid
                    FROM bcsid.crontable_bep_vw_m_karyawan_aktif
                    WHERE kode_sid IS NOT NULL
                      AND TRIM(kode_sid) <> ''
                      AND UPPER(TRIM(COALESCE(jabatan_fungsional, ''))) LIKE '%OPERATOR%'
                      AND UPPER(TRIM(COALESCE(jabatan_fungsional, ''))) <> 'VISITOR'
                ),
                rfid AS MATERIALIZED (
                    SELECT DISTINCT kode_sid
                    FROM bcsid.mv_checkinout_rfid
                    WHERE tanggal_checkinout >= ? AND tanggal_checkinout < ?
                      AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
            ";
            $bindings = [$start, $end];
            if ($this->scopePerusahaan !== null) {
                $sql .= ' AND TRIM(perusahaan::text) = ?';
                $bindings[] = $this->scopePerusahaan;
            }
            $sql .= '
                )
                SELECT count(*) AS total
                FROM rfid r
                INNER JOIN ops o ON o.kode_sid = r.kode_sid
            ';

            $this->applyStatementTimeout($connection, self::RFID_STATEMENT_TIMEOUT_MS);
            try {
                $row = DB::connection($connection)->selectOne($sql, $bindings);
            } catch (Throwable $e) {
                report($e);

                return 0;
            } finally {
                $this->clearStatementTimeout($connection);
            }

            return (int) ($row->total ?? 0);
        });
    }

    /**
     * Jumlah SID unik yang ada di RFID, dibatasi daftar SID operator.
     *
     * Tidak memfilter jenis_checkinout maupun status_lolos — semua baris RFID
     * dalam window dihitung.
     *
     * COUNT DISTINCT di Postgres (bukan dump semua SID RFID ke PHP)
     * plus statement_timeout, supaya load dashboard tidak 504.
     *
     * @param  list<string>  $operatorSids
     */
    public function countDistinctOperatorCheckins(string $start, string $end, array $operatorSids): int
    {
        $normalized = [];
        foreach ($operatorSids as $sid) {
            $upper = strtoupper(trim((string) $sid));
            if ($upper !== '') {
                $normalized[$upper] = true;
            }
        }
        if ($normalized === []) {
            return 0;
        }

        $sidList = array_keys($normalized);
        $sidHash = md5(implode(',', $sidList));
        $cacheKey = 'dms_monitoring:rfid_operator_count_v4:'.md5($start.'|'.$end.'|'.$this->scopeCacheSuffix().'|'.$sidHash);

        $cached = Cache::get($cacheKey);
        if (is_int($cached)) {
            return $cached;
        }

        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return 0;
        }

        $total = 0;
        $this->applyStatementTimeout($connection, self::RFID_STATEMENT_TIMEOUT_MS);
        try {
            $sql = "
                SELECT count(DISTINCT UPPER(TRIM(kode_sid))) AS total
                FROM bcsid.mv_checkinout_rfid
                WHERE tanggal_checkinout >= ? AND tanggal_checkinout < ?
                  AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
                  AND UPPER(TRIM(kode_sid)) = ANY(?::text[])
            ";
            $bindings = [$start, $end, $this->toPgTextArray($sidList)];
            if ($this->scopePerusahaan !== null) {
                $sql .= ' AND TRIM(perusahaan::text) = ?';
                $bindings[] = $this->scopePerusahaan;
            }

            $row = DB::connection($connection)->selectOne($sql, $bindings);
            $total = (int) ($row->total ?? 0);
        } catch (Throwable $e) {
            report($e);

            return 0;
        } finally {
            $this->clearStatementTimeout($connection);
        }

        Cache::put($cacheKey, $total, 1800);

        return $total;
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
                WHERE {$this->alertDateWhere()}
                  AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
            ";

            try {
                $rows = DB::connection($connection)->select($sql, $this->alertDateBindings($start, $end));
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static fn ($r): string => (string) $r->sid, $rows);
        });
    }

    public function countDistinctCheckinSids(string $start, string $end): int
    {
        return $this->rememberScalarInt('rfid_sids_count_v4', $start, $end, function () use ($start, $end): int {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return 0;
            }

            $sql = "
                SELECT count(DISTINCT UPPER(TRIM(kode_sid))) AS total
                FROM bcsid.mv_checkinout_rfid
                WHERE tanggal_checkinout >= ? AND tanggal_checkinout < ?
                  AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
            ";
            $bindings = [$start, $end];
            if ($this->scopePerusahaan !== null) {
                $sql .= ' AND TRIM(perusahaan::text) = ?';
                $bindings[] = $this->scopePerusahaan;
            }

            $this->applyStatementTimeout($connection, self::RFID_STATEMENT_TIMEOUT_MS);
            try {
                $row = DB::connection($connection)->selectOne($sql, $bindings);
            } catch (Throwable $e) {
                report($e);

                return 0;
            } finally {
                $this->clearStatementTimeout($connection);
            }

            return (int) ($row->total ?? 0);
        });
    }

    public function countDistinctAlertSids(string $start, string $end): int
    {
        return $this->rememberScalarInt('alert_sids_count', $start, $end, function () use ($start, $end): int {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return 0;
            }

            $sql = "
                SELECT count(DISTINCT UPPER(TRIM(kode_sid))) AS total
                FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()}
                  AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
            ";

            $this->applyStatementTimeout($connection, self::RFID_STATEMENT_TIMEOUT_MS);
            try {
                $row = DB::connection($connection)->selectOne($sql, $this->alertDateBindings($start, $end));
            } catch (Throwable $e) {
                report($e);

                return 0;
            } finally {
                $this->clearStatementTimeout($connection);
            }

            return (int) ($row->total ?? 0);
        });
    }

    public function countPostEventDistinctSids(string $start, string $end): int
    {
        return $this->rememberScalarInt('post_event_sids_count', $start, $end, function () use ($start, $end): int {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return 0;
            }

            $sql = "
                SELECT count(DISTINCT UPPER(TRIM(driver_sid))) AS total
                FROM bcsid.dms_violation_report_log
                WHERE created_at >= ? AND created_at < ?
                  AND driver_sid IS NOT NULL AND TRIM(driver_sid) <> ''
            ";

            $this->applyStatementTimeout($connection, self::FDW_STATEMENT_TIMEOUT_MS);
            try {
                $row = DB::connection($connection)->selectOne($sql, [$start, $end]);
            } catch (Throwable $e) {
                report($e);

                return 0;
            } finally {
                $this->clearStatementTimeout($connection);
            }

            return (int) ($row->total ?? 0);
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
     * Unit beroperasi dalam rentang tanggal — unit unik dengan speed_gps > 0
     * di bcsid.dms_vehicle_statuses (proxy pergerakan GPS).
     */
    public function unitsOperatingInRange(string $start, string $end): int
    {
        $cacheKey = 'dms_monitoring:units_operating_range:'.self::OPERATING_LOGIC_VERSION.':'.md5($start.'|'.$end);

        return Cache::remember($cacheKey, 300, function () use ($start, $end): int {
            return $this->resolveOperatingUnitCount($start, $end);
        });
    }

    /**
     * Unit beroperasi dalam N menit terakhir.
     * Utama: speed_gps > 0 di dms_vehicle_statuses; fallback: last_online_at di dms_vehicle_status_alerts.
     */
    public function unitsOperatingNow(int $withinMinutes = 30): int
    {
        $cacheKey = 'dms_monitoring:units_operating:'.self::OPERATING_LOGIC_VERSION.':'.$withinMinutes;

        return Cache::remember($cacheKey, 60, function () use ($withinMinutes): int {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return 0;
            }

            try {
                $row = DB::connection($connection)->selectOne(
                    'SELECT now() - (? || \' minutes\')::interval AS start_at, now() AS end_at',
                    [$withinMinutes],
                );
                $startAt = (string) ($row->start_at ?? '');
                $endAt = (string) ($row->end_at ?? '');
            } catch (Throwable $e) {
                report($e);

                return 0;
            }

            if ($startAt === '' || $endAt === '') {
                return 0;
            }

            return $this->resolveOperatingUnitCount($startAt, $endAt);
        });
    }

    /**
     * Unit beroperasi per hari — satu query agregat (hindari N+1 untuk sparkline).
     *
     * @return list<array{hari:string, units:int}>
     */
    public function dailyOperatingUnitSeries(string $start, string $end): array
    {
        return $this->remember('dashboard.daily_units.v6', $start, $end, function () use ($start, $end): array {
            if (! $this->shouldSkipGpsStatusesQuery($start) && $this->hasGpsMovingUnitsInRange($start, $end)) {
                return $this->dailyMovingUnitsFromStatuses($start, $end);
            }

            return $this->dailyOnlineUnitsFromAlerts($start, $end);
        });
    }

    /**
     * Ringkasan unit beroperasi vs alert untuk modal overall KPI.
     *
     * @return array{
     *     units_operating:int,
     *     units_without_alert:int,
     *     units_with_alert:int,
     *     total_alerts:int,
     *     ratio_per_unit:float,
     *     top_units:list<array{unit:string, site:string, perusahaan:string, alert_count:int}>
     * }
     */
    public function overallOperatingUnitsSummary(string $start, string $end): array
    {
        $empty = [
            'units_operating' => 0,
            'units_without_alert' => 0,
            'units_with_alert' => 0,
            'total_alerts' => 0,
            'ratio_per_unit' => 0.0,
            'top_units' => [],
        ];

        return $this->rememberScalar('overall.units_summary.v1', $start, $end, function () use ($start, $end, $empty): array {
            if (! $this->isUp()) {
                return $empty;
            }

            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return $empty;
            }

            $useGps = ! $this->shouldSkipGpsStatusesQuery($start) && $this->hasGpsMovingUnitsInRange($start, $end);
            $scopeWhereVehicle = $this->vehicleScopeWhere('dv.site', 'dv.company_owner');
            $scopeWhereAlert = $this->alertDateWhere();

            if ($useGps) {
                $unitKey = $this->vehicleRegisterKeyExpr('vs.vehicle_no');
                $operatingSql = "
                    SELECT DISTINCT
                        {$unitKey} AS unit,
                        COALESCE(NULLIF(TRIM(dv.site::text), ''), '-') AS site,
                        COALESCE(NULLIF(TRIM(dv.company_owner::text), ''), '-') AS perusahaan
                    FROM bcsid.dms_vehicle_statuses vs
                    INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = {$unitKey}
                    WHERE vs.created_at >= ? AND vs.created_at < ?
                      AND {$this->movingVehicleSpeedWhere('vs')}
                      {$scopeWhereVehicle}
                ";
                $operatingBindings = array_merge([$start, $end], $this->vehicleScopeBindings());
            } else {
                $operatingSql = "
                    SELECT DISTINCT
                        TRIM(dv.no_register) AS unit,
                        COALESCE(NULLIF(TRIM(dv.site::text), ''), '-') AS site,
                        COALESCE(NULLIF(TRIM(dv.company_owner::text), ''), '-') AS perusahaan
                    FROM bcsid.dms_vehicle_status_alerts vsa
                    INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = TRIM(vsa.vehicle_no)
                    WHERE vsa.last_online_at >= ? AND vsa.last_online_at < ?
                      AND vsa.vehicle_no IS NOT NULL AND TRIM(vsa.vehicle_no) <> ''
                      {$scopeWhereVehicle}
                ";
                $operatingBindings = array_merge([$start, $end], $this->vehicleScopeBindings());
            }

            $sql = "
                WITH operating AS ({$operatingSql}),
                unit_alerts AS (
                    SELECT
                        TRIM(unit::text) AS unit,
                        COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site,
                        count(*) AS alert_count
                    FROM bcsid.mv_dms_alert
                    WHERE {$scopeWhereAlert}
                      AND unit IS NOT NULL AND TRIM(unit::text) <> ''
                    GROUP BY 1, 2
                ),
                joined AS (
                    SELECT
                        o.unit,
                        o.site,
                        o.perusahaan,
                        COALESCE(ua.alert_count, 0) AS alert_count
                    FROM operating o
                    LEFT JOIN unit_alerts ua
                        ON TRIM(o.unit) = TRIM(ua.unit) AND TRIM(o.site) = TRIM(ua.site)
                )
                SELECT
                    count(*) AS units_operating,
                    count(*) FILTER (WHERE alert_count = 0) AS units_without_alert,
                    count(*) FILTER (WHERE alert_count > 0) AS units_with_alert,
                    COALESCE(sum(alert_count), 0) AS total_alerts
                FROM joined
            ";

            try {
                $summaryRow = DB::connection($connection)->selectOne(
                    $sql,
                    array_merge($operatingBindings, $this->alertDateBindings($start, $end)),
                );
                $topRows = DB::connection($connection)->select(
                    "
                    WITH operating AS ({$operatingSql}),
                    unit_alerts AS (
                        SELECT TRIM(unit::text) AS unit, COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site, count(*) AS alert_count
                        FROM bcsid.mv_dms_alert
                        WHERE {$scopeWhereAlert} AND unit IS NOT NULL AND TRIM(unit::text) <> ''
                        GROUP BY 1, 2
                    ),
                    joined AS (
                        SELECT o.unit, o.site, o.perusahaan, COALESCE(ua.alert_count, 0) AS alert_count
                        FROM operating o
                        LEFT JOIN unit_alerts ua ON TRIM(o.unit) = TRIM(ua.unit) AND TRIM(o.site) = TRIM(ua.site)
                    )
                    SELECT unit, site, perusahaan, alert_count FROM joined
                    ORDER BY alert_count DESC, unit ASC
                    LIMIT 5
                    ",
                    array_merge($operatingBindings, $this->alertDateBindings($start, $end)),
                );
            } catch (Throwable $e) {
                report($e);

                return $empty;
            }

            $unitsOperating = (int) ($summaryRow->units_operating ?? 0);
            $totalAlerts = (int) ($summaryRow->total_alerts ?? 0);

            return [
                'units_operating' => $unitsOperating,
                'units_without_alert' => (int) ($summaryRow->units_without_alert ?? 0),
                'units_with_alert' => (int) ($summaryRow->units_with_alert ?? 0),
                'total_alerts' => $totalAlerts,
                'ratio_per_unit' => $unitsOperating > 0 ? round($totalAlerts / $unitsOperating, 2) : 0.0,
                'top_units' => array_map(static fn ($r): array => [
                    'unit' => (string) $r->unit,
                    'site' => (string) $r->site,
                    'perusahaan' => (string) $r->perusahaan,
                    'alert_count' => (int) $r->alert_count,
                ], $topRows),
            ];
        }, $empty);
    }

    /**
     * Tabel unit beroperasi (dengan/tanpa alert) untuk modal overall.
     *
     * @return array{
     *     total:int,
     *     rows:list<array{
     *         unit:string,
     *         site:string,
     *         perusahaan:string,
     *         alert_count:int,
     *         has_alert:bool
     *     }>
     * }
     */
    public function overallOperatingUnitsTable(
        string $start,
        string $end,
        int $page,
        int $perPage,
        string $status = 'with_alert',
    ): array {
        $empty = ['total' => 0, 'rows' => []];
        if (! $this->isUp()) {
            return $empty;
        }

        $cacheKey = 'dms_monitoring:overall.units_table.v1:'.md5(
            $start.'|'.$end.'|'.$page.'|'.$perPage.'|'.$status.'|'.$this->scopeCacheSuffix()
        );

        /** @var array{total:int, rows:list<array<string, mixed>>} */
        return Cache::remember($cacheKey, 300, function () use ($start, $end, $page, $perPage, $status, $empty): array {
            return $this->queryOverallOperatingUnitsTable($start, $end, $page, $perPage, $status, $empty);
        });
    }

    /**
     * @param  array{total:int, rows:list<array<string, mixed>>}  $empty
     * @return array{total:int, rows:list<array<string, mixed>>}
     */
    private function queryOverallOperatingUnitsTable(
        string $start,
        string $end,
        int $page,
        int $perPage,
        string $status,
        array $empty,
    ): array {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return $empty;
        }

        $useGps = ! $this->shouldSkipGpsStatusesQuery($start) && $this->hasGpsMovingUnitsInRange($start, $end);
        $scopeWhereVehicle = $this->vehicleScopeWhere('dv.site', 'dv.company_owner');
        $scopeWhereAlert = $this->alertDateWhere();
        $offset = max(0, ($page - 1) * $perPage);

        if ($useGps) {
            $unitKey = $this->vehicleRegisterKeyExpr('vs.vehicle_no');
            $operatingSql = "
                SELECT {$unitKey} AS unit,
                    COALESCE(NULLIF(TRIM(dv.site::text), ''), '-') AS site,
                    COALESCE(NULLIF(TRIM(dv.company_owner::text), ''), '-') AS perusahaan,
                    'GPS bergerak' AS evidence_source,
                    max(vs.created_at) AS evidence_at,
                    max(vs.speed_gps) AS evidence_value
                FROM bcsid.dms_vehicle_statuses vs
                INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = {$unitKey}
                WHERE vs.created_at >= ? AND vs.created_at < ?
                  AND {$this->movingVehicleSpeedWhere('vs')} {$scopeWhereVehicle}
                GROUP BY 1, 2, 3, 4
            ";
            $operatingBindings = array_merge([$start, $end], $this->vehicleScopeBindings());
        } else {
            $operatingSql = "
                SELECT TRIM(dv.no_register) AS unit,
                    COALESCE(NULLIF(TRIM(dv.site::text), ''), '-') AS site,
                    COALESCE(NULLIF(TRIM(dv.company_owner::text), ''), '-') AS perusahaan,
                    'Online DMS' AS evidence_source,
                    max(vsa.last_online_at) AS evidence_at,
                    null::numeric AS evidence_value
                FROM bcsid.dms_vehicle_status_alerts vsa
                INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = TRIM(vsa.vehicle_no)
                WHERE vsa.last_online_at >= ? AND vsa.last_online_at < ?
                  AND vsa.vehicle_no IS NOT NULL AND TRIM(vsa.vehicle_no) <> ''
                  {$scopeWhereVehicle}
                GROUP BY 1, 2, 3, 4, 6
            ";
            $operatingBindings = array_merge([$start, $end], $this->vehicleScopeBindings());
        }

        $baseBindings = array_merge($operatingBindings, $this->alertDateBindings($start, $end));
        $statusWhere = $status === 'without_alert'
            ? ' WHERE alert_count = 0'
            : ' WHERE alert_count > 0';

        $countSql = "
            WITH operating AS ({$operatingSql}),
            unit_alerts AS (
                SELECT TRIM(unit::text) AS unit, COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site, count(*) AS alert_count
                FROM bcsid.mv_dms_alert WHERE {$scopeWhereAlert} AND unit IS NOT NULL AND TRIM(unit::text) <> ''
                GROUP BY 1, 2
            ),
            joined AS (
                SELECT o.unit, o.site, o.perusahaan, o.evidence_source, o.evidence_at, o.evidence_value, COALESCE(ua.alert_count, 0) AS alert_count
                FROM operating o
                LEFT JOIN unit_alerts ua ON TRIM(o.unit) = TRIM(ua.unit) AND TRIM(o.site) = TRIM(ua.site)
            )
            SELECT count(*) AS total FROM joined{$statusWhere}
        ";

        $dataSql = "
            WITH operating AS ({$operatingSql}),
            unit_alerts AS (
                SELECT TRIM(unit::text) AS unit, COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site, count(*) AS alert_count
                FROM bcsid.mv_dms_alert WHERE {$scopeWhereAlert} AND unit IS NOT NULL AND TRIM(unit::text) <> ''
                GROUP BY 1, 2
            ),
            joined AS (
                SELECT o.unit, o.site, o.perusahaan, o.evidence_source, o.evidence_at, o.evidence_value, COALESCE(ua.alert_count, 0) AS alert_count
                FROM operating o
                LEFT JOIN unit_alerts ua ON TRIM(o.unit) = TRIM(ua.unit) AND TRIM(o.site) = TRIM(ua.site)
            )
            SELECT j.unit, j.site, j.perusahaan, j.evidence_source, j.evidence_at, j.evidence_value, j.alert_count
            FROM joined j{$statusWhere}
            ORDER BY j.alert_count DESC, j.unit ASC
            LIMIT ? OFFSET ?
        ";

        try {
            $countRow = DB::connection($connection)->selectOne($countSql, $baseBindings);
            $rows = DB::connection($connection)->select(
                $dataSql,
                array_merge($baseBindings, [$perPage, $offset]),
            );
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }

        $mappedRows = array_map(static fn ($r): array => [
            'unit' => (string) $r->unit,
            'site' => (string) $r->site,
            'perusahaan' => (string) $r->perusahaan,
            'evidence_source' => (string) ($r->evidence_source ?? '-'),
            'evidence_at' => ($r->evidence_at instanceof \DateTimeInterface)
                ? $r->evidence_at->format('Y-m-d H:i:s')
                : ($r->evidence_at !== null ? (string) $r->evidence_at : null),
            'evidence_value' => $r->evidence_value !== null ? (float) $r->evidence_value : null,
            'alert_count' => (int) $r->alert_count,
            'has_alert' => (int) $r->alert_count > 0,
        ], $rows);

        return [
            'total' => (int) ($countRow->total ?? 0),
            'rows' => $mappedRows,
        ];
    }

    /**
     * @return list<array{name:string,total:int}>
     */
    public function operatingUnitAlertDetails(string $start, string $end, string $unit, string $site, string $perusahaan): array
    {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return [];
        }

        $bindings = $this->alertDateBindings($start, $end);
        $bindings[] = $unit;
        $bindings[] = $site;
        $bindings[] = $perusahaan;

        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(nama_pelanggaran::text), ''), '-') AS alert_name,
                count(*) AS total
            FROM bcsid.mv_dms_alert
            WHERE {$this->alertDateWhere()}
              AND unit IS NOT NULL AND TRIM(unit::text) <> ''
              AND TRIM(unit::text) = ?
              AND COALESCE(NULLIF(TRIM(site::text), ''), '-') = ?
              AND COALESCE(NULLIF(TRIM(perusahaan::text), ''), '-') = ?
            GROUP BY 1
            ORDER BY total DESC, alert_name ASC
        ";

        try {
            $detailRows = DB::connection($connection)->select($sql, $bindings);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $mapped = [];
        foreach ($detailRows as $detail) {
            if (count($mapped) >= 8) {
                continue;
            }
            $mapped[] = [
                'name' => (string) $detail->alert_name,
                'total' => (int) ($detail->total ?? 0),
            ];
        }

        return $mapped;
    }

    /**
     * Tren harian alert untuk unit teratas (multi-series chart).
     *
     * @param  list<string>  $units
     * @return array{labels:list<string>, series:list<array{name:string, data:list<int>}>}
     */
    public function dailyAlertsForTopUnits(string $start, string $end, array $units): array
    {
        $empty = ['labels' => [], 'series' => []];
        if ($units === [] || ! $this->isUp()) {
            return $empty;
        }

        $cacheKey = 'dms_monitoring:overall.top_units_daily:'.md5($start.'|'.$end.'|'.implode(',', $units).'|'.$this->scopeCacheSuffix());

        return Cache::remember($cacheKey, 1800, function () use ($start, $end, $units, $empty): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return $empty;
            }

            $placeholders = implode(',', array_fill(0, count($units), '?'));
            $sql = "
                SELECT date(waktu_deteksi) AS hari, TRIM(unit::text) AS unit, count(*) AS total
                FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()}
                  AND TRIM(unit::text) IN ({$placeholders})
                GROUP BY 1, 2
                ORDER BY 1
            ";

            try {
                $rows = DB::connection($connection)->select(
                    $sql,
                    array_merge($this->alertDateBindings($start, $end), $units),
                );
            } catch (Throwable $e) {
                report($e);

                return $empty;
            }

            $labels = [];
            $indexed = [];
            foreach ($rows as $row) {
                $hari = $row->hari;
                if ($hari instanceof \DateTimeInterface) {
                    $hari = $hari->format('Y-m-d');
                }
                $hari = (string) $hari;
                $labels[$hari] = true;
                $indexed[(string) $row->unit][$hari] = (int) $row->total;
            }

            $sortedLabels = array_keys($labels);
            sort($sortedLabels);

            $series = [];
            foreach ($units as $unit) {
                $data = [];
                foreach ($sortedLabels as $label) {
                    $data[] = (int) ($indexed[$unit][$label] ?? 0);
                }
                $series[] = ['name' => $unit, 'data' => $data];
            }

            return ['labels' => $sortedLabels, 'series' => $series];
        });
    }

    /**
     * Tren harian alert untuk operator teratas (multi-series chart).
     *
     * @param  list<string>  $sids
     * @return array{labels:list<string>, series:list<array{name:string, data:list<int>}>}
     */
    public function dailyAlertsForTopOperatorSids(string $start, string $end, array $sids): array
    {
        $empty = ['labels' => [], 'series' => []];
        if ($sids === [] || ! $this->isUp()) {
            return $empty;
        }

        $cacheKey = 'dms_monitoring:overall.top_operator_daily:'.md5($start.'|'.$end.'|'.implode(',', $sids).'|'.$this->scopeCacheSuffix());

        return Cache::remember($cacheKey, 1800, function () use ($start, $end, $sids, $empty): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return $empty;
            }

            $placeholders = implode(',', array_fill(0, count($sids), '?'));
            $sql = "
                SELECT date(waktu_deteksi) AS hari, UPPER(TRIM(kode_sid)) AS sid, count(*) AS total
                FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()}
                  AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
                  AND UPPER(TRIM(kode_sid)) IN ({$placeholders})
                GROUP BY 1, 2
                ORDER BY 1
            ";

            try {
                $rows = DB::connection($connection)->select(
                    $sql,
                    array_merge($this->alertDateBindings($start, $end), $sids),
                );
            } catch (Throwable $e) {
                report($e);

                return $empty;
            }

            $labels = [];
            $indexed = [];
            foreach ($rows as $row) {
                $hari = $row->hari;
                if ($hari instanceof \DateTimeInterface) {
                    $hari = $hari->format('Y-m-d');
                }
                $hari = (string) $hari;
                $labels[$hari] = true;
                $indexed[(string) $row->sid][$hari] = (int) $row->total;
            }

            $sortedLabels = array_keys($labels);
            sort($sortedLabels);

            $series = [];
            foreach ($sids as $sid) {
                $data = [];
                foreach ($sortedLabels as $label) {
                    $data[] = (int) ($indexed[$sid][$label] ?? 0);
                }
                $series[] = ['name' => $sid, 'data' => $data];
            }

            return ['labels' => $sortedLabels, 'series' => $series];
        });
    }

    /**
     * @return list<array{name:string,total:int}>
     */
    public function operatorAlertDetails(string $start, string $end, string $sid): array
    {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return [];
        }

        $bindings = $this->alertDateBindings($start, $end);
        $bindings[] = strtoupper(trim($sid));

        $sql = "
            SELECT COALESCE(NULLIF(TRIM(nama_pelanggaran::text), ''), '-') AS alert_name, count(*) AS total
            FROM bcsid.mv_dms_alert
            WHERE {$this->alertDateWhere()}
              AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
              AND UPPER(TRIM(kode_sid)) = ?
            GROUP BY 1
            ORDER BY total DESC, alert_name ASC
        ";

        try {
            $rows = DB::connection($connection)->select($sql, $bindings);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (count($out) >= 8) {
                break;
            }
            $out[] = [
                'name' => (string) $row->alert_name,
                'total' => (int) ($row->total ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Agregat performa control room per perusahaan × site.
     *
     * @return list<array{
     *     perusahaan:string,
     *     site:string,
     *     total_alert:int,
     *     alert_intervened:int,
     *     total_unit:int,
     *     unit_intervened:int,
     *     alert_under_5min:int
     * }>
     */
    public function controlRoomPerformanceRows(string $start, string $end): array
    {
        return $this->remember('control_room_matrix.v3', $start, $end, function () use ($start, $end): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT
                    COALESCE(NULLIF(TRIM(perusahaan::text), ''), '-') AS perusahaan,
                    COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site,
                    count(*) AS total_alert,
                    count(*) FILTER (WHERE sudah_direview_l1 = true) AS alert_intervened,
                    count(*) FILTER (
                        WHERE sudah_direview_l1 = true
                          AND waktu_review_l1 IS NOT NULL
                          AND waktu_deteksi IS NOT NULL
                          AND EXTRACT(EPOCH FROM (waktu_review_l1 - waktu_deteksi)) <= ?
                    ) AS alert_under_5min
                FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()}
                GROUP BY 1, 2
                ORDER BY 1, 2
            ";

            try {
                $rows = DB::connection($connection)->select(
                    $sql,
                    array_merge([self::LEAD_TIME_THRESHOLD_SECONDS], $this->alertDateBindings($start, $end)),
                );
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            $operatingUnits = $this->operatingUnitCountMapBySiteCompany($start, $end);
            $intervenedUnits = $this->intervenedOperatingUnitCountMapBySiteCompany($start, $end);

            return array_map(static function ($r) use ($operatingUnits, $intervenedUnits): array {
                $key = (string) $r->perusahaan.'|'.(string) $r->site;

                return [
                    'perusahaan' => (string) $r->perusahaan,
                    'site' => (string) $r->site,
                    'total_alert' => (int) $r->total_alert,
                    'alert_intervened' => (int) $r->alert_intervened,
                    'total_unit' => (int) ($operatingUnits[$key] ?? 0),
                    'unit_intervened' => (int) ($intervenedUnits[$key] ?? 0),
                    'alert_under_5min' => (int) $r->alert_under_5min,
                ];
            }, $rows);
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
                WHERE {$this->alertDateWhere()}
                GROUP BY 1
                ORDER BY total_direview DESC
            ";

            try {
                $rows = DB::connection($connection)->select($sql, $this->alertDateBindings($start, $end));
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
        $bindings = $this->alertDateBindings($start, $end);
        if ($excludeAlertIds !== []) {
            $placeholders = implode(',', array_fill(0, count($excludeAlertIds), '?'));
            $excludeSql = " AND id_alert NOT IN ({$placeholders})";
            $bindings = array_merge($bindings, $excludeAlertIds);
        }
        $bindings[] = $limit;

        $sql = "
            SELECT id_alert, UPPER(TRIM(kode_sid)) AS kode_sid, nama_pelanggaran, unit, site, waktu_deteksi
            FROM bcsid.mv_dms_alert
            WHERE {$this->alertDateWhere()}
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
     * Tren harian alert (total / confirmed / dismissed / pending / operator unik).
     *
     * @return list<array{hari:string, total:int, confirmed:int, dismissed:int, pending:int, operators:int}>
     */
    public function dailyAlertSeries(string $start, string $end): array
    {
        return $this->remember('dashboard.daily_series.v2', $start, $end, function () use ($start, $end): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT
                    date(waktu_deteksi) AS hari,
                    count(*) AS total,
                    count(*) FILTER (WHERE sudah_direview_l1 = true AND l1_context_status = true) AS confirmed,
                    count(*) FILTER (WHERE sudah_direview_l1 = true AND l1_context_status = false) AS dismissed,
                    count(*) FILTER (WHERE sudah_direview_l1 = false OR sudah_direview_l1 IS NULL) AS pending
                FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()}
                GROUP BY 1
                ORDER BY 1
            ";

            $this->applyStatementTimeout($connection, self::RFID_STATEMENT_TIMEOUT_MS);
            try {
                $rows = DB::connection($connection)->select($sql, $this->alertDateBindings($start, $end));
            } catch (Throwable $e) {
                report($e);

                return [];
            } finally {
                $this->clearStatementTimeout($connection);
            }

            return array_map(static function ($r): array {
                $hari = $r->hari;
                if ($hari instanceof \DateTimeInterface) {
                    $hari = $hari->format('Y-m-d');
                }

                return [
                    'hari' => (string) $hari,
                    'total' => (int) $r->total,
                    'confirmed' => (int) $r->confirmed,
                    'dismissed' => (int) $r->dismissed,
                    'pending' => (int) $r->pending,
                    'operators' => (int) $r->total,
                ];
            }, $rows);
        });
    }

    /**
     * Agregat alert per site untuk kartu "Status Site".
     *
     * @return list<array{site:string, total:int, confirmed:int}>
     */
    public function alertsBySite(string $start, string $end, int $limit = 8): array
    {
        return $this->remember('dashboard.by_site.'.$limit, $start, $end, function () use ($start, $end, $limit): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT
                    COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site,
                    count(*) AS total,
                    count(*) FILTER (WHERE sudah_direview_l1 = true AND l1_context_status = true) AS confirmed
                FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()}
                GROUP BY 1
                ORDER BY total DESC
                LIMIT ?
            ";

            try {
                $rows = DB::connection($connection)->select($sql, array_merge($this->alertDateBindings($start, $end), [$limit]));
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static fn ($r): array => [
                'site' => (string) $r->site,
                'total' => (int) $r->total,
                'confirmed' => (int) $r->confirmed,
            ], $rows);
        });
    }

    /**
     * Alert terbaru dalam window — untuk tabel dashboard. WAJIB LIMIT.
     *
     * @return list<array{id_alert:string, kode_sid:string, nama:string, nama_pelanggaran:string, unit:string, site:string, waktu_deteksi:string|null, sudah_direview_l1:bool, l1_confirmed:bool|null}>
     */
    public function recentAlerts(string $start, string $end, int $limit = 10, bool $confirmedOnly = false): array
    {
        $cacheSuffix = $confirmedOnly ? 'confirmed' : 'all';

        return $this->remember('dashboard.recent.'.$cacheSuffix.'.'.$limit, $start, $end, function () use ($start, $end, $limit, $confirmedOnly): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $confirmedSql = $confirmedOnly
                ? ' AND sudah_direview_l1 = true AND l1_context_status = true'
                : '';

            $sql = "
                SELECT
                    id_alert,
                    UPPER(TRIM(kode_sid)) AS kode_sid,
                    COALESCE(NULLIF(TRIM(nama_driver_dms::text), ''), '-') AS nama,
                    COALESCE(NULLIF(TRIM(nama_pelanggaran::text), ''), '-') AS nama_pelanggaran,
                    COALESCE(NULLIF(TRIM(unit::text), ''), '-') AS unit,
                    COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site,
                    waktu_deteksi,
                    sudah_direview_l1,
                    l1_context_status
                FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()}
                  {$confirmedSql}
                ORDER BY waktu_deteksi DESC
                LIMIT ?
            ";

            try {
                $rows = DB::connection($connection)->select($sql, array_merge($this->alertDateBindings($start, $end), [$limit]));
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static function ($r): array {
                $asBool = static function (mixed $value): bool {
                    if (is_bool($value)) {
                        return $value;
                    }
                    if ($value === null) {
                        return false;
                    }

                    return in_array(strtolower(trim((string) $value)), ['1', 't', 'true', 'y', 'yes'], true);
                };

                $reviewed = $asBool($r->sudah_direview_l1 ?? false);
                $confirmedRaw = $r->l1_context_status;
                $waktu = $r->waktu_deteksi;
                if ($waktu instanceof \DateTimeInterface) {
                    $waktu = $waktu->format('Y-m-d H:i:s');
                }

                return [
                    'id_alert' => (string) $r->id_alert,
                    'kode_sid' => $r->kode_sid !== null ? (string) $r->kode_sid : '-',
                    'nama' => (string) $r->nama,
                    'nama_pelanggaran' => (string) $r->nama_pelanggaran,
                    'unit' => (string) $r->unit,
                    'site' => (string) $r->site,
                    'waktu_deteksi' => $waktu !== null && $waktu !== '' ? (string) $waktu : null,
                    'sudah_direview_l1' => $reviewed,
                    'l1_confirmed' => $confirmedRaw === null ? null : $asBool($confirmedRaw),
                ];
            }, $rows);
        });
    }

    /**
     * Total alert per site (drill-down KPI level sites).
     *
     * @return list<array{site:string, value:int}>
     */
    public function alertCountBySite(string $start, string $end, int $limit = 200): array
    {
        return $this->remember('kpi.alert_by_site.'.$limit, $start, $end, function () use ($start, $end, $limit): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT
                    COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site,
                    count(*) AS value
                FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()}
                GROUP BY 1
                ORDER BY value DESC
                LIMIT ?
            ";

            try {
                $rows = DB::connection($connection)->select($sql, array_merge($this->alertDateBindings($start, $end), [$limit]));
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static fn ($r): array => [
                'site' => (string) $r->site,
                'value' => (int) $r->value,
            ], $rows);
        });
    }

    /**
     * Total alert per perusahaan dalam satu site (drill-down level companies).
     *
     * @return list<array{perusahaan:string, value:int}>
     */
    public function alertCountBySiteAndCompany(string $start, string $end, string $site, int $limit = 200): array
    {
        $siteKey = md5($site);

        return $this->remember('kpi.alert_by_company.'.$siteKey.'.'.$limit, $start, $end, function () use ($start, $end, $site, $limit): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT
                    COALESCE(NULLIF(TRIM(perusahaan::text), ''), '-') AS perusahaan,
                    count(*) AS value
                FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()}
                  AND TRIM(site::text) = ?
                GROUP BY 1
                ORDER BY value DESC
                LIMIT ?
            ";

            try {
                $rows = DB::connection($connection)->select(
                    $sql,
                    array_merge($this->alertDateBindings($start, $end), [$site, $limit]),
                );
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static fn ($r): array => [
                'perusahaan' => (string) $r->perusahaan,
                'value' => (int) $r->value,
            ], $rows);
        });
    }

    /**
     * Unit unik yang punya alert per site (untuk perbandingan vs unit beroperasi).
     *
     * @return list<array{site:string, value:int}>
     */
    public function distinctAlertUnitsBySite(string $start, string $end, int $limit = 200): array
    {
        return $this->remember('kpi.alert_units_by_site.'.$limit, $start, $end, function () use ($start, $end, $limit): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $sql = "
                SELECT
                    COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site,
                    count(DISTINCT NULLIF(TRIM(unit::text), '')) AS value
                FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()}
                  AND unit IS NOT NULL AND TRIM(unit::text) <> ''
                GROUP BY 1
                ORDER BY value DESC
                LIMIT ?
            ";

            try {
                $rows = DB::connection($connection)->select($sql, array_merge($this->alertDateBindings($start, $end), [$limit]));
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static fn ($r): array => [
                'site' => (string) $r->site,
                'value' => (int) $r->value,
            ], $rows);
        });
    }

    /**
     * Unit beroperasi (bergerak GPS) per site — join dms_vehicle_statuses × dms_vehicle.
     *
     * @return list<array{site:string, value:int}>
     */
    public function distinctUnitsBySite(string $start, string $end, int $limit = 200): array
    {
        return $this->remember('kpi.units_by_site.v3.'.$limit, $start, $end, function () use ($start, $end, $limit): array {
            if (! $this->hasGpsMovingUnitsInRange($start, $end)) {
                return $this->distinctOnlineUnitsBySiteFromAlerts($start, $end, $limit);
            }

            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $unitKey = $this->vehicleRegisterKeyExpr('vs.vehicle_no');
            $scopeWhere = $this->vehicleScopeWhere('dv.site', 'dv.company_owner');

            $sql = "
                SELECT
                    COALESCE(NULLIF(TRIM(dv.site::text), ''), '-') AS site,
                    count(DISTINCT {$unitKey}) AS value
                FROM bcsid.dms_vehicle_statuses vs
                INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = {$unitKey}
                WHERE vs.created_at >= ? AND vs.created_at < ?
                  AND {$this->movingVehicleSpeedWhere('vs')}
                  {$scopeWhere}
                GROUP BY 1
                ORDER BY value DESC
                LIMIT ?
            ";

            try {
                $rows = DB::connection($connection)->select(
                    $sql,
                    array_merge([$start, $end], $this->vehicleScopeBindings(), [$limit]),
                );
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static fn ($r): array => [
                'site' => (string) $r->site,
                'value' => (int) $r->value,
            ], $rows);
        });
    }

    /**
     * Unit beroperasi (bergerak GPS) per perusahaan dalam site.
     *
     * @return list<array{perusahaan:string, value:int}>
     */
    public function distinctUnitsBySiteAndCompany(string $start, string $end, string $site, int $limit = 200): array
    {
        $siteKey = md5($site);

        return $this->remember('kpi.units_by_company.v3.'.$siteKey.'.'.$limit, $start, $end, function () use ($start, $end, $site, $limit): array {
            if (! $this->hasGpsMovingUnitsInRange($start, $end)) {
                return $this->distinctOnlineUnitsByCompanyFromAlerts($start, $end, $site, $limit);
            }

            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $unitKey = $this->vehicleRegisterKeyExpr('vs.vehicle_no');
            $scopeWhere = $this->vehicleScopeWhere('dv.site', 'dv.company_owner');

            $sql = "
                SELECT
                    COALESCE(NULLIF(TRIM(dv.company_owner::text), ''), '-') AS perusahaan,
                    count(DISTINCT {$unitKey}) AS value
                FROM bcsid.dms_vehicle_statuses vs
                INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = {$unitKey}
                WHERE vs.created_at >= ? AND vs.created_at < ?
                  AND {$this->movingVehicleSpeedWhere('vs')}
                  AND TRIM(dv.site::text) = ?
                  {$scopeWhere}
                GROUP BY 1
                ORDER BY value DESC
                LIMIT ?
            ";

            try {
                $rows = DB::connection($connection)->select(
                    $sql,
                    array_merge([$start, $end, $site], $this->vehicleScopeBindings(), [$limit]),
                );
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            return array_map(static fn ($r): array => [
                'perusahaan' => (string) $r->perusahaan,
                'value' => (int) $r->value,
            ], $rows);
        });
    }

    /**
     * Total alert per operator (SID) — untuk rasio per orang.
     *
     * @return array<string, int> UPPER(kode_sid) => count
     */
    public function alertCountByOperatorSid(string $start, string $end, ?string $site = null, ?string $perusahaan = null): array
    {
        if (! $this->isUp()) {
            return [];
        }

        $extra = ($site ?? '').'|'.($perusahaan ?? '');
        $cacheKey = 'dms_monitoring:kpi.alert_by_sid:'.md5($start.'|'.$end.'|'.$this->scopeCacheSuffix().'|'.$extra);

        /** @var array<string, int> */
        return Cache::remember($cacheKey, 1800, function () use ($start, $end, $site, $perusahaan): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $extraWhere = '';
            $extraBindings = [];
            if ($site !== null && $site !== '') {
                $extraWhere .= ' AND TRIM(site::text) = ?';
                $extraBindings[] = $site;
            }
            if ($perusahaan !== null && $perusahaan !== '') {
                $extraWhere .= ' AND TRIM(perusahaan::text) = ?';
                $extraBindings[] = $perusahaan;
            }

            $sql = "
                SELECT UPPER(TRIM(kode_sid)) AS sid, count(*) AS total
                FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()}
                  AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
                  {$extraWhere}
                GROUP BY 1
            ";

            try {
                $rows = DB::connection($connection)->select(
                    $sql,
                    array_merge($this->alertDateBindings($start, $end), $extraBindings),
                );
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            $map = [];
            foreach ($rows as $row) {
                $map[(string) $row->sid] = (int) ($row->total ?? 0);
            }

            return $map;
        });
    }

    /**
     * Total alert per operator — dibatasi daftar SID cohort (chunked IN).
     * Lebih ringan daripada alertCountByOperatorSid saat hanya butuh subset operator.
     *
     * @param  list<string>  $sids
     * @return array<string, int> UPPER(kode_sid) => count
     */
    public function alertCountForOperatorSids(
        string $start,
        string $end,
        array $sids,
        ?string $site = null,
        ?string $perusahaan = null,
    ): array {
        if (! $this->isUp()) {
            return [];
        }

        $normalized = [];
        foreach ($sids as $sid) {
            $upper = strtoupper(trim((string) $sid));
            if ($upper !== '') {
                $normalized[$upper] = true;
            }
        }
        if ($normalized === []) {
            return [];
        }

        $sidList = array_keys($normalized);
        sort($sidList);
        $sidHash = md5(implode(',', $sidList));
        $extra = ($site ?? '').'|'.($perusahaan ?? '');
        $cacheKey = 'dms_monitoring:alert_by_sid_scoped:'.md5($start.'|'.$end.'|'.$this->scopeCacheSuffix().'|'.$extra.'|'.$sidHash);

        /** @var array<string, int> */
        return Cache::remember($cacheKey, 1800, function () use ($start, $end, $sidList, $site, $perusahaan): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $extraWhere = '';
            $extraBindings = [];
            if ($site !== null && $site !== '') {
                $extraWhere .= ' AND TRIM(site::text) = ?';
                $extraBindings[] = $site;
            }
            if ($perusahaan !== null && $perusahaan !== '') {
                $extraWhere .= ' AND TRIM(perusahaan::text) = ?';
                $extraBindings[] = $perusahaan;
            }

            $map = [];
            foreach (array_chunk($sidList, 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $sql = "
                    SELECT UPPER(TRIM(kode_sid)) AS sid, count(*) AS total
                    FROM bcsid.mv_dms_alert
                    WHERE {$this->alertDateWhere()}
                      AND kode_sid IS NOT NULL AND TRIM(kode_sid) <> ''
                      AND UPPER(TRIM(kode_sid)) IN ({$placeholders})
                      {$extraWhere}
                    GROUP BY 1
                ";

                try {
                    $rows = DB::connection($connection)->select(
                        $sql,
                        array_merge($this->alertDateBindings($start, $end), $chunk, $extraBindings),
                    );
                } catch (Throwable $e) {
                    report($e);

                    continue;
                }

                foreach ($rows as $row) {
                    $map[(string) $row->sid] = (int) ($row->total ?? 0);
                }
            }

            return $map;
        });
    }

    /**
     * Daftar alert paginated untuk drill-down level rows.
     *
     * @return array{total:int, rows:list<array{id_alert:string, kode_sid:string, nama:string, nama_pelanggaran:string, unit:string, site:string, perusahaan:string, waktu_deteksi:string|null, status_label:string}>}
     */
    public function alertDetailRows(
        string $start,
        string $end,
        ?string $site,
        ?string $perusahaan,
        int $page,
        int $perPage,
    ): array {
        $empty = ['total' => 0, 'rows' => []];
        if (! $this->isUp()) {
            return $empty;
        }

        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return $empty;
        }

        $extraWhere = '';
        $extraBindings = [];
        if ($site !== null && $site !== '') {
            $extraWhere .= ' AND TRIM(site::text) = ?';
            $extraBindings[] = $site;
        }
        if ($perusahaan !== null && $perusahaan !== '') {
            $extraWhere .= ' AND TRIM(perusahaan::text) = ?';
            $extraBindings[] = $perusahaan;
        }

        $baseBindings = array_merge($this->alertDateBindings($start, $end), $extraBindings);
        $offset = max(0, ($page - 1) * $perPage);

        $countSql = "
            SELECT count(*) AS total FROM bcsid.mv_dms_alert
            WHERE {$this->alertDateWhere()} {$extraWhere}
        ";

        $dataSql = "
            SELECT
                id_alert,
                UPPER(TRIM(kode_sid)) AS kode_sid,
                COALESCE(NULLIF(TRIM(nama_driver_dms::text), ''), '-') AS nama,
                COALESCE(NULLIF(TRIM(nama_pelanggaran::text), ''), '-') AS nama_pelanggaran,
                COALESCE(NULLIF(TRIM(unit::text), ''), '-') AS unit,
                COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site,
                COALESCE(NULLIF(TRIM(perusahaan::text), ''), '-') AS perusahaan,
                waktu_deteksi,
                sudah_direview_l1,
                l1_context_status
            FROM bcsid.mv_dms_alert
            WHERE {$this->alertDateWhere()} {$extraWhere}
            ORDER BY waktu_deteksi DESC
            LIMIT ? OFFSET ?
        ";

        try {
            $countRow = DB::connection($connection)->selectOne($countSql, $baseBindings);
            $rows = DB::connection($connection)->select($dataSql, array_merge($baseBindings, [$perPage, $offset]));
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }

        return [
            'total' => (int) ($countRow->total ?? 0),
            'rows' => array_map(static function ($r): array {
                $reviewed = filter_var($r->sudah_direview_l1 ?? false, FILTER_VALIDATE_BOOLEAN);
                $confirmed = $r->l1_context_status;
                $statusLabel = ! $reviewed ? 'Belum L1' : (filter_var($confirmed, FILTER_VALIDATE_BOOLEAN) ? 'Confirmed' : 'Dismissed');
                $waktu = $r->waktu_deteksi;
                if ($waktu instanceof \DateTimeInterface) {
                    $waktu = $waktu->format('Y-m-d H:i:s');
                }

                return [
                    'id_alert' => (string) $r->id_alert,
                    'kode_sid' => (string) ($r->kode_sid ?? '-'),
                    'nama' => (string) $r->nama,
                    'nama_pelanggaran' => (string) $r->nama_pelanggaran,
                    'unit' => (string) $r->unit,
                    'site' => (string) $r->site,
                    'perusahaan' => (string) $r->perusahaan,
                    'waktu_deteksi' => $waktu !== null && $waktu !== '' ? (string) $waktu : null,
                    'status_label' => $statusLabel,
                ];
            }, $rows),
        ];
    }

    /**
     * Daftar unit beroperasi (GPS bergerak) — drill-down level rows.
     *
     * @return array{total:int, rows:list<array{unit:string, site:string, perusahaan:string, value:int}>}
     */
    public function operatingUnitDetailRows(
        string $start,
        string $end,
        ?string $site,
        ?string $perusahaan,
        int $page,
        int $perPage,
    ): array {
        $empty = ['total' => 0, 'rows' => []];
        if (! $this->isUp()) {
            return $empty;
        }

        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return $empty;
        }

        $useGpsSnapshot = $this->hasGpsMovingUnitsInRange($start, $end);

        $unitKey = $this->vehicleRegisterKeyExpr('vs.vehicle_no');
        $extraWhere = '';
        $extraBindings = [];
        if ($site !== null && $site !== '') {
            $extraWhere .= ' AND TRIM(dv.site::text) = ?';
            $extraBindings[] = $site;
        }
        if ($perusahaan !== null && $perusahaan !== '') {
            $extraWhere .= ' AND TRIM(dv.company_owner::text) = ?';
            $extraBindings[] = $perusahaan;
        }

        $baseBindings = array_merge([$start, $end], $extraBindings);
        $offset = max(0, ($page - 1) * $perPage);

        if (! $useGpsSnapshot) {
            $countSql = "
                SELECT count(*) AS total FROM (
                    SELECT 1
                    FROM bcsid.dms_vehicle_status_alerts vsa
                    INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = TRIM(vsa.vehicle_no)
                    WHERE vsa.last_online_at >= ? AND vsa.last_online_at < ?
                      {$extraWhere}
                    GROUP BY TRIM(vsa.vehicle_no), TRIM(dv.site::text), TRIM(dv.company_owner::text)
                ) AS grouped
            ";
            $dataSql = "
                SELECT
                    COALESCE(NULLIF(TRIM(dv.no_register::text), ''), '-') AS unit,
                    COALESCE(NULLIF(TRIM(dv.site::text), ''), '-') AS site,
                    COALESCE(NULLIF(TRIM(dv.company_owner::text), ''), '-') AS perusahaan,
                    1 AS value
                FROM bcsid.dms_vehicle_status_alerts vsa
                INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = TRIM(vsa.vehicle_no)
                WHERE vsa.last_online_at >= ? AND vsa.last_online_at < ?
                  {$extraWhere}
                GROUP BY 1, 2, 3
                ORDER BY unit ASC
                LIMIT ? OFFSET ?
            ";
        } else {
            $countSql = "
                SELECT count(*) AS total FROM (
                    SELECT 1
                    FROM bcsid.dms_vehicle_statuses vs
                    INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = {$unitKey}
                    WHERE vs.created_at >= ? AND vs.created_at < ?
                      AND {$this->movingVehicleSpeedWhere('vs')}
                      {$extraWhere}
                    GROUP BY {$unitKey}, TRIM(dv.site::text), TRIM(dv.company_owner::text)
                ) AS grouped
            ";
            $dataSql = "
                SELECT
                    COALESCE(NULLIF(TRIM(dv.no_register::text), ''), '-') AS unit,
                    COALESCE(NULLIF(TRIM(dv.site::text), ''), '-') AS site,
                    COALESCE(NULLIF(TRIM(dv.company_owner::text), ''), '-') AS perusahaan,
                    1 AS value
                FROM bcsid.dms_vehicle_statuses vs
                INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = {$unitKey}
                WHERE vs.created_at >= ? AND vs.created_at < ?
                  AND {$this->movingVehicleSpeedWhere('vs')}
                  {$extraWhere}
                GROUP BY 1, 2, 3
                ORDER BY unit ASC
                LIMIT ? OFFSET ?
            ";
        }

        try {
            $countRow = DB::connection($connection)->selectOne($countSql, $baseBindings);
            $rows = DB::connection($connection)->select($dataSql, array_merge($baseBindings, [$perPage, $offset]));
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }

        return [
            'total' => (int) ($countRow->total ?? 0),
            'rows' => array_map(static fn ($r): array => [
                'unit' => (string) $r->unit,
                'site' => (string) $r->site,
                'perusahaan' => (string) $r->perusahaan,
                'value' => (int) $r->value,
            ], $rows),
        ];
    }

    /**
     * Daftar unit dengan total alert — drill-down level rows (rasio alert/unit).
     *
     * @return array{total:int, rows:list<array{unit:string, site:string, perusahaan:string, value:int}>}
     */
    public function unitDetailRows(
        string $start,
        string $end,
        ?string $site,
        ?string $perusahaan,
        int $page,
        int $perPage,
    ): array {
        $empty = ['total' => 0, 'rows' => []];
        if (! $this->isUp()) {
            return $empty;
        }

        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return $empty;
        }

        $extraWhere = ' AND unit IS NOT NULL AND TRIM(unit::text) <> \'\'';
        $extraBindings = [];
        if ($site !== null && $site !== '') {
            $extraWhere .= ' AND TRIM(site::text) = ?';
            $extraBindings[] = $site;
        }
        if ($perusahaan !== null && $perusahaan !== '') {
            $extraWhere .= ' AND TRIM(perusahaan::text) = ?';
            $extraBindings[] = $perusahaan;
        }

        $baseBindings = array_merge($this->alertDateBindings($start, $end), $extraBindings);
        $offset = max(0, ($page - 1) * $perPage);

        $countSql = "
            SELECT count(*) AS total FROM (
                SELECT 1 FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()} {$extraWhere}
                GROUP BY TRIM(unit::text), TRIM(site::text), TRIM(perusahaan::text)
            ) AS grouped
        ";

        $dataSql = "
            SELECT
                COALESCE(NULLIF(TRIM(unit::text), ''), '-') AS unit,
                COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site,
                COALESCE(NULLIF(TRIM(perusahaan::text), ''), '-') AS perusahaan,
                count(*) AS value
            FROM bcsid.mv_dms_alert
            WHERE {$this->alertDateWhere()} {$extraWhere}
            GROUP BY 1, 2, 3
            ORDER BY value DESC
            LIMIT ? OFFSET ?
        ";

        try {
            $countRow = DB::connection($connection)->selectOne($countSql, $baseBindings);
            $rows = DB::connection($connection)->select($dataSql, array_merge($baseBindings, [$perPage, $offset]));
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }

        return [
            'total' => (int) ($countRow->total ?? 0),
            'rows' => array_map(static fn ($r): array => [
                'unit' => (string) $r->unit,
                'site' => (string) $r->site,
                'perusahaan' => (string) $r->perusahaan,
                'value' => (int) $r->value,
            ], $rows),
        ];
    }

    /**
     * Jumlah alert per site untuk perhitungan rasio (pasangan dengan check-in per site).
     *
     * @return array<string, int> site => count
     */
    public function alertCountMapBySite(string $start, string $end): array
    {
        $rows = $this->alertCountBySite($start, $end, 500);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['site']] = $row['value'];
        }

        return $map;
    }

    /**
     * Jumlah alert per site+perusahaan dalam satu site.
     *
     * @return array<string, int> perusahaan => count
     */
    public function alertCountMapByCompanyInSite(string $start, string $end, string $site): array
    {
        $rows = $this->alertCountBySiteAndCompany($start, $end, $site, 500);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['perusahaan']] = $row['value'];
        }

        return $map;
    }

    /**
     * Jumlah unit unik per site (map).
     *
     * @return array<string, int>
     */
    public function unitCountMapBySite(string $start, string $end): array
    {
        $rows = $this->distinctUnitsBySite($start, $end, 500);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['site']] = $row['value'];
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    public function unitCountMapByCompanyInSite(string $start, string $end, string $site): array
    {
        $rows = $this->distinctUnitsBySiteAndCompany($start, $end, $site, 500);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['perusahaan']] = $row['value'];
        }

        return $map;
    }

    /**
     * Jumlah alert dismissed L1 dalam window — dipakai sebagai populasi (N) rumus Slovin.
     */
    public function dismissedL1Count(string $start, string $end): int
    {
        if (! $this->isUp()) {
            return 0;
        }

        $cacheKey = 'dms_monitoring:dismissed_l1_count:'.md5($start.'|'.$end.'|'.$this->scopeCacheSuffix());

        return Cache::remember($cacheKey, 1800, function () use ($start, $end): int {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return 0;
            }

            $sql = "
                SELECT count(*) AS total FROM bcsid.mv_dms_alert
                WHERE {$this->alertDateWhere()}
                  AND sudah_direview_l1 = true AND l1_context_status = false
            ";

            try {
                $row = DB::connection($connection)->selectOne($sql, $this->alertDateBindings($start, $end));
            } catch (Throwable $e) {
                report($e);

                return 0;
            }

            return (int) ($row->total ?? 0);
        });
    }

    /**
     * Opsi dropdown Site / Perusahaan dari window tanggal (tidak di-scope
     * oleh filter yang sedang dipilih, supaya user masih bisa ganti opsi).
     *
     * @return array{sites:list<string>, companies:list<string>}
     */
    public function filterOptions(string $start, string $end): array
    {
        $empty = ['sites' => [], 'companies' => []];
        if (! $this->isUp()) {
            return $empty;
        }

        $cacheKey = 'dms_monitoring:filter_options:'.md5($start.'|'.$end);

        /** @var array{sites:list<string>, companies:list<string>} */
        return Cache::remember($cacheKey, 1800, function () use ($start, $end, $empty): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return $empty;
            }

            $siteSql = "
                SELECT DISTINCT TRIM(site::text) AS v
                FROM bcsid.mv_dms_alert
                WHERE waktu_deteksi >= ? AND waktu_deteksi < ?
                  AND site IS NOT NULL AND TRIM(site::text) <> '' AND TRIM(site::text) <> '-'
                ORDER BY 1
                LIMIT 200
            ";
            $companySql = "
                SELECT DISTINCT TRIM(perusahaan::text) AS v
                FROM bcsid.mv_dms_alert
                WHERE waktu_deteksi >= ? AND waktu_deteksi < ?
                  AND perusahaan IS NOT NULL AND TRIM(perusahaan::text) <> '' AND TRIM(perusahaan::text) <> '-'
                ORDER BY 1
                LIMIT 200
            ";

            $this->applyStatementTimeout($connection, self::RFID_STATEMENT_TIMEOUT_MS);
            try {
                $siteRows = DB::connection($connection)->select($siteSql, [$start, $end]);
                $companyRows = DB::connection($connection)->select($companySql, [$start, $end]);
            } catch (Throwable $e) {
                report($e);

                return $empty;
            } finally {
                $this->clearStatementTimeout($connection);
            }

            $pick = static function (array $rows): array {
                $out = [];
                foreach ($rows as $row) {
                    $value = trim((string) ($row->v ?? ''));
                    if ($value !== '') {
                        $out[] = $value;
                    }
                }

                return $out;
            };

            return [
                'sites' => $pick($siteRows),
                'companies' => $pick($companyRows),
            ];
        });
    }

    private function sanitizeFilter(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = mb_substr(trim($value), 0, 80);

        return $trimmed === '' ? null : $trimmed;
    }

    private function alertDateWhere(): string
    {
        $sql = 'waktu_deteksi >= ? AND waktu_deteksi < ?';
        if ($this->scopeSite !== null) {
            $sql .= ' AND TRIM(site::text) = ?';
        }
        if ($this->scopePerusahaan !== null) {
            $sql .= ' AND TRIM(perusahaan::text) = ?';
        }

        return $sql;
    }

    /**
     * Ekspresi normalisasi vehicle_no → no_register (match dms_vehicle & mv_dms_alert.unit).
     */
    private function vehicleRegisterKeyExpr(string $vehicleNoColumn): string
    {
        return "CASE WHEN POSITION('/' IN {$vehicleNoColumn}) > 0 "
            ."THEN TRIM(split_part({$vehicleNoColumn}, '/', 1)) "
            ."ELSE TRIM({$vehicleNoColumn}) END";
    }

    private function movingVehicleSpeedWhere(string $alias = 'vs'): string
    {
        return "{$alias}.speed_gps > ".self::OPERATING_MIN_SPEED
            ." AND {$alias}.speed_gps <= ".self::OPERATING_MAX_SPEED;
    }

    private function vehicleScopeWhere(string $siteColumn, string $companyColumn): string
    {
        $sql = '';
        if ($this->scopeSite !== null) {
            $sql .= " AND TRIM({$siteColumn}::text) = ?";
        }
        if ($this->scopePerusahaan !== null) {
            $sql .= " AND TRIM({$companyColumn}::text) = ?";
        }

        return $sql;
    }

    /**
     * @return list<string>
     */
    private function vehicleScopeBindings(): array
    {
        $bindings = [];
        if ($this->scopeSite !== null) {
            $bindings[] = $this->scopeSite;
        }
        if ($this->scopePerusahaan !== null) {
            $bindings[] = $this->scopePerusahaan;
        }

        return $bindings;
    }

    /**
     * Hitung unit beroperasi: GPS bergerak jika ada data; fallback ke last_online_at alerts.
     */
    private function resolveOperatingUnitCount(string $start, string $end): int
    {
        if ($this->shouldSkipGpsStatusesQuery($start)) {
            return $this->countOnlineUnitsFromAlerts($start, $end);
        }

        if ($this->hasGpsMovingUnitsInRange($start, $end)) {
            return $this->countMovingUnitsFromStatuses($start, $end);
        }

        return $this->countOnlineUnitsFromAlerts($start, $end);
    }

    /**
     * Lewati scan FDW dms_vehicle_statuses (~30M baris) bila window di luar cakupan GPS.
     */
    private function shouldSkipGpsStatusesQuery(string $rangeStart): bool
    {
        $through = config('dms_monitoring.gps_statuses_through');
        if (! is_string($through) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $through) !== 1) {
            return false;
        }

        return substr($rangeStart, 0, 10) >= $through;
    }

    /**
     * Apakah window ini punya unit bergerak (speed_gps > 0) di dms_vehicle_statuses.
     */
    private function hasGpsMovingUnitsInRange(string $start, string $end): bool
    {
        if ($this->shouldSkipGpsStatusesQuery($start)) {
            return false;
        }

        $cacheKey = 'dms_monitoring:vs_moving:'.self::OPERATING_LOGIC_VERSION.':'.md5($start.'|'.$end);

        return Cache::remember($cacheKey, 300, function () use ($start, $end): bool {
            return $this->countMovingUnitsFromStatuses($start, $end) > 0;
        });
    }

    private function countMovingUnitsFromStatuses(string $start, string $end): int
    {
        if ($this->shouldSkipGpsStatusesQuery($start)) {
            return 0;
        }

        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return 0;
        }

        $unitKey = $this->vehicleRegisterKeyExpr('vs.vehicle_no');
        $sql = "
            SELECT count(DISTINCT {$unitKey}) AS total
            FROM bcsid.dms_vehicle_statuses vs
            WHERE vs.created_at >= ? AND vs.created_at < ?
              AND {$this->movingVehicleSpeedWhere('vs')}
        ";

        try {
            $row = DB::connection($connection)->selectOne($sql, [$start, $end]);
        } catch (Throwable $e) {
            report($e);

            return 0;
        }

        return (int) ($row->total ?? 0);
    }

    private function countOnlineUnitsFromAlerts(string $start, string $end): int
    {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return 0;
        }

        $hasScope = $this->scopeSite !== null || $this->scopePerusahaan !== null;
        if ($hasScope) {
            $scopeWhere = $this->vehicleScopeWhere('dv.site', 'dv.company_owner');
            $sql = "
                SELECT count(DISTINCT TRIM(vsa.vehicle_no)) AS total
                FROM bcsid.dms_vehicle_status_alerts vsa
                INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = TRIM(vsa.vehicle_no)
                WHERE vsa.last_online_at >= ? AND vsa.last_online_at < ?
                  AND vsa.vehicle_no IS NOT NULL AND TRIM(vsa.vehicle_no) <> ''
                  {$scopeWhere}
            ";
            $bindings = array_merge([$start, $end], $this->vehicleScopeBindings());
        } else {
            $sql = "
                SELECT count(DISTINCT TRIM(vehicle_no)) AS total
                FROM bcsid.dms_vehicle_status_alerts
                WHERE last_online_at >= ? AND last_online_at < ?
                  AND vehicle_no IS NOT NULL AND TRIM(vehicle_no) <> ''
            ";
            $bindings = [$start, $end];
        }

        $this->applyStatementTimeout($connection, self::FDW_STATEMENT_TIMEOUT_MS);
        try {
            $row = DB::connection($connection)->selectOne($sql, $bindings);
        } catch (Throwable $e) {
            report($e);

            return 0;
        } finally {
            $this->clearStatementTimeout($connection);
        }

        return (int) ($row->total ?? 0);
    }

    /**
     * @return list<array{hari:string, units:int}>
     */
    private function dailyOnlineUnitsFromAlerts(string $start, string $end): array
    {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return [];
        }

        $scopeWhere = $this->vehicleScopeWhere('dv.site', 'dv.company_owner');
        $sql = "
            SELECT
                date(vsa.last_online_at) AS hari,
                count(DISTINCT TRIM(vsa.vehicle_no)) AS units
            FROM bcsid.dms_vehicle_status_alerts vsa
            INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = TRIM(vsa.vehicle_no)
            WHERE vsa.last_online_at >= ? AND vsa.last_online_at < ?
              AND vsa.vehicle_no IS NOT NULL AND TRIM(vsa.vehicle_no) <> ''
              {$scopeWhere}
            GROUP BY 1
            ORDER BY 1
        ";

        try {
            $rows = DB::connection($connection)->select(
                $sql,
                array_merge([$start, $end], $this->vehicleScopeBindings()),
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return $this->mapDailyUnitRows($rows);
    }

    /**
     * @return list<array{hari:string, units:int}>
     */
    private function dailyMovingUnitsFromStatuses(string $start, string $end): array
    {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return [];
        }

        $unitKey = $this->vehicleRegisterKeyExpr('vs.vehicle_no');
        $scopeWhere = $this->vehicleScopeWhere('dv.site', 'dv.company_owner');
        $sql = "
            SELECT
                date(vs.created_at) AS hari,
                count(DISTINCT {$unitKey}) AS units
            FROM bcsid.dms_vehicle_statuses vs
            INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = {$unitKey}
            WHERE vs.created_at >= ? AND vs.created_at < ?
              AND {$this->movingVehicleSpeedWhere('vs')}
              {$scopeWhere}
            GROUP BY 1
            ORDER BY 1
        ";

        try {
            $rows = DB::connection($connection)->select(
                $sql,
                array_merge([$start, $end], $this->vehicleScopeBindings()),
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return $this->mapDailyUnitRows($rows);
    }

    /**
     * @param  list<object>  $rows
     * @return list<array{hari:string, units:int}>
     */
    private function mapDailyUnitRows(array $rows): array
    {
        return array_map(static function ($r): array {
            $hari = $r->hari;
            if ($hari instanceof \DateTimeInterface) {
                $hari = $hari->format('Y-m-d');
            }

            return [
                'hari' => (string) $hari,
                'units' => (int) ($r->units ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return list<array{site:string, value:int}>
     */
    private function distinctOnlineUnitsBySiteFromAlerts(string $start, string $end, int $limit): array
    {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return [];
        }

        $scopeWhere = $this->vehicleScopeWhere('dv.site', 'dv.company_owner');
        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(dv.site::text), ''), '-') AS site,
                count(DISTINCT TRIM(vsa.vehicle_no)) AS value
            FROM bcsid.dms_vehicle_status_alerts vsa
            INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = TRIM(vsa.vehicle_no)
            WHERE vsa.last_online_at >= ? AND vsa.last_online_at < ?
              AND vsa.vehicle_no IS NOT NULL AND TRIM(vsa.vehicle_no) <> ''
              {$scopeWhere}
            GROUP BY 1
            ORDER BY value DESC
            LIMIT ?
        ";

        try {
            $rows = DB::connection($connection)->select(
                $sql,
                array_merge([$start, $end], $this->vehicleScopeBindings(), [$limit]),
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return array_map(static fn ($r): array => [
            'site' => (string) $r->site,
            'value' => (int) $r->value,
        ], $rows);
    }

    /**
     * @return list<array{perusahaan:string, value:int}>
     */
    private function distinctOnlineUnitsByCompanyFromAlerts(string $start, string $end, string $site, int $limit): array
    {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return [];
        }

        $scopeWhere = $this->vehicleScopeWhere('dv.site', 'dv.company_owner');
        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(dv.company_owner::text), ''), '-') AS perusahaan,
                count(DISTINCT TRIM(vsa.vehicle_no)) AS value
            FROM bcsid.dms_vehicle_status_alerts vsa
            INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = TRIM(vsa.vehicle_no)
            WHERE vsa.last_online_at >= ? AND vsa.last_online_at < ?
              AND TRIM(dv.site::text) = ?
              AND vsa.vehicle_no IS NOT NULL AND TRIM(vsa.vehicle_no) <> ''
              {$scopeWhere}
            GROUP BY 1
            ORDER BY value DESC
            LIMIT ?
        ";

        try {
            $rows = DB::connection($connection)->select(
                $sql,
                array_merge([$start, $end, $site], $this->vehicleScopeBindings(), [$limit]),
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return array_map(static fn ($r): array => [
            'perusahaan' => (string) $r->perusahaan,
            'value' => (int) $r->value,
        ], $rows);
    }

    /**
     * @return array<string, int>
     */
    private function onlineUnitCountMapBySiteCompanyFromAlerts(string $start, string $end): array
    {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return [];
        }

        $scopeWhere = $this->vehicleScopeWhere('dv.site', 'dv.company_owner');
        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(dv.company_owner::text), ''), '-') AS perusahaan,
                COALESCE(NULLIF(TRIM(dv.site::text), ''), '-') AS site,
                count(DISTINCT TRIM(vsa.vehicle_no)) AS total_unit
            FROM bcsid.dms_vehicle_status_alerts vsa
            INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = TRIM(vsa.vehicle_no)
            WHERE vsa.last_online_at >= ? AND vsa.last_online_at < ?
              AND vsa.vehicle_no IS NOT NULL AND TRIM(vsa.vehicle_no) <> ''
              {$scopeWhere}
            GROUP BY 1, 2
        ";

        try {
            $rows = DB::connection($connection)->select(
                $sql,
                array_merge([$start, $end], $this->vehicleScopeBindings()),
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->perusahaan.'|'.(string) $row->site] = (int) $row->total_unit;
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function intervenedUnitCountMapBySiteCompanyFromAlerts(string $start, string $end): array
    {
        $connection = $this->connectionSource->connectionName();
        if ($connection === null) {
            return [];
        }

        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(perusahaan::text), ''), '-') AS perusahaan,
                COALESCE(NULLIF(TRIM(site::text), ''), '-') AS site,
                count(DISTINCT TRIM(unit::text)) AS unit_intervened
            FROM bcsid.mv_dms_alert
            WHERE {$this->alertDateWhere()}
              AND sudah_direview_l1 = true
              AND unit IS NOT NULL AND TRIM(unit::text) <> ''
            GROUP BY 1, 2
        ";

        try {
            $rows = DB::connection($connection)->select($sql, $this->alertDateBindings($start, $end));
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->perusahaan.'|'.(string) $row->site] = (int) $row->unit_intervened;
        }

        return $map;
    }

    /**
     * @return array<string, int> "perusahaan|site" => count
     */
    private function operatingUnitCountMapBySiteCompany(string $start, string $end): array
    {
        $cacheKey = 'dms_monitoring:operating_units_map:'.self::OPERATING_LOGIC_VERSION.':'.md5($start.'|'.$end.'|'.$this->scopeCacheSuffix());

        /** @var array<string, int> */
        return Cache::remember($cacheKey, 1800, function () use ($start, $end): array {
            if (! $this->hasGpsMovingUnitsInRange($start, $end)) {
                return $this->onlineUnitCountMapBySiteCompanyFromAlerts($start, $end);
            }

            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $unitKey = $this->vehicleRegisterKeyExpr('vs.vehicle_no');
            $scopeWhere = $this->vehicleScopeWhere('dv.site', 'dv.company_owner');

            $sql = "
                SELECT
                    COALESCE(NULLIF(TRIM(dv.company_owner::text), ''), '-') AS perusahaan,
                    COALESCE(NULLIF(TRIM(dv.site::text), ''), '-') AS site,
                    count(DISTINCT {$unitKey}) AS total_unit
                FROM bcsid.dms_vehicle_statuses vs
                INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = {$unitKey}
                WHERE vs.created_at >= ? AND vs.created_at < ?
                  AND {$this->movingVehicleSpeedWhere('vs')}
                  {$scopeWhere}
                GROUP BY 1, 2
            ";

            try {
                $rows = DB::connection($connection)->select(
                    $sql,
                    array_merge([$start, $end], $this->vehicleScopeBindings()),
                );
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            $map = [];
            foreach ($rows as $row) {
                $map[(string) $row->perusahaan.'|'.(string) $row->site] = (int) $row->total_unit;
            }

            return $map;
        });
    }

    /**
     * Unit beroperasi yang alert-nya sudah diintervensi L1.
     *
     * @return array<string, int> "perusahaan|site" => count
     */
    private function intervenedOperatingUnitCountMapBySiteCompany(string $start, string $end): array
    {
        $cacheKey = 'dms_monitoring:intervened_operating_units_map:'.self::OPERATING_LOGIC_VERSION.':'.md5($start.'|'.$end.'|'.$this->scopeCacheSuffix());

        /** @var array<string, int> */
        return Cache::remember($cacheKey, 1800, function () use ($start, $end): array {
            if (! $this->hasGpsMovingUnitsInRange($start, $end)) {
                return $this->intervenedUnitCountMapBySiteCompanyFromAlerts($start, $end);
            }

            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $unitKey = $this->vehicleRegisterKeyExpr('vs.vehicle_no');
            $scopeWhere = $this->vehicleScopeWhere('o.site', 'o.perusahaan');

            $sql = "
                WITH operating AS (
                    SELECT DISTINCT
                        COALESCE(NULLIF(TRIM(dv.company_owner::text), ''), '-') AS perusahaan,
                        COALESCE(NULLIF(TRIM(dv.site::text), ''), '-') AS site,
                        {$unitKey} AS unit_key
                    FROM bcsid.dms_vehicle_statuses vs
                    INNER JOIN bcsid.dms_vehicle dv ON TRIM(dv.no_register) = {$unitKey}
                    WHERE vs.created_at >= ? AND vs.created_at < ?
                      AND {$this->movingVehicleSpeedWhere('vs')}
                )
                SELECT
                    o.perusahaan,
                    o.site,
                    count(DISTINCT o.unit_key) AS unit_intervened
                FROM operating o
                INNER JOIN bcsid.mv_dms_alert a
                    ON TRIM(a.unit::text) = o.unit_key
                   AND TRIM(a.site::text) = o.site
                   AND TRIM(a.perusahaan::text) = o.perusahaan
                WHERE a.waktu_deteksi >= ? AND a.waktu_deteksi < ?
                  AND a.sudah_direview_l1 = true
                  AND a.unit IS NOT NULL AND TRIM(a.unit::text) <> ''
                  {$scopeWhere}
                GROUP BY 1, 2
            ";

            try {
                $rows = DB::connection($connection)->select(
                    $sql,
                    array_merge([$start, $end, $start, $end], $this->vehicleScopeBindings()),
                );
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            $map = [];
            foreach ($rows as $row) {
                $map[(string) $row->perusahaan.'|'.(string) $row->site] = (int) $row->unit_intervened;
            }

            return $map;
        });
    }

    /**
     * @return list<string>
     */
    private function alertDateBindings(string $start, string $end): array
    {
        $bindings = [$start, $end];
        if ($this->scopeSite !== null) {
            $bindings[] = $this->scopeSite;
        }
        if ($this->scopePerusahaan !== null) {
            $bindings[] = $this->scopePerusahaan;
        }

        return $bindings;
    }

    private function scopeCacheSuffix(): string
    {
        return ($this->scopeSite ?? '').'|'.($this->scopePerusahaan ?? '');
    }

    /**
     * Suffix scope untuk cache key eksternal (modal/service).
     */
    public function scopeCacheSuffixForKey(): string
    {
        return $this->scopeCacheSuffix();
    }

    private function remember(string $key, string $start, string $end, \Closure $callback): array
    {
        if (! $this->isUp()) {
            return [];
        }

        $cacheKey = 'dms_monitoring:'.$key.':'.md5($start.'|'.$end.'|'.$this->scopeCacheSuffix());

        /** @var array<mixed> */
        return Cache::remember($cacheKey, 1800, $callback);
    }

    /**
     * @template T
     *
     * @param  T  $empty
     * @return T
     */
    private function rememberScalar(string $key, string $start, string $end, \Closure $callback, mixed $empty): mixed
    {
        if (! $this->isUp()) {
            return $empty;
        }

        $cacheKey = 'dms_monitoring:'.$key.':'.md5($start.'|'.$end.'|'.$this->scopeCacheSuffix());

        return Cache::remember($cacheKey, 1800, $callback);
    }

    private function rememberScalarInt(string $key, string $start, string $end, \Closure $callback): int
    {
        if (! $this->isUp()) {
            return 0;
        }

        $cacheKey = 'dms_monitoring:'.$key.':'.md5($start.'|'.$end.'|'.$this->scopeCacheSuffix());

        return (int) Cache::remember($cacheKey, 1800, $callback);
    }

    private function applyStatementTimeout(string $connection, int $milliseconds): void
    {
        DB::connection($connection)->statement('SET statement_timeout = '.(string) max(1, $milliseconds));
    }

    private function clearStatementTimeout(string $connection): void
    {
        try {
            DB::connection($connection)->statement('SET statement_timeout = 0');
        } catch (Throwable) {
            // ignore
        }
    }

    /**
     * @param  list<string>  $values
     */
    private function toPgTextArray(array $values): string
    {
        $parts = [];
        foreach ($values as $value) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
            $parts[] = '"'.$escaped.'"';
        }

        return '{'.implode(',', $parts).'}';
    }
}
