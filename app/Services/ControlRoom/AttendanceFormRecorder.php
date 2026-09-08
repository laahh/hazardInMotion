<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\SchedulePlan;
use App\Models\OhsDashboard\Employee;
use App\Services\ControlRoom\Reference\PersonnelReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class AttendanceFormRecorder
{
    public const MODE_SCHEDULED = 'scheduled';

    public const MODE_PENGGANTI = 'pengganti';

    public function __construct(
        private readonly PersonnelReader $personnelReader,
        private readonly ControlRoomDutyRosterService $dutyRoster,
    ) {}

    /**
     * @param  array{sid: string, tanggal?: string, site?: string|null, mode?: string, replacing_plan_id?: int|string|null}  $data
     */
    public function record(array $data, UploadedFile $bukti): Attendance
    {
        if (($data['mode'] ?? self::MODE_SCHEDULED) === self::MODE_PENGGANTI) {
            return $this->recordReplacement($data, $bukti);
        }

        $sid = strtoupper(trim($data['sid']));
        $personnel = $this->findPersonnel($sid);
        $plan = $this->requireDuty($sid, $this->siteFromData($data));
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

    /**
     * Pengganti yang belum masuk jadwal: absen seperti biasa, lalu slot
     * jadwal berpindah ke SID-nya dan tercatat di riwayat ganti personil.
     *
     * @param  array{sid: string, site?: string|null, replacing_plan_id?: int|string|null}  $data
     */
    public function recordReplacement(array $data, UploadedFile $bukti): Attendance
    {
        $sid = strtoupper(trim($data['sid']));
        $personnel = $this->findPersonnel($sid);
        $dutyDate = $this->dutyRoster->dutyDate();
        $original = $this->dutyRoster->findPlanForDuty(
            (int) ($data['replacing_plan_id'] ?? 0),
            $dutyDate,
            $this->siteFromData($data),
        );

        if (! $original instanceof SchedulePlan) {
            throw ValidationException::withMessages([
                'replacing_plan_id' => 'Pilih personil terjadwal yang Anda gantikan hari ini.',
            ]);
        }

        if (strtoupper((string) $original->personnel_source_key) === $sid) {
            throw ValidationException::withMessages([
                'sid' => 'SID ini sudah dijadwalkan. Gunakan absen biasa, bukan tombol pengganti.',
            ]);
        }

        $slotTaken = SchedulePlan::query()
            ->where('site_code', $original->site_code->value)
            ->whereDate('date', $original->date->toDateString())
            ->where('shift_code', $original->shift_code->value)
            ->whereRaw('upper(personnel_source_key) = ?', [$sid])
            ->where('id', '!=', $original->id)
            ->exists();

        if ($slotTaken) {
            throw ValidationException::withMessages([
                'sid' => 'SID ini sudah ada di slot jadwal yang sama.',
            ]);
        }

        $alreadyPresent = Attendance::query()
            ->where('schedule_plan_id', $original->id)
            ->where('status', Attendance::STATUS_SESUAI_JADWAL)
            ->exists();

        if ($alreadyPresent) {
            throw ValidationException::withMessages([
                'replacing_plan_id' => 'Personil yang dipilih sudah absen sesuai jadwal, tidak perlu diganti.',
            ]);
        }

        $originalSid = strtoupper((string) $original->personnel_source_key);
        $originalName = trim((string) $original->personnel_name_snapshot);
        $proofPath = $bukti->store('control-room/attendance-proofs/'.$original->date->format('Y/m'), 'public');

        return DB::transaction(function () use ($original, $personnel, $sid, $originalSid, $originalName, $proofPath): Attendance {
            $original->changeReason = sprintf(
                'Pengganti absensi Control Room: %s (%s) tidak hadir, digantikan %s (%s).',
                $originalName !== '' ? $originalName : $originalSid,
                $originalSid,
                $personnel->emp_name,
                $sid,
            );
            $original->update([
                'personnel_source_key' => $sid,
                'personnel_name_snapshot' => $personnel->emp_name,
            ]);

            $payload = [
                'schedule_plan_id' => $original->id,
                'personnel_name_snapshot' => $personnel->emp_name,
                'status' => Attendance::STATUS_MENGGANTIKAN,
                'replacing_source_key' => $originalSid,
                'checked_in_at' => now(),
            ];

            if (Schema::hasColumn('control_room_attendances', 'proof_path')) {
                $payload['proof_path'] = $proofPath;
            }

            return Attendance::query()->updateOrCreate(
                [
                    'site_code' => $original->site_code->value,
                    'date' => $original->date->toDateString(),
                    'shift_code' => $original->shift_code->value,
                    'personnel_source_key' => $sid,
                ],
                $payload
            );
        });
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

    private function requireDuty(string $sid, ?ControlRoomSiteCode $site): SchedulePlan
    {
        $plan = $this->dutyRoster->findDuty($sid, $this->dutyRoster->dutyDate(), $site);
        if (! $plan instanceof SchedulePlan) {
            throw ValidationException::withMessages([
                'sid' => ControlRoomDutyRosterService::NOT_SCHEDULED_MESSAGE,
            ]);
        }

        return $plan;
    }

    /**
     * @param  array{site?: string|null}  $data
     */
    private function siteFromData(array $data): ?ControlRoomSiteCode
    {
        $value = strtoupper(trim((string) ($data['site'] ?? '')));
        if ($value === '') {
            return null;
        }

        return ControlRoomSiteCode::tryFrom($value);
    }
}
