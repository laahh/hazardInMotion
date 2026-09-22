@extends('pnc-monitoring.layouts.app')

@section('title', 'Dashboard Utama')

@section('content')
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-24">
  <div>
    <h4 class="mb-4">Dashboard Utama PNC Monitoring</h4>
    <p class="text-secondary-light mb-0">Ringkasan gabungan performa IKK &amp; Commissioning (SPIP) lintas site</p>
  </div>
  <div class="text-end text-sm">
    <div><span class="text-success-main">●</span> LIVE / auto-refresh 60 detik</div>
    <div id="main-loaded-at">Dimuat: {{ $payload['meta']['generatedAt'] ?? '-' }}</div>
  </div>
</div>

<div class="card shadow-none border mb-24">
  <div class="card-body">
    <form id="main-filter-form" class="row g-3 align-items-end">
      @foreach ([
        ['year', 'Tahun', $payload['options']['years'] ?? []],
        ['site', 'Site', $payload['options']['sites'] ?? []],
      ] as [$id, $label, $opts])
        <div class="col-md-3">
          <label class="form-label text-xs text-secondary-light text-uppercase" for="f-{{ $id }}">{{ $label }}</label>
          <select class="form-select form-select-sm" id="f-{{ $id }}" name="{{ $id }}">
            <option value="ALL">Semua</option>
            @foreach ($opts as $opt)
              <option value="{{ $opt }}" @selected(($payload['filters'][$id] ?? 'ALL') == $opt)>{{ $opt }}</option>
            @endforeach
          </select>
        </div>
      @endforeach
      <div class="col-md-2">
        <button type="button" id="main-reset" class="btn btn-outline-secondary btn-sm w-100">Reset</button>
      </div>
    </form>
  </div>
</div>

<div class="mb-12">
  <h6 class="text-uppercase text-secondary-light text-sm mb-12">Modul IKK</h6>
  <div class="row gy-4" id="main-kpi-ikk"></div>
</div>

<div class="mb-24 mt-24">
  <h6 class="text-uppercase text-secondary-light text-sm mb-12">Modul Commissioning</h6>
  <div class="row gy-4" id="main-kpi-commissioning"></div>
</div>

<div class="row gy-4 mb-24">
  <div class="col-xl-6"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Volume IKK vs Unit Commissioning per Site</h6><div id="chart-volume-site"></div></div></div></div>
  <div class="col-xl-6"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">IPK IKK vs Performance Commissioning per Site</h6><div id="chart-performance-site"></div></div></div></div>
  <div class="col-xl-8"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Trend Mingguan Gabungan</h6><div id="chart-trend-combined"></div></div></div></div>
  <div class="col-xl-4"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Top 5 Pengawas Teknis</h6><div id="chart-top-pengawas"></div></div></div></div>
</div>

<div class="card border shadow-none">
  <div class="card-body">
    <h6 class="mb-12">Perbandingan per Site</h6>
    <div class="table-responsive">
      <table class="table bordered-table mb-0" id="main-site-table">
        <thead>
          <tr>
            <th>Site</th><th>Jumlah IKK</th><th>IPK Performance</th><th>IA Performance</th><th>Unit Commissioning</th><th>Commissioning Performance</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
  const dataUrl = @json($dataUrl);
  const initial = @json($payload);
  const charts = {};

  const pct = (v) => (v === null || v === undefined || !isFinite(v)) ? 'N/A' : (v * 100).toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + '%';
  const num = (v) => Number(v || 0).toLocaleString('id-ID');
  const perfClass = (v) => v === null || v === undefined ? '' : (v >= 0.9995 ? 'text-success-main' : (v >= 0.9 ? 'text-warning-main' : 'text-danger-main'));

  function filters() {
    return {
      year: document.getElementById('f-year').value,
      site: document.getElementById('f-site').value,
    };
  }

  function kpiCard(label, value, sub, bg) {
    return `
      <div class="col-xxl-3 col-sm-6">
        <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
          <div class="d-flex align-items-center gap-2">
            <span class="w-48-px h-48-px ${bg} text-white d-flex justify-content-center align-items-center rounded-circle"></span>
            <div>
              <span class="text-sm text-secondary-light">${label}</span>
              <h5 class="mb-0 fw-bold">${value}</h5>
              <span class="text-xs text-secondary-light">${sub}</span>
            </div>
          </div>
        </div>
      </div>`;
  }

  function renderKpis(d) {
    const ikk = d.ikk?.kpis || {};
    document.getElementById('main-kpi-ikk').innerHTML = [
      kpiCard('Jumlah IKK', num(ikk.ikkCount), num(ikk.distinctIkk) + ' nomor unik', 'bg-primary-600'),
      kpiCard('IPK Performance', pct(ikk.ipkPerformance), num(ikk.ipkActual) + ' / ' + num(ikk.ipkDenominator), 'bg-success-main'),
      kpiCard('IA Performance', pct(ikk.iaPerformance), num(ikk.iaEffective) + ' efektif', 'bg-purple'),
      kpiCard('OKK Layer 2 Up', pct(ikk.okkL2UpPerformance), num(ikk.layer2Achieved) + ' / ' + num(ikk.layer2Required), 'bg-warning-main'),
    ].join('');

    const c = d.commissioning?.kpis || {};
    document.getElementById('main-kpi-commissioning').innerHTML = [
      kpiCard('Total Unit', num(c.totalUnits), num(c.totalFindings) + ' total temuan', 'bg-info'),
      kpiCard('Performance', pct(c.performance), num(c.status1) + ' / ' + num(c.status1 + c.status0), 'bg-success-main'),
      kpiCard('SKO Release', num(c.releaseCount), 'Status release', 'bg-primary-600'),
      kpiCard('SKO Reject', num(c.rejectCount), 'Status reject', 'bg-danger-main'),
    ].join('');
  }

  function renderTable(rows) {
    const tbody = document.querySelector('#main-site-table tbody');
    tbody.innerHTML = (rows || []).map(r => `
      <tr>
        <td><strong>${r.site}</strong></td>
        <td>${num(r.ikkCount)}</td>
        <td class="${perfClass(r.ipkPerformance)}">${pct(r.ipkPerformance)}</td>
        <td class="${perfClass(r.iaPerformance)}">${pct(r.iaPerformance)}</td>
        <td>${num(r.commissioningUnits)}</td>
        <td class="${perfClass(r.commissioningPerformance)}">${pct(r.commissioningPerformance)}</td>
      </tr>`).join('') || '<tr><td colspan="6" class="text-center text-secondary-light">Tidak ada data.</td></tr>';
  }

  function drawChart(id, options) {
    if (charts[id]) charts[id].destroy();
    charts[id] = new ApexCharts(document.querySelector(id), options);
    charts[id].render();
  }

  function renderCharts(d) {
    const sites = d.combinedBySite || [];

    drawChart('#chart-volume-site', {
      chart: { type: 'bar', height: 320, toolbar: { show: false } },
      series: [
        { name: 'Jumlah IKK', data: sites.map(x => x.ikkCount || 0) },
        { name: 'Unit Commissioning', data: sites.map(x => x.commissioningUnits || 0) },
      ],
      xaxis: { categories: sites.map(x => x.site) },
      plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
      dataLabels: { enabled: false },
      colors: ['#487FFF', '#45B369'],
    });

    drawChart('#chart-performance-site', {
      chart: { type: 'bar', height: 320, toolbar: { show: false } },
      series: [
        { name: 'IPK IKK', data: sites.map(x => +((x.ipkPerformance || 0) * 100).toFixed(1)) },
        { name: 'Performance Commissioning', data: sites.map(x => +((x.commissioningPerformance || 0) * 100).toFixed(1)) },
      ],
      xaxis: { categories: sites.map(x => x.site), max: 100, labels: { formatter: (v) => v + '%' } },
      plotOptions: { bar: { horizontal: true, borderRadius: 4, columnWidth: '55%' } },
      dataLabels: { enabled: false },
      colors: ['#F86624', '#8C62FF'],
    });

    const trend = d.combinedTrend || [];
    drawChart('#chart-trend-combined', {
      chart: { type: 'line', height: 340, toolbar: { show: false } },
      series: [
        { name: 'Jumlah IKK', type: 'column', data: trend.map(x => x.ikkCount || 0) },
        { name: 'Unit Commissioning', type: 'column', data: trend.map(x => x.commissioningUnits || 0) },
        { name: 'IPK IKK %', type: 'line', data: trend.map(x => +((x.ipkPerformance || 0) * 100).toFixed(1)) },
        { name: 'Performance Commissioning %', type: 'line', data: trend.map(x => +((x.commissioningPerformance || 0) * 100).toFixed(1)) },
      ],
      xaxis: { categories: trend.map(x => x.period) },
      yaxis: [
        { title: { text: 'Volume' } },
        { title: { text: 'Volume' }, show: false },
        { opposite: true, max: 100, title: { text: 'Performance %' } },
        { opposite: true, max: 100, show: false },
      ],
      colors: ['#487FFF', '#45B369', '#F86624', '#8C62FF'],
      dataLabels: { enabled: false },
      stroke: { width: [0, 0, 3, 3] },
    });

    const top = (d.commissioning?.top10 || []).slice(0, 5);
    drawChart('#chart-top-pengawas', {
      chart: { type: 'bar', height: 340, toolbar: { show: false } },
      series: [{ name: 'Performance', data: top.map(x => +((x.performance || 0) * 100).toFixed(1)) }],
      xaxis: { categories: top.map(x => x.name), max: 100, labels: { formatter: (v) => v + '%' } },
      plotOptions: { bar: { horizontal: true, borderRadius: 4, columnWidth: '55%' } },
      dataLabels: { enabled: false },
      colors: ['#45B369'],
    });
  }

  function render(d) {
    document.getElementById('main-loaded-at').textContent = 'Dimuat: ' + (d.meta?.generatedAt || '-');
    renderKpis(d);
    renderTable(d.combinedBySite || []);
    renderCharts(d);
  }

  async function load() {
    const qs = new URLSearchParams(filters()).toString();
    const res = await fetch(dataUrl + '?' + qs, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    render(data);
  }

  document.querySelectorAll('#main-filter-form select').forEach(el => el.addEventListener('change', load));
  document.getElementById('main-reset').addEventListener('click', () => {
    document.querySelectorAll('#main-filter-form select').forEach(el => el.value = 'ALL');
    load();
  });

  render(initial);
  setInterval(load, 60000);
})();
</script>
@endsection
