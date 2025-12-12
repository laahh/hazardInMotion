<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ChatbotRuleService
{
    /**
     * Daftar rules untuk deteksi intent dan konversi ke SQL
     */
    private $rules = [
        // Rule 1: Query berdasarkan site
        [
            'id' => 'query_by_site',
            'name' => 'Query CCTV berdasarkan Site',
            'keywords' => ['site', 'lokasi site', 'di site', 'site mana', 'cctv site'],
            'patterns' => [
                '/cctv.*site\s+([A-Z0-9\s]+?)(?:\s|$)/i',
                '/site\s+([A-Z0-9\s]+?)(?:\s|$).*cctv/i',
                '/cctv.*di\s+site\s+([A-Z0-9\s]+?)(?:\s|$)/i',
            ],
            'description' => 'Mencari CCTV berdasarkan nama site',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE site = '{value}'",
            'extract_value' => true,
            'column' => 'site',
        ],

        // Rule 2: Query berdasarkan perusahaan
        [
            'id' => 'query_by_perusahaan',
            'name' => 'Query CCTV berdasarkan Perusahaan',
            'keywords' => ['perusahaan', 'company', 'pt', 'cctv perusahaan'],
            'patterns' => [
                '/cctv.*perusahaan\s+([^\s]+)/i',
                '/perusahaan\s+([^\s]+).*cctv/i',
                '/cctv.*pt\s+([^\s]+)/i',
            ],
            'description' => 'Mencari CCTV berdasarkan nama perusahaan',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE perusahaan LIKE '%{value}%'",
            'extract_value' => true,
            'column' => 'perusahaan',
        ],

        // Rule 3: Query berdasarkan status
        [
            'id' => 'query_by_status',
            'name' => 'Query CCTV berdasarkan Status',
            'keywords' => ['status', 'live', 'offline', 'aktif', 'tidak aktif'],
            'patterns' => [
                '/cctv.*status\s+([^\s]+)/i',
                '/cctv.*yang\s+(live|offline|aktif|tidak\s+aktif)/i',
                '/status\s+(live|offline|aktif|tidak\s+aktif).*cctv/i',
            ],
            'description' => 'Mencari CCTV berdasarkan status',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE status = '{value}'",
            'extract_value' => true,
            'column' => 'status',
            'value_mapping' => [
                'live' => 'Live View',
                'offline' => 'Offline',
                'aktif' => 'Live View',
                'tidak aktif' => 'Offline',
            ],
        ],

        // Rule 4: Query berdasarkan kondisi
        [
            'id' => 'query_by_kondisi',
            'name' => 'Query CCTV berdasarkan Kondisi',
            'keywords' => ['kondisi', 'baik', 'breakdown', 'repair', 'dismantle', 'maintenance'],
            'patterns' => [
                '/cctv.*kondisi\s+([^\s]+)/i',
                '/cctv.*yang\s+(baik|breakdown|repair|dismantle|rusak|maintenance)/i',
            ],
            'description' => 'Mencari CCTV berdasarkan kondisi',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE kondisi = '{value}'",
            'extract_value' => true,
            'column' => 'kondisi',
            'value_mapping' => [
                'baik' => 'Baik',
                'breakdown' => 'Breakdown',
                'repair' => 'Repair',
                'dismantle' => 'Dismantle',
                'rusak' => 'Breakdown', // Mapping rusak ke Breakdown
                'maintenance' => 'Repair', // Mapping maintenance ke Repair
            ],
        ],

        // Rule 5: Query berdasarkan nama CCTV
        [
            'id' => 'query_by_nama',
            'name' => 'Query CCTV berdasarkan Nama',
            'keywords' => ['nama', 'cctv dengan nama', 'cari cctv'],
            'patterns' => [
                '/cctv.*nama\s+([^\s]+)/i',
                '/cari\s+cctv\s+([^\s]+)/i',
                '/cctv\s+([^\s]+)/i',
            ],
            'description' => 'Mencari CCTV berdasarkan nama',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE nama_cctv LIKE '%{value}%'",
            'extract_value' => true,
            'column' => 'nama_cctv',
        ],

        // Rule 6: Query berdasarkan no_cctv
        [
            'id' => 'query_by_no_cctv',
            'name' => 'Query CCTV berdasarkan Nomor CCTV',
            'keywords' => ['no cctv', 'nomor cctv', 'cctv nomor'],
            'patterns' => [
                '/cctv.*no\s+([A-Z0-9-]+)/i',
                '/no\s+cctv\s+([A-Z0-9-]+)/i',
                '/nomor\s+cctv\s+([A-Z0-9-]+)/i',
            ],
            'description' => 'Mencari CCTV berdasarkan nomor CCTV',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE no_cctv = '{value}'",
            'extract_value' => true,
            'column' => 'no_cctv',
        ],

        // Rule 7: Query berdasarkan lokasi pemasangan
        [
            'id' => 'query_by_lokasi',
            'name' => 'Query CCTV berdasarkan Lokasi Pemasangan',
            'keywords' => ['lokasi', 'di lokasi', 'lokasi pemasangan'],
            'patterns' => [
                '/cctv.*lokasi\s+([^\s]+)/i',
                '/lokasi\s+([^\s]+).*cctv/i',
            ],
            'description' => 'Mencari CCTV berdasarkan lokasi pemasangan',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE lokasi_pemasangan LIKE '%{value}%'",
            'extract_value' => true,
            'column' => 'lokasi_pemasangan',
        ],

        // Rule 8: Query berdasarkan coverage lokasi
        [
            'id' => 'query_by_coverage',
            'name' => 'Query CCTV berdasarkan Coverage Lokasi',
            'keywords' => ['coverage', 'area coverage', 'cakupan'],
            'patterns' => [
                '/cctv.*coverage\s+([^\s]+)/i',
                '/coverage\s+([^\s]+).*cctv/i',
            ],
            'description' => 'Mencari CCTV berdasarkan coverage lokasi',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE coverage_lokasi LIKE '%{value}%'",
            'extract_value' => true,
            'column' => 'coverage_lokasi',
        ],

        // Rule 9: Query berdasarkan tipe CCTV
        [
            'id' => 'query_by_tipe',
            'name' => 'Query CCTV berdasarkan Tipe',
            'keywords' => ['tipe', 'jenis cctv', 'merk', 'hikvision'],
            'patterns' => [
                '/cctv.*tipe\s+([^\s]+)/i',
                '/tipe\s+([^\s]+).*cctv/i',
                '/cctv.*merk\s+([^\s]+)/i',
            ],
            'description' => 'Mencari CCTV berdasarkan tipe/merk',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE tipe_cctv LIKE '%{value}%'",
            'extract_value' => true,
            'column' => 'tipe_cctv',
        ],

        // Rule 10: Query berdasarkan connected status
        [
            'id' => 'query_by_connected',
            'name' => 'Query CCTV berdasarkan Status Koneksi',
            'keywords' => ['connected', 'terhubung', 'koneksi'],
            'patterns' => [
                '/cctv.*connected/i',
                '/cctv.*terhubung/i',
                '/cctv.*yang\s+(connected|terhubung|tidak\s+terhubung)/i',
            ],
            'description' => 'Mencari CCTV berdasarkan status koneksi',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE connected = '{value}'",
            'extract_value' => true,
            'column' => 'connected',
            'value_mapping' => [
                'connected' => 'YES',
                'terhubung' => 'YES',
                'tidak terhubung' => 'NO',
            ],
        ],

        // Rule 11: Query statistik - total CCTV
        [
            'id' => 'stat_total_cctv',
            'name' => 'Statistik Total CCTV',
            'keywords' => ['total cctv', 'berapa banyak cctv', 'jumlah cctv', 'statistik cctv'],
            'patterns' => [
                '/total\s+cctv/i',
                '/berapa\s+banyak\s+cctv/i',
                '/jumlah\s+cctv/i',
                '/statistik\s+cctv/i',
            ],
            'description' => 'Menampilkan total jumlah CCTV',
            'sql_template' => "SELECT COUNT(*) as total FROM cctv_data_bmo2",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 12: Query statistik - CCTV per site
        [
            'id' => 'stat_cctv_per_site',
            'name' => 'Statistik CCTV per Site',
            'keywords' => ['cctv per site', 'distribusi site', 'jumlah per site'],
            'patterns' => [
                '/cctv\s+per\s+site/i',
                '/distribusi\s+site/i',
                '/jumlah\s+per\s+site/i',
            ],
            'description' => 'Menampilkan distribusi CCTV per site',
            'sql_template' => "SELECT site, COUNT(*) as jumlah FROM cctv_data_bmo2 GROUP BY site ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 13: Query statistik - CCTV per perusahaan
        [
            'id' => 'stat_cctv_per_perusahaan',
            'name' => 'Statistik CCTV per Perusahaan',
            'keywords' => ['cctv per perusahaan', 'distribusi perusahaan'],
            'patterns' => [
                '/cctv\s+per\s+perusahaan/i',
                '/distribusi\s+perusahaan/i',
            ],
            'description' => 'Menampilkan distribusi CCTV per perusahaan',
            'sql_template' => "SELECT perusahaan, COUNT(*) as jumlah FROM cctv_data_bmo2 GROUP BY perusahaan ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 14: Query statistik - CCTV per status
        [
            'id' => 'stat_cctv_per_status',
            'name' => 'Statistik CCTV per Status',
            'keywords' => ['cctv per status', 'distribusi status'],
            'patterns' => [
                '/cctv\s+per\s+status/i',
                '/distribusi\s+status/i',
            ],
            'description' => 'Menampilkan distribusi CCTV per status',
            'sql_template' => "SELECT status, COUNT(*) as jumlah FROM cctv_data_bmo2 GROUP BY status ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 14b: Query statistik - CCTV per kondisi
        [
            'id' => 'stat_cctv_per_kondisi',
            'name' => 'Statistik CCTV per Kondisi',
            'keywords' => ['cctv per kondisi', 'distribusi kondisi', 'breakdown kondisi', 'statistik kondisi'],
            'patterns' => [
                '/cctv\s+per\s+kondisi/i',
                '/distribusi\s+kondisi/i',
                '/breakdown\s+kondisi/i',
                '/statistik\s+kondisi/i',
            ],
            'description' => 'Menampilkan distribusi CCTV per kondisi (Baik, Breakdown, Repair, Dismantle)',
            'sql_template' => "SELECT kondisi, COUNT(*) as jumlah FROM cctv_data_bmo2 WHERE kondisi IS NOT NULL GROUP BY kondisi ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 15: Query berdasarkan kategori area tercapture
        [
            'id' => 'query_by_kategori_area',
            'name' => 'Query CCTV berdasarkan Kategori Area',
            'keywords' => ['kategori area', 'area kritis', 'area non kritis', 'area tercapture', 'tampilkan area', 'data area'],
            'patterns' => [
                '/cctv.*area\s+(kritis|non\s+kritis)/i',
                '/area\s+(kritis|non\s+kritis).*cctv/i',
                '/cctv.*kategori\s+area/i',
                '/tampilkan.*area\s+(kritis|non\s+kritis)/i',
                '/data\s+area\s+(kritis|non\s+kritis)/i',
            ],
            'description' => 'Mencari CCTV berdasarkan kategori area tercapture',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE kategori_area_tercapture = '{value}'",
            'extract_value' => true,
            'column' => 'kategori_area_tercapture',
            'value_mapping' => [
                'kritis' => 'Area Kritis',
                'non kritis' => 'Area Non Kritis',
            ],
        ],

        // Rule 16: Statistik - Total Area Kritis
        [
            'id' => 'stat_total_area_kritis',
            'name' => 'Statistik Total Area Kritis',
            'keywords' => ['total area kritis', 'berapa area kritis', 'jumlah area kritis', 'area kritis ada berapa', 'data area kritis'],
            'patterns' => [
                '/total\s+area\s+kritis/i',
                '/berapa\s+area\s+kritis/i',
                '/jumlah\s+area\s+kritis/i',
                '/area\s+kritis.*ada\s+berapa/i',
                '/tampilkan.*area\s+kritis.*berapa/i',
            ],
            'description' => 'Menampilkan total jumlah area kritis',
            'sql_template' => "SELECT COUNT(*) as total FROM cctv_data_bmo2 WHERE kategori_area_tercapture = 'Area Kritis'",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 16b: Statistik - Total Area Non Kritis
        [
            'id' => 'stat_total_area_non_kritis',
            'name' => 'Statistik Total Area Non Kritis',
            'keywords' => ['total area non kritis', 'berapa area non kritis', 'jumlah area non kritis', 'area non kritis ada berapa', 'data area non kritis', 'tampilkan area non kritis'],
            'patterns' => [
                '/total\s+area\s+non\s+kritis/i',
                '/berapa\s+area\s+non\s+kritis/i',
                '/jumlah\s+area\s+non\s+kritis/i',
                '/area\s+non\s+kritis.*ada\s+berapa/i',
                '/tampilkan.*area\s+non\s+kritis.*berapa/i',
                '/tampilkan\s+data\s+area\s+non\s+kritis/i',
            ],
            'description' => 'Menampilkan total jumlah area non kritis',
            'sql_template' => "SELECT COUNT(*) as total FROM cctv_data_bmo2 WHERE kategori_area_tercapture = 'Area Non Kritis'",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 17: Statistik - Total Area (semua kategori)
        [
            'id' => 'stat_total_area',
            'name' => 'Statistik Total Area',
            'keywords' => ['total area', 'berapa area', 'jumlah area', 'total kategori area'],
            'patterns' => [
                '/total\s+area/i',
                '/berapa\s+area/i',
                '/jumlah\s+area/i',
                '/total\s+kategori\s+area/i',
            ],
            'description' => 'Menampilkan total semua area',
            'sql_template' => "SELECT COUNT(*) as total FROM cctv_data_bmo2 WHERE kategori_area_tercapture IS NOT NULL",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 18: Statistik - Jumlah CCTV Area Kritis
        [
            'id' => 'stat_jumlah_cctv_area_kritis',
            'name' => 'Statistik Jumlah CCTV Area Kritis',
            'keywords' => ['jumlah cctv area kritis', 'cctv area kritis', 'berapa cctv area kritis'],
            'patterns' => [
                '/jumlah\s+cctv\s+area\s+kritis/i',
                '/cctv\s+area\s+kritis/i',
                '/berapa\s+cctv\s+area\s+kritis/i',
            ],
            'description' => 'Menampilkan jumlah CCTV di area kritis',
            'sql_template' => "SELECT COUNT(*) as jumlah FROM cctv_data_bmo2 WHERE kategori_area_tercapture = 'Area Kritis'",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 19: Statistik - Distribusi Area Kritis vs Non Kritis
        [
            'id' => 'stat_distribusi_area',
            'name' => 'Statistik Distribusi Area Kritis vs Non Kritis',
            'keywords' => ['distribusi area', 'perbandingan area', 'area kritis vs non kritis'],
            'patterns' => [
                '/distribusi\s+area/i',
                '/perbandingan\s+area/i',
                '/area\s+kritis\s+vs/i',
            ],
            'description' => 'Menampilkan distribusi area kritis vs non kritis',
            'sql_template' => "SELECT kategori_area_tercapture, COUNT(*) as jumlah FROM cctv_data_bmo2 WHERE kategori_area_tercapture IS NOT NULL GROUP BY kategori_area_tercapture ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 20: Statistik - CCTV per Kategori Area
        [
            'id' => 'stat_cctv_per_kategori_area',
            'name' => 'Statistik CCTV per Kategori Area',
            'keywords' => ['cctv per kategori area', 'distribusi kategori area'],
            'patterns' => [
                '/cctv\s+per\s+kategori\s+area/i',
                '/distribusi\s+kategori\s+area/i',
            ],
            'description' => 'Menampilkan distribusi CCTV per kategori area',
            'sql_template' => "SELECT kategori_area_tercapture, COUNT(*) as jumlah FROM cctv_data_bmo2 WHERE kategori_area_tercapture IS NOT NULL GROUP BY kategori_area_tercapture ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 21: Query - CCTV di Area Kritis
        [
            'id' => 'query_cctv_area_kritis',
            'name' => 'Query CCTV di Area Kritis',
            'keywords' => ['cctv area kritis', 'cctv di area kritis', 'daftar cctv area kritis'],
            'patterns' => [
                '/cctv\s+area\s+kritis/i',
                '/cctv\s+di\s+area\s+kritis/i',
                '/daftar\s+cctv\s+area\s+kritis/i',
            ],
            'description' => 'Menampilkan daftar CCTV di area kritis',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE kategori_area_tercapture = 'Area Kritis'",
            'extract_value' => false,
        ],

        // Rule 22: Statistik - CCTV per Kategori Aktivitas
        [
            'id' => 'stat_cctv_per_kategori_aktivitas',
            'name' => 'Statistik CCTV per Kategori Aktivitas',
            'keywords' => ['cctv per aktivitas', 'distribusi aktivitas', 'kategori aktivitas'],
            'patterns' => [
                '/cctv\s+per\s+aktivitas/i',
                '/distribusi\s+aktivitas/i',
                '/kategori\s+aktivitas/i',
            ],
            'description' => 'Menampilkan distribusi CCTV per kategori aktivitas tercapture',
            'sql_template' => "SELECT kategori_aktivitas_tercapture, COUNT(*) as jumlah FROM cctv_data_bmo2 WHERE kategori_aktivitas_tercapture IS NOT NULL GROUP BY kategori_aktivitas_tercapture ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 23: Statistik - Overview Area Kritis (Ringkasan)
        [
            'id' => 'stat_overview_area_kritis',
            'name' => 'Overview Area Kritis',
            'keywords' => ['overview area kritis', 'ringkasan area kritis', 'summary area kritis'],
            'patterns' => [
                '/overview\s+area\s+kritis/i',
                '/ringkasan\s+area\s+kritis/i',
                '/summary\s+area\s+kritis/i',
            ],
            'description' => 'Menampilkan overview/ringkasan area kritis',
            'sql_template' => "SELECT 
                COUNT(*) as total_cctv,
                SUM(CASE WHEN status = 'Live View' THEN 1 ELSE 0 END) as cctv_online,
                SUM(CASE WHEN status = 'Offline' OR (status IS NOT NULL AND status != 'Live View') THEN 1 ELSE 0 END) as cctv_offline,
                SUM(CASE WHEN kondisi = 'Baik' THEN 1 ELSE 0 END) as cctv_baik,
                SUM(CASE WHEN kondisi IN ('Breakdown', 'Repair', 'Dismantle') THEN 1 ELSE 0 END) as cctv_tidak_baik
                FROM cctv_data_bmo2",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 24: Statistik - Coverage Lokasi
        [
            'id' => 'stat_coverage_lokasi',
            'name' => 'Statistik Coverage Lokasi',
            'keywords' => ['coverage lokasi', 'distribusi coverage', 'cakupan lokasi'],
            'patterns' => [
                '/coverage\s+lokasi/i',
                '/distribusi\s+coverage/i',
                '/cakupan\s+lokasi/i',
            ],
            'description' => 'Menampilkan distribusi coverage lokasi',
            'sql_template' => "SELECT coverage_lokasi, COUNT(*) as jumlah FROM cctv_data_bmo2 WHERE coverage_lokasi IS NOT NULL GROUP BY coverage_lokasi ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 25: Query - CCTV dengan kondisi baik di area kritis
        [
            'id' => 'query_cctv_baik_area_kritis',
            'name' => 'Query CCTV Kondisi Baik di Area Kritis',
            'keywords' => ['cctv baik area kritis', 'cctv kondisi baik area kritis'],
            'patterns' => [
                '/cctv\s+baik\s+area\s+kritis/i',
                '/cctv\s+kondisi\s+baik\s+area\s+kritis/i',
            ],
            'description' => 'Menampilkan CCTV dengan kondisi baik di area kritis',
            'sql_template' => "SELECT * FROM cctv_data_bmo2 WHERE kategori_area_tercapture = 'Area Kritis' AND kondisi = 'Baik'",
            'extract_value' => false,
        ],

        // Rule 26: Statistik - Area Kritis per Site
        [
            'id' => 'stat_area_kritis_per_site',
            'name' => 'Statistik Area Kritis per Site',
            'keywords' => ['area kritis per site', 'distribusi area kritis site'],
            'patterns' => [
                '/area\s+kritis\s+per\s+site/i',
                '/distribusi\s+area\s+kritis\s+site/i',
            ],
            'description' => 'Menampilkan distribusi area kritis per site',
            'sql_template' => "SELECT site, COUNT(*) as jumlah FROM cctv_data_bmo2 WHERE kategori_area_tercapture = 'Area Kritis' GROUP BY site ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 27: Statistik - Area Kritis per Perusahaan
        [
            'id' => 'stat_area_kritis_per_perusahaan',
            'name' => 'Statistik Area Kritis per Perusahaan',
            'keywords' => ['area kritis per perusahaan', 'distribusi area kritis perusahaan'],
            'patterns' => [
                '/area\s+kritis\s+per\s+perusahaan/i',
                '/distribusi\s+area\s+kritis\s+perusahaan/i',
            ],
            'description' => 'Menampilkan distribusi area kritis per perusahaan',
            'sql_template' => "SELECT perusahaan, COUNT(*) as jumlah FROM cctv_data_bmo2 WHERE kategori_area_tercapture = 'Area Kritis' GROUP BY perusahaan ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
        ],

        // Rule 28: Query dengan multiple conditions (akan diproses oleh AI)
        [
            'id' => 'query_complex',
            'name' => 'Query Kompleks dengan Multiple Conditions',
            'keywords' => ['dan', 'atau', 'yang', 'dimana'],
            'patterns' => [
                '/cctv.*yang.*dan/i',
                '/cctv.*dimana/i',
            ],
            'description' => 'Query kompleks dengan multiple conditions',
            'sql_template' => null, // Akan di-generate oleh AI
            'extract_value' => false,
            'is_complex' => true,
        ],

        // Rule 29: Query - Daftar Insiden
        [
            'id' => 'query_insiden_list',
            'name' => 'Query Daftar Insiden',
            'keywords' => ['insiden', 'kejadian', 'kecelakaan', 'accident', 'incident', 'daftar insiden', 'list insiden', 'jelaskan insiden', 'ceritakan insiden'],
            'patterns' => [
                '/insiden/i',
                '/kejadian/i',
                '/kecelakaan/i',
                '/daftar\s+insiden/i',
                '/list\s+insiden/i',
                '/ceritakan\s+insiden/i',
                '/kejadian\s+apa\s+saja/i',
                '/jelaskan\s+insiden/i',
                '/jelaskan\s+secara\s+detail\s+insiden/i',
                '/jelaskan\s+detail\s+insiden/i',
            ],
            'description' => 'Menampilkan daftar insiden',
            'sql_template' => "SELECT * FROM insiden_tabel ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => false,
            'is_incident' => true,
        ],

        // Rule 30: Query - Insiden berdasarkan site
        [
            'id' => 'query_insiden_by_site',
            'name' => 'Query Insiden berdasarkan Site',
            'keywords' => ['insiden site', 'kejadian site', 'insiden di site', 'insiden di', 'jelaskan insiden di', 'ceritakan insiden di'],
            'patterns' => [
                '/insiden.*site\s+([A-Z0-9\s]+)/i',
                '/kejadian.*site\s+([A-Z0-9\s]+)/i',
                '/insiden.*di\s+site\s+([A-Z0-9\s]+)/i',
                '/insiden\s+di\s+([A-Z0-9\s]+)/i',
                '/jelaskan.*insiden.*di\s+([A-Z0-9\s]+)/i',
                '/ceritakan.*insiden.*di\s+([A-Z0-9\s]+)/i',
                '/jelaskan.*insiden.*yang\s+ada\s+di\s+([A-Z0-9\s]+)/i',
                '/insiden.*yang\s+ada\s+di\s+([A-Z0-9\s]+)/i',
                '/insiden\s+apa\s+saja.*di\s+([A-Z0-9\s]+)/i',
                '/kejadian\s+apa\s+saja.*di\s+([A-Z0-9\s]+)/i',
            ],
            'description' => 'Mencari insiden berdasarkan site',
            'sql_template' => "SELECT * FROM insiden_tabel WHERE TRIM(UPPER(site)) = TRIM(UPPER('{value}')) OR TRIM(UPPER(REPLACE(site, ' ', ''))) = TRIM(UPPER(REPLACE('{value}', ' ', ''))) OR TRIM(UPPER(REPLACE(REPLACE(site, ' ', ''), '-', ''))) = TRIM(UPPER(REPLACE(REPLACE('{value}', ' ', ''), '-', ''))) ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => true,
            'column' => 'site',
            'is_incident' => true,
        ],

        // Rule 31: Query - Insiden berdasarkan perusahaan
        [
            'id' => 'query_insiden_by_perusahaan',
            'name' => 'Query Insiden berdasarkan Perusahaan',
            'keywords' => ['insiden perusahaan', 'kejadian perusahaan'],
            'patterns' => [
                '/insiden.*perusahaan\s+([^\s]+)/i',
                '/kejadian.*perusahaan\s+([^\s]+)/i',
            ],
            'description' => 'Mencari insiden berdasarkan perusahaan',
            'sql_template' => "SELECT * FROM insiden_tabel WHERE perusahaan LIKE '%{value}%' ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => true,
            'column' => 'perusahaan',
            'is_incident' => true,
        ],

        // Rule 32: Query - Insiden berdasarkan kategori
        [
            'id' => 'query_insiden_by_kategori',
            'name' => 'Query Insiden berdasarkan Kategori',
            'keywords' => ['insiden kategori', 'nearmiss', 'accident', 'kecelakaan kategori'],
            'patterns' => [
                '/insiden.*kategori\s+([^\s]+)/i',
                '/nearmiss/i',
                '/accident/i',
                '/kecelakaan/i',
            ],
            'description' => 'Mencari insiden berdasarkan kategori',
            'sql_template' => "SELECT * FROM insiden_tabel WHERE kategori = '{value}' ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => true,
            'column' => 'kategori',
            'value_mapping' => [
                'nearmiss' => 'Nearmiss',
                'accident' => 'Accident',
                'kecelakaan' => 'Accident',
            ],
            'is_incident' => true,
        ],

        // Rule 33: Statistik - Total Insiden
        [
            'id' => 'stat_total_insiden',
            'name' => 'Statistik Total Insiden',
            'keywords' => ['total insiden', 'berapa insiden', 'jumlah insiden', 'statistik insiden'],
            'patterns' => [
                '/total\s+insiden/i',
                '/berapa\s+insiden/i',
                '/jumlah\s+insiden/i',
                '/statistik\s+insiden/i',
            ],
            'description' => 'Menampilkan total jumlah insiden',
            'sql_template' => "SELECT COUNT(DISTINCT no_kecelakaan) as total FROM insiden_tabel",
            'extract_value' => false,
            'is_statistic' => true,
            'is_incident' => true,
        ],

        // Rule 34: Statistik - Insiden per Kategori
        [
            'id' => 'stat_insiden_per_kategori',
            'name' => 'Statistik Insiden per Kategori',
            'keywords' => ['insiden per kategori', 'distribusi insiden', 'insiden kategori'],
            'patterns' => [
                '/insiden\s+per\s+kategori/i',
                '/distribusi\s+insiden/i',
                '/insiden\s+kategori/i',
            ],
            'description' => 'Menampilkan distribusi insiden per kategori',
            'sql_template' => "SELECT kategori, COUNT(DISTINCT no_kecelakaan) as jumlah FROM insiden_tabel WHERE kategori IS NOT NULL GROUP BY kategori ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
            'is_incident' => true,
        ],

        // Rule 35: Statistik - Insiden per Site
        [
            'id' => 'stat_insiden_per_site',
            'name' => 'Statistik Insiden per Site',
            'keywords' => ['insiden per site', 'distribusi insiden site'],
            'patterns' => [
                '/insiden\s+per\s+site/i',
                '/distribusi\s+insiden\s+site/i',
            ],
            'description' => 'Menampilkan distribusi insiden per site',
            'sql_template' => "SELECT site, COUNT(DISTINCT no_kecelakaan) as jumlah FROM insiden_tabel WHERE site IS NOT NULL GROUP BY site ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
            'is_incident' => true,
        ],

        // Rule 36: Query - Tampilkan insiden di site (variasi pertanyaan)
        [
            'id' => 'query_insiden_tampilkan_site',
            'name' => 'Query Tampilkan Insiden di Site',
            'keywords' => ['tampilkan insiden', 'lihat insiden', 'show insiden', 'tampilkan kejadian', 'lihat kejadian', 'insiden di', 'jelaskan insiden', 'ceritakan insiden'],
            'patterns' => [
                '/tampilkan\s+insiden.*site\s+([A-Z0-9\s]+)/i',
                '/lihat\s+insiden.*site\s+([A-Z0-9\s]+)/i',
                '/tampilkan\s+insiden\s+di\s+([A-Z0-9\s]+)/i',
                '/lihat\s+insiden\s+di\s+([A-Z0-9\s]+)/i',
                '/insiden\s+di\s+([A-Z0-9\s]+)/i',
                '/kejadian\s+di\s+([A-Z0-9\s]+)/i',
                '/tampilkan\s+insiden\s+([A-Z0-9\s]+)/i',
                '/lihat\s+insiden\s+([A-Z0-9\s]+)/i',
                '/jelaskan.*insiden.*di\s+([A-Z0-9\s]+)/i',
                '/ceritakan.*insiden.*di\s+([A-Z0-9\s]+)/i',
                '/jelaskan.*secara\s+detail.*insiden.*di\s+([A-Z0-9\s]+)/i',
                '/jelaskan.*detail.*insiden.*di\s+([A-Z0-9\s]+)/i',
                '/jelaskan.*insiden.*yang\s+ada\s+di\s+([A-Z0-9\s]+)/i',
                '/insiden\s+apa\s+saja.*di\s+([A-Z0-9\s]+)/i',
            ],
            'description' => 'Menampilkan insiden di site tertentu',
            'sql_template' => "SELECT * FROM insiden_tabel WHERE TRIM(UPPER(site)) = TRIM(UPPER('{value}')) OR TRIM(UPPER(REPLACE(site, ' ', ''))) = TRIM(UPPER(REPLACE('{value}', ' ', ''))) OR TRIM(UPPER(REPLACE(REPLACE(site, ' ', ''), '-', ''))) = TRIM(UPPER(REPLACE(REPLACE('{value}', ' ', ''), '-', ''))) ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => true,
            'column' => 'site',
            'is_incident' => true,
        ],

        // Rule 37: Query - Insiden berdasarkan status LPI
        [
            'id' => 'query_insiden_by_status_lpi',
            'name' => 'Query Insiden berdasarkan Status LPI',
            'keywords' => ['insiden status', 'insiden open', 'insiden closed', 'insiden resolved', 'insiden in progress'],
            'patterns' => [
                '/insiden.*status\s+(open|closed|resolved|in\s+progress)/i',
                '/insiden\s+(open|closed|resolved)/i',
                '/kejadian.*status\s+(open|closed|resolved|in\s+progress)/i',
            ],
            'description' => 'Mencari insiden berdasarkan status LPI',
            'sql_template' => "SELECT * FROM insiden_tabel WHERE status_lpi = '{value}' ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => true,
            'column' => 'status_lpi',
            'value_mapping' => [
                'open' => 'Open',
                'closed' => 'Closed',
                'resolved' => 'Resolved',
                'in progress' => 'In Progress',
            ],
            'is_incident' => true,
        ],

        // Rule 38: Query - Insiden berdasarkan High Potential
        [
            'id' => 'query_insiden_by_hipo',
            'name' => 'Query Insiden berdasarkan High Potential',
            'keywords' => ['insiden hipo', 'insiden high potential', 'hipo insiden', 'non hipo'],
            'patterns' => [
                '/insiden.*hipo/i',
                '/insiden.*high\s+potential/i',
                '/hipo.*insiden/i',
                '/non\s+hipo.*insiden/i',
            ],
            'description' => 'Mencari insiden berdasarkan high potential',
            'sql_template' => "SELECT * FROM insiden_tabel WHERE high_potential = '{value}' ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => true,
            'column' => 'high_potential',
            'value_mapping' => [
                'hipo' => 'HIPO',
                'high potential' => 'HIPO',
                'non hipo' => 'NON HIPO',
            ],
            'is_incident' => true,
        ],

        // Rule 39: Query - Insiden berdasarkan tahun
        [
            'id' => 'query_insiden_by_tahun',
            'name' => 'Query Insiden berdasarkan Tahun',
            'keywords' => ['insiden tahun', 'insiden 2025', 'insiden 2024', 'kejadian tahun'],
            'patterns' => [
                '/insiden.*tahun\s+(\d{4})/i',
                '/insiden\s+tahun\s+(\d{4})/i',
                '/insiden\s+(\d{4})/i',
                '/kejadian.*tahun\s+(\d{4})/i',
            ],
            'description' => 'Mencari insiden berdasarkan tahun',
            'sql_template' => "SELECT * FROM insiden_tabel WHERE tahun = {value} ORDER BY bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => true,
            'column' => 'tahun',
            'is_incident' => true,
        ],

        // Rule 40: Query - Insiden berdasarkan bulan dan tahun
        [
            'id' => 'query_insiden_by_bulan_tahun',
            'name' => 'Query Insiden berdasarkan Bulan dan Tahun',
            'keywords' => ['insiden bulan', 'insiden januari', 'insiden februari', 'kejadian bulan'],
            'patterns' => [
                '/insiden.*bulan\s+(\d{1,2}).*tahun\s+(\d{4})/i',
                '/insiden.*bulan\s+(\d{1,2})/i',
                '/insiden\s+(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)/i',
            ],
            'description' => 'Mencari insiden berdasarkan bulan dan tahun',
            'sql_template' => null, // Akan di-generate dinamis
            'extract_value' => true,
            'column' => 'bulan',
            'is_incident' => true,
        ],

        // Rule 41: Statistik - Insiden per Perusahaan
        [
            'id' => 'stat_insiden_per_perusahaan',
            'name' => 'Statistik Insiden per Perusahaan',
            'keywords' => ['insiden per perusahaan', 'distribusi insiden perusahaan', 'statistik insiden perusahaan'],
            'patterns' => [
                '/insiden\s+per\s+perusahaan/i',
                '/distribusi\s+insiden\s+perusahaan/i',
                '/statistik\s+insiden\s+perusahaan/i',
            ],
            'description' => 'Menampilkan distribusi insiden per perusahaan',
            'sql_template' => "SELECT perusahaan, COUNT(DISTINCT no_kecelakaan) as jumlah FROM insiden_tabel WHERE perusahaan IS NOT NULL GROUP BY perusahaan ORDER BY jumlah DESC",
            'extract_value' => false,
            'is_statistic' => true,
            'is_incident' => true,
        ],

        // Rule 42: Statistik - Insiden per Tahun
        [
            'id' => 'stat_insiden_per_tahun',
            'name' => 'Statistik Insiden per Tahun',
            'keywords' => ['insiden per tahun', 'distribusi insiden tahun', 'statistik insiden tahun'],
            'patterns' => [
                '/insiden\s+per\s+tahun/i',
                '/distribusi\s+insiden\s+tahun/i',
                '/statistik\s+insiden\s+tahun/i',
            ],
            'description' => 'Menampilkan distribusi insiden per tahun',
            'sql_template' => "SELECT tahun, COUNT(DISTINCT no_kecelakaan) as jumlah FROM insiden_tabel WHERE tahun IS NOT NULL GROUP BY tahun ORDER BY tahun DESC",
            'extract_value' => false,
            'is_statistic' => true,
            'is_incident' => true,
        ],

        // Rule 43: Statistik - Insiden per Bulan
        [
            'id' => 'stat_insiden_per_bulan',
            'name' => 'Statistik Insiden per Bulan',
            'keywords' => ['insiden per bulan', 'distribusi insiden bulan', 'statistik insiden bulan'],
            'patterns' => [
                '/insiden\s+per\s+bulan/i',
                '/distribusi\s+insiden\s+bulan/i',
                '/statistik\s+insiden\s+bulan/i',
            ],
            'description' => 'Menampilkan distribusi insiden per bulan',
            'sql_template' => "SELECT bulan, COUNT(DISTINCT no_kecelakaan) as jumlah FROM insiden_tabel WHERE bulan IS NOT NULL GROUP BY bulan ORDER BY bulan DESC",
            'extract_value' => false,
            'is_statistic' => true,
            'is_incident' => true,
        ],

        // Rule 44: Statistik - Total Insiden di Site
        [
            'id' => 'stat_total_insiden_site',
            'name' => 'Statistik Total Insiden di Site',
            'keywords' => ['berapa insiden di', 'jumlah insiden di', 'total insiden di', 'berapa insiden site'],
            'patterns' => [
                '/berapa\s+insiden.*site\s+([A-Z0-9\s]+)/i',
                '/jumlah\s+insiden.*site\s+([A-Z0-9\s]+)/i',
                '/total\s+insiden.*site\s+([A-Z0-9\s]+)/i',
                '/berapa\s+insiden\s+di\s+([A-Z0-9\s]+)/i',
            ],
            'description' => 'Menampilkan total insiden di site tertentu',
            'sql_template' => "SELECT COUNT(DISTINCT no_kecelakaan) as total FROM insiden_tabel WHERE TRIM(UPPER(site)) = TRIM(UPPER('{value}')) OR TRIM(UPPER(REPLACE(site, ' ', ''))) = TRIM(UPPER(REPLACE('{value}', ' ', ''))) OR TRIM(UPPER(REPLACE(REPLACE(site, ' ', ''), '-', ''))) = TRIM(UPPER(REPLACE(REPLACE('{value}', ' ', ''), '-', '')))",
            'extract_value' => true,
            'column' => 'site',
            'is_statistic' => true,
            'is_incident' => true,
        ],

        // Rule 45: Statistik - Total Insiden per Kategori di Site
        [
            'id' => 'stat_insiden_kategori_site',
            'name' => 'Statistik Insiden per Kategori di Site',
            'keywords' => ['insiden kategori site', 'distribusi kategori site'],
            'patterns' => [
                '/insiden\s+kategori.*site\s+([A-Z0-9\s]+)/i',
                '/distribusi\s+kategori.*site\s+([A-Z0-9\s]+)/i',
            ],
            'description' => 'Menampilkan distribusi insiden per kategori di site tertentu',
            'sql_template' => "SELECT kategori, COUNT(DISTINCT no_kecelakaan) as jumlah FROM insiden_tabel WHERE (TRIM(UPPER(site)) = TRIM(UPPER('{value}')) OR TRIM(UPPER(REPLACE(site, ' ', ''))) = TRIM(UPPER(REPLACE('{value}', ' ', ''))) OR TRIM(UPPER(REPLACE(REPLACE(site, ' ', ''), '-', ''))) = TRIM(UPPER(REPLACE(REPLACE('{value}', ' ', ''), '-', '')))) AND kategori IS NOT NULL GROUP BY kategori ORDER BY jumlah DESC",
            'extract_value' => true,
            'column' => 'site',
            'is_statistic' => true,
            'is_incident' => true,
        ],

        // Rule 46: Query - Insiden yang belum selesai (Open/In Progress)
        [
            'id' => 'query_insiden_belum_selesai',
            'name' => 'Query Insiden Belum Selesai',
            'keywords' => ['insiden belum selesai', 'insiden open', 'insiden pending', 'insiden belum closed'],
            'patterns' => [
                '/insiden.*belum\s+selesai/i',
                '/insiden.*belum\s+closed/i',
                '/insiden.*pending/i',
                '/insiden\s+open/i',
            ],
            'description' => 'Menampilkan insiden yang belum selesai',
            'sql_template' => "SELECT * FROM insiden_tabel WHERE status_lpi IN ('Open', 'In Progress') ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => false,
            'is_incident' => true,
        ],

        // Rule 47: Query - Insiden yang sudah selesai (Closed/Resolved)
        [
            'id' => 'query_insiden_sudah_selesai',
            'name' => 'Query Insiden Sudah Selesai',
            'keywords' => ['insiden sudah selesai', 'insiden closed', 'insiden resolved', 'insiden selesai'],
            'patterns' => [
                '/insiden.*sudah\s+selesai/i',
                '/insiden.*sudah\s+closed/i',
                '/insiden\s+closed/i',
                '/insiden\s+resolved/i',
                '/insiden\s+selesai/i',
            ],
            'description' => 'Menampilkan insiden yang sudah selesai',
            'sql_template' => "SELECT * FROM insiden_tabel WHERE status_lpi IN ('Closed', 'Resolved') ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => false,
            'is_incident' => true,
        ],

        // Rule 48: Query - Insiden HIPO
        [
            'id' => 'query_insiden_hipo',
            'name' => 'Query Insiden HIPO',
            'keywords' => ['insiden hipo', 'hipo insiden', 'high potential insiden'],
            'patterns' => [
                '/insiden\s+hipo/i',
                '/hipo\s+insiden/i',
                '/high\s+potential\s+insiden/i',
            ],
            'description' => 'Menampilkan insiden dengan high potential',
            'sql_template' => "SELECT * FROM insiden_tabel WHERE high_potential LIKE '%HIPO%' ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => false,
            'is_incident' => true,
        ],

        // Rule 49: Query - Insiden berdasarkan lokasi
        [
            'id' => 'query_insiden_by_lokasi',
            'name' => 'Query Insiden berdasarkan Lokasi',
            'keywords' => ['insiden lokasi', 'insiden di lokasi', 'kejadian lokasi'],
            'patterns' => [
                '/insiden.*lokasi\s+([^\s]+)/i',
                '/insiden\s+di\s+lokasi\s+([^\s]+)/i',
                '/kejadian.*lokasi\s+([^\s]+)/i',
            ],
            'description' => 'Mencari insiden berdasarkan lokasi',
            'sql_template' => "SELECT * FROM insiden_tabel WHERE lokasi LIKE '%{value}%' OR lokasi_spesifik LIKE '%{value}%' ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => true,
            'column' => 'lokasi',
            'is_incident' => true,
        ],

        // Rule 50: Query - Insiden berdasarkan shift
        [
            'id' => 'query_insiden_by_shift',
            'name' => 'Query Insiden berdasarkan Shift',
            'keywords' => ['insiden shift', 'kejadian shift', 'insiden shift 1', 'insiden shift 2'],
            'patterns' => [
                '/insiden.*shift\s+(\d+)/i',
                '/kejadian.*shift\s+(\d+)/i',
                '/insiden\s+shift\s+(\d+)/i',
            ],
            'description' => 'Mencari insiden berdasarkan shift',
            'sql_template' => "SELECT * FROM insiden_tabel WHERE shift = '{value}' ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100",
            'extract_value' => true,
            'column' => 'shift',
            'value_mapping' => [
                '1' => 'Shift 1',
                '2' => 'Shift 2',
                '3' => 'Shift 3',
            ],
            'is_incident' => true,
        ],
    ];

    /**
     * Mendeteksi rule yang cocok dengan user message
     */
    public function detectRule($message)
    {
        return $this->detectRuleWithIntent($message, null);
    }

    /**
     * Mendeteksi rule dengan intent analysis (lebih cerdas)
     */
    public function detectRuleWithIntent($message, $intentAnalysis = null)
    {
        $message = trim(strtolower($message));
        $matchedRules = [];

        foreach ($this->rules as $rule) {
            $score = 0;
            $ruleCopy = $rule; // Copy untuk modifikasi

            // Jika ada intent analysis, gunakan untuk matching yang lebih baik
            if ($intentAnalysis) {
                $score += $this->matchRuleWithIntent($ruleCopy, $intentAnalysis, $message);
            }

            // Check keywords (fuzzy matching) - berikan score lebih tinggi untuk keyword yang lebih spesifik
            foreach ($ruleCopy['keywords'] as $keyword) {
                $keywordLower = strtolower($keyword);
                
                // Exact match
                if (stripos($message, $keywordLower) !== false) {
                    // Berikan score lebih tinggi untuk keyword yang lebih panjang/spesifik
                    $keywordLength = strlen($keywordLower);
                    $baseScore = $keywordLength > 10 ? 4 : 3;
                    $score += $baseScore;
                }
                // Fuzzy match - check individual words
                else {
                    $keywordWords = explode(' ', $keywordLower);
                    $messageWords = explode(' ', $message);
                    $matchedWords = 0;
                    
                    foreach ($keywordWords as $kw) {
                        foreach ($messageWords as $mw) {
                            // Exact word match
                            if ($kw === $mw) {
                                $matchedWords++;
                                break;
                            }
                            // Partial match (min 3 chars)
                            elseif (strlen($kw) >= 3 && stripos($mw, $kw) !== false) {
                                $matchedWords += 0.5;
                                break;
                            }
                        }
                    }
                    
                    if ($matchedWords > 0) {
                        $score += ($matchedWords / count($keywordWords)) * 2;
                    }
                }
            }

            // Check patterns - berikan score lebih tinggi untuk pattern yang match
            if (isset($ruleCopy['patterns'])) {
                foreach ($ruleCopy['patterns'] as $pattern) {
                    if (preg_match($pattern, $message, $matches)) {
                        $score += 6; // Score lebih tinggi untuk pattern match
                        if (isset($ruleCopy['extract_value']) && $ruleCopy['extract_value']) {
                            $extractedValue = $matches[1] ?? null;
                            if ($extractedValue) {
                                // Normalize untuk site jika perlu
                                if (isset($ruleCopy['column']) && $ruleCopy['column'] === 'site') {
                                    $extractedValue = strtoupper(trim($extractedValue));
                                    $extractedValue = preg_replace('/([A-Z]+)(\d+)/', '$1 $2', $extractedValue);
                                    $extractedValue = preg_replace('/\s+/', ' ', $extractedValue);
                                    $extractedValue = trim($extractedValue);
                                }
                                $ruleCopy['extracted_value'] = $extractedValue;
                            }
                        }
                        break;
                    }
                }
            }

            // Jika ada intent analysis, extract value dari entity_value
            if ($intentAnalysis && isset($intentAnalysis['entity_value']) && 
                isset($ruleCopy['extract_value']) && $ruleCopy['extract_value']) {
                $entityValue = $intentAnalysis['entity_value'];
                if ($entityValue && !isset($ruleCopy['extracted_value'])) {
                    // Check jika entity type match dengan column rule
                    $entityType = strtolower($intentAnalysis['entity_type'] ?? '');
                    $ruleColumn = strtolower($ruleCopy['column'] ?? '');
                    
                    // Mapping entity type ke column
                    $entityToColumn = [
                        'area' => 'kategori_area_tercapture',
                        'site' => 'site',
                        'perusahaan' => 'perusahaan',
                        'status' => 'status',
                        'kondisi' => 'kondisi',
                    ];
                    
                    if (isset($entityToColumn[$entityType]) && 
                        ($entityToColumn[$entityType] === $ruleColumn || 
                         stripos($ruleColumn, $entityType) !== false)) {
                        $ruleCopy['extracted_value'] = $entityValue;
                        $score += 3; // Bonus score untuk intent match
                    }
                }
            }

            if ($score > 0) {
                $ruleCopy['match_score'] = $score;
                $matchedRules[] = $ruleCopy;
            }
        }

        // Sort by score (highest first)
        usort($matchedRules, function($a, $b) {
            return $b['match_score'] - $a['match_score'];
        });

        return !empty($matchedRules) ? $matchedRules[0] : null;
    }

    /**
     * Match rule dengan intent analysis
     */
    private function matchRuleWithIntent($rule, $intentAnalysis, $message)
    {
        $score = 0;
        
        if (!$intentAnalysis || !isset($intentAnalysis['intent'])) {
            return $score;
        }

        $intent = strtolower($intentAnalysis['intent']);
        $operation = strtolower($intentAnalysis['operation'] ?? '');
        $entityType = strtolower($intentAnalysis['entity_type'] ?? '');
        $entityValue = $intentAnalysis['entity_value'] ?? null;

        // Map intent ke rule ID patterns
        $intentToRulePatterns = [
            'count_area' => ['stat_total_area', 'stat_total_area_kritis', 'stat_jumlah_cctv_area_kritis'],
            'list_cctv' => ['query_cctv_area_kritis', 'query_by_site', 'query_by_perusahaan'],
            'distribution' => ['stat_distribusi_area', 'stat_cctv_per_kategori_area', 'stat_cctv_per_site'],
            'statistics' => ['stat_', 'overview'],
        ];

        // Check rule ID match dengan intent
        foreach ($intentToRulePatterns as $intentKey => $patterns) {
            if (stripos($intent, $intentKey) !== false) {
                foreach ($patterns as $pattern) {
                    if (stripos($rule['id'], $pattern) !== false) {
                        $score += 4;
                        break;
                    }
                }
            }
        }

        // Check operation match
        if ($operation === 'count' && isset($rule['is_statistic']) && $rule['is_statistic']) {
            $score += 2;
        }
        if ($operation === 'group_by' && isset($rule['is_statistic']) && $rule['is_statistic']) {
            $score += 2;
        }
        if ($operation === 'filter' && !isset($rule['is_statistic'])) {
            $score += 2;
        }

        // Check entity type match dengan rule column
        if ($entityType && isset($rule['column'])) {
            $ruleColumn = strtolower($rule['column']);
            $entityToColumn = [
                'area' => 'kategori_area_tercapture',
                'site' => 'site',
                'perusahaan' => 'perusahaan',
                'status' => 'status',
                'kondisi' => 'kondisi',
            ];
            
            if (isset($entityToColumn[$entityType])) {
                $expectedColumn = $entityToColumn[$entityType];
                if (stripos($ruleColumn, $expectedColumn) !== false || 
                    stripos($expectedColumn, $ruleColumn) !== false) {
                    $score += 3;
                }
            }
        }

        // Check entity value match dengan rule keywords
        if ($entityValue) {
            $entityValueLower = strtolower($entityValue);
            foreach ($rule['keywords'] as $keyword) {
                if (stripos($keyword, $entityValueLower) !== false || 
                    stripos($entityValueLower, $keyword) !== false) {
                    $score += 2;
                }
            }
        }

        return $score;
    }

    /**
     * Extract value dari message berdasarkan rule
     */
    public function extractValue($message, $rule)
    {
        if (!isset($rule['extract_value']) || !$rule['extract_value']) {
            return null;
        }

        $value = null;

        // Jika sudah di-extract dari pattern matching
        if (isset($rule['extracted_value'])) {
            $value = trim($rule['extracted_value']);
        } else {
            // Coba extract dari keywords dengan pattern yang lebih fleksibel
            $column = $rule['column'] ?? null;
            if ($column) {
                $messageLower = strtolower($message);
                
                // Special handling untuk area kritis/non kritis
                if ($column === 'kategori_area_tercapture') {
                    if (preg_match('/area\s+(non\s+)?kritis/i', $message, $matches)) {
                        $isNonKritis = stripos($message, 'non') !== false;
                        $value = $isNonKritis ? 'Area Non Kritis' : 'Area Kritis';
                    } elseif (preg_match('/(kritis|non\s+kritis)/i', $message, $matches)) {
                        $isNonKritis = stripos($matches[1], 'non') !== false;
                        $value = $isNonKritis ? 'Area Non Kritis' : 'Area Kritis';
                    }
                }
                
                // Special handling untuk site (untuk insiden)
                if ($column === 'site') {
                    // Pattern untuk "di BMO 2", "site BMO 2", "BMO 2", "bmo2", dll
                    if (preg_match('/(?:di|site|di\s+site|yang\s+ada\s+di|apa\s+saja.*di)\s+([A-Z0-9\s]+?)(?:\s|$|,|\.)/i', $message, $matches)) {
                        $value = trim($matches[1]);
                    } elseif (preg_match('/([A-Z]{2,}\s*\d+)/i', $message, $matches)) {
                        // Pattern untuk "BMO 2", "BMO2", "GMO 1", "bmo2", dll
                        $value = trim($matches[1]);
                    } elseif (preg_match('/(bmo|gmo|bmo1|bmo2|gmo1|gmo2)\s*(\d*)/i', $message, $matches)) {
                        // Pattern untuk "bmo2", "bmo 2", "gmo1", dll (case insensitive)
                        $prefix = strtoupper($matches[1]);
                        $number = isset($matches[2]) && $matches[2] ? $matches[2] : '';
                        
                        // Normalize: "BMO2" -> "BMO 2", "BMO1" -> "BMO 1"
                        if (preg_match('/([A-Z]+)(\d+)/', $prefix, $prefixMatch)) {
                            $value = $prefixMatch[1] . ' ' . $prefixMatch[2];
                        } else {
                            $value = $prefix . ($number ? ' ' . $number : '');
                        }
                    }
                    
                    // Normalize value: "BMO2" -> "BMO 2", "bmo2" -> "BMO 2"
                    if ($value) {
                        // Convert to uppercase first
                        $value = strtoupper($value);
                        // Normalize spacing: "BMO2" -> "BMO 2"
                        $value = preg_replace('/([A-Z]+)(\d+)/', '$1 $2', $value);
                        // Normalize multiple spaces
                        $value = preg_replace('/\s+/', ' ', $value);
                        $value = trim($value);
                    }
                }
                
                // Jika belum dapat value, coba extract dari keywords
                if (!$value) {
                    $keywords = $rule['keywords'];
                    foreach ($keywords as $keyword) {
                        $keywordLower = strtolower($keyword);
                        $pos = stripos($messageLower, $keywordLower);
                        if ($pos !== false) {
                            $remaining = substr($message, $pos + strlen($keyword));
                            $remaining = trim($remaining);
                            
                            // Untuk site, coba extract setelah kata kunci
                            if ($column === 'site') {
                                // Cari pattern site setelah keyword
                                if (preg_match('/(?:di|yang\s+ada\s+di|apa\s+saja.*di)\s+([A-Z0-9\s]+?)(?:\s|$|,|\.)/i', $remaining, $siteMatch)) {
                                    $value = trim($siteMatch[1]);
                                } elseif (preg_match('/([A-Z]{2,}\s*\d+)/i', $remaining, $siteMatch)) {
                                    $value = trim($siteMatch[1]);
                                } elseif (preg_match('/(bmo|gmo|bmo1|bmo2|gmo1|gmo2)\s*(\d*)/i', $remaining, $siteMatch)) {
                                    $prefix = strtoupper($siteMatch[1]);
                                    $number = isset($siteMatch[2]) && $siteMatch[2] ? $siteMatch[2] : '';
                                    if (preg_match('/([A-Z]+)(\d+)/', $prefix, $prefixMatch)) {
                                        $value = $prefixMatch[1] . ' ' . $prefixMatch[2];
                                    } else {
                                        $value = $prefix . ($number ? ' ' . $number : '');
                                    }
                                } else {
                                    // Ambil beberapa kata pertama
                                    $words = explode(' ', $remaining);
                                    if (count($words) > 0) {
                                        $value = implode(' ', array_slice($words, 0, min(3, count($words))));
                                    }
                                }
                                
                                // Normalize site value
                                if ($value) {
                                    $value = strtoupper($value);
                                    $value = preg_replace('/([A-Z]+)(\d+)/', '$1 $2', $value);
                                    $value = preg_replace('/\s+/', ' ', $value);
                                    $value = trim($value);
                                }
                            } else {
                                // Ambil beberapa kata pertama (untuk nilai dengan spasi seperti "BMO 1" atau "non kritis")
                                $words = explode(' ', $remaining);
                                if (count($words) > 0) {
                                    // Ambil 1-3 kata pertama
                                    $value = implode(' ', array_slice($words, 0, min(3, count($words))));
                                    $value = trim($value);
                                }
                            }
                            
                            if ($value) {
                                break;
                            }
                        }
                    }
                }
            }
        }

        // Apply value mapping jika ada
        if (isset($rule['value_mapping']) && isset($value)) {
            $valueLower = strtolower($value);
            
            // Check exact match
            if (isset($rule['value_mapping'][$valueLower])) {
                $value = $rule['value_mapping'][$valueLower];
            } else {
                // Check partial match
                foreach ($rule['value_mapping'] as $key => $mappedValue) {
                    if (stripos($valueLower, $key) !== false || stripos($key, $valueLower) !== false) {
                        $value = $mappedValue;
                        break;
                    }
                }
            }
        }

        return $value ? trim($value) : null;
    }

    /**
     * Generate SQL query dari rule
     */
    public function generateSQL($rule, $message = null, $intentAnalysis = null)
    {
        // Jika complex query, return null (akan di-handle oleh AI)
        if (isset($rule['is_complex']) && $rule['is_complex']) {
            return null;
        }

        // Handle khusus untuk query insiden berdasarkan bulan
        if ($rule['id'] === 'query_insiden_by_bulan_tahun') {
            return $this->generateSQLForBulanTahun($rule, $message, $intentAnalysis);
        }

        // Jika ada SQL template
        if (isset($rule['sql_template'])) {
            $sql = $rule['sql_template'];

            // Extract value jika diperlukan
            if (isset($rule['extract_value']) && $rule['extract_value']) {
                $value = null;
                
                // Prioritaskan dari intent analysis jika ada
                if ($intentAnalysis && isset($intentAnalysis['entity_value'])) {
                    $value = $intentAnalysis['entity_value'];
                    
                    // Apply value mapping jika ada
                    if (isset($rule['value_mapping'])) {
                        $valueLower = strtolower($value);
                        if (isset($rule['value_mapping'][$valueLower])) {
                            $value = $rule['value_mapping'][$valueLower];
                        }
                    }
                }
                
                // Fallback ke extract dari message
                if (!$value && $message) {
                    $value = $this->extractValue($message, $rule);
                }
                
                if ($value) {
                    // Log untuk debugging
                    Log::info('ChatbotRuleService: Extracted value', [
                        'rule_id' => $rule['id'] ?? 'unknown',
                        'column' => $rule['column'] ?? 'unknown',
                        'extracted_value' => $value,
                        'message' => $message
                    ]);
                    
                    $sql = str_replace('{value}', addslashes($value), $sql);
                    
                    // Log SQL yang dihasilkan
                    Log::info('ChatbotRuleService: Generated SQL', [
                        'rule_id' => $rule['id'] ?? 'unknown',
                        'sql' => $sql
                    ]);
                } else {
                    // Jika tidak bisa extract value, return null untuk di-handle AI
                    Log::warning('ChatbotRuleService: Cannot extract value', [
                        'rule_id' => $rule['id'] ?? 'unknown',
                        'column' => $rule['column'] ?? 'unknown',
                        'message' => $message
                    ]);
                    return null;
                }
            }

            return $sql;
        }

        return null;
    }

    /**
     * Generate SQL untuk query insiden berdasarkan bulan dan tahun
     */
    private function generateSQLForBulanTahun($rule, $message, $intentAnalysis)
    {
        $messageLower = strtolower($message ?? '');
        $bulan = null;
        $tahun = null;
        
        // Mapping nama bulan Indonesia ke angka
        $bulanMapping = [
            'januari' => 1, 'january' => 1,
            'februari' => 2, 'february' => 2,
            'maret' => 3, 'march' => 3,
            'april' => 4,
            'mei' => 5, 'may' => 5,
            'juni' => 6, 'june' => 6,
            'juli' => 7, 'july' => 7,
            'agustus' => 8, 'august' => 8,
            'september' => 9, 'sept' => 9,
            'oktober' => 10, 'october' => 10,
            'november' => 11, 'nov' => 11,
            'desember' => 12, 'december' => 12, 'dec' => 12,
        ];
        
        // Extract tahun (4 digit)
        if (preg_match('/tahun\s+(\d{4})/i', $message, $tahunMatch)) {
            $tahun = (int)$tahunMatch[1];
        } elseif (preg_match('/(\d{4})/i', $message, $tahunMatch)) {
            $tahun = (int)$tahunMatch[1];
        }
        
        // Extract bulan dari nama bulan
        foreach ($bulanMapping as $namaBulan => $angkaBulan) {
            if (stripos($messageLower, $namaBulan) !== false) {
                $bulan = $angkaBulan;
                break;
            }
        }
        
        // Extract bulan dari angka (1-12)
        if (!$bulan && preg_match('/bulan\s+(\d{1,2})/i', $message, $bulanMatch)) {
            $bulan = (int)$bulanMatch[1];
            if ($bulan < 1 || $bulan > 12) {
                $bulan = null;
            }
        }
        
        // Build SQL
        $conditions = [];
        if ($bulan) {
            $conditions[] = "bulan = " . (int)$bulan;
        }
        if ($tahun) {
            $conditions[] = "tahun = " . (int)$tahun;
        }
        
        if (empty($conditions)) {
            return null; // Tidak bisa extract bulan/tahun, fallback ke AI
        }
        
        $whereClause = "WHERE " . implode(" AND ", $conditions);
        return "SELECT * FROM insiden_tabel {$whereClause} ORDER BY tahun DESC, bulan DESC, tanggal DESC, no_kecelakaan, id LIMIT 100";
    }

    /**
     * Get all rules (untuk debugging/admin)
     */
    public function getAllRules()
    {
        return $this->rules;
    }

    /**
     * Get rule by ID
     */
    public function getRuleById($ruleId)
    {
        foreach ($this->rules as $rule) {
            if ($rule['id'] === $ruleId) {
                return $rule;
            }
        }
        return null;
    }
}

