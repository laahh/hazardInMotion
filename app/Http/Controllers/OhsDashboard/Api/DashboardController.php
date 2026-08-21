<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsDashboard\Api;

use App\Services\OhsDashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends OhsDashboardApiController
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function overview(Request $request): JsonResponse
    {
        return $this->jsonOk($this->dashboardService->overview($this->payload($request)));
    }
}
