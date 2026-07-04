<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringRecord;
use App\Support\MonitoringSafetyEngineering\MonitoringSafetyEngineeringExcelStructure as Excel;
use App\Support\MonitoringSafetyEngineering\MonitoringSafetyEngineeringExcelValueMapper as Mapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class MonitoringSafetyEngineeringExcelImportService
{
    public function __construct(
        private readonly MonitoringSafetyEngineeringExcelTemplateService $templateService,
    ) {}

    /**
     * @return array{imported: int, errors: list<string>, header_invalid?: bool}
     */
    public function importFromFile(string $filePath, int $periodYear): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getSheetByName('Data Komitmen') ?? $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $headerErrors = $this->templateService->validateImportHeaders($rows);
        if ($headerErrors !== []) {
            return [
                'imported' => 0,
                'errors' => $headerErrors,
                'header_invalid' => true,
            ];
        }

        $dataStart = $this->templateService->resolveDataStartRowIndex();
        $errors = [];
        $parsedRows = [];
        $rowNumber = $dataStart + 1;

        foreach (array_slice($rows, $dataStart) as $row) {
            if ($this->isEmptyRow($row)) {
                $rowNumber++;

                continue;
            }

            if ($this->isNoteRow($row)) {
                $rowNumber++;

                continue;
            }

            try {
                $parsedRows[] = $this->parseRow($row, $rowNumber, $periodYear);
            } catch (\InvalidArgumentException $e) {
                $errors[] = 'Baris ' . $rowNumber . ': ' . $e->getMessage();
            }

            $rowNumber++;
        }

        if ($parsedRows === [] && $errors === []) {
            return [
                'imported' => 0,
                'errors' => ['Tidak ada data valid untuk diimport.'],
            ];
        }

        if ($parsedRows === [] && $errors !== []) {
            return [
                'imported' => 0,
                'errors' => $errors,
            ];
        }

        $imported = 0;
        $userId = Auth::id();

        DB::transaction(function () use ($parsedRows, $userId, &$imported): void {
            foreach ($parsedRows as $payload) {
                $payload['created_by'] = $userId;
                $payload['updated_by'] = $userId;
                MonitoringSafetyEngineeringRecord::query()->create($payload);
                $imported++;
            }
        });

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<mixed>  $row
     * @return array<string, mixed>
     */
    private function parseRow(array $row, int $rowNumber, int $periodYear): array
    {
        $pengendalianRekayasa = trim((string) ($row[Excel::COL_PENGENDALIAN_REKAYASA] ?? ''));
        if ($pengendalianRekayasa === '') {
            throw new \InvalidArgumentException('PENGENDALIAN REKAYASA wajib diisi.');
        }

        $site = trim((string) ($row[Excel::COL_SITE] ?? ''));
        if ($site === '') {
            throw new \InvalidArgumentException('SITE wajib diisi.');
        }

        $perusahaan = trim((string) ($row[Excel::COL_PERUSAHAAN] ?? ''));
        if ($perusahaan === '') {
            throw new \InvalidArgumentException('PERUSAHAAN wajib diisi.');
        }

        $potensi = Mapper::resolveBoolean(
            isset($row[Excel::COL_POTENSI_EFEKTIVITAS]) ? (string) $row[Excel::COL_POTENSI_EFEKTIVITAS] : null,
            'POTENSI PENINGKATAN LEVEL EFEKTIVITAS',
        );

        $pengendalianEfektivitas = trim((string) ($row[Excel::COL_PENGENDALIAN_EFEKTIVITAS] ?? ''));
        if ($potensi && $pengendalianEfektivitas === '') {
            throw new \InvalidArgumentException('PENGENDALIAN REKAYASA (PENINGKATAN LEVEL EFEKTIVITAS) wajib diisi jika POTENSI = Ya.');
        }

        return [
            'row_no' => $this->parseInteger($row[Excel::COL_NO] ?? null, $rowNumber),
            'site' => $site,
            'perusahaan' => $perusahaan,
            'aktivitas' => trim((string) ($row[Excel::COL_AKTIVITAS] ?? '')),
            'sumber_rekayasa' => Mapper::resolveSumberRekayasa(isset($row[Excel::COL_SUMBER_REKAYASA]) ? (string) $row[Excel::COL_SUMBER_REKAYASA] : null)->value,
            'pelaksana_rekayasa' => Mapper::resolvePelaksana(isset($row[Excel::COL_PELAKSANA_REKAYASA]) ? (string) $row[Excel::COL_PELAKSANA_REKAYASA] : null)->value,
            'pengendalian_rekayasa' => $pengendalianRekayasa,
            'tanggal_ideation' => $this->parseDate($row[Excel::COL_TANGGAL_IDEATION] ?? null),
            'kajian_teknis_due_date' => $this->parseDate($row[Excel::COL_KT_DUE] ?? null),
            'kajian_teknis_status' => Mapper::resolvePhaseStatus(isset($row[Excel::COL_KT_STATUS]) ? (string) $row[Excel::COL_KT_STATUS] : null, 'Status Kajian Teknis')->value,
            'pengadaan_due_date' => $this->parseDate($row[Excel::COL_PENGADAAN_DUE] ?? null),
            'pengadaan_status' => Mapper::resolvePhaseStatus(isset($row[Excel::COL_PENGADAAN_STATUS]) ? (string) $row[Excel::COL_PENGADAAN_STATUS] : null, 'Status Pengadaan')->value,
            'uji_coba_due_date' => $this->parseDate($row[Excel::COL_UJI_COBA_DUE] ?? null),
            'uji_coba_status' => Mapper::resolvePhaseStatus(isset($row[Excel::COL_UJI_COBA_STATUS]) ? (string) $row[Excel::COL_UJI_COBA_STATUS] : null, 'Status Uji Coba')->value,
            'standardisasi_due_date' => $this->parseDate($row[Excel::COL_STD_DUE] ?? null),
            'standardisasi_status' => Mapper::resolvePhaseStatus(isset($row[Excel::COL_STD_STATUS]) ? (string) $row[Excel::COL_STD_STATUS] : null, 'Status Standardisasi')->value,
            'replikasi_due_date' => $this->parseDate($row[Excel::COL_REP_DUE] ?? null),
            'replikasi_total_populasi' => $this->parseInteger($row[Excel::COL_REP_TOTAL_POPULASI] ?? 0, 0),
            'replikasi_satuan' => trim((string) ($row[Excel::COL_REP_SATUAN] ?? '')),
            'replikasi_target_komitmen' => $this->parseInteger($row[Excel::COL_REP_TARGET] ?? 0, 0),
            'replikasi_diusulkan_pjo' => $this->nullableString($row[Excel::COL_REP_DIUSULKAN_PJO] ?? null),
            'replikasi_ditinjau' => $this->nullableString($row[Excel::COL_REP_DITINJAU] ?? null),
            'replikasi_disetujui' => $this->nullableString($row[Excel::COL_REP_DISETUJUI] ?? null),
            'replikasi_aktual' => $this->parseInteger($row[Excel::COL_REP_AKTUAL] ?? 0, 0),
            'deteksi_deviasi' => $this->parseNullableInteger($row[Excel::COL_DETEKSI_DEVIASI] ?? null),
            'intervensi_deviasi' => Mapper::resolveIntervensi(isset($row[Excel::COL_INTERVENSI_DEVIASI]) ? (string) $row[Excel::COL_INTERVENSI_DEVIASI] : null),
            'prediksi_penurunan_tangga_risiko' => $this->parseNullableInteger($row[Excel::COL_PREDIKSI_RISIKO] ?? null),
            'terkait_hazard' => Mapper::resolveBoolean(isset($row[Excel::COL_TERKAIT_HAZARD]) ? (string) $row[Excel::COL_TERKAIT_HAZARD] : null, 'TERKAIT HAZARD'),
            'terkait_insiden' => Mapper::resolveBoolean(isset($row[Excel::COL_TERKAIT_INSIDEN]) ? (string) $row[Excel::COL_TERKAIT_INSIDEN] : null, 'TERKAIT INSIDEN'),
            'brief_analysis_challenge' => $this->nullableString($row[Excel::COL_BRIEF] ?? null),
            'next_to_do' => $this->nullableString($row[Excel::COL_NEXT_TODO] ?? null),
            'potensi_peningkatan_efektivitas' => $potensi,
            'pengendalian_peningkatan_efektivitas' => $pengendalianEfektivitas !== '' ? $pengendalianEfektivitas : null,
            'period_year' => $periodYear,
            'sort_order' => $this->parseInteger($row[Excel::COL_NO] ?? null, $rowNumber),
        ];
    }

    /**
     * @param  list<mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        $keys = [
            Excel::COL_SITE,
            Excel::COL_PERUSAHAAN,
            Excel::COL_PENGENDALIAN_REKAYASA,
        ];

        foreach ($keys as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $row
     */
    private function isNoteRow(array $row): bool
    {
        $first = strtolower(trim((string) ($row[0] ?? '')));

        return str_starts_with($first, 'catatan');
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
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

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
