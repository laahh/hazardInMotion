# Quick Fix Guide - SSH Tunnel Error

## Masalah
SSH tunnel gagal dibuat karena beberapa alasan:
1. Path PEM file belum dikonfigurasi dengan benar
2. Windows SSH memerlukan cara khusus untuk background process

## Solusi Cepat

### Opsi 1: Setup Manual SSH Tunnel (Recommended)

1. **Buka PowerShell atau Command Prompt baru**

2. **Jalankan command ini** (ganti path pem file dengan path yang benar):
```powershell
ssh -N -L 5433:postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com:5432 ubuntu@13.212.87.127 -p 22 -i C:\path\to\your\key.pem
```

   **Atau jika tidak pakai key file:**
```powershell
ssh -N -L 5433:postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com:5432 ubuntu@13.212.87.127 -p 22
```

3. **Biarkan terminal itu tetap terbuka** (jangan tutup)

4. **Refresh halaman database viewer** di browser (`/database`)

### Opsi 2: Update File .env

1. Buka file `.env` di project Laravel

2. Update path SSH_PKEY dengan path yang benar ke file PEM Anda:
```env
SSH_PKEY=C:\laragon\www\Admin\storage\app\keys\your-key.pem
```

   **Atau jika tidak pakai key file:**
```env
SSH_PKEY=
SSH_PASSWORD=your_password_here
```

3. Pastikan file PEM ada di lokasi tersebut dan bisa dibaca

4. Coba refresh halaman lagi

### Opsi 3: Gunakan PuTTY (Alternatif)

Jika OpenSSH tidak bekerja, install PuTTY dan gunakan `plink`:

1. Download PuTTY dari: https://www.putty.org/

2. Install dan tambahkan ke PATH, atau gunakan full path ke plink.exe

3. Update code untuk menggunakan plink (perlu modifikasi SshTunnelService.php)

## Troubleshooting

### Cek apakah port sudah digunakan:
```powershell
netstat -an | findstr 5433
```

Jika ada output, berarti tunnel sudah berjalan.

### Test koneksi SSH:
```powershell
ssh ubuntu@13.212.87.127 -p 22 -i C:\path\to\key.pem
```

Jika berhasil connect, berarti SSH credentials benar.

### Cek log Laravel:
```powershell
Get-Content storage\logs\laravel.log -Tail 50
```

File log akan memberikan informasi lebih detail tentang error yang terjadi.

## Solusi Permanen

Untuk membuat tunnel otomatis berjalan, Anda bisa:

1. **Setup Windows Task Scheduler** untuk menjalankan SSH tunnel saat startup
2. **Gunakan NSSM (Non-Sucking Service Manager)** untuk menjalankan SSH tunnel sebagai Windows Service
3. **Manual tunnel** - buka terminal baru setiap kali akan menggunakan database viewer

