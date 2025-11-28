# Install PostgreSQL Extension untuk PHP

## Masalah
Error: "could not find driver" berarti PHP extension untuk PostgreSQL belum diaktifkan.

## Solusi untuk Laragon

### Langkah 1: Cek Extension Files
Extension PostgreSQL biasanya sudah ada di Laragon, hanya perlu diaktifkan.

Lokasi extension: `C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/ext/`

File yang diperlukan:
- `php_pdo_pgsql.dll` (untuk PDO PostgreSQL)
- `php_pgsql.dll` (untuk PostgreSQL native)

### Langkah 2: Aktifkan Extension di php.ini

1. **Buka Laragon** → klik kanan icon tray → **PHP** → **php.ini**

2. **Cari baris** (gunakan Ctrl+F):
   ```
   ;extension=pdo_pgsql
   ;extension=pgsql
   ```

3. **Hapus tanda `;`** di depan kedua baris tersebut:
   ```
   extension=pdo_pgsql
   extension=pgsql
   ```

4. **Save file** (Ctrl+S)

5. **Restart Laragon** atau restart Apache/Nginx:
   - Klik kanan icon Laragon → **Stop All**
   - Klik kanan icon Laragon → **Start All`

### Langkah 3: Verifikasi

Jalankan di PowerShell:
```powershell
php -m | findstr -i pgsql
```

Harus muncul:
```
pdo_pgsql
pgsql
```

### Jika Extension Tidak Ada

Jika file extension tidak ada di folder `ext/`, Anda perlu:

1. **Download PHP extension** dari PECL atau
2. **Update Laragon** ke versi terbaru yang sudah include extension PostgreSQL
3. **Atau install manual** dari: https://windows.php.net/downloads/pecl/releases/

### Alternatif: Gunakan PHP 8.2 atau 8.3

Jika PHP 8.1 tidak punya extension, coba switch ke PHP 8.2 atau 8.3:
- Laragon → klik kanan → **PHP** → pilih versi yang lebih baru

### Troubleshooting

Jika masih error setelah enable extension:
1. Pastikan file DLL ada di folder `ext/`
2. Pastikan tidak ada typo di php.ini
3. Restart Laragon/Apache
4. Cek error log: `C:/laragon/logs/php_error.log`

