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

        $allCategoryItems = array_merge(
            $dashboard['replikasi_items'] ?? [],
            $dashboard['safety_engineering_items'] ?? [],
            $dashboard['additional_safety_items'] ?? [],
        );

        $recordDetailById = collect($allCategoryItems)
            ->filter(static fn (array $item): bool => isset($item['detail'], $item['id']))
            ->mapWithKeys(static fn (array $item): array => [(int) $item['id'] => $item['detail']])
            ->all();

        return view('MonitoringSafetyEngginering.dashboard', $this->monitoringSafetyEngineeringViewData('dashboard', [
            'dashboard' => $dashboard,
            'filters' => $dashboard['filters'],
            'filterOptions' => $dashboard['filter_options'],
            'summary' => $dashboard['summary'],
            'overdueSummary' => $dashboard['overdue_summary'],
            'activeCategory' => $dashboard['active_category'],
            'activeItems' => $dashboard['active_items'],
            'replikasiItems' => $dashboard['replikasi_items'] ?? [],
            'safetyEngineeringItems' => $dashboard['safety_engineering_items'] ?? [],
            'additionalSafetyItems' => $dashboard['additional_safety_items'] ?? [],
            'safetyEngineeringDetailById' => $recordDetailById,
            'recordDetailById' => $recordDetailById,
            'briefAnalysis' => $dashboard['brief_analysis'],
            'nextTodo' => $dashboard['next_todo'],
            'charts' => $dashboard['charts'],
            'riskReductionMatrix' => $dashboard['risk_reduction_matrix'] ?? [
                'columns' => [],
                'rows' => [],
                'total' => 0,
                'without_prediksi' => 0,
            ],
        ]));
    }
}
