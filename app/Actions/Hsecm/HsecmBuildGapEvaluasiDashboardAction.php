<?php

declare(strict_types=1);

namespace App\Actions\Hsecm;

use App\Models\Hsecm\HsecmTasklistItem;
use App\Services\Hsecm\HsecmDashboardService;
use App\Services\Hsecm\HsecmDatabaseRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluasi gap harian (scrape D vs D-1) + efektivitas pasca tindak lanjut tasklist.
 * Termasuk ringkasan matriks & metrik per parameter program.
 */
final class HsecmBuildGapEvaluasiDashboardAction
{
    private const DETAIL_LIMIT = 40;

    private const SCOPE_DETAIL_LIMIT = 40;

    /** Max hari kalender di date range (performa). */
    private const MAX_RANGE_DAYS = 3;

    /** Cache hasil dashboard singkat (detik). */
    private const DASHBOARD_CACHE_TTL = 120;

    /**
     * Urutan & label parameter (selaras mockup Gap Evaluasi).
     *
     * @var array<string, string>
     */
    private const PROGRAM_LABELS = [
        'layer1-tanpa-sap' => 'Layer 1 tanpa SAP',
        'coverage-area' => 'Coverage Area Kritis Daily',
        'tbc-blindspot' => 'Blindspot TBC',
        'hazard-overdue' => 'Task Follow-up Overdue',
        'hazard-submitted' => 'Submitted Over 24 Hours',
        'ikk-compliance' => 'Compliance IKK',
        'aggregator-fill' => 'Tidak Mengisi Aggregator',
        'ftw-merah' => 'FTW Merah',
        'hazard-rootcause' => 'Hazard Related Incident',
    ];

    /** Program tanpa perusahaan — matriks 1 baris per Site. */
    private const SITE_ONLY_PROGRAMS = [
        'coverage-area',
        'hazard-rootcause',
    ];

    public function __construct(
        private readonly HsecmDashboardService $dashboardService,
        private readonly HsecmDatabaseRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function execute(array $filters): array
    {
        $probeTable = HsecmDashboardService::DATASETS['sap-rfid']['table'];
        $dates = $this->repository->listDistinctBatchSlotDates($probeTable, 90);
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters, $dates);

        $cacheKey = 'hsecm.gap_eval.v3.'.md5(json_encode([
            'site' => (string) ($filters['site'] ?? ''),
            'perusahaan' => (string) ($filters['perusahaan'] ?? ''),
            'q' => (string) ($filters['q'] ?? ''),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'week' => (string) ($filters['week'] ?? ''),
            'year' => (string) ($filters['year'] ?? ''),
        ], JSON_THROW_ON_ERROR));

        /** @var array<string, mixed> $dashboard */
        $dashboard = Cache::remember(
            $cacheKey,
            self::DASHBOARD_CACHE_TTL,
            fn (): array => $this->buildDashboard($filters, $dates, $dateFrom, $dateTo, $probeTable)
        );

        return $dashboard;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $dates
     * @return array<string, mixed>
     */
    private function buildDashboard(
        array $filters,
        array $dates,
        string $dateFrom,
        string $dateTo,
        string $probeTable,
    ): array {
        $rangeDates = $this->datesInRange($dates, $dateFrom, $dateTo);
        if ($rangeDates === []) {
            // Tidak ada scrape di range — tetap pakai date_from sebagai titik evaluasi jika ada slot.
            $rangeDates = [$dateFrom];
        }

        $prevDate = $this->resolvePreviousDate($dates, $dateFrom);

        $slotsD = $this->collectSlotsForDates($probeTable, $rangeDates);
        $slotsPrev = [];
        if ($prevDate !== null) {
            $latestPrev = $this->repository->latestBatchSlotOnDate($probeTable, $prevDate);
            if ($latestPrev !== null) {
                $slotsPrev = [$latestPrev];
            }
        }

        $collected = $this->collectRangeIdentityMap($filters, $slotsD, $rangeDates);
        $mapD = $collected['map'];
        /** @var array<string, int> $dayCountByIdentity */
        $dayCountByIdentity = $collected['day_counts'];

        $mapPrev = $this->collectDayIdentityMap($filters, $slotsPrev);

        $scrape = $this->classifyScrape($mapD, $mapPrev, $prevDate, $slotsPrev, $dayCountByIdentity);

        $tasklist = $this->buildTasklistEffectiveness(
            $filters,
            $mapD,
            $dateTo,
        );

        /** @var array<string, int> $hilangStreakByIdentity */
        $hilangStreakByIdentity = $scrape['hilang_streak_by_identity'] ?? [];
        /** @var list<string> $hilangIdentities */
        $hilangIdentities = $scrape['hilang_identities'] ?? [];

        $hilangTanpaTindaklanjut = 0;
        foreach ($hilangIdentities as $identity) {
            if (! isset($tasklist['acted_identities'][$identity])) {
                $hilangTanpaTindaklanjut++;
            }
        }
        $tasklist['cards']['hilang_tanpa_tindaklanjut'] = $hilangTanpaTindaklanjut;

        $tindaklanjutTanpaPerulangan = 0;
        foreach ($tasklist['berhasil_identities'] ?? [] as $identity) {
            $streak = (int) ($hilangStreakByIdentity[$identity] ?? 1);
            if ($streak < 2) {
                $tindaklanjutTanpaPerulangan++;
            }
        }
        $tasklist['cards']['tindaklanjut_tanpa_perulangan'] = $tindaklanjutTanpaPerulangan;

        $programs = $this->buildProgramMetrics(
            $scrape['all_details'] ?? [],
            $tasklist,
            $hilangStreakByIdentity,
        );
        $overview = $this->buildOverviewCards($scrape, $tasklist, $programs);

        unset(
            $tasklist['acted_identities'],
            $tasklist['berhasil_identities'],
            $scrape['hilang_identities'],
            $scrape['hilang_streak_by_identity'],
            $scrape['all_details'],
        );
        // Jangan kirim tabel detail besar ke Blade (cukup cards + modal matriks).
        $scrape['details'] = [
            'tetap' => [],
            'hilang' => [],
            'baru' => [],
            'kembali' => [],
        ];
        $scrape['truncated'] = [
            'tetap' => 0,
            'hilang' => 0,
            'baru' => 0,
            'kembali' => 0,
        ];
        $tasklist['details'] = [
            'tindaklanjut_berhasil' => [],
            'tindaklanjut_belum_efektif' => [],
            'belum_tindaklanjut_masih_gap' => [],
        ];

        $periodLabel = $dateFrom === $dateTo
            ? (
                $prevDate !== null
                    ? 'Evaluasi '.$dateFrom.' vs '.$prevDate.' (hari kalender scrape)'
                    : 'Evaluasi '.$dateFrom.' (belum ada hari pembanding)'
            )
            : (
                $prevDate !== null
                    ? 'Evaluasi '.$dateFrom.' s/d '.$dateTo.' vs pembanding '.$prevDate
                    : 'Evaluasi '.$dateFrom.' s/d '.$dateTo.' (belum ada hari pembanding)'
            );

        return [
            'filters' => array_merge($filters, [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]),
            'filter_options' => $this->dashboardService->getFilterOptions(),
            'period_label' => $periodLabel,
            'eval_date' => $dateTo,
            'prev_date' => $prevDate,
            'slots_d' => $slotsD,
            'slots_prev' => $slotsPrev,
            'range_dates' => $rangeDates,
            'overview' => $overview,
            'programs' => $programs,
            'scrape' => $scrape,
            'tasklist' => $tasklist,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $availableDates  desc Y-m-d
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(array $filters, array $availableDates): array
    {
        $fallback = $availableDates[0] ?? Carbon::now('Asia/Makassar')->format('Y-m-d');

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        if ($dateFrom === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) {
            $dateFrom = $fallback;
        }
        if ($dateTo === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1) {
            $dateTo = $dateFrom;
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        // Batasi rentang agar performa tetap wajar.
        $from = Carbon::parse($dateFrom, 'Asia/Makassar')->startOfDay();
        $to = Carbon::parse($dateTo, 'Asia/Makassar')->startOfDay();
        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $dateFrom = $to->copy()->subDays(self::MAX_RANGE_DAYS)->format('Y-m-d');
            $dateTo = $to->format('Y-m-d');
        }

        return [$dateFrom, $dateTo];
    }

    /**
     * Irisan tanggal scrape yang tersedia dengan range filter.
     *
     * @param  list<string>  $availableDates
     * @return list<string>  ascending
     */
    private function datesInRange(array $availableDates, string $dateFrom, string $dateTo): array
    {
        $matched = [];
        foreach ($availableDates as $date) {
            if ($date >= $dateFrom && $date <= $dateTo) {
                $matched[] = $date;
            }
        }

        sort($matched);

        return $matched;
    }

    /**
     * Satu slot per hari (terakhir) — cukup untuk identitas gap harian, jauh lebih ringan.
     *
     * @param  list<string>  $dates
     * @return list<string>
     */
    private function collectSlotsForDates(string $table, array $dates): array
    {
        $slots = [];
        foreach ($dates as $date) {
            $latest = $this->repository->latestBatchSlotOnDate($table, $date);
            if ($latest === null) {
                continue;
            }
            $slots[] = $latest;
        }

        return $slots;
    }

    /**
     * @param  list<string>  $dates
     */
    private function resolvePreviousDate(array $dates, string $evalDate): ?string
    {
        foreach ($dates as $date) {
            if ($date < $evalDate) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $slots
     * @return array<string, array<string, mixed>>
     */
    private function collectDayIdentityMap(array $filters, array $slots): array
    {
        return $this->collectRangeIdentityMap($filters, $slots, [])['map'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $slots
     * @param  list<string>  $rangeDates  untuk hitung distinct hari per identity (opsional)
     * @return array{map: array<string, array<string, mixed>>, day_counts: array<string, int>}
     */
    private function collectRangeIdentityMap(array $filters, array $slots, array $rangeDates): array
    {
        /** @var array<string, array<string, mixed>> $map */
        $map = [];
        /** @var array<string, array<string, true>> $daysByIdentity */
        $daysByIdentity = [];
        /** @var array<string, list<array<string, mixed>>> $slotCache */
        $slotCache = [];

        $baseFilters = [
            'site' => (string) ($filters['site'] ?? ''),
            'perusahaan' => (string) ($filters['perusahaan'] ?? ''),
            'week' => '',
            'year' => '',
            'date_from' => '',
            'date_to' => '',
            'q' => (string) ($filters['q'] ?? ''),
        ];

        // Newest-first: row display diambil dari scrape terbaru, day_count tetap dihitung semua.
        $orderedSlots = array_reverse($slots);

        foreach ($orderedSlots as $slot) {
            $slotDate = $this->slotToDate($slot);
            $cacheKey = $slot.'|'.$baseFilters['site'].'|'.$baseFilters['perusahaan'].'|'.$baseFilters['q'];
            if (! isset($slotCache[$cacheKey])) {
                $slotFilters = $this->dashboardService->withBatchContext(
                    $baseFilters,
                    $slot,
                    'snapshot',
                );
                $slotCache[$cacheKey] = $this->dashboardService->extractGapIdentityRowsLean($slotFilters);
            }

            foreach ($slotCache[$cacheKey] as $row) {
                $identity = (string) ($row['identity'] ?? '');
                if ($identity === '') {
                    continue;
                }
                if (! isset($map[$identity])) {
                    // Buang payload berat di memory map (cukup field klasifikasi).
                    unset($row['payload'], $row['action_hint']);
                    $map[$identity] = $row;
                }
                if ($slotDate !== null && ($rangeDates === [] || in_array($slotDate, $rangeDates, true))) {
                    $daysByIdentity[$identity][$slotDate] = true;
                }
            }
        }

        $dayCounts = [];
        foreach ($daysByIdentity as $identity => $days) {
            $dayCounts[$identity] = count($days);
        }

        return [
            'map' => $map,
            'day_counts' => $dayCounts,
        ];
    }

    private function slotToDate(string $slot): ?string
    {
        $slot = trim($slot);
        if ($slot === '') {
            return null;
        }

        try {
            return Carbon::parse($slot, 'Asia/Makassar')->format('Y-m-d');
        } catch (\Throwable) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $slot, $m) === 1) {
                return $m[1];
            }

            return null;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $mapD
     * @param  array<string, array<string, mixed>>  $mapPrev
     * @param  list<string>  $slotsPrev
     * @param  array<string, int>  $dayCountByIdentity
     * @return array<string, mixed>
     */
    private function classifyScrape(
        array $mapD,
        array $mapPrev,
        ?string $prevDate,
        array $slotsPrev,
        array $dayCountByIdentity = [],
    ): array {
        $tetap = [];
        $hilang = [];
        $baru = [];
        $kembali = [];

        $refSlotPrev = $slotsPrev !== [] ? (string) end($slotsPrev) : null;

        /** @var array<string, list<string>> $keysByTable */
        $keysByTable = [];
        foreach ($mapPrev as $row) {
            $table = (string) ($row['table'] ?? '');
            $bk = trim((string) ($row['business_key'] ?? ''));
            if ($table !== '' && $bk !== '') {
                $keysByTable[$table][] = $bk;
            }
        }

        /** @var array<string, array<string, int>> $streakByTable */
        $streakByTable = [];
        if ($refSlotPrev !== null) {
            foreach ($keysByTable as $table => $keys) {
                $streakByTable[$table] = $this->repository->countConsecutiveStreakByKeys(
                    $table,
                    array_values(array_unique($keys)),
                    $refSlotPrev,
                );
            }
        }

        foreach ($mapPrev as $identity => $row) {
            $table = (string) ($row['table'] ?? '');
            $bk = trim((string) ($row['business_key'] ?? ''));
        // Streak dari repository; fallback 1 (payload sengaja tidak dibawa di map lean).
            $slotStreak = (int) ($streakByTable[$table][$bk] ?? 1);
            $dayStreak = max(1, (int) ceil($slotStreak / 2));
            // Perulangan dalam range: naikkan streak jika muncul multi-hari di periode filter.
            $rangeDays = (int) ($dayCountByIdentity[$identity] ?? 0);
            if ($rangeDays > $dayStreak) {
                $dayStreak = $rangeDays;
            }

            if (isset($mapD[$identity])) {
                // Hadir di periode evaluasi + hari pembanding: streak minimal 2
                // hanya jika memang multi-hari (range atau streak histori).
                // Streak 1 = gap sekali (tetap masuk Total Gap, bukan Perulangan).
                $tetap[] = $this->detailRow($row, 'tetap', $dayStreak);
            } else {
                $hilang[] = $this->detailRow($row, 'hilang', $dayStreak);
            }
        }

        /** @var array<string, list<string>> $baruKeysByTable */
        $baruKeysByTable = [];
        foreach ($mapD as $identity => $row) {
            if (isset($mapPrev[$identity])) {
                continue;
            }
            $table = (string) ($row['table'] ?? '');
            $bk = trim((string) ($row['business_key'] ?? ''));
            if ($table !== '' && $bk !== '') {
                $baruKeysByTable[$table][] = $bk;
            }
        }

        /** @var array<string, array<string, true>> $presentBefore */
        $presentBefore = [];
        if ($prevDate !== null) {
            foreach ($baruKeysByTable as $table => $keys) {
                $presentBefore[$table] = $this->repository->businessKeysPresentBeforeDate(
                    $table,
                    array_values(array_unique($keys)),
                    $prevDate,
                );
            }
        }

        foreach ($mapD as $identity => $row) {
            if (isset($mapPrev[$identity])) {
                continue;
            }
            $table = (string) ($row['table'] ?? '');
            $bk = trim((string) ($row['business_key'] ?? ''));
            $rangeDays = max(1, (int) ($dayCountByIdentity[$identity] ?? 1));
            $isReopen = $bk !== '' && isset($presentBefore[$table][$bk]);
            if ($isReopen) {
                $kembali[] = $this->detailRow($row, 'kembali', $rangeDays);
            } else {
                $baru[] = $this->detailRow($row, 'baru', $rangeDays);
            }
        }

        $perbaikanTanpaPerulangan = 0;
        $perbaikanDenganPerulangan = 0;
        /** @var list<string> $hilangIdentities */
        $hilangIdentities = [];
        /** @var array<string, int> $hilangStreakByIdentity */
        $hilangStreakByIdentity = [];
        foreach ($hilang as $row) {
            $identity = $this->rowIdentity($row);
            $hilangIdentities[] = $identity;
            $hilangStreakByIdentity[$identity] = (int) ($row['day_streak'] ?? 1);
            if ((int) ($row['day_streak'] ?? 1) <= 1) {
                $perbaikanTanpaPerulangan++;
            } else {
                $perbaikanDenganPerulangan++;
            }
        }

        $tetapMultiHari = count(array_filter(
            $tetap,
            static fn (array $r): bool => (int) ($r['day_streak'] ?? 1) >= 2
        ));

        return [
            'cards' => [
                'tetap' => count($tetap),
                'hilang' => count($hilang),
                'baru' => count($baru),
                'kembali' => count($kembali),
                'tidak_perbaikan_masih_berulang' => count($tetap),
                'tidak_perbaikan_masih_berulang_multi_hari' => $tetapMultiHari,
                'perbaikan_tanpa_perulangan' => $perbaikanTanpaPerulangan,
                'perbaikan_dengan_perulangan' => $perbaikanDenganPerulangan,
            ],
            'hilang_identities' => $hilangIdentities,
            'hilang_streak_by_identity' => $hilangStreakByIdentity,
            'all_details' => [
                'tetap' => $tetap,
                'hilang' => $hilang,
                'baru' => $baru,
                'kembali' => $kembali,
            ],
            'details' => [
                'tetap' => array_slice($tetap, 0, self::DETAIL_LIMIT),
                'hilang' => array_slice($hilang, 0, self::DETAIL_LIMIT),
                'baru' => array_slice($baru, 0, self::DETAIL_LIMIT),
                'kembali' => array_slice($kembali, 0, self::DETAIL_LIMIT),
            ],
            'truncated' => [
                'tetap' => max(0, count($tetap) - self::DETAIL_LIMIT),
                'hilang' => max(0, count($hilang) - self::DETAIL_LIMIT),
                'baru' => max(0, count($baru) - self::DETAIL_LIMIT),
                'kembali' => max(0, count($kembali) - self::DETAIL_LIMIT),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function detailRow(array $row, string $status, int $dayStreak): array
    {
        return [
            'status' => $status,
            'program_key' => (string) ($row['program_key'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'business_key' => (string) ($row['business_key'] ?? ''),
            'value_label' => (string) ($row['value_label'] ?? ''),
            'site' => (string) ($row['site'] ?? ''),
            'perusahaan' => (string) ($row['perusahaan'] ?? ''),
            'dataset_key' => (string) ($row['dataset_key'] ?? ''),
            'day_streak' => $dayStreak,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowIdentity(array $row): string
    {
        return strtolower((string) ($row['program_key'] ?? '')).'|'
            .((string) ($row['business_key'] ?? '')).'|'
            .strtolower((string) ($row['site'] ?? '')).'|'
            .strtolower((string) ($row['perusahaan'] ?? ''));
    }

    /**
     * @param  array{
     *     tetap: list<array<string, mixed>>,
     *     hilang: list<array<string, mixed>>,
     *     baru: list<array<string, mixed>>,
     *     kembali: list<array<string, mixed>>
     * }  $allDetails
     * @param  array<string, mixed>  $tasklist
     * @param  array<string, int>  $hilangStreakByIdentity
     * @return list<array<string, mixed>>
     */
    private function buildProgramMetrics(array $allDetails, array $tasklist, array $hilangStreakByIdentity): array
    {
        $companyTemplate = $this->dashboardService->buildGapEvaluasiMatrixTemplate();
        $siteTemplate = $this->dashboardService->buildGapEvaluasiSiteOnlyMatrixTemplate();

        /** @var list<array{key: string, site: string, company_code: string, company_name: string}> $companyScopeOrder */
        $companyScopeOrder = [];
        foreach ($companyTemplate['groups'] as $group) {
            foreach ($group['companies'] as $company) {
                $companyScopeOrder[] = [
                    'key' => (string) $company['key'],
                    'site' => (string) $group['site'],
                    'company_code' => (string) $company['code'],
                    'company_name' => (string) $company['name'],
                ];
            }
        }

        /** @var list<array{key: string, site: string, company_code: string, company_name: string}> $siteScopeOrder */
        $siteScopeOrder = [];
        foreach ($siteTemplate['groups'] as $group) {
            foreach ($group['companies'] as $company) {
                $siteScopeOrder[] = [
                    'key' => (string) $company['key'],
                    'site' => (string) $group['site'],
                    'company_code' => (string) $company['code'],
                    'company_name' => (string) $company['name'],
                ];
            }
        }

        /** @var array<string, array{total_gap: int, total_perulangan: int, perbaikan_tanpa_perulangan: int, perbaikan_total: int, tindaklanjut_berhasil: int, tindaklanjut_tanpa_perulangan: int, scopes: array<string, mixed>}> $stats */
        $stats = [];
        foreach (array_keys(self::PROGRAM_LABELS) as $key) {
            $stats[$key] = [
                'total_gap' => 0,
                'total_perulangan' => 0,
                'perbaikan_tanpa_perulangan' => 0,
                'perbaikan_total' => 0,
                'tindaklanjut_berhasil' => 0,
                'tindaklanjut_tanpa_perulangan' => 0,
                'scopes' => [],
            ];
        }

        // Total Gap = semua yang terkait gap di evaluasi (masih open ATAU sudah perbaikan).
        // Sehingga: Perbaikan tanpa Perulangan ⊆ Total Gap (tidak bisa lebih besar).
        foreach (['tetap', 'baru', 'kembali'] as $bucket) {
            foreach ($allDetails[$bucket] ?? [] as $row) {
                $key = (string) ($row['program_key'] ?? '');
                if (! isset($stats[$key])) {
                    continue;
                }
                $stats[$key]['total_gap']++;
                $isPerulangan = $this->isPerulangan($row);
                if ($isPerulangan) {
                    $stats[$key]['total_perulangan']++;
                }

                $scopeKey = $this->resolveScopeKeyOrOther($row);
                $this->ensureScopeBucket($stats[$key]['scopes'], $scopeKey);
                $stats[$key]['scopes'][$scopeKey]['total_gap']++;
                $this->pushScopeDetail($stats[$key]['scopes'][$scopeKey]['detail_gap'], $row);
                if ($isPerulangan) {
                    $stats[$key]['scopes'][$scopeKey]['total_perulangan']++;
                    $this->pushScopeDetail($stats[$key]['scopes'][$scopeKey]['detail_perulangan'], $row);
                }
            }
        }

        foreach ($allDetails['hilang'] ?? [] as $row) {
            $key = (string) ($row['program_key'] ?? '');
            if (! isset($stats[$key])) {
                continue;
            }
            // Hilang = pernah gap lalu clear → tetap bagian dari Total Gap.
            $stats[$key]['total_gap']++;
            $stats[$key]['perbaikan_total']++;
            $tanpaPerulangan = (int) ($row['day_streak'] ?? 1) <= 1;
            if ($tanpaPerulangan) {
                $stats[$key]['perbaikan_tanpa_perulangan']++;
            }

            $scopeKey = $this->resolveScopeKeyOrOther($row);
            $this->ensureScopeBucket($stats[$key]['scopes'], $scopeKey);
            $stats[$key]['scopes'][$scopeKey]['total_gap']++;
            $this->pushScopeDetail($stats[$key]['scopes'][$scopeKey]['detail_gap'], $row);
            if ($tanpaPerulangan) {
                $stats[$key]['scopes'][$scopeKey]['perbaikan_tanpa_perulangan']++;
                $this->pushScopeDetail($stats[$key]['scopes'][$scopeKey]['detail_perbaikan'], $row);
            }
        }

        foreach ($tasklist['berhasil_identities'] ?? [] as $identity) {
            $programKey = explode('|', $identity, 2)[0] ?? '';
            if (! isset($stats[$programKey])) {
                continue;
            }
            $stats[$programKey]['tindaklanjut_berhasil']++;
            $streak = (int) ($hilangStreakByIdentity[$identity] ?? 1);
            if ($streak < 2) {
                $stats[$programKey]['tindaklanjut_tanpa_perulangan']++;
            }
        }

        $programs = [];
        foreach (self::PROGRAM_LABELS as $key => $label) {
            $s = $stats[$key];
            $isSiteOnly = in_array($key, self::SITE_ONLY_PROGRAMS, true);
            $matrixRows = $this->buildVerticalScopeRows(
                $isSiteOnly ? $siteScopeOrder : $companyScopeOrder,
                $s['scopes'],
            );
            $programs[] = [
                'key' => $key,
                'label' => $label,
                'scope_mode' => $isSiteOnly ? 'site' : 'site_company',
                'total_gap' => $s['total_gap'],
                'total_perulangan' => $s['total_perulangan'],
                'perbaikan_tanpa_perulangan' => $s['perbaikan_tanpa_perulangan'],
                'perbaikan_total' => $s['perbaikan_total'],
                'tindaklanjut_berhasil' => $s['tindaklanjut_berhasil'],
                'tindaklanjut_tanpa_perulangan' => $s['tindaklanjut_tanpa_perulangan'],
                'matrix_rows' => $matrixRows,
            ];
        }

        return $programs;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveScopeKeyOrOther(array $row): string
    {
        $programKey = (string) ($row['program_key'] ?? '');
        if (in_array($programKey, self::SITE_ONLY_PROGRAMS, true)) {
            $scopeKey = $this->dashboardService->resolveGapEvaluasiSiteOnlyScopeKey(
                (string) ($row['site'] ?? ''),
            );

            return $scopeKey ?? 'Lainnya|—';
        }

        $scopeKey = $this->dashboardService->resolveGapEvaluasiScopeKey(
            (string) ($row['site'] ?? ''),
            (string) ($row['perusahaan'] ?? ''),
        );

        return $scopeKey ?? 'Lainnya|—';
    }

    /**
     * @param  array<string, array{
     *     total_gap: int,
     *     total_perulangan: int,
     *     perbaikan_tanpa_perulangan: int,
     *     detail_gap: list<array<string, mixed>>,
     *     detail_perulangan: list<array<string, mixed>>,
     *     detail_perbaikan: list<array<string, mixed>>
     * }>  $scopes
     */
    private function ensureScopeBucket(array &$scopes, string $scopeKey): void
    {
        if (! isset($scopes[$scopeKey])) {
            $scopes[$scopeKey] = [
                'total_gap' => 0,
                'total_perulangan' => 0,
                'perbaikan_tanpa_perulangan' => 0,
                'detail_gap' => [],
                'detail_perulangan' => [],
                'detail_perbaikan' => [],
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $bucket
     * @param  array<string, mixed>  $row
     */
    private function pushScopeDetail(array &$bucket, array $row): void
    {
        if (count($bucket) >= self::SCOPE_DETAIL_LIMIT) {
            return;
        }

        $bucket[] = [
            'status' => (string) ($row['status'] ?? ''),
            'value_label' => (string) ($row['value_label'] ?? ''),
            'business_key' => (string) ($row['business_key'] ?? ''),
            'site' => (string) ($row['site'] ?? ''),
            'perusahaan' => (string) ($row['perusahaan'] ?? ''),
            'day_streak' => (int) ($row['day_streak'] ?? 0),
        ];
    }

    /**
     * Tabel vertikal: Site/Perusahaan ke bawah, metrik ke samping.
     *
     * @param  list<array{key: string, site: string, company_code: string, company_name: string}>  $scopeOrder
     * @param  array<string, array{
     *     total_gap: int,
     *     total_perulangan: int,
     *     perbaikan_tanpa_perulangan: int,
     *     detail_gap?: list<array<string, mixed>>,
     *     detail_perulangan?: list<array<string, mixed>>,
     *     detail_perbaikan?: list<array<string, mixed>>
     * }>  $scopes
     * @return list<array<string, mixed>>
     */
    private function buildVerticalScopeRows(array $scopeOrder, array $scopes): array
    {
        $rows = [];
        $seen = [];

        foreach ($scopeOrder as $scope) {
            $key = $scope['key'];
            $seen[$key] = true;
            $bucket = $scopes[$key] ?? [
                'total_gap' => 0,
                'total_perulangan' => 0,
                'perbaikan_tanpa_perulangan' => 0,
                'detail_gap' => [],
                'detail_perulangan' => [],
                'detail_perbaikan' => [],
            ];
            $rows[] = $this->formatMatrixScopeRow(
                $key,
                $scope['site'],
                $scope['company_code'],
                $scope['company_name'],
                $bucket,
            );
        }

        foreach ($scopes as $key => $bucket) {
            if (isset($seen[$key])) {
                continue;
            }
            $parts = explode('|', $key, 2);
            $rows[] = $this->formatMatrixScopeRow(
                $key,
                $parts[0] !== '' ? $parts[0] : 'Lainnya',
                $parts[1] ?? '—',
                $parts[1] ?? '—',
                $bucket,
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @return array<string, mixed>
     */
    private function formatMatrixScopeRow(
        string $key,
        string $site,
        string $companyCode,
        string $companyName,
        array $bucket,
    ): array {
        return [
            'key' => $key,
            'site' => $site,
            'company_code' => $companyCode,
            'company_name' => $companyName,
            'total_gap' => (int) ($bucket['total_gap'] ?? 0),
            'total_perulangan' => (int) ($bucket['total_perulangan'] ?? 0),
            'perbaikan_tanpa_perulangan' => (int) ($bucket['perbaikan_tanpa_perulangan'] ?? 0),
            'detail_gap' => array_values($bucket['detail_gap'] ?? []),
            'detail_perulangan' => array_values($bucket['detail_perulangan'] ?? []),
            'detail_perbaikan' => array_values($bucket['detail_perbaikan'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $scrape
     * @param  array<string, mixed>  $tasklist
     * @param  list<array<string, mixed>>  $programs
     * @return array<string, int>
     */
    private function buildOverviewCards(array $scrape, array $tasklist, array $programs): array
    {
        $totalGap = 0;
        $totalPerulangan = 0;
        $perbaikanTanpaPerulangan = 0;
        foreach ($programs as $program) {
            $totalGap += (int) ($program['total_gap'] ?? 0);
            $totalPerulangan += (int) ($program['total_perulangan'] ?? 0);
            $perbaikanTanpaPerulangan += (int) ($program['perbaikan_tanpa_perulangan'] ?? 0);
        }

        $cards = $scrape['cards'] ?? [];
        $tlCards = $tasklist['cards'] ?? [];

        return [
            'total_gap' => $totalGap,
            'total_perulangan' => $totalPerulangan,
            'perbaikan_total' => (int) ($cards['hilang'] ?? 0),
            'perbaikan_tanpa_perulangan' => $perbaikanTanpaPerulangan,
            'tindaklanjut_berhasil' => (int) ($tlCards['tindaklanjut_berhasil'] ?? 0),
            'tindaklanjut_tanpa_perulangan' => (int) ($tlCards['tindaklanjut_tanpa_perulangan'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    /**
     * Perulangan = streak hari ≥ 2 (log berulang).
     * Status "tetap" saja TIDAK otomatis perulangan — tetap dengan streak 1
     * tetap masuk Total Gap, tapi tidak Total Perulangan.
     *
     * @param  array<string, mixed>  $row
     */
    private function isPerulangan(array $row): bool
    {
        return (int) ($row['day_streak'] ?? 1) >= 2;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, array<string, mixed>>  $mapD
     * @return array<string, mixed>
     */
    private function buildTasklistEffectiveness(array $filters, array $mapD, string $evalDate): array
    {
        if (! Schema::hasTable('hsecm_tasklist_items') || ! Schema::hasTable('hsecm_tasklists')) {
            return [
                'available' => false,
                'cards' => [
                    'tindaklanjut_berhasil' => 0,
                    'tindaklanjut_belum_efektif' => 0,
                    'belum_tindaklanjut_masih_gap' => 0,
                    'hilang_tanpa_tindaklanjut' => 0,
                    'tindaklanjut_tanpa_perulangan' => 0,
                ],
                'details' => [
                    'tindaklanjut_berhasil' => [],
                    'tindaklanjut_belum_efektif' => [],
                    'belum_tindaklanjut_masih_gap' => [],
                ],
                'acted_identities' => [],
                'berhasil_identities' => [],
                'message' => 'Tabel tasklist belum tersedia.',
            ];
        }

        $site = trim((string) ($filters['site'] ?? ''));
        $perusahaan = trim((string) ($filters['perusahaan'] ?? ''));

        // Join lebih ringan daripada whereHas subquery; batasi window 45 hari.
        $windowStart = Carbon::parse($evalDate, 'Asia/Makassar')->subDays(45)->startOfDay();

        $query = HsecmTasklistItem::query()
            ->select([
                'hsecm_tasklist_items.id',
                'hsecm_tasklist_items.tasklist_id',
                'hsecm_tasklist_items.program_key',
                'hsecm_tasklist_items.title',
                'hsecm_tasklist_items.business_key',
                'hsecm_tasklist_items.value_label',
                'hsecm_tasklist_items.status',
                'hsecm_tasklist_items.submitted_by_name',
                'hsecm_tasklist_items.submitted_at',
            ])
            ->join('hsecm_tasklists', 'hsecm_tasklists.id', '=', 'hsecm_tasklist_items.tasklist_id')
            ->addSelect([
                'hsecm_tasklists.site as tasklist_site',
                'hsecm_tasklists.perusahaan as tasklist_perusahaan',
            ])
            ->when($site !== '', static fn ($q) => $q->where('hsecm_tasklists.site', $site))
            ->when($perusahaan !== '', static fn ($q) => $q->where('hsecm_tasklists.perusahaan', $perusahaan))
            ->where(function ($inner) use ($evalDate, $windowStart): void {
                $inner->whereNull('hsecm_tasklists.batch_slot')
                    ->orWhere(function ($slotQ) use ($evalDate, $windowStart): void {
                        $slotQ->whereDate('hsecm_tasklists.batch_slot', '<=', $evalDate)
                            ->whereDate('hsecm_tasklists.batch_slot', '>=', $windowStart->toDateString());
                    });
            });

        $items = $query->orderByDesc('hsecm_tasklist_items.id')->limit(500)->get();

        $berhasil = [];
        $belumEfektif = [];
        $belumTindaklanjut = [];
        /** @var array<string, true> $actedIdentities */
        $actedIdentities = [];
        /** @var list<string> $berhasilIdentities */
        $berhasilIdentities = [];

        foreach ($items as $item) {
            $itemSite = trim((string) ($item->tasklist_site ?? ''));
            $itemCompany = trim((string) ($item->tasklist_perusahaan ?? ''));
            $programKey = trim((string) $item->program_key);
            $businessKey = trim((string) $item->business_key);
            $identity = strtolower($programKey).'|'.$businessKey.'|'.strtolower($itemSite).'|'.strtolower($itemCompany);

            $status = (string) $item->status;
            $acted = in_array($status, ['submitted', 'approved'], true);
            $stillGap = isset($mapD[$identity]);

            $detail = [
                'item_id' => (int) $item->id,
                'tasklist_id' => (int) $item->tasklist_id,
                'program_key' => $programKey,
                'title' => (string) ($item->title ?? ''),
                'business_key' => $businessKey,
                'value_label' => (string) ($item->value_label ?? ''),
                'site' => $itemSite,
                'perusahaan' => $itemCompany,
                'status' => $status,
                'submitted_by_name' => (string) ($item->submitted_by_name ?? ''),
                'submitted_at' => optional($item->submitted_at)?->timezone('Asia/Makassar')?->format('d/m/Y H:i'),
                'still_gap_on_eval_day' => $stillGap,
            ];

            if ($acted) {
                $actedIdentities[$identity] = true;
                if ($stillGap) {
                    $belumEfektif[] = $detail;
                } else {
                    $berhasil[] = $detail;
                    $berhasilIdentities[] = $identity;
                }
            } elseif ($stillGap && in_array($status, ['open', 'rejected'], true)) {
                $belumTindaklanjut[] = $detail;
            }
        }

        return [
            'available' => true,
            'cards' => [
                'tindaklanjut_berhasil' => count($berhasil),
                'tindaklanjut_belum_efektif' => count($belumEfektif),
                'belum_tindaklanjut_masih_gap' => count($belumTindaklanjut),
                'hilang_tanpa_tindaklanjut' => 0,
                'tindaklanjut_tanpa_perulangan' => 0,
            ],
            'acted_identities' => $actedIdentities,
            'berhasil_identities' => array_values(array_unique($berhasilIdentities)),
            'details' => [
                'tindaklanjut_berhasil' => array_slice($berhasil, 0, self::DETAIL_LIMIT),
                'tindaklanjut_belum_efektif' => array_slice($belumEfektif, 0, self::DETAIL_LIMIT),
                'belum_tindaklanjut_masih_gap' => array_slice($belumTindaklanjut, 0, self::DETAIL_LIMIT),
            ],
            'message' => null,
        ];
    }
}
