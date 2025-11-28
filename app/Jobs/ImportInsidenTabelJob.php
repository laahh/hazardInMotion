<?php

namespace App\Jobs;

use App\Models\InsidenTabel;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;

class ImportInsidenTabelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $relativePath;

    protected array $columns = [
        'no_kecelakaan',
        'kode_be_investigasi',
        'status_lpi',
        'target_penyelesaian_lpi',
        'actual_penyelesaian_lpi',
        'ketepatan_waktu_lpi',
        'tanggal',
        'bulan',
        'tahun',
        'minggu_ke',
        'hari',
        'jam',
        'menit',
        'shift',
        'perusahaan',
        'latitude',
        'longitude',
        'departemen',
        'site',
        'lokasi',
        'sublokasi',
        'lokasi_spesifik',
        'lokasi_validasi_hsecm',
        'pja',
        'insiden_dalam_site_mining',
        'kategori',
        'injury_status',
        'kronologis',
        'high_potential',
        'alat_terlibat',
        'nama',
        'jabatan',
        'shift_kerja_ke',
        'hari_kerja_ke',
        'npk',
        'umur',
        'range_umur',
        'masa_kerja_perusahaan_tahun',
        'masa_kerja_perusahaan_bulan',
        'range_masa_kerja_perusahaan',
        'masa_kerja_bc_tahun',
        'masa_kerja_bc_bulan',
        'range_masa_kerja_bc',
        'bagian_luka',
        'loss_cost',
        'saksi_langsung',
        'atasan_langsung',
        'jabatan_atasan_langsung',
        'kontak',
        'detail_kontak',
        'sumber_kecelakaan',
        'layer',
        'jenis_item_ipls',
        'detail_layer',
        'klasifikasi_layer',
        'keterangan_layer',
        'id_lokasi_insiden',
    ];

    protected array $dateColumns = [
        'target_penyelesaian_lpi',
        'actual_penyelesaian_lpi',
        'tanggal',
    ];

    protected array $integerColumns = [
        'bulan',
        'tahun',
        'minggu_ke',
        'jam',
        'menit',
        'shift_kerja_ke',
        'hari_kerja_ke',
        'umur',
        'masa_kerja_perusahaan_tahun',
        'masa_kerja_perusahaan_bulan',
        'masa_kerja_bc_tahun',
        'masa_kerja_bc_bulan',
    ];

    protected array $decimalColumns = [
        'latitude',
        'longitude',
        'loss_cost',
    ];

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
            Log::warning('ImportInsidenTabelJob file missing: ' . $fullPath);
            return;
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (SpreadsheetException $e) {
            Log::error('ImportInsidenTabelJob unable to read file: ' . $e->getMessage());
            @unlink($fullPath);
            return;
        }

        @unlink($fullPath);

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if (count($rows) <= 1) {
            return;
        }

        // Load existing no_kecelakaan untuk pengecekan duplicate
        $existingNoKecelakaan = InsidenTabel::pluck('no_kecelakaan')->toArray();
        $existingNoKecelakaan = array_flip($existingNoKecelakaan); // Convert to hash map untuk lookup O(1)

        $batch = [];
        $batchSize = 500;
        $skippedCount = 0;
        $insertedCount = 0;

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            if (! isset($row[0]) || trim((string) $row[0]) === '') {
                continue;
            }

            $payload = [];

            foreach ($this->columns as $colIndex => $columnName) {
                $value = $row[$colIndex] ?? null;
                $payload[$columnName] = $this->normalizeValue($value, $columnName);
            }

            // Cek apakah no_kecelakaan sudah ada (duplicate)
            $noKecelakaan = $payload['no_kecelakaan'] ?? null;
            if ($noKecelakaan && isset($existingNoKecelakaan[$noKecelakaan])) {
                $skippedCount++;
                continue; // Skip data duplicate
            }

            $payload['created_at'] = now();
            $payload['updated_at'] = now();
            $batch[] = $payload;

            // Tambahkan no_kecelakaan ke existing list untuk menghindari duplicate dalam batch yang sama
            if ($noKecelakaan) {
                $existingNoKecelakaan[$noKecelakaan] = true;
            }

            if (count($batch) >= $batchSize) {
                InsidenTabel::insert($batch);
                $insertedCount += count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            InsidenTabel::insert($batch);
            $insertedCount += count($batch);
        }

        Log::info("ImportInsidenTabelJob completed. Inserted: {$insertedCount}, Skipped (duplicate): {$skippedCount}");
    }

    protected function normalizeValue($value, string $column)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($column, $this->dateColumns, true)) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        if (in_array($column, $this->integerColumns, true)) {
            return (int) $value;
        }

        if (in_array($column, $this->decimalColumns, true)) {
            return (float) $value;
        }

        return trim((string) $value);
    }
}

