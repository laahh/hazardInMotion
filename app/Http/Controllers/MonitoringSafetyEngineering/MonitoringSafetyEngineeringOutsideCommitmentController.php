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

        $allCategoryItems = array_merge(
            $dashboard['arahan_manajemen_items'] ?? [],
            $dashboard['rekom_insiden_items'] ?? [],
            $dashboard['rekom_gr_items'] ?? [],
        );

        $recordDetailById = collect($allCategoryItems)
            ->filter(static fn (array $item): bool => isset($item['detail'], $item['id']))
            ->mapWithKeys(static fn (array $item): array => [(int) $item['id'] => $item['detail']])
            ->all();

        return view('MonitoringSafetyEngginering.outside-commitment', $this->monitoringSafetyEngineeringViewData('outside-commitment', [
            'dashboard' => $dashboard,
            'filters' => $dashboard['filters'],
            'filterOptions' => $dashboard['filter_options'],
            'summary' => $dashboard['summary'],
            'overdueSummary' => $dashboard['overdue_summary'],
            'activeCategory' => $dashboard['active_category'],
            'activeItems' => $dashboard['active_items'],
            'arahanManajemenItems' => $dashboard['arahan_manajemen_items'] ?? [],
            'rekomInsidenItems' => $dashboard['rekom_insiden_items'] ?? [],
            'rekomGrItems' => $dashboard['rekom_gr_items'] ?? [],
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
