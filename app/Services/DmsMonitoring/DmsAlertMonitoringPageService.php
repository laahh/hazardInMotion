<?php

declare(strict_types=1);

namespace App\Services\DmsMonitoring;

use App\Services\Dms\DmsDashboardOverviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Orkestrator payload halaman /pra-operasi/dashboard — satu cache, tanpa query duplikat.
 */
final class DmsAlertMonitoringPageService
{
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly DmsDashboardOverviewService $overview,
        private readonly DmsAlertMonitoringService $monitoring,
        private readonly DmsAlertMonitoringDataReader $reader,
    ) {}

    /**
     * @return array{start:string, end:string, site:string, perusahaan:string}
     */
    public function filtersFromRequest(Request $request): array
    {
        return $this->monitoring->filtersFromRequest($request);
    }

    /**
     * Payload utama dashboard — widget berat (kuadran, control room, growth) di-load lazy.
     *
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    public function cachedPayload(array $filters): array
    {
        $cacheKey = 'dms_dashboard_page:v15:'.md5(json_encode($filters));
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['up'] ?? false) === true) {
            return $cached;
        }

        $payload = $this->buildPayload($filters);
        if (($payload['up'] ?? false) === true) {
            Cache::put($cacheKey, $payload, self::CACHE_TTL);
        }

        return $payload;
    }

    /**
     * @param  array{start:string, end:string, site:string, perusahaan:string}  $filters
     * @return array<string, mixed>
     */
    private function buildPayload(array $filters): array
    {
        $site = $filters['site'] !== '' ? $filters['site'] : null;
        $perusahaan = $filters['perusahaan'] !== '' ? $filters['perusahaan'] : null;

        $crm = $this->overview->dashboard(
            $filters['start'],
            $filters['end'],
            $site,
            $perusahaan,
            deferGrowth: true,
        );

        $tz = (string) config('app.timezone');
        $start = Carbon::parse($filters['start'], $tz)->startOfDay()->format('Y-m-d H:i:s');
        $end = Carbon::parse($filters['end'], $tz)->startOfDay()->addDay()->format('Y-m-d H:i:s');

        $this->reader->applyScope($site, $perusahaan);

        $summary = is_array($crm['summary'] ?? null) ? $crm['summary'] : [];
        $funnel = [];
        if (($crm['up'] ?? false) === true) {
            $funnel = [
                ['label' => 'Checkin RFID', 'count' => (int) ($crm['operatorsCheckedIn'] ?? 0)],
                ['label' => 'Punya Alert DMS', 'count' => $this->reader->countDistinctAlertSids($start, $end)],
                ['label' => 'Direview L1', 'count' => (int) ($summary['l1_reviewed'] ?? 0)],
                ['label' => 'Direview L2', 'count' => (int) ($summary['l2_reviewed'] ?? 0)],
                ['label' => 'Post Event', 'count' => (int) ($summary['post_event_eligible'] ?? 0)],
            ];
        }

        return array_merge($crm, [
            'up' => (bool) ($crm['up'] ?? false),
            'filters' => $filters,
            'filterOptions' => ($crm['up'] ?? false) === true
                ? $this->reader->filterOptions($start, $end)
                : ['sites' => [], 'companies' => []],
            'campaigns' => $this->mapFunnelCampaigns($funnel),
            'kpiDeltaLabel' => 'this week',
            'statistic' => $this->emptyStatisticPlaceholder(),
            'controlRoom' => $this->emptyControlRoomPlaceholder(),
            'lazyWidgets' => true,
        ]);
    }

    /**
     * @param  list<array{label?:string, count?:int}>  $funnel
     * @return list<array{name:string, total:int, pct:int, icon:string, barClass:string, textClass:string, conversion_label:string}>
     */
    private function mapFunnelCampaigns(array $funnel): array
    {
        $styles = [
            ['icon' => 'majesticons:mail', 'textClass' => 'text-orange', 'barClass' => 'bg-orange'],
            ['icon' => 'eva:globe-2-fill', 'textClass' => 'text-success-main', 'barClass' => 'bg-success-main'],
            ['icon' => 'fa6-brands:square-facebook', 'textClass' => 'text-info-main', 'barClass' => 'bg-info-main'],
            ['icon' => 'fluent:location-off-20-filled', 'textClass' => 'text-indigo', 'barClass' => 'bg-indigo'],
            ['icon' => 'solar:shield-check-bold', 'textClass' => 'text-primary-600', 'barClass' => 'bg-primary-600'],
        ];

        $out = [];
        $previous = null;
        foreach ($funnel as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $style = $styles[$i] ?? $styles[0];
            $count = (int) ($row['count'] ?? 0);
            $pct = $previous === null || $previous <= 0
                ? 100
                : (int) round(($count / $previous) * 100);
            $out[] = $style + [
                'name' => (string) ($row['label'] ?? '-'),
                'total' => $count,
                'pct' => max(0, min(100, $pct)),
                'conversion_label' => $previous === null ? 'baseline' : 'vs tahap sebelumnya',
            ];
            $previous = max(0, $count);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStatisticPlaceholder(): array
    {
        return [
            'title' => 'Matriks Site',
            'subtitle' => 'Exposure vs Alert Intensity per Site',
            'mode' => 'quadrant',
            'total' => '…',
            'confirmed' => '…',
            'dismissed' => '…',
            'x_median' => 0,
            'y_median' => 0,
            'quadrants' => [
                'q1' => ['label' => 'Q1 – Critical Exposure', 'description' => '', 'bg' => '#fef2f2', 'border' => '#ef4444', 'text' => '#991b1b', 'icon' => 'solar:danger-triangle-bold', 'sites' => []],
                'q2' => ['label' => 'Q2 – Localized High Risk', 'description' => '', 'bg' => '#fff7ed', 'border' => '#f97316', 'text' => '#9a3412', 'icon' => 'solar:map-point-bold', 'sites' => []],
                'q3' => ['label' => 'Q3 – High Exposure, Controlled', 'description' => '', 'bg' => '#eff6ff', 'border' => '#3b82f6', 'text' => '#1e40af', 'icon' => 'solar:shield-check-bold', 'sites' => []],
                'q4' => ['label' => 'Q4 – Low Exposure, Controlled', 'description' => '', 'bg' => '#f0fdf4', 'border' => '#22c55e', 'text' => '#166534', 'icon' => 'solar:check-circle-bold', 'sites' => []],
            ],
            'loading' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyControlRoomPlaceholder(): array
    {
        return [
            'title' => 'Performa Control Room',
            'subtitle' => 'Intervensi alert & lead time real time per perusahaan / site',
            'metrics' => [],
            'companies' => [],
            'columns' => [],
            'rows' => [],
            'loading' => true,
        ];
    }
}
