@extends('peta-risiko-pja._layout')

@section('title', 'Peta Risiko PJA')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Peta Risiko PJA</h6>
    @if ($meta)
      <p class="text-sm text-secondary-light mb-0 mt-4" id="pr-periode-label"></p>
    @endif
  </div>
  <ul class="d-flex align-items-center gap-2 mb-0 list-unstyled">
    <li class="fw-medium d-flex align-items-center gap-1"><i class="ri-map-pin-2-line text-lg"></i> Peta Risiko PJA</li>
    <li>-</li>
    <li class="fw-medium">Semua PJA</li>
  </ul>
</div>

@if (! $meta)
  <div class="card radius-8 border-0">
    <div class="card-body p-24 text-center">
      <h6 class="fw-semibold text-lg mb-8">Snapshot belum diimpor</h6>
      <p class="text-secondary-light mb-0">Jalankan <code>php artisan peta-risiko:impor &lt;Mapping_PJA_Proses_Lokasi_SAP.xlsx&gt; &lt;Peta_HIRA_Risiko_Dinamis.html&gt;</code></p>
    </div>
  </div>
@else
  <div class="row gy-4 mb-24" id="pr-kpis"></div>

  <div class="card radius-8 border-0">
    <div class="card-body p-24">
      <div class="d-flex align-items-center flex-wrap gap-3 justify-content-between mb-20">
        <div>
          <h6 class="mb-0 fw-bold text-lg">Daftar PJA</h6>
          <p class="text-sm text-secondary-light mb-0">Diurutkan dari tema yang paling perlu tindakan (ada gap + tanpa kontrol HIRA).</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <select class="form-select form-select-sm w-auto bg-base border text-secondary-light" id="pr-periode" aria-label="Periode">
            <option value="wk">Minggu ini</option>
            <option value="ytd">YTD</option>
          </select>
          <select class="form-select form-select-sm w-auto bg-base border text-secondary-light" id="pr-site" aria-label="Site">
            <option value="">Semua site</option>
          </select>
          <input type="search" class="form-control form-control-sm bg-base border w-auto" id="pr-cari" placeholder="Cari PJA…" aria-label="Cari PJA">
        </div>
      </div>
      <div class="table-responsive scroll-sm">
        <table class="table bordered-table mb-0">
          <thead>
            <tr>
              <th scope="col">PJA</th>
              <th scope="col" class="text-center">Proses</th>
              <th scope="col">Komposisi status</th>
              <th scope="col" class="text-center">Perlu tindakan</th>
              <th scope="col" class="text-center">SAP open</th>
              <th scope="col" class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody id="pr-list"></tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="text-sm text-secondary-light mt-20">
    <p class="mb-4">Angka = jumlah tema bahaya per PJA × bisnis proses. Satu lokasi bisa dimiliki beberapa PJA, jadi angka ini bukan jumlah lokasi.</p>
    @foreach ($meta['catatan'] as $c)
      <p class="mb-4">{{ $c }}</p>
    @endforeach
    <p class="mb-0">Snapshot dibuat {{ \Illuminate\Support\Carbon::parse($meta['dibuat'])->timezone('Asia/Makassar')->translatedFormat('d M Y H:i') }} WITA.</p>
  </div>
@endif
@endsection

@section('scripts')
@if ($meta)
@include('peta-risiko-pja._status')
<script>
(function () {
  const META = {{ \Illuminate\Support\Js::from(['periode' => $meta['periode'], 'pja' => $meta['pja']]) }};
  const URL_PJA = {{ \Illuminate\Support\Js::from(route('peta-risiko-pja.show', ['pja' => '__ID__'])) }};
  const { fmt, esc } = window.PR_UTIL;
  const state = { periode: @json($periode), site: @json($site), cari: '' };
  const $ = (s) => document.querySelector(s);

  const sites = [...new Set(META.pja.map((p) => p.site))].sort();
  $('#pr-site').insertAdjacentHTML('beforeend', sites.map((s) => `<option value="${esc(s)}">${esc(s)}</option>`).join(''));
  $('#pr-site').value = state.site;
  $('#pr-periode').value = state.periode;

  function render() {
    const per = META.periode;
    $('#pr-periode-label').textContent = (state.periode === 'wk' ? per.wk : per.ytd) + (per.status ? ' · temuan open ' + per.status : '');
    const q = state.cari.toLowerCase();
    const list = META.pja.filter((p) => (!state.site || p.site === state.site) && (!q || p.nama.toLowerCase().includes(q)));

    const tot = { gap: 0, tindak: 0, fyi: 0, ok: 0 };
    list.forEach((p) => Object.keys(tot).forEach((k) => { tot[k] += p.ringkas[state.periode][k]; }));
    $('#pr-kpis').innerHTML = Object.keys(tot).map((k) => window.PR_UTIL.kartu(k, tot[k])).join('');

    const urut = [...list].sort((a, b) => {
      const ra = a.ringkas[state.periode], rb = b.ringkas[state.periode];
      return (rb.gap + rb.tindak) - (ra.gap + ra.tindak) || a.nama.localeCompare(b.nama);
    });
    $('#pr-list').innerHTML = urut.length ? urut.map((p) => {
      const r = p.ringkas[state.periode];
      const aksi = r.gap + r.tindak;
      const href = URL_PJA.replace('__ID__', p.id) + '?periode=' + state.periode;
      return `<tr class="pr-row" data-href="${href}">
        <td><span class="text-md text-primary-light fw-semibold d-block">${esc(p.nama)}</span><span class="text-sm text-secondary-light">${esc(p.site)}</span></td>
        <td class="text-center">${r.proses}</td>
        <td>${window.PR_UTIL.stack(r)}</td>
        <td class="text-center">${aksi ? `<span class="bg-danger-focus text-danger-main px-12 py-4 rounded-pill fw-semibold text-sm">${fmt(aksi)}</span>` : '<span class="text-secondary-light">0</span>'} <span class="text-sm text-secondary-light">/ ${fmt(r.unit)}</span></td>
        <td class="text-center">${fmt(r.open)}${r.lewat ? `<span class="d-block text-xs text-danger-main fw-medium">${fmt(r.lewat)} lewat janji</span>` : ''}</td>
        <td class="text-center"><a href="${href}" class="text-primary-600 hover-text-primary fw-medium d-inline-flex align-items-center gap-1">Lihat <i class="ri-arrow-right-s-line"></i></a></td>
      </tr>`;
    }).join('') : '<tr><td colspan="6" class="text-center text-secondary-light py-24">Tidak ada PJA yang cocok.</td></tr>';

    const u = new URL(location.href);
    u.searchParams.set('periode', state.periode);
    state.site ? u.searchParams.set('site', state.site) : u.searchParams.delete('site');
    history.replaceState(null, '', u);
  }

  $('#pr-periode').addEventListener('change', (e) => { state.periode = e.target.value; render(); });
  $('#pr-site').addEventListener('change', (e) => { state.site = e.target.value; render(); });
  $('#pr-cari').addEventListener('input', (e) => { state.cari = e.target.value.trim(); render(); });
  $('#pr-list').addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-href]');
    if (tr && !e.target.closest('a')) location.href = tr.dataset.href;
  });
  render();
})();
</script>
@endif
@endsection
