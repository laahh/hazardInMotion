<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Http\Controllers\Controller;
use App\Services\Isc\IscCctvMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IscCctvMapController extends Controller
{
    public function __construct(
        private readonly IscCctvMapService $cctv,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $demo = $request->query('source') === 'demo';

        return response()->json([
            'success' => true,
            ...$this->cctv->payload($demo),
        ]);
    }
}
