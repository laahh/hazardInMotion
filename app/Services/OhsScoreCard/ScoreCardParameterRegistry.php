<?php

declare(strict_types=1);

namespace App\Services\OhsScoreCard;

/**
 * Daftar sumber data dan band resmi untuk tabel "Score Card Parameter".
 *
 * SATU BARIS = SATU PARAMETER. Tiap entri menunjuk tabel ringkasan milik
 * halaman parameter itu, lengkap dengan nama kolom site/kontraktor/bulan/nilai,
 * cara meringkasnya, dan band penilaiannya.
 *
 * AMBANG BAND DI SINI DIGANDAKAN dari SCORE_BANDS milik controller tiap
 * halaman -- controller tetap pemilik kebenarannya. Penggandaan ini disengaja
 * supaya tabel ringkasan tidak perlu memuat 21 controller sekaligus, dan
 * dijaga oleh skrip pembanding yang memeriksa keduanya tetap sama. Kalau
 * ambang di controller diubah, ubah juga di sini.
 *
 * PARAMETER TANPA SUMBER sengaja tetap didaftarkan dengan 'sumber' => null.
 * Barisnya tetap muncul di tabel sebagai sel kosong bertanda, supaya kerangka
 * 32 parameter tetap utuh dan yang belum tergarap kelihatan, bukan hilang
 * diam-diam.
 */
final class ScoreCardParameterRegistry
{
    /**
     * Kolom tabel: site beserta kontraktor yang ditampilkan.
     *
     * SENGAJA DIPATOK, bukan diturunkan dari data. Konsekuensinya pasangan
     * site/kontraktor di luar daftar ini tidak ikut tampil meskipun datanya
     * ada: saat ini BMO 1/PT MTN, GMO/PT BAR, dan LMO/PT MTN punya angka
     * tetapi tidak berkolom. Tambahkan di sini kalau ingin ikut tampil.
     *
     * BMO 1/PT FAD dan BMO 2/PT BUMA pernah ada di daftar ini lalu dibuang:
     * keduanya warisan tabel contoh dan tidak punya data sama sekali.
     *
     * @var array<string, array<int, string>>
     */
    public const KOLOM = [
        'BMO 1' => ['PT BUMA', 'PT KDC', 'PT MTL'],
        'BMO 2' => ['PT PAMA'],
        'BMO 3' => ['PT BAR'],
        'GMO' => ['PT KDC', 'PT PAMA'],
        'LMO' => ['PT BUMA', 'PT FAD'],
        'SMO' => ['PT MTN'],
    ];

    /**
     * Nama kontraktor ditulis berbeda-beda antar tabel: ada yang memakai nama
     * lengkap ("PT Bukit Makmur Mandiri Utama"), ada yang singkatan telanjang
     * ("BUMA"), ada pula yang "PT BUMA". Semuanya dipetakan ke satu label
     * kolom supaya satu kontraktor tidak terpecah jadi beberapa kolom.
     *
     * Kuncinya sudah dinormalkan: huruf kecil, tanpa awalan "pt", tanpa spasi
     * ganda. Lihat ScoreCardParameterMatrix::kunciKontraktor().
     *
     * @var array<string, string>
     */
    public const ALIAS_KONTRAKTOR = [
        'buma' => 'PT BUMA',
        'bukit makmur mandiri utama' => 'PT BUMA',
        'bar' => 'PT BAR',
        'bumi artlantis raya' => 'PT BAR',
        'fad' => 'PT FAD',
        'fajar anugerah dinamika' => 'PT FAD',
        'kdc' => 'PT KDC',
        'kaltim diamond coal' => 'PT KDC',
        'mtl' => 'PT MTL',
        'mutiara tanjung lestari' => 'PT MTL',
        'mtn' => 'PT MTN',
        'madhani talatah nusantara' => 'PT MTN',
        'pama' => 'PT PAMA',
        'pamapersada nusantara' => 'PT PAMA',
    ];

    /**
     * Keluarga band:
     *   naik  -> makin besar makin baik; [ambang2, ambang3, ambang4, batas atas]
     *   turun -> makin kecil makin baik; [batas band 4, band 3, band 2]
     *   cacah -> mencacah kejadian; 0 terbaik
     *   biner -> hanya 100% yang bernilai 4
     */
    public const BAND_NAIK = 'naik';
    public const BAND_TURUN = 'turun';
    public const BAND_CACAH = 'cacah';
    public const BAND_BINER = 'biner';
    /** Band Penuntasan Rekayasa: nilai tertinggi justru DI ATAS 100%. */
    public const BAND_REKAYASA = 'rekayasa';

    /** Nilai sel diringkas dengan rata-rata (persentase) atau jumlah (cacah). */
    public const RINGKAS_RATA = 'rata';
    public const RINGKAS_JUMLAH = 'jumlah';

    /**
     * Urutan baris mengikuti tabel Score Card yang dipakai manajemen.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function parameter(): array
    {
        return [
            self::persen('Ratio Pelaporan TBC & GR', 'lead_ratio_pelapor_tbc',
                'site_dedicated_pelapor_all_karyawan', 'perusahaan_pelapor_all_karyawan',
                'month_of_date_time', 'pct_ratio_pelapor_tbc', [94, 96, 98, 100]),

            self::persen('Coverage Area Daily', 'lead_coverage_area_daily',
                'site_hst', 'pic_detail_lokasi_maincont',
                'month_of_date_hst', 'pct_coverage_daily', [94, 96, 98, 100]),

            self::terbalik('Blindspot TBC yang dilaporkan BC', 'lead_blindspot_tbc_month',
                'site', 'perusahaan_pic', 'month_of_date_for_join', 'pct_blindspot_tbc_dari_bc'),

            self::terbalik('Blindspot GR yang dilaporkan BC', 'lead_blindspot_gr_month',
                'site', 'perusahaan_pic', 'month_of_date_for_join', 'blindspot_gr'),

            self::persen('Coverage Area Kritis Pengawas Suptend up',
                'lead_coverage_area_kritis_pengawas_suptend_up',
                'site_hst', 'pic_detail_lokasi_clean',
                'month_of_date_hst', 'pctcoverage_suptend_up', [2, 4, 6, 8]),

            self::persen('% Pengawasan Berjarak', 'lead_pengawasan_berjarak',
                'site', 'perusahaan_pelapor_all_karyawan',
                'month_of_date_for_join', 'pct_berjarak', [60, 70, 80, 100]),

            // MAKIN KECIL MAKIN BAIK, sama dengan Blindspot TBC dan GR:
            // yang diukur bahaya yang luput, jadi nol adalah hasil terbaik.
            // Ambangnya [batas Nilai 4, Nilai 3, Nilai 2] = tepat 0%, 3%, 5%.
            [
                'nama' => '% Blindspot temuan Real Time',
                'sumber' => 'lead_blindspot_laporan_real_time',
                'site' => 'site', 'mitra' => 'perusahaan_pic',
                'bulan' => 'month_of_date_for_join',
                'nilai' => 'pct_blindspot_temuan_real_time',
                'band' => self::BAND_TURUN, 'ambang' => [0, 3, 5],
                'satuan' => '%', 'ringkas' => self::RINGKAS_RATA,
            ],

            self::persen('Coverage Daily Area Kritis Pengawas Safety',
                'lead_coverage_daily_area_kritis_pengawas_safety',
                'site_hst', 'pic_detail_lokasi_clean',
                'month_of_date_hst', 'pctcoverage_safety', [85, 90, 95, 100]),

            self::persen('Speak up fatigue', 'lead_speak_up_sebelum_alert',
                'site_dedicated', 'nama_perusahaan',
                'month_of_event_time', 'pct_true_alert_fatigue_speak_up_sebelum', [96, 98, 100, 100]),

            self::cacah('Tidak ada temuan penggunaan HP', 'lead_gr_penggunaan_hp',
                'site', 'perusahaan_pic', 'month_of_date_for_join', 'distinct_count_of_task_number'),

            self::cacah('Incident dengan Gap Coverage CCTV & Gap pada DMS', 'lead_inc_gap_cctv_dms',
                'site1', 'perusahaan', 'month_of_tanggal_kejadian', 'incident_dengan_gap_cctv_dms'),

            self::persen('Leadtime Alert DMS masuk ke Server',
                'lead_leadtime_alert_entry_to_bedms_month',
                'site', 'perusahaan', 'month_of_event_time',
                'pct_leadtime_alert_masuk_ke_server_evidence_bedms_under_5_mi', [70, 80, 90, 100]),

            self::persen('Kinerja Pengawasan Control Room DMS', 'lead_kinerja_control_room_dms',
                'site', 'perusahaan', 'month_of_event_time',
                'pct_kinerja_pengawas_control_room', [85, 90, 95, 100]),

            // SEL KOSONG DI SINI BERARTI TIDAK ADA PERULANGAN, bukan data
            // yang belum masuk -- dan itu hasil TERBAIK, bukan ketiadaan.
            //
            // Dibuktikan dari tabelnya: 13 baris, nilai terkecil 1, dan tidak
            // satu pun baris bernilai 0. Tabel itu hanya mencatat ketika
            // perulangan benar-benar terjadi, jadi kombinasi yang tidak
            // tercatat memang bersih.
            //
            // Karena itu sel kosong diperlakukan sebagai cacah 0 -- yang jatuh
            // ke band teratas dan berwarna hijau -- dan ditulis "N/A" supaya
            // tidak tertukar dengan angka nol hasil pengukuran.
            self::cacah('Perulangan rekomendasi hasil investigasi', 'lead_perulangan_rekomendasi',
                'site1', 'perusahaan', 'month_of_ccr_waktu_insiden',
                'count_of_layer_tindakan_perbaikan1') + [
                    'kosong_berarti' => 0.0,
                    'kosong_label' => 'N/A',
                ],

            self::persen('Kesesuaian Implementasi IKK', 'lead_compliance_ikk',
                'ra_site_name', 'company_name_ikk_work_permit',
                'month_of_start_date_convert', 'pct_compliance_ikk', [85, 90, 95, 100]),

            self::persen('% SPIP yang dilakukan Commissioning', 'lead_scr_spip_commisioning_new',
                'site_existing', 'perusahaan_pemilik_existing',
                'performance_month', 'performance_pct', [80, 90, 98, 100]),

            self::kosong('Laporan Perizinan Usaha Jasa'),

            // Temuan dibebankan ke perusahaan MINECON di atas subkon yang
            // jadi PIC-nya, diturunkan dari view relasi perusahaan di OLAP;
            // lihat ambilPicSubcont() dan MineconRelasi.
            //
            // YANG DITAMPILKAN CACAH, BUKAN PERSENTASE: tabel bulanannya kosong
            // (0 baris) dan satu-satunya kolom persen di tabel rincian bernilai
            // 100,00 untuk seluruh 123 baris, jadi tidak ada penyebut yang
            // sahih. Band resmi parameter ini berbasis persen, karena itu
            // Nilainya sengaja dikosongkan.
            [
                'nama' => '% Blindspot TBC dengan PIC Subcontractor',
                'sumber' => 'detail_lead_subcont_blindspot_tbc_pic_subcont',
                'khusus' => 'pic_subcont',
                'site' => 'site', 'mitra' => 'perusahaan_pic',
                'bulan' => 'month_of_date_for_join', 'nilai' => 'task_number',
                'band' => null, 'ambang' => [],
                'satuan' => '', 'ringkas' => self::RINGKAS_JUMLAH,
            ],

            // Kriterianya naratif (terlaksana / perulangan / tindak lanjut),
            // bukan ambang angka.
            self::kosong('Peer Pressure'),

            // Kedua parameter sertifikasi TIDAK PUNYA DIMENSI WAKTU: tabel
            // sumbernya hanya memuat keadaan saat ini. Nilainya karena itu sama
            // untuk bulan mana pun, dan penyaring bulan tidak mengubahnya.
            self::kompetensi('Pemenuhan Sertifikasi Pengawas Teknis', 'competency_pengawas_teknis'),
            self::kompetensi('Pemenuhan Sertifikasi Tenaga Teknis', 'competency_tenaga_teknis'),

            // Dihitung dari road_summary yang berbasis MINGGU, bukan bulan;
            // penanganannya khusus di ScoreCardParameterMatrix.
            [
                'nama' => 'Jalan sesuai standar',
                'sumber' => 'road_summary',
                'khusus' => 'road_summary',
                'band' => self::BAND_NAIK,
                'ambang' => [95, 98, 100, 100],
                'satuan' => '%',
                'ringkas' => self::RINGKAS_RATA,
            ],

            self::cacah('Deviasi Rekayasa Engineering Seatbelt', 'lead_gr_seatbelt',
                'site', 'perusahaan_pic', 'month_of_date_for_join', 'distinct_count_of_task_number'),

            self::cacah('Deviasi Rekayasa Engineering Overspeed', 'lead_pelanggaran_overspeed',
                'site_by_approval', 'perusahaan', 'month_of_start_date_be_record',
                'distinct_count_of_kode_sid_bep_vw_berecord'),

            self::kosong('Pemenuhan Regulasi'),
            [
                'nama' => 'Penuntasan pengendalian rekayasa',
                'sumber' => 'lead_replikasi_rekayasa_engineering',
                'site' => 'site', 'mitra' => 'perusahaan',
                'bulan' => 'month_name', 'nilai' => 'target_komitmen',
                'skala' => 100.0,
                'band' => self::BAND_REKAYASA, 'ambang' => [80, 100],
                'satuan' => '%', 'ringkas' => self::RINGKAS_RATA,
            ],
            // Pembilangnya di MySQL, penyebutnya di database BeSigma
            // (Postgres), jadi tidak bisa di-JOIN; lihat ambilBesigma().
            // Ketika BeSigma tidak terjangkau, parameter ini tampil sebagai
            // belum bersumber alih-alih diberi angka yang salah.
            [
                'nama' => 'Utilisasi BeSigma',
                'sumber' => 'lead_utilisasi_besigma',
                'khusus' => 'besigma',
                'site' => 'site_dedicated', 'mitra' => 'company',
                'bulan' => 'month_name', 'nilai' => 'distinct_kode_sid',
                'band' => self::BAND_NAIK, 'ambang' => [96, 98, 100, 100],
                'satuan' => '%', 'ringkas' => self::RINGKAS_RATA,
            ],

            self::persen('Rasio kelayakan kerja (wellbeing)', 'lead_ratio_kelayakan_kerja',
                'site_dedicated', 'nama_perusahaan',
                'month_of_tanggal_pelaksanaan_mcu', 'pct_mcu_fit', [85, 90, 95, 100]),

            self::persen('Pemeriksaan Fit to Work awal shift pekerja', 'lead_fit_to_work_awal_shift',
                'site_dedicated', 'nama_perusahaan',
                'month_of_tanggal_date', 'pct_pengisian_aggregator', [85, 90, 95, 100]),

            // Kolomnya rasio 0-1, jadi diskalakan 100 kali.
            self::rasio('Pelaksanaan Sobriety Test Jam Kritis dan Pengecekan Sobriety Test',
                'lead_sobriety_test', 'site_dedicated', 'nama_perusahaan',
                'month_name', 'pengisian_aggregator', [96, 98, 100, 100]),

            [
                'nama' => 'Tidak ada pelaporan melewati batas golden time',
                'sumber' => 'lead_golden_time_emergency',
                'site' => 'site1',
                'mitra' => 'perusahaan',
                'bulan' => 'month_of_ccr_waktu_insiden',
                'nilai' => 'pct_golden_time',
                'band' => self::BAND_BINER,
                'ambang' => [],
                'satuan' => '%',
                'ringkas' => self::RINGKAS_RATA,
            ],

            self::kosong('Kesiapan alat Emergency'),
        ];
    }

    /** @param array<int, int|float> $ambang */
    private static function persen(
        string $nama, string $tabel, string $site, string $mitra,
        string $bulan, string $nilai, array $ambang
    ): array {
        return [
            'nama' => $nama, 'sumber' => $tabel, 'site' => $site, 'mitra' => $mitra,
            'bulan' => $bulan, 'nilai' => $nilai,
            'band' => self::BAND_NAIK, 'ambang' => $ambang,
            'satuan' => '%', 'ringkas' => self::RINGKAS_RATA,
        ];
    }

    private static function terbalik(
        string $nama, string $tabel, string $site, string $mitra,
        string $bulan, string $nilai
    ): array {
        return [
            'nama' => $nama, 'sumber' => $tabel, 'site' => $site, 'mitra' => $mitra,
            'bulan' => $bulan, 'nilai' => $nilai,
            'band' => self::BAND_TURUN, 'ambang' => [5, 10, 15],
            'satuan' => '%', 'ringkas' => self::RINGKAS_RATA,
        ];
    }

    private static function cacah(
        string $nama, string $tabel, string $site, string $mitra,
        string $bulan, string $nilai
    ): array {
        return [
            'nama' => $nama, 'sumber' => $tabel, 'site' => $site, 'mitra' => $mitra,
            'bulan' => $bulan, 'nilai' => $nilai,
            'band' => self::BAND_CACAH, 'ambang' => [3, 5],
            'satuan' => '', 'ringkas' => self::RINGKAS_JUMLAH,
        ];
    }


    /**
     * Parameter sertifikasi kompetensi. Dihitung per ORANG unik, bukan per
     * baris, dan tanpa dimensi bulan -- lihat
     * AbstractSertifikasiKompetensiController untuk alasannya.
     */
    private static function kompetensi(string $nama, string $tabel): array
    {
        return [
            'nama' => $nama, 'sumber' => $tabel, 'khusus' => 'kompetensi',
            'site' => 'nama_site', 'mitra' => 'perusahaan',
            'bulan' => null, 'nilai' => 'sertifikasi', 'tanpa_bulan' => true,
            'band' => self::BAND_NAIK, 'ambang' => [50, 60, 80, 100],
            'satuan' => '%', 'ringkas' => self::RINGKAS_RATA,
        ];
    }

    /**
     * Parameter yang kolom nilainya berupa RASIO 0-1, bukan persen. Diskalakan
     * 100 kali sebelum dinilai.
     *
     * @param  array<int, int|float>  $ambang
     */
    private static function rasio(
        string $nama, string $tabel, string $site, string $mitra,
        string $bulan, string $nilai, array $ambang
    ): array {
        return [
            'nama' => $nama, 'sumber' => $tabel, 'site' => $site, 'mitra' => $mitra,
            'bulan' => $bulan, 'nilai' => $nilai, 'skala' => 100.0,
            'band' => self::BAND_NAIK, 'ambang' => $ambang,
            'satuan' => '%', 'ringkas' => self::RINGKAS_RATA,
        ];
    }

    private static function kosong(string $nama): array
    {
        return [
            'nama' => $nama, 'sumber' => null, 'band' => null, 'ambang' => [],
            'satuan' => '', 'ringkas' => self::RINGKAS_RATA,
        ];
    }
}
