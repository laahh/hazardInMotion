<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\ControlRoom\ControlRoomDataQualityIndexRequest;
use App\Services\ControlRoom\ControlRoomDataQualityService;
use App\Services\ControlRoom\ControlRoomSiteDutyBoardService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

final class DataQualityController extends Controller
{
    public function index(
        ControlRoomDataQualityIndexRequest $request,
        ControlRoomDataQualityService $dataQuality,
    ): View {
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
        $site = $request->siteFilter();
        $payload = $dataQuality->build($weekStart, $site);

        $boardSites = [];
        foreach (ControlRoomSiteDutyBoardService::BOARD_SITE_CODES as $code) {
            $boardSites[] = ControlRoomSiteCode::from($code);
        }

        return view('control-room.data-quality.index', [
            'site' => $site,
            'boardSites' => $boardSites,
            'year' => (int) $weekStart->isoWeekYear(),
            'week' => (int) $weekStart->isoWeek(),
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'prevYear' => (int) $prevWeekStart->isoWeekYear(),
            'prevWeek' => (int) $prevWeekStart->isoWeek(),
            'nextYear' => (int) $nextWeekStart->isoWeekYear(),
            'nextWeek' => (int) $nextWeekStart->isoWeek(),
            'quality' => $payload,
        ]);
    }
}
