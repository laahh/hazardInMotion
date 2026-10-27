{{-- Gaya kartu dashboard Master Roster (KPI, kartu bertajuk, chip) — dipakai Overview, Upload & Approval. --}}
@once
<style>
  .rov-kpi { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
  .rov-kpi__kartu { background: #fff; border: 1px solid #EEF2F7; border-radius: 12px; padding: 18px 20px; display: flex; gap: 14px; align-items: flex-start; }
  .rov-kpi__ikon { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; font-size: 20px; flex: none; }
  .rov-kpi__label { font-size: 12px; font-weight: 600; color: #64748B; margin-bottom: 4px; }
  .rov-kpi__nilai { font-size: 26px; line-height: 1.1; font-weight: 700; color: #0F172A; font-variant-numeric: tabular-nums; }
  .rov-kpi__sub { font-size: 12px; color: #64748B; margin-top: 4px; }
  .rov-kpi__sub a { color: #4F46E5; font-weight: 600; }
  .rov-ikon-indigo { background: #EEF2FF; color: #4F46E5; }
  .rov-ikon-sky { background: #F0F9FF; color: #0284C7; }
  .rov-ikon-hijau { background: #ECFDF5; color: #059669; }
  .rov-ikon-oranye { background: #FFF7ED; color: #C2410C; }
  .rov-chip { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 999px; background: #F1F5F9; color: #334155; font-size: 11px; font-weight: 600; margin: 4px 4px 0 0; }
  .rov-kartu { background: #fff; border: 1px solid #EEF2F7; border-radius: 12px; overflow: hidden; }
  .rov-kartu__kepala { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; padding: 18px 24px; border-bottom: 1px solid #EEF2F7; }
  .rov-kartu__judul { font-size: 16px; font-weight: 600; color: #0F172A; margin: 0; }
  .rov-kartu__sub { font-size: 12px; color: #64748B; margin: 2px 0 0; }
  .rov-kpi--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  a.rov-kpi__kartu { transition: border-color .15s ease, box-shadow .15s ease; }
  a.rov-kpi__kartu:hover { border-color: #C7D2FE; box-shadow: 0 4px 14px rgba(79, 70, 229, .08); }
  .rov-ikon-merah { background: #FEF2F2; color: #DC2626; }
  .rov-kpi > * { min-width: 0; }
  /* Tabel riwayat upload: tema memberi .bordered-table min-width max-content +
     padding 16px, jadi tabel tidak pernah menyesuaikan lebar kartu. */
  .rm-daftar.bordered-table { min-width: 100%; }
  .rm-daftar.bordered-table thead tr th { padding: 10px 14px !important; font-size: 11px; color: #64748B !important; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
  .rm-daftar.bordered-table tbody tr td { padding: 12px 14px !important; font-size: 13px; vertical-align: middle; }
  .rm-daftar__judul { min-width: 220px; max-width: 360px; white-space: normal; word-break: break-word; }
  @media (max-width: 1199.98px) { .rov-kpi:not(.rov-kpi--3) { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 767.98px) { .rov-kpi--3 { grid-template-columns: 1fr; } }
  @media (max-width: 575.98px) {
    .rov-kpi { grid-template-columns: 1fr; }
    .rov-kartu__kepala { padding: 16px; }
  }
</style>
@endonce
