<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\ControlRoomLocationCoverageService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
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

    public function test_daily_menampilkan_semua_lokasi_dan_hanya_tanggal_terpilih(): void
    {
        $service = $this->service();
        $payload = $service->evaluateDaily(
            [
                ['site' => 'GMO', 'lokasi' => 'Aktivitas Area High Risk', 'detail_lokasi' => 'Pompa'],
                ['site' => 'GMO', 'lokasi' => 'Workshop', 'detail_lokasi' => 'Office'],
            ],
            $service->coveredHits([
                ['lokasi' => 'Aktivitas Area High Risk', 'detil_lokasi' => 'Pompa', 'at' => '2026-08-31 08:00:00'],
                ['lokasi' => 'Workshop', 'detil_lokasi' => 'Office', 'at' => '2026-08-31 09:00:00'],
            ]),
            '2026-08-31',
        );

        $this->assertSame(2, $payload['kpi']['total']);
        $this->assertSame(2, $payload['kpi']['covered']);
        $this->assertSame(0, $payload['kpi']['uncovered']);
        $this->assertSame(1, $payload['critical_count']);
        $this->assertSame(1, $payload['noncritical_count']);
        $this->assertTrue($payload['rows'][0]['is_critical']);
        $this->assertFalse($payload['rows'][1]['is_critical']);
        $this->assertTrue($payload['rows'][0]['covered']);
        $this->assertTrue($payload['rows'][1]['covered']);
        $this->assertSame(['2026-08-31'], $payload['rows'][0]['covered_dates']);
        $this->assertSame([], $payload['attention']);
        $this->assertSame('2026-08-31', $payload['selected_date']);
    }

    public function test_daily_sap_hari_lain_tidak_mencover_tanggal_terpilih(): void
    {
        $service = $this->service();
        $payload = $service->evaluateDaily(
            [
                ['site' => 'GMO', 'lokasi' => 'Aktivitas Area High Risk', 'detail_lokasi' => 'Pompa'],
                ['site' => 'GMO', 'lokasi' => 'Workshop', 'detail_lokasi' => 'Office'],
            ],
            $service->coveredHits([
                ['lokasi' => 'Aktivitas Area High Risk', 'detil_lokasi' => 'Pompa', 'at' => '2026-08-31 08:00:00'],
                ['lokasi' => 'Workshop', 'detil_lokasi' => 'Office', 'at' => '2026-08-31 09:00:00'],
            ]),
            '2026-09-01',
        );

        $this->assertSame(2, $payload['kpi']['total']);
        $this->assertSame(0, $payload['kpi']['covered']);
        $this->assertSame(2, $payload['kpi']['uncovered']);
        $this->assertFalse($payload['rows'][0]['covered']);
        $this->assertSame('Sel', $payload['rows'][0]['gap_label']);
        $this->assertCount(1, $payload['attention']);
        $this->assertSame('Aktivitas Area High Risk', $payload['attention'][0]['lokasi']);
    }

    public function test_daily_tercover_jika_ada_sap_pada_tanggal_terpilih(): void
    {
        $service = $this->service();
        $payload = $service->evaluateDaily(
            [
                ['site' => 'LMO', 'lokasi' => '(B7) Area Kritis Blok 7', 'detail_lokasi' => 'Front'],
            ],
            $service->coveredHits([
                ['lokasi' => '(B7) Area Kritis Blok 7', 'detil_lokasi' => 'Front', 'at' => '2026-08-31 08:00:00'],
                ['lokasi' => '(B7) Area Kritis Blok 7', 'detil_lokasi' => 'Front', 'at' => '2026-09-01 07:30:00'],
            ]),
            '2026-09-01',
        );

        $this->assertTrue($payload['rows'][0]['covered']);
        $this->assertSame(1, $payload['kpi']['covered']);
        $this->assertSame([], $payload['attention']);
        $this->assertSame('—', $payload['rows'][0]['gap_label']);
        $this->assertSame(['2026-08-31', '2026-09-01'], $payload['rows'][0]['covered_dates']);
    }

    public function test_daily_tanpa_sap_dianggap_tidak_tercover(): void
    {
        $payload = $this->service()->evaluateDaily(
            [
                ['site' => 'BMO 1', 'lokasi' => '(B PMO) Area Kritis', 'detail_lokasi' => 'Disposal OPD Q1 KDC'],
            ],
            [],
            '2026-08-31',
        );

        $this->assertFalse($payload['rows'][0]['covered']);
        $this->assertNull($payload['rows'][0]['last_at']);
        $this->assertSame('Sen', $payload['rows'][0]['gap_label']);
        $this->assertSame([], $payload['rows'][0]['covered_dates']);
    }

    public function test_daily_kritis_dari_detil_lokasi_ikut_ditandai(): void
    {
        $payload = $this->service()->evaluateDaily(
            [
                ['site' => 'BMO 1', 'lokasi' => 'Pit A', 'detail_lokasi' => 'Area Eksplorasi Q1'],
                ['site' => 'BMO 1', 'lokasi' => 'Pit A', 'detail_lokasi' => 'Office'],
            ],
            [],
            '2026-09-01',
        );

        $this->assertTrue($payload['rows'][0]['is_critical']);
        $this->assertFalse($payload['rows'][1]['is_critical']);
        $this->assertSame(1, $payload['critical_count']);
        $this->assertSame(1, $payload['noncritical_count']);
        $this->assertSame('Area Eksplorasi Q1', $payload['attention'][0]['detail_lokasi']);
    }

    public function test_weekly_cukup_satu_sap_meski_tidak_setiap_hari(): void
    {
        $service = $this->service();
        $hits = $service->coveredHits([
            ['lokasi' => 'Aktivitas Area High Risk', 'detil_lokasi' => 'Pompa', 'at' => '2026-08-31 08:00:00'],
        ]);
        $weekly = $service->evaluateWeekly(
            [
                ['site' => 'GMO', 'lokasi' => 'Aktivitas Area High Risk', 'detail_lokasi' => 'Pompa'],
            ],
            $hits,
        );
        $dailyOtherDay = $service->evaluateDaily(
            [
                ['site' => 'GMO', 'lokasi' => 'Aktivitas Area High Risk', 'detail_lokasi' => 'Pompa'],
            ],
            $hits,
            '2026-09-01',
        );
        $dailySameDay = $service->evaluateDaily(
            [
                ['site' => 'GMO', 'lokasi' => 'Aktivitas Area High Risk', 'detail_lokasi' => 'Pompa'],
            ],
            $hits,
            '2026-08-31',
        );

        $this->assertTrue($weekly['rows'][0]['covered']);
        $this->assertFalse($dailyOtherDay['rows'][0]['covered']);
        $this->assertTrue($dailySameDay['rows'][0]['covered']);
    }

    public function test_build_memakai_hasil_terakhir_saat_obds_baru_saja_gagal(): void
    {
        $this->travelTo('2026-09-11 08:00:00');
        $weekStart = CarbonImmutable::parse('2026-09-06');
        $good = [
            'loaded' => true,
            'daily' => [
                'loaded' => true,
                'kpi' => ['total' => 1, 'covered' => 1, 'uncovered' => 0, 'percent' => 100.0],
                'rows' => [],
                'attention' => [],
            ],
            'weekly' => [
                'loaded' => true,
                'kpi' => ['total' => 1, 'covered' => 1, 'uncovered' => 0, 'percent' => 100.0],
                'rows' => [],
                'attention' => [],
            ],
        ];
        $cacheKey = 'control-room:location-coverage:v13:daily:2026-09-06:HO:2026-09-11';
        Cache::put($cacheKey.':miss', true, 60);
        Cache::put('control-room:location-coverage:v13:daily:last:2026-09-06:HO:2026-09-11', $good, 60);

        $payload = $this->service()->build(ControlRoomSiteCode::HeadOffice, $weekStart);
        $this->travelBack();

        $this->assertTrue($payload['loaded']);
        $this->assertSame(100.0, $payload['daily']['kpi']['percent']);
    }

    private function service(): ControlRoomLocationCoverageService
    {
        return $this->app->make(ControlRoomLocationCoverageService::class);
    }
}
