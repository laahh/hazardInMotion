<?php

declare(strict_types=1);

namespace App\Http\Controllers\MonitoringSafetyEngineering;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MonitoringSafetyEngineering\Concerns\ProvidesMonitoringSafetyEngineeringLayout;
use App\Services\MonitoringSafetyEngineering\MonitoringSafetyEngineeringOutsideCommitmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringSafetyEngineeringOutsideCommitmentController extends Controller
{
    use ProvidesMonitoringSafetyEngineeringLayout;

    public function __construct(
        private readonly MonitoringSafetyEngineeringOutsideCommitmentService $outsideCommitmentService,
    ) {}

    public function index(Request $request): View
    {
        $dashboard = $this->outsideCommitmentService->buildDashboard($request);

        return view('MonitoringSafetyEngginering.outside-commitment', $this->monitoringSafetyEngineeringViewData('outside-commitment', [
            'filters' => $dashboard['filters'],
            'filterOptions' => $dashboard['filter_options'],
            'summary' => $dashboard['summary'],
            'overdueSummary' => $dashboard['overdue_summary'],
            'activeCategory' => $dashboard['active_category'],
            'activeItems' => $dashboard['active_items'],
            'previewItems' => $dashboard['preview_items'],
            'briefAnalysis' => $dashboard['brief_analysis'],
            'nextTodo' => $dashboard['next_todo'],
            'charts' => $dashboard['charts'],
        ]));
    }
}
