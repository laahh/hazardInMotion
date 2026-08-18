<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Dashboard operator onsite (RFID) × status PVT BeWell. Read-only.
 */
final class SportEvaluationPvtDashboardService
{
    private const CACHE_TTL = 45;

    /**
     * Chart 7-hari itu data HISTORIS, tidak perlu sesegar cohort "hari ini" —
     * cache lebih lama supaya query berat (rentang multi-hari) tidak diulang
     * tiap 45 detik di bawah traffic nyata.
     */
    private const CHART_CACHE_TTL = 600;

    private const PVT_STATUSES = ['belum', 'lulus', 'tidak_lulus'];

    /** @var array<string, list<array{id:int,nama:string,kode_sid:string,site:string,company:string,jabatan:string}>> */
    private array $operatorsMemo = [];

    public function __construct(
        private readonly BewellConnectionService $bewell,
        private readonly SportEvaluationPvtRfidCheckinReader $rfidReader,
        private readonly SportEvaluationKaryawanWellSiteResolver $siteResolver,
        private readonly SportEvaluationCompanyAliasResolver $companyAliasResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Request $request): array
    {
        $filters = $this->readFilters($request);
        $empty = $this->emptyPayload($filters);

        $bewellUp = $this->bewell->isUp();
        $rfidUp = $this->rfidReader->isUp();
        $empty['bewellUp'] = $bewellUp;
        $empty['rfidUp'] = $rfidUp;

        if (! $bewellUp) {
            return $empty;
        }

        try {
            $cohort = $this->cohortRows($filters);
            try {
                $chart = $this->checkinChart($filters);
            } catch (Throwable $chartError) {
                report($chartError);
                $chart = $this->emptyCheckinChart();
            }

            return [
                'bewellUp' => true,
                'rfidUp' => $rfidUp,
                'filters' => $filters,
                'dateLabel' => $this->dateLabel($filters['date']),
                'kpi' => $this->buildKpi($cohort),
                'siteRows' => $this->aggregateBy($cohort, 'site'),
                'companyRows' => $this->aggregateBy($cohort, 'company'),
                'checkinChart' => $chart,
                'filterOptions' => $this->buildFilterOptions(),
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(Request $request): array
    {
        $draw = (int) $request->input('draw', 1);
        $empty = [
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ];

        if (! $this->bewell->isUp()) {
            return $empty;
        }

        try {
            $filters = $this->readFilters($request);
            $rows = $this->cohortRows($filters);
            $recordsTotal = count($rows);

            $search = trim((string) $request->input('search.value', ''));
            if ($search !== '') {
                $needle = mb_strtolower($search);
                $rows = array_values(array_filter(
                    $rows,
                    static function (array $row) use ($needle): bool {
                        $haystack = mb_strtolower(implode(' ', [
                            $row['nama'],
                            $row['kode_sid'],
                            $row['site'],
                            $row['company'],
                            $row['jabatan'],
                            $row['gate'],
                        ]));

                        return str_contains($haystack, $needle);
                    }
                ));
            }

            $recordsFiltered = count($rows);
            $orderColumnIndex = (int) data_get($request->input('order'), '0.column', 5);
            $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'desc')) === 'asc'
                ? 'asc'
                : 'desc';

            $orderable = [
                0 => 'nama',
                1 => 'kode_sid',
                2 => 'site',
                3 => 'company',
                4 => 'pvt_status',
                5 => 'pvt_status',
                6 => 'checked_in_at',
            ];
            $orderKey = $orderable[$orderColumnIndex] ?? 'nama';
            usort($rows, static function (array $a, array $b) use ($orderKey, $orderDir): int {
                $left = $a[$orderKey] ?? '';
                $right = $b[$orderKey] ?? '';
                if (is_numeric($left) && is_numeric($right)) {
                    $cmp = (int) $left <=> (int) $right;
                } else {
                    $cmp = strcmp((string) $left, (string) $right);
                }

                return $orderDir === 'asc' ? $cmp : -$cmp;
            });

            $start = max(0, (int) $request->input('start', 0));
            $length = (int) $request->input('length', 10);
            if ($length < 1) {
                $length = 10;
            }
            if ($length > 100) {
                $length = 100;
            }

            $page = array_slice($rows, $start, $length);
            $data = [];
            foreach ($page as $row) {
                $data[] = $this->presentRow($row);
            }

            return [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty + ['error' => 'Gagal memuat data PVT.'];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(Request $request): array
    {
        if (! $this->bewell->isUp()) {
            return [];
        }

        $filters = $this->readFilters($request);
        $rows = $this->cohortRows($filters);
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter(
                $rows,
                static function (array $row) use ($needle): bool {
                    $haystack = mb_strtolower(implode(' ', [
                        $row['nama'],
                        $row['kode_sid'],
                        $row['site'],
                        $row['company'],
                    ]));

                    return str_contains($haystack, $needle);
                }
            ));
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['checked_in_at'], (string) $b['checked_in_at']));

        $export = [];
        foreach ($rows as $row) {
            $presented = $this->presentRow($row);
            $export[] = [
                'nama' => $presented['nama'],
                'kode_sid' => $presented['kode_sid'],
                'site' => $presented['site'],
                'company' => $presented['company'],
                'jabatan' => $presented['jabatan'],
                'gate' => $presented['gate'],
                'checked_in_at' => $presented['checked_in_at'],
                'status_lolos' => $presented['status_lolos'],
                'pvt_status_label' => $presented['pvt_status_label'],
                'mean_rt_ms' => $presented['mean_rt_ms'] === '' ? '-' : $presented['mean_rt_ms'],
                'median_rt_ms' => $presented['median_rt_ms'] === '' ? '-' : $presented['median_rt_ms'],
                'lapses' => $presented['lapses'] === '' ? '-' : $presented['lapses'],
                'false_starts' => $presented['false_starts'] === '' ? '-' : $presented['false_starts'],
                'tested_at' => $presented['tested_at'],
                'evaluation_label' => $presented['evaluation_label'],
            ];
        }

        return $export;
    }

    /**
     * @return array{date:string,site:string,company:string,pvt_status:string}
     */
    public function readFilters(Request $request): array
    {
        $read = static fn (mixed $value): string => is_string($value)
            ? mb_substr(trim($value), 0, 180)
            : '';

        $date = $read($request->input('date', $request->query('date', '')));
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = Carbon::now(config('app.timezone'))->toDateString();
        }

        $status = strtolower($read($request->input('pvt_status', $request->query('pvt_status', ''))));
        if (! in_array($status, self::PVT_STATUSES, true)) {
            $status = '';
        }

        return [
            'date' => $date,
            'site' => $read($request->input('site', $request->query('site', ''))),
            'company' => $read($request->input('company', $request->query('company', $request->query('perusahaan', '')))),
            'pvt_status' => $status,
        ];
    }

    /**
     * @param  array{date:string,site:string,company:string,pvt_status:string}  $filters
     * @return list<array<string, mixed>>
     */
    private function cohortRows(array $filters): array
    {
        $cacheKey = 'evaluasi_well:pvt_cohort:v3:'.sha1((string) json_encode([
            $filters['date'],
            $filters['site'],
            $filters['company'],
            $filters['pvt_status'],
        ], JSON_THROW_ON_ERROR));

        /** @var list<array<string, mixed>> */
        return Cache::remember($cacheKey, self::CACHE_TTL, fn (): array => $this->buildCohort($filters));
    }

    /**
     * @param  array{date:string,site:string,company:string,pvt_status:string}  $filters
     * @return list<array<string, mixed>>
     */
    private function buildCohort(array $filters): array
    {
        $operators = $this->loadOperators($filters);
        if ($operators === []) {
            return [];
        }

        $sids = array_map(static fn (array $op): string => $op['kode_sid'], $operators);
        $checkins = $this->rfidReader->firstPassedCheckinsForSids($filters['date'], $sids);
        if ($checkins === []) {
            return [];
        }

        $onsite = [];
        foreach ($operators as $operator) {
            $upper = mb_strtoupper($operator['kode_sid']);
            if (! isset($checkins[$upper])) {
                continue;
            }
            $checkin = $checkins[$upper];
            $company = $operator['company'] !== ''
                ? $operator['company']
                : $this->companyAliasResolver->resolve($checkin['perusahaan']);
            if ($company === '') {
                $company = 'Tidak diketahui';
            }

            $onsite[] = [
                'id' => $operator['id'],
                'nama' => $operator['nama'] !== '' ? $operator['nama'] : $checkin['nama_karyawan'],
                'kode_sid' => $operator['kode_sid'],
                'site' => $operator['site'] !== '' ? $operator['site'] : 'Tidak diketahui',
                'company' => $company,
                'jabatan' => $operator['jabatan'],
                'gate' => $checkin['gate'] !== '' ? $checkin['gate'] : '-',
                'checked_in_at' => $checkin['checked_in_at'],
                'status_lolos' => $checkin['status_lolos'] !== '' ? $checkin['status_lolos'] : '-',
                'jenis_checkinout' => $checkin['jenis_checkinout'],
            ];
        }

        if ($onsite === []) {
            return [];
        }

        $pvtBySid = $this->loadPvtBySid(
            array_map(static fn (array $row): string => $row['kode_sid'], $onsite),
            $filters['date'],
        );

        $cohort = [];
        foreach ($onsite as $row) {
            $matched = $this->matchPvtForCheckin(
                $pvtBySid[mb_strtoupper($row['kode_sid'])] ?? [],
                $row['checked_in_at'],
            );
            $status = $this->resolvePvtStatus($matched);
            if ($filters['pvt_status'] !== '' && $status !== $filters['pvt_status']) {
                continue;
            }

            $cohort[] = array_merge($row, [
                'pvt_status' => $status,
                'mean_rt_ms' => $matched['mean_rt_ms'] ?? null,
                'median_rt_ms' => $matched['median_rt_ms'] ?? null,
                'lapses' => $matched['lapses'] ?? null,
                'false_starts' => $matched['false_starts'] ?? null,
                'tested_at' => $matched['tested_at'] ?? null,
                'evaluation_label' => $matched['evaluation_label'] ?? '',
                'passed' => $matched['passed'] ?? null,
            ]);
        }

        return $cohort;
    }

    /**
     * Daftar operator TIDAK bergantung pada 'date' atau 'pvt_status' (hanya
     * site/company yang memengaruhi query) — dashboard() memanggil ini dari
     * cohortRows() dan checkinChart() dengan hasil yang pasti identik, jadi
     * di-memoize per request supaya query BeWell ini tidak dobel tiap load.
     *
     * @param  array{date:string,site:string,company:string,pvt_status:string}  $filters
     * @return list<array{id:int,nama:string,kode_sid:string,site:string,company:string,jabatan:string}>
     */
    private function loadOperators(array $filters): array
    {
        $memoKey = $filters['site'].'|'.$filters['company'];
        if (isset($this->operatorsMemo[$memoKey])) {
            return $this->operatorsMemo[$memoKey];
        }

        $query = DB::connection(BewellConnectionService::CONNECTION)
            ->table('employee_profiles as e')
            ->select([
                'e.id',
                'e.nama',
                'e.kode_sid',
                'e.site',
                'e.nama_perusahaan',
                'e.jabatan_fungsional',
            ])
            ->whereNotNull('e.kode_sid')
            ->where('e.kode_sid', '<>', '')
            ->whereRaw("UPPER(TRIM(COALESCE(e.jabatan_fungsional, ''))) LIKE ?", ['%OPERATOR%'])
            ->whereRaw("UPPER(TRIM(COALESCE(e.jabatan_fungsional, ''))) <> ?", ['VISITOR']);

        if ($filters['site'] !== '') {
            $this->siteResolver->applySiteFilter($query, $filters['site']);
        }
        if ($filters['company'] !== '') {
            $names = $this->companyAliasResolver->matchingRawNames($filters['company']);
            if ($names === []) {
                $names = [$filters['company']];
            }
            $normalized = array_map(
                static fn (string $name): string => mb_strtoupper(trim($name)),
                $names
            );
            $placeholders = implode(',', array_fill(0, count($normalized), '?'));
            $query->whereRaw(
                'UPPER(TRIM(COALESCE(e.nama_perusahaan, \'\'))) IN ('.$placeholders.')',
                $normalized
            );
        }

        $rows = $query->get();
        $operators = [];
        foreach ($rows as $row) {
            $sid = trim((string) ($row->kode_sid ?? ''));
            if ($sid === '') {
                continue;
            }

            $site = $this->siteResolver->resolve(
                $sid,
                isset($row->site) ? (string) $row->site : null,
            );
            $company = $this->companyAliasResolver->resolve(
                isset($row->nama_perusahaan) ? (string) $row->nama_perusahaan : null
            );

            $upper = mb_strtoupper($sid);
            if (isset($operators[$upper])) {
                continue;
            }

            $operators[$upper] = [
                'id' => (int) $row->id,
                'nama' => trim((string) ($row->nama ?? '')),
                'kode_sid' => $sid,
                'site' => $site,
                'company' => $company,
                'jabatan' => trim((string) ($row->jabatan_fungsional ?? '')),
            ];
        }

        return $this->operatorsMemo[$memoKey] = array_values($operators);
    }

    /**
     * Hasil PVT hari itu, dikelompokkan per SID (bukan hanya employee_profiles.id)
     * supaya tes yang tersimpan di profil duplikat SID yang sama tetap ketemu.
     *
     * Window query diperlebar ±12 jam untuk mengantisipasi tested_at naive UTC,
     * lalu difilter ulang ke tanggal kalender Asia/Makassar.
     *
     * @param  list<string>  $sids
     * @return array<string, list<array<string, mixed>>>
     */
    private function loadPvtBySid(array $sids, string $date, ?string $toDate = null): array
    {
        $upperSids = [];
        foreach ($sids as $sid) {
            $trimmed = trim((string) $sid);
            if ($trimmed === '') {
                continue;
            }
            $upperSids[mb_strtoupper($trimmed)] = true;
        }
        $upperSids = array_keys($upperSids);
        if ($upperSids === []) {
            return [];
        }

        $tz = (string) config('app.timezone');
        $fromDay = Carbon::parse($date, $tz)->startOfDay();
        $toDay = Carbon::parse($toDate ?? $date, $tz)->startOfDay();
        $dateSet = [];
        for ($cursor = $fromDay->copy(); $cursor->lte($toDay); $cursor->addDay()) {
            $dateSet[$cursor->toDateString()] = true;
        }
        $start = $fromDay->copy()->subHours(12)->format('Y-m-d H:i:s');
        $end = $toDay->copy()->addDay()->addHours(12)->format('Y-m-d H:i:s');

        $rows = collect();
        try {
            foreach (array_chunk($upperSids, 800) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $chunkRows = DB::connection(BewellConnectionService::CONNECTION)
                    ->table('cognitive_pvt_results as p')
                    ->join('employee_profiles as e', 'e.id', '=', 'p.user_id')
                    ->select([
                        'e.kode_sid',
                        'p.user_id',
                        'p.tested_at',
                        'p.passed',
                        'p.evaluation_label',
                        'p.mean_rt_ms',
                        'p.median_rt_ms',
                        'p.lapses',
                        'p.false_starts',
                    ])
                    ->where('p.tested_at', '>=', $start)
                    ->where('p.tested_at', '<', $end)
                    ->whereRaw('UPPER(TRIM(e.kode_sid)) IN ('.$placeholders.')', $chunk)
                    ->orderBy('p.tested_at')
                    ->get();
                $rows = $rows->concat($chunkRows);
            }
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $sid = mb_strtoupper(trim((string) ($row->kode_sid ?? '')));
            if ($sid === '') {
                continue;
            }

            $testedLocal = $this->testedAtInDates($row->tested_at ?? null, $dateSet);
            if ($testedLocal === null) {
                continue;
            }

            $grouped[$sid][] = [
                'tested_at' => $testedLocal->format('Y-m-d H:i:s'),
                'passed' => $row->passed === null ? null : (int) $row->passed,
                'evaluation_label' => trim((string) ($row->evaluation_label ?? '')),
                'mean_rt_ms' => $row->mean_rt_ms === null ? null : (int) $row->mean_rt_ms,
                'median_rt_ms' => $row->median_rt_ms === null ? null : (int) $row->median_rt_ms,
                'lapses' => $row->lapses === null ? null : (int) $row->lapses,
                'false_starts' => $row->false_starts === null ? null : (int) $row->false_starts,
            ];
        }

        return $grouped;
    }

    /**
     * Ambil PVT terakhir di hari yang sama. Utamakan tes setelah check-in;
     * jika belum ada (tes biasanya di klinik sebelum gate), pakai tes terakhir hari itu.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, mixed>|null
     */
    private function matchPvtForCheckin(array $candidates, string $checkedInAt): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $checkin = $this->parseAppDateTime($checkedInAt);
        $afterCheckin = [];
        foreach ($candidates as $candidate) {
            $tested = $this->parseAppDateTime($candidate['tested_at'] ?? null);
            if ($tested === null) {
                continue;
            }
            if ($checkin === null || $tested->timestamp >= $checkin->timestamp) {
                $afterCheckin[] = $candidate;
            }
        }

        $pool = $afterCheckin !== [] ? $afterCheckin : $candidates;

        $matched = null;
        $matchedTs = null;
        foreach ($pool as $candidate) {
            $tested = $this->parseAppDateTime($candidate['tested_at'] ?? null);
            if ($tested === null) {
                continue;
            }
            if ($matched === null || $tested->timestamp >= $matchedTs) {
                $matched = $candidate;
                $matchedTs = $tested->timestamp;
            }
        }

        return $matched;
    }

    private function parseAppDateTime(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->timezone((string) config('app.timezone'));
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            $tz = (string) config('app.timezone');
            $hasOffset = preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $raw) === 1;
            $parsed = $hasOffset ? Carbon::parse($raw) : Carbon::parse($raw, $tz);

            return $parsed->timezone($tz);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * tested_at MySQL DATETIME bersifat naive: bisa lokal Makassar atau UTC.
     * Terima baris jika salah satu interpretasi jatuh di salah satu tanggal filter.
     *
     * @param  array<string, bool>  $dateSet
     */
    private function testedAtInDates(mixed $value, array $dateSet): ?Carbon
    {
        $asLocal = $this->parseAppDateTime($value);
        if ($asLocal !== null && isset($dateSet[$asLocal->toDateString()])) {
            return $asLocal;
        }

        $raw = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d H:i:s')
            : trim((string) $value);
        if ($raw === '' || preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $raw) === 1) {
            return null;
        }

        try {
            $asUtc = Carbon::parse($raw, 'UTC')->timezone((string) config('app.timezone'));

            return isset($dateSet[$asUtc->toDateString()]) ? $asUtc : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $pvt
     */
    private function resolvePvtStatus(?array $pvt): string
    {
        if ($pvt === null) {
            return 'belum';
        }

        $passed = $pvt['passed'] ?? null;
        if ($passed === null) {
            $label = mb_strtolower((string) ($pvt['evaluation_label'] ?? ''));

            return str_contains($label, 'memenuhi') ? 'lulus' : 'tidak_lulus';
        }

        return ((int) $passed === 1) ? 'lulus' : 'tidak_lulus';
    }

    /**
     * @param  list<array<string, mixed>>  $cohort
     * @return array{checkin:int,sudah_pvt:int,belum_pvt:int,lulus:int,tidak_lulus:int}
     */
    private function buildKpi(array $cohort): array
    {
        $kpi = [
            'checkin' => count($cohort),
            'sudah_pvt' => 0,
            'belum_pvt' => 0,
            'lulus' => 0,
            'tidak_lulus' => 0,
        ];

        foreach ($cohort as $row) {
            $status = (string) ($row['pvt_status'] ?? 'belum');
            if ($status === 'belum') {
                $kpi['belum_pvt']++;
            } else {
                $kpi['sudah_pvt']++;
                if ($status === 'lulus') {
                    $kpi['lulus']++;
                } else {
                    $kpi['tidak_lulus']++;
                }
            }
        }

        return $kpi;
    }

    /**
     * @param  list<array<string, mixed>>  $cohort
     * @return list<array{
     *     name:string,
     *     checkin:int,
     *     sudah_pvt:int,
     *     belum_pvt:int,
     *     lulus:int,
     *     tidak_lulus:int,
     *     pct_pvt:float,
     *     pct_lulus:float
     * }>
     */
    private function aggregateBy(array $cohort, string $dimension): array
    {
        $grouped = [];
        foreach ($cohort as $row) {
            $name = trim((string) ($row[$dimension] ?? ''));
            if ($name === '') {
                $name = 'Tidak diketahui';
            }
            if (! isset($grouped[$name])) {
                $grouped[$name] = [
                    'name' => $name,
                    'checkin' => 0,
                    'sudah_pvt' => 0,
                    'belum_pvt' => 0,
                    'lulus' => 0,
                    'tidak_lulus' => 0,
                ];
            }
            $grouped[$name]['checkin']++;
            $status = (string) ($row['pvt_status'] ?? 'belum');
            if ($status === 'belum') {
                $grouped[$name]['belum_pvt']++;
            } else {
                $grouped[$name]['sudah_pvt']++;
                if ($status === 'lulus') {
                    $grouped[$name]['lulus']++;
                } else {
                    $grouped[$name]['tidak_lulus']++;
                }
            }
        }

        $list = array_values($grouped);
        foreach ($list as &$item) {
            $checkin = (int) $item['checkin'];
            $sudah = (int) $item['sudah_pvt'];
            $item['pct_pvt'] = $checkin > 0 ? round($sudah / $checkin * 100, 1) : 0.0;
            $item['pct_lulus'] = $sudah > 0 ? round(((int) $item['lulus']) / $sudah * 100, 1) : 0.0;
        }
        unset($item);

        usort($list, static function (array $a, array $b): int {
            $cmp = $b['checkin'] <=> $a['checkin'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return $list;
    }

    /**
     * @return array{sites:list<string>,companies:list<string>}
     */
    private function buildFilterOptions(): array
    {
        return Cache::remember('evaluasi_well:pvt_filter_options:v1', 300, function (): array {
            $rows = DB::connection(BewellConnectionService::CONNECTION)
                ->table('employee_profiles as e')
                ->select(['e.kode_sid', 'e.site', 'e.nama_perusahaan'])
                ->whereNotNull('e.kode_sid')
                ->where('e.kode_sid', '<>', '')
                ->whereRaw("UPPER(TRIM(COALESCE(e.jabatan_fungsional, ''))) LIKE ?", ['%OPERATOR%'])
                ->whereRaw("UPPER(TRIM(COALESCE(e.jabatan_fungsional, ''))) <> ?", ['VISITOR'])
                ->get();

            $sites = [];
            $companies = [];
            foreach ($rows as $row) {
                $site = $this->siteResolver->resolve(
                    isset($row->kode_sid) ? (string) $row->kode_sid : null,
                    isset($row->site) ? (string) $row->site : null,
                );
                if ($site !== '') {
                    $sites[$site] = true;
                }
                $company = $this->companyAliasResolver->resolve(
                    isset($row->nama_perusahaan) ? (string) $row->nama_perusahaan : null
                );
                if ($company !== '') {
                    $companies[$company] = true;
                }
            }

            $siteList = array_keys($sites);
            $companyList = array_keys($companies);
            sort($siteList, SORT_STRING);
            sort($companyList, SORT_STRING);

            return [
                'sites' => $siteList,
                'companies' => $companyList,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function presentRow(array $row): array
    {
        $status = (string) ($row['pvt_status'] ?? 'belum');
        $evalLabel = trim((string) ($row['evaluation_label'] ?? ''));
        $statusLabel = match ($status) {
            'lulus' => 'Lulus',
            'tidak_lulus' => 'Tidak lulus',
            default => 'Belum tes',
        };
        $resultLabel = match ($status) {
            'lulus' => $evalLabel !== '' ? $evalLabel : 'Memenuhi skrining PVT',
            'tidak_lulus' => $evalLabel !== '' ? $evalLabel : 'Di bawah ambang skrining PVT',
            default => 'Belum dilaksanakan',
        };

        return [
            'id' => (int) ($row['id'] ?? 0),
            'nama' => (string) ($row['nama'] ?: '-'),
            'kode_sid' => (string) ($row['kode_sid'] ?: '-'),
            'site' => (string) ($row['site'] ?: '-'),
            'company' => (string) ($row['company'] ?: '-'),
            'jabatan' => (string) ($row['jabatan'] ?: '-'),
            'gate' => (string) ($row['gate'] ?: '-'),
            'checked_in_at' => $this->formatDateTime((string) ($row['checked_in_at'] ?? '')),
            'checked_in_at_raw' => (string) ($row['checked_in_at'] ?? ''),
            'status_lolos' => (string) ($row['status_lolos'] ?: '-'),
            'pvt_status' => $status,
            'pvt_status_label' => $statusLabel,
            'pvt_done_label' => $status === 'belum' ? 'Belum' : 'Sudah',
            'pvt_result_label' => $resultLabel,
            'mean_rt_ms' => $row['mean_rt_ms'] === null ? '' : (string) $row['mean_rt_ms'],
            'median_rt_ms' => $row['median_rt_ms'] === null ? '' : (string) $row['median_rt_ms'],
            'lapses' => $row['lapses'] === null ? '' : (string) $row['lapses'],
            'false_starts' => $row['false_starts'] === null ? '' : (string) $row['false_starts'],
            'tested_at' => $this->formatDateTime((string) ($row['tested_at'] ?? '')),
            'evaluation_label' => $evalLabel !== '' ? $evalLabel : '-',
        ];
    }

    private function formatDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '-';
        }

        try {
            return Carbon::parse($value, config('app.timezone'))->format('d M Y H:i');
        } catch (Throwable) {
            return $value;
        }
    }

    private function dateLabel(string $date): string
    {
        try {
            return Carbon::parse($date, config('app.timezone'))->translatedFormat('d M Y');
        } catch (Throwable) {
            return $date;
        }
    }

    /**
     * @param  array{date:string,site:string,company:string,pvt_status:string}  $filters
     * @return array<string, mixed>
     */
    private function emptyPayload(array $filters): array
    {
        return [
            'bewellUp' => false,
            'rfidUp' => false,
            'filters' => $filters,
            'dateLabel' => $this->dateLabel($filters['date']),
            'kpi' => [
                'checkin' => 0,
                'sudah_pvt' => 0,
                'belum_pvt' => 0,
                'lulus' => 0,
                'tidak_lulus' => 0,
            ],
            'siteRows' => [],
            'companyRows' => [],
            'checkinChart' => $this->emptyCheckinChart(),
            'filterOptions' => [
                'sites' => [],
                'companies' => [],
            ],
        ];
    }

    /**
     * @param  array{date:string,site:string,company:string,pvt_status:string}  $filters
     * @return array{
     *     categories: list<string>,
     *     dates: list<string>,
     *     checkin: list<int>,
     *     lulus: list<int>,
     *     tidak_lulus: list<int>,
     *     belum: list<int>,
     *     pct_sudah: list<float>
     * }
     */
    private function checkinChart(array $filters): array
    {
        $cacheKey = 'evaluasi_well:pvt_checkin_chart:v1:'.sha1((string) json_encode([
            $filters['date'],
            $filters['site'],
            $filters['company'],
        ], JSON_THROW_ON_ERROR));

        /** @var array{categories: list<string>, dates: list<string>, checkin: list<int>, lulus: list<int>, tidak_lulus: list<int>, belum: list<int>, pct_sudah: list<float>} */
        return Cache::remember($cacheKey, self::CHART_CACHE_TTL, fn (): array => $this->buildCheckinChart($filters));
    }

    /**
     * @param  array{date:string,site:string,company:string,pvt_status:string}  $filters
     * @return array{
     *     categories: list<string>,
     *     dates: list<string>,
     *     checkin: list<int>,
     *     lulus: list<int>,
     *     tidak_lulus: list<int>,
     *     belum: list<int>,
     *     pct_sudah: list<float>
     * }
     */
    private function buildCheckinChart(array $filters): array
    {
        $tz = (string) config('app.timezone');
        $end = Carbon::parse($filters['date'], $tz)->startOfDay();
        $start = $end->copy()->subDays(6);
        $dates = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $dates[] = $cursor->toDateString();
        }

        $empty = $this->presentCheckinChart($dates, []);
        if (! $this->rfidReader->isUp()) {
            return $empty;
        }

        $operators = $this->loadOperators([
            'date' => $filters['date'],
            'site' => $filters['site'],
            'company' => $filters['company'],
            'pvt_status' => '',
        ]);
        if ($operators === []) {
            return $empty;
        }

        $sids = array_map(static fn (array $op): string => $op['kode_sid'], $operators);
        $byDay = $this->rfidReader->firstPassedCheckinsByDayForSids(
            $start->toDateString(),
            $end->toDateString(),
            $sids,
        );
        $pvtBySid = $this->loadPvtBySid($sids, $start->toDateString(), $end->toDateString());

        $operatorSids = [];
        foreach ($operators as $operator) {
            $operatorSids[mb_strtoupper($operator['kode_sid'])] = true;
        }

        $dayStats = [];
        foreach ($dates as $date) {
            $stats = [
                'checkin' => 0,
                'lulus' => 0,
                'tidak_lulus' => 0,
                'belum' => 0,
            ];
            foreach (($byDay[$date] ?? []) as $upper => $checkin) {
                if (! isset($operatorSids[$upper])) {
                    continue;
                }
                $candidates = [];
                foreach ($pvtBySid[$upper] ?? [] as $pvt) {
                    if (str_starts_with((string) ($pvt['tested_at'] ?? ''), $date)) {
                        $candidates[] = $pvt;
                    }
                }
                $matched = $this->matchPvtForCheckin($candidates, (string) ($checkin['checked_in_at'] ?? ''));
                $status = $this->resolvePvtStatus($matched);
                $stats['checkin']++;
                if ($status === 'lulus') {
                    $stats['lulus']++;
                } elseif ($status === 'tidak_lulus') {
                    $stats['tidak_lulus']++;
                } else {
                    $stats['belum']++;
                }
            }
            $dayStats[$date] = $stats;
        }

        return $this->presentCheckinChart($dates, $dayStats);
    }

    /**
     * @param  list<string>  $dates
     * @param  array<string, array{checkin:int,lulus:int,tidak_lulus:int,belum:int}>  $dayStats
     * @return array{
     *     categories: list<string>,
     *     dates: list<string>,
     *     checkin: list<int>,
     *     lulus: list<int>,
     *     tidak_lulus: list<int>,
     *     belum: list<int>,
     *     pct_sudah: list<float>
     * }
     */
    private function presentCheckinChart(array $dates, array $dayStats): array
    {
        $categories = [];
        $checkin = [];
        $lulus = [];
        $tidakLulus = [];
        $belum = [];
        $pctSudah = [];

        foreach ($dates as $date) {
            $stats = $dayStats[$date] ?? [
                'checkin' => 0,
                'lulus' => 0,
                'tidak_lulus' => 0,
                'belum' => 0,
            ];
            $total = (int) $stats['checkin'];
            $sudah = (int) $stats['lulus'] + (int) $stats['tidak_lulus'];
            $categories[] = Carbon::parse($date, config('app.timezone'))->translatedFormat('d M');
            $checkin[] = $total;
            $lulus[] = (int) $stats['lulus'];
            $tidakLulus[] = (int) $stats['tidak_lulus'];
            $belum[] = (int) $stats['belum'];
            $pctSudah[] = $total > 0 ? round($sudah / $total * 100, 2) : 0.0;
        }

        return [
            'categories' => $categories,
            'dates' => $dates,
            'checkin' => $checkin,
            'lulus' => $lulus,
            'tidak_lulus' => $tidakLulus,
            'belum' => $belum,
            'pct_sudah' => $pctSudah,
        ];
    }

    /**
     * @return array{
     *     categories: list<string>,
     *     dates: list<string>,
     *     checkin: list<int>,
     *     lulus: list<int>,
     *     tidak_lulus: list<int>,
     *     belum: list<int>,
     *     pct_sudah: list<float>
     * }
     */
    private function emptyCheckinChart(): array
    {
        return [
            'categories' => [],
            'dates' => [],
            'checkin' => [],
            'lulus' => [],
            'tidak_lulus' => [],
            'belum' => [],
            'pct_sudah' => [],
        ];
    }
}
