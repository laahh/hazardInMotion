<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Http\Controllers\Controller;
use App\Services\ControlRoom\DashboardMockDataProvider;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * plan-OCR.md T6.1-T6.8 — kerangka, filter, dan MOCKUP visual panel KPI.
 *
 * Panel KPI sungguhan menunggu tabel agregasi Fase 5 + snapshot SAP Fase 4
 * (T0.1 sudah selesai — lihat plan-OCR.md 0.6 — tapi desain final Fase 5
 * masih menunggu keputusan reuse mv_sap_scorecard_mingguan, dan beberapa
 * sumber lain seperti Sheet ID TBC belum ada — lihat Lampiran D #23/#27).
 * Sampai itu selesai, dashboard menampilkan data MOCKUP (DashboardMockDataProvider)
 * supaya ada gambaran visual layout — bukan angka asli.
 */
final class DashboardController extends Controller
{
    public function index(Request $request, DashboardMockDataProvider $mock): View
    {
        $site = ControlRoomSiteCode::from($request->string('site', ControlRoomSiteCode::HeadOffice->value)->toString());
        $year = (int) $request->integer('year', (int) now()->isoFormat('GGGG'));
        $week = (int) $request->integer('week', (int) now()->isoWeek());

        return view('control-room.dashboard.index', [
            'site' => $site,
            'year' => $year,
            'week' => $week,
            'sites' => ControlRoomSiteCode::cases(),
            'mock' => $mock->build(),
        ]);
    }
}
