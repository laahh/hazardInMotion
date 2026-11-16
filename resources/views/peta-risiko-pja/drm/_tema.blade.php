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
