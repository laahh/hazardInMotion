<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringRecord;
use App\Models\MonitoringSafetyEngineeringRecordChangeLog;
use App\Support\MonitoringSafetyEngineering\MonitoringSafetyEngineeringRecordGridDefinition;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class MonitoringSafetyEngineeringRecordChangeLogService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function logCreate(MonitoringSafetyEngineeringRecord $record, array $payload, ?int $userId): int
    {
        $changedAt = now();
        $batch = (string) Str::uuid();
        $rows = [];

        foreach ($this->trackedFields() as $field => $label) {
            $newValue = $this->normalizeComparable($field, $payload[$field] ?? null);

            if ($newValue === '') {
                continue;
            }

            $rows[] = $this->buildRow(
                recordId: $record->id,
                batch: $batch,
                action: 'created',
                field: $field,
                label: $label,
                oldValue: null,
                newValue: $this->formatDisplay($field, $payload[$field] ?? null),
                changedAt: $changedAt,
                userId: $userId,
                periodYear: (int) ($payload['period_year'] ?? $record->period_year),
            );
        }

        return $this->insertLogRows($rows);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function logUpdate(MonitoringSafetyEngineeringRecord $record, array $payload, ?int $userId): int
    {
        $changedAt = now();
        $batch = (string) Str::uuid();
        $rows = [];
        $tracked = $this->trackedFields();

        foreach ($payload as $field => $newRaw) {
            if (! is_string($field) || ! isset($tracked[$field])) {
                continue;
            }

            $oldRaw = $record->getAttribute($field);
            $oldComparable = $this->normalizeComparable($field, $oldRaw);
            $newComparable = $this->normalizeComparable($field, $newRaw);

            if ($oldComparable === $newComparable) {
                continue;
            }

            $rows[] = $this->buildRow(
                recordId: $record->id,
                batch: $batch,
                action: 'updated',
                field: $field,
                label: $tracked[$field],
                oldValue: $this->formatDisplay($field, $oldRaw),
                newValue: $this->formatDisplay($field, $newRaw),
                changedAt: $changedAt,
                userId: $userId,
                periodYear: (int) ($payload['period_year'] ?? $record->period_year),
            );
        }

        return $this->insertLogRows($rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchHistory(int $recordId, ?string $field = null): array
    {
        $record = MonitoringSafetyEngineeringRecord::query()
            ->select(['id', 'pengendalian_rekayasa', 'site', 'perusahaan'])
            ->find($recordId);

        if ($record === null) {
            return [
                'found' => false,
                'record_id' => $recordId,
                'batches' => [],
                'entries' => [],
            ];
        }

        $tracked = $this->trackedFields();
        $fieldFilter = $field !== null && $field !== '' && isset($tracked[$field]) ? $field : null;

        try {
            $query = MonitoringSafetyEngineeringRecordChangeLog::query()
                ->with(['changer:id,name'])
                ->where('record_id', $recordId)
                ->orderByDesc('changed_at')
                ->orderByDesc('id');

            if ($fieldFilter !== null) {
                $query->where('field_name', $fieldFilter);
            }

            $logs = $query->get();
        } catch (QueryException $e) {
            if (! $this->isMissingTableException($e)) {
                throw $e;
            }

            return [
                'found' => true,
                'record_id' => $record->id,
                'pengendalian_rekayasa' => $record->pengendalian_rekayasa,
                'site' => $record->site,
                'perusahaan' => $record->perusahaan,
                'field' => $fieldFilter,
                'field_label' => $fieldFilter !== null ? $tracked[$fieldFilter] : null,
                'batches' => [],
                'entries' => [],
                'total_changes' => 0,
                'message' => 'Tabel log belum tersedia. Jalankan migration terlebih dahulu.',
            ];
        }

        $batches = [];

        $entries = [];

        foreach ($logs as $log) {
            $batchKey = $log->change_batch;
            $changedAt = $log->changed_at?->format('Y-m-d H:i:s');
            $changedByName = $log->changer?->name ?? 'Sistem';

            if (! isset($batches[$batchKey])) {
                $batches[$batchKey] = [
                    'change_batch' => $log->change_batch,
                    'action' => $log->action,
                    'review_week' => $log->review_week,
                    'period_year' => $log->period_year,
                    'changed_at' => $changedAt,
                    'changed_by' => $log->changed_by,
                    'changed_by_name' => $changedByName,
                    'changes' => [],
                ];
            }

            $batches[$batchKey]['changes'][] = [
                'field_name' => $log->field_name,
                'field_label' => $log->field_label,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
            ];

            $entries[] = [
                'field_name' => $log->field_name,
                'field_label' => $log->field_label,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'action' => $log->action,
                'review_week' => $log->review_week,
                'changed_at' => $changedAt,
                'changed_by' => $log->changed_by,
                'changed_by_name' => $changedByName,
            ];
        }

        return [
            'found' => true,
            'record_id' => $record->id,
            'pengendalian_rekayasa' => $record->pengendalian_rekayasa,
            'site' => $record->site,
            'perusahaan' => $record->perusahaan,
            'field' => $fieldFilter,
            'field_label' => $fieldFilter !== null ? $tracked[$fieldFilter] : null,
            'batches' => array_values($batches),
            'entries' => $entries,
            'total_changes' => $logs->count(),
        ];
    }

    public function reviewWeek(CarbonInterface $date): string
    {
        return $date->format('o-\WW');
    }

    /**
     * @return array<string, string>
     */
    private function trackedFields(): array
    {
        $labels = [];

        foreach (MonitoringSafetyEngineeringRecordGridDefinition::columns() as $column) {
            $labels[$column['key']] = $column['label'];
        }

        $labels['row_no'] = 'No';
        $labels['sort_order'] = 'Urutan';

        return $labels;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertLogRows(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        try {
            MonitoringSafetyEngineeringRecordChangeLog::query()->insert($rows);
        } catch (QueryException $e) {
            if ($this->isMissingTableException($e)) {
                return 0;
            }

            throw $e;
        }

        return count($rows);
    }

    private function isMissingTableException(QueryException $e): bool
    {
        $message = $e->getMessage();

        return $e->getCode() === '42S02'
            || str_contains($message, "doesn't exist")
            || str_contains($message, 'Base table or view not found');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRow(
        int $recordId,
        string $batch,
        string $action,
        string $field,
        string $label,
        ?string $oldValue,
        ?string $newValue,
        CarbonInterface $changedAt,
        ?int $userId,
        int $periodYear,
    ): array {
        $timestamp = now();

        return [
            'record_id' => $recordId,
            'change_batch' => $batch,
            'action' => $action,
            'field_name' => $field,
            'field_label' => $label,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_at' => $changedAt,
            'changed_by' => $userId,
            'period_year' => $periodYear,
            'review_week' => $this->reviewWeek($changedAt),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function normalizeComparable(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (in_array($field, ['terkait_hazard', 'terkait_insiden', 'potensi_peningkatan_efektivitas'], true)) {
            $normalized = strtolower(trim((string) $value));

            return match ($normalized) {
                'ya', 'yes', 'y', '1', 'true' => '1',
                default => '0',
            };
        }

        if (in_array($field, [
            'kajian_teknis_status', 'pengadaan_status', 'uji_coba_status', 'standardisasi_status',
        ], true)) {
            return $this->normalizePhaseStatus((string) $value);
        }

        if (in_array($field, ['sumber_rekayasa', 'pelaksana_rekayasa'], true)) {
            return strtolower(str_replace(' ', '_', trim((string) $value)));
        }

        if ($field === 'intervensi_deviasi') {
            return $this->normalizeIntervensiKey((string) $value);
        }

        return trim((string) $value);
    }

    private function formatDisplay(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if (in_array($field, ['terkait_hazard', 'terkait_insiden', 'potensi_peningkatan_efektivitas'], true)) {
            if (is_bool($value)) {
                return $value ? 'Ya' : 'Tidak';
            }

            $normalized = strtolower(trim((string) $value));

            return match ($normalized) {
                'ya', 'yes', 'y', '1', 'true' => 'Ya',
                default => 'Tidak',
            };
        }

        $maps = [
            'sumber_rekayasa' => config('monitoring_safety_engineering.sumber_rekayasa', []),
            'pelaksana_rekayasa' => config('monitoring_safety_engineering.pelaksana_rekayasa', []),
            'kajian_teknis_status' => config('monitoring_safety_engineering.phase_status', []),
            'pengadaan_status' => config('monitoring_safety_engineering.phase_status', []),
            'uji_coba_status' => config('monitoring_safety_engineering.phase_status', []),
            'standardisasi_status' => config('monitoring_safety_engineering.phase_status', []),
            'intervensi_deviasi' => config('monitoring_safety_engineering.intervensi_deviasi', []),
            'deteksi_deviasi' => config('monitoring_safety_engineering.deteksi_deviasi', []),
        ];

        if (isset($maps[$field])) {
            $scalar = strtolower(str_replace(' ', '_', trim((string) $value)));

            return $maps[$field][$scalar] ?? (string) $value;
        }

        return (string) $value;
    }

    private function normalizePhaseStatus(string $value): string
    {
        $key = strtolower(str_replace(' ', '_', trim($value)));
        $map = [];

        foreach (config('monitoring_safety_engineering.phase_status', []) as $backing => $label) {
            $map[strtolower(str_replace(' ', '_', $label))] = $backing;
            $map[$backing] = $backing;
        }

        return $map[$key] ?? $key;
    }

    private function normalizeIntervensiKey(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        foreach (config('monitoring_safety_engineering.intervensi_deviasi', []) as $backing => $label) {
            if ($trimmed === $backing || strcasecmp($trimmed, (string) $label) === 0) {
                return (string) $backing;
            }
        }

        return strtolower(str_replace(' ', '_', $trimmed));
    }
}
