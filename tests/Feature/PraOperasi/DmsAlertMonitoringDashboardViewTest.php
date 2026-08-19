<?php

declare(strict_types=1);

namespace Tests\Feature\PraOperasi;

use Tests\TestCase;

class DmsAlertMonitoringDashboardViewTest extends TestCase
{
    public function test_renders_crm_layout_with_operational_kpi_values(): void
    {
        $html = view('pra-operasi.dms-alert-monitoring', $this->payload())->render();

        $this->assertStringContainsString('Revenue Growth', $html);
        $this->assertStringContainsString('Earning Statistic', $html);
        $this->assertStringContainsString('Campaigns', $html);
        $this->assertStringContainsString('Customer Overview', $html);
        $this->assertStringContainsString('Client Payment Status', $html);
        $this->assertStringContainsString('Countries Status', $html);
        $this->assertStringContainsString('Top Performer', $html);
        $this->assertStringContainsString('All Item', $html);
        $this->assertStringContainsString('Last Transaction', $html);
        $this->assertStringContainsString('world-map', $html);

        $this->assertStringContainsString('Total Orang Checkin', $html);
        $this->assertStringContainsString('Total Alert', $html);
        $this->assertStringContainsString('Rasio Alert / Orang', $html);
        $this->assertStringContainsString('Unit Beroperasi', $html);
        $this->assertStringContainsString('Rasio Alert / Unit', $html);
        $this->assertStringContainsString('dmsKpiDetailModal', $html);
        $this->assertStringContainsString('data-kpi-metric="operator_checkin"', $html);
        $this->assertStringContainsString('data-kpi-metric="total_alert"', $html);
        $this->assertStringContainsString('dms-kpi-detail-breadcrumb', $html);
        $this->assertStringContainsString('Checkin RFID', $html);
        $this->assertStringContainsString('Site', $html);
        $this->assertStringContainsString('Perusahaan', $html);
        $this->assertStringContainsString('Semua Site', $html);
        $this->assertStringContainsString('Semua Perusahaan', $html);
        $this->assertStringContainsString('Binungan', $html);
        $this->assertStringContainsString('PT Example', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $delta = ['text' => '+0', 'class' => 'bg-success-focus text-success-main'];

        return [
            'up' => true,
            'filters' => ['start' => '2026-08-13', 'end' => '2026-08-19', 'site' => 'Binungan', 'perusahaan' => 'PT Example'],
            'filterOptions' => ['sites' => ['Binungan', 'Lati'], 'companies' => ['PT Example', 'PT Lain']],
            'dateLabel' => '13 Agu 2026 - 19 Agu 2026',
            'kpiDeltaLabel' => 'this week',
            'kpis' => [
                ['metric' => 'operator_checkin', 'label' => 'Total Orang Checkin', 'value' => '80', 'icon' => 'mingcute:user-follow-fill', 'bg' => 'bg-primary-600', 'gradient' => 'bg-gradient-end-1', 'chart' => 'new-user-chart', 'color' => '#487fff', 'sparkline' => [1, 2, 3], 'delta' => $delta],
                ['metric' => 'total_alert', 'label' => 'Total Alert', 'value' => '120', 'icon' => 'solar:danger-triangle-bold', 'bg' => 'bg-success-main', 'gradient' => 'bg-gradient-end-2', 'chart' => 'active-user-chart', 'color' => '#45b369', 'sparkline' => [1, 2, 3], 'delta' => $delta],
                ['label' => 'Rasio Alert / Orang', 'value' => '1.50', 'icon' => 'solar:chart-2-bold', 'bg' => 'bg-yellow', 'gradient' => 'bg-gradient-end-3', 'chart' => 'total-sales-chart', 'color' => '#f4941e', 'sparkline' => [1, 2, 3], 'delta' => $delta],
                ['label' => 'Unit Beroperasi', 'value' => '335', 'icon' => 'solar:wheel-bold', 'bg' => 'bg-purple', 'gradient' => 'bg-gradient-end-4', 'chart' => 'conversion-user-chart', 'color' => '#8252e9', 'sparkline' => [1, 2, 3], 'delta' => $delta],
                ['label' => 'Total Alert', 'value' => '120', 'icon' => 'solar:danger-triangle-bold', 'bg' => 'bg-pink', 'gradient' => 'bg-gradient-end-5', 'chart' => 'leads-chart', 'color' => '#de3ace', 'sparkline' => [1, 2, 3], 'delta' => $delta],
                ['label' => 'Rasio Alert / Unit', 'value' => '0.36', 'icon' => 'solar:bus-bold', 'bg' => 'bg-cyan', 'gradient' => 'bg-gradient-end-6', 'chart' => 'total-profit-chart', 'color' => '#00b8f2', 'sparkline' => [1, 2, 3], 'delta' => $delta],
            ],
            'growth' => ['title' => 'Revenue Growth', 'subtitle' => 'Weekly Report', 'total' => '40', 'delta' => $delta, 'labels' => ['Mon'], 'series' => [4]],
            'statistic' => ['title' => 'Earning Statistic', 'subtitle' => 'Yearly', 'total' => '40', 'confirmed' => '10', 'dismissed' => '8', 'labels' => ['Jan'], 'series' => [10]],
            'categories' => [],
            'campaigns' => [
                ['name' => 'Checkin RFID', 'total' => 80, 'pct' => 100, 'icon' => 'majesticons:mail', 'barClass' => 'bg-orange', 'textClass' => 'text-orange'],
            ],
            'overview' => ['confirmed' => 10, 'dismissed' => 8, 'pending' => 5, 'rate' => 50.0],
            'weeklyStatus' => ['confirmed' => [1], 'pending' => [2], 'dismissed' => [3], 'labels' => ['Mon'], 'totals' => ['confirmed' => 1, 'pending' => 2, 'dismissed' => 3]],
            'sites' => [['site' => 'Binungan', 'total' => 10, 'confirmed' => 4, 'pct' => 80, 'initials' => 'BI', 'barClass' => 'bg-primary-600']],
            'topOperators' => [['nama' => 'Budi', 'kode_sid' => 'K49RF', 'total' => 5, 'confirmed' => 2, 'initials' => 'BS', 'color' => '#487fff']],
            'recentAll' => [['id_alert' => 'A1', 'nama_pelanggaran' => 'DT-01', 'nama' => 'Budi', 'waktu' => '19 Agu', 'status_class' => 'bg-success-focus text-success-main', 'status_label' => 'OK', 'site' => 'Binungan']],
            'recentConfirmed' => [],
            'recentReviews' => [['id_alert' => 'A1', 'waktu' => '19 Agu', 'status_class' => 'bg-success-focus text-success-main', 'status_label' => 'OK', 'site' => 'Binungan']],
        ];
    }
}
