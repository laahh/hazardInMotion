<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pemeriksa ketersediaan koneksi ke DB BeWell (bewell_db) yang diakses lewat
 * SSH tunnel manual (start-bewell-tunnel.bat, port 3316). Dipakai agar halaman
 * tetap tampil (degradasi anggun) saat tunnel down, bukan error 500.
 * Method isUp() hanya melakukan SELECT 1 (tidak menulis).
 *
 * Hasil di-cache per request + Redis/file singkat agar tidak memicu N× timeout
 * koneksi (penyebab umum 504 Gateway Timeout).
 */
final class BewellConnectionService
{
    public const CONNECTION = 'bewell_db';

    private const CACHE_KEY = 'evaluasi_well:bewell_is_up_v1';

    private const CACHE_TTL_SECONDS = 20;

    private ?bool $requestCache = null;

    /**
     * Cek apakah koneksi bewell_db hidup. Hanya SELECT 1, tidak menulis apa pun.
     */
    public function isUp(): bool
    {
        if ($this->requestCache !== null) {
            return $this->requestCache;
        }

        try {
            $this->requestCache = (bool) Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL_SECONDS,
                function (): bool {
                    try {
                        DB::connection(self::CONNECTION)->select('SELECT 1');

                        return true;
                    } catch (Throwable $e) {
                        // Jangan report() di sini — dipanggil sangat sering saat tunnel down.
                        return false;
                    }
                }
            );
        } catch (Throwable $e) {
            report($e);
            $this->requestCache = false;
        }

        return $this->requestCache;
    }

    /**
     * Paksa cek ulang (mis. setelah user tahu tunnel baru dinyalakan).
     */
    public function forgetCachedStatus(): void
    {
        $this->requestCache = null;
        Cache::forget(self::CACHE_KEY);
    }
}
