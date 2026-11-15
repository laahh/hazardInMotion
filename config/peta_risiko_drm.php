<?php

/*
| Peta Risiko PJA (DRM 2.1) — aturan yang dipakai `peta-risiko:sync`.
|
| Semua pemetaan berbasis kata kunci, dihitung ulang di ClickHouse OBDS setiap sync.
| Ubah aturan di sini, lalu jalankan ulang sync; tidak ada data yang diedit manual.
*/

return [

    /* Jendela sinyal SAP untuk status (hari) dan panjang strip harian (hari). */
    'jendela_hari' => (int) env('PETA_RISIKO_JENDELA_HARI', 28),
    'strip_hari' => (int) env('PETA_RISIKO_STRIP_HARI', 84),

    /*
    | Rentang data SAP yang dibaca (hari ke belakang dari tanggal akhir) — 3 bulan.
    | Berlaku untuk semua sinyal: temuan open, Observasi/OAK, strip, tren. Temuan yang dilaporkan
    | sebelum rentang ini tidak dihitung walau masih open. Tren minggu dibatasi agar minggu paling
    | tua tetap punya jendela penuh: (tren_minggu − 1) × 7 + jendela_hari ≤ rentang_hari.
    */
    'rentang_hari' => (int) env('PETA_RISIKO_RENTANG_HARI', 91),
    'tren_minggu' => (int) env('PETA_RISIKO_TREN_MINGGU', 9),

    /* Batas waktu per query ClickHouse saat sync (detik). */
    'timeout_detik' => (int) env('PETA_RISIKO_TIMEOUT_DETIK', 900),

    /* Flag status aktivitas: tanggal mulai paling jauh N hari ke belakang. */
    'flag_maks_mundur_hari' => 14,

    /* Gap3 "berulang/sistemik": jumlah temuan terpetakan dalam jendela. */
    'ambang_berulang' => 3,

    /*
    | Aturan pemetaan teks (Master A: label SAP ↔ bahaya, Master B: teks Obs/OAK ↔ aktivitas,
    | insiden ↔ bahaya). Prinsip: JANGAN DIPAKSAKAN — lebih baik "tidak terpetakan" daripada salah.
    | - Kata dibandingkan UTUH (bukan 5 huruf awal: "penga" dulu menyatukan pengawas/pengaman/pengangkutan).
    | - kata_min: jumlah kata sama minimal.
    | - Kata yang muncul di > kata_umum_persen dari teks HIRA dianggap "kata umum" (jalan, kendaraan,
    |   terjatuh, …): boleh ikut dihitung tetapi minimal 1 kata sama harus BUKAN kata umum.
    | - tema_umum tidak dihitung sebagai tema bersama (terlalu umum, cocok ke hampir semua bahaya).
    | Uji 15 Nov 2026: Master A lama 159 ribu pasangan (rata-rata 173 bahaya per label, 93% hanya
    | 1 kata sama) → aturan ini ±7 ribu pasangan (rata-rata 18 per label).
    */
    'pemetaan' => [
        'kata_min' => 2,
        'kata_umum_persen' => 0.02,
        'tema_umum' => ['sop', 'kompetensi', 'komunikasi'],
    ],

    /* Master B: selain aturan pemetaan di atas, maksimal 3 aktivitas HIRA terkuat per teks. */
    'master_b' => ['kata_min' => 2, 'maks_per_teks' => 3],

    /*
    | Master C/D: butir checklist OAK → bahaya. Master C memasangkan aktivitas OAK (m_oak_activity)
    | dengan aktivitas HIRA; Master D memasangkan teks objek + detail objek pengamatan dengan
    | bahaya + potensi + pengendalian HIRA di aktivitas pasangan itu (aturan pemetaan yang sama,
    | plus ≥1 kata sama harus ada di teks bahayanya sendiri). Aktivitas butir diambil dari register
    | OAK yang diisi, karena checklist versi baru tidak mengikat objek ke aktivitas di master.
    | Kode id_suitability di oak_detail_object: sesuai → terkontrol, tidak sesuai → gap,
    | tidak relevan/N/A (10188) diabaikan. Kode "tidak sesuai" = 10187 masih perlu dipastikan user.
    */
    'oak_checklist' => ['sesuai' => 10186, 'tidak_sesuai' => 10187],

    /*
    | Label Hazard/Inspeksi disebut SPESIFIK bila di satu detail lokasi hanya cocok ke bahaya dari
    | ≤ N aktivitas HIRA: aktivitas itu ikut ditandai terdeteksi (seperti Observasi). Label UMUM
    | (APD, sampah, berdebu …) cocok ke banyak aktivitas, jadi hanya mengonfirmasi bahaya yang cocok.
    */
    'hazard_spesifik_maks_aktivitas' => 2,

    /*
    | Beranda PJA (halaman /peta-risiko-pja/pja/{id}), mengikuti konsep "Beranda PJA" DRM 2.1:
    | - Satu baris = kelompok aktivitas (proses bisnis) × detail lokasi × bahaya-risiko.
    | - Periode = minggu kalender Minggu–Sabtu; status per minggu: terkontrol / gap (laporan
    |   bermasalah atau tanpa laporan). Tanpa laporan dihitung GAP, bukan aman.
    | - Laporan bermasalah: belum closed pada akhir minggu, berulang (≥ ambang_berulang laporan
    |   bahaya sama di detail lokasi sama dalam seminggu), OAK Stop Pekerjaan, butir checklist tidak sesuai.
    | - Bahaya umum: teks bahaya sama di ≥ umum_min_kelompok kelompok dalam satu grup lokasi →
    |   ditampilkan terpisah, tidak masuk pembagi.
    */
    'beranda' => [
        'minggu' => 12,
        'hari' => 28,
        'ambang_berulang' => 3,
        'umum_min_kelompok' => 4,
        'tekanan' => ['u4_tinggi' => 0.6, 'u4_sedang' => 0.3, 'hari_tinggi' => 5, 'beruntun_tinggi' => 3, 'hari_sedang' => 3],
        'lokasi_lama_hari' => 14,
    ],

    /* Status SAP yang tidak dihitung sama sekali. */
    'status_buang' => ['DELETED', 'BUKAN TEMUAN'],

    /* Insiden: hanya yang diinvestigasi, tanpa kategori pelanggaran. */
    'insiden' => [
        'ccr_status' => 'INVESTIGASI',
        'kategori_buang' => ['Pelanggaran Golden Rules', 'Pelanggaran PSPP', 'Test', ''],
    ],

    /*
    | Kamus 29 tema bahaya — salinan kartu Metabase 341 (DRM2.1).
    | Jembatan teks SAP ↔ bahaya HIRA: pasangan wajib punya tema bersama
    | DAN minimal 1 kata kunci bersama (kartu 342/343).
    */
    'tema' => [
        'jalan' => 'jalan|licin|undulating|berlubang|amblas|tikungan|tanjakan|turunan|ramp',
        'kendaraan' => 'kendaraan|unit |kelayakan|rem |ban |klakson|alarm mundur|kabin|dump truck|alat berat|p2h|komponen',
        'tanggul' => 'tanggul|berm|bundwall|bund wall|pembatas',
        'apd' => 'apd|helm|sepatu|kacamata|sarung tangan|rompi|masker|earplug|sabuk pengaman|harness',
        'debu' => 'debu|dust|water truck|penyiraman|asap|emisi',
        'rambu' => 'rambu|marka|signage|bendera|penerangan|pencahayaan|lampu',
        'dumping' => 'dumping|disposal|spoil|tumpahan material',
        'listrik' => 'listrik|kabel|panel|grounding|loto|arus|tegangan|genset|stop kontak|elektrik|petir',
        'biologi' => 'ular|satwa|hewan|lebah|serangga|nyamuk|biologi|binatang|tanaman|pohon',
        'darurat' => 'apar|kebakaran|api|darurat|evakuasi|hydrant|p3k|emergency|pemadam|terbakar',
        'jarak' => 'jarak aman|jarak|interaksi|tabrak|bertabrakan|blind spot|pejalan',
        'fatigue' => 'fatigue|lelah|kantuk|ngantuk|tidur|istirahat|fit to work',
        'ketinggian' => 'ketinggian|tangga|scaffold|perancah|jatuh|railing|pagar pengaman|handrail|platform',
        'lifting' => 'lifting|crane|angkat|sling|rigging|forklift|hoist|derek|seling',
        'b3' => 'b3|kimia|bbm|solar|oli|tumpahan|limbah|spill|pelumas|bahan bakar|msds',
        'housekeeping' => 'kebersihan|housekeeping|rapi|sampah|bersih|berserakan|5r',
        'lereng' => 'lereng|longsor|retakan|crack|geoteknik|highwall|low wall|bench|slope|runtuh',
        'air' => 'air |banjir|drainase|sump|genangan|saluran|pompa|kolam|sedimen|settling',
        'sop' => 'sop|prosedur|izin|jsa|permit|metode kerja|instruksi|p5m|briefing|toolbox|ijin',
        'kompetensi' => 'kompetensi|pelatihan|sertifikat|simper|kesadaran|disiplin|pengawasan|awareness|training|lisensi',
        'ergonomi' => 'ergonomi|posisi|postur|manual handling|membungkuk|getaran|bising|kebisingan',
        'perkakas' => 'perkakas|tools|alat tangan|gerinda|las |welding|bor |palu|kunci|mesin',
        'guarding' => 'guarding|pelindung|cover|penutup|barikade|barrier|isolasi|area terbatas|akses',
        'konveyor' => 'conveyor|konveyor|belt|roller|pulley|rotating|terjepit|terlilit|crusher',
        'blasting' => 'blasting|peledakan|handak|peledak|detonator|ledak',
        'komunikasi' => 'radio|komunikasi|koordinasi|sinyal',
        'higiene' => 'kesehatan|higiene|makanan|air minum|toilet|kantin|panas|heat|hidrasi|mcu',
        'bangunan' => 'bangunan|gedung|atap|lantai|dinding|fasilitas|kantor|bengkel|mess|workshop',
        'marine' => 'kapal|tongkang|barge|jetty|dermaga|marine|pelampung|life jacket|laut|sandar|tambat|mooring',
    ],

    /* Kata yang diabaikan saat mencari kata kunci bersama (kartu 342-345). */
    'stopword' => [
        'yang', 'dengan', 'dari', 'pada', 'untuk', 'tidak', 'atau', 'dalam', 'oleh', 'area', 'kerja', 'ada', 'dan',
        'terdapat', 'belum', 'sudah', 'sesuai', 'memadai', 'kurang', 'sebagai', 'antara', 'saat', 'karena', 'akibat',
        'dapat', 'potensi', 'pekerja', 'lokasi', 'unit', 'kondisi', 'lainnya', 'umum',
        // Ditambahkan 15 Nov 2026 (kata sambung/umum yang memicu pasangan paksa):
        'melakukan', 'menggunakan', 'dilakukan', 'tanpa', 'terhadap', 'adanya', 'serta', 'akan', 'bisa', 'harus',
        'masih', 'lebih', 'telah', 'setelah', 'sebelum', 'selama', 'para', 'hingga', 'sampai', 'yaitu', 'juga',
        'pekerjaan', 'aktivitas', 'standar', 'layak', 'secara',
    ],

    /*
    | Proses bisnis: 11 proses inti + 6 pendukung (daftar & deskripsi dari Peta HIRA Risiko Dinamis).
    | Dicocokkan ke "aktivitas sub aktivitas" HIRA; aturan PERTAMA yang cocok menang (satu bahaya = satu
    | proses). Urutan sengaja: proses spesifik dulu, proses umum (OB, transportasi, kantor) belakangan.
    | Tidak cocok = kode X "Belum terpetakan".
    */
    'proses_bisnis' => [
        'DNB' => ['Drilling & Blasting', 'inti', 'blast|peledak|handak|\ban ke\b|mmu|detonator|lubang ledak|mesin bor|mata bor|blast hole|pengisian lubang'],
        'MNT' => ['Maintenance, Workshop & Pekerjaan Khusus', 'pendukung', 'workshop|maintenance|perawatan alat|perawatan unit|perbaikan unit|repair|servis|service|pengelasan|\blas\b|tyre|tire|pit service|fixed plant|ketinggian|ruang terbatas|confined|lifting|pengangkatan|gerinda|forklift|tools|crane|gouging|fabrikasi|backlog|greasing'],
        'CG' => ['Coal Getting', 'inti', 'coal getting|underland|loading batubara|coal loading|penggalian batubara|cleaning coal'],
        'CH' => ['Coal Hauling', 'inti', 'coal hauling|hauling coal|hauling batubara|angkut batubara|pengangkutan batubara'],
        'CR' => ['Crushing', 'inti', 'crush|hopper|feeding|fine coal|pembersihan belt|rehandling ke crusher'],
        'SP' => ['Stockpiling', 'inti', 'stockpile|stocking|\brom\b|penimbangan|timbangan|spontaneous|rehandling|coal dumping'],
        'BL' => ['Barge Loading', 'inti', 'pre loading|barge loading|loading barge|belt conveyor|coal barging|beaching|to loader|at loader'],
        'TS' => ['Transshipment', 'inti', 'transhipment|transshipment|\bogv\b|mother vessel|floating crane'],
        'BG' => ['Barging', 'inti', 'tongkang|sandar|towing|shifting|draught|speedboat|speed boat|barge|jetty|tug ?boat|marine'],
        'EXP' => ['Exploration & Land Clearing', 'inti', 'land clearing|pembersihan lahan|seismik|geolistrik|georadar|geofisika|geologi|eksplorasi|coring'],
        'PLN' => ['Planning & Design', 'inti', 'perencanaan|planning|desain|design|geoteknik|lereng|slope|survey|survei|hidrologi|amdal|topografi'],
        'OB' => ['OB Removal', 'inti', 'overburden|\bob\b|soil|material lunak|lumpur|dumping|sloping|trimming|disposal|front loading|\bloading (ob|soil|material)|pemberaian|earth work|pekerjaan tanah|dozing'],
        'ENV' => ['Air, Lingkungan, Reklamasi & Laboratorium', 'pendukung', 'pompa|pumping|dewatering|hdpe|pipa|kolam|limbah|sampah|reklamasi|revegetasi|nursery|pembibitan|penanaman|lingkungan|sampling|sampel|laboratorium|analisa|air buangan|kompos|acid mine|netralisasi'],
        'INF' => ['Infrastruktur, Sipil & Kelistrikan', 'pendukung', 'jalan|road|gorong|drainase|beton|baja|struktur|konstruksi|kelistrikan|listrik|elektrikal|power house|jaringan|chipseal|\bctb\b|bangunan|pembangunan|tiang|bekisting|pengecoran|arsitektural|plumbing|mekanikal|tower'],
        'TRN' => ['Transportasi, Mobilisasi & Operasi Unit', 'pendukung', 'mobilisasi|demobilisasi|transportasi|angkutan|kendaraan|unit|hauling|shift change|mengemudi|dinas luar kota|\bbus\b|manhaul|parkir|fuel|water truck|pengoperasian|loading|unloading'],
        'HSE' => ['HSE, Emergency & Pengawasan', 'pendukung', 'emergency|darurat|pengawasan|patroli|inspeksi|audit|klinik|pasien|\bdms\b|cctv|covid|safety|investigasi|apar|pemadam|evakuasi|penjagaan|pengawalan'],
        'ADM' => ['Kantor, Administrasi & Aktivitas Umum', 'pendukung', 'kantor|perkantoran|office|administrasi|administratif|training|pelatihan|meeting|pertemuan|catering|konsumsi|makan|housing|mess|toilet|dokumen|umum|hotel|benefit'],
    ],

    /*
    | Group Lokasi untuk DETAIL LOKASI: aturan pertama yang cocok menang.
    | Dicocokkan ke "detail lokasi || lokasi" (huruf kecil, regex RE2).
    | Detail lokasi tanpa kecocokan = "Belum terpetakan".
    */
    'grup_detail_lokasi' => [
        'Drill & Blast' => 'drill|blast|handak|peledak|magazine',
        'Eksplorasi' => 'eksplorasi|pengeboran|\\brig\\b|survey|geolog|coring|geofisika',
        'Transhipment' => 'transhipment|\\bkapal|tug ?boat|\\bmv\\b|floating|main deck|engine room|ogv|barge|\\bbg\\b|\\btb\\b|cts bulk|\\blct\\b',
        'Jetty/Port/Beaching Point' => 'jetty|\\bport\\b|beaching|dermaga|dolphin|barging|tongkang|pelabuhan|speedboat',
        'Sungai' => 'sungai|\\balur\\b',
        'CPP / ROM' => '\\brom\\b|cpp|crusher|stockpile|coal processing|inloading|conveyor|cr/hp/cv|hopper|\\bblc\\b|fixed plant',
        'WMP/Sump/Void' => 'wmp|sump|void|kolam|settling|dewatering|pompa|pumping|check pond|sedimen|\\bpond|drainase|drainage|tailing|waterfill|\\btps\\b|lb3|ipal|\\bwtp\\b|limbah',
        'Disposal' => 'disposal|dumping|\\bopd|\\bipd|stocksoil|low ?wall|timbunan',
        'Loading Point' => 'front|loading|handling soil|coal getting',
        'Workshop/Pit Stop' => 'workshop|\\bbays?\\b|tyre|tire|fabrikasi|washing|washpad|oil trap|tools? room|perbaikan|maintenance|maintenace|service|warehouse|gudang|power house|compres|spare ?part|pit ?stop|\\byard|\\bws\\b|plant|genset|laboratorium|\\blab\\b|reaktivasi|heavy equipment|storage|maintank',
        'Parking Area/Fuel Station' => 'parkir|parking|fuel|refuel|hssc|pengisian|\\bdsl\\b|\\bfs\\b',
        'Public Road' => 'jalan negara|jalan umum|public|jalan poros|jalan kabupaten|jalan provinsi',
        'Area Transportasi' => 'transportasi|halte|sarana lv|\\bbus\\b|manhaul|rest area',
        'Office / Mess' => 'office|mess|kantin|dapur|accommodation|kantor|camp|\\bpos\\b|control room|bandara|view ?point|look ?out|change shift|assembly|klinik|masjid|mesjid|musholla|toilet|kost|perumahan|hotel|ruang|gerdas|training|station er|fasilitas umum|sport|tower|risk intervention|\\bric\\b|catering|security|patroli',
        'Project' => 'project|proyek|pembangunan|underpass|diversion|konstruksi|revegetasi|reklamasi|land clearing|nursery|pembibitan',
        'Hauling Road' => 'hauling|crossing|simpang|barokah',
        'Mine Road' => 'mine road|akses|jalan|\\bjln|\\bjl\\b|road|ramp',
        'Area Pit' => '\\bpit\\b|dinding tambang|\\bblok\\b|bench|highwall|lereng',
    ],

    /*
    | Group Lokasi untuk AKTIVITAS HIRA: SEMUA aturan yang cocok dipakai (satu aktivitas
    | bisa di beberapa grup). Dicocokkan ke "aktivitas sub aktivitas".
    | Aktivitas tanpa grup (mis. "Umum") hanya masuk baseline detail lokasi yang
    | punya laporan Observasi/OAK untuk aktivitas itu.
    */
    'grup_aktivitas' => [
        'Drill & Blast' => 'blast|peledak|handak|\\ban ke\\b|mmu|detonator|lubang ledak|mesin bor|blast hole',
        'Eksplorasi' => 'eksplorasi|survey|survei|geofisika|georadar|geolistrik|seismik|logging|coring|pemboran|pengeboran|sampling|sampel|hidrologi|geolog|topografi',
        'Transhipment' => 'transhipment|ogv|mooring|floating crane|kapal|tug ?boat|barge|tongkang|speedboat|ketinting',
        'Jetty/Port/Beaching Point' => 'jetty|sandar|pelabuhan|beaching|dermaga|cast off|shifting tongkang|bongkar.muat',
        'Sungai' => 'sungai',
        'CPP / ROM' => 'crush|cpp|hopper|fixed plant|\\brom\\b|stockpile|conveyor|coal processing|timbangan|counter weight',
        'WMP/Sump/Void' => 'pompa|pumping|dewatering|hdpe|pipa|kolam|air buangan|drainase|gorong|sump|lumpur|settling|netralisasi|koagulasi|acid mine|wmp|paritan',
        'Disposal' => 'dumping|penimbunan|disposal|timbun',
        'Loading Point' => 'loading|ob removal|soil removal|coal getting|excavat|front|pemberaian|overburden|\\bob\\b|dragflow',
        'Hauling Road' => 'hauling|pengangkutan material|transit hauler',
        'Mine Road' => 'jalan|road|chipseal|\\bctb\\b|rambu|traffic|penyiraman|tanggul|bund wall|pit service|sloping|lereng|geoteknik|grading|perawatan badan|overlayer|cement treated',
        'Workshop/Pit Stop' => 'workshop|maintenance|perawatan alat|repair|perbaikan unit|servis|service|tyre|tire|las|pengelasan|gerinda|forklift|tools|ragum|fabrikasi|gouging|acetylene|oksigen|p2h|backlog|midlife|mekanikal|greasing|inflator|cuci|mencuci|washing|power house|genset|laboratorium|analisa sampel|\\bqc\\b',
        'Parking Area/Fuel Station' => 'fuel|pengisian|parkir|refuel',
        'Area Transportasi' => 'transportasi|angkutan|\\bbus\\b|manhaul|mengemudi|kendaraan|dinas luar kota|mobilisasi',
        'Office / Mess' => 'kantor|perkantoran|office|meeting|pertemuan|toilet|catering|konsumsi|makan|mess|housing|dokumen|kantin|dapur|istirahat|penjagaan|patroli|pasien|klinik|training|pelatihan',
        'Project' => 'land clearing|pembersihan lahan|reklamasi|revegetasi|nursery|pembibitan|penanaman|kompos|beton|konstruksi|pembangunan|project|projek|bekisting|pengecoran|struktur|tiang pancang|arsitektural|komposit|batu|kayu|landscape|pengecatan|preparation|farming|sapi|ternak|golf|atsiri|instalasi|installasi|tower',
        'Area Pit' => 'pit monitoring|area tambang|tambang',
    ],
];
