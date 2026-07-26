<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Facades\DB;

/**
 * Pemeriksa koneksi MCU metabolik via OLAP Postgres (pgsql_ssh).
 * Tunnel: setup-ssh-tunnel.bat / start-bemcu-tunnel.bat → localhost:5433.
 */
final class McuConnectionService
{
    public const CONNECTION = 'pgsql_ssh';

    /**
     * Cek apakah koneksi Postgres OLAP (MCU) hidup. Read-only.
     */
    public function isUp(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            DB::connection(self::CONNECTION)->select('SELECT 1');

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Mapping tabel/identity/kondisi metabolik di config sudah cukup untuk query produksi.
     */
    public function isMappingReady(): bool
    {
        $table = trim((string) config('bemcu.table', ''));
        $examDate = trim((string) config('bemcu.exam_date', ''));
        $sid = trim((string) config('bemcu.identity.sid', ''));
        $nik = trim((string) config('bemcu.identity.nik', ''));
        $labs = config('bemcu.labs', []);
        $jsonFields = config('bemcu.json_fields', []);

        if ($table === '' || $examDate === '') {
            return false;
        }

        if ($sid === '' && $nik === '') {
            return false;
        }

        if (! is_array($jsonFields) || $jsonFields === []) {
            return false;
        }

        foreach (['glucose', 'cholesterol', 'triglyceride', 'uric_acid'] as $key) {
            $names = $labs[$key] ?? [];
            if (is_array($names) && $names !== []) {
                return true;
            }
            if (is_string($names) && trim($names) !== '') {
                return true;
            }
        }

        return false;
    }

    public function isConfigured(): bool
    {
        $database = trim((string) config('database.connections.'.self::CONNECTION.'.database', ''));
        $username = trim((string) config('database.connections.'.self::CONNECTION.'.username', ''));

        return $database !== '' && $username !== '';
    }
}
