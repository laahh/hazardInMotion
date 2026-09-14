@extends('pnc-monitoring.layouts.app')

@section('title', 'Dashboard Pengawas')

@section('content')
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-24">
  <div>
    <h4 class="mb-4">Performance Dashboard Pengawas Teknis</h4>
    <p class="text-secondary-light mb-0">Permit &amp; Compliance · Commissioning / SPIP</p>
  </div>
  <div class="text-end text-sm">
    <div><span class="text-success-main">●</span> LIVE / auto-refresh 60 detik</div>
    <div id="pgw-loaded-at">Dimuat: {{ $payload['meta']['generatedAt'] ?? '-' }}</div>
  </div>
</div>

<div class="card shadow-none border mb-24">
  <div class="card-body">
    <form id="pgw-filter-form" class="row g-3 align-items-end">
      @foreach ([
        ['year', 'Tahun', $payload['options']['years'] ?? []],
        ['week', 'Minggu', $payload['options']['weeks'] ?? []],
        ['site', 'Site', $payload['options']['sites'] ?? []],
        ['company', 'Perusahaan', $payload['options']['companies'] ?? []],
        ['detail', 'Detail Jenis SPIP', $payload['options']['details'] ?? []],
      ] as [$id, $label, $opts])
        <div class="col-md-2">
          <label class="form-label text-xs text-secondary-light text-uppercase" for="p-{{ $id }}">{{ $label }}</label>
          <select class="form-select form-select-sm" id="p-{{ $id }}" name="{{ $id }}">
            <option value="ALL">Semua</option>
            @foreach ($opts as $opt)
              <option value="{{ $opt }}" @selected(($payload['filters'][$id] ?? 'ALL') == $opt)>{{ $opt }}</option>
            @endforeach
          </select>
        </div>
      @endforeach
      <div class="col-md-2">
        <button type="button" id="pgw-reset" class="btn btn-outline-secondary btn-sm w-100">Reset</button>
      </div>
    </form>
  </div>
</div>

<div class="row gy-4 mb-24" id="pgw-kpi-grid"></div>

<div class="row gy-4 mb-24">
  <div class="col-xl-6">
    <div class="card border shadow-none h-100">
      <div class="card-header bg-primary-600 text-white"><strong>TOP 10 PENGAWAS</strong></div>
      <div class="card-body p-0"><div class="table-responsive" id="pgw-top-table"></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card border shadow-none h-100">
      <div class="card-header bg-danger-main text-white"><strong>BOTTOM 10 PENGAWAS</strong></div>
      <div class="card-body p-0"><div class="table-responsive" id="pgw-bottom-table"></div></div>
    </div>
  </div>
  <div class="col-xl-6"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Unit Commissioning per Site</h6><div id="chart-pgw-units"></div></div></div></div>
  <div class="col-xl-6"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Performance per Site</h6><div id="chart-pgw-perf"></div></div></div></div>
  <div class="col-xl-6"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">SKO Release &amp; Reject per Site</h6><div id="chart-pgw-sko"></div></div></div></div>
  <div class="col-xl-6"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Alasan Reject</h6><div id="chart-pgw-reject"></div></div></div></div>
  <div class="col-12"><div class="card border shadow-none"><div class="card-body"><h6 class="mb-12">Trend Mingguan</h6><div id="chart-pgw-trend"></div></div></div></div>
</div>
@endsection

@section('scripts')
<script>
(() => {
  const dataUrl = @json($dataUrl);
  const initial = @json($payload);
  const charts = {};
  const pct = (v) => (v === null || v === undefined) ? 'N/A' : (v * 100).toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + '%';
  const num = (v) => Number(v || 0).toLocaleString('id-ID');

  function filters() {
    return {
      year: document.getElementById('p-year').value,
      week: document.getElementById('p-week').value,
      site: document.getElementById('p-site').value,
      company: document.getElementById('p-company').value,
      detail: document.getElementById('p-detail').value,
    };
  }

  function renderKpis(k) {
    const cards = [
      ['Jumlah Commissioning', num(k.totalUnits), 'Aktivitas terfilter'],
      ['SKO Release', num(k.releaseCount), 'Status mengandung Release'],
      ['Performance Pengawas', pct(k.performance), num(k.status1) + ' tanpa temuan / ' + num((k.status1||0)+(k.status0||0))],
      ['SKO Reject', num(k.rejectCount), 'Status mengandung Reject'],
      ['Finding Verlap', num(k.totalFindings), 'Total temuan'],
      ['Total Pengawas', num(k.totalSupervisors), 'Nama unik'],
      ['Pengawas Perform', num(k.performSupervisors), 'Tanpa temuan di semua unit'],
      ['Pengawas Tidak Perform', num(k.notPerformSupervisors), 'Minimal satu unit bertemuan'],
      ['Unit Tanpa Temuan', num(k.status1), 'Temuan = 0'],
      ['Unit Dengan Temuan', num(k.status0), 'Temuan > 0'],
    ];
    document.getElementById('pgw-kpi-grid').innerHTML = cards.map(([label, value, sub]) => `
      <div class="col-xxl-3 col-sm-6">
        <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
          <span class="text-sm text-secondary-light">${label}</span>
          <h5 class="mb-0 fw-bold">${value}</h5>
          <span class="text-xs text-secondary-light">${sub}</span>
        </div>
      </div>`).join('');
  }

  function rankTable(rows) {
    if (!rows || !rows.length) return '<div class="p-16 text-center text-secondary-light">Tidak ada data.</div>';
    return `<table class="table bordered-table mb-0"><thead><tr>
      <th>Rank</th><th>Nama</th><th>Perusahaan</th><th>Site</th><th>Unit</th><th>Temuan</th><th>Perf</th>
    </tr></thead><tbody>${rows.map((r,i)=>`<tr>
      <td>${i+1}</td><td>${r.name}</td><td>${r.company||'-'}</td><td>${r.site||'-'}</td>
      <td>${num(r.units)}</td><td>${num(r.findings)}</td><td>${pct(r.performance)}</td>
    </tr>`).join('')}</tbody></table>`;
  }

  function drawBar(id, categories, series, horizontal=false) {
    if (charts[id]) charts[id].destroy();
    charts[id] = new ApexCharts(document.querySelector(id), {
      chart: { type: 'bar', height: 300, toolbar: { show: false } },
      series, xaxis: { categories },
      plotOptions: { bar: { horizontal, borderRadius: 4 } },
      dataLabels: { enabled: false },
      colors: ['#487FFF', '#F86624', '#45B369'],
    });
    charts[id].render();
  }

  function renderCharts(d) {
    const sites = d.bySite || [];
    drawBar('#chart-pgw-units', sites.map(x=>x.site), [{name:'Unit', data: sites.map(x=>x.units)}]);
    drawBar('#chart-pgw-perf', sites.map(x=>x.site), [{name:'Performance %', data: sites.map(x=>+((x.performance||0)*100).toFixed(1))}]);
    drawBar('#chart-pgw-sko', sites.map(x=>x.site), [
      {name:'Release', data: sites.map(x=>x.release)},
      {name:'Reject', data: sites.map(x=>x.reject)},
    ], true);
    const rr = d.rejectReasons || [];
    drawBar('#chart-pgw-reject', rr.map(x=>x.label), [{name:'Reject', data: rr.map(x=>x.value)}], true);
    const w = d.weeklyTrend || [];
    if (charts['#chart-pgw-trend']) charts['#chart-pgw-trend'].destroy();
    charts['#chart-pgw-trend'] = new ApexCharts(document.querySelector('#chart-pgw-trend'), {
      chart: { type: 'line', height: 300, toolbar: { show: false } },
      series: [
        { name: 'Unit', type: 'column', data: w.map(x=>x.units) },
        { name: 'Performance %', type: 'line', data: w.map(x=>+((x.performance||0)*100).toFixed(1)) },
      ],
      xaxis: { categories: w.map(x=>x.week) },
      yaxis: [{ title: { text: 'Unit' } }, { opposite: true, max: 100, title: { text: '%' } }],
      colors: ['#45B369', '#F86624'],
      dataLabels: { enabled: false },
    });
    charts['#chart-pgw-trend'].render();
  }

  function render(d) {
    document.getElementById('pgw-loaded-at').textContent = 'Dimuat: ' + (d.meta?.generatedAt || '-');
    renderKpis(d.kpis || {});
    document.getElementById('pgw-top-table').innerHTML = rankTable(d.top10 || []);
    document.getElementById('pgw-bottom-table').innerHTML = rankTable(d.bottom10 || []);
    renderCharts(d);
  }

  async function load() {
    const qs = new URLSearchParams(filters()).toString();
    const res = await fetch(dataUrl + '?' + qs, { headers: { 'Accept': 'application/json' } });
    render(await res.json());
  }

  document.querySelectorAll('#pgw-filter-form select').forEach(el => el.addEventListener('change', load));
  document.getElementById('pgw-reset').addEventListener('click', () => {
    document.querySelectorAll('#pgw-filter-form select').forEach(el => el.value = 'ALL');
    load();
  });
  render(initial);
  setInterval(load, 60000);
})();
</script>
@endsection
