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
 * SEL KOSONG DITULIS "N/A" DAN DINILAI PENUH. Tiap parameter menyatakan
 * ujung "terbaik"-nya lewat 'kosong_berarti', dan angkanya BERBEDA-BEDA
 * mengikuti arah band masing-masing:
 *
 *   arah naik   -> batas atas band teratas (biasanya 100, tetapi 8 untuk
 *                  Coverage Area Kritis yang band teratasnya 6%-8%)
 *   arah turun  -> 0, karena nol adalah hasil terbaik
 *   cacah       -> 0, karena tidak ada kejadian adalah hasil terbaik
 *
 * Mengisi semuanya dengan angka yang sama akan salah arah untuk separuh
 * parameter: 0 pada parameter arah naik justru memberi Nilai 1 merah.
 *
 * YANG DIKLAIM ANGKA ITU perlu disadari. Untuk parameter cacah dan arah turun
 * sudah dibuktikan dari tabelnya bahwa sel kosong berarti kejadiannya tidak
 * ada. Untuk parameter arah naik, sel kosong umumnya berarti BELUM DIUKUR,
 * dan menilainya penuh adalah keputusan pengguna, bukan temuan data.
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
     * Parameter yang untuk sementara tidak ditampilkan di matriks Score Card.
     *
     * SENGAJA DISEMBUNYIKAN, BUKAN DIHAPUS. Entri parameternya tetap utuh di
     * parameter() lengkap dengan catatannya, jadi memunculkannya kembali cukup
     * dengan membuang namanya dari daftar ini -- tidak perlu menulis ulang
     * definisinya dan tidak ada riwayat yang hilang.
     *
     * Parameter yang disembunyikan hilang sepenuhnya dari matriks: tidak
     * menjadi baris, dan tidak ikut menghitung rata-rata kartu site.
     *
     * @var array<int, string>
     */
    public const DISEMBUNYIKAN = [
        // Diminta disembunyikan 6 Oktober 2026.
        'Peer Pressure',
    ];

    /**
     * Urutan baris mengikuti tabel Score Card yang dipakai manajemen.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function parameter(): array
    {
        return array_values(array_filter(
            self::semuaParameter(),
            static fn (array $p): bool => !in_array($p['nama'], self::DISEMBUNYIKAN, true)
        ));
    }

    /**
     * Seluruh parameter apa adanya, termasuk yang sedang disembunyikan.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function semuaParameter(): array
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

            // DUA HAL KHUSUS DI PARAMETER INI.
            //
            // 1. KOLOMNYA PECAHAN 0-1, BUKAN PERSEN. Nilainya hanya 0 · 0,5 · 1
            //    dan tiap baris bernilai punya temuan nyata di rincian: 0,5
            //    muncul pada kombinasi dengan 2 temuan, 1,0 pada yang 1 atau 3
            //    temuan. Artinya rasio blindspot terhadap seluruh temuan, jadi
            //    1,0 berarti 100% -- bukan 1%. Skalanya dideteksi otomatis,
            //    sama seperti skalaPersen() di BlindspotGrController.
            //
            // 2. SEL KOSONG BERARTI TIDAK ADA BLINDSPOT, bukan data hilang.
            //    Dibuktikan: 101 dari 124 baris NULL, dan TIDAK SATU PUN punya
            //    temuan di detail_lead_blindspot_gr; tidak ada pula kombinasi
            //    yang ada di rincian tetapi hilang dari tabel bulanan. Karena
            //    arahnya terbalik, nol adalah hasil terbaik -- jadi sel kosong
            //    bernilai 0% dan ditulis "N/A".
            self::terbalik('Blindspot GR yang dilaporkan BC', 'lead_blindspot_gr_month',
                'site', 'perusahaan_pic', 'month_of_date_for_join', 'blindspot_gr') + [
                    'skala' => 'auto',
                    'kosong_berarti' => 0.0,
                    'kosong_label' => 'N/A',
                ],

            // Sel kosong berarti pasangan itu tidak punya lokasi kritis
            // terdaftar sama sekali, bukan lokasi yang gagal dikunjungi:
            // tabelnya memuat 60 baris dengan jumlah lokasi terdaftar
            // minimum 2, tanpa baris bernilai nol maupun NULL. Tidak ada
            // lokasi berarti tidak ada yang terlewat, jadi ditulis "N/A".
            //
            // NILAINYA 6, BUKAN 0 maupun 100: arah parameter ini NAIK dan band
            // teratasnya 6%-8%, jadi 6 adalah ambang masuk Nilai 4. Mengisinya
            // 0 akan memberi Nilai 1 merah, sedangkan 100 di luar rentang band
            // yang masuk akal untuk parameter ini.
            self::persen('Coverage Area Kritis Pengawas Suptend up',
                'lead_coverage_area_kritis_pengawas_suptend_up',
                'site_hst', 'pic_detail_lokasi_clean',
                'month_of_date_hst', 'pctcoverage_suptend_up', [2, 4, 6, 8]) + [
                    'kosong_berarti' => 6.0,
                    'kosong_label' => 'N/A',
                ],

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
                // Arah turun: nol adalah hasil terbaik.
                'kosong_berarti' => 0.0,
                'kosong_label' => 'N/A',
            ],

            self::persen('Coverage Daily Area Kritis Pengawas Safety',
                'lead_coverage_daily_area_kritis_pengawas_safety',
                'site_hst', 'pic_detail_lokasi_clean',
                'month_of_date_hst', 'pctcoverage_safety', [85, 90, 95, 100]),

            self::persen('Speak up fatigue', 'lead_speak_up_sebelum_alert',
                'site_dedicated', 'nama_perusahaan',
                'month_of_event_time', 'pct_true_alert_fatigue_speak_up_sebelum', [96, 98, 100, 100]),

            // Tabelnya hanya mencatat temuan; 29 baris, nilai terkecil 1,
            // tanpa baris bernilai nol. Sel kosong berarti tidak ada temuan.
            self::cacah('Tidak ada temuan penggunaan HP', 'lead_gr_penggunaan_hp',
                'site', 'perusahaan_pic', 'month_of_date_for_join', 'distinct_count_of_task_number') + [
                    'kosong_berarti' => 0.0,
                    'kosong_label' => 'N/A',
                ],

            // 20 baris, nilai terkecil 1, tanpa baris nol.
            self::cacah('Incident dengan Gap Coverage CCTV & Gap pada DMS', 'lead_inc_gap_cctv_dms',
                'site1', 'perusahaan', 'month_of_tanggal_kejadian', 'incident_dengan_gap_cctv_dms') + [
                    'kosong_berarti' => 0.0,
                    'kosong_label' => 'N/A',
                ],

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

            [
                'nama' => 'Laporan Perizinan Usaha Jasa',
                'sumber' => 'scr_business_license_performance',
                'khusus' => 'perizinan_usaha_jasa',
                // Sumbernya menyimpan CACAH deviasi dan cacah subkontraktor,
                // bukan persentase, supaya rata-ratanya tertimbang; hasil
                // baginya pecahan 0-1.
                'skala' => 100.0,
                // ARAHNYA TURUN: angka ini PROPORSI DEVIASI, bukan kepatuhan.
                // Nol berarti tidak ada subkontraktor yang menyimpang, dan itu
                // hasil TERBAIK.
                //
                // BAND RESMINYA BELUM ADA, jadi dibiarkan null dengan sengaja:
                // sel tetap menampilkan persentasenya tetapi TANPA angka Nilai,
                // karena memberi Nilai berarti mengarang skor resmi. Akibatnya
                // parameter ini juga tidak ikut menghitung rata-rata kartu site
                // -- rataSite() memang melewati parameter tanpa band.
                //
                // Begitu band resminya ada, isi 'band' => self::BAND_TURUN dan
                // 'ambang' di sini; halamannya punya AMBANG_SEMENTARA sendiri
                // yang juga perlu diganti.
                'band' => null,
                'ambang' => [],
                'satuan' => '%',
                'ringkas' => self::RINGKAS_RATA,
            ],

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
            //
            // SEDANG DISEMBUNYIKAN lewat DISEMBUNYIKAN di atas, jadi barisnya
            // tidak muncul di matriks. Entrinya sengaja dibiarkan di sini.
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
                // Sumbernya menyimpan CACAH segmen, bukan persentase, supaya
                // rata-ratanya tertimbang seperti di halaman Jalan sesuai
                // standar. Hasil bagi segmen_standar/segmen_total berupa
                // pecahan 0-1, jadi dikali seratus di sini.
                'skala' => 100.0,
                'band' => self::BAND_NAIK,
                'ambang' => [95, 98, 100, 100],
                'satuan' => '%',
                'ringkas' => self::RINGKAS_RATA,
                // Sel kosong berarti pasangan itu tidak punya ruas jalan yang
                // disurvei sama sekali di road_summary -- bukan ruas yang
                // gagal standar. Tidak ada jalan di bawah standar, jadi
                // dianggap capaian penuh dan ditulis "N/A".
                //
                // NILAINYA 100, BUKAN 0: arah parameter ini NAIK, jadi yang
                // terbaik ada di ujung atas. Mengisinya 0 seperti parameter
                // cacah justru memberi Nilai 1 merah.
                'kosong_berarti' => 100.0,
                'kosong_label' => 'N/A',
            ],

            // 33 baris, nilai terkecil 1, tanpa baris nol.
            self::cacah('Deviasi Rekayasa Engineering Seatbelt', 'lead_gr_seatbelt',
                'site', 'perusahaan_pic', 'month_of_date_for_join', 'distinct_count_of_task_number') + [
                    'kosong_berarti' => 0.0,
                    'kosong_label' => 'N/A',
                ],

            // 47 baris, nilai terkecil 1, tanpa baris nol.
            self::cacah('Deviasi Rekayasa Engineering Overspeed', 'lead_pelanggaran_overspeed',
                'site_by_approval', 'perusahaan', 'month_of_start_date_be_record',
                'distinct_count_of_kode_sid_bep_vw_berecord') + [
                    'kosong_berarti' => 0.0,
                    'kosong_label' => 'N/A',
                ],

            [
                'nama' => 'Pemenuhan Regulasi',
                'sumber' => 'regulatory_compliance_summary',
                'khusus' => 'pemenuhan_regulasi',
                // Tabelnya potret satu waktu dan TIDAK punya kolom bulan sama
                // sekali, jadi angkanya sama untuk bulan mana pun yang dipilih.
                'tanpa_bulan' => true,
                // Sumbernya menyimpan CACAH kewajiban, bukan persentase, supaya
                // rata-ratanya tertimbang; hasil baginya pecahan 0-1.
                'skala' => 100.0,
                // BAND RESMINYA BELUM ADA. Dibiarkan null dengan sengaja: sel
                // tetap menampilkan persentasenya, tetapi TANPA angka Nilai,
                // karena memberi Nilai berarti mengarang skor resmi. Akibatnya
                // parameter ini juga tidak ikut menghitung rata-rata kartu site
                // -- rataSite() memang melewati parameter tanpa band.
                //
                // Begitu band resminya ada, cukup isi 'band' dan 'ambang' di
                // sini; halaman /ohs-score-card/pemenuhan-regulasi punya
                // ambangnya sendiri di AMBANG_SEMENTARA yang juga perlu diganti.
                'band' => null,
                'ambang' => [],
                'satuan' => '%',
                'ringkas' => self::RINGKAS_RATA,
            ],
            [
                'nama' => 'Penuntasan pengendalian rekayasa',
                'sumber' => 'lead_replikasi_rekayasa_engineering',
                'site' => 'site', 'mitra' => 'perusahaan',
                'bulan' => 'month_name', 'nilai' => 'target_komitmen',
                'skala' => 100.0,
                'band' => self::BAND_REKAYASA, 'ambang' => [80, 100],
                'satuan' => '%', 'ringkas' => self::RINGKAS_RATA,
                // TEPAT 100, BUKAN LEBIH. Band teratas parameter ini menuntut
                // capaian DI ATAS komitmen, dan tidak adanya komitmen tidak
                // bisa disebut melampauinya. Yang bisa dikatakan hanya tidak
                // ada komitmen yang tertunggak, yaitu 100% -- Nilai 3.
                'kosong_berarti' => 100.0,
                'kosong_label' => 'N/A',
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
                // Tidak ada insiden berarti tidak ada pelaporan yang lewat
                // batas, jadi 100% -- satu-satunya capaian yang bernilai 4.
                'kosong_berarti' => 100.0,
                'kosong_label' => 'N/A',
            ],

            [
                'nama' => 'Kesiapan alat Emergency',
                'sumber' => 'emergency_equipment_inventory',
                'khusus' => 'kesiapan_emergency',
                // Sumbernya menyimpan CACAH alat, bukan persentase, supaya
                // rata-ratanya tertimbang: hasil bagi siap/total berupa
                // pecahan 0-1, jadi dikali seratus di sini.
                'skala' => 100.0,
                'band' => self::BAND_NAIK,
                'ambang' => [80, 90, 98, 100],
                'satuan' => '%',
                'ringkas' => self::RINGKAS_RATA,
                // YANG DIKLAIM DI SINI PERLU DISADARI. Sel kosong pada
                // parameter ini berarti kontraktor itu TIDAK PUNYA alat
                // emergency terdaftar di site itu -- 4 dari 10 kolom, karena
                // 92% alat emergency milik BC sendiri dan BC bukan kolom di
                // matriks ini. Menyebutnya 100% berarti menilai penuh sesuatu
                // yang tidak ada alatnya. Itu mengikuti keputusan "semua
                // parameter yang kosong di-N/A-kan", bukan temuan data.
                'kosong_berarti' => 100.0,
                'kosong_label' => 'N/A',
            ],
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
            // Arah naik: yang terbaik ada di batas atas band teratas.
            'kosong_berarti' => (float) ($ambang[3] ?? 100),
            'kosong_label' => 'N/A',
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
            // Arah turun: nol adalah hasil terbaik.
            'kosong_berarti' => 0.0,
            'kosong_label' => 'N/A',
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
            // Tidak ada kejadian adalah hasil terbaik.
            'kosong_berarti' => 0.0,
            'kosong_label' => 'N/A',
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
            'kosong_berarti' => 100.0,
            'kosong_label' => 'N/A',
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
            'kosong_berarti' => (float) ($ambang[3] ?? 100),
            'kosong_label' => 'N/A',
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
