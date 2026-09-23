<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryCategory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PncMonitoringInventoryToolMasterExcelTemplateService
{
    public function download(): StreamedResponse
    {
        $spreadsheet = $this->spreadsheet();
        $filename = 'template-pnc-monitoring-katalog-alat.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function spreadsheet(): Spreadsheet
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
        $sheet->getRowDimension(1)->setRowHeight(32);
        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth(20);
        }
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(35);

        // Format kolom Kode Kategori sebagai Teks — kalau tidak, Excel akan menghilangkan
        // angka nol di depan (mis. "001" tersimpan jadi angka 1) saat diisi manual.
        $sheet->getStyle('A2:A1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $codes = PncMonitoringInventoryCategory::query()->orderBy('code')->pluck('code')->all();
        if ($codes !== []) {
            $sheet->setCellValue('K1', 'Kode kategori tersedia: '.implode(', ', $codes));
            $sheet->getStyle('K1')->applyFromArray(['font' => ['italic' => true, 'color' => ['rgb' => '6B7280']]]);
        }

        return $spreadsheet;
    }
}
