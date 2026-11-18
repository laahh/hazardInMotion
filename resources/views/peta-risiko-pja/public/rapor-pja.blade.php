<!DOCTYPE html>
<html lang="id">
<head>
   <meta charset="utf-8"/>
   <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
   <title>Rapor PJA — PT Berau Coal</title>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
   <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
   <style>
      /* Gaya mengikuti /form/pengajuan-treatment-banned supaya form publik terasa satu keluarga. */
      :root {
         --brand: #3952bc; --brand-dark: #2b45af; --brand-soft: rgba(57, 82, 188, 0.08);
         --ok: #059669; --ok-soft: #ecfdf5; --part: #d97706; --part-soft: #fffbeb;
         --bm: #dc2626; --bm-soft: #fef2f2; --none: #94a3b8; --none-soft: #f1f5f9;
         --ink: #1e293b; --muted: #64748b; --line: #e2e8f0; --surface: #ffffff;
         --bg: linear-gradient(160deg, #eef2ff 0%, #f8fafc 45%, #f1f5f9 100%);
      }
      * { box-sizing: border-box; }
      body { margin: 0; min-height: 100dvh; font-family: 'Inter', system-ui, sans-serif; color: var(--ink); background: var(--bg); -webkit-font-smoothing: antialiased; }
      .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
      .wrap { max-width: 760px; margin: 0 auto; padding: 1.25rem max(1rem, env(safe-area-inset-left)) 3rem max(1rem, env(safe-area-inset-right)); }
      .hero { border-radius: 20px; overflow: hidden; background: var(--surface); box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 16px 40px -12px rgba(57,82,188,.18); margin-bottom: 1rem; }
      .hero-top { height: 6px; background: linear-gradient(90deg, var(--brand), #72479e); }
      .hero-body { padding: 1.75rem 1.5rem 1.5rem; }
      .badge { display: inline-flex; align-items: center; gap: .35rem; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--brand); background: var(--brand-soft); border-radius: 999px; padding: .35rem .75rem; }
      h1 { margin: .85rem 0 0; font-size: clamp(1.5rem, 4vw, 1.85rem); line-height: 1.2; font-weight: 800; }
      .lead { margin: .65rem 0 0; color: var(--muted); font-size: .95rem; line-height: 1.55; }
      .steps { display: flex; gap: .5rem; margin: 1.25rem 0 1rem; }
      .step-dot { flex: 1; height: 4px; border-radius: 999px; background: #e2e8f0; transition: background .35s ease; }
      .step-dot.is-active { background: var(--brand); }
      .card { background: var(--surface); border: 1px solid var(--line); border-radius: 18px; padding: 1.35rem 1.25rem; box-shadow: 0 8px 24px -16px rgba(15,23,42,.12); margin-bottom: .85rem; }
      .card-title { display: flex; align-items: center; gap: .5rem; font-size: .95rem; font-weight: 700; margin: 0 0 1rem; }
      .card-title small { margin-left: auto; font-size: .75rem; font-weight: 600; color: var(--muted); }
      .num { width: 1.65rem; height: 1.65rem; flex: none; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: var(--brand-soft); color: var(--brand); font-size: .75rem; font-weight: 800; }
      label { display: block; font-size: .82rem; font-weight: 600; margin-bottom: .4rem; color: #334155; }
      .req { color: var(--bm); }
      .hint { font-size: .78rem; color: var(--muted); margin-top: .35rem; line-height: 1.45; }
      .input { width: 100%; border: 1.5px solid var(--line); border-radius: 12px; background: #fafbfc; padding: .85rem 1rem; font: inherit; font-size: .95rem; transition: border-color .2s, box-shadow .2s; }
      .input:focus { outline: none; border-color: rgba(57,82,188,.45); box-shadow: 0 0 0 4px rgba(57,82,188,.12); background: #fff; }
      .input-mono { font-family: ui-monospace, monospace; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
      .row { display: flex; gap: .65rem; align-items: stretch; }
      .row .input { flex: 1; }
      .btn { border: 0; border-radius: 12px; cursor: pointer; font: inherit; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: .4rem; transition: transform .15s ease, opacity .15s ease; }
      .btn:active { transform: scale(.98); }
      .btn:disabled { opacity: .6; cursor: progress; }
      .btn-secondary { background: #f1f5f9; color: #475569; padding: .85rem 1rem; border: 1px solid var(--line); }
      .btn:focus-visible, .input:focus-visible, summary:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }

      .preview-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .65rem; font-size: .86rem; }
      .preview-grid span { display: block; color: var(--muted); font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
      .preview-grid strong { display: block; margin-top: .15rem; font-weight: 600; color: var(--ink); overflow-wrap: anywhere; }
      .pja-pilih { margin-top: 1rem; }

      /* Ringkasan risiko */
      .skor { display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap; }
      .skor-angka { font-size: 2.6rem; font-weight: 800; line-height: 1; letter-spacing: -.02em; }
      .skor-ket { color: var(--muted); font-size: .85rem; line-height: 1.45; padding-bottom: .2rem; }
      .bar { display: flex; height: 14px; border-radius: 999px; overflow: hidden; background: var(--none-soft); margin: 1rem 0 .75rem; }
      .bar i { display: block; height: 100%; transition: width .5s ease; }
      .legenda { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .5rem; }
      .leg { border-radius: 12px; padding: .65rem .75rem; background: var(--c-soft); }
      .leg span { display: flex; align-items: center; gap: .35rem; font-size: .72rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .03em; }
      .leg span::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--c); flex: none; }
      .leg b { display: block; margin-top: .2rem; font-size: 1.25rem; font-weight: 800; font-variant-numeric: tabular-nums; }
      .leg em { font-style: normal; font-size: .75rem; color: var(--muted); }
      .c-ok { --c: var(--ok); --c-soft: var(--ok-soft); } .c-part { --c: var(--part); --c-soft: var(--part-soft); }
      .c-bm { --c: var(--bm); --c-soft: var(--bm-soft); } .c-none { --c: var(--none); --c-soft: var(--none-soft); }
      .sub-h { margin: 1.25rem 0 .55rem; font-size: .78rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
      .tbl-wrap { overflow-x: auto; }
      table { width: 100%; border-collapse: collapse; font-size: .85rem; }
      th { text-align: left; font-size: .7rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; padding: .5rem .5rem; border-bottom: 1px solid var(--line); white-space: nowrap; }
      td { padding: .55rem .5rem; border-bottom: 1px solid #f1f5f9; font-variant-numeric: tabular-nums; }
      td.r, th.r { text-align: right; }
      .lv { display: inline-block; padding: .1rem .5rem; border-radius: 999px; font-size: .72rem; font-weight: 700; color: #fff; background: var(--lv); white-space: nowrap; }
      .lv-Significant { --lv: #b3261e; } .lv-High { --lv: #d0640c; } .lv-Medium { --lv: #c28f12; } .lv-Low { --lv: #2f7d4f; }
      .tren { display: flex; align-items: flex-end; gap: .4rem; height: 92px; }
      .tren div { flex: 1; display: flex; flex-direction: column; align-items: center; gap: .25rem; height: 100%; min-width: 0; }
      .tren .tb { flex: 1; width: 100%; display: flex; align-items: flex-end; background: var(--none-soft); border-radius: 6px; overflow: hidden; }
      .tren .tb i { display: block; width: 100%; background: var(--ok); border-radius: 6px 6px 0 0; }
      .tren .akhir .tb i { background: var(--brand); }
      .tren span { font-size: .68rem; color: var(--muted); font-weight: 600; white-space: nowrap; }
      .tren b { font-size: .7rem; font-weight: 700; }

      /* Personal */
      .spark { display: flex; align-items: flex-end; gap: 2px; height: 34px; }
      .spark i { flex: 1; background: var(--brand); border-radius: 2px 2px 0 0; min-height: 2px; opacity: .85; }
      .spark i.z { background: #e2e8f0; }
      .chip { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 999px; font-size: .74rem; font-weight: 700; }
      .chip-ok { background: var(--ok-soft); color: #047857; } .chip-bm { background: var(--bm-soft); color: #b91c1c; }
      .chip-muted { background: var(--none-soft); color: #475569; } .chip-brand { background: var(--brand-soft); color: var(--brand); }

      /* Temuan & belum ditemukan */
      .list { display: grid; gap: .6rem; }
      .item { border: 1px solid var(--line); border-radius: 14px; padding: .8rem .9rem; display: grid; gap: .3rem; }
      .item-top { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; font-size: .75rem; color: var(--muted); font-weight: 600; }
      .item-judul { font-weight: 700; font-size: .9rem; line-height: 1.35; }
      .item-sub { font-size: .82rem; color: #475569; line-height: 1.45; }
      details.akt { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
      details.akt + details.akt { margin-top: .5rem; }
      details.akt summary { list-style: none; cursor: pointer; padding: .75rem .9rem; display: flex; gap: .5rem; align-items: center; font-weight: 700; font-size: .88rem; background: #fafbfc; }
      details.akt summary::-webkit-details-marker { display: none; }
      details.akt summary::after { content: "expand_more"; font-family: 'Material Symbols Outlined'; margin-left: auto; color: var(--muted); transition: transform .25s ease; }
      details.akt[open] summary::after { transform: rotate(180deg); }
      details.akt summary em { font-style: normal; font-weight: 700; font-size: .72rem; color: var(--muted); background: #fff; border: 1px solid var(--line); border-radius: 999px; padding: .1rem .5rem; flex: none; }
      details.akt ul { margin: 0; padding: .35rem .9rem .75rem 2rem; display: grid; gap: .45rem; font-size: .84rem; line-height: 1.45; }
      .kosong { padding: 1rem; border-radius: 12px; background: var(--ok-soft); color: #047857; font-size: .86rem; font-weight: 600; }
      .alert { border-radius: 12px; padding: .85rem 1rem; font-size: .86rem; line-height: 1.45; }
      .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
      .msg { margin-top: .5rem; font-size: .8rem; }
      .msg.err { color: var(--bm); } .msg.ok { color: var(--ok); } .msg.wait { color: var(--muted); }
      .more { margin-top: .6rem; font-size: .78rem; color: var(--muted); }
      .footer-note { text-align: center; color: var(--muted); font-size: .78rem; margin-top: 1.25rem; line-height: 1.5; }
      #hasil { display: none; }
      #hasil.is-visible { display: block; animation: naik .4s ease both; }
      @keyframes naik { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
      @media (prefers-reduced-motion: reduce) { #hasil.is-visible { animation: none; } .bar i, .step-dot { transition: none; } }
      @media (max-width: 560px) {
         .preview-grid { grid-template-columns: 1fr; }
         .row { flex-direction: column; }
         .legenda { grid-template-columns: repeat(2, minmax(0, 1fr)); }
         .hero-body { padding: 1.4rem 1.15rem 1.2rem; }
      }
   </style>
</head>
<body>
<div class="wrap">
   <div class="hero">
      <div class="hero-top"></div>
      <div class="hero-body">
         <span class="badge"><span class="material-symbols-outlined" style="font-size:15px">lock_open</span> Tanpa login</span>
         <h1>Rapor PJA</h1>
         <p class="lead">Masukkan SID Anda untuk melihat performa PJA tempat Anda ditugaskan: berapa bahaya-risiko yang sudah terkontrol, temuan SAP yang masih open, dan bahaya-risiko yang belum ditemukan lewat SAP.</p>
      </div>
   </div>

   <div class="steps" aria-hidden="true">
      <div class="step-dot is-active" id="dot-1"></div>
      <div class="step-dot" id="dot-2"></div>
      <div class="step-dot" id="dot-3"></div>
   </div>

   <section class="card">
      <h2 class="card-title"><span class="num">1</span> Cari SID Anda</h2>
      <form id="form-cari" novalidate>
         <label for="sid">Nomor SID <span class="req">*</span></label>
         <div class="row">
            <input class="input input-mono" type="text" id="sid" name="sid" value="{{ $prefillSid }}" placeholder="Contoh: U8WAP" required maxlength="20" autocomplete="off" autocapitalize="characters"/>
            <button type="submit" class="btn btn-secondary" id="btn-cari" style="white-space:nowrap"><span class="material-symbols-outlined">search</span> Cari</button>
         </div>
         <p class="hint">Ketik SID persis seperti di kartu identitas / SAP, lalu tekan <strong>Cari</strong>.</p>
         <p id="msg" class="msg" role="status" aria-live="polite"></p>
      </form>
      <div id="identitas" style="display:none;margin-top:1rem">
         <div class="preview-grid">
            <div><span>Nama</span><strong id="pv-nama">—</strong></div>
            <div><span>Perusahaan</span><strong id="pv-perusahaan">—</strong></div>
            <div><span>PJA</span><strong id="pv-pja">—</strong></div>
            <div><span>Data sampai</span><strong id="pv-sampai">—</strong></div>
         </div>
         <div class="pja-pilih" id="pja-pilih-wrap" style="display:none">
            <label for="pja-pilih">Anda terdaftar di beberapa PJA, pilih salah satu</label>
            <select class="input" id="pja-pilih"></select>
         </div>
      </div>
   </section>

   <div id="hasil" aria-live="polite">
      <section class="card">
         <h2 class="card-title"><span class="num">2</span> Performa PJA <small id="periode"></small></h2>
         <div class="skor">
            <div class="skor-angka" id="skor">–</div>
            <div class="skor-ket" id="skor-ket"></div>
         </div>
         <div class="bar" id="bar" role="img"></div>
         <div class="legenda" id="legenda"></div>

         <p class="sub-h">Tren terkontrol penuh · Significant + High</p>
         <div class="tren" id="tren"></div>

         <p class="sub-h">Rincian per tingkat risiko awal</p>
         <div class="tbl-wrap"><table>
            <thead><tr><th>Risiko awal</th><th class="r">Baris</th><th class="r">Terkontrol penuh</th><th class="r">Sebagian</th><th class="r">Bermasalah</th><th class="r">Belum ditemukan</th></tr></thead>
            <tbody id="tbl-level"></tbody>
         </table></div>

         <p class="sub-h">Aktivitas SAP Anda</p>
         <div class="preview-grid" id="personal"></div>
      </section>

      <section class="card">
         <h2 class="card-title"><span class="num">3</span> Temuan SAP open di area PJA <small id="temuan-n"></small></h2>
         <div class="list" id="temuan"></div>
         <p class="more" id="temuan-more"></p>
      </section>

      <section class="card">
         <h2 class="card-title"><span class="num">4</span> Bahaya-risiko yang belum ditemukan <small id="belum-n"></small></h2>
         <div class="alert alert-info" style="margin-bottom:.85rem">
            <span class="material-symbols-outlined" style="font-size:18px;vertical-align:-4px">info</span>
            Bahaya-risiko Significant/High di baseline HIRA area PJA ini yang <strong>tidak tertangkap laporan SAP</strong> sama sekali pada minggu ini. Prioritaskan pengawasan dan pelaporan di sini.
         </div>
         <div id="belum"></div>
         <p class="more" id="belum-more"></p>

         <p class="sub-h" id="gap-h">Sudah ditemukan tetapi masih bermasalah</p>
         <div id="gap"></div>
      </section>
   </div>

   <p class="footer-note">PT Berau Coal · Safety App<br/>Data dari snapshot Peta Risiko PJA (DRM 2.1); hanya ditampilkan, tidak disimpan.</p>
</div>

<script>
(function () {
   var URL_CARI = @json(route('peta-risiko-pja.rapor-publik.cari'));
   var $ = function (id) { return document.getElementById(id); };
   var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
   var fmt = function (n) { return Number(n || 0).toLocaleString('id-ID'); };
   var pct = function (a, b) { return b ? (100 * a / b).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%' : '–'; };
   var tgl = function (s) { return s ? new Date(s + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '–'; };
   var tglPendek = function (s) { return new Date(s + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }); };
   var geser = function (s, n) { var d = new Date(s + 'T00:00:00'); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); };
   var lv = function (l) { return '<span class="lv lv-' + esc(l) + '">' + esc(l) + '</span>'; };
   var sidAktif = '';

   function pesan(teks, jenis) { var m = $('msg'); m.textContent = teks; m.className = 'msg ' + (jenis || ''); }
   function langkah(n) { [1, 2, 3].forEach(function (i) { $('dot-' + i).classList.toggle('is-active', i <= n); }); }

   function cari(pja) {
      var sid = $('sid').value.trim().toUpperCase();
      if (!sid) { pesan('Masukkan SID terlebih dahulu.', 'err'); $('sid').focus(); return; }
      pesan('Mencari data…', 'wait');
      $('btn-cari').disabled = true;
      var q = { sid: sid };
      if (pja) q.pja = pja;
      fetch(URL_CARI + '?' + new URLSearchParams(q), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
         .then(function (r) {
            if (r.status === 429) throw new Error('Terlalu banyak pencarian. Tunggu satu menit lalu coba lagi.');
            return r.json();
         })
         .then(function (p) {
            $('btn-cari').disabled = false;
            if (!p.found) {
               pesan(p.message || 'SID tidak ditemukan.', 'err');
               $('identitas').style.display = 'none';
               $('hasil').classList.remove('is-visible');
               langkah(1);
               return;
            }
            sidAktif = sid;
            pesan('Data ditemukan ✓', 'ok');
            tampil(p);
         })
         .catch(function (e) {
            $('btn-cari').disabled = false;
            pesan(e && e.message && e.message.indexOf('Terlalu') === 0 ? e.message : 'Koneksi gagal. Periksa internet lalu coba lagi.', 'err');
         });
   }

   function tampil(p) {
      var r = p.rapor, o = r.orang;
      $('pv-nama').textContent = o.nama || p.orang.nama || '—';
      $('pv-perusahaan').textContent = o.perusahaan || p.orang.perusahaan || '—';
      $('pv-pja').textContent = r.pja.nama + (r.pja.site ? ' · ' + r.pja.site : '');
      $('pv-sampai').textContent = tgl(r.periode.sampai);
      var sel = $('pja-pilih');
      if (p.daftarPja.length > 1) {
         sel.innerHTML = p.daftarPja.map(function (x) { return '<option value="' + esc(x.id) + '"' + (String(x.id) === String(r.pja.id) ? ' selected' : '') + '>' + esc(x.nama + (x.site ? ' · ' + x.site : '')) + '</option>'; }).join('');
         $('pja-pilih-wrap').style.display = '';
      } else {
         $('pja-pilih-wrap').style.display = 'none';
      }
      $('identitas').style.display = '';
      performa(r);
      temuan(r.temuan);
      belum(r);
      $('hasil').classList.add('is-visible');
      langkah(3);
   }

   function performa(r) {
      var u = r.ringkas.utama, bm = u.gaponly;
      var awal = r.periode.minggu;
      $('periode').textContent = 'Minggu ' + tglPendek(awal) + ' – ' + tglPendek(geser(awal, 6)) + (r.periode.berjalan ? ' (berjalan)' : '');
      $('skor').textContent = pct(u.full, u.tot);
      $('skor').style.color = !u.tot ? 'var(--muted)' : u.full / u.tot >= 0.6 ? 'var(--ok)' : u.full / u.tot >= 0.3 ? 'var(--part)' : 'var(--bm)';
      $('skor-ket').innerHTML = 'bahaya-risiko <b>Significant + High</b> terkontrol penuh menurut SAP<br>'
         + fmt(u.full) + ' dari ' + fmt(u.tot) + ' baris · ' + fmt(r.pja.dl) + ' detail lokasi · ' + fmt(r.pja.personil) + ' personil';
      var bagian = [['ok', u.full, 'Terkontrol penuh', 'semua detail lokasi'], ['part', u.part, 'Sebagian', 'sebagian lokasi'], ['bm', bm, 'Bermasalah', 'belum closed / berulang'], ['none', u.none, 'Belum ditemukan', 'tanpa laporan SAP']];
      $('bar').innerHTML = bagian.map(function (b) { return '<i style="width:' + (u.tot ? (100 * b[1] / u.tot).toFixed(2) : 0) + '%;background:var(--' + b[0] + ')"></i>'; }).join('');
      $('bar').setAttribute('aria-label', bagian.map(function (b) { return b[2] + ' ' + pct(b[1], u.tot); }).join(', '));
      $('legenda').innerHTML = bagian.map(function (b) { return '<div class="leg c-' + b[0] + '"><span>' + b[2] + '</span><b>' + fmt(b[1]) + '</b><em>' + pct(b[1], u.tot) + ' · ' + b[3] + '</em></div>'; }).join('');

      var maks = r.tren.length - 1;
      $('tren').innerHTML = r.tren.map(function (t, i) {
         var x = t.tot ? t.full / t.tot : 0;
         return '<div class="' + (i === maks ? 'akhir' : '') + '" title="Minggu ' + tglPendek(t.minggu) + ': ' + fmt(t.full) + ' dari ' + fmt(t.tot) + ' terkontrol penuh"><b>' + Math.round(100 * x) + '%</b><div class="tb"><i style="height:' + (100 * x).toFixed(1) + '%"></i></div><span>' + tglPendek(t.minggu) + '</span></div>';
      }).join('');

      $('tbl-level').innerHTML = ['Significant', 'High', 'Medium', 'Low'].map(function (l) {
         var x = r.ringkas[l];
         if (!x || !x.tot) return '';
         return '<tr><td>' + lv(l) + '</td><td class="r">' + fmt(x.tot) + '</td><td class="r"><b>' + fmt(x.full) + '</b> <span style="color:var(--muted)">' + pct(x.full, x.tot) + '</span></td><td class="r">' + fmt(x.part) + '</td><td class="r">' + fmt(x.gaponly) + '</td><td class="r">' + fmt(x.none) + '</td></tr>';
      }).join('') || '<tr><td colspan="6" style="color:var(--muted)">Belum ada baris baseline untuk PJA ini.</td></tr>';

      var o = r.orang, mx = Math.max.apply(null, [1].concat(o.harian));
      var bearc = !o.bearc ? '<span class="chip chip-muted">Tidak ada data</span>'
         : o.bearc.banned ? '<span class="chip chip-bm">Banned SAP</span>'
         : '<span class="chip chip-ok">' + (o.bearc.persen == null ? 'Achieve dihitung' : Math.round(o.bearc.persen) + '% · achieve') + '</span>';
      $('personal').innerHTML = '<div><span>Laporan SAP 14 hari</span><strong>' + fmt(o.totalSap) + ' laporan</strong>'
         + '<div class="spark" style="margin-top:.4rem" title="' + esc(o.harian.join(', ')) + '">' + o.harian.map(function (n) { return n ? '<i style="height:' + (100 * n / mx).toFixed(0) + '%"></i>' : '<i class="z"></i>'; }).join('') + '</div></div>'
         + '<div><span>Status BeARC minggu lalu</span><strong style="margin-top:.35rem">' + bearc + '</strong>'
         + (o.kategori ? '<span style="margin-top:.65rem">Peran di PJA</span><strong>' + esc(o.kategori) + '</strong>' : '') + '</div>';
   }

   function temuan(t) {
      $('temuan-n').textContent = fmt(t.total) + ' open';
      $('temuan').innerHTML = t.daftar.length ? t.daftar.map(function (x) {
         return '<div class="item"><div class="item-top"><span class="chip chip-muted">' + esc(x.jenis) + '</span><span>' + tgl(x.tanggal) + '</span>'
            + (x.milikSaya ? '<span class="chip chip-brand">Laporan Anda</span>' : '')
            + (x.janji ? (x.lewat ? '<span class="chip chip-bm">Lewat janji ' + tglPendek(x.janji) + '</span>' : '<span>janji ' + tglPendek(x.janji) + '</span>') : '')
            + '<span style="margin-left:auto">' + esc(x.status) + '</span></div>'
            + '<div class="item-judul">' + esc(x.ket) + (x.subket ? ' · ' + esc(x.subket) : '') + '</div>'
            + (x.deskripsi ? '<div class="item-sub">' + esc(x.deskripsi) + '</div>' : '')
            + '<div class="item-top"><span class="material-symbols-outlined" style="font-size:16px">location_on</span>' + esc(x.lokasi || '–') + '</div></div>';
      }).join('') : '<div class="kosong">Tidak ada temuan Hazard/Inspeksi yang masih open di area PJA ini.</div>';
      $('temuan-more').textContent = t.total > t.daftar.length ? 'Menampilkan ' + t.daftar.length + ' temuan terbaru dari ' + fmt(t.total) + '.' : '';
   }

   function daftarAkt(data, kosong, masalah) {
      if (!data.total) return '<div class="kosong">' + kosong + '</div>';
      return data.aktivitas.map(function (a, i) {
         return '<details class="akt"' + (i === 0 ? ' open' : '') + '><summary><span>' + esc(a.nama || 'Aktivitas tanpa nama') + '</span><em>' + a.baris.length + ' bahaya</em></summary><ul>'
            + a.baris.map(function (b) {
               return '<li>' + lv(b.level) + ' ' + esc(b.bahaya) + (b.sub && b.sub !== a.nama ? ' <span style="color:var(--muted)">· ' + esc(b.sub) + '</span>' : '')
                  + (b.insiden ? ' <span class="chip chip-bm">pernah insiden</span>' : '')
                  + (masalah && b.masalah.length ? ' <span class="chip chip-muted">' + esc(b.masalah.join(', ')) + '</span>' : '') + '</li>';
            }).join('') + '</ul></details>';
      }).join('');
   }

   function belum(r) {
      var tampilBelum = r.belum.aktivitas.reduce(function (s, a) { return s + a.baris.length; }, 0);
      $('belum-n').textContent = fmt(r.belum.total) + ' baris';
      $('belum').innerHTML = daftarAkt(r.belum, 'Semua bahaya-risiko Significant/High sudah tertangkap laporan SAP minggu ini.', false);
      $('belum-more').textContent = r.belum.total > tampilBelum ? 'Menampilkan ' + tampilBelum + ' dari ' + fmt(r.belum.total) + ' baris (prioritas: Significant dan yang pernah insiden).' : '';
      $('gap-h').style.display = r.gap.total ? '' : 'none';
      $('gap').innerHTML = r.gap.total ? daftarAkt(r.gap, '', true) : '';
   }

   $('form-cari').addEventListener('submit', function (e) { e.preventDefault(); cari(); });
   $('pja-pilih').addEventListener('change', function (e) { if (sidAktif) cari(e.target.value); });
   if ($('sid').value.trim()) cari();
})();
</script>
</body>
</html>
