<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringRecord;
use App\Support\MonitoringSafetyEngineering\MonitoringSafetyEngineeringExcelValueMapper as Mapper;
use App\Support\MonitoringSafetyEngineering\MonitoringSafetyEngineeringRecordGridDefinition;
use BackedEnum;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class MonitoringSafetyEngineeringRecordGridService
{
    public function __construct(
        private readonly MonitoringSafetyEngineeringPhaseStatusLogService $phaseStatusLogService,
        private readonly MonitoringSafetyEngineeringRecordChangeLogService $changeLogService,
        private readonly MonitoringSafetyEngineeringRiskReductionCalculator $riskReductionCalculator,
        private readonly MonitoringSafetyEngineeringPicScopeService $picScope,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function gridConfig(): array
    {
        $scope = $this->picScope->forCurrentUser();
        $columns = MonitoringSafetyEngineeringRecordGridDefinition::columns();
        $dropdowns = MonitoringSafetyEngineeringRecordGridDefinition::dropdownSources();

        if ($scope['scoped']) {
            if (! $scope['all_sites'] && $scope['sites'] !== []) {
                $dropdowns['site'] = $scope['sites'];
            }
            if ($scope['companies'] !== []) {
                $dropdowns['perusahaan'] = $scope['companies'];
            }

            foreach ($columns as $index => $column) {
                if ($column['key'] === 'site' && $scope['lock_site']) {
                    $columns[$index]['read_only'] = true;
                }
                if ($column['key'] === 'perusahaan' && $scope['lock_perusahaan']) {
                    $columns[$index]['read_only'] = true;
                }
            }
        }

        return [
            'columns' => $columns,
            'nested_headers' => MonitoringSafetyEngineeringRecordGridDefinition::nestedHeaderGroups(),
            'dropdowns' => $dropdowns,
            'editable_fields' => MonitoringSafetyEngineeringRecordGridDefinition::editableFields(),
            'fixed_columns_left' => MonitoringSafetyEngineeringRecordGridDefinition::fixedColumnsLeft(),
            'pic_scope' => $scope,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRows(?int $periodYear = null): array
    {
        $maps = $this->gridLabelMaps();

        try {
            return $this->fetchGridRows($this->recordSelectColumns(true), $periodYear, $maps);
        } catch (QueryException $e) {
            if (! $this->isUnknownColumnException($e)) {
                throw $e;
            }

            return $this->fetchGridRows($this->recordSelectColumns(false), $periodYear, $maps);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, logs_created: int, change_logs_created: int, errors: list<string>, saved: list<array{client_index: int, id: int}>}
     */
    public function bulkSave(array $rows, int $defaultPeriodYear): array
    {
        $created = 0;
        $updated = 0;
        $logsCreated = 0;
        $changeLogsCreated = 0;
        $errors = [];
        $saved = [];
        $userId = Auth::id();
        $scope = $this->picScope->forCurrentUser();

        $ids = [];
        foreach ($rows as $row) {
            $id = isset($row['id']) && $row['id'] !== '' && $row['id'] !== null
                ? (int) $row['id']
                : 0;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        DB::transaction(function () use (
            $rows,
            $defaultPeriodYear,
            $userId,
            $ids,
            $scope,
            &$created,
            &$updated,
            &$logsCreated,
            &$changeLogsCreated,
            &$errors,
            &$saved,
        ): void {
            $records = $ids === []
                ? collect()
                : MonitoringSafetyEngineeringRecord::query()->whereIn('id', array_values(array_unique($ids)))->get()->keyBy('id');

            foreach ($rows as $index => $row) {
                $line = $index + 1;
                $clientIndex = isset($row['client_index']) && is_numeric($row['client_index'])
                    ? (int) $row['client_index']
                    : $index;

                $id = isset($row['id']) && $row['id'] !== '' && $row['id'] !== null
                    ? (int) $row['id']
                    : null;

                try {
                    if ($id !== null && $id > 0) {
                        $record = $records->get($id);
                        if ($record === null) {
                            $errors[] = 'Baris ' . $line . ': record ID ' . $id . ' tidak ditemukan.';

                            continue;
                        }

                        if (! $this->picScope->allowsRecord($record, $scope)) {
                            $errors[] = 'Baris ' . $line . ': record di luar site/perusahaan Anda.';

                            continue;
                        }

                        if ($scope['lock_site']) {
                            unset($row['site']);
                        }
                        if ($scope['lock_perusahaan']) {
                            unset($row['perusahaan']);
                        }

                        if (isset($row['site']) || isset($row['perusahaan'])) {
                            $nextSite = isset($row['site']) ? (string) $row['site'] : (string) $record->site;
                            $nextPerusahaan = isset($row['perusahaan']) ? (string) $row['perusahaan'] : (string) $record->perusahaan;
                            if (! $this->picScope->allowsPair($nextSite, $nextPerusahaan, $scope)) {
                                $errors[] = 'Baris ' . $line . ': site/perusahaan tidak sesuai PIC Anda.';

                                continue;
                            }
                        }

                        $payload = $this->normalizePatchPayload($row, $record, $defaultPeriodYear);
                        if ($payload === []) {
                            $saved[] = ['client_index' => $clientIndex, 'id' => $id];

                            continue;
                        }

                        $payload['updated_by'] = $userId;
                        $tracing = $this->phaseStatusLogService->applyForUpdate($record, $payload, $userId);
                        $payload = $tracing['payload'];
                        $logsCreated += $tracing['logs_created'];
                        $changeLogsCreated += $this->changeLogService->logUpdate($record, $payload, $userId);
                        $record->update($payload);
                        $updated++;
                        $saved[] = ['client_index' => $clientIndex, 'id' => $id];

                        continue;
                    }

                    $row = $this->picScope->applyToNewRow($row, $scope);
                    if (! $this->picScope->allowsPair(
                        trim((string) ($row['site'] ?? '')),
                        trim((string) ($row['perusahaan'] ?? '')),
                        $scope,
                    )) {
                        $errors[] = 'Baris ' . $line . ': site/perusahaan tidak sesuai PIC Anda.';

                        continue;
                    }

                    $payload = $this->normalizeRowPayload($row, $defaultPeriodYear);
                    $payload['created_by'] = $userId;
                    $payload['updated_by'] = $userId;
                    $record = MonitoringSafetyEngineeringRecord::query()->create($payload);
                    $logsCreated += $this->phaseStatusLogService->writeInitialLogs($record, $payload, $userId);
                    $changeLogsCreated += $this->changeLogService->logCreate($record, $payload, $userId);
                    $created++;
                    $saved[] = ['client_index' => $clientIndex, 'id' => (int) $record->id];
                } catch (\InvalidArgumentException $e) {
                    $errors[] = 'Baris ' . $line . ': ' . $e->getMessage();
                }
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'logs_created' => $logsCreated,
            'change_logs_created' => $changeLogsCreated,
            'errors' => $errors,
            'saved' => $saved,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchChangeHistory(int $recordId, ?string $field = null): array
    {
        $scope = $this->picScope->forCurrentUser();
        if ($scope['scoped']) {
            $record = MonitoringSafetyEngineeringRecord::query()
                ->select(['id', 'site', 'perusahaan'])
                ->find($recordId);

            if ($record === null || ! $this->picScope->allowsRecord($record, $scope)) {
                return [
                    'found' => false,
                    'record_id' => $recordId,
                    'batches' => [],
                    'entries' => [],
                ];
            }
        }

        return $this->changeLogService->fetchHistory($recordId, $field);
    }

    /**
     * @param  list<string>  $columns
     * @param  array<string, array<string, string>>  $maps
     * @return list<array<string, mixed>>
     */
    private function fetchGridRows(array $columns, ?int $periodYear, array $maps): array
    {
        $query = DB::table('monitoring_safety_engineering_records')
            ->select($columns)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('row_no')
            ->orderBy('id');

        if ($periodYear !== null) {
            $query->where('period_year', $periodYear);
        }

        $this->picScope->applyToQuery($query, $this->picScope->forCurrentUser());

        $withTracing = in_array('kajian_teknis_status_compliance', $columns, true);

        return $query
            ->get()
            ->map(fn (object $row): array => $this->serializeGridRow((array) $row, $maps, $withTracing))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, string>>  $maps
     * @return array<string, mixed>
     */
    private function serializeGridRow(array $row, array $maps, bool $withTracing): array
    {
        foreach (['sumber_rekayasa', 'pelaksana_rekayasa', 'kajian_teknis_status', 'pengadaan_status', 'uji_coba_status', 'standardisasi_status'] as $enumField) {
            $row[$enumField] = $this->labelFromMap($maps[$enumField] ?? [], $row[$enumField] ?? null);
        }

        foreach ([
            'tanggal_ideation', 'kajian_teknis_due_date', 'pengadaan_due_date', 'uji_coba_due_date',
            'standardisasi_due_date', 'replikasi_due_date',
        ] as $dateField) {
            $row[$dateField] = $this->formatSerializableDate($row[$dateField] ?? null);
        }

        $row['terkait_hazard'] = $this->booleanLabel($row['terkait_hazard'] ?? null);
        $row['terkait_insiden'] = $this->booleanLabel($row['terkait_insiden'] ?? null);
        $row['potensi_peningkatan_efektivitas'] = $this->booleanLabel($row['potensi_peningkatan_efektivitas'] ?? null);
        $row['intervensi_deviasi'] = $this->labelFromMap($maps['intervensi_deviasi'] ?? [], $row['intervensi_deviasi'] ?? null);
        $row['efektivitas_rekayasa'] = $this->labelFromMap($maps['efektivitas_rekayasa'] ?? [], $row['efektivitas_rekayasa'] ?? null);
        $row['deteksi_deviasi'] = $this->labelFromMap($maps['deteksi_deviasi'] ?? [], $row['deteksi_deviasi'] ?? null);

        if ($withTracing) {
            foreach ($this->phaseStatusLogService->phaseDefinitions() as $definition) {
                $changedAtField = $definition['changed_at'];
                $complianceField = $definition['compliance'];

                $row[$changedAtField] = $this->formatSerializableDate($row[$changedAtField] ?? null, 'Y-m-d H:i:s');
                $compliance = $row[$complianceField] ?? null;
                $row[$complianceField] = $compliance instanceof BackedEnum ? $compliance->value : $compliance;
            }
        }

        return $row;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function gridLabelMaps(): array
    {
        $phaseStatus = array_map('strval', config('monitoring_safety_engineering.phase_status', []));

        return [
            'sumber_rekayasa' => array_map('strval', config('monitoring_safety_engineering.sumber_rekayasa', [])),
            'pelaksana_rekayasa' => array_map('strval', config('monitoring_safety_engineering.pelaksana_rekayasa', [])),
            'kajian_teknis_status' => $phaseStatus,
            'pengadaan_status' => $phaseStatus,
            'uji_coba_status' => $phaseStatus,
            'standardisasi_status' => $phaseStatus,
            'intervensi_deviasi' => array_map('strval', config('monitoring_safety_engineering.intervensi_deviasi', [])),
            'efektivitas_rekayasa' => array_map('strval', config('monitoring_safety_engineering.efektivitas_rekayasa', [])),
            'deteksi_deviasi' => array_map('strval', config('monitoring_safety_engineering.deteksi_deviasi', [])),
        ];
    }

    /**
     * @param  array<string, string>  $map
     */
    private function labelFromMap(array $map, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $raw = trim((string) $value);
        if (isset($map[$raw])) {
            return $map[$raw];
        }

        return $raw;
    }

    private function booleanLabel(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Tidak';
        }

        if (is_string($value) && in_array(strtolower($value), ['ya', 'tidak'], true)) {
            return strtolower($value) === 'ya' ? 'Ya' : 'Tidak';
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Ya' : 'Tidak';
    }

    private function isUnknownColumnException(QueryException $e): bool
    {
        return $e->getCode() === '42S22' || str_contains($e->getMessage(), 'Unknown column');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizePatchPayload(array $row, MonitoringSafetyEngineeringRecord $record, int $defaultPeriodYear): array
    {
        $payload = [];

        foreach ($this->patchableFields() as $field) {
            if (! array_key_exists($field, $row)) {
                continue;
            }

            $payload[$field] = $this->normalizePatchField($field, $row[$field], $defaultPeriodYear);
        }

        if (isset($payload['site']) && trim((string) $payload['site']) === '') {
            throw new \InvalidArgumentException('Site wajib diisi.');
        }

        if (isset($payload['perusahaan']) && trim((string) $payload['perusahaan']) === '') {
            throw new \InvalidArgumentException('Perusahaan wajib diisi.');
        }

        if (isset($payload['pengendalian_rekayasa']) && trim((string) $payload['pengendalian_rekayasa']) === '') {
            throw new \InvalidArgumentException('Pengendalian Rekayasa wajib diisi.');
        }

        $potensi = array_key_exists('potensi_peningkatan_efektivitas', $payload)
            ? (bool) $payload['potensi_peningkatan_efektivitas']
            : (bool) $record->potensi_peningkatan_efektivitas;

        $pengendalianEfektivitas = array_key_exists('pengendalian_peningkatan_efektivitas', $payload)
            ? (string) ($payload['pengendalian_peningkatan_efektivitas'] ?? '')
            : (string) ($record->pengendalian_peningkatan_efektivitas ?? '');

        if ($potensi && trim($pengendalianEfektivitas) === '') {
            throw new \InvalidArgumentException('Pengendalian peningkatan efektivitas wajib diisi jika potensi = Ya.');
        }

        if (
            array_key_exists('deteksi_deviasi', $payload)
            || array_key_exists('intervensi_deviasi', $payload)
            || array_key_exists('prediksi_penurunan_tangga_risiko', $payload)
        ) {
            $deteksi = array_key_exists('deteksi_deviasi', $payload)
                ? $payload['deteksi_deviasi']
                : $record->deteksi_deviasi;
            $intervensi = array_key_exists('intervensi_deviasi', $payload)
                ? $payload['intervensi_deviasi']
                : $record->intervensi_deviasi;
            $stored = array_key_exists('prediksi_penurunan_tangga_risiko', $payload)
                ? $payload['prediksi_penurunan_tangga_risiko']
                : $record->prediksi_penurunan_tangga_risiko;

            $payload['prediksi_penurunan_tangga_risiko'] = $this->resolvePrediksiPenurunanTangga(
                is_numeric($stored) ? (int) $stored : null,
                $deteksi !== null && $deteksi !== '' ? (string) $deteksi : null,
                $intervensi !== null && $intervensi !== '' ? (string) $intervensi : null,
            );
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function patchableFields(): array
    {
        return [
            'row_no', 'site', 'perusahaan', 'aktivitas', 'sumber_rekayasa', 'pelaksana_rekayasa',
            'pengendalian_rekayasa', 'tanggal_ideation', 'kajian_teknis_due_date', 'kajian_teknis_status',
            'pengadaan_due_date', 'pengadaan_status', 'uji_coba_due_date', 'uji_coba_status',
            'standardisasi_due_date', 'standardisasi_status', 'replikasi_due_date', 'replikasi_total_populasi',
            'replikasi_satuan', 'replikasi_target_komitmen', 'replikasi_diusulkan_pjo', 'replikasi_ditinjau',
            'replikasi_disetujui', 'replikasi_aktual', 'deteksi_deviasi', 'intervensi_deviasi',
            'prediksi_penurunan_tangga_risiko', 'terkait_hazard', 'terkait_insiden', 'efektivitas_rekayasa',
            'brief_analysis_challenge', 'next_to_do', 'potensi_peningkatan_efektivitas',
            'pengendalian_peningkatan_efektivitas', 'total_risiko_signifikan', 'link_list_risiko_signifikan',
            'jumlah_risiko_signifikan_tercover_rekayasa', 'link_risiko_signifikan_tercover_rekayasa',
            'period_year', 'sort_order',
        ];
    }

    private function normalizePatchField(string $field, mixed $value, int $defaultPeriodYear): mixed
    {
        return match ($field) {
            'site', 'perusahaan', 'pengendalian_rekayasa', 'aktivitas', 'replikasi_satuan' => trim((string) ($value ?? '')),
            'sumber_rekayasa' => Mapper::resolveSumberRekayasa(isset($value) ? (string) $value : null)->value,
            'pelaksana_rekayasa' => Mapper::resolvePelaksana(isset($value) ? (string) $value : null)->value,
            'tanggal_ideation', 'kajian_teknis_due_date', 'pengadaan_due_date', 'uji_coba_due_date',
            'standardisasi_due_date', 'replikasi_due_date' => $this->parseDate($value),
            'kajian_teknis_status' => Mapper::resolvePhaseStatus(isset($value) ? (string) $value : null, 'Status Kajian Teknis')->value,
            'pengadaan_status' => Mapper::resolvePhaseStatus(isset($value) ? (string) $value : null, 'Status Pengadaan')->value,
            'uji_coba_status' => Mapper::resolvePhaseStatus(isset($value) ? (string) $value : null, 'Status Uji Coba')->value,
            'standardisasi_status' => Mapper::resolvePhaseStatus(isset($value) ? (string) $value : null, 'Status Standardisasi')->value,
            'row_no' => $this->parseInteger($value, 0),
            'replikasi_total_populasi', 'replikasi_target_komitmen', 'replikasi_aktual' => $this->parseInteger($value, 0),
            'sort_order' => $this->parseInteger($value, 0),
            'period_year' => $this->parseInteger($value, $defaultPeriodYear),
            'replikasi_diusulkan_pjo', 'replikasi_ditinjau', 'replikasi_disetujui',
            'brief_analysis_challenge', 'next_to_do', 'pengendalian_peningkatan_efektivitas',
            'link_list_risiko_signifikan', 'link_risiko_signifikan_tercover_rekayasa' => $this->nullableString($value),
            'deteksi_deviasi' => Mapper::resolveDeteksi(isset($value) ? (string) $value : null),
            'intervensi_deviasi' => Mapper::resolveIntervensi(isset($value) ? (string) $value : null),
            'prediksi_penurunan_tangga_risiko', 'total_risiko_signifikan',
            'jumlah_risiko_signifikan_tercover_rekayasa' => $this->parseNullableInteger($value),
            'terkait_hazard' => Mapper::resolveBoolean(isset($value) ? (string) $value : null, 'Terkait Hazard'),
            'terkait_insiden' => Mapper::resolveBoolean(isset($value) ? (string) $value : null, 'Terkait Insiden'),
            'potensi_peningkatan_efektivitas' => Mapper::resolveBoolean(isset($value) ? (string) $value : null, 'Potensi Peningkatan Efektivitas'),
            'efektivitas_rekayasa' => Mapper::resolveEfektivitas(isset($value) ? (string) $value : null),
            default => $value,
        };
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

            $string = trim((string) $value);
            if ($string === '' || str_starts_with($string, '0000-00-00')) {
                return null;
            }

            $timestamp = strtotime($string);

            return $timestamp === false ? null : date($format, $timestamp);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function recordSelectColumns(bool $withTracing): array
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

        if ($withTracing) {
            $columns = array_merge($columns, [
                'kajian_teknis_status_changed_at', 'kajian_teknis_status_compliance',
                'pengadaan_status_changed_at', 'pengadaan_status_compliance',
                'uji_coba_status_changed_at', 'uji_coba_status_compliance',
                'standardisasi_status_changed_at', 'standardisasi_status_compliance',
            ]);
        }

        return $columns;
    }
}
