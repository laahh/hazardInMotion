<?php

declare(strict_types=1);

namespace App\Actions\Hsecm;

use App\Models\Hsecm\HsecmTasklistItem;
use App\Services\Hsecm\HsecmDashboardService;
use App\Services\Hsecm\HsecmDatabaseRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluasi gap harian (scrape D vs D-1) + efektivitas pasca tindak lanjut tasklist.
 */
final class HsecmBuildGapEvaluasiDashboardAction
{
    private const DETAIL_LIMIT = 200;

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

        $evalDate = trim((string) ($filters['date_from'] ?? ''));
        if ($evalDate === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $evalDate) !== 1) {
            $evalDate = $dates[0] ?? Carbon::now('Asia/Makassar')->format('Y-m-d');
        }

        $prevDate = $this->resolvePreviousDate($dates, $evalDate);

        $slotsD = $this->repository->listBatchSlotsOnDate($probeTable, $evalDate);
        $slotsPrev = $prevDate !== null
            ? $this->repository->listBatchSlotsOnDate($probeTable, $prevDate)
            : [];

        $mapD = $this->collectDayIdentityMap($filters, $slotsD);
        $mapPrev = $this->collectDayIdentityMap($filters, $slotsPrev);

        $scrape = $this->classifyScrape($mapD, $mapPrev, $prevDate, $slotsPrev);

        $tasklist = $this->buildTasklistEffectiveness(
            $filters,
            $mapD,
            $evalDate,
        );

        $hilangTanpaTindaklanjut = 0;
        foreach ($scrape['hilang_identities'] ?? [] as $identity) {
            if (! isset($tasklist['acted_identities'][$identity])) {
                $hilangTanpaTindaklanjut++;
            }
        }
        $tasklist['cards']['hilang_tanpa_tindaklanjut'] = $hilangTanpaTindaklanjut;

        $summaryScrape = $this->buildScrapeMatrix(
            $scrape['items']['tetap'] ?? [],
            $scrape['items']['hilang'] ?? [],
            array_merge($scrape['items']['baru'] ?? [], $scrape['items']['kembali'] ?? []),
        );

        $summaryTasklist = $this->buildTasklistMatrix(
            $tasklist['items']['tindaklanjut_berhasil'] ?? [],
            $tasklist['items']['tindaklanjut_belum_efektif'] ?? [],
        );

        $sections = $this->buildProgramSections(
            $scrape['items']['tetap'] ?? [],
            $scrape['items']['hilang'] ?? [],
        );

        unset($tasklist['acted_identities'], $scrape['hilang_identities'], $scrape['items'], $tasklist['items']);

        return [
            'filters' => array_merge($filters, [
                'date_from' => $evalDate,
                'date_to' => $evalDate,
            ]),
            'filter_options' => $this->dashboardService->getFilterOptions(),
            'period_label' => $prevDate !== null
                ? 'Evaluasi '.$evalDate.' vs '.$prevDate.' (hari kalender scrape)'
                : 'Evaluasi '.$evalDate.' (belum ada hari pembanding)',
            'eval_date' => $evalDate,
            'prev_date' => $prevDate,
            'slots_d' => $slotsD,
            'slots_prev' => $slotsPrev,
            'scrape' => $scrape,
            'tasklist' => $tasklist,
            'summary_scrape' => $summaryScrape,
            'summary_tasklist' => $summaryTasklist,
            'sections' => $sections,
        ];
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
        /** @var array<string, array<string, mixed>> $map */
        $map = [];

        foreach ($slots as $slot) {
            $slotFilters = $this->dashboardService->withBatchContext(
                [
                    'site' => (string) ($filters['site'] ?? ''),
                    'perusahaan' => (string) ($filters['perusahaan'] ?? ''),
                    'week' => '',
                    'year' => '',
                    'date_from' => '',
                    'date_to' => '',
                    'q' => (string) ($filters['q'] ?? ''),
                ],
                $slot,
                'snapshot',
            );

            foreach ($this->dashboardService->extractGapIdentityRows($slotFilters) as $row) {
                $identity = (string) $row['identity'];
                if ($identity === '' || isset($map[$identity])) {
                    continue;
                }
                $map[$identity] = $row;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, array<string, mixed>>  $mapD
     * @param  array<string, array<string, mixed>>  $mapPrev
     * @param  list<string>  $slotsPrev
     * @return array<string, mixed>
     */
    private function classifyScrape(array $mapD, array $mapPrev, ?string $prevDate, array $slotsPrev): array
    {
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
            $slotStreak = (int) ($streakByTable[$table][$bk] ?? (int) ($row['payload']['gap_count'] ?? 1));
            $dayStreak = max(1, (int) ceil($slotStreak / 2));

            if (isset($mapD[$identity])) {
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
            $isReopen = $bk !== '' && isset($presentBefore[$table][$bk]);
            if ($isReopen) {
                $kembali[] = $this->detailRow($row, 'kembali', 1);
            } else {
                $baru[] = $this->detailRow($row, 'baru', 1);
            }
        }

        $perbaikanTanpaPerulangan = 0;
        $perbaikanDenganPerulangan = 0;
        /** @var list<string> $hilangIdentities */
        $hilangIdentities = [];
        foreach ($hilang as $row) {
            $hilangIdentities[] = strtolower((string) $row['program_key']).'|'
                .((string) $row['business_key']).'|'
                .strtolower((string) $row['site']).'|'
                .strtolower((string) $row['perusahaan']);
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
            'items' => [
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
                ],
                'items' => [
                    'tindaklanjut_berhasil' => [],
                    'tindaklanjut_belum_efektif' => [],
                    'belum_tindaklanjut_masih_gap' => [],
                ],
                'details' => [
                    'tindaklanjut_berhasil' => [],
                    'tindaklanjut_belum_efektif' => [],
                    'belum_tindaklanjut_masih_gap' => [],
                ],
                'acted_identities' => [],
                'message' => 'Tabel tasklist belum tersedia.',
            ];
        }

        $site = trim((string) ($filters['site'] ?? ''));
        $perusahaan = trim((string) ($filters['perusahaan'] ?? ''));

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
            ->with(['tasklist:id,site,perusahaan,batch_slot,token'])
            ->whereHas('tasklist', function ($q) use ($site, $perusahaan, $evalDate): void {
                if ($site !== '') {
                    $q->where('site', $site);
                }
                if ($perusahaan !== '') {
                    $q->where('perusahaan', $perusahaan);
                }
                // Tasklist yang relevan: batch pada/sebelum hari evaluasi
                $q->where(function ($inner) use ($evalDate): void {
                    $inner->whereNull('batch_slot')
                        ->orWhereDate('batch_slot', '<=', $evalDate);
                });
            });

        $items = $query->orderByDesc('hsecm_tasklist_items.id')->limit(2000)->get();

        $berhasil = [];
        $belumEfektif = [];
        $belumTindaklanjut = [];
        /** @var array<string, true> $actedIdentities */
        $actedIdentities = [];

        foreach ($items as $item) {
            $tasklist = $item->tasklist;
            if ($tasklist === null) {
                continue;
            }

            $itemSite = trim((string) ($tasklist->site ?? ''));
            $itemCompany = trim((string) ($tasklist->perusahaan ?? ''));
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
                }
            } elseif ($stillGap && in_array($status, ['open', 'rejected'], true)) {
                $belumTindaklanjut[] = $detail;
            }
        }

        // Hilang di scrape D tanpa ada tindak lanjut tasklist (dari mapPrev keys yang hilang)
        // Dihitung di controller layer via scrape cards; di sini hitung identity di mapD absen
        // yang punya riwayat di mapPrev — sudah di scrape.hilang. Untuk B: hilang tanpa acted.
        // Kita hitung dari keys yang tidak di mapD tapi pernah di-tasklist? Plan:
        // hilang_tanpa_tindaklanjut = scrape hilang identities without acted submit.
        // Action needs mapPrev for that — pass via classify or recompute here from scrape details.
        // Simpler: count later in execute by intersecting — for now compute from mapD absence:
        // Actually build from acted vs scrape: identities in mapD that weren't acted aren't "hilang".
        // hilang_tanpa_tindaklanjut = keys missing from mapD that appear in prev scrape and not in actedIdentities.
        // We don't have mapPrev here easily for count without passing — pass scrape hilang identities.

        return [
            'available' => true,
            'cards' => [
                'tindaklanjut_berhasil' => count($berhasil),
                'tindaklanjut_belum_efektif' => count($belumEfektif),
                'belum_tindaklanjut_masih_gap' => count($belumTindaklanjut),
                'hilang_tanpa_tindaklanjut' => 0,
            ],
            'acted_identities' => $actedIdentities,
            'items' => [
                'tindaklanjut_berhasil' => $berhasil,
                'tindaklanjut_belum_efektif' => $belumEfektif,
                'belum_tindaklanjut_masih_gap' => $belumTindaklanjut,
            ],
            'details' => [
                'tindaklanjut_berhasil' => array_slice($berhasil, 0, self::DETAIL_LIMIT),
                'tindaklanjut_belum_efektif' => array_slice($belumEfektif, 0, self::DETAIL_LIMIT),
                'belum_tindaklanjut_masih_gap' => array_slice($belumTindaklanjut, 0, self::DETAIL_LIMIT),
            ],
            'message' => null,
        ];
    }

    private const SECTION_DETAIL_LIMIT = 50;

    /**
     * @param  list<array<string, mixed>>  $tetap
     * @param  list<array<string, mixed>>  $hilang
     * @param  list<array<string, mixed>>  $baru
     * @return array{
     *     groups: list<array<string, mixed>>,
     *     columns: list<string>,
     *     rows: list<array<string, mixed>>,
     *     mode: string
     * }
     */
    private function buildScrapeMatrix(array $tetap, array $hilang, array $baru): array
    {
        /** @var array<string, array<string, true>> $seenPairs */
        $seenPairs = [];
        /** @var array<string, array<string, array{sudah: int, belum: int, baru: int}>> $cells */
        $cells = [];

        foreach ($tetap as $row) {
            $this->bumpScrapeCell($cells, $seenPairs, $row, 'belum');
        }
        foreach ($hilang as $row) {
            $this->bumpScrapeCell($cells, $seenPairs, $row, 'sudah');
        }
        foreach ($baru as $row) {
            $this->bumpScrapeCell($cells, $seenPairs, $row, 'baru');
        }

        $header = $this->dashboardService->buildEvaluasiMatrixHeader($seenPairs);
        $programs = $this->dashboardService->gapEvaluasiMatrixPrograms();
        $rows = [];
        foreach ($programs as $program) {
            $key = $program['key'];
            $rowCells = [];
            foreach ($header['columns'] as $col) {
                $rowCells[$col] = $cells[$key][$col] ?? ['sudah' => 0, 'belum' => 0, 'baru' => 0];
            }
            $rows[] = [
                'key' => $key,
                'label' => $program['label'],
                'cells' => $rowCells,
            ];
        }

        return [
            'groups' => $header['groups'],
            'columns' => $header['columns'],
            'rows' => $rows,
            'mode' => 'scrape',
        ];
    }

    /**
     * @param  array<string, array<string, array{sudah: int, belum: int, baru: int}>>  $cells
     * @param  array<string, array<string, true>>  $seenPairs
     * @param  array<string, mixed>  $row
     */
    private function bumpScrapeCell(array &$cells, array &$seenPairs, array $row, string $metric): void
    {
        $programKey = $this->toMatrixProgramKey($row);
        $pair = $this->dashboardService->resolveEvaluasiMatrixPair(
            (string) ($row['site'] ?? ''),
            (string) ($row['perusahaan'] ?? ''),
        );
        if ($pair === null) {
            return;
        }
        [$site, $code] = $pair;
        $col = $this->dashboardService->evaluasiMatrixColumnKey($site, $code);
        $seenPairs[$site][$code] = true;
        if (! isset($cells[$programKey][$col])) {
            $cells[$programKey][$col] = ['sudah' => 0, 'belum' => 0, 'baru' => 0];
        }
        $cells[$programKey][$col][$metric]++;
    }

    /**
     * @param  list<array<string, mixed>>  $berhasil
     * @param  list<array<string, mixed>>  $belumEfektif
     * @return array{
     *     groups: list<array<string, mixed>>,
     *     columns: list<string>,
     *     rows: list<array<string, mixed>>,
     *     mode: string
     * }
     */
    private function buildTasklistMatrix(array $berhasil, array $belumEfektif): array
    {
        /** @var array<string, array<string, true>> $seenPairs */
        $seenPairs = [];
        /** @var array<string, array<string, array{efektif: int, belum_efektif: int}>> $cells */
        $cells = [];

        foreach ($berhasil as $row) {
            $this->bumpTasklistCell($cells, $seenPairs, $row, 'efektif');
        }
        foreach ($belumEfektif as $row) {
            $this->bumpTasklistCell($cells, $seenPairs, $row, 'belum_efektif');
        }

        $header = $this->dashboardService->buildEvaluasiMatrixHeader($seenPairs);
        $programs = $this->dashboardService->gapEvaluasiMatrixPrograms();
        $rows = [];
        foreach ($programs as $program) {
            $key = $program['key'];
            $rowCells = [];
            foreach ($header['columns'] as $col) {
                $rowCells[$col] = $cells[$key][$col] ?? ['efektif' => 0, 'belum_efektif' => 0];
            }
            $rows[] = [
                'key' => $key,
                'label' => $program['label'],
                'cells' => $rowCells,
            ];
        }

        return [
            'groups' => $header['groups'],
            'columns' => $header['columns'],
            'rows' => $rows,
            'mode' => 'tasklist',
        ];
    }

    /**
     * @param  array<string, array<string, array{efektif: int, belum_efektif: int}>>  $cells
     * @param  array<string, array<string, true>>  $seenPairs
     * @param  array<string, mixed>  $row
     */
    private function bumpTasklistCell(array &$cells, array &$seenPairs, array $row, string $metric): void
    {
        $programKey = $this->toMatrixProgramKey($row);
        $pair = $this->dashboardService->resolveEvaluasiMatrixPair(
            (string) ($row['site'] ?? ''),
            (string) ($row['perusahaan'] ?? ''),
        );
        if ($pair === null) {
            return;
        }
        [$site, $code] = $pair;
        $col = $this->dashboardService->evaluasiMatrixColumnKey($site, $code);
        $seenPairs[$site][$code] = true;
        if (! isset($cells[$programKey][$col])) {
            $cells[$programKey][$col] = ['efektif' => 0, 'belum_efektif' => 0];
        }
        $cells[$programKey][$col][$metric]++;
    }

    /**
     * @param  list<array<string, mixed>>  $tetap
     * @param  list<array<string, mixed>>  $hilang
     * @return list<array<string, mixed>>
     */
    private function buildProgramSections(array $tetap, array $hilang): array
    {
        $programs = $this->dashboardService->gapEvaluasiMatrixPrograms();
        $sections = [];

        foreach ($programs as $program) {
            $key = $program['key'];
            $belumRows = array_values(array_filter(
                $tetap,
                fn (array $r): bool => $this->toMatrixProgramKey($r) === $key
            ));
            $sudahRows = array_values(array_filter(
                $hilang,
                fn (array $r): bool => $this->toMatrixProgramKey($r) === $key
            ));

            if ($belumRows === [] && $sudahRows === []) {
                continue;
            }

            /** @var array<string, array{label: string, belum: int, sudah: int}> $byCompany */
            $byCompany = [];
            foreach ($belumRows as $row) {
                $pair = $this->dashboardService->resolveEvaluasiMatrixPair(
                    (string) ($row['site'] ?? ''),
                    (string) ($row['perusahaan'] ?? ''),
                );
                $label = $pair !== null ? $pair[1] : (trim((string) ($row['perusahaan'] ?? '')) ?: 'Lainnya');
                if (! isset($byCompany[$label])) {
                    $byCompany[$label] = ['label' => $label, 'belum' => 0, 'sudah' => 0];
                }
                $byCompany[$label]['belum']++;
            }
            foreach ($sudahRows as $row) {
                $pair = $this->dashboardService->resolveEvaluasiMatrixPair(
                    (string) ($row['site'] ?? ''),
                    (string) ($row['perusahaan'] ?? ''),
                );
                $label = $pair !== null ? $pair[1] : (trim((string) ($row['perusahaan'] ?? '')) ?: 'Lainnya');
                if (! isset($byCompany[$label])) {
                    $byCompany[$label] = ['label' => $label, 'belum' => 0, 'sudah' => 0];
                }
                $byCompany[$label]['sudah']++;
            }

            uasort($byCompany, static fn (array $a, array $b): int => ($b['belum'] + $b['sudah']) <=> ($a['belum'] + $a['sudah']));
            $chartRows = array_slice(array_values($byCompany), 0, 8);

            $sections[] = [
                'key' => $key,
                'label' => $program['label'],
                'icon' => $program['icon'],
                'total_belum' => count($belumRows),
                'total_sudah' => count($sudahRows),
                'chart_title' => 'Belum vs Sudah per Mitra',
                'chart_labels' => array_column($chartRows, 'label'),
                'chart_belum' => array_column($chartRows, 'belum'),
                'chart_sudah' => array_column($chartRows, 'sudah'),
                'belum_rows' => array_slice($belumRows, 0, self::SECTION_DETAIL_LIMIT),
                'sudah_rows' => array_slice($sudahRows, 0, self::SECTION_DETAIL_LIMIT),
                'belum_truncated' => max(0, count($belumRows) - self::SECTION_DETAIL_LIMIT),
                'sudah_truncated' => max(0, count($sudahRows) - self::SECTION_DETAIL_LIMIT),
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toMatrixProgramKey(array $row): string
    {
        $dataset = trim((string) ($row['dataset_key'] ?? ''));
        if ($dataset !== '') {
            return $dataset;
        }

        return match (trim((string) ($row['program_key'] ?? ''))) {
            'layer1-tanpa-sap' => 'sap-rfid',
            'coverage-area' => 'coverage-cctv',
            'tbc-blindspot' => 'tbc-blindspot',
            'hazard-overdue' => 'task-overdue',
            'hazard-submitted' => 'task-submitted',
            'ikk-compliance' => 'ikk-work-permit',
            'aggregator-fill' => 'aggregator',
            'ftw-merah' => 'fatigue',
            'hazard-rootcause' => 'hazard-rootcause',
            default => trim((string) ($row['program_key'] ?? 'other')),
        };
    }
}
