# Dokumentasi Teknis: Deteksi Long Shift RFID / SQL Database

**Versi:** 1.0  
**Tanggal:** 22 Oktober 2026  
**Database:** PostgreSQL 13+  
**Zona waktu:** WITA (UTC+08), `Asia/Makassar`

Metode identifikasi karyawan yang melanjutkan pekerjaan ke shift berikutnya dari rangkaian RFID `IN`–`OUT` yang berulang, termasuk lintas tengah malam.

**Fokus pengolahan:** Normalisasi event RFID, pembentukan sesi, kontinuitas jeda, irisan jadwal, klasifikasi Long Shift, dan kontrol kualitas data.

## 1. Definisi operasional

**Long Shift berarti kelanjutan ke shift berikutnya, bukan sekadar durasi hadir yang panjang.**

### 1.1 Pola shift

| Shift | Periode | Lintas hari? |
| --- | --- | --- |
| Shift 1 / Siang | 07:00–19:00 | Tidak |
| Shift 2 / Malam | 19:00–07:00 | Ya, tanggal berikutnya |

### 1.2 Syarat status `LONG_SHIFT`

1. Ada kehadiran selama shift yang dijadwalkan **minimal 360 menit**.
2. Blok kehadiran yang sama memiliki aktivitas sebelum akhir shift dan berlanjut setelahnya.
3. Jeda antarsesi dalam blok **maksimal 60 menit**.
4. Kehadiran dalam 12 jam shift berikutnya **minimal 30 menit**.

### 1.3 Parameter yang dapat diubah

| Parameter SQL | Nilai | Makna |
| --- | --- | --- |
| `duplicate_window` | 2 menit | Scan identik berdekatan |
| `max_continuity_gap` | 60 menit | Batas jeda OUT ke IN |
| `max_valid_pair` | 36 jam | Batas validasi pasangan |
| `min_shift_minutes` | 360 menit | Kehadiran minimal dalam shift |
| `min_next_shift_minutes` | 30 menit | Kehadiran setelah akhir shift |

> **Catatan:** Nilai di atas merupakan kebijakan contoh untuk algoritma. Durasi yang dihitung adalah kehadiran berdasarkan RFID, bukan bukti langsung bahwa seluruh waktu tersebut dipakai bekerja.

## 2. Struktur data dan kualitas RFID

Tabel jadwal wajib tersedia agar sistem mengenali shift yang seharusnya dijalankan.

### 2.1 Kontrak data minimal

| Tabel | Kolom utama | Fungsi |
| --- | --- | --- |
| `rfid_events` | `employee_id` | Identitas karyawan |
| `rfid_events` | `event_time` | Tanggal + jam event (timezone) |
| `rfid_events` | `event_type` | `IN` atau `OUT` |
| `shift_schedule` | `shift_start` | Mulai shift terjadwal |
| `shift_schedule` | `shift_end` | Akhir shift terjadwal |

### 2.2 Contoh event karyawan E001

| Tanggal / waktu (WITA) | Event | Keterangan |
| --- | --- | --- |
| 20 Okt 07:00 | IN | Mulai shift 1 |
| 20 Okt 07:01 | IN | Duplikasi scan 1 menit |
| 20 Okt 12:00 | OUT | Keluar area |
| 20 Okt 13:00 | IN | Kembali, jeda 60 menit |
| 20 Okt 19:00 | OUT | Batas akhir shift terjadwal |
| 20 Okt 19:30 | IN | Kembali, jeda 30 menit |
| 21 Okt 01:00 | OUT | Selesai pada shift berikutnya |

> **Penting untuk data nyata:** Urutkan dengan `employee_id`, `event_time`, `id` untuk hasil deterministik. Pasangan `IN → OUT` yang valid menjadi satu sesi; event hilang, scan berurutan yang tidak wajar, dan durasi ekstrem harus masuk daftar anomali untuk ditinjau.

## 3. Algoritma deteksi

Pipeline dilakukan per karyawan dan menggunakan **timestamp lengkap**, bukan pemotongan per tanggal.

1. **SORT & DEDUP:** Urutkan semua event; buang scan sejenis dalam 2 menit.
2. **PAIR EVENTS:** Pasangkan event `IN` yang langsung diikuti `OUT`; validasi durasi.
3. **BUILD CONTINUOUS BLOCK:** Sambungkan sesi ketika jeda `OUT` ke `IN` ≤ 60 menit.
4. **MATCH SCHEDULE:** Cari irisan sesi dengan shift terjadwal karyawan.
5. **MEASURE NEXT SHIFT:** Hitung kehadiran blok di `[shift_end, shift_end + 12 jam)`.
6. **CLASSIFY:** Terbitkan `LONG_SHIFT` apabila semua syarat terpenuhi.

### 3.1 Rumus perhitungan irisan waktu

```text
overlap = max(0, min(OUT, akhir) - max(IN, mulai))
```

Hasil dihitung per sesi, lalu dijumlahkan per shift / blok.

**Contoh:** Sesi 19:30–01:00 adalah 330 menit pada shift berikutnya untuk karyawan Shift 1 (berakhir pukul 19:00). Pada shift malam, rentang 19:00–07:00 besok adalah satu interval jadwal yang utuh.

## 4. Contoh hasil dan skenario uji

Ekspektasi berikut mengikuti data demo yang disertakan di dalam file SQL.

### 4.1 Hasil klasifikasi

| ID | Shift terjadwal | Menit di shift | Menit lanjut | Hasil |
| --- | --- | ---: | ---: | --- |
| E001 | 20 Okt 07:00–19:00 | 660 | 330 | `LONG_SHIFT` |
| E002 | 20 Okt 19:00–07:00 | 660 | 0 | `NORMAL` |
| E003 | 20 Okt 07:00–19:00 | 720 | 60* | `NORMAL` |
| E004 | 20 Okt 19:00–07:00 | 720 | 150 | `LONG_SHIFT` |

*E003* memiliki kehadiran 60 menit setelah shift, tetapi berada dalam blok terpisah karena jeda OUT 19:00 ke IN 22:00 adalah 180 menit.

### 4.2 Skenario uji minimal

| Skenario | Harapan sistem |
| --- | --- |
| IN-OUT normal pada shift malam | Normal; lintas tanggal bukan Long Shift |
| OUT 19:00, IN 19:30 hingga 01:00 | Long Shift bila syarat lain terpenuhi |
| OUT 19:00, IN 22:00 hingga 23:00 | Bukan kelanjutan (jeda > 60 menit) |
| Duplikasi IN dalam 2 menit | Abaikan duplikat untuk pembentukan sesi |
| IN tanpa OUT | Flag `DATA_ANOMALY` / perlu validasi |
| Jadwal shift tidak ditemukan | Tidak dapat diklasifikasikan dengan pasti |

> **Interpretasi:** Output SQL demo hanya mengembalikan baris `LONG_SHIFT`. Untuk dashboard seluruh karyawan, jadwal perlu di-`LEFT JOIN` dengan hasil deteksi agar status `NORMAL`, `NO_DATA`, dan `DATA_ANOMALY` juga terlihat.
