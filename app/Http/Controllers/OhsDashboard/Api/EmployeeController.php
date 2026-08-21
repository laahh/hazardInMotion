<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsDashboard\Api;

use App\Services\OhsDashboard\InitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmployeeController extends OhsDashboardApiController
{
    public function __construct(private readonly InitService $initService) {}

    public function search(Request $request): JsonResponse
    {
        return $this->jsonOk($this->initService->searchEmployees(
            (string) $request->query('q', ''),
            (int) $request->query('limit', 20),
        ));
    }
}
