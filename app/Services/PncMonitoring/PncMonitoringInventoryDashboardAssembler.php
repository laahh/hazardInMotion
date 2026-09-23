<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryCategory;
use App\Models\PncMonitoring\PncMonitoringInventoryToolAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class PncMonitoringInventoryDashboardAssembler
{
    /**
     * @param  array{category?:string,site?:string,status?:string}  $filters
     * @return array<string, mixed>
     */
    public function assemble(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $cacheKey = 'pnc_monitoring_inventory_dash_'.md5((string) json_encode($normalized));

        return Cache::remember($cacheKey, 45, function () use ($normalized): array {
            $rows = $this->baseQuery($normalized)
                ->with(['toolMaster.category', 'latestCalibration', 'latestInspection'])
                ->get();

            return [
                'meta' => [
                    'generatedAt' => now()->timezone(config('app.timezone'))->format('d M Y H:i'),
                    'filteredRows' => $rows->count(),
                ],
                'filters' => $normalized,
                'options' => $this->options(),
                'kpis' => $this->kpis($rows),
                'byCategory' => $this->countBy($rows, fn (PncMonitoringInventoryToolAsset $r) => $r->toolMaster?->category?->name),
                'byStatus' => $this->countBy($rows, fn (PncMonitoringInventoryToolAsset $r) => $r->status_availability),
                'byCondition' => $this->countBy($rows, fn (PncMonitoringInventoryToolAsset $r) => $r->condition),
                'bySite' => $this->countBy($rows, fn (PncMonitoringInventoryToolAsset $r) => $r->location_detail),
                'dueSoon' => $this->dueSoon($rows),
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
            'category' => $this->allOrValue($filters['category'] ?? 'ALL'),
            'site' => $this->allOrValue($filters['site'] ?? 'ALL'),
            'status' => $this->allOrValue($filters['status'] ?? 'ALL'),
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
        $query = PncMonitoringInventoryToolAsset::query();
        if ($filters['category'] !== 'ALL') {
            $query->whereHas('toolMaster.category', fn ($q) => $q->where('name', $filters['category']));
        }
        if ($filters['site'] !== 'ALL') {
            $query->where('location_detail', $filters['site']);
        }
        if ($filters['status'] !== 'ALL') {
            $query->where('status_availability', $filters['status']);
        }

        return $query;
    }

    /**
     * @return array<string, list<string>>
     */
    private function options(): array
    {
        return [
            'categories' => PncMonitoringInventoryCategory::query()->orderBy('name')->pluck('name')->all(),
            'statuses' => PncMonitoringInventoryToolAsset::STATUSES,
            'sites' => PncMonitoringInventoryToolAsset::query()
                ->whereNotNull('location_detail')->where('location_detail', '!=', '')
                ->distinct()->orderBy('location_detail')->pluck('location_detail')->all(),
        ];
    }

    /**
     * @param  Collection<int, PncMonitoringInventoryToolAsset>  $rows
     * @return array<string, mixed>
     */
    private function kpis(Collection $rows): array
    {
        $today = Carbon::today();
        $horizon = $today->copy()->addDays(30);

        $calibDue = fn (PncMonitoringInventoryToolAsset $r) => $r->latestCalibration?->next_due_date;
        $inspDue = fn (PncMonitoringInventoryToolAsset $r) => $r->latestInspection?->next_due_date;

        return [
            'totalTools' => $rows->count(),
            'totalQty' => $rows->count(),
            'available' => $rows->filter(fn ($r) => $r->status_availability === 'Available')->count(),
            'checkedOut' => $rows->filter(fn ($r) => $r->status_availability === 'Checked-out')->count(),
            'inRepair' => $rows->filter(fn ($r) => $r->status_availability === 'In Repair')->count(),
            'scrapped' => $rows->filter(fn ($r) => $r->status_availability === 'Scrapped')->count(),
            'damaged' => $rows->filter(fn ($r) => $r->condition === 'Damaged')->count(),
            'calibrationOverdue' => $rows->filter(fn ($r) => $calibDue($r) !== null && $calibDue($r)->lt($today))->count(),
            'calibrationDueSoon' => $rows->filter(fn ($r) => $calibDue($r) !== null && $calibDue($r)->between($today, $horizon))->count(),
            'inspectionOverdue' => $rows->filter(fn ($r) => $inspDue($r) !== null && $inspDue($r)->lt($today))->count(),
            'inspectionDueSoon' => $rows->filter(fn ($r) => $inspDue($r) !== null && $inspDue($r)->between($today, $horizon))->count(),
        ];
    }

    /**
     * @param  Collection<int, PncMonitoringInventoryToolAsset>  $rows
     * @return list<array{name:string,value:int}>
     */
    private function countBy(Collection $rows, \Closure $resolver): array
    {
        return $rows->groupBy(fn ($r) => $resolver($r) ?: 'Tidak diisi')
            ->map(fn (Collection $group, string $name): array => ['name' => $name, 'value' => $group->count()])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PncMonitoringInventoryToolAsset>  $rows
     * @return list<array<string, mixed>>
     */
    private function dueSoon(Collection $rows): array
    {
        $today = Carbon::today();
        $horizon = $today->copy()->addDays(30);

        return $rows
            ->map(function (PncMonitoringInventoryToolAsset $r) {
                $dueDates = array_filter([
                    $r->latestCalibration?->next_due_date,
                    $r->latestInspection?->next_due_date,
                ]);
                $earliest = collect($dueDates)->sort()->first();

                return $earliest === null ? null : [
                    'name' => $r->toolMaster?->standard_name,
                    'assetId' => $r->inventory_id,
                    'category' => $r->toolMaster?->category?->name,
                    'site' => $r->location_detail,
                    'dueDateRaw' => $earliest,
                    'dueDate' => $earliest->format('d M Y'),
                    'isOverdue' => $earliest->lt($today),
                ];
            })
            ->filter(fn ($r) => $r !== null && $r['dueDateRaw']->lte($horizon))
            ->sortBy('dueDateRaw')
            ->take(20)
            ->map(fn ($r) => collect($r)->except('dueDateRaw')->all())
            ->values()
            ->all();
    }
}
