<?php

declare(strict_types=1);

namespace App\Services\Isc;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IscCctvExcelService
{
    public function __construct(
        private readonly IscCctvMapService $cctv,
    ) {}

    public function download(bool $demo, ?string $site): StreamedResponse
    {
        $payload = $this->cctv->payload($demo);
        $cameras = $payload['cameras'] ?? [];
        if ($site !== null && $site !== '') {
            $cameras = array_values(array_filter(
                $cameras,
                static fn (array $row): bool => (string) ($row['site_code'] ?? '') === $site
            ));
        }

        $filename = 'isc-cctv-'.now()->format('Ymd-His').'.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CCTV');

        $headers = ['No', 'No CCTV', 'Nama', 'Site', 'Perusahaan', 'Lokasi', 'Kondisi', 'Status', 'Latitude', 'Longitude', 'Link Akses'];
        foreach ($headers as $index => $header) {
            $cell = $sheet->getCell([$index + 1, 1]);
            $cell->setValue($header);
            $sheet->getStyle($cell->getCoordinate())->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F0FE']],
            ]);
        }

        foreach (array_values($cameras) as $i => $row) {
            $sheet->fromArray([
                $i + 1,
                $row['no_cctv'] ?? '',
                $row['name'] ?? '',
                $row['site'] ?? ($row['site_code'] ?? ''),
                $row['company'] ?? '',
                $row['location'] ?? '',
                $row['kondisi'] ?? '',
                empty($row['ok']) ? 'Off' : 'On',
                $row['lat'] ?? '',
                $row['lng'] ?? '',
                $row['link'] ?? '',
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
