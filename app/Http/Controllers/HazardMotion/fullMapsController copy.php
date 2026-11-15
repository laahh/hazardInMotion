<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Models\CctvData;
use App\Models\CctvCoverage;
use App\Models\CctvControlRoomPengawas;
use App\Models\CctvP2hChecklist;
use App\Models\InsidenTabel;
use App\Models\GrTable;
use App\Models\HazardValidation;
use App\Models\PjaCctvDedicated;
use App\Models\IntervensiControlRoom;
use App\Models\IntervensiAreaKerja;
use App\Models\DailyOperationPlan;
use App\Services\BesigmaDbService;
use App\Services\ClickHouseService;
use App\Services\TelegramBotService;
use App\Services\QwenAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class fullMapsController extends Controller
{
   
    // public function index()
    // {
       

    //     return view('HazardMotion.admin.fullMaps'
    //     );
    // }



    public function index()
    {
        // Get logged-in user
        $user = Auth::user();
        $userName = $user ? $user->name : null;
        
        // Get control rooms that the logged-in user supervises
        $supervisedControlRooms = [];
        if ($userName) {
            $pengawasRecords = CctvControlRoomPengawas::where('nama_pengawas', $userName)->get();
            $supervisedControlRooms = $pengawasRecords->pluck('control_room')->filter()->unique()->toArray();
        }
        
        // Ambil SEMUA data CCTV dari tabel cctv_data_bmo2 (termasuk yang tidak punya koordinat)
        // Model CctvData sudah dikonfigurasi untuk menggunakan tabel cctv_data_bmo2
        // DEFAULT: Filter CCTV data based on supervised control rooms (auth pengawas)
        // Tapi semua data tetap dikirim ke frontend untuk filter Control Room
        $cctvDataAllQuery = CctvData::query();
        
        // Filter CCTV data based on supervised control rooms if user is not admin
        // If user is admin or has no supervised control rooms, show all CCTV
        if ($userName && !empty($supervisedControlRooms)) {
            $cctvDataAllQuery->whereIn('control_room', $supervisedControlRooms);
        }
        
        $cctvDataAll = $cctvDataAllQuery->get();
        
        // Ambil data CCTV yang memiliki koordinat untuk ditampilkan di map
        // DEFAULT: Filter by supervised control rooms (auth pengawas)
        $cctvDataWithLocationQuery = CctvData::whereNotNull('longitude')
            ->whereNotNull('latitude');
        
        // Apply same filter for map data
        if ($userName && !empty($supervisedControlRooms)) {
            $cctvDataWithLocationQuery->whereIn('control_room', $supervisedControlRooms);
        }
        
        $cctvDataWithLocation = $cctvDataWithLocationQuery->get();
        
        // AMBIL SEMUA DATA CCTV (tanpa filter) untuk keperluan filter Control Room
        // Data ini akan digunakan di frontend untuk filter Control Room
        $cctvDataAllForControlRoom = CctvData::query()->get();
        
        // Format data lengkap untuk filter Control Room (semua CCTV tanpa filter pengawas)
        $cctvLocationsForControlRoom = $cctvDataAllForControlRoom->map(function ($cctv) {
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
                'coverage_detail_lokasi' => $cctv->coverage_detail_lokasi ?? null,
                'kategori_area_tercapture' => $cctv->kategori_area_tercapture ?? null,
                'created_at' => $cctv->created_at ? $cctv->created_at->toDateTimeString() : null,
                'updated_at' => $cctv->updated_at ? $cctv->updated_at->toDateTimeString() : null,
                'tahun_update' => $cctv->tahun_update ?? null,
                'bulan_update' => $cctv->bulan_update ?? null,
            ];
        })->toArray();
        
        // Data untuk map lengkap (tanpa filter pengawas) - untuk filter Control Room
        $cctvLocationsForMapAll = CctvData::whereNotNull('longitude')
            ->whereNotNull('latitude')
            ->get()
            ->map(function ($cctv) {
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
                    'coverage_detail_lokasi' => $cctv->coverage_detail_lokasi ?? null,
                ];
            })->toArray();

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
                'coverage_detail_lokasi' => $cctv->coverage_detail_lokasi ?? null,
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
                'coverage_detail_lokasi' => $cctv->coverage_detail_lokasi ?? null,
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

        // Get P2H status for control rooms
        $p2hStatus = [];
        if ($userName && !empty($supervisedControlRooms)) {
            $today = Carbon::now()->toDateString();
            $currentShift = $this->getCurrentShift();
            
            foreach ($supervisedControlRooms as $controlRoom) {
                $hasP2h = CctvP2hChecklist::hasP2hToday($controlRoom, $currentShift, $today);
                $p2hStatus[$controlRoom] = [
                    'has_p2h' => $hasP2h,
                    'control_room' => $controlRoom,
                ];
            }
        }

        // Hitung CCTV yang belum ada PJA (belum termapping)
        // Ambil semua cctv_dedicated yang sudah ada PJA dari tabel pja_cctv_dedicated
        // Gunakan distinct untuk menghindari duplikasi
        $mappedCctv = PjaCctvDedicated::select('cctv_dedicated')
            ->whereNotNull('cctv_dedicated')
            ->distinct()
            ->pluck('cctv_dedicated')
            ->map(function($item) {
                // Normalize: trim, hapus karakter non-printable, lowercase
                $normalized = trim($item ?? '');
                $normalized = preg_replace('/[\x00-\x1F\x7F]/', '', $normalized); // Hapus karakter kontrol
                return strtolower($normalized);
            })
            ->filter()
            ->unique() // Pastikan tidak ada duplikasi setelah normalize
            ->values()
            ->toArray();
        
        // Query dasar untuk CCTV - tampilkan semua CCTV tanpa filter berdasarkan control room pengawasan
        $baseQuery = CctvData::whereNotNull('no_cctv');
        
        // Ambil semua CCTV data untuk di-filter di PHP (lebih fleksibel untuk matching)
        $allCctvData = $baseQuery->get();
        
        // Hitung total CCTV untuk persentase
        $totalCctvForPja = $allCctvData->count();
        
        // Filter CCTV yang belum ada PJA dengan matching yang fleksibel
        $cctvBelumPjaCount = 0;
        if ($totalCctvForPja > 0) {
            if (!empty($mappedCctv)) {
                // Filter CCTV yang belum termapping
                // Hanya match antara no_cctv dengan cctv_dedicated
                // Gunakan exact match saja (case insensitive, dengan trim dan normalize)
                $unmappedCctv = $allCctvData->filter(function($cctv) use ($mappedCctv) {
                    $noCctv = trim($cctv->no_cctv ?? '');
                    
                    // Jika tidak ada no_cctv, skip
                    if (empty($noCctv)) {
                        return false; // Skip CCTV tanpa no_cctv
                    }
                    
                    // Normalize untuk matching (lowercase, trim, hapus karakter non-printable)
                    $noCctvNormalized = strtolower($noCctv);
                    $noCctvNormalized = preg_replace('/[\x00-\x1F\x7F]/', '', $noCctvNormalized); // Hapus karakter kontrol
                    
                    // Cek apakah no_cctv ada di mapped CCTV (cctv_dedicated)
                    // Exact match (case insensitive) saja
                    if (in_array($noCctvNormalized, $mappedCctv)) {
                        return false; // Sudah termapping
                    }
                    
                    // Return true jika belum termapping
                    return true;
                });
                
                $cctvBelumPjaCount = $unmappedCctv->count();
                
                // Debug: Ambil sample CCTV yang sudah termapping dan belum termapping
                $mappedSample = $allCctvData->filter(function($cctv) use ($mappedCctv) {
                    $noCctv = trim($cctv->no_cctv ?? '');
                    if (empty($noCctv)) return false;
                    $noCctvNormalized = strtolower(preg_replace('/[\x00-\x1F\x7F]/', '', $noCctv));
                    return in_array($noCctvNormalized, $mappedCctv);
                })->take(5)->pluck('no_cctv')->toArray();
                
                $unmappedSample = $unmappedCctv->take(5)->pluck('no_cctv')->toArray();
            } else {
                // Jika tidak ada CCTV yang sudah ada PJA, berarti semua CCTV belum ada PJA
                $cctvBelumPjaCount = $totalCctvForPja;
                $mappedSample = [];
                $unmappedSample = $allCctvData->take(5)->pluck('no_cctv')->toArray();
            }
        }
        
        // Hitung persentase CCTV yang belum ada PJA
        $cctvBelumPjaPercentage = $totalCctvForPja > 0 
            ? round(($cctvBelumPjaCount / $totalCctvForPja) * 100, 1) 
            : 0;
        
        // Hitung persentase CCTV yang sudah ada PJA
        $cctvSudahPjaPercentage = $totalCctvForPja > 0 
            ? round((($totalCctvForPja - $cctvBelumPjaCount) / $totalCctvForPja) * 100, 1) 
            : 0;
        
        // Hitung jumlah CCTV yang sudah ada PJA
        $cctvSudahPjaCount = $totalCctvForPja - $cctvBelumPjaCount;

        // Debug logging (bisa dihapus setelah fix)
        Log::info('CCTV Belum PJA Calculation', [
            'total_cctv' => $totalCctvForPja,
            'cctv_belum_pja_count' => $cctvBelumPjaCount,
            'cctv_belum_pja_percentage' => $cctvBelumPjaPercentage,
            'mapped_cctv_count' => count($mappedCctv ?? []),
            'mapped_cctv_sample' => $mappedSample ?? [],
            'unmapped_cctv_sample' => $unmappedSample ?? [],
            'supervised_control_rooms' => $supervisedControlRooms ?? [],
            'user_name' => $userName,
        ]);

        // Ambil data P2H terbaru untuk ditampilkan di tabel
        $p2hResults = [];
        $processedCctvIds = []; // Untuk menghindari duplikasi
        
        if ($userName && !empty($supervisedControlRooms)) {
            // Ambil P2H terbaru untuk setiap control room
            foreach ($supervisedControlRooms as $controlRoom) {
                $latestP2h = CctvP2hChecklist::where('control_room', $controlRoom)
                    ->where('status', 'completed')
                    ->orderBy('tanggal_pemeriksaan', 'desc')
                    ->orderBy('shift', 'desc')
                    ->first();
                
                if ($latestP2h && !empty($latestP2h->detail_cctv) && is_array($latestP2h->detail_cctv)) {
                    // Jika ada data P2H dengan detail_cctv, gunakan itu
                    foreach ($latestP2h->detail_cctv as $detail) {
                        $cctvId = $detail['cctv_id'] ?? null;
                        $noCctv = $detail['no_cctv'] ?? null;
                        
                        // Skip jika sudah diproses (untuk menghindari duplikasi)
                        $key = $cctvId ?? $noCctv;
                        if ($key && in_array($key, $processedCctvIds)) {
                            continue;
                        }
                        
                        if ($key) {
                            $processedCctvIds[] = $key;
                        }
                        
                        $p2hResults[] = [
                            'cctv_id' => $cctvId,
                            'nama_cctv' => $detail['nama_cctv'] ?? null,
                            'no_cctv' => $noCctv,
                            'lokasi' => $detail['lokasi'] ?? null,
                            'kondisi' => strtolower($detail['status'] ?? $detail['kondisi'] ?? 'baik'), // baik, rusak, tidak_ada
                            'catatan' => $detail['catatan'] ?? null,
                            'tanggal_pemeriksaan' => $latestP2h->tanggal_pemeriksaan,
                            'shift' => $latestP2h->shift,
                        ];
                    }
                }
            }
            
            // Jika tidak ada data P2H atau data P2H tidak lengkap, ambil dari cctv_data_bmo2
            if (empty($p2hResults)) {
                $cctvFromMaster = CctvData::whereIn('control_room', $supervisedControlRooms)
                    ->whereNotNull('no_cctv')
                    ->orderBy('no_cctv')
                    ->get();
                
                foreach ($cctvFromMaster as $cctv) {
                    // Skip jika sudah ada di hasil P2H
                    if (in_array($cctv->id, $processedCctvIds) || in_array($cctv->no_cctv, $processedCctvIds)) {
                        continue;
                    }
                    
                    $p2hResults[] = [
                        'cctv_id' => $cctv->id,
                        'nama_cctv' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                        'no_cctv' => $cctv->no_cctv,
                        'lokasi' => $cctv->lokasi_pemasangan ?? $cctv->coverage_detail_lokasi ?? null,
                        'kondisi' => strtolower($cctv->kondisi ?? 'baik'), // baik, rusak, tidak_ada
                        'catatan' => $cctv->keterangan ?? null,
                        'tanggal_pemeriksaan' => null,
                        'shift' => null,
                    ];
                }
            }
        }

        return view('HazardMotion.admin.fullMaps', compact(
            'cctvLocations',
            'cctvLocationsForMap',
            'cctvLocationsForControlRoom',  // Semua CCTV untuk filter Control Room
            'cctvLocationsForMapAll',        // Semua CCTV dengan koordinat untuk filter Control Room
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
            'unitVehicles',
            'p2hStatus',
            'cctvBelumPjaCount',
            'cctvBelumPjaPercentage',
            'cctvSudahPjaCount',
            'cctvSudahPjaPercentage',
            'totalCctvForPja',
            'p2hResults'
        ));
    }

    /**
     * Extract brand from tipe CCTV
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
     * Get SAP data from ClickHouse
     */
    private function getSapDataFromClickHouse($weekStart = null)
    {
        Log::info('getSapDataFromClickHouse - Method called', [
            'weekStart' => $weekStart ? (is_string($weekStart) ? $weekStart : $weekStart->format('Y-m-d H:i:s')) : 'NULL'
        ]);
        
        try {
            $clickhouse = new ClickHouseService();
            Log::info('getSapDataFromClickHouse - ClickHouseService instantiated');
            
            if (!$clickhouse->isConnected()) {
                Log::warning('ClickHouse is not connected. Returning empty SAP data.');
                return [];
            }
            
            Log::info('getSapDataFromClickHouse - ClickHouse is connected');

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
            
            // 1. Query aaj_car_all_year_from_dav
            try {
                $sqlInspeksi = "
                    SELECT 
                        toString(id) as task_number,
                        ifNull(toString(jenis_laporan), 'INSPEKSI_HAZARD') as jenis_laporan,
                        ifNull(toString(deskripsi), '') as aktivitas_pekerjaan,
                        ifNull(toString(nama_lokasi), '') as lokasi,
                        ifNull(toString(nama_detail_lokasi), '') as detail_lokasi,
                        ifNull(toString(deskripsi), '') as keterangan,
                        ifNull(toString(tanggal_pembuatan), toString(bedraft_date)) as tanggal_pelaporan,
                        ifNull(toString(perusahaan_pelapor), '') as perusahaan_pelapor,
                        ifNull(toString(nama_pelapor), '') as pelapor,
                        ifNull(toString(sid_pelapor), '') as sid_pelapor,
                        ifNull(toString(jabatan_fungsional_pelapor), '') as jabatan_fungsional_pelapor,
                        ifNull(toString(departemen_pelapor), '') as departemen_pelapor,
                        ifNull(toString(nama_pic), '') as pic,
                        ifNull(toString(sid_pic), '') as sid_pic,
                        ifNull(toString(jabatan_fungsional_pic), '') as jabatan_fungsional_pic,
                        ifNull(toString(perusahaan_pic), '') as perusahaan_pic,
                        ifNull(toString(departemen_pic), '') as departemen_pic,
                        ifNull(toString(url_photo), '') as uri_foto,
                        ifNull(toString(name_tools_observation), '') as tools_pengawasan,
                        ifNull(toString(tindakan), '') as catatan_tindakan,
                        ifNull(toString(id_pelapor), '') as nik_pelapor,
                        ifNull(toString(nama_pelapor), '') as nama_pelapor,
                        ifNull(toString(perusahaan_pelapor), '') as nama_perusahaan_pelapor_karyawan,
                        ifNull(toString(jabatan_fungsional_pelapor), '') as jabatan_fungsional_karyawan_pelapor,
                        ifNull(toString(latitude), '') as latitude,
                        ifNull(toString(longitude), '') as longitude,
                        ifNull(toString(nama_site), '') as site,
                        ifNull(toString(lokasi_detail), '') as keterangan_lokasi,
                        ifNull(toString(jam), '') as jam,
                        ifNull(toString(menit), '') as menit,
                        ifNull(toString(nama_lokasi), '') as nama_lokasi,
                        ifNull(toString(nama_detail_lokasi), '') as nama_detail_lokasi
                    FROM beats.aaj_car_all_year_from_dav
                    WHERE (
                        (tanggal_pembuatan IS NOT NULL 
                            AND toDate(tanggal_pembuatan) >= toDate('{$weekStartStr}') 
                            AND toDate(tanggal_pembuatan) < toDate('{$weekEndStr}'))
                        OR (bedraft_date IS NOT NULL 
                            AND toDate(bedraft_date) >= toDate('{$weekStartStr}') 
                            AND toDate(bedraft_date) < toDate('{$weekEndStr}'))
                    )
                    ORDER BY 
                        CASE 
                            WHEN tanggal_pembuatan IS NOT NULL THEN toDateTime(tanggal_pembuatan)
                            WHEN bedraft_date IS NOT NULL THEN toDateTime(bedraft_date)
                            ELSE toDateTime('1970-01-01 00:00:00')
                        END DESC
                    LIMIT 12500
                ";
                
                $resultsInspeksi = $clickhouse->query($sqlInspeksi);
                
                if (!empty($resultsInspeksi) && is_array($resultsInspeksi)) {
                    foreach ($resultsInspeksi as $row) {
                        try {
                            // Convert object to array if needed
                            if (is_object($row)) {
                                $row = (array) $row;
                            }
                            
                            // Map uri_foto to url_foto for formatSapRow compatibility
                            if (isset($row['uri_foto']) && !isset($row['url_foto'])) {
                                $row['url_foto'] = $row['uri_foto'];
                            }
                            
                            // Map keterangan_lokasi to keterangan lokasi for formatSapRow
                            if (isset($row['keterangan_lokasi']) && !isset($row['keterangan lokasi'])) {
                                $row['keterangan lokasi'] = $row['keterangan_lokasi'];
                            }
                            
                            $formattedRow = $this->formatSapRow($row, 'INSPEKSI_HAZARD');
                            $sapData[] = $formattedRow;
                        } catch (Exception $e) {
                            Log::error('Error processing row in aaj_car_all_year_from_dav: ' . $e->getMessage());
                        }
                    }
                }
            } catch (Exception $e) {
                Log::error('Error querying aaj_car_all_year_from_dav: ' . $e->getMessage());
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
                    FROM beats.aaj_database_observasi_from_bep_ytd_only
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
            } catch (Exception $e) {
                Log::error('Error querying tabel_observasi: ' . $e->getMessage());
            }
            
            // 3. Query aaj_vw_car_oak_register_ytd_only
            try {
                $sqlOak = "
                    SELECT 
                        toString(id) as task_number,
                        toString(activity) as aktivitas_pekerjaan,
                        toString(sub_activity) as sub_aktivitas_pekerjaan_oak,
                        toString(tool_type) as tool_pekerjaan_oak,
                        toString(location) as lokasi,
                        toString(detail_location) as detail_lokasi,
                        toString(conclusion) as hasil_oak,
                        toString(tools_observasi) as tools_pengawasan,
                        toString(submit_date) as tanggal_pelaporan,
                        toString(location_description) as keterangan_lokasi,
                        toString(shift) as shift_oak,
                        toString(company_submit_by) as perusahaan_pelapor,
                        toString(submit_by) as pelapor,
                        toString(code_sib) as kode_sib_oak,
                        toString(jabatan_fungsional_submiter) as jabatan_fungsional_pelapor,
                        toString(url_photo) as url_foto,
                        toString(material) as material_oak,
                        toString(conveyance_type) as jenis_alat_angkut_oak,
                        toString(lifting_equipment) as jenis_alat_angkut_oak_2,
                        toString(kode_sid_pelapor) as sid_pelapor,
                        toString(kode_sid_pelapor) as kode_sid_pelapor,
                        toString(kode_sid_team) as kode_sid_team,
                        toString(nama_team) as pic,
                        toString(kode_sid_team) as sid_pic,
                        toString(company_submit_by) as perusahaan_pic,
                        toString(jabatan_fungsional_team) as jabatan_fungsional_pic,
                        toString(tipe) as tipe,
                        ifNull(toString(latitude), '') as latitude,
                        ifNull(toString(longitude), '') as longitude,
                        ifNull(toString(site), '') as site
                    FROM beats.aaj_vw_car_oak_register_ytd_only
                    WHERE submit_date IS NOT NULL
                        AND toDate(submit_date) >= toDate('{$weekStartStr}')
                        AND toDate(submit_date) < toDate('{$weekEndStr}')
                    ORDER BY toDateTime(submit_date) DESC
                    LIMIT 12500
                ";
                
                $resultsOak = $clickhouse->query($sqlOak);
                if (!empty($resultsOak) && is_array($resultsOak)) {
                    foreach ($resultsOak as $row) {
                        // Map hasil_oak to keterangan for formatSapRow compatibility
                        if (isset($row['hasil_oak']) && !isset($row['keterangan'])) {
                            $row['keterangan'] = $row['hasil_oak'];
                        }
                        
                        $sapData[] = $this->formatSapRow($row, 'OAK');
                    }
                }
            } catch (Exception $e) {
                Log::error('Error querying aaj_vw_car_oak_register_ytd_only: ' . $e->getMessage());
            }
            
            // 4. Query coaching from nitip.bep_vw_database_coaching
            try {
                $sqlCoaching = "
                    SELECT 
                        toString(_Task) as task_number,
                        toString(topik_coaching) as aktivitas_pekerjaan,
                        toString(lokasi) as lokasi,
                        toString(detil_lokasi) as detail_lokasi,
                        toString(keterangan_lokasi) as keterangan,
                        toString(Tanggal_Pembuatan) as tanggal_pelaporan,
                        toString(perusahaan_coachee) as perusahaan_pelapor,
                        toString(nama_coachee) as pelapor,
                        toString(nama_coachee) as nama_pelapor,
                        toString(kode_sid_coachee) as sid_pelapor,
                        toString(jabatan_fungsional_coachee) as jabatan_fungsional_pelapor,
                        toString(departement_coachee) as departemen_pelapor,
                        toString(nama_coach) as pic,
                        toString(kode_sid_pelapor) as sid_pic,
                        toString(jabatan_fungsional_coach) as jabatan_fungsional_pic,
                        toString(perusahaan_coach) as perusahaan_pic,
                        toString(departement_coach) as departemen_pic,
                        toString(foto) as url_foto,
                        toString(tools_pengamatan) as tools_pengawasan,
                        toString(catatan_coach) as catatan_tindakan,
                        toString(id_coachee) as nik_pelapor,
                        toString(divisi_coachee) as divisi_pelapor,
                        toString(departement_coachee) as departement_pelapor_karyawan,
                        toString(perusahaan_coachee) as nama_perusahaan_pelapor_karyawan,
                        toString(jabatan_fungsional_coachee) as jabatan_fungsional_karyawan_pelapor,
                        toString(jabatan_struktural_coachee) as jabatan_struktural_pelapor,
                        ifNull(toString(latitude), '') as latitude,
                        ifNull(toString(longitude), '') as longitude,
                        ifNull(toString(site), '') as site
                    FROM nitip.bep_vw_database_coaching
                    WHERE Tanggal_Pembuatan IS NOT NULL
                        AND toDate(Tanggal_Pembuatan) >= toDate('{$weekStartStr}')
                        AND toDate(Tanggal_Pembuatan) < toDate('{$weekEndStr}')
                    ORDER BY toDateTime(Tanggal_Pembuatan) DESC
                    LIMIT 12500
                ";
                
                $resultsCoaching = $clickhouse->query($sqlCoaching);
                if (!empty($resultsCoaching) && is_array($resultsCoaching)) {
                    foreach ($resultsCoaching as $row) {
                        $sapData[] = $this->formatSapRow($row, 'COACHING');
                    }
                }
            } catch (Exception $e) {
                Log::error('Error querying bep_vw_database_coaching: ' . $e->getMessage());
            }
            
            // Sort by tanggal_pelaporan descending
            usort($sapData, function($a, $b) {
                $dateA = $a['tanggal_pelaporan'] ?? '';
                $dateB = $b['tanggal_pelaporan'] ?? '';
                return strcmp($dateB, $dateA);
            });
            
            Log::info('getSapDataFromClickHouse - Method completed, returning ' . count($sapData) . ' SAP records');
            
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
        // Helper function to convert empty string to null
        $cleanValue = function($value) {
            if ($value === '' || $value === null) {
                return null;
            }
            return $value;
        };
        
        // Get coordinates if available
        $latitude = null;
        $longitude = null;
        
        $latValue = $cleanValue($row['latitude'] ?? null);
        $lngValue = $cleanValue($row['longitude'] ?? null);
        
        if (!empty($latValue) && is_numeric($latValue)) {
            $latitude = floatval($latValue);
        }
        if (!empty($lngValue) && is_numeric($lngValue)) {
            $longitude = floatval($lngValue);
        }
        
        // Set jenis_laporan berdasarkan source type jika tidak ada
        $jenisLaporan = $cleanValue($row['jenis_laporan'] ?? null) ?: $sourceType;
        
        // Base data array - use cleanValue to convert empty strings to null
        $taskNumber = $cleanValue($row['task_number'] ?? null);
        
        // Get jam and menit for time formatting
        $jam = $cleanValue($row['jam'] ?? null);
        $menit = $cleanValue($row['menit'] ?? null);
        
        // Format waktu from jam and menit if available
        $waktuFormatted = null;
        if (!empty($jam) && !empty($menit)) {
            $jamInt = intval($jam);
            $menitInt = intval($menit);
            if ($jamInt >= 0 && $jamInt <= 23 && $menitInt >= 0 && $menitInt <= 59) {
                $waktuFormatted = sprintf('%02d:%02d', $jamInt, $menitInt);
            }
        }
        
        // Get nama_lokasi (prefer nama_lokasi over lokasi)
        $namaLokasi = $cleanValue($row['nama_lokasi'] ?? $row['lokasi'] ?? null);
        $namaDetailLokasi = $cleanValue($row['nama_detail_lokasi'] ?? $row['detail_lokasi'] ?? null);
        
        $data = [
            'id' => 'SAP-' . ($taskNumber ?: uniqid()),
            'task_number' => $taskNumber,
            'type' => $jenisLaporan,
            'jenis_laporan' => $jenisLaporan,
            'source_type' => $sourceType, // INSPEKSI_HAZARD, OBSERVASI, OAK, COACHING
            'aktivitas_pekerjaan' => $cleanValue($row['aktivitas_pekerjaan'] ?? null),
            'lokasi' => $namaLokasi, // Use nama_lokasi as primary
            'detail_lokasi' => $namaDetailLokasi, // Use nama_detail_lokasi
            'nama_lokasi' => $namaLokasi, // Add explicit nama_lokasi field
            'nama_detail_lokasi' => $namaDetailLokasi, // Add explicit nama_detail_lokasi field
            'keterangan' => $cleanValue($row['keterangan'] ?? null),
            'tanggal_pelaporan' => $this->ensureValidDate($row['tanggal_pelaporan'] ?? null),
            'jam' => $jam,
            'menit' => $menit,
            'waktu' => $waktuFormatted, // Formatted time as HH:mm
            'perusahaan_pelapor' => $cleanValue($row['perusahaan_pelapor'] ?? null),
            'pelapor' => $cleanValue($row['pelapor'] ?? null),
            'nama_pelapor' => $cleanValue($row['nama_pelapor'] ?? null) ?: $cleanValue($row['pelapor'] ?? null),
            'pic' => $cleanValue($row['pic'] ?? null),
            'url_foto' => $cleanValue($row['url_foto'] ?? null),
            'tools_pengawasan' => $cleanValue($row['tools_pengawasan'] ?? null),
            'catatan_tindakan' => $cleanValue($row['catatan_tindakan'] ?? null),
            'description' => $cleanValue($row['keterangan'] ?? null) ?: $cleanValue($row['aktivitas_pekerjaan'] ?? null) ?: 'No description',
            'severity' => 'medium',
            'status' => 'active',
            'location' => [
                'lat' => $latitude,
                'lng' => $longitude,
            ],
            'detected_at' => $this->ensureValidDate($row['tanggal_pelaporan'] ?? null),
            'site' => $cleanValue($row['site'] ?? null),
            'perusahaan' => $cleanValue($row['perusahaan_pelapor'] ?? null),
            'sid_pelapor' => $cleanValue($row['sid_pelapor'] ?? null),
            'jabatan_fungsional_pelapor' => $cleanValue($row['jabatan_fungsional_pelapor'] ?? null),
            'departemen_pelapor' => $cleanValue($row['departemen_pelapor'] ?? null),
            'sid_pic' => $cleanValue($row['sid_pic'] ?? null),
            'jabatan_fungsional_pic' => $cleanValue($row['jabatan_fungsional_pic'] ?? null),
            'perusahaan_pic' => $cleanValue($row['perusahaan_pic'] ?? null),
            'departemen_pic' => $cleanValue($row['departemen_pic'] ?? null),
            'nik_pelapor' => $cleanValue($row['nik_pelapor'] ?? null),
            'divisi_pelapor' => $cleanValue($row['divisi_pelapor'] ?? null),
            'jabatan_fungsional_karyawan_pelapor' => $cleanValue($row['jabatan_fungsional_karyawan_pelapor'] ?? null),
            'jabatan_struktural_pelapor' => $cleanValue($row['jabatan_struktural_pelapor'] ?? null),
        ];
        
        // Apply field renames for INSPEKSI_HAZARD source type
        if ($sourceType === 'INSPEKSI_HAZARD') {
            // Rename fields as specified - these will be the primary field names sent to frontend
            $data['#Task Number'] = $data['task_number'];
            $data['tanggal pelaporan'] = $data['tanggal_pelaporan'];
            $data['keterangan lokasi'] = $row['keterangan lokasi'] ?? $data['detail_lokasi'] ?? null;
            $data['uri foto'] = $data['url_foto'];
            $data['pelapor'] = $data['pelapor'];
            $data['pic'] = $data['pic'];
            $data['perusahaan pic'] = $data['perusahaan_pic'];
            $data['perusahaan pelapor'] = $data['perusahaan_pelapor'];
            $data['sid pic'] = $data['sid_pic'];
            $data['jabatan fungsional pic'] = $data['jabatan_fungsional_pic'];
            $data['jabatan fungsional pelapor'] = $data['jabatan_fungsional_pelapor'];
            $data['site'] = $data['site'];
            $data['lokasi'] = $data['lokasi'];
            $data['detail lokasi'] = $data['detail_lokasi'];
            $data['tools pengawasan'] = $data['tools_pengawasan'];
            $data['departemen pelapor'] = $data['departemen_pelapor'];
            $data['departemen pic'] = $data['departemen_pic'];
        }
        
        return $data;
    }

    /**
     * Ensure date value is valid and in format that JavaScript can parse
     */
    private function ensureValidDate($dateValue)
    {
        if (empty($dateValue) || $dateValue === '' || $dateValue === null) {
            return null;
        }
        
        // If already a valid date string, try to normalize format
        if (is_string($dateValue)) {
            // Remove any extra whitespace
            $dateValue = trim($dateValue);
            
            // If empty after trim, return null
            if ($dateValue === '') {
                return null;
            }
            
            // Try to parse and reformat to ensure it's valid
            try {
                // Try multiple date formats that ClickHouse might return
                $formats = [
                    'Y-m-d H:i:s',
                    'Y-m-d\TH:i:s',
                    'Y-m-d\TH:i:s.u',
                    'Y-m-d\TH:i:s.v',
                    'Y-m-d H:i:s.u',
                    'Y-m-d',
                ];
                
                foreach ($formats as $format) {
                    try {
                        $date = \DateTime::createFromFormat($format, $dateValue);
                        if ($date !== false) {
                            return $date->format('Y-m-d H:i:s');
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                
                // If format matching fails, try standard DateTime parsing
                $date = new \DateTime($dateValue);
                return $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // If all parsing fails, return original value (might be in different format that JS can handle)
                Log::warning('Could not parse date value: ' . $dateValue . ' - Error: ' . $e->getMessage());
                return $dateValue;
            }
        }
        
        return $dateValue;
    }

    /**
     * Get GR detections from PostgreSQL
     */
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
            Log::error('Error fetching GR detections from MySQL: ' . $e->getMessage());
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
                Log::warning('SSH tunnel is not active. Cannot count valid GR.');
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
            Log::error('Error counting valid GR: ' . $e->getMessage());
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
     * Get current shift based on current hour
     */
    private function getCurrentShift()
    {
        $hour = Carbon::now()->hour;
        
        // Shift 1: 06:00 - 14:00
        // Shift 2: 14:00 - 22:00
        // Shift 3: 22:00 - 06:00
        if ($hour >= 6 && $hour < 14) {
            return '1';
        } elseif ($hour >= 14 && $hour < 22) {
            return '2';
        } else {
            return '3';
        }
    }

    /**
     * Get SAP data from ClickHouse with limit
     * API endpoint untuk mengambil SAP data dengan limit 500
     */
    public function getSapDataApi(Request $request)
    {
        try {
            $limit = $request->input('limit', 500);
            $limit = min($limit, 500); // Max 500
            
            $clickhouse = new ClickHouseService();
            
            if (!$clickhouse->isConnected()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ClickHouse is not connected',
                    'data' => []
                ], 500);
            }
            
            $sapData = [];
            
            // Query all SAP data without week filter, just limit
            // 1. Query aaj_car_all_year_from_dav
            try {
                $sqlInspeksi = "
                    SELECT 
                        toString(id) as task_number,
                        ifNull(toString(jenis_laporan), 'INSPEKSI_HAZARD') as jenis_laporan,
                        ifNull(toString(deskripsi), '') as aktivitas_pekerjaan,
                        ifNull(toString(nama_lokasi), '') as lokasi,
                        ifNull(toString(nama_detail_lokasi), '') as detail_lokasi,
                        ifNull(toString(deskripsi), '') as keterangan,
                        ifNull(toString(tanggal_pembuatan), toString(bedraft_date)) as tanggal_pelaporan,
                        ifNull(toString(perusahaan_pelapor), '') as perusahaan_pelapor,
                        ifNull(toString(nama_pelapor), '') as pelapor,
                        ifNull(toString(sid_pelapor), '') as sid_pelapor,
                        ifNull(toString(jabatan_fungsional_pelapor), '') as jabatan_fungsional_pelapor,
                        ifNull(toString(departemen_pelapor), '') as departemen_pelapor,
                        ifNull(toString(nama_pic), '') as pic,
                        ifNull(toString(sid_pic), '') as sid_pic,
                        ifNull(toString(jabatan_fungsional_pic), '') as jabatan_fungsional_pic,
                        ifNull(toString(perusahaan_pic), '') as perusahaan_pic,
                        ifNull(toString(departemen_pic), '') as departemen_pic,
                        ifNull(toString(url_photo), '') as uri_foto,
                        ifNull(toString(name_tools_observation), '') as tools_pengawasan,
                        ifNull(toString(tindakan), '') as catatan_tindakan,
                        ifNull(toString(id_pelapor), '') as nik_pelapor,
                        ifNull(toString(nama_pelapor), '') as nama_pelapor,
                        ifNull(toString(perusahaan_pelapor), '') as nama_perusahaan_pelapor_karyawan,
                        ifNull(toString(jabatan_fungsional_pelapor), '') as jabatan_fungsional_karyawan_pelapor,
                        ifNull(toString(latitude), '') as latitude,
                        ifNull(toString(longitude), '') as longitude,
                        ifNull(toString(nama_site), '') as site,
                        ifNull(toString(lokasi_detail), '') as keterangan_lokasi
                    FROM beats.aaj_car_all_year_from_dav
                    WHERE latitude IS NOT NULL 
                        AND longitude IS NOT NULL
                        AND latitude != ''
                        AND longitude != ''
                    ORDER BY 
                        CASE 
                            WHEN tanggal_pembuatan IS NOT NULL THEN toDateTime(tanggal_pembuatan)
                            WHEN bedraft_date IS NOT NULL THEN toDateTime(bedraft_date)
                            ELSE toDateTime('1970-01-01 00:00:00')
                        END DESC
                    LIMIT {$limit}
                ";
                
                $resultsInspeksi = $clickhouse->query($sqlInspeksi);
                
                if (!empty($resultsInspeksi) && is_array($resultsInspeksi)) {
                    foreach ($resultsInspeksi as $row) {
                        try {
                            if (is_object($row)) {
                                $row = (array) $row;
                            }
                            
                            if (isset($row['uri_foto']) && !isset($row['url_foto'])) {
                                $row['url_foto'] = $row['uri_foto'];
                            }
                            
                            if (isset($row['keterangan_lokasi']) && !isset($row['keterangan lokasi'])) {
                                $row['keterangan lokasi'] = $row['keterangan_lokasi'];
                            }
                            
                            $formattedRow = $this->formatSapRow($row, 'INSPEKSI_HAZARD');
                            $sapData[] = $formattedRow;
                        } catch (Exception $e) {
                            Log::error('Error processing row in aaj_car_all_year_from_dav: ' . $e->getMessage());
                        }
                    }
                }
            } catch (Exception $e) {
                Log::error('Error querying aaj_car_all_year_from_dav: ' . $e->getMessage());
            }
            
            // 2. Query tabel_observasi
            try {
                $remainingLimit = $limit - count($sapData);
                if ($remainingLimit > 0) {
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
                            toString(`jabatan fungsional pelapor`) as jabatan_fungsional_karyawan_pelapor,
                            ifNull(toString(latitude), '') as latitude,
                            ifNull(toString(longitude), '') as longitude
                        FROM beats.aaj_database_observasi_from_bep_ytd_only
                        WHERE latitude IS NOT NULL 
                            AND longitude IS NOT NULL
                            AND latitude != ''
                            AND longitude != ''
                        ORDER BY toDateTime(`tanggal pelaporan`) DESC
                        LIMIT {$remainingLimit}
                    ";
                    
                    $resultsObservasi = $clickhouse->query($sqlObservasi);
                    if (!empty($resultsObservasi) && is_array($resultsObservasi)) {
                        foreach ($resultsObservasi as $row) {
                            $sapData[] = $this->formatSapRow($row, 'OBSERVASI');
                        }
                    }
                }
            } catch (Exception $e) {
                Log::error('Error querying tabel_observasi: ' . $e->getMessage());
            }
            
            // 3. Query aaj_vw_car_oak_register_ytd_only
            try {
                $remainingLimit = $limit - count($sapData);
                if ($remainingLimit > 0) {
                    $sqlOak = "
                        SELECT 
                            toString(id) as task_number,
                            toString(activity) as aktivitas_pekerjaan,
                            toString(sub_activity) as sub_aktivitas_pekerjaan_oak,
                            toString(tool_type) as tool_pekerjaan_oak,
                            toString(location) as lokasi,
                            toString(detail_location) as detail_lokasi,
                            toString(conclusion) as hasil_oak,
                            toString(tools_observasi) as tools_pengawasan,
                            toString(submit_date) as tanggal_pelaporan,
                            toString(location_description) as keterangan_lokasi,
                            toString(shift) as shift_oak,
                            toString(company_submit_by) as perusahaan_pelapor,
                            toString(submit_by) as pelapor,
                            toString(code_sib) as kode_sib_oak,
                            toString(jabatan_fungsional_submiter) as jabatan_fungsional_pelapor,
                            toString(url_photo) as url_foto,
                            toString(material) as material_oak,
                            toString(conveyance_type) as jenis_alat_angkut_oak,
                            toString(lifting_equipment) as jenis_alat_angkut_oak_2,
                            toString(kode_sid_pelapor) as sid_pelapor,
                            toString(kode_sid_pelapor) as kode_sid_pelapor,
                            toString(kode_sid_team) as kode_sid_team,
                            toString(nama_team) as pic,
                            toString(kode_sid_team) as sid_pic,
                            toString(company_submit_by) as perusahaan_pic,
                            toString(jabatan_fungsional_team) as jabatan_fungsional_pic,
                            toString(tipe) as tipe,
                            ifNull(toString(latitude), '') as latitude,
                            ifNull(toString(longitude), '') as longitude,
                            ifNull(toString(site), '') as site
                        FROM beats.aaj_vw_car_oak_register_ytd_only
                        WHERE latitude IS NOT NULL 
                            AND longitude IS NOT NULL
                            AND latitude != ''
                            AND longitude != ''
                        ORDER BY toDateTime(submit_date) DESC
                        LIMIT {$remainingLimit}
                    ";
                    
                    $resultsOak = $clickhouse->query($sqlOak);
                    if (!empty($resultsOak) && is_array($resultsOak)) {
                        foreach ($resultsOak as $row) {
                            if (isset($row['hasil_oak']) && !isset($row['keterangan'])) {
                                $row['keterangan'] = $row['hasil_oak'];
                            }
                            $sapData[] = $this->formatSapRow($row, 'OAK');
                        }
                    }
                }
            } catch (Exception $e) {
                Log::error('Error querying aaj_vw_car_oak_register_ytd_only: ' . $e->getMessage());
            }
            
            // 4. Query coaching from nitip.bep_vw_database_coaching
            try {
                $remainingLimit = $limit - count($sapData);
                if ($remainingLimit > 0) {
                    $sqlCoaching = "
                        SELECT 
                            toString(_Task) as task_number,
                            toString(topik_coaching) as aktivitas_pekerjaan,
                            toString(lokasi) as lokasi,
                            toString(detil_lokasi) as detail_lokasi,
                            toString(keterangan_lokasi) as keterangan,
                            toString(Tanggal_Pembuatan) as tanggal_pelaporan,
                            toString(perusahaan_coachee) as perusahaan_pelapor,
                            toString(nama_coachee) as pelapor,
                            toString(nama_coachee) as nama_pelapor,
                            toString(kode_sid_coachee) as sid_pelapor,
                            toString(jabatan_fungsional_coachee) as jabatan_fungsional_pelapor,
                            toString(departement_coachee) as departemen_pelapor,
                            toString(nama_coach) as pic,
                            toString(kode_sid_pelapor) as sid_pic,
                            toString(jabatan_fungsional_coach) as jabatan_fungsional_pic,
                            toString(perusahaan_coach) as perusahaan_pic,
                            toString(departement_coach) as departemen_pic,
                            toString(foto) as url_foto,
                            toString(tools_pengamatan) as tools_pengawasan,
                            toString(catatan_coach) as catatan_tindakan,
                            toString(id_coachee) as nik_pelapor,
                            toString(divisi_coachee) as divisi_pelapor,
                            toString(departement_coachee) as departement_pelapor_karyawan,
                            toString(perusahaan_coachee) as nama_perusahaan_pelapor_karyawan,
                            toString(jabatan_fungsional_coachee) as jabatan_fungsional_karyawan_pelapor,
                            toString(jabatan_struktural_coachee) as jabatan_struktural_pelapor,
                            ifNull(toString(latitude), '') as latitude,
                            ifNull(toString(longitude), '') as longitude,
                            ifNull(toString(site), '') as site
                        FROM nitip.bep_vw_database_coaching
                        WHERE latitude IS NOT NULL 
                            AND longitude IS NOT NULL
                            AND latitude != ''
                            AND longitude != ''
                        ORDER BY toDateTime(Tanggal_Pembuatan) DESC
                        LIMIT {$remainingLimit}
                    ";
                    
                    $resultsCoaching = $clickhouse->query($sqlCoaching);
                    if (!empty($resultsCoaching) && is_array($resultsCoaching)) {
                        foreach ($resultsCoaching as $row) {
                            $sapData[] = $this->formatSapRow($row, 'COACHING');
                        }
                    }
                }
            } catch (Exception $e) {
                Log::error('Error querying bep_vw_database_coaching: ' . $e->getMessage());
            }
            
            // Sort by tanggal_pelaporan descending
            usort($sapData, function($a, $b) {
                $dateA = $a['tanggal_pelaporan'] ?? '';
                $dateB = $b['tanggal_pelaporan'] ?? '';
                return strcmp($dateB, $dateA);
            });
            
            // Limit to requested limit
            $sapData = array_slice($sapData, 0, $limit);
            
            return response()->json([
                'success' => true,
                'message' => 'SAP data retrieved successfully',
                'data' => $sapData,
                'count' => count($sapData)
            ]);
            
        } catch (Exception $e) {
            Log::error('Error getting SAP data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving SAP data: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get CCTV by coverage location from cctv_coverage table
     * API endpoint untuk mengambil CCTV berdasarkan coverage_lokasi
     */
    public function getCctvByCoverageLocation(Request $request)
    {
        try {
            $lokasiName = $request->input('lokasi_name');
            
            if (!$lokasiName) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lokasi name is required',
                    'data' => []
                ], 400);
            }
            
            // Normalize lokasiName untuk matching (remove spaces, convert to lowercase)
            $normalizedLokasi = strtolower(trim($lokasiName));
            $normalizedLokasiNoSpaces = str_replace(' ', '', $normalizedLokasi);
            
            // Ambil CCTV dari cctv_coverage berdasarkan coverage_lokasi yang match dengan lokasiName
            // Join dengan cctv_data_bmo2 untuk mendapatkan data lengkap CCTV
            $cctvList = CctvCoverage::select(
                    'cctv_coverage.id as coverage_id',
                    'cctv_coverage.id_cctv',
                    'cctv_coverage.coverage_lokasi',
                    'cctv_coverage.coverage_detail_lokasi',
                    'cctv_coverage.kategori_aktivitas',
                    'cctv_coverage.kategori_area',
                    'cctv_data_bmo2.id',
                    'cctv_data_bmo2.no_cctv',
                    'cctv_data_bmo2.nama_cctv',
                    'cctv_data_bmo2.kondisi',
                    'cctv_data_bmo2.status',
                    'cctv_data_bmo2.lokasi_pemasangan',
                    'cctv_data_bmo2.longitude',
                    'cctv_data_bmo2.latitude',
                    'cctv_data_bmo2.site',
                    'cctv_data_bmo2.perusahaan',
                    'cctv_data_bmo2.control_room',
                    'cctv_data_bmo2.connected'
                )
                ->leftJoin('cctv_data_bmo2', 'cctv_coverage.id_cctv', '=', 'cctv_data_bmo2.id')
                ->where(function($q) use ($lokasiName, $normalizedLokasi, $normalizedLokasiNoSpaces) {
                    // Match dengan coverage_lokasi (flexible matching)
                    // Case insensitive matching
                    $q->whereRaw("LOWER(cctv_coverage.coverage_lokasi) LIKE ?", ['%' . $normalizedLokasi . '%'])
                      // Remove parentheses and match
                      ->orWhereRaw("LOWER(REPLACE(REPLACE(cctv_coverage.coverage_lokasi, '(', ''), ')', '')) LIKE ?", ['%' . $normalizedLokasi . '%'])
                      // Remove all spaces and match
                      ->orWhereRaw("LOWER(REPLACE(REPLACE(REPLACE(cctv_coverage.coverage_lokasi, '(', ''), ')', ''), ' ', '')) LIKE ?", ['%' . $normalizedLokasiNoSpaces . '%'])
                      // Reverse match - check if lokasiName contains coverage_lokasi (after normalization)
                      ->orWhereRaw("? LIKE CONCAT('%', LOWER(REPLACE(REPLACE(cctv_coverage.coverage_lokasi, '(', ''), ')', '')), '%')", [$normalizedLokasi])
                      ->orWhereRaw("? LIKE CONCAT('%', LOWER(REPLACE(REPLACE(REPLACE(cctv_coverage.coverage_lokasi, '(', ''), ')', ''), ' ', '')), '%')", [$normalizedLokasiNoSpaces]);
                })
                ->whereNotNull('cctv_data_bmo2.id') // Pastikan CCTV data exists
                ->get();
            
            // Format data untuk frontend
            $formattedData = $cctvList->map(function ($item) {
                // Pastikan kondisi dan status terkirim dengan benar
                $kondisi = $item->kondisi ?? null;
                $status = $item->status ?? null;
                $connected = $item->connected ?? null;
                
                return [
                    'id' => $item->id,
                    'no_cctv' => $item->no_cctv ?? null,
                    'nomor_cctv' => $item->no_cctv ?? null,
                    'name' => $item->nama_cctv ?? 'CCTV ' . $item->id,
                    'nama_cctv' => $item->nama_cctv ?? null,
                    'location' => ($item->longitude && $item->latitude) 
                        ? [(float) $item->longitude, (float) $item->latitude] 
                        : null,
                    'status' => $status ?? $kondisi ?? 'Unknown',
                    'kondisi' => $kondisi,
                    'site' => $item->site ?? null,
                    'perusahaan' => $item->perusahaan ?? null,
                    'lokasi_pemasangan' => $item->lokasi_pemasangan ?? null,
                    'control_room' => $item->control_room ?? null,
                    'coverage_lokasi' => $item->coverage_lokasi ?? null,
                    'coverage_detail_lokasi' => $item->coverage_detail_lokasi ?? null,
                    'connected' => $connected,
                ];
            })->toArray();
            
            return response()->json([
                'success' => true,
                'message' => 'CCTV data retrieved successfully',
                'data' => $formattedData,
                'count' => count($formattedData)
            ]);
            
        } catch (Exception $e) {
            Log::error('Error getting CCTV by coverage location: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving CCTV data: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Generate AI-based recommendations for Control Room Supervisor
     * Based on Risk Matrix Summary, CCTV data, and SAP reports
     */
    public function generateControlRoomRecommendations(Request $request)
    {
        try {
            $riskSummary = $request->input('risk_summary');
            $cctvList = $request->input('cctv_list', []);
            $sapReports = $request->input('sap_reports', []);
            $areaInfo = $request->input('area_info', []);

            if (!$riskSummary) {
                return response()->json([
                    'success' => false,
                    'message' => 'Risk summary is required',
                    'recommendations' => []
                ], 400);
            }

            // Prepare data for AI prompt
            $riskLevel = $riskSummary['risk_level'] ?? 'NORMAL';
            $hasSapReport = $riskSummary['has_sap_report'] ?? false;
            $hasOnlineCctv = $riskSummary['has_online_cctv'] ?? false;
            $isHighRiskArea = $riskSummary['is_high_risk_area'] ?? false;
            $cctvCount = count($cctvList);
            $onlineCctvCount = 0;
            $offlineCctvCount = 0;
            $sapCount = count($sapReports);

            // Count online/offline CCTV
            foreach ($cctvList as $cctv) {
                $kondisi = strtolower($cctv['kondisi'] ?? $cctv['status'] ?? '');
                $isOnline = $kondisi === 'baik' || $kondisi === 'online' || 
                           strtolower($cctv['status'] ?? '') === 'live view' ||
                           strtolower($cctv['connected'] ?? '') === 'yes' ||
                           ($cctv['status'] ?? null) === 1 ||
                           ($cctv['is_online'] ?? false) === true ||
                           ($cctv['status_online'] ?? null) === 1;
                
                if ($isOnline) {
                    $onlineCctvCount++;
                } else {
                    $offlineCctvCount++;
                }
            }

            // Get SAP report types and detailed info
            $sapTypes = [];
            $sapDetails = [];
            foreach ($sapReports as $sap) {
                $type = $sap['jenis_laporan'] ?? $sap['source_type'] ?? $sap['type'] ?? 'SAP';
                if (!in_array($type, $sapTypes)) {
                    $sapTypes[] = $type;
                }
                
                // Collect detailed SAP info
                $taskNumber = $sap['task_number'] ?? $sap['id'] ?? 'N/A';
                
                // Prefer nama_lokasi over lokasi, and nama_detail_lokasi over detail_lokasi
                $namaLokasi = $sap['nama_lokasi'] ?? $sap['lokasi'] ?? 'N/A';
                $namaDetailLokasi = $sap['nama_detail_lokasi'] ?? $sap['detail_lokasi'] ?? null;
                
                // Use nama_lokasi as primary, fallback to nama_detail_lokasi if nama_lokasi is empty
                $lokasi = !empty($namaLokasi) && $namaLokasi !== 'N/A' ? $namaLokasi : ($namaDetailLokasi ?? 'N/A');
                
                // Get deskripsi/keterangan hazard
                $deskripsi = $sap['keterangan'] ?? $sap['deskripsi'] ?? $sap['aktivitas_pekerjaan'] ?? $sap['description'] ?? null;
                
                // Get waktu from formatted waktu field, or from jam:menit, or from tanggal
                $waktu = $sap['waktu'] ?? '';
                if (empty($waktu)) {
                    $jam = $sap['jam'] ?? null;
                    $menit = $sap['menit'] ?? null;
                    if (!empty($jam) && !empty($menit)) {
                        $jamInt = intval($jam);
                        $menitInt = intval($menit);
                        if ($jamInt >= 0 && $jamInt <= 23 && $menitInt >= 0 && $menitInt <= 59) {
                            $waktu = sprintf('%02d:%02d', $jamInt, $menitInt);
                        }
                    }
                }
                
                // If still no waktu, try from tanggal
                if (empty($waktu)) {
                    $tanggal = $sap['tanggal_pelaporan'] ?? $sap['detected_at'] ?? null;
                    if ($tanggal) {
                        try {
                            $dateTime = new \Carbon\Carbon($tanggal);
                            $waktu = $dateTime->format('H:i');
                        } catch (\Exception $e) {
                            $waktu = '';
                        }
                    }
                }
                
                $sapDetails[] = [
                    'task_number' => $taskNumber,
                    'jenis' => $type,
                    'lokasi' => $lokasi,
                    'nama_lokasi' => $namaLokasi,
                    'nama_detail_lokasi' => $namaDetailLokasi,
                    'deskripsi' => $deskripsi,
                    'waktu' => $waktu,
                    'tanggal' => $sap['tanggal_pelaporan'] ?? $sap['detected_at'] ?? null
                ];
            }

            $areaName = $areaInfo['lokasi'] ?? $areaInfo['nama_lokasi'] ?? 'Area Kerja';
            $site = $areaInfo['site'] ?? 'N/A';
            $perusahaan = $areaInfo['perusahaan'] ?? 'N/A';
            $today = \Carbon\Carbon::now()->format('d F Y');

            // Prepare detailed CCTV info
            $cctvDetails = [];
            $onlineCctvList = [];
            $offlineCctvList = [];
            
            foreach ($cctvList as $cctv) {
                $cctvNo = $cctv['no_cctv'] ?? $cctv['nomor_cctv'] ?? $cctv['nama_cctv'] ?? 'N/A';
                $cctvName = $cctv['nama_cctv'] ?? $cctv['no_cctv'] ?? 'CCTV';
                $lokasiPemasangan = $cctv['lokasi_pemasangan'] ?? $cctv['coverage_detail_lokasi'] ?? $cctv['coverage_lokasi'] ?? 'N/A';
                $kondisi = strtolower($cctv['kondisi'] ?? $cctv['status'] ?? '');
                $isOnline = $kondisi === 'baik' || $kondisi === 'online' || 
                           strtolower($cctv['status'] ?? '') === 'live view' ||
                           strtolower($cctv['connected'] ?? '') === 'yes' ||
                           ($cctv['status'] ?? null) === 1 ||
                           ($cctv['is_online'] ?? false) === true ||
                           ($cctv['status_online'] ?? null) === 1;
                
                $cctvInfo = [
                    'no_cctv' => $cctvNo,
                    'nama' => $cctvName,
                    'lokasi' => $lokasiPemasangan,
                    'status' => $isOnline ? 'Online' : 'Offline'
                ];
                
                if ($isOnline) {
                    $onlineCctvList[] = $cctvInfo;
                } else {
                    $offlineCctvList[] = $cctvInfo;
                }
            }

            // Build AI prompt with Context → Insight → Action format
            $prompt = "Anda adalah asisten AI untuk Pengawas Control Room di perusahaan pertambangan. 

Buatkan rekomendasi tindakan menggunakan pendekatan \"Context → Insight → Action\" dengan format yang sangat spesifik dan detail.

TANGGAL: {$today}

DATA AREA KERJA:
- Nama Area: {$areaName}
- Site: {$site}
- Perusahaan: {$perusahaan}
- Risk Level: {$riskLevel}
- Area Highrisk: " . ($isHighRiskArea ? 'Ya' : 'Tidak') . "
- Terdapat Laporan SAP: " . ($hasSapReport ? 'Ya' : 'Tidak') . "
- CCTV Kondisi Online: " . ($hasOnlineCctv ? 'Ya' : 'Tidak') . "

DATA CCTV (Total: {$cctvCount}, Online: {$onlineCctvCount}, Offline: {$offlineCctvCount}):";

            if (count($onlineCctvList) > 0) {
                $prompt .= "\n\nCCTV ONLINE:";
                foreach (array_slice($onlineCctvList, 0, 15) as $cctv) {
                    $prompt .= "\n- {$cctv['no_cctv']} ({$cctv['nama']}) - Lokasi: {$cctv['lokasi']}";
                }
            }

            if (count($offlineCctvList) > 0) {
                $prompt .= "\n\nCCTV OFFLINE:";
                foreach (array_slice($offlineCctvList, 0, 10) as $cctv) {
                    $prompt .= "\n- {$cctv['no_cctv']} ({$cctv['nama']}) - Lokasi: {$cctv['lokasi']}";
                }
            }

            if (count($sapDetails) > 0) {
                $prompt .= "\n\nDATA LAPORAN SAP HARI INI (Total: {$sapCount}):";
                foreach (array_slice($sapDetails, 0, 10) as $sap) {
                    $waktuStr = $sap['waktu'] ? " pukul {$sap['waktu']}" : "";
                    $lokasiStr = $sap['lokasi'] ?? 'N/A';
                    $deskripsiStr = $sap['deskripsi'] ? " - Deskripsi: {$sap['deskripsi']}" : "";
                    $prompt .= "\n- {$sap['jenis']} #{$sap['task_number']}{$waktuStr} - Lokasi: {$lokasiStr}{$deskripsiStr}";
                }
            }

            $prompt .= "\n\nINSTRUKSI FORMAT REKOMENDASI:
Gunakan pendekatan \"Context → Insight → Action\":

1. CONTEXT: Ringkaskan fakta utama dari data saat ini (sebutkan nomor CCTV spesifik, nomor SAP, waktu, lokasi, aktivitas)
2. INSIGHT: Interpretasi risiko atau peluang berdasarkan fakta tersebut
3. ACTION: Rekomendasi spesifik, bernada arahan, dan relevan dengan kondisi nyata

SETIAP REKOMENDASI HARUS:
- Menggunakan nomor CCTV spesifik (contoh: LMO-BM-0014, BMO-FAD-0002)
- Menyebutkan nomor SAP jika ada (contoh: HAZARD #7985899)
- Menyebutkan waktu jika tersedia (contoh: pukul 04:11)
- Menyebutkan lokasi spesifik (contoh: Pit PQRT, Area Kritis B 56, (B8) Pit CD)
- Menyebutkan deskripsi hazard/aktivitas spesifik yang dilaporkan (contoh: 'Jalan undulating', 'FDT253 mendonga ke atas', 'Drill & Blast', 'Welding HDPE')
- Menjelaskan apa hazard/aktivitas yang terjadi berdasarkan deskripsi dari database
- Menggunakan kalimat deskriptif lengkap yang dapat langsung ditindaklanjuti
- Fokus pada tindakan yang dapat dilakukan oleh Pengawas Control Room melalui CCTV monitoring

CONTOH FORMAT YANG DIINGINKAN:
\"Terdapat Temuan HAZARD #7985899 terkait 'Jalan undulating' yang dilaporkan pukul 04:11 di (B8) Pit CD, pastikan temuan (jika ada) telah ditindaklanjuti oleh tim lapangan dan tidak ada kondisi yang memerlukan perhatian khusus.\"

\"Terdapat Temuan HAZARD #6630354 terkait 'FDT253 mendonga ke atas' yang dilaporkan pukul 15:37 di (B 56) Pit QSV, pastikan temuan terkait akses jalan hauling OB telah ditindaklanjuti dan kondisi jalan sudah diperbaiki.\"

\"Fokuskan pemantauan real-time pada aktivitas Drill & Blast di Pit PQRT dan Pit OS, karena kedua lokasi tersebut memiliki CCTV aktif (LMO-BM-0014 dan LMO-BM-0025) dan termasuk dalam zona kritis meskipun tidak diklasifikasikan sebagai high-risk hari ini.\"

\"Amati proses Welding HDPE dan Penyambungan Pipa HDPE melalui kamera LMO-BM-0006, karena aktivitas ini rentan terhadap bahaya panas dan kebocoran — pastikan APD dan prosedur hot work diterapkan.\"

\"Gunakan kamera PTZ (misal: LMO-FAD-0013 dan BMO-FAD-0002) untuk melakukan patroli visual rutin terhadap area Dumping Diatas Air di Pit L5 dan Pit OS, mengingat potensi longsor material basah.\"

\"Dokumentasikan penggunaan seluruh {$cctvCount} CCTV dalam shift ini sebagai bukti utilitas sistem, khususnya untuk kamera yang memantau aktivitas non-rutin seperti Pembongkaran Bahan Peledak (LMO-BM-0014).\"

FORMAT OUTPUT JSON:
[
  {
    \"priority\": \"HIGH|MEDIUM|LOW\",
    \"action\": \"Kalimat lengkap dengan Context → Insight → Action, menyebutkan nomor CCTV, nomor SAP, waktu, lokasi, dan aktivitas spesifik\"
  }
]

PENTING: 
- JANGAN gunakan format pendek seperti \"Pengawas Control Room wajib melakukan P2H Status CCTV setiap awal shift\" atau \"Pengawas Control Room monitoring aktivitas Highrisk\"
- SETIAP rekomendasi HARUS menggunakan format Context → Insight → Action yang lengkap dan detail
- SETIAP rekomendasi HARUS menyebutkan nomor CCTV spesifik, lokasi spesifik, atau nomor SAP jika tersedia
- SETIAP rekomendasi HARUS minimal 80 karakter dan menjelaskan konteks, insight, dan action secara lengkap

Buatkan 4-6 rekomendasi yang sangat spesifik dan detail. Prioritaskan berdasarkan tingkat risiko. Gunakan bahasa Indonesia yang jelas dan profesional.

Hanya return JSON array, tanpa markdown, tanpa penjelasan tambahan.";

            // Call AI service - using Gemini only
            $aiService = new QwenAIService();
            $aiResponse = $aiService->chat($prompt, []); // Empty conversation history, use Gemini only

            // Parse AI response
            $recommendations = [];
            $aiUsed = false;
            
            if ($aiResponse) {
                // Clean the response - remove markdown code blocks if present
                $cleanedResponse = $aiResponse;
                $cleanedResponse = preg_replace('/```json\s*/', '', $cleanedResponse);
                $cleanedResponse = preg_replace('/```\s*/', '', $cleanedResponse);
                $cleanedResponse = trim($cleanedResponse);
                
                // Try multiple methods to extract JSON
                $jsonMatch = [];
                if (preg_match('/\[[\s\S]*\]/', $cleanedResponse, $jsonMatch)) {
                    $recommendations = json_decode($jsonMatch[0], true);
                } else {
                    // Fallback: try to parse entire response as JSON
                    $recommendations = json_decode($cleanedResponse, true);
                }

                // Validate recommendations format and content
                if (is_array($recommendations) && count($recommendations) > 0) {
                    // Check if recommendations have proper structure
                    $isValid = true;
                    foreach ($recommendations as $rec) {
                        if (!isset($rec['action']) || empty($rec['action']) || 
                            !isset($rec['priority']) || 
                            strlen($rec['action']) < 80) { // Minimum 80 characters for detailed format
                            $isValid = false;
                            break;
                        }
                        
                        // Check if it's using old format (too generic)
                        $actionLower = strtolower($rec['action']);
                        $oldFormatPatterns = [
                            'pengawas control room wajib melakukan p2h',
                            'pengawas control room monitoring aktivitas highrisk',
                            'pengawas control room wajib melakukan pemeriksaan'
                        ];
                        
                        $hasOldFormat = false;
                        foreach ($oldFormatPatterns as $pattern) {
                            if (strpos($actionLower, $pattern) === 0 && strlen($rec['action']) < 120) {
                                $hasOldFormat = true;
                                break;
                            }
                        }
                        
                        if ($hasOldFormat) {
                            $isValid = false;
                            break;
                        }
                    }
                    
                    if ($isValid) {
                        $aiUsed = true;
                    } else {
                        // AI response format is invalid, use fallback
                        Log::warning('AI response format invalid, using fallback', [
                            'response' => substr($aiResponse, 0, 500),
                            'parsed' => $recommendations
                        ]);
                        $recommendations = $this->generateFallbackRecommendations(
                            $riskLevel, $hasSapReport, $hasOnlineCctv, $isHighRiskArea, 
                            $cctvCount, $onlineCctvCount, $offlineCctvCount, $sapCount, $areaName,
                            $cctvList, $sapReports
                        );
                    }
                } else {
                    // If AI didn't return proper format, create fallback recommendations
                    Log::warning('AI response not parseable, using fallback', [
                        'response' => substr($aiResponse, 0, 500)
                    ]);
                    $recommendations = $this->generateFallbackRecommendations(
                        $riskLevel, $hasSapReport, $hasOnlineCctv, $isHighRiskArea, 
                        $cctvCount, $onlineCctvCount, $offlineCctvCount, $sapCount, $areaName,
                        $cctvList, $sapReports
                    );
                }
            } else {
                // Fallback if AI service fails
                Log::warning('AI service returned empty response, using fallback');
                $recommendations = $this->generateFallbackRecommendations(
                    $riskLevel, $hasSapReport, $hasOnlineCctv, $isHighRiskArea, 
                    $cctvCount, $onlineCctvCount, $offlineCctvCount, $sapCount, $areaName,
                    $cctvList, $sapReports
                );
            }

            return response()->json([
                'success' => true,
                'recommendations' => $recommendations,
                'ai_used' => $aiUsed
            ]);

        } catch (Exception $e) {
            Log::error('Error generating AI recommendations: ' . $e->getMessage());
            
            // Return fallback recommendations on error
            $riskSummary = $request->input('risk_summary', []);
            $cctvList = $request->input('cctv_list', []);
            $sapReports = $request->input('sap_reports', []);
            $areaInfo = $request->input('area_info', []);

            $riskLevel = $riskSummary['risk_level'] ?? 'NORMAL';
            $hasSapReport = $riskSummary['has_sap_report'] ?? false;
            $hasOnlineCctv = $riskSummary['has_online_cctv'] ?? false;
            $isHighRiskArea = $riskSummary['is_high_risk_area'] ?? false;
            $cctvCount = count($cctvList);
            $onlineCctvCount = 0;
            $offlineCctvCount = 0;
            $sapCount = count($sapReports);

            foreach ($cctvList as $cctv) {
                $kondisi = strtolower($cctv['kondisi'] ?? $cctv['status'] ?? '');
                $isOnline = $kondisi === 'baik' || $kondisi === 'online';
                if ($isOnline) {
                    $onlineCctvCount++;
                } else {
                    $offlineCctvCount++;
                }
            }

            $areaName = $areaInfo['lokasi'] ?? $areaInfo['nama_lokasi'] ?? 'Area Kerja';

            $recommendations = $this->generateFallbackRecommendations(
                $riskLevel, $hasSapReport, $hasOnlineCctv, $isHighRiskArea, 
                $cctvCount, $onlineCctvCount, $offlineCctvCount, $sapCount, $areaName,
                $cctvList, $sapReports
            );

            return response()->json([
                'success' => true,
                'recommendations' => $recommendations,
                'ai_used' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate fallback recommendations when AI service is unavailable
     */
    private function generateFallbackRecommendations(
        $riskLevel, $hasSapReport, $hasOnlineCctv, $isHighRiskArea, 
        $cctvCount, $onlineCctvCount, $offlineCctvCount, $sapCount, $areaName,
        $cctvList = [], $sapReports = []
    ) {
        $recommendations = [];

        // Get sample CCTV numbers for recommendations
        $sampleCctvOnline = [];
        $sampleCctvOffline = [];
        if (!empty($cctvList)) {
            foreach ($cctvList as $cctv) {
                $cctvNo = $cctv['no_cctv'] ?? $cctv['nomor_cctv'] ?? $cctv['nama_cctv'] ?? null;
                $lokasi = $cctv['lokasi_pemasangan'] ?? $cctv['coverage_detail_lokasi'] ?? $cctv['coverage_lokasi'] ?? '';
                $kondisi = strtolower($cctv['kondisi'] ?? $cctv['status'] ?? '');
                $isOnline = $kondisi === 'baik' || $kondisi === 'online' || 
                           strtolower($cctv['status'] ?? '') === 'live view';
                
                if ($cctvNo) {
                    if ($isOnline && count($sampleCctvOnline) < 3) {
                        $sampleCctvOnline[] = ['no' => $cctvNo, 'lokasi' => $lokasi];
                    } elseif (!$isOnline && count($sampleCctvOffline) < 2) {
                        $sampleCctvOffline[] = ['no' => $cctvNo, 'lokasi' => $lokasi];
                    }
                }
            }
        }

        // Get sample SAP task numbers
        $sampleSapTasks = [];
        if (!empty($sapReports) && $sapCount > 0) {
            foreach (array_slice($sapReports, 0, 3) as $sap) {
                $taskNumber = $sap['task_number'] ?? $sap['id'] ?? null;
                $jenis = $sap['jenis_laporan'] ?? $sap['source_type'] ?? 'SAP';
                $lokasi = $sap['nama_lokasi'] ?? $sap['lokasi'] ?? $sap['detail_lokasi'] ?? '';
                $deskripsi = $sap['keterangan'] ?? $sap['deskripsi'] ?? $sap['aktivitas_pekerjaan'] ?? $sap['description'] ?? null;
                $tanggal = $sap['tanggal_pelaporan'] ?? $sap['detected_at'] ?? null;
                
                // Get waktu from formatted waktu, or from jam:menit, or from tanggal
                $waktu = $sap['waktu'] ?? '';
                if (empty($waktu)) {
                    $jam = $sap['jam'] ?? null;
                    $menit = $sap['menit'] ?? null;
                    if (!empty($jam) && !empty($menit)) {
                        $jamInt = intval($jam);
                        $menitInt = intval($menit);
                        if ($jamInt >= 0 && $jamInt <= 23 && $menitInt >= 0 && $menitInt <= 59) {
                            $waktu = sprintf('%02d:%02d', $jamInt, $menitInt);
                        }
                    }
                }
                
                if (empty($waktu) && $tanggal) {
                    try {
                        $dateTime = new \Carbon\Carbon($tanggal);
                        $waktu = $dateTime->format('H:i');
                    } catch (\Exception $e) {}
                }
                
                if ($taskNumber) {
                    $sampleSapTasks[] = [
                        'task' => $taskNumber,
                        'jenis' => $jenis,
                        'lokasi' => $lokasi,
                        'deskripsi' => $deskripsi,
                        'waktu' => $waktu
                    ];
                }
            }
        }

        if ($riskLevel === 'HIGH') {
            if (!empty($sampleSapTasks)) {
                $sap = $sampleSapTasks[0];
                $waktuStr = $sap['waktu'] ? " pukul {$sap['waktu']}" : "";
                $deskripsiStr = !empty($sap['deskripsi']) ? " terkait '{$sap['deskripsi']}'" : "";
                $recommendations[] = [
                    'priority' => 'HIGH',
                    'action' => "Terdapat Temuan {$sap['jenis']} #{$sap['task']}{$deskripsiStr} yang dilaporkan{$waktuStr} di {$sap['lokasi']}, pastikan temuan (jika ada) telah ditindaklanjuti oleh tim lapangan dan tidak ada kondisi yang memerlukan perhatian khusus."
                ];
            }
            
            $recommendations[] = [
                'priority' => 'HIGH',
                'action' => "Koordinasi dengan Safety dan Mining Superintendet BC untuk memberikan teguran terhadap PJA dan IT Mitra jika tidak ada follow up utilisasi CCTV dan perbaikan status offline CCTV 3 hari berturut-turut di area {$areaName}, mengingat area ini memiliki risk level tinggi dan memerlukan monitoring optimal untuk mencegah potensi insiden."
            ];
            
            if (!empty($sampleCctvOffline)) {
                $cctvNos = implode(', ', array_column($sampleCctvOffline, 'no'));
                $recommendations[] = [
                    'priority' => 'HIGH',
                    'action' => "Segera koordinasi dengan IT Mitra untuk memperbaiki CCTV yang offline ({$cctvNos}) di area {$areaName}, mengingat area ini memiliki risk level tinggi dan memerlukan monitoring optimal."
                ];
            }
        } else if ($riskLevel === 'MEDIUM') {
            // Format Context → Insight → Action untuk MEDIUM risk
            if (!empty($sampleCctvOnline) && $isHighRiskArea) {
                $cctvNos = implode(' dan ', array_slice(array_column($sampleCctvOnline, 'no'), 0, 2));
                $lokasiList = array_unique(array_slice(array_column($sampleCctvOnline, 'lokasi'), 0, 2));
                $lokasiStr = implode(' dan ', $lokasiList);
                $recommendations[] = [
                    'priority' => 'MEDIUM',
                    'action' => "Fokuskan pemantauan real-time pada aktivitas di {$lokasiStr}, karena kedua lokasi tersebut memiliki CCTV aktif ({$cctvNos}) dan termasuk dalam zona kritis meskipun tidak diklasifikasikan sebagai high-risk hari ini."
                ];
            } elseif (!empty($sampleCctvOnline)) {
                $cctvNos = implode(' dan ', array_slice(array_column($sampleCctvOnline, 'no'), 0, 2));
                $lokasiList = array_unique(array_slice(array_column($sampleCctvOnline, 'lokasi'), 0, 2));
                $lokasiStr = implode(' dan ', $lokasiList);
                $recommendations[] = [
                    'priority' => 'MEDIUM',
                    'action' => "Fokuskan pemantauan real-time pada aktivitas di {$lokasiStr}, karena lokasi tersebut memiliki CCTV aktif ({$cctvNos}) dan memerlukan perhatian khusus untuk memastikan operasi berjalan dengan aman."
                ];
            }
            
            if ($sapCount > 0 && !empty($sampleSapTasks)) {
                $sap = $sampleSapTasks[0];
                $waktuStr = $sap['waktu'] ? " pukul {$sap['waktu']}" : "";
                $deskripsiStr = !empty($sap['deskripsi']) ? " terkait '{$sap['deskripsi']}'" : "";
                $recommendations[] = [
                    'priority' => 'MEDIUM',
                    'action' => "Terdapat Temuan {$sap['jenis']} #{$sap['task']}{$deskripsiStr} yang dilaporkan{$waktuStr} di {$sap['lokasi']}, pastikan temuan (jika ada) telah ditindaklanjuti oleh tim lapangan dan tidak ada kondisi yang memerlukan perhatian khusus."
                ];
            }
            
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'action' => "Pengawas Control Room wajib melakukan pemeriksaan kondisi aktivitas highrisk minimal 3 kali dalam shift ini di area {$areaName}, dengan fokus pada area yang memiliki potensi risiko sedang untuk mencegah eskalasi kondisi."
            ];
            
            if ($offlineCctvCount > 0 && !empty($sampleCctvOffline)) {
                $cctvNos = implode(', ', array_column($sampleCctvOffline, 'no'));
                $recommendations[] = [
                    'priority' => 'MEDIUM',
                    'action' => "Koordinasi dengan IT Mitra Kerja dan Berau Coal untuk memfollow up kondisi status offline CCTV {$cctvNos} dan memastikan kondisi jaringan internet lancar dan tersedia di area {$areaName}, mengingat pentingnya monitoring kontinyu untuk area dengan risk level sedang."
                ];
            }
        } else {
            // Format Context → Insight → Action untuk NORMAL risk
            if (!empty($sampleCctvOnline) && $cctvCount > 0) {
                $cctvNos = implode(', ', array_slice(array_column($sampleCctvOnline, 'no'), 0, 3));
                $lokasiList = array_unique(array_slice(array_column($sampleCctvOnline, 'lokasi'), 0, 2));
                $lokasiStr = !empty($lokasiList) ? implode(' dan ', $lokasiList) : $areaName;
                
                $recommendations[] = [
                    'priority' => 'LOW',
                    'action' => "Fokuskan pemantauan real-time pada aktivitas di {$lokasiStr}, karena lokasi tersebut memiliki CCTV aktif ({$cctvNos}) dan memerlukan monitoring rutin untuk memastikan operasi berjalan sesuai standar keselamatan."
                ];
            }
            
            if ($cctvCount > 0) {
                $recommendations[] = [
                    'priority' => 'LOW',
                    'action' => "Dokumentasikan penggunaan seluruh {$cctvCount} CCTV dalam shift ini sebagai bukti utilitas sistem, khususnya untuk kamera yang memantau aktivitas operasional rutin di area {$areaName}."
                ];
            }
            
            if (!empty($sampleCctvOnline)) {
                $cctvNos = implode(' dan ', array_slice(array_column($sampleCctvOnline, 'no'), 0, 2));
                $lokasiList = array_unique(array_slice(array_column($sampleCctvOnline, 'lokasi'), 0, 2));
                $lokasiStr = !empty($lokasiList) ? implode(' dan ', $lokasiList) : $areaName;
                
                $recommendations[] = [
                    'priority' => 'LOW',
                    'action' => "Gunakan kamera {$cctvNos} untuk melakukan patroli visual rutin terhadap aktivitas di {$lokasiStr}, mengingat pentingnya memastikan prosedur keselamatan diterapkan dengan baik di setiap tahap operasi."
                ];
            }
            
            if ($hasSapReport && !empty($sampleSapTasks)) {
                $sap = $sampleSapTasks[0];
                $waktuStr = $sap['waktu'] ? " pukul {$sap['waktu']}" : "";
                $recommendations[] = [
                    'priority' => 'LOW',
                    'action' => "Terdapat Temuan {$sap['jenis']} #{$sap['task']} yang dilaporkan{$waktuStr} di {$sap['lokasi']}, pastikan temuan (jika ada) telah ditindaklanjuti oleh tim lapangan dan tidak ada kondisi yang memerlukan perhatian khusus."
                ];
            } else {
                // Jika tidak ada SAP report, buat rekomendasi berdasarkan CCTV yang tersedia
                if (!empty($sampleCctvOnline) && $cctvCount > 0) {
                    $cctvNos = implode(', ', array_slice(array_column($sampleCctvOnline, 'no'), 0, 3));
                    $lokasiList = array_unique(array_slice(array_column($sampleCctvOnline, 'lokasi'), 0, 2));
                    $lokasiStr = !empty($lokasiList) ? implode(' dan ', $lokasiList) : $areaName;
                    
                    $recommendations[] = [
                        'priority' => 'LOW',
                        'action' => "Lakukan verifikasi status dan kualitas sinyal pada kamera {$cctvNos} di awal shift, karena kamera tersebut memantau aktivitas di {$lokasiStr} dan memerlukan kondisi optimal untuk memastikan monitoring berjalan efektif sepanjang shift."
                    ];
                } elseif ($cctvCount > 0) {
                    $recommendations[] = [
                        'priority' => 'LOW',
                        'action' => "Lakukan verifikasi status seluruh {$cctvCount} CCTV di area {$areaName} pada awal shift, pastikan semua kamera dalam kondisi baik dan dapat diakses untuk monitoring aktivitas operasional, mengingat pentingnya visibilitas kontinyu untuk menjaga standar keselamatan."
                    ];
                } else {
                    $recommendations[] = [
                        'priority' => 'LOW',
                        'action' => "Koordinasi dengan tim terkait untuk memastikan ketersediaan CCTV di area {$areaName}, karena monitoring visual sangat penting untuk menjaga standar keselamatan operasional."
                    ];
                }
            }
        }

        if ($sapCount > 0 && empty($sampleSapTasks)) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'action' => "Review dan follow up {$sapCount} laporan SAP yang ada hari ini di area {$areaName}, pastikan semua temuan telah ditindaklanjuti oleh tim lapangan dan tidak ada kondisi yang memerlukan perhatian khusus atau tindakan lanjutan."
            ];
        }

        return $recommendations;
    }

    /**
     * Store intervensi for area kerja
     */
    public function storeIntervensiAreaKerja(Request $request)
    {
        try {
            $validated = $request->validate([
                'lokasi' => 'required|string|max:255',
                'area_kerja' => 'nullable|string|max:255',
                'pic_id' => 'required|string',
                'issue' => 'required|string',
            ]);

            // Get authenticated user
            $user = Auth::user();
            $createdBy = $user ? $user->name : 'Unknown';
            $createdByEmail = $user ? $user->email : null;

            // Get PIC details from ClickHouse
            $clickHouseService = new ClickHouseService();
            $picId = $validated['pic_id'];
            
            // Escape single quotes to prevent SQL injection
            $escapedPicId = str_replace("'", "''", $picId);
            
            $sql = "SELECT 
                        toString(id) as id,
                        toString(username) as username,
                        toString(nama) as nama,
                        toString(selular) as selular
                    FROM nitip.vw_user 
                    WHERE toString(id) = '{$escapedPicId}'
                    LIMIT 1";
            
            $picData = $clickHouseService->query($sql);
            $picInfo = !empty($picData) ? $picData[0] : null;

            // Store intervensi using new IntervensiAreaKerja model
            $intervensi = IntervensiAreaKerja::create([
                'lokasi' => $validated['lokasi'],
                'area_kerja' => $validated['area_kerja'] ?? null,
                'pic_id' => $picId,
                'pic_username' => $picInfo['username'] ?? null,
                'pic_nama' => $picInfo['nama'] ?? null,
                'pic_telepon' => $picInfo['selular'] ?? null,
                'issue' => $validated['issue'],
                'status' => 'open', // Default status
                'created_by' => $createdBy,
                'created_by_email' => $createdByEmail,
            ]);

            // Prepare WhatsApp URL
            $whatsappNumber = $picInfo['selular'] ?? null;
            $whatsappUrl = null;
            
            if ($whatsappNumber) {
                // Clean phone number (remove non-numeric characters except +)
                $cleanNumber = preg_replace('/[^0-9+]/', '', $whatsappNumber);
                // Remove leading 0 and replace with country code if needed
                if (substr($cleanNumber, 0, 1) === '0') {
                    $cleanNumber = '62' . substr($cleanNumber, 1);
                } elseif (substr($cleanNumber, 0, 1) !== '+') {
                    $cleanNumber = '62' . $cleanNumber;
                }
                $cleanNumber = str_replace('+', '', $cleanNumber);
                
                // Format pesan WhatsApp
                $pesan = "Form Intervensi Area Kerja\n\n";
                $pesan .= "Pelapor: " . $createdBy . "\n";
                $pesan .= "Lokasi: " . $validated['lokasi'] . "\n";
                if ($validated['area_kerja']) {
                    $pesan .= "Area Kerja: " . $validated['area_kerja'] . "\n";
                }
                $pesan .= "PIC: " . ($picInfo['username'] ?? '') . " - " . ($picInfo['nama'] ?? '') . "\n";
                $pesan .= "Issue:\n" . $validated['issue'] . "\n\n";
                $pesan .= "Link: https://besentry.beraucoal.co.id/cctv-data-control-room/intervensi";
                
                $whatsappUrl = "https://wa.me/" . $cleanNumber . "?text=" . urlencode($pesan);
            }

            return response()->json([
                'success' => true,
                'message' => 'Intervensi berhasil dikirim!',
                'data' => [
                    'intervensi_id' => $intervensi->id,
                    'whatsapp_url' => $whatsappUrl,
                    'pic_telepon' => $whatsappNumber
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error storing intervensi area kerja: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan intervensi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CCTV list for area kerja (based on location/area kerja)
     */
    public function getCctvForAreaKerja(Request $request)
    {
        try {
            $lokasi = $request->get('lokasi');
            $areaKerja = $request->get('area_kerja');
            
            if (!$lokasi && !$areaKerja) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lokasi atau Area Kerja harus diisi'
                ], 400);
            }

            // Query CCTV based on lokasi or area_kerja
            $query = CctvData::query();
            
            if ($lokasi) {
                $query->where('lokasi', 'like', '%' . $lokasi . '%');
            }
            
            if ($areaKerja) {
                $query->orWhere('area_kerja', 'like', '%' . $areaKerja . '%');
            }
            
            $cctvList = $query->get();
            
            $cctvData = $cctvList->map(function($cctv) {
                return [
                    'id' => $cctv->id,
                    'nama_cctv' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                    'no_cctv' => $cctv->no_cctv ?? null,
                    'lokasi_pemasangan' => $cctv->lokasi_pemasangan ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $cctvData
            ]);

        } catch (Exception $e) {
            Log::error('Error getting CCTV for area kerja: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data CCTV.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily operation plans with polygons from ClickHouse
     * Returns GeoJSON FeatureCollection for display on map
     */
    public function getDailyOperationPlansWithPolygons(Request $request)
    {
        try {
            // Get all daily operation plans
            $plans = DailyOperationPlan::all();
            
            if ($plans->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'type' => 'FeatureCollection',
                        'features' => []
                    ]
                ]);
            }

            $clickHouseService = new ClickHouseService();
            $features = [];

            foreach ($plans as $plan) {
                $lokasi = $plan->lokasi;
                $detailLokasi = $plan->detail_lokasi;

                if (!$lokasi || !$detailLokasi) {
                    continue; // Skip if lokasi or detail_lokasi is empty
                }

                // Escape single quotes to prevent SQL injection
                $escapedLokasi = str_replace("'", "''", $lokasi);
                $escapedDetailLokasi = str_replace("'", "''", $detailLokasi);

                // Query ClickHouse for polygon geometry
                // Use ST_AsGeoJSON if available, otherwise use wkt_geometry
                // Try to get GeoJSON directly from ClickHouse first
                $sql = "SELECT 
                            lokasi,
                            Detil_Lokasi as detail_lokasi,
                            wkt_geometry,
                            site,
                            toString(ST_AsGeoJSON(wkt_geometry)) as geo_json
                        FROM nitip.bep_vw_site_lokasi_detil_lokasi 
                        WHERE lokasi = '{$escapedLokasi}' 
                        AND Detil_Lokasi = '{$escapedDetailLokasi}'
                        LIMIT 1";

                try {
                    $clickHouseResults = $clickHouseService->query($sql);
                    
                    if (!empty($clickHouseResults)) {
                        $polygonData = $clickHouseResults[0];
                        $geoJson = null;
                        
                        // Try to use GeoJSON directly from ClickHouse first
                        if (!empty($polygonData['geo_json'])) {
                            $geoJsonStr = $polygonData['geo_json'];
                            // Remove quotes if present
                            $geoJsonStr = trim($geoJsonStr, '"\'');
                            $decoded = json_decode($geoJsonStr, true);
                            if ($decoded && is_array($decoded)) {
                                $geoJson = $decoded;
                            }
                        }
                        
                        // Fallback to WKT parsing if GeoJSON not available
                        if (!$geoJson) {
                            $wktGeometry = $polygonData['wkt_geometry'] ?? null;
                            if ($wktGeometry) {
                                // Convert WKT to GeoJSON
                                // WKT format: MULTIPOLYGON(((lon lat, lon lat, ...)))
                                // We'll parse it and convert to GeoJSON
                                $geoJson = $this->wktToGeoJson($wktGeometry);
                            }
                        }

                        if ($geoJson) {
                            // Validate geometry coordinates
                            if ($this->isValidGeometry($geoJson)) {
                                // Additional check: ensure polygon has valid coordinates (not all same lat/lon)
                                if ($this->hasValidCoordinateVariation($geoJson)) {
                                    // Create GeoJSON feature with plan data as properties
                                    $feature = [
                                        'type' => 'Feature',
                                        'geometry' => $geoJson,
                                        'properties' => [
                                            'id' => $plan->id,
                                            'site' => $plan->site ?? $polygonData['site'] ?? null,
                                            'pekerjaan' => $plan->pekerjaan ?? null,
                                            'unit_id' => $plan->unit_id ?? null,
                                            'lokasi' => $lokasi,
                                            'detail_lokasi' => $detailLokasi,
                                            'potensi_resiko' => $plan->potensi_resiko ?? null,
                                            'pengendalian_bahaya' => $plan->pengendalian_bahaya ?? null,
                                            'catatan' => $plan->catatan ?? null,
                                            'tanggal' => $plan->tanggal ? $plan->tanggal->format('Y-m-d') : null,
                                            'foto_pekerjaan' => $plan->foto_pekerjaan ?? null,
                                        ]
                                    ];
                                    $features[] = $feature;
                                } else {
                                    Log::warning("Geometry has no coordinate variation for plan {$plan->id} (lokasi: {$lokasi}, detail_lokasi: {$detailLokasi}) - likely a line or invalid polygon");
                                }
                            } else {
                                Log::warning("Invalid geometry for plan {$plan->id} (lokasi: {$lokasi}, detail_lokasi: {$detailLokasi})");
                            }
                        }
                    }
                } catch (Exception $chError) {
                    Log::warning("ClickHouse query failed for plan {$plan->id}: " . $chError->getMessage());
                    continue; // Skip this plan if ClickHouse query fails
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'type' => 'FeatureCollection',
                    'features' => $features
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error getting daily operation plans with polygons: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data rencana kerja.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert WKT geometry to GeoJSON
     * Supports MULTIPOLYGON format
     */
    private function wktToGeoJson($wkt)
    {
        try {
            // Remove MULTIPOLYGON keyword and parentheses
            $wkt = trim($wkt);
            if (stripos($wkt, 'MULTIPOLYGON') === 0) {
                $wkt = substr($wkt, 12); // Remove "MULTIPOLYGON"
            }
            $wkt = trim($wkt, '()');

            // Parse coordinates
            // Format: (((lon lat, lon lat, ...)), ((lon lat, ...)))
            $polygons = [];
            $depth = 0;
            $currentPolygon = '';
            $polygonStrings = [];

            // Split by outermost parentheses to get individual polygons
            $chars = str_split($wkt);
            $current = '';
            $parenDepth = 0;

            foreach ($chars as $char) {
                if ($char === '(') {
                    $parenDepth++;
                    if ($parenDepth > 1) {
                        $current .= $char;
                    }
                } elseif ($char === ')') {
                    $parenDepth--;
                    if ($parenDepth > 0) {
                        $current .= $char;
                    } elseif ($parenDepth === 0 && !empty($current)) {
                        $polygonStrings[] = $current;
                        $current = '';
                    }
                } else {
                    if ($parenDepth > 0) {
                        $current .= $char;
                    }
                }
            }

            // If no polygons found, try parsing as single polygon
            if (empty($polygonStrings)) {
                $polygonStrings[] = $wkt;
            }

            foreach ($polygonStrings as $polygonStr) {
                // Remove outer parentheses
                $polygonStr = trim($polygonStr, '()');
                
                // Split by coordinates (handling nested parentheses for holes)
                $rings = [];
                $ringStr = '';
                $ringDepth = 0;

                foreach (str_split($polygonStr) as $char) {
                    if ($char === '(') {
                        $ringDepth++;
                        if ($ringDepth > 1) {
                            $ringStr .= $char;
                        }
                    } elseif ($char === ')') {
                        $ringDepth--;
                        if ($ringDepth > 0) {
                            $ringStr .= $char;
                        } elseif ($ringDepth === 0 && !empty($ringStr)) {
                            $rings[] = $this->parseCoordinateRing($ringStr);
                            $ringStr = '';
                        }
                    } else {
                        if ($ringDepth > 0) {
                            $ringStr .= $char;
                        }
                    }
                }

                // If no rings found, parse the whole string as a ring
                if (empty($rings)) {
                    $rings[] = $this->parseCoordinateRing($polygonStr);
                }

                if (!empty($rings)) {
                    $polygons[] = $rings;
                }
            }

            if (empty($polygons)) {
                return null;
            }

            // Return as MultiPolygon GeoJSON
            if (count($polygons) === 1) {
                // Single polygon - return as Polygon
                return [
                    'type' => 'Polygon',
                    'coordinates' => $polygons[0]
                ];
            } else {
                // Multiple polygons - return as MultiPolygon
                return [
                    'type' => 'MultiPolygon',
                    'coordinates' => $polygons
                ];
            }

        } catch (Exception $e) {
            Log::error('Error converting WKT to GeoJSON: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse a coordinate ring string into array of [lon, lat] pairs
     */
    private function parseCoordinateRing($ringStr)
    {
        $ringStr = trim($ringStr, '()');
        $coords = [];
        
        // Remove any nested parentheses first
        $ringStr = preg_replace('/\(([^()]+)\)/', '$1', $ringStr);
        
        // Split by comma
        $parts = explode(',', $ringStr);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }
            
            // Split by whitespace to get lon and lat
            // WKT format is "lon lat" (space-separated)
            $values = preg_split('/\s+/', $part);
            if (count($values) >= 2) {
                $lon = (float) trim($values[0]);
                $lat = (float) trim($values[1]);
                
                // Validate coordinates
                if ($lon >= -180 && $lon <= 180 && $lat >= -90 && $lat <= 90) {
                    $coords[] = [$lon, $lat];
                } else {
                    Log::warning("Invalid coordinate parsed: lon={$lon}, lat={$lat}");
                }
            }
        }
        
        // Ensure ring is closed (first and last coordinates should be the same)
        if (count($coords) > 0) {
            $first = $coords[0];
            $last = $coords[count($coords) - 1];
            if ($first[0] != $last[0] || $first[1] != $last[1]) {
                // Close the ring by adding first coordinate at the end
                $coords[] = $first;
            }
        }
        
        return $coords;
    }

    /**
     * Validate geometry coordinates to ensure they are within reasonable bounds
     * Prevents invalid geometries that could cause display issues
     */
    private function isValidGeometry($geoJson)
    {
        if (!$geoJson || !isset($geoJson['type']) || !isset($geoJson['coordinates'])) {
            return false;
        }

        // Valid longitude range: -180 to 180
        // Valid latitude range: -90 to 90
        // For Indonesia, longitude is typically around 95-141, latitude around -11 to 6
        
        $coordinates = $geoJson['coordinates'];
        
        if ($geoJson['type'] === 'Polygon') {
            foreach ($coordinates as $ring) {
                foreach ($ring as $coord) {
                    if (count($coord) < 2) {
                        return false;
                    }
                    $lon = (float) $coord[0];
                    $lat = (float) $coord[1];
                    
                    // Check if coordinates are within valid ranges
                    if ($lon < -180 || $lon > 180 || $lat < -90 || $lat > 90) {
                        return false;
                    }
                    
                    // Additional check: if coordinates seem way off (likely parsing error)
                    // For Indonesia area, coordinates should be roughly: lon 95-141, lat -11 to 6
                    // But we'll be lenient and allow a wider range to avoid false positives
                    if (abs($lon) > 200 || abs($lat) > 100) {
                        return false;
                    }
                }
            }
        } elseif ($geoJson['type'] === 'MultiPolygon') {
            foreach ($coordinates as $polygon) {
                foreach ($polygon as $ring) {
                    foreach ($ring as $coord) {
                        if (count($coord) < 2) {
                            return false;
                        }
                        $lon = (float) $coord[0];
                        $lat = (float) $coord[1];
                        
                        // Check if coordinates are within valid ranges
                        if ($lon < -180 || $lon > 180 || $lat < -90 || $lat > 90) {
                            return false;
                        }
                        
                        // Additional check: if coordinates seem way off
                        if (abs($lon) > 200 || abs($lat) > 100) {
                            return false;
                        }
                    }
                }
            }
        } else {
            return false; // Unsupported geometry type
        }
        
        return true;
    }

    /**
     * Check if geometry has valid coordinate variation
     * Prevents lines (all same lat or all same lon) from being displayed as polygons
     */
    private function hasValidCoordinateVariation($geoJson)
    {
        if (!$geoJson || !isset($geoJson['coordinates'])) {
            return false;
        }

        $coordinates = $geoJson['coordinates'];
        $allLons = [];
        $allLats = [];
        
        if ($geoJson['type'] === 'Polygon') {
            foreach ($coordinates as $ring) {
                foreach ($ring as $coord) {
                    if (count($coord) >= 2) {
                        $allLons[] = (float) $coord[0];
                        $allLats[] = (float) $coord[1];
                    }
                }
            }
        } elseif ($geoJson['type'] === 'MultiPolygon') {
            foreach ($coordinates as $polygon) {
                foreach ($polygon as $ring) {
                    foreach ($ring as $coord) {
                        if (count($coord) >= 2) {
                            $allLons[] = (float) $coord[0];
                            $allLats[] = (float) $coord[1];
                        }
                    }
                }
            }
        } else {
            return false;
        }
        
        if (count($allLons) < 3 || count($allLats) < 3) {
            return false; // Need at least 3 points for a polygon
        }
        
        // Check if all longitudes are the same (vertical line) or all latitudes are the same (horizontal line)
        $lonVariation = max($allLons) - min($allLons);
        $latVariation = max($allLats) - min($allLats);
        
        // Both must have some variation (at least 0.0001 degrees, roughly 11 meters)
        if ($lonVariation < 0.0001 || $latVariation < 0.0001) {
            return false; // This is a line, not a polygon
        }
        
        return true;
    }

}


