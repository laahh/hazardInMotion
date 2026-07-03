<?php

declare(strict_types=1);

namespace App\Http\Controllers\MonitoringSafetyEngineering;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MonitoringSafetyEngineering\Concerns\ProvidesMonitoringSafetyEngineeringLayout;
use App\Services\MonitoringSafetyEngineering\MonitoringSafetyEngineeringDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringSafetyEngineeringDashboardController extends Controller
{
    use ProvidesMonitoringSafetyEngineeringLayout;

    public function __construct(
        private readonly MonitoringSafetyEngineeringDashboardService $dashboardService,
    ) {}

    public function index(Request $request): View
    {
        $dashboard = $this->dashboardService->buildDashboard($request);

        return view('MonitoringSafetyEngginering.dashboard', $this->monitoringSafetyEngineeringViewData('dashboard', [
            'dashboard' => $dashboard,
            'filters' => $dashboard['filters'],
            'filterOptions' => $dashboard['filter_options'],
            'summary' => $dashboard['summary'],
            'overdueSummary' => $dashboard['overdue_summary'],
            'activeCategory' => $dashboard['active_category'],
            'activeItems' => $dashboard['active_items'],
            'briefAnalysis' => $dashboard['brief_analysis'],
            'nextTodo' => $dashboard['next_todo'],
        ]));
    }
}
