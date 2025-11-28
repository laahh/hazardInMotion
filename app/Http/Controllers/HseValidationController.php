<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\QwenAIService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class HseValidationController extends Controller
{
    protected $qwenService;

    public function __construct(QwenAIService $qwenService)
    {
        $this->qwenService = $qwenService;
    }

    /**
     * Tampilkan halaman upload
     */
    public function index()
    {
        return view('hse-validation.index');
    }

    /**
     * Proses upload dan validasi file
     */
    public function process(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Baca file Excel/CSV
            // Untuk CSV, gunakan reader khusus
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

            // Ambil header
            $headers = array_map('strtolower', array_map('trim', $rows[0]));
            
            // Cari kolom Deskripsi dan Url Photo
            $deskripsiIndex = null;
            $urlPhotoIndex = null;
            
            foreach ($headers as $index => $header) {
                if (strpos($header, 'deskripsi') !== false || strpos($header, 'description') !== false) {
                    $deskripsiIndex = $index;
                }
                if (strpos($header, 'url photo') !== false || strpos($header, 'url_photo') !== false || 
                    strpos($header, 'photo') !== false || strpos($header, 'url') !== false) {
                    $urlPhotoIndex = $index;
                }
            }

            if ($deskripsiIndex === null) {
                return back()->withErrors(['file' => 'Kolom "Deskripsi" tidak ditemukan dalam file.']);
            }

            if ($urlPhotoIndex === null) {
                return back()->withErrors(['file' => 'Kolom "Url Photo" tidak ditemukan dalam file.']);
            }

            // Hitung total baris yang akan diproses
            $totalRows = 0;
            for ($i = 1; $i < count($rows); $i++) {
                if (!empty($rows[$i][$deskripsiIndex])) {
                    $totalRows++;
                }
            }

            // Generate unique ID untuk proses ini
            $processId = uniqid('hse_validation_', true);
            
            // Simpan data awal ke cache
            Cache::put("hse_process_{$processId}", [
                'total_rows' => $totalRows,
                'processed' => 0,
                'current_row' => 0,
                'status' => 'processing',
                'headers' => $headers,
                'results' => [],
                'deskripsi_index' => $deskripsiIndex,
                'url_photo_index' => $urlPhotoIndex,
                'rows' => $rows,
            ], now()->addHours(2));

            // Redirect ke halaman loading dengan process ID
            return redirect()->route('hse-validation.loading', ['processId' => $processId]);

        } catch (Exception $e) {
            return back()->withErrors(['file' => 'Error processing file: ' . $e->getMessage()]);
        }
    }

    /**
     * Halaman loading dengan progress bar
     */
    public function loading($processId)
    {
        return view('hse-validation.loading', compact('processId'));
    }

    /**
     * Endpoint untuk mendapatkan progress
     */
    public function getProgress($processId)
    {
        $processData = Cache::get("hse_process_{$processId}");
        
        if (!$processData) {
            Log::warning('Progress not found', ['processId' => $processId]);
            return response()->json([
                'status' => 'not_found',
                'progress' => 0,
                'message' => 'Process not found'
            ]);
        }

        $progress = $processData['total_rows'] > 0 
            ? round(($processData['processed'] / $processData['total_rows']) * 100) 
            : 0;

        $response = [
            'status' => $processData['status'],
            'progress' => min(100, $progress),
            'processed' => $processData['processed'],
            'total' => $processData['total_rows'],
            'current_row' => $processData['current_row'],
        ];

        // Jika completed, pastikan progress 100%
        if ($processData['status'] === 'completed') {
            $response['progress'] = 100;
            $response['redirect'] = route('hse-validation.results', ['processId' => $processId]);
        }

        return response()->json($response);
    }

    /**
     * Proses validasi secara async
     */
    public function processAsync($processId)
    {
        $processData = Cache::get("hse_process_{$processId}");
        
        if (!$processData) {
            return response()->json(['error' => 'Process not found'], 404);
        }

        // Jika sudah selesai, return hasil
        if ($processData['status'] === 'completed') {
            return response()->json([
                'status' => 'completed',
                'redirect' => route('hse-validation.results', ['processId' => $processId])
            ]);
        }

        // Proses baris berikutnya
        $rows = $processData['rows'];
        $deskripsiIndex = $processData['deskripsi_index'];
        $urlPhotoIndex = $processData['url_photo_index'];
        $results = $processData['results'];
        $processed = $processData['processed'];
        $currentRow = $processData['current_row'];

        // Proses beberapa baris sekaligus (batch processing)
        $batchSize = 1; // Proses 1 baris per request untuk update progress lebih smooth
        $batchCount = 0;

        // Cek apakah sudah mencapai akhir baris
        $totalRowsCount = count($rows) - 1; // Exclude header
        $isCompleted = ($currentRow + 1) >= $totalRowsCount;

        // Mulai dari baris berikutnya (currentRow + 1 karena currentRow adalah index terakhir yang diproses)
        if (!$isCompleted) {
            for ($i = $currentRow + 1; $i < count($rows) && $batchCount < $batchSize; $i++) {
                $row = $rows[$i];
                
                // Skip baris kosong
                if (empty($row[$deskripsiIndex])) {
                    $currentRow = $i; // Update currentRow meskipun skip
                    // Cek apakah ini baris terakhir
                    if ($i >= $totalRowsCount) {
                        $isCompleted = true;
                    }
                    continue;
                }

                $deskripsi = $row[$deskripsiIndex] ?? '';
                $urlPhoto = $row[$urlPhotoIndex] ?? '';

                try {
                    // Validasi menggunakan AI
                    $validationResult = $this->qwenService->validateFinding($deskripsi, $urlPhoto);

                    // Tambahkan hasil validasi ke baris
                    $rowData = [
                        'row_number' => $i + 1,
                        'deskripsi' => $deskripsi,
                        'url_photo' => $urlPhoto,
                        'validasi_main_category' => $validationResult['main_category'] ?? null,
                        'validasi_sub_category' => $validationResult['sub_category'] ?? null,
                        'validasi_TBC' => $validationResult['concern_level']['TBC'] ?? false,
                        'validasi_PSPP' => $validationResult['concern_level']['PSPP'] ?? false,
                        'validasi_GR' => $validationResult['concern_level']['GR'] ?? false,
                        'validasi_Incident' => $validationResult['concern_level']['Incident'] ?? false,
                        'validasi_justifikasi' => $validationResult['justification'] ?? '',
                        'validasi_confidence' => $validationResult['confidence_score'] ?? 0.0,
                        'match_found' => $validationResult['match_found'] ?? false,
                    ];

                    // Simpan semua kolom original
                    foreach ($row as $colIndex => $value) {
                        $rowData['original_' . $colIndex] = $value;
                    }

                    $results[] = $rowData;
                    $processed++;
                    $currentRow = $i;
                    $batchCount++;

                    // Cek apakah ini baris terakhir
                    if ($i >= $totalRowsCount) {
                        $isCompleted = true;
                    }

                } catch (Exception $e) {
                    // Jika error, skip baris ini dan lanjut
                    Log::error('Error processing row', [
                        'row' => $i,
                        'error' => $e->getMessage()
                    ]);
                    $currentRow = $i; // Update currentRow meskipun error
                    // Cek apakah ini baris terakhir
                    if ($i >= $totalRowsCount) {
                        $isCompleted = true;
                    }
                    continue;
                }
            }
        }

        // Update progress - pastikan completed jika sudah mencapai akhir
        if (($currentRow + 1) >= $totalRowsCount) {
            $isCompleted = true;
        }
        
        Cache::put("hse_process_{$processId}", [
            'total_rows' => $processData['total_rows'],
            'processed' => $processed,
            'current_row' => $currentRow,
            'status' => $isCompleted ? 'completed' : 'processing',
            'headers' => $processData['headers'],
            'results' => $results,
            'deskripsi_index' => $deskripsiIndex,
            'url_photo_index' => $urlPhotoIndex,
            'rows' => $rows,
        ], now()->addHours(2));

        // Jika selesai, simpan ke session
        if ($isCompleted) {
            session(['hse_validation_results' => $results]);
            session(['hse_validation_headers' => $processData['headers']]);
            Log::info('HSE Validation completed', [
                'processId' => $processId,
                'total_results' => count($results),
                'processed' => $processed
            ]);
        }

        $progress = $processData['total_rows'] > 0 
            ? round(($processed / $processData['total_rows']) * 100) 
            : 0;

        return response()->json([
            'status' => $isCompleted ? 'completed' : 'processing',
            'progress' => min(100, $progress),
            'processed' => $processed,
            'total' => $processData['total_rows'],
            'redirect' => $isCompleted ? route('hse-validation.results', ['processId' => $processId]) : null
        ]);
    }

    /**
     * Tampilkan hasil validasi
     */
    public function results(Request $request, $processId = null)
    {
        // Cek apakah ini request AJAX atau request normal
        // Jika AJAX, return JSON untuk menghindari reload loop
        if ($request->ajax() || $request->wantsJson()) {
            $processData = Cache::get("hse_process_{$processId}");
            if ($processData && $processData['status'] === 'completed') {
                return response()->json([
                    'status' => 'completed',
                    'results_count' => count($processData['results'] ?? [])
                ]);
            }
            return response()->json(['status' => 'processing']);
        }

        $results = [];
        $headers = [];

        // Jika ada processId, ambil dari cache
        if ($processId) {
            $processData = Cache::get("hse_process_{$processId}");
            if ($processData) {
                if ($processData['status'] === 'completed' && !empty($processData['results'])) {
                    $results = $processData['results'];
                    $headers = $processData['headers'];
                    Log::info('Results loaded from cache', [
                        'processId' => $processId,
                        'results_count' => count($results)
                    ]);
                } else {
                    // Jika belum completed, coba ambil dari session
                    $results = session('hse_validation_results', []);
                    $headers = session('hse_validation_headers', []);
                    Log::warning('Process not completed, trying session', [
                        'processId' => $processId,
                        'status' => $processData['status'] ?? 'unknown',
                        'session_results_count' => count($results)
                    ]);
                }
            } else {
                // Cache tidak ditemukan, coba dari session
                $results = session('hse_validation_results', []);
                $headers = session('hse_validation_headers', []);
                Log::warning('Process cache not found, trying session', [
                    'processId' => $processId,
                    'session_results_count' => count($results)
                ]);
            }
        } else {
            // Tidak ada processId, ambil dari session
            $results = session('hse_validation_results', []);
            $headers = session('hse_validation_headers', []);
        }

        if (empty($results)) {
            Log::warning('No results found', [
                'processId' => $processId,
                'has_session_results' => !empty(session('hse_validation_results'))
            ]);
            return redirect()->route('hse-validation.index')
                ->withErrors(['message' => 'Tidak ada hasil validasi. Silakan upload file terlebih dahulu.']);
        }

        return view('hse-validation.results', compact('results', 'headers'));
    }

    /**
     * Proxy untuk gambar (mengatasi CORS dan extract gambar dari halaman)
     */
    public function imageProxy(Request $request)
    {
        $url = $request->get('url');
        
        if (!$url) {
            abort(400, 'URL parameter is required');
        }

        try {
            // Jika URL adalah halaman HTML (beraucoal.co.id), coba extract gambar
            if (strpos($url, 'hseautomation.beraucoal.co.id/report/photoCar') !== false) {
                $response = Http::timeout(10)->get($url);
                
                if ($response->successful()) {
                    $html = $response->body();
                    
                    // Cek apakah ada "No Photo" di halaman
                    if (stripos($html, 'No Photo') !== false && stripos($html, 'Foto Temuan') === false) {
                        Log::info('No photo found on page', ['url' => $url]);
                        // Return placeholder image
                        return $this->generatePlaceholderImage('No Photo Available');
                    }
                    
                    // Coba berbagai pattern untuk extract URL gambar dari HTML
                    // Prioritas: cari gambar di section "Foto Temuan" terlebih dahulu
                    $patterns = [
                        // Cari gambar di dalam section Foto Temuan (prioritas tertinggi)
                        '/Foto Temuan[^>]*>.*?<img[^>]+src=["\']([^"\']+)["\']/is',
                        '/Foto Temuan[^>]*>.*?<img[^>]+data-src=["\']([^"\']+)["\']/is',
                        // Cari semua gambar dengan ekstensi gambar
                        '/<img[^>]+src=["\']([^"\']+\.(jpg|jpeg|png|gif|webp|bmp)[^"\']*)["\']/i',
                        '/<img[^>]+src=["\']([^"\']+)["\']/i',
                        // Cari background-image
                        '/background-image:\s*url\(["\']?([^"\']+)["\']?\)/i',
                        // Cari data-src (lazy loading)
                        '/<img[^>]+data-src=["\']([^"\']+)["\']/i',
                        // Cari URL gambar langsung di HTML
                        '/(https?:\/\/[^\s"\'<>]+\.(jpg|jpeg|png|gif|webp|bmp))/i',
                    ];
                    
                    $imageUrl = null;
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $html, $matches)) {
                            $imageUrl = $matches[1];
                            // Skip jika URL adalah placeholder, icon, atau data URI
                            if (stripos($imageUrl, 'placeholder') !== false || 
                                stripos($imageUrl, 'icon') !== false ||
                                stripos($imageUrl, 'logo') !== false ||
                                stripos($imageUrl, 'data:image') === 0) {
                                continue;
                            }
                            break;
                        }
                    }
                    
                    if ($imageUrl) {
                        // Jika relative URL, buat absolute
                        if (strpos($imageUrl, 'http') !== 0) {
                            $baseUrl = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
                            // Jika URL dimulai dengan /, langsung append
                            if (strpos($imageUrl, '/') === 0) {
                                $imageUrl = $baseUrl . $imageUrl;
                            } else {
                                $imageUrl = $baseUrl . '/' . ltrim($imageUrl, '/');
                            }
                        }
                        
                        Log::info('Extracted image URL from HTML', [
                            'original_url' => $url,
                            'image_url' => $imageUrl
                        ]);
                        
                        // Fetch gambar
                        $imageResponse = Http::timeout(10)->get($imageUrl);
                        
                        if ($imageResponse->successful()) {
                            $contentType = $imageResponse->header('Content-Type');
                            // Pastikan ini benar-benar gambar
                            if (strpos($contentType, 'image/') === 0) {
                                return response($imageResponse->body(), 200)
                                    ->header('Content-Type', $contentType)
                                    ->header('Cache-Control', 'public, max-age=3600');
                            }
                        }
                    }
                    
                    // Jika tidak ditemukan gambar, return placeholder
                    Log::warning('Could not extract image from HTML', ['url' => $url]);
                    return $this->generatePlaceholderImage('Image not found on page');
                }
            }
            
            // Untuk URL langsung ke gambar
            $response = Http::timeout(10)->get($url);
            
            if ($response->successful()) {
                $contentType = $response->header('Content-Type');
                
                // Jika response adalah HTML, bukan gambar
                if (strpos($contentType, 'text/html') !== false) {
                    Log::warning('URL returned HTML instead of image', ['url' => $url]);
                    return $this->generatePlaceholderImage('URL is not a direct image link');
                }
                
                return response($response->body(), 200)
                    ->header('Content-Type', $contentType ?? 'image/jpeg')
                    ->header('Cache-Control', 'public, max-age=3600');
            }
            
            return $this->generatePlaceholderImage('Image not found');
        } catch (Exception $e) {
            Log::error('Image proxy error', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return $this->generatePlaceholderImage('Failed to load image');
        }
    }

    /**
     * Generate placeholder image SVG
     */
    private function generatePlaceholderImage($message = 'Image not available')
    {
        $svg = '<?xml version="1.0" encoding="UTF-8"?>
            <svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
            <rect width="200" height="200" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
            <text x="100" y="90" font-family="Arial, sans-serif" font-size="14" fill="#999" text-anchor="middle">' . htmlspecialchars($message) . '</text>
            <text x="100" y="120" font-family="Arial, sans-serif" font-size="12" fill="#ccc" text-anchor="middle">Klik untuk membuka link</text>
            </svg>';
        
        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'no-cache');
    }

    /**
     * Download hasil validasi sebagai Excel
     */
    public function download()
    {
        $results = session('hse_validation_results', []);
        $headers = session('hse_validation_headers', []);

        if (empty($results)) {
            return redirect()->route('hse-validation.index')
                ->withErrors(['message' => 'Tidak ada hasil validasi untuk diunduh.']);
        }

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Header row
            $headerRow = [
                'No',
                'Deskripsi',
                'Url Photo',
                'Validasi Main Category',
                'Validasi Sub Category',
                'Validasi TBC',
                'Validasi PSPP',
                'Validasi GR',
                'Validasi Incident',
                'Validasi Justifikasi',
                'Validasi Confidence',
                'Match Found'
            ];

            $col = 'A';
            foreach ($headerRow as $header) {
                $sheet->setCellValue($col . '1', $header);
                $col++;
            }

            // Data rows
            $rowNum = 2;
            foreach ($results as $result) {
                $sheet->setCellValue('A' . $rowNum, $result['row_number']);
                $sheet->setCellValue('B' . $rowNum, $result['deskripsi']);
                $sheet->setCellValue('C' . $rowNum, $result['url_photo']);
                $sheet->setCellValue('D' . $rowNum, $result['validasi_main_category']);
                $sheet->setCellValue('E' . $rowNum, $result['validasi_sub_category']);
                $sheet->setCellValue('F' . $rowNum, $result['validasi_TBC'] ? 'Ya' : 'Tidak');
                $sheet->setCellValue('G' . $rowNum, $result['validasi_PSPP'] ? 'Ya' : 'Tidak');
                $sheet->setCellValue('H' . $rowNum, $result['validasi_GR'] ? 'Ya' : 'Tidak');
                $sheet->setCellValue('I' . $rowNum, $result['validasi_Incident'] ? 'Ya' : 'Tidak');
                $sheet->setCellValue('J' . $rowNum, $result['validasi_justifikasi']);
                $sheet->setCellValue('K' . $rowNum, $result['validasi_confidence']);
                $sheet->setCellValue('L' . $rowNum, $result['match_found'] ? 'Ya' : 'Tidak');
                $rowNum++;
            }

            // Auto-size columns
            foreach (range('A', 'L') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Download file
            $writer = new Xlsx($spreadsheet);
            $filename = 'hse_validation_results_' . date('Y-m-d_His') . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            return redirect()->route('hse-validation.results')
                ->withErrors(['message' => 'Error generating download: ' . $e->getMessage()]);
        }
    }
}

