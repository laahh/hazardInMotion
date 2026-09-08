<?php

declare(strict_types=1);

namespace App\Http\Controllers\PembatasanLV;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PembatasanLV\Concerns\ProvidesPembatasanLVInputasiFormContext;
use App\Http\Controllers\PembatasanLV\Concerns\ProvidesPembatasanLVLayout;
use App\Models\PembatasanLvInputasi;
use App\Models\PembatasanOrangInputasi;
use App\Services\PembatasanLV\PembatasanLVControlRoomContextService;
use App\Services\PembatasanLV\PembatasanLVOverviewService;
use App\Services\PembatasanLV\PembatasanLVShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PembatasanLVController extends Controller
{
    use ProvidesPembatasanLVLayout;
    use ProvidesPembatasanLVInputasiFormContext;

    public function __construct(
        private readonly PembatasanLVOverviewService $overviewService,
        private readonly PembatasanLVShiftService $shiftService,
        private readonly PembatasanLVControlRoomContextService $controlRoomContext,
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $filters = [
            'site' => trim((string) $request->query('site', '')),
            'tanggal' => $request->query('tanggal', now()->toDateString()),
            'control_room' => trim((string) $request->query('control_room', '')),
        ];

        $dashboard = $this->overviewService->dashboardPayload($user, $filters);

        return view('PembatasanLV.index', [
            'navActive' => 'overview',
            'navItems' => $this->pembatasanLvNavItems(),
            'filters' => $filters,
            'sites' => $dashboard['sites'],
            'controlRooms' => $dashboard['controlRooms'],
            'supervisedRooms' => $dashboard['supervisedRooms'],
            'lvMasukAktif' => $dashboard['lvMasukAktif'],
            'lvKeluar' => $dashboard['lvKeluar'],
            'lvMasukAktifList' => $dashboard['lvMasukAktifList'],
            'lvAllList' => $dashboard['lvAllList'],
            'orangMasukAktif' => $dashboard['orangMasukAktif'],
            'orangKeluar' => $dashboard['orangKeluar'],
            'orangMasukAktifList' => $dashboard['orangMasukAktifList'],
            'orangAllList' => $dashboard['orangAllList'],
            'formContext' => $this->pembatasanLvInputasiFormContext($this->shiftService, $this->controlRoomContext, $user),
            'aktivitasOptions' => collect(),
        ]);
    }

    public function checkoutLv(Request $request, PembatasanLvInputasi $inputasi): RedirectResponse
    {
        $user = Auth::user();

        if (! $this->overviewService->userCanManageRecord($user, $inputasi)) {
            abort(403, 'Anda tidak berwenang checkout LV di control room ini.');
        }

        if ($inputasi->checkout_at !== null) {
            return back()->with('error', 'LV ini sudah di-checkout sebelumnya.');
        }

        $now = now()->timezone(config('app.timezone'));

        $inputasi->update([
            'checkout_at' => $now,
            'checkout_by_id' => $user?->id,
            'checkout_by_name' => (string) ($user?->name ?? '—'),
        ]);

        return back()->with('success', 'Checkout LV '.$inputasi->no_lambung.' berhasil pada '.$now->format('d M Y H:i').'.');
    }

    public function checkoutOrang(Request $request, PembatasanOrangInputasi $inputasi): RedirectResponse
    {
        $user = Auth::user();

        if (! $this->overviewService->userCanManageOrangRecord($user, $inputasi)) {
            abort(403, 'Anda tidak berwenang checkout orang di control room ini.');
        }

        if ($inputasi->checkout_at !== null) {
            return back()->with('error', 'Orang ini sudah di-checkout sebelumnya.');
        }

        $now = now()->timezone(config('app.timezone'));

        $inputasi->update([
            'checkout_at' => $now,
            'checkout_by_id' => $user?->id,
            'checkout_by_name' => (string) ($user?->name ?? '—'),
        ]);

        return back()->with('success', 'Checkout '.$inputasi->nama.' (SID: '.$inputasi->sid.') berhasil pada '.$now->format('d M Y H:i').'.');
    }

    public function lvMasukAktifData(Request $request): JsonResponse
    {
        $user = Auth::user();
        $filters = [
            'site' => trim((string) $request->query('site', '')),
            'tanggal' => $request->query('tanggal', now()->toDateString()),
            'control_room' => trim((string) $request->query('control_room', '')),
        ];

        $now = now()->timezone(config('app.timezone'));

        $rows = $this->overviewService
            ->lvMasukAktifQuery($user, $filters)
            ->select(PembatasanLVOverviewService::LV_LIVE_COLUMNS)
            ->limit(100)
            ->get()
            ->map(fn (PembatasanLvInputasi $row) => [
                'id' => $row->id,
                'nama_driver' => $row->nama_driver,
                'no_lambung' => $row->no_lambung,
                'checkin_at' => $row->checkin_at?->timezone(config('app.timezone'))->toIso8601String(),
                'lokasi' => $row->lokasi,
                'detail_lokasi' => $row->detail_lokasi,
                'durasi_detik' => $row->checkin_at
                    ? (int) $row->checkin_at->timezone(config('app.timezone'))->diffInSeconds($now)
                    : 0,
            ])
            ->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $rows->count(),
                'server_now' => $now->toIso8601String(),
            ],
        ]);
    }

    public function orangMasukAktifData(Request $request): JsonResponse
    {
        $user = Auth::user();
        $filters = [
            'site' => trim((string) $request->query('site', '')),
            'tanggal' => $request->query('tanggal', now()->toDateString()),
            'control_room' => trim((string) $request->query('control_room', '')),
        ];

        $now = now()->timezone(config('app.timezone'));

        $rows = $this->overviewService
            ->orangMasukAktifQuery($user, $filters)
            ->select(PembatasanLVOverviewService::ORANG_LIVE_COLUMNS)
            ->limit(100)
            ->get()
            ->map(fn (PembatasanOrangInputasi $row) => [
                'id' => $row->id,
                'sid' => $row->sid,
                'nama' => $row->nama,
                'nama_perusahaan' => $row->nama_perusahaan,
                'checkin_at' => $row->checkin_at?->timezone(config('app.timezone'))->toIso8601String(),
                'lokasi' => $row->lokasi,
                'detail_lokasi' => $row->detail_lokasi,
                'durasi_detik' => $row->checkin_at
                    ? (int) $row->checkin_at->timezone(config('app.timezone'))->diffInSeconds($now)
                    : 0,
            ])
            ->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $rows->count(),
                'server_now' => $now->toIso8601String(),
            ],
        ]);
    }
}
