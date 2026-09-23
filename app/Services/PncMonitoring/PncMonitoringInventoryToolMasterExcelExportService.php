<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryToolMaster;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PncMonitoringInventoryToolMasterExcelExportService
{
    /**
     * @param  Collection<int, PncMonitoringInventoryToolMaster>  $rows
     */
    public function download(Collection $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('KatalogAlat');
        $sheet->freezePane('A2');

        $headers = PncMonitoringInventoryToolMasterExcelParser::HEADERS;
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

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row->category?->code,
                $row->standard_name,
                $row->sub_category,
                $row->main_function,
                $row->criticality,
                $row->risk_class,
                $row->is_regulated ? 'Ya' : 'Tidak',
                $row->image_url,
            ], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $this->appendDetailSheets($spreadsheet, $rows);

        $filename = 'export-pnc-monitoring-katalog-alat.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  Collection<int, PncMonitoringInventoryToolMaster>  $rows
     */
    private function appendDetailSheets(Spreadsheet $spreadsheet, Collection $rows): void
    {
        foreach (PncMonitoringInventoryToolMasterExcelParser::DETAIL_SECTIONS as $config) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($config['sheetTitle']);
            $sheet->freezePane('A2');

            $headers = ['Nama Alat (Standard Name)', ...$config['headers']];
            $lastCol = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '1E3A8A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            ]);
            foreach (range(1, count($headers)) as $index) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth(30);
            }

            $detailRowIndex = 2;
            foreach ($rows as $toolMaster) {
                foreach ($toolMaster->{$config['relation']} as $item) {
                    $values = array_map(static fn (string $field) => $item->{$field}, $config['fields']);
                    $sheet->fromArray([$toolMaster->standard_name, ...$values], null, 'A'.$detailRowIndex);
                    $detailRowIndex++;
                }
            }
        }
    }
}
