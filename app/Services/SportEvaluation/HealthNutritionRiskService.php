<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Risiko MCU metabolik × pola makan (read-only BeMCU + BeWell).
 */
final class HealthNutritionRiskService
{
    private const CACHE_TTL = 180;

    public function __construct(
        private readonly McuConnectionService $mcu,
        private readonly BewellConnectionService $bewell,
        private readonly NutritionEvaluationService $nutrition,
    ) {}

    /**
     * @param  array{site?:string,company?:string,division?:string,mcu_severity?:string,lab_type?:string}  $filters
     * @return array<string,mixed>
     */
    public function dashboard(array $filters = []): array
    {
        $payload = $this->buildDataset($filters);

        return [
            'bewellUp' => $this->bewell->isUp(),
            'mcuUp' => $this->mcu->isUp(),
            'mcuMappingReady' => $this->mcu->isMappingReady(),
            'weekLabel' => $payload['weekLabel'],
            'kpi' => $payload['kpi'],
            'labChartLabels' => $payload['labChartLabels'],
            'labChartWarn' => $payload['labChartWarn'],
            'labChartHigh' => $payload['labChartHigh'],
            'filterOptions' => $payload['filterOptions'],
            'filters' => $filters,
            'priorityOneTotal' => $payload['kpi']['p1'],
        ];
    }

    /**
     * @return array{draw:int,recordsTotal:int,recordsFiltered:int,data:array<int,array<string,mixed>>}
     */
    public function datatable(Request $request): array
    {
        $filters = $this->readFilters($request);
        $dataset = $this->buildDataset($filters);
        $rows = $dataset['priorityOne'];

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $hay = mb_strtolower(implode(' ', [
                    $row['nama'],
                    $row['kode_sid'],
                    $row['company'],
                    $row['divisi'],
                    $row['evidence'],
                    implode(' ', $row['alert_codes']),
                ]));

                return str_contains($hay, $needle);
            }));
        }

        $recordsFiltered = count($rows);
        $recordsTotal = $dataset['kpi']['p1'];

        $orderCol = (int) data_get($request->input('order'), '0.column', 6);
        $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = [
            0 => 'nama',
            1 => 'company',
            2 => 'divisi',
            6 => 'risk_score',
        ];
        $sortKey = $sortable[$orderCol] ?? 'risk_score';
        usort($rows, static function (array $a, array $b) use ($sortKey, $orderDir): int {
            $cmp = $a[$sortKey] <=> $b[$sortKey];
            if ($cmp === 0) {
                $cmp = strcmp((string) $a['nama'], (string) $b['nama']);
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

        return [
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $page,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function exportRows(Request $request): array
    {
        $filters = $this->readFilters($request);
        $dataset = $this->buildDataset($filters);
        $rows = $dataset['priorityOne'];

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $hay = mb_strtolower(implode(' ', [
                    $row['nama'],
                    $row['kode_sid'],
                    $row['company'],
                    $row['divisi'],
                    $row['evidence'],
                ]));

                return str_contains($hay, $needle);
            }));
        }

        return $rows;
    }

    public function logAccess(string $route, array $filters = []): void
    {
        Log::info('health_nutrition_access', [
            'user_id' => auth()->id(),
            'route' => $route,
            'filters' => $filters,
        ]);
    }

    /**
     * @return array{site:string,company:string,division:string,mcu_severity:string,lab_type:string}
     */
    public function readFilters(Request $request): array
    {
        $read = static fn (mixed $v): string => is_string($v) ? mb_substr(trim($v), 0, 150) : '';

        $severity = strtolower($read($request->input('mcu_severity')));
        if (! in_array($severity, ['warn', 'high'], true)) {
            $severity = '';
        }

        $lab = strtolower($read($request->input('lab_type')));
        if (! in_array($lab, ['glucose', 'cholesterol', 'triglyceride', 'uric_acid'], true)) {
            $lab = '';
        }

        return [
            'site' => $read($request->input('site')),
            'company' => $read($request->input('company')),
            'division' => $read($request->input('division')),
            'mcu_severity' => $severity,
            'lab_type' => $lab,
        ];
    }

    /**
     * @param  array{site?:string,company?:string,division?:string,mcu_severity?:string,lab_type?:string}  $filters
     * @return array<string,mixed>
     */
    private function buildDataset(array $filters): array
    {
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = Carbon::now()->endOfWeek(Carbon::SUNDAY);
        $weekLabel = $weekStart->translatedFormat('d M').' – '.$weekEnd->translatedFormat('d M Y');

        $empty = [
            'weekLabel' => $weekLabel,
            'kpi' => ['p1' => 0, 'p2' => 0, 'p3' => 0, 'mcu_abnormal' => 0],
            'labChartLabels' => ['Gula darah', 'Kolesterol', 'Trigliserida', 'Asam urat'],
            'labChartWarn' => [0, 0, 0, 0],
            'labChartHigh' => [0, 0, 0, 0],
            'filterOptions' => [
                'sites' => [],
                'companies' => [],
                'divisions' => [],
            ],
            'priorityOne' => [],
        ];

        $bewellUp = $this->bewell->isUp();
        if (! $bewellUp) {
            return $empty;
        }

        $filterHash = sha1(json_encode($filters, JSON_THROW_ON_ERROR));
        $cacheKey = 'evaluasi_well:health_nutrition:v4:'.$filterHash;

        try {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL,
                function () use ($filters, $weekLabel, $weekStart, $weekEnd): array {
                    $mcuRows = $this->fetchLatestMetabolicFindings();
                    $mapped = $this->mapToActiveEmployees($mcuRows);

                    $filterOptions = $this->buildFilterOptions($mapped);

                    if (($filters['site'] ?? '') !== '') {
                        $mapped = $mapped->filter(
                            static fn (array $row): bool => $row['site'] === $filters['site']
                        )->values();
                    }
                    if (($filters['company'] ?? '') !== '') {
                        $mapped = $mapped->filter(
                            static fn (array $row): bool => $row['company'] === $filters['company']
                        )->values();
                    }
                    if (($filters['division'] ?? '') !== '') {
                        $needle = mb_strtolower((string) $filters['division']);
                        $mapped = $mapped->filter(
                            static fn (array $row): bool => str_contains(mb_strtolower($row['divisi']), $needle)
                        )->values();
                    }
                    if (($filters['mcu_severity'] ?? '') !== '') {
                        $mapped = $mapped->filter(
                            static fn (array $row): bool => $row['mcu_max_severity'] === $filters['mcu_severity']
                        )->values();
                    }
                    if (($filters['lab_type'] ?? '') !== '') {
                        $lab = (string) $filters['lab_type'];
                        $mapped = $mapped->filter(
                            static fn (array $row): bool => isset($row['findings'][$lab])
                        )->values();
                    }

                    $userIds = $mapped->pluck('user_id')->map(static fn ($id): int => (int) $id)->all();
                    $dietByUser = $this->nutrition->evaluateDietRiskForUsers($userIds, $weekStart, $weekEnd);

                    $uricUserIds = $mapped
                        ->filter(static fn (array $row): bool => isset($row['findings']['uric_acid']))
                        ->pluck('user_id')
                        ->map(static fn ($id): int => (int) $id)
                        ->unique()
                        ->values()
                        ->all();
                    $purineHits = $this->findUsersWithPurineFood($uricUserIds, $weekStart, $weekEnd);

                    // P3: poor diet among AKTIF without requiring MCU match — limited sample from diet alerts on mapped empty set
                    $p3Count = 0;
                    if ($mapped->isEmpty() && $this->mcu->isUp() && $this->mcu->isMappingReady()) {
                        $p3Count = 0;
                    }

                    $priorityOne = [];
                    $p1 = 0;
                    $p2 = 0;
                    $labWarn = ['glucose' => 0, 'cholesterol' => 0, 'triglyceride' => 0, 'uric_acid' => 0];
                    $labHigh = ['glucose' => 0, 'cholesterol' => 0, 'triglyceride' => 0, 'uric_acid' => 0];

                    foreach ($mapped as $row) {
                        $uid = (int) $row['user_id'];
                        $diet = $dietByUser[$uid] ?? [
                            'has_poor_diet' => false,
                            'alert_codes' => [],
                            'alerts' => [],
                            'days_logged' => 0,
                            'avg_calories' => 0.0,
                            'risk_score' => 0,
                        ];

                        foreach ($row['findings'] as $labKey => $finding) {
                            if ($finding['severity'] === 'high') {
                                $labHigh[$labKey]++;
                            } else {
                                $labWarn[$labKey]++;
                            }
                        }

                        $evidence = $this->buildEvidence($row['findings'], $diet);
                        if (isset($purineHits[$uid], $row['findings']['uric_acid'])) {
                            $evidence[] = 'Asam urat tinggi + indikasi makanan tinggi purin';
                            $diet['has_poor_diet'] = true;
                            $diet['alert_codes'][] = 'purine_risk_estimate';
                            $diet['risk_score'] += 2;
                        }

                        $mcuScore = $row['mcu_max_severity'] === 'high' ? 4 : 2;
                        $riskScore = $mcuScore + (int) $diet['risk_score'];

                        if ($diet['has_poor_diet']) {
                            $p1++;
                            $priorityOne[] = [
                                'user_id' => $uid,
                                'nama' => $row['nama'],
                                'kode_sid' => $row['kode_sid'],
                                'site' => $row['site'],
                                'company' => $row['company'],
                                'divisi' => $row['divisi'],
                                'mcu_badges' => $this->formatMcuBadges($row['findings']),
                                'alert_codes' => array_values(array_unique($diet['alert_codes'])),
                                'days_logged' => (int) $diet['days_logged'],
                                'avg_calories' => (float) $diet['avg_calories'],
                                'evidence' => implode(' · ', $evidence) ?: 'MCU metabolik abnormal + pola makan berisiko',
                                'risk_score' => $riskScore,
                                'mcu_max_severity' => $row['mcu_max_severity'],
                            ];
                        } else {
                            $p2++;
                        }
                    }

                    // P3: karyawan AKTIF dengan pola makan buruk tanpa MCU abnormal di dataset ini
                    // Ambil kandidat terbatas dari food_analyses 7 hari yang tidak ada di mapped MCU
                    $p3Count = $this->countPriorityThree($userIds, $weekStart, $weekEnd);

                    usort($priorityOne, static function (array $a, array $b): int {
                        return $b['risk_score'] <=> $a['risk_score']
                            ?: strcmp($a['nama'], $b['nama']);
                    });

                    return [
                        'weekLabel' => $weekLabel,
                        'kpi' => [
                            'p1' => $p1,
                            'p2' => $p2,
                            'p3' => $p3Count,
                            'mcu_abnormal' => $mapped->count(),
                        ],
                        'labChartLabels' => ['Gula darah', 'Kolesterol', 'Trigliserida', 'Asam urat'],
                        'labChartWarn' => [
                            $labWarn['glucose'],
                            $labWarn['cholesterol'],
                            $labWarn['triglyceride'],
                            $labWarn['uric_acid'],
                        ],
                        'labChartHigh' => [
                            $labHigh['glucose'],
                            $labHigh['cholesterol'],
                            $labHigh['triglyceride'],
                            $labHigh['uric_acid'],
                        ],
                        'filterOptions' => $filterOptions,
                        'priorityOne' => $priorityOne,
                    ];
                }
            );
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * Ambil temuan metabolik terbaru per SID dari bcsid.mv_ftw_mcu (JSONB kondisi_*).
     *
     * @return Collection<int,array<string,mixed>>
     */
    private function fetchLatestMetabolicFindings(): Collection
    {
        if (! $this->mcu->isUp() || ! $this->mcu->isMappingReady()) {
            return collect();
        }

        $table = (string) config('bemcu.table');
        $examDate = (string) config('bemcu.exam_date');
        $sidCol = trim((string) config('bemcu.identity.sid', ''));
        $nikCol = trim((string) config('bemcu.identity.nik', ''));
        $jsonFields = array_values(array_filter(
            (array) config('bemcu.json_fields', ['kondisi_kritis', 'kondisi_non_kritis']),
            static fn ($f): bool => is_string($f) && trim($f) !== ''
        ));
        $labNameMap = $this->normalizeLabNameMap((array) config('bemcu.labs', []));
        $highConditions = array_map(
            static fn ($n): string => mb_strtolower(trim((string) $n)),
            (array) config('bemcu.high_severity_conditions', [])
        );

        if ($labNameMap === [] || $jsonFields === []) {
            return collect();
        }

        $identityExpr = $sidCol !== '' ? $sidCol : $nikCol;
        $selectParts = array_values(array_unique(array_filter([
            $examDate.' as exam_date',
            $sidCol !== '' ? $sidCol.' as mcu_sid' : 'NULL::text as mcu_sid',
            $nikCol !== '' ? $nikCol.' as mcu_nik' : 'NULL::text as mcu_nik',
            ...$jsonFields,
        ])));

        $labNames = array_values(array_unique(array_merge(...array_values($labNameMap))));
        $labPlaceholders = implode(', ', array_fill(0, count($labNames), '?'));

        try {
            // PostgreSQL hanya mengirim kondisi metabolik abnormal terbaru, bukan seluruh JSON MCU.
            $sql = 'WITH latest AS ('
                .' SELECT DISTINCT ON (UPPER(TRIM('.$identityExpr.'))) '
                .implode(', ', $selectParts)
                .' FROM '.$table
                .' WHERE '.$identityExpr.' IS NOT NULL'
                .' AND TRIM('.$identityExpr.") <> ''"
                .' ORDER BY UPPER(TRIM('.$identityExpr.')), '.$examDate.' DESC NULLS LAST'
                .'), metabolic AS ('
                .' SELECT exam_date, mcu_sid, mcu_nik, item'
                .' FROM latest'
                .' CROSS JOIN LATERAL jsonb_array_elements('
                ." COALESCE(kondisi_kritis, '[]'::jsonb) || COALESCE(kondisi_non_kritis, '[]'::jsonb)"
                .') AS item'
                .' WHERE LOWER(TRIM(item->>\'nama_kondisi\')) IN ('.$labPlaceholders.')'
                .' AND ('
                ." COALESCE(NULLIF(item->>'is_yes', '')::integer, 0) = 1"
                ." OR LOWER(COALESCE(item->>'note', '')) LIKE '%abnormal%'"
                .')'
                .')'
                .' SELECT exam_date, mcu_sid, mcu_nik,'
                ." '[]'::jsonb AS kondisi_kritis,"
                .' jsonb_agg(item) AS kondisi_non_kritis'
                .' FROM metabolic'
                .' GROUP BY exam_date, mcu_sid, mcu_nik';

            $rows = DB::connection(McuConnectionService::CONNECTION)->select($sql, $labNames);
        } catch (Throwable $e) {
            report($e);

            return collect();
        }

        $latest = [];
        foreach ($rows as $row) {
            $sid = isset($row->mcu_sid) ? trim((string) $row->mcu_sid) : '';
            $nik = isset($row->mcu_nik) ? trim((string) $row->mcu_nik) : '';
            $key = $sid !== '' ? 'sid:'.mb_strtoupper($sid) : ($nik !== '' ? 'nik:'.$nik : '');
            if ($key === '' || isset($latest[$key])) {
                continue;
            }

            $findings = $this->extractFindingsFromKondisi($row, $jsonFields, $labNameMap, $highConditions);
            if ($findings === []) {
                continue;
            }

            $maxSeverity = 'warn';
            foreach ($findings as $finding) {
                if ($finding['severity'] === 'high') {
                    $maxSeverity = 'high';
                    break;
                }
            }

            $latest[$key] = [
                'mcu_sid' => $sid,
                'mcu_nik' => $nik,
                'exam_date' => (string) ($row->exam_date ?? ''),
                'findings' => $findings,
                'mcu_max_severity' => $maxSeverity,
            ];
        }

        return collect(array_values($latest));
    }

    /**
     * @param  array<string,mixed>  $labs
     * @return array<string,array<int,string>> labKey => lowercased nama_kondisi
     */
    private function normalizeLabNameMap(array $labs): array
    {
        $map = [];
        foreach (['glucose', 'cholesterol', 'triglyceride', 'uric_acid'] as $key) {
            $raw = $labs[$key] ?? [];
            if (is_string($raw) && trim($raw) !== '') {
                $raw = [trim($raw)];
            }
            if (! is_array($raw)) {
                continue;
            }
            $names = [];
            foreach ($raw as $name) {
                $n = mb_strtolower(trim((string) $name));
                if ($n !== '') {
                    $names[] = $n;
                }
            }
            if ($names !== []) {
                $map[$key] = array_values(array_unique($names));
            }
        }

        return $map;
    }

    /**
     * @param  object  $row
     * @param  array<int,string>  $jsonFields
     * @param  array<string,array<int,string>>  $labNameMap
     * @param  array<int,string>  $highConditions lowercase
     * @return array<string,array{label:string,severity:string}>
     */
    private function extractFindingsFromKondisi(
        object $row,
        array $jsonFields,
        array $labNameMap,
        array $highConditions,
    ): array {
        $findings = [];

        foreach ($jsonFields as $field) {
            $items = $this->decodeKondisiJson($row->{$field} ?? null);
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $nama = mb_strtolower(trim((string) ($item['nama_kondisi'] ?? '')));
                if ($nama === '') {
                    continue;
                }

                $labKey = null;
                foreach ($labNameMap as $key => $names) {
                    if (in_array($nama, $names, true)) {
                        $labKey = $key;
                        break;
                    }
                }
                if ($labKey === null) {
                    continue;
                }

                $severity = $this->classifyKondisiItem($item, $nama, $highConditions);
                if ($severity === null) {
                    continue;
                }

                if (! isset($findings[$labKey]) || ($severity === 'high' && $findings[$labKey]['severity'] === 'warn')) {
                    $findings[$labKey] = [
                        'label' => $this->labLabel($labKey),
                        'severity' => $severity,
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<int,mixed>
     */
    private function decodeKondisiJson(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_object($raw)) {
            return (array) $raw;
        }
        if (! is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string,mixed>  $item
     * @param  array<int,string>  $highConditions lowercase
     */
    private function classifyKondisiItem(array $item, string $namaLower, array $highConditions): ?string
    {
        $isYes = ! empty($item['is_yes']);
        $isNo = ! empty($item['is_no']);
        $isNa = ! empty($item['is_na']);
        $note = mb_strtolower(trim((string) ($item['note'] ?? '')));

        if ($isNo || $isNa) {
            return null;
        }

        $isAbnormalNote = $note !== '' && str_contains($note, 'abnormal');
        $isPositive = $isYes || $isAbnormalNote;

        if (! $isPositive) {
            return null;
        }

        if ($isYes || in_array($namaLower, $highConditions, true)) {
            return 'high';
        }

        return 'warn';
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $mcuRows
     * @return Collection<int,array<string,mixed>>
     */
    private function mapToActiveEmployees(Collection $mcuRows): Collection
    {
        if ($mcuRows->isEmpty() || ! $this->bewell->isUp()) {
            return collect();
        }

        $sids = $mcuRows->pluck('mcu_sid')->filter()->map(static fn ($v): string => mb_strtoupper(trim((string) $v)))->unique()->values()->all();
        $niks = $mcuRows->pluck('mcu_nik')->filter()->map(static fn ($v): string => trim((string) $v))->unique()->values()->all();

        $profiles = collect();
        $db = DB::connection(BewellConnectionService::CONNECTION);

        foreach (array_chunk($sids, 800) as $chunk) {
            if ($chunk === []) {
                continue;
            }
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rows = $db->table('employee_profiles')
                ->where('status_karyawan', 'AKTIF')
                ->whereRaw('UPPER(TRIM(kode_sid)) IN ('.$placeholders.')', $chunk)
                ->get(['id', 'nama', 'kode_sid', 'nik', 'site', 'nama_perusahaan', 'divisi']);
            $profiles = $profiles->concat($rows);
        }

        foreach (array_chunk($niks, 800) as $chunk) {
            if ($chunk === []) {
                continue;
            }
            $rows = $db->table('employee_profiles')
                ->where('status_karyawan', 'AKTIF')
                ->whereIn('nik', $chunk)
                ->get(['id', 'nama', 'kode_sid', 'nik', 'site', 'nama_perusahaan', 'divisi']);
            $profiles = $profiles->concat($rows);
        }

        $bySid = [];
        $byNik = [];
        foreach ($profiles as $profile) {
            $sid = mb_strtoupper(trim((string) ($profile->kode_sid ?? '')));
            $nik = trim((string) ($profile->nik ?? ''));
            if ($sid !== '' && ! isset($bySid[$sid])) {
                $bySid[$sid] = $profile;
            }
            if ($nik !== '' && ! isset($byNik[$nik])) {
                $byNik[$nik] = $profile;
            }
        }

        $mapped = [];
        $seenUser = [];
        foreach ($mcuRows as $mcu) {
            $sid = mb_strtoupper(trim((string) $mcu['mcu_sid']));
            $nik = trim((string) $mcu['mcu_nik']);
            $profile = null;
            if ($sid !== '' && isset($bySid[$sid])) {
                $profile = $bySid[$sid];
            } elseif ($nik !== '' && isset($byNik[$nik])) {
                $profile = $byNik[$nik];
            }
            if ($profile === null) {
                continue;
            }
            $uid = (int) $profile->id;
            if (isset($seenUser[$uid])) {
                continue;
            }
            $seenUser[$uid] = true;

            $mapped[] = [
                'user_id' => $uid,
                'nama' => (string) ($profile->nama ?: 'User #'.$uid),
                'kode_sid' => (string) ($profile->kode_sid ?: '-'),
                'site' => (string) (trim((string) ($profile->site ?? '')) !== '' ? $profile->site : '-'),
                'company' => (string) (trim((string) ($profile->nama_perusahaan ?? '')) !== '' ? $profile->nama_perusahaan : '-'),
                'divisi' => (string) (trim((string) ($profile->divisi ?? '')) !== '' ? $profile->divisi : '-'),
                'findings' => $mcu['findings'],
                'mcu_max_severity' => $mcu['mcu_max_severity'],
                'exam_date' => $mcu['exam_date'],
            ];
        }

        return collect($mapped);
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $mapped
     * @return array{sites:array<int,string>,companies:array<int,string>,divisions:array<int,string>}
     */
    private function buildFilterOptions(Collection $mapped): array
    {
        return [
            'sites' => $mapped->pluck('site')->filter(static fn ($v) => $v !== '-')->unique()->sort()->values()->all(),
            'companies' => $mapped->pluck('company')->filter(static fn ($v) => $v !== '-')->unique()->sort()->values()->all(),
            'divisions' => $mapped->pluck('divisi')->filter(static fn ($v) => $v !== '-')->unique()->sort()->values()->all(),
        ];
    }

    /**
     * @param  array<string,array{label:string,severity:string}>  $findings
     * @param  array<string,mixed>  $diet
     * @return array<int,string>
     */
    private function buildEvidence(array $findings, array $diet): array
    {
        $codes = $diet['alert_codes'] ?? [];
        $parts = [];

        if (isset($findings['glucose']) && (in_array('carb_over', $codes, true) || in_array('sugar_risk_estimate', $codes, true))) {
            $parts[] = 'Gula darah abnormal + karbo/manis tinggi';
        }
        if (isset($findings['triglyceride']) && (in_array('calorie_over', $codes, true) || in_array('carb_over', $codes, true))) {
            $parts[] = 'Trigliserida abnormal + kalori/karbo berlebih';
        }
        if (isset($findings['cholesterol']) && in_array('calorie_over', $codes, true)) {
            $parts[] = 'Kolesterol abnormal + kalori berlebih';
        }
        if ($parts === [] && ($diet['has_poor_diet'] ?? false)) {
            $parts[] = 'MCU metabolik abnormal + alert pola makan';
        }

        return $parts;
    }

    /**
     * Batch cek makanan tinggi purin (hindari N+1 per user).
     *
     * @param  array<int,int>  $userIds
     * @return array<int,true> user_id => true
     */
    private function findUsersWithPurineFood(array $userIds, Carbon $from, Carbon $to): array
    {
        $keywords = config('bemcu.purine_keywords', []);
        if ($userIds === [] || $keywords === []) {
            return [];
        }

        $hits = [];
        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr = $to->format('Y-m-d H:i:s');

        try {
            foreach (array_chunk($userIds, 800) as $chunk) {
                $rows = DB::connection(BewellConnectionService::CONNECTION)
                    ->table('food_analyses')
                    ->whereIn('user_id', $chunk)
                    ->whereBetween('created_at', [$fromStr, $toStr])
                    ->limit(5000)
                    ->get(['user_id', 'food_name']);

                foreach ($rows as $row) {
                    $uid = (int) $row->user_id;
                    if (isset($hits[$uid])) {
                        continue;
                    }
                    $hay = mb_strtolower((string) ($row->food_name ?? ''));
                    if ($hay === '') {
                        continue;
                    }
                    foreach ($keywords as $kw) {
                        if (str_contains($hay, mb_strtolower((string) $kw))) {
                            $hits[$uid] = true;
                            break;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return $hits;
    }

    private function hasPurineFood(int $userId, Carbon $from, Carbon $to): bool
    {
        return isset($this->findUsersWithPurineFood([$userId], $from, $to)[$userId]);
    }

    /**
     * @param  array<int,int>  $excludeUserIds
     */
    private function countPriorityThree(array $excludeUserIds, Carbon $from, Carbon $to): int
    {
        try {
            $candidateIds = DB::connection(BewellConnectionService::CONNECTION)
                ->table('food_analyses as f')
                ->join('employee_profiles as e', 'e.id', '=', 'f.user_id')
                ->where('e.status_karyawan', 'AKTIF')
                ->whereBetween('f.created_at', [
                    $from->format('Y-m-d H:i:s'),
                    $to->format('Y-m-d H:i:s'),
                ])
                ->when($excludeUserIds !== [], static function ($q) use ($excludeUserIds): void {
                    $q->whereNotIn('f.user_id', $excludeUserIds);
                })
                ->distinct()
                ->limit(500)
                ->pluck('f.user_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if ($candidateIds === []) {
                return 0;
            }

            $diet = $this->nutrition->evaluateDietRiskForUsers($candidateIds, $from, $to);
            $count = 0;
            foreach ($diet as $row) {
                if ($row['has_poor_diet']) {
                    $count++;
                }
            }

            return $count;
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    /**
     * @param  array<string,array{label:string,severity:string}>  $findings
     * @return array<int,array{label:string,severity:string,class:string}>
     */
    private function formatMcuBadges(array $findings): array
    {
        $badges = [];
        foreach ($findings as $finding) {
            $badges[] = [
                'label' => $finding['label'].' · '.($finding['severity'] === 'high' ? 'Tinggi' : 'Waspada'),
                'severity' => $finding['severity'],
                'class' => $finding['severity'] === 'high'
                    ? 'bg-danger-focus text-danger-main'
                    : 'bg-warning-focus text-warning-main',
            ];
        }

        return $badges;
    }

    private function labLabel(string $key): string
    {
        return match ($key) {
            'glucose' => 'Gula darah',
            'cholesterol' => 'Kolesterol',
            'triglyceride' => 'Trigliserida',
            'uric_acid' => 'Asam urat',
            default => $key,
        };
    }
}
