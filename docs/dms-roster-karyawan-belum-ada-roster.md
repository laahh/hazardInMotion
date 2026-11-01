# Karyawan Aktif yang Belum Ada di Master Roster

Dokumen ini menjelaskan asal angka **"belum ada roster"** di `/dms/master-roster/overview` (per 2 Nov 2026: **5.004 dari 17.208 karyawan aktif**) dan usulan flow banned / unbanned otomatis berdasarkan angka tersebut.

---

## 1. Ringkasan rumus

```
BELUM ADA ROSTER = KARYAWAN AKTIF (A)  −  KARYAWAN AKTIF YANG SID-NYA ADA DI ROSTER (B)

A = SID di bcsid.bep_vw_safety_karyawan_aktif (Postgres OLAP)
    − jabatan VISITOR
    − perusahaan / SID kosong
    − perusahaan & site yang dikecualikan

B = SID dari A yang muncul di dms_roster_master_jadwal (MySQL)
    dengan upload berstatus PENDING atau APPROVED (REJECTED tidak dihitung)
```

| Komponen | Jumlah (2 Nov 2026) |
|---|---:|
| A. Karyawan aktif | 17.208 |
| B1. Ada di roster **APPROVED** | 11.660 |
| B2. Hanya ada di roster **PENDING** (belum approval) | 544 |
| B. Sudah ada roster (B1 + B2) | 12.204 |
| **A − B. Belum ada roster** | **5.004** |

---

## 2. Sumber data

### 2a. Karyawan aktif (A): Postgres OLAP `hse_automation`

**View:** `bcsid.bep_vw_safety_karyawan_aktif`

| Kolom | Dipakai sebagai |
|---|---|
| `kode_sid` | SID (di-`trim`, dibandingkan dalam huruf besar) |
| `nama` | nama karyawan |
| `nama_perusahaan` | perusahaan |
| `site_dedicated` | site |
| `jabatan_struktural` | untuk membuang VISITOR |

Catatan:
- View ini hanya memuat karyawan yang **aktif dan permit-nya PASSED**. Karyawan aktif dengan permit `NOT PASSED` tidak muncul di sini.
- View ini rename tipis di atas `m_karyawan` (±6 GB). **Jangan pasang `WHERE` langsung pada view**, karena predikatnya terdorong ke tabel dasar dan query jadi lebih dari 30 detik. Ambil semua barisnya, lalu saring di aplikasi (atau di CTE `MATERIALIZED`).

Query pengambilan (dipakai Tahap 8 `dms:sync-roster-rfid`):

```sql
SELECT trim(kode_sid)        AS sid,
       nama,
       trim(nama_perusahaan) AS perusahaan,
       jabatan_struktural    AS jabatan,
       site_dedicated        AS site
FROM bcsid.bep_vw_safety_karyawan_aktif;
```

Hasilnya disalin ke cache aplikasi `dms_roster:karyawan_aktif:v1`, disegarkan **tiap jam** oleh `php artisan dms:sync-roster-rfid` (Tahap 8).

**Penyaringan setelah diambil:**

| Aturan | Nilai | Lokasi pengaturan |
|---|---|---|
| Jabatan tidak dihitung | `VISITOR` | `config/dms_roster.php`, `master.jabatan_dikecualikan` |
| Perusahaan / SID kosong | dibuang | kode |
| Perusahaan dikecualikan | PT Berau Coal, PT Berau Coal Energy, PT Unggul Jaya Berkah, Politeknik Sinar Mas Berau Coal, Yayasan Dharma Bakti Berau Coal, KAMPUS MERDEKA | `config/dms_roster.php`, `master.overview_dikecualikan.perusahaan` |
| Site dikecualikan | `MARINE` | `config/dms_roster.php`, `master.overview_dikecualikan.site` |

Nama perusahaan dicocokkan **utuh** tanpa awalan PT/CV dan tanda baca: `PT. Berau Coal` = `PT Berau Coal`, tetapi `Politeknik Sinar Mas Berau Coal` ≠ `PT Berau Coal`. Site dicocokkan tanpa membedakan huruf besar/kecil.

### 2b. Roster ter-upload (B): MySQL `app_mixer`

| Tabel | Isi | Kolom penting |
|---|---|---|
| `dms_roster_master_upload` | satu baris per file roster yang di-upload | `id`, `status` (`PENDING` / `APPROVED` / `REJECTED`), `created_at` |
| `dms_roster_master_jadwal` | satu baris per karyawan per tanggal | `upload_id`, `kode_sid`, `perusahaan`, `site`, `tanggal` |

Query SID yang sudah ada roster:

```sql
SELECT UPPER(TRIM(j.kode_sid))      AS sid,
       MAX(u.status = 'APPROVED')   AS ada_approved,
       MAX(u.status = 'PENDING')    AS ada_pending
FROM dms_roster_master_jadwal j
JOIN dms_roster_master_upload u ON u.id = j.upload_id
WHERE u.status <> 'REJECTED'
  AND UPPER(TRIM(COALESCE(j.site, ''))) <> 'MARINE'
GROUP BY 1;
```

> ⚠️ `dms_roster_master_jadwal` berisi ±344 ribu baris. Di MySQL dev, `GROUP BY` penuh memakan **20–40 detik**. Jangan jalankan di request web. Aplikasi sudah menghitungnya di latar setiap menit, **hanya bila data berubah** (`php artisan dms:master-roster-ringkas`), dan menyimpannya di cache `dms_roster:master_ringkas:v1`. Flow banned sebaiknya membaca dari ringkasan ini.

---

## 3. Cara menghitung (langkah demi langkah)

Kedua sumber ada di database berbeda (Postgres vs MySQL), jadi **tidak bisa di-JOIN di SQL**. Pencocokan dilakukan di aplikasi.

```
1. Ambil A    : semua karyawan aktif dari view (langkah 2a),
                buang VISITOR, perusahaan/SID kosong, perusahaan & site dikecualikan.
                Kunci = UPPER(TRIM(kode_sid)).

2. Ambil B    : semua SID di roster PENDING/APPROVED (langkah 2b),
                buang baris yang perusahaan/site-nya dikecualikan.
                Kunci = UPPER(TRIM(kode_sid)).

3. Cocokkan per SID:
     SID di A dan ada_approved       → "Sudah roster (approved)"
     SID di A, hanya ada_pending     → "Sudah roster (menunggu approval)"
     SID di A, tidak ada di B        → "BELUM ADA ROSTER"   ← kandidat banned
```

Pseudocode:

```php
$aktif  = [...];   // sid => [nama, perusahaan, site]   (langkah 1)
$roster = [...];   // sid => [approved => bool, pending => bool]   (langkah 2)

$belum = array_diff_key($aktif, $roster);   // 5.004 SID
```

**Pencocokan dilakukan per SID, bukan per nama perusahaan.** Karyawan dianggap sudah ber-roster bila SID-nya ada di roster mana pun, walaupun roster itu di-upload atas nama perusahaan lain atau dengan ejaan berbeda.

---

## 4. Hal yang perlu diperhatikan sebelum dipakai untuk banned

### 4a. SID salah isi di roster

Per 2 Nov 2026 ada **272 SID** di roster yang tidak cocok dengan karyawan aktif mana pun:

| Kelompok | Jumlah | Contoh |
|---|---:|---|
| Karyawan sudah nonaktif (`m_karyawan.id_status = 2`) | 53 | PT Puncak Makmur Jaya 26 orang |
| Aktif tetapi permit NOT PASSED | 50 | tidak masuk view aktif |
| Aktif & PASSED, belum ada di salinan (karyawan baru) | 8 | |
| **Bukan SID** (NRP, nomor karyawan, teks) | ±161 | PT Pamapersada Nusantara mengisi NRP `61070408`; PT Salwa Jaya `20180901`; isian `RESIGN`, `Kontraktor A` |

Akibatnya, sebagian dari 5.004 orang itu sebenarnya **sudah di-roster-kan, hanya SID-nya salah tulis**. Kalau langsung di-banned, mereka akan ter-banned padahal perusahaannya sudah upload.

**Saran:** validasi SID saat upload. Tolak atau tandai baris yang SID-nya tidak ada di karyawan aktif, supaya perusahaan memperbaikinya sebelum flow banned berjalan.

### 4b. Kesegaran data

| Data | Disegarkan | Risiko |
|---|---|---|
| Salinan karyawan aktif | tiap jam (Tahap 8) | Karyawan baru belum masuk selama ≤ 1 jam. Kalau OLAP mati, salinan bisa lebih lama. |
| Ringkasan roster | tiap menit, bila ada perubahan | Upload baru terbaca ≤ ±1 menit + lama query (20–40 detik) |

Flow banned **wajib berhenti (tidak mem-banned siapa pun)** bila salah satu salinan kosong atau gagal diambil. Kalau tidak, semua karyawan akan terbaca "belum ada roster".

### 4c. Roster yang sudah lewat

Rumus di atas menghitung "sudah roster" bila SID **pernah** ada di roster PENDING/APPROVED, **tanpa melihat tanggal**. Roster periode Agustus yang sudah habis tetap terhitung "sudah".

Kalau maksudnya "harus punya jadwal yang **masih berlaku**", tambahkan syarat pada langkah 2b:

```sql
  AND j.tanggal >= CURDATE()      -- punya jadwal hari ini atau ke depan
```

Ini **keputusan bisnis yang perlu ditetapkan** sebelum flow banned dibuat.

---

## 5. Usulan flow banned / unbanned otomatis

```
                ┌──────────────────────────────────────────┐
  tiap jam ───► │ 1. Ambil A (karyawan aktif) & B (roster) │
                └──────────────────┬───────────────────────┘
                                   │ salah satu kosong / gagal?
                                   ├── ya ──► STOP, catat log, jangan ubah apa pun
                                   ▼
                ┌──────────────────────────────────────────┐
                │ 2. BELUM = A − B (per SID)               │
                └──────────────────┬───────────────────────┘
                                   ▼
        ┌──────────────────────────┴──────────────────────────┐
        ▼                                                     ▼
┌───────────────────────────────┐            ┌──────────────────────────────────┐
│ 3. BANNED                     │            │ 4. UNBANNED OTOMATIS             │
│ SID di BELUM, dan             │            │ SID yang sedang di-banned dengan │
│ belum punya kasus aktif       │            │ alasan "belum ada roster", dan   │
│ → masukkan ke master banned   │            │ SID-nya SEKARANG ada di B        │
│   alasan: "Belum ada di       │            │ (PENDING atau APPROVED)          │
│   Master Roster"              │            │ → tutup kasus / unban            │
└───────────────────────────────┘            └──────────────────────────────────┘
```

### Aturan banned
1. SID ada di **A** dan **tidak ada** di **B**.
2. Belum punya kasus banned aktif dengan alasan yang sama (hindari dobel).
3. *(Disarankan)* Masa tenggang. Banned baru dijalankan bila SID sudah **N hari berturut-turut** ada di daftar BELUM, sehingga karyawan baru dan perusahaan yang sedang menyiapkan upload tidak langsung kena.
4. *(Disarankan)* Lewati perusahaan yang **belum pernah upload sama sekali** sampai batas waktu upload yang ditetapkan. Ada 81 perusahaan seperti ini, yang tidak tahu ada kewajiban upload.

### Aturan unbanned otomatis
1. SID sedang di-banned dengan alasan **"Belum ada di Master Roster"**.
2. SID tersebut **sekarang ada di B**, yaitu di roster berstatus **PENDING maupun APPROVED**.
3. → Tutup kasus banned (unban), catat waktu dan upload mana yang membuatnya lolos.

Catatan untuk unbanned:
- Bila roster yang membuat unban kemudian **di-REJECT**, SID kembali masuk daftar BELUM dan akan di-banned lagi pada putaran berikutnya. Ini sesuai dengan aturan "PENDING pun dianggap sudah upload".
- Bila roster **dihapus** (fitur hapus batch untuk roster yang belum disetujui), efeknya sama dengan REJECT.

### Integrasi dengan sistem banned yang sudah ada
- Master banned: tabel `sid_roster_banned_master` (menu Master Roster Banned).
- Unban: tabel `sid_roster_unban_log`.
- Eksekusi banned SID sungguhan di work permit dikerjakan modul **AutoBanned**, yang membaca master banned. Jadi baris yang masuk ke master banned **benar-benar mem-banned SID**. Uji dulu dengan mode pratinjau (pola yang sama dengan `php artisan dms:roster-auto-banned --uji`) sebelum diaktifkan.
- Bedakan alasan banned "Belum ada di Master Roster" dari alasan lain (pelanggaran > 70 hari on-site, > 13 hari kerja beruntun), supaya unban otomatis **hanya** menutup kasus miliknya sendiri.

---

## 6. Referensi kode

| Fungsi | File |
|---|---|
| Ambil & salin karyawan aktif | `app/Services/Dms/Roster/DmsRosterKaryawanAktif.php` (`segarkan()`, `sidPerPerusahaan()`) |
| Ringkasan roster (query MySQL) | `app/Services/Dms/Roster/DmsRosterMasterCache.php` (`hitungDanSimpan()`) |
| Pengecualian perusahaan / site | `app/Services/Dms/Roster/DmsRosterOverviewPengecualian.php` |
| Perbandingan cakupan & kolom "Kurang" | `app/Services/Dms/Roster/DmsRosterCakupanReader.php` (`cakupan()`) |
| Konfigurasi | `config/dms_roster.php` (`master.*`) |
| Jadwal sinkron | `dms:sync-roster-rfid` (tiap jam), `dms:master-roster-ringkas` (tiap menit) |
