<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Models\CctvData;
use App\Models\InsidenTabel;
use App\Models\GrTable;
use App\Models\HazardValidation;
use App\Services\BesigmaDbService;
use App\Services\ClickHouseService;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class MapBaseController extends Controller
{
    /**
     * Display the hazard detection page
     */
    public function index()
    {
        // Ambil SEMUA data CCTV dari tabel cctv_data_bmo2 (termasuk yang tidak punya koordinat)
        // Model CctvData sudah dikonfigurasi untuk menggunakan tabel cctv_data_bmo2
        $cctvDataAll = CctvData::all();
        
        // Ambil data CCTV yang memiliki koordinat untuk ditampilkan di map
        $cctvDataWithLocation = CctvData::whereNotNull('longitude')
            ->whereNotNull('latitude')
            ->get();

        // Format data untuk JavaScript dengan semua field yang diperlukan
        // Data diambil langsung dari database, bukan dari WMS atau GeoJSON
        // Gunakan semua data untuk sidebar, tapi hanya yang punya koordinat untuk map
        $cctvLocations = $cctvDataAll->map(function ($cctv) {
            return [
                'id' => $cctv->id,
                'no_cctv' => $cctv->no_cctv ?? null,
                'nomor_cctv' => $cctv->no_cctv ?? null,
                'name' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                'cctv_name' => $cctv->nama_cctv ?? null,
                'nama_cctv' => $cctv->nama_cctv ?? null,
                'location' => ($cctv->longitude && $cctv->latitude) 
                    ? [(float) $cctv->longitude, (float) $cctv->latitude] 
                    : null,
                'has_location' => !is_null($cctv->longitude) && !is_null($cctv->latitude),
                'status' => $cctv->kondisi ?? $cctv->status ?? 'Unknown',
                'kondisi' => $cctv->kondisi ?? null,
                'site' => $cctv->site ?? null,
                'perusahaan' => $cctv->perusahaan ?? null,
                'perusahaan_cctv' => $cctv->perusahaan ?? null,
                'link_akses' => $cctv->link_akses ?? null,
                'externalUrl' => $cctv->link_akses ?? null,
                'rtsp_url' => null, // Will be built if needed
                'user_name' => $cctv->user_name ?? null,
                'password' => $cctv->password ?? null,
                'ip' => null, // Not in current schema
                'port' => null,
                'channel' => null,
                'brand' => $this->extractBrandFromTipe($cctv->tipe_cctv ?? ''),
                'tipe_cctv' => $cctv->tipe_cctv ?? null,
                'fungsi_cctv' => $cctv->fungsi_cctv ?? null,
                'lokasi_pemasangan' => $cctv->lokasi_pemasangan ?? null,
                'control_room' => $cctv->control_room ?? null,
                'coverage_lokasi' => $cctv->coverage_lokasi ?? null,
                'kategori_area_tercapture' => $cctv->kategori_area_tercapture ?? null,
                'created_at' => $cctv->created_at ? $cctv->created_at->toDateTimeString() : null,
                'updated_at' => $cctv->updated_at ? $cctv->updated_at->toDateTimeString() : null,
                'tahun_update' => $cctv->tahun_update ?? null,
                'bulan_update' => $cctv->bulan_update ?? null,
            ];
        })->toArray();
        
        // Data untuk map (hanya yang punya koordinat)
        $cctvLocationsForMap = $cctvDataWithLocation->map(function ($cctv) {
            return [
                'id' => $cctv->id,
                'no_cctv' => $cctv->no_cctv ?? null,
                'nomor_cctv' => $cctv->no_cctv ?? null,
                'name' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                'cctv_name' => $cctv->nama_cctv ?? null,
                'nama_cctv' => $cctv->nama_cctv ?? null,
                'location' => [(float) $cctv->longitude, (float) $cctv->latitude],
                'status' => $cctv->kondisi ?? $cctv->status ?? 'Unknown',
                'kondisi' => $cctv->kondisi ?? null,
                'site' => $cctv->site ?? null,
                'perusahaan' => $cctv->perusahaan ?? null,
                'perusahaan_cctv' => $cctv->perusahaan ?? null,
                'link_akses' => $cctv->link_akses ?? null,
                'externalUrl' => $cctv->link_akses ?? null,
                'rtsp_url' => null,
                'user_name' => $cctv->user_name ?? null,
                'password' => $cctv->password ?? null,
                'ip' => null,
                'port' => null,
                'channel' => null,
                'brand' => $this->extractBrandFromTipe($cctv->tipe_cctv ?? ''),
                'tipe_cctv' => $cctv->tipe_cctv ?? null,
                'fungsi_cctv' => $cctv->fungsi_cctv ?? null,
                'lokasi_pemasangan' => $cctv->lokasi_pemasangan ?? null,
                'control_room' => $cctv->control_room ?? null,
                'coverage_lokasi' => $cctv->coverage_lokasi ?? null,
            ];
        })->toArray();

        // Statistik area kritis untuk tampilan awal
        $totalCctvCount = CctvData::count();

        $criticalCoverageBaseQuery = CctvData::query()->where(function ($query) {
            $query->where('kategori_area_tercapture', 'like', '%kritis%')
                  ->orWhere('kategori_area_tercapture', 'like', '%critical%')
                  ->orWhere('coverage_lokasi', 'like', '%kritis%')
                  ->orWhere('coverage_lokasi', 'like', '%critical%');
        });

        $criticalAreaCount = (clone $criticalCoverageBaseQuery)
            ->whereNotNull('coverage_lokasi')
            ->where('coverage_lokasi', '!=', '')
            ->distinct('coverage_lokasi')
            ->count('coverage_lokasi');

        $criticalCoverageCctv = (clone $criticalCoverageBaseQuery)->count();

        $criticalCoveragePercentage = $totalCctvCount > 0
            ? round(($criticalCoverageCctv / $totalCctvCount) * 100, 1)
            : 0;

        // Ambil data SAP (Safety Action Plan) dari ClickHouse
        // Mengganti hazard dengan SAP dari tabel nitip.union_sap_all_with_karyawan_full
        // Default: ambil data untuk week ini (Senin-Senin)
        $today = Carbon::now();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $sapData = $this->getSapDataFromClickHouse($weekStart);

        // Ambil data GR detections dari PostgreSQL
        $grDetections = $this->getGrDetectionsFromPostgres();

        // Hitung jumlah valid GR yang cocok dengan data dari PostgreSQL
        $validGrCount = $this->getValidGrCount();

        // Statistics untuk SAP
        $stats = [
            'total_detections' => count($sapData),
            'active_detections' => count($sapData), // Semua SAP dianggap active
            'resolved_detections' => 0,
            'critical_severity' => 0,
            'high_severity' => 0,
            'medium_severity' => count($sapData),
        ];

        // Get all insiden records (remove limit to get all data for accurate filtering)
        $insidenRecords = InsidenTabel::orderByDesc('created_at')
            ->get();

        $insidenGroups = $insidenRecords
            ->groupBy('no_kecelakaan')
            ->map(function ($items, $noKecelakaan) {
                $items = $items->values();
                $first = $items->first();

                $latItem = $items->first(function ($item) {
                    return ! is_null($item->latitude);
                });
                $lonItem = $items->first(function ($item) {
                    return ! is_null($item->longitude);
                });

                return [
                    'no_kecelakaan' => $noKecelakaan,
                    'site' => $first->site,
                    'lokasi' => $first->lokasi ?? $first->lokasi_spesifik ?? null,
                    'status_lpi' => $first->status_lpi,
                    'layer' => $first->layer,
                    'jenis_item_ipls' => $first->jenis_item_ipls,
                    'kategori' => $first->kategori,
                    'tanggal' => optional($first->tanggal)->format('Y-m-d'),
                    'latitude' => $latItem->latitude ?? null,
                    'longitude' => $lonItem->longitude ?? null,
                    'items' => $items->map(function ($item) {
                        return [
                            'tasklist' => $item->tasklist ?? null,
                            'layer' => $item->layer,
                            'jenis_item_ipls' => $item->jenis_item_ipls,
                            'detail_layer' => $item->detail_layer,
                            'klasifikasi_layer' => $item->klasifikasi_layer,
                            'keterangan_layer' => $item->keterangan_layer,
                            'site' => $item->site,
                            'lokasi' => $item->lokasi,
                            'lokasi_spesifik' => $item->lokasi_spesifik,
                            'tanggal' => optional($item->tanggal)->format('Y-m-d'),
                            'status_lpi' => $item->status_lpi,
                            'catatan' => $item->catatan,
                            'perusahaan' => $item->perusahaan,
                            'latitude' => $item->latitude,
                            'longitude' => $item->longitude,
                        ];
                    })->toArray(),
                ];
            })
            ->filter(function ($group) {
                // Hanya tampilkan insiden yang memiliki latitude dan longitude
                return ! is_null($group['latitude']) && ! is_null($group['longitude']);
            })
            ->values()
            ->toArray();

        // Hitung TBC (To Be Concerned) - hazard_validations dengan tobe_concerned_hazard = 'Valid'
        $tbcCount = HazardValidation::where('tobe_concerned_hazard', 'Valid')->count();
        
        // Hitung TBC tahun ini
        $currentYear = now()->year;
        $tbcThisYear = HazardValidation::where('tobe_concerned_hazard', 'Valid')
            ->whereYear('created_at', $currentYear)
            ->count();
        
        // Hitung TBC tahun lalu untuk perbandingan
        $lastYear = $currentYear - 1;
        $tbcLastYear = HazardValidation::where('tobe_concerned_hazard', 'Valid')
            ->whereYear('created_at', $lastYear)
            ->count();
        
        // Hitung perubahan persentase
        $tbcChange = 0;
        if ($tbcLastYear > 0) {
            $tbcChange = round((($tbcThisYear - $tbcLastYear) / $tbcLastYear) * 100, 1);
        } elseif ($tbcThisYear > 0) {
            $tbcChange = 100;
        }

        // Get unit vehicle data from besigma database
        $unitVehicles = [];
        try {
            $besigmaService = new BesigmaDbService();
            $unitVehicles = $besigmaService->getCombinedUnitData();
        } catch (Exception $e) {
            Log::error('Error fetching unit vehicles: ' . $e->getMessage());
            $unitVehicles = [];
        }

        return view('HazardMotion.admin.mapBase', compact(
            'cctvLocations',
            'cctvLocationsForMap',
            'sapData',
            'grDetections',
            'stats',
            'insidenGroups',
            'criticalAreaCount',
            'criticalCoveragePercentage',
            'criticalCoverageCctv',
            'validGrCount',
            'totalCctvCount',
            'tbcCount',
            'tbcThisYear',
            'tbcChange',
            'unitVehicles'
        ));
    }

    /**
     * Display the hazard detection fullscreen map page
     */
    public function fullscreenMap()
    {
        // Use the same data as index method
        $cctvData = CctvData::whereNotNull('longitude')
            ->whereNotNull('latitude')
            ->get();

        $cctvLocations = $cctvData->map(function ($cctv) {
            return [
                'id' => $cctv->id,
                'no_cctv' => $cctv->no_cctv ?? null,
                'nomor_cctv' => $cctv->no_cctv ?? null,
                'name' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                'cctv_name' => $cctv->nama_cctv ?? null,
                'nama_cctv' => $cctv->nama_cctv ?? null,
                'location' => [(float) $cctv->longitude, (float) $cctv->latitude],
                'status' => $cctv->kondisi ?? $cctv->status ?? 'Unknown',
                'kondisi' => $cctv->kondisi ?? null,
                'site' => $cctv->site ?? null,
                'perusahaan' => $cctv->perusahaan ?? null,
                'perusahaan_cctv' => $cctv->perusahaan ?? null,
                'link_akses' => $cctv->link_akses ?? null,
                'externalUrl' => $cctv->link_akses ?? null,
                'rtsp_url' => null,
                'user_name' => $cctv->user_name ?? null,
                'password' => $cctv->password ?? null,
                'ip' => null,
                'port' => null,
                'channel' => null,
                'brand' => $this->extractBrandFromTipe($cctv->tipe_cctv ?? ''),
                'tipe_cctv' => $cctv->tipe_cctv ?? null,
                'fungsi_cctv' => $cctv->fungsi_cctv ?? null,
                'lokasi_pemasangan' => $cctv->lokasi_pemasangan ?? null,
                'control_room' => $cctv->control_room ?? null,
                'coverage_lokasi' => $cctv->coverage_lokasi ?? null,
            ];
        })->toArray();

        $hazardDetections = $this->getHazardDetectionsFromPostgres();
        $grDetections = $this->getGrDetectionsFromPostgres();
        $validGrCount = $this->getValidGrCount();

        $insidenRecords = InsidenTabel::orderByDesc('created_at')->get();
        $insidenGroups = $insidenRecords
            ->groupBy('no_kecelakaan')
            ->map(function ($items, $noKecelakaan) {
                $items = $items->values();
                $first = $items->first();

                $latItem = $items->first(function ($item) {
                    return ! is_null($item->latitude);
                });
                $lonItem = $items->first(function ($item) {
                    return ! is_null($item->longitude);
                });

                return [
                    'no_kecelakaan' => $noKecelakaan,
                    'site' => $first->site,
                    'lokasi' => $first->lokasi ?? $first->lokasi_spesifik ?? null,
                    'status_lpi' => $first->status_lpi,
                    'layer' => $first->layer,
                    'jenis_item_ipls' => $first->jenis_item_ipls,
                    'kategori' => $first->kategori,
                    'tanggal' => optional($first->tanggal)->format('Y-m-d'),
                    'latitude' => $latItem->latitude ?? null,
                    'longitude' => $lonItem->longitude ?? null,
                    'items' => $items->map(function ($item) {
                        return [
                            'tasklist' => $item->tasklist ?? null,
                            'layer' => $item->layer,
                            'jenis_item_ipls' => $item->jenis_item_ipls,
                            'detail_layer' => $item->detail_layer,
                            'klasifikasi_layer' => $item->klasifikasi_layer,
                            'keterangan_layer' => $item->keterangan_layer,
                            'site' => $item->site,
                            'lokasi' => $item->lokasi,
                            'lokasi_spesifik' => $item->lokasi_spesifik,
                            'tanggal' => optional($item->tanggal)->format('Y-m-d'),
                            'status_lpi' => $item->status_lpi,
                            'catatan' => $item->catatan,
                            'perusahaan' => $item->perusahaan,
                            'latitude' => $item->latitude,
                            'longitude' => $item->longitude,
                        ];
                    })->toArray(),
                ];
            })
            ->filter(function ($group) {
                return ! is_null($group['latitude']) && ! is_null($group['longitude']);
            })
            ->values()
            ->toArray();

        $tbcCount = HazardValidation::where('tobe_concerned_hazard', 'Valid')->count();
        $currentYear = now()->year;
        $tbcThisYear = HazardValidation::where('tobe_concerned_hazard', 'Valid')
            ->whereYear('created_at', $currentYear)
            ->count();

        $unitVehicles = [];
        try {
            $besigmaService = new BesigmaDbService();
            $unitVehicles = $besigmaService->getCombinedUnitData();
        } catch (Exception $e) {
            Log::error('Error fetching unit vehicles: ' . $e->getMessage());
            $unitVehicles = [];
        }
        
        // Ensure arrays are not null
        $hazardDetections = $hazardDetections ?? [];
        $grDetections = $grDetections ?? [];
        $cctvLocations = $cctvLocations ?? [];
        $insidenGroups = $insidenGroups ?? [];
        $unitVehicles = $unitVehicles ?? [];

        return view('HazardMotion.admin.hazard-detection-fullscreen', compact(
            'cctvLocations',
            'hazardDetections',
            'grDetections',
            'insidenGroups',
            'validGrCount',
            'tbcCount',
            'tbcThisYear',
            'unitVehicles'
        ));
    }

    /**
     * Get unit vehicles data via API (for AJAX requests)
     * Returns all units from units table for list display
     */
    public function getUnitVehicles(Request $request)
    {
        try {
            $besigmaService = new BesigmaDbService();
            $unitVehicles = $besigmaService->getCombinedUnitData();
            
            return response()->json([
                'success' => true,
                'unitVehicles' => $unitVehicles,
                'count' => count($unitVehicles)
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching unit vehicles via API: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'unitVehicles' => []
            ], 500);
        }
    }

    /**
     * Get unit GPS logs for movement tracking (from unit_gps_logs table)
     */
    public function getUnitGpsLogs(Request $request)
    {
        try {
            $unitId = $request->get('unit_id');
            $limit = $request->get('limit', 1000);
            
            $besigmaService = new BesigmaDbService();
            $gpsLogs = $besigmaService->getUnitGpsLogsForTracking($unitId, $limit);
            
            return response()->json([
                'success' => true,
                'gpsLogs' => $gpsLogs,
                'count' => count($gpsLogs)
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching unit GPS logs via API: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'gpsLogs' => []
            ], 500);
        }
    }

    /**
     * Get PJA data from ClickHouse
     */
    public function getPjaData(Request $request)
    {
        try {
            $clickhouse = new ClickHouseService();
            
            if (!$clickhouse->isConnected()) {
                Log::warning('ClickHouse is not connected. Returning empty PJA data.');
                return response()->json([
                    'success' => false,
                    'error' => 'ClickHouse is not connected',
                    'data' => []
                ], 500);
            }
            
            // Query untuk mengambil data PJA dari tabel nitip.pja_full_hierarchical_view_fix
            $sql = "
                SELECT 
                    toString(site) as site,
                    toString(lokasi) as lokasi,
                    toString(detail_lokasi) as detail_lokasi,
                    toString(pja_id) as pja_id,
                    toString(nama_pja) as nama_pja,
                    toString(pja_active) as pja_active,
                    toString(pja_type_name) as pja_type_name,
                    toString(pja_category_name) as pja_category_name,
                    toString(pja_layer) as pja_layer,
                    toString(id_employee) as id_employee,
                    toString(nik) as nik,
                    toString(kode_sid) as kode_sid,
                    toString(employee_name) as employee_name,
                    toString(employee_email) as employee_email,
                    toString(kategori_pja) as kategori_pja
                FROM nitip.pja_full_hierarchical_view_fix
                ORDER BY pja_id DESC
                LIMIT 10000
            ";
            
            $results = $clickhouse->query($sql);
            
            // Format data untuk frontend
            $pjaData = [];
            foreach ($results as $row) {
                $pjaData[] = [
                    'site' => $row['site'] ?? null,
                    'lokasi' => $row['lokasi'] ?? null,
                    'detail_lokasi' => $row['detail_lokasi'] ?? null,
                    'pja_id' => $row['pja_id'] ?? null,
                    'nama_pja' => $row['nama_pja'] ?? null,
                    'pja_active' => $row['pja_active'] ?? null,
                    'pja_type_name' => $row['pja_type_name'] ?? null,
                    'pja_category_name' => $row['pja_category_name'] ?? null,
                    'pja_layer' => $row['pja_layer'] ?? null,
                    'id_employee' => $row['id_employee'] ?? null,
                    'nik' => $row['nik'] ?? null,
                    'kode_sid' => $row['kode_sid'] ?? null,
                    'employee_name' => $row['employee_name'] ?? null,
                    'employee_email' => $row['employee_email'] ?? null,
                    'kategori_pja' => $row['kategori_pja'] ?? null,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $pjaData,
                'count' => count($pjaData)
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching PJA data via API: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get hazard detections via API (for AJAX requests)
     */
    public function getDetections(Request $request)
    {
        // Filter parameters
        $status = $request->get('status', 'all');
        $severity = $request->get('severity', 'all');
        
        // Mock data (akan diganti dengan query database)
        $hazardDetections = [
            [
                'id' => 'HD-001',
                'type' => 'Personnel in Restricted Zone',
                'severity' => 'high',
                'status' => 'active',
                'location' => ['lat' => -2.186253, 'lng' => 117.4539035],
                'detected_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
                'description' => 'Personnel detected in restricted mining area',
                'cctv_id' => 'CCTV-001',
                'personnel_name' => 'MOHAMMAD NUR AKBAR HIDAYATULLAH',
                'distance' => '15mtr',
                'zone' => 'Tambang JOINT MW'
            ],
            
        ];

        // Apply filters
        if ($status !== 'all') {
            $hazardDetections = array_filter($hazardDetections, fn($h) => $h['status'] === $status);
        }
        
        if ($severity !== 'all') {
            $hazardDetections = array_filter($hazardDetections, fn($h) => $h['severity'] === $severity);
        }

        return response()->json([
            'success' => true,
            'data' => array_values($hazardDetections),
            'count' => count($hazardDetections)
        ]);
    }

    /**
     * Get CCTV data by name (for AJAX requests)
     */
    public function getCctvByName(Request $request)
    {
        $cctvName = $request->get('name');
        
        if (!$cctvName) {
            return response()->json([
                'success' => false,
                'message' => 'CCTV name is required'
            ], 400);
        }

        // Normalize CCTV name for better matching (remove spaces, dashes, underscores)
        $normalizedName = strtolower(preg_replace('/[\s\-_]/', '', $cctvName));
        
        $cctv = CctvData::where(function($query) use ($cctvName, $normalizedName) {
                $query->where('nama_cctv', 'like', '%' . $cctvName . '%')
                      ->orWhere('no_cctv', 'like', '%' . $cctvName . '%')
                      ->orWhereRaw('LOWER(REPLACE(REPLACE(REPLACE(nama_cctv, " ", ""), "-", ""), "_", "")) LIKE ?', ['%' . $normalizedName . '%']);
            })
            ->first();

        if (!$cctv) {
            return response()->json([
                'success' => false,
                'message' => 'CCTV not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $cctv->id,
                'no_cctv' => $cctv->no_cctv ?? null,
                'nomor_cctv' => $cctv->no_cctv ?? null,
                'name' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                'cctv_name' => $cctv->nama_cctv ?? null,
                'nama_cctv' => $cctv->nama_cctv ?? null,
                'location' => $cctv->longitude && $cctv->latitude ? [(float) $cctv->longitude, (float) $cctv->latitude] : null,
                'status' => $cctv->kondisi ?? $cctv->status ?? 'Unknown',
                'kondisi' => $cctv->kondisi ?? null,
                'site' => $cctv->site ?? null,
                'perusahaan' => $cctv->perusahaan ?? null,
                'perusahaan_cctv' => $cctv->perusahaan ?? null,
                'link_akses' => $cctv->link_akses ?? null,
                'externalUrl' => $cctv->link_akses ?? null,
                'rtsp_url' => null,
                'user_name' => $cctv->user_name ?? null,
                'password' => $cctv->password ?? null,
                'brand' => $this->extractBrandFromTipe($cctv->tipe_cctv ?? ''),
                'tipe_cctv' => $cctv->tipe_cctv ?? null,
                'fungsi_cctv' => $cctv->fungsi_cctv ?? null,
                'lokasi_pemasangan' => $cctv->lokasi_pemasangan ?? null,
                'control_room' => $cctv->control_room ?? null,
                'coverage_lokasi' => $cctv->coverage_lokasi ?? null,
            ]
        ]);
    }

    /**
     * Extract brand from tipe_cctv field
     */
    private function extractBrandFromTipe($tipe)
    {
        if (!$tipe) {
            return '';
        }

        $tipeLower = strtolower($tipe);
        
        if (strpos($tipeLower, 'hikvision') !== false || strpos($tipeLower, 'hik') !== false) {
            return 'HIKVision';
        } elseif (strpos($tipeLower, 'ezviz') !== false) {
            return 'Ezviz';
        } elseif (strpos($tipeLower, 'dahua') !== false) {
            return 'Dahua';
        }
        
        return '';
    }

    /**
     * Get hazard detections from PostgreSQL car_register table
     */
    private function getHazardDetectionsFromPostgres()
    {
        try {
            // Check if SSH tunnel is active
            if (!$this->isTunnelActive()) {
                \Log::warning('SSH tunnel is not active. Returning empty hazard detections.');
                return [];
            }

            // Query sederhana untuk Laporan Hazard Beats - hanya ambil field yang diperlukan
            // Schema yang digunakan: bcbeats
            $query = "
                SELECT 
                    cr.id,
                    cr.deskripsi,
                    cr.lokasi_detail,
                    cr.kekerapan,
                    cr.keparahan,
                    cr.nilai_resiko,
                    cr.create_date AS tanggal_pembuatan,
                    cr.location_latitude AS latitude,
                    cr.location_longitude AS longitude,
                    loc_d.nama AS nama_detail_lokasi,
                    loc.nama AS nama_lokasi,
                    site.nama AS nama_site,
                    mo.nama AS ketidaksesuaian,
                    od.nama AS subketidaksesuaian,
                    st.nama AS status,
                    req.nama AS nama_pelapor,
                    pic.nama AS nama_pic,
                    m_goldenrule.nama AS nama_goldenrule,
                    m_kategori_tipe.nama AS nama_kategori,
                    car_tindakan.tanggal_aktual_penyelesaian,
                    tob.name AS name_tools_observation
                FROM bcbeats.car_register cr
                    LEFT JOIN bcbeats.m_lokasi loc_d ON loc_d.id = cr.id_lokasi
                    LEFT JOIN bcbeats.m_lokasi loc ON loc.id = loc_d.id_parent
                    LEFT JOIN bcbeats.m_lokasi site ON site.id = loc.id_parent
                    LEFT JOIN bcbeats.m_lookup tob ON tob.id = cr.id_tools_observation
                    LEFT JOIN bcbeats.m_obyek_detil od ON od.id = cr.id_obyek_detil
                    LEFT JOIN bcbeats.m_obyek mo ON mo.id = cr.id_obyek
                    LEFT JOIN bcbeats.m_status st ON st.id = cr.id_status
                    LEFT JOIN bcsid.m_karyawan req ON req.id = cr.id_pelapor
                    LEFT JOIN bcsid.m_karyawan pic ON pic.id = cr.id_pic
                    LEFT JOIN bcbeats.m_goldenrule ON m_goldenrule.id = cr.id_goldenrule
                    LEFT JOIN bcbeats.m_kategori_tipe ON m_kategori_tipe.id = cr.id_kategori
                    LEFT JOIN bcbeats.car_tindakan ON car_tindakan.id_car_register = cr.id
                WHERE cr.id_sumberdata <> 200 
                    AND cr.create_date >= '2023-12-31 23:59:59'::timestamp without time zone
                ORDER BY cr.create_date DESC
                LIMIT 30
            ";

            $results = DB::connection('pgsql_ssh')->select($query);

            // Map data dari PostgreSQL ke format yang digunakan di view
            $hazardDetections = array_map(function ($row) {
                // Map keparahan ke severity
                $severityMap = [
                    'Sangat Tinggi' => 'critical',
                    'Tinggi' => 'high',
                    'Sedang' => 'medium',
                    'Rendah' => 'low',
                ];
                $severity = $severityMap[$row->keparahan ?? 'Sedang'] ?? 'medium';

                // Map status
                $statusMap = [
                    'Open' => 'active',
                    'Closed' => 'resolved',
                    'In Progress' => 'active',
                    'Resolved' => 'resolved',
                ];
                $status = $statusMap[$row->status ?? 'Open'] ?? 'active';

                // Format detected_at
                $detectedAt = $row->tanggal_pembuatan 
                    ? date('Y-m-d H:i:s', strtotime($row->tanggal_pembuatan))
                    : now()->format('Y-m-d H:i:s');

                // Format resolved_at jika ada
                $resolvedAt = null;
                if ($row->tanggal_aktual_penyelesaian) {
                    $resolvedAt = date('Y-m-d H:i:s', strtotime($row->tanggal_aktual_penyelesaian));
                }

                return [
                    'id' => 'HD-' . $row->id,
                    'type' => $row->ketidaksesuaian ?? $row->subketidaksesuaian ?? 'Hazard Detection',
                    'severity' => $severity,
                    'status' => $status,
                    'location' => [
                        'lat' => $row->latitude ? (float) $row->latitude : null,
                        'lng' => $row->longitude ? (float) $row->longitude : null,
                    ],
                    'detected_at' => $detectedAt,
                    'resolved_at' => $resolvedAt,
                    'description' => $row->deskripsi ?? $row->ketidaksesuaian ?? 'No description',
                    'cctv_id' => $row->name_tools_observation ?? 'N/A',
                    'personnel_name' => $row->nama_pelapor ?? null,
                    'equipment_id' => null,
                    'zone' => $row->nama_lokasi ?? $row->nama_detail_lokasi ?? $row->nama_site ?? 'Unknown',
                    'site' => $row->nama_site ?? null,
                    'lokasi_detail' => $row->lokasi_detail ?? null,
                    'nama_detail_lokasi' => $row->nama_detail_lokasi ?? null,
                    'nama_lokasi' => $row->nama_lokasi ?? null,
                    'keparahan' => $row->keparahan ?? null,
                    'kekerapan' => $row->kekerapan ?? null,
                    'nilai_resiko' => $row->nilai_resiko ?? null,
                    'nama_pelapor' => $row->nama_pelapor ?? null,
                    'nama_pic' => $row->nama_pic ?? null,
                    'nama_goldenrule' => $row->nama_goldenrule ?? null,
                    'nama_kategori' => $row->nama_kategori ?? null,
                    // URL foto menggunakan format: https://hseautomation.beraucoal.co.id/report/photoCar/{id}
                    // Halaman ini menampilkan Foto Temuan dan Foto Penyelesaian
                    'url_photo' => 'https://hseautomation.beraucoal.co.id/report/photoCar/' . $row->id,
                    'tanggal_pembuatan' => $row->tanggal_pembuatan ?? null,
                    'original_id' => $row->id, // ID asli dari database
                ];
            }, $results);

            return $hazardDetections;

        } catch (Exception $e) {
            \Log::error('Error fetching hazard detections from PostgreSQL: ' . $e->getMessage());
            // Return empty array on error
            return [];
        }
    }

    /**
     * Get incidents by CCTV ID or name
     * Mencocokkan berdasarkan coverage_detail_lokasi dari CCTV dengan lokasi di pelaporan hazard
     */
    public function getIncidentsByCctv(Request $request)
    {
        try {
            $cctvId = $request->input('cctv_id');
            $cctvName = $request->input('cctv_name');
            
            if (!$cctvId && !$cctvName) {
                return response()->json([
                    'success' => false,
                    'message' => 'CCTV ID or name is required'
                ], 400);
            }

            // Check if SSH tunnel is active
            if (!$this->isTunnelActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'SSH tunnel is not active',
                    'data' => []
                ]);
            }

            // Ambil data CCTV untuk mendapatkan coverage_detail_lokasi
            $cctvQuery = CctvData::query();
            if ($cctvId) {
                $cctvQuery->where('id', $cctvId);
            }
            if ($cctvName) {
                $cctvQuery->where(function($q) use ($cctvName) {
                    $q->where('nama_cctv', 'like', '%' . $cctvName . '%')
                      ->orWhere('no_cctv', 'like', '%' . $cctvName . '%');
                });
            }
            
            $cctv = $cctvQuery->first();
            
            if (!$cctv) {
                return response()->json([
                    'success' => false,
                    'message' => 'CCTV not found',
                    'data' => []
                ], 404);
            }

            $coverageDetailLokasi = $cctv->coverage_detail_lokasi;
            
            if (!$coverageDetailLokasi) {
                return response()->json([
                    'success' => true,
                    'message' => 'CCTV does not have coverage_detail_lokasi',
                    'data' => []
                ]);
            }

            // Query untuk mengambil insiden berdasarkan coverage_detail_lokasi
            // Mencocokkan coverage_detail_lokasi (CCTV) dengan detail lokasi yang ada di hazard (car_register)
            // Hanya menggunakan exact match untuk memastikan hanya hazard dengan detail lokasi yang sama yang ditampilkan
            $query = "
                SELECT 
                    cr.id,
                    cr.deskripsi,
                    cr.lokasi_detail,
                    cr.kekerapan,
                    cr.keparahan,
                    cr.nilai_resiko,
                    cr.create_date AS tanggal_pembuatan,
                    cr.location_latitude AS latitude,
                    cr.location_longitude AS longitude,
                    loc_d.nama AS nama_detail_lokasi,
                    loc.nama AS nama_lokasi,
                    site.nama AS nama_site,
                    mo.nama AS ketidaksesuaian,
                    od.nama AS subketidaksesuaian,
                    st.nama AS status,
                    req.nama AS nama_pelapor,
                    pic.nama AS nama_pic,
                    m_goldenrule.nama AS nama_goldenrule,
                    m_kategori_tipe.nama AS nama_kategori,
                    car_tindakan.tanggal_aktual_penyelesaian,
                    tob.name AS name_tools_observation,
                    tob.id AS id_tools_observation
                FROM bcbeats.car_register cr
                    LEFT JOIN bcbeats.m_lokasi loc_d ON loc_d.id = cr.id_lokasi
                    LEFT JOIN bcbeats.m_lokasi loc ON loc.id = loc_d.id_parent
                    LEFT JOIN bcbeats.m_lokasi site ON site.id = loc.id_parent
                    LEFT JOIN bcbeats.m_lookup tob ON tob.id = cr.id_tools_observation
                    LEFT JOIN bcbeats.m_obyek_detil od ON od.id = cr.id_obyek_detil
                    LEFT JOIN bcbeats.m_obyek mo ON mo.id = cr.id_obyek
                    LEFT JOIN bcbeats.m_status st ON st.id = cr.id_status
                    LEFT JOIN bcsid.m_karyawan req ON req.id = cr.id_pelapor
                    LEFT JOIN bcsid.m_karyawan pic ON pic.id = cr.id_pic
                    LEFT JOIN bcbeats.m_goldenrule ON m_goldenrule.id = cr.id_goldenrule
                    LEFT JOIN bcbeats.m_kategori_tipe ON m_kategori_tipe.id = cr.id_kategori
                    LEFT JOIN bcbeats.car_tindakan ON car_tindakan.id_car_register = cr.id
                WHERE cr.id_sumberdata <> 200 
                    AND cr.create_date >= '2023-12-31 23:59:59'::timestamp without time zone
                    AND (
                        LOWER(TRIM(cr.lokasi_detail)) = LOWER(TRIM(?))
                        OR LOWER(TRIM(loc_d.nama)) = LOWER(TRIM(?))
                    )
                ORDER BY cr.create_date DESC
                LIMIT 500
            ";

            $exactMatch = trim($coverageDetailLokasi);

            $results = DB::connection('pgsql_ssh')->select($query, [
                $exactMatch,     // Exact match untuk cr.lokasi_detail
                $exactMatch      // Exact match untuk loc_d.nama
            ]);

            // Map data dari PostgreSQL ke format yang digunakan di view
            $incidents = array_map(function ($row) {
                // Map keparahan ke severity
                $severityMap = [
                    'Sangat Tinggi' => 'critical',
                    'Tinggi' => 'high',
                    'Sedang' => 'medium',
                    'Rendah' => 'low',
                ];
                $severity = $severityMap[$row->keparahan ?? 'Sedang'] ?? 'medium';

                // Map status
                $statusMap = [
                    'Open' => 'active',
                    'Closed' => 'resolved',
                    'In Progress' => 'active',
                    'Resolved' => 'resolved',
                ];
                $status = $statusMap[$row->status ?? 'Open'] ?? 'active';

                // Format detected_at
                $detectedAt = $row->tanggal_pembuatan 
                    ? date('Y-m-d H:i:s', strtotime($row->tanggal_pembuatan))
                    : now()->format('Y-m-d H:i:s');

                // Format resolved_at jika ada
                $resolvedAt = null;
                if ($row->tanggal_aktual_penyelesaian) {
                    $resolvedAt = date('Y-m-d H:i:s', strtotime($row->tanggal_aktual_penyelesaian));
                }

                return [
                    'id' => 'HD-' . $row->id,
                    'type' => $row->ketidaksesuaian ?? $row->subketidaksesuaian ?? 'Hazard Detection',
                    'severity' => $severity,
                    'status' => $status,
                    'location' => [
                        'lat' => $row->latitude ? (float) $row->latitude : null,
                        'lng' => $row->longitude ? (float) $row->longitude : null,
                    ],
                    'detected_at' => $detectedAt,
                    'resolved_at' => $resolvedAt,
                    'description' => $row->deskripsi ?? $row->ketidaksesuaian ?? 'No description',
                    'cctv_id' => $row->name_tools_observation ?? 'N/A',
                    'personnel_name' => $row->nama_pelapor ?? null,
                    'equipment_id' => null,
                    'zone' => $row->nama_lokasi ?? $row->nama_detail_lokasi ?? $row->nama_site ?? 'Unknown',
                    'site' => $row->nama_site ?? null,
                    'lokasi_detail' => $row->lokasi_detail ?? null,
                    'nama_detail_lokasi' => $row->nama_detail_lokasi ?? null,
                    'nama_lokasi' => $row->nama_lokasi ?? null,
                    'keparahan' => $row->keparahan ?? null,
                    'kekerapan' => $row->kekerapan ?? null,
                    'nilai_resiko' => $row->nilai_resiko ?? null,
                    'nama_pelapor' => $row->nama_pelapor ?? null,
                    'nama_pic' => $row->nama_pic ?? null,
                    'nama_goldenrule' => $row->nama_goldenrule ?? null,
                    'nama_kategori' => $row->nama_kategori ?? null,
                    // URL foto menggunakan format: https://hseautomation.beraucoal.co.id/report/photoCar/{id}
                    'url_photo' => 'https://hseautomation.beraucoal.co.id/report/photoCar/' . $row->id,
                    'tanggal_pembuatan' => $row->tanggal_pembuatan ?? null,
                    'original_id' => $row->id,
                ];
            }, $results);

            return response()->json([
                'success' => true,
                'data' => $incidents,
                'count' => count($incidents)
            ]);

        } catch (Exception $e) {
            \Log::error('Error fetching incidents by CCTV: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching incidents: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get PJA (Pekerjaan Jalan Angkut) by CCTV location
     * Mengambil PJA di lokasi CCTV beserta laporan yang terkait
     */
    public function getPjaByCctv(Request $request)
    {
        try {
            $cctvId = $request->input('cctv_id');
            $cctvName = $request->input('cctv_name');
            
            if (!$cctvId && !$cctvName) {
                return response()->json([
                    'success' => false,
                    'message' => 'CCTV ID or name is required'
                ], 400);
            }

            // Ambil data CCTV untuk mendapatkan lokasi
            $cctvQuery = CctvData::query();
            if ($cctvId) {
                $cctvQuery->where('id', $cctvId);
            }
            if ($cctvName) {
                $cctvQuery->where(function($q) use ($cctvName) {
                    $q->where('nama_cctv', 'like', '%' . $cctvName . '%')
                      ->orWhere('no_cctv', 'like', '%' . $cctvName . '%');
                });
            }
            
            $cctv = $cctvQuery->first();
            
            if (!$cctv) {
                return response()->json([
                    'success' => false,
                    'message' => 'CCTV not found',
                    'data' => []
                ], 404);
            }

            // Ambil lokasi dari CCTV (prioritas: coverage_detail_lokasi > lokasi_pemasangan > coverage_lokasi)
            $lokasiCctv = $cctv->coverage_detail_lokasi 
                        ?? $cctv->lokasi_pemasangan 
                        ?? $cctv->coverage_lokasi 
                        ?? null;
            
            if (!$lokasiCctv) {
                return response()->json([
                    'success' => true,
                    'message' => 'CCTV does not have location information',
                    'data' => [],
                    'pja_list' => []
                ]);
            }

            // Query untuk mengambil PJA dari insiden_tabel berdasarkan lokasi
            $pjaList = InsidenTabel::whereNotNull('pja')
                ->where(function($q) use ($lokasiCctv) {
                    $q->where('lokasi', 'like', '%' . $lokasiCctv . '%')
                      ->orWhere('sublokasi', 'like', '%' . $lokasiCctv . '%')
                      ->orWhere('lokasi_spesifik', 'like', '%' . $lokasiCctv . '%')
                      ->orWhere('lokasi_validasi_hsecm', 'like', '%' . $lokasiCctv . '%');
                })
                ->select('pja')
                ->distinct()
                ->pluck('pja')
                ->filter()
                ->values();

            $result = [
                'cctv_info' => [
                    'id' => $cctv->id,
                    'nama_cctv' => $cctv->nama_cctv,
                    'no_cctv' => $cctv->no_cctv,
                    'lokasi' => $lokasiCctv,
                    'site' => $cctv->site,
                    'perusahaan' => $cctv->perusahaan,
                ],
                'pja_list' => []
            ];

            // Untuk setiap PJA, ambil laporan yang terkait
            foreach ($pjaList as $pja) {
                if (empty($pja)) continue;

                // Ambil insiden dari insiden_tabel untuk PJA ini
                $insidenList = InsidenTabel::where('pja', $pja)
                    ->where(function($q) use ($lokasiCctv) {
                        $q->where('lokasi', 'like', '%' . $lokasiCctv . '%')
                          ->orWhere('sublokasi', 'like', '%' . $lokasiCctv . '%')
                          ->orWhere('lokasi_spesifik', 'like', '%' . $lokasiCctv . '%')
                          ->orWhere('lokasi_validasi_hsecm', 'like', '%' . $lokasiCctv . '%');
                    })
                    ->orderBy('tanggal', 'desc')
                    ->limit(50)
                    ->get();

                // Ambil hazard dari car_register untuk lokasi yang sama (jika SSH tunnel aktif)
                $hazardList = [];
                if ($this->isTunnelActive()) {
                    try {
                        $searchPattern = '%' . $lokasiCctv . '%';
                        $hazardQuery = "
                            SELECT 
                                cr.id,
                                cr.deskripsi,
                                cr.lokasi_detail,
                                cr.kekerapan,
                                cr.keparahan,
                                cr.nilai_resiko,
                                cr.create_date AS tanggal_pembuatan,
                                cr.location_latitude AS latitude,
                                cr.location_longitude AS longitude,
                                loc_d.nama AS nama_detail_lokasi,
                                loc.nama AS nama_lokasi,
                                site.nama AS nama_site,
                                mo.nama AS ketidaksesuaian,
                                od.nama AS subketidaksesuaian,
                                st.nama AS status,
                                req.nama AS nama_pelapor,
                                pic.nama AS nama_pic,
                                m_goldenrule.nama AS nama_goldenrule,
                                m_kategori_tipe.nama AS nama_kategori,
                                car_tindakan.tanggal_aktual_penyelesaian,
                                tob.name AS name_tools_observation
                            FROM bcbeats.car_register cr
                                LEFT JOIN bcbeats.m_lokasi loc_d ON loc_d.id = cr.id_lokasi
                                LEFT JOIN bcbeats.m_lokasi loc ON loc.id = loc_d.id_parent
                                LEFT JOIN bcbeats.m_lokasi site ON site.id = loc.id_parent
                                LEFT JOIN bcbeats.m_lookup tob ON tob.id = cr.id_tools_observation
                                LEFT JOIN bcbeats.m_obyek_detil od ON od.id = cr.id_obyek_detil
                                LEFT JOIN bcbeats.m_obyek mo ON mo.id = cr.id_obyek
                                LEFT JOIN bcbeats.m_status st ON st.id = cr.id_status
                                LEFT JOIN bcsid.m_karyawan req ON req.id = cr.id_pelapor
                                LEFT JOIN bcsid.m_karyawan pic ON pic.id = cr.id_pic
                                LEFT JOIN bcbeats.m_goldenrule ON m_goldenrule.id = cr.id_goldenrule
                                LEFT JOIN bcbeats.m_kategori_tipe ON m_kategori_tipe.id = cr.id_kategori
                                LEFT JOIN bcbeats.car_tindakan ON car_tindakan.id_car_register = cr.id
                            WHERE cr.id_sumberdata <> 200 
                                AND cr.create_date >= '2023-12-31 23:59:59'::timestamp without time zone
                                AND (
                                    LOWER(cr.lokasi_detail) LIKE LOWER(?)
                                    OR LOWER(loc_d.nama) LIKE LOWER(?)
                                    OR LOWER(loc.nama) LIKE LOWER(?)
                                    OR LOWER(site.nama) LIKE LOWER(?)
                                )
                            ORDER BY cr.create_date DESC
                            LIMIT 50
                        ";

                        $hazardResults = DB::connection('pgsql_ssh')->select($hazardQuery, [
                            $searchPattern,
                            $searchPattern,
                            $searchPattern,
                            $searchPattern
                        ]);

                        // Map hazard data
                        $hazardList = array_map(function ($row) {
                            $severityMap = [
                                'Sangat Tinggi' => 'critical',
                                'Tinggi' => 'high',
                                'Sedang' => 'medium',
                                'Rendah' => 'low',
                            ];
                            $severity = $severityMap[$row->keparahan ?? 'Sedang'] ?? 'medium';

                            $statusMap = [
                                'Open' => 'active',
                                'Closed' => 'resolved',
                                'In Progress' => 'active',
                                'Resolved' => 'resolved',
                            ];
                            $status = $statusMap[$row->status ?? 'Open'] ?? 'active';

                            return [
                                'id' => 'HD-' . $row->id,
                                'type' => $row->ketidaksesuaian ?? $row->subketidaksesuaian ?? 'Hazard Detection',
                                'severity' => $severity,
                                'status' => $status,
                                'description' => $row->deskripsi ?? 'No description',
                                'keparahan' => $row->keparahan ?? null,
                                'tanggal_pembuatan' => $row->tanggal_pembuatan ? date('Y-m-d H:i:s', strtotime($row->tanggal_pembuatan)) : null,
                                'nama_pelapor' => $row->nama_pelapor ?? null,
                                'nama_pic' => $row->nama_pic ?? null,
                                'nama_goldenrule' => $row->nama_goldenrule ?? null,
                                'nama_kategori' => $row->nama_kategori ?? null,
                                'original_id' => $row->id,
                            ];
                        }, $hazardResults);
                    } catch (Exception $e) {
                        \Log::warning('Error fetching hazards for PJA: ' . $e->getMessage());
                    }
                }

                // Ambil nama orang PJA dari insiden pertama (asumsi semua insiden dalam PJA yang sama memiliki nama PJA yang sama)
                $namaPjaPerson = null;
                if ($insidenList->count() > 0) {
                    $firstInsiden = $insidenList->first();
                    $namaPjaPerson = $firstInsiden->nama ?? $firstInsiden->atasan_langsung ?? null;
                }

                // Format insiden data
                $formattedInsiden = $insidenList->map(function ($insiden) {
                    return [
                        'no_kecelakaan' => $insiden->no_kecelakaan,
                        'tanggal' => $insiden->tanggal ? $insiden->tanggal->format('Y-m-d') : null,
                        'site' => $insiden->site,
                        'lokasi' => $insiden->lokasi,
                        'sublokasi' => $insiden->sublokasi,
                        'lokasi_spesifik' => $insiden->lokasi_spesifik,
                        'kategori' => $insiden->kategori,
                        'status_lpi' => $insiden->status_lpi,
                        'kronologis' => $insiden->kronologis,
                        'high_potential' => $insiden->high_potential,
                        'layer' => $insiden->layer,
                        'jenis_item_ipls' => $insiden->jenis_item_ipls,
                        'nama' => $insiden->nama,
                        'jabatan' => $insiden->jabatan,
                        'atasan_langsung' => $insiden->atasan_langsung,
                        'jabatan_atasan_langsung' => $insiden->jabatan_atasan_langsung,
                    ];
                })->toArray();

                $result['pja_list'][] = [
                    'pja' => $pja,
                    'nama_pja_person' => $namaPjaPerson, // Nama orang PJA
                    'insiden_count' => $insidenList->count(),
                    'hazard_count' => count($hazardList),
                    'insiden' => $formattedInsiden,
                    'hazards' => $hazardList,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'pja_count' => count($result['pja_list'])
            ]);

        } catch (Exception $e) {
            \Log::error('Error fetching PJA by CCTV: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching PJA: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get GR detections from MySQL gr_table
     * Only show GR with value "Valid" and "Potentially"
     */
    /**
     * Get SAP (Safety Action Plan) data from ClickHouse
     * Mengambil data dari 4 tabel terpisah:
     * - nitip.tabel_inspeksi_hazard (INSPEKSI, HAZARD)
     * - nitip.tabel_observasi (OBSERVASI)
     * - nitip.tabel_oak_pic (OAK)
     * - nitip.tabel_coaching (COACHING)
     * Filter per week: Senin sampai Senin (1 week)
     */
    private function getSapDataFromClickHouse($weekStart = null)
    {
        try {
            $clickhouse = new ClickHouseService();
            
            if (!$clickhouse->isConnected()) {
                Log::warning('ClickHouse is not connected. Returning empty SAP data.');
                return [];
            }

            // Jika weekStart tidak diberikan, gunakan Senin minggu ini
            if (!$weekStart) {
                $today = Carbon::now();
                $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY)->setTime(0, 0, 0);
            } else {
                // Parse weekStart string (format: YYYY-MM-DD HH:MM:SS atau YYYY-MM-DD)
                $weekStart = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY)->setTime(0, 0, 0);
            }
            
            // Week end adalah Senin berikutnya (7 hari setelah weekStart) pada 00:00:00
            $weekEnd = $weekStart->copy()->addDays(7)->setTime(0, 0, 0);
            
            $weekStartStr = $weekStart->format('Y-m-d');
            $weekEndStr = $weekEnd->format('Y-m-d');
            
            Log::info('SAP Query - Week Start: ' . $weekStartStr . ', Week End: ' . $weekEndStr);
            
            $sapData = [];
            $resultsInspeksi = [];
            $resultsObservasi = [];
            $resultsOak = [];
            $resultsCoaching = [];
            
            // 1. Query tabel_inspeksi_hazard
            try {
                $sqlInspeksi = "
                    SELECT 
                        toString(id) as task_number,
                        toString(jenis_laporan) as jenis_laporan,
                        toString(deskripsi) as aktivitas_pekerjaan,
                        toString(lokasi) as lokasi,
                        toString(`detail lokasi`) as detail_lokasi,
                        toString(deskripsi) as keterangan,
                        toString(`tanggal pelaporan`) as tanggal_pelaporan,
                        toString(`perusahaan pelapor`) as perusahaan_pelapor,
                        toString(pelapor) as pelapor,
                        toString(sid_pelapor) as sid_pelapor,
                        toString(`jabatan fungsional pelapor`) as jabatan_fungsional_pelapor,
                        toString(`departemen pelapor`) as departemen_pelapor,
                        toString(pic) as pic,
                        toString(`sid pic`) as sid_pic,
                        toString(`jabatan fungsional pic`) as jabatan_fungsional_pic,
                        toString(`perusahaan pic`) as perusahaan_pic,
                        toString(`departemen pic`) as departemen_pic,
                        toString(`url foto`) as url_foto,
                        toString(`tools pengawasan`) as tools_pengawasan,
                        toString(tindakan) as catatan_tindakan,
                        toString(id_pelapor) as nik_pelapor,
                        toString(pelapor) as nama_pelapor,
                        toString(`perusahaan pelapor`) as nama_perusahaan_pelapor_karyawan,
                        toString(`jabatan fungsional pelapor`) as jabatan_fungsional_karyawan_pelapor,
                        ifNull(toString(latitude), '') as latitude,
                        ifNull(toString(longitude), '') as longitude,
                        ifNull(toString(site), '') as site
                    FROM nitip.tabel_inspeksi_hazard
                    WHERE toDate(`tanggal pelaporan`) >= toDate('{$weekStartStr}')
                        AND toDate(`tanggal pelaporan`) < toDate('{$weekEndStr}')
                    ORDER BY toDateTime(`tanggal pelaporan`) DESC
                    LIMIT 12500
                ";
                
                $resultsInspeksi = $clickhouse->query($sqlInspeksi);
                if (!empty($resultsInspeksi) && is_array($resultsInspeksi)) {
                    foreach ($resultsInspeksi as $row) {
                        $sapData[] = $this->formatSapRow($row, 'INSPEKSI_HAZARD');
                    }
                }
                Log::info('Inspeksi Hazard: ' . count($resultsInspeksi ?? []) . ' records');
            } catch (Exception $e) {
                Log::error('Error querying tabel_inspeksi_hazard: ' . $e->getMessage());
            }
            
            // 2. Query tabel_observasi
            try {
                $sqlObservasi = "
                    SELECT 
                        toString(TaskNumber) as task_number,
                        toString(`aktivitas pekerjaan diobservasi`) as aktivitas_pekerjaan,
                        toString(lokasi) as lokasi,
                        toString(`detail lokasi`) as detail_lokasi,
                        toString(keterangan) as keterangan,
                        toString(`tanggal pelaporan`) as tanggal_pelaporan,
                        toString(`perusahaan pelapor`) as perusahaan_pelapor,
                        toString(pelapor) as pelapor,
                        toString(`sid pelapor`) as sid_pelapor,
                        toString(`jabatan fungsional pelapor`) as jabatan_fungsional_pelapor,
                        toString(`departemen pelapor`) as departemen_pelapor,
                        toString(pic) as pic,
                        toString(`sid pic`) as sid_pic,
                        toString(`jabatan fungsional pic`) as jabatan_fungsional_pic,
                        toString(`perusahaan pic`) as perusahaan_pic,
                        toString(`departemen pic`) as departemen_pic,
                        toString(`url foto`) as url_foto,
                        toString(`tools pengawasan`) as tools_pengawasan,
                        toString(`catatan OBS`) as catatan_tindakan,
                        toString(pelapor) as nama_pelapor,
                        toString(`perusahaan pelapor`) as nama_perusahaan_pelapor_karyawan,
                        toString(`jabatan fungsional pelapor`) as jabatan_fungsional_karyawan_pelapor
                    FROM nitip.tabel_observasi
                    WHERE toDate(`tanggal pelaporan`) >= toDate('{$weekStartStr}')
                        AND toDate(`tanggal pelaporan`) < toDate('{$weekEndStr}')
                    ORDER BY toDateTime(`tanggal pelaporan`) DESC
                    LIMIT 12500
                ";
                
                $resultsObservasi = $clickhouse->query($sqlObservasi);
                if (!empty($resultsObservasi) && is_array($resultsObservasi)) {
                    foreach ($resultsObservasi as $row) {
                        $sapData[] = $this->formatSapRow($row, 'OBSERVASI');
                    }
                }
                Log::info('Observasi: ' . count($resultsObservasi ?? []) . ' records');
            } catch (Exception $e) {
                Log::error('Error querying tabel_observasi: ' . $e->getMessage());
            }
            
            // 3. Query tabel_oak_pic
            try {
                $sqlOak = "
                    SELECT 
                        toString(TaskNumber) as task_number,
                        toString(`aktivitas pekerjaan oak`) as aktivitas_pekerjaan,
                        toString(lokasi) as lokasi,
                        toString(`detail lokasi`) as detail_lokasi,
                        toString(`hasil oak`) as keterangan,
                        toString(`tanggal pelaporan`) as tanggal_pelaporan,
                        toString(`perusahaan pelapor`) as perusahaan_pelapor,
                        toString(pelapor) as pelapor,
                        toString(sid_pelapor) as sid_pelapor,
                        toString(`jabatan fungsional pelapor`) as jabatan_fungsional_pelapor,
                        toString(pic) as pic,
                        toString(`sid pic`) as sid_pic,
                        toString(`jabatan fungsional pic`) as jabatan_fungsional_pic,
                        toString(`url foto`) as url_foto,
                        toString(`tools pengawasan`) as tools_pengawasan,
                        toString(pelapor) as nama_pelapor,
                        toString(`perusahaan pelapor`) as nama_perusahaan_pelapor_karyawan,
                        toString(`jabatan fungsional pelapor`) as jabatan_fungsional_karyawan_pelapor
                    FROM nitip.tabel_oak_pic
                    WHERE toDate(`tanggal pelaporan`) >= toDate('{$weekStartStr}')
                        AND toDate(`tanggal pelaporan`) < toDate('{$weekEndStr}')
                    ORDER BY toDateTime(`tanggal pelaporan`) DESC
                    LIMIT 12500
                ";
                
                $resultsOak = $clickhouse->query($sqlOak);
                if (!empty($resultsOak) && is_array($resultsOak)) {
                    foreach ($resultsOak as $row) {
                        $sapData[] = $this->formatSapRow($row, 'OAK');
                    }
                }
                Log::info('OAK: ' . count($resultsOak ?? []) . ' records');
            } catch (Exception $e) {
                Log::error('Error querying tabel_oak_pic: ' . $e->getMessage());
            }
            
            // 4. Query tabel_coaching
            try {
                $sqlCoaching = "
                    SELECT 
                        toString(`Task Number`) as task_number,
                        toString(`topik_coaching`) as aktivitas_pekerjaan,
                        toString(lokasi) as lokasi,
                        toString(`detail lokasi`) as detail_lokasi,
                        toString(`keterangan lokasi`) as keterangan,
                        toString(`tanggal pelaporan`) as tanggal_pelaporan,
                        toString(`perusahaan pelapor`) as perusahaan_pelapor,
                        toString(pelapor) as pelapor,
                        toString(`sid pelapor`) as sid_pelapor,
                        toString(`jabatan fungsional pelapor`) as jabatan_fungsional_pelapor,
                        toString(`departemen pelapor`) as departemen_pelapor,
                        toString(pic) as pic,
                        toString(`sid pic`) as sid_pic,
                        toString(`jabatan fungsional pic`) as jabatan_fungsional_pic,
                        toString(`perusahaan pic`) as perusahaan_pic,
                        toString(`departemen pic`) as departemen_pic,
                        toString(`url foto`) as url_foto,
                        toString(`tools pengawasan`) as tools_pengawasan,
                        toString(`catatan_coach`) as catatan_tindakan,
                        toString(id_coachee) as nik_pelapor,
                        toString(pelapor) as nama_pelapor,
                        toString(divisi_coachee) as divisi_pelapor,
                        toString(`departemen pelapor`) as departement_pelapor_karyawan,
                        toString(`perusahaan pelapor`) as nama_perusahaan_pelapor_karyawan,
                        toString(`jabatan fungsional pelapor`) as jabatan_fungsional_karyawan_pelapor,
                        toString(`jabatan struktural pelapor`) as jabatan_struktural_pelapor,
                        ifNull(toString(latitude), '') as latitude,
                        ifNull(toString(longitude), '') as longitude,
                        ifNull(toString(site), '') as site
                    FROM nitip.tabel_coaching
                    WHERE toDate(`tanggal pelaporan`) >= toDate('{$weekStartStr}')
                        AND toDate(`tanggal pelaporan`) < toDate('{$weekEndStr}')
                    ORDER BY toDateTime(`tanggal pelaporan`) DESC
                    LIMIT 12500
                ";
                
                $resultsCoaching = $clickhouse->query($sqlCoaching);
                if (!empty($resultsCoaching) && is_array($resultsCoaching)) {
                    foreach ($resultsCoaching as $row) {
                        $sapData[] = $this->formatSapRow($row, 'COACHING');
                    }
                }
                Log::info('Coaching: ' . count($resultsCoaching ?? []) . ' records');
            } catch (Exception $e) {
                Log::error('Error querying tabel_coaching: ' . $e->getMessage());
            }
            
            // Sort by tanggal_pelaporan descending
            usort($sapData, function($a, $b) {
                $dateA = $a['tanggal_pelaporan'] ?? '';
                $dateB = $b['tanggal_pelaporan'] ?? '';
                return strcmp($dateB, $dateA);
            });
            
            Log::info('SAP data fetched: ' . count($sapData) . ' items from 4 tables (Inspeksi: ' . count($resultsInspeksi ?? []) . ', Observasi: ' . count($resultsObservasi ?? []) . ', OAK: ' . count($resultsOak ?? []) . ', Coaching: ' . count($resultsCoaching ?? []) . ')');
            
            if (count($sapData) === 0) {
                Log::warning('No SAP data found for week: ' . $weekStartStr . ' to ' . $weekEndStr);
            }
            
            return $sapData;

        } catch (Exception $e) {
            Log::error('Error fetching SAP data from ClickHouse: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Format SAP row data untuk konsistensi
     */
    private function formatSapRow($row, $sourceType)
    {
        // Get coordinates if available
        $latitude = null;
        $longitude = null;
        
        if (!empty($row['latitude']) && is_numeric($row['latitude'])) {
            $latitude = floatval($row['latitude']);
        }
        if (!empty($row['longitude']) && is_numeric($row['longitude'])) {
            $longitude = floatval($row['longitude']);
        }
        
        // Set jenis_laporan berdasarkan source type jika tidak ada
        $jenisLaporan = $row['jenis_laporan'] ?? $sourceType;
        
        return [
            'id' => 'SAP-' . ($row['task_number'] ?? uniqid()),
            'task_number' => $row['task_number'] ?? null,
            'type' => $jenisLaporan,
            'jenis_laporan' => $jenisLaporan,
            'source_type' => $sourceType, // INSPEKSI_HAZARD, OBSERVASI, OAK, COACHING
            'aktivitas_pekerjaan' => $row['aktivitas_pekerjaan'] ?? null,
            'lokasi' => $row['lokasi'] ?? null,
            'detail_lokasi' => $row['detail_lokasi'] ?? null,
            'keterangan' => $row['keterangan'] ?? null,
            'tanggal_pelaporan' => $row['tanggal_pelaporan'] ?? null,
            'perusahaan_pelapor' => $row['perusahaan_pelapor'] ?? null,
            'pelapor' => $row['pelapor'] ?? null,
            'nama_pelapor' => $row['nama_pelapor'] ?? $row['pelapor'] ?? null,
            'pic' => $row['pic'] ?? null,
            'url_foto' => $row['url_foto'] ?? null,
            'tools_pengawasan' => $row['tools_pengawasan'] ?? null,
            'catatan_tindakan' => $row['catatan_tindakan'] ?? null,
            'description' => $row['keterangan'] ?? $row['aktivitas_pekerjaan'] ?? 'No description',
            'severity' => 'medium',
            'status' => 'active',
            'location' => [
                'lat' => $latitude,
                'lng' => $longitude,
            ],
            'detected_at' => $row['tanggal_pelaporan'] ?? null,
            'site' => $row['site'] ?? null,
            'perusahaan' => $row['perusahaan_pelapor'] ?? null,
            'sid_pelapor' => $row['sid_pelapor'] ?? null,
            'jabatan_fungsional_pelapor' => $row['jabatan_fungsional_pelapor'] ?? null,
            'departemen_pelapor' => $row['departemen_pelapor'] ?? null,
            'sid_pic' => $row['sid_pic'] ?? null,
            'jabatan_fungsional_pic' => $row['jabatan_fungsional_pic'] ?? null,
            'perusahaan_pic' => $row['perusahaan_pic'] ?? null,
            'departemen_pic' => $row['departemen_pic'] ?? null,
            'nik_pelapor' => $row['nik_pelapor'] ?? null,
            'divisi_pelapor' => $row['divisi_pelapor'] ?? null,
            'jabatan_fungsional_karyawan_pelapor' => $row['jabatan_fungsional_karyawan_pelapor'] ?? null,
            'jabatan_struktural_pelapor' => $row['jabatan_struktural_pelapor'] ?? null,
        ];
    }

    private function getGrDetectionsFromPostgres()
    {
        try {
            // Ambil data GR dari MySQL menggunakan model GrTable
            // Filter hanya GR yang valid (gr = "Valid" atau "Potentially")
            $grRecords = GrTable::whereIn('gr', ['Valid', 'Potentially'])
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();

            // Map data ke format yang digunakan di view
            $grDetections = $grRecords->map(function ($gr) {
                $detectedAt = $gr->created_at 
                    ? $gr->created_at->format('Y-m-d H:i:s')
                    : now()->format('Y-m-d H:i:s');

                return [
                    'id' => 'GR-' . $gr->id,
                    'type' => 'GR Task',
                    'gr' => $gr->gr ?? 'N/A',
                    'catatan' => $gr->catatan ?? null,
                    'tasklist' => $gr->tasklist ?? 'N/A',
                    'severity' => 'medium', // Default severity untuk GR
                    'status' => 'active', // Default status
                    'location' => [
                        'lat' => null,
                        'lng' => null,
                    ],
                    'detected_at' => $detectedAt,
                    'description' => $gr->catatan ?? $gr->tasklist ?? 'No description',
                    'zone' => 'Unknown',
                    'site' => null,
                    'nama_lokasi' => null,
                    'nama_detail_lokasi' => null,
                    'nama_pelapor' => null,
                    'nama_pic' => null,
                    'nama_goldenrule' => null,
                    'nama_kategori' => null,
                    'url_photo' => null,
                    'tanggal_pembuatan' => $detectedAt,
                    'original_id' => $gr->id,
                ];
            })->toArray();

            return $grDetections;

        } catch (Exception $e) {
            \Log::error('Error fetching GR detections from MySQL: ' . $e->getMessage());
            // Return empty array on error
            return [];
        }
    }

    /**
     * Get count of valid GR that match with PostgreSQL car_register data
     * Mencocokkan tasklist dari gr_table (dimana gr = "Valid") dengan cr.id dari PostgreSQL
     */
    private function getValidGrCount()
    {
        try {
            // Ambil semua tasklist dari gr_table yang memiliki gr = "Valid"
            $validGrTasklists = GrTable::where('gr', 'Valid')
                ->pluck('tasklist')
                ->toArray();

            if (empty($validGrTasklists)) {
                return 0;
            }

            // Check if SSH tunnel is active
            if (!$this->isTunnelActive()) {
                \Log::warning('SSH tunnel is not active. Cannot count valid GR.');
                return 0;
            }

            // Query untuk menghitung jumlah cr.id yang cocok dengan tasklist yang valid
            // Menggunakan query yang sama dengan getHazardDetectionsFromPostgres untuk konsistensi
            $placeholders = implode(',', array_fill(0, count($validGrTasklists), '?'));
            
            $query = "
                SELECT COUNT(DISTINCT cr.id) as total
                FROM bcbeats.car_register cr
                    LEFT JOIN bcbeats.m_lokasi loc_d ON loc_d.id = cr.id_lokasi
                    LEFT JOIN bcbeats.m_lokasi loc ON loc.id = loc_d.id_parent
                    LEFT JOIN bcbeats.m_lokasi site ON site.id = loc.id_parent
                    LEFT JOIN bcbeats.m_lookup tob ON tob.id = cr.id_tools_observation
                    LEFT JOIN bcbeats.m_obyek_detil od ON od.id = cr.id_obyek_detil
                    LEFT JOIN bcbeats.m_obyek mo ON mo.id = cr.id_obyek
                    LEFT JOIN bcbeats.m_status st ON st.id = cr.id_status
                    LEFT JOIN bcsid.m_karyawan req ON req.id = cr.id_pelapor
                    LEFT JOIN bcsid.m_karyawan pic ON pic.id = cr.id_pic
                    LEFT JOIN bcbeats.m_goldenrule ON m_goldenrule.id = cr.id_goldenrule
                    LEFT JOIN bcbeats.m_kategori_tipe ON m_kategori_tipe.id = cr.id_kategori
                    LEFT JOIN bcbeats.car_tindakan ON car_tindakan.id_car_register = cr.id
                WHERE cr.id_sumberdata <> 200 
                    AND cr.create_date >= '2023-12-31 23:59:59'::timestamp without time zone
                    AND cr.id IN ($placeholders)
            ";

            $results = DB::connection('pgsql_ssh')->select($query, $validGrTasklists);
            
            return $results[0]->total ?? 0;

        } catch (Exception $e) {
            \Log::error('Error counting valid GR: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if SSH tunnel is active
     */
    private function isTunnelActive()
    {
        $localPort = config('database.connections.pgsql_ssh.local_port', 5433);
        $connection = @fsockopen('127.0.0.1', $localPort, $errno, $errstr, 1);
        if ($connection) {
            fclose($connection);
            return true;
        }
        return false;
    }

    /**
     * Get photos from photoCar page
     * Extract Foto Temuan and Foto Penyelesaian URLs from photoCar page
     */
    public function getPhotosFromPhotoCar(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $id = $request->input('id');
        $photoCarUrl = 'https://hseautomation.beraucoal.co.id/report/photoCar/' . $id;

        try {
            $response = Http::timeout(10)->get($photoCarUrl);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch photoCar page',
                    'data' => [
                        'foto_temuan' => null,
                        'foto_penyelesaian' => null,
                    ]
                ], 404);
            }

            $html = $response->body();

            // Check if page has "No Photo"
            if (stripos($html, 'No Photo') !== false && stripos($html, 'Foto Temuan') === false) {
                return response()->json([
                    'success' => true,
                    'message' => 'No photos found',
                    'data' => [
                        'foto_temuan' => null,
                        'foto_penyelesaian' => null,
                    ]
                ]);
            }

            // Extract Foto Temuan
            $fotoTemuanUrl = null;
            $fotoPenyelesaianUrl = null;

            // Pattern untuk mencari URL foto di section Foto Temuan
            $patterns = [
                // Cari link "Unduh" di section Foto Temuan
                '/Foto Temuan[^>]*>.*?<a[^>]+href=["\']([^"\']*beats2\/file[^"\']*)["\']/is',
                // Cari img src di section Foto Temuan
                '/Foto Temuan[^>]*>.*?<img[^>]+src=["\']([^"\']*beats2\/file[^"\']*)["\']/is',
                // Cari img data-src di section Foto Temuan
                '/Foto Temuan[^>]*>.*?<img[^>]+data-src=["\']([^"\']*beats2\/file[^"\']*)["\']/is',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $fotoTemuanUrl = $matches[1];
                    // Make absolute URL if relative
                    if (strpos($fotoTemuanUrl, 'http') !== 0) {
                        if (strpos($fotoTemuanUrl, '/') === 0) {
                            $fotoTemuanUrl = 'https://hseautomation.beraucoal.co.id' . $fotoTemuanUrl;
                        } else {
                            $fotoTemuanUrl = 'https://hseautomation.beraucoal.co.id/' . ltrim($fotoTemuanUrl, '/');
                        }
                    }
                    break;
                }
            }

            // Pattern untuk mencari URL foto di section Foto Penyelesaian
            $patternsPenyelesaian = [
                // Cari link "Unduh" di section Foto Penyelesaian
                '/Foto Penyelesaian[^>]*>.*?<a[^>]+href=["\']([^"\']*beats2\/file[^"\']*)["\']/is',
                // Cari img src di section Foto Penyelesaian
                '/Foto Penyelesaian[^>]*>.*?<img[^>]+src=["\']([^"\']*beats2\/file[^"\']*)["\']/is',
                // Cari img data-src di section Foto Penyelesaian
                '/Foto Penyelesaian[^>]*>.*?<img[^>]+data-src=["\']([^"\']*beats2\/file[^"\']*)["\']/is',
            ];

            foreach ($patternsPenyelesaian as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $fotoPenyelesaianUrl = $matches[1];
                    // Make absolute URL if relative
                    if (strpos($fotoPenyelesaianUrl, 'http') !== 0) {
                        if (strpos($fotoPenyelesaianUrl, '/') === 0) {
                            $fotoPenyelesaianUrl = 'https://hseautomation.beraucoal.co.id' . $fotoPenyelesaianUrl;
                        } else {
                            $fotoPenyelesaianUrl = 'https://hseautomation.beraucoal.co.id/' . ltrim($fotoPenyelesaianUrl, '/');
                        }
                    }
                    break;
                }
            }

            // Fallback: cari semua link dengan beats2/file
            if (!$fotoTemuanUrl) {
                if (preg_match_all('/<a[^>]+href=["\']([^"\']*beats2\/file[^"\']*)["\']/i', $html, $allMatches)) {
                    if (isset($allMatches[1][0])) {
                        $fotoTemuanUrl = $allMatches[1][0];
                        if (strpos($fotoTemuanUrl, 'http') !== 0) {
                            if (strpos($fotoTemuanUrl, '/') === 0) {
                                $fotoTemuanUrl = 'https://hseautomation.beraucoal.co.id' . $fotoTemuanUrl;
                            } else {
                                $fotoTemuanUrl = 'https://hseautomation.beraucoal.co.id/' . ltrim($fotoTemuanUrl, '/');
                            }
                        }
                    }
                    // Jika ada link kedua, itu mungkin Foto Penyelesaian
                    if (isset($allMatches[1][1])) {
                        $fotoPenyelesaianUrl = $allMatches[1][1];
                        if (strpos($fotoPenyelesaianUrl, 'http') !== 0) {
                            if (strpos($fotoPenyelesaianUrl, '/') === 0) {
                                $fotoPenyelesaianUrl = 'https://hseautomation.beraucoal.co.id' . $fotoPenyelesaianUrl;
                            } else {
                                $fotoPenyelesaianUrl = 'https://hseautomation.beraucoal.co.id/' . ltrim($fotoPenyelesaianUrl, '/');
                            }
                        }
                    }
                }
            }

            // Fallback: cari semua img dengan beats2/file
            if (!$fotoTemuanUrl) {
                if (preg_match_all('/<img[^>]+(?:src|data-src)=["\']([^"\']*beats2\/file[^"\']*)["\']/i', $html, $imgMatches)) {
                    if (isset($imgMatches[1][0])) {
                        $fotoTemuanUrl = $imgMatches[1][0];
                        if (strpos($fotoTemuanUrl, 'http') !== 0) {
                            if (strpos($fotoTemuanUrl, '/') === 0) {
                                $fotoTemuanUrl = 'https://hseautomation.beraucoal.co.id' . $fotoTemuanUrl;
                            } else {
                                $fotoTemuanUrl = 'https://hseautomation.beraucoal.co.id/' . ltrim($fotoTemuanUrl, '/');
                            }
                        }
                    }
                    // Jika ada img kedua, itu mungkin Foto Penyelesaian
                    if (isset($imgMatches[1][1])) {
                        $fotoPenyelesaianUrl = $imgMatches[1][1];
                        if (strpos($fotoPenyelesaianUrl, 'http') !== 0) {
                            if (strpos($fotoPenyelesaianUrl, '/') === 0) {
                                $fotoPenyelesaianUrl = 'https://hseautomation.beraucoal.co.id' . $fotoPenyelesaianUrl;
                            } else {
                                $fotoPenyelesaianUrl = 'https://hseautomation.beraucoal.co.id/' . ltrim($fotoPenyelesaianUrl, '/');
                            }
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Photos extracted successfully',
                'data' => [
                    'foto_temuan' => $fotoTemuanUrl,
                    'foto_penyelesaian' => $fotoPenyelesaianUrl,
                    'photo_car_url' => $photoCarUrl,
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching photos from photoCar: ' . $e->getMessage(), [
                'id' => $id,
                'url' => $photoCarUrl,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching photos: ' . $e->getMessage(),
                'data' => [
                    'foto_temuan' => null,
                    'foto_penyelesaian' => null,
                ]
            ], 500);
        }
    }

    /**
     * Get company statistics for modal
     */
    public function getCompanyStats(Request $request)
    {
        try {
            $company = trim($request->query('company', '__all__'));
            $site = trim($request->query('site', '__all__'));
            
            $query = CctvData::query();
            
            // Filter by company
            if ($company !== '__all__') {
                if (strcasecmp($company, 'Tidak Diketahui') === 0) {
                    $query->where(function ($q) {
                        $q->whereNull('perusahaan')
                          ->orWhere('perusahaan', '');
                    });
                } else {
                    $query->whereRaw('TRIM(perusahaan) = ?', [$company]);
                }
            }

            // Filter by site
            if ($site !== '__all__') {
                if (strcasecmp($site, 'Tidak Diketahui') === 0) {
                    $query->where(function ($q) {
                        $q->whereNull('site')
                          ->orWhere('site', '');
                    });
                } else {
                    $query->whereRaw('TRIM(site) = ?', [$site]);
                }
            }
            
            $total = $query->count();
            
            // CCTV Aktif
            $aktif = (clone $query)->where(function($q) {
                $q->where('status', 'Live View')
                  ->orWhere('kondisi', 'Baik');
            })->count();
            
            // CCTV Non Aktif
            $nonAktif = $total - $aktif;
            
            // Area Kritis
            // kategori_area_tercapture hanya ada 2 nilai: "Area Non Kritis" dan "Area Kritis"
            $areaKritis = (clone $query)->where(function($q) {
                $q->where('kategori_area_tercapture', 'Area Kritis')
                  ->orWhere('coverage_lokasi', 'like', '%kritis%')
                  ->orWhere('coverage_lokasi', 'like', '%critical%');
            })->count();
            
            return response()->json([
                'success' => true,
                'total' => $total,
                'aktif' => $aktif,
                'nonAktif' => $nonAktif,
                'areaKritis' => $areaKritis,
                'percentageAktif' => $total > 0 ? round(($aktif / $total) * 100, 1) : 0,
                'percentageNonAktif' => $total > 0 ? round(($nonAktif / $total) * 100, 1) : 0,
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching company stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'total' => 0,
                'aktif' => 0,
                'nonAktif' => 0,
                'areaKritis' => 0,
            ], 500);
        }
    }

    /**
     * Return CCTV data grouped by selected company for DataTable (server-side processing)
     */
    public function getCompanyCctvData(Request $request)
    {
        try {
            $draw = $request->get('draw');
            $start = $request->get('start', 0);
            $length = $request->get('length', 10);
            $searchValue = $request->get('search')['value'] ?? '';
            $orderColumn = $request->get('order')[0]['column'] ?? 0;
            $orderDir = $request->get('order')[0]['dir'] ?? 'asc';
            $company = trim($request->query('company', '__all__'));
            $site = trim($request->query('site', '__all__'));

            // Column mapping (sesuai urutan kolom di DataTable)
            $columns = ['site', 'perusahaan', 'no_cctv', 'nama_cctv', 'status', 'kondisi', 'coverage_lokasi', 'coverage_detail_lokasi', 'kategori_area_tercapture', 'lokasi_pemasangan'];
            $orderColumnName = $columns[$orderColumn] ?? 'no_cctv';

            // Base query
            $query = CctvData::query();

            // Filter by company
            if ($company !== '__all__') {
                if (strcasecmp($company, 'Tidak Diketahui') === 0) {
                    $query->where(function ($q) {
                        $q->whereNull('perusahaan')
                          ->orWhere('perusahaan', '');
                    });
                } else {
                    $query->whereRaw('TRIM(perusahaan) = ?', [$company]);
                }
            }

            // Filter by site
            if ($site !== '__all__') {
                if (strcasecmp($site, 'Tidak Diketahui') === 0) {
                    $query->where(function ($q) {
                        $q->whereNull('site')
                          ->orWhere('site', '');
                    });
                } else {
                    $query->whereRaw('TRIM(site) = ?', [$site]);
                }
            }

            // Get total records before search
            $recordsTotal = $query->count();

            // Search functionality
            if (!empty($searchValue)) {
                $query->where(function($q) use ($searchValue) {
                    $q->where('site', 'like', '%' . $searchValue . '%')
                      ->orWhere('perusahaan', 'like', '%' . $searchValue . '%')
                      ->orWhere('no_cctv', 'like', '%' . $searchValue . '%')
                      ->orWhere('nama_cctv', 'like', '%' . $searchValue . '%')
                      ->orWhere('status', 'like', '%' . $searchValue . '%')
                      ->orWhere('kondisi', 'like', '%' . $searchValue . '%')
                      ->orWhere('coverage_lokasi', 'like', '%' . $searchValue . '%')
                      ->orWhere('coverage_detail_lokasi', 'like', '%' . $searchValue . '%')
                      ->orWhere('kategori_area_tercapture', 'like', '%' . $searchValue . '%')
                      ->orWhere('lokasi_pemasangan', 'like', '%' . $searchValue . '%');
                });
            }

            // Get filtered records count
            $recordsFiltered = $query->count();

            // Order and paginate
            $data = $query->orderBy($orderColumnName, $orderDir)
                         ->skip($start)
                         ->take($length)
                         ->get();

            // Format data for DataTable
            $formattedData = $data->map(function($item, $index) use ($start) {
                $statusBadge = $item->status === 'Live View' ? 'success' : 'secondary';
                $kondisiBadge = $item->kondisi === 'Baik' ? 'success' : 'warning';
                
                return [
                    'DT_RowIndex' => $start + $index + 1,
                    'site' => $item->site ?? '-',
                    'perusahaan' => $item->perusahaan ?? 'Tidak Diketahui',
                    'no_cctv' => $item->no_cctv ?? '-',
                    'nama_cctv' => $item->nama_cctv ?? '-',
                    'status' => '<span class="badge bg-' . $statusBadge . '">' . ($item->status ?? 'N/A') . '</span>',
                    'kondisi' => '<span class="badge bg-' . $kondisiBadge . '">' . ($item->kondisi ?? 'N/A') . '</span>',
                    'coverage_lokasi' => $item->coverage_lokasi ?? '-',
                    'coverage_detail_lokasi' => $item->coverage_detail_lokasi ?? '-',
                    'kategori_area_tercapture' => $item->kategori_area_tercapture ?? '-',
                    'lokasi_pemasangan' => $item->lokasi_pemasangan ?? '-',
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $formattedData
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching company CCTV data: ' . $e->getMessage());
            return response()->json([
                'draw' => intval($request->get('draw', 1)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal mengambil data CCTV.'
            ], 500);
        }
    }

    /**
     * Get company overview for modal
     */
    public function getCompanyOverview()
    {
        try {
            $companies = CctvData::select('perusahaan', DB::raw('COUNT(*) as total'))
                ->whereNotNull('perusahaan')
                ->where('perusahaan', '!=', '')
                ->groupBy('perusahaan')
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            $totalAll = CctvData::count();
            
            $companyOverview = $companies->map(function($company) use ($totalAll) {
                $perusahaan = trim($company->perusahaan);
                $total = $company->total;
                
                $aktif = CctvData::whereRaw('TRIM(perusahaan) = ?', [$perusahaan])
                    ->where(function($q) {
                        $q->where('status', 'Live View')
                          ->orWhere('kondisi', 'Baik');
                    })
                    ->count();
                
                $off = $total - $aktif;
                $percentage = $totalAll > 0 ? round(($total / $totalAll) * 100, 1) : 0;
                
                return [
                    'perusahaan' => $perusahaan,
                    'total' => $total,
                    'aktif' => $aktif,
                    'off' => $off,
                    'percentage' => $percentage,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $companyOverview,
                'totalAll' => $totalAll,
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching company overview: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => [],
                'totalAll' => 0,
            ], 500);
        }
    }

    /**
     * Get CCTV statistics for charts based on filters
     */
    public function getCctvChartStats(Request $request)
    {
        try {
            $company = trim($request->query('company', '__all__'));
            $site = trim($request->query('site', '__all__'));
            
            $query = CctvData::query();
            
            // Filter by company
            if ($company !== '__all__') {
                if (strcasecmp($company, 'Tidak Diketahui') === 0) {
                    $query->where(function ($q) {
                        $q->whereNull('perusahaan')
                          ->orWhere('perusahaan', '');
                    });
                } else {
                    $query->whereRaw('TRIM(perusahaan) = ?', [$company]);
                }
            }
            
            // Filter by site
            if ($site !== '__all__') {
                if (strcasecmp($site, 'Tidak Diketahui') === 0) {
                    $query->where(function ($q) {
                        $q->whereNull('site')
                          ->orWhere('site', '');
                    });
                } else {
                    $query->whereRaw('TRIM(site) = ?', [$site]);
                }
            }
            
            $total = $query->count();
            
            // KPI Summary
            $cctvAktif = (clone $query)->where(function($q) {
                $q->where('status', 'Live View')
                  ->where(function($q2) {
                      $q2->where('connected', 'like', '%yes%')
                         ->orWhere('connected', 'like', '%ya%');
                  });
            })->count();
            
            $cctvKondisiBaik = (clone $query)->where('kondisi', 'Baik')->count();
            
            // CCTV dengan kondisi tidak baik (selain Baik, termasuk null dan kosong)
            $cctvKondisiTidakBaik = (clone $query)->where(function($q) {
                $q->where('kondisi', '!=', 'Baik')
                  ->orWhereNull('kondisi')
                  ->orWhere('kondisi', '');
            })->count();
            
            $cctvAutoAlert = (clone $query)->where(function($q) {
                $q->where('fitur_auto_alert', 'like', '%yes%')
                  ->orWhere('fitur_auto_alert', 'like', '%ya%')
                  ->orWhere('fitur_auto_alert', 'like', '%aktif%');
            })->count();
            
            $jumlahSite = (clone $query)->whereNotNull('site')
                ->where('site', '!=', '')
                ->distinct('site')
                ->count('site');
            
            $jumlahPerusahaan = (clone $query)->whereNotNull('perusahaan')
                ->where('perusahaan', '!=', '')
                ->distinct('perusahaan')
                ->count('perusahaan');
            
            // Status breakdown for pie chart
            $statusBreakdown = (clone $query)->select('status', DB::raw('COUNT(*) as count'))
                ->whereNotNull('status')
                ->where('status', '!=', '')
                ->groupBy('status')
                ->get()
                ->map(function($item) {
                    return [
                        'label' => $item->status,
                        'value' => $item->count
                    ];
                });
            
            // Kondisi breakdown for pie chart
            $kondisiBreakdown = (clone $query)->select('kondisi', DB::raw('COUNT(*) as count'))
                ->whereNotNull('kondisi')
                ->where('kondisi', '!=', '')
                ->groupBy('kondisi')
                ->get()
                ->map(function($item) {
                    return [
                        'label' => $item->kondisi,
                        'value' => $item->count
                    ];
                });
            
            // Kategori CCTV breakdown
            $kategoriCctvBreakdown = (clone $query)->select('kategori', DB::raw('COUNT(*) as count'))
                ->whereNotNull('kategori')
                ->where('kategori', '!=', '')
                ->groupBy('kategori')
                ->get()
                ->map(function($item) {
                    return [
                        'label' => $item->kategori ?: 'Tidak Diketahui',
                        'value' => $item->count
                    ];
                });
            
            // Kategori Area Tercapture breakdown
            $kategoriAreaBreakdown = (clone $query)->select('kategori_area_tercapture', DB::raw('COUNT(*) as count'))
                ->whereNotNull('kategori_area_tercapture')
                ->where('kategori_area_tercapture', '!=', '')
                ->groupBy('kategori_area_tercapture')
                ->get()
                ->map(function($item) {
                    return [
                        'label' => $item->kategori_area_tercapture ?: 'Tidak Diketahui',
                        'value' => $item->count
                    ];
                });
            
            // Kategori Aktivitas Tercapture breakdown
            $kategoriAktivitasBreakdown = (clone $query)->select('kategori_aktivitas_tercapture', DB::raw('COUNT(*) as count'))
                ->whereNotNull('kategori_aktivitas_tercapture')
                ->where('kategori_aktivitas_tercapture', '!=', '')
                ->groupBy('kategori_aktivitas_tercapture')
                ->get()
                ->map(function($item) {
                    return [
                        'label' => $item->kategori_aktivitas_tercapture ?: 'Tidak Diketahui',
                        'value' => $item->count
                    ];
                });
            
            // Distribution by site for bar chart
            $distributionBySite = (clone $query)->select('site', DB::raw('COUNT(*) as count'))
                ->whereNotNull('site')
                ->where('site', '!=', '')
                ->groupBy('site')
                ->orderByDesc('count')
                ->get()
                ->map(function($item) {
                    return [
                        'label' => $item->site,
                        'value' => $item->count
                    ];
                });
            
            // Distribution by company for bar chart
            $distributionByCompany = (clone $query)->select('perusahaan', DB::raw('COUNT(*) as count'))
                ->whereNotNull('perusahaan')
                ->where('perusahaan', '!=', '')
                ->groupBy('perusahaan')
                ->orderByDesc('count')
                ->get()
                ->map(function($item) {
                    return [
                        'label' => $item->perusahaan,
                        'value' => $item->count
                    ];
                });
            
            // Tipe CCTV breakdown
            $tipeCctvBreakdown = (clone $query)->select('tipe_cctv', DB::raw('COUNT(*) as count'))
                ->whereNotNull('tipe_cctv')
                ->where('tipe_cctv', '!=', '')
                ->groupBy('tipe_cctv')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(function($item) {
                    return [
                        'label' => $item->tipe_cctv ?: 'Tidak Diketahui',
                        'value' => $item->count
                    ];
                });
            
            // Jenis Instalasi breakdown
            $jenisInstalasiBreakdown = (clone $query)->select('bentuk_instalasi_cctv', DB::raw('COUNT(*) as count'))
                ->whereNotNull('bentuk_instalasi_cctv')
                ->where('bentuk_instalasi_cctv', '!=', '')
                ->groupBy('bentuk_instalasi_cctv')
                ->orderByDesc('count')
                ->get()
                ->map(function($item) {
                    return [
                        'label' => $item->bentuk_instalasi_cctv ?: 'Tidak Diketahui',
                        'value' => $item->count
                    ];
                });
            
            // Time series - Perkembangan CCTV per Bulan/Tahun
            $timeSeriesData = (clone $query)->select(
                    DB::raw('COALESCE(tahun_update, YEAR(NOW())) as tahun'),
                    DB::raw('COALESCE(bulan_update, MONTH(NOW())) as bulan'),
                    DB::raw('COUNT(*) as count')
                )
                ->whereNotNull('tahun_update')
                ->whereNotNull('bulan_update')
                ->groupBy('tahun_update', 'bulan_update')
                ->orderBy('tahun_update')
                ->orderBy('bulan_update')
                ->get()
                ->map(function($item) {
                    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                    return [
                        'label' => $monthNames[($item->bulan - 1) % 12] . ' ' . $item->tahun,
                        'value' => $item->count,
                        'tahun' => $item->tahun,
                        'bulan' => $item->bulan
                    ];
                });
            
            // Aktif vs Non Aktif
            $aktif = (clone $query)->where(function($q) {
                $q->where('status', 'Live View')
                  ->orWhere('kondisi', 'Baik');
            })->count();
            
            $nonAktif = $total - $aktif;
            
            // Area Kritis - Statistik berdasarkan coverage_lokasi
            // Ambil semua coverage_lokasi yang unik beserta jumlah CCTV dan status kritis/non kritis
            $detailCoverageLokasi = (clone $query)->select('coverage_lokasi', DB::raw('COUNT(*) as jumlah_cctv'))
                ->whereNotNull('coverage_lokasi')
                ->where('coverage_lokasi', '!=', '')
                ->groupBy('coverage_lokasi')
                ->orderByDesc('jumlah_cctv')
                ->get()
                ->map(function($item) use ($query) {
                    $coverageLokasi = $item->coverage_lokasi;
                    
                    // Cek apakah lokasi ini termasuk kritis atau non kritis
                    // Berdasarkan kategori_area_tercapture dari semua CCTV di lokasi tersebut
                    // Jika ADA SATU CCTV yang memiliki kategori_area_tercapture mengandung "kritis" atau "critical",
                    // maka lokasi dianggap kritis
                    // Jika TIDAK ADA CCTV yang kritis, maka lokasi non-kritis
                    
                    // Buat query baru dengan filter yang sama seperti query utama
                    $baseQuery = CctvData::query();
                    
                    // Terapkan filter company jika ada
                    $company = request()->query('company', '__all__');
                    if ($company !== '__all__') {
                        if (strcasecmp($company, 'Tidak Diketahui') === 0) {
                            $baseQuery->where(function ($q) {
                                $q->whereNull('perusahaan')
                                  ->orWhere('perusahaan', '');
                            });
                        } else {
                            $baseQuery->whereRaw('TRIM(perusahaan) = ?', [$company]);
                        }
                    }
                    
                    // Terapkan filter site jika ada
                    $site = request()->query('site', '__all__');
                    if ($site !== '__all__') {
                        if (strcasecmp($site, 'Tidak Diketahui') === 0) {
                            $baseQuery->where(function ($q) {
                                $q->whereNull('site')
                                  ->orWhere('site', '');
                            });
                        } else {
                            $baseQuery->whereRaw('TRIM(site) = ?', [$site]);
                        }
                    }
                    
                    // Cek apakah ada CCTV di lokasi ini yang memiliki kategori_area_tercapture = "Area Kritis"
                    // kategori_area_tercapture hanya ada 2 nilai: "Area Non Kritis" dan "Area Kritis"
                    $isKritis = $baseQuery->where('coverage_lokasi', $coverageLokasi)
                        ->where('kategori_area_tercapture', 'Area Kritis')
                        ->exists();
                    
                    return [
                        'nama_lokasi' => $coverageLokasi,
                        'jumlah_cctv' => $item->jumlah_cctv,
                        'is_kritis' => $isKritis
                    ];
                });
            
            // Hitung statistik berdasarkan coverage_lokasi
            $jumlahAreaKritis = $detailCoverageLokasi->where('is_kritis', true)->count();
            $jumlahAreaNonKritis = $detailCoverageLokasi->where('is_kritis', false)->count();
            
            // CCTV yang mengcover Area Kritis (berdasarkan coverage_lokasi yang kritis)
            $cctvAreaKritis = $detailCoverageLokasi->where('is_kritis', true)->sum('jumlah_cctv');
            
            // CCTV yang mengcover Area Non Kritis (berdasarkan coverage_lokasi yang non kritis)
            $cctvAreaNonKritis = $detailCoverageLokasi->where('is_kritis', false)->sum('jumlah_cctv');
            
            // Detail area kritis yang tercover (hanya yang kritis)
            $detailAreaKritis = $detailCoverageLokasi->where('is_kritis', true)
                ->map(function($item) {
                    return [
                        'nama_area' => $item['nama_lokasi'],
                        'jumlah_cctv' => $item['jumlah_cctv'],
                        'is_kritis' => true
                    ];
                })
                ->values();
            
            // Area Kritis (untuk backward compatibility - termasuk coverage_lokasi)
            // kategori_area_tercapture hanya ada 2 nilai: "Area Non Kritis" dan "Area Kritis"
            $areaKritis = (clone $query)->where(function($q) {
                $q->where('kategori_area_tercapture', 'Area Kritis')
                  ->orWhere('coverage_lokasi', 'like', '%kritis%')
                  ->orWhere('coverage_lokasi', 'like', '%critical%');
            })->count();
            
            // Issues/Alerts
            $notConnected = (clone $query)->where(function($q) {
                $q->where('connected', 'like', '%no%')
                  ->orWhere('connected', 'like', '%tidak%')
                  ->orWhereNull('connected')
                  ->orWhere('connected', '');
            })->count();
            
            $notMirrored = (clone $query)->where(function($q) {
                $q->where('mirrored', 'like', '%no%')
                  ->orWhere('mirrored', 'like', '%tidak%')
                  ->orWhereNull('mirrored')
                  ->orWhere('mirrored', '');
            })->count();
            
            // CCTV di area kritis tanpa auto alert
            // kategori_area_tercapture hanya ada 2 nilai: "Area Non Kritis" dan "Area Kritis"
            $criticalWithoutAutoAlert = (clone $query)->where('kategori_area_tercapture', 'Area Kritis')
                ->where(function($q) {
                    $q->where('fitur_auto_alert', 'like', '%no%')
                      ->orWhere('fitur_auto_alert', 'like', '%tidak%')
                      ->orWhereNull('fitur_auto_alert')
                      ->orWhere('fitur_auto_alert', '');
                })->count();
            
            // CCTV belum diverifikasi 3 bulan terakhir
            $threeMonthsAgo = now()->subMonths(3);
            $notVerified = (clone $query)->where(function($q) use ($threeMonthsAgo) {
                $q->whereNull('verifikasi_by_petugas_ocr')
                  ->orWhere('verifikasi_by_petugas_ocr', '')
                  ->orWhere(function($q2) use ($threeMonthsAgo) {
                      $q2->where('tahun_update', '<', $threeMonthsAgo->year)
                         ->orWhere(function($q3) use ($threeMonthsAgo) {
                             $q3->where('tahun_update', '=', $threeMonthsAgo->year)
                                ->where('bulan_update', '<', $threeMonthsAgo->month);
                         });
                  });
            })->count();
            
            return response()->json([
                'success' => true,
                'total' => $total,
                'cctvAktif' => $cctvAktif,
                'cctvKondisiBaik' => $cctvKondisiBaik,
                'cctvKondisiTidakBaik' => $cctvKondisiTidakBaik,
                'cctvAutoAlert' => $cctvAutoAlert,
                'jumlahSite' => $jumlahSite,
                'jumlahPerusahaan' => $jumlahPerusahaan,
                'jumlahAreaKritis' => $jumlahAreaKritis,
                'jumlahAreaNonKritis' => $jumlahAreaNonKritis,
                'cctvAreaKritis' => $cctvAreaKritis,
                'cctvAreaNonKritis' => $cctvAreaNonKritis,
                'detailAreaKritis' => $detailAreaKritis,
                'detailCoverageLokasi' => $detailCoverageLokasi,
                'aktif' => $aktif,
                'nonAktif' => $nonAktif,
                'areaKritis' => $areaKritis,
                'statusBreakdown' => $statusBreakdown,
                'kondisiBreakdown' => $kondisiBreakdown,
                'kategoriCctvBreakdown' => $kategoriCctvBreakdown,
                'kategoriAreaBreakdown' => $kategoriAreaBreakdown,
                'kategoriAktivitasBreakdown' => $kategoriAktivitasBreakdown,
                'distributionBySite' => $distributionBySite,
                'distributionByCompany' => $distributionByCompany,
                'tipeCctvBreakdown' => $tipeCctvBreakdown,
                'jenisInstalasiBreakdown' => $jenisInstalasiBreakdown,
                'timeSeriesData' => $timeSeriesData,
                'issues' => [
                    'notConnected' => $notConnected,
                    'notMirrored' => $notMirrored,
                    'criticalWithoutAutoAlert' => $criticalWithoutAutoAlert,
                    'notVerified' => $notVerified,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching CCTV chart stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'total' => 0,
                'cctvAktif' => 0,
                'cctvKondisiBaik' => 0,
                'cctvKondisiTidakBaik' => 0,
                'cctvAutoAlert' => 0,
                'jumlahSite' => 0,
                'jumlahPerusahaan' => 0,
                'jumlahAreaKritis' => 0,
                'jumlahAreaNonKritis' => 0,
                'cctvAreaKritis' => 0,
                'cctvAreaNonKritis' => 0,
                'detailAreaKritis' => [],
                'detailCoverageLokasi' => [],
                'aktif' => 0,
                'nonAktif' => 0,
                'areaKritis' => 0,
                'statusBreakdown' => [],
                'kondisiBreakdown' => [],
                'kategoriCctvBreakdown' => [],
                'kategoriAreaBreakdown' => [],
                'kategoriAktivitasBreakdown' => [],
                'distributionBySite' => [],
                'distributionByCompany' => [],
                'tipeCctvBreakdown' => [],
                'jenisInstalasiBreakdown' => [],
                'timeSeriesData' => [],
                'issues' => [
                    'notConnected' => 0,
                    'notMirrored' => 0,
                    'criticalWithoutAutoAlert' => 0,
                    'notVerified' => 0,
                ],
            ], 500);
        }
    }

    /**
     * Get sites list for filter
     */
    public function getSitesList()
    {
        try {
            $sites = CctvData::select('site')
                ->whereNotNull('site')
                ->where('site', '!=', '')
                ->distinct()
                ->orderBy('site')
                ->pluck('site')
                ->map(function($site) {
                    return trim($site);
                })
                ->filter()
                ->values();

            return response()->json([
                'success' => true,
                'data' => $sites,
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching sites list: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => [],
            ], 500);
        }
    }

    /**
     * Check for new APD detections
     */
    public function checkNewApdDetections(Request $request)
    {
        try {
            $lastCheckTime = $request->get('last_check_time');
            $testMode = $request->get('test', false); // Mode test untuk debugging
            
            // Cek apakah tabel exists
            $tableExists = DB::getSchemaBuilder()->hasTable('no_apd_detections');
            
            if (!$tableExists) {
                Log::warning('Table no_apd_detections does not exist');
                // Return test data jika mode test
                if ($testMode) {
                    return response()->json([
                        'success' => true,
                        'has_new' => true,
                        'count' => 1,
                        'data' => [
                            (object)[
                                'id' => 999,
                                'cctv_name' => 'CCTV Test',
                                'created_at' => now()->toDateTimeString(),
                            ]
                        ],
                        'last_check_time' => now()->toDateTimeString(),
                        'test_mode' => true,
                    ]);
                }
                
                return response()->json([
                    'success' => true,
                    'has_new' => false,
                    'count' => 0,
                    'data' => [],
                    'last_check_time' => now()->toDateTimeString(),
                    'message' => 'Table no_apd_detections does not exist',
                ]);
            }
            
            // Query untuk mendapatkan data baru dari tabel no_apd_detections
            $query = DB::table('no_apd_detections');
            
            if ($lastCheckTime) {
                // Hanya gunakan created_at karena tabel tidak memiliki updated_at
                $query->where('created_at', '>', $lastCheckTime);
            }
            
            $newDetections = $query->orderBy('created_at', 'desc')
                                  ->limit(10)
                                  ->get();
            
            // Log untuk debugging
            Log::info('APD Detection Check', [
                'last_check_time' => $lastCheckTime,
                'found_count' => $newDetections->count(),
                'has_new' => $newDetections->count() > 0,
                'table_exists' => $tableExists,
            ]);
            
            return response()->json([
                'success' => true,
                'has_new' => $newDetections->count() > 0,
                'count' => $newDetections->count(),
                'data' => $newDetections,
                'last_check_time' => now()->toDateTimeString(),
            ]);
        } catch (Exception $e) {
            Log::error('Error checking new APD detections: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'has_new' => false,
                'count' => 0,
                'data' => [],
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tasklist detail from PostgreSQL car_register table
     */
    public function getTasklistDetail(Request $request)
    {
        try {
            $tasklistId = $request->get('tasklist_id');
            
            if (!$tasklistId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tasklist ID is required'
                ], 400);
            }

            // Check if SSH tunnel is active
            if (!$this->isTunnelActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'SSH tunnel is not active',
                    'data' => null
                ]);
            }

            // Query untuk mengambil detail tasklist dari PostgreSQL
            $query = "
                SELECT 
                    cr.id,
                    cr.deskripsi,
                    cr.lokasi_detail,
                    cr.kekerapan,
                    cr.keparahan,
                    cr.nilai_resiko,
                    cr.create_date AS tanggal_pembuatan,
                    cr.location_latitude AS latitude,
                    cr.location_longitude AS longitude,
                    loc_d.nama AS nama_detail_lokasi,
                    loc.nama AS nama_lokasi,
                    site.nama AS nama_site,
                    mo.nama AS ketidaksesuaian,
                    od.nama AS subketidaksesuaian,
                    st.nama AS status,
                    req.nama AS nama_pelapor,
                    pic.nama AS nama_pic,
                    m_goldenrule.nama AS nama_goldenrule,
                    m_kategori_tipe.nama AS nama_kategori,
                    car_tindakan.tanggal_aktual_penyelesaian,
                    tob.name AS name_tools_observation
                FROM bcbeats.car_register cr
                    LEFT JOIN bcbeats.m_lokasi loc_d ON loc_d.id = cr.id_lokasi
                    LEFT JOIN bcbeats.m_lokasi loc ON loc.id = loc_d.id_parent
                    LEFT JOIN bcbeats.m_lokasi site ON site.id = loc.id_parent
                    LEFT JOIN bcbeats.m_lookup tob ON tob.id = cr.id_tools_observation
                    LEFT JOIN bcbeats.m_obyek_detil od ON od.id = cr.id_obyek_detil
                    LEFT JOIN bcbeats.m_obyek mo ON mo.id = cr.id_obyek
                    LEFT JOIN bcbeats.m_status st ON st.id = cr.id_status
                    LEFT JOIN bcsid.m_karyawan req ON req.id = cr.id_pelapor
                    LEFT JOIN bcsid.m_karyawan pic ON pic.id = cr.id_pic
                    LEFT JOIN bcbeats.m_goldenrule ON m_goldenrule.id = cr.id_goldenrule
                    LEFT JOIN bcbeats.m_kategori_tipe ON m_kategori_tipe.id = cr.id_kategori
                    LEFT JOIN bcbeats.car_tindakan ON car_tindakan.id_car_register = cr.id
                WHERE cr.id = ?
                LIMIT 1
            ";

            $results = DB::connection('pgsql_ssh')->select($query, [$tasklistId]);

            if (empty($results)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tasklist not found',
                    'data' => null
                ], 404);
            }

            $row = $results[0];

            // Map data ke format yang digunakan
            $severityMap = [
                'Sangat Tinggi' => 'critical',
                'Tinggi' => 'high',
                'Sedang' => 'medium',
                'Rendah' => 'low',
            ];
            $severity = $severityMap[$row->keparahan ?? 'Sedang'] ?? 'medium';

            $statusMap = [
                'Open' => 'active',
                'Closed' => 'resolved',
                'In Progress' => 'active',
                'Resolved' => 'resolved',
            ];
            $status = $statusMap[$row->status ?? 'Open'] ?? 'active';

            $detectedAt = $row->tanggal_pembuatan 
                ? date('Y-m-d H:i:s', strtotime($row->tanggal_pembuatan))
                : now()->format('Y-m-d H:i:s');

            $resolvedAt = null;
            if ($row->tanggal_aktual_penyelesaian) {
                $resolvedAt = date('Y-m-d H:i:s', strtotime($row->tanggal_aktual_penyelesaian));
            }

            $tasklistDetail = [
                'id' => $row->id,
                'type' => $row->ketidaksesuaian ?? $row->subketidaksesuaian ?? 'Hazard Detection',
                'severity' => $severity,
                'keparahan' => $row->keparahan ?? null,
                'kekerapan' => $row->kekerapan ?? null,
                'nilai_resiko' => $row->nilai_resiko ?? null,
                'status' => $status,
                'status_name' => $row->status ?? 'Open',
                'location' => [
                    'lat' => $row->latitude ? (float) $row->latitude : null,
                    'lng' => $row->longitude ? (float) $row->longitude : null,
                ],
                'detected_at' => $detectedAt,
                'resolved_at' => $resolvedAt,
                'description' => $row->deskripsi ?? $row->ketidaksesuaian ?? 'No description',
                'cctv_id' => $row->name_tools_observation ?? 'N/A',
                'personnel_name' => $row->nama_pelapor ?? null,
                'equipment_id' => null,
                'zone' => $row->nama_lokasi ?? $row->nama_detail_lokasi ?? $row->nama_site ?? 'Unknown',
                'site' => $row->nama_site ?? null,
                'lokasi_detail' => $row->lokasi_detail ?? null,
                'nama_detail_lokasi' => $row->nama_detail_lokasi ?? null,
                'nama_lokasi' => $row->nama_lokasi ?? null,
                'nama_pelapor' => $row->nama_pelapor ?? null,
                'nama_pic' => $row->nama_pic ?? null,
                'nama_goldenrule' => $row->nama_goldenrule ?? null,
                'nama_kategori' => $row->nama_kategori ?? null,
                'ketidaksesuaian' => $row->ketidaksesuaian ?? null,
                'subketidaksesuaian' => $row->subketidaksesuaian ?? null,
                'url_photo' => 'https://hseautomation.beraucoal.co.id/report/photoCar/' . $row->id,
                'tanggal_pembuatan' => $row->tanggal_pembuatan ?? null,
                'original_id' => $row->id,
            ];

            return response()->json([
                'success' => true,
                'data' => $tasklistDetail
            ]);

        } catch (Exception $e) {
            \Log::error('Error fetching tasklist detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching tasklist detail: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get total CCTV count based on company and site filters
     */
    public function getTotalCctvCount(Request $request)
    {
        try {
            $company = trim($request->query('company', '__all__'));
            $site = trim($request->query('site', '__all__'));
            
            $query = CctvData::query();
            
            // Filter by company
            if ($company !== '__all__') {
                if (strcasecmp($company, 'Tidak Diketahui') === 0) {
                    $query->where(function ($q) {
                        $q->whereNull('perusahaan')
                          ->orWhere('perusahaan', '');
                    });
                } else {
                    $query->whereRaw('TRIM(perusahaan) = ?', [$company]);
                }
            }
            
            // Filter by site
            if ($site !== '__all__') {
                if (strcasecmp($site, 'Tidak Diketahui') === 0) {
                    $query->where(function ($q) {
                        $q->whereNull('site')
                          ->orWhere('site', '');
                    });
                } else {
                    $query->whereRaw('TRIM(site) = ?', [$site]);
                }
            }
            
            $total = $query->count();
            
            return response()->json([
                'success' => true,
                'total' => $total,
                'formatted' => number_format($total)
            ]);
        } catch (Exception $e) {
            \Log::error('Error fetching total CCTV count: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching total CCTV count: ' . $e->getMessage(),
                'total' => 0,
                'formatted' => '0'
            ], 500);
        }
    }

    /**
     * Get TBC (To Be Concerned) overview data
     * Menampilkan statistik dan data TBC dari hazard_validations dengan detail dari PostgreSQL
     */
    public function getTbcOverview(Request $request)
    {
        try {
            // Ambil semua TBC valid dari hazard_validations
            $tbcValidations = HazardValidation::where('tobe_concerned_hazard', 'Valid')
                ->orderByDesc('created_at')
                ->get();

            $tbcCount = $tbcValidations->count();
            
            // Ambil tasklist dari TBC valid
            $tasklists = $tbcValidations->pluck('tasklist')->filter()->unique()->toArray();

            // Jika tidak ada tasklist, return data kosong
            if (empty($tasklists)) {
                return response()->json([
                    'success' => true,
                    'total_tbc' => 0,
                    'statistics' => [],
                    'by_company' => [],
                    'by_site' => [],
                    'by_status' => [],
                    'data' => []
                ]);
            }

            // Check if SSH tunnel is active
            if (!$this->isTunnelActive()) {
                \Log::warning('SSH tunnel is not active. Returning TBC overview without PostgreSQL details.');
                return response()->json([
                    'success' => true,
                    'total_tbc' => $tbcCount,
                    'statistics' => [
                        'total' => $tbcCount,
                        'this_year' => HazardValidation::where('tobe_concerned_hazard', 'Valid')
                            ->whereYear('created_at', now()->year)
                            ->count(),
                        'last_year' => HazardValidation::where('tobe_concerned_hazard', 'Valid')
                            ->whereYear('created_at', now()->year - 1)
                            ->count(),
                    ],
                    'by_company' => [],
                    'by_site' => [],
                    'by_status' => [],
                    'data' => []
                ]);
            }

            // Query PostgreSQL untuk mengambil detail tasklist
            $placeholders = implode(',', array_fill(0, count($tasklists), '?'));
            
            $query = "
                SELECT 
                    cr.id,
                    cr.deskripsi,
                    cr.lokasi_detail,
                    cr.kekerapan,
                    cr.keparahan,
                    cr.nilai_resiko,
                    cr.create_date AS tanggal_pembuatan,
                    cr.location_latitude AS latitude,
                    cr.location_longitude AS longitude,
                    loc_d.nama AS nama_detail_lokasi,
                    loc.nama AS nama_lokasi,
                    site.nama AS nama_site,
                    mo.nama AS ketidaksesuaian,
                    od.nama AS subketidaksesuaian,
                    st.nama AS status,
                    req.nama AS nama_pelapor,
                    pic.nama AS nama_pic,
                    m_goldenrule.nama AS nama_goldenrule,
                    m_kategori_tipe.nama AS nama_kategori,
                    car_tindakan.tanggal_aktual_penyelesaian,
                    tob.name AS name_tools_observation
                FROM bcbeats.car_register cr
                    LEFT JOIN bcbeats.m_lokasi loc_d ON loc_d.id = cr.id_lokasi
                    LEFT JOIN bcbeats.m_lokasi loc ON loc.id = loc_d.id_parent
                    LEFT JOIN bcbeats.m_lokasi site ON site.id = loc.id_parent
                    LEFT JOIN bcbeats.m_lookup tob ON tob.id = cr.id_tools_observation
                    LEFT JOIN bcbeats.m_obyek_detil od ON od.id = cr.id_obyek_detil
                    LEFT JOIN bcbeats.m_obyek mo ON mo.id = cr.id_obyek
                    LEFT JOIN bcbeats.m_status st ON st.id = cr.id_status
                    LEFT JOIN bcsid.m_karyawan req ON req.id = cr.id_pelapor
                    LEFT JOIN bcsid.m_karyawan pic ON pic.id = cr.id_pic
                    LEFT JOIN bcbeats.m_goldenrule ON m_goldenrule.id = cr.id_goldenrule
                    LEFT JOIN bcbeats.m_kategori_tipe ON m_kategori_tipe.id = cr.id_kategori
                    LEFT JOIN bcbeats.car_tindakan ON car_tindakan.id_car_register = cr.id
                WHERE cr.id_sumberdata <> 200 
                    AND cr.id::text IN ($placeholders)
                ORDER BY cr.create_date DESC
            ";

            $results = DB::connection('pgsql_ssh')->select($query, $tasklists);

            // Map data dari PostgreSQL
            $tbcData = array_map(function ($row) {
                $severityMap = [
                    'Sangat Tinggi' => 'critical',
                    'Tinggi' => 'high',
                    'Sedang' => 'medium',
                    'Rendah' => 'low',
                ];
                $severity = $severityMap[$row->keparahan ?? 'Sedang'] ?? 'medium';

                $statusMap = [
                    'Open' => 'active',
                    'Closed' => 'resolved',
                    'In Progress' => 'active',
                    'Resolved' => 'resolved',
                ];
                $status = $statusMap[$row->status ?? 'Open'] ?? 'active';

                return [
                    'id' => $row->id,
                    'tasklist' => (string) $row->id,
                    'deskripsi' => $row->deskripsi ?? null,
                    'lokasi_detail' => $row->lokasi_detail ?? null,
                    'kekerapan' => $row->kekerapan ?? null,
                    'keparahan' => $row->keparahan ?? null,
                    'nilai_resiko' => $row->nilai_resiko ?? null,
                    'tanggal_pembuatan' => $row->tanggal_pembuatan ? date('Y-m-d H:i:s', strtotime($row->tanggal_pembuatan)) : null,
                    'latitude' => $row->latitude ? (float) $row->latitude : null,
                    'longitude' => $row->longitude ? (float) $row->longitude : null,
                    'nama_detail_lokasi' => $row->nama_detail_lokasi ?? null,
                    'nama_lokasi' => $row->nama_lokasi ?? null,
                    'nama_site' => $row->nama_site ?? null,
                    'ketidaksesuaian' => $row->ketidaksesuaian ?? null,
                    'subketidaksesuaian' => $row->subketidaksesuaian ?? null,
                    'status' => $status,
                    'status_name' => $row->status ?? 'Open',
                    'nama_pelapor' => $row->nama_pelapor ?? null,
                    'nama_pic' => $row->nama_pic ?? null,
                    'nama_goldenrule' => $row->nama_goldenrule ?? null,
                    'nama_kategori' => $row->nama_kategori ?? null,
                    'tanggal_aktual_penyelesaian' => $row->tanggal_aktual_penyelesaian ? date('Y-m-d H:i:s', strtotime($row->tanggal_aktual_penyelesaian)) : null,
                    'name_tools_observation' => $row->name_tools_observation ?? null,
                    'severity' => $severity,
                ];
            }, $results);

            // Statistik berdasarkan perusahaan (menggunakan nama_site sebagai perusahaan)
            $byCompany = [];
            foreach ($tbcData as $item) {
                // Gunakan nama_site sebagai perusahaan
                $company = $item['nama_site'] ?? 'Tidak Diketahui';
                if (empty($company) || trim($company) === '') {
                    $company = 'Tidak Diketahui';
                }
                if (!isset($byCompany[$company])) {
                    $byCompany[$company] = 0;
                }
                $byCompany[$company]++;
            }
            arsort($byCompany);
            $byCompanyArray = array_map(function ($company, $count) {
                return ['company' => $company, 'count' => $count];
            }, array_keys($byCompany), $byCompany);

            // Statistik berdasarkan site
            $bySite = [];
            foreach ($tbcData as $item) {
                $site = $item['nama_site'] ?? 'Tidak Diketahui';
                if (!isset($bySite[$site])) {
                    $bySite[$site] = 0;
                }
                $bySite[$site]++;
            }
            arsort($bySite);
            $bySiteArray = array_map(function ($site, $count) {
                return ['site' => $site, 'count' => $count];
            }, array_keys($bySite), $bySite);

            // Statistik berdasarkan status
            $byStatus = [];
            foreach ($tbcData as $item) {
                $status = $item['status_name'] ?? 'Unknown';
                if (!isset($byStatus[$status])) {
                    $byStatus[$status] = 0;
                }
                $byStatus[$status]++;
            }
            arsort($byStatus);
            $byStatusArray = array_map(function ($status, $count) {
                return ['status' => $status, 'count' => $count];
            }, array_keys($byStatus), $byStatus);

            // Statistik umum
            $currentYear = now()->year;
            $lastYear = $currentYear - 1;
            
            $statistics = [
                'total' => $tbcCount,
                'this_year' => HazardValidation::where('tobe_concerned_hazard', 'Valid')
                    ->whereYear('created_at', $currentYear)
                    ->count(),
                'last_year' => HazardValidation::where('tobe_concerned_hazard', 'Valid')
                    ->whereYear('created_at', $lastYear)
                    ->count(),
                'with_postgres_data' => count($tbcData),
                'by_severity' => [
                    'critical' => count(array_filter($tbcData, fn($item) => $item['severity'] === 'critical')),
                    'high' => count(array_filter($tbcData, fn($item) => $item['severity'] === 'high')),
                    'medium' => count(array_filter($tbcData, fn($item) => $item['severity'] === 'medium')),
                    'low' => count(array_filter($tbcData, fn($item) => $item['severity'] === 'low')),
                ],
            ];

            return response()->json([
                'success' => true,
                'total_tbc' => $tbcCount,
                'statistics' => $statistics,
                'by_company' => array_values($byCompanyArray),
                'by_site' => array_values($bySiteArray),
                'by_status' => array_values($byStatusArray),
                'data' => $tbcData
            ]);

        } catch (Exception $e) {
            \Log::error('Error fetching TBC overview: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching TBC overview: ' . $e->getMessage(),
                'total_tbc' => 0,
                'statistics' => [],
                'by_company' => [],
                'by_site' => [],
                'by_status' => [],
                'data' => []
            ], 500);
        }
    }

    /**
     * Get filtered map data based on filters (company, site, layer visibility)
     * API endpoint untuk mendapatkan data yang sudah difilter untuk ditampilkan di map
     */
    /**
     * Get user GPS data from ClickHouse (nitip.v_employee_location)
     */
    public function getUserGps(Request $request)
    {
        try {
            $clickhouse = new ClickHouseService();
            
            if (!$clickhouse->isConnected()) {
                Log::warning('ClickHouse is not connected. Returning empty user GPS data.');
                return response()->json([
                    'success' => false,
                    'error' => 'ClickHouse is not connected',
                    'users' => []
                ], 500);
            }
            
            // Cek apakah user adalah admin
            $user = auth()->user();
            $isAdmin = false;
            $userEmail = null;
            $userKodeSid = null;
            
            if ($user) {
                $userEmail = $user->email;
                
                // Opsi 1: Menggunakan method isAdmin() dari model User (disarankan)
                // Pastikan kolom 'role' sudah ditambahkan di tabel users
                if (method_exists($user, 'isAdmin')) {
                    $isAdmin = $user->isAdmin();
                } else {
                    // Fallback: Cek kolom role langsung
                    $isAdmin = isset($user->role) && ($user->role === 'admin' || $user->role === 'administrator');
                }
                
                // Opsi 2: Jika menggunakan kolom is_admin (boolean)
                // $isAdmin = isset($user->is_admin) && $user->is_admin === true;
                
                // Fallback: Jika kolom role/is_admin belum ada, gunakan pengecekan email
                if (!$isAdmin && !isset($user->role) && !isset($user->is_admin)) {
                    $isAdmin = stripos($userEmail, 'admin') !== false || 
                              $userEmail === 'admin@gmail.com' ||
                              $userEmail === 'administrator@gmail.com';
                }
            }
            
            // Set timeout lebih lama untuk query yang besar (60 detik)
            // Note: timeout diatur di ClickHouseService, tapi kita bisa handle error dengan lebih baik

            // Query untuk mengambil data employee location dari nitip.v_employee_location
            // Menggunakan toString() pada semua field untuk menghindari konflik tipe data
            // Filter latitude/longitude != 0 dilakukan di PHP untuk menghindari konflik tipe
            // Filter hanya data hari ini (berdasarkan date)
            $today = Carbon::now()->format('Y-m-d');
            
            // Jika bukan admin, filter berdasarkan kode_sid atau employee_id user
            $whereClause = "latitude IS NOT NULL 
                    AND longitude IS NOT NULL
                    AND toDate(date) = today()";
            
            // Jika bukan admin, tambahkan filter untuk data user sendiri
            // Asumsi: kita perlu mapping antara user email/name dengan kode_sid atau employee_id
            // Untuk sementara, jika bukan admin, kita akan return empty atau filter di PHP
            // Anda bisa menyesuaikan ini dengan menambahkan kolom user_id atau email di tabel employee
            
            $sql = "
                SELECT 
                    toString(kode_sid) as kode_sid,
                    toString(k.nama) as nama,
                    toString(nama_perusahaan) as nama_perusahaan,
                    toString(id) as id,
                    toString(latitude) as latitude,
                    toString(longitude) as longitude,
                    toString(date) as date,
                    toString(device_info) as device_info,
                    toString(employee_id) as employee_id,
                    toString(location_id) as location_id,
                    toString(checkpoint) as checkpoint,
                    toString(is_onsite) as is_onsite
                FROM nitip.v_employee_location
                WHERE {$whereClause}
                ORDER BY date DESC
                LIMIT 5000
            ";

            // Query dengan retry mechanism untuk handle timeout
            // Coba dengan LIMIT yang lebih kecil jika timeout
            $limit = 5000;
            $maxRetries = 2;
            $results = [];
            
            for ($retry = 0; $retry <= $maxRetries; $retry++) {
                try {
                    $sqlWithLimit = $sql;
                    if ($retry > 0) {
                        // Kurangi LIMIT jika retry
                        $limit = intval($limit / 2);
                        $sqlWithLimit = preg_replace('/LIMIT \d+/', 'LIMIT ' . $limit, $sql);
                        Log::info("Retrying query with LIMIT: $limit");
                    }
                    
                    $results = $clickhouse->query($sqlWithLimit);
                    break; // Success, exit loop
                } catch (Exception $queryException) {
                    $errorMsg = $queryException->getMessage();
                    // Jika timeout dan masih ada retry, coba lagi dengan LIMIT lebih kecil
                    if (($retry < $maxRetries) && 
                        (strpos($errorMsg, 'timeout') !== false || 
                         strpos($errorMsg, 'timed out') !== false ||
                         strpos($errorMsg, 'Operation timed out') !== false)) {
                        Log::warning("Query timeout (attempt " . ($retry + 1) . "), retrying with smaller LIMIT");
                        continue;
                    } else {
                        // Jika bukan timeout atau sudah max retries, throw exception
                        throw $queryException;
                    }
                }
            }

            // Format data untuk frontend
            // Gunakan Map untuk deduplikasi berdasarkan employee_id (ambil yang terbaru)
            $userGpsDataMap = [];
            foreach ($results as $row) {
                // Filter latitude/longitude != 0 di PHP
                // Handle comma as decimal separator (2,263024 -> 2.263024)
                $latitude = isset($row['latitude']) && $row['latitude'] !== '' ? (float)str_replace(',', '.', $row['latitude']) : null;
                $longitude = isset($row['longitude']) && $row['longitude'] !== '' ? (float)str_replace(',', '.', $row['longitude']) : null;
                
                if ($latitude === null || $longitude === null || $latitude == 0 || $longitude == 0) {
                    continue;
                }
                
                // Use employee_id as primary identifier, fallback to id or kode_sid
                $employeeId = $row['employee_id'] ?? $row['id'] ?? $row['kode_sid'] ?? null;
                if (!$employeeId) {
                    continue;
                }
                
                // Map fields from v_employee_location to expected frontend format
                $userData = [
                    'id' => $employeeId,
                    'user_id' => $employeeId,
                    'employee_id' => $row['employee_id'] ?? null,
                    'kode_sid' => $row['kode_sid'] ?? null,
                    'npk' => $row['kode_sid'] ?? null, // Use kode_sid as npk
                    'fullname' => $row['nama'] ?? null,
                    'nama_perusahaan' => $row['nama_perusahaan'] ?? null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'location' => [
                        'lat' => $latitude,
                        'lng' => $longitude
                    ],
                    'date' => $row['date'] ?? null,
                    'gps_updated_at' => $row['date'] ?? null, // Use date as gps_updated_at
                    'gps_created_at' => $row['date'] ?? null,
                    'device_info' => $row['device_info'] ?? null,
                    'location_id' => $row['location_id'] ?? null,
                    'checkpoint' => $row['checkpoint'] ?? null,
                    'is_onsite' => isset($row['is_onsite']) ? (int)$row['is_onsite'] : null
                ];
                
                // Deduplikasi: jika employee_id sudah ada, ambil yang terbaru berdasarkan date
                if (!isset($userGpsDataMap[$employeeId])) {
                    // Employee belum ada, tambahkan
                    $userGpsDataMap[$employeeId] = $userData;
                } else {
                    // Employee sudah ada, bandingkan timestamp dan ambil yang terbaru
                    $existingTime = $userGpsDataMap[$employeeId]['date'] ?? $userGpsDataMap[$employeeId]['gps_updated_at'] ?? '';
                    $currentTime = $userData['date'] ?? $userData['gps_updated_at'] ?? '';
                    
                    if ($currentTime > $existingTime) {
                        // Replace dengan data yang lebih baru
                        $userGpsDataMap[$employeeId] = $userData;
                    }
                }
            }
            
            // Convert map to array
            $userGpsData = array_values($userGpsDataMap);
            
            // Jika bukan admin, filter hanya data user sendiri
            // Filter berdasarkan kode_sid atau employee_id yang sesuai dengan user
            // Catatan: Untuk mapping yang lebih akurat, disarankan menambahkan kolom kode_sid atau employee_id di tabel users
            // Atau membuat tabel mapping antara users.id dengan employee.kode_sid/employee_id
            if (!$isAdmin && $user) {
                $userName = strtolower(trim($user->name ?? ''));
                $userEmail = strtolower(trim($user->email ?? ''));
                $filteredData = [];
                
                foreach ($userGpsData as $userData) {
                    $employeeName = strtolower(trim($userData['fullname'] ?? ''));
                    $employeeKodeSid = trim($userData['kode_sid'] ?? '');
                    $employeeEmail = strtolower(trim($userData['email'] ?? ''));
                    
                    // Match berdasarkan:
                    // 1. Nama (fuzzy match)
                    // 2. Email (exact match)
                    // 3. Kode SID (jika user memiliki kode_sid di profile)
                    $nameMatch = $userName && $employeeName && 
                                (stripos($employeeName, $userName) !== false || 
                                 stripos($userName, $employeeName) !== false ||
                                 $employeeName === $userName);
                    
                    $emailMatch = $userEmail && $employeeEmail && $employeeEmail === $userEmail;
                    
                    // Jika ada kode_sid di user (dari request atau profile), match dengan kode_sid employee
                    $kodeSidMatch = false;
                    if ($request->has('user_kode_sid')) {
                        $userKodeSid = trim($request->input('user_kode_sid'));
                        $kodeSidMatch = $userKodeSid && $employeeKodeSid && $employeeKodeSid === $userKodeSid;
                    }
                    
                    if ($nameMatch || $emailMatch || $kodeSidMatch) {
                        $filteredData[] = $userData;
                    }
                }
                
                $userGpsData = $filteredData;
                
                // Log untuk debugging
                Log::info('GPS data filtered for user', [
                    'user_id' => $user->id,
                    'user_name' => $userName,
                    'user_email' => $userEmail,
                    'filtered_count' => count($filteredData),
                    'total_count' => count($userGpsDataMap)
                ]);
            }
            
            // Deteksi area kerja untuk setiap user menggunakan PostGIS
            // Batch check untuk performa yang lebih baik
            // Cek koneksi PostgreSQL sekali di awal untuk menghindari spam log
            $pgsqlAvailable = false;
            try {
                DB::connection('pgsql')->getPdo();
                $pgsqlAvailable = true;
            } catch (Exception $connException) {
                // PostgreSQL tidak tersedia, skip semua query work area
                Log::warning('PostgreSQL connection not available, skipping work area detection: ' . $connException->getMessage());
            }
            
            if ($pgsqlAvailable) {
                foreach ($userGpsData as &$userData) {
                    if (isset($userData['latitude']) && isset($userData['longitude'])) {
                        try {
                            // Cek apakah koordinat berada di dalam area kerja dari tabel geo_tagging
                            // Transform point ke SRID geometry terlebih dahulu untuk akurasi yang lebih baik
                            $workArea = DB::connection('pgsql')
                                ->table('geo_tagging')
                                ->select('id', 'name', 'location_id', 'buffer', 'type_lookup_id')
                                ->where('is_active', true)
                                ->whereRaw(
                                    'ST_Contains(
                                        geometry, 
                                        ST_Transform(
                                            ST_SetSRID(ST_MakePoint(?, ?), 4326), 
                                            ST_SRID(geometry)
                                        )
                                    )',
                                    [$userData['longitude'], $userData['latitude']]
                                )
                                ->first();

                            if ($workArea) {
                                $userData['work_area_id'] = $workArea->id;
                                $userData['work_area_name'] = $workArea->name;
                                $userData['work_area_location_id'] = $workArea->location_id;
                                $userData['work_area_buffer'] = $workArea->buffer;
                                $userData['work_area_type_lookup_id'] = $workArea->type_lookup_id;
                                $userData['is_in_work_area'] = true;
                            } else {
                                $userData['is_in_work_area'] = false;
                            }
                        } catch (Exception $e) {
                            // Jika error saat query, skip deteksi untuk user ini
                            // Tidak perlu log setiap error karena sudah dicek di awal
                            $userData['is_in_work_area'] = false;
                        }
                    } else {
                        $userData['is_in_work_area'] = false;
                    }
                }
            } else {
                // Jika PostgreSQL tidak tersedia, set semua user is_in_work_area = false
                foreach ($userGpsData as &$userData) {
                    $userData['is_in_work_area'] = false;
                }
            }

            return response()->json([
                'success' => true,
                'users' => $userGpsData,
                'count' => count($userGpsData)
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching user GPS data from ClickHouse: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'users' => []
            ], 500);
        }
    }

    /**
     * Get employee location data from nitip.v_employee_location
     * Returns latest location data for employees with coordinates
     */
    public function getEmployeeLocation(Request $request)
    {
        try {
            $clickhouse = new ClickHouseService();
            
            if (!$clickhouse->isConnected()) {
                Log::warning('ClickHouse is not connected. Returning empty employee location data.');
                return response()->json([
                    'success' => false,
                    'error' => 'ClickHouse is not connected',
                    'employees' => []
                ], 500);
            }
            
            // Get optional filters
            $limit = (int)($request->input('limit', 1000));
            $employeeId = $request->input('employee_id');
            $kodeSid = $request->input('kode_sid');
            $isOnsite = $request->input('is_onsite'); // 1 or 0
            
            // Build WHERE clause - avoid type conflicts by filtering in PHP instead
            // Only check for NOT NULL in SQL, filter != 0 in PHP
            $whereConditions = [
                "latitude IS NOT NULL",
                "longitude IS NOT NULL"
            ];
            
            if ($employeeId) {
                $whereConditions[] = "toString(employee_id) = '" . addslashes($employeeId) . "'";
            }
            
            if ($kodeSid) {
                $whereConditions[] = "toString(kode_sid) = '" . addslashes($kodeSid) . "'";
            }
            
            if ($isOnsite !== null) {
                $isOnsiteValue = $isOnsite === '1' || $isOnsite === 1 || $isOnsite === true ? '1' : '0';
                $whereConditions[] = "toString(is_onsite) = '" . $isOnsiteValue . "'";
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Query untuk mengambil data employee location terbaru
            // Use toString() for all fields to avoid type conflicts
            $sql = "
                SELECT 
                    toString(kode_sid) as kode_sid,
                    toString(k.nama) as nama,
                    toString(nama_perusahaan) as nama_perusahaan,
                    toString(id) as id,
                    toString(latitude) as latitude,
                    toString(longitude) as longitude,
                    toString(date) as date,
                    toString(device_info) as device_info,
                    toString(employee_id) as employee_id,
                    toString(location_id) as location_id,
                    toString(checkpoint) as checkpoint,
                    toString(is_onsite) as is_onsite
                FROM nitip.v_employee_location
                WHERE {$whereClause}
                ORDER BY date DESC
                LIMIT {$limit}
            ";
            
            $results = $clickhouse->query($sql);
            
            // Format data untuk frontend
            $employeeData = [];
            $employeeMap = []; // Untuk deduplikasi per employee_id (ambil yang terbaru)
            
            foreach ($results as $row) {
                $latitude = isset($row['latitude']) && $row['latitude'] !== '' ? (float)str_replace(',', '.', $row['latitude']) : null;
                $longitude = isset($row['longitude']) && $row['longitude'] !== '' ? (float)str_replace(',', '.', $row['longitude']) : null;
                
                if ($latitude === null || $longitude === null || $latitude == 0 || $longitude == 0) {
                    continue;
                }
                
                $employeeId = $row['employee_id'] ?? null;
                if (!$employeeId) {
                    continue;
                }
                
                $dateStr = $row['date'] ?? '';
                
                $empData = [
                    'kode_sid' => $row['kode_sid'] ?? null,
                    'nama' => $row['nama'] ?? null,
                    'nama_perusahaan' => $row['nama_perusahaan'] ?? null,
                    'id' => $row['id'] ?? null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'date' => $dateStr,
                    'device_info' => $row['device_info'] ?? null,
                    'employee_id' => $employeeId,
                    'location_id' => $row['location_id'] ?? null,
                    'checkpoint' => $row['checkpoint'] ?? null,
                    'is_onsite' => isset($row['is_onsite']) ? (int)$row['is_onsite'] : null,
                    'location' => [
                        'lat' => $latitude,
                        'lng' => $longitude
                    ]
                ];
                
                // Deduplikasi: jika employee_id sudah ada, ambil yang terbaru berdasarkan date
                if (!isset($employeeMap[$employeeId])) {
                    $employeeMap[$employeeId] = $empData;
                } else {
                    $existingDate = $employeeMap[$employeeId]['date'] ?? '';
                    if ($dateStr > $existingDate) {
                        $employeeMap[$employeeId] = $empData;
                    }
                }
            }
            
            // Convert map to array
            $employeeData = array_values($employeeMap);
            
            return response()->json([
                'success' => true,
                'employees' => $employeeData,
                'count' => count($employeeData)
            ]);
            
        } catch (Exception $e) {
            Log::error('Error fetching employee location data from ClickHouse: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'employees' => []
            ], 500);
        }
    }

    /**
     * Get work area polygons from geo_tagging table
     * Returns area kerja data with geometry converted to GeoJSON
     */
    public function getWorkAreas(Request $request)
    {
        try {
            // Cek koneksi PostgreSQL terlebih dahulu
            try {
                DB::connection('pgsql')->getPdo();
            } catch (Exception $connException) {
                Log::warning('PostgreSQL connection not available for getWorkAreas: ' . $connException->getMessage());
                return response()->json([
                    'success' => false,
                    'error' => 'PostgreSQL connection not available',
                    'areas' => []
                ], 503);
            }
            
            // Gunakan koneksi PostgreSQL untuk data geometry
            // Convert geometry ke WGS84 (SRID 4326) untuk kompatibilitas dengan frontend
            $workAreas = DB::connection('pgsql')
                ->table('geo_tagging')
                ->select(
                    'id',
                    'name',
                    'location_id',
                    'buffer',
                    'is_active',
                    'type_lookup_id',
                    DB::raw('ST_AsGeoJSON(ST_Transform(geometry, 4326)) as geometry_json'),
                    DB::raw('ST_SRID(geometry) as srid'),
                    'created_date',
                    'updated_date'
                )
                ->where('is_active', true)
                ->get();

            $areas = [];
            foreach ($workAreas as $area) {
                $geometryJson = json_decode($area->geometry_json, true);
                
                if ($geometryJson) {
                    $areas[] = [
                        'id' => $area->id,
                        'name' => $area->name,
                        'location_id' => $area->location_id,
                        'buffer' => $area->buffer,
                        'is_active' => $area->is_active,
                        'type_lookup_id' => $area->type_lookup_id,
                        'geometry' => $geometryJson,
                        'srid' => $area->srid,
                        'created_date' => $area->created_date,
                        'updated_date' => $area->updated_date
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'areas' => $areas,
                'count' => count($areas)
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching work areas from database: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'areas' => []
            ], 500);
        }
    }

    /**
     * Check if GPS coordinate is inside any work area polygon
     * Uses PostGIS ST_Contains for efficient spatial query
     */
    public function checkGpsInWorkArea(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $employeeId = $request->input('employee_id');

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'error' => 'Latitude and longitude are required'
                ], 400);
            }

            // Cek koneksi PostgreSQL terlebih dahulu
            try {
                DB::connection('pgsql')->getPdo();
            } catch (Exception $connException) {
                return response()->json([
                    'success' => false,
                    'error' => 'PostgreSQL connection not available',
                    'is_inside' => false
                ], 503);
            }

            // Gunakan PostGIS ST_Contains untuk mengecek apakah point berada di dalam polygon
            // Transform point ke SRID geometry terlebih dahulu, lalu bandingkan
            // ST_SetSRID: Set Spatial Reference System ID (4326 = WGS84)
            // ST_MakePoint: Create point from longitude, latitude
            // ST_Transform: Transform point ke SRID yang sama dengan geometry
            $result = DB::connection('pgsql')
                ->table('geo_tagging')
                ->select(
                    'id',
                    'name',
                    'location_id',
                    'buffer',
                    'type_lookup_id',
                    DB::raw('ST_SRID(geometry) as srid')
                )
                ->where('is_active', true)
                ->whereRaw(
                    'ST_Contains(
                        geometry, 
                        ST_Transform(
                            ST_SetSRID(ST_MakePoint(?, ?), 4326), 
                            ST_SRID(geometry)
                        )
                    )',
                    [$longitude, $latitude] // Note: PostGIS uses (lon, lat) order
                )
                ->first();

            $isInside = $result !== null;

            return response()->json([
                'success' => true,
                'is_inside' => $isInside,
                'work_area' => $result,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'employee_id' => $employeeId
            ]);

        } catch (Exception $e) {
            Log::error('Error checking GPS in work area: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'is_inside' => false
            ], 500);
        }
    }

    /**
     * Get GPS user location history
     * Returns history of locations visited by the user based on their name/kode_sid
     */
    public function getUserGpsHistory(Request $request)
    {
        try {
            $clickhouse = new ClickHouseService();
            
            if (!$clickhouse->isConnected()) {
                Log::warning('ClickHouse is not connected. Returning empty GPS history.');
                return response()->json([
                    'success' => false,
                    'error' => 'ClickHouse is not connected',
                    'history' => []
                ], 500);
            }

            // Get current user
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not authenticated',
                    'history' => []
                ], 401);
            }

            // Get user info for filtering
            $userName = strtolower(trim($user->name ?? ''));
            $userEmail = strtolower(trim($user->email ?? ''));
            $userKodeSid = $request->input('kode_sid'); // Optional: bisa dikirim dari frontend jika ada mapping
            
            // Get date range (default: 7 hari terakhir untuk performa lebih baik)
            $daysBack = (int)$request->input('days', 7);
            $startDate = Carbon::now()->subDays($daysBack)->format('Y-m-d');
            $endDate = Carbon::now()->format('Y-m-d');

            // Query untuk mengambil history lokasi dari nitip.v_employee_location
            // Filter berdasarkan nama atau kode_sid user
            $sql = "
                SELECT 
                    toString(kode_sid) as kode_sid,
                    toString(k.nama) as nama,
                    toString(nama_perusahaan) as nama_perusahaan,
                    toString(id) as id,
                    toString(latitude) as latitude,
                    toString(longitude) as longitude,
                    toString(date) as date,
                    toString(device_info) as device_info,
                    toString(employee_id) as employee_id,
                    toString(location_id) as location_id,
                    toString(checkpoint) as checkpoint,
                    toString(is_onsite) as is_onsite
                FROM nitip.v_employee_location
                WHERE latitude IS NOT NULL 
                    AND longitude IS NOT NULL
                    AND toDate(date) >= '{$startDate}'
                    AND toDate(date) <= '{$endDate}'
            ";

            // Filter berdasarkan nama atau kode_sid jika ada
            // Untuk user biasa, filter berdasarkan nama user yang login
            if ($userKodeSid) {
                $sql .= " AND toString(kode_sid) = '" . addslashes($userKodeSid) . "'";
            } elseif ($userName) {
                // Filter berdasarkan nama (case-insensitive partial match)
                // Cari nama yang mengandung kata-kata dari nama user
                $nameWords = explode(' ', $userName);
                $nameConditions = [];
                foreach ($nameWords as $word) {
                    if (strlen(trim($word)) > 2) { // Hanya kata dengan lebih dari 2 karakter
                        $nameConditions[] = "lower(toString(k.nama)) LIKE '%" . addslashes(trim($word)) . "%'";
                    }
                }
                if (!empty($nameConditions)) {
                    $sql .= " AND (" . implode(' OR ', $nameConditions) . ")";
                }
            }

            $sql .= " ORDER BY date DESC LIMIT 300";

            $results = $clickhouse->query($sql);

            // Group history berdasarkan area kerja (location_id atau koordinat yang sama dalam radius tertentu)
            $historyByLocation = [];
            $locationGroups = [];

            foreach ($results as $row) {
                // Handle comma as decimal separator
                $latitude = isset($row['latitude']) && $row['latitude'] !== '' ? (float)str_replace(',', '.', $row['latitude']) : null;
                $longitude = isset($row['longitude']) && $row['longitude'] !== '' ? (float)str_replace(',', '.', $row['longitude']) : null;
                
                if ($latitude === null || $longitude === null || $latitude == 0 || $longitude == 0) {
                    continue;
                }

                $employeeId = $row['employee_id'] ?? $row['id'] ?? $row['kode_sid'] ?? null;
                if (!$employeeId) {
                    continue;
                }

                // Cek apakah koordinat ini sudah ada di group lokasi (dalam radius 100 meter)
                // Optimasi: gunakan perkiraan jarak yang lebih cepat (tidak perlu akurat)
                $locationKey = null;
                $threshold = 0.001; // ~100 meter dalam derajat (perkiraan)
                
                foreach ($locationGroups as $key => $group) {
                    $groupLat = $group['latitude'];
                    $groupLng = $group['longitude'];
                    
                    // Quick check: jika perbedaan lat/lng terlalu besar, skip
                    if (abs($latitude - $groupLat) > $threshold || abs($longitude - $groupLng) > $threshold) {
                        continue;
                    }
                    
                    // Hanya hitung jarak jika sudah dekat
                    $distance = $this->calculateDistance($latitude, $longitude, $groupLat, $groupLng);
                    
                    if ($distance <= 100) { // Dalam radius 100 meter, anggap lokasi yang sama
                        $locationKey = $key;
                        break;
                    }
                }

                // Jika belum ada group, buat group baru
                if (!$locationKey) {
                    $locationKey = 'loc_' . count($locationGroups);
                    $locationGroups[$locationKey] = [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'location_id' => $row['location_id'] ?? null,
                        'count' => 0,
                        'first_visit' => $row['date'],
                        'last_visit' => $row['date'],
                        'visits' => []
                    ];
                }

                // Tambahkan visit ke group
                $visitData = [
                    'id' => $row['id'] ?? null,
                    'employee_id' => $employeeId,
                    'kode_sid' => $row['kode_sid'] ?? null,
                    'nama' => $row['nama'] ?? null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'date' => $row['date'] ?? null,
                    'location_id' => $row['location_id'] ?? null,
                    'checkpoint' => $row['checkpoint'] ?? null,
                    'is_onsite' => isset($row['is_onsite']) ? (int)$row['is_onsite'] : null,
                    'device_info' => $row['device_info'] ?? null
                ];

                $locationGroups[$locationKey]['visits'][] = $visitData;
                $locationGroups[$locationKey]['count']++;
                
                // Update first_visit dan last_visit
                if ($row['date']) {
                    $currentDate = $row['date'];
                    $firstVisit = $locationGroups[$locationKey]['first_visit'];
                    $lastVisit = $locationGroups[$locationKey]['last_visit'];
                    
                    // Compare dates (string comparison should work for ISO format)
                    if ($currentDate < $firstVisit || !$firstVisit) {
                        $locationGroups[$locationKey]['first_visit'] = $currentDate;
                    }
                    if ($currentDate > $lastVisit || !$lastVisit) {
                        $locationGroups[$locationKey]['last_visit'] = $currentDate;
                    }
                }
            }

            // Convert groups to array dan sort by last_visit (terbaru dulu)
            $historyData = [];
            foreach ($locationGroups as $key => $group) {
                // Sort visits by date (terbaru dulu)
                usort($group['visits'], function($a, $b) {
                    $dateA = $a['date'] ?? '';
                    $dateB = $b['date'] ?? '';
                    return strcmp($dateB, $dateA);
                });

                $historyData[] = [
                    'location_key' => $key,
                    'latitude' => $group['latitude'],
                    'longitude' => $group['longitude'],
                    'location_id' => $group['location_id'],
                    'visit_count' => $group['count'],
                    'first_visit' => $group['first_visit'],
                    'last_visit' => $group['last_visit'],
                    'visits' => $group['visits']
                ];
            }

            // Sort by last_visit (terbaru dulu)
            usort($historyData, function($a, $b) {
                return strcmp($b['last_visit'], $a['last_visit']);
            });

            // Limit to 5 locations
            $historyData = array_slice($historyData, 0, 5);

            return response()->json([
                'success' => true,
                'history' => $historyData,
                'count' => count($historyData),
                'total_visits' => array_sum(array_column($historyData, 'visit_count'))
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching user GPS history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'history' => []
            ], 500);
        }
    }

    /**
     * Get GPS user location details
     * Returns information about location, SAP count, and CCTV count for a GPS coordinate
     */
    public function getGpsUserLocationDetails(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $locationId = $request->input('location_id');
            $employeeId = $request->input('employee_id');

            if (!$latitude || !$longitude) {
                return response()->json([
                    'success' => false,
                    'error' => 'Latitude and longitude are required'
                ], 400);
            }

            $result = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'location_id' => $locationId,
                'employee_id' => $employeeId,
                'work_area' => null,
                'sap_count' => 0,
                'sap_open_count' => 0,
                'cctv_count' => 0,
                'pja_count' => 0,
                'sap_list' => [],
                'sap_open_list' => [],
                'cctv_list' => [],
                'pja_list' => []
            ];

            // Cek koneksi PostgreSQL terlebih dahulu
            $pgsqlAvailable = false;
            try {
                DB::connection('pgsql')->getPdo();
                $pgsqlAvailable = true;
            } catch (Exception $connException) {
                Log::warning('PostgreSQL connection not available for getGpsUserLocationDetails: ' . $connException->getMessage());
            }

            // 1. Cek area kerja dari geo_tagging
            if ($pgsqlAvailable) {
                try {
                    // Cek dulu SRID dari geometry di tabel geo_tagging
                    // Kemudian gunakan query yang sesuai dengan SRID tersebut
                    // Coba beberapa metode untuk memastikan deteksi area kerja
                    $workArea = null;
                    
                    // Method 1: ST_Contains dengan transform
                    try {
                        $workArea = DB::connection('pgsql')
                            ->table('geo_tagging')
                            ->select('id', 'name', 'location_id', 'buffer', 'type_lookup_id')
                            ->where('is_active', true)
                            ->whereRaw(
                                'ST_Contains(
                                    geometry, 
                                    ST_Transform(
                                        ST_SetSRID(ST_MakePoint(?, ?), 4326), 
                                        COALESCE(ST_SRID(geometry), 4326)
                                    )
                                )',
                                [$longitude, $latitude]
                            )
                            ->first();
                    } catch (Exception $e) {
                        Log::warning('Method 1 failed: ' . $e->getMessage());
                    }

                // Jika tidak ditemukan dengan transform, coba langsung tanpa transform (jika SRID sudah 4326)
                if (!$workArea) {
                    $workArea = DB::connection('pgsql')
                        ->table('geo_tagging')
                        ->select('id', 'name', 'location_id', 'buffer', 'type_lookup_id')
                        ->where('is_active', true)
                        ->whereRaw(
                            'ST_Contains(
                                geometry, 
                                ST_SetSRID(ST_MakePoint(?, ?), 4326)
                            )',
                            [$longitude, $latitude]
                        )
                        ->first();
                }

                // Jika masih tidak ditemukan, coba dengan buffer menggunakan ST_DWithin (untuk toleransi)
                if (!$workArea) {
                    try {
                        $bufferMeters = 50; // 50 meter buffer untuk toleransi
                        $workArea = DB::connection('pgsql')
                            ->table('geo_tagging')
                            ->select('id', 'name', 'location_id', 'buffer', 'type_lookup_id')
                            ->where('is_active', true)
                            ->whereRaw(
                                'ST_DWithin(
                                    geometry::geography,
                                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                                    ?
                                )',
                                [$longitude, $latitude, $bufferMeters]
                            )
                            ->orderByRaw(
                                'ST_Distance(
                                    geometry::geography,
                                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                                ) ASC'
                            )
                            ->first();
                    } catch (Exception $e) {
                        Log::warning('ST_DWithin method failed: ' . $e->getMessage());
                    }
                }
                
                // Method terakhir: Cek semua area kerja dan hitung jarak, ambil yang terdekat dalam radius tertentu
                if (!$workArea) {
                    try {
                        $maxDistanceMeters = 100; // Maksimal 100 meter dari area kerja
                        // Escape nilai untuk keamanan
                        $safeLongitude = addslashes($longitude);
                        $safeLatitude = addslashes($latitude);
                        
                        $allAreas = DB::connection('pgsql')
                            ->table('geo_tagging')
                            ->select(
                                'id', 
                                'name', 
                                'location_id', 
                                'buffer', 
                                'type_lookup_id',
                                DB::raw("ST_Distance(
                                    geometry::geography,
                                    ST_SetSRID(ST_MakePoint({$safeLongitude}, {$safeLatitude}), 4326)::geography
                                ) as distance")
                            )
                            ->where('is_active', true)
                            ->orderByRaw("ST_Distance(
                                geometry::geography,
                                ST_SetSRID(ST_MakePoint({$safeLongitude}, {$safeLatitude}), 4326)::geography
                            ) ASC")
                            ->limit(1)
                            ->get();
                        
                        if ($allAreas->count() > 0) {
                            $nearestArea = $allAreas->first();
                            // Jika jarak kurang dari maxDistanceMeters, gunakan area ini
                            if ($nearestArea->distance <= $maxDistanceMeters) {
                                $workArea = (object)[
                                    'id' => $nearestArea->id,
                                    'name' => $nearestArea->name,
                                    'location_id' => $nearestArea->location_id,
                                    'buffer' => $nearestArea->buffer,
                                    'type_lookup_id' => $nearestArea->type_lookup_id
                                ];
                            }
                        }
                    } catch (Exception $e) {
                        Log::warning('Distance-based method failed: ' . $e->getMessage());
                    }
                }

                if ($workArea) {
                    $result['work_area'] = [
                        'id' => $workArea->id,
                        'name' => $workArea->name,
                        'location_id' => $workArea->location_id,
                        'buffer' => $workArea->buffer,
                        'type_lookup_id' => $workArea->type_lookup_id
                    ];
                    Log::info('Work area found', [
                        'work_area_id' => $workArea->id,
                        'work_area_name' => $workArea->name,
                        'location_id' => $workArea->location_id,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'method' => 'detected'
                    ]);
                } else {
                    // Cek apakah ada area kerja di database (dari ClickHouse)
                    try {
                        $clickhouse = new ClickHouseService();
                        if ($clickhouse->isConnected()) {
                            // Ambil total area kerja dari ClickHouse
                            $totalAreasQuery = "
                                SELECT count(*) as total
                                FROM nitip.geo_tagging
                                WHERE is_active = true
                            ";
                            $totalAreasResult = $clickhouse->query($totalAreasQuery);
                            $totalAreas = $totalAreasResult[0]['total'] ?? 0;
                            
                            // Ambil sample area untuk debugging
                            $sampleAreaQuery = "
                                SELECT 
                                    toString(id) as id,
                                    toString(name) as name,
                                    toString(location_id) as location_id,
                                    toString(buffer) as buffer,
                                    toString(type_lookup_id) as type_lookup_id
                                FROM nitip.geo_tagging
                                WHERE is_active = true
                                LIMIT 1
                            ";
                            $sampleAreaResult = $clickhouse->query($sampleAreaQuery);
                            $sampleArea = $sampleAreaResult[0] ?? null;
                            
                            Log::warning('No work area found for coordinates', [
                                'latitude' => $latitude,
                                'longitude' => $longitude,
                                'total_active_areas' => $totalAreas,
                                'location_id' => $locationId,
                                'sample_area_id' => $sampleArea['id'] ?? 'unknown',
                                'sample_area_name' => $sampleArea['name'] ?? 'unknown'
                            ]);
                        }
                    } catch (Exception $e) {
                        Log::error('Error checking work areas from ClickHouse: ' . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                Log::error('Error checking work area: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ]);
            }
        }

            // 2. Hitung SAP di area tersebut (berdasarkan koordinat atau location_id)
            try {
                $clickhouse = new ClickHouseService();
                if ($clickhouse->isConnected()) {
                    $today = Carbon::now();
                    $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
                    $weekEnd = $weekStart->copy()->addDays(6);
                    $weekStartStr = $weekStart->format('Y-m-d');
                    $weekEndStr = $weekEnd->format('Y-m-d');

                    // Query untuk mendapatkan SAP yang berada di area tersebut
                    // Gunakan buffer sekitar 100 meter dari koordinat GPS
                    $bufferDistance = 0.001; // ~100 meter dalam derajat
                    $sapLatMin = $latitude - $bufferDistance;
                    $sapLatMax = $latitude + $bufferDistance;
                    $sapLngMin = $longitude - $bufferDistance;
                    $sapLngMax = $longitude + $bufferDistance;

                    // Query SAP dari semua tabel (Inspeksi, Observasi, OAK, Coaching)
                    // Gunakan pendekatan yang lebih sederhana: ambil semua SAP minggu ini, filter di PHP
                    $allSapResults = [];
                    
                    // Ambil SAP dari semua tabel untuk minggu ini
                    $sapData = $this->getSapDataFromClickHouse($weekStart);
                    
                    // Filter SAP berdasarkan jarak dari koordinat GPS
                    foreach ($sapData as $sap) {
                        $sapLat = isset($sap['latitude']) ? (float)$sap['latitude'] : null;
                        $sapLng = isset($sap['longitude']) ? (float)$sap['longitude'] : null;
                        
                        if ($sapLat && $sapLng) {
                            // Hitung jarak dalam meter
                            $distance = $this->calculateDistance($latitude, $longitude, $sapLat, $sapLng);
                            if ($distance <= 100) { // Dalam radius 100 meter
                                $allSapResults[] = [
                                    'task_number' => $sap['task_number'] ?? null,
                                    'lokasi' => $sap['lokasi'] ?? null,
                                    'detail_lokasi' => $sap['detail_lokasi'] ?? null,
                                    'latitude' => $sapLat,
                                    'longitude' => $sapLng,
                                    'tanggal' => $sap['tanggal'] ?? null,
                                    'jenis_laporan' => $sap['jenis_laporan'] ?? $sap['source_type'] ?? null,
                                    'source_type' => $sap['source_type'] ?? null,
                                    'distance' => round($distance, 2)
                                ];
                            }
                        }
                    }

                    // Pisahkan SAP open (belum selesai) dan semua SAP
                    $sapOpenResults = [];
                    foreach ($allSapResults as $sap) {
                        // SAP dianggap open jika status belum selesai
                        // Asumsi: jika tidak ada field status atau status != 'Selesai'/'Closed', maka open
                        $status = $sap['status'] ?? null;
                        $isOpen = !$status || 
                                 (stripos($status, 'selesai') === false && 
                                  stripos($status, 'closed') === false &&
                                  stripos($status, 'done') === false);
                        
                        if ($isOpen) {
                            $sapOpenResults[] = $sap;
                        }
                    }

                    $result['sap_count'] = count($allSapResults);
                    $result['sap_open_count'] = count($sapOpenResults);
                    $result['sap_list'] = $allSapResults;
                    $result['sap_open_list'] = $sapOpenResults;
                }
            } catch (Exception $e) {
                Log::warning('Error counting SAP: ' . $e->getMessage());
            }
            
            // 2b. Hitung PJA di lokasi tersebut
            try {
                $clickhouse = new ClickHouseService();
                if ($clickhouse->isConnected()) {
                    // Ambil PJA berdasarkan lokasi atau koordinat
                    $pjaBuffer = 0.005; // ~500 meter
                    $pjaLatMin = $latitude - $pjaBuffer;
                    $pjaLatMax = $latitude + $pjaBuffer;
                    $pjaLngMin = $longitude - $pjaBuffer;
                    $pjaLngMax = $longitude + $pjaBuffer;
                    
                    // Query PJA dari tabel nitip.pja_full_hierarchical_view_fix
                    // Filter berdasarkan lokasi jika ada location_id
                    $pjaQuery = "
                        SELECT 
                            toString(site) as site,
                            toString(lokasi) as lokasi,
                            toString(detail_lokasi) as detail_lokasi,
                            toString(pja_id) as pja_id,
                            toString(nama_pja) as nama_pja,
                            toString(pja_active) as pja_active,
                            toString(pja_type_name) as pja_type_name,
                            toString(pja_category_name) as pja_category_name,
                            toString(pja_layer) as pja_layer,
                            toString(id_employee) as id_employee,
                            toString(nik) as nik,
                            toString(kode_sid) as kode_sid,
                            toString(employee_name) as employee_name
                        FROM nitip.pja_full_hierarchical_view_fix
                        WHERE pja_active = '1'
                    ";
                    
                    // Jika ada location_id, filter berdasarkan lokasi
                    if ($locationId) {
                        $pjaQuery .= " AND toString(location_id) = '" . addslashes($locationId) . "'";
                    } else {
                        // Filter berdasarkan lokasi dari work_area jika ada
                        if (isset($result['work_area']['location_id'])) {
                            $pjaQuery .= " AND toString(location_id) = '" . addslashes($result['work_area']['location_id']) . "'";
                        }
                    }
                    
                    $pjaQuery .= " LIMIT 1000";
                    
                    try {
                        $pjaResults = $clickhouse->query($pjaQuery);
                        
                        $pjaList = [];
                        foreach ($pjaResults as $pja) {
                            $pjaList[] = [
                                'pja_id' => $pja['pja_id'] ?? null,
                                'nama_pja' => $pja['nama_pja'] ?? null,
                                'lokasi' => $pja['lokasi'] ?? null,
                                'detail_lokasi' => $pja['detail_lokasi'] ?? null,
                                'site' => $pja['site'] ?? null,
                                'pja_type_name' => $pja['pja_type_name'] ?? null,
                                'pja_category_name' => $pja['pja_category_name'] ?? null,
                                'employee_name' => $pja['employee_name'] ?? null,
                                'kode_sid' => $pja['kode_sid'] ?? null
                            ];
                        }
                        
                        $result['pja_count'] = count($pjaList);
                        $result['pja_list'] = $pjaList;
                    } catch (Exception $e) {
                        Log::warning('Error querying PJA: ' . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                Log::warning('Error counting PJA: ' . $e->getMessage());
            }

            // 3. Hitung CCTV yang mengcover area tersebut
            try {
                // Cek CCTV berdasarkan location_id jika ada
                if ($locationId) {
                    $cctvByLocation = CctvData::where('location_id', $locationId)
                        ->whereNotNull('longitude')
                        ->whereNotNull('latitude')
                        ->get();
                    
                    $result['cctv_count'] = $cctvByLocation->count();
                    $result['cctv_list'] = $cctvByLocation->map(function($cctv) {
                        return [
                            'id' => $cctv->id,
                            'name' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                            'no_cctv' => $cctv->no_cctv ?? null,
                            'latitude' => $cctv->latitude,
                            'longitude' => $cctv->longitude,
                            'coverage_lokasi' => $cctv->coverage_lokasi ?? null
                        ];
                    })->toArray();
                } else {
                    // Jika tidak ada location_id, cari CCTV dalam radius 500 meter
                    $cctvBuffer = 0.005; // ~500 meter
                    $cctvLatMin = $latitude - $cctvBuffer;
                    $cctvLatMax = $latitude + $cctvBuffer;
                    $cctvLngMin = $longitude - $cctvBuffer;
                    $cctvLngMax = $longitude + $cctvBuffer;

                    $nearbyCctv = CctvData::whereNotNull('longitude')
                        ->whereNotNull('latitude')
                        ->whereBetween('latitude', [$cctvLatMin, $cctvLatMax])
                        ->whereBetween('longitude', [$cctvLngMin, $cctvLngMax])
                        ->get();

                    // Filter berdasarkan jarak sebenarnya
                    $filteredCctv = [];
                    foreach ($nearbyCctv as $cctv) {
                        $distance = $this->calculateDistance($latitude, $longitude, $cctv->latitude, $cctv->longitude);
                        if ($distance <= 500) { // Dalam radius 500 meter
                            $filteredCctv[] = [
                                'id' => $cctv->id,
                                'name' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                                'no_cctv' => $cctv->no_cctv ?? null,
                                'latitude' => $cctv->latitude,
                                'longitude' => $cctv->longitude,
                                'coverage_lokasi' => $cctv->coverage_lokasi ?? null,
                                'distance' => round($distance, 2)
                            ];
                        }
                    }

                    $result['cctv_count'] = count($filteredCctv);
                    $result['cctv_list'] = $filteredCctv;
                }
            } catch (Exception $e) {
                Log::warning('Error counting CCTV: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Error getting GPS user location details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate distance between two coordinates in meters using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Earth radius in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Send Telegram notification
     */
    public function sendTelegramNotification(Request $request)
    {
        try {
            $chatId = $request->input('chat_id') ?? config('services.telegram.chat_id');
            $message = $request->input('message');
            $parseMode = $request->input('parse_mode', 'HTML'); // Default to HTML for better formatting
            
            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message is required'
                ], 400);
            }
            
            if (!$chatId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat ID is required'
                ], 400);
            }
            
            $telegramService = TelegramBotService::makeFromConfig();
            $response = $telegramService->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode
            ]);
            
            return response()->json([
                'success' => true,
                'response' => $response
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error sending Telegram notification: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return more detailed error for debugging
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'Telegram bot token is not configured') !== false) {
                $errorMessage = 'Telegram bot token is not configured. Please check your .env file.';
            } elseif (strpos($errorMessage, 'Chat not found') !== false || strpos($errorMessage, 'Unauthorized') !== false) {
                $errorMessage = 'Invalid Chat ID. Please check your Telegram Chat ID.';
            }
            
            return response()->json([
                'success' => false,
                'error' => $errorMessage,
                'details' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    public function getFilteredMapData(Request $request)
    {
        try {
            $company = trim($request->get('company', '__all__'));
            $site = trim($request->get('site', '__all__'));
            $weekStart = $request->get('week_start'); // Filter per week untuk SAP
            $showCctv = $request->get('show_cctv', 'true') === 'true';
            $showHazard = $request->get('show_hazard', 'true') === 'true';
            $showSap = $request->get('show_sap', $showHazard ? 'true' : 'false') === 'true'; // Alias untuk SAP
            $showGr = $request->get('show_gr', 'true') === 'true';
            $showInsiden = $request->get('show_insiden', 'true') === 'true';
            $showUnit = $request->get('show_unit', 'true') === 'true';

            $result = [
                'cctv' => [],
                'sap' => [],
                'hazard' => [], // Alias untuk kompatibilitas
                'gr' => [],
                'insiden' => [],
                'unit' => []
            ];

            // Get CCTV data
            if ($showCctv) {
                $cctvQuery = CctvData::whereNotNull('longitude')
                    ->whereNotNull('latitude');

                if ($company !== '__all__') {
                    if (strcasecmp($company, 'Tidak Diketahui') === 0) {
                        $cctvQuery->where(function ($q) {
                            $q->whereNull('perusahaan')
                              ->orWhere('perusahaan', '');
                        });
                    } else {
                        $cctvQuery->whereRaw('TRIM(perusahaan) = ?', [$company]);
                    }
                }

                if ($site !== '__all__') {
                    if (strcasecmp($site, 'Tidak Diketahui') === 0) {
                        $cctvQuery->where(function ($q) {
                            $q->whereNull('site')
                              ->orWhere('site', '');
                        });
                    } else {
                        $cctvQuery->whereRaw('TRIM(site) = ?', [$site]);
                    }
                }

                $cctvData = $cctvQuery->get();
                $result['cctv'] = $cctvData->map(function ($cctv) {
                    return [
                        'id' => $cctv->id,
                        'no_cctv' => $cctv->no_cctv ?? null,
                        'nomor_cctv' => $cctv->no_cctv ?? null,
                        'name' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                        'cctv_name' => $cctv->nama_cctv ?? null,
                        'nama_cctv' => $cctv->nama_cctv ?? null,
                        'location' => [(float) $cctv->longitude, (float) $cctv->latitude],
                        'status' => $cctv->kondisi ?? $cctv->status ?? 'Unknown',
                        'kondisi' => $cctv->kondisi ?? null,
                        'site' => $cctv->site ?? null,
                        'perusahaan' => $cctv->perusahaan ?? null,
                        'perusahaan_cctv' => $cctv->perusahaan ?? null,
                        'link_akses' => $cctv->link_akses ?? null,
                        'externalUrl' => $cctv->link_akses ?? null,
                        'rtsp_url' => null,
                        'user_name' => $cctv->user_name ?? null,
                        'password' => $cctv->password ?? null,
                        'ip' => null,
                        'port' => null,
                        'channel' => null,
                        'brand' => $this->extractBrandFromTipe($cctv->tipe_cctv ?? ''),
                        'tipe_cctv' => $cctv->tipe_cctv ?? null,
                        'fungsi_cctv' => $cctv->fungsi_cctv ?? null,
                        'lokasi_pemasangan' => $cctv->lokasi_pemasangan ?? null,
                        'control_room' => $cctv->control_room ?? null,
                        'coverage_lokasi' => $cctv->coverage_lokasi ?? null,
                    ];
                })->toArray();
            }

            // Get SAP data (mengganti Hazard)
            if ($showHazard || $showSap) {
                $sapData = $this->getSapDataFromClickHouse($weekStart);
                
                // Apply filters
                if ($company !== '__all__' || $site !== '__all__') {
                    $sapData = array_filter($sapData, function($sap) use ($company, $site) {
                        if ($company !== '__all__') {
                            $sapCompany = $sap['perusahaan_pelapor'] ?? $sap['perusahaan'] ?? null;
                            if (strcasecmp($company, 'Tidak Diketahui') === 0) {
                                if (!empty($sapCompany)) {
                                    return false;
                                }
                            } else {
                                if (trim($sapCompany) !== $company) {
                                    return false;
                                }
                            }
                        }
                        
                        // Site filter bisa diekstrak dari lokasi jika perlu
                        // Untuk sementara skip site filter karena SAP mungkin tidak punya field site langsung
                        
                        return true;
                    });
                }
                
                $result['sap'] = array_values($sapData);
                $result['hazard'] = array_values($sapData); // Alias untuk kompatibilitas
            }

            // Get GR data
            if ($showGr) {
                $grDetections = $this->getGrDetectionsFromPostgres();
                
                // Apply filters (GR mungkin tidak punya company/site, jadi skip filter untuk sekarang)
                $result['gr'] = $grDetections;
            }

            // Get Insiden data
            if ($showInsiden) {
                $insidenQuery = InsidenTabel::orderByDesc('created_at');

                if ($company !== '__all__') {
                    if (strcasecmp($company, 'Tidak Diketahui') === 0) {
                        $insidenQuery->where(function ($q) {
                            $q->whereNull('perusahaan')
                              ->orWhere('perusahaan', '');
                        });
                    } else {
                        $insidenQuery->whereRaw('TRIM(perusahaan) = ?', [$company]);
                    }
                }

                if ($site !== '__all__') {
                    if (strcasecmp($site, 'Tidak Diketahui') === 0) {
                        $insidenQuery->where(function ($q) {
                            $q->whereNull('site')
                              ->orWhere('site', '');
                        });
                    } else {
                        $insidenQuery->whereRaw('TRIM(site) = ?', [$site]);
                    }
                }

                $insidenRecords = $insidenQuery->get();
                $insidenGroups = $insidenRecords
                    ->groupBy('no_kecelakaan')
                    ->map(function ($items, $noKecelakaan) {
                        $items = $items->values();
                        $first = $items->first();

                        $latItem = $items->first(function ($item) {
                            return ! is_null($item->latitude);
                        });
                        $lonItem = $items->first(function ($item) {
                            return ! is_null($item->longitude);
                        });

                        return [
                            'no_kecelakaan' => $noKecelakaan,
                            'site' => $first->site,
                            'lokasi' => $first->lokasi ?? $first->lokasi_spesifik ?? null,
                            'status_lpi' => $first->status_lpi,
                            'layer' => $first->layer,
                            'jenis_item_ipls' => $first->jenis_item_ipls,
                            'kategori' => $first->kategori,
                            'tanggal' => optional($first->tanggal)->format('Y-m-d'),
                            'latitude' => $latItem->latitude ?? null,
                            'longitude' => $lonItem->longitude ?? null,
                            'items' => $items->map(function ($item) {
                                return [
                                    'tasklist' => $item->tasklist ?? null,
                                    'layer' => $item->layer,
                                    'jenis_item_ipls' => $item->jenis_item_ipls,
                                    'detail_layer' => $item->detail_layer,
                                    'klasifikasi_layer' => $item->klasifikasi_layer,
                                    'keterangan_layer' => $item->keterangan_layer,
                                    'site' => $item->site,
                                    'lokasi' => $item->lokasi,
                                    'lokasi_spesifik' => $item->lokasi_spesifik,
                                    'tanggal' => optional($item->tanggal)->format('Y-m-d'),
                                    'status_lpi' => $item->status_lpi,
                                    'catatan' => $item->catatan,
                                    'perusahaan' => $item->perusahaan,
                                    'latitude' => $item->latitude,
                                    'longitude' => $item->longitude,
                                ];
                            })->toArray(),
                        ];
                    })
                    ->filter(function ($group) {
                        return ! is_null($group['latitude']) && ! is_null($group['longitude']);
                    })
                    ->values()
                    ->toArray();

                $result['insiden'] = $insidenGroups;
            }

            // Get Unit data
            if ($showUnit) {
                try {
                    $besigmaService = new BesigmaDbService();
                    $unitVehicles = $besigmaService->getCombinedUnitData();
                    
                    // Apply filters if needed (unit mungkin tidak punya company/site yang jelas)
                    $result['unit'] = $unitVehicles;
                } catch (Exception $e) {
                    Log::error('Error fetching unit vehicles: ' . $e->getMessage());
                    $result['unit'] = [];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'filters' => [
                    'company' => $company,
                    'site' => $site,
                    'week_start' => $weekStart,
                    'show_cctv' => $showCctv,
                    'show_hazard' => $showHazard,
                    'show_sap' => $showSap,
                    'show_gr' => $showGr,
                    'show_insiden' => $showInsiden,
                    'show_unit' => $showUnit,
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching filtered map data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching filtered map data: ' . $e->getMessage(),
                'data' => [
                    'cctv' => [],
                    'sap' => [],
                    'hazard' => [],
                    'gr' => [],
                    'insiden' => [],
                    'unit' => []
                ]
            ], 500);
        }
    }

    /**
     * Get evaluation summary for area kerja or area CCTV
     */
    public function getEvaluationSummary(Request $request)
    {
        try {
            $type = $request->input('type'); // 'area_kerja' or 'area_cctv'
            $idLokasi = $request->input('id_lokasi');
            $lokasiName = $request->input('lokasi_name');
            $nomorCctv = $request->input('nomor_cctv');
            $cctvName = $request->input('cctv_name');
            $polygonCoords = $request->input('polygon_coords'); // Array of [lon, lat] coordinates

            $summary = [
                'cctv_list' => [],
                'inspeksi_count' => 0,
                'inspeksi_open_count' => 0,
                'hazard_count' => 0,
                'hazard_open_count' => 0,
                'coaching_count' => 0,
                'coaching_open_count' => 0,
                'observasi_count' => 0,
                'observasi_open_count' => 0,
                'observasi_area_kritis_count' => 0,
                'observasi_area_kritis_open_count' => 0,
                'inspeksi_hazard_list' => [],
                'coaching_list' => [],
                'observasi_list' => [],
                'observasi_area_kritis_list' => [],
                'area_name' => $lokasiName ?? $cctvName ?? 'N/A',
                'area_type' => $type ?? 'unknown'
            ];

            $clickhouse = new ClickHouseService();
            $besigmaDb = new BesigmaDbService();

            // Get CCTV list that covers this area
            try {
                $cctvQuery = CctvData::query();
                
                if ($lokasiName) {
                    $cctvQuery->where(function($q) use ($lokasiName) {
                        $q->where('coverage_lokasi', 'like', '%' . $lokasiName . '%')
                          ->orWhere('coverage_detail_lokasi', 'like', '%' . $lokasiName . '%')
                          ->orWhere('lokasi_pemasangan', 'like', '%' . $lokasiName . '%');
                    });
                } elseif ($cctvName || $nomorCctv) {
                    // If clicking on area CCTV, find other CCTV in the same location
                    $cctvData = CctvData::where(function($q) use ($cctvName, $nomorCctv) {
                        if ($nomorCctv) {
                            $q->where('no_cctv', 'like', '%' . $nomorCctv . '%');
                        }
                        if ($cctvName) {
                            $q->orWhere('nama_cctv', 'like', '%' . $cctvName . '%');
                        }
                    })->first();
                    
                    if ($cctvData) {
                        $lokasiCctv = $cctvData->coverage_detail_lokasi 
                                    ?? $cctvData->lokasi_pemasangan 
                                    ?? $cctvData->coverage_lokasi 
                                    ?? null;
                        
                        if ($lokasiCctv) {
                            $cctvQuery->where(function($q) use ($lokasiCctv) {
                                $q->where('coverage_lokasi', 'like', '%' . $lokasiCctv . '%')
                                  ->orWhere('coverage_detail_lokasi', 'like', '%' . $lokasiCctv . '%')
                                  ->orWhere('lokasi_pemasangan', 'like', '%' . $lokasiCctv . '%');
                            });
                        }
                    }
                }
                
                $cctvList = $cctvQuery->select('id', 'no_cctv', 'nama_cctv', 'coverage_lokasi', 'coverage_detail_lokasi', 'lokasi_pemasangan', 'site', 'perusahaan')
                    ->orderBy('no_cctv')
                    ->get();
                
                $summary['cctv_list'] = $cctvList->map(function($cctv) {
                    return [
                        'id' => $cctv->id,
                        'no_cctv' => $cctv->no_cctv,
                        'nama_cctv' => $cctv->nama_cctv,
                        'lokasi' => $cctv->coverage_detail_lokasi ?? $cctv->lokasi_pemasangan ?? $cctv->coverage_lokasi ?? 'N/A',
                        'site' => $cctv->site,
                        'perusahaan' => $cctv->perusahaan
                    ];
                })->toArray();
            } catch (Exception $e) {
                Log::warning('Error fetching CCTV list: ' . $e->getMessage());
            }

            // Get data for TODAY (hari ini)
            $today = Carbon::now()->format('Y-m-d');
            $todayStart = Carbon::now()->startOfDay()->format('Y-m-d H:i:s');
            $todayEnd = Carbon::now()->endOfDay()->format('Y-m-d H:i:s');

            // Get SAP/Hazard data from ClickHouse
            if ($clickhouse->isConnected()) {
                try {
                    // Location filter for matching
                    $locationFilter = '';
                    if ($lokasiName) {
                        $locationFilter = "AND (toString(lokasi) LIKE '%" . addslashes($lokasiName) . "%' OR toString(`detail lokasi`) LIKE '%" . addslashes($lokasiName) . "%')";
                    } elseif ($cctvName || $nomorCctv) {
                        $searchTerm = $cctvName ?? $nomorCctv ?? '';
                        $locationFilter = "AND (toString(lokasi) LIKE '%" . addslashes($searchTerm) . "%' OR toString(`detail lokasi`) LIKE '%" . addslashes($searchTerm) . "%')";
                    }

                    // Query tabel_inspeksi_hazard (hari ini) - COUNT
                    $sqlInspeksi = "
                        SELECT COUNT(*) as count
                        FROM nitip.tabel_inspeksi_hazard
                        WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                            {$locationFilter}
                        LIMIT 1
                    ";
                    
                    $resultsInspeksi = $clickhouse->query($sqlInspeksi);
                    if (!empty($resultsInspeksi) && isset($resultsInspeksi[0]['count'])) {
                        $summary['inspeksi_count'] += (int)$resultsInspeksi[0]['count'];
                    }
                    
                    // Query tabel_inspeksi_hazard dengan status OPEN - COUNT
                    $sqlInspeksiOpen = "
                        SELECT COUNT(*) as count
                        FROM nitip.tabel_inspeksi_hazard
                        WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                            {$locationFilter}
                            AND (toString(status) = 'Open' OR toString(status) = 'OPEN' OR toString(status) = 'open' OR toString(status) = 'Belum Selesai' OR toString(status) = 'BELUM SELESAI')
                        LIMIT 1
                    ";
                    
                    $resultsInspeksiOpen = $clickhouse->query($sqlInspeksiOpen);
                    if (!empty($resultsInspeksiOpen) && isset($resultsInspeksiOpen[0]['count'])) {
                        $summary['inspeksi_open_count'] = (int)$resultsInspeksiOpen[0]['count'];
                    }
                    
                    // Query tabel_inspeksi_hazard - DETAIL (limit 5)
                    $sqlInspeksiDetail = "
                        SELECT 
                            toString(`nomor laporan`) as nomor_laporan,
                            toString(lokasi) as lokasi,
                            toString(`detail lokasi`) as detail_lokasi,
                            toString(deskripsi) as deskripsi,
                            toString(status) as status,
                            toString(`tanggal pelaporan`) as tanggal_pelaporan,
                            toString(pelapor) as pelapor
                        FROM nitip.tabel_inspeksi_hazard
                        WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                            {$locationFilter}
                        ORDER BY toDateTime(`tanggal pelaporan`) DESC
                        LIMIT 5
                    ";
                    
                    $resultsInspeksiDetail = $clickhouse->query($sqlInspeksiDetail);
                    $summary['inspeksi_hazard_list'] = array_map(function($row) {
                        return [
                            'nomor_laporan' => $row['nomor_laporan'] ?? 'N/A',
                            'lokasi' => $row['lokasi'] ?? 'N/A',
                            'detail_lokasi' => $row['detail_lokasi'] ?? 'N/A',
                            'deskripsi' => $row['deskripsi'] ?? 'N/A',
                            'status' => $row['status'] ?? 'N/A',
                            'tanggal_pelaporan' => $row['tanggal_pelaporan'] ?? 'N/A',
                            'pelapor' => $row['pelapor'] ?? 'N/A'
                        ];
                    }, $resultsInspeksiDetail ?? []);

                    // Query tabel_observasi (hari ini) - COUNT
                    $sqlObservasi = "
                        SELECT COUNT(*) as count
                        FROM nitip.tabel_observasi
                        WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                            {$locationFilter}
                        LIMIT 1
                    ";
                    
                    $resultsObservasi = $clickhouse->query($sqlObservasi);
                    if (!empty($resultsObservasi) && isset($resultsObservasi[0]['count'])) {
                        $summary['observasi_count'] = (int)$resultsObservasi[0]['count'];
                    }
                    
                    // Query tabel_observasi dengan status OPEN - COUNT
                    $sqlObservasiOpen = "
                        SELECT COUNT(*) as count
                        FROM nitip.tabel_observasi
                        WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                            {$locationFilter}
                            AND (toString(status) = 'Open' OR toString(status) = 'OPEN' OR toString(status) = 'open' OR toString(status) = 'Belum Selesai' OR toString(status) = 'BELUM SELESAI')
                        LIMIT 1
                    ";
                    
                    $resultsObservasiOpen = $clickhouse->query($sqlObservasiOpen);
                    if (!empty($resultsObservasiOpen) && isset($resultsObservasiOpen[0]['count'])) {
                        $summary['observasi_open_count'] = (int)$resultsObservasiOpen[0]['count'];
                    }
                    
                    // Query tabel_observasi - DETAIL (limit 5)
                    $sqlObservasiDetail = "
                        SELECT 
                            toString(`nomor laporan`) as nomor_laporan,
                            toString(lokasi) as lokasi,
                            toString(`detail lokasi`) as detail_lokasi,
                            toString(deskripsi) as deskripsi,
                            toString(status) as status,
                            toString(`tanggal pelaporan`) as tanggal_pelaporan,
                            toString(pelapor) as pelapor
                        FROM nitip.tabel_observasi
                        WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                            {$locationFilter}
                        ORDER BY toDateTime(`tanggal pelaporan`) DESC
                        LIMIT 5
                    ";
                    
                    $resultsObservasiDetail = $clickhouse->query($sqlObservasiDetail);
                    $summary['observasi_list'] = array_map(function($row) {
                        return [
                            'nomor_laporan' => $row['nomor_laporan'] ?? 'N/A',
                            'lokasi' => $row['lokasi'] ?? 'N/A',
                            'detail_lokasi' => $row['detail_lokasi'] ?? 'N/A',
                            'deskripsi' => $row['deskripsi'] ?? 'N/A',
                            'status' => $row['status'] ?? 'N/A',
                            'tanggal_pelaporan' => $row['tanggal_pelaporan'] ?? 'N/A',
                            'pelapor' => $row['pelapor'] ?? 'N/A'
                        ];
                    }, $resultsObservasiDetail ?? []);

                    // Query tabel_observasi area kritis (hari ini)
                    // Find CCTV with kategori_area_tercapture = "Area Kritis" in this area, then match observasi by location
                    $cctvKritisLokasi = [];
                    try {
                        $cctvKritisQuery = CctvData::query();
                        
                        if ($lokasiName) {
                            $cctvKritisQuery->where(function($q) use ($lokasiName) {
                                $q->where('coverage_lokasi', 'like', '%' . $lokasiName . '%')
                                  ->orWhere('coverage_detail_lokasi', 'like', '%' . $lokasiName . '%')
                                  ->orWhere('lokasi_pemasangan', 'like', '%' . $lokasiName . '%');
                            });
                        } elseif ($cctvName || $nomorCctv) {
                            $cctvData = CctvData::where(function($q) use ($cctvName, $nomorCctv) {
                                if ($nomorCctv) {
                                    $q->where('no_cctv', 'like', '%' . $nomorCctv . '%');
                                }
                                if ($cctvName) {
                                    $q->orWhere('nama_cctv', 'like', '%' . $cctvName . '%');
                                }
                            })->first();
                            
                            if ($cctvData) {
                                $lokasiCctv = $cctvData->coverage_detail_lokasi 
                                            ?? $cctvData->lokasi_pemasangan 
                                            ?? $cctvData->coverage_lokasi 
                                            ?? null;
                                
                                if ($lokasiCctv) {
                                    $cctvKritisQuery->where(function($q) use ($lokasiCctv) {
                                        $q->where('coverage_lokasi', 'like', '%' . $lokasiCctv . '%')
                                          ->orWhere('coverage_detail_lokasi', 'like', '%' . $lokasiCctv . '%')
                                          ->orWhere('lokasi_pemasangan', 'like', '%' . $lokasiCctv . '%');
                                    });
                                }
                            }
                        }
                        
                        $cctvKritisList = $cctvKritisQuery->where('kategori_area_tercapture', 'Area Kritis')
                            ->select('coverage_lokasi', 'coverage_detail_lokasi', 'lokasi_pemasangan')
                            ->get();
                        
                        foreach ($cctvKritisList as $cctv) {
                            $lokasi = $cctv->coverage_detail_lokasi ?? $cctv->lokasi_pemasangan ?? $cctv->coverage_lokasi ?? null;
                            if ($lokasi) {
                                $cctvKritisLokasi[] = $lokasi;
                            }
                        }
                    } catch (Exception $e) {
                        Log::warning('Error fetching CCTV kritis locations: ' . $e->getMessage());
                    }
                    
                    // Query observasi area kritis based on CCTV locations
                    if (!empty($cctvKritisLokasi)) {
                        $lokasiKritisFilter = '';
                        foreach ($cctvKritisLokasi as $lokasi) {
                            if ($lokasiKritisFilter) {
                                $lokasiKritisFilter .= ' OR ';
                            }
                            $lokasiKritisFilter .= "(toString(lokasi) LIKE '%" . addslashes($lokasi) . "%' OR toString(`detail lokasi`) LIKE '%" . addslashes($lokasi) . "%')";
                        }
                        
                        $sqlObservasiKritis = "
                            SELECT COUNT(*) as count
                            FROM nitip.tabel_observasi
                            WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                                {$locationFilter}
                                AND ({$lokasiKritisFilter})
                            LIMIT 1
                        ";
                        
                        try {
                            $resultsObservasiKritis = $clickhouse->query($sqlObservasiKritis);
                            if (!empty($resultsObservasiKritis) && isset($resultsObservasiKritis[0]['count'])) {
                                $summary['observasi_area_kritis_count'] = (int)$resultsObservasiKritis[0]['count'];
                            }
                            
                            // Query observasi area kritis dengan status OPEN - COUNT
                            $sqlObservasiKritisOpen = "
                                SELECT COUNT(*) as count
                                FROM nitip.tabel_observasi
                                WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                                    {$locationFilter}
                                    AND ({$lokasiKritisFilter})
                                    AND (toString(status) = 'Open' OR toString(status) = 'OPEN' OR toString(status) = 'open' OR toString(status) = 'Belum Selesai' OR toString(status) = 'BELUM SELESAI')
                                LIMIT 1
                            ";
                            
                            $resultsObservasiKritisOpen = $clickhouse->query($sqlObservasiKritisOpen);
                            if (!empty($resultsObservasiKritisOpen) && isset($resultsObservasiKritisOpen[0]['count'])) {
                                $summary['observasi_area_kritis_open_count'] = (int)$resultsObservasiKritisOpen[0]['count'];
                            }
                            
                            // Query observasi area kritis - DETAIL (limit 5)
                            $sqlObservasiKritisDetail = "
                                SELECT 
                                    toString(`nomor laporan`) as nomor_laporan,
                                    toString(lokasi) as lokasi,
                                    toString(`detail lokasi`) as detail_lokasi,
                                    toString(deskripsi) as deskripsi,
                                    toString(status) as status,
                                    toString(`tanggal pelaporan`) as tanggal_pelaporan,
                                    toString(pelapor) as pelapor
                                FROM nitip.tabel_observasi
                                WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                                    {$locationFilter}
                                    AND ({$lokasiKritisFilter})
                                ORDER BY toDateTime(`tanggal pelaporan`) DESC
                                LIMIT 5
                            ";
                            
                            $resultsObservasiKritisDetail = $clickhouse->query($sqlObservasiKritisDetail);
                            $summary['observasi_area_kritis_list'] = array_map(function($row) {
                                return [
                                    'nomor_laporan' => $row['nomor_laporan'] ?? 'N/A',
                                    'lokasi' => $row['lokasi'] ?? 'N/A',
                                    'detail_lokasi' => $row['detail_lokasi'] ?? 'N/A',
                                    'deskripsi' => $row['deskripsi'] ?? 'N/A',
                                    'status' => $row['status'] ?? 'N/A',
                                    'tanggal_pelaporan' => $row['tanggal_pelaporan'] ?? 'N/A',
                                    'pelapor' => $row['pelapor'] ?? 'N/A'
                                ];
                            }, $resultsObservasiKritisDetail ?? []);
                        } catch (Exception $e) {
                            Log::warning('Error querying observasi area kritis: ' . $e->getMessage());
                        }
                    } else {
                        // Fallback: search for 'kritis' or 'critical' in location
                        $sqlObservasiKritis = "
                            SELECT COUNT(*) as count
                            FROM nitip.tabel_observasi
                            WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                                {$locationFilter}
                                AND (
                                    toString(lokasi) LIKE '%kritis%' 
                                    OR toString(lokasi) LIKE '%critical%'
                                    OR toString(`detail lokasi`) LIKE '%kritis%'
                                    OR toString(`detail lokasi`) LIKE '%critical%'
                                )
                            LIMIT 1
                        ";
                        
                        try {
                            $resultsObservasiKritis = $clickhouse->query($sqlObservasiKritis);
                            if (!empty($resultsObservasiKritis) && isset($resultsObservasiKritis[0]['count'])) {
                                $summary['observasi_area_kritis_count'] = (int)$resultsObservasiKritis[0]['count'];
                            }
                            
                            // Query observasi area kritis dengan status OPEN - COUNT (fallback)
                            $sqlObservasiKritisOpen = "
                                SELECT COUNT(*) as count
                                FROM nitip.tabel_observasi
                                WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                                    {$locationFilter}
                                    AND (
                                        toString(lokasi) LIKE '%kritis%' 
                                        OR toString(lokasi) LIKE '%critical%'
                                        OR toString(`detail lokasi`) LIKE '%kritis%'
                                        OR toString(`detail lokasi`) LIKE '%critical%'
                                    )
                                    AND (toString(status) = 'Open' OR toString(status) = 'OPEN' OR toString(status) = 'open' OR toString(status) = 'Belum Selesai' OR toString(status) = 'BELUM SELESAI')
                                LIMIT 1
                            ";
                            
                            $resultsObservasiKritisOpen = $clickhouse->query($sqlObservasiKritisOpen);
                            if (!empty($resultsObservasiKritisOpen) && isset($resultsObservasiKritisOpen[0]['count'])) {
                                $summary['observasi_area_kritis_open_count'] = (int)$resultsObservasiKritisOpen[0]['count'];
                            }
                            
                            // Query observasi area kritis - DETAIL (limit 5) - fallback
                            $sqlObservasiKritisDetail = "
                                SELECT 
                                    toString(`nomor laporan`) as nomor_laporan,
                                    toString(lokasi) as lokasi,
                                    toString(`detail lokasi`) as detail_lokasi,
                                    toString(deskripsi) as deskripsi,
                                    toString(status) as status,
                                    toString(`tanggal pelaporan`) as tanggal_pelaporan,
                                    toString(pelapor) as pelapor
                                FROM nitip.tabel_observasi
                                WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                                    {$locationFilter}
                                    AND (
                                        toString(lokasi) LIKE '%kritis%' 
                                        OR toString(lokasi) LIKE '%critical%'
                                        OR toString(`detail lokasi`) LIKE '%kritis%'
                                        OR toString(`detail lokasi`) LIKE '%critical%'
                                    )
                                ORDER BY toDateTime(`tanggal pelaporan`) DESC
                                LIMIT 5
                            ";
                            
                            $resultsObservasiKritisDetail = $clickhouse->query($sqlObservasiKritisDetail);
                            $summary['observasi_area_kritis_list'] = array_map(function($row) {
                                return [
                                    'nomor_laporan' => $row['nomor_laporan'] ?? 'N/A',
                                    'lokasi' => $row['lokasi'] ?? 'N/A',
                                    'detail_lokasi' => $row['detail_lokasi'] ?? 'N/A',
                                    'deskripsi' => $row['deskripsi'] ?? 'N/A',
                                    'status' => $row['status'] ?? 'N/A',
                                    'tanggal_pelaporan' => $row['tanggal_pelaporan'] ?? 'N/A',
                                    'pelapor' => $row['pelapor'] ?? 'N/A'
                                ];
                            }, $resultsObservasiKritisDetail ?? []);
                        } catch (Exception $e) {
                            Log::warning('Error querying observasi area kritis fallback: ' . $e->getMessage());
                        }
                    }

                    // Query tabel_coaching (hari ini) - COUNT
                    $sqlCoaching = "
                        SELECT COUNT(*) as count
                        FROM nitip.tabel_coaching
                        WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                            {$locationFilter}
                        LIMIT 1
                    ";
                    
                    $resultsCoaching = $clickhouse->query($sqlCoaching);
                    if (!empty($resultsCoaching) && isset($resultsCoaching[0]['count'])) {
                        $summary['coaching_count'] = (int)$resultsCoaching[0]['count'];
                    }
                    
                    // Query tabel_coaching dengan status OPEN - COUNT
                    $sqlCoachingOpen = "
                        SELECT COUNT(*) as count
                        FROM nitip.tabel_coaching
                        WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                            {$locationFilter}
                            AND (toString(status) = 'Open' OR toString(status) = 'OPEN' OR toString(status) = 'open' OR toString(status) = 'Belum Selesai' OR toString(status) = 'BELUM SELESAI')
                        LIMIT 1
                    ";
                    
                    $resultsCoachingOpen = $clickhouse->query($sqlCoachingOpen);
                    if (!empty($resultsCoachingOpen) && isset($resultsCoachingOpen[0]['count'])) {
                        $summary['coaching_open_count'] = (int)$resultsCoachingOpen[0]['count'];
                    }
                    
                    // Query tabel_coaching - DETAIL (limit 5)
                    $sqlCoachingDetail = "
                        SELECT 
                            toString(`nomor laporan`) as nomor_laporan,
                            toString(lokasi) as lokasi,
                            toString(`detail lokasi`) as detail_lokasi,
                            toString(deskripsi) as deskripsi,
                            toString(status) as status,
                            toString(`tanggal pelaporan`) as tanggal_pelaporan,
                            toString(pelapor) as pelapor
                        FROM nitip.tabel_coaching
                        WHERE toDate(`tanggal pelaporan`) = toDate('{$today}')
                            {$locationFilter}
                        ORDER BY toDateTime(`tanggal pelaporan`) DESC
                        LIMIT 5
                    ";
                    
                    $resultsCoachingDetail = $clickhouse->query($sqlCoachingDetail);
                    $summary['coaching_list'] = array_map(function($row) {
                        return [
                            'nomor_laporan' => $row['nomor_laporan'] ?? 'N/A',
                            'lokasi' => $row['lokasi'] ?? 'N/A',
                            'detail_lokasi' => $row['detail_lokasi'] ?? 'N/A',
                            'deskripsi' => $row['deskripsi'] ?? 'N/A',
                            'status' => $row['status'] ?? 'N/A',
                            'tanggal_pelaporan' => $row['tanggal_pelaporan'] ?? 'N/A',
                            'pelapor' => $row['pelapor'] ?? 'N/A'
                        ];
                    }, $resultsCoachingDetail ?? []);
                } catch (Exception $e) {
                    Log::error('Error querying ClickHouse for evaluation: ' . $e->getMessage());
                }
            }

            // Get Hazard data (from car_register in PostgreSQL) - hari ini
            if ($lokasiName || $cctvName) {
                try {
                    $searchTerm = $lokasiName ?? $cctvName ?? '';
                    $hazardCount = DB::connection('pgsql_ssh')
                        ->table('bcbeats.car_register')
                        ->whereBetween('create_date', [$todayStart, $todayEnd])
                        ->where(function($query) use ($searchTerm) {
                            $query->where('lokasi_detail', 'like', '%' . $searchTerm . '%')
                                  ->orWhere('deskripsi', 'like', '%' . $searchTerm . '%');
                        })
                        ->where('id_sumberdata', '<>', 200)
                        ->count();
                    
                    $summary['hazard_count'] = $hazardCount;
                } catch (Exception $e) {
                    Log::warning('Error fetching hazard count: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (Exception $e) {
            Log::error('Error getting evaluation summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error getting evaluation summary: ' . $e->getMessage(),
                'data' => [
                    'cctv_list' => [],
                    'inspeksi_count' => 0,
                    'hazard_count' => 0,
                    'coaching_count' => 0,
                    'observasi_count' => 0,
                    'observasi_area_kritis_count' => 0,
                    'area_name' => 'N/A',
                    'area_type' => 'unknown'
                ]
            ], 500);
        }
    }

}

