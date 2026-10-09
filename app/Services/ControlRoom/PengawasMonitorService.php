<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Services\ControlRoom\Metrics\ControlRoomSapQualityEvaluator;
use App\Services\ControlRoom\Reference\PengawasRoster;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;

/**
 * Monitoring Pengawas Control Room: Pencapaian, Coverage Personil, Pareto,
 * Highlight, Kualitas Temuan, dan Data Quality — sama dengan dashboard tapi
 * rosternya daftar pengawas (bukan jadwal jaga) dan jendela laporan 1 hari.
 */
final class PengawasMonitorService
{
    public const WINDOW_DAYS = 1;

    private const PAGE_CACHE_SECONDS = 180;

    private readonly ControlRoomSapDutyReader $dailyWindow;

    public function __construct(
        private readonly Container $container,
        private readonly PengawasRoster $roster,
        private readonly PengawasAchievementBuilder $achievement,
        ControlRoomSapDutyReader $dutyReader,
    ) {
        $this->dailyWindow = $dutyReader->withWindowDays(self::WINDOW_DAYS);
    }

    public function dutyReader(): ControlRoomSapDutyReader
    {
        return $this->dailyWindow;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(?ControlRoomSiteCode $site, ControlRoomIsoWeekPeriod $period): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $cacheKey = sprintf(
            'control-room:pengawas-monitor:v1:%s:%s:%s:%s',
            $site?->value ?? 'ALL',
            $period->start->toDateString(),
            $today->toDateString(),
            hash('crc32b', serialize(config('control-room-pengawas.list', []))),
        );

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $payload = $this->buildUncached($site, $period, $today);
        // Gagal OBDS tidak di-cache supaya muat ulang berikutnya mencoba lagi.
        if ($payload['loaded'] && $payload['dataQuality']['loaded']) {
            Cache::put($cacheKey, $payload, self::PAGE_CACHE_SECONDS);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUncached(?ControlRoomSiteCode $site, ControlRoomIsoWeekPeriod $period, CarbonImmutable $today): array
    {
        $people = $this->roster->forSite($site);
        $dates = $this->runningDates($period->start, $today);
        $days = $this->scheduleDays($people, $dates);

        $weekCounts = $this->container->make(ControlRoomSapWeekCountsReader::class, ['dutyWindow' => $this->dailyWindow]);
        $sapWeek = $weekCounts->forScheduleDays($days);
        $insights = $this->container
            ->make(ControlRoomDashboardInsightsAssembler::class, ['dutyWindow' => $this->dailyWindow])
            ->build(
                $site ?? ControlRoomSiteCode::HeadOffice,
                $period->start,
                $period->end,
                $days,
                $sapWeek['findings'] ?? [],
                $sapWeek['loaded'],
            );

        $rows = $this->achievement->rows($people, $dates, $sapWeek['counts'], $sapWeek['loaded'], $insights['tbcMetaBySid'] ?? []);

        return [
            'loaded' => $sapWeek['loaded'],
            'people' => count($people),
            'days' => $this->achievement->dayHeads($dates),
            'rows' => $rows,
            'kpi' => $this->achievement->kpiCards(
                $rows,
                $insights['highlight']['tbcPercentage'] ?? null,
                $this->previousRows($people, $period, $weekCounts),
            ),
            'personnelCoverage' => $insights['personnelCoverage'] ?? [],
            'pareto' => $insights['pareto'] ?? ['s1' => [], 's2' => []],
            'highlight' => $insights['highlight'] ?? [
                'goldenRules' => [], 'blindspotCount' => 0, 'blindspotTotal' => 0,
                'tbcPercentage' => null, 'blindspotItems' => [], 'tbcItems' => [],
            ],
            'quality' => $insights['quality'] ?? [],
            'dataQuality' => $this->dataQuality($people, $dates, $period->start),
        ];
    }

    /**
     * Minggu lalu hanya dari cache (tidak memukul OBDS) — delta KPI kosong bila belum ada.
     *
     * @param  list<array{sid: string, name: string, site: ControlRoomSiteCode}>  $people
     * @return list<array<string, mixed>>
     */
    private function previousRows(array $people, ControlRoomIsoWeekPeriod $period, ControlRoomSapWeekCountsReader $weekCounts): array
    {
        $prev = $period->previous();
        $dates = $this->runningDates($prev->start, $prev->end);
        $cached = $weekCounts->cachedForScheduleDays($this->scheduleDays($people, $dates));
        if ($cached === null) {
            return [];
        }

        return $this->achievement->rows($people, $dates, $cached['counts'], true);
    }

    /**
     * @param  list<array{sid: string, name: string, site: ControlRoomSiteCode}>  $people
     * @param  list<string>  $dates
     * @return array{loaded: bool, rows: list<array<string, mixed>>, kpi: array<string, mixed>}
     */
    private function dataQuality(array $people, array $dates, CarbonImmutable $weekStart): array
    {
        $roster = [];
        if ($dates !== []) {
            foreach ($people as $person) {
                $roster[$person['sid']] = [
                    'sid' => $person['sid'],
                    'name' => $person['name'],
                    'sites' => [$person['site']->value => $person['site']->label()],
                    'dates' => array_combine($dates, $dates),
                ];
            }
        }

        $service = $this->container->make(ControlRoomDataQualityService::class, [
            'qualityFindings' => $this->container->make(ControlRoomSapQualityFindingsReader::class, ['dutyWindow' => $this->dailyWindow]),
            // Jendela 1 hari membuat sumbu waktu (% laporan di hari H) selalu 100 — tidak dinilai.
            'evaluator' => $this->container->make(ControlRoomSapQualityEvaluator::class, [
                'dutyWindow' => $this->dailyWindow,
                'excludedAxes' => [ControlRoomSapQualityEvaluator::AXIS_TIMING],
            ]),
        ]);

        return $service->evaluateRoster($roster, $weekStart);
    }

    /**
     * Pengawas dianggap bertugas tiap hari: tiap tanggal berjalan berisi semua
     * pengawas di slot S1 (format sama dengan jadwal dashboard).
     *
     * @param  list<array{sid: string, name: string, site: ControlRoomSiteCode}>  $people
     * @param  list<string>  $dates
     * @return list<array<string, mixed>>
     */
    private function scheduleDays(array $people, array $dates): array
    {
        $slot = array_map(static fn (array $person): array => [
            'sid' => $person['sid'],
            'name' => $person['name'],
            'status' => 'sesuai',
            'shift' => ControlRoomShiftCode::S1->value,
        ], $people);

        return array_map(static fn (string $date): array => ['date' => $date, 's1' => $slot, 's2' => []], $dates);
    }

    /**
     * @return list<string>
     */
    private function runningDates(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $dates = [];
        $end = $from->addDays(6)->min($until);
        for ($day = $from->startOfDay(); $day->lte($end); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }
}
