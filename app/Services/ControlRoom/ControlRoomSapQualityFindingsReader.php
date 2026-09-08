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

    private const QUERY_TIMEOUT_MS = 4000;

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
        $cacheKey = 'control-room:sap-quality-findings:v2:'.hash(
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
     * Satu statement per chunk: BitmapAnd SID+tanggal, tanpa TOAST foto/deskripsi.
     *
     * @param  list<string>  $sids
     * @return list<array<string, mixed>>|null
     */
    private function fetchChunk(array $sids, CarbonImmutable $start, CarbonImmutable $end): ?array
    {
        $placeholders = implode(',', array_fill(0, count($sids), '?'));
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
                ORDER BY id_oak, tanggal_submit
            ) oak
        ";

        $range = [$start->toDateTimeString(), $end->toDateTimeString()];
        $bindings = [...$sids, ...$range, ...$sids, ...$range, ...$sids, ...$range];

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
