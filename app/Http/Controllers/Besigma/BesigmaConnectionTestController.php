<?php

declare(strict_types=1);

namespace App\Http\Controllers\Besigma;

use App\Http\Controllers\Controller;
use App\Services\Besigma\BesigmaConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman tes apakah jumphost Besigma (setup-ssh-tunnel-besigma.bat) hidup.
 */
final class BesigmaConnectionTestController extends Controller
{
    public function __construct(
        private readonly BesigmaConnectionService $connection,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $probe = $this->connection->probe();

        if ($request->wantsJson()) {
            return response()->json($probe, $probe['connected'] ? 200 : 503);
        }

        return view('besigma.connection-test', [
            'probe' => $probe,
        ]);
    }
}
