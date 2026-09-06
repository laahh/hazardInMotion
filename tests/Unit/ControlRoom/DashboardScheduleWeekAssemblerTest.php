<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\DashboardScheduleWeekAssembler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class DashboardScheduleWeekAssemblerTest extends TestCase
{
    public function test_jadwal_tanpa_absen_di_hari_lalu_jadi_tidak_hadir(): void
    {
        $weekStart = CarbonImmutable::parse('2026-08-31');
        $plan = $this->plan(1, '2026-08-31', ControlRoomShiftCode::S1, 'SID01', 'AGUNG NUGROHO');

        $days = (new DashboardScheduleWeekAssembler())->assemble(
            $weekStart,
            new Collection([$plan]),
            new Collection(),
            CarbonImmutable::parse('2026-09-07'),
        )['days'];

        $this->assertSame('Agung Nugroho', $days[0]['s1'][0]['name']);
        $this->assertSame('tidak_hadir', $days[0]['s1'][0]['status']);
        $this->assertTrue($days[0]['s1'][0]['planned']);
        $this->assertSame([], $days[0]['s2']);
    }

    public function test_absen_sesuai_jadwal_mengisi_status_sesuai(): void
    {
        $weekStart = CarbonImmutable::parse('2026-08-31');
        $plan = $this->plan(2, '2026-09-01', ControlRoomShiftCode::S2, 'SID02', 'MUHAMMAD ALI YUSNI');
        $attendance = $this->attendance(10, 2, '2026-09-01', ControlRoomShiftCode::S2, 'SID02', 'MUHAMMAD ALI YUSNI', Attendance::STATUS_SESUAI_JADWAL);

        $days = (new DashboardScheduleWeekAssembler())->assemble(
            $weekStart,
            new Collection([$plan]),
            new Collection([$attendance]),
            CarbonImmutable::parse('2026-09-07'),
        )['days'];

        $this->assertSame('Muhammad Ali Yusni', $days[1]['s2'][0]['name']);
        $this->assertSame('sesuai', $days[1]['s2'][0]['status']);
    }

    public function test_hadir_tanpa_slot_tampil_tidak_dijadwalkan(): void
    {
        $weekStart = CarbonImmutable::parse('2026-08-31');
        $attendance = $this->attendance(11, null, '2026-09-02', ControlRoomShiftCode::S1, 'SID99', 'DEWI LESTARI', Attendance::STATUS_SESUAI_JADWAL);

        $days = (new DashboardScheduleWeekAssembler())->assemble(
            $weekStart,
            new Collection(),
            new Collection([$attendance]),
            CarbonImmutable::parse('2026-09-07'),
        )['days'];

        $this->assertSame('Dewi Lestari', $days[2]['s1'][0]['name']);
        $this->assertSame('tidak_dijadwalkan', $days[2]['s1'][0]['status']);
        $this->assertFalse($days[2]['s1'][0]['planned']);
    }

    public function test_jadwal_hari_ini_tanpa_absen_jadi_belum_absen(): void
    {
        $weekStart = CarbonImmutable::parse('2026-09-07');
        $plan = $this->plan(3, '2026-09-07', ControlRoomShiftCode::S1, 'SID03', 'BUDI SANTOSO');

        $days = (new DashboardScheduleWeekAssembler())->assemble(
            $weekStart,
            new Collection([$plan]),
            new Collection(),
            CarbonImmutable::parse('2026-09-07'),
        )['days'];

        $this->assertSame('belum_absen', $days[0]['s1'][0]['status']);
    }

    private function plan(int $id, string $date, ControlRoomShiftCode $shift, string $sid, string $name): SchedulePlan
    {
        $plan = new SchedulePlan();
        $plan->id = $id;
        $plan->forceFill([
            'site_code' => ControlRoomSiteCode::HeadOffice,
            'date' => $date,
            'shift_code' => $shift,
            'personnel_source_key' => $sid,
            'personnel_name_snapshot' => $name,
        ]);

        return $plan;
    }

    private function attendance(
        int $id,
        ?int $planId,
        string $date,
        ControlRoomShiftCode $shift,
        string $sid,
        string $name,
        string $status,
    ): Attendance {
        $attendance = new Attendance();
        $attendance->id = $id;
        $attendance->forceFill([
            'schedule_plan_id' => $planId,
            'site_code' => ControlRoomSiteCode::HeadOffice,
            'date' => $date,
            'shift_code' => $shift,
            'personnel_source_key' => $sid,
            'personnel_name_snapshot' => $name,
            'status' => $status,
            'checked_in_at' => $date.' 08:00:00',
        ]);

        return $attendance;
    }
}
