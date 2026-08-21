<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard;

use App\Models\OhsDashboard\Employee;
use App\Models\OhsDashboard\Event;
use App\Models\OhsDashboard\LeaveRequest;
use App\Models\OhsDashboard\LeaveType;
use App\Models\OhsDashboard\ProjectIssueTracker;

final class InitService
{
    public function __construct(private readonly OhsDashboardSupport $support) {}

    /**
     * @return array<string, mixed>
     */
    public function getInit(): array
    {
        $currentYear = (int) $this->support->today()->year;
        $years = [$currentYear - 1, $currentYear];

        foreach (LeaveRequest::query()->select(['start_date', 'end_date'])->cursor() as $leave) {
            $years[] = (int) $leave->start_date?->year;
            $years[] = (int) $leave->end_date?->year;
        }
        foreach (Event::query()->select(['event_date'])->cursor() as $event) {
            $years[] = (int) $event->event_date?->year;
        }
        foreach (ProjectIssueTracker::query()->select(['start_date', 'due_date'])->cursor() as $tracker) {
            $years[] = (int) $tracker->start_date?->year;
            $years[] = (int) $tracker->due_date?->year;
        }

        $years = array_values(array_unique(array_filter($years, fn ($year): bool => $year > 1990 && $year < 2100)));
        sort($years);

        $teams = Employee::query()
            ->whereNotNull('team')
            ->where('team', '!=', '')
            ->distinct()
            ->orderBy('team')
            ->pluck('team')
            ->values()
            ->all();

        $sites = Employee::query()
            ->whereNotNull('site_dedicated')
            ->where('site_dedicated', '!=', '')
            ->distinct()
            ->orderBy('site_dedicated')
            ->pluck('site_dedicated')
            ->values()
            ->all();

        return [
            'employeeCount' => Employee::query()->count(),
            'leaveTypes' => LeaveType::query()->orderBy('leave_type')->get()->map->toApiArray()->all(),
            'teams' => $teams,
            'sites' => $sites,
            'years' => $years,
            'currentYear' => $currentYear,
            'holidays' => $this->support->holidayMap(),
            'todayISO' => $this->support->todayISO(),
        ];
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
                $builder->where('emp_name', 'like', $like)
                    ->orWhere('emp_id', 'like', $like)
                    ->orWhere('company', 'like', $like)
                    ->orWhere('team', 'like', $like);
            })
            ->orderBy('emp_name')
            ->limit($limit)
            ->get()
            ->map(fn (Employee $employee): array => $employee->toApiArray())
            ->all();
    }
}
