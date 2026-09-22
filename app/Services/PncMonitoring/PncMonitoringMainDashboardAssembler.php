<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

final class PncMonitoringMainDashboardAssembler
{
    public function __construct(
        private readonly PncMonitoringIkkDashboardAssembler $ikkAssembler,
        private readonly PncMonitoringPengawasDashboardAssembler $commissioningAssembler,
        private readonly PncMonitoringInventoryDashboardAssembler $inventoryAssembler,
    ) {}

    /**
     * @param  array{year?:string,site?:string}  $filters
     * @return array<string, mixed>
     */
    public function assemble(array $filters): array
    {
        $year = $this->allOrValue($filters['year'] ?? 'ALL');
        $site = $this->allOrValue($filters['site'] ?? 'ALL');

        $ikk = $this->ikkAssembler->assemble(['year' => $year, 'site' => $site]);
        $ikkHeatmap = $this->ikkAssembler->complianceHeatmap(['year' => $year, 'site' => $site]);
        $ikkDailySeries = $this->ikkAssembler->dailySeries(['site' => $site]);
        $commissioning = $this->commissioningAssembler->assemble(['year' => $year, 'site' => $site]);
        $inventory = $this->inventoryAssembler->assemble(['site' => $site]);

        return [
            'meta' => [
                'generatedAt' => now()->timezone(config('app.timezone'))->format('d M Y H:i'),
            ],
            'filters' => ['year' => $year, 'site' => $site],
            'options' => [
                'years' => $this->mergeOptions($ikk['options']['years'] ?? [], $commissioning['options']['years'] ?? []),
                'sites' => $this->mergeOptions(
                    $this->mergeOptions($ikk['options']['sites'] ?? [], $commissioning['options']['sites'] ?? []),
                    $inventory['options']['sites'] ?? [],
                ),
            ],
            'ikk' => [
                'kpis' => $ikk['kpis'],
                'bySite' => $ikk['bySite'],
                'trend' => $ikk['trend'],
                'heatmap' => $ikkHeatmap,
                'dailySeries' => $ikkDailySeries,
            ],
            'commissioning' => [
                'kpis' => $commissioning['kpis'],
                'bySite' => $commissioning['bySite'],
                'weeklyTrend' => $commissioning['weeklyTrend'],
                'top10' => $commissioning['top10'],
                'bottom10' => $commissioning['bottom10'],
                'rejectReasons' => $commissioning['rejectReasons'],
            ],
            'inventory' => [
                'kpis' => $inventory['kpis'],
                'byCategory' => $inventory['byCategory'],
                'byStatus' => $inventory['byStatus'],
                'dueSoon' => $inventory['dueSoon'],
            ],
            'combinedBySite' => $this->combineBySite($ikk['bySite'], $commissioning['bySite']),
            'combinedTrend' => $this->combineTrend($ikk['trend'], $commissioning['weeklyTrend']),
        ];
    }

    /**
     * @param  list<string|int>  $a
     * @param  list<string|int>  $b
     * @return list<string|int>
     */
    private function mergeOptions(array $a, array $b): array
    {
        $merged = array_values(array_unique(array_merge($a, $b)));
        sort($merged);

        return $merged;
    }

    private function allOrValue(mixed $value): string
    {
        $text = trim((string) $value);

        return $text === '' ? 'ALL' : $text;
    }

    /**
     * @param  list<array<string, mixed>>  $ikkSites
     * @param  list<array<string, mixed>>  $commissioningSites
     * @return list<array<string, mixed>>
     */
    private function combineBySite(array $ikkSites, array $commissioningSites): array
    {
        $map = [];

        foreach ($ikkSites as $row) {
            $name = (string) $row['name'];
            $map[$name] ??= ['site' => $name];
            $map[$name]['ikkCount'] = $row['ikkCount'];
            $map[$name]['ipkPerformance'] = $row['ipkPerformance'];
            $map[$name]['iaPerformance'] = $row['iaPerformance'];
        }

        foreach ($commissioningSites as $row) {
            $name = (string) $row['site'];
            $map[$name] ??= ['site' => $name];
            $map[$name]['commissioningUnits'] = $row['units'];
            $map[$name]['commissioningPerformance'] = $row['performance'];
        }

        foreach ($map as $name => &$row) {
            $row['ikkCount'] ??= 0;
            $row['ipkPerformance'] ??= null;
            $row['iaPerformance'] ??= null;
            $row['commissioningUnits'] ??= 0;
            $row['commissioningPerformance'] ??= null;
        }
        unset($row);

        ksort($map);

        return array_values($map);
    }

    /**
     * @param  list<array<string, mixed>>  $ikkTrend
     * @param  list<array<string, mixed>>  $commissioningTrend
     * @return list<array<string, mixed>>
     */
    private function combineTrend(array $ikkTrend, array $commissioningTrend): array
    {
        $map = [];

        foreach ($ikkTrend as $row) {
            $period = (string) $row['period'];
            $map[$period] ??= ['period' => $period];
            $map[$period]['ikkCount'] = $row['ikkCount'];
            $map[$period]['ipkPerformance'] = $row['ipkPerformance'];
        }

        foreach ($commissioningTrend as $row) {
            $period = (string) $row['week'];
            $map[$period] ??= ['period' => $period];
            $map[$period]['commissioningUnits'] = $row['units'];
            $map[$period]['commissioningPerformance'] = $row['performance'];
        }

        foreach ($map as $period => &$row) {
            $row['ikkCount'] ??= 0;
            $row['ipkPerformance'] ??= null;
            $row['commissioningUnits'] ??= 0;
            $row['commissioningPerformance'] ??= null;
        }
        unset($row);

        ksort($map);

        return array_values($map);
    }
}
