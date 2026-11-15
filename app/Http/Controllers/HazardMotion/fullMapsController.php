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
use App\Models\SupervisoryAlertLog;
use App\Models\DailyOperationPlan;
use App\Models\Dopm;
use App\Models\HseAiValidation;
use App\Models\IpkIkk;
use App\Models\Okk;
use App\Services\ClickHouseService;
use App\Services\EvaluasiUnitDataService;
use App\Services\TelegramBotService;
use App\Services\QwenAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class fullMapsController extends Controller
{
    /** Koneksi ClickHouse untuk data nitip (database nitip, unit_gps_logs). */
    private function getClickHouseNitip(): ClickHouseService
    {
        return app(ClickHouseService::class, ['connectionName' => 'clickhouse_nitip']);
    }

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
        
        // Get user roles for filtering area kerja boundaries
        $userRoles = [];
        if ($user && method_exists($user, 'roles')) {
            $userRoles = $user->roles()->pluck('slug')->toArray();
        }
        
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
        // Mengganti hazard dengan SAP dari tabel hse_automation.aaj_car_all_year_from_dav
        // Default: ambil data hari ini saja
        $sapData = $this->getSapDataFromClickHouse();

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

                // Cari record yang memiliki kode_be_investigasi tidak null
                $mainRecord = $items->first(function ($item) {
                    return ! is_null($item->kode_be_investigasi) && $item->kode_be_investigasi !== '';
                });
                
                // Jika tidak ada, gunakan record yang memiliki data paling lengkap
                if (! $mainRecord) {
                    $mainRecord = $items->first(function ($item) {
                        return ! is_null($item->hari) || ! is_null($item->jam) || 
                               ! is_null($item->shift) || ! is_null($item->perusahaan) || 
                               ! is_null($item->departemen) || ! is_null($item->bulan) || 
                               ! is_null($item->tahun) || ! is_null($item->minggu_ke);
                    });
                }
                
                // Jika masih tidak ada, gunakan record pertama
                if (! $mainRecord) {
                    $mainRecord = $first;
                }

                $latItem = $items->first(function ($item) {
                    return ! is_null($item->latitude);
                });
                $lonItem = $items->first(function ($item) {
                    return ! is_null($item->longitude);
                });

                return [
                    'no_kecelakaan' => $noKecelakaan,
                    'kode_be_investigasi' => $mainRecord->kode_be_investigasi,
                    'status_lpi' => $mainRecord->status_lpi,
                    'site' => $mainRecord->site,
                    'lokasi' => $mainRecord->lokasi ?? $mainRecord->lokasi_spesifik ?? null,
                    'sublokasi' => $mainRecord->sublokasi,
                    'lokasi_spesifik' => $mainRecord->lokasi_spesifik,
                    'lokasi_validasi_hsecm' => $mainRecord->lokasi_validasi_hsecm,
                    'layer' => $mainRecord->layer,
                    'jenis_item_ipls' => $mainRecord->jenis_item_ipls,
                    'kategori' => $mainRecord->kategori,
                    'injury_status' => $mainRecord->injury_status,
                    'high_potential' => $mainRecord->high_potential,
                    'kronologis' => $mainRecord->kronologis,
                    'tanggal' => optional($mainRecord->tanggal)->format('Y-m-d'),
                    'bulan' => $mainRecord->bulan,
                    'tahun' => $mainRecord->tahun,
                    'minggu_ke' => $mainRecord->minggu_ke,
                    'hari' => $mainRecord->hari,
                    'jam' => $mainRecord->jam,
                    'menit' => $mainRecord->menit,
                    'shift' => $mainRecord->shift,
                    'perusahaan' => $mainRecord->perusahaan,
                    'departemen' => $mainRecord->departemen,
                    'pja' => $mainRecord->pja,
                    'insiden_dalam_site_mining' => $mainRecord->insiden_dalam_site_mining,
                    'latitude' => $latItem->latitude ?? null,
                    'longitude' => $lonItem->longitude ?? null,
                    'items' => $items->map(function ($item) {
                        return [
                            'kode_be_investigasi' => $item->kode_be_investigasi,
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

        // Get unit vehicle data from ClickHouse Nitip only (unit_gps_logs: today, latest per unit)
        $unitVehicles = [];
        try {
            $unitVehicles = $this->getUnitVehiclesFromClickHouseNitip();
        } catch (Exception $e) {
            Log::error('Error fetching unit vehicles from ClickHouse Nitip: ' . $e->getMessage());
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
            'p2hResults',
            'userRoles'  // User roles for filtering area kerja boundaries
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
    private function getSapDataFromClickHouse()
    {
        Log::info('getSapDataFromClickHouse - Method called (filter: today only)');
        
        try {
            // Menggunakan koneksi langsung ke ClickHouse 10.10.10.38
            // Tidak menggunakan ClickHouseService karena menggunakan koneksi custom
            Log::info('getSapDataFromClickHouse - Using custom ClickHouse connection to 10.10.10.38');
            
            $today = Carbon::now();
            $todayStr = $today->format('Y-m-d');
            
            Log::info('SAP Query - Filter: Today only (' . $todayStr . ')');
            
            $sapData = [];
            $resultsInspeksi = [];
            
            // 1. Query aaj_car_all_year_from_dav
            try {
                // First, test connection with a simple count query
                $testSql = "SELECT count() as total FROM aaj_car_all_year_from_dav LIMIT 1";
                $testResult = $this->queryClickHouseCustom($testSql, 'hse_automation');
                Log::info('ClickHouse connection test', [
                    'test_result' => $testResult,
                    'test_count' => is_array($testResult) && isset($testResult[0]) ? $testResult[0] : null
                ]);
                
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
                    FROM aaj_car_all_year_from_dav
                    WHERE (
                        (tanggal_pembuatan IS NOT NULL 
                            AND toDate(toTimeZone(tanggal_pembuatan, 'Asia/Makassar')) = toDate(toTimeZone(now(), 'Asia/Makassar')))
                        OR (bedraft_date IS NOT NULL 
                            AND toDate(toTimeZone(bedraft_date, 'Asia/Makassar')) = toDate(toTimeZone(now(), 'Asia/Makassar')))
                    )
                    ORDER BY 
                        CASE 
                            WHEN tanggal_pembuatan IS NOT NULL THEN toDateTime(tanggal_pembuatan)
                            WHEN bedraft_date IS NOT NULL THEN toDateTime(bedraft_date)
                            ELSE toDateTime('1970-01-01 00:00:00')
                        END DESC
                    LIMIT 12500
                ";
                
                Log::info('Executing SAP query (today only)', [
                    'sql_preview' => substr($sqlInspeksi, 0, 300) . '...',
                    'filter_date' => $todayStr
                ]);
                
                // Menggunakan queryClickHouseCustom dengan database 'hse_automation' dan koneksi ke 10.10.10.38
                $resultsInspeksi = $this->queryClickHouseCustom($sqlInspeksi, 'hse_automation');
                
                Log::info('SAP query result', [
                    'result_type' => gettype($resultsInspeksi),
                    'result_count' => is_array($resultsInspeksi) ? count($resultsInspeksi) : 0,
                    'result_preview' => is_array($resultsInspeksi) && !empty($resultsInspeksi) ? array_slice($resultsInspeksi, 0, 2) : null
                ]);
                
                if (!empty($resultsInspeksi) && is_array($resultsInspeksi)) {
                    Log::info('INSPEKSI_HAZARD data retrieved: ' . count($resultsInspeksi) . ' records');
                    foreach ($resultsInspeksi as $index => $row) {
                        try {
                            // Convert object to array if needed
                            if (is_object($row)) {
                                $row = (array) $row;
                            }
                            
                            // Ensure row is an array
                            if (!is_array($row)) {
                                Log::warning('Skipping non-array row', ['index' => $index, 'type' => gettype($row)]);
                                continue;
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
                            Log::error('Error processing row in aaj_car_all_year_from_dav', [
                                'index' => $index,
                                'error' => $e->getMessage(),
                                'row_preview' => is_array($row) ? array_slice($row, 0, 5) : $row
                            ]);
                        }
                    }
                } else {
                    Log::warning('INSPEKSI_HAZARD query returned empty result', [
                        'result_type' => gettype($resultsInspeksi),
                        'result' => $resultsInspeksi
                    ]);
                    
                    // Fallback: Try querying without date filter to see if there's any data
                    Log::info('Trying fallback query without date filter');
                    try {
                        $fallbackSql = "
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
                            FROM aaj_car_all_year_from_dav
                            WHERE (
                                (tanggal_pembuatan IS NOT NULL 
                                    AND toDate(toTimeZone(tanggal_pembuatan, 'Asia/Makassar')) = toDate(toTimeZone(now(), 'Asia/Makassar')))
                                OR (bedraft_date IS NOT NULL 
                                    AND toDate(toTimeZone(bedraft_date, 'Asia/Makassar')) = toDate(toTimeZone(now(), 'Asia/Makassar')))
                            )
                            ORDER BY 
                                CASE 
                                    WHEN tanggal_pembuatan IS NOT NULL THEN toDateTime(tanggal_pembuatan)
                                    WHEN bedraft_date IS NOT NULL THEN toDateTime(bedraft_date)
                                    ELSE toDateTime('1970-01-01 00:00:00')
                                END DESC
                            LIMIT 1000
                        ";
                        
                        $fallbackResults = $this->queryClickHouseCustom($fallbackSql, 'hse_automation');
                        
                        if (!empty($fallbackResults) && is_array($fallbackResults)) {
                            Log::info('Fallback query returned ' . count($fallbackResults) . ' records');
                            foreach ($fallbackResults as $index => $row) {
                                try {
                                    if (is_object($row)) {
                                        $row = (array) $row;
                                    }
                                    
                                    if (!is_array($row)) {
                                        continue;
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
                                    Log::error('Error processing fallback row: ' . $e->getMessage());
                                }
                            }
                        } else {
                            Log::warning('Fallback query also returned empty result');
                        }
                    } catch (Exception $fallbackError) {
                        Log::error('Fallback query failed: ' . $fallbackError->getMessage());
                    }
                }
            } catch (Exception $e) {
                Log::error('Error querying aaj_car_all_year_from_dav', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Sort by tanggal_pelaporan descending
            usort($sapData, function($a, $b) {
                $dateA = $a['tanggal_pelaporan'] ?? '';
                $dateB = $b['tanggal_pelaporan'] ?? '';
                return strcmp($dateB, $dateA);
            });
            
            Log::info('getSapDataFromClickHouse - Method completed', [
                'sap_records_count' => count($sapData),
                'filter_date' => $todayStr
            ]);
            
            return $sapData;

        } catch (Exception $e) {
            Log::error('Error fetching SAP data from ClickHouse', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
                // Set timezone to Asia/Makassar (UTC+8)
                $timezone = new \DateTimeZone('Asia/Makassar');
                
                // Try multiple date formats that ClickHouse might return
                $formats = [
                    'Y-m-d H:i:s',
                    'Y-m-d\TH:i:s',
                    'Y-m-d\TH:i:s.u',
                    'Y-m-d\TH:i:s.v',
                    'Y-m-d H:i:s.u',
                    'Y-m-d',
                ];
                
                $date = null;
                foreach ($formats as $format) {
                    try {
                        // Parse date assuming it's already in Asia/Makassar timezone
                        $date = \DateTime::createFromFormat($format, $dateValue, $timezone);
                        if ($date !== false) {
                            break;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                
                // If format matching fails, try standard DateTime parsing
                if ($date === false || $date === null) {
                    // Assume the date from ClickHouse is in Asia/Makassar timezone
                    $date = new \DateTime($dateValue, $timezone);
                }
                
                // Return ISO 8601 format with timezone (e.g., "2026-01-19T19:06:43+08:00")
                return $date->format('c'); // 'c' format = ISO 8601 with timezone
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
            
            $sapData = [];
            
            // Query SAP data with today filter using timezone Asia/Makassar
            // 1. Query aaj_car_all_year_from_dav using direct ClickHouse connection to 10.10.10.38
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
                    FROM hse_automation.aaj_car_all_year_from_dav
                    WHERE latitude IS NOT NULL 
                        AND longitude IS NOT NULL
                        AND latitude != ''
                        AND longitude != ''
                        AND (
                            (tanggal_pembuatan IS NOT NULL 
                                AND toDate(tanggal_pembuatan, 'Asia/Makassar') = toDate(toTimeZone(now(), 'Asia/Makassar')))
                            OR (bedraft_date IS NOT NULL 
                                AND toDate(bedraft_date, 'Asia/Makassar') = toDate(toTimeZone(now(), 'Asia/Makassar')))
                        )
                    ORDER BY 
                        CASE 
                            WHEN tanggal_pembuatan IS NOT NULL THEN toDateTime(toTimeZone(tanggal_pembuatan, 'Asia/Makassar'))
                            WHEN bedraft_date IS NOT NULL THEN toDateTime(toTimeZone(bedraft_date, 'Asia/Makassar'))
                            ELSE toDateTime('1970-01-01 00:00:00')
                        END DESC
                    LIMIT {$limit}
                ";
                
                // Menggunakan queryClickHouseCustom dengan database 'hse_automation' dan koneksi ke 10.10.10.38
                $resultsInspeksi = $this->queryClickHouseCustom($sqlInspeksi, 'hse_automation');
                
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
     * Get CCTV list for area kerja (all CCTV, no location filter)
     */
    public function getCctvForAreaKerja(Request $request)
    {
        try {
            // Get all CCTV without location filter
            $cctvList = CctvData::query()->get();
            
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
     * Simpan alert Supervisory (layer Pengawasan Berjarak) ke database.
     * Hanya menyimpan ketika risk_level HIGH atau MEDIUM; NORMAL (hijau) tidak disimpan.
     * Menerima single payload atau batch (alerts array).
     */
    public function storeSupervisoryAlertLog(Request $request)
    {
        try {
            $alerts = $request->input('alerts');
            if (is_array($alerts)) {
                $saved = 0;
                foreach ($alerts as $item) {
                    $normalized = $this->normalizeSupervisoryAlertItem($item);
                    if ($normalized['nama_lokasi'] !== '' && SupervisoryAlertLog::storeIfNotGreen($normalized)) {
                        $saved++;
                    }
                }
                return response()->json([
                    'success' => true,
                    'message' => 'Alert supervisory disimpan.',
                    'saved' => $saved,
                ]);
            }

            $item = $request->only([
                'tanggal', 'id_lokasi', 'nama_lokasi', 'risk_level',
                'has_sap_report', 'has_online_cctv', 'is_high_risk_area'
            ]);
            $item = $this->normalizeSupervisoryAlertItem($item);
            if ($item['nama_lokasi'] === '') {
                return response()->json(['success' => false, 'message' => 'nama_lokasi wajib diisi.'], 422);
            }
            $saved = SupervisoryAlertLog::storeIfNotGreen($item);
            return response()->json([
                'success' => true,
                'message' => $saved ? 'Alert supervisory disimpan.' : 'Status hijau tidak disimpan.',
                'saved' => $saved,
            ]);
        } catch (Exception $e) {
            Log::error('Error storing supervisory alert log: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan alert supervisory.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Normalize single supervisory alert item for DB (tanggal format, booleans).
     */
    private function normalizeSupervisoryAlertItem(array $item): array
    {
        $tanggal = $item['tanggal'] ?? now()->toDateString();
        if ($tanggal instanceof \DateTimeInterface) {
            $tanggal = Carbon::parse($tanggal)->toDateString();
        } elseif (is_string($tanggal) && strlen($tanggal) > 10) {
            $tanggal = Carbon::parse($tanggal)->toDateString();
        }
        return [
            'tanggal' => $tanggal,
            'id_lokasi' => $item['id_lokasi'] ?? null,
            'nama_lokasi' => (string) ($item['nama_lokasi'] ?? ''),
            'risk_level' => $item['risk_level'] ?? SupervisoryAlertLog::RISK_NORMAL,
            'has_sap_report' => filter_var($item['has_sap_report'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'has_online_cctv' => filter_var($item['has_online_cctv'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_high_risk_area' => filter_var($item['is_high_risk_area'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * List supervisory_alert_log for fullMaps sidebar (Alert Supervisory tab).
     */
    public function getSupervisoryAlertLogList(Request $request)
    {
        try {
            $limit = min(max((int) $request->get('limit', 100), 1), 500);
            // Default: tanggal hari ini menurut timezone Asia/Makassar. Request boleh kirim ?tanggal=Y-m-d untuk override.
            $tanggal = $request->get('tanggal') ?? now('Asia/Makassar')->format('Y-m-d');

            $query = SupervisoryAlertLog::query()
                ->whereDate('tanggal', $tanggal)
                ->orderBy('tanggal', 'desc')
                ->orderBy('updated_at', 'desc')
                ->limit($limit);

            $rows = $query->get();

            $data = $rows->map(function ($row) {
                return [
                    'id' => $row->id,
                    'tanggal' => $row->tanggal ? $row->tanggal->format('Y-m-d') : null,
                    'id_lokasi' => $row->id_lokasi,
                    'nama_lokasi' => $row->nama_lokasi ?? '',
                    'risk_level' => $row->risk_level ?? '',
                    'has_sap_report' => (bool) $row->has_sap_report,
                    'has_online_cctv' => (bool) $row->has_online_cctv,
                    'is_high_risk_area' => (bool) $row->is_high_risk_area,
                    'tarp_recommendations' => $row->tarp_recommendations ?? [],
                    'cctv_list' => $row->cctv_list ?? [],
                    'sap_list' => $row->sap_list ?? [],
                    'created_at' => $row->created_at ? $row->created_at->toIso8601String() : null,
                    'updated_at' => $row->updated_at ? $row->updated_at->toIso8601String() : null,
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => count($data),
            ]);
        } catch (Exception $e) {
            Log::error('getSupervisoryAlertLogList: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data supervisory alert.',
                'data' => [],
                'total' => 0,
            ], 500);
        }
    }

    /**
     * Get daily operation plans with polygons from MySQL
     * Returns GeoJSON FeatureCollection for display on map
     */
    public function getDailyOperationPlansWithPolygons(Request $request)
    {
        try {
            Log::info('getDailyOperationPlansWithPolygons - Method called, using MySQL database');
            
            // Get all daily operation plans with CCTV relationships
            $plans = DailyOperationPlan::with('cctvs')->get();
            
            Log::info('getDailyOperationPlansWithPolygons - Total plans found: ' . $plans->count());
            
            if ($plans->isEmpty()) {
                Log::info('getDailyOperationPlansWithPolygons - No plans found');
                return response()->json([
                    'success' => true,
                    'data' => [
                        'type' => 'FeatureCollection',
                        'features' => []
                    ]
                ]);
            }

            // Menggunakan koordinat langsung dari tabel daily_operation_plans (latitude, longitude)

            $features = [];
            $processedCount = 0;
            $geometryCount = 0;

            foreach ($plans as $plan) {
                $lokasi = $plan->lokasi;
                $detailLokasi = $plan->detail_lokasi;
                $latitude = $plan->latitude;
                $longitude = $plan->longitude;

                // Skip if no coordinates
                if (empty($latitude) || empty($longitude)) {
                    Log::debug("Skipping plan {$plan->id}: missing latitude or longitude", [
                        'latitude' => $latitude,
                        'longitude' => $longitude
                    ]);
                    continue;
                }

                $processedCount++;

                // Convert latitude and longitude to float (handle comma as decimal separator)
                $lat = is_string($latitude) ? (float) str_replace(',', '.', $latitude) : (float) $latitude;
                $lon = is_string($longitude) ? (float) str_replace(',', '.', $longitude) : (float) $longitude;

                // Validate coordinates
                if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                    Log::warning("Invalid coordinates for plan {$plan->id}", [
                        'latitude' => $lat,
                        'longitude' => $lon
                    ]);
                    continue;
                }

                $geometryCount++;
                
                // Get CCTV data for this DOP
                $cctvData = [];
                if ($plan->cctvs && $plan->cctvs->count() > 0) {
                    foreach ($plan->cctvs as $cctv) {
                        if ($cctv->longitude && $cctv->latitude) {
                            $cctvData[] = [
                                'id' => $cctv->id,
                                'no_cctv' => $cctv->no_cctv ?? null,
                                'nama_cctv' => $cctv->nama_cctv ?? null,
                                'longitude' => (float) $cctv->longitude,
                                'latitude' => (float) $cctv->latitude,
                                'lokasi_pemasangan' => $cctv->lokasi_pemasangan ?? null,
                                'kondisi' => $cctv->kondisi ?? null,
                                'status' => $cctv->status ?? null,
                            ];
                        }
                    }
                }
                
                $effectiveSite = trim((string) ($plan->site ?? ''));
                if ($effectiveSite === '') {
                    $effectiveSite = trim((string) ($plan->unit_id ?? ''));
                }

                // Create Point geometry from coordinates
                $feature = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [$lon, $lat] // GeoJSON format: [longitude, latitude]
                    ],
                    'properties' => [
                        'id' => $plan->id,
                        'site' => $effectiveSite !== '' ? $effectiveSite : null,
                        'pekerjaan' => $plan->pekerjaan ?? null,
                        'unit_id' => $plan->unit_id ?? null,
                        'lokasi' => $lokasi,
                        'detail_lokasi' => $detailLokasi,
                        'potensi_resiko' => $plan->potensi_resiko ?? null,
                        'pengendalian_bahaya' => $plan->pengendalian_bahaya ?? null,
                        'catatan' => $plan->catatan ?? null,
                        'tanggal' => $plan->tanggal ? $plan->tanggal->format('Y-m-d') : null,
                        'foto_pekerjaan' => $plan->foto_pekerjaan ?? null,
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'cctvs' => $cctvData, // Include CCTV data
                    ]
                ];
                $features[] = $feature;
                
                Log::info("Successfully added point feature for plan {$plan->id}", [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'lokasi' => $lokasi,
                    'detail_lokasi' => $detailLokasi
                ]);
            }

            // Get all CCTV from dop_cctv table
            $allDopCctv = DB::table('dop_cctv')
                ->join('cctv_data_bmo2', 'dop_cctv.cctv_id', '=', 'cctv_data_bmo2.id')
                ->whereNotNull('cctv_data_bmo2.longitude')
                ->whereNotNull('cctv_data_bmo2.latitude')
                ->select(
                    'cctv_data_bmo2.id',
                    'cctv_data_bmo2.no_cctv',
                    'cctv_data_bmo2.nama_cctv',
                    'cctv_data_bmo2.longitude',
                    'cctv_data_bmo2.latitude',
                    'cctv_data_bmo2.lokasi_pemasangan',
                    'cctv_data_bmo2.kondisi',
                    'cctv_data_bmo2.status',
                    'cctv_data_bmo2.site',
                    'cctv_data_bmo2.perusahaan',
                    'cctv_data_bmo2.link_akses',
                    'dop_cctv.dop_id'
                )
                ->get();

            // Format CCTV data for frontend
            $cctvList = $allDopCctv->map(function ($cctv) {
                return [
                    'id' => $cctv->id,
                    'no_cctv' => $cctv->no_cctv ?? null,
                    'nama_cctv' => $cctv->nama_cctv ?? null,
                    'longitude' => (float) $cctv->longitude,
                    'latitude' => (float) $cctv->latitude,
                    'lokasi_pemasangan' => $cctv->lokasi_pemasangan ?? null,
                    'kondisi' => $cctv->kondisi ?? null,
                    'status' => $cctv->status ?? null,
                    'site' => $cctv->site ?? null,
                    'perusahaan' => $cctv->perusahaan ?? null,
                    'link_akses' => $cctv->link_akses ?? null,
                    'dop_id' => $cctv->dop_id,
                ];
            })->toArray();

            Log::info('getDailyOperationPlansWithPolygons - Summary', [
                'total_plans' => $plans->count(),
                'processed' => $processedCount,
                'with_coordinates' => $geometryCount,
                'features_returned' => count($features),
                'plans_without_coordinates' => $processedCount - $geometryCount,
                'cctv_count' => count($cctvList)
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'type' => 'FeatureCollection',
                    'features' => $features
                ],
                'cctv_list' => $cctvList, // All CCTV from dop_cctv
                'summary' => [
                    'total_plans' => $plans->count(),
                    'processed' => $processedCount,
                    'with_coordinates' => $geometryCount,
                    'features_returned' => count($features),
                    'plans_without_coordinates' => $processedCount - $geometryCount,
                    'cctv_count' => count($cctvList)
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error getting daily operation plans with polygons: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data rencana kerja dari MySQL: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'data' => [
                    'type' => 'FeatureCollection',
                    'features' => []
                ]
            ], 500);
        }
    }

    /**
     * Get DOPM (IKK) data for full maps IKK layer based on date range.
     * Shows entries where today falls between tanggal_dop (start) and tanggal_selesai_ijin (end).
     * Uses Asia/Jakarta so "hari ini" matches user date in Indonesia.
     */
    public function getDopmIkkToday(Request $request)
    {
        try {
            $tz = config('app.timezone') === 'UTC' ? 'Asia/Jakarta' : config('app.timezone');
            $today = Carbon::today($tz)->format('Y-m-d');
            // Tampilkan DOPM yang rentangnya (tanggal_dop - tanggal_selesai_ijin) mencakup hari ini
            // tanggal_dop <= today AND tanggal_selesai_ijin >= today
            $entries = Dopm::whereDate('tanggal_dop', '<=', $today)
                ->whereDate('tanggal_selesai_ijin', '>=', $today)
                ->orderBy('detail_lokasi')
                ->orderBy('id_dop')
                ->get();

            $data = $entries->map(function ($dopm) {
                return [
                    'id' => $dopm->id,
                    'id_dop' => $dopm->id_dop,
                    'timestamp' => $dopm->timestamp?->format('Y-m-d H:i:s'),
                    'site_ijin_kerja_khusus' => $dopm->site_ijin_kerja_khusus,
                    'perusahaan_ijin_kerja_khusus' => $dopm->perusahaan_ijin_kerja_khusus,
                    'jenis_ijin_kerja_khusus' => $dopm->jenis_ijin_kerja_khusus,
                    'kode_ikk' => $dopm->kode_ikk,
                    'tanggal_selesai_ijin' => $dopm->tanggal_selesai_ijin?->format('Y-m-d'),
                    'nama_pekerjaan' => $dopm->nama_pekerjaan,
                    'tanggal_dop' => $dopm->tanggal_dop?->format('Y-m-d'),
                    'status_pengiriman_notif' => $dopm->status_pengiriman_notif,
                    'status' => $dopm->status,
                    'deskripsi_atau_alasan_cancel' => $dopm->deskripsi_atau_alasan_cancel,
                    'sid_layer_2' => $dopm->sid_layer_2,
                    'nama_layer_2' => $dopm->nama_layer_2,
                    'sid_layer_3' => $dopm->sid_layer_3,
                    'nama_layer_3' => $dopm->nama_layer_3,
                    'sid_layer_4' => $dopm->sid_layer_4,
                    'nama_layer_4' => $dopm->nama_layer_4,
                    'jenis_pengawasan_layer' => $dopm->jenis_pengawasan_layer,
                    'detail_lokasi' => $dopm->detail_lokasi,
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => $data,
                'summary' => [
                    'tanggal' => $today,
                    'total' => count($data),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('getDopmIkkToday error: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data IKK: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get IKK (work permit) data for today from ClickHouse ikk_work_permit.
     * Used by full maps IKK layer: show cards on map (geo_lat, geo_lon) and in notification panel.
     * Filter: today between start_date and end_date (rentang pekerjaan yang sedang aktif).
     * IKK akan ditampilkan jika hari ini berada di antara start_date dan end_date.
     * id, code, name, status, location_name, location_detail_name, ra_site_name, geo_lat, geo_lon, etc.
     */
    public function getIkkWorkPermitToday(Request $request)
    {
        try {
            $tz = 'Asia/Jakarta';
            $today = Carbon::today($tz)->format('Y-m-d');
            $dateEsc = addslashes($today);

            // Tampilkan IKK yang rentang (start_date - end_date) mencakup hari ini
            // Artinya: start_date <= today AND end_date >= today
            $whereDate = "toDate(start_date) <= toDate('{$dateEsc}') AND toDate(end_date) >= toDate('{$dateEsc}')";
            $whereDeleted = 'AND deleted_at IS NULL';
            $whereStatus = "AND trim(upper(toString(status))) = 'APPROVED'";

            // Satu query sesuai schema: id UUID→toString, Nullable(String)→ifNull, DateTime64→toString, geo Nullable(Float64)
            $sql = "
                SELECT
                    toString(id) AS id,
                    ifNull(code, '') AS code,
                    ifNull(name, '') AS name,
                    ifNull(status, '') AS status,
                    ifNull(ra_site_name, '') AS ra_site_name,
                    ifNull(company_name, '') AS company_name,
                    ifNull(ra_pjo_name, '') AS ra_pjo_name,
                    ifNull(location_name, '') AS location_name,
                    ifNull(location_detail_name, '') AS location_detail_name,
                    ifNull(location_description, '') AS location_description,
                    toString(start_date) AS start_date,
                    toString(end_date) AS end_date,
                    toString(submit_date) AS submit_date,
                    geo_lat AS geo_lat,
                    geo_lon AS geo_lon
                FROM hse_automation.ikk_work_permit
                WHERE {$whereDate}
                {$whereDeleted}
                {$whereStatus}
                ORDER BY start_date ASC
            ";

            $rows = $this->queryClickHouseCustom($sql, 'hse_automation');
            if (! is_array($rows)) {
                $rows = [];
            }

            // Ambil employee per work permit untuk Layer 1–4 (cara sama seperti DOPMController)
            $wpIds = array_values(array_unique(array_filter(array_column($rows, 'id'))));
            $layersByWp = [];
            if (! empty($wpIds)) {
                $idList = implode(',', array_map(function ($id) {
                    $id = is_object($id) ? (string) $id : (string) $id;
                    return "'" . addslashes($id) . "'";
                }, $wpIds));
                $sqlEmp = "
                    SELECT toString(work_permit_id) AS work_permit_id, layer, ifNull(toString(employee_name), '') AS employee_name, ifNull(toString(employee_sid), '') AS employee_sid
                    FROM hse_automation.ikk_work_permit_employee
                    WHERE toString(work_permit_id) IN ({$idList})
                ";
                $empRows = $this->queryClickHouseCustom($sqlEmp, 'hse_automation');
                if (is_array($empRows)) {
                    foreach ($empRows as $er) {
                        $er = is_object($er) ? (array) $er : $er;
                        $wpId = $er['work_permit_id'] ?? null;
                        if ($wpId === null || $wpId === '') {
                            continue;
                        }
                        $layerRaw = $er['layer'] ?? null;
                        if ($layerRaw === null || $layerRaw === '') {
                            continue;
                        }
                        $layerNum = (int) $layerRaw;
                        if (! in_array($layerNum, [1, 2, 3, 4], true)) {
                            continue;
                        }
                        if (! isset($layersByWp[$wpId])) {
                            $layersByWp[$wpId] = [];
                        }
                        if (! isset($layersByWp[$wpId][$layerNum])) {
                            $layersByWp[$wpId][$layerNum] = [
                                'name' => trim((string) ($er['employee_name'] ?? '')),
                                'sid' => trim((string) ($er['employee_sid'] ?? '')),
                            ];
                        }
                    }
                }
            }

            $data = [];
            foreach ($rows as $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                $wpId = $row['id'] ?? null;
                $layers = isset($wpId) ? ($layersByWp[$wpId] ?? []) : [];
                $namaLayer1 = isset($layers[1]) ? $layers[1]['name'] : '';
                $namaLayer2 = isset($layers[2]) ? $layers[2]['name'] : '';
                $namaLayer3 = isset($layers[3]) ? $layers[3]['name'] : '';
                $namaLayer4 = isset($layers[4]) ? $layers[4]['name'] : '';
                $data[] = [
                    'id' => $wpId,
                    'code' => $row['code'] ?? null,
                    'name' => $row['name'] ?? '',
                    'status' => $row['status'] ?? '',
                    'ra_site_name' => $row['ra_site_name'] ?? '',
                    'company_name' => $row['company_name'] ?? '',
                    'ra_pjo_name' => $row['ra_pjo_name'] ?? '',
                    'location_name' => $row['location_name'] ?? '',
                    'location_detail_name' => $row['location_detail_name'] ?? '',
                    'location_description' => $row['location_description'] ?? '',
                    'geo_lat' => isset($row['geo_lat']) && $row['geo_lat'] !== '' ? (float) $row['geo_lat'] : null,
                    'geo_lon' => isset($row['geo_lon']) && $row['geo_lon'] !== '' ? (float) $row['geo_lon'] : null,
                    'start_date' => $row['start_date'] ?? '',
                    'end_date' => $row['end_date'] ?? '',
                    'submit_date' => $row['submit_date'] ?? '',
                    'detail_lokasi' => trim(($row['location_name'] ?? '') . ' ' . ($row['location_detail_name'] ?? '')),
                    'nama_layer_1' => $namaLayer1,
                    'nama_layer_2' => $namaLayer2,
                    'nama_layer_3' => $namaLayer3,
                    'nama_layer_4' => $namaLayer4,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'summary' => [
                    'tanggal' => $today,
                    'total' => count($data),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('getIkkWorkPermitToday error: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data IKK work permit: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Data IKK hari ini yang belum ada IPK atau belum ada OKK (untuk sidebar Control Room / DOP & IKK).
     * Sumber: ClickHouse ikk_work_permit + ipk_ikk (MySQL) + okk (MySQL).
     */
    public function getIkkForControlroomSidebar(Request $request)
    {
        try {
            $tz = 'Asia/Jakarta';
            $today = Carbon::today($tz)->format('Y-m-d');
            $dateEsc = addslashes($today);

            $sql = "
                SELECT
                    id,
                    code,
                    name,
                    ra_site_name,
                    company_name,
                    status,
                    m_job_id,
                    start_date,
                    end_date,
                    location_name,
                    location_detail_name
                FROM hse_automation.ikk_work_permit
                WHERE toDate(start_date) <= toDate('{$dateEsc}')
                  AND toDate(end_date)   >= toDate('{$dateEsc}')
                  AND deleted_at IS NULL
                  AND trim(upper(toString(status))) = 'APPROVED'
                ORDER BY start_date ASC
            ";
            $wpRows = $this->queryClickHouseCustom($sql, 'hse_automation');
            if (! is_array($wpRows)) {
                $wpRows = [];
            }

            $jobIds = array_values(array_unique(array_filter(array_column($wpRows, 'm_job_id'))));
            $jobNamesById = [];
            if (! empty($jobIds)) {
                $inJobs = implode(',', array_map(function ($id) {
                    $id = is_object($id) ? (string) $id : (string) $id;
                    return "'" . addslashes($id) . "'";
                }, $jobIds));
                $sqlJobs = "SELECT id, name FROM hse_automation.ikk_m_job WHERE id IN ({$inJobs})";
                $jobRows = $this->queryClickHouseCustom($sqlJobs, 'hse_automation');
                if (is_array($jobRows)) {
                    foreach ($jobRows as $jr) {
                        $jr = is_array($jr) ? $jr : (array) $jr;
                        $jobNamesById[$jr['id'] ?? ''] = $jr['name'] ?? null;
                    }
                }
            }

            $wpIds = array_values(array_unique(array_filter(array_map(function ($r) {
                $r = is_array($r) ? $r : (array) $r;
                return $r['id'] ?? null;
            }, $wpRows))));
            $layersByWp = [];
            if (! empty($wpIds)) {
                $idList = implode(',', array_map(function ($id) {
                    $id = is_object($id) ? (string) $id : (string) $id;
                    return "'" . addslashes($id) . "'";
                }, $wpIds));
                $sqlEmp = "
                    SELECT toString(work_permit_id) AS work_permit_id, layer,
                           ifNull(toString(employee_name), '') AS employee_name,
                           ifNull(toString(employee_sid), '') AS employee_sid
                    FROM hse_automation.ikk_work_permit_employee
                    WHERE toString(work_permit_id) IN ({$idList})
                ";
                $empRows = $this->queryClickHouseCustom($sqlEmp, 'hse_automation');
                if (is_array($empRows)) {
                    foreach ($empRows as $er) {
                        $er = is_array($er) ? $er : (array) $er;
                        $wpId = $er['work_permit_id'] ?? null;
                        if ($wpId === null || $wpId === '') {
                            continue;
                        }
                        $layerNum = (int) ($er['layer'] ?? 0);
                        if (! in_array($layerNum, [1, 2, 3, 4], true)) {
                            continue;
                        }
                        if (! isset($layersByWp[$wpId])) {
                            $layersByWp[$wpId] = [];
                        }
                        if (! isset($layersByWp[$wpId][$layerNum])) {
                            $layersByWp[$wpId][$layerNum] = [
                                'name' => trim((string) ($er['employee_name'] ?? '')),
                                'sid' => trim((string) ($er['employee_sid'] ?? '')),
                            ];
                        }
                    }
                }
            }

            $listByCode = [];
            foreach ($wpRows as $row) {
                $row = is_array($row) ? $row : (array) $row;
                $wpId = $row['id'] ?? null;
                $code = $row['code'] ?? null;
                if ($code === null || $code === '') {
                    continue;
                }
                if (isset($listByCode[$code])) {
                    continue;
                }
                $layers = isset($wpId) ? ($layersByWp[$wpId] ?? []) : [];
                $mJobId = $row['m_job_id'] ?? null;
                $listByCode[$code] = [
                    'id' => $wpId,
                    'code' => $code,
                    'site' => $row['ra_site_name'] ?? null,
                    'jenis_ijin_kerja_khusus' => $mJobId ? ($jobNamesById[$mJobId] ?? null) : null,
                    'nama_pekerjaan' => $row['name'] ?? null,
                    'perusahaan' => $row['company_name'] ?? null,
                    'status' => $row['status'] ?? null,
                    'location_name' => $row['location_name'] ?? null,
                    'location_detail_name' => $row['location_detail_name'] ?? null,
                    'nama_layer_1' => $layers[1]['name'] ?? null,
                    'sid_layer_1' => $layers[1]['sid'] ?? null,
                    'nama_layer_2' => $layers[2]['name'] ?? null,
                    'sid_layer_2' => $layers[2]['sid'] ?? null,
                    'nama_layer_3' => $layers[3]['name'] ?? null,
                    'sid_layer_3' => $layers[3]['sid'] ?? null,
                    'nama_layer_4' => $layers[4]['name'] ?? null,
                    'sid_layer_4' => $layers[4]['sid'] ?? null,
                ];
            }

            $kodeIkks = array_keys($listByCode);
            $statusByKode = [];
            if (! empty($kodeIkks)) {
                $statusRows = IpkIkk::whereIn('kode_ikk', $kodeIkks)
                    ->orderByDesc('ts')
                    ->get(['kode_ikk', 'status_pekerjaan']);
                foreach ($statusRows as $srow) {
                    $k = $srow->kode_ikk;
                    if ($k !== null && $k !== '' && ! isset($statusByKode[$k])) {
                        $statusByKode[$k] = $srow->status_pekerjaan;
                    }
                }
            }

            $okkKodesToday = Okk::whereDate('ts', $today)->pluck('kode_ikk')->unique()->flip()->all();

            $out = [];
            foreach ($listByCode as $code => $item) {
                $statusPekerjaan = $statusByKode[$code] ?? null;
                $hasOkk = isset($okkKodesToday[$code]);
                $belumIpk = $statusPekerjaan === null || $statusPekerjaan === '' || (is_string($statusPekerjaan) && stripos($statusPekerjaan, 'belum') !== false);
                $belumOkk = ! $hasOkk;
                if (! $belumIpk && ! $belumOkk) {
                    continue;
                }
                $item['status_pekerjaan'] = $statusPekerjaan ?? 'Belum ada IPK';
                $item['status_matriks'] = (! $belumIpk && $belumOkk) ? 'Kuning' : (($belumIpk && ! $belumOkk) ? 'Kuning' : 'Merah');
                $item['tanggal_dop'] = $today;
                $out[] = $item;
            }

            return response()->json([
                'success' => true,
                'data' => array_values($out),
                'total' => count($out),
            ]);
        } catch (Exception $e) {
            Log::error('getIkkForControlroomSidebar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data IKK untuk sidebar.',
                'data' => [],
                'total' => 0,
            ], 500);
        }
    }

    /**
     * Map jenis_ijin_kerja_khusus (DOPM) to Activity / Sub Activity for ClickHouse OAK
     */
    private function mapJenisIjinToOakActivity(string $jenis): array
    {
        $j = $jenis;
        if (stripos($j, 'IKDP') !== false || stripos($j, 'Ijin Kerja Dengan Panas') !== false) {
            return ['activity' => 'Bekerja dengan Panas', 'sub_activity' => null];
        }
        if (stripos($j, 'IKDA') !== false || stripos($j, 'Dekat/Atas Air') !== false) {
            return ['activity' => 'Bekerja di Dekat Air', 'sub_activity' => null];
        }
        if (stripos($j, 'IKPM') !== false || stripos($j, 'Pengangkatan') !== false) {
            return ['activity' => 'Pengangkatan & Pengangkutan Unit/Material', 'sub_activity' => null];
        }
        if (stripos($j, 'IKDW') !== false || stripos($j, 'Diluar Workshop') !== false) {
            return ['activity' => 'Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang', 'sub_activity' => null];
        }
        if (stripos($j, 'IKDK') !== false || stripos($j, 'Ketinggian') !== false) {
            $sub = null;
            if (stripos($j, 'Perancah') !== false && stripos($j, '10 Meter') !== false && (stripos($j, 'Diatas') !== false || stripos($j, '>10') !== false)) {
                $sub = 'Ketinggian >10 m'; // with Perancah
            } elseif (stripos($j, 'Perancah') !== false && (stripos($j, 'Dibawah') !== false || stripos($j, '<10') !== false)) {
                $sub = 'Ketinggian <10 m';
            } elseif (stripos($j, 'Diatas 10') !== false || stripos($j, '>10') !== false) {
                $sub = 'Ketinggian >10 m';
            } elseif (stripos($j, 'Dibawah 10') !== false || stripos($j, '<10') !== false) {
                $sub = 'Ketinggian <10 m';
            }
            return ['activity' => 'Bekerja di Ketinggian', 'sub_activity' => $sub];
        }
        return ['activity' => null, 'sub_activity' => null];
    }

    /**
     * Get data for IKK modal: IPK IKK (by kode_ikk), OKK (by kode_ikk), OAK from ClickHouse (by Activity from jenis_ijin + SID)
     */
    public function getIkkModalData(Request $request)
    {
        try {
            $kodeIkk = $request->input('kode_ikk');
            $jenisIjin = $request->input('jenis_ijin_kerja_khusus', '');
            $namaLayer2 = $request->input('nama_layer_2', '');
            $namaLayer3 = $request->input('nama_layer_3', '');
            $namaLayer4 = $request->input('nama_layer_4', '');
            $sidLayer2 = $request->input('sid_layer_2', '');
            $sidLayer3 = $request->input('sid_layer_3', '');
            $sidLayer4 = $request->input('sid_layer_4', '');

            $ipkIkk = [];
            $okk = [];
            $oak = [];

            if ($kodeIkk) {
                $ipkIkk = IpkIkk::where('kode_ikk', $kodeIkk)->orderBy('ts', 'desc')->get()->map(function ($row) {
                    return $row->toArray();
                })->toArray();
                $okk = Okk::where('kode_ikk', $kodeIkk)->orderBy('ts', 'desc')->get()->map(function ($row) {
                    return $row->toArray();
                })->toArray();
            }

            $mapped = $this->mapJenisIjinToOakActivity($jenisIjin ?? '');
            $activity = $mapped['activity'];
            $subActivity = $mapped['sub_activity'];

            if ($activity) {
                $sids = array_filter([$sidLayer2, $sidLayer3, $sidLayer4]);
                $names = array_filter([$namaLayer2, $namaLayer3, $namaLayer4]);
                $sql = "
                    SELECT 
                        toString(id) as id,
                        toString(activity) as activity,
                        toString(sub_activity) as sub_activity,
                        toString(tool_type) as tool_type,
                        toString(location) as location,
                        toString(detail_location) as detail_location,
                        toString(conclusion) as conclusion,
                        toString(submit_date) as submit_date,
                        toString(company_submit_by) as company_submit_by,
                        toString(submit_by) as submit_by,
                        toString(kode_sid_pelapor) as kode_sid_pelapor,
                        toString(kode_sid_team) as kode_sid_team,
                        toString(nama_team) as nama_team,
                        ifNull(toString(latitude), '') as latitude,
                        ifNull(toString(longitude), '') as longitude,
                        ifNull(toString(site), '') as site
                    FROM hse_automation.aaj_vw_car_oak_register_ytd_only
                    WHERE 1=1
                    AND (trim(lower(toString(tipe))) = 'observe' OR trim(lower(toString(tipe))) = 'observee')
                    AND trim(lower(toString(activity))) = trim(lower('" . addslashes($activity) . "'))
                ";
                if ($subActivity) {
                    $sql .= " AND trim(lower(toString(sub_activity))) LIKE trim(lower('%" . addslashes($subActivity) . "%'))";
                }
                if (!empty($sids)) {
                    $inList = implode(',', array_map(function ($s) {
                        return "'" . addslashes($s) . "'";
                    }, $sids));
                    $sql .= " AND (toString(kode_sid) IN ({$inList}) OR toString(kode_sid_pelapor) IN ({$inList}) OR toString(kode_sid_team) IN ({$inList}))";
                }
                $sql .= " ORDER BY toDateTime(submit_date) DESC LIMIT 100";
                $oak = $this->queryClickHouseCustom($sql, 'hse_automation');
                if (!is_array($oak)) {
                    $oak = [];
                }
            }

            return response()->json([
                'success' => true,
                'ipk_ikk' => $ipkIkk,
                'okk' => $okk,
                'oak' => $oak,
                'dopm_context' => [
                    'nama_layer_2' => $namaLayer2,
                    'nama_layer_3' => $namaLayer3,
                    'nama_layer_4' => $namaLayer4,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('getIkkModalData error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'ipk_ikk' => [],
                'okk' => [],
                'oak' => [],
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
     * Validate and normalize coordinates structure for GeoJSON
     * Ensures coordinates have correct nested array structure
     */
    private function validateAndNormalizeCoordinates($coordinates, $geometryType)
    {
        if (!is_array($coordinates)) {
            return null;
        }
        
        try {
            switch ($geometryType) {
                case 'Point':
                    // Point: [lon, lat]
                    if (count($coordinates) >= 2 && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
                        return [(float)$coordinates[0], (float)$coordinates[1]];
                    }
                    return null;
                    
                case 'LineString':
                    // LineString: [[lon, lat], [lon, lat], ...]
                    $normalized = [];
                    foreach ($coordinates as $coord) {
                        if (is_array($coord) && count($coord) >= 2 && is_numeric($coord[0]) && is_numeric($coord[1])) {
                            $normalized[] = [(float)$coord[0], (float)$coord[1]];
                        }
                    }
                    return count($normalized) >= 2 ? $normalized : null;
                    
                case 'Polygon':
                    // Polygon: [[[lon, lat], [lon, lat], ...], ...] (first ring is exterior, rest are holes)
                    $normalized = [];
                    
                    // Handle case where coordinates might be incorrectly nested
                    // Sometimes coordinates might be [[[lon, lat], ...]] instead of [[[lon, lat], ...], ...]
                    if (count($coordinates) === 1 && is_array($coordinates[0])) {
                        $firstItem = $coordinates[0];
                        // Check if first item is already a ring (array of [lon, lat])
                        if (is_array($firstItem) && count($firstItem) > 0) {
                            if (is_array($firstItem[0]) && count($firstItem[0]) >= 2) {
                                // Already correct structure: [[[lon, lat], ...]]
                                $coordinates = [$firstItem];
                            } elseif (is_numeric($firstItem[0]) && count($firstItem) >= 2) {
                                // This is a single coordinate pair, wrap it: [[[lon, lat]]]
                                $coordinates = [[$firstItem]];
                            }
                        }
                    }
                    
                    foreach ($coordinates as $ring) {
                        if (!is_array($ring)) {
                            Log::warning("Polygon ring is not an array", ['ring_type' => gettype($ring)]);
                            continue;
                        }
                        $normalizedRing = [];
                        foreach ($ring as $coord) {
                            if (is_array($coord) && count($coord) >= 2) {
                                // Ensure both values are numeric
                                $lon = is_numeric($coord[0]) ? (float)$coord[0] : null;
                                $lat = is_numeric($coord[1]) ? (float)$coord[1] : null;
                                if ($lon !== null && $lat !== null) {
                                    $normalizedRing[] = [$lon, $lat];
                                }
                            }
                        }
                        if (count($normalizedRing) >= 4) { // Minimum 4 points for closed polygon
                            // Ensure ring is closed
                            $first = $normalizedRing[0];
                            $last = $normalizedRing[count($normalizedRing) - 1];
                            if (abs($first[0] - $last[0]) > 0.000001 || abs($first[1] - $last[1]) > 0.000001) {
                                $normalizedRing[] = $first;
                            }
                            $normalized[] = $normalizedRing;
                        } else {
                            Log::warning("Polygon ring has insufficient points", [
                                'point_count' => count($normalizedRing),
                                'minimum_required' => 4
                            ]);
                        }
                    }
                    return count($normalized) > 0 ? $normalized : null;
                    
                case 'MultiPolygon':
                    // MultiPolygon: [[[[lon, lat], ...], ...], ...]
                    $normalized = [];
                    foreach ($coordinates as $polygon) {
                        if (!is_array($polygon)) {
                            continue;
                        }
                        $normalizedPolygon = [];
                        foreach ($polygon as $ring) {
                            if (!is_array($ring)) {
                                continue;
                            }
                            $normalizedRing = [];
                            foreach ($ring as $coord) {
                                if (is_array($coord) && count($coord) >= 2 && is_numeric($coord[0]) && is_numeric($coord[1])) {
                                    $normalizedRing[] = [(float)$coord[0], (float)$coord[1]];
                                }
                            }
                            if (count($normalizedRing) >= 4) {
                                // Ensure ring is closed
                                $first = $normalizedRing[0];
                                $last = $normalizedRing[count($normalizedRing) - 1];
                                if ($first[0] != $last[0] || $first[1] != $last[1]) {
                                    $normalizedRing[] = $first;
                                }
                                $normalizedPolygon[] = $normalizedRing;
                            }
                        }
                        if (count($normalizedPolygon) > 0) {
                            $normalized[] = $normalizedPolygon;
                        }
                    }
                    return count($normalized) > 0 ? $normalized : null;
                    
                default:
                    Log::warning("Unsupported geometry type: {$geometryType}");
                    return null;
            }
        } catch (Exception $e) {
            Log::error("Error validating coordinates: " . $e->getMessage());
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

    /**
     * Get location data with SAP counts for incident prediction matrix
     */
    public function getLocationSapCounts(Request $request)
    {
        try {
            $clickHouseService = new ClickHouseService();
            
            if (!$clickHouseService->isConnected()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ClickHouse tidak terhubung',
                    'data' => []
                ]);
            }

            // Query to get location data with SAP counts grouped by nama_lokasi
            $sql = "
                SELECT 
                    ifNull(toString(nama_lokasi), '') as nama_lokasi,
                    COUNT(*) AS jumlah_sap
                FROM aaj_car_all_year_from_dav
                WHERE nama_lokasi IS NOT NULL 
                    AND nama_lokasi != ''
                GROUP BY nama_lokasi
                ORDER BY jumlah_sap DESC
            ";

            // Menggunakan queryClickHouseCustom dengan database 'hse_automation' dan koneksi ke 10.10.10.38
            $results = $this->queryClickHouseCustom($sql, 'hse_automation');
            
            $locations = [];
            foreach ($results as $row) {
                $locations[] = [
                    'nama_lokasi' => $row['nama_lokasi'] ?? '',
                    'jumlah_sap' => (int)($row['jumlah_sap'] ?? 0)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $locations
            ]);

        } catch (Exception $e) {
            Log::error('Error getting location SAP counts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ]);
        }
    }

    /**
     * Get latest CCTV alert data
     */
    public function getLatestCctvAlert()
    {
        try {
            $latestAlert = DB::table('cctv_alerts')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$latestAlert) {
                return response()->json([
                    'success' => false,
                    'message' => 'No alert data found',
                    'data' => null
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $latestAlert->id,
                    'site' => $latestAlert->site,
                    'tanggal' => $latestAlert->tanggal,
                    'jumlah_offline' => $latestAlert->jumlah_offline,
                    'jumlah_online' => $latestAlert->jumlah_online,
                    'message_id' => $latestAlert->message_id,
                    'created_at' => $latestAlert->created_at
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error getting latest CCTV alert: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get CCTV alerts with units data for sidebar
     */
    public function getCctvAlertsWithUnits()
    {
        try {
            // Get CCTV alerts data
            $alerts = DB::table('cctv_alerts')
                ->select(
                    'cctv_alerts.id',
                    'cctv_alerts.site',
                    'cctv_alerts.tanggal',
                    'cctv_alerts.jumlah_offline',
                    'cctv_alerts.jumlah_online',
                    'cctv_alerts.message_id',
                    'cctv_alerts.created_at'
                )
                ->orderBy('cctv_alerts.tanggal', 'desc')
                ->orderBy('cctv_alerts.site')
                ->get();
            
            // Get CCTV units for each alert
            $groupedData = [];
            foreach ($alerts as $alert) {
                // Get CCTV units for this alert
                $cctvUnits = DB::table('cctv_units')
                    ->where('alert_id', $alert->id)
                    ->select(
                        'cctv_units.id',
                        'cctv_units.alert_id',
                        'cctv_units.unit_code',
                        'cctv_units.location',
                        'cctv_units.last_connect',
                        'cctv_units.status',
                        'cctv_units.created_at'
                    )
                    ->orderBy('cctv_units.unit_code')
                    ->get();
                
                $groupedData[] = [
                    'id' => $alert->id,
                    'site' => $alert->site,
                    'tanggal' => $alert->tanggal,
                    'jumlah_offline' => $alert->jumlah_offline,
                    'jumlah_online' => $alert->jumlah_online,
                    'message_id' => $alert->message_id,
                    'created_at' => $alert->created_at,
                    'cctv_units' => $cctvUnits->map(function($unit) {
                        return [
                            'id' => $unit->id,
                            'alert_id' => $unit->alert_id,
                            'unit_code' => $unit->unit_code,
                            'location' => $unit->location,
                            'last_connect' => $unit->last_connect,
                            'status' => $unit->status,
                            'created_at' => $unit->created_at,
                        ];
                    })->toArray()
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $groupedData,
                'count' => count($groupedData)
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching CCTV alerts with units: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Query ClickHouse dengan koneksi custom
     */
    private function queryClickHouseCustom($sql, $database = 'hse_automation')
    {
        try {
            $host = '10.10.10.38';
            $port = 8123;
            $protocol = 'http';
            $baseUrl = $protocol . '://' . $host . ':' . $port;
            $username = 'default';
            $password = 'Zxcdsaqwe321:;';
            $timeout = 60; // Increase timeout for large queries

            $url = $baseUrl . '/?database=' . urlencode($database) . '&default_format=JSON';
            
            Log::info('ClickHouse Query', [
                'url' => $url,
                'database' => $database,
                'sql_preview' => substr($sql, 0, 200) . '...'
            ]);
            
            $httpClient = Http::timeout($timeout)
                ->withBasicAuth($username, $password)
                ->withBody($sql, 'text/plain');
            
            $response = $httpClient->post($url);

            if (!$response->successful()) {
                Log::error('ClickHouse custom query failed', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                    'url' => $url
                ]);
                return [];
            }

            $body = $response->body();
            Log::debug('ClickHouse response body preview', [
                'body_length' => strlen($body),
                'body_preview' => substr($body, 0, 500)
            ]);

            // Try to parse as JSON
            $result = json_decode($body, true);
            
            // Check for JSON decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('ClickHouse response is not valid JSON', [
                    'json_error' => json_last_error_msg(),
                    'body_preview' => substr($body, 0, 500)
                ]);
                
                // Try to parse as JSON lines format (NDJSON)
                $lines = explode("\n", trim($body));
                $data = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line)) {
                        $decoded = json_decode($line, true);
                        if ($decoded !== null && json_last_error() === JSON_ERROR_NONE) {
                            $data[] = $decoded;
                        }
                    }
                }
                
                if (!empty($data)) {
                    Log::info('Parsed ClickHouse response as JSON Lines', ['count' => count($data)]);
                    return $data;
                }
                
                return [];
            }
            
            // Parse ClickHouse JSON response
            // ClickHouse returns array of objects directly when using JSON format
            if (is_array($result)) {
                if (isset($result['data'])) {
                    Log::info('ClickHouse response has data key', ['count' => count($result['data'])]);
                    return $result['data'];
                } elseif (!empty($result) && isset($result[0])) {
                    Log::info('ClickHouse response is array', ['count' => count($result)]);
                    return $result;
                } elseif (empty($result)) {
                    Log::info('ClickHouse response is empty array');
                    return [];
                }
            }
            
            Log::warning('ClickHouse response format unexpected', [
                'result_type' => gettype($result),
                'result_preview' => is_array($result) ? array_slice($result, 0, 3) : $result
            ]);
            
            return [];
        } catch (Exception $e) {
            Log::error('Error in queryClickHouseCustom', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function searchCctv(Request $request)
    {
        $query = $request->input('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return response()->json([]);
        }

        // Get logged-in user for filtering
        $user = Auth::user();
        $userName = $user ? $user->name : null;
        $supervisedControlRooms = [];
        
        if ($userName) {
            $pengawasRecords = CctvControlRoomPengawas::where('nama_pengawas', $userName)->get();
            $supervisedControlRooms = $pengawasRecords->pluck('control_room')->filter()->unique()->toArray();
        }

        // Search in multiple fields
        $searchQuery = CctvData::where(function($q) use ($query) {
            $q->where('nama_cctv', 'LIKE', '%' . $query . '%')
              ->orWhere('no_cctv', 'LIKE', '%' . $query . '%')
              ->orWhere('lokasi_pemasangan', 'LIKE', '%' . $query . '%')
              ->orWhere('site', 'LIKE', '%' . $query . '%')
              ->orWhere('control_room', 'LIKE', '%' . $query . '%')
              ->orWhere('fungsi_cctv', 'LIKE', '%' . $query . '%')
              ->orWhere('kategori', 'LIKE', '%' . $query . '%');
        });

        // Apply filter for supervised control rooms if user is not admin
        if ($userName && !empty($supervisedControlRooms)) {
            $searchQuery->whereIn('control_room', $supervisedControlRooms);
        }

        $cctvResults = $searchQuery->limit(10)->get();

        $formattedCctv = $cctvResults->map(function($cctv) {
            return [
                'type' => 'cctv',
                'id' => $cctv->id,
                'nama_cctv' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                'no_cctv' => $cctv->no_cctv ?? null,
                'lokasi_pemasangan' => $cctv->lokasi_pemasangan ?? null,
                'site' => $cctv->site ?? null,
                'control_room' => $cctv->control_room ?? null,
                'status' => $cctv->status ?? null,
                'kondisi' => $cctv->kondisi ?? null,
                'latitude' => $cctv->latitude ?? null,
                'longitude' => $cctv->longitude ?? null,
                'has_location' => !is_null($cctv->latitude) && !is_null($cctv->longitude),
            ];
        });

        // Search insiden_tabel (multiple text columns)
        $insidenQuery = InsidenTabel::where(function($q) use ($query) {
            $q->where('no_kecelakaan', 'LIKE', '%' . $query . '%')
              ->orWhere('kode_be_investigasi', 'LIKE', '%' . $query . '%')
              ->orWhere('perusahaan', 'LIKE', '%' . $query . '%')
              ->orWhere('departemen', 'LIKE', '%' . $query . '%')
              ->orWhere('site', 'LIKE', '%' . $query . '%')
              ->orWhere('lokasi', 'LIKE', '%' . $query . '%')
              ->orWhere('sublokasi', 'LIKE', '%' . $query . '%')
              ->orWhere('lokasi_spesifik', 'LIKE', '%' . $query . '%')
              ->orWhere('pja', 'LIKE', '%' . $query . '%')
              ->orWhere('kategori', 'LIKE', '%' . $query . '%')
              ->orWhere('injury_status', 'LIKE', '%' . $query . '%')
              ->orWhere('kronologis', 'LIKE', '%' . $query . '%')
              ->orWhere('high_potential', 'LIKE', '%' . $query . '%')
              ->orWhere('alat_terlibat', 'LIKE', '%' . $query . '%')
              ->orWhere('nama', 'LIKE', '%' . $query . '%')
              ->orWhere('jabatan', 'LIKE', '%' . $query . '%')
              ->orWhere('npk', 'LIKE', '%' . $query . '%')
              ->orWhere('sumber_kecelakaan', 'LIKE', '%' . $query . '%')
              ->orWhere('layer', 'LIKE', '%' . $query . '%')
              ->orWhere('detail_layer', 'LIKE', '%' . $query . '%')
              ->orWhere('klasifikasi_layer', 'LIKE', '%' . $query . '%')
              ->orWhere('keterangan_layer', 'LIKE', '%' . $query . '%')
              ->orWhere('status_lpi', 'LIKE', '%' . $query . '%');
        });

        $insidenResults = $insidenQuery->limit(10)->get();

        $formattedInsiden = $insidenResults->map(function($row) {
            $lat = $row->latitude;
            $lng = $row->longitude;
            $hasLocation = !is_null($lat) && $lat !== '' && !is_null($lng) && $lng !== '';
            return [
                'type' => 'insiden',
                'id' => $row->id,
                'nama_cctv' => $row->no_kecelakaan ?? 'Insiden #' . $row->id,
                'no_cctv' => null,
                'site' => $row->site ?? null,
                'lokasi' => $row->lokasi ?? null,
                'kategori' => $row->kategori ?? null,
                'latitude' => $hasLocation ? $lat : null,
                'longitude' => $hasLocation ? $lng : null,
                'has_location' => $hasLocation,
            ];
        });

        $formattedResults = $formattedCctv->concat($formattedInsiden)->values()->all();

        return response()->json($formattedResults);
    }

    /**
     * Get unit vehicles for fullMaps from ClickHouse Nitip unit_gps_logs.
     * Only today's data (by updated_at), one row per unit (latest updated_at per unit_id).
     */
    private function getUnitVehiclesFromClickHouseNitip(): array
    {
        try {
            $ch = $this->getClickHouseNitip();
            if (!$ch->isConnected()) {
                Log::info('ClickHouse Nitip not connected, unit vehicles will be empty.');
                return [];
            }

            // Subquery: alias updated_at AS ts so outer aggregates use ts (avoids "aggregate inside aggregate").
            $sql = "
                SELECT 
                    unit_id,
                    argMax(id, ts) AS id,
                    argMax(integration_id, ts) AS integration_id,
                    argMax(latitude, ts) AS latitude,
                    argMax(longitude, ts) AS longitude,
                    argMax(speed, ts) AS speed,
                    argMax(course, ts) AS course,
                    argMax(battery, ts) AS battery,
                    argMax(heading, ts) AS heading,
                    argMax(vehicle_number, ts) AS vehicle_number,
                    argMax(vehicle_name, ts) AS vehicle_name,
                    argMax(vendor_name, ts) AS vendor_name,
                    argMax(vendor_type, ts) AS vendor_type,
                    argMax(vehicle_type, ts) AS vehicle_type,
                    argMax(timezone, ts) AS timezone,
                    argMax(user_id, ts) AS user_id,
                    max(ts) AS updated_at,
                    argMax(created_at, ts) AS created_at
                FROM (
                    SELECT unit_id, id, integration_id, latitude, longitude, speed, course, battery, heading,
                           vehicle_number, vehicle_name, vendor_name, vendor_type, vehicle_type, timezone, user_id,
                           updated_at AS ts, created_at
                    FROM nitip.unit_gps_logs
                    WHERE toDate(updated_at) = today()
                      AND is_unit = true
                      AND latitude IS NOT NULL
                      AND longitude IS NOT NULL
                      AND toFloat64OrZero(latitude) != 0
                      AND toFloat64OrZero(longitude) != 0
                )
                GROUP BY unit_id
                ORDER BY max(ts) DESC
            ";
            $rows = $ch->query($sql);
            if (!is_array($rows)) {
                return [];
            }

            $list = [];
            foreach ($rows as $row) {
                $lat = $row['latitude'] ?? null;
                $lon = $row['longitude'] ?? null;
                if ($lat === null || $lon === null || (float) $lat === 0.0 || (float) $lon === 0.0) {
                    continue;
                }
                $list[] = [
                    'id' => $row['id'] ?? null,
                    'unit_id' => $row['unit_id'] ?? null,
                    'integration_id' => $row['integration_id'] ?? null,
                    'latitude' => (float) $lat,
                    'longitude' => (float) $lon,
                    'course' => isset($row['course']) ? (float) $row['course'] : 0,
                    'speed' => isset($row['speed']) ? (float) $row['speed'] : null,
                    'heading' => isset($row['heading']) ? (float) $row['heading'] : null,
                    'battery' => isset($row['battery']) ? (float) $row['battery'] : 0,
                    'vehicle_type' => $row['vehicle_type'] ?? 'Unknown',
                    'vehicle_number' => $row['vehicle_number'] ?? 'N/A',
                    'vehicle_name' => $row['vehicle_name'] ?? 'N/A',
                    'vendor_name' => $row['vendor_name'] ?? 'N/A',
                    'vendor_type' => $row['vendor_type'] ?? null,
                    'user_id' => $row['user_id'] ?? null,
                    'is_unit' => true,
                    'timezone' => $row['timezone'] ?? null,
                    'created_at' => $row['created_at'] ?? null,
                    'updated_at' => $row['updated_at'] ?? null,
                    'user' => null,
                ];
            }
            Log::info('Unit vehicles from ClickHouse Nitip unit_gps_logs (today, latest per unit)', ['count' => count($list)]);
            return $list;
        } catch (Exception $e) {
            Log::warning('getUnitVehiclesFromClickHouseNitip failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get unit vehicles via API (for refreshUnitVehicles in fullMaps).
     * Source: ClickHouse Nitip unit_gps_logs only (today, latest per unit).
     */
    public function getUnitVehicles(Request $request)
    {
        try {
            $unitVehicles = $this->getUnitVehiclesFromClickHouseNitip();
            return response()->json([
                'success' => true,
                'unitVehicles' => $unitVehicles,
                'count' => count($unitVehicles),
            ]);
        } catch (Exception $e) {
            Log::error('fullMaps getUnitVehicles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'unitVehicles' => [],
            ], 500);
        }
    }

    /**
     * Get units and latest GPS from ClickHouse nitip (Evaluasi Fuelling Unit).
     * Data: nitip.units + nitip.unit_gps_logs (latest per unit).
     */
    public function getNitipUnits()
    {
        try {
            $ch = $this->getClickHouseNitip();
            if (!$ch->isConnected()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ClickHouse Nitip tidak terhubung. Periksa konfigurasi CLICKHOUSE_NITIP_* di .env',
                    'data' => [],
                ], 503);
            }

            $sqlUnits = "
                SELECT 
                    id,
                    vehicle_name,
                    vehicle_number,
                    integration_id,
                    last_latitude,
                    last_longitude,
                    last_battery,
                    last_course,
                    vendor_name,
                    vendor_type,
                    vehicle_type,
                    created_at,
                    updated_at
                FROM nitip.units
                ORDER BY vehicle_name ASC
            ";
            $units = $ch->query($sqlUnits);
            if (!is_array($units)) {
                $units = [];
            }

            // Optional: get latest GPS log per unit (argMax = nilai dari baris dengan created_at max)
            $unitIds = array_filter(array_column($units, 'id'));
            $latestGpsByUnit = [];
            if (!empty($unitIds)) {
                $idsList = implode(',', array_map(function ($id) {
                    return "'" . addslashes((string) $id) . "'";
                }, $unitIds));
                $sqlLatestGps = "
                    SELECT 
                        unit_id,
                        argMax(latitude, created_at) AS latitude,
                        argMax(longitude, created_at) AS longitude,
                        argMax(speed, created_at) AS speed,
                        argMax(battery, created_at) AS battery,
                        max(created_at) AS created_at
                    FROM nitip.unit_gps_logs
                    WHERE unit_id IN ($idsList)
                    GROUP BY unit_id
                ";
                try {
                    $latestRows = $ch->query($sqlLatestGps);
                    if (is_array($latestRows)) {
                        foreach ($latestRows as $row) {
                            $uid = $row['unit_id'] ?? null;
                            if ($uid !== null) {
                                $latestGpsByUnit[$uid] = $row;
                            }
                        }
                    }
                } catch (Exception $e) {
                    Log::warning('ClickHouse nitip unit_gps_logs query failed: ' . $e->getMessage());
                }
            }

            $list = [];
            foreach ($units as $u) {
                $id = $u['id'] ?? null;
                $gps = $latestGpsByUnit[$id] ?? null;
                $lat = $gps['latitude'] ?? $u['last_latitude'] ?? null;
                $lon = $gps['longitude'] ?? $u['last_longitude'] ?? null;
                $list[] = [
                    'id' => $id,
                    'vehicle_name' => $u['vehicle_name'] ?? null,
                    'vehicle_number' => $u['vehicle_number'] ?? null,
                    'integration_id' => $u['integration_id'] ?? null,
                    'last_latitude' => $lat,
                    'last_longitude' => $lon,
                    'last_battery' => $gps['battery'] ?? $u['last_battery'] ?? null,
                    'last_course' => $u['last_course'] ?? null,
                    'speed' => $gps['speed'] ?? null,
                    'vendor_name' => $u['vendor_name'] ?? null,
                    'vendor_type' => $u['vendor_type'] ?? null,
                    'vehicle_type' => $u['vehicle_type'] ?? null,
                    'created_at' => $u['created_at'] ?? null,
                    'updated_at' => $u['updated_at'] ?? null,
                    'gps_at' => $gps['created_at'] ?? null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $list,
                'count' => count($list),
            ]);
        } catch (Exception $e) {
            Log::error('getNitipUnits: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get GPS log history for one unit from nitip.unit_gps_logs (Evaluasi Fuelling Unit).
     * Optional date=YYYY-MM-DD: filter by that day (default today). Berdasarkan updated_at untuk tracing path.
     */
    public function getNitipUnitGpsLogs(Request $request)
    {
        $unitId = $request->query('unit_id');
        if (empty($unitId)) {
            return response()->json(['success' => false, 'message' => 'unit_id required', 'data' => []], 400);
        }
        $dateStr = $request->query('date');
        if (empty($dateStr)) {
            $dateStr = now()->format('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return response()->json(['success' => false, 'message' => 'date must be YYYY-MM-DD', 'data' => []], 400);
        }
        try {
            $ch = $this->getClickHouseNitip();
            if (!$ch->isConnected()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ClickHouse Nitip tidak terhubung',
                    'data' => [],
                ], 503);
            }
            $limit = (int) $request->query('limit', 500);
            $limit = min(max($limit, 1), 1000);
            $safeId = "'" . addslashes((string) $unitId) . "'";
            $safeDate = "'" . addslashes($dateStr) . "'";
            $sql = "
                SELECT 
                    id,
                    unit_id,
                    latitude,
                    longitude,
                    speed,
                    battery,
                    course,
                    heading,
                    created_at,
                    updated_at,
                    vehicle_number,
                    vehicle_name,
                    vendor_name
                FROM nitip.unit_gps_logs
                WHERE unit_id = $safeId
                  AND toDate(updated_at) = $safeDate
                ORDER BY updated_at ASC
                LIMIT $limit
            ";
            $rows = $ch->query($sql);
            if (!is_array($rows)) {
                $rows = [];
            }
            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
            ]);
        } catch (Exception $e) {
            Log::error('getNitipUnitGpsLogs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Export Evaluasi Unit ke Excel: NO UNIT | JARAK YANG DITEMPUH | WAKTU AKTIF (total jam) | TANGGAL HARI AKTIF.
     * Parameter: date_from, date_to (YYYY-MM-DD). Default 30 hari terakhir jika tidak ada.
     * Data diambil via EvaluasiUnitDataService (satu query agregat ClickHouse).
     */
    public function exportEvaluasiUnitExcel(Request $request)
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = null;
        }
        if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = null;
        }
        if (!$dateFrom || !$dateTo) {
            $dateTo = $dateTo ? now()->format('Y-m-d') : now()->format('Y-m-d');
            $dateFrom = $dateFrom ?: now()->subDays(30)->format('Y-m-d');
        }

        try {
            $service = new EvaluasiUnitDataService();
            $excelRows = $service->getAggregatedData($dateFrom, $dateTo);

            $headers = ['NO UNIT', 'JARAK YANG DITEMPUH', 'WAKTU AKTIF (total jam)', 'TANGGAL HARI AKTIF / ADA LOG'];
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Evaluasi Unit');

            $col = 1;
            foreach ($headers as $h) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . '1', $h);
                $col++;
            }
            $lastCol = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
            $sheet->getStyle('A1:' . $lastCol . '1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9EAD3');

            $rowNum = 2;
            foreach ($excelRows as $row) {
                $sheet->setCellValue('A' . $rowNum, $row['no_unit']);
                $sheet->setCellValue('B' . $rowNum, $row['jarak']);
                $sheet->setCellValue('C' . $rowNum, $row['waktu_jam']);
                $sheet->setCellValue('D' . $rowNum, $row['tanggal_aktif']);
                $rowNum++;
            }

            foreach (range('A', 'D') as $colLetter) {
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }

            $filename = 'Evaluasi_Unit_' . date('Y-m-d_His') . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (Exception $e) {
            Log::error('exportEvaluasiUnitExcel: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get photos from hse_ai_validations for gallery
     */
    public function getPhotoGallery(Request $request)
    {
        try {
            $limit = $request->get('limit', 20); // Default 20 photos
            
            // Get photos from hse_ai_validations where uri_foto is not empty
            $photos = HseAiValidation::whereNotNull('uri_foto')
                ->where('uri_foto', '!=', '')
                ->orderBy('tanggal_pelaporan', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($validation) {
                    // Extract foto temuan from photoCar URL if needed (same as hse-ai-validation)
                    $fotoUrl = $this->extractFotoTemuan($validation->uri_foto ?? '');
                    
                    // Skip if no valid photo URL
                    if (empty($fotoUrl)) {
                        return null;
                    }
                    
                    return [
                        'id' => $validation->id,
                        'photo_url' => $fotoUrl,
                        'keterangan' => $validation->keterangan ?? $validation->aktivitas_pekerjaan ?? 'No description',
                        'lokasi' => $validation->lokasi ?? $validation->nama_lokasi ?? 'Unknown location',
                        'tanggal' => $validation->tanggal_pelaporan ? $validation->tanggal_pelaporan->format('Y-m-d') : null,
                    ];
                })
                ->filter(function($photo) {
                    // Filter out null values and invalid URLs
                    return $photo !== null && 
                           !empty($photo['photo_url']) && 
                           (strpos($photo['photo_url'], 'http://') === 0 || strpos($photo['photo_url'], 'https://') === 0);
                });
            
            return response()->json([
                'success' => true,
                'photos' => $photos->values(), // Re-index array
                'count' => $photos->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching photo gallery: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching photos',
                'photos' => [],
                'count' => 0
            ], 500);
        }
    }

    /**
     * Extract Foto Temuan URL from photoCar page (same method as HseAiValidationDisplayController)
     */
    private function extractFotoTemuan($uriFoto)
    {
        if (empty($uriFoto)) {
            return '';
        }

        // If URL is already a direct image URL (beats2/file), return it
        if (strpos($uriFoto, 'beats2/file') !== false) {
            // Make sure it's absolute URL
            if (strpos($uriFoto, 'http') !== 0) {
                if (strpos($uriFoto, '/') === 0) {
                    return 'https://hseautomation.beraucoal.co.id' . $uriFoto;
                } else {
                    return 'https://hseautomation.beraucoal.co.id/' . ltrim($uriFoto, '/');
                }
            }
            return $uriFoto;
        }

        // If URL is photoCar page, extract foto temuan
        if (strpos($uriFoto, 'hseautomation.beraucoal.co.id/report/photoCar') !== false) {
            try {
                $response = Http::timeout(10)->get($uriFoto);
                
                if (!$response->successful()) {
                    Log::warning('Failed to fetch photoCar page', ['url' => $uriFoto]);
                    return $uriFoto; // Return original URL as fallback
                }

                $html = $response->body();

                // Check if page has "No Photo"
                if (stripos($html, 'No Photo') !== false && stripos($html, 'Foto Temuan') === false) {
                    return ''; // No photo available
                }

                // Extract Foto Temuan URL
                $fotoTemuanUrl = null;

                // Pattern untuk mencari URL foto di section Foto Temuan (prioritas tertinggi)
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
                        break;
                    }
                }

                // Fallback: cari semua link dengan beats2/file, ambil yang pertama
                if (!$fotoTemuanUrl) {
                    if (preg_match_all('/<a[^>]+href=["\']([^"\']*beats2\/file[^"\']*)["\']/i', $html, $allMatches)) {
                        if (isset($allMatches[1][0])) {
                            $fotoTemuanUrl = $allMatches[1][0];
                        }
                    }
                }

                // Fallback: cari semua img dengan beats2/file, ambil yang pertama
                if (!$fotoTemuanUrl) {
                    if (preg_match_all('/<img[^>]+(?:src|data-src)=["\']([^"\']*beats2\/file[^"\']*)["\']/i', $html, $imgMatches)) {
                        if (isset($imgMatches[1][0])) {
                            $fotoTemuanUrl = $imgMatches[1][0];
                        }
                    }
                }

                if ($fotoTemuanUrl) {
                    // Make sure it's absolute URL
                    if (strpos($fotoTemuanUrl, 'http') !== 0) {
                        if (strpos($fotoTemuanUrl, '/') === 0) {
                            $fotoTemuanUrl = 'https://hseautomation.beraucoal.co.id' . $fotoTemuanUrl;
                        } else {
                            $fotoTemuanUrl = 'https://hseautomation.beraucoal.co.id/' . ltrim($fotoTemuanUrl, '/');
                        }
                    }
                    return $fotoTemuanUrl;
                }

                // If no foto temuan found, return empty
                return '';

            } catch (Exception $e) {
                Log::error('Error extracting foto temuan', [
                    'url' => $uriFoto,
                    'error' => $e->getMessage()
                ]);
                return $uriFoto; // Return original URL as fallback
            }
        }

        // For other URLs, return as is
        return $uriFoto;
    }

}


