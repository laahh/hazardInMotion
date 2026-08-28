<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Enums\MonitoringSafetyEngineeringPhaseStatus;
use App\Enums\MonitoringSafetyEngineeringStatusCompliance;
use App\Models\MonitoringSafetyEngineeringPhaseStatusLog;
use App\Models\MonitoringSafetyEngineeringRecord;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class MonitoringSafetyEngineeringPhaseStatusLogService
{
    private ?bool $tracingReadyCache = null;

    public function isTracingReady(): bool
    {
        if ($this->tracingReadyCache !== null) {
            return $this->tracingReadyCache;
        }

        $this->tracingReadyCache = Schema::hasTable('monitoring_safety_engineering_phase_status_logs')
            && Schema::hasColumn('monitoring_safety_engineering_records', 'kajian_teknis_status_compliance');

        return $this->tracingReadyCache;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload: array<string, mixed>, logs_created: int}
     */
    public function applyForUpdate(MonitoringSafetyEngineeringRecord $record, array $payload, ?int $userId): array
    {
        if (! $this->isTracingReady()) {
            return ['payload' => $payload, 'logs_created' => 0];
        }

        $changedAt = now();
        $logsCreated = 0;

        foreach ($this->phaseDefinitions() as $phaseKey => $definition) {
            $statusField = $definition['status'];
            $dueField = $definition['due'];
            $changedAtField = $definition['changed_at'];
            $complianceField = $definition['compliance'];

            $newStatus = array_key_exists($statusField, $payload)
                ? (string) ($payload[$statusField] ?? MonitoringSafetyEngineeringPhaseStatus::NotYet->value)
                : null;

            if ($newStatus === null) {
                continue;
            }

            $oldStatus = $this->resolveStatusValue($record->getAttribute($statusField));

            if ($oldStatus === $newStatus) {
                continue;
            }

            $dueDate = $payload[$dueField] ?? $this->formatDateValue($record->getAttribute($dueField));
            $compliance = $this->resolveCompliance($dueDate, $changedAt);

            $payload[$changedAtField] = $changedAt;
            $payload[$complianceField] = $compliance->value;

            MonitoringSafetyEngineeringPhaseStatusLog::query()->create([
                'record_id' => $record->id,
                'phase' => $phaseKey,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'due_date' => $dueDate,
                'changed_at' => $changedAt,
                'changed_by' => $userId,
                'compliance' => $compliance->value,
                'period_year' => (int) ($payload['period_year'] ?? $record->period_year),
                'review_week' => $this->reviewWeek($changedAt),
            ]);

            $logsCreated++;
        }

        return [
            'payload' => $payload,
            'logs_created' => $logsCreated,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function writeInitialLogs(MonitoringSafetyEngineeringRecord $record, array $payload, ?int $userId): int
    {
        if (! $this->isTracingReady()) {
            return 0;
        }

        $changedAt = now();
        $snapshots = [];
        $logsCreated = 0;

        foreach ($this->phaseDefinitions() as $phaseKey => $definition) {
            $statusField = $definition['status'];
            $dueField = $definition['due'];
            $changedAtField = $definition['changed_at'];
            $complianceField = $definition['compliance'];

            $newStatus = (string) ($payload[$statusField] ?? MonitoringSafetyEngineeringPhaseStatus::NotYet->value);

            if ($newStatus === MonitoringSafetyEngineeringPhaseStatus::NotYet->value) {
                continue;
            }

            $dueDate = $payload[$dueField] ?? null;
            $compliance = $this->resolveCompliance(is_string($dueDate) ? $dueDate : $this->formatDateValue($dueDate), $changedAt);

            MonitoringSafetyEngineeringPhaseStatusLog::query()->create([
                'record_id' => $record->id,
                'phase' => $phaseKey,
                'old_status' => null,
                'new_status' => $newStatus,
                'due_date' => is_string($dueDate) ? $dueDate : $this->formatDateValue($dueDate),
                'changed_at' => $changedAt,
                'changed_by' => $userId,
                'compliance' => $compliance->value,
                'period_year' => (int) ($payload['period_year'] ?? $record->period_year),
                'review_week' => $this->reviewWeek($changedAt),
            ]);

            $snapshots[$changedAtField] = $changedAt;
            $snapshots[$complianceField] = $compliance->value;
            $logsCreated++;
        }

        if ($snapshots !== []) {
            $record->update($snapshots);
        }

        return $logsCreated;
    }

    public function resolveCompliance(?string $dueDate, CarbonInterface $changedAt): MonitoringSafetyEngineeringStatusCompliance
    {
        if ($dueDate === null || $dueDate === '') {
            return MonitoringSafetyEngineeringStatusCompliance::NoDueDate;
        }

        $due = Carbon::parse($dueDate)->startOfDay();
        $changed = Carbon::parse($changedAt)->startOfDay();

        return $changed->lte($due)
            ? MonitoringSafetyEngineeringStatusCompliance::OnTarget
            : MonitoringSafetyEngineeringStatusCompliance::Overdue;
    }

    public function reviewWeek(CarbonInterface $date): string
    {
        return $date->format('o-\WW');
    }

    /**
     * @return array<string, array{label: string, status: string, due: string, changed_at: string, compliance: string}>
     */
    public function phaseDefinitions(): array
    {
        return config('monitoring_safety_engineering.trace_phases', []);
    }

    private function resolveStatusValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return (string) $value;
    }

    private function formatDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
