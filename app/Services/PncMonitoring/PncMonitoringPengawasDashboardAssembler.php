<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringCommissioning;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class PncMonitoringPengawasDashboardAssembler
{
    /**
     * @param  array{year?:string,week?:string,site?:string,company?:string,detail?:string}  $filters
     * @return array<string, mixed>
     */
    public function assemble(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $cacheKey = 'pnc_monitoring_pengawas_dash_'.md5((string) json_encode($normalized));

        return Cache::remember($cacheKey, 45, function () use ($normalized): array {
            $rows = $this->baseQuery($normalized)->get([
                'id', 'site', 'no_register_spip', 'detail_jenis_spip', 'nama_pengawas_teknis',
                'week', 'tahun', 'pemilik_spip', 'temuan_komisioning', 'alasan_reject', 'status',
            ]);

            $supervisorStats = $this->supervisorStats($rows);

            return [
                'meta' => [
                    'generatedAt' => now()->timezone(config('app.timezone'))->format('d M Y H:i'),
                    'filteredRows' => $rows->count(),
                ],
                'filters' => $normalized,
                'options' => $this->options(),
                'kpis' => $this->kpis($rows, $supervisorStats),
                'bySite' => $this->bySite($rows),
                'top10' => $supervisorStats->sortByDesc('performance')->take(10)->values()->all(),
                'bottom10' => $supervisorStats->sortBy('performance')->take(10)->values()->all(),
                'weeklyTrend' => $this->weeklyTrend($rows),
                'rejectReasons' => $this->rejectReasons($rows),
            ];
        });
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, string>
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'year' => $this->allOrValue($filters['year'] ?? 'ALL'),
            'week' => $this->allOrValue($filters['week'] ?? 'ALL'),
            'site' => $this->allOrValue($filters['site'] ?? 'ALL'),
            'company' => $this->allOrValue($filters['company'] ?? 'ALL'),
            'detail' => $this->allOrValue($filters['detail'] ?? 'ALL'),
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
        $query = PncMonitoringCommissioning::query();
        if ($filters['year'] !== 'ALL') {
            $query->where('tahun', (int) $filters['year']);
        }
        if ($filters['week'] !== 'ALL') {
            $query->where('week', (int) $filters['week']);
        }
        if ($filters['site'] !== 'ALL') {
            $query->where('site', $filters['site']);
        }
        if ($filters['company'] !== 'ALL') {
            $query->where('pemilik_spip', $filters['company']);
        }
        if ($filters['detail'] !== 'ALL') {
            $query->where('detail_jenis_spip', $filters['detail']);
        }

        return $query;
    }

    /**
     * @return array<string, list<string|int>>
     */
    private function options(): array
    {
        return [
            'years' => PncMonitoringCommissioning::query()->whereNotNull('tahun')->distinct()->orderByDesc('tahun')->pluck('tahun')->all(),
            'weeks' => PncMonitoringCommissioning::query()->whereNotNull('week')->distinct()->orderBy('week')->pluck('week')->all(),
            'sites' => PncMonitoringCommissioning::query()->whereNotNull('site')->where('site', '!=', '')->distinct()->orderBy('site')->pluck('site')->all(),
            'companies' => PncMonitoringCommissioning::query()->whereNotNull('pemilik_spip')->where('pemilik_spip', '!=', '')->distinct()->orderBy('pemilik_spip')->pluck('pemilik_spip')->all(),
            'details' => PncMonitoringCommissioning::query()->whereNotNull('detail_jenis_spip')->where('detail_jenis_spip', '!=', '')->distinct()->orderBy('detail_jenis_spip')->pluck('detail_jenis_spip')->all(),
        ];
    }

    /**
     * @param  Collection<int, PncMonitoringCommissioning>  $rows
     * @param  Collection<int, array<string, mixed>>  $supervisorStats
     * @return array<string, mixed>
     */
    public function kpis(Collection $rows, ?Collection $supervisorStats = null): array
    {
        $supervisorStats ??= $this->supervisorStats($rows);
        $totalUnits = $rows->count();
        $releaseCount = $rows->filter(fn ($r) => $this->isRelease($r->status))->count();
        $rejectCount = $rows->filter(fn ($r) => $this->isReject($r->status))->count();
        $status1 = $rows->filter(fn ($r) => (int) $r->temuan_komisioning === 0)->count();
        $status0 = $rows->filter(fn ($r) => (int) $r->temuan_komisioning > 0)->count();
        $measured = $status0 + $status1;
        $totalFindings = (int) $rows->sum('temuan_komisioning');

        return [
            'totalUnits' => $totalUnits,
            'releaseCount' => $releaseCount,
            'rejectCount' => $rejectCount,
            'performance' => $this->ratio($status1, $measured),
            'totalFindings' => $totalFindings,
            'totalSupervisors' => $supervisorStats->count(),
            'performSupervisors' => $supervisorStats->filter(fn ($s) => (float) $s['performance'] >= 0.999999)->count(),
            'notPerformSupervisors' => $supervisorStats->filter(fn ($s) => (int) $s['unitsWithFindings'] > 0)->count(),
            'status1' => $status1,
            'status0' => $status0,
        ];
    }

    /**
     * @param  Collection<int, PncMonitoringCommissioning>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function supervisorStats(Collection $rows): Collection
    {
        return $rows->groupBy(fn ($r) => $r->nama_pengawas_teknis ?: 'Unknown')
            ->map(function (Collection $group, string $name): array {
                $units = $group->count();
                $withFindings = $group->filter(fn ($r) => (int) $r->temuan_komisioning > 0)->count();
                $without = $units - $withFindings;
                $findings = (int) $group->sum('temuan_komisioning');

                return [
                    'name' => $name,
                    'company' => $group->pluck('pemilik_spip')->filter()->first() ?: '-',
                    'site' => $group->pluck('site')->filter()->unique()->implode(', ') ?: '-',
                    'units' => $units,
                    'unitsWithFindings' => $withFindings,
                    'unitsWithoutFindings' => $without,
                    'findings' => $findings,
                    'performance' => $this->ratio($without, $units) ?? 0.0,
                ];
            })
            ->values()
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;

                return $row;
            });
    }

    /**
     * @param  Collection<int, PncMonitoringCommissioning>  $rows
     * @return list<array<string, mixed>>
     */
    private function bySite(Collection $rows): array
    {
        return $rows->groupBy(fn ($r) => $r->site ?: 'Unknown')
            ->map(function (Collection $group, string $site): array {
                $units = $group->count();
                $without = $group->filter(fn ($r) => (int) $r->temuan_komisioning === 0)->count();
                $release = $group->filter(fn ($r) => $this->isRelease($r->status))->count();
                $reject = $group->filter(fn ($r) => $this->isReject($r->status))->count();

                return [
                    'site' => $site,
                    'units' => $units,
                    'measured' => $units,
                    'performance' => $this->ratio($without, $units) ?? 0.0,
                    'release' => $release,
                    'reject' => $reject,
                ];
            })
            ->sortByDesc('units')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PncMonitoringCommissioning>  $rows
     * @return list<array<string, mixed>>
     */
    private function weeklyTrend(Collection $rows): array
    {
        return $rows->groupBy(fn ($r) => 'Y'.($r->tahun ?: '-').'-W'.($r->week ?: '-'))
            ->map(function (Collection $group, string $week): array {
                $units = $group->count();
                $without = $group->filter(fn ($r) => (int) $r->temuan_komisioning === 0)->count();

                return [
                    'week' => $week,
                    'units' => $units,
                    'performance' => $this->ratio($without, $units) ?? 0.0,
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PncMonitoringCommissioning>  $rows
     * @return list<array{label:string,value:int}>
     */
    private function rejectReasons(Collection $rows): array
    {
        return $rows->filter(fn ($r) => $this->isReject($r->status))
            ->groupBy(fn ($r) => $r->alasan_reject ?: 'Tidak diisi')
            ->map(fn (Collection $group, string $label): array => [
                'label' => $label,
                'value' => $group->count(),
            ])
            ->sortByDesc('value')
            ->take(8)
            ->values()
            ->all();
    }

    private function isRelease(?string $status): bool
    {
        $s = mb_strtolower(trim((string) $status));

        return str_contains($s, 'release');
    }

    private function isReject(?string $status): bool
    {
        $s = mb_strtolower(trim((string) $status));

        return str_contains($s, 'reject');
    }

    private function ratio(int $num, int $den): ?float
    {
        if ($den <= 0) {
            return null;
        }

        return $num / $den;
    }
}
