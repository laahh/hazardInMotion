<?php

declare(strict_types=1);

namespace App\Http\Controllers\MonitoringSafetyEngineering;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MonitoringSafetyEngineering\Concerns\ProvidesMonitoringSafetyEngineeringLayout;
use App\Services\MonitoringSafetyEngineering\MonitoringSafetyEngineeringCompanyOverviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringSafetyEngineeringCompanyOverviewController extends Controller
{
    use ProvidesMonitoringSafetyEngineeringLayout;

    public function __construct(
        private readonly MonitoringSafetyEngineeringCompanyOverviewService $companyOverviewService,
    ) {}

    public function index(Request $request): View
    {
        $dashboard = $this->companyOverviewService->buildDashboard($request);

        return view('MonitoringSafetyEngginering.company-overview', $this->monitoringSafetyEngineeringViewData('company-overview', [
            'filters' => $dashboard['filters'],
            'filterOptions' => $dashboard['filter_options'],
            'totals' => $dashboard['totals'],
            'mitraRows' => $dashboard['mitra_rows'],
            'sumberProgramRows' => $dashboard['sumber_program_rows'],
            'topOverdue' => $dashboard['top_overdue'],
            'trend' => $dashboard['trend'],
            'briefAnalysis' => $dashboard['brief_analysis'],
            'nextTodo' => $dashboard['next_todo'],
        ]));
    }
}
