@extends('ohs-score-card.layouts.app')

@section('title', 'Tidak Ada Pelaporan Melewati Batas Golden Time')

@section('css')
<style>

  /* Penanda target pada progress bar. Tidak ada padanannya di WowDash,
     jadi ditulis sendiri: satu garis tipis di posisi persentase target. */
  .gte-track { position: relative; overflow: visible; }
  .gte-track__target {
    position: absolute; top: -3px; bottom: -3px; width: 2px;
    background: var(--text-primary-light, #0F172A); opacity: .45;
  }

  /* ---- Matriks capaian bulanan ---- */
  .gte-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .gte-matrix {
    width: 100%; min-width: 820px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .gte-matrix th, .gte-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .gte-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .gte-matrix thead th.gte-th-last { background: #2E90FA !important; color: #fff !important; }
  /* Sel site di-merge dengan rowspan, jadi diratakan ke tengah secara vertikal
     supaya labelnya berada di tengah blok site-nya. */
  .gte-matrix .gte-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .gte-matrix .gte-mitra {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 96px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .gte-matrix thead .gte-site, .gte-matrix thead .gte-mitra { z-index: 4; background: #F8FAFC; }
  .gte-matrix .gte-avg { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  /* Kinerja: naik berarti membaik, jadi panah atas hijau. */
  .gte-matrix .gte-trend--up { color: #16A34A; font-weight: 800; }
  .gte-matrix .gte-trend--down { color: #DC2626; font-weight: 800; }
  .gte-matrix .gte-trend--flat { color: #94A3B8; font-weight: 800; }
  .gte-matrix .gte-cell {
    font-weight: 700; color: #fff; min-width: 58px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    font-variant-numeric: tabular-nums;
  }
  .gte-matrix .gte-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(5,150,105,.35);
    position: relative; z-index: 1;
  }
  .gte-matrix .gte-empty { background: #F1F5F9; color: #CBD5E1 !important; border-radius: 6px; }
  /* Gradasi persentase: angka besar hijau, karena di sini tinggi berarti baik. */
  .gte-t1 { background: #E0484A; }
  .gte-t2 { background: #F08C2E; }
  .gte-t3 { background: #F2C230; color: #1F2937 !important; }
  .gte-t4 { background: #86C96B; }
  .gte-t5 { background: #059669; }
  /* Mode Nilai: 4 band resmi, warnanya senada dengan kartu perusahaan. */
  .gte-n1 { background: #E0484A; }
  .gte-n2 { background: #F08C2E; }
  .gte-n3 { background: #F2C230; color: #1F2937 !important; }
  .gte-n4 { background: #16A34A; }

  /* Kronologi insiden panjang-panjang; dipotong supaya barisnya tetap rapi
     dan teks utuhnya tetap bisa dibaca lewat tooltip. */
  .gte-kronologi {
    display: inline-block; max-width: 460px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    vertical-align: bottom;
  }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Tidak Ada Pelaporan Melewati Batas Golden Time</h6>
    <div class="text-secondary-light text-sm mt-4">
      Persentase insiden yang dilaporkan ke Control Room sebelum batas golden time
      ({{ $ambang }} menit) terlampaui
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
    <li class="fw-medium text-primary-600">Golden Time</li>
  </ul>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="gte-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="gte-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#gte-pane-ringkasan"
            type="button" role="tab" aria-controls="gte-pane-ringkasan" aria-selected="true">
      Ringkasan
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="gte-tab-data"
            data-bs-toggle="pill" data-bs-target="#gte-pane-data"
            type="button" role="tab" aria-controls="gte-pane-data" aria-selected="false">
      Data
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="gte-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.golden-time-emergency.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="gte-pane-data" role="tabpanel">
    @include('ohs-score-card.golden-time-emergency.partials._data')
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// ---- Tab Ringkasan ----------------------------------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.gte-overview');
    if (!root) { return; }

    // Warna seri grafik diambil dari palet WowDash yang sudah dipakai
    // dashboard lain di aplikasi ini, bukan palet baru.
    var PALETTE = ['#487FFF', '#45B369', '#FF9F29', '#EF4A00', '#8252E9', '#00B8F2', '#E0484A'];

    var overviewUrl = root.dataset.url;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.gte-filter'));
    var charts = { monthly: null, sebaran: null };
    var loaded = false;

    // 'persen' atau 'nilai'. Payload terakhir disimpan supaya mengganti mode
    // cukup menggambar ulang matriks, tanpa memanggil server lagi.
    var matrixMode = 'persen';
    var lastPayload = null;

    function el(name) {
        return root.querySelector('[data-gte="' + name + '"]');
    }

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

    // Gradasi warna untuk mode Persentase: 5 tingkat, lebih halus daripada
    // band Nilai sehingga perbedaan antar bulan lebih mudah terlihat.
    function tierClass(pct) {
        if (pct >= 98) return 'gte-t5';
        if (pct >= 90) return 'gte-t4';
        if (pct >= 78) return 'gte-t3';
        if (pct >= 62) return 'gte-t2';
        return 'gte-t1';
    }

    // Mode Nilai memakai 4 band resmi, bukan gradasi di atas, supaya warna sel
    // tidak pernah bertentangan dengan angka Nilai-nya.
    function nilaiClass(nilai) {
        return { 1: 'gte-n1', 2: 'gte-n2', 3: 'gte-n3', 4: 'gte-n4' }[nilai] || 'gte-empty';
    }

    // Kelas badge mengikuti sistem warna WowDash (bg-*-focus + text-*-main),
    // bukan warna inline, supaya ikut tema dan konsisten dengan modul lain.
    function nilaiBadgeClass(nilai) {
        return {
            1: 'bg-danger-focus text-danger-main',
            2: 'bg-warning-focus text-warning-main',
            3: 'bg-info-focus text-info-main',
            4: 'bg-success-focus text-success-main'
        }[nilai] || 'bg-neutral-200 text-secondary-light';
    }

    function nilaiBarClass(nilai) {
        return {
            1: 'bg-danger-main', 2: 'bg-warning-main',
            3: 'bg-info-main', 4: 'bg-success-main'
        }[nilai] || 'bg-neutral-400';
    }

    function cellClass(cell) {
        return matrixMode === 'nilai' ? nilaiClass(cell.nilai) : tierClass(cell.pct);
    }

    function currentFilters() {
        var out = {};
        filterEls.forEach(function (node) {
            if (node.value) { out[node.dataset.column] = node.value; }
        });
        return out;
    }

    // ---- Kartu ringkasan utama ---------------------------------------------
    function renderKpi(k) {
        var cards = [
            {
                grad: 'bg-gradient-end-1', icon: 'solar:clock-circle-outline', dot: 'bg-primary-600',
                label: 'Dalam Golden Time',
                value: k.rata === null ? '–' : fmtPct(k.rata),
                foot: k.rata === null
                    ? 'Belum ada data'
                    : '<span class="' + nilaiBadgeClass(k.nilai) + ' px-1 rounded-2 fw-medium text-sm">Nilai '
                        + k.nilai + '</span> '
                        + (k.memenuhi_target ? 'Memenuhi' : 'Belum memenuhi') + ' target ' + k.target + '%'
            },
            {
                grad: 'bg-gradient-end-5', icon: 'solar:danger-triangle-outline', dot: 'bg-danger-main',
                label: 'Melewati Batas',
                value: fmtNum(k.insiden_telat) + ' insiden',
                foot: 'Dari ' + fmtNum(k.insiden_total) + ' insiden tercatat · '
                    + fmtNum(k.insiden_tepat) + ' dilaporkan &lt; ' + k.ambang_menit + ' menit'
            },
            {
                grad: 'bg-gradient-end-3', icon: 'solar:stopwatch-outline', dot: 'bg-yellow',
                label: 'Jarak Lapor Tengah',
                value: k.menit_median === null ? '–' : fmtNum(k.menit_median) + ' mnt',
                foot: k.menit_terlama === null
                    ? 'Belum ada data'
                    : 'Separuh insiden lebih cepat dari ini · terlama ' + fmtNum(k.menit_terlama) + ' menit'
            },
            {
                grad: 'bg-gradient-end-2', icon: 'solar:check-circle-outline', dot: 'bg-success-main',
                label: 'Memenuhi Target', value: fmtNum(k.memenuhi) + ' / ' + fmtNum(k.kombinasi),
                foot: 'Pasangan site &amp; perusahaan yang rata-ratanya ≥ ' + k.target + '% · '
                    + fmtNum(k.site_count) + ' site, ' + fmtNum(k.mitra_count) + ' perusahaan'
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

    // ---- Capaian per site ---------------------------------------------------
    function renderPerSite(list) {
        var host = el('per-site');
        if (!list.length) {
            host.innerHTML = '<p class="text-secondary-light text-sm text-center py-24 mb-0">Tidak ada data.</p>';
            return;
        }
        host.innerHTML = list.map(function (s, i) {
            return '<div class="' + (i ? 'mt-20' : '') + '">'
                + '<div class="d-flex align-items-center justify-content-between mb-8">'
                +   '<span class="text-sm fw-semibold">' + escapeHtml(s.site) + '</span>'
                +   '<span class="text-sm fw-medium text-secondary-light">' + fmtPct(s.percent) + '</span>'
                + '</div>'
                + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px gte-track"'
                +   ' title="Target ' + s.target + '%">'
                +   '<div class="progress-bar ' + nilaiBarClass(s.nilai) + ' rounded-pill" role="progressbar"'
                +     ' style="width:' + Math.min(100, s.percent) + '%" aria-valuenow="' + Math.round(s.percent) + '"'
                +     ' aria-valuemin="0" aria-valuemax="100"></div>'
                +   '<span class="gte-track__target" style="left:' + s.target + '%"></span>'
                + '</div>'
                + '<span class="text-xs text-secondary-light">' + fmtNum(s.jumlah)
                +   ' perusahaan · terendah ' + fmtPct(s.terendah) + '</span>'
                + '</div>';
        }).join('');
    }

    // ---- Capaian per perusahaan --------------------------------------------
    function renderPerMitra(list) {
        var host = el('per-mitra');
        if (!list.length) {
            host.innerHTML = '<div class="col-12 text-center text-secondary-light py-24">'
                + 'Tidak ada data untuk filter ini.</div>';
            return;
        }
        host.innerHTML = list.map(function (p) {
            return '<div class="col-xxl-4 col-md-6">'
                + '<div class="border input-form-light radius-8 p-16 h-100">'
                +   '<div class="d-flex align-items-center justify-content-between gap-2 mb-12">'
                +     '<span class="text-md fw-semibold">' + escapeHtml(p.mitra) + '</span>'
                +     '<span class="' + nilaiBadgeClass(p.nilai) + ' px-8 py-2 rounded-pill fw-medium text-xs">Nilai '
                +       p.nilai + '</span>'
                +   '</div>'
                +   '<h6 class="mb-8 fw-semibold">' + fmtPct(p.percent) + '</h6>'
                +   '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px mb-8">'
                +     '<div class="progress-bar ' + nilaiBarClass(p.nilai) + ' rounded-pill" role="progressbar"'
                +       ' style="width:' + Math.min(100, p.percent) + '%" aria-valuenow="' + Math.round(p.percent) + '"'
                +       ' aria-valuemin="0" aria-valuemax="100"></div>'
                +   '</div>'
                +   '<span class="text-sm text-secondary-light">' + fmtNum(p.jumlah) + ' site · terendah '
                +     fmtPct(p.terendah) + '</span>'
                + '</div></div>';
        }).join('');
    }

    // ---- Perlu perhatian ----------------------------------------------------
    function renderTerendah(list) {
        var body = el('terendah');
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>';
            return;
        }
        body.innerHTML = list.map(function (t) {
            return '<tr>'
                + '<td>'
                +   '<span class="text-md fw-semibold d-block">' + escapeHtml(t.site) + '</span>'
                +   '<span class="text-sm text-secondary-light">' + escapeHtml(t.mitra)
                +     ', terendah ' + fmtPct(t.terendah) + '</span>'
                + '</td>'
                + '<td class="text-center"><span class="' + nilaiBadgeClass(t.nilai)
                +   ' px-8 py-2 rounded-pill fw-medium text-xs">' + t.nilai + '</span></td>'
                + '<td class="text-end fw-medium">' + fmtPct(t.percent) + '</td>'
                + '</tr>';
        }).join('');
    }

    // ---- Pelaporan terlama --------------------------------------------------
    function renderTerlama(list) {
        var body = el('terlama');
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-secondary-light py-24">'
                + 'Tidak ada pelaporan yang melewati batas untuk filter ini.</td></tr>';
            return;
        }
        body.innerHTML = list.map(function (r) {
            return '<tr>'
                + '<td class="fw-medium">' + escapeHtml(r.site) + '</td>'
                + '<td>'
                +   '<span class="d-block">' + escapeHtml(r.mitra) + '</span>'
                +   (r.lead && r.lead !== '-' && r.lead !== r.mitra
                        ? '<span class="text-xs text-secondary-light">Lead: ' + escapeHtml(r.lead) + '</span>'
                        : '')
                + '</td>'
                + '<td class="text-sm">' + escapeHtml(r.waktu_insiden) + '</td>'
                + '<td class="text-sm">' + escapeHtml(r.waktu_lapor) + '</td>'
                + '<td class="text-end"><span class="bg-danger-focus text-danger-main px-8 py-2 rounded-pill'
                +   ' fw-medium text-xs">' + escapeHtml(r.menit_label) + '</span></td>'
                + '<td><span class="gte-kronologi text-sm text-secondary-light" title="'
                +   escapeHtml(r.kronologi) + '">' + escapeHtml(r.kronologi) + '</span></td>'
                + '</tr>';
        }).join('');
    }

    /** Legenda ikut mode: gradasi persentase, atau 4 band Nilai. */
    function renderLegend() {
        var items = matrixMode === 'nilai'
            ? [
                { color: '#E0484A', label: 'Nilai 1 · <80%' },
                { color: '#F08C2E', label: 'Nilai 2 · 80–90%' },
                { color: '#F2C230', label: 'Nilai 3 · 90–98%' },
                { color: '#16A34A', label: 'Nilai 4 · 98–100%' }
            ]
            : [
                { color: '#E0484A', label: '<62%' },
                { color: '#F08C2E', label: '62–78%' },
                { color: '#F2C230', label: '78–90%' },
                { color: '#86C96B', label: '90–98%' },
                { color: '#059669', label: '≥98%' }
            ];

        el('legend').innerHTML = items.map(function (it) {
            return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                + '<span class="rounded-1" style="width:14px;height:14px;background:' + it.color + ';"></span>'
                + escapeHtml(it.label) + '</span>';
        }).join('');

        el('matrix-subtitle').textContent = matrixMode === 'nilai'
            ? 'Nilai 1–4 dari persentase pelaporan dalam golden time, tiap perusahaan di tiap site'
            : 'Persentase pelaporan dalam golden time, tiap perusahaan di tiap site';
    }

    function renderMatrix(months, rows) {
        var table = el('matrix');
        var thead = table.querySelector('thead');
        var tbody = table.querySelector('tbody');

        var head = '<tr><th class="gte-site">SITE</th><th class="gte-mitra">PERUSAHAAN</th>'
            + '<th>RATA</th><th>TREND</th>';
        months.forEach(function (m, i) {
            head += '<th class="' + (i === months.length - 1 ? 'gte-th-last' : '') + '">'
                + escapeHtml(m.label) + '</th>';
        });
        thead.innerHTML = head + '</tr>';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="' + (months.length + 4) + '" class="text-center py-24 text-secondary-light">'
                + 'Tidak ada data untuk filter ini.</td></tr>';
            return;
        }

        // Sel site digabung dengan rowspan: satu sel untuk semua perusahaan di
        // site yang sama. Baris sudah dikelompokkan per site dari server, jadi
        // cukup menghitung panjang blok yang berurutan.
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
                + (lewati[i] ? '' : '<td class="gte-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="gte-mitra">' + escapeHtml(row.mitra) + '</td>';

            if (row.average === null) {
                html += '<td class="gte-avg" title="Belum ada insiden tercatat">–</td>';
            } else {
                var tipRata = fmtPct(row.average) + ' · Nilai ' + row.nilai + ' (' + row.nilai_band + ')'
                    + ' · dari ' + row.bulan_terisi + ' bulan · terendah ' + fmtPct(row.terendah);
                html += '<td class="gte-avg" title="' + escapeHtml(tipRata) + '">'
                    + (matrixMode === 'nilai' ? row.nilai : fmtPct(row.average)) + '</td>';
            }

            if (row.trend === 'up') {
                html += '<td class="gte-trend--up" title="Naik dari bulan sebelumnya">&uarr;</td>';
            } else if (row.trend === 'down') {
                html += '<td class="gte-trend--down" title="Turun dari bulan sebelumnya">&darr;</td>';
            } else if (row.trend === 'flat') {
                html += '<td class="gte-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
            } else {
                html += '<td class="text-secondary-light">–</td>';
            }

            // Variabel sengaja dinamai m, bukan i: i di luar sudah dipakai
            // sebagai index baris untuk perhitungan rowspan site.
            row.cells.forEach(function (cell, m) {
                if (cell === null) {
                    html += '<td class="gte-empty" title="' + escapeHtml(months[m].label)
                        + ': tidak ada insiden tercatat">–</td>';
                    return;
                }

                // Tooltip selalu memuat kedua angka, apa pun mode tampilannya,
                // supaya berganti mode tidak menghilangkan informasi.
                var tip = row.site + ' · ' + row.mitra + ' · ' + months[m].label + ': '
                    + fmtPct(cell.pct) + ' · Nilai ' + cell.nilai + ' (' + cell.nilai_band + ')';

                html += '<td class="gte-cell ' + cellClass(cell) + '" title="' + escapeHtml(tip) + '">'
                    + (matrixMode === 'nilai' ? cell.nilai : Math.round(cell.pct) + '%')
                    + '</td>';
            });

            return html + '</tr>';
        }).join('');
    }

    // ---- Grafik -------------------------------------------------------------
    /** Sebaran lama pelaporan; batang tepat waktu hijau, sisanya merah. */
    function renderSebaranChart(list) {
        var node = el('chart-sebaran');
        if (!node || typeof ApexCharts === 'undefined') { return; }

        if (charts.sebaran) {
            charts.sebaran.destroy();
            charts.sebaran = null;
        }

        var total = list.reduce(function (acc, b) { return acc + b.jumlah; }, 0);

        if (!list.length || total === 0) {
            node.innerHTML = '<p class="text-secondary-light text-sm text-center py-40 mb-0">Tidak ada data.</p>';
            return;
        }
        node.innerHTML = '';

        charts.sebaran = new ApexCharts(node, {
            series: [{
                name: 'Insiden',
                data: list.map(function (b) { return b.jumlah; })
            }],
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            plotOptions: {
                bar: { horizontal: true, borderRadius: 4, barHeight: '58%', distributed: true }
            },
            colors: list.map(function (b) { return b.tepat_waktu ? '#16A34A' : '#E0484A'; }),
            legend: { show: false },
            dataLabels: {
                enabled: true,
                formatter: function (v, opt) {
                    var b = list[opt.dataPointIndex];
                    return v === 0 ? '' : fmtNum(v) + ' (' + Math.round(b.persen) + '%)';
                },
                style: { fontSize: '11px', colors: ['#fff'] }
            },
            xaxis: {
                categories: list.map(function (b) { return b.label; }),
                labels: { formatter: function (v) { return fmtNum(Math.round(v)); } }
            },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            tooltip: {
                // WAJIB eksplisit: kombinasi shared + intersect melempar error
                // sehingga grafiknya gagal dirender sama sekali.
                shared: false,
                intersect: true,
                y: {
                    formatter: function (v, opt) {
                        var b = list[opt.dataPointIndex];
                        return fmtNum(v) + ' insiden · ' + fmtPct(b.persen) + ' dari seluruhnya';
                    }
                }
            }
        });
        charts.sebaran.render();
    }

    function renderMonthlyChart(payload) {
        var node = el('chart-monthly');
        if (!node || typeof ApexCharts === 'undefined') { return; }

        if (charts.monthly) {
            charts.monthly.destroy();
            charts.monthly = null;
        }

        if (!payload.series.length) {
            node.innerHTML = '<p class="text-secondary-light text-sm text-center py-40 mb-0">Tidak ada data.</p>';
            return;
        }
        node.innerHTML = '';

        charts.monthly = new ApexCharts(node, {
            series: payload.series,
            chart: { type: 'line', height: 320, toolbar: { show: false }, zoom: { enabled: false } },
            colors: PALETTE,
            stroke: { curve: 'smooth', width: 3 },
            markers: { size: 4, hover: { size: 5 } },
            dataLabels: { enabled: false },
            xaxis: {
                categories: payload.labels,
                labels: { style: { fontSize: '11px' } }
            },
            yaxis: {
                min: 0, max: 100,
                labels: { formatter: function (v) { return Math.round(v) + '%'; } }
            },
            legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            annotations: {
                yaxis: [{
                    y: {{ $target }},
                    borderColor: '#0F172A',
                    strokeDashArray: 4,
                    label: {
                        text: 'Target {{ $target }}%',
                        style: { fontSize: '10px', background: '#0F172A', color: '#fff' }
                    }
                }]
            },
            tooltip: {
                shared: true,
                // WAJIB eksplisit: kombinasi shared + intersect melempar error
                // sehingga grafiknya gagal dirender sama sekali.
                intersect: false,
                y: { formatter: function (v) { return v === null ? 'tidak ada insiden' : fmtPct(v); } }
            }
        });
        charts.monthly.render();
    }

    /** Keterangan dari server, atau disembunyikan kalau tidak ada. */
    function renderCatatan(text) {
        el('note').textContent = text || '';
        el('note-wrap').classList.toggle('d-none', !text);
    }

    /** Membungkus renderer agar kegagalan satu panel tidak menjatuhkan sisanya. */
    function safe(label, fn) {
        try {
            fn();
        } catch (err) {
            if (typeof console !== 'undefined' && console.error) {
                console.error('Golden Time: panel "' + label + '" gagal dirender', err);
            }
        }
    }

    function load() {
        var params = new URLSearchParams(currentFilters());
        el('status').textContent = 'memuat…';

        fetch(overviewUrl + (params.toString() ? '?' + params.toString() : ''), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.json();
            })
            .then(function (json) {
                lastPayload = json;

                safe('kpi', function () { renderKpi(json.kpi); });
                safe('legend', renderLegend);
                safe('matrix', function () { renderMatrix(json.months || [], json.matrix || []); });
                safe('per-site', function () { renderPerSite(json.per_site || []); });
                safe('per-mitra', function () { renderPerMitra(json.per_mitra || []); });
                safe('terendah', function () { renderTerendah(json.terendah || []); });
                safe('terlama', function () { renderTerlama(json.terlama || []); });
                safe('sebaran', function () { renderSebaranChart(json.sebaran || []); });
                safe('monthly', function () { renderMonthlyChart(json.monthly); });
                safe('catatan', function () { renderCatatan(json.catatan); });

                var k = json.kpi;
                el('status').textContent = (k.rata === null ? 'belum ada data' : fmtPct(k.rata) + ' dalam golden time')
                    + ' · ' + fmtNum(k.insiden_total) + ' insiden · ' + fmtNum(k.insiden_telat)
                    + ' melewati batas · ' + k.bulan_count + ' bulan';
                loaded = true;
            })
            .catch(function (err) {
                el('status').textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Ringkasan Golden Time: gagal memuat', err);
                }
            });
    }

    filterEls.forEach(function (node) {
        node.addEventListener('change', load);
    });

    // Ganti mode hanya menggambar ulang dari payload terakhir, tidak ada
    // permintaan baru ke server, karena angka Nilai sudah ikut dikirim.
    root.querySelectorAll('.gte-switch__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.mode === matrixMode) { return; }

            matrixMode = btn.dataset.mode;

            root.querySelectorAll('.gte-switch__btn').forEach(function (b) {
                // Kelas aktifnya 'active' (bawaan nav-pills WowDash),
                // bukan kelas buatan sendiri.
                b.classList.toggle('active', b.dataset.mode === matrixMode);
            });

            renderLegend();

            if (lastPayload) {
                renderMatrix(lastPayload.months || [], lastPayload.matrix || []);
            }
        });
    });

    el('reset').addEventListener('click', function () {
        filterEls.forEach(function (node) { node.value = ''; });
        load();
    });

    load();

    var tab = document.querySelector('#gte-tab-ringkasan');
    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            if (!loaded) { load(); return; }
            // ApexCharts tidak bisa mengukur elemen yang sedang tersembunyi,
            // jadi ukurannya dihitung ulang saat tab kembali tampil.
            ['monthly', 'sebaran'].forEach(function (nama) {
                if (charts[nama] && typeof charts[nama].windowResizeHandler === 'function') {
                    charts[nama].windowResizeHandler();
                }
            });
        });
    }
})();
</script>
<script>
// ---- Tab Data ---------------------------------------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.gte-datatable');
    if (!root) { return; }

    var tableEl = root.querySelector('[data-gte="table"]');
    if (!tableEl || typeof DataTable === 'undefined') { return; }
    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.gted-filter'));
    var hintEl = root.querySelector('[data-gte="hint"]');

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmtNum(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

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
                hintEl.textContent = fmtNum(json.recordsFiltered) + ' insiden';
                return json.data || [];
            },
            error: function (xhr, error) {
                hintEl.textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Golden Time: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'site' },
            { data: 'mitra' },
            { data: 'lead' },
            // Bulan tersimpan sebagai nama bulan Inggris, jadi mengurutkannya
            // hanya menghasilkan urutan abjad yang menyesatkan.
            { data: 'bulan', orderable: false },
            // Waktu insiden tersimpan sebagai teks "8/12/2026 11:50:00 PM";
            // diurutkan di SQL hasilnya urutan abjad, jadi dimatikan dan
            // pengurutan kronologis diserahkan ke kolom Jarak Lapor.
            { data: 'waktu_insiden', orderable: false },
            {
                data: 'menit',
                className: 'text-end',
                render: function (d, type, row) {
                    if (type !== 'display') { return d === null ? 0 : d; }
                    if (d === null) { return '<span class="text-secondary-light">–</span>'; }
                    var kelas = row.tepat_waktu
                        ? 'bg-success-focus text-success-main'
                        : 'bg-danger-focus text-danger-main';
                    return '<span class="' + kelas + ' px-8 py-2 rounded-pill fw-medium text-xs"'
                        + ' title="Dilaporkan ' + escapeHtml(row.waktu_lapor) + '">'
                        + escapeHtml(row.menit_label) + '</span>';
                }
            },
            {
                data: 'status',
                orderable: false,
                render: function (d, type, row) {
                    if (type !== 'display') { return d; }
                    var kelas = row.menit === null
                        ? 'text-secondary-light'
                        : (row.tepat_waktu ? 'text-success-main' : 'text-danger-main');
                    return '<span class="' + kelas + ' fw-medium text-sm">' + escapeHtml(d) + '</span>';
                }
            },
            {
                data: 'kronologi',
                orderable: false,
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    if (!d) { return '<span class="text-secondary-light">–</span>'; }
                    return '<span class="gte-kronologi text-sm text-secondary-light" title="'
                        + escapeHtml(d) + '">' + escapeHtml(d) + '</span>';
                }
            }
        ],
        language: {
            processing: 'Memuat…',
            lengthMenu: 'Tampilkan _MENU_ baris',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ insiden',
            infoEmpty: 'Tidak ada insiden',
            infoFiltered: '(disaring dari _MAX_ insiden)',
            search: 'Cari:',
            zeroRecords: 'Tidak ada insiden untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    filterEls.forEach(function (node) {
        node.addEventListener('change', function () { table.ajax.reload(); });
    });

    root.querySelector('[data-gte="reset"]').addEventListener('click', function () {
        filterEls.forEach(function (node) { node.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    root.querySelectorAll('[data-gte="export"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var params = new URLSearchParams(currentFilters());
            var search = table.search();
            if (search) { params.set('search', search); }
            params.set('format', btn.dataset.format);
            window.location.href = exportUrl + '?' + params.toString();
        });
    });

    // Tabel dibangun saat pane-nya masih tersembunyi, jadi lebar kolom
    // dihitung ulang begitu tab-nya pertama kali dibuka.
    var tab = document.querySelector('#gte-tab-data');
    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        });
    }
})();
</script>
@endsection
