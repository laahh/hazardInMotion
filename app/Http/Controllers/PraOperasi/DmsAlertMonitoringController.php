<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\DmsMonitoring\DmsMonitoringOverallModalRequest;
use App\Services\Dms\DmsDashboardOverviewService;
use App\Services\DmsMonitoring\DmsAlertMonitoringPageService;
use App\Services\DmsMonitoring\DmsAlertMonitoringService;
use App\Services\DmsMonitoring\DmsMonitoringControlRoomPerformanceService;
use App\Services\DmsMonitoring\DmsMonitoringKpiDetailService;
use App\Services\DmsMonitoring\DmsMonitoringOverallModalService;
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
        private readonly DmsAlertMonitoringPageService $page,
        private readonly DmsAlertMonitoringService $service,
        private readonly DmsDashboardOverviewService $overview,
        private readonly DmsMonitoringKpiDetailService $kpiDetail,
        private readonly DmsMonitoringOverallModalService $overallModal,
        private readonly DmsMonitoringControlRoomPerformanceService $controlRoom,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->page->filtersFromRequest($request);

        return view('pra-operasi.dms-alert-monitoring', $this->page->cachedPayload($filters));
    }

    public function widgetQuadrant(Request $request): View
    {
        $filters = $this->page->filtersFromRequest($request);
        $quadrantFilters = [
            'start' => $filters['start'],
            'end' => $filters['end'],
            'site' => $filters['site'],
            'perusahaan' => $filters['perusahaan'],
        ];

        return view('pra-operasi.partials._dms-quadrant-widget', [
            'statistic' => $this->kpiDetail->siteQuadrantMatrix($quadrantFilters),
            'dateLabel' => $this->dateRangeLabel($filters['start'], $filters['end']),
            'quadrantOrder' => ['q2', 'q1', 'q4', 'q3'],
        ]);
    }

    public function widgetControlRoom(Request $request): View
    {
        $filters = $this->page->filtersFromRequest($request);
        $controlRoom = $this->controlRoom->matrix([
            'start' => $filters['start'],
            'end' => $filters['end'],
            'site' => $filters['site'],
            'perusahaan' => $filters['perusahaan'],
        ]);

        return view('pra-operasi.partials._dms-control-room-widget', [
            'controlRoom' => $controlRoom,
            'controlRoomColumns' => $controlRoom['columns'] ?? [],
            'controlRoomRows' => $controlRoom['rows'] ?? [],
            'dateLabel' => $this->dateRangeLabel($filters['start'], $filters['end']),
        ]);
    }

    public function widgetGrowth(Request $request): JsonResponse
    {
        $filters = $this->page->filtersFromRequest($request);
        $growth = $this->overview->growthWidget(
            $filters['end'],
            $filters['site'] !== '' ? $filters['site'] : null,
            $filters['perusahaan'] !== '' ? $filters['perusahaan'] : null,
        );

        return response()->json(['ok' => true, 'growth' => $growth]);
    }

    public function overallModal(DmsMonitoringOverallModalRequest $request): JsonResponse
    {
        $payload = $this->overallModal->payload(
            $request->filters(),
            $request->page(),
        );

        $status = ($payload['ok'] ?? false) ? 200 : 422;

        return response()->json($payload, $status);
    }

    /**
     * @deprecated Drill-down lama — diganti overallModal
     */
    public function kpiDetail(DmsMonitoringOverallModalRequest $request): JsonResponse
    {
        return $this->overallModal($request);
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

    private function dateRangeLabel(string $start, string $end): string
    {
        try {
            $tz = (string) config('app.timezone');
            $startLabel = \Illuminate\Support\Carbon::parse($start, $tz)->translatedFormat('d M Y');
            $endLabel = \Illuminate\Support\Carbon::parse($end, $tz)->translatedFormat('d M Y');

            return $start === $end ? $startLabel : "{$startLabel} - {$endLabel}";
        } catch (\Throwable) {
            return "{$start} - {$end}";
        }
    }
}
