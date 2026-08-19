<?php

declare(strict_types=1);

namespace Tests\Feature\PraOperasi;

use App\Services\DmsMonitoring\SlovinSamplingCalculator;
use Tests\TestCase;

class DmsAlertMonitoringDashboardViewTest extends TestCase
{
    public function test_renders_operational_metrics_instead_of_crm_dummy_labels(): void
    {
        $html = view('pra-operasi.dms-alert-monitoring', $this->payload())->render();

        $this->assertStringContainsString('Total Alert Masuk', $html);
        $this->assertStringContainsString('Unit Beroperasi', $html);
        $this->assertStringContainsString('Rasio Alert / Unit', $html);
        $this->assertStringContainsString('Rasio Alert / Orang', $html);
        $this->assertStringContainsString('False Positif (L1 Dismiss)', $html);
        $this->assertStringContainsString('False Negatif (QA L1)', $html);
        $this->assertStringContainsString('Funnel Layer', $html);
        $this->assertStringContainsString('Checkin RFID', $html);
        $this->assertStringContainsString('Orang yang Belum Pernah Post Event', $html);
        $this->assertStringContainsString('Cakupan Sampling vs Rumus Slovin', $html);
        $this->assertStringContainsString('Kesesuaian Post Event', $html);
        $this->assertStringContainsString('DT-01', $html);
        $this->assertStringNotContainsString('New Users', $html);
        $this->assertStringNotContainsString('Total Sales', $html);
        $this->assertStringNotContainsString('Revenue Growth', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'up' => true,
            'filters' => ['start' => '2026-08-13', 'end' => '2026-08-19'],
            'dateLabel' => '13 Agu 2026 - 19 Agu 2026',
            'today' => [
                'date_label' => '19 Agu 2026',
                'units_operating' => 12,
                'operators_checked_in' => 40,
                'total_alerts' => 30,
                'ratio_per_unit' => 2.5,
                'ratio_per_operator' => 0.75,
            ],
            'kpis' => [
                ['label' => 'Total Alert Masuk', 'value' => '120', 'hint' => 'periode filter', 'icon' => 'solar:danger-triangle-bold', 'bg' => 'bg-primary-600', 'gradient' => 'bg-gradient-end-1'],
                ['label' => 'Unit Beroperasi', 'value' => '12', 'hint' => 'hari ini', 'icon' => 'solar:wheel-bold', 'bg' => 'bg-success-main', 'gradient' => 'bg-gradient-end-2'],
                ['label' => 'Rasio Alert / Unit', 'value' => '2.50', 'hint' => '19 Agu 2026', 'icon' => 'solar:bus-bold', 'bg' => 'bg-yellow', 'gradient' => 'bg-gradient-end-3'],
                ['label' => 'Rasio Alert / Orang', 'value' => '0.75', 'hint' => '40 orang check-in RFID', 'icon' => 'mingcute:user-follow-fill', 'bg' => 'bg-purple', 'gradient' => 'bg-gradient-end-4'],
                ['label' => 'False Positif (L1 Dismiss)', 'value' => '18', 'hint' => 'alert DMS yang L1 anggap bukan pelanggaran', 'icon' => 'solar:close-circle-bold', 'bg' => 'bg-pink', 'gradient' => 'bg-gradient-end-5'],
                ['label' => 'False Negatif (QA L1)', 'value' => '2', 'hint' => 'rate 10%', 'icon' => 'solar:shield-warning-bold', 'bg' => 'bg-cyan', 'gradient' => 'bg-gradient-end-6'],
            ],
            'summary' => [
                'total' => 120,
                'l1_reviewed' => 100,
                'l1_confirmed' => 40,
                'l1_dismissed' => 18,
                'l1_belum' => 20,
                'l2_reviewed' => 30,
                'l2_confirmed' => 10,
                'post_event_eligible' => 8,
            ],
            'byUnit' => [['unit' => 'DT-01', 'site' => 'Binungan', 'total' => 9, 'confirmed' => 3]],
            'byOperator' => [['nama' => 'Budi', 'kode_sid' => 'K49RF', 'total' => 5, 'confirmed' => 2]],
            'quadrant' => [],
            'unitsOperating' => 4,
            'postEvent' => ['total' => 6, 'behazard' => 4, 'berecord' => 2, 'distinct_sids' => ['K49RF']],
            'turnaround' => [],
            'funnel' => [
                ['label' => 'Checkin RFID', 'count' => 80],
                ['label' => 'Punya Alert DMS', 'count' => 50],
            ],
            'neverPostEvent' => ['window_days' => 90, 'total_dengan_alert' => 50, 'total_belum_post_event' => 12, 'persentase' => 24.0],
            'slovin' => [
                'population' => 120,
                'margin_of_error' => SlovinSamplingCalculator::DEFAULT_MARGIN_OF_ERROR,
                'target_sample_size' => 92,
                'l1_reviewed' => 100,
                'l2_reviewed' => 30,
                'post_event' => 6,
            ],
            'qaSummary' => [
                'population' => 18,
                'target_sample_size' => 17,
                'total_sampled' => 5,
                'total_audited' => 5,
                'pending' => 0,
                'benar_dismiss' => 3,
                'false_negative' => 2,
                'tidak_jelas' => 0,
                'false_negative_rate' => 40.0,
                'estimated_false_negative_count' => 7,
            ],
            'qaPending' => [],
        ];
    }
}
