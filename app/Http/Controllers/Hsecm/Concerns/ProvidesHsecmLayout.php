<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hsecm\Concerns;

use App\Services\Hsecm\HsecmDashboardService;

trait ProvidesHsecmLayout
{
    /**
     * @return list<array{key: string, label: string, route: string, params?: array<string, string>}>
     */
    protected function hsecmNavItems(): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Overview', 'route' => 'hsecm.dashboard'],
            ['key' => 'pjo-action', 'label' => 'Aksi PJO', 'route' => 'hsecm.pjo-action'],
            ['key' => 'wa-notify', 'label' => 'Kirim WA & Email', 'route' => 'hsecm.wa-notify.index'],
        ];

        foreach (HsecmDashboardService::DATASETS as $key => $meta) {
            $items[] = [
                'key' => $key,
                'label' => $meta['label'],
                'route' => 'hsecm.datasets.show',
                'params' => ['dataset' => $key],
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function hsecmViewData(string $navActive, array $extra = []): array
    {
        return array_merge([
            'navActive' => $navActive,
            'navItems' => $this->hsecmNavItems(),
            'programLabel' => 'Daily Monitoring Dashboard',
            'programCode' => 'Daily',
        ], $extra);
    }
}
