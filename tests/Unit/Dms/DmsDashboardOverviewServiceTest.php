<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\DmsDashboardDataSource;
use App\Services\Dms\DmsDashboardOverviewService;
use Mockery;
use Tests\TestCase;

class DmsDashboardOverviewServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_empty_payload_when_connection_is_down(): void
    {
        $reader = Mockery::mock(DmsDashboardDataSource::class);
        $reader->shouldReceive('isUp')->once()->andReturn(false);

        $payload = (new DmsDashboardOverviewService($reader))->dashboard();

        $this->assertFalse($payload['up']);
        $this->assertCount(6, $payload['kpis']);
        $this->assertSame('0', $payload['kpis'][0]['value']);
        $this->assertSame('0', $payload['growth']['total']);
        $this->assertSame([], $payload['categories']);
        $this->assertSame([], $payload['topOperators']);
        $this->assertSame([], $payload['recentAll']);
    }

    public function test_maps_alert_summary_into_wowdash_kpi_cards(): void
    {
        $reader = Mockery::mock(DmsDashboardDataSource::class);
        $reader->shouldReceive('isUp')->andReturn(true);

        $empty = [
            'total' => 0,
            'l1_reviewed' => 0,
            'l1_confirmed' => 0,
            'l1_dismissed' => 0,
            'l1_belum' => 0,
            'l2_reviewed' => 0,
            'l2_confirmed' => 0,
            'post_event_eligible' => 0,
        ];
        $today = [
            'total' => 12,
            'l1_reviewed' => 10,
            'l1_confirmed' => 4,
            'l1_dismissed' => 6,
            'l1_belum' => 2,
            'l2_reviewed' => 0,
            'l2_confirmed' => 0,
            'post_event_eligible' => 0,
        ];

        $reader->shouldReceive('alertSummary')->andReturn($today, $empty, $today, $empty);
        $reader->shouldReceive('distinctAlertSids')->andReturn(['A', 'B'], []);
        $reader->shouldReceive('unitsOperatingInRange')->andReturn(5, 3);
        $reader->shouldReceive('unitsOperatingNow')->andReturn(4);
        $reader->shouldReceive('dailyAlertSeries')->andReturn([]);
        $reader->shouldReceive('categoryQuadrant')->andReturn([
            ['nama_pelanggaran' => 'Menutup Mata', 'total' => 8, 'confirmed' => 3, 'confirmation_rate' => 37.5],
        ]);
        $reader->shouldReceive('alertsBySite')->andReturn([
            ['site' => 'Binungan', 'total' => 10, 'confirmed' => 4],
        ]);
        $reader->shouldReceive('alertsByOperator')->andReturn([
            ['kode_sid' => 'K49RF', 'nama' => 'Budi Santoso', 'total' => 5, 'confirmed' => 2],
        ]);
        $reader->shouldReceive('recentAlerts')->andReturn([], []);

        $payload = (new DmsDashboardOverviewService($reader))->dashboard();

        $this->assertTrue($payload['up']);
        $this->assertSame('12', $payload['kpis'][0]['value']);
        $this->assertSame('Total Alert', $payload['kpis'][0]['label']);
        $this->assertSame('2', $payload['kpis'][1]['value']);
        $this->assertSame('4', $payload['kpis'][2]['value']);
        $this->assertSame('40%', $payload['kpis'][3]['value']);
        $this->assertSame('2', $payload['kpis'][4]['value']);
        $this->assertSame('4', $payload['kpis'][5]['value']);
        $this->assertSame('Menutup Mata', $payload['categories'][0]['name']);
        $this->assertSame('Binungan', $payload['sites'][0]['site']);
        $this->assertSame('Budi Santoso', $payload['topOperators'][0]['nama']);
        $this->assertSame('BS', $payload['topOperators'][0]['initials']);
        $this->assertSame(2, $payload['topOperators'][0]['confirmed']);
    }
}
