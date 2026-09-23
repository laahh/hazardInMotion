<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryToolChecklistItem;
use App\Models\PncMonitoring\PncMonitoringInventoryToolMaster;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import/export checklist pemeriksaan untuk SATU jenis alat (tool_master) sekaligus —
 * dipakai dari halaman edit Katalog Alat, bukan sebagai daftar top-level tersendiri.
 */
final class PncMonitoringInventoryChecklistExcelService
{
    /**
     * @var list<string>
     */
    public const HEADERS = ['No', 'Komponen Diperiksa', 'Kriteria Pemeriksaan'];

    public function download(PncMonitoringInventoryToolMaster $toolMaster): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Checklist');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1E3A8A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(50);

        $rowIndex = 2;
        foreach ($toolMaster->checklistItems as $item) {
            $sheet->fromArray([$item->sequence, $item->komponen_diperiksa, $item->kriteria_pemeriksaan], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $filename = 'checklist-'.str($toolMaster->standard_name)->slug().'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Mengganti seluruh checklist milik tool_master ini dengan isi file Excel.
     */
    public function importReplace(PncMonitoringInventoryToolMaster $toolMaster, string $path): PncMonitoringExcelUpsertResult
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return new PncMonitoringExcelUpsertResult(0, 0, ['File tidak valid: '.$e->getMessage()]);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $items = [];
        $warnings = [];

        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $komponen = trim((string) $sheet->getCell([2, $rowIndex])->getValue());
            $kriteria = trim((string) $sheet->getCell([3, $rowIndex])->getValue());
            if ($komponen === '') {
                continue;
            }
            $items[] = [
                'tool_master_id' => $toolMaster->tool_master_id,
                'sequence' => count($items) + 1,
                'komponen_diperiksa' => $komponen,
                'kriteria_pemeriksaan' => $kriteria,
            ];
        }

        if ($items === []) {
            return new PncMonitoringExcelUpsertResult(0, 0, ['Tidak ada baris checklist yang bisa diimpor.']);
        }

        DB::transaction(function () use ($toolMaster, $items): void {
            PncMonitoringInventoryToolChecklistItem::query()->where('tool_master_id', $toolMaster->tool_master_id)->delete();
            PncMonitoringInventoryToolChecklistItem::query()->insert($items);
        });

        return new PncMonitoringExcelUpsertResult(count($items), 0, [], $warnings);
    }
}
