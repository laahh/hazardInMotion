@extends('ohs-score-card.layouts.app')

@section('title', $judul)

{{--
  Laporan Perizinan Usaha Jasa.

  ANGKANYA TINGKAT PEMENUHAN, dibaca dari kolom performance_<bulan>_26_pct
  (rasio 0-1, dikali 100). Seratus persen berarti tidak ada subkontraktor yang
  menyimpang dan itu hasil TERBAIK, jadi arahnya NAIK: hijau di atas, merah di
  bawah, dan barisnya diurutkan dari yang TERENDAH karena itu yang perlu
  ditindak.

  ARTI KOLOM ITU PERNAH BERUBAH. Sebelumnya kolom yang sama memuat proporsi
  deviasi (nol berarti terbaik), dan halaman ini dibangun untuk itu. Kalau
  suatu saat angkanya terlihat terbalik lagi, periksa catatan di bawah matriks:
  controller membandingkan kolom persen dengan cacah deviasi dan akan
  menyebutkan selisihnya.

  TIDAK ADA MODE "NILAI". Band resmi parameter ini belum ada, jadi warnanya
  memakai ambang sementara dari controller dan angka Nilai 1-4 tidak pernah
  ditampilkan.
--}}

@section('css')
<style>
  .lpu-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .lpu-matrix {
    width: 100%; min-width: 900px;
    border-collapse: separate; border-spacing: 4px; font-size: 12px;
  }
  .lpu-matrix th, .lpu-matrix td {
    padding: 8px 10px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .lpu-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #64748B; font-weight: 600; font-size: 11px; border-radius: 8px;
  }
  .lpu-matrix thead th.lpu-th-last { background: #2E90FA !important; color: #fff !important; }
  .lpu-matrix .lpu-site, .lpu-matrix .lpu-mitra {
    position: sticky; z-index: 2; text-align: left !important;
    background: #F1F5F9; color: #0F172A !important; border-radius: 8px;
  }
  .lpu-matrix .lpu-site { left: 0; font-weight: 700; min-width: 74px; }
  .lpu-matrix .lpu-mitra { left: 74px; font-weight: 600; min-width: 220px; }
  .lpu-matrix thead .lpu-site, .lpu-matrix thead .lpu-mitra {
    z-index: 4; background: #F8FAFC; color: #64748B !important;
  }
  .lpu-matrix .lpu-rata {
    font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px;
  }

  .lpu-cell {
    font-weight: 700; min-width: 72px; border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
  }
  .lpu-cell--klik { cursor: pointer; }
  .lpu-cell--klik:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(5,150,105,.3);
    position: relative; z-index: 1;
  }
  .lpu-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Band 4 = 100% pemenuhan = terbaik. Arahnya naik. */
  .lpu-b1 { background: #FF0000; color: #fff; }
  .lpu-b2 { background: #FFC000; color: #1F2937; }
  .lpu-b3 { background: #FFFF00; color: #1F2937; }
  .lpu-b4 { background: #92D050; color: #1F2937; }
  /* Bulan itu belum terdata -- bukan 0% pemenuhan. */
  .lpu-kosong {
    background: #F8FAFC; color: #CBD5E1; border-style: dashed; border-color: #E2E8F0;
  }

  .lpu-bar { height: 8px; border-radius: 999px; background: #EEF2F7; overflow: hidden; }
  .lpu-bar > span { display: block; height: 100%; border-radius: 999px; }
  .lpu-bar1 { background: #FF0000; }
  .lpu-bar2 { background: #FFC000; }
  .lpu-bar3 { background: #FFFF00; }
  .lpu-bar4 { background: #92D050; }

  .lpu-badge1 { background: #FFE5E5; color: #B91C1C !important; }
  .lpu-badge2 { background: #FFF2CC; color: #92400E !important; }
  .lpu-badge3 { background: #FFFBCC; color: #854D0E !important; }
  .lpu-badge4 { background: #E8F5DC; color: #3F6212 !important; }

  .lpu-modal-scroll { max-height: 38vh; overflow: auto; }
  .lpu-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">{{ $judul }}</h6>
    <span class="text-sm text-secondary-light">{{ $penjelasan }}, dari {{ $tabel }}</span>
  </div>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="lpu-tab" role="tablist">
  <li class="nav-item flex-shrink-0" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="lpu-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#lpu-pane-ringkasan" type="button"
            role="tab" aria-controls="lpu-pane-ringkasan" aria-selected="true">Ringkasan</button>
  </li>
  <li class="nav-item flex-shrink-0" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="lpu-tab-data"
            data-bs-toggle="pill" data-bs-target="#lpu-pane-data" type="button"
            role="tab" aria-controls="lpu-pane-data" aria-selected="false">Data</button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="lpu-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.laporan-perizinan-usaha-jasa.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="lpu-pane-data" role="tabpanel">
    @include('ohs-score-card.laporan-perizinan-usaha-jasa.partials._data')
  </div>
</div>
@endsection

@section('page-scripts')
<script>
(function () {
    var root = document.querySelector('.lpu-overview');

    if (!root) { return; }

    var urlOverview = root.dataset.url;
    var urlDetail = root.dataset.detailUrl;
    var lastPayload = null;

    function el(nama) { return root.querySelector('[data-lpu="' + nama + '"]'); }

    function escapeHtml(teks) {
        return String(teks === null || teks === undefined ? '' : teks)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function fmtNum(n) {
        if (n === null || n === undefined || n === '') { return '–'; }
        return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 0 });
    }

    function fmtPct(n) {
        if (n === null || n === undefined || n === '') { return '–'; }
        return Number(n).toLocaleString('id-ID', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        }) + '%';
    }

    /* Nomor band datang dari controller. Ambangnya SEMENTARA dan berarah
       TURUN; keduanya tinggal di satu tempat di sana. */
    function cellClass(sel) {
        if (!sel || !sel.ada) { return 'lpu-kosong'; }
        return 'lpu-b' + (sel.band || 1);
    }

    function badgeClass(band) { return 'lpu-badge' + (band || 1); }
    function barClass(band) { return 'lpu-bar' + (band || 1); }

    function currentFilters() {
        var out = {};
        root.querySelectorAll('.lpu-filter').forEach(function (node) {
            if (node.value) { out[node.dataset.column] = node.value; }
        });
        return out;
    }

    function safe(nama, fn) {
        try { fn(); } catch (e) {
            var node = el(nama);
            if (node) { node.innerHTML = '<span class="text-danger-main text-sm">Gagal menampilkan bagian ini.</span>'; }
        }
    }

    // ---------------------------------------------------------------- KPI
    function ubin(label, nilai, catatan) {
        return '<div class="col-xxl-3 col-sm-6">'
            + '<div class="card radius-8 border h-100"><div class="card-body p-20">'
            + '<span class="text-sm text-secondary-light d-block mb-4">' + escapeHtml(label) + '</span>'
            + '<h6 class="fw-semibold mb-4">' + nilai + '</h6>'
            + '<span class="text-xs text-secondary-light">' + catatan + '</span>'
            + '</div></div></div>';
    }

    function renderKpi(k) {
        el('kpi').innerHTML =
            ubin('Pemenuhan keseluruhan', fmtPct(k.rata),
                '100% adalah hasil terbaik · ' + fmtNum(k.deviasi) + ' deviasi tercatat')
            + ubin('Total subcontractor', fmtNum(k.subcont),
                'di ' + fmtNum(k.kombinasi) + ' pasangan site &amp; main contractor')
            + ubin('Sudah 100%', fmtNum(k.kombinasi_sempurna) + ' / ' + fmtNum(k.kombinasi),
                'pasangan tanpa deviasi sepanjang periode')
            + ubin('Terendah', fmtPct(k.terendah), 'pada satu pasangan');
    }

    // ------------------------------------------------------------- MATRIKS
    function renderMatrix(months, rows) {
        var thead = root.querySelector('[data-lpu="matrix"] thead');
        var tbody = root.querySelector('[data-lpu="matrix"] tbody');

        thead.innerHTML = '<tr><th class="lpu-site">Site</th>'
            + '<th class="lpu-mitra">Main Contractor</th>'
            + '<th>Subcont</th><th>Rata</th>'
            + months.map(function (m, i) {
                return '<th class="' + (i === months.length - 1 ? 'lpu-th-last' : '') + '">'
                    + escapeHtml(m.short) + '</th>';
            }).join('') + '</tr>';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="' + (months.length + 4)
                + '" class="text-secondary-light">Tidak ada data untuk filter ini.</td></tr>';
            return;
        }

        var lewati = [];
        var span = [];

        rows.forEach(function (row, i) {
            if (i > 0 && rows[i - 1].site === row.site) { lewati[i] = true; return; }
            var n = 1;
            while (i + n < rows.length && rows[i + n].site === row.site) { n++; }
            span[i] = n;
        });

        tbody.innerHTML = rows.map(function (row, i) {
            var html = '<tr>'
                + (lewati[i] ? '' : '<td class="lpu-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="lpu-mitra">' + escapeHtml(row.mitra) + '</td>'
                + '<td>' + fmtNum(row.subcont) + '</td>'
                + '<td class="lpu-rata" title="' + escapeHtml(
                    fmtNum(row.deviasi) + ' deviasi · ' + row.bulan_terisi + ' bulan terdata'
                    + (row.terendah ? ' · terendah di ' + row.terendah : ''))
                + '">' + fmtPct(row.average) + '</td>';

            row.cells.forEach(function (sel, m) {
                if (!sel.ada) {
                    html += '<td class="lpu-cell lpu-kosong" title="'
                        + escapeHtml(row.site + ' · ' + row.mitra + ' · ' + months[m].label
                            + ': belum terdata') + '">–</td>';
                    return;
                }

                var tip = row.site + ' · ' + row.mitra + ' · ' + months[m].label + ': '
                    + 'pemenuhan ' + fmtPct(sel.pct) + ' · '
                    + fmtNum(sel.deviasi) + ' dari ' + fmtNum(sel.total)
                    + ' subcontractor menyimpang';

                html += '<td class="lpu-cell lpu-cell--klik ' + cellClass(sel) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-mitra="' + escapeHtml(row.mitra) + '"'
                    + ' data-bulan="' + months[m].number + '"'
                    + ' title="' + escapeHtml(tip + ' · klik untuk rincian') + '">'
                    + (sel.pct === 100 ? '100%' : fmtPct(sel.pct))
                    + '</td>';
            });

            return html + '</tr>';
        }).join('');
    }

    function renderLegend() {
        var warna = { 1: '#FF0000', 2: '#FFC000', 3: '#FFFF00', 4: '#92D050' };
        var legenda = JSON.parse(root.dataset.legenda || '[]');

        el('legend').innerHTML = legenda.map(function (it) {
            return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                + '<span class="rounded-1" style="width:14px;height:14px;background:'
                + warna[it.band] + ';"></span>' + escapeHtml(it.label)
                + '</span>';
        }).join('')
            + '<span class="text-xs fst-italic" style="color:#94A3B8;">'
            + 'ambang warna sementara — band resmi parameter ini belum ada</span>';
    }

    // -------------------------------------------------------------- PANEL
    function renderPanel(nama, rows) {
        if (!rows || !rows.length) {
            el(nama).innerHTML = '<span class="text-sm text-secondary-light">Tidak ada data.</span>';
            return;
        }

        el(nama).innerHTML = rows.map(function (r) {
            /* Nilainya berkerumun di 88-100%, jadi batang yang digambar dari
               nol semuanya tampak penuh dan tak terbedakan. Yang digambar
               karena itu KEKURANGANNYA terhadap 100%, diperbesar lima kali. */
            var lebar = Math.max(2, Math.min(100, (100 - Number(r.percent || 0)) * 5));

            return '<div class="mb-16">'
                + '<div class="d-flex align-items-center justify-content-between gap-2 mb-6">'
                + '<span class="text-sm fw-medium text-truncate" title="' + escapeHtml(r.label) + '">'
                + escapeHtml(r.label) + '</span>'
                + '<span class="' + badgeClass(r.band) + ' px-8 py-2 rounded-pill fw-medium text-xs">'
                + fmtPct(r.percent) + '</span>'
                + '</div>'
                + '<div class="lpu-bar" title="panjang batang = kekurangan terhadap 100%">'
                + '<span class="' + barClass(r.band) + '" style="width:' + lebar + '%;"></span></div>'
                + '<span class="text-xs text-secondary-light">'
                + fmtNum(r.deviasi) + ' deviasi dari ' + fmtNum(r.total) + ' pemeriksaan</span>'
                + '</div>';
        }).join('');
    }

    function renderBulan(d) {
        if (!d || !d.labels || !d.labels.length) {
            el('per-bulan').innerHTML = '<span class="text-sm text-secondary-light">Tidak ada data.</span>';
            return;
        }

        /* Batangnya menggambarkan KEKURANGAN terhadap 100%, bukan nilainya:
           seluruh angka ada di 90-100% sehingga batang dari nol tampak sama
           tinggi semua dan tidak memberi tahu apa pun. */
        var kurang = d.data.map(function (x) {
            return x === null ? null : 100 - Number(x);
        });
        var maks = Math.max.apply(null, kurang.map(function (x) { return Number(x || 0); }));

        el('per-bulan').innerHTML = '<div class="d-flex align-items-end gap-2" style="height:140px;">'
            + d.labels.map(function (l, i) {
                var v = d.data[i];
                var k = kurang[i];
                var tinggi = (maks > 0 && k !== null) ? Math.max(3, k / maks * 100) : 3;

                return '<div class="flex-fill d-flex flex-column align-items-center gap-1"'
                    + ' title="' + escapeHtml(l + ': pemenuhan ' + fmtPct(v)
                        + ' · kurang ' + fmtPct(k)) + '">'
                    + '<span class="text-xs text-secondary-light">'
                    + (v === null ? '–' : Number(v).toLocaleString('id-ID',
                        { maximumFractionDigits: 1 }) + '%') + '</span>'
                    + '<div style="width:100%;height:' + tinggi + '%;background:'
                    + (Number(k || 0) > 0 ? '#FF0000' : '#92D050')
                    + ';border-radius:4px 4px 0 0;"></div>'
                    + '<span class="text-xs text-secondary-light">' + escapeHtml(l.substring(0, 3))
                    + '</span></div>';
            }).join('') + '</div>';
    }

    // -------------------------------------------------------------- MODAL
    var modalEl = document.getElementById('lpu-modal');
    var modal = modalEl && window.bootstrap ? new window.bootstrap.Modal(modalEl) : null;

    function bukaDetail(site, mitra, bulan) {
        if (!modal) { return; }

        var params = new URLSearchParams();
        params.set('site', site);
        params.set('mitra', mitra);
        params.set('bulan', bulan);

        document.querySelector('[data-lpu="modal-judul"]').textContent = site + ' · ' + mitra;
        document.querySelector('[data-lpu="modal-isi"]').innerHTML =
            '<div class="text-center py-24 text-secondary-light text-sm">Memuat rincian…</div>';
        modal.show();

        fetch(urlDetail + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
        })
            .then(function (r) {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.json();
            })
            .then(renderDetail)
            .catch(function () {
                document.querySelector('[data-lpu="modal-isi"]').innerHTML =
                    '<div class="text-center py-24 text-danger-main text-sm">Gagal memuat rincian.</div>';
            });
    }

    function renderDetail(d) {
        document.querySelector('[data-lpu="modal-judul"]').textContent = d.judul + ' · ' + d.bulan;

        var html = '<div class="row gy-3 mb-16">'
            + '<div class="col-sm-6"><div class="card radius-8 border h-100"><div class="card-body p-16">'
            + '<span class="text-xs text-secondary-light d-block mb-4">Pemenuhan</span>'
            + '<h6 class="fw-semibold mb-0">' + fmtPct(d.persen) + '</h6>'
            + '<span class="text-xs text-secondary-light">'
            + fmtNum(d.deviasi) + ' dari ' + fmtNum(d.total) + ' subcontractor menyimpang</span>'
            + '</div></div></div>'
            + '<div class="col-sm-6"><div class="card radius-8 border h-100"><div class="card-body p-16">'
            + '<span class="text-xs text-secondary-light d-block mb-4">Peringkat perlu ditindak</span>'
            + '<h6 class="fw-semibold mb-0">' + fmtNum(d.peringkat) + ' dari ' + fmtNum(d.dari) + '</h6>'
            + '<span class="text-xs text-secondary-light">peringkat 1 = pemenuhan terendah</span>'
            + '</div></div></div></div>';

        if (d.riwayat && d.riwayat.length) {
            html += '<h6 class="text-sm fw-semibold mb-8">Riwayat kombinasi ini</h6>'
                + '<div class="lpu-modal-scroll mb-16"><table class="table table-sm mb-0">'
                + '<thead><tr><th>Bulan</th><th class="text-end">Deviasi</th>'
                + '<th class="text-end">Pemenuhan</th></tr></thead><tbody>'
                + d.riwayat.map(function (r) {
                    return '<tr' + (r.ini ? ' class="fw-semibold"' : '') + '>'
                        + '<td>' + escapeHtml(r.label) + (r.ini ? ' (sel ini)' : '') + '</td>'
                        + '<td class="text-end">' + fmtNum(r.deviasi) + ' / ' + fmtNum(r.total) + '</td>'
                        + '<td class="text-end"><span class="' + badgeClass(r.band)
                        + ' px-8 py-2 rounded-pill fw-medium text-xs">' + fmtPct(r.persen)
                        + '</span></td></tr>';
                }).join('') + '</tbody></table></div>';
        }

        if (d.sebulan && d.sebulan.length) {
            html += '<h6 class="text-sm fw-semibold mb-8">Kombinasi lain pada ' + escapeHtml(d.bulan)
                + ' — terendah lebih dulu</h6>'
                + '<div class="lpu-modal-scroll"><table class="table table-sm mb-0">'
                + '<thead><tr><th>Site</th><th>Main Contractor</th>'
                + '<th class="text-end">Pemenuhan</th></tr></thead><tbody>'
                + d.sebulan.map(function (s) {
                    return '<tr><td>' + escapeHtml(s.site) + '</td>'
                        + '<td>' + escapeHtml(s.mitra) + '</td>'
                        + '<td class="text-end">' + fmtNum(s.deviasi) + ' / ' + fmtNum(s.total)
                        + ' · ' + fmtPct(s.persen) + '</td></tr>';
                }).join('') + '</tbody></table></div>';
        }

        document.querySelector('[data-lpu="modal-isi"]').innerHTML = html;
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap
    // ganti filter, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    matrixEl.addEventListener('click', function (e) {
        var td = e.target.closest('.lpu-cell--klik');
        if (td) { bukaDetail(td.dataset.site, td.dataset.mitra, td.dataset.bulan); }
    });

    matrixEl.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') { return; }
        var td = e.target.closest('.lpu-cell--klik');
        if (td) { e.preventDefault(); bukaDetail(td.dataset.site, td.dataset.mitra, td.dataset.bulan); }
    });

    function muat() {
        var params = new URLSearchParams(currentFilters());

        el('status').textContent = 'Memuat…';

        fetch(urlOverview + (params.toString() ? '?' + params.toString() : ''), {
            headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
        })
            .then(function (r) {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.json();
            })
            .then(function (json) {
                lastPayload = json;

                safe('kpi', function () { renderKpi(json.kpi); });
                safe('legend', renderLegend);
                safe('matrix', function () { renderMatrix(json.months || [], json.matrix || []); });
                safe('per-site', function () { renderPanel('per-site', json.per_site); });
                safe('per-mitra', function () { renderPanel('per-mitra', json.per_mitra); });
                safe('per-bulan', function () { renderBulan(json.per_bulan); });

                var catatan = el('catatan');
                catatan.textContent = json.catatan || '';
                catatan.classList.toggle('d-none', !json.catatan);

                var k = json.kpi;
                el('status').textContent = fmtNum(k.kombinasi) + ' pasangan site/main contractor · '
                    + fmtNum(k.subcont) + ' subcontractor · pemenuhan ' + fmtPct(k.rata);
            })
            .catch(function () {
                el('status').textContent = 'Gagal memuat data.';
            });
    }

    root.querySelectorAll('.lpu-filter').forEach(function (node) {
        node.addEventListener('change', muat);
    });

    var reset = el('reset');

    if (reset) {
        reset.addEventListener('click', function () {
            root.querySelectorAll('.lpu-filter').forEach(function (n) { n.value = ''; });
            muat();
        });
    }

    muat();
})();
</script>

<script>
// ---- Tab Data ---------------------------------------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.lpu-datatable');

    if (!root) { return; }

    var tableEl = root.querySelector('[data-lpud="table"]');

    if (!tableEl || typeof DataTable === 'undefined') { return; }

    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.lpud-filter'));
    var hintEl = root.querySelector('[data-lpud="hint"]');

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmtNum(value) {
        return Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
    }

    function fmtPct(value) {
        if (value === null || value === undefined) { return '–'; }
        return Number(value).toLocaleString('id-ID', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        }) + '%';
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
        lengthMenu: [25, 50, 100],
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
                hintEl.textContent = fmtNum(json.recordsFiltered) + ' baris';
                return json.data || [];
            },
            error: function (xhr, error) {
                hintEl.textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Laporan perizinan: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'site' },
            { data: 'mitra' },
            {
                data: 'subcont',
                className: 'text-end',
                render: function (d, type) { return type === 'display' ? fmtNum(d) : d; }
            },
            {
                data: 'deviasi',
                className: 'text-end',
                orderable: false,
                render: function (d, type) { return type === 'display' ? fmtNum(d) : d; }
            },
            {
                data: 'rata',
                className: 'text-end',
                orderable: false,
                render: function (d, type, row) {
                    if (type !== 'display') { return d === null ? -1 : d; }
                    if (d === null) { return '<span class="text-secondary-light">–</span>'; }

                    return '<span class="lpu-badge' + (row.band || 1)
                        + ' px-8 py-2 rounded-pill fw-medium text-xs">' + fmtPct(d) + '</span>';
                }
            },
            {
                data: 'bulan',
                orderable: false,
                render: function (d, type) {
                    if (type !== 'display') { return ''; }

                    return '<div class="d-flex gap-1 flex-wrap">' + d.map(function (b) {
                        if (!b.ada) {
                            return '<span class="lpu-kosong px-6 py-2 radius-4 text-xs"'
                                + ' title="' + escapeHtml(b.label) + ': belum terdata">–</span>';
                        }

                        return '<span class="lpu-b' + b.band + ' px-6 py-2 radius-4 text-xs fw-medium"'
                            + ' title="' + escapeHtml(b.label + ': pemenuhan ' + fmtPct(b.persen)
                                + ' · ' + (b.deviasi === null ? '?' : b.deviasi) + ' deviasi') + '">'
                            + escapeHtml(b.label.substring(0, 3)) + ' '
                            + Math.round(b.persen) + '%'
                            + '</span>';
                    }).join('') + '</div>';
                }
            }
        ]
    });

    filterEls.forEach(function (node) {
        node.addEventListener('change', function () { table.ajax.reload(); });
    });

    var reset = root.querySelector('[data-lpud="reset"]');

    if (reset) {
        reset.addEventListener('click', function () {
            filterEls.forEach(function (n) { n.value = ''; });
            table.search('').draw();
        });
    }

    root.querySelectorAll('[data-lpud="export"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var params = new URLSearchParams(currentFilters());
            var search = table.search();

            if (search) { params.set('search', search); }

            params.set('format', btn.dataset.format);
            window.location.href = exportUrl + '?' + params.toString();
        });
    });

    var tab = document.querySelector('#lpu-tab-data');

    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        }, { once: true });
    }
})();
</script>
@endsection
