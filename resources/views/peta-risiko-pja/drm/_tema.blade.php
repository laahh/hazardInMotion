{{--
  Tema Peta Risiko PJA mengikuti konsep template /peer-pressure-edukasi/dashboard-peer:
  latar #f0f2f5, font Poppins, primary #3952bc / secondary #72479e, bilah atas putih menempel
  (merek + menu), judul halaman besar dengan breadcrumb, kartu putih "anchored" (rounded 16px).
  Ditulis sebagai CSS biasa (bukan Tailwind CDN) supaya preflight Tailwind tidak menimpa tabel,
  tombol, dan komponen lama; kelas lama (pr-*, dr-*, br-*) tetap dipakai, di sini hanya ditimpa.
--}}
<style>
  body.tp {
    --primary: #3952bc; --primary-dim: #2b45af; --secondary: #72479e;
    --navy: #3952bc; --ink: #2c2f31; --mute: #595c5e; --mute-2: #747779;
    --line: rgba(0, 0, 0, .08); --line-2: #dfe3e6; --sky: #f5f7f9;
    --shadow: 0 4px 0 0 rgba(0, 0, 0, .05), 0 12px 24px -4px rgba(0, 0, 0, .15);
    background: #f0f2f5; color: var(--ink);
  }
  body.tp [hidden] { display: none !important; }
  body.tp, body.tp *:not(.material-symbols-outlined):not(code) { font-family: "Poppins", "Segoe UI", system-ui, sans-serif !important; }
  body.tp .material-symbols-outlined { font-family: "Material Symbols Outlined" !important; font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; line-height: 1; }

  /* ---- Bilah atas (TopAppBar) ---- */
  .tp-bar { position: sticky; top: 0; z-index: 50; background: #fff; border-bottom: 1px solid #dfe3e6; box-shadow: 0 1px 2px rgba(0, 0, 0, .05); }
  .tp-bar-in { display: flex; align-items: center; gap: 28px; padding: 14px 32px; }
  .tp-brand { display: flex; align-items: center; gap: 12px; flex: none; }
  .tp-brand img { height: 34px; display: block; }
  .tp-brand b { display: block; font-weight: 700; font-size: 19px; line-height: 1.15; letter-spacing: -.03em; color: var(--primary); }
  .tp-brand span { display: block; font-size: 10px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--mute); }
  .tp-sep { width: 1px; height: 32px; background: #dfe3e6; flex: none; }
  .tp-nav, body.tp .dr-tabs.tp-nav { display: flex; gap: 26px; flex: 1; min-width: 0; overflow-x: auto; border: 0; margin: 0; scrollbar-width: none; }
  .tp-nav::-webkit-scrollbar { display: none; }
  .tp-nav a, body.tp .dr-tabs.tp-nav button { font-size: 14px !important; font-weight: 600; letter-spacing: -.01em; color: var(--mute); padding: 6px 0 5px; border: 0; border-bottom: 2px solid transparent; background: none; white-space: nowrap; cursor: pointer; transition: color .15s; }
  .tp-nav a:hover, body.tp .dr-tabs.tp-nav button:hover { color: var(--primary); }
  .tp-nav a[aria-current="page"], body.tp .dr-tabs.tp-nav button[aria-selected="true"] { color: var(--primary); font-weight: 700; border-bottom-color: var(--primary); }
  .tp-aksi { display: flex; gap: 8px; flex: none; margin-left: auto; }

  /* ---- Area utama + judul halaman ---- */
  body.tp .pr-main.tp-main { margin: 0; padding: 32px 32px 64px; }
  .tp-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 20px 24px; padding-bottom: 22px; margin-bottom: 26px; border-bottom: 1px solid rgba(171, 173, 175, .3); }
  .tp-head-teks { min-width: 0; }
  body.tp .pr-kicker { display: flex; align-items: center; gap: 6px; margin: 0 0 8px; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--mute); }
  body.tp .pr-kicker::before, body.tp .pr-kicker::after { content: none; }
  body.tp .pr-kicker .tp-kini { color: var(--primary); }
  .tp-crumb-sep { font-size: 14px !important; color: var(--mute); }
  body.tp .pr-title { font-size: clamp(26px, 3vw, 36px); font-weight: 800; line-height: 1.15; letter-spacing: -.02em; color: var(--ink); margin: 0; }
  body.tp .pr-sub { margin: 6px 0 0; font-size: 14px; font-weight: 500; color: var(--mute); }
  .tp-head-aksi { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }

  /* ---- Tombol aksi ---- */
  body.tp .pr-back { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 999px; background: #fff; border: 1px solid rgba(171, 173, 175, .45); color: var(--ink); font-size: 13px; font-weight: 600; box-shadow: 0 1px 2px rgba(0, 0, 0, .05); transition: background .15s, border-color .15s; }
  body.tp .pr-back:hover { background: #f5f7f9; border-color: var(--primary); color: var(--primary); }

  /* ---- Kartu (anchored-card) ---- */
  body.tp .pr-card { background: #fff; border: 1px solid rgba(0, 0, 0, .08); border-radius: 16px; box-shadow: var(--shadow); }
  body.tp .pr-card-head, body.tp .br-rhead { background: #fff; border-bottom: 1px solid rgba(171, 173, 175, .2); padding: 18px 24px; }
  body.tp .pr-card-head b, body.tp .br-rhead b.t { font-size: 16px; font-weight: 700; letter-spacing: -.01em; color: var(--ink); }
  body.tp .pr-card-head small, body.tp .br-rhead small { font-size: 12.5px; color: var(--mute); }
  body.tp .pr-tbl th { font-size: 10.5px; font-weight: 700; letter-spacing: .06em; color: var(--mute); background: #f8fafc; }
  body.tp .pr-name { font-size: 14px; font-weight: 600; }

  /* ---- Kartu KPI ---- */
  body.tp .br-kpis { gap: 20px; }
  body.tp .br-kpi { border-radius: 16px; padding: 22px 22px 20px; border: 1px solid rgba(0, 0, 0, .08); border-left: 0; box-shadow: var(--shadow); transition: transform .2s ease, box-shadow .2s ease; }
  body.tp .br-kpi::before { content: none; }
  body.tp .br-kpi:hover { transform: translateY(-2px); box-shadow: 0 6px 0 0 rgba(0, 0, 0, .05), 0 20px 32px -8px rgba(0, 0, 0, .2); }
  body.tp .br-kpi small { font-size: 11px; font-weight: 700; letter-spacing: .08em; color: var(--mute); padding-right: 46px; }
  body.tp .br-kpi b { margin: 14px 0 6px; font-size: 34px; font-weight: 800; letter-spacing: -.02em; }
  body.tp .br-kpi em { font-size: 11.5px; font-weight: 500; }
  /* 5 kartu KPI dalam satu baris (Overview / Group Lokasi / Kelompok Aktivitas). */
  body.tp .br-kpis.br-kpis-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 16px; }
  body.tp .br-kpis-5 .br-kpi { padding: 20px 18px 18px; }
  body.tp .br-kpis-5 .br-kpi b { font-size: 30px; }
  @media (max-width: 1280px) { body.tp .br-kpis.br-kpis-5 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
  @media (max-width: 760px) { body.tp .br-kpis.br-kpis-5 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 480px) { body.tp .br-kpis.br-kpis-5 { grid-template-columns: 1fr; } }
  /* Baseline + 5 kartu status (angka) dalam satu baris. */
  body.tp .br-kpis.br-kpis-6 { grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 14px; }
  body.tp .br-kpis-6 .br-kpi { padding: 18px 16px 16px; }
  body.tp .br-kpis-6 .br-kpi b { font-size: 30px; }
  body.tp .br-kpis-6 .br-kpi small { padding-right: 44px; line-height: 1.35; }
  @media (max-width: 1440px) { body.tp .br-kpis.br-kpis-6 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
  @media (max-width: 760px) { body.tp .br-kpis.br-kpis-6 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 480px) { body.tp .br-kpis.br-kpis-6 { grid-template-columns: 1fr; } }
  /* Kartu KPI yang bisa diklik → modal daftar bahaya. */
  body.tp .br-kpi-klik { cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; }
  body.tp .br-kpi-klik:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(20, 26, 34, .10); }
  body.tp .br-kpi-klik:focus-visible { outline: 2px solid #3952bc; outline-offset: 3px; }
  .dr-modal { --mc: #3952bc; width: min(1100px, calc(100vw - 32px)); max-height: calc(100vh - 48px); padding: 0; border: 0; border-radius: 16px;
    box-shadow: 0 24px 64px rgba(15, 23, 42, .28); font-family: Poppins, sans-serif; color: #2c2f31; overflow: hidden; }
  .dr-modal[open] { display: flex; flex-direction: column; }
  .dr-modal::backdrop { background: rgba(15, 23, 42, .45); }
  .dr-modal-kepala { display: flex; gap: 16px; justify-content: space-between; align-items: flex-start; padding: 20px 24px 14px; border-top: 5px solid var(--mc); }
  .dr-modal-kepala h3 { margin: 0; font-size: 18px; font-weight: 700; color: var(--mc); }
  .dr-modal-kepala p { margin: 4px 0 0; font-size: 12.5px; color: #595c5e; line-height: 1.45; }
  .dr-modal-tutup { border: 0; background: #f3f4f6; border-radius: 999px; width: 36px; height: 36px; display: grid; place-items: center; cursor: pointer; flex: none; }
  .dr-modal-tutup:hover { background: #e5e7eb; }
  .dr-modal-alat { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; padding: 0 24px 12px; border-bottom: 1px solid #eef0f2; font-size: 12.5px; }
  .dr-modal-cari { position: relative; flex: 1 1 280px; max-width: 460px; }
  .dr-modal-cari .material-symbols-outlined { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 19px; color: #8a8d90; }
  .dr-modal-cari input { width: 100%; padding: 9px 12px 9px 36px; border: 1px solid #d9dce0; border-radius: 10px; font: inherit; font-size: 13px; }
  .dr-modal-cari input:focus { outline: 2px solid var(--mc); outline-offset: 0; border-color: transparent; }
  .dr-modal-isi { overflow: auto; padding: 0 24px 20px; flex: 1; min-height: 160px; }
  .dr-modal-tbl { width: 100%; font-size: 12.5px; }
  .dr-modal-tbl thead th { position: sticky; top: 0; background: #fff; z-index: 1; }
  .dr-modal-tbl td small { display: block; color: #8a8d90; font-size: 11.5px; margin-top: 2px; }
  .dr-modal-grp td { background: #f8fafc; font-weight: 700; font-size: 12px; color: #3f4346; padding-top: 10px; }
  .dr-modal-grp td span { margin-left: 6px; font-weight: 600; color: #8a8d90; }
  .dr-jejak { display: inline-flex; gap: 2px; }
  .dr-jejak i { width: 9px; height: 16px; border-radius: 2px; }
  .dr-jejak i.sel { outline: 2px solid #2c2f31; outline-offset: 1px; }
  .dr-lv { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #f3f4f6; color: #4b5563; }
  .dr-lv.lv-Significant { background: #fdecee; color: #b91c1c; } .dr-lv.lv-High { background: #fff0e3; color: #b45309; }
  .dr-lv.lv-Medium { background: #fef8e1; color: #92650a; } .dr-lv.lv-Low { background: #eef8f0; color: #15803d; }
  .dr-tanda { display: inline-block; margin: 4px 6px 0 0; padding: 1px 7px; border-radius: 999px; font-size: 10.5px; font-weight: 600; }
  .dr-tanda.ungu { background: #f1e8fa; color: #7e3fb3; } .dr-tanda.hijau { background: #dcfce7; color: #15803d; }
  .dr-tanda.merah { background: #fdecee; color: #b91c1c; }
  .dr-sap-btn { display: inline-flex; align-items: center; gap: 4px; padding: 5px 10px; border-radius: 999px; border: 1px solid #d9dce0; background: #fff;
    font: 600 12px/1 Poppins, sans-serif; color: #3952bc; cursor: pointer; white-space: nowrap; }
  .dr-sap-btn .material-symbols-outlined { font-size: 16px; }
  .dr-sap-btn:hover, .dr-sap-btn.on { background: #eef1fd; border-color: #3952bc; }
  .dr-modal-sap > td { background: #fafbfc; padding: 12px 14px 14px !important; }
  /* Tabel Baseline lebar (gaya DRM 2.1 lama): status, risiko akhir, 4 gap, item sumber, volume. */
  .dr-modal.lebar { width: min(1480px, calc(100vw - 32px)); }
  .dr-bl-tbl { min-width: 1360px; }
  .dr-bl-tbl thead th { font-size: 10.5px; line-height: 1.25; vertical-align: bottom; white-space: normal; }
  .dr-bl-tbl td { vertical-align: top; }
  .dr-bl-tbl td.c, .dr-bl-tbl th.c { text-align: center; }
  .dr-bl-tbl td.n, .dr-bl-tbl th.n { text-align: right; font-variant-numeric: tabular-nums; }
  .dr-bl-tbl .akt { min-width: 150px; max-width: 220px; } .dr-bl-tbl .bhy { min-width: 180px; max-width: 280px; }
  .dr-st { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; border: 1px solid transparent; }
  .dr-st.K { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
  .dr-st.G { background: #fef9c3; color: #854d0e; border-color: #fde68a; }
  .dr-st.T { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
  .dr-gap { display: inline-block; padding: 2px 7px; border-radius: 6px; font-size: 10.5px; font-weight: 600; background: #f3f4f6; color: #8a8d90; white-space: nowrap; }
  .dr-gap.ya { background: #fdecee; color: #b91c1c; text-decoration: underline; }
  .dr-gap.na { background: transparent; color: #b0b3b6; font-style: italic; font-weight: 500; }
  .dr-item { border: 0; background: none; padding: 0; color: #1d4ed8; text-decoration: underline; font: inherit; font-size: 12px; cursor: pointer; white-space: nowrap; }
  .dr-item:hover { color: #1e3a8a; }
  .dr-nol { color: #b0b3b6; }
  .dr-sap-ringkas { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-bottom: 10px; font-size: 12.5px; }
  .dr-sap-tbl { min-width: 880px; font-size: 12px; }
  .dr-sap-tbl td small { display: block; color: #8a8d90; font-size: 11px; margin-top: 2px; }
  @media (max-width: 640px) { .dr-modal-kepala, .dr-modal-alat, .dr-modal-isi { padding-left: 16px; padding-right: 16px; } }
  @media (prefers-reduced-motion: reduce) { body.tp .br-kpi-klik { transition: none; } body.tp .br-kpi-klik:hover { transform: none; } }
  /* Kartu status (angka, garis warna di kiri) — gaya dashboard DRM 2.1 lama. */
  body.tp .tp-stat-sub { font-size: 12.5px; color: #595c5e; margin: 0 0 10px; }
  body.tp .tp-stat-sub b { color: #2c2f31; }
  body.tp .tp-stats { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; }
  body.tp .tp-stat { background: #fff; border-radius: 12px; border-left: 5px solid var(--c); padding: 14px 18px 16px;
    box-shadow: 0 1px 2px rgba(20, 26, 34, .06), 0 4px 14px rgba(20, 26, 34, .05); display: flex; flex-direction: column; gap: 4px; }
  body.tp .tp-stat b { font: 800 28px/1.1 Poppins, sans-serif; color: var(--c); font-variant-numeric: tabular-nums; }
  body.tp .tp-stat span { font-size: 12.5px; line-height: 1.35; color: #3f4346; }
  body.tp .tp-stat em { font-style: normal; font-size: 11.5px; color: #8a8d90; }
  @media (max-width: 1280px) { body.tp .tp-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
  @media (max-width: 760px) { body.tp .tp-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 480px) { body.tp .tp-stats { grid-template-columns: 1fr; } }
  .tp-ikon { position: absolute; top: 18px; right: 18px; display: grid; place-items: center; width: 38px; height: 38px; border-radius: 10px; background: var(--ib, rgba(57, 82, 188, .1)); color: var(--ic, var(--primary)); }
  .tp-ikon .material-symbols-outlined { font-size: 22px; }

  /* ---- Filter (kanan judul) ---- */
  body.tp .dr-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 0; margin: 0; background: none; border: 0; }
  body.tp .dr-bar label { display: inline-flex; align-items: center; gap: 8px; padding: 6px 6px 6px 14px; border-radius: 12px; background: #f8fafc; border: 1px solid rgba(171, 173, 175, .3); box-shadow: inset 0 1px 2px rgba(0, 0, 0, .04); font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--mute); }
  body.tp .dr-bar select, body.tp .dr-bar input { border: 0; background: transparent; font-size: 13.5px; font-weight: 700; color: var(--ink); text-transform: none; letter-spacing: 0; padding: 4px 6px; }
  body.tp .dr-bar select:focus, body.tp .dr-bar input:focus { outline: 2px solid rgba(57, 82, 188, .35); border-radius: 8px; }

  @media (max-width: 900px) {
    .tp-bar-in { flex-wrap: wrap; gap: 12px 16px; padding: 12px 16px; }
    .tp-sep { display: none; }
    .tp-nav, body.tp .dr-tabs.tp-nav { order: 3; flex-basis: 100%; gap: 18px; }
    body.tp .pr-main.tp-main { padding: 20px 16px 48px; }
  }
</style>
