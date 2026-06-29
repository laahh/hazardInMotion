<?php

namespace App\Http\Controllers;

use App\Models\SupervisoryAlertLog;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupervisoryAlertLogController extends Controller
{
    /** Kolom untuk ordering DataTables (index 6 = actions, pakai updated_at) */
    private const COLUMNS = ['tanggal', 'nama_lokasi', 'risk_level', 'has_sap_report', 'has_online_cctv', 'updated_at', 'updated_at'];

    /**
     * Halaman alert log dengan tab (tab pertama: Supervisory).
     */
    public function index()
    {
        return view('supervisory-alert-log.index');
    }

    /**
     * Data untuk DataTables (server-side).
     */
    public function getData(Request $request)
    {
        try {
            $draw = (int) $request->get('draw', 1);
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 25);
            $length = min(max($length, 1), 100);
            $searchValue = $request->get('search')['value'] ?? '';
            $order = $request->get('order', []);
            $orderColIndex = isset($order[0]['column']) ? (int) $order[0]['column'] : 0;
            $orderDir = isset($order[0]['dir']) && strtolower($order[0]['dir']) === 'asc' ? 'asc' : 'desc';

            $orderColumn = self::COLUMNS[$orderColIndex] ?? 'tanggal';

            $query = SupervisoryAlertLog::query();

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            }
            if ($request->filled('risk_level') && in_array($request->risk_level, [SupervisoryAlertLog::RISK_HIGH, SupervisoryAlertLog::RISK_MEDIUM], true)) {
                $query->where('risk_level', $request->risk_level);
            }
            if ($searchValue !== '') {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('nama_lokasi', 'like', '%' . $searchValue . '%')
                        ->orWhere('risk_level', 'like', '%' . $searchValue . '%')
                        ->orWhere('id_lokasi', 'like', '%' . $searchValue . '%');
                });
            }

            $recordsTotal = SupervisoryAlertLog::query()->count();
            $recordsFiltered = $query->count();

            $rows = $query->orderBy($orderColumn, $orderDir)
                ->skip($start)
                ->take($length)
                ->get();

            $data = $rows->map(function ($row) {
                $riskBadge = $row->risk_level === 'HIGH'
                    ? '<span class="badge bg-danger">HIGH</span>'
                    : ($row->risk_level === 'MEDIUM'
                        ? '<span class="badge bg-warning text-dark">MEDIUM</span>'
                        : '<span class="badge bg-secondary">' . e($row->risk_level) . '</span>');

                $lokasiEsc = e($row->nama_lokasi ?? '');
                $actionsBtn = '<div class="d-flex flex-wrap gap-1">';
                $actionsBtn .= '<button type="button" class="btn btn-sm btn-outline-primary btn-view-matrix" data-id="' . (int) $row->id . '" title="Lihat Hasil Matriks TARP"><i class="material-icons-outlined me-1" style="font-size:16px;vertical-align:middle;">visibility</i> Hasil Matriks</button>';
                $actionsBtn .= '<button type="button" class="btn btn-sm btn-outline-success btn-intervensi" data-lokasi="' . $lokasiEsc . '" title="Intervensi"><i class="material-icons-outlined me-1" style="font-size:16px;vertical-align:middle;">campaign</i> Intervensi</button>';
                $actionsBtn .= '</div>';

                return [
                    'tanggal' => $row->tanggal ? $row->tanggal->format('d/m/Y') : '-',
                    'nama_lokasi' => $row->nama_lokasi ?? '-',
                    'risk_level' => $riskBadge,
                    'has_sap_report' => $row->has_sap_report ? '<span class="text-success">Ya</span>' : '<span class="text-muted">Tidak</span>',
                    'has_online_cctv' => $row->has_online_cctv ? '<span class="text-success">Ya</span>' : '<span class="text-muted">Tidak</span>',
                    'updated_at' => $row->updated_at ? $row->updated_at->format('d/m/Y H:i') : '-',
                    'actions' => $actionsBtn,
                ];
            })->toArray();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error('SupervisoryAlertLogController getData: ' . $e->getMessage());
            return response()->json([
                'draw' => (int) $request->get('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal memuat data.',
            ], 500);
        }
    }

    /**
     * Detail satu log untuk modal Hasil Matriks (TARP, CCTV, SAP).
     */
    public function getDetail(int $id)
    {
        $log = SupervisoryAlertLog::find($id);
        if (! $log) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $log->id,
                'tanggal' => $log->tanggal ? $log->tanggal->format('d/m/Y') : null,
                'nama_lokasi' => $log->nama_lokasi,
                'id_lokasi' => $log->id_lokasi,
                'risk_level' => $log->risk_level,
                'has_sap_report' => $log->has_sap_report,
                'has_online_cctv' => $log->has_online_cctv,
                'is_high_risk_area' => $log->is_high_risk_area,
                'has_sap_in_high_risk' => $log->is_high_risk_area && $log->has_sap_report,
                'tarp_recommendations' => $log->tarp_recommendations ?? [],
                'cctv_list' => $log->cctv_list ?? [],
                'sap_list' => $log->sap_list ?? [],
                'updated_at' => $log->updated_at ? $log->updated_at->format('d/m/Y H:i') : null,
            ],
        ]);
    }

    /**
     * Data untuk tab Alert Mobility (Unit & Orang).
     * Sumber data dapat diintegrasikan dari sistem Mobility; sementara mengembalikan struktur kosong.
     */
    public function getDataMobility(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [],
            'total' => 0,
        ]);
    }

    /**
     * Export data alert supervisory ke Excel (mendukung filter tanggal & risk level).
     */
    public function exportExcel(Request $request)
    {
        try {
            $headers = [
                'No', 'Tanggal', 'ID Lokasi', 'Nama Lokasi', 'Risk Level',
                'SAP Report', 'CCTV Online', 'High Risk Area', 'TARP Recommendations',
                'CCTV List', 'SAP List', 'Created At', 'Updated At',
            ];

            $query = SupervisoryAlertLog::query();

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            }
            if ($request->filled('risk_level') && in_array($request->risk_level, [SupervisoryAlertLog::RISK_HIGH, SupervisoryAlertLog::RISK_MEDIUM], true)) {
                $query->where('risk_level', $request->risk_level);
            }

            $rows = $query->orderByDesc('tanggal')->orderByDesc('updated_at')->get();

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders($headers);
            $sheet = $spreadsheet->getActiveSheet();

            $rowNum = 2;
            foreach ($rows as $index => $row) {
                $sheet->fromArray([
                    $index + 1,
                    $row->tanggal ? $row->tanggal->format('Y-m-d') : '',
                    $row->id_lokasi ?? '',
                    $row->nama_lokasi ?? '',
                    $row->risk_level ?? '',
                    $row->has_sap_report ? 'Ya' : 'Tidak',
                    $row->has_online_cctv ? 'Ya' : 'Tidak',
                    $row->is_high_risk_area ? 'Ya' : 'Tidak',
                    $row->tarp_recommendations ? json_encode($row->tarp_recommendations, JSON_UNESCAPED_UNICODE) : '',
                    $row->cctv_list ? json_encode($row->cctv_list, JSON_UNESCAPED_UNICODE) : '',
                    $row->sap_list ? json_encode($row->sap_list, JSON_UNESCAPED_UNICODE) : '',
                    $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '',
                    $row->updated_at ? $row->updated_at->format('Y-m-d H:i:s') : '',
                ], null, 'A' . $rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download($spreadsheet, 'supervisory_alert_log_' . date('Y-m-d_His') . '.xlsx');
        } catch (\Throwable $e) {
            Log::error('SupervisoryAlertLogController exportExcel: ' . $e->getMessage());

            return redirect()
                ->route('supervisory-alert-log.index')
                ->with('error', 'Gagal mengexport data Excel: ' . $e->getMessage());
        }
    }
}
