<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Jumlah alert fatigue DMS (bcsid.dms_alert ⋈ bcsid.dms_alert_mapping,
 * kategori Menutup Mata/Menguap/Menunduk) per driver_sid dalam window waktu
 * tertentu — dipakai dashboard Pra Operasi, Saat Operasi, dan Evaluasi Harian.
 *
 * Alert harian / live SENGAJA dari tabel mentah (bukan mv_dms_alert) supaya
 * angka sama dengan /pra-operasi dan tidak menunggu refresh materialized view.
 */
final class PraOperasiDmsAlertReader
{
    private const SID_CHUNK = 500;

    /** @var list<string> */
    private const FATIGUE_ALERT_NAMES = ['Menutup Mata', 'Menguap', 'Menunduk'];

    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $connectionSource,
    ) {}

    public function isUp(): bool
    {
        return $this->connectionSource->isUp();
    }

    /**
     * @param  list<string>  $sids
     * @return array<string, int>  UPPER(kode_sid) => jumlah alert fatigue dalam window
     */
    public function fatigueAlertCountsForSids(array $sids, string $sinceDate, string $untilDate): array
    {
        if (! $this->isUp() || $sids === []) {
            return [];
        }

        $normalized = [];
        foreach ($sids as $sid) {
            $trimmed = trim((string) $sid);
            if ($trimmed !== '') {
                $normalized[mb_strtoupper($trimmed)] = true;
            }
        }
        $upperSids = array_keys($normalized);
        if ($upperSids === []) {
            return [];
        }

        $cacheKey = 'pra_operasi:dms_alert:v1:'.$sinceDate.':'.$untilDate.':'.md5(implode(',', $upperSids));

        return Cache::remember($cacheKey, 60, function () use ($upperSids, $sinceDate, $untilDate): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $tz = (string) config('app.timezone');
            $start = Carbon::parse($sinceDate, $tz)->startOfDay()->format('Y-m-d H:i:s');
            $end = Carbon::parse($untilDate, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');

            $namePlaceholders = implode(',', array_fill(0, count(self::FATIGUE_ALERT_NAMES), '?'));

            $counts = [];
            foreach (array_chunk($upperSids, self::SID_CHUNK) as $chunk) {
                $sidPlaceholders = implode(',', array_fill(0, count($chunk), '?'));
                $sql = '
                    SELECT UPPER(TRIM(a.driver_sid)) AS kode_sid, count(*) AS alert_count
                    FROM bcsid.dms_alert a
                    JOIN bcsid.dms_alert_mapping m ON a.alert_name_mapping_id::text = m.id::text
                    WHERE a.event_time >= ? AND a.event_time < ?
                      AND a.driver_sid IS NOT NULL
                      AND UPPER(TRIM(a.driver_sid)) IN ('.$sidPlaceholders.')
                      AND m.name IN ('.$namePlaceholders.')
                    GROUP BY UPPER(TRIM(a.driver_sid))
                ';

                $bindings = array_merge([$start, $end], $chunk, self::FATIGUE_ALERT_NAMES);

                try {
                    $rows = DB::connection($connection)->select($sql, $bindings);
                } catch (Throwable $e) {
                    report($e);
                    continue;
                }

                foreach ($rows as $row) {
                    $sid = trim((string) ($row->kode_sid ?? ''));
                    if ($sid === '') {
                        continue;
                    }
                    $counts[$sid] = (int) ($row->alert_count ?? 0);
                }
            }

            return $counts;
        });
    }

    /**
     * Statistik alert TERKONFIRMASI NYATA (l1_model_status = true) per SID dalam
     * window (default 30 hari) + arah tren (7 hari terakhir vs 7 hari sebelumnya).
     * Dipakai untuk skor risiko komposit — beda dari fatigueAlertCountsForSids()
     * yang menghitung SEMUA alert tanpa memandang status intervensi.
     *
     * @param  list<string>  $sids
     * @return array<string, array{count:int, trend:string, ratio:float|null}>  keyed by UPPER(kode_sid)
     */
    public function confirmedAlertStatsForSids(array $sids, string $untilDate, int $days = 30): array
    {
        if (! $this->isUp() || $sids === []) {
            return [];
        }

        $upperSids = array_values(array_unique(array_filter(array_map(
            static fn (string $s): string => mb_strtoupper(trim($s)),
            $sids
        ), static fn (string $s): bool => $s !== '')));
        if ($upperSids === []) {
            return [];
        }

        $cacheKey = 'pra_operasi:dms_confirmed_stats:v1:'.$untilDate.':'.$days.':'.md5(implode(',', $upperSids));

        return Cache::remember($cacheKey, 1800, function () use ($upperSids, $untilDate, $days): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $tz = (string) config('app.timezone');
            $end = Carbon::parse($untilDate, $tz)->startOfDay()->addDay();
            $midpoint = $end->copy()->subDays(min(7, intdiv($days, 2) ?: 7));
            $farStart = $end->copy()->subDays($days);
            $namePlaceholders = implode(',', array_fill(0, count(self::FATIGUE_ALERT_NAMES), '?'));

            $out = [];
            foreach (array_chunk($upperSids, self::SID_CHUNK) as $chunk) {
                $sidPlaceholders = implode(',', array_fill(0, count($chunk), '?'));
                $sql = '
                    SELECT UPPER(TRIM(kode_sid)) AS sid,
                        count(*) FILTER (WHERE waktu_deteksi >= ? AND waktu_deteksi < ?) AS periode_awal,
                        count(*) FILTER (WHERE waktu_deteksi >= ? AND waktu_deteksi < ?) AS periode_akhir,
                        count(*) AS total
                    FROM bcsid.mv_dms_alert
                    WHERE nama_pelanggaran IN ('.$namePlaceholders.')
                      AND l1_model_status = true
                      AND waktu_deteksi >= ? AND waktu_deteksi < ?
                      AND UPPER(TRIM(kode_sid)) IN ('.$sidPlaceholders.')
                    GROUP BY UPPER(TRIM(kode_sid))
                ';

                $bindings = array_merge(
                    [$farStart->format('Y-m-d H:i:s'), $midpoint->format('Y-m-d H:i:s')],
                    [$midpoint->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')],
                    self::FATIGUE_ALERT_NAMES,
                    [$farStart->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')],
                    $chunk,
                );

                try {
                    $rows = DB::connection($connection)->select($sql, $bindings);
                } catch (Throwable $e) {
                    report($e);
                    continue;
                }

                foreach ($rows as $row) {
                    $sid = trim((string) ($row->sid ?? ''));
                    if ($sid === '') {
                        continue;
                    }
                    $awal = (int) $row->periode_awal;
                    $akhir = (int) $row->periode_akhir;
                    $ratio = $awal > 0 ? round($akhir / $awal, 2) : ($akhir > 0 ? null : 1.0);

                    $trend = 'stabil';
                    if ($ratio !== null) {
                        if ($ratio >= 1.2) {
                            $trend = 'meningkat';
                        } elseif ($ratio <= 0.8) {
                            $trend = 'menurun';
                        }
                    } elseif ($akhir > 0) {
                        $trend = 'meningkat';
                    }

                    $out[$sid] = ['count' => (int) $row->total, 'trend' => $trend, 'ratio' => $ratio];
                }
            }

            return $out;
        });
    }

    /**
     * Breakdown status alert (nyata/palsu/belum) untuk SATU tanggal spesifik per
     * SID — dipakai Saat Operasi (Fase 2) dan Evaluasi Harian (Fase 3). Beda dari
     * confirmedAlertStatsForSids() yang window 30 hari & hanya menghitung yang nyata.
     *
     * Mapping status mengikuti definisi mv_dms_alert:
     * sudah_direview_l1 = (l1_updated_at IS NOT NULL).
     *
     * @param  list<string>  $sids
     * @return array<string, array{nyata:int, palsu:int, belum:int}>  keyed by UPPER(kode_sid)
     */
    public function dailyAlertBreakdownForSids(array $sids, string $date): array
    {
        if (! $this->isUp() || $sids === []) {
            return [];
        }

        $upperSids = array_values(array_unique(array_filter(array_map(
            static fn (string $s): string => mb_strtoupper(trim($s)),
            $sids
        ), static fn (string $s): bool => $s !== '')));
        if ($upperSids === []) {
            return [];
        }

        $cacheKey = 'pra_operasi:dms_daily_breakdown:v2:'.$date.':'.md5(implode(',', $upperSids));

        return Cache::remember($cacheKey, 300, function () use ($upperSids, $date): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $tz = (string) config('app.timezone');
            $start = Carbon::parse($date, $tz)->startOfDay()->format('Y-m-d H:i:s');
            $end = Carbon::parse($date, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');
            $namePlaceholders = implode(',', array_fill(0, count(self::FATIGUE_ALERT_NAMES), '?'));

            $out = [];
            foreach (array_chunk($upperSids, self::SID_CHUNK) as $chunk) {
                $sidPlaceholders = implode(',', array_fill(0, count($chunk), '?'));
                $sql = '
                    SELECT UPPER(TRIM(a.driver_sid)) AS sid,
                        count(*) FILTER (WHERE a.l1_updated_at IS NOT NULL AND a.l1_model_status = true) AS nyata,
                        count(*) FILTER (WHERE a.l1_updated_at IS NOT NULL AND a.l1_model_status = false) AS palsu,
                        count(*) FILTER (WHERE a.l1_updated_at IS NULL) AS belum
                    FROM bcsid.dms_alert a
                    JOIN bcsid.dms_alert_mapping m ON a.alert_name_mapping_id::text = m.id::text
                    WHERE m.name IN ('.$namePlaceholders.')
                      AND a.event_time >= ? AND a.event_time < ?
                      AND a.driver_sid IS NOT NULL
                      AND UPPER(TRIM(a.driver_sid)) IN ('.$sidPlaceholders.')
                    GROUP BY UPPER(TRIM(a.driver_sid))
                ';

                $bindings = array_merge(self::FATIGUE_ALERT_NAMES, [$start, $end], $chunk);

                try {
                    $rows = DB::connection($connection)->select($sql, $bindings);
                } catch (Throwable $e) {
                    report($e);
                    continue;
                }

                foreach ($rows as $row) {
                    $sid = trim((string) ($row->sid ?? ''));
                    if ($sid === '') {
                        continue;
                    }
                    $out[$sid] = [
                        'nyata' => (int) $row->nyata,
                        'palsu' => (int) $row->palsu,
                        'belum' => (int) $row->belum,
                    ];
                }
            }

            return $out;
        });
    }

    /**
     * Fase 2 (Saat Operasi) — feed alert terbaru (kronologis, terbaru di atas)
     * lintas beberapa SID sekaligus, untuk papan pemantauan live. TIDAK
     * di-cache lama (30 detik saja) karena memang dipakai untuk polling live.
     *
     * @param  list<string>  $sids
     * @return list<array{kode_sid:string, nama:string, waktu:string, name:string, status:string}>  terbaru dulu
     */
    public function recentAlertsForSids(array $sids, string $date, int $limit = 25): array
    {
        if (! $this->isUp() || $sids === []) {
            return [];
        }

        $upperSids = array_values(array_unique(array_filter(array_map(
            static fn (string $s): string => mb_strtoupper(trim($s)),
            $sids
        ), static fn (string $s): bool => $s !== '')));
        if ($upperSids === []) {
            return [];
        }

        $cacheKey = 'pra_operasi:dms_recent_feed:v2:'.$date.':'.md5(implode(',', $upperSids)).':'.$limit;

        return Cache::remember($cacheKey, 30, function () use ($upperSids, $date, $limit): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $tz = (string) config('app.timezone');
            $start = Carbon::parse($date, $tz)->startOfDay()->format('Y-m-d H:i:s');
            $end = Carbon::parse($date, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');
            $namePlaceholders = implode(',', array_fill(0, count(self::FATIGUE_ALERT_NAMES), '?'));
            $sidPlaceholders = implode(',', array_fill(0, count($upperSids), '?'));

            $sql = '
                SELECT a.driver_sid AS kode_sid,
                    a.driver_name AS nama_driver_dms,
                    a.event_time AS waktu_deteksi,
                    m.name AS nama_pelanggaran,
                    a.l1_model_status,
                    (a.l1_updated_at IS NOT NULL) AS sudah_direview_l1
                FROM bcsid.dms_alert a
                JOIN bcsid.dms_alert_mapping m ON a.alert_name_mapping_id::text = m.id::text
                WHERE m.name IN ('.$namePlaceholders.')
                  AND a.event_time >= ? AND a.event_time < ?
                  AND a.driver_sid IS NOT NULL
                  AND UPPER(TRIM(a.driver_sid)) IN ('.$sidPlaceholders.')
                ORDER BY a.event_time DESC
                LIMIT ?
            ';

            $bindings = array_merge(self::FATIGUE_ALERT_NAMES, [$start, $end], $upperSids, [$limit]);

            try {
                $rows = DB::connection($connection)->select($sql, $bindings);
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            $out = [];
            foreach ($rows as $row) {
                $reviewed = (bool) ($row->sudah_direview_l1 ?? false);
                $status = ! $reviewed ? 'belum' : (((bool) $row->l1_model_status) ? 'nyata' : 'palsu');
                $waktu = $row->waktu_deteksi ?? null;
                $tanggal = $waktu instanceof \DateTimeInterface
                    ? Carbon::instance($waktu)->timezone($tz)->format('H:i:s')
                    : (string) $waktu;

                $out[] = [
                    'kode_sid' => trim((string) ($row->kode_sid ?? '')),
                    'nama' => trim((string) ($row->nama_driver_dms ?? '')) ?: '-',
                    'waktu' => $tanggal,
                    'name' => trim((string) ($row->nama_pelanggaran ?? '')),
                    'status' => $status,
                ];
            }

            return $out;
        });
    }

    /**
     * Riwayat alert individual (bukan agregat harian) untuk SATU SID selama
     * N hari terakhir — dipakai panel detail operator (mis. fatigue-baseline
     * dashboard) untuk menelusuri kronologi kejadian, bukan cuma jumlahnya.
     *
     * @return list<array{date:string, name:string, status:string}>  terbaru dulu
     */
    public function alertTimelineForSid(string $kodeSid, string $untilDate, int $days = 30, int $limit = 50): array
    {
        if (! $this->isUp()) {
            return [];
        }

        $upper = mb_strtoupper(trim($kodeSid));
        if ($upper === '') {
            return [];
        }

        $cacheKey = 'pra_operasi:dms_alert_timeline:v1:'.$untilDate.':'.$days.':'.$upper.':'.$limit;

        return Cache::remember($cacheKey, 300, function () use ($upper, $untilDate, $days, $limit): array {
            $connection = $this->connectionSource->connectionName();
            if ($connection === null) {
                return [];
            }

            $tz = (string) config('app.timezone');
            $end = Carbon::parse($untilDate, $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');
            $start = Carbon::parse($untilDate, $tz)->startOfDay()->subDays($days)->format('Y-m-d H:i:s');
            $namePlaceholders = implode(',', array_fill(0, count(self::FATIGUE_ALERT_NAMES), '?'));

            $sql = '
                SELECT waktu_deteksi, nama_pelanggaran, l1_model_status, sudah_direview_l1
                FROM bcsid.mv_dms_alert
                WHERE UPPER(TRIM(kode_sid)) = ?
                  AND nama_pelanggaran IN ('.$namePlaceholders.')
                  AND waktu_deteksi >= ? AND waktu_deteksi < ?
                ORDER BY waktu_deteksi DESC
                LIMIT ?
            ';

            try {
                $rows = DB::connection($connection)->select(
                    $sql,
                    array_merge([$upper], self::FATIGUE_ALERT_NAMES, [$start, $end, $limit])
                );
            } catch (Throwable $e) {
                report($e);

                return [];
            }

            $out = [];
            foreach ($rows as $row) {
                $reviewed = (bool) ($row->sudah_direview_l1 ?? false);
                $status = ! $reviewed ? 'belum' : (((bool) $row->l1_model_status) ? 'nyata' : 'palsu');

                $waktu = $row->waktu_deteksi ?? null;
                $tanggal = $waktu instanceof \DateTimeInterface
                    ? Carbon::instance($waktu)->timezone($tz)->format('Y-m-d H:i')
                    : (string) $waktu;

                $out[] = [
                    'date' => $tanggal,
                    'name' => trim((string) ($row->nama_pelanggaran ?? '')),
                    'status' => $status,
                ];
            }

            return $out;
        });
    }
}
