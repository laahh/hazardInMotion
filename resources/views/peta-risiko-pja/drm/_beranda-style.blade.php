{{-- Gaya Beranda PJA, dipakai halaman Beranda PJA dan tab Per PJA. Akar elemen wajib berkelas .br-root. --}}
@push('styles')
<style>
  /* Beranda PJA: tata letak mengikuti prototipe, gaya memakai token halaman (--navy, --green, --line, --shadow, --ease). */
  .br-root { --acc: var(--navy); }
  .br-root [id] { scroll-margin-top: 92px; }
  .br-root > * + * { margin-top: 20px; }
  .br-root summary { list-style: none; }
  .br-root summary::-webkit-details-marker { display: none; }
  .br-root summary.pr-card-head::after { content: "▸"; display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 8px; background: var(--sky-2); color: var(--navy); font-size: 15px; transition: transform .3s var(--ease); }
  #br-perh { margin-top: 24px; }
  .br-root details[open] > summary.pr-card-head::after { transform: rotate(90deg); }

  /* Bilah filter: menempel di atas saat menggulir */
  .br-bar-wrap { position: sticky; top: 10px; z-index: 30; }
  .br-bar { display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: center; padding: 12px 18px; background: rgba(255, 255, 255, .92); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 6px 20px rgba(20, 30, 60, .08); }
  .br-bar .lbl { font: 700 12px/1 "Plus Jakarta Sans", sans-serif; letter-spacing: .06em; text-transform: uppercase; color: var(--mute-2); }
  .br-bar select { height: 36px; border: 1px solid var(--line-2); border-radius: 10px; padding: 0 10px; font: inherit; font-size: 14px; background: #fff; max-width: 330px; transition: border-color .2s; }
  .br-bar select:hover { border-color: var(--navy); }
  .br-nav { display: inline-flex; border: 1px solid var(--line-2); border-radius: 10px; overflow: hidden; background: #fff; }
  .br-nav button { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; color: var(--navy); transition: background .2s; }
  .br-nav button + button { border-left: 1px solid var(--line-2); }
  .br-nav button:hover:not(:disabled) { background: var(--sky-2); }
  .br-nav button:disabled { color: var(--mute-2); cursor: default; }
  .br-sep { width: 1px; height: 26px; background: var(--line-2); }
  .br-lv { display: inline-flex; gap: 6px; align-items: center; padding: 7px 12px; border: 1px solid var(--line-2); border-radius: 999px; font: 700 13px/1 "Plus Jakarta Sans", sans-serif; color: var(--mute); background: #fff; transition: background .25s var(--ease), color .25s, border-color .25s; }
  .br-lv i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; background: var(--c); }
  .br-lv:hover { border-color: var(--c); }
  .br-lv[aria-pressed="true"] { background: color-mix(in srgb, var(--c) 12%, #fff); border-color: var(--c); color: var(--ink); }
  .br-sum { margin-left: auto; display: inline-flex; gap: 12px; align-items: center; font-size: 13px; color: var(--mute); }
  .br-sum b { font: 800 15px/1 "Plus Jakarta Sans", sans-serif; color: var(--c, var(--ink)); }

  /* Judul bagian */
  .br-h { display: flex; align-items: center; gap: 8px; margin: 22px 0 10px; font: 700 12px/1.2 "Plus Jakarta Sans", sans-serif; letter-spacing: .07em; text-transform: uppercase; color: var(--mute); }
  .br-h::before { content: ""; flex: 0 0 auto; width: 3px; height: 14px; border-radius: 2px; background: var(--acc); }
  .br-h:first-child { margin-top: 0; }

  /* KPI */
  .br-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
  .br-kpi { position: relative; overflow: hidden; background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px 14px 20px; box-shadow: var(--shadow); transition: transform .3s var(--ease), box-shadow .3s var(--ease); }
  .br-kpi::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: var(--ac, var(--mute-2)); }
  .br-kpi:hover { transform: translateY(-2px); box-shadow: 0 2px 4px rgba(20, 26, 34, .05), 0 14px 32px rgba(20, 26, 34, .10); }
  .br-kpi small { display: block; font: 700 12px/1.3 "Plus Jakarta Sans", sans-serif; letter-spacing: .04em; text-transform: uppercase; color: var(--mute); }
  .br-kpi b { display: block; margin: 6px 0 4px; font: 800 32px/1 "Plus Jakarta Sans", sans-serif; letter-spacing: -.02em; color: var(--kc, var(--navy)); }
  .br-kpi em { font-style: normal; font-size: 13px; line-height: 1.4; color: var(--mute); }

  /* Kartu perlu aksi */
  .br-act { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
  .br-ac { background: #fff; border: 1px solid var(--line); border-top: 4px solid var(--ac); border-radius: 14px; padding: 12px 16px 14px; box-shadow: var(--shadow); transition: transform .3s var(--ease), box-shadow .3s var(--ease); }
  .br-ac:hover { transform: translateY(-2px); box-shadow: 0 2px 4px rgba(20, 26, 34, .05), 0 14px 32px rgba(20, 26, 34, .10); }
  .br-ac small { display: block; font: 700 13px/1.3 "Plus Jakarta Sans", sans-serif; color: var(--mute); }
  .br-ac .big { display: block; margin: 4px 0 2px; font: 800 28px/1.15 "Plus Jakarta Sans", sans-serif; color: var(--ink); }
  .br-ac .big span { font-size: 14px; font-weight: 600; color: var(--mute); }
  .br-ac a { display: inline-flex; gap: 4px; margin-top: 8px; font: 700 13px/1 "Plus Jakarta Sans", sans-serif; color: var(--navy-2); cursor: pointer; }
  .br-ac a:hover { text-decoration: underline; }

  /* Bar komposisi */
  .br-sb { display: flex; height: 10px; border-radius: 5px; overflow: hidden; background: var(--track); min-width: 110px; }
  .br-sb i { display: block; height: 100%; }
  .br-sb i + i { box-shadow: inset 1px 0 0 rgba(255, 255, 255, .7); }
  .br-sb.big { height: 16px; border-radius: 8px; }

  /* Ringkasan */
  .br-rhead { padding: 16px 22px 14px; border-bottom: 1px solid var(--line); background: linear-gradient(180deg, var(--sky), #fff); }
  .br-rhead b.t { display: block; font: 800 17px/1.2 "Plus Jakarta Sans", sans-serif; color: var(--ink); }
  .br-rhead small { display: block; margin-top: 3px; font-size: 14px; color: var(--mute); }
  .br-rhead .br-sb { margin: 12px 0 10px; }
  .br-chips { display: flex; flex-wrap: wrap; gap: 8px; }
  .br-chips > span { display: inline-flex; align-items: center; gap: 6px; padding: 4px 11px; border-radius: 999px; background: #fff; border: 1px solid var(--line-2); font-size: 13px; }
  .br-chips i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
  .br-two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
  .br-tis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 14px; }
  .br-ti { display: flex; gap: 10px; align-items: flex-start; border: 1px solid var(--line); border-left: 4px solid var(--c); border-radius: 12px; padding: 10px 12px; font-size: 14px; line-height: 1.5; background: #fff; transition: box-shadow .25s var(--ease); }
  .br-ti:hover { box-shadow: 0 6px 18px rgba(20, 26, 34, .08); }
  .br-tn { flex: 0 0 auto; width: 22px; height: 22px; border-radius: 50%; background: var(--c); color: #fff; font: 800 12px/22px "Plus Jakarta Sans", sans-serif; text-align: center; margin-top: 1px; }

  /* Peta panas */
  .br-hm { border-collapse: separate; border-spacing: 3px; font-size: 13px; }
  .br-hm th { font: 700 11px/1.2 "Plus Jakarta Sans", sans-serif; color: var(--mute); padding: 2px 4px; text-align: center; white-space: nowrap; }
  .br-hm td.c { min-width: 42px; height: 28px; text-align: center; border-radius: 6px; cursor: pointer; font: 700 12px/1 "Plus Jakarta Sans", sans-serif; font-variant-numeric: tabular-nums; transition: transform .2s var(--ease), filter .2s; }
  .br-hm td.c:hover { transform: scale(1.08); filter: brightness(.95); }
  .br-hm td.c.sel { outline: 2px solid var(--navy); outline-offset: 1px; }
  .br-hlg { display: inline-block; width: 120px; height: 8px; border-radius: 4px; vertical-align: middle; margin: 0 6px; background: linear-gradient(90deg, hsl(8, 55%, 86%), hsl(68, 55%, 73%), hsl(128, 55%, 60%)); }

  /* Bar harian, tren, proyeksi */
  .br-mb { display: flex; align-items: flex-end; gap: 1px; height: 34px; min-width: 210px; }
  .br-mc { flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; position: relative; }
  .br-mc i { display: block; width: 100%; }
  .br-mc b { position: absolute; top: 0; left: 0; right: 0; height: 4px; border-radius: 2px; background: #E8112D; box-shadow: 0 0 6px 1px #E8112D; }
  .br-wb { display: flex; align-items: flex-end; gap: 8px; height: 150px; padding-top: 6px; }
  .br-wc { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; cursor: pointer; height: 100%; }
  .br-wc .stk { flex: 1; width: 100%; display: flex; flex-direction: column; justify-content: flex-end; border-radius: 6px; overflow: hidden; background: var(--track); transition: transform .25s var(--ease), box-shadow .25s; }
  .br-wc:hover .stk { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(20, 26, 34, .12); }
  .br-wc.sel .stk { outline: 2px solid var(--navy); outline-offset: 2px; }
  .br-wc span { font: 600 11px/1 "Plus Jakarta Sans", sans-serif; color: var(--mute); white-space: nowrap; }
  .br-wc.sel span { color: var(--navy); }
  .br-wc .stk i { display: flex; align-items: center; justify-content: center; min-height: 0; overflow: hidden; }
  .br-wc .stk em { font: 700 10.5px/1 "Plus Jakarta Sans", sans-serif; font-style: normal; }
  .br-wc span.br-wv { font-weight: 700; color: var(--ink, #2c2f31); }
  .br-pj { display: flex; align-items: flex-end; gap: 8px; cursor: help; }
  .br-pj b { font: 800 13px/1 "Plus Jakarta Sans", sans-serif; color: var(--ink); }
  .br-pb { display: flex; align-items: flex-end; gap: 2px; height: 36px; }
  .br-pb div { width: 8px; height: 100%; display: flex; align-items: flex-end; }
  .br-pb i { display: block; width: 100%; border-radius: 2px 2px 0 0; }

  /* Tabel hierarki */
  .br-pill { display: inline-block; white-space: nowrap; padding: 2px 9px; border-radius: 999px; color: #fff; font: 700 12px/1.4 "Plus Jakarta Sans", sans-serif; }
  .br-a > td, .br-g > td { transition: background .2s; }
  .br-a:hover > td, .br-g:hover > td { background: var(--sky); }
  .br-a.open > td, .br-g.open > td { background: var(--sky-2); }
  .br-sub > td { background: #F8FAFC; }
  .br-cr { display: inline-flex; width: 20px; height: 20px; align-items: center; justify-content: center; border-radius: 6px; margin-right: 4px; font-size: 12px; color: var(--navy); transition: transform .25s var(--ease), background .2s; }
  .br-cr:hover { background: var(--sky-2); }
  .open .br-cr { transform: rotate(90deg); }
  .br-red { color: var(--red); font-weight: 700; }
  .br-ol { margin: 6px 0 0 18px; padding: 0; } .br-ol li { margin: 6px 0; }

  /* Gerak halus: muncul bertahap saat pertama dimuat, baris yang dibuka meluncur masuk */
  @keyframes br-naik { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
  .br-awal > * { animation: br-naik .5s var(--ease) both; }
  .br-awal > *:nth-child(2) { animation-delay: .04s; } .br-awal > *:nth-child(3) { animation-delay: .08s; }
  .br-awal > *:nth-child(4) { animation-delay: .12s; } .br-awal > *:nth-child(n+5) { animation-delay: .16s; }
  .br-buka > td > * { animation: br-naik .35s var(--ease) both; }
  @media (prefers-reduced-motion: reduce) {
    .br-awal > *, .br-buka > td > * { animation: none; }
    .br-kpi, .br-ac, .br-hm td.c, .br-wc .stk, .br-cr { transition: none; }
  }

  @media (max-width: 1100px) { .br-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 900px) { .br-act, .br-tis, .br-two { grid-template-columns: 1fr; } .br-sum { margin-left: 0; } }
  @media (max-width: 560px) { .br-kpis { grid-template-columns: 1fr; } .br-bar-wrap { position: static; } }
</style>
@endpush
