# Safety Roster — Dokumentasi Perhitungan & Rumus

Hasil pembedahan aplikasi **Kepatuhan Roster Karyawan** di <https://safety-roster.vercel.app/>.

| Item | Keterangan |
|---|---|
| Arsitektur | Satu file HTML statis (~64 KB), logika di blok `<script type="text/plain" id="app">` |
| Data utama | `d/cache.json` (18,6 MB) — pola roster pra-hitung per karyawan |
| Data delta | `d/daily.json` — hari-hari baru yang di-append di sisi browser saat load |
| Fungsi inti | `compute(sid, nama, jab, pola, M, r0, r1)` |
| Snapshot data | `Last-Modified: 30 Sep 2026 02:10 UTC` |
| Tanggal audit | 30 September 2026 |

Dokumen ini menjelaskan: (1) cara data gate diubah jadi pola harian, (2) enam rule yang diuji, (3) rumus setiap kolom dan kartu, (4) parameter per perusahaan, dan (5) temuan inkonsistensi antara label UI dan kode.

---

## 1. Populasi & Sumber Data

Sumber tunggal: **scan gate** (tabel working permit register). Hanya scan berstatus `PASSED` yang dipakai.

Kriteria masuk populasi:

- Jabatan operator / driver / mekanik lapangan
- Status karyawan AKTIF
- Working Permit **PASSED**
- SIMPER **aktif**

Site yang dicakup: BMO 1, BMO 2, BMO 3, GMO, LMO, SMO.

Jabatan struktural (±150 varian) dipetakan ke 4 kategori lewat tabel `jabcat`:

| Kategori | Perlakuan rule |
|---|---|
| Operator A2B | Regulasi penuh |
| Operator Hauler | Regulasi penuh |
| Operator Transportasi Massal | **Regulasi longgar** |
| Mekanik | **Regulasi longgar** |

"Regulasi longgar" (`isLonggar()`) = dibebaskan dari REG-1, REG-2, REG-3, dan MAP-1. Semua kondisi rule tersebut dijaga dengan `&& !LG`.

---

## 2. Pembentukan Pola Harian

Satu karyawan = satu string, **1 karakter = 1 hari kalender**.

### 2.1 Klasifikasi harian

Dari `CHECK IN pertama` pada hari tersebut:

```
menit_checkin < 720  (sebelum 12:00)   →  'P'   Shift Pagi
menit_checkin ≥ 720  (12:00 atau lebih) →  'M'   Shift Malam
tidak ada CHECK IN                      →  'o'   Off
```

Kode di loader:

```js
row[3] += e ? (e[1] < 720 ? 'P' : 'M') : 'o';
```

### 2.2 Normalisasi Off → Cuti (`toC`)

```
run 'o' dengan panjang  > 5 hari  →  seluruh run diubah jadi 'c'  (Cuti)
run 'o' dengan panjang 1–5 hari   →  tetap 'o', tetap dihitung ON-SITE
```

Artinya **Cuti = ≥6 hari berturut-turut tanpa check-in**, dan off pendek **tidak** memutus hitungan on-site.

Contoh dari halaman Rule aplikasi:

```
mentah  : PPPPPPoMMMMMMoPPoooooooPPP
setelah : PPPPPPoMMMMMMoPPcccccccPPP
                          └──────┘ 7 hari off → jadi Cuti
```

### 2.3 Nomor roster / cycle

```js
let cyc = 1;
for (i = 0; i < n; i++) {
  rosterAt[i] = cyc;
  if (p[i] === 'c' && (i + 1 >= n || p[i + 1] !== 'c')) cyc++;
}
```

Roster dimulai dari 1 dan naik +1 **setiap satu blok cuti selesai**. Satu roster = rangkaian on-site sampai blok cuti penutupnya selesai.

---

## 3. Rule Engine

Enam rule: tiga **merah** (pelanggaran regulasi fatigue) dan tiga **kuning** (tidak sesuai mapping desain roster — sifatnya peringatan). Bila satu hari kena merah dan kuning sekaligus, yang ditampilkan merah.

### 3.1 Ringkasan

| Kode | Label di UI | Kondisi di kode | Cakupan | Longgar dikecualikan |
|---|---|---|---|---|
| REG-1 | Kerja beruntun ≥14 hr | run `P`/`M` tanpa `o`/`c`, `len > 13` | Seluruh timeline (YTD) | Ya |
| REG-2 | On-site >70 hr belum cuti | run non-`c` (P+M+o), `len > 71` | YTD | Ya |
| REG-3 | Cuti <14 hr | run `c`, `len < 12` | YTD | Ya |
| MAP-1 | Melebihi blok roster | run kerja `len > thr-1` tapi belum ≥14 | Per PT | Ya |
| MAP-2 | Wajib OFF saat ganti shift | `map==='pama'`: ada P→M / M→P dalam satu run kerja | PAMA saja | Tidak |
| MAP-3 | Urutan shift terbalik | non-PAMA: muncul `P` setelah `M` dalam satu run kerja | PT lain | Tidak |

### 3.2 REG-1 — Kerja beruntun

```js
// run kerja = deretan P/M tanpa satu pun 'o' atau 'c'
if (len > 13 && !LG) {
  f1 = true;
  for (k = i; k < j; k++) {
    red[k] = 'Kerja beruntun ' + len + ' hr (≥14) — PELANGGARAN';
    dayno[k] = k - i + 1;   // counter hari ke-N dalam run
  }
}
```

Seluruh hari dalam run ditandai merah, bukan hanya hari ke-14 ke atas.

### 3.3 REG-2 — On-site tanpa cuti

```js
// run on-site = deretan karakter apa pun selain 'c'  → P, M, dan off pendek
if (len > 71 && !LG) {
  f2 = true;
  for (k = i; k < j; k++)
    red[k] = 'On-site ' + len + ' hr belum cuti (>70) — PELANGGARAN';
}
```

Off 1–5 hari **ikut dihitung** sebagai hari on-site. Yang memutus run hanya blok cuti (≥6 hari).

### 3.4 REG-3 — Cuti terlalu pendek

```js
// run 'c'
if (len < 12 && !LG) {
  f3 = true;
  for (k = i; k < j; k++)
    red[k] = 'Cuti ' + len + ' hr (<14) — PELANGGARAN';
}
```

### 3.5 MAP-1/2/3 — Mapping desain roster

```js
if (len > maxblock && !LG)              // maxblock = M.thr - 1
  yel[k] = 'Kerja beruntun ' + len + ' hr — melebihi blok roster (' + maxblock + ')';

if (M.map === 'pama') {                 // MAP-2
  if (p[k] !== p[k-1]) yel[k] = 'Wajib OFF saat ganti shift (Pagi↔Malam)';
} else {                                // MAP-3
  if (p[k] === 'P' && sudahAdaMalam) yel[k] = 'Urutan shift tak sesuai (Pagi setelah Malam)';
}
```

Ketiganya hanya dievaluasi di cabang `else` — yaitu **hanya bila run kerja belum kena REG-1** (`len <= 13`).

---

## 4. Parameter per Perusahaan

Ambang REG-1/2/3 **seragam** untuk semua PT. Nilai `thr` per PT hanya dipakai MAP-1.

| PT | Nama lengkap | Roster | Pola shift | `thr` | Blok MAP-1 | `map` |
|---|---|---|---|---|---|---|
| PAMA | PT Pamapersada Nusantara | 10:2 Minggu | 6:1 6:1 | 7 | >6 hr | pama |
| MTL | PT Mutiara Tanjung Lestari | 10:2 Minggu | 6:6:1 | 13 | >12 hr | block |
| MTN | PT Madhani Talatah Nusantara | 10:2 Minggu | 6:6:1 | 13 | >12 hr | block |
| BUMA | PT Bukit Makmur Mandiri Utama | 10:2 Minggu | 3:3:1 | 7 | >6 hr | block |
| KDC | PT Kaltim Diamond Coal | 10:2 Minggu | 7:6:1 | 14 | >13 hr | block |
| BAR | PT Bumi Artlantis Raya | 70:12 hari | 3:3:1 | 7 | >6 hr | block |
| FAD | PT Fajar Anugerah Dinamika | 10:2 Minggu | 3:3:1 | 7 | >6 hr | block |
| lain-lain | Majau Inti Jaya, Bagong Dekaka Makmur, Puncak Makmur Jaya, Serasi Autoraya, Koperasi Pamandiri, Ambar Borneo, Transkon Jaya, United Tractors | — | — | 7 | >6 hr | block |

---

## 5. Rumus Kolom Tabel

Semua dihitung di `compute()` dan dikembalikan sebagai properti objek baris.

### 5.1 Roster berjalan (dibatasi `rosterAt[i] === curCyc`)

```
pagi     = jumlah hari 'P' pada roster berjalan
malam    = jumlah hari 'M' pada roster berjalan
off      = jumlah hari 'o' pada roster berjalan
cutiCur  = jumlah hari 'c' pada roster berjalan (= panjang cuti yang sedang berjalan)

On-site  = hadir = pagi + malam + off        ← cuti tidak dihitung
onNoOff  = run P/M terpanjang tanpa off di roster berjalan
           → kolom "Shift kerja maks (roster kini)"
```

### 5.2 YTD — lepas dari filter periode

Dihitung atas seluruh array `[0, n)`, jadi **tidak berubah** walau periode difilter.

```
onAll   = max( len run non-'c' )    → kolom "On-site maks (all roster)"
cutiMin = min( len run 'c' )        → kolom "Cuti min (all roster)"
```

Keduanya juga menyimpan nomor roster dan tanggal mulai–selesai run pemenangnya (`onAllR/onAllS/onAllE`, `cutiMinR/cutiMinS/cutiMinE`) untuk ditampilkan sebagai sub-label.

### 5.3 On-site berjalan

```js
let onCur = 0, k = r1;
while (k >= 0 && p[k] !== 'c') { onCur++; k--; }
```

Hitung balik dari hari terakhir periode sampai ketemu cuti. Ini dasar penentuan **Wajib cuti**.

### 5.4 Status hari terakhir periode

```
p[r1] === 'c'                        →  Cuti
p[r1] === 'o'                        →  Off
run kerja beruntun sampai r1 ≥ 8     →  Overshift
p[r1] === 'P'                        →  Shift Pagi
p[r1] === 'M'                        →  Shift Malam
```

### 5.5 Durasi harian (popup timeline)

```js
function fmtDur(ci, co) {
  let d = co - ci;
  if (d < 0) d += 1440;        // shift malam melewati tengah malam
  return Math.floor(d/60) + 'j ' + (d%60) + 'm';
}
```

---

## 6. Rumus Kartu Ringkasan

```
total              = jumlah baris setelah filter aktif
Pelanggaran (YTD)  = COUNT(orang) dengan everRed = true       ← jumlah ORANG, bukan hari
  ├─ On-site >70   = COUNT(redK2)   (kondisi sebenarnya: onAll > 71)
  ├─ Cuti <14      = COUNT(redK3)   (kondisi sebenarnya: cutiMin < 12)
  └─ Shift ≥14     = COUNT(redK1)
Wajib cuti         = COUNT(onCur > 71 && !longgar)
Overshift (kini)   = COUNT(status === 'Overshift')
Cuti (kini)        = COUNT(status === 'Cuti')
Off (kini)         = COUNT(status === 'Off')

persen = (x / total) × 100, satu desimal
```

Ringkasan per site memakai rumus yang sama, diagregasi per site: `tot`, `pel` (everRed), `waj` (onCur > 71).

---

## 7. Periode & Filter

| Elemen | Pengaruh |
|---|---|
| Kuartal / Bulan / Minggu / tanggal bebas | Mengubah `[RI0, RI1]` → memengaruhi Status, kolom roster berjalan, Notes, timeline |
| On-site maks & Cuti min | **Tidak terpengaruh** — selalu YTD |
| Pelanggaran (YTD) | **Tidak terpengaruh** — `everRed` atas seluruh timeline |
| Notes | Hanya flag yang jatuh di dalam `[r0, r1]` |

Definisi periode: Q1 = Jan–Mar, Q2 = Apr–Jun, Q3 = Jul–Sep. Minggu baru dimulai setiap hari **Senin** (`getUTCDay() === 1`), dinomori sejak awal tahun.

---

## 8. Temuan — Selisih Label UI vs Kode

Lima hal berikut bukan soal gaya penulisan, tapi perbedaan nyata antara apa yang tertulis di UI/halaman Rule dan apa yang dijalankan kode.

### T-1 · REG-2 off-by-one (ambang efektif 72, bukan 71)

Label: "On-site **>70** hr". Kode: `len > 71` → merah baru muncul di hari ke-**72**. On-site 71 hari lolos tanpa flag.

Berlaku konsisten di tiga tempat:

| Lokasi | Kode |
|---|---|
| Rule REG-2 | `if (len > 71 && !LG)` |
| Badge kolom On-site maks | `bO = r.onAll > 71 && !r.longgar` |
| Kartu & filter Wajib cuti | `r.onCur > 71 && !r.longgar` |

**Perbaikan:** bila ambang regulasi memang >70, ketiganya jadi `> 70`.

### T-2 · REG-3 memakai ambang 12, tapi berlabel 14

Kode: `len < 12`. Label kartu: "**Cuti <14 hr**". Akibatnya cuti 12–13 hari **tidak** merah padahal menurut label termasuk pelanggaran.

Petunjuk bahwa ini bug, bukan desain: di `recompute()` sudah ada penghitung dengan ambang yang benar tapi tidak dipakai di kartu mana pun —

```js
if (!r.longgar && r.cutiMin > 0 && r.cutiMin < 14) npc14++;   // tidak dirender
if (!r.longgar && r.cutiMin > 0 && r.cutiMin < 12) npc12++;   // tidak dirender
```

Badge kolom Cuti min juga memakai 12: `bC = r.cutiMin && r.cutiMin < 12 && !r.longgar`.

**Perbaikan:** samakan — `len < 14` di rule dan `< 14` di badge, atau ubah label jadi "<12 hr".

### T-3 · Seluruh flag kuning (MAP-1/2/3) tidak pernah tampil

Di `compute()`, array `yel` dikosongkan tepat sebelum dibaca:

```js
yel.fill(''); let hasRed = false, hasYel = false;
for (i = r0; i <= r1; i++) { if (red[i]) hasRed = true; if (yel[i]) hasYel = true; }
```

Konsekuensinya:

- `cats.map` selalu `false` → badge "Tidak sesuai mapping" di kolom Notes tidak pernah muncul
- `everYel` selalu `false` → penghitung `nmap` selalu 0
- `heat()` tidak pernah menggambar strip kuning di timeline
- Filter "Tidak sesuai mapping" tidak akan pernah mengembalikan baris

Padahal MAP-1/2/3 masih didokumentasikan lengkap di halaman **Rule & Glosarium**, termasuk kolom "Dampak di dashboard".

**Perbaikan:** kalau `yel.fill('')` adalah kill-switch sementara, beri catatan di halaman Rule; kalau tidak, hapus baris tersebut.

### T-4 · MAP-1 mati secara logika untuk KDC

MAP-1 hanya dievaluasi bila run kerja `len <= 13` (cabang `else` dari REG-1), sedangkan ambangnya `len > thr - 1`.

| PT | `thr` | Ambang MAP-1 | Rentang yang benar-benar bisa menyala |
|---|---|---|---|
| KDC | 14 | >13 | **kosong** — `len > 13` selalu sudah ditangkap REG-1 |
| MTL / MTN | 13 | >12 | hanya `len = 13` |
| lain | 7 | >6 | `len = 7…13` |

Untuk KDC, MAP-1 tidak akan pernah aktif. Untuk MTL/MTN hanya menangkap satu nilai panjang run.

### T-5 · Bias sensor data di batas tahun

`onAll` dan `cutiMin` dihitung dari run yang ada **di dalam** array saja:

- Run on-site yang **mulai sebelum 1 Januari** terpotong di hari pertama data → panjangnya kurang dari kenyataan → **pelanggaran REG-2 bisa terlewat**
- Blok cuti yang **masih berjalan** di hari terakhir data dihitung sebagai blok selesai yang pendek → **REG-3 palsu**

Digabung dengan asumsi eksplisit aplikasi, risiko false positive naik:

> Karyawan tap gate saat awal & akhir cuti (mess di area site); hari tanpa scan dianggap off/cuti sehingga scan yang terlewat bisa memunculkan flag palsu — verifikasi flag merah sebelum tindakan. Rule berbasis jam (durasi shift, jeda istirahat) belum diterapkan.

### Catatan tambahan

- **Cakupan REG-1 vs badge kolom.** `redK1` dihitung YTD (seluruh timeline), tapi badge ⚠ pada kolom "Shift kerja maks" memakai `onNoOff >= 14` yang cakupannya **hanya roster berjalan**. Untuk orang yang pelanggarannya terjadi di roster lama, angka kartu dan badge kolom tidak sinkron.
- **Kode mati.** `npel3b` dan `ns1..ns4` dihitung di `recompute()` lalu disimpan ke `AGG`, tapi tidak dirender di mana pun. `ns1..ns4` adalah 4 skenario kombinasi ambang (70/71 × 12/14) — sisa eksperimen sensitivitas ambang:

  ```js
  var _o70 = !r.longgar && r.onAll > 70,  _o71 = !r.longgar && r.onAll > 71,
      _c14 = !r.longgar && r.cutiMin > 0 && r.cutiMin < 14,
      _c12 = !r.longgar && r.cutiMin > 0 && r.cutiMin < 12, _sh = r.redK1;
  if (_sh || _o70 || _c14) ns1++;   if (_sh || _o71 || _c14) ns2++;
  if (_sh || _o70 || _c12) ns3++;   if (_sh || _o71 || _c12) ns4++;
  ```

- **`D.companies[r.co].cutimin`** dibaca di `render()` ke variabel `CU`, tapi `CU` tidak pernah dipakai — dan key `cutimin` tidak ada di `cache.json` (yang ada: `full`, `roster`, `thr`, `map`, `sites`), jadi nilainya `undefined`.

---

## 9. Ringkasan Ambang

| Konsep | Label UI | Kode aktual | Sinkron? |
|---|---|---|---|
| Off → Cuti | off >5 hari | `> 5` | ✔ |
| Kerja beruntun (REG-1) | ≥14 hr | `> 13` | ✔ |
| On-site tanpa cuti (REG-2) | >70 hr | `> 71` | ✘ T-1 |
| Cuti minimum (REG-3) | <14 hr | `< 12` | ✘ T-2 |
| Wajib cuti | >70 hr berjalan | `onCur > 71` | ✘ T-1 |
| Overshift | ≥8 hr beruntun | `run >= 8` | ✔ |
| Blok roster (MAP-1) | `thr` per PT | `> thr - 1` | ✔ (tapi lihat T-3, T-4) |

---

## 10. Implementasi Live di Aplikasi Ini

Metodologi di atas diterapkan sebagai dashboard live di **`/dms/roster-compliance`** — pola roster dirakit dari scan RFID, bukan dari snapshot JSON. Halaman snapshot statis (`/dms/roster-compliance-static`) tetap ada sebagai pembanding validasi.

### Sumber data

| Kebutuhan | Tabel | Ukuran |
|---|---|---|
| Scan gate RFID | `bcsid.mv_checkinout_rfid` (Postgres OLAP) | 4,59 juta baris / 758 MB, terindeks `kode_sid` + `tanggal_checkinout` |
| Populasi karyawan | `bcsid.bep_vw_safety_karyawan_aktif` | 22.810 baris (22.144 SID unik) |

Populasi roster = jabatan operator/driver/mekanik, `status_karyawan = 'AKTIF'`, `status_permit = 'PASSED'`.

View ini dipilih, bukan `crontable_bep_vw_m_karyawan_aktif`, karena cron table punya filter tak terdokumentasi yang diam-diam membuang sebagian karyawan aktif (mis. staf HO) — lihat catatan di `app/Models/OhsDashboard/Employee.php`.

**Jebakan kinerja:** view ini rename tipis di atas `bep_vw_m_karyawan_aktif` yang bersumber dari `m_karyawan` (6 GB). Menaruh `WHERE` apa pun membuat predikat terdorong sampai ke tabel dasar dan query melewati 30 detik, sedangkan `SELECT` tanpa `WHERE` selesai cepat. Karena itu penyaringan status/jabatan dikerjakan di PHP, dan 666 `kode_sid` ganda di view di-dedup di sana juga (baris pertama menang).

### Alur data

```
bcsid.mv_checkinout_rfid
   │  agregasi per (kode_sid, tanggal): check-in pertama, check-out terakhir, gate
   ▼
dms_roster_rfid_days        ← fakta harian (gate & jam untuk panel detail)
   │  kompilasi: 1 karakter per hari, karakter ke-0 = 1 Januari
   ▼
dms_roster_patterns         ← 1 baris per karyawan per tahun + atribut denormalisasi
   │  DmsRosterRuleEngine (port compute()), hasil ringkas di-cache 5 menit
   ▼
/dms/roster-compliance      ← agregasi, filter, paginasi dikerjakan di server
```

Agregasi satu tahun penuh memindai hampir seluruh materialized view, jadi itu **hanya** dipakai saat backfill. Jadwal rutin menarik beberapa hari terakhir saja sehingga indeks tanggal terpakai.

### Berkas

| Berkas | Peran |
|---|---|
| `config/dms_roster.php` | Seluruh ambang, parameter per PT, filter populasi |
| `app/Services/Dms/Roster/DmsRosterRuleEngine.php` | Port `compute()` — murni, tanpa DB |
| `app/Services/Dms/Roster/DmsRosterEvaluation.php` | DTO hasil evaluasi (readonly) |
| `app/Services/Dms/Roster/DmsRosterJabatanKategori.php` | Jabatan → kategori (jabcat.json + fallback kata kunci) |
| `app/Services/Dms/Roster/DmsRosterRfidSyncService.php` | Tarik & kompilasi dari OLAP |
| `app/Services/Dms/Roster/DmsRosterComplianceService.php` | Payload dashboard |
| `app/Console/Commands/Dms/SyncRosterRfidCommand.php` | `dms:sync-roster-rfid` |
| `tests/Unit/Dms/DmsRosterRuleEngineTest.php` | 22 test rule engine |
| `tests/Unit/Dms/DmsRosterJabatanKategoriTest.php` | 22 test klasifikasi jabatan |
| `tests/Unit/Dms/DmsRosterPopulasiTest.php` | 9 test penyaringan & dedup populasi |

### Operasional

```bash
# sekali saat pasang — backfill 1 Januari s/d hari ini
php artisan dms:sync-roster-rfid --full

# rutin (sudah terjadwal tiap 30 menit di app/Console/Kernel.php)
php artisan dms:sync-roster-rfid

# rentang tertentu, mis. perbaikan data satu bulan
php artisan dms:sync-roster-rfid --from=2026-09-01 --to=2026-09-30
```

Command menghangatkan cache dashboard di akhir prosesnya, karena evaluasi 11.848 karyawan memakan ±3 detik CPU dan tidak pantas ditanggung pengunjung pertama.

### Perbedaan yang disengaja dari referensi

| # | Referensi | Implementasi ini | Alasan |
|---|---|---|---|
| 1 | Ambang REG-2 `>71`, REG-3 `<12` | Sama, tapi ditulis di `config/dms_roster.php` | Angka bisa diadu dengan halaman statis; ganti ke 70/14 cukup ubah config |
| 2 | Label UI menyebut `>70` / `<14` | Label mengambil angka dari config | Label tidak lagi berbeda dari yang dihitung (memperbaiki T-1 & T-2) |
| 3 | `yel.fill('')` mematikan semua flag kuning | Flag MAP-1/2/3 berfungsi | T-3 adalah bug, bukan desain — rule-nya didokumentasikan lengkap di halaman Rule |
| 4 | Lookup jabatan case-sensitive | Case-insensitive + fallback kata kunci | Populasi live punya 483 jabatan berbeda vs ±150 di tabel; tanpa fallback ribuan orang jatuh ke "Lainnya" |
| 5 | Seluruh evaluasi di browser, payload 14 MB | Evaluasi di server, payload halaman kecil | Data personal tidak lagi dikirim utuh ke klien |

Catatan T-4 (MAP-1 mati secara logika untuk KDC karena `thr=14`) **masih berlaku** — struktur "MAP hanya diuji bila belum kena REG-1" dipertahankan sesuai desain referensi. Untuk mengaktifkannya, turunkan `thr` KDC di config.

Satu keputusan klasifikasi yang perlu ditinjau: `TECHNICIAN` (147 orang) dipetakan ke **Mekanik**, yang berarti dikecualikan dari REG-1/2/3. Di referensi jabatan ini tidak ada di tabel sehingga jatuh ke "Lainnya" dan tetap kena rule penuh. Kalau pengecualian itu tidak dikehendaki, hapus `'TECHNICIAN'` dan `'TEKNISI'` dari `KEYWORD_RULES` di `DmsRosterJabatanKategori`.
