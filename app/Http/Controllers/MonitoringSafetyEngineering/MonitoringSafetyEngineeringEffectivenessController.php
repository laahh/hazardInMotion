<?php

declare(strict_types=1);

namespace App\Http\Controllers\MonitoringSafetyEngineering;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MonitoringSafetyEngineering\Concerns\ProvidesMonitoringSafetyEngineeringLayout;
use App\Services\MonitoringSafetyEngineering\MonitoringSafetyEngineeringEffectivenessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringSafetyEngineeringEffectivenessController extends Controller
{
    use ProvidesMonitoringSafetyEngineeringLayout;

    public function __construct(
        private readonly MonitoringSafetyEngineeringEffectivenessService $effectivenessService,
    ) {}

    public function index(Request $request): View
    {
        $dashboard = $this->effectivenessService->buildDashboard($request);

        return view('MonitoringSafetyEngginering.effectiveness', $this->monitoringSafetyEngineeringViewData('effectiveness', [
            'filters' => $dashboard['filters'],
            'filterOptions' => $dashboard['filter_options'],
            'summary' => $dashboard['summary'],
            'riskDistribution' => $dashboard['risk_distribution'],
            'validationRecap' => $dashboard['validation_recap'],
            'validationMatrix' => $dashboard['validation_matrix'],
            'priorityList' => $dashboard['priority_list'],
            'briefAnalysis' => $dashboard['brief_analysis'],
            'nextTodo' => $dashboard['next_todo'],
        ]));
    }
}
