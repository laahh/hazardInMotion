<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\ControlRoom\ControlRoomDashboardSapDetailRequest;
use App\Services\ControlRoom\ControlRoomSapDutyReader;
use App\Services\ControlRoom\DashboardMockDataProvider;
use App\Services\ControlRoom\DashboardScheduleWeekAssembler;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * plan-OCR.md T6.1-T6.8 — kerangka, filter, dan MOCKUP visual panel KPI.
 *
 * Panel KPI sungguhan menunggu tabel agregasi Fase 5 + snapshot SAP Fase 4
 * (T0.1 sudah selesai — lihat plan-OCR.md 0.6 — tapi desain final Fase 5
 * masih menunggu keputusan reuse mv_sap_scorecard_mingguan, dan beberapa
 * sumber lain seperti Sheet ID TBC belum ada — lihat Lampiran D #23/#27).
 * Panel KPI, ranking, Pareto, dan kualitas masih MOCKUP (DashboardMockDataProvider).
 * Panel Penjadwalan memakai Jadwal Rencana + Absen nyata; default filter = minggu lalu.
 */
final class DashboardController extends Controller
{
    public function index(
        Request $request,
        DashboardMockDataProvider $mock,
        DashboardScheduleWeekAssembler $scheduleWeek,
    ): View {
        $site = ControlRoomSiteCode::from($request->string('site', ControlRoomSiteCode::HeadOffice->value)->toString());
        $previousWeekStart = CarbonImmutable::now()
            ->setISODate((int) now()->isoWeekYear(), (int) now()->isoWeek(), 1)
            ->subWeek();
        $year = (int) $request->integer('year', (int) $previousWeekStart->isoWeekYear());
        $week = (int) $request->integer('week', (int) $previousWeekStart->isoWeek());
        $week = max(1, min(53, $week));

        $weekStart = CarbonImmutable::now()->setISODate($year, $week, 1)->startOfDay();
        $weekEnd = $weekStart->addDays(6)->endOfDay();
        $prevWeekStart = $weekStart->subWeek();
        $nextWeekStart = $weekStart->addWeek();
        $schedule = $scheduleWeek->build($site, $weekStart);

        return view('control-room.dashboard.index', [
            'site' => $site,
            'year' => (int) $weekStart->isoWeekYear(),
            'week' => (int) $weekStart->isoWeek(),
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'prevYear' => (int) $prevWeekStart->isoWeekYear(),
            'prevWeek' => (int) $prevWeekStart->isoWeek(),
            'nextYear' => (int) $nextWeekStart->isoWeekYear(),
            'nextWeek' => (int) $nextWeekStart->isoWeek(),
            'sites' => ControlRoomSiteCode::cases(),
            'mock' => $mock->build($weekStart, $schedule['days']),
            'schedule' => $schedule,
        ]);
    }

    public function sapDetail(ControlRoomDashboardSapDetailRequest $request, ControlRoomSapDutyReader $reader): JsonResponse
    {
        $shift = ControlRoomShiftCode::from($request->validated('shift'));

        return response()->json($reader->forDuty(
            $request->validated('sid'),
            CarbonImmutable::parse($request->validated('date')),
            $shift,
        ));
    }
}
