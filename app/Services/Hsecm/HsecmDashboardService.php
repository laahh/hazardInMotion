<?php

declare(strict_types=1);

namespace App\Services\Hsecm;

use App\Models\Hsecm\HsecmAggregatorPengisian;
use App\Models\Hsecm\HsecmCoverageAreaCctv;
use App\Models\Hsecm\HsecmFatigueManagementCheck;
use App\Models\Hsecm\HsecmIkkWorkPermit;
use App\Models\Hsecm\HsecmSapRfidPelaporHarian;
use App\Models\Hsecm\HsecmSumberDataRfidPelapor;
use App\Models\Hsecm\HsecmTaskFollowupOverdue;
use App\Models\Hsecm\HsecmTaskFollowupSubmitted;
use App\Models\Hsecm\HsecmTbcBlindspot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HsecmDashboardService
{
    public const DATASETS = [
        'sap-rfid' => [
            'label' => 'SAP / RFID Pelapor',
            'icon' => 'badge',
            'model' => HsecmSapRfidPelaporHarian::class,
            'site_column' => 'site_dedicated_pelapor_all_karyawan',
            'company_column' => 'perusahaan_pelapor_all_karyawan',
            'week_column' => 'week_of_date',
            'year_column' => 'year_of_date',
            'columns' => [
                'date' => 'Tanggal',
                'sid_pelapor_all_karyawan' => 'SID',
                'pelapor_all_karyawan' => 'Pelapor',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan',
                'site_dedicated_pelapor_all_karyawan' => 'Site',
                'layer_pelapor' => 'Layer',
                'jabatan_struktural_pelapor_all_karyawan' => 'Jabatan Struktural',
                'rfid_per_sid' => 'RFID / SID',
                'sap_per_sid' => 'SAP / SID',
                'week_of_date' => 'Week',
                'year_of_date' => 'Year',
            ],
        ],
        'coverage-cctv' => [
            'label' => 'Coverage Area CCTV',
            'icon' => 'videocam',
            'model' => HsecmCoverageAreaCctv::class,
            'site_column' => 'site',
            'company_column' => null,
            'week_column' => 'week_of_date',
            'year_column' => 'year_of_date',
            'columns' => [
                'site' => 'Site',
                'lokasi' => 'Lokasi',
                'detil_lokasi' => 'Detil Lokasi',
                'status_coverage_dalam_1_week' => 'Status Coverage',
                'pct_tercover' => '% Tercover',
                'day_of_date' => 'Day',
                'week_of_date' => 'Week',
                'year_of_date' => 'Year',
            ],
        ],
        'tbc-blindspot' => [
            'label' => 'TBC Blindspot',
            'icon' => 'visibility_off',
            'model' => HsecmTbcBlindspot::class,
            'site_column' => 'site',
            'company_column' => 'perusahaan_pelapor_all_karyawan',
            'week_column' => 'week_of_date_for_join',
            'year_column' => 'year_of_date_for_join',
            'columns' => [
                'site' => 'Site',
                'kategori_tbc' => 'Kategori TBC',
                'blindspot_tbc' => 'Blindspot TBC',
                'deskripsi' => 'Deskripsi',
                'pelapor_all_karyawan' => 'Pelapor',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan Pelapor',
                'pic' => 'PIC',
                'perusahaan_pic' => 'Perusahaan PIC',
                'status3' => 'Status',
                'validasi_gr' => 'Validasi GR',
                'week_of_date_for_join' => 'Week',
                'year_of_date_for_join' => 'Year',
            ],
        ],
        'task-overdue' => [
            'label' => 'Task Follow-up Overdue',
            'icon' => 'schedule',
            'model' => HsecmTaskFollowupOverdue::class,
            'site_column' => 'site',
            'company_column' => 'perusahaan_pelapor_all_karyawan',
            'week_column' => 'week_of_date_time',
            'year_column' => 'year_of_date_time',
            'columns' => [
                'no_task_number' => 'No Task',
                'site' => 'Site',
                'deskripsi' => 'Deskripsi',
                'pelapor_all_karyawan' => 'Pelapor',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan Pelapor',
                'pic' => 'PIC',
                'perusahaan_pic' => 'Perusahaan PIC',
                'status3' => 'Status',
                'tanggal_janji' => 'Tanggal Janji',
                'week_of_date_time' => 'Week',
                'year_of_date_time' => 'Year',
            ],
        ],
        'task-submitted' => [
            'label' => 'Task Follow-up Submitted',
            'icon' => 'task_alt',
            'model' => HsecmTaskFollowupSubmitted::class,
            'site_column' => 'site',
            'company_column' => 'perusahaan_pelapor_all_karyawan',
            'week_column' => 'week_of_date_time',
            'year_column' => 'year_of_date_time',
            'columns' => [
                'no_task_number' => 'No Task',
                'site' => 'Site',
                'deskripsi' => 'Deskripsi',
                'pelapor_all_karyawan' => 'Pelapor',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan Pelapor',
                'pic' => 'PIC',
                'perusahaan_pic' => 'Perusahaan PIC',
                'status3' => 'Status',
                'selisih_jam_dari_submit' => 'Selisih Jam',
                'week_of_date_time' => 'Week',
                'year_of_date_time' => 'Year',
            ],
        ],
        'ikk-work-permit' => [
            'label' => 'IKK Work Permit',
            'icon' => 'description',
            'model' => HsecmIkkWorkPermit::class,
            'site_column' => 'ra_site_name',
            'company_column' => 'company_name_ikk_work_permit',
            'week_column' => 'week_of_start_date_convert',
            'year_column' => 'iso_year_of_start_date_convert',
            'columns' => [
                'code' => 'Code',
                'name_ikk_work_permit' => 'Nama IKK',
                'company_name_ikk_work_permit' => 'Perusahaan',
                'ra_site_name' => 'Site',
                'location_name' => 'Lokasi',
                'location_detail_name' => 'Detil Lokasi',
                'status' => 'Status',
                'status_ikk_work_permit_pic' => 'Status PIC',
                'pct_compliance_ikk' => '% Compliance',
                'start_date_convert' => 'Start Date',
                'week_of_start_date_convert' => 'Week',
                'iso_year_of_start_date_convert' => 'Year',
            ],
        ],
        'aggregator' => [
            'label' => 'Aggregator Pengisian',
            'icon' => 'percent',
            'model' => HsecmAggregatorPengisian::class,
            'site_column' => 'site_dedicated',
            'company_column' => 'nama_perusahaan',
            'week_column' => 'week_of_tanggal_date',
            'year_column' => 'year_of_tanggal_date',
            'columns' => [
                'kode_sid' => 'SID',
                'nama' => 'Nama',
                'nama_perusahaan' => 'Perusahaan',
                'site_dedicated' => 'Site',
                'jabatan_struktural' => 'Jabatan Struktural',
                'pct_pengisian_aggregator' => '% Pengisian',
                'day_of_tanggal_date' => 'Day',
                'week_of_tanggal_date' => 'Week',
                'year_of_tanggal_date' => 'Year',
            ],
        ],
        'fatigue' => [
            'label' => 'Fatigue Management Check',
            'icon' => 'bedtime',
            'model' => HsecmFatigueManagementCheck::class,
            'site_column' => 'site_dedicated',
            'company_column' => 'nama_perusahaan',
            'week_column' => 'week_of_tanggal_date',
            'year_column' => 'year_of_tanggal_date',
            'columns' => [
                'tanggal_date' => 'Tanggal',
                'kode_sid' => 'SID',
                'nama' => 'Nama',
                'nama_perusahaan' => 'Perusahaan',
                'site_dedicated' => 'Site',
                'kondisi_karyawan' => 'Kondisi',
                'ftw_merah' => 'FTW Merah',
                'jumlah_jam_tidur' => 'Jam Tidur',
                'hasil_sobriety_test' => 'Sobriety',
                'klasifikasi_tekanan_darah' => 'Tekanan Darah',
                'week_of_tanggal_date' => 'Week',
                'year_of_tanggal_date' => 'Year',
            ],
        ],
        'sumber-rfid' => [
            'label' => 'Sumber Data RFID Pelapor',
            'icon' => 'database',
            'model' => HsecmSumberDataRfidPelapor::class,
            'site_column' => 'site_dedicated',
            'company_column' => 'perusahaan_pelapor_all_karyawan',
            'week_column' => 'week_of_date',
            'year_column' => 'year_of_date',
            'columns' => [
                'date' => 'Tanggal',
                'sid_pelapor_all_karyawan' => 'SID',
                'nama' => 'Nama',
                'perusahaan_pelapor_all_karyawan' => 'Perusahaan',
                'site_dedicated' => 'Site',
                'sumber_data' => 'Sumber Data',
                'tanggal_hari_pertama' => 'Hari Pertama Lapor',
                'week_of_date' => 'Week',
                'year_of_date' => 'Year',
            ],
        ],
    ];

    /**
     * @return array{site: string, perusahaan: string, week: string, year: string, q: string}
     */
    public function resolveFilters(Request $request): array
    {
        return [
            'site' => trim((string) $request->query('site', '')),
            'perusahaan' => trim((string) $request->query('perusahaan', '')),
            'week' => trim((string) $request->query('week', '')),
            'year' => trim((string) $request->query('year', '')),
            'q' => trim((string) $request->query('q', '')),
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
                'count' => $this->filteredQuery($key, $filters)->count(),
            ])->values()->all(),
        ];
    }

    /**
     * Ringkasan KPI untuk scope site/perusahaan (dipakai WA notify).
     *
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
                'count' => $this->filteredQuery($key, $filters)->count(),
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
        return Cache::remember('hsecm.filter_options.v1', 300, function (): array {
            $sites = collect();
            $companies = collect();
            $weeks = collect();
            $years = collect();

            foreach (self::DATASETS as $meta) {
                /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
                $model = $meta['model'];
                $siteCol = $meta['site_column'];
                $companyCol = $meta['company_column'];
                $weekCol = $meta['week_column'];
                $yearCol = $meta['year_column'];

                $sites = $sites->merge(
                    $model::query()
                        ->whereNotNull($siteCol)
                        ->where($siteCol, '!=', '')
                        ->distinct()
                        ->pluck($siteCol)
                );

                if ($companyCol !== null) {
                    $companies = $companies->merge(
                        $model::query()
                            ->whereNotNull($companyCol)
                            ->where($companyCol, '!=', '')
                            ->distinct()
                            ->pluck($companyCol)
                    );
                }

                $weeks = $weeks->merge(
                    $model::query()
                        ->whereNotNull($weekCol)
                        ->where($weekCol, '!=', '')
                        ->distinct()
                        ->pluck($weekCol)
                );

                $years = $years->merge(
                    $model::query()
                        ->whereNotNull($yearCol)
                        ->where($yearCol, '!=', '')
                        ->where($yearCol, '!=', 'All')
                        ->distinct()
                        ->pluck($yearCol)
                );
            }

            return [
                'sites' => $sites->unique()->sort()->values()->all(),
                'companies' => $companies->unique()->sort()->values()->all(),
                'weeks' => $weeks->unique()->sort(SORT_NATURAL)->values()->all(),
                'years' => $years->map(fn ($y) => (string) $y)->unique()->sort()->values()->all(),
            ];
        });
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     * @return list<array{label: string, value: string|int|float, icon: string, hint: string, tone: string}>
     */
    private function buildKpis(array $filters): array
    {
        $sapQuery = $this->filteredQuery('sap-rfid', $filters)
            ->where('sap_per_sid', '!=', 'All')
            ->where('rfid_per_sid', '!=', 'All');

        $avgSap = (float) (clone $sapQuery)->selectRaw('AVG(CAST(sap_per_sid AS DECIMAL(12,2))) as avg_val')->value('avg_val');
        $avgRfid = (float) (clone $sapQuery)->selectRaw('AVG(CAST(rfid_per_sid AS DECIMAL(12,2))) as avg_val')->value('avg_val');

        $avgCoverage = (float) $this->filteredQuery('coverage-cctv', $filters)->avg('pct_tercover');
        $tbcCount = $this->filteredQuery('tbc-blindspot', $filters)->count();
        $overdueCount = $this->filteredQuery('task-overdue', $filters)->count();
        $submittedCount = $this->filteredQuery('task-submitted', $filters)->count();
        $avgSubmitHours = (float) $this->filteredQuery('task-submitted', $filters)->avg('selisih_jam_dari_submit');
        $avgIkk = (float) $this->filteredQuery('ikk-work-permit', $filters)->avg('pct_compliance_ikk');
        $avgAggregator = (float) $this->filteredQuery('aggregator', $filters)->avg('pct_pengisian_aggregator');
        $fatigueCount = $this->filteredQuery('fatigue', $filters)->count();
        $ftwMerah = $this->filteredQuery('fatigue', $filters)
            ->whereNotNull('ftw_merah')
            ->where('ftw_merah', '!=', '')
            ->whereRaw('LOWER(ftw_merah) NOT IN (?, ?)', ['tidak', 'no'])
            ->count();
        $sumberCount = $this->filteredQuery('sumber-rfid', $filters)->count();

        return [
            [
                'label' => 'Avg SAP / SID',
                'value' => round($avgSap, 1),
                'icon' => 'analytics',
                'hint' => 'Pelapor harian',
                'tone' => 'primary',
            ],
            [
                'label' => 'Avg RFID / SID',
                'value' => round($avgRfid, 1),
                'icon' => 'contactless',
                'hint' => 'Pelapor harian',
                'tone' => 'primary',
            ],
            [
                'label' => 'CCTV Coverage',
                'value' => round($avgCoverage, 1).'%',
                'icon' => 'videocam',
                'hint' => 'Rata-rata % tercover',
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
                'label' => 'Task Submitted',
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
                'label' => 'Fatigue Check',
                'value' => $fatigueCount,
                'icon' => 'bedtime',
                'hint' => 'FTW merah: '.$ftwMerah,
                'tone' => $ftwMerah > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Sumber RFID',
                'value' => $sumberCount,
                'icon' => 'database',
                'hint' => 'Data pelapor RFID',
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
        $keysWithSite = ['sap-rfid', 'coverage-cctv', 'tbc-blindspot', 'task-overdue', 'task-submitted', 'ikk-work-permit', 'aggregator', 'fatigue', 'sumber-rfid'];

        /** @var Collection<string, array<string, int|float|string>> $rows */
        $rows = collect();

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

        foreach ($keysWithSite as $key) {
            $meta = self::DATASETS[$key];
            $siteCol = $meta['site_column'];

            $select = [$siteCol.' as site_name', DB::raw('COUNT(*) as total')];
            if ($key === 'coverage-cctv') {
                $select[] = DB::raw('AVG(pct_tercover) as avg_metric');
            } elseif ($key === 'ikk-work-permit') {
                $select[] = DB::raw('AVG(pct_compliance_ikk) as avg_metric');
            } elseif ($key === 'aggregator') {
                $select[] = DB::raw('AVG(pct_pengisian_aggregator) as avg_metric');
            }

            $grouped = $this->filteredQuery($key, array_merge($filters, ['site' => '']))
                ->select($select)
                ->whereNotNull($siteCol)
                ->where($siteCol, '!=', '')
                ->groupBy($siteCol)
                ->get();

            foreach ($grouped as $row) {
                $site = (string) $row->site_name;
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

                $field = $map[$key];
                $current[$field] = (int) $row->total;
                $current['total_records'] = (int) $current['total_records'] + (int) $row->total;

                if ($key === 'coverage-cctv') {
                    $current['avg_coverage'] = round((float) ($row->avg_metric ?? 0), 1);
                } elseif ($key === 'ikk-work-permit') {
                    $current['avg_ikk'] = round((float) ($row->avg_metric ?? 0), 1);
                } elseif ($key === 'aggregator') {
                    $current['avg_aggregator'] = round((float) ($row->avg_metric ?? 0), 1);
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
        $keysWithCompany = [
            'sap-rfid',
            'tbc-blindspot',
            'task-overdue',
            'task-submitted',
            'ikk-work-permit',
            'aggregator',
            'fatigue',
            'sumber-rfid',
        ];

        /** @var Collection<string, array<string, int|float|string>> $rows */
        $rows = collect();

        foreach ($keysWithCompany as $key) {
            $meta = self::DATASETS[$key];
            $companyCol = $meta['company_column'];
            if ($companyCol === null) {
                continue;
            }

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

            $select = [$companyCol.' as company_name', DB::raw('COUNT(*) as total')];
            if ($key === 'ikk-work-permit') {
                $select[] = DB::raw('AVG(pct_compliance_ikk) as avg_metric');
            } elseif ($key === 'aggregator') {
                $select[] = DB::raw('AVG(pct_pengisian_aggregator) as avg_metric');
            }

            $grouped = $this->filteredQuery($key, array_merge($filters, ['perusahaan' => '']))
                ->select($select)
                ->whereNotNull($companyCol)
                ->where($companyCol, '!=', '')
                ->groupBy($companyCol)
                ->get();

            foreach ($grouped as $row) {
                $company = (string) $row->company_name;
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

                $field = $map[$key];
                $current[$field] = (int) $row->total;
                $current['total_records'] = (int) $current['total_records'] + (int) $row->total;

                if ($key === 'ikk-work-permit') {
                    $current['avg_ikk'] = round((float) ($row->avg_metric ?? 0), 1);
                } elseif ($key === 'aggregator') {
                    $current['avg_aggregator'] = round((float) ($row->avg_metric ?? 0), 1);
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
        $query = $this->filteredQuery($datasetKey, $filters);
        $count = (clone $query)->count();

        $extra = match ($datasetKey) {
            'sap-rfid' => [
                'avg_sap' => round((float) (clone $query)->where('sap_per_sid', '!=', 'All')->selectRaw('AVG(CAST(sap_per_sid AS DECIMAL(12,2))) as v')->value('v'), 1),
                'avg_rfid' => round((float) (clone $query)->where('rfid_per_sid', '!=', 'All')->selectRaw('AVG(CAST(rfid_per_sid AS DECIMAL(12,2))) as v')->value('v'), 1),
            ],
            'coverage-cctv' => [
                'avg_pct' => round((float) (clone $query)->avg('pct_tercover'), 1),
            ],
            'task-submitted' => [
                'avg_hours' => round((float) (clone $query)->avg('selisih_jam_dari_submit'), 1),
            ],
            'ikk-work-permit' => [
                'avg_compliance' => round((float) (clone $query)->avg('pct_compliance_ikk'), 1),
            ],
            'aggregator' => [
                'avg_fill' => round((float) (clone $query)->avg('pct_pengisian_aggregator'), 1),
            ],
            default => [],
        };

        return array_merge(['total' => $count], $extra);
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     */
    private function paginateDataset(string $datasetKey, array $filters): LengthAwarePaginator
    {
        $meta = self::DATASETS[$datasetKey];
        $columns = array_keys($meta['columns']);

        return $this->filteredQuery($datasetKey, $filters)
            ->select(array_merge(['id'], $columns))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @param  array{site: string, perusahaan: string, week: string, year: string, q: string}  $filters
     */
    private function filteredQuery(string $datasetKey, array $filters): Builder
    {
        $meta = self::DATASETS[$datasetKey];
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = $meta['model'];

        $query = $model::query();

        if ($filters['site'] !== '') {
            $query->where($meta['site_column'], $filters['site']);
        }

        if ($filters['perusahaan'] !== '' && $meta['company_column'] !== null) {
            $query->where($meta['company_column'], $filters['perusahaan']);
        }

        if ($filters['week'] !== '') {
            $query->where($meta['week_column'], $filters['week']);
        }

        if ($filters['year'] !== '') {
            $query->where($meta['year_column'], $filters['year']);
        }

        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $query->where(function (Builder $builder) use ($meta, $like): void {
                $first = true;
                foreach (array_keys($meta['columns']) as $column) {
                    if ($first) {
                        $builder->where($column, 'like', $like);
                        $first = false;
                    } else {
                        $builder->orWhere($column, 'like', $like);
                    }
                }
            });
        }

        return $query;
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, 1);
    }
}
