<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan Excel poster jadwal 1 minggu (format matriks untuk dibagikan).
 */
final class ControlRoomScheduleShareExcelService
{
    /**
     * @param  array<string, mixed>  $grid
     */
    public function download(array $grid): StreamedResponse
    {
        $spreadsheet = $this->spreadsheet($grid);
        $scope = ($grid['scope'] ?? 'site') === 'all' ? 'semua-site' : (string) ($grid['site'] ?? 'site');
        $filename = sprintf(
            'jadwal-ocr-%s-w%02d-%d.xlsx',
            $scope,
            (int) ($grid['week'] ?? 0),
            (int) ($grid['year'] ?? 0),
        );

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $grid
     */
    public function spreadsheet(array $grid): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jadwal Minggu');
        $days = $grid['days'] ?? [];
        $lastCol = chr(ord('C') + max(0, count($days) - 1));

        $sheet->mergeCells('A1:'.$lastCol.'1');
        $sheet->setCellValue('A1', 'Tim Safety · '.$grid['site_label'].' · '.$grid['week_label']);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $headers = ['Peran / Site', 'Personil'];
        foreach ($days as $day) {
            $headers[] = ($day['label'] ?? '').' '.($day['header'] ?? '');
        }
        $sheet->fromArray($headers, null, 'A2');
        $sheet->getStyle('A2:'.$lastCol.'2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1E3A8A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDE68A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);

        $row = 3;
        foreach ($grid['groups'] ?? [] as $group) {
            $title = trim((string) ($group['title'] ?? ''));
            $subtitle = trim((string) ($group['subtitle'] ?? ''));
            $label = $subtitle !== '' ? $title.' · '.$subtitle : $title;
            $rows = $group['rows'] ?? [];
            $span = max(1, count($rows));
            $start = $row;
            $end = $row + $span - 1;
            $sheet->mergeCells('A'.$start.':A'.$end);
            $sheet->setCellValue('A'.$start, $label);
            $sheet->getStyle('A'.$start)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '92400E']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDE68A']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'textRotation' => count($rows) > 2 ? 90 : 0,
                    'wrapText' => true,
                ],
            ]);

            foreach ($rows as $person) {
                $sheet->setCellValue('B'.$row, ($person['site'] ?? '').' · '.($person['name'] ?? ''));
                $col = 'C';
                foreach ($person['cells'] ?? [] as $cell) {
                    $text = (string) ($cell['text'] ?? '');
                    $sheet->setCellValue($col.$row, $text);
                    $tone = (string) ($cell['tone'] ?? 'empty');
                    $fill = match ($tone) {
                        's2' => 'FFEDD5',
                        'mix' => 'E0E7FF',
                        's1' => 'DBEAFE',
                        default => 'FFFFFF',
                    };
                    $sheet->getStyle($col.$row)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'font' => ['bold' => $text !== '', 'color' => ['rgb' => $tone === 's2' ? '9A3412' : '1E3A8A']],
                    ]);
                    $col++;
                }
                $row++;
            }
        }

        if ($row === 3) {
            $sheet->mergeCells('A3:'.$lastCol.'3');
            $sheet->setCellValue('A3', 'Belum ada jadwal pada minggu ini.');
        }

        $sheet->getStyle('A2:'.$lastCol.($row - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(36);
        for ($i = 0; $i < count($days); $i++) {
            $sheet->getColumnDimension(chr(ord('C') + $i))->setWidth(16);
        }
        $sheet->freezePane('C3');
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(1);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 2);

        return $spreadsheet;
    }
}
