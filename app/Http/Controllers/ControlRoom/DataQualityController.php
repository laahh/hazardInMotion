<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\ControlRoom\ControlRoomDataQualityIndexRequest;
use App\Services\ControlRoom\ControlRoomDataQualityService;
use App\Services\ControlRoom\ControlRoomIsoWeekPeriod;
use App\Services\ControlRoom\ControlRoomSiteDutyBoardService;
use Illuminate\View\View;

final class DataQualityController extends Controller
{
    public function index(
        ControlRoomDataQualityIndexRequest $request,
        ControlRoomDataQualityService $dataQuality,
    ): View {
        $period = ControlRoomIsoWeekPeriod::fromRequest($request);
        $site = $request->siteFilter();
        $payload = $dataQuality->build($period->start, $site);

        $boardSites = [];
        foreach (ControlRoomSiteDutyBoardService::BOARD_SITE_CODES as $code) {
            $boardSites[] = ControlRoomSiteCode::from($code);
        }

        return view('control-room.data-quality.index', [
            ...$period->viewData(),
            'site' => $site,
            'boardSites' => $boardSites,
            'quality' => $payload,
        ]);
    }
}
