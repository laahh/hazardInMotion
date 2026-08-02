<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hsecm;

use App\Actions\Hsecm\HsecmBuildGapEvaluasiDashboardAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Hsecm\Concerns\ProvidesHsecmLayout;
use App\Services\Hsecm\HsecmDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HsecmGapEvaluasiController extends Controller
{
    use ProvidesHsecmLayout;

    public function __construct(
        private readonly HsecmDashboardService $dashboardService,
        private readonly HsecmBuildGapEvaluasiDashboardAction $buildGapEvaluasi,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->dashboardService->resolveFilters($request);
        $dashboard = $this->buildGapEvaluasi->execute($filters);

        return view('BaseRule.gap-evaluasi.index', $this->hsecmViewData('gap-evaluasi', [
            'filters' => $dashboard['filters'],
            'filterOptions' => $dashboard['filter_options'],
            'periodLabel' => $dashboard['period_label'],
            'evalDate' => $dashboard['eval_date'],
            'prevDate' => $dashboard['prev_date'],
            'slotsD' => $dashboard['slots_d'],
            'slotsPrev' => $dashboard['slots_prev'],
            'overview' => $dashboard['overview'],
            'programs' => $dashboard['programs'],
            'scrape' => $dashboard['scrape'],
            'tasklist' => $dashboard['tasklist'],
        ]));
    }
}
