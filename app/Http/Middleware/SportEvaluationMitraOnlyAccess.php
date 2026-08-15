<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SportEvaluation\SportEvaluationAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * User Mitra Kerja (assignment aktif, non-manager) hanya boleh ke /evaluasi-well/mitra
 * dan /evaluasi-well/pvt (+ detail karyawan).
 */
final class SportEvaluationMitraOnlyAccess
{
    public function __construct(
        private readonly SportEvaluationAccessService $accessService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $this->accessService->isMitraOnlyUser($user)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($this->accessService->isMitraAllowedRoute($routeName)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Akses terbatas ke dashboard Mitra Kerja dan Evaluasi PVT.',
            ], 403);
        }

        return redirect()->route('evaluasi-well.mitra.index');
    }
}
