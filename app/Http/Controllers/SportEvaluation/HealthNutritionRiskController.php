<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Services\SportEvaluation\HealthNutritionDummyDataProvider;
use App\Services\SportEvaluation\HealthNutritionRiskService;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Dashboard Risiko MCU metabolik × pola makan.
 *
 * Desain baru (2026-09-18) sedang tampil dengan data dummy dari
 * HealthNutritionDummyDataProvider sambil menunggu HealthNutritionRiskService
 * dipetakan ulang ke struktur data yang sama. $service tetap di-inject supaya
 * logAccess() (audit log, tanpa query berat) tetap jalan.
 */
final class HealthNutritionRiskController extends Controller
{
    public function __construct(
        private readonly HealthNutritionRiskService $service,
        private readonly HealthNutritionDummyDataProvider $dummy,
    ) {}

    public function index(Request $request): View
    {
        $this->service->logAccess('evaluasi-well.health-nutrition.index', $request->query());

        return view('evaluasi-well.health-nutrition.index', $this->dummy->dashboard());
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->dummy->dashboard()['employeeTable']['rows']]);
    }

    public function export(Request $request): JsonResponse
    {
        $this->service->logAccess('evaluasi-well.health-nutrition.export', $request->query());

        try {
            $rows = $this->dummy->dashboard()['employeeTable']['rows'];

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
                'No',
                'Nama',
                'NIK',
                'Perusahaan',
                'Site',
                'BMI',
                'Kolesterol',
                'LDL',
                'Trigliserida',
                'Tensi',
                'GDS',
                'Risiko',
                'Status Nutrisi',
            ]);
            $sheet = $spreadsheet->getActiveSheet();

            $rowNum = 2;
            foreach ($rows as $row) {
                $sheet->fromArray([
                    $row['no'],
                    $row['nama'],
                    $row['nik'],
                    $row['perusahaan'],
                    $row['site'],
                    $row['bmi'],
                    $row['kolesterol'],
                    $row['ldl'],
                    $row['trigliserida'],
                    $row['tensi'],
                    $row['gds'],
                    $row['risiko_label'],
                    $row['status_nutrisi'],
                ], null, 'A'.$rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download(
                $spreadsheet,
                'evaluasi_well_mcu_nutrisi_'.date('Y-m-d_His').'.xlsx'
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal mengekspor data.'], 500);
        }
    }
}
