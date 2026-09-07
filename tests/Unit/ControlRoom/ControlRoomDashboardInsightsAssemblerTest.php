<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomDashboardInsightsAssembler;
use App\Services\ControlRoom\ControlRoomSapDutyReader;
use App\Services\ControlRoom\Metrics\FindingVariety;
use App\Services\ControlRoom\Metrics\TbcValidity;
use App\Services\ControlRoom\Reference\LocationReader;
use App\Services\ControlRoom\Reference\ShiftResolver;
use App\Services\Hsecm\HsecmDatabaseRepository;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Tests\TestCase;

final class ControlRoomDashboardInsightsAssemblerTest extends TestCase
{
    public function test_pareto_memisahkan_jam_laporan_per_shift(): void
    {
        $insights = $this->assembler()->fromFindings(
            [
                $this->finding('FJAVJ', '2026-08-31 08:15:00', 'hazard', 'APD', 'Isolasi Energi'),
                $this->finding('FJAVJ', '2026-08-31 08:40:00', 'inspeksi', 'APD', ''),
                $this->finding('FJAVJ', '2026-08-31 20:10:00', 'observasi', 'Observasi Alat', ''),
            ],
            $this->schedule(),
            ['uncovered' => [], 'total' => 0],
            [],
            sapLoaded: true,
        );

        $this->assertSame(8, $insights['pareto']['s1'][0]['hour']);
        $this->assertSame(2, $insights['pareto']['s1'][0]['count']);
        $this->assertSame(100.0, $insights['pareto']['s1'][0]['cumulative']);
        $this->assertSame(20, $insights['pareto']['s2'][0]['hour']);
        $this->assertSame(1, $insights['pareto']['s2'][0]['count']);
    }

    public function test_highlight_mengelompokkan_golden_rule_dan_menghitung_tbc(): void
    {
        $insights = $this->assembler()->fromFindings(
            [
                $this->finding('FJAVJ', '2026-08-31 08:15:00', 'hazard', 'APD', 'Tidak Melanggar Golden Rules'),
                $this->finding('FJAVJ', '2026-08-31 09:00:00', 'inspeksi', 'APD', 'Isolasi Energi'),
            ],
            $this->schedule(),
            ['uncovered' => ['pit a|front' => true], 'total' => 10],
            [['pelapor_all_karyawan' => 'Agung Nugroho']],
            sapLoaded: true,
        );

        $byName = array_column($insights['highlight']['goldenRules'], 'count', 'name');
        $this->assertSame(1, $byName['Isolasi Energi']);
        $this->assertArrayNotHasKey('Tidak Melanggar Golden Rules', $byName);
        $this->assertSame(1, $insights['highlight']['blindspotCount']);
        $this->assertSame(10, $insights['highlight']['blindspotTotal']);
        $this->assertSame(50.0, $insights['highlight']['tbcPercentage']);
    }

    public function test_kualitas_menghitung_variasi_dan_pelanggaran_golden_rule(): void
    {
        $insights = $this->assembler()->fromFindings(
            [
                $this->finding('FJAVJ', '2026-08-31 08:15:00', 'hazard', 'APD', 'Tidak Melanggar Golden Rules', 'Pit A', 'Front'),
                $this->finding('FJAVJ', '2026-08-31 09:00:00', 'inspeksi', 'Kendaraan', 'Isolasi Energi', 'Pit A', 'Front'),
                $this->finding('FJAVJ', '2026-08-31 10:00:00', 'observasi', 'APD', '', 'Workshop', 'Dalam'),
            ],
            $this->schedule(),
            ['uncovered' => ['pit a|front' => true], 'total' => 4],
            [['pelapor_all_karyawan' => 'Agung Nugroho'], ['pelapor_all_karyawan' => 'Agung Nugroho']],
            sapLoaded: true,
        );

        $row = $insights['quality'][0];
        $this->assertSame('Agung Nugroho', $row['name']);
        $this->assertSame(3, $row['total_findings']);
        $this->assertSame(2, $row['distinct_categories']);
        $this->assertSame(0.67, $row['variety_score']);
        $this->assertSame(1, $row['gr']);
        $this->assertSame(2, $row['blindspot']);
        $this->assertSame(2, $row['tbc']);
    }

    public function test_kualitas_hanya_dari_orang_jaga_dan_jendela_tugas(): void
    {
        $insights = $this->assembler()->fromFindings(
            [
                $this->finding('FJAVJ', '2026-08-31 08:15:00', 'hazard', 'Tidak menggunakan APD', ''),
                $this->finding('FJAVJ', '2026-08-31 09:00:00', 'inspeksi', 'Tidak ada pengawas', ''),
                $this->finding('FJAVJ', '2026-09-02 08:00:00', 'hazard', 'Di luar jaga', ''),
                $this->finding('XXXXX', '2026-08-31 08:00:00', 'hazard', 'Bukan jaga CR', ''),
            ],
            $this->schedule(),
            ['uncovered' => [], 'total' => 0],
            [],
            sapLoaded: true,
        );

        $this->assertCount(1, $insights['quality']);
        $row = $insights['quality'][0];
        $this->assertSame('Agung Nugroho', $row['name']);
        $this->assertSame(2, $row['total_findings']);
        $this->assertSame(2, $row['distinct_categories']);
        $this->assertSame(1.0, $row['variety_score']);
    }

    public function test_coverage_personil_menghitung_lokasi_unik_dan_kritis_saat_jaga(): void
    {
        $days = [
            [
                'date' => '2026-08-31',
                's1' => [['name' => 'Agung Nugroho', 'sid' => 'FJAVJ']],
                's2' => [['name' => 'Muhammad Ali Yusni', 'sid' => 'ALI01']],
            ],
        ];

        $insights = $this->assembler()->fromFindings(
            [
                $this->finding('FJAVJ', '2026-08-31 08:15:00', 'hazard', 'APD', '', '(B7) Area Kritis Blok 7', 'Front'),
                $this->finding('FJAVJ', '2026-08-31 09:00:00', 'inspeksi', 'APD', '', '(B7) Area Kritis Blok 7', 'Front'),
                $this->finding('FJAVJ', '2026-08-31 10:00:00', 'observasi', 'APD', '', 'PIT Q1', 'View Point'),
                $this->finding('FJAVJ', '2026-08-31 11:00:00', 'oak', 'APD', '', 'LATI', 'Area Pengeboran'),
                $this->finding('XXXXX', '2026-08-31 12:00:00', 'hazard', 'APD', '', 'Workshop kemakmuran', 'Dalam'),
            ],
            $days,
            ['uncovered' => [], 'total' => 0],
            [],
            sapLoaded: true,
        );

        $byName = [];
        foreach ($insights['personnelCoverage'] as $row) {
            $byName[$row['name']] = $row;
        }

        $this->assertSame(3, $byName['Agung Nugroho']['lokasi']);
        $this->assertSame(2, $byName['Agung Nugroho']['kritis']);
        $this->assertTrue($byName['Agung Nugroho']['lead']);
        $this->assertSame(0, $byName['Muhammad Ali Yusni']['lokasi']);
        $this->assertSame(0, $byName['Muhammad Ali Yusni']['kritis']);
        $this->assertArrayNotHasKey('XXXXX', $byName);
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(
        string $sid,
        string $at,
        string $component,
        string $category,
        string $goldenRule,
        string $lokasi = '',
        string $detil = '',
    ): array {
        return [
            'sid' => $sid,
            'name' => 'AGUNG NUGROHO',
            'at' => $at,
            'hour' => (int) substr($at, 11, 2),
            'component' => $component,
            'category' => $category,
            'golden_rule' => $goldenRule,
            'lokasi' => $lokasi,
            'detil_lokasi' => $detil,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function schedule(): array
    {
        return [[
            'date' => '2026-08-31',
            's1' => [['name' => 'Agung Nugroho', 'sid' => 'FJAVJ']],
            's2' => [],
        ]];
    }

    private function assembler(): ControlRoomDashboardInsightsAssembler
    {
        return new ControlRoomDashboardInsightsAssembler(
            new ShiftResolver(),
            new FindingVariety(),
            new TbcValidity(),
            new LocationReader(new PembatasanLVOlapQuery()),
            new HsecmDatabaseRepository(),
            new ControlRoomSapDutyReader(new PembatasanLVOlapQuery()),
        );
    }
}
