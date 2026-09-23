@extends('pnc-monitoring.layouts.app')

@section('title', 'Dashboard Inventory Tools')

@section('content')
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-24">
  <div>
    <h4 class="mb-4">Dashboard Inventory Tools</h4>
    <p class="text-secondary-light mb-0">Monitoring unit aset, status ketersediaan, kondisi &amp; jadwal kalibrasi/inspeksi</p>
  </div>
  <div class="text-end text-sm">
    <div><span class="text-success-main">●</span> LIVE / auto-refresh 60 detik</div>
    <div id="inv-loaded-at">Dimuat: {{ $payload['meta']['generatedAt'] ?? '-' }}</div>
  </div>
</div>

<div class="card shadow-none border mb-24">
  <div class="card-body">
    <form id="inv-filter-form" class="row g-3 align-items-end">
      @foreach ([
        ['category', 'Kategori', $payload['options']['categories'] ?? []],
        ['site', 'Site', $payload['options']['sites'] ?? []],
        ['status', 'Status', $payload['options']['statuses'] ?? []],
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
      <div class="col-md-3">
        <button type="button" id="inv-reset" class="btn btn-outline-secondary btn-sm w-100">Reset</button>
      </div>
    </form>
  </div>
</div>

<div class="row gy-4 mb-24" id="inv-kpi-grid"></div>

<div class="row gy-4 mb-24">
  <div class="col-xl-4"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Jumlah per Kategori</h6><div id="chart-category"></div></div></div></div>
  <div class="col-xl-4"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Status Ketersediaan</h6><div id="chart-status"></div></div></div></div>
  <div class="col-xl-4"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Kondisi Alat</h6><div id="chart-condition"></div></div></div></div>
  <div class="col-xl-12"><div class="card border shadow-none h-100"><div class="card-body"><h6 class="mb-12">Jumlah per Site</h6><div id="chart-site"></div></div></div></div>
</div>

<div class="card border shadow-none">
  <div class="card-body">
    <h6 class="mb-12">Jatuh Tempo Kalibrasi / Inspeksi (30 hari ke depan)</h6>
    <div class="table-responsive">
      <table class="table bordered-table mb-0" id="inv-due-table">
        <thead>
          <tr><th>Nama Alat</th><th>Asset ID</th><th>Kategori</th><th>Site</th><th>Jatuh Tempo</th></tr>
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

  const num = (v) => Number(v || 0).toLocaleString('id-ID');

  function filters() {
    return {
      category: document.getElementById('f-category').value,
      site: document.getElementById('f-site').value,
      status: document.getElementById('f-status').value,
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

  function renderKpis(k) {
    document.getElementById('inv-kpi-grid').innerHTML = [
      kpiCard('Total Unit Aset', num(k.totalTools), 'Seluruh unit terdaftar', 'bg-primary-600'),
      kpiCard('Available', num(k.available), 'Siap dipakai', 'bg-success-main'),
      kpiCard('Checked-out / In Repair', num(k.checkedOut) + ' / ' + num(k.inRepair), 'Sedang dipakai / diperbaiki', 'bg-warning-main'),
      kpiCard('Damaged / Scrapped', num(k.damaged) + ' / ' + num(k.scrapped), 'Kondisi rusak / afkir', 'bg-danger-main'),
      kpiCard('Kalibrasi Overdue', num(k.calibrationOverdue), num(k.calibrationDueSoon) + ' due 30 hari', 'bg-danger-main'),
      kpiCard('Inspeksi Overdue', num(k.inspectionOverdue), num(k.inspectionDueSoon) + ' due 30 hari', 'bg-warning-main'),
    ].join('');
  }

  function renderTable(rows) {
    const tbody = document.querySelector('#inv-due-table tbody');
    tbody.innerHTML = (rows || []).map(r => `
      <tr class="${r.isOverdue ? 'text-danger-main' : ''}">
        <td><strong>${r.name}</strong></td>
        <td>${r.assetId || '-'}</td>
        <td>${r.category || '-'}</td>
        <td>${r.site || '-'}</td>
        <td>${r.dueDate || '-'} ${r.isOverdue ? '(overdue)' : ''}</td>
      </tr>`).join('') || '<tr><td colspan="5" class="text-center text-secondary-light">Tidak ada jadwal jatuh tempo.</td></tr>';
  }

  function drawChart(id, options) {
    if (charts[id]) charts[id].destroy();
    charts[id] = new ApexCharts(document.querySelector(id), options);
    charts[id].render();
  }

  function renderCharts(d) {
    const cat = d.byCategory || [];
    drawChart('#chart-category', {
      chart: { type: 'bar', height: 280, toolbar: { show: false } },
      series: [{ name: 'Jumlah', data: cat.map(x => x.value) }],
      xaxis: { categories: cat.map(x => x.name) },
      plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
      dataLabels: { enabled: false },
      colors: ['#487FFF'],
    });

    const status = d.byStatus || [];
    drawChart('#chart-status', {
      chart: { type: 'donut', height: 280 },
      series: status.map(x => x.value),
      labels: status.map(x => x.name),
      colors: ['#45B369', '#F86624', '#8C62FF', '#F8285A', '#ADB5BD'],
    });

    const cond = d.byCondition || [];
    drawChart('#chart-condition', {
      chart: { type: 'donut', height: 280 },
      series: cond.map(x => x.value),
      labels: cond.map(x => x.name),
      colors: ['#45B369', '#487FFF', '#F86624', '#F8285A'],
    });

    const sites = d.bySite || [];
    drawChart('#chart-site', {
      chart: { type: 'bar', height: 300, toolbar: { show: false } },
      series: [{ name: 'Jumlah', data: sites.map(x => x.value) }],
      xaxis: { categories: sites.map(x => x.name) },
      plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
      dataLabels: { enabled: false },
      colors: ['#45B369'],
    });
  }

  function render(d) {
    document.getElementById('inv-loaded-at').textContent = 'Dimuat: ' + (d.meta?.generatedAt || '-');
    renderKpis(d.kpis || {});
    renderTable(d.dueSoon || []);
    renderCharts(d);
  }

  async function load() {
    const qs = new URLSearchParams(filters()).toString();
    const res = await fetch(dataUrl + '?' + qs, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    render(data);
  }

  document.querySelectorAll('#inv-filter-form select').forEach(el => el.addEventListener('change', load));
  document.getElementById('inv-reset').addEventListener('click', () => {
    document.querySelectorAll('#inv-filter-form select').forEach(el => el.value = 'ALL');
    load();
  });

  render(initial);
  setInterval(load, 60000);
})();
</script>
@endsection
