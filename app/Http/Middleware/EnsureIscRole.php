<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIscRole
{
    public function handle(Request $request, Closure $next, string ...$roleSlugs): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Anda harus login untuk mengakses modul ISC.');
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }
        foreach ($roleSlugs as $slug) {
            if ($user->hasRole($slug)) {
                return $next($request);
            }
        }
        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }
}
