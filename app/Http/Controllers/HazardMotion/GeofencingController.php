<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Models\CctvData;
use App\Models\WmsLink;
use App\Models\GeojsonArea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GeofencingController extends Controller
{
    /**
     * Display WMS link management page
     */
    public function index()
    {
        $currentWeek = WmsLink::getCurrentWeek();
        $currentYear = WmsLink::getCurrentYear();
        
        // Get WMS links with pagination, ordered by latest first
        $wmsLinks = WmsLink::orderBy('created_at', 'desc')
            ->paginate(15);
        
        // Get count of links for current week and year
        $currentWeekLinksCount = WmsLink::where('year', $currentYear)
            ->where('week', $currentWeek)
            ->count();

        return view('HazardMotion.admin.geofencing', compact(
            'wmsLinks',
            'currentWeek',
            'currentYear',
            'currentWeekLinksCount'
        ));
    }

    /**
     * Store WMS link
     */
    public function storeWmsLink(Request $request)
    {
        $validated = $request->validate([
            'location_name' => 'required|string|max:255',
            'wms_link' => 'required|url|max:500',
        ]);

        // Get current week and year
        $week = WmsLink::getCurrentWeek();
        $year = WmsLink::getCurrentYear();

        // Create WMS link
        WmsLink::create([
            'location_name' => $validated['location_name'],
            'wms_link' => $validated['wms_link'],
            'week' => $week,
            'year' => $year,
        ]);

        $message = 'WMS link berhasil diupload untuk Week ' . $week . ' tahun ' . $year;

        // Return JSON response for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        }

        return redirect()->route('geofencing.index')
            ->with('success', $message);
    }

    /**
     * Get WMS link by ID (for edit)
     */
    public function getWmsLink($id)
    {
        $wmsLink = WmsLink::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $wmsLink
        ]);
    }

    /**
     * Update WMS link
     */
    public function updateWmsLink(Request $request, $id)
    {
        $validated = $request->validate([
            'location_name' => 'required|string|max:255',
            'wms_link' => 'required|url|max:500',
        ]);

        $wmsLink = WmsLink::findOrFail($id);
        
        // Get current week and year for update
        $week = WmsLink::getCurrentWeek();
        $year = WmsLink::getCurrentYear();

        $wmsLink->update([
            'location_name' => $validated['location_name'],
            'wms_link' => $validated['wms_link'],
            'week' => $week,
            'year' => $year,
        ]);

        $message = 'WMS link berhasil diupdate untuk Week ' . $week . ' tahun ' . $year;

        // Return JSON response for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        }

        return redirect()->route('geofencing.index')
            ->with('success', $message);
    }

    /**
     * Delete WMS link
     */
    public function deleteWmsLink(Request $request, $id)
    {
        $wmsLink = WmsLink::findOrFail($id);
        $wmsLink->delete();

        $message = 'WMS link berhasil dihapus';

        // Return JSON response for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        }

        return redirect()->route('geofencing.index')
            ->with('success', $message);
    }

    /**
     * Display geofence rules page
     */
    public function rules()
    {
        $currentWeek = GeojsonArea::getCurrentWeek();
        $currentYear = GeojsonArea::getCurrentYear();
        
        // Get GeoJSON areas with pagination
        $geojsonAreas = GeojsonArea::orderBy('created_at', 'desc')
            ->paginate(15);
        
        // Get counts by type
        $areaKerjaCount = GeojsonArea::where('type', 'area_kerja')->count();
        $areaCctvCount = GeojsonArea::where('type', 'area_cctv')->count();
        $currentWeekCount = GeojsonArea::where('year', $currentYear)
            ->where('week', $currentWeek)
            ->count();

        return view('HazardMotion.admin.geofence-rules', compact(
            'geojsonAreas',
            'currentWeek',
            'currentYear',
            'areaKerjaCount',
            'areaCctvCount',
            'currentWeekCount'
        ));
    }

    /**
     * Store GeoJSON area
     */
    public function storeGeojsonArea(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:area_kerja,area_cctv',
            'geojson_file' => 'required|file|mimes:json,geojson|max:10240', // Max 10MB
            'description' => 'nullable|string|max:500',
        ]);

        try {
            // Read and validate GeoJSON file
            $file = $request->file('geojson_file');
            $fileContent = file_get_contents($file->getRealPath());
            $geojsonData = json_decode($fileContent, true);

            // Validate GeoJSON structure
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON format');
            }

            if (!isset($geojsonData['type']) || $geojsonData['type'] !== 'FeatureCollection') {
                throw new \Exception('GeoJSON must be a FeatureCollection');
            }

            // Get current week and year
            $week = GeojsonArea::getCurrentWeek();
            $year = GeojsonArea::getCurrentYear();

            // Store file (optional - bisa disimpan di storage atau hanya di database)
            $fileName = $file->getClientOriginalName();
            $filePath = $file->storeAs('geojson', $fileName, 'public');

            // Create GeoJSON area
            GeojsonArea::create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'geojson_data' => $geojsonData,
                'file_name' => $fileName,
                'week' => $week,
                'year' => $year,
                'description' => $validated['description'] ?? null,
            ]);

            $message = 'GeoJSON ' . ($validated['type'] === 'area_kerja' ? 'Area Kerja' : 'Area CCTV') . ' berhasil diupload untuk Week ' . $week . ' tahun ' . $year;

            // Return JSON response for AJAX requests
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            }

            return redirect()->route('geofencing.rules')
                ->with('success', $message);
        } catch (\Exception $e) {
            $errorMessage = 'Gagal mengupload GeoJSON: ' . $e->getMessage();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 400);
            }

            return redirect()->route('geofencing.rules')
                ->with('error', $errorMessage);
        }
    }

    /**
     * Get GeoJSON area by ID (for edit)
     */
    public function getGeojsonArea($id)
    {
        $geojsonArea = GeojsonArea::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $geojsonArea
        ]);
    }

    /**
     * Update GeoJSON area
     */
    public function updateGeojsonArea(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:area_kerja,area_cctv',
            'geojson_file' => 'nullable|file|mimes:json,geojson|max:10240',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $geojsonArea = GeojsonArea::findOrFail($id);
            
            $updateData = [
                'name' => $validated['name'],
                'type' => $validated['type'],
                'description' => $validated['description'] ?? null,
            ];

            // If new file is uploaded
            if ($request->hasFile('geojson_file')) {
                $file = $request->file('geojson_file');
                $fileContent = file_get_contents($file->getRealPath());
                $geojsonData = json_decode($fileContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON format');
                }

                if (!isset($geojsonData['type']) || $geojsonData['type'] !== 'FeatureCollection') {
                    throw new \Exception('GeoJSON must be a FeatureCollection');
                }

                $fileName = $file->getClientOriginalName();
                $filePath = $file->storeAs('geojson', $fileName, 'public');

                $updateData['geojson_data'] = $geojsonData;
                $updateData['file_name'] = $fileName;
            }

            // Get current week and year for update
            $week = GeojsonArea::getCurrentWeek();
            $year = GeojsonArea::getCurrentYear();
            $updateData['week'] = $week;
            $updateData['year'] = $year;

            $geojsonArea->update($updateData);

            $message = 'GeoJSON ' . ($validated['type'] === 'area_kerja' ? 'Area Kerja' : 'Area CCTV') . ' berhasil diupdate untuk Week ' . $week . ' tahun ' . $year;

            // Return JSON response for AJAX requests
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            }

            return redirect()->route('geofencing.rules')
                ->with('success', $message);
        } catch (\Exception $e) {
            $errorMessage = 'Gagal mengupdate GeoJSON: ' . $e->getMessage();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 400);
            }

            return redirect()->route('geofencing.rules')
                ->with('error', $errorMessage);
        }
    }

    /**
     * Delete GeoJSON area
     */
    public function deleteGeojsonArea(Request $request, $id)
    {
        $geojsonArea = GeojsonArea::findOrFail($id);
        $geojsonArea->delete();

        $message = 'GeoJSON area berhasil dihapus';

        // Return JSON response for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        }

        return redirect()->route('geofencing.rules')
            ->with('success', $message);
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

