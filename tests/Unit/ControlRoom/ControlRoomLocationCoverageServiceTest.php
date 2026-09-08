<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomLocationCoverageService;
use Tests\TestCase;

final class ControlRoomLocationCoverageServiceTest extends TestCase
{
    public function test_satu_oak_pada_kunci_yang_sama_menandai_tercover(): void
    {
        $payload = $this->service()->evaluate(
            [
                ['site' => 'BMO 1', 'lokasi' => 'Pit A', 'detail_lokasi' => 'Front'],
                ['site' => 'BMO 1', 'lokasi' => 'Workshop', 'detail_lokasi' => 'Office'],
            ],
            $this->service()->coveredAt([
                ['lokasi' => 'PIT A', 'detil_lokasi' => 'front', 'at' => '2026-08-31 10:00:00', 'component' => 'oak'],
            ]),
        );

        $this->assertSame(2, $payload['kpi']['total']);
        $this->assertSame(1, $payload['kpi']['covered']);
        $this->assertSame(1, $payload['kpi']['uncovered']);
        $this->assertSame(50.0, $payload['kpi']['percent']);
        $this->assertTrue($payload['rows'][0]['covered']);
        $this->assertSame('2026-08-31 10:00:00', $payload['rows'][0]['last_at']);
        $this->assertFalse($payload['rows'][1]['covered']);
    }

    public function test_kritis_belum_tercover_masuk_perhatian(): void
    {
        $payload = $this->service()->evaluate(
            [
                ['site' => 'LMO', 'lokasi' => '(B7) Area Kritis Blok 7', 'detail_lokasi' => 'Front'],
                ['site' => 'LMO', 'lokasi' => 'Workshop', 'detail_lokasi' => 'Office'],
            ],
            [],
        );

        $this->assertCount(1, $payload['attention']);
        $this->assertSame('(B7) Area Kritis Blok 7', $payload['attention'][0]['lokasi']);
        $this->assertTrue($payload['attention'][0]['is_critical']);
        $this->assertFalse($payload['attention'][0]['covered']);
    }

    public function test_lokasi_biasa_uncovered_tidak_masuk_perhatian(): void
    {
        $payload = $this->service()->evaluate(
            [
                ['site' => 'HO', 'lokasi' => 'Workshop', 'detail_lokasi' => 'Office'],
            ],
            [],
        );

        $this->assertSame([], $payload['attention']);
        $this->assertSame(0, $payload['kpi']['covered']);
        $this->assertSame(1, $payload['kpi']['uncovered']);
    }

    public function test_kritis_yang_sudah_tercover_keluar_dari_perhatian(): void
    {
        $service = $this->service();
        $payload = $service->evaluate(
            [
                ['site' => 'GMO', 'lokasi' => 'Aktivitas Area High Risk', 'detail_lokasi' => 'Pompa'],
            ],
            $service->coveredAt([
                ['lokasi' => 'Aktivitas Area High Risk', 'detil_lokasi' => 'Pompa', 'at' => '2026-09-01 08:00:00', 'component' => 'hazard'],
            ]),
        );

        $this->assertSame([], $payload['attention']);
        $this->assertTrue($payload['rows'][0]['covered']);
        $this->assertTrue($payload['rows'][0]['is_critical']);
    }

    private function service(): ControlRoomLocationCoverageService
    {
        return $this->app->make(ControlRoomLocationCoverageService::class);
    }
}
