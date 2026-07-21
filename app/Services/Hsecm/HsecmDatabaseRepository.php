<?php

declare(strict_types=1);

namespace App\Services\Hsecm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sumber data HSECM dari tabel hasil scraping Tableau (prefix scr_hsecm_*).
 *
 * Data di-cache singkat karena tabel relatif kecil (< 1 MB) dan seluruh proses
 * filter/agregasi dilakukan di level aplikasi (mirror pola repository JSON lama).
 */
class HsecmDatabaseRepository
{
    private const CACHE_PREFIX = 'hsecm.db.';

    private const CACHE_VERSION = 'v1';

    private const CACHE_TTL_SECONDS = 300;

    /**
     * Ambil seluruh baris tabel sebagai array asosiatif (key = nama kolom DB).
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $table): array
    {
        return Cache::remember(self::cacheKey($table), self::CACHE_TTL_SECONDS, function () use ($table): array {
            return DB::table($table)
                ->orderBy('id')
                ->get()
                ->map(function (object $row): array {
                    /** @var array<string, mixed> $arr */
                    $arr = (array) $row;
                    $arr['_row_id'] = $arr['id'] ?? null;

                    return $arr;
                })
                ->all();
        });
    }

    public function forgetCache(string $table): void
    {
        Cache::forget(self::cacheKey($table));
    }

    private static function cacheKey(string $table): string
    {
        return self::CACHE_PREFIX.$table.'.'.self::CACHE_VERSION;
    }
}
