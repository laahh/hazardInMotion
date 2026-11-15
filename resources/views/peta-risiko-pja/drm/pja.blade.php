@extends('peta-risiko-pja._layout')

@section('title', $ringkas['nama'].' · Peta Risiko PJA')

@section('hero-aksi')
  <a class="pr-back" href="{{ route('peta-risiko-pja.index', ['tab' => 'pja', 'site' => $ringkas['site']]) }}">← Semua PJA</a>
@endsection

@section('hero')
  <p class="pr-kicker">PJA · {{ $ringkas['site'] }}</p>
  <h1 class="pr-title">{{ $ringkas['nama'] }}</h1>
  <p class="pr-sub" id="dr-sub">Data sampai {{ \Carbon\Carbon::parse($meta['sampai'])->translatedFormat('d M Y') }} · sinyal SAP {{ $meta['jendela'] }} hari</p>
@endsection

@section('content')
  <div class="pr-card">
    <div class="dr-kpis" id="dr-kpis"></div>
    <div class="pr-card-body dr-two" id="dr-matriks" style="padding-top:0"></div>
  </div>

  <div class="dr-grid">
    <div class="pr-card">
      <div class="pr-card-head"><div><b>Profil baseline PJA</b><small>Lokasi → aktivitas → bahaya-risiko dari HIRA</small></div></div>
      <div id="dr-profil"><div class="pr-empty">Memuat…</div></div>
    </div>
    <div class="pr-card">
      <div class="pr-card-head"><div><b>Pengawasan personil PJA</b><small>Pemenuhan SAP harian (14 hari) dan mingguan (BeARC)</small></div></div>
      <div id="dr-personil"><div class="pr-empty">Memuat…</div></div>
    </div>
  </div>

  <div class="pr-card">
    <div class="pr-card-head"><div><b>Proses bisnis di area PJA</b><small>11 proses inti + 6 pendukung; satu bahaya-risiko masuk satu proses (kata kunci aktivitas HIRA)</small></div></div>
    <div class="dr-scroll"><table class="pr-tbl dr-tbl" id="dr-proses"></table></div>
  </div>

  <div class="pr-card">
    <div class="pr-card-head"><div><b><span class="dr-sec-num">7</span>Identifikasi risiko harian per aktivitas – detail lokasi</b>
      <small>Satu kotak = satu hari. Klik baris untuk rincian bahaya-risiko, temuan, dan insiden.</small></div>
      <div class="pr-filters"><label class="dr-mute"><input type="checkbox" id="dr-hanya-gap" checked> Hanya baris yang ada gap / laporan</label></div>
    </div>
    <div class="dr-legend">
      <span><i style="--c:#12457A"></i>Terkontrol (Observasi/OAK sesuai)</span>
      <span><i style="--c:#8FC1E8"></i>Ada temuan, sudah closed</span>
      <span><i style="--c:var(--g)"></i>Gap: temuan masih open / OAK stop pekerjaan</span>
      <span><i style="--c:#DCE2E9"></i>Tidak ada laporan</span>
      <span><i style="--c:var(--inc)"></i>Insiden (tanda di atas kotak)</span>
    </div>
    <div class="pr-card-body dr-scroll" id="dr-strip"><div class="pr-empty">Memuat…</div></div>
  </div>

  <div class="pr-card">
    <div class="pr-card-head"><div><b><span class="dr-sec-num">8</span>Ringkasan open risk</b><small>Pasangan detail lokasi × bahaya-risiko</small></div></div>
    <div class="dr-kpis" id="dr-open-kpi"></div>
  </div>

  <div class="pr-card">
    <div class="pr-card-head"><div><b><span class="dr-sec-num">9</span>Open risk per aktivitas & detail lokasi</b>
      <small>Diurutkan dari gap terbanyak. "Hari sejak terkontrol" = indikator awal: makin panjang, makin lama area itu tanpa bukti kontrol.</small></div>
      <div class="pr-filters">
        <select class="pr-search" id="dr-f-proses" aria-label="Filter proses bisnis" style="width:auto"><option value="">Semua proses bisnis</option></select>
        <input type="search" class="pr-search" id="dr-cari" placeholder="Cari aktivitas / detail lokasi" aria-label="Cari aktivitas atau detail lokasi">
      </div>
    </div>
    <div class="dr-scroll"><table class="pr-tbl dr-tbl" id="dr-open"></table></div>
  </div>

  <div class="pr-card">
    <div class="pr-card-head"><div><b>Detail temuan yang masih menjadi gap</b><small id="dr-temuan-sub">Hazard/Inspeksi open di detail lokasi PJA</small></div></div>
    <div class="dr-scroll"><table class="pr-tbl dr-tbl" id="dr-temuan"></table></div>
  </div>

  <div class="pr-foot">
    <p>Hazard/Inspeksi tidak memuat aktivitas: temuannya dikenakan ke semua aktivitas di detail lokasi yang bahayanya cocok. </p>
    <p>Status aktivitas diubah lewat tombol "Flag" di panel aktivitas: pengajuan berlaku setelah disetujui orang lain. Aktivitas NONAKTIF yang disetujui tidak dihitung sebagai open risk, kecuali masih ada temuan open (gap tetap menang).</p>
  </div>

  <div class="pr-scrim" id="dr-scrim"></div>
  <aside class="pr-panel" id="dr-panel" aria-hidden="true" aria-labelledby="dr-panel-judul">
    <div class="pr-panel-head"><p class="pr-kicker" id="dr-panel-kicker"></p><h2 id="dr-panel-judul"></h2>
      <button type="button" class="pr-x" id="dr-tutup" aria-label="Tutup">✕</button></div>
    <div class="pr-panel-body" id="dr-panel-isi"></div>
  </aside>
@endsection

@section('scripts')
@include('peta-risiko-pja.drm._ui')
<script>
(() => {
  const META = {{ \Illuminate\Support\Js::from(\Illuminate\Support\Arr::only($meta, ['sampai', 'jendela', 'stripHari', 'stripMulai', 'kekerapan', 'konsekuensi', 'level', 'kolomAkt', 'kolomBahaya', 'proses'])) }};
  const CAK = @json($meta['cakupan']['PJA|'.$ringkas['id']] ?? null);
  const URL_DATA = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.pja.data', ['pja' => $ringkas['id']])) }};
  const $ = (s) => document.querySelector(s);
  const E = DR.esc, F = DR.fmt;
  const C = Object.fromEntries(META.kolomAkt.map((k, i) => [k, i]));
  const B = Object.fromEntries(META.kolomBahaya.map((k, i) => [k, i]));
  const URL_FLAG = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.flag.index', ['pja' => $ringkas['id']])) }};
  const URL_REVIEW = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.flag.review', ['flag' => '__ID__'])) }};
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const NAMA_PROSES = Object.fromEntries(META.proses.map((p) => [p.kode, p.nama]));
  const HARI_INI = new Date().toISOString().slice(0, 10);
  let D = null, idx = {}, FL = { tersedia: false, flag: [] };

  if (CAK) {
    $('#dr-kpis').innerHTML = DR.kpiCards(CAK.kpi);
    $('#dr-matriks').innerHTML = DR.matriks('Baseline (risiko awal HIRA)', CAK.mAwal, META) + DR.matriks('Final (identifikasi SAP)', CAK.mFinal, META);
  }

  Promise.all([
    fetch(URL_DATA, { headers: { Accept: 'application/json' } }).then((r) => r.json()),
    fetch(URL_FLAG, { headers: { Accept: 'application/json' } }).then((r) => r.ok ? r.json() : FL).catch(() => FL),
  ]).then(([d, fl]) => { D = d; FL = fl; siapkan(); render(); })
    .catch(() => ['#dr-profil', '#dr-personil', '#dr-strip'].forEach((s) => { $(s).innerHTML = '<div class="pr-empty">Gagal memuat data PJA.</div>'; }));

  function siapkan() {
    idx.dl = Object.fromEntries(D.lokasi.map((l) => [l.dl, l]));
    idx.bahaya = Object.fromEntries(D.bahaya.map((b) => [b[B.hid], b]));
    idx.bahayaPerA = {};
    D.bahaya.forEach((b) => (idx.bahayaPerA[b[B.a]] ||= []).push(b));
    idx.sorot = {};
    D.sorot.forEach((s) => { idx.sorot[s.dl + '|' + s.hid] = s; });
    idx.incDl = {};
    D.insiden.forEach((i) => (idx.incDl[i.dl] ||= []).push(i));
    idx.temuanDl = {};
    D.temuan.forEach((t) => (idx.temuanDl[t.dl] ||= []).push(t));
    indeksFlag();
    if (D.info.mitra.length) $('#dr-sub').textContent += ' · Mitra: ' + D.info.mitra.map((m) => m.nama).join(', ');
  }

  function render() { profil(); pasangLokasi(); personil(); prosesBisnis(); strip(); openRisk(); temuan(); }

  // --------------------------------------------- tabel lokasi (bagian 1)
  /** Lokasi → detail lokasi sebagai tabel; baris lokasi bisa dibuka/tutup. */
  function tabelLokasi(perLok) {
    const agg = {};
    D.akt.forEach((r) => {
      const a = (agg[r[C.dl]] ||= { akt: 0, b: 0, g: 0, t: 0 });
      a.akt++; a.b += r[C.bahaya]; a.g += r[C.g]; a.t += r[C.t];
    });
    const lapor = Object.fromEntries(D.coverage.map((c) => [c.dl, Number(c.semua) || 0]));
    const angka = (ds) => ds.reduce((s, d) => {
      const a = agg[d.dl] || {};
      s.akt += a.akt || 0; s.b += a.b || 0; s.g += a.g || 0; s.t += a.t || 0; s.lap += lapor[d.dl] || 0;
      return s;
    }, { akt: 0, b: 0, g: 0, t: 0, lap: 0 });
    const sel = (s) => `<td class="r">${F(s.akt)}</td><td class="r">${F(s.b)}</td>
      <td class="r">${s.g ? `<b style="color:var(--g)">${F(s.g)}</b>` : 0}</td><td class="r">${F(s.t)}</td>
      <td class="r">${s.lap ? F(s.lap) : '<span class="dr-tag st-T">0</span>'}</td>`;
    const grup = Object.entries(perLok).map(([l, ds]) => ({ l, ds, s: angka(ds) }))
      .sort((a, b) => b.s.g - a.s.g || a.l.localeCompare(b.l));

    return `<div style="display:flex;gap:8px;margin:8px 0">
        <button type="button" class="pr-back" data-lok-semua="1">Buka semua</button>
        <button type="button" class="pr-back" data-lok-semua="0">Tutup semua</button></div>
      <div class="dr-scroll"><table class="pr-tbl dr-tbl dr-lok">
        <thead><tr><th>Lokasi / detail lokasi</th><th>Grup</th><th class="r" title="Jumlah aktivitas HIRA">Akt.</th>
          <th class="r" title="Pasangan detail lokasi × bahaya-risiko">Bahaya</th><th class="r" title="Teridentifikasi – ada gap">Gap</th>
          <th class="r" title="Tidak teridentifikasi (open risk)">Tdk ident.</th>
          <th class="r" title="Jumlah laporan SAP ${META.jendela} hari terakhir (semua pelapor)">SAP ${META.jendela}h</th></tr></thead>
        ${grup.map(({ l, ds, s }, i) => {
          const grp = [...new Set(ds.map((d) => d.grp))];
          return `<tbody>
            <tr class="dr-lok-head" data-lok="${i}" tabindex="0" role="button" aria-expanded="false">
              <td><span class="dr-caret" aria-hidden="true">▸</span> <b>${E(l)}</b> <span class="dr-mute">${ds.length} detail</span></td>
              <td class="dr-mute">${grp.length > 2 ? grp.length + ' grup' : grp.map(E).join(', ')}</td>${sel(s)}</tr>
            ${ds.slice().sort((a, b) => ((agg[b.dl] || {}).g || 0) - ((agg[a.dl] || {}).g || 0) || a.detlok.localeCompare(b.detlok)).map((d) =>
              `<tr class="dr-lok-child" data-lok="${i}" hidden><td style="padding-left:34px">${E(d.detlok)}</td>
                <td><span class="dr-tag" style="--bg:#EEF2F6;--c:#333">${E(d.grp)}</span></td>${sel(angka([d]))}</tr>`).join('')}
          </tbody>`;
        }).join('')}
      </table></div>`;
  }

  function pasangLokasi() {
    const atur = (head, buka) => {
      head.setAttribute('aria-expanded', String(buka));
      head.querySelector('.dr-caret').textContent = buka ? '▾' : '▸';
      document.querySelectorAll(`.dr-lok-child[data-lok="${head.dataset.lok}"]`).forEach((tr) => { tr.hidden = !buka; });
    };
    document.querySelectorAll('.dr-lok-head').forEach((head) => {
      const toggle = () => atur(head, head.getAttribute('aria-expanded') !== 'true');
      head.addEventListener('click', toggle);
      head.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
    });
    document.querySelectorAll('[data-lok-semua]').forEach((b) => b.addEventListener('click', () =>
      document.querySelectorAll('.dr-lok-head').forEach((h) => atur(h, b.dataset.lokSemua === '1'))));
  }

  // ------------------------------------------------------------------ flag
  function indeksFlag() {
    idx.flag = {};
    (FL.flag || []).forEach((f) => (idx.flag[f.dl + '|' + f.aktivitas] ||= []).push(f));
  }
  function flagBaris(r) { return idx.flag[r[C.dl] + '|' + r[C.a]] || []; }
  /** Flag disetujui yang berlaku hari ini (mulai terbaru menang). */
  function flagBerlaku(r) {
    return flagBaris(r).filter((f) => f.review === 'APPROVED' && f.mulai <= HARI_INI && (!f.selesai || f.selesai >= HARI_INI))
      .sort((a, b) => b.mulai.localeCompare(a.mulai) || b.id - a.id)[0] || null;
  }
  function nonaktif(r) { const f = flagBerlaku(r); return !!f && f.status === 'NONAKTIF'; }
  /** Ada laporan SAP di strip pada hari-hari periode NONAKTIF yang disetujui. */
  function konflik(r) {
    const s = r[C.strip] || '';
    return flagBaris(r).some((f) => f.review === 'APPROVED' && f.status === 'NONAKTIF' && Array.from(s).some((c, i) => {
      if (c === '-') return false;
      const t = tanggalKe(i).toISOString().slice(0, 10);
      return t >= f.mulai && (!f.selesai || t <= f.selesai);
    }));
  }
  function tagFlag(r) {
    const f = flagBerlaku(r);
    const pending = flagBaris(r).some((x) => x.review === 'PENDING');
    return (f ? `<span class="dr-tag ${f.status === 'NONAKTIF' ? 'st-T' : 'st-K'}">${f.status === 'NONAKTIF' ? 'Nonaktif' : 'Aktif'}</span>` : '<span class="dr-mute">Aktif (bawaan)</span>')
      + (pending ? ' <span class="dr-tag st-X">Menunggu review</span>' : '')
      + (konflik(r) ? ' <span class="dr-tag st-L" title="Ada laporan SAP saat periode nonaktif">Konflik</span>' : '');
  }
  /** Baris yang dihitung sebagai open risk: nonaktif disetujui dikecualikan, kecuali masih ada gap. */
  function dihitung(r) { return !nonaktif(r) || r[C.g] > 0; }

  // --------------------------------------------------------------- proses
  function prosesBisnis() {
    const agg = {};
    D.akt.forEach((r) => {
      const p = r[C.proses] || 'X';
      const a = (agg[p] ||= { akt: new Set(), dl: new Set(), bahaya: 0, K: 0, G: 0, T: 0, X: 0 });
      a.akt.add(r[C.a]); a.dl.add(r[C.dl]);
      a.bahaya += r[C.bahaya]; a.K += r[C.k]; a.G += r[C.g]; a.T += r[C.t]; a.X += r[C.x];
    });
    const sel = $('#dr-f-proses');
    sel.innerHTML = '<option value="">Semua proses bisnis</option>' + META.proses.filter((p) => agg[p.kode])
      .map((p) => `<option value="${E(p.kode)}">${E(p.nama)}</option>`).join('');
    $('#dr-proses').innerHTML = `<thead><tr><th>Proses bisnis</th><th>Jenis</th><th class="r">Aktivitas</th><th class="r">Detail lokasi</th>
      <th class="r">Pasangan bahaya × detlok</th><th>Komposisi</th><th class="r">! Gap</th><th class="r">○ Tidak teridentifikasi</th><th class="r">% teridentifikasi</th></tr></thead><tbody>${
      META.proses.filter((p) => agg[p.kode]).map((p) => { const a = agg[p.kode]; return `<tr data-p="${E(p.kode)}" style="cursor:pointer">
        <td><span class="pr-name" style="font-size:14px">${E(p.nama)}</span></td><td class="dr-mute">${p.jenis === 'inti' ? 'Inti' : 'Pendukung'}</td>
        <td class="r">${a.akt.size}</td><td class="r">${a.dl.size}</td><td class="r dr-num">${F(a.bahaya)}</td><td>${DR.comp(a)}</td>
        <td class="r">${F(a.G)}</td><td class="r">${F(a.T)}</td><td class="r">${DR.pct(a.K + a.G, a.bahaya)}</td></tr>`; }).join('')
      || '<tr><td colspan="9" class="pr-empty">Belum ada aktivitas.</td></tr>'}</tbody>`;
    document.querySelectorAll('#dr-proses tr[data-p]').forEach((tr) => tr.addEventListener('click', () => {
      sel.value = tr.dataset.p; tabelOpen(($('#dr-cari').value || '').toLowerCase());
      document.getElementById('dr-open').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
  }

  // ------------------------------------------------------------------ 1–6
  function profil() {
    const perLok = {};
    D.lokasi.forEach((l) => (perLok[l.lokasi] ||= []).push(l));
    const aktCount = {};
    D.akt.forEach((r) => { aktCount[r[C.a]] = (aktCount[r[C.a]] || 0) + 1; });
    const aktList = Object.keys(aktCount).sort((a, b) => (idx.bahayaPerA[b] || []).length - (idx.bahayaPerA[a] || []).length);
    const lv = (kol) => META.level.map((l) => D.bahaya.filter((b) => b[kol] === l).length);
    const fin = CAK ? META.level.map((l) => CAK.kpi.final[l] || 0) : [0, 0, 0, 0];
    const awal = lv(B.n1), res = lv(B.n2);
    const adaKontrol = D.bahaya.filter((b) => b[B.kontrol]).length;

    $('#dr-profil').innerHTML = `<ul class="dr-list">
      <li><b><span class="dr-sec-num">1</span>Lokasi – detail lokasi terkait</b> <span class="dr-mute">${Object.keys(perLok).length} lokasi · ${D.lokasi.length} detail lokasi</span>
        ${tabelLokasi(perLok)}</li>
      <li><b><span class="dr-sec-num">2</span>Aktivitas terkait (HIRA)</b> <span class="dr-mute">${aktList.length} aktivitas</span>
        <details style="margin-top:6px"><summary class="dr-mute">Lihat daftar</summary><div style="margin-top:6px">${aktList.map((a) =>
          `<span class="dr-tag" style="--bg:#EEF2F6;--c:#333;margin:2px">${E(D.namaAkt[a] || a)} · ${aktCount[a]} detlok</span>`).join(' ')}</div></details></li>
      <li><b><span class="dr-sec-num">3</span>Bahaya-risiko terkait (HIRA)</b> <span class="dr-mute">${F(D.bahaya.length)} bahaya-risiko unik dari aktivitas di atas</span></li>
      <li><b><span class="dr-sec-num">4</span>Level risiko</b>
        <table class="pr-tbl dr-tbl" style="margin-top:6px"><thead><tr><th></th>${META.level.map((l) => `<th class="r">${l}</th>`).join('')}</tr></thead><tbody>
          <tr><td>Risiko awal (HIRA)</td>${awal.map((n) => `<td class="r">${F(n)}</td>`).join('')}</tr>
          <tr><td>Risiko residual (HIRA)</td>${res.map((n) => `<td class="r">${F(n)}</td>`).join('')}</tr>
          <tr><td>Berdasarkan identifikasi SAP</td>${fin.map((n) => `<td class="r"><b>${F(n)}</b></td>`).join('')}</tr></tbody></table>
        <span class="dr-mute">Baris SAP dihitung per bahaya-risiko unik di area PJA; residual hanya berlaku bila terkontrol menurut SAP.</span></li>
      <li><b><span class="dr-sec-num">5</span>Pengendalian yang ada (HIRA)</b> <span class="dr-mute">${F(adaKontrol)} dari ${F(D.bahaya.length)} bahaya punya pengendalian tercatat — rinciannya di panel tiap aktivitas (FYI)</span></li>
      <li><b><span class="dr-sec-num">6</span>Insiden & root cause di lokasi PJA</b> <span class="dr-mute">${D.insiden.length} insiden (investigasi, tanpa GR/PSPP)</span>
        ${D.insiden.slice(0, 8).map((i) => `<div style="margin-top:8px;padding-left:10px;border-left:3px solid var(--inc)">
          <b>${E(i.tanggal)} · ${E(i.kategori || 'Tanpa kategori')}</b> <span class="dr-mute">${E(i.detlok)}</span>
          <div class="pr-clamp dr-mute">${E(i.kronologi)}</div>
          ${i.akar.length ? `<details><summary class="dr-mute">Root cause (${i.akar.length})</summary><ul style="margin:4px 0;padding-left:18px">${i.akar.map((a) => `<li class="dr-mute">${E(a)}</li>`).join('')}</ul></details>` : ''}
        </div>`).join('')}
        ${D.insiden.length > 8 ? `<div class="dr-mute" style="margin-top:6px">+${D.insiden.length - 8} insiden lain (lihat panel aktivitas)</div>` : ''}</li>
    </ul>`;
  }

  // ------------------------------------------------------------------ a–c
  function personil() {
    const akhirBearc = {};
    D.bearc.forEach((b) => { if (!akhirBearc[b.sid] || akhirBearc[b.sid].mulai < b.mulai) akhirBearc[b.sid] = b; });
    const bc = D.personil.filter((p) => p.pja === D.info.nama);
    const mk = D.personil.filter((p) => p.pja !== D.info.nama);
    const tabel = (rows) => rows.length ? `<table class="pr-tbl dr-tbl"><thead><tr><th>Personil</th><th>SAP 14 hari</th><th class="r">Total</th><th class="r">%SAP minggu lalu</th><th>BeARC</th></tr></thead><tbody>${
      rows.map((p) => {
        const tot = p.harian.reduce((a, n) => a + Number(n), 0);
        const mx = Math.max(1, ...p.harian.map(Number));
        const b = akhirBearc[p.sid];
        return `<tr><td><span class="pr-name" style="font-size:14px">${E(p.nama)}</span><span class="pr-meta">${E(p.sid)} · ${E(p.perusahaan)}${p.pja !== D.info.nama ? ' · ' + E(p.pja) : ''}</span></td>
          <td><span class="dr-spark" title="${p.harian.join(', ')}">${p.harian.map((n) => Number(n) ? `<i style="height:${(100 * n / mx).toFixed(0)}%"></i>` : '<i class="z"></i>').join('')}</span></td>
          <td class="r dr-num">${F(tot)}</td>
          <td class="r">${!b ? '–' : b.persen === null ? 'N/A' : Number(b.persen).toFixed(0) + '%'}</td>
          <td>${!b ? '<span class="dr-mute">tidak ada data</span>' : Number(b.banned_sap) ? `<span class="dr-tag st-L" title="${E(b.status_banned)}">Banned SAP</span>`
            : `<span class="dr-tag st-K" title="${E(b.status_banned)}">Achieve dihitung</span>`}</td></tr>`;
      }).join('')}</tbody></table>` : '<div class="pr-empty">Tidak ada personil terdaftar.</div>';

    const cov = D.coverage.map((c) => ({ ...c, l: idx.dl[c.dl] })).filter((c) => c.l).sort((a, b) => Number(a.semua) - Number(b.semua));
    const tanpa = cov.filter((c) => !Number(c.semua)).length;
    $('#dr-personil').innerHTML = `
      <div class="pr-card-body" style="padding-bottom:6px"><b><span class="dr-sec-num">a</span>Personil PJA BC (${bc.length})</b></div>${tabel(bc)}
      <div class="pr-card-body" style="padding-bottom:6px"><b><span class="dr-sec-num">b</span>Personil PJA Mitra (${mk.length})</b></div>${tabel(mk)}
      <div class="pr-card-body" style="padding-bottom:6px"><b><span class="dr-sec-num">c</span>Coverage SAP detail lokasi (${META.jendela} hari)</b>
        <div class="dr-mute">${tanpa} dari ${cov.length} detail lokasi tanpa laporan SAP sama sekali</div></div>
      <div class="dr-scroll" style="max-height:340px;overflow-y:auto"><table class="pr-tbl dr-tbl"><thead><tr><th>Detail lokasi</th><th class="r">Laporan (semua)</th><th class="r">Oleh personil PJA</th></tr></thead><tbody>${
        cov.map((c) => `<tr><td>${E(c.l.detlok)}<span class="pr-meta">${E(c.l.lokasi)}</span></td><td class="r">${Number(c.semua) ? F(c.semua) : '<span class="dr-tag st-T">0</span>'}</td>
          <td class="r">${F(c.dedicated)} <span class="dr-mute">${DR.pct(Number(c.dedicated), Number(c.semua))}</span></td></tr>`).join('')}</tbody></table></div>`;
  }

  // ------------------------------------------------------------------ 7
  function tanggalKe(i) { const d = new Date(META.stripMulai + 'T00:00:00'); d.setDate(d.getDate() + i); return d; }
  function isoMinggu(d) { const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate())); const day = t.getUTCDay() || 7; t.setUTCDate(t.getUTCDate() + 4 - day); const y = new Date(Date.UTC(t.getUTCFullYear(), 0, 1)); return Math.ceil(((t - y) / 864e5 + 1) / 7); }

  function insidenBaris(r) {
    const daftar = idx.incDl[r[C.dl]] || [];
    return daftar.filter((i) => i.bahaya.some((h) => (idx.bahaya[h] || [])[B.a] === r[C.a]));
  }

  function strip() {
    const n = META.stripHari;
    const hanyaGap = $('#dr-hanya-gap').checked;
    const rows = D.akt.filter((r) => !hanyaGap || r[C.g] || /[GCK]/.test(r[C.strip]))
      .sort((a, b) => b[C.g] - a[C.g] || b[C.t] - a[C.t]).slice(0, 300);
    const hari = Array.from({ length: n }, (_, i) => tanggalKe(i));
    const awalMinggu = hari.map((d) => d.getDay() === 1);
    const kepala = `<div class="dr-weeks">${hari.map((d, i) => `<span${awalMinggu[i] && i ? ' style="margin-left:3px"' : ''}>${awalMinggu[i] ? 'W' + isoMinggu(d) : ''}</span>`).join('')}</div>`;
    const mulaiStr = META.stripMulai;
    $('#dr-strip').innerHTML = rows.length ? `<table class="dr-strip-tbl"><thead><tr><th>Aktivitas</th><th>Detail lokasi</th><th>${kepala}</th><th>Bahaya</th></tr></thead><tbody>${
      rows.map((r, ri) => {
        const l = idx.dl[r[C.dl]] || {};
        const s = (r[C.strip] || '').padEnd(n, '-');
        const incHari = new Set(insidenBaris(r).map((i) => Math.round((new Date(i.tanggal + 'T00:00:00') - new Date(mulaiStr + 'T00:00:00')) / 864e5)));
        const kotak = Array.from(s).map((c, i) => `<span class="c-${c === '-' ? '' : c}${awalMinggu[i] && i ? ' wk' : ''}${incHari.has(i) ? ' inc' : ''}" title="${hari[i].toLocaleDateString('id-ID')}"></span>`).join('');
        return `<tr data-k="${ri}"><td><span class="dr-trunc" title="${E(D.namaAkt[r[C.a]])}">${E(D.namaAkt[r[C.a]] || r[C.a])}</span></td>
          <td><span class="dr-trunc" style="max-width:180px" title="${E(l.lokasi)} / ${E(l.detlok)}">${E(l.detlok)}</span></td>
          <td><div class="dr-strip">${kotak}</div></td><td>${DR.comp({ K: r[C.k], G: r[C.g], T: r[C.t], X: r[C.x] })}</td></tr>`;
      }).join('')}</tbody></table>${D.akt.length > rows.length ? `<p class="dr-mute">Menampilkan ${rows.length} dari ${D.akt.length} pasangan.</p>` : ''}`
      : '<div class="pr-empty">Belum ada laporan SAP yang terpetakan di area PJA ini.</div>';
    document.querySelectorAll('#dr-strip tr[data-k]').forEach((tr) => tr.addEventListener('click', () => buka(rows[Number(tr.dataset.k)])));
  }
  document.addEventListener('change', (e) => { if (e.target.id === 'dr-hanya-gap' && D) strip(); });

  // ------------------------------------------------------------------ 8–9
  function hariSejakTerkontrol(s) { const i = s.lastIndexOf('K'); return i < 0 ? null : s.length - 1 - i; }

  function openRisk() {
    const dipakai = D.akt.filter(dihitung);
    const jumlah = (k) => dipakai.reduce((a, r) => a + r[k], 0);
    const off = D.akt.length - dipakai.length;
    $('#dr-open-kpi').innerHTML = [
      [jumlah(C.t), '○ Tidak teridentifikasi (tanpa laporan sama sekali)', 'st-T'],
      [jumlah(C.gOpen), '! Belum closed (temuan open)', 'st-G'],
      [jumlah(C.gUlang), `! Berulang / sistemik (≥ ${@json($meta['ambangUlang'])} temuan dalam ${META.jendela} hari)`, 'st-G'],
      [jumlah(C.gStop), '! OAK stop pekerjaan', 'st-L'],
      [jumlah(C.x), '? Belum terpetakan ke SAP', 'st-X'],
    ].map(([n, l, c]) => `<div class="dr-kpi ${c}"><b>${F(n)}</b><span>${l}</span></div>`).join('')
      + (off ? `<div class="dr-kpi"><b>${F(off)}</b><span>Aktivitas × detail lokasi dinonaktifkan (flag disetujui, tanpa gap) — tidak dihitung</span></div>` : '');
    tabelOpen(($('#dr-cari').value || '').toLowerCase());
    if (openRisk.siap) return;
    openRisk.siap = true;
    $('#dr-cari').addEventListener('input', (e) => tabelOpen(e.target.value.toLowerCase()));
    $('#dr-f-proses').addEventListener('change', () => tabelOpen(($('#dr-cari').value || '').toLowerCase()));
  }

  function tabelOpen(cari) {
    const proses = $('#dr-f-proses').value;
    const rows = D.akt.filter((r) => (r[C.g] || r[C.t]) && dihitung(r) && (!proses || r[C.proses] === proses)).filter((r) => {
      if (!cari) return true;
      const l = idx.dl[r[C.dl]] || {};
      return ((D.namaAkt[r[C.a]] || '') + ' ' + l.detlok + ' ' + l.lokasi).toLowerCase().includes(cari);
    }).sort((a, b) => b[C.g] - a[C.g] || b[C.sig] - a[C.sig] || b[C.t] - a[C.t]).slice(0, 400);
    $('#dr-open').innerHTML = `<thead><tr><th>Aktivitas</th><th>Detail lokasi</th><th>Status</th><th class="r">! Gap</th><th class="r">○ Tidak teridentifikasi</th>
      <th>Jenis gap</th><th>Terakhir dilaporkan</th><th class="r">Hari sejak terkontrol</th><th>Terkait insiden?</th><th>Status aktivitas</th></tr></thead><tbody>${
      rows.map((r, i) => {
        const l = idx.dl[r[C.dl]] || {};
        const inc = insidenBaris(r);
        const h = hariSejakTerkontrol(r[C.strip] || '');
        const jenis = [r[C.gOpen] && `belum closed ${r[C.gOpen]}`, r[C.gUlang] && `berulang ${r[C.gUlang]}`, r[C.gStop] && `OAK stop ${r[C.gStop]}`].filter(Boolean).join(' · ');
        return `<tr data-i="${i}" style="cursor:pointer"><td><span class="pr-name" style="font-size:14px">${E(D.namaAkt[r[C.a]] || r[C.a])}</span><span class="pr-meta">${E(NAMA_PROSES[r[C.proses]] || 'Belum terpetakan')}${r[C.viaBukti] ? ' · dari laporan Observasi/OAK' : ''}</span></td>
          <td>${E(l.detlok)}<span class="pr-meta">${E(l.lokasi)} · ${E(l.grp)}</span></td>
          <td>${DR.tag(r[C.g] ? 'G' : 'T')}</td><td class="r">${F(r[C.g])}</td><td class="r">${F(r[C.t])}</td>
          <td class="dr-mute">${jenis || '–'}</td><td>${r[C.terakhir] || '<span class="dr-mute">belum pernah</span>'}</td>
          <td class="r">${h === null ? `<span class="dr-tag st-T">&gt; ${META.stripHari}</span>` : F(h)}</td>
          <td>${inc.length ? `<span class="dr-tag st-L">${inc.length} insiden</span>` : '<span class="dr-mute">–</span>'}</td>
          <td>${tagFlag(r)}</td></tr>`;
      }).join('')}</tbody>`;
    document.querySelectorAll('#dr-open tr[data-i]').forEach((tr) => tr.addEventListener('click', () => buka(rows[Number(tr.dataset.i)])));
  }

  function temuan() {
    $('#dr-temuan-sub').textContent = `Hazard/Inspeksi open di detail lokasi PJA: ${F(D.temuanTotal)} temuan${D.temuanTotal > D.temuan.length ? ` (ditampilkan ${D.temuan.length} terbaru)` : ''}`;
    $('#dr-temuan').innerHTML = `<thead><tr><th>Tanggal</th><th>Detail lokasi</th><th>Ketidaksesuaian</th><th>Deskripsi</th><th>Status</th><th>PIC</th><th>Janji</th></tr></thead><tbody>${
      D.temuan.map((t) => { const l = idx.dl[t.dl] || {}; return `<tr><td>${E(t.tanggal)}<span class="pr-meta">${E(t.jenis)}</span></td>
        <td>${E(l.detlok)}</td><td>${E(t.ket)}<span class="pr-meta">${E(t.subket)}</span></td><td><div class="pr-clamp">${E(t.deskripsi)}</div></td>
        <td><span class="dr-tag st-G">${E(t.status)}</span></td><td>${E(t.pic)}</td><td>${E(t.janji)}</td></tr>`; }).join('')
      || '<tr><td colspan="7" class="pr-empty">Tidak ada temuan open.</td></tr>'}</tbody>`;
  }

  // ------------------------------------------------------------------ panel
  function buka(r) {
    const l = idx.dl[r[C.dl]] || {};
    const nama = D.namaAkt[r[C.a]] || r[C.a];
    const bahaya = (idx.bahayaPerA[r[C.a]] || []).map((b) => ({ b, s: idx.sorot[r[C.dl] + '|' + b[B.hid]] }))
      .sort((x, y) => (y.s?.status === 'G') - (x.s?.status === 'G') || META.level.indexOf(y.b[B.n1]) - META.level.indexOf(x.b[B.n1]));
    const tm = (idx.temuanDl[r[C.dl]] || []).filter((t) => (t.aktivitas || []).includes(nama));
    const inc = insidenBaris(r);
    $('#dr-panel-kicker').textContent = `${l.lokasi || ''} · ${l.detlok || ''}`;
    $('#dr-panel-judul').textContent = nama;
    $('#dr-panel-isi').innerHTML = `
      <div class="pr-card"><div class="dr-kpis" style="grid-template-columns:repeat(4,1fr);padding:12px">${['K', 'G', 'T', 'X'].map((k) =>
        `<div class="dr-kpi st-${k}"><b>${F(r[C[k.toLowerCase()]])}</b><span>${DR.ST[k].pendek}</span></div>`).join('')}</div></div>
      ${inc.length ? `<div class="pr-card"><div class="pr-card-head"><div><b>Insiden terkait (${inc.length})</b></div></div>${inc.map((i) =>
        `<div class="pr-entry"><div class="pr-entry-top"><strong>${E(i.tanggal)} · ${E(i.kategori)}</strong><span class="dr-mute">No ${E(i.no)}</span></div>
         <p>${E(i.kronologi)}</p>${i.akar.length ? `<p><b>Root cause:</b> ${i.akar.map(E).join(' · ')}</p>` : ''}</div>`).join('')}</div>` : ''}
      <div class="pr-card"><div class="pr-card-head"><div><b>Bahaya-risiko (${bahaya.length})</b><small>Status per bahaya hanya tersedia untuk yang ada gap / terkait insiden; sisanya lihat komposisi di atas.</small></div></div>${
        bahaya.map(({ b, s }) => `<div class="pr-entry"><div class="pr-entry-top"><strong>${E(b[B.bahaya])}</strong>
          <span>${s ? DR.tag(s.status === 'G' ? 'G' : s.status) : ''}</span></div>
          <p>${E(b[B.sub])} · potensi: ${E(b[B.potensi])}</p>
          <p><span class="dr-tag lv-${E(b[B.n1])}">Awal ${E(b[B.n1])}</span> → <span class="dr-tag lv-${E(b[B.n2])}">Residual ${E(b[B.n2])}</span>
            ${s && s.status === 'G' ? `<span class="dr-mute"> · open ${s.open_n}, ${s.rep} temuan ${META.jendela} hr${Number(s.stop) ? ', OAK stop' : ''}</span>` : ''}
            ${s && Number(s.n_inc) ? `<span class="dr-tag st-L">insiden ${E(s.tgl_inc)}</span>` : ''}</p>
          ${b[B.kontrol] ? `<details><summary class="dr-mute">Pengendalian HIRA (FYI)</summary><p>${E(b[B.kontrol]).split(' | ').join('<br>')}</p></details>` : ''}</div>`).join('')}</div>
      <div class="pr-card"><div class="pr-card-head"><div><b>Temuan open terkait (${tm.length})</b><small>Cara melaporkan: Hazard/Inspeksi di BeSafe pada detail lokasi ini</small></div></div>${
        tm.length ? tm.slice(0, 50).map((t) => `<div class="pr-entry"><div class="pr-entry-top"><strong>${E(t.subket || t.ket)}</strong><span class="dr-tag st-G">${E(t.status)}</span></div>
          <p>${E(t.tanggal)} · PIC ${E(t.pic)} · janji ${E(t.janji || '–')}</p><p class="pr-clamp">${E(t.deskripsi)}</p></div>`).join('') : '<div class="pr-empty">Tidak ada.</div>'}</div>
      ${panelFlag(r)}`;
    pasangFlag(r);
    document.body.classList.add('is-open');
    $('#dr-panel').setAttribute('aria-hidden', 'false');
    $('#dr-tutup').focus();
  }
  function panelFlag(r) {
    const riwayat = flagBaris(r);
    const isi = !FL.tersedia
      ? '<div class="pr-empty">Fitur flag belum aktif: tabel <code>peta_risiko_flag_aktivitas</code> belum dimigrasi.</div>'
      : `<div class="pr-card-body">
          <p class="dr-mute" style="margin:0 0 10px">Status sekarang: ${tagFlag(r)}</p>
          <form id="dr-flag-form" class="dr-flag-form">
            <label>Status <select name="status"><option value="NONAKTIF">Nonaktif</option><option value="AKTIF">Aktif</option></select></label>
            <label>Mulai <input type="date" name="mulai" value="${HARI_INI}" required></label>
            <label>Selesai <input type="date" name="selesai"></label>
            <label style="grid-column:1/-1">Alasan <textarea name="alasan" rows="2" minlength="10" required placeholder="Mis. pekerjaan dihentikan sementara karena …"></textarea></label>
            <div style="grid-column:1/-1;display:flex;gap:10px;align-items:center"><button type="submit" class="pr-back">Ajukan flag</button><span id="dr-flag-msg" class="dr-mute"></span></div>
          </form></div>
        ${riwayat.length ? riwayat.map((f) => `<div class="pr-entry"><div class="pr-entry-top"><strong>${f.status === 'NONAKTIF' ? 'Nonaktif' : 'Aktif'} · ${E(f.mulai)} s/d ${E(f.selesai || 'seterusnya')}</strong>
            <span class="dr-tag ${f.review === 'APPROVED' ? 'st-K' : f.review === 'REJECTED' ? 'st-L' : 'st-X'}">${f.review === 'APPROVED' ? 'Disetujui' : f.review === 'REJECTED' ? 'Ditolak' : 'Menunggu review'}</span></div>
            <p>${E(f.alasan)}</p><p>Diajukan ${E(f.pengaju || '–')} · ${E(f.dibuat)}${f.reviewer ? ` · direview ${E(f.reviewer)} ${E(f.direview)}` : ''}${f.catatan ? ` — ${E(f.catatan)}` : ''}</p>
            ${f.review === 'PENDING' && Number(f.pengajuId) !== Number(FL.saya) ? `<div style="display:flex;gap:8px;margin-top:6px">
              <button type="button" class="pr-back" data-review="${f.id}" data-k="APPROVED">Setujui</button>
              <button type="button" class="pr-back" data-review="${f.id}" data-k="REJECTED">Tolak</button></div>` : ''}</div>`).join('') : ''}`;
    return `<div class="pr-card"><div class="pr-card-head"><div><b>Flag status aktivitas</b><small>Berlaku setelah disetujui orang lain (bukan pengaju)</small></div></div>${isi}</div>`;
  }

  async function kirim(url, body) {
    const res = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(body) });
    const j = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(j.errors ? Object.values(j.errors).flat().join(' ') : (j.message || 'Gagal menyimpan.'));
    return j;
  }

  async function muatUlangFlag(r) {
    FL = await fetch(URL_FLAG, { headers: { Accept: 'application/json' } }).then((x) => x.json());
    indeksFlag(); openRisk(); buka(r);
  }

  function pasangFlag(r) {
    const form = document.getElementById('dr-flag-form');
    if (form) form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const msg = document.getElementById('dr-flag-msg');
      msg.textContent = 'Menyimpan…';
      try {
        await kirim(URL_FLAG, { dl: r[C.dl], aktivitas: r[C.a], status: fd.get('status'), mulai: fd.get('mulai'), selesai: fd.get('selesai') || null, alasan: fd.get('alasan') });
        await muatUlangFlag(r);
      } catch (err) { msg.textContent = err.message; msg.style.color = 'var(--red)'; }
    });
    document.querySelectorAll('[data-review]').forEach((b) => b.addEventListener('click', async () => {
      const catatan = b.dataset.k === 'REJECTED' ? prompt('Alasan menolak:') : '';
      if (b.dataset.k === 'REJECTED' && !catatan) return;
      try { await kirim(URL_REVIEW.replace('__ID__', b.dataset.review), { keputusan: b.dataset.k, catatan }); await muatUlangFlag(r); }
      catch (err) { alert(err.message); }
    }));
  }

  function tutup() { document.body.classList.remove('is-open'); $('#dr-panel').setAttribute('aria-hidden', 'true'); }
  $('#dr-tutup').addEventListener('click', tutup);
  $('#dr-scrim').addEventListener('click', tutup);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') tutup(); });
})();
</script>
@endsection
