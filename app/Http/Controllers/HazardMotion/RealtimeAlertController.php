<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Models\CctvData;
use Illuminate\Http\Request;

class RealtimeAlertController extends Controller
{
    /**
     * Display active alerts page
     */
    public function index()
    {
        // Mock data untuk active alerts (akan diganti dengan data real dari database)
        $activeAlerts = [
            [
                'id' => 'ALERT-001',
                'type' => 'Personnel Violation',
                'severity' => 'critical',
                'priority' => 'high',
                'title' => 'Unauthorized Personnel in Restricted Zone',
                'message' => 'Personnel detected in high-risk mining area without proper authorization',
                'location' => ['lat' => -2.186253, 'lng' => 117.4539035],
                'zone' => 'Tambang JOINT MW',
                'cctv_id' => 'CCTV-001',
                'cctv_name' => 'CCTV Main Gate',
                'personnel_name' => 'MOHAMMAD NUR AKBAR HIDAYATULLAH',
                'equipment_id' => null,
                'distance' => '15mtr',
                'detected_at' => now()->subSeconds(45)->format('Y-m-d H:i:s'),
                'duration' => '45 seconds ago',
                'status' => 'unread',
                'acknowledged' => false,
                'acknowledged_by' => null,
                'acknowledged_at' => null,
            ],
            [
                'id' => 'ALERT-002',
                'type' => 'Equipment Violation',
                'severity' => 'high',
                'priority' => 'medium',
                'title' => 'Equipment Operating Outside Designated Area',
                'message' => 'Heavy equipment detected operating outside approved operational zone',
                'location' => ['lat' => -2.1767075, 'lng' => 117.3942385],
                'zone' => 'Block A',
                'cctv_id' => 'CCTV-002',
                'cctv_name' => 'CCTV Block A',
                'personnel_name' => null,
                'equipment_id' => 'CE-6163-CK',
                'equipment_type' => 'Crane Truck',
                'distance' => '49mtr CE',
                'detected_at' => now()->subMinutes(2)->format('Y-m-d H:i:s'),
                'duration' => '2 minutes ago',
                'status' => 'read',
                'acknowledged' => true,
                'acknowledged_by' => 'Supervisor A',
                'acknowledged_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 'ALERT-003',
                'type' => 'Safety Protocol',
                'severity' => 'high',
                'priority' => 'high',
                'title' => 'Safety Protocol Violation Detected',
                'message' => 'Personnel not following safety protocols in operational area',
                'location' => ['lat' => -2.074805, 'lng' => 117.4606195],
                'zone' => 'Block 1-4',
                'cctv_id' => 'CCTV-003',
                'cctv_name' => 'CCTV Block 1-4',
                'personnel_name' => 'DV-7003-CK',
                'equipment_id' => null,
                'distance' => '25mtr Dewater',
                'detected_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
                'duration' => '5 minutes ago',
                'status' => 'unread',
                'acknowledged' => false,
                'acknowledged_by' => null,
                'acknowledged_at' => null,
            ],
            [
                'id' => 'ALERT-004',
                'type' => 'Unauthorized Access',
                'severity' => 'critical',
                'priority' => 'critical',
                'title' => 'Unauthorized Access to High-Risk Zone',
                'message' => 'Unauthorized personnel detected entering restricted high-risk operational zone',
                'location' => ['lat' => -2.033457, 'lng' => 117.44043],
                'zone' => 'Block 5-6',
                'cctv_id' => 'CCTV-004',
                'cctv_name' => 'CCTV Block 5-6',
                'personnel_name' => 'HT-Fadili-CX',
                'equipment_id' => null,
                'distance' => '15mtr',
                'detected_at' => now()->subSeconds(120)->format('Y-m-d H:i:s'),
                'duration' => '2 minutes ago',
                'status' => 'unread',
                'acknowledged' => false,
                'acknowledged_by' => null,
                'acknowledged_at' => null,
            ],
            [
                'id' => 'ALERT-005',
                'type' => 'Geofence Violation',
                'severity' => 'medium',
                'priority' => 'medium',
                'title' => 'Vehicle Entering Restricted Zone',
                'message' => 'Vehicle detected entering geofenced restricted area',
                'location' => ['lat' => -2.1523135, 'lng' => 117.554182],
                'zone' => 'Gurimbang',
                'cctv_id' => 'CCTV-005',
                'cctv_name' => 'CCTV Gurimbang Gate',
                'personnel_name' => null,
                'equipment_id' => 'TR-1234-AB',
                'equipment_type' => 'Truck',
                'distance' => '30mtr',
                'detected_at' => now()->subMinutes(8)->format('Y-m-d H:i:s'),
                'duration' => '8 minutes ago',
                'status' => 'read',
                'acknowledged' => true,
                'acknowledged_by' => 'Security Team',
                'acknowledged_at' => now()->subMinutes(6)->format('Y-m-d H:i:s'),
            ],
        ];

        // Statistics
        $stats = [
            'total_alerts' => count($activeAlerts),
            'unread_alerts' => count(array_filter($activeAlerts, fn($a) => $a['status'] === 'unread')),
            'critical_alerts' => count(array_filter($activeAlerts, fn($a) => $a['severity'] === 'critical')),
            'unacknowledged_alerts' => count(array_filter($activeAlerts, fn($a) => !$a['acknowledged'])),
        ];

        return view('HazardMotion.admin.realtime-alerts', compact('activeAlerts', 'stats'));
    }

    /**
     * Display alert history page
     */
    public function history()
    {
        // Mock data untuk alert history
        $alertHistory = [
            [
                'id' => 'HIST-001',
                'type' => 'Personnel Violation',
                'severity' => 'high',
                'title' => 'Personnel in Restricted Zone',
                'zone' => 'Block A',
                'detected_at' => now()->subHours(3)->format('Y-m-d H:i:s'),
                'resolved_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
                'duration' => '1 hour',
                'resolved_by' => 'Supervisor A',
                'status' => 'resolved',
            ],
            [
                'id' => 'HIST-002',
                'type' => 'Equipment Violation',
                'severity' => 'medium',
                'title' => 'Equipment Operating Outside Zone',
                'zone' => 'Block B',
                'detected_at' => now()->subDays(1)->format('Y-m-d H:i:s'),
                'resolved_at' => now()->subDays(1)->addHours(2)->format('Y-m-d H:i:s'),
                'duration' => '2 hours',
                'resolved_by' => 'Supervisor B',
                'status' => 'resolved',
            ],
            // Add more history items...
        ];

        return view('HazardMotion.admin.alert-history', compact('alertHistory'));
    }

    /**
     * Display notification settings page
     */
    public function settings()
    {
        // Mock settings data
        $settings = [
            'email_notifications' => true,
            'sms_notifications' => false,
            'push_notifications' => true,
            'critical_alerts_only' => false,
            'notification_sound' => true,
            'auto_acknowledge' => false,
            'alert_retention_days' => 30,
        ];

        return view('HazardMotion.admin.alert-settings', compact('settings'));
    }

    /**
     * Get real-time alerts via API (for AJAX/polling)
     */
    public function getAlerts(Request $request)
    {
        $status = $request->get('status', 'all');
        $severity = $request->get('severity', 'all');
        
        // Mock data (akan diganti dengan query database)
        $alerts = [
            [
                'id' => 'ALERT-001',
                'type' => 'Personnel Violation',
                'severity' => 'critical',
                'title' => 'Unauthorized Personnel in Restricted Zone',
                'detected_at' => now()->subSeconds(45)->format('Y-m-d H:i:s'),
                'status' => 'unread',
            ],
            // ... more alerts
        ];

        // Apply filters
        if ($status !== 'all') {
            $alerts = array_filter($alerts, fn($a) => $a['status'] === $status);
        }
        
        if ($severity !== 'all') {
            $alerts = array_filter($alerts, fn($a) => $a['severity'] === $severity);
        }

        return response()->json([
            'success' => true,
            'data' => array_values($alerts),
            'count' => count($alerts),
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Acknowledge an alert
     */
    public function acknowledge(Request $request, $alertId)
    {
        // Logic to acknowledge alert
        // This would update the database
        
        return response()->json([
            'success' => true,
            'message' => 'Alert acknowledged successfully',
            'alert_id' => $alertId
        ]);
    }

    /**
     * Save notification settings
     */
    public function saveSettings(Request $request)
    {
        // Validate and save settings
        // This would save to database or user preferences
        
        $validated = $request->validate([
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'critical_alerts_only' => 'boolean',
            'notification_sound' => 'boolean',
            'auto_acknowledge' => 'boolean',
            'alert_retention_days' => 'integer|min:1|max:365',
        ]);

        // Save settings logic here
        // For now, just return success
        
        return response()->json([
            'success' => true,
            'message' => 'Settings saved successfully',
            'data' => $validated
        ]);
    }
}

