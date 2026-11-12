@extends('dms.layouts.app')

@section('title', 'Overview Roster')

@section('css')
{{-- Tombol "Lihat …" & panel collapse sama dengan tabel Scorecard Ringkasan Roster. --}}
<link rel="stylesheet" href="{{ asset('dms-assets/roster-compliance/roster-overview.css') }}?v=18">
@include('dms.roster-master._grid-css')
@include('dms.roster-master._ui-css')
<style>
  /* ── KPI ── */

  /* ── Perlu perhatian ── */
  .rov-perhatian { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
  .rov-perhatian__kartu { background: #fff; border: 1px solid #EEF2F7; border-radius: 12px; padding: 14px 18px; display: flex; gap: 12px; align-items: flex-start; }
  .rov-perhatian__kartu > iconify-icon { font-size: 20px; flex: none; margin-top: 1px; }
  .rov-kpi > *, .rov-perhatian > *, .rov-perhatian__kartu > div { min-width: 0; }
  .rov-chip { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .rov-perhatian__judul { font-size: 13px; font-weight: 600; color: #0F172A; }
  .rov-perhatian__isi { font-size: 12px; color: #64748B; margin-top: 2px; }

  /* ── Daftar perusahaan ── */
  .rov-legenda { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: #475569; }
  .rov-legenda span { display: inline-flex; align-items: center; gap: 6px; }
  .rov-legenda i { width: 14px; height: 8px; border-radius: 4px; display: inline-block; }
  .rov-legenda i.is-garis { width: 2px; height: 12px; border-radius: 1px; background: #0F172A; }

  .ro-pt-tabel { margin: 0; }
  .ro-pt-tabel th { font-size: 11px; color: #64748B; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; background: #F8FAFC; border-bottom: 1px solid #EEF2F7; padding-top: 10px; padding-bottom: 10px; }
  .ro-pt-tabel td { font-size: 13px; vertical-align: middle; border-bottom: 1px solid #F1F5F9; padding-top: 14px; padding-bottom: 14px; }
  .ro-pt-tabel tbody tr[data-ro-baris]:hover > td { background: #FAFBFF; }
  .ro-pt-tabel tr.is-buka > td { background: #F8FAFF; }
  .ro-pt-tabel tr.is-buka > td:first-child { box-shadow: inset 3px 0 0 #4F46E5; }
  .ro-pt-nama { font-weight: 600; color: #0F172A; }
  .ro-pt-site { font-size: 12px; color: #64748B; margin-top: 2px; }
  .ro-pt-detail > td { padding: 0 !important; border-bottom: 0; }
  /* Isi panel tidak boleh ikut menentukan lebar tabel perusahaan (tabel jadwal
     bisa sangat lebar): lebar 0 + min-width 100% = selebar sel, sisanya digulir di dalam. */
  .ro-pt-detail .collapse, .ro-pt-detail .collapsing { width: 0; min-width: 100%; }

  .rov-angka { font-weight: 600; color: #0F172A; font-variant-numeric: tabular-nums; }
  .rov-minibar { height: 6px; border-radius: 3px; background: #F1F5F9; margin-top: 6px; width: 96px; }
  .rov-minibar > span { display: block; height: 100%; border-radius: 3px; background: #6366F1; }

  /* Garis waktu periode: semua baris memakai sumbu tanggal yang sama. */
  .rov-waktu { position: relative; height: 10px; border-radius: 5px; background: #F1F5F9; min-width: 180px; }
  .rov-waktu__bar { position: absolute; top: 0; bottom: 0; border-radius: 5px; background: #A5B4FC; }
  .rov-waktu__bar.is-berlaku { background: #6366F1; }
  .rov-waktu__bar.is-segera { background: #6366F1; }
  .rov-waktu__bar.is-berakhir { background: #CBD5E1; }
  .rov-waktu__kini { position: absolute; top: -3px; bottom: -3px; width: 2px; border-radius: 1px; background: #0F172A; }
  .rov-waktu__tgl { display: flex; justify-content: space-between; font-size: 11px; color: #64748B; margin-top: 6px; font-variant-numeric: tabular-nums; }
  .rov-sumbu { display: flex; justify-content: space-between; font-size: 10px; font-weight: 500; color: #94A3B8; text-transform: none; letter-spacing: 0; margin-top: 2px; }

  .rov-status { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; white-space: nowrap; }
  .rov-status iconify-icon { font-size: 15px; }
  .rov-status.is-berlaku { color: #047857; }
  .rov-status.is-segera { color: #B45309; }
  .rov-status.is-berakhir { color: #64748B; }
  .rov-status.is-belum { color: #4338CA; }
  .rov-upload { font-size: 11px; color: #94A3B8; margin-top: 3px; white-space: nowrap; }
  .rov-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
  .rov-badge.is-ok { background: #ECFDF5; color: #047857; }
  .rov-badge.is-tunggu { background: #FFF7ED; color: #C2410C; }

  .ro-filter .form-control, .ro-filter .form-select { min-width: 0; }
  .ro-filter label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
  .ro-memuat { padding: 28px; text-align: center; color: #64748B; font-size: 13px; }

  /* ── Cakupan upload (perusahaan & site: sudah / menunggu / belum) ── */
  .rov-cak-kpi { padding: 20px 24px 4px; margin-bottom: 0; }
  .rov-cak-kpi .rov-kpi__kartu { box-shadow: none; }
  /* Batang comply rate: hijau = karyawan aktif yang sudah ada di roster. */
  .rov-cak-rate { display: flex; gap: 2px; height: 6px; border-radius: 3px; background: #F1F5F9; margin-top: 8px; overflow: hidden; }
  .rov-cak-rate > span { display: block; height: 100%; border-radius: 3px; }
  .rov-cak-rate .is-sudah { background: #10B981; }
  .rov-cak-rate .is-menunggu { background: #F59E0B; }
  .rov-cak-saring i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; flex: none; }
  .rov-cak-saring .is-sudah { background: #10B981; }
  .rov-cak-saring .is-menunggu { background: #F59E0B; }
  .rov-cak-saring .is-belum { background: #CBD5E1; }
  .rov-cak-saring .is-sebagian { background: #3B82F6; }
  .rov-cak-status.is-sebagian { background: #EFF6FF; color: #1D4ED8; }

  .rov-cak-alat { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 24px 16px; }
  .rov-cak-saring { display: inline-flex; flex-wrap: wrap; gap: 6px; }
  .rov-cak-saring button { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #E2E8F0; background: #fff; color: #475569; border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 600; }
  .rov-cak-saring button b { color: #0F172A; font-variant-numeric: tabular-nums; }
  .rov-cak-saring button.is-aktif { border-color: #6366F1; background: #EEF2FF; color: #4338CA; }
  .rov-cak-saring button:focus-visible { outline: 2px solid #6366F1; outline-offset: 2px; }
  .rov-cak-filter { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
  .rov-cak-filter .form-select { width: 220px; }
  .rov-cak-cari { width: 220px; }
  .rov-cak-konteks { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; background: #EEF2FF; color: #4338CA; font-size: 12px; font-weight: 600; }
  .rov-cak-gulir { max-height: 560px; overflow: auto; border-top: 1px solid #EEF2F7; }
  .rov-cak-gulir thead th { position: sticky; top: 0; z-index: 1; }
  /* Chip site tidak boleh menumpuk satu-satu ke bawah di layar sempit. */
  .rov-cak-gulir td:nth-child(5) { min-width: 280px; }

  .rov-cak-status { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
  .rov-cak-status iconify-icon { font-size: 14px; }
  .rov-cak-status.is-sudah, .rov-cak-chip.is-sudah { background: #ECFDF5; color: #047857; }
  .rov-cak-status.is-menunggu, .rov-cak-chip.is-menunggu { background: #FFF7ED; color: #C2410C; }
  .rov-cak-status.is-belum, .rov-cak-chip.is-belum { background: #F1F5F9; color: #64748B; }
  .rov-cak-jumlah { font-size: 12px; font-weight: 600; color: #0F172A; font-variant-numeric: tabular-nums; }
  .rov-cak-situs { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
  .rov-cak-chip { display: inline-flex; align-items: center; gap: 3px; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
  .rov-cak-chip iconify-icon { font-size: 12px; }
  .rov-cak-kurang-angka { font-weight: 700; color: #DC2626; font-variant-numeric: tabular-nums; }
  .rov-cak-lengkap { display: inline-flex; align-items: center; gap: 4px; font-weight: 700; color: #047857; }
  .rov-cak-tab { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #E2E8F0; background: #fff; color: #475569; border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 600; }
  .rov-cak-tab b { color: #0F172A; }
  .rov-cak-tab.is-aktif { border-color: #6366F1; background: #EEF2FF; color: #4338CA; }
  /* Kartu KPI yang membuka modal per site. */
  .rov-cak-buka { width: 100%; text-align: left; color: inherit; cursor: pointer; transition: border-color .15s ease, box-shadow .15s ease; }
  .rov-cak-buka:hover { border-color: #C7D2FE; box-shadow: 0 4px 14px rgba(79, 70, 229, .08) !important; }
  .rov-cak-buka:focus-visible { outline: 2px solid #6366F1; outline-offset: 2px; }
  .rov-cak-buka__ajak { display: inline-flex; align-items: center; gap: 2px; margin-top: 6px; font-size: 12px; font-weight: 600; color: #4F46E5; }

  /* Modal per site: ringkasan, bar chart sudah/kurang, daftar perusahaan. */
  .rov-st-ringkas { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
  .rov-st-ringkas > div { border: 1px solid #EEF2F7; border-radius: 10px; padding: 12px 14px; }
  .rov-st-ringkas span { display: block; font-size: 12px; color: #64748B; }
  .rov-st-ringkas b { font-size: 20px; color: #0F172A; font-variant-numeric: tabular-nums; }
  .rov-st-legenda { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: #475569; margin-bottom: 8px; }
  .rov-st-legenda i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
  .rov-st-sudah { background: #10B981; }
  .rov-st-kurang { background: #EF4444; }
  .rov-st-grafik { display: flex; flex-direction: column; gap: 2px; margin-bottom: 20px; }
  .rov-st-bar { display: grid; grid-template-columns: 110px minmax(0, 1fr) 270px; align-items: center; gap: 12px; width: 100%; border: 0; background: transparent; padding: 6px 8px; border-radius: 8px; text-align: left; color: inherit; }
  .rov-st-bar:hover { background: #F8FAFC; }
  .rov-st-bar.is-aktif { background: #EEF2FF; }
  .rov-st-bar:focus-visible { outline: 2px solid #6366F1; outline-offset: 1px; }
  .rov-st-bar__nama { font-size: 13px; font-weight: 600; color: #0F172A; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .rov-st-bar__trek { display: flex; gap: 2px; height: 14px; }
  .rov-st-bar__trek > span { display: block; height: 100%; }
  .rov-st-bar__trek > span:first-child { border-radius: 4px 0 0 4px; }
  .rov-st-bar__trek > span:last-child { border-radius: 0 4px 4px 0; }
  .rov-st-bar__trek > span:only-child { border-radius: 4px; }
  .rov-st-bar__angka { font-size: 12px; color: #475569; font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
  .rov-st-bar__angka b { color: #DC2626; }
  .rov-st-detail { border-top: 1px solid #EEF2F7; padding-top: 16px; }
  .rov-st-detail__kepala { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
  .rov-st-detail__judul { font-weight: 600; color: #0F172A; }
  .rov-st-mini { display: flex; gap: 2px; height: 6px; width: 120px; background: #F1F5F9; border-radius: 3px; overflow: hidden; }
  .rov-st-mini > span { display: block; height: 100%; }
  .rov-st-tabel td, .rov-st-tabel th { font-size: 13px; vertical-align: middle; }

  .rov-cak-tidakcocok { display: flex; gap: 8px; padding: 12px 24px 16px; border-top: 1px solid #EEF2F7; font-size: 12px; color: #64748B; }
  .rov-cak-tidakcocok > iconify-icon { font-size: 16px; flex: none; margin-top: 1px; }

  @media (max-width: 767.98px) {
    .rov-perhatian { grid-template-columns: 1fr; }
    .rov-cak-kpi, .rov-cak-alat { padding-left: 16px; padding-right: 16px; }
    .rov-cak-filter, .rov-cak-filter .form-select, .rov-cak-cari { width: 100%; }
    .rov-st-ringkas { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .rov-st-bar { grid-template-columns: 84px minmax(0, 1fr); }
    .rov-st-bar__angka { grid-column: 1 / -1; text-align: left; }
  }
  /* ── Sub-menu Overview (People / Org chart / Time off) ── */
  .ovt-tabs { display: flex; flex-wrap: wrap; gap: 4px; border-bottom: 1px solid #E2E8F0; }
  .ovt-tab { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border: 0; background: none; color: #64748B; font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -1px; cursor: pointer; transition: color .15s, border-color .2s; }
  .ovt-tab:hover { color: #0F172A; }
  .ovt-tab.is-aktif { color: var(--primary-600, #487FFF); border-bottom-color: var(--primary-600, #487FFF); }
  .ovt-filter > div { min-width: 160px; }
  .ovt-tabel th { font-size: 12px; font-weight: 600; color: #64748B; background: #F8FAFC; white-space: nowrap; }
  .ovt-tabel td { vertical-align: middle; }
  .ovt-kaki { border-top: 1px solid #EEF2F7; }
  .ovt-pil { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; background: #F1F5F9; color: #475569; }
  .ovt-pil.is-sudah { background: #E2F3E5; color: #16712C; }
  .ovt-pil.is-menunggu { background: #FFF2D6; color: #B26A00; }
  .ovt-pil.is-belum { background: #FDE7EA; color: #C8102E; }
  .ovt-pil.is-oto { background: #EEF4FF; color: #2F5BD3; }
  .ovt-node { margin: 2px 0; }
  .ovt-node > summary { list-style: none; display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; cursor: pointer; }
  .ovt-node > summary::-webkit-details-marker { display: none; }
  .ovt-node > summary:hover { background: #F8FAFC; }
  .ovt-node--pt > summary { font-weight: 700; font-size: 15px; background: #F8FAFC; }
  .ovt-node--site > summary { font-weight: 600; }
  .ovt-node__ikon { color: #94A3B8; font-size: 18px; }
  .ovt-node__nama { flex: 1; min-width: 0; }
  .ovt-node__jml { font-size: 12px; font-weight: 600; color: #475569; background: #EEF2F7; padding: 1px 8px; border-radius: 999px; }
  .ovt-node__anak { margin-left: 18px; padding-left: 14px; border-left: 1px dashed #CBD5E1; }
  .ovt-jab { padding: 6px 12px; }
  .ovt-jab__kepala { display: flex; justify-content: space-between; gap: 8px; font-size: 13px; font-weight: 600; color: #334155; }
  .ovt-jab__orang { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
  .ovt-orang { font-size: 12px; padding: 2px 8px; border-radius: 6px; background: #F1F5F9; color: #334155; }
  .ovt-orang.is-lain { background: none; color: #64748B; }
  .ovt-ringkas { display: flex; flex-wrap: wrap; gap: 12px; }
  .ovt-ringkas__kartu { display: flex; align-items: center; gap: 10px; padding: 10px 16px; border: 1px solid #EEF2F7; border-radius: 10px; }
  .ovt-ringkas__angka { font-size: 22px; font-weight: 700; color: #0F172A; }
  .ovt-ringkas__label { font-size: 12px; color: #64748B; line-height: 1.3; }
  .ovt-ringkas__kartu.is-ct .ovt-ringkas__angka { color: #2F5BD3; }
  .ovt-ringkas__kartu.is-s .ovt-ringkas__angka { color: #C8102E; }
  .ovt-ringkas__kartu.is-i .ovt-ringkas__angka { color: #B26A00; }
  .ovt-kal { border-collapse: separate; border-spacing: 2px; font-size: 11px; }
  .ovt-kal th { font-weight: 600; color: #64748B; text-align: center; min-width: 24px; }
  .ovt-kal td { text-align: center; height: 28px; min-width: 24px; border-radius: 4px; background: #F8FAFC; font-weight: 700; }
  .ovt-kal .is-libur { background: #F1F5F9; color: #94A3B8; }
  .ovt-kal th.is-kini { color: var(--primary-600, #487FFF); }
  .ovt-kal td.is-ct { background: #DCE7FF; color: #2F5BD3; }
  .ovt-kal td.is-s { background: #FDE7EA; color: #C8102E; }
  .ovt-kal td.is-i { background: #FFF2D6; color: #B26A00; }
  .ovt-kal__nama { text-align: left !important; min-width: 220px; padding-right: 12px; position: sticky; left: 0; background-color: #fff !important; z-index: 1; }
  .ovt-kal__jml { background: none !important; font-weight: 700; padding-left: 8px; }

  /* ── Org chart bagan (kartu bertingkat dengan garis penghubung) ── */
  .oc-alat { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
  .oc-mode, .oc-zoom { display: inline-flex; align-items: center; gap: 2px; background: #F1F5F9; border-radius: 10px; padding: 3px; }
  .oc-mode button, .oc-zoom button { border: 0; background: none; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #475569; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
  .oc-mode button.is-aktif { background: #fff; color: #0F172A; box-shadow: 0 1px 2px rgba(15, 23, 42, .08); }
  .oc-zoom button:hover { background: #fff; }
  .oc-zoom span { font-size: 12px; font-weight: 600; color: #64748B; min-width: 44px; text-align: center; }
  .oc-kanvas { overflow: auto; cursor: grab; margin: 12px 24px 24px; border: 1px solid #EEF2F7; border-radius: 12px; height: 70vh; min-height: 420px;
    background: #F8FAFC radial-gradient(circle, #E2E8F0 1px, transparent 1px) 0 0 / 18px 18px; }
  .oc-kanvas.is-geser { cursor: grabbing; user-select: none; }
  .oc-pohon { display: inline-block; padding: 28px; transform-origin: 0 0; min-width: 100%; }
  .oc-pohon ul { display: flex; justify-content: center; padding: 22px 0 0; margin: 0; position: relative; }
  .oc-pohon > ul { padding-top: 0; }
  .oc-pohon li { list-style: none; position: relative; padding: 22px 8px 0; display: flex; flex-direction: column; align-items: center; }
  .oc-pohon > ul > li { padding-top: 0; }
  .oc-pohon li::before, .oc-pohon li::after { content: ''; position: absolute; top: 0; right: 50%; width: 50%; height: 22px; border-top: 2px solid #CBD5E1; }
  .oc-pohon li::after { right: auto; left: 50%; border-left: 2px solid #CBD5E1; }
  .oc-pohon li:only-child::before, .oc-pohon li:only-child::after { display: none; }
  .oc-pohon li:only-child { padding-top: 22px; }
  .oc-pohon > ul > li:only-child { padding-top: 0; }
  .oc-pohon li:first-child::before, .oc-pohon li:last-child::after { border: 0 none; }
  .oc-pohon li:last-child::before { border-right: 2px solid #CBD5E1; border-radius: 0 8px 0 0; }
  .oc-pohon li:first-child::after { border-radius: 8px 0 0 0; }
  .oc-pohon ul ul::before { content: ''; position: absolute; top: 0; left: 50%; height: 22px; border-left: 2px solid #CBD5E1; }
  .oc-pohon li:only-child > .oc-simpul, .oc-pohon li:only-child > .oc-dept { position: relative; }
  .oc-pohon ul ul > li:only-child::before { display: block; content: ''; position: absolute; top: 0; left: 50%; height: 22px; width: 0; border-left: 2px solid #CBD5E1; border-top: 0; }
  .oc-simpul { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #E2E8F0; border-radius: 12px; padding: 10px 16px; box-shadow: 0 2px 6px rgba(15, 23, 42, .06); white-space: nowrap; }
  .oc-simpul iconify-icon { font-size: 22px; color: var(--primary-600, #487FFF); }
  .oc-simpul.is-pt { background: #1E293B; border-color: #1E293B; color: #fff; }
  .oc-simpul.is-pt iconify-icon { color: #93C5FD; }
  .oc-simpul__nama { font-weight: 700; font-size: 14px; }
  .oc-simpul__jml { font-size: 11px; opacity: .7; }
  .oc-dept { width: 220px; display: flex; flex-direction: column; align-items: stretch; gap: 8px; }
  .oc-dept__judul { text-align: center; font-size: 12px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: .02em; background: #E2E8F0; border-radius: 8px; padding: 6px 8px; }
  .oc-dept__judul span { font-weight: 600; color: #64748B; margin-left: 4px; }
  .oc-anggota { position: relative; display: flex; flex-direction: column; gap: 8px; padding-left: 16px; }
  .oc-anggota::before { content: ''; position: absolute; left: 6px; top: -8px; bottom: 18px; border-left: 2px solid #CBD5E1; }
  .oc-anggota > .oc-kartu::before { content: ''; position: absolute; left: -10px; top: 18px; width: 10px; border-top: 2px solid #CBD5E1; }
  .oc-kartu { position: relative; background: #fff; border: 1px solid #E2E8F0; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(15, 23, 42, .06); text-align: left; }
  .oc-kartu__kepala { background: #4B5563; color: #fff; padding: 6px 10px; }
  .oc-kartu__nama { font-size: 12px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .oc-kartu__jabatan { font-size: 10px; opacity: .8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .oc-kartu__isi { display: flex; align-items: center; gap: 8px; padding: 8px 10px; }
  .oc-avatar { flex: none; width: 34px; height: 34px; border-radius: 50%; color: #fff; font-size: 12px; font-weight: 700; display: grid; place-items: center; }
  .oc-kartu__detail { font-size: 11px; color: #475569; line-height: 1.35; min-width: 0; }
  .oc-kartu__sub { color: #94A3B8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
  .oc-kartu.is-kecil .oc-kartu__kepala { background: #64748B; }
  .oc-kartu.is-kecil .oc-avatar { width: 28px; height: 28px; font-size: 11px; }
  .oc-lain { font-size: 12px; font-weight: 600; color: #64748B; text-align: center; border: 1px dashed #CBD5E1; border-radius: 8px; padding: 6px; background: #fff; }

  @media (max-width: 575.98px) {
    .ro-filter > div { flex: 1 1 100%; }
    .ovt-filter > div { flex: 1 1 100%; }
    .ro-sc-detail { padding: 12px; }
    .ro-sc-detail__panel { padding: 12px; }
  }
</style>
@endsection

@section('content')
@php
  $fmt = static fn ($t, string $f = 'd/m/Y') => \Carbon\CarbonImmutable::parse($t)->format($f);
  $angka = static fn (int $n) => number_format($n, 0, ',', '.');
  $ikonStatus = ['berlaku' => 'solar:check-circle-bold', 'segera' => 'solar:danger-triangle-bold', 'berakhir' => 'solar:history-bold', 'belum' => 'solar:calendar-bold'];
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Overview Roster</h6>
    <p class="text-sm text-secondary-light mb-0 mt-4">Cakupan &amp; status roster kerja yang sudah di-upload per perusahaan, per {{ now()->locale('id')->translatedFormat('d F Y') }}.</p>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>DMS
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Master Roster</li>
    <li>-</li>
    <li class="fw-medium">Overview Roster</li>
  </ul>
</div>

{{-- Sub-menu Overview: Roster (isi lama) / People / Org chart / Time off. Tab selain Roster dimuat lewat AJAX saat dibuka. --}}
<nav class="ovt-tabs mb-24" role="tablist" aria-label="Sub-menu Overview Roster">
  <button type="button" class="ovt-tab is-aktif" role="tab" aria-selected="true" data-ovt-tab="roster"><iconify-icon icon="solar:calendar-mark-bold"></iconify-icon>Roster</button>
  <button type="button" class="ovt-tab" role="tab" aria-selected="false" data-ovt-tab="people" data-url="{{ route('dms.master-roster.overview.people') }}"><iconify-icon icon="solar:users-group-rounded-bold"></iconify-icon>People</button>
  <button type="button" class="ovt-tab" role="tab" aria-selected="false" data-ovt-tab="org" data-url="{{ route('dms.master-roster.overview.org') }}"><iconify-icon icon="solar:structure-bold"></iconify-icon>Org chart</button>
  <button type="button" class="ovt-tab" role="tab" aria-selected="false" data-ovt-tab="time-off" data-url="{{ route('dms.master-roster.overview.time-off') }}"><iconify-icon icon="solar:calendar-minimalistic-bold"></iconify-icon>Time off</button>
</nav>

<div data-ovt-panel="roster">
@if ($cakupan !== null)
  @include('dms.roster-master._cakupan', ['cakupan' => $cakupan])
@endif

@if ($perusahaan === [])
  <div class="rov-kartu p-24 text-center text-secondary-light">
    Belum ada perusahaan yang meng-upload roster.
    <a href="{{ route('dms.master-roster.upload') }}" class="text-primary-600 fw-semibold">Upload roster</a>
  </div>
@else
  {{-- KPI --}}
  <div class="rov-kpi">
    <div class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-indigo"><iconify-icon icon="solar:buildings-2-bold"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">Perusahaan</div>
        <div class="rov-kpi__nilai">{{ $angka($ringkas['perusahaan']) }}</div>
        <div class="rov-kpi__sub">{{ $angka($ringkas['berlaku']) }} punya roster berlaku hari ini</div>
      </div>
    </div>
    <div class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-sky"><iconify-icon icon="solar:users-group-rounded-bold"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">Karyawan terjadwal</div>
        <div class="rov-kpi__nilai">{{ $angka($ringkas['karyawan']) }}</div>
        <div class="rov-kpi__sub">{{ $angka($ringkas['karyawan_berlaku']) }} di roster yang berlaku hari ini</div>
      </div>
    </div>
    <div class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-hijau"><iconify-icon icon="solar:check-circle-bold"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">Roster disetujui</div>
        <div class="rov-kpi__nilai">{{ $angka($ringkas['disetujui']) }}</div>
        <div class="rov-kpi__sub">dari {{ $angka($ringkas['disetujui'] + $ringkas['menunggu']) }} upload aktif</div>
      </div>
    </div>
    <div class="rov-kpi__kartu">
      <span class="rov-kpi__ikon rov-ikon-oranye"><iconify-icon icon="solar:hourglass-bold"></iconify-icon></span>
      <div>
        <div class="rov-kpi__label">Menunggu approval</div>
        <div class="rov-kpi__nilai">{{ $angka($ringkas['menunggu']) }}</div>
        <div class="rov-kpi__sub">
          @if ($ringkas['menunggu'] > 0)
            <a href="{{ route('dms.master-roster.approval') }}">Buka Approval Roster →</a>
          @else
            Semua roster sudah direview
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Perlu perhatian --}}
  <div class="rov-perhatian">
    <div class="rov-perhatian__kartu">
      <iconify-icon icon="solar:hourglass-line-bold" class="{{ $ringkas['pt_menunggu'] === [] ? 'text-success-main' : 'text-warning-main' }}"></iconify-icon>
      <div>
        <div class="rov-perhatian__judul">
          {{ $ringkas['pt_menunggu'] === [] ? 'Tidak ada roster yang menunggu approval' : count($ringkas['pt_menunggu']).' perusahaan menunggu approval' }}
        </div>
        @if ($ringkas['pt_menunggu'] !== [])
          <div>@foreach ($ringkas['pt_menunggu'] as $nama)<span class="rov-chip">{{ $nama }}</span>@endforeach</div>
        @else
          <div class="rov-perhatian__isi">Semua upload sudah disetujui atau ditolak.</div>
        @endif
      </div>
    </div>
    <div class="rov-perhatian__kartu">
      <iconify-icon icon="solar:calendar-minimalistic-bold" class="{{ $ringkas['pt_segera'] === [] ? 'text-success-main' : 'text-warning-main' }}"></iconify-icon>
      <div>
        <div class="rov-perhatian__judul">
          {{ $ringkas['pt_segera'] === [] ? 'Tidak ada roster yang habis dalam '.$ringkas['segera_hari'].' hari' : count($ringkas['pt_segera']).' roster habis dalam '.$ringkas['segera_hari'].' hari' }}
        </div>
        @if ($ringkas['pt_segera'] !== [])
          <div>@foreach ($ringkas['pt_segera'] as $nama)<span class="rov-chip">{{ $nama }}</span>@endforeach</div>
          <div class="rov-perhatian__isi">Minta perusahaan ini meng-upload roster periode berikutnya.</div>
        @else
          <div class="rov-perhatian__isi">Roster yang berlaku masih cukup panjang.</div>
        @endif
      </div>
    </div>
  </div>

  {{-- Daftar perusahaan --}}
  <div class="rov-kartu">
    <div class="rov-kartu__kepala">
      <div>
        <div class="rov-kartu__judul">Roster per perusahaan</div>
        <p class="rov-kartu__sub">Roster yang ditolak tidak dihitung. Klik <b>Lihat jadwal</b> untuk membuka jadwal di bawah barisnya.</p>
      </div>
      <div class="rov-legenda" aria-label="Keterangan garis waktu">
        <span><i style="background:#6366F1"></i>Berlaku</span>
        <span><i style="background:#A5B4FC"></i>Belum mulai</span>
        <span><i style="background:#CBD5E1"></i>Berakhir</span>
        @if ($ringkas['hari_ini_persen'] !== null)<span><i class="is-garis"></i>Hari ini</span>@endif
      </div>
    </div>
    <div class="table-responsive">
      <table class="table ro-pt-tabel">
        <thead>
          <tr>
            <th class="ps-24">Perusahaan</th>
            <th>Karyawan</th>
            <th>Roster</th>
            <th style="min-width:220px">
              Periode jadwal
              <div class="rov-sumbu"><span>{{ $ringkas['rentang_awal']->format('d/m/y') }}</span><span>{{ $ringkas['rentang_akhir']->format('d/m/y') }}</span></div>
            </th>
            <th>Status</th>
            <th class="pe-24 text-end">Jadwal</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($perusahaan as $i => $p)
            @php $dibuka = $buka === $p['perusahaan']; $idPanel = 'roPt'.$i; @endphp
            <tr class="{{ $dibuka ? 'is-buka' : '' }}" data-ro-baris="{{ $idPanel }}" data-ro-cari="{{ mb_strtolower($p['perusahaan'].' '.implode(' ', $p['ejaan'] ?? []).' '.implode(' ', $p['site'])) }}">
              <td class="ps-24">
                <div class="ro-pt-nama">{{ $p['perusahaan'] }}</div>
                <div class="ro-pt-site">{{ $p['site'] === [] ? '—' : implode(' · ', $p['site']) }}</div>
              </td>
              <td>
                <span class="rov-angka">{{ $angka($p['karyawan']) }}</span>
                <div class="rov-minibar" title="{{ $angka($p['karyawan']) }} karyawan"><span style="width: {{ $p['karyawan_persen'] }}%"></span></div>
              </td>
              <td>
                <div class="d-flex flex-column align-items-start gap-1">
                  @if ($p['disetujui'])<span class="rov-badge is-ok"><iconify-icon icon="solar:check-circle-linear"></iconify-icon>{{ $p['disetujui'] }} disetujui</span>@endif
                  @if ($p['menunggu'])<span class="rov-badge is-tunggu"><iconify-icon icon="solar:hourglass-linear"></iconify-icon>{{ $p['menunggu'] }} menunggu</span>@endif
                </div>
              </td>
              <td>
                <div class="rov-waktu" title="{{ $fmt($p['dari']) }} – {{ $fmt($p['sampai']) }}">
                  <span class="rov-waktu__bar is-{{ $p['status'] }}" style="left: {{ $p['bar_kiri'] }}%; width: {{ $p['bar_lebar'] }}%"></span>
                  @if ($ringkas['hari_ini_persen'] !== null)<span class="rov-waktu__kini" style="left: calc({{ $ringkas['hari_ini_persen'] }}% - 1px)"></span>@endif
                </div>
                <div class="rov-waktu__tgl"><span>{{ $fmt($p['dari']) }}</span><span>{{ $fmt($p['sampai']) }}</span></div>
              </td>
              <td>
                <span class="rov-status is-{{ $p['status'] }}"><iconify-icon icon="{{ $ikonStatus[$p['status']] }}"></iconify-icon>{{ $p['status_label'] }}</span>
                <div class="rov-upload">Upload terakhir {{ $p['terakhir'] ? $fmt($p['terakhir'], 'd/m/Y H:i') : '—' }}</div>
              </td>
              <td class="pe-24 text-end">
                <button class="ro-sc-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $idPanel }}"
                        aria-expanded="{{ $dibuka ? 'true' : 'false' }}" aria-controls="{{ $idPanel }}">
                  <iconify-icon icon="solar:alt-arrow-down-linear"></iconify-icon>
                  Lihat jadwal
                </button>
              </td>
            </tr>
            <tr class="ro-pt-detail">
              <td colspan="6">
                <div class="collapse {{ $dibuka ? 'show' : '' }}" id="{{ $idPanel }}"
                     data-ro-url="{{ route('dms.master-roster.overview.jadwal', ['pt' => $p['perusahaan']], absolute: false) }}">
                  <div class="ro-sc-detail" data-ro-isi>
                    <div class="ro-memuat">Memuat jadwal…</div>
                  </div>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif
</div>{{-- /panel roster --}}

<div data-ovt-panel="people" hidden></div>
<div data-ovt-panel="org" hidden></div>
<div data-ovt-panel="time-off" hidden></div>
@endsection

@section('page-scripts')
<script>
// Sub-menu Overview: tab People / Org chart / Time off dimuat dari server saat dibuka.
(function () {
  const tabs = document.querySelectorAll('[data-ovt-tab]');
  const panel = (nama) => document.querySelector('[data-ovt-panel="' + nama + '"]');
  const muat = (nama, query) => {
    const tab = document.querySelector('[data-ovt-tab="' + nama + '"]');
    const el = panel(nama);
    if (!tab || !tab.dataset.url || !el) return;
    el.innerHTML = '<div class="rov-kartu p-24 text-center text-secondary-light">Memuat…</div>';
    const url = tab.dataset.url + (query ? '?' + query : '');
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then((r) => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
      .then((html) => { el.innerHTML = html; el.dataset.dimuat = '1'; el.dataset.query = query || ''; })
      .catch((e) => { el.innerHTML = '<div class="rov-kartu p-24 text-center text-danger-main">Gagal memuat (' + e.message + '). Coba lagi.</div>'; });
  };
  const buka = (nama, simpan) => {
    tabs.forEach((t) => { const on = t.dataset.ovtTab === nama; t.classList.toggle('is-aktif', on); t.setAttribute('aria-selected', on); });
    document.querySelectorAll('[data-ovt-panel]').forEach((p) => { p.hidden = p.dataset.ovtPanel !== nama; });
    const el = panel(nama);
    if (nama !== 'roster' && el && !el.dataset.dimuat) muat(nama, '');
    if (simpan) { const u = new URL(location.href); if (nama === 'roster') { u.searchParams.delete('tab'); } else { u.searchParams.set('tab', nama); } history.replaceState(null, '', u); }
  };
  tabs.forEach((t) => t.addEventListener('click', () => buka(t.dataset.ovtTab, true)));

  // Form filter & tombol halaman di dalam panel.
  document.addEventListener('submit', (e) => {
    const f = e.target.closest('[data-ovt-form]');
    if (!f) return;
    e.preventDefault();
    const q = new URLSearchParams(new FormData(f));
    [...q.keys()].forEach((k) => { if (q.get(k) === '') q.delete(k); });
    muat(f.dataset.ovtForm, q.toString());
  });
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-ovt-hal]');
    if (!b) return;
    const el = b.closest('[data-ovt-panel]');
    const q = new URLSearchParams(el.dataset.query || '');
    q.set('hal', b.dataset.ovtHal);
    muat(el.dataset.ovtPanel, q.toString());
  });
  // Site mengikuti perusahaan.
  document.addEventListener('change', (e) => {
    const pt = e.target.closest('[data-ovt-pt]');
    if (!pt) return;
    const site = pt.closest('form').querySelector('[data-ovt-site]');
    const pilih = pt.selectedOptions[0];
    const opsi = pilih && pilih.dataset.site ? JSON.parse(pilih.dataset.site) : null;
    const semua = opsi || [...new Set([...pt.options].flatMap((o) => (o.dataset.site ? JSON.parse(o.dataset.site) : [])))].sort();
    const esc = (v) => String(v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    site.innerHTML = '<option value="">Semua site</option>' + semua.map((v) => '<option value="' + esc(v) + '">' + (v === '-' ? 'Tanpa site' : esc(v)) + '</option>').join('');
  });

  // Org chart bagan: ganti mode, zoom, dan geser dengan seret.
  const zoomOc = (kanvas, nilai) => {
    const pohon = kanvas.querySelector('[data-oc-pohon]');
    const z = Math.min(1.6, Math.max(0.25, nilai));
    kanvas.dataset.zoom = z;
    pohon.style.transform = 'scale(' + z + ')';
    pohon.style.width = (100 / z) + '%';
    const label = kanvas.closest('.rov-kartu').querySelector('[data-oc-zoom-nilai]');
    if (label) label.textContent = Math.round(z * 100) + '%';
  };
  const pasLayar = (kanvas) => {
    const pohon = kanvas.querySelector('[data-oc-pohon]');
    pohon.style.transform = 'none';
    pohon.style.width = 'auto';
    const lebar = pohon.scrollWidth || 1;
    zoomOc(kanvas, Math.min(1, (kanvas.clientWidth - 8) / lebar));
    kanvas.scrollLeft = 0;
    kanvas.scrollTop = 0;
  };
  document.addEventListener('click', (e) => {
    const mode = e.target.closest('[data-oc-mode]');
    if (mode) {
      const kartu = mode.closest('.rov-kartu');
      kartu.querySelectorAll('[data-oc-mode]').forEach((b) => b.classList.toggle('is-aktif', b === mode));
      const bagan = mode.dataset.ocMode === 'bagan';
      kartu.querySelector('[data-oc-kanvas]').hidden = !bagan;
      kartu.querySelector('[data-oc-zoom-alat]').hidden = !bagan;
      kartu.querySelector('[data-oc-daftar]').hidden = bagan;
      return;
    }
    const zb = e.target.closest('[data-oc-zoom]');
    if (zb) {
      const kanvas = zb.closest('.rov-kartu').querySelector('[data-oc-kanvas]');
      const langkah = Number(zb.dataset.ocZoom);
      if (langkah === 0) pasLayar(kanvas); else zoomOc(kanvas, Number(kanvas.dataset.zoom || 1) + langkah * 0.15);
    }
  });
  let geser = null;
  document.addEventListener('pointerdown', (e) => {
    const kanvas = e.target.closest('[data-oc-kanvas]');
    if (!kanvas || e.button !== 0) return;
    geser = { kanvas, x: e.clientX, y: e.clientY, kiri: kanvas.scrollLeft, atas: kanvas.scrollTop };
    kanvas.classList.add('is-geser');
  });
  document.addEventListener('pointermove', (e) => {
    if (!geser) return;
    geser.kanvas.scrollLeft = geser.kiri - (e.clientX - geser.x);
    geser.kanvas.scrollTop = geser.atas - (e.clientY - geser.y);
  });
  document.addEventListener('pointerup', () => { if (geser) { geser.kanvas.classList.remove('is-geser'); geser = null; } });
  // Saat panel Org chart selesai dimuat: bagan langsung pas layar.
  new MutationObserver(() => {
    const kanvas = document.querySelector('[data-ovt-panel="org"] [data-oc-kanvas]');
    if (kanvas && !kanvas.dataset.siap) { kanvas.dataset.siap = '1'; requestAnimationFrame(() => pasLayar(kanvas)); }
  }).observe(document.querySelector('[data-ovt-panel="org"]'), { childList: true, subtree: false });

  const awal = new URL(location.href).searchParams.get('tab');
  if (awal && panel(awal)) buka(awal, false);
})();
</script>
<script>
// Kotak Search di navbar: di halaman ini menyaring tabel Cakupan upload roster
// (lewat kotak cari kartu itu) dan tabel Roster per perusahaan. Enter tidak
// memuat ulang halaman, hanya menggulir ke hasil.
(function () {
    'use strict';

    var form = document.querySelector('.navbar-search');
    var input = form ? form.querySelector('input[name="search"]') : null;
    if (!input) { return; }

    input.placeholder = 'Cari perusahaan / site…';
    input.setAttribute('aria-label', 'Cari perusahaan atau site');
    var cariKartu = document.querySelector('#roCakupan [data-cak-cari]');
    var barisRoster = document.querySelectorAll('tr[data-ro-baris]');

    function saring() {
        var q = input.value.trim().toLowerCase();
        if (cariKartu && cariKartu.value !== input.value) {
            cariKartu.value = input.value;
            cariKartu.dispatchEvent(new Event('input', { bubbles: true }));
        }
        barisRoster.forEach(function (tr) {
            var cocok = q === '' || (tr.dataset.roCari || '').indexOf(q) !== -1;
            tr.classList.toggle('d-none', !cocok);
            // Baris panel jadwal di bawahnya ikut disembunyikan.
            var detail = tr.nextElementSibling;
            if (detail && detail.classList.contains('ro-pt-detail')) { detail.classList.toggle('d-none', !cocok); }
        });
    }

    input.addEventListener('input', saring);
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        saring();
        var tujuan = document.getElementById('roCakupan') || document.querySelector('.ro-pt-tabel');
        if (tujuan) { tujuan.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
    // Kotak cari di kartu juga mengisi navbar, supaya keduanya selalu sama.
    if (cariKartu) {
        cariKartu.addEventListener('input', function () {
            if (input.value !== cariKartu.value) { input.value = cariKartu.value; saring(); }
        });
    }
})();
</script>
<script>
// Cakupan upload: saring status (Semua/Belum/Menunggu/Sudah), perusahaan, site,
// dan cari. Bila satu site dipilih, status tiap perusahaan = status site itu dan
// angka perbandingan di atas dihitung ulang hanya untuk site tersebut.
(function () {
    'use strict';

    var kartu = document.getElementById('roCakupan');
    if (!kartu) { return; }

    var STATUS = ['sudah', 'sebagian', 'menunggu', 'belum'];
    var tombol = kartu.querySelectorAll('[data-cak-status]');
    var cari = kartu.querySelector('[data-cak-cari]');
    var pilihPt = kartu.querySelector('[data-cak-pt]');
    var pilihSite = kartu.querySelector('[data-cak-site]');
    var reset = kartu.querySelector('[data-cak-reset]');
    var konteks = kartu.querySelector('[data-cak-konteks]');
    var kosong = kartu.querySelector('[data-cak-kosong]');
    var baris = Array.prototype.map.call(kartu.querySelectorAll('[data-cak-baris]'), function (tr) {
        return {
            tr: tr,
            nama: tr.dataset.nama,
            status: tr.dataset.status,
            karyawan: parseInt(tr.dataset.karyawan, 10) || 0,
            site: JSON.parse(tr.dataset.site || '[]'),
            cari: tr.dataset.cari,
            chip: tr.querySelectorAll('[data-cak-chip]'),
            jumlah: tr.querySelector('[data-cak-jumlah]'),
            badge: tr.querySelector('.rov-cak-status'),
            pasti: tr.dataset.kurangPasti === '1',
            // Isi asli sel angka (semua site) — dikembalikan saat filter site dilepas.
            sel: ['karyawan', 'kurang', 'roster'].reduce(function (o, k) {
                var td = tr.querySelector('[data-cak-sel="' + k + '"]');
                o[k] = { td: td, asli: td.innerHTML };
                return o;
            }, {}),
        };
    });

    // Angka baris untuk satu site: karyawan aktif, kurang, dan di roster site itu.
    function isiAngkaSite(b, s) {
        // Tab bawaan modal Detail ikut angka kurang yang sedang tampil.
        var tombol = b.tr.querySelector('[data-cak-kurang]');
        if (!s) {
            ['karyawan', 'kurang', 'roster'].forEach(function (k) { b.sel[k].td.innerHTML = b.sel[k].asli; });
            tombol.dataset.cakTab = (parseInt(b.tr.dataset.kurang, 10) || 0) === 0 ? 'sudah' : 'belum';
            return;
        }
        tombol.dataset.cakTab = s[4] === 0 ? 'sudah' : 'belum';
        b.sel.karyawan.td.innerHTML = '<span class="rov-angka">' + angka(s[2]) + '</span>';
        b.sel.kurang.td.innerHTML = s[4] === 0
            ? '<span class="rov-cak-lengkap" title="Semua karyawan aktif site ini sudah ada di roster"><iconify-icon icon="solar:check-circle-bold"></iconify-icon>0</span>'
            : '<span class="rov-cak-kurang-angka">' + (b.pasti ? '' : '≈ ') + angka(s[4]) + '</span>';
        b.sel.roster.td.innerHTML = s[3] > 0 ? '<span class="rov-angka">' + angka(s[3]) + '</span>' : '<span class="ro-pt-site">—</span>';
    }
    var LABEL = { sudah: 'Sudah', sebagian: 'Sudah, belum semua karyawan', menunggu: 'Menunggu approval', belum: 'Belum upload' };
    var IKON = { sudah: 'solar:check-circle-bold', sebagian: 'solar:pie-chart-2-bold', menunggu: 'solar:hourglass-bold', belum: 'solar:minus-circle-linear' };

    // Badge status baris = status efektif (status site itu bila satu site dipilih).
    function isiBadge(b, efektif, site) {
        b.badge.className = 'rov-cak-status is-' + efektif;
        b.badge.innerHTML = '<iconify-icon icon="' + IKON[efektif] + '"></iconify-icon>' + LABEL[efektif];
        b.badge.title = site === '' ? '' : 'Status roster untuk site ' + site;
    }
    var status = '';

    function angka(n) { return n.toLocaleString('id-ID'); }
    function persen(n, t) { return t > 0 ? (n / t * 100).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) : '0'; }
    function nol() { return { sudah: 0, sebagian: 0, menunggu: 0, belum: 0 }; }

    var nKurang = 0;
    function isiKpi(nPt, nSite, nKar) {
        var kpi = kartu.querySelector('[data-cak-kpi]');
        var disetujui = nPt.sudah + nPt.sebagian;
        var total = disetujui + nPt.menunggu + nPt.belum;
        var set = function (k, v) { kpi.querySelector('[data-cak-n="' + k + '"]').textContent = v; };
        set('total', angka(total));
        var karTotal = nKar.sudah + nKar.sebagian + nKar.menunggu + nKar.belum;
        var karRoster = Math.max(0, karTotal - nKurang);
        set('kar_total', angka(karTotal));
        set('kar_roster', angka(karRoster));
        set('upload', angka(disetujui + nPt.menunggu));
        set('disetujui', angka(disetujui));
        set('menunggu', angka(nPt.menunggu));
        set('belum', angka(nPt.belum));
        set('kurang', angka(nKurang));
        // Comply rate per karyawan, bukan per perusahaan.
        set('rate', persen(karRoster, karTotal));
        kpi.querySelector('[data-cak-seg="sudah"]').style.width = (karTotal > 0 ? karRoster / karTotal * 100 : 0) + '%';
    }

    function terapkan() {
        var q = cari.value.trim().toLowerCase();
        var pt = pilihPt.value;
        var site = pilihSite.value;
        var nPt = nol(), nSite = nol(), nKar = nol(), tampil = 0;
        nKurang = 0;

        baris.forEach(function (b) {
            // Status & karyawan efektif: per site terpilih, atau per perusahaan.
            var efektif = b.status, karyawan = b.karyawan, situs = b.site, kurang = parseInt(b.tr.dataset.kurang, 10) || 0, siteIni = null;
            var dasar = (pt === '' || b.nama === pt) && (q === '' || b.cari.indexOf(q) !== -1);
            if (dasar && site !== '') {
                var s = b.site.filter(function (x) { return x[0] === site; })[0];
                dasar = !!s;
                if (s) {
                    // Roster site ini disetujui tapi masih ada karyawan aktif site ini yang belum terjadwal.
                    efektif = s[1] === 'sudah' && s[4] > 0 ? 'sebagian' : s[1];
                    karyawan = s[2]; situs = [s]; kurang = s[4]; siteIni = s;
                }
            }
            if (dasar) {
                nKurang += kurang;
                nPt[efektif]++;
                nKar[efektif] += karyawan;
                situs.forEach(function (x) { nSite[x[1]]++; });
            }
            var lihat = dasar && (status === '' || efektif === status);
            b.tr.classList.toggle('d-none', !lihat);
            if (lihat) { tampil++; }

            if (lihat) { isiBadge(b, efektif, site); isiAngkaSite(b, siteIni); }
            b.chip.forEach(function (c) { c.classList.toggle('d-none', site !== '' && c.dataset.cakChip !== site); });
            if (b.jumlah) { b.jumlah.classList.toggle('d-none', site !== ''); }
        });

        isiKpi(nPt, nSite, nKar);
        STATUS.forEach(function (s) { kartu.querySelector('[data-cak-hitung="' + s + '"]').textContent = angka(nPt[s]); });
        kartu.querySelector('[data-cak-hitung=""]').textContent = angka(nPt.sudah + nPt.sebagian + nPt.menunggu + nPt.belum);
        kosong.classList.toggle('d-none', tampil > 0);

        var label = [];
        if (pt !== '') { label.push(pt); }
        if (site !== '') { label.push('Site ' + site); }
        konteks.textContent = label.length ? 'Menampilkan: ' + label.join(' · ') : '';
        konteks.classList.toggle('d-none', label.length === 0);
        reset.classList.toggle('d-none', label.length === 0 && q === '' && status === '');
    }

    tombol.forEach(function (b) {
        b.addEventListener('click', function () {
            status = b.dataset.cakStatus;
            tombol.forEach(function (x) { x.classList.toggle('is-aktif', x === b); });
            terapkan();
        });
    });
    cari.addEventListener('input', terapkan);
    pilihPt.addEventListener('change', terapkan);
    pilihSite.addEventListener('change', terapkan);
    reset.addEventListener('click', function () {
        status = '';
        cari.value = '';
        pilihPt.value = '';
        pilihSite.value = '';
        tombol.forEach(function (x) { x.classList.toggle('is-aktif', x.dataset.cakStatus === ''); });
        terapkan();
    });

    // ── Modal "Cakupan per site" (kartu Belum upload / Comply rate) ──────────
    // Dirakit dari data-site tiap baris: [nama, status, aktif, di roster, kurang].
    // Karyawan aktif tanpa site tidak ditampilkan, jadi total modal bisa lebih
    // kecil dari angka KPI.
    var siteModalEl = document.getElementById('roSiteModal');
    if (!siteModalEl || typeof bootstrap === 'undefined') { return; }
    var siteModal = bootstrap.Modal.getOrCreateInstance(siteModalEl);
    var siteIsi = siteModalEl.querySelector('[data-site-isi]');
    var siteData = [], sitePilih = '', siteTab = 'kurang';

    function esc(t) {
        return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }

    function rakitSite() {
        var peta = {};
        function tambah(nama, pt, status, aktif, kurang) {
            if (aktif <= 0) { return; }
            kurang = Math.min(aktif, Math.max(0, kurang));
            var s = peta[nama] = peta[nama] || { nama: nama, aktif: 0, kurang: 0, ptBelum: 0, pt: [] };
            s.aktif += aktif;
            s.kurang += kurang;
            if (status === 'belum') { s.ptBelum++; }
            s.pt.push({ nama: pt, status: status === 'sudah' && kurang > 0 ? 'sebagian' : status, aktif: aktif, kurang: kurang });
        }
        baris.forEach(function (b) {
            b.site.forEach(function (s) { tambah(s[0], b.nama, s[1], s[2], s[4]); });
        });

        return Object.keys(peta).map(function (k) { return peta[k]; })
            .sort(function (a, b) { return b.kurang - a.kurang || b.aktif - a.aktif || a.nama.localeCompare(b.nama); });
    }

    function htmlGrafik() {
        var maks = Math.max.apply(null, siteData.map(function (s) { return s.aktif; }).concat([1]));
        return siteData.map(function (s) {
            var sudah = s.aktif - s.kurang;
            var lebar = s.aktif / maks * 100;
            var seg = '';
            if (sudah > 0) { seg += '<span class="rov-st-sudah" style="width:' + (sudah / s.aktif * lebar) + '%"></span>'; }
            if (s.kurang > 0) { seg += '<span class="rov-st-kurang" style="width:' + (s.kurang / s.aktif * lebar) + '%"></span>'; }
            var info = s.nama + ': ' + angka(s.aktif) + ' karyawan aktif, ' + angka(sudah) + ' sudah di roster, ' + angka(s.kurang) + ' belum';
            return '<button type="button" class="rov-st-bar' + (s.nama === sitePilih ? ' is-aktif' : '') + '" data-st-site="' + esc(s.nama) + '" title="' + esc(info) + '" aria-label="' + esc(info) + '" aria-pressed="' + (s.nama === sitePilih) + '">'
                + '<span class="rov-st-bar__nama">' + esc(s.nama) + '</span>'
                + '<span class="rov-st-bar__trek">' + seg + '</span>'
                + '<span class="rov-st-bar__angka"><b>' + angka(s.kurang) + '</b> belum · ' + persen(sudah, s.aktif) + '% sudah · ' + angka(s.ptBelum) + ' PT belum upload</span>'
                + '</button>';
        }).join('');
    }

    function htmlDetail() {
        var s = siteData.filter(function (x) { return x.nama === sitePilih; })[0];
        if (!s) { return ''; }
        var nKurang = s.pt.filter(function (p) { return p.kurang > 0; }).length;
        var daftar = s.pt.filter(function (p) {
            return siteTab === '' || (siteTab === 'kurang' ? p.kurang > 0 : p.kurang === 0);
        }).sort(function (a, b) { return b.kurang - a.kurang || b.aktif - a.aktif || a.nama.localeCompare(b.nama); });

        var tab = [['', 'Semua', s.pt.length], ['kurang', 'Masih kurang', nKurang], ['lengkap', 'Sudah lengkap', s.pt.length - nKurang]]
            .map(function (t) {
                return '<button type="button" class="rov-cak-tab' + (siteTab === t[0] ? ' is-aktif' : '') + '" data-st-tab="' + t[0] + '">' + t[1] + ' <b>' + angka(t[2]) + '</b></button>';
            }).join(' ');

        var isi = daftar.map(function (p) {
            var sudah = p.aktif - p.kurang;
            return '<tr>'
                + '<td class="ps-0"><div class="ro-pt-nama">' + esc(p.nama) + '</div></td>'
                + '<td><span class="rov-cak-status is-' + p.status + '"><iconify-icon icon="' + IKON[p.status] + '"></iconify-icon>' + LABEL[p.status] + '</span></td>'
                + '<td class="text-end">' + angka(p.aktif) + '</td>'
                + '<td class="text-end">' + angka(sudah) + '</td>'
                + '<td class="text-end">' + (p.kurang > 0 ? '<span class="rov-cak-kurang-angka">' + angka(p.kurang) + '</span>' : '<span class="rov-cak-lengkap"><iconify-icon icon="solar:check-circle-bold"></iconify-icon>0</span>') + '</td>'
                + '<td class="pe-0"><div class="d-flex align-items-center gap-8"><span class="rov-st-mini">'
                + (sudah > 0 ? '<span class="rov-st-sudah" style="width:' + (sudah / p.aktif * 100) + '%"></span>' : '')
                + (p.kurang > 0 ? '<span class="rov-st-kurang" style="width:' + (p.kurang / p.aktif * 100) + '%"></span>' : '')
                + '</span><span class="text-sm text-secondary-light">' + persen(sudah, p.aktif) + '%</span></div></td>'
                + '</tr>';
        }).join('') || '<tr><td colspan="6" class="text-center text-secondary-light py-16">Tidak ada perusahaan.</td></tr>';

        return '<div class="rov-st-detail">'
            + '<div class="rov-st-detail__kepala"><div class="rov-st-detail__judul">Perusahaan di site ' + esc(s.nama)
            + ' <span class="text-secondary-light fw-normal">· ' + angka(s.aktif) + ' karyawan aktif, ' + angka(s.kurang) + ' belum di roster</span></div>'
            + '<div class="d-flex flex-wrap gap-6">' + tab + '</div></div>'
            + '<div class="table-responsive"><table class="table rov-st-tabel mb-0"><thead><tr>'
            + '<th class="ps-0">Perusahaan</th><th>Status site ini</th><th class="text-end">Aktif</th><th class="text-end">Sudah di roster</th><th class="text-end">Kurang</th><th class="pe-0">Progres</th>'
            + '</tr></thead><tbody>' + isi + '</tbody></table></div></div>';
    }

    function gambarSite() {
        var aktif = 0, kurang = 0, ptBelum = 0;
        siteData.forEach(function (s) { aktif += s.aktif; kurang += s.kurang; });
        baris.forEach(function (b) { if (b.status === 'belum' && b.site.length > 0) { ptBelum++; } });
        siteIsi.innerHTML = '<div class="rov-st-ringkas">'
            + '<div><span>Site</span><b>' + angka(siteData.length) + '</b></div>'
            + '<div><span>Karyawan aktif</span><b>' + angka(aktif) + '</b></div>'
            + '<div><span>Sudah di roster</span><b>' + angka(aktif - kurang) + '</b> <span class="d-inline text-success-main fw-semibold">' + persen(aktif - kurang, aktif) + '%</span></div>'
            + '<div><span>Belum di roster</span><b class="text-danger-main">' + angka(kurang) + '</b> <span class="d-inline">' + angka(ptBelum) + ' PT belum upload</span></div>'
            + '</div>'
            + '<div class="rov-st-legenda"><span><i class="rov-st-sudah"></i>Sudah di roster (disetujui / menunggu)</span><span><i class="rov-st-kurang"></i>Belum di roster</span>'
            + '<span class="text-secondary-light">Urut dari kurang terbanyak · klik site untuk daftar perusahaan</span></div>'
            + '<div class="rov-st-grafik">' + htmlGrafik() + '</div>'
            + htmlDetail();
    }

    siteIsi.addEventListener('click', function (e) {
        var bar = e.target.closest('[data-st-site]');
        if (bar) {
            sitePilih = bar.dataset.stSite;
            gambarSite();
            var detail = siteIsi.querySelector('.rov-st-detail');
            if (detail) { detail.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
            return;
        }
        var t = e.target.closest('[data-st-tab]');
        if (t) {
            siteTab = t.dataset.stTab;
            gambarSite();
        }
    });

    kartu.querySelectorAll('[data-cak-site-buka]').forEach(function (b) {
        b.addEventListener('click', function () {
            siteData = rakitSite();
            // Bawaan: site filter tabel bila dipilih, kalau tidak site dengan kurang terbanyak.
            sitePilih = pilihSite.value !== '' && siteData.some(function (s) { return s.nama === pilihSite.value; })
                ? pilihSite.value : (siteData[0] ? siteData[0].nama : '');
            siteTab = 'kurang';
            siteModalEl.querySelector('[data-site-konteks]').textContent = 'Semua perusahaan · ' + angka(siteData.length) + ' site';
            gambarSite();
            siteModal.show();
        });
    });
})();
</script>
<script>
// Modal "Kurang": karyawan aktif yang belum / sudah di roster untuk satu
// perusahaan. Tab, bulan, cari, dan halaman dimuat ulang di modal yang sama.
(function () {
    'use strict';

    var modalEl = document.getElementById('roCakModal');
    if (!modalEl || typeof bootstrap === 'undefined') { return; }
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var isi = modalEl.querySelector('[data-cak-modal-isi]');
    var judul = modalEl.querySelector('#roCakModalJudul');
    var minta = 0;

    function muat(url) {
        var no = ++minta;
        isi.style.opacity = isi.querySelector('.rm-xl') ? '.55' : '';
        if (!isi.querySelector('.rm-xl')) { isi.innerHTML = '<div class="ro-memuat">Memuat…</div>'; }
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.text(); })
            .then(function (html) { if (no === minta) { isi.innerHTML = html; } })
            .catch(function () {
                if (no !== minta) { return; }
                isi.innerHTML = '<div class="ro-memuat text-danger-600">Gagal memuat. <a href="#" class="text-primary-600 fw-semibold" data-ulang>Coba lagi</a></div>';
                isi.querySelector('[data-ulang]').addEventListener('click', function (e) { e.preventDefault(); muat(url); });
            })
            .finally(function () { isi.style.opacity = ''; });
    }

    isi.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-ro-muat]');
        if (!a) { return; }
        e.preventDefault();
        if (!a.classList.contains('disabled')) { muat(a.href); }
    });
    isi.addEventListener('submit', function (e) {
        var form = e.target.closest('form[data-ro-filter]');
        if (!form) { return; }
        e.preventDefault();
        muat(form.action + '?' + new URLSearchParams(new FormData(form)).toString());
    });

    document.querySelectorAll('[data-cak-kurang]').forEach(function (b) {
        b.addEventListener('click', function () {
            var pt = b.dataset.cakKurang;
            var pilihSite = document.querySelector('[data-cak-site]');
            var site = pilihSite ? pilihSite.value : '';
            judul.textContent = 'Karyawan aktif vs roster — ' + pt + (site ? ' · Site ' + site : '');
            isi.innerHTML = '<div class="ro-memuat">Memuat…</div>';
            modal.show();
            var param = { pt: pt, tampil: b.dataset.cakTab || 'belum' };
            if (site) { param.site = site; }
            muat(modalEl.dataset.url + '?' + new URLSearchParams(param).toString());
        });
    });
})();
</script>
<script>
// Overview Roster: jadwal tiap perusahaan dimuat saat barisnya dibuka; navigasi
// bulan, filter, cari, dan paginasi di dalam panel memuat ulang panel itu saja.
(function () {
    'use strict';

    function muat(panel, url) {
        var isi = panel.querySelector('[data-ro-isi]');
        isi.setAttribute('aria-busy', 'true');
        if (!isi.querySelector('.ro-sc-detail__panel')) {
            isi.innerHTML = '<div class="ro-memuat">Memuat jadwal…</div>';
        } else {
            isi.style.opacity = '.55';
        }
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.text(); })
            .then(function (html) {
                isi.innerHTML = html;
                panel.dataset.roDimuat = '1';
                pasang(panel);
            })
            .catch(function () {
                isi.innerHTML = '<div class="ro-memuat text-danger-600">Gagal memuat jadwal. <a href="#" data-ro-ulang class="text-primary-600 fw-semibold">Coba lagi</a></div>';
                isi.querySelector('[data-ro-ulang]').addEventListener('click', function (e) { e.preventDefault(); muat(panel, url); });
            })
            .finally(function () { isi.style.opacity = ''; isi.removeAttribute('aria-busy'); });
    }

    function pasang(panel) {
        panel.querySelectorAll('[data-ro-muat]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                if (!a.classList.contains('disabled')) { muat(panel, a.href); }
            });
        });
        var form = panel.querySelector('[data-ro-filter]');
        if (!form) { return; }
        var kirim = function () { muat(panel, form.action + '?' + new URLSearchParams(new FormData(form)).toString()); };
        form.addEventListener('submit', function (e) { e.preventDefault(); kirim(); });
        form.querySelectorAll('[data-ro-auto]').forEach(function (el) { el.addEventListener('change', kirim); });
    }

    document.querySelectorAll('.collapse[data-ro-url]').forEach(function (panel) {
        var baris = document.querySelector('[data-ro-baris="' + panel.id + '"]');
        panel.addEventListener('show.bs.collapse', function () {
            if (baris) { baris.classList.add('is-buka'); }
            if (!panel.dataset.roDimuat) { muat(panel, panel.dataset.roUrl); }
        });
        panel.addEventListener('hide.bs.collapse', function () { if (baris) { baris.classList.remove('is-buka'); } });
        // Dibuka sejak awal (?pt=…): langsung muat.
        if (panel.classList.contains('show')) { muat(panel, panel.dataset.roUrl); }
    });
})();
</script>
@endsection
