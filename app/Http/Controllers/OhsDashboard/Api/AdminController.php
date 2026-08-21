<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsDashboard\Api;

use App\Services\OhsDashboard\EmailDigestService;
use App\Services\OhsDashboard\HseSyncService;
use App\Services\OhsDashboard\OverdueReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminController extends OhsDashboardApiController
{
    public function __construct(
        private readonly EmailDigestService $emailDigestService,
        private readonly OverdueReminderService $overdueReminderService,
        private readonly HseSyncService $hseSyncService,
    ) {}

    public function emailSettings(): JsonResponse
    {
        return $this->jsonOk($this->emailDigestService->settings());
    }

    public function saveEmailSettings(Request $request): JsonResponse
    {
        return $this->jsonOk($this->emailDigestService->save($this->payload($request)));
    }

    public function emailSend(): JsonResponse
    {
        return $this->jsonOk($this->emailDigestService->sendNow());
    }

    public function emailTest(): JsonResponse
    {
        return $this->jsonOk($this->emailDigestService->sendNow(true));
    }

    public function overdueReminderSend(): JsonResponse
    {
        return $this->jsonOk($this->overdueReminderService->sendNow());
    }

    public function hseSyncNow(): JsonResponse
    {
        return $this->jsonOk($this->hseSyncService->syncNow());
    }

    public function installCron(): JsonResponse
    {
        return $this->jsonOk([
            'message' => 'Cron Apps Script tidak dipakai. Gunakan Laravel Scheduler: ohs-dashboard:digest, ohs-dashboard:overdue-reminder, ohs-dashboard:hse-sync.',
        ]);
    }

    public function removeCron(): JsonResponse
    {
        return $this->jsonOk([
            'message' => 'Tidak ada cron Apps Script yang perlu dihapus. Matikan schedule di app/Console/Kernel.php jika diperlukan.',
        ]);
    }
}
