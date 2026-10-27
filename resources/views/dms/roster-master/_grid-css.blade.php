{{-- Gaya tabel jadwal bergaya template Excel — dipakai halaman Detail & Overview Roster. --}}
<style>
  /* Susunan kolom mengikuti template Excel "ROSTER KERJA KARYAWAN"; warnanya
     versi web yang lebih tenang (file Excel tetap memakai warna KODE). */
  .rm-xl { border-collapse: separate; border-spacing: 0; font-size: 12px; color: #0F172A; }
  .rm-xl th, .rm-xl td { white-space: nowrap; border-right: 1px solid #EEF2F7; border-bottom: 1px solid #EEF2F7; padding: 4px 8px; }
  .rm-xl tr > :first-child { border-left: 1px solid #E2E8F0; }
  .rm-xl thead tr:first-child > * { border-top: 1px solid #E2E8F0; }
  .rm-xl thead th { position: sticky; z-index: 2; text-align: center; font-weight: 600; }
  .rm-xl thead tr:nth-child(1) th { top: 0; }
  .rm-xl thead tr:nth-child(2) th { top: 26px; }
  .rm-xl thead tr:nth-child(3) th { top: 46px; }
  .rm-xl thead tr:last-child th, .rm-xl thead .rm-h-id { border-bottom-color: #E2E8F0; }
  .rm-xl .rm-h-id { background: #F8FAFC; color: #64748B; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; vertical-align: middle; white-space: normal; line-height: 1.3; }
  .rm-xl .rm-h-tgl { background: #F8FAFC; color: #0F172A; font-size: 12px; font-weight: 700; height: 26px; min-width: 40px; border-bottom: 0; }
  .rm-xl .rm-h-hari { background: #F8FAFC; color: #64748B; font-size: 10px; font-weight: 600; height: 20px; border-bottom: 0; padding-top: 0; padding-bottom: 0; }
  .rm-xl .rm-h-bln { background: #F8FAFC; color: #94A3B8; font-size: 9.5px; font-weight: 500; text-transform: uppercase; height: 20px; padding-top: 0; }
  .rm-xl .rm-minggu { background: #FEF2F2 !important; color: #DC2626 !important; }
  .rm-xl .rm-c-id { background: #fff; color: #0F172A; }
  .rm-xl .rm-c-no, .rm-xl .rm-c-sid { color: #64748B; font-variant-numeric: tabular-nums; }
  .rm-xl tbody tr:hover > .rm-c-id { background: #F8FAFF; }
  .rm-xl .rm-c-no, .rm-xl .rm-c-sid, .rm-xl .rm-c-jam { text-align: center; }
  /* Tiga kolom pertama (No, SID, Nama) tetap terlihat saat digulir ke kanan. */
  .rm-xl .rm-s1, .rm-xl .rm-s2, .rm-xl .rm-s3 { position: sticky; z-index: 1; }
  .rm-xl thead .rm-s1, .rm-xl thead .rm-s2, .rm-xl thead .rm-s3 { z-index: 4; }
  .rm-xl .rm-s1 { left: 0; min-width: 40px; }
  .rm-xl .rm-s2 { left: 40px; min-width: 76px; }
  .rm-xl .rm-s3 { left: 116px; min-width: 190px; box-shadow: 1px 0 0 #E2E8F0, 4px 0 8px -4px rgba(15, 23, 42, .12); }
  .rm-xl .rm-c-nama { font-weight: 600; }
  .rm-xl .rm-c-kode { padding: 3px; text-align: center; }
  .rm-sel { min-width: 34px; height: 22px; line-height: 22px; border-radius: 6px; text-align: center; font-weight: 600; font-size: 10.5px; }
  .rm-xl .rm-bulan { border-left: 2px solid #A5B4FC !important; }

  /* ── Responsif ─────────────────────────────────────────────────────────
     ≥ 992 px : semua kolom identitas seperti template Excel.
     < 992 px : Perusahaan, Jabatan, Site, Jam Kerja disembunyikan — isinya
                diringkas di bawah nama, supaya kolom tanggal langsung terlihat.
     < 576 px : No & SID ikut disembunyikan; hanya kolom Nama yang menempel kiri. */
  .rm-isi { padding: 24px; }
  .rm-gulir { max-height: 70vh; -webkit-overflow-scrolling: touch; }
  .rm-cari { width: 260px; max-width: 100%; }
  .rm-kepala-teks { min-width: 0; flex: 1 1 420px; }
  .rm-xl .rm-w-pt { max-width: 200px; min-width: 150px; }
  .rm-xl .rm-w-jab { max-width: 180px; min-width: 130px; }
  .rm-xl .rm-w-kecil { min-width: 64px; }
  .rm-potong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .rm-nama { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }
  .rm-ringkas { display: none; }
  @media (max-width: 991.98px) {
    .rm-xl .rm-opsional { display: none; }
    .rm-xl .rm-s3 { min-width: 170px; max-width: 190px; white-space: normal; }
    .rm-nama { max-width: 175px; font-weight: 600; }
    .rm-ringkas { display: block; font-size: 10px; line-height: 1.3; color: #64748B; white-space: normal; margin-top: 1px; }
    .rm-ringkas-sid { display: none; }
    .rm-cari { width: 100%; }
  }
  @media (max-width: 575.98px) {
    .rm-isi { padding: 12px; }
    .rm-gulir { max-height: 65vh; }
    .rm-xl .rm-hp-sembunyi { display: none; }
    .rm-xl .rm-s3 { left: 0; min-width: 140px; max-width: 150px; }
    .rm-nama { max-width: 135px; }
    .rm-ringkas-sid { display: inline; }
    .rm-xl .rm-h-tgl, .rm-sel { min-width: 32px; }
    .rm-sel { font-size: 10.5px; }
    .rm-hal { width: 100%; }
    .rm-hal .btn { flex: 1 1 0; }
  }
  /* Warna kode versi web: satu keluarga warna lembut per jenis (Day, Night, …).
     Template Excel tetap memakai warna DmsRosterMasterService::KODE. */
  @php
    $warnaWeb = ['D' => ['FEF3C7', '92400E'], 'N' => ['E0E7FF', '3730A3'], 'OFF' => ['F1F5F9', '64748B'],
                 'CT' => ['FFEDD5', '9A3412'], 'S' => ['FEE2E2', 'B91C1C'], 'I' => ['F3E8FF', '7E22CE']];
  @endphp
  @foreach (array_keys(\App\Services\Dms\Roster\DmsRosterMasterService::KODE) as $kodeCss)
  @php [$latar, $teks] = $warnaWeb[preg_replace('/\d+$/', '', $kodeCss)] ?? ['F1F5F9', '334155']; @endphp
  .rm-{{ $kodeCss }} { background: #{{ $latar }}; color: #{{ $teks }}; }
  @endforeach
  /* Kode lama (sebelum D1–D13 / N1–N7) tetap berwarna. */
  .rm-DS { background: #FEF3C7; color: #92400E; }
  .rm-NS { background: #E0E7FF; color: #3730A3; }
  .rm-lain { background: #F1F5F9; color: #334155; }
  .rm-kosong { background: transparent; }
  .rm-libur { color: #DC2626 !important; }
  /* Jadwal dari roster yang belum disetujui (Overview, opsi "termasuk menunggu"). */
  .rm-sel.rm-menunggu { outline: 1.5px dashed #D97706; outline-offset: -2px; opacity: .85; }
</style>
