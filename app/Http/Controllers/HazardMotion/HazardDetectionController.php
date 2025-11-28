<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Models\CctvData;
use App\Models\InsidenTabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class HazardDetectionController extends Controller
{
    /**
     * Display the hazard detection page
     */
    public function index()
    {
        // Ambil data CCTV yang memiliki koordinat
        $cctvData = CctvData::whereNotNull('longitude')
            ->whereNotNull('latitude')
            ->get();

        // Format data untuk JavaScript dengan semua field yang diperlukan
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

        // Ambil data hazard detections dari PostgreSQL
        $hazardDetections = $this->getHazardDetectionsFromPostgres();

        // Ambil data GR detections dari PostgreSQL
        $grDetections = $this->getGrDetectionsFromPostgres();

        // Statistics
        $stats = [
            'total_detections' => count($hazardDetections),
            'active_detections' => count(array_filter($hazardDetections, fn($h) => $h['status'] === 'active')),
            'resolved_detections' => count(array_filter($hazardDetections, fn($h) => $h['status'] === 'resolved')),
            'critical_severity' => count(array_filter($hazardDetections, fn($h) => $h['severity'] === 'critical')),
            'high_severity' => count(array_filter($hazardDetections, fn($h) => $h['severity'] === 'high')),
            'medium_severity' => count(array_filter($hazardDetections, fn($h) => $h['severity'] === 'medium')),
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

        return view('HazardMotion.admin.hazard-detection', compact(
            'cctvLocations',
            'hazardDetections',
            'grDetections',
            'stats',
            'insidenGroups',
            'criticalAreaCount',
            'criticalCoveragePercentage',
            'criticalCoverageCctv'
        ));
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
     * Get GR detections from PostgreSQL gr_table
     */
    private function getGrDetectionsFromPostgres()
    {
        try {
            // Check if SSH tunnel is active
            if (!$this->isTunnelActive()) {
                \Log::warning('SSH tunnel is not active. Returning empty GR detections.');
                return [];
            }

            // Query untuk mengambil data GR dari PostgreSQL
            // Asumsi tabel gr_table ada di PostgreSQL dengan kolom: tasklist, gr, catatan
            // Jika struktur berbeda, sesuaikan query ini
            $query = "
                SELECT 
                    gr.id,
                    gr.tasklist,
                    gr.gr,
                    gr.catatan,
                    gr.create_date AS tanggal_pembuatan,
                    gr.location_latitude AS latitude,
                    gr.location_longitude AS longitude,
                    loc_d.nama AS nama_detail_lokasi,
                    loc.nama AS nama_lokasi,
                    site.nama AS nama_site,
                    st.nama AS status,
                    req.nama AS nama_pelapor,
                    pic.nama AS nama_pic,
                    m_goldenrule.nama AS nama_goldenrule,
                    m_kategori_tipe.nama AS nama_kategori
                FROM bcbeats.gr_table gr
                    LEFT JOIN bcbeats.m_lokasi loc_d ON loc_d.id = gr.id_lokasi
                    LEFT JOIN bcbeats.m_lokasi loc ON loc.id = loc_d.id_parent
                    LEFT JOIN bcbeats.m_lokasi site ON site.id = loc.id_parent
                    LEFT JOIN bcbeats.m_status st ON st.id = gr.id_status
                    LEFT JOIN bcsid.m_karyawan req ON req.id = gr.id_pelapor
                    LEFT JOIN bcsid.m_karyawan pic ON pic.id = gr.id_pic
                    LEFT JOIN bcbeats.m_goldenrule ON m_goldenrule.id = gr.id_goldenrule
                    LEFT JOIN bcbeats.m_kategori_tipe ON m_kategori_tipe.id = gr.id_kategori
                WHERE gr.create_date >= '2023-12-31 23:59:59'::timestamp without time zone
                ORDER BY gr.create_date DESC
                LIMIT 30
            ";

            // Jika tabel gr_table tidak memiliki kolom seperti di atas, gunakan query sederhana
            // Alternatif: query sederhana jika struktur tabel berbeda
            $simpleQuery = "
                SELECT 
                    id,
                    tasklist,
                    gr,
                    catatan,
                    created_at AS tanggal_pembuatan
                FROM bcbeats.gr_table
                ORDER BY created_at DESC
                LIMIT 30
            ";

            try {
                $results = DB::connection('pgsql_ssh')->select($query);
            } catch (Exception $e) {
                // Fallback ke query sederhana jika query kompleks gagal
                \Log::info('Complex GR query failed, trying simple query: ' . $e->getMessage());
                $results = DB::connection('pgsql_ssh')->select($simpleQuery);
            }

            // Map data dari PostgreSQL ke format yang digunakan di view
            $grDetections = array_map(function ($row) {
                // Map status jika ada
                $statusMap = [
                    'Open' => 'active',
                    'Closed' => 'resolved',
                    'In Progress' => 'active',
                    'Resolved' => 'resolved',
                ];
                $status = isset($row->status) ? ($statusMap[$row->status] ?? 'active') : 'active';

                // Format detected_at
                $detectedAt = isset($row->tanggal_pembuatan) && $row->tanggal_pembuatan
                    ? date('Y-m-d H:i:s', strtotime($row->tanggal_pembuatan))
                    : (isset($row->created_at) && $row->created_at ? date('Y-m-d H:i:s', strtotime($row->created_at)) : now()->format('Y-m-d H:i:s'));

                return [
                    'id' => 'GR-' . ($row->id ?? uniqid()),
                    'type' => $row->tasklist ?? 'GR Task',
                    'gr' => $row->gr ?? 'N/A',
                    'catatan' => $row->catatan ?? null,
                    'tasklist' => $row->tasklist ?? 'N/A',
                    'severity' => 'medium', // Default severity untuk GR
                    'status' => $status,
                    'location' => [
                        'lat' => isset($row->latitude) && $row->latitude ? (float) $row->latitude : null,
                        'lng' => isset($row->longitude) && $row->longitude ? (float) $row->longitude : null,
                    ],
                    'detected_at' => $detectedAt,
                    'description' => $row->catatan ?? $row->tasklist ?? 'No description',
                    'zone' => isset($row->nama_lokasi) ? $row->nama_lokasi : (isset($row->nama_detail_lokasi) ? $row->nama_detail_lokasi : (isset($row->nama_site) ? $row->nama_site : 'Unknown')),
                    'site' => $row->nama_site ?? null,
                    'nama_lokasi' => $row->nama_lokasi ?? null,
                    'nama_detail_lokasi' => $row->nama_detail_lokasi ?? null,
                    'nama_pelapor' => $row->nama_pelapor ?? null,
                    'nama_pic' => $row->nama_pic ?? null,
                    'nama_goldenrule' => $row->nama_goldenrule ?? null,
                    'nama_kategori' => $row->nama_kategori ?? null,
                    'url_photo' => null, // GR mungkin tidak punya foto
                    'tanggal_pembuatan' => $detectedAt,
                    'original_id' => $row->id ?? null,
                ];
            }, $results);

            return $grDetections;

        } catch (Exception $e) {
            \Log::error('Error fetching GR detections from PostgreSQL: ' . $e->getMessage());
            // Return empty array on error
            return [];
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

}

