<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryTool;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Membaca Excel Master Data Inventory Tools. Header baris 1 harus sama urutan template.
 */
final class PncMonitoringInventoryExcelParser
{
    public const MAX_ROWS = 5000;

    /**
     * @var list<string>
     */
    public const HEADERS = [
        'Kategori',
        'Asset ID',
        'Nama Alat',
        'Sub Kategori / Jenis',
        'Brand',
        'Model',
        'Serial Number',
        'Tahun Pembuatan',
        'Power Source',
        'Kapasitas / Rating',
        'Site',
        'Lokasi Detail',
        'Status Ketersediaan',
        'Condition',
        'PIC',
        'Qty On Hand',
        'Calibration Required (Ya/Tidak)',
        'Last Calibration Date',
        'Calibration Due Date',
        'Inspection Required (Ya/Tidak)',
        'Last Inspection Date',
        'Next Inspection Due',
        'Inspection Result',
        'PM Required (Ya/Tidak)',
        'Last PM Date',
        'Next PM Due',
        'Catatan',
    ];

    /**
     * @var list<string>
     */
    public const FIELD_KEYS = [
        'category',
        'asset_id',
        'nama_alat',
        'sub_kategori',
        'brand',
        'model',
        'serial_number',
        'tahun_pembuatan',
        'power_source',
        'kapasitas_rating',
        'site',
        'lokasi_detail',
        'status_ketersediaan',
        'condition',
        'pic',
        'qty_on_hand',
        'calibration_required',
        'last_calibration_date',
        'calibration_due_date',
        'inspection_required',
        'last_inspection_date',
        'next_inspection_due',
        'inspection_result',
        'pm_required',
        'last_pm_date',
        'next_pm_due',
        'catatan',
    ];

    public function parse(string $absolutePath): PncMonitoringExcelParseResult
    {
        if (! is_readable($absolutePath)) {
            return new PncMonitoringExcelParseResult([], ['File tidak dapat dibaca.']);
        }

        try {
            $spreadsheet = IOFactory::load($absolutePath);
            $sheet = $spreadsheet->getSheet(0);
            $rows = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable $e) {
            return new PncMonitoringExcelParseResult([], ['File Excel tidak terbaca: '.$e->getMessage()]);
        }

        if ($rows === []) {
            return new PncMonitoringExcelParseResult([], ['File kosong atau tidak terbaca.']);
        }

        $header = array_map(fn (mixed $cell): string => $this->normalizeHeader((string) ($cell ?? '')), (array) array_shift($rows));
        $expected = array_map(fn (string $label): string => $this->normalizeHeader($label), self::HEADERS);
        if ($header !== $expected) {
            return new PncMonitoringExcelParseResult([], [
                'Header Excel tidak sesuai template. Urutan wajib: '.implode(', ', self::HEADERS).'.',
            ]);
        }

        $errors = [];
        $warnings = [];
        $parsed = [];
        $seen = [];

        foreach ($rows as $offset => $row) {
            $excelRow = $offset + 2;
            if ($excelRow > self::MAX_ROWS + 1) {
                $errors[] = 'Maksimal '.self::MAX_ROWS.' baris data.';
                break;
            }

            $cells = is_array($row) ? $row : [];
            $attrs = $this->attributesFromRow($cells);
            if ($this->isAttributesAllEmpty($attrs)) {
                continue;
            }

            $category = trim((string) ($attrs['category'] ?? ''));
            $namaAlat = trim((string) ($attrs['nama_alat'] ?? ''));
            if ($category === '' || ! in_array($category, PncMonitoringInventoryTool::CATEGORIES, true)) {
                $errors[] = "Baris {$excelRow}: Kategori wajib salah satu dari: ".implode(', ', PncMonitoringInventoryTool::CATEGORIES).'.';
                continue;
            }
            if ($namaAlat === '') {
                $errors[] = "Baris {$excelRow}: Nama Alat wajib diisi.";
                continue;
            }

            $upsertKey = PncMonitoringInventoryTool::buildUpsertKey(
                $category,
                $namaAlat,
                $attrs['asset_id'] ?? null,
                $attrs['serial_number'] ?? null,
            );
            $attrs['upsert_key'] = $upsertKey;
            $attrs['category'] = $category;
            $attrs['nama_alat'] = $namaAlat;

            if (isset($seen[$upsertKey])) {
                $warnings[] = "Baris {$excelRow}: kunci {$upsertKey} dobel di file — baris terakhir yang dipakai.";
            }
            $seen[$upsertKey] = true;
            $parsed[$upsertKey] = $attrs;
        }

        return new PncMonitoringExcelParseResult(array_values($parsed), $errors, $warnings);
    }

    /**
     * @param  list<mixed>  $cells
     * @return array<string, mixed>
     */
    private function attributesFromRow(array $cells): array
    {
        $attrs = [];
        foreach (self::FIELD_KEYS as $index => $key) {
            $raw = $this->cell($cells, $index);
            $attrs[$key] = match ($key) {
                'last_calibration_date', 'calibration_due_date', 'last_inspection_date',
                'next_inspection_due', 'last_pm_date', 'next_pm_due' => $this->parseDate($raw),
                'qty_on_hand' => $this->parseInt($raw),
                'calibration_required', 'inspection_required', 'pm_required' => $this->parseBool($raw),
                default => $raw,
            };
        }

        return $attrs;
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function cell(array $cells, int $index): ?string
    {
        $raw = $cells[$index] ?? null;
        if ($raw === null) {
            return null;
        }
        $value = trim((string) $raw);

        return $value === '' ? null : $value;
    }

    private function parseDate(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw);

                return $date->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function parseInt(?string $raw): ?int
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (int) round((float) $raw);
    }

    private function parseBool(?string $raw): ?bool
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $normalized = mb_strtolower(trim($raw));

        return match ($normalized) {
            'ya', 'yes', 'y', '1', 'true' => true,
            'tidak', 'no', 'n', '0', 'false' => false,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function isAttributesAllEmpty(array $attrs): bool
    {
        foreach ($attrs as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(string $label): string
    {
        $label = preg_replace('/\s+/u', ' ', trim($label)) ?? trim($label);

        return mb_strtolower($label);
    }
}
