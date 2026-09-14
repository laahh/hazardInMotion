<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use App\Services\PncMonitoring\PncMonitoringIkkDashboardAssembler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PncMonitoringIkkDashboardController extends Controller
{
    public function index(Request $request, PncMonitoringIkkDashboardAssembler $assembler): View
    {
        $payload = $assembler->assemble($this->filtersFromRequest($request));

        return view('pnc-monitoring.dashboard.ikk', [
            'payload' => $payload,
            'dataUrl' => route('pnc-monitoring.dashboard.ikk.data'),
        ]);
    }

    public function data(Request $request, PncMonitoringIkkDashboardAssembler $assembler): JsonResponse
    {
        return response()->json($assembler->assemble($this->filtersFromRequest($request)));
    }

    /**
     * @return array<string, string>
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'year' => (string) $request->input('year', 'ALL'),
            'month' => (string) $request->input('month', 'ALL'),
            'week' => (string) $request->input('week', 'ALL'),
            'site' => (string) $request->input('site', 'ALL'),
            'company' => (string) $request->input('company', 'ALL'),
            'type' => (string) $request->input('type', 'ALL'),
        ];
    }
}
