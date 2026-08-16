<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Jumlah alert fatigue DMS (bcsid.dms_alert, kategori Menutup Mata/Menguap/Menunduk)
 * per driver_sid dalam window waktu tertentu — dipakai untuk cross-check "Pencapaian
 * Pengisian Aggregator Fatigue berdasarkan Pekerja dengan Alert DMS".
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
}
