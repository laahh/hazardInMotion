<?php

declare(strict_types=1);

namespace App\Jobs\EmergencyResponse;

use App\Models\EmergencyResponse\Manpower\Employee;
use App\Models\EmergencyResponse\MasterData\Department;
use App\Models\EmergencyResponse\MasterData\EmergencyUnit;
use App\Models\EmergencyResponse\MasterData\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;

/**
 * Kolom template (baris 1 header, diabaikan): No. Pegawai | Nama Lengkap |
 * Jabatan | Departemen (kode) | Unit Emergency (kode) | Site (kode) |
 * Email | Telepon | Status Pekerjaan | Peran Emergency
 */
class ImportEmployeeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected string $relativePath, protected int $importedBy) {}

    public function handle(): void
    {
        $fullPath = storage_path('app/'.$this->relativePath);

        if (! file_exists($fullPath)) {
            Log::warning('ImportEmployeeJob file not found: '.$fullPath);

            return;
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (SpreadsheetException $e) {
            Log::error('ImportEmployeeJob spreadsheet error: '.$e->getMessage());

            return;
        } finally {
            @unlink($fullPath);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $departmentsByCode = Department::query()->pluck('id', 'code');
        $emergencyUnitsByCode = EmergencyUnit::query()->pluck('id', 'code');
        $sitesByCode = Site::query()->pluck('id', 'code');

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $employeeNumber = trim((string) ($row[0] ?? ''));
            $fullName = trim((string) ($row[1] ?? ''));

            if ($employeeNumber === '' || $fullName === '') {
                continue;
            }

            $employmentStatus = trim((string) ($row[8] ?? ''));

            Employee::updateOrCreate(
                ['employee_number' => $employeeNumber],
                [
                    'full_name' => $fullName,
                    'position' => trim((string) ($row[2] ?? '')) ?: null,
                    'department_id' => $departmentsByCode[trim((string) ($row[3] ?? ''))] ?? null,
                    'emergency_unit_id' => $emergencyUnitsByCode[trim((string) ($row[4] ?? ''))] ?? null,
                    'site_id' => $sitesByCode[trim((string) ($row[5] ?? ''))] ?? null,
                    'email' => trim((string) ($row[6] ?? '')) ?: null,
                    'phone' => trim((string) ($row[7] ?? '')) ?: null,
                    'employment_status' => array_key_exists($employmentStatus, Employee::EMPLOYMENT_STATUSES) ? $employmentStatus : 'permanent',
                    'emergency_role' => trim((string) ($row[9] ?? '')) ?: null,
                    'is_active' => true,
                    'created_by' => $this->importedBy,
                    'updated_by' => $this->importedBy,
                ],
            );
        }
    }
}
