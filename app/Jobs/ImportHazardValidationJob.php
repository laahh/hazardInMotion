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

class ImportHazardValidationJob implements ShouldQueue
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
            Log::warning('ImportHazardValidationJob file not found: ' . $fullPath);
            return;
        }

        // Check if table exists
        try {
            if (! DB::getSchemaBuilder()->hasTable('hazard_validations')) {
                Log::error('ImportHazardValidationJob: Table hazard_validations does not exist. Please run migrations first.');
                @unlink($fullPath);
                throw new \Exception('Table hazard_validations does not exist. Please run migrations first.');
            }
        } catch (\Exception $e) {
            Log::error('ImportHazardValidationJob table check error: ' . $e->getMessage());
            @unlink($fullPath);
            throw $e;
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (SpreadsheetException $e) {
            Log::error('ImportHazardValidationJob spreadsheet error: ' . $e->getMessage());
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
            Log::info("ImportHazardValidationJob: Processing {$totalRows} rows");

            if ($totalRows <= 1) {
                Log::info('ImportHazardValidationJob: No data rows found (only header or empty)');
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

                $validator = isset($row[0]) ? trim((string) $row[0]) : null;
                $tasklist = isset($row[1]) ? trim((string) $row[1]) : null;
                $tobeConcernedHazard = isset($row[2]) ? trim((string) $row[2]) : null;
                $gr = isset($row[3]) ? trim((string) $row[3]) : null;
                $catatan = isset($row[4]) ? trim((string) $row[4]) : null;

                // Skip if tasklist or gr is empty
                if (empty($tasklist) && empty($gr)) {
                    $skippedCount++;
                    continue;
                }

                $batch[] = [
                    'validator' => $validator ?: null,
                    'tasklist' => $tasklist ?? '',
                    'tobe_concerned_hazard' => $tobeConcernedHazard ?: null,
                    'gr' => $gr ?? '',
                    'catatan' => $catatan ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= $batchSize) {
                    try {
                        DB::table('hazard_validations')->insert($batch);
                        $processedCount += count($batch);
                        $batch = [];
                        
                        // Log progress every 5000 records
                        if ($processedCount % 5000 == 0) {
                            Log::info("ImportHazardValidationJob progress: {$processedCount} records processed");
                        }
                    } catch (\Exception $e) {
                        Log::error('ImportHazardValidationJob batch insert error: ' . $e->getMessage());
                        throw $e;
                    }
                }
            }

            if (! empty($batch)) {
                try {
                    DB::table('hazard_validations')->insert($batch);
                    $processedCount += count($batch);
                } catch (\Exception $e) {
                    Log::error('ImportHazardValidationJob final batch insert error: ' . $e->getMessage());
                    throw $e;
                }
            }

            Log::info("ImportHazardValidationJob completed. Processed {$processedCount} records, skipped {$skippedCount} empty rows.");
        } catch (\Exception $e) {
            Log::error('ImportHazardValidationJob error: ' . $e->getMessage(), [
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
        Log::error('ImportHazardValidationJob failed permanently: ' . $exception->getMessage(), [
            'path' => $this->relativePath,
            'trace' => $exception->getTraceAsString()
        ]);
    }
}

