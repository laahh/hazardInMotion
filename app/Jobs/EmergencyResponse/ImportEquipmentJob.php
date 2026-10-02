<?php

declare(strict_types=1);

namespace App\Jobs\EmergencyResponse;

use App\Models\Company;
use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\MasterData\EquipmentCategory;
use App\Models\EmergencyResponse\MasterData\Site;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import register peralatan emergency dari template yang dibuat
 * EquipmentImportTemplate (sheet "Data"). Kategori, SITE dan Perusahaan
 * dicocokkan berdasarkan nama — kode juga diterima untuk kategori/site.
 *
 * UUID peralatan tidak ada di template: nilainya digenerate model dari
 * site-kategori-nama-urutan. Baris dengan No Registrasi yang sama akan
 * memperbarui data lama, baris tanpa No Registrasi selalu dibuat baru.
 *
 * Nilai yang tidak dikenali tidak membatalkan baris — kolomnya dikosongkan
 * dan seluruh penolakan dicatat sebagai satu ringkasan di log.
 */
class ImportEquipmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TEMPLATE_HEADERS = [
        'Kategori Peralatan', 'Nama Peralatan', 'No Registrasi', 'Detail Peralatan',
        'Klasifikasi Alat', 'Kondisi Peralatan', 'Keterangan Alat', 'Keterangan Kerusakan',
        'Status Posisi Barang', 'SITE', 'Perusahaan', 'Progress BA', 'Keterangan BA',
        'Status Barang', 'Tanggal Close BA',
    ];

    private const DATA_SHEET = 'Data';

    /** @var array<int, string> nilai yang ditolak, untuk ringkasan log */
    private array $rejected = [];

    public function __construct(protected string $relativePath, protected int $importedBy) {}

    public function handle(): void
    {
        $fullPath = storage_path('app/'.$this->relativePath);

        if (! file_exists($fullPath)) {
            Log::warning('ImportEquipmentJob file not found: '.$fullPath);

            return;
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (SpreadsheetException $e) {
            Log::error('ImportEquipmentJob spreadsheet error: '.$e->getMessage());

            return;
        } finally {
            @unlink($fullPath);
        }

        $sheet = $spreadsheet->getSheetByName(self::DATA_SHEET) ?? $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $categories = $this->lookupMap(EquipmentCategory::query()->get(['id', 'code', 'name']));
        $sites = $this->lookupMap(Site::query()->get(['id', 'code', 'name']));
        $companies = $this->lookupMap(Company::query()->get(['id', 'code', 'name']));

        $created = 0;
        $updated = 0;

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $name = $this->text($row[1] ?? null);

            if ($name === null) {
                continue;
            }

            $rowNumber = $index + 1;
            $registrationNumber = $this->text($row[2] ?? null);

            $attributes = [
                'name' => $name,
                'equipment_category_id' => $this->reference($row[0] ?? null, $categories, 'Kategori Peralatan', $rowNumber),
                'registration_number' => $registrationNumber,
                'equipment_detail' => $this->text($row[3] ?? null),
                'classification' => $this->text($row[4] ?? null),
                'condition' => $this->option($row[5] ?? null, EmergencyEquipment::CONDITIONS, 'Kondisi Peralatan', $rowNumber) ?? 'baik',
                'equipment_remarks' => $this->text($row[6] ?? null),
                'damage_remarks' => $this->text($row[7] ?? null),
                'position_status' => $this->option($row[8] ?? null, EmergencyEquipment::POSITION_STATUSES, 'Status Posisi Barang', $rowNumber),
                'site_id' => $this->reference($row[9] ?? null, $sites, 'SITE', $rowNumber),
                'company_id' => $this->reference($row[10] ?? null, $companies, 'Perusahaan', $rowNumber),
                'ba_progress' => $this->option($row[11] ?? null, EmergencyEquipment::BA_PROGRESSES, 'Progress BA', $rowNumber),
                'ba_remarks' => $this->text($row[12] ?? null),
                'item_status' => $this->option($row[13] ?? null, EmergencyEquipment::ITEM_STATUSES, 'Status Barang', $rowNumber),
                'ba_closed_at' => $this->date($row[14] ?? null, $rowNumber),
                'updated_by' => $this->importedBy,
            ];

            $existing = $registrationNumber === null
                ? null
                : EmergencyEquipment::query()->where('registration_number', $registrationNumber)->first();

            if ($existing !== null) {
                $existing->update($attributes);
                $updated++;

                continue;
            }

            EmergencyEquipment::create($attributes + ['created_by' => $this->importedBy]);
            $created++;
        }

        $this->logSummary($created, $updated);
    }

    private function logSummary(int $created, int $updated): void
    {
        $context = [
            'file' => $this->relativePath,
            'imported_by' => $this->importedBy,
            'created' => $created,
            'updated' => $updated,
            'rejected_count' => count($this->rejected),
        ];

        if ($this->rejected === []) {
            Log::info('ImportEquipmentJob selesai', $context);

            return;
        }

        // Dibatasi supaya file upload yang kacau tidak membanjiri log.
        $context['rejected'] = array_slice($this->rejected, 0, 50);

        Log::warning('ImportEquipmentJob selesai dengan nilai yang tidak dikenali', $context);
    }

    /**
     * Mencocokkan kolom relasi; nilai yang tidak ada di database dicatat.
     *
     * @param  array<string, mixed>  $map
     */
    private function reference(mixed $value, array $map, string $column, int $rowNumber): mixed
    {
        $key = $this->key($value);

        if ($key === null) {
            return null;
        }

        if (array_key_exists($key, $map)) {
            return $map[$key];
        }

        $this->reject($column, $rowNumber, $value);

        return null;
    }

    /**
     * Peta kode dan nama (lowercase) ke id, supaya sheet boleh memakai salah satu.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $records
     * @return array<string, mixed>
     */
    private function lookupMap($records): array
    {
        $map = [];

        foreach ($records as $record) {
            foreach ([$record->code ?? null, $record->name ?? null] as $label) {
                $key = $this->key($label);

                if ($key !== null) {
                    $map[$key] = $record->id;
                }
            }
        }

        return $map;
    }

    private function key(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === null ? null : mb_strtolower($text);
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    /**
     * Menerima key (mis. "di_tempat") maupun label (mis. "Di Tempat").
     *
     * @param  array<string, string>  $options
     */
    private function option(mixed $value, array $options, string $column, int $rowNumber): ?string
    {
        $text = $this->key($value);

        if ($text === null) {
            return null;
        }

        if (array_key_exists($text, $options)) {
            return $text;
        }

        foreach ($options as $key => $label) {
            if (mb_strtolower($label) === $text) {
                return $key;
            }
        }

        $this->reject($column, $rowNumber, $value);

        return null;
    }

    private function date(mixed $value, int $rowNumber): ?string
    {
        if ($this->text($value) === null) {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (InvalidFormatException) {
            $this->reject('Tanggal Close BA', $rowNumber, $value);

            return null;
        }
    }

    private function reject(string $column, int $rowNumber, mixed $value): void
    {
        $this->rejected[] = sprintf('baris %d, %s: "%s"', $rowNumber, $column, (string) $value);
    }
}
