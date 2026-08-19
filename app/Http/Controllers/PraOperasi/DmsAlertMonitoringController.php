<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\DmsMonitoring\DmsMonitoringKpiDetailRequest;
use App\Services\Dms\DmsDashboardOverviewService;
use App\Services\DmsMonitoring\DmsAlertMonitoringService;
use App\Services\DmsMonitoring\DmsMonitoringKpiDetailService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /pra-operasi/dashboard — layout WowDash CRM, angka dari monitoring DMS.
 */
final class DmsAlertMonitoringController extends Controller
{
    public function __construct(
        private readonly DmsAlertMonitoringService $service,
        private readonly DmsDashboardOverviewService $overview,
        private readonly DmsMonitoringKpiDetailService $kpiDetail,
    ) {}

    public function index(Request $request): View
    {
        $monitoring = $this->service->dashboard($request);
        $filters = $monitoring['filters'] ?? ['start' => null, 'end' => null, 'site' => null, 'perusahaan' => null];
        $crm = $this->overview->dashboard(
            is_string($filters['start'] ?? null) ? $filters['start'] : null,
            is_string($filters['end'] ?? null) ? $filters['end'] : null,
            is_string($filters['site'] ?? null) && $filters['site'] !== '' ? $filters['site'] : null,
            is_string($filters['perusahaan'] ?? null) && $filters['perusahaan'] !== '' ? $filters['perusahaan'] : null,
        );

        $quadrantFilters = [
            'start' => (string) ($filters['start'] ?? ''),
            'end' => (string) ($filters['end'] ?? ''),
            'site' => (string) ($filters['site'] ?? ''),
            'perusahaan' => (string) ($filters['perusahaan'] ?? ''),
        ];
        if ($quadrantFilters['start'] !== '' && $quadrantFilters['end'] !== '') {
            $crm['statistic'] = $this->kpiDetail->siteQuadrantMatrix($quadrantFilters);
        }

        return view('pra-operasi.dms-alert-monitoring', $this->mergeCrmPayload($crm, $monitoring));
    }

    public function kpiDetail(DmsMonitoringKpiDetailRequest $request): JsonResponse
    {
        $payload = $this->kpiDetail->detail(
            $request->metricKey(),
            $request->filters(),
            $request->level(),
            $request->parentSite(),
            $request->parentCompany(),
            $request->page(),
        );

        $status = ($payload['ok'] ?? false) ? 200 : 422;

        return response()->json($payload, $status);
    }

    /**
     * Buat sampel QA baru untuk periode ini (ukuran sampel dari rumus Slovin).
     */
    public function generateQaSample(Request $request): RedirectResponse
    {
        $start = (string) $request->input('start', '');
        $end = (string) $request->input('end', '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1) {
            return back()->with('error', 'Periode tidak valid.');
        }

        $result = $this->service->generateQaSample($start, $end);

        return back()->with('status', "Sampel QA dibuat: {$result['generated']} baru (target {$result['target_sample_size']} dari populasi {$result['population']} alert yang di-dismiss L1).");
    }

    /**
     * Simpan verdict audit ulang satu sampel QA.
     */
    public function submitQaVerdict(Request $request): JsonResponse
    {
        $sampleId = (int) $request->input('sample_id');
        $verdict = (string) $request->input('verdict', '');
        $catatan = $request->filled('catatan') ? mb_substr((string) $request->input('catatan'), 0, 1000) : null;

        if ($sampleId <= 0 || $verdict === '') {
            return response()->json(['message' => 'Data tidak lengkap.'], 422);
        }

        $ok = $this->service->submitQaVerdict($sampleId, $verdict, $catatan, $request->user()?->id);

        if (! $ok) {
            return response()->json(['message' => 'Gagal menyimpan verdict.'], 422);
        }

        return response()->json(['message' => 'Tersimpan.']);
    }

    /**
     * Layout CRM tetap; overlay angka operasional ke slot widget.
     *
     * @param  array<string, mixed>  $crm
     * @param  array<string, mixed>  $monitoring
     * @return array<string, mixed>
     */
    private function mergeCrmPayload(array $crm, array $monitoring): array
    {
        $kpis = $crm['kpis'] ?? [];
        foreach ($monitoring['kpis'] ?? [] as $i => $kpi) {
            if (! is_array($kpi)) {
                continue;
            }
            if (! isset($kpis[$i]) || ! is_array($kpis[$i])) {
                $kpis[$i] = $kpi;
                continue;
            }
            $kpis[$i]['label'] = $kpi['label'] ?? $kpis[$i]['label'];
            $kpis[$i]['value'] = $kpi['value'] ?? $kpis[$i]['value'];
            $kpis[$i]['icon'] = $kpi['icon'] ?? $kpis[$i]['icon'];
            $kpis[$i]['bg'] = $kpi['bg'] ?? $kpis[$i]['bg'];
            $kpis[$i]['gradient'] = $kpi['gradient'] ?? $kpis[$i]['gradient'];
        }

        $campaigns = $this->mapFunnelCampaigns($monitoring['funnel'] ?? []);
        if ($campaigns === []) {
            $campaigns = $crm['categories'] ?? [];
        }

        return array_merge($crm, [
            'up' => (bool) ($crm['up'] ?? false) || (bool) ($monitoring['up'] ?? false),
            'filters' => $monitoring['filters'] ?? ['start' => '', 'end' => '', 'site' => '', 'perusahaan' => ''],
            'filterOptions' => $monitoring['filterOptions'] ?? ['sites' => [], 'companies' => []],
            'kpis' => array_values($kpis),
            'campaigns' => $campaigns,
            'kpiDeltaLabel' => 'this week',
        ]);
    }

    /**
     * @param  list<array{label?:string, count?:int}>  $funnel
     * @return list<array{name:string, total:int, pct:int, icon:string, barClass:string, textClass:string}>
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

        $max = 1;
        foreach ($funnel as $row) {
            if (! is_array($row)) {
                continue;
            }
            $max = max($max, (int) ($row['count'] ?? 0));
        }

        $out = [];
        foreach ($funnel as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $style = $styles[$i] ?? $styles[0];
            $count = (int) ($row['count'] ?? 0);
            $out[] = $style + [
                'name' => (string) ($row['label'] ?? '-'),
                'total' => $count,
                'pct' => (int) round($count / $max * 100),
            ];
        }

        return $out;
    }
}
