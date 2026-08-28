<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringRecord;
use App\Support\MonitoringSafetyEngineering\MonitoringSafetyEngineeringExcelValueMapper as Mapper;
use App\Support\MonitoringSafetyEngineering\MonitoringSafetyEngineeringRecordGridDefinition;
use BackedEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MonitoringSafetyEngineeringRecordGridService
{
    private ?bool $tracingReadyForBatch = null;

    public function __construct(
        private readonly MonitoringSafetyEngineeringPhaseStatusLogService $phaseStatusLogService,
        private readonly MonitoringSafetyEngineeringRecordChangeLogService $changeLogService,
        private readonly MonitoringSafetyEngineeringRiskReductionCalculator $riskReductionCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function gridConfig(): array
    {
        return [
            'columns' => MonitoringSafetyEngineeringRecordGridDefinition::columns(),
            'nested_headers' => MonitoringSafetyEngineeringRecordGridDefinition::nestedHeaderGroups(),
            'dropdowns' => MonitoringSafetyEngineeringRecordGridDefinition::dropdownSources(),
            'editable_fields' => MonitoringSafetyEngineeringRecordGridDefinition::editableFields(),
            'fixed_columns_left' => MonitoringSafetyEngineeringRecordGridDefinition::fixedColumnsLeft(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRows(?int $periodYear = null): array
    {
        $query = MonitoringSafetyEngineeringRecord::query()
            ->select($this->recordSelectColumns())
            ->orderBy('sort_order')
            ->orderBy('row_no')
            ->orderBy('id');

        if ($periodYear !== null) {
            $query->where('period_year', $periodYear);
        }

        $records = $query->get();

        $this->tracingReadyForBatch = $this->phaseStatusLogService->isTracingReady();

        try {
            return $records
                ->map(function (MonitoringSafetyEngineeringRecord $record): array {
                    try {
                        return $this->serializeRecord($record);
                    } catch (\Throwable $e) {
                        report($e);

                        return [
                            'id' => $record->getKey(),
                            'pengendalian_rekayasa' => (string) ($record->getAttributes()['pengendalian_rekayasa'] ?? ''),
                            'site' => (string) ($record->getAttributes()['site'] ?? ''),
                            'perusahaan' => (string) ($record->getAttributes()['perusahaan'] ?? ''),
                        ];
                    }
                })
                ->all();
        } finally {
            $this->tracingReadyForBatch = null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, logs_created: int, change_logs_created: int, errors: list<string>}
     */
    public function bulkSave(array $rows, int $defaultPeriodYear): array
    {
        $created = 0;
        $updated = 0;
        $logsCreated = 0;
        $changeLogsCreated = 0;
        $errors = [];
        $userId = Auth::id();

        DB::transaction(function () use ($rows, $defaultPeriodYear, $userId, &$created, &$updated, &$logsCreated, &$changeLogsCreated, &$errors): void {
            foreach ($rows as $index => $row) {
                $line = $index + 1;

                try {
                    $payload = $this->normalizeRowPayload($row, $defaultPeriodYear);
                } catch (\InvalidArgumentException $e) {
                    $errors[] = 'Baris ' . $line . ': ' . $e->getMessage();

                    continue;
                }

                $id = isset($row['id']) && $row['id'] !== '' && $row['id'] !== null
                    ? (int) $row['id']
                    : null;

                if ($id !== null && $id > 0) {
                    $record = MonitoringSafetyEngineeringRecord::query()->find($id);
                    if ($record === null) {
                        $errors[] = 'Baris ' . $line . ': record ID ' . $id . ' tidak ditemukan.';

                        continue;
                    }

                    $payload['updated_by'] = $userId;
                    $tracing = $this->phaseStatusLogService->applyForUpdate($record, $payload, $userId);
                    $payload = $tracing['payload'];
                    $logsCreated += $tracing['logs_created'];
                    $changeLogsCreated += $this->changeLogService->logUpdate($record, $payload, $userId);
                    $record->update($payload);
                    $updated++;

                    continue;
                }

                $payload['created_by'] = $userId;
                $payload['updated_by'] = $userId;
                $record = MonitoringSafetyEngineeringRecord::query()->create($payload);
                $logsCreated += $this->phaseStatusLogService->writeInitialLogs($record, $payload, $userId);
                $changeLogsCreated += $this->changeLogService->logCreate($record, $payload, $userId);
                $created++;
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'logs_created' => $logsCreated,
            'change_logs_created' => $changeLogsCreated,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchChangeHistory(int $recordId): array
    {
        return $this->changeLogService->fetchHistory($recordId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRecord(MonitoringSafetyEngineeringRecord $record): array
    {
        $data = $record->toArray();

        foreach (['sumber_rekayasa', 'pelaksana_rekayasa', 'kajian_teknis_status', 'pengadaan_status', 'uji_coba_status', 'standardisasi_status'] as $enumField) {
            $value = $record->getAttribute($enumField);
            if ($value instanceof BackedEnum) {
                $data[$enumField] = $this->enumToLabel($enumField, $value->value);
            } elseif (is_string($value) && $value !== '') {
                $data[$enumField] = $this->enumToLabel($enumField, $value);
            }
        }

        foreach ([
            'tanggal_ideation', 'kajian_teknis_due_date', 'pengadaan_due_date', 'uji_coba_due_date',
            'standardisasi_due_date', 'replikasi_due_date',
        ] as $dateField) {
            $data[$dateField] = $this->formatSerializableDate($record->getAttribute($dateField));
        }

        $data['terkait_hazard'] = $record->terkait_hazard ? 'Ya' : 'Tidak';
        $data['terkait_insiden'] = $record->terkait_insiden ? 'Ya' : 'Tidak';
        $data['potensi_peningkatan_efektivitas'] = $record->potensi_peningkatan_efektivitas ? 'Ya' : 'Tidak';

        if ($record->intervensi_deviasi !== null && $record->intervensi_deviasi !== '') {
            try {
                $data['intervensi_deviasi'] = Mapper::resolveIntervensi((string) $record->intervensi_deviasi);
            } catch (\InvalidArgumentException) {
                $data['intervensi_deviasi'] = (string) $record->intervensi_deviasi;
            }
        }

        if ($record->efektivitas_rekayasa !== null && $record->efektivitas_rekayasa !== '') {
            try {
                $data['efektivitas_rekayasa'] = Mapper::resolveEfektivitas((string) $record->efektivitas_rekayasa);
            } catch (\InvalidArgumentException) {
                $data['efektivitas_rekayasa'] = (string) $record->efektivitas_rekayasa;
            }
        }

        if ($this->tracingReadyForBatch === true) {
            foreach ($this->phaseStatusLogService->phaseDefinitions() as $definition) {
                $changedAtField = $definition['changed_at'];
                $complianceField = $definition['compliance'];

                $data[$changedAtField] = $this->formatSerializableDate($record->getAttribute($changedAtField), 'Y-m-d H:i:s');
                $compliance = $record->getAttribute($complianceField);
                $data[$complianceField] = $compliance instanceof BackedEnum ? $compliance->value : $compliance;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRowPayload(array $row, int $defaultPeriodYear): array
    {
        $pengendalian = trim((string) ($row['pengendalian_rekayasa'] ?? ''));
        if ($pengendalian === '') {
            throw new \InvalidArgumentException('Pengendalian Rekayasa wajib diisi.');
        }

        $site = trim((string) ($row['site'] ?? ''));
        if ($site === '') {
            throw new \InvalidArgumentException('Site wajib diisi.');
        }

        $perusahaan = trim((string) ($row['perusahaan'] ?? ''));
        if ($perusahaan === '') {
            throw new \InvalidArgumentException('Perusahaan wajib diisi.');
        }

        $potensi = Mapper::resolveBoolean(
            isset($row['potensi_peningkatan_efektivitas']) ? (string) $row['potensi_peningkatan_efektivitas'] : null,
            'Potensi Peningkatan Efektivitas',
        );

        $pengendalianEfektivitas = trim((string) ($row['pengendalian_peningkatan_efektivitas'] ?? ''));
        if ($potensi && $pengendalianEfektivitas === '') {
            throw new \InvalidArgumentException('Pengendalian peningkatan efektivitas wajib diisi jika potensi = Ya.');
        }

        $rowNo = $this->parseInteger($row['row_no'] ?? null, 0);
        $periodYear = $this->parseInteger($row['period_year'] ?? null, $defaultPeriodYear);

        return [
            'row_no' => $rowNo,
            'site' => $site,
            'perusahaan' => $perusahaan,
            'sumber_rekayasa' => Mapper::resolveSumberRekayasa(isset($row['sumber_rekayasa']) ? (string) $row['sumber_rekayasa'] : null)->value,
            'pelaksana_rekayasa' => Mapper::resolvePelaksana(isset($row['pelaksana_rekayasa']) ? (string) $row['pelaksana_rekayasa'] : null)->value,
            'pengendalian_rekayasa' => $pengendalian,
            'tanggal_ideation' => $this->parseDate($row['tanggal_ideation'] ?? null),
            'kajian_teknis_due_date' => $this->parseDate($row['kajian_teknis_due_date'] ?? null),
            'kajian_teknis_status' => Mapper::resolvePhaseStatus(isset($row['kajian_teknis_status']) ? (string) $row['kajian_teknis_status'] : null, 'Status Kajian Teknis')->value,
            'pengadaan_due_date' => $this->parseDate($row['pengadaan_due_date'] ?? null),
            'pengadaan_status' => Mapper::resolvePhaseStatus(isset($row['pengadaan_status']) ? (string) $row['pengadaan_status'] : null, 'Status Pengadaan')->value,
            'uji_coba_due_date' => $this->parseDate($row['uji_coba_due_date'] ?? null),
            'uji_coba_status' => Mapper::resolvePhaseStatus(isset($row['uji_coba_status']) ? (string) $row['uji_coba_status'] : null, 'Status Uji Coba')->value,
            'standardisasi_due_date' => $this->parseDate($row['standardisasi_due_date'] ?? null),
            'standardisasi_status' => Mapper::resolvePhaseStatus(isset($row['standardisasi_status']) ? (string) $row['standardisasi_status'] : null, 'Status Standardisasi')->value,
            'replikasi_due_date' => $this->parseDate($row['replikasi_due_date'] ?? null),
            'replikasi_total_populasi' => $this->parseInteger($row['replikasi_total_populasi'] ?? 0, 0),
            'replikasi_satuan' => trim((string) ($row['replikasi_satuan'] ?? '')),
            'replikasi_target_komitmen' => $this->parseInteger($row['replikasi_target_komitmen'] ?? 0, 0),
            'replikasi_diusulkan_pjo' => $this->nullableString($row['replikasi_diusulkan_pjo'] ?? null),
            'replikasi_ditinjau' => $this->nullableString($row['replikasi_ditinjau'] ?? null),
            'replikasi_disetujui' => $this->nullableString($row['replikasi_disetujui'] ?? null),
            'replikasi_aktual' => $this->parseInteger($row['replikasi_aktual'] ?? 0, 0),
            'deteksi_deviasi' => $deteksi = Mapper::resolveDeteksi(isset($row['deteksi_deviasi']) ? (string) $row['deteksi_deviasi'] : null),
            'intervensi_deviasi' => $intervensi = Mapper::resolveIntervensi(isset($row['intervensi_deviasi']) ? (string) $row['intervensi_deviasi'] : null),
            'prediksi_penurunan_tangga_risiko' => $this->resolvePrediksiPenurunanTangga(
                $this->parseNullableInteger($row['prediksi_penurunan_tangga_risiko'] ?? null),
                $deteksi,
                $intervensi,
            ),
            'terkait_hazard' => Mapper::resolveBoolean(isset($row['terkait_hazard']) ? (string) $row['terkait_hazard'] : null, 'Terkait Hazard'),
            'terkait_insiden' => Mapper::resolveBoolean(isset($row['terkait_insiden']) ? (string) $row['terkait_insiden'] : null, 'Terkait Insiden'),
            'efektivitas_rekayasa' => Mapper::resolveEfektivitas(isset($row['efektivitas_rekayasa']) ? (string) $row['efektivitas_rekayasa'] : null),
            'brief_analysis_challenge' => $this->nullableString($row['brief_analysis_challenge'] ?? null),
            'next_to_do' => $this->nullableString($row['next_to_do'] ?? null),
            'potensi_peningkatan_efektivitas' => $potensi,
            'pengendalian_peningkatan_efektivitas' => $pengendalianEfektivitas !== '' ? $pengendalianEfektivitas : null,
            'aktivitas' => trim((string) ($row['aktivitas'] ?? '')),
            'total_risiko_signifikan' => $this->parseNullableInteger($row['total_risiko_signifikan'] ?? null),
            'link_list_risiko_signifikan' => $this->nullableString($row['link_list_risiko_signifikan'] ?? null),
            'jumlah_risiko_signifikan_tercover_rekayasa' => $this->parseNullableInteger($row['jumlah_risiko_signifikan_tercover_rekayasa'] ?? null),
            'link_risiko_signifikan_tercover_rekayasa' => $this->nullableString($row['link_risiko_signifikan_tercover_rekayasa'] ?? null),
            'period_year' => $periodYear,
            'sort_order' => $this->parseInteger($row['sort_order'] ?? null, $rowNo),
        ];
    }

    private function enumToLabel(string $field, mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        $scalarValue = (string) $value;

        $maps = [
            'sumber_rekayasa' => config('monitoring_safety_engineering.sumber_rekayasa', []),
            'pelaksana_rekayasa' => config('monitoring_safety_engineering.pelaksana_rekayasa', []),
            'kajian_teknis_status' => config('monitoring_safety_engineering.phase_status', []),
            'pengadaan_status' => config('monitoring_safety_engineering.phase_status', []),
            'uji_coba_status' => config('monitoring_safety_engineering.phase_status', []),
            'standardisasi_status' => config('monitoring_safety_engineering.phase_status', []),
        ];

        return $maps[$field][$scalarValue] ?? $scalarValue;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = trim((string) $value);
        $timestamp = strtotime($string);

        if ($timestamp === false) {
            throw new \InvalidArgumentException('Format tanggal tidak valid: "' . $string . '".');
        }

        return date('Y-m-d', $timestamp);
    }

    private function parseInteger(mixed $value, int $fallback): int
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException('Nilai numerik tidak valid: "' . $value . '".');
        }

        return max(0, (int) $value);
    }

    private function parseNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException('Nilai numerik tidak valid: "' . $value . '".');
        }

        return max(0, (int) $value);
    }

    private function resolvePrediksiPenurunanTangga(
        ?int $stored,
        ?string $deteksi,
        ?string $intervensi,
    ): ?int {
        return $this->riskReductionCalculator->resolveEffectivePrediksi($stored, $deteksi, $intervensi);
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function formatSerializableDate(mixed $value, string $format = 'Y-m-d'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format($format);
            }

            $timestamp = strtotime((string) $value);

            return $timestamp === false ? null : date($format, $timestamp);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function recordSelectColumns(): array
    {
        $columns = [
            'id', 'row_no', 'site', 'perusahaan', 'aktivitas', 'sumber_rekayasa', 'pelaksana_rekayasa',
            'pengendalian_rekayasa', 'tanggal_ideation', 'kajian_teknis_due_date', 'kajian_teknis_status',
            'pengadaan_due_date', 'pengadaan_status',
            'uji_coba_due_date', 'uji_coba_status',
            'standardisasi_due_date', 'standardisasi_status',
            'replikasi_due_date', 'replikasi_total_populasi',
            'replikasi_satuan', 'replikasi_target_komitmen', 'replikasi_diusulkan_pjo', 'replikasi_ditinjau',
            'replikasi_disetujui', 'replikasi_aktual', 'deteksi_deviasi', 'intervensi_deviasi',
            'prediksi_penurunan_tangga_risiko', 'terkait_hazard', 'terkait_insiden', 'efektivitas_rekayasa',
            'brief_analysis_challenge',
            'next_to_do', 'potensi_peningkatan_efektivitas', 'pengendalian_peningkatan_efektivitas',
            'total_risiko_signifikan', 'link_list_risiko_signifikan',
            'jumlah_risiko_signifikan_tercover_rekayasa', 'link_risiko_signifikan_tercover_rekayasa',
            'period_year', 'sort_order',
        ];

        if ($this->phaseStatusLogService->isTracingReady()) {
            $columns = array_merge($columns, [
                'kajian_teknis_status_changed_at', 'kajian_teknis_status_compliance',
                'pengadaan_status_changed_at', 'pengadaan_status_compliance',
                'uji_coba_status_changed_at', 'uji_coba_status_compliance',
                'standardisasi_status_changed_at', 'standardisasi_status_compliance',
            ]);
        }

        $existing = Schema::getColumnListing('monitoring_safety_engineering_records');

        return array_values(array_intersect($columns, $existing));
    }
}
