<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\SchedulePlan;
use App\Models\OhsDashboard\Employee;
use App\Services\ControlRoom\Reference\PersonnelReader;
use App\Services\ControlRoom\Reference\ShiftResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class AttendanceFormRecorder
{
    public function __construct(
        private readonly PersonnelReader $personnelReader,
        private readonly ShiftResolver $shiftResolver,
    ) {}

    /**
     * @param  array{sid: string, tanggal: string}  $data
     */
    public function record(array $data, UploadedFile $bukti): Attendance
    {
        $sid = $data['sid'];
        $date = CarbonImmutable::parse($data['tanggal'])->startOfDay();
        $personnel = $this->findPersonnel($sid);

        [$site, $shift, $plan] = $this->resolveSlot($personnel, $sid, $date);

        $proofPath = $bukti->store('control-room/attendance-proofs/'.$date->format('Y/m'), 'public');

        $payload = [
            'schedule_plan_id' => $plan?->id,
            'personnel_name_snapshot' => $personnel->emp_name,
            'status' => Attendance::STATUS_SESUAI_JADWAL,
            'checked_in_at' => now(),
        ];

        if (Schema::hasColumn('control_room_attendances', 'proof_path')) {
            $payload['proof_path'] = $proofPath;
        }

        return Attendance::query()->updateOrCreate(
            [
                'site_code' => $site->value,
                'date' => $date->toDateString(),
                'shift_code' => $shift->value,
                'personnel_source_key' => $sid,
            ],
            $payload
        );
    }

    private function findPersonnel(string $sid): Employee
    {
        $personnel = $this->personnelReader->find($sid);
        if ($personnel === null) {
            throw ValidationException::withMessages([
                'sid' => 'SID tidak ditemukan atau personil tidak aktif.',
            ]);
        }

        return $personnel;
    }

    /**
     * @return array{0: ControlRoomSiteCode, 1: ControlRoomShiftCode, 2: SchedulePlan|null}
     */
    private function resolveSlot(Employee $personnel, string $sid, CarbonImmutable $date): array
    {
        $plans = SchedulePlan::query()
            ->select(['id', 'site_code', 'date', 'shift_code', 'personnel_source_key'])
            ->where('personnel_source_key', $sid)
            ->whereDate('date', $date->toDateString())
            ->orderBy('shift_code')
            ->get();

        $currentShift = $this->shiftResolver->resolve(now());

        if ($plans->count() === 1) {
            $plan = $plans->first();

            return [$plan->site_code, $plan->shift_code, $plan];
        }

        if ($plans->isNotEmpty()) {
            $plan = $plans->first(
                fn (SchedulePlan $row): bool => $row->shift_code === $currentShift
            ) ?? $plans->first();

            return [$plan->site_code, $plan->shift_code, $plan];
        }

        $site = ControlRoomSiteCode::fromDedicated($personnel->site_dedicated);

        return [$site, $currentShift, null];
    }
}
