<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Actions\Isc\IscHazardReportStoreAction;
use App\Actions\Isc\IscInterventionStoreAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Isc\IscHazardReportStoreRequest;
use App\Http\Requests\Isc\IscInterventionStoreRequest;
use App\Services\Isc\IscHazardEmployeeLookupService;
use App\Services\Isc\IscMapsInterventionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IscMapsInterventionController extends Controller
{
    public function __construct(
        private readonly IscMapsInterventionService $tasks,
        private readonly IscInterventionStoreAction $storeAction,
        private readonly IscHazardReportStoreAction $hazardStoreAction,
        private readonly IscHazardEmployeeLookupService $employees,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $demo = $request->query('source') === 'demo';

        return response()->json([
            'success' => true,
            ...$this->tasks->payload($request->user(), $demo),
        ]);
    }

    public function store(IscInterventionStoreRequest $request): JsonResponse
    {
        $intervention = $this->storeAction->execute($request->user(), $request->validated(), []);

        return response()->json([
            'success' => true,
            'intervention_id' => $intervention->id,
            'event_id' => $intervention->event_id,
            'status' => $intervention->event?->status,
        ]);
    }

    public function storeHazardReport(IscHazardReportStoreRequest $request): JsonResponse
    {
        $report = $this->hazardStoreAction->execute(
            $request->user(),
            $request->validated(),
            $request->file('foto'),
        );

        return response()->json([
            'success' => true,
            'report_id' => $report->id,
            'event_id' => $report->event_id,
            'intervention_id' => $report->intervention_id,
            'status' => $report->status,
        ], 201);
    }

    public function lookupEmployees(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'results' => []]);
        }

        return response()->json([
            'success' => true,
            'results' => $this->employees->search($q),
        ]);
    }
}
