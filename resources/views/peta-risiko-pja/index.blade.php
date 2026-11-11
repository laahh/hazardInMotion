@extends('peta-risiko-pja._layout')

@section('title', 'Peta Risiko PJA')

@section('hero-aksi')
  @if ($meta)
    <div class="pr-seg" role="group" aria-label="Periode">
      <button type="button" data-periode="wk">Minggu ini</button>
      <button type="button" data-periode="ytd">YTD</button>
    </div>
  @endif
@endsection

@section('hero')
  <p class="pr-kicker">HSE · Peta Risiko PJA</p>
  <h1 class="pr-title">Apa yang perlu dicek di area PJA</h1>
  <p class="pr-sub" id="pr-periode">{{ $meta ? '' : 'Data belum diimpor' }}</p>
@endsection

@section('content')
@if (! $meta)
  <div class="pr-card"><div class="pr-empty">
    Jalankan <code>php artisan peta-risiko:impor &lt;Mapping_PJA_Proses_Lokasi_SAP.xlsx&gt; &lt;Peta_HIRA_Risiko_Dinamis.html&gt;</code> lalu muat ulang halaman ini.
  </div></div>
@else
  <div class="pr-card">
    <div class="pr-kpis" id="pr-kpis"></div>
    <p class="pr-note">Jumlah tema bahaya per PJA × bisnis proses. Lokasi yang dimiliki beberapa PJA dihitung di setiap PJA.</p>
  </div>

  <div class="pr-card">
    <div class="pr-card-head">
      <div>
        <b>Daftar PJA</b>
        <small>Diurutkan dari tema yang paling perlu tindakan</small>
      </div>
      <div class="pr-filters">
        <div class="pr-chips" id="pr-sites" role="group" aria-label="Filter site"></div>
        <input type="search" class="pr-search" id="pr-cari" placeholder="Cari nama PJA" aria-label="Cari nama PJA">
      </div>
    </div>
    <table class="pr-tbl">
      <thead>
        <tr>
          <th>PJA</th>
          <th class="hide-sm">Komposisi status tema</th>
          <th class="r">Perlu tindakan</th>
          <th class="r hide-sm">SAP open</th>
        </tr>
      </thead>
      <tbody id="pr-list"></tbody>
    </table>
  </div>

  <div class="pr-foot">
    @foreach ($meta['catatan'] as $c)
      <p>{{ $c }}</p>
    @endforeach
    <p>Data per {{ \Illuminate\Support\Carbon::parse($meta['dibuat'])->timezone('Asia/Makassar')->translatedFormat('d M Y, H:i') }} WITA.</p>
  </div>
@endif
@endsection

@section('scripts')
@if ($meta)
<script>
(function () {
  const META = {{ \Illuminate\Support\Js::from(['periode' => $meta['periode'], 'pja' => $meta['pja']]) }};
  const URL_PJA = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.show', ['pja' => '__ID__'])) }};
  const { fmt, esc } = PR;
  const state = { periode: @json($periode), site: @json($site), cari: '' };
  const $ = (s) => document.querySelector(s);
  const sites = [...new Set(META.pja.map((p) => p.site))].sort();

  function render() {
    const per = META.periode;
    $('#pr-periode').textContent = (state.periode === 'wk' ? per.wk : per.ytd) + (per.status ? ' · temuan open ' + per.status : '');
    document.querySelectorAll('[data-periode]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.periode === state.periode));

    const r = (p) => p.ringkas[state.periode];
    const diSite = META.pja.filter((p) => !state.site || p.site === state.site);
    const q = state.cari.toLowerCase();
    const list = diSite.filter((p) => !q || p.nama.toLowerCase().includes(q));

    const tot = { gap: 0, tindak: 0, fyi: 0, ok: 0 };
    diSite.forEach((p) => PR.URUT.forEach((k) => { tot[k] += r(p)[k]; }));
    $('#pr-kpis').innerHTML = PR.kpis(tot);

    $('#pr-sites').innerHTML = [['', 'Semua', META.pja.length], ...sites.map((s) => [s, s, META.pja.filter((p) => p.site === s).length])]
      .map(([v, l, n]) => `<button type="button" class="pr-chip" data-site="${esc(v)}" aria-pressed="${state.site === v}">${esc(l)}<small>${n}</small></button>`).join('');

    const urut = [...list].sort((a, b) => (r(b).gap + r(b).tindak) - (r(a).gap + r(a).tindak) || a.nama.localeCompare(b.nama));
    $('#pr-list').innerHTML = urut.length ? urut.map((p) => {
      const x = r(p);
      const aksi = x.gap + x.tindak;
      const href = URL_PJA.replace('__ID__', p.id) + '?periode=' + state.periode;
      return `<tr data-href="${href}">
        <td><a class="pr-name" href="${href}">${esc(p.nama)}</a><span class="pr-meta">${esc(p.site)} · ${x.proses} bisnis proses</span></td>
        <td class="hide-sm" style="width:34%">${PR.comp(x)}</td>
        <td class="r"><span class="pr-big ${aksi ? 'amber' : 'zero'}">${fmt(aksi)}</span><span class="pr-of">dari ${fmt(x.unit)} tema</span></td>
        <td class="r hide-sm"><span class="pr-big">${fmt(x.open)}</span>${x.lewat ? `<span class="pr-late">${fmt(x.lewat)} lewat janji</span>` : '<span class="pr-of">temuan</span>'}</td>
      </tr>`;
    }).join('') : `<tr><td colspan="4" class="pr-empty">Tidak ada PJA dengan nama "${esc(state.cari)}"${state.site ? ' di ' + esc(state.site) : ''}.</td></tr>`;

    const u = new URL(location.href);
    u.searchParams.set('periode', state.periode);
    state.site ? u.searchParams.set('site', state.site) : u.searchParams.delete('site');
    history.replaceState(null, '', u);
  }

  document.addEventListener('click', (e) => {
    const p = e.target.closest('[data-periode]');
    if (p) { state.periode = p.dataset.periode; render(); return; }
    const s = e.target.closest('[data-site]');
    if (s) { state.site = s.dataset.site; render(); return; }
    const tr = e.target.closest('tr[data-href]');
    if (tr && !e.target.closest('a')) location.href = tr.dataset.href;
  });
  $('#pr-cari').addEventListener('input', (e) => { state.cari = e.target.value.trim(); render(); });
  render();
})();
</script>
@endif
@endsection
