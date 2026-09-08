<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\SchedulePlan;
use Carbon\CarbonInterface;

/**
 * Papan status live semua Control Room: hijau jika ada jadwal shift
 * berjalan DAN sudah ada absensi hadir; selain itu merah.
 */
final class ControlRoomSiteDutyBoardService
{
    public function __construct(
        private readonly ControlRoomDutyRosterService $dutyRoster,
    ) {}

    /**
     * @return array{
     *     dutyDate: string,
     *     dutyDateLabel: string,
     *     shift: ControlRoomShiftCode,
     *     cards: list<array<string, mixed>>
     * }
     */
    public function build(?CarbonInterface $now = null): array
    {
        $dutyDate = $this->dutyRoster->dutyDate($now);
        $shift = $this->dutyRoster->currentShift($now);
        $date = $dutyDate->toDateString();

        $plans = SchedulePlan::query()
            ->select(['id', 'site_code', 'personnel_source_key', 'personnel_name_snapshot'])
            ->whereDate('date', $date)
            ->where('shift_code', $shift->value)
            ->orderBy('personnel_name_snapshot')
            ->get()
            ->groupBy(fn (SchedulePlan $plan): string => $plan->site_code->value);

        $attendances = Attendance::query()
            ->select(['id', 'site_code', 'personnel_source_key', 'personnel_name_snapshot', 'status', 'checked_in_at'])
            ->whereDate('date', $date)
            ->where('shift_code', $shift->value)
            ->whereIn('status', [Attendance::STATUS_SESUAI_JADWAL, Attendance::STATUS_MENGGANTIKAN])
            ->whereNotNull('checked_in_at')
            ->orderBy('personnel_name_snapshot')
            ->get()
            ->groupBy(fn (Attendance $row): string => $row->site_code->value);

        $cards = [];
        foreach (ControlRoomSiteCode::cases() as $site) {
            $cards[] = $this->composeCard(
                $site,
                $plans->get($site->value, collect()),
                $attendances->get($site->value, collect()),
            );
        }

        return [
            'dutyDate' => $date,
            'dutyDateLabel' => $this->dutyRoster->dutyDateLabel($dutyDate),
            'shift' => $shift,
            'cards' => $cards,
        ];
    }

    /**
     * Hijau hanya jika jadwal shift ini ada dan sudah ada yang absen jaga.
     *
     * @return array{tone: string, state: string, hasSchedule: bool, hasDuty: bool}
     */
    public function resolveState(bool $hasSchedule, bool $hasDuty): array
    {
        if ($hasSchedule && $hasDuty) {
            return [
                'tone' => 'green',
                'state' => 'Terjaga',
                'hasSchedule' => true,
                'hasDuty' => true,
            ];
        }

        if ($hasSchedule) {
            return [
                'tone' => 'red',
                'state' => 'Belum absen',
                'hasSchedule' => true,
                'hasDuty' => false,
            ];
        }

        if ($hasDuty) {
            return [
                'tone' => 'red',
                'state' => 'Absen tanpa jadwal',
                'hasSchedule' => false,
                'hasDuty' => true,
            ];
        }

        return [
            'tone' => 'red',
            'state' => 'Tidak ada jadwal',
            'hasSchedule' => false,
            'hasDuty' => false,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SchedulePlan>  $plans
     * @param  \Illuminate\Support\Collection<int, Attendance>  $attendances
     * @return array<string, mixed>
     */
    public function composeCard(ControlRoomSiteCode $site, $plans, $attendances): array
    {
        $state = $this->resolveState($plans->isNotEmpty(), $attendances->isNotEmpty());

        return [
            'site' => $site->value,
            'label' => $site->label(),
            'tone' => $state['tone'],
            'state' => $state['state'],
            'hasSchedule' => $state['hasSchedule'],
            'hasDuty' => $state['hasDuty'],
            'scheduled' => $plans->map(fn (SchedulePlan $plan): array => [
                'sid' => strtoupper((string) $plan->personnel_source_key),
                'name' => (string) $plan->personnel_name_snapshot,
            ])->values()->all(),
            'present' => $attendances->map(fn (Attendance $row): array => [
                'sid' => strtoupper((string) $row->personnel_source_key),
                'name' => (string) $row->personnel_name_snapshot,
            ])->values()->all(),
        ];
    }
}
