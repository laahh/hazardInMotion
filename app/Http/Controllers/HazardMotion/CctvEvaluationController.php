<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Services\ClickHouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class CctvEvaluationController extends Controller
{
    private $clickhouse;
    private $database = 'nitip';

    public function __construct()
    {
        $this->clickhouse = new ClickHouseService();
    }

    /**
     * Display CCTV evaluation dashboard
     */
    public function index()
    {
        try {
            // Check if ClickHouse is connected
            if (!$this->clickhouse->isConnected()) {
                Log::warning('ClickHouse is not connected. CCTV evaluation data will not be available.');
                return view('HazardMotion.admin.cctv-evaluation', [
                    'error' => 'ClickHouse tidak terhubung. Silakan periksa konfigurasi database.',
                    'data' => []
                ]);
            }

            // Get all data for dashboard
            $data = [
                'trendline' => $this->getTrendlineData(),
                'cameraDetails' => $this->getCameraDetails(),
                'currentWeekUtilization' => $this->getCurrentWeekUtilization(),
                'reportingScheme' => $this->getReportingScheme(),
                'totalReports' => $this->getTotalReports(),
                'coverageCctv' => $this->getCoverageCctv(),
                'coverageLocation' => $this->getCoverageLocation(),
                'locationReportingScheme' => $this->getLocationReportingScheme(),
                'operationalAreaUtilization' => $this->getOperationalAreaUtilization(),
                'nonReportingCctv' => $this->getNonReportingCctv(),
                'summary' => $this->getSummary(),
                'insights' => $this->getInsights(),
                'recommendations' => $this->getRecommendations(),
            ];

            return view('HazardMotion.admin.cctv-evaluation', compact('data'));
        } catch (Exception $e) {
            Log::error('Error loading CCTV evaluation dashboard: ' . $e->getMessage());
            return view('HazardMotion.admin.cctv-evaluation', [
                'error' => 'Terjadi kesalahan saat memuat data: ' . $e->getMessage(),
                'data' => []
            ]);
        }
    }

    /**
     * Get trendline data (utilization % and report count over time)
     */
    private function getTrendlineData()
    {
        $sql = "
            SELECT 
                toStartOfWeek(toDate(concat(toString(tahun), '-', toString(bulan), '-', toString(tanggal)))) as week_start,
                count(*) as total_laporan,
                count(DISTINCT name_tools_observation) as unique_cctv,
                round((count(DISTINCT name_tools_observation) * 100.0 / 5.0, 2) as utilisasi_persen
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
            AND tahun >= 2024
            GROUP BY week_start
            ORDER BY week_start DESC
            LIMIT 52
        ";

        try {
            $results = $this->clickhouse->query($sql);
            return array_reverse($results); // Reverse untuk chronological order
        } catch (Exception $e) {
            Log::error('Error fetching trendline data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get camera details (CCTV number, location, reports per week)
     */
    private function getCameraDetails()
    {
        $sql = "
            SELECT 
                name_tools_observation as nomor_cctv,
                nama_lokasi as lokasi,
                toStartOfWeek(toDate(concat(toString(tahun), '-', toString(bulan), '-', toString(tanggal)))) as week_start,
                count(*) as jumlah_laporan
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
            AND tahun >= 2024
            GROUP BY nomor_cctv, lokasi, week_start
            ORDER BY week_start DESC, jumlah_laporan DESC
        ";

        try {
            $results = $this->clickhouse->query($sql);
            return $results;
        } catch (Exception $e) {
            Log::error('Error fetching camera details: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get current week utilization percentage
     */
    private function getCurrentWeekUtilization()
    {
        // Get total CCTV dedicated (all time distinct count)
        $sqlTotal = "
            SELECT count(DISTINCT name_tools_observation) as total_cctv_dedicated
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
        ";

        // Get current week data
        $sqlCurrent = "
            SELECT 
                count(*) as total_laporan_minggu_ini,
                count(DISTINCT name_tools_observation) as cctv_aktif
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
            AND toStartOfWeek(toDate(concat(toString(tahun), '-', toString(bulan), '-', toString(tanggal)))) = toStartOfWeek(today())
        ";

        try {
            $totalResult = $this->clickhouse->query($sqlTotal);
            $currentResult = $this->clickhouse->query($sqlCurrent);
            
            $totalCctv = $totalResult[0]['total_cctv_dedicated'] ?? 5; // Default 5 jika tidak ada data
            $currentData = $currentResult[0] ?? ['total_laporan_minggu_ini' => 0, 'cctv_aktif' => 0];
            
            $utilisasi = $totalCctv > 0 ? round(($currentData['cctv_aktif'] / $totalCctv) * 100, 2) : 0;
            
            return [
                'total_laporan_minggu_ini' => $currentData['total_laporan_minggu_ini'] ?? 0,
                'cctv_aktif' => $currentData['cctv_aktif'] ?? 0,
                'total_cctv_dedicated' => $totalCctv,
                'utilisasi_persen' => $utilisasi
            ];
        } catch (Exception $e) {
            Log::error('Error fetching current week utilization: ' . $e->getMessage());
            return [
                'total_laporan_minggu_ini' => 0,
                'cctv_aktif' => 0,
                'total_cctv_dedicated' => 5,
                'utilisasi_persen' => 0
            ];
        }
    }

    /**
     * Get reporting scheme (Real Time vs Post Event)
     */
    private function getReportingScheme()
    {
        $sql = "
            SELECT 
                CASE 
                    WHEN name_tools_observation LIKE 'Real Time%' THEN 'Real Time'
                    WHEN name_tools_observation LIKE 'Post Event%' THEN 'Post Event'
                    ELSE 'Unknown'
                END as skema_pelaporan,
                count(*) as jumlah_laporan
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
            AND tahun >= 2024
            GROUP BY skema_pelaporan
            ORDER BY jumlah_laporan DESC
        ";

        try {
            $results = $this->clickhouse->query($sql);
            return $results;
        } catch (Exception $e) {
            Log::error('Error fetching reporting scheme: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total reports (Post Event & Real Time)
     */
    private function getTotalReports()
    {
        $sql = "
            SELECT 
                CASE 
                    WHEN name_tools_observation LIKE 'Real Time%' THEN 'Real Time'
                    WHEN name_tools_observation LIKE 'Post Event%' THEN 'Post Event'
                    ELSE 'Unknown'
                END as jenis_laporan,
                count(*) as total
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
            AND tahun >= 2024
            GROUP BY jenis_laporan
        ";

        try {
            $results = $this->clickhouse->query($sql);
            $total = 0;
            $breakdown = [];
            foreach ($results as $row) {
                $total += $row['total'];
                $breakdown[$row['jenis_laporan']] = $row['total'];
            }
            return [
                'total' => $total,
                'breakdown' => $breakdown
            ];
        } catch (Exception $e) {
            Log::error('Error fetching total reports: ' . $e->getMessage());
            return ['total' => 0, 'breakdown' => []];
        }
    }

    /**
     * Get CCTV coverage (CCTV that haven't reported anything)
     */
    private function getCoverageCctv()
    {
        // Expected CCTV types (dedicated PJA)
        $expectedCctv = [
            'Post Event - Mining Eyes',
            'Real Time - CCTV Portable',
            'Post Event - CCTV Portable',
            'Post Event - CCTV Support',
            'Real Time - CCTV Support'
        ];

        // Get all distinct CCTV from historical data
        $sqlAll = "
            SELECT DISTINCT name_tools_observation as cctv_name
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
        ";

        // Get CCTV that reported in current month
        $sqlActive = "
            SELECT DISTINCT name_tools_observation as cctv_name
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
            AND toStartOfMonth(toDate(concat(toString(tahun), '-', toString(bulan), '-', toString(tanggal)))) = toStartOfMonth(today())
        ";

        try {
            $allCctv = $this->clickhouse->query($sqlAll);
            $activeCctv = $this->clickhouse->query($sqlActive);
            
            $allNames = array_column($allCctv, 'cctv_name');
            $activeNames = array_column($activeCctv, 'cctv_name');
            
            // Combine expected and actual to get total
            $totalCctv = max(count($expectedCctv), count($allNames));
            
            // Find non-reporting CCTV
            $nonReporting = [];
            foreach ($expectedCctv as $cctv) {
                if (!in_array($cctv, $activeNames)) {
                    $nonReporting[] = $cctv;
                }
            }
            
            return [
                'total_cctv' => $totalCctv,
                'active_cctv' => count($activeNames),
                'non_reporting' => $nonReporting
            ];
        } catch (Exception $e) {
            Log::error('Error fetching coverage CCTV: ' . $e->getMessage());
            return [
                'total_cctv' => count($expectedCctv),
                'active_cctv' => 0,
                'non_reporting' => $expectedCctv
            ];
        }
    }

    /**
     * Get coverage detail by location
     */
    private function getCoverageLocation()
    {
        $sql = "
            SELECT 
                nama_lokasi as lokasi,
                count(DISTINCT name_tools_observation) as jumlah_cctv,
                count(*) as total_laporan
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
            AND tahun >= 2024
            GROUP BY lokasi
            ORDER BY total_laporan DESC
        ";

        try {
            $results = $this->clickhouse->query($sql);
            return $results;
        } catch (Exception $e) {
            Log::error('Error fetching coverage location: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get reporting scheme per location
     */
    private function getLocationReportingScheme()
    {
        $sql = "
            SELECT 
                nama_lokasi as lokasi,
                CASE 
                    WHEN name_tools_observation LIKE 'Real Time%' THEN 'Real Time'
                    WHEN name_tools_observation LIKE 'Post Event%' THEN 'Post Event'
                    ELSE 'Unknown'
                END as skema_pelaporan,
                count(*) as jumlah_laporan
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
            AND tahun >= 2024
            GROUP BY lokasi, skema_pelaporan
            ORDER BY lokasi, jumlah_laporan DESC
        ";

        try {
            $results = $this->clickhouse->query($sql);
            // Group by location
            $grouped = [];
            foreach ($results as $row) {
                if (!isset($grouped[$row['lokasi']])) {
                    $grouped[$row['lokasi']] = [];
                }
                $grouped[$row['lokasi']][] = $row;
            }
            return $grouped;
        } catch (Exception $e) {
            Log::error('Error fetching location reporting scheme: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get utilization percentage per operational area
     */
    private function getOperationalAreaUtilization()
    {
        // First get total reports for percentage calculation
        $sqlTotal = "
            SELECT count(*) as total_all
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
            AND tahun >= 2024
        ";

        $sql = "
            SELECT 
                nama_lokasi as area_operasional,
                count(DISTINCT name_tools_observation) as cctv_aktif,
                count(*) as total_laporan
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
            AND tahun >= 2024
            GROUP BY area_operasional
            ORDER BY total_laporan DESC
        ";

        try {
            $totalResult = $this->clickhouse->query($sqlTotal);
            $totalAll = $totalResult[0]['total_all'] ?? 1; // Avoid division by zero
            
            $results = $this->clickhouse->query($sql);
            
            // Calculate percentage for each area
            foreach ($results as &$row) {
                $row['prosentase_utilisasi'] = round(($row['total_laporan'] / $totalAll) * 100, 2);
            }
            
            return $results;
        } catch (Exception $e) {
            Log::error('Error fetching operational area utilization: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get list of CCTV dedicated PJA that haven't reported at all
     */
    private function getNonReportingCctv()
    {
        // Get all CCTV types that should be dedicated PJA
        $expectedCctv = [
            'Post Event - Mining Eyes',
            'Real Time - CCTV Portable',
            'Post Event - CCTV Portable',
            'Post Event - CCTV Support',
            'Real Time - CCTV Support'
        ];

        // Get CCTV that have reported in last 3 months
        $sql = "
            SELECT DISTINCT name_tools_observation as cctv_name
            FROM nitip.vm_hazard_inspeksi
            WHERE name_tools_observation IN (
                'Post Event - Mining Eyes',
                'Real Time - CCTV Portable',
                'Post Event - CCTV Portable',
                'Post Event - CCTV Support',
                'Real Time - CCTV Support'
            )
            AND toDate(concat(toString(tahun), '-', toString(bulan), '-', toString(tanggal))) >= today() - INTERVAL 3 MONTH
        ";

        try {
            $reportedCctv = $this->clickhouse->query($sql);
            $reportedNames = array_column($reportedCctv, 'cctv_name');
            
            $nonReporting = [];
            foreach ($expectedCctv as $cctv) {
                if (!in_array($cctv, $reportedNames)) {
                    $nonReporting[] = $cctv;
                }
            }
            
            return $nonReporting;
        } catch (Exception $e) {
            Log::error('Error fetching non-reporting CCTV: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get summary analysis
     */
    private function getSummary()
    {
        try {
            $totalReports = $this->getTotalReports();
            $currentWeek = $this->getCurrentWeekUtilization();
            $coverage = $this->getCoverageCctv();
            $nonReporting = $this->getNonReportingCctv();
            
            return [
                'total_laporan' => $totalReports['total'],
                'utilisasi_minggu_ini' => $currentWeek['utilisasi_persen'] ?? 0,
                'cctv_aktif' => $coverage['active_cctv'] ?? 0,
                'cctv_total' => $coverage['total_cctv'] ?? 0,
                'cctv_tidak_melapor' => count($nonReporting),
                'persentase_aktif' => $coverage['total_cctv'] > 0 
                    ? round(($coverage['active_cctv'] / $coverage['total_cctv']) * 100, 2) 
                    : 0
            ];
        } catch (Exception $e) {
            Log::error('Error generating summary: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get strategic insights
     */
    private function getInsights()
    {
        // This will be populated based on data analysis
        // For now, return structure
        return [
            'perbandingan_perusahaan' => 'Analisis perbandingan akan ditampilkan setelah data tersedia',
            'red_flags' => [],
            'penyebab_rendah_utilisasi' => []
        ];
    }

    /**
     * Get recommendations
     */
    private function getRecommendations()
    {
        return [
            [
                'title' => 'Audit Teknis CCTV',
                'description' => 'Lakukan audit menyeluruh terhadap semua CCTV dedicated PJA untuk memastikan peralatan berfungsi dengan baik dan terhubung ke sistem monitoring.',
                'priority' => 'high'
            ],
            [
                'title' => 'Pelatihan Operator',
                'description' => 'Adakan pelatihan khusus untuk operator PJA mengenai penggunaan CCTV dan prosedur pelaporan yang benar, baik Real Time maupun Post Event.',
                'priority' => 'high'
            ],
            [
                'title' => 'Penugasan PIC Per Area',
                'description' => 'Tetapkan Person In Charge (PIC) untuk setiap area operasional yang bertanggung jawab memastikan CCTV digunakan dan laporan dibuat secara konsisten.',
                'priority' => 'medium'
            ],
            [
                'title' => 'Target Utilisasi',
                'description' => 'Tetapkan target utilisasi CCTV minimal 80% per minggu untuk setiap CCTV dedicated PJA dan lakukan monitoring berkala.',
                'priority' => 'medium'
            ],
            [
                'title' => 'Sistem Reward & Penalty',
                'description' => 'Implementasikan sistem reward untuk area/operator yang konsisten menggunakan CCTV dan penalty untuk yang tidak memenuhi target utilisasi.',
                'priority' => 'low'
            ]
        ];
    }
}

