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
use App\Services\ControlRoom\ControlRoomSapDetailTbcEnricher;
use App\Services\ControlRoom\ControlRoomSapDutyReader;
use App\Services\ControlRoom\ControlRoomSapPhotoResolver;
use App\Services\ControlRoom\ControlRoomSapWeekCountsReader;
use App\Services\ControlRoom\ControlRoomSiteDutyBoardService;
use App\Services\ControlRoom\DashboardMockDataProvider;
use App\Services\ControlRoom\DashboardScheduleWeekAssembler;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        ControlRoomReplacementAttendanceService $replacementAttendance,
        ControlRoomLocationCoverageService $locationCoverage,
    ): View {
        if (Cache::add('control-room:dash-duty-checkins', 1, 60)) {
            $replacementAttendance->ensureDutyDateCheckins();
        }
        $site = ControlRoomSiteCode::from($request->string('site', ControlRoomSiteCode::HeadOffice->value)->toString());
        $period = ControlRoomIsoWeekPeriod::fromRequest($request);
        $weekStart = $period->start;
        $weekEnd = $period->end;
        $prev = $period->previous();
        // RFID hanya untuk minggu yang ditampilkan (detail roster check-in/out).
        // Minggu sebelumnya cukup untuk delta KPI — tanpa query RFID.
        $schedule = $scheduleWeek->build($site, $weekStart, withRfid: true);
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
        $previousSap = $sapWeekCounts->cachedForScheduleDays($previousSchedule['days'], withFindings: false)
            ?? ['loaded' => false, 'counts' => [], 'findings' => []];

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
            'coverageUrl' => route('control-room.dashboard.coverage', [
                'site' => $site->value,
                'year' => $period->year,
                'week' => $period->week,
                'iso_week' => $period->isoWeekValue(),
            ]),
            'coverageDate' => $locationCoverage->defaultDailyDate($weekStart),
        ]);
    }

    public function coverage(Request $request, ControlRoomLocationCoverageService $locationCoverage): View
    {
        $site = ControlRoomSiteCode::from($request->string('site', ControlRoomSiteCode::HeadOffice->value)->toString());
        $period = ControlRoomIsoWeekPeriod::fromRequest($request);
        $mode = $request->string('mode', 'daily')->toString() === 'weekly' ? 'weekly' : 'daily';
        set_time_limit($mode === 'weekly' ? 30 : 15);
        $selectedDate = $request->input('date');
        $payload = $locationCoverage->build(
            $site,
            $period->start,
            now(),
            is_string($selectedDate) ? $selectedDate : null,
            $mode,
        );
        $coverageScope = $site === ControlRoomSiteCode::HeadOffice
            ? 'Semua site operasi'
            : $site->label();
        $partial = $request->string('partial', '')->toString();
        if ($partial === 'daily') {
            return view('control-room.dashboard.partials.coverage-mode', [
                'mode' => 'daily',
                'panel' => $payload['daily'],
                'visible' => true,
                'weekRangeLabel' => $period->rangeLabel(),
                'coverageScope' => $coverageScope,
            ]);
        }
        if ($partial === 'weekly') {
            return view('control-room.dashboard.partials.coverage-mode', [
                'mode' => 'weekly',
                'panel' => $payload['weekly'],
                'visible' => true,
                'weekRangeLabel' => $period->rangeLabel(),
                'coverageScope' => $coverageScope,
            ]);
        }

        return view('control-room.dashboard.partials.coverage-section', [
            'locationCoverage' => $payload,
            'weekRangeLabel' => $period->rangeLabel(),
            'site' => $site,
        ]);
    }

    public function sapDetail(
        ControlRoomDashboardSapDetailRequest $request,
        ControlRoomSapDutyReader $reader,
        ControlRoomSapDetailTbcEnricher $tbc,
    ): JsonResponse {
        $shift = ControlRoomShiftCode::from($request->validated('shift'));

        return response()->json($tbc->enrich($reader->forDuty(
            $request->validated('sid'),
            CarbonImmutable::parse($request->validated('date')),
            $shift,
        )));
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
