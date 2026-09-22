<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringIkkRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class PncMonitoringIkkDashboardAssembler
{
    /**
     * @param  array{year?:string,month?:string,week?:string,site?:string,company?:string,type?:string}  $filters
     * @return array<string, mixed>
     */
    public function assemble(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $cacheKey = 'pnc_monitoring_ikk_dash_'.md5((string) json_encode($normalized));

        return Cache::remember($cacheKey, 45, function () use ($normalized): array {
            $rows = $this->baseQuery($normalized)->get([
                'id', 'jenis', 'nomor', 'tanggal', 'minggu', 'bulan', 'tahun', 'site', 'perusahaan',
                'finding_ia', 'finding_verlap', 'ia', 'ipk', 'plan_okk',
                'okk_1', 'okk_2', 'okk_3', 'okk_layer_2', 'okk_layer_3', 'okk_layer_4',
            ]);

            return [
                'meta' => [
                    'generatedAt' => now()->timezone(config('app.timezone'))->format('d M Y H:i'),
                    'lastDataDate' => optional($rows->max('tanggal'))?->format('d M Y') ?? '-',
                    'filteredRows' => $rows->count(),
                ],
                'filters' => $normalized,
                'options' => $this->options(),
                'kpis' => $this->kpis($rows),
                'bySite' => $this->bySite($rows),
                'trend' => $this->trend($rows),
                'rankings' => [
                    'cancelSite' => $this->rankBy($rows, 'site', fn ($r) => $this->isCancel($r) ? 1 : 0),
                    'cancelCompany' => $this->rankBy($rows, 'perusahaan', fn ($r) => $this->isCancel($r) ? 1 : 0),
                    'findingIACompany' => $this->rankBy($rows, 'perusahaan', fn ($r) => (int) $r->finding_ia),
                ],
            ];
        });
    }

    /**
     * @param  array{year?:string,month?:string,week?:string,site?:string,company?:string,type?:string}  $filters
     * @return array<string, mixed>
     */
    public function complianceHeatmap(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $cacheKey = 'pnc_monitoring_ikk_heatmap_'.md5((string) json_encode($normalized));

        return Cache::remember($cacheKey, 45, function () use ($normalized): array {
            $rows = $this->baseQuery($normalized)->whereNotNull('tanggal')->get(['tanggal', 'ipk']);

            return $this->buildComplianceHeatmap($rows);
        });
    }

    /**
     * @param  Collection<int, PncMonitoringIkkRecord>  $rows
     * @return array<string, mixed>
     */
    private function buildComplianceHeatmap(Collection $rows): array
    {
        $dailyTotal = [];
        $dailyCompliant = [];
        foreach ($rows as $row) {
            if ($row->tanggal === null) {
                continue;
            }
            $key = $row->tanggal->format('Y-m-d');
            $dailyTotal[$key] = ($dailyTotal[$key] ?? 0) + 1;
            if ((int) $row->ipk === 1) {
                $dailyCompliant[$key] = ($dailyCompliant[$key] ?? 0) + 1;
            }
        }

        if ($dailyTotal === []) {
            return [
                'series' => [],
                'categories' => [],
                'peakDayLabel' => '-',
                'peakDayCount' => 0,
                'avgDaily' => 0,
                'overallComplianceRate' => null,
                'insight' => 'Belum ada data IKK untuk ditampilkan.',
            ];
        }

        ksort($dailyTotal);
        $dateKeys = array_keys($dailyTotal);
        $start = Carbon::parse($dateKeys[0])->startOfDay();
        $end = Carbon::parse($dateKeys[array_key_last($dateKeys)])->startOfDay();

        $weekdayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        $dowToName = [
            Carbon::MONDAY => 'Senin',
            Carbon::TUESDAY => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY => 'Kamis',
            Carbon::FRIDAY => 'Jumat',
            Carbon::SATURDAY => 'Sabtu',
            Carbon::SUNDAY => 'Minggu',
        ];

        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $weekLabels = [];
        $seriesData = [];
        foreach ($weekdayNames as $name) {
            $seriesData[$name] = [];
        }

        $peakCount = -1;
        $peakLabel = '-';
        $totalSum = 0;
        $compliantSum = 0;
        $daysWithData = 0;

        $cursor = $gridStart->copy();
        $weekIndex = -1;
        while ($cursor->lte($gridEnd)) {
            if ((int) $cursor->dayOfWeek === Carbon::MONDAY) {
                $weekIndex++;
                $weekLabels[] = $cursor->translatedFormat('d M');
                foreach ($weekdayNames as $name) {
                    $seriesData[$name][$weekIndex] = [
                        'x' => $weekLabels[$weekIndex],
                        'y' => null,
                        'date' => null,
                        'date_label' => null,
                        'total' => 0,
                        'compliant' => 0,
                        'empty' => true,
                    ];
                }
            }

            $rowName = $dowToName[(int) $cursor->dayOfWeek] ?? 'Senin';
            $key = $cursor->format('Y-m-d');
            $inRange = $cursor->betweenIncluded($start, $end);

            if ($inRange) {
                $total = $dailyTotal[$key] ?? 0;
                $compliant = $dailyCompliant[$key] ?? 0;
                $rate = $total > 0 ? round($compliant / $total * 100, 1) : null;

                $seriesData[$rowName][$weekIndex] = [
                    'x' => $weekLabels[$weekIndex],
                    'y' => $rate,
                    'date' => $key,
                    'date_label' => $cursor->translatedFormat('d M Y'),
                    'total' => $total,
                    'compliant' => $compliant,
                    'empty' => $total === 0,
                ];

                if ($total > 0) {
                    $totalSum += $total;
                    $compliantSum += $compliant;
                    $daysWithData++;
                    if ($total > $peakCount) {
                        $peakCount = $total;
                        $peakLabel = $cursor->translatedFormat('d M Y');
                    }
                }
            }

            $cursor->addDay();
        }

        $series = [];
        foreach ($weekdayNames as $name) {
            $series[] = [
                'name' => $name,
                'data' => array_values($seriesData[$name]),
            ];
        }

        $avgDaily = $daysWithData > 0 ? (int) round($totalSum / $daysWithData) : 0;
        $overallRate = $totalSum > 0 ? round($compliantSum / $totalSum * 100, 1) : null;

        $insight = 'Belum ada cukup data untuk insight kepatuhan IKK.';
        if ($overallRate !== null) {
            if ($overallRate >= 90) {
                $insight = "Kepatuhan IKK secara umum sangat baik ({$overallRate}%), hari terbanyak di {$peakLabel} dengan {$peakCount} IKK.";
            } elseif ($overallRate >= 70) {
                $insight = "Kepatuhan IKK cukup baik ({$overallRate}%), namun masih ada ruang perbaikan pada hari-hari tertentu.";
            } else {
                $insight = "Kepatuhan IKK masih rendah ({$overallRate}%) — perlu perhatian pada hari dengan warna merah/kuning.";
            }
        }

        return [
            'series' => $series,
            'categories' => $weekLabels,
            'peakDayLabel' => $peakLabel,
            'peakDayCount' => max(0, $peakCount),
            'avgDaily' => $avgDaily,
            'overallComplianceRate' => $overallRate,
            'insight' => $insight,
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, string>
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'year' => $this->allOrValue($filters['year'] ?? 'ALL'),
            'month' => $this->allOrValue($filters['month'] ?? 'ALL'),
            'week' => $this->allOrValue($filters['week'] ?? 'ALL'),
            'site' => $this->allOrValue($filters['site'] ?? 'ALL'),
            'company' => $this->allOrValue($filters['company'] ?? 'ALL'),
            'type' => $this->allOrValue($filters['type'] ?? 'ALL'),
        ];
    }

    private function allOrValue(mixed $value): string
    {
        $text = trim((string) $value);

        return $text === '' ? 'ALL' : $text;
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function baseQuery(array $filters): Builder
    {
        $query = PncMonitoringIkkRecord::query();
        if ($filters['year'] !== 'ALL') {
            $query->where('tahun', (int) $filters['year']);
        }
        if ($filters['month'] !== 'ALL') {
            $query->where('bulan', (int) $filters['month']);
        }
        if ($filters['week'] !== 'ALL') {
            $query->where('minggu', (int) $filters['week']);
        }
        if ($filters['site'] !== 'ALL') {
            $query->where('site', $filters['site']);
        }
        if ($filters['company'] !== 'ALL') {
            $query->where('perusahaan', $filters['company']);
        }
        if ($filters['type'] !== 'ALL') {
            $query->where('jenis', $filters['type']);
        }

        return $query;
    }

    /**
     * @return array<string, list<string|int>>
     */
    private function options(): array
    {
        return [
            'years' => PncMonitoringIkkRecord::query()->whereNotNull('tahun')->distinct()->orderByDesc('tahun')->pluck('tahun')->all(),
            'months' => PncMonitoringIkkRecord::query()->whereNotNull('bulan')->distinct()->orderBy('bulan')->pluck('bulan')->all(),
            'weeks' => PncMonitoringIkkRecord::query()->whereNotNull('minggu')->distinct()->orderBy('minggu')->pluck('minggu')->all(),
            'sites' => PncMonitoringIkkRecord::query()->whereNotNull('site')->where('site', '!=', '')->distinct()->orderBy('site')->pluck('site')->all(),
            'companies' => PncMonitoringIkkRecord::query()->whereNotNull('perusahaan')->where('perusahaan', '!=', '')->distinct()->orderBy('perusahaan')->pluck('perusahaan')->all(),
            'types' => PncMonitoringIkkRecord::query()->whereNotNull('jenis')->where('jenis', '!=', '')->distinct()->orderBy('jenis')->pluck('jenis')->all(),
        ];
    }

    /**
     * @param  Collection<int, PncMonitoringIkkRecord>  $rows
     * @return array<string, mixed>
     */
    public function kpis(Collection $rows): array
    {
        $ikkCount = $rows->count();
        $distinctIkk = $rows->pluck('nomor')->filter()->unique()->count();
        $cancelCount = $rows->filter(fn ($r) => $this->isCancel($r))->count();

        $ipkActual = $rows->filter(fn ($r) => (int) $r->ipk === 1)->count();
        $ipkDenominator = $rows->filter(fn ($r) => $r->ipk !== null && in_array((int) $r->ipk, [0, 1], true))->count();

        $iaRequired = $ikkCount;
        $iaActual = $rows->filter(fn ($r) => (int) $r->ia === 1)->count();
        $iaPenaltyCases = $rows->filter(fn ($r) => (int) $r->ia === 1 && (int) $r->finding_verlap > 0)->count();
        $iaEffective = max(0, $iaActual - $iaPenaltyCases);

        $planOkk = (int) $rows->sum('plan_okk');
        $okkAchieved = (int) $rows->sum(fn ($r) => (int) $r->okk_1 + (int) $r->okk_2 + (int) $r->okk_3);
        $layer2Required = $planOkk;
        $layer2Achieved = (int) $rows->sum(fn ($r) => (int) $r->okk_layer_2 + (int) $r->okk_layer_3 + (int) $r->okk_layer_4);

        return [
            'ikkCount' => $ikkCount,
            'distinctIkk' => $distinctIkk,
            'rowCount' => $ikkCount,
            'cancelCount' => $cancelCount,
            'ipkActual' => $ipkActual,
            'ipkDenominator' => $ipkDenominator,
            'ipkPerformance' => $this->ratio($ipkActual, $ipkDenominator),
            'iaRequired' => $iaRequired,
            'iaActual' => $iaActual,
            'iaEffective' => $iaEffective,
            'iaPenaltyCases' => $iaPenaltyCases,
            'iaCompliance' => $this->ratio($iaActual, $iaRequired),
            'iaPerformance' => $this->ratio($iaEffective, $iaRequired),
            'findingIA' => (int) $rows->sum('finding_ia'),
            'findingVerlap' => (int) $rows->sum('finding_verlap'),
            'okkPlan' => $planOkk,
            'okkAchieved' => $okkAchieved,
            'okkL1Performance' => $this->ratio($okkAchieved, $planOkk),
            'layer2Required' => $layer2Required,
            'layer2Achieved' => $layer2Achieved,
            'okkL2UpPerformance' => $this->ratio($layer2Achieved, $layer2Required),
        ];
    }

    /**
     * @param  Collection<int, PncMonitoringIkkRecord>  $rows
     * @return list<array<string, mixed>>
     */
    private function bySite(Collection $rows): array
    {
        return $rows->groupBy(fn ($r) => $r->site ?: 'Unknown')
            ->map(function (Collection $group, string $site): array {
                $kpis = $this->kpis($group);

                return [
                    'name' => $site,
                    'ikkCount' => $kpis['ikkCount'],
                    'cancelCount' => $kpis['cancelCount'],
                    'ipkPerformance' => $kpis['ipkPerformance'],
                    'ipkActual' => $kpis['ipkActual'],
                    'ipkDenominator' => $kpis['ipkDenominator'],
                    'iaCompliance' => $kpis['iaCompliance'],
                    'iaPerformance' => $kpis['iaPerformance'],
                    'iaEffective' => $kpis['iaEffective'],
                    'iaRequired' => $kpis['iaRequired'],
                    'iaPenaltyCases' => $kpis['iaPenaltyCases'],
                    'findingIA' => $kpis['findingIA'],
                    'findingVerlap' => $kpis['findingVerlap'],
                    'okkL1Performance' => $kpis['okkL1Performance'],
                    'okkL2UpPerformance' => $kpis['okkL2UpPerformance'],
                    'okkAchieved' => $kpis['okkAchieved'],
                    'okkPlan' => $kpis['okkPlan'],
                    'layer2Achieved' => $kpis['layer2Achieved'],
                    'layer2Required' => $kpis['layer2Required'],
                ];
            })
            ->sortByDesc('ikkCount')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PncMonitoringIkkRecord>  $rows
     * @return list<array<string, mixed>>
     */
    private function trend(Collection $rows): array
    {
        return $rows->groupBy(function ($r): string {
            $y = $r->tahun ?: '-';
            $w = $r->minggu ?: '-';

            return 'Y'.$y.'-W'.$w;
        })->map(function (Collection $group, string $period): array {
            $kpis = $this->kpis($group);

            return [
                'period' => $period,
                'ikkCount' => $kpis['ikkCount'],
                'ipkPerformance' => $kpis['ipkPerformance'],
            ];
        })->sortKeys()->values()->all();
    }

    /**
     * @param  Collection<int, PncMonitoringIkkRecord>  $rows
     * @param  callable(PncMonitoringIkkRecord): int  $valueFn
     * @return list<array{name:string,value:int}>
     */
    private function rankBy(Collection $rows, string $field, callable $valueFn): array
    {
        return $rows->groupBy(fn ($r) => $r->{$field} ?: 'Unknown')
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'value' => (int) $group->sum(fn ($r) => $valueFn($r)),
            ])
            ->sortByDesc('value')
            ->take(10)
            ->values()
            ->all();
    }

    private function isCancel(PncMonitoringIkkRecord $row): bool
    {
        return $row->ipk === null || (int) $row->ipk === 0;
    }

    private function ratio(int $num, int $den): ?float
    {
        if ($den <= 0) {
            return null;
        }

        return $num / $den;
    }
}
