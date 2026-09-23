<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryCategory;
use App\Models\PncMonitoring\PncMonitoringInventoryToolAttribute;
use App\Models\PncMonitoring\PncMonitoringInventoryToolChecklistItem;
use App\Models\PncMonitoring\PncMonitoringInventoryToolFunction;
use App\Models\PncMonitoring\PncMonitoringInventoryToolInspectionMethod;
use App\Models\PncMonitoring\PncMonitoringInventoryToolSafetyFeature;
use App\Models\PncMonitoring\PncMonitoringInventoryToolStandard;
use App\Models\PncMonitoring\PncMonitoringInventoryToolUsageRule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Satu file Excel untuk seluruh dokumentasi Katalog Alat: sheet pertama ("KatalogAlat")
 * berisi data inti (satu baris per jenis alat), sheet-sheet berikutnya berisi bagian
 * detail (Fungsi, Metode Inspeksi, dst) — masing-masing baris merujuk ke jenis alat
 * lewat kolom "Nama Alat (Standard Name)", jadi satu kali upload bisa langsung
 * membuat jenis alat SEKALIGUS seluruh detailnya.
 */
final class PncMonitoringInventoryToolMasterExcelParser
{
    /**
     * @var list<string>
     */
    public const HEADERS = [
        'Kode Kategori',
        'Nama Alat (Standard Name)',
        'Sub Kategori',
        'Fungsi Utama',
        'Criticality',
        'Risk Class',
        'Wajib Regulasi (Ya/Tidak)',
        'URL Gambar',
    ];

    /**
     * @var list<string>
     */
    public const FIELD_KEYS = [
        'category_code',
        'standard_name',
        'sub_category',
        'main_function',
        'criticality',
        'risk_class',
        'is_regulated',
        'image_url',
    ];

    public const CORE_SHEET_TITLE = 'KatalogAlat';

    /**
     * Konfigurasi sheet detail — sumber kebenaran tunggal dipakai bersama oleh
     * template generator, parser, dan upsert service (kunci array = section key
     * yang juga dipakai PncMonitoringInventoryToolMasterDetailExcelService).
     *
     * @var array<string, array{sheetTitle:string, fields:list<string>, headers:list<string>, model:class-string, hasSequence:bool, relation:string}>
     */
    public const DETAIL_SECTIONS = [
        'functions' => [
            'sheetTitle' => 'FungsiDetail',
            'fields' => ['description'],
            'headers' => ['Deskripsi Fungsi'],
            'model' => PncMonitoringInventoryToolFunction::class,
            'hasSequence' => true,
            'relation' => 'functions',
        ],
        'inspection_methods' => [
            'sheetTitle' => 'MetodeInspeksi',
            'fields' => ['method_name'],
            'headers' => ['Nama Metode'],
            'model' => PncMonitoringInventoryToolInspectionMethod::class,
            'hasSequence' => false,
            'relation' => 'inspectionMethods',
        ],
        'safety_features' => [
            'sheetTitle' => 'FiturKeselamatan',
            'fields' => ['feature_name', 'description'],
            'headers' => ['Nama Fitur', 'Deskripsi'],
            'model' => PncMonitoringInventoryToolSafetyFeature::class,
            'hasSequence' => false,
            'relation' => 'safetyFeatures',
        ],
        'standards' => [
            'sheetTitle' => 'StandarAcuan',
            'fields' => ['standard_name'],
            'headers' => ['Nama Standar'],
            'model' => PncMonitoringInventoryToolStandard::class,
            'hasSequence' => false,
            'relation' => 'standards',
        ],
        'checklist_items' => [
            'sheetTitle' => 'ChecklistPemeriksaan',
            'fields' => ['komponen_diperiksa', 'kriteria_pemeriksaan'],
            'headers' => ['Komponen Diperiksa', 'Kriteria Pemeriksaan'],
            'model' => PncMonitoringInventoryToolChecklistItem::class,
            'hasSequence' => true,
            'relation' => 'checklistItems',
        ],
        'usage_rules' => [
            'sheetTitle' => 'AturanPenggunaan',
            'fields' => ['rule_type', 'description'],
            'headers' => ['Tipe (do/dont)', 'Deskripsi'],
            'model' => PncMonitoringInventoryToolUsageRule::class,
            'hasSequence' => true,
            'relation' => 'usageRules',
        ],
        'attributes' => [
            'sheetTitle' => 'AtributTeknis',
            'fields' => ['attribute_name', 'attribute_value'],
            'headers' => ['Nama Atribut', 'Nilai'],
            'model' => PncMonitoringInventoryToolAttribute::class,
            'hasSequence' => false,
            'relation' => 'attributes',
        ],
    ];

    public function parse(string $path): PncMonitoringInventoryToolMasterBulkParseResult
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return new PncMonitoringInventoryToolMasterBulkParseResult([], [], ['File tidak valid: '.$e->getMessage()], []);
        }

        $coreSheet = $spreadsheet->getSheetByName(self::CORE_SHEET_TITLE) ?? $spreadsheet->getSheet(0);
        [$coreRows, $warnings] = $this->parseCoreSheet($coreSheet);

        $detailRows = [];
        foreach (self::DETAIL_SECTIONS as $section => $config) {
            $sheet = $spreadsheet->getSheetByName($config['sheetTitle']);
            if ($sheet === null) {
                continue;
            }
            [$rows, $sheetWarnings] = $this->parseDetailSheet($sheet, $config);
            $detailRows[$section] = $rows;
            $warnings = [...$warnings, ...$sheetWarnings];
        }

        return new PncMonitoringInventoryToolMasterBulkParseResult($coreRows, $detailRows, [], $warnings);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function parseCoreSheet(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $categoryLookup = $this->buildCategoryLookup();
        $rows = [];
        $warnings = [];

        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $cells = [];
            foreach (self::FIELD_KEYS as $colIndex => $key) {
                $cells[$key] = trim((string) $sheet->getCell([$colIndex + 1, $rowIndex])->getValue());
            }
            if (implode('', $cells) === '') {
                continue;
            }

            $code = strtoupper($cells['category_code']);
            $standardName = $cells['standard_name'];
            if ($code === '' || $standardName === '') {
                $warnings[] = "KatalogAlat baris {$rowIndex}: Kode Kategori dan Nama Alat wajib diisi, dilewati.";
                continue;
            }
            // Excel sering menghilangkan angka nol di depan (mis. "001" jadi "1")
            // kalau kolomnya tidak diformat Teks — cocokkan juga bentuk tanpa nol depan.
            $categoryId = $categoryLookup[$code] ?? $categoryLookup[ltrim($code, '0') ?: '0'] ?? null;
            if ($categoryId === null) {
                $warnings[] = "KatalogAlat baris {$rowIndex}: Kode Kategori '{$code}' tidak ditemukan, dilewati.";
                continue;
            }

            $rows[] = [
                'category_id' => $categoryId,
                'standard_name' => $standardName,
                'sub_category' => $cells['sub_category'] !== '' ? $cells['sub_category'] : null,
                'main_function' => $cells['main_function'] !== '' ? $cells['main_function'] : null,
                'criticality' => $cells['criticality'] !== '' ? $cells['criticality'] : null,
                'risk_class' => $cells['risk_class'] !== '' ? $cells['risk_class'] : null,
                'is_regulated' => in_array(strtolower($cells['is_regulated']), ['ya', 'yes', 'true', '1'], true),
                'image_url' => $cells['image_url'] !== '' ? $cells['image_url'] : null,
            ];
        }

        return [$rows, $warnings];
    }

    /**
     * @param  array{sheetTitle:string, fields:list<string>, headers:list<string>, model:class-string, hasSequence:bool}  $config
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function parseDetailSheet(Worksheet $sheet, array $config): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $rows = [];
        $warnings = [];

        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $standardName = trim((string) $sheet->getCell([1, $rowIndex])->getValue());
            $values = [];
            foreach ($config['fields'] as $colIndex => $field) {
                $values[$field] = trim((string) $sheet->getCell([$colIndex + 2, $rowIndex])->getValue());
            }
            $primaryField = $config['fields'][0];
            if ($standardName === '' && $values[$primaryField] === '') {
                continue;
            }
            if ($standardName === '') {
                $warnings[] = "{$config['sheetTitle']} baris {$rowIndex}: Nama Alat wajib diisi, dilewati.";
                continue;
            }
            if ($values[$primaryField] === '') {
                continue;
            }

            $rows[] = ['standard_name' => $standardName, ...$values];
        }

        return [$rows, $warnings];
    }

    /**
     * @return array<string, int>
     */
    private function buildCategoryLookup(): array
    {
        $lookup = [];
        foreach (PncMonitoringInventoryCategory::query()->pluck('category_id', 'code') as $code => $id) {
            $code = strtoupper((string) $code);
            $lookup[$code] = (int) $id;
            $lookup[ltrim($code, '0') ?: '0'] = (int) $id;
        }

        return $lookup;
    }
}
