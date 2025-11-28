<?php

namespace App\Http\Controllers;

use App\Models\CctvData;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;
use Illuminate\Support\Facades\DB;
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

                    // Cek apakah data dengan kombinasi nama_cctv dan no_cctv sudah ada di database
                    // Hanya cek jika kedua field ada nilainya
                    if (!empty($data['nama_cctv']) && !empty($data['no_cctv'])) {
                        $existingData = CctvData::where('nama_cctv', $data['nama_cctv'])
                                              ->where('no_cctv', $data['no_cctv'])
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

}

