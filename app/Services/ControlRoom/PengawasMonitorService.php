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

    /** Snapshot terakhir yang sukses — dipakai "Semua Site" selagi warm berikutnya berjalan. */
    private const LAST_GOOD_SECONDS = 604800;

    /** Minggu yang diminta user untuk "Semua Site" tapi belum punya snapshot — diambil warmer. */
    private const PENDING_WEEKS_KEY = 'control-room:pengawas-monitor:pending-all-weeks';

    private const PENDING_WEEKS_SECONDS = 86400;

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
        $cached = Cache::get($this->freshKey($site, $period));
        if (is_array($cached)) {
            return $cached;
        }

        return $this->warm($site, $period);
    }

    /**
     * "Semua Site" (±250 SID) terlalu berat untuk request HTTP — ±40 query OBDS
     * berurutan melewati timeout ALB (504). Halaman hanya membaca snapshot;
     * perhitungannya oleh `control-room:pengawas-warm` di scheduler.
     *
     * @return array<string, mixed>
     */
    public function snapshotOrQueue(?ControlRoomSiteCode $site, ControlRoomIsoWeekPeriod $period): array
    {
        $cached = Cache::get($this->freshKey($site, $period)) ?? Cache::get($this->lastGoodKey($site, $period));
        if (is_array($cached)) {
            return $cached;
        }

        $pending = $this->pendingWeeks();
        if (! in_array($period->isoWeekValue(), $pending, true)) {
            Cache::put(self::PENDING_WEEKS_KEY, [...$pending, $period->isoWeekValue()], self::PENDING_WEEKS_SECONDS);
        }

        return $this->placeholder($site, $period);
    }

    /**
     * Hitung ulang dari OBDS dan simpan (fresh + last-good) bila sukses.
     *
     * @return array<string, mixed>
     */
    public function warm(?ControlRoomSiteCode $site, ControlRoomIsoWeekPeriod $period): array
    {
        $payload = $this->buildUncached($site, $period, CarbonImmutable::now()->startOfDay());
        // Gagal OBDS tidak di-cache supaya muat ulang berikutnya mencoba lagi.
        if ($payload['loaded'] && $payload['dataQuality']['loaded']) {
            $payload['generatedAt'] = CarbonImmutable::now()->toDateTimeString();
            Cache::put($this->freshKey($site, $period), $payload, self::PAGE_CACHE_SECONDS);
            Cache::put($this->lastGoodKey($site, $period), $payload, self::LAST_GOOD_SECONDS);
        }

        return $payload;
    }

    /**
     * @return list<string> nilai iso_week (mis. 2026-W41)
     */
    public function pendingWeeks(): array
    {
        $weeks = Cache::get(self::PENDING_WEEKS_KEY);

        return is_array($weeks) ? array_values($weeks) : [];
    }

    public function forgetPendingWeek(string $isoWeek): void
    {
        $remaining = array_values(array_diff($this->pendingWeeks(), [$isoWeek]));
        Cache::put(self::PENDING_WEEKS_KEY, $remaining, self::PENDING_WEEKS_SECONDS);
    }

    private function freshKey(?ControlRoomSiteCode $site, ControlRoomIsoWeekPeriod $period): string
    {
        return sprintf(
            'control-room:pengawas-monitor:v2:%s:%s:%s:%s',
            $site?->value ?? 'ALL',
            $period->start->toDateString(),
            CarbonImmutable::now()->toDateString(),
            $this->rosterHash(),
        );
    }

    private function lastGoodKey(?ControlRoomSiteCode $site, ControlRoomIsoWeekPeriod $period): string
    {
        return sprintf(
            'control-room:pengawas-monitor:last-good:v1:%s:%s:%s',
            $site?->value ?? 'ALL',
            $period->start->toDateString(),
            $this->rosterHash(),
        );
    }

    private function rosterHash(): string
    {
        return hash('crc32b', serialize(config('control-room-pengawas.list', [])));
    }

    /**
     * Payload kosong berbentuk sama dengan build() — tanpa satu pun query OBDS.
     *
     * @return array<string, mixed>
     */
    private function placeholder(?ControlRoomSiteCode $site, ControlRoomIsoWeekPeriod $period): array
    {
        $people = $this->roster->forSite($site);
        $dates = $this->runningDates($period->start, CarbonImmutable::now()->startOfDay());
        $rows = $this->achievement->rows($people, $dates, [], false);

        return [
            'loaded' => false,
            'preparing' => true,
            'people' => count($people),
            'days' => $this->achievement->dayHeads($dates),
            'rows' => $rows,
            'kpi' => $this->achievement->kpiCards($rows, null, []),
            'personnelCoverage' => [],
            'pareto' => ['s1' => [], 's2' => []],
            'highlight' => [
                'goldenRules' => [], 'blindspotCount' => 0, 'blindspotTotal' => 0,
                'tbcPercentage' => null, 'blindspotItems' => [], 'tbcItems' => [],
            ],
            'quality' => [],
            'dataQuality' => [
                'loaded' => false,
                'rows' => [],
                'kpi' => [
                    'personnel' => 0, 'avg_composite' => null, 'avg_mix' => null,
                    'labels' => ['Belum ada temuan' => 0, 'Perlu perbaikan' => 0, 'Cukup' => 0, 'Baik' => 0],
                ],
            ],
        ];
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
