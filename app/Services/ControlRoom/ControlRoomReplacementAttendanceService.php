<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\ScheduleChange;
use App\Models\ControlRoom\SchedulePlan;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Penggantian personil pada hari jaga harus sekalian menjadi absensi
 * `hadir_menggantikan`, bukan hanya panah riwayat di kalender.
 */
final class ControlRoomReplacementAttendanceService
{
    public function __construct(
        private readonly ControlRoomDutyRosterService $dutyRoster,
    ) {}

    public function ensureDutyDateCheckins(?CarbonInterface $now = null): int
    {
        $date = $this->dutyRoster->dutyDate($now)->toDateString();
        $plans = SchedulePlan::query()
            ->select([
                'id',
                'site_code',
                'date',
                'shift_code',
                'personnel_source_key',
                'personnel_name_snapshot',
            ])
            ->whereDate('date', $date)
            ->get();

        $replacedSids = $this->replacedSidIndex($plans);
        $written = 0;
        foreach ($plans as $plan) {
            $previousSid = $replacedSids[(int) $plan->id] ?? null;
            if ($previousSid === null) {
                continue;
            }
            if ($this->write($plan, $previousSid) instanceof Attendance) {
                $written++;
            }
        }

        return $written;
    }

    public function recordAfterPersonnelChange(SchedulePlan $plan, string $previousSid, ?CarbonInterface $now = null): ?Attendance
    {
        $previousSid = strtoupper(trim($previousSid));
        if ($previousSid === '') {
            return null;
        }

        if ($plan->date->toDateString() !== $this->dutyRoster->dutyDate($now)->toDateString()) {
            return null;
        }

        return $this->write($plan, $previousSid);
    }

    public function replacedSid(SchedulePlan $plan): ?string
    {
        $index = $this->replacedSidIndex(collect([$plan]));

        return $index[(int) $plan->id] ?? null;
    }

    public function scheduledCheckInStatus(?string $replacedSid): string
    {
        return $replacedSid !== null && $replacedSid !== ''
            ? Attendance::STATUS_MENGGANTIKAN
            : Attendance::STATUS_SESUAI_JADWAL;
    }

    /**
     * @param  Collection<int, SchedulePlan>  $plans
     * @return array<int, string>
     */
    private function replacedSidIndex(Collection $plans): array
    {
        $ids = $plans->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id)->all();
        if ($ids === []) {
            return [];
        }

        $latest = ScheduleChange::query()
            ->whereIn('schedule_plan_id', $ids)
            ->where('field', 'personnel_source_key')
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->get(['schedule_plan_id', 'old_value', 'new_value']);

        $index = [];
        foreach ($latest as $change) {
            $planId = (int) $change->schedule_plan_id;
            if (isset($index[$planId])) {
                continue;
            }

            $old = strtoupper(trim((string) $change->old_value));
            $new = strtoupper(trim((string) $change->new_value));
            if ($old === '' || $old === $new) {
                continue;
            }

            $plan = $plans->firstWhere('id', $planId);
            if (! $plan instanceof SchedulePlan) {
                continue;
            }
            if ($new !== strtoupper((string) $plan->personnel_source_key)) {
                continue;
            }

            $index[$planId] = $old;
        }

        return $index;
    }

    private function write(SchedulePlan $plan, string $previousSid): ?Attendance
    {
        $sid = strtoupper(trim((string) $plan->personnel_source_key));
        $previousSid = strtoupper(trim($previousSid));
        if ($sid === '' || $previousSid === '' || $sid === $previousSid) {
            return null;
        }

        $existing = Attendance::query()
            ->where('site_code', $plan->site_code->value)
            ->whereDate('date', $plan->date->toDateString())
            ->where('shift_code', $plan->shift_code->value)
            ->whereRaw('upper(personnel_source_key) = ?', [$sid])
            ->first();

        if ($existing instanceof Attendance) {
            if (
                $existing->status === Attendance::STATUS_SESUAI_JADWAL
                || ($existing->status === Attendance::STATUS_MENGGANTIKAN && $existing->replacing_source_key === null)
            ) {
                $existing->update([
                    'schedule_plan_id' => $plan->id,
                    'status' => Attendance::STATUS_MENGGANTIKAN,
                    'replacing_source_key' => $previousSid,
                    'personnel_name_snapshot' => $plan->personnel_name_snapshot,
                ]);
            }

            return $existing->fresh() ?? $existing;
        }

        return Attendance::query()->create([
            'schedule_plan_id' => $plan->id,
            'site_code' => $plan->site_code->value,
            'date' => $plan->date->toDateString(),
            'shift_code' => $plan->shift_code->value,
            'personnel_source_key' => $sid,
            'personnel_name_snapshot' => $plan->personnel_name_snapshot,
            'status' => Attendance::STATUS_MENGGANTIKAN,
            'replacing_source_key' => $previousSid,
            'checked_in_at' => now(),
        ]);
    }
}
