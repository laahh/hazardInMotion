@extends('peta-risiko-pja._layout')

@section('title', 'Peta Risiko PJA')

@section('css')
  @include('peta-risiko-pja._style')
@endsection

@section('content')
<div class="pr" id="pr-index">
  <div class="pr-head">
    <div>
      <p class="pr-eyebrow">Peta Risiko PJA</p>
      <h1 class="pr-title">Apa yang perlu dicek di area saya?</h1>
      @if ($meta)
        <p class="pr-sub" id="pr-periode-label"></p>
      @endif
    </div>
    @if ($meta)
      <div class="pr-tools">
        <div class="pr-seg" role="group" aria-label="Periode">
          <button type="button" data-periode="wk">Minggu ini</button>
          <button type="button" data-periode="ytd">YTD</button>
        </div>
        <select class="pr-select" id="pr-site" aria-label="Site">
          <option value="">Semua site</option>
        </select>
        <input class="pr-input" id="pr-cari" type="search" placeholder="Cari PJA…" aria-label="Cari PJA">
      </div>
    @endif
  </div>

  @if (! $meta)
    <div class="pr-card pr-empty">
      <p><strong>Snapshot belum diimpor.</strong></p>
      <p>Jalankan <code>php artisan peta-risiko:impor &lt;Mapping_PJA_Proses_Lokasi_SAP.xlsx&gt; &lt;Peta_HIRA_Risiko_Dinamis.html&gt;</code></p>
    </div>
  @else
    <div class="pr-kpis" id="pr-kpis"></div>
    <p class="pr-note">Angka = jumlah tema bahaya per PJA × bisnis proses. Satu lokasi bisa dimiliki beberapa PJA, jadi jumlah ini tidak sama dengan jumlah lokasi. Klik kartu untuk mengurutkan.</p>

    <div class="pr-card">
      <div class="pr-list-head">
        <span>PJA</span>
        <span>Proses</span>
        <span>Komposisi status</span>
        <span>Perlu tindakan</span>
        <span>SAP open</span>
        <span></span>
      </div>
      <div id="pr-list"></div>
    </div>

    <ul class="pr-foot">
      @foreach ($meta['catatan'] as $c)
        <li>{{ $c }}</li>
      @endforeach
      <li>Snapshot dibuat {{ \Illuminate\Support\Carbon::parse($meta['dibuat'])->timezone('Asia/Makassar')->translatedFormat('d M Y H:i') }} WITA.</li>
    </ul>
  @endif
</div>
@endsection

@section('scripts')
@if ($meta)
<script>
(function () {
  const META = {{ \Illuminate\Support\Js::from(['periode' => $meta['periode'], 'pja' => $meta['pja']]) }};
  const URL_PJA = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.show', ['pja' => '__ID__'])) }};
  const STATUS = [
    { k: 'gap', label: 'Ada gap', desc: 'Dilaporkan, tapi masih open atau berulang', tag: 'tindak lanjut' },
    { k: 'tindak', label: 'Tanpa kontrol HIRA', desc: 'Belum dilaporkan dan pengendalian HIRA belum terlihat', tag: 'kendalikan' },
    { k: 'fyi', label: 'Belum dilaporkan', desc: 'Pengendalian HIRA sudah ada, tidak perlu dicari-cari', tag: 'info' },
    { k: 'ok', label: 'Terkontrol', desc: 'Dilaporkan dan sudah terverifikasi', tag: 'aman' },
  ];
  const state = { periode: @json($periode), site: @json($site), cari: '', urut: 'aksi' };
  const $ = (s) => document.querySelector(s);
  const fmt = (n) => Number(n || 0).toLocaleString('id-ID');
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const sites = [...new Set(META.pja.map((p) => p.site))].sort();
  $('#pr-site').insertAdjacentHTML('beforeend', sites.map((s) => `<option value="${esc(s)}">${esc(s)}</option>`).join(''));
  $('#pr-site').value = state.site;

  function terpilih() {
    const q = state.cari.toLowerCase();
    return META.pja.filter((p) => (!state.site || p.site === state.site) && (!q || p.nama.toLowerCase().includes(q)));
  }

  function renderKpi(list) {
    const tot = { gap: 0, tindak: 0, fyi: 0, ok: 0 };
    list.forEach((p) => STATUS.forEach((s) => { tot[s.k] += p.ringkas[state.periode][s.k]; }));
    $('#pr-kpis').innerHTML = STATUS.map((s) => `
      <button type="button" class="pr-kpi s-${s.k}" data-urut="${s.k}" aria-pressed="${state.urut === s.k}">
        <div class="pr-kpi-top"><span class="pr-dot"></span>${s.label}<span class="pr-tag">${s.tag}</span></div>
        <div class="pr-kpi-num">${fmt(tot[s.k])}</div>
        <div class="pr-kpi-desc">${s.desc}</div>
      </button>`).join('');
  }

  function bar(r) {
    const t = r.unit || 1;
    return `<div class="pr-bar" title="${STATUS.map((s) => s.label + ': ' + r[s.k]).join(' · ')}">${STATUS.map((s) => r[s.k] ? `<i class="s-${s.k}" style="width:${(100 * r[s.k] / t).toFixed(2)}%"></i>` : '').join('')}</div>`;
  }

  function renderList(list) {
    const key = state.urut === 'aksi' ? (r) => r.gap + r.tindak : (r) => r[state.urut];
    list = [...list].sort((a, b) => key(b.ringkas[state.periode]) - key(a.ringkas[state.periode]) || a.nama.localeCompare(b.nama));
    if (!list.length) {
      $('#pr-list').innerHTML = '<div class="pr-empty">Tidak ada PJA yang cocok.</div>';
      return;
    }
    $('#pr-list').innerHTML = list.map((p) => {
      const r = p.ringkas[state.periode];
      const aksi = r.gap + r.tindak;
      const href = URL_PJA.replace('__ID__', p.id) + '?periode=' + state.periode;
      return `<a class="pr-row" href="${href}">
        <div><div class="pr-row-name">${esc(p.nama)}</div><div class="pr-row-site">${esc(p.site)}</div></div>
        <div class="pr-num">${r.proses}</div>
        <div>${bar(r)}</div>
        <div><span class="pr-act ${aksi ? '' : 'nol'}">${aksi ? '<span class="pr-dot s-tindak"></span>' : ''}${fmt(aksi)}</span> <small class="pr-row-site">/ ${fmt(r.unit)}</small></div>
        <div class="pr-num">${fmt(r.open)}${r.lewat ? `<span class="pr-late">${fmt(r.lewat)} lewat janji</span>` : ''}</div>
        <div class="pr-chev" aria-hidden="true">›</div>
      </a>`;
    }).join('');
  }

  function render() {
    document.querySelectorAll('[data-periode]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.periode === state.periode));
    const per = META.periode;
    $('#pr-periode-label').textContent = state.periode === 'wk'
      ? `${per.wk}${per.status ? ' · ' + per.status : ''}`
      : `${per.ytd} · status temuan open per ${per.status ? per.status.replace(/^status per /, '') : 'snapshot'}`;
    const list = terpilih();
    renderKpi(list);
    renderList(list);
    const u = new URL(location.href);
    u.searchParams.set('periode', state.periode);
    state.site ? u.searchParams.set('site', state.site) : u.searchParams.delete('site');
    history.replaceState(null, '', u);
  }

  document.addEventListener('click', (e) => {
    const p = e.target.closest('[data-periode]');
    if (p) { state.periode = p.dataset.periode; render(); }
    const k = e.target.closest('[data-urut]');
    if (k) { state.urut = state.urut === k.dataset.urut ? 'aksi' : k.dataset.urut; render(); }
  });
  $('#pr-site').addEventListener('change', (e) => { state.site = e.target.value; render(); });
  $('#pr-cari').addEventListener('input', (e) => { state.cari = e.target.value.trim(); render(); });
  render();
})();
</script>
@endif
@endsection
