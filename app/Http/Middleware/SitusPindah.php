<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server lama: setiap URL dialihkan ke alamat baru dengan path & query yang sama
 * (mis. /evaluasi-well → https://besentry.beraucoal.co.id/evaluasi-well).
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

        $alamatBaru = rtrim($tujuan, '/').$request->getRequestUri();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Aplikasi sudah pindah ke '.$tujuan, 'pindah_ke' => $alamatBaru], 410);
        }

        // 302 (bukan 301) agar browser tidak menyimpan pengalihan selamanya bila suatu saat dibatalkan.
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return redirect()->away($alamatBaru, 302);
        }

        // POST/PUT dst. tidak dikirim ulang ke server baru (token CSRF berbeda): tampilkan pemberitahuan.
        return response()->view('pindah-situs', ['tujuan' => $alamatBaru]);
    }
}
