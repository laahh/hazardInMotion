<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Facades\DB;

/**
 * Pemeriksa ketersediaan koneksi ke DB BeWell (bewell_db) yang diakses lewat
 * SSH tunnel manual (start-bewell-tunnel.bat, port 3316). Dipakai agar halaman
 * tetap tampil (degradasi anggun) saat tunnel down, bukan error 500.
 * Method isUp() hanya melakukan SELECT 1 (tidak menulis).
 */
final class BewellConnectionService
{
    public const CONNECTION = 'bewell_db';

    /**
     * Cek apakah koneksi bewell_db hidup. Hanya SELECT 1, tidak menulis apa pun.
     */
    public function isUp(): bool
    {
        try {
            DB::connection(self::CONNECTION)->select('SELECT 1');

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
