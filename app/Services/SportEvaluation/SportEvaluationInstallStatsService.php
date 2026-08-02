<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Agregasi statistik install aplikasi BeWell per dimensi organisasi (read-only).
 * Mendukung filter global (site / divisi grup / jabatan / perusahaan / departemen / install).
 */
final class SportEvaluationInstallStatsService
{
    private const CACHE_TTL = 180;

    private const CHART_TOP_N = 15;

    /** @var array<string, string> */
    private const DIMENSION_LABELS = [
        'site' => 'Site',
        'divisi' => 'Divisi',
        'company' => 'Perusahaan',
        'departement' => 'Departemen',
        'jabatan' => 'Jabatan',
    ];

    /** @var array<string, string> */
    private const DIMENSION_ICONS = [
        'site' => 'solar:map-point-bold',
        'divisi' => 'solar:diagram-up-bold',
        'company' => 'solar:buildings-2-bold',
        'departement' => 'solar:users-group-rounded-bold',
        'jabatan' => 'solar:case-round-bold',
    ];

    /** @var list<string> */
    private const BAR_CLASSES = [
        'bg-primary-600',
        'bg-orange',
        'bg-yellow',
        'bg-success-main',
        'bg-info-main',
        'bg-indigo',
    ];

    public function __construct(
        private readonly BewellConnectionService $connection,
        private readonly SportEvaluationKaryawanWellSiteResolver $siteResolver,
        private readonly SportEvaluationDivisiGroupResolver $divisiGroupResolver,
    ) {}

    /**
     * @param  array{
     *     site?:string,
     *     division_group?:string,
     *     jabatan?:string,
     *     company?:string,
     *     departement?:string,
     *     install?:string
     * }  $filters
     * @return array<string, mixed>
     */
    public function getStats(string $dimension, array $filters = []): array
    {
        $dimension = $this->resolveDimension($dimension);
        $filters = $this->normalizeFilters($filters);
        $empty = $this->emptyPayload($dimension, 'Koneksi BeWell belum tersedia.');

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $cacheKey = 'evaluasi_well:install_stats:v5:'.$dimension.':'.sha1(json_encode($filters, JSON_THROW_ON_ERROR));

            $stats = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($dimension, $filters): array {
                return $this->buildStats($dimension, $filters);
            });

            $stats['overview'] = $this->getOverview($filters);
            $stats['filters'] = $filters;
            $stats['filter_options'] = $this->filterOptions();

            return $stats;
        } catch (Throwable $e) {
            report($e);

            return $this->emptyPayload($dimension, 'Gagal memuat statistik install.');
        }
    }

    /**
     * @param  array{
     *     site?:string,
     *     division_group?:string,
     *     jabatan?:string,
     *     company?:string,
     *     departement?:string,
     *     install?:string
     * }  $filters
     * @return list<array<string, mixed>>
     */
    public function getOverview(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);

        if (! $this->connection->isUp()) {
            return $this->emptyOverview();
        }

        try {
            $cacheKey = 'evaluasi_well:install_stats:overview:v5:'.sha1(json_encode($filters, JSON_THROW_ON_ERROR));

            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters): array {
                $overview = [];
                $rows = $this->filteredEmployeeRows($filters);

                foreach (array_keys(self::DIMENSION_LABELS) as $dimension) {
                    $stats = $this->aggregateRows($dimension, $rows, $filters);
                    $summary = $stats['summary'];
                    $top = $stats['rows'][0] ?? null;

                    $overview[] = [
                        'dimension' => $dimension,
                        'label' => self::DIMENSION_LABELS[$dimension],
                        'icon' => self::DIMENSION_ICONS[$dimension],
                        'groups' => (int) ($summary['groups'] ?? count($stats['rows'])),
                        'total' => (int) $summary['total'],
                        'installed' => (int) $summary['installed'],
                        'not_installed' => (int) $summary['not_installed'],
                        'adoption_pct' => (float) $summary['adoption_pct'],
                        'top_name' => $top !== null ? (string) $top['name'] : '-',
                        'top_installed' => $top !== null ? (int) $top['installed'] : 0,
                        'top_pct' => $top !== null ? (float) $top['pct'] : 0.0,
                    ];
                }

                return $overview;
            });
        } catch (Throwable $e) {
            report($e);

            return $this->emptyOverview();
        }
    }

    /**
     * @return array{
     *     sites:list<string>,
     *     division_groups:list<string>,
     *     companies:list<string>,
     *     departements:list<string>,
     *     jabatans:list<string>
     * }
     */
    public function filterOptions(): array
    {
        if (! $this->connection->isUp()) {
            return [
                'sites' => [],
                'division_groups' => $this->divisiGroupResolver->groupLabels(),
                'companies' => [],
                'departements' => [],
                'jabatans' => [],
            ];
        }

        try {
            return Cache::remember('evaluasi_well:install_stats:filter_options:v1', self::CACHE_TTL, function (): array {
                $rows = $this->rawEmployeeRows();
                $sites = [];
                $companies = [];
                $departements = [];
                $jabatans = [];
                $divisionGroups = [];

                foreach ($rows as $row) {
                    $site = $this->siteResolver->resolve($row['kode_sid'], $row['site']);
                    if ($site !== '') {
                        $sites[$site] = true;
                    }

                    $group = $this->divisiGroupResolver->resolve($row['divisi']);
                    if ($group !== '') {
                        $divisionGroups[$group] = true;
                    }

                    if ($row['company'] !== '') {
                        $companies[$row['company']] = true;
                    }
                    if ($row['departement'] !== '') {
                        $departements[$row['departement']] = true;
                    }
                    if ($row['jabatan'] !== '' && mb_strtoupper($row['jabatan']) !== 'VISITOR') {
                        $jabatans[$row['jabatan']] = true;
                    }
                }

                $siteList = array_keys($sites);
                $companyList = array_keys($companies);
                $deptList = array_keys($departements);
                $jabatanList = array_keys($jabatans);
                $divList = array_keys($divisionGroups);
                sort($siteList, SORT_STRING);
                sort($companyList, SORT_STRING);
                sort($deptList, SORT_STRING);
                sort($jabatanList, SORT_STRING);
                sort($divList, SORT_STRING);

                return [
                    'sites' => $siteList,
                    'division_groups' => $divList,
                    'companies' => $companyList,
                    'departements' => $deptList,
                    'jabatans' => $jabatanList,
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return [
                'sites' => [],
                'division_groups' => $this->divisiGroupResolver->groupLabels(),
                'companies' => [],
                'departements' => [],
                'jabatans' => [],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     site:string,
     *     division_group:string,
     *     jabatan:string,
     *     company:string,
     *     departement:string,
     *     install:string
     * }
     */
    public function normalizeFilters(array $filters): array
    {
        $read = static function (mixed $value): string {
            if (! is_string($value)) {
                return '';
            }

            return mb_substr(trim($value), 0, 180);
        };

        $install = strtolower($read($filters['install'] ?? ''));
        if (! in_array($install, ['sudah', 'belum'], true)) {
            $install = '';
        }

        $jabatan = $read($filters['jabatan'] ?? $filters['jabatan_fungsional'] ?? '');
        if (mb_strtoupper($jabatan) === 'VISITOR') {
            $jabatan = '';
        }

        return [
            'site' => $read($filters['site'] ?? ''),
            'division_group' => $read($filters['division_group'] ?? $filters['division'] ?? ''),
            'jabatan' => $jabatan,
            'company' => $read($filters['company'] ?? ''),
            'departement' => $read($filters['departement'] ?? ''),
            'install' => $install,
        ];
    }

    /**
     * @param  array{
     *     site:string,
     *     division_group:string,
     *     jabatan:string,
     *     company:string,
     *     departement:string,
     *     install:string
     * }  $filters
     * @return array<string, mixed>
     */
    private function buildStats(string $dimension, array $filters): array
    {
        $rows = $this->filteredEmployeeRows($filters);

        return $this->aggregateRows($dimension, $rows, $filters);
    }

    /**
     * @param  list<array{
     *     kode_sid:string,
     *     site:string,
     *     resolved_site:string,
     *     divisi:string,
     *     divisi_group:string,
     *     company:string,
     *     departement:string,
     *     jabatan:string,
     *     is_installed:bool
     * }>  $rows
     * @param  array{
     *     site:string,
     *     division_group:string,
     *     jabatan:string,
     *     company:string,
     *     departement:string,
     *     install:string
     * }  $filters
     * @return array<string, mixed>
     */
    private function aggregateRows(string $dimension, array $rows, array $filters): array
    {
        $aggregated = [];

        foreach ($rows as $row) {
            $name = match ($dimension) {
                'site' => $row['resolved_site'] !== '' ? $row['resolved_site'] : 'Tidak diketahui',
                'divisi' => $row['divisi_group'] !== '' ? $row['divisi_group'] : 'Tidak diketahui',
                'company' => $row['company'] !== '' ? $row['company'] : 'Tidak diketahui',
                'departement' => $row['departement'] !== '' ? $row['departement'] : 'Tidak diketahui',
                'jabatan' => $row['jabatan'] !== '' ? $row['jabatan'] : 'Tidak diketahui',
                default => 'Tidak diketahui',
            };

            if (! isset($aggregated[$name])) {
                $aggregated[$name] = [
                    'name' => $name,
                    'total' => 0,
                    'installed' => 0,
                ];
            }

            $aggregated[$name]['total']++;
            if ($row['is_installed']) {
                $aggregated[$name]['installed']++;
            }
        }

        $list = array_values($aggregated);
        usort($list, static function (array $a, array $b): int {
            $cmp = $b['installed'] <=> $a['installed'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a['name'], $b['name']);
        });

        $formattedRows = [];
        $totalAll = 0;
        $installedScoped = 0;

        foreach ($list as $i => $item) {
            $total = (int) $item['total'];
            $installed = (int) $item['installed'];
            $notInstalled = max(0, $total - $installed);
            $pct = $total > 0 ? round($installed / $total * 100, 1) : 0.0;

            $formattedRows[] = [
                'name' => (string) $item['name'],
                'total' => $total,
                'installed' => $installed,
                'not_installed' => $notInstalled,
                'pct' => $pct,
                'bar_class' => self::BAR_CLASSES[$i % count(self::BAR_CLASSES)],
            ];

            $totalAll += $total;
            $installedScoped += $installed;
        }

        $hasFilters = $this->hasActiveFilters($filters);
        $kpiCardTotal = $this->kpiCardTotal();

        // Tanpa filter: Sudah Install mengikuti KPI kartu global.
        // Dengan filter: pakai hitungan cohort terfilter.
        $installedAll = (! $hasFilters && $kpiCardTotal > 0)
            ? $kpiCardTotal
            : $installedScoped;
        $notInstalledAll = max(0, $totalAll - ($hasFilters ? $installedScoped : min($installedAll, $totalAll)));
        if (! $hasFilters) {
            $notInstalledAll = max(0, $totalAll - $installedScoped);
            // Tampilkan installed = KPI, belum tetap dari scoped AKTIF
            $installedAll = $kpiCardTotal > 0 ? $kpiCardTotal : $installedScoped;
        } else {
            $installedAll = $installedScoped;
            $notInstalledAll = max(0, $totalAll - $installedAll);
        }

        $adoptionPct = $totalAll > 0 ? round($installedAll / $totalAll * 100, 1) : 0.0;
        if ($hasFilters) {
            $adoptionPct = $totalAll > 0 ? round($installedScoped / $totalAll * 100, 1) : 0.0;
        } else {
            $adoptionPct = $totalAll > 0 ? round($installedAll / $totalAll * 100, 1) : 0.0;
        }

        return [
            'available' => true,
            'dimension' => $dimension,
            'dimension_label' => self::DIMENSION_LABELS[$dimension] ?? 'Site',
            'footnote' => 'Filter global mempengaruhi seluruh ringkasan. Divisi digabung per grup sejenis. Karyawan status AKTIF (exclude VISITOR).',
            'message' => null,
            'summary' => [
                'total' => $totalAll,
                'installed' => $installedAll,
                'not_installed' => $notInstalledAll,
                'adoption_pct' => $adoptionPct,
                'kpi_card_total' => $kpiCardTotal,
                'groups' => count($formattedRows),
            ],
            'overview' => [],
            'rows' => $formattedRows,
            'chart' => $this->buildChartPayload($formattedRows),
        ];
    }

    /**
     * @param  array{
     *     site:string,
     *     division_group:string,
     *     jabatan:string,
     *     company:string,
     *     departement:string,
     *     install:string
     * }  $filters
     */
    private function hasActiveFilters(array $filters): bool
    {
        return $filters['site'] !== ''
            || $filters['division_group'] !== ''
            || $filters['jabatan'] !== ''
            || $filters['company'] !== ''
            || $filters['departement'] !== ''
            || $filters['install'] !== '';
    }

    /**
     * @param  array{
     *     site:string,
     *     division_group:string,
     *     jabatan:string,
     *     company:string,
     *     departement:string,
     *     install:string
     * }  $filters
     * @return list<array{
     *     kode_sid:string,
     *     site:string,
     *     resolved_site:string,
     *     divisi:string,
     *     divisi_group:string,
     *     company:string,
     *     departement:string,
     *     jabatan:string,
     *     is_installed:bool
     * }>
     */
    private function filteredEmployeeRows(array $filters): array
    {
        $rows = [];

        foreach ($this->rawEmployeeRows() as $row) {
            $resolvedSite = $this->siteResolver->resolve($row['kode_sid'], $row['site']);
            $divisiGroup = $this->divisiGroupResolver->resolve($row['divisi']);

            if ($filters['site'] !== '' && $resolvedSite !== $filters['site']) {
                continue;
            }
            if ($filters['division_group'] !== '' && $divisiGroup !== $filters['division_group']) {
                continue;
            }
            if ($filters['company'] !== '' && $row['company'] !== $filters['company']) {
                continue;
            }
            if ($filters['departement'] !== '' && ! str_contains(mb_strtolower($row['departement']), mb_strtolower($filters['departement']))) {
                continue;
            }
            if ($filters['jabatan'] !== '' && $row['jabatan'] !== $filters['jabatan']) {
                continue;
            }
            if ($filters['install'] === 'sudah' && ! $row['is_installed']) {
                continue;
            }
            if ($filters['install'] === 'belum' && $row['is_installed']) {
                continue;
            }

            $rows[] = [
                'kode_sid' => $row['kode_sid'],
                'site' => $row['site'],
                'resolved_site' => $resolvedSite,
                'divisi' => $row['divisi'],
                'divisi_group' => $divisiGroup,
                'company' => $row['company'],
                'departement' => $row['departement'],
                'jabatan' => $row['jabatan'],
                'is_installed' => $row['is_installed'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{
     *     kode_sid:string,
     *     site:string,
     *     divisi:string,
     *     company:string,
     *     departement:string,
     *     jabatan:string,
     *     is_installed:bool
     * }>
     */
    private function rawEmployeeRows(): array
    {
        /** @var list<array{kode_sid:string,site:string,divisi:string,company:string,departement:string,jabatan:string,is_installed:bool}> */
        return Cache::remember('evaluasi_well:install_stats:raw_employees:v1', self::CACHE_TTL, function (): array {
            $db = DB::connection(BewellConnectionService::CONNECTION);

            $sql = '
                SELECT
                    e.kode_sid,
                    e.site,
                    e.divisi,
                    e.nama_perusahaan,
                    e.departement,
                    e.jabatan_fungsional,
                    CASE WHEN (
                        EXISTS (
                            SELECT 1 FROM login_audit a
                            WHERE a.user_id = e.id AND a.event = ?
                        )
                        OR EXISTS (
                            SELECT 1 FROM food_analyses f
                            WHERE f.user_id = e.id
                        )
                        OR EXISTS (
                            SELECT 1 FROM workout_analyses w
                            WHERE w.user_id = e.id
                        )
                    ) THEN 1 ELSE 0 END AS is_installed
                FROM employee_profiles e
                WHERE e.status_karyawan = ?
                  AND UPPER(TRIM(COALESCE(e.jabatan_fungsional, \'\'))) <> ?
            ';

            $queryRows = $db->select($sql, ['login_success', 'AKTIF', 'VISITOR']);
            $rows = [];

            foreach ($queryRows as $row) {
                $rows[] = [
                    'kode_sid' => trim((string) ($row->kode_sid ?? '')),
                    'site' => trim((string) ($row->site ?? '')),
                    'divisi' => trim((string) ($row->divisi ?? '')),
                    'company' => trim((string) ($row->nama_perusahaan ?? '')),
                    'departement' => trim((string) ($row->departement ?? '')),
                    'jabatan' => trim((string) ($row->jabatan_fungsional ?? '')),
                    'is_installed' => (int) ($row->is_installed ?? 0) === 1,
                ];
            }

            return $rows;
        });
    }

    /**
     * @param  list<array{name: string, total: int, installed: int, not_installed: int, pct: float, bar_class: string}>  $rows
     * @return array{categories: list<string>, installed: list<int>, not_installed: list<int>}
     */
    private function buildChartPayload(array $rows): array
    {
        $categories = [];
        $installed = [];
        $notInstalled = [];

        $top = array_slice($rows, 0, self::CHART_TOP_N);
        $rest = array_slice($rows, self::CHART_TOP_N);

        foreach ($top as $row) {
            $categories[] = $row['name'];
            $installed[] = $row['installed'];
            $notInstalled[] = $row['not_installed'];
        }

        if ($rest !== []) {
            $categories[] = 'Lainnya';
            $installed[] = (int) array_sum(array_column($rest, 'installed'));
            $notInstalled[] = (int) array_sum(array_column($rest, 'not_installed'));
        }

        return [
            'categories' => $categories,
            'installed' => $installed,
            'not_installed' => $notInstalled,
        ];
    }

    private function kpiCardTotal(): int
    {
        try {
            return (int) Cache::remember('evaluasi_well:install_stats:kpi_card_total:v1', self::CACHE_TTL, function (): int {
                $db = DB::connection(BewellConnectionService::CONNECTION);

                $installSignalsSql = '
                    SELECT user_id FROM login_audit
                        WHERE event = ? AND user_id IS NOT NULL
                    UNION ALL
                    SELECT user_id FROM food_analyses
                        WHERE user_id IS NOT NULL
                    UNION ALL
                    SELECT user_id FROM workout_analyses
                        WHERE user_id IS NOT NULL
                ';

                $row = $db->selectOne(
                    'SELECT COUNT(DISTINCT user_id) AS c FROM ('.$installSignalsSql.') AS install_signals',
                    ['login_success']
                );

                return (int) ($row->c ?? 0);
            });
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    private function resolveDimension(string $dimension): string
    {
        $dimension = strtolower(trim($dimension));

        return array_key_exists($dimension, self::DIMENSION_LABELS) ? $dimension : 'site';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function emptyOverview(): array
    {
        $overview = [];

        foreach (self::DIMENSION_LABELS as $dimension => $label) {
            $overview[] = [
                'dimension' => $dimension,
                'label' => $label,
                'icon' => self::DIMENSION_ICONS[$dimension],
                'groups' => 0,
                'total' => 0,
                'installed' => 0,
                'not_installed' => 0,
                'adoption_pct' => 0.0,
                'top_name' => '-',
                'top_installed' => 0,
                'top_pct' => 0.0,
            ];
        }

        return $overview;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(string $dimension, string $message): array
    {
        return [
            'available' => false,
            'dimension' => $dimension,
            'dimension_label' => self::DIMENSION_LABELS[$dimension] ?? 'Site',
            'footnote' => 'Filter global mempengaruhi seluruh ringkasan. Divisi digabung per grup sejenis. Karyawan status AKTIF (exclude VISITOR).',
            'message' => $message,
            'summary' => [
                'total' => 0,
                'installed' => 0,
                'not_installed' => 0,
                'adoption_pct' => 0.0,
                'kpi_card_total' => 0,
                'groups' => 0,
            ],
            'overview' => $this->emptyOverview(),
            'rows' => [],
            'chart' => [
                'categories' => [],
                'installed' => [],
                'not_installed' => [],
            ],
            'filters' => $this->normalizeFilters([]),
            'filter_options' => [
                'sites' => [],
                'division_groups' => $this->divisiGroupResolver->groupLabels(),
                'companies' => [],
                'departements' => [],
                'jabatans' => [],
            ],
        ];
    }
}
