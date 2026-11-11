{{-- Bahasa visual mengikuti deck Company Profile Berau Coal (request/CompanyProfileBC.html). --}}
<style>
  :root {
    --sky: #F4FAFF;
    --sky-2: #E6F2FD;
    --panel: #FFFFFF;
    --ink: #141A22;
    --mute: #5B646D;
    --mute-2: #8A939C;
    --line: rgba(20, 26, 34, .08);
    --line-2: rgba(20, 26, 34, .14);
    --navy: #16294F;
    --navy-2: #0F2D6B;
    --green: #1F9A3A;
    --green-d: #147A2C;
    --lime: #5DBB46;
    --amber: #C77800;   --amber-bg: #FFF2D6;
    --red: #C8102E;     --red-bg: #FDE7EA;
    --grey: #8A939C;    --grey-bg: #EEF2F6;
    --done: #16712C;    --done-bg: #E2F3E5;
    --track: #E7ECF2;
    --ease: cubic-bezier(.16, 1, .3, 1);
    --shadow: 0 1px 2px rgba(20, 26, 34, .04), 0 10px 28px rgba(20, 26, 34, .07);
  }
  *, *::before, *::after { box-sizing: border-box; }
  body.pr {
    margin: 0;
    background: var(--sky);
    color: var(--ink);
    font: 16px/1.5 "Source Sans 3", "Segoe UI", system-ui, sans-serif;
    -webkit-font-smoothing: antialiased;
  }
  :where(.pr) a { color: inherit; text-decoration: none; }
  :where(.pr) button { font: inherit; color: inherit; background: none; border: 0; padding: 0; cursor: pointer; text-align: left; }
  .pr :focus-visible { outline: 2px solid var(--green); outline-offset: 2px; border-radius: 6px; }
  .pr .jk { font-family: "Plus Jakarta Sans", "Segoe UI", sans-serif; }

  /* Banner */
  .pr-hero { position: relative; color: #fff; background: var(--navy) var(--hero) center 62% / cover no-repeat; }
  .pr-hero::before { content: ""; position: absolute; inset: 0; background: linear-gradient(100deg, rgba(15, 45, 107, .94) 0%, rgba(22, 41, 79, .86) 45%, rgba(22, 41, 79, .45) 100%); }
  .pr-hero-in { position: relative; max-width: 1280px; margin: 0 auto; padding: 22px 32px 96px; }
  .pr-hero-top { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 34px; }
  .pr-logo { display: inline-flex; background: #fff; border-radius: 10px; padding: 4px 14px; }
  .pr-logo img { height: 40px; display: block; }
  .pr-kicker { display: flex; align-items: center; gap: 12px; font: 700 12px/1 "Plus Jakarta Sans", sans-serif; letter-spacing: .2em; text-transform: uppercase; color: rgba(255, 255, 255, .78); margin: 0 0 14px; }
  .pr-kicker::before { content: ""; width: 36px; height: 4px; border-radius: 2px; background: linear-gradient(90deg, var(--green-d), var(--lime)); }
  .pr-title { font: 800 clamp(26px, 3.4vw, 40px)/1.15 "Plus Jakarta Sans", sans-serif; letter-spacing: -.02em; margin: 0; }
  .pr-sub { margin: 10px 0 0; color: rgba(255, 255, 255, .78); font-size: 16px; }
  .pr-back { display: inline-flex; align-items: center; gap: 8px; color: rgba(255, 255, 255, .85); font: 600 14px/1 "Plus Jakarta Sans", sans-serif; padding: 8px 12px; border-radius: 8px; background: rgba(255, 255, 255, .12); transition: background .2s; }
  .pr-back:hover { background: rgba(255, 255, 255, .2); }

  .pr-seg { display: inline-flex; background: rgba(255, 255, 255, .14); border-radius: 10px; padding: 3px; }
  .pr-seg button { font: 700 13px/1 "Plus Jakarta Sans", sans-serif; padding: 9px 14px; border-radius: 8px; color: rgba(255, 255, 255, .85); transition: background .25s var(--ease), color .25s; }
  .pr-seg button[aria-pressed="true"] { background: #fff; color: var(--navy); }

  .pr-main { position: relative; max-width: 1280px; margin: -64px auto 0; padding: 0 32px 64px; }

  /* Kartu */
  .pr-card { background: var(--panel); border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); overflow: hidden; }
  .pr-card + .pr-card, .pr-gap { margin-top: 20px; }
  .pr-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 20px; padding: 16px 22px; border-bottom: 1px solid var(--line); background: linear-gradient(180deg, #F4F8F5, #fff); }
  .pr-card-head b { display: block; font: 800 17px/1.2 "Plus Jakarta Sans", sans-serif; letter-spacing: -.01em; }
  .pr-card-head small { display: block; margin-top: 3px; font-size: 14px; font-weight: 500; color: var(--mute); }
  .pr-card-body { padding: 20px 22px; }

  /* KPI */
  .pr-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; padding: 18px; }
  .pr-kpi { border-radius: 12px; padding: 14px 16px; background: var(--bg); border: 1px solid color-mix(in srgb, var(--c) 18%, transparent); }
  .pr-kpi b { display: block; font: 800 30px/1.05 "Plus Jakarta Sans", sans-serif; color: var(--c); letter-spacing: -.02em; }
  .pr-kpi strong { display: block; margin-top: 6px; font: 700 14px/1.2 "Plus Jakarta Sans", sans-serif; color: var(--ink); }
  .pr-kpi span { display: block; margin-top: 3px; font-size: 13px; color: var(--mute); line-height: 1.35; }
  .s-gap { --c: var(--amber); --bg: var(--amber-bg); }
  .s-tindak { --c: var(--red); --bg: var(--red-bg); }
  .s-fyi { --c: #5B646D; --bg: var(--grey-bg); }
  .s-ok { --c: var(--done); --bg: var(--done-bg); }
  .pr-note { margin: 0; padding: 0 22px 16px; font-size: 13px; color: var(--mute); }

  /* Filter */
  .pr-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
  .pr-chips { display: flex; gap: 6px; flex-wrap: wrap; }
  .pr-chip { display: inline-flex; align-items: baseline; gap: 2px; font: 700 13px/1 "Plus Jakarta Sans", sans-serif; padding: 8px 12px; border-radius: 999px; border: 1px solid var(--line-2); color: var(--mute); background: #fff; transition: background .2s, color .2s, border-color .2s; white-space: nowrap; }
  .pr-chip:hover { border-color: var(--navy); color: var(--navy); }
  .pr-chip[aria-pressed="true"] { background: var(--navy); border-color: var(--navy); color: #fff; }
  .pr-chip small { font-weight: 600; opacity: .7; margin-left: 4px; }
  .pr-search { height: 36px; width: 230px; max-width: 100%; border: 1px solid var(--line-2); border-radius: 10px; padding: 0 12px; font: inherit; font-size: 15px; background: #fff; color: var(--ink); }
  .pr-search:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(31, 154, 58, .15); }

  /* Tabel */
  .pr-tbl { width: 100%; border-collapse: collapse; }
  .pr-tbl th { text-align: left; font: 700 12px/1.2 "Plus Jakarta Sans", sans-serif; letter-spacing: .04em; text-transform: uppercase; color: var(--mute-2); padding: 12px 22px; border-bottom: 1px solid var(--line); background: #FAFCFE; }
  .pr-tbl td { padding: 14px 22px; border-bottom: 1px solid var(--line); vertical-align: middle; }
  .pr-tbl tr:last-child td { border-bottom: 0; }
  .pr-tbl .r { text-align: right; }
  .pr-tbl tbody tr { transition: background .15s; }
  .pr-tbl tbody tr[data-href], .pr-tbl tbody tr[data-tema] { cursor: pointer; }
  .pr-tbl tbody tr[data-href]:hover, .pr-tbl tbody tr[data-tema]:hover { background: #F6FAFE; }
  .pr-name { font: 700 15px/1.3 "Plus Jakarta Sans", sans-serif; color: var(--ink); }
  .pr-meta { display: block; font-size: 14px; color: var(--mute); margin-top: 2px; }
  .pr-big { font: 800 18px/1 "Plus Jakarta Sans", sans-serif; }
  .pr-big.amber { color: var(--amber); }
  .pr-big.zero { color: var(--mute-2); font-weight: 700; }
  .pr-late { display: block; font-size: 13px; color: var(--red); font-weight: 600; margin-top: 3px; }
  .pr-of { display: block; font-size: 13px; color: var(--mute); margin-top: 3px; }

  .pr-comp { display: flex; height: 12px; border-radius: 2px; overflow: hidden; background: var(--track); min-width: 140px; }
  .pr-comp i { display: block; height: 100%; background: var(--c); transform-origin: left center; animation: prGrow .7s var(--ease) both; }
  .pr-comp i + i { box-shadow: inset 1px 0 0 #fff; }
  @keyframes prGrow { from { transform: scaleX(0); } to { transform: scaleX(1); } }

  .pr-tag { display: inline-block; padding: 3px 9px; border-radius: 6px; font: 700 12px/1.3 "Plus Jakarta Sans", sans-serif; background: var(--bg); color: var(--c); white-space: nowrap; }
  .pr-lv { display: inline-block; padding: 2px 8px; border-radius: 4px; font: 700 12px/1.4 "Plus Jakarta Sans", sans-serif; color: #fff; white-space: nowrap; }
  .pr-lv.l1 { background: var(--green); } .pr-lv.l2 { background: #C99400; } .pr-lv.l3 { background: #E06A10; } .pr-lv.l4 { background: var(--red); } .pr-lv.l0 { background: #C7CED6; }
  .pr-arrow { color: var(--mute-2); margin: 0 4px; }

  /* Rantai proses */
  .pr-stage { display: grid; grid-template-columns: 140px minmax(0, 1fr); gap: 16px; padding: 16px 22px; border-bottom: 1px solid var(--line); }
  .pr-stage:last-child { border-bottom: 0; }
  .pr-stage-name { font: 700 12px/1.3 "Plus Jakarta Sans", sans-serif; letter-spacing: .14em; text-transform: uppercase; color: var(--mute-2); padding-top: 14px; }
  .pr-procs { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
  .pr-proc { border: 1px solid var(--line-2); border-radius: 12px; padding: 12px 14px; background: #fff; display: flex; flex-direction: column; gap: 10px; transition: border-color .2s, box-shadow .3s var(--ease), transform .3s var(--ease); }
  .pr-proc:hover { border-color: var(--navy); transform: translateY(-1px); }
  .pr-proc[aria-pressed="true"] { border-color: var(--navy); background: #F5F8FD; box-shadow: 0 0 0 3px rgba(22, 41, 79, .12); }
  .pr-proc-name { font: 700 14px/1.3 "Plus Jakarta Sans", sans-serif; }
  .pr-proc .pr-comp { height: 8px; min-width: 0; }
  .pr-proc-foot { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 13px; color: var(--mute); }

  /* Panel proses */
  .pr-split { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 20px; margin-top: 20px; align-items: start; }
  .pr-split > .pr-card { margin-top: 0; }
  .pr-fade { animation: prFade .35s var(--ease); }
  @keyframes prFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
  .pr-hz { display: block; font-size: 14px; color: var(--mute); margin-top: 2px; max-width: 420px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .pr-hz em { font-style: normal; color: var(--amber); font-weight: 600; }
  .pr-grp td { background: #FAFCFE; padding: 10px 22px; font: 700 13px/1.2 "Plus Jakarta Sans", sans-serif; color: var(--ink); }
  .pr-grp td span { color: var(--mute); font-weight: 600; margin-left: 6px; }
  .pr-grp .pr-dot { width: 9px; height: 9px; border-radius: 3px; display: inline-block; background: var(--c); margin-right: 8px; }
  .pr-loc-k { font: 700 12px/1.3 "Plus Jakarta Sans", sans-serif; letter-spacing: .1em; text-transform: uppercase; color: var(--mute-2); margin: 16px 0 6px; }
  .pr-loc-k:first-child { margin-top: 0; }
  .pr-loc { list-style: none; margin: 0; padding: 0; }
  .pr-loc li { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--line); font-size: 15px; }
  .pr-loc li span:last-child { color: var(--mute); font-size: 14px; white-space: nowrap; }
  .pr-desc { margin: 0 0 16px; font-size: 14px; color: var(--mute); }

  /* Panel detail */
  .pr-scrim { position: fixed; inset: 0; background: rgba(15, 45, 107, .28); opacity: 0; pointer-events: none; transition: opacity .3s var(--ease); z-index: 40; backdrop-filter: blur(2px); }
  .pr-panel { position: fixed; top: 12px; right: 12px; bottom: 12px; width: min(620px, calc(100vw - 24px)); background: var(--sky); border-radius: 18px; z-index: 41; transform: translateX(calc(100% + 24px)); transition: transform .45s var(--ease); display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 45, 107, .25); }
  .is-open .pr-scrim { opacity: 1; pointer-events: auto; }
  .is-open .pr-panel { transform: none; }
  .is-open { overflow: hidden; }
  .pr-panel-head { position: relative; color: #fff; padding: 22px 24px 20px; background: linear-gradient(100deg, var(--navy-2), var(--navy)); }
  .pr-panel-head .pr-kicker { margin-bottom: 10px; }
  .pr-panel-head h2 { margin: 0; font: 800 22px/1.25 "Plus Jakarta Sans", sans-serif; letter-spacing: -.01em; padding-right: 40px; }
  .pr-x { position: absolute; top: 16px; right: 16px; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: rgba(255, 255, 255, .14); color: #fff; transition: background .2s; }
  .pr-x:hover { background: rgba(255, 255, 255, .26); }
  .pr-panel-body { flex: 1; min-height: 0; overflow-y: auto; padding: 18px; display: flex; flex-direction: column; gap: 14px; overscroll-behavior: contain; }
  .pr-panel-body > * { flex: none; }
  .pr-verdict { border-radius: 12px; padding: 14px 16px; background: var(--bg); border: 1px solid color-mix(in srgb, var(--c) 22%, transparent); font-size: 15px; }
  .pr-verdict b { color: var(--c); font-family: "Plus Jakarta Sans", sans-serif; }
  .pr-entry { padding: 12px 18px; border-bottom: 1px solid var(--line); }
  .pr-entry:last-child { border-bottom: 0; }
  .pr-entry-top { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
  .pr-entry-top strong { font: 700 15px/1.35 "Plus Jakarta Sans", sans-serif; }
  .pr-entry p { margin: 4px 0 0; font-size: 14px; color: var(--mute); }
  .pr-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .pr-layer { padding: 12px 18px 4px; }
  .pr-layer h4 { margin: 0 0 6px; font: 700 13px/1.3 "Plus Jakarta Sans", sans-serif; color: var(--green-d); }
  .pr-layer ul { margin: 0; padding-left: 18px; }
  .pr-layer li { font-size: 15px; margin: 4px 0; }
  .pr-layer li small { color: var(--mute); font-size: 13px; }
  .pr-more { display: block; width: 100%; text-align: center; padding: 12px; font: 700 14px/1 "Plus Jakarta Sans", sans-serif; color: var(--navy-2); border-top: 1px solid var(--line); }
  .pr-more:hover { background: #F6FAFE; }
  .pr-empty { padding: 32px 22px; text-align: center; color: var(--mute); }
  .pr-foot { margin: 24px 4px 0; font-size: 13px; color: var(--mute); max-width: 900px; }
  .pr-foot p { margin: 0 0 4px; }

  @media (max-width: 1100px) {
    .pr-split { grid-template-columns: 1fr; }
    .pr-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 760px) {
    .pr-hero-in { padding: 16px 16px 84px; }
    .pr-main { padding: 0 12px 48px; }
    .pr-stage { grid-template-columns: 1fr; gap: 8px; padding: 14px 16px; }
    .pr-stage-name { padding-top: 0; }
    .pr-card-head, .pr-card-body { padding-left: 16px; padding-right: 16px; }
    .pr-tbl th, .pr-tbl td { padding-left: 14px; padding-right: 14px; }
    .pr-tbl .hide-sm { display: none; }
    .pr-search { width: 100%; }
    .pr-kpi b { font-size: 24px; }
  }
  @media (prefers-reduced-motion: reduce) {
    .pr *, .pr *::before, .pr *::after { transition: none !important; animation: none !important; }
  }
</style>
