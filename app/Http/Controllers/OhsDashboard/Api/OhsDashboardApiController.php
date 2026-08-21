<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsDashboard\Api;

use App\Http\Controllers\Controller;
use App\Services\OhsDashboard\Support\OhsDashboardPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class OhsDashboardApiController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    protected function payload(Request $request): array
    {
        return OhsDashboardPayload::from($request);
    }

    protected function jsonOk(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }
}
