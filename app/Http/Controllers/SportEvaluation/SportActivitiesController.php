<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\SportEvaluation\SportEvaluationWorkoutActivityRequest;
use App\Services\SportEvaluation\SportEvaluationWorkoutActivityService;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Dashboard tren aktivitas olahraga WELL (workout_analyses + food_analyses).
 */
final class SportActivitiesController extends Controller
{
    public function __construct(
        private readonly SportEvaluationWorkoutActivityService $service,
    ) {
    }

    public function index(SportEvaluationWorkoutActivityRequest $request): View
    {
        return view('evaluasi-well.activities.index', $this->service->dashboard($request));
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->service->datatable($request));
    }

    public function rawData(Request $request): JsonResponse
    {
        return response()->json($this->service->rawDatatable($request));
    }

    public function export(SportEvaluationWorkoutActivityRequest $request): JsonResponse
    {
        try {
            $payload = $this->service->exportPayload($request);
            $filters = $payload['filters'];

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
                'No',
                'Nama',
                'Kode SID',
                'Site',
                'Perusahaan',
                'Divisi',
                'Sesi',
                'Sesi/Minggu',
                'Durasi (menit)',
                'Jarak lari/jalan (km)',
                'Kkal keluar',
                'Kkal masuk',
                'Net kkal (indikator log)',
                'Olahraga terakhir',
            ]);
            $spreadsheet->getActiveSheet()->setTitle('Ringkasan Karyawan');
            $this->fillRows($spreadsheet->getActiveSheet(), $payload['users'], static function (int $index, array $row): array {
                return [
                    $index + 1,
                    $row['nama'],
                    $row['kode_sid'],
                    $row['site'],
                    $row['company'],
                    $row['divisi'],
                    $row['sesi'],
                    $row['sessions_per_week'],
                    $row['duration_minutes'],
                    $row['distance_km'],
                    $row['kcal_out'],
                    $row['kcal_in'],
                    $row['kcal_net'],
                    $row['last_workout_at'],
                ];
            });

            $workoutSheet = SpreadsheetExporter::addSheetWithHeaders($spreadsheet, 'Log Olahraga Raw', [
                'No',
                'ID',
                'User ID',
                'Kode SID',
                'Nama',
                'Site',
                'Perusahaan',
                'Jenis aktivitas',
                'Kkal (raw)',
                'Kkal (parse)',
                'Durasi (raw)',
                'Durasi (menit)',
                'Jarak (raw)',
                'Jarak km (lari/jalan)',
                'Avg heart rate',
                'Waktu',
            ]);
            $this->fillRows($workoutSheet, $payload['rawWorkouts'], static function (int $index, array $row): array {
                return [
                    $index + 1,
                    $row['id'],
                    $row['user_id'],
                    $row['kode_sid'],
                    $row['nama'],
                    $row['site'],
                    $row['company'],
                    $row['activity_type'],
                    $row['calories_kcal_raw'],
                    $row['calories_kcal'],
                    $row['workout_time_raw'],
                    $row['duration_minutes'],
                    $row['distance_raw'],
                    $row['distance_km'],
                    $row['avg_heart_rate'],
                    $row['local_datetime'],
                ];
            });

            $foodSheet = SpreadsheetExporter::addSheetWithHeaders($spreadsheet, 'Log Makanan Raw', [
                'No',
                'ID',
                'User ID',
                'Kode SID',
                'Nama',
                'Site',
                'Perusahaan',
                'Nama makanan',
                'Meal type',
                'Total kkal',
                'Waktu',
            ]);
            $this->fillRows($foodSheet, $payload['rawFoods'], static function (int $index, array $row): array {
                return [
                    $index + 1,
                    $row['id'],
                    $row['user_id'],
                    $row['kode_sid'],
                    $row['nama'],
                    $row['site'],
                    $row['company'],
                    $row['food_name'],
                    $row['meal_type'],
                    $row['total_calories'],
                    $row['created_at'],
                ];
            });

            $trend = $payload['trendDaily'];
            $trendRows = [];
            foreach ($trend['labels'] as $i => $label) {
                $trendRows[] = [
                    'tanggal' => $label,
                    'sesi' => $trend['sesi'][$i] ?? 0,
                    'users' => $trend['users'][$i] ?? 0,
                    'menit' => $trend['menit'][$i] ?? 0,
                    'km' => $trend['km'][$i] ?? 0,
                    'kcal_out' => $trend['kcal_out'][$i] ?? 0,
                    'kcal_in' => $trend['kcal_in'][$i] ?? 0,
                ];
            }
            $trendSheet = SpreadsheetExporter::addSheetWithHeaders($spreadsheet, 'Tren Harian', [
                'No',
                'Tanggal',
                'Sesi',
                'Karyawan unik',
                'Durasi (menit)',
                'Jarak (km)',
                'Kkal keluar',
                'Kkal masuk',
            ]);
            $this->fillRows($trendSheet, $trendRows, static function (int $index, array $row): array {
                return [
                    $index + 1,
                    $row['tanggal'],
                    $row['sesi'],
                    $row['users'],
                    $row['menit'],
                    $row['km'],
                    $row['kcal_out'],
                    $row['kcal_in'],
                ];
            });

            $spreadsheet->setActiveSheetIndex(0);

            SpreadsheetExporter::download(
                $spreadsheet,
                'evaluasi_well_tren_aktivitas_'.$filters['from'].'_'.$filters['to'].'_'.date('Y-m-d_His').'.xlsx'
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal mengekspor data tren aktivitas.'], 500);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(int, array<string, mixed>): list<mixed>  $mapper
     */
    private function fillRows(Worksheet $sheet, array $rows, callable $mapper): void
    {
        $rowNum = 2;
        foreach ($rows as $index => $row) {
            $sheet->fromArray($mapper($index, $row), null, 'A'.$rowNum);
            $rowNum++;
        }
    }
}
