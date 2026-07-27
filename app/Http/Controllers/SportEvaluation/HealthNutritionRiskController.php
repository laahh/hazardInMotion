<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Services\SportEvaluation\HealthNutritionRiskService;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Dashboard Risiko MCU metabolik × pola makan.
 */
final class HealthNutritionRiskController extends Controller
{
    public function __construct(
        private readonly HealthNutritionRiskService $service,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->service->readFilters($request);
        $this->service->logAccess('evaluasi-well.health-nutrition.index', $filters);

        $data = $this->service->dashboard($filters);

        return view('evaluasi-well.health-nutrition.index', $data);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->service->datatable($request));
    }

    public function export(Request $request): JsonResponse
    {
        $filters = $this->service->readFilters($request);
        $this->service->logAccess('evaluasi-well.health-nutrition.export', $filters);

        try {
            $rows = $this->service->exportRows($request);

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
                'No',
                'Nama',
                'Kode SID',
                'Perusahaan',
                'Divisi',
                'Temuan MCU',
                'Alert Nutrisi',
                'Hari Log 7h',
                'Rata Kkal',
                'Evidence',
                'Skor Risiko',
            ]);
            $sheet = $spreadsheet->getActiveSheet();

            $rowNum = 2;
            foreach ($rows as $index => $row) {
                $mcuLabels = [];
                foreach ($row['mcu_badges'] as $badge) {
                    $mcuLabels[] = $badge['label'];
                }

                $sheet->fromArray([
                    $index + 1,
                    $row['nama'],
                    $row['kode_sid'],
                    $row['company'],
                    $row['divisi'],
                    implode('; ', $mcuLabels),
                    implode(', ', $row['alert_codes']),
                    $row['days_logged'],
                    $row['avg_calories'],
                    $row['evidence'],
                    $row['risk_score'],
                ], null, 'A'.$rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download(
                $spreadsheet,
                'evaluasi_well_mcu_nutrisi_p1_'.date('Y-m-d_His').'.xlsx'
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal mengekspor data.'], 500);
        }
    }
}
