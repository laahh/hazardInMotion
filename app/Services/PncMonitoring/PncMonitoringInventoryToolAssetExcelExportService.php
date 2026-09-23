<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryToolAsset;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PncMonitoringInventoryToolAssetExcelExportService
{
    /**
     * @param  Collection<int, PncMonitoringInventoryToolAsset>  $rows
     */
    public function download(Collection $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('UnitAset');
        $sheet->freezePane('A2');

        $headers = PncMonitoringInventoryToolAssetExcelParser::HEADERS;
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1E3A8A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth(20);
        }
        $sheet->getColumnDimension('A')->setWidth(30);

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row->toolMaster?->standard_name,
                $row->inventory_id,
                $row->brand,
                $row->model,
                $row->serial_number,
                $row->year_made,
                $row->power_source,
                $row->status_availability,
                $row->location_detail,
                $row->condition,
                $row->owner_type,
                $row->ownerCompany?->name,
                $row->purchase_date?->format('Y-m-d'),
                $row->purchase_price,
                $row->notes,
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $filename = 'export-pnc-monitoring-unit-aset.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
