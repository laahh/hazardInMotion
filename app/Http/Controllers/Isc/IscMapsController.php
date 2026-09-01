<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Http\Controllers\Controller;
use App\Services\Isc\IscBoundaryMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Peta ISC: boundary IUPK (BounderyBC.js) + polygon/event dari Besigma.
 */
final class IscMapsController extends Controller
{
    public function __construct(
        private readonly IscBoundaryMapService $boundaries,
    ) {}

    public function index(): View
    {
        $status = $this->boundaries->tableStatus();

        return view('isc.maps.index', [
            'connected' => $status['connected'],
            'tables' => $status['tables'],
            'iupkAsset' => asset('isc-assets/BounderyBC.js'),
            'boundariesUrl' => route('isc.maps.boundaries'),
            'overlayUrl' => route('isc.maps.overlay'),
            'pobUrl' => route('isc.maps.pob', ['source' => 'demo']),
            'interventionsUrl' => route('isc.interventions.index'),
        ]);
    }

    public function boundaries(): JsonResponse
    {
        return response()->json($this->boundaries->boundariesGeoJson());
    }

    public function overlay(): JsonResponse
    {
        $status = $this->boundaries->tableStatus();

        return response()->json([
            'connected' => $status['connected'],
            'tables' => $status['tables'],
            'overlay' => $this->boundaries->overlayData(),
        ]);
    }
}
