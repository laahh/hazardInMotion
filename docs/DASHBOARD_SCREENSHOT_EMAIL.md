# Fitur Auto Screenshot Dashboard & Kirim Email

Fitur ini mengambil screenshot halaman Dashboard DOPM secara otomatis dan mengirimkannya ke email yang dikonfigurasi, **3 kali sehari**: pagi (07:00), siang (12:00), dan sore (17:00).

## Persyaratan

- **PHP 8.2+** (sesuai requirement Laravel project)
- **Node.js** (LTS disarankan, e.g. Node 18/20)
- **Puppeteer** (di-install via npm)
- **Chrome/Chromium** (biasanya ikut ter-install dengan Puppeteer)

## Langkah 1: Install Browsershot & Puppeteer

### 1.1 Install package PHP (pastikan menggunakan PHP 8.2)

```bash
# Pastikan CLI memakai PHP 8.2, contoh di Laragon:
# C:\laragon\bin\php\php-8.2.x\php.exe C:\laragon\bin\composer\composer.phar require spatie/browsershot

composer require spatie/browsershot
```

### 1.2 Install Puppeteer (Node.js)

Di **root project** (folder yang sama dengan `package.json`):

```bash
npm install puppeteer
```

Jika project belum punya `package.json`:

```bash
npm init -y
npm install puppeteer
```

Di **Windows (Laragon)**: pastikan Node.js dan npm sudah ter-install. Jika memakai Laragon, Node bisa diaktifkan dari Laragon Menu.

Di **Linux (production)** sering perlu dependency sistem untuk headless Chrome:

```bash
# Contoh Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2
```

## Langkah 2: Konfigurasi .env

Tambahkan atau edit di file `.env`:

```env
# Penting: agar screenshot menangkap DASHBOARD (bukan halaman login), set token:
# Command akan memakai URL: {APP_URL}/dopmikk/dopm/dashboard/screenshot?token={token}
DASHBOARD_SCREENSHOT_TOKEN=your-secret-token-minimal-16-karakter

# User ID yang dipakai untuk render dashboard saat akses via token (default: 1)
DASHBOARD_SCREENSHOT_USER_ID=1

# Jika DASHBOARD_SCREENSHOT_TOKEN tidak di-set, URL di bawah yang dipakai (bisa dapat halaman login)
# DASHBOARD_SCREENSHOT_URL=https://besentry.beraucoal.co.id/dopmikk/dopm/dashboard

# Email penerima, pisahkan dengan koma untuk banyak penerima
DASHBOARD_SCREENSHOT_EMAILS=admin@example.com,manager@example.com

# Opsional: timeout (detik), default 60
DASHBOARD_SCREENSHOT_TIMEOUT=60

# Opsional: ukuran viewport (px)
DASHBOARD_SCREENSHOT_WIDTH=1920
DASHBOARD_SCREENSHOT_HEIGHT=1080
```

Pastikan juga konfigurasi **mail** sudah benar (untuk mengirim email):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

## Langkah 3: Jadwal Cron (agar jalan otomatis 3x sehari)

Laravel scheduler sudah di-set di `app/Console/Kernel.php`:

| Waktu  | Slot  | Command                              |
|--------|--------|--------------------------------------|
| 07:00  | Pagi   | `php artisan dashboard:screenshot-send --slot=pagi`  |
| 12:00  | Siang  | `php artisan dashboard:screenshot-send --slot=siang` |
| 17:00  | Sore   | `php artisan dashboard:screenshot-send --slot=sore`  |

Agar scheduler jalan, **cron server** harus menjalankan Laravel scheduler setiap menit.

### Di server (Linux)

Edit crontab:

```bash
crontab -e
```

Tambahkan baris (sesuaikan path ke project dan PHP):

```cron
* * * * * cd /path/ke/project/Admin && php artisan schedule:run >> /dev/null 2>&1
```

Contoh dengan PHP dari Laragon di Windows (Task Scheduler):

- Program: `C:\laragon\bin\php\php-8.2.x\php.exe`
- Arguments: `C:\laragon\www\Admin\artisan schedule:run`
- Start in: `C:\laragon\www\Admin`
- Trigger: setiap 1 menit (atau setidaknya pada 07:00, 12:00, 17:00 jika tidak ingin setiap menit)

## Langkah 4: Tes manual

Tanpa menunggu jadwal, Anda bisa tes screenshot dan kirim email manual:

```bash
# Screenshot + kirim email (slot pagi)
php artisan dashboard:screenshot-send --slot=pagi

# Hanya screenshot, tidak kirim email (file disimpan di storage/app/dashboard-screenshots)
php artisan dashboard:screenshot-send --slot=siang --no-email

# Override URL (opsional)
php artisan dashboard:screenshot-send --slot=sore --url=https://besentry.beraucoal.co.id/dopmikk/dopm/dashboard
```

Log perintah ini ditulis ke: `storage/logs/dashboard-screenshot.log`

## Ringkasan file yang ditambah/diubah

| File | Keterangan |
|------|------------|
| `config/dashboard_screenshot.php` | Konfigurasi URL, email, timeout, ukuran viewport |
| `app/Mail/DashboardScreenshotMail.php` | Mailable untuk email + attachment screenshot |
| `resources/views/emails/dashboard-screenshot.blade.php` | Template body email |
| `app/Console/Commands/SendDashboardScreenshot.php` | Artisan command screenshot + kirim email |
| `app/Console/Kernel.php` | Jadwal 3x sehari (07:00, 12:00, 17:00) |

## Mengubah jam pengiriman

Edit `app/Console/Kernel.php`, ubah method `schedule()`:

- Pagi: `->dailyAt('07:00')` → ganti angka jam yang diinginkan.
- Siang: `->dailyAt('12:00')`.
- Sore: `->dailyAt('17:00')`.

Setelah mengubah, pastikan cron/Task Scheduler tetap menjalankan `schedule:run` seperti di atas.
