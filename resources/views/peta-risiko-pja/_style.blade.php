<style>
  .pr {
    --pr-bg: #f5f6f8;
    --pr-card: #ffffff;
    --pr-line: #e7e9ee;
    --pr-line-2: #f0f1f4;
    --pr-ink: #172033;
    --pr-ink-2: #4a5468;
    --pr-muted: #7a8396;
    --pr-accent: #0f766e;
    --pr-accent-soft: #e6f3f1;
    --pr-gap: #d97706;
    --pr-gap-soft: #fef3e2;
    --pr-tindak: #dc2626;
    --pr-tindak-soft: #fdecec;
    --pr-fyi: #94a3b8;
    --pr-fyi-soft: #f1f4f8;
    --pr-ok: #16a34a;
    --pr-ok-soft: #e8f6ed;
    --pr-r1: #16a34a;
    --pr-r2: #ca8a04;
    --pr-r3: #ea580c;
    --pr-r4: #b91c1c;
    --pr-radius: 14px;
    color: var(--pr-ink);
    font-size: 14px;
    line-height: 1.5;
    max-width: 1320px;
    margin: 0 auto;
    padding: 4px 0 40px;
  }
  .pr *, .pr *::before, .pr *::after { box-sizing: border-box; }
  .pr a { color: inherit; text-decoration: none; }
  .pr button { font: inherit; color: inherit; }

  /* Kepala halaman */
  .pr-head { display: flex; flex-wrap: wrap; gap: 16px 24px; align-items: flex-end; justify-content: space-between; margin-bottom: 20px; }
  .pr-eyebrow { font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--pr-accent); margin: 0 0 6px; }
  .pr-title { font-size: 24px; font-weight: 700; line-height: 1.25; margin: 0; color: var(--pr-ink); }
  .pr-sub { margin: 6px 0 0; color: var(--pr-muted); }
  .pr-back { display: inline-flex; align-items: center; gap: 6px; color: var(--pr-muted); font-size: 13px; margin-bottom: 10px; }
  .pr-back:hover { color: var(--pr-accent); }
  .pr-tools { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }

  /* Kontrol */
  .pr-seg { display: inline-flex; background: var(--pr-card); border: 1px solid var(--pr-line); border-radius: 10px; padding: 3px; }
  .pr-seg button { border: 0; background: none; padding: 6px 14px; border-radius: 7px; font-weight: 500; color: var(--pr-ink-2); cursor: pointer; white-space: nowrap; }
  .pr-seg button[aria-pressed="true"] { background: var(--pr-accent); color: #fff; }
  .pr-input, .pr-select { height: 36px; border: 1px solid var(--pr-line); border-radius: 10px; background: var(--pr-card); padding: 0 12px; color: var(--pr-ink); min-width: 0; }
  .pr-input { width: 240px; max-width: 100%; }
  .pr-input:focus, .pr-select:focus, .pr-seg button:focus-visible, .pr-row:focus-visible, .pr-chip:focus-visible, .pr-proc:focus-visible, .pr-tema:focus-visible { outline: 2px solid var(--pr-accent); outline-offset: 2px; }

  /* Kartu status */
  .pr-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 8px; }
  .pr-kpi { background: var(--pr-card); border: 1px solid var(--pr-line); border-radius: var(--pr-radius); padding: 14px 16px; text-align: left; cursor: pointer; transition: border-color .15s, box-shadow .15s; position: relative; overflow: hidden; }
  .pr-kpi::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--c); }
  .pr-kpi:hover { border-color: var(--c); }
  .pr-kpi[aria-pressed="true"] { border-color: var(--c); box-shadow: 0 0 0 3px var(--cs); }
  .pr-kpi-top { display: flex; align-items: center; gap: 8px; font-weight: 600; color: var(--pr-ink-2); font-size: 13px; }
  .pr-kpi-num { font-size: 28px; font-weight: 700; line-height: 1.1; margin: 6px 0 2px; color: var(--pr-ink); font-variant-numeric: tabular-nums; }
  .pr-kpi-desc { font-size: 12px; color: var(--pr-muted); }
  .pr-tag { display: inline-block; font-size: 11px; font-weight: 600; padding: 1px 7px; border-radius: 99px; background: var(--cs); color: var(--c); margin-left: auto; }
  .pr-note { font-size: 12px; color: var(--pr-muted); margin: 0 0 18px; }

  .s-gap { --c: var(--pr-gap); --cs: var(--pr-gap-soft); }
  .s-tindak { --c: var(--pr-tindak); --cs: var(--pr-tindak-soft); }
  .s-fyi { --c: var(--pr-fyi); --cs: var(--pr-fyi-soft); }
  .s-ok { --c: var(--pr-ok); --cs: var(--pr-ok-soft); }
  .pr-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--c); flex: none; display: inline-block; }

  /* Kartu umum */
  .pr-card { background: var(--pr-card); border: 1px solid var(--pr-line); border-radius: var(--pr-radius); }
  .pr-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 18px; border-bottom: 1px solid var(--pr-line-2); }
  .pr-card-title { font-size: 15px; font-weight: 650; margin: 0; }
  .pr-card-sub { font-size: 12px; color: var(--pr-muted); margin: 2px 0 0; }

  /* Bar bertumpuk */
  .pr-bar { display: flex; height: 8px; border-radius: 99px; overflow: hidden; background: var(--pr-line-2); min-width: 80px; }
  .pr-bar i { display: block; height: 100%; background: var(--c); }

  /* Daftar PJA */
  .pr-list-head, .pr-row { display: grid; grid-template-columns: minmax(220px, 2.2fr) 70px minmax(140px, 1.6fr) 110px 120px 24px; gap: 16px; align-items: center; padding: 12px 18px; }
  .pr-list-head { font-size: 12px; font-weight: 600; color: var(--pr-muted); border-bottom: 1px solid var(--pr-line-2); }
  .pr-row { border-bottom: 1px solid var(--pr-line-2); transition: background .12s; }
  .pr-row:last-child { border-bottom: 0; }
  .pr-row:hover { background: #fafbfc; }
  .pr-row-name { font-weight: 600; color: var(--pr-ink); }
  .pr-row-site { font-size: 12px; color: var(--pr-muted); }
  .pr-num { font-variant-numeric: tabular-nums; font-weight: 600; }
  .pr-num small { font-weight: 400; color: var(--pr-muted); }
  .pr-act { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-variant-numeric: tabular-nums; }
  .pr-act.nol { color: var(--pr-muted); font-weight: 500; }
  .pr-late { display: inline-block; font-size: 11px; font-weight: 600; color: var(--pr-tindak); background: var(--pr-tindak-soft); padding: 0 6px; border-radius: 99px; margin-left: 4px; }
  .pr-chev { color: #b5bccb; }
  .pr-empty { padding: 40px 18px; text-align: center; color: var(--pr-muted); }
  .pr-site-group { padding: 8px 18px; font-size: 12px; font-weight: 700; letter-spacing: .04em; color: var(--pr-ink-2); background: #fafbfc; border-bottom: 1px solid var(--pr-line-2); }

  /* Rantai proses */
  .pr-chain { padding: 16px 18px 6px; }
  .pr-stage { margin-bottom: 14px; }
  .pr-stage-label { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--pr-muted); margin-bottom: 8px; }
  .pr-flow { display: flex; flex-wrap: wrap; gap: 10px; align-items: stretch; }
  .pr-arrow { align-self: center; color: #c3c9d5; font-size: 16px; }
  .pr-proc { border: 1px solid var(--pr-line); background: var(--pr-card); border-radius: 12px; padding: 10px 12px; width: 190px; text-align: left; cursor: pointer; transition: border-color .15s, box-shadow .15s; display: flex; flex-direction: column; gap: 6px; }
  .pr-proc:hover { border-color: var(--pr-accent); }
  .pr-proc[aria-pressed="true"] { border-color: var(--pr-accent); box-shadow: 0 0 0 3px var(--pr-accent-soft); background: #fcfefe; }
  .pr-proc-name { font-weight: 600; font-size: 13px; line-height: 1.3; }
  .pr-proc-meta { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 2px 8px; font-size: 12px; color: var(--pr-muted); }
  .pr-proc-meta > * { white-space: nowrap; }
  .pr-proc-meta b { color: var(--pr-tindak); font-weight: 700; }
  .pr-proc-meta b.nol { color: var(--pr-muted); font-weight: 500; }

  /* Panel proses */
  .pr-panel { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 16px; margin-top: 16px; }
  .pr-chips { display: flex; flex-wrap: wrap; gap: 6px; }
  .pr-chip { border: 1px solid var(--pr-line); background: var(--pr-card); border-radius: 99px; padding: 4px 11px; font-size: 12px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; color: var(--pr-ink-2); }
  .pr-chip[aria-pressed="true"] { border-color: var(--c, var(--pr-accent)); background: var(--cs, var(--pr-accent-soft)); color: var(--pr-ink); }
  .pr-chip span { color: var(--pr-muted); font-variant-numeric: tabular-nums; }
  .pr-tema { display: grid; grid-template-columns: 150px minmax(0, 1fr) 168px 56px 70px 18px; gap: 14px; align-items: center; width: 100%; padding: 12px 18px; border: 0; border-bottom: 1px solid var(--pr-line-2); background: none; text-align: left; cursor: pointer; }
  .pr-tema:hover { background: #fafbfc; }
  .pr-tema:last-child { border-bottom: 0; }
  .pr-tema-head { cursor: default; font-size: 12px; font-weight: 600; color: var(--pr-muted); }
  .pr-tema-head:hover { background: none; }
  .pr-status { display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 600; color: var(--c); white-space: nowrap; }
  .pr-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .pr-tema-name { display: block; font-weight: 600; }
  .pr-tema-hz { display: block; font-size: 12px; color: var(--pr-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .pr-risk { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; white-space: nowrap; color: var(--pr-muted); }
  .pr-lv { display: inline-block; white-space: nowrap; padding: 1px 7px; border-radius: 6px; font-size: 11px; font-weight: 600; color: #fff; }
  .pr-lv.l1 { background: var(--pr-r1); } .pr-lv.l2 { background: var(--pr-r2); } .pr-lv.l3 { background: var(--pr-r3); } .pr-lv.l4 { background: var(--pr-r4); } .pr-lv.l0 { background: #cbd2dc; }
  .pr-loc { padding: 6px 0; }
  .pr-loc-k { padding: 8px 18px 2px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--pr-muted); }
  .pr-loc-i { display: flex; justify-content: space-between; gap: 10px; padding: 5px 18px; font-size: 13px; }
  .pr-loc-i span:last-child { color: var(--pr-muted); font-variant-numeric: tabular-nums; white-space: nowrap; }
  .pr-desc { font-size: 13px; color: var(--pr-ink-2); padding: 12px 18px 0; margin: 0; }

  /* Laci detail */
  .pr-drawer-bg { position: fixed; inset: 0; background: rgba(15, 23, 42, .32); z-index: 1990; opacity: 0; pointer-events: none; transition: opacity .2s; }
  .pr-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: min(620px, 100%); background: var(--pr-bg); z-index: 2000; transform: translateX(100%); transition: transform .25s ease; display: flex; flex-direction: column; box-shadow: -12px 0 40px rgba(15, 23, 42, .12); }
  .pr-open .pr-drawer-bg { opacity: 1; pointer-events: auto; }
  .pr-open .pr-drawer { transform: none; }
  .pr-drawer-head { background: var(--pr-card); padding: 18px 20px; border-bottom: 1px solid var(--pr-line); display: flex; gap: 12px; align-items: flex-start; }
  .pr-drawer-head h2 { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
  .pr-close { margin-left: auto; border: 0; background: var(--pr-line-2); width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 18px; line-height: 1; flex: none; }
  .pr-drawer-body { overflow-y: auto; padding: 16px 20px 32px; display: flex; flex-direction: column; gap: 14px; }
  .pr-explain { border-radius: 12px; padding: 12px 14px; background: var(--cs); border: 1px solid color-mix(in srgb, var(--c) 30%, transparent); font-size: 13px; }
  .pr-explain b { color: var(--c); }
  .pr-sec { background: var(--pr-card); border: 1px solid var(--pr-line); border-radius: 12px; }
  .pr-sec h3 { font-size: 13px; font-weight: 700; margin: 0; padding: 12px 14px; border-bottom: 1px solid var(--pr-line-2); display: flex; justify-content: space-between; }
  .pr-sec h3 span { color: var(--pr-muted); font-weight: 500; }
  .pr-li { padding: 10px 14px; border-bottom: 1px solid var(--pr-line-2); font-size: 13px; }
  .pr-li:last-child { border-bottom: 0; }
  .pr-li-top { display: flex; gap: 10px; justify-content: space-between; align-items: baseline; }
  .pr-li-sub { color: var(--pr-muted); font-size: 12px; }
  .pr-layer { padding: 10px 14px 4px; font-size: 11px; font-weight: 700; color: var(--pr-accent); text-transform: uppercase; letter-spacing: .05em; }
  .pr-ctl { display: flex; gap: 8px; padding: 4px 14px; font-size: 13px; }
  .pr-ctl::before { content: ""; width: 5px; height: 5px; border-radius: 50%; background: var(--pr-accent); margin-top: 8px; flex: none; }
  .pr-ctl small { color: var(--pr-muted); white-space: nowrap; }
  .pr-hz { padding: 10px 14px 6px; font-weight: 600; font-size: 13px; }
  .pr-hz small { display: block; font-weight: 400; color: var(--pr-muted); }
  .pr-more { display: block; width: 100%; border: 0; background: none; padding: 10px; color: var(--pr-accent); font-weight: 600; cursor: pointer; font-size: 13px; }
  .pr-foot { margin-top: 22px; font-size: 12px; color: var(--pr-muted); }
  .pr-foot li { margin-bottom: 2px; }

  @media (max-width: 1100px) {
    .pr-panel { grid-template-columns: 1fr; }
    .pr-list-head, .pr-row { grid-template-columns: minmax(0, 1fr) minmax(90px, 1fr) 90px 20px; }
    .pr-list-head > :nth-child(2), .pr-row > :nth-child(2), .pr-list-head > :nth-child(5), .pr-row > :nth-child(5) { display: none; }
  }
  @media (max-width: 760px) {
    .pr-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pr-title { font-size: 20px; }
    .pr-input { width: 100%; }
    .pr-tools { width: 100%; }
    .pr-tema { grid-template-columns: minmax(0, 1fr) 70px 18px; }
    .pr-tema > :nth-child(1) { grid-column: 1 / -1; }
    .pr-tema > :nth-child(3), .pr-tema > :nth-child(4) { display: none; }
    .pr-proc { width: calc(50% - 5px); }
    .pr-arrow { display: none; }
  }
</style>
