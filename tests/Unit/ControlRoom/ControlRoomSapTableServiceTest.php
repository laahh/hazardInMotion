<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomSapTableService;
use Tests\TestCase;

final class ControlRoomSapTableServiceTest extends TestCase
{
    public function test_hanya_sid_jaga_yang_masuk_tabel(): void
    {
        $payload = $this->service()->present(
            [
                'FJAVJ' => [
                    'sid' => 'FJAVJ',
                    'name' => 'Agung Nugroho',
                    'sites' => ['BMO1' => 'BMO 1'],
                    'dates' => ['2026-09-07' => '2026-09-07'],
                ],
            ],
            [
                ['sid' => 'FJAVJ', 'name' => 'Nama SAP', 'at' => '2026-09-07 08:00:00', 'component' => 'hazard', 'category' => 'Jalan licin', 'golden_rule' => 'GR1', 'lokasi' => 'Area Kritis', 'detil_lokasi' => 'Disposal OPD Q1 KDC', 'report_id' => '11'],
                ['sid' => 'XXXXX', 'name' => 'Bukan Jaga', 'at' => '2026-09-07 09:00:00', 'component' => 'oak', 'category' => 'OAK', 'golden_rule' => '', 'lokasi' => 'Pit', 'detil_lokasi' => 'Front', 'report_id' => '22'],
            ],
        );

        $this->assertCount(1, $payload['rows']);
        $this->assertSame('Agung Nugroho', $payload['rows'][0]['name']);
        $this->assertSame('BMO 1', $payload['rows'][0]['sites']);
        $this->assertSame('Hazard', $payload['rows'][0]['component_label']);
        $this->assertSame(1, $payload['kpi']['personnel']);
        $this->assertSame(1, $payload['kpi']['reporters']);
        $this->assertSame(0, $payload['kpi']['without_sap']);
        $this->assertSame(1, $payload['kpi']['hazard']);
        $this->assertSame(0, $payload['kpi']['oak']);
    }

    public function test_laporan_terbaru_di_atas(): void
    {
        $payload = $this->service()->present(
            [
                'FJAVJ' => [
                    'sid' => 'FJAVJ',
                    'name' => 'Agung Nugroho',
                    'sites' => ['BMO1' => 'BMO 1'],
                    'dates' => ['2026-09-07' => '2026-09-07'],
                ],
            ],
            [
                ['sid' => 'FJAVJ', 'at' => '2026-09-07 08:00:00', 'component' => 'inspeksi', 'category' => 'A', 'report_id' => '1'],
                ['sid' => 'FJAVJ', 'at' => '2026-09-08 10:00:00', 'component' => 'observasi', 'category' => 'B', 'report_id' => '2'],
            ],
        );

        $this->assertSame('2', $payload['rows'][0]['report_id']);
        $this->assertSame('Observasi', $payload['rows'][0]['component_label']);
        $this->assertSame(1, $payload['kpi']['inspeksi']);
        $this->assertSame(1, $payload['kpi']['observasi']);
        $this->assertSame(2, $payload['kpi']['total']);
    }

    public function test_personil_jaga_tanpa_sap_tetap_dihitung(): void
    {
        $payload = $this->service()->present(
            [
                'FJAVJ' => [
                    'sid' => 'FJAVJ',
                    'name' => 'Agung Nugroho',
                    'sites' => ['BMO1' => 'BMO 1'],
                    'dates' => ['2026-09-07' => '2026-09-07'],
                ],
                'C5BXK' => [
                    'sid' => 'C5BXK',
                    'name' => 'Ifa Aprillianto',
                    'sites' => ['BMO1' => 'BMO 1'],
                    'dates' => ['2026-09-08' => '2026-09-08'],
                ],
            ],
            [
                ['sid' => 'FJAVJ', 'at' => '2026-09-07 08:00:00', 'component' => 'oak', 'category' => 'Aktifitas', 'report_id' => '9'],
            ],
        );

        $this->assertSame(2, $payload['kpi']['personnel']);
        $this->assertSame(1, $payload['kpi']['reporters']);
        $this->assertSame(1, $payload['kpi']['without_sap']);
        $this->assertSame(1, $payload['kpi']['oak']);
    }

    private function service(): ControlRoomSapTableService
    {
        return $this->app->make(ControlRoomSapTableService::class);
    }
}
