<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering\Concerns;

trait BuildsMonitoringSafetyEngineeringItems
{
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
