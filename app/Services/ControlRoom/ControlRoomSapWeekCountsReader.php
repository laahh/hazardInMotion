<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Agregat jumlah laporan SAP per SID per tanggal jaga, untuk tabel
 * Pencapaian Personil. Satu batch per minggu (bukan N+1 per baris).
 *
 * Observasi dan OAK mengisi slot yang sama. Jendela sama dengan Detail:
 * hari H s/d akhir H+1.
 */
final class ControlRoomSapWeekCountsReader
{
    private const CACHE_SECONDS = 90;

    private const QUERY_TIMEOUT_MS = 4000;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
        private readonly ControlRoomSapDutyReader $dutyWindow,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $scheduleDays
     * @return array{
     *     loaded: bool,
     *     counts: array<string, array{hazard: int, inspeksi: int, observasi: int}>
     * }
     */
    public function forScheduleDays(array $scheduleDays): array
    {
        $duties = $this->dutiesFromSchedule($scheduleDays);
        if ($duties === []) {
            return ['loaded' => true, 'counts' => []];
        }

        if (! $this->olap->isReachable()) {
            return ['loaded' => false, 'counts' => []];
        }

        $sids = array_values(array_unique(array_column($duties, 'sid')));
        sort($sids);
        $dates = array_column($duties, 'date');
        sort($dates);
        $cacheKey = 'control-room:sap-week-counts:v1:'.hash('sha1', implode(',', $sids).'|'.$dates[0].'|'.$dates[array_key_last($dates)]);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['counts'])) {
            return ['loaded' => true, 'counts' => $cached['counts']];
        }

        $rangeStart = CarbonImmutable::parse($dates[0])->startOfDay();
        $rangeEnd = $this->dutyWindow->reportingWindow(CarbonImmutable::parse($dates[array_key_last($dates)]))['end'];

        $failed = 0;
        $events = [
            ...$this->fetchHazardInspeksi($sids, $rangeStart, $rangeEnd, $failed),
            ...$this->fetchObservasi($sids, $rangeStart, $rangeEnd, $failed),
            ...$this->fetchOak($sids, $rangeStart, $rangeEnd, $failed),
        ];

        if ($failed > 0) {
            return ['loaded' => false, 'counts' => []];
        }

        $counts = $this->countForDuties($events, $duties);
        Cache::put($cacheKey, ['counts' => $counts], self::CACHE_SECONDS);

        return ['loaded' => true, 'counts' => $counts];
    }

    /**
     * @param  list<array{sid: string, at: CarbonImmutable, component: string}>  $events
     * @param  list<array{sid: string, date: string}>  $duties
     * @return array<string, array{hazard: int, inspeksi: int, observasi: int}>
     */
    public function countForDuties(array $events, array $duties): array
    {
        $counts = [];
        foreach ($duties as $duty) {
            $key = $this->slotKey($duty['sid'], $duty['date']);
            if (isset($counts[$key])) {
                continue;
            }

            $window = $this->dutyWindow->reportingWindow(CarbonImmutable::parse($duty['date']));
            $bucket = ['hazard' => 0, 'inspeksi' => 0, 'observasi' => 0];
            foreach ($events as $event) {
                if ($event['sid'] !== $duty['sid']) {
                    continue;
                }
                if ($event['at']->lt($window['start']) || $event['at']->gte($window['end'])) {
                    continue;
                }
                $component = $event['component'];
                if (isset($bucket[$component])) {
                    $bucket[$component]++;
                }
            }
            $counts[$key] = $bucket;
        }

        return $counts;
    }

    public function slotKey(string $sid, string $date): string
    {
        return strtoupper(trim($sid)).'|'.$date;
    }

    /**
     * @param  list<array<string, mixed>>  $scheduleDays
     * @return list<array{sid: string, date: string}>
     */
    private function dutiesFromSchedule(array $scheduleDays): array
    {
        $duties = [];
        foreach ($scheduleDays as $day) {
            $date = (string) ($day['date'] ?? '');
            if ($date === '') {
                continue;
            }
            foreach (['s1', 's2'] as $shiftKey) {
                foreach ($day[$shiftKey] ?? [] as $person) {
                    $sid = strtoupper(trim((string) ($person['sid'] ?? '')));
                    if ($sid === '') {
                        continue;
                    }
                    $duties[] = ['sid' => $sid, 'date' => $date];
                }
            }
        }

        return $duties;
    }

    /**
     * @param  list<string>  $sids
     * @param  int  $failed
     * @return list<array{sid: string, at: CarbonImmutable, component: string}>
     */
    private function fetchHazardInspeksi(array $sids, CarbonImmutable $start, CarbonImmutable $end, int &$failed): array
    {
        $placeholders = implode(',', array_fill(0, count($sids), '?'));
        $sql = "
            WITH sid_rows AS MATERIALIZED (
                SELECT kode_sid_pelapor, tanggal_laporan, jenis_laporan
                FROM bcbeats.mv_inspeksi_hazard
                WHERE kode_sid_pelapor IN ({$placeholders})
            )
            SELECT kode_sid_pelapor, tanggal_laporan, jenis_laporan
            FROM sid_rows
            WHERE tanggal_laporan >= CAST(? AS timestamp)
              AND tanggal_laporan < CAST(? AS timestamp)
        ";

        $rows = $this->select($sql, [...$sids, $start->toDateTimeString(), $end->toDateTimeString()], 'hazard/inspeksi', $failed);
        $events = [];
        foreach ($rows as $row) {
            $at = $this->parseAt($row->tanggal_laporan ?? null);
            $sid = strtoupper(trim((string) ($row->kode_sid_pelapor ?? '')));
            if ($at === null || $sid === '') {
                continue;
            }
            $jenis = strtoupper(trim((string) ($row->jenis_laporan ?? '')));
            $events[] = [
                'sid' => $sid,
                'at' => $at,
                'component' => $jenis === 'INSPEKSI' ? 'inspeksi' : 'hazard',
            ];
        }

        return $events;
    }

    /**
     * @param  list<string>  $sids
     * @param  int  $failed
     * @return list<array{sid: string, at: CarbonImmutable, component: string}>
     */
    private function fetchObservasi(array $sids, CarbonImmutable $start, CarbonImmutable $end, int &$failed): array
    {
        $placeholders = implode(',', array_fill(0, count($sids), '?'));
        $sql = "
            WITH sid_rows AS MATERIALIZED (
                SELECT kode_sid_pelapor, tanggal_observasi
                FROM bcbeats.mv_observasi
                WHERE kode_sid_pelapor IN ({$placeholders})
            )
            SELECT kode_sid_pelapor, tanggal_observasi
            FROM sid_rows
            WHERE tanggal_observasi >= CAST(? AS timestamp)
              AND tanggal_observasi < CAST(? AS timestamp)
        ";

        $rows = $this->select($sql, [...$sids, $start->toDateTimeString(), $end->toDateTimeString()], 'observasi', $failed);
        $events = [];
        foreach ($rows as $row) {
            $at = $this->parseAt($row->tanggal_observasi ?? null);
            $sid = strtoupper(trim((string) ($row->kode_sid_pelapor ?? '')));
            if ($at === null || $sid === '') {
                continue;
            }
            $events[] = [
                'sid' => $sid,
                'at' => $at,
                'component' => 'observasi',
            ];
        }

        return $events;
    }

    /**
     * @param  list<string>  $sids
     * @param  int  $failed
     * @return list<array{sid: string, at: CarbonImmutable, component: string}>
     */
    private function fetchOak(array $sids, CarbonImmutable $start, CarbonImmutable $end, int &$failed): array
    {
        $placeholders = implode(',', array_fill(0, count($sids), '?'));
        $sql = "
            WITH sid_rows AS MATERIALIZED (
                SELECT id_oak, kode_sid_pelapor, tanggal_submit
                FROM bcbeats.mv_oak
                WHERE kode_sid_pelapor IN ({$placeholders})
            )
            SELECT DISTINCT ON (id_oak)
                kode_sid_pelapor, tanggal_submit
            FROM sid_rows
            WHERE tanggal_submit >= CAST(? AS timestamp)
              AND tanggal_submit < CAST(? AS timestamp)
            ORDER BY id_oak, tanggal_submit
        ";

        $rows = $this->select($sql, [...$sids, $start->toDateTimeString(), $end->toDateTimeString()], 'OAK', $failed);
        $events = [];
        foreach ($rows as $row) {
            $at = $this->parseAt($row->tanggal_submit ?? null);
            $sid = strtoupper(trim((string) ($row->kode_sid_pelapor ?? '')));
            if ($at === null || $sid === '') {
                continue;
            }
            $events[] = [
                'sid' => $sid,
                'at' => $at,
                'component' => 'observasi',
            ];
        }

        return $events;
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<object>
     */
    private function select(string $sql, array $bindings, string $source, int &$failed): array
    {
        try {
            return $this->olap->select($sql, $bindings, self::QUERY_TIMEOUT_MS);
        } catch (Throwable $e) {
            Log::warning('ControlRoom SAP week counts '.$source.' gagal: '.$e->getMessage());
            $failed++;

            return [];
        }
    }

    private function parseAt(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
