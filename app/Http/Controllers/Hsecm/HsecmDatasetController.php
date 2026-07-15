<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hsecm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Hsecm\Concerns\ProvidesHsecmLayout;
use App\Services\Hsecm\HsecmDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HsecmDatasetController extends Controller
{
    use ProvidesHsecmLayout;

    public function __construct(
        private readonly HsecmDashboardService $dashboardService,
    ) {}

    public function show(Request $request, string $dataset): View
    {
        if (! $this->dashboardService->datasetExists($dataset)) {
            abort(404);
        }

        $filters = $this->dashboardService->resolveFilters($request);
        $page = $this->dashboardService->buildDatasetPage($dataset, $filters);

        return view('BaseRule.datasets.show', $this->hsecmViewData($dataset, [
            'filters' => $page['filters'],
            'filterOptions' => $page['filter_options'],
            'dataset' => $page['dataset'],
            'rows' => $page['rows'],
            'summary' => $page['summary'],
        ]));
    }
}
