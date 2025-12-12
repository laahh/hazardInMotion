<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;
use Illuminate\Support\Facades\Log;

class ImportBaselinePjaJob implements ShouldQueue
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

        if (! file_exists($fullPath)) {
            Log::warning('ImportBaselinePjaJob file not found: ' . $fullPath);
            return;
        }

        // Check if table exists
        try {
            if (! DB::getSchemaBuilder()->hasTable('baseline_pja')) {
                Log::error('ImportBaselinePjaJob: Table baseline_pja does not exist. Please run migrations first.');
                @unlink($fullPath);
                throw new \Exception('Table baseline_pja does not exist. Please run migrations first.');
            }
        } catch (\Exception $e) {
            Log::error('ImportBaselinePjaJob table check error: ' . $e->getMessage());
            @unlink($fullPath);
            throw $e;
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (SpreadsheetException $e) {
            Log::error('ImportBaselinePjaJob spreadsheet error: ' . $e->getMessage());
            @unlink($fullPath);
            throw $e; // Re-throw to mark job as failed
        }

        try {
            $sheet = $spreadsheet->getActiveSheet();
            
            // Read all data into array
            $rows = $sheet->toArray(null, true, true, false);
            
            // Clean up file and spreadsheet from memory as soon as possible
            @unlink($fullPath);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            unset($sheet);

            $totalRows = count($rows);
            Log::info("ImportBaselinePjaJob: Processing {$totalRows} rows");

            if ($totalRows <= 1) {
                Log::info('ImportBaselinePjaJob: No data rows found (only header or empty)');
                return;
            }

            $batch = [];
            $batchSize = 500; // Reduced batch size for better memory management
            $processedCount = 0;
            $skippedCount = 0;
            $now = now();
            
            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue; // Skip header
                }

                $site = isset($row[0]) ? trim((string) $row[0]) : null;
                $perusahaan = isset($row[1]) ? trim((string) $row[1]) : null;
                $idLokasi = isset($row[2]) ? trim((string) $row[2]) : null;
                $lokasi = isset($row[3]) ? trim((string) $row[3]) : null;
                $idPja = isset($row[4]) ? trim((string) $row[4]) : null;
                $pja = isset($row[5]) ? trim((string) $row[5]) : null;
                $tipePja = isset($row[6]) ? trim((string) $row[6]) : null;

                // Skip if all fields are empty
                if (empty($site) && empty($perusahaan) && empty($idLokasi) && empty($lokasi) && empty($idPja) && empty($pja) && empty($tipePja)) {
                    $skippedCount++;
                    continue;
                }

                $batch[] = [
                    'site' => $site ?: null,
                    'perusahaan' => $perusahaan ?: null,
                    'id_lokasi' => $idLokasi ?: null,
                    'lokasi' => $lokasi ?: null,
                    'id_pja' => $idPja ?: null,
                    'pja' => $pja ?: null,
                    'tipe_pja' => $tipePja ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= $batchSize) {
                    try {
                        DB::table('baseline_pja')->insert($batch);
                        $processedCount += count($batch);
                        $batch = [];
                        
                        // Log progress every 5000 records
                        if ($processedCount % 5000 == 0) {
                            Log::info("ImportBaselinePjaJob progress: {$processedCount} records processed");
                        }
                    } catch (\Exception $e) {
                        Log::error('ImportBaselinePjaJob batch insert error: ' . $e->getMessage());
                        throw $e;
                    }
                }
            }

            if (! empty($batch)) {
                try {
                    DB::table('baseline_pja')->insert($batch);
                    $processedCount += count($batch);
                } catch (\Exception $e) {
                    Log::error('ImportBaselinePjaJob final batch insert error: ' . $e->getMessage());
                    throw $e;
                }
            }

            Log::info("ImportBaselinePjaJob completed. Processed {$processedCount} records, skipped {$skippedCount} empty rows.");
        } catch (\Exception $e) {
            Log::error('ImportBaselinePjaJob error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ImportBaselinePjaJob failed permanently: ' . $exception->getMessage(), [
            'path' => $this->relativePath,
            'trace' => $exception->getTraceAsString()
        ]);
    }
}

