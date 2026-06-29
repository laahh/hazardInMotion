<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\SpreadsheetExporter;
use Carbon\Carbon;

class CctvAlertsDashboardController extends Controller
{
    /**
     * Display the CCTV Alerts Dashboard
     */
    public function index()
    {
        return view('cctv-alerts-dashboard.index');
    }

    /**
     * Get chart data based on filter type
     */
    public function getChartData(Request $request)
    {
        $filterType = $request->get('filter_type', 'month'); // month, week, day
        $filterValue = $request->get('filter_value'); // YYYY-MM, YYYY-WW, YYYY-MM-DD
        $site = $request->get('site'); // Optional site filter

        $query = DB::table('cctv_alerts');

        // Apply site filter if provided
        if ($site) {
            $query->where('site', $site);
        }

        $data = [];
        $labels = [];
        $onlineData = [];
        $offlineData = [];

        switch ($filterType) {
            case 'month':
                // Filter by month, show data per week
                if ($filterValue) {
                    [$year, $month] = explode('-', $filterValue);
                    $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                    $endDate = $startDate->copy()->endOfMonth();
                    
                    $query->whereBetween('tanggal', [
                        $startDate->format('Y-m-d H:i:s'),
                        $endDate->format('Y-m-d H:i:s')
                    ]);
                } else {
                    // Default to current month
                    $startDate = Carbon::now()->startOfMonth();
                    $endDate = Carbon::now()->endOfMonth();
                    $query->whereBetween('tanggal', [
                        $startDate->format('Y-m-d H:i:s'),
                        $endDate->format('Y-m-d H:i:s')
                    ]);
                }

                // Group by week (MySQL syntax)
                $results = $query->selectRaw('
                    DATE_SUB(tanggal, INTERVAL WEEKDAY(tanggal) DAY) as week_start,
                    CAST(AVG(jumlah_online) AS UNSIGNED) as avg_online,
                    CAST(AVG(jumlah_offline) AS UNSIGNED) as avg_offline
                ')
                ->groupByRaw('DATE_SUB(tanggal, INTERVAL WEEKDAY(tanggal) DAY)')
                ->orderBy('week_start')
                ->get();

                foreach ($results as $result) {
                    $weekStart = Carbon::parse($result->week_start);
                    $labels[] = 'Minggu ' . $weekStart->format('d M');
                    $onlineData[] = $result->avg_online;
                    $offlineData[] = $result->avg_offline;
                }
                break;

            case 'week':
                // Filter by week, show data per day (Monday to Sunday)
                if ($filterValue) {
                    // Format: YYYY-WW (e.g., 2026-W03)
                    if (strpos($filterValue, '-W') !== false) {
                        [$year, $week] = explode('-W', $filterValue);
                        $startDate = Carbon::now()->setISODate((int)$year, (int)$week, 1)->startOfWeek();
                        $endDate = $startDate->copy()->endOfWeek();
                    } else {
                        // Try to parse as date and get week
                        $date = Carbon::parse($filterValue);
                        $startDate = $date->copy()->startOfWeek();
                        $endDate = $date->copy()->endOfWeek();
                    }
                    $query->whereBetween('tanggal', [
                        $startDate->format('Y-m-d H:i:s'),
                        $endDate->format('Y-m-d H:i:s')
                    ]);
                } else {
                    // Default to current week
                    $startDate = Carbon::now()->startOfWeek();
                    $endDate = Carbon::now()->endOfWeek();
                    $query->whereBetween('tanggal', [
                        $startDate->format('Y-m-d H:i:s'),
                        $endDate->format('Y-m-d H:i:s')
                    ]);
                }

                // Group by day
                $results = $query->selectRaw('
                    DATE(tanggal) as date,
                    CAST(AVG(jumlah_online) AS UNSIGNED) as avg_online,
                    CAST(AVG(jumlah_offline) AS UNSIGNED) as avg_offline
                ')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

                $dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
                foreach ($results as $result) {
                    $date = Carbon::parse($result->date);
                    $dayName = $dayNames[$date->dayOfWeek == 0 ? 6 : $date->dayOfWeek - 1];
                    $labels[] = $dayName . ' ' . $date->format('d M');
                    $onlineData[] = $result->avg_online;
                    $offlineData[] = $result->avg_offline;
                }
                break;

            case 'day':
                // Filter by day, show data per minute
                if ($filterValue) {
                    $date = Carbon::parse($filterValue);
                    $query->whereDate('tanggal', $date->format('Y-m-d'));
                } else {
                    // Default to today
                    $query->whereDate('tanggal', Carbon::today());
                }

                // Group by minute (MySQL syntax)
                $results = $query->selectRaw('
                    DATE_FORMAT(tanggal, \'%Y-%m-%d %H:%i:00\') as minute,
                    CAST(AVG(jumlah_online) AS UNSIGNED) as avg_online,
                    CAST(AVG(jumlah_offline) AS UNSIGNED) as avg_offline
                ')
                ->groupByRaw('DATE_FORMAT(tanggal, \'%Y-%m-%d %H:%i:00\')')
                ->orderBy('minute')
                ->get();

                foreach ($results as $result) {
                    $minute = Carbon::parse($result->minute);
                    $labels[] = $minute->format('H:i');
                    $onlineData[] = $result->avg_online;
                    $offlineData[] = $result->avg_offline;
                }
                break;
        }

        return response()->json([
            'success' => true,
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Jumlah Online',
                    'data' => $onlineData,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.4
                ],
                [
                    'label' => 'Jumlah Offline',
                    'data' => $offlineData,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4
                ]
            ]
        ]);
    }

    /**
     * Get data for DataTable
     */
    public function getDataTableData(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get('start', 0);
        $length = $request->get('length', 10);
        $search = $request->get('search')['value'] ?? '';
        $filterType = $request->get('filter_type', 'month');
        $filterValue = $request->get('filter_value');
        $site = $request->get('site');

        $query = DB::table('cctv_alerts');

        // Apply filters
        if ($site) {
            $query->where('site', $site);
        }

        if ($filterType && $filterValue) {
            switch ($filterType) {
                case 'month':
                    [$year, $month] = explode('-', $filterValue);
                    $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                    $endDate = $startDate->copy()->endOfMonth();
                    $query->whereBetween('tanggal', [
                        $startDate->format('Y-m-d H:i:s'),
                        $endDate->format('Y-m-d H:i:s')
                    ]);
                    break;
                case 'week':
                    if (strpos($filterValue, '-W') !== false) {
                        [$year, $week] = explode('-W', $filterValue);
                        $startDate = Carbon::now()->setISODate((int)$year, (int)$week, 1)->startOfWeek();
                        $endDate = $startDate->copy()->endOfWeek();
                    } else {
                        $date = Carbon::parse($filterValue);
                        $startDate = $date->copy()->startOfWeek();
                        $endDate = $date->copy()->endOfWeek();
                    }
                    $query->whereBetween('tanggal', [
                        $startDate->format('Y-m-d H:i:s'),
                        $endDate->format('Y-m-d H:i:s')
                    ]);
                    break;
                case 'day':
                    $date = Carbon::parse($filterValue);
                    $query->whereDate('tanggal', $date->format('Y-m-d'));
                    break;
            }
        }

        // Apply search (MySQL syntax)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('site', 'LIKE', "%{$search}%")
                  ->orWhereRaw("DATE_FORMAT(tanggal, '%Y-%m-%d %H:%i:%s') LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("CAST(jumlah_online AS CHAR) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("CAST(jumlah_offline AS CHAR) LIKE ?", ["%{$search}%"]);
            });
        }

        // Get total records before search
        $totalRecords = DB::table('cctv_alerts')->count();
        
        // Get filtered records count
        $filteredRecords = $query->count();

        // Apply pagination
        $data = $query->orderBy('tanggal', 'desc')
            ->orderBy('site')
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'site' => $item->site,
                    'tanggal' => Carbon::parse($item->tanggal)->format('d M Y H:i:s'),
                    'jumlah_online' => $item->jumlah_online,
                    'jumlah_offline' => $item->jumlah_offline,
                    'message_id' => $item->message_id,
                    'created_at' => Carbon::parse($item->created_at)->format('d M Y H:i:s'),
                ];
            });

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data
        ]);
    }

    /**
     * Get available sites
     */
    public function getSites()
    {
        $sites = DB::table('cctv_alerts')
            ->select('site')
            ->distinct()
            ->orderBy('site')
            ->pluck('site');

        return response()->json([
            'success' => true,
            'sites' => $sites
        ]);
    }

    /**
     * Export data CCTV alerts ke Excel (mendukung filter yang sama dengan DataTable).
     */
    public function exportExcel(Request $request)
    {
        try {
            $headers = [
                'No', 'Site', 'Tanggal', 'Jumlah Online', 'Jumlah Offline', 'Message ID', 'Created At',
            ];

            $query = DB::table('cctv_alerts');

            if ($request->filled('site')) {
                $query->where('site', $request->site);
            }

            $filterType = $request->get('filter_type', 'month');
            $filterValue = $request->get('filter_value');

            if ($filterType && $filterValue) {
                switch ($filterType) {
                    case 'month':
                        [$year, $month] = explode('-', $filterValue);
                        $startDate = Carbon::create((int) $year, (int) $month, 1)->startOfMonth();
                        $endDate = $startDate->copy()->endOfMonth();
                        $query->whereBetween('tanggal', [
                            $startDate->format('Y-m-d H:i:s'),
                            $endDate->format('Y-m-d H:i:s'),
                        ]);
                        break;
                    case 'week':
                        if (strpos($filterValue, '-W') !== false) {
                            [$year, $week] = explode('-W', $filterValue);
                            $startDate = Carbon::now()->setISODate((int) $year, (int) $week, 1)->startOfWeek();
                            $endDate = $startDate->copy()->endOfWeek();
                        } else {
                            $date = Carbon::parse($filterValue);
                            $startDate = $date->copy()->startOfWeek();
                            $endDate = $date->copy()->endOfWeek();
                        }
                        $query->whereBetween('tanggal', [
                            $startDate->format('Y-m-d H:i:s'),
                            $endDate->format('Y-m-d H:i:s'),
                        ]);
                        break;
                    case 'day':
                        $date = Carbon::parse($filterValue);
                        $query->whereDate('tanggal', $date->format('Y-m-d'));
                        break;
                }
            }

            $rows = $query->orderByDesc('tanggal')->orderBy('site')->get();

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders($headers);
            $sheet = $spreadsheet->getActiveSheet();

            $rowNum = 2;
            foreach ($rows as $index => $item) {
                $sheet->fromArray([
                    $index + 1,
                    $item->site ?? '',
                    $item->tanggal ? Carbon::parse($item->tanggal)->format('Y-m-d H:i:s') : '',
                    $item->jumlah_online ?? 0,
                    $item->jumlah_offline ?? 0,
                    $item->message_id ?? '',
                    $item->created_at ? Carbon::parse($item->created_at)->format('Y-m-d H:i:s') : '',
                ], null, 'A' . $rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download($spreadsheet, 'cctv_alerts_' . date('Y-m-d_His') . '.xlsx');
        } catch (\Throwable $e) {
            Log::error('CctvAlertsDashboardController exportExcel: ' . $e->getMessage());

            return redirect()
                ->route('cctv-alerts-dashboard.index')
                ->with('error', 'Gagal mengexport data Excel: ' . $e->getMessage());
        }
    }
}

