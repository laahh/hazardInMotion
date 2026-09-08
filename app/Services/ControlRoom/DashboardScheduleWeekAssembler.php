<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\ScheduleChange;
use App\Models\ControlRoom\SchedulePlan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Menyusun panel Penjadwalan (Rencana vs Aktual) dari jadwal + absen nyata.
 */
final class DashboardScheduleWeekAssembler
{
    public function __construct(
        private readonly ControlRoomRfidCheckinoutReader $rfidReader,
        private readonly ControlRoomScheduleChangePresenter $changePresenter,
    ) {}

    /**
     * @return array{days: list<array<string, mixed>>}
     */
    public function build(
        ControlRoomSiteCode $site,
        CarbonImmutable $weekStart,
        ?CarbonInterface $today = null,
        bool $withRfid = true,
    ): array {
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

        $slots = [];
        foreach ($plans as $plan) {
            $slots[] = [
                'sid' => (string) $plan->personnel_source_key,
                'date' => $plan->date,
                'shift' => $plan->shift_code,
            ];
        }
        foreach ($attendances as $attendance) {
            $slots[] = [
                'sid' => (string) $attendance->personnel_source_key,
                'date' => $attendance->date,
                'shift' => $attendance->shift_code,
            ];
        }

        return $this->assemble(
            $weekStart,
            $plans,
            $attendances,
            $today,
            $withRfid ? $this->rfidReader->forDutySlots($slots) : [],
            $this->replacementsByPlanId($plans),
        );
    }

    /**
     * @param  Collection<int, SchedulePlan>  $plans
     * @param  Collection<int, Attendance>  $attendances
     * @param  array<string, list<array<string, mixed>>>  $rfidBySlot
     * @param  array<int, array{from: string, to: string, summary: string}>  $replacementsByPlanId
     * @return array{days: list<array<string, mixed>>}
     */
    public function assemble(
        CarbonImmutable $weekStart,
        Collection $plans,
        Collection $attendances,
        ?CarbonInterface $today = null,
        array $rfidBySlot = [],
        array $replacementsByPlanId = [],
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
            $peopleByDayShift[$date][$shift][] = $this->personFromPlan($plan, $attendance, $todayDate, $replacedNames, $rfidBySlot, $replacementsByPlanId);
        }

        foreach ($attendances as $attendance) {
            if (isset($consumedIds[$attendance->id])) {
                continue;
            }

            $date = $attendance->date->toDateString();
            $shift = $attendance->shift_code->value;
            $peopleByDayShift[$date][$shift][] = $this->personFromUnplannedAttendance($attendance, $replacedNames, $rfidBySlot, $replacementsByPlanId);
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
     * @param  array<string, list<array<string, mixed>>>  $rfidBySlot
     * @param  array<int, array{from: string, to: string, summary: string}>  $replacementsByPlanId
     * @return array{name: string, short_name: string, initial: string, planned: bool, status: string, jabatan: string, lokasi: string, catatan: string, checkinout: list<array<string, mixed>>, sid: string, replacement: string}
     */
    private function personFromPlan(
        SchedulePlan $plan,
        ?Attendance $attendance,
        string $todayDate,
        array $replacedNames,
        array $rfidBySlot,
        array $replacementsByPlanId = [],
    ): array {
        $name = $this->formatName((string) $plan->personnel_name_snapshot);
        $taps = $rfidBySlot[$this->slotKey($plan->date, $plan->shift_code, (string) $plan->personnel_source_key)] ?? [];
        $replacement = $this->replacementSummary($plan->id, $attendance, $replacementsByPlanId, $replacedNames);

        if ($attendance === null) {
            $isPast = $plan->date->toDateString() < $todayDate;
            $status = $isPast ? 'tidak_hadir' : 'belum_absen';

            return $this->personPayload(
                $name,
                planned: true,
                status: $status,
                catatan: $replacement !== '' ? $replacement : ($isPast ? 'Tidak ada absen' : 'Belum check-in'),
                checkinout: $taps,
                sid: (string) $plan->personnel_source_key,
                replacement: $replacement,
            );
        }

        return $this->personPayload(
            $name,
            planned: true,
            status: $this->mapAttendanceStatus($attendance, planned: true),
            catatan: $replacement !== '' ? $replacement : $this->catatanFromAttendance($attendance, $replacedNames),
            checkinout: $taps,
            sid: (string) $plan->personnel_source_key,
            replacement: $replacement,
        );
    }

    /**
     * @param  array<string, string>  $replacedNames
     * @param  array<string, list<array<string, mixed>>>  $rfidBySlot
     * @param  array<int, array{from: string, to: string, summary: string}>  $replacementsByPlanId
     * @return array{name: string, short_name: string, initial: string, planned: bool, status: string, jabatan: string, lokasi: string, catatan: string, checkinout: list<array<string, mixed>>, sid: string, replacement: string}
     */
    private function personFromUnplannedAttendance(
        Attendance $attendance,
        array $replacedNames,
        array $rfidBySlot,
        array $replacementsByPlanId = [],
    ): array {
        $status = $attendance->status === Attendance::STATUS_MENGGANTIKAN
            ? 'menggantikan'
            : 'tidak_dijadwalkan';
        $replacement = $this->replacementSummary($attendance->schedule_plan_id, $attendance, $replacementsByPlanId, $replacedNames);

        return $this->personPayload(
            $this->formatName((string) $attendance->personnel_name_snapshot),
            planned: false,
            status: $status,
            catatan: $replacement !== ''
                ? $replacement
                : ($this->catatanFromAttendance($attendance, $replacedNames) ?: 'Hadir tanpa slot jadwal.'),
            checkinout: $rfidBySlot[$this->slotKey($attendance->date, $attendance->shift_code, (string) $attendance->personnel_source_key)] ?? [],
            sid: (string) $attendance->personnel_source_key,
            replacement: $replacement,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $checkinout
     * @return array{name: string, short_name: string, initial: string, planned: bool, status: string, jabatan: string, lokasi: string, catatan: string, checkinout: list<array<string, mixed>>, sid: string, replacement: string}
     */
    private function personPayload(
        string $name,
        bool $planned,
        string $status,
        string $catatan,
        array $checkinout = [],
        string $sid = '',
        string $replacement = '',
    ): array {
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
            'checkinout' => $checkinout,
            'sid' => strtoupper(trim($sid)),
            'replacement' => $replacement,
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

    /**
     * @param  Collection<int, SchedulePlan>  $plans
     * @return array<int, array{from: string, to: string, summary: string}>
     */
    private function replacementsByPlanId(Collection $plans): array
    {
        $ids = $plans->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id)->all();
        if ($ids === []) {
            return [];
        }

        $changes = ScheduleChange::query()
            ->with('changedBy:id,name')
            ->whereIn('schedule_plan_id', $ids)
            ->whereIn('field', ['personnel_source_key', 'personnel_name_snapshot'])
            ->orderBy('changed_at')
            ->get()
            ->groupBy('schedule_plan_id');

        $index = [];
        foreach ($changes as $planId => $rows) {
            $timeline = $this->changePresenter->timeline($rows);
            $latest = $timeline[0] ?? null;
            if ($latest === null || ($latest['from'] === '—' && $latest['to'] === '—')) {
                continue;
            }

            $index[(int) $planId] = [
                'from' => $latest['from'],
                'to' => $latest['to'],
                'summary' => $latest['summary'],
            ];
        }

        return $index;
    }

    /**
     * @param  array<int, array{from: string, to: string, summary: string}>  $replacementsByPlanId
     * @param  array<string, string>  $replacedNames
     */
    private function replacementSummary(
        mixed $planId,
        ?Attendance $attendance,
        array $replacementsByPlanId,
        array $replacedNames,
    ): string {
        $id = (int) $planId;
        if ($id > 0 && isset($replacementsByPlanId[$id]['summary'])) {
            return (string) $replacementsByPlanId[$id]['summary'];
        }

        if ($attendance instanceof Attendance && $attendance->status === Attendance::STATUS_MENGGANTIKAN) {
            return $this->catatanFromAttendance($attendance, $replacedNames);
        }

        return '';
    }

    private function slotKey(mixed $date, mixed $shift, string $sourceKey): string
    {
        $dateString = $date instanceof CarbonInterface ? $date->toDateString() : (string) $date;
        $shiftCode = $shift instanceof ControlRoomShiftCode ? $shift->value : (string) $shift;

        return $dateString.'|'.$shiftCode.'|'.strtoupper(trim($sourceKey));
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
