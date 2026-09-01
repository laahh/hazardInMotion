<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Services\Isc\IscPersonnelGpsReader;
use App\Services\Isc\IscPostEventTrackService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class IscPostEventTrackServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_day_window_follows_selected_date(): void
    {
        $this->assertSame('2026-08-20 00:00:00', IscPersonnelGpsReader::dayStart('2026-08-20', 'Asia/Makassar'));
        $this->assertSame('2026-08-21 00:00:00', IscPersonnelGpsReader::dayEndExclusive('2026-08-20', 'Asia/Makassar'));
    }

    public function test_downsample_keeps_start_and_end_and_drops_stationary_points(): void
    {
        $points = [
            ['lat' => 1.95, 'lng' => 117.30, 'at' => '06:00'],
            ['lat' => 1.95001, 'lng' => 117.30001, 'at' => '06:01'],
            ['lat' => 1.96, 'lng' => 117.32, 'at' => '08:00'],
            ['lat' => 1.97, 'lng' => 117.34, 'at' => '12:00'],
        ];
        $out = IscPostEventTrackService::downsample($points, 20, 50.0);

        $this->assertSame('06:00', $out[0]['at']);
        $this->assertSame('12:00', $out[count($out) - 1]['at']);
        $this->assertLessThan(count($points), count($out));
        $this->assertGreaterThanOrEqual(2, count($out));
    }

    public function test_demo_roster_includes_people_and_unit(): void
    {
        $data = app(IscPostEventTrackService::class)->roster('2026-09-01', '', true);

        $this->assertSame('demo', $data['source']);
        $this->assertNotEmpty($data['entries']);
        $entities = array_column($data['entries'], 'entity');
        $this->assertContains('person', $entities);
        $this->assertContains('unit', $entities);
        $this->assertArrayHasKey('has_trail', $data['entries'][0]);
    }

    public function test_demo_search_filters_name(): void
    {
        $data = app(IscPostEventTrackService::class)->roster('2026-09-01', 'andi', true);
        $names = array_column($data['entries'], 'name');

        $this->assertCount(1, $data['entries']);
        $this->assertSame('Andi Pratama', $names[0]);
    }

    public function test_demo_trail_has_ordered_points(): void
    {
        $data = app(IscPostEventTrackService::class)->trail('person', '1', '2026-09-01', true);

        $this->assertSame('demo', $data['source']);
        $this->assertGreaterThanOrEqual(8, count($data['points']));
        $this->assertArrayHasKey('lat', $data['points'][0]);
        $this->assertArrayHasKey('lng', $data['points'][0]);
        $first = $data['points'][0]['at'];
        $last = $data['points'][count($data['points']) - 1]['at'];
        $this->assertNotSame($first, $last);
    }
}
