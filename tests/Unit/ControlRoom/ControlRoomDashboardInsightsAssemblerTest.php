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

    public function test_tbc_excel_hanya_tasklist_yang_cocok_report_id_hazard_inspeksi(): void
    {
        $findings = [
            $this->finding('FJAVJ', '2026-08-31 08:15:00', 'hazard', 'APD', '', reportId: '9374205'),
            $this->finding('FJAVJ', '2026-08-31 09:00:00', 'inspeksi', 'APD', '', reportId: '111'),
            $this->finding('FJAVJ', '2026-08-31 10:00:00', 'observasi', 'APD', '', reportId: '999'),
        ];
        $validations = [
            ['tasklist' => '9374205', 'kronologi_singkat' => 'TBC dari Excel', 'sid_pekerja_terlibat' => 'FJAVJ', 'to_be_concerned_hazard' => 'Valid', 'no_alert' => 'Alert'],
            ['tasklist' => '999', 'kronologi_singkat' => 'Observasi tidak dihitung'],
            ['tasklist' => 'TIDAK-ADA', 'kronologi_singkat' => 'Bukan minggu ini'],
        ];

        $matched = $this->assembler()->tbcRowsMatchingFindings($findings, $validations);
        $this->assertCount(1, $matched);
        $this->assertSame('9374205', $matched[0]['tasklist']);
        $this->assertSame('TBC dari Excel', $matched[0]['deskripsi']);

        $insights = $this->assembler()->fromFindings(
            $findings,
            $this->schedule(),
            ['uncovered' => [], 'total' => 0],
            $matched,
            sapLoaded: true,
        );
        $this->assertSame(50.0, $insights['highlight']['tbcPercentage']);
        $this->assertSame('9374205', $insights['highlight']['tbcItems'][0]['tasklist']);
        $this->assertSame('TBC dari Excel', $insights['highlight']['tbcItems'][0]['description']);
        $this->assertSame('FJAVJ', $insights['highlight']['tbcItems'][0]['company_pic']);
        $this->assertSame([], $insights['tbcBySlot']);
    }

    public function test_tbc_loaded_menandai_sudah_dan_belum_plus_persentase_slot(): void
    {
        $findings = [
            $this->finding('FJAVJ', '2026-08-31 08:15:00', 'hazard', 'APD', '', reportId: '9374205', description: 'Tidak memakai APD'),
            $this->finding('FJAVJ', '2026-08-31 09:00:00', 'inspeksi', 'APD', '', reportId: '111', description: 'Inspeksi unit'),
            $this->finding('FJAVJ', '2026-08-31 10:00:00', 'observasi', 'APD', '', reportId: '999'),
        ];
        $matched = $this->assembler()->tbcRowsMatchingFindings($findings, [
            ['Tasklist' => '9374205'],
        ]);

        $insights = $this->assembler()->fromFindings(
            $findings,
            $this->schedule(),
            ['uncovered' => [], 'total' => 0],
            $matched,
            sapLoaded: true,
            tbcLoaded: true,
        );

        $this->assertSame(50.0, $insights['highlight']['tbcPercentage']);
        $this->assertSame(50.0, $insights['tbcBySlot']['FJAVJ|2026-08-31']);
        $this->assertSame(1, $insights['tbcMetaBySid']['FJAVJ']['matched']);
        $this->assertSame(2, $insights['tbcMetaBySid']['FJAVJ']['total']);
        $this->assertSame(50.0, $insights['tbcMetaBySid']['FJAVJ']['percent']);
        $statuses = array_column($insights['highlight']['tbcItems'], 'status', 'tasklist');
        $this->assertSame('Belum TBC', $statuses['111']);
        $this->assertSame('Sudah TBC', $statuses['9374205']);
        $this->assertArrayNotHasKey('999', $statuses);
        $this->assertSame(1, $insights['quality'][0]['tbc']);
        $this->assertSame(2, $insights['quality'][0]['tbc_basis']);
    }

    public function test_tbc_loaded_tanpa_cocokan_menghasilkan_nol_persen(): void
    {
        $insights = $this->assembler()->fromFindings(
            [
                $this->finding('FJAVJ', '2026-08-31 08:15:00', 'hazard', 'APD', '', reportId: '9374205'),
                $this->finding('FJAVJ', '2026-08-31 09:00:00', 'inspeksi', 'APD', '', reportId: '111'),
            ],
            $this->schedule(),
            ['uncovered' => [], 'total' => 0],
            [],
            sapLoaded: true,
            tbcLoaded: true,
        );

        $this->assertSame(0.0, $insights['highlight']['tbcPercentage']);
        $this->assertSame(0.0, $insights['tbcBySlot']['FJAVJ|2026-08-31']);
        $this->assertSame(0, $insights['quality'][0]['tbc']);
        $this->assertSame(2, $insights['quality'][0]['tbc_basis']);
        $this->assertSame('Belum TBC', $insights['highlight']['tbcItems'][0]['status']);
    }

    public function test_persen_tbc_personil_akumulasi_semua_hari_jaga_orang_itu(): void
    {
        $days = [
            [
                'date' => '2026-08-31',
                's1' => [['name' => 'Agung Nugroho', 'sid' => 'FJAVJ']],
                's2' => [],
            ],
            [
                'date' => '2026-09-01',
                's1' => [['name' => 'Agung Nugroho', 'sid' => 'FJAVJ']],
                's2' => [],
            ],
        ];
        $findings = [
            $this->finding('FJAVJ', '2026-08-31 08:15:00', 'hazard', 'APD', '', reportId: '9374205'),
            $this->finding('FJAVJ', '2026-09-01 09:00:00', 'inspeksi', 'APD', '', reportId: '111'),
            $this->finding('FJAVJ', '2026-09-01 10:00:00', 'observasi', 'APD', '', reportId: '999'),
        ];
        $matched = $this->assembler()->tbcRowsMatchingFindings($findings, [
            ['Tasklist' => '9374205'],
        ]);

        $insights = $this->assembler()->fromFindings(
            $findings,
            $days,
            ['uncovered' => [], 'total' => 0],
            $matched,
            sapLoaded: true,
            tbcLoaded: true,
        );

        $this->assertSame(50.0, $insights['tbcMetaBySid']['FJAVJ']['percent']);
        $this->assertSame(50.0, $insights['tbcBySlot']['FJAVJ|2026-08-31']);
        $this->assertSame(50.0, $insights['tbcBySlot']['FJAVJ|2026-09-01']);
        $this->assertSame(1, $insights['quality'][0]['tbc']);
        $this->assertSame(2, $insights['quality'][0]['tbc_basis']);
    }

    public function test_highlight_kategori_menyertakan_detail_temuan(): void
    {
        $insights = $this->assembler()->fromFindings(
            [
                $this->finding(
                    'FJAVJ',
                    '2026-08-31 09:00:00',
                    'inspeksi',
                    'APD',
                    'Isolasi Energi',
                    'Pit A',
                    'Front',
                    '9374205',
                    'Tidak memakai APD',
                    'ANDRIANSYAH',
                    'PT Serasi Autoraya',
                    'CLOSED',
                ),
                $this->finding('FJAVJ', '2026-08-31 08:15:00', 'hazard', 'APD', 'Tidak Melanggar Golden Rules'),
            ],
            $this->schedule(),
            ['uncovered' => ['pit a|front' => ['lokasi' => 'Pit A', 'detil' => 'Front', 'status' => 'Belum tercover']], 'total' => 10],
            [[
                'Date_for_Join' => '2026-08-31',
                'deskripsi' => 'TBC tinggi',
                'pic' => 'BUDI',
                'perusahaan_pic' => 'PT Berau Coal',
                'status3' => 'OPEN',
                'Task_Number' => 'TL-11',
                'pelapor_all_karyawan' => 'Agung Nugroho',
            ]],
            sapLoaded: true,
        );

        $gr = collect($insights['highlight']['goldenRules'])->firstWhere('name', 'Isolasi Energi');
        $this->assertNotNull($gr);
        $this->assertSame('9374205', $gr['items'][0]['tasklist']);
        $this->assertSame('2026-08-31 09:00:00', $gr['items'][0]['found_at']);
        $this->assertSame('Tidak memakai APD', $gr['items'][0]['description']);
        $this->assertSame('PT Serasi Autoraya — ANDRIANSYAH', $gr['items'][0]['company_pic']);
        $this->assertSame('Closed', $gr['items'][0]['status']);
        $this->assertSame('—', $insights['highlight']['blindspotItems'][0]['tasklist']);
        $this->assertSame('Pit A / Front', $insights['highlight']['blindspotItems'][0]['description']);
        $this->assertSame('TL-11', $insights['highlight']['tbcItems'][0]['tasklist']);
        $this->assertSame('TBC tinggi', $insights['highlight']['tbcItems'][0]['description']);
        $this->assertSame('PT Berau Coal — BUDI', $insights['highlight']['tbcItems'][0]['company_pic']);
    }

    public function test_kualitas_menghitung_variasi_tanpa_dummy_tbc_gr_blindspot(): void
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
        $this->assertNull($row['tbc']);
        $this->assertNull($row['gr']);
        $this->assertNull($row['blindspot']);
    }

    public function test_kualitas_total_menghitung_sap_hari_jaga_dan_h_plus_satu(): void
    {
        $insights = $this->assembler()->fromFindings(
            [
                $this->finding('FJAVJ', '2026-08-31 08:15:00', 'hazard', 'APD', ''),
                $this->finding('FJAVJ', '2026-09-01 19:10:00', 'inspeksi', 'Kendaraan', ''),
                $this->finding('FJAVJ', '2026-09-02 00:00:00', 'hazard', 'Di luar jendela', ''),
            ],
            $this->schedule(),
            ['uncovered' => [], 'total' => 0],
            [],
            sapLoaded: true,
        );

        $this->assertSame(2, $insights['quality'][0]['total_findings']);
    }

    public function test_kualitas_total_tidak_menggandakan_baris_observasi_yang_sama(): void
    {
        $insights = $this->assembler()->fromFindings(
            [
                $this->finding('FJAVJ', '2026-08-31 08:15:00', 'observasi', 'APD', '', reportId: '100'),
                $this->finding('FJAVJ', '2026-08-31 08:15:00', 'observasi', 'APD', '', reportId: '100'),
                $this->finding('FJAVJ', '2026-08-31 09:00:00', 'oak', 'Aktivitas', '', reportId: '11'),
                $this->finding('FJAVJ', '2026-08-31 09:00:00', 'oak', 'Aktivitas', '', reportId: '11'),
                $this->finding('FJAVJ', '2026-08-31 10:00:00', 'hazard', 'APD', '', reportId: '200'),
            ],
            $this->schedule(),
            ['uncovered' => [], 'total' => 0],
            [],
            sapLoaded: true,
        );

        $this->assertSame(3, $insights['quality'][0]['total_findings']);
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
        string $reportId = '',
        string $description = '',
        string $pic = '',
        string $company = '',
        string $status = '',
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
            'report_id' => $reportId,
            'description' => $description,
            'pic' => $pic,
            'company' => $company,
            'status' => $status,
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
