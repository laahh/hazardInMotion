<?php

declare(strict_types=1);

namespace App\Http\Controllers\Besigma;

use App\Http\Controllers\Controller;
use App\Services\Besigma\BesigmaConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Halaman tes apakah jumphost Besigma (setup-ssh-tunnel-besigma.bat) hidup.
 */
final class BesigmaConnectionTestController extends Controller
{
    public function __construct(
        private readonly BesigmaConnectionService $connection,
    ) {}

    public function index(Request $request): View|JsonResponse|Response
    {
        $probe = $this->connection->probe();
        $schema = is_array($probe['schema'] ?? null) ? $probe['schema'] : [];

        if ($request->query('format') === 'text' || $request->routeIs('besigma.connection-test.text')) {
            $body = $probe['connected']
                ? $this->connection->schemaAsText($schema)
                : (string) ($probe['error'] ?? 'besigma_db tidak terhubung')."\n";

            return response($body, $probe['connected'] ? 200 : 503)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        if ($request->wantsJson() || $request->query('json') === '1' || $request->routeIs('besigma.connection-test.json')) {
            return response()->json($probe, $probe['connected'] ? 200 : 503);
        }

        return view('besigma.connection-test', [
            'probe' => $probe,
            'schemaText' => $this->connection->schemaAsText($schema),
        ]);
    }
}
