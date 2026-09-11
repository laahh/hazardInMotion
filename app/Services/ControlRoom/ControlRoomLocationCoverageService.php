<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\Reference\LocationReader;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Coverage lokasi master vs SAP minggu terpilih (hazard/inspeksi/observasi/OAK) di OBDS.
 *
 * Weekly: semua lokasi, ter-cover jika ada ≥1 SAP dalam minggu.
 * Daily: semua lokasi, ter-cover jika ada ≥1 SAP pada tanggal yang dipilih
 * (bukan kumulatif 7 hari). Filter kritis/non-kritis memakai rumus CONTAINS.
 */
final class ControlRoomLocationCoverageService
{
    private const PAGE_CACHE_TTL = 'v10';

    private const PAGE_CACHE_SECONDS = 300;

    private const PAST_PAGE_CACHE_SECONDS = 21600;

    private const LAST_GOOD_CACHE_SECONDS = 86400;

    private const MISS_CACHE_SECONDS = 20;

    /** @var list<string> */
    private const DAY_SHORT = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    public function __construct(
        private readonly LocationReader $locations,
        private readonly ControlRoomSapQualityFindingsReader $qualityFindings,
        private readonly ControlRoomSapDutyReader $dutyWindow,
    ) {}

    /**
     * @return array{
     *     loaded: bool,
     *     daily: array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>},
     *     weekly: array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>}
     * }
     */
    public function build(
        ControlRoomSiteCode $site,
        CarbonImmutable $weekStart,
        ?CarbonInterface $now = null,
    ): array {
        $today = CarbonImmutable::parse($now ?? now())->startOfDay();
        $ttl = $weekStart->addDays(6)->lessThan($today)
            ? self::PAST_PAGE_CACHE_SECONDS
            : self::PAGE_CACHE_SECONDS;
        $cacheKey = sprintf(
            'control-room:location-coverage:%s:%s:%s:%s',
            self::PAGE_CACHE_TTL,
            $weekStart->toDateString(),
            $site->value,
            $today->toDateString(),
        );
        $lastGoodKey = sprintf(
            'control-room:location-coverage:%s:last:%s:%s',
            self::PAGE_CACHE_TTL,
            $weekStart->toDateString(),
            $site->value,
        );

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['loaded'] ?? false) === true && isset($cached['daily'], $cached['weekly'])) {
            /** @var array{loaded: bool, daily: array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>}, weekly: array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>}} */
            return $cached;
        }

        if (Cache::get($cacheKey.':miss')) {
            return $this->lastGoodCoverage($lastGoodKey) ?? $this->packCoverage($site, $weekStart, $today, [], false);
        }

        $payload = $this->buildUncached($site, $weekStart, $today);
        if ($payload['loaded']) {
            Cache::put($cacheKey, $payload, $ttl);
            Cache::put($lastGoodKey, $payload, self::LAST_GOOD_CACHE_SECONDS);

            return $payload;
        }

        Cache::put($cacheKey.':miss', true, self::MISS_CACHE_SECONDS);

        return $this->lastGoodCoverage($lastGoodKey) ?? $payload;
    }

    /**
     * @param  list<array{site: string, lokasi: string, detail_lokasi: string}>  $master
     * @param  array<string, string>  $coveredAt  locationKey => last_at
     * @return array{
     *     loaded: bool,
     *     kpi: array{total: int, covered: int, uncovered: int, percent: float},
     *     rows: list<array<string, mixed>>,
     *     attention: list<array<string, mixed>>
     * }
     */
    public function evaluate(array $master, array $coveredAt, bool $loaded = true): array
    {
        $hits = [];
        foreach ($coveredAt as $key => $at) {
            if (! is_string($at) || $at === '') {
                continue;
            }
            $hits[(string) $key] = ['last_at' => $at, 'days' => []];
        }

        return $this->evaluateWeekly($master, $hits, $loaded);
    }

    /**
     * @param  list<array{site: string, lokasi: string, detail_lokasi: string}>  $master
     * @param  array<string, array{last_at: string, days: array<string, true>}>  $hits
     * @return array{
     *     loaded: bool,
     *     kpi: array{total: int, covered: int, uncovered: int, percent: float},
     *     rows: list<array<string, mixed>>,
     *     attention: list<array<string, mixed>>
     * }
     */
    public function evaluateWeekly(array $master, array $hits, bool $loaded = true): array
    {
        $rows = [];
        $attention = [];
        $covered = 0;

        foreach ($master as $item) {
            $lokasi = (string) ($item['lokasi'] ?? '');
            $detil = (string) ($item['detail_lokasi'] ?? '');
            $hit = $this->hitFor($lokasi, $detil, $hits);
            $lastAt = $hit['last_at'];
            $isCovered = is_string($lastAt) && $lastAt !== '';
            $isCritical = $this->locations->isCritical($lokasi, $detil);
            if ($isCovered) {
                $covered++;
            }

            $row = [
                'site' => (string) ($item['site'] ?? ''),
                'lokasi' => $lokasi,
                'detail_lokasi' => $detil,
                'is_critical' => $isCritical,
                'covered' => $isCovered,
                'last_at' => $isCovered ? $lastAt : null,
                'gap_label' => $isCovered ? '—' : 'Minggu ini',
                'day_marks' => [],
            ];
            $rows[] = $row;
            if ($isCritical && ! $isCovered) {
                $attention[] = $row;
            }
        }

        return $this->packPanel($rows, $attention, $covered, $loaded);
    }

    /**
     * @param  list<array{site: string, lokasi: string, detail_lokasi: string}>  $master
     * @param  array<string, array{last_at: string, days: array<string, true>}>  $hits
     * @return array{
     *     loaded: bool,
     *     selected_date: string,
     *     kpi: array{total: int, covered: int, uncovered: int, percent: float},
     *     rows: list<array<string, mixed>>,
     *     attention: list<array<string, mixed>>,
     *     critical_count: int,
     *     noncritical_count: int
     * }
     */
    public function evaluateDaily(
        array $master,
        array $hits,
        string $selectedDate,
        bool $loaded = true,
    ): array {
        $rows = [];
        $attention = [];
        $covered = 0;
        $criticalCount = 0;

        foreach ($master as $item) {
            $lokasi = (string) ($item['lokasi'] ?? '');
            $detil = (string) ($item['detail_lokasi'] ?? '');
            $hit = $this->hitFor($lokasi, $detil, $hits);
            $days = $hit['days'];
            $isCovered = $selectedDate !== '' && isset($days[$selectedDate]);
            $isCritical = $this->locations->isCritical($lokasi, $detil);
            if ($isCovered) {
                $covered++;
            }
            if ($isCritical) {
                $criticalCount++;
            }

            $coveredDates = array_keys($days);
            sort($coveredDates);

            $row = [
                'site' => (string) ($item['site'] ?? ''),
                'lokasi' => $lokasi,
                'detail_lokasi' => $detil,
                'is_critical' => $isCritical,
                'covered' => $isCovered,
                'last_at' => $hit['last_at'],
                'gap_label' => $isCovered ? '—' : $this->dayShort($selectedDate),
                'covered_dates' => array_values($coveredDates),
            ];
            $rows[] = $row;
            if ($isCritical && ! $isCovered) {
                $attention[] = $row;
            }
        }

        $panel = $this->packPanel($rows, $attention, $covered, $loaded);
        $panel['selected_date'] = $selectedDate;
        $panel['critical_count'] = $criticalCount;
        $panel['noncritical_count'] = count($rows) - $criticalCount;

        return $panel;
    }

    public function locationKey(string $lokasi, string $detil): string
    {
        $lokasi = $this->normalizeLabel($lokasi);
        $detil = $this->normalizeLabel($detil);
        if ($lokasi === '' && $detil === '') {
            return '';
        }

        return $lokasi.'|'.$detil;
    }

    /**
     * @return list<string>
     */
    public function locationKeys(string $lokasi, string $detil): array
    {
        $lokasi = $this->normalizeLabel($lokasi);
        $detil = $this->normalizeLabel($detil);
        $keys = [];
        $this->pushKey($keys, $lokasi, $detil);
        $stripped = $this->stripSitePrefix($lokasi);
        if ($stripped !== $lokasi) {
            $this->pushKey($keys, $stripped, $detil);
        }

        return $keys;
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return array<string, string>
     */
    public function coveredAt(array $findings): array
    {
        $covered = [];
        foreach ($this->coveredHits($findings) as $key => $hit) {
            $covered[$key] = $hit['last_at'];
        }

        return $covered;
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return array<string, array{last_at: string, days: array<string, true>}>
     */
    public function coveredHits(array $findings): array
    {
        $covered = [];
        foreach ($findings as $finding) {
            $at = trim((string) ($finding['at'] ?? ''));
            if ($at === '') {
                continue;
            }
            try {
                $day = CarbonImmutable::parse($at)->toDateString();
            } catch (\Throwable) {
                continue;
            }
            $detil = (string) ($finding['detil_lokasi'] ?? $finding['detail_lokasi'] ?? '');
            foreach ($this->locationKeys((string) ($finding['lokasi'] ?? ''), $detil) as $key) {
                if (! isset($covered[$key])) {
                    $covered[$key] = [
                        'last_at' => $at,
                        'days' => [$day => true],
                    ];

                    continue;
                }
                if ($at > $covered[$key]['last_at']) {
                    $covered[$key]['last_at'] = $at;
                }
                $covered[$key]['days'][$day] = true;
            }
        }

        return $covered;
    }

    /**
     * @return array{
     *     loaded: bool,
     *     daily: array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>},
     *     weekly: array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>}
     * }
     */
    private function buildUncached(
        ControlRoomSiteCode $site,
        CarbonImmutable $weekStart,
        CarbonImmutable $today,
    ): array {
        $weekEnd = $weekStart->addDays(6);
        $lastDay = $weekEnd->lessThan($today) ? $weekEnd : $today;
        $sap = $this->qualityFindings->locationHits(
            $weekStart->startOfDay(),
            $this->dutyWindow->reportingWindow($lastDay)['end'],
            $weekEnd->lessThan($today) ? 21600 : 300,
        );

        return $this->packCoverage($site, $weekStart, $today, $this->coveredHits($sap['findings']), $sap['loaded']);
    }

    /**
     * @param  array<string, array{last_at: string, days: array<string, true>}>  $hits
     * @return array{
     *     loaded: bool,
     *     daily: array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>},
     *     weekly: array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>}
     * }
     */
    private function packCoverage(
        ControlRoomSiteCode $site,
        CarbonImmutable $weekStart,
        CarbonImmutable $today,
        array $hits,
        bool $loaded,
    ): array {
        $master = $this->locations->forCoverage($site)->all();
        $selectedDate = $this->defaultDailyDate($weekStart, $today);
        $daily = $this->evaluateDaily($master, $hits, $selectedDate, $loaded);
        $daily['week_days'] = $this->weekDayOptions($weekStart, $today, $selectedDate);

        return [
            'loaded' => $loaded,
            'daily' => $daily,
            'weekly' => $this->evaluateWeekly($master, $hits, $loaded),
        ];
    }

    /**
     * @return array{
     *     loaded: bool,
     *     daily: array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>},
     *     weekly: array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>}
     * }|null
     */
    private function lastGoodCoverage(string $key): ?array
    {
        $cached = Cache::get($key);
        if (! is_array($cached) || ! isset($cached['daily'], $cached['weekly'])) {
            return null;
        }

        /** @var array{loaded: bool, daily: array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>}, weekly: array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>}} */
        return $cached;
    }

    /**
     * @param  array<string, array{last_at: string, days: array<string, true>}>  $hits
     * @return array{last_at: string|null, days: array<string, true>}
     */
    private function hitFor(string $lokasi, string $detil, array $hits): array
    {
        $lastAt = null;
        $days = [];
        foreach ($this->locationKeys($lokasi, $detil) as $key) {
            $hit = $hits[$key] ?? null;
            if ($hit === null) {
                continue;
            }
            if ($lastAt === null || $hit['last_at'] > $lastAt) {
                $lastAt = $hit['last_at'];
            }
            foreach ($hit['days'] as $day => $flag) {
                if ($flag) {
                    $days[$day] = true;
                }
            }
        }

        return ['last_at' => $lastAt, 'days' => $days];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $attention
     * @return array{
     *     loaded: bool,
     *     kpi: array{total: int, covered: int, uncovered: int, percent: float},
     *     rows: list<array<string, mixed>>,
     *     attention: list<array<string, mixed>>
     * }
     */
    private function packPanel(array $rows, array $attention, int $covered, bool $loaded): array
    {
        $this->sortCoverageRows($rows);
        $this->sortCoverageRows($attention);
        $total = count($rows);

        return [
            'loaded' => $loaded,
            'kpi' => [
                'total' => $total,
                'covered' => $covered,
                'uncovered' => $total - $covered,
                'percent' => $total === 0 ? 0.0 : round($covered / $total * 100, 1),
            ],
            'rows' => $rows,
            'attention' => $attention,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sortCoverageRows(array &$rows): void
    {
        usort($rows, function (array $a, array $b): int {
            $bySite = strcasecmp((string) $a['site'], (string) $b['site']);
            if ($bySite !== 0) {
                return $bySite;
            }
            $byLokasi = strcasecmp((string) $a['lokasi'], (string) $b['lokasi']);
            if ($byLokasi !== 0) {
                return $byLokasi;
            }

            return strcasecmp((string) $a['detail_lokasi'], (string) $b['detail_lokasi']);
        });
    }

    /**
     * Default tanggal Daily: hari ini jika masih dalam minggu terpilih,
     * hari terakhir minggu jika minggu sudah lewat, hari pertama jika minggu belum mulai.
     */
    private function defaultDailyDate(CarbonImmutable $weekStart, CarbonImmutable $today): string
    {
        $weekEnd = $weekStart->addDays(6);
        if ($today->lessThan($weekStart)) {
            return $weekStart->toDateString();
        }
        if ($today->greaterThan($weekEnd)) {
            return $weekEnd->toDateString();
        }

        return $today->toDateString();
    }

    /**
     * @return list<array{date: string, label: string, display: string, is_today: bool, is_future: bool, selected: bool}>
     */
    private function weekDayOptions(
        CarbonImmutable $weekStart,
        CarbonImmutable $today,
        string $selectedDate,
    ): array {
        $todayIso = $today->toDateString();
        $options = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->addDays($i);
            $iso = $date->toDateString();
            $options[] = [
                'date' => $iso,
                'label' => self::DAY_SHORT[(int) $date->dayOfWeek] ?? $iso,
                'display' => $date->locale('id')->translatedFormat('j M'),
                'is_today' => $iso === $todayIso,
                'is_future' => $iso > $todayIso,
                'selected' => $iso === $selectedDate,
            ];
        }

        return $options;
    }

    private function dayShort(string $date): string
    {
        try {
            return self::DAY_SHORT[(int) CarbonImmutable::parse($date)->dayOfWeek] ?? $date;
        } catch (\Throwable) {
            return $date;
        }
    }

    /**
     * @param  list<string>  $keys
     */
    private function pushKey(array &$keys, string $lokasi, string $detil): void
    {
        if ($lokasi === '' && $detil === '') {
            return;
        }
        $keys[] = $lokasi.'|'.$detil;
    }

    private function normalizeLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return $value;
    }

    private function stripSitePrefix(string $lokasi): string
    {
        return trim((string) preg_replace('/^\([^)]+\)\s*/u', '', $lokasi));
    }
}
