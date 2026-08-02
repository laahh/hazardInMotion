<?php

declare(strict_types=1);

namespace App\Services\Hsecm;

use App\Models\Employee;
use App\Support\FatigueManagement\FatigueManagementCompanyResolver;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
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
            'date_column' => 'date',
            'columns' => [
                'date' => 'Tanggal',
                'sid_pelapor_all_karyawan' => 'SID',
                'pelapor_all_karyawan' => 'Pelapor',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan',
                'site_dedicated_pelapor_all_karyawan' => 'Site',
                'Layer_Pelapor' => 'Layer',
                'jabatan_fungsional_pelapor_all_karyawan' => 'Department',
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
            'date_column' => 'Day_of_Date',
            'columns' => [
                'Day_of_Date' => 'Tanggal',
                'Site' => 'Site',
                'Lokasi' => 'Lokasi',
                'Detil_Lokasi' => 'Detil Lokasi',
                'Status_Coverage_dalam_1_Week' => 'Status Coverage',
                'Tercover' => '% Tercover',
                'Week_of_Date' => 'Week',
                'Year_of_Date' => 'Year',
            ],
        ],
        'tbc-blindspot' => [
            'label' => 'Blindspot TBC',
            'icon' => 'visibility_off',
            'table' => 'scr_hsecm_blindspot_tbc_gr',
            'site_column' => 'site',
            'company_column' => 'perusahaan_pic',
            'week_column' => 'Week_of_Date_for_Join',
            'year_column' => 'Year_of_Date_for_Join',
            'date_column' => 'Date_for_Join',
            'columns' => [
                'Date_for_Join' => 'Tanggal',
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
            'date_column' => 'tanggal_janji',
            'columns' => [
                'Second_of_date_time' => 'Tanggal/Waktu',
                'tanggal_janji' => 'Tanggal Janji',
                'Task_Number' => 'No Task',
                'site' => 'Site',
                'deskripsi' => 'Deskripsi',
                'pelapor_all_karyawan' => 'Pelapor',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan Pelapor',
                'pic' => 'PIC',
                'perusahaan_pic' => 'Perusahaan PIC',
                'status3' => 'Status',
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
            'date_column' => 'Second_of_date_time',
            'columns' => [
                'Second_of_date_time' => 'Tanggal/Waktu',
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
            'date_column' => 'Start_Date_Convert',
            'columns' => [
                'Start_Date_Convert' => 'Tanggal',
                'Code' => 'Code',
                'Name_Ikk_Work_Permit' => 'Nama IKK',
                'Company_Name_Ikk_Work_Permit' => 'Perusahaan',
                'Ra_Site_Name' => 'Site',
                'Location_Name' => 'Lokasi',
                'Location_Detail_Name' => 'Detil Lokasi',
                'Status' => 'Status',
                'Status_Ikk_Work_Permit_Pic' => 'Status PIC',
                'Compliance_IKK' => '% Compliance',
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
            'date_column' => 'Start_Date_Convert',
            'columns' => [
                'Start_Date_Convert' => 'Tanggal',
                'Code' => 'Code',
                'Name_Ikk_Work_Permit' => 'Nama IKK',
                'Company_Name_Ikk_Work_Permit' => 'Perusahaan',
                'Ra_Site_Name' => 'Site',
                'Location_Name' => 'Lokasi',
                'Location_Detail_Name' => 'Detil Lokasi',
                'Status' => 'Status',
                'Status_Ikk_Work_Permit_Pic' => 'Status PIC',
                'Compliance_IKK' => '% Compliance',
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
            'date_column' => 'Day_of_Tanggal_Date',
            'columns' => [
                'Day_of_Tanggal_Date' => 'Tanggal',
                'Kode_Sid' => 'SID',
                'Nama' => 'Nama',
                'Nama_Perusahaan' => 'Perusahaan',
                'Site_Dedicated' => 'Site',
                'Jabatan_Struktural' => 'Jabatan Struktural',
                'Pengisian_Aggregator' => '% Pengisian',
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
            'date_column' => 'Tanggal_Date',
            'columns' => [
                'Tanggal_Date' => 'Tanggal',
                'Kode_Sid' => 'SID',
                'Nama' => 'Nama',
                'Nama_Perusahaan' => 'Perusahaan',
                'Site_Dedicated' => 'Site',
                'Jabatan_Struktural' => 'Jabatan Struktural',
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
            'date_column' => 'date',
            'columns' => [
                'date' => 'Tanggal',
                'tanggal_hari_pertama' => 'Hari Pertama Lapor',
                'sid_pelapor_all_karyawan' => 'SID',
                'nama' => 'Nama',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan',
                'site_dedicated' => 'Site',
                'department' => 'Department',
                'jabatan_struktural' => 'Jabatan Struktural',
                'sumber_data' => 'Sumber Data',
                'Week_of_date' => 'Week',
                'Year_of_date' => 'Year',
            ],
        ],
        'hazard-rootcause' => [
            'label' => 'Hazard Related Incident',
            'icon' => 'report',
            'table' => 'scr_hsecm_hazard_rootcause_belum_terlaporkan',
            'site_column' => 'Site',
            'company_column' => null,
            'week_column' => 'Week',
            'year_column' => 'filter_year',
            'date_column' => null,
            'columns' => [
                'Site' => 'Site',
                'Lokasi' => 'Lokasi',
                'Detail_Lokasi' => 'Detail Lokasi',
                'Ketidaksesuaian' => 'Ketidaksesuaian',
                'Sub_Ketidaksesuaian' => 'Sub Ketidaksesuaian',
                'HIPO_Index_pada_Lokasi' => 'HIPO Index',
                'Severity_Index_pada_Lokasi' => 'Severity Index',
                'Week' => 'Week',
                'filter_year' => 'Year',
            ],
        ],
    ];

    /**
     * Urutan site & mitra untuk header Ringkasan Gap Perulangan (2 tingkat).
     *
     * @var array<string, list<string>>
     */
    private const GAP_PERULANGAN_SITE_COMPANIES = [
        'BMO 1' => ['BUMA', 'KDC', 'MTL'],
        'BMO 2' => ['PAMA'],
        'BMO3' => ['BAR'],
        'GMO' => ['PAMA'],
        'LMO' => ['BUMA', 'FAD', 'MTN'],
    ];

    /**
     * @var array<string, string>
     */
    private const GAP_PERULANGAN_COMPANY_NAMES = [
        'BUMA' => 'PT Bukit Makmur Mandiri Utama',
        'KDC' => 'PT Kaltim Diamond Coal',
        'MTL' => 'PT Mutiara Tanjung Lestari',
        'PAMA' => 'PT Pamapersada Nusantara',
        'BAR' => 'PT Bumi Artlantis Raya',
        'FAD' => 'PT Fajar Anugerah Dinamika',
        'MTN' => 'PT Madhani Talatah Nusantara',
    ];

    public function __construct(
        private readonly HsecmDatabaseRepository $repository,
    ) {}


    /**
     * @return array{
     *     site: string,
     *     perusahaan: string,
     *     week: string,
     *     year: string,
     *     date_from: string,
     *     date_to: string,
     *     q: string,
     *     batch_slot: string,
     *     data_mode: string,
     *     previous_batch_slot: string
     * }
     */
    public function resolveFilters(Request $request): array
    {
        $dateFrom = trim((string) $request->query('date_from', $request->input('date_from', '')));
        $dateTo = trim((string) $request->query('date_to', $request->input('date_to', '')));

        if ($dateFrom !== '' && ! $this->isValidYmd($dateFrom)) {
            $dateFrom = '';
        }
        if ($dateTo !== '' && ! $this->isValidYmd($dateTo)) {
            $dateTo = '';
        }
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $dataMode = strtolower(trim((string) $request->query('data_mode', $request->input('data_mode', 'snapshot'))));
        if (! in_array($dataMode, ['snapshot', 'still_open', 'all'], true)) {
            $dataMode = 'snapshot';
        }

        return [
            'site' => trim((string) $request->query('site', $request->input('site', ''))),
            'perusahaan' => trim((string) $request->query('perusahaan', $request->input('perusahaan', ''))),
            'week' => trim((string) $request->query('week', $request->input('week', ''))),
            'year' => trim((string) $request->query('year', $request->input('year', ''))),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'q' => trim((string) $request->query('q', $request->input('q', ''))),
            'batch_slot' => trim((string) $request->query('batch_slot', $request->input('batch_slot', ''))),
            'data_mode' => $dataMode,
            'previous_batch_slot' => trim((string) $request->query('previous_batch_slot', $request->input('previous_batch_slot', ''))),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function withBatchContext(array $filters, string $batchSlot, string $dataMode = 'snapshot', ?string $previousBatchSlot = null): array
    {
        return array_merge($filters, [
            'batch_slot' => $batchSlot,
            'data_mode' => $dataMode,
            'previous_batch_slot' => $previousBatchSlot ?? '',
        ]);
    }

    /**
     * @return array{sites: list<string>, companies: list<string>, weeks: list<string>, years: list<string>}
     */
    public function getFilterOptions(): array
    {
        return $this->buildFilterOptions();
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, date_from: string, date_to: string, q: string}  $filters
     * @return array<string, mixed>
     */
    public function buildDashboard(array $filters): array
    {
        // Snapshot + batch_slot kosong → rowsForBatchSlot memakai latestBatchSlot() per tabel.
        if (trim((string) ($filters['batch_slot'] ?? '')) === '') {
            $filters['data_mode'] = 'snapshot';
            $filters['previous_batch_slot'] = '';
        }

        return [
            'filters' => $filters,
            'filter_options' => $this->buildFilterOptions(),
            'period_label' => $this->buildPeriodLabel($filters),
            'kpis' => $this->buildKpis($filters),
            'by_site' => $this->buildSiteMonitoring($filters),
            'by_company' => $this->buildCompanyMonitoring($filters),
            'datasets' => collect($this->visibleDatasets())->map(fn (array $meta, string $key): array => [
                'key' => $key,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'count' => $this->filteredRows($key, $filters)->count(),
            ])->values()->all(),
            'data_source' => 'database',
        ];
    }

    /**
     * Dashboard Gap Perulangan — data still_open per program.
     *
     * @param  array{site: string, perusahaan: string, week: string, year: string, date_from: string, date_to: string, q: string, batch_slot?: string, data_mode?: string, previous_batch_slot?: string}  $filters
     * @return array{
     *     filters: array<string, mixed>,
     *     filter_options: array<string, mixed>,
     *     period_label: string,
     *     summary: array{
     *         groups: list<array{site: string, companies: list<array{code: string, name: string, key: string}>}>,
     *         columns: list<string>,
     *         rows: list<array{key: string, label: string, counts: array<string, int>}>
     *     },
     *     sections: list<array<string, mixed>>
     * }
     */
    public function buildGapPerulanganDashboard(array $filters): array
    {
        // Snapshot batch terkini (bukan still_open): banyak program punya gap_count=1
        // tanpa irisan business_key antar slot, sehingga still_open jadi kosong.
        $filters = array_merge($filters, [
            'data_mode' => 'snapshot',
            'batch_slot' => '',
            'previous_batch_slot' => '',
        ]);

        $datasetRows = [];
        foreach (array_keys($this->gapPerulanganDatasets()) as $key) {
            $datasetRows[$key] = $this->filteredRows($key, $filters);
        }

        return [
            'filters' => $filters,
            'filter_options' => $this->buildFilterOptions(),
            'period_label' => $this->buildPeriodLabel($filters),
            'summary' => $this->buildGapPerulanganSummary($datasetRows),
            'sections' => $this->buildGapPerulanganSections($datasetRows, $filters),
        ];
    }

    /**
     * @param  array<string, Collection<int, array<string, mixed>>>  $datasetRows
     * @return array{
     *     groups: list<array{site: string, companies: list<array{code: string, name: string, key: string}>}>,
     *     columns: list<string>,
     *     rows: list<array{key: string, label: string, counts: array<string, int>}>
     * }
     */
    private function buildGapPerulanganSummary(array $datasetRows): array
    {
        $matrixRows = [];
        /** @var array<string, array<string, true>> $seenPairs site => [code => true] */
        $seenPairs = [];

        foreach ($this->gapPerulanganDatasets() as $key => $meta) {
            $rows = $datasetRows[$key] ?? collect();
            $counts = [];

            foreach ($rows as $row) {
                $pair = $this->gapPerulanganSiteCompanyPair($row, $meta);
                if ($pair === null) {
                    continue;
                }

                [$site, $code] = $pair;
                $scopeKey = $this->gapPerulanganMatrixKey($site, $code);
                $counts[$scopeKey] = ($counts[$scopeKey] ?? 0) + 1;
                $seenPairs[$site][$code] = true;
            }

            $matrixRows[] = [
                'key' => $key,
                'label' => $this->gapPerulanganProgramLabel($key, $meta['label']),
                'counts' => $counts,
            ];
        }

        $groups = $this->buildGapPerulanganHeaderGroups($seenPairs);
        $columns = [];
        foreach ($groups as $group) {
            foreach ($group['companies'] as $company) {
                $columns[] = $company['key'];
            }
        }

        return [
            'groups' => $groups,
            'columns' => $columns,
            'rows' => $matrixRows,
        ];
    }

    /**
     * @param  array<string, array<string, true>>  $seenPairs
     * @return list<array{site: string, companies: list<array{code: string, name: string, key: string}>}>
     */
    private function buildGapPerulanganHeaderGroups(array $seenPairs): array
    {
        $groups = [];
        /** @var array<string, int> $groupIndexByNormSite */
        $groupIndexByNormSite = [];

        foreach (self::GAP_PERULANGAN_SITE_COMPANIES as $site => $codes) {
            $companies = [];
            foreach ($codes as $code) {
                $companies[] = [
                    'code' => $code,
                    'name' => self::GAP_PERULANGAN_COMPANY_NAMES[$code] ?? $code,
                    'key' => $this->gapPerulanganMatrixKey($site, $code),
                ];
            }

            $groupIndexByNormSite[$this->normalizeGapPerulanganSite($site)] = count($groups);
            $groups[] = [
                'site' => $site,
                'companies' => $companies,
            ];
        }

        foreach ($seenPairs as $site => $codesMap) {
            $norm = $this->normalizeGapPerulanganSite($site);
            $codes = array_keys($codesMap);
            sort($codes);

            if (isset($groupIndexByNormSite[$norm])) {
                $idx = $groupIndexByNormSite[$norm];
                $existing = array_column($groups[$idx]['companies'], 'code');
                $templateSite = $groups[$idx]['site'];

                foreach (array_diff($codes, $existing) as $code) {
                    $groups[$idx]['companies'][] = [
                        'code' => $code,
                        'name' => self::GAP_PERULANGAN_COMPANY_NAMES[$code]
                            ?? FatigueManagementCompanyResolver::partnerToCompany($code),
                        'key' => $this->gapPerulanganMatrixKey($templateSite, $code),
                    ];
                }

                continue;
            }

            $companies = [];
            foreach ($codes as $code) {
                $companies[] = [
                    'code' => $code,
                    'name' => self::GAP_PERULANGAN_COMPANY_NAMES[$code]
                        ?? FatigueManagementCompanyResolver::partnerToCompany($code),
                    'key' => $this->gapPerulanganMatrixKey($site, $code),
                ];
            }

            if ($companies === []) {
                continue;
            }

            $groupIndexByNormSite[$norm] = count($groups);
            $groups[] = [
                'site' => $site,
                'companies' => $companies,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $meta
     * @return array{0: string, 1: string}|null
     */
    private function gapPerulanganSiteCompanyPair(array $row, array $meta): ?array
    {
        $siteRaw = trim((string) ($row[$meta['site_column']] ?? ''));
        if ($siteRaw === '' || $this->isAllToken($siteRaw)) {
            return null;
        }

        $site = $this->resolveGapPerulanganTemplateSite($siteRaw) ?? $siteRaw;
        $code = $this->resolveGapPerulanganCompanyCode($row, $meta);
        if ($code === null) {
            return null;
        }

        return [$site, $code];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $meta
     */
    private function resolveGapPerulanganCompanyCode(array $row, array $meta): ?string
    {
        $companyColumn = $meta['company_column'] ?? null;
        if ($companyColumn !== null) {
            $raw = trim((string) ($row[$companyColumn] ?? ''));
            if ($raw !== '' && ! $this->isAllToken($raw)) {
                $code = FatigueManagementCompanyResolver::companyToPartner($raw);
                $normalized = FatigueManagementCompanyResolver::normalizeKey($code);
                if (FatigueManagementCompanyResolver::isKnownPartnerKey($normalized)) {
                    return $normalized;
                }
                // Sudah singkatan pendek (BUMA, FAD, …)
                if (preg_match('/^[A-Z]{2,6}$/', $normalized) === 1) {
                    return $normalized;
                }
            }
        }

        foreach (['Detail_Lokasi', 'Detil_Lokasi', 'Lokasi', 'Location_Name'] as $col) {
            $text = trim((string) ($row[$col] ?? ''));
            if ($text === '') {
                continue;
            }
            if (preg_match('/\[([A-Za-z]{2,6})\]/', $text, $m) === 1) {
                $code = FatigueManagementCompanyResolver::normalizeKey($m[1]);
                if ($code !== '') {
                    return $code;
                }
            }
        }

        return null;
    }

    private function resolveGapPerulanganTemplateSite(string $site): ?string
    {
        $normalized = $this->normalizeGapPerulanganSite($site);

        foreach (array_keys(self::GAP_PERULANGAN_SITE_COMPANIES) as $template) {
            if ($this->normalizeGapPerulanganSite($template) === $normalized) {
                return $template;
            }
        }

        return null;
    }

    private function normalizeGapPerulanganSite(string $site): string
    {
        $s = mb_strtoupper(preg_replace('/\s+/u', ' ', trim($site)) ?? '');
        $s = str_replace(['BMO1', 'BMO 1'], 'BMO 1', $s);
        $s = str_replace(['BMO2', 'BMO 2'], 'BMO 2', $s);
        $s = str_replace(['BMO 3', 'BMO-3'], 'BMO3', $s);

        return $s;
    }

    private function gapPerulanganMatrixKey(string $site, string $companyCode): string
    {
        return $site.'|'.$companyCode;
    }

    /**
     * Template header matriks Site → Perusahaan (untuk Gap Evaluasi / Perulangan).
     *
     * @return array{
     *     groups: list<array{site: string, companies: list<array{code: string, name: string, key: string}>}>,
     *     columns: list<string>
     * }
     */
    public function buildGapEvaluasiMatrixTemplate(): array
    {
        $groups = [];
        $columns = [];

        foreach (self::GAP_PERULANGAN_SITE_COMPANIES as $site => $codes) {
            $companies = [];
            foreach ($codes as $code) {
                $key = $this->gapPerulanganMatrixKey($site, $code);
                $companies[] = [
                    'code' => $code,
                    'name' => self::GAP_PERULANGAN_COMPANY_NAMES[$code] ?? $code,
                    'key' => $key,
                ];
                $columns[] = $key;
            }
            $groups[] = [
                'site' => $site,
                'companies' => $companies,
            ];
        }

        return [
            'groups' => $groups,
            'columns' => $columns,
        ];
    }

    /**
     * Resolve kunci matriks `Site|KODE` dari label site + perusahaan identity row.
     */
    public function resolveGapEvaluasiScopeKey(string $site, string $perusahaan): ?string
    {
        $siteRaw = trim($site);
        if ($siteRaw === '' || $this->isAllToken($siteRaw)) {
            return null;
        }

        $templateSite = $this->resolveGapPerulanganTemplateSite($siteRaw) ?? $siteRaw;
        $companyRaw = trim($perusahaan);
        if ($companyRaw === '' || $this->isAllToken($companyRaw)) {
            return null;
        }

        $code = FatigueManagementCompanyResolver::companyToPartner($companyRaw);
        $normalized = FatigueManagementCompanyResolver::normalizeKey($code);
        if (FatigueManagementCompanyResolver::isKnownPartnerKey($normalized)) {
            return $this->gapPerulanganMatrixKey($templateSite, $normalized);
        }

        if (preg_match('/^[A-Z]{2,6}$/', $normalized) === 1) {
            return $this->gapPerulanganMatrixKey($templateSite, $normalized);
        }

        return null;
    }

    /**
     * @param  array<string, Collection<int, array<string, mixed>>>  $datasetRows
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function buildGapPerulanganSections(array $datasetRows, array $filters): array
    {
        $sections = [];

        foreach ($this->gapPerulanganDatasets() as $key => $meta) {
            $rows = $datasetRows[$key] ?? collect();
            $sections[] = match ($key) {
                'sap-rfid' => $this->buildGapPerulanganSapSection($key, $meta, $rows),
                'coverage-cctv' => $this->buildGapPerulanganCoverageSection($key, $meta, $rows, $filters),
                default => $this->buildGapPerulanganGenericSection($key, $meta, $rows),
            };
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildGapPerulanganSapSection(string $key, array $meta, Collection $rows): array
    {
        $sorted = $rows
            ->sortByDesc(fn (array $row): int => (int) ($row['gap_count'] ?? 0))
            ->values()
            ->take(100);

        $tableRows = [];
        foreach ($sorted as $index => $row) {
            $site = trim((string) ($row['site_dedicated_pelapor_all_karyawan'] ?? ''));
            $tableRows[] = [
                'rank' => $index + 1,
                'nama' => (string) ($row['pelapor_all_karyawan'] ?? '—'),
                'sid' => (string) ($row['sid_pelapor_all_karyawan'] ?? '—'),
                'site' => $site !== '' ? $site : '—',
                'jabatan' => (string) ($row['jabatan_struktural_pelapor_all_karyawan'] ?? '—'),
                'perusahaan' => (string) ($row['perusahaan_pelapor_all_karyawan'] ?? '—'),
                'gap_count' => $this->toPerulanganDisplayCount((int) ($row['gap_count'] ?? 0)),
                'sap' => $row['SAP_per_SID'] ?? '—',
            ];
        }

        return [
            'key' => $key,
            'label' => 'Layer 1 tanpa SAP',
            'icon' => $meta['icon'],
            'layout' => 'sap-rfid',
            'chart_title' => 'Top Perusahaan dengan Perulangan',
            'top_chart' => $this->buildGapPerulanganTopChart($rows, $meta, 'company', 8),
            'table_headers' => ['Rank', 'Nama', 'SID', 'Site', 'Jabatan Struktural', 'Perusahaan', 'Jumlah Perulangan', 'Total SAP'],
            'table_rows' => $tableRows,
            'total' => $rows->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  Collection<int, array<string, mixed>>  $rows  Snapshot gap terkini (untuk ranking lokasi).
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildGapPerulanganCoverageSection(string $key, array $meta, Collection $rows, array $filters): array
    {
        $dateKeys = $this->resolveCoverageWeekDateKeys($rows, $meta, $filters);

        // Ambil histori 7 hari (mode all + filter tanggal) agar kolom harian X/V lengkap.
        $weekFilters = array_merge($filters, [
            'data_mode' => 'all',
            'batch_slot' => '',
            'previous_batch_slot' => '',
            'date_from' => $dateKeys[0] ?? '',
            'date_to' => $dateKeys[count($dateKeys) - 1] ?? '',
        ]);
        $weekRows = $dateKeys === []
            ? collect()
            : $this->filteredRows($key, $weekFilters);

        $grouped = [];

        foreach ($weekRows as $row) {
            $site = trim((string) ($row['Site'] ?? ''));
            $lokasi = trim((string) ($row['Lokasi'] ?? ''));
            $detil = trim((string) ($row['Detil_Lokasi'] ?? ''));
            if ($site === '' && $lokasi === '' && $detil === '') {
                continue;
            }

            $groupKey = $site.'|'.$lokasi.'|'.$detil;
            $ymd = $this->parseFlexibleDate($row[$meta['date_column']] ?? null);

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'site' => $site !== '' ? $site : '—',
                    'lokasi' => $lokasi !== '' ? $lokasi : '—',
                    'detil' => $detil !== '' ? $detil : '—',
                    'gap_count' => (int) ($row['gap_count'] ?? 0),
                    'x_count' => 0,
                    'days' => [],
                ];
            }

            $grouped[$groupKey]['gap_count'] = max(
                $grouped[$groupKey]['gap_count'],
                (int) ($row['gap_count'] ?? 0)
            );

            if ($ymd === null || ! in_array($ymd, $dateKeys, true)) {
                continue;
            }

            $mark = $this->coverageDayMark($row);
            $grouped[$groupKey]['days'][$ymd] = $mark;
            if ($mark === 'X') {
                $grouped[$groupKey]['x_count']++;
            }
        }

        // Utamakan lokasi yang punya gap (X) di minggu ini.
        $grouped = array_filter(
            $grouped,
            static fn (array $item): bool => $item['x_count'] > 0 || $item['gap_count'] > 0
        );

        uasort($grouped, static function (array $a, array $b): int {
            if ($a['x_count'] !== $b['x_count']) {
                return $b['x_count'] <=> $a['x_count'];
            }

            return $b['gap_count'] <=> $a['gap_count'];
        });
        $limited = array_slice($grouped, 0, 100, true);

        $tableRows = [];
        $rank = 1;
        $siteGapCounts = [];
        foreach ($limited as $item) {
            $dayMarks = [];
            foreach ($dateKeys as $ymd) {
                // Kosong jika tidak ada data hari itu (bukan default X).
                $dayMarks[$ymd] = $item['days'][$ymd] ?? '';
            }

            $tableRows[] = [
                'rank' => $rank++,
                'site' => $item['site'],
                'lokasi' => $item['lokasi'],
                'detil' => $item['detil'],
                'days' => $dayMarks,
                'gap_count' => $this->toPerulanganDisplayCount((int) ($item['gap_count'] ?? 0)),
                'x_count' => $item['x_count'],
            ];

            if ($item['site'] !== '—') {
                $siteGapCounts[$item['site']] = ($siteGapCounts[$item['site']] ?? 0) + $item['x_count'];
            }
        }

        arsort($siteGapCounts);
        $topChart = [];
        foreach (array_slice($siteGapCounts, 0, 8, true) as $label => $value) {
            $topChart[] = ['label' => $label, 'value' => $value];
        }

        $dateHeaders = [];
        foreach ($dateKeys as $ymd) {
            $dateHeaders[$ymd] = Carbon::parse($ymd)->format('d M');
        }

        return [
            'key' => $key,
            'label' => 'Coverage Area Kritis Daily',
            'icon' => $meta['icon'],
            'layout' => 'coverage-cctv',
            'chart_title' => 'Top Site dengan Perulangan',
            'top_chart' => $topChart,
            'date_headers' => $dateHeaders,
            'table_rows' => $tableRows,
            'total' => count($grouped),
        ];
    }

    /**
     * Rentang 7 hari untuk matriks Coverage (wireframe 19–25 Jul).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $filters
     * @return list<string> Y-m-d
     */
    private function resolveCoverageWeekDateKeys(Collection $rows, array $meta, array $filters): array
    {
        if ($filters['date_from'] !== '' && $filters['date_to'] !== '') {
            $dates = [];
            $cursor = Carbon::parse($filters['date_from'])->startOfDay();
            $end = Carbon::parse($filters['date_to'])->startOfDay();
            while ($cursor->lte($end) && count($dates) < 14) {
                $dates[] = $cursor->format('Y-m-d');
                $cursor->addDay();
            }

            return $dates;
        }

        $endYmd = $rows
            ->map(fn (array $row): ?string => $this->parseFlexibleDate($row[$meta['date_column']] ?? null))
            ->filter()
            ->sort()
            ->last();

        if (! is_string($endYmd) || $endYmd === '') {
            $endYmd = Carbon::now()->format('Y-m-d');
        }

        $end = Carbon::parse($endYmd)->startOfDay();
        $start = $end->copy()->subDays(6);
        $dates = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildGapPerulanganGenericSection(string $key, array $meta, Collection $rows): array
    {
        $columnKeys = match ($key) {
            'tbc-blindspot' => [
                'Date_for_Join',
                'site',
                'kategori_TBC',
                'blindspot_TBC',
                'deskripsi',
                'pelapor_all_karyawan',
                'perusahaan_pic',
                'pic',
            ],
            default => array_slice(array_keys($meta['columns']), 0, 6),
        };
        $headers = ['Rank'];
        foreach ($columnKeys as $col) {
            $headers[] = $meta['columns'][$col] ?? $col;
        }
        $headers[] = 'Jumlah Perulangan';

        $sorted = $rows
            ->sortByDesc(fn (array $row): int => (int) ($row['gap_count'] ?? 0))
            ->values()
            ->take(100);

        $tableRows = [];
        foreach ($sorted as $index => $row) {
            $cells = [];
            foreach ($columnKeys as $col) {
                $val = $row[$col] ?? null;
                $cells[$col] = $val === null || trim((string) $val) === '' ? '—' : (string) $val;
            }

            $tableRows[] = [
                'rank' => $index + 1,
                'cells' => $cells,
                'gap_count' => $this->toPerulanganDisplayCount((int) ($row['gap_count'] ?? 0)),
            ];
        }

        $chartAxis = $meta['company_column'] !== null ? 'company' : 'site';

        return [
            'key' => $key,
            'label' => $meta['label'],
            'icon' => $meta['icon'],
            'layout' => 'generic',
            'chart_title' => $chartAxis === 'company'
                ? 'Top Perusahaan dengan Perulangan'
                : 'Top Site dengan Perulangan',
            'top_chart' => $this->buildGapPerulanganTopChart($rows, $meta, $chartAxis, 8),
            'table_headers' => $headers,
            'table_column_keys' => $columnKeys,
            'table_rows' => $tableRows,
            'total' => $rows->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $meta
     * @return list<array{label: string, value: int}>
     */
    private function buildGapPerulanganTopChart(Collection $rows, array $meta, string $axis, int $limit): array
    {
        $counts = [];

        foreach ($rows as $row) {
            if ($axis === 'company' && $meta['company_column'] !== null) {
                $label = trim((string) ($row[$meta['company_column']] ?? ''));
            } else {
                $label = trim((string) ($row[$meta['site_column']] ?? ''));
            }

            if ($label === '') {
                continue;
            }

            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        arsort($counts);

        $chart = [];
        foreach (array_slice($counts, 0, $limit, true) as $label => $value) {
            $chart[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $chart;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function coverageDayMark(array $row): string
    {
        $status = strtolower(trim((string) ($row['Status_Coverage_dalam_1_Week'] ?? '')));
        if ($status !== '') {
            if (str_contains($status, 'tidak') || str_contains($status, 'belum') || str_contains($status, 'gap')) {
                return 'X';
            }
            if (str_contains($status, 'tercover') || $status === 'v' || $status === 'ok' || $status === 'covered') {
                return 'V';
            }
        }

        $tercover = $this->toFloat($row['Tercover'] ?? null);
        if ($tercover !== null) {
            return $tercover >= 1 ? 'V' : 'X';
        }

        return 'X';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $meta
     */
    private function gapPerulanganScopeKey(array $row, array $meta): string
    {
        $site = trim((string) ($row[$meta['site_column']] ?? ''));
        $companyColumn = $meta['company_column'] ?? null;

        if ($companyColumn === null) {
            return $site;
        }

        $company = trim((string) ($row[$companyColumn] ?? ''));
        if ($company === '' && $site === '') {
            return '';
        }
        if ($company === '') {
            return $site;
        }
        if ($site === '') {
            return $company;
        }

        return $company.' '.$site;
    }

    private function gapPerulanganProgramLabel(string $key, string $defaultLabel): string
    {
        return match ($key) {
            'sap-rfid' => 'Layer 1 tanpa SAP',
            'coverage-cctv' => 'Coverage Area Kritis',
            'hazard-rootcause' => 'Hazard Related Incident',
            default => $defaultLabel,
        };
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, date_from?: string, date_to?: string, q: string}  $filters
     * @return array{
     *     kpis: list<array<string, mixed>>,
     *     datasets: list<array<string, mixed>>,
     *     by_company: list<array<string, mixed>>,
     *     filter_options: array<string, mixed>
     * }
     */
    public function buildScopeSummary(array $filters): array
    {
        $filters = array_merge([
            'site' => '',
            'perusahaan' => '',
            'week' => '',
            'year' => '',
            'date_from' => '',
            'date_to' => '',
            'q' => '',
            'batch_slot' => '',
            'data_mode' => 'snapshot',
            'previous_batch_slot' => '',
        ], $filters);

        return [
            'kpis' => $this->buildKpis($filters),
            'datasets' => collect($this->visibleDatasets())->map(fn (array $meta, string $key): array => [
                'key' => $key,
                'label' => $meta['label'],
                'count' => $this->filteredRows($key, $filters)->count(),
            ])->values()->all(),
            'by_company' => $this->buildCompanyDetailForNotify($filters),
            'email_narrative' => $this->buildEmailNarrative($filters),
            'filter_options' => $this->buildFilterOptions(),
        ];
    }

    /**
     * Struktur konten email naratif (exposure + gap) beserta tabel detail.
     *
     * @param  array{site: string, perusahaan: string, week: string, year: string, date_from?: string, date_to?: string, q: string}  $filters
     * @return array{exposure: list<array<string, mixed>>, gaps: list<array<string, mixed>>}
     */
    public function buildEmailNarrative(array $filters, int $rowLimit = 40): array
    {
        $filters = array_merge([
            'site' => '',
            'perusahaan' => '',
            'week' => '',
            'year' => '',
            'date_from' => '',
            'date_to' => '',
            'q' => '',
            'batch_slot' => '',
            'data_mode' => 'snapshot',
            'previous_batch_slot' => '',
        ], $filters);

        $ikkRows = $this->filteredRows('ikk-work-permit', $filters);
        $avgIkk = round($this->avgPercent($ikkRows, 'Compliance_IKK'), 1);
        $ikkNonCompliantRows = $this->filterIkkBelowFullCompliance($ikkRows);
        $sumberRows = $this->filteredRows('sumber-rfid', $filters);
        $coverageRows = $this->filteredRows('coverage-cctv', $filters);
        $tbcRows = $this->filteredRows('tbc-blindspot', $filters);
        $overdueRows = $this->filteredRows('task-overdue', $filters);
        $submittedRows = $this->filteredRows('task-submitted', $filters);
        $aggregatorRows = $this->filteredRows('aggregator', $filters);
        $fatigueRows = $this->filteredRows('fatigue', $filters);
        $hazardRootcauseRows = $this->filteredRows('hazard-rootcause', $filters);

        $sapRows = $this->filteredRows('sap-rfid', $filters)
            ->filter(function (array $row): bool {
                if ($this->isAllToken($row['SAP_per_SID'] ?? null)) {
                    return false;
                }
                $sap = $this->toFloat($row['SAP_per_SID'] ?? null);

                return $sap !== null && abs($sap) < 0.00001;
            })
            ->values();

        return [
            'exposure' => [
                $this->makeEmailDetailSection(
                    key: 'ikk-aktif',
                    title: 'Jumlah IKK Aktif',
                    value: (string) $ikkRows->count(),
                    datasetKey: 'ikk-work-permit',
                    rows: $ikkRows,
                    columnKeys: ['Start_Date_Convert', 'Code', 'Name_Ikk_Work_Permit', 'Company_Name_Ikk_Work_Permit', 'Ra_Site_Name', 'Compliance_IKK'],
                    filters: $filters,
                    percentColumns: ['Compliance_IKK'],
                    rowLimit: $rowLimit,
                    action: 'Pantau IKK aktif di area kerja; pastikan PIC & OAK dan OKK ada',
                    tone: 'info',
                    needsAction: false,
                ),
                $this->makeEmailDetailSection(
                    key: 'pekerja-baru',
                    title: 'Jumlah Pekerja Baru',
                    value: (string) $sumberRows->count(),
                    datasetKey: 'sumber-rfid',
                    rows: $this->enrichPekerjaBaruWithEmployeeMeta($sumberRows),
                    columnKeys: ['date', 'sid_pelapor_all_karyawan', 'nama', 'perusahaan_pelapor_all_karyawan', 'site_dedicated', 'department', 'jabatan_struktural', 'sumber_data'],
                    filters: $filters,
                    rowLimit: $rowLimit,
                    action: 'Pastikan pekerja baru mendapat induksi/exposure control sebelum mulai bekerja.',
                    tone: 'info',
                    needsAction: false,
                ),
            ],
            'gaps' => [
                $this->makeEmailDetailSection(
                    key: 'layer1-tanpa-sap',
                    title: 'Layer 1 Tanpa SAP',
                    value: (string) $sapRows->count(),
                    datasetKey: 'sap-rfid',
                    rows: $sapRows,
                    columnKeys: ['date', 'sid_pelapor_all_karyawan', 'pelapor_all_karyawan', 'perusahaan_pelapor_all_karyawan', 'site_dedicated_pelapor_all_karyawan', 'jabatan_fungsional_pelapor_all_karyawan', 'jabatan_struktural_pelapor_all_karyawan', 'SAP_per_SID'],
                    filters: $filters,
                    rowLimit: $rowLimit,
                    action: 'Coaching oleh atasan langsung',
                    tone: 'warning',
                    includeStreak: true,
                ),
                $this->makeEmailDetailSection(
                    key: 'coverage-area',
                    title: 'Area Kritis belum tercover SAP',
                    value: (string) $coverageRows->count(),
                    datasetKey: 'coverage-cctv',
                    rows: $coverageRows,
                    columnKeys: ['Day_of_Date', 'Site', 'Lokasi', 'Detil_Lokasi', 'Status_Coverage_dalam_1_Week', 'Tercover'],
                    filters: $filters,
                    rowLimit: $rowLimit,
                    action: 'Jika detail lokasi tidak aktif dinonaktifkan dan wajib pemenuhan SAP di shift berikutnya',
                    tone: 'danger',
                    includeStreak: true,
                ),
                $this->makeEmailDetailSection(
                    key: 'tbc-blindspot',
                    title: 'TBC Blindspot',
                    value: (string) $tbcRows->count(),
                    datasetKey: 'tbc-blindspot',
                    rows: $tbcRows,
                    columnKeys: ['Date_for_Join', 'site', 'kategori_TBC', 'blindspot_TBC', 'pelapor_all_karyawan', 'status3'],
                    filters: $filters,
                    rowLimit: $rowLimit,
                    action: 'Wajib dilaksanakan Peer Pressure',
                    tone: 'danger',
                    includeStreak: true,
                ),
                $this->makeEmailDetailSection(
                    key: 'hazard-overdue',
                    title: 'Hazard Overdue',
                    value: (string) $overdueRows->count(),
                    datasetKey: 'task-overdue',
                    rows: $overdueRows,
                    columnKeys: ['tanggal_janji', 'Task_Number', 'site', 'deskripsi', 'pic', 'status3'],
                    filters: $filters,
                    rowLimit: $rowLimit,
                    action: 'Coaching oleh atasan langsung',
                    tone: 'danger',
                    includeStreak: true,
                ),
                $this->makeEmailDetailSection(
                    key: 'hazard-submitted',
                    title: 'Hazard Submitted > 24 jam',
                    value: (string) $submittedRows->count(),
                    datasetKey: 'task-submitted',
                    rows: $submittedRows,
                    columnKeys: ['Second_of_date_time', 'Task_Number', 'site', 'Selisih_jam_dari_Submit', 'pic', 'status3'],
                    filters: $filters,
                    rowLimit: $rowLimit,
                    action: 'Tindaklanjut tasklist',
                    tone: 'warning',
                    includeStreak: true,
                ),
                $this->makeEmailDetailSection(
                    key: 'ikk-compliance',
                    title: 'IKK Compliance',
                    value: $avgIkk.'%',
                    datasetKey: 'ikk-work-permit',
                    rows: $ikkNonCompliantRows,
                    columnKeys: ['Start_Date_Convert', 'Code', 'Name_Ikk_Work_Permit', 'Company_Name_Ikk_Work_Permit', 'Ra_Site_Name', 'Compliance_IKK'],
                    filters: $filters,
                    percentColumns: ['Compliance_IKK'],
                    rowLimit: $rowLimit,
                    action: 'Suspend IKK & Coaching personil terkait',
                    tone: $avgIkk >= 80 ? 'success' : 'warning',
                    needsAction: $ikkNonCompliantRows->isNotEmpty(),
                    includeStreak: true,
                ),
                $this->makeEmailDetailSection(
                    key: 'aggregator-fill',
                    title: 'Tidak mengisi Aggregator Fit to Work',
                    value: (string) $aggregatorRows->count(),
                    datasetKey: 'aggregator',
                    rows: $aggregatorRows,
                    columnKeys: ['Day_of_Tanggal_Date', 'Kode_Sid', 'Nama', 'Nama_Perusahaan', 'Site_Dedicated', 'Jabatan_Struktural', 'Pengisian_Aggregator'],
                    filters: $filters,
                    percentColumns: ['Pengisian_Aggregator'],
                    rowLimit: $rowLimit,
                    action: 'Menunjukkan evidence Operator tanpa Aggregator tidak mengoperasikan unit',
                    tone: 'warning',
                    includeStreak: true,
                ),
                $this->makeEmailDetailSection(
                    key: 'ftw-merah',
                    title: 'Aggregator Fit to Work Merah',
                    value: (string) $fatigueRows->count(),
                    datasetKey: 'fatigue',
                    rows: $fatigueRows,
                    columnKeys: ['Tanggal_Date', 'Kode_Sid', 'Nama', 'Nama_Perusahaan', 'Site_Dedicated', 'Jabatan_Struktural', 'Kondisi_Karyawan'],
                    filters: $filters,
                    rowLimit: $rowLimit,
                    action: 'Stop Operasi & Mengarahkan Operator dengan Fit to Work Merah diistirahatkan & Menunjukkan Operator tidak mengoperasikan unit',
                    tone: 'danger',
                    includeStreak: true,
                ),
                $this->makeEmailDetailSection(
                    key: 'hazard-rootcause',
                    title: 'Hazard Related Rootcause Incident Belum Terlaporkan',
                    value: (string) $hazardRootcauseRows->count(),
                    datasetKey: 'hazard-rootcause',
                    rows: $hazardRootcauseRows,
                    columnKeys: [
                        'Site',
                        'Lokasi',
                        'Detail_Lokasi',
                        'Ketidaksesuaian',
                        'Sub_Ketidaksesuaian',
                        'HIPO_Index_pada_Lokasi',
                        'Severity_Index_pada_Lokasi',
                        'Week',
                    ],
                    filters: $filters,
                    rowLimit: $rowLimit,
                    action: 'Laporkan / tindaklanjuti rootcause hazard terkait incident yang belum terlaporkan',
                    tone: 'danger',
                    includeStreak: true,
                ),
            ],
        ];
    }

    /**
     * Ringkasan dashboard PJO (Monitoring & Intervensi) selaras template email.
     *
     * @param  array{site: string, perusahaan: string, week: string, year: string, date_from: string, date_to: string, q: string}  $filters
     * @return array<string, mixed>
     */
    public function buildPjoActionDashboard(array $filters): array
    {
        $narrative = $this->buildEmailNarrative($filters, 100);
        $exposure = $narrative['exposure'];
        $gaps = $narrative['gaps'];

        $actionGaps = collect($gaps)
            ->filter(static fn (array $section): bool => (bool) ($section['needs_action'] ?? false))
            ->values()
            ->all();

        return [
            'filters' => $filters,
            'filter_options' => $this->buildFilterOptions(),
            'period_label' => $this->buildPeriodLabel($filters),
            'exposure' => $exposure,
            'gaps' => $gaps,
            'action_gaps' => $actionGaps,
            'summary' => [
                'exposure_items' => count($exposure),
                'gap_items' => count($gaps),
                'open_actions' => count($actionGaps),
                'total_gap_rows' => collect($actionGaps)->sum(static fn (array $s): int => (int) ($s['total'] ?? 0)),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<string>  $columnKeys
     * @param  array{site: string, perusahaan: string, week: string, year: string, date_from?: string, date_to?: string, q?: string, batch_slot?: string}  $filters
     * @param  list<string>  $percentColumns
     * @return array<string, mixed>
     */
    private function makeEmailDetailSection(
        string $key,
        string $title,
        string $value,
        string $datasetKey,
        Collection $rows,
        array $columnKeys,
        array $filters,
        array $percentColumns = [],
        int $rowLimit = 40,
        string $action = '',
        string $tone = 'warning',
        ?bool $needsAction = null,
        bool $includeStreak = false,
    ): array {
        $meta = self::DATASETS[$datasetKey];
        $beforeCount = $rows->count();
        $rows = $this->dedupeEmailRows($rows, $datasetKey);
        $afterCount = $rows->count();
        if (trim($value) === (string) $beforeCount) {
            $value = (string) $afterCount;
        }

        $allLabels = $meta['columns'];
        $columns = [];

        if ($includeStreak) {
            $columns[] = [
                'key' => '_perulangan',
                'label' => 'Perulangan',
            ];
        }

        foreach ($columnKeys as $columnKey) {
            if (! isset($allLabels[$columnKey])) {
                continue;
            }
            $columns[] = [
                'key' => $columnKey,
                'label' => $allLabels[$columnKey],
            ];
        }

        if ($includeStreak && $rows->isNotEmpty()) {
            $streakByKey = $this->resolveEmailRowStreaks($datasetKey, $meta, $rows, $filters);
            $rows = $rows
                ->map(function (array $row) use ($streakByKey, $datasetKey): array {
                    $businessKey = $this->resolveBusinessKey($row, $datasetKey);
                    $streak = 1;
                    if ($businessKey !== '' && isset($streakByKey[$businessKey]) && (int) $streakByKey[$businessKey] > 0) {
                        $streak = (int) $streakByKey[$businessKey];
                    } else {
                        $gapCount = (int) ($row['gap_count'] ?? 0);
                        $streak = $gapCount > 0 ? $gapCount : 1;
                    }
                    $row['_streak'] = $streak;

                    return $row;
                })
                ->sortByDesc(static fn (array $row): int => (int) ($row['_streak'] ?? 1))
                ->values();
        }

        $total = $rows->count();
        $limit = max(1, $rowLimit);
        $dateColumn = is_string($meta['date_column'] ?? null) ? (string) $meta['date_column'] : null;
        $previewRows = $rows->take($limit)->map(function (array $row) use (
            $columnKeys,
            $percentColumns,
            $dateColumn,
            $includeStreak,
        ): array {
            $cells = [];

            if ($includeStreak) {
                $streak = max(1, (int) ($row['_streak'] ?? 1));
                $cells['_perulangan'] = $this->toPerulanganDisplayCount($streak).'×';
            }

            foreach ($columnKeys as $columnKey) {
                $raw = $row[$columnKey] ?? '';
                if (in_array($columnKey, $percentColumns, true)) {
                    $num = $this->toFloat($raw);
                    $cells[$columnKey] = $num === null ? '—' : round($num * 100, 1).'%';
                    continue;
                }

                if ($dateColumn !== null && $columnKey === $dateColumn) {
                    $cells[$columnKey] = $this->formatEmailDateCell($row, $dateColumn);
                    continue;
                }

                $text = is_scalar($raw) || $raw === null ? (string) ($raw ?? '') : '';
                $text = trim($text);
                if (mb_strlen($text) > 80) {
                    $text = mb_substr($text, 0, 77).'...';
                }
                $cells[$columnKey] = $text !== '' ? $text : '—';
            }

            return $cells;
        })->values()->all();

        $resolvedNeedsAction = $needsAction ?? ($total > 0);

        return [
            'key' => $key,
            'title' => $title,
            'value' => $value,
            'available' => true,
            'dataset_key' => $datasetKey,
            'total' => $total,
            'truncated' => $total > $limit,
            'columns' => $columns,
            'rows' => $previewRows,
            'action' => $action,
            'tone' => $tone,
            'needs_action' => $resolvedNeedsAction,
            'detail_url' => route('hsecm.datasets.show', array_filter([
                'dataset' => $datasetKey,
                'site' => $filters['site'] ?? null,
                'perusahaan' => $filters['perusahaan'] ?? null,
                'week' => $filters['week'] ?? null,
                'year' => $filters['year'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '')),
        ];
    }

    /**
     * Streak consecutive per business_key (batch_slot). Fallback kosong → caller pakai gap_count.
     *
     * @param  array<string, mixed>  $meta
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function resolveEmailRowStreaks(string $datasetKey, array $meta, Collection $rows, array $filters): array
    {
        $table = (string) ($meta['table'] ?? '');
        $currentSlot = trim((string) ($filters['batch_slot'] ?? ''));
        if ($currentSlot === '' && $table !== '') {
            $currentSlot = (string) ($this->repository->latestBatchSlot($table) ?? '');
        }

        $keys = [];
        foreach ($rows as $row) {
            $businessKey = $this->resolveBusinessKey($row, $datasetKey);
            if ($businessKey !== '') {
                $keys[] = $businessKey;
            }
        }

        if ($table === '' || $currentSlot === '' || $keys === []) {
            return [];
        }

        return $this->repository->countConsecutiveStreakByKeys($table, $keys, $currentSlot);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeUnavailableEmailSection(string $key, string $title): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'value' => '—',
            'available' => false,
            'dataset_key' => null,
            'total' => 0,
            'truncated' => false,
            'columns' => [],
            'rows' => [],
            'note' => 'Dataset belum tersedia di HSECM Monitoring.',
            'detail_url' => null,
            'action' => '',
            'tone' => 'muted',
            'needs_action' => false,
        ];
    }

    /**
     * Dataset pekerja baru belum punya kolom department/jabatan — lengkapi dari master employees by SID.
     * Department memakai jabatan_fungsional (kolom department belum ada di scrape).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function enrichPekerjaBaruWithEmployeeMeta(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $sids = $rows
            ->map(static fn (array $row): string => strtoupper(trim((string) ($row['sid_pelapor_all_karyawan'] ?? ''))))
            ->filter(static fn (string $sid): bool => $sid !== '')
            ->unique()
            ->values()
            ->all();

        if ($sids === []) {
            return $rows->map(static function (array $row): array {
                $row['department'] = $row['department'] ?? '';
                $row['jabatan_struktural'] = $row['jabatan_struktural'] ?? '';

                return $row;
            });
        }

        $employees = Employee::query()
            ->select(['kode_sid', 'jabatan_fungsional', 'jabatan_struktural'])
            ->whereIn('kode_sid', $sids)
            ->get()
            ->keyBy(static fn (Employee $employee): string => strtoupper(trim((string) $employee->kode_sid)));

        return $rows->map(static function (array $row) use ($employees): array {
            $sid = strtoupper(trim((string) ($row['sid_pelapor_all_karyawan'] ?? '')));
            $employee = $sid !== '' ? $employees->get($sid) : null;

            $row['department'] = trim((string) ($employee?->jabatan_fungsional ?? $row['department'] ?? ''));
            $row['jabatan_struktural'] = trim((string) ($employee?->jabatan_struktural ?? $row['jabatan_struktural'] ?? ''));

            return $row;
        });
    }

    /**
     * Detail metrik per perusahaan untuk email/WA notify (selaras KPI dashboard).
     *
     * @param  array{site: string, perusahaan: string, week: string, year: string, date_from?: string, date_to?: string, q: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function buildCompanyDetailForNotify(array $filters): array
    {
        $companies = $this->buildCompanyMonitoring($filters);
        $scopedCompany = trim((string) ($filters['perusahaan'] ?? ''));

        if ($scopedCompany !== '') {
            $companies = collect($companies)
                ->filter(function (array $row) use ($scopedCompany): bool {
                    return strcasecmp((string) ($row['perusahaan'] ?? ''), $scopedCompany) === 0;
                })
                ->values()
                ->all();
        }

        return collect($companies)->map(function (array $row): array {
            return [
                'perusahaan' => (string) ($row['perusahaan'] ?? '-'),
                'metrics' => [
                    [
                        'key' => 'sap_rfid',
                        'label' => 'Layer 1 Tanpa SAP',
                        'value' => (int) ($row['sap_rfid'] ?? 0),
                        'hint' => 'Jumlah baris SAP = 0',
                        'format' => 'number',
                    ],
                    [
                        'key' => 'tbc_blindspot',
                        'label' => 'TBC Blindspot',
                        'value' => (int) ($row['tbc_blindspot'] ?? 0),
                        'hint' => 'Kasus blindspot',
                        'format' => 'number',
                    ],
                    [
                        'key' => 'task_overdue',
                        'label' => 'Task Overdue',
                        'value' => (int) ($row['task_overdue'] ?? 0),
                        'hint' => 'Follow-up terlambat',
                        'format' => 'number',
                    ],
                    [
                        'key' => 'task_submitted',
                        'label' => 'Submitted >24jam',
                        'value' => (int) ($row['task_submitted'] ?? 0),
                        'hint' => 'Closing hazard terlambat',
                        'format' => 'number',
                    ],
                    [
                        'key' => 'ikk',
                        'label' => 'Jumlah IKK',
                        'value' => (int) ($row['ikk'] ?? 0),
                        'hint' => 'IKK aktif (baris)',
                        'format' => 'number',
                    ],
                    [
                        'key' => 'avg_ikk',
                        'label' => 'IKK Compliance',
                        'value' => (float) ($row['avg_ikk'] ?? 0),
                        'hint' => 'Work permit',
                        'format' => 'percent',
                    ],
                    [
                        'key' => 'aggregator',
                        'label' => 'Aggregator Fill',
                        'value' => (int) ($row['aggregator'] ?? 0),
                        'hint' => 'Jumlah tidak mengisi aggregator',
                        'format' => 'number',
                    ],
                    [
                        'key' => 'fatigue',
                        'label' => 'FTW Merah',
                        'value' => (int) ($row['fatigue'] ?? 0),
                        'hint' => 'Jumlah FTW merah (agregat)',
                        'format' => 'number',
                    ],
                    [
                        'key' => 'sumber_rfid',
                        'label' => 'Jumlah Pekerja Baru',
                        'value' => (int) ($row['sumber_rfid'] ?? 0),
                        'hint' => 'RFID pekerja baru',
                        'format' => 'number',
                    ],
                ],
                'total_records' => (int) ($row['total_records'] ?? 0),
            ];
        })->values()->all();
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
        return isset(self::DATASETS[$datasetKey]) && $this->isDatasetVisible($datasetKey);
    }

    /**
     * Dataset yang ditampilkan di UI (abaikan flag hidden).
     *
     * @return array<string, array<string, mixed>>
     */
    private function visibleDatasets(): array
    {
        return array_filter(
            self::DATASETS,
            static fn (array $meta): bool => ! (bool) ($meta['hidden'] ?? false)
        );
    }

    /**
     * Dataset untuk Gap Perulangan — exclude Implementasi IKK & Pekerja Baru (RFID).
     *
     * @return array<string, array<string, mixed>>
     */
    private function gapPerulanganDatasets(): array
    {
        $excluded = [
            'implementasi-ikk' => true,
            'sumber-rfid' => true,
        ];

        return array_filter(
            $this->visibleDatasets(),
            static fn (array $meta, string $key): bool => ! isset($excluded[$key]),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function isDatasetVisible(string $datasetKey): bool
    {
        if (! isset(self::DATASETS[$datasetKey])) {
            return false;
        }

        return ! (bool) (self::DATASETS[$datasetKey]['hidden'] ?? false);
    }

    /**
     * @return array{sites: list<string>, companies: list<string>, weeks: list<string>, years: list<string>}
     */
    private function buildFilterOptions(): array
    {
        return Cache::remember('hsecm.filter_options.db.v3', 300, function (): array {
            $sites = collect();
            $companies = collect();
            $weeks = collect();
            $years = collect();

            foreach ($this->visibleDatasets() as $key => $meta) {
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
        // Layer 1 tanpa SAP = jumlah (count) baris dengan SAP_per_SID masih 0.
        $layer1TanpaSap = $this->countRowsWithSapZero($this->filteredRows('sap-rfid', $filters));
        $coverageCount = $this->filteredRows('coverage-cctv', $filters)->count();
        $tbcCount = $this->filteredRows('tbc-blindspot', $filters)->count();
        $overdueCount = $this->filteredRows('task-overdue', $filters)->count();
        $submittedRows = $this->filteredRows('task-submitted', $filters);
        $submittedCount = $submittedRows->count();
        $avgSubmitHours = $this->avgNumeric($submittedRows, 'Selisih_jam_dari_Submit');
        $ikkRows = $this->filteredRows('ikk-work-permit', $filters);
        $ikkCount = $ikkRows->count();
        $avgIkk = $this->avgPercent($ikkRows, 'Compliance_IKK');
        $aggregatorCount = $this->filteredRows('aggregator', $filters)->count();
        // FTW Merah = jumlah (agregat) seluruh baris FTW merah.
        $ftwMerah = $this->filteredRows('fatigue', $filters)->count();
        $sumberCount = $this->filteredRows('sumber-rfid', $filters)->count();
        $hazardRootcauseCount = $this->filteredRows('hazard-rootcause', $filters)->count();

        return [
            [
                'label' => 'Layer 1 tanpa SAP',
                'value' => $layer1TanpaSap,
                'icon' => 'analytics',
                'hint' => 'Jumlah baris SAP = 0',
                'tone' => $layer1TanpaSap > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Coverage Area Kritis',
                'value' => $coverageCount,
                'icon' => 'videocam',
                'hint' => 'Area kritis belum tercover',
                'tone' => $coverageCount > 0 ? 'danger' : 'success',
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
                'value' => $aggregatorCount,
                'icon' => 'person_off',
                'hint' => 'Jumlah tidak mengisi aggregator',
                'tone' => $aggregatorCount > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'FTW Merah',
                'value' => $ftwMerah,
                'icon' => 'bedtime',
                'hint' => 'Jumlah FTW merah (agregat)',
                'tone' => $ftwMerah > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Jumlah pekerja baru',
                'value' => $sumberCount,
                'icon' => 'database',
                'hint' => 'RFID pekerja baru',
                'tone' => 'primary',
            ],
            [
                'label' => 'Hazard Related Incident',
                'value' => $hazardRootcauseCount,
                'icon' => 'report',
                'hint' => 'Belum terlaporkan (batch terkini)',
                'tone' => $hazardRootcauseCount > 0 ? 'danger' : 'success',
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
            'hazard-rootcause' => 'hazard_rootcause',
        ];

        /** @var Collection<string, array<string, int|float|string>> $rows */
        $rows = collect();

        foreach ($map as $key => $field) {
            if (! $this->isDatasetVisible($key)) {
                continue;
            }
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
                    'hazard_rootcause' => 0,
                    'avg_ikk' => 0,
                    'avg_aggregator' => 0,
                ]);

                $count = $key === 'sap-rfid'
                    ? $this->countRowsWithSapZero($items)
                    : $items->count();
                $current[$field] = $count;
                $current['total_records'] = (int) $current['total_records'] + $count;

                if ($key === 'ikk-work-permit') {
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

                $count = $key === 'sap-rfid'
                    ? $this->countRowsWithSapZero($items)
                    : $items->count();
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
     * Item gap siap dijadikan tasklist (per baris + business_key).
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{
     *     program_key: string,
     *     title: string,
     *     business_key: string,
     *     action_hint: string,
     *     value_label: string,
     *     payload: array<string, mixed>
     * }>
     */
    public function extractTasklistItemsFromGaps(array $filters): array
    {
        return array_map(
            static function (array $row): array {
                return [
                    'program_key' => $row['program_key'],
                    'title' => $row['title'],
                    'business_key' => $row['business_key'],
                    'action_hint' => $row['action_hint'],
                    'value_label' => $row['value_label'],
                    'payload' => $row['payload'],
                ];
            },
            $this->extractGapIdentityRows($filters)
        );
    }

    /**
     * Gap action items + identitas scope (untuk evaluasi day-over-day / tasklist).
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{
     *     identity: string,
     *     program_key: string,
     *     title: string,
     *     business_key: string,
     *     action_hint: string,
     *     value_label: string,
     *     site: string,
     *     perusahaan: string,
     *     dataset_key: string,
     *     table: string,
     *     payload: array<string, mixed>
     * }>
     */
    public function extractGapIdentityRows(array $filters): array
    {
        // Path lean: tanpa buildEmailNarrative (mahal). Digunakan Gap Evaluasi & tasklist.
        return $this->extractGapIdentityRowsLean($filters);
    }

    /**
     * Versi ringan: langsung iterasi dataset gap, tanpa narrative email.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function extractGapIdentityRowsLean(array $filters): array
    {
        $programs = [
            'sap-rfid' => ['key' => 'layer1-tanpa-sap', 'title' => 'Layer 1 Tanpa SAP', 'action' => 'Coaching oleh atasan langsung'],
            'coverage-cctv' => ['key' => 'coverage-area', 'title' => 'Area Kritis belum tercover SAP', 'action' => 'Pemenuhan SAP di shift berikutnya'],
            'tbc-blindspot' => ['key' => 'tbc-blindspot', 'title' => 'TBC Blindspot', 'action' => 'Wajib dilaksanakan Peer Pressure'],
            'task-overdue' => ['key' => 'hazard-overdue', 'title' => 'Hazard Overdue', 'action' => 'Coaching oleh atasan langsung'],
            'task-submitted' => ['key' => 'hazard-submitted', 'title' => 'Hazard Submitted > 24 jam', 'action' => 'Tindaklanjut tasklist'],
            'ikk-work-permit' => ['key' => 'ikk-compliance', 'title' => 'IKK Compliance', 'action' => 'Suspend IKK & Coaching'],
            'aggregator' => ['key' => 'aggregator-fill', 'title' => 'Tidak mengisi Aggregator Fit to Work', 'action' => 'Evidence operator tanpa Aggregator'],
            'fatigue' => ['key' => 'ftw-merah', 'title' => 'Aggregator Fit to Work Merah', 'action' => 'Stop Operasi & istirahatkan operator'],
            'hazard-rootcause' => ['key' => 'hazard-rootcause', 'title' => 'Hazard Related Rootcause Incident', 'action' => 'Laporkan / tindaklanjuti rootcause'],
        ];

        $items = [];
        foreach ($programs as $datasetKey => $metaProg) {
            if (! isset(self::DATASETS[$datasetKey]) || ! $this->isDatasetVisible($datasetKey)) {
                continue;
            }

            $meta = self::DATASETS[$datasetKey];
            $programKey = $metaProg['key'];
            $siteColumn = (string) ($meta['site_column'] ?? '');
            $companyColumn = $meta['company_column'] !== null ? (string) $meta['company_column'] : '';

            $rows = $this->filteredRows($datasetKey, $filters);
            if ($programKey === 'layer1-tanpa-sap') {
                $rows = $rows->filter(function (array $row): bool {
                    if ($this->isAllToken($row['SAP_per_SID'] ?? null)) {
                        return false;
                    }
                    $sap = $this->toFloat($row['SAP_per_SID'] ?? null);

                    return $sap !== null && abs($sap) < 0.00001;
                })->values();
            }
            if ($programKey === 'ikk-compliance') {
                $rows = $this->filterIkkBelowFullCompliance($rows);
                if ($rows->isEmpty()) {
                    continue;
                }
            }

            $rows = $this->dedupeEmailRows($rows, $datasetKey);

            foreach ($rows as $row) {
                $businessKey = $this->resolveBusinessKey($row, $datasetKey);
                $site = $siteColumn !== '' ? trim((string) ($row[$siteColumn] ?? '')) : '';
                $perusahaan = $companyColumn !== '' ? trim((string) ($row[$companyColumn] ?? '')) : '';
                if ($site === '' && trim((string) ($filters['site'] ?? '')) !== '') {
                    $site = trim((string) $filters['site']);
                }
                if ($perusahaan === '' && trim((string) ($filters['perusahaan'] ?? '')) !== '') {
                    $perusahaan = trim((string) $filters['perusahaan']);
                }

                $identity = strtolower($programKey).'|'.$businessKey.'|'.strtolower($site).'|'.strtolower($perusahaan);

                $items[] = [
                    'identity' => $identity,
                    'program_key' => $programKey,
                    'title' => $metaProg['title'],
                    'business_key' => $businessKey,
                    'action_hint' => $metaProg['action'],
                    'value_label' => $this->buildItemValueLabel($row, $datasetKey),
                    'site' => $site,
                    'perusahaan' => $perusahaan,
                    'dataset_key' => $datasetKey,
                    'table' => (string) $meta['table'],
                    'payload' => [
                        'dataset_key' => $datasetKey,
                        'table' => $meta['table'],
                        'batch_slot' => $row['batch_slot'] ?? ($filters['batch_slot'] ?? null),
                        'gap_count' => $row['gap_count'] ?? null,
                        'row_id' => $row['_row_id'] ?? null,
                    ],
                ];
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveBusinessKey(array $row, string $datasetKey): string
    {
        $fromCol = trim((string) ($row['business_key'] ?? ''));
        if ($fromCol !== '') {
            return $fromCol;
        }

        $candidates = match ($datasetKey) {
            'sap-rfid', 'sumber-rfid' => [
                $row['sid_pelapor_all_karyawan'] ?? null,
                $row['date'] ?? null,
            ],
            'task-overdue', 'task-submitted' => [
                $row['Task_Number'] ?? null,
            ],
            'ikk-work-permit', 'implementasi-ikk' => [
                $row['Code'] ?? null,
            ],
            'coverage-cctv' => [
                $row['Site'] ?? null,
                $row['Lokasi'] ?? null,
                $row['Detil_Lokasi'] ?? null,
                $row['Day_of_Date'] ?? null,
            ],
            'tbc-blindspot' => [
                $row['site'] ?? null,
                $row['blindspot_TBC'] ?? null,
                $row['Date_for_Join'] ?? null,
            ],
            'aggregator', 'fatigue' => [
                $row['Kode_Sid'] ?? null,
                $row['Day_of_Tanggal_Date'] ?? ($row['Tanggal_Date'] ?? null),
            ],
            'hazard-rootcause' => [
                $row['Site'] ?? null,
                $row['Lokasi'] ?? null,
                $row['Detail_Lokasi'] ?? null,
                $row['Ketidaksesuaian'] ?? null,
                $row['Sub_Ketidaksesuaian'] ?? null,
                $row['Week'] ?? null,
            ],
            default => [$row['_row_id'] ?? null],
        };

        $parts = [];
        foreach ($candidates as $part) {
            $text = trim((string) ($part ?? ''));
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        if ($parts === []) {
            return $datasetKey.':'.(string) ($row['_row_id'] ?? uniqid('row_', true));
        }

        return $datasetKey.':'.implode('|', $parts);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buildItemValueLabel(array $row, string $datasetKey): string
    {
        return match ($datasetKey) {
            'sap-rfid' => trim((string) ($row['pelapor_all_karyawan'] ?? $row['sid_pelapor_all_karyawan'] ?? '')),
            'task-overdue', 'task-submitted' => trim((string) ($row['Task_Number'] ?? '')),
            'ikk-work-permit' => trim((string) ($row['Code'] ?? $row['Name_Ikk_Work_Permit'] ?? '')),
            'coverage-cctv' => trim(implode(' · ', array_filter([
                (string) ($row['Lokasi'] ?? ''),
                (string) ($row['Detil_Lokasi'] ?? ''),
            ]))),
            'tbc-blindspot' => trim((string) ($row['blindspot_TBC'] ?? $row['kategori_TBC'] ?? '')),
            'aggregator', 'fatigue' => trim((string) ($row['Nama'] ?? $row['Kode_Sid'] ?? '')),
            'hazard-rootcause' => trim(implode(' · ', array_filter([
                (string) ($row['Lokasi'] ?? ''),
                (string) ($row['Detail_Lokasi'] ?? ''),
                (string) ($row['Sub_Ketidaksesuaian'] ?? ''),
            ]))),
            default => (string) ($row['_row_id'] ?? ''),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function buildItemPreviewCells(array $row, string $datasetKey): array
    {
        $meta = self::DATASETS[$datasetKey];
        $keys = array_slice(array_keys($meta['columns']), 0, 6);
        $dateColumn = is_string($meta['date_column'] ?? null) ? (string) $meta['date_column'] : null;
        $out = [];
        foreach ($keys as $key) {
            if ($dateColumn !== null && $key === $dateColumn) {
                $out[$key] = $this->formatEmailDateCell($row, $dateColumn);
                continue;
            }
            $text = trim((string) ($row[$key] ?? ''));
            $out[$key] = $text !== '' ? (mb_strlen($text) > 80 ? mb_substr($text, 0, 77).'...' : $text) : '—';
        }

        return $out;
    }

    /**
     * Tanggal untuk email/preview: kolom bisnis dulu, fallback batch_slot / scraped_at
     * (scrape L1 tanpa SAP H-1 sering mengosongkan kolom date).
     *
     * @param  array<string, mixed>  $row
     */
    private function formatEmailDateCell(array $row, string $dateColumn): string
    {
        $raw = $row[$dateColumn] ?? null;
        $text = is_scalar($raw) || $raw === null ? trim((string) ($raw ?? '')) : '';
        if ($text !== '' && ! $this->isAllToken($text)) {
            $ymd = $this->parseFlexibleDate($text);
            if ($ymd !== null) {
                return Carbon::parse($ymd)->format('d/m/Y');
            }

            return mb_strlen($text) > 80 ? mb_substr($text, 0, 77).'...' : $text;
        }

        foreach (['batch_slot', 'scraped_at'] as $fallbackKey) {
            $ymd = $this->parseFlexibleDate($row[$fallbackKey] ?? null);
            if ($ymd !== null) {
                return Carbon::parse($ymd)->format('d/m/Y');
            }
        }

        return '—';
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filteredRows(string $datasetKey, array $filters): Collection
    {
        $meta = self::DATASETS[$datasetKey];
        $dataMode = strtolower((string) ($filters['data_mode'] ?? 'snapshot'));
        $batchSlot = trim((string) ($filters['batch_slot'] ?? ''));
        $previousSlot = trim((string) ($filters['previous_batch_slot'] ?? ''));
        $hasDateFilter = ($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '';

        // Filter tanggal hanya relevan bila dataset punya date_column.
        // Tanpa date_column (mis. hazard-rootcause), tetap snapshot batch terkini.
        if (
            $hasDateFilter
            && $dataMode === 'snapshot'
            && $batchSlot === ''
            && ($meta['date_column'] ?? null) !== null
        ) {
            $dataMode = 'all';
        }

        if ($dataMode === 'all' || ! $this->repository->hasBatchSlotSupport($meta['table'])) {
            $rows = collect($this->repository->rows($meta['table']));
        } elseif ($dataMode === 'still_open') {
            $rows = collect($this->repository->rowsStillOpen(
                $meta['table'],
                $batchSlot !== '' ? $batchSlot : null,
                $previousSlot !== '' ? $previousSlot : null,
            ));
        } else {
            $rows = collect($this->repository->rowsForBatchSlot(
                $meta['table'],
                $batchSlot !== '' ? $batchSlot : null,
            ));
        }

        $filtered = $rows->filter(function (array $row) use ($meta, $filters): bool {
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

            $dateColumn = $meta['date_column'] ?? null;
            if ($dateColumn !== null && (($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '')) {
                $rowDate = $this->parseFlexibleDate($row[$dateColumn] ?? null);
                if ($rowDate === null) {
                    return false;
                }
                if (($filters['date_from'] ?? '') !== '' && $rowDate < $filters['date_from']) {
                    return false;
                }
                if (($filters['date_to'] ?? '') !== '' && $rowDate > $filters['date_to']) {
                    return false;
                }
            }

            if (($filters['q'] ?? '') !== '') {
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

        // Mode all + filter tanggal bisa menumpuk batch_slot yang sama → dedupe identity.
        if ($hasDateFilter && $dataMode === 'all') {
            return $this->dedupeRowsByBusinessKey($filtered);
        }

        return $filtered;
    }

    /**
     * Tampilkan perulangan = ceil(total_slot / 2).
     * 2 batch_slot per hari; jika ganjil diganjilkan dulu (5 → 6 → 3).
     */
    private function toPerulanganDisplayCount(int $slotStreak): int
    {
        if ($slotStreak <= 0) {
            return 0;
        }

        return (int) ceil($slotStreak / 2);
    }

    /**
     * Unique baris email: 1 business_key (atau fingerprint kolom) = 1 baris.
     * Pertahankan row dengan gap_count / id tertinggi.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function dedupeEmailRows(Collection $rows, string $datasetKey): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $meta = self::DATASETS[$datasetKey] ?? null;
        $columnKeys = is_array($meta['columns'] ?? null) ? array_keys($meta['columns']) : [];

        return $rows
            ->sortBy([
                [static fn (array $row): int => (int) ($row['gap_count'] ?? 0), 'desc'],
                [static fn (array $row): int => (int) ($row['id'] ?? $row['_row_id'] ?? 0), 'desc'],
            ])
            ->unique(function (array $row) use ($datasetKey, $columnKeys): string {
                $businessKey = $this->resolveBusinessKey($row, $datasetKey);
                if ($businessKey !== '') {
                    return 'bk:'.$businessKey;
                }

                $parts = [];
                foreach ($columnKeys as $columnKey) {
                    $raw = $row[$columnKey] ?? '';
                    $parts[] = $columnKey.'='.(is_scalar($raw) || $raw === null ? trim((string) ($raw ?? '')) : '');
                }

                if ($parts !== []) {
                    return 'fp:'.md5(implode('|', $parts));
                }

                return 'id:'.(string) ($row['id'] ?? $row['_row_id'] ?? uniqid('row_', true));
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function dedupeRowsByBusinessKey(Collection $rows): Collection
    {
        return $rows
            ->sortByDesc(static fn (array $row): int => (int) ($row['id'] ?? $row['_row_id'] ?? 0))
            ->unique(function (array $row): string {
                $key = trim((string) ($row['business_key'] ?? ''));
                if ($key !== '') {
                    return $key;
                }

                return 'id:'.(string) ($row['id'] ?? $row['_row_id'] ?? uniqid('row_', true));
            })
            ->values();
    }

    /**
     * Normalisasi nilai tanggal row ke Y-m-d (null jika tidak bisa diparse).
     * Tanggal slash dari Tableau memakai format US m/d/Y (contoh: 7/22/2026).
     */
    private function parseFlexibleDate(mixed $value): ?string
    {
        if ($value === null || $this->isAllToken($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))->format('Y-m-d');
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // Epoch / numeric timestamp (detik)
        if (ctype_digit($raw) && strlen($raw) >= 10) {
            try {
                return Carbon::createFromTimestamp((int) $raw)->format('Y-m-d');
            } catch (\Throwable) {
                // continue
            }
        }

        // 7/22/2026 atau 7/22/2026 6:00:00 AM — utamakan m/d/Y (Tableau), hindari overflow d/m/Y.
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\b.*)?$/', $raw, $m) === 1) {
            $first = (int) $m[1];
            $second = (int) $m[2];
            $year = (int) $m[3];

            if ($second > 12 && $first >= 1 && $first <= 12) {
                $month = $first;
                $day = $second;
            } elseif ($first > 12 && $second >= 1 && $second <= 12) {
                $day = $first;
                $month = $second;
            } else {
                // Ambigu (keduanya ≤ 12): data scrap HSECM = US m/d/Y.
                $month = $first;
                $day = $second;
            }

            if (! checkdate($month, $day, $year)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        $formats = [
            'Y-m-d',
            'Y-m-d H:i:s',
            'd-m-Y',
            'd M Y',
            'F j, Y',
            'M j, Y',
            'Y/m/d',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat('!'.$format, $raw);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                // try next
            }
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
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
     * Hitung baris Layer 1 tanpa SAP: SAP_per_SID masih 0.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function countRowsWithSapZero(Collection $rows): int
    {
        return $rows->filter(function (array $row): bool {
            if ($this->isAllToken($row['SAP_per_SID'] ?? null)) {
                return false;
            }

            $sap = $this->toFloat($row['SAP_per_SID'] ?? null);

            return $sap !== null && abs($sap) < 0.00001;
        })->count();
    }

    /**
     * IKK dengan Compliance_IKK di bawah 100% (ratio 0–1 atau persen 0–100).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function filterIkkBelowFullCompliance(Collection $rows): Collection
    {
        return $rows
            ->filter(function (array $row): bool {
                $num = $this->toFloat($row['Compliance_IKK'] ?? null);
                if ($num === null) {
                    return true;
                }

                // Scraped sebagai ratio (1 = 100%); toleransi jika sudah dalam persen.
                if ($num > 1.0001) {
                    return $num < 100;
                }

                return $num < 0.999999;
            })
            ->values();
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

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, date_from: string, date_to: string, q: string}  $filters
     */
    private function buildPeriodLabel(array $filters): string
    {
        $parts = [];

        if ($filters['date_from'] !== '' || $filters['date_to'] !== '') {
            $from = $filters['date_from'] !== ''
                ? Carbon::parse($filters['date_from'])->format('d M Y')
                : 'awal';
            $to = $filters['date_to'] !== ''
                ? Carbon::parse($filters['date_to'])->format('d M Y')
                : 'akhir';
            $parts[] = $from.' – '.$to;
        }

        if ($filters['week'] !== '') {
            $parts[] = 'Week '.$filters['week'];
        }
        if ($filters['year'] !== '') {
            $parts[] = 'Year '.$filters['year'];
        }

        if ($parts === []) {
            return 'Semua periode (belum ada filter tanggal / week / year)';
        }

        return implode(' · ', $parts);
    }

    private function isValidYmd(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $value);

            return $parsed !== null && $parsed->format('Y-m-d') === $value;
        } catch (\Throwable) {
            return false;
        }
    }
}
