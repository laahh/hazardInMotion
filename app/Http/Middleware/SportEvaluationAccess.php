<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SportEvaluation\SportEvaluationAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportEvaluationAccess
{
    public function __construct(
        private readonly SportEvaluationAccessService $accessService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->accessService->canAccessModule($request->user())) {
            abort(403, 'Anda tidak memiliki akses ke modul Evaluasi Olahraga & Aktivitas.');
        }

        return $next($request);
    }
}
