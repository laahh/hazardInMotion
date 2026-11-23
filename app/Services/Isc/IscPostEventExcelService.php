<?php

declare(strict_types=1);

namespace App\Services\Isc;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IscPostEventExcelService
{
    public function __construct(
        private readonly IscPostEventTrackService $tracks,
    ) {}

    public function download(string $date, string $query, bool $demo): StreamedResponse
    {
        $pack = $this->tracks->roster($date, $query, $demo);
        $entries = array_values(array_filter(
            $pack['entries'] ?? [],
            static fn (array $row): bool => ($row['entity'] ?? 'person') !== 'unit'
        ));

        $filename = 'isc-post-event-'.$date.'-'.now()->format('Ymd-His').'.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Post-event');

        $headers = ['No', 'Nama', 'SID', 'Perusahaan', 'Jabatan', 'Site', 'Masuk Zona Bahaya', 'Nama Bahaya', 'Waktu Pertama', 'Waktu Terakhir', 'Jumlah Titik GPS'];
        foreach ($headers as $index => $header) {
            $cell = $sheet->getCell([$index + 1, 1]);
            $cell->setValue($header);
            $sheet->getStyle($cell->getCoordinate())->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F0FE']],
            ]);
        }

        foreach ($entries as $i => $row) {
            $sheet->fromArray([
                $i + 1,
                $row['name'] ?? '',
                $row['sid'] ?? '',
                $row['company'] ?? '',
                $row['job_title'] ?? '',
                $row['site'] ?? ($row['site_code'] ?? ''),
                empty($row['entered']) ? 'Tidak' : 'Ya',
                $row['hazard_name'] ?? '',
                $row['first_at'] ?? '',
                $row['last_at'] ?? '',
                $row['point_count'] ?? 0,
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
