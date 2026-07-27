<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Services\SportEvaluation\SportEvaluationWeeklyUploadService;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Ringkasan upload makanan/olahraga per minggu.
 */
final class SportEvaluationWeeklyUploadController extends Controller
{
    public function __construct(
        private readonly SportEvaluationWeeklyUploadService $service,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->service->dashboard($request);

        return view('evaluasi-well.weekly-uploads.index', $data);
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
            $weekKey = str_replace(['\\', '/'], '-', $filters['week']);

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
                'No',
                'Nama',
                'Kode SID',
                'Site',
                'Perusahaan',
                'Departemen',
                'Divisi',
                'Upload Makanan',
                'Upload Olahraga',
                'Total Upload',
                'Upload Terakhir',
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
                    $row['departement'],
                    $row['divisi'],
                    $row['food_count'],
                    $row['workout_count'],
                    $row['total_count'],
                    $row['last_upload_at'],
                ], null, 'A'.$rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download(
                $spreadsheet,
                'evaluasi_well_upload_mingguan_'.$weekKey.'_'.date('Y-m-d_His').'.xlsx'
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal mengekspor data.'], 500);
        }
    }
}