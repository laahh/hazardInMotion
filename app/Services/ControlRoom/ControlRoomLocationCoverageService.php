<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\Reference\LocationReader;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Coverage lokasi master vs SAP minggu terpilih (hazard/inspeksi/observasi/OAK).
 * Ter-cover = ada laporan SAP di lokasi+detil yang sama (bukan hanya SID jaga).
 */
final class ControlRoomLocationCoverageService
{
    private const PAGE_CACHE_SECONDS = 180;

    public function __construct(
        private readonly LocationReader $locations,
        private readonly ControlRoomSapQualityFindingsReader $qualityFindings,
        private readonly ControlRoomSapDutyReader $dutyWindow,
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
            'control-room:location-coverage:v2:%s:%s:%s',
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
            $lastAt = $this->latestCoveredAt($lokasi, $detil, $coveredAt);
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
     * @return array{loaded: bool, kpi: array{total: int, covered: int, uncovered: int, percent: float}, rows: list<array<string, mixed>>, attention: list<array<string, mixed>>}
     */
    private function buildUncached(
        ControlRoomSiteCode $site,
        CarbonImmutable $weekStart,
        CarbonImmutable $today,
    ): array {
        $master = $this->locations->forCoverage($site)->all();
        $weekEnd = $weekStart->addDays(6);
        $lastDay = $weekEnd->lessThan($today) ? $weekEnd : $today;
        $sap = $this->qualityFindings->locationHits(
            $weekStart->startOfDay(),
            $this->dutyWindow->reportingWindow($lastDay)['end'],
        );

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
            $at = trim((string) ($finding['at'] ?? ''));
            if ($at === '') {
                continue;
            }
            $detil = (string) ($finding['detil_lokasi'] ?? $finding['detail_lokasi'] ?? '');
            foreach ($this->locationKeys((string) ($finding['lokasi'] ?? ''), $detil) as $key) {
                if (! isset($covered[$key]) || $at > $covered[$key]) {
                    $covered[$key] = $at;
                }
            }
        }

        return $covered;
    }

    /**
     * @param  array<string, string>  $coveredAt
     */
    private function latestCoveredAt(string $lokasi, string $detil, array $coveredAt): ?string
    {
        $latest = null;
        foreach ($this->locationKeys($lokasi, $detil) as $key) {
            $at = $coveredAt[$key] ?? null;
            if (! is_string($at) || $at === '') {
                continue;
            }
            if ($latest === null || $at > $latest) {
                $latest = $at;
            }
        }

        return $latest;
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
