<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\ControlRoomRfidCheckinoutReader;
use App\Services\ControlRoom\ControlRoomScheduleChangePresenter;
use App\Services\ControlRoom\DashboardScheduleWeekAssembler;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class DashboardScheduleWeekAssemblerTest extends TestCase
{
    public function test_jadwal_tanpa_absen_di_hari_lalu_jadi_tidak_hadir(): void
    {
        $weekStart = CarbonImmutable::parse('2026-08-31');
        $plan = $this->plan(1, '2026-08-31', ControlRoomShiftCode::S1, 'SID01', 'AGUNG NUGROHO');

        $days = $this->assembler()->assemble(
            $weekStart,
            new Collection([$plan]),
            new Collection(),
            CarbonImmutable::parse('2026-09-07'),
        )['days'];

        $this->assertSame('Agung Nugroho', $days[0]['s1'][0]['name']);
        $this->assertSame('SID01', $days[0]['s1'][0]['sid']);
        $this->assertSame('tidak_hadir', $days[0]['s1'][0]['status']);
        $this->assertTrue($days[0]['s1'][0]['planned']);
        $this->assertSame([], $days[0]['s2']);
    }

    public function test_absen_sesuai_jadwal_mengisi_status_sesuai(): void
    {
        $weekStart = CarbonImmutable::parse('2026-08-31');
        $plan = $this->plan(2, '2026-09-01', ControlRoomShiftCode::S2, 'SID02', 'MUHAMMAD ALI YUSNI');
        $attendance = $this->attendance(10, 2, '2026-09-01', ControlRoomShiftCode::S2, 'SID02', 'MUHAMMAD ALI YUSNI', Attendance::STATUS_SESUAI_JADWAL);

        $days = $this->assembler()->assemble(
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

        $days = $this->assembler()->assemble(
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

        $days = $this->assembler()->assemble(
            $weekStart,
            new Collection([$plan]),
            new Collection(),
            CarbonImmutable::parse('2026-09-07'),
        )['days'];

        $this->assertSame('belum_absen', $days[0]['s1'][0]['status']);
    }

    public function test_tap_rfid_menempel_ke_slot_jadwal_yang_benar(): void
    {
        $weekStart = CarbonImmutable::parse('2026-08-31');
        $plan = $this->plan(4, '2026-08-31', ControlRoomShiftCode::S2, '9etfj', 'MUHAMMAD ALI YUSNI');
        $taps = [
            [
                'at' => '2026-08-31 18:01:00',
                'time' => '18:01',
                'date_label' => '31 Agu',
                'type' => 'in',
                'type_label' => 'Check-in',
                'gate' => 'GATE A',
                'passed' => true,
            ],
            [
                'at' => '2026-09-01 07:30:00',
                'time' => '07:30',
                'date_label' => '01 Sep',
                'type' => 'out',
                'type_label' => 'Check-out',
                'gate' => 'GATE A',
                'passed' => true,
            ],
        ];

        $days = $this->assembler()->assemble(
            $weekStart,
            new Collection([$plan]),
            new Collection(),
            CarbonImmutable::parse('2026-09-07'),
            ['2026-08-31|S2|9ETFJ' => $taps],
        )['days'];

        $this->assertSame($taps, $days[0]['s2'][0]['checkinout']);
    }

    public function test_penggantian_menampilkan_dari_siapa_ke_siapa(): void
    {
        $weekStart = CarbonImmutable::parse('2026-08-31');
        $plan = $this->plan(8, '2026-09-01', ControlRoomShiftCode::S1, 'C5BXK', 'IFA APRILLIANTO');
        $attendance = $this->attendance(
            20,
            8,
            '2026-09-01',
            ControlRoomShiftCode::S1,
            'C5BXK',
            'IFA APRILLIANTO',
            Attendance::STATUS_MENGGANTIKAN,
            'FJAVJ',
        );

        $days = $this->assembler()->assemble(
            $weekStart,
            new Collection([$plan]),
            new Collection([$attendance]),
            CarbonImmutable::parse('2026-09-07'),
            [],
            [
                8 => [
                    'from' => 'Agung Nugroho (FJAVJ)',
                    'to' => 'Ifa Aprillianto (C5BXK)',
                    'summary' => 'Agung Nugroho (FJAVJ) → Ifa Aprillianto (C5BXK)',
                ],
            ],
        )['days'];

        $this->assertSame('menggantikan', $days[1]['s1'][0]['status']);
        $this->assertSame('Ifa Aprillianto', $days[1]['s1'][0]['name']);
        $this->assertSame('Agung Nugroho (FJAVJ) → Ifa Aprillianto (C5BXK)', $days[1]['s1'][0]['replacement']);
        $this->assertSame('Agung Nugroho (FJAVJ) → Ifa Aprillianto (C5BXK)', $days[1]['s1'][0]['catatan']);
    }

    private function assembler(): DashboardScheduleWeekAssembler
    {
        return new DashboardScheduleWeekAssembler(
            new ControlRoomRfidCheckinoutReader(new PembatasanLVOlapQuery()),
            new ControlRoomScheduleChangePresenter(),
        );
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
        ?string $replacingSourceKey = null,
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
            'replacing_source_key' => $replacingSourceKey,
            'checked_in_at' => $date.' 08:00:00',
        ]);

        return $attendance;
    }
}
