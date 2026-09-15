<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Actions\Isc\IscHazardReportStoreAction;
use App\Actions\Isc\IscInterventionStoreAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Isc\IscHazardReportStoreRequest;
use App\Http\Requests\Isc\IscInterventionStoreRequest;
use App\Services\Isc\IscHazardEmployeeLookupService;
use App\Services\Isc\IscHazardSysUserLookupService;
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
        private readonly IscHazardSysUserLookupService $sysUsers,
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
            'demo' => $report->status === 'demo',
            'report_id' => $report->id,
            'event_id' => $report->event_id,
            'intervention_id' => $report->intervention_id,
            'status' => $report->status,
            'message' => $report->status === 'demo'
                ? 'Laporan hazard dummy diterima (Belum disimpan ke DB).'
                : null,
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

    /**
     * Lookup akses (username/password) + profil pelapor by SID dari hse_automation.
     */
    public function lookupSysUser(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', $request->query('sid', '')));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'results' => [], 'user' => null]);
        }

        $results = $this->sysUsers->search($q);
        $exact = null;
        $sid = strtoupper($q);
        foreach ($results as $row) {
            if (strtoupper((string) ($row['sid'] ?? '')) === $sid) {
                $exact = $row;
                break;
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'user' => $exact ?? ($results[0] ?? null),
        ]);
    }
}
