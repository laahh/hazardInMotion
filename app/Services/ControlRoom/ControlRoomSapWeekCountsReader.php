<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Agregat jumlah laporan SAP per SID per tanggal jaga, untuk tabel
 * Pencapaian Personil. Satu batch per minggu (bukan N+1 per baris).
 *
 * Observasi dan OAK mengisi slot yang sama. Jendela sama dengan Detail:
 * hari H s/d akhir H+1.
 */
final class ControlRoomSapWeekCountsReader
{
    public const SID_CHUNK = 16;

    private const CACHE_SECONDS = 300;

    private const PAST_CACHE_SECONDS = 21600;

    private const QUERY_TIMEOUT_MS = 6000;

    public function __construct(
        private readonly PembatasanLVOlapQuery $olap,
        private readonly ControlRoomSapDutyReader $dutyWindow,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $scheduleDays
     * @return array{
     *     loaded: bool,
     *     counts: array<string, array{hazard: int, inspeksi: int, observasi: int}>,
     *     findings: list<array<string, mixed>>
     * }
     */
    public function forScheduleDays(array $scheduleDays, bool $withFindings = true): array
    {
        $empty = ['loaded' => true, 'counts' => [], 'findings' => []];
        $duties = $this->dutiesFromSchedule($scheduleDays);
        if ($duties === []) {
            return $empty;
        }

        if (! $this->olap->isReachable()) {
            return ['loaded' => false, 'counts' => [], 'findings' => []];
        }

        $sids = array_values(array_unique(array_column($duties, 'sid')));
        sort($sids);
        $dates = array_column($duties, 'date');
        sort($dates);
        $cacheKey = $this->cacheKey($sids, $dates, $withFindings);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['counts'], $cached['findings'])) {
            return ['loaded' => true, 'counts' => $cached['counts'], 'findings' => $cached['findings']];
        }

        $rangeStart = CarbonImmutable::parse($dates[0])->startOfDay();
        $rangeEnd = $this->dutyWindow->reportingWindow(CarbonImmutable::parse($dates[array_key_last($dates)]))['end'];

        $chunksOk = 0;
        $findings = [];
        foreach (array_chunk($sids, self::SID_CHUNK) as $chunk) {
            $chunkFindings = $this->fetchChunk($chunk, $rangeStart, $rangeEnd, $withFindings);
            if ($chunkFindings === null) {
                continue;
            }
            $chunksOk++;
            $findings = [...$findings, ...$chunkFindings];
        }

        if ($chunksOk === 0) {
            return ['loaded' => false, 'counts' => [], 'findings' => []];
        }

        $findings = $this->uniqueByReport($findings);

        $events = [];
        foreach ($findings as $finding) {
            $component = (string) $finding['component'];
            $events[] = [
                'sid' => (string) $finding['sid'],
                'at' => CarbonImmutable::parse((string) $finding['at']),
                'component' => $component === 'oak' ? 'observasi' : $component,
            ];
        }

        $counts = $this->countForDuties($events, $duties);
        $onDuty = $withFindings ? $this->findingsOnDuty($findings, $duties) : [];
        Cache::put($cacheKey, ['counts' => $counts, 'findings' => $onDuty], $this->cacheTtl($dates));

        return ['loaded' => true, 'counts' => $counts, 'findings' => $onDuty];
    }

    /**
     * Hasil cache saja — tidak memukul OBDS. Null jika belum pernah sukses di-cache.
     *
     * @param  list<array<string, mixed>>  $scheduleDays
     * @return array{loaded: bool, counts: array<string, array{hazard: int, inspeksi: int, observasi: int}>, findings: list<array<string, mixed>>}|null
     */
    public function cachedForScheduleDays(array $scheduleDays, bool $withFindings = true): ?array
    {
        $duties = $this->dutiesFromSchedule($scheduleDays);
        if ($duties === []) {
            return ['loaded' => true, 'counts' => [], 'findings' => []];
        }
        $sids = array_values(array_unique(array_column($duties, 'sid')));
        sort($sids);
        $dates = array_column($duties, 'date');
        sort($dates);
        $cached = Cache::get($this->cacheKey($sids, $dates, $withFindings));
        if (! is_array($cached) || ! isset($cached['counts'], $cached['findings'])) {
            return null;
        }

        return ['loaded' => true, 'counts' => $cached['counts'], 'findings' => $cached['findings']];
    }

    /**
     * @param  list<string>  $sids
     * @param  list<string>  $dates
     */
    private function cacheKey(array $sids, array $dates, bool $withFindings): string
    {
        return 'control-room:sap-week-counts:v13:'.($withFindings ? 'full' : 'counts').':'.hash(
            'sha1',
            implode(',', $sids).'|'.($dates[0] ?? '').'|'.($dates[array_key_last($dates)] ?? ''),
        );
    }

    /**
     * @param  list<string>  $dates
     */
    private function cacheTtl(array $dates): int
    {
        $last = (string) ($dates[array_key_last($dates)] ?? '');
        if ($last !== '' && $last < CarbonImmutable::now()->toDateString()) {
            return self::PAST_CACHE_SECONDS;
        }

        return self::CACHE_SECONDS;
    }

    /**
     * @param  list<array{sid: string, at: CarbonImmutable, component: string}>  $events
     * @param  list<array{sid: string, date: string}>  $duties
     * @return array<string, array{hazard: int, inspeksi: int, observasi: int}>
     */
    public function countForDuties(array $events, array $duties): array
    {
        $counts = [];
        foreach ($duties as $duty) {
            $key = $this->slotKey($duty['sid'], $duty['date']);
            if (isset($counts[$key])) {
                continue;
            }

            $window = $this->dutyWindow->reportingWindow(CarbonImmutable::parse($duty['date']));
            $bucket = ['hazard' => 0, 'inspeksi' => 0, 'observasi' => 0];
            foreach ($events as $event) {
                if ($event['sid'] !== $duty['sid']) {
                    continue;
                }
                if ($event['at']->lt($window['start']) || $event['at']->gte($window['end'])) {
                    continue;
                }
                $component = $this->slotComponent((string) $event['component']);
                if ($component !== null) {
                    $bucket[$component]++;
                }
            }
            $counts[$key] = $bucket;
        }

        return $counts;
    }

    /**
     * Hanya laporan yang jatuh di jendela jaga H s/d H+1 untuk SID yang dijadwalkan.
     *
     * @param  list<array<string, mixed>>  $findings
     * @param  list<array{sid: string, date: string}>  $duties
     * @return list<array<string, mixed>>
     */
    public function findingsOnDuty(array $findings, array $duties): array
    {
        $windowsBySid = [];
        foreach ($duties as $duty) {
            $window = $this->dutyWindow->reportingWindow(CarbonImmutable::parse($duty['date']));
            $windowsBySid[$duty['sid']][] = $window;
        }

        $onDuty = [];
        foreach ($findings as $finding) {
            $sid = strtoupper(trim((string) ($finding['sid'] ?? '')));
            if ($sid === '' || ! isset($windowsBySid[$sid])) {
                continue;
            }

            $at = $this->parseAt($finding['at'] ?? null);
            if ($at === null) {
                continue;
            }

            foreach ($windowsBySid[$sid] as $window) {
                if ($at->gte($window['start']) && $at->lt($window['end'])) {
                    $onDuty[] = $finding;
                    break;
                }
            }
        }

        return $onDuty;
    }

    public function slotKey(string $sid, string $date): string
    {
        return strtoupper(trim($sid)).'|'.$date;
    }

    /**
     * Observasi dan OAK mengisi slot % SAP yang sama.
     */
    public function slotComponent(string $component): ?string
    {
        return match (strtolower(trim($component))) {
            'hazard' => 'hazard',
            'inspeksi' => 'inspeksi',
            'observasi', 'oak' => 'observasi',
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $scheduleDays
     * @return list<array{sid: string, date: string}>
     */
    private function dutiesFromSchedule(array $scheduleDays): array
    {
        $duties = [];
        foreach ($scheduleDays as $day) {
            $date = (string) ($day['date'] ?? '');
            if ($date === '') {
                continue;
            }
            foreach (['s1', 's2'] as $shiftKey) {
                foreach ($day[$shiftKey] ?? [] as $person) {
                    $sid = strtoupper(trim((string) ($person['sid'] ?? '')));
                    if ($sid === '') {
                        continue;
                    }
                    $duties[] = ['sid' => $sid, 'date' => $date];
                }
            }
        }

        return $duties;
    }

    /**
     * Satu UNION ALL per chunk SID: BitmapAnd SID+tanggal, tanpa DISTINCT ON
     * (dedupe di uniqueByReport). Tools IN sargable.
     *
     * @param  list<string>  $sids
     * @return list<array<string, mixed>>|null
     */
    private function fetchChunk(array $sids, CarbonImmutable $start, CarbonImmutable $end, bool $withDetails): ?array
    {
        $placeholders = implode(',', array_fill(0, count($sids), '?'));
        $tools = ControlRoomInspeksiHazardToolFilter::sqlPredicate();
        $hazardDetail = $withDetails
            ? "COALESCE(NULLIF(BTRIM(COALESCE(subketidaksesuaian, '')), ''), NULLIF(BTRIM(COALESCE(ketidaksesuaian, '')), ''), '') AS category,
                    COALESCE(nama_goldenrule, '') AS golden_rule,
                    COALESCE(lokasi, '') AS lokasi,
                    COALESCE(detil_lokasi, '') AS detil_lokasi,
                    CAST(id_laporan AS text) AS report_id,
                    LEFT(COALESCE(deskripsi_temuan, ''), 400) AS description,
                    COALESCE(nama_pic, '') AS pic,
                    COALESCE(perusahaan_pic, '') AS company,
                    COALESCE(status_laporan, '') AS status"
            : "'' AS category, '' AS golden_rule, '' AS lokasi, '' AS detil_lokasi,
                    CAST(id_laporan AS text) AS report_id,
                    '' AS description, '' AS pic, '' AS company, '' AS status";
        $observasiDetail = $withDetails
            ? "COALESCE(NULLIF(BTRIM(COALESCE(jenis_kegiatan, '')), ''), NULLIF(BTRIM(COALESCE(tools_observasi, '')), ''), '') AS category,
                    '' AS golden_rule,
                    COALESCE(lokasi, '') AS lokasi,
                    COALESCE(detil_lokasi, '') AS detil_lokasi,
                    CAST(id_observasi AS text) AS report_id,
                    '' AS description, '' AS pic, '' AS company, '' AS status"
            : "'' AS category, '' AS golden_rule, '' AS lokasi, '' AS detil_lokasi,
                    CAST(id_observasi AS text) AS report_id,
                    '' AS description, '' AS pic, '' AS company, '' AS status";
        $oakDetail = $withDetails
            ? "COALESCE(NULLIF(BTRIM(COALESCE(sub_aktivitas, '')), ''), NULLIF(BTRIM(COALESCE(aktivitas, '')), ''), '') AS category,
                    '' AS golden_rule,
                    COALESCE(lokasi, '') AS lokasi,
                    COALESCE(detil_lokasi, '') AS detil_lokasi,
                    CAST(id_oak AS text) AS report_id,
                    '' AS description, '' AS pic, '' AS company, '' AS status"
            : "'' AS category, '' AS golden_rule, '' AS lokasi, '' AS detil_lokasi,
                    CAST(id_oak AS text) AS report_id,
                    '' AS description, '' AS pic, '' AS company, '' AS status";

        $sql = "
            SELECT sid, at, component, category, golden_rule, lokasi, detil_lokasi, report_id, description, pic, company, status, name
            FROM (
                SELECT
                    kode_sid_pelapor AS sid,
                    tanggal_laporan AS at,
                    CASE
                        WHEN POSITION('INSPEKSI' IN UPPER(COALESCE(jenis_laporan, ''))) > 0 THEN 'inspeksi'
                        WHEN POSITION('HAZARD' IN UPPER(COALESCE(jenis_laporan, ''))) > 0 THEN 'hazard'
                        ELSE NULL
                    END AS component,
                    {$hazardDetail},
                    COALESCE(nama_pelapor, '') AS name
                FROM bcbeats.mv_inspeksi_hazard
                WHERE kode_sid_pelapor IN ({$placeholders})
                  AND tanggal_laporan >= CAST(? AS timestamp)
                  AND tanggal_laporan < CAST(? AS timestamp)
                  AND {$tools['sql']}
            ) hazard
            WHERE component IS NOT NULL

            UNION ALL

            SELECT
                kode_sid_pelapor AS sid,
                tanggal_observasi AS at,
                'observasi'::text AS component,
                {$observasiDetail},
                COALESCE(nama_pelapor, '') AS name
            FROM bcbeats.mv_observasi
            WHERE kode_sid_pelapor IN ({$placeholders})
              AND tanggal_observasi >= CAST(? AS timestamp)
              AND tanggal_observasi < CAST(? AS timestamp)
              AND {$tools['sql']}

            UNION ALL

            SELECT
                kode_sid_pelapor AS sid,
                tanggal_submit AS at,
                'oak'::text AS component,
                {$oakDetail},
                COALESCE(nama_pelapor, '') AS name
            FROM bcbeats.mv_oak
            WHERE kode_sid_pelapor IN ({$placeholders})
              AND tanggal_submit >= CAST(? AS timestamp)
              AND tanggal_submit < CAST(? AS timestamp)
              AND {$tools['sql']}
              AND peran_dalam_tim = 'OBSERVEE'
        ";

        $range = [$start->toDateTimeString(), $end->toDateTimeString()];
        $bindings = [
            ...$sids, ...$range, ...$tools['bindings'],
            ...$sids, ...$range, ...$tools['bindings'],
            ...$sids, ...$range, ...$tools['bindings'],
        ];

        $rows = $this->select($sql, $bindings, 'union');
        if ($rows === null) {
            return null;
        }

        $findings = [];
        foreach ($rows as $row) {
            $mapped = $this->mapChunkRow($row);
            if ($mapped !== null) {
                $findings[] = $mapped;
            }
        }

        return $findings;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapChunkRow(object $row): ?array
    {
        $rawComponent = strtolower(trim((string) ($row->component ?? '')));
        if (! in_array($rawComponent, ['hazard', 'inspeksi', 'observasi', 'oak'], true)) {
            return null;
        }
        $sid = strtoupper(trim((string) ($row->sid ?? '')));
        $at = $this->parseAt($row->at ?? null);
        if ($sid === '' || $at === null) {
            return null;
        }

        return $this->finding(
            sid: $sid,
            name: (string) ($row->name ?? ''),
            at: $at,
            component: $rawComponent,
            category: (string) ($row->category ?? ''),
            goldenRule: (string) ($row->golden_rule ?? ''),
            lokasi: (string) ($row->lokasi ?? ''),
            detilLokasi: (string) ($row->detil_lokasi ?? ''),
            reportId: (string) ($row->report_id ?? ''),
            description: (string) ($row->description ?? ''),
            pic: (string) ($row->pic ?? ''),
            company: (string) ($row->company ?? ''),
            status: (string) ($row->status ?? ''),
        );
    }

    /**
     * Satu laporan SAP = satu baris. Observasi/OAK di MV dipecah per orang
     * yang diamati — COUNT(*) polos menggandakan Total.
     *
     * @param  list<array<string, mixed>>  $findings
     * @return list<array<string, mixed>>
     */
    public function uniqueByReport(array $findings): array
    {
        $seen = [];
        $unique = [];
        foreach ($findings as $finding) {
            $component = strtolower(trim((string) ($finding['component'] ?? '')));
            $reportId = trim((string) ($finding['report_id'] ?? ''));
            $key = $reportId !== ''
                ? $component.'|'.$reportId
                : $component.'|'.($finding['sid'] ?? '').'|'.($finding['at'] ?? '').'|'.($finding['category'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $finding;
        }

        return $unique;
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(
        string $sid,
        string $name,
        CarbonImmutable $at,
        string $component,
        string $category,
        string $goldenRule,
        string $lokasi,
        string $detilLokasi,
        string $reportId = '',
        string $description = '',
        string $photoUrl = '',
        mixed $latitude = null,
        mixed $longitude = null,
        string $pic = '',
        string $company = '',
        string $status = '',
    ): array {
        return [
            'sid' => $sid,
            'name' => trim($name),
            'at' => $at->toDateTimeString(),
            'hour' => (int) $at->format('G'),
            'component' => $component,
            'category' => $category,
            'golden_rule' => trim($goldenRule),
            'lokasi' => trim($lokasi),
            'detil_lokasi' => trim($detilLokasi),
            'report_id' => $reportId,
            'description' => trim($description),
            'photo_url' => trim($photoUrl),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'pic' => trim($pic),
            'company' => trim($company),
            'status' => trim($status),
        ];
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<object>|null
     */
    private function select(string $sql, array $bindings, string $source): ?array
    {
        try {
            return $this->olap->select($sql, $bindings, self::QUERY_TIMEOUT_MS, [
                'jit' => 'off',
                'max_parallel_workers_per_gather' => '0',
            ]);
        } catch (Throwable $e) {
            Log::warning('ControlRoom SAP week counts '.$source.' gagal: '.$e->getMessage());

            return null;
        }
    }

    private function parseAt(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
