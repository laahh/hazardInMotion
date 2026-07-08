<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering\Concerns;

use App\Models\MonitoringSafetyEngineeringRecord;
use Carbon\Carbon;

trait BuildsMonitoringSafetyEngineeringItems
{
    /**
     * @return array{start: string, end: string}|null
     */
    private function resolveReviewWeekRange(array $filters): ?array
    {
        if (! preg_match('/^W(\d{1,2})$/', (string) ($filters['review_week'] ?? ''), $matches)) {
            return null;
        }

        $week = max(1, min(53, (int) $matches[1]));
        $year = ($filters['period_year'] ?? 0) > 0 ? (int) $filters['period_year'] : (int) now()->year;
        $start = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $end = Carbon::now()->setISODate($year, $week)->endOfWeek();

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ];
    }

    private function recordHasDueDateInReviewWeek(MonitoringSafetyEngineeringRecord $record, array $filters): bool
    {
        $range = $this->resolveReviewWeekRange($filters);

        if ($range === null) {
            return false;
        }

        $dueDates = array_filter([
            $record->replikasi_due_date,
            $record->kajian_teknis_due_date,
            $record->pengadaan_due_date,
            $record->uji_coba_due_date,
            $record->standardisasi_due_date,
        ]);

        foreach ($dueDates as $dueDate) {
            if ($dueDate === null) {
                continue;
            }

            $date = $dueDate->toDateString();

            if ($date >= $range['start'] && $date <= $range['end']) {
                return true;
            }
        }

        return false;
    }
    /**
     * @return array<string, mixed>
     */
    private function item(
        string $name,
        string $unit,
        int $plan,
        int $done,
        string $dueDate,
        int $overdue,
    ): array {
        $percentage = $plan > 0 ? (int) round(($done / $plan) * 100) : 0;

        return [
            'name' => $name,
            'unit' => $unit,
            'plan' => $plan,
            'done' => $done,
            'percentage' => $percentage,
            'percentage_color' => $this->percentageColor($percentage),
            'due_date' => $dueDate,
            'due_date_label' => $dueDate !== '' ? date('d M Y', strtotime($dueDate)) : '-',
            'overdue' => $overdue,
        ];
    }

    private function percentageColor(int $percentage): string
    {
        return match (true) {
            $percentage >= 100 => 'green',
            $percentage >= 50 => 'amber',
            $percentage > 0 => 'orange',
            default => 'red',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function calculateOverallProgress(array $items): int
    {
        if ($items === []) {
            return 0;
        }

        $totalPlan = array_sum(array_column($items, 'plan'));
        $totalDone = array_sum(array_column($items, 'done'));

        if ($totalPlan === 0) {
            return 0;
        }

        return (int) round(($totalDone / $totalPlan) * 100);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function sumOverdue(array $items): int
    {
        return (int) array_sum(array_column($items, 'overdue'));
    }
}
