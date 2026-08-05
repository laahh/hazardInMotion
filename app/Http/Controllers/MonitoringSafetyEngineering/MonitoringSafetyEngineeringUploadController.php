<?php

declare(strict_types=1);

namespace App\Http\Controllers\MonitoringSafetyEngineering;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MonitoringSafetyEngineering\Concerns\ProvidesMonitoringSafetyEngineeringLayout;
use App\Http\Requests\MonitoringSafetyEngineering\MonitoringSafetyEngineeringImportRequest;
use App\Services\MonitoringSafetyEngineering\MonitoringSafetyEngineeringExcelImportService;
use App\Services\MonitoringSafetyEngineering\MonitoringSafetyEngineeringExcelTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonitoringSafetyEngineeringUploadController extends Controller
{
    use ProvidesMonitoringSafetyEngineeringLayout;

    public function __construct(
        private readonly MonitoringSafetyEngineeringExcelTemplateService $templateService,
        private readonly MonitoringSafetyEngineeringExcelImportService $importService,
    ) {}

    public function index(): View
    {
        $currentYear = (int) now()->format('Y');

        return view('MonitoringSafetyEngginering.upload.index', $this->monitoringSafetyEngineeringViewData('upload', [
            'tablesReady' => Schema::hasTable('monitoring_safety_engineering_records'),
            'planYears' => range($currentYear - 1, $currentYear + 2),
            'columnCount' => 41,
        ]));
    }

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = $this->templateService->buildSpreadsheet();
        $filename = 'template-monitoring-safety-engineering-' . date('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(MonitoringSafetyEngineeringImportRequest $request): RedirectResponse
    {
        if (! Schema::hasTable('monitoring_safety_engineering_records')) {
            return back()->with('error', 'Tabel database belum tersedia. Jalankan migration terlebih dahulu.');
        }

        $file = $request->file('excel_file');
        if ($file === null) {
            return back()->with('error', 'File Excel tidak ditemukan.');
        }

        try {
            $result = $this->importService->importFromFile(
                $file->getRealPath(),
                (int) $request->validated('period_year'),
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal membaca file Excel: ' . $e->getMessage());
        }

        if (! empty($result['header_invalid'])) {
            return back()
                ->with('error', 'Upload ditolak: format kolom Excel tidak sesuai template.')
                ->with('importErrors', $result['errors']);
        }

        if ($result['imported'] === 0 && $result['errors'] !== []) {
            return back()
                ->with('error', 'Import gagal. Periksa error di bawah.')
                ->with('importErrors', $result['errors']);
        }

        $message = 'Import berhasil: ' . $result['imported'] . ' baris data tersimpan.';

        if ($result['errors'] !== []) {
            return back()
                ->with('success', $message)
                ->with('importErrors', $result['errors']);
        }

        return back()->with('success', $message);
    }
}
