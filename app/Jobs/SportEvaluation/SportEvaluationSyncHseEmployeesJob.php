<?php

declare(strict_types=1);

namespace App\Jobs\SportEvaluation;

use App\Services\SportEvaluation\SportEvaluationHseEmployeeSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync massal karyawan HSE → BeWell. Karyawan baru ditambahkan; karyawan
 * existing yang sudah tidak ada di roster aktif HSE otomatis dinonaktifkan.
 */
final class SportEvaluationSyncHseEmployeesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** Volume besar + detail per SID — timeout panjang. */
    public int $timeout = 7200;

    public function handle(SportEvaluationHseEmployeeSyncService $syncService): void
    {
        try {
            $summary = $syncService->sync();

            Log::info('SportEvaluationSyncHseEmployeesJob completed', $summary);
        } catch (Throwable $e) {
            report($e);
            Log::error('SportEvaluationSyncHseEmployeesJob failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
