@extends('ohs-score-card.layouts.app')

@section('title', 'Blindspot TBC')

@section('css')
<style>

  /* ---- Matriks temuan ----
     Rupanya sengaja disamakan dengan matriks di halaman Ratio TBC & GR,
     tetapi skala warnanya terbalik: di sini angka besar berarti buruk. */
  .bs-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .bs-matrix {
    width: 100%; min-width: 760px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .bs-matrix th, .bs-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .bs-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .bs-matrix thead th.bs-th-last { background: #2E90FA !important; color: #fff !important; }
  .bs-matrix .bs-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .bs-matrix .bs-mitra {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 150px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .bs-matrix thead .bs-site, .bs-matrix thead .bs-mitra { z-index: 4; background: #F8FAFC; }
  .bs-matrix .bs-total { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  /* Untuk blindspot, naik berarti memburuk, jadi panah atas diwarnai merah. */
  .bs-matrix .bs-trend--up { color: #DC2626; font-weight: 800; }
  .bs-matrix .bs-trend--down { color: #16A34A; font-weight: 800; }
  .bs-matrix .bs-trend--flat { color: #94A3B8; font-weight: 800; }
  .bs-matrix .bs-cell {
    font-weight: 700; color: #fff; min-width: 54px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    font-variant-numeric: tabular-nums;
  }
  .bs-matrix .bs-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(220,38,38,.3);
    position: relative; z-index: 1;
  }
  .bs-matrix .bs-empty { background: #F1F5F9; color: #CBD5E1 !important; border-radius: 6px; }
  /* Nol temuan itu kabar baik, jadi warnanya hijau, bukan abu-abu kosong. */
  .bs-k0 { background: #16A34A; }
  .bs-k1 { background: #86C96B; }
  .bs-k2 { background: #F2C230; color: #1F2937 !important; }
  .bs-k3 { background: #F08C2E; }
  .bs-k4 { background: #E0484A; }

  /* Deskripsi temuan panjang-panjang; dipotong agar baris tabel tetap rapi. */
  .bs-deskripsi {
    display: block; max-width: 420px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Blindspot TBC</h6>
    <div class="text-secondary-light text-sm mt-4">
      Temuan TBC di area sebuah perusahaan yang justru dilaporkan pihak lain
    </div>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('ohs-score-card.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li class="text-secondary-light">/</li>
    <li class="fw-medium text-primary-600">Blindspot TBC</li>
  </ul>
</div>

@php
  // Satu tab per (kumpulan data x jenis panel); tab pertama yang aktif.
  $tabs = [];
  foreach ($datasets as $ds) {
      $tabs[] = ['key' => $ds['slug'] . '-ringkasan', 'label' => 'Ringkasan ' . $ds['label'], 'kind' => 'ringkasan', 'ds' => $ds];
      $tabs[] = ['key' => $ds['slug'] . '-data', 'label' => 'Data ' . $ds['label'], 'kind' => 'data', 'ds' => $ds];
  }
@endphp

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="bs-tab" role="tablist">
  @foreach ($tabs as $i => $tab)
    <li class="nav-item" role="presentation">
      <button class="nav-link px-24 py-10 text-md text-center radius-8 {{ $i === 0 ? 'active' : '' }}"
              id="bs-tab-{{ $tab['key'] }}"
              data-bs-toggle="pill" data-bs-target="#bs-pane-{{ $tab['key'] }}"
              type="button" role="tab" aria-controls="bs-pane-{{ $tab['key'] }}"
              aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
        {{ $tab['label'] }}
      </button>
    </li>
  @endforeach
</ul>

<div class="tab-content">
  @foreach ($tabs as $i => $tab)
    <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}"
         id="bs-pane-{{ $tab['key'] }}" role="tabpanel">
      @include('ohs-score-card.blindspot-tbc.partials._' . $tab['kind'], ['ds' => $tab['ds']])
    </div>
  @endforeach
</div>
@endsection

@section('page-scripts')
<script>
// ---- Tab Ringkasan ----------------------------------------------------------
// Satu pabrik, dipakai untuk tiap kumpulan data. Semua pencarian elemen
// dilakukan di dalam root agar dua salinan tidak saling menimpa.
window.bsOverview = (function () {
    'use strict';

    var PALETTE = ['#E0484A', '#FF9F29', '#F2C230', '#8252E9', '#487FFF', '#00B8F2', '#45B369', '#EF4A00'];

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmtNum(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

    function fmtPct(value) {
        return Number(value || 0).toLocaleString('id-ID', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        }) + '%';
    }

    // Skala warna matriks persentase. Berbeda dari halaman Ratio TBC & GR:
    // di sini angka kecil yang hijau, karena yang diukur adalah yang luput.
    function tierPersen(pct) {
        if (pct <= 0) return 'bs-k0';
        if (pct <= 2) return 'bs-k1';
        if (pct <= 5) return 'bs-k2';
        if (pct <= 10) return 'bs-k3';
        return 'bs-k4';
    }

    /** Skala untuk cacah temuan; arahnya sama, hanya satuannya berbeda. */
    function tierTemuan(jumlah) {
        if (jumlah <= 0) return 'bs-k0';
        if (jumlah <= 2) return 'bs-k1';
        if (jumlah <= 5) return 'bs-k2';
        if (jumlah <= 10) return 'bs-k3';
        return 'bs-k4';
    }

    return function create(root) {
        var overviewUrl = root.dataset.url;
        var charts = { pic: null, pelapor: null, monthly: null };
        var loaded = false;

        var filterEls = Array.prototype.slice.call(root.querySelectorAll('.bs-filter'));
        var statusEl = root.querySelector('[data-bs-el="status"]');

        function el(name) {
            return root.querySelector('[data-bs-el="' + name + '"]');
        }

        function currentFilters() {
            var out = {};
            filterEls.forEach(function (node) {
                if (node.value) { out[node.dataset.column] = node.value; }
            });
            return out;
        }

        // ---- Kartu ringkasan utama -----------------------------------------
        function renderKpi(k) {
            var cards = [
                k.ukuran === 'persen'
                    ? {
                        grad: 'bg-gradient-end-5', icon: 'solar:eye-closed-outline', dot: 'bg-danger-main',
                        label: 'Rata-rata Blindspot', value: fmtPct(k.rata_persen),
                        foot: 'Tertinggi ' + fmtPct(k.puncak_persen) + ' di antara ' + fmtNum(k.kombinasi)
                            + ' pasangan site &amp; perusahaan'
                    }
                    : {
                        grad: 'bg-gradient-end-5', icon: 'solar:eye-closed-outline', dot: 'bg-danger-main',
                        label: 'Temuan Blindspot', value: fmtNum(k.temuan),
                        foot: 'Persentase resminya belum tersedia, jadi yang dihitung cacah temuan'
                    },
                k.ukuran === 'persen'
                    ? {
                        grad: 'bg-gradient-end-3', icon: 'solar:danger-triangle-outline', dot: 'bg-warning-main',
                        label: 'Di Atas Ambang', value: fmtNum(k.di_atas_ambang),
                        foot: 'Rata-ratanya lebih dari ' + k.ambang + '%'
                    }
                    : {
                        grad: 'bg-gradient-end-3', icon: 'solar:buildings-outline', dot: 'bg-warning-main',
                        label: 'Perusahaan PIC', value: fmtNum(k.temuan_mitra),
                        foot: fmtNum(k.temuan_kombinasi) + ' pasangan site &amp; perusahaan'
                    },
                {
                    grad: 'bg-gradient-end-1', icon: 'solar:calendar-outline', dot: 'bg-primary-600',
                    label: 'Cakupan', value: fmtNum(k.bulan_count) + ' bulan',
                    foot: fmtNum(k.site_count) + ' site, ' + fmtNum(k.mitra_count) + ' perusahaan PIC'
                },
                k.ukuran === 'persen'
                    ? {
                        grad: 'bg-gradient-end-2', icon: 'solar:clipboard-list-outline', dot: 'bg-yellow',
                        label: 'Temuan Tercatat', value: fmtNum(k.temuan),
                        foot: fmtNum(k.pic_count) + ' PIC, dari ' + fmtNum(k.temuan_kombinasi)
                            + ' pasangan yang sudah ada rinciannya'
                    }
                    : {
                        grad: 'bg-gradient-end-2', icon: 'solar:user-id-outline', dot: 'bg-yellow',
                        label: 'PIC Terlibat', value: fmtNum(k.pic_count),
                        foot: fmtNum(k.pelapor_count) + ' perusahaan pelapor'
                    }
            ];

            el('kpi').innerHTML = cards.map(function (c) {
                return '<div class="col-xxl-3 col-sm-6">'
                    + '<div class="card p-3 shadow-2 radius-8 border input-form-light h-100 ' + c.grad + '">'
                    +   '<div class="card-body p-0">'
                    +     '<div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">'
                    +       '<div class="d-flex align-items-center gap-2">'
                    +         '<span class="mb-0 w-48-px h-48-px ' + c.dot + ' text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">'
                    +           '<iconify-icon icon="' + c.icon + '" class="icon"></iconify-icon>'
                    +         '</span>'
                    +         '<div>'
                    +           '<span class="mb-2 fw-medium text-secondary-light text-sm">' + escapeHtml(c.label) + '</span>'
                    +           '<h6 class="fw-semibold">' + c.value + '</h6>'
                    +         '</div>'
                    +       '</div>'
                    +     '</div>'
                    +     '<p class="text-sm mb-0">' + c.foot + '</p>'
                    +   '</div>'
                    + '</div></div>';
            }).join('');
        }

        // ---- Rata-rata per site ---------------------------------------------
        function renderPerSite(list, k) {
            var host = el('per-site');
            var persen = k.ukuran === 'persen';
            var angka = function (v) { return persen ? fmtPct(v) : fmtNum(v) + ' temuan'; };

            el('per-site-judul').textContent = persen ? 'Rata-rata per Site' : 'Temuan per Site';
            el('per-site-sub').textContent = persen
                ? 'Ambang ' + k.ambang + '%'
                : 'Dihitung dari cacah temuan';

            if (!list.length) {
                host.innerHTML = '<p class="text-secondary-light text-sm text-center py-24 mb-0">Tidak ada data.</p>';
                return;
            }

            var puncak = Math.max.apply(null, list.map(function (s) { return s.nilai; })) || 1;

            host.innerHTML = list.map(function (s, i) {
                return '<div class="' + (i ? 'mt-20' : '') + '">'
                    + '<div class="d-flex align-items-center justify-content-between mb-8">'
                    +   '<span class="text-sm fw-semibold">' + escapeHtml(s.site) + '</span>'
                    +   '<span class="text-sm fw-medium text-secondary-light">' + angka(s.nilai) + '</span>'
                    + '</div>'
                    + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                    +   '<div class="progress-bar ' + (s.di_atas_ambang ? 'bg-danger-main' : 'bg-success-main')
                    +     ' rounded-pill" role="progressbar"'
                    +     ' style="width:' + (s.nilai / puncak * 100) + '%" aria-valuenow="' + s.nilai + '"'
                    +     ' aria-valuemin="0" aria-valuemax="' + puncak + '"></div>'
                    + '</div>'
                    + '<span class="text-xs text-secondary-light">' + fmtNum(s.jumlah)
                    +   ' perusahaan · tertinggi ' + angka(s.puncak)
                    +   (s.di_atas_ambang ? ' · di atas ambang ' + k.ambang + '%' : '') + '</span>'
                    + '</div>';
            }).join('');
        }

        // ---- Peringkat perusahaan PIC ---------------------------------------
        function renderPerMitra(list, k) {
            var body = el('per-mitra');
            var persen = k.ukuran === 'persen';
            var angka = function (v) { return persen ? fmtPct(v) : fmtNum(v); };

            el('per-mitra-kol1').textContent = persen ? 'Rata-rata' : 'Temuan';
            el('per-mitra-sub').textContent = persen
                ? 'Diurutkan dari persentase tertinggi'
                : 'Diurutkan dari temuan terbanyak';

            if (!list.length) {
                body.innerHTML = '<tr><td colspan="3" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>';
                return;
            }
            body.innerHTML = list.map(function (m) {
                var kelas = m.di_atas_ambang
                    ? 'bg-danger-focus text-danger-main'
                    : 'bg-success-focus text-success-main';

                return '<tr>'
                    + '<td><span class="text-md fw-medium">' + escapeHtml(m.mitra) + '</span>'
                    +   '<span class="d-block text-xs text-secondary-light">' + fmtNum(m.jumlah)
                    +   ' site</span></td>'
                    + '<td class="text-end"><span class="' + kelas
                    +   ' px-8 py-2 rounded-pill fw-medium text-xs">' + angka(m.nilai) + '</span></td>'
                    + '<td class="text-end text-secondary-light">' + angka(m.puncak) + '</td>'
                    + '</tr>';
            }).join('');
        }

        function renderLegend() {
            var items = [
                { color: '#16A34A', label: '0%' },
                { color: '#86C96B', label: 'sampai 2%' },
                { color: '#F2C230', label: '2–5%' },
                { color: '#F08C2E', label: '5–10%' },
                { color: '#E0484A', label: 'lebih dari 10%' }
            ];

            el('legend').innerHTML = items.map(function (it) {
                return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                    + '<span class="rounded-1" style="width:14px;height:14px;background:' + it.color + ';"></span>'
                    + escapeHtml(it.label) + '</span>';
            }).join('');
        }

        /**
         * Satu penggambar untuk dua matriks: persentase dan cacah temuan.
         * Keduanya sebentuk, yang berbeda hanya cara memformat angkanya.
         */
        function renderMatrix(name, payload, opsi) {
            var table = el(name);
            var thead = table.querySelector('thead');
            var tbody = table.querySelector('tbody');
            var months = payload.months || [];
            var rows = payload.rows || [];

            if (!payload.tersedia) {
                thead.innerHTML = '';
                tbody.innerHTML = '<tr><td class="text-center py-24 text-secondary-light">'
                    + 'Tabel ' + escapeHtml(payload.tabel) + ' belum berisi data untuk filter ini.'
                    + '</td></tr>';
                return;
            }

            var head = '<tr><th class="bs-site">SITE</th><th class="bs-mitra">PERUSAHAAN PIC</th>'
                + '<th>' + opsi.ringkasLabel + '</th><th>TREND</th>';
            months.forEach(function (m, i) {
                head += '<th class="' + (i === months.length - 1 ? 'bs-th-last' : '') + '">'
                    + escapeHtml(m.label) + '</th>';
            });
            thead.innerHTML = head + '</tr>';

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="' + (months.length + 4) + '" class="text-center py-24 text-secondary-light">'
                    + 'Tidak ada data untuk filter ini.</td></tr>';
                return;
            }

            // Sel site digabung dengan rowspan: satu sel untuk semua perusahaan
            // di site yang sama. Baris sudah dikelompokkan per site dari
            // server, jadi cukup menghitung panjang blok yang berurutan.
            var span = {};   // index baris pertama tiap blok -> jumlah baris
            var lewati = {}; // index baris yang tidak menulis sel site
            rows.forEach(function (row, i) {
                if (i > 0 && rows[i - 1].site === row.site) {
                    lewati[i] = true;
                    return;
                }
                var n = 1;
                while (i + n < rows.length && rows[i + n].site === row.site) { n++; }
                span[i] = n;
            });

            tbody.innerHTML = rows.map(function (row, i) {
                var html = '<tr>'
                    + (lewati[i] ? '' : '<td class="bs-site" rowspan="' + span[i] + '">'
                        + escapeHtml(row.site) + '</td>')
                    + '<td class="bs-mitra">' + escapeHtml(row.mitra) + '</td>'
                    + '<td class="bs-total" title="' + escapeHtml(opsi.ringkasTip(row)) + '">'
                    +   opsi.ringkas(row) + '</td>';

                if (row.trend === 'up') {
                    html += '<td class="bs-trend--up" title="Naik dari bulan sebelumnya, berarti memburuk">&uarr;</td>';
                } else if (row.trend === 'down') {
                    html += '<td class="bs-trend--down" title="Turun dari bulan sebelumnya, berarti membaik">&darr;</td>';
                } else if (row.trend === 'flat') {
                    html += '<td class="bs-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
                } else {
                    html += '<td class="text-secondary-light">–</td>';
                }

                row.cells.forEach(function (value, m) {
                    if (value === null) {
                        html += '<td class="bs-empty" title="' + escapeHtml(months[m].label)
                            + ': tidak ada data">–</td>';
                        return;
                    }

                    var tip = row.site + ' · ' + row.mitra + ' · ' + months[m].label + ': '
                        + opsi.sel(value) + ' ' + opsi.satuan;

                    html += '<td class="bs-cell ' + opsi.tier(value) + '" title="' + escapeHtml(tip) + '">'
                        + opsi.sel(value) + '</td>';
                });

                return html + '</tr>';
            }).join('');
        }

        // ---- Grafik ---------------------------------------------------------
        function destroyChart(key) {
            if (charts[key]) {
                charts[key].destroy();
                charts[key] = null;
            }
        }

        function emptyChart(node, text) {
            node.innerHTML = '<p class="text-secondary-light text-sm text-center py-40 mb-0">'
                + escapeHtml(text || 'Tidak ada data.') + '</p>';
        }

        /** Batang mendatar: nama PIC panjang-panjang, jadi lebih terbaca begini. */
        function renderPicChart(rows) {
            var node = el('chart-pic');
            if (!node || typeof ApexCharts === 'undefined') { return; }

            destroyChart('pic');

            if (!rows.length) { emptyChart(node); return; }
            node.innerHTML = '';

            charts.pic = new ApexCharts(node, {
                series: [{ name: 'Temuan', data: rows.map(function (r) { return r.jumlah; }) }],
                chart: { type: 'bar', height: 340, toolbar: { show: false } },
                colors: ['#E0484A'],
                plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } },
                dataLabels: {
                    enabled: true,
                    formatter: function (v) { return fmtNum(v); },
                    style: { fontSize: '11px', colors: ['#fff'] }
                },
                xaxis: {
                    categories: rows.map(function (r) { return r.label; }),
                    labels: { style: { fontSize: '11px' }, formatter: function (v) { return fmtNum(v); } }
                },
                yaxis: { labels: { style: { fontSize: '11px' }, maxWidth: 220 } },
                grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
                tooltip: {
                    y: {
                        formatter: function (v, opts) {
                            var r = rows[opts.dataPointIndex] || {};
                            return fmtNum(v) + ' temuan — ' + (r.mitra || '') + ' (' + (r.site || '') + ')';
                        }
                    }
                }
            });
            charts.pic.render();
        }

        function renderPelaporChart(rows) {
            var node = el('chart-pelapor');
            if (!node || typeof ApexCharts === 'undefined') { return; }

            destroyChart('pelapor');

            var values = rows.map(function (r) { return r.jumlah; });
            var total = values.reduce(function (a, b) { return a + b; }, 0);
            if (!values.length || total === 0) { emptyChart(node); return; }
            node.innerHTML = '';

            charts.pelapor = new ApexCharts(node, {
                series: values,
                labels: rows.map(function (r) { return r.label; }),
                colors: PALETTE,
                chart: { type: 'donut', height: 340 },
                dataLabels: { enabled: false },
                stroke: { width: 0 },
                legend: { position: 'bottom', fontSize: '11px', itemMargin: { vertical: 2 } },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '62%',
                            labels: {
                                show: true,
                                total: {
                                    show: true, showAlways: true, label: 'Temuan',
                                    formatter: function () { return fmtNum(total); }
                                }
                            }
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function (v) {
                            return fmtNum(v) + ' (' + (v / total * 100).toFixed(1) + '%)';
                        }
                    }
                }
            });
            charts.pelapor.render();
        }

        function renderMonthlyChart(payload, k) {
            var node = el('chart-monthly');
            if (!node || typeof ApexCharts === 'undefined') { return; }

            var persen = k.ukuran === 'persen';

            el('monthly-sub').textContent = persen
                ? 'Persentase blindspot per perusahaan PIC; garis yang menanjak berarti memburuk'
                : 'Jumlah temuan per perusahaan PIC; garis yang menanjak berarti memburuk';

            destroyChart('monthly');

            if (!payload.series.length) { emptyChart(node); return; }
            node.innerHTML = '';

            charts.monthly = new ApexCharts(node, {
                series: payload.series,
                chart: { type: 'line', height: 320, toolbar: { show: false }, zoom: { enabled: false } },
                colors: PALETTE,
                stroke: { curve: 'smooth', width: 3 },
                // Bulan tanpa data dikirim null; dibiarkan putus, bukan
                // disambung ke nol yang akan terbaca sebagai "tidak ada blindspot".
                forecastDataPoints: { count: 0 },
                markers: { size: 4, hover: { size: 5 } },
                dataLabels: { enabled: false },
                xaxis: {
                    categories: payload.labels,
                    labels: { style: { fontSize: '11px' } }
                },
                yaxis: {
                    min: 0,
                    title: { text: persen ? 'Blindspot' : 'Temuan', style: { fontSize: '11px' } },
                    labels: {
                        formatter: function (v) {
                            return persen ? Math.round(v) + '%' : fmtNum(Math.round(v));
                        }
                    }
                },
                legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
                grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
                tooltip: {
                    shared: true,
                    // WAJIB eksplisit: kombinasi shared + intersect melempar
                    // error sehingga grafiknya gagal dirender sama sekali.
                    intersect: false,
                    y: {
                        formatter: function (v) {
                            if (v === null) { return 'tidak ada data'; }
                            return persen ? fmtPct(v) : fmtNum(v) + ' temuan';
                        }
                    }
                }
            });
            charts.monthly.render();
        }

        /**
         * Keterangan dari server: ditampilkan apa adanya, atau disembunyikan
         * ketika tidak ada yang perlu diterangkan.
         */
        function renderCatatan(text) {
            var wrap = el('note-wrap');
            if (!wrap) { return; }

            el('note').textContent = text || '';
            wrap.classList.toggle('d-none', !text);
        }

        /** Membungkus renderer agar kegagalan satu panel tidak menjatuhkan sisanya. */
        function safe(label, fn) {
            try {
                fn();
            } catch (err) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Blindspot: panel "' + label + '" gagal dirender', err);
                }
            }
        }

        function load() {
            var params = new URLSearchParams(currentFilters());
            statusEl.textContent = 'memuat…';

            fetch(overviewUrl + (params.toString() ? '?' + params.toString() : ''), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (res) {
                    if (!res.ok) { throw new Error('HTTP ' + res.status); }
                    return res.json();
                })
                .then(function (json) {
                    var kosong = { tersedia: false, tabel: '-', months: [], rows: [] };

                    // Tiap panel dibungkus sendiri: satu panel yang gagal tidak
                    // boleh membuat seluruh dashboard tampak kosong.
                    safe('kpi', function () { renderKpi(json.kpi); });
                    safe('legend', renderLegend);
                    safe('persen', function () {
                        renderMatrix('persen', json.persen || kosong, {
                            ringkasLabel: 'RATA', satuan: 'blindspot', tier: tierPersen,
                            sel: function (v) { return Number(v).toFixed(1) + '%'; },
                            ringkas: function (row) {
                                return row.average === null ? '–' : fmtPct(row.average);
                            },
                            ringkasTip: function (row) {
                                return row.average === null
                                    ? 'Belum ada data'
                                    : 'Rata-rata ' + fmtPct(row.average) + ', tertinggi ' + fmtPct(row.puncak);
                            }
                        });
                    });
                    safe('temuan', function () {
                        renderMatrix('temuan', json.temuan || kosong, {
                            ringkasLabel: 'TOTAL', satuan: 'temuan', tier: tierTemuan,
                            sel: function (v) { return fmtNum(v); },
                            ringkas: function (row) { return fmtNum(row.total); },
                            ringkasTip: function (row) {
                                return 'Rata-rata ' + row.rata + ' temuan per bulan, tertinggi ' + row.puncak;
                            }
                        });
                    });
                    safe('per-site', function () { renderPerSite(json.per_site || [], json.kpi); });
                    safe('per-mitra', function () { renderPerMitra(json.per_mitra || [], json.kpi); });
                    safe('pic', function () { renderPicChart(json.per_pic || []); });
                    safe('pelapor', function () { renderPelaporChart(json.per_pelapor || []); });
                    safe('monthly', function () { renderMonthlyChart(json.monthly, json.kpi); });
                    safe('catatan', function () { renderCatatan(json.catatan); });

                    var k = json.kpi;
                    statusEl.textContent = (k.ukuran === 'persen'
                        ? fmtPct(k.rata_persen) + ' rata-rata · ' + k.kombinasi + ' kombinasi site/perusahaan'
                        : 'persentase resmi belum tersedia · ' + k.temuan_kombinasi + ' kombinasi site/perusahaan')
                        + ' · ' + k.bulan_count + ' bulan · ' + fmtNum(k.temuan) + ' temuan tercatat';
                    loaded = true;
                })
                .catch(function (err) {
                    statusEl.textContent = 'gagal memuat';
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('Ringkasan Blindspot TBC: gagal memuat', err);
                    }
                });
        }

        filterEls.forEach(function (node) {
            node.addEventListener('change', load);
        });

        root.querySelector('[data-bs-el="reset"]').addEventListener('click', function () {
            filterEls.forEach(function (node) { node.value = ''; });
            load();
        });

        return {
            load: load,
            // Dipanggil saat tab-nya ditampilkan: ApexCharts tidak bisa
            // mengukur elemen yang sedang tersembunyi.
            show: function () {
                if (!loaded) { load(); return; }
                Object.keys(charts).forEach(function (k) {
                    if (charts[k] && typeof charts[k].windowResizeHandler === 'function') {
                        charts[k].windowResizeHandler();
                    }
                });
            }
        };
    };
})();
</script>
<script>
// ---- Tab Data Temuan --------------------------------------------------------
window.bsDataTable = (function () {
    'use strict';

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmtNum(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

    return function create(root) {
        var tableEl = root.querySelector('[data-bs-el="table"]');
        if (!tableEl || typeof DataTable === 'undefined') {
            return null;
        }
        if (DataTable.ext) {
            DataTable.ext.errMode = 'none';
        }

        var dataUrl = root.dataset.url;
        var exportUrl = root.dataset.exportUrl;
        var filterEls = Array.prototype.slice.call(root.querySelectorAll('.bsd-filter'));
        var hintEl = root.querySelector('[data-bs-el="hint"]');

        function currentFilters() {
            var out = {};
            filterEls.forEach(function (node) {
                if (node.value) { out[node.dataset.column] = node.value; }
            });
            return out;
        }

        var table = new DataTable(tableEl, {
            processing: true,
            serverSide: true,
            searching: true,
            ordering: true,
            order: [[0, 'asc']],
            pageLength: 25,
            lengthMenu: [25, 50, 100, 200],
            autoWidth: false,
            layout: {
                topStart: 'pageLength',
                topEnd: 'search',
                bottomStart: 'info',
                bottomEnd: 'paging'
            },
            ajax: {
                url: dataUrl,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                data: function (d) {
                    var filters = currentFilters();
                    Object.keys(filters).forEach(function (key) { d[key] = filters[key]; });
                },
                dataSrc: function (json) {
                    hintEl.textContent = fmtNum(json.recordsFiltered) + ' temuan';
                    return json.data || [];
                },
                error: function (xhr, error) {
                    hintEl.textContent = 'gagal memuat';
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('Blindspot TBC: gagal memuat data', error, xhr && xhr.status);
                    }
                }
            },
            columns: [
                { data: 'site' },
                { data: 'mitra' },
                {
                    data: 'pic',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d; }
                        return '<span class="fw-semibold">' + escapeHtml(d || '-') + '</span>'
                            + '<span class="d-block text-xs text-secondary-light">'
                            + escapeHtml(row.sid_pic) + '</span>';
                    }
                },
                {
                    data: 'pelapor',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d; }
                        return '<span class="fw-medium">' + escapeHtml(d || '-') + '</span>'
                            + '<span class="d-block text-xs text-secondary-light">'
                            + escapeHtml(row.pelapor_perusahaan) + '</span>';
                    }
                },
                {
                    data: 'deskripsi',
                    orderable: false,
                    render: function (d, type) {
                        if (type !== 'display') { return d; }
                        // Teks penuh tetap ada di title; yang dipotong hanya
                        // tampilannya, supaya tinggi baris tidak meledak.
                        return '<span class="bs-deskripsi" title="' + escapeHtml(d) + '">'
                            + escapeHtml(d || '-') + '</span>';
                    }
                },
                {
                    data: 'bulan',
                    // Bulan tersimpan sebagai nama bulan Inggris, jadi
                    // mengurutkannya hanya menghasilkan urutan abjad.
                    orderable: false,
                    render: function (d, type, row) {
                        if (type !== 'display') { return d; }
                        return escapeHtml(d) + ' <span class="text-xs text-secondary-light">'
                            + escapeHtml(row.tahun) + '</span>';
                    }
                },
                { data: 'task', className: 'text-end' }
            ],
            language: {
                processing: 'Memuat…',
                lengthMenu: 'Tampilkan _MENU_ baris',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ temuan',
                infoEmpty: 'Tidak ada temuan',
                infoFiltered: '(disaring dari _MAX_ temuan)',
                search: 'Cari:',
                zeroRecords: 'Tidak ada temuan untuk filter ini.',
                paginate: { first: '«', last: '»', next: '›', previous: '‹' }
            }
        });

        filterEls.forEach(function (node) {
            node.addEventListener('change', function () { table.ajax.reload(); });
        });

        root.querySelector('[data-bs-el="reset"]').addEventListener('click', function () {
            filterEls.forEach(function (node) { node.value = ''; });
            table.search('');
            table.ajax.reload();
        });

        root.querySelectorAll('[data-bs-el="export"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var params = new URLSearchParams(currentFilters());
                var search = table.search();
                if (search) { params.set('search', search); }
                params.set('format', btn.dataset.format);
                window.location.href = exportUrl + '?' + params.toString();
            });
        });

        return {
            // Tabel dibangun saat pane-nya masih tersembunyi, jadi lebar kolom
            // dihitung ulang begitu tab-nya pertama kali dibuka.
            show: function () { table.columns.adjust(); }
        };
    };
})();
</script>
<script>
// ---- Perakitan tab ----------------------------------------------------------
(function () {
    'use strict';

    var panes = {};

    document.querySelectorAll('.bs-overview').forEach(function (root) {
        panes[root.closest('.tab-pane').id] = window.bsOverview(root);
    });

    document.querySelectorAll('.bs-datatable').forEach(function (root) {
        var instance = window.bsDataTable(root);
        if (instance) {
            panes[root.closest('.tab-pane').id] = instance;
        }
    });

    // Hanya pane yang aktif sejak awal yang langsung dimuat; sisanya menunggu
    // tab-nya dibuka, supaya halaman tidak menembak empat query sekaligus.
    var active = document.querySelector('.tab-pane.active');
    if (active && panes[active.id] && panes[active.id].load) {
        panes[active.id].load();
    }

    document.querySelectorAll('#bs-tab [data-bs-toggle="pill"]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function () {
            var id = (btn.dataset.bsTarget || '').replace('#', '');
            if (panes[id] && panes[id].show) { panes[id].show(); }
        });
    });
})();
</script>
@endsection
