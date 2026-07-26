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
        // #region agent log
        $__dbgT0 = microtime(true);
        file_put_contents(base_path('debug-8686de.log'), json_encode(['sessionId' => '8686de', 'hypothesisId' => 'A', 'location' => 'HealthNutritionRiskController::index', 'message' => 'index_enter', 'data' => ['uri' => $request->getRequestUri()], 'timestamp' => (int) round(microtime(true) * 1000)], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
        // #endregion

        $filters = $this->service->readFilters($request);
        $this->service->logAccess('evaluasi-well.health-nutrition.index', $filters);

        $data = $this->service->dashboard($filters);

        // #region agent log
        file_put_contents(base_path('debug-8686de.log'), json_encode(['sessionId' => '8686de', 'hypothesisId' => 'A', 'location' => 'HealthNutritionRiskController::index', 'message' => 'index_exit', 'data' => ['ms' => (int) round((microtime(true) - $__dbgT0) * 1000), 'mcuUp' => $data['mcuUp'] ?? null, 'bewellUp' => $data['bewellUp'] ?? null, 'mcuAbnormal' => $data['kpi']['mcu_abnormal'] ?? null], 'timestamp' => (int) round(microtime(true) * 1000)], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
        // #endregion

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
