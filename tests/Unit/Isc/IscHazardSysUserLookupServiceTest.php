<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Services\Isc\IscHazardSysUserLookupService;
use Tests\TestCase;

final class IscHazardSysUserLookupServiceTest extends TestCase
{
    public function test_demo_fallback_finds_sid_with_credentials(): void
    {
        $row = app(IscHazardSysUserLookupService::class)->findBySid('2E2AF');

        $this->assertNotNull($row);
        $this->assertSame('2E2AF', $row['sid']);
        $this->assertSame('ifa.aprillianto', $row['username']);
        $this->assertNotEmpty($row['password']);
    }
}
