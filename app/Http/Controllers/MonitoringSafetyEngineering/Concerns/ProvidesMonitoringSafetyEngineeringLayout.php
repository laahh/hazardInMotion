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
