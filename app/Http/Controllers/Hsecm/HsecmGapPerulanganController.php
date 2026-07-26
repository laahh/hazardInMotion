<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hsecm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Hsecm\Concerns\ProvidesHsecmLayout;
use App\Services\Hsecm\HsecmDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HsecmGapPerulanganController extends Controller
{
    use ProvidesHsecmLayout;

    public function __construct(
        private readonly HsecmDashboardService $dashboardService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->dashboardService->resolveFilters($request);
        $dashboard = $this->dashboardService->buildGapPerulanganDashboard($filters);

        return view('BaseRule.gap-perulangan.index', $this->hsecmViewData('gap-perulangan', [
            'filters' => $dashboard['filters'],
            'filterOptions' => $dashboard['filter_options'],
            'periodLabel' => $dashboard['period_label'],
            'summary' => $dashboard['summary'],
            'sections' => $dashboard['sections'],
        ]));
    }
}
