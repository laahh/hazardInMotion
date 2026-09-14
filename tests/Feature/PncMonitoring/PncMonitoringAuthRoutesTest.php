<?php

declare(strict_types=1);

namespace Tests\Feature\PncMonitoring;

use Tests\TestCase;

final class PncMonitoringAuthRoutesTest extends TestCase
{
    public function test_guest_diarahkan_login_untuk_portal_dan_dashboard(): void
    {
        $this->get('/pnc-monitoring')->assertRedirect('/login');
        $this->get('/pnc-monitoring/dashboard/ikk')->assertRedirect('/login');
        $this->get('/pnc-monitoring/dashboard/pengawas')->assertRedirect('/login');
        $this->get('/pnc-monitoring/ikk-records')->assertRedirect('/login');
        $this->get('/pnc-monitoring/commissionings')->assertRedirect('/login');
    }
}
