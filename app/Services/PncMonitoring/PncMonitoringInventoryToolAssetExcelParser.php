<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryToolAsset;
use App\Models\PncMonitoring\PncMonitoringInventoryToolMaster;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

final class PncMonitoringInventoryToolAssetExcelParser
{
    /**
     * @var list<string>
     */
    public const HEADERS = [
        'Nama Alat (Standard Name)',
        'Inventory ID / Asset Tag',
        'Brand',
        'Model',
        'Serial Number',
        'Tahun Pembuatan',
        'Power Source',
        'Status Ketersediaan',
        'Lokasi Detail',
        'Condition',
        'Owner Type',
        'Owner Company',
        'Tanggal Pembelian',
        'Harga Pembelian',
        'Catatan',
    ];

    public function parse(string $path): PncMonitoringExcelParseResult
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            return new PncMonitoringExcelParseResult([], ['File tidak valid: '.$e->getMessage()]);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $toolMasters = PncMonitoringInventoryToolMaster::query()->pluck('tool_master_id', 'standard_name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower((string) $name) => $id]);

        $rows = [];
        $warnings = [];

        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $cell = fn (int $col) => trim((string) $sheet->getCellByColumnAndRow($col, $rowIndex)->getValue());
            $standardName = $cell(1);
            if ($standardName === '' && $cell(2) === '' && $cell(5) === '') {
                continue;
            }
            if ($standardName === '') {
                $warnings[] = "Baris {$rowIndex}: Nama Alat wajib diisi, dilewati.";
                continue;
            }
            $toolMasterId = $toolMasters->get(mb_strtolower($standardName));
            if ($toolMasterId === null) {
                $warnings[] = "Baris {$rowIndex}: Nama Alat '{$standardName}' tidak ditemukan di Katalog Alat, dilewati.";
                continue;
            }

            $status = $cell(8);
            $condition = $cell(10);
            $rows[] = [
                'tool_master_id' => (int) $toolMasterId,
                'inventory_id' => $cell(2) !== '' ? $cell(2) : null,
                'brand' => $cell(3) !== '' ? $cell(3) : null,
                'model' => $cell(4) !== '' ? $cell(4) : null,
                'serial_number' => $cell(5) !== '' ? $cell(5) : null,
                'year_made' => is_numeric($cell(6)) ? (int) $cell(6) : null,
                'power_source' => $cell(7) !== '' ? $cell(7) : null,
                'status_availability' => in_array($status, PncMonitoringInventoryToolAsset::STATUSES, true) ? $status : 'Available',
                'location_detail' => $cell(9) !== '' ? $cell(9) : null,
                'condition' => in_array($condition, PncMonitoringInventoryToolAsset::CONDITIONS, true) ? $condition : null,
                'owner_type' => $cell(11) !== '' ? $cell(11) : null,
                'owner_company_name' => $cell(12) !== '' ? $cell(12) : null,
                'purchase_date' => $this->parseDate($cell(13)),
                'purchase_price' => is_numeric($cell(14)) ? (float) $cell(14) : null,
                'notes' => $cell(15) !== '' ? $cell(15) : null,
            ];
        }

        return new PncMonitoringExcelParseResult($rows, [], $warnings);
    }

    private function parseDate(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
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
}
