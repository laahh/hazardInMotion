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

        if (! $this->olap->isReachable()) {
            return ['loaded' => false, 'findings' => []];
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

    /**
     * Pasangan lokasi+detil per hari yang muncul di SAP pada jendela minggu (semua pelapor).
     *
     * @return array{loaded: bool, findings: list<array{lokasi: string, detil_lokasi: string, at: string}>}
     */
    public function locationHits(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! $this->olap->isReachable()) {
            return ['loaded' => false, 'findings' => []];
        }

        $from = $start->startOfDay();
        $until = CarbonImmutable::parse($end);
        $merged = [];
        $ok = 0;
        foreach (['hazard', 'observasi', 'oak'] as $source) {
            $rows = $this->fetchLocationHitsSource($source, $from, $until);
            if ($rows === null) {
                continue;
            }
            $ok++;
            $merged = [...$merged, ...$rows];
        }

        if ($ok === 0) {
            return ['loaded' => false, 'findings' => []];
        }

        return ['loaded' => true, 'findings' => $this->collapseLocationHits($merged)];
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
     * @return list<array{lokasi: string, detil_lokasi: string, at: string}>|null
     */
    private function fetchLocationHitsSource(string $source, CarbonImmutable $from, CarbonImmutable $until): ?array
    {
        $cacheKey = 'control-room:sap-location-hits:v5:'.$source.':'.$from->toDateTimeString().'|'.$until->toDateTimeString();
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            /** @var list<array{lokasi: string, detil_lokasi: string, at: string}> */
            return $cached;
        }

        $sql = match ($source) {
            'hazard' => <<<'SQL'
                SELECT lokasi, detil_lokasi, MAX(tanggal_laporan) AS at
                FROM bcbeats.mv_inspeksi_hazard
                WHERE tanggal_laporan >= CAST(? AS timestamp)
                  AND tanggal_laporan < CAST(? AS timestamp)
                GROUP BY lokasi, detil_lokasi, CAST(tanggal_laporan AS date)
                SQL,
            'observasi' => <<<'SQL'
                SELECT lokasi, detil_lokasi, MAX(tanggal_observasi) AS at
                FROM bcbeats.mv_observasi
                WHERE tanggal_observasi >= CAST(? AS timestamp)
                  AND tanggal_observasi < CAST(? AS timestamp)
                GROUP BY lokasi, detil_lokasi, CAST(tanggal_observasi AS date)
                SQL,
            'oak' => <<<'SQL'
                SELECT lokasi, detil_lokasi, MAX(tanggal_submit) AS at
                FROM bcbeats.mv_oak
                WHERE tanggal_submit >= CAST(? AS timestamp)
                  AND tanggal_submit < CAST(? AS timestamp)
                  AND peran_dalam_tim = 'OBSERVEE'
                GROUP BY lokasi, detil_lokasi, CAST(tanggal_submit AS date)
                SQL,
            default => null,
        };
        if ($sql === null) {
            return null;
        }

        try {
            $rows = $this->olap->select($sql, [$from->toDateTimeString(), $until->toDateTimeString()], self::LOCATION_HITS_TIMEOUT_MS, [
                'jit' => 'off',
                'work_mem' => '64MB',
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

        Cache::put($cacheKey, $findings, self::LOCATION_HITS_CACHE_SECONDS);

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
