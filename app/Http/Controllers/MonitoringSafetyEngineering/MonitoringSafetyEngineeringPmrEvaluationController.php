<?php

declare(strict_types=1);

namespace App\Http\Controllers\MonitoringSafetyEngineering;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MonitoringSafetyEngineering\Concerns\ProvidesMonitoringSafetyEngineeringLayout;
use App\Services\MonitoringSafetyEngineering\MonitoringSafetyEngineeringPmrEvaluationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringSafetyEngineeringPmrEvaluationController extends Controller
{
    use ProvidesMonitoringSafetyEngineeringLayout;

    public function __construct(
        private readonly MonitoringSafetyEngineeringPmrEvaluationService $pmrEvaluationService,
    ) {}

    public function index(Request $request): View
    {
        $dashboard = $this->pmrEvaluationService->buildDashboard($request);

        return view('MonitoringSafetyEngginering.pmr-evaluation', $this->monitoringSafetyEngineeringViewData('pmr-evaluation', [
            'filters' => $dashboard['filters'],
            'filterOptions' => $dashboard['filter_options'],
            'summary' => $dashboard['summary'],
            'items' => $dashboard['items'],
            'pmrGroups' => $dashboard['pmr_groups'],
            'effectivenessLevels' => $dashboard['effectiveness_levels'],
            'validationMatrix' => $dashboard['validation_matrix'],
            'followUpSummary' => $dashboard['follow_up_summary'],
            'priorityItems' => $dashboard['priority_items'],
            'fokusAnalisis' => $dashboard['fokus_analisis'],
            'briefAnalysis' => $dashboard['brief_analysis'],
            'nextTodo' => $dashboard['next_todo'],
            'charts' => $dashboard['charts'],
        ]));
    }
}
