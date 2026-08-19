<?php

declare(strict_types=1);

namespace Tests\Feature\PraOperasi;

use App\Services\DmsMonitoring\DmsMonitoringKpiDetailService;
use Tests\TestCase;

class DmsMonitoringKpiDetailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_returns_kpi_detail_json_for_valid_request(): void
    {
        $this->mock(DmsMonitoringKpiDetailService::class, function ($mock): void {
            $mock->shouldReceive('detail')
                ->once()
                ->with(
                    'total_alert',
                    ['start' => '2026-08-13', 'end' => '2026-08-19', 'site' => '', 'perusahaan' => ''],
                    'sites',
                    null,
                    null,
                    1,
                )
                ->andReturn([
                    'ok' => true,
                    'metric' => 'total_alert',
                    'label' => 'Total Alert',
                    'level' => 'sites',
                    'total' => '100',
                    'rows' => [
                        ['label' => 'Binungan', 'value' => '80', 'drill' => ['level' => 'companies', 'parent_site' => 'Binungan']],
                    ],
                    'columns' => [
                        ['key' => 'label', 'label' => 'Site'],
                        ['key' => 'value', 'label' => 'Nilai'],
                    ],
                    'breadcrumb' => [['label' => 'Semua Site', 'level' => 'sites']],
                    'drillable' => true,
                    'pagination' => null,
                ]);
        });

        $response = $this->getJson(route('pra-operasi.dms-monitoring.kpi-detail', [
            'metric' => 'total_alert',
            'start' => '2026-08-13',
            'end' => '2026-08-19',
            'level' => 'sites',
        ]));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('metric', 'total_alert')
            ->assertJsonPath('rows.0.label', 'Binungan');
    }

    public function test_validation_fails_when_dates_missing(): void
    {
        $response = $this->getJson(route('pra-operasi.dms-monitoring.kpi-detail', [
            'metric' => 'total_alert',
            'level' => 'sites',
        ]));

        $response->assertUnprocessable();
    }

    public function test_invalid_metric_returns_not_found(): void
    {
        $response = $this->getJson('/pra-operasi/dashboard/kpi/invalid-metric/detail?start=2026-08-13&end=2026-08-19&level=sites');

        $response->assertNotFound();
    }

    public function test_kpi_detail_service_exposes_site_quadrant_matrix(): void
    {
        $this->assertTrue(method_exists(DmsMonitoringKpiDetailService::class, 'siteQuadrantMatrix'));
        $this->assertTrue(method_exists(\App\Services\DmsMonitoring\DmsMonitoringControlRoomPerformanceService::class, 'matrix'));
    }
}
