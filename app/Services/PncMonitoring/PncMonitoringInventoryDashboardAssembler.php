<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryTool;
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
            $rows = $this->baseQuery($normalized)->get([
                'id', 'category', 'site', 'status_ketersediaan', 'condition', 'qty_on_hand',
                'calibration_required', 'calibration_due_date',
                'inspection_required', 'next_inspection_due',
                'pm_required', 'next_pm_due',
            ]);

            return [
                'meta' => [
                    'generatedAt' => now()->timezone(config('app.timezone'))->format('d M Y H:i'),
                    'filteredRows' => $rows->count(),
                ],
                'filters' => $normalized,
                'options' => $this->options(),
                'kpis' => $this->kpis($rows),
                'byCategory' => $this->countBy($rows, 'category'),
                'byStatus' => $this->countBy($rows, 'status_ketersediaan'),
                'byCondition' => $this->countBy($rows, 'condition'),
                'bySite' => $this->countBy($rows, 'site'),
                'dueSoon' => $this->dueSoon($normalized),
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
        $query = PncMonitoringInventoryTool::query();
        if ($filters['category'] !== 'ALL') {
            $query->where('category', $filters['category']);
        }
        if ($filters['site'] !== 'ALL') {
            $query->where('site', $filters['site']);
        }
        if ($filters['status'] !== 'ALL') {
            $query->where('status_ketersediaan', $filters['status']);
        }

        return $query;
    }

    /**
     * @return array<string, list<string>>
     */
    private function options(): array
    {
        return [
            'categories' => PncMonitoringInventoryTool::CATEGORIES,
            'statuses' => PncMonitoringInventoryTool::STATUSES,
            'sites' => PncMonitoringInventoryTool::query()->whereNotNull('site')->where('site', '!=', '')->distinct()->orderBy('site')->pluck('site')->all(),
        ];
    }

    /**
     * @param  Collection<int, PncMonitoringInventoryTool>  $rows
     * @return array<string, mixed>
     */
    public function kpis(Collection $rows): array
    {
        $today = Carbon::today();

        return [
            'totalTools' => $rows->count(),
            'totalQty' => (int) $rows->sum('qty_on_hand'),
            'available' => $rows->filter(fn ($r) => $r->status_ketersediaan === 'Available')->count(),
            'checkedOut' => $rows->filter(fn ($r) => $r->status_ketersediaan === 'Checked-out')->count(),
            'inRepair' => $rows->filter(fn ($r) => $r->status_ketersediaan === 'In Repair')->count(),
            'scrapped' => $rows->filter(fn ($r) => $r->status_ketersediaan === 'Scrapped')->count(),
            'damaged' => $rows->filter(fn ($r) => $r->condition === 'Damaged')->count(),
            'calibrationOverdue' => $rows->filter(fn ($r) => $r->calibration_required && $r->calibration_due_date !== null && $r->calibration_due_date->lt($today))->count(),
            'calibrationDueSoon' => $rows->filter(fn ($r) => $r->calibration_required && $r->calibration_due_date !== null && $r->calibration_due_date->between($today, $today->copy()->addDays(30)))->count(),
            'inspectionOverdue' => $rows->filter(fn ($r) => $r->inspection_required && $r->next_inspection_due !== null && $r->next_inspection_due->lt($today))->count(),
            'inspectionDueSoon' => $rows->filter(fn ($r) => $r->inspection_required && $r->next_inspection_due !== null && $r->next_inspection_due->between($today, $today->copy()->addDays(30)))->count(),
            'pmOverdue' => $rows->filter(fn ($r) => $r->pm_required && $r->next_pm_due !== null && $r->next_pm_due->lt($today))->count(),
            'pmDueSoon' => $rows->filter(fn ($r) => $r->pm_required && $r->next_pm_due !== null && $r->next_pm_due->between($today, $today->copy()->addDays(30)))->count(),
        ];
    }

    /**
     * @param  Collection<int, PncMonitoringInventoryTool>  $rows
     * @return list<array{name:string,value:int}>
     */
    private function countBy(Collection $rows, string $field): array
    {
        return $rows->groupBy(fn ($r) => $r->{$field} ?: 'Tidak diisi')
            ->map(fn (Collection $group, string $name): array => ['name' => $name, 'value' => $group->count()])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $filters
     * @return list<array<string, mixed>>
     */
    private function dueSoon(array $filters): array
    {
        $today = Carbon::today();
        $horizon = $today->copy()->addDays(30);

        $rows = $this->baseQuery($filters)
            ->where(function (Builder $q) use ($horizon): void {
                $q->where(function (Builder $qq) use ($horizon): void {
                    $qq->where('calibration_required', true)->whereNotNull('calibration_due_date')->where('calibration_due_date', '<=', $horizon);
                })->orWhere(function (Builder $qq) use ($horizon): void {
                    $qq->where('inspection_required', true)->whereNotNull('next_inspection_due')->where('next_inspection_due', '<=', $horizon);
                })->orWhere(function (Builder $qq) use ($horizon): void {
                    $qq->where('pm_required', true)->whereNotNull('next_pm_due')->where('next_pm_due', '<=', $horizon);
                });
            })
            ->get(['id', 'nama_alat', 'asset_id', 'category', 'site', 'calibration_due_date', 'next_inspection_due', 'next_pm_due']);

        return $rows->map(function (PncMonitoringInventoryTool $row) use ($today): array {
            $dueDates = array_filter([
                $row->calibration_due_date,
                $row->next_inspection_due,
                $row->next_pm_due,
            ]);
            $earliest = collect($dueDates)->sort()->first();

            return [
                'name' => $row->nama_alat,
                'assetId' => $row->asset_id,
                'category' => $row->category,
                'site' => $row->site,
                'dueDate' => $earliest?->format('d M Y'),
                'isOverdue' => $earliest !== null && $earliest->lt($today),
            ];
        })->sortBy('dueDate')->take(20)->values()->all();
    }
}
