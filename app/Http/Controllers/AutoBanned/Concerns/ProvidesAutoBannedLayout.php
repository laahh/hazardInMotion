<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoBanned\Concerns;

trait ProvidesAutoBannedLayout
{
    /**
     * @return array<int, array{key: string, label: string, route: string}>
     */
    protected function autoBannedNavItems(): array
    {
        return [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'route' => 'auto-banned.index',
            ],
            [
                'key' => 'Monitoring Banned',
                'label' => 'Monitoring Banned',
                'route' => 'auto-banned.banned-monitoring.index',
            ],
            [
                'key' => 'unban-monitoring',
                'label' => 'Monitoring Un Banned',
                'route' => 'auto-banned.unban-monitoring.index',
            ],
            [
                'key' => 'pipeline-monitoring',
                'label' => 'Pipeline Banned → Unban',
                'route' => 'auto-banned.pipeline-monitoring.index',
            ],
            // [
            //     'key' => 'hsct-email',
            //     'label' => 'Riwayat Email HSECT',
            //     'route' => 'auto-banned.hsct-email.index',
            // ],
            [
                'key' => 'tableau-flow-history',
                'label' => 'Riwayat Tableau Flow',
                'route' => 'auto-banned.tableau-flow-history.index',
            ],
            // [
            //     'key' => 'inputasi',
            //     'label' => 'Inputasi',
            //     'route' => 'auto-banned.inputasi.index',
            // ],
            [
                'key' => 'sod-verification',
                'label' => 'Verifikasi SOD',
                'route' => 'auto-banned.sod-verification.index',
            ],
            // [
            //     'key' => 'master-data',
            //     'label' => 'Master Data',
            //     'route' => 'auto-banned.master-data.index',
            // ],
            [
                'key' => 'master-sod',
                'label' => 'Master SOD',
                'route' => 'auto-banned.master-sod.index',
            ],
        ];
    }
}
