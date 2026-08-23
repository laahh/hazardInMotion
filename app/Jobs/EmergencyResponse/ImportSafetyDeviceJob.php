<?php

declare(strict_types=1);

namespace App\Jobs\EmergencyResponse;

use App\Models\EmergencyResponse\MasterData\SafetyDeviceType;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\EmergencyResponse\SafetyDevice\SafetyDevice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;

/**
 * Kolom template (baris 1 header, diabaikan): Kode | Nama | Jenis (kode) |
 * Merek | Model | No. Seri | Site (kode) | Kondisi | Status Operasional
 */
class ImportSafetyDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected string $relativePath, protected int $importedBy) {}

    public function handle(): void
    {
        $fullPath = storage_path('app/'.$this->relativePath);

        if (! file_exists($fullPath)) {
            Log::warning('ImportSafetyDeviceJob file not found: '.$fullPath);

            return;
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (SpreadsheetException $e) {
            Log::error('ImportSafetyDeviceJob spreadsheet error: '.$e->getMessage());

            return;
        } finally {
            @unlink($fullPath);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $typesByCode = SafetyDeviceType::query()->pluck('id', 'code');
        $sitesByCode = Site::query()->pluck('id', 'code');

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $code = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));

            if ($code === '' || $name === '') {
                continue;
            }

            SafetyDevice::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'safety_device_type_id' => $typesByCode[trim((string) ($row[2] ?? ''))] ?? null,
                    'brand' => trim((string) ($row[3] ?? '')) ?: null,
                    'model' => trim((string) ($row[4] ?? '')) ?: null,
                    'serial_number' => trim((string) ($row[5] ?? '')) ?: null,
                    'site_id' => $sitesByCode[trim((string) ($row[6] ?? ''))] ?? null,
                    'condition' => array_key_exists($row[7] ?? '', SafetyDevice::CONDITIONS) ? $row[7] : 'baik',
                    'operational_status' => array_key_exists($row[8] ?? '', SafetyDevice::OPERATIONAL_STATUSES) ? $row[8] : 'available',
                    'created_by' => $this->importedBy,
                    'updated_by' => $this->importedBy,
                ],
            );
        }
    }
}
