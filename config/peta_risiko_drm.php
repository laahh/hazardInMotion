<?php

/*
| Peta Risiko PJA (DRM 2.1) — aturan yang dipakai `peta-risiko:sync`.
|
| Baseline = master mapping DRM 2.1 (lihat 'master' di bawah): baris HIRA v31, grup lokasi, detail
| lokasi, label SAP, dan teks OBS/OAK dari workbook; kelompok aktivitas, tema, dan PJA dihitung ulang.
| Ubah aturan di sini atau impor ulang workbook, lalu jalankan ulang sync.
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

    /*
    | Master C/D: butir checklist OAK → bahaya. Master C memasangkan aktivitas OAK (m_oak_activity)
    | dengan aktivitas HIRA; Master D memasangkan teks objek + detail objek pengamatan dengan
    | bahaya + potensi + pengendalian HIRA di aktivitas pasangan itu (aturan pemetaan yang sama,
    | plus ≥1 kata sama harus ada di teks bahayanya sendiri). Aktivitas butir diambil dari register
    | OAK yang diisi, karena checklist versi baru tidak mengikat objek ke aktivitas di master.
    | Kode id_suitability di oak_detail_object: sesuai → terkontrol, tidak sesuai → gap,
    | tidak relevan/N/A (10188) diabaikan. Kode dipastikan di bcbeats.oak_detail_object 17 Nov 2026
    | (10187 = Tidak Sesuai, catatannya di tx_suitability). stop = id_conclusion "Stop Pekerjaan"
    | di bcbeats.oak_register (19501 = Pekerjaan Dilanjutkan).
    | Saat ini TIDAK dipakai: OBS / OAK ditangkap lewat label persis M11/M12 (dokumentasi Risk Matrix 5x5, 17 Nov 2026).
    */
    'oak_checklist' => ['sesuai' => 10186, 'tidak_sesuai' => 10187, 'stop' => 19502],

    /*
    | Tabel transaksi OAK di ClickHouse (koneksi clickhouse_obds). Bawaan bcbeats (tersinkron harian); user koneksi
    | butuh GRANT SELECT ON bcbeats.oak_register / bcbeats.oak_detail_object. Bisa dialihkan lewat .env, mis. ke
    | obds.oak_register / obds.oak_detail_object (salinan lama, berhenti 28 Okt 2026) sementara hak akses diurus.
    */
    'oak_tabel' => [
        'register' => env('PETA_RISIKO_OAK_REGISTER', 'bcbeats.oak_register'),
        'detail' => env('PETA_RISIKO_OAK_DETAIL', 'bcbeats.oak_detail_object'),
    ],

    /*
    | Label OBS (M11) / OAK (M12) → baris HIRA. false = persis master (dokumentasi Risk Matrix 5x5, baseline tetap:
    | OBS 826 baris, OAK 93 baris). true = label juga menandai baris lain dengan aktivitas + sub aktivitas yang sama.
    */
    'label_obs_oak_per_aktivitas' => false,

    /*
    | Aktivitas OAK → aktivitas HIRA yang IKUT DIHITUNG di samping label M12. Sengaja KOSONG (keputusan user 17 Nov 2026):
    | mapping harus spesifik per baris (aktivitas – sub – bahaya), jangan dipaksakan. Tautan tingkat aktivitas membuat
    | satu OAK menutup semua bahaya di aktivitas itu (mis. OAK Refueling → semua bahaya "Pembersihan Dispenser").
    | Format: 'aktivitas OAK' => ['aktivitas HIRA', ...]. Isi hanya untuk tautan yang sudah disetujui pemilik mapping.
    */
    'oak_aktivitas_hira' => [],

    /*
    | Butir Tidak Sesuai pada OAK ikut gap baris. false (bawaan): tanpa mapping butir checklist → bahaya per baris,
    | butir Tidak Sesuai tidak bisa dipastikan menyangkut bahaya baris itu; gap OAK cukup Stop Pekerjaan (dokumentasi).
    */
    'oak_tidak_sesuai_gap' => false,

    /*
    | Label Hazard (M09) yang TIDAK dihitung, menurut kolom sumber di master. Bawaan kosong (18 Nov 2026): master per
    | baris menjadi acuan utama dan label yang janggal dihapus langsung di editor master. Bisa diisi mis.
    | ['Pemetaan label baru (label)'] (250 label, tidak ada di dokumentasi Risk Matrix; 33% pasangannya janggal).
    */
    'm09_sumber_dikecualikan' => [],

    /*
    | USULAN (TIDAK DIHITUNG) — draf kurasi aktivitas OAK → aktivitas HIRA dari 17 Nov 2026, bahan awal pemilik mapping.
    | Terlalu longgar untuk dipakai langsung (tingkat aktivitas, bukan per bahaya). Pindahkan tautan yang disetujui ke
    | `oak_aktivitas_hira`, atau lebih baik jadikan label M12 per baris di Master mapping.
    */
    'oak_aktivitas_hira_usulan' => [
        'Pengoperasian Kendaraan dan Unit, termasuk Hauling OB & Coal' => [
            'Coal Hauling', 'Hauling Coal', 'Hauling material', 'Hauling OB', 'Hauling Soil', 'Driving',
            'Pengoperasian kendaraan dan unit', 'Pengoperasian Unit & Kendaraan', 'Pengoperasian Unit dan Kendaraan',
            'Pengoperasian unit ke luar site atau jalan negara', 'Pengoperasian WT & FT', 'Penggunaan kendaraan di malam hari',
            'Penggunaan kendaraan saat hujan', 'Parkir', 'Transportasi', 'Mobilisasi A2B', 'Rental A2B', 'Penggunaan ICC',
            'Pengunaan ICC di dalam unit',
        ],
        'Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang' => [
            'Aktivitas Perbaikan dan Pemeliharaan Unit dan Kendaraan', 'Servis dan perbaikan', 'Pit Service', 'Workshop',
            'Pekerjaan Workshop', 'Indoor Maintenance', 'Outdoor Maintenance', 'Machining/ Fabrikasi', 'Operasional Maintenance',
            'Repair & Maintenance Execution', 'Maintenance Improvement', 'Perawatan', 'Pemeliharaan', 'Pencucian unit',
            'Mengikat Hose dengan menggunakan kawat (fitting)', 'Pekerjaan Mekanikal', 'Mengoperasikan Forklift',
        ],
        'Dumping' => [
            'Dumping material OB', 'Dumping Material OB', 'Dumping OB', 'Soil Dumping', 'Dumping Material Lunak di Channel Dump',
            'Dumping lumpur', 'Dumping material lumpur lunak', 'Dumping di ROM', 'Coal Dumping', 'Dumping batubara ke hopper',
            'Dumping Coal ke Hoper', 'Penimbunan material lumpur', 'Counter Weight', 'Bund Wall',
            'Pengawasan Area Penimbunan Menggunakan Alat Bantu Teropong',
        ],
        'Loading' => [
            'Coal Loading', 'Loading Over Burden (OB)', 'Loading Material Spoil', 'Loading menggunakan DT',
            'Loading batubara dengan Dozer/loader',
            'Loading batubara ke  Drown Hopper atau Reclaimer Truck Loading Conveyor menggunakan Dozer, Excavator dan WA',
            'Loading batubara ke unit DT/SDT Menggunakan Truck Loading Conveyor (TLC), Silo Dan Wheel Loader (WA)',
            'Melakukan loading material Lumpur', 'Melakukan loading material Lunak (Rawa/ Disposal)',
            'Loading Lumpur Cair Menggunakan Excavator (Dragflow)',
            'Loading Soil / Overburden di atas lobang galian bawah tanah / Terowongan',
            'Loading/hauling lumpur/fine coal / material reject dari area fixed plant dibuang ke disposal/penumpukan sementara',
            'Pemuatan material lunak', 'Persiapan Loading Material Lunak', 'OB Removal', 'Soil Removal', 'Coal Getting',
            'Feeding ke DDH menggunakan wheel loader',
        ],
        'Pengelolaan Tools' => [
            'Penggunaan Tools', 'Pekerjaan hand Tools', 'Inspeksi Tools', 'Perawatan Alat', 'Penambahan Peralatan',
        ],
        'Hauling Road Maintenance' => [
            'Perawatan jalan hauling / Hauling Road Maintenance (HRM)', 'Pembuatan & Perawatan Jalan', 'Jalan', 'Road Construction',
            'Pekerjaan Pembangunan Jalan dengan Double Chipseal', 'Penyiraman', 'Pekerjaan Perkerasan',
            'Pekerjaan dan Maintenance Rambu Jalan', 'Pekerjaan Landscape Tanggul Jalan', 'Pembuatan  Akses Jalan Menuju lokasi pemboran',
        ],
        'Bekerja di Dekat Air' => [
            'Kolam', 'Dewatering', 'Mine Dewatering', 'Pekerjaan Pengeringan / Dewatering', 'Pumping', 'Pompa Tambang',
            'Intake Pump (WMP, Tunnel, Jetty, chemical)', 'Pekerjaan di Jetty / dekat sungai', 'Pembersihan settling pond',
            'Pembersihan WMP', 'IPAL dan Pit Void Utilization', 'Pemantauan dan pengelolaan kualitas air WMP',
            'Pemantauan dan pengawasan pengelolaan Air', 'Pemantauan dan pengawasan pengelolan Air', 'Operasional WTP',
            'Pengambilan Contoh Uji sampel  Air Sungai + Biota', 'Mengoperasikan Mesin Tempel',
        ],
        'Bekerja di Area Coal Processing Plant' => [
            'Coal crushing', 'Crushed coal feeding & dozing', 'Pekerjaan  Fixed Plant', 'Bekerja di area fixed plant dalam kondisi unit running',
            'Buka tutup hopper, tes beban Belt Conveyor dll',
            'Cleaning batubara di Area Tunnel & Wingwall Reclaimer Truck Loading Conveyor',
            'Cleaning lantai, setting bukaan hopper, maintenance dibawah transfer dll',
            'Hand picking / pengambilan dilusi, bone coal, kontaminan dari primary / secondary crusher',
            'Pemecahan batubara oversize atau boulder OB/dilusi di primary/secondary crusher', 'Rehandling batubara ke crusher',
            'Rehandling ke crusher', 'Coal stocking', 'Maintenance stockpile', 'Maintenance stockpile / ROM', 'Pembersihan fine coal / plant',
            'Pembersihan settling pond / fine coal di area fixed plant (Crusher/BLC) menggunakan Excavator',
            'Pembersihan belt dengan air, water spraying untuk mengurangi debu', 'Sampling batubara manual di atas belt / BLC',
            'Sampling batubara manual di ROM', 'Sampling', 'Penimbangan batubara', 'Penimbangan DT muatan batubara / DT Kosongan',
            'Inspeksi Area CPP', 'Pencarian metal kontaminan', 'Loading dengan Belt Conveyor', 'Trimming', 'Pengoperasian Dust Spraying',
        ],
        'Pengisian Bahan Bakar Cair (Refueling)' => [
            'BAHAN BAKAR CAIR', 'FUEL MANAGEMENT SYSTEM', 'Aktivitas P2H & Pengisian fuel', 'Aktivitas Pembersihan Dispenser',
            'pengisian BBC', 'Pengisian Tangki Solar', 'Transfer fuel dari Fuel Trusk ke Fuel Tank', 'Pengelolaan Oil Trap',
        ],
        'Bekerja dengan Panas' => [
            'Pengelasan', 'Gerinda menggunakan gerinda duduk (portable)', 'Gerinda menggunakan gerinda tangan',
            'Gouging/arc welding with air pressure (memotong/membuang bekas las-lasan/motong plate dengan menggunkan angin bertekanan)',
            'Membengkokkan atau membentuk plate/pipa dengan menggunakan oxt dan acetylene)', 'Memotong Material/plat menggunakan Oksigen dan Acyteline',
            'Pemotongan', 'Pengelasan arm blade Bulldozer', 'Pengelasan blade Bulldozer', 'Pengelasan Bucket unit Excavator',
            'Pengelasan handrill pada unit Excavator, Wheel Loader, Fuel Truck, Water Truck', 'Pengelasan material/plat menggunakan miller',
            'Pengelasan Vessel Unit Dump Truck', 'Pengelasan(Repair) Body Skin Vessel unit Trailer', 'Pekerjaan Pengelasan Tangki Fuel',
            'Pekerjaan Pengelasan Tangki Water Truck',
        ],
        'Pengangkutan Karyawan' => [
            'Pengangkutan karyawan', 'Penggunaan Transportasi Darat (Kendaraan Ringan & BUS)', 'Transportasi darat dari / ke Head Office',
            'Penggunaan angkutan umum', 'Transportasi air dari/ke Dermaga Head Office',
        ],
        'Barging Batubara di Jetty' => [
            'Loading Barge', 'After Loading Barge', 'Sandar di Jetty', 'Lepas Sandar Tongkang dari Jetty / Cast Off',
            'Shifting Tongkang di Jetty', 'Loading dengan Belt Conveyor / Coal Barging', 'Mooring dan unmooring barge', 'Towing Barges',
            'Barge Cleaning', 'Draught Survey Barge', 'Perawatan Jetty Dolphin', 'Beaching Point', 'Aktifitas Beaching Point',
        ],
        'Persimpangan' => [
            'Jalan', 'Pengoperasian kendaraan dan unit', 'Pengoperasian Unit & Kendaraan', 'Pengoperasian Unit dan Kendaraan',
        ],
        'Bekerja di Dekat Tebing' => [
            'Melakukan Pekerjaan dekat Lereng', 'Aspek Geoteknik', 'Sloping', 'Overlayer',
        ],
        'Mobilisasi Unit Drilling Machine (DM) dan Pengeboran Peledakan' => [
            'Kegiatan Pemboran', 'Pengeboran', 'Pengeboran untuk peledakan', 'Mobilisasi Mesin Bor', 'Memarkir unit bor', 'Mengganti mata bor',
            'Persiapan pemboran', 'Pra Pengeboran', 'Kegiatan Pasca Pemboran', 'Pengamanan Area pengeboran',
        ],
        'Pemompaan Tyre' => [
            'Assembling tyre', 'Inspektion Tyre', 'Pemasangan tyre LV di luar workshop', 'Pemasangan tyre LV di Workshop',
            'Pengoperasian tyre changer', 'Pengoperasian/penggunaan tire inflator', 'Remove & Instal tyre',
            'Remove & Instal tyre di Jalan Hauling', 'Tyre Management',
        ],
        'Shift Change' => ['Shift Change', 'Hot Shift change'],
        'Pengangkatan & Pengangkutan Unit/Material' => [
            'Pengangkatan', 'Lifting & Support', 'Mobilisasi dan Demobilisasi', 'Mobilisasi-demobilisasi', 'Towing Loader',
            'Pengangkutan & Pembuangan Pipa Bekas', 'pembongkaran tower',
            'Pembongkaran bahan kimia di Lokasi Pengguna menggunakan heavy equipment (forklift/crane)',
            'Mobilisasi sarana pendukung ( tower lamp, pos pengawas, toilet )',
        ],
        'Peledakan dan Pasca Peledakan' => [
            'Blasting', 'Aktifitas Peledakan Tidur', 'Desain Peledakan', 'Pengecekan pasca peledakan', 'Pengecekan rangkaian akhir',
            'Perangkaian', 'Pengawasan Blasting',
            'Memasang demarkasi dan rambu area blasting disekitar area drilling dan pengisian bahan peledak',
            'Pengisian Bahan Peledak ke Lubang Ledak / Priming',
            'Pengisian handak curah ke lubang ledak oleh truck handak/MMU ( Charging )',
        ],
        'Loading Unloading Transhipment' => [
            'Loading OGV', 'Pre Loading', 'Pre Loading OGV', 'Pre Loading bunker/ water', 'Post Loading OGV',
            'Mooring  dan UnmooringBarge at Loader/OGV', 'Mooring/ Unmooring LCT to Loader', 'Draught Survey Vessel',
            'Berkunjung Transhipment Point', 'Inspeksi transhipment point', 'Loading & Unloading di Pelabuhan',
            'Bongkar-Muat Agregat Diatas Tongkang', 'Patroli, Inspeksi, Observasi Alat Transportasi Air',
        ],
        'Jalan Tambang' => [
            'Jalan', 'Penyiraman', 'Pekerjaan dan Maintenance Rambu Jalan', 'Pembuatan & Perawatan Jalan',
        ],
        'Transportasi & Penanganan Bahan Peledak (Handak)' => [
            'Pengangkutan Bahan Peledak', 'Pengoperasian Truck Handak', 'Penanganan Handak di Lokasi peledakan', 'Pencampuran Handak',
            'Pengeluaran Handak', 'Penyimpanan handak', 'Pembongkaran bahan peledak di gudang handak',
            'Pengaturan  Ruangan Dan Persyaratan Teknis Gudang Bahan Peledak', 'Loading AN ke MMU',
            'Loading Emulsi / Watergel / Watergel Dengan MMU', 'Pengisian AN ke MMU', 'Bongkar muat AN di pelabuhan',
        ],
        'Eksplorasi' => [
            'Aktifitas Survey', 'Survey', 'Aktivitas Lapangan', 'Mapping Geology', 'Pemetaan Geologi', 'Logging Geofisika',
            'Pengukuran Geolistrik', 'Pengukuran Georadar', 'Pengukuran Seismik', 'Penandaan/pembuatan titik', 'Kegiatan Hidrologi',
            'Survey lokasi terkait dengan verifikasi proses perijinan',
        ],
        'Bekerja di Ketinggian' => ['Bekerja di ketinggian'],
        'Pekerjaan Listrik' => [
            'Kelistrikan', 'Pekerjaan Kelistrikan', 'Pekerjaan Elektrikal', 'Electrical Equipment', 'Power House', 'Power Plant',
            'Sub Distribution Panel (SDP)', 'PAP Oli Transformator', 'Pengoperasian Unit Genset', 'Instalasi CCTV',
            'Maintenance CCTV area tambang', 'Pekerjaan Mekanikal, Elektrikal & Plumbing',
        ],
        'Pengoperasian Slurry Pump di Sump' => ['Pumping', 'Pompa Tambang', 'Mine Dewatering', 'Dewatering'],
        'Inspeksi Geoteknik' => ['Aspek Geoteknik', 'Kegiatan Monitoring Slope Stabilty Radar', 'Pit Monitoring'],
        'Bekerja di Underland' => ['Loading Soil / Overburden di atas lobang galian bawah tanah / Terowongan'],
    ],

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

    /*
    | PJA yang tidak ikut dashboard (keputusan user 19 Nov 2026): nama mengandung salah satu kata ini (tanpa beda
    | huruf besar/kecil) dibuang dari mapping lokasi → PJA saat sync, jadi tidak punya detail lokasi / baris.
    | "inspektor" dan "inspector" = PJA Inspektor/Inspector (Mining, Safety, HSE).
    */
    'pja_dikecualikan' => ['inspektor', 'inspector'],

    /* Observasi yang deskripsi/umpan balik/tindakannya berisi frasa ini (huruf kecil) tidak dihitung sebagai deteksi. */
    'obs_tanpa_aktivitas' => ['tidak ada aktivitas'],

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
    | Master mapping DRM 2.1 (workbook "DRM21_Mapping_Master_Overall.xlsx", diimpor lewat
    | `php artisan peta-risiko:impor-master`). Dari workbook diambil bagian yang hasil kurasi dan
    | tidak punya aturan tertulis: M01 baris HIRA v31, M04/M05 grup lokasi → baris, M06 detail
    | lokasi → grup, M09 label SAP → baris, M11/M12 teks aktivitas OBS/OAK → baris.
    | Dihitung ulang dari ClickHouse setiap sync: kelompok aktivitas (M02, di bawah), PJA per lokasi
    | (M07/M08 dari obds.aaj_lokasi_dan_pja), tema checklist → baris (M15/M16, di bawah).
    */
    'master' => [
        'dir' => 'peta-risiko-drm-master',
        /*
        | Pengguna yang boleh mengubah tabel master di /peta-risiko-pja/master: daftar email atau id user,
        | dipisah koma (env PETA_RISIKO_MASTER_EDITOR). Kosong = semua pengguna yang login boleh mengubah.
        | Semua perubahan tetap dicatat di drm_master_log.
        */
        'editor' => array_values(array_filter(array_map('trim', explode(',', (string) env('PETA_RISIKO_MASTER_EDITOR', ''))))),
    ],

    /*
    | Kelompok aktivitas (M02, fam.py): dicocokkan ke teks AKTIVITAS HIRA (bukan sub), ke teks aktivitas
    | OBS, dan ke "aktivitas / sub" OAK (tanpa membedakan huruf besar-kecil). Regex PERTAMA yang cocok
    | menang, urut K1 → K25; tidak cocok = K0. Kunci tetap bernama proses_bisnis supaya tampilan,
    | cakupan PROSES|kode|site, dan flag yang sudah ada tetap jalan. Format: [nama, jenis, regex].
    */
    'proses_bisnis' => [
        'K1' => ['Layanan Kesehatan & Respon Tumpahan', 'inti', 'tanda-tanda vital|jalan nafas|sphygmo|stehoscope|oxygen cylind|spill|tumpahan|p3k|medis|klinik|ambulan|pasien'],
        'K2' => ['Peledakan & Bahan Peledak', 'inti', 'handak|peledak|blasting|charging|priming|\bmmu\b|emulsi|watergel|\bAN\b|ammonium|detonator|stemming|misfire|rangkaian akhir|rangkaian|perangkaian|demarkasi'],
        'K3' => ['Pemboran', 'inti', 'pemboran|pengeboran|\bbor\b|drilling|drill |mata bor|unit bor|\bdm\b|logging geofisika'],
        'K4' => ['Jetty, Barging & Marine', 'inti', 'jetty|barg|tongkang|\bogv\b|mooring|dermaga|beaching|transhipment|pelabuhan|\blct\b|vessel|kapal|draught|draft|bongkar.?muat|towing|sandar|alat transportasi air|transportasi air|dolphin|loader/ogv|shipper|stevedore|bolard|tali tambat|conveyor di jetty|marine'],
        'K5' => ['CPP, Fixed Plant & Stockpile', 'inti', 'crusher|hopper|hoper|belt|conveyor|stockpile|stocking|\brom\b|rehandling|coal processing|\bcpp\b|reclaimer|tunnel|wingwall|fixed plant|plant|timbang|sampling|fine coal|dilusi|kontaminan|bone coal|ddh|\bblc\b|coal crushing|coal feeding|crushed coal|screening|washing|hand picking|dust spray|bunker|silo|transfer'],
        'K6' => ['Geoteknik, Lereng & Pit Monitoring', 'inti', 'lereng|slope|geoteknik|tebing|radar|sloping|trimming|pit monitoring|stabilty|stability|underland|tambang bawah|terowongan|aspek geoteknik|overlayer|bench|highwall'],
        'K7' => ['Eksplorasi, Survei & Geologi', 'inti', 'eksplorasi|survey|survei|geologi|geolistrik|georadar|seismik|hidrologi|mapping|pemetaan|penandaan|titik|sampel|contoh uji|biota'],
        'K8' => ['Pengisian BBM & Fuel', 'inti', 'fuel|bahan bakar|\bbbc\b|solar|refuel|dispenser|p2h & pengisian|pengisian tangki|bahan bakar cair|oil trap'],
        'K9' => ['Pengelasan & Kerja Panas', 'inti', '\blas\b|pengelasan|gerinda|gouging|oxt|acetylene|acyteline|oksigen|panas|pemotongan|memotong|miller|welding|membengkokkan'],
        'K10' => ['Perawatan Unit, Workshop & Tyre', 'inti', 'workshop|maintenance|perawatan|perbaikan|pemeliharaan|servis|service|tyre|tire|ban |fabrikasi|machining|assembling|repair|pit service|mesin tempel|pencucian unit|hose|fitting|forklift|\bpap\b|inflator|pompa tambang'],
        'K11' => ['Konstruksi, Sipil & Bangunan', 'inti', 'beton|baja|kayu|pondasi|arsitektur|pengecoran|pengecatan|landscape|erection|struktur|earth work|tanah/|bangunan|gedung|gorong|perkerasan|chipseal|rambu|pekerjaan batu|drop structure|plumbing|perpipaan|jembatan|road construction|pembuatan.*jalan|akses jalan|pembangunan'],
        'K12' => ['Listrik & Elektrikal', 'inti', 'listrik|elektrikal|electrical|genset|panel|power|kelistrikan|transformator|\bsdp\b|cctv|instalasi'],
        'K13' => ['Jalan Tambang & Road Maintenance', 'inti', 'jalan|road|hrm|persimpangan|penyiraman|hauling road'],
        'K14' => ['Hauling & Pengoperasian Unit', 'inti', 'hauling|pengoperasian|operasi kendaraan|operasional|driving|kendaraan|unit|truck|\bdt\b|parkir|mobil|towing loader|transportasi|\bwt\b|\bft\b|\bicc\b|lv\b|angkutan|rental|a2b|pengangkutan|mobilisasi'],
        'K15' => ['Loading (Material, OB, Batubara)', 'inti', 'loading|pemuatan|muat|feeding|excavator|loader'],
        'K16' => ['Dumping & Penimbunan', 'inti', 'dumping|penimbunan|disposal|bund wall|soil|removal|pemberaian|spoil|counter weight|material lunak|lumpur|channel dump|dump'],
        'K17' => ['Dewatering, Air & Kolam', 'inti', 'dewatering|pengeringan|\bair\b|pump|pompa|wmp|ipal|settling|kolam|\bwtp\b|water|pit void|penyiraman|bekerja di dekat air|slurry|galon|sungai'],
        'K18' => ['Lingkungan, Limbah & Bahan Kimia', 'inti', 'limbah|b3|3r|sampah|kimia|reklamasi|revegetasi|reclamation|pencemaran|debu|keanekaragaman|biodiversity|lingkungan|spontaneous|tps|lahan'],
        'K19' => ['Ketinggian, Ruang Terbatas & Pengangkatan', 'inti', 'ketinggian|ruang terbatas|pengangkatan|lifting|crane|angkat|rigging|support'],
        'K20' => ['Pekerjaan Tanah, Grading & Lahan', 'inti', 'grading|galian|urugan|ripping|patok|pemangkasan|penanaman|pemupukan|rumput|shaping|superelevasi|pembobokan|grader|land clearing|hutan|semen|pasir'],
        'K21' => ['Tools & Peralatan Kerja', 'inti', 'tool|inflator|peralatan|perawatan alat|penambahan peralatan'],
        'K22' => ['Pengawasan, Inspeksi & Observasi', 'inti', 'pengawasan|inspeksi|inspektion|patroli|observasi|mining eyes|drone|\bsbs\b|alat bantu|pemantauan|monitoring|kontrol sequence|final check|pengecekan|check'],
        'K23' => ['Keadaan Darurat', 'inti', 'emergency|darurat|evakuasi|kecelakaan|tanggap|kebakaran|p3k'],
        'K24' => ['Shift Change & Operasional Umum', 'inti', 'shift change|kegiatan di malam|malam hari|hujan|house keeping|aktivitas lapangan|aktivitas prod|produksi|persiapan|pengaturan|aktifitas|kegiatan'],
        'K25' => ['Kantor, Administrasi, Gudang & Training', 'inti', 'kantor|office|gudang|warehous|storage|makanan|toilet|fasilitas|pos pengawas|camp|pelatihan|training|sosialisasi|komputer|administrasi|dokumen|ruang|penyimpanan|penumpukan|penataan|penjagaan|absensi|manual handling|handling|tangga|pembersihan|p2h e-logging|baterai|batteray|alat ukur|pengukuran'],
        'K0' => ['Lainnya (perlu dipetakan manual)', 'inti', ''],
    ],

    /*
    | Tema checklist (M15): teks butir checklist OAK dan teks OBS dicocokkan ke tema (regex kolom 1),
    | tema → baris HIRA lewat teks "bahaya sub " (regex kolom 2) = M16. Baris diidentifikasi bila kelompok
    | aktivitas checklist/OBS sama dengan kelompok baris, atau baris itu bahaya umum (M01 kolom H).
    | Format: id => [nama, regex teks checklist/OBS, regex teks bahaya HIRA].
    */
    'tema_checklist' => [
        1 => ['APD', 'apd|safety shoes|safety helmet|helm |rompi|kacamata|sarung tangan|seragam|masker|reflektif|pelindung', 'apd|alat pelindung|pelampung|kacamata'],
        2 => ['Fatigue', 'fatigue|sobriety|mess|jam kritis|roster|ftw|kantuk|tidur', 'fatigue|kelelahan|mengantuk|kantuk'],
        3 => ['Rambu/lalu lintas', 'rambu|traffic|lalu lintas|klakson|jarak aman|antri|waiting line|marka|speed|kecepatan', 'rambu|lalu lintas|overspeed|kecepatan|jarak aman|tabrak|antri'],
        4 => ['Pencahayaan', 'penerangan|pencahayaan|lux|tower lamp|senter|lampu', 'penerangan|gelap|pencahayaan|lampu'],
        5 => ['Jalan/berm', 'jalan|geometri|berm|tanjakan|turunan|penyiraman|road|grade|licin|amblas', 'jalan|berm|licin|tanjakan|turunan|amblas|debu jalan|berlumpur|curam'],
        6 => ['Longsor/retakan/dumping', 'retakan|patahan|disposal|dumping|longsor|tanggul|lereng|slope|highwall|crest|toe|bench', 'longsor|retak|amblas|dumping|disposal|runtuh|lereng|ambles|slope|material jatuh'],
        7 => ['Kelayakan unit', 'p2h|tyre|ban |wheelnut|rem |kelayakan|unit layak|alarm|inspeksi unit|komponen|hydraulic|hidrolik|oli ', 'tidak layak|kelayakan|ban |rem |p2h|peralatan tidak|unit rusak|kerusakan'],
        8 => ['Komunikasi', 'radio|komunikasi|channel', 'komunikasi|radio'],
        9 => ['Muatan', 'muatan|overload|vessel|payload', 'muatan|overload|beban berlebih'],
        10 => ['Prosedur/izin', 'jsa|izin|sop|prosedur|instruksi kerja|p5m|briefing|tbm|ik |loto|permit', 'prosedur|sop|izin|jsa|briefing|tidak sesuai standar|tidak mengikuti'],
        11 => ['Kompetensi', 'id card|sim |kompeten|pelatihan|lisensi|sertifikat|mine permit|izin operator', 'kompeten|sim |tidak berwenang|pelatihan|lisensi|izin operator|tidak memiliki'],
        12 => ['Tumpahan/B3/sampah', 'tumpahan|ceceran|b3|limbah|sampah|housekeeping|kebersihan|spill|oli|lingkungan', 'tumpah|ceceran|b3|limbah|sampah|oli|pencemar|housekeeping'],
        13 => ['Kebakaran/darurat', 'apar|kebakaran|tanggap darurat|p3k|eyewash|emergency|evakuasi|assembly', 'kebakaran|darurat|p3k|eyewash|apar|api'],
        14 => ['Listrik', 'listrik|kabel|isolasi|panel|grounding|stop kontak|genset', 'listrik|kabel|sengat|korsleting|genset'],
        15 => ['Ketinggian/lifting', 'ketinggian|harness|lifting|crane|sling|rigging|scaffolding|perancah|tangga|pijakan', 'ketinggian|jatuh|lifting|crane|sling|perancah|tangga|pijakan|tergelincir'],
        16 => ['Pekerjaan panas', 'las |welding|gerinda|panas|cutting|api |percikan', 'las |panas|gerinda|percikan|cutting|welding'],
        17 => ['Perkakas/guard', 'tools|perkakas|guard|hand tool|pelindung mesin', 'tools|perkakas|guard|hand tool|alat tidak'],
        18 => ['Air/jetty', 'pelampung|lifebuoy|sungai|jetty|dermaga|barge|tongkang|air ', 'tenggelam|air|pelampung|jetty|dermaga|sungai|tongkang|kapal'],
        19 => ['Pengawasan', 'pengawas|supervisi|pengawasan', 'pengawas|supervisi'],
        20 => ['Cuaca/satwa', 'petir|hujan|cuaca|ular|satwa|angin', 'petir|cuaca|hujan|ular|satwa|angin|biologi'],
        21 => ['Interaksi unit/orang', 'pejalan|interaksi|lv |manuver|spotter|blind|blindspot|pedestrian|kaki', 'interaksi|pejalan|manuver|blind|tertabrak|tabrak|pedestrian|kontak dengan unit'],
        22 => ['Ergonomi', 'ergonomi|postur|angkat manual|manual handling', 'ergonomi|postur|manual|beban terlalu berat|angkat'],
        23 => ['Peledakan/bor', 'blast|peledak|handak|drill|bor ', 'ledak|handak|bor|flying rock|batu terbang'],
        24 => ['Debu/bising/panas', 'debu|bising|kebisingan|heat|hidrasi|suhu', 'debu|bising|panas|dehidrasi|suhu|kebisingan'],
    ],
];
