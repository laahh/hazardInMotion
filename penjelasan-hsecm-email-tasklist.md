# Penjelasan Email Shift HSECM + Tasklist

Dokumen ini menjelaskan **cara kerja email terjadwal**, **2 shift**, **tasklist publik**, dan **ACC/tolak** di Admin.  
Sumber data scrape dijelaskan di [`penjelasan.md`](penjelasan.md).

---

## 1. Tujuan fitur

Setiap shift, sistem:

1. Membaca status gap HSECM dari tabel `scr_hsecm_*` berdasarkan **`batch_slot`** (bukan `COUNT(*)` seluruh tabel).
2. Mengirim email ke PJO / PIC / PM per **site + perusahaan**.
3. Di **akhir shift**: membuat **tasklist** + link token untuk upload evidence.
4. HSE di Admin **ACC** atau **Tolak** submit tersebut.
5. Jika tasklist belum closed → **escalate** otomatis.

---

## 2. Dua shift per hari (WITA)

Operasi mengikuti **2 shift**, mengikuti cut-off scrape `00 / 06 / 12 / 18`.

| Shift | Midshift (monitoring) | Endshift (still-open + tasklist) |
|-------|------------------------|----------------------------------|
| **Malam** | **01:00** → snapshot slot **00:00** | **07:00** → still-open **06:00 vs 00:00** |
| **Siang** | **13:00** → snapshot slot **12:00** | **19:00** → still-open **18:00 vs 12:00** |

Urutan berulang:

```text
… → Mid 00 → End 06 → Mid 12 → End 18 → Mid 00 → …
     (email 01) (email 07) (email 13) (email 19)
```

### Scheduler Laravel (`Asia/Makassar`)

| Jam | Command |
|-----|---------|
| 01:00 | `hsecm:send-midshift-email --shift=night` |
| 07:00 | `hsecm:send-endshift-email --shift=night` |
| 13:00 | `hsecm:send-midshift-email --shift=day` |
| 19:00 | `hsecm:send-endshift-email --shift=day` |
| 08 / 14 / 20 / 02 | `hsecm:escalate-open-tasklists` |

File: `app/Console/Kernel.php`.

---

## 3. Midshift vs Endshift

### Midshift (tengah shift)

- Mode data: **`snapshot`** = semua baris outstanding di `batch_slot` target.
- Template: Monitoring & Intervensi (nada “sebelum akhir shift”).
- CTA: halaman publik **Aksi PJO** `/hsecm/pjo-action?site=…&perusahaan=…`.
- **Tidak** membuat tasklist.
- Tetap dikirim meskipun gap sedikit (selama ada penerima & data slot).

### Endshift (akhir shift)

- Mode data: **`still_open`** = item yang masih ada di slot akhir **dan** slot mid sebelumnya (intersection `business_key`).
  - Fallback jika belum ada pair slot / tanpa `business_key`: `gap_count >= 2`.
- Template: pasca-shift + ajakan submit evidence.
- CTA: **Tasklist** `/hsecm/tasklist/{token}`.
- **Membuat 1 tasklist** per `(batch_slot, site, perusahaan)`.
- Jika **tidak ada** gap still-open untuk scope itu → **skip** (tidak kirim, tidak buat tasklist).

```text
Contoh siang:
  Slot 12:00 → A, B, C
  Slot 18:00 → B, C, D
  Still-open = B, C   (A comply, D baru)
  → email endshift + tasklist berisi B & C
```

---

## 4. Penerima email

Sumber sama dengan WA Notify:

1. `config/hsecm.php` → `wa_recipients`
2. Custom: `storage/app/hsecm/wa_recipients.json` (bisa ditambah dari UI)

Setiap penerima mendapat email **hanya untuk scope site + perusahaannya**.

Public URL di email memakai `HSECM_PUBLIC_URL` (bukan `APP_URL` lokal), default `https://besentry-dev.beraucoal.co.id`.

### Uji ke satu email

```bash
php artisan hsecm:send-midshift-email --email=anda@contoh.com --shift=day
php artisan hsecm:send-endshift-email --email=anda@contoh.com --shift=night
php artisan hsecm:send-endshift-email --dry-run --email=anda@contoh.com
```

| Opsi | Arti |
|------|------|
| `--dry-run` | Simulasi; **tidak** kirim email / tidak buat tasklist |
| `--email=` | Hanya ke alamat itu (atau override uji jika belum terdaftar) |
| `--shift=auto\|day\|night` | `auto` = jam 00–11 night, 12–23 day |
| `--site=` / `--perusahaan=` | Scope jika email belum di daftar |

---

## 5. Tasklist (submit evidence)

### Kapan token muncul?

Token dibuat **otomatis saat endshift sukses** (ada still-open + migration jalan).

- Disimpan di `hsecm_tasklists.token` (`Str::random(48)`).
- Link: `{HSECM_PUBLIC_URL}/hsecm/tasklist/{token}`
- Satu token di-share semua penerima scope yang sama.
- Bisa dilihat lagi di Admin → **Tasklist Review**.

### Alur PJO / PIC (publik, tanpa login)

1. Buka `/hsecm/tasklist/{token}`
2. Centang item
3. Isi nama + catatan perbaikan
4. Upload file evidence per item
5. Submit → status item `submitted` (bukan final closed)

File disimpan di disk `public`: `hsecm/tasklist-evidence/{Y}/{m}/`  
(perlu `php artisan storage:link` jika belum).

### Status item

```text
open → submitted → approved
                 ↘ rejected → submitted (resubmit) → …
```

### Status tasklist

| Status | Arti |
|--------|------|
| `open` | Belum ada progress |
| `partial` | Ada campuran submitted / approved / rejected |
| `closed` | **Semua** item `approved` |

---

## 6. ACC / Tolak (HSE Admin)

Route auth:

| URL | Fungsi |
|-----|--------|
| `/hsecm/tasklist` | Daftar tasklist |
| `/hsecm/tasklist/manage/{id}` | Detail + ACC / Tolak |
| `POST .../items/{id}/approve` | ACC |
| `POST .../items/{id}/reject` | Tolak (wajib alasan) |

Nav: **Tasklist Review**.

- **ACC** → item `approved`
- **Tolak** → item `rejected` + alasan; PJO bisa resubmit
- Tasklist `closed` hanya jika semua item approved

---

## 7. Escalate

Jalan jika tasklist **belum `closed`** dan `next_escalate_at` sudah lewat.

| | |
|--|--|
| Pertama | **H+1 jam 08:00** WITA (relatif tanggal `batch_slot`) |
| Berikutnya | +6 jam (selaras slot 08 / 14 / 20 / 02) |
| Isi email | Item pending (`open` / `submitted` / `rejected`) + link token yang sama |

Command: `hsecm:escalate-open-tasklists` (`--dry-run`, `--email=` didukung).

---

## 8. Tabel database (Laravel migration)

| Tabel | Isi |
|-------|-----|
| `hsecm_tasklists` | token, site, perusahaan, batch_slot, status, escalate_* |
| `hsecm_tasklist_items` | program, business_key, status, notes, review |
| `hsecm_tasklist_evidences` | file evidence per submit/resubmit |

Migration file:

- `database/migrations/2026_07_24_100001_create_hsecm_tasklists_table.php`
- `database/migrations/2026_07_24_100002_create_hsecm_tasklist_items_table.php`
- `database/migrations/2026_07_24_100003_create_hsecm_tasklist_evidences_table.php`

**Jangan migrate otomatis tanpa konfirmasi.** Jalankan manual setelah disetujui.

Ini **terpisah** dari alter SQL scrap (`batch_slot` / `business_key` / `gap_count` di `scr_hsecm_*`).

---

## 9. Prasyarat data scrape

Email terjadwal **wajib** kolom `batch_slot` di `scr_hsecm_*`.

| Tanpa `batch_slot` | Command gagal: “Kolom batch_slot belum ada…” |
| Midshift tanpa data slot | Pesan tidak ada batch_slot ≤ target |
| Endshift tanpa still-open | `Skip: tidak ada gap still-open` (bukan error SMTP) |

Lihat detail append / gap di [`penjelasan.md`](penjelasan.md).

---

## 10. File kode utama

| Area | File |
|------|------|
| Repository slot | `app/Services/Hsecm/HsecmDatabaseRepository.php` |
| KPI / narrative / extract gap | `app/Services/Hsecm/HsecmDashboardService.php` |
| Dispatch email | `app/Services/Hsecm/HsecmShiftEmailDispatchService.php` |
| Tasklist logic | `app/Services/Hsecm/HsecmTasklistService.php` |
| Mail | `app/Mail/HsecmSummaryMail.php` + `resources/views/emails/hsecm-*.blade.php` |
| Publik submit | `HsecmTasklistPublicController` + `BaseRule/tasklist/show.blade.php` |
| Admin review | `HsecmTasklistManageController` + `BaseRule/tasklist/{index,manage}.blade.php` |
| Route | `routes/Hsecm/hsecm.php` |
| Config | `config/hsecm.php` |

---

## 11. Checklist uji cepat

1. Pastikan `batch_slot` ada & scrape minimal 2 slot untuk uji endshift.
2. Migrate tabel tasklist (setelah konfirmasi).
3. `php artisan storage:link` (evidence).
4. Midshift uji:
   ```bash
   php artisan hsecm:send-midshift-email --email=anda@contoh.com --shift=night
   ```
5. Endshift uji (setelah ada still-open):
   ```bash
   php artisan hsecm:send-endshift-email --email=anda@contoh.com --shift=night
   ```
6. Buka link token → submit notes + file.
7. Login Admin → Tasklist Review → ACC / Tolak.
8. Pastikan scheduler aktif (`schedule:work` / cron `schedule:run`).

---

## 12. Satu kalimat ringkas

Setiap 6 jam data di-foto ke `scr_hsecm_*`; tiap shift dikirim midshift (snapshot) lalu endshift (yang masih open + tasklist token); HSE ACC/tolak di Admin; escalate berulang sampai semua item approved.
