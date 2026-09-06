<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use Tests\TestCase;

final class ControlRoomSiteCodeTest extends TestCase
{
    public function test_from_dedicated_mengenali_kode_dan_source_key(): void
    {
        $this->assertSame(ControlRoomSiteCode::Bmo1, ControlRoomSiteCode::fromDedicated('BMO 1'));
        $this->assertSame(ControlRoomSiteCode::Bmo1, ControlRoomSiteCode::fromDedicated('bmo1'));
        $this->assertSame(ControlRoomSiteCode::Smo, ControlRoomSiteCode::fromDedicated('SMO'));
        $this->assertSame(ControlRoomSiteCode::HeadOffice, ControlRoomSiteCode::fromDedicated('HO'));
        $this->assertSame(ControlRoomSiteCode::HeadOffice, ControlRoomSiteCode::fromDedicated(null));
        $this->assertSame(ControlRoomSiteCode::HeadOffice, ControlRoomSiteCode::fromDedicated('UNKNOWN'));
    }
}
