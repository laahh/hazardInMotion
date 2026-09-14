<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use App\Services\PncMonitoring\PncMonitoringPengawasDashboardAssembler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PncMonitoringPengawasDashboardController extends Controller
{
    public function index(Request $request, PncMonitoringPengawasDashboardAssembler $assembler): View
    {
        $payload = $assembler->assemble($this->filtersFromRequest($request));

        return view('pnc-monitoring.dashboard.pengawas', [
            'payload' => $payload,
            'dataUrl' => route('pnc-monitoring.dashboard.pengawas.data'),
        ]);
    }

    public function data(Request $request, PncMonitoringPengawasDashboardAssembler $assembler): JsonResponse
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
            'week' => (string) $request->input('week', 'ALL'),
            'site' => (string) $request->input('site', 'ALL'),
            'company' => (string) $request->input('company', 'ALL'),
            'detail' => (string) $request->input('detail', 'ALL'),
        ];
    }
}
