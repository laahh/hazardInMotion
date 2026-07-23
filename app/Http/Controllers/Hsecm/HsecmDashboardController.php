<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hsecm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Hsecm\Concerns\ProvidesHsecmLayout;
use App\Services\Hsecm\HsecmDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HsecmDashboardController extends Controller
{
    use ProvidesHsecmLayout;

    public function __construct(
        private readonly HsecmDashboardService $dashboardService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->dashboardService->resolveFilters($request);
        $dashboard = $this->dashboardService->buildDashboard($filters);

        return view('BaseRule.dashboard', $this->hsecmViewData('dashboard', [
            'filters' => $dashboard['filters'],
            'filterOptions' => $dashboard['filter_options'],
            'periodLabel' => $dashboard['period_label'],
            'kpis' => $dashboard['kpis'],
            'bySite' => $dashboard['by_site'],
            'byCompany' => $dashboard['by_company'],
            'datasets' => $dashboard['datasets'],
        ]));
    }
}
