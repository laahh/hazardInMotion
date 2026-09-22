<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringInventoryTool;
use Illuminate\Support\Facades\DB;

final class PncMonitoringInventoryUpsertService
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsert(array $rows, ?int $userId): PncMonitoringExcelUpsertResult
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $userId, &$created, &$updated, &$errors): void {
            foreach ($rows as $row) {
                $upsertKey = trim((string) ($row['upsert_key'] ?? ''));
                $namaAlat = trim((string) ($row['nama_alat'] ?? ''));
                if ($upsertKey === '' || $namaAlat === '') {
                    $errors[] = 'Nama Alat/upsert key kosong — baris dilewati.';
                    continue;
                }

                $payload = $this->payloadFromRow($row);
                $payload['upsert_key'] = $upsertKey;
                $payload['nama_alat'] = $namaAlat;
                $payload['updated_by'] = $userId;

                $existing = PncMonitoringInventoryTool::query()->where('upsert_key', $upsertKey)->first();
                if ($existing === null) {
                    $payload['created_by'] = $userId;
                    PncMonitoringInventoryTool::query()->create($payload);
                    $created++;
                    continue;
                }

                $existing->fill($payload);
                $existing->save();
                $updated++;
            }
        });

        return new PncMonitoringExcelUpsertResult($created, $updated, $errors, []);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function payloadFromRow(array $row): array
    {
        return [
            'category' => $this->nullableString($row['category'] ?? null),
            'asset_id' => $this->nullableString($row['asset_id'] ?? null),
            'sub_kategori' => $this->nullableString($row['sub_kategori'] ?? null),
            'brand' => $this->nullableString($row['brand'] ?? null),
            'model' => $this->nullableString($row['model'] ?? null),
            'serial_number' => $this->nullableString($row['serial_number'] ?? null),
            'tahun_pembuatan' => $this->nullableString($row['tahun_pembuatan'] ?? null),
            'power_source' => $this->nullableString($row['power_source'] ?? null),
            'kapasitas_rating' => $this->nullableString($row['kapasitas_rating'] ?? null),
            'site' => $this->nullableString($row['site'] ?? null),
            'lokasi_detail' => $this->nullableString($row['lokasi_detail'] ?? null),
            'status_ketersediaan' => $this->nullableString($row['status_ketersediaan'] ?? null),
            'condition' => $this->nullableString($row['condition'] ?? null),
            'pic' => $this->nullableString($row['pic'] ?? null),
            'qty_on_hand' => $row['qty_on_hand'] ?? 1,
            'calibration_required' => $row['calibration_required'] ?? null,
            'last_calibration_date' => $this->nullableString($row['last_calibration_date'] ?? null),
            'calibration_due_date' => $this->nullableString($row['calibration_due_date'] ?? null),
            'inspection_required' => $row['inspection_required'] ?? null,
            'last_inspection_date' => $this->nullableString($row['last_inspection_date'] ?? null),
            'next_inspection_due' => $this->nullableString($row['next_inspection_due'] ?? null),
            'inspection_result' => $this->nullableString($row['inspection_result'] ?? null),
            'pm_required' => $row['pm_required'] ?? null,
            'last_pm_date' => $this->nullableString($row['last_pm_date'] ?? null),
            'next_pm_due' => $this->nullableString($row['next_pm_due'] ?? null),
            'catatan' => $this->nullableString($row['catatan'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
