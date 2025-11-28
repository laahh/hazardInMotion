# ALTERNATIF: Akses Database Langsung (Tanpa SSH Tunnel)

Jika SSH server tidak bisa diakses dari komputer Anda, ada beberapa alternatif:

## Opsi 1: Gunakan VPN / Network yang Diizinkan

Jika server hanya bisa diakses dari network tertentu:
1. Connect VPN terlebih dahulu
2. Atau hubungkan ke network yang sesuai
3. Kemudian coba SSH tunnel lagi

## Opsi 2: Direct Database Connection (Jika Database Bisa Diakses Langsung)

Jika PostgreSQL database bisa diakses langsung dari internet, bisa konfigurasi langsung tanpa SSH tunnel.

Tambahkan ke file `.env`:

```env
# Direct PostgreSQL Connection (tanpa SSH tunnel)
DB_CONNECTION=pgsql
DB_HOST=postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com
DB_PORT=5432
DB_DATABASE=hse_automation
DB_USERNAME=safety_evaluator_2
DB_PASSWORD=safety123
```

Lalu update controller untuk menggunakan connection default.

## Opsi 3: Minta Admin untuk:
1. **Tambahkan IP Anda ke Security Group** di AWS (allow SSH dari IP Anda)
2. **Cek apakah server masih aktif**
3. **Verify bahwa SSH key masih valid**
4. **Buat VPN atau bastion host** yang bisa diakses dari lokasi Anda

## Opsi 4: Gunakan AWS Session Manager (Jika Tersedia)

Jika AWS Systems Manager Session Manager tersedia, bisa menggunakan itu untuk tunnel.

## Opsi 5: Remote Desktop ke Server yang Bisa Akses

Jika ada server lain yang bisa akses database tersebut, bisa remote desktop ke sana dan akses dari sana.

## Checklist Troubleshooting:

- [ ] Apakah Anda di corporate network yang perlu VPN?
- [ ] Apakah firewall company memblokir port 22?
- [ ] Apakah sudah contact admin untuk whitelist IP Anda?
- [ ] Apakah database bisa diakses langsung tanpa SSH tunnel?
- [ ] Apakah ada VPN atau network lain yang perlu di-connect dulu?

