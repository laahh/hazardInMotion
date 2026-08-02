<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Agregasi statistik install aplikasi BeWell per dimensi organisasi (read-only).
 */
final class SportEvaluationInstallStatsService
{
    private const CACHE_TTL = 300;

    private const CHART_TOP_N = 15;

    /** @var array<string, string> */
    private const DIMENSION_COLUMNS = [
        'site' => 'e.site',
        'company' => 'e.nama_perusahaan',
        'departement' => 'e.departement',
        'jabatan' => 'e.jabatan_fungsional',
    ];

    /** @var array<string, string> */
    private const DIMENSION_LABELS = [
        'site' => 'Site',
        'company' => 'Perusahaan',
        'departement' => 'Departemen',
        'jabatan' => 'Jabatan',
    ];

    /** @var array<string, string> */
    private const DIMENSION_ICONS = [
        'site' => 'solar:map-point-bold',
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
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     dimension: string,
     *     dimension_label: string,
     *     footnote: string,
     *     message: string|null,
     *     summary: array{
     *         total: int,
     *         installed: int,
     *         not_installed: int,
     *         adoption_pct: float,
     *         kpi_card_total: int,
     *         groups: int
     *     },
     *     overview: list<array{
     *         dimension: string,
     *         label: string,
     *         icon: string,
     *         groups: int,
     *         total: int,
     *         installed: int,
     *         not_installed: int,
     *         adoption_pct: float,
     *         top_name: string,
     *         top_installed: int,
     *         top_pct: float
     *     }>,
     *     rows: list<array{name: string, total: int, installed: int, not_installed: int, pct: float, bar_class: string}>,
     *     chart: array{categories: list<string>, installed: list<int>, not_installed: list<int>}
     * }
     */
    public function getStats(string $dimension): array
    {
        $dimension = $this->resolveDimension($dimension);
        $empty = $this->emptyPayload($dimension, 'Koneksi BeWell belum tersedia.');

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $stats = Cache::remember(
                'evaluasi_well:install_stats:v3:'.$dimension,
                self::CACHE_TTL,
                function () use ($dimension): array {
                    return $this->buildStats($dimension);
                }
            );

            $stats['overview'] = $this->getOverview();

            return $stats;
        } catch (Throwable $e) {
            report($e);

            return $this->emptyPayload($dimension, 'Gagal memuat statistik install.');
        }
    }

    /**
     * Ringkasan total untuk semua dimensi (Site, Perusahaan, Departemen, Jabatan).
     *
     * @return list<array{
     *     dimension: string,
     *     label: string,
     *     icon: string,
     *     groups: int,
     *     total: int,
     *     installed: int,
     *     not_installed: int,
     *     adoption_pct: float,
     *     top_name: string,
     *     top_installed: int,
     *     top_pct: float
     * }>
     */
    public function getOverview(): array
    {
        if (! $this->connection->isUp()) {
            return $this->emptyOverview();
        }

        try {
            return Cache::remember(
                'evaluasi_well:install_stats:overview:v3',
                self::CACHE_TTL,
                function (): array {
                    $overview = [];

                    foreach (array_keys(self::DIMENSION_COLUMNS) as $dimension) {
                        $stats = Cache::remember(
                            'evaluasi_well:install_stats:v3:'.$dimension,
                            self::CACHE_TTL,
                            function () use ($dimension): array {
                                return $this->buildStats($dimension);
                            }
                        );

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
                }
            );
        } catch (Throwable $e) {
            report($e);

            return $this->emptyOverview();
        }
    }

    /**
     * @return array{
     *     available: bool,
     *     dimension: string,
     *     dimension_label: string,
     *     footnote: string,
     *     message: string|null,
     *     summary: array{
     *         total: int,
     *         installed: int,
     *         not_installed: int,
     *         adoption_pct: float,
     *         kpi_card_total: int,
     *         groups: int
     *     },
     *     overview: list<array{
     *         dimension: string,
     *         label: string,
     *         icon: string,
     *         groups: int,
     *         total: int,
     *         installed: int,
     *         not_installed: int,
     *         adoption_pct: float,
     *         top_name: string,
     *         top_installed: int,
     *         top_pct: float
     *     }>,
     *     rows: list<array{name: string, total: int, installed: int, not_installed: int, pct: float, bar_class: string}>,
     *     chart: array{categories: list<string>, installed: list<int>, not_installed: list<int>}
     * }
     */
    private function buildStats(string $dimension): array
    {
        if ($dimension === 'site') {
            return $this->buildStatsByResolvedSite();
        }

        $column = self::DIMENSION_COLUMNS[$dimension];
        $db = DB::connection(BewellConnectionService::CONNECTION);

        $dimExpr = 'COALESCE(NULLIF(TRIM('.$column.'), \'\'), \'Tidak diketahui\')';

        $sql = '
            SELECT
                '.$dimExpr.' AS dim_name,
                COUNT(*) AS total,
                SUM(
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
                    ) THEN 1 ELSE 0 END
                ) AS installed
            FROM employee_profiles e
            WHERE e.status_karyawan = ?
              AND UPPER(TRIM(COALESCE(e.jabatan_fungsional, \'\'))) <> ?
            GROUP BY '.$dimExpr.'
            ORDER BY installed DESC, dim_name ASC
        ';

        $queryRows = $db->select($sql, ['login_success', 'AKTIF', 'VISITOR']);

        return $this->formatInstallStatRows($dimension, $queryRows);
    }

    /**
     * Agregasi install per site memakai site_dedicated (karyawan_well) + fallback e.site.
     *
     * @return array{
     *     available: bool,
     *     dimension: string,
     *     dimension_label: string,
     *     footnote: string,
     *     message: string|null,
     *     summary: array{
     *         total: int,
     *         installed: int,
     *         not_installed: int,
     *         adoption_pct: float,
     *         kpi_card_total: int,
     *         groups: int
     *     },
     *     overview: list<array{
     *         dimension: string,
     *         label: string,
     *         icon: string,
     *         groups: int,
     *         total: int,
     *         installed: int,
     *         not_installed: int,
     *         adoption_pct: float,
     *         top_name: string,
     *         top_installed: int,
     *         top_pct: float
     *     }>,
     *     rows: list<array{name: string, total: int, installed: int, not_installed: int, pct: float, bar_class: string}>,
     *     chart: array{categories: list<string>, installed: list<int>, not_installed: list<int>}
     * }
     */
    private function buildStatsByResolvedSite(): array
    {
        $db = DB::connection(BewellConnectionService::CONNECTION);

        $sql = '
            SELECT
                e.kode_sid,
                e.site,
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
        $aggregated = [];

        foreach ($queryRows as $row) {
            $siteName = $this->siteResolver->resolve(
                isset($row->kode_sid) ? (string) $row->kode_sid : null,
                isset($row->site) ? (string) $row->site : null,
            );
            if ($siteName === '') {
                $siteName = 'Tidak diketahui';
            }

            if (! isset($aggregated[$siteName])) {
                $aggregated[$siteName] = (object) [
                    'dim_name' => $siteName,
                    'total' => 0,
                    'installed' => 0,
                ];
            }

            $aggregated[$siteName]->total++;
            if ((int) ($row->is_installed ?? 0) === 1) {
                $aggregated[$siteName]->installed++;
            }
        }

        usort($aggregated, static function (object $a, object $b): int {
            $installedCmp = ((int) $b->installed) <=> ((int) $a->installed);
            if ($installedCmp !== 0) {
                return $installedCmp;
            }

            return strcmp((string) $a->dim_name, (string) $b->dim_name);
        });

        return $this->formatInstallStatRows('site', array_values($aggregated));
    }

    /**
     * @param  list<object>  $queryRows
     * @return array{
     *     available: bool,
     *     dimension: string,
     *     dimension_label: string,
     *     footnote: string,
     *     message: string|null,
     *     summary: array{
     *         total: int,
     *         installed: int,
     *         not_installed: int,
     *         adoption_pct: float,
     *         kpi_card_total: int,
     *         groups: int
     *     },
     *     overview: list<array{
     *         dimension: string,
     *         label: string,
     *         icon: string,
     *         groups: int,
     *         total: int,
     *         installed: int,
     *         not_installed: int,
     *         adoption_pct: float,
     *         top_name: string,
     *         top_installed: int,
     *         top_pct: float
     *     }>,
     *     rows: list<array{name: string, total: int, installed: int, not_installed: int, pct: float, bar_class: string}>,
     *     chart: array{categories: list<string>, installed: list<int>, not_installed: list<int>}
     * }
     */
    private function formatInstallStatRows(string $dimension, array $queryRows): array
    {
        $rows = [];
        $totalAll = 0;
        $installedAll = 0;

        foreach ($queryRows as $i => $row) {
            $total = (int) ($row->total ?? 0);
            $installed = (int) ($row->installed ?? 0);
            $notInstalled = max(0, $total - $installed);
            $pct = $total > 0 ? round($installed / $total * 100, 1) : 0.0;

            $rows[] = [
                'name' => (string) ($row->dim_name ?? 'Tidak diketahui'),
                'total' => $total,
                'installed' => $installed,
                'not_installed' => $notInstalled,
                'pct' => $pct,
                'bar_class' => self::BAR_CLASSES[$i % count(self::BAR_CLASSES)],
            ];

            $totalAll += $total;
            $installedAll += $installed;
        }

        $notInstalledAll = max(0, $totalAll - $installedAll);
        $adoptionPct = $totalAll > 0 ? round($installedAll / $totalAll * 100, 1) : 0.0;

        return [
            'available' => true,
            'dimension' => $dimension,
            'dimension_label' => self::DIMENSION_LABELS[$dimension],
            'footnote' => 'Berdasarkan karyawan status AKTIF (exclude VISITOR). Angka dapat berbeda dari total di kartu KPI.',
            'message' => null,
            'summary' => [
                'total' => $totalAll,
                'installed' => $installedAll,
                'not_installed' => $notInstalledAll,
                'adoption_pct' => $adoptionPct,
                'kpi_card_total' => $this->kpiCardTotal(),
                'groups' => count($rows),
            ],
            'overview' => [],
            'rows' => $rows,
            'chart' => $this->buildChartPayload($rows),
        ];
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

        return array_key_exists($dimension, self::DIMENSION_COLUMNS) ? $dimension : 'site';
    }

    /**
     * @return list<array{
     *     dimension: string,
     *     label: string,
     *     icon: string,
     *     groups: int,
     *     total: int,
     *     installed: int,
     *     not_installed: int,
     *     adoption_pct: float,
     *     top_name: string,
     *     top_installed: int,
     *     top_pct: float
     * }>
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
     * @return array{
     *     available: bool,
     *     dimension: string,
     *     dimension_label: string,
     *     footnote: string,
     *     message: string|null,
     *     summary: array{
     *         total: int,
     *         installed: int,
     *         not_installed: int,
     *         adoption_pct: float,
     *         kpi_card_total: int,
     *         groups: int
     *     },
     *     overview: list<array{
     *         dimension: string,
     *         label: string,
     *         icon: string,
     *         groups: int,
     *         total: int,
     *         installed: int,
     *         not_installed: int,
     *         adoption_pct: float,
     *         top_name: string,
     *         top_installed: int,
     *         top_pct: float
     *     }>,
     *     rows: list<array{name: string, total: int, installed: int, not_installed: int, pct: float, bar_class: string}>,
     *     chart: array{categories: list<string>, installed: list<int>, not_installed: list<int>}
     * }
     */
    private function emptyPayload(string $dimension, string $message): array
    {
        return [
            'available' => false,
            'dimension' => $dimension,
            'dimension_label' => self::DIMENSION_LABELS[$dimension] ?? 'Site',
            'footnote' => 'Berdasarkan karyawan status AKTIF (exclude VISITOR). Angka dapat berbeda dari total di kartu KPI.',
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
        ];
    }
}
