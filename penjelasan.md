# Penjelasan Scraping HSECM → `scr_hsecm_*`

Dokumen konteks untuk Cursor / tim: bagaimana data ditarik, disimpan, dan dibaca.
Sumber scrape: `tableau_hsecm_rulebase_to_json.py`.
Tujuan utama di tahap ini: **menarik & menumpuk data**. Pengiriman email memakai data ini sebagai sumber, bukan mengganti logika scrape.

---

## 1. Apa yang dilakukan sistem

1. Scrape view Tableau (rule HSECM) untuk **week berjalan**.
2. Simpan JSON di `output/hsecm/`.
3. Jika `MYSQL_SYNC=true`, **append** hasil ke tabel `scr_hsecm_*` (satu tabel per program).
4. Bandingkan dengan scrape **6 jam sebelumnya** (naik/turun, resolved/new).
5. Hitung **`gap_count`** per item: berapa kali berturut-turut masih muncul (belum comply).

Ini **bukan** “ambil hanya event baru 6 jam terakhir dari Tableau”.
Tableau memberi **snapshot kondisi saat ini**; sistem yang menyimpan history dan menghitung perubahan.

---

## 2. Jadwal cut-off (`batch_slot`)

Scrape dijadwalkan tiap **6 jam** pada jam lokal:

| Slot | Jam |
|------|-----|
| Malam | `00:00` |
| Pagi | `06:00` |
| Siang | `12:00` |
| Sore | `18:00` |

### Dua field waktu

| Field | Arti |
|--------|------|
| `scraped_at` | Waktu nyata eksekusi scrape (mis. `12:03:17`) |
| `batch_slot` | **Cut-off resmi** yang dilantai ke `00/06/12/18` |

Contoh: scrape jam `16:30` → `batch_slot = 12:00` (slot terakhir yang sudah lewat), kecuali di-override lewat env `HSECM_BATCH_SLOT`.

“6 jam sebelumnya” = `batch_slot − 6 jam`, bukan “sekarang − 6 jam” secara longgar.

Override opsional:

```text
HSECM_BATCH_SLOT=2026-07-23 12:00:00
```

(harus tepat jam `00/06/12/18`, menit/detik 0)

---

## 3. Konsep APPEND (penting)

Setiap scrape **menambah** baris baru. Data slot lama **tidak dihapus**.

### Use case

Tanggal **23 Juli 2026**, tabel `scr_hsecm_partisipasi_sap_l1_rfid`:

| Waktu | Isi scrape | Aksi DB |
|--------|------------|---------|
| 12:00 | 10 orang | INSERT 10 baris (`batch_slot = 2026-07-23 12:00:00`) |
| 18:00 | 5 orang | INSERT 5 baris baru (`batch_slot = 2026-07-23 18:00:00`) |

**Total baris di tabel setelah jam 18:00 = 10 + 5 = 15**
bukan 5.

| Pertanyaan | Jawaban | Cara baca |
|------------|---------|-----------|
| Total history di tabel? | **15** | `COUNT(*)` tanpa filter |
| Kondisi “sekarang” (jam 18)? | **5** | `WHERE batch_slot = '2026-07-23 18:00:00'` |
| Yang hilang sejak siang (comply)? | ~**5** | compare `resolved` / kunci yang tidak muncul lagi |
| Yang masih gap dari siang? | yang 5 itu, biasanya `gap_count = 2` | masih ada di 12:00 dan 18:00 |

```sql
-- Total history
SELECT COUNT(*) FROM scr_hsecm_partisipasi_sap_l1_rfid;

-- Snapshot jam 18 saja (untuk email / status terkini)
SELECT COUNT(*) FROM scr_hsecm_partisipasi_sap_l1_rfid
WHERE batch_slot = '2026-07-23 18:00:00';
```

**Aturan baca untuk email / dashboard:**
selalu filter `batch_slot` (atau ambil `MAX(batch_slot)`), jangan anggap seluruh tabel = kondisi terkini.

---

## 4. Idempotensi per slot

Jika scrape jam 12 dijalankan 2× (retry), INSERT slot yang sama **di-skip**.
Satu `batch_slot` per program tidak dobel.

Jejak slot (termasuk hasil 0 baris) juga ada di `output/hsecm/slots/`.

---

## 5. Compare vs slot sebelumnya

Setelah scrape slot `T`, bandingkan dengan `T − 6 jam`:

| Metrik | Arti |
|--------|------|
| `curr_count` | Jumlah baris snapshot sekarang |
| `prev_count` | Jumlah baris snapshot sebelumnya |
| `delta` | `curr − prev` |
| `decreased` | `true` jika total turun |
| `resolved_count` | Ada di prev, hilang di curr (comply / keluar trigger) |
| `new_count` | Tidak ada di prev, muncul di curr |
| `still_open_count` | Masih ada di keduanya |
| `status` | `decreased` / `increased` / `unchanged` / `no_previous_slot` |

Ringkasan: `output/hsecm/slot_compare_*.json` dan field `slot_compare` di JSON program.

---

## 6. `gap_count` (streak belum comply)

Per **item** (bukan hanya total program):

| Kondisi di slot sebelumnya | `gap_count` sekarang |
|----------------------------|----------------------|
| Tidak ada (baru / sempat hilang = comply) | **1** (reset) |
| Ada dengan nilai N | **N + 1** |

Item hilang dari Tableau → tidak di-insert di slot itu → dianggap comply → streak putus.

Kolom terkait:

- `business_key` — identitas item (mis. SID+date, Task Number, Code)
- `gap_count` — berapa kali berturut-turut masih muncul

Agregat di `slot_compare`: `max_gap_count`, `gap_count_1`, `gap_count_ge_2`, `gap_count_ge_3`.

```sql
-- Item kritis: sudah gap ≥ 3 di slot terakhir
SELECT business_key, gap_count, *
FROM scr_hsecm_partisipasi_sap_l1_rfid
WHERE batch_slot = '2026-07-23 18:00:00'
  AND gap_count >= 3;
```

---

## 7. Mapping tabel per program

| No | Tabel |
|----|--------|
| 1 | `scr_hsecm_partisipasi_sap_l1_rfid` |
| 2 | `scr_hsecm_coverage_area_kritis_daily` |
| 3 | `scr_hsecm_blindspot_tbc_gr` |
| 4 | `scr_hsecm_overdue_hazard` |
| 5 | `scr_hsecm_submitted_hazard_24jam` |
| 6 | `scr_hsecm_ikk_aktif_ipk_okk` |
| 8 | `scr_hsecm_pengisian_ftw` |
| 9 | `scr_hsecm_ftw_merah` |
| 10 | `scr_hsecm_implementasi_ikk` |
| 11 | `scr_hsecm_pekerja_baru` |

---

## 8. Skema / migrate (manual saja)

Tidak ada auto-migrate dari script Python.

- Tabel baru: `sql/create_scr_hsecm_tables.sql`
- Tabel lama tambah cut-off: `sql/alter_scr_hsecm_add_batch_slot.sql`
- Tabel lama tambah streak: `sql/alter_scr_hsecm_add_gap_count.sql`

Jalankan manual di MySQL setelah konfirmasi.

---

## 9. Cara menjalankan

```bash
python tableau_hsecm_rulebase_to_json.py
```

Env penting:

- `MYSQL_SYNC=true` + kredensial MySQL → tulis ke `scr_hsecm_*`
- `HSECM_SYNC_FROM_JSON=true` → isi MySQL dari JSON existing (tanpa scrape Tableau)
- `HSECM_ONLY_NO=1,4,5` → hanya program tertentu
- `HSECM_BATCH_SLOT=...` → override cut-off

Scheduler disarankan: **00:00, 06:00, 12:00, 18:00**.

---

## 10. Panduan untuk fitur email (konteks Cursor)

Saat membuat pengirim email dari data ini:

1. **Sumber status terkini** = filter `batch_slot` terbaru (atau slot yang dimaksud), **bukan** seluruh tabel.
2. **History / tren** = bandingkan beberapa `batch_slot` atau pakai `slot_compare_*.json`.
3. **Escalation / reminder** = filter `gap_count >= 2` atau `>= 3` pada slot terkini.
4. **Yang sudah comply** = ada di slot sebelumnya, tidak ada di slot sekarang (resolved); biasanya **tidak** perlu diemail sebagai outstanding.
5. Jangan mengasumsikan `COUNT(*)` tabel = jumlah kasus aktif.

Contoh alur email:

```text
Ambil MAX(batch_slot) untuk program X
  → list baris outstanding (gap_count, business_key, detail)
  → bandingkan dengan prev slot (resolved / new) bila perlu narasi
  → kirim email
```

---

## 11. Satu kalimat ringkas

Setiap 6 jam sistem memfoto daftar masalah HSECM week ini, menumpuk fotonya di `scr_hsecm_*` berlabel `batch_slot`, menghitung apakah total berkurang, dan menandai tiap item dengan `gap_count` berapa kali berturut belum comply — data ini siap dipakai untuk email berdasarkan **snapshot slot**, bukan total seluruh baris tabel.
