<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Services\PraOperasi\PraOperasiDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PraOperasiDashboardController extends Controller
{
    public function __construct(
        private readonly PraOperasiDashboardService $service,
    ) {}

    public function index(Request $request): View
    {
        return view('pra-operasi.dashboard', $this->service->dashboard($request));
    }
}
