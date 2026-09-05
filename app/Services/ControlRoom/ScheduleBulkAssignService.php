<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\Reference\PersonnelReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * plan-OCR.md T3.1 — submit satu baris (dari modal kalender) atau banyak
 * baris sekaligus. Dipisah dari controller supaya testable tanpa HTTP.
 *
 * year/week_number TIDAK diminta dari pemanggil — dihitung di sini dari
 * tanggal tiap baris (CarbonImmutable::isoWeekYear()/isoWeek()), supaya
 * selalu konsisten dengan tanggal aslinya walau UI kalender kini navigasi
 * per bulan, bukan per minggu.
 */
final class ScheduleBulkAssignService
{
    public function __construct(
        private readonly PersonnelReader $personnelReader,
    ) {}

    /**
     * @param  array{site_code: string, assignments: list<array{date: string, shift_code: string, personnel_source_key: string}>}  $payload
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
                $date = CarbonImmutable::parse($row['date']);

                $plan = SchedulePlan::updateOrCreate(
                    [
                        'site_code' => $siteCode,
                        'date' => $row['date'],
                        'shift_code' => $row['shift_code'],
                        'personnel_source_key' => $row['personnel_source_key'],
                    ],
                    [
                        'year' => $date->isoWeekYear(),
                        'week_number' => $date->isoWeek(),
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
     * yang sama.
     *
     * @param  array{site_code: string, assignments: list<array{date: string, shift_code: string, personnel_source_key: string}>}  $payload
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

        return array_values(array_unique($warnings));
    }
}
