<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server lama: setiap URL menampilkan pemberitahuan bahwa aplikasi sudah pindah.
 * Aktif hanya bila APP_PINDAH_KE (config app.pindah_ke) berisi URL yang valid.
 */
class SitusPindah
{
    public function handle(Request $request, Closure $next): Response
    {
        $tujuan = config('app.pindah_ke');

        if (! filter_var($tujuan, FILTER_VALIDATE_URL)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Aplikasi sudah pindah ke '.$tujuan, 'pindah_ke' => $tujuan], 410);
        }

        return response()->view('pindah-situs', ['tujuan' => $tujuan]);
    }
}
