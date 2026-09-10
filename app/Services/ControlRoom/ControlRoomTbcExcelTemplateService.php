<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Template Excel Validasi TBC Control Room.
 */
final class ControlRoomTbcExcelTemplateService
{
    public function download(): StreamedResponse
    {
        $spreadsheet = $this->spreadsheet();
        $filename = 'template-validasi-tbc-control-room.xlsx';

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
        $this->writeDataSheet($spreadsheet);
        $this->writePetunjukSheet($spreadsheet);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function writeDataSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ValidasiTbc');
        $sheet->freezePane('A2');
        $headers = ControlRoomTbcExcelParser::HEADERS;
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
            $col = Coordinate::stringFromColumnIndex($index);
            $sheet->getColumnDimension($col)->setWidth($index === 3 ? 18 : 22);
        }

        $this->applyListValidation($sheet, 'A', $this->options('tbc_no_alert_options'));
        $this->applyListValidation($sheet, 'K', $this->options('tbc_rootcause_options'));
        $sheet->getStyle('C2:C2001')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEF9C3'],
            ],
        ]);
    }

    /**
     * @param  list<string>  $options
     */
    private function applyListValidation(Worksheet $sheet, string $column, array $options): void
    {
        if ($options === []) {
            return;
        }

        $formula = '"'.implode(',', array_map(
            static fn (string $option): string => str_replace(['"', ','], ['', ' '], $option),
            $options,
        )).'"';

        $validation = $sheet->getCell($column.'2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1($formula);
        $sheet->setDataValidation($column.'2:'.$column.'2001', $validation);
    }

    /**
     * @return list<string>
     */
    private function options(string $configKey): array
    {
        $raw = config('control-room.'.$configKey, []);
        if (! is_array($raw)) {
            return [];
        }

        $options = [];
        foreach ($raw as $option) {
            $label = trim((string) $option);
            if ($label !== '') {
                $options[] = $label;
            }
        }

        return $options;
    }

    private function writePetunjukSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Petunjuk');
        $sheet->fromArray([
            ['Template upload Validasi TBC Control Room'],
            ['Kunci unik', 'Tasklist (wajib). Upload meng-update baris dengan Tasklist yang sama.'],
            ['Join dashboard', 'Tasklist harus sama dengan id laporan SAP (Hazard/Inspeksi) agar masuk Ratio TBC.'],
            [''],
            ['Header wajib (urutan tidak boleh diubah)'],
            ...array_map(static fn (string $header): array => [$header], ControlRoomTbcExcelParser::HEADERS),
            [''],
            ['Kolom kuning (Tasklist) wajib. Rootcause Aktual memakai dropdown bila opsi di config terisi.'],
        ], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getColumnDimension('A')->setWidth(72);
        $sheet->getColumnDimension('B')->setWidth(80);
    }
}
