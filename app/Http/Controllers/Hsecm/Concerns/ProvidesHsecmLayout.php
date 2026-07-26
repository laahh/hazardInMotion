<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hsecm\Concerns;

trait ProvidesHsecmLayout
{
    /**
     * @return list<array{key: string, label: string, route: string, params?: array<string, string>}>
     */
    protected function hsecmNavItems(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Overview', 'route' => 'hsecm.dashboard'],
            ['key' => 'gap-perulangan', 'label' => 'Gap Perulangan', 'route' => 'hsecm.gap-perulangan'],
            ['key' => 'pjo-action', 'label' => 'Aksi PJO', 'route' => 'hsecm.pjo-action'],
            ['key' => 'tasklist-review', 'label' => 'Tasklist Review', 'route' => 'hsecm.tasklist.index'],
            ['key' => 'wa-notify', 'label' => 'Kirim WA & Email', 'route' => 'hsecm.wa-notify.index'],
        ];
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
