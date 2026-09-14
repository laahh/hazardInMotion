@extends('pnc-monitoring.layouts.app')

@section('title', 'Dashboard IKK')

@section('content')
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-24">
  <div>
    <h4 class="mb-4">Performance Dashboard IKK</h4>
    <p class="text-secondary-light mb-0">Permit &amp; Compliance · Izin Kerja Khusus</p>
  </div>
  <div class="text-end text-sm">
    <div><span class="text-success-main">●</span> LIVE / auto-refresh 60 detik</div>
    <div id="ikk-loaded-at">Dimuat: {{ $payload['meta']['generatedAt'] ?? '-' }}</div>
    <div id="ikk-last-data">Data terakhir: {{ $payload['meta']['lastDataDate'] ?? '-' }}</div>
  </div>
</div>

<div class="card shadow-none border mb-24">
  <div class="card-body">
    <form id="ikk-filter-form" class="row g-3 align-items-end">
      @foreach ([
        ['year', 'Tahun', $payload['options']['years'] ?? []],
        ['month', 'Bulan', $payload['options']['months'] ?? []],
        ['week', 'Minggu', $payload['options']['weeks'] ?? []],
        ['site', 'Site', $payload['options']['sites'] ?? []],
        ['company', 'Perusahaan', $payload['options']['companies'] ?? []],
        ['type', 'Jenis IKK', $payload['options']['types'] ?? []],
      ] as [$id, $label, $opts])
        <div class="col-md-2">
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
        <button type="button" id="ikk-reset" class="btn btn-outline-secondary btn-sm w-100">Reset</button>
      </div>
    </form>
  </div>
</div>

<div class="row gy-4 mb-24" id="ikk-kpi-grid"></div>

<div class="row gy-4 mb-24">
  <div class="col-xl-6"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Jumlah IKK per Site</h6><div id="chart-ikk-site"></div></div></div></div>
  <div class="col-xl-6"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">IPK Performance per Site</h6><div id="chart-ipk-site"></div></div></div></div>
  <div class="col-xl-6"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">IA Performance per Site</h6><div id="chart-ia-site"></div></div></div></div>
  <div class="col-xl-6"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">OKK L1 &amp; L2 Up per Site</h6><div id="chart-okk-site"></div></div></div></div>
  <div class="col-xl-6"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Trend Mingguan</h6><div id="chart-trend"></div></div></div></div>
  <div class="col-xl-6"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Finding IA &amp; Verlap per Site</h6><div id="chart-finding-site"></div></div></div></div>
</div>

<div class="card border shadow-none">
  <div class="card-body">
    <h6 class="mb-12">Performance per Site</h6>
    <div class="table-responsive">
      <table class="table bordered-table mb-0" id="ikk-site-table">
        <thead>
          <tr>
            <th>Site</th><th>IKK</th><th>Cancel</th><th>IPK</th><th>IA Comp</th><th>IA Perf</th><th>Finding IA</th><th>Finding Verlap</th><th>OKK L1</th><th>OKK L2 Up</th>
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
      month: document.getElementById('f-month').value,
      week: document.getElementById('f-week').value,
      site: document.getElementById('f-site').value,
      company: document.getElementById('f-company').value,
      type: document.getElementById('f-type').value,
    };
  }

  function renderKpis(k) {
    const cards = [
      ['Jumlah IKK', num(k.ikkCount), num(k.distinctIkk) + ' nomor unik', 'bg-primary-600'],
      ['IPK Performance', pct(k.ipkPerformance), num(k.ipkActual) + ' / ' + num(k.ipkDenominator), 'bg-success-main'],
      ['Cancel IKK/IPK', num(k.cancelCount), 'IPK = 0 atau blank', 'bg-yellow'],
      ['IA Compliance', pct(k.iaCompliance), num(k.iaActual) + ' / ' + num(k.iaRequired), 'bg-info'],
      ['IA Performance', pct(k.iaPerformance), num(k.iaEffective) + ' efektif · ' + num(k.iaPenaltyCases) + ' gap', 'bg-purple'],
      ['Finding IA', num(k.findingIA), 'Total finding IA', 'bg-pink'],
      ['Finding Verlap', num(k.findingVerlap), 'Total finding Verlap', 'bg-danger-main'],
      ['OKK Layer 1', pct(k.okkL1Performance), num(k.okkAchieved) + ' / ' + num(k.okkPlan), 'bg-primary-600'],
      ['OKK Layer 2 Up', pct(k.okkL2UpPerformance), num(k.layer2Achieved) + ' / ' + num(k.layer2Required), 'bg-success-main'],
    ];
    document.getElementById('ikk-kpi-grid').innerHTML = cards.map(([label, value, sub, bg]) => `
      <div class="col-xxl-4 col-sm-6">
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
      </div>`).join('');
  }

  function renderTable(rows) {
    const tbody = document.querySelector('#ikk-site-table tbody');
    tbody.innerHTML = (rows || []).map(r => `
      <tr>
        <td><strong>${r.name}</strong></td>
        <td>${num(r.ikkCount)}</td>
        <td>${num(r.cancelCount)}</td>
        <td class="${perfClass(r.ipkPerformance)}">${pct(r.ipkPerformance)}</td>
        <td class="${perfClass(r.iaCompliance)}">${pct(r.iaCompliance)}</td>
        <td class="${perfClass(r.iaPerformance)}">${pct(r.iaPerformance)}</td>
        <td>${num(r.findingIA)}</td>
        <td>${num(r.findingVerlap)}</td>
        <td class="${perfClass(r.okkL1Performance)}">${pct(r.okkL1Performance)}</td>
        <td class="${perfClass(r.okkL2UpPerformance)}">${pct(r.okkL2UpPerformance)}</td>
      </tr>`).join('') || '<tr><td colspan="10" class="text-center text-secondary-light">Tidak ada data.</td></tr>';
  }

  function drawBar(id, categories, series, horizontal = false) {
    if (charts[id]) charts[id].destroy();
    charts[id] = new ApexCharts(document.querySelector(id), {
      chart: { type: 'bar', height: 320, toolbar: { show: false } },
      series,
      xaxis: horizontal ? { categories, labels: { formatter: (v) => typeof v === 'number' && v <= 1 ? pct(v) : v } } : { categories },
      yaxis: horizontal ? {} : { labels: { formatter: (v) => Number.isInteger(v) ? v : pct(v) } },
      plotOptions: { bar: { horizontal, borderRadius: 4, columnWidth: '55%' } },
      dataLabels: { enabled: false },
      colors: ['#487FFF', '#45B369', '#F86624'],
    });
    charts[id].render();
  }

  function renderCharts(d) {
    const sites = d.bySite || [];
    drawBar('#chart-ikk-site', sites.map(x => x.name), [{ name: 'IKK', data: sites.map(x => x.ikkCount) }]);
    drawBar('#chart-ipk-site', sites.map(x => x.name), [{ name: 'IPK', data: sites.map(x => +(x.ipkPerformance || 0).toFixed(4)) }], true);
    drawBar('#chart-ia-site', sites.map(x => x.name), [{ name: 'IA Perf', data: sites.map(x => +(x.iaPerformance || 0).toFixed(4)) }], true);
    drawBar('#chart-okk-site', sites.map(x => x.name), [
      { name: 'OKK L1', data: sites.map(x => +(x.okkL1Performance || 0).toFixed(4)) },
      { name: 'OKK L2 Up', data: sites.map(x => +(x.okkL2UpPerformance || 0).toFixed(4)) },
    ]);
    const trend = d.trend || [];
    if (charts['#chart-trend']) charts['#chart-trend'].destroy();
    charts['#chart-trend'] = new ApexCharts(document.querySelector('#chart-trend'), {
      chart: { type: 'line', height: 320, toolbar: { show: false } },
      series: [
        { name: 'Jumlah IKK', type: 'column', data: trend.map(x => x.ikkCount) },
        { name: 'IPK Performance', type: 'line', data: trend.map(x => +((x.ipkPerformance || 0) * 100).toFixed(1)) },
      ],
      xaxis: { categories: trend.map(x => x.period) },
      yaxis: [
        { title: { text: 'IKK' } },
        { opposite: true, max: 100, title: { text: 'IPK %' } },
      ],
      colors: ['#487FFF', '#F86624'],
      dataLabels: { enabled: false },
    });
    charts['#chart-trend'].render();
    drawBar('#chart-finding-site', sites.map(x => x.name), [
      { name: 'Finding IA', data: sites.map(x => x.findingIA || 0) },
      { name: 'Finding Verlap', data: sites.map(x => x.findingVerlap || 0) },
    ]);
  }

  function render(d) {
    document.getElementById('ikk-loaded-at').textContent = 'Dimuat: ' + (d.meta?.generatedAt || '-');
    document.getElementById('ikk-last-data').textContent = 'Data terakhir: ' + (d.meta?.lastDataDate || '-');
    renderKpis(d.kpis || {});
    renderTable(d.bySite || []);
    renderCharts(d);
  }

  async function load() {
    const qs = new URLSearchParams(filters()).toString();
    const res = await fetch(dataUrl + '?' + qs, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    render(data);
  }

  document.querySelectorAll('#ikk-filter-form select').forEach(el => el.addEventListener('change', load));
  document.getElementById('ikk-reset').addEventListener('click', () => {
    document.querySelectorAll('#ikk-filter-form select').forEach(el => el.value = 'ALL');
    load();
  });

  render(initial);
  setInterval(load, 60000);
})();
</script>
@endsection
