<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use App\Services\SportEvaluation\HealthNutritionRiskService;
use App\Services\SportEvaluation\McuNutritionAnalyticsService;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Dashboard Risiko MCU metabolik × pola makan ("MCU x Nutrisi").
 *
 * Desain baru (2026-09-18) tampil dengan data dummy dari
 * McuNutritionAnalyticsService (+ McuNutritionRepository) sambil menunggu
 * HealthNutritionRiskService (join real MCU Postgres + BeWell MySQL yang
 * sudah ada) dipetakan ke kontrak data yang sama — lihat catatan di kedua
 * kelas tsb. $service tetap di-inject supaya logAccess() (audit log, tanpa
 * query berat) tetap jalan.
 */
final class HealthNutritionRiskController extends Controller
{
    public function __construct(
        private readonly HealthNutritionRiskService $service,
        private readonly McuNutritionAnalyticsService $analytics,
    ) {}

    public function index(Request $request): View
    {
        $this->service->logAccess('evaluasi-well.health-nutrition.index', $request->query());

        $data = $this->analytics->dashboard();
        $data['employeeTable'] = ['tabs' => $data['employeeTabs']];

        return view('evaluasi-well.health-nutrition.index', $data);
    }

    /**
     * DataTables server-side: search + filter (tab kondisi/site/perusahaan/status) + order + paginasi
     * dijalankan di sini supaya browser tidak pernah menerima seluruh dataset sekaligus.
     */
    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);

        $rows = $this->analytics->employeeRows();

        $tab = trim((string) $request->input('tab', 'semua'));
        if ($tab !== '' && $tab !== 'semua') {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => in_array($tab, $r['conditions'], true)));
        }

        $site = trim((string) $request->input('site', ''));
        if ($site !== '') {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => $r['site'] === $site));
        }

        $company = trim((string) $request->input('company', ''));
        if ($company !== '') {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => $r['perusahaan'] === $company));
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => $r['status_nutrisi'] === $status));
        }

        $recordsFiltered = count($rows);

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => str_contains(mb_strtolower($r['nama'].' '.$r['nik'].' '.$r['perusahaan']), $needle)
            ));
            $recordsFiltered = count($rows);
        }

        $orderColumnIndex = (int) data_get($request->input('order'), '0.column', 0);
        $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        // Kolom tabel (lihat #healthNutritionTable di Blade): 0 no, 1 nama, 2 perusahaan,
        // 3 site, 4 departemen, 5 bmi, 6 kolesterol, 7 ldl, 8 trigliserida, 9 tensi,
        // 10 gdp, 11 conditions, 12 status_nutrisi, 13 aksi.
        $orderable = [1 => 'nama', 5 => 'bmi', 6 => 'kolesterol', 7 => 'ldl', 8 => 'trigliserida', 10 => 'gdp'];
        if (isset($orderable[$orderColumnIndex])) {
            $key = $orderable[$orderColumnIndex];
            usort($rows, static function (array $a, array $b) use ($key, $orderDir): int {
                $cmp = $a[$key] <=> $b[$key];

                return $orderDir === 'desc' ? -$cmp : $cmp;
            });
        }

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $length = $length < 1 ? 10 : min(100, $length);
        $page = array_slice($rows, $start, $length);

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => count($this->analytics->employeeRows()),
            'recordsFiltered' => $recordsFiltered,
            'data' => $page,
        ]);
    }

    public function employeeDetail(Request $request, int $id): JsonResponse
    {
        $this->service->logAccess('evaluasi-well.health-nutrition.employee-detail', ['id' => $id]);

        $detail = $this->analytics->employeeDetail($id);
        if ($detail === null) {
            return response()->json(['message' => 'Data karyawan tidak ditemukan.'], 404);
        }

        return response()->json($detail);
    }

    public function export(Request $request): JsonResponse
    {
        $this->service->logAccess('evaluasi-well.health-nutrition.export', $request->query());

        try {
            $rows = $this->analytics->employeeRows();

            $tab = trim((string) $request->input('tab', ''));
            if ($tab !== '' && $tab !== 'semua') {
                $rows = array_values(array_filter($rows, static fn (array $r): bool => in_array($tab, $r['conditions'], true)));
            }

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
                'No',
                'Nama',
                'NIK',
                'Perusahaan',
                'Site',
                'Departemen',
                'BMI',
                'Kolesterol',
                'LDL',
                'Trigliserida',
                'Tensi',
                'GDP',
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
                    $row['departemen'],
                    $row['bmi'],
                    $row['kolesterol'],
                    $row['ldl'],
                    $row['trigliserida'],
                    $row['tensi'],
                    $row['gdp'],
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
