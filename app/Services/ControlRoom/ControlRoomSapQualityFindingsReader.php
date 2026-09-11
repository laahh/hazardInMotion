<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Temuan SAP ringan untuk Data Quality: SID di-chunk, 1 round-trip/chunk
 * (UNION ALL 3 sumber), flag kedalaman tanpa tarik url_foto/teks panjang.
 */
final class ControlRoomSapQualityFindingsReader
{
    public const SID_CHUNK = 12;

    private const CACHE_SECONDS = 180;

    private const LOCATION_HITS_CACHE_SECONDS = 300;

    private const LOCATION_HITS_PAST_CACHE_SECONDS = 21600;

    private const LOCATION_HITS_STALE_SECONDS = 86400;

    private const QUERY_TIMEOUT_MS = 4000;

    private const LOCATION_HITS_TIMEOUT_MS = 6000;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
        private readonly ControlRoomSapDutyReader $dutyWindow,
    ) {}

    /**
     * @param  list<string>  $sids
     * @return array{loaded: bool, findings: list<array<string, mixed>>}
     */
    public function forSids(array $sids, CarbonImmutable $from, CarbonImmutable $lastDutyDate): array
    {
        $sids = array_values(array_unique(array_filter(array_map(
            static fn (string $sid): string => strtoupper(trim($sid)),
            $sids,
        ))));
        if ($sids === []) {
            return ['loaded' => true, 'findings' => []];
        }

        sort($sids);
        $start = $from->startOfDay();
        $end = $this->dutyWindow->reportingWindow($lastDutyDate)['end'];
        $cacheKey = 'control-room:sap-quality-findings:v5:'.hash(
            'sha1',
            implode(',', $sids).'|'.$start->toDateTimeString().'|'.$end->toDateTimeString(),
        );
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['findings'])) {
            return ['loaded' => true, 'findings' => $cached['findings']];
        }

        if (! $this->olap->isReachable()) {
            return ['loaded' => false, 'findings' => []];
        }

        $findings = [];
        $chunksOk = 0;
        foreach (array_chunk($sids, self::SID_CHUNK) as $chunk) {
            $chunkFindings = $this->fetchChunk($chunk, $start, $end);
            if ($chunkFindings === null) {
                continue;
            }
            $chunksOk++;
            $findings = [...$findings, ...$chunkFindings];
        }

        if ($chunksOk === 0) {
            return ['loaded' => false, 'findings' => []];
        }

        Cache::put($cacheKey, ['findings' => $findings], self::CACHE_SECONDS);

        return ['loaded' => true, 'findings' => $findings];
    }

    public static function locationHitsDayCacheKey(string $date): string
    {
        return 'control-room:sap-location-hits:v7:day:'.$date;
    }

    public static function locationHitsStaleCacheKey(string $date): string
    {
        return 'control-room:sap-location-hits:v7:stale:'.$date;
    }

    /**
     * Pasangan lokasi+detil per hari. Cache per tanggal dulu (hari lalu 6 jam,
     * hari ini 5 menit), baru query OBDS untuk hari yang belum ada. Tiga sumber
     * di-agregat terpisah (index tanggal), tools OCR saja.
     *
     * @return array{loaded: bool, findings: list<array{lokasi: string, detil_lokasi: string, at: string}>}
     */
    public function locationHits(CarbonImmutable $start, CarbonImmutable $end, int $cacheSeconds = self::LOCATION_HITS_CACHE_SECONDS): array
    {
        $from = $start->startOfDay();
        $until = CarbonImmutable::parse($end);
        $days = $this->datesInRange($from, $until);
        if ($days === []) {
            return ['loaded' => true, 'findings' => []];
        }

        $byDay = [];
        $missing = [];
        foreach ($days as $date) {
            $cached = Cache::get(self::locationHitsDayCacheKey($date));
            if (is_array($cached)) {
                $byDay[$date] = $cached;
                continue;
            }
            $missing[] = $date;
        }

        if ($missing !== []) {
            $fetched = $this->fetchAndStoreMissingDays($missing, $cacheSeconds);
            foreach ($fetched as $date => $rows) {
                $byDay[$date] = $rows;
            }
        }

        $findings = [];
        foreach ($days as $date) {
            foreach ($byDay[$date] ?? [] as $row) {
                $findings[] = $row;
            }
        }

        if ($byDay !== [] || $missing === []) {
            return ['loaded' => true, 'findings' => $findings];
        }

        return ['loaded' => false, 'findings' => []];
    }

    /**
     * Satu kunci lokasi+detil+hari, timestamp terakhir hari itu.
     *
     * @param  list<array{lokasi: string, detil_lokasi: string, at: string}>  $findings
     * @return list<array{lokasi: string, detil_lokasi: string, at: string}>
     */
    public function collapseLocationHits(array $findings): array
    {
        $best = [];
        foreach ($findings as $finding) {
            $lokasi = trim((string) ($finding['lokasi'] ?? ''));
            $detil = trim((string) ($finding['detil_lokasi'] ?? ''));
            $at = trim((string) ($finding['at'] ?? ''));
            if ($at === '' || ($lokasi === '' && $detil === '')) {
                continue;
            }
            try {
                $date = CarbonImmutable::parse($at)->toDateString();
            } catch (Throwable) {
                continue;
            }
            $key = $lokasi."\n".$detil."\n".$date;
            if (! isset($best[$key]) || $at > $best[$key]['at']) {
                $best[$key] = [
                    'lokasi' => $lokasi,
                    'detil_lokasi' => $detil,
                    'at' => $at,
                ];
            }
        }

        return array_values($best);
    }

    /**
     * @param  list<string>  $missing
     * @return array<string, list<array{lokasi: string, detil_lokasi: string, at: string}>>
     */
    private function fetchAndStoreMissingDays(array $missing, int $cacheSeconds): array
    {
        $stored = [];
        $rangeStart = CarbonImmutable::parse($missing[0])->startOfDay();
        $rangeEnd = CarbonImmutable::parse($missing[array_key_last($missing)])->addDay()->startOfDay();
        $rows = $this->fetchLocationHits($rangeStart, $rangeEnd);

        if ($rows === null) {
            foreach ($missing as $date) {
                $stale = Cache::get(self::locationHitsStaleCacheKey($date));
                if (is_array($stale)) {
                    $stored[$date] = $stale;
                }
            }

            return $stored;
        }

        $byDay = [];
        foreach ($this->collapseLocationHits($rows) as $finding) {
            try {
                $date = CarbonImmutable::parse($finding['at'])->toDateString();
            } catch (Throwable) {
                continue;
            }
            $byDay[$date][] = $finding;
        }

        $now = CarbonImmutable::now()->startOfDay();
        foreach ($missing as $date) {
            $hits = $byDay[$date] ?? [];
            $this->rememberDayHits($date, $hits, $cacheSeconds, CarbonImmutable::parse($date)->lt($now));
            $stored[$date] = $hits;
        }

        return $stored;
    }

    /**
     * @param  list<array{lokasi: string, detil_lokasi: string, at: string}>  $hits
     */
    private function rememberDayHits(string $date, array $hits, int $cacheSeconds, bool $isPast): void
    {
        $ttl = $isPast
            ? self::LOCATION_HITS_PAST_CACHE_SECONDS
            : max(self::LOCATION_HITS_CACHE_SECONDS, min($cacheSeconds, self::LOCATION_HITS_PAST_CACHE_SECONDS));
        Cache::put(self::locationHitsDayCacheKey($date), $hits, $ttl);
        Cache::put(self::locationHitsStaleCacheKey($date), $hits, self::LOCATION_HITS_STALE_SECONDS);
    }

    /**
     * @return list<string>
     */
    private function datesInRange(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $days = [];
        $cursor = $from->startOfDay();
        $end = $until->greaterThan($until->startOfDay()) ? $until->startOfDay()->addDay() : $until->startOfDay();
        while ($cursor->lt($end)) {
            $days[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * Tiga query terpisah (index tanggal + GROUP BY), tools OCR. Satu sumber
     * timeout tidak membatalkan yang lain.
     *
     * @return list<array{lokasi: string, detil_lokasi: string, at: string}>|null
     */
    private function fetchLocationHits(CarbonImmutable $from, CarbonImmutable $until): ?array
    {
        $tools = ControlRoomInspeksiHazardToolFilter::sqlPredicate();
        $range = [$from->toDateTimeString(), $until->toDateTimeString()];
        $ok = 0;
        $findings = [];

        $sources = [
            'hazard' => [
                'sql' => "
                    SELECT lokasi, detil_lokasi, MAX(tanggal_laporan) AS at
                    FROM bcbeats.mv_inspeksi_hazard
                    WHERE tanggal_laporan >= CAST(? AS timestamp)
                      AND tanggal_laporan < CAST(? AS timestamp)
                      AND {$tools['sql']}
                    GROUP BY lokasi, detil_lokasi, CAST(tanggal_laporan AS date)
                ",
                'bindings' => [...$range, ...$tools['bindings']],
            ],
            'observasi' => [
                'sql' => "
                    SELECT lokasi, detil_lokasi, MAX(tanggal_observasi) AS at
                    FROM bcbeats.mv_observasi
                    WHERE tanggal_observasi >= CAST(? AS timestamp)
                      AND tanggal_observasi < CAST(? AS timestamp)
                      AND {$tools['sql']}
                    GROUP BY lokasi, detil_lokasi, CAST(tanggal_observasi AS date)
                ",
                'bindings' => [...$range, ...$tools['bindings']],
            ],
            'oak' => [
                'sql' => "
                    SELECT lokasi, detil_lokasi, MAX(tanggal_submit) AS at
                    FROM bcbeats.mv_oak
                    WHERE tanggal_submit >= CAST(? AS timestamp)
                      AND tanggal_submit < CAST(? AS timestamp)
                      AND {$tools['sql']}
                      AND peran_dalam_tim = 'OBSERVEE'
                    GROUP BY lokasi, detil_lokasi, CAST(tanggal_submit AS date)
                ",
                'bindings' => [...$range, ...$tools['bindings']],
            ],
        ];

        foreach ($sources as $source => $query) {
            $rows = $this->selectLocationHits($source, $query['sql'], $query['bindings']);
            if ($rows === null) {
                continue;
            }
            $ok++;
            $findings = [...$findings, ...$rows];
        }

        return $ok === 0 ? null : $findings;
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<array{lokasi: string, detil_lokasi: string, at: string}>|null
     */
    private function selectLocationHits(string $source, string $sql, array $bindings): ?array
    {
        try {
            $rows = $this->olap->select($sql, $bindings, self::LOCATION_HITS_TIMEOUT_MS, [
                'jit' => 'off',
                'work_mem' => '32MB',
                'max_parallel_workers_per_gather' => '0',
            ]);
        } catch (Throwable $e) {
            Log::warning('ControlRoom SAP location hits '.$source.' gagal: '.$e->getMessage());

            return null;
        }

        $findings = [];
        foreach ($rows as $row) {
            $at = trim((string) ($row->at ?? ''));
            if ($at === '') {
                continue;
            }
            try {
                $at = CarbonImmutable::parse($at)->toDateTimeString();
            } catch (Throwable) {
                continue;
            }
            $lokasi = trim((string) ($row->lokasi ?? ''));
            $detil = trim((string) ($row->detil_lokasi ?? ''));
            if ($lokasi === '' && $detil === '') {
                continue;
            }
            $findings[] = [
                'lokasi' => $lokasi,
                'detil_lokasi' => $detil,
                'at' => $at,
            ];
        }

        return $findings;
    }

    /**
     * Satu statement per chunk: BitmapAnd SID+tanggal, tanpa TOAST foto/deskripsi.
     *
     * @param  list<string>  $sids
     * @return list<array<string, mixed>>|null
     */
    private function fetchChunk(array $sids, CarbonImmutable $start, CarbonImmutable $end): ?array
    {
        $placeholders = implode(',', array_fill(0, count($sids), '?'));
        $tools = ControlRoomInspeksiHazardToolFilter::sqlPredicate();
        $sql = "
            SELECT sid, at, component, category, lokasi, detil_lokasi, report_id, has_text, has_photo, has_geo
            FROM (
                SELECT DISTINCT ON (id_laporan)
                    kode_sid_pelapor AS sid,
                    tanggal_laporan AS at,
                    CASE
                        WHEN POSITION('INSPEKSI' IN UPPER(COALESCE(jenis_laporan, ''))) > 0 THEN 'inspeksi'
                        WHEN POSITION('HAZARD' IN UPPER(COALESCE(jenis_laporan, ''))) > 0 THEN 'hazard'
                        ELSE NULL
                    END AS component,
                    COALESCE(
                        NULLIF(BTRIM(COALESCE(subketidaksesuaian, '')), ''),
                        NULLIF(BTRIM(COALESCE(ketidaksesuaian, '')), ''),
                        ''
                    ) AS category,
                    lokasi,
                    detil_lokasi,
                    CAST(id_laporan AS text) AS report_id,
                    (deskripsi_temuan IS NOT NULL) AS has_text,
                    (url_foto IS NOT NULL) AS has_photo,
                    (latitude IS NOT NULL AND longitude IS NOT NULL) AS has_geo
                FROM bcbeats.mv_inspeksi_hazard
                WHERE kode_sid_pelapor IN ({$placeholders})
                  AND tanggal_laporan >= CAST(? AS timestamp)
                  AND tanggal_laporan < CAST(? AS timestamp)
                  AND {$tools['sql']}
                ORDER BY id_laporan, tanggal_laporan
            ) hazard
            WHERE component IS NOT NULL

            UNION ALL

            SELECT sid, at, component, category, lokasi, detil_lokasi, report_id, has_text, has_photo, has_geo
            FROM (
                SELECT DISTINCT ON (id_observasi)
                    kode_sid_pelapor AS sid,
                    tanggal_observasi AS at,
                    'observasi'::text AS component,
                    COALESCE(
                        NULLIF(BTRIM(COALESCE(jenis_kegiatan, '')), ''),
                        NULLIF(BTRIM(COALESCE(tools_observasi, '')), ''),
                        ''
                    ) AS category,
                    lokasi,
                    detil_lokasi,
                    CAST(id_observasi AS text) AS report_id,
                    (catatan_observasi IS NOT NULL) AS has_text,
                    (url_foto IS NOT NULL) AS has_photo,
                    (latitude IS NOT NULL AND longitude IS NOT NULL) AS has_geo
                FROM bcbeats.mv_observasi
                WHERE kode_sid_pelapor IN ({$placeholders})
                  AND tanggal_observasi >= CAST(? AS timestamp)
                  AND tanggal_observasi < CAST(? AS timestamp)
                  AND {$tools['sql']}
                ORDER BY id_observasi, tanggal_observasi
            ) observasi

            UNION ALL

            SELECT sid, at, component, category, lokasi, detil_lokasi, report_id, has_text, has_photo, has_geo
            FROM (
                SELECT DISTINCT ON (id_oak)
                    kode_sid_pelapor AS sid,
                    tanggal_submit AS at,
                    'oak'::text AS component,
                    COALESCE(
                        NULLIF(BTRIM(COALESCE(sub_aktivitas, '')), ''),
                        NULLIF(BTRIM(COALESCE(aktivitas, '')), ''),
                        ''
                    ) AS category,
                    lokasi,
                    detil_lokasi,
                    CAST(id_oak AS text) AS report_id,
                    (kesimpulan IS NOT NULL) AS has_text,
                    (url_foto IS NOT NULL) AS has_photo,
                    (latitude IS NOT NULL AND longitude IS NOT NULL) AS has_geo
                FROM bcbeats.mv_oak
                WHERE kode_sid_pelapor IN ({$placeholders})
                  AND tanggal_submit >= CAST(? AS timestamp)
                  AND tanggal_submit < CAST(? AS timestamp)
                  AND {$tools['sql']}
                  AND peran_dalam_tim = 'OBSERVEE'
                ORDER BY id_oak, tanggal_submit
            ) oak
        ";

        $range = [$start->toDateTimeString(), $end->toDateTimeString()];
        $bindings = [
            ...$sids, ...$range, ...$tools['bindings'],
            ...$sids, ...$range, ...$tools['bindings'],
            ...$sids, ...$range, ...$tools['bindings'],
        ];

        try {
            $rows = $this->olap->select($sql, $bindings, self::QUERY_TIMEOUT_MS);
        } catch (Throwable $e) {
            Log::warning('ControlRoom SAP quality findings chunk gagal: '.$e->getMessage());

            return null;
        }

        $findings = [];
        foreach ($rows as $row) {
            $mapped = $this->mapRow($row);
            if ($mapped !== null) {
                $findings[] = $mapped;
            }
        }

        return $findings;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapRow(object $row): ?array
    {
        $component = strtolower(trim((string) ($row->component ?? '')));
        if (! in_array($component, ['hazard', 'inspeksi', 'observasi', 'oak'], true)) {
            return null;
        }
        $sid = strtoupper(trim((string) ($row->sid ?? '')));
        if ($sid === '') {
            return null;
        }
        try {
            $at = CarbonImmutable::parse((string) ($row->at ?? ''));
        } catch (Throwable) {
            return null;
        }

        return [
            'sid' => $sid,
            'at' => $at->toDateTimeString(),
            'component' => $component,
            'category' => trim((string) ($row->category ?? '')),
            'lokasi' => trim((string) ($row->lokasi ?? '')),
            'detil_lokasi' => trim((string) ($row->detil_lokasi ?? '')),
            'report_id' => trim((string) ($row->report_id ?? '')),
            'has_text' => $this->asBool($row->has_text ?? false),
            'has_photo' => $this->asBool($row->has_photo ?? false),
            'has_geo' => $this->asBool($row->has_geo ?? false),
        ];
    }

    private function asBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 't', 'true', 'yes'], true);
    }
}
