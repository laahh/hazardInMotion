# Dokumentasi PVT (Psychomotor Vigilance Test)

## 1. Apa itu PVT dan Tujuannya

**PVT (Psychomotor Vigilance Test)** — di aplikasi disebut **"Tes Kewaspadaan Psikomotor"** — adalah tes reaksi sederhana yang mengukur **kecepatan dan konsistensi respons** seseorang terhadap stimulus visual. Tes ini adalah instrumen skrining standar di literatur riset tidur/fatigue untuk mendeteksi penurunan kewaspadaan akibat kurang tidur, kelelahan, atau gangguan konsentrasi.

Dalam konteks aplikasi ini (aplikasi kesehatan karyawan tambang/K3 — PT Berau Coal & grup), PVT dipakai sebagai **skrining cepat "fitness for duty"** sebelum bekerja, terutama untuk pekerja yang:
- Mengoperasikan alat berat / kendaraan
- Bekerja di ketinggian
- Membutuhkan konsentrasi tinggi selama shift

> ⚠️ **Catatan penting dari kode** ([cognitiveFitnessAssessment.js](src/lib/cognitiveFitnessAssessment.js)): ini **bukan alat diagnosis medis**. Ini hanya skrining ringkas; keputusan akhir tetap ada di tangan dokter/petugas kesehatan perusahaan.

PVT digunakan bersama satu tes lain, **Tes Memori Kerja** (working memory / grid pattern), untuk menghasilkan kesimpulan gabungan "layak bekerja" (`evaluateFitnessForDuty`).

---

## 2. Cara Kerja Tes (Mekanisme)

Implementasi utama: [PvtTestContent.jsx](src/components/cognitive/PvtTestContent.jsx)

### Alur satu percobaan (trial)
1. **Fase "wait"** — layar berwarna oranye (`#E85D3D`), teks "Tunggu...". Peserta **tidak boleh** menekan layar.
2. Setelah jeda acak (**ISI – Inter-Stimulus Interval**), layar berubah **hijau** (`#82E05A`) — fase **"stimulus"** — teks "KETUK SEKARANG!".
3. Peserta menekan layar secepat mungkin. Waktu antara stimulus muncul dan ketukan direkam sebagai **RT (Reaction Time)** dalam milidetik, diukur pakai `performance.now()`.
4. Fase **"feedback"** menampilkan RT hasil ketukan (atau "Waktu habis" jika tidak merespons), lalu lanjut ke trial berikutnya.

### Konstanta yang digunakan
| Konstanta | Nilai | Keterangan |
|---|---|---|
| `TRIALS` | 18 | Total percobaan per sesi PVT |
| `LAPSE_MS` | 500 ms | Ambang RT dianggap **lapse** (kelalaian/keterlambatan kognitif) |
| `MAX_WAIT_MS` | 12.000 ms | Batas maksimum menunggu respons; jika lewat → dianggap timeout/lapse otomatis |

### Dua mode timing (ISI & feedback berbeda)
| Parameter | Mode mandiri (`/cognitive-tests/pvt`) | Mode sesi lengkap (chained dgn tes memori) |
|---|---|---|
| ISI minimum | 2.000 ms | 900 ms |
| ISI maksimum | 6.500 ms | 3.200 ms |
| Durasi tampilan feedback | 2.000 ms | 650 ms |
| Durasi "flash" false start | 1.200 ms | 700 ms |

Mode sesi lengkap dipersingkat supaya total waktu tes gabungan (PVT + memori) tetap wajar.

### Kejadian khusus yang dicatat
- **False start**: peserta menekan layar **sebelum** warna berubah hijau (saat fase "wait"). Trial itu tidak dihitung sebagai RT, layar ber-flash merah "Terlalu cepat!", lalu ISI diulang dari awal (bukan menambah trial baru).
- **Timeout/lapse otomatis**: peserta tidak menekan sama sekali dalam `MAX_WAIT_MS` (12 detik) → dicatat sebagai lapse, trial dianggap selesai tanpa nilai RT valid.
- **Lapse berdasar RT**: RT ≥ 500 ms (`LAPSE_MS`) tetap dihitung sebagai respons valid, tapi juga ditandai sebagai lapse (indikasi kewaspadaan menurun).

---

## 3. Cara Menghitung Metrik Hasil

Setelah 18 trial selesai (`finishBlock` di [PvtTestContent.jsx:157-179](src/components/cognitive/PvtTestContent.jsx#L157-L179)):

```js
const rts = rtsRef.current;                                  // semua RT dari tap yang berhasil
const valid = rts.filter((x) => x > 0 && x < MAX_WAIT_MS);    // RT valid (bukan timeout)

payload = {
  trials: 18,
  validTrials: valid.length,
  meanRtMs: mean(valid),      // rata-rata RT valid
  medianRtMs: median(valid),  // median RT valid
  lapses: lapsesRef.current,      // RT >= 500ms ATAU timeout
  falseStarts: falseStartsRef.current, // jumlah tap sebelum sinyal hijau
}
```

### Rumus setiap metrik

| Metrik | Rumus / Definisi |
|---|---|
| **`trials`** | Jumlah percobaan total (selalu 18) |
| **`validTrials`** | Jumlah RT yang valid, yaitu `0 < RT < 12000 ms` (bukan false start, bukan timeout mentah) |
| **`meanRtMs`** | Rata-rata aritmatika seluruh RT valid, dibulatkan (`Math.round(sum/count)`) |
| **`medianRtMs`** | Nilai tengah RT valid setelah diurutkan; jika jumlah genap, dirata-rata dua nilai tengah |
| **`lapses`** | Bertambah setiap kali: (a) RT ≥ 500 ms, **atau** (b) tidak ada respons sama sekali dalam 12 detik (timeout) |
| **`falseStarts`** | Bertambah setiap kali peserta menekan layar saat fase masih "wait" (sebelum hijau) |

Fungsi bantu (di file yang sama):
```js
function mean(arr) { return Math.round(arr.reduce((a,b)=>a+b,0) / arr.length); }
function median(arr) {
  const s = [...arr].sort((a,b)=>a-b);
  const m = Math.floor(s.length/2);
  return s.length % 2 ? s[m] : Math.round((s[m-1]+s[m])/2);
}
```

---

## 4. Kriteria Kelulusan Skrining (`evaluatePvt`)

Sumber: [cognitiveFitnessAssessment.js](src/lib/cognitiveFitnessAssessment.js) — fungsi `evaluatePvt(r)`.

Peserta dinyatakan **"Memenuhi skrining PVT"** hanya jika **keempat** kondisi berikut terpenuhi:

| Kriteria | Ambang | Logika |
|---|---|---|
| Proporsi respons valid | ≥ 78% | `validTrials / trials >= 0.78` |
| Waktu reaksi | mean ≤ 580 ms **dan** median ≤ 560 ms | Keduanya harus lolos |
| Jumlah lapse | ≤ 6 dari 18 trial | `lapses <= 6` |
| Jumlah false start | ≤ 5 | `falseStarts <= 5` |

Jika salah satu gagal, hasilnya **"Di bawah ambang skrining PVT"**, dan alasan kegagalan (`reasonsFail`) dikembalikan sebagai teks penjelasan.

> Ambang ini disengaja dibuat **konservatif** (lebih mudah dianggap "perlu perhatian" daripada meloloskan kondisi berisiko) — bukan standar klinis tunggal.

Hasil PVT ini kemudian dikombinasikan dengan hasil tes memori kerja lewat `evaluateFitnessForDuty(pvtEval, memEval)` menghasilkan salah satu dari 3 level:
- **`layak`** — kedua tes lolos
- **`waspada`** — hanya salah satu tes lolos
- **`tidak_layak`** — kedua tes gagal

---

## 5. Alur Data (Frontend → Backend → Database)

```
PvtTestContent.jsx (UI tes)
   │  hasil 1 sesi trial (payload)
   ▼
appendPvtResult() → localStorage (cognitiveTestStorage.js)
   │  entry ber-id UUID + timestamp "at"
   ▼
syncPvtResultToBackend() (cognitiveTestSync.js)
   │  POST /me/cognitive-tests/pvt   (hanya jika login via API/JWT & user.id numerik)
   ▼
validateBody(cognitivePvtBodySchema)  (server/src/validation/schemas.js)
   │
   ▼
cognitiveTest.controller.js → postPvtResult
   ▼
cognitiveTest.service.js → savePvtResult()
   │  memastikan userId valid (employee_profiles.id)
   ▼
cognitiveTest.repository.js → upsertPvtResult()
   │  INSERT ... ON DUPLICATE KEY UPDATE
   ▼
Tabel MySQL: cognitive_pvt_results
```

Catatan penting:
- **Data disimpan dulu di `localStorage`** perangkat (key dari [storageKeys.js](src/lib/storageKeys.js), fungsi `getCognitiveUserKey`), sehingga tes tetap bisa dipakai tanpa akun/backend.
- **Sinkronisasi ke MySQL bersifat opsional** — hanya berjalan jika user login lewat backend Node (JWT) dan `user.id` berupa angka (id karyawan di `employee_profiles`). Lihat `shouldSyncCognitiveToBackend()`.
- Upsert memakai `client_id` (UUID dari device) sebagai kunci unik per user, jadi retry sinkron tidak menghasilkan duplikat baris.

---

## 6. Tabel Database yang Digunakan

Skema sumber: [mysql/migrations/004_cognitive_tests.sql](mysql/migrations/004_cognitive_tests.sql) (sudah live di dump [databaseweel.sql](databaseweel.sql), skema database `bewell`).

Ada **3 tabel** yang terlibat dalam ekosistem PVT:

### 6.1 `cognitive_pvt_results` — hasil PVT per sesi tunggal

Tabel utama tempat setiap kali seseorang menyelesaikan 18 trial PVT.

| Kolom | Tipe | Keterangan Detail |
|---|---|---|
| `id` | BIGINT, AUTO_INCREMENT, PK | ID baris internal database |
| `user_id` | BIGINT, NOT NULL | FK → `employee_profiles.id`. Pemilik hasil tes (ON DELETE CASCADE — terhapus otomatis jika karyawan dihapus) |
| `client_id` | VARCHAR(64), NOT NULL | UUID yang dibuat di **sisi aplikasi/device** (`crypto.randomUUID()`) saat trial selesai. Dipakai sebagai kunci upsert agar sinkron ulang tidak duplikat |
| `session_id` | VARCHAR(64), NULL | UUID sesi **gabungan** (PVT + memori) bila tes ini dijalankan sebagai bagian dari sesi lengkap, bukan tes PVT berdiri sendiri. Menghubungkan baris ini ke `cognitive_test_sessions.session_id` |
| `trials` | INT, default 0 | Jumlah total percobaan (selalu 18 pada implementasi saat ini) |
| `valid_trials` | INT, default 0 | Jumlah percobaan dengan RT valid (0 < RT < 12000 ms) |
| `mean_rt_ms` | INT, default 0 | Rata-rata waktu reaksi (ms) dari trial valid |
| `median_rt_ms` | INT, default 0 | Median waktu reaksi (ms) dari trial valid |
| `lapses` | INT, default 0 | Jumlah kelalaian: RT ≥ 500 ms atau tidak ada respons sama sekali |
| `false_starts` | INT, default 0 | Jumlah ketukan yang terjadi sebelum sinyal hijau muncul |
| `passed` | TINYINT(1), NULL | Hasil evaluasi skrining: `1` = lulus, `0` = tidak lulus, `NULL` = belum/tidak dinilai |
| `evaluation_label` | VARCHAR(512), NULL | Teks label hasil evaluasi, misalnya `"Memenuhi skrining PVT"` atau `"Di bawah ambang skrining PVT"` |
| `raw_payload` | JSON, NULL | Salinan mentah seluruh payload yang dikirim dari client (termasuk field yang tidak punya kolom sendiri), untuk audit/debug |
| `tested_at` | DATETIME(3), default NOW | Waktu tes **dilakukan** oleh user (dari timestamp client, field `at`) |
| `created_at` | DATETIME(3), default NOW | Waktu baris pertama kali dibuat di database (server-side) |

**Index & constraint:**
- `UNIQUE KEY uq_cognitive_pvt_user_client (user_id, client_id)` → mencegah duplikat hasil yang sama untuk user yang sama, sekaligus dasar untuk `ON DUPLICATE KEY UPDATE` (upsert).
- `KEY idx_cognitive_pvt_user_tested (user_id, tested_at DESC)` → mempercepat query "riwayat tes terbaru milik user X".
- `KEY idx_cognitive_pvt_session (user_id, session_id)` → mempercepat pencarian hasil PVT dalam satu sesi gabungan tertentu.
- `FOREIGN KEY fk_cognitive_pvt_user (user_id) REFERENCES employee_profiles(id) ON DELETE CASCADE`.

**Contoh data (dari dump produksi):**
```
id=1, user_id=116750, client_id='1458c5b6-...', session_id='b0fbc9ef-...',
trials=18, valid_trials=18, mean_rt_ms=424, median_rt_ms=403,
lapses=2, false_starts=0, passed=1, evaluation_label='Memenuhi skrining PVT'
```

### 6.2 `cognitive_test_sessions` — ringkasan sesi gabungan (PVT + Memori)

Tabel ini menyimpan **kesimpulan** satu sesi lengkap (bukan detail trial), dipakai saat PVT dan Tes Memori dijalankan berurutan dalam satu alur ("fitness for duty check").

| Kolom | Tipe | Keterangan Detail |
|---|---|---|
| `id` | BIGINT, AUTO_INCREMENT, PK | ID baris internal |
| `user_id` | BIGINT, NOT NULL | FK → `employee_profiles.id` |
| `session_id` | VARCHAR(64), NOT NULL | UUID sesi, dibuat di client. Sama dengan nilai `session_id` pada baris `cognitive_pvt_results` dan `cognitive_memory_results` terkait |
| `overall_level` | VARCHAR(32), default '' | Salah satu dari: `layak`, `waspada`, `tidak_layak` — hasil `evaluateFitnessForDuty()` |
| `overall_json` | JSON, NOT NULL | Objek lengkap kesimpulan: `{ level, title, subtitle, color, recommendations[] }` |
| `pvt_json` | JSON, NULL | Snapshot hasil PVT pada sesi ini: `{ raw: {...hasil mentah}, evaluation: {...hasil evaluatePvt} }` |
| `memory_json` | JSON, NULL | Snapshot hasil tes memori pada sesi ini, format serupa `pvt_json` |
| `tested_at` | DATETIME(3), default NOW | Waktu sesi dilakukan (dari client) |
| `created_at` | DATETIME(3), default NOW | Waktu baris dibuat di server |

**Index & constraint:**
- `UNIQUE KEY uq_cognitive_session_user (user_id, session_id)` → basis upsert per sesi.
- `KEY idx_cognitive_session_user_tested (user_id, tested_at DESC)`.
- `FOREIGN KEY fk_cognitive_session_user → employee_profiles(id) ON DELETE CASCADE`.

### 6.3 `cognitive_memory_results` — hasil tes memori kerja (pendukung, bukan PVT)

Disertakan di sini karena selalu tampil berpasangan dengan PVT dalam satu sesi, dan berbagi `session_id` yang sama.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id`, `user_id`, `client_id`, `session_id` | — | Sama fungsinya seperti pada `cognitive_pvt_results` |
| `rounds` | INT | Jumlah babak tes memori (default 6) |
| `rounds_correct` | INT | Jumlah babak yang dijawab benar |
| `max_span` | INT | Kompleksitas pola maksimal (jumlah sel aktif) yang berhasil dijawab benar |
| `sum_correct_lengths` | INT | Total panjang pola dari semua babak yang benar |
| `score` | INT | Skor gabungan = `roundsCorrect × 20 + sumCorrectLengths` (skor lama, lihat komentar di kode) |
| `passed`, `evaluation_label`, `raw_payload`, `tested_at`, `created_at` | — | Sama fungsinya seperti pada `cognitive_pvt_results` |

---

## 7. Relasi Antar Tabel (ERD Ringkas)

```
employee_profiles (id) ──1:N── cognitive_pvt_results (user_id)
employee_profiles (id) ──1:N── cognitive_memory_results (user_id)
employee_profiles (id) ──1:N── cognitive_test_sessions (user_id)

cognitive_test_sessions.session_id  ←── (opsional, sama nilainya) ──  cognitive_pvt_results.session_id
cognitive_test_sessions.session_id  ←── (opsional, sama nilainya) ──  cognitive_memory_results.session_id
```

Tidak ada FK formal antara `session_id` di tiga tabel tersebut (hanya kesamaan nilai UUID) — relasi ini bersifat logis/aplikasi, bukan constraint database.

---

## 8. Ringkasan File Terkait

| File | Peran |
|---|---|
| [src/components/cognitive/PvtTestContent.jsx](src/components/cognitive/PvtTestContent.jsx) | UI & logika jalannya tes PVT, kalkulasi metrik hasil |
| [src/lib/cognitiveFitnessAssessment.js](src/lib/cognitiveFitnessAssessment.js) | Kriteria skrining/evaluasi PVT & memori, kesimpulan fitness-for-duty |
| [src/lib/cognitiveTestStorage.js](src/lib/cognitiveTestStorage.js) | Penyimpanan hasil di `localStorage` device |
| [src/lib/cognitiveTestSync.js](src/lib/cognitiveTestSync.js) | Sinkronisasi hasil ke backend (API Node) |
| [server/src/validation/schemas.js](server/src/validation/schemas.js) | Skema validasi payload PVT (`cognitivePvtBodySchema`) |
| [server/src/controllers/cognitiveTest.controller.js](server/src/controllers/cognitiveTest.controller.js) | Endpoint HTTP `POST /me/cognitive-tests/pvt` |
| [server/src/services/cognitiveTest.service.js](server/src/services/cognitiveTest.service.js) | Validasi user & orkestrasi penyimpanan |
| [server/src/repositories/cognitiveTest.repository.js](server/src/repositories/cognitiveTest.repository.js) | Query SQL upsert ke MySQL |
| [mysql/migrations/004_cognitive_tests.sql](mysql/migrations/004_cognitive_tests.sql) | DDL tabel `cognitive_pvt_results`, `cognitive_memory_results`, `cognitive_test_sessions` |
| [src/components/cognitive/CognitiveTestResultsContent.jsx](src/components/cognitive/CognitiveTestResultsContent.jsx) | Halaman riwayat hasil tes (tampil dari localStorage) |
