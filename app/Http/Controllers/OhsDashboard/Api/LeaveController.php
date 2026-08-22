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

    public function list(Request $request): JsonResponse
    {
        return $this->jsonOk($this->leaveService->list($this->payload($request)));
    }

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

    public function show(Request $request): JsonResponse
    {
        $requestId = trim((string) $request->query('requestId', ''));
        if ($requestId === '') {
            throw new OhsDashboardException('requestId wajib diisi.');
        }

        return $this->jsonOk($this->leaveService->show($requestId));
    }

    public function checkOverlap(Request $request): JsonResponse
    {
        return $this->jsonOk($this->leaveService->checkOverlap($this->payload($request)));
    }

    public function create(Request $request): JsonResponse
    {
        return $this->jsonOk($this->leaveService->create($this->payload($request)), 201);
    }

    public function update(Request $request): JsonResponse
    {
        return $this->jsonOk($this->leaveService->update($this->payload($request)));
    }

    public function delete(Request $request): JsonResponse
    {
        return $this->jsonOk($this->leaveService->delete($this->payload($request)));
    }
}
