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

class ImportGrTableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
            Log::warning('ImportGrTableJob file not found: ' . $fullPath);
            return;
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (SpreadsheetException $e) {
            Log::error('ImportGrTableJob spreadsheet error: ' . $e->getMessage());
            return;
        } finally {
            @unlink($fullPath);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if (count($rows) <= 1) {
            return;
        }

        $batch = [];
        $batchSize = 1000;
        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $tasklist = $row[0] ?? null;
            $gr = $row[1] ?? null;

            if (! $tasklist || ! $gr) {
                continue;
            }

            $batch[] = [
                'tasklist' => trim((string) $tasklist),
                'gr' => trim((string) $gr),
                'catatan' => isset($row[2]) ? trim((string) $row[2]) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= $batchSize) {
                DB::table('gr_table')->insert($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('gr_table')->insert($batch);
        }
    }
}

