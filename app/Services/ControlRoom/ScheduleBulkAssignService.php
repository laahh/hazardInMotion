<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\Reference\PersonnelReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * plan-OCR.md T3.1 — logika submit satu minggu penuh sekaligus (grid personil
 * x 7 hari x 2 shift). Dipisah dari controller supaya testable tanpa HTTP.
 */
final class ScheduleBulkAssignService
{
    public function __construct(
        private readonly PersonnelReader $personnelReader,
    ) {}

    /**
     * @param  array{site_code: string, year: int, week_number: int, assignments: list<array{date: string, shift_code: string, personnel_source_key: string}>}  $payload
     */
    public function assign(array $payload, int $createdByUserId): ScheduleBulkAssignResult
    {
        $siteCode = $payload['site_code'];
        $errors = [];

        $personnelActive = [];
        foreach ($payload['assignments'] as $row) {
            $key = $row['personnel_source_key'];
            if (! array_key_exists($key, $personnelActive)) {
                $personnelActive[$key] = $this->personnelReader->existsAndActive($key);
            }
            if (! $personnelActive[$key]) {
                $errors[] = "Personil {$key} tidak ditemukan/tidak aktif di sumber data personil.";
            }
        }

        if ($errors !== []) {
            return new ScheduleBulkAssignResult(0, 0, array_values(array_unique($errors)), []);
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($payload, $siteCode, $createdByUserId, &$created, &$updated, &$errors): void {
            foreach ($payload['assignments'] as $row) {
                $existing = SchedulePlan::query()
                    ->where('site_code', $siteCode)
                    ->where('date', $row['date'])
                    ->where('shift_code', $row['shift_code'])
                    ->where('personnel_source_key', $row['personnel_source_key'])
                    ->first();

                if ($existing !== null && $existing->isLocked()) {
                    $errors[] = "Slot {$row['date']} {$row['shift_code']} untuk {$row['personnel_source_key']} sudah locked — ubah lewat form update dengan alasan, bukan bulk assign.";

                    continue;
                }

                $personnel = $this->personnelReader->find($row['personnel_source_key']);

                $plan = SchedulePlan::updateOrCreate(
                    [
                        'site_code' => $siteCode,
                        'date' => $row['date'],
                        'shift_code' => $row['shift_code'],
                        'personnel_source_key' => $row['personnel_source_key'],
                    ],
                    [
                        'year' => $payload['year'],
                        'week_number' => $payload['week_number'],
                        'personnel_name_snapshot' => $personnel?->emp_name ?? $row['personnel_source_key'],
                        'status' => SchedulePlan::STATUS_DRAFT,
                        'created_by' => $createdByUserId,
                    ]
                );

                $plan->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        $warnings = $errors === [] ? $this->buildWarnings($payload) : [];

        return new ScheduleBulkAssignResult($created, $updated, array_values(array_unique($errors)), $warnings);
    }

    /**
     * Peringatan (tidak memblokir submit): personil kena S1 dan S2 di hari
     * yang sama, dan slot shift yang masih kosong untuk minggu ini.
     *
     * @param  array{site_code: string, year: int, week_number: int, assignments: list<array{date: string, shift_code: string, personnel_source_key: string}>}  $payload
     * @return list<string>
     */
    private function buildWarnings(array $payload): array
    {
        $warnings = [];

        $byDatePersonnel = [];
        foreach ($payload['assignments'] as $row) {
            $byDatePersonnel[$row['date']][$row['personnel_source_key']][] = $row['shift_code'];
        }

        foreach ($byDatePersonnel as $date => $personnelShifts) {
            foreach ($personnelShifts as $personnelKey => $shifts) {
                if (count(array_unique($shifts)) > 1) {
                    $warnings[] = "Personil {$personnelKey} dijadwalkan S1 dan S2 di tanggal {$date}.";
                }
            }
        }

        $weekStart = CarbonImmutable::now()->setISODate($payload['year'], $payload['week_number'], 1);
        $expectedDates = collect(range(0, 6))->map(fn (int $i): string => $weekStart->addDays($i)->toDateString());
        $expectedShifts = array_column(ControlRoomShiftCode::cases(), 'value');

        $existingSlots = SchedulePlan::query()
            ->where('site_code', $payload['site_code'])
            ->where('year', $payload['year'])
            ->where('week_number', $payload['week_number'])
            ->get(['date', 'shift_code'])
            ->map(fn (SchedulePlan $plan): string => $plan->date->toDateString().'|'.$plan->shift_code->value)
            ->unique();

        foreach ($expectedDates as $date) {
            foreach ($expectedShifts as $shift) {
                if (! $existingSlots->contains("{$date}|{$shift}")) {
                    $warnings[] = "Slot {$date} {$shift} belum ada personil.";
                }
            }
        }

        return array_values(array_unique($warnings));
    }
}
