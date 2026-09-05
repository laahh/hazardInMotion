# Rencana Eksekusi Detail — Dashboard Monitoring Pengawasan OCR


**Sumber data:** HSE Database — schema `bcbeats` & `bcsid` (read-only)
**Scope:** reference layer → penjadwalan & absen → konsumsi SAP → agregasi → dashboard

> **Batas kepemilikan data.**
> Aplikasi ini hanya memiliki **Jadwal Rencana** dan **Absen**.
> Site, shift, dan config metrik ditulis sebagai konstanta di `config/control-room.php`.
> Personil, lokasi, dan seluruh data SAP dikonsumsi read-only dari HSE Database.
> **Tidak ada role & permission** — semua user yang login punya akses sama.

> **Penamaan.** "OCR" di judul dokumen ini adalah nama program/bisnis (Pengawasan OCR). Secara teknis, modul ini diberi nama **ControlRoom** — semua folder, namespace, tabel, config, artisan command, dan URL pakai penamaan itu: route diprefix `/control-room`, folder `routes/ControlRoom/`, `app/Http/Controllers/ControlRoom/`, `app/Models/ControlRoom/`, `app/Services/ControlRoom/`, `resources/views/control-room/`, `config/control-room.php`, tabel `control_room_*`, artisan `control-room:*`.

---

## 0. Cara Pakai Dokumen Ini

Format tiap task: **Tujuan** → **Struktur** → **Operasi** → **Acceptance**.

Aturan main untuk Claude Code:

1. Satu task = satu branch = satu PR.
2. Jangan lanjut fase berikutnya sebelum acceptance fase sekarang lolos.
3. Nama kolom di dokumen ini adalah **usulan**. Validasi ke skema nyata di T0.1.
4. **Jangan pernah menulis ke HSE Database.** Read-only di level kredensial, bukan hanya disiplin kode.
5. Jangan membuat tabel master untuk data yang sudah ada di sumber.
6. **LARANGAN KERAS — jangan pernah menjalankan migrasi database secara otomatis** (`php artisan migrate`, `migrate:fresh`, `migrate:rollback`, atau apapun yang memanggil itu). Buat file migration seperti biasa, tapi jangan pernah dieksekusi. User migrate manual sendiri. Lihat detail di T0.2.

---

## 0.5 Temuan Dari Codebase Existing — Wajib Dibaca Sebelum T0.1

> Review 2026-09-06 menelusuri seluruh codebase (`app/`, `config/`, `docs/`, root `.md`) untuk cross-check asumsi dokumen ini terhadap apa yang **sudah terbukti jalan** di project. Hasilnya mengubah beberapa desain di fase-fase berikutnya — baca dulu sebelum mengeksekusi T0.1 dst. Keputusan yang sudah dikonfirmasi user ditandai **[DIPUTUSKAN]**.

**1. Koneksi ke HSE Database sudah ada — jangan buat koneksi baru.**
`config/database.php` sudah punya `pgsql_ssh` (via SSH tunnel, `start-bemcu-tunnel.bat`) dan `pgsql_direct` (langsung ke RDS), keduanya mengarah ke database `hse_automation`, `search_path = bcbeats,public,bcsid,datamart`. Sudah dipakai puluhan modul (`PraOperasi*Reader`, `DmsMonitoring*`, `PembatasanLV*`, dll). **[DIPUTUSKAN] T0.2 memakai `pgsql_ssh`/`pgsql_direct` yang sudah ada**, bukan koneksi `hse_source` baru. Plumbing pendukung yang tersedia untuk dipakai ulang: `App\Services\SshTunnelService` (buka tunnel), `App\Services\DatabaseConnectionService` (health check `fsockopen` + `SELECT 1`).

**2. `mv_inspeksi_hazard`, `mv_observasi`, `mv_oak`, `mv_coaching` — nol referensi di seluruh codebase.**
Grep menyeluruh (`app/`, `routes/`, `database/`, `docs/`) hanya menemukan keempat nama ini di dalam dokumen ini sendiri. Dua kemungkinan: (a) view baru di Postgres yang memang belum pernah disentuh kode manapun — konsisten dengan catatan "data dibatasi mulai 2026-01-01" di temuan #6 di bawah — atau (b) nama ini keliru/berasal dari sumber dokumentasi yang sudah usang. **T0.1 tetap wajib dan tetap jadi gerbang paling kritis** — jangan asumsikan salah satu benar sebelum query langsung ke Postgres membuktikannya. Skema yang justru terbukti dipakai di produksi untuk hazard/inspeksi adalah `bcbeats.car_register` (join ke `m_status`, `m_goldenrule`, `m_kategori_tipe`, `m_lookup`, `m_obyek`, `m_obyek_detil`, `bcsid.m_karyawan`) — lihat `app/Http/Controllers/HazardMotion/*`. Kalau T0.1 membuktikan `mv_*` memang tidak ada, `car_register` + tabel master di atas adalah fallback yang sudah terbukti jalan.

**3. Personil — reuse `App\Models\OhsDashboard\Employee`.**
Model ini sudah query `bcsid.bep_vw_safety_karyawan_aktif` via `pgsql_direct` (global scope menulis ulang FROM ke derived table), sudah filter `status_karyawan='AKTIF'`, kolom kunci `kode_sid AS sid`. Ada catatan penting di doc-comment class ini: view alternatif `crontable_bep_vw_m_karyawan_aktif` diam-diam kehilangan ~750 karyawan aktif — makanya `Employee.php` sengaja pakai view langsung, bukan versi cron. **[DIPUTUSKAN] T1.2 `PersonnelReader` memanggil `OhsDashboard\Employee` ini langsung**, tidak membuat model `Source\Personnel` terpisah, tidak duplikasi query.

**4. Area kritis — bukan flag di `vw_lokasi_aktif` (yang juga nol referensi di codebase). [KOREKSI — lihat catatan di bawah]**
~~Status kritis lokasi dihitung ULANG setiap hari oleh `App\Services\LokasiNonKritisService::generate()`, disimpan ke tabel lokal `lokasi_non_kritis`.~~ **Ini benar secara kode, tapi ditolak user sebagai sumber untuk OCR** — `LokasiNonKritisService` terbukti query **ClickHouse** (`hse_automation.lokasi_detail_lokasi`, via `ClickHouseService`, pakai fungsi `toString()`/`toDate()` khas ClickHouse — bukan Postgres), dan user secara eksplisit ingin **OCR baca langsung ke `bcbeats` (Postgres), bukan lewat mirror ClickHouse ini**.

Konteks: ClickHouse `hse_automation`/`nitip` kemungkinan besar adalah mirror hasil replikasi (Airbyte — lihat `config/database.php` koneksi `clickhouse_nitip` yang usernamenya literally `airbyte`) dari schema Postgres `bcbeats`/`bcsid`. Buktinya: `PembatasanLVSiteLokasiService.php` (doc-comment baris 12-15) menyebut ada view **`bcbeats.bep_vw_site_lokasi_detil_lokasi`** di Postgres (versi aslinya), sementara kode lain (`SistemRoster\DashboardController`, `HazardMotion\fullMapsController`) query versi ClickHouse-nya: **`nitip.bep_vw_site_lokasi_detil_lokasi`**. Jadi data yang sama ada di dua tempat — Postgres (sumber asli) dan ClickHouse (mirror cepat untuk query berat).

**[DIPUTUSKAN] T1.3 `LocationReader` query langsung ke Postgres `bcbeats`, pola ambil dari `PembatasanLVSiteLokasiService`:**
```sql
-- Hierarki site → lokasi → detil_lokasi, semua di tabel self-referencing bcbeats.m_lokasi
SELECT TRIM(site.nama) AS site, TRIM(lokasi.nama) AS lokasi, TRIM(detil.nama) AS detail_lokasi
FROM bcbeats.m_lokasi site
JOIN bcbeats.m_lokasi lokasi ON lokasi.id_parent = site.id AND lokasi.id_tipe = 200 AND lokasi.is_active = '1'
JOIN bcbeats.m_lokasi detil  ON detil.id_parent = lokasi.id AND detil.id_tipe = 300 AND detil.is_active = '1'
WHERE site.id_tipe = 100 AND site.is_active = '1'
```
Dijalankan lewat `App\Services\PembatasanLV\PembatasanLVOlapQuery` yang sudah ada — helper generik untuk query Postgres OLAP dengan auto-fallback `pgsql_direct` → `pgsql_ssh` (cache status koneksi 20 detik) + `SET LOCAL statement_timeout`. **Reuse class ini langsung**, jangan tulis ulang logika pemilihan koneksi.

**Yang masih terbuka — flag kritis belum terbukti ada di `bcbeats.m_lokasi` maupun di `bcbeats.bep_vw_site_lokasi_detil_lokasi`.** Query di atas hanya mengambil `nama`/`id_tipe`/`id_parent`/`is_active` karena itu yang dibutuhkan combobox — kedua objek Postgres ini mungkin punya kolom lain (termasuk kemungkinan flag kritis) yang belum pernah di-select oleh kode manapun. Ini jadi pertanyaan baru, setara T0.1, sebelum T1.3 final: `\d+ bcbeats.m_lokasi` dan `\d+ bcbeats.bep_vw_site_lokasi_detil_lokasi` untuk melihat kolom lengkapnya. Kalau tidak ada flag kritis di kedua objek itu juga, "area kritis" untuk OCR harus didefinisikan ulang dari sumber lain yang murni bcbeats (mis. join ke IKK work permit versi Postgres, kalau ada) — **eskalasi ke user** kalau ini terjadi, jangan diam-diam fallback ke ClickHouse/`lokasi_non_kritis` karena itu sudah ditolak secara eksplisit.

**5. TBC & Blindspot — bukan kolom di source view, dan bukan tabel `scr_daily_coverage_area` yang diasumsikan plan (nol referensi di codebase).**
Ada pipeline eksternal (Python scraper `tableau_hsecm_rulebase_to_json.py`, di luar repo ini — lihat `penjelasan.md`) yang scrape Tableau tiap 6 jam dan APPEND ke tabel `scr_hsecm_blindspot_tbc_gr` (kolom `kategori_TBC`, `blindspot_TBC`, `deskripsi`, `pic`, dst, ditandai `batch_slot` 00/06/12/18) dan `scr_hsecm_coverage_area_kritis_daily` (kolom `Tercover`, `Status_Coverage_dalam_1_Week`). Dibaca lewat `App\Services\Hsecm\HsecmDatabaseRepository` + `HsecmDashboardService::DATASETS`, di-cache 5 menit.
**[DIPUTUSKAN setelah klarifikasi user 2026-09-06]:** validasi TBC yang sesungguhnya sekarang tinggal di **Google Sheet tasklist** (manual, per temuan), sharing **"anyone with link" (internal domain)** — bukan lagi (atau tidak selalu sinkron dengan) `scr_hsecm_blindspot_tbc_gr`. Konsekuensi:
- **Sumber TBC untuk OCR = Google Sheet tasklist**, dibaca via CSV export (`https://docs.google.com/spreadsheets/d/{ID}/export?format=csv&gid={GID}`), HTTP GET biasa (`Http::get()`) — tidak perlu service account/kredensial baru karena sheet publik-internal.
- **Sumber Blindspot & Coverage Area Kritis untuk OCR = tetap reuse `scr_hsecm_coverage_area_kritis_daily`** (belum ada indikasi bagian ini juga pindah ke Sheet) — reuse pola `HsecmDatabaseRepository`, jangan bikin tabel `app_mixer.scr_daily_coverage_area` baru seperti diasumsikan plan awal.
- Task baru: **T1.5 — GSheetTbcReader** (lihat Fase 1 di bawah). **Belum diketahui**: Sheet ID, nama tab/`gid`, daftar kolom persis, dan apakah tiap baris punya kolom yang mereferensikan laporan asal (source_row_key ke `car_register`/`mv_inspeksi_hazard`) atau cuma personil+tanggal+kategori bebas teks. Ini jadi prasyarat T1.5 & T2.1, dicatat sebagai pertanyaan baru di Lampiran D.

**6. Roster/jadwal — bukan model untuk direuse, tapi konvensi penamaan yang relevan.**
`RosterPlanning` / `RosterPlanningKaryawan` / `RosterPlanningJob` sudah ada, tapi domainnya beda (roster kepatuhan DOP, bukan jadwal pengawas OCR). Jangan reuse tabelnya — tapi pola servicenya (`RosterPlanningDopSyncService`) valid sebagai referensi struktur untuk T3.1.

**7. Tidak ada precedent Enum `Site`/`Shift` — boleh jadi yang pertama.**
24 Enum class yang ada semua domain lain (`AutoBanned*`, `FatigueManagement*`, dst). Gaya "config sebagai kamus" paling dekat adalah `config/monitoring_safety_engineering.php` (array flat untuk daftar site, assoc array untuk vocab status/shift). Memperkenalkan Enum `SiteCode`/`ShiftCode` baru sesuai desain T1.1 bukan penyimpangan dari house style — cuma memang belum ada presedennya juga.

---

## 1. Sumber Data

> **Status: sebagian besar sudah terverifikasi** dari dokumentasi skema internal HSE Database (skill `hse-sap-insiden-analysis`). Kolom yang ditandai ✅ sudah dikonfirmasi ada. Yang ditandai ⚠️ masih perlu dicek langsung ke DB.

| Kebutuhan | Objek sumber | Kolom kunci | Status |
|---|---|---|---|
| Personil | **[DIPUTUSKAN]** `App\Models\OhsDashboard\Employee` (reuse, bukan model baru) → `bcsid.bep_vw_safety_karyawan_aktif` via `pgsql_direct` | `kode_sid AS sid`, `nik`, `nama` | ✅ sudah terbukti jalan — lihat 0.5 poin 3 |
| Lokasi | **[DIPUTUSKAN]** `bcbeats.m_lokasi` (hierarki, langsung Postgres) via `PembatasanLVOlapQuery` | `nama`, `id_tipe` (100/200/300), `id_parent`, `is_active` | ✅ pola query sudah terbukti jalan (`PembatasanLVSiteLokasiService`), reuse langsung |
| Area kritis | `bcbeats.m_lokasi` atau `bcbeats.bep_vw_site_lokasi_detil_lokasi` — kolom flag belum diketahui | — | ⚠️ **belum terverifikasi** — kedua query yang sudah ada tidak pernah select kolom ini; wajib `\d+` langsung sebelum T1.3 final, lihat 0.5 poin 4 |
| TBC (validasi) | **[DIPUTUSKAN]** Google Sheet tasklist (manual, "anyone with link"), dibaca via CSV export | belum diketahui — perlu Sheet ID/gid & daftar kolom | ⚠️ perlu inventarisasi struktur sheet (task baru T1.5) — lihat 0.5 poin 5 |
| Blindspot & Coverage Area Kritis | **[DIPUTUSKAN]** reuse `scr_hsecm_coverage_area_kritis_daily` (MySQL lokal, diisi scraper Tableau eksternal tiap 6 jam) | `Site`, `Lokasi`, `Detil_Lokasi`, `Tercover`, `Status_Coverage_dalam_1_Week` | ✅ sudah jalan via `HsecmDatabaseRepository` — **bukan** `app_mixer.scr_daily_coverage_area` (tabel itu nol referensi di codebase) |
| SAP — Hazard & Inspeksi | `bcbeats.mv_inspeksi_hazard` | `jenis_laporan`, `kode_sid_pelapor`, `tanggal_laporan`, `site`, `deskripsi_temuan`, `ketidaksesuaian`, `subketidaksesuaian`, `nama_kategori`, `nama_goldenrule`, `kekerapan`, `keparahan`, `nilai_resiko` | ⚠️ **nol referensi di codebase** — wajib diverifikasi T0.1; fallback teruji = `bcbeats.car_register` (lihat 0.5 poin 2) |
| SAP — Observasi | `bcbeats.mv_observasi` | `id_observasi`, `kode_sid_pelapor`, `tanggal_observasi`, `site`, `lokasi`, `detil_lokasi`, `jenis_kegiatan` | ⚠️ **nol referensi di codebase** — wajib diverifikasi T0.1 |
| SAP — OAK | `bcbeats.mv_oak` | `kode_sid_pelapor`, `tanggal_submit`, `site` | ⚠️ **nol referensi di codebase** — wajib diverifikasi T0.1 |
| Coaching | `bcbeats.mv_coaching` | `kode_sid_coach`, `kode_sid_coachee`, `tanggal_coaching`, `site` | ⚠️ **nol referensi di codebase** — wajib diverifikasi T0.1 |

**Enam temuan awal (dari dokumentasi skema eksternal — sebagian sudah dikoreksi lebih lanjut oleh review codebase di 0.5, terutama poin 3 di bawah untuk personil dan poin 6 untuk validitas `mv_*`):**

**1. Engine terkonfirmasi PostgreSQL, dan tidak ada jembatan ke MySQL.**
Dokumentasi menyatakan eksplisit: Postgres di project ini **tidak punya FDW/dblink ke MySQL manapun**. Cross-database join mustahil → **Skenario B (sync ke snapshot) wajib**, bukan pilihan.

**2. Kolom diskriminator HAZARD vs INSPEKSI ADA.**
`mv_inspeksi_hazard.jenis_laporan` berisi `'HAZARD'` / `'INSPEKSI'`. Ini sebelumnya jadi pertanyaan pemblokir utama — sudah terjawab.

**3. Kunci personil = `kode_sid`, bukan nama.**
Dipakai konsisten di keempat view. Nama **tidak unik** — divalidasi ada >6 orang berbeda bernama "MUHAMMAD SAID". Jangan pernah join pakai nama.

**4. Kolom tanggal berbeda di tiap view.** Normalizer harus memetakan satu per satu:
```
mv_coaching         → tanggal_coaching
mv_oak              → tanggal_submit
mv_inspeksi_hazard  → tanggal_laporan
mv_observasi        → tanggal_observasi
```

**5. Nilai `site` tidak konsisten antar view.**
Contoh nyata: `"BMO 2"` (pakai spasi) vs `"BMO-2 B7"` vs `"BMO-2 B8"`. Wajib `SELECT DISTINCT site` per view sebelum memetakan ke `SiteCode`. Total 13 site terdeteksi: JAKARTA, BMO 1, BMO 2, BMO 3, BMO-2 B7, BMO-2 B8, EKSPLORASI, GMO, HO, LMO, MARINE, PMO, SMO.

**6. Data dibatasi mulai 2026-01-01.**
`mv_coaching` / `mv_oak` / `mv_inspeksi_hazard` hanya memuat data sejak awal 2026. Semua materialized view = snapshot, bukan realtime.

**Pemetaan view → komponen metrik (sudah final):**

```
mv_inspeksi_hazard  →  jenis_laporan = 'HAZARD'   → komponen 'hazard'
                       jenis_laporan = 'INSPEKSI' → komponen 'inspeksi'
                       → satu-satunya sumber nama_goldenrule (basis Highlight GR)

mv_observasi        →  komponen 'observasi'
mv_oak              →  komponen 'observasi'
                       (requirement "1 Obsr/OAK" — keduanya mengisi slot yang sama)

mv_coaching         →  BUKAN komponen target. Bonus %SAP di atas 100%.
```

---

## 2. Arsitektur

```
┌──────────────────────────────┐        ┌─────────────────────────┐
│ HSE Database (read-only)     │        │ config/control-room.php          │
│                              │        │  • Site                 │
│ bcsid.bep_vw_safety_         │        │  • Shift S1, S2         │
│       karyawan_aktif         │        │  • Config metrik        │
│ vw_lokasi_aktif              │        └────────────┬────────────┘
│ bcbeats.mv_inspeksi_hazard   │                     │
│ bcbeats.mv_observasi         │                     │
│ bcbeats.mv_oak               │                     │
│ bcbeats.mv_coaching          │                     │
└──────────────┬───────────────┘                     │
               │ sync inkremental                    │
               ▼                                     │
┌──────────────────────────────┐                     │
│ app_mixer (MySQL)            │◄────────────────────┘
│                              │
│  control_room_sap_snapshot   ← Fase 4 (normalisasi 4 view jadi 1)
│  control_room_schedule_plans ← Fase 3 (CRUD)
│  control_room_attendances    ← Fase 3 (CRUD)
│  control_room_schedule_changes
│  4 tabel agregasi   ← Fase 5
└──────────────┬───────────────┘
               ▼
        ┌─────────────┐
        │  DASHBOARD  │  Fase 6
        └─────────────┘
```

**Catatan teknis penting:** penamaan `mv_` (materialized view) dan schema `bcbeats`/`bcsid` mengindikasikan HSE Database berjalan di **PostgreSQL**, sementara aplikasi ini di **MySQL**. Kalau benar, cross-database join **tidak mungkin** — snapshot/sync jadi wajib, bukan opsional. Konfirmasi di T0.1.

**Update dari review codebase (0.5):** koneksi Postgres itu **sudah ada** (`pgsql_ssh`/`pgsql_direct`, bukan hipotetis) dan sudah dipakai puluhan modul lain — jadi bagian "cross-database join tidak mungkin, wajib snapshot" **terbukti benar dan sudah jadi praktik baku** di project ini, bukan cuma dugaan. Yang berubah: kotak "Personil" dan "Lokasi & area kritis" di diagram di atas **tidak lagi disuplai dari HSE Database di fase sync ini** — keduanya direuse dari tabel/model lokal yang sudah ada (`OhsDashboard\Employee` tetap query live ke Postgres tapi lewat model yang sudah ada, `lokasi_non_kritis` sepenuhnya lokal). Kotak "Data SAP (4 view)" tetap seperti digambar — itu satu-satunya bagian yang benar-benar butuh sync baru dari Postgres, dan itu pun menunggu T0.1.

**Prinsip yang tidak boleh dilanggar:**
- Dashboard tidak query HSE Database langsung — selalu lewat snapshot & agregasi.
- Formula metrik hanya hidup di `app/Services/ControlRoom/Metrics/`.
- Jadwal & absen menyimpan kunci sumber + snapshot nama, tanpa foreign key lintas database.

---

## 3. Matriks Kepemilikan

| Entitas | Lokasi | C | R | U | D |
|---|---|:-:|:-:|:-:|:-:|
| Site, Shift, Config metrik | `config/control-room.php` | — | ✓ | deploy | — |
| Personil | `bcsid.bep_vw_...` | ✗ | ✓ | ✗ | ✗ |
| Lokasi + area kritis | `vw_lokasi_aktif` | ✗ | ✓ | ✗ | ✗ |
| Data SAP (4 view) | `bcbeats.mv_*` | ✗ | ✓ | ✗ | ✗ |
| Snapshot SAP | app_mixer | sync | ✓ | sync | sync |
| **Jadwal Rencana** | **app_mixer** | ✓ | ✓ | ✓ | ✓ |
| **Absen** | **app_mixer** | ✓ | ✓ | ✓ | ✗ |
| Change log | app_mixer | auto | ✓ | ✗ | ✗ |
| Agregasi | app_mixer | auto | ✓ | auto | auto |

**Tabel yang dibuat aplikasi ini: 8** — snapshot SAP, jadwal, absen, change log, dan 4 tabel agregasi.

---

# FASE 0 — Discovery & Fondasi

## T0.1 — Inventarisasi Enam Objek Sumber

**Tujuan:** tahu bentuk persis keenam objek sebelum menulis reader.

**Langkah — untuk tiap objek, jalankan dan catat hasilnya:**

```sql
-- struktur
\d+ bcbeats.mv_inspeksi_hazard          -- PostgreSQL
DESCRIBE bcbeats.mv_inspeksi_hazard;    -- MySQL

-- volume & rentang waktu
SELECT COUNT(*), MIN(<kolom_tanggal>), MAX(<kolom_tanggal>)
FROM bcbeats.mv_inspeksi_hazard;

-- contoh isi
SELECT * FROM bcbeats.mv_inspeksi_hazard LIMIT 20;
```

Ulangi untuk `mv_observasi`, `mv_oak`, `mv_coaching`, `vw_lokasi_aktif`, `bcsid.bep_vw_safety_karyawan_aktif`.

**Pertanyaan yang wajib terjawab dengan bukti query:**

| # | Pertanyaan | Kenapa penting |
|---|---|---|
| 1 | Engine-nya PostgreSQL atau MySQL? Satu server dengan `app_mixer`? | menentukan wajib-tidaknya sync |
| 2 | `mv_inspeksi_hazard` punya kolom pembeda HAZARD vs INSPEKSI? | tanpa ini %SAP tidak bisa dihitung |
| 3 | Keempat `mv_*` punya kolom personil yang **formatnya sama**? | kunci join ke jadwal & absen |
| 4 | Ada kolom `val_tbc` / `val_gr`, dan seberapa banyak yang null? | basis %TBC |
| 5 | `vw_lokasi_aktif` ada flag area kritis? Namanya apa? | basis Coverage Score ×2 |
| 6 | `vw_lokasi_aktif` ada hierarki lokasi → detail lokasi? | panel coverage |
| 7 | Ada kolom site & shift, atau harus diturunkan? | filter dashboard |
| 8 | Ada kolom waktu submit **terpisah** dari waktu pengawasan? | perhitungan H+1 |
| 9 | Ada primary key / kolom unik per baris di tiap `mv_*`? | dedup saat sync |
| 10 | Materialized view di-refresh kapan? Data lama bisa berubah? | strategi sync inkremental |

**Deliverable:** `docs/schema-inventory.md` berisi struktur keenam objek, ERD Mermaid, tabel MAPPING kolom sumber → kolom snapshot, dan keputusan strategi sync.

**Acceptance:** kesepuluh pertanyaan terjawab dengan output query nyata, bukan asumsi. Kalau nomor 2 atau 5 jawabannya "tidak ada", **hentikan** dan eskalasi — dua requirement inti kehilangan basis.

---

## T0.2 — Fondasi Aplikasi

**Deliverable struktur:**

```
config/
  └─ control-room.php → site, shift, config metrik, mapping view
                        (koneksi HSE sudah ada: pgsql_ssh / pgsql_direct
                         di config/database.php — TIDAK buat koneksi baru)

routes/
  └─ ControlRoom/
       └─ control-room.php  → semua route modul ini, dibungkus
                               Route::prefix('control-room')->name('control-room.')->group(...)

app/
  ├─ Http/Controllers/ControlRoom/ → DashboardController, ScheduleController,
  │                                  AttendanceController, SapController,
  │                                  DataQualityController, dst — satu controller
  │                                  per area fungsional, ikut pola modul lain
  │                                  (mis. EmergencyResponse/Dashboard/DashboardController)
  ├─ Models/ControlRoom/             → SchedulePlan, Attendance, ScheduleChange, SapSnapshot,
  │                            GsheetTbcSnapshot (baru, lihat T1.5)
  ├─ Models/Source/          → InspeksiHazard, Observasi, Oak, Coaching
  │                            (read-only, hanya untuk 4 objek yang lolos
  │                            verifikasi T0.1 — Personnel TIDAK dibuat di sini,
  │                            reuse App\Models\OhsDashboard\Employee;
  │                            Location TIDAK dibuat di sini, reuse
  │                            App\Models\LokasiNonKritis)
  ├─ Services/ControlRoom/Reference/ → PersonnelReader (wraps OhsDashboard\Employee),
  │                            LocationReader (wraps LokasiNonKritis)
  ├─ Services/ControlRoom/Source/    → SapSyncService, SapNormalizer, GSheetTbcReader
  ├─ Services/ControlRoom/Metrics/
  ├─ Services/ControlRoom/Aggregation/
  └─ Http/Requests/ControlRoom/

resources/views/control-room/
  ├─ layouts/app.blade.php   → lihat T6.0
  └─ partials/sidebar.blade.php, topbar.blade.php

CLAUDE.md
```

**Pengaman read-only:** trait `ReadOnlyModel` yang meng-override `save()`, `update()`, `delete()`, `insert()` agar melempar `ReadOnlyModelException`. Dipasang di semua model baru di `App\Models\Source` (untuk 4 objek SAP). Tidak perlu dipasang ulang di `OhsDashboard\Employee`/`LokasiNonKritis` — keduanya direuse apa adanya, bukan ditulis ulang.

**Koneksi:** reuse `pgsql_ssh`/`pgsql_direct` (sudah ada di `config/database.php`, lihat 0.5 poin 1) untuk 4 objek SAP yang lolos T0.1. Health-check pakai `App\Services\DatabaseConnectionService` yang sudah ada, jangan tulis ulang.

**Auth — sederhana, tanpa role:**
- Login standar Laravel Breeze
- Semua user yang login melihat hal yang sama
- Tabel `users` ditambah kolom `personnel_source_key` (nullable) — dibutuhkan agar fitur absen tahu user ini personil yang mana. Tanpa ini, absen tidak bisa tahu siapa yang check-in.

**Migrasi database — LARANGAN KERAS, baca sebelum mengerjakan T3.1/T3.2/T3.3/T4.1/T5.1 dst:**
```
JANGAN PERNAH menjalankan `php artisan migrate`, `migrate:fresh`, `migrate:rollback`,
atau perintah migrate lainnya secara otomatis — baik langsung, lewat script, lewat
CI, maupun lewat perintah artisan custom yang memanggilnya di dalam kode.

User akan menjalankan migrasi secara MANUAL sendiri.

Yang BOLEH dan WAJIB dikerjakan: buat file migration Laravel (`database/migrations/
..._create_control_room_xxx_table.php`) seperti biasa untuk setiap tabel baru
(control_room_schedule_plans, control_room_attendances, control_room_schedule_changes,
control_room_sap_snapshot, control_room_gsheet_tbc_snapshot, 4 tabel agregasi Fase 5,
kolom tambahan users.personnel_source_key) — tulis filenya, JANGAN eksekusi.

Kalau task tertentu butuh tabel itu sudah ada untuk lanjut (mis. menulis test,
menjalankan seeder), laporkan ke user bahwa migrasi perlu dijalankan manual dulu,
JANGAN jalankan sendiri "supaya progress tidak macet".
```

**Acceptance:** login jalan, koneksi `pgsql_ssh`/`pgsql_direct` terverifikasi lewat `DatabaseConnectionService`, percobaan tulis ke model `App\Models\Source\*` melempar `ReadOnlyModelException`, `php artisan test` hijau, dan tidak ada satu pun pemanggilan `Artisan::call('migrate...')` atau eksekusi `php artisan migrate*` di riwayat kerja manapun untuk modul ini.

---

# FASE 1 — Reference Layer (Read-Only)

## T1.1 — Konstanta Site & Shift

**Deliverable:** `config/control-room.php`

```php
return [
    'sites' => [
        'HO' => [
            'name'       => 'Head Office',
            'source_key' => 'HO',            // nilai di kolom site data sumber
            'timezone'   => 'Asia/Makassar',
        ],
    ],

    'shifts' => [
        'S1' => ['name' => 'Shift 1', 'start' => '06:00', 'end' => '18:00', 'crosses_midnight' => false],
        'S2' => ['name' => 'Shift 2', 'start' => '18:00', 'end' => '06:00', 'crosses_midnight' => true],
    ],
];
```

**Deliverable pendukung:**
- Enum PHP `SiteCode` dan `ShiftCode` agar type-safe
- `ShiftResolver::resolve(Carbon $timestamp): ShiftCode`

**Aturan `crosses_midnight`:** laporan jam 01:00 tanggal 28 masuk **Shift 2 tanggal 27**, bukan Shift 1 tanggal 28. Ini menentukan banyak angka di dashboard.

**Acceptance:**
- Menambah site = menambah satu entri config, tanpa perubahan kode
- `ShiftResolver` punya test: tengah S1, tengah S2, tepat jam pergantian, lewat tengah malam

---

## T1.2 — PersonnelReader

**Sumber:** **[DIPUTUSKAN]** reuse `App\Models\OhsDashboard\Employee` (sudah query `bcsid.bep_vw_safety_karyawan_aktif` via `pgsql_direct`, lihat 0.5 poin 3) — **jangan buat `app/Models/Source/Personnel.php` baru**, jangan duplikasi global scope/query yang sudah ada.

**Deliverable:**

`app/Services/ControlRoom/Reference/PersonnelReader.php` — tipis, murni wrapper di atas `Employee`:
```
all(SiteCode $site): Collection       // dropdown assign jadwal — filter site_dedicated
find(string $sourceKey): ?Employee    // by kode_sid/sid
existsAndActive(string $sourceKey): bool
```

**Cache:** `remember` 60 menit + `php artisan control-room:refresh-reference`.

**Yang harus ditangani:**
- Model `Employee` sudah menangani filter `status_karyawan='AKTIF'` dan sudah menghindari bug view cron (750 karyawan hilang) — jangan ubah query dasarnya, cukup bungkus dengan cache + filter site di layer `PersonnelReader`.
- Personil yang resign hilang dari view `Employee` sumbernya, padahal jadwal & agregasi lamanya harus tetap terbaca → wajib pakai pola snapshot nama di T3.0 (tidak berubah dari desain awal).
- Kunci personil = `kode_sid` (alias `sid` di model `Employee`), bukan nama — konsisten dengan temuan #3 di Section 1.

**Acceptance:**
- Daftar personil satu site < 200 ms dari cache
- Personil yang hilang dari `Employee` (resign) tidak membuat halaman jadwal error
- Tidak ada query baru ke `bcsid.bep_vw_safety_karyawan_aktif` di luar `Employee.php` — grep `bep_vw_safety_karyawan_aktif` di luar file itu harus nihil

---

## T1.3 — LocationReader

**Sumber:** **[DIPUTUSKAN]** query langsung ke Postgres `bcbeats.m_lokasi` (hierarki site/lokasi/detil via `id_tipe` 100/200/300 + self-join `id_parent`), lewat `App\Services\PembatasanLV\PembatasanLVOlapQuery` yang sudah ada — **bukan** `vw_lokasi_aktif` (nol referensi), dan **bukan** reuse `LokasiNonKritisService`/`lokasi_non_kritis` (itu terbukti baca ClickHouse, ditolak eksplisit oleh user — lihat 0.5 poin 4).

**Deliverable:**

`app/Models/Source/Location.php` — read-only, connection dipilih lewat `PembatasanLVOlapQuery` (bukan `$connection` statis, karena helper ini punya fallback `pgsql_direct`/`pgsql_ssh`)

`app/Services/ControlRoom/Reference/LocationReader.php`, meniru pola `PembatasanLVSiteLokasiService` (cache 10 menit, query hierarki `m_lokasi`):
```
all(SiteCode $site): Collection
find(string $lokasi, string $detilLokasi): ?array
isCritical(string $lokasi, string $detilLokasi): bool   // dipakai CoverageScore — lihat catatan di 0.5 poin 4
criticalAreas(SiteCode $site): Collection
```

**Cache:** 10-60 menit + refresh manual, konsisten dengan `PembatasanLVSiteLokasiService::CACHE_TTL_SECONDS`.

**Prasyarat yang belum terjawab (baca 0.5 poin 4 sebelum implementasi):**
```
Query m_lokasi yang sudah terbukti jalan (PembatasanLVSiteLokasiService) hanya
select nama/id_tipe/id_parent/is_active — TIDAK ada flag kritis di situ.
View bcbeats.bep_vw_site_lokasi_detil_lokasi (disebut di doc-comment service itu)
mungkin punya kolom tambahan, tapi belum pernah di-select oleh kode manapun.

WAJIB sebelum T1.3 final: \d+ bcbeats.m_lokasi dan
\d+ bcbeats.bep_vw_site_lokasi_detil_lokasi via pgsql_direct/pgsql_ssh,
cari kolom yang mengindikasikan status kritis.

Kalau tidak ketemu di keduanya → HENTIKAN dan eskalasi ke user.
Jangan diam-diam fallback ke ClickHouse/lokasi_non_kritis — itu sudah
ditolak eksplisit sebagai sumber untuk modul OCR.
```

**Risiko — flag kritis berubah di sumber setelah minggu diagregasi (tidak berubah dari desain awal):**
```
Kalau status area kritis berubah di bcbeats setelah suatu minggu diagregasi,
skor minggu itu TIDAK boleh ikut berubah.

Solusi: tabel control_room_weekly_coverage_score menyimpan hasil hitungan sebagai
nilai BEKU. Dashboard membaca nilai beku itu, bukan menghitung ulang dari
bcbeats setiap kali dashboard dibuka.
```

**Acceptance:**
- Kolom flag kritis ditemukan dan dikonfirmasi lewat query nyata (bukan asumsi) sebelum kode `isCritical()` ditulis
- `isCritical()` punya test dengan data nyata dari bcbeats
- Mengubah flag di bcbeats lalu recalc minggu lama tidak mengubah skor beku
- Tidak ada satu pun query ke ClickHouse (`hse_automation`/`nitip`) di dalam `App\Services\ControlRoom\*` — grep harus nihil

---

## T1.4 — Config Metrik

**Deliverable:** bagian di `config/control-room.php`

```php
'sap_sources' => [
    'inspeksi_hazard' => [
        'view'          => 'bcbeats.mv_inspeksi_hazard',
        'components'    => ['hazard', 'inspeksi'],  // ditentukan kolom diskriminator
        'counts_tbc'    => true,
        'is_bonus'      => false,
    ],
    'observasi' => [
        'view'       => 'bcbeats.mv_observasi',
        'components' => ['observasi'],
        'counts_tbc' => false,
        'is_bonus'   => false,
    ],
    'oak' => [
        'view'       => 'bcbeats.mv_oak',
        'components' => ['observasi'],   // OAK mengisi slot observasi
        'counts_tbc' => false,
        'is_bonus'   => false,
    ],
    'coaching' => [
        'view'       => 'bcbeats.mv_coaching',
        'components' => [],
        'counts_tbc' => false,
        'is_bonus'   => true,            // menambah di atas 100%
    ],
],

'sap_target_components' => ['hazard', 'inspeksi', 'observasi'],

'coverage_weight' => ['normal' => 1, 'critical' => 2],
```

**Acceptance:** mengubah bobot atau flag di config lalu recalc mengubah hasil, tanpa sentuh kode logika.

---

## T1.5 — GSheetTbcReader (baru)

**Tujuan:** membaca hasil validasi TBC manual dari Google Sheet tasklist — sumber yang dikonfirmasi user menggantikan asumsi `val_tbc`/`val_gr` sebagai kolom di source view (lihat 0.5 poin 5).

**Prasyarat yang wajib dicek dulu (setara T0.1, tapi untuk objek ini):**
```
1. Sheet ID & gid tab yang benar (bisa lebih dari satu tab per periode?)
2. Daftar kolom persis — termasuk apakah ada kolom yang merujuk balik
   ke laporan asal (source_row_key ke car_register / mv_inspeksi_hazard),
   atau cuma personil + tanggal + kategori bebas teks
3. Format kolom personil — kode_sid, nama, atau keduanya?
4. Rentang tanggal yang dicakup sheet (apakah historis penuh atau rolling window)
5. Siapa yang mengisi/mengedit sheet, dan seberapa sering
```
Kalau #2 jawabannya "tidak ada join key ke laporan individual", maka %TBC (T2.1) tidak bisa dihitung per-baris SAP — harus dihitung sebagai **hitungan agregat per personil per minggu** dari sheet (mis. jumlah baris tervalidasi TBC / jumlah hazard+inspeksi personil itu di minggu yang sama), bukan flag per baris di `control_room_sap_snapshot`. Ini keputusan desain yang menunggu jawaban #2.

**Deliverable:**

`app/Services/ControlRoom/Source/GSheetTbcReader.php`:
```php
public function fetch(): Collection
{
    $csvUrl = "https://docs.google.com/spreadsheets/d/{$this->sheetId}/export?format=csv&gid={$this->gid}";
    $response = Http::timeout(15)->get($csvUrl); // butuh 2xx + content-type text/csv, bukan HTML login page
    // parse CSV → Collection baris ternormalisasi
}
```

`app/Models/ControlRoom/GsheetTbcSnapshot.php` + tabel `control_room_gsheet_tbc_snapshot` (kolom disesuaikan setelah struktur sheet diketahui) — pola sync sama seperti `control_room_sap_snapshot`: upsert berdasarkan kunci baris sheet, `synced_at`, tidak pernah delete baris lama (append/upsert saja, sesuai temuan bahwa sheet ini sifatnya tasklist berjalan).

**Penanganan kegagalan:** kalau sheet berubah jadi privat, atau struktur kolom berubah, `fetch()` harus gagal dengan jelas (bukan silently return collection kosong) — ini adalah dependency eksternal di luar kendali kode, jadi kegagalannya harus terlihat di halaman Data Quality (T4.4), bukan membuat %TBC diam-diam jadi 0.

**Command:** `php artisan control-room:sync-gsheet-tbc` — dijadwalkan mengikuti cadence sheet diupdate (tanya pemilik sheet; kalau tidak diketahui, mulai dari tiap jam lalu disesuaikan).

**Acceptance:**
- Sync dua kali berturut-turut tidak menghasilkan duplikat
- Sheet yang tidak bisa diakses (private/URL salah) menghasilkan log error yang jelas, bukan exception tak tertangkap
- Ada test dengan fixture CSV nyata (bukan mock HTTP yang mengembalikan data buatan tanpa hubungan ke bentuk asli)

---

# FASE 2 — Kamus Metrik

## T2.1 — Kodifikasi Formula

**Deliverable:** `app/Services/ControlRoom/Metrics/`, satu class per metrik.

### SAP H+1 — `SubmissionWindow`
```
Laporan diakui jika:
  submitted_at <= akhir_shift(tanggal_pengawasan) + 24 jam
Di luar itu → is_late = true, tidak masuk numerator %SAP,
tapi tetap tampil di tabel detail.
```

### % SAP — `SapAchievement`
```
Target per personil per shift:
  1 × hazard  (dari mv_inspeksi_hazard)
+ 1 × inspeksi (dari mv_inspeksi_hazard)
+ 1 × observasi (dari mv_observasi ATAU mv_oak)

komponen_terpenuhi = jumlah dari 3 komponen dengan count >= 1
% SAP = (komponen_terpenuhi / 3) × 100

Bonus di atas 100%: laporan tambahan + baris di mv_coaching.
Simpan raw count per komponen, bukan hanya persentase.
```

### % TBC — `TbcValidity`  ✅ **DIPUTUSKAN — bukan lagi open question**

Temuan discovery awal: **TBC = "Temuan To Be Concerned"**, kategori hasil validasi manual (TBC / PSPP / GR / Incident). **Klarifikasi user (2026-09-06): validasi ini memang dikerjakan manual oleh manusia, hasilnya tinggal di Google Sheet tasklist** (bukan kolom di `mv_inspeksi_hazard`, dan bukan pencocokan makna otomatis yang perlu diimplementasikan Laravel). Ini menyelesaikan open question #11 di Lampiran D — jawabannya bukan "ada kolom `val_tbc`" atau "tidak ada sama sekali", melainkan **"ada, tapi di sistem lain (Google Sheet), sudah divalidasi manusia"**.

```
Sumber: Google Sheet tasklist, dibaca via T1.5 GSheetTbcReader → control_room_gsheet_tbc_snapshot

% TBC = (jumlah entri tervalidasi TBC untuk personil tsb di periode tsb
         / total HAZARD+INSPEKSI personil itu di periode yang sama) × 100
Jika basis (total HAZARD+INSPEKSI) = 0 → null (tampil "—"), bukan 0
```

**Yang masih terbuka (bukan soal ada/tidaknya data, tapi soal bentuknya):** apakah tiap baris di sheet punya join key ke laporan SAP individual (source_row_key), atau cuma personil+tanggal+kategori bebas teks. Ini menentukan apakah %TBC dihitung **per-baris** (join langsung, presisi) atau **agregat per personil per minggu** (count-vs-count, tanpa presisi per laporan). Lihat prasyarat di T1.5 — keputusan formula final menunggu jawaban itu, tapi implementasi TIDAK menunggu penemuan kolom di Postgres seperti diasumsikan sebelumnya.

### Coverage Score — `CoverageScore`
```
skor = (COUNT DISTINCT lokasi non-kritis × 1)
     + (COUNT DISTINCT lokasi kritis    × 2)

Lokasi dikumpulkan dari SEMUA view yang punya kolom lokasi.
Status kritis dari LocationReader::isCritical().
DISTINCT — 5 laporan di lokasi sama tetap 1 lokasi.
```

### Variasi Score — `FindingVariety`
```
variasi = COUNT DISTINCT kategori_temuan / COUNT total_temuan
1.00 → tiap laporan kategori berbeda
0.05 → 20 laporan semua kategori sama
Jika total = 0 → null
```

**Acceptance:**
- Tiap formula punya unit test ≥ 4 kasus: normal, nol, di atas 100%, data kosong
- `grep` untuk `* 2` dan `/ 3` di controller/Blade → nihil

---

# FASE 3 — CRUD Jadwal & Absen

> Satu-satunya modul input di aplikasi ini.

## T3.0 — Pola Referensi Lintas Database

**Aturan:**
```
TIDAK ADA foreign key ke objek sumber — beda database (kemungkinan beda engine).

Setiap tabel yang merujuk personil menyimpan DUA hal:
  1. personnel_source_key    → kunci resolusi ke sumber
  2. personnel_name_snapshot → nama saat data dibuat

Alasan: bep_vw_safety_karyawan_aktif hanya berisi karyawan AKTIF.
Personil yang resign hilang dari view. Tanpa snapshot, jadwal dan
dashboard periode lama akan kosong atau error.
```

Pola sama untuk `site_code` dan `shift_code` — simpan string dari enum, bukan FK.

**Acceptance:** personil yang hilang dari view sumber tidak membuat halaman jadwal minggu lama error.

---

## T3.1 — CRUD Jadwal Rencana

**Skema `control_room_schedule_plans`:**

| Kolom | Tipe | Aturan |
|---|---|---|
| `id` | bigint PK | |
| `site_code` | varchar(20) | dari enum `SiteCode` |
| `year` / `week_number` | int | ISO week |
| `date` | date | |
| `shift_code` | varchar(10) | `S1` \| `S2` |
| `personnel_source_key` | varchar(100) | |
| `personnel_name_snapshot` | varchar(150) | |
| `status` | enum | `draft` \| `locked` |
| `locked_at` | timestamp | |
| `created_by` | FK users | |

Unique: (`site_code`, `date`, `shift_code`, `personnel_source_key`)
Index: (`site_code`, `year`, `week_number`)

**Operasi:**

| Op | Route | Aturan |
|---|---|---|
| Index | `GET /control-room/schedule?week=35&site=HO` | grid personil × 7 hari × 2 shift |
| Create | `POST /control-room/schedule/bulk` | satu submit untuk satu minggu penuh |
| Copy | `POST /control-room/schedule/copy` | duplikasi minggu sebelumnya lalu diedit |
| Update | `PUT /control-room/schedule/{id}` | jika `locked` → alasan wajib, masuk change log |
| Delete | `DELETE /control-room/schedule/{id}` | hanya `draft` dan minggu belum berjalan |
| Lock | `POST /control-room/schedule/{week}/lock` | kunci sebagai baseline pembanding |

**Validasi:**
- Personil tidak boleh dobel di tanggal + shift sama
- `personnel_source_key` harus lolos `PersonnelReader::existsAndActive()`
- Peringatan (bukan blokir): personil kena S1 dan S2 di hari sama
- Peringatan: slot shift tanpa personil
- `site_code` & `shift_code` valid menurut enum

**Acceptance:**
- Satu minggu penuh (7 hari × 2 shift) di-assign dalam satu submit
- Setelah lock, rencana awal tidak hilang meski jadwal berubah
- Bisa menjawab: "Minggu 35, siapa direncanakan vs siapa yang hadir"

---

## T3.2 — Change Log Jadwal

**Skema `control_room_schedule_changes`:** `schedule_plan_id`, `field`, `old_value`, `new_value`, `reason`, `changed_by`, `changed_at`.

**Operasi:** create otomatis lewat model observer. Read-only untuk user.

**Acceptance:** tiap perubahan setelah lock punya jejak: siapa, kapan, dari apa ke apa, alasan.

---

## T3.3 — CRUD Absen

**Skema `control_room_attendances`:**

| Kolom | Tipe | Aturan |
|---|---|---|
| `id` | bigint PK | |
| `schedule_plan_id` | FK, nullable | null = hadir tanpa dijadwalkan |
| `site_code` / `date` / `shift_code` | | |
| `personnel_source_key` | varchar(100) | |
| `personnel_name_snapshot` | varchar(150) | |
| `status` | enum | `hadir_sesuai_jadwal` \| `hadir_menggantikan` \| `tidak_hadir` |
| `replacing_source_key` | varchar(100), nullable | **wajib** jika menggantikan |
| `absence_reason` | text, nullable | wajib jika tidak hadir |
| `checked_in_at` | timestamp | **waktu server**, bukan device |
| `corrected_by` / `correction_reason` | | jika dikoreksi |

Unique: (`site_code`, `date`, `shift_code`, `personnel_source_key`)

**Operasi:**

| Op | Route | Aturan |
|---|---|---|
| Create | `POST /control-room/attendance/check-in` | identitas dari `users.personnel_source_key`; hanya dalam rentang shift ±2 jam |
| Index | `GET /control-room/attendance?week=35` | rekap mingguan |
| Show | `GET /control-room/attendance/{id}` | detail + siapa digantikan |
| Update | `PUT /control-room/attendance/{id}` | `correction_reason` wajib |
| Delete | — | tidak tersedia; koreksi lewat update |

**Aturan bisnis:**
```
Absen TIDAK bisa memblokir submit SAP — SAP dibuat di sistem lain.

Gantinya, agregasi menandai dua anomali:
  a) report_without_attendance → ada baris SAP, tidak ada absen
  b) attendance_without_report → absen hadir, nol baris SAP

Keduanya tampil sebagai flag di panel Penjadwalan dan Pencapaian.
```

**Acceptance:**
- `hadir_menggantikan` tanpa `replacing_source_key` ditolak
- Absen di luar jam shift (±2 jam) ditolak
- Kedua anomali terdeteksi dan tampil di dashboard

---

## T3.4 — UI Absen Mobile

**Acceptance:** alur absen selesai ≤ 3 tap di layar 380 px, tombol utama ≥ 44 px.

---

# FASE 4 — Read Adapter & Normalisasi SAP

> Fase paling teknis. Empat view dengan struktur berbeda harus jadi satu bentuk seragam.

## T4.1 — Tabel Snapshot Ternormalisasi

**Tujuan:** satu tabel di `app_mixer` yang menampung keempat view dalam bentuk seragam, agar agregasi tidak perlu tahu asal-usulnya.

**Skema `control_room_sap_snapshot`:**

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `source_view` | enum | `inspeksi_hazard` \| `observasi` \| `oak` \| `coaching` |
| `source_row_key` | varchar(150) | PK dari view sumber — basis dedup |
| `site_code` | varchar(20) | hasil resolusi |
| `observation_date` | date | |
| `observed_at` | datetime, nullable | |
| `submitted_at` | datetime | dasar hitung H+1 |
| `shift_code` | varchar(10) | hasil `ShiftResolver` |
| `is_late` | boolean | hasil `SubmissionWindow` |
| `personnel_source_key` | varchar(100) | |
| `personnel_name_raw` | varchar(150) | nama apa adanya dari sumber |
| `sap_component` | enum, nullable | `hazard` \| `inspeksi` \| `observasi` \| null (coaching) |
| `is_bonus` | boolean | true untuk coaching |
| `location_key` | varchar(150), nullable | |
| `detail_location_key` | varchar(150), nullable | |
| `finding_category` | varchar(150), nullable | basis Variasi Score |
| `nonconformity` | text, nullable | |
| `monitoring_tool` | varchar(100), nullable | DMS / CCTV / Mining / RFID |
| `val_tbc` | varchar(30), nullable | **diisi lewat join ke `control_room_gsheet_tbc_snapshot` (T1.5) saat sync, bukan dari kolom source view** — lihat T2.1 |
| `val_gr` | varchar(30), nullable | `nama_goldenrule` dari `mv_inspeksi_hazard` (kalau lolos T0.1), bukan dari Google Sheet |
| `description` | text, nullable | |
| `mapping_status` | enum | `mapped` \| `unknown_personnel` \| `unknown_location` \| `unknown_site` \| `no_shift` |
| `synced_at` | timestamp | |

Unique: (`source_view`, `source_row_key`)
Index: (`site_code`, `observation_date`), (`personnel_source_key`, `observation_date`), (`sap_component`), (`mapping_status`)

> `mapping_status` adalah kunci transparansi. Baris yang gagal dipetakan **tetap masuk snapshot**, tidak dibuang, agar terlihat di halaman Data Quality.

---

## T4.2 — Normalizer per View

**Tujuan:** satu class per view, masing-masing tahu cara memetakan kolomnya sendiri ke bentuk seragam.

**Deliverable:**

```
app/Services/ControlRoom/Source/Normalizer/
  ├─ AbstractNormalizer.php      → logika bersama: resolusi personil,
  │                                 lokasi, site, shift, is_late, mapping_status
  ├─ InspeksiHazardNormalizer.php → menentukan hazard vs inspeksi dari kolom
  │                                 diskriminator; satu-satunya yang mengisi val_tbc
  ├─ ObservasiNormalizer.php      → sap_component = 'observasi'
  ├─ OakNormalizer.php            → sap_component = 'observasi'
  └─ CoachingNormalizer.php       → sap_component = null, is_bonus = true
```

Tiap normalizer punya tabel mapping kolom eksplisit di kode, bukan tebakan runtime:

```php
// contoh — nama kolom kanan disesuaikan hasil T0.1
protected array $columnMap = [
    'source_row_key'       => 'id',
    'observation_date'     => 'tanggal_pengawasan',
    'submitted_at'         => 'second_of_date',
    'personnel_source_key' => 'nama',
    'location_key'         => 'lokasi',
    'detail_location_key'  => 'detail_lokasi',
    'finding_category'     => 'subketidaksesuaian',
    'monitoring_tool'      => 'tools_pengawasan',
    'val_tbc'              => 'val_tbc',
    'val_gr'               => 'val_gr',
];
```

**Acceptance:**
- Tiap normalizer punya unit test dengan fixture 5 baris nyata dari view-nya
- Baris tanpa personil yang cocok tetap tersimpan dengan `mapping_status = unknown_personnel`
- `mv_coaching` tidak pernah menghasilkan `sap_component` selain null

---

## T4.3 — Sync Service

**Deliverable:**
- `php artisan control-room:sync-sap --from=2026-08-24 --to=2026-08-30 [--view=observasi]`
- Sync inkremental: berdasarkan `updated_at` atau rentang tanggal, tergantung hasil T0.1 no.10
- Upsert berdasarkan (`source_view`, `source_row_key`) → idempoten
- Schedule tiap jam untuk minggu berjalan, harian untuk minggu sebelumnya
- Log per run: baris dibaca per view, baris ter-upsert, baris gagal dipetakan

**Penanganan materialized view:** kalau `mv_*` di-refresh berkala dan data lama bisa berubah, sync harus **re-sync rentang tanggal**, bukan hanya menarik baris baru. Tentukan setelah T0.1 no.10.

**Acceptance:**
- Sync dua kali untuk rentang sama tidak menghasilkan duplikat
- Sync satu minggu keempat view < 60 detik
- Ada laporan ringkas di akhir run: `dibaca X, tersimpan Y, gagal petakan Z`

---

## T4.4 — Halaman Data Quality

**Deliverable:** `GET /control-room/data-quality`

| Isu | Sumber | Tampilan |
|---|---|---|
| Personil tidak cocok | `mapping_status = unknown_personnel` | daftar nama unik + jumlah baris |
| Lokasi tidak dikenali | `unknown_location` | daftar + jumlah |
| Site tidak terdeteksi | `unknown_site` | daftar + jumlah |
| Di luar jam shift | `no_shift` | daftar + jumlah |
| `val_tbc` kosong | `inspeksi_hazard` dengan val_tbc null | jumlah + persentase |

Plus angka **mapping coverage** per view: berapa persen baris berstatus `mapped`.

**Acceptance:**
- Setiap baris yang gagal dipetakan muncul di halaman ini
- Mapping coverage tampil di header dashboard sebagai indikator kesehatan data
- Angka di halaman ini cocok dengan log sync

---

## T4.5 — Panel SAP Read-Only

**Deliverable:** `GET /control-room/sap` — tabel dari `control_room_sap_snapshot`. Filter: site, rentang tanggal, personil, `source_view`, `sap_component`, tools, val_tbc, val_gr, mapping_status. Server-side pagination. Export Excel. Badge asal view per baris.

**Acceptance:** tidak ada satu pun route POST/PUT/DELETE menuju data SAP.

---

# FASE 5 — Agregasi

## T5.1 — Tabel Agregasi

**`control_room_weekly_personnel_summary`**
`site_code, year, week_number, date, shift_code, personnel_source_key, personnel_name_snapshot, attendance_status, hazard_count, inspeksi_count, observasi_count, oak_count, coaching_count, sap_percentage, tbc_basis_count, tbc_valid_count, tbc_percentage, late_count, anomaly_flag`

**`control_room_weekly_coverage_score`**
`site_code, year, week_number, personnel_source_key, normal_location_count, critical_location_count, score, rank`
> Nilai beku. Perubahan flag kritis di `vw_lokasi_aktif` tidak mengubah minggu lama.

**`control_room_hourly_submission`**
`site_code, year, week_number, shift_code, hour, report_count, avg_per_day, cumulative_percentage`

**`control_room_weekly_quality`**
`site_code, year, week_number, personnel_source_key, total_findings, distinct_categories, variety_score, tbc_count, gr_count, blindspot_count`

---

## T5.2 — Job Agregasi

**Deliverable:**
- Job `RecalculateWeeklySummary` — membaca `control_room_sap_snapshot`, bukan HSE Database
- Hanya memproses baris dengan `mapping_status = mapped`
- Schedule harian 01:00 untuk minggu berjalan + minggu sebelumnya
- Command: `php artisan control-room:recalc --week=35 --site=HO --all`

**Acceptance:**
- Backfill 12 minggu berhasil
- Recalc dua kali menghasilkan angka identik
- Satu minggu satu site < 30 detik
- Jumlah baris terabaikan cocok dengan halaman Data Quality

---

# FASE 6 — Dashboard

## T6.0 — Layout & Template Dasar (samakan dengan `/emergency-response`)

**Tujuan:** dashboard OCR pakai template visual yang sama dengan modul Emergency Response, bukan template baru — konsisten dengan house style project ini.

**Template yang dipakai:** **WowDash Admin** (Bootstrap 5), asetnya sudah ada di `public/wowdash-admin/assets/` (dipakai bersama oleh banyak modul — bukan aset khusus Emergency Response). Stack yang ikut terbawa: **ApexCharts** (chart), **DataTables** (tabel server-side, dibutuhkan T4.5/T6.9), **Flatpickr** (date picker, untuk filter minggu/tanggal), **Remixicon** (`ri-*` icon font), **Leaflet** (peta — OCR kemungkinan tidak butuh ini kecuali ada requirement peta lokasi di masa depan).

**Konvensi yang sudah dipakai puluhan modul lain (`EmergencyResponse`, `Isc`, `Hsecm`, `OhsDashboard`, `DopSafety`, dst) — bukan cuma Emergency Response — ikuti pola yang sama:**
```
resources/views/control-room/
  ├─ layouts/app.blade.php      → copy struktur dari
  │                                resources/views/EmergencyResponse/layouts/app.blade.php
  │                                (link CSS/JS wowdash-admin yang sama persis, ganti
  │                                <title> & teks footer jadi "OCR" / nama dashboard ini)
  └─ partials/
       ├─ sidebar.blade.php     → copy struktur dari
       │                          resources/views/EmergencyResponse/partials/sidebar.blade.php,
       │                          ganti isi <ul class="sidebar-menu"> dengan menu OCR
       │                          (Dashboard, Jadwal, Absen, Data SAP, Data Quality),
       │                          pola link pakai helper $rr() (Route::has() dulu sebelum
       │                          route() — supaya sidebar tidak error kalau ada route yang
       │                          belum didaftarkan saat development bertahap)
       └─ topbar.blade.php      → copy struktur dari
                                   resources/views/EmergencyResponse/partials/topbar.blade.php
```

**Jangan** bikin sistem layout/CSS baru, jangan pasang framework CSS lain (Tailwind, dst) — modul ini harus terlihat konsisten dengan modul lain yang sudah production, dan tim yang sudah familiar dengan WowDash tidak perlu belajar pattern baru.

**Pola komponen yang harus diikuti (dicontek langsung dari `EmergencyResponse/dashboard/index.blade.php`):**
- Kartu KPI: `<div class="card shadow-none border h-100">` + icon lingkaran berwarna (`w-50-px h-50-px bg-{warna}-100 text-{warna}-600 rounded-circle`) — dipakai untuk 6 kartu KPI header di T6.2
- Chart: `<div id="chart-xxx"></div>` + inisialisasi `new ApexCharts(...)` di `@push('scripts')`, data dilempar lewat `@json($variable)` dari controller — dipakai untuk Pareto (T6.6), tren mingguan, dsb
- Filter form: `<form method="GET" class="card shadow-none border mb-24">` dengan `<select>`/`<input type="date">` submit biasa (bukan AJAX/livewire) — dipakai untuk filter site/minggu di T6.1
- List/tabel ringkas: `list-group list-group-flush` untuk daftar pendek (mis. anomali terbaru); DataTables untuk tabel besar dengan pagination server-side (T4.5, T6.9)
- Badge status: `<span class="badge bg-{warna}-focus text-{warna}-600 px-8 py-2 radius-4">`

**Acceptance:** halaman dashboard OCR memakai `resources/views/control-room/layouts/app.blade.php`, hasil render visualnya (spacing, warna kartu, tipografi) tidak bisa dibedakan dari modul Emergency Response oleh orang yang tidak tahu kode sumbernya — cek berdampingan (screenshot) sebelum lanjut ke T6.2 dst.

---

## T6.1 — Kerangka & Filter
`GET /control-room/dashboard?site=HO&week=35`. Filter global site/tahun/minggu, persist di session. Header: "Last Data Update", waktu sync terakhir, mapping coverage. Extends `control-room.layouts.app` (lihat T6.0).

## T6.2 — Panel KPI Header
6 kartu: % Total Kehadiran, % Avg SAP, Coverage Detail Lokasi, Coverage Area Kritis, Ratio SAP, Ratio TBC. Tambahan: delta vs minggu lalu, tooltip rumus, klik untuk filter.

## T6.3 — Panel Penjadwalan (Rencana vs Aktual)
Matriks personil × 7 tanggal × 2 shift, dua layer: outline = rencana, fill = aktual. Legend: Hadir Sesuai Jadwal / Hadir Menggantikan / Tidak Hadir / Tidak Dijadwalkan / Anomali.

## T6.4 — Panel Pencapaian per Personil
Tabel harian: Tanggal, Nama, Kehadiran, %SAP, %TBC. Heatmap merah <60%, kuning 60–99%, hijau ≥100%. Klik baris → drill-down ke `control_room_sap_snapshot`.

## T6.5 — Panel Coverage Score & Ranking

| Rank | Nama | Non-kritis (×1) | Kritis (×2) | Skor |
|---|---|---|---|---|

**Acceptance:** uji regresi data existing — NIA ANGGITA (5 lokasi, 10 kritis → 25) di atas M. FADILLAH S (13 lokasi, 0 kritis → 13).

## T6.6 — Panel Pareto Distribusi Jam
Bar diurutkan dari frekuensi tertinggi, garis kumulatif % di sumbu kanan, garis bantu 80%, terpisah Shift 1 / Shift 2.

## T6.7 — Panel Highlight Temuan

| Blok | Sumber | Status |
|---|---|---|
| **GR** | `mv_inspeksi_hazard.nama_goldenrule` — breakdown per Golden Rule | ⚠️ menunggu T0.1 (lihat 0.5 poin 2) |
| **Blindspot** | **[DIPUTUSKAN]** `scr_hsecm_coverage_area_kritis_daily` (bukan `app_mixer.scr_daily_coverage_area`, tabel itu tidak ada) | ✅ tabel & data sudah ada, tinggal reuse |
| **TBC** | **[DIPUTUSKAN]** Google Sheet tasklist via T1.5 (lihat T2.1) | ✅ sumber sudah jelas, struktur sheet masih perlu diinventarisasi |

**Definisi Blindspot (revisi — tabel asli plan tidak ada di codebase):**
```
Rencana awal dokumen ini mengasumsikan tabel app_mixer.scr_daily_coverage_area
dengan kolom Not_Covered. Grep menyeluruh membuktikan tabel ini TIDAK ADA
di manapun di codebase — nama itu hanya muncul di dokumen ini sendiri.

Yang benar-benar ada dan sudah dipakai produksi: scr_hsecm_coverage_area_kritis_daily
(kolom Site, Lokasi, Detil_Lokasi, Tercover, Status_Coverage_dalam_1_Week),
diisi scraper Tableau eksternal tiap 6 jam, dibaca via
App\Services\Hsecm\HsecmDatabaseRepository (cache 5 menit).

Definisi blindspot untuk OCR: turunkan dari kolom Tercover / 
Status_Coverage_dalam_1_Week (mis. Tercover = 0% atau status "Belum Tercover"),
BUKAN dari boolean Not_Covered yang tidak pernah ada.
Perlu dicek langsung isi kolom Tercover/Status untuk memastikan nilai
persis yang menandakan "belum pernah disentuh" (mirip catatan lama soal
Not_Covered: baca ISI kolom, jangan tebak dari NAMA kolom).
```

**Ini bukan soal cakupan kamera CCTV/DMS** — melainkan sebaran lokasi yang sudah/belum dikunjungi pengawas.

**Arsitektur:** `scr_hsecm_coverage_area_kritis_daily` ada di MySQL lokal — **database yang sama dengan aplikasi ini**. Jadi panel Blindspot dan Coverage tidak perlu sync lintas engine sama sekali, cukup query lokal lewat `HsecmDatabaseRepository` yang sudah ada — jangan bikin service baru yang menduplikasi cara baca tabel ini.

**Task ini tidak lagi tertahan**, tapi ganti prasyaratnya: sebelum T6.7 dikerjakan, cek isi kolom `Tercover`/`Status_Coverage_dalam_1_Week` di `scr_hsecm_coverage_area_kritis_daily` untuk memastikan nilai yang menandakan blindspot (setara langkah T0.1 tapi untuk tabel ini).

## T6.8 — Panel Kualitas

| Nama | Total Temuan | Kategori Unik | Variasi Score | TBC | GR | Blindspot |
|---|---|---|---|---|---|---|

Plus scatter: X = volume, Y = variasi.

## T6.9 — Panel Detail Pelaporan
Reuse T4.5 sebagai embedded, terfilter sesuai konteks dashboard.

---

# FASE 7 — Replikasi Site

## T7.1 — Site Scoping
Filter `site_code` di semua query agregasi. Site switcher di header. Menambah site = menambah entri `config/control-room.php` + deploy.

## T7.2 — Perbandingan Antar-Site
Prioritas rendah. Tabel KPI semua site berdampingan + tren mingguan.

---

# FASE 8 — Hardening

**T8.1 Testing** — feature test jadwal & absen, regresi seluruh formula metrik, test `ShiftResolver` lintas tengah malam, test tiap normalizer dengan fixture nyata, test koneksi sumber menolak tulis.

**T8.2 Performa** — index sesuai T4.1, cache reference 60 menit, cache agregasi 15 menit, dashboard load < 2 detik, N+1 query nol.

**T8.3 Audit Trail** — log perubahan jadwal dan absen.

**T8.4 Seeder & Demo** — fake reader + snapshot dummy 4 minggu, agar testing tidak bergantung koneksi HSE Database.

---

# Lampiran A — Daftar Route

```
# Semua route di bawah prefix /control-room (Route::prefix('control-room')->group(...))

# Scheduling  ── CRUD penuh
GET                  /control-room/schedule
POST                 /control-room/schedule/bulk
POST                 /control-room/schedule/copy
PUT|DELETE           /control-room/schedule/{id}
POST                 /control-room/schedule/{week}/lock
GET                  /control-room/schedule/changes

# Absen  ── tanpa delete
POST                 /control-room/attendance/check-in
GET                  /control-room/attendance
GET|PUT              /control-room/attendance/{id}

# Data SAP  ── READ-ONLY
GET                  /control-room/sap
GET                  /control-room/sap/{id}
GET                  /control-room/sap/export
GET                  /control-room/data-quality

# Dashboard
GET                  /control-room/dashboard
GET                  /control-room/dashboard/drill/{panel}
GET                  /control-room/dashboard/export
```

> Tidak ada route `/master/*`. Tidak ada route role/permission.

---

# Lampiran B — Command Artisan

```
php artisan control-room:sync-sap --from=YYYY-MM-DD --to=YYYY-MM-DD [--view=]
php artisan control-room:recalc --week=35 --site=HO [--all]
php artisan control-room:refresh-reference
```

---

# Lampiran C — Urutan Eksekusi & Dependensi

```
FASE 0  T0.1 → T0.2                        [discovery 6 objek + fondasi]
FASE 1  T1.1 → T1.2 → T1.3 → T1.4          [reference layer]
FASE 2  T2.1                               [kamus metrik — GERBANG]
FASE 3  T3.0 → T3.1 → T3.2 → T3.3 → T3.4   [jadwal & absen]
FASE 4  T4.1 → T4.2 → T4.3 → T4.4 → T4.5   [normalisasi 4 view]
FASE 5  T5.1 → T5.2                        [agregasi]
FASE 6  T6.1 → T6.2 ... T6.9               [dashboard]
FASE 7  T7.1 → T7.2                        [replikasi site]
FASE 8  T8.1 ... T8.4                      [hardening]
```

| Task | Diblokir oleh | Alasan |
|---|---|---|
| T4.x (normalisasi SAP 4 view), GR di T6.7 | T0.1 | struktur `mv_inspeksi_hazard`/`mv_observasi`/`mv_oak`/`mv_coaching` belum diketahui — nol referensi di codebase, lihat 0.5 poin 2 |
| T1.2, T2.1 (%TBC bagian sumber), T6.7 (Blindspot) | ~~T0.1~~ **tidak lagi diblokir** | sudah diputuskan reuse `OhsDashboard\Employee`, Google Sheet, `scr_hsecm_coverage_area_kritis_daily` — lihat 0.5 |
| T1.3 (query lokasi), T6.5 (bobot kritis) | **belum lolos, tapi bukan lagi menunggu T0.1** | sumber lokasi sudah pasti (`bcbeats.m_lokasi` langsung Postgres, reuse `PembatasanLVOlapQuery`) — yang masih dicari cuma nama kolom flag kritis, lihat pertanyaan #26 |
| T1.5, T2.1 (%TBC formula final) | inventarisasi struktur Google Sheet | perlu Sheet ID/gid, kolom, dan ada/tidaknya join key ke laporan individual |
| T6.7 (definisi persis nilai blindspot) | cek isi kolom `Tercover`/`Status_Coverage_dalam_1_Week` | setara T0.1 tapi untuk `scr_hsecm_coverage_area_kritis_daily`, bukan tabel yang diasumsikan plan awal |
| Fase 3–6 | T2.1 | formula harus final |
| T4.2 | T0.1 no.2 | butuh kolom pembeda hazard vs inspeksi (kalau `mv_*` terbukti ada; kalau tidak, pakai `jenis_laporan`/diskriminator setara di `car_register`) |
| T5.1 | T4.3 | agregasi baca snapshot |
| Fase 6 | T5.2 | dashboard baca agregasi |

---

# Lampiran D — Status Pertanyaan

## ✅ Sudah terjawab dari dokumentasi skema

| # | Pertanyaan | Jawaban |
|---|---|---|
| 1 | Engine HSE Database? | **PostgreSQL.** `app_mixer` MySQL terpisah. **Tidak ada FDW/dblink** antara keduanya → sync wajib |
| 2 | Kolom pembeda HAZARD vs INSPEKSI? | **Ada:** `mv_inspeksi_hazard.jenis_laporan` = `'HAZARD'` \| `'INSPEKSI'` |
| 3 | Format identifikasi personil sama di keempat view? | **Ya — `kode_sid`.** Nama tidak unik, jangan dipakai sebagai kunci |
| 4 | Kolom tanggal? | Berbeda per view: `tanggal_coaching` / `tanggal_submit` / `tanggal_laporan` / `tanggal_observasi` |
| 5 | Ada kolom site? | Ada di keempat view, tapi **nilainya tidak konsisten** (`"BMO 2"` vs `"BMO-2 B7"`) |
| 6 | Definisi **Blindspot**? | **Titik Lokasi+Detil_Lokasi dengan `Not_Covered = 'Not Covered'`** di `scr_daily_coverage_area` |
| 7 | Sumber **Golden Rules**? | `mv_inspeksi_hazard.nama_goldenrule` — kolom langsung, tidak perlu objek terpisah |
| 8 | Kolom untuk Variasi Score? | Tersedia: `nama_kategori`, `ketidaksesuaian`, `subketidaksesuaian`. Dashboard existing pakai `subketidaksesuaian` |
| 9 | Data coverage lokasi dari mana? | `app_mixer.scr_daily_coverage_area` — **MySQL, satu DB dengan aplikasi ini**, append-only harian |
| 10 | Rentang data tersedia? | `mv_coaching`/`mv_oak`/`mv_inspeksi_hazard` dibatasi **mulai 2026-01-01** |

## ✅ Resolved lewat review codebase 2026-09-06 (bukan lewat query DB, lewat cross-check kode)

| # | Pertanyaan lama | Jawaban baru |
|---|---|---|
| 11 | `val_tbc` / `val_gr` benar ada sebagai kolom di `mv_inspeksi_hazard`? | **Tidak relevan lagi** — TBC divalidasi manual di Google Sheet tasklist (dikonfirmasi user), bukan kolom di source view. Lihat 0.5 poin 5, T1.5, T2.1. |
| 12 | `vw_lokasi_aktif` ada di schema mana, dan punya flag area kritis? | **`vw_lokasi_aktif` sendiri tidak dipakai — diganti `bcbeats.m_lokasi`/`bcbeats.bep_vw_site_lokasi_detil_lokasi` (Postgres langsung, bukan ClickHouse).** Flag kritisnya sendiri **masih belum ditemukan** — kode yang ada belum pernah select kolom itu. Lihat 0.5 poin 4, T1.3, dan item #24b baru di bawah. |
| 13 | `bep_vw_safety_karyawan_aktif` isinya apa, dan `kode_sid`-nya sama dengan `bep_sid_karyawan`? | **Tidak perlu dicek ulang** — `App\Models\OhsDashboard\Employee` sudah query view ini di produksi, sudah tervalidasi (termasuk bug 750 karyawan hilang di view alternatif). Reuse langsung, lihat 0.5 poin 3, T1.2. |

## ⚠️ Masih perlu dicek langsung ke database / sumber lain

| # | Pertanyaan | Cara cek | Memblokir |
|---|---|---|---|
| 14 | Ada kolom **waktu submit** terpisah dari tanggal pengawasan di `mv_*`? | cek kolom bertipe timestamp di tiap view (kalau T0.1 membuktikan `mv_*` ada) | T2.1 (H+1) |
| 15 | Materialized view di-refresh kapan? Data lama bisa berubah? | `SELECT * FROM pg_matviews` + tanya admin | T4.3 (strategi sync) |
| 16 | Ada kolom **shift** di data SAP? | cek kolom di keempat view | T4.2 |
| 23 | Google Sheet TBC: Sheet ID, gid, kolom persis, ada/tidak join key ke laporan individual? | tanya pemilik sheet + buka sheet langsung | T1.5, T2.1 |
| 24 | Isi persis kolom `Tercover` / `Status_Coverage_dalam_1_Week` di `scr_hsecm_coverage_area_kritis_daily` — nilai mana yang berarti "blindspot"? | `SELECT DISTINCT Tercover, Status_Coverage_dalam_1_Week FROM scr_hsecm_coverage_area_kritis_daily` | T6.7 |
| 26 | Kolom flag area kritis ada di `bcbeats.m_lokasi` atau `bcbeats.bep_vw_site_lokasi_detil_lokasi`? Namanya apa? | `\d+ bcbeats.m_lokasi` dan `\d+ bcbeats.bep_vw_site_lokasi_detil_lokasi` via `pgsql_direct`/`pgsql_ssh` | T1.3, T6.5 — **user sudah menolak fallback ke ClickHouse/`lokasi_non_kritis`, jadi ini wajib ketemu di Postgres atau dieskalasi** |
| 25 | Apakah `mv_inspeksi_hazard` dkk benar-benar ada di Postgres `hse_automation`, atau harus fallback ke `bcbeats.car_register`? | `\d+ bcbeats.mv_inspeksi_hazard` via `pgsql_ssh`/`pgsql_direct` | **Semua task Fase 4, GR di T6.7 — paling kritis, ini T0.1** |

## 🔷 Keputusan bisnis — perlu jawaban user, bukan query

| # | Pertanyaan |
|---|---|
| 17 | Jam mulai & selesai Shift 1 dan Shift 2 yang sebenarnya? |
| 18 | Batas **H+1** dihitung dari tanggal pengawasan atau akhir shift? |
| 19 | Personil bertugas 2 shift sehari — targetnya 3 atau 6 komponen? |
| 20 | **Coaching** menambah berapa persen ke %SAP? |
| 21 | Tanpa role, siapa yang boleh assign jadwal — semua user yang login? |
| 22 | Bobot area kritis ×2 — kalau `vw_lokasi_aktif` ternyata tidak punya flag kritis, dari mana penentuannya? |