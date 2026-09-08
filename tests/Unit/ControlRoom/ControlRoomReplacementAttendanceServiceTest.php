<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\ControlRoomDutyRosterService;
use App\Services\ControlRoom\ControlRoomReplacementAttendanceService;
use App\Services\ControlRoom\Reference\ShiftResolver;
use Carbon\Carbon;
use Tests\TestCase;

final class ControlRoomReplacementAttendanceServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_absen_terjadwal_jadi_menggantikan_jika_ada_sid_lama(): void
    {
        $service = $this->service();

        $this->assertSame(Attendance::STATUS_MENGGANTIKAN, $service->scheduledCheckInStatus('C5BXK'));
        $this->assertSame(Attendance::STATUS_SESUAI_JADWAL, $service->scheduledCheckInStatus(null));
        $this->assertSame(Attendance::STATUS_SESUAI_JADWAL, $service->scheduledCheckInStatus(''));
    }

    public function test_ganti_personil_tanggal_lain_tidak_menulis_absen(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-09 15:00:00', 'Asia/Makassar'));

        $plan = new SchedulePlan();
        $plan->forceFill([
            'site_code' => ControlRoomSiteCode::HeadOffice,
            'date' => '2026-09-10',
            'personnel_source_key' => 'S69PK',
            'personnel_name_snapshot' => 'INDRA NUR SIDIQ',
        ]);

        $this->assertNull($this->service()->recordAfterPersonnelChange($plan, 'C5BXK'));
    }

    private function service(): ControlRoomReplacementAttendanceService
    {
        return new ControlRoomReplacementAttendanceService(
            new ControlRoomDutyRosterService(new ShiftResolver()),
        );
    }
}
