<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryToolAttribute;
use App\Models\PncMonitoring\PncMonitoringInventoryToolFunction;
use App\Models\PncMonitoring\PncMonitoringInventoryToolInspectionMethod;
use App\Models\PncMonitoring\PncMonitoringInventoryToolMaster;
use App\Models\PncMonitoring\PncMonitoringInventoryToolSafetyFeature;
use App\Models\PncMonitoring\PncMonitoringInventoryToolStandard;
use App\Models\PncMonitoring\PncMonitoringInventoryToolUsageRule;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import/export generik untuk bagian dokumentasi jenis alat (Fungsi Detail, Metode
 * Inspeksi, Fitur Keselamatan, Standar Acuan, Aturan Penggunaan, Atribut Teknis) —
 * satu file per SATU jenis alat (tool_master), pola sama seperti Checklist Pemeriksaan.
 * Import selalu MENGGANTIKAN seluruh isi bagian tsb untuk jenis alat terkait.
 */
final class PncMonitoringInventoryToolMasterDetailExcelService
{
    /**
     * @var array<string, array{relation:string, model:class-string, fields:list<string>, headers:list<string>, hasSequence:bool, label:string}>
     */
    private const SECTIONS = [
        'functions' => [
            'relation' => 'functions',
            'model' => PncMonitoringInventoryToolFunction::class,
            'fields' => ['description'],
            'headers' => ['Deskripsi Fungsi'],
            'hasSequence' => true,
            'label' => 'fungsi-detail',
        ],
        'inspection_methods' => [
            'relation' => 'inspectionMethods',
            'model' => PncMonitoringInventoryToolInspectionMethod::class,
            'fields' => ['method_name'],
            'headers' => ['Nama Metode'],
            'hasSequence' => false,
            'label' => 'metode-inspeksi',
        ],
        'safety_features' => [
            'relation' => 'safetyFeatures',
            'model' => PncMonitoringInventoryToolSafetyFeature::class,
            'fields' => ['feature_name', 'description'],
            'headers' => ['Nama Fitur', 'Deskripsi'],
            'hasSequence' => false,
            'label' => 'fitur-keselamatan',
        ],
        'standards' => [
            'relation' => 'standards',
            'model' => PncMonitoringInventoryToolStandard::class,
            'fields' => ['standard_name'],
            'headers' => ['Nama Standar'],
            'hasSequence' => false,
            'label' => 'standar-acuan',
        ],
        'usage_rules' => [
            'relation' => 'usageRules',
            'model' => PncMonitoringInventoryToolUsageRule::class,
            'fields' => ['rule_type', 'description'],
            'headers' => ['Tipe (do/dont)', 'Deskripsi'],
            'hasSequence' => true,
            'label' => 'aturan-penggunaan',
        ],
        'attributes' => [
            'relation' => 'attributes',
            'model' => PncMonitoringInventoryToolAttribute::class,
            'fields' => ['attribute_name', 'attribute_value'],
            'headers' => ['Nama Atribut', 'Nilai'],
            'hasSequence' => false,
            'label' => 'atribut-teknis',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function sectionKeys(): array
    {
        return array_keys(self::SECTIONS);
    }

    public function download(string $section, PncMonitoringInventoryToolMaster $toolMaster): StreamedResponse
    {
        $config = self::SECTIONS[$section];
        $toolMaster->loadMissing($config['relation']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');
        $headers = $config['headers'];
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1E3A8A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth(35);
        }

        $rowIndex = 2;
        foreach ($toolMaster->{$config['relation']} as $item) {
            $values = array_map(static fn (string $field) => $item->{$field}, $config['fields']);
            $sheet->fromArray($values, null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $filename = $config['label'].'-'.str($toolMaster->standard_name)->slug().'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importReplace(string $section, PncMonitoringInventoryToolMaster $toolMaster, string $path): PncMonitoringExcelUpsertResult
    {
        $config = self::SECTIONS[$section];

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return new PncMonitoringExcelUpsertResult(0, 0, ['File tidak valid: '.$e->getMessage()]);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $primaryField = $config['fields'][0];
        $byPrimary = [];
        $warnings = [];

        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $values = [];
            foreach ($config['fields'] as $colIndex => $field) {
                $values[$field] = trim((string) $sheet->getCell([$colIndex + 1, $rowIndex])->getValue());
            }
            if ($values[$primaryField] === '') {
                continue;
            }
            if ($section === 'usage_rules') {
                $ruleType = mb_strtolower($values['rule_type']);
                $values['rule_type'] = in_array($ruleType, ['do'], true) ? 'do' : (in_array($ruleType, ['dont', "don't"], true) ? 'dont' : 'do');
            }
            foreach ($config['fields'] as $field) {
                if ($values[$field] === '' && $field !== $primaryField) {
                    $values[$field] = null;
                }
            }

            $dedupeKey = mb_strtolower($values[$primaryField]);
            if (isset($byPrimary[$dedupeKey])) {
                $warnings[] = "Baris {$rowIndex}: '{$values[$primaryField]}' dobel — baris terakhir yang dipakai.";
            }
            $byPrimary[$dedupeKey] = $values;
        }

        $items = [];
        foreach (array_values($byPrimary) as $values) {
            $entry = ['tool_master_id' => $toolMaster->tool_master_id, ...$values];
            if ($config['hasSequence']) {
                $entry['sequence'] = count($items) + 1;
            }
            $items[] = $entry;
        }

        if ($items === []) {
            return new PncMonitoringExcelUpsertResult(0, 0, ['Tidak ada baris yang bisa diimpor.']);
        }

        $modelClass = $config['model'];
        DB::transaction(function () use ($modelClass, $toolMaster, $items): void {
            $modelClass::query()->where('tool_master_id', $toolMaster->tool_master_id)->delete();
            $modelClass::query()->insert($items);
        });

        return new PncMonitoringExcelUpsertResult(count($items), 0, [], $warnings);
    }
}
