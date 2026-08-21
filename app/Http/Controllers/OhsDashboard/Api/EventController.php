<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsDashboard\Api;

use App\Exceptions\OhsDashboard\OhsDashboardException;
use App\Services\OhsDashboard\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EventController extends OhsDashboardApiController
{
    public function __construct(private readonly EventService $eventService) {}

    public function create(Request $request): JsonResponse
    {
        return $this->jsonOk($this->eventService->create($this->payload($request)), 201);
    }

    public function update(Request $request): JsonResponse
    {
        return $this->jsonOk($this->eventService->update($this->payload($request)));
    }

    public function readiness(Request $request): JsonResponse
    {
        return $this->jsonOk($this->eventService->updateReadiness($this->payload($request)));
    }

    public function makerData(Request $request): JsonResponse
    {
        return $this->jsonOk($this->eventService->makerData($this->payload($request)));
    }

    public function checkinInfo(Request $request): JsonResponse
    {
        $eventId = trim((string) $request->query('eventId', ''));
        if ($eventId === '') {
            throw new OhsDashboardException('Event tidak ditemukan atau QR sudah tidak berlaku.');
        }

        return $this->jsonOk($this->eventService->checkinInfo($eventId));
    }

    public function checkin(Request $request): JsonResponse
    {
        return $this->jsonOk($this->eventService->checkin($this->payload($request)));
    }

    public function attendance(Request $request): JsonResponse
    {
        $eventId = trim((string) $request->query('eventId', ''));
        if ($eventId === '') {
            throw new OhsDashboardException('eventId wajib diisi.');
        }

        return $this->jsonOk($this->eventService->attendanceSummary($eventId));
    }

    public function minutes(Request $request): JsonResponse
    {
        $eventId = trim((string) $request->query('eventId', ''));
        if ($eventId === '') {
            throw new OhsDashboardException('eventId wajib diisi.');
        }

        return $this->jsonOk($this->eventService->minutes($eventId));
    }

    public function saveMinutes(Request $request): JsonResponse
    {
        return $this->jsonOk($this->eventService->saveMinutes($this->payload($request)));
    }

    public function addActionItem(Request $request): JsonResponse
    {
        return $this->jsonOk($this->eventService->addActionItem($this->payload($request)), 201);
    }

    public function updateActionItemStatus(Request $request): JsonResponse
    {
        return $this->jsonOk($this->eventService->updateActionItemStatus($this->payload($request)));
    }
}
