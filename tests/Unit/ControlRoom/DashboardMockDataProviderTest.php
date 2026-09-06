<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\DashboardMockDataProvider;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class DashboardMockDataProviderTest extends TestCase
{
    public function test_tabel_pencapaian_dikelompokkan_per_tanggal_dari_jadwal(): void
    {
        $days = [
            [
                'date' => '2026-08-31',
                's1' => [[
                    'name' => 'Agung Nugroho',
                    'sid' => 'FJAVJ',
                    'status' => 'sesuai',
                    'checkinout' => [
                        ['time' => '07:30', 'date_label' => '31 Agu', 'type' => 'in', 'type_label' => 'Check-in', 'gate' => 'POS 1', 'passed' => true],
                    ],
                ]],
                's2' => [[
                    'name' => 'Muhammad Ali Yusni',
                    'status' => 'sesuai',
                    'checkinout' => [
                        ['time' => '18:01', 'date_label' => '31 Agu', 'type' => 'in', 'type_label' => 'Check-in', 'gate' => 'POS 2', 'passed' => true],
                        ['time' => '07:30', 'date_label' => '01 Sep', 'type' => 'out', 'type_label' => 'Check-out', 'gate' => 'POS 2', 'passed' => true],
                    ],
                ]],
            ],
        ];

        $mock = (new DashboardMockDataProvider())->build(CarbonImmutable::parse('2026-08-31'), $days);
        $group = $mock['achievementGroups'][0];

        $this->assertSame('8/31/2026', $group['date_label']);
        $this->assertCount(2, $group['rows']);
        $this->assertSame('Agung Nugroho', $group['rows'][0]['name']);
        $this->assertSame('FJAVJ', $group['rows'][0]['sid']);
        $this->assertSame(100.0, $group['rows'][0]['attendance_pct']);
        $this->assertNull($group['rows'][0]['sap']);
        $this->assertNull($group['rows'][0]['tbc']);
        $this->assertSame('07:30', $group['rows'][0]['checkinout'][0]['time']);
        $this->assertSame('S2', $group['rows'][1]['shift']);
        $this->assertCount(2, $group['rows'][1]['checkinout']);
        $names = array_column($mock['personnelCoverage'], 'name');
        $this->assertContains('Agung Nugroho', $names);
        $this->assertContains('Muhammad Ali Yusni', $names);
        $this->assertTrue($mock['personnelCoverage'][0]['lead']);
    }

    public function test_sap_mengikuti_target_satu_hazard_inspeksi_dan_observasi(): void
    {
        $days = [
            [
                'date' => '2026-08-31',
                's1' => [[
                    'name' => 'Agung Nugroho',
                    'sid' => 'FJAVJ',
                    'status' => 'sesuai',
                    'checkinout' => [],
                ]],
                's2' => [[
                    'name' => 'Herru Siswahyudi',
                    'sid' => 'HHHHH',
                    'status' => 'tidak_hadir',
                    'checkinout' => [],
                ]],
            ],
        ];

        $counts = [
            'FJAVJ|2026-08-31' => ['hazard' => 2, 'inspeksi' => 1, 'observasi' => 1],
            'HHHHH|2026-08-31' => ['hazard' => 1, 'inspeksi' => 0, 'observasi' => 0],
        ];

        $mock = (new DashboardMockDataProvider())->build(
            CarbonImmutable::parse('2026-08-31'),
            $days,
            $counts,
            sapLoaded: true,
        );

        $this->assertSame(100.0, $mock['achievementGroups'][0]['rows'][0]['attendance_pct']);
        $this->assertSame(100.0, $mock['achievementGroups'][0]['rows'][0]['sap']);
        $this->assertSame(0.0, $mock['achievementGroups'][0]['rows'][1]['attendance_pct']);
        $this->assertSame(33.33, $mock['achievementGroups'][0]['rows'][1]['sap']);
        $this->assertNull($mock['achievementGroups'][0]['rows'][0]['tbc']);
    }
}
