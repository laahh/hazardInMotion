<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard;

use App\Exceptions\OhsDashboard\OhsDashboardException;
use App\Models\OhsDashboard\Employee;
use App\Models\OhsDashboard\LeaveType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

final class InitService
{
    public const CACHE_KEY = 'ohs-dashboard.init';

    public function __construct(private readonly OhsDashboardSupport $support) {}

    /**
     * @return array<string, mixed>
     */
    public function getInit(): array
    {
        try {
            $cached = Cache::remember(self::CACHE_KEY, 180, function (): array {
                $currentYear = (int) $this->support->today()->year;

                return [
                    'employeeCount' => Employee::query()->count(),
                    'leaveTypes' => LeaveType::query()->orderBy('leave_type')->get()->map->toApiArray()->all(),
                    'teams' => Employee::query()
                        ->whereNotNull('team')
                        ->where('team', '!=', '')
                        ->distinct()
                        ->orderBy('team')
                        ->pluck('team')
                        ->values()
                        ->all(),
                    'sites' => Employee::query()
                        ->whereNotNull('site_dedicated')
                        ->where('site_dedicated', '!=', '')
                        ->distinct()
                        ->orderBy('site_dedicated')
                        ->pluck('site_dedicated')
                        ->values()
                        ->all(),
                    'years' => [$currentYear - 1, $currentYear],
                    'currentYear' => $currentYear,
                ];
            });
        } catch (QueryException $e) {
            throw new OhsDashboardException(
                'Tabel OHS Dashboard belum siap atau database lambat merespons. Pastikan migrasi ohs_dashboard sudah dijalankan.',
                503,
            );
        }

        $cached['todayISO'] = $this->support->todayISO();
        $cached['currentYear'] = (int) $this->support->today()->year;
        if (! in_array($cached['currentYear'], $cached['years'], true)) {
            $cached['years'][] = $cached['currentYear'];
            sort($cached['years']);
        }

        return $cached;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchEmployees(string $query, int $limit): array
    {
        $query = trim($query);
        $limit = $this->support->clampInteger($limit, 1, 50, 20);
        if ($query === '') {
            return [];
        }

        $like = '%'.$query.'%';

        return Employee::query()
            ->where(function ($builder) use ($like): void {
                $builder->where('emp_name', 'ilike', $like)
                    ->orWhere('emp_id', 'ilike', $like)
                    ->orWhere('company', 'ilike', $like)
                    ->orWhere('team', 'ilike', $like);
            })
            ->orderBy('emp_name')
            ->limit($limit)
            ->get()
            ->map(fn (Employee $employee): array => $employee->toApiArray())
            ->all();
    }
}
