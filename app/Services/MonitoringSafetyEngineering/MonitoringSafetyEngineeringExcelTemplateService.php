<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Support\MonitoringSafetyEngineering\MonitoringSafetyEngineeringExcelStructure;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class MonitoringSafetyEngineeringExcelTemplateService
{
    public function buildSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $dataSheet = $spreadsheet->getActiveSheet();
        $dataSheet->setTitle('Data Komitmen');
        $this->buildDataSheet($dataSheet);

        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('Referensi');
        $this->buildReferenceSheet($refSheet);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return list<string>
     */
    public function validateImportHeaders(array $rows): array
    {
        $errors = [];
        $headerRowIndex = MonitoringSafetyEngineeringExcelStructure::EXCEL_COLUMN_HEADER_ROW - 1;
        $expected = MonitoringSafetyEngineeringExcelStructure::leafHeaders();
        $actualRow = $rows[$headerRowIndex] ?? [];

        foreach ($expected as $index => $expectedHeader) {
            $actual = $this->normalizeHeaderCell($actualRow[$index] ?? null);
            $expectedNorm = $this->normalizeHeaderCell($expectedHeader);

            if ($actual !== $expectedNorm) {
                $colLetter = $this->columnLetter($index + 1);
                $errors[] = 'Kolom ' . $colLetter . ' baris ' . MonitoringSafetyEngineeringExcelStructure::EXCEL_COLUMN_HEADER_ROW
                    . ' seharusnya "' . $expectedHeader . '", ditemukan "' . ($actualRow[$index] ?? '') . '".';
            }
        }

        return $errors;
    }

    public function resolveDataStartRowIndex(): int
    {
        return MonitoringSafetyEngineeringExcelStructure::EXCEL_DATA_START_ROW - 1;
    }

    private function buildDataSheet(Worksheet $sheet): void
    {
        $sheet->setCellValue('A1', 'NO');
        $sheet->setCellValue('B1', 'SITE');
        $sheet->setCellValue('C1', 'PERUSAHAAN');
        $sheet->setCellValue('D1', 'AKTIVITAS');
        $sheet->setCellValue('E1', 'SUMBER REKAYASA');
        $sheet->setCellValue('F1', 'PELAKSANA REKAYASA');
        $sheet->setCellValue('G1', 'PENGENDALIAN REKAYASA');
        $sheet->setCellValue('H1', 'TANGGAL IDEATION');

        $sheet->mergeCells('I1:K1');
        $sheet->setCellValue('I1', 'KAJIAN TEKNIS');
        $sheet->mergeCells('L1:N1');
        $sheet->setCellValue('L1', 'PENGADAAN');
        $sheet->mergeCells('O1:Q1');
        $sheet->setCellValue('O1', 'UJI COBA');
        $sheet->mergeCells('R1:T1');
        $sheet->setCellValue('R1', 'STANDARISASI');
        $sheet->mergeCells('U1:AB1');
        $sheet->setCellValue('U1', 'REPLIKASI');

        $sheet->setCellValue('AC1', 'DETEKSI DEVIASI');
        $sheet->setCellValue('AD1', 'INTERVENSI DEVIASI');
        $sheet->setCellValue('AE1', 'PREDIKSI PENURUNAN TANGGA NILAI RISIKO');
        $sheet->setCellValue('AF1', 'TERKAIT HAZARD');
        $sheet->setCellValue('AG1', 'TERKAIT INSIDEN');
        $sheet->setCellValue('AH1', 'BRIEF ANALYSIS/CHALLENGE');
        $sheet->setCellValue('AI1', 'NEXT TO DO');
        $sheet->setCellValue('AJ1', 'POTENSI PENINGKATAN LEVEL EFEKTIVITAS');
        $sheet->setCellValue('AK1', 'PENGENDALIAN REKAYASA (PENINGKATAN LEVEL EFEKTIVITAS)');

        foreach (MonitoringSafetyEngineeringExcelStructure::verticalMergeRanges() as [$from, $to]) {
            $sheet->mergeCells($from . ':' . $to);
        }

        $sheet->fromArray(
            [MonitoringSafetyEngineeringExcelStructure::leafHeaders()],
            null,
            'A' . MonitoringSafetyEngineeringExcelStructure::EXCEL_COLUMN_HEADER_ROW,
        );

        $exampleRow = [
            1,
            'BMO',
            'PAMA',
            'Hauling',
            'Replikasi 2026',
            'Inisiator',
            'DMS Unit DT',
            '2025-01-15',
            '2025-06-30',
            'In Progress',
            '',
            '2025-09-30',
            'Not Yet',
            '',
            '2026-03-31',
            'Not Yet',
            '',
            '2026-06-30',
            'Not Yet',
            '',
            '2026-12-31',
            50,
            'Unit',
            50,
            'PJO Site A',
            'Sudah',
            'Belum',
            12,
            1,
            'Alat',
            2,
            'Ya',
            'Tidak',
            'Keterlambatan pengiriman peralatan DMS.',
            'Follow up vendor mingguan.',
            'Ya',
            'Upgrade spesifikasi kamera cabin unit DT.',
        ];

        $sheet->fromArray([$exampleRow], null, 'A' . MonitoringSafetyEngineeringExcelStructure::EXCEL_DATA_START_ROW);

        $lastCol = MonitoringSafetyEngineeringExcelStructure::lastColumnLetter();
        $sheet->getStyle('A1:' . $lastCol . '2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A5F'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        $sheet->getStyle('A' . MonitoringSafetyEngineeringExcelStructure::EXCEL_DATA_START_ROW . ':' . $lastCol . MonitoringSafetyEngineeringExcelStructure::EXCEL_DATA_START_ROW)
            ->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EEF2FF'],
                ],
            ]);

        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(36);
        $sheet->freezePane('A' . MonitoringSafetyEngineeringExcelStructure::EXCEL_DATA_START_ROW);

        foreach (range('A', $lastCol) as $column) {
            $sheet->getColumnDimension($column)->setWidth($column === 'G' || $column === 'AH' || $column === 'AI' || $column === 'AK' ? 28 : 14);
        }

        $sheet->setCellValue('A50', 'Catatan: Baris 1–2 jangan diubah. Evidence di Excel opsional (upload file via sistem). Isi dropdown sesuai sheet Referensi.');
        $sheet->mergeCells('A50:AK50');
        $sheet->getStyle('A50')->getFont()->setItalic(true)->setSize(9);
    }

    private function buildReferenceSheet(Worksheet $sheet): void
    {
        $sheet->setCellValue('A1', 'Field');
        $sheet->setCellValue('B1', 'Nilai Valid');
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);

        $rows = [
            ['SITE', implode(', ', config('monitoring_safety_engineering.sites', []))],
            ['PERUSAHAAN', implode(', ', config('monitoring_safety_engineering.perusahaan', []))],
            ['SUMBER REKAYASA', implode(', ', array_values(config('monitoring_safety_engineering.sumber_rekayasa', [])))],
            ['PELAKSANA REKAYASA', implode(', ', array_values(config('monitoring_safety_engineering.pelaksana_rekayasa', [])))],
            ['Status Fase', implode(', ', array_values(config('monitoring_safety_engineering.phase_status', [])))],
            ['INTERVENSI DEVIASI', implode(', ', array_values(config('monitoring_safety_engineering.intervensi_deviasi', [])))],
            ['Satuan Rekayasa', implode(', ', config('monitoring_safety_engineering.replikasi_satuan', []))],
            ['Ya / Tidak', 'Ya, Tidak'],
            ['Format Tanggal', 'YYYY-MM-DD atau format tanggal Excel'],
        ];

        $sheet->fromArray($rows, null, 'A2');
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(80);
    }

    private function normalizeHeaderCell(mixed $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value) ?? ''));
    }

    private function columnLetter(int $columnIndex): string
    {
        $letter = '';
        while ($columnIndex > 0) {
            $columnIndex--;
            $letter = chr(65 + ($columnIndex % 26)) . $letter;
            $columnIndex = intdiv($columnIndex, 26);
        }

        return $letter;
    }
}
