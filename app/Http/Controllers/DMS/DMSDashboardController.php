<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Models\SafetyScoreLog;
use App\Models\DmsCalibration;
use App\Services\Dms\DmsDashboardOverviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DMSDashboardController extends Controller
{
    public function __construct(
        private readonly DmsDashboardOverviewService $overview,
    ) {}

    /**
     * Display DMS Dashboard (layout WowDash CRM, data mv_dms_alert).
     */
    public function index(): View
    {
        return view('dms.dashboard', $this->overview->dashboard());
    }

    /**
     * Get realtime data for dashboard
     */
    public function getRealtimeData(Request $request): JsonResponse
    {
        try {
            $driverId = $request->input('driver_id');
            $limit = $request->input('limit', 100); // Default 100 data points
            $minutes = $request->input('minutes', 60); // Default last 60 minutes

            $query = SafetyScoreLog::query()
                ->orderBy('timestamp', 'desc');

            // Filter by driver if provided
            if ($driverId) {
                $query->where('driver_id', $driverId);
            }

            // Filter by time range
            if ($minutes) {
                $query->where('timestamp', '>=', now()->subMinutes($minutes));
            }

            // Get latest data
            $logs = $query->limit($limit)->get();

            // Group by driver for multiple operators
            $drivers = SafetyScoreLog::select('driver_id')
                ->where('timestamp', '>=', now()->subMinutes($minutes))
                ->distinct()
                ->orderBy('driver_id')
                ->pluck('driver_id');

            // Get latest calibration for each driver
            $calibrations = DmsCalibration::whereIn('driver_id', $drivers)
                ->orderBy('calibration_start_time', 'desc')
                ->get()
                ->groupBy('driver_id')
                ->map(function ($calibs) {
                    return $calibs->first();
                });

            $data = [];
            foreach ($drivers as $driver) {
                $driverLogs = $logs->where('driver_id', $driver)->sortBy('timestamp')->values();
                
                if ($driverLogs->isEmpty()) {
                    continue;
                }

                $latest = $driverLogs->last();
                
                // Get calibration data for this driver
                $calibration = $calibrations->get($driver);
                $threshold = $calibration ? (float) $calibration->t_close : 0.2; // Default threshold
                $earMean = $calibration ? (float) $calibration->ear_mean : 0.28;
                $earSd = $calibration ? (float) $calibration->ear_sd : 0.02;
                
                // Calculate EAR band (mean ± 1 SD)
                $earBandLow = max(0.1, $earMean - $earSd);
                $earBandHigh = min(0.36, $earMean + $earSd);
                
                // Calculate slope (EAR change per minute) from recent data
                $recentLogs = $driverLogs->take(10)->values();
                $slope = 0;
                if ($recentLogs->count() >= 2) {
                    $first = $recentLogs->first();
                    $last = $recentLogs->last();
                    $timeDiff = ($last->timestamp->timestamp - $first->timestamp->timestamp) / 60; // minutes
                    if ($timeDiff > 0 && $first->ear && $last->ear) {
                        $slope = ($last->ear - $first->ear) / $timeDiff;
                    }
                }

                // Prepare chart data - focus on EAR data
                $chartData = [
                    'labels' => $driverLogs->map(function ($log) {
                        return $log->timestamp->format('H:i:s');
                    })->toArray(),
                    'safetyScore' => $driverLogs->map(function ($log) {
                        return (float) ($log->safety_score ?? 0);
                    })->toArray(),
                    'fatigue' => $driverLogs->map(function ($log) {
                        return (float) ($log->fatigue ?? 0);
                    })->toArray(),
                    'drift' => $driverLogs->map(function ($log) {
                        return (float) ($log->drift ?? 0);
                    })->toArray(),
                    'ear' => $driverLogs->map(function ($log) {
                        return (float) ($log->ear ?? 0);
                    })->toArray(),
                ];

                $data[] = [
                    'driver_id' => $driver,
                    'latest' => [
                        'safety_score' => (float) ($latest->safety_score ?? 0),
                        'fatigue' => (float) ($latest->fatigue ?? 0),
                        'drift' => (float) ($latest->drift ?? 0),
                        'perclos_60s' => (float) ($latest->perclos_60s ?? 0),
                        'blink_60s' => (int) ($latest->blink_60s ?? 0),
                        'microsleep_60s' => (int) ($latest->microsleep_60s ?? 0),
                        'status' => $latest->status ?? 'Safe',
                        'ear' => (float) ($latest->ear ?? 0),
                        'slope_ear_per_min' => (float) round($slope, 4),
                        'timestamp' => $latest->timestamp->toISOString(),
                        // Add calibration data for chart
                        'ear_threshold' => $threshold,
                        'ear_band_low' => $earBandLow,
                        'ear_band_high' => $earBandHigh,
                    ],
                    'chart_data' => $chartData,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch realtime data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics summary
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $minutes = $request->input('minutes', 60);

            $query = SafetyScoreLog::where('timestamp', '>=', now()->subMinutes($minutes));

            $stats = [
                'total_drivers' => $query->distinct('driver_id')->count('driver_id'),
                'total_records' => $query->count(),
                'avg_safety_score' => round($query->avg('safety_score') ?? 0, 2),
                'avg_fatigue' => round($query->avg('fatigue') ?? 0, 2),
                'avg_drift' => round($query->avg('drift') ?? 0, 2),
                'status_distribution' => [
                    'Safe' => $query->where('status', 'Safe')->count(),
                    'Caution' => $query->where('status', 'Caution')->count(),
                    'Attention' => $query->where('status', 'Attention')->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get safety score logs for a specific driver
     */
    public function getDriverLogs(Request $request): JsonResponse
    {
        try {
            $driverId = $request->input('driver_id');
            
            if (!$driverId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Driver ID is required',
                ], 400);
            }

            $limit = $request->input('limit', 1000); // Default 1000 records
            $minutes = $request->input('minutes', null); // Optional time filter

            $query = SafetyScoreLog::where('driver_id', $driverId)
                ->orderBy('timestamp', 'desc');

            // Filter by time range if provided
            if ($minutes) {
                $query->where('timestamp', '>=', now()->subMinutes($minutes));
            }

            $logs = $query->limit($limit)->get();

            // Format the data
            // Set timezone to Asia/Jakarta (WIB) for display
            // Laravel stores timestamps in UTC by default, so we need to convert to local timezone
            $timezone = 'Asia/Jakarta';
            
            // Set locale to Indonesian for month names
            \Carbon\Carbon::setLocale('id');
            
            $formattedLogs = $logs->map(function ($log) use ($timezone) {
                // Convert timestamp from UTC (database) to Asia/Jakarta timezone
                // The timestamp is already a Carbon instance due to the cast in the model
                $timestamp = $log->timestamp->setTimezone($timezone);
                
                // Format timestamp: "12 jan 2026 10:15:12"
                $formattedTimestamp = $timestamp->format('d') . ' ' . 
                    strtolower($timestamp->format('M')) . ' ' . 
                    $timestamp->format('Y H:i:s');
                
                return [
                    'id' => $log->id,
                    'driver_id' => $log->driver_id,
                    'timestamp' => $formattedTimestamp,
                    'ear' => $log->ear ? number_format((float)$log->ear, 6, ',', '') : null,
                    'perclos_60s' => $log->perclos_60s ? number_format((float)$log->perclos_60s, 6, ',', '') : null,
                    'blink_60s' => $log->blink_60s,
                    'microsleep_60s' => $log->microsleep_60s,
                    'fatigue' => $log->fatigue ? number_format((float)$log->fatigue, 6, ',', '') : null,
                    'drift' => $log->drift ? number_format((float)$log->drift, 6, ',', '') : null,
                    'safety_score' => $log->safety_score ? number_format((float)$log->safety_score, 6, ',', '') : null,
                    'status' => $log->status,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedLogs,
                'count' => $formattedLogs->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch driver logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get DMS true alerts (EAR fatigue) for table: driver_sid, driver_name, warning_type, l1_context_status, event_time, alert
     */
    public function getTrueAlerts(Request $request): JsonResponse
    {
        try {
            $minutes = $request->input('minutes', 60);
            $limit = $request->input('limit', 100);

            $query = SafetyScoreLog::query()
                ->where('timestamp', '>=', now()->subMinutes($minutes))
                ->where(function ($q) {
                    $q->where('status', 'Attention')
                        ->orWhere('fatigue', '>=', 60);
                })
                ->orderBy('timestamp', 'desc');

            $logs = $query->limit($limit)->get();
            $timezone = 'Asia/Jakarta';

            $data = $logs->map(function ($log) use ($timezone) {
                $ts = $log->timestamp->setTimezone($timezone);
                $eventTime = $ts->format('j/n/Y g:i:s A');
                return [
                    'driver_sid' => $log->driver_id ?? '—',
                    'driver_name' => strtoupper((string) ($log->driver_id ?? '—')),
                    'warning_type' => 'Closedeyes',
                    'l1_context_status' => 'True Alarm',
                    'event_time' => $eventTime,
                    'alert' => 1,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch true alerts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

