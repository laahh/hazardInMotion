<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\SchedulePlan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Menyusun panel Penjadwalan (Rencana vs Aktual) dari jadwal + absen nyata.
 */
final class DashboardScheduleWeekAssembler
{
    /**
     * @return array{days: list<array<string, mixed>>}
     */
    public function build(ControlRoomSiteCode $site, CarbonImmutable $weekStart, ?CarbonInterface $today = null): array
    {
        $weekEnd = $weekStart->addDays(6);

        $plans = SchedulePlan::query()
            ->select(['id', 'site_code', 'date', 'shift_code', 'personnel_source_key', 'personnel_name_snapshot'])
            ->where('site_code', $site->value)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('shift_code')
            ->orderBy('personnel_name_snapshot')
            ->get();

        $attendances = Attendance::query()
            ->select([
                'id',
                'schedule_plan_id',
                'site_code',
                'date',
                'shift_code',
                'personnel_source_key',
                'personnel_name_snapshot',
                'status',
                'replacing_source_key',
                'absence_reason',
            ])
            ->where('site_code', $site->value)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();

        return $this->assemble($weekStart, $plans, $attendances, $today);
    }

    /**
     * @param  Collection<int, SchedulePlan>  $plans
     * @param  Collection<int, Attendance>  $attendances
     * @return array{days: list<array<string, mixed>>}
     */
    public function assemble(
        CarbonImmutable $weekStart,
        Collection $plans,
        Collection $attendances,
        ?CarbonInterface $today = null,
    ): array {
        $todayDate = CarbonImmutable::parse($today ?? now())->toDateString();
        $replacedNames = $this->replacedNameIndex($plans);
        $attendancesByPlanId = $attendances->filter(fn (Attendance $row): bool => $row->schedule_plan_id !== null)
            ->keyBy('schedule_plan_id');
        $attendancesBySlot = $attendances->keyBy(
            fn (Attendance $row): string => $this->slotKey($row->date, $row->shift_code, $row->personnel_source_key)
        );

        $peopleByDayShift = [];
        $consumedIds = [];

        foreach ($plans as $plan) {
            $attendance = $this->attendanceForPlan($plan, $attendancesByPlanId, $attendancesBySlot);
            if ($attendance !== null) {
                $consumedIds[$attendance->id] = true;
            }

            $date = $plan->date->toDateString();
            $shift = $plan->shift_code->value;
            $peopleByDayShift[$date][$shift][] = $this->personFromPlan($plan, $attendance, $todayDate, $replacedNames);
        }

        foreach ($attendances as $attendance) {
            if (isset($consumedIds[$attendance->id])) {
                continue;
            }

            $date = $attendance->date->toDateString();
            $shift = $attendance->shift_code->value;
            $peopleByDayShift[$date][$shift][] = $this->personFromUnplannedAttendance($attendance, $replacedNames);
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->addDays($i);
            $dateString = $date->toDateString();

            $days[] = [
                'date' => $dateString,
                'label' => $date->locale('id')->translatedFormat('D'),
                'weekday' => $date->locale('id')->translatedFormat('l'),
                'day_number' => $date->format('d'),
                'month_short' => $date->locale('id')->translatedFormat('M'),
                'year' => $date->format('Y'),
                'is_today' => $dateString === $todayDate,
                'is_weekend' => $date->isWeekend(),
                's1' => $peopleByDayShift[$dateString][ControlRoomShiftCode::S1->value] ?? [],
                's2' => $peopleByDayShift[$dateString][ControlRoomShiftCode::S2->value] ?? [],
            ];
        }

        return ['days' => $days];
    }

    /**
     * @param  Collection<int, SchedulePlan>  $plans
     * @return array<string, string>
     */
    private function replacedNameIndex(Collection $plans): array
    {
        $index = [];
        foreach ($plans as $plan) {
            $index[$this->slotKey($plan->date, $plan->shift_code, $plan->personnel_source_key)] = $this->formatName(
                (string) $plan->personnel_name_snapshot
            );
        }

        return $index;
    }

    /**
     * @param  Collection<int, Attendance>  $attendancesByPlanId
     * @param  Collection<int, Attendance>  $attendancesBySlot
     */
    private function attendanceForPlan(
        SchedulePlan $plan,
        Collection $attendancesByPlanId,
        Collection $attendancesBySlot,
    ): ?Attendance {
        if ($plan->id !== null && $attendancesByPlanId->has($plan->id)) {
            return $attendancesByPlanId->get($plan->id);
        }

        return $attendancesBySlot->get($this->slotKey($plan->date, $plan->shift_code, $plan->personnel_source_key));
    }

    /**
     * @param  array<string, string>  $replacedNames
     * @return array{name: string, short_name: string, initial: string, planned: bool, status: string, jabatan: string, lokasi: string, catatan: string}
     */
    private function personFromPlan(
        SchedulePlan $plan,
        ?Attendance $attendance,
        string $todayDate,
        array $replacedNames,
    ): array {
        $name = $this->formatName((string) $plan->personnel_name_snapshot);

        if ($attendance === null) {
            $isPast = $plan->date->toDateString() < $todayDate;
            $status = $isPast ? 'tidak_hadir' : 'belum_absen';

            return $this->personPayload(
                $name,
                planned: true,
                status: $status,
                catatan: $isPast ? 'Tidak ada absen' : 'Belum check-in',
            );
        }

        return $this->personPayload(
            $name,
            planned: true,
            status: $this->mapAttendanceStatus($attendance, planned: true),
            catatan: $this->catatanFromAttendance($attendance, $replacedNames),
        );
    }

    /**
     * @param  array<string, string>  $replacedNames
     * @return array{name: string, short_name: string, initial: string, planned: bool, status: string, jabatan: string, lokasi: string, catatan: string}
     */
    private function personFromUnplannedAttendance(Attendance $attendance, array $replacedNames): array
    {
        $status = $attendance->status === Attendance::STATUS_MENGGANTIKAN
            ? 'menggantikan'
            : 'tidak_dijadwalkan';

        return $this->personPayload(
            $this->formatName((string) $attendance->personnel_name_snapshot),
            planned: false,
            status: $status,
            catatan: $this->catatanFromAttendance($attendance, $replacedNames) ?: 'Hadir tanpa slot jadwal.',
        );
    }

    /**
     * @return array{name: string, short_name: string, initial: string, planned: bool, status: string, jabatan: string, lokasi: string, catatan: string}
     */
    private function personPayload(string $name, bool $planned, string $status, string $catatan): array
    {
        $parts = preg_split('/\s+/', $name) ?: [$name];

        return [
            'name' => $name,
            'short_name' => $parts[0],
            'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
            'planned' => $planned,
            'status' => $status,
            'jabatan' => '—',
            'lokasi' => '—',
            'catatan' => $catatan,
        ];
    }

    private function mapAttendanceStatus(Attendance $attendance, bool $planned): string
    {
        return match ($attendance->status) {
            Attendance::STATUS_SESUAI_JADWAL => $planned ? 'sesuai' : 'tidak_dijadwalkan',
            Attendance::STATUS_MENGGANTIKAN => 'menggantikan',
            Attendance::STATUS_TIDAK_HADIR => 'tidak_hadir',
            default => $planned ? 'belum_absen' : 'tidak_dijadwalkan',
        };
    }

    /**
     * @param  array<string, string>  $replacedNames
     */
    private function catatanFromAttendance(Attendance $attendance, array $replacedNames): string
    {
        if ($attendance->status === Attendance::STATUS_MENGGANTIKAN && $attendance->replacing_source_key) {
            $key = $this->slotKey($attendance->date, $attendance->shift_code, $attendance->replacing_source_key);
            $replaced = $replacedNames[$key] ?? $attendance->replacing_source_key;

            return 'Menggantikan '.$replaced.'.';
        }

        if ($attendance->status === Attendance::STATUS_TIDAK_HADIR) {
            $reason = trim((string) $attendance->absence_reason);

            return $reason !== '' ? $reason : 'Tidak hadir';
        }

        return $attendance->status === Attendance::STATUS_SESUAI_JADWAL ? '-' : '—';
    }

    private function slotKey(mixed $date, mixed $shift, string $sourceKey): string
    {
        $dateString = $date instanceof CarbonInterface ? $date->toDateString() : (string) $date;
        $shiftCode = $shift instanceof ControlRoomShiftCode ? $shift->value : (string) $shift;

        return $dateString.'|'.$shiftCode.'|'.$sourceKey;
    }

    private function formatName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '—';
        }

        return mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
}
