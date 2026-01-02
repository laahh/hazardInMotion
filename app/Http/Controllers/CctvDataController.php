<?php

namespace App\Http\Controllers;

use App\Models\CctvData;
use App\Models\CctvCoverage;
use App\Models\PjaCctv;
use App\Models\PjaCctvDedicated;
use App\Models\CctvControlRoomPengawas;
use App\Services\ClickHouseService;
use App\Jobs\ImportPjaCctvJob;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class CctvDataController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('cctv-data.index');
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

        // Base query
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

        // Get total records
        $recordsTotal = CctvData::count();
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
                    // Hanya cek jika no_cctv ada nilainya
                    if (!empty($data['no_cctv'])) {
                        $existingData = CctvData::where('no_cctv', $data['no_cctv'])
                                              ->first();

                        // Skip jika data sudah ada
                        if ($existingData) {
                            $skippedCount++;
                            continue;
                        }
                    }

                    // Simpan data
                    try {
                        $newCctvData = CctvData::create($data);
                        $successCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                        $errors[] = "Baris " . ($i + 1) . ": " . $e->getMessage();
                    }
                }

                DB::commit();

                $message = "Import berhasil! {$successCount} data berhasil diimpor.";
                if ($skippedCount > 0) {
                    $message .= " {$skippedCount} data di-skip (sudah ada di database).";
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
        $columns = ['cctv_coverage.id', 'cctv_data_bmo2.no_cctv', 'cctv_coverage.coverage_lokasi', 'cctv_coverage.coverage_detail_lokasi', 'cctv_coverage.created_at', 'cctv_coverage.updated_at'];
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
                'cctv_coverage.created_at',
                'cctv_coverage.updated_at'
            )
            ->leftJoin('cctv_data_bmo2', 'cctv_coverage.id_cctv', '=', 'cctv_data_bmo2.id');

        // Search functionality
        if (!empty($searchValue)) {
            $query->where(function($q) use ($searchValue) {
                $q->where('cctv_data_bmo2.no_cctv', 'like', '%' . $searchValue . '%')
                  ->orWhere('cctv_coverage.coverage_lokasi', 'like', '%' . $searchValue . '%')
                  ->orWhere('cctv_coverage.coverage_detail_lokasi', 'like', '%' . $searchValue . '%');
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
            return [
                'DT_RowIndex' => $start + $index + 1,
                'no_cctv' => $item->no_cctv ?? '-',
                'coverage_lokasi' => $item->coverage_lokasi ?? '-',
                'coverage_detail_lokasi' => $item->coverage_detail_lokasi ?? '-',
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : '-',
                'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d H:i:s') : '-',
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
            $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

            // Contoh data
            $examples = [
                ['HO', 'PT Fajar Anugerah Dinamika', 'CCTV 01 FAD LMO', 'Dermaga', 'Dermaga FAD Prapatan'],
                ['LMO', 'PT Fajar Anugerah Dinamika', 'LMO-FAD-0001', 'Workshop FAD', 'Base Workshop'],
                ['LMO', 'PT Fajar Anugerah Dinamika', 'LMO-FAD-0001', 'Workshop FAD', 'Parkiran Unit'],
                ['BMO 1', 'PT Fajar Anugerah Dinamika', 'BMO1-MTL-0023', 'Workshop FAD', 'Area Fabrikasi'],
            ];

            // Data rows dengan contoh
            $rowNum = 2;
            foreach ($examples as $index => $example) {
                $sheet->setCellValue('A' . $rowNum, $example[0]);
                $sheet->setCellValue('B' . $rowNum, $example[1]);
                $sheet->setCellValue('C' . $rowNum, $example[2]);
                $sheet->setCellValue('D' . $rowNum, $example[3]);
                $sheet->setCellValue('E' . $rowNum, $example[4]);
                
                // Set style untuk baris contoh (warna abu-abu terang)
                if ($index < 2) {
                    $sheet->getStyle('A' . $rowNum . ':E' . $rowNum)->getFill()
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
            
            // Set style untuk kolom yang harus diisi (warna kuning)
            $sheet->getStyle('A' . $rowNum . ':E' . $rowNum)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFFACD');

            // Auto-size columns
            foreach (range('A', 'E') as $col) {
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
            
            // Mapping kolom Excel ke field
            $columnMapping = [
                'site' => ['site'],
                'perusahaan' => ['perusahaan cctv', 'perusahaan'],
                'no_cctv' => ['nomer cctv', 'nomer cctv', 'no cctv', 'no_cctv'],
                'coverage_lokasi' => ['coverage lokasi', 'coverage_lokasi'],
                'coverage_detail_lokasi' => ['coverage detail lokasi', 'coverage_detail_lokasi'],
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
            if ($columnIndexes['site'] === null || 
                $columnIndexes['perusahaan'] === null || 
                $columnIndexes['no_cctv'] === null) {
                return back()->withErrors(['file' => 'File harus memiliki kolom: Site, Perusahaan CCTV, dan Nomer CCTV.']);
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
                    $site = isset($row[$columnIndexes['site']]) ? trim((string) $row[$columnIndexes['site']]) : null;
                    $perusahaan = isset($row[$columnIndexes['perusahaan']]) ? trim((string) $row[$columnIndexes['perusahaan']]) : null;
                    $noCctvExcel = isset($row[$columnIndexes['no_cctv']]) ? trim((string) $row[$columnIndexes['no_cctv']]) : null;
                    $coverageLokasi = isset($row[$columnIndexes['coverage_lokasi']]) ? trim((string) $row[$columnIndexes['coverage_lokasi']]) : null;
                    $coverageDetailLokasi = isset($row[$columnIndexes['coverage_detail_lokasi']]) ? trim((string) $row[$columnIndexes['coverage_detail_lokasi']]) : null;

                    // Validasi data wajib
                    if (empty($site) || empty($perusahaan) || empty($noCctvExcel)) {
                        $errorCount++;
                        $errors[] = "Baris " . ($i + 1) . ": Site, Perusahaan CCTV, dan Nomer CCTV harus diisi.";
                        continue;
                    }

                    // Cari CCTV dengan multiple matching strategies
                    $cctvData = $this->findCctvByFlexibleMatching($site, $perusahaan, $noCctvExcel);

                    if (!$cctvData) {
                        $errorCount++;
                        $errors[] = "Baris " . ($i + 1) . ": CCTV tidak ditemukan dengan Site: {$site}, Perusahaan: {$perusahaan}, Nomer CCTV: {$noCctvExcel}";
                        continue;
                    }

                    // Cek apakah coverage sudah ada (optional - bisa dihapus jika ingin allow duplicate)
                    $existingCoverage = CctvCoverage::where('id_cctv', $cctvData->id)
                        ->where('coverage_lokasi', $coverageLokasi)
                        ->where('coverage_detail_lokasi', $coverageDetailLokasi)
                        ->first();

                    if ($existingCoverage) {
                        $skippedCount++;
                        continue;
                    }

                    // Simpan data coverage
                    try {
                        CctvCoverage::create([
                            'id_cctv' => $cctvData->id,
                            'coverage_lokasi' => $coverageLokasi,
                            'coverage_detail_lokasi' => $coverageDetailLokasi,
                        ]);
                        $successCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                        $errors[] = "Baris " . ($i + 1) . ": " . $e->getMessage();
                    }
                }

                DB::commit();

                $message = "Import berhasil! {$successCount} data coverage berhasil diimpor.";
                if ($skippedCount > 0) {
                    $message .= " {$skippedCount} data di-skip (sudah ada di database).";
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
                    $no = isset($row[$columnIndexes['no']]) ? trim((string) $row[$columnIndexes['no']]) : null;
                    $pja = isset($row[$columnIndexes['pja']]) ? trim((string) $row[$columnIndexes['pja']]) : null;
                    $cctvDedicated = isset($row[$columnIndexes['cctv_dedicated']]) ? trim((string) $row[$columnIndexes['cctv_dedicated']]) : null;

                    // Validasi data wajib
                    if (empty($pja) || empty($cctvDedicated)) {
                        $errorCount++;
                        $errors[] = "Baris " . ($i + 1) . ": PJA dan CCTV Dedicated harus diisi.";
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
                        $errors[] = "Baris " . ($i + 1) . ": " . $e->getMessage();
                    }
                }

                DB::commit();

                $message = "Import berhasil! {$successCount} data berhasil diimpor.";
                if ($skippedCount > 0) {
                    $message .= " {$skippedCount} data di-skip (sudah ada di database).";
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
    public function getCctvDetails(Request $request, $id)
    {
        try {
            $cctv = CctvData::findOrFail($id);
            
            // Get coverage locations
            $coverages = CctvCoverage::where('id_cctv', $id)
                ->orderBy('coverage_lokasi')
                ->orderBy('coverage_detail_lokasi')
                ->get();
            
            // Get this week's date range using ClickHouse toStartOfWeek (konsisten dengan week di database)
            // Gunakan toStartOfWeek() di ClickHouse untuk memastikan week calculation sama dengan kolom 'minggu'
            $today = \Carbon\Carbon::today();
            $todayStr = $today->format('Y-m-d');
            
            // Get hazard inspection statistics
            $hazardStats = [];
            $clickhouse = new ClickHouseService();
            
            if ($clickhouse->isConnected() && $coverages->count() > 0) {
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
                
                // Build detail lokasi list from coverages
                $detailLokasiList = $coverages->pluck('coverage_detail_lokasi')->filter()->toArray();
                
                if (!empty($detailLokasiList)) {
                    // Normalize detail lokasi list
                    $normalizedDetailLokasiMap = [];
                    foreach ($detailLokasiList as $lokasi) {
                        $normalized = mb_strtolower(trim($lokasi));
                        $normalizedDetailLokasiMap[$normalized] = $lokasi;
                    }
                    
                    // Build WHERE conditions for detail lokasi matching with flexible LIKE
                    $detailLokasiConditions = [];
                    foreach ($detailLokasiList as $lokasi) {
                        $escapedLokasi = addslashes(trim($lokasi));
                        // Use multiple matching strategies for better matching
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
                        $hazardResults = $clickhouse->query($sqlHazard);
                        
                        Log::info('CCTV Details - Hazard Query Results', [
                            'cctv_id' => $id,
                            'total_hazard_results' => count($hazardResults),
                            'coverage_detail_lokasi_list' => array_values($normalizedDetailLokasiMap),
                            'sample_hazard_results' => array_slice($hazardResults, 0, 5)
                        ]);
                        
                        $matchedCount = 0;
                        $unmatchedCount = 0;
                        
                        // Group by detail_lokasi with flexible matching
                        foreach ($hazardResults as $row) {
                            $hazardDetailLokasi = trim($row['detail_lokasi'] ?? '');
                            $count = (int)($row['count'] ?? 0);
                            $tool = $row['tools_pengawasan'] ?? '';
                            
                            if (empty($hazardDetailLokasi)) continue;
                            
                            // Find matching coverage detail lokasi with flexible matching
                            $matchedCoverageLokasi = null;
                            $normalizedHazard = mb_strtolower(trim($hazardDetailLokasi));
                            
                            foreach ($normalizedDetailLokasiMap as $normalizedCoverage => $originalCoverage) {
                                // Check if they match using multiple strategies
                                $matches = false;
                                
                                // Exact match
                                if ($normalizedCoverage === $normalizedHazard) {
                                    $matches = true;
                                }
                                // Coverage contains hazard
                                elseif (mb_strlen($normalizedCoverage) >= mb_strlen($normalizedHazard) && mb_strpos($normalizedCoverage, $normalizedHazard) !== false) {
                                    $matches = true;
                                }
                                // Hazard contains coverage
                                elseif (mb_strlen($normalizedHazard) >= mb_strlen($normalizedCoverage) && mb_strpos($normalizedHazard, $normalizedCoverage) !== false) {
                                    $matches = true;
                                }
                                // Similar text check (>= 80% similarity)
                                elseif (similar_text($normalizedCoverage, $normalizedHazard, $percent) && $percent >= 80) {
                                    $matches = true;
                                }
                                
                                if ($matches) {
                                    $matchedCoverageLokasi = $originalCoverage;
                                    break;
                                }
                            }
                            
                            // Hanya masukkan hazard jika ada match dengan coverage detail lokasi CCTV
                            // Jika tidak ada match, skip (tidak tampilkan)
                            if ($matchedCoverageLokasi === null) {
                                $unmatchedCount++;
                                continue; // Skip hazard yang tidak match
                            }
                            
                            $matchedCount++;
                            
                            // Gunakan matched coverage detail lokasi sebagai key
                            $keyLokasi = $matchedCoverageLokasi;
                            
                            if (!isset($hazardStats[$keyLokasi])) {
                                $hazardStats[$keyLokasi] = [
                                    'detail_lokasi' => $keyLokasi,
                                    'total_count' => 0,
                                    'by_tool' => []
                                ];
                            }
                            $hazardStats[$keyLokasi]['total_count'] += $count;
                            $hazardStats[$keyLokasi]['by_tool'][$tool] = $count;
                        }
                        
                        Log::info('CCTV Details - Hazard Matching Summary', [
                            'cctv_id' => $id,
                            'total_hazard_results' => count($hazardResults),
                            'matched_count' => $matchedCount,
                            'unmatched_count' => $unmatchedCount,
                            'final_hazard_stats_count' => count($hazardStats)
                        ]);
                    } catch (Exception $e) {
                        Log::error('Error querying hazard inspections: ' . $e->getMessage());
                    }
                }
            }
            
            // Get PJA information
            $pjaList = [];
            if ($clickhouse->isConnected() && $coverages->count() > 0) {
                $detailLokasiList = $coverages->pluck('coverage_detail_lokasi')->filter()->toArray();
                
                if (!empty($detailLokasiList)) {
                    // Build WHERE conditions for detail lokasi matching (using LIKE for flexible matching)
                    $detailLokasiConditions = [];
                    foreach ($detailLokasiList as $lokasi) {
                        $escapedLokasi = addslashes($lokasi);
                        // Use LIKE for flexible matching (case-insensitive)
                        $detailLokasiConditions[] = "lowerUTF8(toString(detail_lokasi)) = lowerUTF8('{$escapedLokasi}')";
                    }
                    $detailLokasiFilter = '(' . implode(' OR ', $detailLokasiConditions) . ')';
                    
                    $sqlPja = "
                        SELECT DISTINCT
                            toString(site) as site,
                            toString(lokasi) as lokasi,
                            toString(detail_lokasi) as detail_lokasi,
                            toString(pja_id) as pja_id,
                            toString(nama_pja) as nama_pja,
                            toString(pja_active) as pja_active,
                            toString(employee_name) as employee_name,
                            toString(kode_sid) as kode_sid,
                            toString(employee_email) as employee_email,
                            toString(kategori_pja) as kategori_pja
                        FROM nitip.pja_full_hierarchical_view_fix
                        WHERE {$detailLokasiFilter}
                            AND toString(pja_active) = '1'
                        ORDER BY detail_lokasi, nama_pja
                        LIMIT 100
                    ";
                    
                    try {
                        $pjaResults = $clickhouse->query($sqlPja);
                        
                        // Group by detail_lokasi
                        foreach ($pjaResults as $row) {
                            $detailLokasi = $row['detail_lokasi'] ?? '';
                            if (!isset($pjaList[$detailLokasi])) {
                                $pjaList[$detailLokasi] = [];
                            }
                            $pjaList[$detailLokasi][] = [
                                'pja_id' => $row['pja_id'] ?? '',
                                'nama_pja' => $row['nama_pja'] ?? '',
                                'employee_name' => $row['employee_name'] ?? '',
                                'kode_sid' => $row['kode_sid'] ?? '',
                                'employee_email' => $row['employee_email'] ?? '',
                                'kategori_pja' => $row['kategori_pja'] ?? '',
                                'site' => $row['site'] ?? '',
                                'lokasi' => $row['lokasi'] ?? ''
                            ];
                        }
                    } catch (Exception $e) {
                        Log::error('Error querying PJA data: ' . $e->getMessage());
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'cctv' => [
                        'id' => $cctv->id,
                        'nama_cctv' => $cctv->nama_cctv,
                        'no_cctv' => $cctv->no_cctv,
                        'site' => $cctv->site,
                    ],
                    'coverages' => $coverages->map(function($coverage) {
                        return [
                            'id' => $coverage->id,
                            'coverage_lokasi' => $coverage->coverage_lokasi,
                            'coverage_detail_lokasi' => $coverage->coverage_detail_lokasi,
                        ];
                    }),
                    'hazard_stats' => array_values($hazardStats),
                    'pja_list' => $pjaList,
                    'week_info' => [
                        'today' => $todayStr,
                        'method' => 'toStartOfWeek() in ClickHouse'
                    ]
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'CCTV not found'
            ], 404);
        } catch (Exception $e) {
            Log::error('Error getting CCTV details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error retrieving CCTV details: ' . $e->getMessage()
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
        
        return view('cctv-data.pja-cctv-dedicated', compact('stats'));
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

            // Set title
            $sheet->setCellValue('A1', 'NO');
            $sheet->setCellValue('B1', 'CCTV Dedicated');
            $sheet->setCellValue('C1', 'Jumlah PJA');
            $sheet->setCellValue('D1', 'PJA');
            $sheet->setCellValue('E1', 'Created At');
            $sheet->setCellValue('F1', 'Updated At');

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
            $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

            // Data rows
            $rowNum = 2;
            foreach ($formattedGrouped as $item) {
                $sheet->setCellValue('A' . $rowNum, $item['no'] ?? '');
                $sheet->setCellValue('B' . $rowNum, $item['cctv_dedicated'] ?? '');
                $sheet->setCellValue('C' . $rowNum, $item['pja_count'] ?? 0);
                $sheet->setCellValue('D' . $rowNum, $item['pja'] ?? '');
                $sheet->setCellValue('E' . $rowNum, $item['created_at'] ? $item['created_at']->format('Y-m-d H:i:s') : '');
                $sheet->setCellValue('F' . $rowNum, $item['updated_at'] ? $item['updated_at']->format('Y-m-d H:i:s') : '');
                
                // Set wrap text for PJA column
                $sheet->getStyle('D' . $rowNum)->getAlignment()->setWrapText(true);
                $rowNum++;
            }

            // Auto-size columns
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Set column width for PJA column (make it wider)
            $sheet->getColumnDimension('D')->setWidth(50);

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
        return view('cctv-data.control-room');
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

}

