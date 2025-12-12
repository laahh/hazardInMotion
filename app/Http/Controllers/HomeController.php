<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CctvData;
use Illuminate\Support\Facades\DB;
use App\Services\ClickHouseService;
use Exception;

class HomeController extends Controller
{
    /**
     * Display the dashboard index page
     */
    public function index()
    {
        // Coba ambil statistik dari ClickHouse, fallback ke PostgreSQL
        $overviewStats = $this->getOverviewStats();
        
        // Ambil statistik CCTV dari MySQL
        $cctvStats = $this->getCctvStatsFromMysql();

        // Ambil data OAK register - coba ClickHouse dulu, fallback ke PostgreSQL (range 24 Nov 2025 - 1 Des 2025)
        $oakData = [];
        try {
            $startDate = '2025-11-24 00:00:00';
            $endDate = '2025-12-01 23:59:59';
            
            // Coba dari ClickHouse terlebih dahulu
            $clickhouse = new ClickHouseService();
            if ($clickhouse->isConnected()) {
                try {
                    // Query OAK dari ClickHouse menggunakan view yang sudah dibuat
                    // View: nitip.oak_tabel_view sudah menggabungkan semua data yang diperlukan
                    $oakQueryClickHouse = "
                        SELECT 
                            `oak.id` as id,
                            mobile_uuid,
                            activity,
                            sub_activity,
                            material,
                            tool_type,
                            conveyance_type,
                            lifting_equipment,
                            site,
                            location,
                            detail_location,
                            location_description,
                            shift,
                            conclusion,
                            company_submit_by,
                            kode_sid_pelapor,
                            `submit_by.kode_sid` as kode_sid,
                            submit_by,
                            submit_id,
                            jabatan_fungsional_submiter,
                            `oak.submit_date` as submit_date,
                            sib_register,
                            code_sib,
                            tools_observasi,
                            id_employee_team,
                            nama_team,
                            kode_sid_team,
                            jabatan_fungsional_team,
                            tipe,
                            latitude,
                            longitude,
                            file_foto,
                            platform,
                            is_be_draft,
                            bedraft_date,
                            versi_apk,
                            apk,
                            method,
                            url_photo
                        FROM nitip.oak_tabel_view
                        WHERE `oak.submit_date` >= '{startDate}'
                            AND `oak.submit_date` <= '{endDate}'
                        ORDER BY `oak.submit_date` DESC
                        LIMIT 1000
                    ";
                    
                    $oakQueryClickHouse = str_replace(
                        ['{startDate}', '{endDate}'],
                        [$startDate, $endDate],
                        $oakQueryClickHouse
                    );
                    
                    $oakDataClickHouse = $clickhouse->query($oakQueryClickHouse);
                    
                    if (!empty($oakDataClickHouse)) {
                        \Log::info('OAK data fetched from ClickHouse view (nitip.oak_tabel_view): ' . count($oakDataClickHouse) . ' records');
                        // Convert array hasil ClickHouse ke format object seperti PostgreSQL
                        // Pastikan semua kolom ada dan format konsisten
                        $oakData = array_map(function($row) {
                            // Convert array associative ke object
                            $obj = new \stdClass();
                            foreach ($row as $key => $value) {
                                // Handle NULL values dari ClickHouse
                                $obj->$key = ($value === null || $value === '[NULL]' || $value === '') ? null : $value;
                            }
                            return $obj;
                        }, $oakDataClickHouse);
                    } else {
                        \Log::info('No OAK data found in ClickHouse view (nitip.oak_tabel_view), trying PostgreSQL');
                        throw new Exception('No data in ClickHouse');
                    }
                } catch (Exception $e) {
                    \Log::warning('ClickHouse OAK query failed, falling back to PostgreSQL: ' . $e->getMessage());
                    // Fallback ke PostgreSQL
                    if ($this->isTunnelActive()) {
                                $oakQuery = <<<SQL
SELECT oak.id,
       oak.mobile_uuid,
       act.name AS activity,
       subact.name AS sub_activity,
       mat.name AS material,
       tool.name AS tool_type,
       conveyance.name AS conveyance_type,
       lifting.name AS lifting_equipment,
       site.nama AS site,
       loc.nama AS location,
       detailloc.nama AS detail_location,
       oak.ket_lokasi AS location_description,
       shift.name AS shift,
       conclusion.name AS conclusion,
       company_submit_by.nama AS company_submit_by,
       submit_by.kode_sid AS kode_sid_pelapor,
       submit_by.kode_sid,
       submit_by.nama AS submit_by,
       submit_by.id AS submit_id,
       submit_jbt.nama AS jabatan_fungsional_submiter,
       oak.submit_date,
       oak.sib_register_id AS sib_register,
       code_sib.code_sib,
       tl_obs.name AS tools_observasi,
       oak_team.id_employee AS id_employee_team,
       mk.nama AS nama_team,
       mk.kode_sid AS kode_sid_team,
       mk_jbt.nama AS jabatan_fungsional_team,
       oak_team.type AS tipe,
       oak.location_latitude AS latitude,
       oak.location_longitude AS longitude,
       ff.filename AS file_foto,
       oak.platform,
       oak.is_be_draft,
       oak.be_draft_photo_date AS bedraft_date,
       su.app_version AS versi_apk,
       CASE
           WHEN su.app_version::text = 'BEATS-v0.3.1.1'::text THEN '3.1.1'::text
           WHEN su.app_version::text = 'BEATS-v0.3.1.7'::text THEN '3.1.7'::text
           ELSE 'others'::text
       END AS apk,
       CASE
           WHEN oak.is_be_draft = 1 THEN 'BeDraft'::text
           ELSE 'Normal'::text
       END AS method,
       concat('https://hseautomation.beraucoal.co.id/beats2/file/document/', oak.id) AS url_photo
FROM bcbeats.oak_register oak
JOIN bcbeats.m_oak_activity act ON act.id = oak.id_oak_activity
JOIN bcbeats.m_oak_sub_activity subact ON subact.id = oak.id_oak_sub_activity
JOIN bcbeats.m_oak_material mat ON mat.id = oak.id_oak_material
JOIN bcbeats.m_oak_jenis_tools tool ON tool.id = oak.id_oak_type_tools
JOIN bcbeats.m_oak_jenis_alat_angkut conveyance ON conveyance.id = oak.id_oak_type_conveyance
JOIN bcbeats.m_oak_alat_angkat lifting ON lifting.id = oak.id_oak_lifting_equipment
JOIN bcbeats.m_lokasi detailloc ON detailloc.id = oak.id_location
JOIN bcbeats.m_lokasi loc ON loc.id = detailloc.id_parent
JOIN bcbeats.m_lokasi site ON site.id = loc.id_parent
JOIN bcbeats.m_lookup shift ON shift.id = oak.id_shift
JOIN bcbeats.m_lookup conclusion ON conclusion.id = oak.id_conclusion
JOIN bcbeats.oak_team ON bcbeats.oak_team.id_oak_register = oak.id
LEFT JOIN bcbeats.m_lookup tl_obs ON tl_obs.id = oak.id_tools_observation
LEFT JOIN bcsid.m_karyawan submit_by ON submit_by.id = oak.submit_by_id
LEFT JOIN bcsid.m_jabatan submit_jbt ON submit_jbt.id = submit_by.id_jabatan_fungsional
LEFT JOIN bcsid.m_perusahaan company_submit_by ON company_submit_by.id = submit_by.id_perusahaan
LEFT JOIN bcsid.sib_register ON sib_register.id = oak.sib_register_id
LEFT JOIN bcsid.sib_master code_sib ON code_sib.id = sib_register.id_m_sib
LEFT JOIN bcsid.m_karyawan mk ON mk.id = bcbeats.oak_team.id_employee
LEFT JOIN bcsid.m_jabatan mk_jbt ON mk_jbt.id = mk.id_jabatan_fungsional
LEFT JOIN bcbeats.file_foto ff ON ff.id = oak.photo_id
LEFT JOIN bcbeats.sys_user su ON su.username::text = submit_by.kode_sid::text
WHERE oak.submit_date BETWEEN ?::timestamp without time zone AND ?::timestamp without time zone
ORDER BY oak.submit_date DESC
LIMIT 1000
SQL;

                        $oakData = DB::connection('pgsql_ssh')->select($oakQuery, [$startDate, $endDate]);
                        \Log::info('OAK data fetched from PostgreSQL: ' . count($oakData) . ' records');
                    } else {
                        \Log::warning('SSH tunnel is not active, cannot fetch OAK data from PostgreSQL');
                    }
                }
            } else {
                // ClickHouse tidak tersedia, langsung gunakan PostgreSQL
                \Log::info('ClickHouse not connected, using PostgreSQL for OAK data');
                if ($this->isTunnelActive()) {
                    $oakQuery = <<<SQL
SELECT oak.id,
       oak.mobile_uuid,
       act.name AS activity,
       subact.name AS sub_activity,
       mat.name AS material,
       tool.name AS tool_type,
       conveyance.name AS conveyance_type,
       lifting.name AS lifting_equipment,
       site.nama AS site,
       loc.nama AS location,
       detailloc.nama AS detail_location,
       oak.ket_lokasi AS location_description,
       shift.name AS shift,
       conclusion.name AS conclusion,
       company_submit_by.nama AS company_submit_by,
       submit_by.kode_sid AS kode_sid_pelapor,
       submit_by.kode_sid,
       submit_by.nama AS submit_by,
       submit_by.id AS submit_id,
       submit_jbt.nama AS jabatan_fungsional_submiter,
       oak.submit_date,
       oak.sib_register_id AS sib_register,
       code_sib.code_sib,
       tl_obs.name AS tools_observasi,
       oak_team.id_employee AS id_employee_team,
       mk.nama AS nama_team,
       mk.kode_sid AS kode_sid_team,
       mk_jbt.nama AS jabatan_fungsional_team,
       oak_team.type AS tipe,
       oak.location_latitude AS latitude,
       oak.location_longitude AS longitude,
       ff.filename AS file_foto,
       oak.platform,
       oak.is_be_draft,
       oak.be_draft_photo_date AS bedraft_date,
       su.app_version AS versi_apk,
       CASE
           WHEN su.app_version::text = 'BEATS-v0.3.1.1'::text THEN '3.1.1'::text
           WHEN su.app_version::text = 'BEATS-v0.3.1.7'::text THEN '3.1.7'::text
           ELSE 'others'::text
       END AS apk,
       CASE
           WHEN oak.is_be_draft = 1 THEN 'BeDraft'::text
           ELSE 'Normal'::text
       END AS method,
       concat('https://hseautomation.beraucoal.co.id/beats2/file/document/', oak.id) AS url_photo
FROM bcbeats.oak_register oak
JOIN bcbeats.m_oak_activity act ON act.id = oak.id_oak_activity
JOIN bcbeats.m_oak_sub_activity subact ON subact.id = oak.id_oak_sub_activity
JOIN bcbeats.m_oak_material mat ON mat.id = oak.id_oak_material
JOIN bcbeats.m_oak_jenis_tools tool ON tool.id = oak.id_oak_type_tools
JOIN bcbeats.m_oak_jenis_alat_angkut conveyance ON conveyance.id = oak.id_oak_type_conveyance
JOIN bcbeats.m_oak_alat_angkat lifting ON lifting.id = oak.id_oak_lifting_equipment
JOIN bcbeats.m_lokasi detailloc ON detailloc.id = oak.id_location
JOIN bcbeats.m_lokasi loc ON loc.id = detailloc.id_parent
JOIN bcbeats.m_lokasi site ON site.id = loc.id_parent
JOIN bcbeats.m_lookup shift ON shift.id = oak.id_shift
JOIN bcbeats.m_lookup conclusion ON conclusion.id = oak.id_conclusion
JOIN bcbeats.oak_team ON bcbeats.oak_team.id_oak_register = oak.id
LEFT JOIN bcbeats.m_lookup tl_obs ON tl_obs.id = oak.id_tools_observation
LEFT JOIN bcsid.m_karyawan submit_by ON submit_by.id = oak.submit_by_id
LEFT JOIN bcsid.m_jabatan submit_jbt ON submit_jbt.id = submit_by.id_jabatan_fungsional
LEFT JOIN bcsid.m_perusahaan company_submit_by ON company_submit_by.id = submit_by.id_perusahaan
LEFT JOIN bcsid.sib_register ON sib_register.id = oak.sib_register_id
LEFT JOIN bcsid.sib_master code_sib ON code_sib.id = sib_register.id_m_sib
LEFT JOIN bcsid.m_karyawan mk ON mk.id = bcbeats.oak_team.id_employee
LEFT JOIN bcsid.m_jabatan mk_jbt ON mk_jbt.id = mk.id_jabatan_fungsional
LEFT JOIN bcbeats.file_foto ff ON ff.id = oak.photo_id
LEFT JOIN bcbeats.sys_user su ON su.username::text = submit_by.kode_sid::text
WHERE oak.submit_date BETWEEN ?::timestamp without time zone AND ?::timestamp without time zone
ORDER BY oak.submit_date DESC
LIMIT 1000
SQL;

                    $oakData = DB::connection('pgsql_ssh')->select($oakQuery, [$startDate, $endDate]);
                    \Log::info('OAK data fetched from PostgreSQL: ' . count($oakData) . ' records');
                }
            }
        } catch (Exception $e) {
            \Log::error('Error fetching OAK data: ' . $e->getMessage());
            $oakData = [];
        }

        // Parameters for coverage table
        $coverageSearch = request('coverage_search');
        $coveragePage = max((int) request('coverage_page', 1), 1);

        // Ambil data coverage CCTV beserta laporan hazard
        $coverageSummary = $this->getCctvCoverageWithHazards(
            $coverageSearch,
            10,
            $coveragePage
        );
        
        // Extract CCTV stats for easier access in view
        $totalCctv = $cctvStats['totalCctv'] ?? 0;
        $cctvOn = $cctvStats['cctvOn'] ?? 0;
        $cctvOff = $cctvStats['cctvOff'] ?? 0;
        $criticalAreas = $cctvStats['criticalAreas'] ?? 0;
        $coverageByLocation = $cctvStats['coverageByLocation'] ?? collect([]);
        $distributionBySite = $cctvStats['distributionBySite'] ?? collect([]);
        $distributionByCompany = $cctvStats['distributionByCompany'] ?? collect([]);
        $statusBreakdown = $cctvStats['statusBreakdown'] ?? collect([]);
        $kondisiBreakdown = $cctvStats['kondisiBreakdown'] ?? collect([]);
        $cctvWithAccess = $cctvStats['cctvWithAccess'] ?? 0;
        $cctvWithAutoAlert = $cctvStats['cctvWithAutoAlert'] ?? 0;
        $coveragePercentage = $cctvStats['coveragePercentage'] ?? 0;
        $topCoverageArea = $cctvStats['topCoverageArea'] ?? null;
        $topSite = $cctvStats['topSite'] ?? null;
        $companyOverview = $cctvStats['companyOverview'] ?? collect([]);
        $activeCctvRecords = $cctvStats['activeCctvRecords'] ?? collect([]);
        $offlineCctvRecords = $cctvStats['offlineCctvRecords'] ?? collect([]);
        $criticalCctvRecords = $cctvStats['criticalCctvRecords'] ?? collect([]);
        
        return view('index', compact(
            'overviewStats',
            'cctvStats',
            'oakData',
            'coverageSummary',
            'coverageSearch',
            'coveragePage',
            'totalCctv',
            'cctvOn',
            'cctvOff',
            'criticalAreas',
            'coverageByLocation',
            'distributionBySite',
            'distributionByCompany',
            'statusBreakdown',
            'kondisiBreakdown',
            'cctvWithAccess',
            'cctvWithAutoAlert',
            'coveragePercentage',
            'topCoverageArea',
            'topSite',
            'companyOverview',
            'activeCctvRecords',
            'offlineCctvRecords',
            'criticalCctvRecords'
        ))
            ->with($overviewStats)
            ->with($cctvStats);
    }

    /**
     * Return CCTV dataset per company for modal DataTable
     */
    public function companyCctvData(Request $request)
    {
        $company = trim($request->get('company', ''));
        $normalized = $this->normalizeCompanyName($company);

        $query = CctvData::select([
            'site',
            'perusahaan',
            'no_cctv',
            'nama_cctv',
            'status',
            'kondisi',
            'coverage_lokasi',
        ])->orderBy('perusahaan')
          ->orderBy('no_cctv');

        if ($company !== '' && !in_array($normalized, ['all', 'semuaperusahaan'])) {
            if ($normalized === 'tidakdiketahui') {
                $query->where(function($q) {
                    $q->whereNull('perusahaan')
                      ->orWhere('perusahaan', '');
                });
            } else {
                $query->whereRaw(
                    "LOWER(REGEXP_REPLACE(TRIM(perusahaan), '[^a-zA-Z0-9]', '')) = ?",
                    [$normalized]
                );
            }
        }

        $data = $query->get()->map(function ($item) {
            return [
                'site' => $item->site ? trim($item->site) : '-',
                'perusahaan' => $item->perusahaan ? trim($item->perusahaan) : 'Tidak Diketahui',
                'no_cctv' => $item->no_cctv ?? '-',
                'nama_cctv' => $item->nama_cctv ?? '-',
                'status' => $item->status ?? '-',
                'kondisi' => $item->kondisi ?? '-',
                'coverage_lokasi' => $item->coverage_lokasi ? trim($item->coverage_lokasi) : '-',
            ];
        });

        return response()->json([
            'company' => $company !== '' ? $company : 'Semua Perusahaan',
            'total' => $data->count(),
            'data' => $data,
        ]);
    }

    /**
     * Get overview statistics - try ClickHouse first, fallback to PostgreSQL
     */
    private function getOverviewStats()
    {
        $clickhouse = new ClickHouseService();
        
        // Coba dari ClickHouse jika tersedia
        if ($clickhouse->isConnected()) {
            try {
                return $this->getOverviewStatsFromClickHouse($clickhouse);
            } catch (Exception $e) {
                \Log::warning('ClickHouse query failed, falling back to PostgreSQL: ' . $e->getMessage());
            }
        }
        
        // Fallback ke PostgreSQL
        return $this->getOverviewStatsFromPostgres();
    }

    /**
     * Get overview statistics from ClickHouse
     */
    private function getOverviewStatsFromClickHouse(ClickHouseService $clickhouse)
    {
        $currentYear = date('Y');
        $currentMonth = date('m');
        $lastMonth = date('m', strtotime('-1 month'));
        $lastMonthYear = date('Y', strtotime('-1 month'));
        $startOfYear = $currentYear . '-01-01';
        $startOfMonth = $currentYear . '-' . $currentMonth . '-01';
        $startOfLastMonth = $lastMonthYear . '-' . $lastMonth . '-01';
        $endOfLastMonth = date('Y-m-t', strtotime('-1 month'));

        // Total YTD Insiden
        $ytdQuery = "
            SELECT COUNT(*) as total
            FROM car_register
            WHERE id_sumberdata <> 200 
                AND create_date >= '{startOfYear}'
                AND create_date < today() + 1
        ";
        $ytdResult = $clickhouse->query(str_replace('{startOfYear}', $startOfYear, $ytdQuery));
        $totalYtdInsiden = isset($ytdResult[0]) && isset($ytdResult[0]['total']) ? (int)$ytdResult[0]['total'] : 0;

        // Total YTD Insiden bulan lalu
        $ytdLastMonthQuery = "
            SELECT COUNT(*) as total
            FROM car_register
            WHERE id_sumberdata <> 200 
                AND create_date >= '{startOfYear}'
                AND create_date <= '{endOfLastMonth} 23:59:59'
        ";
        $ytdLastMonthResult = $clickhouse->query(str_replace(
            ['{startOfYear}', '{endOfLastMonth}'],
            [$startOfYear, $endOfLastMonth],
            $ytdLastMonthQuery
        ));
        $ytdLastMonth = isset($ytdLastMonthResult[0]) && isset($ytdLastMonthResult[0]['total']) ? (int)$ytdLastMonthResult[0]['total'] : 0;
        $ytdInsidenChange = $ytdLastMonth > 0 ? round((($totalYtdInsiden - $ytdLastMonth) / $ytdLastMonth) * 100, 1) : 0;

        // Active Hazards
        $activeQuery = "
            SELECT COUNT(*) as total
            FROM car_register cr
            LEFT JOIN m_status st ON st.id = cr.id_status
            WHERE cr.id_sumberdata <> 200 
                AND cr.create_date >= '2023-12-31 23:59:59'
                AND (st.nama = 'Open' OR st.nama = 'In Progress')
        ";
        $activeResult = $clickhouse->query($activeQuery);
        $activeHazards = isset($activeResult[0]) && isset($activeResult[0]['total']) ? (int)$activeResult[0]['total'] : 0;

        // Total hazards
        $totalHazardsQuery = "
            SELECT COUNT(*) as total
            FROM car_register
            WHERE id_sumberdata <> 200 
                AND create_date >= '2023-12-31 23:59:59'
        ";
        $totalHazardsResult = $clickhouse->query($totalHazardsQuery);
        $totalHazards = isset($totalHazardsResult[0]) && isset($totalHazardsResult[0]['total']) ? (int)$totalHazardsResult[0]['total'] : 0;
        $hazardIncrease = $activeHazards;

        // Resolved Hazards This Year
        $resolvedQuery = "
            SELECT COUNT(*) as total
            FROM car_register cr
            LEFT JOIN m_status st ON st.id = cr.id_status
            LEFT JOIN car_tindakan ct ON ct.id_car_register = cr.id
            WHERE cr.id_sumberdata <> 200 
                AND (st.nama = 'Closed' OR st.nama = 'Resolved')
                AND ct.tanggal_aktual_penyelesaian >= '{startOfYear}'
                AND ct.tanggal_aktual_penyelesaian < today() + 1
        ";
        $resolvedResult = $clickhouse->query(str_replace('{startOfYear}', $startOfYear, $resolvedQuery));
        $resolvedHazards = isset($resolvedResult[0]) && isset($resolvedResult[0]['total']) ? (int)$resolvedResult[0]['total'] : 0;

        // Resolved Hazards bulan lalu
        $resolvedLastMonthQuery = "
            SELECT COUNT(*) as total
            FROM car_register cr
            LEFT JOIN m_status st ON st.id = cr.id_status
            LEFT JOIN car_tindakan ct ON ct.id_car_register = cr.id
            WHERE cr.id_sumberdata <> 200 
                AND (st.nama = 'Closed' OR st.nama = 'Resolved')
                AND ct.tanggal_aktual_penyelesaian >= '{startOfYear}'
                AND ct.tanggal_aktual_penyelesaian <= '{endOfLastMonth} 23:59:59'
        ";
        $resolvedLastMonthResult = $clickhouse->query(str_replace(
            ['{startOfYear}', '{endOfLastMonth}'],
            [$startOfYear, $endOfLastMonth],
            $resolvedLastMonthQuery
        ));
        $resolvedLastMonth = isset($resolvedLastMonthResult[0]) && isset($resolvedLastMonthResult[0]['total']) ? (int)$resolvedLastMonthResult[0]['total'] : 0;
        $resolvedHazardsChange = $resolvedLastMonth > 0 ? round((($resolvedHazards - $resolvedLastMonth) / $resolvedLastMonth) * 100, 1) : 0;

        // Monthly Hazards
        $monthlyQuery = "
            SELECT COUNT(*) as total
            FROM car_register
            WHERE id_sumberdata <> 200 
                AND create_date >= '{startOfMonth}'
                AND create_date < today() + 1
        ";
        $monthlyResult = $clickhouse->query(str_replace('{startOfMonth}', $startOfMonth, $monthlyQuery));
        $monthlyHazards = isset($monthlyResult[0]) && isset($monthlyResult[0]['total']) ? (int)$monthlyResult[0]['total'] : 0;

        // Monthly Hazards bulan lalu
        $monthlyLastMonthQuery = "
            SELECT COUNT(*) as total
            FROM car_register
            WHERE id_sumberdata <> 200 
                AND create_date >= '{startOfLastMonth}'
                AND create_date <= '{endOfLastMonth} 23:59:59'
        ";
        $monthlyLastMonthResult = $clickhouse->query(str_replace(
            ['{startOfLastMonth}', '{endOfLastMonth}'],
            [$startOfLastMonth, $endOfLastMonth],
            $monthlyLastMonthQuery
        ));
        $monthlyLastMonth = isset($monthlyLastMonthResult[0]) && isset($monthlyLastMonthResult[0]['total']) ? (int)$monthlyLastMonthResult[0]['total'] : 0;
        $monthlyChange = $monthlyLastMonth > 0 ? round((($monthlyHazards - $monthlyLastMonth) / $monthlyLastMonth) * 100, 1) : 0;
        $monthlyCount = $monthlyLastMonth;

        // Yearly Hazards
        $yearlyQuery = "
            SELECT COUNT(*) as total
            FROM car_register
            WHERE id_sumberdata <> 200 
                AND create_date >= '{startOfYear}'
                AND create_date < today() + 1
        ";
        $yearlyResult = $clickhouse->query(str_replace('{startOfYear}', $startOfYear, $yearlyQuery));
        $yearlyHazards = isset($yearlyResult[0]) && isset($yearlyResult[0]['total']) ? (int)$yearlyResult[0]['total'] : 0;

        // Yearly Hazards tahun lalu
        $lastYear = $currentYear - 1;
        $yearlyLastYearQuery = "
            SELECT COUNT(*) as total
            FROM car_register
            WHERE id_sumberdata <> 200 
                AND create_date >= '{lastYear}-01-01'
                AND create_date <= '{lastYear}-12-31 23:59:59'
        ";
        $yearlyLastYearResult = $clickhouse->query(str_replace('{lastYear}', $lastYear, $yearlyLastYearQuery));
        $yearlyLastYear = isset($yearlyLastYearResult[0]) && isset($yearlyLastYearResult[0]['total']) ? (int)$yearlyLastYearResult[0]['total'] : 0;
        $yearlyChange = $yearlyLastYear > 0 ? round((($yearlyHazards - $yearlyLastYear) / $yearlyLastYear) * 100, 1) : 0;
        $yearlyCount = $yearlyLastYear;

        // Chart data per bulan
        $chartQuery = "
            SELECT 
                toMonth(create_date) as month_num,
                COUNT(*) as count
            FROM car_register
            WHERE id_sumberdata <> 200 
                AND create_date >= '{startOfYear}'
                AND create_date < today() + 1
            GROUP BY toMonth(create_date)
            ORDER BY month_num
        ";
        $chartResult = $clickhouse->query(str_replace('{startOfYear}', $startOfYear, $chartQuery));
        
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $chartData = [];
        foreach ($chartResult as $row) {
            $monthNum = isset($row['month_num']) ? (int)$row['month_num'] : 0;
            if ($monthNum >= 1 && $monthNum <= 12) {
                $chartData[] = [
                    'month' => $monthNames[$monthNum - 1],
                    'count' => isset($row['count']) ? (int)$row['count'] : 0
                ];
            }
        }

        return [
            'totalYtdInsiden' => $totalYtdInsiden,
            'ytdInsidenChange' => $ytdInsidenChange,
            'activeHazards' => $activeHazards,
            'hazardIncrease' => $hazardIncrease,
            'resolvedHazards' => $resolvedHazards,
            'resolvedHazardsChange' => $resolvedHazardsChange,
            'monthlyHazards' => $monthlyHazards,
            'monthlyChange' => $monthlyChange,
            'monthlyCount' => $monthlyCount,
            'yearlyHazards' => $yearlyHazards,
            'yearlyChange' => $yearlyChange,
            'yearlyCount' => $yearlyCount,
            'chartData' => $chartData,
            'totalHazards' => $totalHazards,
        ];
    }

    /**
     * Get overview statistics from PostgreSQL
     */
    private function getOverviewStatsFromPostgres()
    {
        try {
            // Check if SSH tunnel is active
            if (!$this->isTunnelActive()) {
                \Log::warning('SSH tunnel is not active. Returning default overview stats.');
                return $this->getDefaultOverviewStats();
            }

            $currentYear = date('Y');
            $currentMonth = date('m');
            $lastMonth = date('m', strtotime('-1 month'));
            $lastMonthYear = date('Y', strtotime('-1 month'));
            $startOfYear = $currentYear . '-01-01';
            $startOfMonth = $currentYear . '-' . $currentMonth . '-01';
            $startOfLastMonth = $lastMonthYear . '-' . $lastMonth . '-01';

            // Total YTD Insiden
            $ytdQuery = "
                SELECT COUNT(*) as total
                FROM bcbeats.car_register cr
                WHERE cr.id_sumberdata <> 200 
                    AND cr.create_date >= ?::timestamp without time zone
                    AND cr.create_date < (CURRENT_DATE + INTERVAL '1 day')::timestamp without time zone
            ";

            $ytdResult = DB::connection('pgsql_ssh')->select($ytdQuery, [$startOfYear]);
            $totalYtdInsiden = $ytdResult[0]->total ?? 0;

            // Total YTD Insiden bulan lalu (dari awal tahun sampai akhir bulan lalu)
            $endOfLastMonth = date('Y-m-t', strtotime('-1 month'));
            $ytdLastMonthQuery = "
                SELECT COUNT(*) as total
                FROM bcbeats.car_register cr
                WHERE cr.id_sumberdata <> 200 
                    AND cr.create_date >= ?::timestamp without time zone
                    AND cr.create_date <= ?::timestamp without time zone
            ";
            $ytdLastMonthResult = DB::connection('pgsql_ssh')->select($ytdLastMonthQuery, [
                $currentYear . '-01-01',
                $endOfLastMonth . ' 23:59:59'
            ]);
            $ytdLastMonth = $ytdLastMonthResult[0]->total ?? 0;
            $ytdInsidenChange = $ytdLastMonth > 0 ? round((($totalYtdInsiden - $ytdLastMonth) / $ytdLastMonth) * 100, 1) : 0;

            // Active Hazards
            $activeQuery = "
                SELECT COUNT(*) as total
                FROM bcbeats.car_register cr
                LEFT JOIN bcbeats.m_status st ON st.id = cr.id_status
                WHERE cr.id_sumberdata <> 200 
                    AND cr.create_date >= '2023-12-31 23:59:59'::timestamp without time zone
                    AND (st.nama = 'Open' OR st.nama = 'In Progress')
            ";

            $activeResult = DB::connection('pgsql_ssh')->select($activeQuery);
            $activeHazards = $activeResult[0]->total ?? 0;

            // Total hazards
            $totalHazardsQuery = "
                SELECT COUNT(*) as total
                FROM bcbeats.car_register cr
                WHERE cr.id_sumberdata <> 200 
                    AND cr.create_date >= '2023-12-31 23:59:59'::timestamp without time zone
            ";

            $totalHazardsResult = DB::connection('pgsql_ssh')->select($totalHazardsQuery);
            $totalHazards = $totalHazardsResult[0]->total ?? 0;
            $hazardIncrease = $activeHazards;

            // Resolved Hazards This Year
            $resolvedQuery = "
                SELECT COUNT(*) as total
                FROM bcbeats.car_register cr
                LEFT JOIN bcbeats.m_status st ON st.id = cr.id_status
                LEFT JOIN bcbeats.car_tindakan ct ON ct.id_car_register = cr.id
                WHERE cr.id_sumberdata <> 200 
                    AND (st.nama = 'Closed' OR st.nama = 'Resolved')
                    AND ct.tanggal_aktual_penyelesaian >= ?::timestamp without time zone
                    AND ct.tanggal_aktual_penyelesaian < (CURRENT_DATE + INTERVAL '1 day')::timestamp without time zone
            ";

            $resolvedResult = DB::connection('pgsql_ssh')->select($resolvedQuery, [$startOfYear]);
            $resolvedHazards = $resolvedResult[0]->total ?? 0;

            // Resolved Hazards bulan lalu (dari awal tahun sampai akhir bulan lalu)
            $resolvedLastMonthQuery = "
                SELECT COUNT(*) as total
                FROM bcbeats.car_register cr
                LEFT JOIN bcbeats.m_status st ON st.id = cr.id_status
                LEFT JOIN bcbeats.car_tindakan ct ON ct.id_car_register = cr.id
                WHERE cr.id_sumberdata <> 200 
                    AND (st.nama = 'Closed' OR st.nama = 'Resolved')
                    AND ct.tanggal_aktual_penyelesaian >= ?::timestamp without time zone
                    AND ct.tanggal_aktual_penyelesaian <= ?::timestamp without time zone
            ";
            $resolvedLastMonthResult = DB::connection('pgsql_ssh')->select($resolvedLastMonthQuery, [
                $startOfYear,
                $endOfLastMonth . ' 23:59:59'
            ]);
            $resolvedLastMonth = $resolvedLastMonthResult[0]->total ?? 0;
            $resolvedHazardsChange = $resolvedLastMonth > 0 ? round((($resolvedHazards - $resolvedLastMonth) / $resolvedLastMonth) * 100, 1) : 0;

            // Monthly Hazards
            $monthlyQuery = "
                SELECT COUNT(*) as total
                FROM bcbeats.car_register cr
                WHERE cr.id_sumberdata <> 200 
                    AND cr.create_date >= ?::timestamp without time zone
                    AND cr.create_date < (CURRENT_DATE + INTERVAL '1 day')::timestamp without time zone
            ";

            $monthlyResult = DB::connection('pgsql_ssh')->select($monthlyQuery, [$startOfMonth]);
            $monthlyHazards = $monthlyResult[0]->total ?? 0;

            // Monthly Hazards bulan lalu (dari awal bulan lalu sampai akhir bulan lalu)
            $monthlyLastMonthQuery = "
                SELECT COUNT(*) as total
                FROM bcbeats.car_register cr
                WHERE cr.id_sumberdata <> 200 
                    AND cr.create_date >= ?::timestamp without time zone
                    AND cr.create_date <= ?::timestamp without time zone
            ";
            $monthlyLastMonthResult = DB::connection('pgsql_ssh')->select($monthlyLastMonthQuery, [
                $startOfLastMonth,
                $endOfLastMonth . ' 23:59:59'
            ]);
            $monthlyLastMonth = $monthlyLastMonthResult[0]->total ?? 0;
            $monthlyChange = $monthlyLastMonth > 0 ? round((($monthlyHazards - $monthlyLastMonth) / $monthlyLastMonth) * 100, 1) : 0;
            $monthlyCount = $monthlyLastMonth;

            // Yearly Hazards
            $yearlyQuery = "
                SELECT COUNT(*) as total
                FROM bcbeats.car_register cr
                WHERE cr.id_sumberdata <> 200 
                    AND cr.create_date >= ?::timestamp without time zone
                    AND cr.create_date < (CURRENT_DATE + INTERVAL '1 day')::timestamp without time zone
            ";

            $yearlyResult = DB::connection('pgsql_ssh')->select($yearlyQuery, [$startOfYear]);
            $yearlyHazards = $yearlyResult[0]->total ?? 0;

            // Yearly Hazards tahun lalu (dari awal tahun lalu sampai akhir tahun lalu)
            $lastYear = $currentYear - 1;
            $yearlyLastYearQuery = "
                SELECT COUNT(*) as total
                FROM bcbeats.car_register cr
                WHERE cr.id_sumberdata <> 200 
                    AND cr.create_date >= ?::timestamp without time zone
                    AND cr.create_date <= ?::timestamp without time zone
            ";
            $yearlyLastYearResult = DB::connection('pgsql_ssh')->select($yearlyLastYearQuery, [
                $lastYear . '-01-01',
                $lastYear . '-12-31 23:59:59'
            ]);
            $yearlyLastYear = $yearlyLastYearResult[0]->total ?? 0;
            $yearlyChange = $yearlyLastYear > 0 ? round((($yearlyHazards - $yearlyLastYear) / $yearlyLastYear) * 100, 1) : 0;
            $yearlyCount = $yearlyLastYear;

            // Chart data per bulan
            $chartQuery = "
                SELECT 
                    TO_CHAR(cr.create_date, 'Mon') as month_name,
                    TO_CHAR(cr.create_date, 'MM') as month_num,
                    COUNT(*) as count
                FROM bcbeats.car_register cr
                WHERE cr.id_sumberdata <> 200 
                    AND cr.create_date >= ?::timestamp without time zone
                    AND cr.create_date < (CURRENT_DATE + INTERVAL '1 day')::timestamp without time zone
                GROUP BY TO_CHAR(cr.create_date, 'Mon'), TO_CHAR(cr.create_date, 'MM')
                ORDER BY TO_CHAR(cr.create_date, 'MM')
            ";

            $chartResult = DB::connection('pgsql_ssh')->select($chartQuery, [$startOfYear]);
            $chartData = [];
            foreach ($chartResult as $row) {
                $chartData[] = [
                    'month' => $row->month_name,
                    'count' => (int)$row->count
                ];
            }

            return [
                'totalYtdInsiden' => $totalYtdInsiden,
                'ytdInsidenChange' => $ytdInsidenChange,
                'activeHazards' => $activeHazards,
                'hazardIncrease' => $hazardIncrease,
                'resolvedHazards' => $resolvedHazards,
                'resolvedHazardsChange' => $resolvedHazardsChange,
                'monthlyHazards' => $monthlyHazards,
                'monthlyChange' => $monthlyChange,
                'monthlyCount' => $monthlyCount,
                'yearlyHazards' => $yearlyHazards,
                'yearlyChange' => $yearlyChange,
                'yearlyCount' => $yearlyCount,
                'chartData' => $chartData,
                'totalHazards' => $totalHazards,
            ];

        } catch (Exception $e) {
            \Log::error('Error fetching overview stats from PostgreSQL: ' . $e->getMessage());
            return $this->getDefaultOverviewStats();
        }
    }

    /**
     * Get CCTV statistics from MySQL
     */
    private function getCctvStatsFromMysql()
    {
        try {
            $totalCctv = CctvData::count();
            
            // CCTV On (Live View atau kondisi Baik)
            $cctvOn = CctvData::where(function($query) {
                $query->where('status', 'Live View')
                      ->orWhere('kondisi', 'Baik');
            })->count();
            
            // CCTV Off
            $cctvOff = $totalCctv - $cctvOn;
            
            // CCTV dengan koordinat
            $cctvWithCoordinates = CctvData::whereNotNull('longitude')
                ->whereNotNull('latitude')
                ->count();
            
            // Area kritis - CCTV yang mengcover area kritis
            $criticalAreas = CctvData::where(function($query) {
                $query->where('kategori_area_tercapture', 'like', '%kritis%')
                      ->orWhere('kategori_area_tercapture', 'like', '%critical%')
                      ->orWhere('coverage_lokasi', 'like', '%kritis%')
                      ->orWhere('coverage_lokasi', 'like', '%critical%');
            })->count();
            
            // Coverage berdasarkan lokasi
            $coverageByLocation = CctvData::select('coverage_lokasi', DB::raw('COUNT(*) as count'))
                ->whereNotNull('coverage_lokasi')
                ->where('coverage_lokasi', '!=', '')
                ->groupBy('coverage_lokasi')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->map(function($item) use ($totalCctv) {
                    return [
                        'location' => $item->coverage_lokasi,
                        'count' => $item->count,
                        'percentage' => $totalCctv > 0 ? round(($item->count / $totalCctv) * 100, 1) : 0
                    ];
                });
            
            // Distribusi berdasarkan site
            $distributionBySite = CctvData::select('site', DB::raw('COUNT(*) as count'))
                ->whereNotNull('site')
                ->where('site', '!=', '')
                ->groupBy('site')
                ->orderBy('count', 'desc')
                ->get()
                ->map(function($item) use ($totalCctv) {
                    return [
                        'site' => $item->site,
                        'count' => $item->count,
                        'percentage' => $totalCctv > 0 ? round(($item->count / $totalCctv) * 100, 1) : 0
                    ];
                });
            
            // Distribusi berdasarkan perusahaan
            $distributionByCompany = CctvData::select('perusahaan', DB::raw('COUNT(*) as count'))
                ->whereNotNull('perusahaan')
                ->where('perusahaan', '!=', '')
                ->groupBy('perusahaan')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get()
                ->map(function($item) use ($totalCctv) {
                    return [
                        'company' => $item->perusahaan,
                        'count' => $item->count,
                        'percentage' => $totalCctv > 0 ? round(($item->count / $totalCctv) * 100, 1) : 0
                    ];
                });
            
            // Status breakdown
            $statusBreakdown = CctvData::select('status', DB::raw('COUNT(*) as count'))
                ->whereNotNull('status')
                ->where('status', '!=', '')
                ->groupBy('status')
                ->get()
                ->map(function($item) {
                    return [
                        'status' => $item->status,
                        'count' => $item->count
                    ];
                });
            
            // Kondisi breakdown
            $kondisiBreakdown = CctvData::select('kondisi', DB::raw('COUNT(*) as count'))
                ->whereNotNull('kondisi')
                ->where('kondisi', '!=', '')
                ->groupBy('kondisi')
                ->get()
                ->map(function($item) {
                    return [
                        'kondisi' => $item->kondisi,
                        'count' => $item->count
                    ];
                });
            
            // CCTV dengan link akses (dapat diakses)
            $cctvWithAccess = CctvData::whereNotNull('link_akses')
                ->where('link_akses', '!=', '')
                ->count();
            
            // CCTV dengan auto alert
            $cctvWithAutoAlert = CctvData::where(function($query) {
                $query->where('fitur_auto_alert', 'like', '%ya%')
                      ->orWhere('fitur_auto_alert', 'like', '%yes%')
                      ->orWhere('fitur_auto_alert', 'like', '%aktif%');
            })->count();
            
            // Persentase coverage
            $coveragePercentage = $totalCctv > 0 ? round(($cctvOn / $totalCctv) * 100, 1) : 0;
            
            // Analisis: Area dengan CCTV terbanyak
            $topCoverageArea = $coverageByLocation->first();
            
            // Analisis: Site dengan CCTV terbanyak
            $topSite = $distributionBySite->first();

            // Overview per perusahaan
            $companyOverview = CctvData::selectRaw("
                    COALESCE(NULLIF(perusahaan, ''), 'Tidak Diketahui') as perusahaan,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Live View' OR kondisi = 'Baik' THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN NOT (status = 'Live View' OR kondisi = 'Baik') THEN 1 ELSE 0 END) as tidak_aktif
                ")
                ->groupBy('perusahaan')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(function ($item) use ($totalCctv) {
                    $inactive = (int) $item->tidak_aktif;
                    $active = (int) $item->aktif;
                    $companyTotal = (int) $item->total;
                    $companyName = trim($item->perusahaan ?? 'Tidak Diketahui');
                    return [
                        'company' => $companyName !== '' ? $companyName : 'Tidak Diketahui',
                        'company_key' => $this->normalizeCompanyName($companyName),
                        'total' => $companyTotal,
                        'active' => $active,
                        'inactive' => $inactive,
                        'percentage' => $totalCctv > 0 ? round(($companyTotal / $totalCctv) * 100, 1) : 0,
                    ];
                })
                ->values();

            $sampleFields = ['id','site','perusahaan','no_cctv','nama_cctv','status','kondisi','coverage_lokasi'];

            $activeCctvRecords = CctvData::where(function($query) {
                    $query->where('status', 'Live View')
                          ->orWhere('kondisi', 'Baik');
                })
                ->select($sampleFields)
                ->orderBy('perusahaan')
                ->orderBy('no_cctv')
                ->limit(50)
                ->get();

            $offlineCctvRecords = CctvData::where(function($query) {
                    $query->whereNull('status')
                          ->orWhere('status', '!=', 'Live View');
                })
                ->where(function($query) {
                    $query->whereNull('kondisi')
                          ->orWhere('kondisi', '!=', 'Baik');
                })
                ->select($sampleFields)
                ->orderBy('perusahaan')
                ->orderBy('no_cctv')
                ->limit(50)
                ->get();

            $criticalCctvRecords = CctvData::where(function($query) {
                    $query->where('kategori_area_tercapture', 'like', '%kritis%')
                          ->orWhere('kategori_area_tercapture', 'like', '%critical%')
                          ->orWhere('coverage_lokasi', 'like', '%kritis%')
                          ->orWhere('coverage_lokasi', 'like', '%critical%');
                })
                ->select(array_merge($sampleFields, ['kategori_area_tercapture']))
                ->orderBy('coverage_lokasi')
                ->limit(50)
                ->get();
            
            return [
                'totalCctv' => $totalCctv,
                'cctvOn' => $cctvOn,
                'cctvOff' => $cctvOff,
                'activeCctv' => $cctvOn, // untuk backward compatibility
                'cctvWithCoordinates' => $cctvWithCoordinates,
                'criticalAreas' => $criticalAreas,
                'coverageByLocation' => $coverageByLocation,
                'distributionBySite' => $distributionBySite,
                'distributionByCompany' => $distributionByCompany,
                'statusBreakdown' => $statusBreakdown,
                'kondisiBreakdown' => $kondisiBreakdown,
                'cctvWithAccess' => $cctvWithAccess,
                'cctvWithAutoAlert' => $cctvWithAutoAlert,
                'coveragePercentage' => $coveragePercentage,
                'topCoverageArea' => $topCoverageArea,
                'topSite' => $topSite,
                'companyOverview' => $companyOverview,
                'activeCctvRecords' => $activeCctvRecords,
                'offlineCctvRecords' => $offlineCctvRecords,
                'criticalCctvRecords' => $criticalCctvRecords,
            ];
        } catch (Exception $e) {
            \Log::error('Error fetching CCTV stats from MySQL: ' . $e->getMessage());
            return [
                'totalCctv' => 0,
                'cctvOn' => 0,
                'cctvOff' => 0,
                'activeCctv' => 0,
                'cctvWithCoordinates' => 0,
                'criticalAreas' => 0,
                'coverageByLocation' => collect([]),
                'distributionBySite' => collect([]),
                'distributionByCompany' => collect([]),
                'statusBreakdown' => collect([]),
                'kondisiBreakdown' => collect([]),
                'cctvWithAccess' => 0,
                'cctvWithAutoAlert' => 0,
                'coveragePercentage' => 0,
                'topCoverageArea' => null,
                'topSite' => null,
                'companyOverview' => collect([]),
                'activeCctvRecords' => collect([]),
                'offlineCctvRecords' => collect([]),
                'criticalCctvRecords' => collect([]),
            ];
        }
    }

    /**
     * Get CCTV coverage detail with hazard reports
     */
    private function getCctvCoverageWithHazards($search = null, $perPage = 10, $page = 1)
    {
        try {
            $cctvRecords = CctvData::select(
                'site',
                'perusahaan',
                'no_cctv',
                'nama_cctv',
                'coverage_lokasi',
                'coverage_detail_lokasi'
            )
            ->orderBy('site')
            ->orderBy('no_cctv')
            ->get();

            if ($cctvRecords->isEmpty()) {
                return [];
            }

            $hazardsByLocation = $this->getHazardsGroupedByLocation();

            $filteredRecords = $cctvRecords->filter(function ($record) use ($search) {
                if (!$search) {
                    return true;
                }

                $keywords = preg_split('/\s+/', trim($search));
                $haystack = strtolower(
                    implode(' ', array_filter([
                        $record->site,
                        $record->perusahaan,
                        $record->no_cctv,
                        $record->nama_cctv,
                        $record->coverage_lokasi,
                        $record->coverage_detail_lokasi,
                    ]))
                );

                foreach ($keywords as $keyword) {
                    if ($keyword !== '' && !str_contains($haystack, strtolower($keyword))) {
                        return false;
                    }
                }

                return true;
            });

            $totalItems = $filteredRecords->count();
            $totalPages = (int) ceil($totalItems / $perPage);
            $page = max(1, min($page, max($totalPages, 1)));

            $pagedRecords = $filteredRecords
                ->slice(($page - 1) * $perPage, $perPage)
                ->values();

            $coverageData = $pagedRecords->map(function ($cctv) use ($hazardsByLocation) {
                $site = $cctv->site ?: 'Site Tidak Diketahui';

                $locationCandidates = [
                    $cctv->coverage_detail_lokasi,
                    $cctv->coverage_lokasi,
                    $cctv->nama_cctv,
                    $cctv->site
                ];

                $matchedHazards = [];
                foreach ($locationCandidates as $candidate) {
                    $key = $this->normalizeLocationString($candidate);
                    if ($key && isset($hazardsByLocation[$key])) {
                        $matchedHazards = $hazardsByLocation[$key];
                        break;
                    }
                }

                $displayHazards = array_slice($matchedHazards, 0, 3);

                return [
                    'site' => $site,
                    'cctv_number' => $cctv->no_cctv ?: '-',
                    'cctv_name' => $cctv->nama_cctv ?: '-',
                    'company' => $cctv->perusahaan ?: '-',
                    'coverage_location' => $cctv->coverage_lokasi ?: '-',
                    'coverage_detail' => $cctv->coverage_detail_lokasi ?: '-',
                    'hazards' => $displayHazards,
                    'hazard_count' => count($matchedHazards),
                ];
            });

            $grouped = $coverageData
                ->groupBy('site')
                ->map(function ($items) {
                    return $items->values();
                })
                ->toArray();

            return [
                'data' => $grouped,
                'meta' => [
                    'total_items' => $totalItems,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                    'per_page' => $perPage,
                ],
            ];
        } catch (Exception $e) {
            \Log::error('Error fetching CCTV coverage with hazards: ' . $e->getMessage());
            return [
                'data' => [],
                'meta' => [
                    'total_items' => 0,
                    'total_pages' => 0,
                    'current_page' => 1,
                    'per_page' => $perPage,
                ],
            ];
        }
    }

    /**
     * Get hazards grouped by normalized location
     */
    private function getHazardsGroupedByLocation()
    {
        try {
            if (!$this->isTunnelActive()) {
                return [];
            }

            $query = "
                SELECT 
                    cr.id,
                    cr.deskripsi,
                    cr.create_date,
                    cr.keparahan,
                    cr.nilai_resiko,
                    loc_d.nama AS nama_detail_lokasi,
                    cr.lokasi_detail,
                    loc.nama AS nama_lokasi,
                    site.nama AS nama_site
                FROM bcbeats.car_register cr
                    LEFT JOIN bcbeats.m_lokasi loc_d ON loc_d.id = cr.id_lokasi
                    LEFT JOIN bcbeats.m_lokasi loc ON loc.id = loc_d.id_parent
                    LEFT JOIN bcbeats.m_lokasi site ON site.id = loc.id_parent
                WHERE cr.id_sumberdata <> 200
                    AND cr.create_date >= (CURRENT_DATE - INTERVAL '180 days')
                ORDER BY cr.create_date DESC
                LIMIT 1000
            ";

            $results = DB::connection('pgsql_ssh')->select($query);

            if (empty($results)) {
                return [];
            }

            $grouped = [];

            foreach ($results as $row) {
                $hazardData = [
                    'id' => 'HD-' . $row->id,
                    'description' => $row->deskripsi ?? 'Tidak ada deskripsi',
                    'detected_at' => $row->create_date
                        ? date('d M Y H:i', strtotime($row->create_date))
                        : '-',
                    'severity' => $row->keparahan ?? 'N/A',
                    'risk_value' => $row->nilai_resiko ?? null,
                    'type' => $row->nama_detail_lokasi ?? $row->nama_lokasi ?? 'Hazard',
                ];

                $candidates = array_filter([
                    $row->nama_detail_lokasi,
                    $row->lokasi_detail,
                    $row->nama_lokasi,
                    $row->nama_site,
                ]);

                $uniqueKeys = [];
                foreach ($candidates as $candidate) {
                    $normalized = $this->normalizeLocationString($candidate);
                    if ($normalized && !in_array($normalized, $uniqueKeys, true)) {
                        $uniqueKeys[] = $normalized;
                    }
                }

                foreach ($uniqueKeys as $key) {
                    $grouped[$key][] = $hazardData;
                }
            }

            return $grouped;
        } catch (Exception $e) {
            \Log::error('Error fetching hazards grouped by location: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Normalize location string for matching
     */
    private function normalizeLocationString($value)
    {
        if (!$value) {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9]/i', '', strtolower($value));

        return $normalized ?: null;
    }

    /**
     * Normalize company name for consistent keying
     */
    private function normalizeCompanyName($value)
    {
        if (!$value) {
            return 'tidakdiketahui';
        }

        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $value));

        return $normalized ?: 'tidakdiketahui';
    }

    /**
     * Get company statistics for modal
     */
    public function getCompanyStats(Request $request)
    {
        try {
            $company = trim($request->query('company', '__all__'));
            
            $query = CctvData::query();
            
            // Filter by company
            if ($company !== '__all__') {
                if (strcasecmp($company, 'Tidak Diketahui') === 0) {
                    $query->where(function ($q) {
                        $q->whereNull('perusahaan')
                          ->orWhere('perusahaan', '');
                    });
                } else {
                    $query->whereRaw('TRIM(perusahaan) = ?', [$company]);
                }
            }
            
            $total = $query->count();
            
            // CCTV Aktif
            $aktif = (clone $query)->where(function($q) {
                $q->where('status', 'Live View')
                  ->orWhere('kondisi', 'Baik');
            })->count();
            
            // CCTV Non Aktif
            $nonAktif = $total - $aktif;
            
            // Area Kritis
            $areaKritis = (clone $query)->where(function($q) {
                $q->where('kategori_area_tercapture', 'like', '%kritis%')
                  ->orWhere('kategori_area_tercapture', 'like', '%critical%')
                  ->orWhere('coverage_lokasi', 'like', '%kritis%')
                  ->orWhere('coverage_lokasi', 'like', '%critical%');
            })->count();
            
            return response()->json([
                'success' => true,
                'total' => $total,
                'aktif' => $aktif,
                'nonAktif' => $nonAktif,
                'areaKritis' => $areaKritis,
                'percentageAktif' => $total > 0 ? round(($aktif / $total) * 100, 1) : 0,
                'percentageNonAktif' => $total > 0 ? round(($nonAktif / $total) * 100, 1) : 0,
            ]);
        } catch (Exception $e) {
            \Log::error('Error fetching company stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'total' => 0,
                'aktif' => 0,
                'nonAktif' => 0,
                'areaKritis' => 0,
            ], 500);
        }
    }

    /**
     * Return CCTV data grouped by selected company for DataTable (server-side processing)
     */
    public function getCompanyCctvData(Request $request)
    {
        try {
            $draw = $request->get('draw');
            $start = $request->get('start', 0);
            $length = $request->get('length', 10);
            $searchValue = $request->get('search')['value'] ?? '';
            $orderColumn = $request->get('order')[0]['column'] ?? 0;
            $orderDir = $request->get('order')[0]['dir'] ?? 'asc';
            $company = trim($request->query('company', '__all__'));

            // Column mapping (sesuai urutan kolom di DataTable)
            $columns = ['site', 'perusahaan', 'no_cctv', 'nama_cctv', 'status', 'kondisi', 'coverage_lokasi', 'coverage_detail_lokasi', 'kategori_area_tercapture', 'lokasi_pemasangan'];
            $orderColumnName = $columns[$orderColumn] ?? 'no_cctv';

            // Base query
            $query = CctvData::query();

            // Filter by company
            if ($company !== '__all__') {
                if (strcasecmp($company, 'Tidak Diketahui') === 0) {
                    $query->where(function ($q) {
                        $q->whereNull('perusahaan')
                          ->orWhere('perusahaan', '');
                    });
                } else {
                    $query->whereRaw('TRIM(perusahaan) = ?', [$company]);
                }
            }

            // Get total records before search
            $recordsTotal = $query->count();

            // Search functionality
            if (!empty($searchValue)) {
                $query->where(function($q) use ($searchValue) {
                    $q->where('site', 'like', '%' . $searchValue . '%')
                      ->orWhere('perusahaan', 'like', '%' . $searchValue . '%')
                      ->orWhere('no_cctv', 'like', '%' . $searchValue . '%')
                      ->orWhere('nama_cctv', 'like', '%' . $searchValue . '%')
                      ->orWhere('status', 'like', '%' . $searchValue . '%')
                      ->orWhere('kondisi', 'like', '%' . $searchValue . '%')
                      ->orWhere('coverage_lokasi', 'like', '%' . $searchValue . '%')
                      ->orWhere('coverage_detail_lokasi', 'like', '%' . $searchValue . '%')
                      ->orWhere('kategori_area_tercapture', 'like', '%' . $searchValue . '%')
                      ->orWhere('lokasi_pemasangan', 'like', '%' . $searchValue . '%');
                });
            }

            // Get filtered records count
            $recordsFiltered = $query->count();

            // Order and paginate
            $data = $query->orderBy($orderColumnName, $orderDir)
                         ->skip($start)
                         ->take($length)
                         ->get();

            // Format data for DataTable
            $formattedData = $data->map(function($item, $index) use ($start) {
                $statusBadge = $item->status === 'Live View' ? 'success' : 'secondary';
                $kondisiBadge = $item->kondisi === 'Baik' ? 'success' : 'warning';
                
                return [
                    'DT_RowIndex' => $start + $index + 1,
                    'site' => $item->site ?? '-',
                    'perusahaan' => $item->perusahaan ?? 'Tidak Diketahui',
                    'no_cctv' => $item->no_cctv ?? '-',
                    'nama_cctv' => $item->nama_cctv ?? '-',
                    'status' => '<span class="badge bg-' . $statusBadge . '">' . ($item->status ?? 'N/A') . '</span>',
                    'kondisi' => '<span class="badge bg-' . $kondisiBadge . '">' . ($item->kondisi ?? 'N/A') . '</span>',
                    'coverage_lokasi' => $item->coverage_lokasi ?? '-',
                    'coverage_detail_lokasi' => $item->coverage_detail_lokasi ?? '-',
                    'kategori_area_tercapture' => $item->kategori_area_tercapture ?? '-',
                    'lokasi_pemasangan' => $item->lokasi_pemasangan ?? '-',
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $formattedData
            ]);
        } catch (Exception $e) {
            \Log::error('Error fetching company CCTV data: ' . $e->getMessage());
            return response()->json([
                'draw' => intval($request->get('draw', 1)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal mengambil data CCTV.'
            ], 500);
        }
    }

    /**
     * Get default overview stats when database is not available
     */
    private function getDefaultOverviewStats()
    {
        return [
            'totalYtdInsiden' => 0,
            'ytdInsidenChange' => 0,
            'activeHazards' => 0,
            'hazardIncrease' => 0,
            'resolvedHazards' => 0,
            'resolvedHazardsChange' => 0,
            'monthlyHazards' => 0,
            'monthlyChange' => 0,
            'monthlyCount' => 0,
            'yearlyHazards' => 0,
            'yearlyChange' => 0,
            'yearlyCount' => 0,
            'chartData' => [],
            'totalHazards' => 0,
        ];
    }

    /**
     * Check if SSH tunnel is active
     */
    private function isTunnelActive()
    {
        $localPort = config('database.connections.pgsql_ssh.local_port', 5433);
        $connection = @fsockopen('127.0.0.1', $localPort, $errno, $errstr, 1);
        if ($connection) {
            fclose($connection);
            return true;
        }
        return false;
    }

    /**
     * Check ClickHouse connection status
     */
    public function checkClickHouseStatus()
    {
        try {
            $clickhouse = new ClickHouseService();
            $testResult = $clickhouse->testConnection();
            
            return response()->json([
                'connected' => $clickhouse->isConnected(),
                'test_result' => $testResult,
                'troubleshooting' => [
                    '1. Check if ClickHouse server is running on ' . config('database.connections.clickhouse.host') . ':' . config('database.connections.clickhouse.port'),
                    '2. Verify network connectivity (ping or telnet)',
                    '3. Check firewall rules',
                    '4. Verify credentials in .env file',
                    '5. Check ClickHouse server logs',
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'connected' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function pageView($routeName, $page = null)
    {
        // Skip jika routeName adalah hse-validation untuk menghindari konflik dengan route spesifik
        if ($routeName === 'hse-validation') {
            abort(404);
        }
        
        // Construct the view name based on the provided routeName and optional page parameter
        $viewName = ($page) ? $routeName.'.'.$page : $routeName;
        // Check if the constructed view exists
        if (\View::exists($viewName)) {
            // If the view exists, return the view
            return view($viewName);
        } else {
            // If the view doesn't exist, return a 404 error
            abort(404);
        }
    }
}