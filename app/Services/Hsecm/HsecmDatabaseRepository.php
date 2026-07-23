<?php

declare(strict_types=1);

namespace App\Services\Hsecm;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Sumber data HSECM dari tabel hasil scraping Tableau (prefix scr_hsecm_*).
 *
 * Status terkini = filter batch_slot (bukan COUNT seluruh tabel).
 * Data di-cache singkat; cache key menyertakan batch_slot / mode.
 */
class HsecmDatabaseRepository
{
    private const CACHE_PREFIX = 'hsecm.db.';

    private const CACHE_VERSION = 'v2';

    private const CACHE_TTL_SECONDS = 300;

    /** @var array<string, bool> */
    private array $columnSupportCache = [];

    /**
     * Ambil seluruh baris tabel sebagai array asosiatif (key = nama kolom DB).
     * Legacy / mode all — prefer rowsForBatchSlot untuk status terkini.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $table): array
    {
        return Cache::remember(self::cacheKey($table, 'all'), self::CACHE_TTL_SECONDS, function () use ($table): array {
            return $this->mapRows(
                DB::table($table)->orderBy('id')->get()
            );
        });
    }

    public function hasBatchSlotSupport(string $table): bool
    {
        return $this->tableHasColumn($table, 'batch_slot');
    }

    public function hasBusinessKeySupport(string $table): bool
    {
        return $this->tableHasColumn($table, 'business_key');
    }

    public function hasGapCountSupport(string $table): bool
    {
        return $this->tableHasColumn($table, 'gap_count');
    }

    /**
     * Slot batch terbaru di tabel, atau null jika kolom belum ada / tabel kosong.
     */
    public function latestBatchSlot(string $table): ?string
    {
        if (! $this->hasBatchSlotSupport($table)) {
            return null;
        }

        return Cache::remember(self::cacheKey($table, 'latest_slot'), self::CACHE_TTL_SECONDS, function () use ($table): ?string {
            $max = DB::table($table)->max('batch_slot');

            return $this->normalizeSlot($max);
        });
    }

    /**
     * Slot sebelum $batchSlot (MAX di bawah slot tersebut).
     */
    public function previousBatchSlot(string $table, string $batchSlot): ?string
    {
        if (! $this->hasBatchSlotSupport($table)) {
            return null;
        }

        $normalized = $this->normalizeSlot($batchSlot);
        if ($normalized === null) {
            return null;
        }

        return Cache::remember(
            self::cacheKey($table, 'prev_slot.'.$normalized),
            self::CACHE_TTL_SECONDS,
            function () use ($table, $normalized): ?string {
                $prev = DB::table($table)
                    ->where('batch_slot', '<', $normalized)
                    ->max('batch_slot');

                return $this->normalizeSlot($prev);
            }
        );
    }

    /**
     * Resolusi slot target berdasarkan jam cut-off (00/06/12/18) pada tanggal referensi.
     * Ambil MAX(batch_slot) yang <= target, agar scrape terlambat tetap terpakai.
     */
    public function resolveBatchSlotAtOrBefore(string $table, Carbon $target): ?string
    {
        if (! $this->hasBatchSlotSupport($table)) {
            return null;
        }

        $targetStr = $target->format('Y-m-d H:i:s');

        return Cache::remember(
            self::cacheKey($table, 'slot_le.'.$targetStr),
            self::CACHE_TTL_SECONDS,
            function () use ($table, $targetStr): ?string {
                $slot = DB::table($table)
                    ->where('batch_slot', '<=', $targetStr)
                    ->max('batch_slot');

                return $this->normalizeSlot($slot);
            }
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rowsForBatchSlot(string $table, ?string $batchSlot = null): array
    {
        if (! $this->hasBatchSlotSupport($table)) {
            return $this->rows($table);
        }

        $slot = $batchSlot !== null ? $this->normalizeSlot($batchSlot) : $this->latestBatchSlot($table);
        if ($slot === null) {
            return [];
        }

        return Cache::remember(
            self::cacheKey($table, 'slot.'.$slot),
            self::CACHE_TTL_SECONDS,
            function () use ($table, $slot): array {
                return $this->mapRows(
                    DB::table($table)
                        ->where('batch_slot', $slot)
                        ->orderBy('id')
                        ->get()
                );
            }
        );
    }

    /**
     * Item still-open: intersection business_key vs slot sebelumnya.
     * Fallback tanpa previous: gap_count >= 2, atau seluruh snapshot jika gap_count tidak ada.
     *
     * @return list<array<string, mixed>>
     */
    public function rowsStillOpen(string $table, ?string $currentSlot = null, ?string $previousSlot = null): array
    {
        if (! $this->hasBatchSlotSupport($table)) {
            throw new RuntimeException(
                "Kolom batch_slot belum ada di tabel {$table}. Jalankan alter SQL scrap HSECM dulu."
            );
        }

        $current = $currentSlot !== null
            ? $this->normalizeSlot($currentSlot)
            : $this->latestBatchSlot($table);

        if ($current === null) {
            return [];
        }

        $prev = $previousSlot !== null
            ? $this->normalizeSlot($previousSlot)
            : $this->previousBatchSlot($table, $current);

        $currentRows = $this->rowsForBatchSlot($table, $current);

        if ($prev === null) {
            if ($this->hasGapCountSupport($table)) {
                return array_values(array_filter(
                    $currentRows,
                    static fn (array $row): bool => (int) ($row['gap_count'] ?? 0) >= 2
                ));
            }

            return $currentRows;
        }

        if (! $this->hasBusinessKeySupport($table)) {
            if ($this->hasGapCountSupport($table)) {
                return array_values(array_filter(
                    $currentRows,
                    static fn (array $row): bool => (int) ($row['gap_count'] ?? 0) >= 2
                ));
            }

            return $currentRows;
        }

        $prevKeys = [];
        foreach ($this->rowsForBatchSlot($table, $prev) as $row) {
            $key = trim((string) ($row['business_key'] ?? ''));
            if ($key !== '') {
                $prevKeys[$key] = true;
            }
        }

        return array_values(array_filter(
            $currentRows,
            static function (array $row) use ($prevKeys): bool {
                $key = trim((string) ($row['business_key'] ?? ''));

                return $key !== '' && isset($prevKeys[$key]);
            }
        ));
    }

    public function forgetCache(string $table): void
    {
        Cache::forget(self::cacheKey($table, 'all'));
        Cache::forget(self::cacheKey($table, 'latest_slot'));
        // Slot-specific keys expire via TTL; bump version if hard flush needed.
    }

    public function forgetAllTableCaches(string $table): void
    {
        $this->forgetCache($table);
        unset(
            $this->columnSupportCache[$table.'.batch_slot'],
            $this->columnSupportCache[$table.'.business_key'],
            $this->columnSupportCache[$table.'.gap_count'],
        );
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = $table.'.'.$column;
        if (! array_key_exists($cacheKey, $this->columnSupportCache)) {
            try {
                $this->columnSupportCache[$cacheKey] = Schema::hasColumn($table, $column);
            } catch (\Throwable) {
                $this->columnSupportCache[$cacheKey] = false;
            }
        }

        return $this->columnSupportCache[$cacheKey];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>|iterable<object>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapRows(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            /** @var array<string, mixed> $arr */
            $arr = (array) $row;
            $arr['_row_id'] = $arr['id'] ?? null;
            $out[] = $arr;
        }

        return $out;
    }

    private function normalizeSlot(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $raw;
        }
    }

    private static function cacheKey(string $table, string $suffix): string
    {
        return self::CACHE_PREFIX.$table.'.'.self::CACHE_VERSION.'.'.$suffix;
    }
}
