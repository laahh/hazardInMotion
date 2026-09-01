<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Http\Controllers\Controller;
use App\Services\Isc\IscPobSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IscPobController extends Controller
{
    public function __construct(
        private readonly IscPobSnapshotService $snapshot,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $demo = $request->query('source', 'demo') !== 'live';
        $data = $this->snapshot->snapshot($request->boolean('fresh'), $demo);

        return response()->json([
            'success' => true,
            'source' => $data['source'] ?? ($demo ? 'demo' : 'live'),
            'generated_at' => $data['generated_at'],
            'stale_gps_seconds' => $data['stale_gps_seconds'],
            'besigma_connected' => $data['besigma_connected'],
            'besigma_error' => $data['besigma_error'],
            'rfid_available' => $data['rfid_available'],
            'summary' => $data['summary'],
            'reconcile' => $data['reconcile'],
            'people' => $data['people'],
            'hazard_features' => $data['hazard_features'] ?? [],
        ]);
    }

    public function show(Request $request, string $key): JsonResponse
    {
        $demo = $request->query('source', 'demo') !== 'live';
        $person = $this->snapshot->personByKey($key, $demo);
        if ($person === null) {
            return response()->json(['success' => false, 'message' => 'Personel tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'person' => $person]);
    }
}
