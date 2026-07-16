<?php

declare(strict_types=1);

namespace App\Services\Hsecm;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HsecmDashboardService
{
    /**
     * Mapping dataset UI → sheet JSON + kolom filter/display.
     * Semua data diambil dari resources/views/BaseRule/database.json.
     */
    public const DATASETS = [
        'sap-rfid' => [
            'label' => 'SAP / RFID Pelapor (L1 tanpa SAP)',
            'icon' => 'badge',
            'sheet' => 'L1 tanpa SAP',
            'site_column' => 'site dedicated pelapor all karyawan',
            'company_column' => 'perusahaan pelapor all karyawan',
            'week_column' => 'Week of date',
            'year_column' => 'Year of date',
            'columns' => [
                'date' => 'Tanggal',
                'sid pelapor all karyawan' => 'SID',
                'pelapor all karyawan' => 'Pelapor',
                'perusahaan pelapor all karyawan' => 'Perusahaan',
                'site dedicated pelapor all karyawan' => 'Site',
                'Layer Pelapor' => 'Layer',
                'jabatan struktural pelapor all karyawan' => 'Jabatan Struktural',
                'RFID per SID' => 'RFID / SID',
                'SAP per SID' => 'SAP / SID',
                'Week of date' => 'Week',
                'Year of date' => 'Year',
            ],
        ],
        'coverage-cctv' => [
            'label' => 'Coverage Area Kritis',
            'icon' => 'videocam',
            'sheet' => 'Area Kritis tidak Tercover',
            'site_column' => 'Site',
            'company_column' => null,
            'week_column' => 'Week of Date',
            'year_column' => 'Year of Date',
            'columns' => [
                'Site' => 'Site',
                'Lokasi' => 'Lokasi',
                'Detil Lokasi' => 'Detil Lokasi',
                'Status Coverage dalam 1 Week' => 'Status Coverage',
                '% Tercover' => '% Tercover',
                'Day of Date' => 'Day',
                'Week of Date' => 'Week',
                'Year of Date' => 'Year',
            ],
        ],
        'tbc-blindspot' => [
            'label' => 'Blindspot TBC',
            'icon' => 'visibility_off',
            'sheet' => 'Blindspot TBC',
            'site_column' => 'site',
            'company_column' => 'perusahaan pelapor all karyawan',
            'week_column' => 'Week of Date for Join',
            'year_column' => 'Year of Date for Join',
            'columns' => [
                'site' => 'Site',
                'kategori TBC' => 'Kategori TBC',
                'blindspot TBC' => 'Blindspot TBC',
                'deskripsi' => 'Deskripsi',
                'pelapor all karyawan' => 'Pelapor',
                'perusahaan pelapor all karyawan' => 'Perusahaan Pelapor',
                'pic' => 'PIC',
                'perusahaan pic' => 'Perusahaan PIC',
                'status3' => 'Status',
                'validasi GR' => 'Validasi GR',
                'Week of Date for Join' => 'Week',
                'Year of Date for Join' => 'Year',
            ],
        ],
        'task-overdue' => [
            'label' => 'Task Follow-up Overdue',
            'icon' => 'schedule',
            'sheet' => 'Overdue',
            'site_column' => 'site',
            'company_column' => 'perusahaan pelapor all karyawan',
            'week_column' => 'Week of date time',
            'year_column' => 'Year of date time',
            'columns' => [
                '#Task Number' => 'No Task',
                'site' => 'Site',
                'deskripsi' => 'Deskripsi',
                'pelapor all karyawan' => 'Pelapor',
                'perusahaan pelapor all karyawan' => 'Perusahaan Pelapor',
                'pic' => 'PIC',
                'perusahaan pic' => 'Perusahaan PIC',
                'status3' => 'Status',
                'tanggal_janji' => 'Tanggal Janji',
                'Week of date time' => 'Week',
                'Year of date time' => 'Year',
            ],
        ],
        'task-submitted' => [
            'label' => 'Submitted Over 24 Hours',
            'icon' => 'task_alt',
            'sheet' => 'Submitted over 24 hours',
            'site_column' => 'site',
            'company_column' => 'perusahaan pelapor all karyawan',
            'week_column' => 'Week of date time',
            'year_column' => 'Year of date time',
            'columns' => [
                '#Task Number' => 'No Task',
                'site' => 'Site',
                'deskripsi' => 'Deskripsi',
                'pelapor all karyawan' => 'Pelapor',
                'perusahaan pelapor all karyawan' => 'Perusahaan Pelapor',
                'pic' => 'PIC',
                'perusahaan pic' => 'Perusahaan PIC',
                'status3' => 'Status',
                'Selisih jam dari Submit' => 'Selisih Jam',
                'Week of date time' => 'Week',
                'Year of date time' => 'Year',
            ],
        ],
        'ikk-work-permit' => [
            'label' => 'Compliance IKK',
            'icon' => 'description',
            'sheet' => 'Compliance IKK',
            'site_column' => 'Ra Site Name',
            'company_column' => 'Company Name (Ikk Work Permit)',
            'week_column' => 'Week of Start Date Convert',
            'year_column' => 'ISO Year of Start Date Convert',
            'columns' => [
                'Code' => 'Code',
                'Name (Ikk Work Permit)' => 'Nama IKK',
                'Company Name (Ikk Work Permit)' => 'Perusahaan',
                'Ra Site Name' => 'Site',
                'Location Name' => 'Lokasi',
                'Location Detail Name' => 'Detil Lokasi',
                'Status' => 'Status',
                'Status (Ikk Work Permit Pic)' => 'Status PIC',
                '% Compliance IKK' => '% Compliance',
                'Start Date Convert' => 'Start Date',
                'Week of Start Date Convert' => 'Week',
                'ISO Year of Start Date Convert' => 'Year',
            ],
        ],
        'aggregator' => [
            'label' => 'Tidak Mengisi Aggregator',
            'icon' => 'percent',
            'sheet' => 'Tidak mengisi aggregator',
            'site_column' => 'Site Dedicated',
            'company_column' => 'Nama Perusahaan',
            'week_column' => 'Week of Tanggal Date',
            'year_column' => 'Year of Tanggal Date',
            'columns' => [
                'Kode Sid' => 'SID',
                'Nama' => 'Nama',
                'Nama Perusahaan' => 'Perusahaan',
                'Site Dedicated' => 'Site',
                'Jabatan Struktural' => 'Jabatan Struktural',
                '% Pengisian Aggregator' => '% Pengisian',
                'Day of Tanggal Date' => 'Day',
                'Week of Tanggal Date' => 'Week',
                'Year of Tanggal Date' => 'Year',
            ],
        ],
        'fatigue' => [
            'label' => 'FTW Merah',
            'icon' => 'bedtime',
            'sheet' => 'FTW Merah',
            'site_column' => 'Site Dedicated',
            'company_column' => 'Nama Perusahaan',
            'week_column' => 'Week of Tanggal Date',
            'year_column' => 'Year of Tanggal Date',
            'columns' => [
                'Tanggal Date' => 'Tanggal',
                'Kode Sid' => 'SID',
                'Nama' => 'Nama',
                'Nama Perusahaan' => 'Perusahaan',
                'Site Dedicated' => 'Site',
                'Kondisi Karyawan' => 'Kondisi',
                'FTW Merah' => 'FTW Merah',
                'Jumlah Jam Tidur' => 'Jam Tidur',
                'hasil_sobriety_test' => 'Sobriety',
                'Klasifikasi Tekanan Darah' => 'Tekanan Darah',
                'Week of Tanggal Date' => 'Week',
                'Year of Tanggal Date' => 'Year',
            ],
        ],
        'sumber-rfid' => [
            'label' => 'Pekerja Baru (RFID)',
            'icon' => 'database',
            'sheet' => 'Pekerja Baru',
            'site_column' => 'site_dedicated',
            'company_column' => 'perusahaan pelapor all karyawan',
            'week_column' => 'Week of date',
            'year_column' => 'Year of date',
            'columns' => [
                'date' => 'Tanggal',
                'sid pelapor all karyawan' => 'SID',
                'nama' => 'Nama',
                'perusahaan pelapor all karyawan' => 'Perusahaan',
                'site_dedicated' => 'Site',
                'sumber_data' => 'Sumber Data',
                'tanggal_hari_pertama' => 'Hari Pertama Lapor',
                'Week of date' => 'Week',
                'Year of date' => 'Year',
            ],
        ],
    ];

    public function __construct(
        private readonly HsecmJsonDataRepository $jsonRepository,
    ) {}

    /**
     * @return array{site: string, perusahaan: string, week: string, year: string, q: string}
     */
    public function resolveFilters(Request $request): array
    {
        return [
            'site' => trim((string) $request->query('site', $request->input('site', ''))),
            'perusahaan' => trim((string) $request->query('perusahaan', $request->input('perusahaan', ''))),
            'week' => trim((string) $request->query('week', $request->input('week', ''))),
            'year' => trim((string) $request->query('year', $request->input('year', ''))),
            'q' => trim((string) $request->query('q', $request->input('q', ''))),
        ];
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     * @return array<string, mixed>
     */
    public function buildDashboard(array $filters): array
    {
        return [
            'filters' => $filters,
            'filter_options' => $this->buildFilterOptions(),
            'kpis' => $this->buildKpis($filters),
            'by_site' => $this->buildSiteMonitoring($filters),
            'by_company' => $this->buildCompanyMonitoring($filters),
            'datasets' => collect(self::DATASETS)->map(fn (array $meta, string $key): array => [
                'key' => $key,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'count' => $this->filteredRows($key, $filters)->count(),
            ])->values()->all(),
            'data_source' => 'json',
        ];
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     * @return array{kpis: list<array<string, mixed>>, datasets: list<array<string, mixed>>, filter_options: array<string, mixed>}
     */
    public function buildScopeSummary(array $filters): array
    {
        return [
            'kpis' => $this->buildKpis($filters),
            'datasets' => collect(self::DATASETS)->map(fn (array $meta, string $key): array => [
                'key' => $key,
                'label' => $meta['label'],
                'count' => $this->filteredRows($key, $filters)->count(),
            ])->values()->all(),
            'filter_options' => $this->buildFilterOptions(),
        ];
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     * @return array<string, mixed>
     */
    public function buildDatasetPage(string $datasetKey, array $filters): array
    {
        $meta = self::DATASETS[$datasetKey] ?? null;
        if ($meta === null) {
            abort(404);
        }

        return [
            'filters' => $filters,
            'filter_options' => $this->buildFilterOptions(),
            'dataset' => [
                'key' => $datasetKey,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'columns' => $meta['columns'],
                'has_company_filter' => $meta['company_column'] !== null,
            ],
            'rows' => $this->paginateDataset($datasetKey, $filters),
            'summary' => $this->buildDatasetSummary($datasetKey, $filters),
        ];
    }

    public function datasetExists(string $datasetKey): bool
    {
        return isset(self::DATASETS[$datasetKey]);
    }

    /**
     * @return array{sites: list<string>, companies: list<string>, weeks: list<string>, years: list<string>}
     */
    private function buildFilterOptions(): array
    {
        return Cache::remember('hsecm.filter_options.json.v1', 300, function (): array {
            $sites = collect();
            $companies = collect();
            $weeks = collect();
            $years = collect();

            foreach (self::DATASETS as $key => $meta) {
                $rows = collect($this->jsonRepository->sheet($meta['sheet']));

                $sites = $sites->merge($this->uniqueColumnValues($rows, $meta['site_column']));
                if ($meta['company_column'] !== null) {
                    $companies = $companies->merge($this->uniqueColumnValues($rows, $meta['company_column']));
                }
                $weeks = $weeks->merge($this->uniqueColumnValues($rows, $meta['week_column']));
                $years = $years->merge($this->uniqueColumnValues($rows, $meta['year_column']));
            }

            return [
                'sites' => $sites
                    ->filter(fn ($v) => ! $this->isAllToken($v))
                    ->unique()
                    ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all(),
                'companies' => $companies
                    ->filter(fn ($v) => ! $this->isAllToken($v))
                    ->unique()
                    ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all(),
                'weeks' => $weeks->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
                'years' => $years
                    ->map(fn ($y) => (string) $y)
                    ->filter(fn ($v) => ! $this->isAllToken($v))
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
            ];
        });
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     * @return list<array{label: string, value: string|int|float, icon: string, hint: string, tone: string}>
     */
    private function buildKpis(array $filters): array
    {
        $sapRows = $this->filteredRows('sap-rfid', $filters)->filter(function (array $row): bool {
            return ! $this->isAllToken($row['SAP per SID'] ?? null)
                && ! $this->isAllToken($row['RFID per SID'] ?? null);
        });

        $avgSap = $this->avgNumeric($sapRows, 'SAP per SID');
        $avgRfid = $this->avgNumeric($sapRows, 'RFID per SID');
        $avgCoverage = $this->avgNumeric($this->filteredRows('coverage-cctv', $filters), '% Tercover');
        $tbcCount = $this->filteredRows('tbc-blindspot', $filters)->count();
        $overdueCount = $this->filteredRows('task-overdue', $filters)->count();
        $submittedRows = $this->filteredRows('task-submitted', $filters);
        $submittedCount = $submittedRows->count();
        $avgSubmitHours = $this->avgNumeric($submittedRows, 'Selisih jam dari Submit');
        $avgIkk = $this->avgNumeric($this->filteredRows('ikk-work-permit', $filters), '% Compliance IKK');
        $avgAggregator = $this->avgNumeric($this->filteredRows('aggregator', $filters), '% Pengisian Aggregator');
        $fatigueRows = $this->filteredRows('fatigue', $filters);
        $fatigueCount = $fatigueRows->count();
        $ftwMerah = $fatigueRows->filter(function (array $row): bool {
            $val = trim((string) ($row['FTW Merah'] ?? ''));
            if ($val === '') {
                return false;
            }

            return ! in_array(Str::lower($val), ['tidak', 'no', '0', 'false'], true);
        })->count();
        $sumberCount = $this->filteredRows('sumber-rfid', $filters)->count();

        return [
            [
                'label' => 'Avg SAP / SID',
                'value' => round($avgSap, 1),
                'icon' => 'analytics',
                'hint' => 'L1 tanpa SAP',
                'tone' => 'primary',
            ],
            [
                'label' => 'Avg RFID / SID',
                'value' => round($avgRfid, 1),
                'icon' => 'contactless',
                'hint' => 'L1 tanpa SAP',
                'tone' => 'primary',
            ],
            [
                'label' => 'CCTV Coverage',
                'value' => round($avgCoverage, 1).'%',
                'icon' => 'videocam',
                'hint' => 'Area kritis',
                'tone' => $avgCoverage >= 80 ? 'success' : 'warning',
            ],
            [
                'label' => 'TBC Blindspot',
                'value' => $tbcCount,
                'icon' => 'visibility_off',
                'hint' => 'Kasus blindspot',
                'tone' => $tbcCount > 0 ? 'danger' : 'success',
            ],
            [
                'label' => 'Task Overdue',
                'value' => $overdueCount,
                'icon' => 'schedule',
                'hint' => 'Follow-up terlambat',
                'tone' => $overdueCount > 0 ? 'danger' : 'success',
            ],
            [
                'label' => 'Submitted >24jam',
                'value' => $submittedCount,
                'icon' => 'task_alt',
                'hint' => 'Avg '.$this->formatNumber($avgSubmitHours).' jam',
                'tone' => 'primary',
            ],
            [
                'label' => 'IKK Compliance',
                'value' => round($avgIkk, 1).'%',
                'icon' => 'description',
                'hint' => 'Work permit',
                'tone' => $avgIkk >= 80 ? 'success' : 'warning',
            ],
            [
                'label' => 'Aggregator Fill',
                'value' => round($avgAggregator, 1).'%',
                'icon' => 'percent',
                'hint' => 'Pengisian aggregator',
                'tone' => $avgAggregator >= 80 ? 'success' : 'warning',
            ],
            [
                'label' => 'FTW Merah',
                'value' => $fatigueCount,
                'icon' => 'bedtime',
                'hint' => 'Flag merah: '.$ftwMerah,
                'tone' => $ftwMerah > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Pekerja Baru',
                'value' => $sumberCount,
                'icon' => 'database',
                'hint' => 'RFID pekerja baru',
                'tone' => 'primary',
            ],
        ];
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function buildSiteMonitoring(array $filters): array
    {
        $map = [
            'sap-rfid' => 'sap_rfid',
            'coverage-cctv' => 'coverage_cctv',
            'tbc-blindspot' => 'tbc_blindspot',
            'task-overdue' => 'task_overdue',
            'task-submitted' => 'task_submitted',
            'ikk-work-permit' => 'ikk',
            'aggregator' => 'aggregator',
            'fatigue' => 'fatigue',
            'sumber-rfid' => 'sumber_rfid',
        ];

        /** @var Collection<string, array<string, int|float|string>> $rows */
        $rows = collect();

        foreach ($map as $key => $field) {
            $meta = self::DATASETS[$key];
            $siteCol = $meta['site_column'];
            $grouped = $this->filteredRows($key, array_merge($filters, ['site' => '']))
                ->filter(fn (array $row): bool => trim((string) ($row[$siteCol] ?? '')) !== '' && ! $this->isAllToken($row[$siteCol] ?? null))
                ->groupBy(fn (array $row): string => (string) $row[$siteCol]);

            foreach ($grouped as $site => $items) {
                $current = $rows->get($site, [
                    'site' => $site,
                    'total_records' => 0,
                    'sap_rfid' => 0,
                    'coverage_cctv' => 0,
                    'tbc_blindspot' => 0,
                    'task_overdue' => 0,
                    'task_submitted' => 0,
                    'ikk' => 0,
                    'aggregator' => 0,
                    'fatigue' => 0,
                    'sumber_rfid' => 0,
                    'avg_ikk' => 0,
                    'avg_coverage' => 0,
                    'avg_aggregator' => 0,
                ]);

                $count = $items->count();
                $current[$field] = $count;
                $current['total_records'] = (int) $current['total_records'] + $count;

                if ($key === 'coverage-cctv') {
                    $current['avg_coverage'] = round($this->avgNumeric($items, '% Tercover'), 1);
                } elseif ($key === 'ikk-work-permit') {
                    $current['avg_ikk'] = round($this->avgNumeric($items, '% Compliance IKK'), 1);
                } elseif ($key === 'aggregator') {
                    $current['avg_aggregator'] = round($this->avgNumeric($items, '% Pengisian Aggregator'), 1);
                }

                $rows->put($site, $current);
            }
        }

        return $rows->sortByDesc('total_records')->values()->all();
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function buildCompanyMonitoring(array $filters): array
    {
        $map = [
            'sap-rfid' => 'sap_rfid',
            'tbc-blindspot' => 'tbc_blindspot',
            'task-overdue' => 'task_overdue',
            'task-submitted' => 'task_submitted',
            'ikk-work-permit' => 'ikk',
            'aggregator' => 'aggregator',
            'fatigue' => 'fatigue',
            'sumber-rfid' => 'sumber_rfid',
        ];

        /** @var Collection<string, array<string, int|float|string>> $rows */
        $rows = collect();

        foreach ($map as $key => $field) {
            $meta = self::DATASETS[$key];
            $companyCol = $meta['company_column'];
            if ($companyCol === null) {
                continue;
            }

            $grouped = $this->filteredRows($key, array_merge($filters, ['perusahaan' => '']))
                ->filter(fn (array $row): bool => trim((string) ($row[$companyCol] ?? '')) !== '' && ! $this->isAllToken($row[$companyCol] ?? null))
                ->groupBy(fn (array $row): string => (string) $row[$companyCol]);

            foreach ($grouped as $company => $items) {
                $current = $rows->get($company, [
                    'perusahaan' => $company,
                    'total_records' => 0,
                    'sap_rfid' => 0,
                    'tbc_blindspot' => 0,
                    'task_overdue' => 0,
                    'task_submitted' => 0,
                    'ikk' => 0,
                    'aggregator' => 0,
                    'fatigue' => 0,
                    'sumber_rfid' => 0,
                    'avg_ikk' => 0,
                    'avg_aggregator' => 0,
                ]);

                $count = $items->count();
                $current[$field] = $count;
                $current['total_records'] = (int) $current['total_records'] + $count;

                if ($key === 'ikk-work-permit') {
                    $current['avg_ikk'] = round($this->avgNumeric($items, '% Compliance IKK'), 1);
                } elseif ($key === 'aggregator') {
                    $current['avg_aggregator'] = round($this->avgNumeric($items, '% Pengisian Aggregator'), 1);
                }

                $rows->put($company, $current);
            }
        }

        return $rows->sortByDesc('total_records')->values()->all();
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     * @return array<string, mixed>
     */
    private function buildDatasetSummary(string $datasetKey, array $filters): array
    {
        $rows = $this->filteredRows($datasetKey, $filters);
        $extra = match ($datasetKey) {
            'sap-rfid' => [
                'avg_sap' => round($this->avgNumeric($rows->filter(fn ($r) => ! $this->isAllToken($r['SAP per SID'] ?? null)), 'SAP per SID'), 1),
                'avg_rfid' => round($this->avgNumeric($rows->filter(fn ($r) => ! $this->isAllToken($r['RFID per SID'] ?? null)), 'RFID per SID'), 1),
            ],
            'coverage-cctv' => [
                'avg_pct' => round($this->avgNumeric($rows, '% Tercover'), 1),
            ],
            'task-submitted' => [
                'avg_hours' => round($this->avgNumeric($rows, 'Selisih jam dari Submit'), 1),
            ],
            'ikk-work-permit' => [
                'avg_compliance' => round($this->avgNumeric($rows, '% Compliance IKK'), 1),
            ],
            'aggregator' => [
                'avg_fill' => round($this->avgNumeric($rows, '% Pengisian Aggregator'), 1),
            ],
            default => [],
        };

        return array_merge(['total' => $rows->count()], $extra);
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     */
    private function paginateDataset(string $datasetKey, array $filters): LengthAwarePaginator
    {
        $meta = self::DATASETS[$datasetKey];
        $columns = array_keys($meta['columns']);
        $page = max(1, (int) request()->query('page', 1));
        $perPage = 25;

        $filtered = $this->filteredRows($datasetKey, $filters)
            ->sortByDesc('_row_id')
            ->values();

        $total = $filtered->count();
        $slice = $filtered->forPage($page, $perPage)->map(function (array $row) use ($columns): object {
            $item = ['id' => $row['_row_id'] ?? null];
            foreach ($columns as $column) {
                $item[$column] = $row[$column] ?? null;
            }

            return (object) $item;
        })->values();

        return new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filteredRows(string $datasetKey, array $filters): Collection
    {
        $meta = self::DATASETS[$datasetKey];
        $rows = collect($this->jsonRepository->sheet($meta['sheet']));

        return $rows->filter(function (array $row) use ($meta, $filters): bool {
            if ($filters['site'] !== '') {
                if (! $this->valueEquals($row[$meta['site_column']] ?? null, $filters['site'])) {
                    return false;
                }
            }

            if ($filters['perusahaan'] !== '' && $meta['company_column'] !== null) {
                if (! $this->valueEquals($row[$meta['company_column']] ?? null, $filters['perusahaan'])) {
                    return false;
                }
            }

            if ($filters['week'] !== '') {
                if (! $this->valueEquals($row[$meta['week_column']] ?? null, $filters['week'])) {
                    return false;
                }
            }

            if ($filters['year'] !== '') {
                if (! $this->valueEquals($row[$meta['year_column']] ?? null, $filters['year'])) {
                    return false;
                }
            }

            if ($filters['q'] !== '') {
                $q = Str::lower($filters['q']);
                $matched = false;
                foreach (array_keys($meta['columns']) as $column) {
                    $hay = Str::lower((string) ($row[$column] ?? ''));
                    if ($hay !== '' && str_contains($hay, $q)) {
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, string>
     */
    private function uniqueColumnValues(Collection $rows, string $column): Collection
    {
        return $rows
            ->map(fn (array $row) => $row[$column] ?? null)
            ->filter(fn ($v) => $v !== null && trim((string) $v) !== '')
            ->map(fn ($v) => (string) $v)
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function avgNumeric(Collection $rows, string $column): float
    {
        $values = $rows
            ->map(fn (array $row) => $this->toFloat($row[$column] ?? null))
            ->filter(fn (?float $v): bool => $v !== null)
            ->values();

        if ($values->isEmpty()) {
            return 0.0;
        }

        return (float) $values->avg();
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $this->isAllToken($value)) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace(['%', ','], ['', '.'], $raw);
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function valueEquals(mixed $actual, string $expected): bool
    {
        return strcasecmp(trim((string) $actual), trim($expected)) === 0;
    }

    private function isAllToken(mixed $value): bool
    {
        return strcasecmp(trim((string) $value), 'All') === 0;
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, 1);
    }
}
