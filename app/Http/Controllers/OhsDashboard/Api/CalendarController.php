<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsDashboard\Api;

use App\Services\OhsDashboard\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CalendarController extends OhsDashboardApiController
{
    public function __construct(private readonly CalendarService $calendarService) {}

    public function range(Request $request): JsonResponse
    {
        return $this->jsonOk($this->calendarService->range($this->payload($request)));
    }
}
