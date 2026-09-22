<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use App\Services\PncMonitoring\PncMonitoringInventoryDashboardAssembler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PncMonitoringInventoryDashboardController extends Controller
{
    public function index(Request $request, PncMonitoringInventoryDashboardAssembler $assembler): View
    {
        $payload = $assembler->assemble($this->filtersFromRequest($request));

        return view('pnc-monitoring.dashboard.inventory', [
            'payload' => $payload,
            'dataUrl' => route('pnc-monitoring.dashboard.inventory.data'),
        ]);
    }

    public function data(Request $request, PncMonitoringInventoryDashboardAssembler $assembler): JsonResponse
    {
        return response()->json($assembler->assemble($this->filtersFromRequest($request)));
    }

    /**
     * @return array<string, string>
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'category' => (string) $request->input('category', 'ALL'),
            'site' => (string) $request->input('site', 'ALL'),
            'status' => (string) $request->input('status', 'ALL'),
        ];
    }
}
