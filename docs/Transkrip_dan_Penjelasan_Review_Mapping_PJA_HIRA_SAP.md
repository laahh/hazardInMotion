# Review Konsep Mapping PJA – Bisnis Proses – HIRA – SAP

**Sumber audio:** Rekaman WhatsApp, 11 November 2026, durasi ±38 menit
**Konteks:** Diskusi/review dashboard *Panduan Bahaya & Pelaporan HSE (Risk Matrix 5x5 Week 45 v31)* dan desain Figma, dengan penanya/reviewer memberi masukan ke pembuatnya (developer)
**Data pendukung:**
- `Risk_Matrix_5x5_Week45_2026_v31.html`: dashboard yang dibahas
- `Mapping_PJA_Proses_Lokasi_SAP.xlsx`: file mapping PJA → Bisnis Proses → Lokasi → Tema bahaya → Temuan SAP

> **Catatan tentang transkrip:** transkrip dibuat otomatis dengan speech-to-text (Whisper), lalu dirapikan. Rekaman diambil di ruangan yang ramai dan beberapa orang bicara bersamaan, jadi nama pembicara tidak dipisahkan dan sebagian kalimat ditandai *[tidak jelas]*. Istilah yang salah dengar sudah dikoreksi sesuai konteks HSE Berau Coal, misalnya "PGA" menjadi **PJA**, "call getting" menjadi **Coal Getting**, "obidias" menjadi **OBDS**, dan "SPS" menjadi **Layer SBS**.

---

## Bagian 1 — Penjelasan yang Mudah Dimengerti

### 1.1 Intinya dalam 5 kalimat

1. Dashboard ini adalah **panduan (guidance)** untuk orang lapangan: *"di area kerja saya, sesuai PJA saya, apa saja bahaya yang harus dicek dan bagaimana melaporkannya lewat SAP?"*
2. Datanya disusun dari **HIRA** (daftar aktivitas, bahaya, dan pengendaliannya). Aktivitas HIRA **dikelompokkan ke Bisnis Proses** (Coal Getting, Coal Hauling, Crushing, Barge Loading, dll.). Kalau tidak cocok ke mana pun, aktivitas itu masuk **Proses Pendukung**.
3. Bahaya di HIRA lalu **dicocokkan dengan temuan SAP** (Hazard/Inspeksi lewat ketidaksesuaian, Observasi OAK lewat aktivitas/sub-aktivitas). Dari situ terlihat bahaya mana yang **sudah dilaporkan dan terkontrol**, **dilaporkan tapi masih ada gap**, atau **belum pernah dilaporkan sama sekali (Open Risk)**.
4. Reviewer menilai konsepnya **sudah oke**, tetapi alurnya sebaiknya **dibalik: berangkat dari PJA dulu**, lalu PJA → lokasi/detail lokasi → bisnis proses → HIRA → SAP. Beberapa interaksi klik juga belum menampilkan detail.
5. Kesepakatan penting: bahaya yang **belum pernah ditemukan tidak wajib "dicari-cari"** (contoh: "orang mabuk"). Yang penting adalah **memastikan bahaya tersebut sudah punya pengendalian di HIRA**. Yang belum punya pengendalian itulah yang harus ditindaklanjuti.

### 1.2 Alur konsep (cara membaca sistemnya)

Alur yang **disarankan reviewer** (berangkat dari sudut pandang PJA):

```
PJA (Parent → Sub-PJA / mitra kerja)
   │
   ▼
Lokasi & Detail Lokasi  ──►  Klaster Lokasi  ──►  Bisnis Proses
   │                                               (Coal Getting, Crushing, dll.)
   ▼
HIRA: Aktivitas → Sub-aktivitas → Bahaya-Risiko → Pengendalian
   │                                        │
   │                                        ▼
   │                              Pengendalian dipetakan ke Layer SBS
   ▼
Temuan SAP (Hazard/Inspeksi, OAK)
   │
   ▼
Status: Terkontrol / Ada Gap / Tidak Teridentifikasi (Open Risk)
```

Urutan ini sama dengan yang dipakai di file Excel pendukung (sheet *Info*):
**Site → PJA → Bisnis proses → Lokasi → Detail lokasi → Tema bahaya (HIRA) → Temuan SAP**

### 1.3 Penjelasan per bagian

#### a) Dari HIRA ke Bisnis Proses
- Setiap baris HIRA berisi satu **aktivitas/sub-aktivitas** beserta: jenis (Rutin/Non-rutin), kondisi (Normal/Abnormal/Emergency), site, regulasi terkait, bahaya, konsekuensi, nilai risiko awal, pengendalian, dan nilai risiko sisa.
- **Contoh yang dibahas:** *Penyimpanan bahan kimia*. Bahayanya bahan kimia mudah menyulut dengan potensi kebakaran. Pengendaliannya: disimpan di wadah aslinya, inspeksi, pelatihan penanganan, APAR, tanggap darurat. Hasilnya risiko turun dari *Kemungkinan Kecil × Bencana (Medium)* ke kategori yang lebih rendah.
- Aktivitas ini kemudian **diklasifikasikan ke bisnis proses** oleh AI. Aktivitas yang sifatnya umum (misalnya pengoperasian unit yang tidak spesifik LV/DT/HD, air/pipa, lingkungan kerja, masyarakat) masuk ke **Proses Pendukung**.
- **Masukan reviewer:** beberapa pengelompokan masih "campur". Contohnya *Kantor, Administrasi & Aktivitas Umum*, atau *Maintenance & Workshop* yang tercampur dengan fuel station/warehouse. **Fuel dan warehouse/penyimpanan sebaiknya dipisah**, karena bukan bagian dari maintenance. Sebaiknya kategorinya lebih spesifik, misalnya "Storage/Penyimpanan" sendiri.
- Area **Pit** saat ini otomatis masuk ke dua proses (Coal Getting **dan** OB Removal), karena di pit bisa ada front coal dan front OB. Idealnya dipisah, tapi kalau tidak dipisah juga tidak masalah.

#### b) Pengendalian dipetakan ke Layer SBS
- Pengendalian di HIRA (administratif, engineering, APD, eliminasi, substitusi) dipetakan ke **Layer SBS**.
- Contoh: *menyimpan bahan kimia di wadah* dan *inspeksi area kerja* masuk **Layer 3** (pelaksanaan pekerjaan sesuai SOP). *Pelatihan penanganan* masuk **1.14 Training & Kompetensi**. *P5M sebelum bekerja* juga masuk pengendalian Layer 3.
- Ada kelompok *Contact Defense* (istilah sesuai yang terdengar di rekaman) yang berisi **tanggul, APD, emergency response, safety device**. Disebutkan bahwa daftar ini **sudah fix**.
- **Masalah UI:** saat klik *Coal Getting → Contact Defense*, tampilan malah pindah ke menu umum, bukan menampilkan Contact Defense **khusus Coal Getting**. Bagian itu juga tidak bisa di-expand. **Harapannya:** klik di proses tertentu menampilkan pengendalian HIRA yang relevan untuk proses itu saja.

#### c) PJA → Lokasi → Bisnis Proses
- Setiap **PJA terikat ke lokasi dan detail lokasi**. Ada **PJA Parent** dan **PJA Sub** (anak PJA, biasanya mitra kerja). Data PJA diambil dari **OBDS**.
- Lokasi dikelompokkan ke **klaster lokasi**, lalu otomatis ke bisnis proses. Contohnya:
  - CPP Binungan → klaster CPP → **Crushing**
  - Workshop, gudang, fuel station, warehouse → **Maintenance, Workshop & Pekerjaan Khusus**
  - Hauling road → **Coal Hauling**
  - Jetty/dermaga → **Barge Loading**
- Satu PJA bisa menyentuh beberapa proses. Contohnya *PJA CPP & Hauling* yang kena ke Crushing **dan** Coal Hauling.
- **Masukan reviewer:** mapping lokasi → aktivitas saat ini masih berbasis AI/kata kunci. Sebaiknya diminta lebih spesifik, dan alur tampilannya sebaiknya mulai dari PJA.

#### d) Dari HIRA ke SAP (inti evaluasinya)
- Bahaya-risiko HIRA dicocokkan ke SAP:
  - **Hazard/Inspeksi** dicocokkan lewat *ketidaksesuaian* dan *sub-ketidaksesuaian*
  - **Observasi OAK** dicocokkan lewat *aktivitas* dan *sub-aktivitas* (karena OAK punya checklist)
- Contoh: bahaya *bahan kimia mudah menyulut* cocok dengan sub-ketidaksesuaian terkait bahan kimia, atau dengan checklist OAK *penanganan bahan kimia*.
- Hasilnya dibagi menjadi:

| Status | Artinya |
|---|---|
| **Teridentifikasi & Terkontrol** | Sudah ada laporan SAP dan tidak ada gap |
| **Teridentifikasi – Ada Gap** | Ada laporan, tapi (1) masih open/belum closed, (2) **berulang**, yaitu temuan sama di lokasi sama saat ditarik ke belakang, atau (3) ada OAK stop pekerjaan |
| **Tidak Teridentifikasi (Open Risk)** | Sama sekali belum ada laporan SAP yang cocok |

- **Masukan reviewer:** saat baris diklik, *tidak terjadi apa-apa*. Seharusnya muncul **detail temuan SAP-nya** (apa, status, dll.). Menu lain sebaiknya disederhanakan. Fokus ke menu **Proses Bisnis & Pengendalian** dan **Layer SBS**.

#### e) Diskusi penting: apakah semua bahaya harus "ditemukan"?
- Dari ±1.700 bahaya-risiko, sekitar dua pertiga sudah teridentifikasi dan sepertiga belum.
- Pertanyaannya: apakah angka ini jadi **target** (semua harus ditemukan) atau hanya **informasi (FYI)**?
- Kekhawatirannya: kalau dijadikan target, orang malah **mengejar bahaya yang aneh-aneh** hanya supaya terisi. Contoh yang disebut: "ayo kita cari orang mabuk".
- Ada perbedaan pendapat sebelumnya (disebut nama Pak Dian, Indra, dan Pak Jimmi).
- **Kesepakatan akhir:**
  > Bahaya yang belum ditemukan **tidak harus dipaksa ditemukan**. Tetapi untuk setiap bahaya itu, kita harus **tahu pengendaliannya di HIRA**. Jadi langkahnya: dari daftar yang belum teridentifikasi, cek **apakah sudah ada pengendaliannya**. Yang **belum punya pengendalian**, itulah yang harus dikendalikan.

### 1.4 Daftar tindak lanjut (Action Items)

| # | Tindak lanjut | Asal masukan |
|---|---|---|
| 1 | Ubah alur tampilan supaya **berangkat dari PJA** (PJA → lokasi/detail lokasi → bisnis proses → HIRA → SAP) | Reviewer, menit ~13–16 |
| 2 | Pisahkan klaster yang tercampur: **fuel & warehouse/storage** dipisah dari Maintenance; rapikan "Aktivitas Umum" | Menit ~19–24 |
| 3 | Pertimbangkan pemisahan **Pit → Coal Getting vs OB Removal** (opsional) | Menit ~20–21 |
| 4 | Perbaiki klik **Bisnis Proses → Contact Defense** supaya menampilkan pengendalian khusus proses itu dan bisa di-expand | Menit ~9–10 |
| 5 | Klik baris temuan harus memunculkan **detail SAP** | Menit ~27 |
| 6 | Sederhanakan menu: fokus ke **Proses Bisnis & Pengendalian** dan **Layer SBS** | Menit ~29 |
| 7 | Tambahkan analisis: bahaya **Tidak Teridentifikasi** dicek **apakah sudah ada pengendalian di HIRA**. Tampilkan yang belum punya pengendalian | Kesepakatan, menit ~36–37 |
| 8 | Posisikan angka % teridentifikasi sebagai **informasi (FYI)**, bukan target | Menit ~34–35 |
| 9 | Kirim semua bahan ke reviewer: **HTML, Figma, dan Excel mapping** | Menit ~35 |
| 10 | (Opsional) Tambahkan data **mitra kerja / PJA Sub** di bawah PJA Parent | Menit ~15 |

### 1.5 Angka pendukung dari data (Week 45: 1–7 Nov 2026)

Dari **dashboard HTML (All Site)**:

| Indikator | Jumlah | % dari total |
|---|---:|---:|
| Total bahaya-risiko unik (baseline BeHIRA) | 1.726 | 100% |
| Teridentifikasi & Terkontrol | 621 | 36,0% |
| Teridentifikasi – Ada Gap | 462 | 26,8% |
| **Tidak Teridentifikasi (Open Risk)** | **643** | **37,3%** |
| Risiko Laten (pernah insiden, kini tidak ada laporan SAP) | 17 | 1,0% |
| Risiko turun kategori (berhasil dikontrol) | 576 | 33,4% |

> Ini sesuai dengan yang dibahas di rekaman: sekitar 63% sudah teridentifikasi, dan sekitar 37% belum. Di rekaman disebut "66% vs 33%" sebagai angka kasar. **17 Risiko Laten** layak jadi prioritas, karena bahaya itu pernah menyebabkan insiden tetapi sekarang tidak dipantau. Kelompok ini tidak termasuk "boleh FYI".

Dari **file Excel mapping**:
- **115 PJA** di 9 site/area (BMO 1, BMO 2, BMO 3, LMO, SMO, GMO, HO, Marine, Eksplorasi), dipetakan ke **16 bisnis proses**.
- Status bukti SAP per kombinasi PJA × lokasi × tema: **Terbuka 6.588**, **Tidak terpantau 2.857**, **Terverifikasi 1.589**, **Berulang 1.183**.
- Klaster lokasi terbanyak: *Area pit & front kerja* (3.788 baris), lalu *Workshop, gudang & fuel* (1.606). Klaster kedua ini adalah yang disarankan reviewer untuk **dipecah**.
- Peringatan dari file: angka **jangan dijumlah lintas PJA/proses** (lokasi bisa muncul di beberapa PJA). Risiko awal/residual HIRA **belum terikat ke lokasi**. **Risiko efektif hanya indikatif**, bukan nilai resmi BeHIRA.

### 1.6 Kamus singkat

| Istilah | Arti |
|---|---|
| **PJA** | Penanggung Jawab Area. Ada PJA Parent dan PJA Sub (mitra kerja) |
| **HIRA** | Hazard Identification & Risk Assessment: daftar aktivitas, bahaya, risiko, pengendalian |
| **Bisnis Proses** | Kelompok proses tambang: Coal Getting, OB Removal, Coal Hauling, Crushing, Barge Loading, Transshipment, dll. |
| **Proses Pendukung** | Aktivitas HIRA yang tidak spesifik ke satu bisnis proses |
| **SAP** | Sistem pelaporan keselamatan: Hazard, Inspeksi, Observasi OAK |
| **Ketidaksesuaian / Sub-ketidaksesuaian** | Kategori temuan di laporan Hazard/Inspeksi |
| **OAK** | Observasi Aktivitas Kerja (berbasis checklist aktivitas) |
| **Layer SBS** | Lapisan pengendalian/pertahanan, dipakai untuk memetakan jenis kontrol |
| **P5M** | Pembicaraan 5 Menit sebelum bekerja |
| **Open Risk** | Bahaya di HIRA yang belum pernah ada laporan SAP-nya |
| **Gap** | Sudah dilaporkan tapi masih open, berulang, atau ada OAK stop kerja |
| **Risiko Laten** | Pernah ada insiden terkait, tapi sekarang tidak ada laporan SAP (blind spot) |
| **OBDS** | One Big Database Safety, sumber data |

---

## Bagian 2 — Transkrip (dirapikan)

> Kalimat dirapikan seperlunya tanpa mengubah makna. Obrolan di luar topik diringkas sebagai *[obrolan di luar topik]*. Timestamp = posisi di rekaman (menit:detik).

**[00:00–00:43]** *[Persiapan rekaman, obrolan di luar topik]* … Jadi ini jam 10.31, harusnya 15 menit. Ini yang v89 … *[tidak jelas]*

**[00:46]** Objektifnya apa?

**[00:49]** Objektifnya adalah melihat ini sebagai *guidance* sebenarnya. Jadi orang-orang ini harus ngecek apa sih di area kerjanya, sesuai PJA-nya.

**[01:05]** Yang dilihat di sini nanti, yang aku kirim tadi, menu *Proses dan Pengendalian*. Dari bisnis proses itu (misalnya transshipment), dan itu dari HIRA dikonversi. HIRA kan punya aktivitas. Aktivitas di HIRA itu dikonversi menjadi bisnis proses ini. Kalau di luar itu, akan masuk ke **proses pendukung**.

**[01:33]** Ke IKA ya? — Bukan. Ini nggak ngomongin IKA, ini ngomongin HIRA. Aktivitas HIRA dikonversi ke situ. Yang di luar bisnis proses itu yang terkait air atau pipa, lingkungan kerja, masyarakat…

**[02:02]** …atau yang kayak general, pengoperasian unit, karena nggak spesifik di masing-masing proses. Misalnya ada pengoperasian unit di Coal Hauling, harusnya masuk ke Coal Hauling. Tapi pengoperasian unit yang di sini nggak spesifik ngomongin LV, DT, atau HD, jadi dipisah.

**[02:30]** Nanti aku kasih mapping-nya ya, Excel, buat di-inject di tempatmu. — Oke. Kalau HTML-nya ada harusnya. — Oh, bisa juga ya? — Iya, pakai yang aku kirim tadi aja. Ambil di menu *Proses Pengendalian*. Terus sebenarnya di HIRA itu kan punya pengendalian, kalau kamu tahu. — Belum… Oh iya, kan kita investigasi, orang udah training ya.

**[03:13]** Terus ini terkait apa? Ini kan satu baris itu kayak satu aktivitas, sub-aktivitas. Sub-aktivitas ini punya bahaya apa aja. — Nilai risiko? — Iya, itu juga betul, tapi dikonversi dulu. Ini pekerjaan terkait **penyimpanan bahan kimia**.

**[03:43]** *[tidak jelas]* … Mungkin digabung jadi satu aja. Oke, terus? Penyimpanan bahan kimia ini, N atau R? Tebak.

**[03:56]** Rutin dan non-rutin. Ini N, A, E? — Kayaknya Normal, Abnormal, sama Emergency.

**[04:12]** Rutin/non-rutin itu seberapa sering terjadi. Normal/abnormal itu aktivitas ini terjadi saat kondisi normal atau tidak.

**[04:27]** Terus site-nya berlaku di mana, regulasi terkait.

**[04:33]** Yang dilihat sebenarnya bahayanya: bahan kimia yang disimpan mudah menyulut. Potensinya terbakar.

**[04:51]** Keparahan, konsekuensi, sama nilai risiko.

**[05:00]** Terus tinggal melihat untuk bahaya bahan kimia mudah menyulut tadi, pengendalian yang dilakukan ada apa saja. Berarti menyimpan di wadah aslinya, inspeksi, latihan, APAR, emergency, dan lain-lain. Sehingga dari nilai risiko awal tadi, dengan keparahan dan konsekuensinya, ini sudah dikendalikan dengan ini semua, jadi turun.

**[05:45]** Ada standarnya … *[obrolan singkat]* … Oh ini kekerapan.

**[06:04]** Tadi kan Bencana ke Sedang. Jadi sebelumnya masuknya…

**[06:38]** Sebelumnya: Kemungkinan Kecil sama Bencana, Medium, berada di sini, diturunkan. Oke, next. Itu baru satu ya? — Iya. Nah dari sini kamu konversi maksudnya? — Betul. Jadi ke AI-nya, HTML-nya itu, aktivitas ini dikonversi masuk ke bisnis proses yang mana. — Oh, klasifikasi dan justifikasi. — Betul.

**[07:08]** Kalau nggak ada, baru masuk ke pendukung. — Kenapa cuma segini? 1, 2, 3, 4, 5, 6… — Ada berapa ratus? *[tidak jelas]*

**[07:25]** Kayak ini tiba-tiba ada *Kantor, Administrasi & Aktivitas Umum*. Aku lihat aktivitas umumnya apa sih? Ternyata kegiatan umum. Harusnya terpisah juga. Maintenance dan workshop juga campur dengan perbaikan unit.

**[07:43]** Oh, klasifikasinya juga belum ya? Kan dia suka-suka dia gitu. Mungkin yang di luar ini dia pisah sendiri, cuma grouping-nya. — Tapi yang di sini udah bener ya? Dari eksplorasi sampai transshipment? — Insya Allah. Tadi aku cek sekilas, kayaknya terpisah.

**[08:08]** Soalnya satu HIRA itu punya beberapa departemen. Sedangkan di mining, departemennya BMO 1, BMO 2, itu beda departemen. Kayak gini nih, Lati, Gurimbang.

**[08:23]** Untuk yang Coal Getting, di dalam Coal Getting ada coal loading, coal getting, dan lain-lain. Menurutku mapping-nya oke. Terus di dalam masing-masing itu tadi ada risiko awal, sebelum pengendalian, sama sesudah.

**[08:43]** Terus pengendalian menurut hierarki ada apa aja.

**[08:52]** Ada yang administratif, engineering, APD, sesuai yang ada di sini. Terus dari sini aku mapping pengendalian menurut layer. — SBS? — Betul, **layer SBS**. Jadi dikonversi, administratif ini masuk ke yang mana. Tapi pas aku klik ya…

**[09:18]** …lemahnya di sini. Aku kira akan lari ke punyanya Coal Getting, ternyata pas aku klik *Contact Defense*, pindah menu, tapi nggak spesifik Coal Getting yang diklik. Jadi langsung ke general. Makanya tadi aneh, bingung.

**[09:46]** — Harusnya ekspektasi kamu apa? — Harusnya pas aku klik Coal Getting → Contact Defense, dia munculin Contact Defense Coal Getting. — Contohnya kayak gimana? — Pertanyaan bagus. Ini juga nggak bisa di-expand. Isinya: HIRA yang berhubungan dengan Contact Defense di Coal Getting.

**[10:30]** Maka nanti yang berhubungan dengan Contact Defense yang ada di HIRA. Contohnya, Contact Defense itu harusnya APD, terus safety device…

**[11:06]** Ini mapping-nya apa? SBS kan ada 4? Ini 5 ya? — Jadi sebenarnya Contact Defense itu ada 5: terkait tanggul, APD, emergency response, safety device. — Itu udah fix? — Udah fix. — Oke. Misal contoh, kita ambil pengendalian ini: *menyimpan bahan kimia di dalam wadah*, berarti harusnya masuk ke **Layer 3**, karena…

**[11:35]** …karena terkait pelaksanaan pekerjaan sesuai SOP. Mungkin SOP mengatur penyimpanannya. Terus *inspeksi area kerja*, berarti harusnya masuk ke Layer 3 juga. *[tidak jelas, menyebut versi lama vs baru]* Terus *pelatihan penanganan*, berarti masuk ke **1.14 Training dan Kompetensi**.

**[12:04]** MK3, Teknik Lingkungan… Itu **P5M**. Sebelum mau kerja P5M, masuk ke pengendalian nomor 3.

**[12:22]** Sebenarnya aku lihat harusnya udah kayak gitu. Tapi pas dimunculin, ini ke mana? Aku mau lihat Layer 5… — Ini udah ada di sini, ada tutorialnya. — Ini oke sih, sebenarnya oke banget, aku suka.

**[12:51]** Terus ini HIRA departemen apa aja. — Oh itu basic, bawaan. Bukan cuma label, ternyata punya departemen yang terkait ini.

**[13:09]** Bahaya-risikonya ada apa aja berdasarkan HIRA. Oh ternyata di aktivitas Coal Getting-nya ada bahaya *operator terlalu percaya diri*, *operator dalam melakukan P2H*, dan lain-lain.

**[13:24]** Yang tadi sampai ke PJA, sampai lokasi dan detail lokasi, aktivitasnya apa? Belum ya? Sebenarnya harusnya **berangkat dari sini dulu, dari PJA dulu**. Masing-masing PJA kan terikat ke lokasi dan detail lokasi. Jadi misalkan Coal Getting itu ada di lokasi mana, HIRA apa aja, PJA apa aja.

**[13:54]** Buka PJA lokasi dan PJA, setelah ke situ: Coal Getting di site Binungan ada siapa aja orangnya. Terus Safety pasti ada di semua HIRA atau lokasi? Kayaknya Inspektor masuk di semua.

**[14:19]** Terus klaster lokasinya di mana aja. Nah sebenarnya **harusnya berangkat dari sini dulu, Fa**. Jadi biar tahu PJA ini punya…

**[14:29]** …kena di bisnis proses apa aja.

**[14:38–15:00]** *[obrolan di luar topik]*

**[15:04]** Coal Hauling itu ada yang bisa digabung dengan Coal Getting dan Coal Hauling. Orang-orangnya ini *[menyebut nama]*. — Oh ada nama orangnya. — Iya. Harusnya ada **mitra** juga. Ini kan **PJA Parent**. PJA Parent punya **PJA Sub**. PJA Sub itu anak PJA, yang sebenarnya mitra kerja. Biasanya turunan dari sini.

**[15:37]** Ada di database? — Ada. Aku pakai database yang ada di **OBDS**. Aku ambilnya di OBDS.

**[15:54]** Oke. Di bawahnya punya PJA mitra kerja. *[tidak jelas]* sama infra yang masuk ke Transport dan Facility. Oke, next. Itu harusnya berangkat dari sini dulu. PJA ini masuk ke flow proses yang mana? — Betul, ke bisnis proses yang mana. Berarti kita **gambarkan flow proses bisnisnya ada apa aja dulu**, terus di dalamnya ada PJA…

**[16:27]** …lokasinya apa aja, orang-orangnya siapa aja. Cuma nanti kamu bikin dengan **POV PJA**. — Terus masuk ke HIRA-nya di bagian mana, Mas? — *[tidak jelas, membuka Figma]*

**[16:51–17:37]** *[obrolan di luar topik, membuka Figma]*

**[17:42]** Berdasarkan aktivitasnya itu kan berangkat dari HIRA. Yang dari PJA sebenarnya dibalik aja. Terus dari sini ke sini pakai apa?

**[18:03]** PJA itu kan punya lokasi-lokasi. Lokasi itu punya mapping aktivitas ke mana? Pakai apa?

**[18:12]** — Aku belum pernah lihat soalnya. — Pertanyaan bagus. Tadi ada Excel mapping-an, bentar aku lihat.

**[18:29]** Ini dari AI sebenarnya, Pak. Untuk lokasi ke aktivitas memang nggak ada (datanya), pendekatan AI aja.

**[18:42]** Pakai detail lokasi, terus langsung ke bisnis proses. Dia mengklaster lokasi. Misalkan ada lokasi **CPP Binungan**, langsung dikonversi ke klaster lokasi CPP, dan CPP otomatis ke bisnis proses **Crushing**.

**[19:08]** Maintenance, fuel station masuk ke workshop, gudang, dan fuel → Maintenance, Workshop & Pekerjaan Khusus. Hauling road langsung otomatis lari ke **Coal Hauling**. Jetty/dermaga masuk ke **Barge Loading**. Workshop dan warehouse termasuk Maintenance Workshop. Nah ini nanti kita bagi-bagi nggak?

**[19:39]** Misalkan maintenance sendiri. — Bisa sih, cuma kayaknya AI-nya langsung gabungin jadi satu. Mau dipecah atau dipecah aja nggak apa sih, Pak? Kayaknya ini **terlalu memaksakan**. Fuel itu sebenarnya bukan bagian dari ini, dia masuk di pekerjaan khusus.

**[20:05]** Jadi harusnya spesifik aja. — Oke, coba nanti minta AI-mu mapping-nya lebih spesifik.

**[20:21]** *[obrolan singkat soal nama AI "Jarvis"]*

**[20:28]** Masih langsung, **pit** masuk ke Coal Getting atau OB Removal. Karena kadang statusnya front OB atau front coal. Kalau front coal langsung masuk ke sini. — Oh itu masih dua ya? — Masih dua. Anggapannya di pit itu pasti ada coal dan ada OB.

**[20:53]** Harusnya mungkin pisah. Tapi kalau nggak pisah, nggak apa-apa juga. Nanti akan ada dua.

**[21:06]** Kadang pasti di pit itu ada PJA Coal Getting sama Coal Hauling. Bisa gabung ya.

**[21:25]** Coal Getting dan Hauling ada di Hauling Road sama di Pit. Nanti namanya PJA Pit.

**[21:43]** Nanti ada PJA hauling.

**[21:48]** Nih, **PJA CPP dan Hauling**, dia pasti kena ke bisnis proses Crushing dan Hauling Road. Oke. Terus lanjut ke Figma ya. Di sini aktivitasnya pendekatannya pakai detail lokasi.

**[22:20]** Itu kan udah punya PJA sama lokasi, detail lokasi. Pendekatannya ke lokasi/detail lokasi itu sebenarnya di sini juga nggak di-state banget, ini masuk ke lokasi/detail lokasi apa.

**[22:43]** Bilang bahan kimianya di mana sih?

**[23:01]** Karena di sini nggak ada lokasi/detail lokasi, ini aktivitas, aktivitasnya dikonversi ke sini. Jadi kalau dari HIRA, kayaknya dia bikin…

**[23:15]** …PJA → proses. Langsung dia bikin aja: ini masuk ke klaster apa? Contoh, tadi PC ini, **penyimpanan bahan kimia → warehouse**.

**[23:51]** Langsung otomatis. Warehouse kan tempat penyimpanan, langsung konversi ke sini. Jadi pasti yang dibuat ini langsung ke warehouse menurut mapping-an itu. — Harusnya punya sendiri. Warehouse sendiri. — Bener, harusnya sendiri. Apa namanya?

**[24:19]** Mungkin kayak **penyimpanan atau storage**. Oke. Terus habis itu? — Habis itu nanti kita kaitkan sama **SAP-nya**. Di sini belum ya? Yang di sini sebenarnya ada tapi nggak jelas.

**[24:45]** Temuan dari orang-orang terkait itu gimana? — Eh bukan.

**[24:50]** Dari PJA ada lokasi/detail lokasi, kemudian ke HIRA.

**[24:55]** Nah di HIRA itu temuannya apa? Kurang lebih gitu. Eh bukan, jadi ada mapping-an lagi.

**[25:05–25:52]** *[obrolan di luar topik]*

**[25:52]** Jadi sebenarnya aku mapping lagi kan…

**[25:56]** Mapping-nya dua kali ya? — Ya.

**[26:02]** (Pertama) **HIRA vs PJA dengan pendekatan lokasi/detail lokasi**. Terus HIRA punya bahaya-risiko, nah ini dikonversi ke **SAP**. Dari inspeksi/hazard pakai **ketidaksesuaian dan sub-ketidaksesuaian**. Terus observasi **OAK pakai aktivitas dan sub-aktivitas**, karena OAK punya checklist. Ini dimasukkan ke bahaya-risiko apa aja yang terkait.

**[26:31]** Contohnya: bahaya-risiko bahan kimia. Nanti di ketidaksesuaian masuknya ke bahaya kimia. Sub-ketidaksesuaiannya *bahan kimia yang mudah menyulut*, misalkan, yang akan dilaporkan. Terus mungkin ada OAK-nya…

**[26:59]** …misalkan aktivitas *penanganan bahan kimia*. Dalam checklist-nya ada terkait pengecekan penyimpanan bahan kimia. Dia akan konversi ini dengan pendekatan bahaya-risiko HIRA tadi. — Oke sih konsepnya.

**[27:24]** Nanti di-klik… — Pertanyaan bagus, pasti klik **tidak terjadi apa-apa**.

**[27:31]** Harusnya muncul **detail SAP-nya**. Terkait dengan apa.

**[27:46–28:02]** *[tidak jelas]*

**[28:07]** Di **Crushing** ini ada total **53 kontrol**. Kayaknya dia langsung membaca HIRA yang paling banyak.

**[28:15]** Satu aktivitas di HIRA ada beberapa baris, beberapa pengendalian. Langsung diambil jadi kontrol-kontrolnya.

**[28:32]** Oh, kontrolnya: APD, eliminasi, substitusi, gitu-gitu. Sebenarnya ada bahaya-risiko, ada pengendaliannya/kontrolnya. — Oke, nanti coba dibalik.

**[28:48]** Tapi okelah, secara general overall.

**[28:52]** 53 pengendalian, terus risikonya ada di sini. Ini FYI aja, perbandingan sebelum dan sesudah sesuai HIRA. Harus kita lihat mapping-an dengan SAP. Tapi ini kayaknya masih aneh: teridentifikasi, terus terkendali, ada gap, sama tidak teridentifikasi. Detailnya…

**[29:19]** …nggak bisa (dibuka) di sini.

**[29:32]** Aku pusing sendiri lihatnya. **Menu-menu lain nggak usah kamu lihat. Ambil menu Proses Bisnis & Pengendalian sama Layer SBS.** — Terus ke Layer SBS-nya di mana? Dari layar PJA ke SBS nyambungnya ke mana? — Kayaknya di Figma belum ada.

**[29:59]** SBS sebenarnya dari sini: pengendalian ini masuk ke apa aja, belum ada. Tapi ini label aja, mapping-nya ambil dari **mapping HIRA**, yang pertama. Coba kasih tahu aja ada apa aja. Oke, terus?

**[30:25]** SAP-nya. Dilaporin nggak? Bahaya-bahaya ini udah diidentifikasi apa enggak? Orang-orang udah ngecek terkait bahan kimia mudah menyulut apa enggak? Kalau ada identifikasi, nanti dibagi dua: **terkendali dan tidak terkendali**.

**[30:51]** Maaf ya kebanyakan, karena baru pertama kali bikin. — Oh ini yang bisa kamu tampilkan. Sepakat.

**[31:04]** Aku bikin grouping-nya itu…

**[31:15]** Aku bikin gap itu: **Open Risk** itu kalau dia **tidak teridentifikasi sama sekali**. Maksudnya dari mapping-an HIRA tadi nggak ada temuan yang kena. Itu kan udah di-mapping: ini punya SAP, ini punya observasi OAK, punya aktivitas ini. Misalkan tadi All Site **1.726**, itu ada **±640**…

**[31:43]** …yang tidak teridentifikasi. Sisanya udah dilaporin. — Bentar, yang nggak terkendali itu dari mananya?

**[31:59]** Nggak terkendali dari parameter apa?

**[32:08]** Misal contoh: aktivitas ini di site mana… material OB, bahaya-risiko terkait **interaksi antar unit**. Ini aku mapping pakai **sub-ketidaksesuaian**. Ada nggak laporannya? Ternyata ada, tapi **berulang**.

**[32:34]** Ditemukan 6 hari sebelumnya, atau hari ini.

**[32:41]** Nah aku bikin mapping-an terkendali…

**[32:46]** Teridentifikasi itu gabungan: ±1.000-an teridentifikasi semua, Pak. Dari yang teridentifikasi ada yang **terkontrol** atau ada **gap**. Terkontrol berarti dia nggak ada gap.

**[33:11]** Yang kedua, ditemukan tapi **masih open** hazard-nya. Yang ketiga, **berulang**. Berulang berarti ada catatan yang sama, ditarik ke belakang, di lokasi yang sama.

**[33:27]** Ada di sini, aku kirim juga.

**[33:31]** Udah, habis itu? Sebenarnya di sini oke sih.

**[33:41]** Risiko akhirnya apa di sini? Status SAP-nya apa? Oh, belum ditemukan.

**[33:49]** Nanti ambil dari sini aja. Habis itu harusnya dia kasih tahu yang open-open ini apa aja, misal semua status.

**[34:11]** Tapi yang belum dibahas adalah…

**[34:15]** …bagaimana ini berjalan.

**[34:20]** Apakah harus setiap…

**[34:24]** …hari? 1.700 ini setiap hari dicek, belum terkendali atau terkendali. Tapi aku tadi mikir, **apakah ini akan jadi target atau FYI aja**. Menurutku **FYI aja**, karena nggak mungkin juga ngecek semua. Jadi orang-orang fokus ke yang belum ditemukan.

**[34:50]** Di sini ada contoh bahaya angin kencang. Ada **orang mabuk**, bahaya orang mabuk. "Ayo kita cari orang mabuk." Itu makanya nanti orang-orang malah **ngejar-ngejar yang aneh**, karena belum pernah ditemukan. Apakah harus dipaksakan ada?

**[35:18]** Iya sih. Bener, sebenarnya ada bahaya orang mabuk, dan memang harus ada di HIRA. Itu yang agak susah di-translate. Tadi Pak Dian waktu online bilang, apakah harus dipenuhi semua? Aku bilang, enggak, ini sebagai informasi. Tapi Indra kayak nggak setuju.

**[35:43]** Terus Pak Jimmi juga: "Wah guys, kok pemikirannya kayak gini?" Dia bilang, berarti masih ada 33% yang… karena 66% ditemukan, 33%-nya enggak, yang tidak teridentifikasi sama sekali. Kenapa harus dicari? Bahaya juga ya.

**[36:06]** Maksudnya, dari 66 itu udah ditemukan, 33 belum. Barangkali 33 itu memang nggak harus ditemukan, yang kayak contoh tadi. Sebenarnya memang bahaya ada orang mabuk, tapi preventifnya kan sebenarnya udah ada. **Kamu bisa kasih tahu juga pengendalian yang ada, dari HIRA tadi.**

**[36:35]** Boleh, Pak, nanti coba aku gambar. — **Kirim semua, Mas, termasuk Figma-nya, sama mapping-annya.** — Oh iya, semuanya bisa. Misalkan tadi, sepakat ya, orang mabuk itu nggak apa-apa, memang nggak harus dilaporkan. **Tapi kita untuk mengidentifikasi orang mabuk punya pengendalian ini, Pak.**

**[37:01]** Ya, nggak harus ditemukan, tapi kita tahu pengendaliannya apa. Berarti kita harus **mapping yang belum terkendali: sebenarnya udah ada pengendaliannya belum?** Kan udah di HIRA semua, tinggal di-connect-kan. Yang belum ditemukan ini ada pengendaliannya nggak sih. Terus dicari, dari yang belum terkendali itu **mana yang belum ada pengendaliannya**. — Betul, **itu yang harus dikendalikan**.

**[37:26]** Betul, sepakat.

**[37:35–38:03]** *[penutup, obrolan santai soal developer/evaluator dan cara memakai AI untuk coding: "aku harus paham dulu baru bisa menjelaskan ke AI"]*
