<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringIkkRecord;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PncMonitoringIkkExcelExportService
{
    /**
     * @param  Collection<int, PncMonitoringIkkRecord>  $rows
     */
    public function download(Collection $rows): StreamedResponse
    {
        $spreadsheet = $this->spreadsheet($rows);
        $filename = 'data-pnc-monitoring-ikk-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  Collection<int, PncMonitoringIkkRecord>  $rows
     */
    public function spreadsheet(Collection $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('DataIKK');
        $sheet->freezePane('A2');

        $headers = PncMonitoringIkkExcelParser::HEADERS;
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1E3A8A']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DBEAFE'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(36);
        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth(16);
        }
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(28);

        $rowIndex = 2;
        foreach ($rows as $record) {
            $sheet->fromArray($this->rowValues($record), null, 'A'.$rowIndex);
            $rowIndex++;
        }

        return $spreadsheet;
    }

    /**
     * @return list<int|string|null>
     */
    private function rowValues(PncMonitoringIkkRecord $record): array
    {
        return [
            $record->jenis,
            $record->nomor,
            $record->pekerjaan,
            $record->tanggal?->format('Y-m-d'),
            $record->minggu,
            $record->bulan,
            $record->site,
            $record->mine_contractor,
            $record->perusahaan,
            $record->finding_ia,
            $record->finding_verlap,
            $record->ia,
            $record->ipk,
            $record->plan_okk,
            $record->okk_1,
            $record->okk_2,
            $record->okk_3,
            $this->pct($record->okkPerformance()),
            $record->okk_layer_2,
            $record->okk_layer_3,
            $record->okk_layer_4,
            $this->pct($record->layer2UpPerformance()),
        ];
    }

    private function pct(?float $ratio): ?string
    {
        return $ratio === null ? null : number_format($ratio * 100, 1).'%';
    }
}
