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
        unset($tasklist['acted_identities'], $scrape['hilang_identities']);

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
                'details' => [
                    'tindaklanjut_berhasil' => [],
                    'tindaklanjut_belum_efektif' => [],
                    'belum_tindaklanjut_masih_gap' => [],
                ],
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
                'hilang_tanpa_tindaklanjut' => 0, // diisi di execute setelah scrape
            ],
            'acted_identities' => $actedIdentities,
            'details' => [
                'tindaklanjut_berhasil' => array_slice($berhasil, 0, self::DETAIL_LIMIT),
                'tindaklanjut_belum_efektif' => array_slice($belumEfektif, 0, self::DETAIL_LIMIT),
                'belum_tindaklanjut_masih_gap' => array_slice($belumTindaklanjut, 0, self::DETAIL_LIMIT),
            ],
            'message' => null,
        ];
    }
}
