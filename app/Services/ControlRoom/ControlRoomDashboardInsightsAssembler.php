<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\Metrics\FindingVariety;
use App\Services\ControlRoom\Metrics\TbcValidity;
use App\Services\ControlRoom\Reference\LocationReader;
use App\Services\ControlRoom\Reference\ShiftResolver;
use App\Services\Hsecm\HsecmDatabaseRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pareto, Highlight (GR/Blindspot/TBC), dan Kualitas dari laporan SAP minggu
 * ini + snapshot HSECM lokal. Bukan mock.
 */
final class ControlRoomDashboardInsightsAssembler
{
    private const COVERAGE_TABLE = 'scr_hsecm_coverage_area_kritis_daily';

    private const TBC_TABLE = 'scr_hsecm_blindspot_tbc_gr';

    private const HSECM_CACHE_SECONDS = 300;

    public function __construct(
        private readonly ShiftResolver $shifts,
        private readonly FindingVariety $variety,
        private readonly TbcValidity $tbc,
        private readonly LocationReader $locations,
        private readonly HsecmDatabaseRepository $hsecm,
        private readonly ControlRoomSapDutyReader $dutyWindow,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $scheduleDays
     * @param  list<array<string, mixed>>  $findings
     * @return array{
     *     pareto: array{s1: list<array{hour: int, count: int, cumulative: float}>, s2: list<array{hour: int, count: int, cumulative: float}>},
     *     highlight: array{goldenRules: list<array{name: string, count: int}>, blindspotCount: int, blindspotTotal: int, tbcPercentage: ?float},
     *     quality: list<array<string, mixed>>,
     *     personnelCoverage: list<array{name: string, lokasi: int, kritis: int, lead: bool}>
     * }
     */
    public function build(
        ControlRoomSiteCode $site,
        CarbonInterface $weekStart,
        CarbonInterface $weekEnd,
        array $scheduleDays,
        array $findings,
        bool $sapLoaded,
    ): array {
        $cacheKey = sprintf(
            'control-room:insights-hsecm:v1:%s:%s:%s',
            $site->value,
            $weekStart->toDateString(),
            $weekEnd->toDateString(),
        );
        $hsecm = Cache::remember($cacheKey, self::HSECM_CACHE_SECONDS, function () use ($site, $weekStart, $weekEnd): array {
            return [
                'coverage' => $this->loadCoverage($site),
                'tbc' => $this->loadTbcRows($site, $weekStart, $weekEnd),
            ];
        });

        return $this->fromFindings($findings, $scheduleDays, $hsecm['coverage'], $hsecm['tbc'], $sapLoaded);
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @param  list<array<string, mixed>>  $scheduleDays
     * @param  array{uncovered: array<string, true>, total: int}  $coverage
     * @param  list<array<string, mixed>>  $tbcRows
     * @return array{
     *     pareto: array{s1: list<array{hour: int, count: int, cumulative: float}>, s2: list<array{hour: int, count: int, cumulative: float}>},
     *     highlight: array{goldenRules: list<array{name: string, count: int}>, blindspotCount: int, blindspotTotal: int, tbcPercentage: ?float},
     *     quality: list<array<string, mixed>>,
     *     personnelCoverage: list<array{name: string, lokasi: int, kritis: int, lead: bool}>
     * }
     */
    public function fromFindings(
        array $findings,
        array $scheduleDays,
        array $coverage,
        array $tbcRows,
        bool $sapLoaded,
    ): array {
        $usable = $sapLoaded ? $this->onDutyFindings($findings, $scheduleDays) : [];

        return [
            'pareto' => $this->paretoFromFindings($usable),
            'highlight' => $this->highlightFromFindings($usable, $coverage, $tbcRows),
            'quality' => $this->qualityFromFindings($usable, $scheduleDays, $coverage, $tbcRows),
            'personnelCoverage' => $this->personnelCoverageFromFindings($usable, $scheduleDays),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return array{s1: list<array{hour: int, count: int, cumulative: float}>, s2: list<array{hour: int, count: int, cumulative: float}>}
     */
    public function paretoFromFindings(array $findings): array
    {
        $buckets = [
            ControlRoomShiftCode::S1->value => [],
            ControlRoomShiftCode::S2->value => [],
        ];

        foreach ($findings as $finding) {
            $at = CarbonImmutable::parse((string) $finding['at']);
            $shift = $this->shifts->resolve($at)->value;
            $hour = (int) ($finding['hour'] ?? $at->format('G'));
            $buckets[$shift][$hour] = ($buckets[$shift][$hour] ?? 0) + 1;
        }

        return [
            's1' => $this->toParetoSeries($buckets[ControlRoomShiftCode::S1->value]),
            's2' => $this->toParetoSeries($buckets[ControlRoomShiftCode::S2->value]),
        ];
    }

    /**
     * @param  array<int, int>  $counts
     * @return list<array{hour: int, count: int, cumulative: float}>
     */
    public function toParetoSeries(array $counts): array
    {
        if ($counts === []) {
            return [];
        }

        arsort($counts);
        $total = array_sum($counts);
        $running = 0;
        $series = [];

        foreach ($counts as $hour => $count) {
            $running += $count;
            $series[] = [
                'hour' => (int) $hour,
                'count' => $count,
                'cumulative' => $total > 0 ? round(($running / $total) * 100, 1) : 0.0,
            ];
        }

        return $series;
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @param  array{uncovered: array<string, true>, total: int}  $coverage
     * @param  list<array<string, mixed>>  $tbcRows
     * @return array{goldenRules: list<array{name: string, count: int}>, blindspotCount: int, blindspotTotal: int, tbcPercentage: ?float}
     */
    private function highlightFromFindings(array $findings, array $coverage, array $tbcRows): array
    {
        $golden = [];
        $hazardInspeksi = 0;
        foreach ($findings as $finding) {
            $component = (string) ($finding['component'] ?? '');
            if ($component === 'hazard' || $component === 'inspeksi') {
                $hazardInspeksi++;
            }
            $rule = trim((string) ($finding['golden_rule'] ?? ''));
            if (! $this->isGoldenRuleViolation($rule)) {
                continue;
            }
            $golden[$rule] = ($golden[$rule] ?? 0) + 1;
        }

        arsort($golden);
        $goldenRules = [];
        foreach ($golden as $name => $count) {
            $goldenRules[] = ['name' => $name, 'count' => $count];
        }

        $tbcPercentage = $tbcRows === []
            ? null
            : $this->tbc->percentage(count($tbcRows), $hazardInspeksi);

        return [
            'goldenRules' => $goldenRules,
            'blindspotCount' => count($coverage['uncovered']),
            'blindspotTotal' => $coverage['total'],
            'tbcPercentage' => $tbcPercentage,
        ];
    }

    /**
     * Kualitas temuan personil jadwal: kategori = sub ketidaksesuaian
     * (observasi/OAK memakai analog jenis kegiatan / sub aktivitas).
     * Variasi = COUNT DISTINCT kategori / COUNT temuan saat jaga.
     *
     * @param  list<array<string, mixed>>  $findings
     * @param  list<array<string, mixed>>  $scheduleDays
     * @param  array{uncovered: array<string, true>, total: int}  $coverage
     * @param  list<array<string, mixed>>  $tbcRows
     * @return list<array<string, mixed>>
     */
    private function qualityFromFindings(array $findings, array $scheduleDays, array $coverage, array $tbcRows): array
    {
        $namesBySid = $this->namesBySid($scheduleDays);
        $tbcByName = $this->tbcCountsByPelapor($tbcRows);

        $bySid = [];
        foreach ($findings as $finding) {
            $sid = strtoupper(trim((string) ($finding['sid'] ?? '')));
            if ($sid === '' || ! isset($namesBySid[$sid])) {
                continue;
            }
            $bySid[$sid][] = $finding;
        }

        $rows = [];
        foreach ($namesBySid as $sid => $name) {
            $personFindings = $bySid[$sid] ?? [];
            if ($personFindings === []) {
                continue;
            }

            $categories = [];
            $gr = 0;
            $blindspot = 0;
            foreach ($personFindings as $finding) {
                $categories[] = $this->findingCategory($finding);
                if ($this->isGoldenRuleViolation((string) ($finding['golden_rule'] ?? ''))) {
                    $gr++;
                }
                $locationKey = $this->locationKey(
                    (string) ($finding['lokasi'] ?? ''),
                    (string) ($finding['detil_lokasi'] ?? ''),
                );
                if ($coverage['total'] > 0) {
                    if ($locationKey !== '' && isset($coverage['uncovered'][$locationKey])) {
                        $blindspot++;
                    }
                } elseif ($this->locations->isCritical((string) ($finding['lokasi'] ?? ''), (string) ($finding['detil_lokasi'] ?? ''))) {
                    $blindspot++;
                }
            }

            $rows[] = [
                'name' => $name,
                'sid' => $sid,
                'total_findings' => count($personFindings),
                'distinct_categories' => count(array_unique($categories)),
                'variety_score' => $this->variety->score($categories),
                'tbc' => $tbcByName[$this->normalizeName($name)] ?? 0,
                'gr' => $gr,
                'blindspot' => $blindspot,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['total_findings'] <=> $a['total_findings']);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @param  list<array<string, mixed>>  $scheduleDays
     * @return list<array<string, mixed>>
     */
    private function onDutyFindings(array $findings, array $scheduleDays): array
    {
        $windowsBySid = [];
        foreach ($scheduleDays as $day) {
            $date = (string) ($day['date'] ?? '');
            if ($date === '') {
                continue;
            }
            $window = $this->dutyWindow->reportingWindow(CarbonImmutable::parse($date));
            foreach (['s1', 's2'] as $shiftKey) {
                foreach ($day[$shiftKey] ?? [] as $person) {
                    $sid = strtoupper(trim((string) ($person['sid'] ?? '')));
                    if ($sid !== '') {
                        $windowsBySid[$sid][] = $window;
                    }
                }
            }
        }

        $onDuty = [];
        foreach ($findings as $finding) {
            $sid = strtoupper(trim((string) ($finding['sid'] ?? '')));
            if ($sid === '' || ! isset($windowsBySid[$sid])) {
                continue;
            }

            try {
                $at = CarbonImmutable::parse((string) ($finding['at'] ?? ''));
            } catch (Throwable) {
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

    /**
     * @param  array<string, mixed>  $finding
     */
    private function findingCategory(array $finding): string
    {
        $category = trim((string) preg_replace('/\s+/', ' ', (string) ($finding['category'] ?? '')));
        if ($category === '') {
            return 'Tanpa kategori';
        }

        return mb_strtolower($category);
    }

    /**
     * Snapshot coverage slot terbaru: COUNT DISTINCT di SQL, lalu hanya
     * baris yang belum tercover. Menghindari hydrate seluruh site (HO).
     *
     * @return array{uncovered: array<string, true>, total: int}
     */
    private function loadCoverage(ControlRoomSiteCode $site): array
    {
        $empty = ['uncovered' => [], 'total' => 0];
        if (! Schema::hasTable(self::COVERAGE_TABLE)) {
            return $empty;
        }

        try {
            $base = DB::table(self::COVERAGE_TABLE);
            if ($this->hsecm->hasBatchSlotSupport(self::COVERAGE_TABLE)) {
                $slot = $this->hsecm->latestBatchSlot(self::COVERAGE_TABLE);
                if ($slot === null) {
                    return $empty;
                }
                $base->where('batch_slot', $slot);
            }
            $this->applySiteFilter($base, $site, 'Site');
            $base->where(function ($query): void {
                $query->whereRaw("TRIM(COALESCE(`Lokasi`, '')) <> ''")
                    ->orWhereRaw("TRIM(COALESCE(`Detil_Lokasi`, '')) <> ''");
            });

            $total = (int) (clone $base)
                ->selectRaw("COUNT(DISTINCT LOWER(CONCAT(TRIM(COALESCE(`Lokasi`, '')), '|', TRIM(COALESCE(`Detil_Lokasi`, ''))))) as c")
                ->value('c');

            $rows = (clone $base)
                ->select(['Lokasi', 'Detil_Lokasi', 'Status_Coverage_dalam_1_Week', 'Tercover'])
                ->where(function ($query): void {
                    $status = "LOWER(TRIM(COALESCE(`Status_Coverage_dalam_1_Week`, '')))";
                    $query->whereRaw("{$status} LIKE ?", ['%tidak%'])
                        ->orWhereRaw("{$status} LIKE ?", ['%belum%'])
                        ->orWhereRaw("{$status} LIKE ?", ['%gap%'])
                        ->orWhereRaw("TRIM(COALESCE(`Status_Coverage_dalam_1_Week`, '')) = ''")
                        ->orWhereRaw('CAST(`Tercover` AS DECIMAL(12, 4)) < 1');
                })
                ->get();
        } catch (Throwable $e) {
            Log::warning('ControlRoom HSECM coverage gagal: '.$e->getMessage());

            return $empty;
        }

        $uncovered = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            if (! $this->isUncovered($arr)) {
                continue;
            }
            $key = $this->locationKey((string) ($arr['Lokasi'] ?? ''), (string) ($arr['Detil_Lokasi'] ?? ''));
            if ($key !== '') {
                $uncovered[$key] = true;
            }
        }

        return ['uncovered' => $uncovered, 'total' => $total];
    }

    /**
     * TBC dari latest batch_slot + Date_for_Join minggu ini — bukan dump
     * semua scrape di rentang tanggal.
     *
     * @return list<array<string, mixed>>
     */
    private function loadTbcRows(ControlRoomSiteCode $site, CarbonInterface $weekStart, CarbonInterface $weekEnd): array
    {
        if (! Schema::hasTable(self::TBC_TABLE)) {
            return [];
        }

        $from = $weekStart->toDateString();
        $to = $weekEnd->toDateString();

        try {
            $query = DB::table(self::TBC_TABLE)
                ->select(['Date_for_Join', 'site', 'kategori_TBC', 'blindspot_TBC', 'pelapor_all_karyawan', 'validasi_GR']);
            if ($this->hsecm->hasBatchSlotSupport(self::TBC_TABLE)) {
                $slot = $this->hsecm->latestBatchSlot(self::TBC_TABLE);
                if ($slot === null) {
                    return [];
                }
                $query->where('batch_slot', $slot);
            }
            $this->applySiteFilter($query, $site, 'site');
            $query->where(function ($inner) use ($from, $to): void {
                $inner->whereNull('Date_for_Join')
                    ->orWhere(function ($dates) use ($from, $to): void {
                        $dates->whereDate('Date_for_Join', '>=', $from)
                            ->whereDate('Date_for_Join', '<=', $to);
                    });
            });

            return $query->get()->map(static fn (object $row): array => (array) $row)->all();
        } catch (Throwable $e) {
            Log::warning('ControlRoom HSECM TBC gagal: '.$e->getMessage());

            return [];
        }
    }

    private function applySiteFilter(Builder $query, ControlRoomSiteCode $site, string $column): void
    {
        if ($site === ControlRoomSiteCode::HeadOffice) {
            return;
        }

        $table = (string) $query->from;
        if ($table === '' || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $query->where($column, $site->sourceKey());
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isUncovered(array $row): bool
    {
        $status = mb_strtolower(trim((string) ($row['Status_Coverage_dalam_1_Week'] ?? '')));
        if ($status !== '') {
            if (str_contains($status, 'tidak') || str_contains($status, 'belum') || str_contains($status, 'gap')) {
                return true;
            }
            if (str_contains($status, 'tercover')) {
                return false;
            }
        }

        $raw = $row['Tercover'] ?? null;
        if (is_numeric($raw)) {
            return (float) $raw < 1;
        }

        return $status === '';
    }

    private function isGoldenRuleViolation(string $rule): bool
    {
        $rule = trim($rule);
        if ($rule === '') {
            return false;
        }

        return ! str_starts_with(mb_strtolower($rule), 'tidak melanggar');
    }

    /**
     * Coverage personil saat jadwal jaga: DISTINCT (lokasi + detil lokasi)
     * dari laporan SAP. Kritis mengikuti rumus CONTAINS Tableau
     * (LocationReader::isCritical).
     *
     * @param  list<array<string, mixed>>  $findings
     * @param  list<array<string, mixed>>  $scheduleDays
     * @return list<array{name: string, lokasi: int, kritis: int, lead: bool}>
     */
    public function personnelCoverageFromFindings(array $findings, array $scheduleDays): array
    {
        $namesBySid = $this->namesBySid($scheduleDays);
        $pairsBySid = [];
        foreach ($namesBySid as $sid => $_name) {
            $pairsBySid[$sid] = [];
        }

        foreach ($findings as $finding) {
            $sid = strtoupper(trim((string) ($finding['sid'] ?? '')));
            if ($sid === '' || ! isset($namesBySid[$sid])) {
                continue;
            }

            $lokasi = trim((string) ($finding['lokasi'] ?? ''));
            $detil = trim((string) ($finding['detil_lokasi'] ?? ''));
            $key = $this->locationKey($lokasi, $detil);
            if ($key === '') {
                continue;
            }

            $pairsBySid[$sid][$key] = ['lokasi' => $lokasi, 'detil' => $detil];
        }

        $rows = [];
        foreach ($namesBySid as $sid => $name) {
            $kritis = 0;
            foreach ($pairsBySid[$sid] as $pair) {
                if ($this->locations->isCritical($pair['lokasi'], $pair['detil'])) {
                    $kritis++;
                }
            }

            $rows[] = [
                'name' => $name,
                'lokasi' => count($pairsBySid[$sid]),
                'kritis' => $kritis,
                'lead' => false,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $byLokasi = $b['lokasi'] <=> $a['lokasi'];
            if ($byLokasi !== 0) {
                return $byLokasi;
            }

            return $b['kritis'] <=> $a['kritis'];
        });

        if ($rows !== []) {
            $rows[0]['lead'] = true;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $scheduleDays
     * @return array<string, string>
     */
    private function namesBySid(array $scheduleDays): array
    {
        $names = [];
        foreach ($scheduleDays as $day) {
            foreach (['s1', 's2'] as $shiftKey) {
                foreach ($day[$shiftKey] ?? [] as $person) {
                    $sid = strtoupper(trim((string) ($person['sid'] ?? '')));
                    $name = trim((string) ($person['name'] ?? ''));
                    if ($sid !== '' && $name !== '' && $name !== '—') {
                        $names[$sid] = $name;
                    }
                }
            }
        }

        return $names;
    }

    /**
     * @param  list<array<string, mixed>>  $tbcRows
     * @return array<string, int>
     */
    private function tbcCountsByPelapor(array $tbcRows): array
    {
        $counts = [];
        foreach ($tbcRows as $row) {
            $name = $this->normalizeName((string) ($row['pelapor_all_karyawan'] ?? ''));
            if ($name === '') {
                continue;
            }
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        return $counts;
    }

    private function locationKey(string $lokasi, string $detil): string
    {
        $lokasi = mb_strtolower(trim($lokasi));
        $detil = mb_strtolower(trim($detil));
        if ($lokasi === '' && $detil === '') {
            return '';
        }

        return $lokasi.'|'.$detil;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
    }
}
