# Kerangka Evaluasi Keselamatan Operasi: Pra — Saat — Pasca Operasi

> Status: **Sudah diimplementasikan** (lihat tabel status di bawah). Ditulis dari sudut pandang evaluator safety, berdasarkan data yang sudah tervalidasi nyata dari `hse_automation`.

## Status Implementasi (update terakhir)

| Bagian | Status | Keterangan |
|---|---|---|
| Fase 1 — Watchlist, Level Risiko, Roster (hari-ke/shift), Panel Kesiapan Menyeluruh | ✅ Selesai | `/pra-operasi` |
| Fase 1 — Drawer detail: roster, evaluasi kemarin, heatmap riwayat evaluasi | ✅ Selesai | Klik baris di watchlist |
| Fase 2 — Matriks Fit-to-Continue, live monitoring, red flag proses | ✅ Selesai | `/pra-operasi/saat-operasi` |
| Fase 2 — Feed Alert Live | ✅ Selesai | Panel di halaman Saat Operasi |
| Fase 2 — Tandai tindak lanjut supervisor | ✅ Kode selesai, **menunggu migration dijalankan** | Tabel `pra_operasi_tindak_lanjut` |
| Fase 3 — Evaluasi harian, job terjadwal, dashboard | ✅ Selesai | `/pra-operasi/evaluasi-harian` |
| Fase 3 — Export CSV | ✅ Selesai | Tombol "Download CSV" (bukan Excel/PDF — belum ada package terpasang) |
| Notifikasi WA/Email otomatis saat status "Tarik dari Unit" | ⏸️ **Sengaja belum dibangun** | Berisiko mengirim pesan nyata ke orang sungguhan tanpa konfirmasi channel/format — menunggu keputusan eksplisit Anda |
| Definisi presisi "sedang beroperasi" (batas waktu shift) | ⏸️ Belum, masih pakai `checked_out_at IS NULL` sederhana | Penyempurnaan lanjutan |

---

## 1. Latar Belakang & Cara Berpikir

Selama ini modul Pra Operasi yang sudah dibangun ( `/pra-operasi` ) pada dasarnya adalah **satu snapshot pagi**: siapa checkin, siapa sudah Fatigue Test, siapa sudah PVT, dan skor risiko komposit dari gabungan sinyal itu.

Yang diminta sekarang adalah membingkai ulang ini menjadi **siklus hidup keselamatan satu shift kerja**, karena secara nyata risiko kelelahan **tidak statis** — seseorang bisa lolos pemeriksaan pagi dengan hasil Hijau, tapi 6 jam kemudian di tengah shift mulai menunjukkan tanda mengantuk berulang di kamera DMS. Sebaliknya, evaluasi di akhir hari adalah bahan pembelajaran untuk memperbaiki keputusan pra-operasi besok.

Tiga fase ini **bukan tiga fitur terpisah** — mereka adalah satu alur data yang mengalir:

```
PRA OPERASI                    SAAT OPERASI                     PASCA OPERASI
(sebelum unit jalan)      →    (unit sedang beroperasi)    →    (setelah shift selesai)
                                                                        │
                                                                        ▼
                                                          menjadi 1 titik data baru di
                                                          riwayat/baseline pribadi orang itu
                                                                        │
                                                                        ▼
                                                          dipakai lagi besok pagi sebagai
                                                          konteks di dashboard Pra Operasi
```

Dokumen ini merinci **tujuan, data, dan fitur dashboard** di tiap fase, lalu bagaimana ketiganya saling terhubung.

---

## 2. FASE 1 — PRA OPERASI

### 2.1 Tujuan
Menjawab pertanyaan: **"Dari semua yang checkin pagi ini, siapa yang belum boleh naik unit, dan siapa yang perlu perhatian ekstra berdasarkan riwayatnya?"**

Pengguna utama: Supervisor lapangan / petugas medis pra-shift, di jam-jam sibuk checkin (sebelum shift dimulai).

### 2.2 Data & Sumber (yang sudah tervalidasi)

| Kebutuhan | Sumber | Status |
|---|---|---|
| Siapa checkin, jam berapa, unit apa | `bcsid.mv_checkinout_rfid` ⋈ `m_karyawan`/`m_jabatan` | ✅ Sudah ada (`PraOperasiCheckinReader`) |
| Sudah/belum Fatigue Test hari ini | `bcsid.clean_data_fatigue_check` | ✅ Sudah ada (`PraOperasiFatigueCheckReader`) |
| Sudah/belum PVT hari ini | `cognitive_pvt_results` (BeWell) | ✅ Sudah ada (`PraOperasiPvtStatusReader`) |
| Riwayat penyakit kritis + follow-up | `clean_data_fatigue_check` | ✅ Sudah ada (`PraOperasiCriticalIllnessReader`) |
| **Roster ke berapa (hari ke-N dalam siklus kerja)** | `clean_data_fatigue_check.hari_ke` | ⚠️ Kolom ada, **perlu validasi** rentang nilai riil sebelum dipakai (contoh sampel: "1", "3") |
| **Shift siang/malam** | `clean_data_fatigue_check.shift` (kode 1/2) | ⚠️ Perlu konfirmasi mapping 1=siang atau 1=malam ke tim HSE |
| Status SIMPER (izin operasi unit) | `clean_data_fatigue_check.pemegang_simper` | Tersedia, belum dipakai |

### 2.3 Fitur Dashboard — Detail

**A. KPI Strip (ringkasan pagi)**
- Total Operator Checkin
- Belum Fatigue Test (dengan sub-angka: "dari jumlah ini, X sudah lewat jam mulai shift" — indikator keterlambatan proses, bukan cuma jumlah mentah)
- Belum PVT
- **Baru:** "Roster Hari ke-N Tinggi" — jumlah operator yang sedang di hari ke-5 atau lebih dalam siklus roster tanpa jeda (indikator akumulasi kelelahan, ambang persis perlu divalidasi ke distribusi riil `hari_ke`)

**B. Watchlist Operator (perluasan dari yang sudah ada)**
Kolom tambahan yang diusulkan di atas watchlist existing:
| Kolom baru | Kegunaan |
|---|---|
| Roster Hari ke- | Konteks akumulasi kelelahan — hari ke-1 vs hari ke-10 beda risiko meski skor FT sama |
| Shift (Siang/Malam) | Shift malam secara literatur risiko fatigue lebih tinggi |
| Evaluasi Kemarin | Tarik dari Fase 3 (Pasca Operasi) hari sebelumnya — badge kecil "Kemarin: Perlu Perhatian" supaya supervisor tahu ada histori, bukan cuma insiden pertama |

**C. Panel "Kesiapan Menyeluruh"**
Menggabungkan compliance (FT+PVT) dengan konteks roster — visual sederhana:
```
        Roster Hari 1-3     Roster Hari 4-7     Roster Hari 8+
Hijau        n orang             n orang            n orang
Kuning       n orang             n orang            n orang
Merah        n orang             n orang            n orang
```
Tujuannya: melihat apakah tier Kuning/Merah **terkonsentrasi** di roster hari-hari akhir (pola fatigue akumulatif) — kalau ya, ini insight actionable untuk kebijakan rotasi.

**D. Panel Detail Operator (sudah ada, drawer) — tambahan**
- Tambahkan baris "Hari ke-N roster saat ini" dan "Shift" di header drawer.
- Tambahkan baris "Evaluasi hari kerja sebelumnya" (hasil Fase 3 kemarin) di bagian atas alasan risiko — supaya keputusan pagi ini mempertimbangkan tren, bukan cuma snapshot hari ini.

---

## 3. FASE 2 — SAAT OPERASI

### 3.1 Tujuan
Menjawab pertanyaan: **"Dari semua operator yang sedang mengoperasikan unit sekarang, siapa yang kondisinya berubah dan perlu ditarik/ditegur SEKARANG — bukan besok setelah laporan harian keluar."**

Ini fase yang **paling berbeda sifatnya** dari dua fase lain: harus terasa **real-time/near-live**, karena keputusannya berdampak dalam hitungan menit (unit masih berjalan di jalan tambang).

Pengguna utama: Room control / Traffic Control / Supervisor HSE yang memantau selama shift berjalan.

### 3.2 Konsep Inti: Status Fit-to-Continue itu Dinamis

Status kelayakan operator **BUKAN** ditentukan sekali di pagi hari dan berlaku sepanjang shift. Status ini **diperbarui setiap kali ada alert DMS baru** selama unit berjalan. Inilah "matriks Fit/Tidak" yang diminta:

**Matriks Fit-to-Continue** (kolom = kondisi alert real-time, baris = hasil Fatigue Test pagi):

| Fatigue Test Pagi ↓ / Alert Selama Shift → | Belum Ada Alert | Ada Alert, Belum Diperiksa | Alert Dikonfirmasi Nyata (1x) | Alert Nyata Berulang (≥2x) |
|---|---|---|---|---|
| **Hijau** | 🟢 Fit | 🟢 Fit (pantau) | 🟡 Perlu Perhatian | 🔴 Tarik dari Unit |
| **Kuning** | 🟡 Perlu Perhatian | 🟡 Perlu Perhatian | 🔴 Tarik dari Unit | 🔴 Tarik dari Unit |
| **Merah** | 🔴 *(harusnya tidak sampai jalan — lihat catatan)* | 🔴 Tarik dari Unit | 🔴 Tarik dari Unit | 🔴 Tarik dari Unit |

> **Catatan penting:** baris "Merah" seharusnya sudah dicegat di Fase 1 (Pra Operasi) sebelum unit jalan. Kalau ada operator ber-status Merah tapi tetap tercatat checkin/beroperasi, ini sendiri adalah **red flag proses** — supervisor pra-operasi melewatkan sesuatu. Dashboard Fase 2 harus **menyorot kasus ini secara eksplisit** (bukan cuma menampilkan "Tarik dari Unit" seperti kasus biasa), karena ini indikasi kegagalan kontrol, bukan cuma risiko orang.

**"Belum diperiksa" vs "Dikonfirmasi Nyata"** — ini poin penting yang diminta secara eksplisit: alert yang **belum direview petugas L1** tidak boleh langsung dianggap "aman" hanya karena belum ada label — statusnya sendiri harus dianggap **pending/perlu perhatian**, sambil menunggu review benar-benar masuk. Inilah bedanya kolom "Belum Diperiksa" (Kuning) dengan "Belum Ada Alert" (Hijau) di matriks atas — keduanya BUKAN hal yang sama.

### 3.3 Data & Sumber

| Kebutuhan | Sumber | Status |
|---|---|---|
| Alert real-time selama shift | `bcsid.dms_alert` / `bcsid.mv_dms_alert` (kategori Menutup Mata/Menguap/Menunduk) | ✅ Sumber sudah tervalidasi |
| Status intervensi (nyata/palsu/belum) | `l1_model_status`, `sudah_direview_l1` | ✅ Sudah dipakai (reader profil operator) |
| Hasil Fatigue Test pagi (baris matriks) | `clean_data_fatigue_check` hari berjalan | ✅ Sudah ada |
| Sedang di unit / sudah checkout | `checked_out_at` (dari `mv_checkinout_rfid`) | ✅ Sudah ada — dipakai untuk memfilter "yang MASIH beroperasi" |

### 3.4 Fitur Dashboard — Detail

**A. Filter default: hanya yang "Masih di Site"**
Fase ini secara alami hanya relevan untuk baris watchlist dengan `checked_out_at IS NULL` (sudah dihitung sebagai KPI "Masih di Site" di dashboard yang ada) — jadi tidak perlu tabel baru, **tapi butuh mode tampilan baru**: sorting/filtering default ke populasi yang sedang aktif, dan kolom status berubah dari "Level Risiko" (statis harian) menjadi **"Status Fit-to-Continue"** (dinamis, dari matriks di atas).

**B. Papan Pemantauan Live (mode "Ruang Kontrol")**
- Grid kartu besar per operator yang sedang beroperasi (mirip pola `dms/dashboard.blade.php` yang sudah ada — realtime polling tiap beberapa detik), tapi kartunya menampilkan **Status Fit-to-Continue** dari matriks, bukan cuma safety score mentah.
- Highlight visual mencolok (border merah berkedip halus / badge besar) untuk kasus **"Tarik dari Unit"** — ini harus menonjol jauh lebih dari status lain karena butuh aksi segera.
- Kartu kasus "Merah pagi tapi tetap beroperasi" (red flag proses) ditampilkan **terpisah di baris paling atas**, bukan bercampur dengan kartu biasa.

**C. Feed Alert Live**
- Daftar kronologis alert masuk (terbaru di atas), dengan badge status (Belum Diperiksa/Nyata/Palsu) dan link cepat ke kartu operator terkait.
- Tombol aksi cepat: "Tandai sudah ditindaklanjuti" (kalau ada mekanisme mencatat tindakan supervisor di lapangan — perlu dikonfirmasi apakah ini sudah ada tabelnya atau perlu tabel baru).

**D. Alarm/Notifikasi (opsional, tahap lanjutan)**
- Trigger notifikasi (WA/email, mengikuti pola `dopm:auto-alert-wa` yang sudah ada di scheduler) begitu ada operator masuk status "Tarik dari Unit" — supaya tidak bergantung pada supervisor terus-menerus menatap layar.

---

## 4. FASE 3 — PASCA OPERASI

### 4.1 Tujuan
Menjawab pertanyaan: **"Bagaimana keseluruhan performa keselamatan satu hari kerja orang ini — dan apa yang bisa dipelajari untuk besok?"**

Ini **evaluasi retrospektif harian**, dijalankan setelah shift/checkout selesai (bisa berupa laporan yang di-generate di akhir shift, atau live namun dilihat sebagai "riwayat hari ini" oleh supervisor pagi berikutnya).

Pengguna utama: Tim HSE untuk evaluasi & pembinaan, plus feed balik ke supervisor pra-operasi shift berikutnya.

### 4.2 Konsep Inti: Skor Evaluasi Harian

Berbeda dengan Level Risiko di Fase 1 (yang dihitung SEBELUM shift, berbasis riwayat 30 hari + hasil pagi), **Skor Evaluasi Harian** dihitung SETELAH shift selesai, dan menggabungkan apa yang BENAR-BENAR terjadi hari itu:

| Komponen | Bobot dalam evaluasi |
|---|---|
| Hasil Fatigue Test pagi (tier) | Titik awal hari |
| Hasil PVT pagi | Titik awal hari |
| Jumlah & jenis alert selama shift (nyata/palsu/belum) | Realita di lapangan |
| Apakah pernah masuk status "Tarik dari Unit" selama shift (dari Fase 2) | Insiden puncak hari itu |
| Durasi kerja aktual (checkin → checkout) | Konteks kelelahan (shift kepanjangan?) |
| Perbandingan ke baseline pribadi (skor & jumlah alert vs rata-rata 30 hari orang itu) | Apakah ini "hari buruk" personal atau memang tren menurun |

**Kategori akhir yang diusulkan:**
- 🟢 **Baik** — kontrol lengkap, tidak ada alert nyata berarti, sesuai/lebih baik dari baseline pribadi
- 🟡 **Perlu Pembinaan** — ada 1-2 sinyal (mis. alert nyata muncul tapi tidak berulang, atau kontrol pagi telat)
- 🔴 **Kritis — Perlu Tindak Lanjut** — sempat masuk status "Tarik dari Unit" di Fase 2, atau kombinasi berat (skor jauh di bawah baseline + alert nyata berulang)

### 4.3 Data & Sumber
Semua sumber sudah ada dari Fase 1 & 2 — Fase 3 pada dasarnya **agregasi** dari keduanya per-orang per-hari, tidak butuh sumber data baru. Yang dibutuhkan baru adalah **satu tabel/penyimpanan hasil evaluasi harian** (lihat §6) supaya:
1. Tidak perlu hitung ulang tiap kali dashboard dibuka (evaluasi hari yang sudah lewat itu tetap, tidak berubah lagi).
2. Bisa ditarik balik sebagai konteks di Fase 1 hari berikutnya ("Evaluasi Kemarin").

### 4.4 Fitur Dashboard — Detail

**A. Ringkasan Harian (per tanggal, seperti laporan shift)**
- KPI: Baik / Perlu Pembinaan / Kritis (jumlah orang per kategori)
- Trend mini dibanding hari sebelumnya (naik/turun jumlah kasus Kritis)

**B. Tabel Evaluasi Harian per Operator**
Kolom: Nama, Unit, Shift, Skor Kesiapan Pagi, Hasil PVT, Jumlah Alert (nyata/palsu/belum), Sempat "Tarik dari Unit"? (Y/T), Durasi Kerja, **Kategori Evaluasi Akhir**, Perbandingan ke Baseline (↑/→/↓).

**C. Panel "Riwayat Evaluasi" per Operator (perluasan drawer detail)**
- Grafik kalender/heatmap sederhana: kotak per hari (30-90 hari terakhir), warna = kategori evaluasi hari itu — supaya pola berulang (mis. "tiap hari ke-7 roster selalu Kuning") langsung kelihatan visual, bukan harus scroll tabel.

**D. Laporan/Ekspor Harian**
- Export Excel/PDF ringkasan evaluasi harian per site/perusahaan — untuk dilampirkan ke rapat HSE pagi (mengikuti pola export yang sudah ada di modul lain, mis. `evaluasi-well`).

---

## 5. Keterhubungan Antar Fase (Loop Tertutup)

```
┌─────────────────────────────────────────────────────────────────────┐
│  FASE 1: PRA OPERASI                                                 │
│  Input: checkin + FT + PVT + riwayat 30hr + "Evaluasi Kemarin"        │
│  Output: siapa boleh jalan, siapa perlu pantau ekstra                │
└───────────────────────────────┬───────────────────────────────────────┘
                                 │ operator yang lolos → mulai beroperasi
                                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│  FASE 2: SAAT OPERASI                                                 │
│  Input: hasil FT pagi (dari Fase 1) + alert DMS real-time             │
│  Output: status Fit-to-Continue dinamis, aksi tarik-dari-unit         │
└───────────────────────────────┬───────────────────────────────────────┘
                                 │ shift selesai / checkout
                                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│  FASE 3: PASCA OPERASI                                                │
│  Input: semua dari Fase 1 + seluruh kejadian Fase 2 hari itu          │
│  Output: Skor Evaluasi Harian (disimpan permanen)                    │
└───────────────────────────────┬───────────────────────────────────────┘
                                 │ jadi 1 titik data baru
                                 ▼
                    riwayat/baseline pribadi (30 hari) — dipakai lagi
                    besok pagi di Fase 1 sebagai "Evaluasi Kemarin"
                    dan sebagai bagian dari perhitungan Level Risiko
```

**Prinsip kunci:** ketiga fase berbagi bahasa data yang sama (SID, tanggal, tier Hijau/Kuning/Merah, status alert Nyata/Palsu/Belum) — supaya seorang supervisor yang paham satu fase otomatis paham dua lainnya, tanpa perlu belajar istilah baru per dashboard.

---

## 6. Kebutuhan Teknis Baru (yang belum ada sebelumnya)

| Kebutuhan | Kenapa perlu | Catatan |
|---|---|---|
| **Tabel penyimpanan Skor Evaluasi Harian** (per SID per tanggal) | Fase 3 butuh hasil yang "membeku" (tidak dihitung ulang tiap load), dan Fase 1 besok butuh menarik balik hasil ini | Bisa di MySQL lokal aplikasi (bukan hse_automation) — data milik aplikasi ini sendiri, bukan data mentah dari sumber lain |
| **Job terjadwal "tutup buku harian"** | Menghitung & menyimpan Skor Evaluasi Harian untuk SEMUA operator yang checkin, dijalankan sekali setelah jam kerja/shift berakhir | Mengikuti pola `$schedule->command(...)` yang sudah banyak dipakai di `Kernel.php` |
| **Definisi "sedang beroperasi" yang presisi** | Fase 2 butuh tahu siapa yang benar-benar masih di unit, bukan cuma belum checkout (bisa jadi lupa tap keluar) | Perlu aturan tambahan (mis. batas waktu maksimal shift) supaya tidak salah anggap orang "masih beroperasi" 20 jam kemudian |
| **Validasi kolom `hari_ke` dan `shift`** | Dipakai di Fase 1 untuk konteks roster | Perlu query distribusi nilai riil dulu sebelum dipakai di produksi (pola yang sama seperti validasi ambang skor Fatigue Test sebelumnya) |
| **Mekanisme "tandai sudah ditindaklanjuti"** (Fase 2) | Supaya supervisor bisa mencatat aksi, bukan cuma melihat | Perlu tabel baru kalau belum ada — perlu dikonfirmasi ke Anda |

---

## 7. Rencana Bertahap (Diusulkan)

1. **Tahap 1 — Perluas Fase 1** (risiko paling rendah, murni tambah kolom & panel ke yang sudah ada): tambah `hari_ke`, `shift`, dan panel "Evaluasi Kemarin" (butuh Tahap 3 selesai dulu secara data, tapi UI-nya bisa disiapkan lebih awal dengan data kosong).
2. **Tahap 2 — Bangun Fase 3** (evaluasi harian + tabel penyimpanan + job terjadwal): ini prasyarat teknis supaya Fase 1 bisa menampilkan "Evaluasi Kemarin", dan nilainya berdiri sendiri (laporan harian untuk tim HSE) bahkan sebelum Fase 2 selesai.
3. **Tahap 3 — Bangun Fase 2** (paling kompleks: perlu mode tampilan real-time/live, keputusan UX untuk notifikasi): dikerjakan terakhir karena bergantung pada matriks yang sudah dikonfirmasi, dan idealnya setelah pola Fase 1 & 3 sudah divalidasi dulu oleh tim di lapangan.

---

## 8. Pertanyaan Terbuka (perlu keputusan Anda / tim HSE sebelum implementasi)

1. **Mapping kode `shift`** — apakah `1` = siang dan `2` = malam, atau sebaliknya? (perlu dikonfirmasi ke tim, bukan ditebak)
2. **Ambang "hari ke-N roster tinggi"** — hari ke berapa mulai dianggap perlu perhatian ekstra? Perlu dicek distribusi nilai `hari_ke` riil dulu.
3. **Definisi "masih beroperasi" di Fase 2** — apakah cukup `checked_out_at IS NULL`, atau perlu batas waktu maksimal (mis. otomatis dianggap selesai setelah 14 jam sejak checkin, untuk kasus lupa tap keluar)?
4. **Mekanisme tindak lanjut di Fase 2** — apakah sudah ada sistem pencatatan tindakan supervisor (mis. terhubung ke `m_dms_intervension` yang pernah kita temukan di eksplorasi awal), atau perlu dibangun baru?
5. **Kategori evaluasi harian (Fase 3)** — apakah 3 kategori (Baik/Perlu Pembinaan/Kritis) sudah cukup, atau perlu selaras dengan skema sanksi/pembinaan yang sudah ada di perusahaan (mis. skema Berecord L1-L4 yang sempat kita temukan di awal eksplorasi `bcsid`)?
6. **Notifikasi real-time Fase 2** — apakah mau dibangun sejak awal, atau cukup dashboard yang di-refresh manual dulu (lebih sederhana, lebih cepat dirilis)?

---

*Dokumen ini murni perencanaan — menunggu konfirmasi/masukan Anda sebelum masuk ke tahap implementasi kode.*
