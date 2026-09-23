<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryCategory;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    public function parse(string $path): PncMonitoringExcelParseResult
    {
        $rows = [];
        $errors = [];
        $warnings = [];

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return new PncMonitoringExcelParseResult([], ['File tidak valid: '.$e->getMessage()], []);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $categoryLookup = $this->buildCategoryLookup();

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
                $warnings[] = "Baris {$rowIndex}: Kode Kategori dan Nama Alat wajib diisi, dilewati.";
                continue;
            }
            // Excel sering menghilangkan angka nol di depan (mis. "001" jadi "1")
            // kalau kolomnya tidak diformat Teks — cocokkan juga bentuk tanpa nol depan.
            $categoryId = $categoryLookup[$code] ?? $categoryLookup[ltrim($code, '0') ?: '0'] ?? null;
            if ($categoryId === null) {
                $warnings[] = "Baris {$rowIndex}: Kode Kategori '{$code}' tidak ditemukan, dilewati.";
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

        return new PncMonitoringExcelParseResult($rows, $errors, $warnings);
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
