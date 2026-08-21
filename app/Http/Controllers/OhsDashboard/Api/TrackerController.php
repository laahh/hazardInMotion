<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsDashboard\Api;

use App\Exceptions\OhsDashboard\OhsDashboardException;
use App\Services\OhsDashboard\TrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TrackerController extends OhsDashboardApiController
{
    public function __construct(private readonly TrackerService $trackerService) {}

    public function create(Request $request): JsonResponse
    {
        return $this->jsonOk($this->trackerService->create($this->payload($request)), 201);
    }

    public function updateDetails(Request $request): JsonResponse
    {
        return $this->jsonOk($this->trackerService->updateDetails($this->payload($request)));
    }

    public function data(Request $request): JsonResponse
    {
        return $this->jsonOk($this->trackerService->data($this->payload($request)));
    }

    public function updateSubTask(Request $request): JsonResponse
    {
        return $this->jsonOk($this->trackerService->updateSubTaskProgress($this->payload($request)));
    }

    public function update(Request $request): JsonResponse
    {
        return $this->jsonOk($this->trackerService->updateParentProgress($this->payload($request)));
    }

    public function log(Request $request): JsonResponse
    {
        $trackerId = trim((string) $request->query('trackerId', ''));
        if ($trackerId === '') {
            throw new OhsDashboardException('trackerId wajib diisi.');
        }

        return $this->jsonOk($this->trackerService->parentLog($trackerId));
    }

    public function subtaskLog(Request $request): JsonResponse
    {
        $subTaskId = trim((string) $request->query('subTaskId', ''));
        if ($subTaskId === '') {
            throw new OhsDashboardException('subTaskId wajib diisi.');
        }

        return $this->jsonOk($this->trackerService->subTaskLog($subTaskId));
    }
}
