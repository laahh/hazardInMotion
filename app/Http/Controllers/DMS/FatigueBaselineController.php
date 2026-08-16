<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Services\Dms\FatigueBaselineService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * /dms/fatigue-baseline-static — baseline personal per operator dari pola
 * alert DMS (bcsid.mv_dms_alert) + proyeksi kapan kemungkinan mencapai ambang
 * risiko. Lihat FatigueBaselineService untuk metodologi lengkap.
 */
final class FatigueBaselineController extends Controller
{
    public function __construct(
        private readonly FatigueBaselineService $service,
    ) {}

    public function index(Request $request): View
    {
        $date = (string) $request->query('date', '');
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = Carbon::now(config('app.timezone'))->toDateString();
        }

        return view('dms.fatigue-baseline-static', $this->service->dashboard($date));
    }

    /**
     * JSON on-demand detail satu operator: riwayat Fatigue Check, PVT, dan
     * alert individual — dipanggil dari panel detail saat operator diklik.
     */
    public function operatorDetail(Request $request, string $sid): JsonResponse
    {
        $date = (string) $request->query('date', '');
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = Carbon::now(config('app.timezone'))->toDateString();
        }

        $sid = mb_substr(trim($sid), 0, 20);
        if ($sid === '') {
            return response()->json(['message' => 'Kode SID tidak valid.'], 422);
        }

        return response()->json($this->service->operatorDetail($sid, $date));
    }
}
