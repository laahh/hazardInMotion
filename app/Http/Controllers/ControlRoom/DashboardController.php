<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\ControlRoom\ControlRoomDashboardSapDetailRequest;
use App\Http\Requests\ControlRoom\ControlRoomDashboardSapPhotosRequest;
use App\Services\ControlRoom\ControlRoomDashboardInsightsAssembler;
use App\Services\ControlRoom\ControlRoomIsoWeekPeriod;
use App\Services\ControlRoom\ControlRoomLocationCoverageService;
use App\Services\ControlRoom\ControlRoomReplacementAttendanceService;
use App\Services\ControlRoom\ControlRoomSapDutyReader;
use App\Services\ControlRoom\ControlRoomSapPhotoResolver;
use App\Services\ControlRoom\ControlRoomSapWeekCountsReader;
use App\Services\ControlRoom\ControlRoomSiteDutyBoardService;
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
 * Pencapaian Personil, KPI header, Pareto, Highlight, dan Kualitas memakai jadwal + OBDS/HSECM.
 * Panel Penjadwalan memakai Jadwal Rencana + Absen nyata; default filter = minggu lalu.
 */
final class DashboardController extends Controller
{
    public function index(
        Request $request,
        DashboardMockDataProvider $mock,
        DashboardScheduleWeekAssembler $scheduleWeek,
        ControlRoomSapWeekCountsReader $sapWeekCounts,
        ControlRoomDashboardInsightsAssembler $insightsAssembler,
        ControlRoomSiteDutyBoardService $siteDutyBoard,
        ControlRoomLocationCoverageService $locationCoverage,
        ControlRoomReplacementAttendanceService $replacementAttendance,
    ): View {
        $replacementAttendance->ensureDutyDateCheckins();
        $site = ControlRoomSiteCode::from($request->string('site', ControlRoomSiteCode::HeadOffice->value)->toString());
        $period = ControlRoomIsoWeekPeriod::fromRequest($request);
        $weekStart = $period->start;
        $weekEnd = $period->end;
        $prev = $period->previous();
        $schedule = $scheduleWeek->build($site, $weekStart);
        $sapWeek = $sapWeekCounts->forScheduleDays($schedule['days']);
        $insights = $insightsAssembler->build(
            $site,
            $weekStart,
            $weekEnd,
            $schedule['days'],
            $sapWeek['findings'] ?? [],
            $sapWeek['loaded'],
        );
        $previousSchedule = $scheduleWeek->build($site, $prev->start, withRfid: false);
        $previousSap = $sapWeekCounts->forScheduleDays($previousSchedule['days'], withFindings: false);

        return view('control-room.dashboard.index', [
            ...$period->viewData(),
            'site' => $site,
            'sites' => ControlRoomSiteCode::cases(),
            'mock' => $mock->build(
                $weekStart,
                $schedule['days'],
                $sapWeek['counts'],
                $sapWeek['loaded'],
                $insights,
                $previousSchedule['days'],
                $previousSap['counts'],
                $previousSap['loaded'],
            ),
            'schedule' => $schedule,
            'siteBoard' => $siteDutyBoard->build(),
            'locationCoverage' => $locationCoverage->build($site, $weekStart),
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

    public function sapPhotos(ControlRoomDashboardSapPhotosRequest $request, ControlRoomSapPhotoResolver $photos): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'success' => true,
            'data' => $photos->resolve(
                (int) $validated['id'],
                (string) ($validated['kind'] ?? ControlRoomSapPhotoResolver::KIND_PHOTOCAR),
            ),
        ]);
    }
}
