@extends('evaluasi-well.layouts.app')

@section('title', 'MCU x Nutrisi')

@section('css')
<style>
  /* ===== MCU x Nutrisi — dashboard baru (data dummy, arsitektur real) ===== */
  .hn-tabs {
    display: inline-flex;
    gap: 8px;
  }
  .hn-tabs .nav-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #E2E8F0;
    border-radius: 999px;
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 600;
    color: #64748B;
    background: #fff;
  }
  .hn-tabs .nav-link iconify-icon { font-size: 15px; }
  .hn-tabs .nav-link.active {
    background: #ECFDF5;
    color: #16A34A;
    border-color: #16A34A;
  }

  /* ---- Header toolbar: date chip + filter selects + export ---- */
  .hn-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
  }
  .hn-header__title h6 { font-size: 20px; font-weight: 700; color: #0F172A; margin-bottom: 4px; }

  .hn-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
  }
  .hn-toolbar > * { flex: 0 0 auto; }

  .hn-filter-chip,
  .hn-filter-select {
    height: 38px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    font-size: 13px;
    font-weight: 500;
    color: #334155;
  }

  .hn-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 14px;
    background: #F8FAFC;
    white-space: nowrap;
    cursor: pointer;
    transition: border-color .15s ease, background .15s ease;
  }
  .hn-filter-chip iconify-icon { color: #64748B; font-size: 16px; }
  .hn-filter-chip:hover { background: #F1F5F9; border-color: #CBD5E1; }
  .hn-filter-chip:focus-visible,
  .hn-filter-select:focus {
    outline: none;
    border-color: #16A34A;
    box-shadow: 0 0 0 3px rgba(22,163,74,.12);
  }

  .hn-filter-select {
    min-width: 156px;
    padding-top: 0;
    padding-bottom: 0;
    padding-left: 34px;
  }

  .hn-select-wrap { position: relative; }
  .hn-select-wrap__icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 15px;
    color: #64748B;
    pointer-events: none;
    z-index: 1;
  }

  .hn-toolbar .hn-export-btn {
    height: 38px;
    border-radius: 10px;
    padding: 0 14px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
  }

  @media (max-width: 575.98px) {
    .hn-toolbar { justify-content: flex-start; width: 100%; }
    .hn-toolbar > * { flex: 1 1 auto; }
  }

  /* Kartu banner — dibuat identik dengan konvensi .wc-card di /evaluasi-well
     (lihat "Top 5 Olahraga"/"Kalori Makanan"): kartu putih + foto masked di
     kanan, bukan panel gradient solid. */
  .wc-card {
    border: 1px solid #E2E8F0 !important;
    border-radius: 16px !important;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06) !important;
    background: #fff;
    overflow: hidden;
    position: relative;
  }
  .wc-card .card-body { z-index: 1; position: relative; }
  .wc-card__bg {
    position: absolute;
    right: 0;
    top: 22%;
    width: min(44%, 230px);
    height: auto;
    max-height: 62%;
    object-fit: contain;
    object-position: right bottom;
    opacity: 0.48;
    pointer-events: none;
    z-index: 0;
    mask-image: linear-gradient(90deg, transparent 0%, rgba(0,0,0,.4) 30%, #000 58%);
    -webkit-mask-image: linear-gradient(90deg, transparent 0%, rgba(0,0,0,.4) 30%, #000 58%);
  }
  .wc-card__head-icon {
    width: 44px;
    height: 44px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 22px;
    background: #16A34A;
    color: #fff;
  }
  .wc-card__title { color: #0F172A; }
  .wc-card__subtitle {
    display: block;
    font-size: 13px;
    color: #64748B;
    line-height: 1.35;
  }
  .wc-card__badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ECFDF5;
    color: #166534;
    border: 1px solid #BBF7D0;
    border-radius: 999px;
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.35;
    max-width: 100%;
  }
  .wc-card__badge iconify-icon { font-size: 16px; color: #16A34A; flex-shrink: 0; }
  @media (max-width: 991px) { .wc-card__bg { opacity: 0.12; } }
  @media (max-width: 768px) { .wc-card__bg { display: none; } }

  .hn-coverage-card {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 16px 20px;
  }
  .hn-coverage-value { font-size: 17px; font-weight: 700; color: #0F172A; line-height: 1.2; }
  .hn-coverage-label { font-size: 12px; color: #64748B; }
  .hn-coverage-label iconify-icon { font-size: 13px; cursor: help; }
  .hn-coverage-icon {
    width: 38px; height: 38px; border-radius: 999px; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center; font-size: 18px;
  }
  .hn-coverage-icon--green { background: #ECFDF5; color: #16A34A; }
  .hn-coverage-icon--blue { background: #EFF6FF; color: #2563EB; }
  .hn-coverage-divider { width: 1px; align-self: stretch; background: #E2E8F0; }
  .hn-coverage-desc { font-size: 12px; color: #64748B; line-height: 1.4; }

  .hn-quality-box {
    display: flex;
    align-items: center;
    gap: 12px;
    border-radius: 12px;
    padding: 10px 16px;
    max-width: 380px;
  }
  .hn-quality-box--good { background: #ECFDF5; border: 1px solid #BBF7D0; }
  .hn-quality-box--warning { background: #FFFBEB; border: 1px solid #FDE68A; }
  .hn-quality-box__icon {
    width: 32px; height: 32px; border-radius: 999px; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center; font-size: 16px; color: #fff;
  }
  .hn-quality-box--good .hn-quality-box__icon { background: #16A34A; }
  .hn-quality-box--warning .hn-quality-box__icon { background: #F4941E; }
  .hn-quality-box__title { font-size: 13px; font-weight: 700; color: #0F172A; }
  .hn-quality-box--good .hn-quality-box__title { color: #14532D; }
  .hn-quality-box--warning .hn-quality-box__title { color: #92400E; }
  .hn-quality-box__desc { font-size: 11.5px; color: #64748B; line-height: 1.3; }
  .hn-quality-box__chevron { font-size: 16px; color: #94A3B8; flex-shrink: 0; }

  .hn-hero__bg {
    top: 8%;
    max-height: 84%;
    width: min(30%, 210px);
    opacity: 0.95;
    mask-image: linear-gradient(90deg, transparent 0%, rgba(0,0,0,.5) 12%, #000 30%);
    -webkit-mask-image: linear-gradient(90deg, transparent 0%, rgba(0,0,0,.5) 12%, #000 30%);
  }
  .hn-hero__quote {
    align-items: center;
    gap: 10px;
    background: #ECFDF5;
    border: 1px solid #BBF7D0;
    border-radius: 12px;
    padding: 10px 16px;
    max-width: 320px;
    font-size: 13px;
    font-style: italic;
    color: #334155;
  }
  .hn-hero__quote iconify-icon { font-size: 22px; color: #16A34A; flex-shrink: 0; }
  .hn-hero__next {
    width: 38px; height: 38px; border-radius: 999px; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    background: #16A34A; color: #fff; border: none; font-size: 16px;
    transition: background .15s ease;
  }
  .hn-hero__next:hover { background: #14532D; }

  .hn-kpi-card__spark { height: 44px; width: 96px; flex-shrink: 0; }
  .hn-kpi-card__footer { border-top: 1px solid #F1F5F9; }

  .hn-section-bar { width: 4px; height: 20px; border-radius: 999px; background: #16A34A; display: inline-block; }
  .hn-detail-analysis-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: #ECFDF5; color: #16A34A; border: 1px solid #BBF7D0;
    border-radius: 999px; padding: 7px 16px; font-size: 12.5px; font-weight: 600;
  }
  .hn-detail-analysis-btn:hover { background: #DCFCE7; }

  .hn-cond-card { border: 1px solid rgba(15,23,42,.05) !important; }
  .hn-cond-card__spark { height: 28px; width: 64px; flex-shrink: 0; }

  #hnHeatmap .apexcharts-tooltip { font-size: 12px; }

  .hn-mini-stat { padding: 10px 14px; border: 1px solid #E8EDF3; border-radius: 10px; background: #F8FAFC; }
  .hn-mini-stat__value { font-size: 15px; font-weight: 700; color: #0F172A; }
  .hn-mini-stat__label { font-size: 11px; color: #64748B; }

  .hn-corr-table { width: 100%; border-collapse: separate; border-spacing: 4px; font-size: 12px; }
  .hn-corr-table th { font-weight: 600; color: #475569; text-align: center; padding: 6px 4px; font-size: 11.5px; }
  .hn-corr-table th:first-child { text-align: left; }
  .hn-corr-table td.hn-corr-row-label { text-align: left; font-weight: 600; color: #334155; white-space: nowrap; padding-right: 10px; font-size: 12px; }
  .hn-corr-table td.hn-corr-cell { border-radius: 6px; height: 34px; cursor: default; }

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
  .hn-emp-tab.active { background: #16A34A; border-color: #16A34A; color: #fff; }

  .hn-pair-row { border: 1px solid #E8EDF3; border-radius: 10px; padding: 10px 14px; }
  .hn-pair-row .hn-pair-flag { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
  .hn-pair-flag--high, .hn-pair-flag--above { background: #FEF2F2; color: #DC2626; }
  .hn-pair-flag--normal, .hn-pair-flag--ontarget { background: #ECFDF5; color: #16A34A; }

  .dt-container:has(#healthNutritionTable) .dt-layout-row,
  #healthNutritionTable_wrapper .dt-layout-row {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin: .75rem 0;
  }
  .dt-container:has(#healthNutritionTable),
  #healthNutritionTable_wrapper,
  #healthNutritionTable { width: 100% !important; max-width: 100% !important; }
  #healthNutritionTable th, #healthNutritionTable td { vertical-align: middle; }
  #healthNutritionTable thead th { white-space: nowrap; font-weight: 600; }

  .dt-container:has(#criticalIllnessTable) .dt-layout-row,
  #criticalIllnessTable_wrapper .dt-layout-row {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin: .75rem 0;
  }
  .dt-container:has(#criticalIllnessTable),
  #criticalIllnessTable_wrapper,
  #criticalIllnessTable { width: 100% !important; max-width: 100% !important; }
  #criticalIllnessTable th, #criticalIllnessTable td { vertical-align: middle; }
  #criticalIllnessTable thead th { white-space: nowrap; font-weight: 600; }
  .hn-ci-badge {
    display: inline-block; padding: 2px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 600; background: #FEF2F2; color: #DC2626; margin: 0 4px 4px 0;
  }
  .hn-ci-lifestyle { font-size: 12px; color: #334155; line-height: 1.5; }
  .hn-ci-lifestyle .text-muted-light { color: #94A3B8; }
</style>
@endsection

@section('page-scripts')
<script>
(function () {
    if (typeof ApexCharts === 'undefined') {
        return;
    }

    var macro = @json($macroChart ?? ['tabs' => [], 'data' => []]);
    var conditionAnalysis = @json($conditionAnalysis ?? []);
    var comparison = @json($comparisonChart ?? ['calories' => null, 'macros' => null]);

    function formatNum(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

    // ---- Tooltip Bootstrap (info coverage + tabel keterkaitan) ----
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        if (window.bootstrap && window.bootstrap.Tooltip) {
            new bootstrap.Tooltip(el);
        }
    });

    // ---- Banner: kutipan bergilir ----
    var heroQuotes = [
        'Karyawan yang sehat adalah investasi terbaik untuk perusahaan yang kuat.',
        'Pola makan yang baik hari ini, produktivitas yang lebih baik esok hari.',
        'Kecil tapi konsisten: catat asupan nutrisi setiap hari.'
    ];
    var heroQuoteIndex = 0;
    var heroQuoteEl = document.getElementById('hn-hero-quote-text');
    var heroQuoteBtn = document.getElementById('hn-hero-quote-next');
    if (heroQuoteEl && heroQuoteBtn) {
        heroQuoteBtn.addEventListener('click', function () {
            heroQuoteIndex = (heroQuoteIndex + 1) % heroQuotes.length;
            heroQuoteEl.textContent = heroQuotes[heroQuoteIndex];
        });
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
            chart: { type: 'bar', height: 280, toolbar: { show: false } },
            colors: ['#DC2626', '#16A34A'],
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

    // ---- 6 donut kartu analisis kondisi ----
    conditionAnalysis.forEach(function (card) {
        var el = document.getElementById('hn-analysis-donut-' + card.key);
        if (!el) return;
        var available = card.legend.filter(function (i) { return i.available; });
        if (!available.length) return;
        var series = available.map(function (i) { return i.pct; });
        var colors = available.map(function (i) { return i.color; });
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

    // ---- Bar: Perbandingan Rata-rata Asupan Nutrisi — dipisah kkal vs gram ----
    // (unit beda jauh skalanya, digabung 1 axis akan menyesatkan)
    var caloriesEl = document.getElementById('hnComparisonCalories');
    if (caloriesEl && comparison.calories) {
        new ApexCharts(caloriesEl, {
            series: [
                { name: 'Berisiko', data: comparison.calories.berisiko },
                { name: 'Tidak Berisiko', data: comparison.calories.tidak_berisiko }
            ],
            chart: { type: 'bar', height: 160, toolbar: { show: false } },
            colors: ['#DC2626', '#16A34A'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '35%', horizontal: true, dataLabels: { position: 'top' } } },
            dataLabels: {
                enabled: true,
                offsetX: 20,
                style: { fontSize: '11px', colors: ['#334155'] },
                formatter: function (v) { return formatNum(v) + ' kkal'; }
            },
            grid: { borderColor: '#E2E8F0', strokeDashArray: 4 },
            xaxis: { categories: comparison.calories.categories },
            legend: { show: false },
            tooltip: { shared: true, intersect: false }
        }).render();
    }
    var macrosCompEl = document.getElementById('hnComparisonMacros');
    if (macrosCompEl && comparison.macros) {
        new ApexCharts(macrosCompEl, {
            series: [
                { name: 'Berisiko', data: comparison.macros.berisiko },
                { name: 'Tidak Berisiko', data: comparison.macros.tidak_berisiko }
            ],
            chart: { type: 'bar', height: 220, toolbar: { show: false } },
            colors: ['#DC2626', '#16A34A'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '55%', dataLabels: { position: 'top' } } },
            dataLabels: {
                enabled: true,
                offsetY: -18,
                style: { fontSize: '10px', colors: ['#334155'] },
                formatter: function (v) { return formatNum(v) + 'g'; }
            },
            grid: { borderColor: '#E2E8F0', strokeDashArray: 4 },
            xaxis: { categories: comparison.macros.categories },
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

    var dataUrl = @json(route('evaluasi-well.health-nutrition.data'));
    var exportBaseUrl = @json(route('evaluasi-well.health-nutrition.export'));
    var employeeDetailBase = @json(url('/evaluasi-well/health-nutrition/employees'));
    var currentTab = 'semua';
    var siteEl = document.querySelector('#hn-emp-site');
    var companyEl = document.querySelector('#hn-emp-company');
    var statusEl = document.querySelector('#hn-emp-status');

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
            dislipidemia: 'bg-info-focus text-info-main',
            hipertensi: 'bg-danger-focus text-danger-main',
            gula_darah: 'bg-purple-focus text-purple-main',
            sindrom_metabolik: 'bg-neutral-200 text-neutral-600',
            framingham: 'bg-danger-focus text-danger-main'
        };
        return map[condition] || 'bg-neutral-200 text-secondary-light';
    }

    var conditionLabels = {
        dislipidemia: 'Dislipidemia', hipertensi: 'Hipertensi',
        gula_darah: 'Gula Darah Tinggi', sindrom_metabolik: 'Sindrom Metabolik', framingham: 'Framingham High Risk'
    };

    function statusBadgeClass(status) {
        if (status === 'Baik') return 'bg-success-focus text-success-main';
        if (status === 'Pantau') return 'bg-warning-focus text-warning-main';
        if (status === 'Perlu Intervensi') return 'bg-danger-focus text-danger-main';
        return 'bg-neutral-200 text-secondary-light';
    }

    function updateExportHref() {
        var btn = document.querySelector('#hn-export-btn');
        if (!btn) return;
        var params = new URLSearchParams();
        if (currentTab && currentTab !== 'semua') params.set('tab', currentTab);
        var query = params.toString();
        btn.href = exportBaseUrl + (query ? '?' + query : '');
    }

    var table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50],
        order: [[1, 'asc']],
        autoWidth: false,
        ajax: {
            url: dataUrl,
            data: function (d) {
                d.tab = currentTab;
                d.site = siteEl ? siteEl.value : '';
                d.company = companyEl ? companyEl.value : '';
                d.status = statusEl ? statusEl.value : '';
            }
        },
        columns: [
            { data: 'no', orderable: false },
            {
                data: 'nama',
                render: function (data, type, row) {
                    if (type !== 'display') return data;
                    return '<div class="fw-medium">' + escapeHtml(data) + '</div>'
                        + '<span class="text-xs text-secondary-light">' + escapeHtml(row.nik) + '</span>';
                }
            },
            { data: 'perusahaan', orderable: false },
            { data: 'site', orderable: false },
            { data: 'departemen', orderable: false },
            {
                data: 'conditions',
                orderable: false,
                render: function (data, type, row) {
                    if (type !== 'display') return row.risiko_label;
                    return data.map(function (c) {
                        return '<span class="' + riskBadgeClass(c) + ' px-10 py-2 rounded-pill text-xs fw-medium d-inline-block mb-2 me-2">'
                            + escapeHtml(conditionLabels[c] || c) + '</span>';
                    }).join('');
                }
            },
            {
                data: 'status_nutrisi',
                orderable: false,
                render: function (data, type) {
                    if (type !== 'display') return data;
                    return '<span class="' + statusBadgeClass(data) + ' px-10 py-2 rounded-pill text-xs fw-medium">' + escapeHtml(data) + '</span>';
                }
            },
            {
                data: 'id',
                orderable: false,
                render: function (data) {
                    return '<button type="button" class="btn btn-sm btn-outline-primary-600 py-2 px-10 hn-detail-btn" data-id="' + data + '">Detail</button>';
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

    document.querySelectorAll('.hn-emp-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.hn-emp-tab').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentTab = btn.getAttribute('data-tab');
            updateExportHref();
            table.ajax.reload();
        });
    });

    [siteEl, companyEl, statusEl].forEach(function (el) {
        if (el) el.addEventListener('change', function () { table.ajax.reload(); });
    });

    updateExportHref();

    // ---- Modal detail karyawan: MCU x Nutrition Profile ----
    var detailModalEl = document.getElementById('hnEmployeeDetailModal');
    var detailModal = (detailModalEl && window.bootstrap) ? new bootstrap.Modal(detailModalEl) : null;
    var detailBody = document.getElementById('hn-detail-body');
    var detailLoading = document.getElementById('hn-detail-loading');

    function renderDetail(detail) {
        var p = detail.profile;
        var mcu = detail.mcu;
        var nutrition = detail.nutrition;
        var completeness = detail.data_completeness;

        var html = '<div class="row g-3">';
        html += '<div class="col-md-4"><h6 class="text-sm fw-bold mb-8">Profil</h6>'
            + '<p class="mb-2 text-sm"><strong>' + escapeHtml(p.nama) + '</strong><br>' + escapeHtml(p.nik) + '</p>'
            + '<p class="mb-0 text-sm text-secondary-light">' + escapeHtml(p.perusahaan) + ' · ' + escapeHtml(p.site) + '<br>'
            + escapeHtml(p.departemen) + '</p></div>';

        html += '<div class="col-md-4"><h6 class="text-sm fw-bold mb-8">MCU</h6>';
        if (mcu) {
            html += '<p class="text-xs text-secondary-light mb-8">Pemeriksaan terakhir: ' + escapeHtml(mcu.exam_date || '-') + '</p>';
            html += '<ul class="list-unstyled text-sm mb-0 d-flex flex-column gap-6">';
            (mcu.conditions || []).forEach(function (c) {
                var badgeClass = c.abnormal ? 'bg-danger-focus text-danger-main' : 'bg-success-focus text-success-main';
                html += '<li class="d-flex align-items-center justify-content-between gap-2">'
                    + '<span>' + escapeHtml(c.label) + '</span>'
                    + '<span class="' + badgeClass + ' px-8 py-2 rounded-pill text-xs fw-medium">' + escapeHtml(c.status) + '</span>'
                    + '</li>';
            });
            html += '</ul>';
        } else {
            html += '<p class="text-sm text-secondary-light mb-0">Data MCU belum tersedia.</p>';
        }
        html += '</div>';

        html += '<div class="col-md-4"><h6 class="text-sm fw-bold mb-8">Nutrisi (30 hari)</h6>';
        if (nutrition) {
            html += '<ul class="list-unstyled text-sm mb-0">'
                + '<li>Rata-rata Kalori: <strong>' + formatNumJs(nutrition.avg_calories) + ' kkal</strong></li>'
                + '<li>Target Kalori: <strong>' + formatNumJs(nutrition.target_calories) + ' kkal</strong> (' + (nutrition.calories_vs_target_pct >= 0 ? '+' : '') + nutrition.calories_vs_target_pct + '%)</li>'
                + '<li>Karbohidrat: <strong>' + formatNumJs(nutrition.avg_carbs_g) + ' g</strong></li>'
                + '<li>Protein: <strong>' + formatNumJs(nutrition.avg_protein_g) + ' g</strong></li>'
                + '<li>Hari log (30 hari): <strong>' + nutrition.logging_days_30d + '</strong></li>'
                + '</ul>';
        } else {
            html += '<p class="text-sm text-secondary-light mb-0">Data nutrisi belum tersedia.</p>';
        }
        html += '</div></div>';

        if (!completeness.matched) {
            html += '<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 radius-8 mt-16 mb-0 py-10 px-16 text-sm">'
                + 'Data belum lengkap (MCU dan nutrisi belum ter-matched untuk karyawan ini).</div>';
        }

        return html;
    }

    function formatNumJs(value) {
        return value === null || value === undefined ? '-' : Number(value).toLocaleString('id-ID');
    }

    tableEl.addEventListener('click', function (event) {
        var btn = event.target.closest('.hn-detail-btn');
        if (!btn || !detailModal) return;
        var id = btn.getAttribute('data-id');

        detailBody.innerHTML = '';
        detailLoading.classList.remove('d-none');
        detailModal.show();

        fetch(employeeDetailBase + '/' + id, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.ok ? res.json() : Promise.reject();
        }).then(function (detail) {
            detailBody.innerHTML = renderDetail(detail);
        }).catch(function () {
            detailBody.innerHTML = '<p class="text-danger-main text-sm mb-0">Gagal memuat detail karyawan.</p>';
        }).finally(function () {
            detailLoading.classList.add('d-none');
        });
    });
})();
</script>
<script>
(function () {
    var tableEl = document.querySelector('#criticalIllnessTable');
    if (!tableEl || typeof DataTable === 'undefined') {
        return;
    }

    var dataUrl = @json(route('evaluasi-well.health-nutrition.critical-illness.data'));
    var detailBase = @json(url('/evaluasi-well/health-nutrition/critical-illness/employees'));
    var conditionEl = document.querySelector('#hn-ci-condition');
    var siteEl = document.querySelector('#hn-ci-site');
    var companyEl = document.querySelector('#hn-ci-company');

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNumJs(value, decimals) {
        if (value === null || value === undefined) return '-';
        return Number(value).toLocaleString('id-ID', { maximumFractionDigits: decimals || 0 });
    }

    function renderConditions(conditions) {
        return (conditions || []).map(function (c) {
            return '<span class="hn-ci-badge">' + escapeHtml(c) + '</span>';
        }).join('');
    }

    function renderExerciseCell(exercise) {
        if (!exercise || !exercise.has_data) {
            return '<span class="text-xs text-secondary-light">Belum ada data</span>';
        }
        var html = '<div class="hn-ci-lifestyle">';
        html += '<div>' + formatNumJs(exercise.log_count) + ' log · ' + escapeHtml(exercise.aktivitas_terakhir || '-') + '</div>';
        html += '<div class="text-muted-light">' + formatNumJs(exercise.avg_durasi_menit, 1) + ' menit · '
            + formatNumJs(exercise.avg_langkah) + ' langkah · ' + formatNumJs(exercise.avg_kalori) + ' kkal</div>';
        html += '</div>';
        return html;
    }

    function renderDietCell(diet) {
        if (!diet || !diet.has_data) {
            return '<span class="text-xs text-secondary-light">Belum ada log</span>';
        }
        var badge = diet.has_poor_diet
            ? '<span class="hn-ci-badge" style="background:#FEF3C7;color:#B45309;">Perlu Perhatian</span>'
            : '<span class="hn-ci-badge" style="background:#ECFDF5;color:#16A34A;">Baik</span>';
        var html = '<div class="hn-ci-lifestyle">';
        html += '<div>' + badge + '</div>';
        html += '<div class="text-muted-light">' + formatNumJs(diet.avg_calories) + ' kkal/hari · '
            + formatNumJs(diet.days_logged) + ' hari log (30 hari)</div>';
        html += '</div>';
        return html;
    }

    var table = new DataTable(tableEl, {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: false,
        pageLength: 10,
        lengthMenu: [10, 25, 50],
        autoWidth: false,
        ajax: {
            url: dataUrl,
            data: function (d) {
                d.condition = conditionEl ? conditionEl.value : '';
                d.site = siteEl ? siteEl.value : '';
                d.company = companyEl ? companyEl.value : '';
            }
        },
        columns: [
            { data: 'no', orderable: false },
            {
                data: 'nama',
                render: function (data, type, row) {
                    if (type !== 'display') return data;
                    return '<div class="fw-medium">' + escapeHtml(data) + '</div>'
                        + '<span class="text-xs text-secondary-light">' + escapeHtml(row.nik) + ' · ' + escapeHtml(row.kode_sid) + '</span>';
                }
            },
            {
                data: 'perusahaan',
                render: function (data, type, row) {
                    if (type !== 'display') return data;
                    return escapeHtml(data) + '<br><span class="text-xs text-secondary-light">' + escapeHtml(row.site) + '</span>';
                }
            },
            { data: 'divisi' },
            {
                data: 'conditions',
                render: function (data, type) {
                    if (type !== 'display') return (data || []).join(', ');
                    return renderConditions(data);
                }
            },
            { data: 'tanggal_terkonfirmasi', render: function (d) { return d || '-'; } },
            {
                data: 'exercise',
                render: function (data, type) {
                    if (type !== 'display') return data && data.has_data ? 'ya' : 'tidak';
                    return renderExerciseCell(data);
                }
            },
            {
                data: 'diet',
                render: function (data, type) {
                    if (type !== 'display') return data && data.has_data ? 'ya' : 'tidak';
                    return renderDietCell(data);
                }
            },
            {
                data: 'id',
                render: function (data) {
                    return '<button type="button" class="btn btn-sm btn-outline-primary-600 py-2 px-10 hn-ci-detail-btn" data-id="' + data + '">Detail</button>';
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

    [conditionEl, siteEl, companyEl].forEach(function (el) {
        if (el) el.addEventListener('change', function () { table.ajax.reload(); });
    });

    var detailModalEl = document.getElementById('hnCriticalIllnessDetailModal');
    var detailModal = (detailModalEl && window.bootstrap) ? new bootstrap.Modal(detailModalEl) : null;
    var detailBody = document.getElementById('hn-ci-detail-body');
    var detailLoading = document.getElementById('hn-ci-detail-loading');

    function renderDetail(row) {
        var html = '<div class="row g-3">';
        html += '<div class="col-md-5"><h6 class="text-sm fw-bold mb-8">Profil</h6>'
            + '<p class="mb-2 text-sm"><strong>' + escapeHtml(row.nama) + '</strong><br>'
            + escapeHtml(row.nik) + ' · ' + escapeHtml(row.kode_sid) + '</p>'
            + '<p class="mb-0 text-sm text-secondary-light">' + escapeHtml(row.perusahaan) + ' · ' + escapeHtml(row.site) + '<br>'
            + escapeHtml(row.divisi) + '</p>'
            + '<div class="mt-10">' + renderConditions(row.conditions) + '</div>'
            + '<p class="text-xs text-secondary-light mt-8 mb-0">Terkonfirmasi: ' + escapeHtml(row.tanggal_terkonfirmasi || '-')
            + (row.tindakan ? ' · Tindakan: ' + escapeHtml(row.tindakan) : '') + '</p>'
            + '</div>';

        html += '<div class="col-md-4"><h6 class="text-sm fw-bold mb-8">Olahraga</h6>';
        if (row.exercise && row.exercise.has_data) {
            html += '<ul class="list-unstyled text-sm mb-0">'
                + '<li>Jumlah log: <strong>' + formatNumJs(row.exercise.log_count) + '</strong></li>'
                + '<li>Rata-rata durasi: <strong>' + formatNumJs(row.exercise.avg_durasi_menit, 1) + ' menit</strong></li>'
                + '<li>Rata-rata langkah: <strong>' + formatNumJs(row.exercise.avg_langkah) + '</strong></li>'
                + '<li>Rata-rata kalori: <strong>' + formatNumJs(row.exercise.avg_kalori) + ' kkal</strong></li>'
                + '<li>Rata-rata denyut jantung: <strong>' + formatNumJs(row.exercise.avg_denyut_jantung) + ' bpm</strong></li>'
                + '<li>Aktivitas terakhir: <strong>' + escapeHtml(row.exercise.aktivitas_terakhir || '-') + '</strong></li>'
                + '</ul>';
        } else {
            html += '<p class="text-sm text-secondary-light mb-0">Belum ada data olahraga (Data Well Being) untuk SID ini.</p>';
        }
        html += '</div>';

        html += '<div class="col-md-3"><h6 class="text-sm fw-bold mb-8">Makan (30 hari)</h6>';
        if (row.diet && row.diet.has_data) {
            html += '<ul class="list-unstyled text-sm mb-0">'
                + '<li>Rata-rata kalori: <strong>' + formatNumJs(row.diet.avg_calories) + ' kkal/hari</strong></li>'
                + '<li>Hari log: <strong>' + formatNumJs(row.diet.days_logged) + '</strong></li>'
                + '<li>Status: <strong>' + (row.diet.has_poor_diet ? 'Perlu Perhatian' : 'Baik') + '</strong></li>'
                + '</ul>';
            if (row.diet.alert_codes && row.diet.alert_codes.length) {
                html += '<div class="mt-8">' + row.diet.alert_codes.map(function (code) {
                    return '<span class="hn-ci-badge" style="background:#FEF3C7;color:#B45309;">' + escapeHtml(code) + '</span>';
                }).join('') + '</div>';
            }
        } else {
            html += '<p class="text-sm text-secondary-light mb-0">Belum ada log makan BeWell dalam 30 hari terakhir.</p>';
        }
        html += '</div></div>';

        return html;
    }

    tableEl.addEventListener('click', function (event) {
        var btn = event.target.closest('.hn-ci-detail-btn');
        if (!btn || !detailModal) return;
        var id = btn.getAttribute('data-id');

        detailBody.innerHTML = '';
        detailLoading.classList.remove('d-none');
        detailModal.show();

        fetch(detailBase + '/' + id, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.ok ? res.json() : Promise.reject();
        }).then(function (row) {
            detailBody.innerHTML = renderDetail(row);
        }).catch(function () {
            detailBody.innerHTML = '<p class="text-danger-main text-sm mb-0">Gagal memuat detail.</p>';
        }).finally(function () {
            detailLoading.classList.add('d-none');
        });
    });
})();
</script>
@endsection

@section('content')
@php
  $kpiTop = $kpiTop ?? [];
  $kpiConditions = $kpiConditions ?? [];
  $conditionAnalysis = $conditionAnalysis ?? [];
  $associationMatrix = $associationMatrix ?? ['rows' => [], 'cols' => [], 'cells' => []];
  $macroChart = $macroChart ?? ['tabs' => []];
  $employeeTable = $employeeTable ?? ['tabs' => []];
  $filterOptions = $filterOptions ?? ['sites' => [], 'companies' => []];
  $coverage = $coverage ?? ['total_mcu' => 0, 'total_nutrition' => 0, 'matched_total' => 0, 'matched_pct_of_mcu' => 0];

  $corrLevelColor = static function (int $level): string {
      return match ($level) {
          4 => '#DC2626',
          3 => '#F86624',
          2 => '#FCD34D',
          1 => '#FDE68A',
          default => '#E2E8F0',
      };
  };
  // Format Indonesia (titik ribuan, koma desimal) — number_format() bawaan
  // Blade default-nya format US (koma ribuan, titik desimal).
  $fmt = static fn (mixed $value, int $decimals = 0): string => number_format((float) $value, $decimals, ',', '.');
@endphp

<div class="hn-header mb-16">
  <div class="hn-header__title">
    <h6 class="mb-4">MCU x Nutrisi</h6>
    <p class="text-sm text-secondary-light mb-0">Analisis kondisi kesehatan karyawan berdasarkan hasil MCU dan pola konsumsi nutrisi</p>
  </div>
  <div class="hn-toolbar">
    <button type="button" class="hn-filter-chip">
      <iconify-icon icon="solar:calendar-outline"></iconify-icon>
      {{ $dateRangeLabel ?? '-' }}
    </button>
    <div class="hn-select-wrap">
      <iconify-icon icon="solar:map-point-linear" class="hn-select-wrap__icon"></iconify-icon>
      <select class="form-select hn-filter-select">
        <option>Semua Site</option>
        @foreach ($filterOptions['sites'] as $site)
          <option>{{ $site }}</option>
        @endforeach
      </select>
    </div>
    <div class="hn-select-wrap">
      <iconify-icon icon="solar:buildings-2-linear" class="hn-select-wrap__icon"></iconify-icon>
      <select class="form-select hn-filter-select">
        <option>Semua Perusahaan</option>
        @foreach ($filterOptions['companies'] as $company)
          <option>{{ $company }}</option>
        @endforeach
      </select>
    </div>
    <a href="{{ route('evaluasi-well.health-nutrition.export') }}" class="btn btn-success-600 hn-export-btn">
      <iconify-icon icon="solar:export-bold"></iconify-icon>
      Export
      <iconify-icon icon="solar:alt-arrow-down-linear" class="text-sm"></iconify-icon>
    </a>
  </div>
</div>

{{-- Data quality: cakupan & matched records — jangan anggap kosong = 0 --}}
<div class="hn-coverage-card d-flex flex-wrap align-items-center gap-16 mb-16">
  <div class="hn-coverage-item d-flex align-items-center gap-10">
    <span class="hn-coverage-icon hn-coverage-icon--green"><iconify-icon icon="solar:users-group-rounded-bold"></iconify-icon></span>
    <div>
      <div class="hn-coverage-value">{{ $fmt($coverage['total_mcu']) }}</div>
      <div class="hn-coverage-label">MCU records</div>
    </div>
  </div>
  <span class="hn-coverage-divider"></span>
  <div class="hn-coverage-item d-flex align-items-center gap-10">
    <span class="hn-coverage-icon hn-coverage-icon--blue"><iconify-icon icon="solar:notebook-bold"></iconify-icon></span>
    <div>
      <div class="hn-coverage-value">{{ $fmt($coverage['total_nutrition']) }}</div>
      <div class="hn-coverage-label">Nutrition records</div>
    </div>
  </div>
  <span class="hn-coverage-divider"></span>
  <div class="hn-coverage-item d-flex align-items-center gap-10">
    <span class="hn-coverage-icon hn-coverage-icon--green"><iconify-icon icon="solar:link-bold"></iconify-icon></span>
    <div>
      <div class="hn-coverage-value d-flex align-items-center gap-6">
        {{ $fmt($coverage['matched_total']) }} <span class="text-xs fw-medium text-secondary-light">({{ $fmt($coverage['matched_pct_of_mcu'], 1) }}%)</span>
      </div>
      <div class="hn-coverage-label d-flex align-items-center gap-4">
        Successfully matched
        <iconify-icon icon="solar:info-circle-linear" data-bs-toggle="tooltip" title="Dipakai sebagai denominator untuk semua analisis MCU × Nutrisi di bawah."></iconify-icon>
      </div>
    </div>
  </div>
  <span class="hn-coverage-divider d-none d-xl-block"></span>
  <div class="hn-coverage-desc d-none d-xl-block">
    Data ini digunakan untuk semua analisis<br>MCU × Nutrisi di bawah.
  </div>
  <div class="hn-quality-box hn-quality-box--{{ $coverage['quality_level'] }} ms-auto">
    <span class="hn-quality-box__icon"><iconify-icon icon="{{ $coverage['quality_level'] === 'good' ? 'solar:shield-check-bold' : 'solar:danger-triangle-bold' }}"></iconify-icon></span>
    <div class="min-w-0">
      <div class="hn-quality-box__title">{{ $coverage['quality_title'] }}</div>
      <div class="hn-quality-box__desc">{{ $coverage['quality_message'] }}</div>
    </div>
    <iconify-icon icon="solar:alt-arrow-right-linear" class="hn-quality-box__chevron"></iconify-icon>
  </div>
</div>

<ul class="nav hn-tabs mb-16" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#hnTabDashboard" type="button" role="tab">
      <iconify-icon icon="solar:widget-5-bold"></iconify-icon> Dashboard
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#hnTabRingkasan" type="button" role="tab">
      <iconify-icon icon="solar:chart-square-linear"></iconify-icon> Ringkasan
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#hnTabKritis" type="button" role="tab">
      <iconify-icon icon="solar:shield-cross-bold"></iconify-icon> Riwayat Penyakit Kritis
    </button>
  </li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="hnTabDashboard" role="tabpanel">

  {{-- Banner --}}
  <div class="card wc-card hn-hero mb-20">
    <img class="wc-card__bg hn-hero__bg" src="{{ asset('evaluasi-well-assets/images/wellness/bg-calorie.png') }}" alt="" aria-hidden="true">
    <div class="card-body p-24">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-start gap-3 min-w-0">
          <span class="wc-card__head-icon">
            <iconify-icon icon="mdi:food-apple"></iconify-icon>
          </span>
          <div class="min-w-0">
            <h6 class="mb-1 fw-bold text-lg wc-card__title">Nutrisi Seimbang, Energi untuk Hari yang Lebih Baik</h6>
            <span class="wc-card__subtitle">Pantau pola makan karyawan dan keterkaitannya dengan hasil MCU secara berkala.</span>
          </div>
        </div>
        <div class="hn-hero__quote d-none d-xl-flex">
          <iconify-icon icon="mdi:format-quote-close"></iconify-icon>
          <span id="hn-hero-quote-text">Karyawan yang sehat adalah investasi terbaik untuk perusahaan yang kuat.</span>
        </div>
        <div class="d-flex align-items-center gap-8">
          <div class="wc-card__badge">
            <iconify-icon icon="solar:link-bold"></iconify-icon>
            <span>{{ $fmt($coverage['matched_total']) }} matched ({{ $fmt($coverage['matched_pct_of_mcu'], 1) }}%)</span>
          </div>
          <button type="button" id="hn-hero-quote-next" class="hn-hero__next" aria-label="Tips berikutnya">
            <iconify-icon icon="solar:arrow-right-linear"></iconify-icon>
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- 4 KPI utama --}}
  <div class="row g-3 mb-20">
    @foreach ($kpiTop as $key => $kpi)
    <div class="col-xxl-3 col-sm-6">
      <div class="card radius-8 border-0 shadow-sm h-100 hn-kpi-card">
        <div class="card-body p-20 d-flex flex-column">
          <div class="d-flex align-items-center gap-8 mb-10">
            <span class="w-32-px h-32-px rounded-circle d-flex justify-content-center align-items-center flex-shrink-0 bg-{{ $kpi['color'] }}-600">
              <iconify-icon icon="{{ $kpi['icon'] }}" class="text-white text-md mb-0"></iconify-icon>
            </span>
            <span class="fw-medium text-secondary-light text-sm text-truncate">{{ $kpi['label'] }}</span>
          </div>
          <div class="d-flex align-items-end justify-content-between gap-2 flex-grow-1">
            <div>
              <h6 class="mb-8">{{ $fmt($kpi['value']) }}</h6>
              <div class="d-flex align-items-center gap-6 flex-wrap">
                <span class="text-secondary-light text-xs">{{ isset($kpi['sub_pct']) ? $fmt($kpi['sub_pct'], 1).'% ' : '' }}</span>
              </div>
            </div>
          </div>
          <div class="hn-kpi-card__footer d-flex align-items-center gap-8 mt-16 pt-12">
            <iconify-icon icon="{{ $kpi['icon'] }}" class="text-secondary-light text-sm"></iconify-icon>
            <span class="text-secondary-light text-xs text-truncate">{{ $kpi['caption'] }}</span>
          </div>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Header seksi kondisi kesehatan --}}
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-12">
    <div class="d-flex align-items-center gap-8">
      <span class="hn-section-bar"></span>
      <iconify-icon icon="solar:heart-pulse-bold" class="text-success-main text-lg"></iconify-icon>
      <h6 class="fw-bold text-lg mb-0">Kondisi Kesehatan Karyawan (Hasil MCU)</h6>
    </div>
    <button type="button" class="hn-detail-analysis-btn" title="Analisis per kondisi — segera hadir">
      <iconify-icon icon="solar:chart-2-bold"></iconify-icon>
      Lihat Detail Analisis
      <iconify-icon icon="solar:alt-arrow-right-linear"></iconify-icon>
    </button>
  </div>

  {{-- 6 KPI kondisi --}}
  <div class="row g-3 mb-20">
    @foreach ($kpiConditions as $cond)
    <div class="col-xxl-2 col-md-4 col-sm-6">
      <div class="card radius-8 border-0 shadow-sm h-100 hn-cond-card" style="background: {{ $cond['bg'] }}">
        <div class="card-body p-16 d-flex flex-column">
          <div class="d-flex align-items-center gap-10 mb-10">
            <span class="w-40-px h-40-px rounded-circle d-flex justify-content-center align-items-center flex-shrink-0" style="background: {{ $cond['color'] }}">
              <iconify-icon icon="{{ $cond['icon'] }}" class="text-white text-lg mb-0"></iconify-icon>
            </span>
            <div class="min-w-0">
              <span class="fw-semibold text-sm d-block text-truncate" style="color: {{ $cond['color'] }}">{{ $cond['label'] }}</span>
              <span class="text-secondary-light text-xs d-block text-truncate">{{ $cond['sub_label'] }}</span>
            </div>
          </div>
          <h6 class="mb-2 text-xl" style="color: {{ $cond['color'] }}">{{ $fmt($cond['value']) }}</h6>
          <span class="text-secondary-light text-xs mb-8">{{ $fmt($cond['pct'], 1) }}% dari total MCU</span>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Association matrix + Makronutrien --}}
  <div class="row g-3 mb-20 align-items-stretch">
    <div class="col-xxl-6">
      <div class="card radius-8 border-0 shadow-sm h-100">
        <div class="card-body p-24">
          <h6 class="fw-bold text-lg mb-2">Keterkaitan Deskriptif: Hasil MCU dengan Pola Nutrisi</h6>
          <span class="text-sm text-secondary-light d-block mb-16">
            Proporsi overlap antar kelompok (bukan hasil uji statistik) — semakin gelap semakin tinggi proporsinya.
          </span>
          <div class="table-responsive">
            <table class="hn-corr-table">
              <thead>
                <tr>
                  <th></th>
                  @foreach ($associationMatrix['cols'] as $col)
                    <th>{{ $col }}</th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @foreach ($associationMatrix['rows'] as $ri => $rowLabel)
                <tr>
                  <td class="hn-corr-row-label">{{ $rowLabel }}</td>
                  @foreach ($associationMatrix['cols'] as $ci => $col)
                    @php $cell = $associationMatrix['cells'][$ri][$ci] ?? ['level' => 0, 'pct' => 0, 'count' => 0, 'denominator' => 0]; @endphp
                    <td class="hn-corr-cell"
                        style="background: {{ $corrLevelColor($cell['level']) }}"
                        data-bs-toggle="tooltip"
                        data-bs-html="true"
                        title="<strong>{{ $rowLabel }}</strong> × {{ $col }}<br>{{ $fmt($cell['count']) }} dari {{ $fmt($cell['denominator']) }} karyawan ({{ $fmt($cell['pct'], 1) }}%)"
                    ></td>
                  @endforeach
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-3 mt-12">
            <span class="text-xs fw-medium text-secondary-light">Proporsi:</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#DC2626;"></span>Sangat Tinggi</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#F86624;"></span>Tinggi</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#FCD34D;"></span>Sedang</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#FDE68A;"></span>Rendah</span>
            <span class="d-inline-flex align-items-center gap-1 text-xs"><span class="rounded-1" style="width:12px;height:12px;background:#E2E8F0;"></span>Tidak Signifikan</span>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-6">
      <div class="card radius-8 border-0 shadow-sm h-100">
        <div class="card-body p-24">
          <h6 class="fw-bold text-lg mb-16">Makronutrien Karyawan Berisiko</h6>
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

  {{-- 6 kartu analisis kondisi --}}
  <div class="row g-3 mb-20">
    @foreach ($conditionAnalysis as $card)
    <div class="col-xxl-4 col-md-6">
      <div class="card radius-8 border-0 shadow-sm h-100">
        <div class="card-body p-24 d-flex flex-column gap-12">
          <h6 class="fw-bold text-md mb-0">{{ $card['title'] }}</h6>
          <div class="d-flex align-items-center gap-12">
            <div id="hn-analysis-donut-{{ $card['key'] }}" style="width:150px;height:150px;flex-shrink:0;"></div>
            <div class="d-flex flex-column gap-8 min-w-0">
              @foreach ($card['legend'] as $item)
              <div class="d-flex align-items-center gap-8 text-sm">
                <span class="w-24-px h-24-px rounded-circle d-inline-flex justify-content-center align-items-center flex-shrink-0" style="background: {{ $item['color'] }}">
                  <iconify-icon icon="{{ $item['icon'] }}" class="text-white text-sm mb-0"></iconify-icon>
                </span>
                @if ($item['available'])
                  <span class="text-truncate">{{ $item['label'] }} <strong>{{ $fmt($item['pct'], 1) }}%</strong>{{ isset($item['count']) ? ' ('.$fmt($item['count']).')' : '' }}</span>
                @else
                  <span class="text-truncate text-secondary-light fst-italic">{{ $item['label'] }}: Data belum tersedia</span>
                @endif
              </div>
              @endforeach
            </div>
          </div>
          <div class="bg-success-focus text-success-main border border-success-100 text-sm radius-8 px-12 py-8">{{ $card['insight'] }}</div>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Perbandingan Rata-rata Asupan Nutrisi --}}
  <div class="row g-3 mb-20 align-items-stretch">
    <div class="col-xxl-8">
      <div class="card radius-8 border-0 shadow-sm h-100">
        <div class="card-body p-24">
          <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-12">
            <h6 class="fw-bold text-lg mb-0">Perbandingan Rata-rata Asupan Nutrisi</h6>
            <select class="form-select form-select-sm" style="width:auto"><option>Berisiko vs Tidak Berisiko</option></select>
          </div>
          <div id="hnComparisonCalories" class="mb-8"></div>
          <div id="hnComparisonMacros"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- Tabel Daftar Karyawan Risiko Tinggi --}}
  <div class="card radius-8 border-0 shadow-sm mb-20">
    <div class="card-header border-bottom bg-base py-16 px-24">
      <h6 class="text-lg fw-semibold mb-0">Daftar Karyawan dengan Risiko Tinggi</h6>
    </div>
    <div class="card-body p-24">
      <div class="hn-emp-tabs mb-16">
        @foreach ($employeeTable['tabs'] as $tabKey => $tab)
          <button type="button" class="hn-emp-tab {{ $loop->first ? 'active' : '' }}" data-tab="{{ $tabKey }}">{{ $tab['label'] }} ({{ $fmt($tab['total']) }})</button>
        @endforeach
      </div>
      <div class="bg-neutral-50 border radius-8 p-16 mb-20">
        <div class="row g-3 align-items-end">
          <div class="col-lg-2 col-md-6">
            <label for="hn-emp-site" class="form-label text-sm fw-medium mb-6">Site</label>
            <select id="hn-emp-site" class="form-select form-select-sm">
              <option value="">Semua Site</option>
              @foreach ($filterOptions['sites'] as $site)
                <option value="{{ $site }}">{{ $site }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label for="hn-emp-company" class="form-label text-sm fw-medium mb-6">Perusahaan</label>
            <select id="hn-emp-company" class="form-select form-select-sm">
              <option value="">Semua Perusahaan</option>
              @foreach ($filterOptions['companies'] as $company)
                <option value="{{ $company }}">{{ $company }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label for="hn-emp-status" class="form-label text-sm fw-medium mb-6">Status Nutrisi</label>
            <select id="hn-emp-status" class="form-select form-select-sm">
              <option value="">Semua</option>
              <option value="Perlu Intervensi">Perlu Intervensi</option>
              <option value="Pantau">Pantau</option>
              <option value="Baik">Baik</option>
              <option value="Data belum tersedia">Data belum tersedia</option>
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <a id="hn-export-btn" href="{{ route('evaluasi-well.health-nutrition.export') }}" class="btn btn-sm btn-success-600 w-100 d-inline-flex align-items-center justify-content-center gap-1">
              <iconify-icon icon="solar:file-download-bold"></iconify-icon> Export
            </a>
          </div>
        </div>
        <span class="text-xs text-secondary-light d-block mt-8">Pencarian nama/NIK/perusahaan tersedia lewat kotak "Cari" di atas tabel.</span>
      </div>
      <div class="table-responsive">
        <table id="healthNutritionTable" class="table bordered-table mb-0 w-100" style="width:100%">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama Karyawan</th>
              <th>Perusahaan</th>
              <th>Site</th>
              <th>Departemen</th>
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
  {{-- <div class="hn-bottom-banner d-flex flex-wrap align-items-center justify-content-between gap-4 mb-4">
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
  </div> --}}

</div>

<div class="tab-pane fade" id="hnTabRingkasan" role="tabpanel">
  <div class="card radius-8 border-0 shadow-sm">
    <div class="card-body p-24">
      <h6 class="fw-semibold mb-12">Ringkasan</h6>
      <p class="text-sm text-secondary-light">
        Dari {{ $fmt($kpiTop['total_karyawan']['value'] ?? 0) }} karyawan yang mengikuti MCU dan
        {{ $fmt($coverage['matched_total']) }} yang datanya berhasil di-matched dengan log nutrisi ({{ $fmt($coverage['matched_pct_of_mcu'], 1) }}%),
        {{ $fmt($kpiTop['karyawan_berisiko']['value'] ?? 0) }} ({{ $fmt($kpiTop['karyawan_berisiko']['sub_pct'] ?? 0, 1) }}%) ditemukan berisiko metabolik.
        {{ $fmt($kpiTop['target_kalori']['sub_pct'] ?? 0, 1) }}% dari matched records telah memenuhi target kalori harian.
      </p>
      <div class="row g-3 mt-8">
        @foreach ($kpiConditions as $cond)
        <div class="col-md-4 col-sm-6">
          <div class="d-flex align-items-center gap-8">
            <span class="w-40-px h-40-px rounded-circle d-flex justify-content-center align-items-center flex-shrink-0" style="background: {{ $cond['color'] }}"><iconify-icon icon="{{ $cond['icon'] }}" class="text-white text-lg mb-0"></iconify-icon></span>
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

<div class="tab-pane fade" id="hnTabKritis" role="tabpanel">
  @php
    $ciKpi = $criticalIllness['kpi'] ?? ['total_karyawan' => 0, 'raw_sid_total' => 0, 'with_exercise' => 0, 'with_exercise_pct' => 0, 'with_diet' => 0, 'with_diet_pct' => 0, 'poor_diet' => 0, 'poor_diet_pct' => 0];
    $ciByCondition = $criticalIllness['byCondition'] ?? [];
    $ciFilterOptions = $criticalIllness['filterOptions'] ?? ['sites' => [], 'companies' => [], 'conditions' => []];
    $ciUp = $criticalIllness['up'] ?? false;
  @endphp

  @unless ($ciUp)
  <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 radius-8 mb-20 py-12 px-16 text-sm">
    Koneksi ke ClickHouse (behealth) atau BeWell sedang tidak tersedia — data riwayat penyakit kritis tidak dapat ditampilkan saat ini.
  </div>
  @endunless

  <p class="text-sm text-secondary-light mb-20">
    Riwayat penyakit kritis TERKONFIRMASI (sumber: ClickHouse <code>behealth.clean_data_konfirmasi_penyakit_kritis</code>),
    dipasangkan dengan ringkasan olahraga (<code>behealth.clean_data_data_well_being</code>) dan pola makan dari BeWell —
    hanya karyawan AKTIF yang berhasil ter-matched dengan SID yang ditampilkan.
  </p>

  <div class="row gy-4 mb-20">
    <div class="col-xxl-3 col-sm-6">
      <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
        <div class="card-body p-0">
          <div class="d-flex align-items-center gap-2">
            <span class="mb-0 w-48-px h-48-px bg-danger-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
              <iconify-icon icon="solar:shield-cross-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="mb-2 fw-medium text-secondary-light text-sm">Karyawan Riwayat Penyakit Kritis</span>
              <h6 class="fw-semibold mb-0">{{ $fmt($ciKpi['total_karyawan']) }}</h6>
              <span class="text-xs text-secondary-light">dari {{ $fmt($ciKpi['raw_sid_total']) }} SID terkonfirmasi di behealth</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-3 col-sm-6">
      <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
        <div class="card-body p-0">
          <div class="d-flex align-items-center gap-2">
            <span class="mb-0 w-48-px h-48-px bg-success-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
              <iconify-icon icon="solar:running-round-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="mb-2 fw-medium text-secondary-light text-sm">Ada Data Olahraga</span>
              <h6 class="fw-semibold mb-0">{{ $fmt($ciKpi['with_exercise']) }} <span class="text-xs text-secondary-light">({{ $fmt($ciKpi['with_exercise_pct'], 1) }}%)</span></h6>
              <span class="text-xs text-secondary-light">tercatat di Data Well Being</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-3 col-sm-6">
      <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
        <div class="card-body p-0">
          <div class="d-flex align-items-center gap-2">
            <span class="mb-0 w-48-px h-48-px bg-info-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
              <iconify-icon icon="solar:cup-hot-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="mb-2 fw-medium text-secondary-light text-sm">Ada Log Makan (30 hari)</span>
              <h6 class="fw-semibold mb-0">{{ $fmt($ciKpi['with_diet']) }} <span class="text-xs text-secondary-light">({{ $fmt($ciKpi['with_diet_pct'], 1) }}%)</span></h6>
              <span class="text-xs text-secondary-light">dari BeWell food_analyses</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xxl-3 col-sm-6">
      <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
        <div class="card-body p-0">
          <div class="d-flex align-items-center gap-2">
            <span class="mb-0 w-48-px h-48-px bg-warning-main flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
              <iconify-icon icon="solar:danger-triangle-bold" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="mb-2 fw-medium text-secondary-light text-sm">Pola Makan Berisiko</span>
              <h6 class="fw-semibold mb-0">{{ $fmt($ciKpi['poor_diet']) }} <span class="text-xs text-secondary-light">({{ $fmt($ciKpi['poor_diet_pct'], 1) }}%)</span></h6>
              <span class="text-xs text-secondary-light">dari yang punya log makan</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  @if (count($ciByCondition))
  <div class="card radius-8 border-0 shadow-sm mb-20">
    <div class="card-body p-24">
      <h6 class="fw-semibold mb-12 text-sm">Komposisi Penyakit Terkonfirmasi</h6>
      <div class="d-flex flex-wrap gap-2">
        @foreach ($ciByCondition as $cond)
          <span class="hn-ci-badge">{{ $cond['label'] }} — {{ $fmt($cond['total']) }} ({{ $fmt($cond['pct'], 1) }}%)</span>
        @endforeach
      </div>
      <span class="text-xs text-secondary-light d-block mt-10">
        Satu karyawan dapat memiliki lebih dari satu kondisi terkonfirmasi, sehingga total di atas bisa lebih besar dari jumlah karyawan.
      </span>
    </div>
  </div>
  @endif

  <div class="card radius-8 border-0 shadow-sm">
    <div class="card-body p-24">
      <div class="bg-neutral-50 border radius-8 p-16 mb-20">
        <div class="row g-3 align-items-end">
          <div class="col-lg-3 col-md-6">
            <label for="hn-ci-condition" class="form-label text-sm fw-medium mb-6">Penyakit Terkonfirmasi</label>
            <select id="hn-ci-condition" class="form-select form-select-sm">
              <option value="">Semua Kondisi</option>
              @foreach ($ciFilterOptions['conditions'] as $cond)
                <option value="{{ $cond }}">{{ $cond }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-3 col-md-6">
            <label for="hn-ci-site" class="form-label text-sm fw-medium mb-6">Site</label>
            <select id="hn-ci-site" class="form-select form-select-sm">
              <option value="">Semua Site</option>
              @foreach ($ciFilterOptions['sites'] as $site)
                <option value="{{ $site }}">{{ $site }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-3 col-md-6">
            <label for="hn-ci-company" class="form-label text-sm fw-medium mb-6">Perusahaan</label>
            <select id="hn-ci-company" class="form-select form-select-sm">
              <option value="">Semua Perusahaan</option>
              @foreach ($ciFilterOptions['companies'] as $company)
                <option value="{{ $company }}">{{ $company }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <span class="text-xs text-secondary-light d-block mt-8">Pencarian nama/NIK/SID/perusahaan tersedia lewat kotak "Cari" di atas tabel.</span>
      </div>
      <div class="table-responsive">
        <table id="criticalIllnessTable" class="table bordered-table mb-0 w-100" style="width:100%">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama Karyawan</th>
              <th>Perusahaan / Site</th>
              <th>Divisi</th>
              <th>Penyakit Terkonfirmasi</th>
              <th>Tgl Terkonfirmasi</th>
              <th>Olahraga</th>
              <th>Makan</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>

{{-- Modal Detail Karyawan --}}
<div class="modal fade" id="hnEmployeeDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content radius-8 border-0 shadow-lg">
      <div class="modal-header border-bottom py-16 px-24">
        <h5 class="modal-title fw-bold text-lg mb-0">Detail Karyawan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24 position-relative">
        <div id="hn-detail-loading" class="text-center py-40 d-none">
          <div class="spinner-border text-primary-600" role="status" aria-hidden="true"></div>
          <p class="text-sm text-secondary-light mt-12 mb-0">Memuat detail…</p>
        </div>
        <div id="hn-detail-body"></div>
      </div>
      <div class="modal-footer border-top py-16 px-24">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

{{-- Modal Detail Riwayat Penyakit Kritis --}}
<div class="modal fade" id="hnCriticalIllnessDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content radius-8 border-0 shadow-lg">
      <div class="modal-header border-bottom py-16 px-24">
        <h5 class="modal-title fw-bold text-lg mb-0">Detail Riwayat Penyakit Kritis</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24 position-relative">
        <div id="hn-ci-detail-loading" class="text-center py-40 d-none">
          <div class="spinner-border text-primary-600" role="status" aria-hidden="true"></div>
          <p class="text-sm text-secondary-light mt-12 mb-0">Memuat detail…</p>
        </div>
        <div id="hn-ci-detail-body"></div>
      </div>
      <div class="modal-footer border-top py-16 px-24">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection
