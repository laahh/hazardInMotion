<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomSiteCode;
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

    public function test_prefix_site_di_master_tetap_cocok_dengan_sap(): void
    {
        $service = $this->service();
        $payload = $service->evaluate(
            [
                ['site' => 'BMO 1', 'lokasi' => '(B PMO) Area Kritis', 'detail_lokasi' => 'Disposal OPD Q1 KDC'],
                ['site' => 'BMO 1', 'lokasi' => 'Area Revegetasi', 'detail_lokasi' => 'Revegetasi BC BMO 1'],
            ],
            $service->coveredAt([
                ['lokasi' => 'Area Kritis', 'detil_lokasi' => 'Disposal OPD Q1 KDC', 'at' => '2026-09-08 09:00:00'],
                ['lokasi' => 'Area Revegetasi', 'detail_lokasi' => 'Revegetasi BC BMO 1', 'at' => '2026-09-08 10:00:00'],
            ]),
        );

        $this->assertTrue($payload['rows'][0]['covered']);
        $this->assertTrue($payload['rows'][1]['covered']);
        $this->assertSame(2, $payload['kpi']['covered']);
        $this->assertSame(0, $payload['kpi']['uncovered']);
    }

    public function test_status_tercover_dari_hsecm_bukan_obds(): void
    {
        $payload = $this->service()->fromHsecmRows(
            [
                [
                    'Site' => 'BMO 1',
                    'Lokasi' => 'Pit A',
                    'Detil_Lokasi' => 'Front',
                    'Status_Coverage_dalam_1_Week' => 'Tercover',
                    'Tercover' => 1,
                    'Day_of_Date' => '2026-09-08',
                ],
                [
                    'Site' => 'BMO 1',
                    'Lokasi' => 'Workshop',
                    'Detil_Lokasi' => 'Office',
                    'Status_Coverage_dalam_1_Week' => 'Belum Tercover',
                    'Tercover' => 0,
                    'Day_of_Date' => '2026-09-08',
                ],
                [
                    'Site' => 'GMO',
                    'Lokasi' => 'Pit Lain',
                    'Detil_Lokasi' => 'Front',
                    'Status_Coverage_dalam_1_Week' => 'Tercover',
                    'Tercover' => 1,
                    'Day_of_Date' => '2026-09-08',
                ],
            ],
            ControlRoomSiteCode::Bmo1,
        );

        $this->assertTrue($payload['loaded']);
        $this->assertSame(2, $payload['kpi']['total']);
        $this->assertSame(1, $payload['kpi']['covered']);
        $this->assertSame(1, $payload['kpi']['uncovered']);
        $this->assertTrue($payload['rows'][0]['covered']);
        $this->assertSame('2026-09-08', $payload['rows'][0]['last_at']);
        $this->assertFalse($payload['rows'][1]['covered']);
    }

    public function test_spasi_ganda_dan_beda_kapital_tetap_tercover(): void
    {
        $service = $this->service();
        $payload = $service->evaluate(
            [
                ['site' => 'BMO 1', 'lokasi' => 'Area  Transportasi', 'detail_lokasi' => 'Jalan Hauling (sarana transportasi)'],
            ],
            $service->coveredAt([
                ['lokasi' => 'area transportasi', 'detil_lokasi' => 'Jalan  Hauling (sarana transportasi)', 'at' => '2026-09-09 07:00:00'],
            ]),
        );

        $this->assertTrue($payload['rows'][0]['covered']);
    }

    public function test_kolom_detail_lokasi_alias_tetap_tercover(): void
    {
        $service = $this->service();
        $payload = $service->evaluate(
            [
                ['site' => 'BMO 1', 'lokasi' => 'Area Revegetasi', 'detail_lokasi' => 'Revegetasi BC BMO 1'],
            ],
            $service->coveredAt([
                ['lokasi' => 'Area Revegetasi', 'detail_lokasi' => 'Revegetasi BC BMO 1', 'at' => '2026-09-08 11:00:00'],
            ]),
        );

        $this->assertTrue($payload['rows'][0]['covered']);
    }

    public function test_sap_berprefix_cocok_dengan_master_tanpa_prefix(): void
    {
        $service = $this->service();
        $payload = $service->evaluate(
            [
                ['site' => 'BMO 1', 'lokasi' => 'Area Kritis', 'detail_lokasi' => 'Disposal OPD Q1 KDC'],
            ],
            $service->coveredAt([
                ['lokasi' => '(B PMO) Area Kritis', 'detil_lokasi' => 'Disposal OPD Q1 KDC', 'at' => '2026-09-08 09:00:00'],
            ]),
        );

        $this->assertTrue($payload['rows'][0]['covered']);
    }

    public function test_detil_sama_lokasi_beda_tidak_saling_cover(): void
    {
        $service = $this->service();
        $payload = $service->evaluate(
            [
                ['site' => 'BMO 1', 'lokasi' => '(B PMO) Area Kritis', 'detail_lokasi' => 'Disposal OPD Q1 KDC'],
                ['site' => 'BMO 1', 'lokasi' => '(B PMO) Pit Q1', 'detail_lokasi' => 'Disposal OPD Q1 KDC'],
            ],
            $service->coveredAt([
                ['lokasi' => 'Area Kritis', 'detil_lokasi' => 'Disposal OPD Q1 KDC', 'at' => '2026-09-08 09:00:00'],
            ]),
        );

        $this->assertTrue($payload['rows'][0]['covered']);
        $this->assertFalse($payload['rows'][1]['covered']);
        $this->assertSame(1, $payload['kpi']['covered']);
        $this->assertSame(1, $payload['kpi']['uncovered']);
    }

    private function service(): ControlRoomLocationCoverageService
    {
        return $this->app->make(ControlRoomLocationCoverageService::class);
    }
}
