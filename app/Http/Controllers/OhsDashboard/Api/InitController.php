<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsDashboard\Api;

use App\Services\OhsDashboard\InitService;
use Illuminate\Http\JsonResponse;

final class InitController extends OhsDashboardApiController
{
    public function __construct(private readonly InitService $initService) {}

    public function __invoke(): JsonResponse
    {
        return $this->jsonOk($this->initService->getInit());
    }
}
