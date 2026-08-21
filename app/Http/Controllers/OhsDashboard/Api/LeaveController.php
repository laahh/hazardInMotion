<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsDashboard\Api;

use App\Exceptions\OhsDashboard\OhsDashboardException;
use App\Services\OhsDashboard\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LeaveController extends OhsDashboardApiController
{
    public function __construct(private readonly LeaveService $leaveService) {}

    public function history(Request $request): JsonResponse
    {
        $empId = trim((string) $request->query('empId', ''));
        if ($empId === '') {
            throw new OhsDashboardException('empId wajib diisi.');
        }

        $year = $request->query('year');

        return $this->jsonOk($this->leaveService->history(
            $empId,
            $year !== null && $year !== '' ? (int) $year : null,
        ));
    }

    public function checkOverlap(Request $request): JsonResponse
    {
        return $this->jsonOk($this->leaveService->checkOverlap($this->payload($request)));
    }

    public function create(Request $request): JsonResponse
    {
        return $this->jsonOk($this->leaveService->create($this->payload($request)), 201);
    }
}
