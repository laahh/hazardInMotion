<?php

declare(strict_types=1);

namespace Tests\Unit\OakCcv;

use App\Services\OakCcv\OakCcvDashboardPayloadService;
use App\Support\OakCcv\OakCcvCompanyClassifier;
use Tests\TestCase;

final class OakCcvDashboardPayloadServiceTest extends TestCase
{
    public function test_classifies_bc_group_and_mitra(): void
    {
        $this->assertSame(['group' => 'BC', 'entity' => 'BC'], OakCcvCompanyClassifier::classify('PT Berau Coal'));
        $this->assertSame(['group' => 'BC', 'entity' => 'BCE'], OakCcvCompanyClassifier::classify('PT Berau Coal Energy'));
        $this->assertSame(['group' => 'BC', 'entity' => 'Unggul'], OakCcvCompanyClassifier::classify('PT Unggul Jaya Berkah'));
        $this->assertSame(['group' => 'BC', 'entity' => 'Primac'], OakCcvCompanyClassifier::classify('PT Primac Perkasa Indonesia'));
        $this->assertSame(['group' => 'BC', 'entity' => 'Suprima'], OakCcvCompanyClassifier::classify('PT Suprima Mitra Adihusada'));
        $this->assertSame(['group' => 'BC', 'entity' => 'Yayasan'], OakCcvCompanyClassifier::classify('Yayasan Dharma Bakti Berau Coal'));
        $this->assertSame(['group' => 'Mitra', 'entity' => 'Mitra'], OakCcvCompanyClassifier::classify('PT Pamapersada Nusantara'));
    }

    public function test_build_from_fixture_filters_bc_group(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oak_ccv_fixture_'.uniqid('', true).'.json';
        file_put_contents($path, json_encode($this->fixture(), JSON_THROW_ON_ERROR));

        try {
            $payload = (new OakCcvDashboardPayloadService($path))->build(['group' => 'bc']);
        } finally {
            @unlink($path);
        }

        $this->assertSame(10, $payload['kpi']['laporan_rows']);
        $this->assertSame(10, $payload['kpi']['bc_rows']);
        $this->assertSame(0, $payload['kpi']['mitra_rows']);
        $this->assertSame(100.0, $payload['kpi']['bc_pct']);
        $this->assertSame(1, $payload['kpi']['stop_gaps']);
        $this->assertSame('OBSERVASI AREA KRITIS', $payload['jenis_data']);
        $this->assertNotEmpty($payload['evaluation']['narrative']);
    }

    public function test_daily_bc_vs_mitra_ignores_group_filter(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oak_ccv_fixture_'.uniqid('', true).'.json';
        file_put_contents($path, json_encode($this->fixture(), JSON_THROW_ON_ERROR));

        try {
            $all = (new OakCcvDashboardPayloadService($path))->build([]);
            $bcOnly = (new OakCcvDashboardPayloadService($path))->build(['group' => 'bc']);
        } finally {
            @unlink($path);
        }

        $this->assertCount(2, $all['daily_bc_vs_mitra']);
        $this->assertSame(4, $all['daily_bc_vs_mitra'][0]['bc']);
        $this->assertSame(6, $all['daily_bc_vs_mitra'][0]['mitra']);
        $this->assertSame($all['daily_bc_vs_mitra'], $bcOnly['daily_bc_vs_mitra']);
    }

    public function test_dashboard_view_renders_oak_heading(): void
    {
        $html = view('oak-ccv.dashboard', [
            'dash' => (new OakCcvDashboardPayloadService())->build([]),
            'navActive' => 'overview',
        ])->render();

        $this->assertStringContainsString('OAK CCV Evaluation', $html);
        $this->assertStringContainsString('OBSERVASI AREA KRITIS', $html);
        $this->assertStringContainsString('Trend Observasi OAK per Minggu', $html);
        $this->assertStringContainsString('Stop / Gap CCV', $html);
        $this->assertStringContainsString('oak-bc-mitra-daily-modal', $html);
        $this->assertStringContainsString('Perbandingan BC vs mitra kerja per hari', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        return [
            'schema_version' => '1.0',
            'jenis_data' => 'OBSERVASI AREA KRITIS',
            'source_file' => 'fixture.xlsx',
            'generated_at_utc' => '2026-08-31T00:00:00Z',
            'meta' => [
                'pelaksanaan_rows' => 30,
                'pelaksanaan_tasks' => 30,
                'date_min' => '2026-07-27',
                'date_max' => '2026-08-16',
                'days' => 21,
                'stop_rows' => 2,
                'stop_tasks' => 2,
                'stop_matched_oak_tasks' => 1,
                'stop_date_min' => '2026-03-04',
                'stop_date_max' => '2026-08-30',
                'geotagging' => ['Geotagging' => 20, 'Non Geotagging' => 10],
                'entity_order' => OakCcvCompanyClassifier::ENTITY_ORDER,
                'bc_entities' => OakCcvCompanyClassifier::BC_ENTITIES,
            ],
            'weeks' => [
                ['week' => '2026-W31', 'label' => 'W31', 'rows' => 10],
                ['week' => '2026-W32', 'label' => 'W32', 'rows' => 20],
            ],
            'sites' => ['GMO', 'LMO'],
            'oak_cube' => [
                ['week' => '2026-W31', 'site' => 'GMO', 'entity' => 'BC', 'group' => 'BC', 'aktivitas' => 'Dumping', 'rows' => 10, 'tasks' => 10],
                ['week' => '2026-W32', 'site' => 'LMO', 'entity' => 'Mitra', 'group' => 'Mitra', 'aktivitas' => 'Loading', 'rows' => 20, 'tasks' => 20],
            ],
            'daily_cube' => [
                ['date' => '2026-07-27', 'week' => '2026-W31', 'site' => 'GMO', 'entity' => 'BC', 'group' => 'BC', 'rows' => 4],
                ['date' => '2026-07-27', 'week' => '2026-W31', 'site' => 'GMO', 'entity' => 'Mitra', 'group' => 'Mitra', 'rows' => 6],
                ['date' => '2026-08-03', 'week' => '2026-W32', 'site' => 'LMO', 'entity' => 'Mitra', 'group' => 'Mitra', 'rows' => 20],
            ],
            'tools' => [
                ['site' => 'GMO', 'entity' => 'BC', 'tool' => 'Pengawasan Langsung', 'rows' => 10],
                ['site' => 'LMO', 'entity' => 'Mitra', 'tool' => 'CCTV', 'rows' => 20],
            ],
            'layers' => [
                ['site' => 'GMO', 'entity' => 'BC', 'layer' => 'Layer 1', 'rows' => 10],
            ],
            'top_mitra' => [['company' => 'PT Pamapersada Nusantara', 'rows' => 20]],
            'mitra_by_site' => [['site' => 'LMO', 'company' => 'PT Pamapersada Nusantara', 'rows' => 20]],
            'stop_rows' => [
                [
                    'task' => 1,
                    'tanggal' => '2026-07-28 10:00',
                    'week' => '2026-W31',
                    'aktivitas' => 'Dumping',
                    'sub_aktivitas' => 'CCV Dumping',
                    'object' => 'CCV Dumping',
                    'detil_object' => 'Bendera limiter',
                    'jawaban' => 'Tidak Sesuai',
                    'matched_oak' => true,
                    'aktivitas_in_oak' => true,
                    'oak_site' => 'GMO',
                    'oak_perusahaan' => 'PT Berau Coal',
                    'oak_entity' => 'BC',
                ],
                [
                    'task' => 2,
                    'tanggal' => '2026-03-04 10:00',
                    'week' => '2026-W10',
                    'aktivitas' => 'Dumping',
                    'sub_aktivitas' => 'CCV Dumping',
                    'object' => 'CCV Dumping',
                    'detil_object' => 'Area tidak rata',
                    'jawaban' => 'Tidak Sesuai',
                    'matched_oak' => false,
                    'aktivitas_in_oak' => true,
                    'oak_site' => null,
                    'oak_perusahaan' => null,
                    'oak_entity' => null,
                ],
            ],
            'stop_weeks' => [
                ['week' => '2026-W10', 'label' => 'W10', 'rows' => 1],
                ['week' => '2026-W31', 'label' => 'W31', 'rows' => 1],
            ],
        ];
    }
}
