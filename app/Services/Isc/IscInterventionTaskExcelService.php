<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IscInterventionTaskExcelService
{
    public function __construct(
        private readonly IscMapsInterventionService $tasks,
    ) {}

    public function download(?User $user, bool $demo): StreamedResponse
    {
        $payload = $this->tasks->payload($user, $demo);
        $rows = $payload['tasks'] ?? [];

        $filename = 'isc-intervensi-'.now()->format('Ymd-His').'.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Intervensi');

        $headers = ['No', 'Nama', 'SID', 'Perusahaan', 'Jabatan', 'Site', 'Jenis Pelanggaran', 'Nama Bahaya', 'Status', 'Masuk Pada', 'Durasi (menit)'];
        foreach ($headers as $index => $header) {
            $cell = $sheet->getCell([$index + 1, 1]);
            $cell->setValue($header);
            $sheet->getStyle($cell->getCoordinate())->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F0FE']],
            ]);
        }

        foreach (array_values($rows) as $i => $row) {
            $sheet->fromArray([
                $i + 1,
                $row['name'] ?? '',
                $row['sid'] ?? '',
                $row['company'] ?? '',
                $row['job_title'] ?? '',
                $row['site'] ?? ($row['site_code'] ?? ''),
                $row['hazard_kind_label'] ?? '',
                $row['hazard_name'] ?? '',
                ($row['status'] ?? '') === 'in_progress' ? 'On progress' : 'Open',
                $row['entered_at'] ?? '',
                ! empty($row['duration_seconds']) ? (int) round(((int) $row['duration_seconds']) / 60) : 0,
            ], null, 'A'.($i + 2));
        }

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
