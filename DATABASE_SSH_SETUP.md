# Database SSH Tunnel Configuration

## Setup Instructions

### 1. Environment Variables

Tambahkan konfigurasi berikut ke file `.env`:

```env
# SSH Configuration
SSH_HOST=13.212.87.127
SSH_PORT=22
SSH_USER=ubuntu
SSH_PASSWORD=
SSH_PKEY=/path/to/your/pem/file.pem

# PostgreSQL Configuration (via SSH Tunnel)
PG_HOST=postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com
PG_PORT=5432
PG_SSH_HOST=127.0.0.1
PG_SSH_LOCAL_PORT=5433
PG_SSH_DATABASE=hse_automation
PG_SSH_USER=safety_evaluator_2
PG_SSH_PASSWORD=safety123
```

### 2. SSH Client Requirements

#### Windows:
- Install OpenSSH Client (Windows 10/11 biasanya sudah include)
- Atau install PuTTY dan gunakan plink.exe
- Pastikan SSH client ada di PATH system

#### Linux/Mac:
- OpenSSH biasanya sudah terinstall secara default

### 3. Private Key Setup

Jika menggunakan private key (.pem file):
- Letakkan file .pem di tempat yang aman (misalnya: `storage/app/keys/`)
- Pastikan permission file hanya bisa dibaca oleh user aplikasi
- Update path di `SSH_PKEY` di file `.env`

### 4. Testing Connection

1. Akses route: `/database`
2. Jika SSH tunnel berhasil, akan muncul daftar semua tabel
3. Klik "View Data" pada tabel untuk melihat isi lengkap

### 5. Troubleshooting

**Error: SSH tunnel failed to establish**
- Pastikan SSH client terinstall dan ada di PATH
- Windows: Jalankan `ssh` di command prompt untuk test
- Check apakah port 5433 sudah digunakan: `netstat -an | findstr 5433`
- Pastikan private key path benar dan file dapat dibaca

**Error: Connection refused**
- Pastikan SSH server dapat diakses
- Check firewall settings
- Verify SSH credentials

**Error: Database connection failed**
- Pastikan SSH tunnel sudah berjalan (port 5433 listening)
- Check database credentials
- Verify PostgreSQL server dapat diakses dari SSH server

### 6. Manual SSH Tunnel (Alternative)

Jika automated tunnel tidak bekerja, bisa setup manual:

```bash
# Linux/Mac
ssh -N -L 5433:postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com:5432 ubuntu@13.212.87.127 -i /path/to/pem/file.pem

# Windows (PowerShell)
ssh -N -L 5433:postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com:5432 ubuntu@13.212.87.127 -i C:\path\to\pem\file.pem
```

Kemudian pastikan `PG_SSH_HOST=127.0.0.1` dan `PG_SSH_LOCAL_PORT=5433` di file `.env`.

