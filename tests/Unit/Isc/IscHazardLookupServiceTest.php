<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Services\Isc\IscHazardEmployeeLookupService;
use App\Services\Isc\IscHazardSysUserLookupService;
use Tests\TestCase;

final class IscHazardLookupServiceTest extends TestCase
{
    public function test_sysuser_demo_fallback_finds_sid_with_credentials(): void
    {
        $row = app(IscHazardSysUserLookupService::class)->findBySid('2E2AF');

        $this->assertNotNull($row);
        $this->assertSame('2E2AF', $row['sid']);
        $this->assertSame('ifa.aprillianto', $row['username']);
        $this->assertNotEmpty($row['password']);
    }

    public function test_employee_pic_uses_all_karyawan_view_constant(): void
    {
        $this->assertSame('bcsid.bep_vw_safety_all_karyawan', IscHazardEmployeeLookupService::VIEW);
        $this->assertSame('pgsql_direct', IscHazardEmployeeLookupService::CONNECTION);

        $row = app(IscHazardEmployeeLookupService::class)->findBySid('BC002');
        $this->assertNotNull($row);
        $this->assertSame('Budi Santoso', $row['nama']);
    }

    public function test_sysuser_view_is_bcbeats(): void
    {
        $this->assertSame('bcbeats.bep_vw_karyawan_sysuser_user_role', IscHazardSysUserLookupService::VIEW);
        $this->assertSame('pgsql_direct', IscHazardSysUserLookupService::CONNECTION);
    }
}
