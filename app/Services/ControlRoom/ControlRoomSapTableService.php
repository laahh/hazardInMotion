<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\SchedulePlan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Tabel laporan SAP minggu terpilih, hanya SID yang jaga Control Room.
 * Jendela sama dengan Detail SAP: H 00:00 s/d H+2 00:00.
 */
final class ControlRoomSapTableService
{
    private const PAGE_CACHE_SECONDS = 180;

    public function __construct(
        private readonly ControlRoomSapWeekCountsReader $weekCounts,
    ) {}

    /**
     * @return array{
     *     loaded: bool,
     *     rows: list<array<string, mixed>>,
     *     kpi: array<string, int>
     * }
     */
    public function build(
        CarbonImmutable $weekStart,
        ?ControlRoomSiteCode $site = null,
        ?CarbonInterface $now = null,
    ): array {
        $today = CarbonImmutable::parse($now ?? now())->startOfDay();
        $cacheKey = sprintf(
            'control-room:sap-table:v2:%s:%s:%s',
            $weekStart->toDateString(),
            $site?->value ?? 'ALL',
            $today->toDateString(),
        );

        /** @var array{loaded: bool, rows: list<array<string, mixed>>, kpi: array<string, int>} */
        return Cache::remember($cacheKey, self::PAGE_CACHE_SECONDS, function () use ($weekStart, $site, $today): array {
            return $this->buildUncached($weekStart, $site, $today);
        });
    }

    /**
     * @param  array<string, array{sid: string, name: string, sites: array<string, string>, dates: array<string, string>}>  $people
     * @param  list<array<string, mixed>>  $findings
     * @return array{loaded: bool, rows: list<array<string, mixed>>, kpi: array<string, int>}
     */
    public function present(array $people, array $findings, bool $loaded = true): array
    {
        $rows = [];
        foreach ($findings as $finding) {
            $sid = strtoupper(trim((string) ($finding['sid'] ?? '')));
            if ($sid === '' || ! isset($people[$sid])) {
                continue;
            }
            $person = $people[$sid];
            $at = trim((string) ($finding['at'] ?? ''));
            $component = strtolower(trim((string) ($finding['component'] ?? '')));
            $rows[] = [
                'at' => $at,
                'sid' => $sid,
                'name' => $person['name'] !== '' ? $person['name'] : trim((string) ($finding['name'] ?? '')),
                'sites' => implode(', ', array_values($person['sites'])),
                'component' => $component,
                'component_label' => $this->componentLabel($component),
                'category' => trim((string) ($finding['category'] ?? '')),
                'golden_rule' => trim((string) ($finding['golden_rule'] ?? '')),
                'lokasi' => trim((string) ($finding['lokasi'] ?? '')),
                'detil_lokasi' => trim((string) ($finding['detil_lokasi'] ?? '')),
                'report_id' => trim((string) ($finding['report_id'] ?? '')),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $byAt = strcmp((string) $b['at'], (string) $a['at']);
            if ($byAt !== 0) {
                return $byAt;
            }

            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return [
            'loaded' => $loaded,
            'rows' => $rows,
            'kpi' => $this->kpi($people, $rows),
        ];
    }

    public function componentLabel(string $component): string
    {
        return match (strtolower(trim($component))) {
            'hazard' => 'Hazard',
            'inspeksi' => 'Inspeksi',
            'observasi' => 'Observasi',
            'oak' => 'OAK',
            default => strtoupper(trim($component)),
        };
    }

    /**
     * @return array{loaded: bool, rows: list<array<string, mixed>>, kpi: array<string, int>}
     */
    private function buildUncached(
        CarbonImmutable $weekStart,
        ?ControlRoomSiteCode $site,
        CarbonImmutable $today,
    ): array {
        $people = $this->roster($weekStart, $site, $today);
        if ($people === []) {
            return $this->present([], [], true);
        }

        $sap = $this->weekCounts->forScheduleDays($this->scheduleDays($people));

        return $this->present($people, $sap['findings'], $sap['loaded']);
    }

    /**
     * @return array<string, array{sid: string, name: string, sites: array<string, string>, dates: array<string, string>}>
     */
    private function roster(
        CarbonImmutable $weekStart,
        ?ControlRoomSiteCode $site,
        CarbonImmutable $today,
    ): array {
        $sites = $this->sites($site);
        if ($sites === []) {
            return [];
        }

        $codes = array_map(static fn (ControlRoomSiteCode $item): string => $item->value, $sites);
        $visibleUntil = $weekStart->addDays(6)->lessThan($today) ? $weekStart->addDays(6) : $today;

        $plans = SchedulePlan::query()
            ->select(['site_code', 'date', 'personnel_source_key', 'personnel_name_snapshot'])
            ->whereIn('site_code', $codes)
            ->whereBetween('date', [$weekStart->toDateString(), $visibleUntil->toDateString()])
            ->orderBy('personnel_name_snapshot')
            ->get();

        $people = [];
        foreach ($plans as $plan) {
            $sid = strtoupper(trim((string) $plan->personnel_source_key));
            $name = trim((string) $plan->personnel_name_snapshot);
            if ($sid === '' || $name === '' || $name === '—') {
                continue;
            }
            $siteCode = $plan->site_code instanceof ControlRoomSiteCode
                ? $plan->site_code
                : ControlRoomSiteCode::from((string) $plan->site_code);
            $date = $plan->date instanceof CarbonInterface
                ? $plan->date->toDateString()
                : (string) $plan->date;
            if (! isset($people[$sid])) {
                $people[$sid] = [
                    'sid' => $sid,
                    'name' => $name,
                    'sites' => [],
                    'dates' => [],
                ];
            }
            $people[$sid]['sites'][$siteCode->value] = $siteCode->label();
            $people[$sid]['dates'][$date] = $date;
        }

        return $people;
    }

    /**
     * @param  array<string, array{sid: string, name: string, sites: array<string, string>, dates: array<string, string>}>  $people
     * @return list<array{date: string, s1: list<array{sid: string}>, s2: list<array{sid: string}>}>
     */
    private function scheduleDays(array $people): array
    {
        $days = [];
        foreach ($people as $person) {
            foreach ($person['dates'] as $date) {
                if (! isset($days[$date])) {
                    $days[$date] = ['date' => $date, 's1' => [], 's2' => []];
                }
                $days[$date]['s1'][] = ['sid' => $person['sid']];
            }
        }
        ksort($days);

        return array_values($days);
    }

    /**
     * @return list<ControlRoomSiteCode>
     */
    private function sites(?ControlRoomSiteCode $site): array
    {
        if ($site instanceof ControlRoomSiteCode) {
            if (! in_array($site->value, ControlRoomSiteDutyBoardService::BOARD_SITE_CODES, true)) {
                return [];
            }

            return [$site];
        }

        $sites = [];
        foreach (ControlRoomSiteDutyBoardService::BOARD_SITE_CODES as $code) {
            $sites[] = ControlRoomSiteCode::from($code);
        }

        return $sites;
    }

    /**
     * @param  array<string, array{sid: string, name: string, sites: array<string, string>, dates: array<string, string>}>  $people
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function kpi(array $people, array $rows): array
    {
        $reporters = [];
        $counts = [
            'hazard' => 0,
            'inspeksi' => 0,
            'observasi' => 0,
            'oak' => 0,
        ];
        foreach ($rows as $row) {
            $component = (string) ($row['component'] ?? '');
            if (isset($counts[$component])) {
                $counts[$component]++;
            }
            $sid = (string) ($row['sid'] ?? '');
            if ($sid !== '') {
                $reporters[$sid] = true;
            }
        }

        $personnel = count($people);

        return [
            'personnel' => $personnel,
            'reporters' => count($reporters),
            'without_sap' => max(0, $personnel - count($reporters)),
            'total' => count($rows),
            'hazard' => $counts['hazard'],
            'inspeksi' => $counts['inspeksi'],
            'observasi' => $counts['observasi'],
            'oak' => $counts['oak'],
        ];
    }
}
