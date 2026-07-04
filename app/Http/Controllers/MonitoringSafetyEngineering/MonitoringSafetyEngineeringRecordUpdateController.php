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
        $periodYear = (int) $request->get('period_year', $currentYear);

        return view('MonitoringSafetyEngginering.records.update', $this->monitoringSafetyEngineeringViewData('data-update', [
            'tablesReady' => Schema::hasTable('monitoring_safety_engineering_records'),
            'periodYear' => $periodYear,
            'planYears' => range($currentYear - 1, $currentYear + 2),
            'gridConfig' => $this->gridService->gridConfig(),
        ]));
    }

    public function records(Request $request): JsonResponse
    {
        if (! Schema::hasTable('monitoring_safety_engineering_records')) {
            return response()->json(['message' => 'Tabel database belum tersedia.'], 422);
        }

        $periodYear = $request->filled('period_year') ? (int) $request->get('period_year') : null;

        return response()->json([
            'data' => $this->gridService->fetchRows($periodYear),
        ]);
    }

    public function save(MonitoringSafetyEngineeringRecordGridRequest $request): JsonResponse
    {
        if (! Schema::hasTable('monitoring_safety_engineering_records')) {
            return response()->json(['message' => 'Tabel database belum tersedia.'], 422);
        }

        $validated = $request->validated();
        $result = $this->gridService->bulkSave(
            $validated['rows'],
            (int) $validated['period_year'],
        );

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
            'errors' => $result['errors'],
        ], $result['errors'] !== [] && ($result['created'] + $result['updated']) === 0 ? 422 : 200);
    }

    public function history(int $recordId): JsonResponse
    {
        if (! Schema::hasTable('monitoring_safety_engineering_records')) {
            return response()->json(['message' => 'Tabel database belum tersedia.'], 422);
        }

        $history = $this->gridService->fetchChangeHistory($recordId);

        if (! ($history['found'] ?? false)) {
            return response()->json(['message' => 'Record tidak ditemukan.'], 404);
        }

        return response()->json($history);
    }
}
