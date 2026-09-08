<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\Metrics\ControlRoomSapQualityEvaluator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Evaluasi kualitas SAP personil jaga minggu terpilih.
 * Roster = satu query MySQL; SAP = query OBDS ter-chunk, cache 3 menit.
 */
final class ControlRoomDataQualityService
{
    private const PAGE_CACHE_SECONDS = 180;

    public function __construct(
        private readonly ControlRoomSapQualityFindingsReader $qualityFindings,
        private readonly ControlRoomSapQualityEvaluator $evaluator,
    ) {}

    /**
     * @return array{
     *     loaded: bool,
     *     rows: list<array<string, mixed>>,
     *     kpi: array<string, mixed>
     * }
     */
    public function build(
        CarbonImmutable $weekStart,
        ?ControlRoomSiteCode $site = null,
        ?CarbonInterface $now = null,
    ): array {
        $today = CarbonImmutable::parse($now ?? now())->startOfDay();
        $cacheKey = sprintf(
            'control-room:data-quality:v3:%s:%s:%s',
            $weekStart->toDateString(),
            $site?->value ?? 'ALL',
            $today->toDateString(),
        );

        /** @var array{loaded: bool, rows: list<array<string, mixed>>, kpi: array<string, mixed>} */
        return Cache::remember($cacheKey, self::PAGE_CACHE_SECONDS, function () use ($weekStart, $site, $today): array {
            return $this->buildUncached($weekStart, $site, $today);
        });
    }

    /**
     * @return array{loaded: bool, rows: list<array<string, mixed>>, kpi: array<string, mixed>}
     */
    private function buildUncached(
        CarbonImmutable $weekStart,
        ?ControlRoomSiteCode $site,
        CarbonImmutable $today,
    ): array {
        $people = $this->roster($weekStart, $site, $today);
        if ($people === []) {
            return [
                'loaded' => true,
                'rows' => [],
                'kpi' => $this->kpi([]),
            ];
        }

        $dates = [];
        foreach ($people as $person) {
            foreach ($person['dates'] as $date) {
                $dates[] = $date;
            }
        }
        sort($dates);
        $lastDuty = CarbonImmutable::parse($dates[array_key_last($dates)]);
        $sap = $this->qualityFindings->forSids(array_keys($people), $weekStart, $lastDuty);

        $findingsBySid = [];
        foreach ($sap['findings'] as $finding) {
            $sid = strtoupper(trim((string) ($finding['sid'] ?? '')));
            if ($sid !== '') {
                $findingsBySid[$sid][] = $finding;
            }
        }

        $rows = [];
        foreach ($people as $person) {
            $siteCodes = array_keys($person['sites']);
            $rows[] = $this->evaluator->evaluate(
                $person['sid'],
                $person['name'],
                implode(', ', $siteCodes),
                implode(', ', array_values($person['sites'])),
                array_values($person['dates']),
                $findingsBySid[$person['sid']] ?? [],
            );
        }

        usort($rows, function (array $a, array $b): int {
            $byComposite = $b['composite'] <=> $a['composite'];
            if ($byComposite !== 0) {
                return $byComposite;
            }
            $byTotal = $b['total'] <=> $a['total'];
            if ($byTotal !== 0) {
                return $byTotal;
            }

            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return [
            'loaded' => $sap['loaded'],
            'rows' => $rows,
            'kpi' => $this->kpi($rows),
        ];
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
     * @return list<ControlRoomSiteCode>
     */
    public function sites(?ControlRoomSiteCode $site): array
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
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function kpi(array $rows): array
    {
        $labels = [
            'Belum ada temuan' => 0,
            'Perlu perbaikan' => 0,
            'Cukup' => 0,
            'Baik' => 0,
        ];
        $composites = [];
        $mixes = [];
        foreach ($rows as $row) {
            $label = (string) $row['label'];
            if (isset($labels[$label])) {
                $labels[$label]++;
            }
            $composites[] = (float) $row['composite'];
            $mixes[] = (float) $row['scores'][ControlRoomSapQualityEvaluator::AXIS_MIX];
        }

        return [
            'personnel' => count($rows),
            'avg_composite' => $composites === [] ? null : round(array_sum($composites) / count($composites), 1),
            'avg_mix' => $mixes === [] ? null : round(array_sum($mixes) / count($mixes), 1),
            'labels' => $labels,
        ];
    }
}
