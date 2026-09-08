<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\ControlRoomAttendanceQrCodeService;
use App\Services\ControlRoom\ControlRoomSiteDutyBoardService;
use Tests\TestCase;

final class ControlRoomAttendanceQrCodeServiceTest extends TestCase
{
    public function test_url_form_membawa_query_site(): void
    {
        $url = (new ControlRoomAttendanceQrCodeService())->attendanceFormUrl(ControlRoomSiteCode::Bmo2);

        $this->assertStringContainsString('/control-room/attendance/form', $url);
        $this->assertStringContainsString('site=BMO2', $url);
        $this->assertStringNotContainsString('site=HO', $url);
    }

    public function test_kartu_hanya_untuk_site_papan(): void
    {
        $cards = (new ControlRoomAttendanceQrCodeService())->cards();
        $codes = array_map(static fn (array $card): string => $card['site']->value, $cards);

        $this->assertSame(ControlRoomSiteDutyBoardService::BOARD_SITE_CODES, $codes);
        $this->assertStringContainsString('<svg', $cards[0]['svg']);
        $this->assertStringContainsString('site=HO', $cards[0]['url']);
    }
}
