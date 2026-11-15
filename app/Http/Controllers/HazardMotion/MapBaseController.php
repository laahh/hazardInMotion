<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Models\CctvData;
use App\Models\CctvCoverage;
use App\Models\CctvControlRoomPengawas;
use App\Models\CctvP2hChecklist;
use App\Models\GeojsonArea;
use App\Models\InsidenTabel;
use App\Models\GrTable;
use App\Models\HazardValidation;
use App\Models\PjaCctvDedicated;
use App\Models\WmsLink;
use App\Models\IntervensiKesiapanOrang;
use App\Services\BesigmaDbService;
use App\Services\ClickHouseService;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class MapBaseController extends Controller
{
    /**
     * Normalize role/permission slug for safe comparison.
     */
    private function normalizeSlug($value): string
    {
        return strtolower(str_replace('_', '-', trim((string) $value)));
    }

    /**
     * Robust role check: hasRole(), pivot roles.slug/name, and users.role fallback.
     */
    private function userHasAnyRoleAlias($user, array $aliases): bool
    {
        if (!$user) {
            return false;
        }

        $normalizedAliases = array_values(array_unique(array_map(function ($alias) {
            return $this->normalizeSlug($alias);
        }, $aliases)));

        // 1) Existing helper check
        foreach ($aliases as $alias) {
            if ($user->hasRole($alias)) {
                return true;
            }
        }

        // 2) Direct DB check via pivot relation (roles.slug / roles.name)
        $slugVariants = [];
        foreach ($normalizedAliases as $alias) {
            $slugVariants[] = $alias;
            $slugVariants[] = str_replace('-', '_', $alias);
        }
        $slugVariants = array_values(array_unique($slugVariants));

        if ($user->roles()->whereIn('slug', $slugVariants)->exists()) {
            return true;
        }
        if ($user->roles()->whereIn('name', $slugVariants)->exists()) {
            return true;
        }

        // 3) Legacy users.role column
        $userRoleValue = $this->normalizeSlug($user->role ?? '');
        return $userRoleValue !== '' && in_array($userRoleValue, $normalizedAliases, true);
    }

    /**
     * Get allowed company and site based on user role
     * Returns array with 'company' and 'sites' keys
     * - 'company': company name if restricted, null if all companies allowed
     * - 'sites': array of allowed site names, empty array if all sites allowed
     */
    private function getAllowedCompanyAndSiteByRole()
    {
        $user = Auth::user();
        if (!$user) {
            return ['company' => null, 'sites' => []];
        }

        // Role-based access mapping
        // Format: 'role_slug' => ['company' => 'Company Name', 'sites' => ['Site 1', 'Site 2']]
        // If 'sites' is empty array, user can access all sites within the company
        // If 'company' is null, user can access all companies
        $roleAccessMap = [
            'control_room_pama' => [
                'company' => 'PT Pamapersada Nusantara',
                'sites' => ['BMO 2'] // User dengan role ini hanya bisa akses site BMO 2
            ],
            'control-room-pama' => [
                'company' => 'PT Pamapersada Nusantara',
                'sites' => ['BMO 2'] // User dengan role ini hanya bisa akses site BMO 2
            ],
            'control_room_mtn' => [
                'company' => 'PT Madhani Talatah Nusantara',
                'sites' => ['SMO'] // User dengan role ini hanya bisa akses site SMO
            ],
            'control-room-mtn' => [
                'company' => 'PT Madhani Talatah Nusantara',
                'sites' => ['SMO']
            ],
            // Alias role lama/alternatif yang sudah dipakai di beberapa view
            'hazard-motion-it-pama' => [
                'company' => 'PT Pamapersada Nusantara',
                'sites' => ['BMO 2']
            ],
            'hazard_motion_it_pama' => [
                'company' => 'PT Pamapersada Nusantara',
                'sites' => ['BMO 2']
            ],
            'hazard-motion-it-mtl' => [
                'company' => 'PT Madhani Talatah Nusantara',
                'sites' => ['SMO']
            ],
            'hazard_motion_it_mtl' => [
                'company' => 'PT Madhani Talatah Nusantara',
                'sites' => ['SMO']
            ],
            // Add more role mappings here as needed
            // Example:
            // 'control_room_site_a' => [
            //     'company' => 'PT Pamapersada Nusantara',
            //     'sites' => ['BMO 1', 'BMO 2']
            // ],
            // 'control_room_company_b' => [
            //     'company' => 'PT Company B',
            //     'sites' => ['Site C']
            // ],
        ];

        // Check user's roles and return first matching restriction
        foreach ($roleAccessMap as $roleSlug => $access) {
            if ($this->userHasAnyRoleAlias($user, [$roleSlug])) {
                return [
                    'company' => $access['company'],
                    'sites' => $access['sites']
                ];
            }
        }

        // Fallback: some users are restricted via permissions (not roles)
        $permissionAccessMap = [
            'hazard-motion-it-pama' => [
                'company' => 'PT Pamapersada Nusantara',
                'sites' => ['BMO 2']
            ],
            'hazard-motion-it-mtl' => [
                'company' => 'PT Madhani Talatah Nusantara',
                'sites' => ['SMO']
            ],
        ];

        foreach ($permissionAccessMap as $permissionSlug => $access) {
            if ($user->hasPermission($permissionSlug)) {
                return [
                    'company' => $access['company'],
                    'sites' => $access['sites']
                ];
            }
        }

        // Admin or other roles can see all companies and sites
        return ['company' => null, 'sites' => []];
    }

    /**
     * Get allowed company based on user role
     * Returns company name if user has specific role, null if all companies allowed
     * @deprecated Use getAllowedCompanyAndSiteByRole() instead for better flexibility
     */
    private function getAllowedCompanyByRole()
    {
        $access = $this->getAllowedCompanyAndSiteByRole();
        return $access['company'];
    }

    /**
     * Get allowed sites based on user role
     * Returns array of allowed site names, empty array if all sites allowed
     */
    private function getAllowedSitesByRole()
    {
        $access = $this->getAllowedCompanyAndSiteByRole();
        return $access['sites'];
    }

    /**
     * Display the hazard detection page
     */
    public function index()
    {
        // Get logged-in user
        $user = Auth::user();
        $userName = $user ? $user->name : null;
        
        // Get allowed company and sites based on role
        $roleAccess = $this->getAllowedCompanyAndSiteByRole();
        $allowedCompany = $roleAccess['company'];
        $allowedSites = $roleAccess['sites'];
        
        // Get control rooms that the logged-in user supervises
        $supervisedControlRooms = [];
        if ($userName) {
            $pengawasRecords = CctvControlRoomPengawas::where('nama_pengawas', $userName)->get();
            $supervisedControlRooms = $pengawasRecords->pluck('control_room')->filter()->unique()->toArray();
        }
        
        // Ambil SEMUA data CCTV dari tabel cctv_data_bmo2 (termasuk yang tidak punya koordinat)
        // Model CctvData sudah dikonfigurasi untuk menggunakan tabel cctv_data_bmo2
        $cctvDataAllQuery = CctvData::query();
        
        // Filter by company if user has specific role
        if ($allowedCompany) {
            $cctvDataAllQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        
        // Filter by sites if user has specific role with site restrictions
        if (!empty($allowedSites)) {
            $cctvDataAllQuery->whereIn('site', $allowedSites);
        }
        
        // Filter CCTV data based on supervised control rooms if user is not admin
        // If user is admin or has no supervised control rooms, show all CCTV
        if ($userName && !empty($supervisedControlRooms)) {
            $cctvDataAllQuery->whereIn('control_room', $supervisedControlRooms);
        }
        
        $cctvDataAll = $cctvDataAllQuery->get();
        
        // Ambil data CCTV yang memiliki koordinat untuk ditampilkan di map
        $cctvDataWithLocationQuery = CctvData::whereNotNull('longitude')
            ->whereNotNull('latitude');
        
        // Filter by company if user has specific role
        if ($allowedCompany) {
            $cctvDataWithLocationQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        
        // Filter by sites if user has specific role with site restrictions
        if (!empty($allowedSites)) {
            $cctvDataWithLocationQuery->whereIn('site', $allowedSites);
        }
        
        // Apply same filter for map data
        if ($userName && !empty($supervisedControlRooms)) {
            $cctvDataWithLocationQuery->whereIn('control_room', $supervisedControlRooms);
        }
        
        $cctvDataWithLocation = $cctvDataWithLocationQuery->get();

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
        $totalCctvCountQuery = CctvData::query();
        if ($allowedCompany) {
            $totalCctvCountQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $totalCctvCountQuery->whereIn('site', $allowedSites);
        }
        $totalCctvCount = $totalCctvCountQuery->count();
        
        // Hitung jumlah control room yang unik dari tabel cctv_data_bmo2
        $totalControlRoomCountQuery = CctvData::whereNotNull('control_room')
            ->where('control_room', '!=', '');
        if ($allowedCompany) {
            $totalControlRoomCountQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $totalControlRoomCountQuery->whereIn('site', $allowedSites);
        }
        $totalControlRoomCount = $totalControlRoomCountQuery->distinct('control_room')
            ->count('control_room');
        
        // Ambil semua control room yang unik untuk ditampilkan di overview
        $allControlRoomsQuery = CctvData::whereNotNull('control_room')
            ->where('control_room', '!=', '');
        if ($allowedCompany) {
            $allControlRoomsQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $allControlRoomsQuery->whereIn('site', $allowedSites);
        }
        $allControlRooms = $allControlRoomsQuery->distinct('control_room')
            ->orderBy('control_room')
            ->pluck('control_room')
            ->toArray();

        $criticalCoverageBaseQuery = CctvData::query()->where(function ($query) {
            $query->where('kategori_area_tercapture', 'like', '%kritis%')
                  ->orWhere('kategori_area_tercapture', 'like', '%critical%')
                  ->orWhere('coverage_lokasi', 'like', '%kritis%')
                  ->orWhere('coverage_lokasi', 'like', '%critical%');
        });
        if ($allowedCompany) {
            $criticalCoverageBaseQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $criticalCoverageBaseQuery->whereIn('site', $allowedSites);
        }

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
        $insidenRecordsQuery = InsidenTabel::orderByDesc('created_at');
        if ($allowedCompany) {
            $insidenRecordsQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $insidenRecordsQuery->whereIn('site', $allowedSites);
        }
        $insidenRecords = $insidenRecordsQuery->get();

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
        // Menggunakan logika yang sama dengan getPjaCctvStatistics() di CctvDataController
        // Ambil semua mapped CCTV dedicated values
        $mappedCctv = PjaCctvDedicated::distinct('cctv_dedicated')
            ->pluck('cctv_dedicated')
            ->filter()
            ->map(function($item) {
                return trim($item);
            })
            ->toArray();
        
        // Query dasar untuk CCTV berdasarkan control room yang diawasi
        $baseQuery = CctvData::query();
        if ($userName && !empty($supervisedControlRooms)) {
            $baseQuery->whereIn('control_room', $supervisedControlRooms);
        }
        
        // Ambil semua CCTV data untuk di-filter di PHP (lebih fleksibel untuk matching)
        $allCctvData = $baseQuery->get();
        
        // Hitung total CCTV untuk persentase
        $totalCctvForPja = $allCctvData->count();
        
        // Count CCTV that have PJA mapping (menggunakan exact match dan partial match)
        $mappedCctvCount = $allCctvData->filter(function($cctv) use ($mappedCctv) {
            $noCctv = trim($cctv->no_cctv ?? '');
            $namaCctv = trim($cctv->nama_cctv ?? '');
            
            if (empty($noCctv) && empty($namaCctv)) {
                return false;
            }
            
            foreach ($mappedCctv as $mapped) {
                $mappedTrimmed = trim($mapped);
                if (empty($mappedTrimmed)) continue;
                
                // Exact match
                if ($noCctv === $mappedTrimmed || $namaCctv === $mappedTrimmed) {
                    return true;
                }
                
                // Partial match (contains)
                if (!empty($noCctv) && (
                    str_contains($noCctv, $mappedTrimmed) || 
                    str_contains($mappedTrimmed, $noCctv)
                )) {
                    return true;
                }
                if (!empty($namaCctv) && (
                    str_contains($namaCctv, $mappedTrimmed) || 
                    str_contains($mappedTrimmed, $namaCctv)
                )) {
                    return true;
                }
            }
            return false;
        })->count();
        
        // Count CCTV that don't have PJA mapping
        $cctvBelumPjaCount = $allCctvData->filter(function($cctv) use ($mappedCctv) {
            $noCctv = trim($cctv->no_cctv ?? '');
            $namaCctv = trim($cctv->nama_cctv ?? '');
            
            if (empty($noCctv) && empty($namaCctv)) {
                return false;
            }
            
            foreach ($mappedCctv as $mapped) {
                $mappedTrimmed = trim($mapped);
                if (empty($mappedTrimmed)) continue;
                
                if ($noCctv === $mappedTrimmed || $namaCctv === $mappedTrimmed) {
                    return false;
                }
                if (!empty($noCctv) && (
                    str_contains($noCctv, $mappedTrimmed) || 
                    str_contains($mappedTrimmed, $noCctv)
                )) {
                    return false;
                }
                if (!empty($namaCctv) && (
                    str_contains($namaCctv, $mappedTrimmed) || 
                    str_contains($mappedTrimmed, $namaCctv)
                )) {
                    return false;
                }
            }
            return true;
        })->count();
        
        // Hitung persentase CCTV yang sudah ada PJA
        $cctvSudahPjaPercentage = $totalCctvForPja > 0 
            ? round(($mappedCctvCount / $totalCctvForPja) * 100, 1) 
            : 0;
        
        // Hitung persentase CCTV yang belum ada PJA
        $cctvBelumPjaPercentage = $totalCctvForPja > 0 
            ? round(($cctvBelumPjaCount / $totalCctvForPja) * 100, 1) 
            : 0;
        
        // Hitung jumlah CCTV yang sudah ada PJA
        $cctvSudahPjaCount = $mappedCctvCount;
        
        // Debug: Ambil sample CCTV yang sudah termapping dan belum termapping
        $mappedSample = $allCctvData->filter(function($cctv) use ($mappedCctv) {
            $noCctv = trim($cctv->no_cctv ?? '');
            $namaCctv = trim($cctv->nama_cctv ?? '');
            
            if (empty($noCctv) && empty($namaCctv)) {
                return false;
            }
            
            foreach ($mappedCctv as $mapped) {
                $mappedTrimmed = trim($mapped);
                if (empty($mappedTrimmed)) continue;
                
                if ($noCctv === $mappedTrimmed || $namaCctv === $mappedTrimmed) {
                    return true;
                }
                if (!empty($noCctv) && (
                    str_contains($noCctv, $mappedTrimmed) || 
                    str_contains($mappedTrimmed, $noCctv)
                )) {
                    return true;
                }
                if (!empty($namaCctv) && (
                    str_contains($namaCctv, $mappedTrimmed) || 
                    str_contains($mappedTrimmed, $namaCctv)
                )) {
                    return true;
                }
            }
            return false;
        })->take(5)->pluck('no_cctv')->toArray();
        
        $unmappedSample = $allCctvData->filter(function($cctv) use ($mappedCctv) {
            $noCctv = trim($cctv->no_cctv ?? '');
            $namaCctv = trim($cctv->nama_cctv ?? '');
            
            if (empty($noCctv) && empty($namaCctv)) {
                return false;
            }
            
            foreach ($mappedCctv as $mapped) {
                $mappedTrimmed = trim($mapped);
                if (empty($mappedTrimmed)) continue;
                
                if ($noCctv === $mappedTrimmed || $namaCctv === $mappedTrimmed) {
                    return false;
                }
                if (!empty($noCctv) && (
                    str_contains($noCctv, $mappedTrimmed) || 
                    str_contains($mappedTrimmed, $noCctv)
                )) {
                    return false;
                }
                if (!empty($namaCctv) && (
                    str_contains($namaCctv, $mappedTrimmed) || 
                    str_contains($mappedTrimmed, $namaCctv)
                )) {
                    return false;
                }
            }
            return true;
        })->take(5)->pluck('no_cctv')->toArray();

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

        return view('HazardMotion.admin.mapBase', compact(
            'cctvLocations',
            'cctvLocationsForMap',
            'sapData',
            'grDetections',
            'stats',
            'allowedCompany',
            'allowedSites',
            'insidenGroups',
            'allControlRooms',
            'criticalAreaCount',
            'criticalCoveragePercentage',
            'criticalCoverageCctv',
            'validGrCount',
            'totalCctvCount',
            'totalControlRoomCount',
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
     * Get PJA data from ClickHouse - Grouped by nama_pja
     */
    public function getPjaData(Request $request)
    {
        try {
            // Query untuk mengambil data PJA dari MySQL tabel wan_vw_pja_karyawan
            // Group by nama_pja dan include semua karyawan
            try {
                $results = DB::table('wan_vw_pja_karyawan')
                    ->select(
                        'kode_sid',
                        'nama_pja',
                        'tipe_pja',
                        'peruashaan',
                        'nama_karyawan',
                        'status_pja_karyawan',
                        'status_nama_pja'
                    )
                    ->where('status_pja_karyawan', '1')
                    ->where('status_nama_pja', '1')
                    ->orderBy('nama_pja')
                    ->orderBy('nama_karyawan')
                    ->limit(10000)
                    ->get()
                    ->map(function($row) {
                        // Convert to array and ensure all values are strings (for compatibility)
                        return [
                            'kode_sid' => (string)($row->kode_sid ?? ''),
                            'nama_pja' => (string)($row->nama_pja ?? ''),
                            'tipe_pja' => (string)($row->tipe_pja ?? ''),
                            'peruashaan' => (string)($row->peruashaan ?? ''),
                            'nama_karyawan' => (string)($row->nama_karyawan ?? ''),
                            'status_pja_karyawan' => (string)($row->status_pja_karyawan ?? ''),
                            'status_nama_pja' => (string)($row->status_nama_pja ?? ''),
                        ];
                    })
                    ->toArray();
            } catch (Exception $e) {
                Log::error('Error querying wan_vw_pja_karyawan from MySQL: ' . $e->getMessage());
                $results = [];
            }
            
            // Query checkinout_rfid untuk menentukan status onsite dan Pass/Not Pass
            // Menggunakan ClickHouse baru (IP 10.10.10.38, database hse_automation)
            $onsiteStatusMap = [];
            $passStatusMap = [];
            try {
                $now = Carbon::now();
                $today = $now->format('Y-m-d');
                $yesterday = $now->copy()->subDay()->format('Y-m-d');
                
                // Query untuk onsite status (hanya yang PASSED)
                // Menggunakan ClickHouse baru dengan database hse_automation
                $sqlCheckin = "
                    SELECT 
                        toString(nama_karyawan) as nama_karyawan,
                        date,
                        status_checkin_out,
                        toString(status_passed) as status_passed
                    FROM hse_automation.aaj_vw_checkinout_rfid
                    WHERE status_passed = 'PASSED'
                      AND (
                          (toDate(date) = '{$today}' AND toHour(date) >= 6 AND toHour(date) < 18)
                          OR
                          (toDate(date) = '{$yesterday}' AND toHour(date) >= 18)
                          OR
                          (toDate(date) = '{$today}' AND toHour(date) < 6)
                      )
                    ORDER BY nama_karyawan, date DESC
                ";
                
                // Use custom ClickHouse connection (IP 10.10.10.38, database hse_automation)
                $checkinResults = $this->queryClickHouseCustom($sqlCheckin, 'hse_automation');
                
                foreach ($checkinResults as $checkin) {
                    $namaKaryawan = trim($checkin['nama_karyawan'] ?? '');
                    if (empty($namaKaryawan)) {
                        continue;
                    }
                    
                    $namaKaryawan = preg_replace('/\s+/', ' ', $namaKaryawan);
                    
                    if (isset($onsiteStatusMap[$namaKaryawan])) {
                        continue;
                    }
                    
                    $checkinDate = $checkin['date'] ?? null;
                    if (!$checkinDate) {
                        continue;
                    }
                    
                    $statusPassed = $checkin['status_passed'] ?? '';
                    if (strtoupper($statusPassed) === 'PASSED') {
                        $passStatusMap[$namaKaryawan] = 1;
                    }
                    
                    try {
                        $checkinDateTime = Carbon::parse($checkinDate);
                        $checkinHour = (int)$checkinDateTime->format('H');
                        $checkinDateOnly = $checkinDateTime->format('Y-m-d');
                        
                        if ($checkinDateOnly === $today) {
                            if ($checkinHour >= 6 && $checkinHour < 18) {
                                $onsiteStatusMap[$namaKaryawan] = 'SHIFT_1';
                            } elseif ($checkinHour < 6) {
                                $onsiteStatusMap[$namaKaryawan] = 'SHIFT_2';
                            } elseif ($checkinHour >= 18) {
                                $onsiteStatusMap[$namaKaryawan] = 'SHIFT_2';
                            }
                        } elseif ($checkinDateOnly === $yesterday) {
                            if ($checkinHour >= 18) {
                                $onsiteStatusMap[$namaKaryawan] = 'SHIFT_2';
                            }
                        }
                    } catch (Exception $e) {
                        Log::warning('Error parsing checkin date: ' . $checkinDate . ' - ' . $e->getMessage());
                        continue;
                    }
                }
                
                // Query untuk Pass/Not Pass status dari semua check-in terbaru (7 hari terakhir)
                // Menggunakan ClickHouse baru (IP 10.10.10.38, database hse_automation)
                try {
                    $sqlPassStatus = "
                        SELECT 
                            toString(nama_karyawan) as nama_karyawan,
                            toString(status_passed) as status_passed,
                            date
                        FROM hse_automation.aaj_vw_checkinout_rfid
                        WHERE toDate(date) >= toDate(now()) - INTERVAL 7 DAY
                        ORDER BY nama_karyawan, date DESC
                    ";
                    
                    // Use custom ClickHouse connection (IP 10.10.10.38, database hse_automation)
                    $passStatusResults = $this->queryClickHouseCustom($sqlPassStatus, 'hse_automation');
                    
                    $processedNames = [];
                    foreach ($passStatusResults as $passStatus) {
                        $namaKaryawan = trim($passStatus['nama_karyawan'] ?? '');
                        if (empty($namaKaryawan)) {
                            continue;
                        }
                        
                        $namaKaryawan = preg_replace('/\s+/', ' ', $namaKaryawan);
                        
                        if (isset($processedNames[$namaKaryawan])) {
                            continue;
                        }
                        
                        $processedNames[$namaKaryawan] = true;
                        
                        $statusPassed = $passStatus['status_passed'] ?? '';
                        if (strtoupper($statusPassed) === 'PASSED') {
                            $passStatusMap[$namaKaryawan] = 1;
                        } else {
                            $passStatusMap[$namaKaryawan] = 0;
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Error querying pass status from checkinout_rfid: ' . $e->getMessage());
                }
            } catch (Exception $e) {
                Log::error('Error querying checkinout_rfid: ' . $e->getMessage());
            }
            
            // Group data by nama_pja
            $groupedPja = [];
            foreach ($results as $row) {
                $namaPja = $row['nama_pja'] ?? 'Unknown PJA';
                $namaKaryawan = trim($row['nama_karyawan'] ?? '');
                $namaKaryawanNormalized = preg_replace('/\s+/', ' ', $namaKaryawan);
                
                if (!isset($groupedPja[$namaPja])) {
                    $groupedPja[$namaPja] = [
                        'nama_pja' => $namaPja,
                        'employees' => []
                    ];
                }
                
                // Determine onsite status
                $statusOnsite = null;
                if (!empty($namaKaryawanNormalized) && isset($onsiteStatusMap[$namaKaryawanNormalized])) {
                    $statusOnsite = $onsiteStatusMap[$namaKaryawanNormalized];
                } else {
                    if (!empty($namaKaryawan) && isset($onsiteStatusMap[$namaKaryawan])) {
                        $statusOnsite = $onsiteStatusMap[$namaKaryawan];
                    } else {
                        foreach ($onsiteStatusMap as $key => $value) {
                            if (strcasecmp($namaKaryawanNormalized, $key) === 0) {
                                $statusOnsite = $value;
                                break;
                            }
                        }
                    }
                }
                
                // Determine Pass/Not Pass status
                $statusPjaKaryawan = null;
                if (!empty($namaKaryawanNormalized) && isset($passStatusMap[$namaKaryawanNormalized])) {
                    $statusPjaKaryawan = $passStatusMap[$namaKaryawanNormalized];
                } else {
                    if (!empty($namaKaryawan) && isset($passStatusMap[$namaKaryawan])) {
                        $statusPjaKaryawan = $passStatusMap[$namaKaryawan];
                    } else {
                        foreach ($passStatusMap as $key => $value) {
                            if (strcasecmp($namaKaryawanNormalized, $key) === 0) {
                                $statusPjaKaryawan = $value;
                                break;
                            }
                        }
                    }
                }
                
                // Add employee to the group
                $groupedPja[$namaPja]['employees'][] = [
                    'kode_sid' => $row['kode_sid'] ?? null,
                    'nama_karyawan' => $row['nama_karyawan'] ?? null,
                    'tipe_pja' => $row['tipe_pja'] ?? null,
                    'peruashaan' => $row['peruashaan'] ?? null,
                    'status_pja_karyawan' => $row['status_pja_karyawan'] ?? null,
                    'status_onsite' => $statusOnsite,
                    'status_pass' => $statusPjaKaryawan, // Pass/Not Pass dari checkinout_rfid
                ];
            }
            
            // Convert to array format for frontend
            $pjaData = array_values($groupedPja);
            
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
     * Query ClickHouse dengan konfigurasi khusus (IP 10.10.10.38, database hse_automation)
     * Untuk data Total Onsite hari ini
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
            $timeout = 30;

            $url = $baseUrl . '/?database=' . urlencode($database) . '&default_format=JSON';
            
            $httpClient = Http::timeout($timeout)
                ->withBasicAuth($username, $password)
                ->withBody($sql, 'text/plain');
            
            $response = $httpClient->post($url);

            if (!$response->successful()) {
                Log::error('ClickHouse custom query failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $result = $response->json();
            
            // Parse ClickHouse JSON response
            if (isset($result['data'])) {
                return $result['data'];
            } elseif (isset($result[0])) {
                return $result;
            } else {
                // Try to parse as JSON lines format
                $lines = explode("\n", trim($response->body()));
                $data = [];
                foreach ($lines as $line) {
                    if (!empty(trim($line))) {
                        $decoded = json_decode($line, true);
                        if ($decoded !== null) {
                            $data[] = $decoded;
                        }
                    }
                }
                return $data;
            }
        } catch (Exception $e) {
            Log::error('Error in queryClickHouseCustom: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get kesiapan orang data (PJA Karyawan and CCTV Dedicated)
     */
    public function getKesiapanOrangData(Request $request)
    {
        try {
            // Query untuk mengambil data PJA Karyawan dari MySQL tabel wan_vw_pja_karyawan
            try {
                $resultsKaryawan = DB::table('wan_vw_pja_karyawan')
                    ->select(
                        'kode_sid',
                        'nama_pja',
                        'tipe_pja',
                        'peruashaan as perusahaan',
                        'id_employee',
                        'id_nama_pja',
                        'pja_kategori',
                        'id_pja_parent',
                        'nama_karyawan',
                        'id_pja_employee',
                        'status_karyawan',
                        'status_nama_pja',
                        'pja_kategory_layer',
                        'status_pja_karyawan',
                        'tanggal_update_nama_pja',
                        'tanggal_update_data_karyawan',
                        'tanggal_update_data_pja_karyawan'
                    )
                    ->orderBy('nama_pja')
                    ->orderBy('nama_karyawan')
                    ->limit(10000)
                    ->get()
                    ->map(function($row) {
                        // Convert to array and ensure all values are strings (for compatibility)
                        return [
                            'kode_sid' => (string)($row->kode_sid ?? ''),
                            'nama_pja' => (string)($row->nama_pja ?? ''),
                            'tipe_pja' => (string)($row->tipe_pja ?? ''),
                            'perusahaan' => (string)($row->perusahaan ?? ''),
                            'id_employee' => (string)($row->id_employee ?? ''),
                            'id_nama_pja' => (string)($row->id_nama_pja ?? ''),
                            'pja_kategori' => (string)($row->pja_kategori ?? ''),
                            'id_pja_parent' => (string)($row->id_pja_parent ?? ''),
                            'nama_karyawan' => (string)($row->nama_karyawan ?? ''),
                            'id_pja_employee' => (string)($row->id_pja_employee ?? ''),
                            'status_karyawan' => (string)($row->status_karyawan ?? ''),
                            'status_nama_pja' => (string)($row->status_nama_pja ?? ''),
                            'pja_kategory_layer' => (string)($row->pja_kategory_layer ?? ''),
                            'status_pja_karyawan' => (string)($row->status_pja_karyawan ?? ''),
                            'tanggal_update_nama_pja' => $row->tanggal_update_nama_pja ?? null,
                            'tanggal_update_data_karyawan' => $row->tanggal_update_data_karyawan ?? null,
                            'tanggal_update_data_pja_karyawan' => $row->tanggal_update_data_pja_karyawan ?? null,
                        ];
                    })
                    ->toArray();
            } catch (Exception $e) {
                Log::error('Error querying wan_vw_pja_karyawan from MySQL: ' . $e->getMessage());
                $resultsKaryawan = [];
            }
            
            // Get CCTV Dedicated data from Laravel model
            $cctvDedicated = PjaCctvDedicated::select('id', 'no', 'pja', 'cctv_dedicated')
                ->orderBy('pja')
                ->orderBy('cctv_dedicated')
                ->get();
            
            // Get total CCTV from cctv_data_bmo2
            $totalCctv = 0;
            $cctvWithPjaCount = 0;
            $persentaseCctvDenganPja = 0;
            
            try {
                $totalCctv = CctvData::count();
                
                if ($totalCctv > 0) {
                    // Get unique CCTV names from pja_cctv_dedicated
                    $cctvWithPja = PjaCctvDedicated::select('cctv_dedicated')
                        ->distinct()
                        ->whereNotNull('cctv_dedicated')
                        ->where('cctv_dedicated', '!=', '')
                        ->pluck('cctv_dedicated')
                        ->map(function($value) {
                            return trim((string)$value);
                        })
                        ->filter(function($value) {
                            return !empty($value);
                        })
                        ->unique()
                        ->values()
                        ->toArray();
                    
                    // Count CCTV from cctv_data_bmo2 that match with cctv_dedicated
                    if (!empty($cctvWithPja)) {
                        // Get all CCTV numbers from cctv_data_bmo2
                        $allCctvNumbers = CctvData::select('no_cctv')
                            ->whereNotNull('no_cctv')
                            ->where('no_cctv', '!=', '')
                            ->pluck('no_cctv')
                            ->map(function($value) {
                                return trim((string)$value);
                            })
                            ->filter(function($value) {
                                return !empty($value);
                            })
                            ->unique()
                            ->values()
                            ->toArray();
                        
                        // Count matches (case-insensitive comparison)
                        $matchedCctv = [];
                        foreach ($cctvWithPja as $dedicatedCctv) {
                            if (empty($dedicatedCctv)) {
                                continue;
                            }
                            
                            foreach ($allCctvNumbers as $cctvNumber) {
                                if (empty($cctvNumber)) {
                                    continue;
                                }
                                
                                // Case-insensitive comparison
                                if (strcasecmp($dedicatedCctv, $cctvNumber) === 0) {
                                    // Count each CCTV only once
                                    if (!in_array($cctvNumber, $matchedCctv, true)) {
                                        $matchedCctv[] = $cctvNumber;
                                        $cctvWithPjaCount++;
                                    }
                                    break;
                                }
                            }
                        }
                    }
                    
                    // Calculate percentage
                    $persentaseCctvDenganPja = $totalCctv > 0 
                        ? round(($cctvWithPjaCount / $totalCctv) * 100, 2) 
                        : 0;
                }
            } catch (Exception $e) {
                Log::error('Error calculating CCTV with PJA statistics: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                // Set default values on error
                $totalCctv = 0;
                $cctvWithPjaCount = 0;
                $persentaseCctvDenganPja = 0;
            }
            
            // Query checkinout_rfid untuk menentukan status onsite dan status Pass/Not Pass
            // Menggunakan ClickHouse dengan IP 10.10.10.38, database hse_automation
            $onsiteStatusMap = [];
            $passStatusMap = []; // Map untuk status Pass/Not Pass berdasarkan status_passed
            try {
                $now = Carbon::now();
                $today = $now->format('Y-m-d');
                $yesterday = $now->copy()->subDay()->format('Y-m-d');
                
                // Shift 1: 6:00-18:00 (hari ini) - jika tap antara 6-18 hari ini = onsite shift 1
                // Shift 2: 18:00-6:00 (dari kemarin 18:00 sampai hari ini 6:00) - jika tap antara 18-6 = onsite shift 2
                // Query untuk shift 1: hari ini antara 6:00-18:00
                // Query untuk shift 2: kemarin 18:00 sampai hari ini 6:00
                
                $sqlCheckin = "
                    SELECT 
                        toString(nama_karyawan) as nama_karyawan,
                        date,
                        status_checkin_out,
                        toString(status_passed) as status_passed
                    FROM hse_automation.aaj_vw_checkinout_rfid
                    WHERE status_passed = 'PASSED'
                      AND (
                          -- Shift 1: hari ini antara 6:00-18:00
                          (toDate(date) = '{$today}' AND toHour(date) >= 6 AND toHour(date) < 18)
                          OR
                          -- Shift 2: kemarin setelah 18:00 atau hari ini sebelum 6:00
                          (toDate(date) = '{$yesterday}' AND toHour(date) >= 18)
                          OR
                          (toDate(date) = '{$today}' AND toHour(date) < 6)
                      )
                    ORDER BY nama_karyawan, date DESC
                ";
                
                // Use custom ClickHouse connection (IP 10.10.10.38, database hse_automation)
                $checkinResults = $this->queryClickHouseCustom($sqlCheckin, 'hse_automation');
                
                // Group by nama_karyawan and determine shift (ambil check-in terakhir)
                foreach ($checkinResults as $checkin) {
                    $namaKaryawan = trim($checkin['nama_karyawan'] ?? '');
                    if (empty($namaKaryawan)) {
                        continue;
                    }
                    
                    // Normalize nama_karyawan (remove extra spaces)
                    $namaKaryawan = preg_replace('/\s+/', ' ', $namaKaryawan);
                    
                    // Skip if already processed (take latest check-in per karyawan)
                    if (isset($onsiteStatusMap[$namaKaryawan])) {
                        continue;
                    }
                    
                    $checkinDate = $checkin['date'] ?? null;
                    if (!$checkinDate) {
                        continue;
                    }
                    
                    // Set status Pass jika status_passed = 'PASSED'
                    $statusPassed = $checkin['status_passed'] ?? '';
                    if (strtoupper($statusPassed) === 'PASSED') {
                        $passStatusMap[$namaKaryawan] = 1; // Pass
                    }
                    
                    try {
                        $checkinDateTime = Carbon::parse($checkinDate);
                        $checkinHour = (int)$checkinDateTime->format('H');
                        $checkinDateOnly = $checkinDateTime->format('Y-m-d');
                        
                        // Determine shift berdasarkan waktu tap
                        if ($checkinDateOnly === $today) {
                            // Tap hari ini
                            if ($checkinHour >= 6 && $checkinHour < 18) {
                                // Shift 1: 6:00-18:00
                                $onsiteStatusMap[$namaKaryawan] = 'SHIFT_1';
                            } elseif ($checkinHour < 6) {
                                // Sebelum 6:00 hari ini = shift 2 (dari kemarin 18:00)
                                $onsiteStatusMap[$namaKaryawan] = 'SHIFT_2';
                            } elseif ($checkinHour >= 18) {
                                // Setelah 18:00 hari ini = shift 2 (untuk shift malam)
                                $onsiteStatusMap[$namaKaryawan] = 'SHIFT_2';
                            }
                        } elseif ($checkinDateOnly === $yesterday) {
                            // Tap kemarin setelah 18:00 = shift 2
                            if ($checkinHour >= 18) {
                                $onsiteStatusMap[$namaKaryawan] = 'SHIFT_2';
                            }
                        }
                    } catch (Exception $e) {
                        Log::warning('Error parsing checkin date: ' . $checkinDate . ' - ' . $e->getMessage());
                        continue;
                    }
                }
                
                // Query untuk mendapatkan status Pass/Not Pass dari semua check-in terbaru
                // Ambil check-in terbaru per karyawan untuk menentukan Pass/Not Pass (dalam 7 hari terakhir)
                // Menggunakan ClickHouse dengan IP 10.10.10.38, database hse_automation
                try {
                    $sqlPassStatus = "
                        SELECT 
                            toString(nama_karyawan) as nama_karyawan,
                            toString(status_passed) as status_passed,
                            date
                        FROM hse_automation.aaj_vw_checkinout_rfid
                        WHERE toDate(date) >= toDate(now()) - INTERVAL 7 DAY
                        ORDER BY nama_karyawan, date DESC
                    ";
                    
                    // Use custom ClickHouse connection (IP 10.10.10.38, database hse_automation)
                    $passStatusResults = $this->queryClickHouseCustom($sqlPassStatus, 'hse_automation');
                    
                    // Group by nama_karyawan, ambil yang terbaru (query sudah di ORDER BY date DESC)
                    $processedNames = [];
                    foreach ($passStatusResults as $passStatus) {
                        $namaKaryawan = trim($passStatus['nama_karyawan'] ?? '');
                        if (empty($namaKaryawan)) {
                            continue;
                        }
                        
                        // Normalize nama_karyawan
                        $namaKaryawan = preg_replace('/\s+/', ' ', $namaKaryawan);
                        
                        // Skip if already processed (take latest check-in per karyawan)
                        if (isset($processedNames[$namaKaryawan])) {
                            continue;
                        }
                        
                        $processedNames[$namaKaryawan] = true;
                        
                        // Set status Pass/Not Pass berdasarkan status_passed terbaru
                        // Ini akan menimpa status dari query sebelumnya jika ada check-in yang lebih baru
                        $statusPassed = $passStatus['status_passed'] ?? '';
                        if (strtoupper($statusPassed) === 'PASSED') {
                            $passStatusMap[$namaKaryawan] = 1; // Pass
                        } else {
                            $passStatusMap[$namaKaryawan] = 0; // Not Pass
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Error querying pass status from checkinout_rfid: ' . $e->getMessage());
                    // Continue without pass status if query fails
                }
            } catch (Exception $e) {
                Log::error('Error querying checkinout_rfid: ' . $e->getMessage());
                // Continue without onsite status if query fails
            }
            
            // Format data untuk frontend
            $karyawanData = [];
            foreach ($resultsKaryawan as $row) {
                $namaKaryawan = trim($row['nama_karyawan'] ?? '');
                $namaKaryawanNormalized = preg_replace('/\s+/', ' ', $namaKaryawan);
                
                // Determine onsite status
                $statusOnsite = null;
                if (!empty($namaKaryawanNormalized) && isset($onsiteStatusMap[$namaKaryawanNormalized])) {
                    $statusOnsite = $onsiteStatusMap[$namaKaryawanNormalized];
                } else {
                    // Try exact match first
                    if (!empty($namaKaryawan) && isset($onsiteStatusMap[$namaKaryawan])) {
                        $statusOnsite = $onsiteStatusMap[$namaKaryawan];
                    } else {
                        // Try case-insensitive match
                        foreach ($onsiteStatusMap as $key => $value) {
                            if (strcasecmp($namaKaryawanNormalized, $key) === 0) {
                                $statusOnsite = $value;
                                break;
                            }
                        }
                    }
                }
                
                // Determine Pass/Not Pass status dari checkinout_rfid
                $statusPjaKaryawan = null; // null = tidak ada data
                if (!empty($namaKaryawanNormalized) && isset($passStatusMap[$namaKaryawanNormalized])) {
                    $statusPjaKaryawan = $passStatusMap[$namaKaryawanNormalized];
                } else {
                    // Try exact match first
                    if (!empty($namaKaryawan) && isset($passStatusMap[$namaKaryawan])) {
                        $statusPjaKaryawan = $passStatusMap[$namaKaryawan];
                    } else {
                        // Try case-insensitive match
                        foreach ($passStatusMap as $key => $value) {
                            if (strcasecmp($namaKaryawanNormalized, $key) === 0) {
                                $statusPjaKaryawan = $value;
                                break;
                            }
                        }
                    }
                }
                
                $karyawanData[] = [
                    'kode_sid' => $row['kode_sid'] ?? null,
                    'nama_pja' => $row['nama_pja'] ?? null,
                    'tipe_pja' => $row['tipe_pja'] ?? null,
                    'perusahaan' => $row['perusahaan'] ?? null,
                    'id_employee' => $row['id_employee'] ?? null,
                    'id_nama_pja' => $row['id_nama_pja'] ?? null,
                    'pja_kategori' => $row['pja_kategori'] ?? null,
                    'id_pja_parent' => $row['id_pja_parent'] ?? null,
                    'nama_karyawan' => $row['nama_karyawan'] ?? null,
                    'id_pja_employee' => $row['id_pja_employee'] ?? null,
                    'status_karyawan' => $row['status_karyawan'] ?? null,
                    'status_nama_pja' => $row['status_nama_pja'] ?? null,
                    'pja_kategory_layer' => $row['pja_kategory_layer'] ?? null,
                    'status_pja_karyawan' => $statusPjaKaryawan, // Dari checkinout_rfid, bukan dari wan_vw_pja_karyawan
                    'tanggal_update_nama_pja' => $row['tanggal_update_nama_pja'] ?? null,
                    'tanggal_update_data_karyawan' => $row['tanggal_update_data_karyawan'] ?? null,
                    'tanggal_update_data_pja_karyawan' => $row['tanggal_update_data_pja_karyawan'] ?? null,
                    'status_onsite' => $statusOnsite,
                ];
            }
            
            // Format CCTV Dedicated data
            $cctvDedicatedData = $cctvDedicated->map(function ($item) {
                return [
                    'id' => $item->id,
                    'no' => $item->no,
                    'pja' => $item->pja,
                    'cctv_dedicated' => $item->cctv_dedicated,
                ];
            })->toArray();
            
            // Calculate statistics
            $totalKaryawan = count($karyawanData);
            $karyawanAktif = count(array_filter($karyawanData, function($k) {
                return ($k['status_karyawan'] ?? '0') == '1' || ($k['status_karyawan'] ?? '0') == 1;
            }));
            $pjaAktif = count(array_filter($karyawanData, function($k) {
                return ($k['status_nama_pja'] ?? '0') == '1' || ($k['status_nama_pja'] ?? '0') == 1;
            }));
            // Count total onsite (karyawan yang memiliki status_onsite tidak null)
            $totalOnsite = count(array_filter($karyawanData, function($k) {
                return !empty($k['status_onsite']);
            }));
            $totalCctvDedicated = count($cctvDedicatedData);
            $karyawanDenganCctv = 0;
            
            // Count karyawan yang memiliki CCTV dedicated
            foreach ($karyawanData as $karyawan) {
                $namaPja = $karyawan['nama_pja'] ?? '';
                foreach ($cctvDedicatedData as $cctv) {
                    if ($cctv['pja'] === $namaPja) {
                        $karyawanDenganCctv++;
                        break;
                    }
                }
            }
            
            // Get intervensi data untuk mengecek apakah PJA sudah terintervensi
            $intervensiMap = [];
            try {
                $intervensiList = IntervensiKesiapanOrang::select('nama_pja', 'id_employee', 'status')
                    ->where('status', 'open') // Hanya ambil yang masih open
                    ->get();
                
                foreach ($intervensiList as $intervensi) {
                    $key = ($intervensi->nama_pja ?? '') . '|' . ($intervensi->id_employee ?? '');
                    if (!empty($key) && $key !== '|') {
                        $intervensiMap[$key] = true;
                    }
                }
            } catch (Exception $e) {
                Log::error('Error querying intervensi kesiapan orang: ' . $e->getMessage());
                // Continue without intervensi data if query fails
            }
            
            // Add intervensi status to karyawan data
            foreach ($karyawanData as &$karyawan) {
                $key = ($karyawan['nama_pja'] ?? '') . '|' . ($karyawan['id_employee'] ?? '');
                $karyawan['has_intervensi'] = isset($intervensiMap[$key]) && $intervensiMap[$key];
            }
            unset($karyawan); // Unset reference
            
            $statistics = [
                'total_karyawan' => $totalKaryawan,
                'karyawan_aktif' => $karyawanAktif,
                'pja_aktif' => $pjaAktif,
                'total_onsite' => $totalOnsite,
                'total_cctv_dedicated' => $totalCctvDedicated,
                'karyawan_dengan_cctv' => $karyawanDenganCctv,
                'total_cctv' => $totalCctv,
                'cctv_dengan_pja' => $cctvWithPjaCount,
                'persentase_cctv_dengan_pja' => $persentaseCctvDenganPja,
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'karyawan' => $karyawanData,
                    'cctv_dedicated' => $cctvDedicatedData,
                ],
                'statistics' => $statistics,
                'count' => [
                    'karyawan' => count($karyawanData),
                    'cctv_dedicated' => count($cctvDedicatedData),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching kesiapan orang data via API: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
                'statistics' => [
                    'total_karyawan' => 0,
                    'karyawan_aktif' => 0,
                    'pja_aktif' => 0,
                    'total_cctv_dedicated' => 0,
                    'karyawan_dengan_cctv' => 0,
                    'total_cctv' => 0,
                    'cctv_dengan_pja' => 0,
                    'persentase_cctv_dengan_pja' => 0,
                ]
            ], 500);
        }
    }

    /**
     * Store intervensi kesiapan orang
     */
    public function storeIntervensiKesiapanOrang(Request $request)
    {
        try {
            $validated = $request->validate([
                'nama_pja' => 'required|string|max:255',
                'tipe_pja' => 'nullable|string|max:255',
                'perusahaan' => 'nullable|string|max:255',
                'id_employee' => 'nullable|string',
                'pic_id' => 'required|string',
                'issue' => 'required|string',
            ]);

            // Get authenticated user
            $user = Auth::user();
            $createdBy = $user ? $user->name : 'Unknown';
            $createdByEmail = $user ? $user->email : null;

            // Get PIC details from MySQL using pic_id from form
            $picId = $validated['pic_id'];
            $picUsername = null;
            $picNama = null;
            $picTelepon = null;
            $namaKaryawan = null;

            // Get PIC details from MySQL
            if (!empty($picId)) {
                try {
                    $picInfo = DB::table('vw_user')
                        ->where('id', $picId)
                        ->select('id', 'username', 'nama', 'selular')
                        ->first();
                    
                    if ($picInfo) {
                        $picId = (string)($picInfo->id ?? '');
                        $picUsername = $picInfo->username ?? null;
                        $picNama = $picInfo->nama ?? null;
                        $picTelepon = $picInfo->selular ?? null;
                    }
                } catch (Exception $e) {
                    Log::error('Error getting PIC details from MySQL: ' . $e->getMessage());
                    // Continue without PIC details if query fails
                }
            }
            
            // Get nama karyawan from id_employee if provided
            if (!empty($validated['id_employee'])) {
                try {
                    $karyawanInfo = DB::table('vw_user')
                        ->where('id', $validated['id_employee'])
                        ->select('id', 'nama')
                        ->first();
                    
                    if ($karyawanInfo) {
                        $namaKaryawan = $karyawanInfo->nama ?? null;
                    }
                } catch (Exception $e) {
                    Log::error('Error getting karyawan details from MySQL: ' . $e->getMessage());
                    // Continue without karyawan name if query fails
                }
            }

            // Determine lokasi from perusahaan or use default
            $lokasi = $validated['perusahaan'] ?? 'Unknown';

            // Store intervensi using IntervensiKesiapanOrang model
            $intervensi = IntervensiKesiapanOrang::create([
                'lokasi' => $lokasi,
                'area_kerja' => 'Kesiapan Orang',
                'nama_pja' => $validated['nama_pja'],
                'tipe_pja' => $validated['tipe_pja'] ?? null,
                'perusahaan' => $validated['perusahaan'] ?? null,
                'id_employee' => $validated['id_employee'] ?? null,
                'nama_karyawan' => $namaKaryawan,
                'pic_id' => $picId,
                'pic_username' => $picUsername,
                'pic_nama' => $picNama,
                'pic_telepon' => $picTelepon,
                'issue' => $validated['issue'],
                'status' => 'open', // Default status
                'created_by' => $createdBy,
                'created_by_email' => $createdByEmail,
            ]);

            // Prepare WhatsApp URL if PIC telepon is available
            $whatsappNumber = $picTelepon;
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
                $pesan = "Form Intervensi Kesiapan Orang\n\n";
                $pesan .= "Pelapor: " . $createdBy . "\n";
                $pesan .= "Nama PJA: " . $validated['nama_pja'] . "\n";
                if ($validated['tipe_pja']) {
                    $pesan .= "Tipe PJA: " . $validated['tipe_pja'] . "\n";
                }
                if ($validated['perusahaan']) {
                    $pesan .= "Perusahaan: " . $validated['perusahaan'] . "\n";
                }
                if ($picNama) {
                    $pesan .= "PIC: " . ($picUsername ?? '') . " - " . $picNama . "\n";
                }
                $pesan .= "Issue:\n" . $validated['issue'] . "\n\n";
                $pesan .= "Link: https://besentry.beraucoal.co.id/maps";
                
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
            Log::error('Error storing intervensi kesiapan orang: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan intervensi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get area kerja data (Boundary Area Kerja, WMS Links, and CCTV Coverage)
     */
    public function getAreaKerjaData(Request $request)
    {
        try {
            // Check user role for filtering
            $user = Auth::user();
            $isControlRoomPama = false;
            $isControlRoomMtn = false;
            $isAdminHazardMotion = false;
            
            if ($user) {
                $isControlRoomPama =
                    $this->userHasAnyRoleAlias($user, [
                        'control_room_pama',
                        'control-room-pama',
                        'hazard-motion-it-pama',
                        'hazard_motion_it_pama',
                    ]) ||
                    $user->hasPermission('hazard-motion-it-pama');

                $isControlRoomMtn =
                    $this->userHasAnyRoleAlias($user, [
                        'control_room_mtn',
                        'control-room-mtn',
                        'hazard-motion-it-mtl',
                        'hazard_motion_it_mtl',
                    ]) ||
                    $user->hasPermission('hazard-motion-it-mtl');

                $isAdminHazardMotion = $user->hasRole('admin_hazard_motion') || $user->hasRole('admin-hazard-motion');
            }
            
            // Get total boundary area kerja from geojson_areas where type = 'area_kerja'
            $totalBoundaryAreaKerja = GeojsonArea::where('type', 'area_kerja')->count();
            
            // Get last updated week for area kerja
            $lastUpdatedAreaKerja = GeojsonArea::where('type', 'area_kerja')
                ->orderBy('updated_at', 'desc')
                ->first();
            $lastWeekAreaKerja = $lastUpdatedAreaKerja ? $lastUpdatedAreaKerja->week : null;
            $lastYearAreaKerja = $lastUpdatedAreaKerja ? $lastUpdatedAreaKerja->year : null;
            
            // Get total WMS links
            $totalWmsLinks = WmsLink::count();
            
            // Get last updated week for WMS links
            $lastUpdatedWms = WmsLink::orderBy('updated_at', 'desc')->first();
            $lastWeekWms = $lastUpdatedWms ? $lastUpdatedWms->week : null;
            $lastYearWms = $lastUpdatedWms ? $lastUpdatedWms->year : null;
            
            // Get total CCTV coverage
            $totalCctvCoverage = CctvCoverage::count();
            
            // Get area highrisk from cctv_coverage where kategori_area = 'Area Highrisk'
            $totalAreaHighrisk = CctvCoverage::where('kategori_area', 'Area Highrisk')->count();
            
            // Get area kritis from cctv_coverage where kategori_area = 'Area Kritis'
            $totalAreaKritis = CctvCoverage::where('kategori_area', 'Area Kritis')->count();
            
            // Calculate percentages
            // Boundary Area Kerja: percentage based on current week coverage vs total
            $currentWeek = GeojsonArea::getCurrentWeek();
            $currentYear = GeojsonArea::getCurrentYear();
            $areasCurrentWeek = GeojsonArea::where('type', 'area_kerja')
                ->where('year', $currentYear)
                ->where('week', $currentWeek)
                ->count();
            
            // Calculate percentage: areas with current week data / total areas
            // If no current week data, use latest week data
            if ($areasCurrentWeek == 0 && $totalBoundaryAreaKerja > 0) {
                // Get latest week data
                $latestArea = GeojsonArea::where('type', 'area_kerja')
                    ->orderBy('year', 'desc')
                    ->orderBy('week', 'desc')
                    ->first();
                if ($latestArea) {
                    $latestWeek = $latestArea->week;
                    $latestYear = $latestArea->year;
                    $areasLatestWeek = GeojsonArea::where('type', 'area_kerja')
                        ->where('year', $latestYear)
                        ->where('week', $latestWeek)
                        ->count();
                    $boundaryAreaKerjaPercentage = round(($areasLatestWeek / $totalBoundaryAreaKerja) * 100, 2);
                } else {
                    $boundaryAreaKerjaPercentage = 0;
                }
            } else {
                $boundaryAreaKerjaPercentage = $totalBoundaryAreaKerja > 0 
                    ? round(($areasCurrentWeek / $totalBoundaryAreaKerja) * 100, 2) 
                    : 0;
            }
            
            // WMS Links: percentage based on coverage (WMS links vs total boundary areas)
            // Assuming ideal is 1 WMS link per area, or calculate based on current week
            $wmsLinksCurrentWeek = WmsLink::where('year', $currentYear)
                ->where('week', $currentWeek)
                ->count();
            $wmsLinksPercentage = $totalBoundaryAreaKerja > 0 
                ? round(($wmsLinksCurrentWeek / max($totalBoundaryAreaKerja, 1)) * 100, 2) 
                : ($wmsLinksCurrentWeek > 0 ? 100 : 0);
            
            // If no current week WMS links, use total vs a baseline
            if ($wmsLinksCurrentWeek == 0 && $totalWmsLinks > 0) {
                // Use total WMS links vs total areas as percentage
                $wmsLinksPercentage = $totalBoundaryAreaKerja > 0 
                    ? min(round(($totalWmsLinks / $totalBoundaryAreaKerja) * 100, 2), 100) 
                    : 100;
            }
            
            // Area Highrisk: percentage of total CCTV coverage
            $areaHighriskPercentage = $totalCctvCoverage > 0 
                ? round(($totalAreaHighrisk / $totalCctvCoverage) * 100, 2) 
                : 0;
            
            // Area Kritis: percentage of total CCTV coverage
            $areaKritisPercentage = $totalCctvCoverage > 0 
                ? round(($totalAreaKritis / $totalCctvCoverage) * 100, 2) 
                : 0;
            
            // Get all CCTV coverage data (for table display) with no_cctv from cctv_data_bmo2
            // Limit to 5000 records to prevent timeout, can be increased if needed
            $cctvCoverageQuery = CctvCoverage::select(
                'cctv_coverage.id',
                'cctv_coverage.id_cctv',
                'cctv_coverage.coverage_lokasi',
                'cctv_coverage.coverage_detail_lokasi',
                'cctv_coverage.kategori_aktivitas',
                'cctv_coverage.kategori_area',
                'cctv_data_bmo2.no_cctv'
            )
            ->leftJoin('cctv_data_bmo2', 'cctv_coverage.id_cctv', '=', 'cctv_data_bmo2.id');
            
            // Apply filter based on user role
            // If user is control_room_pama, filter by site BMO 2 and perusahaan PT Pamapersada Nusantara
            if ($isControlRoomPama && !$isAdminHazardMotion) {
                $cctvCoverageQuery->where(function($query) {
                    $query->whereRaw('TRIM(cctv_data_bmo2.site) = ?', ['BMO 2'])
                          ->whereRaw('TRIM(cctv_data_bmo2.perusahaan) = ?', ['PT Pamapersada Nusantara']);
                });
            } elseif ($isControlRoomMtn && !$isAdminHazardMotion) {
                $cctvCoverageQuery->where(function($query) {
                    $query->whereRaw('TRIM(cctv_data_bmo2.site) = ?', ['SMO'])
                          ->whereRaw('TRIM(cctv_data_bmo2.perusahaan) = ?', ['PT Madhani Talatah Nusantara']);
                });
            }
            // If user is admin_hazard_motion, show all data (no filter)
            
            $cctvCoverageData = $cctvCoverageQuery
            ->orderBy('cctv_coverage.id', 'desc')
            ->limit(5000)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'no_cctv' => $item->no_cctv,
                    'coverage_lokasi' => $item->coverage_lokasi,
                    'coverage_detail_lokasi' => $item->coverage_detail_lokasi,
                    'kategori_aktivitas' => $item->kategori_aktivitas,
                    'kategori_area' => $item->kategori_area,
                ];
            });
            
            $statistics = [
                'boundary_area_kerja_percentage' => $boundaryAreaKerjaPercentage,
                'wms_links_percentage' => $wmsLinksPercentage,
                'area_highrisk_percentage' => $areaHighriskPercentage,
                'area_kritis_percentage' => $areaKritisPercentage,
                'last_week_area_kerja' => $lastWeekAreaKerja,
                'last_year_area_kerja' => $lastYearAreaKerja,
                'last_week_wms' => $lastWeekWms,
                'last_year_wms' => $lastYearWms,
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'cctv_coverage' => $cctvCoverageData,
                ],
                'statistics' => $statistics,
                'count' => [
                    'cctv_coverage' => $cctvCoverageData->count(),
                ]
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            Log::error('Error fetching area kerja data via API: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Gagal memuat data area kerja. Silakan coba lagi.',
                'data' => [
                    'cctv_coverage' => [],
                ],
                'statistics' => [
                    'boundary_area_kerja_percentage' => 0,
                    'wms_links_percentage' => 0,
                    'area_highrisk_percentage' => 0,
                    'area_kritis_percentage' => 0,
                    'last_week_area_kerja' => null,
                    'last_year_area_kerja' => null,
                    'last_week_wms' => null,
                    'last_year_wms' => null,
                ]
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Get Area Kerja data grouped by coverage_lokasi for sidebar
     */
    public function getAreaKerjaSidebarData(Request $request)
    {
        try {
            // Get CCTV coverage data grouped by coverage_lokasi
            $cctvCoverageData = CctvCoverage::select(
                'cctv_coverage.id',
                'cctv_coverage.id_cctv',
                'cctv_coverage.coverage_lokasi',
                'cctv_coverage.coverage_detail_lokasi',
                'cctv_coverage.kategori_aktivitas',
                'cctv_coverage.kategori_area',
                'cctv_data_bmo2.id as cctv_id',
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
                'cctv_data_bmo2.link_akses'
            )
            ->leftJoin('cctv_data_bmo2', 'cctv_coverage.id_cctv', '=', 'cctv_data_bmo2.id')
            ->whereNotNull('cctv_coverage.coverage_lokasi')
            ->orderBy('cctv_coverage.coverage_lokasi')
            ->orderBy('cctv_data_bmo2.nama_cctv')
            ->get();
            
            // Group by coverage_lokasi
            $groupedData = [];
            foreach ($cctvCoverageData as $item) {
                $coverageLokasi = $item->coverage_lokasi ?? 'Unknown';
                
                if (!isset($groupedData[$coverageLokasi])) {
                    $groupedData[$coverageLokasi] = [
                        'coverage_lokasi' => $coverageLokasi,
                        'cctv_list' => []
                    ];
                }
                
                // Add CCTV to the group
                if ($item->cctv_id) {
                    $groupedData[$coverageLokasi]['cctv_list'][] = [
                        'id' => $item->cctv_id,
                        'no_cctv' => $item->no_cctv,
                        'nama_cctv' => $item->nama_cctv,
                        'kondisi' => $item->kondisi,
                        'status' => $item->status,
                        'lokasi_pemasangan' => $item->lokasi_pemasangan,
                        'coverage_detail_lokasi' => $item->coverage_detail_lokasi,
                        'kategori_aktivitas' => $item->kategori_aktivitas,
                        'kategori_area' => $item->kategori_area,
                        'longitude' => $item->longitude,
                        'latitude' => $item->latitude,
                        'site' => $item->site,
                        'perusahaan' => $item->perusahaan,
                        'control_room' => $item->control_room,
                        'link_akses' => $item->link_akses,
                    ];
                }
            }
            
            // Convert to array format for frontend
            $areaKerjaData = array_values($groupedData);
            
            return response()->json([
                'success' => true,
                'data' => $areaKerjaData,
                'count' => count($areaKerjaData)
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching area kerja sidebar data via API: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get auto alert sidebar data (grouped by alert)
     */
    public function getAutoAlertSidebarData(Request $request)
    {
        try {
            // Get CCTV alerts data grouped by alert_id
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
            Log::error('Error fetching auto alert sidebar data via API: ' . $e->getMessage());
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
     * Mengambil data dari tabel INSPEKSI_HAZARD:
     * - hse_automation.aaj_car_all_year_from_dav (INSPEKSI_HAZARD)
     * Filter per week: Senin sampai Senin (1 week)
     * Menggunakan koneksi langsung ke ClickHouse 10.10.10.38
     */
    private function getSapDataFromClickHouse($weekStart = null)
    {
        Log::info('getSapDataFromClickHouse - Method called', [
            'weekStart' => $weekStart ? (is_string($weekStart) ? $weekStart : $weekStart->format('Y-m-d H:i:s')) : 'NULL'
        ]);
        
        try {

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
            
            // 1. Query aaj_car_all_year_from_dav using direct ClickHouse connection to 10.10.10.38
            try {
                // Create ClickHouse connection with specific configuration
                $host = '10.10.10.38';
                $port = 8123; // Default ClickHouse HTTP port
                $protocol = 'http';
                $baseUrl = $protocol . '://' . $host . ':' . $port;
                $username = 'default';
                $password = 'Zxcdsaqwe321:;';
                $database = 'hse_automation';
                $timeout = 60;
                
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
                    WHERE (
                        (tanggal_pembuatan IS NOT NULL 
                            AND toDate(tanggal_pembuatan, 'Asia/Makassar') >= toDate('{$weekStartStr}')
                            AND toDate(tanggal_pembuatan, 'Asia/Makassar') < toDate('{$weekEndStr}'))
                        OR (bedraft_date IS NOT NULL 
                            AND toDate(bedraft_date, 'Asia/Makassar') >= toDate('{$weekStartStr}')
                            AND toDate(bedraft_date, 'Asia/Makassar') < toDate('{$weekEndStr}'))
                    )
                    ORDER BY 
                        CASE 
                            WHEN tanggal_pembuatan IS NOT NULL THEN toDateTime(tanggal_pembuatan)
                            WHEN bedraft_date IS NOT NULL THEN toDateTime(bedraft_date)
                            ELSE toDateTime('1970-01-01 00:00:00')
                        END DESC
                    LIMIT 12500
                ";
                
                Log::info('Executing query for aaj_car_all_year_from_dav using direct ClickHouse connection', [
                    'host' => $host,
                    'database' => $database,
                    'week_start' => $weekStartStr,
                    'week_end' => $weekEndStr
                ]);
                
                // Execute query using HTTP client directly
                $url = $baseUrl . '/?database=' . urlencode($database) . '&default_format=JSON';
                
                $httpClient = Http::timeout($timeout)
                    ->withBasicAuth($username, $password)
                    ->withBody($sqlInspeksi, 'text/plain');
                
                $response = $httpClient->post($url);
                
                if (!$response->successful()) {
                    Log::error('INSPEKSI_HAZARD - Query failed', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    $resultsInspeksi = [];
                } else {
                    $result = $response->json();
                    
                    // Parse ClickHouse JSON response
                    if (isset($result['data'])) {
                        $resultsInspeksi = $result['data'];
                    } elseif (isset($result[0])) {
                        $resultsInspeksi = $result;
                    } else {
                        // Try to parse as JSON lines format
                        $lines = explode("\n", trim($response->body()));
                        $resultsInspeksi = [];
                        foreach ($lines as $line) {
                            if (!empty(trim($line))) {
                                $decoded = json_decode($line, true);
                                if ($decoded !== null) {
                                    $resultsInspeksi[] = $decoded;
                                }
                            }
                        }
                    }
                    
                    Log::info('Query result from aaj_car_all_year_from_dav', [
                        'result_type' => gettype($resultsInspeksi),
                        'is_array' => is_array($resultsInspeksi),
                        'count' => is_array($resultsInspeksi) ? count($resultsInspeksi) : 0,
                        'empty' => empty($resultsInspeksi),
                        'week_start' => $weekStartStr,
                        'week_end' => $weekEndStr
                    ]);
                }
                
                // If no results, try query without strict date filter (last 30 days as fallback)
                if (empty($resultsInspeksi) || (is_array($resultsInspeksi) && count($resultsInspeksi) === 0)) {
                    Log::warning('INSPEKSI_HAZARD - No results with week filter, trying fallback query (last 30 days)');
                    $fallbackStart = Carbon::now()->subDays(30)->format('Y-m-d');
                    $fallbackEnd = Carbon::now()->addDays(1)->format('Y-m-d');
                    
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
                            ifNull(toString(lokasi_detail), '') as keterangan_lokasi
                        FROM hse_automation.aaj_car_all_year_from_dav
                        WHERE (
                            (tanggal_pembuatan IS NOT NULL 
                                AND toDate(tanggal_pembuatan, 'Asia/Makassar') >= toDate('{$fallbackStart}')
                                AND toDate(tanggal_pembuatan, 'Asia/Makassar') < toDate('{$fallbackEnd}'))
                            OR (bedraft_date IS NOT NULL 
                                AND toDate(bedraft_date, 'Asia/Makassar') >= toDate('{$fallbackStart}')
                                AND toDate(bedraft_date, 'Asia/Makassar') < toDate('{$fallbackEnd}'))
                        )
                        ORDER BY 
                            CASE 
                                WHEN tanggal_pembuatan IS NOT NULL THEN toDateTime(tanggal_pembuatan)
                                WHEN bedraft_date IS NOT NULL THEN toDateTime(bedraft_date)
                                ELSE toDateTime('1970-01-01 00:00:00')
                            END DESC
                        LIMIT 12500
                    ";
                    
                    try {
                        $fallbackResponse = Http::timeout($timeout)
                            ->withBasicAuth($username, $password)
                            ->withBody($fallbackSql, 'text/plain')
                            ->post($url);
                        
                        if ($fallbackResponse->successful()) {
                            $fallbackResult = $fallbackResponse->json();
                            
                            // Parse ClickHouse JSON response
                            $fallbackResults = [];
                            if (isset($fallbackResult['data'])) {
                                $fallbackResults = $fallbackResult['data'];
                            } elseif (isset($fallbackResult[0])) {
                                $fallbackResults = $fallbackResult;
                            } else {
                                // Try to parse as JSON lines format
                                $lines = explode("\n", trim($fallbackResponse->body()));
                                foreach ($lines as $line) {
                                    if (!empty(trim($line))) {
                                        $decoded = json_decode($line, true);
                                        if ($decoded !== null) {
                                            $fallbackResults[] = $decoded;
                                        }
                                    }
                                }
                            }
                            
                            Log::info('INSPEKSI_HAZARD - Fallback query result (last 30 days):', [
                                'count' => is_array($fallbackResults) ? count($fallbackResults) : 0
                            ]);
                            
                            if (!empty($fallbackResults) && is_array($fallbackResults) && count($fallbackResults) > 0) {
                                $resultsInspeksi = $fallbackResults;
                                Log::info('INSPEKSI_HAZARD - Using fallback query results (last 30 days)');
                            }
                        }
                    } catch (Exception $e) {
                        Log::error('INSPEKSI_HAZARD - Fallback query failed: ' . $e->getMessage());
                    }
                }
                
                if (!empty($resultsInspeksi) && is_array($resultsInspeksi)) {
                    // Log first row keys for debugging
                    if (!empty($resultsInspeksi[0])) {
                        $firstRow = is_array($resultsInspeksi[0]) ? $resultsInspeksi[0] : (array)$resultsInspeksi[0];
                        Log::info('First row keys from aaj_car_all_year_from_dav: ' . implode(', ', array_keys($firstRow)));
                        Log::info('First row sample data: ' . json_encode($firstRow, JSON_UNESCAPED_UNICODE));
                    }
                    
                    $processedCount = 0;
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
                            
                            // Send directly to formatSapRow without additional mapping
                            $formattedRow = $this->formatSapRow($row, 'INSPEKSI_HAZARD');
                            
                            // Log first row for debugging
                            if ($processedCount === 0 && !empty($formattedRow)) {
                                Log::info('INSPEKSI_HAZARD - First formatted row sample', [
                                    'task_number' => $formattedRow['task_number'] ?? null,
                                    'tanggal_pelaporan' => $formattedRow['tanggal_pelaporan'] ?? null,
                                    'detected_at' => $formattedRow['detected_at'] ?? null,
                                    'source_type' => $formattedRow['source_type'] ?? null,
                                    'has_location' => !empty($formattedRow['location']['lat']) && !empty($formattedRow['location']['lng']),
                                ]);
                            }
                            
                            $sapData[] = $formattedRow;
                            $processedCount++;
                        } catch (Exception $e) {
                            Log::error('Error processing row in aaj_car_all_year_from_dav: ' . $e->getMessage(), [
                                'row' => json_encode($row, JSON_UNESCAPED_UNICODE)
                            ]);
                        }
                    }
                    
                    Log::info('Inspeksi Hazard processed', [
                        'total_records' => count($resultsInspeksi),
                        'processed_count' => $processedCount,
                        'sap_data_count' => count($sapData)
                    ]);
                } else {
                    Log::warning('No results or empty results from aaj_car_all_year_from_dav query');
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
            
            // Count by source type
            $countByType = [
                'INSPEKSI_HAZARD' => 0
            ];
            
            foreach ($sapData as $sap) {
                $sourceType = $sap['source_type'] ?? 'UNKNOWN';
                if (isset($countByType[$sourceType])) {
                    $countByType[$sourceType]++;
                }
            }
            
            // Count by source type in final sapData
            $finalCountByType = [];
            $finalCountWithDate = [];
            $finalCountWithoutDate = [];
            foreach ($sapData as $sap) {
                $type = $sap['source_type'] ?? 'UNKNOWN';
                $finalCountByType[$type] = ($finalCountByType[$type] ?? 0) + 1;
                
                if (!empty($sap['tanggal_pelaporan']) || !empty($sap['detected_at'])) {
                    $finalCountWithDate[$type] = ($finalCountWithDate[$type] ?? 0) + 1;
                } else {
                    $finalCountWithoutDate[$type] = ($finalCountWithoutDate[$type] ?? 0) + 1;
                }
            }
            
            Log::info('SAP data fetched: ' . count($sapData) . ' items from INSPEKSI_HAZARD table', [
                'week_start' => $weekStartStr,
                'week_end' => $weekEndStr,
                'inspeksi_hazard' => count($resultsInspeksi ?? []),
                'count_by_type' => $countByType,
                'final_count_by_type' => $finalCountByType,
                'final_count_with_date' => $finalCountWithDate,
                'final_count_without_date' => $finalCountWithoutDate,
                'total_sap_data' => count($sapData)
            ]);
            
            if (count($sapData) === 0) {
                Log::warning('No SAP data found for week: ' . $weekStartStr . ' to ' . $weekEndStr);
            }
            
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
        $data = [
            'id' => 'SAP-' . ($taskNumber ?: uniqid()),
            'task_number' => $taskNumber,
            'type' => $jenisLaporan,
            'jenis_laporan' => $jenisLaporan,
            'source_type' => $sourceType, // INSPEKSI_HAZARD
            'aktivitas_pekerjaan' => $cleanValue($row['aktivitas_pekerjaan'] ?? null),
            'lokasi' => $cleanValue($row['lokasi'] ?? null),
            'detail_lokasi' => $cleanValue($row['detail_lokasi'] ?? null),
            'keterangan' => $cleanValue($row['keterangan'] ?? null),
            'tanggal_pelaporan' => $this->ensureValidDate($row['tanggal_pelaporan'] ?? null),
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
                
                $parsed = false;
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
                // Log for debugging
                Log::warning('Could not parse date value: ' . $dateValue . ' - Error: ' . $e->getMessage());
                return $dateValue;
            }
        }
        
        return $dateValue;
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

            // Get allowed company and sites based on role
            $roleAccess = $this->getAllowedCompanyAndSiteByRole();
            $allowedCompany = $roleAccess['company'];
            $allowedSites = $roleAccess['sites'];
            
            // Override company filter if user has specific role
            if ($allowedCompany) {
                $company = $allowedCompany;
            }
            
            // Override site filter if user has specific role with site restrictions
            if (!empty($allowedSites)) {
                // If user has site restrictions, only allow filtering by those sites
                // If requested site is not in allowed sites, use first allowed site or '__all__'
                if ($site !== '__all__' && !in_array($site, $allowedSites)) {
                    $site = !empty($allowedSites) ? $allowedSites[0] : '__all__';
                }
            }

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
            
            // Apply role-based site filter if user has site restrictions
            if (!empty($allowedSites)) {
                $query->whereIn('site', $allowedSites);
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
            
            // Get allowed company and sites based on role
            $roleAccess = $this->getAllowedCompanyAndSiteByRole();
            $allowedCompany = $roleAccess['company'];
            $allowedSites = $roleAccess['sites'];
            
            // Override company filter if user has specific role
            if ($allowedCompany) {
                $company = $allowedCompany;
            }
            
            // Override site filter if user has specific role with site restrictions
            // Always apply site filter if user has site restrictions, even if site is '__all__'
            if (!empty($allowedSites)) {
                // If site is '__all__' or not in allowed sites, use first allowed site
                if ($site === '__all__' || !in_array($site, $allowedSites)) {
                    $site = $allowedSites[0];
                }
            }
            
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
            
            // Apply role-based site filter if user has site restrictions
            if (!empty($allowedSites)) {
                $query->whereIn('site', $allowedSites);
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
            
            // Aktivitas Highrisk dari tabel cctv_coverage
            // Ambil ID CCTV yang sesuai dengan filter company dan site
            $cctvIds = (clone $query)->pluck('id')->toArray();
            
            // Inisialisasi variabel dengan default 0
            $aktivitasHighrisk = 0;
            
            // Hitung aktivitas highrisk dari tabel cctv_coverage
            // Mencari kategori_aktivitas yang bernilai "Aktivitas Highrisk" atau "Aktivitas Kritis"
            try {
                $aktivitasHighriskQuery = CctvCoverage::query();
                
                // Filter berdasarkan ID CCTV jika ada
                if (!empty($cctvIds)) {
                    $aktivitasHighriskQuery->whereIn('id_cctv', $cctvIds);
                }
                
                // Filter berdasarkan kategori aktivitas
                $aktivitasHighriskQuery->where(function($q) {
                    $q->where('kategori_aktivitas', 'Aktivitas Highrisk')
                      ->orWhere('kategori_aktivitas', 'Aktivitas Kritis');
                });
                
                $aktivitasHighrisk = $aktivitasHighriskQuery->count();
                
                // Debug logging
                Log::info('Aktivitas Highrisk Calculation', [
                    'cctv_ids_count' => count($cctvIds),
                    'aktivitas_highrisk_count' => $aktivitasHighrisk,
                    'query_sql' => $aktivitasHighriskQuery->toSql()
                ]);
            } catch (Exception $e) {
                Log::error('Error calculating aktivitas highrisk: ' . $e->getMessage());
                $aktivitasHighrisk = 0;
            }
            
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
                'aktivitasHighrisk' => isset($aktivitasHighrisk) ? $aktivitasHighrisk : 0,
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
                'aktivitasHighrisk' => 0,
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
            
            // Get allowed company and sites based on role
            $roleAccess = $this->getAllowedCompanyAndSiteByRole();
            $allowedCompany = $roleAccess['company'];
            $allowedSites = $roleAccess['sites'];
            
            // Override company filter if user has specific role
            if ($allowedCompany) {
                $company = $allowedCompany;
            }
            
            // Override site filter if user has specific role with site restrictions
            // Always apply site filter if user has site restrictions, even if site is '__all__'
            if (!empty($allowedSites)) {
                // If site is '__all__' or not in allowed sites, use first allowed site
                if ($site === '__all__' || !in_array($site, $allowedSites)) {
                    $site = $allowedSites[0];
                }
            }
            
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
            
            // Apply role-based site filter if user has site restrictions (always apply this as additional filter)
            if (!empty($allowedSites)) {
                $query->whereIn('site', $allowedSites);
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
     * Get user GPS data dari tabel user_gps_latests + users_besigma (MySQL, pola sama seperti unit/gps unit).
     */
    public function getUserGps(Request $request)
    {
        try {
            // Ambil data GPS orang dari tabel user_gps_latests (cara sama seperti unit: Query Builder langsung)
            $gpsLogs = DB::connection('mysql')
                ->table('user_gps_logs')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereNotNull('user_id')
                ->where('latitude', '!=', '')
                ->where('longitude', '!=', '')
                ->whereRaw('CAST(COALESCE(latitude, 0) AS DECIMAL(10,8)) IS NOT NULL')
                ->whereRaw('CAST(COALESCE(longitude, 0) AS DECIMAL(10,8)) IS NOT NULL')
                ->whereRaw('CAST(COALESCE(latitude, 0) AS DECIMAL(10,8)) != 0')
                ->whereRaw('CAST(COALESCE(longitude, 0) AS DECIMAL(10,8)) != 0')
                ->orderBy('updated_at', 'desc')
                ->limit(500)
                ->get();

            if ($gpsLogs->isEmpty()) {
                Log::info('No user GPS logs in MySQL user_gps_latests');
                return response()->json([
                    'success' => true,
                    'users' => [],
                    'count' => 0
                ]);
            }

            // Deduplikasi per user_id: ambil yang terbaru (urutan sudah updated_at desc)
            $gpsByUser = [];
            foreach ($gpsLogs as $row) {
                $userId = $row->user_id ?? null;
                if (!$userId) {
                    continue;
                }
                if (!isset($gpsByUser[$userId])) {
                    $gpsByUser[$userId] = $row;
                }
            }

            $userIds = array_keys($gpsByUser);

            // Ambil data user dari tabel users_besigma (MySQL, sama seperti unit ambil user)
            $usersRows = DB::connection('mysql')
                ->table('users_besigma')
                ->whereIn('id', $userIds)
                ->get()
                ->keyBy('id');

            // Format data untuk frontend (format sama seperti sebelumnya)
            $userGpsData = [];
            foreach ($gpsByUser as $userId => $row) {
                $latitude = null;
                $longitude = null;
                if (isset($row->latitude)) {
                    $latitude = is_numeric($row->latitude)
                        ? (float) $row->latitude
                        : (float) str_replace(',', '.', (string) $row->latitude);
                }
                if (isset($row->longitude)) {
                    $longitude = is_numeric($row->longitude)
                        ? (float) $row->longitude
                        : (float) str_replace(',', '.', (string) $row->longitude);
                }
                if ($latitude === null || $longitude === null || $latitude == 0 || $longitude == 0) {
                    continue;
                }

                $userIdStr = (string) $userId;
                $userRow = $usersRows->get($userId);

                $userGpsData[] = [
                    'id' => $userIdStr,
                    'user_id' => $userIdStr,
                    'employee_id' => optional($userRow)->employee_id,
                    'npk' => optional($userRow)->npk,
                    'nik' => optional($userRow)->nik,
                    'sid_code' => optional($userRow)->sid_code,
                    'fullname' => optional($userRow)->fullname ?? ('User ' . substr($userIdStr, 0, 8)),
                    'email' => optional($userRow)->email,
                    'phone' => optional($userRow)->phone,
                    'username' => optional($userRow)->username,
                    'division_name' => optional($userRow)->division_name,
                    'department_name' => optional($userRow)->department_name,
                    'site_assignment' => optional($userRow)->site_assignment,
                    'functional_position' => optional($userRow)->functional_position,
                    'structural_position' => optional($userRow)->structural_position,
                    'company_id' => optional($userRow)->company_id,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'location' => ['lat' => $latitude, 'lng' => $longitude],
                    'course' => isset($row->course) && $row->course !== '' ? (float) str_replace(',', '.', $row->course) : null,
                    'battery' => isset($row->battery) && $row->battery !== '' ? (int) $row->battery : null,
                    'timezone' => $row->timezone ?? null,
                    'gps_updated_at' => $row->updated_at ?? null,
                    'gps_created_at' => $row->created_at ?? null,
                    'created_at' => $row->created_at ?? null,
                ];
            }

            Log::info('User GPS from MySQL user_gps_latests + users_besigma', [
                'unique_users' => count($userGpsData),
                'raw_rows' => $gpsLogs->count(),
            ]);

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
            Log::error('Error fetching user GPS data from MySQL: ' . $e->getMessage());
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
            
            // Get allowed company and sites based on role
            $roleAccess = $this->getAllowedCompanyAndSiteByRole();
            $allowedCompany = $roleAccess['company'];
            $allowedSites = $roleAccess['sites'];
            
            // Override company filter if user has specific role
            if ($allowedCompany) {
                $company = $allowedCompany;
            }
            
            // Override site filter if user has specific role with site restrictions
            if (!empty($allowedSites)) {
                if ($site !== '__all__' && !in_array($site, $allowedSites)) {
                    $site = !empty($allowedSites) ? $allowedSites[0] : '__all__';
                }
            }
            
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
                
                // Apply role-based site filter if user has site restrictions
                if (!empty($allowedSites)) {
                    $cctvQuery->whereIn('site', $allowedSites);
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
                // Check if this is for CCTV tab - use new ClickHouse connection for today's data
                $isForCctv = $request->get('for_cctv', 'false') === 'true';
                
                if ($isForCctv) {
                    // Use new ClickHouse connection for CCTV: IP 10.10.10.38, database hse_automation
                    $sapData = $this->getSapDataTodayFromClickHouseCctv();
                } else {
                    // Use existing ClickHouse connection for other purposes
                    $sapData = $this->getSapDataFromClickHouse($weekStart);
                }
                
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

    /**
     * Get Control Room Overview Data - Group by control_room from cctv_data_bmo2
     */
    public function getControlRoomOverview(Request $request)
    {
        try {
            // Get allowed company and sites based on role
            $roleAccess = $this->getAllowedCompanyAndSiteByRole();
            $allowedCompany = $roleAccess['company'];
            $allowedSites = $roleAccess['sites'];
            $requestedCompany = trim((string) $request->query('company', '__all__'));
            $requestedSite = trim((string) $request->query('site', '__all__'));
            
            // Ambil semua data CCTV dari cctv_data_bmo2 dan group by control_room
            $query = CctvData::whereNotNull('control_room')
                ->where('control_room', '!=', '')
                ->whereRaw("TRIM(COALESCE(control_room, '')) != ''");
            
            // Filter by company if user has specific role
            if ($allowedCompany) {
                $query->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
            } elseif ($requestedCompany !== '' && $requestedCompany !== '__all__') {
                $query->whereRaw('TRIM(perusahaan) = ?', [$requestedCompany]);
            }
            
            // Filter by sites if user has specific role with site restrictions
            if (!empty($allowedSites)) {
                $query->whereIn('site', $allowedSites);
            } elseif ($requestedSite !== '' && $requestedSite !== '__all__') {
                $query->whereRaw('TRIM(site) = ?', [$requestedSite]);
            }
            
            // Get all CCTV data
            $allCctvData = $query->get();
            
            // Group by control_room
            $groupedData = $allCctvData->groupBy(function($item) {
                return trim($item->control_room);
            });
            
            // Get current date and shift for P2H status
            $today = Carbon::now()->toDateString();
            $currentShift = $this->getCurrentShift();
            
            // Get current user name to check if user is pengawas
            $user = Auth::user();
            $userName = $user ? $user->name : null;
            
            // Get all control rooms where user is pengawas
            $userSupervisedControlRooms = [];
            if ($userName) {
                $pengawasRecords = CctvControlRoomPengawas::where('nama_pengawas', $userName)->get();
                $userSupervisedControlRooms = $pengawasRecords->pluck('control_room')->filter()->unique()->toArray();
            }
            
            // Get P2H status for all control rooms
            $p2hStatusMap = [];
            $allControlRooms = $groupedData->keys();
            foreach ($allControlRooms as $controlRoom) {
                // Check if has P2H today for current shift
                $hasP2hToday = CctvP2hChecklist::where('control_room', $controlRoom)
                    ->whereDate('tanggal_pemeriksaan', $today)
                    ->where('shift', $currentShift)
                    ->where('status', 'completed')
                    ->exists();
                
                // Get latest P2H
                $latestP2h = CctvP2hChecklist::where('control_room', $controlRoom)
                    ->where('status', 'completed')
                    ->orderBy('tanggal_pemeriksaan', 'desc')
                    ->orderBy('shift', 'desc')
                    ->first();
                
                // Check if current user is pengawas for this control room
                $isPengawas = in_array($controlRoom, $userSupervisedControlRooms);
                
                $p2hStatusMap[$controlRoom] = [
                    'has_p2h_today' => $hasP2hToday,
                    'latest_p2h_date' => $latestP2h ? $latestP2h->tanggal_pemeriksaan->format('Y-m-d') : null,
                    'latest_p2h_shift' => $latestP2h ? $latestP2h->shift : null,
                    'latest_p2h_pengawas' => $latestP2h ? $latestP2h->nama_pengawas : null,
                    'is_pengawas' => $isPengawas,
                ];
            }
            
            // Format data untuk response
            $controlRoomStats = $groupedData->map(function($items, $controlRoom) use ($p2hStatusMap) {
                $total = $items->count();
                $aktif = $items->filter(function($item) {
                    $status = strtolower($item->status ?? '');
                    $kondisi = strtolower($item->kondisi ?? '');
                    return in_array($status, ['aktif', 'connected', 'live view']) || 
                           in_array($kondisi, ['baik']);
                })->count();
                $tidakAktif = $total - $aktif;
                
                // Format CCTV list untuk detail
                $cctvList = $items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'no_cctv' => $item->no_cctv ?? null,
                        'nomor_cctv' => $item->no_cctv ?? null,
                        'nama_cctv' => $item->nama_cctv ?? null,
                        'name' => $item->nama_cctv ?? 'CCTV ' . $item->id,
                        'status' => $item->status ?? $item->kondisi ?? 'Unknown',
                        'kondisi' => $item->kondisi ?? null,
                        'site' => $item->site ?? null,
                        'perusahaan' => $item->perusahaan ?? null,
                        'perusahaan_cctv' => $item->perusahaan ?? null,
                    ];
                })->values()->toArray();
                
                // Get P2H status for this control room
                $p2hStatus = $p2hStatusMap[$controlRoom] ?? [
                    'has_p2h_today' => false,
                    'latest_p2h_date' => null,
                    'latest_p2h_shift' => null,
                    'latest_p2h_pengawas' => null,
                    'is_pengawas' => false,
                ];
                
                return [
                    'name' => $controlRoom,
                    'total' => $total,
                    'aktif' => $aktif,
                    'tidak_aktif' => $tidakAktif,
                    'cctv_list' => $cctvList,
                    'p2h_status' => $p2hStatus
                ];
            })->values();
            
            // Calculate totals
            $totalControlRooms = $controlRoomStats->count();
            $totalCctv = $controlRoomStats->sum('total');
            $totalAktif = $controlRoomStats->sum('aktif');
            
            // Calculate P2H statistics
            $totalSudahP2h = $controlRoomStats->filter(function($room) {
                return $room['p2h_status']['has_p2h_today'] ?? false;
            })->count();
            
            $totalBelumP2h = $totalControlRooms - $totalSudahP2h;
            
            // Calculate P2H percentage
            $p2hPercentage = $totalControlRooms > 0 
                ? round(($totalSudahP2h / $totalControlRooms) * 100, 1) 
                : 0;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'control_rooms' => $controlRoomStats,
                    'total_control_rooms' => $totalControlRooms,
                    'total_cctv' => $totalCctv,
                    'total_aktif' => $totalAktif,
                    'total_sudah_p2h' => $totalSudahP2h,
                    'total_belum_p2h' => $totalBelumP2h,
                    'p2h_percentage' => $p2hPercentage
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Error getting control room overview: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error getting control room overview: ' . $e->getMessage(),
                'data' => [
                    'control_rooms' => [],
                    'total_control_rooms' => 0,
                    'total_cctv' => 0,
                    'total_aktif' => 0,
                    'total_sudah_p2h' => 0,
                    'total_belum_p2h' => 0,
                    'p2h_percentage' => 0
                ]
            ], 500);
        }
    }

    /**
     * Determine current shift based on time
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
     * Get SAP data today from ClickHouse for CCTV
     * Uses specific ClickHouse connection: IP 10.10.10.38, database hse_automation, table aaj_car_all_year_from_dav
     */
    private function getSapDataTodayFromClickHouseCctv()
    {
        try {
            // Create ClickHouse connection with specific configuration for CCTV
            $host = '10.10.10.38';
            $port = 8123; // Default ClickHouse HTTP port
            $protocol = 'http';
            $baseUrl = $protocol . '://' . $host . ':' . $port;
            $username = 'default';
            $password = 'Zxcdsaqwe321:;';
            $database = 'hse_automation';
            $timeout = 30;

            // Get today's date
            $today = Carbon::now()->format('Y-m-d');
            
            Log::info('getSapDataTodayFromClickHouseCctv - Fetching SAP data for today', [
                'host' => $host,
                'database' => $database,
                'date' => $today
            ]);

            // Build SQL query for today's SAP data
            $sql = "
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
                WHERE (
                    (tanggal_pembuatan IS NOT NULL 
                        AND toDate(tanggal_pembuatan, 'Asia/Makassar') = toDate('{$today}'))
                    OR (bedraft_date IS NOT NULL 
                        AND toDate(bedraft_date, 'Asia/Makassar') = toDate('{$today}'))
                )
                ORDER BY 
                    CASE 
                        WHEN tanggal_pembuatan IS NOT NULL THEN toDateTime(tanggal_pembuatan)
                        WHEN bedraft_date IS NOT NULL THEN toDateTime(bedraft_date)
                        ELSE toDateTime('1970-01-01 00:00:00')
                    END DESC
                LIMIT 10000
            ";

            // Execute query using HTTP client directly
            $url = $baseUrl . '/?database=' . urlencode($database) . '&default_format=JSON';
            
            $httpClient = Http::timeout($timeout)
                ->withBasicAuth($username, $password)
                ->withBody($sql, 'text/plain');
            
            $response = $httpClient->post($url);

            if (!$response->successful()) {
                Log::error('getSapDataTodayFromClickHouseCctv - Query failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $result = $response->json();
            
            // Parse ClickHouse JSON response
            $results = [];
            if (isset($result['data'])) {
                $results = $result['data'];
            } elseif (isset($result[0])) {
                $results = $result;
            } else {
                // Try to parse as JSON lines format
                $lines = explode("\n", trim($response->body()));
                foreach ($lines as $line) {
                    if (!empty(trim($line))) {
                        $decoded = json_decode($line, true);
                        if ($decoded !== null) {
                            $results[] = $decoded;
                        }
                    }
                }
            }

            // Format data using formatSapRow method
            $sapData = [];
            foreach ($results as $row) {
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
                    Log::error('Error processing row in getSapDataTodayFromClickHouseCctv: ' . $e->getMessage());
                }
            }

            Log::info('getSapDataTodayFromClickHouseCctv - Success', [
                'total_records' => count($results),
                'processed_count' => count($sapData)
            ]);

            return $sapData;

        } catch (Exception $e) {
            Log::error('Error in getSapDataTodayFromClickHouseCctv: ' . $e->getMessage());
            return [];
        }
    }
}

