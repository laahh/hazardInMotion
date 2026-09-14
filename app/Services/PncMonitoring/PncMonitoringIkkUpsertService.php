<?php

declare(strict_types=1);

namespace App\Services\PncMonitoring;

use App\Models\PncMonitoring\PncMonitoringIkkRecord;
use Illuminate\Support\Facades\DB;

final class PncMonitoringIkkUpsertService
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
                $nomor = trim((string) ($row['nomor'] ?? ''));
                if ($upsertKey === '' || $nomor === '') {
                    $errors[] = 'Nomor/upsert key kosong — baris dilewati.';
                    continue;
                }

                $payload = $this->payloadFromRow($row);
                $payload['upsert_key'] = $upsertKey;
                $payload['nomor'] = $nomor;
                $payload['updated_by'] = $userId;

                $existing = PncMonitoringIkkRecord::query()->where('upsert_key', $upsertKey)->first();
                if ($existing === null) {
                    $payload['created_by'] = $userId;
                    PncMonitoringIkkRecord::query()->create($payload);
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
            'jenis' => $this->nullableString($row['jenis'] ?? null),
            'pekerjaan' => $this->nullableString($row['pekerjaan'] ?? null),
            'tanggal' => $this->nullableString($row['tanggal'] ?? null),
            'minggu' => $this->nullableInt($row['minggu'] ?? null),
            'bulan' => $this->nullableInt($row['bulan'] ?? null),
            'tahun' => $this->nullableInt($row['tahun'] ?? null),
            'site' => $this->nullableString($row['site'] ?? null),
            'mine_contractor' => $this->nullableString($row['mine_contractor'] ?? null),
            'perusahaan' => $this->nullableString($row['perusahaan'] ?? null),
            'finding_ia' => $this->intOrZero($row['finding_ia'] ?? null),
            'finding_verlap' => $this->intOrZero($row['finding_verlap'] ?? null),
            'ia' => $this->nullableInt($row['ia'] ?? null),
            'ipk' => $this->nullableInt($row['ipk'] ?? null),
            'plan_okk' => $this->intOrZero($row['plan_okk'] ?? null),
            'okk_1' => $this->intOrZero($row['okk_1'] ?? null),
            'okk_2' => $this->intOrZero($row['okk_2'] ?? null),
            'okk_3' => $this->intOrZero($row['okk_3'] ?? null),
            'okk_layer_2' => $this->intOrZero($row['okk_layer_2'] ?? null),
            'okk_layer_3' => $this->intOrZero($row['okk_layer_3'] ?? null),
            'okk_layer_4' => $this->intOrZero($row['okk_layer_4'] ?? null),
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

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function intOrZero(mixed $value): int
    {
        return $this->nullableInt($value) ?? 0;
    }
}
