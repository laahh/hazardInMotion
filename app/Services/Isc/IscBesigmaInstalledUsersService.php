<?php

declare(strict_types=1);

namespace App\Services\Isc;

use App\Services\Besigma\BesigmaConnectionService;
use App\Services\Besigma\BesigmaSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Total user yang "sudah install" Besigma: user aktif & belum dihapus di
 * besigma_db.users yang setidaknya pernah punya 1 baris di
 * besigma_db.user_gps_logs (bukti aplikasi pernah terpasang & pernah kirim GPS).
 *
 * Query didorong dari sisi users (~63rb baris, lebih kecil) pakai EXISTS
 * (semi-join) — BUKAN JOIN + DISTINCT dari user_gps_logs (~2 juta baris) —
 * supaya tetap ringan. Hasil di-cache lama (30 menit): angka ini berubah
 * lambat dan query-nya jauh lebih berat dari snapshot POB/reconcile lain
 * yang cache-nya cuma 10 detik.
 */
final class IscBesigmaInstalledUsersService
{
    private const CACHE_KEY = 'isc.besigma.installed_users_total.v1';

    private const CACHE_TTL_SECONDS = 1800;

    public function __construct(
        private readonly BesigmaConnectionService $connection,
    ) {}

    public function isUp(): bool
    {
        return $this->connection->isUp();
    }

    /**
     * @return int|null null berarti besigma_db sedang tidak terjangkau/gagal.
     */
    public function total(bool $fresh = false): ?int
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_int($cached)) {
            return $cached;
        }

        if (! $this->isUp()) {
            return null;
        }

        try {
            $row = DB::connection(BesigmaConnectionService::CONNECTION)->selectOne(
                'SELECT count(*) AS total
                 FROM '.BesigmaSchema::qualify('users').' u
                 WHERE '.BesigmaSchema::flagIsTrue('u.is_active').'
                   AND '.BesigmaSchema::flagIsFalse('u.is_deleted').'
                   AND u.deleted_at IS NULL
                   AND EXISTS (
                        SELECT 1
                        FROM '.BesigmaSchema::qualify('user_gps_logs').' g
                        WHERE g.user_id = u.id
                   )'
            );

            $total = (int) ($row->total ?? 0);
            Cache::put(self::CACHE_KEY, $total, self::CACHE_TTL_SECONDS);

            return $total;
        } catch (Throwable $e) {
            report($e);
            $this->connection->rememberFailure($e);

            return null;
        }
    }
}
