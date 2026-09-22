<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use App\Services\PncMonitoring\PncMonitoringMainDashboardAssembler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PncMonitoringMainDashboardController extends Controller
{
    public function index(Request $request, PncMonitoringMainDashboardAssembler $assembler): View
    {
        $payload = $assembler->assemble($this->filtersFromRequest($request));

        return view('pnc-monitoring.dashboard.main', [
            'payload' => $payload,
            'dataUrl' => route('pnc-monitoring.dashboard.main.data'),
        ]);
    }

    public function data(Request $request, PncMonitoringMainDashboardAssembler $assembler): JsonResponse
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
            'site' => (string) $request->input('site', 'ALL'),
        ];
    }
}
