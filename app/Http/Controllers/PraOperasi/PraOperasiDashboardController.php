<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Services\PraOperasi\PraOperasiDashboardService;
use App\Services\PraOperasi\PraOperasiOperatorProfileReader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class PraOperasiDashboardController extends Controller
{
    public function __construct(
        private readonly PraOperasiDashboardService $service,
        private readonly PraOperasiOperatorProfileReader $profileReader,
    ) {}

    public function index(Request $request): View
    {
        return view('pra-operasi.dashboard', $this->service->dashboard($request));
    }

    /**
     * JSON profil satu operator (dipanggil dari panel detail saat baris watchlist diklik).
     */
    public function operatorProfile(Request $request, string $kodeSid): JsonResponse
    {
        $date = (string) $request->query('date', '');
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = Carbon::now(config('app.timezone'))->toDateString();
        }

        $kodeSid = mb_substr(trim($kodeSid), 0, 20);
        if ($kodeSid === '') {
            return response()->json(['message' => 'Kode SID tidak valid.'], 422);
        }

        return response()->json($this->profileReader->profile($kodeSid, $date));
    }
}
