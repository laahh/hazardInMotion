<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\Attendance;
use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\ControlRoomDutyRosterService;
use App\Services\ControlRoom\ControlRoomSiteDutyBoardService;
use App\Services\ControlRoom\Reference\ShiftResolver;
use Tests\TestCase;

final class ControlRoomSiteDutyBoardServiceTest extends TestCase
{
    public function test_hijau_hanya_jika_ada_jadwal_dan_sudah_absen(): void
    {
        $service = $this->service();

        $this->assertSame('green', $service->resolveState(true, true)['tone']);
        $this->assertSame('Terjaga', $service->resolveState(true, true)['state']);
        $this->assertSame('red', $service->resolveState(true, false)['tone']);
        $this->assertSame('Belum absen', $service->resolveState(true, false)['state']);
        $this->assertSame('Tidak ada jadwal', $service->resolveState(false, false)['state']);
        $this->assertSame('red', $service->resolveState(false, true)['tone']);
    }

    public function test_kartu_memuat_nama_jadwal_dan_yang_sudah_jaga(): void
    {
        $plan = new SchedulePlan();
        $plan->forceFill([
            'site_code' => ControlRoomSiteCode::Bmo1,
            'personnel_source_key' => 'c5bxk',
            'personnel_name_snapshot' => 'IFA APRILLIANTO',
        ]);

        $attendance = new Attendance();
        $attendance->forceFill([
            'site_code' => ControlRoomSiteCode::Bmo1,
            'personnel_source_key' => 'c5bxk',
            'personnel_name_snapshot' => 'IFA APRILLIANTO',
        ]);

        $card = $this->service()->composeCard(
            ControlRoomSiteCode::Bmo1,
            collect([$plan]),
            collect([$attendance]),
        );

        $this->assertSame('green', $card['tone']);
        $this->assertSame('BMO1', $card['site']);
        $this->assertSame('C5BXK', $card['scheduled'][0]['sid']);
        $this->assertSame('C5BXK', $card['present'][0]['sid']);
        $this->assertNotSame('', $card['sparkLine']);
    }

    public function test_papan_hanya_delapan_site_operasi(): void
    {
        $this->assertSame(
            ['HO', 'BMO1', 'BMO2', 'BMO3', 'GMO', 'LMO', 'PMO', 'SMO'],
            ControlRoomSiteDutyBoardService::BOARD_SITE_CODES,
        );
        $this->assertNotContains('MARINE', ControlRoomSiteDutyBoardService::BOARD_SITE_CODES);
        $this->assertNotContains('EKSPLORASI', ControlRoomSiteDutyBoardService::BOARD_SITE_CODES);
        $this->assertNotContains('JAKARTA', ControlRoomSiteDutyBoardService::BOARD_SITE_CODES);
    }

    public function test_sparkline_menghasilkan_garis_dan_area(): void
    {
        $spark = $this->service()->sparkline([0, 1, 2, 1, 3, 2, 4]);

        $this->assertNotSame('', $spark['line']);
        $this->assertStringStartsWith('0,', $spark['area']);
        $this->assertStringContainsString('88,', $spark['area']);
    }

    private function service(): ControlRoomSiteDutyBoardService
    {
        return new ControlRoomSiteDutyBoardService(
            new ControlRoomDutyRosterService(new ShiftResolver()),
        );
    }
}
