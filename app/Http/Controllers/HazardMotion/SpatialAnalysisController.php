<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Models\CctvData;
use Illuminate\Http\Request;

class SpatialAnalysisController extends Controller
{
    /**
     * Display heat map page
     */
    public function heatMap()
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

        // Mock data untuk heat map points (akan diganti dengan data real dari database)
        $heatMapData = [
            ['lat' => -2.186253, 'lng' => 117.4539035, 'intensity' => 85, 'type' => 'violation'],
            ['lat' => -2.1767075, 'lng' => 117.3942385, 'intensity' => 72, 'type' => 'violation'],
            ['lat' => -2.174805, 'lng' => 117.4606195, 'intensity' => 65, 'type' => 'alert'],
            ['lat' => -2.033457, 'lng' => 117.44043, 'intensity' => 90, 'type' => 'violation'],
            ['lat' => -2.1523135, 'lng' => 117.554182, 'intensity' => 55, 'type' => 'alert'],
            ['lat' => -2.079805, 'lng' => 117.4656195, 'intensity' => 78, 'type' => 'violation'],
        ];

        return view('HazardMotion.admin.spatial-analysis-heatmap', compact('cctvLocations', 'heatMapData'));
    }

    /**
     * Display zone analysis page
     */
    public function zoneAnalysis()
    {
        // Mock data untuk zone analysis
        $zoneAnalysis = [
            [
                'zone_id' => 'ZONE-001',
                'zone_name' => 'Restricted Mining Area - Block A',
                'total_events' => 45,
                'violations' => 32,
                'alerts' => 13,
                'personnel_count' => 28,
                'equipment_count' => 15,
                'risk_score' => 8.5,
                'last_activity' => now()->subMinutes(15)->format('Y-m-d H:i:s'),
            ],
            [
                'zone_id' => 'ZONE-002',
                'zone_name' => 'Equipment Storage Zone',
                'total_events' => 18,
                'violations' => 8,
                'alerts' => 10,
                'personnel_count' => 12,
                'equipment_count' => 25,
                'risk_score' => 6.2,
                'last_activity' => now()->subMinutes(30)->format('Y-m-d H:i:s'),
            ],
            [
                'zone_id' => 'ZONE-003',
                'zone_name' => 'Personnel Safety Zone',
                'total_events' => 5,
                'violations' => 1,
                'alerts' => 4,
                'personnel_count' => 35,
                'equipment_count' => 8,
                'risk_score' => 3.1,
                'last_activity' => now()->subHours(2)->format('Y-m-d H:i:s'),
            ],
        ];

        return view('HazardMotion.admin.spatial-analysis-zone', compact('zoneAnalysis'));
    }

    /**
     * Display movement patterns page
     */
    public function movementPatterns()
    {
        // Mock data untuk movement patterns
        $movementPatterns = [
            [
                'entity_id' => 'P-12345',
                'entity_name' => 'MOHAMMAD NUR AKBAR HIDAYATULLAH',
                'entity_type' => 'personnel',
                'movement_path' => [
                    ['lat' => -2.186253, 'lng' => 117.4539035, 'timestamp' => now()->subHours(3)->format('Y-m-d H:i:s')],
                    ['lat' => -2.1767075, 'lng' => 117.3942385, 'timestamp' => now()->subHours(2)->format('Y-m-d H:i:s')],
                    ['lat' => -2.174805, 'lng' => 117.4606195, 'timestamp' => now()->subHour()->format('Y-m-d H:i:s')],
                    ['lat' => -2.033457, 'lng' => 117.44043, 'timestamp' => now()->format('Y-m-d H:i:s')],
                ],
                'total_distance' => '2.5 km',
                'duration' => '3 hours',
                'zones_visited' => ['ZONE-001', 'ZONE-002'],
            ],
            [
                'entity_id' => 'CE-6163-CK',
                'entity_name' => 'Crane Truck CE-6163-CK',
                'entity_type' => 'equipment',
                'movement_path' => [
                    ['lat' => -2.1523135, 'lng' => 117.554182, 'timestamp' => now()->subHours(5)->format('Y-m-d H:i:s')],
                    ['lat' => -2.079805, 'lng' => 117.4656195, 'timestamp' => now()->subHours(3)->format('Y-m-d H:i:s')],
                    ['lat' => -2.033457, 'lng' => 117.44043, 'timestamp' => now()->subHour()->format('Y-m-d H:i:s')],
                ],
                'total_distance' => '4.2 km',
                'duration' => '5 hours',
                'zones_visited' => ['ZONE-002', 'ZONE-004'],
            ],
        ];

        return view('HazardMotion.admin.spatial-analysis-movement', compact('movementPatterns'));
    }

    /**
     * Display risk assessment page
     */
    public function riskAssessment()
    {
        // Mock data untuk risk assessment
        $riskAssessment = [
            [
                'zone_id' => 'ZONE-001',
                'zone_name' => 'Restricted Mining Area - Block A',
                'risk_level' => 'high',
                'risk_score' => 8.5,
                'factors' => [
                    'High violation rate' => 3.0,
                    'Frequent unauthorized access' => 2.5,
                    'Equipment violations' => 2.0,
                    'Personnel density' => 1.0,
                ],
                'recommendations' => [
                    'Increase CCTV coverage',
                    'Enhance security patrols',
                    'Implement stricter access control',
                ],
            ],
            [
                'zone_id' => 'ZONE-002',
                'zone_name' => 'Equipment Storage Zone',
                'risk_level' => 'medium',
                'risk_score' => 6.2,
                'factors' => [
                    'Equipment movement violations' => 2.5,
                    'Moderate personnel activity' => 1.8,
                    'Storage area access issues' => 1.9,
                ],
                'recommendations' => [
                    'Improve zone marking',
                    'Regular equipment audits',
                ],
            ],
            [
                'zone_id' => 'ZONE-003',
                'zone_name' => 'Personnel Safety Zone',
                'risk_level' => 'low',
                'risk_score' => 3.1,
                'factors' => [
                    'Low violation rate' => 1.2,
                    'Good compliance' => 0.9,
                    'Adequate safety measures' => 1.0,
                ],
                'recommendations' => [
                    'Maintain current safety protocols',
                ],
            ],
        ];

        return view('HazardMotion.admin.spatial-analysis-risk', compact('riskAssessment'));
    }

    /**
     * Get heat map data via API
     */
    public function getHeatMapData(Request $request)
    {
        $type = $request->get('type', 'all');
        
        // Mock data
        $heatMapData = [
            ['lat' => -2.186253, 'lng' => 117.4539035, 'intensity' => 85, 'type' => 'violation'],
            ['lat' => -2.1767075, 'lng' => 117.3942385, 'intensity' => 72, 'type' => 'violation'],
        ];

        if ($type !== 'all') {
            $heatMapData = array_filter($heatMapData, fn($d) => $d['type'] === $type);
        }

        return response()->json([
            'success' => true,
            'data' => array_values($heatMapData),
            'count' => count($heatMapData)
        ]);
    }
}

