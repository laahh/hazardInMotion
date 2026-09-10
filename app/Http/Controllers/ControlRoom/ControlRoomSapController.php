<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\ControlRoom\ControlRoomSapIndexRequest;
use App\Services\ControlRoom\ControlRoomIsoWeekPeriod;
use App\Services\ControlRoom\ControlRoomSapTableService;
use App\Services\ControlRoom\ControlRoomSiteDutyBoardService;
use Illuminate\View\View;

final class ControlRoomSapController extends Controller
{
    public function index(
        ControlRoomSapIndexRequest $request,
        ControlRoomSapTableService $sapTable,
    ): View {
        $period = ControlRoomIsoWeekPeriod::fromRequest($request);
        $site = $request->siteFilter();
        $payload = $sapTable->build($period->start, $site);

        $boardSites = [];
        foreach (ControlRoomSiteDutyBoardService::BOARD_SITE_CODES as $code) {
            $boardSites[] = ControlRoomSiteCode::from($code);
        }

        return view('control-room.sap.index', [
            ...$period->viewData(),
            'site' => $site,
            'boardSites' => $boardSites,
            'sap' => $payload,
        ]);
    }
}
