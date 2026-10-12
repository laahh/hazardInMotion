<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\ControlRoom\ControlRoomPengawasIndexRequest;
use App\Http\Requests\ControlRoom\ControlRoomPengawasSapDetailRequest;
use App\Services\ControlRoom\ControlRoomIsoWeekPeriod;
use App\Services\ControlRoom\ControlRoomSapDetailTbcEnricher;
use App\Services\ControlRoom\ControlRoomTbcBasisReader;
use App\Services\ControlRoom\PengawasMonitorService;
use App\Services\ControlRoom\Reference\PengawasRoster;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Monitoring Pengawas Control Room — panel dashboard dari Pencapaian
 * Personil ke bawah + Data Quality, untuk daftar pengawas tetap.
 */
final class PengawasMonitorController extends Controller
{
    public function index(
        ControlRoomPengawasIndexRequest $request,
        PengawasMonitorService $monitor,
        PengawasRoster $roster,
    ): View {
        set_time_limit(60);
        $period = ControlRoomIsoWeekPeriod::fromRequest($request);
        $site = $request->siteFilter();

        return view('control-room.pengawas.index', [
            ...$period->viewData(),
            'site' => $site,
            'siteParam' => $site?->value ?? ControlRoomPengawasIndexRequest::ALL_SITES,
            'sites' => $roster->sites(),
            'monitor' => $monitor->build($site, $period),
        ]);
    }

    public function sapDetail(
        ControlRoomPengawasSapDetailRequest $request,
        PengawasMonitorService $monitor,
        ControlRoomSapDetailTbcEnricher $tbc,
        ControlRoomTbcBasisReader $tbcBasis,
    ): JsonResponse {
        $reader = $monitor->dutyReader();
        $sid = $request->validated('sid');
        $date = CarbonImmutable::parse($request->validated('date'));
        $window = $reader->reportingWindow($date);

        return response()->json($tbc->enrich(
            $reader->forDuty($sid, $date, ControlRoomShiftCode::S1),
            $tbcBasis->cardsForSid($sid, $window['start'], $window['end']),
        ));
    }
}
