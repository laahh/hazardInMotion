<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hsecm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Hsecm\Concerns\ProvidesHsecmLayout;
use App\Services\Hsecm\HsecmDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HsecmPjoActionController extends Controller
{
    use ProvidesHsecmLayout;

    public function __construct(
        private readonly HsecmDashboardService $dashboardService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->dashboardService->resolveFilters($request);
        $dashboard = $this->dashboardService->buildPjoActionDashboard($filters);

        return view('BaseRule.pjo-action.index', $this->hsecmViewData('pjo-action', [
            'filters' => $dashboard['filters'],
            'filterOptions' => $dashboard['filter_options'],
            'periodLabel' => $dashboard['period_label'],
            'exposure' => $dashboard['exposure'],
            'gaps' => $dashboard['gaps'],
            'actionGaps' => $dashboard['action_gaps'],
            'summary' => $dashboard['summary'],
        ]));
    }
}
