<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\ControlRoomDataQualityService;
use App\Services\ControlRoom\ControlRoomSapDutyReader;
use App\Services\ControlRoom\ControlRoomSapQualityFindingsReader;
use App\Services\ControlRoom\ControlRoomSiteDutyBoardService;
use App\Services\ControlRoom\Metrics\ControlRoomSapQualityEvaluator;
use App\Services\ControlRoom\Metrics\FindingVariety;
use App\Services\ControlRoom\Metrics\SapAchievement;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Tests\TestCase;

final class ControlRoomSapQualityEvaluatorTest extends TestCase
{
    public function test_hanya_oak_tidak_penuh_kelengkapan_mix(): void
    {
        $row = $this->evaluator()->evaluate(
            'FJAVJ',
            'Agung Nugroho',
            'HO',
            'Head Office',
            ['2026-08-31'],
            [$this->finding('oak', '2026-08-31 10:00:00', 'Aktivitas')],
        );

        $this->assertSame(33.3, $row['scores']['mix']);
        $this->assertSame(1, $row['oak']);
        $this->assertSame(0, $row['hazard']);
    }

    public function test_laporan_h_plus_satu_menurunkan_disiplin_waktu(): void
    {
        $row = $this->evaluator()->evaluate(
            'FJAVJ',
            'Agung Nugroho',
            'HO',
            'Head Office',
            ['2026-08-31'],
            [$this->finding('hazard', '2026-09-01 10:00:00', 'APD')],
        );

        $this->assertSame(0.0, $row['scores']['timing']);
        $this->assertSame(1, $row['total']);
    }

    public function test_laporan_hari_h_skor_waktu_penuh(): void
    {
        $row = $this->evaluator()->evaluate(
            'FJAVJ',
            'Agung Nugroho',
            'HO',
            'Head Office',
            ['2026-08-31'],
            [$this->finding('hazard', '2026-08-31 08:00:00', 'APD')],
        );

        $this->assertSame(100.0, $row['scores']['timing']);
    }

    public function test_tanpa_deskripsi_kedalaman_nol(): void
    {
        $row = $this->evaluator()->evaluate(
            'FJAVJ',
            'Agung Nugroho',
            'HO',
            'Head Office',
            ['2026-08-31'],
            [$this->finding('hazard', '2026-08-31 08:00:00', 'APD', description: '', photo: 'https://example.test/a.jpg')],
        );

        $this->assertSame(0.0, $row['scores']['depth']);
    }

    public function test_deskripsi_dan_foto_mengisi_kedalaman(): void
    {
        $row = $this->evaluator()->evaluate(
            'FJAVJ',
            'Agung Nugroho',
            'HO',
            'Head Office',
            ['2026-08-31'],
            [$this->finding('hazard', '2026-08-31 08:00:00', 'APD', description: 'Tidak memakai helm', photo: 'https://example.test/a.jpg')],
        );

        $this->assertSame(100.0, $row['scores']['depth']);
    }

    public function test_tanpa_temuan_label_kosong(): void
    {
        $row = $this->evaluator()->evaluate(
            'FJAVJ',
            'Agung Nugroho',
            'HO',
            'Head Office',
            ['2026-08-31'],
            [],
        );

        $this->assertSame('Belum ada temuan', $row['label']);
        $this->assertSame(0.0, $row['composite']);
        $this->assertNull($row['weakest']);
    }

    public function test_jakarta_tidak_masuk_papan_data_quality(): void
    {
        $service = $this->app->make(ControlRoomDataQualityService::class);

        $this->assertSame([], $service->sites(ControlRoomSiteCode::Jakarta));
        $this->assertCount(8, $service->sites(null));
        $this->assertSame(
            ControlRoomSiteDutyBoardService::BOARD_SITE_CODES,
            array_map(static fn (ControlRoomSiteCode $site): string => $site->value, $service->sites(null)),
        );
    }

    public function test_flag_sql_kedalaman_tanpa_kolom_berat(): void
    {
        $evaluator = $this->evaluator();

        $this->assertTrue($evaluator->isDeep([
            'has_text' => true,
            'has_photo' => true,
            'has_geo' => false,
        ]));
        $this->assertTrue($evaluator->isDeep([
            'has_text' => true,
            'has_photo' => false,
            'has_geo' => true,
        ]));
        $this->assertFalse($evaluator->isDeep([
            'has_text' => true,
            'has_photo' => false,
            'has_geo' => false,
        ]));
        $this->assertFalse($evaluator->isDeep([
            'has_text' => false,
            'has_photo' => true,
            'has_geo' => false,
            'description' => 'Ada temuan',
            'photo_url' => 'https://example.test/a.jpg',
        ]));
    }

    public function test_sid_dipecah_supaya_query_olap_tidak_timeout(): void
    {
        $this->assertSame(12, ControlRoomSapQualityFindingsReader::SID_CHUNK);
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(
        string $component,
        string $at,
        string $category,
        string $description = 'Ada temuan',
        string $photo = '',
        string $lokasi = 'Pit A',
        string $detil = 'Front',
    ): array {
        return [
            'sid' => 'FJAVJ',
            'at' => $at,
            'component' => $component,
            'category' => $category,
            'lokasi' => $lokasi,
            'detil_lokasi' => $detil,
            'report_id' => $component.'-'.$at,
            'description' => $description,
            'photo_url' => $photo,
            'latitude' => null,
            'longitude' => null,
        ];
    }

    private function evaluator(): ControlRoomSapQualityEvaluator
    {
        return new ControlRoomSapQualityEvaluator(
            new SapAchievement(),
            new FindingVariety(),
            new ControlRoomSapDutyReader(new PembatasanLVOlapQuery()),
        );
    }
}
