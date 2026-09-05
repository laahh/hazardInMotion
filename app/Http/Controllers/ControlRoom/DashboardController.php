<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * plan-OCR.md T6.1 — kerangka & filter dasar. Panel KPI (T6.2 dst) menunggu
 * tabel agregasi Fase 5, yang menunggu snapshot SAP Fase 4, yang menunggu
 * T0.1 (verifikasi mv_inspeksi_hazard dkk) — lihat plan-OCR.md 0.5 poin 2.
 * Controller ini sengaja hanya menyediakan kerangka + filter, belum ada
 * data KPI (tidak boleh fabrikasi angka).
 */
final class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $site = ControlRoomSiteCode::from($request->string('site', ControlRoomSiteCode::HeadOffice->value)->toString());
        $year = (int) $request->integer('year', (int) now()->isoFormat('GGGG'));
        $week = (int) $request->integer('week', (int) now()->isoWeek());

        return view('control-room.dashboard.index', [
            'site' => $site,
            'year' => $year,
            'week' => $week,
            'sites' => ControlRoomSiteCode::cases(),
        ]);
    }
}
