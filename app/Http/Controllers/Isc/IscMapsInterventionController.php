<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Actions\Isc\IscInterventionStoreAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Isc\IscInterventionStoreRequest;
use App\Services\Isc\IscMapsInterventionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IscMapsInterventionController extends Controller
{
    public function __construct(
        private readonly IscMapsInterventionService $tasks,
        private readonly IscInterventionStoreAction $storeAction,
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
}
