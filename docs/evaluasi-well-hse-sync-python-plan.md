# Planning: Sync Karyawan HSE → BeWell (Python)

Dokumen ini adalah kontrak implementasi untuk **project Python baru** yang menggantikan job Laravel `SportEvaluationSyncHseEmployeesJob`.

Salin file ini ke repo Python baru, lalu implementasi mengikuti urutan dan aturan di bawah. Jangan improvisasi bagian yang ditandai **WAJIB**.

---

## 1. Tujuan

Sinkronisasi roster karyawan dari **HSE Automation API** ke tabel BeWell `employee_profiles`.

Perilaku yang harus sama dengan Laravel sekarang:

| Kasus | Aksi |
|---|---|
| SID baru (belum ada di BeWell) | **INSERT** baris baru, `id = MAX(id)+1`, `password_hash = bcrypt(kode_sid)` |
| SID sudah ada | **UPDATE** field (nama, perusahaan, status, jabatan, site, …) **tanpa mengubah `id`**, **tanpa reset password** |
| SID BeWell `AKTIF` tapi tidak muncul di roster HSE | Set `status_karyawan = NONAKTIF` |
| Fetch company HSE ada yang gagal | **Jangan** deactivate siapa pun |

Ini **bukan** replace seluruh tabel. Jangan `TRUNCATE`, `DELETE` massal, atau `REPLACE INTO`.

---

## 2. Mengapa bukan replace / hapus-insert

Kolom `employee_profiles.id` adalah **user_id** di tabel aktivitas BeWell:

- `food_analyses.user_id`
- `workout_analyses.user_id`
- `login_audit.user_id`

Kalau `id` berubah, histori makan/olahraga/login putus. Dashboard Evaluasi Well (install, user aktif, mitra) ikut salah.

`id` di BeWell **bukan AUTO_INCREMENT**. Harus dialokasi manual `MAX(id)+1`.

---

## 3. Arsitektur

```
┌─────────────────────────┐     HTTPS      ┌──────────────────────────┐
│  HSE Automation API     │◄───────────────│  Python sync (cron)      │
│  hseautomation.beraucoal│                │  project baru            │
└─────────────────────────┘                └────────────┬─────────────┘
                                                       │ MySQL
                                                       ▼
                                           ┌──────────────────────────┐
                                           │  BeWell DB               │
                                           │  employee_profiles       │
                                           │  (sering via SSH tunnel) │
                                           └──────────────────────────┘
```

Admin Laravel **tidak** perlu diubah agar sync jalan. Python menulis langsung ke BeWell. Tombol Sync HSE di Admin boleh dibiarkan (akan redundant) atau dinonaktifkan belakangan.

---

## 4. Flow proses (urut WAJIB)

```
1. Ping / SELECT 1 ke BeWell
   └─ gagal → ABORT (jangan deactivate)

2. Auth HSE
   ├─ jika EVALUASI_WELL_HSE_API_KEY terisi → pakai sebagai x-api-key
   └─ else POST /beats/api/mobile/login {username, password} → ambil token

3. GET /sid2/api/ftwApi/getCompany?page=1&size=1000

4. Untuk setiap company:
   GET /sid2/api/ftwApi/getEmployee?companyId={id}&page=1&size=30000
   └─ gagal → failed_companies += 1, lanjut company berikutnya
   └─ sukses → kumpulkan SID (unique, key = UPPER(sid))

5. Deactivate (HANYA jika failed_companies == 0 DAN ada SID):
   BeWell status AKTIF yang UPPER(kode_sid) tidak ada di set HSE
   → UPDATE status_karyawan = 'NONAKTIF'

6. Load map existing BeWell:
   UPPER(TRIM(kode_sid)) → {id, kode_sid_asli}
   (chunk IN 800; kalau duplikat SID, pakai id terkecil)

7. Loop setiap SID:
   a. GET /sid2/employeeInfo/bySid/{sid}?expand=...
   b. Map JSON → payload (skip jika sid/nama kosong)
   c. Jika SID ada di map → UPDATE WHERE id = ... (tanpa password, tanpa ubah id)
      Pertahankan kode_sid tersimpan (jangan ubah casing)
   d. Jika SID baru → INSERT id=MAX(id)+1 + bcrypt(sid)
   e. Jeda ~50ms antar request detail
   f. Retry 3× untuk HTTP 5xx / timeout (jeda ~700ms)
   g. Log progress tiap 100 SID

8. Tulis summary: inserted, updated, deactivated, skipped_invalid, failed, errors
```

Deactivate **sebelum** upsert, sama seperti Laravel. Alasan: roster HSE adalah source of truth untuk siapa yang masih AKTIF.

---

## 5. Aturan bisnis (WAJIB)

### 5.1 Kunci = `kode_sid`, bukan `id`

Match: `UPPER(TRIM(kode_sid))`.

Pada UPDATE existing:

- Jangan rewrite `kode_sid` ke casing baru dari HSE.
- Pakai `kode_sid` yang sudah tersimpan di BeWell.

### 5.2 Jangan sentuh kolom ini saat UPDATE

- `id`
- `password_hash`
- `last_login_at`, `login_count`, `last_login_ip`, `last_platform` (bukan milik roster)

### 5.3 INSERT karyawan baru

```
password_hash = bcrypt(kode_sid)   # kompatibel Laravel Hash::make, cost 10
id            = MAX(id) + 1        # lock FOR UPDATE, skip id yang sudah terpakai, retry jika duplicate
```

Plaintext password login app BeWell = Kode SID.

Pakai library bcrypt yang menghasilkan hash `$2y$` atau `$2b$` (PHP Laravel menerima keduanya di versi modern; utamakan `$2y$` jika mudah).

### 5.4 Normalisasi `status_karyawan`

| Input HSE (upper) | Hasil |
|---|---|
| kosong | `AKTIF` |
| mengandung `NON` / `INACTIVE` / `TIDAK` | `NONAKTIF` |
| lainnya | `AKTIF` |

Hanya dua nilai sah: `AKTIF` | `NONAKTIF`.

### 5.5 Skip baris invalid

Jika setelah mapping `kode_sid` atau `nama` kosong → `skipped_invalid`, jangan tulis DB.

### 5.6 Deactivate hanya jika roster lengkap

```
if failed_companies == 0 and len(pending_sids) > 0:
    deactivate_missing()
else:
    log.warning("skip deactivate karena fetch company tidak lengkap")
```

Kalau satu company gagal, menonaktifkan semua orang company itu = data rusak.

### 5.7 Unique SID / NIK

- Jangan insert SID yang sudah ada (race: catch duplicate, lalu UPDATE).
- NIK unique jika diisi; jika bentrok, log error SID itu, jangan gagalkan seluruh sync.

---

## 6. API HSE

### 6.1 Base & auth

```
BASE_URL = https://hseautomation.beraucoal.co.id   # override via env

Header semua request data:
  Accept: application/json
  x-api-key: <token>

Timeout request: 120 detik
```

Auth prioritas:

1. Env `HSE_API_KEY` (statis) → langsung jadi `x-api-key`
2. Fallback login Beats:

```
POST {BASE_URL}/beats/api/mobile/login
Body JSON: { "username": "...", "password": "..." }

Token dicari di:
  token, apiKey, api_key, accessToken,
  data.token, data.apiKey, data.accessToken,
  result.token, result.apiKey
```

### 6.2 Endpoints

| Langkah | Method | Path | Query |
|---|---|---|---|
| Perusahaan | GET | `/sid2/api/ftwApi/getCompany` | `page=1&size=1000` |
| Roster | GET | `/sid2/api/ftwApi/getEmployee` | `companyId={id}&page=1&size=30000` |
| Detail | GET | `/sid2/employeeInfo/bySid/{sid}` | `expand=` lihat di bawah |

`expand` detail (wajib, satu string):

```
employee.functionalPosition,employee.structuralPosition,employee.department,employee.company,dedicatedSite,employee.status,identities,competencies,licences
```

SID di path harus di-URL-encode.

### 6.3 Parse list JSON

Cek urutan key: `results`, `data`, `content`, `result`, `items`, `rows`, `list`.

Kalau valuenya object (bukan list), cek nested `data` / `content` / `items` / `rows`.

Kalau root sudah list of objects, pakai itu.

Company id dicari di: `id`, `companyId`, `company_id`, `idCompany`, `id_company`.

SID dari baris roster dicari di:

```
sid, sidCode, kodeSid, kode_sid, employeeSid,
employee.sid, employee.sidCode, employee.kodeSid, employee.kode_sid
```

### 6.4 Parse detail JSON

Jika ada key `data` / `result` / `content` / `employeeInfo` berupa object (bukan list), pakai itu sebagai root detail.

Object karyawan: `detail["employee"]` jika ada, else seluruh detail.

Retry: 3 percobaan total. Retry hanya untuk 5xx atau exception koneksi/timeout. **Jangan retry 4xx.**

Jeda antar detail: 50 ms (env `HSE_DETAIL_DELAY_MS`).

---

## 7. Mapping field HSE → `employee_profiles`

Hanya tulis kolom yang ada nilainya (jangan overwrite dengan NULL kecuali memang kosong di HSE dan Anda sengaja clear — default Laravel: field kosong tidak dikirim, jadi kolom lama **tetap**). **Rekomendasi Python:** UPDATE hanya kolom yang terisi di payload; jangan SET NULL massal.

| Kolom BeWell | Sumber HSE (prioritas kiri → kanan) | Catatan |
|---|---|---|
| `kode_sid` | detail.sid, detail.sidCode, employee.sid, employee.sidCode, employee.kodeSid, employee.kode_sid, fallback SID loop | Wajib |
| `nama` | employee.name, employee.nama, employee.fullName, detail.name | Wajib |
| `nik` | employee.nik, employee.noKtp, employee.ktp, detail.nik, lalu `identities[]` type mengandung nik/ktp | Optional |
| `site` | dedicatedSite.name, dedicatedSite.nama, dedicatedSite (string), employee.dedicatedSite.name, employee.site, employee.siteName | max 100 |
| `nama_perusahaan` | employee.company.name, company.nama, company.companyName, employee.namaPerusahaan | max 255 |
| `id_perusahaan` | employee.company.id, company.companyId, employee.idCompany, employee.companyId | int |
| `departement` | employee.department.name, department.nama, employee.departement, employee.departmentName | max 255 |
| `jabatan_fungsional` | employee.functionalPosition.name, functionalPosition.nama, employee.jabatanFungsional | max 255 |
| `jabatan_struktural` | employee.structuralPosition.name, structuralPosition.nama, employee.jabatanStruktural | max 255 |
| `divisi` | employee.division.name, division.nama, employee.divisi, employee.divisionName | max 255 |
| `status_karyawan` | employee.status.name, status.nama, employee.status, employee.statusKaryawan | normalisasi |
| `usia` | employee.usia, employee.age | int |
| `masa_kerja` | employee.masaKerja, masa_kerja, yearsOfService | max 100 |

Identities: array of `{type|jenis|identityType|name, value|number|nomor|nik}`. Ambil yang type kosong atau mengandung `nik`/`ktp`.

Abaikan nilai `""` dan `"-"`.

---

## 8. Database BeWell

### 8.1 Koneksi

Laravel config referensi (`bewell_db`):

```
Host     : tunnel local 127.0.0.1 (default port 3316)
          atau direct ke remote MySQL jika Python jalan di network yang sama
Database : bewell
Charset  : utf8mb4
```

Tunnel SSH (jika perlu, sama seperti Admin):

```
ssh -N -L 3316:<BEWELL_REMOTE_HOST>:3306 <SSH_USER>@<SSH_HOST> -p 22 -i <pkey>
```

Python connect ke `127.0.0.1:3316` setelah tunnel up. Ping `SELECT 1` dulu.

Kredensial: simpan di `.env` project Python, **jangan hardcode**.

### 8.2 SQL referensi

**Map SID existing (chunk 800):**

```sql
SELECT id, kode_sid
FROM employee_profiles
WHERE UPPER(TRIM(kode_sid)) IN (%s, %s, ...)
```

Duplikat SID → simpan `id` terkecil.

**Cari satu SID:**

```sql
SELECT id, kode_sid
FROM employee_profiles
WHERE UPPER(TRIM(kode_sid)) = %s
ORDER BY id
LIMIT 1
```

**UPDATE existing:**

```sql
UPDATE employee_profiles
SET
  nama = %s,
  status_karyawan = %s,
  nik = %s,
  site = %s,
  nama_perusahaan = %s,
  id_perusahaan = %s,
  departement = %s,
  divisi = %s,
  jabatan_fungsional = %s,
  jabatan_struktural = %s,
  usia = %s,
  masa_kerja = %s
WHERE id = %s
```

Jangan set kolom yang tidak ada di payload (hindari NULL-kan data lama). Bangun SET secara dinamis.

**INSERT baru (transaksi):**

```sql
START TRANSACTION;
SELECT id FROM employee_profiles ORDER BY id DESC LIMIT 1 FOR UPDATE;
-- next_id = (row.id or 0) + 1
-- while EXISTS (SELECT 1 FROM employee_profiles WHERE id = next_id): next_id += 1

INSERT INTO employee_profiles (
  id, kode_sid, nama, password_hash, status_karyawan,
  nik, site, nama_perusahaan, id_perusahaan, departement,
  divisi, jabatan_fungsional, jabatan_struktural, usia, masa_kerja
) VALUES (...);
COMMIT;
```

Jika `Duplicate entry` (errno 1062): jangan panic — SELECT ulang by SID, lalu UPDATE (race).

**Deactivate:**

```sql
SELECT id, kode_sid FROM employee_profiles WHERE status_karyawan = 'AKTIF';
-- filter di Python: UPPER(TRIM(kode_sid)) not in hse_set
UPDATE employee_profiles
SET status_karyawan = 'NONAKTIF'
WHERE id IN (...chunk 800...);
```

---

## 9. Struktur project Python (usulan)

```
hse-bewell-sync/
├── README.md                          # salin ringkasan dari dokumen ini
├── .env.example
├── requirements.txt
├── pyproject.toml                     # optional
├── src/
│   └── hse_bewell_sync/
│       ├── __init__.py
│       ├── config.py                  # baca env
│       ├── hse_client.py              # login, getCompany, getEmployee, getDetail, retry
│       ├── mapping.py                 # extract sid, map detail → payload, normalize status
│       ├── bewell_repo.py             # ping, sid map, update, insert, deactivate
│       └── sync.py                    # orchestrator = flow bagian 4
├── scripts/
│   └── run_sync.py                    # entrypoint CLI
├── tests/
│   ├── test_mapping.py
│   └── test_status.py
└── logs/                              # gitignore
```

`requirements.txt` usulan:

```
requests>=2.31
pymysql>=1.1
bcrypt>=4.1
python-dotenv>=1.0
```

CLI:

```bash
python -m scripts.run_sync
# atau
python scripts/run_sync.py --dry-run
```

`--dry-run`: fetch HSE + hitung inserted/updated/deactivated, **tidak** WRITE ke BeWell.

---

## 10. Environment (.env.example)

```env
# HSE Automation
HSE_BASE_URL=https://hseautomation.beraucoal.co.id
HSE_API_KEY=
HSE_USERNAME=
HSE_PASSWORD=
HSE_LOGIN_PATH=/beats/api/mobile/login
HSE_COMPANY_PAGE_SIZE=1000
HSE_EMPLOYEE_PAGE_SIZE=30000
HSE_TIMEOUT_SECONDS=120
HSE_DETAIL_DELAY_MS=50
HSE_MAX_RETRIES=3

# BeWell MySQL (isi dari ops, jangan commit nilai asli)
BEWELL_DB_HOST=127.0.0.1
BEWELL_DB_PORT=3316
BEWELL_DB_DATABASE=bewell
BEWELL_DB_USER=
BEWELL_DB_PASSWORD=

# Opsional: script bisa cek tunnel dulu
BEWELL_REQUIRE_PING=true

# Logging
LOG_LEVEL=INFO
LOG_FILE=logs/hse_bewell_sync.log
```

---

## 11. Logging (minimal)

Event yang wajib ada:

| Event | Isi |
|---|---|
| `sync.started` | jumlah company |
| `sync.company_failed` | companyId, error |
| `sync.deactivated` | count (atau skipped + alasan) |
| `sync.sids_ready` | seen, existing, new |
| `sync.detail_sample` | 1× di SID pertama: keys JSON (untuk debug mapping) |
| `sync.progress` | tiap 100: processed/total/inserted/updated/failed |
| `sync.sid_failed` | sid + error |
| `sync.finished` | inserted, updated, skipped_invalid, failed, deactivated |

Jangan log password, API key, atau NIK penuh di production (mask NIK jika perlu).

---

## 12. Menjalankan di Ubuntu

### 12.1 Sekali (tes)

```bash
cd /path/to/hse-bewell-sync
source .venv/bin/activate
python scripts/run_sync.py --dry-run
python scripts/run_sync.py
```

Pastikan SSH tunnel BeWell sudah nyala jika DB tidak direct.

### 12.2 Cron (malam, timeout panjang)

Sync bisa 1–2 jam (satu HTTP per SID).

```cron
# tiap hari 01:15 WITA
15 1 * * * /path/to/hse-bewell-sync/.venv/bin/python /path/to/hse-bewell-sync/scripts/run_sync.py >> /path/to/hse-bewell-sync/logs/cron.log 2>&1
```

Atau systemd timer + `TimeoutStartSec=3h`.

Jangan overlap: pakai lock file (`flock`) supaya cron berikutnya tidak jalan jika sync sebelumnya belum selesai.

```bash
flock -n /tmp/hse-bewell-sync.lock -c '/path/to/.venv/bin/python /path/to/scripts/run_sync.py'
```

---

## 13. Checklist tes

- [ ] Mapping: detail sample HSE punya `nama`, `company.name`, `status`, `dedicatedSite`
- [ ] SID existing: `id` BeWell **tidak berubah** sebelum vs sesudah
- [ ] SID existing: `password_hash` **tidak berubah**
- [ ] SID baru: baris muncul, `id` = max lama + n, login app pakai SID sebagai password
- [ ] Karyawan hilang dari HSE: jadi `NONAKTIF` (hanya jika semua company sukses)
- [ ] Simulasi 1 company 5xx: **tidak ada** deactivate
- [ ] `--dry-run` tidak menulis DB
- [ ] Duplikat SID beda casing: update id terkecil, tidak insert kedua
- [ ] Dashboard Evaluasi Well: KPI install / user aktif masih nyambung ke user yang sama

---

## 14. Yang dilarang

- `TRUNCATE employee_profiles`
- `DELETE FROM employee_profiles` lalu insert ulang
- `REPLACE INTO employee_profiles` (MySQL = delete + insert → `id` baru)
- `UPDATE ... SET id = ...`
- Reset `password_hash` untuk SID yang sudah ada
- Deactivate saat fetch company tidak lengkap
- Hardcode credential di git
- Menjalankan dua sync paralel tanpa lock

---

## 15. Referensi implementasi Laravel (Admin)

Kalau ragu, samakan dengan file ini di repo Admin:

- `app/Services/SportEvaluation/SportEvaluationHseEmployeeSyncService.php` — orchestrator + mapping
- `app/Services/SportEvaluation/SportEvaluationHseEmployeeApiClient.php` — HTTP HSE
- `app/Services/SportEvaluation/SportEvaluationEmployeeProfileService.php` — `create`, `update($id, $payload, false)`, `existingKodeSidMap`, `deactivateMissingFromHse`, `allocateNextEmployeeProfileId`
- `app/Jobs/SportEvaluation/SportEvaluationSyncHseEmployeesJob.php` — timeout 7200s, tries=1
- `config/services.php` key `evaluasi_well_hse`
- `config/database.php` connection `bewell_db`

---

## 16. Definition of done

Script Python:

1. Bisa dijalankan di Ubuntu via cron tanpa Laravel queue.
2. Hasil sync di BeWell setara tombol **Sync HSE** di `/evaluasi-well/users`.
3. Tidak memutus relasi aktivitas (`user_id` tetap).
4. Ada log summary + dry-run.
5. Gagal parsial (satu SID / satu company) tidak merusak seluruh roster.
)