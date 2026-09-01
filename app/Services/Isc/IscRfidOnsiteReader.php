<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader;
use Illuminate\Support\Carbon;
use Throwable;

final class IscRfidOnsiteReader
{
    public function __construct(
        private readonly SportEvaluationPvtRfidCheckinReader $rfid,
    ) {}

    public function isUp(): bool
    {
        return $this->rfid->isUp();
    }

    /**
     * @param  list<string>  $sids
     * @return array{available:bool,people:list<array<string,mixed>>}
     */
    public function onsiteToday(array $sids): array
    {
        if ($sids === []) {
            return ['available' => $this->isUp(), 'people' => []];
        }

        try {
            if (! $this->isUp()) {
                return ['available' => false, 'people' => []];
            }

            $date = Carbon::now((string) config('app.timezone'))->toDateString();
            $checkins = $this->rfid->firstPassedCheckinsForSids($date, $sids);
            $checkouts = $this->rfid->lastPassedCheckoutsForSids($date, $sids);

            $people = [];
            foreach ($checkins as $upper => $row) {
                $inAt = (string) ($row['checked_in_at'] ?? '');
                $outAt = (string) ($checkouts[$upper]['checked_in_at'] ?? '');
                if ($outAt !== '' && $inAt !== '' && $outAt > $inAt) {
                    continue;
                }
                $people[] = [
                    'sid' => (string) ($row['kode_sid'] ?? $upper),
                    'name' => $this->nullableString($row['nama_karyawan'] ?? null),
                    'company' => $this->nullableString($row['perusahaan'] ?? null),
                    'gate' => $this->nullableString($row['gate'] ?? null),
                    'checked_in_at' => $inAt !== '' ? $inAt : null,
                    'checked_out_at' => ($outAt !== '' && $outAt > $inAt) ? $outAt : null,
                ];
            }

            return ['available' => true, 'people' => $people];
        } catch (Throwable $e) {
            report($e);

            return ['available' => false, 'people' => []];
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
