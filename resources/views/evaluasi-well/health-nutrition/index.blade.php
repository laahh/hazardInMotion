@extends('evaluasi-well.layouts.app')

@section('title', 'MCU x Nutrisi')

@section('css')
<style>
  /* ===== MCU x Nutrisi — dashboard baru (data dummy) ===== */
  .hn-tabs {
    display: inline-flex;
    background: #F1F5F9;
    border-radius: 10px;
    padding: 4px;
    gap: 4px;
  }
  .hn-tabs .nav-link {
    border: none;
    border-radius: 8px;
    padding: 7px 18px;
    font-size: 13px;
    font-weight: 600;
    color: #64748B;
    background: transparent;
  }
  .hn-tabs .nav-link.active {
    background: #fff;
    color: #0F172A;
    box-shadow: 0 1px 4px rgba(15,23,42,.12);
  }

  .hn-banner {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    background: linear-gradient(120deg, #14532D 0%, #16A34A 55%, #22C55E 100%);
    padding: 22px 28px;
    color: #fff;
    min-height: 96px;
    display: flex;
    align-items: center;
  }
  .hn-banner__bg {
    position: absolute;
    right: -10px;
    top: 50%;
    transform: translateY(-50%);
    width: min(42%, 260px);
    border-radius: 14px;
    opacity: 0.92;
    box-shadow: 0 12px 30px rgba(0,0,0,.25);
  }
  .hn-banner__text { position: relative; z-index: 1; max-width: 62%; }
  .hn-banner__text h6 { font-size: 18px; font-weight: 700; margin-bottom: 2px; }
  .hn-banner__text span { font-size: 13px; opacity: .9; }

  .hn-kpi-card {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 18px 20px;
    background: #fff;
    height: 100%;
  }
  .hn-kpi-card__icon {
    width: 42px; height: 42px; border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 20px; color: #fff; flex-shrink: 0;
  }
  .hn-kpi-card__value { font-size: 24px; font-weight: 700; color: #0F172A; line-height: 1.2; }
  .hn-kpi-card__sub { font-size: 12px; color: #64748B; }
  .hn-kpi-card__delta { font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 3px; }
  .hn-kpi-card__delta--up { color: #16A34A; }
  .hn-kpi-card__spark { height: 32px; width: 84px; }
  .bg-primary-soft { background: #487FFF; }
  .bg-danger-soft { background: #EF4444; }
  .bg-success-soft { background: #16A34A; }
  .bg-info-soft { background: #0EA5E9; }

  .hn-condition-card {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 14px 16px;
    background: #fff;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .hn-condition-card__icon {
    width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    color: #fff; font-size: 18px;
  }
  .hn-condition-card__value { font-size: 19px; font-weight: 700; color: #0F172A; line-height: 1.2; }
  .hn-condition-card__label { font-size: 12.5px; font-weight: 600; color: #334155; }
  .hn-condition-card__sub { font-size: 11px; color: #94A3B8; }
  .hn-condition-card__delta { font-size: 11.5px; font-weight: 600; color: #16A34A; }

  .hn-card {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    background: #fff;
    height: 100%;
  }
  .hn-card__head { padding: 18px 20px 0; }
  .hn-card__title { font-size: 15px; font-weight: 700; color: #0F172A; margin: 0; }
  .hn-card__subtitle { font-size: 12.5px; color: #64748B; }
  .hn-card__body { padding: 16px 20px 20px; }

  #hnHeatmap .apexcharts-tooltip { font-size: 12px; }

  .hn-corr-table { width: 100%; border-collapse: separate; border-spacing: 4px; font-size: 12px; }
  .hn-corr-table th { font-weight: 600; color: #475569; text-align: center; padding: 6px 4px; font-size: 11.5px; }
  .hn-corr-table th:first-child { text-align: left; }
  .hn-corr-table td.hn-corr-row-label { text-align: left; font-weight: 600; color: #334155; white-space: nowrap; padding-right: 10px; font-size: 12px; }
  .hn-corr-table td.hn-corr-cell { border-radius: 6px; height: 34px; }

  .hn-macro-tab {
    border: 1px solid #E2E8F0;
    background: #fff;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 999px;
  }
  .hn-macro-tab.active { background: #16A34A; border-color: #16A34A; color: #fff; }

  .hn-analysis-card {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    background: #fff;
    padding: 18px 20px;
    height: 100%;
  }
  .hn-analysis-card__title { font-size: 14px; font-weight: 700; color: #0F172A; }
  .hn-analysis-card__legend-item { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #334155; }
  .hn-analysis-card__dot { width: 22px; height: 22px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; flex-shrink: 0; }
  .hn-analysis-card__insight { background: #F0FDF4; border: 1px solid #BBF7D0; color: #166534; font-size: 12px; border-radius: 8px; padding: 8px 10px; }

  .hn-bottom-banner {
    border-radius: 16px;
    background: linear-gradient(120deg, #052e1c 0%, #14532D 45%, #16A34A 100%);
    color: #fff;
    padding: 26px 30px;
  }
  .hn-bottom-banner h6 { font-size: 18px; font-weight: 700; margin-bottom: 2px; }
  .hn-bottom-banner .hn-bb-item { text-align: center; min-width: 110px; }
  .hn-bottom-banner .hn-bb-icon {
    width: 44px; height: 44px; border-radius: 999px; background: rgba(255,255,255,.15);
    display: inline-flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 6px;
  }
  .hn-bottom-banner .hn-bb-label { font-size: 12px; font-weight: 600; }

  .hn-emp-tabs { display: flex; flex-wrap: wrap; gap: 6px; }
  .hn-emp-tab {
    border: 1px solid #E2E8F0; background: #fff; color: #475569;
    font-size: 12.5px; font-weight: 600; padding: 6px 14px; border-radius: 999px;
  }
  .hn-emp-tab.active { background: #0F172A; border-color: #0F172A; color: #fff; }

  .dt-container:has(#healthNutritionTable) .dt-layout-row,
  #healthNutritionTable_wrapper .dt-layout-row {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin: .75rem 0;
  }
  .dt-container:has(#healthNutritionTable),
  #healthNutritionTable_wrapper,
  #healthNutritionTable { width: 100% !important; max-width: 100% !important; }
  #healthNutritionTable th, #healthNutritionTable td { vertical-align: middle; }
  #healthNutritionTable thead th { white-space: nowrap; font-weight: 600; }
</style>
@endsection

@section('page-scripts')
<script>
(function () {
    if (typeof ApexCharts === 'undefined') {
        return;
    }

    var kpiTop = @json($kpiTop ?? []);
    var heatmap = @json($heatmap ?? ['categories' => [], 'series' => []]);
    var compliance = @json($compliance ?? ['center_value' => 0, 'center_label' => '', 'legend' => []]);
    var macro = @json($macroChart ?? ['tabs' => [], 'data' => []]);
    var analysisCards = @json($analysisCards ?? []);
    var trend = @json($trendChart ?? ['categories' => [], 'series' => []]);
    var comparison = @json($comparisonChart ?? ['categories' => [], 'berisiko' => [], 'tidak_berisiko' => []]);

    function formatNum(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

    // ---- 4 mini sparklines (KPI atas) ----
    Object.keys(kpiTop).forEach(function (key) {
        var el = document.getElementById('hn-spark-' + key);
        if (!el) return;
        new ApexCharts(el, {
            series: [{ data: kpiTop[key].sparkline || [] }],
            chart: { type: 'line', height: 32, width: 84, sparkline: { enabled: true } },
            stroke: { curve: 'smooth', width: 2 },
            colors: ['#16A34A'],
            tooltip: { enabled: false }
        }).render();
    });

    // ---- Heatmap: Pola Pencatatan Nutrisi Karyawan ----
    var heatmapEl = document.getElementById('hnHeatmap');
    if (heatmapEl && heatmap.series && heatmap.series.length) {
        new ApexCharts(heatmapEl, {
            series: heatmap.series,
            chart: { type: 'heatmap', height: 300, toolbar: { show: false } },
            dataLabels: { enabled: false },
            plotOptions: {
                heatmap: {
                    radius: 3,
                    colorScale: {
                        ranges: [
                            { from: 0, to: 50, name: '0-50', color: '#DCFCE7' },
                            { from: 51, to: 100, name: '51-100', color: '#86EFAC' },
                            { from: 101, to: 200, name: '101-200', color: '#4ADE80' },
                            { from: 201, to: 400, name: '201-400', color: '#16A34A' },
                            { from: 401, to: 100000, name: '>400', color: '#14532D' }
                        ]
                    }
                }
            },
            xaxis: { labels: { show: true, rotate: -45, style: { fontSize: '10px' } }, tickAmount: 10 },
            legend: { show: false },
            tooltip: { y: { formatter: function (v) { return formatNum(v) + ' user aktif'; } } }
        }).render();
    }

    // ---- Donut: Kepatuhan Pencatatan Nutrisi ----
    var complianceEl = document.getElementById('hnComplianceDonut');
    if (complianceEl && compliance.legend && compliance.legend.length) {
        var series = compliance.legend.map(function (i) { return i.count; });
        var colors = compliance.legend.map(function (i) { return i.color; });
        new ApexCharts(complianceEl, {
            series: series,
            labels: compliance.legend.map(function (i) { return i.label; }),
            chart: { type: 'donut', height: 240 },
            colors: colors,
            legend: { show: false },
            stroke: { width: 3, colors: ['#fff'] },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
                            name: { show: true, fontSize: '12px', color: '#64748B', offsetY: -4 },
                            value: {
                                show: true, fontSize: '20px', fontWeight: 700, color: '#0F172A', offsetY: 4,
                                formatter: function () { return formatNum(compliance.center_value); }
                            },
                            total: {
                                show: true, label: compliance.center_label,
                                formatter: function () { return formatNum(compliance.center_value); }
                            }
                        }
                    }
                }
            },
            tooltip: { y: { formatter: function (v) { return formatNum(v) + ' karyawan'; } } }
        }).render();
    }

    // ---- Bar: Makronutrien Karyawan Berisiko (dengan tab) ----
    var macroEl = document.getElementById('hnMacroChart');
    var macroChartInstance = null;
    function renderMacro(tabKey) {
        var dataset = macro.data[tabKey];
        if (!macroEl || !dataset) return;
        var options = {
            series: [
                { name: 'Berisiko', data: dataset.berisiko },
                { name: 'Tidak Berisiko', data: dataset.tidak_berisiko }
            ],
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            colors: ['#EF4444', '#16A34A'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
            dataLabels: { enabled: false },
            grid: { borderColor: '#E2E8F0', strokeDashArray: 4 },
            xaxis: { categories: dataset.categories },
            yaxis: { labels: { formatter: function (v) { return v + '%'; } } },
            legend: { position: 'top' },
            tooltip: { y: { formatter: function (v) { return v + '%'; } } }
        };
        if (macroChartInstance) {
            macroChartInstance.updateOptions(options);
        } else {
            macroChartInstance = new ApexCharts(macroEl, options);
            macroChartInstance.render();
        }
    }
    renderMacro('semua');
    document.querySelectorAll('.hn-macro-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.hn-macro-tab').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            renderMacro(btn.getAttribute('data-tab'));
        });
    });

    // ---- 6 donut kartu analisis ----
    analysisCards.forEach(function (card) {
        var el = document.getElementById('hn-analysis-donut-' + card.key);
        if (!el) return;
        var series = card.legend.map(function (i) { return i.pct; });
        var colors = card.legend.map(function (i) { return i.color; });
        new ApexCharts(el, {
            series: series,
            chart: { type: 'donut', height: 150, width: 150 },
            colors: colors,
            legend: { show: false },
            stroke: { width: 2, colors: ['#fff'] },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            name: { show: true, fontSize: '10px', color: '#64748B', offsetY: -2 },
                            value: {
                                show: true, fontSize: '16px', fontWeight: 700, color: '#0F172A', offsetY: 2,
                                formatter: function () { return formatNum(card.total); }
                            },
                            total: { show: true, label: card.pct + '%', formatter: function () { return formatNum(card.total); } }
                        }
                    }
                }
            },
            tooltip: { y: { formatter: function (v) { return v + '%'; } } }
        }).render();
    });

    // ---- Line: Tren Karyawan Berisiko ----
    var trendEl = document.getElementById('hnTrendChart');
    if (trendEl && trend.series && trend.series.length) {
        new ApexCharts(trendEl, {
            series: trend.series,
            chart: { type: 'line', height: 300, toolbar: { show: false } },
            colors: ['#3B82F6', '#F97316', '#EF4444', '#EAB308', '#16A34A', '#EC4899'],
            stroke: { curve: 'smooth', width: 2.5 },
            markers: { size: 4 },
            grid: { borderColor: '#E2E8F0', strokeDashArray: 4 },
            xaxis: { categories: trend.categories },
            legend: { position: 'bottom', fontSize: '12px' },
            tooltip: { shared: true, intersect: false }
        }).render();
    }

    // ---- Bar: Perbandingan Rata-rata Asupan Nutrisi ----
    var comparisonEl = document.getElementById('hnComparisonChart');
    if (comparisonEl) {
        new ApexCharts(comparisonEl, {
            series: [
                { name: 'Berisiko', data: comparison.berisiko },
                { name: 'Tidak Berisiko', data: comparison.tidak_berisiko }
            ],
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            colors: ['#EF4444', '#16A34A'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '55%', dataLabels: { position: 'top' } } },
            dataLabels: {
                enabled: true,
                offsetY: -18,
                style: { fontSize: '10px', colors: ['#334155'] },
                formatter: function (v) { return formatNum(v); }
            },
            grid: { borderColor: '#E2E8F0', strokeDashArray: 4 },
            xaxis: { categories: comparison.categories },
            legend: { position: 'top' },
            tooltip: { shared: true, intersect: false }
        }).render();
    }
})();
</script>
<script>
(function () {
    var tableEl = document.querySelector('#healthNutritionTable');
    if (!tableEl || typeof DataTable === 'undefined') {
        return;
    }

    var allRows = @json($employeeTable['rows'] ?? []);
    var exportUrl = @json(route('evaluasi-well.health-nutrition.export'));
    var currentTab = 'semua';
    var searchTerm = '';
    var siteEl = document.querySelector('#hn-emp-site');
    var companyEl = document.querySelector('#hn-emp-company');
    var statusEl = document.querySelector('#hn-emp-status');
    var searchEl = document.querySelector('#hn-emp-search');

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function riskBadgeClass(condition) {
        var map = {
            obesitas: 'bg-warning-focus text-warning-main',
            dislipidemia: 'bg-info-focus text-info-main',
            hipertensi: 'bg-danger-focus text-danger-main',
            gula_darah: 'bg-purple-focus text-purple-main',
            sindrom_metabolik: 'bg-neutral-200 text-neutral-600',
            framingham: 'bg-danger-focus text-danger-main'
        };
        return map[condition] || 'bg-neutral-200 text-secondary-light';
    }

    function statusBadgeClass(status) {
        if (status === 'Baik') return 'bg-success-focus text-success-main';
        if (status === 'Pantau') return 'bg-warning-focus text-warning-main';
        return 'bg-danger-focus text-danger-main';
    }

    function filteredRows() {
        var site = siteEl ? siteEl.value : '';
        var company = companyEl ? companyEl.value : '';
        var status = statusEl ? statusEl.value : '';

        return allRows.filter(function (row) {
            if (currentTab !== 'semua' && row.conditions.indexOf(currentTab) === -1) return false;
            if (site && row.site !== site) return false;
            if (company && row.perusahaan !== company) return false;
            if (status && row.status_nutrisi !== status) return false;
            if (searchTerm) {
                var haystack = (row.nama + ' ' + row.nik + ' ' + row.perusahaan).toLowerCase();
                if (haystack.indexOf(searchTerm.toLowerCase()) === -1) return false;
            }
            return true;
        });
    }

    var table = new DataTable(tableEl, {
        data: filteredRows(),
        pageLength: 10,
        lengthMenu: [10, 25, 50],
        order: [[0, 'asc']],
        autoWidth: false,
        columns: [
            { data: 'no' },
            {
                data: 'nama',
                render: function (data, type, row) {
                    if (type !== 'display') return data;
                    return '<div class="fw-medium">' + escapeHtml(data) + '</div>'
                        + '<span class="text-xs text-secondary-light">' + escapeHtml(row.nik) + '</span>';
                }
            },
            { data: 'perusahaan' },
            { data: 'site' },
            { data: 'bmi' },
            { data: 'kolesterol' },
            { data: 'ldl' },
            { data: 'trigliserida' },
            { data: 'tensi' },
            { data: 'gds' },
            {
                data: 'conditions',
                orderable: false,
                render: function (data, type, row) {
                    if (type !== 'display') return row.risiko_label;
                    return data.map(function (c) {
                        return '<span class="' + riskBadgeClass(c) + ' px-10 py-2 rounded-pill text-xs fw-medium d-inline-block mb-2 me-2">'
                            + escapeHtml(row.risiko_label.split(', ')[data.indexOf(c)] || c) + '</span>';
                    }).join('');
                }
            },
            {
                data: 'status_nutrisi',
                render: function (data, type) {
                    if (type !== 'display') return data;
                    return '<span class="' + statusBadgeClass(data) + ' px-10 py-2 rounded-pill text-xs fw-medium">' + escapeHtml(data) + '</span>';
                }
            },
            {
                data: null,
                orderable: false,
                render: function () {
                    return '<button type="button" class="btn btn-sm btn-outline-primary-600 py-2 px-10 hn-detail-btn">Detail</button>';
                }
            }
        ],
        language: {
            processing: 'Memuat...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(difilter dari _MAX_ total data)',
            zeroRecords: 'Tidak ada karyawan untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    function reload() {
        table.clear();
        table.rows.add(filteredRows());
        table.draw();
    }

    document.querySelectorAll('.hn-emp-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.hn-emp-tab').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentTab = btn.getAttribute('data-tab');
            reload();
        });
    });

    [siteEl, companyEl, statusEl].forEach(function (el) {
        if (el) el.addEventListener('change', reload);
    });

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            searchTerm = searchEl.value.trim();
            reload();
        });
    }

    var exportBtn = document.querySelector('#hn-export-btn');
    if (exportBtn) {
        exportBtn.href = exportUrl;
    }
})();
</script>
@endsection

@section('content')
@php
  $kpiTop = $kpiTop ?? [];
  $kpiConditions = $kpiConditions ?? [];
  $analysisCards = $analysisCards ?? [];
  $correlationMatrix = $correlationMatrix ?? ['rows' => [], 'cols' => [], 'levels' => []];
  $macroChart = $macroChart ?? ['tabs' => []];
  $employeeTable = $employeeTable ?? ['tabs' => [], 'rows' => []];
  $filterOptions = $filterOptions ?? ['sites' => [], 'companies' => []];

  $corrLevelColor = static function (int $level): string {
      return match ($level) {
          4 => '#EF4444',
          3 => '#F97316',
          2 => '#FCD34D',
          1 => '#FDE68A',
          default => '#E2E8F0',
      };
  };
  $corrLevelText = static function (int $level): string {
      return $level >= 3 ? '#fff' : '#334155';
  };
  // Format Indonesia (titik ribuan, koma desimal) — $fmt() bawaan
  // Blade default-nya format US (koma ribuan, titik desimal).
  $fmt = static fn (mixed $value, int $decimals = 0): string => number_format((float) $value, $decimals, ',', '.');
@endphp

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-20">
  <div>
    <h6 class="fw-bold mb-4">MCU x Nutrisi</h6>
    <p class="text-sm text-secondary-light mb-0">Analisis kondisi kesehatan karyawan berdasarkan hasil MCU dan pola konsumsi nutrisi</p>
  </div>
  <div class="d-flex flex-wrap align-items-center gap-2">
    <span class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2">
      <iconify-icon icon="solar:calendar-outline"></iconify-icon>
      {{ $dateRangeLabel ?? '-' }}
    </span>
    <select class="form-select form-select-sm" style="min-width:140px">
      <option>Semua Site</option>
      @foreach ($filterOptions['sites'] as $site)
        <option>{{ $site }}</option>
      @endforeach
    </select>
    <select class="form-select form-select-sm" style="min-width:160px">
      <option>Semua Perusahaan</option>
      @foreach ($filterOptions['companies'] as $company)
        <option>{{ $company }}</option>
      @endforeach
    </select>
    <a href="{{ route('evaluasi-well.health-nutrition.export') }}" class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
      <iconify-icon icon="solar:export-bold"></iconify-icon>
      Export
    </a>
  </div>
</div>

<ul class="nav hn-tabs mb-20" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#hnTabDashboard" type="button" role="tab">Dashboard</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#hnTabRingkasan" type="button" role="tab">Ringkasan</button>
  </li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="hnTabDashboard" role="tabpanel">

  {{-- Banner --}}
  <div class="hn-banner mb-20">
    <img src="{{ asset('evaluasi-well-assets/images/wellness/bg-calorie.png') }}" alt="" class="hn-banner__bg d-none d-md-block">
    <div class="hn-banner__text">
      <h6>Nutrisi seimbang,</h6>
      <span>energi untuk hari yang lebih baik</span>
    </div>
  </div>

  {{-- 4 KPI utama --}}
  <div class="row g-3 mb-20">
    @foreach ($kpiTop as $key => $kpi)
    <div class="col-xxl-3 col-sm-6">
      <div class="hn-kpi-card">
        <div class="d-flex align-items-start justify-content-between mb-12">
          <span class="hn-kpi-card__icon bg-{{ $kpi['color'] }}-600" style="background:{{ ['primary'=>'#487FFF','danger'=>'#EF4444','success'=>'#16A34A','info'=>'#0EA5E9'][$kpi['color']] ?? '#487FFF' }}">
            <iconify-icon icon="{{ $kpi['icon'] }}"></iconify-icon>
          </span>
          <div id="hn-spark-{{ $key }}" class="hn-kpi-card__spark"></div>
        </div>
        <div class="hn-kpi-card__value">{{ $fmt($kpi['value']) }}</div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="hn-kpi-card__delta hn-kpi-card__delta--up">
            <iconify-icon icon="solar:arrow-up-bold"></iconify-icon> {{ $fmt($kpi['delta_pct'], 1) }}%
          </span>
          <span class="hn-kpi-card__sub">
            {{ isset($kpi['sub_pct']) ? $fmt($kpi['sub_pct'], 1).'% ' : '' }}{{ $kpi['sub_label'] }}
          </span>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- 6 KPI kondisi --}}
  <div class="row g-3 mb-20">
    @foreach ($kpiConditions as $cond)
    <div class="col-xxl-2 col-md-4 col-sm-6">
      <div class="hn-condition-card">
        <span class="hn-condition-card__icon" style="background: {{ $cond['color'] }}">
          <iconify-icon icon="{{ $cond['icon'] }}"></iconify-icon>
        </span>
        <div class="min-w-0">
          <div class="hn-condition-card__label text-truncate">{{ $cond['label'] }}</div>
          <div class="hn-condition-card__sub text-truncate">{{ $cond['sub_label'] }}</div>
          <div class="d-flex align-items-center gap-2 mt-2">
            <span class="hn-condition-card__value">{{ $fmt($cond['value']) }}</span>
            <span class="hn-condition-card__sub">{{ $fmt($cond['pct'], 1) }}%</span>
          </div>
          <span class="hn-condition-card__delta"><iconify-icon icon="solar:arrow-up-bold"></iconify-icon> {{ $fmt($cond['delta_pct'], 1) }}%</span>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Heatmap + Donut kepatuhan --}}
  <div class="row g-3 mb-20 align-items-stretch">
    <div class="col-xxl-8">
      <div class="hn-card">
        <div class="hn-card__head d-flex align-items-start justify-content-between flex-wrap gap-2">
          <div>
            <h6 class="hn-card__title"><span class="text-success-main">|</span> Pola Pencatatan Nutrisi Karyawan</h6>
            <span class="hn-card__subtitle">Jumlah karyawan yang mencatat asupan kalori</span>
          </div>
          <select class="form-select form-select-sm" style="width:auto">
            <option>Jumlah user aktif</option>
          </select>
        </div>
        <div class="hn-card__body">
          <div id="hnHeatmap"></div>
          <div class="d-flex flex-wrap align-items-center gap-3 mt-8">
            <span class="text-xs fw-medium text-secondary-light">Jumlah user aktif</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1 border" style="width:12px;height:12px;background:#DCFCE7;"></span>0-50</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#86EFAC;"></span>51-100</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#4ADE80;"></span>101-200</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#16A34A;"></span>201-400</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#14532D;"></span>&gt;400</span>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-4">
      <div class="hn-card">
        <div class="hn-card__head">
          <h6 class="hn-card__title"><span class="text-success-main">|</span> Kepatuhan Pencatatan Nutrisi</h6>
        </div>
        <div class="hn-card__body">
          <div id="hnComplianceDonut"></div>
          <div class="d-flex flex-column gap-10 mt-8">
            @foreach ($compliance['legend'] as $item)
            <div class="d-flex align-items-center gap-8">
              <span class="rounded-circle flex-shrink-0" style="width:10px;height:10px;background:{{ $item['color'] }}"></span>
              <div class="min-w-0">
                <div class="text-sm fw-semibold" style="color: {{ $item['color'] }}">{{ $item['label'] }}</div>
                <div class="text-xs text-secondary-light">{{ $fmt($item['pct'], 1) }}% ({{ $fmt($item['count']) }})</div>
              </div>
            </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Korelasi + Makronutrien --}}
  <div class="row g-3 mb-20 align-items-stretch">
    <div class="col-xxl-6">
      <div class="hn-card">
        <div class="hn-card__head">
          <h6 class="hn-card__title"><span class="text-success-main">|</span> Korelasi Hasil MCU dengan Pola Nutrisi</h6>
          <span class="hn-card__subtitle">Semakin gelap menunjukkan korelasi semakin tinggi</span>
        </div>
        <div class="hn-card__body">
          <div class="table-responsive">
            <table class="hn-corr-table">
              <thead>
                <tr>
                  <th></th>
                  @foreach ($correlationMatrix['cols'] as $col)
                    <th>{{ $col }}</th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @foreach ($correlationMatrix['rows'] as $ri => $rowLabel)
                <tr>
                  <td class="hn-corr-row-label">{{ $rowLabel }}</td>
                  @foreach ($correlationMatrix['cols'] as $ci => $col)
                    @php $lvl = $correlationMatrix['levels'][$ri][$ci] ?? 0; @endphp
                    <td class="hn-corr-cell" style="background: {{ $corrLevelColor($lvl) }}"></td>
                  @endforeach
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-3 mt-12">
            <span class="text-xs fw-medium text-secondary-light">Korelasi:</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#EF4444;"></span>Sangat Tinggi</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#F97316;"></span>Tinggi</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#FCD34D;"></span>Sedang</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#FDE68A;"></span>Rendah</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#E2E8F0;"></span>Tidak Signifikan</span>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-6">
      <div class="hn-card">
        <div class="hn-card__head d-flex align-items-start justify-content-between flex-wrap gap-2">
          <h6 class="hn-card__title"><span class="text-success-main">|</span> Makronutrien Karyawan Berisiko</h6>
        </div>
        <div class="hn-card__body">
          <div class="d-flex flex-wrap gap-2 mb-16">
            @foreach ($macroChart['tabs'] as $tabKey => $tabLabel)
              <button type="button" class="hn-macro-tab {{ $loop->first ? 'active' : '' }}" data-tab="{{ $tabKey }}">{{ $tabLabel }}</button>
            @endforeach
          </div>
          <span class="text-xs text-secondary-light d-block mb-8">% karyawan melebihi target harian</span>
          <div id="hnMacroChart"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- 6 kartu analisis --}}
  <div class="row g-3 mb-20">
    @foreach ($analysisCards as $card)
    <div class="col-xxl-4 col-md-6">
      <div class="hn-analysis-card d-flex flex-column gap-12">
        <h6 class="hn-analysis-card__title mb-0">{{ $card['title'] }}</h6>
        <div class="d-flex align-items-center gap-12">
          <div id="hn-analysis-donut-{{ $card['key'] }}" style="width:150px;height:150px;flex-shrink:0;"></div>
          <div class="d-flex flex-column gap-8 min-w-0">
            @foreach ($card['legend'] as $item)
            <div class="hn-analysis-card__legend-item">
              <span class="hn-analysis-card__dot" style="background: {{ $item['color'] }}">
                <iconify-icon icon="{{ $item['icon'] }}"></iconify-icon>
              </span>
              <span class="text-truncate">{{ $item['label'] }} <strong>{{ $fmt($item['pct'], 1) }}%</strong>{{ isset($item['count']) ? ' ('.$fmt($item['count']).')' : '' }}</span>
            </div>
            @endforeach
          </div>
        </div>
        <div class="hn-analysis-card__insight">{{ $card['insight'] }}</div>
        <a href="#" class="text-primary-600 hover-text-primary text-sm fw-medium d-inline-flex align-items-center gap-1">
          Lihat Detail <iconify-icon icon="solar:alt-arrow-right-linear"></iconify-icon>
        </a>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Tren + Perbandingan --}}
  <div class="row g-3 mb-20 align-items-stretch">
    <div class="col-xxl-6">
      <div class="hn-card">
        <div class="hn-card__head d-flex align-items-start justify-content-between flex-wrap gap-2">
          <h6 class="hn-card__title"><span class="text-success-main">|</span> Tren Karyawan Berisiko</h6>
          <select class="form-select form-select-sm" style="width:auto"><option>Bulanan</option></select>
        </div>
        <div class="hn-card__body">
          <div id="hnTrendChart"></div>
        </div>
      </div>
    </div>
    <div class="col-xxl-6">
      <div class="hn-card">
        <div class="hn-card__head d-flex align-items-start justify-content-between flex-wrap gap-2">
          <h6 class="hn-card__title"><span class="text-success-main">|</span> Perbandingan Rata-rata Asupan Nutrisi</h6>
          <select class="form-select form-select-sm" style="width:auto"><option>Berisiko vs Tidak Berisiko</option></select>
        </div>
        <div class="hn-card__body">
          <div id="hnComparisonChart"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- Tabel Daftar Karyawan Risiko Tinggi --}}
  <div class="hn-card mb-20">
    <div class="hn-card__head">
      <h6 class="hn-card__title mb-12"><span class="text-success-main">|</span> Daftar Karyawan dengan Risiko Tinggi</h6>
    </div>
    <div class="hn-card__body pt-0">
      <div class="hn-emp-tabs mb-16">
        @foreach ($employeeTable['tabs'] as $tabKey => $tab)
          <button type="button" class="hn-emp-tab {{ $loop->first ? 'active' : '' }}" data-tab="{{ $tabKey }}">{{ $tab['label'] }} ({{ $fmt($tab['total']) }})</button>
        @endforeach
      </div>
      <div class="row g-2 align-items-center mb-16">
        <div class="col-lg-4 col-md-6">
          <div class="position-relative">
            <iconify-icon icon="solar:magnifer-linear" class="position-absolute top-50 start-0 translate-middle-y ms-12 text-secondary-light"></iconify-icon>
            <input type="search" id="hn-emp-search" class="form-control form-control-sm ps-32" placeholder="Cari nama / NIK / perusahaan...">
          </div>
        </div>
        <div class="col-lg-2 col-md-6">
          <select id="hn-emp-site" class="form-select form-select-sm">
            <option value="">Semua Site</option>
            @foreach ($filterOptions['sites'] as $site)
              <option value="{{ $site }}">{{ $site }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <select id="hn-emp-company" class="form-select form-select-sm">
            <option value="">Semua Perusahaan</option>
            @foreach ($filterOptions['companies'] as $company)
              <option value="{{ $company }}">{{ $company }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <select id="hn-emp-status" class="form-select form-select-sm">
            <option value="">Status Nutrisi</option>
            <option value="Perlu Intervensi">Perlu Intervensi</option>
            <option value="Pantau">Pantau</option>
            <option value="Baik">Baik</option>
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <a id="hn-export-btn" href="{{ route('evaluasi-well.health-nutrition.export') }}" class="btn btn-sm btn-success-600 w-100 d-inline-flex align-items-center justify-content-center gap-1">
            <iconify-icon icon="solar:file-download-bold"></iconify-icon> Export
          </a>
        </div>
      </div>
      <div class="table-responsive">
        <table id="healthNutritionTable" class="table bordered-table mb-0 w-100" style="width:100%">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama Karyawan</th>
              <th>Perusahaan</th>
              <th>Site</th>
              <th>BMI</th>
              <th>Kolesterol</th>
              <th>LDL</th>
              <th>Trigliserida</th>
              <th>Tensi</th>
              <th>GDS</th>
              <th>Risiko</th>
              <th>Status Nutrisi</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Banner bawah --}}
  <div class="hn-bottom-banner d-flex flex-wrap align-items-center justify-content-between gap-4 mb-4">
    <div>
      <h6>Karyawan Sehat, Operasi Kuat,</h6>
      <span class="text-sm" style="opacity:.9">Masa Depan Berkelanjutan</span>
    </div>
    <div class="d-flex flex-wrap gap-4">
      <div class="hn-bb-item">
        <div class="hn-bb-icon"><iconify-icon icon="solar:cup-hot-bold"></iconify-icon></div>
        <div class="hn-bb-label">Nutrisi<br>Seimbang</div>
      </div>
      <div class="hn-bb-item">
        <div class="hn-bb-icon"><iconify-icon icon="solar:running-round-bold"></iconify-icon></div>
        <div class="hn-bb-label">Aktivitas<br>Rutin</div>
      </div>
      <div class="hn-bb-item">
        <div class="hn-bb-icon"><iconify-icon icon="solar:scale-bold"></iconify-icon></div>
        <div class="hn-bb-label">Berat Badan<br>Ideal</div>
      </div>
      <div class="hn-bb-item">
        <div class="hn-bb-icon"><iconify-icon icon="solar:health-bold"></iconify-icon></div>
        <div class="hn-bb-label">Cek Kesehatan<br>Berkala</div>
      </div>
    </div>
  </div>

</div>

<div class="tab-pane fade" id="hnTabRingkasan" role="tabpanel">
  <div class="hn-card">
    <div class="hn-card__body">
      <h6 class="fw-semibold mb-12">Ringkasan</h6>
      <p class="text-sm text-secondary-light">
        Dari {{ $fmt($kpiTop['total_karyawan']['value'] ?? 0) }} karyawan yang mengikuti MCU,
        {{ $fmt($kpiTop['karyawan_berisiko']['value'] ?? 0) }} ({{ $fmt($kpiTop['karyawan_berisiko']['sub_pct'] ?? 0, 1) }}%) terindikasi berisiko metabolik.
        Kepatuhan pencatatan nutrisi tercatat pada {{ $fmt($kpiTop['data_nutrisi']['value'] ?? 0) }} karyawan
        ({{ $fmt($kpiTop['data_nutrisi']['sub_pct'] ?? 0, 1) }}% dari total), dengan
        {{ $fmt($kpiTop['target_kalori']['sub_pct'] ?? 0, 1) }}% karyawan telah memenuhi target kalori harian.
      </p>
      <div class="row g-3 mt-8">
        @foreach ($kpiConditions as $cond)
        <div class="col-md-4 col-sm-6">
          <div class="d-flex align-items-center gap-8">
            <span class="hn-condition-card__icon" style="background: {{ $cond['color'] }}"><iconify-icon icon="{{ $cond['icon'] }}"></iconify-icon></span>
            <div>
              <div class="fw-semibold text-sm">{{ $cond['label'] }}</div>
              <div class="text-xs text-secondary-light">{{ $fmt($cond['value']) }} karyawan ({{ $fmt($cond['pct'], 1) }}%)</div>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
</div>
</div>
@endsection
