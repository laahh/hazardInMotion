<?php

declare(strict_types=1);

namespace App\Http\Controllers\MonitoringSafetyEngineering;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MonitoringSafetyEngineering\Concerns\ProvidesMonitoringSafetyEngineeringLayout;
use App\Http\Requests\MonitoringSafetyEngineering\MonitoringSafetyEngineeringRecordGridRequest;
use App\Services\MonitoringSafetyEngineering\MonitoringSafetyEngineeringRecordGridService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MonitoringSafetyEngineeringRecordUpdateController extends Controller
{
    use ProvidesMonitoringSafetyEngineeringLayout;

    public function __construct(
        private readonly MonitoringSafetyEngineeringRecordGridService $gridService,
    ) {}

    public function index(Request $request): View
    {
        $currentYear = (int) now()->format('Y');
        $periodYear = $request->filled('period_year') ? (int) $request->get('period_year') : null;
        $gridConfig = $this->gridService->gridConfig();
        $picScope = $gridConfig['pic_scope'] ?? ['scoped' => false];

        return view('MonitoringSafetyEngginering.records.update', $this->monitoringSafetyEngineeringViewData('data-update', [
            'tablesReady' => Schema::hasTable('monitoring_safety_engineering_records'),
            'periodYear' => $periodYear,
            'currentYear' => $currentYear,
            'planYears' => range($currentYear - 1, $currentYear + 2),
            'gridConfig' => $gridConfig,
            'picScope' => $picScope,
        ]));
    }

    public function records(Request $request): JsonResponse
    {
        $periodYear = $request->filled('period_year') ? (int) $request->get('period_year') : null;

        try {
            return response()->json(
                ['data' => $this->gridService->fetchRows($periodYear)],
                200,
                [],
                JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE,
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Gagal memuat data. ' . $e->getMessage(),
            ], 500);
        }
    }

    public function save(MonitoringSafetyEngineeringRecordGridRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->gridService->bulkSave(
                $validated['rows'],
                (int) $validated['period_year'],
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Gagal menyimpan data. ' . $e->getMessage(),
            ], 500);
        }

        $message = 'Berhasil menyimpan data: ' . $result['updated'] . ' diperbarui, ' . $result['created'] . ' ditambahkan.';
        if (($result['change_logs_created'] ?? 0) > 0) {
            $message .= ' Log perubahan: ' . $result['change_logs_created'] . ' field tercatat.';
        } elseif (($result['logs_created'] ?? 0) > 0) {
            $message .= ' Status tracing: ' . $result['logs_created'] . ' perubahan tercatat.';
        }

        return response()->json([
            'message' => $message,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'logs_created' => $result['logs_created'] ?? 0,
            'change_logs_created' => $result['change_logs_created'] ?? 0,
            'saved' => $result['saved'] ?? [],
            'errors' => $result['errors'],
        ], $result['errors'] !== [] && ($result['created'] + $result['updated']) === 0 ? 422 : 200);
    }

    public function history(Request $request, int $recordId): JsonResponse
    {
        $field = $request->filled('field') ? (string) $request->get('field') : null;

        try {
            $history = $this->gridService->fetchChangeHistory($recordId, $field);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Gagal memuat riwayat. ' . $e->getMessage(),
            ], 500);
        }

        if (! ($history['found'] ?? false)) {
            return response()->json(['message' => 'Record tidak ditemukan.'], 404);
        }

        return response()->json($history);
    }
}
