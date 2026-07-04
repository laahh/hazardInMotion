<?php

declare(strict_types=1);

namespace App\Http\Controllers\MonitoringSafetyEngineering\Concerns;

trait ProvidesMonitoringSafetyEngineeringLayout
{
    /**
     * @return list<array{key: string, label: string, route: string}>
     */
    protected function monitoringSafetyEngineeringNavItems(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Progress Komitmen', 'route' => 'monitoring-safety-engineering.dashboard'],
            ['key' => 'outside-commitment', 'label' => 'Luar Komitmen', 'route' => 'monitoring-safety-engineering.outside-commitment'],
            ['key' => 'pmr-evaluation', 'label' => 'Evaluasi PMR', 'route' => 'monitoring-safety-engineering.pmr-evaluation'],
            ['key' => 'company-overview', 'label' => 'Overall Perusahaan', 'route' => 'monitoring-safety-engineering.company-overview'],
            ['key' => 'effectiveness', 'label' => 'Efektivitas Rekayasa', 'route' => 'monitoring-safety-engineering.effectiveness'],
            ['key' => 'upload', 'label' => 'Upload Data', 'route' => 'monitoring-safety-engineering.upload.index'],
            ['key' => 'data-update', 'label' => 'Update Data', 'route' => 'monitoring-safety-engineering.data-update.index'],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function monitoringSafetyEngineeringViewData(string $navActive, array $extra = []): array
    {
        return array_merge([
            'navActive' => $navActive,
            'navItems' => $this->monitoringSafetyEngineeringNavItems(),
            'programLabel' => 'Monitoring Safety Engineering',
            'programCode' => 'MSE-GMO-001',
        ], $extra);
    }
}
