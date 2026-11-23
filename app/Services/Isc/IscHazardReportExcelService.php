<?php

declare(strict_types=1);

namespace App\Services\Isc;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IscHazardReportExcelService
{
    public function __construct(
        private readonly IscHazardReportBoardService $board,
    ) {}

    public function downloadIntervened(): StreamedResponse
    {
        return $this->download($this->board->intervenedPayload(), 'isc-hazard-intervensi');
    }

    public function downloadHistorical(): StreamedResponse
    {
        return $this->download($this->board->historicalPayload(), 'isc-hazard-historical');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function download(array $payload, string $prefix): StreamedResponse
    {
        $rows = $payload['reports'] ?? [];
        $filename = $prefix.'-'.now()->format('Ymd-His').'.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Hazard');

        $headers = [
            'No', 'Tanggal', 'Status', 'Nama Pelapor', 'SID Pelapor', 'Jabatan Pelapor',
            'Pelanggar', 'SID Pelanggar', 'Perusahaan Pelanggar', 'Site', 'Lokasi', 'Detail Lokasi',
            'Ketidaksesuaian', 'Sub Ketidaksesuaian', 'Deskripsi Temuan', 'PIC', 'Jabatan PIC',
            'Observasi Area Kritis', 'Quick Action', 'Status Intervensi', 'Status Event',
        ];
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
                $row['created_at'] ?? '',
                $row['status_label'] ?? '',
                $row['nama_pelapor'] ?? '',
                $row['sid_pelapor'] ?? '',
                $row['jabatan_pelapor'] ?? '',
                $row['violator_name'] ?? '',
                $row['violator_sid'] ?? '',
                $row['violator_company'] ?? '',
                $row['site'] ?? '',
                $row['lokasi'] ?? '',
                $row['detail_lokasi'] ?? '',
                $row['ketidaksesuaian'] ?? '',
                $row['sub_ketidaksesuaian'] ?? '',
                $row['deskripsi_temuan'] ?? '',
                $row['pic_nama'] ?? '',
                $row['pic_jabatan'] ?? '',
                empty($row['is_observasi_area_kritis']) ? 'Tidak' : 'Ya',
                $row['quick_action'] ?? '',
                $row['intervention_status'] ?? '',
                $row['event_status'] ?? '',
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
