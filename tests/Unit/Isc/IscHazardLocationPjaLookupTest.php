<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Services\Isc\IscHazardLocationLookupService;
use App\Services\Isc\IscHazardPjaLookupService;
use Tests\TestCase;

final class IscHazardLocationPjaLookupTest extends TestCase
{
    public function test_location_view_matches_control_room_source(): void
    {
        $this->assertSame('bcbeats.bep_vw_site_lokasi_detil_lokasi', IscHazardLocationLookupService::VIEW);
    }

    public function test_pja_view_constant(): void
    {
        $this->assertSame('bcbeats.wan_vw_relasi_lokasi_pja', IscHazardPjaLookupService::VIEW);
    }
}
