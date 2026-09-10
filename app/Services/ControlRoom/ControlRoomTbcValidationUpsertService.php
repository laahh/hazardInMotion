<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Models\ControlRoom\ControlRoomTbcValidation;
use Illuminate\Support\Facades\DB;

final class ControlRoomTbcValidationUpsertService
{
    /**
     * @param  list<array<string, ?string>>  $rows
     */
    public function upsert(array $rows, ?int $userId): ControlRoomTbcValidationUpsertResult
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $userId, &$created, &$updated, &$errors): void {
            foreach ($rows as $row) {
                $tasklist = trim((string) ($row['tasklist'] ?? ''));
                if ($tasklist === '') {
                    $errors[] = 'Tasklist kosong — baris dilewati.';
                    continue;
                }

                $payload = [];
                foreach (ControlRoomTbcValidation::FILLABLE_FIELDS as $field) {
                    $payload[$field] = $this->nullableString($row[$field] ?? null);
                }
                $payload['tasklist'] = $tasklist;
                $payload['updated_by'] = $userId;

                $existing = ControlRoomTbcValidation::query()->where('tasklist', $tasklist)->first();
                if ($existing === null) {
                    $payload['created_by'] = $userId;
                    ControlRoomTbcValidation::query()->create($payload);
                    $created++;
                    continue;
                }

                $existing->fill($payload);
                $existing->save();
                $updated++;
            }
        });

        return new ControlRoomTbcValidationUpsertResult($created, $updated, $errors, []);
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
