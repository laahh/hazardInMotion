<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\Reference\LocationReader;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Coverage lokasi master vs SAP minggu terpilih (hazard/inspeksi/observasi/OAK).
 * Roster = SID jadwal 8 site papan; SAP = findings ter-chunk.
 */
final class ControlRoomLocationCoverageService
{
    private const PAGE_CACHE_SECONDS = 180;

    public function __construct(
        private readonly LocationReader $locations,
        private readonly ControlRoomSapQualityFindingsReader $qualityFindings,
    ) {}

    /**
     * @return array{
     *     loaded: bool,
     *     kpi: array{total: int, covered: int, uncovered: int, percent: float},
     *     rows: list<array<string, mixed>>,
     *     attention: list<array<string, mixed>>
     * }
     */
    public function build(
        ControlRoomSiteCode $site,
        CarbonImmutable $weekStart,
        ?CarbonInterface $now = null,
    ): array {
        $today = CarbonImmutable::parse($now ?? now())->startOfDay();
        $cacheKey = sprintf(
            'control-room:location-coverage:v1:%s:%s:%s',
            $weekStart->toDateString(),
            $site->value,
            $today->toDateString(),
        );

        /** @var array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>} */
        return Cache::remember($cacheKey, self::PAGE_CACHE_SECONDS, function () use ($site, $weekStart, $today): array {
            return $this->buildUncached($site, $weekStart, $today);
        });
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
        $rows = [];
        $attention = [];
        $covered = 0;

        foreach ($master as $item) {
            $lokasi = (string) ($item['lokasi'] ?? '');
            $detil = (string) ($item['detail_lokasi'] ?? '');
            $key = $this->locationKey($lokasi, $detil);
            $lastAt = $key !== '' ? ($coveredAt[$key] ?? null) : null;
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
            ];
            $rows[] = $row;
            if ($isCritical && ! $isCovered) {
                $attention[] = $row;
            }
        }

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

        usort($attention, function (array $a, array $b): int {
            $byLokasi = strcasecmp((string) $a['lokasi'], (string) $b['lokasi']);
            if ($byLokasi !== 0) {
                return $byLokasi;
            }

            return strcasecmp((string) $a['detail_lokasi'], (string) $b['detail_lokasi']);
        });

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

    public function locationKey(string $lokasi, string $detil): string
    {
        $lokasi = mb_strtolower(trim($lokasi));
        $detil = mb_strtolower(trim($detil));
        if ($lokasi === '' && $detil === '') {
            return '';
        }

        return $lokasi.'|'.$detil;
    }

    /**
     * @return array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>}
     */
    private function buildUncached(
        ControlRoomSiteCode $site,
        CarbonImmutable $weekStart,
        CarbonImmutable $today,
    ): array {
        $master = $this->locations->forCoverage($site)->all();
        $roster = $this->boardRoster($weekStart, $today);
        if ($roster['sids'] === []) {
            return $this->evaluate($master, [], true);
        }

        $sap = $this->qualityFindings->forSids($roster['sids'], $weekStart, $roster['last_duty']);

        return $this->evaluate($master, $this->coveredAt($sap['findings']), $sap['loaded']);
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return array<string, string>
     */
    public function coveredAt(array $findings): array
    {
        $covered = [];
        foreach ($findings as $finding) {
            $key = $this->locationKey(
                (string) ($finding['lokasi'] ?? ''),
                (string) ($finding['detil_lokasi'] ?? ''),
            );
            if ($key === '') {
                continue;
            }
            $at = trim((string) ($finding['at'] ?? ''));
            if ($at === '') {
                continue;
            }
            if (! isset($covered[$key]) || $at > $covered[$key]) {
                $covered[$key] = $at;
            }
        }

        return $covered;
    }

    /**
     * @return array{sids: list<string>, last_duty: CarbonImmutable}
     */
    private function boardRoster(CarbonImmutable $weekStart, CarbonImmutable $today): array
    {
        $visibleUntil = $weekStart->addDays(6)->lessThan($today) ? $weekStart->addDays(6) : $today;
        $plans = SchedulePlan::query()
            ->select(['personnel_source_key', 'date'])
            ->whereIn('site_code', ControlRoomSiteDutyBoardService::BOARD_SITE_CODES)
            ->whereBetween('date', [$weekStart->toDateString(), $visibleUntil->toDateString()])
            ->get();

        $sids = [];
        $dates = [];
        foreach ($plans as $plan) {
            $sid = strtoupper(trim((string) $plan->personnel_source_key));
            if ($sid === '') {
                continue;
            }
            $sids[$sid] = $sid;
            $date = $plan->date instanceof CarbonInterface
                ? $plan->date->toDateString()
                : (string) $plan->date;
            if ($date !== '') {
                $dates[] = $date;
            }
        }

        return [
            'sids' => array_values($sids),
            'last_duty' => $dates === []
                ? $visibleUntil
                : CarbonImmutable::parse((string) max($dates)),
        ];
    }
}
