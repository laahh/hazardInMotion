# Cara Enable PostgreSQL Extension di Laragon

## Masalah
Error: "could not find driver" saat menggunakan PostgreSQL di Laravel.

## Solusi

### 1. Buka file php.ini
File location: `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.ini`

### 2. Cari baris berikut (sekitar line 940):
```ini
;extension=pdo_pgsql
;extension=pgsql
```

### 3. Uncomment (hapus tanda `;`) menjadi:
```ini
extension=pdo_pgsql
extension=pgsql
```

### 4. Restart Laragon
- Stop Laragon (Stop All)
- Start Laragon lagi

### 5. Verify
Jalankan di PowerShell:
```powershell
php -m | findstr -i pgsql
```

Seharusnya muncul:
```
pdo_pgsql
pgsql
```

## Catatan
- File extension sudah ada di: `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\ext\`
- Hanya perlu enable di php.ini
- Setelah enable, restart Laragon/web server

