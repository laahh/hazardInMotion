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

    private const CACHE_VERSION = 'v3';

    private const CACHE_TTL_SECONDS = 300;

    /** Max slot histori untuk hitung streak consecutive (≈30 hari × 2 slot). */
    private const STREAK_LOOKBACK_SLOTS = 60;

    /** Chunk size untuk whereIn business_key (hindari packet/query terlalu besar). */
    private const BUSINESS_KEY_CHUNK = 500;

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
     * @param  list<string>|null  $columns  null = SELECT * ; list = SELECT kolom tertentu (lebih ringan)
     * @return list<array<string, mixed>>
     */
    public function rowsForBatchSlot(string $table, ?string $batchSlot = null, ?array $columns = null): array
    {
        if (! $this->hasBatchSlotSupport($table)) {
            return $this->rows($table);
        }

        $slot = $batchSlot !== null ? $this->normalizeSlot($batchSlot) : $this->latestBatchSlot($table);
        if ($slot === null) {
            return [];
        }

        $select = $this->normalizeSelectColumns($table, $columns);
        $colKey = $select === null ? 'allcols' : 'cols.'.md5(implode(',', $select));

        return Cache::remember(
            self::cacheKey($table, 'slot.'.$slot.'.'.$colKey),
            self::CACHE_TTL_SECONDS,
            function () use ($table, $slot, $select): array {
                $query = DB::table($table)->where('batch_slot', $slot)->orderBy('id');
                if ($select !== null) {
                    $query->select($select);
                }

                return $this->mapRows($query->get());
            }
        );
    }

    /**
     * Nilai unik kolom (opsional dibatasi ke satu batch_slot) — untuk filter dropdown.
     *
     * @return list<string>
     */
    public function distinctColumnValues(string $table, string $column, ?string $batchSlot = null): array
    {
        if ($column === '' || ! $this->tableHasColumn($table, $column)) {
            return [];
        }

        $slot = $batchSlot !== null ? $this->normalizeSlot($batchSlot) : null;
        $cacheSuffix = 'dist.'.$column.'.'.($slot ?? 'noslot');

        return Cache::remember(
            self::cacheKey($table, $cacheSuffix),
            self::CACHE_TTL_SECONDS,
            function () use ($table, $column, $slot): array {
                $query = DB::table($table)
                    ->whereNotNull($column)
                    ->where($column, '!=', '');

                if ($slot !== null && $this->hasBatchSlotSupport($table)) {
                    $query->where('batch_slot', $slot);
                }

                return $query
                    ->distinct()
                    ->orderBy($column)
                    ->limit(500)
                    ->pluck($column)
                    ->map(static fn ($v): string => trim((string) $v))
                    ->filter(static fn (string $v): bool => $v !== '')
                    ->values()
                    ->all();
            }
        );
    }

    /**
     * Slot terakhir pada tanggal kalender Y-m-d (satu query MAX).
     */
    public function latestBatchSlotOnDate(string $table, string $ymd): ?string
    {
        if (! $this->hasBatchSlotSupport($table) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) !== 1) {
            return null;
        }

        return Cache::remember(
            self::cacheKey($table, 'latest_on.'.$ymd),
            self::CACHE_TTL_SECONDS,
            function () use ($table, $ymd): ?string {
                $max = DB::table($table)
                    ->whereBetween('batch_slot', [$ymd.' 00:00:00', $ymd.' 23:59:59'])
                    ->max('batch_slot');

                return $this->normalizeSlot($max);
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

    /**
     * Streak consecutive: mundur dari $currentSlot per batch_slot scrape.
     * Putus 1 slot → streak berhenti. Termasuk slot sekarang (min 1 jika key ada di slot terkini).
     *
     * @param  list<string>  $businessKeys
     * @return array<string, int> business_key => streak (>= 0)
     */
    public function countConsecutiveStreakByKeys(string $table, array $businessKeys, string $currentSlot): array
    {
        $keys = array_values(array_unique(array_filter(array_map(
            static fn (string $k): string => trim($k),
            $businessKeys
        ), static fn (string $k): bool => $k !== '')));

        if ($keys === [] || ! $this->hasBatchSlotSupport($table) || ! $this->hasBusinessKeySupport($table)) {
            return [];
        }

        $current = $this->normalizeSlot($currentSlot);
        if ($current === null) {
            return [];
        }

        $cacheKey = self::cacheKey(
            $table,
            'streak_consec.v2.'.md5($current.'|'.implode("\n", $keys))
        );

        /** @var array<string, int> $cached */
        $cached = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($table, $keys, $current): array {
            // Hanya lookback terbatas — cukup untuk streak harian; hindari full-table scan histori.
            $rawSlots = DB::table($table)
                ->where('batch_slot', '<=', $current)
                ->distinct()
                ->orderByDesc('batch_slot')
                ->limit(self::STREAK_LOOKBACK_SLOTS)
                ->pluck('batch_slot');

            $slots = [];
            foreach ($rawSlots as $rawSlot) {
                $normalized = $this->normalizeSlot($rawSlot);
                if ($normalized !== null) {
                    $slots[$normalized] = true;
                }
            }
            $slots = array_keys($slots);
            rsort($slots, SORT_STRING);

            if ($slots === []) {
                $out = [];
                foreach ($keys as $key) {
                    $out[$key] = 0;
                }

                return $out;
            }

            $slotMin = $slots[array_key_last($slots)];
            /** @var array<string, array<string, true>> $presence */
            $presence = [];

            foreach (array_chunk($keys, self::BUSINESS_KEY_CHUNK) as $chunk) {
                $appearRows = DB::table($table)
                    ->select(['business_key', 'batch_slot'])
                    ->whereIn('business_key', $chunk)
                    ->whereBetween('batch_slot', [$slotMin, $current])
                    ->get();

                foreach ($appearRows as $row) {
                    $key = trim((string) ($row->business_key ?? ''));
                    $slot = $this->normalizeSlot($row->batch_slot ?? null);
                    if ($key === '' || $slot === null) {
                        continue;
                    }
                    $presence[$key][$slot] = true;
                }
            }

            $out = [];
            foreach ($keys as $key) {
                $streak = 0;
                foreach ($slots as $slot) {
                    if (! empty($presence[$key][$slot])) {
                        $streak++;
                    } else {
                        break;
                    }
                }
                $out[$key] = $streak;
            }

            return $out;
        });

        return $cached;
    }

    /**
     * Jumlah slot sebelumnya dalam streak consecutive (max(0, streak - 1)).
     * Putus 1 batch_slot → reset; bukan total kemunculan historis non-consecutive.
     *
     * @param  list<string>  $businessKeys
     * @return array<string, int> business_key => jumlah slot sebelumnya dalam streak
     */
    public function countPreviousAppearancesByKeys(string $table, array $businessKeys, string $beforeSlot): array
    {
        $streaks = $this->countConsecutiveStreakByKeys($table, $businessKeys, $beforeSlot);
        $out = [];
        foreach ($streaks as $key => $streak) {
            $out[$key] = max(0, (int) $streak - 1);
        }

        return $out;
    }

    /**
     * Tanggal kalender (Y-m-d) yang punya batch_slot, descending.
     *
     * @return list<string>
     */
    public function listDistinctBatchSlotDates(string $table, int $limit = 60): array
    {
        if (! $this->hasBatchSlotSupport($table)) {
            return [];
        }

        $limit = max(1, min(365, $limit));

        return Cache::remember(
            self::cacheKey($table, 'slot_dates.v2.'.$limit),
            self::CACHE_TTL_SECONDS,
            function () use ($table, $limit): array {
                // Hindari GROUP BY DATE(batch_slot) (tidak index-friendly).
                // Ambil slot terbaru lalu unique tanggal di PHP.
                $raw = DB::table($table)
                    ->whereNotNull('batch_slot')
                    ->orderByDesc('batch_slot')
                    ->limit(max(40, $limit * 4))
                    ->pluck('batch_slot');

                $dates = [];
                foreach ($raw as $value) {
                    $normalized = $this->normalizeSlot($value);
                    if ($normalized === null) {
                        continue;
                    }
                    $ymd = substr($normalized, 0, 10);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) === 1) {
                        $dates[$ymd] = true;
                    }
                    if (count($dates) >= $limit) {
                        break;
                    }
                }

                $list = array_keys($dates);
                rsort($list, SORT_STRING);

                return $list;
            }
        );
    }

    /**
     * Semua batch_slot pada tanggal kalender (Y-m-d), ascending.
     *
     * @return list<string>
     */
    public function listBatchSlotsOnDate(string $table, string $ymd): array
    {
        if (! $this->hasBatchSlotSupport($table)) {
            return [];
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) !== 1) {
            return [];
        }

        return Cache::remember(
            self::cacheKey($table, 'slots_on.'.$ymd),
            self::CACHE_TTL_SECONDS,
            function () use ($table, $ymd): array {
                $start = $ymd.' 00:00:00';
                $end = $ymd.' 23:59:59';
                $raw = DB::table($table)
                    ->whereBetween('batch_slot', [$start, $end])
                    ->distinct()
                    ->orderBy('batch_slot')
                    ->pluck('batch_slot');

                $slots = [];
                foreach ($raw as $value) {
                    $normalized = $this->normalizeSlot($value);
                    if ($normalized !== null) {
                        $slots[$normalized] = true;
                    }
                }

                return array_keys($slots);
            }
        );
    }

    /**
     * business_key yang pernah muncul di slot sebelum tanggal Y-m-d (00:00).
     *
     * @param  list<string>  $businessKeys
     * @return array<string, true>
     */
    public function businessKeysPresentBeforeDate(string $table, array $businessKeys, string $ymd): array
    {
        $keys = array_values(array_unique(array_filter(array_map(
            static fn (string $k): string => trim($k),
            $businessKeys
        ), static fn (string $k): bool => $k !== '')));

        if ($keys === [] || ! $this->hasBatchSlotSupport($table) || ! $this->hasBusinessKeySupport($table)) {
            return [];
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) !== 1) {
            return [];
        }

        $before = $ymd.' 00:00:00';
        $out = [];

        foreach (array_chunk($keys, self::BUSINESS_KEY_CHUNK) as $chunk) {
            $rows = DB::table($table)
                ->select('business_key')
                ->whereIn('business_key', $chunk)
                ->where('batch_slot', '<', $before)
                ->distinct()
                ->pluck('business_key');

            foreach ($rows as $key) {
                $trimmed = trim((string) $key);
                if ($trimmed !== '') {
                    $out[$trimmed] = true;
                }
            }
        }

        return $out;
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
     * @param  list<string>|null  $columns
     * @return list<string>|null
     */
    private function normalizeSelectColumns(string $table, ?array $columns): ?array
    {
        if ($columns === null || $columns === []) {
            return null;
        }

        $select = [];
        foreach ($columns as $column) {
            $col = trim((string) $column);
            if ($col === '' || isset($select[$col])) {
                continue;
            }
            if (! $this->tableHasColumn($table, $col)) {
                continue;
            }
            $select[$col] = true;
        }

        if ($select === []) {
            return null;
        }

        // id selalu ikut untuk _row_id / dedupe.
        if ($this->tableHasColumn($table, 'id')) {
            $select = ['id' => true] + $select;
        }

        return array_keys($select);
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
