<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Services\SportEvaluation\SportEvaluationPvtDashboardService;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Dashboard operator check-in RFID × status PVT.
 */
final class SportEvaluationPvtDashboardController extends Controller
{
    public function __construct(
        private readonly SportEvaluationPvtDashboardService $service,
    ) {}

    public function index(Request $request): View
    {
        return view('evaluasi-well.pvt.index', $this->service->dashboard($request));
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->service->datatable($request));
    }

    public function export(Request $request): JsonResponse
    {
        try {
            $rows = $this->service->exportRows($request);
            $filters = $this->service->readFilters($request);
            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
                'No',
                'Nama',
                'Kode SID',
                'Site',
                'Perusahaan',
                'Jabatan',
                'Gate',
                'Jam Check-in',
                'Status Lolos',
                'Status PVT',
                'Mean RT (ms)',
                'Median RT (ms)',
                'Lapses',
                'False Starts',
                'Jam Tes PVT',
                'Label Evaluasi',
            ]);
            $sheet = $spreadsheet->getActiveSheet();

            $rowNum = 2;
            foreach ($rows as $index => $row) {
                $sheet->fromArray([
                    $index + 1,
                    $row['nama'],
                    $row['kode_sid'],
                    $row['site'],
                    $row['company'],
                    $row['jabatan'],
                    $row['gate'],
                    $row['checked_in_at'],
                    $row['status_lolos'],
                    $row['pvt_status_label'],
                    $row['mean_rt_ms'],
                    $row['median_rt_ms'],
                    $row['lapses'],
                    $row['false_starts'],
                    $row['tested_at'],
                    $row['evaluation_label'],
                ], null, 'A'.$rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download(
                $spreadsheet,
                'evaluasi_well_pvt_'.$filters['date'].'_'.date('Y-m-d_His').'.xlsx'
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal mengekspor data PVT.'], 500);
        }
    }
}
