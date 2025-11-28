<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Models\CctvData;
use Illuminate\Http\Request;

class GeofencingController extends Controller
{
    /**
     * Display zone management page
     */
    public function index()
    {
        // Ambil data CCTV yang memiliki koordinat
        $cctvData = CctvData::whereNotNull('longitude')
            ->whereNotNull('latitude')
            ->get();

        // Format data untuk JavaScript
        $cctvLocations = $cctvData->map(function ($cctv) {
            return [
                'id' => $cctv->no_cctv ?? 'CCTV-' . $cctv->id,
                'name' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                'location' => [(float) $cctv->longitude, (float) $cctv->latitude],
                'status' => $cctv->kondisi ?? $cctv->status ?? 'Unknown',
            ];
        })->toArray();

        // Mock data untuk geofence zones (akan diganti dengan data real dari database)
        $geofenceZones = [
            [
                'id' => 'ZONE-001',
                'name' => 'Restricted Mining Area - Block A',
                'type' => 'restricted',
                'status' => 'active',
                'coordinates' => [
                    ['lat' => -2.186253, 'lng' => 117.4539035],
                    ['lat' => -2.1767075, 'lng' => 117.3942385],
                    ['lat' => -2.174805, 'lng' => 117.4606195],
                    ['lat' => -2.186253, 'lng' => 117.4539035],
                ],
                'center' => ['lat' => -2.179253, 'lng' => 117.4366195],
                'area' => '2.5 km²',
                'description' => 'High-risk mining operational area - restricted access',
                'created_at' => now()->subDays(30)->format('Y-m-d H:i:s'),
                'updated_at' => now()->subDays(5)->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 'ZONE-002',
                'name' => 'Equipment Storage Zone',
                'type' => 'storage',
                'status' => 'active',
                'coordinates' => [
                    ['lat' => -2.033457, 'lng' => 117.44043],
                    ['lat' => -2.033457, 'lng' => 117.45043],
                    ['lat' => -2.043457, 'lng' => 117.45043],
                    ['lat' => -2.043457, 'lng' => 117.44043],
                    ['lat' => -2.033457, 'lng' => 117.44043],
                ],
                'center' => ['lat' => -2.038457, 'lng' => 117.44543],
                'area' => '0.8 km²',
                'description' => 'Designated area for equipment storage and maintenance',
                'created_at' => now()->subDays(20)->format('Y-m-d H:i:s'),
                'updated_at' => now()->subDays(2)->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 'ZONE-003',
                'name' => 'Personnel Safety Zone',
                'type' => 'safety',
                'status' => 'active',
                'coordinates' => [
                    ['lat' => -2.1523135, 'lng' => 117.554182],
                    ['lat' => -2.1523135, 'lng' => 117.564182],
                    ['lat' => -2.1623135, 'lng' => 117.564182],
                    ['lat' => -2.1623135, 'lng' => 117.554182],
                    ['lat' => -2.1523135, 'lng' => 117.554182],
                ],
                'center' => ['lat' => -2.1573135, 'lng' => 117.559182],
                'area' => '1.2 km²',
                'description' => 'Safe zone for personnel during operations',
                'created_at' => now()->subDays(15)->format('Y-m-d H:i:s'),
                'updated_at' => now()->subDays(1)->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 'ZONE-004',
                'name' => 'Blasting Exclusion Zone',
                'type' => 'exclusion',
                'status' => 'active',
                'coordinates' => [
                    ['lat' => -2.074805, 'lng' => 117.4606195],
                    ['lat' => -2.074805, 'lng' => 117.4706195],
                    ['lat' => -2.084805, 'lng' => 117.4706195],
                    ['lat' => -2.084805, 'lng' => 117.4606195],
                    ['lat' => -2.074805, 'lng' => 117.4606195],
                ],
                'center' => ['lat' => -2.079805, 'lng' => 117.4656195],
                'area' => '0.5 km²',
                'description' => 'Exclusion zone during blasting operations - no entry allowed',
                'created_at' => now()->subDays(10)->format('Y-m-d H:i:s'),
                'updated_at' => now()->subHours(6)->format('Y-m-d H:i:s'),
            ],
        ];

        // Statistics
        $stats = [
            'total_zones' => count($geofenceZones),
            'active_zones' => count(array_filter($geofenceZones, fn($z) => $z['status'] === 'active')),
            'restricted_zones' => count(array_filter($geofenceZones, fn($z) => $z['type'] === 'restricted')),
            'total_area' => '5.0 km²',
        ];

        return view('HazardMotion.admin.geofencing', compact(
            'cctvLocations',
            'geofenceZones',
            'stats'
        ));
    }

    /**
     * Display geofence rules page
     */
    public function rules()
    {
        // Mock data untuk geofence rules
        $geofenceRules = [
            [
                'id' => 'RULE-001',
                'name' => 'Restricted Zone Entry Alert',
                'zone_id' => 'ZONE-001',
                'zone_name' => 'Restricted Mining Area - Block A',
                'trigger_type' => 'entry',
                'action' => 'alert',
                'severity' => 'critical',
                'notify_users' => ['Supervisor A', 'Security Team'],
                'status' => 'active',
                'created_at' => now()->subDays(30)->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 'RULE-002',
                'name' => 'Equipment Zone Violation',
                'zone_id' => 'ZONE-002',
                'zone_name' => 'Equipment Storage Zone',
                'trigger_type' => 'unauthorized_entry',
                'action' => 'alert_and_log',
                'severity' => 'high',
                'notify_users' => ['Equipment Manager'],
                'status' => 'active',
                'created_at' => now()->subDays(20)->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 'RULE-003',
                'name' => 'Blasting Zone Exclusion',
                'zone_id' => 'ZONE-004',
                'zone_name' => 'Blasting Exclusion Zone',
                'trigger_type' => 'entry',
                'action' => 'critical_alert',
                'severity' => 'critical',
                'notify_users' => ['Safety Officer', 'Site Manager', 'Security Team'],
                'status' => 'active',
                'created_at' => now()->subDays(10)->format('Y-m-d H:i:s'),
            ],
        ];

        return view('HazardMotion.admin.geofence-rules', compact('geofenceRules'));
    }

    /**
     * Display boundary monitoring page
     */
    public function monitoring()
    {
        // Mock data untuk boundary monitoring
        $boundaryEvents = [
            [
                'id' => 'EVENT-001',
                'zone_id' => 'ZONE-001',
                'zone_name' => 'Restricted Mining Area - Block A',
                'event_type' => 'entry',
                'entity_type' => 'personnel',
                'entity_id' => 'P-12345',
                'entity_name' => 'MOHAMMAD NUR AKBAR HIDAYATULLAH',
                'timestamp' => now()->subMinutes(15)->format('Y-m-d H:i:s'),
                'location' => ['lat' => -2.186253, 'lng' => 117.4539035],
                'status' => 'active',
                'action_taken' => 'Alert sent to supervisor',
            ],
            [
                'id' => 'EVENT-002',
                'zone_id' => 'ZONE-002',
                'zone_name' => 'Equipment Storage Zone',
                'event_type' => 'unauthorized_entry',
                'entity_type' => 'equipment',
                'entity_id' => 'CE-6163-CK',
                'entity_name' => 'Crane Truck CE-6163-CK',
                'timestamp' => now()->subMinutes(30)->format('Y-m-d H:i:s'),
                'location' => ['lat' => -2.038457, 'lng' => 117.44543],
                'status' => 'resolved',
                'action_taken' => 'Equipment redirected to correct zone',
            ],
        ];

        return view('HazardMotion.admin.boundary-monitoring', compact('boundaryEvents'));
    }

    /**
     * Get geofence zones via API
     */
    public function getZones(Request $request)
    {
        // Mock data
        $zones = [
            [
                'id' => 'ZONE-001',
                'name' => 'Restricted Mining Area - Block A',
                'type' => 'restricted',
                'status' => 'active',
                'coordinates' => [
                    ['lat' => -2.186253, 'lng' => 117.4539035],
                    ['lat' => -2.1767075, 'lng' => 117.3942385],
                    ['lat' => -2.174805, 'lng' => 117.4606195],
                    ['lat' => -2.186253, 'lng' => 117.4539035],
                ],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $zones,
            'count' => count($zones)
        ]);
    }

    /**
     * Create or update geofence zone
     */
    public function saveZone(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'coordinates' => 'required|array',
            'description' => 'nullable|string',
        ]);

        // Save zone logic here
        
        return response()->json([
            'success' => true,
            'message' => 'Zone saved successfully',
            'data' => $validated
        ]);
    }

    /**
     * Delete geofence zone
     */
    public function deleteZone($zoneId)
    {
        // Delete zone logic here
        
        return response()->json([
            'success' => true,
            'message' => 'Zone deleted successfully',
            'zone_id' => $zoneId
        ]);
    }
}

