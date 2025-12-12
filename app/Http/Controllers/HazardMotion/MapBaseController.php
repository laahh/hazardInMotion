<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Models\CctvData;
use App\Models\InsidenTabel;
use App\Models\GrTable;
use App\Models\HazardValidation;
use App\Services\BesigmaDbService;
use App\Services\ClickHouseService;
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
     * Get user GPS data from ClickHouse (nitip.users and nitip.user_gps_latests)
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
            
            // Set timeout lebih lama untuk query yang besar (60 detik)
            // Note: timeout diatur di ClickHouseService, tapi kita bisa handle error dengan lebih baik

            // Query untuk mengambil data user GPS terbaru dengan join ke tabel users
            // Menggunakan toString() pada semua field untuk menghindari konflik tipe data
            // Convert semua numeric fields ke string dulu, baru convert kembali di PHP
            // Filter latitude/longitude != 0 dilakukan di PHP untuk menghindari konflik tipe
            // Filter hanya data hari ini (berdasarkan updated_at)
            $today = Carbon::now()->format('Y-m-d');
            
            $sql = "
                SELECT 
                    toString(u.id) as user_id,
                    toString(u.npk) as npk,
                    toString(u.fullname) as fullname,
                    toString(u.email) as email,
                    toString(u.phone) as phone,
                    toString(u.gender) as gender,
                    toString(u.division_name) as division_name,
                    toString(u.department_name) as department_name,
                    toString(u.functional_position) as functional_position,
                    toString(u.structural_position) as structural_position,
                    toString(u.site_assignment) as site_assignment,
                    toString(u.company_id) as company_id,
                    toString(u.is_deleted) as is_deleted,
                    toString(u.is_active) as is_active,
                    toString(gps.latitude) as latitude,
                    toString(gps.longitude) as longitude,
                    toString(gps.course) as course,
                    toString(gps.battery) as battery,
                    toString(gps.timezone) as timezone,
                    toString(gps.updated_at) as gps_updated_at,
                    toString(gps.created_at) as gps_created_at
                FROM nitip.user_gps_latests gps
                INNER JOIN nitip.users u ON toString(gps.user_id) = toString(u.id)
                WHERE gps.latitude IS NOT NULL 
                    AND gps.longitude IS NOT NULL
                    AND toDate(gps.updated_at) = today()
                ORDER BY gps.updated_at DESC
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

            // Format data untuk frontend dan filter boolean di PHP
            // Gunakan Map untuk deduplikasi berdasarkan user_id (ambil yang terbaru)
            $userGpsDataMap = [];
            foreach ($results as $row) {
                // Filter boolean di PHP untuk menghindari konflik tipe di ClickHouse
                $isDeleted = $row['is_deleted'] ?? null;
                $isActive = $row['is_active'] ?? null;
                
                // Skip jika user deleted atau tidak active
                if ($isDeleted === '1' || $isDeleted === 'true' || $isDeleted === 'True' || $isDeleted === true || $isDeleted === 1) {
                    continue;
                }
                if ($isActive === '0' || $isActive === 'false' || $isActive === 'False' || $isActive === false || $isActive === 0) {
                    continue;
                }
                
                // Filter latitude/longitude != 0 di PHP
                $latitude = isset($row['latitude']) && $row['latitude'] !== '' ? (float)$row['latitude'] : null;
                $longitude = isset($row['longitude']) && $row['longitude'] !== '' ? (float)$row['longitude'] : null;
                
                if ($latitude === null || $longitude === null || $latitude == 0 || $longitude == 0) {
                    continue;
                }
                
                $userId = $row['user_id'] ?? null;
                if (!$userId) {
                    continue;
                }
                
                $userData = [
                    'id' => $userId,
                    'user_id' => $userId,
                    'npk' => $row['npk'] ?? null,
                    'fullname' => $row['fullname'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'gender' => $row['gender'] ?? null,
                    'division_name' => $row['division_name'] ?? null,
                    'department_name' => $row['department_name'] ?? null,
                    'functional_position' => $row['functional_position'] ?? null,
                    'structural_position' => $row['structural_position'] ?? null,
                    'site_assignment' => $row['site_assignment'] ?? null,
                    'company_id' => $row['company_id'] ?? null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'location' => [
                        'lat' => $latitude,
                        'lng' => $longitude
                    ],
                    'course' => isset($row['course']) && $row['course'] !== '' ? (float)$row['course'] : null,
                    'battery' => isset($row['battery']) && $row['battery'] !== '' ? (int)$row['battery'] : null,
                    'timezone' => $row['timezone'] ?? null,
                    'gps_updated_at' => $row['gps_updated_at'] ?? null,
                    'gps_created_at' => $row['gps_created_at'] ?? null
                ];
                
                // Deduplikasi: jika user_id sudah ada, ambil yang terbaru berdasarkan gps_updated_at
                if (!isset($userGpsDataMap[$userId])) {
                    // User belum ada, tambahkan
                    $userGpsDataMap[$userId] = $userData;
                } else {
                    // User sudah ada, bandingkan timestamp dan ambil yang terbaru
                    $existingTime = $userGpsDataMap[$userId]['gps_updated_at'] ?? $userGpsDataMap[$userId]['gps_created_at'] ?? '';
                    $currentTime = $userData['gps_updated_at'] ?? $userData['gps_created_at'] ?? '';
                    
                    if ($currentTime > $existingTime) {
                        // Replace dengan data yang lebih baru
                        $userGpsDataMap[$userId] = $userData;
                    }
                }
            }
            
            // Convert map to array
            $userGpsData = array_values($userGpsDataMap);

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
                'hazard_count' => 0,
                'coaching_count' => 0,
                'observasi_count' => 0,
                'observasi_area_kritis_count' => 0,
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

                    // Query tabel_inspeksi_hazard (hari ini)
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

                    // Query tabel_observasi (hari ini)
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
                        } catch (Exception $e) {
                            Log::warning('Error querying observasi area kritis fallback: ' . $e->getMessage());
                        }
                    }

                    // Query tabel_coaching (hari ini)
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

