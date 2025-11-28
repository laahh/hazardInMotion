# Troubleshooting SSH Connection

## Masalah: Connection Timeout

Error `Connection timed out` berarti komputer Anda tidak bisa mencapai SSH server di `13.212.87.127:22`.

## Kemungkinan Penyebab:

1. **Firewall/Wall blocking port 22**
   - Windows Firewall
   - Corporate firewall
   - Router firewall

2. **SSH server tidak aktif atau tidak accessible**
   - Server mungkin down
   - Security group di AWS mungkin tidak allow IP Anda

3. **Network issue**
   - VPN perlu aktif
   - Perlu connect ke corporate network dulu

## Solusi:

### 1. Cek Ping ke Server
```powershell
ping 13.212.87.127
```
Jika ping gagal, berarti ada masalah network/firewall.

### 2. Cek dengan Telnet (jika installed)
```powershell
telnet 13.212.87.127 22
```

### 3. Cek Windows Firewall
Pastikan Windows Firewall tidak block outbound connection ke port 22.

### 4. Contact Network Admin
Jika di corporate network, mungkin perlu:
- Whitelist IP 13.212.87.127
- Connect VPN dulu
- Request firewall exception

### 5. Cek AWS Security Group
Pastikan Security Group di AWS allow:
- Your IP address untuk SSH (port 22)
- Atau IP range yang sesuai

### 6. Alternatif: Gunakan VPN/Koneksi lain
Jika server hanya bisa diakses dari network tertentu, perlu connect VPN atau network tersebut dulu.

## Test Koneksi:

1. **Test ping:**
```powershell
ping 13.212.87.127
```

2. **Test SSH connectivity:**
```powershell
ssh -v ubuntu@13.212.87.127 -p 22 -i "C:\laragon\www\Admin\public\JumpHostVPC2.pem"
```
(flag `-v` untuk verbose output)

3. **Cek apakah port 22 open:**
```powershell
Test-NetConnection -ComputerName 13.212.87.127 -Port 22
```

## Jika tetap tidak bisa:

1. **Gunakan VPN** jika server hanya accessible dari network tertentu
2. **Contact AWS admin** untuk:
   - Tambahkan IP Anda ke Security Group
   - Cek apakah server masih aktif
   - Verify SSH key masih valid

3. **Cek apakah ada bastion host lain** yang bisa digunakan

4. **Alternatif:** Jika ada akses langsung ke database (tanpa SSH tunnel), bisa konfigurasi langsung di Laravel

