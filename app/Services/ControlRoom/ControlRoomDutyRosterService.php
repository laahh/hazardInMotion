<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\Reference\ShiftResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Roster jaga Control Room untuk tanggal efektif shift berjalan.
 */
final class ControlRoomDutyRosterService
{
    public const NOT_SCHEDULED_MESSAGE = 'Anda tidak dijadwalkan hari ini jadi tidak bisa absen. Jika ada perubahan, hubungi admin.';

    public function __construct(
        private readonly ShiftResolver $shiftResolver,
    ) {}

    public function dutyDate(?CarbonInterface $now = null): CarbonImmutable
    {
        return $this->shiftResolver->effectiveDate($now ?? now());
    }

    public function dutyDateLabel(CarbonImmutable $date): string
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $days[(int) $date->dayOfWeek].', '.$date->day.' '.$months[(int) $date->month].' '.$date->year;
    }

    public function currentShift(?CarbonInterface $now = null): ControlRoomShiftCode
    {
        return $this->shiftResolver->resolve($now ?? now());
    }

    /**
     * @return Collection<int, SchedulePlan>
     */
    public function roster(CarbonImmutable $date, ?ControlRoomSiteCode $site = null): Collection
    {
        $query = SchedulePlan::query()
            ->select([
                'id',
                'site_code',
                'date',
                'shift_code',
                'personnel_source_key',
                'personnel_name_snapshot',
            ])
            ->whereDate('date', $date->toDateString());

        if ($site instanceof ControlRoomSiteCode) {
            $query->where('site_code', $site->value);
        }

        return $query
            ->orderBy('site_code')
            ->orderBy('shift_code')
            ->orderBy('personnel_name_snapshot')
            ->get();
    }

    public function findDuty(string $sid, CarbonImmutable $date, ?ControlRoomSiteCode $site = null): ?SchedulePlan
    {
        $sid = strtoupper(trim($sid));
        if ($sid === '') {
            return null;
        }

        $query = SchedulePlan::query()
            ->select([
                'id',
                'site_code',
                'date',
                'shift_code',
                'personnel_source_key',
                'personnel_name_snapshot',
            ])
            ->whereDate('date', $date->toDateString())
            ->whereRaw('upper(personnel_source_key) = ?', [$sid]);

        if ($site instanceof ControlRoomSiteCode) {
            $query->where('site_code', $site->value);
        }

        $plans = $query->orderBy('shift_code')->get();

        return $this->pickPlan($plans, $this->currentShift());
    }

    public function findPlanForDuty(int $planId, CarbonImmutable $date, ?ControlRoomSiteCode $site = null): ?SchedulePlan
    {
        if ($planId < 1) {
            return null;
        }

        $query = SchedulePlan::query()
            ->select([
                'id',
                'site_code',
                'date',
                'shift_code',
                'personnel_source_key',
                'personnel_name_snapshot',
                'status',
                'created_by',
            ])
            ->where('id', $planId)
            ->whereDate('date', $date->toDateString());

        if ($site instanceof ControlRoomSiteCode) {
            $query->where('site_code', $site->value);
        }

        $plan = $query->first();

        return $plan instanceof SchedulePlan ? $plan : null;
    }

    /**
     * @param  Collection<int, SchedulePlan>  $plans
     */
    public function pickPlan(Collection $plans, ControlRoomShiftCode $currentShift): ?SchedulePlan
    {
        if ($plans->isEmpty()) {
            return null;
        }

        if ($plans->count() === 1) {
            return $plans->first();
        }

        return $plans->first(
            fn (SchedulePlan $plan): bool => $plan->shift_code === $currentShift
        ) ?? $plans->first();
    }
}
