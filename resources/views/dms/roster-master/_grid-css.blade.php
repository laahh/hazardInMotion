{{-- Gaya tabel jadwal bergaya template Excel — dipakai halaman Detail & Overview Roster. --}}
<style>
  /* Tabel jadwal meniru template Excel "ROSTER KERJA KARYAWAN". */
  .rm-xl { border-collapse: separate; border-spacing: 0; font-size: 11.5px; font-family: Calibri, 'Segoe UI', sans-serif; }
  .rm-xl th, .rm-xl td { white-space: nowrap; border-right: 1px solid #BFBFBF; border-bottom: 1px solid #BFBFBF; padding: 3px 6px; }
  .rm-xl tr > :first-child { border-left: 1px solid #BFBFBF; }
  .rm-xl thead tr:first-child > * { border-top: 1px solid #BFBFBF; }
  .rm-xl thead th { position: sticky; z-index: 2; text-align: center; font-weight: 700; }
  .rm-xl thead tr:nth-child(1) th { top: 0; }
  .rm-xl thead tr:nth-child(2) th { top: 24px; }
  .rm-xl thead tr:nth-child(3) th { top: 46px; }
  .rm-xl .rm-h-id { background: #1F3864; color: #fff; vertical-align: middle; white-space: normal; line-height: 1.25; }
  .rm-xl .rm-h-tgl { background: #fff; color: #C00000; height: 24px; min-width: 38px; }
  .rm-xl .rm-h-hari, .rm-xl .rm-h-bln { background: #D9E1F2; color: #1F3864; height: 22px; }
  .rm-xl .rm-minggu { background: #C00000 !important; color: #fff !important; }
  .rm-xl .rm-c-id { background: #FFF2CC; color: #1F2937; }
  .rm-xl .rm-c-no, .rm-xl .rm-c-sid, .rm-xl .rm-c-jam { text-align: center; }
  /* Tiga kolom pertama (No, SID, Nama) tetap terlihat saat digulir ke kanan. */
  .rm-xl .rm-s1, .rm-xl .rm-s2, .rm-xl .rm-s3 { position: sticky; z-index: 1; }
  .rm-xl thead .rm-s1, .rm-xl thead .rm-s2, .rm-xl thead .rm-s3 { z-index: 4; }
  .rm-xl .rm-s1 { left: 0; min-width: 40px; }
  .rm-xl .rm-s2 { left: 40px; min-width: 76px; }
  .rm-xl .rm-s3 { left: 116px; min-width: 190px; box-shadow: 2px 0 0 #8EA9DB; }
  .rm-xl .rm-c-kode { padding: 0; text-align: center; }
  .rm-sel { min-width: 38px; height: 22px; line-height: 22px; text-align: center; font-weight: 700; font-size: 11px; }
  .rm-xl .rm-bulan { border-left: 2px solid #1F3864 !important; }

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
  /* Warna kode sama dengan template Excel (DmsRosterMasterService::KODE). */
  @foreach (\App\Services\Dms\Roster\DmsRosterMasterService::KODE as $kodeCss => [, $warnaCss])
  .rm-{{ $kodeCss }} { background: #{{ $warnaCss }}; color: #1F2937; }
  @endforeach
  /* Kode lama (sebelum D1–D13 / N1–N7) tetap berwarna. */
  .rm-DS { background: #FFE699; color: #1F2937; }
  .rm-NS { background: #9DC3E6; color: #1F2937; }
  .rm-lain { background: #EDEDED; color: #1F2937; }
  .rm-kosong { background: #fff; }
  .rm-libur { color: #DC2626 !important; }
  /* Jadwal dari roster yang belum disetujui (Overview, opsi "termasuk menunggu"). */
  .rm-sel.rm-menunggu { outline: 1.5px dashed #B45309; outline-offset: -3px; opacity: .8; }
</style>
