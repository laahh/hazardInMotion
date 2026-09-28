# Setup Koneksi Database BeWell (SSH Tunnel)

Tutorial ini menjelaskan cara menyambungkan sebuah project Laravel ke database
`bewell` (MySQL) milik aplikasi BeWell. Database ini **tidak terbuka ke
publik** — hanya bisa diakses dari jaringan internal lewat jump server, jadi
koneksinya wajib lewat SSH tunnel.

Dipakai sebagai referensi kalau mau connect ke DB BeWell dari project lain
(bukan project Admin ini).

## 1. Arsitektur koneksi

```
Laravel App (project kamu)
      │
      │  koneksi mysql biasa ke 127.0.0.1:<LOCAL_PORT>
      ▼
SSH Tunnel (dibuka manual di komputer/server yang menjalankan Laravel)
      │
      │  forward lewat SSH ke jump server
      ▼
Jump Server (52.221.229.4, user ubuntu)
      │
      │  dari jump server, forward ke DB BeWell yang ada di jaringan internal
      ▼
DB Server BeWell (10.11.48.135:3306) — database "bewell"
```

Intinya: Laravel tidak pernah connect langsung ke `10.11.48.135`. Laravel
connect ke `127.0.0.1:<LOCAL_PORT>` di komputernya sendiri, dan SSH tunnel-lah
yang meneruskan koneksi itu ke DB BeWell yang sebenarnya, lewat jump server.

Kalau tunnel-nya mati (window SSH ditutup / proses SSH ke-kill), koneksi ke
`127.0.0.1:<LOCAL_PORT>` otomatis gagal — bukan error di Laravel-nya.

## 2. Kredensial & informasi koneksi

| Item | Nilai |
|---|---|
| Jump server host | `52.221.229.4` |
| Jump server port | `22` |
| Jump server user | `ubuntu` |
| Jump server auth | private key (`.pem`) — **minta file ini ke pemegang akses**, jangan commit ke git |
| DB host (di balik jump server) | `10.11.48.135` |
| DB port asli | `3306` |
| Local port (bebas, tidak boleh bentrok) | contoh: `3316` |
| Nama database | `bewell` |
| DB driver | MySQL |
| DB username / password | minta ke pemegang akses BeWell (jangan hardcode di kode/git) |

> File private key contoh di project ini ada di `public/BeMCU_jumpserver.pem`.
> Untuk project lain, minta salinan key yang sama (atau key baru kalau
> aksesnya beda), lalu simpan **di luar folder yang bisa diakses publik** dan
> **jangan pernah commit ke repo Git** — tambahkan ke `.gitignore`.

## 3. Prasyarat

- **Windows**: OpenSSH Client (biasanya sudah bawaan Windows 10/11). Cek
  dengan `ssh -V` di terminal.
- **Linux/Mac**: OpenSSH biasanya sudah terpasang bawaan.
- Akses jaringan keluar (outbound) ke port 22 tidak diblokir firewall/kantor.
- Private key `.pem` dengan permission yang benar (Linux/Mac: `chmod 600`).

## 4. Buka SSH tunnel (manual)

Tunnel ini **tidak otomatis dibuka oleh Laravel** — harus dijalankan manual
sebagai proses terpisah, dan dibiarkan berjalan selama aplikasi butuh akses
ke DB BeWell.

### Windows (batch script)

Buat file misalnya `start-bewell-tunnel.bat`:

```bat
@echo off
setlocal

set JUMP_HOST=52.221.229.4
set JUMP_USER=ubuntu
set JUMP_KEY=%~dp0path\ke\BeMCU_jumpserver.pem

set DB_HOST=10.11.48.135
set DB_REMOTE_PORT=3306
set LOCAL_PORT=3316

echo Tunnel: localhost:%LOCAL_PORT% -^> %DB_HOST%:%DB_REMOTE_PORT% (database bewell)
echo Biarkan window ini terbuka selama pakai koneksi bewell_db di Laravel.

ssh -N -L %LOCAL_PORT%:%DB_HOST%:%DB_REMOTE_PORT% -i "%JUMP_KEY%" %JUMP_USER%@%JUMP_HOST%

echo Tunnel terputus.
pause
endlocal
```

Jalankan dengan double-click atau lewat terminal, lalu **biarkan window-nya
terbuka**. Selama window itu hidup, port lokal 3316 aktif sebagai jalan
masuk ke DB BeWell.

### Linux/Mac (atau WSL)

```bash
ssh -N -L 3316:10.11.48.135:3306 -i /path/ke/BeMCU_jumpserver.pem ubuntu@52.221.229.4
```

- `-N` = tidak buka shell, cuma buat tunnel port forwarding.
- `-L local_port:remote_host:remote_port` = arahkan port lokal ke tujuan di
  balik jump server.

Jalankan di background kalau tidak mau nunggu terminal:

```bash
ssh -N -L 3316:10.11.48.135:3306 -i /path/ke/BeMCU_jumpserver.pem ubuntu@52.221.229.4 &
```

### Server/production (opsional — tunnel persisten)

Untuk server yang jalan terus-menerus (bukan laptop developer), pertimbangkan:

- **autossh** — otomatis reconnect kalau tunnel putus.
- **systemd service** yang menjalankan perintah `ssh -N -L ...` di atas dan
  auto-restart kalau proses mati.

Ini di luar scope Laravel — cukup pastikan port lokal yang dipakai di `.env`
selalu tersedia selama aplikasi jalan.

## 5. Konfigurasi Laravel

### 5.1. Tambahkan environment variable (`.env`)

```env
BEWELL_DB_HOST=127.0.0.1
BEWELL_DB_PORT=3316
BEWELL_DB_DATABASE=bewell
BEWELL_DB_USERNAME=bewelluser
BEWELL_DB_PASSWORD=isi_password_sebenarnya
BEWELL_DB_CONNECT_TIMEOUT=3
```

`BEWELL_DB_HOST` **selalu `127.0.0.1`** (bukan `10.11.48.135`) karena Laravel
connect ke ujung tunnel di komputernya sendiri, bukan langsung ke DB.

### 5.2. Tambahkan connection di `config/database.php`

Di dalam array `connections`, tambahkan:

```php
'bewell_db' => [
    'driver' => 'mysql',
    'host' => env('BEWELL_DB_HOST', '127.0.0.1'),
    'port' => env('BEWELL_DB_PORT', '3316'),
    'database' => env('BEWELL_DB_DATABASE', 'bewell'),
    'username' => env('BEWELL_DB_USERNAME', 'bewelluser'),
    'password' => env('BEWELL_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    // Fail-fast: jangan biarkan PHP menunggu default TCP timeout kalau
    // tunnel mati (bisa berujung 504 Gateway Timeout di request HTTP).
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::ATTR_TIMEOUT => (int) env('BEWELL_DB_CONNECT_TIMEOUT', 3),
    ]) : [],
],
```

Poin penting: `PDO::ATTR_TIMEOUT` kecil (2-3 detik) supaya kalau tunnel mati,
request langsung gagal cepat — bukan menggantung sampai timeout PHP/Nginx.

### 5.3. Pakai koneksi ini di query

```php
use Illuminate\Support\Facades\DB;

$rows = DB::connection('bewell_db')
    ->table('employee_profiles')
    ->where('status_karyawan', 'AKTIF')
    ->limit(10)
    ->get();
```

Atau kalau pakai Eloquent Model, set `protected $connection = 'bewell_db';`
di model-nya.

## 6. Cek koneksi hidup (opsional tapi disarankan)

Karena tunnel bisa mati kapan saja (window ditutup, laptop sleep, dst),
sebaiknya buat helper kecil yang cek koneksi sebelum query berat, supaya
halaman degradasi dengan pesan yang jelas — bukan error 500 mentah.

Pola yang dipakai di project Admin ([app/Services/SportEvaluation/BewellConnectionService.php](app/Services/SportEvaluation/BewellConnectionService.php)):

```php
final class BewellConnectionService
{
    public const CONNECTION = 'bewell_db';
    private const CACHE_KEY = 'bewell_is_up';
    private const CACHE_TTL_SECONDS = 20;

    public function isUp(): bool
    {
        return (bool) Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): bool {
            try {
                DB::connection(self::CONNECTION)->select('SELECT 1');
                return true;
            } catch (\Throwable $e) {
                return false; // jangan report() di sini, dipanggil sangat sering
            }
        });
    }
}
```

Dipakai di controller/service sebelum query berat:

```php
if (! $this->bewellConnection->isUp()) {
    return view('...', ['connectionUp' => false]); // tampilkan alert, bukan error
}
```

Cache TTL pendek (±20 detik) supaya tidak terus-menerus mencoba konek ulang
di setiap request saat tunnel memang lagi mati, tapi tetap cukup responsif
begitu tunnel dinyalakan lagi.

## 7. Testing

1. Jalankan tunnel (langkah 4).
2. Cek port lokal sudah listening:
   - Windows: `netstat -an | findstr 3316`
   - Linux/Mac: `lsof -i :3316` atau `netstat -an | grep 3316`
3. Test koneksi manual pakai MySQL client:
   ```bash
   mysql -h 127.0.0.1 -P 3316 -u bewelluser -p bewell
   ```
4. Kalau berhasil login dan bisa `SHOW TABLES;`, koneksi dari Laravel juga
   akan berhasil dengan config yang sama.

## 8. Troubleshooting

**`SSH: connect to host 52.221.229.4 port 22: Connection timed out`**
- Jaringan/firewall memblokir outbound port 22. Coba dari jaringan lain atau
  minta IT membuka akses.

**`Permission denied (publickey)`**
- Private key salah / belum diregister ke jump server. Pastikan file `.pem`
  benar dan (Linux/Mac) permission-nya `600`:
  ```bash
  chmod 600 /path/ke/BeMCU_jumpserver.pem
  ```

**`bind: Address already in use` saat buka tunnel**
- Local port (3316) sudah dipakai proses lain (mungkin tunnel lama masih
  jalan). Cek dengan `netstat`/`lsof`, matikan proses lama, atau pakai port
  lokal lain (`BEWELL_DB_PORT` tinggal diganti, tidak perlu sama persis
  3316).

**Laravel error `SQLSTATE[HY000] [2002] Connection refused` / timeout cepat**
- Tunnel belum dibuka atau baru saja terputus. Buka lagi tunnel-nya, lalu
  retry.

**Query jalan lambat / kadang timeout**
- Wajar untuk koneksi lewat SSH tunnel + jaringan internal. Batasi jumlah
  baris yang ditarik sekaligus (pagination/chunking), dan pertimbangkan
  cache hasil agregasi (`Cache::remember`) untuk data yang tidak perlu
  real-time betul.

## 9. Catatan keamanan

- Jangan commit `.pem` key, password DB, atau `.env` ke Git.
- Akses ke `bewell_db` sebaiknya **read-only** kecuali memang ada kebutuhan
  jelas untuk menulis — beri kredensial DB user yang scope-nya read-only
  kalau memungkinkan.
- Kalau project barunya perlu berjalan di server (bukan cuma laptop
  developer), koordinasikan dulu ke pemegang akses BeWell soal IP mana saja
  yang boleh tunnel ke jump server mereka.

## 10. Logika populasi "karyawan aktif" & status install (portable, DB-level)

Bagian ini murni menjelaskan **aturan bisnis di level data** (tabel, kolom,
kondisi) — tidak menyebut controller/class/file apa pun, supaya bisa
diterapkan identik di project mana pun yang query ke database `bewell` yang
sama. Ini dipakai untuk kasus: dashboard menampilkan populasi "karyawan
BeWell yang dihitung", dan angkanya **tidak sama** dengan jumlah baris
`employee_profiles` yang statusnya AKTIF — karena ada lapisan exclusion di
atasnya.

### Langkah 1 — Populasi dasar

Titik awal selalu tabel `employee_profiles`, difilter status aktif dulu:

```sql
SELECT *
FROM employee_profiles e
WHERE e.status_karyawan = 'AKTIF'
```

Baris hasil query ini **belum final** — masih harus dikurangi lagi lewat
"aturan exclude" di Langkah 2 sebelum dianggap sebagai populasi yang
ditampilkan/dihitung di dashboard.

### Langkah 2 — Aturan exclude (WHERE tambahan di atas Langkah 1)

Enam kondisi berikut digabung dengan `AND` (semua harus lolos supaya baris
tetap ikut dihitung). Kalau salah satu kondisi match, baris itu dibuang dari
populasi:

| # | Kondisi (kalau match → dibuang) | Alasan bisnis |
|---|---|---|
| 1 | `jabatan_fungsional` (huruf besar, di-trim) = `VISITOR` atau `PRESIDEN DIREKTUR` | dua jabatan ini tidak dihitung sebagai populasi karyawan biasa |
| 2 | `jabatan_fungsional` = `DIREKTUR` **dan** (`site` kosong **atau** `site` = `HO`) | direktur tanpa site lapangan yang jelas tidak ikut dihitung |
| 3 | `site` (huruf besar, di-trim) = `JAKARTA` atau `POLTEK` | dua site ini di luar populasi site operasional |
| 4 | `nama_perusahaan` (di-uppercase, dibuang spasi/titik/koma/strip) mengandung `POLITEKNIKSINARMAS`, mengandung `SINARMAS` + (`MARITIM` atau `MARITIN`), diawali `FUSI`/`PTFUSI`, atau mengandung `YAYASANDHARMABAKTI` | perusahaan-perusahaan ini di luar hitungan populasi "Performance" |
| 5 | `nama_perusahaan` (versi compact) = `PTBERAUCOAL` atau `BERAUCOAL`, **dan** `departement` (uppercase, di-trim) mengandung salah satu dari: `INTERNSHIP`, `POLTEK`, `KAMPUS MERDEKA`, `PRAKERIN` | anak magang/kampus merdeka PT Berau Coal, bukan karyawan tetap |
| 6 | `nama` mengandung kata `DUMMY` (case-insensitive) | data uji/testing, bukan karyawan sungguhan |

Versi SQL (`WHERE` tambahan yang ditempel ke Langkah 1):

```sql
AND UPPER(TRIM(COALESCE(e.jabatan_fungsional, ''))) NOT IN ('VISITOR', 'PRESIDEN DIREKTUR')
AND NOT (
    UPPER(TRIM(COALESCE(e.jabatan_fungsional, ''))) = 'DIREKTUR'
    AND (TRIM(COALESCE(e.site, '')) = '' OR UPPER(TRIM(e.site)) = 'HO')
)
AND UPPER(TRIM(COALESCE(e.site, ''))) NOT IN ('JAKARTA', 'POLTEK')
AND NOT (
    -- versi "compact" nama_perusahaan: UPPER + hapus spasi/titik/koma/strip
    REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(e.nama_perusahaan,''))),' ',''),'.',''),'-',''),',','') LIKE '%POLITEKNIKSINARMAS%'
    OR REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(e.nama_perusahaan,''))),' ',''),'.',''),'-',''),',','') LIKE '%SINARMAS%MARITIM%'
    OR REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(e.nama_perusahaan,''))),' ',''),'.',''),'-',''),',','') LIKE '%SINARMAS%MARITIN%'
    OR REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(e.nama_perusahaan,''))),' ',''),'.',''),'-',''),',','') LIKE 'FUSI%'
    OR REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(e.nama_perusahaan,''))),' ',''),'.',''),'-',''),',','') LIKE 'PTFUSI%'
    OR REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(e.nama_perusahaan,''))),' ',''),'.',''),'-',''),',','') LIKE '%YAYASANDHARMABAKTI%'
)
AND NOT (
    REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(e.nama_perusahaan,''))),' ',''),'.',''),'-',''),',','') IN ('PTBERAUCOAL', 'BERAUCOAL')
    AND (
        UPPER(TRIM(COALESCE(e.departement, ''))) LIKE '%INTERNSHIP%'
        OR UPPER(TRIM(COALESCE(e.departement, ''))) LIKE '%POLTEK%'
        OR UPPER(TRIM(COALESCE(e.departement, ''))) LIKE '%KAMPUS%MERDEKA%'
        OR UPPER(TRIM(COALESCE(e.departement, ''))) LIKE '%PRAKERIN%'
    )
)
AND (e.nama IS NULL OR UPPER(e.nama) NOT LIKE '%DUMMY%')
```

**Jumlah baris yang tersisa setelah Langkah 1 + Langkah 2** itulah "populasi
karyawan yang dihitung" di dashboard — mencakup karyawan yang **sudah**
maupun **belum** install (status install belum ikut campur di tahap ini,
lihat Langkah 3). Filter dropdown UI (site/perusahaan/divisi/dst) cuma
narrowing opsional di atas populasi ini, bukan bagian dari aturan exclude.

> ⚠️ **Koreksi penting**: angka badge/ringkasan yang ditampilkan di dashboard
> untuk kartu "status install" **bukan** angka populasi Langkah 1+2 di atas.
> Angka itu sudah dipersempit lagi khusus ke subset **"Belum Install"** saja
> (lihat Langkah 3 di bawah) — karyawan yang **sudah** install **tidak ikut**
> dihitung di angka itu. Populasi penuh (sudah + belum install) baru
> kelihatan kalau filter status install diubah ke "Semua"/"Sudah".

> Opsional: kalau aplikasi kamu punya konsep sub-akun/mitra yang hanya
> boleh lihat sebagian karyawan (bukan semua), tambahkan satu filter lagi
> di paling akhir: `AND e.id IN (daftar id yang di-assign ke akun itu)`.
> Ini murni scoping akses per-role, bukan bagian dari 6 aturan exclude di
> atas — kalau akunnya full-access, filter ini tidak perlu diterapkan sama
> sekali.

### Langkah 3 — Status terhitung: Install & User Aktif (bukan exclusion)

Dua kolom ini dihitung per-karyawan (dari sisa yang lolos exclusion), lewat
`EXISTS (...)` — artinya: "apakah pernah ada minimal 1 baris yang cocok
kondisi ini di tabel lain, untuk `user_id` = karyawan ini". Tidak ada flag
"installed" eksplisit di database — status ini murni **hasil tebakan tidak
langsung** dari jejak pemakaian di 3 tabel: `login_audit`, `food_analyses`,
`workout_analyses`.

#### Logika "Install" (kolom `is_installed`)

Karyawan dianggap **"Sudah"** install kalau **salah satu** (OR, bukan AND)
dari 3 kondisi ini terpenuhi, **sepanjang masa** — tidak dibatasi tanggal
sama sekali:

1. Pernah ada baris di tabel `login_audit` untuk user ini dengan
   `event = 'login_success'` → pernah berhasil login ke app BeWell,
   kapan pun, minimal sekali seumur hidup akun.
2. Pernah ada **minimal 1 baris apa pun** di tabel `food_analyses` untuk
   user ini (apa pun `source_type`-nya — foto atau input manual, tidak
   dibedakan di sini).
3. Pernah ada **minimal 1 baris apa pun** di tabel `workout_analyses`
   untuk user ini (jenis aktivitas apa pun).

Kalau **ketiga-tiganya kosong** (tidak pernah login sukses tercatat, tidak
pernah ada log makanan, tidak pernah ada log olahraga) → `is_installed = 0`
→ label **"Belum"**.

Implikasi yang perlu disadari:

- Ini **bukan** deteksi app ter-install di HP secara teknis (tidak ada
  event "app installed" dari device). Ini proxy: "ada jejak pemakaian apa
  pun" = dianggap sudah install. Kalau seseorang install app tapi belum
  pernah berhasil login **dan** belum pernah menghasilkan data apa pun,
  tetap dihitung **"Belum"** — walau app-nya sudah ada di HP-nya.
- Sekali saja pernah kejadian (login sukses / 1 baris makanan / 1 baris
  olahraga) di masa lalu — walau sudah setahun tidak dipakai lagi — tetap
  selamanya dihitung **"Sudah"**. Kolom ini tidak pernah "mundur" jadi
  "Belum" lagi.
- Karena pakai `EXISTS`, SQL berhenti begitu ketemu 1 baris yang cocok
  (tidak perlu hitung total baris), jadi relatif ringan walau dicek per
  karyawan.

#### Cara melihat daftar karyawan yang "Sudah Install" (login dihitung install)

Poin yang sering bikin bingung: **karyawan yang pernah login sukses,
otomatis dianggap "Sudah Install"** — walau dia belum pernah sekali pun
mengisi log makanan atau olahraga. Ini karena 3 kondisi di atas digabung
dengan **OR**, jadi cukup **salah satu** saja yang pernah terjadi:

- pernah login sukses **ATAU**
- pernah ada log makanan **ATAU**
- pernah ada log olahraga

→ ketiganya sama-sama dianggap bukti "app sudah dipakai", jadi status-nya
"Sudah Install". Tidak ada prioritas/urutan antar ketiganya — login-only,
makan-only, olahraga-only, atau kombinasi mana pun, hasilnya sama: "Sudah".

Query siap pakai untuk **melihat daftar** karyawan yang sudah install
(dari populasi yang sudah lolos exclude di Langkah 1+2), sekaligus
menunjukkan **kondisi mana yang bikin dia dianggap sudah install** —
supaya kelihatan jelas mana yang cuma pernah login vs yang memang aktif
pakai fitur makanan/olahraga:

```sql
SELECT * FROM (
    SELECT
        e.id,
        e.nama,
        e.kode_sid,
        e.nama_perusahaan,
        e.site,
        CASE WHEN EXISTS (
            SELECT 1 FROM login_audit a
            WHERE a.user_id = e.id AND a.event = 'login_success'
        ) THEN 1 ELSE 0 END AS pernah_login_sukses,
        CASE WHEN EXISTS (
            SELECT 1 FROM food_analyses f
            WHERE f.user_id = e.id
        ) THEN 1 ELSE 0 END AS pernah_log_makanan,
        CASE WHEN EXISTS (
            SELECT 1 FROM workout_analyses w
            WHERE w.user_id = e.id
        ) THEN 1 ELSE 0 END AS pernah_log_olahraga
    FROM employee_profiles e
    WHERE e.status_karyawan = 'AKTIF'
      -- tempel WHERE tambahan Langkah 2 di sini (6 aturan exclude)
) t
WHERE t.pernah_login_sukses = 1
   OR t.pernah_log_makanan = 1
   OR t.pernah_log_olahraga = 1
```

Cara baca hasilnya:

- Baris dengan `pernah_login_sukses = 1` tapi `pernah_log_makanan = 0` dan
  `pernah_log_olahraga = 0` → karyawan yang **cuma pernah login**, belum
  pernah pakai fitur apa pun setelahnya. Tetap terhitung "Sudah Install".
- Baris dengan `pernah_login_sukses = 0` tapi salah satu dari 2 kolom
  lain = 1 → karyawan yang datanya masuk lewat food/workout tanpa ada
  jejak login sukses tercatat di `login_audit` (mis. token lama, atau
  login dicatat sebelum tabel audit ini ada) — tetap dihitung "Sudah
  Install" juga, karena aturannya OR, bukan AND.

Sengaja dipisah 3 kolom (bukan langsung 1 kolom `is_installed`) supaya
query ini enak dipakai untuk investigasi manual — kalau cuma butuh
flag ringkas seperti di dashboard, cukup gabung ketiganya jadi 1 kolom:

```sql
SELECT
    e.id,
    e.nama,
    CASE WHEN EXISTS (
        SELECT 1 FROM login_audit a
        WHERE a.user_id = e.id AND a.event = 'login_success'
    ) OR EXISTS (
        SELECT 1 FROM food_analyses f WHERE f.user_id = e.id
    ) OR EXISTS (
        SELECT 1 FROM workout_analyses w WHERE w.user_id = e.id
    ) THEN 'Sudah' ELSE 'Belum' END AS status_install
FROM employee_profiles e
WHERE e.status_karyawan = 'AKTIF'
  -- tempel WHERE tambahan Langkah 2 di sini
```

Untuk **jumlah total** karyawan "Sudah Install" (kebalikan dari badge
"Belum Install" di bagian sebelumnya), tinggal `COUNT(*)` dari query
derived-table pertama di atas — atau, lebih simpel: total populasi
Langkah 1+2 **dikurangi** angka "Belum Install":

```
Total Sudah Install = Total populasi (Langkah 1+2) − Total Belum Install
```

#### Angka ringkasan "Belum Install" (badge) — subset, bukan populasi penuh

Kalau dashboard kamu menampilkan angka ringkasan besar (badge/KPI) untuk
"karyawan belum install", **jangan** hitung itu dari jumlah populasi
Langkah 1+2 (yang mencakup sudah + belum install). Angka itu harus dihitung
dari query terpisah yang mempersempit lagi khusus ke yang **belum** install
— kebalikan (`NOT EXISTS`) dari 3 kondisi `is_installed` di atas, digabung
dengan `AND` (bukan `OR`):

```sql
-- ditempel di atas populasi Langkah 1+2 (employee_profiles yang sudah lolos exclude)
AND NOT EXISTS (
    SELECT 1 FROM login_audit a
    WHERE a.user_id = e.id AND a.event = 'login_success'
)
AND NOT EXISTS (
    SELECT 1 FROM food_analyses f
    WHERE f.user_id = e.id
)
AND NOT EXISTS (
    SELECT 1 FROM workout_analyses w
    WHERE w.user_id = e.id
)
```

`COUNT(*)` dari query ini = jumlah karyawan yang **belum pernah** login
sukses **dan** belum pernah ada log makanan **dan** belum pernah ada log
olahraga sama sekali. **Karyawan yang sudah install (salah satu dari 3
kondisi itu pernah terjadi) tidak ikut di angka ini.**

Kalau UI kamu juga punya dropdown filter "Install: Semua / Sudah / Belum"
dengan default terpilih "Belum", angka badge dan isi tabel akan terlihat
konsisten karena sama-sama merujuk ke subset yang sama — tapi begitu
filter diganti ke "Sudah" atau "Semua", tabel akan menampilkan karyawan
lain lagi (yang sudah install) yang **tidak** terhitung di angka badge.

#### Logika "User Aktif" (kolom `is_weekly_active`)

Beda dari "Install", kolom ini **dibatasi rentang waktu**: minggu berjalan,
dari **Senin 00:00:00** sampai **Minggu 23:59:59**, dihitung dari waktu
server saat halaman dibuka (`Carbon::now()->startOfWeek()/endOfWeek()`).
Rentang ini otomatis geser tiap minggu — bukan tanggal tetap yang di-hardcode.

Karyawan dianggap **"Ya"** (aktif minggu ini) kalau **salah satu** dari 2
kondisi ini terpenuhi, **dan** kejadiannya jatuh di dalam rentang minggu
berjalan itu:

1. Ada baris di `food_analyses` untuk user ini dengan
   `source_type = 'photo'` **dan** `created_at` ada di antara Senin–Minggu
   minggu ini. Perhatikan: **hanya upload foto makanan** yang dihitung —
   kalau user mencatat makanan lewat cara lain (bukan foto), itu **tidak**
   membuat dia dihitung aktif di kolom ini.
2. Ada baris di `workout_analyses` untuk user ini dengan `created_at` ada
   di antara Senin–Minggu minggu ini — jenis olahraga apa pun, tidak ada
   pembatasan `source_type` di sini (beda dengan poin 1).

Kalau tidak ada satu pun dari 2 kejadian itu di dalam minggu berjalan →
`is_weekly_active = 0` → label **"Tidak"**.

Implikasi:

- Kolom ini **reset setiap minggu**. Karyawan yang aktif minggu lalu tapi
  belum ada aktivitas sama sekali minggu ini akan tampil "Tidak", walau di
  kolom "Install" tetap "Sudah" (karena Install tidak pernah reset).
- Kombinasi paling umum yang bikin orang salah paham: **Install = Sudah**
  tapi **User Aktif = Tidak** → artinya dia *pernah* pakai app (minimal
  sekali, kapan pun), tapi *minggu ini* belum ada log foto makanan atau
  olahraga sama sekali.

### Kalau mau dipakai konsisten di project lain

Kalau project baru butuh populasi karyawan BeWell yang angkanya harus
konsisten dengan dashboard ini (mis. laporan lintas-sistem yang harus
exclude nama yang sama), tempel ulang **WHERE tambahan di Langkah 2** apa
adanya ke query `employee_profiles` di project baru itu. Jangan query
`employee_profiles` mentah hanya dengan `status_karyawan = 'AKTIF'` saja —
hasilnya akan beda angka dengan populasi yang benar, karena 6 aturan
exclude di atas belum ikut diterapkan.
