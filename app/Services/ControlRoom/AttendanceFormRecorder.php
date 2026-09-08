<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\SchedulePlan;
use App\Models\OhsDashboard\Employee;
use App\Services\ControlRoom\Reference\PersonnelReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class AttendanceFormRecorder
{
    public function __construct(
        private readonly PersonnelReader $personnelReader,
        private readonly ControlRoomDutyRosterService $dutyRoster,
    ) {}

    /**
     * @param  array{sid: string, tanggal?: string}  $data
     */
    public function record(array $data, UploadedFile $bukti): Attendance
    {
        $sid = strtoupper(trim($data['sid']));
        $personnel = $this->findPersonnel($sid);
        $plan = $this->requireDuty($sid);
        $date = $plan->date->toDateString();

        $proofPath = $bukti->store('control-room/attendance-proofs/'.$plan->date->format('Y/m'), 'public');

        $payload = [
            'schedule_plan_id' => $plan->id,
            'personnel_name_snapshot' => $personnel->emp_name,
            'status' => Attendance::STATUS_SESUAI_JADWAL,
            'checked_in_at' => now(),
        ];

        if (Schema::hasColumn('control_room_attendances', 'proof_path')) {
            $payload['proof_path'] = $proofPath;
        }

        return Attendance::query()->updateOrCreate(
            [
                'site_code' => $plan->site_code->value,
                'date' => $date,
                'shift_code' => $plan->shift_code->value,
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

    private function requireDuty(string $sid): SchedulePlan
    {
        $plan = $this->dutyRoster->findDuty($sid, $this->dutyRoster->dutyDate());
        if (! $plan instanceof SchedulePlan) {
            throw ValidationException::withMessages([
                'sid' => ControlRoomDutyRosterService::NOT_SCHEDULED_MESSAGE,
            ]);
        }

        return $plan;
    }
}
