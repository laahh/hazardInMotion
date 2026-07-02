<?php

declare(strict_types=1);

namespace App\Http\Controllers\AutoBanned;

use App\Http\Controllers\Controller;
use App\Http\Controllers\AutoBanned\Concerns\ProvidesAutoBannedLayout;
use App\Services\AutoBanned\AutoBannedPipelineMonitoringService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutoBannedPipelineMonitoringController extends Controller
{
    use ProvidesAutoBannedLayout;

    public function __construct(
        private readonly AutoBannedPipelineMonitoringService $pipelineMonitoringService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->pipelineMonitoringService->resolveFilters($request);
        $dashboard = $this->pipelineMonitoringService->buildDashboard($filters);

        return view('AutoBanned.pipeline-monitoring.index', [
            'navActive' => 'pipeline-monitoring',
            'navItems' => $this->autoBannedNavItems(),
            'filters' => $dashboard['filters'],
            'period' => $dashboard['period'],
            'filterOptions' => $dashboard['filterOptions'],
            'stats' => $dashboard['stats'],
            'pipelineRows' => $dashboard['pipelineRows'],
            'tableAvailable' => $dashboard['tableAvailable'],
        ]);
    }
}
