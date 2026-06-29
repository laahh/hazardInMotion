<?php

namespace App\Http\Controllers;

use App\Models\CctvData;
use App\Models\CctvCoverage;
use App\Models\PjaCctv;
use App\Models\PjaCctvDedicated;
use App\Models\CctvControlRoomPengawas;
use App\Models\IntervensiControlRoom;
use App\Services\ClickHouseService;
use App\Jobs\ImportPjaCctvJob;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class CctvDataController extends Controller
{
    /**
     * Get allowed company and site based on user permission
     * Returns array with 'company' and 'sites' keys
     * - 'company': company name if restricted, null if all companies allowed
     * - 'sites': array of allowed site names, empty array if all sites allowed
     */
    private function getAllowedCompanyAndSiteByPermission()
    {
        $user = Auth::user();
        if (!$user) {
            return ['company' => null, 'sites' => []];
        }

        // Permission-based access mapping
        // Format: 'permission_slug' => ['company' => 'Company Name', 'sites' => ['Site 1', 'Site 2']]
        // If 'sites' is empty array, user can access all sites within the company
        // If 'company' is null, user can access all companies
        $permissionAccessMap = [
            'hazard-motion-it-pama' => [
                'company' => 'PT Pamapersada Nusantara',
                'sites' => ['BMO 2'] // User dengan permission ini hanya bisa akses site BMO 2
            ],
            'hazard-motion-it-mtl' => [
                'company' => 'PT Madhani Talatah Nusantara',
                'sites' => ['SMO']
            ],
            // Add more permission mappings here as needed
        ];

        // Check user's permissions and return first matching restriction
        foreach ($permissionAccessMap as $permissionSlug => $access) {
            if ($user->hasPermission($permissionSlug)) {
                return [
                    'company' => $access['company'],
                    'sites' => $access['sites']
                ];
            }
        }

        // Admin or other users can see all companies and sites
        return ['company' => null, 'sites' => []];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get allowed company and sites based on permission
        $permissionAccess = $this->getAllowedCompanyAndSiteByPermission();
        $allowedCompany = $permissionAccess['company'];
        $allowedSites = $permissionAccess['sites'];
        
        // Base query dengan filter permission
        $baseQuery = CctvData::query();
        
        // Filter by company if user has specific permission
        if ($allowedCompany) {
            $baseQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        
        // Filter by sites if user has specific permission with site restrictions
        if (!empty($allowedSites)) {
            $baseQuery->whereIn('site', $allowedSites);
        }
        
        // Get statistics for dashboard
        $totalCctv = (clone $baseQuery)->count();
        $cctvBaik = (clone $baseQuery)->where('kondisi', 'Baik')->count();
        $cctvRusak = (clone $baseQuery)->where('kondisi', 'Breakdown')->count();
        $cctvLive = (clone $baseQuery)->where('status', 'Live View')->count();
        $cctvWithLink = (clone $baseQuery)->whereNotNull('link_akses')->where('link_akses', '!=', '')->count();
        $cctvWithCoordinates = (clone $baseQuery)->whereNotNull('longitude')->whereNotNull('latitude')->count();
        
        // Distribution by site
        $distributionBySiteQuery = CctvData::select('site', DB::raw('COUNT(*) as count'))
            ->whereNotNull('site')
            ->where('site', '!=', '');
        if ($allowedCompany) {
            $distributionBySiteQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $distributionBySiteQuery->whereIn('site', $allowedSites);
        }
        $distributionBySite = $distributionBySiteQuery->groupBy('site')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
        
        // Distribution by perusahaan
        $distributionByPerusahaanQuery = CctvData::select('perusahaan', DB::raw('COUNT(*) as count'))
            ->whereNotNull('perusahaan')
            ->where('perusahaan', '!=', '');
        if ($allowedCompany) {
            $distributionByPerusahaanQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $distributionByPerusahaanQuery->whereIn('site', $allowedSites);
        }
        $distributionByPerusahaan = $distributionByPerusahaanQuery->groupBy('perusahaan')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
        
        // Distribution by kondisi
        $distributionByKondisiQuery = CctvData::select('kondisi', DB::raw('COUNT(*) as count'))
            ->whereNotNull('kondisi')
            ->where('kondisi', '!=', '');
        if ($allowedCompany) {
            $distributionByKondisiQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $distributionByKondisiQuery->whereIn('site', $allowedSites);
        }
        $distributionByKondisi = $distributionByKondisiQuery->groupBy('kondisi')
            ->orderByDesc('count')
            ->get();
        
        // Distribution by status
        $distributionByStatusQuery = CctvData::select('status', DB::raw('COUNT(*) as count'))
            ->whereNotNull('status')
            ->where('status', '!=', '');
        if ($allowedCompany) {
            $distributionByStatusQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $distributionByStatusQuery->whereIn('site', $allowedSites);
        }
        $distributionByStatus = $distributionByStatusQuery->groupBy('status')
            ->orderByDesc('count')
            ->get();
        
        // Control rooms count
        $totalControlRoomsQuery = CctvData::whereNotNull('control_room')
            ->where('control_room', '!=', '');
        if ($allowedCompany) {
            $totalControlRoomsQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $totalControlRoomsQuery->whereIn('site', $allowedSites);
        }
        $totalControlRooms = $totalControlRoomsQuery->distinct('control_room')
            ->count('control_room');
        
        $stats = [
            'total_cctv' => $totalCctv,
            'cctv_baik' => $cctvBaik,
            'cctv_rusak' => $cctvRusak,
            'cctv_live' => $cctvLive,
            'cctv_with_link' => $cctvWithLink,
            'cctv_with_coordinates' => $cctvWithCoordinates,
            'total_control_rooms' => $totalControlRooms,
            'distribution_by_site' => $distributionBySite,
            'distribution_by_perusahaan' => $distributionByPerusahaan,
            'distribution_by_kondisi' => $distributionByKondisi,
            'distribution_by_status' => $distributionByStatus,
        ];
        
        // Get list of perusahaan and site for filter dropdowns
        $perusahaanListQuery = CctvData::select('perusahaan')
            ->whereNotNull('perusahaan')
            ->where('perusahaan', '!=', '')
            ->distinct();
        if ($allowedCompany) {
            $perusahaanListQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $perusahaanListQuery->whereIn('site', $allowedSites);
        }
        $perusahaanList = $perusahaanListQuery->orderBy('perusahaan')->pluck('perusahaan');
        
        $siteListQuery = CctvData::select('site')
            ->whereNotNull('site')
            ->where('site', '!=', '')
            ->distinct();
        if ($allowedCompany) {
            $siteListQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $siteListQuery->whereIn('site', $allowedSites);
        }
        $siteList = $siteListQuery->orderBy('site')->pluck('site');
        
        return view('cctv-data.index', compact('stats', 'perusahaanList', 'siteList'));
    }

    /**
     * Get CCTV data for DataTable (server-side processing)
     */
    public function getData(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get('start', 0);
        $length = $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';
        $orderColumn = $request->get('order')[0]['column'] ?? 0;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        // Column mapping (sesuai urutan kolom di DataTable)
        $columns = ['id', 'site', 'perusahaan', 'no_cctv', 'nama_cctv', 'status', 'kondisi', 'qr_code', 'id'];
        // Jika kolom pertama (#) yang di-order, gunakan id sebagai gantinya
        if ($orderColumn == 0) {
            $orderColumnName = 'id';
        } else {
            $orderColumnName = $columns[$orderColumn] ?? 'id';
        }

        // Get allowed company and sites based on permission
        $permissionAccess = $this->getAllowedCompanyAndSiteByPermission();
        $allowedCompany = $permissionAccess['company'];
        $allowedSites = $permissionAccess['sites'];

        // Base query
        $query = CctvData::query();
        
        // Filter by company if user has specific permission
        if ($allowedCompany) {
            $query->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        
        // Filter by sites if user has specific permission with site restrictions
        if (!empty($allowedSites)) {
            $query->whereIn('site', $allowedSites);
        }

        // Filter by perusahaan dropdown
        $filterPerusahaan = $request->get('perusahaan');
        if (!empty($filterPerusahaan)) {
            $query->whereRaw('TRIM(perusahaan) = ?', [trim($filterPerusahaan)]);
        }

        // Filter by site dropdown
        $filterSite = $request->get('site');
        if (!empty($filterSite)) {
            $query->where('site', $filterSite);
        }

        // Search functionality
        if (!empty($searchValue)) {
            $query->where(function($q) use ($searchValue) {
                $q->where('site', 'like', '%' . $searchValue . '%')
                  ->orWhere('perusahaan', 'like', '%' . $searchValue . '%')
                  ->orWhere('no_cctv', 'like', '%' . $searchValue . '%')
                  ->orWhere('nama_cctv', 'like', '%' . $searchValue . '%')
                  ->orWhere('status', 'like', '%' . $searchValue . '%')
                  ->orWhere('kondisi', 'like', '%' . $searchValue . '%');
            });
        }

        // Get total records dengan filter permission
        $recordsTotalQuery = CctvData::query();
        if ($allowedCompany) {
            $recordsTotalQuery->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
        }
        if (!empty($allowedSites)) {
            $recordsTotalQuery->whereIn('site', $allowedSites);
        }
        $recordsTotal = $recordsTotalQuery->count();
        $recordsFiltered = $query->count();

        // Order and paginate
        $data = $query->orderBy($orderColumnName, $orderDir)
                     ->skip($start)
                     ->take($length)
                     ->get();

        // Format data for DataTable
        $formattedData = $data->map(function($item, $index) use ($start) {
            return [
                'DT_RowIndex' => $start + $index + 1,
                'site' => $item->site ?? '-',
                'perusahaan' => $item->perusahaan ?? '-',
                'no_cctv' => $item->no_cctv ?? '-',
                'nama_cctv' => $item->nama_cctv ?? '-',
                'status' => $item->status ? '<span class="badge bg-' . ($item->status == 'Live View' ? 'success' : 'secondary') . '">' . $item->status . '</span>' : '<span class="text-muted">-</span>',
                'kondisi' => $item->kondisi ? '<span class="badge bg-' . ($item->kondisi == 'Baik' ? 'success' : 'warning') . '">' . $item->kondisi . '</span>' : '<span class="text-muted">-</span>',
                'qr_code' => '<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#qrModal' . $item->id . '" title="Lihat QR Code"><i class="material-icons-outlined">qr_code</i></button>',
                'actions' => '<div class="d-flex gap-2 flex-wrap">' .
                            '<a href="' . route('cctv-data.show', $item->id) . '" class="btn btn-sm btn-info" title="Detail"><i class="material-icons-outlined">visibility</i></a>' .
                            '<a href="' . route('cctv-data.edit', $item->id) . '" class="btn btn-sm btn-warning" title="Edit"><i class="material-icons-outlined">edit</i></a>' .
                            '<button type="button" class="btn btn-sm btn-danger btn-delete" data-id="' . $item->id . '" data-nama="' . ($item->nama_cctv ?? $item->no_cctv ?? 'CCTV') . '" title="Hapus"><i class="material-icons-outlined">delete</i></button>' .
                            '</div>',
                'id' => $item->id,
                'nama_cctv_display' => $item->nama_cctv ?? $item->no_cctv ?? '-',
                'no_cctv_display' => $item->no_cctv ?? '-'
            ];
        });

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $formattedData
        ]);
    }

    /**
     * Export semua data CCTV (cctv_data_bmo2) ke Excel - semua kolom tabel
     */
    public function exportCctvData()
    {
        try {
            $permissionAccess = $this->getAllowedCompanyAndSiteByPermission();
            $allowedCompany = $permissionAccess['company'];
            $allowedSites = $permissionAccess['sites'];

            $query = CctvData::query();
            if ($allowedCompany) {
                $query->whereRaw('TRIM(perusahaan) = ?', [$allowedCompany]);
            }
            if (!empty($allowedSites)) {
                $query->whereIn('site', $allowedSites);
            }
            $data = $query->orderBy('site')->orderBy('no_cctv')->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Semua kolom tabel cctv_data_bmo2 (sesuai fillable + id, timestamps)
            $headers = [
                'No', 'id', 'site', 'perusahaan', 'kategori', 'no_cctv', 'nama_cctv', 'fungsi_cctv', 'bentuk_instalasi_cctv',
                'jenis', 'tipe_cctv', 'radius_pengawasan', 'jenis_spesifikasi_zoom', 'lokasi_pemasangan', 'control_room',
                'status', 'kondisi', 'longitude', 'latitude', 'coverage_lokasi', 'coverage_detail_lokasi',
                'kategori_area_tercapture', 'kategori_aktivitas_tercapture', 'link_akses', 'user_name', 'password',
                'connected', 'mirrored', 'fitur_auto_alert', 'keterangan', 'verifikasi_by_petugas_ocr',
                'bulan_update', 'tahun_update', 'qr_code', 'created_at', 'updated_at'
            ];
            $col = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue($col . '1', $h);
                $col++;
            }
            $lastCol = chr(ord('A') + count($headers) - 1);
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            ];
            $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray($headerStyle);

            $rowNum = 2;
            foreach ($data as $index => $item) {
                $sheet->setCellValue('A' . $rowNum, $index + 1);
                $sheet->setCellValue('B' . $rowNum, $item->id);
                $sheet->setCellValue('C' . $rowNum, $item->site ?? '');
                $sheet->setCellValue('D' . $rowNum, $item->perusahaan ?? '');
                $sheet->setCellValue('E' . $rowNum, $item->kategori ?? '');
                $sheet->setCellValue('F' . $rowNum, $item->no_cctv ?? '');
                $sheet->setCellValue('G' . $rowNum, $item->nama_cctv ?? '');
                $sheet->setCellValue('H' . $rowNum, $item->fungsi_cctv ?? '');
                $sheet->setCellValue('I' . $rowNum, $item->bentuk_instalasi_cctv ?? '');
                $sheet->setCellValue('J' . $rowNum, $item->jenis ?? '');
                $sheet->setCellValue('K' . $rowNum, $item->tipe_cctv ?? '');
                $sheet->setCellValue('L' . $rowNum, $item->radius_pengawasan ?? '');
                $sheet->setCellValue('M' . $rowNum, $item->jenis_spesifikasi_zoom ?? '');
                $sheet->setCellValue('N' . $rowNum, $item->lokasi_pemasangan ?? '');
                $sheet->setCellValue('O' . $rowNum, $item->control_room ?? '');
                $sheet->setCellValue('P' . $rowNum, $item->status ?? '');
                $sheet->setCellValue('Q' . $rowNum, $item->kondisi ?? '');
                $sheet->setCellValue('R' . $rowNum, $item->longitude !== null ? (string) $item->longitude : '');
                $sheet->setCellValue('S' . $rowNum, $item->latitude !== null ? (string) $item->latitude : '');
                $sheet->setCellValue('T' . $rowNum, $item->coverage_lokasi ?? '');
                $sheet->setCellValue('U' . $rowNum, $item->coverage_detail_lokasi ?? '');
                $sheet->setCellValue('V' . $rowNum, $item->kategori_area_tercapture ?? '');
                $sheet->setCellValue('W' . $rowNum, $item->kategori_aktivitas_tercapture ?? '');
                $sheet->setCellValue('X' . $rowNum, $item->link_akses ?? '');
                $sheet->setCellValue('Y' . $rowNum, $item->user_name ?? '');
                $sheet->setCellValue('Z' . $rowNum, $item->password ?? '');
                $sheet->setCellValue('AA' . $rowNum, $item->connected ?? '');
                $sheet->setCellValue('AB' . $rowNum, $item->mirrored ?? '');
                $sheet->setCellValue('AC' . $rowNum, $item->fitur_auto_alert ?? '');
                $sheet->setCellValue('AD' . $rowNum, $item->keterangan ?? '');
                $sheet->setCellValue('AE' . $rowNum, $item->verifikasi_by_petugas_ocr ?? '');
                $sheet->setCellValue('AF' . $rowNum, $item->bulan_update ?? '');
                $sheet->setCellValue('AG' . $rowNum, $item->tahun_update ?? '');
                $sheet->setCellValue('AH' . $rowNum, $item->qr_code ?? '');
                $sheet->setCellValue('AI' . $rowNum, $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : '');
                $sheet->setCellValue('AJ' . $rowNum, $item->updated_at ? $item->updated_at->format('Y-m-d H:i:s') : '');
                $rowNum++;
            }
            $numCols = count($headers);
            for ($i = 0; $i < $numCols; $i++) {
                $c = $i < 26 ? chr(65 + $i) : 'A' . chr(65 + $i - 26);
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'data_cctv_' . date('Y-m-d_His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            Log::error('Error exporting CCTV data: ' . $e->getMessage());
            return redirect()->route('cctv-data.index')->with('error', 'Error generating export: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('cctv-data.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'site' => 'nullable|string|max:255',
            'perusahaan' => 'nullable|string|max:255',
            'kategori' => 'nullable|string|max:255',
            'no_cctv' => 'nullable|string|max:255',
            'nama_cctv' => 'nullable|string|max:255',
            'fungsi_cctv' => 'nullable|string|max:255',
            'bentuk_instalasi_cctv' => 'nullable|string|max:255',
            'jenis' => 'nullable|string|max:255',
            'tipe_cctv' => 'nullable|string|max:255',
            'radius_pengawasan' => 'nullable|string|max:255',
            'jenis_spesifikasi_zoom' => 'nullable|string|max:255',
            'lokasi_pemasangan' => 'nullable|string|max:255',
            'control_room' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'kondisi' => 'nullable|string|max:255',
            'longitude' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'coverage_lokasi' => 'nullable|string|max:255',
            'coverage_detail_lokasi' => 'nullable|string|max:255',
            'kategori_area_tercapture' => 'nullable|string|max:255',
            'kategori_aktivitas_tercapture' => 'nullable|string|max:255',
            'link_akses' => 'nullable|string',
            'user_name' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'connected' => 'nullable|string|max:255',
            'mirrored' => 'nullable|string|max:255',
            'fitur_auto_alert' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string',
            'verifikasi_by_petugas_ocr' => 'nullable|string|max:255',
            'bulan_update' => 'nullable|integer|min:1|max:12',
            'tahun_update' => 'nullable|integer|min:2000|max:2100',
        ]);

        $cctvData = CctvData::create($validated);

        return redirect()->route('cctv-data.index')
            ->with('success', 'Data CCTV berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $cctvData = CctvData::findOrFail($id);
        return view('cctv-data.show', compact('cctvData'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $cctvData = CctvData::findOrFail($id);
        return view('cctv-data.edit', compact('cctvData'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $cctvData = CctvData::findOrFail($id);
            
            $validated = $request->validate([
                'site' => 'nullable|string|max:255',
                'perusahaan' => 'nullable|string|max:255',
                'kategori' => 'nullable|string|max:255',
                'no_cctv' => 'nullable|string|max:255',
                'nama_cctv' => 'nullable|string|max:255',
                'fungsi_cctv' => 'nullable|string|max:255',
                'bentuk_instalasi_cctv' => 'nullable|string|max:255',
                'jenis' => 'nullable|string|max:255',
                'tipe_cctv' => 'nullable|string|max:255',
                'radius_pengawasan' => 'nullable|string|max:255',
                'jenis_spesifikasi_zoom' => 'nullable|string|max:255',
                'lokasi_pemasangan' => 'nullable|string|max:255',
                'control_room' => 'nullable|string|max:255',
                'status' => 'nullable|string|max:255',
                'kondisi' => 'nullable|string|max:255',
                'longitude' => 'nullable|numeric',
                'latitude' => 'nullable|numeric',
                'coverage_lokasi' => 'nullable|string|max:255',
                'coverage_detail_lokasi' => 'nullable|string|max:255',
                'kategori_area_tercapture' => 'nullable|string|max:255',
                'kategori_aktivitas_tercapture' => 'nullable|string|max:255',
                'link_akses' => 'nullable|string',
                'user_name' => 'nullable|string|max:255',
                'password' => 'nullable|string|max:255',
                'connected' => 'nullable|string|max:255',
                'mirrored' => 'nullable|string|max:255',
                'fitur_auto_alert' => 'nullable|string|max:255',
                'keterangan' => 'nullable|string',
                'verifikasi_by_petugas_ocr' => 'nullable|string|max:255',
                'bulan_update' => 'nullable|integer|min:1|max:12',
                'tahun_update' => 'nullable|integer|min:2000|max:2100',
            ]);

            $cctvData->update($validated);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data CCTV berhasil diperbarui.'
                ]);
            }

            return redirect()->route('cctv-data.index')
                ->with('success', 'Data CCTV berhasil diperbarui.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $e->errors()
                ], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        } catch (Exception $e) {
            \Log::error('Error updating CCTV data: ' . $e->getMessage());
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat memperbarui data.'
                ], 500);
            }
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data.')->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $cctvData = CctvData::findOrFail($id);
            
            // Delete QR code file if exists (jika masih menggunakan file storage)
            if ($cctvData->qr_code && filter_var($cctvData->qr_code, FILTER_VALIDATE_URL)) {
                $filePath = str_replace('/storage/', '', parse_url($cctvData->qr_code, PHP_URL_PATH));
                $fullPath = storage_path('app/public/' . $filePath);
                if (File::exists($fullPath)) {
                    File::delete($fullPath);
                }
            }

            // Get ID before delete for logging
            $deletedId = $cctvData->id;
            $deletedNoCctv = $cctvData->no_cctv ?? 'N/A';
            
            // Delete data
            $cctvData->delete();
            
            // Verify deletion
            $stillExists = CctvData::where('id', $deletedId)->exists();
            if ($stillExists) {
                \Log::error('Data masih ada setelah delete. ID: ' . $deletedId . ', No. CCTV: ' . $deletedNoCctv);
                throw new Exception('Data gagal dihapus dari database. Silakan coba lagi atau hubungi administrator.');
            }
            
            \Log::info('CCTV data berhasil dihapus. ID: ' . $deletedId . ', No. CCTV: ' . $deletedNoCctv);

            // Check if request is AJAX or expects JSON
            if (request()->ajax() || request()->wantsJson() || request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data CCTV berhasil dihapus.'
                ]);
            }

            return redirect()->route('cctv-data.index')
                ->with('success', 'Data CCTV berhasil dihapus.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('CCTV data not found for deletion: ' . $id);
            if (request()->ajax() || request()->wantsJson() || request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data CCTV tidak ditemukan.'
                ], 404);
            }
            return back()->with('error', 'Data CCTV tidak ditemukan.');
        } catch (Exception $e) {
            \Log::error('Error deleting CCTV data ID ' . $id . ': ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Check if request is AJAX or expects JSON
            if (request()->ajax() || request()->wantsJson() || request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat menghapus data: ' . $e->getMessage()
                ], 500);
            }
            return back()->with('error', 'Terjadi kesalahan saat menghapus data.');
        }
    }

    /**
     * Show the form for importing Excel file.
     */
    public function importForm()
    {
        return view('cctv-data.import');
    }

    /**
     * Import data from Excel file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Baca file Excel/CSV
            if ($extension === 'csv') {
                $reader = IOFactory::createReader('Csv');
                $reader->setInputEncoding('UTF-8');
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $spreadsheet = $reader->load($file->getRealPath());
            } else {
                $spreadsheet = IOFactory::load($file->getRealPath());
            }
            
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (count($rows) < 2) {
                return back()->withErrors(['file' => 'File harus memiliki minimal header dan 1 baris data.']);
            }

            // Ambil header (baris pertama)
            $headers = array_map('trim', $rows[0]);
            
            // Mapping kolom Excel ke field database
            $columnMapping = [
                'site' => ['site'],
                'perusahaan' => ['perusahaan'],
                'kategori' => ['kategori'],
                'no_cctv' => ['no. cctv', 'no cctv', 'no_cctv'],
                'nama_cctv' => ['nama cctv', 'nama_cctv'],
                'fungsi_cctv' => ['fungsi cctv', 'fungsi_cctv'],
                'bentuk_instalasi_cctv' => ['bentuk instalasi cctv', 'bentuk_instalasi_cctv'],
                'jenis' => ['jenis'],
                'tipe_cctv' => ['tipe cctv', 'tipe_cctv'],
                'radius_pengawasan' => ['radius pengawasan', 'radius_pengawasan'],
                'jenis_spesifikasi_zoom' => ['jenis spesifikasi zoom', 'jenis_spesifikasi_zoom'],
                'lokasi_pemasangan' => ['lokasi pemasangan', 'lokasi_pemasangan'],
                'control_room' => ['control room', 'control_room'],
                'status' => ['status'],
                'kondisi' => ['kondisi'],
                'longitude' => ['longitude'],
                'latitude' => ['latitude'],
                'coverage_lokasi' => ['coverage lokasi', 'coverage_lokasi'],
                'coverage_detail_lokasi' => ['coverage detail lokasi', 'coverage_detail_lokasi'],
                'kategori_area_tercapture' => ['kategori area tercapture', 'kategori_area_tercapture'],
                'kategori_aktivitas_tercapture' => ['kategori aktivitas tercapture', 'kategori_aktivitas_tercapture'],
                'link_akses' => ['link akses', 'link_akses'],
                'user_name' => ['user name', 'user_name', 'username'],
                'password' => ['password'],
                'connected' => ['connected'],
                'mirrored' => ['mirrored'],
                'fitur_auto_alert' => ['fitur auto alert', 'fitur_auto_alert'],
                'keterangan' => ['keterangan'],
                'verifikasi_by_petugas_ocr' => ['verifikasi by petugas ocr', 'verifikasi_by_petugas_ocr'],
                'bulan_update' => ['bulan update', 'bulan_update'],
                'tahun_update' => ['tahun update', 'tahun_update'],
            ];

            // Cari index kolom untuk setiap field
            $columnIndexes = [];
            foreach ($columnMapping as $field => $possibleNames) {
                $columnIndexes[$field] = null;
                foreach ($headers as $index => $header) {
                    $headerLower = strtolower(trim($header));
                    foreach ($possibleNames as $possibleName) {
                        if ($headerLower === strtolower($possibleName)) {
                            $columnIndexes[$field] = $index;
                            break 2;
                        }
                    }
                }
            }

            // Proses data (mulai dari baris kedua)
            $successCount = 0;
            $errorCount = 0;
            $updatedCount = 0;
            $errors = [];

            DB::beginTransaction();
            
            try {
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    
                    // Skip baris kosong
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    $data = [];
                    
                    // Map data dari Excel ke array
                    foreach ($columnIndexes as $field => $index) {
                        if ($index !== null && isset($row[$index])) {
                            $value = $row[$index];
                            
                            // Handle null atau empty
                            if ($value === null || $value === '') {
                                $data[$field] = null;
                                continue;
                            }
                            
                            // Convert to string and trim
                            $value = trim((string) $value);
                            
                            // Konversi tipe data
                            if ($field === 'longitude' || $field === 'latitude') {
                                $data[$field] = !empty($value) && is_numeric($value) ? (float) $value : null;
                            } elseif ($field === 'bulan_update' || $field === 'tahun_update') {
                                $data[$field] = !empty($value) && is_numeric($value) ? (int) $value : null;
                            } else {
                                $data[$field] = !empty($value) ? $value : null;
                            }
                        }
                    }

                    // Skip jika tidak ada data penting
                    if (empty($data['no_cctv']) && empty($data['nama_cctv'])) {
                        continue;
                    }

                    // Cek apakah data dengan no_cctv sudah ada di database
                    // Jika sudah ada, update data yang ada dengan nilai baru
                    if (!empty($data['no_cctv'])) {
                        $existingData = CctvData::where('no_cctv', $data['no_cctv'])
                                              ->first();

                        // Update jika data sudah ada
                        if ($existingData) {
                            try {
                                $existingData->update($data);
                                $updatedCount++;
                                continue;
                            } catch (Exception $e) {
                                $errorCount++;
                                $errors[] = "Baris " . ($i + 1) . ": " . $e->getMessage();
                                continue;
                            }
                        }
                    }

                    // Simpan data baru jika no_cctv belum ada
                    try {
                        $newCctvData = CctvData::create($data);
                        $successCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                        $errors[] = "Baris " . ($i + 1) . ": " . $e->getMessage();
                    }
                }

                DB::commit();

                $message = "Import berhasil! {$successCount} data baru berhasil diimpor.";
                if ($updatedCount > 0) {
                    $message .= " {$updatedCount} data berhasil di-update.";
                }
                if ($errorCount > 0) {
                    $message .= " {$errorCount} data gagal diimpor.";
                }

                return redirect()->route('cctv-data.index')
                    ->with('success', $message)
                    ->with('import_errors', $errors);

            } catch (Exception $e) {
                DB::rollBack();
                return back()->withErrors(['file' => 'Error saat menyimpan data: ' . $e->getMessage()]);
            }

        } catch (Exception $e) {
            return back()->withErrors(['file' => 'Error processing file: ' . $e->getMessage()]);
        }
    }

    /**
     * Download template Excel untuk import CCTV Data
     */
    public function downloadTemplate()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set header kolom
            $headers = [
                'Site', 'Perusahaan', 'Kategori', 'No. CCTV', 'Nama CCTV', 'Fungsi CCTV',
                'Bentuk Instalasi CCTV', 'Jenis', 'Tipe CCTV', 'Radius Pengawasan', 'Jenis Spesifikasi Zoom',
                'Lokasi Pemasangan', 'Control Room', 'Status', 'Kondisi',
                'Longitude', 'Latitude', 'Coverage Lokasi', 'Coverage Detail Lokasi',
                'Kategori Area Tercapture', 'Kategori Aktivitas Tercapture',
                'Link Akses', 'User Name', 'Password', 'Connected', 'Mirrored', 'Fitur Auto Alert',
                'Keterangan', 'Verifikasi By Petugas OCR', 'Bulan Update', 'Tahun Update'
            ];

            // Helper function untuk convert angka ke kolom Excel (A, B, ..., Z, AA, AB, ...)
            $getColumnLetter = function($number) {
                $letter = '';
                while ($number > 0) {
                    $mod = ($number - 1) % 26;
                    $letter = chr(65 + $mod) . $letter;
                    $number = intval(($number - $mod) / 26);
                }
                return $letter;
            };

            // Set header di baris pertama
            $colIndex = 1;
            foreach ($headers as $header) {
                $col = $getColumnLetter($colIndex);
                $sheet->setCellValue($col . '1', $header);
                $colIndex++;
            }

            // Style header
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ];
            $lastCol = $getColumnLetter(count($headers));
            $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray($headerStyle);

            // Contoh data
            $examples = [
                [
                    'HO', 'PT Fajar Anugerah Dinamika', 'CCTV', 'LMO-FAD-0001', 'CCTV 01 FAD LMO', 'Monitoring',
                    'Pole Mount', 'Fixed', 'IP Camera', '50', 'Optical Zoom',
                    'Dermaga FAD Prapatan', 'Control Room HO', 'Live View', 'Baik',
                    '116.123456', '-6.123456', 'Dermaga', 'Dermaga FAD Prapatan',
                    'Area Produksi', 'Operasional',
                    'http://cctv.example.com', 'admin', 'password123', 'Yes', 'No', 'Yes',
                    'CCTV untuk monitoring dermaga', 'Sudah Diverifikasi', '12', '2024'
                ],
                [
                    'LMO', 'PT Fajar Anugerah Dinamika', 'CCTV', 'LMO-FAD-0002', 'CCTV 02 FAD LMO', 'Security',
                    'Wall Mount', 'PTZ', 'IP Camera', '100', 'Digital Zoom',
                    'Workshop FAD', 'Control Room LMO', 'Live View', 'Baik',
                    '116.234567', '-6.234567', 'Workshop FAD', 'Base Workshop',
                    'Area Workshop', 'Maintenance',
                    'http://cctv.example.com', 'admin', 'password123', 'Yes', 'No', 'No',
                    'CCTV untuk security workshop', 'Sudah Diverifikasi', '11', '2024'
                ],
            ];

            // Data rows dengan contoh
            $rowNum = 2;
            foreach ($examples as $index => $example) {
                $colIndex = 1;
                foreach ($example as $value) {
                    $col = $getColumnLetter($colIndex);
                    $sheet->setCellValue($col . $rowNum, $value);
                    $colIndex++;
                }
                
                // Set style untuk baris contoh (warna abu-abu terang)
                if ($index < 2) {
                    $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F0F0F0');
                }
                $rowNum++;
            }

            // Baris kosong untuk diisi user
            for ($i = 1; $i <= count($headers); $i++) {
                $col = $getColumnLetter($i);
                $sheet->setCellValue($col . $rowNum, '');
            }
            
            // Set style untuk kolom yang harus diisi (warna kuning)
            $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFFACD');

            // Auto-size columns
            for ($i = 1; $i <= count($headers); $i++) {
                $col = $getColumnLetter($i);
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Add instruction sheet
            $instructionSheet = $spreadsheet->createSheet();
            $instructionSheet->setTitle('Petunjuk');
            $instructionSheet->setCellValue('A1', 'PETUNJUK PENGISIAN');
            $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            
            $instructions = [
                'A3' => '1. Kolom Site: Isi dengan site CCTV (contoh: HO, LMO, BMO 1)',
                'A4' => '2. Kolom Perusahaan: Isi dengan nama perusahaan CCTV',
                'A5' => '3. Kolom Kategori: Isi dengan kategori (contoh: CCTV)',
                'A6' => '4. Kolom No. CCTV: Isi dengan nomor CCTV (contoh: LMO-FAD-0001)',
                'A7' => '5. Kolom Nama CCTV: Isi dengan nama CCTV',
                'A8' => '6. Kolom Fungsi CCTV: Isi dengan fungsi CCTV (contoh: Monitoring, Security)',
                'A9' => '7. Kolom Bentuk Instalasi CCTV: Isi dengan bentuk instalasi (contoh: Pole Mount, Wall Mount)',
                'A10' => '8. Kolom Jenis: Isi dengan jenis CCTV (contoh: Fixed, PTZ)',
                'A11' => '9. Kolom Tipe CCTV: Isi dengan tipe CCTV (contoh: IP Camera)',
                'A12' => '10. Kolom Radius Pengawasan: Isi dengan radius dalam meter (angka)',
                'A13' => '11. Kolom Jenis Spesifikasi Zoom: Isi dengan jenis zoom (contoh: Optical Zoom, Digital Zoom)',
                'A14' => '12. Kolom Lokasi Pemasangan: Isi dengan lokasi pemasangan CCTV',
                'A15' => '13. Kolom Control Room: Isi dengan nama control room',
                'A16' => '14. Kolom Status: Isi dengan status (contoh: Live View, Offline)',
                'A17' => '15. Kolom Kondisi: Isi dengan kondisi (contoh: Baik, Rusak)',
                'A18' => '16. Kolom Longitude: Isi dengan koordinat longitude (angka desimal)',
                'A19' => '17. Kolom Latitude: Isi dengan koordinat latitude (angka desimal)',
                'A20' => '18. Kolom Coverage Lokasi: Isi dengan lokasi coverage',
                'A21' => '19. Kolom Coverage Detail Lokasi: Isi dengan detail lokasi coverage',
                'A22' => '20. Kolom Kategori Area Tercapture: Isi dengan kategori area',
                'A23' => '21. Kolom Kategori Aktivitas Tercapture: Isi dengan kategori aktivitas',
                'A24' => '22. Kolom Link Akses: Isi dengan URL akses CCTV',
                'A25' => '23. Kolom User Name: Isi dengan username untuk akses CCTV',
                'A26' => '24. Kolom Password: Isi dengan password untuk akses CCTV',
                'A27' => '25. Kolom Connected: Isi dengan Yes atau No',
                'A28' => '26. Kolom Mirrored: Isi dengan Yes atau No',
                'A29' => '27. Kolom Fitur Auto Alert: Isi dengan Yes atau No',
                'A30' => '28. Kolom Keterangan: Isi dengan keterangan tambahan (opsional)',
                'A31' => '29. Kolom Verifikasi By Petugas OCR: Isi dengan status verifikasi',
                'A32' => '30. Kolom Bulan Update: Isi dengan bulan update (angka 1-12)',
                'A33' => '31. Kolom Tahun Update: Isi dengan tahun update (angka, contoh: 2024)',
            ];

            foreach ($instructions as $cell => $instruction) {
                $instructionSheet->setCellValue($cell, $instruction);
            }

            $instructionSheet->setCellValue('A35', 'CATATAN PENTING:');
            $instructionSheet->getStyle('A35')->getFont()->setBold(true);
            $instructionSheet->setCellValue('A36', '- Nama kolom tidak case-sensitive (huruf besar/kecil tidak masalah)');
            $instructionSheet->setCellValue('A37', '- Nama kolom dapat menggunakan spasi atau underscore');
            $instructionSheet->setCellValue('A38', '- Data yang sudah ada di database (berdasarkan No. CCTV) akan di-skip');
            $instructionSheet->setCellValue('A39', '- Baris kosong akan diabaikan');
            $instructionSheet->setCellValue('A40', '- Pastikan format data sesuai (angka untuk longitude/latitude, bulan/tahun)');
            
            foreach (range('A', 'E') as $col) {
                $instructionSheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Set active sheet kembali ke sheet pertama
            $spreadsheet->setActiveSheetIndex(0);

            // Download file
            $writer = new Xlsx($spreadsheet);
            $filename = 'template_import_cctv_data_' . date('Y-m-d_His') . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            Log::error('Error downloading template CCTV Data: ' . $e->getMessage());
            return redirect()->route('cctv-data.import-form')
                ->with('error', 'Error generating template: ' . $e->getMessage());
        }
    }

    /**
     * Display WMS Map with CCTV data from database.
     */
    public function mapWms()
    {
        // Ambil data CCTV dari database yang memiliki longitude dan latitude
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
                'description' => $cctv->lokasi_pemasangan ?? $cctv->coverage_detail_lokasi ?? '',
                'type' => $cctv->jenis ?? 'FIXED',
                'brand' => $this->extractBrandFromTipe($cctv->tipe_cctv),
                'model' => $cctv->tipe_cctv ?? '',
                'viewType' => $cctv->fungsi_cctv ?? '',
                'area' => $cctv->coverage_lokasi ?? '',
                'areaType' => $cctv->kategori_area_tercapture ?? '',
                'activity' => $cctv->kategori_aktivitas_tercapture ?? '',
                'controlRoom' => $cctv->control_room ?? '',
                'liveView' => $cctv->status ?? '',
                'link_akses' => $cctv->link_akses ?? '',
                'user_name' => $cctv->user_name ?? '',
                'password' => $cctv->password ?? '',
                'connected' => $cctv->connected ?? '',
                'mirrored' => $cctv->mirrored ?? '',
                'site' => $cctv->site ?? '',
                'perusahaan' => $cctv->perusahaan ?? '',
                'kategori' => $cctv->kategori ?? '',
                'radius_pengawasan' => $cctv->radius_pengawasan ?? '',
                'keterangan' => $cctv->keterangan ?? '',
            ];
        })->toArray();

        return view('HazardMotion.admin.index', compact('cctvLocations'));
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
        } elseif (strpos($tipeLower, 'axis') !== false) {
            return 'Axis';
        }
        
        return '';
    }

    /**
     * Generate QR code image on-the-fly (without saving)
     * Using SVG format which doesn't require imagick extension
     */
    private function generateQrCodeImage($cctvData, $format = 'svg')
    {
        try {
            // Isi QR code adalah nomor CCTV saja
            $qrContent = $cctvData->no_cctv ?? 'CCTV-' . $cctvData->id;
            
            // For SVG format, generate directly to string
            if ($format === 'svg') {
                $qrCode = QrCode::format('svg')
                    ->size(400)
                    ->margin(3)
                    ->errorCorrection('H')
                    ->generate($qrContent);
                
                return $qrCode;
            } else {
                // For other formats, use temporary file approach
                $tempDir = sys_get_temp_dir();
                if (!is_writable($tempDir)) {
                    throw new Exception('Temporary directory is not writable: ' . $tempDir);
                }
                
                $tempFile = $tempDir . DIRECTORY_SEPARATOR . 'qrcode_' . uniqid() . '_' . $cctvData->id . '.' . $format;
                
                QrCode::format($format)
                    ->size(400)
                    ->margin(3)
                    ->errorCorrection('H')
                    ->generate($qrContent, $tempFile);
                
                if (!file_exists($tempFile)) {
                    throw new Exception('Failed to generate QR code file');
                }
                
                $imageData = file_get_contents($tempFile);
                @unlink($tempFile);
                
                return $imageData;
            }
            
        } catch (Exception $e) {
            \Log::error('Error generating QR code for CCTV ID ' . $cctvData->id . ': ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Display CCTV data from QR code scan
     */
    public function scan($id)
    {
        $cctvData = CctvData::findOrFail($id);
        return view('cctv-data.scan', compact('cctvData'));
    }

    /**
     * Serve QR code image (generate on-the-fly)
     */
    public function qrCodeImage($id)
    {
        try {
            $cctvData = CctvData::findOrFail($id);
            
            // Generate QR code on-the-fly as SVG
            $qrCodeImage = $this->generateQrCodeImage($cctvData, 'svg');
            
            return response($qrCodeImage, 200)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Cache-Control', 'public, max-age=3600');
                
        } catch (Exception $e) {
            \Log::error('Error serving QR code image for CCTV ID ' . $id . ': ' . $e->getMessage());
            abort(500, 'Failed to generate QR code');
        }
    }

    /**
     * Download QR code image
     */
    public function downloadQrCode($id)
    {
        try {
            $cctvData = CctvData::findOrFail($id);
            
            // Generate QR code on-the-fly as SVG
            $qrCodeImage = $this->generateQrCodeImage($cctvData, 'svg');
            
            $filename = 'qrcode_' . ($cctvData->nama_cctv ?? $cctvData->no_cctv ?? 'cctv') . '_' . $cctvData->id . '.svg';
            $filename = preg_replace('/[^a-z0-9_\-\.]/i', '_', $filename); // Sanitize filename
            
            return response($qrCodeImage, 200)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                
        } catch (Exception $e) {
            \Log::error('Error downloading QR code for CCTV ID ' . $id . ': ' . $e->getMessage());
            abort(500, 'Failed to generate QR code');
        }
    }

    /**
     * Show the form for importing CCTV Coverage Excel file.
     */
    public function importCoverageForm()
    {
        return view('cctv-data.import-coverage');
    }

    /**
     * Get CCTV Coverage data for DataTable (server-side processing)
     */
    public function getCoverageData(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get('start', 0);
        $length = $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';
        $orderColumn = $request->get('order')[0]['column'] ?? 0;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        // Column mapping (sesuai urutan kolom di DataTable)
        $columns = ['cctv_coverage.id', 'cctv_data_bmo2.no_cctv', 'cctv_coverage.coverage_lokasi', 'cctv_coverage.coverage_detail_lokasi', 'cctv_coverage.kategori_aktivitas', 'cctv_coverage.kategori_area'];
        // Jika kolom pertama (#) yang di-order, gunakan id sebagai gantinya
        if ($orderColumn == 0) {
            $orderColumnName = 'cctv_coverage.id';
        } else {
            $orderColumnName = $columns[$orderColumn] ?? 'cctv_coverage.id';
        }

        // Base query dengan join ke cctv_data_bmo2 untuk mendapatkan no_cctv
        $query = CctvCoverage::select(
                'cctv_coverage.id',
                'cctv_coverage.id_cctv',
                'cctv_data_bmo2.no_cctv',
                'cctv_coverage.coverage_lokasi',
                'cctv_coverage.coverage_detail_lokasi',
                'cctv_coverage.kategori_aktivitas',
                'cctv_coverage.kategori_area'
            )
            ->leftJoin('cctv_data_bmo2', 'cctv_coverage.id_cctv', '=', 'cctv_data_bmo2.id');

        // Search functionality
        if (!empty($searchValue)) {
            $query->where(function($q) use ($searchValue) {
                $q->where('cctv_data_bmo2.no_cctv', 'like', '%' . $searchValue . '%')
                  ->orWhere('cctv_coverage.coverage_lokasi', 'like', '%' . $searchValue . '%')
                  ->orWhere('cctv_coverage.coverage_detail_lokasi', 'like', '%' . $searchValue . '%')
                  ->orWhere('cctv_coverage.kategori_aktivitas', 'like', '%' . $searchValue . '%')
                  ->orWhere('cctv_coverage.kategori_area', 'like', '%' . $searchValue . '%');
            });
        }

        // Get total records
        $recordsTotal = CctvCoverage::count();
        $recordsFiltered = $query->count();

        // Order and paginate
        $data = $query->orderBy($orderColumnName, $orderDir)
                     ->skip($start)
                     ->take($length)
                     ->get();

        // Format data for DataTable
        $formattedData = $data->map(function($item, $index) use ($start) {
            $actions = '<div class="d-flex gap-2 flex-wrap justify-content-center">' .
                '<button type="button" class="btn btn-sm btn-info btn-view-coverage" data-id="' . $item->id . '" title="View"><i class="material-icons-outlined">visibility</i></button>' .
                '<button type="button" class="btn btn-sm btn-warning btn-edit-coverage" data-id="' . $item->id . '" title="Edit"><i class="material-icons-outlined">edit</i></button>' .
                '<button type="button" class="btn btn-sm btn-danger btn-delete-coverage" data-id="' . $item->id . '" data-lokasi="' . htmlspecialchars($item->coverage_lokasi ?? '') . '" data-detail="' . htmlspecialchars($item->coverage_detail_lokasi ?? '') . '" title="Delete"><i class="material-icons-outlined">delete</i></button>' .
                '</div>';
            
            return [
                'DT_RowIndex' => $start + $index + 1,
                'no_cctv' => $item->no_cctv ?? '-',
                'coverage_lokasi' => $item->coverage_lokasi ?? '-',
                'coverage_detail_lokasi' => $item->coverage_detail_lokasi ?? '-',
                'kategori_aktivitas' => $item->kategori_aktivitas ?? '-',
                'kategori_area' => $item->kategori_area ?? '-',
                'actions' => $actions,
                'id' => $item->id,
            ];
        });

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $formattedData
        ]);
    }

    /**
     * Export data CCTV Coverage ke Excel (No. CCTV, Coverage Lokasi, Coverage Detail, Kategori Aktivitas, Kategori Area)
     */
    public function exportCoverage()
    {
        try {
            $data = CctvCoverage::select(
                'cctv_coverage.id',
                'cctv_data_bmo2.no_cctv',
                'cctv_coverage.coverage_lokasi',
                'cctv_coverage.coverage_detail_lokasi',
                'cctv_coverage.kategori_aktivitas',
                'cctv_coverage.kategori_area'
            )
                ->leftJoin('cctv_data_bmo2', 'cctv_coverage.id_cctv', '=', 'cctv_data_bmo2.id')
                ->orderBy('cctv_coverage.id')
                ->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setCellValue('A1', 'No. CCTV');
            $sheet->setCellValue('B1', 'Coverage Lokasi');
            $sheet->setCellValue('C1', 'Coverage Detail Lokasi');
            $sheet->setCellValue('D1', 'Kategori Aktivitas');
            $sheet->setCellValue('E1', 'Kategori Area');

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            ];
            $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

            $rowNum = 2;
            foreach ($data as $item) {
                $sheet->setCellValue('A' . $rowNum, $item->no_cctv ?? '');
                $sheet->setCellValue('B' . $rowNum, $item->coverage_lokasi ?? '');
                $sheet->setCellValue('C' . $rowNum, $item->coverage_detail_lokasi ?? '');
                $sheet->setCellValue('D' . $rowNum, $item->kategori_aktivitas ?? '');
                $sheet->setCellValue('E' . $rowNum, $item->kategori_area ?? '');
                $rowNum++;
            }
            foreach (range('A', 'E') as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'cctv_coverage_' . date('Y-m-d_His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            Log::error('Error exporting CCTV Coverage: ' . $e->getMessage());
            return redirect()->route('cctv-data.import-coverage-form')->with('error', 'Error generating export: ' . $e->getMessage());
        }
    }

    /**
     * Download template Excel untuk import CCTV Coverage
     */
    public function downloadTemplateCoverage()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set title
            $sheet->setCellValue('A1', 'Site');
            $sheet->setCellValue('B1', 'Perusahaan CCTV');
            $sheet->setCellValue('C1', 'Nomer CCTV');
            $sheet->setCellValue('D1', 'Coverage Lokasi');
            $sheet->setCellValue('E1', 'Coverage Detail Lokasi');
            $sheet->setCellValue('F1', 'Kategori Aktivitas');
            $sheet->setCellValue('G1', 'Kategori Area');

            // Style header
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ];
            $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

            // Contoh data
            $examples = [
                ['HO', 'PT Fajar Anugerah Dinamika', 'CCTV 01 FAD LMO', 'Dermaga', 'Dermaga FAD Prapatan', 'Aktivitas Kritis', 'Area Kritis'],
                ['LMO', 'PT Fajar Anugerah Dinamika', 'LMO-FAD-0001', 'Workshop FAD', 'Base Workshop', 'Aktivitas Non Kritis', 'Area Non Highrisk'],
                ['LMO', 'PT Fajar Anugerah Dinamika', 'LMO-FAD-0001', 'Workshop FAD', 'Parkiran Unit', 'Aktivitas Kritis', 'Area Kritis'],
                ['BMO 1', 'PT Fajar Anugerah Dinamika', 'BMO1-MTL-0023', 'Workshop FAD', 'Area Fabrikasi', 'Aktivitas Non Kritis', 'Area Non Highrisk'],
            ];

            // Data rows dengan contoh
            $rowNum = 2;
            foreach ($examples as $index => $example) {
                $sheet->setCellValue('A' . $rowNum, $example[0]);
                $sheet->setCellValue('B' . $rowNum, $example[1]);
                $sheet->setCellValue('C' . $rowNum, $example[2]);
                $sheet->setCellValue('D' . $rowNum, $example[3]);
                $sheet->setCellValue('E' . $rowNum, $example[4]);
                $sheet->setCellValue('F' . $rowNum, $example[5] ?? '');
                $sheet->setCellValue('G' . $rowNum, $example[6] ?? '');
                
                // Set style untuk baris contoh (warna abu-abu terang)
                if ($index < 2) {
                    $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F0F0F0');
                }
                $rowNum++;
            }

            // Baris kosong untuk diisi user
            $sheet->setCellValue('A' . $rowNum, '');
            $sheet->setCellValue('B' . $rowNum, '');
            $sheet->setCellValue('C' . $rowNum, '');
            $sheet->setCellValue('D' . $rowNum, '');
            $sheet->setCellValue('E' . $rowNum, '');
            $sheet->setCellValue('F' . $rowNum, '');
            $sheet->setCellValue('G' . $rowNum, '');
            
            // Set style untuk kolom yang harus diisi (warna kuning)
            $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFFACD');

            // Auto-size columns
            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Add instruction sheet
            $instructionSheet = $spreadsheet->createSheet();
            $instructionSheet->setTitle('Petunjuk');
            $instructionSheet->setCellValue('A1', 'PETUNJUK PENGISIAN');
            $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            
            $instructions = [
                'A3' => '1. Kolom Site: Isi dengan site CCTV (contoh: HO, LMO, BMO 1)',
                'A4' => '2. Kolom Perusahaan CCTV: Isi dengan nama perusahaan CCTV (contoh: PT Fajar Anugerah Dinamika)',
                'A5' => '3. Kolom Nomer CCTV: Isi dengan nomor CCTV yang sudah ada di database',
                'A6' => '   Format nomor CCTV bisa berupa:',
                'A7' => '   - CCTV 01 FAD LMO',
                'A8' => '   - LMO-FAD-0001',
                'A9' => '   - BMO1-MTL-0023',
                'A10' => '4. Kolom Coverage Lokasi: Isi dengan lokasi coverage (contoh: Dermaga, Workshop FAD)',
                'A11' => '5. Kolom Coverage Detail Lokasi: Isi dengan detail lokasi coverage',
                'A13' => 'CATATAN PENTING:',
                'A14' => '- Data CCTV harus sudah ada di database terlebih dahulu',
                'A15' => '- Sistem akan mencocokkan berdasarkan Site, Perusahaan CCTV, dan Nomer CCTV',
                'A16' => '- Sistem mendukung berbagai format nomor CCTV',
                'A17' => '- Data coverage yang sudah ada akan di-skip (tidak duplikat)',
                'A18' => '- Baris contoh (baris 2-3) bisa dihapus atau diubah sesuai kebutuhan',
                'A19' => '- Baris kosong akan diabaikan saat import',
            ];

            foreach ($instructions as $cell => $text) {
                $instructionSheet->setCellValue($cell, $text);
                if (strpos($text, 'CATATAN') !== false) {
                    $instructionSheet->getStyle($cell)->getFont()->setBold(true);
                }
            }

            foreach (range('A', 'E') as $col) {
                $instructionSheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Set active sheet kembali ke sheet pertama
            $spreadsheet->setActiveSheetIndex(0);

            // Download file
            $writer = new Xlsx($spreadsheet);
            $filename = 'template_import_cctv_coverage_' . date('Y-m-d_His') . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            Log::error('Error downloading template CCTV Coverage: ' . $e->getMessage());
            return redirect()->route('cctv-data.import-coverage-form')
                ->with('error', 'Error generating template: ' . $e->getMessage());
        }
    }

    /**
     * Get CCTV Coverage detail by ID
     */
    public function getCoverageDetail($id)
    {
        try {
            $coverage = CctvCoverage::with('cctvData')
                ->leftJoin('cctv_data_bmo2', 'cctv_coverage.id_cctv', '=', 'cctv_data_bmo2.id')
                ->select(
                    'cctv_coverage.*',
                    'cctv_data_bmo2.no_cctv'
                )
                ->where('cctv_coverage.id', $id)
                ->first();

            if (!$coverage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data coverage tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $coverage->id,
                    'no_cctv' => $coverage->no_cctv ?? '-',
                    'coverage_lokasi' => $coverage->coverage_lokasi,
                    'coverage_detail_lokasi' => $coverage->coverage_detail_lokasi,
                    'kategori_aktivitas' => $coverage->kategori_aktivitas,
                    'kategori_area' => $coverage->kategori_area,
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update CCTV Coverage
     */
    public function updateCoverage(Request $request, $id)
    {
        try {
            $request->validate([
                'coverage_lokasi' => 'required|string|max:255',
                'coverage_detail_lokasi' => 'required|string|max:255',
                'kategori_aktivitas' => 'nullable|string|max:255',
                'kategori_area' => 'nullable|string|max:255',
            ]);

            $coverage = CctvCoverage::findOrFail($id);
            
            $coverage->update([
                'coverage_lokasi' => $request->coverage_lokasi,
                'coverage_detail_lokasi' => $request->coverage_detail_lokasi,
                'kategori_aktivitas' => $request->kategori_aktivitas,
                'kategori_area' => $request->kategori_area,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data coverage berhasil diupdate'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete CCTV Coverage
     */
    public function deleteCoverage($id)
    {
        try {
            $coverage = CctvCoverage::findOrFail($id);
            $coverage->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data coverage berhasil dihapus'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import CCTV Coverage data from Excel file.
     */
    public function importCoverage(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Baca file Excel/CSV
            if ($extension === 'csv') {
                $reader = IOFactory::createReader('Csv');
                $reader->setInputEncoding('UTF-8');
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $spreadsheet = $reader->load($file->getRealPath());
            } else {
                $spreadsheet = IOFactory::load($file->getRealPath());
            }
            
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (count($rows) < 2) {
                return back()->withErrors(['file' => 'File harus memiliki minimal header dan 1 baris data.']);
            }

            // Ambil header (baris pertama)
            $headers = array_map('trim', $rows[0]);
            
            // Mapping kolom Excel ke field (dengan variasi nama yang lebih banyak dan handle typo)
            $columnMapping = [
                'site' => ['site'],
                'perusahaan' => ['perusahaan cctv', 'perusahaan'],
                'no_cctv' => ['nomer cctv', 'nomer cctv', 'no cctv', 'no_cctv'],
                'coverage_lokasi' => [
                    'coverage lokasi', 'coverage_lokasi', 
                    'coverage_loksai', 'coverage loksai', // handle typo: loksai
                    'lokasi coverage', 'lokasi_coverage'
                ],
                'coverage_detail_lokasi' => [
                    'coverage detail lokasi', 'coverage_detail_lokasi', 
                    'detail lokasi coverage', 'detail_lokasi_coverage', 
                    'detail lokasi', 'coverage detail'
                ],
                'kategori_aktivitas' => [
                    'kategori aktivitas', 'kategori_aktivitas', 
                    'kategori aktivitasa', 'kategori_aktivitasa', // handle typo: aktivitasa
                    'kategori aktivitas', 'aktivitas kategori'
                ],
                'kategori_area' => [
                    'kategori area', 'kategori_area',
                    'aktivitas area', 'aktivitas_area', // handle variasi: aktivitas area
                    'area kategori', 'area'
                ],
            ];

            // Cari index kolom untuk setiap field
            $columnIndexes = [];
            foreach ($columnMapping as $field => $possibleNames) {
                $columnIndexes[$field] = null;
                foreach ($headers as $index => $header) {
                    $headerLower = strtolower(trim($header));
                    foreach ($possibleNames as $possibleName) {
                        if ($headerLower === strtolower($possibleName)) {
                            $columnIndexes[$field] = $index;
                            break 2;
                        }
                    }
                }
            }

            // Deteksi mode: UPDATE atau CREATE
            // Mode UPDATE: hanya perlu coverage_lokasi, coverage_detail_lokasi, kategori_aktivitas, kategori_area
            // Mode CREATE: perlu site, perusahaan, no_cctv, coverage_lokasi, coverage_detail_lokasi, kategori_aktivitas, kategori_area
            $isUpdateMode = ($columnIndexes['coverage_lokasi'] !== null && 
                           $columnIndexes['coverage_detail_lokasi'] !== null &&
                           $columnIndexes['kategori_aktivitas'] !== null &&
                           $columnIndexes['kategori_area'] !== null &&
                           ($columnIndexes['site'] === null || $columnIndexes['perusahaan'] === null || $columnIndexes['no_cctv'] === null));
            
            $isCreateMode = ($columnIndexes['site'] !== null && 
                           $columnIndexes['perusahaan'] !== null && 
                           $columnIndexes['no_cctv'] !== null &&
                           $columnIndexes['coverage_lokasi'] !== null && 
                           $columnIndexes['coverage_detail_lokasi'] !== null);

            // Validasi kolom wajib berdasarkan mode
            if ($isUpdateMode) {
                // Mode UPDATE: hanya perlu 4 kolom
                $missingColumns = [];
                if ($columnIndexes['coverage_lokasi'] === null) $missingColumns[] = 'Coverage Lokasi';
                if ($columnIndexes['coverage_detail_lokasi'] === null) $missingColumns[] = 'Coverage Detail Lokasi';
                if ($columnIndexes['kategori_aktivitas'] === null) $missingColumns[] = 'Kategori Aktivitas';
                if ($columnIndexes['kategori_area'] === null) $missingColumns[] = 'Kategori Area';
                
                if (!empty($missingColumns)) {
                    $foundColumns = [];
                    foreach ($headers as $header) {
                        if (!empty(trim($header))) {
                            $foundColumns[] = trim($header);
                        }
                    }
                    return back()->withErrors([
                        'file' => 'Mode UPDATE: Kolom yang diperlukan tidak ditemukan. Kolom yang ditemukan: ' . implode(', ', $foundColumns) . 
                        '. Kolom yang kurang: ' . implode(', ', $missingColumns) . 
                        '. Pastikan nama kolom sesuai: Coverage Lokasi, Coverage Detail Lokasi, Kategori Aktivitas, Kategori Area.'
                    ]);
                }
            } elseif ($isCreateMode) {
                // Mode CREATE: perlu semua kolom
                $missingColumns = [];
                if ($columnIndexes['site'] === null) $missingColumns[] = 'Site';
                if ($columnIndexes['perusahaan'] === null) $missingColumns[] = 'Perusahaan CCTV';
                if ($columnIndexes['no_cctv'] === null) $missingColumns[] = 'Nomer CCTV';
                if ($columnIndexes['coverage_lokasi'] === null) $missingColumns[] = 'Coverage Lokasi';
                if ($columnIndexes['coverage_detail_lokasi'] === null) $missingColumns[] = 'Coverage Detail Lokasi';
                
                if (!empty($missingColumns)) {
                    $foundColumns = [];
                    foreach ($headers as $header) {
                        if (!empty(trim($header))) {
                            $foundColumns[] = trim($header);
                        }
                    }
                    return back()->withErrors([
                        'file' => 'Mode CREATE: Kolom yang diperlukan tidak ditemukan. Kolom yang ditemukan: ' . implode(', ', $foundColumns) . 
                        '. Kolom yang kurang: ' . implode(', ', $missingColumns)
                    ]);
                }
            } else {
                // Tidak jelas mode-nya - berikan informasi kolom yang ditemukan
                $foundColumns = [];
                foreach ($headers as $header) {
                    if (!empty(trim($header))) {
                        $foundColumns[] = trim($header);
                    }
                }
                
                $foundColumnsStr = !empty($foundColumns) ? implode(', ', $foundColumns) : 'Tidak ada kolom yang dikenali';
                
                return back()->withErrors([
                    'file' => 'File tidak valid. Kolom yang ditemukan: ' . $foundColumnsStr . 
                    '. Untuk UPDATE: perlu Coverage Lokasi, Coverage Detail Lokasi, Kategori Aktivitas, Kategori Area. ' .
                    'Untuk CREATE: perlu Site, Perusahaan CCTV, Nomer CCTV, Coverage Lokasi, Coverage Detail Lokasi.'
                ]);
            }

            // Proses data (mulai dari baris kedua)
            $successCount = 0;
            $errorCount = 0;
            $updateCount = 0;
            $createCount = 0;
            $errors = [];

            DB::beginTransaction();
            
            try {
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    
                    // Skip baris kosong
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    // Ambil data dari Excel
                    $site = isset($row[$columnIndexes['site']]) ? trim((string) $row[$columnIndexes['site']]) : null;
                    $perusahaan = isset($row[$columnIndexes['perusahaan']]) ? trim((string) $row[$columnIndexes['perusahaan']]) : null;
                    $noCctvExcel = isset($row[$columnIndexes['no_cctv']]) ? trim((string) $row[$columnIndexes['no_cctv']]) : null;
                    $coverageLokasi = isset($row[$columnIndexes['coverage_lokasi']]) ? trim((string) $row[$columnIndexes['coverage_lokasi']]) : null;
                    $coverageDetailLokasi = isset($row[$columnIndexes['coverage_detail_lokasi']]) ? trim((string) $row[$columnIndexes['coverage_detail_lokasi']]) : null;
                    $kategoriAktivitas = isset($columnIndexes['kategori_aktivitas']) && $columnIndexes['kategori_aktivitas'] !== null && isset($row[$columnIndexes['kategori_aktivitas']]) ? trim((string) $row[$columnIndexes['kategori_aktivitas']]) : null;
                    $kategoriArea = isset($columnIndexes['kategori_area']) && $columnIndexes['kategori_area'] !== null && isset($row[$columnIndexes['kategori_area']]) ? trim((string) $row[$columnIndexes['kategori_area']]) : null;

                    if ($isUpdateMode) {
                        // MODE UPDATE: hanya update berdasarkan coverage_lokasi dan coverage_detail_lokasi
                        // Validasi data wajib untuk update
                        if (empty($coverageLokasi) || empty($coverageDetailLokasi)) {
                            $errorCount++;
                            $errors[] = "Baris " . ($i + 1) . ": Coverage Lokasi dan Coverage Detail Lokasi harus diisi.";
                            continue;
                        }

                        // Cari data yang cocok berdasarkan coverage_lokasi dan coverage_detail_lokasi
                        $existingCoverage = CctvCoverage::where('coverage_lokasi', $coverageLokasi)
                            ->where('coverage_detail_lokasi', $coverageDetailLokasi)
                            ->first();

                        if ($existingCoverage) {
                            // Update kategori_aktivitas dan kategori_area
                            try {
                                $existingCoverage->update([
                                    'kategori_aktivitas' => $kategoriAktivitas,
                                    'kategori_area' => $kategoriArea,
                                ]);
                                $successCount++;
                                $updateCount++;
                            } catch (Exception $e) {
                                $errorCount++;
                                $errors[] = "Baris " . ($i + 1) . ": Gagal update data - " . $e->getMessage();
                            }
                        } else {
                            $errorCount++;
                            $errors[] = "Baris " . ($i + 1) . ": Data tidak ditemukan dengan Coverage Lokasi: '{$coverageLokasi}' dan Coverage Detail Lokasi: '{$coverageDetailLokasi}'. Pastikan data sudah ada di database.";
                        }
                    } else {
                        // MODE CREATE: buat data baru
                        // Validasi data wajib untuk create
                        if (empty($site) || empty($perusahaan) || empty($noCctvExcel)) {
                            $errorCount++;
                            $errors[] = "Baris " . ($i + 1) . ": Site, Perusahaan CCTV, dan Nomer CCTV harus diisi.";
                            continue;
                        }

                        if (empty($coverageLokasi) || empty($coverageDetailLokasi)) {
                            $errorCount++;
                            $errors[] = "Baris " . ($i + 1) . ": Coverage Lokasi dan Coverage Detail Lokasi harus diisi.";
                            continue;
                        }

                        // Cari CCTV dengan multiple matching strategies
                        $cctvData = $this->findCctvByFlexibleMatching($site, $perusahaan, $noCctvExcel);

                        if (!$cctvData) {
                            $errorCount++;
                            $errors[] = "Baris " . ($i + 1) . ": CCTV tidak ditemukan dengan Site: {$site}, Perusahaan: {$perusahaan}, Nomer CCTV: {$noCctvExcel}";
                            continue;
                        }

                        // Cek apakah sudah ada (untuk avoid duplicate)
                        $existingCoverage = CctvCoverage::where('id_cctv', $cctvData->id)
                            ->where('coverage_lokasi', $coverageLokasi)
                            ->where('coverage_detail_lokasi', $coverageDetailLokasi)
                            ->first();

                        if ($existingCoverage) {
                            // Update jika sudah ada
                            try {
                                $existingCoverage->update([
                                    'kategori_aktivitas' => $kategoriAktivitas,
                                    'kategori_area' => $kategoriArea,
                                ]);
                                $successCount++;
                                $updateCount++;
                            } catch (Exception $e) {
                                $errorCount++;
                                $errors[] = "Baris " . ($i + 1) . ": Gagal update data - " . $e->getMessage();
                            }
                        } else {
                            // Create baru
                            try {
                                CctvCoverage::create([
                                    'id_cctv' => $cctvData->id,
                                    'coverage_lokasi' => $coverageLokasi,
                                    'coverage_detail_lokasi' => $coverageDetailLokasi,
                                    'kategori_aktivitas' => $kategoriAktivitas,
                                    'kategori_area' => $kategoriArea,
                                ]);
                                $successCount++;
                                $createCount++;
                            } catch (Exception $e) {
                                $errorCount++;
                                $errors[] = "Baris " . ($i + 1) . ": " . $e->getMessage();
                            }
                        }
                    }
                }

                DB::commit();

                $modeText = $isUpdateMode ? "Mode UPDATE" : "Mode CREATE";
                $message = "Import berhasil ({$modeText})! {$successCount} data coverage berhasil diproses.";
                if ($updateCount > 0 && $createCount > 0) {
                    $message .= " ({$updateCount} data diupdate, {$createCount} data baru).";
                } elseif ($updateCount > 0) {
                    $message .= " ({$updateCount} data diupdate).";
                } elseif ($createCount > 0) {
                    $message .= " ({$createCount} data baru).";
                }
                if ($errorCount > 0) {
                    $message .= " {$errorCount} data gagal diimpor.";
                }

                return redirect()->route('cctv-data.import-coverage-form')
                    ->with('success', $message)
                    ->with('import_errors', $errors);

            } catch (Exception $e) {
                DB::rollBack();
                return back()->withErrors(['file' => 'Error saat menyimpan data: ' . $e->getMessage()]);
            }

        } catch (Exception $e) {
            return back()->withErrors(['file' => 'Error processing file: ' . $e->getMessage()]);
        }
    }

    /**
     * Find CCTV dengan flexible matching untuk handle berbagai format nomor CCTV
     */
    private function findCctvByFlexibleMatching($site, $perusahaan, $noCctvExcel)
    {
        // Strategy 1: Exact match dengan no_cctv
        $cctv = CctvData::where('site', $site)
            ->where('perusahaan', $perusahaan)
            ->where('no_cctv', $noCctvExcel)
            ->first();

        if ($cctv) {
            return $cctv;
        }

        // Strategy 2: Extract nomor dari format Excel dan match dengan format database
        // Contoh: "CCTV 01 FAD LMO" -> extract "01"
        // Contoh: "LMO-FAD-0001" -> extract "0001" atau "1"
        $extractedNumber = $this->extractNumberFromCctvName($noCctvExcel);
        
        if ($extractedNumber !== null) {
            // Cari dengan nomor yang sudah dinormalisasi (tanpa leading zeros)
            $normalizedNumber = ltrim($extractedNumber, '0');
            if (empty($normalizedNumber)) {
                $normalizedNumber = '0';
            }

            // Cari dengan berbagai format
            $patterns = [
                $normalizedNumber,           // "1"
                str_pad($normalizedNumber, 2, '0', STR_PAD_LEFT),  // "01"
                str_pad($normalizedNumber, 3, '0', STR_PAD_LEFT),  // "001"
                str_pad($normalizedNumber, 4, '0', STR_PAD_LEFT),  // "0001"
            ];

            foreach ($patterns as $pattern) {
                // Format: LMO-FAD-0001, LMO-FAD-001, dll
                $cctv = CctvData::where('site', $site)
                    ->where('perusahaan', $perusahaan)
                    ->where(function($q) use ($pattern) {
                        $q->where('no_cctv', 'like', '%-' . $pattern)
                          ->orWhere('no_cctv', 'like', '%' . $pattern)
                          ->orWhereRaw('SUBSTRING_INDEX(no_cctv, "-", -1) = ?', [$pattern])
                          ->orWhereRaw('SUBSTRING_INDEX(no_cctv, "-", -1) = ?', [str_pad($pattern, 4, '0', STR_PAD_LEFT)]);
                    })
                    ->first();

                if ($cctv) {
                    return $cctv;
                }
            }

            // Cari dengan format: SITE-COMPANY-NUMBER
            $sitePrefix = strtoupper($site);
            $companyPrefix = $this->extractCompanyPrefix($perusahaan);
            
            if ($companyPrefix) {
                foreach ($patterns as $pattern) {
                    $formattedNo = $sitePrefix . '-' . $companyPrefix . '-' . str_pad($pattern, 4, '0', STR_PAD_LEFT);
                    $cctv = CctvData::where('site', $site)
                        ->where('perusahaan', $perusahaan)
                        ->where('no_cctv', $formattedNo)
                        ->first();

                    if ($cctv) {
                        return $cctv;
                    }
                }
            }
        }

        // Strategy 3: Fuzzy match dengan nama_cctv
        // Contoh: "CCTV 01 FAD LMO" mungkin ada di nama_cctv
        $normalizedExcel = strtolower(preg_replace('/[\s\-_]/', '', $noCctvExcel));
        
        $cctv = CctvData::where('site', $site)
            ->where('perusahaan', $perusahaan)
            ->where(function($q) use ($noCctvExcel, $normalizedExcel) {
                $q->where('nama_cctv', 'like', '%' . $noCctvExcel . '%')
                  ->orWhereRaw('LOWER(REPLACE(REPLACE(REPLACE(nama_cctv, \' \', \'\'), \'-\', \'\'), \'_\', \'\')) LIKE ?', ['%' . $normalizedExcel . '%'])
                  ->orWhereRaw('LOWER(REPLACE(REPLACE(REPLACE(no_cctv, \' \', \'\'), \'-\', \'\'), \'_\', \'\')) LIKE ?', ['%' . $normalizedExcel . '%']);
            })
            ->first();

        if ($cctv) {
            return $cctv;
        }

        // Strategy 4: Match dengan nomor saja (jika ada di akhir no_cctv atau nama_cctv)
        if ($extractedNumber !== null) {
            $normalizedNumber = ltrim($extractedNumber, '0');
            if (empty($normalizedNumber)) {
                $normalizedNumber = '0';
            }

            $cctv = CctvData::where('site', $site)
                ->where('perusahaan', $perusahaan)
                ->where(function($q) use ($normalizedNumber, $extractedNumber) {
                    // Cek di akhir no_cctv
                    $q->where('no_cctv', 'like', '%' . $normalizedNumber)
                      ->orWhere('no_cctv', 'like', '%' . $extractedNumber)
                      ->orWhere('no_cctv', 'like', '%' . str_pad($normalizedNumber, 4, '0', STR_PAD_LEFT))
                      // Cek di nama_cctv
                      ->orWhere('nama_cctv', 'like', '%' . $normalizedNumber)
                      ->orWhere('nama_cctv', 'like', '%' . $extractedNumber);
                })
                ->first();

            if ($cctv) {
                return $cctv;
            }
        }

        return null;
    }

    /**
     * Extract nomor dari nama CCTV
     * Contoh: "CCTV 01 FAD LMO" -> "01"
     * Contoh: "2 (Dermaga PMO-BMO)" -> "2"
     * Contoh: "LMO-FAD-0001" -> "0001"
     */
    private function extractNumberFromCctvName($name)
    {
        if (empty($name)) {
            return null;
        }

        // Pattern 1: "CCTV 01 FAD LMO" -> extract "01"
        if (preg_match('/cctv\s+(\d+)/i', $name, $matches)) {
            return $matches[1];
        }

        // Pattern 2: "2 (Dermaga PMO-BMO)" -> extract "2"
        if (preg_match('/^(\d+)\s*\(/i', $name, $matches)) {
            return $matches[1];
        }

        // Pattern 3: "LMO-FAD-0001" -> extract "0001" (nomor di akhir setelah dash terakhir)
        if (preg_match('/-(\d+)$/', $name, $matches)) {
            return $matches[1];
        }

        // Pattern 4: Extract nomor pertama yang ditemukan
        if (preg_match('/(\d+)/', $name, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract company prefix dari nama perusahaan
     * Contoh: "PT Fajar Anugerah Dinamika" -> "FAD"
     */
    private function extractCompanyPrefix($perusahaan)
    {
        if (empty($perusahaan)) {
            return null;
        }

        // Ambil inisial dari kata-kata (skip PT, CV, dll)
        $words = preg_split('/\s+/', $perusahaan);
        $initials = '';
        
        foreach ($words as $word) {
            $word = strtoupper(trim($word));
            // Skip kata umum
            if (in_array($word, ['PT', 'CV', 'UD', 'TOKO', 'PERUSAHAAN', 'COMPANY'])) {
                continue;
            }
            // Ambil huruf pertama
            if (!empty($word)) {
                $initials .= substr($word, 0, 1);
            }
        }

        // Jika terlalu panjang, ambil 3 karakter pertama
        if (strlen($initials) > 3) {
            $initials = substr($initials, 0, 3);
        }

        return !empty($initials) ? $initials : null;
    }

    /**
     * Show the form for importing PJA-CCTV mapping Excel file.
     */
    public function importPjaCctvForm()
    {
        return view('cctv-data.import-pja-cctv');
    }

    /**
     * Import PJA-CCTV mapping data from Excel file.
     * Menggunakan queue job untuk proses background yang lebih cepat.
     */
    public function importPjaCctv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');
            
            if (!$file || !$file->isValid()) {
                return redirect()
                    ->route('cctv-data.import-pja-cctv-form')
                    ->with('error', 'File tidak valid atau gagal diunggah.');
            }

            $uniqueName = uniqid('pja_cctv_', true) . '.' . $file->getClientOriginalExtension();
            $storedPath = $file->storeAs('pja-cctv-imports', $uniqueName);

            if (!$storedPath) {
                return redirect()
                    ->route('cctv-data.import-pja-cctv-form')
                    ->with('error', 'Gagal menyimpan file. Pastikan folder storage/app/pja-cctv-imports dapat ditulis.');
            }

            // Check queue connection
            $queueConnection = config('queue.default');
            $isSync = $queueConnection === 'sync';
            
            // Check if jobs table exists for database queue
            if ($queueConnection === 'database') {
                try {
                    if (!Schema::hasTable('jobs')) {
                        return redirect()
                            ->route('cctv-data.import-pja-cctv-form')
                            ->with('error', 'Tabel jobs belum ada. Silakan jalankan: php artisan migrate');
                    }
                } catch (\Exception $e) {
                    // If we can't check, try to dispatch anyway
                }
            }
            
            // Dispatch job to queue
            ImportPjaCctvJob::dispatch($storedPath)->onQueue('default');

            $message = 'File berhasil diunggah dan sedang diproses di background. Silakan cek beberapa saat lagi.';
            if ($isSync) {
                $message .= ' Catatan: Queue connection menggunakan "sync", pastikan untuk menjalankan queue worker jika ingin memproses di background.';
            }

            return redirect()
                ->route('cctv-data.import-pja-cctv-form')
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('ImportPjaCctv error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()
                ->route('cctv-data.import-pja-cctv-form')
                ->with('error', 'Terjadi kesalahan saat mengunggah file: ' . $e->getMessage());
        }
    }

    /**
     * Find PJA dari ClickHouse dengan fuzzy matching untuk handle typo
     */
    private function findPjaFromClickHouse($pjaName)
    {
        try {
            $clickhouse = new ClickHouseService();
            
            if (!$clickhouse->isConnected()) {
                Log::warning('ClickHouse is not connected. Cannot find PJA.');
                return null;
            }

            // Strategy 1: Exact match
            $escapedPjaName = addslashes($pjaName);
            $sql = "
                SELECT toString(pja_id) as pja_id, toString(nama_pja) as nama_pja
                FROM nitip.pja_full_hierarchical_view_fix
                WHERE toString(nama_pja) = '{$escapedPjaName}'
                LIMIT 1
            ";
            
            try {
                $results = $clickhouse->query($sql);
                if (!empty($results) && isset($results[0]['pja_id'])) {
                    return $results[0]['pja_id'];
                }
            } catch (Exception $e) {
                Log::debug('Exact match failed: ' . $e->getMessage());
            }

            // Strategy 2: Case-insensitive match
            $escapedPjaNameLower = addslashes(strtolower($pjaName));
            $sql = "
                SELECT toString(pja_id) as pja_id, toString(nama_pja) as nama_pja
                FROM nitip.pja_full_hierarchical_view_fix
                WHERE lowerUTF8(toString(nama_pja)) = lowerUTF8('{$escapedPjaName}')
                LIMIT 1
            ";
            
            try {
                $results = $clickhouse->query($sql);
                if (!empty($results) && isset($results[0]['pja_id'])) {
                    return $results[0]['pja_id'];
                }
            } catch (Exception $e) {
                Log::debug('Case-insensitive match failed: ' . $e->getMessage());
            }

            // Strategy 3: Fuzzy match dengan LIKE
            $searchTerm = '%' . str_replace(' ', '%', addslashes($pjaName)) . '%';
            $sql = "
                SELECT toString(pja_id) as pja_id, toString(nama_pja) as nama_pja
                FROM nitip.pja_full_hierarchical_view_fix
                WHERE toString(nama_pja) LIKE '{$searchTerm}'
                LIMIT 10
            ";
            
            try {
                $results = $clickhouse->query($sql);
                
                if (!empty($results)) {
                    // Cari yang paling mirip menggunakan similarity
                    $bestMatch = $this->findBestPjaMatch($pjaName, $results);
                    if ($bestMatch) {
                        return $bestMatch['pja_id'];
                    }
                }
            } catch (Exception $e) {
                Log::debug('Fuzzy match failed: ' . $e->getMessage());
            }

            // Strategy 4: Normalize dan match (handle typo seperti "Fasility" vs "Facility")
            $normalizedPjaName = $this->normalizePjaName($pjaName);
            
            $sql = "
                SELECT toString(pja_id) as pja_id, toString(nama_pja) as nama_pja
                FROM nitip.pja_full_hierarchical_view_fix
                LIMIT 1000
            ";
            
            try {
                $allPjas = $clickhouse->query($sql);
                
                foreach ($allPjas as $pja) {
                    $normalizedDbName = $this->normalizePjaName($pja['nama_pja'] ?? '');
                    if ($normalizedPjaName === $normalizedDbName) {
                        return $pja['pja_id'] ?? null;
                    }
                }
            } catch (Exception $e) {
                Log::debug('Normalized match failed: ' . $e->getMessage());
            }

            return null;

        } catch (Exception $e) {
            Log::error('Error finding PJA from ClickHouse: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize PJA name untuk handle typo
     * Contoh: "PJA Fasility & Infrastrukture BC BMO 1" -> "pja facility infrastructure bc bmo 1"
     */
    private function normalizePjaName($name)
    {
        if (empty($name)) {
            return '';
        }

        // Convert to lowercase
        $normalized = strtolower(trim($name));
        
        // Remove common typos/misspellings
        $replacements = [
            'fasility' => 'facility',
            'infrastrukture' => 'infrastructure',
            'infrastruktur' => 'infrastructure',
            'infrastruktur' => 'infrastructure',
            'infrastruktur' => 'infrastructure',
        ];
        
        foreach ($replacements as $wrong => $correct) {
            $normalized = str_replace($wrong, $correct, $normalized);
        }
        
        // Remove special characters and extra spaces
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        return trim($normalized);
    }

    /**
     * Find best PJA match dari hasil query menggunakan similarity
     */
    private function findBestPjaMatch($searchName, $results)
    {
        if (empty($results)) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;

        $normalizedSearch = $this->normalizePjaName($searchName);

        foreach ($results as $pja) {
            $pjaName = $pja['nama_pja'] ?? '';
            $normalizedPja = $this->normalizePjaName($pjaName);
            
            // Calculate similarity
            $similarity = $this->calculateSimilarity($normalizedSearch, $normalizedPja);
            
            if ($similarity > $bestScore) {
                $bestScore = $similarity;
                $bestMatch = $pja;
            }
        }

        // Return jika similarity >= 80%
        if ($bestScore >= 0.8) {
            return $bestMatch;
        }

        return null;
    }

    /**
     * Calculate similarity between two strings (simple Levenshtein-based)
     */
    private function calculateSimilarity($str1, $str2)
    {
        if (empty($str1) || empty($str2)) {
            return 0;
        }

        // Exact match
        if ($str1 === $str2) {
            return 1.0;
        }

        // Calculate Levenshtein distance
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) {
            return 1.0;
        }

        $distance = levenshtein($str1, $str2);
        $similarity = 1 - ($distance / $maxLen);

        return $similarity;
    }

    /**
     * Find CCTV by name dengan flexible matching
     * Menggunakan logic yang mirip dengan findCctvByFlexibleMatching tapi tanpa site/perusahaan
     */
    private function findCctvByNameFlexible($cctvName)
    {
        // Strategy 1: Exact match dengan no_cctv atau nama_cctv
        $cctv = CctvData::where('no_cctv', $cctvName)
            ->orWhere('nama_cctv', $cctvName)
            ->first();

        if ($cctv) {
            return $cctv;
        }

        // Strategy 2: Extract nomor dari format Excel dan match dengan format database
        // Contoh: "CCTV 1 MTL" -> extract "1"
        // Contoh: "BMO1-MTL-0001" -> extract "0001" atau "1"
        $extractedNumber = $this->extractNumberFromCctvName($cctvName);
        
        if ($extractedNumber !== null) {
            // Cari dengan nomor yang sudah dinormalisasi (tanpa leading zeros)
            $normalizedNumber = ltrim($extractedNumber, '0');
            if (empty($normalizedNumber)) {
                $normalizedNumber = '0';
            }

            // Cari dengan berbagai format
            $patterns = [
                $normalizedNumber,           // "1"
                str_pad($normalizedNumber, 2, '0', STR_PAD_LEFT),  // "01"
                str_pad($normalizedNumber, 3, '0', STR_PAD_LEFT),  // "001"
                str_pad($normalizedNumber, 4, '0', STR_PAD_LEFT),  // "0001"
            ];

            foreach ($patterns as $pattern) {
                // Format: BMO1-MTL-0001, BMO-MTL-001, dll
                $cctv = CctvData::where(function($q) use ($pattern) {
                    $q->where('no_cctv', 'like', '%-' . $pattern)
                      ->orWhere('no_cctv', 'like', '%' . $pattern)
                      ->orWhereRaw('SUBSTRING_INDEX(no_cctv, "-", -1) = ?', [$pattern])
                      ->orWhereRaw('SUBSTRING_INDEX(no_cctv, "-", -1) = ?', [str_pad($pattern, 4, '0', STR_PAD_LEFT)]);
                })
                ->first();

                if ($cctv) {
                    return $cctv;
                }
            }

            // Cari dengan format: SITE-COMPANY-NUMBER (extract site dan company dari nama)
            $siteCompany = $this->extractSiteCompanyFromCctvName($cctvName);
            if ($siteCompany) {
                foreach ($patterns as $pattern) {
                    $formattedNo = $siteCompany['site'] . '-' . $siteCompany['company'] . '-' . str_pad($pattern, 4, '0', STR_PAD_LEFT);
                    $cctv = CctvData::where('no_cctv', $formattedNo)->first();

                    if ($cctv) {
                        return $cctv;
                    }
                }
            }
        }

        // Strategy 3: Fuzzy match dengan nama_cctv
        $normalizedExcel = strtolower(preg_replace('/[\s\-_]/', '', $cctvName));
        
        $cctv = CctvData::where(function($q) use ($cctvName, $normalizedExcel) {
            $q->where('nama_cctv', 'like', '%' . $cctvName . '%')
              ->orWhereRaw('LOWER(REPLACE(REPLACE(REPLACE(nama_cctv, \' \', \'\'), \'-\', \'\'), \'_\', \'\')) LIKE ?', ['%' . $normalizedExcel . '%'])
              ->orWhereRaw('LOWER(REPLACE(REPLACE(REPLACE(no_cctv, \' \', \'\'), \'-\', \'\'), \'_\', \'\')) LIKE ?', ['%' . $normalizedExcel . '%']);
        })
        ->first();

        if ($cctv) {
            return $cctv;
        }

        // Strategy 4: Match dengan nomor saja (jika ada di akhir no_cctv atau nama_cctv)
        if ($extractedNumber !== null) {
            $normalizedNumber = ltrim($extractedNumber, '0');
            if (empty($normalizedNumber)) {
                $normalizedNumber = '0';
            }

            $cctv = CctvData::where(function($q) use ($normalizedNumber, $extractedNumber) {
                // Cek di akhir no_cctv
                $q->where('no_cctv', 'like', '%' . $normalizedNumber)
                  ->orWhere('no_cctv', 'like', '%' . $extractedNumber)
                  ->orWhere('no_cctv', 'like', '%' . str_pad($normalizedNumber, 4, '0', STR_PAD_LEFT))
                  // Cek di nama_cctv
                  ->orWhere('nama_cctv', 'like', '%' . $normalizedNumber)
                  ->orWhere('nama_cctv', 'like', '%' . $extractedNumber);
            })
            ->first();

            if ($cctv) {
                return $cctv;
            }
        }

        return null;
    }

    /**
     * Extract site dan company dari nama CCTV
     * Contoh: "CCTV 1 MTL" -> site: "BMO1" atau "LMO", company: "MTL"
     */
    private function extractSiteCompanyFromCctvName($name)
    {
        if (empty($name)) {
            return null;
        }

        // Pattern: "CCTV 1 MTL" -> extract "MTL"
        if (preg_match('/cctv\s+\d+\s+([a-z]+)/i', $name, $matches)) {
            $company = strtoupper($matches[1]);
            
            // Coba cari site dari database berdasarkan company
            $cctvSample = CctvData::where('no_cctv', 'like', '%-' . $company . '-%')
                ->orWhere('no_cctv', 'like', $company . '-%')
                ->first();
            
            if ($cctvSample) {
                // Extract site dari no_cctv
                $parts = explode('-', $cctvSample->no_cctv);
                if (count($parts) >= 2) {
                    return [
                        'site' => $parts[0],
                        'company' => $company
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Show the form for importing PJA CCTV Dedicated Excel file.
     */
    public function importPjaCctvDedicatedForm()
    {
        return view('cctv-data.import-pja-cctv-dedicated');
    }

    /**
     * Import PJA CCTV Dedicated data from Excel file.
     */
    public function importPjaCctvDedicated(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Baca file Excel/CSV
            if ($extension === 'csv') {
                $reader = IOFactory::createReader('Csv');
                $reader->setInputEncoding('UTF-8');
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $spreadsheet = $reader->load($file->getRealPath());
            } else {
                $spreadsheet = IOFactory::load($file->getRealPath());
            }
            
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (count($rows) < 2) {
                return back()->withErrors(['file' => 'File harus memiliki minimal header dan 1 baris data.']);
            }

            // Ambil header (baris pertama)
            $headers = array_map('trim', $rows[0]);
            
            // Mapping kolom Excel ke field
            $columnMapping = [
                'no' => ['no', 'nomor', 'number'],
                'pja' => ['pja'],
                'cctv_dedicated' => ['cctv dedicated', 'cctv_dedicated', 'cctv'],
            ];

            // Cari index kolom untuk setiap field
            $columnIndexes = [];
            foreach ($columnMapping as $field => $possibleNames) {
                $columnIndexes[$field] = null;
                foreach ($headers as $index => $header) {
                    $headerLower = strtolower(trim($header));
                    foreach ($possibleNames as $possibleName) {
                        if ($headerLower === strtolower($possibleName)) {
                            $columnIndexes[$field] = $index;
                            break 2;
                        }
                    }
                }
            }

            // Validasi kolom wajib
            if ($columnIndexes['pja'] === null || $columnIndexes['cctv_dedicated'] === null) {
                return back()->withErrors(['file' => 'File harus memiliki kolom: PJA dan CCTV Dedicated.']);
            }

            // Proses data (mulai dari baris kedua)
            $successCount = 0;
            $errorCount = 0;
            $deletedCount = 0;
            $errors = [];
            
            // Kumpulkan semua data valid dari Excel terlebih dahulu
            $validData = [];
            $cctvListToReplace = [];
            
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Skip baris kosong
                if (empty(array_filter($row))) {
                    continue;
                }

                // Ambil data dari Excel
                $no = isset($row[$columnIndexes['no']]) ? trim((string) $row[$columnIndexes['no']]) : null;
                $pja = isset($row[$columnIndexes['pja']]) ? trim((string) $row[$columnIndexes['pja']]) : null;
                $cctvDedicated = isset($row[$columnIndexes['cctv_dedicated']]) ? trim((string) $row[$columnIndexes['cctv_dedicated']]) : null;

                // Validasi data wajib
                if (empty($pja) || empty($cctvDedicated)) {
                    $errorCount++;
                    $errors[] = "Baris " . ($i + 1) . ": PJA dan CCTV Dedicated harus diisi.";
                    continue;
                }

                // Simpan data valid
                $validData[] = [
                    'no' => $no,
                    'pja' => $pja,
                    'cctv_dedicated' => $cctvDedicated,
                    'row_number' => $i + 1,
                ];
                
                // Kumpulkan daftar CCTV unik yang akan di-replace
                $cctvListToReplace[$cctvDedicated] = true;
            }
            
            if (empty($validData)) {
                return back()->withErrors(['file' => 'Tidak ada data valid untuk diimpor.']);
            }

            DB::beginTransaction();
            
            try {
                // Hapus semua data lama untuk CCTV yang ada di Excel
                $cctvNames = array_keys($cctvListToReplace);
                $deletedCount = PjaCctvDedicated::whereIn('cctv_dedicated', $cctvNames)->count();
                PjaCctvDedicated::whereIn('cctv_dedicated', $cctvNames)->delete();
                
                // Insert semua data baru dari Excel
                foreach ($validData as $data) {
                    try {
                        PjaCctvDedicated::create([
                            'no' => $data['no'],
                            'pja' => $data['pja'],
                            'cctv_dedicated' => $data['cctv_dedicated'],
                        ]);
                        $successCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                        $errors[] = "Baris " . $data['row_number'] . ": " . $e->getMessage();
                    }
                }

                DB::commit();

                $message = "Import berhasil! {$successCount} data berhasil diimpor.";
                if ($deletedCount > 0) {
                    $message .= " {$deletedCount} data lama di-replace.";
                }
                if ($errorCount > 0) {
                    $message .= " {$errorCount} data gagal diimpor.";
                }

                return redirect()->route('cctv-data.import-pja-cctv-dedicated-form')
                    ->with('success', $message)
                    ->with('import_errors', $errors);

            } catch (Exception $e) {
                DB::rollBack();
                return back()->withErrors(['file' => 'Error saat menyimpan data: ' . $e->getMessage()]);
            }

        } catch (Exception $e) {
            return back()->withErrors(['file' => 'Error processing file: ' . $e->getMessage()]);
        }
    }

    /**
     * Get CCTV details including coverage, hazard stats, and PJA
     */
    // public function getCctvDetails(Request $request, $id)
    // {
    //     try {
    //         $cctv = CctvData::findOrFail($id);
            
    //         // Get coverage locations
    //         $coverages = CctvCoverage::where('id_cctv', $id)
    //             ->orderBy('coverage_lokasi')
    //             ->orderBy('coverage_detail_lokasi')
    //             ->get();
            
    //         // Get this week's date range using ClickHouse toStartOfWeek (konsisten dengan week di database)
    //         // Gunakan toStartOfWeek() di ClickHouse untuk memastikan week calculation sama dengan kolom 'minggu'
    //         $today = \Carbon\Carbon::today();
    //         $todayStr = $today->format('Y-m-d');
            
    //         // Get hazard inspection statistics
    //         $hazardStats = [];
    //         $clickhouse = new ClickHouseService();
            
    //         if ($clickhouse->isConnected() && $coverages->count() > 0) {
    //             // Tools pengawasan yang valid
    //             $validTools = [
    //                 'Post Event - CCTV Portable',
    //                 'Post Event - CCTV Support',
    //                 'Post Event - Mining Eyes',
    //                 'Real Time - CCTV Portable',
    //                 'Real Time - CCTV Support',
    //                 'Real Time - Kamera',
    //                 'Real Time - Mining Eyes'
    //             ];
                
    //             // Build detail lokasi list from coverages
    //             $detailLokasiList = $coverages->pluck('coverage_detail_lokasi')->filter()->toArray();
                
    //             if (!empty($detailLokasiList)) {
    //                 // Normalize detail lokasi list
    //                 $normalizedDetailLokasiMap = [];
    //                 foreach ($detailLokasiList as $lokasi) {
    //                     $normalized = mb_strtolower(trim($lokasi));
    //                     $normalizedDetailLokasiMap[$normalized] = $lokasi;
    //                 }
                    
    //                 // Build WHERE conditions for detail lokasi matching with flexible LIKE
    //                 $detailLokasiConditions = [];
    //                 foreach ($detailLokasiList as $lokasi) {
    //                     $escapedLokasi = addslashes(trim($lokasi));
    //                     // Use multiple matching strategies for better matching
    //                     $detailLokasiConditions[] = "(
    //                         lowerUTF8(toString(`nama detail lokasi`)) = lowerUTF8('{$escapedLokasi}')
    //                         OR lowerUTF8(toString(`nama detail lokasi`)) LIKE lowerUTF8('%{$escapedLokasi}%')
    //                         OR lowerUTF8('{$escapedLokasi}') LIKE concat('%', lowerUTF8(toString(`nama detail lokasi`)), '%')
    //                     )";
    //                 }
    //                 $detailLokasiFilter = '(' . implode(' OR ', $detailLokasiConditions) . ')';
                    
    //                 $escapedTools = array_map(function($tool) {
    //                     return "'" . addslashes($tool) . "'";
    //                 }, $validTools);
    //                 $toolsFilter = implode(',', $escapedTools);
                    
    //                 // Query hazard inspections for this week
    //                 // Gunakan toStartOfWeek() untuk filter berdasarkan week yang sama dengan kolom 'minggu'
    //                 $sqlHazard = "
    //                     SELECT 
    //                         toString(`nama detail lokasi`) as detail_lokasi,
    //                         toString(`tools pengawasan`) as tools_pengawasan,
    //                         COUNT(*) as count
    //                     FROM nitip.tabel_inspeksi_hazard
    //                     WHERE toStartOfWeek(toDate(`tanggal pelaporan`)) = toStartOfWeek(today())
    //                         AND toString(`nama detail lokasi`) != ''
    //                         AND toString(`nama detail lokasi`) IS NOT NULL
    //                         AND toString(`tools pengawasan`) IN ({$toolsFilter})
    //                     GROUP BY `nama detail lokasi`, `tools pengawasan`
    //                     LIMIT 5000
    //                 ";
                    
    //                 try {
    //                     $hazardResults = $clickhouse->query($sqlHazard);
                        
    //                     Log::info('CCTV Details - Hazard Query Results', [
    //                         'cctv_id' => $id,
    //                         'total_hazard_results' => count($hazardResults),
    //                         'coverage_detail_lokasi_list' => array_values($normalizedDetailLokasiMap),
    //                         'sample_hazard_results' => array_slice($hazardResults, 0, 5)
    //                     ]);
                        
    //                     $matchedCount = 0;
    //                     $unmatchedCount = 0;
                        
    //                     // Group by detail_lokasi with flexible matching
    //                     foreach ($hazardResults as $row) {
    //                         $hazardDetailLokasi = trim($row['detail_lokasi'] ?? '');
    //                         $count = (int)($row['count'] ?? 0);
    //                         $tool = $row['tools_pengawasan'] ?? '';
                            
    //                         if (empty($hazardDetailLokasi)) continue;
                            
    //                         // Find matching coverage detail lokasi with flexible matching
    //                         $matchedCoverageLokasi = null;
    //                         $normalizedHazard = mb_strtolower(trim($hazardDetailLokasi));
                            
    //                         foreach ($normalizedDetailLokasiMap as $normalizedCoverage => $originalCoverage) {
    //                             // Check if they match using multiple strategies
    //                             $matches = false;
                                
    //                             // Exact match
    //                             if ($normalizedCoverage === $normalizedHazard) {
    //                                 $matches = true;
    //                             }
    //                             // Coverage contains hazard
    //                             elseif (mb_strlen($normalizedCoverage) >= mb_strlen($normalizedHazard) && mb_strpos($normalizedCoverage, $normalizedHazard) !== false) {
    //                                 $matches = true;
    //                             }
    //                             // Hazard contains coverage
    //                             elseif (mb_strlen($normalizedHazard) >= mb_strlen($normalizedCoverage) && mb_strpos($normalizedHazard, $normalizedCoverage) !== false) {
    //                                 $matches = true;
    //                             }
    //                             // Similar text check (>= 80% similarity)
    //                             elseif (similar_text($normalizedCoverage, $normalizedHazard, $percent) && $percent >= 80) {
    //                                 $matches = true;
    //                             }
                                
    //                             if ($matches) {
    //                                 $matchedCoverageLokasi = $originalCoverage;
    //                                 break;
    //                             }
    //                         }
                            
    //                         // Hanya masukkan hazard jika ada match dengan coverage detail lokasi CCTV
    //                         // Jika tidak ada match, skip (tidak tampilkan)
    //                         if ($matchedCoverageLokasi === null) {
    //                             $unmatchedCount++;
    //                             continue; // Skip hazard yang tidak match
    //                         }
                            
    //                         $matchedCount++;
                            
    //                         // Gunakan matched coverage detail lokasi sebagai key
    //                         $keyLokasi = $matchedCoverageLokasi;
                            
    //                         if (!isset($hazardStats[$keyLokasi])) {
    //                             $hazardStats[$keyLokasi] = [
    //                                 'detail_lokasi' => $keyLokasi,
    //                                 'total_count' => 0,
    //                                 'by_tool' => []
    //                             ];
    //                         }
    //                         $hazardStats[$keyLokasi]['total_count'] += $count;
    //                         $hazardStats[$keyLokasi]['by_tool'][$tool] = $count;
    //                     }
                        
    //                     Log::info('CCTV Details - Hazard Matching Summary', [
    //                         'cctv_id' => $id,
    //                         'total_hazard_results' => count($hazardResults),
    //                         'matched_count' => $matchedCount,
    //                         'unmatched_count' => $unmatchedCount,
    //                         'final_hazard_stats_count' => count($hazardStats)
    //                     ]);
    //                 } catch (Exception $e) {
    //                     Log::error('Error querying hazard inspections: ' . $e->getMessage());
    //                 }
    //             }
    //         }
            
    //         // Get PJA information
    //         $pjaList = [];
    //         if ($clickhouse->isConnected() && $coverages->count() > 0) {
    //             $detailLokasiList = $coverages->pluck('coverage_detail_lokasi')->filter()->toArray();
                
    //             if (!empty($detailLokasiList)) {
    //                 // Build WHERE conditions for detail lokasi matching (using LIKE for flexible matching)
    //                 $detailLokasiConditions = [];
    //                 foreach ($detailLokasiList as $lokasi) {
    //                     $escapedLokasi = addslashes($lokasi);
    //                     // Use LIKE for flexible matching (case-insensitive)
    //                     $detailLokasiConditions[] = "lowerUTF8(toString(detail_lokasi)) = lowerUTF8('{$escapedLokasi}')";
    //                 }
    //                 $detailLokasiFilter = '(' . implode(' OR ', $detailLokasiConditions) . ')';
                    
    //                 $sqlPja = "
    //                     SELECT DISTINCT
    //                         toString(site) as site,
    //                         toString(lokasi) as lokasi,
    //                         toString(detail_lokasi) as detail_lokasi,
    //                         toString(pja_id) as pja_id,
    //                         toString(nama_pja) as nama_pja,
    //                         toString(pja_active) as pja_active,
    //                         toString(employee_name) as employee_name,
    //                         toString(kode_sid) as kode_sid,
    //                         toString(employee_email) as employee_email,
    //                         toString(kategori_pja) as kategori_pja
    //                     FROM nitip.pja_full_hierarchical_view_fix
    //                     WHERE {$detailLokasiFilter}
    //                         AND toString(pja_active) = '1'
    //                     ORDER BY detail_lokasi, nama_pja
    //                     LIMIT 100
    //                 ";
                    
    //                 try {
    //                     $pjaResults = $clickhouse->query($sqlPja);
                        
    //                     // Group by detail_lokasi
    //                     foreach ($pjaResults as $row) {
    //                         $detailLokasi = $row['detail_lokasi'] ?? '';
    //                         if (!isset($pjaList[$detailLokasi])) {
    //                             $pjaList[$detailLokasi] = [];
    //                         }
    //                         $pjaList[$detailLokasi][] = [
    //                             'pja_id' => $row['pja_id'] ?? '',
    //                             'nama_pja' => $row['nama_pja'] ?? '',
    //                             'employee_name' => $row['employee_name'] ?? '',
    //                             'kode_sid' => $row['kode_sid'] ?? '',
    //                             'employee_email' => $row['employee_email'] ?? '',
    //                             'kategori_pja' => $row['kategori_pja'] ?? '',
    //                             'site' => $row['site'] ?? '',
    //                             'lokasi' => $row['lokasi'] ?? ''
    //                         ];
    //                     }
    //                 } catch (Exception $e) {
    //                     Log::error('Error querying PJA data: ' . $e->getMessage());
    //                 }
    //             }
    //         }
            
    //         return response()->json([
    //             'success' => true,
    //             'data' => [
    //                 'cctv' => [
    //                     'id' => $cctv->id,
    //                     'nama_cctv' => $cctv->nama_cctv,
    //                     'no_cctv' => $cctv->no_cctv,
    //                     'site' => $cctv->site,
    //                 ],
    //                 'coverages' => $coverages->map(function($coverage) {
    //                     return [
    //                         'id' => $coverage->id,
    //                         'coverage_lokasi' => $coverage->coverage_lokasi,
    //                         'coverage_detail_lokasi' => $coverage->coverage_detail_lokasi,
    //                     ];
    //                 }),
    //                 'hazard_stats' => array_values($hazardStats),
    //                 'pja_list' => $pjaList,
    //                 'week_info' => [
    //                     'today' => $todayStr,
    //                     'method' => 'toStartOfWeek() in ClickHouse'
    //                 ]
    //             ]
    //         ]);
            
    //     } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'error' => 'CCTV not found'
    //         ], 404);
    //     } catch (Exception $e) {
    //         Log::error('Error getting CCTV details: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'error' => 'Error retrieving CCTV details: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function getCctvDetails(Request $request, $id)
{
    try {
        $cctv = CctvData::findOrFail($id);

        // Get coverage locations
        $coverages = CctvCoverage::where('id_cctv', $id)
            ->orderBy('coverage_lokasi')
            ->orderBy('coverage_detail_lokasi')
            ->get();

        // Info tanggal hari ini (untuk informasi di response)
        $today = \Carbon\Carbon::today();
        $todayStr = $today->format('Y-m-d');

        // ==============================
        // 1. HAZARD ("Inspeksi Hazard Minggu Ini")
        //    Sumber: nitip.aaj_car_all_year_from_dav
        // ==============================
        $hazardStats = [];
        $clickhouse = new ClickHouseService();

        if ($clickhouse->isConnected() && $coverages->count() > 0) {
            // Tools pengawasan yang valid (kolom: name_tools_observation)
            $validTools = [
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Real Time - CCTV Support',
                'Real Time - Mining Eyes',
                'Real Time - Kamera',
                'Pengawasan Langsung',
            ];

            // Daftar detail lokasi dari coverage CCTV
            $detailLokasiList = $coverages->pluck('coverage_detail_lokasi')->filter()->toArray();

            if (!empty($detailLokasiList)) {
                // Normalisasi detail lokasi (lowercase, trim) untuk pencocokan fleksibel
                $normalizedDetailLokasiMap = [];
                foreach ($detailLokasiList as $lokasi) {
                    $normalized = mb_strtolower(trim($lokasi));
                    $normalizedDetailLokasiMap[$normalized] = $lokasi;
                }

                // Filter nama_detail_lokasi di ClickHouse
                $detailLokasiConditions = [];
                foreach ($detailLokasiList as $lokasi) {
                    $escapedLokasi = addslashes(trim($lokasi));
                    $detailLokasiConditions[] =
                        "lowerUTF8(toString(nama_detail_lokasi)) = lowerUTF8('{$escapedLokasi}')";
                }
                $detailLokasiFilter = '(' . implode(' OR ', $detailLokasiConditions) . ')';

                // Filter tools (name_tools_observation)
                $escapedTools = array_map(function ($tool) {
                    return "'" . addslashes($tool) . "'";
                }, $validTools);
                $toolsFilter = implode(',', $escapedTools);

                // Query hazard minggu ini
                // Menggunakan toStartOfWeek(tanggal_pembuatan) sebagai acuan minggu
                $sqlHazard = "
                    SELECT 
                        toString(nama_detail_lokasi)     AS detail_lokasi,
                        toString(name_tools_observation) AS tools_pengawasan,
                        COUNT(*)                         AS count
                    FROM nitip.aaj_car_all_year_from_dav
                    WHERE toStartOfWeek(toDate(tanggal_pembuatan)) = toStartOfWeek(today())
                        AND toString(nama_detail_lokasi) != ''
                        AND toString(nama_detail_lokasi) IS NOT NULL
                        AND toString(name_tools_observation) IN ({$toolsFilter})
                        AND {$detailLokasiFilter}
                    GROUP BY nama_detail_lokasi, name_tools_observation
                    LIMIT 5000
                ";

                try {
                    $hazardResults = $clickhouse->query($sqlHazard);

                    Log::info('CCTV Details - Hazard Query Results (aaj_car_all_year_from_dav)', [
                        'cctv_id'                     => $id,
                        'total_hazard_results'        => count($hazardResults),
                        'coverage_detail_lokasi_list' => array_values($normalizedDetailLokasiMap),
                        'sample_hazard_results'       => array_slice($hazardResults, 0, 5),
                    ]);

                    $matchedCount   = 0;
                    $unmatchedCount = 0;

                    // Group per detail_lokasi dengan pencocokan fleksibel ke coverage_detail_lokasi CCTV
                    foreach ($hazardResults as $row) {
                        $hazardDetailLokasi = trim($row['detail_lokasi'] ?? '');
                        $count              = (int)($row['count'] ?? 0);
                        $tool               = $row['tools_pengawasan'] ?? '';

                        if ($hazardDetailLokasi === '') {
                            continue;
                        }

                        $matchedCoverageLokasi = null;
                        $normalizedHazard      = mb_strtolower($hazardDetailLokasi);

                        foreach ($normalizedDetailLokasiMap as $normalizedCoverage => $originalCoverage) {
                            $matches = false;

                            // Exact match
                            if ($normalizedCoverage === $normalizedHazard) {
                                $matches = true;
                            }
                            // Coverage contains hazard
                            elseif (mb_strlen($normalizedCoverage) >= mb_strlen($normalizedHazard)
                                && mb_strpos($normalizedCoverage, $normalizedHazard) !== false) {
                                $matches = true;
                            }
                            // Hazard contains coverage
                            elseif (mb_strlen($normalizedHazard) >= mb_strlen($normalizedCoverage)
                                && mb_strpos($normalizedHazard, $normalizedCoverage) !== false) {
                                $matches = true;
                            }
                            // Similarity >= 80%
                            elseif (similar_text($normalizedCoverage, $normalizedHazard, $percent) && $percent >= 80) {
                                $matches = true;
                            }

                            if ($matches) {
                                $matchedCoverageLokasi = $originalCoverage;
                                break;
                            }
                        }

                        // Hanya masukkan hazard jika ada match dengan coverage_detail_lokasi
                        if ($matchedCoverageLokasi === null) {
                            $unmatchedCount++;
                            continue;
                        }

                        $matchedCount++;

                        $keyLokasi = $matchedCoverageLokasi;

                        if (!isset($hazardStats[$keyLokasi])) {
                            $hazardStats[$keyLokasi] = [
                                'detail_lokasi' => $keyLokasi,
                                'total_count'   => 0,
                                'by_tool'       => [],
                            ];
                        }

                        $hazardStats[$keyLokasi]['total_count'] += $count;
                        $hazardStats[$keyLokasi]['by_tool'][$tool] = $count;
                    }

                    Log::info('CCTV Details - Hazard Matching Summary (aaj_car_all_year_from_dav)', [
                        'cctv_id'                  => $id,
                        'total_hazard_results'     => count($hazardResults),
                        'matched_count'            => $matchedCount,
                        'unmatched_count'          => $unmatchedCount,
                        'final_hazard_stats_count' => count($hazardStats),
                    ]);
                } catch (Exception $e) {
                    Log::error('Error querying hazard from nitip.aaj_car_all_year_from_dav: ' . $e->getMessage());
                }
            }
        }

        // ==============================
        // 2. PJA Lokasi
        //    Sumber baru: nitip.wan_vw_pja_karyawan
        //    Filter: perusahaan CCTV (kolom peruashaan)
        // ==============================
        $pjaList = [];

        if ($clickhouse->isConnected()) {
            $perusahaan = $cctv->perusahaan ?? null;

            if ($perusahaan) {
                $escapedPerusahaan = addslashes($perusahaan);

                $sqlPja = "
                    SELECT DISTINCT
                        toString(kode_sid)            AS kode_sid,
                        toString(nama_pja)            AS nama_pja,
                        toString(tipe_pja)            AS tipe_pja,
                        toString(peruashaan)          AS perusahaan,
                        toString(nama_karyawan)       AS nama_karyawan,
                        toString(status_pja_karyawan) AS status_pja_karyawan
                    FROM nitip.wan_vw_pja_karyawan
                    WHERE toString(peruashaan) = '{$escapedPerusahaan}'
                      AND toString(status_pja_karyawan) = '1'
                    ORDER BY nama_pja, nama_karyawan
                    LIMIT 200
                ";

                try {
                    $pjaResults = $clickhouse->query($sqlPja);

                    // Struktur pja_list sama dengan sebelumnya: pja_list[detail_lokasi][].
                    // Karena view ini tidak punya detail_lokasi, pakai key tunggal 'GLOBAL'.
                    $key = 'GLOBAL';
                    $pjaList[$key] = [];

                    foreach ($pjaResults as $row) {
                        $pjaList[$key][] = [
                            'pja_id'        => $row['kode_sid'] ?? '',
                            'nama_pja'      => $row['nama_pja'] ?? '',
                            'employee_name' => $row['nama_karyawan'] ?? '',
                            'kode_sid'      => $row['kode_sid'] ?? '',
                            'employee_email'=> '',
                            'kategori_pja'  => $row['tipe_pja'] ?? '',
                            'site'          => '',
                            'lokasi'        => '',
                        ];
                    }
                } catch (Exception $e) {
                    Log::error('Error querying PJA from nitip.wan_vw_pja_karyawan: ' . $e->getMessage());
                }
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'cctv' => [
                    'id'        => $cctv->id,
                    'nama_cctv' => $cctv->nama_cctv,
                    'no_cctv'   => $cctv->no_cctv,
                    'site'      => $cctv->site,
                ],
                'coverages' => $coverages->map(function ($coverage) {
                    return [
                        'id'                    => $coverage->id,
                        'coverage_lokasi'       => $coverage->coverage_lokasi,
                        'coverage_detail_lokasi'=> $coverage->coverage_detail_lokasi,
                    ];
                }),
                'hazard_stats' => array_values($hazardStats),
                'pja_list'     => $pjaList,
                'week_info'    => [
                    'today'  => $todayStr,
                    'method' => 'toStartOfWeek(tanggal_pembuatan) in nitip.aaj_car_all_year_from_dav',
                ],
            ],
        ]);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'error'   => 'CCTV not found',
        ], 404);
    } catch (Exception $e) {
        Log::error('Error getting CCTV details: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'error'   => 'Error retrieving CCTV details: ' . $e->getMessage(),
        ], 500);
    }
}

    /**
     * Get hazard inspection status for multiple CCTV (batch)
     * Returns status for each CCTV: has_hazard_inspection (true/false)
     */
    public function getCctvHazardStatus(Request $request)
    {
        try {
            $cctvIds = $request->get('ids', []);
            
            if (empty($cctvIds)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            // Parse IDs if they come as comma-separated string
            if (is_string($cctvIds)) {
                $cctvIds = array_filter(array_map('trim', explode(',', $cctvIds)));
            }
            
            // Get this week's date range using ClickHouse toStartOfWeek (konsisten dengan week di database)
            // Gunakan toStartOfWeek() di ClickHouse untuk memastikan week calculation sama dengan kolom 'minggu'
            $today = \Carbon\Carbon::today();
            $todayStr = $today->format('Y-m-d');
            
            // Log week calculation for debugging
            Log::info('CCTV Hazard Status - Week Calculation', [
                'today' => $todayStr,
                'method' => 'toStartOfWeek() in ClickHouse',
                'note' => 'Week calculation will be done in ClickHouse query using toStartOfWeek()'
            ]);
            
            // Get all coverages for these CCTV
            $coverages = CctvCoverage::whereIn('id_cctv', $cctvIds)
                ->get()
                ->groupBy('id_cctv');
            
            // Tools pengawasan yang valid
            $validTools = [
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Real Time - CCTV Support',
                'Real Time - Kamera',
                'Real Time - Mining Eyes'
            ];
            
            $statusMap = [];
            $clickhouse = new ClickHouseService();
            
            // Initialize all CCTV as no hazard inspection
            foreach ($cctvIds as $cctvId) {
                $statusMap[$cctvId] = [
                    'has_hazard_inspection' => false,
                    'total_count' => 0
                ];
            }
            
            if ($clickhouse->isConnected() && $coverages->count() > 0) {
                // Collect all detail lokasi from all CCTV
                $allDetailLokasi = [];
                $cctvDetailLokasiMap = []; // Map detail lokasi to CCTV IDs (normalized)
                $originalDetailLokasiMap = []; // Map original detail lokasi untuk reference
                
                foreach ($coverages as $cctvId => $cctvCoverages) {
                    foreach ($cctvCoverages as $coverage) {
                        $detailLokasi = trim($coverage->coverage_detail_lokasi ?? '');
                        if (!empty($detailLokasi)) {
                            // Normalize: trim, lowercase untuk matching
                            $normalizedLokasi = mb_strtolower(trim($detailLokasi));
                            
                            if (!isset($cctvDetailLokasiMap[$normalizedLokasi])) {
                                $cctvDetailLokasiMap[$normalizedLokasi] = [];
                                $allDetailLokasi[] = $detailLokasi; // Keep original for query
                                $originalDetailLokasiMap[$normalizedLokasi] = $detailLokasi;
                            }
                            $cctvDetailLokasiMap[$normalizedLokasi][] = $cctvId;
                        }
                    }
                }
                
                Log::info('CCTV Hazard Status Check', [
                    'today' => $todayStr,
                    'total_cctv' => count($cctvIds),
                    'total_coverage_lokasi' => count($allDetailLokasi),
                    'sample_lokasi' => array_slice($allDetailLokasi, 0, 5)
                ]);
                
                if (!empty($allDetailLokasi)) {
                    // Build WHERE conditions for detail lokasi matching
                    // Use LIKE for flexible matching (handles partial matches and variations)
                    $detailLokasiConditions = [];
                    foreach ($allDetailLokasi as $lokasi) {
                        $escapedLokasi = addslashes($lokasi);
                        // Try multiple matching strategies:
                        // 1. Exact match (case-insensitive)
                        // 2. LIKE match (contains)
                        // 3. Reverse LIKE match (coverage contains hazard detail)
                        $detailLokasiConditions[] = "(
                            lowerUTF8(toString(`nama detail lokasi`)) = lowerUTF8('{$escapedLokasi}')
                            OR lowerUTF8(toString(`nama detail lokasi`)) LIKE lowerUTF8('%{$escapedLokasi}%')
                            OR lowerUTF8('{$escapedLokasi}') LIKE concat('%', lowerUTF8(toString(`nama detail lokasi`)), '%')
                        )";
                    }
                    $detailLokasiFilter = '(' . implode(' OR ', $detailLokasiConditions) . ')';
                    
                    $escapedTools = array_map(function($tool) {
                        return "'" . addslashes($tool) . "'";
                    }, $validTools);
                    $toolsFilter = implode(',', $escapedTools);
                    
                    // Query hazard inspections for this week
                    // Gunakan toStartOfWeek() untuk filter berdasarkan week yang sama dengan kolom 'minggu'
                    $sqlHazard = "
                        SELECT 
                            toString(`nama detail lokasi`) as detail_lokasi,
                            toString(`tools pengawasan`) as tools_pengawasan,
                            COUNT(*) as count
                        FROM nitip.tabel_inspeksi_hazard
                        WHERE toStartOfWeek(toDate(`tanggal pelaporan`)) = toStartOfWeek(today())
                            AND toString(`nama detail lokasi`) != ''
                            AND toString(`nama detail lokasi`) IS NOT NULL
                            AND toString(`tools pengawasan`) IN ({$toolsFilter})
                        GROUP BY `nama detail lokasi`, `tools pengawasan`
                        LIMIT 5000
                    ";
                    
                    try {
                        Log::info('Executing hazard status query', [
                            'sql_preview' => substr($sqlHazard, 0, 500) . '...'
                        ]);
                        
                        $hazardResults = $clickhouse->query($sqlHazard);
                        
                        Log::info('Hazard query results', [
                            'total_results' => count($hazardResults),
                            'sample_results' => array_slice($hazardResults, 0, 3)
                        ]);
                        
                        // Map hazard results to CCTV IDs using flexible matching
                        foreach ($hazardResults as $row) {
                            $hazardDetailLokasi = trim($row['detail_lokasi'] ?? '');
                            $count = (int)($row['count'] ?? 0);
                            
                            if (empty($hazardDetailLokasi)) continue;
                            
                            // Normalize hazard detail lokasi
                            $normalizedHazardLokasi = mb_strtolower(trim($hazardDetailLokasi));
                            
                            // Find matching coverage detail lokasi
                            foreach ($cctvDetailLokasiMap as $normalizedCoverageLokasi => $matchingCctvIds) {
                                // Check if they match (exact or contains)
                                $matches = false;
                                
                                // Exact match
                                if ($normalizedCoverageLokasi === $normalizedHazardLokasi) {
                                    $matches = true;
                                }
                                // Coverage contains hazard
                                elseif (mb_strlen($normalizedCoverageLokasi) >= mb_strlen($normalizedHazardLokasi) && mb_strpos($normalizedCoverageLokasi, $normalizedHazardLokasi) !== false) {
                                    $matches = true;
                                }
                                // Hazard contains coverage
                                elseif (mb_strlen($normalizedHazardLokasi) >= mb_strlen($normalizedCoverageLokasi) && mb_strpos($normalizedHazardLokasi, $normalizedCoverageLokasi) !== false) {
                                    $matches = true;
                                }
                                // Similar text check (>= 80% similarity)
                                elseif (similar_text($normalizedCoverageLokasi, $normalizedHazardLokasi, $percent) && $percent >= 80) {
                                    $matches = true;
                                }
                                
                                if ($matches) {
                                    foreach ($matchingCctvIds as $cctvId) {
                                        if (isset($statusMap[$cctvId])) {
                                            $statusMap[$cctvId]['has_hazard_inspection'] = true;
                                            $statusMap[$cctvId]['total_count'] += $count;
                                        }
                                    }
                                }
                            }
                        }
                        
                        Log::info('Final status map', [
                            'total_cctv_with_hazard' => count(array_filter($statusMap, function($status) {
                                return $status['has_hazard_inspection'];
                            })),
                            'total_cctv_no_hazard' => count(array_filter($statusMap, function($status) {
                                return !$status['has_hazard_inspection'];
                            }))
                        ]);
                        
                    } catch (Exception $e) {
                        Log::error('Error querying hazard inspections for status: ' . $e->getMessage(), [
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                } else {
                    Log::warning('No coverage detail lokasi found for CCTV', [
                        'cctv_ids' => $cctvIds
                    ]);
                }
            } else {
                if (!$clickhouse->isConnected()) {
                    Log::warning('ClickHouse not connected for hazard status check');
                }
                if ($coverages->count() === 0) {
                    Log::warning('No coverages found for CCTV', [
                        'cctv_ids' => $cctvIds
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $statusMap,
                'week_info' => [
                    'today' => $todayStr,
                    'method' => 'toStartOfWeek() in ClickHouse'
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Error getting CCTV hazard status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error retrieving CCTV hazard status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of PJA CCTV Dedicated data.
     */
    public function indexPjaCctvDedicated()
    {
        // Get statistics
        $stats = $this->getPjaCctvStatistics();
        
        // Static list of sites
        $sites = ['BMO 1', 'BMO 2', 'BMO 3', 'EXPLORASI', 'LMO', 'SMO', 'GMO', 'HO', 'MARINE'];
        
        return view('cctv-data.pja-cctv-dedicated', compact('stats', 'sites'));
    }

    /**
     * Get statistics for PJA CCTV mapping
     */
    private function getPjaCctvStatistics()
    {
        // Get all mapped CCTV dedicated values
        $mappedCctv = PjaCctvDedicated::distinct('cctv_dedicated')
            ->pluck('cctv_dedicated')
            ->filter()
            ->map(function($item) {
                return trim($item);
            })
            ->toArray();

        // Get all CCTV data
        $allCctvData = CctvData::get();

        // Count CCTV that have PJA mapping
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
        $unmappedCctvCount = $allCctvData->filter(function($cctv) use ($mappedCctv) {
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

        $totalCctv = $allCctvData->count();
        $mappedPercentage = $totalCctv > 0 ? round(($mappedCctvCount / $totalCctv) * 100, 2) : 0;
        $unmappedPercentage = $totalCctv > 0 ? round(($unmappedCctvCount / $totalCctv) * 100, 2) : 0;

        return [
            'total_cctv' => $totalCctv,
            'mapped_cctv' => $mappedCctvCount,
            'unmapped_cctv' => $unmappedCctvCount,
            'mapped_percentage' => $mappedPercentage,
            'unmapped_percentage' => $unmappedPercentage,
        ];
    }

    /**
     * Get PJA CCTV Dedicated data for DataTable (server-side processing)
     * Grouped by CCTV Dedicated
     */
    public function getPjaCctvDedicatedData(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get('start', 0);
        $length = $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';
        $orderColumn = $request->get('order')[0]['column'] ?? 0;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';
        $siteFilter = $request->get('site', '');

        // Column mapping (sesuai urutan kolom di DataTable: #, NO, CCTV Dedicated, Jumlah PJA, PJA, Created At, Updated At)
        $columns = ['cctv_dedicated', 'no', 'cctv_dedicated', 'pja_count', 'pja', 'created_at', 'updated_at'];
        // Jika kolom pertama (#) yang di-order, gunakan cctv_dedicated sebagai gantinya
        if ($orderColumn == 0) {
            $orderColumnName = 'cctv_dedicated';
        } else {
            $orderColumnName = $columns[$orderColumn] ?? 'cctv_dedicated';
        }

        // Get total records (jumlah unique CCTV) - tanpa filter
        $recordsTotal = PjaCctvDedicated::distinct('cctv_dedicated')->count('cctv_dedicated');

        // Base query - ambil semua data dulu untuk grouping
        $query = PjaCctvDedicated::query();

        // Filter by site (no column)
        if (!empty($siteFilter)) {
            $query->where('no', $siteFilter);
        }

        // Search functionality - search sebelum grouping
        if (!empty($searchValue)) {
            $query->where(function($q) use ($searchValue) {
                $q->where('no', 'like', '%' . $searchValue . '%')
                  ->orWhere('pja', 'like', '%' . $searchValue . '%')
                  ->orWhere('cctv_dedicated', 'like', '%' . $searchValue . '%');
            });
        }

        // Get all data untuk grouping
        $allData = $query->get();

        // Group by cctv_dedicated
        $groupedData = $allData->groupBy('cctv_dedicated');

        // Format grouped data
        $formattedGrouped = $groupedData->map(function($items, $cctvDedicated) {
            $pjaList = $items->pluck('pja')->filter()->unique()->values();
            $noList = $items->pluck('no')->filter()->unique()->values();
            $createdAt = $items->max('created_at');
            $updatedAt = $items->max('updated_at');
            
            return [
                'cctv_dedicated' => $cctvDedicated,
                'no' => $noList->first() ?? '-',
                'pja_list' => $pjaList->toArray(),
                'pja' => $pjaList->implode(', '), // Untuk display
                'pja_count' => $pjaList->count(),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        })->values();

        // Get filtered records count (jumlah unique CCTV setelah filter)
        $recordsFiltered = $formattedGrouped->count();

        // Sort grouped data
        $sortedData = $formattedGrouped->sortBy(function($item) use ($orderColumnName, $orderDir) {
            // Handle different column types
            if ($orderColumnName === 'created_at' || $orderColumnName === 'updated_at') {
                return $item[$orderColumnName] ? $item[$orderColumnName]->timestamp : 0;
            } elseif ($orderColumnName === 'pja_count') {
                return $item['pja_count'] ?? 0;
            } else {
                $value = $item[$orderColumnName] ?? '';
                return is_numeric($value) ? (float)$value : strtolower($value);
            }
        }, SORT_REGULAR, $orderDir === 'desc');

        // Paginate
        $paginatedData = $sortedData->slice($start, $length)->values();

        // Format data for DataTable
        $formattedData = $paginatedData->map(function($item, $index) use ($start) {
            // Format PJA dengan line break untuk tampilan yang lebih baik
            $pjaDisplay = '';
            if (!empty($item['pja_list']) && count($item['pja_list']) > 0) {
                $pjaDisplay = '<div style="max-width: 500px; line-height: 1.8;">';
                foreach ($item['pja_list'] as $idx => $pja) {
                    $pjaDisplay .= '<div class="mb-1 small">' . 
                        '<span class="badge bg-secondary me-1">' . ($idx + 1) . '</span>' .
                        htmlspecialchars($pja) . 
                        '</div>';
                }
                $pjaDisplay .= '</div>';
            } else {
                $pjaDisplay = '<span class="text-muted">-</span>';
            }

            return [
                'DT_RowIndex' => $start + $index + 1,
                'no' => $item['no'] ?? '-',
                'pja' => $pjaDisplay,
                'pja_raw' => $item['pja'], // Untuk search
                'cctv_dedicated' => $item['cctv_dedicated'] ?? '-',
                'pja_count' => '<span class="badge bg-primary">' . ($item['pja_count'] ?? 0) . '</span>',
                'created_at' => $item['created_at'] ? $item['created_at']->format('Y-m-d H:i:s') : '-',
                'updated_at' => $item['updated_at'] ? $item['updated_at']->format('Y-m-d H:i:s') : '-',
            ];
        });

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $formattedData
        ]);
    }

    /**
     * Export PJA CCTV Dedicated data to Excel
     * Grouped by CCTV Dedicated
     */
    public function exportPjaCctvDedicated()
    {
        try {
            // Get all data and group by CCTV Dedicated
            $allData = PjaCctvDedicated::orderBy('cctv_dedicated')->get();
            
            // Group by cctv_dedicated
            $groupedData = $allData->groupBy('cctv_dedicated');

            // Format grouped data
            $formattedGrouped = $groupedData->map(function($items, $cctvDedicated) {
                $pjaList = $items->pluck('pja')->filter()->unique()->values();
                $noList = $items->pluck('no')->filter()->unique()->values();
                $createdAt = $items->max('created_at');
                $updatedAt = $items->max('updated_at');
                
                return [
                    'cctv_dedicated' => $cctvDedicated,
                    'no' => $noList->first() ?? '-',
                    'pja_list' => $pjaList->toArray(),
                    'pja' => $pjaList->implode(', '),
                    'pja_count' => $pjaList->count(),
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];
            })->values()->sortBy('cctv_dedicated');

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Header: #, site, CCTV Dedicated, Jumlah PJA, PJA, Created At, Updated At
            $sheet->setCellValue('A1', '#');
            $sheet->setCellValue('B1', 'site');
            $sheet->setCellValue('C1', 'CCTV Dedicated');
            $sheet->setCellValue('D1', 'Jumlah PJA');
            $sheet->setCellValue('E1', 'PJA');
            $sheet->setCellValue('F1', 'Created At');
            $sheet->setCellValue('G1', 'Updated At');

            // Style header
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ];
            $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

            // Data rows
            $rowNum = 2;
            $no = 1;
            foreach ($formattedGrouped as $item) {
                // PJA format: 1Nama PJA 1, 2Nama PJA 2, ... (tanpa spasi, per baris)
                $pjaList = $item['pja_list'] ?? [];
                $pjaLines = [];
                if (is_array($pjaList)) {
                    foreach ($pjaList as $idx => $pja) {
                        $pjaLines[] = ($idx + 1) . $pja;
                    }
                }
                $pjaExcel = implode("\n", $pjaLines);

                $sheet->setCellValue('A' . $rowNum, $no);
                $sheet->setCellValue('B' . $rowNum, $item['no'] ?? '');
                $sheet->setCellValue('C' . $rowNum, $item['cctv_dedicated'] ?? '');
                $sheet->setCellValue('D' . $rowNum, $item['pja_count'] ?? 0);
                $sheet->setCellValue('E' . $rowNum, $pjaExcel);
                $sheet->setCellValue('F' . $rowNum, $item['created_at'] ? $item['created_at']->format('Y-m-d H:i:s') : '');
                $sheet->setCellValue('G' . $rowNum, $item['updated_at'] ? $item['updated_at']->format('Y-m-d H:i:s') : '');
                $sheet->getStyle('E' . $rowNum)->getAlignment()->setWrapText(true);
                $rowNum++;
                $no++;
            }

            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->getColumnDimension('E')->setWidth(50);

            // Download file
            $writer = new Xlsx($spreadsheet);
            $filename = 'pja_cctv_dedicated_' . date('Y-m-d_His') . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            Log::error('Error exporting PJA CCTV Dedicated: ' . $e->getMessage());
            return redirect()->route('cctv-data.pja-cctv-dedicated.index')
                ->with('error', 'Error generating export: ' . $e->getMessage());
        }
    }

    /**
     * Display CCTV that are not mapped to PJA
     */
    public function indexUnmappedCctv()
    {
        return view('cctv-data.unmapped-cctv');
    }

    /**
     * Get unmapped CCTV data for DataTable (server-side processing)
     * CCTV yang no_cctv atau nama_cctv tidak ada di pja_cctv_dedicated.cctv_dedicated
     */
    public function getUnmappedCctvData(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get('start', 0);
        $length = $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';
        $orderColumn = $request->get('order')[0]['column'] ?? 0;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        // Column mapping (sesuai urutan kolom di DataTable)
        $columns = ['id', 'site', 'perusahaan', 'no_cctv', 'nama_cctv', 'status', 'kondisi'];
        // Jika kolom pertama (#) yang di-order, gunakan id sebagai gantinya
        if ($orderColumn == 0) {
            $orderColumnName = 'id';
        } else {
            $orderColumnName = $columns[$orderColumn] ?? 'id';
        }

        // Get all mapped CCTV dedicated values (trimmed and filtered)
        $mappedCctv = PjaCctvDedicated::distinct('cctv_dedicated')
            ->pluck('cctv_dedicated')
            ->filter()
            ->map(function($item) {
                return trim($item);
            })
            ->filter()
            ->toArray();

        // Base query - Get all CCTV
        $query = CctvData::query();
        
        // Search functionality
        if (!empty($searchValue)) {
            $query->where(function($q) use ($searchValue) {
                $q->where('site', 'like', '%' . $searchValue . '%')
                  ->orWhere('perusahaan', 'like', '%' . $searchValue . '%')
                  ->orWhere('no_cctv', 'like', '%' . $searchValue . '%')
                  ->orWhere('nama_cctv', 'like', '%' . $searchValue . '%')
                  ->orWhere('status', 'like', '%' . $searchValue . '%')
                  ->orWhere('kondisi', 'like', '%' . $searchValue . '%');
            });
        }

        // Get all CCTV data
        $allCctvData = $query->get();

        // Filter CCTV yang belum termapping
        $unmappedCctv = $allCctvData->filter(function($cctv) use ($mappedCctv) {
            $noCctv = trim($cctv->no_cctv ?? '');
            $namaCctv = trim($cctv->nama_cctv ?? '');
            
            // Jika tidak ada no_cctv dan nama_cctv, skip
            if (empty($noCctv) && empty($namaCctv)) {
                return false; // Skip CCTV tanpa identitas
            }
            
            // Cek apakah no_cctv atau nama_cctv ada di mapped CCTV
            foreach ($mappedCctv as $mapped) {
                $mappedTrimmed = trim($mapped);
                if (empty($mappedTrimmed)) continue;
                
                // Exact match
                if ($noCctv === $mappedTrimmed || $namaCctv === $mappedTrimmed) {
                    return false; // Sudah termapping
                }
                
                // Partial match (contains) - lebih fleksibel
                if (!empty($noCctv) && (
                    str_contains($noCctv, $mappedTrimmed) || 
                    str_contains($mappedTrimmed, $noCctv)
                )) {
                    return false; // Sudah termapping
                }
                if (!empty($namaCctv) && (
                    str_contains($namaCctv, $mappedTrimmed) || 
                    str_contains($mappedTrimmed, $namaCctv)
                )) {
                    return false; // Sudah termapping
                }
            }
            
            return true; // Belum termapping
        });

        // Get total records (jumlah CCTV yang belum termapping) - tanpa filter search
        $totalUnmappedQuery = CctvData::query();
        $totalUnmappedData = $totalUnmappedQuery->get();
        $totalUnmapped = $totalUnmappedData->filter(function($cctv) use ($mappedCctv) {
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

        $recordsTotal = $totalUnmapped;
        $recordsFiltered = $unmappedCctv->count();

        // Sort
        $sortedData = $unmappedCctv->sortBy(function($item) use ($orderColumnName, $orderDir) {
            $value = $item->{$orderColumnName} ?? '';
            return is_numeric($value) ? (float)$value : strtolower($value);
        }, SORT_REGULAR, $orderDir === 'desc');

        // Paginate
        $paginatedData = $sortedData->slice($start, $length)->values();

        // Format data for DataTable
        $formattedData = $paginatedData->map(function($item, $index) use ($start) {
            return [
                'DT_RowIndex' => $start + $index + 1,
                'site' => $item->site ?? '-',
                'perusahaan' => $item->perusahaan ?? '-',
                'no_cctv' => $item->no_cctv ?? '-',
                'nama_cctv' => $item->nama_cctv ?? '-',
                'status' => $item->status ? '<span class="badge bg-' . ($item->status == 'Live View' ? 'success' : 'secondary') . '">' . $item->status . '</span>' : '<span class="text-muted">-</span>',
                'kondisi' => $item->kondisi ? '<span class="badge bg-' . ($item->kondisi == 'Baik' ? 'success' : 'warning') . '">' . $item->kondisi . '</span>' : '<span class="text-muted">-</span>',
                'id' => $item->id,
            ];
        });

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $formattedData
        ]);
    }

    /**
     * Download template Excel untuk mapping PJA CCTV
     * Template ini berisi CCTV yang belum termapping dengan kolom PJA kosong untuk diisi
     */
    public function downloadTemplateMappingPja()
    {
        try {
            // Get all mapped CCTV dedicated values
            $mappedCctv = PjaCctvDedicated::distinct('cctv_dedicated')
                ->pluck('cctv_dedicated')
                ->filter()
                ->map(function($item) {
                    return trim($item);
                })
                ->toArray();

            // Get all CCTV data
            $allCctvData = CctvData::orderBy('no_cctv')->get();

            // Filter CCTV yang belum termapping
            $unmappedCctv = $allCctvData->filter(function($cctv) use ($mappedCctv) {
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
            })->values();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set title
            $sheet->setCellValue('A1', 'NO');
            $sheet->setCellValue('B1', 'CCTV');
            $sheet->setCellValue('C1', 'PJA');
            $sheet->setCellValue('D1', 'Keterangan');

            // Style header
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '28A745'], // Green untuk template
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ];
            $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

            // Data rows
            $rowNum = 2;
            foreach ($unmappedCctv as $index => $item) {
                $cctvValue = !empty($item->no_cctv) ? $item->no_cctv : ($item->nama_cctv ?? '');
                
                $sheet->setCellValue('A' . $rowNum, $index + 1);
                $sheet->setCellValue('B' . $rowNum, $cctvValue);
                $sheet->setCellValue('C' . $rowNum, ''); // PJA kosong untuk diisi
                $sheet->setCellValue('D' . $rowNum, 'Isi nama PJA di kolom ini');
                
                // Set style untuk kolom PJA (warna kuning untuk menunjukkan harus diisi)
                $sheet->getStyle('C' . $rowNum)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFFACD'); // Light yellow
                
                $rowNum++;
            }

            // Auto-size columns
            foreach (range('A', 'D') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Set column width untuk kolom PJA (make it wider)
            $sheet->getColumnDimension('C')->setWidth(40);

            // Add instruction sheet
            $instructionSheet = $spreadsheet->createSheet();
            $instructionSheet->setTitle('Petunjuk');
            $instructionSheet->setCellValue('A1', 'PETUNJUK PENGISIAN');
            $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $instructionSheet->setCellValue('A3', '1. Kolom NO: Nomor urut (otomatis)');
            $instructionSheet->setCellValue('A4', '2. Kolom CCTV: Nama CCTV yang belum termapping (jangan diubah)');
            $instructionSheet->setCellValue('A5', '3. Kolom PJA: Isi dengan nama PJA yang akan di-mapping ke CCTV tersebut');
            $instructionSheet->setCellValue('A6', '4. Kolom Keterangan: Opsional, untuk catatan');
            $instructionSheet->setCellValue('A8', 'CATATAN:');
            $instructionSheet->getStyle('A8')->getFont()->setBold(true);
            $instructionSheet->setCellValue('A9', '- Pastikan mengisi kolom PJA dengan benar');
            $instructionSheet->setCellValue('A10', '- Satu CCTV bisa memiliki beberapa PJA (buat baris baru untuk setiap PJA)');
            $instructionSheet->setCellValue('A11', '- Setelah selesai mengisi, simpan file dan upload melalui form upload');
            
            foreach (range('A', 'D') as $col) {
                $instructionSheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Set active sheet kembali ke sheet pertama
            $spreadsheet->setActiveSheetIndex(0);

            // Download file
            $writer = new Xlsx($spreadsheet);
            $filename = 'template_mapping_pja_cctv_' . date('Y-m-d_His') . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            Log::error('Error downloading template mapping PJA: ' . $e->getMessage());
            return redirect()->route('cctv-data.unmapped-cctv.index')
                ->with('error', 'Error generating template: ' . $e->getMessage());
        }
    }

    /**
     * Show form untuk upload Excel mapping PJA
     */
    public function importMappingPjaForm()
    {
        return view('cctv-data.import-mapping-pja');
    }

    /**
     * Import mapping PJA dari Excel
     */
    public function importMappingPja(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Baca file Excel/CSV
            if ($extension === 'csv') {
                $reader = IOFactory::createReader('Csv');
                $reader->setInputEncoding('UTF-8');
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $spreadsheet = $reader->load($file->getRealPath());
            } else {
                $spreadsheet = IOFactory::load($file->getRealPath());
            }
            
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (count($rows) < 2) {
                return back()->withErrors(['file' => 'File harus memiliki minimal header dan 1 baris data.']);
            }

            // Ambil header (baris pertama)
            $headers = array_map('trim', array_map('strtolower', $rows[0]));
            
            // Cari index kolom
            $noIndex = null;
            $cctvIndex = null;
            $pjaIndex = null;
            
            foreach ($headers as $index => $header) {
                $header = trim(strtolower($header));
                if (in_array($header, ['no', 'nomor', 'number'])) {
                    $noIndex = $index;
                } elseif (in_array($header, ['cctv', 'cctv dedicated', 'cctv_dedicated'])) {
                    $cctvIndex = $index;
                } elseif (in_array($header, ['pja', 'nama pja'])) {
                    $pjaIndex = $index;
                }
            }

            // Validasi kolom wajib
            if ($cctvIndex === null || $pjaIndex === null) {
                return back()->withErrors(['file' => 'File harus memiliki kolom: CCTV dan PJA.']);
            }

            // Proses data (mulai dari baris kedua)
            $successCount = 0;
            $errorCount = 0;
            $skippedCount = 0;
            $errors = [];

            DB::beginTransaction();
            
            try {
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    
                    // Skip baris kosong
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    // Ambil data dari Excel
                    $no = isset($row[$noIndex]) ? trim((string) $row[$noIndex]) : null;
                    $cctv = isset($row[$cctvIndex]) ? trim((string) $row[$cctvIndex]) : null;
                    $pja = isset($row[$pjaIndex]) ? trim((string) $row[$pjaIndex]) : null;

                    // Validasi data wajib
                    if (empty($cctv) || empty($pja)) {
                        $errorCount++;
                        $errors[] = "Baris " . ($i + 1) . ": CCTV dan PJA harus diisi.";
                        continue;
                    }

                    // Cek apakah data sudah ada (optional - bisa dihapus jika ingin allow duplicate)
                    $existing = PjaCctvDedicated::where('cctv_dedicated', $cctv)
                        ->where('pja', $pja)
                        ->first();

                    if ($existing) {
                        $skippedCount++;
                        continue;
                    }

                    // Simpan data
                    try {
                        PjaCctvDedicated::create([
                            'no' => $no,
                            'pja' => $pja,
                            'cctv_dedicated' => $cctv,
                        ]);
                        $successCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                        $errors[] = "Baris " . ($i + 1) . ": " . $e->getMessage();
                    }
                }

                DB::commit();

                $message = "Import berhasil! {$successCount} data mapping berhasil diimpor.";
                if ($skippedCount > 0) {
                    $message .= " {$skippedCount} data di-skip (sudah ada di database).";
                }
                if ($errorCount > 0) {
                    $message .= " {$errorCount} data gagal diimpor.";
                }

                return redirect()->route('cctv-data.import-mapping-pja-form')
                    ->with('success', $message)
                    ->with('import_errors', $errors);

            } catch (Exception $e) {
                DB::rollBack();
                return back()->withErrors(['file' => 'Error saat menyimpan data: ' . $e->getMessage()]);
            }

        } catch (Exception $e) {
            return back()->withErrors(['file' => 'Error processing file: ' . $e->getMessage()]);
        }
    }

    /**
     * Export unmapped CCTV data to Excel
     */
    public function exportUnmappedCctv()
    {
        try {
            // Get all mapped CCTV dedicated values
            $mappedCctv = PjaCctvDedicated::distinct('cctv_dedicated')
                ->pluck('cctv_dedicated')
                ->filter()
                ->map(function($item) {
                    return trim($item);
                })
                ->toArray();

            // Get all CCTV data
            $allCctvData = CctvData::orderBy('no_cctv')->get();

            // Filter CCTV yang belum termapping
            $unmappedCctv = $allCctvData->filter(function($cctv) use ($mappedCctv) {
                $noCctv = trim($cctv->no_cctv ?? '');
                $namaCctv = trim($cctv->nama_cctv ?? '');
                
                // Jika tidak ada no_cctv dan nama_cctv, skip
                if (empty($noCctv) && empty($namaCctv)) {
                    return false;
                }
                
                foreach ($mappedCctv as $mapped) {
                    $mappedTrimmed = trim($mapped);
                    if (empty($mappedTrimmed)) continue;
                    
                    // Exact match
                    if ($noCctv === $mappedTrimmed || $namaCctv === $mappedTrimmed) {
                        return false;
                    }
                    
                    // Partial match (contains)
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
            })->values();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set title
            $sheet->setCellValue('A1', 'NO');
            $sheet->setCellValue('B1', 'PJA');
            $sheet->setCellValue('C1', 'CCTV Dedicated');
            $sheet->setCellValue('D1', 'Site');
            $sheet->setCellValue('E1', 'Perusahaan');
            $sheet->setCellValue('F1', 'Nama CCTV');
            $sheet->setCellValue('G1', 'Status');
            $sheet->setCellValue('H1', 'Kondisi');

            // Style header
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DC3545'], // Red untuk unmapped
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ];
            $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

            // Add instruction row
            $sheet->setCellValue('A2', 'INSTRUKSI:');
            $sheet->mergeCells('A2:H2');
            $sheet->getStyle('A2')->getFont()->setBold(true);
            $sheet->setCellValue('A3', '1. Isi kolom NO (opsional)');
            $sheet->mergeCells('A3:H3');
            $sheet->setCellValue('A4', '2. Isi kolom PJA dengan nama PJA yang akan di-mapping ke CCTV');
            $sheet->mergeCells('A4:H4');
            $sheet->setCellValue('A5', '3. Kolom CCTV Dedicated sudah terisi (gunakan no_cctv atau nama_cctv)');
            $sheet->mergeCells('A5:H5');
            $sheet->setCellValue('A6', '4. Setelah selesai mengisi, simpan dan upload file ini kembali');
            $sheet->mergeCells('A6:H6');

            // Data rows (mulai dari baris 8)
            $rowNum = 8;
            foreach ($unmappedCctv as $item) {
                // NO - kosong untuk diisi user
                $sheet->setCellValue('A' . $rowNum, '');
                // PJA - kosong untuk diisi user
                $sheet->setCellValue('B' . $rowNum, '');
                // CCTV Dedicated - gunakan no_cctv jika ada, jika tidak gunakan nama_cctv
                $cctvDedicated = !empty($item->no_cctv) ? $item->no_cctv : ($item->nama_cctv ?? '');
                $sheet->setCellValue('C' . $rowNum, $cctvDedicated);
                // Data lainnya untuk referensi
                $sheet->setCellValue('D' . $rowNum, $item->site ?? '');
                $sheet->setCellValue('E' . $rowNum, $item->perusahaan ?? '');
                $sheet->setCellValue('F' . $rowNum, $item->nama_cctv ?? '');
                $sheet->setCellValue('G' . $rowNum, $item->status ?? '');
                $sheet->setCellValue('H' . $rowNum, $item->kondisi ?? '');
                $rowNum++;
            }

            // Auto-size columns
            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Set column width untuk kolom PJA lebih lebar
            $sheet->getColumnDimension('B')->setWidth(40);
            $sheet->getColumnDimension('C')->setWidth(25);

            // Download file
            $writer = new Xlsx($spreadsheet);
            $filename = 'cctv_belum_termapping_pja_' . date('Y-m-d_His') . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            Log::error('Error exporting unmapped CCTV: ' . $e->getMessage());
            return redirect()->route('cctv-data.unmapped-cctv.index')
                ->with('error', 'Error generating export: ' . $e->getMessage());
        }
    }

    /**
     * Show form for uploading mapping PJA from Excel
     */
    public function importMappingForm()
    {
        return view('cctv-data.import-mapping-pja');
    }

    /**
     * Import mapping PJA from Excel file
     * Format Excel: NO, PJA, CCTV Dedicated, Site, Perusahaan, Nama CCTV, Status, Kondisi
     */
    public function importMapping(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Baca file Excel
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (count($rows) < 8) {
                return back()->withErrors(['file' => 'File tidak valid. Pastikan file adalah template yang didownload dari sistem.']);
            }

            // Skip baris instruksi (baris 2-6), mulai dari baris 8 (index 7)
            $dataRows = array_slice($rows, 7);
            
            if (count($dataRows) < 1) {
                return back()->withErrors(['file' => 'Tidak ada data untuk diimpor.']);
            }

            // Ambil header (baris pertama data, index 7)
            $headers = array_map('trim', $dataRows[0] ?? []);
            
            // Mapping kolom Excel ke field
            $columnMapping = [
                'no' => ['no', 'nomor', 'number'],
                'pja' => ['pja'],
                'cctv_dedicated' => ['cctv dedicated', 'cctv_dedicated', 'cctv'],
            ];

            // Cari index kolom untuk setiap field
            $columnIndexes = [];
            foreach ($columnMapping as $field => $possibleNames) {
                $columnIndexes[$field] = null;
                foreach ($headers as $index => $header) {
                    $headerLower = strtolower(trim($header));
                    foreach ($possibleNames as $possibleName) {
                        if ($headerLower === strtolower($possibleName)) {
                            $columnIndexes[$field] = $index;
                            break 2;
                        }
                    }
                }
            }

            // Validasi kolom wajib
            if ($columnIndexes['pja'] === null || $columnIndexes['cctv_dedicated'] === null) {
                return back()->withErrors(['file' => 'File harus memiliki kolom: PJA dan CCTV Dedicated. Pastikan menggunakan template yang didownload dari sistem.']);
            }

            // Proses data (mulai dari baris kedua setelah header)
            $successCount = 0;
            $errorCount = 0;
            $skippedCount = 0;
            $errors = [];

            DB::beginTransaction();
            
            try {
                for ($i = 1; $i < count($dataRows); $i++) {
                    $row = $dataRows[$i];
                    
                    // Skip baris kosong
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    // Ambil data dari Excel
                    $no = isset($row[$columnIndexes['no']]) ? trim((string) $row[$columnIndexes['no']]) : null;
                    $pja = isset($row[$columnIndexes['pja']]) ? trim((string) $row[$columnIndexes['pja']]) : null;
                    $cctvDedicated = isset($row[$columnIndexes['cctv_dedicated']]) ? trim((string) $row[$columnIndexes['cctv_dedicated']]) : null;

                    // Validasi data wajib
                    if (empty($pja) || empty($cctvDedicated)) {
                        $errorCount++;
                        $errors[] = "Baris " . ($i + 8) . ": PJA dan CCTV Dedicated harus diisi.";
                        continue;
                    }

                    // Cek apakah data sudah ada (optional - bisa dihapus jika ingin allow duplicate)
                    $existing = PjaCctvDedicated::where('pja', $pja)
                        ->where('cctv_dedicated', $cctvDedicated)
                        ->first();

                    if ($existing) {
                        $skippedCount++;
                        continue;
                    }

                    // Simpan data
                    try {
                        PjaCctvDedicated::create([
                            'no' => $no,
                            'pja' => $pja,
                            'cctv_dedicated' => $cctvDedicated,
                        ]);
                        $successCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                        $errors[] = "Baris " . ($i + 8) . ": " . $e->getMessage();
                    }
                }

                DB::commit();

                $message = "Import berhasil! {$successCount} data mapping berhasil diimpor.";
                if ($skippedCount > 0) {
                    $message .= " {$skippedCount} data di-skip (sudah ada di database).";
                }
                if ($errorCount > 0) {
                    $message .= " {$errorCount} data gagal diimpor.";
                }

                return redirect()->route('cctv-data.unmapped-cctv.index')
                    ->with('success', $message)
                    ->with('import_errors', $errors);

            } catch (Exception $e) {
                DB::rollBack();
                return back()->withErrors(['file' => 'Error saat menyimpan data: ' . $e->getMessage()]);
            }

        } catch (Exception $e) {
            return back()->withErrors(['file' => 'Error processing file: ' . $e->getMessage()]);
        }
    }

    /**
     * Display CCTV Control Room page
     */
    public function indexControlRoom()
    {
        // Get statistics for dashboard
        // Use TRIM to handle spaces and ensure accurate counting
        $totalControlRooms = CctvData::whereNotNull('control_room')
            ->whereRaw("TRIM(COALESCE(control_room, '')) != ''")
            ->distinct('control_room')
            ->count('control_room');
        
        // Count all CCTV (total should be 2038)
        $totalCctvInControlRooms = CctvData::count();
        
        $cctvBaikInControlRooms = CctvData::where('kondisi', 'Baik')->count();
        
        $cctvRusakInControlRooms = CctvData::where('kondisi', 'Rusak')->count();
        
        $cctvLiveInControlRooms = CctvData::where('status', 'Live View')->count();
        
        $totalPengawas = CctvControlRoomPengawas::distinct('nama_pengawas')->count('nama_pengawas');
        
        // Distribution by control room
        $distributionByControlRoom = CctvData::select('control_room', DB::raw('COUNT(*) as count'))
            ->whereRaw("TRIM(COALESCE(control_room, '')) != ''")
            ->groupBy('control_room')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
        
        // Distribution by site (untuk control room)
        $distributionBySite = CctvData::select('site', DB::raw('COUNT(DISTINCT control_room) as count'))
            ->whereRaw("TRIM(COALESCE(control_room, '')) != ''")
            ->whereNotNull('site')
            ->where('site', '!=', '')
            ->groupBy('site')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
        
        // Distribution by perusahaan (untuk control room)
        $distributionByPerusahaan = CctvData::select('perusahaan', DB::raw('COUNT(DISTINCT control_room) as count'))
            ->whereRaw("TRIM(COALESCE(control_room, '')) != ''")
            ->whereNotNull('perusahaan')
            ->where('perusahaan', '!=', '')
            ->groupBy('perusahaan')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
        
        // Distribution by kondisi (untuk semua CCTV)
        $distributionByKondisi = CctvData::select('kondisi', DB::raw('COUNT(*) as count'))
            ->whereNotNull('kondisi')
            ->where('kondisi', '!=', '')
            ->groupBy('kondisi')
            ->orderByDesc('count')
            ->get();
        
        // Distribution by status (untuk semua CCTV)
        $distributionByStatus = CctvData::select('status', DB::raw('COUNT(*) as count'))
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();
        
        // Control rooms dengan pengawas
        $controlRoomsWithPengawas = CctvControlRoomPengawas::distinct('control_room')->count('control_room');
        
        $stats = [
            'total_control_rooms' => $totalControlRooms,
            'total_cctv' => $totalCctvInControlRooms,
            'cctv_baik' => $cctvBaikInControlRooms,
            'cctv_rusak' => $cctvRusakInControlRooms,
            'cctv_live' => $cctvLiveInControlRooms,
            'total_pengawas' => $totalPengawas,
            'control_rooms_with_pengawas' => $controlRoomsWithPengawas,
            'distribution_by_control_room' => $distributionByControlRoom,
            'distribution_by_site' => $distributionBySite,
            'distribution_by_perusahaan' => $distributionByPerusahaan,
            'distribution_by_kondisi' => $distributionByKondisi,
            'distribution_by_status' => $distributionByStatus,
        ];
        
        return view('cctv-data.control-room', compact('stats'));
    }

    /**
     * Get CCTV Control Room data for DataTable (server-side processing)
     * Grouped by Control Room
     */
    public function getControlRoomData(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get('start', 0);
        $length = $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';
        $orderColumn = $request->get('order')[0]['column'] ?? 0;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        // Column mapping (sesuai urutan kolom di DataTable: #, Control Room, Site, Perusahaan, Jumlah CCTV, Daftar CCTV, Pengawas, Actions)
        $columns = ['control_room', 'control_room', 'site', 'perusahaan', 'cctv_count', 'cctv_list', 'pengawas', 'actions'];
        // Jika kolom pertama (#) yang di-order, gunakan control_room sebagai gantinya
        if ($orderColumn == 0) {
            $orderColumnName = 'control_room';
        } else {
            $orderColumnName = $columns[$orderColumn] ?? 'control_room';
        }

        // Base query - ambil semua data dulu untuk grouping
        $query = CctvData::whereNotNull('control_room')
            ->where('control_room', '!=', '');

        // Search functionality
        if (!empty($searchValue)) {
            $query->where(function($q) use ($searchValue) {
                $q->where('control_room', 'like', '%' . $searchValue . '%')
                  ->orWhere('site', 'like', '%' . $searchValue . '%')
                  ->orWhere('perusahaan', 'like', '%' . $searchValue . '%');
            });
        }

        // Get all CCTV data
        $allCctvData = $query->get();

        // Group by control_room
        $groupedData = $allCctvData->groupBy('control_room');

        // Get pengawas data - group by control_room
        $pengawasData = CctvControlRoomPengawas::all()->groupBy('control_room');

        // Format grouped data
        $formattedGrouped = $groupedData->map(function($items, $controlRoom) use ($pengawasData) {
            $cctvList = $items->map(function($item) {
                return [
                    'id' => $item->id,
                    'no_cctv' => $item->no_cctv ?? '-',
                    'nama_cctv' => $item->nama_cctv ?? '-',
                    'site' => $item->site ?? '-',
                    'perusahaan' => $item->perusahaan ?? '-',
                ];
            })->values();
            
            $pengawasList = $pengawasData->get($controlRoom, collect())->map(function($pengawas) {
                return [
                    'id' => $pengawas->id,
                    'nama_pengawas' => $pengawas->nama_pengawas,
                    'email_pengawas' => $pengawas->email_pengawas,
                    'no_hp_pengawas' => $pengawas->no_hp_pengawas,
                    'keterangan' => $pengawas->keterangan,
                ];
            })->values()->toArray();
            
            return [
                'control_room' => $controlRoom,
                'cctv_list' => $cctvList->toArray(),
                'cctv_count' => $cctvList->count(),
                'pengawas_list' => $pengawasList,
                'pengawas_count' => count($pengawasList),
                'site' => $items->first()->site ?? '-',
                'perusahaan' => $items->first()->perusahaan ?? '-',
            ];
        })->values();

        // Apply search on grouped data
        if (!empty($searchValue)) {
            $formattedGrouped = $formattedGrouped->filter(function($item) use ($searchValue) {
                $searchLower = strtolower($searchValue);
                $matchControlRoom = str_contains(strtolower($item['control_room']), $searchLower);
                $matchSite = str_contains(strtolower($item['site']), $searchLower);
                $matchPerusahaan = str_contains(strtolower($item['perusahaan']), $searchLower);
                
                // Check if any pengawas matches
                $matchPengawas = false;
                if (!empty($item['pengawas_list'])) {
                    foreach ($item['pengawas_list'] as $pengawas) {
                        if (str_contains(strtolower($pengawas['nama_pengawas'] ?? ''), $searchLower) ||
                            str_contains(strtolower($pengawas['email_pengawas'] ?? ''), $searchLower)) {
                            $matchPengawas = true;
                            break;
                        }
                    }
                }
                
                return $matchControlRoom || $matchSite || $matchPerusahaan || $matchPengawas;
            })->values();
        }

        // Get total records (jumlah unique control room) - tanpa filter search
        $baseQuery = CctvData::whereNotNull('control_room')
            ->where('control_room', '!=', '');
        
        $allBaseData = $baseQuery->get();
        $baseGrouped = $allBaseData->groupBy('control_room');
        $recordsTotal = $baseGrouped->count();
        
        $recordsFiltered = $formattedGrouped->count();

        // Sort grouped data
        $sortedData = $formattedGrouped->sortBy(function($item) use ($orderColumnName, $orderDir) {
            if ($orderColumnName === 'cctv_count') {
                return $item['cctv_count'] ?? 0;
            } elseif ($orderColumnName === 'pengawas') {
                // Sort by first pengawas name or pengawas count
                if (!empty($item['pengawas_list']) && count($item['pengawas_list']) > 0) {
                    return strtolower($item['pengawas_list'][0]['nama_pengawas'] ?? '');
                }
                return '';
            } elseif (isset($item[$orderColumnName])) {
                $value = $item[$orderColumnName];
                return is_numeric($value) ? (float)$value : strtolower($value);
            }
            return '';
        }, SORT_REGULAR, $orderDir === 'desc');

        // Paginate
        $paginatedData = $sortedData->slice($start, $length)->values();

        // Format data for DataTable
        $formattedData = $paginatedData->map(function($item, $index) use ($start) {
            // Format CCTV list dengan line break
            $cctvDisplay = '';
            if (!empty($item['cctv_list']) && count($item['cctv_list']) > 0) {
                $cctvDisplay = '<div style="max-width: 400px; line-height: 1.8;">';
                foreach ($item['cctv_list'] as $idx => $cctv) {
                    $cctvDisplay .= '<div class="mb-1 small">' . 
                        '<span class="badge bg-secondary me-1">' . ($idx + 1) . '</span>' .
                        htmlspecialchars($cctv['no_cctv']) . 
                        ' - ' . htmlspecialchars($cctv['nama_cctv']) . 
                        '</div>';
                }
                $cctvDisplay .= '</div>';
            } else {
                $cctvDisplay = '<span class="text-muted">-</span>';
            }

            // Format pengawas - multiple pengawas dengan tombol delete
            $pengawasDisplay = '-';
            if (!empty($item['pengawas_list']) && count($item['pengawas_list']) > 0) {
                $pengawasDisplay = '<div style="max-width: 350px;">';
                foreach ($item['pengawas_list'] as $idx => $pengawas) {
                    $pengawasDisplay .= '<div class="mb-2 p-2 border rounded" style="background-color: #f8f9fa;" data-pengawas-id="' . $pengawas['id'] . '">';
                    $pengawasDisplay .= '<div class="d-flex justify-content-between align-items-start mb-1">';
                    $pengawasDisplay .= '<div class="flex-grow-1">';
                    $pengawasDisplay .= '<div class="fw-bold small">' . ($idx + 1) . '. ' . htmlspecialchars($pengawas['nama_pengawas']) . '</div>';
                    if (!empty($pengawas['email_pengawas'])) {
                        $pengawasDisplay .= '<div class="small text-muted">' . htmlspecialchars($pengawas['email_pengawas']) . '</div>';
                    }
                    if (!empty($pengawas['no_hp_pengawas'])) {
                        $pengawasDisplay .= '<div class="small text-muted">' . htmlspecialchars($pengawas['no_hp_pengawas']) . '</div>';
                    }
                    if (!empty($pengawas['keterangan'])) {
                        $pengawasDisplay .= '<div class="small text-muted mt-1"><em>' . htmlspecialchars($pengawas['keterangan']) . '</em></div>';
                    }
                    $pengawasDisplay .= '</div>';
                    $pengawasDisplay .= '<button type="button" class="btn btn-sm btn-danger btn-delete-pengawas ms-2" ' .
                        'data-pengawas-id="' . $pengawas['id'] . '" ' .
                        'data-control-room="' . htmlspecialchars($item['control_room']) . '" ' .
                        'title="Hapus Pengawas" style="flex-shrink: 0;">' .
                        '<i class="material-icons-outlined" style="font-size: 16px;">delete</i></button>';
                    $pengawasDisplay .= '</div>';
                    $pengawasDisplay .= '</div>';
                }
                $pengawasDisplay .= '</div>';
            }

            return [
                'DT_RowIndex' => $start + $index + 1,
                'control_room' => $item['control_room'] ?? '-',
                'site' => $item['site'] ?? '-',
                'perusahaan' => $item['perusahaan'] ?? '-',
                'cctv_count' => '<span class="badge bg-primary">' . ($item['cctv_count'] ?? 0) . '</span>',
                'cctv_list' => $cctvDisplay,
                'pengawas' => $pengawasDisplay,
                'pengawas_list' => $item['pengawas_list'] ?? [],
                'pengawas_count' => $item['pengawas_count'] ?? 0,
                'control_room_raw' => $item['control_room'] ?? '',
                'actions' => '', // Akan diisi di drawCallback
            ];
        });

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $formattedData
        ]);
    }

    /**
     * Export data Pengawas Control Room (cctv_control_room_pengawas) ke Excel
     */
    public function exportControlRoomPengawas()
    {
        try {
            $data = CctvControlRoomPengawas::orderBy('control_room')->orderBy('nama_pengawas')->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['No', 'Control Room', 'Nama Pengawas', 'Email', 'No HP', 'Keterangan', 'Created At', 'Updated At'];
            $col = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue($col . '1', $h);
                $col++;
            }
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            ];
            $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

            $rowNum = 2;
            foreach ($data as $index => $item) {
                $sheet->setCellValue('A' . $rowNum, $index + 1);
                $sheet->setCellValue('B' . $rowNum, $item->control_room ?? '');
                $sheet->setCellValue('C' . $rowNum, $item->nama_pengawas ?? '');
                $sheet->setCellValue('D' . $rowNum, $item->email_pengawas ?? '');
                $sheet->setCellValue('E' . $rowNum, $item->no_hp_pengawas ?? '');
                $sheet->setCellValue('F' . $rowNum, $item->keterangan ?? '');
                $sheet->setCellValue('G' . $rowNum, $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : '');
                $sheet->setCellValue('H' . $rowNum, $item->updated_at ? $item->updated_at->format('Y-m-d H:i:s') : '');
                $rowNum++;
            }
            foreach (range('A', 'H') as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'cctv_control_room_pengawas_' . date('Y-m-d_His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            Log::error('Error exporting Control Room Pengawas: ' . $e->getMessage());
            return redirect()->route('cctv-data.control-room.index')->with('error', 'Error generating export: ' . $e->getMessage());
        }
    }

    /**
     * Store pengawas control room (always create new)
     */
    public function storePengawasControlRoom(Request $request)
    {
        $validated = $request->validate([
            'control_room' => 'required|string|max:255',
            'nama_pengawas' => 'required|string|max:255',
            'email_pengawas' => 'nullable|email|max:255',
            'no_hp_pengawas' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string',
        ]);

        try {
            // Always create new pengawas (support multiple pengawas per control room)
            CctvControlRoomPengawas::create($validated);
            $message = 'Data pengawas control room berhasil ditambahkan.';

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            }

            return redirect()->route('cctv-data.control-room.index')
                ->with('success', $message);

        } catch (Exception $e) {
            Log::error('Error storing pengawas control room: ' . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat menyimpan data.'
                ], 500);
            }

            return back()->with('error', 'Terjadi kesalahan saat menyimpan data.')->withInput();
        }
    }

    /**
     * Get pengawas data for a control room (returns all pengawas)
     */
    public function getPengawasControlRoom($controlRoom)
    {
        try {
            $pengawasList = CctvControlRoomPengawas::where('control_room', $controlRoom)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($pengawas) {
                    return [
                        'id' => $pengawas->id,
                        'control_room' => $pengawas->control_room,
                        'nama_pengawas' => $pengawas->nama_pengawas,
                        'email_pengawas' => $pengawas->email_pengawas,
                        'no_hp_pengawas' => $pengawas->no_hp_pengawas,
                        'keterangan' => $pengawas->keterangan,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $pengawasList
            ]);

        } catch (Exception $e) {
            Log::error('Error getting pengawas control room: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.'
            ], 500);
        }
    }

    /**
     * Get users from ClickHouse for PIC selection (with search support for Select2 AJAX)
     */
    public function getUsersFromClickHouse(Request $request)
    {
        try {
            // Get search query from request (Select2 sends 'q' parameter)
            $searchQuery = $request->get('q', '');
            $page = $request->get('page', 1);
            $perPage = 20; // Limit results for better performance
            
            // Build query using Laravel Query Builder for MySQL
            $query = DB::table('vw_user')
                ->where('is_active', 1)
                ->whereNotNull('username')
                ->where('username', '!=', '')
                ->whereNotNull('nama')
                ->where('nama', '!=', '');
            
            // Add search condition if query provided
            if (!empty($searchQuery) && trim($searchQuery) !== '') {
                $searchTerm = trim($searchQuery);
                $query->where(function($q) use ($searchTerm) {
                    $q->where('username', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('nama', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            
            // Get total count for pagination
            $total = $query->count();
            
            // Apply pagination
            $users = $query->select('id', 'username', 'nama', 'email', 'selular', 'nik')
                ->orderBy('username', 'ASC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            // Format data for Select2 AJAX response
            $formattedUsers = [];
            foreach ($users as $user) {
                $username = trim($user->username ?? '');
                $nama = trim($user->nama ?? '');
                
                // Skip if username or nama is empty
                if (empty($username) || empty($nama)) {
                    continue;
                }
                
                $formattedUsers[] = [
                    'id' => (string)($user->id ?? ''),
                    'text' => $username . ' - ' . $nama, // Format: username - nama
                    'username' => $username,
                    'nama' => $nama,
                    'email' => $user->email ?? '',
                    'selular' => $user->selular ?? '',
                    'nik' => $user->nik ?? ''
                ];
            }

            // Return in Select2 AJAX format
            return response()->json([
                'results' => $formattedUsers,
                'pagination' => [
                    'more' => count($formattedUsers) >= $perPage // Indicate if more results available
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error getting users from MySQL: ' . $e->getMessage());
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CCTV list by control room
     */
    public function getCctvByControlRoom(Request $request)
    {
        try {
            $controlRoom = $request->get('control_room');
            
            if (!$controlRoom) {
                return response()->json([
                    'success' => false,
                    'message' => 'Control room is required',
                    'data' => []
                ], 400);
            }
            
            // Get CCTV data for the control room
            $cctvList = CctvData::where('control_room', $controlRoom)
                ->orderBy('nama_cctv')
                ->get(['id', 'no_cctv', 'nama_cctv', 'lokasi_pemasangan', 'status', 'kondisi'])
                ->map(function($cctv) {
                    return [
                        'id' => $cctv->id,
                        'no_cctv' => $cctv->no_cctv ?? 'CCTV-' . $cctv->id,
                        'nama_cctv' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                        'lokasi_pemasangan' => $cctv->lokasi_pemasangan ?? '',
                        'status' => $cctv->status ?? '',
                        'kondisi' => $cctv->kondisi ?? '',
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $cctvList
            ]);
            
        } catch (Exception $e) {
            Log::error('Error getting CCTV by control room: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data CCTV.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Delete pengawas control room
     */
    public function deletePengawasControlRoom($id)
    {
        try {
            $pengawas = CctvControlRoomPengawas::findOrFail($id);
            $pengawas->delete();

            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data pengawas berhasil dihapus.'
                ]);
            }

            return redirect()->route('cctv-data.control-room.index')
                ->with('success', 'Data pengawas berhasil dihapus.');

        } catch (Exception $e) {
            Log::error('Error deleting pengawas control room: ' . $e->getMessage());
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat menghapus data.'
                ], 500);
            }

            return back()->with('error', 'Terjadi kesalahan saat menghapus data.');
        }
    }

    /**
     * Store intervensi control room
     */
    public function storeIntervensiControlRoom(Request $request)
    {
        try {
            $validated = $request->validate([
                'control_room' => 'required|string|max:255',
                'cctv_ids' => 'required|array|min:1',
                'cctv_ids.*' => 'required|integer|exists:cctv_data_bmo2,id',
                'pic_id' => 'required|string',
                'issue' => 'required|string',
            ]);

            // Get authenticated user
            $user = Auth::user();
            $createdBy = $user ? $user->name : 'Unknown';
            $createdByEmail = $user ? $user->email : null;

            // Get PIC details from MySQL
            $picId = $validated['pic_id'];
            
            $picInfo = DB::table('vw_user')
                ->where('id', $picId)
                ->select('id', 'username', 'nama', 'selular')
                ->first();
            
            // Convert to array format for compatibility
            $picInfo = $picInfo ? [
                'id' => (string)($picInfo->id ?? ''),
                'username' => $picInfo->username ?? '',
                'nama' => $picInfo->nama ?? '',
                'selular' => $picInfo->selular ?? ''
            ] : null;

            // Get CCTV information for all selected CCTV
            $cctvIds = $validated['cctv_ids'];
            $cctvList = CctvData::whereIn('id', $cctvIds)->get();
            $cctvNames = $cctvList->map(function($cctv) {
                $name = $cctv->nama_cctv ?? ('CCTV ' . $cctv->id);
                if ($cctv->no_cctv) {
                    $name .= ' (' . $cctv->no_cctv . ')';
                }
                return $name;
            })->implode(', ');

            // Store intervensi (without cctv_id in main table, will use pivot table)
            $intervensi = IntervensiControlRoom::create([
                'control_room' => $validated['control_room'],
                'cctv_id' => null, // Keep nullable for backward compatibility, but use pivot table
                'pic_id' => $picId,
                'pic_username' => $picInfo['username'] ?? null,
                'pic_nama' => $picInfo['nama'] ?? null,
                'pic_telepon' => $picInfo['selular'] ?? null,
                'issue' => $validated['issue'],
                'status' => 'open', // Default status
                'status_done' => 'belum', // Default status_done (for backward compatibility)
                'created_by' => $createdBy,
                'created_by_email' => $createdByEmail,
            ]);

            // Store multiple CCTV in pivot table
            $pivotData = [];
            foreach ($cctvIds as $cctvId) {
                $pivotData[$cctvId] = [
                    'status_done' => 'belum',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            $intervensi->cctvs()->attach($pivotData);

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
                $pesan = "Form Intervensi Control Room\n\n";
                $pesan .= "Pelapor: " . $createdBy . "\n";
                $pesan .= "Control Room: " . $validated['control_room'] . "\n";
                $pesan .= "CCTV: " . $cctvNames . "\n";
                $pesan .= "PIC: " . ($picInfo['username'] ?? '') . " - " . ($picInfo['nama'] ?? '') . "\n";
                $pesan .= "Issue:\n" . $validated['issue'] . "\n\n";
                $pesan .= "Link: https://besentry-dev.beraucoal.co.id/cctv-data-control-room/intervensi";
                
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
            Log::error('Error storing intervensi control room: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan intervensi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display list of intervensi control room
     */
    public function indexIntervensiControlRoom()
    {
        return view('cctv-data.intervensi-control-room');
    }

    /**
     * Get intervensi control room data for DataTable
     */
    public function getIntervensiControlRoomData(Request $request)
    {
        try {
            $draw = $request->get('draw');
            $start = $request->get('start', 0);
            $length = $request->get('length', 10);
            $search = $request->get('search');
            $searchValue = isset($search['value']) ? $search['value'] : '';
            $order = $request->get('order');
            $orderColumn = (isset($order[0]['column'])) ? $order[0]['column'] : 0;
            $orderDir = (isset($order[0]['dir'])) ? $order[0]['dir'] : 'desc';

            // Column mapping - only map to actual database columns
            $columns = ['id', 'control_room', 'pic_username', 'pic_nama', 'issue', 'status', 'created_at'];
            $orderColumnName = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'created_at';
            
            // Ensure order direction is valid
            $orderDir = in_array(strtolower($orderDir), ['asc', 'desc']) ? strtolower($orderDir) : 'desc';

            // Base query - only open issues
            $query = IntervensiControlRoom::where('status', 'open');

            // Search functionality
            if (!empty($searchValue)) {
                $query->where(function($q) use ($searchValue) {
                    $q->where('control_room', 'like', '%' . $searchValue . '%')
                      ->orWhere('pic_username', 'like', '%' . $searchValue . '%')
                      ->orWhere('pic_nama', 'like', '%' . $searchValue . '%')
                      ->orWhere('issue', 'like', '%' . $searchValue . '%')
                      ->orWhere('status', 'like', '%' . $searchValue . '%');
                });
            }

            // Get total records
            try {
                $recordsTotal = IntervensiControlRoom::where('status', 'open')->count();
                $recordsFiltered = $query->count();
            } catch (\Exception $e) {
                Log::error('Error counting records: ' . $e->getMessage());
                $recordsTotal = 0;
                $recordsFiltered = 0;
            }

            // Order and paginate with eager loading
            // Use try-catch for eager loading in case relationship has issues
            try {
                $intervensiList = $query->with('cctvs')
                    ->orderBy($orderColumnName, $orderDir)
                    ->skip($start)
                    ->take($length)
                    ->get();
            } catch (\Exception $e) {
                // If eager loading fails, try without it
                Log::warning('Eager loading cctvs failed, loading without: ' . $e->getMessage());
                $intervensiList = $query->orderBy($orderColumnName, $orderDir)
                    ->skip($start)
                    ->take($length)
                    ->get();
            }

            // Format data for DataTable
            $formattedData = $intervensiList->map(function($intervensi, $index) use ($start) {
                try {
                    // Status badge
                    $statusBadge = '';
                    if ($intervensi->status === 'closed') {
                        $statusBadge = '<span class="badge bg-success">Closed</span>';
                    } else {
                        $statusBadge = '<span class="badge bg-warning">Open</span>';
                    }

                    // Get CCTV names from pivot table (many-to-many)
                    $cctvNames = '-';
                    try {
                        $cctvs = $intervensi->cctvs;
                        if ($cctvs && $cctvs->count() > 0) {
                            $cctvNames = $cctvs->map(function($cctv) {
                                $name = $cctv->nama_cctv ?? ('CCTV ' . $cctv->id);
                                if ($cctv->no_cctv) {
                                    $name .= ' (' . $cctv->no_cctv . ')';
                                }
                                return $name;
                            })->implode(', ');
                        } elseif ($intervensi->cctv_id) {
                            // Fallback untuk data lama (backward compatibility)
                            $cctv = CctvData::find($intervensi->cctv_id);
                            if ($cctv) {
                                $cctvNames = $cctv->nama_cctv ?? ('CCTV ' . $cctv->id);
                                if ($cctv->no_cctv) {
                                    $cctvNames .= ' (' . $cctv->no_cctv . ')';
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error loading CCTV for intervensi ' . $intervensi->id . ': ' . $e->getMessage());
                        $cctvNames = '-';
                    }

                    // Action buttons
                    $actions = '';
                    if ($intervensi->status === 'open') {
                        $actions = '<button class="btn btn-sm btn-success close-intervensi-btn" data-id="' . $intervensi->id . '" title="Close Issue">
                            <i class="material-icons-outlined" style="font-size: 16px;">check_circle</i> Close
                        </button>';
                    } else {
                        $actions = '<span class="text-muted">Closed</span>';
                    }

                    // Format tanggal pelaporan: "9 Jan 2026"
                    $tanggalPelaporan = '-';
                    if ($intervensi->created_at) {
                        $date = is_string($intervensi->created_at) ? \Carbon\Carbon::parse($intervensi->created_at) : $intervensi->created_at;
                        $tanggalPelaporan = $date->format('j M Y'); // Format: 9 Jan 2026
                    }

                    return [
                        'id' => $intervensi->id,
                        'control_room' => $intervensi->control_room ?? '-',
                        'cctv_name' => $cctvNames,
                        'pic_username' => $intervensi->pic_username ?? '-',
                        'pic_nama' => $intervensi->pic_nama ?? '-',
                        'issue' => '<div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' . htmlspecialchars($intervensi->issue ?? '') . '">' . htmlspecialchars($intervensi->issue ?? '') . '</div>',
                        'status' => $statusBadge,
                        'tanggal_pelaporan' => $tanggalPelaporan,
                        'actions' => $actions
                    ];
                } catch (\Exception $e) {
                    Log::error('Error formatting intervensi data for ID ' . ($intervensi->id ?? 'unknown') . ': ' . $e->getMessage());
                    return [
                        'id' => $intervensi->id ?? '-',
                        'control_room' => '-',
                        'cctv_name' => '-',
                        'pic_username' => '-',
                        'pic_nama' => '-',
                        'issue' => 'Error loading data',
                        'status' => '-',
                        'tanggal_pelaporan' => '-',
                        'actions' => '-'
                    ];
                }
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $formattedData
            ]);
        } catch (Exception $e) {
            Log::error('Error in getIntervensiControlRoomData: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request' => $request->all()
            ]);
            
            $errorMessage = 'Terjadi kesalahan saat memuat data.';
            if (config('app.debug')) {
                $errorMessage .= ' ' . $e->getMessage() . ' (File: ' . basename($e->getFile()) . ', Line: ' . $e->getLine() . ')';
            }
            
            return response()->json([
                'draw' => intval($request->get('draw', 0)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $errorMessage
            ], 500);
        }
    }

    /**
     * Get done intervensi control room data for DataTable
     */
    public function getDoneIntervensiControlRoomData(Request $request)
    {
        try {
            $draw = $request->get('draw');
            $start = $request->get('start', 0);
            $length = $request->get('length', 10);
            $search = $request->get('search');
            $searchValue = isset($search['value']) ? $search['value'] : '';
            $order = $request->get('order');
            $orderColumn = (isset($order[0]['column'])) ? $order[0]['column'] : 0;
            $orderDir = (isset($order[0]['dir'])) ? $order[0]['dir'] : 'desc';

            // Column mapping
            $columns = ['id', 'control_room', 'pic_username', 'pic_nama', 'issue', 'created_at', 'closed_at'];
            $orderColumnName = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'closed_at';
            
            // Ensure order direction is valid
            $orderDir = in_array(strtolower($orderDir), ['asc', 'desc']) ? strtolower($orderDir) : 'desc';

            // Base query - only closed issues
            $query = IntervensiControlRoom::where('status', 'closed');

            // Search functionality
            if (!empty($searchValue)) {
                $query->where(function($q) use ($searchValue) {
                    $q->where('control_room', 'like', '%' . $searchValue . '%')
                      ->orWhere('pic_username', 'like', '%' . $searchValue . '%')
                      ->orWhere('pic_nama', 'like', '%' . $searchValue . '%')
                      ->orWhere('issue', 'like', '%' . $searchValue . '%');
                });
            }

            // Get total records
            try {
                $recordsTotal = IntervensiControlRoom::where('status', 'closed')->count();
                $recordsFiltered = $query->count();
            } catch (\Exception $e) {
                Log::error('Error counting done records: ' . $e->getMessage());
                $recordsTotal = 0;
                $recordsFiltered = 0;
            }

            // Order and paginate with eager loading
            try {
                $intervensiList = $query->with('cctvs')
                    ->orderBy($orderColumnName, $orderDir)
                    ->skip($start)
                    ->take($length)
                    ->get();
            } catch (\Exception $e) {
                Log::warning('Eager loading cctvs failed, loading without: ' . $e->getMessage());
                $intervensiList = $query->orderBy($orderColumnName, $orderDir)
                    ->skip($start)
                    ->take($length)
                    ->get();
            }

            // Format data for DataTable
            $formattedData = $intervensiList->map(function($intervensi, $index) use ($start) {
                try {
                    // Get CCTV names from pivot table (many-to-many)
                    $cctvNames = '-';
                    try {
                        $cctvs = $intervensi->cctvs;
                        if ($cctvs && $cctvs->count() > 0) {
                            $cctvNames = $cctvs->map(function($cctv) {
                                $name = $cctv->nama_cctv ?? ('CCTV ' . $cctv->id);
                                if ($cctv->no_cctv) {
                                    $name .= ' (' . $cctv->no_cctv . ')';
                                }
                                return $name;
                            })->implode(', ');
                        } elseif ($intervensi->cctv_id) {
                            $cctv = CctvData::find($intervensi->cctv_id);
                            if ($cctv) {
                                $cctvNames = $cctv->nama_cctv ?? ('CCTV ' . $cctv->id);
                                if ($cctv->no_cctv) {
                                    $cctvNames .= ' (' . $cctv->no_cctv . ')';
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error loading CCTV for intervensi ' . $intervensi->id . ': ' . $e->getMessage());
                        $cctvNames = '-';
                    }

                    // Format tanggal pelaporan: "9 Jan 2026"
                    $tanggalPelaporan = '-';
                    if ($intervensi->created_at) {
                        $date = is_string($intervensi->created_at) ? \Carbon\Carbon::parse($intervensi->created_at) : $intervensi->created_at;
                        $tanggalPelaporan = $date->format('j M Y');
                    }

                    // Format tanggal selesai: "9 Jan 2026"
                    $tanggalSelesai = '-';
                    if ($intervensi->closed_at) {
                        $date = is_string($intervensi->closed_at) ? \Carbon\Carbon::parse($intervensi->closed_at) : $intervensi->closed_at;
                        $tanggalSelesai = $date->format('j M Y');
                    }

                    return [
                        'id' => $intervensi->id,
                        'control_room' => $intervensi->control_room ?? '-',
                        'cctv_name' => $cctvNames,
                        'pic_username' => $intervensi->pic_username ?? '-',
                        'pic_nama' => $intervensi->pic_nama ?? '-',
                        'issue' => '<div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' . htmlspecialchars($intervensi->issue ?? '') . '">' . htmlspecialchars($intervensi->issue ?? '') . '</div>',
                        'tanggal_pelaporan' => $tanggalPelaporan,
                        'tanggal_selesai' => $tanggalSelesai,
                        'actions' => '<button class="btn btn-sm btn-light border view-done-detail-btn" data-id="' . $intervensi->id . '" title="Lihat Detail">
                            <i class="material-icons-outlined" style="font-size: 16px;">visibility</i> Detail
                        </button>'
                    ];
                } catch (\Exception $e) {
                    Log::error('Error formatting done intervensi data for ID ' . ($intervensi->id ?? 'unknown') . ': ' . $e->getMessage());
                    return [
                        'id' => $intervensi->id ?? '-',
                        'control_room' => '-',
                        'cctv_name' => '-',
                        'pic_username' => '-',
                        'pic_nama' => '-',
                        'issue' => 'Error loading data',
                        'tanggal_pelaporan' => '-',
                        'tanggal_selesai' => '-',
                        'actions' => '-'
                    ];
                }
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $formattedData
            ]);
        } catch (Exception $e) {
            Log::error('Error in getDoneIntervensiControlRoomData: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request' => $request->all()
            ]);
            
            $errorMessage = 'Terjadi kesalahan saat memuat data.';
            if (config('app.debug')) {
                $errorMessage .= ' ' . $e->getMessage() . ' (File: ' . basename($e->getFile()) . ', Line: ' . $e->getLine() . ')';
            }
            
            return response()->json([
                'draw' => intval($request->get('draw', 0)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $errorMessage
            ], 500);
        }
    }

    /**
     * Get detail done intervensi for card display
     */
    public function getDoneIntervensiDetail($id)
    {
        try {
            $intervensi = IntervensiControlRoom::with('cctvs')->where('status', 'closed')->findOrFail($id);
            
            // Format CCTV data with resolution and evidence
            $cctvList = $intervensi->cctvs->map(function($cctv) {
                $cctvName = $cctv->nama_cctv ?? ('CCTV ' . $cctv->id);
                if ($cctv->no_cctv) {
                    $cctvName .= ' (' . $cctv->no_cctv . ')';
                }
                
                return [
                    'id' => $cctv->id,
                    'nama_cctv' => $cctvName,
                    'no_cctv' => $cctv->no_cctv ?? null,
                    'status_done' => isset($cctv->pivot->status_done) ? $cctv->pivot->status_done : 'belum',
                    'resolution' => isset($cctv->pivot->resolution) ? $cctv->pivot->resolution : null,
                    'evidence_path' => isset($cctv->pivot->evidence_path) ? $cctv->pivot->evidence_path : null,
                ];
            });
            
            // Format dates
            $tanggalPelaporan = $intervensi->created_at ? (is_string($intervensi->created_at) ? \Carbon\Carbon::parse($intervensi->created_at) : $intervensi->created_at)->format('j M Y') : '-';
            $tanggalSelesai = $intervensi->closed_at ? (is_string($intervensi->closed_at) ? \Carbon\Carbon::parse($intervensi->closed_at) : $intervensi->closed_at)->format('j M Y, H:i') : '-';
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $intervensi->id,
                    'control_room' => $intervensi->control_room,
                    'issue' => $intervensi->issue,
                    'resolution' => $intervensi->resolution,
                    'evidence_path' => $intervensi->evidence_path,
                    'pic_username' => $intervensi->pic_username,
                    'pic_nama' => $intervensi->pic_nama,
                    'pic_telepon' => $intervensi->pic_telepon,
                    'created_by' => $intervensi->created_by,
                    'created_by_email' => $intervensi->created_by_email,
                    'closed_by' => $intervensi->closed_by,
                    'tanggal_pelaporan' => $tanggalPelaporan,
                    'tanggal_selesai' => $tanggalSelesai,
                    'cctvs' => $cctvList
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error getting done intervensi detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data intervensi.'
            ], 500);
        }
    }

    /**
     * Get detail intervensi with CCTV list for close form
     */
    public function getIntervensiDetail($id)
    {
        try {
            $intervensi = IntervensiControlRoom::with('cctvs')->findOrFail($id);
            
            $cctvList = $intervensi->cctvs->map(function($cctv) {
                return [
                    'id' => $cctv->id,
                    'nama_cctv' => $cctv->nama_cctv ?? ('CCTV ' . $cctv->id),
                    'no_cctv' => $cctv->no_cctv ?? null,
                    'status_done' => $cctv->pivot->status_done ?? 'belum',
                    'resolution' => isset($cctv->pivot->resolution) ? $cctv->pivot->resolution : null,
                    'evidence_path' => isset($cctv->pivot->evidence_path) ? $cctv->pivot->evidence_path : null,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $intervensi->id,
                    'control_room' => $intervensi->control_room,
                    'issue' => $intervensi->issue,
                    'resolution' => $intervensi->resolution,
                    'evidence_path' => $intervensi->evidence_path,
                    'status' => $intervensi->status,
                    'cctvs' => $cctvList
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error getting intervensi detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data intervensi.'
            ], 500);
        }
    }

    /**
     * Update status of intervensi control room
     */
    public function updateIntervensiStatus(Request $request, $id)
    {
        try {
            // Validate basic fields first
            $validated = $request->validate([
                'status' => 'required|in:open,closed',
                'resolution' => 'nullable|string',
                'evidence' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240', // Max 10MB
                'cctv_resolutions' => 'nullable|array',
            ]);
            
            // Validate CCTV resolutions if provided
            if ($request->has('cctv_resolutions') && is_array($request->input('cctv_resolutions'))) {
                foreach ($request->input('cctv_resolutions') as $index => $cctvData) {
                    $request->validate([
                        'cctv_resolutions.' . $index . '.cctv_id' => 'required|exists:cctv_data_bmo2,id',
                        'cctv_resolutions.' . $index . '.status_done' => 'nullable|in:belum,sudah',
                        'cctv_resolutions.' . $index . '.resolution' => 'nullable|string',
                    ]);
                    
                    // Validate file if exists
                    if ($request->hasFile('cctv_resolutions.' . $index . '.evidence')) {
                        $request->validate([
                            'cctv_resolutions.' . $index . '.evidence' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
                        ]);
                    }
                }
            }

            $intervensi = IntervensiControlRoom::with('cctvs')->findOrFail($id);
            
            // Get authenticated user
            $user = Auth::user();
            $closedBy = $user ? $user->name : 'Unknown';

            $intervensi->status = $validated['status'];
            
            if ($validated['status'] === 'closed') {
                $intervensi->closed_at = now();
                $intervensi->closed_by = $closedBy;
                
                // Handle resolution
                if (isset($validated['resolution'])) {
                    $intervensi->resolution = $validated['resolution'];
                }
                
                // Handle main evidence file
                if ($request->hasFile('evidence')) {
                    $evidenceFile = $request->file('evidence');
                    $evidencePath = $evidenceFile->store('intervensi/evidence', 'public');
                    $intervensi->evidence_path = $evidencePath;
                }
                
                // Handle CCTV-specific resolutions and evidence
                if ($request->has('cctv_resolutions') && is_array($request->input('cctv_resolutions'))) {
                    foreach ($request->input('cctv_resolutions') as $index => $cctvData) {
                        $cctvId = $cctvData['cctv_id'];
                        $updateData = [];
                        
                        if (isset($cctvData['status_done'])) {
                            $updateData['status_done'] = $cctvData['status_done'];
                        }
                        
                        if (isset($cctvData['resolution'])) {
                            $updateData['resolution'] = $cctvData['resolution'];
                        }
                        
                        // Handle evidence file for this CCTV
                        $evidenceKey = 'cctv_resolutions.' . $index . '.evidence';
                        if ($request->hasFile($evidenceKey)) {
                            $evidenceFile = $request->file($evidenceKey);
                            $evidencePath = $evidenceFile->store('intervensi/cctv-evidence', 'public');
                            $updateData['evidence_path'] = $evidencePath;
                        }
                        
                        // Update pivot table
                        if (!empty($updateData)) {
                            $intervensi->cctvs()->updateExistingPivot($cctvId, $updateData);
                        }
                    }
                }
            } else {
                $intervensi->closed_at = null;
                $intervensi->closed_by = null;
                $intervensi->resolution = null;
                // Don't delete evidence files when reopening, just clear the path
                $intervensi->evidence_path = null;
            }
            
            $intervensi->save();

            return response()->json([
                'success' => true,
                'message' => 'Status intervensi berhasil diupdate.'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal: ' . implode(', ', $e->errors())
            ], 422);
        } catch (Exception $e) {
            Log::error('Error updating intervensi status: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update status_done of intervensi control room
     */
    public function updateIntervensiStatusDone(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status_done' => 'required|in:belum,sudah'
            ]);

            $intervensi = IntervensiControlRoom::findOrFail($id);
            
            // Update status_done di intervensi (for backward compatibility)
            $intervensi->status_done = $validated['status_done'];
            $intervensi->save();
            
            // Update status_done di pivot table untuk semua CCTV dalam intervensi ini
            if ($intervensi->cctvs()->count() > 0) {
                DB::table('intervensi_control_room_cctv')
                    ->where('intervensi_id', $id)
                    ->update([
                        'status_done' => $validated['status_done'],
                        'updated_at' => now()
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Status done berhasil diupdate untuk semua CCTV.'
            ]);

        } catch (Exception $e) {
            Log::error('Error updating intervensi status_done: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate status done.'
            ], 500);
        }
    }

    /**
     * Export daftar issue intervensi control room (open) ke Excel.
     */
    public function exportIntervensiControlRoomExcel(Request $request)
    {
        try {
            $headers = [
                'No', 'ID', 'Control Room', 'CCTV', 'SID PIC', 'Nama PIC', 'No HP PIC',
                'Issue', 'Status', 'Tanggal Pelaporan', 'Created By', 'Created By Email', 'Updated At',
            ];

            $query = IntervensiControlRoom::where('status', 'open');

            if ($request->filled('search')) {
                $searchValue = $request->search;
                $query->where(function ($q) use ($searchValue) {
                    $q->where('control_room', 'like', '%' . $searchValue . '%')
                        ->orWhere('pic_username', 'like', '%' . $searchValue . '%')
                        ->orWhere('pic_nama', 'like', '%' . $searchValue . '%')
                        ->orWhere('issue', 'like', '%' . $searchValue . '%');
                });
            }

            $intervensiList = $query->with('cctvs')->orderByDesc('created_at')->get();

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders($headers);
            $sheet = $spreadsheet->getActiveSheet();

            $rowNum = 2;
            foreach ($intervensiList as $index => $intervensi) {
                $sheet->fromArray([
                    $index + 1,
                    $intervensi->id,
                    $intervensi->control_room ?? '',
                    $this->formatIntervensiCctvNamesForExport($intervensi),
                    $intervensi->pic_username ?? '',
                    $intervensi->pic_nama ?? '',
                    $intervensi->pic_telepon ?? '',
                    $intervensi->issue ?? '',
                    $intervensi->status ?? '',
                    $intervensi->created_at ? $intervensi->created_at->format('Y-m-d H:i:s') : '',
                    $intervensi->created_by ?? '',
                    $intervensi->created_by_email ?? '',
                    $intervensi->updated_at ? $intervensi->updated_at->format('Y-m-d H:i:s') : '',
                ], null, 'A' . $rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download($spreadsheet, 'intervensi_control_room_open_' . date('Y-m-d_His') . '.xlsx');
        } catch (Exception $e) {
            Log::error('Error exporting open intervensi control room: ' . $e->getMessage());

            return redirect()
                ->route('cctv-data.intervensi-control-room.index')
                ->with('error', 'Gagal mengexport data Excel: ' . $e->getMessage());
        }
    }

    /**
     * Export issue selesai intervensi control room ke Excel.
     */
    public function exportDoneIntervensiControlRoomExcel(Request $request)
    {
        try {
            $headers = [
                'No', 'ID', 'Control Room', 'CCTV', 'SID PIC', 'Nama PIC', 'No HP PIC',
                'Issue', 'Resolution', 'Tanggal Pelaporan', 'Tanggal Selesai', 'Closed By', 'Updated At',
            ];

            $query = IntervensiControlRoom::where('status', 'closed');

            if ($request->filled('search')) {
                $searchValue = $request->search;
                $query->where(function ($q) use ($searchValue) {
                    $q->where('control_room', 'like', '%' . $searchValue . '%')
                        ->orWhere('pic_username', 'like', '%' . $searchValue . '%')
                        ->orWhere('pic_nama', 'like', '%' . $searchValue . '%')
                        ->orWhere('issue', 'like', '%' . $searchValue . '%');
                });
            }

            $intervensiList = $query->with('cctvs')->orderByDesc('closed_at')->get();

            $spreadsheet = SpreadsheetExporter::createSheetWithHeaders($headers);
            $sheet = $spreadsheet->getActiveSheet();

            $rowNum = 2;
            foreach ($intervensiList as $index => $intervensi) {
                $sheet->fromArray([
                    $index + 1,
                    $intervensi->id,
                    $intervensi->control_room ?? '',
                    $this->formatIntervensiCctvNamesForExport($intervensi),
                    $intervensi->pic_username ?? '',
                    $intervensi->pic_nama ?? '',
                    $intervensi->pic_telepon ?? '',
                    $intervensi->issue ?? '',
                    $intervensi->resolution ?? '',
                    $intervensi->created_at ? $intervensi->created_at->format('Y-m-d H:i:s') : '',
                    $intervensi->closed_at ? $intervensi->closed_at->format('Y-m-d H:i:s') : '',
                    $intervensi->closed_by ?? '',
                    $intervensi->updated_at ? $intervensi->updated_at->format('Y-m-d H:i:s') : '',
                ], null, 'A' . $rowNum);
                $rowNum++;
            }

            SpreadsheetExporter::download($spreadsheet, 'intervensi_control_room_done_' . date('Y-m-d_His') . '.xlsx');
        } catch (Exception $e) {
            Log::error('Error exporting done intervensi control room: ' . $e->getMessage());

            return redirect()
                ->route('cctv-data.intervensi-control-room.index')
                ->with('error', 'Gagal mengexport data Excel: ' . $e->getMessage());
        }
    }

    /**
     * Format nama CCTV untuk export Excel.
     */
    private function formatIntervensiCctvNamesForExport(IntervensiControlRoom $intervensi): string
    {
        try {
            $cctvs = $intervensi->cctvs;
            if ($cctvs && $cctvs->count() > 0) {
                return $cctvs->map(function ($cctv) {
                    $name = $cctv->nama_cctv ?? ('CCTV ' . $cctv->id);
                    if ($cctv->no_cctv) {
                        $name .= ' (' . $cctv->no_cctv . ')';
                    }

                    return $name;
                })->implode(', ');
            }

            if ($intervensi->cctv_id) {
                $cctv = CctvData::find($intervensi->cctv_id);
                if ($cctv) {
                    $name = $cctv->nama_cctv ?? ('CCTV ' . $cctv->id);
                    if ($cctv->no_cctv) {
                        $name .= ' (' . $cctv->no_cctv . ')';
                    }

                    return $name;
                }
            }
        } catch (Exception $e) {
            Log::warning('Error formatting CCTV for intervensi export ' . $intervensi->id . ': ' . $e->getMessage());
        }

        return '';
    }

}

