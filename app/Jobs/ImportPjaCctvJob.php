<?php

namespace App\Jobs;

use App\Models\CctvData;
use App\Models\PjaCctv;
use App\Services\ClickHouseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;

class ImportPjaCctvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 3600; // 1 hour

    /**
     * Path relative to storage/app
     */
    protected string $relativePath;

    /**
     * Create a new job instance.
     */
    public function __construct(string $relativePath)
    {
        $this->relativePath = $relativePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $fullPath = storage_path('app/' . $this->relativePath);

        if (!file_exists($fullPath)) {
            Log::warning('ImportPjaCctvJob file not found: ' . $fullPath);
            return;
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (SpreadsheetException $e) {
            Log::error('ImportPjaCctvJob spreadsheet error: ' . $e->getMessage());
            @unlink($fullPath);
            return;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (count($rows) < 2) {
            @unlink($fullPath);
            Log::warning('ImportPjaCctvJob: File has less than 2 rows');
            return;
        }

        // OPTIMASI: Load semua data PJA dari ClickHouse SEKALI
        Log::info('ImportPjaCctvJob: Loading PJA data from ClickHouse...');
        $pjaMap = $this->loadAllPjaFromClickHouse();
        
        // OPTIMASI: Load semua CCTV dari database SEKALI
        Log::info('ImportPjaCctvJob: Loading CCTV data from database...');
        $cctvMap = $this->loadAllCctvFromDatabase();

        // Ambil header
        $headers = array_map('trim', $rows[0]);
        
        $columnMapping = [
            'no' => ['no', 'nomor', 'number'],
            'pja' => ['pja'],
            'cctv' => ['cctv dedicated', 'cctv', 'cctv_dedicated'],
        ];

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

        if ($columnIndexes['pja'] === null || $columnIndexes['cctv'] === null) {
            Log::error('ImportPjaCctvJob: Required columns not found');
            @unlink($fullPath);
            return;
        }

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        $errors = [];

        DB::beginTransaction();
        
        try {
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                if (empty(array_filter($row))) {
                    continue;
                }

                $pjaName = isset($row[$columnIndexes['pja']]) ? trim((string) $row[$columnIndexes['pja']]) : null;
                $cctvName = isset($row[$columnIndexes['cctv']]) ? trim((string) $row[$columnIndexes['cctv']]) : null;

                if (empty($pjaName) || empty($cctvName)) {
                    $errorCount++;
                    $errors[] = "Baris " . ($i + 1) . ": PJA dan CCTV Dedicated harus diisi.";
                    continue;
                }

                // Cari PJA dari cache (sangat cepat!)
                $pjaId = $this->findPjaFromCache($pjaName, $pjaMap);

                if (!$pjaId) {
                    $errorCount++;
                    $errors[] = "Baris " . ($i + 1) . ": PJA tidak ditemukan: {$pjaName}";
                    continue;
                }

                // Cari CCTV dari cache (sangat cepat!)
                $cctvId = $this->findCctvFromCache($cctvName, $cctvMap);

                if (!$cctvId) {
                    $errorCount++;
                    $errors[] = "Baris " . ($i + 1) . ": CCTV tidak ditemukan: {$cctvName}";
                    continue;
                }

                // Cek duplikasi
                $existing = PjaCctv::where('id_pja', $pjaId)
                    ->where('id_cctv', $cctvId)
                    ->first();

                if ($existing) {
                    $skippedCount++;
                    continue;
                }

                // Simpan
                try {
                    PjaCctv::create([
                        'id_pja' => $pjaId,
                        'id_cctv' => $cctvId,
                    ]);
                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "Baris " . ($i + 1) . ": " . $e->getMessage();
                }
            }

            DB::commit();

            Log::info("ImportPjaCctvJob completed", [
                'success' => $successCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'error_details' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ImportPjaCctvJob error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            @unlink($fullPath);
        }
    }

    /**
     * Load semua PJA dari ClickHouse sekali dan cache di memory
     */
    private function loadAllPjaFromClickHouse(): array
    {
        $pjaMap = [];
        
        try {
            $clickhouse = new ClickHouseService();
            
            if (!$clickhouse->isConnected()) {
                Log::warning('ImportPjaCctvJob: ClickHouse is not connected');
                return $pjaMap;
            }

            // Ambil semua PJA sekali
            $sql = "
                SELECT toString(pja_id) as pja_id, toString(nama_pja) as nama_pja
                FROM nitip.pja_full_hierarchical_view_fix
                WHERE toString(nama_pja) != ''
            ";
            
            $results = $clickhouse->query($sql);
            
            // Buat multiple index untuk fast lookup
            foreach ($results as $row) {
                $pjaId = $row['pja_id'] ?? null;
                $namaPja = $row['nama_pja'] ?? '';
                
                if (!$pjaId || empty($namaPja)) {
                    continue;
                }

                // Index 1: Exact name (case-sensitive)
                $pjaMap['exact'][$namaPja] = $pjaId;
                
                // Index 2: Lowercase name
                $pjaMap['lower'][strtolower($namaPja)] = $pjaId;
                
                // Index 3: Normalized name (untuk handle typo)
                $normalized = $this->normalizePjaName($namaPja);
                $pjaMap['normalized'][$normalized] = $pjaId;
                
                // Index 4: Store original untuk similarity matching
                $pjaMap['all'][] = [
                    'pja_id' => $pjaId,
                    'nama_pja' => $namaPja,
                    'normalized' => $normalized
                ];
            }

            Log::info('ImportPjaCctvJob: Loaded ' . count($results) . ' PJA records from ClickHouse');

        } catch (\Exception $e) {
            Log::error('ImportPjaCctvJob: Error loading PJA from ClickHouse: ' . $e->getMessage());
        }

        return $pjaMap;
    }

    /**
     * Load semua CCTV dari database sekali dan cache di memory
     */
    private function loadAllCctvFromDatabase(): array
    {
        $cctvMap = [];
        
        try {
            $allCctv = CctvData::select('id', 'no_cctv', 'nama_cctv')->get();
            
            foreach ($allCctv as $cctv) {
                $id = $cctv->id;
                $noCctv = $cctv->no_cctv ?? '';
                $namaCctv = $cctv->nama_cctv ?? '';
                
                // Index 1: Exact no_cctv
                if (!empty($noCctv)) {
                    $cctvMap['no_cctv'][$noCctv] = $id;
                    $cctvMap['no_cctv_lower'][strtolower($noCctv)] = $id;
                }
                
                // Index 2: Exact nama_cctv
                if (!empty($namaCctv)) {
                    $cctvMap['nama_cctv'][$namaCctv] = $id;
                    $cctvMap['nama_cctv_lower'][strtolower($namaCctv)] = $id;
                }
                
                // Index 3: Extract number untuk pattern matching
                $extractedNumber = $this->extractNumberFromCctvName($noCctv ?: $namaCctv);
                if ($extractedNumber !== null) {
                    $normalizedNumber = ltrim($extractedNumber, '0');
                    if (empty($normalizedNumber)) {
                        $normalizedNumber = '0';
                    }
                    
                    if (!isset($cctvMap['by_number'][$normalizedNumber])) {
                        $cctvMap['by_number'][$normalizedNumber] = [];
                    }
                    $cctvMap['by_number'][$normalizedNumber][] = [
                        'id' => $id,
                        'no_cctv' => $noCctv,
                        'nama_cctv' => $namaCctv
                    ];
                }
                
                // Store all untuk fuzzy matching
                $cctvMap['all'][] = [
                    'id' => $id,
                    'no_cctv' => $noCctv,
                    'nama_cctv' => $namaCctv
                ];
            }

            Log::info('ImportPjaCctvJob: Loaded ' . count($allCctv) . ' CCTV records from database');

        } catch (\Exception $e) {
            Log::error('ImportPjaCctvJob: Error loading CCTV from database: ' . $e->getMessage());
        }

        return $cctvMap;
    }

    /**
     * Find PJA dari cache (sangat cepat!)
     */
    private function findPjaFromCache($pjaName, $pjaMap): ?string
    {
        if (empty($pjaName) || empty($pjaMap)) {
            return null;
        }

        // Strategy 1: Exact match
        if (isset($pjaMap['exact'][$pjaName])) {
            return $pjaMap['exact'][$pjaName];
        }

        // Strategy 2: Lowercase match
        $lowerName = strtolower($pjaName);
        if (isset($pjaMap['lower'][$lowerName])) {
            return $pjaMap['lower'][$lowerName];
        }

        // Strategy 3: Normalized match
        $normalized = $this->normalizePjaName($pjaName);
        if (isset($pjaMap['normalized'][$normalized])) {
            return $pjaMap['normalized'][$normalized];
        }

        // Strategy 4: Similarity match
        if (isset($pjaMap['all'])) {
            $bestMatch = null;
            $bestScore = 0;

            foreach ($pjaMap['all'] as $pja) {
                $similarity = $this->calculateSimilarity($normalized, $pja['normalized']);
                if ($similarity > $bestScore && $similarity >= 0.8) {
                    $bestScore = $similarity;
                    $bestMatch = $pja;
                }
            }

            if ($bestMatch) {
                return $bestMatch['pja_id'];
            }
        }

        return null;
    }

    /**
     * Find CCTV dari cache (sangat cepat!)
     */
    private function findCctvFromCache($cctvName, $cctvMap): ?int
    {
        if (empty($cctvName) || empty($cctvMap)) {
            return null;
        }

        // Strategy 1: Exact match no_cctv
        if (isset($cctvMap['no_cctv'][$cctvName])) {
            return $cctvMap['no_cctv'][$cctvName];
        }

        // Strategy 2: Exact match nama_cctv
        if (isset($cctvMap['nama_cctv'][$cctvName])) {
            return $cctvMap['nama_cctv'][$cctvName];
        }

        // Strategy 3: Lowercase match
        $lowerName = strtolower($cctvName);
        if (isset($cctvMap['no_cctv_lower'][$lowerName])) {
            return $cctvMap['no_cctv_lower'][$lowerName];
        }
        if (isset($cctvMap['nama_cctv_lower'][$lowerName])) {
            return $cctvMap['nama_cctv_lower'][$lowerName];
        }

        // Strategy 4: Extract number dan match
        $extractedNumber = $this->extractNumberFromCctvName($cctvName);
        if ($extractedNumber !== null && isset($cctvMap['by_number'])) {
            $normalizedNumber = ltrim($extractedNumber, '0');
            if (empty($normalizedNumber)) {
                $normalizedNumber = '0';
            }

            if (isset($cctvMap['by_number'][$normalizedNumber])) {
                // Cari yang paling cocok dari hasil
                foreach ($cctvMap['by_number'][$normalizedNumber] as $cctv) {
                    // Cek apakah ada pattern yang cocok
                    if (stripos($cctv['no_cctv'], $normalizedNumber) !== false ||
                        stripos($cctv['nama_cctv'], $normalizedNumber) !== false) {
                        return $cctv['id'];
                    }
                }
                // Jika tidak ada yang cocok, ambil yang pertama
                return $cctvMap['by_number'][$normalizedNumber][0]['id'] ?? null;
            }
        }

        // Strategy 5: Fuzzy match
        if (isset($cctvMap['all'])) {
            $normalizedSearch = strtolower(preg_replace('/[\s\-_]/', '', $cctvName));
            
            foreach ($cctvMap['all'] as $cctv) {
                $normalizedNo = strtolower(preg_replace('/[\s\-_]/', '', $cctv['no_cctv']));
                $normalizedNama = strtolower(preg_replace('/[\s\-_]/', '', $cctv['nama_cctv']));
                
                if (strpos($normalizedNo, $normalizedSearch) !== false ||
                    strpos($normalizedSearch, $normalizedNo) !== false ||
                    strpos($normalizedNama, $normalizedSearch) !== false ||
                    strpos($normalizedSearch, $normalizedNama) !== false) {
                    return $cctv['id'];
                }
            }
        }

        return null;
    }

    /**
     * Normalize PJA name untuk handle typo
     */
    private function normalizePjaName($name)
    {
        if (empty($name)) {
            return '';
        }
        $normalized = strtolower(trim($name));
        $replacements = [
            'fasility' => 'facility',
            'infrastrukture' => 'infrastructure',
            'infrastruktur' => 'infrastructure',
        ];
        foreach ($replacements as $wrong => $correct) {
            $normalized = str_replace($wrong, $correct, $normalized);
        }
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return trim($normalized);
    }

    /**
     * Calculate similarity between two strings
     */
    private function calculateSimilarity($str1, $str2)
    {
        if (empty($str1) || empty($str2)) {
            return 0;
        }
        if ($str1 === $str2) {
            return 1.0;
        }
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) {
            return 1.0;
        }
        $distance = levenshtein($str1, $str2);
        return 1 - ($distance / $maxLen);
    }

    /**
     * Extract nomor dari nama CCTV
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
        // Pattern 3: "LMO-FAD-0001" -> extract "0001"
        if (preg_match('/-(\d+)$/', $name, $matches)) {
            return $matches[1];
        }
        // Pattern 4: Extract nomor pertama yang ditemukan
        if (preg_match('/(\d+)/', $name, $matches)) {
            return $matches[1];
        }
        return null;
    }
}

