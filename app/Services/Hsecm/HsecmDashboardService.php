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
     * Mapping dataset UI → tabel DB (scr_hsecm_*) + kolom filter/display.
     * Semua data diambil dari tabel hasil scraping Tableau via HsecmDatabaseRepository.
     */
    public const DATASETS = [
        'sap-rfid' => [
            'label' => 'SAP / RFID Pelapor (L1 tanpa SAP)',
            'icon' => 'badge',
            'table' => 'scr_hsecm_partisipasi_sap_l1_rfid',
            'site_column' => 'site_dedicated_pelapor_all_karyawan',
            'company_column' => 'perusahaan_pelapor_all_karyawan',
            'week_column' => 'Week_of_date',
            'year_column' => 'Year_of_date',
            'columns' => [
                'date' => 'Tanggal',
                'sid_pelapor_all_karyawan' => 'SID',
                'pelapor_all_karyawan' => 'Pelapor',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan',
                'site_dedicated_pelapor_all_karyawan' => 'Site',
                'Layer_Pelapor' => 'Layer',
                'jabatan_struktural_pelapor_all_karyawan' => 'Jabatan Struktural',
                'RFID_per_SID' => 'RFID / SID',
                'SAP_per_SID' => 'SAP / SID',
                'Week_of_date' => 'Week',
                'Year_of_date' => 'Year',
            ],
        ],
        'coverage-cctv' => [
            'label' => 'Coverage Area Kritis',
            'icon' => 'videocam',
            'table' => 'scr_hsecm_coverage_area_kritis_daily',
            'site_column' => 'Site',
            'company_column' => null,
            'week_column' => 'Week_of_Date',
            'year_column' => 'Year_of_Date',
            'columns' => [
                'Site' => 'Site',
                'Lokasi' => 'Lokasi',
                'Detil_Lokasi' => 'Detil Lokasi',
                'Status_Coverage_dalam_1_Week' => 'Status Coverage',
                'Tercover' => '% Tercover',
                'Day_of_Date' => 'Day',
                'Week_of_Date' => 'Week',
                'Year_of_Date' => 'Year',
            ],
        ],
        'tbc-blindspot' => [
            'label' => 'Blindspot TBC',
            'icon' => 'visibility_off',
            'table' => 'scr_hsecm_blindspot_tbc_gr',
            'site_column' => 'site',
            'company_column' => 'perusahaan_pelapor_all_karyawan',
            'week_column' => 'Week_of_Date_for_Join',
            'year_column' => 'Year_of_Date_for_Join',
            'columns' => [
                'site' => 'Site',
                'kategori_TBC' => 'Kategori TBC',
                'blindspot_TBC' => 'Blindspot TBC',
                'deskripsi' => 'Deskripsi',
                'pelapor_all_karyawan' => 'Pelapor',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan Pelapor',
                'pic' => 'PIC',
                'perusahaan_pic' => 'Perusahaan PIC',
                'status3' => 'Status',
                'validasi_GR' => 'Validasi GR',
                'Week_of_Date_for_Join' => 'Week',
                'Year_of_Date_for_Join' => 'Year',
            ],
        ],
        'task-overdue' => [
            'label' => 'Task Follow-up Overdue',
            'icon' => 'schedule',
            'table' => 'scr_hsecm_overdue_hazard',
            'site_column' => 'site',
            'company_column' => 'perusahaan_pelapor_all_karyawan',
            'week_column' => 'Week_of_date_time',
            'year_column' => 'Year_of_date_time',
            'columns' => [
                'Task_Number' => 'No Task',
                'site' => 'Site',
                'deskripsi' => 'Deskripsi',
                'pelapor_all_karyawan' => 'Pelapor',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan Pelapor',
                'pic' => 'PIC',
                'perusahaan_pic' => 'Perusahaan PIC',
                'status3' => 'Status',
                'tanggal_janji' => 'Tanggal Janji',
                'Week_of_date_time' => 'Week',
                'Year_of_date_time' => 'Year',
            ],
        ],
        'task-submitted' => [
            'label' => 'Submitted Over 24 Hours',
            'icon' => 'task_alt',
            'table' => 'scr_hsecm_submitted_hazard_24jam',
            'site_column' => 'site',
            'company_column' => 'perusahaan_pelapor_all_karyawan',
            'week_column' => 'Week_of_date_time',
            'year_column' => 'Year_of_date_time',
            'columns' => [
                'Task_Number' => 'No Task',
                'site' => 'Site',
                'deskripsi' => 'Deskripsi',
                'pelapor_all_karyawan' => 'Pelapor',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan Pelapor',
                'pic' => 'PIC',
                'perusahaan_pic' => 'Perusahaan PIC',
                'status3' => 'Status',
                'Selisih_jam_dari_Submit' => 'Selisih Jam',
                'Week_of_date_time' => 'Week',
                'Year_of_date_time' => 'Year',
            ],
        ],
        'ikk-work-permit' => [
            'label' => 'Compliance IKK',
            'icon' => 'description',
            'table' => 'scr_hsecm_ikk_aktif_ipk_okk',
            'site_column' => 'Ra_Site_Name',
            'company_column' => 'Company_Name_Ikk_Work_Permit',
            'week_column' => 'Week_of_Start_Date_Convert',
            'year_column' => 'ISO_Year_of_Start_Date_Convert',
            'columns' => [
                'Code' => 'Code',
                'Name_Ikk_Work_Permit' => 'Nama IKK',
                'Company_Name_Ikk_Work_Permit' => 'Perusahaan',
                'Ra_Site_Name' => 'Site',
                'Location_Name' => 'Lokasi',
                'Location_Detail_Name' => 'Detil Lokasi',
                'Status' => 'Status',
                'Status_Ikk_Work_Permit_Pic' => 'Status PIC',
                'Compliance_IKK' => '% Compliance',
                'Start_Date_Convert' => 'Start Date',
                'Week_of_Start_Date_Convert' => 'Week',
                'ISO_Year_of_Start_Date_Convert' => 'Year',
            ],
        ],
        'implementasi-ikk' => [
            'label' => 'Implementasi IKK',
            'icon' => 'assignment_turned_in',
            'table' => 'scr_hsecm_implementasi_ikk',
            'site_column' => 'Ra_Site_Name',
            'company_column' => 'Company_Name_Ikk_Work_Permit',
            'week_column' => 'Week_of_Start_Date_Convert',
            'year_column' => 'ISO_Year_of_Start_Date_Convert',
            'columns' => [
                'Code' => 'Code',
                'Name_Ikk_Work_Permit' => 'Nama IKK',
                'Company_Name_Ikk_Work_Permit' => 'Perusahaan',
                'Ra_Site_Name' => 'Site',
                'Location_Name' => 'Lokasi',
                'Location_Detail_Name' => 'Detil Lokasi',
                'Status' => 'Status',
                'Status_Ikk_Work_Permit_Pic' => 'Status PIC',
                'Compliance_IKK' => '% Compliance',
                'Start_Date_Convert' => 'Start Date',
                'Week_of_Start_Date_Convert' => 'Week',
                'ISO_Year_of_Start_Date_Convert' => 'Year',
            ],
        ],
        'aggregator' => [
            'label' => 'Tidak Mengisi Aggregator',
            'icon' => 'percent',
            'table' => 'scr_hsecm_pengisian_ftw',
            'site_column' => 'Site_Dedicated',
            'company_column' => 'Nama_Perusahaan',
            'week_column' => 'Week_of_Tanggal_Date',
            'year_column' => 'Year_of_Tanggal_Date',
            'columns' => [
                'Kode_Sid' => 'SID',
                'Nama' => 'Nama',
                'Nama_Perusahaan' => 'Perusahaan',
                'Site_Dedicated' => 'Site',
                'Jabatan_Struktural' => 'Jabatan Struktural',
                'Pengisian_Aggregator' => '% Pengisian',
                'Day_of_Tanggal_Date' => 'Day',
                'Week_of_Tanggal_Date' => 'Week',
                'Year_of_Tanggal_Date' => 'Year',
            ],
        ],
        'fatigue' => [
            'label' => 'FTW Merah',
            'icon' => 'bedtime',
            'table' => 'scr_hsecm_ftw_merah',
            'site_column' => 'Site_Dedicated',
            'company_column' => 'Nama_Perusahaan',
            'week_column' => 'Week_of_Tanggal_Date',
            'year_column' => 'Year_of_Tanggal_Date',
            'columns' => [
                'Tanggal_Date' => 'Tanggal',
                'Kode_Sid' => 'SID',
                'Nama' => 'Nama',
                'Nama_Perusahaan' => 'Perusahaan',
                'Site_Dedicated' => 'Site',
                'Kondisi_Karyawan' => 'Kondisi',
                'Penyakit_Terkonfirmasi' => 'Penyakit Terkonfirmasi',
                'Jumlah_Jam_Tidur' => 'Jam Tidur',
                'hasil_sobriety_test' => 'Sobriety',
                'Klasifikasi_Tekanan_Darah' => 'Tekanan Darah',
                'Week_of_Tanggal_Date' => 'Week',
                'Year_of_Tanggal_Date' => 'Year',
            ],
        ],
        'sumber-rfid' => [
            'label' => 'Pekerja Baru (RFID)',
            'icon' => 'database',
            'table' => 'scr_hsecm_pekerja_baru',
            'site_column' => 'site_dedicated',
            'company_column' => 'perusahaan_pelapor_all_karyawan',
            'week_column' => 'Week_of_date',
            'year_column' => 'Year_of_date',
            'columns' => [
                'date' => 'Tanggal',
                'sid_pelapor_all_karyawan' => 'SID',
                'nama' => 'Nama',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan',
                'site_dedicated' => 'Site',
                'sumber_data' => 'Sumber Data',
                'tanggal_hari_pertama' => 'Hari Pertama Lapor',
                'Week_of_date' => 'Week',
                'Year_of_date' => 'Year',
            ],
        ],
    ];

    public function __construct(
        private readonly HsecmDatabaseRepository $repository,
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
            'data_source' => 'database',
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
        return Cache::remember('hsecm.filter_options.db.v1', 300, function (): array {
            $sites = collect();
            $companies = collect();
            $weeks = collect();
            $years = collect();

            foreach (self::DATASETS as $key => $meta) {
                $rows = collect($this->repository->rows($meta['table']));

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
            return ! $this->isAllToken($row['SAP_per_SID'] ?? null)
                && ! $this->isAllToken($row['RFID_per_SID'] ?? null);
        });

        $avgSap = $this->avgNumeric($sapRows, 'SAP_per_SID');
        $avgCoverage = $this->avgPercent($this->filteredRows('coverage-cctv', $filters), 'Tercover');
        $tbcCount = $this->filteredRows('tbc-blindspot', $filters)->count();
        $overdueCount = $this->filteredRows('task-overdue', $filters)->count();
        $submittedRows = $this->filteredRows('task-submitted', $filters);
        $submittedCount = $submittedRows->count();
        $avgSubmitHours = $this->avgNumeric($submittedRows, 'Selisih_jam_dari_Submit');
        $ikkRows = $this->filteredRows('ikk-work-permit', $filters);
        $ikkCount = $ikkRows->count();
        $avgIkk = $this->avgPercent($ikkRows, 'Compliance_IKK');
        $avgAggregator = $this->avgPercent($this->filteredRows('aggregator', $filters), 'Pengisian_Aggregator');
        $fatigueRows = $this->filteredRows('fatigue', $filters);
        $fatigueCount = $fatigueRows->count();
        // Seluruh baris pada tabel scr_hsecm_ftw_merah sudah berstatus FTW merah.
        $ftwMerah = $fatigueCount;
        $sumberCount = $this->filteredRows('sumber-rfid', $filters)->count();

        return [
            [
                'label' => 'Layer 1 tanpa SAP',
                'value' => round($avgSap, 1),
                'icon' => 'analytics',
                'hint' => 'L1 tanpa SAP',
                'tone' => 'primary',
            ],
            [
                'label' => 'Coverage Area Kritis',
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
                'label' => 'Jumlah IKK',
                'value' => $ikkCount,
                'icon' => 'fact_check',
                'hint' => 'IKK aktif (baris)',
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
                'label' => 'Jumlah pekerja baru',
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
                    $current['avg_coverage'] = round($this->avgPercent($items, 'Tercover'), 1);
                } elseif ($key === 'ikk-work-permit') {
                    $current['avg_ikk'] = round($this->avgPercent($items, 'Compliance_IKK'), 1);
                } elseif ($key === 'aggregator') {
                    $current['avg_aggregator'] = round($this->avgPercent($items, 'Pengisian_Aggregator'), 1);
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
                    $current['avg_ikk'] = round($this->avgPercent($items, 'Compliance_IKK'), 1);
                } elseif ($key === 'aggregator') {
                    $current['avg_aggregator'] = round($this->avgPercent($items, 'Pengisian_Aggregator'), 1);
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
                'avg_sap' => round($this->avgNumeric($rows->filter(fn ($r) => ! $this->isAllToken($r['SAP_per_SID'] ?? null)), 'SAP_per_SID'), 1),
                'avg_rfid' => round($this->avgNumeric($rows->filter(fn ($r) => ! $this->isAllToken($r['RFID_per_SID'] ?? null)), 'RFID_per_SID'), 1),
            ],
            'coverage-cctv' => [
                'avg_pct' => round($this->avgPercent($rows, 'Tercover'), 1),
            ],
            'task-submitted' => [
                'avg_hours' => round($this->avgNumeric($rows, 'Selisih_jam_dari_Submit'), 1),
            ],
            'ikk-work-permit' => [
                'avg_compliance' => round($this->avgPercent($rows, 'Compliance_IKK'), 1),
            ],
            'implementasi-ikk' => [
                'avg_compliance' => round($this->avgPercent($rows, 'Compliance_IKK'), 1),
            ],
            'aggregator' => [
                'avg_fill' => round($this->avgPercent($rows, 'Pengisian_Aggregator'), 1),
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
        $rows = collect($this->repository->rows($meta['table']));

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

    /**
     * Rata-rata kolom bernilai boolean 0/1 (1 = tercapai) dinyatakan dalam persen 0-100.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function avgPercent(Collection $rows, string $column): float
    {
        return $this->avgNumeric($rows, $column) * 100;
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
