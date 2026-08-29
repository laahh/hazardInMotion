<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PeerPressure\GeneratePeerPressureDashboardHighlightIssueRecommendationAction;
use App\Actions\PeerPressure\GetPeerPressureDeviationModalBreakdownAction;
use App\Actions\PeerPressure\GetPeerPressureDashboardGapMatrixAction;
use App\Actions\PeerPressure\GetPeerPressurePerusahaanPelaksanaanHeatmapAction;
use App\Actions\PeerPressure\GetPeerPressureDashboardEvaluationSummaryAction;
use App\Actions\PeerPressure\GetPeerPressureDashboardInsightCardsAction;
use App\Actions\PeerPressure\GetPeerPressureDashboardKpiStatsAction;
use App\Actions\PeerPressure\GetPeerPressureDashboardComplianceBreakdownAction;
use App\Actions\PeerPressure\GetPeerPressureDashboardWeeklyTrendAction;
use App\Actions\PeerPressure\GetPeerPressureKejadianDetailForDashboardAction;
use App\Actions\PeerPressure\GetPeerPressurePelanggarProfilingDetailAction;
use App\Actions\PeerPressure\GetPeerPressureTbcHighRiskCardsAction;
use App\Actions\PeerPressure\ListPeerPressureDashboardKejadianAction;
use App\Actions\PeerPressure\ListPeerPressureDeviationModalDetailAction;
use App\Actions\PeerPressure\ListPeerPressurePelaksanaanBelumKejadianAction;
use App\Actions\PeerPressure\ListPeerPressurePelaksanaanSelesaiKejadianAction;
use App\Models\PeerPressureKejadianEdukasi;
use App\Services\PeerPressure\PeerPressureKaryawanNitipService;
use App\Services\PeerPressure\PeerPressurePelaksanaanBaselineService;
use App\Services\PeerPressure\PeerPressureResourcesDataAiSummaryService;
use App\Services\PeerPressure\SitePerformanceBriefAnalysisService;
use App\Services\ClickHouseService;
use App\Services\PeerPressure\PeerPressureKejadianEdukasiExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PeerPressureEdukasiController extends Controller
{
    public function index(Request $request): View
    {
        $query = PeerPressureKejadianEdukasi::query()->with('peserta')->orderByDesc('tanggal_temuan')->orderByDesc('id');

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('perusahaan', 'like', '%' . $q . '%')
                    ->orWhere('site', 'like', '%' . $q . '%')
                    ->orWhere('lokasi_temuan', 'like', '%' . $q . '%')
                    ->orWhere('kronologi_temuan', 'like', '%' . $q . '%')
                    ->orWhere('pemimpin_edukasi', 'like', '%' . $q . '%')
                    ->orWhereHas('peserta', function ($p) use ($q) {
                        $p->where('sid', 'like', '%' . $q . '%')
                            ->orWhere('nama', 'like', '%' . $q . '%');
                    });
            });
        }

        $kejadian = $query->paginate(15)->withQueryString();

        return view('peer-pressure-edukasi.index', [
            'kejadian' => $kejadian,
            'q' => $q,
        ]);
    }

    /**
     * Dashboard evaluasi Peer Pressure (data kejadian + peserta dari database).
     */
    public function dashboard(
        Request $request,
        ListPeerPressureDashboardKejadianAction $listDashboardKejadian,
        PeerPressureKaryawanNitipService $karyawanNitip,
        GetPeerPressureDashboardKpiStatsAction $dashboardKpiStats,
        GetPeerPressureDashboardWeeklyTrendAction $weeklyTrend,
        GetPeerPressureDashboardEvaluationSummaryAction $evaluationSummary,
        GetPeerPressureDashboardInsightCardsAction $insightCards,
        GetPeerPressureDeviationModalBreakdownAction $deviationModalBreakdown,
        PeerPressureResourcesDataAiSummaryService $resourcesDataAiSummary,
        SitePerformanceBriefAnalysisService $sitePerformanceBriefAnalysis
    ): View {
        $dashboardView = match (true) {
            $request->routeIs('peer-pressure-edukasi.dashboard') => 'peer-pressure-edukasi.dashboard',
            $request->routeIs('peer-pressure-edukasi.dashboard-performance') => 'peer-pressure-edukasi.DashPerformance',
            $request->routeIs('peer-pressure-edukasi.tematic') => 'peer-pressure-edukasi.tematic',
            default => 'peer-pressure-edukasi.dashboard-peer',
        };
        $needsSiteJsonMetrics = in_array($dashboardView, [
            'peer-pressure-edukasi.dashboard',
            'peer-pressure-edukasi.DashPerformance',
        ], true);

        $q = trim((string) $request->get('q', ''));
        $chartPeriodMonth = $request->filled('year') && $request->filled('month');

        if ($chartPeriodMonth) {
            $chartYear = (int) $request->get('year');
            $chartMonth = (int) $request->get('month');
            $chartYear = max(GetPeerPressureDashboardWeeklyTrendAction::MIN_YEAR, min(GetPeerPressureDashboardWeeklyTrendAction::MAX_YEAR, $chartYear));
            $chartMonth = max(1, min(12, $chartMonth));
            $kpiData = $dashboardKpiStats($chartYear, $chartMonth);
            $weeklyTrendData = $weeklyTrend($chartYear, $chartMonth);
        } else {
            $chartYear = null;
            $chartMonth = null;
            $kpiData = $dashboardKpiStats();
            $weeklyTrendData = $weeklyTrend();
        }

        $kejadian = $listDashboardKejadian($q !== '' ? $q : null);

        $peerSids = [];
        foreach ($kejadian as $k) {
            foreach ($k->peserta->where('peran', 'peer') as $p) {
                $peerSids[] = $p->sid;
            }
        }
        $peerFotoUrls = $karyawanNitip->fotoUrlsByKodeSids($peerSids);

        $siteJson = $needsSiteJsonMetrics
            ? $this->dashboardSiteJsonMetrics($request)
            : $this->emptyDashboardSiteJsonMetrics();

        $deviationModalBreakdownData = $deviationModalBreakdown(
            $chartPeriodMonth ? $chartYear : null,
            $chartPeriodMonth ? $chartMonth : null
        );

        return view($dashboardView, [
            'navActive' => 'overview',
            'kejadian' => $kejadian,
            'peerFotoUrls' => $peerFotoUrls,
            'q' => $q,
            'kpi' => $kpiData,
            'peerResourcesAiSummary' => $needsSiteJsonMetrics
                ? $resourcesDataAiSummary->generate($kpiData)
                : ['text' => '', 'source' => 'heuristic'],
            'sitePerformanceBrief' => $needsSiteJsonMetrics
                ? $sitePerformanceBriefAnalysis->analyze()
                : [
                    'ok' => false,
                    'message' => '',
                    'last_year' => null,
                    'last_week' => null,
                    'gaps_last_week' => [],
                    'attention_sites' => [],
                    'repetitive' => [],
                    'overall_by_site_mitra' => [],
                    'narrative' => '',
                ],
            'weeklyTrend' => $weeklyTrendData,
            'chartYear' => $chartYear,
            'chartMonth' => $chartMonth,
            'chartPeriodMonth' => $chartPeriodMonth,
            'evaluationSummary' => $evaluationSummary(
                $chartPeriodMonth ? $chartYear : null,
                $chartPeriodMonth ? $chartMonth : null
            ),
            'insightCards' => $insightCards(
                $chartPeriodMonth ? $chartYear : null,
                $chartPeriodMonth ? $chartMonth : null
            ),
            'deviationModalBreakdown' => $deviationModalBreakdownData,
            ...$siteJson,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboardSiteJsonMetrics(): array
    {
        return [
            'peerHazardReportingBySite' => null,
            'hazardSite' => '__all',
            'peerHrEvalFromJson' => null,
            'peerTbcHighBySite' => null,
            'peerTbcEvalFromJson' => null,
            'peerTbcBlindspotBySite' => null,
            'peerTbcBlindEvalFromJson' => null,
            'peerGoldenRulesBySite' => null,
            'peerGoldenRulesEvalFromJson' => null,
            'peerAreaNonKritisBySite' => null,
            'peerAreaNonKritisEvalFromJson' => null,
            'peerAreaKritisBySite' => null,
            'peerAreaKritisEvalFromJson' => null,
        ];
    }

    /**
     * Metrik JSON per site hanya dipakai dashboard / DashPerformance, bukan dashboard-peer.
     *
     * @return array<string, mixed>
     */
    private function dashboardSiteJsonMetrics(Request $request): array
    {
        $peerHazardReportingBySite = $this->loadPeerHazardReportingBySite();
        $peerTbcHighBySite = $this->loadPeerTbcHighBySite();
        $peerTbcBlindspotBySite = $this->loadPeerTbcBlindspotBySite();
        $hazardSiteAllowed = ['__all'];
        if (is_array($peerHazardReportingBySite) && ! empty($peerHazardReportingBySite['sites'])) {
            foreach ($peerHazardReportingBySite['sites'] as $siteLabel) {
                $hazardSiteAllowed[] = (string) $siteLabel;
            }
        }
        if (is_array($peerTbcHighBySite) && ! empty($peerTbcHighBySite['sites'])) {
            foreach ($peerTbcHighBySite['sites'] as $siteLabel) {
                $s = (string) $siteLabel;
                if (! in_array($s, $hazardSiteAllowed, true)) {
                    $hazardSiteAllowed[] = $s;
                }
            }
        }
        if (is_array($peerTbcBlindspotBySite) && ! empty($peerTbcBlindspotBySite['sites'])) {
            foreach ($peerTbcBlindspotBySite['sites'] as $siteLabel) {
                $s = (string) $siteLabel;
                if (! in_array($s, $hazardSiteAllowed, true)) {
                    $hazardSiteAllowed[] = $s;
                }
            }
        }
        $peerGoldenRulesBySite = $this->loadPeerGoldenRulesBySite();
        if (is_array($peerGoldenRulesBySite) && ! empty($peerGoldenRulesBySite['sites'])) {
            foreach ($peerGoldenRulesBySite['sites'] as $siteLabel) {
                $s = (string) $siteLabel;
                if (! in_array($s, $hazardSiteAllowed, true)) {
                    $hazardSiteAllowed[] = $s;
                }
            }
        }
        $peerAreaNonKritisBySite = $this->loadPeerAreaNonKritisBySite();
        if (is_array($peerAreaNonKritisBySite) && ! empty($peerAreaNonKritisBySite['sites'])) {
            foreach ($peerAreaNonKritisBySite['sites'] as $siteLabel) {
                $s = (string) $siteLabel;
                if (! in_array($s, $hazardSiteAllowed, true)) {
                    $hazardSiteAllowed[] = $s;
                }
            }
        }
        $peerAreaKritisBySite = $this->loadPeerAreaKritisBySite();
        if (is_array($peerAreaKritisBySite) && ! empty($peerAreaKritisBySite['sites'])) {
            foreach ($peerAreaKritisBySite['sites'] as $siteLabel) {
                $s = (string) $siteLabel;
                if (! in_array($s, $hazardSiteAllowed, true)) {
                    $hazardSiteAllowed[] = $s;
                }
            }
        }
        $hazardSiteReq = (string) $request->query('hazard_site', '__all');
        $hazardSite = in_array($hazardSiteReq, $hazardSiteAllowed, true) ? $hazardSiteReq : '__all';

        return [
            'peerHazardReportingBySite' => $peerHazardReportingBySite,
            'hazardSite' => $hazardSite,
            'peerHrEvalFromJson' => is_array($peerHazardReportingBySite)
                ? $this->peerMetricEvalFromJson($peerHazardReportingBySite, $hazardSite, '#d97706', 0)
                : null,
            'peerTbcHighBySite' => $peerTbcHighBySite,
            'peerTbcEvalFromJson' => is_array($peerTbcHighBySite)
                ? $this->peerMetricEvalFromJson($peerTbcHighBySite, $hazardSite, '#3952bc', 0)
                : null,
            'peerTbcBlindspotBySite' => $peerTbcBlindspotBySite,
            'peerTbcBlindEvalFromJson' => is_array($peerTbcBlindspotBySite)
                ? $this->peerMetricEvalFromJson($peerTbcBlindspotBySite, $hazardSite, '#16a34a', 0)
                : null,
            'peerGoldenRulesBySite' => $peerGoldenRulesBySite,
            'peerGoldenRulesEvalFromJson' => is_array($peerGoldenRulesBySite)
                ? $this->peerMetricEvalFromJson($peerGoldenRulesBySite, $hazardSite, '#c8102e', 0)
                : null,
            'peerAreaNonKritisBySite' => $peerAreaNonKritisBySite,
            'peerAreaNonKritisEvalFromJson' => is_array($peerAreaNonKritisBySite)
                ? $this->peerMetricEvalFromJson($peerAreaNonKritisBySite, $hazardSite, '#ea580c', 0)
                : null,
            'peerAreaKritisBySite' => $peerAreaKritisBySite,
            'peerAreaKritisEvalFromJson' => is_array($peerAreaKritisBySite)
                ? $this->peerMetricEvalFromJson($peerAreaKritisBySite, $hazardSite, '#dc2626', 0)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPeerAreaKritisBySite(): ?array
    {
        $path = resource_path('data/peer_pressure_area_kritis_by_site.json');
        if (! is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPeerAreaNonKritisBySite(): ?array
    {
        $path = resource_path('data/peer_pressure_area_non_kritis_by_site.json');
        if (! is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPeerGoldenRulesBySite(): ?array
    {
        $path = resource_path('data/peer_pressure_golden_rules_by_site.json');
        if (! is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPeerTbcBlindspotBySite(): ?array
    {
        $path = resource_path('data/peer_pressure_tbc_blindspot_by_site.json');
        if (! is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPeerTbcHighBySite(): ?array
    {
        $path = resource_path('data/peer_pressure_tbc_high_by_site.json');
        if (! is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPeerHazardReportingBySite(): ?array
    {
        $path = resource_path('data/peer_pressure_hazard_reporting_by_site.json');
        if (! is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Ambil nilai minggu dari baris bySite; key minggu di JSON bisa beda huruf besar/kecil (W14 vs w14).
     *
     * @param  array<string, mixed>  $row
     */
    private function peerMetricWeekValueFromRow(array $row, string $wk): float
    {
        if (array_key_exists($wk, $row)) {
            return (float) $row[$wk];
        }
        foreach ($row as $k => $v) {
            if (is_string($k) && strcasecmp($k, $wk) === 0) {
                return (float) $v;
            }
        }

        return 0.0;
    }

    /**
     * Agregasi mini-chart metrik per site dari JSON (Hazard, TBC, dll.).
     *
     * @param  array<string, mixed>  $json
     * @return array{weeks: list<string>, label: string, bar: string, values: list<float|int>, decimals: int}
     */
    private function peerMetricEvalFromJson(array $json, string $site, string $bar, int $decimals = 0): array
    {
        $weeks = [];
        if (! empty($json['weeks']) && is_array($json['weeks'])) {
            foreach ($json['weeks'] as $w) {
                $weeks[] = (string) $w;
            }
        }
        if ($weeks === []) {
            $weeks = ['W12', 'W13', 'W14', 'W15'];
        }
        $bySite = $json['bySite'] ?? [];
        if (! is_array($bySite)) {
            $bySite = [];
        }
        $values = [];
        if ($site === '__all') {
            foreach ($weeks as $wk) {
                $sum = 0;
                foreach ($bySite as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $sum += (int) round($this->peerMetricWeekValueFromRow($row, $wk));
                }
                $values[] = (float) $sum;
            }
        } else {
            $row = is_array($bySite[$site] ?? null) ? $bySite[$site] : [];
            foreach ($weeks as $wk) {
                $values[] = $this->peerMetricWeekValueFromRow($row, $wk);
            }
        }

        return [
            'weeks' => $weeks,
            'label' => (string) ($json['parameter'] ?? ''),
            'bar' => $bar,
            'values' => $values,
            'decimals' => $decimals,
        ];
    }

    /**
     * Data chart trend mingguan (JSON) untuk filter periode via popup di dashboard.
     */
    public function weeklyTrendData(
        Request $request,
        GetPeerPressureDashboardWeeklyTrendAction $weeklyTrend,
        GetPeerPressureDashboardKpiStatsAction $dashboardKpiStats,
        GetPeerPressureDashboardEvaluationSummaryAction $evaluationSummary,
        GetPeerPressureDashboardInsightCardsAction $insightCards,
        GetPeerPressureDeviationModalBreakdownAction $deviationModalBreakdown
    ): JsonResponse {
        $chartPeriodMonth = $request->filled('year') && $request->filled('month');

        if ($chartPeriodMonth) {
            $chartYear = (int) $request->get('year');
            $chartMonth = (int) $request->get('month');
            $chartYear = max(GetPeerPressureDashboardWeeklyTrendAction::MIN_YEAR, min(GetPeerPressureDashboardWeeklyTrendAction::MAX_YEAR, $chartYear));
            $chartMonth = max(1, min(12, $chartMonth));

            return response()->json(array_merge(
                $weeklyTrend($chartYear, $chartMonth),
                [
                    'kpi' => $dashboardKpiStats($chartYear, $chartMonth),
                    'evaluation_summary' => $evaluationSummary($chartYear, $chartMonth),
                    'insight_cards' => $insightCards($chartYear, $chartMonth),
                    'deviation_modal_breakdown' => $deviationModalBreakdown($chartYear, $chartMonth),
                ]
            ));
        }

        return response()->json(array_merge(
            $weeklyTrend(),
            [
                'kpi' => $dashboardKpiStats(),
                'evaluation_summary' => $evaluationSummary(),
                'insight_cards' => $insightCards(),
                'deviation_modal_breakdown' => $deviationModalBreakdown(),
            ]
        ));
    }

    /**
     * Matriks gap pelaksanaan vs kepatuhan per kelompok kerja (JSON) — periode sama dengan filter chart.
     */
    public function gapMatrixData(Request $request, GetPeerPressureDashboardGapMatrixAction $gapMatrix): JsonResponse
    {
        $chartPeriodMonth = $request->filled('year') && $request->filled('month');

        if ($chartPeriodMonth) {
            $chartYear = (int) $request->get('year');
            $chartMonth = (int) $request->get('month');
            $chartYear = max(GetPeerPressureDashboardWeeklyTrendAction::MIN_YEAR, min(GetPeerPressureDashboardWeeklyTrendAction::MAX_YEAR, $chartYear));
            $chartMonth = max(1, min(12, $chartMonth));

            return response()->json($gapMatrix($chartYear, $chartMonth));
        }

        return response()->json($gapMatrix());
    }

    /**
     * Heatmap % pelaksanaan selesai per perusahaan × tanggal temuan (JSON).
     */
    public function perusahaanPelaksanaanHeatmapData(
        Request $request,
        GetPeerPressurePerusahaanPelaksanaanHeatmapAction $heatmap
    ): JsonResponse {
        $chartPeriodMonth = $request->filled('year') && $request->filled('month');

        if ($chartPeriodMonth) {
            $chartYear = (int) $request->get('year');
            $chartMonth = (int) $request->get('month');
            $chartYear = max(GetPeerPressureDashboardWeeklyTrendAction::MIN_YEAR, min(GetPeerPressureDashboardWeeklyTrendAction::MAX_YEAR, $chartYear));
            $chartMonth = max(1, min(12, $chartMonth));

            return response()->json($heatmap($chartYear, $chartMonth));
        }

        return response()->json($heatmap());
    }

    /**
     * Rincian item baseline (sudah / belum peer pressure) untuk sel ringkasan site × kontraktor.
     */
    public function pelaksanaanBaselineDetailData(
        Request $request,
        PeerPressurePelaksanaanBaselineService $baseline,
    ): JsonResponse {
        $site = trim((string) $request->query('site', ''));
        if ($site === '') {
            return response()->json(['message' => 'Parameter site wajib diisi.'], 422);
        }

        $companyRaw = $request->query('company');
        $company = ($companyRaw !== null && $companyRaw !== '') ? trim((string) $companyRaw) : null;

        $tRaw = $request->query('terlaksana', '1');
        $terlaksana = ! in_array((string) $tRaw, ['0', 'false', 'no'], true);

        $chartPeriodMonth = $request->filled('year') && $request->filled('month');
        if ($chartPeriodMonth) {
            $chartYear = (int) $request->get('year');
            $chartMonth = (int) $request->get('month');
            $chartYear = max(GetPeerPressureDashboardWeeklyTrendAction::MIN_YEAR, min(GetPeerPressureDashboardWeeklyTrendAction::MAX_YEAR, $chartYear));
            $chartMonth = max(1, min(12, $chartMonth));

            return response()->json(
                $baseline->pelaksanaanBaselineDetailRows($site, $company, $terlaksana, $chartYear, $chartMonth)
            );
        }

        return response()->json(
            $baseline->pelaksanaanBaselineDetailRows($site, $company, $terlaksana, null, null)
        );
    }

    /**
     * Ringkasan AI: Highlight Issue & Rekomendasi dari agregat dashboard (periode sama dengan filter chart).
     */
    public function dashboardHighlightIssueRecommendation(
        Request $request,
        GeneratePeerPressureDashboardHighlightIssueRecommendationAction $action
    ): JsonResponse {
        $chartPeriodMonth = $request->filled('year') && $request->filled('month');

        if ($chartPeriodMonth) {
            $chartYear = (int) $request->get('year');
            $chartMonth = (int) $request->get('month');
            $chartYear = max(GetPeerPressureDashboardWeeklyTrendAction::MIN_YEAR, min(GetPeerPressureDashboardWeeklyTrendAction::MAX_YEAR, $chartYear));
            $chartMonth = max(1, min(12, $chartMonth));

            return response()->json($action($chartYear, $chartMonth));
        }

        return response()->json($action(null, null));
    }

    public function complianceBreakdownData(
        Request $request,
        GetPeerPressureDashboardComplianceBreakdownAction $breakdown
    ): JsonResponse {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(5, (int) $request->query('per_page', 15)));

        $chartPeriodMonth = $request->filled('year') && $request->filled('month');

        if ($chartPeriodMonth) {
            $chartYear = (int) $request->get('year');
            $chartMonth = (int) $request->get('month');
            $chartYear = max(GetPeerPressureDashboardWeeklyTrendAction::MIN_YEAR, min(GetPeerPressureDashboardWeeklyTrendAction::MAX_YEAR, $chartYear));
            $chartMonth = max(1, min(12, $chartMonth));

            return response()->json($breakdown($chartYear, $chartMonth, $page, $perPage));
        }

        return response()->json($breakdown(null, null, $page, $perPage));
    }

    /**
     * Tabel kejadian pelaksanaan selesai (CLOSED/SELESAI) untuk modal dashboard Peer Pressure.
     */
    public function pelaksanaanSelesaiData(
        Request $request,
        ListPeerPressurePelaksanaanSelesaiKejadianAction $action
    ): JsonResponse {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(5, (int) $request->query('per_page', 10)));

        $chartPeriodMonth = $request->filled('year') && $request->filled('month');

        if ($chartPeriodMonth) {
            $chartYear = (int) $request->get('year');
            $chartMonth = (int) $request->get('month');
            $chartYear = max(GetPeerPressureDashboardWeeklyTrendAction::MIN_YEAR, min(GetPeerPressureDashboardWeeklyTrendAction::MAX_YEAR, $chartYear));
            $chartMonth = max(1, min(12, $chartMonth));

            return response()->json($action($chartYear, $chartMonth, $page, $perPage));
        }

        return response()->json($action(null, null, $page, $perPage));
    }

    /**
     * Detail terpaginasi per tab pada modal statistik deviasi (BeRecord / Validasi blindspot / Speak Up Fatigue).
     */
    public function deviationModalDetailData(
        Request $request,
        ListPeerPressureDeviationModalDetailAction $action
    ): JsonResponse {
        $type = (string) $request->query('type', ListPeerPressureDeviationModalDetailAction::TYPE_BERECORD);
        if (! in_array($type, ListPeerPressureDeviationModalDetailAction::allowedTypes(), true)) {
            return response()->json(['message' => 'Parameter type tidak valid.'], 422);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(5, (int) $request->query('per_page', 10)));

        $chartPeriodMonth = $request->filled('year') && $request->filled('month');

        if ($chartPeriodMonth) {
            $chartYear = (int) $request->get('year');
            $chartMonth = (int) $request->get('month');
            $chartYear = max(GetPeerPressureDashboardWeeklyTrendAction::MIN_YEAR, min(GetPeerPressureDashboardWeeklyTrendAction::MAX_YEAR, $chartYear));
            $chartMonth = max(1, min(12, $chartMonth));

            return response()->json($action($type, $chartYear, $chartMonth, $page, $perPage));
        }

        return response()->json($action($type, null, null, $page, $perPage));
    }

    /**
     * Tabel kejadian pelaksanaan belum selesai (bukan CLOSED/SELESAI) untuk modal dashboard Peer Pressure.
     */
    public function pelaksanaanBelumData(
        Request $request,
        ListPeerPressurePelaksanaanBelumKejadianAction $action
    ): JsonResponse {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(5, (int) $request->query('per_page', 10)));

        $chartPeriodMonth = $request->filled('year') && $request->filled('month');

        if ($chartPeriodMonth) {
            $chartYear = (int) $request->get('year');
            $chartMonth = (int) $request->get('month');
            $chartYear = max(GetPeerPressureDashboardWeeklyTrendAction::MIN_YEAR, min(GetPeerPressureDashboardWeeklyTrendAction::MAX_YEAR, $chartYear));
            $chartMonth = max(1, min(12, $chartMonth));

            return response()->json($action($chartYear, $chartMonth, $page, $perPage));
        }

        return response()->json($action(null, null, $page, $perPage));
    }

    /**
     * Detail kejadian untuk modal dashboard (JSON).
     */
    public function kejadianDetail(int $id, GetPeerPressureKejadianDetailForDashboardAction $detail): JsonResponse
    {
        return response()->json($detail($id));
    }

    /**
     * Kartu horizontal TBC GENERAL untuk modal KPI (JSON).
     */
    public function tbcHighRiskCards(Request $request, GetPeerPressureTbcHighRiskCardsAction $action): JsonResponse
    {
        $chartPeriodMonth = $request->filled('year') && $request->filled('month');
        if ($chartPeriodMonth) {
            $chartYear = (int) $request->get('year');
            $chartMonth = (int) $request->get('month');
            $chartYear = max(GetPeerPressureDashboardWeeklyTrendAction::MIN_YEAR, min(GetPeerPressureDashboardWeeklyTrendAction::MAX_YEAR, $chartYear));
            $chartMonth = max(1, min(12, $chartMonth));

            return response()->json(['cards' => $action($chartYear, $chartMonth)]);
        }

        return response()->json(['cards' => $action(null, null)]);
    }

    /**
     * Detail profiling pelanggar (JSON) untuk modal dashboard — query ?sid=
     */
    public function pelanggarProfilingDetail(
        Request $request,
        GetPeerPressurePelanggarProfilingDetailAction $action
    ): JsonResponse {
        $sid = trim((string) $request->query('sid', ''));
        if ($sid === '') {
            return response()->json(['message' => 'Parameter sid wajib diisi.'], 422);
        }

        return response()->json($action($sid));
    }

    /**
     * Pencarian ringkas CAR/AAJ (nitip.aaj_car_all_year_from_dav) untuk modal Highlight TBC — query ?q=
     */
    public function tbcAajCarSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) > 200) {
            return response()->json(['ok' => true, 'items' => []]);
        }

        try {
            $ch = new ClickHouseService('clickhouse_nitip');
            if (! $ch->isConnected()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'ClickHouse (nitip) tidak terhubung. Periksa CLICKHOUSE_NITIP_* di .env.',
                    'items' => [],
                ], 503);
            }

            $esc = $this->tbcAajEscapeChString($q);
            $digits = preg_replace('/\D/', '', $q) ?? '';
            // Kolom teks bisa String/Nullable — pakai toString agar coalesce aman di semua tipe.
            $parts = [
                "positionCaseInsensitive(toString(coalesce(issue, '')), '{$esc}') > 0",
                "positionCaseInsensitive(toString(coalesce(deskripsi, '')), '{$esc}') > 0",
                "positionCaseInsensitive(toString(coalesce(nama_lokasi, '')), '{$esc}') > 0",
                "positionCaseInsensitive(toString(coalesce(nama_kategori, '')), '{$esc}') > 0",
                "positionCaseInsensitive(toString(coalesce(nama_pelapor, '')), '{$esc}') > 0",
            ];
            if ($digits !== '') {
                $dEsc = $this->tbcAajEscapeChString($digits);
                $parts[] = "replaceAll(toString(id), ',', '') LIKE '%{$dEsc}%'";
            }
            $where = implode(' OR ', $parts);

            $sql = "
                SELECT
                    id,
                    issue,
                    nama_lokasi,
                    toString(tanggal_pembuatan) AS tanggal_pembuatan,
                    status
                FROM aaj_car_all_year_from_dav
                WHERE ({$where})
                ORDER BY coalesce(
                    parseDateTimeBestEffortOrNull(toString(tanggal_pembuatan)),
                    toDateTime(0)
                ) DESC
                LIMIT 40
            ";

            $rows = $ch->query($sql) ?? [];
            $items = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $idVal = $this->tbcAajRowVal($row, 'id');
                $idNorm = $this->tbcAajNormalizeId((string) $idVal);
                $issue = (string) ($this->tbcAajRowVal($row, 'issue') ?? '');
                $loc = (string) ($this->tbcAajRowVal($row, 'nama_lokasi') ?? '');
                $st = (string) ($this->tbcAajRowVal($row, 'status') ?? '');
                $tp = (string) ($this->tbcAajRowVal($row, 'tanggal_pembuatan') ?? '');
                $label = $idNorm . ' — ' . ($issue !== '' ? mb_substr($issue, 0, 72) : ($loc !== '' ? $loc : $st));
                $items[] = [
                    'id' => $idNorm,
                    'label' => $label,
                    'issue' => $issue,
                    'nama_lokasi' => $loc,
                    'status' => $st,
                    'tanggal_pembuatan' => $tp,
                ];
            }

            return response()->json(['ok' => true, 'items' => $items]);
        } catch (\Throwable $e) {
            Log::error('tbcAajCarSearch: ' . $e->getMessage());

            return response()->json([
                'ok' => false,
                'message' => 'Gagal mengambil data pencarian.',
                'items' => [],
            ], 500);
        }
    }

    /**
     * Satu baris atau batch by ids — query ?id= atau ?ids= (koma dipisah), dari nitip.aaj_car_all_year_from_dav
     */
    public function tbcAajCarShow(Request $request): JsonResponse
    {
        if ($request->filled('ids')) {
            return $this->tbcAajCarBatch($request);
        }

        $idRaw = trim((string) $request->query('id', ''));
        if ($idRaw === '') {
            return response()->json(['ok' => false, 'message' => 'Parameter id atau ids wajib diisi.'], 422);
        }

        $idNorm = $this->tbcAajNormalizeId($idRaw);
        if ($idNorm === '') {
            return response()->json(['ok' => false, 'message' => 'ID tidak valid.'], 422);
        }

        try {
            $ch = new ClickHouseService('clickhouse_nitip');
            if (! $ch->isConnected()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'ClickHouse (nitip) tidak terhubung.',
                ], 503);
            }

            $sql = "
                SELECT *
                FROM aaj_car_all_year_from_dav
                WHERE replaceAll(toString(id), ',', '') = '{$this->tbcAajEscapeChString($idNorm)}'
                LIMIT 1
            ";
            $rows = $ch->query($sql) ?? [];
            $row = isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
            if ($row === null) {
                return response()->json(['ok' => false, 'message' => 'Data tidak ditemukan.'], 404);
            }

            return response()->json([
                'ok' => true,
                'card' => $this->tbcAajMapRowToCard($row),
                'record' => $row,
            ]);
        } catch (\Throwable $e) {
            Log::error('tbcAajCarShow: ' . $e->getMessage());

            return response()->json(['ok' => false, 'message' => 'Gagal mengambil detail.'], 500);
        }
    }

    private function tbcAajCarBatch(Request $request): JsonResponse
    {
        $raw = (string) $request->query('ids', '');
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $normalized = [];
        foreach ($parts as $p) {
            $n = $this->tbcAajNormalizeId($p);
            if ($n !== '' && ! in_array($n, $normalized, true)) {
                $normalized[] = $n;
            }
        }
        if ($normalized === []) {
            return response()->json(['ok' => true, 'cards' => []]);
        }
        if (count($normalized) > 80) {
            return response()->json(['ok' => false, 'message' => 'Maksimal 80 ID per permintaan.'], 422);
        }

        try {
            $ch = new ClickHouseService('clickhouse_nitip');
            if (! $ch->isConnected()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'ClickHouse (nitip) tidak terhubung.',
                    'cards' => [],
                ], 503);
            }

            $inList = implode(',', array_map(function (string $n) {
                return "'" . $this->tbcAajEscapeChString($n) . "'";
            }, $normalized));

            $sql = "
                SELECT *
                FROM aaj_car_all_year_from_dav
                WHERE replaceAll(toString(id), ',', '') IN ({$inList})
            ";
            $rows = $ch->query($sql) ?? [];
            $byId = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $card = $this->tbcAajMapRowToCard($row);
                $byId[$card['raw_id']] = $card;
            }

            $cards = [];
            foreach ($normalized as $nid) {
                if (isset($byId[$nid])) {
                    $cards[] = $byId[$nid];
                }
            }

            return response()->json(['ok' => true, 'cards' => $cards]);
        } catch (\Throwable $e) {
            Log::error('tbcAajCarBatch: ' . $e->getMessage());

            return response()->json(['ok' => false, 'message' => 'Gagal memuat daftar.', 'cards' => []], 500);
        }
    }

    private function tbcAajEscapeChString(string $s): string
    {
        return str_replace("'", "''", $s);
    }

    private function tbcAajNormalizeId(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        return $digits;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function tbcAajRowVal(array $row, string $key): mixed
    {
        $kLower = strtolower($key);
        foreach ($row as $k => $v) {
            if (strtolower((string) $k) === $kLower) {
                return $v;
            }
        }

        return $row[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function tbcAajMapRowToCard(array $row): array
    {
        $idVal = $this->tbcAajRowVal($row, 'id');
        $idStr = $this->tbcAajNormalizeId((string) $idVal);

        $jenis = trim((string) ($this->tbcAajRowVal($row, 'jenis_laporan') ?? ''));
        $kat = trim((string) ($this->tbcAajRowVal($row, 'nama_kategori') ?? ''));
        $section = $jenis !== '' ? $jenis : ($kat !== '' ? $kat : 'CAR / Observasi');

        $issue = trim((string) ($this->tbcAajRowVal($row, 'issue') ?? ''));
        $title = $issue !== '' ? $issue : ($kat !== '' ? $kat : 'Temuan');

        $tanggal = $this->tbcAajRowVal($row, 'tanggal');
        $tp = $this->tbcAajRowVal($row, 'tanggal_pembuatan');
        $dateStr = '';
        if ($tp !== null && $tp !== '') {
            $dateStr = substr((string) $tp, 0, 10);
        } elseif ($tanggal !== null && $tanggal !== '') {
            $dateStr = substr((string) $tanggal, 0, 10);
        }

        $deskripsi = trim((string) ($this->tbcAajRowVal($row, 'deskripsi') ?? ''));

        $namaPic = trim((string) ($this->tbcAajRowVal($row, 'nama_pic') ?? ''));
        $jabPic = trim((string) ($this->tbcAajRowVal($row, 'jabatan_fungsional_pic') ?? ''));
        $perPic = trim((string) ($this->tbcAajRowVal($row, 'perusahaan_pic') ?? ''));
        $people = $namaPic;
        if ($jabPic !== '') {
            $people .= ($people !== '' ? ' — ' : '') . $jabPic;
        }
        if ($perPic !== '') {
            $people .= ($people !== '' ? ' · ' : '') . $perPic;
        }

        $nl = trim((string) ($this->tbcAajRowVal($row, 'nama_lokasi') ?? ''));
        $ndl = trim((string) ($this->tbcAajRowVal($row, 'nama_detail_lokasi') ?? ''));
        $ld = trim((string) ($this->tbcAajRowVal($row, 'lokasi_detail') ?? ''));
        $lokasi = $nl;
        if ($ndl !== '') {
            $lokasi .= ($lokasi !== '' ? ' — ' : '') . $ndl;
        }
        if ($ld !== '' && $ld !== $ndl) {
            $lokasi .= ($lokasi !== '' ? ' · ' : '') . $ld;
        }

        $np = trim((string) ($this->tbcAajRowVal($row, 'nama_pelapor') ?? ''));
        $method = trim((string) ($this->tbcAajRowVal($row, 'method') ?? ''));
        $pelapor = 'Pelapor: ' . $np;
        if ($method !== '') {
            $pelapor .= ' · ' . $method;
        }

        $st = strtoupper(trim((string) ($this->tbcAajRowVal($row, 'status') ?? '')));
        $status = 'closed';
        if ($st === 'OPEN' || ($st !== '' && str_contains($st, 'OPEN') && ! str_contains($st, 'CLOSE'))) {
            $status = 'open';
        } elseif (str_contains($st, 'SUBMIT')) {
            $status = 'submitted';
        }

        $url = trim((string) ($this->tbcAajRowVal($row, 'url_photo') ?? ''));

        return [
            'source' => 'clickhouse',
            'raw_id' => $idStr,
            'section' => $section,
            'tone' => 'slate',
            'title' => $title,
            'date' => $dateStr,
            'body' => $deskripsi,
            'people' => $people,
            'lokasi' => $lokasi,
            'pelapor' => $pelapor,
            'status' => $status,
            'url_photo' => $url !== '' ? $url : null,
        ];
    }

    public function import(Request $request, PeerPressureKejadianEdukasiExcelImportService $excelImport): RedirectResponse
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls|max:15360',
        ], [
            'excel_file.required' => 'File Excel wajib diupload.',
        ]);

        $result = $excelImport->importFromUpload($request->file('excel_file'));

        if (! $result->success) {
            return redirect()
                ->route('peer-pressure-edukasi.index')
                ->with('error', $result->message)
                ->with('import_errors', $result->errors);
        }

        return redirect()
            ->route('peer-pressure-edukasi.index')
            ->with('success', $result->message);
    }

    public function downloadTemplate(PeerPressureKejadianEdukasiExcelImportService $excelImport)
    {
        return $excelImport->streamTemplateDownload();
    }
}
