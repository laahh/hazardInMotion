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
    private const CACHE_SECONDS = 90;

    private const QUERY_TIMEOUT_MS = 4000;

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
    public function forScheduleDays(array $scheduleDays): array
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
        $cacheKey = 'control-room:sap-week-counts:v2:'.hash('sha1', implode(',', $sids).'|'.$dates[0].'|'.$dates[array_key_last($dates)]);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['counts'], $cached['findings'])) {
            return ['loaded' => true, 'counts' => $cached['counts'], 'findings' => $cached['findings']];
        }

        $rangeStart = CarbonImmutable::parse($dates[0])->startOfDay();
        $rangeEnd = $this->dutyWindow->reportingWindow(CarbonImmutable::parse($dates[array_key_last($dates)]))['end'];

        $failed = 0;
        $findings = [
            ...$this->fetchHazardInspeksi($sids, $rangeStart, $rangeEnd, $failed),
            ...$this->fetchObservasi($sids, $rangeStart, $rangeEnd, $failed),
            ...$this->fetchOak($sids, $rangeStart, $rangeEnd, $failed),
        ];

        if ($failed > 0) {
            return ['loaded' => false, 'counts' => [], 'findings' => []];
        }

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
        Cache::put($cacheKey, ['counts' => $counts, 'findings' => $findings], self::CACHE_SECONDS);

        return ['loaded' => true, 'counts' => $counts, 'findings' => $findings];
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
                $component = $event['component'];
                if (isset($bucket[$component])) {
                    $bucket[$component]++;
                }
            }
            $counts[$key] = $bucket;
        }

        return $counts;
    }

    public function slotKey(string $sid, string $date): string
    {
        return strtoupper(trim($sid)).'|'.$date;
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
     * @param  list<string>  $sids
     * @param  int  $failed
     * @return list<array<string, mixed>>
     */
    private function fetchHazardInspeksi(array $sids, CarbonImmutable $start, CarbonImmutable $end, int &$failed): array
    {
        $placeholders = implode(',', array_fill(0, count($sids), '?'));
        $sql = "
            WITH sid_rows AS MATERIALIZED (
                SELECT kode_sid_pelapor, nama_pelapor, tanggal_laporan, jenis_laporan,
                       nama_kategori, ketidaksesuaian, nama_goldenrule, lokasi, detil_lokasi
                FROM bcbeats.mv_inspeksi_hazard
                WHERE kode_sid_pelapor IN ({$placeholders})
            )
            SELECT kode_sid_pelapor, nama_pelapor, tanggal_laporan, jenis_laporan,
                   nama_kategori, ketidaksesuaian, nama_goldenrule, lokasi, detil_lokasi
            FROM sid_rows
            WHERE tanggal_laporan >= CAST(? AS timestamp)
              AND tanggal_laporan < CAST(? AS timestamp)
        ";

        $rows = $this->select($sql, [...$sids, $start->toDateTimeString(), $end->toDateTimeString()], 'hazard/inspeksi', $failed);
        $findings = [];
        foreach ($rows as $row) {
            $at = $this->parseAt($row->tanggal_laporan ?? null);
            $sid = strtoupper(trim((string) ($row->kode_sid_pelapor ?? '')));
            if ($at === null || $sid === '') {
                continue;
            }
            $jenis = strtoupper(trim((string) ($row->jenis_laporan ?? '')));
            $findings[] = $this->finding(
                sid: $sid,
                name: (string) ($row->nama_pelapor ?? ''),
                at: $at,
                component: $jenis === 'INSPEKSI' ? 'inspeksi' : 'hazard',
                category: $this->firstText($row->nama_kategori ?? null, $row->ketidaksesuaian ?? null),
                goldenRule: (string) ($row->nama_goldenrule ?? ''),
                lokasi: (string) ($row->lokasi ?? ''),
                detilLokasi: (string) ($row->detil_lokasi ?? ''),
            );
        }

        return $findings;
    }

    /**
     * @param  list<string>  $sids
     * @param  int  $failed
     * @return list<array<string, mixed>>
     */
    private function fetchObservasi(array $sids, CarbonImmutable $start, CarbonImmutable $end, int &$failed): array
    {
        $placeholders = implode(',', array_fill(0, count($sids), '?'));
        $sql = "
            WITH sid_rows AS MATERIALIZED (
                SELECT kode_sid_pelapor, nama_pelapor, tanggal_observasi, jenis_kegiatan, tools_observasi, lokasi, detil_lokasi
                FROM bcbeats.mv_observasi
                WHERE kode_sid_pelapor IN ({$placeholders})
            )
            SELECT kode_sid_pelapor, nama_pelapor, tanggal_observasi, jenis_kegiatan, tools_observasi, lokasi, detil_lokasi
            FROM sid_rows
            WHERE tanggal_observasi >= CAST(? AS timestamp)
              AND tanggal_observasi < CAST(? AS timestamp)
        ";

        $rows = $this->select($sql, [...$sids, $start->toDateTimeString(), $end->toDateTimeString()], 'observasi', $failed);
        $findings = [];
        foreach ($rows as $row) {
            $at = $this->parseAt($row->tanggal_observasi ?? null);
            $sid = strtoupper(trim((string) ($row->kode_sid_pelapor ?? '')));
            if ($at === null || $sid === '') {
                continue;
            }
            $findings[] = $this->finding(
                sid: $sid,
                name: (string) ($row->nama_pelapor ?? ''),
                at: $at,
                component: 'observasi',
                category: $this->firstText($row->jenis_kegiatan ?? null, $row->tools_observasi ?? null),
                goldenRule: '',
                lokasi: (string) ($row->lokasi ?? ''),
                detilLokasi: (string) ($row->detil_lokasi ?? ''),
            );
        }

        return $findings;
    }

    /**
     * @param  list<string>  $sids
     * @param  int  $failed
     * @return list<array<string, mixed>>
     */
    private function fetchOak(array $sids, CarbonImmutable $start, CarbonImmutable $end, int &$failed): array
    {
        $placeholders = implode(',', array_fill(0, count($sids), '?'));
        $sql = "
            WITH sid_rows AS MATERIALIZED (
                SELECT id_oak, kode_sid_pelapor, nama_pelapor, tanggal_submit, aktivitas, sub_aktivitas, lokasi, detil_lokasi
                FROM bcbeats.mv_oak
                WHERE kode_sid_pelapor IN ({$placeholders})
            )
            SELECT DISTINCT ON (id_oak)
                kode_sid_pelapor, nama_pelapor, tanggal_submit, aktivitas, sub_aktivitas, lokasi, detil_lokasi
            FROM sid_rows
            WHERE tanggal_submit >= CAST(? AS timestamp)
              AND tanggal_submit < CAST(? AS timestamp)
            ORDER BY id_oak, tanggal_submit
        ";

        $rows = $this->select($sql, [...$sids, $start->toDateTimeString(), $end->toDateTimeString()], 'OAK', $failed);
        $findings = [];
        foreach ($rows as $row) {
            $at = $this->parseAt($row->tanggal_submit ?? null);
            $sid = strtoupper(trim((string) ($row->kode_sid_pelapor ?? '')));
            if ($at === null || $sid === '') {
                continue;
            }
            $findings[] = $this->finding(
                sid: $sid,
                name: (string) ($row->nama_pelapor ?? ''),
                at: $at,
                component: 'oak',
                category: $this->firstText($row->aktivitas ?? null, $row->sub_aktivitas ?? null),
                goldenRule: '',
                lokasi: (string) ($row->lokasi ?? ''),
                detilLokasi: (string) ($row->detil_lokasi ?? ''),
            );
        }

        return $findings;
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
        ];
    }

    private function firstText(mixed ...$values): string
    {
        foreach ($values as $value) {
            $text = trim((string) ($value ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<object>
     */
    private function select(string $sql, array $bindings, string $source, int &$failed): array
    {
        try {
            return $this->olap->select($sql, $bindings, self::QUERY_TIMEOUT_MS);
        } catch (Throwable $e) {
            Log::warning('ControlRoom SAP week counts '.$source.' gagal: '.$e->getMessage());
            $failed++;

            return [];
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
