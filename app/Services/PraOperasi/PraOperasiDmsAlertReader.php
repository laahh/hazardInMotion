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
}
