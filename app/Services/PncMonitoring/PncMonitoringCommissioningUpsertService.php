<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringCommissioning;
use Illuminate\Support\Facades\DB;

final class PncMonitoringCommissioningUpsertService
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
                $register = trim((string) ($row['no_register_spip'] ?? ''));
                if ($register === '') {
                    $errors[] = 'No Register SPIP kosong — baris dilewati.';
                    continue;
                }

                $payload = [
                    'site' => $this->nullableString($row['site'] ?? null),
                    'no_register_spip' => $register,
                    'detail_jenis_spip' => $this->nullableString($row['detail_jenis_spip'] ?? null),
                    'keterangan_sko' => $this->nullableString($row['keterangan_sko'] ?? null),
                    'nama_pengawas_teknis' => $this->nullableString($row['nama_pengawas_teknis'] ?? null),
                    'permohonan_dokumen_1' => $this->nullableString($row['permohonan_dokumen_1'] ?? null),
                    'week' => $this->nullableInt($row['week'] ?? null),
                    'tahun' => $this->nullableInt($row['tahun'] ?? null),
                    'pemilik_spip' => $this->nullableString($row['pemilik_spip'] ?? null),
                    'pengelola_spip' => $this->nullableString($row['pengelola_spip'] ?? null),
                    'temuan_komisioning' => $this->nullableInt($row['temuan_komisioning'] ?? null) ?? 0,
                    'status_komisioning' => $this->nullableString($row['status_komisioning'] ?? null),
                    'keterangan' => $this->nullableString($row['keterangan'] ?? null),
                    'alasan_reject' => $this->nullableString($row['alasan_reject'] ?? null),
                    'status' => $this->nullableString($row['status'] ?? null),
                    'performance_sko' => $this->nullableFloat($row['performance_sko'] ?? null),
                    'updated_by' => $userId,
                ];

                $existing = PncMonitoringCommissioning::query()->where('no_register_spip', $register)->first();
                if ($existing === null) {
                    $payload['created_by'] = $userId;
                    PncMonitoringCommissioning::query()->create($payload);
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

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
