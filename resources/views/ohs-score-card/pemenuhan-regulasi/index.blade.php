@extends('ohs-score-card.layouts.app')

@section('title', $judul)

{{--
  Pemenuhan Regulasi.

  KOLOM MATRIKSNYA SEKTOR, BUKAN BULAN. Kedua tabel sumbernya tidak punya
  kolom bulan sama sekali -- isinya potret satu waktu -- jadi halaman ini
  sengaja tidak punya penyaring bulan dan tidak menggambar tren.

  TIDAK ADA MODE "NILAI". Band resmi parameter ini belum ada, jadi warnanya
  memakai ambang sementara dari controller dan angka Nilai 1-4 tidak pernah
  ditampilkan. Menampilkannya berarti mengarang skor resmi.

  DUA TAB, DUA TABEL YANG TIDAK BOLEH DIJUMLAHKAN: tab Ringkasan dari
  regulatory_compliance_summary (per site & perusahaan), tab Data dari
  regulatory_compliance_detail (daftar regulasi, tanpa kolom site sama sekali).
--}}

@section('css')
<style>
  .prg-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .prg-matrix {
    width: 100%; min-width: 820px;
    border-collapse: separate; border-spacing: 4px; font-size: 12px;
  }
  .prg-matrix th, .prg-matrix td {
    padding: 8px 10px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .prg-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #64748B; font-weight: 600; font-size: 11px; border-radius: 8px;
  }
  .prg-matrix .prg-site, .prg-matrix .prg-mitra {
    position: sticky; z-index: 2; text-align: left !important;
    background: #F1F5F9; color: #0F172A !important; border-radius: 8px;
  }
  .prg-matrix .prg-site { left: 0; font-weight: 700; min-width: 74px; }
  .prg-matrix .prg-mitra { left: 74px; font-weight: 600; min-width: 220px; }
  .prg-matrix thead .prg-site, .prg-matrix thead .prg-mitra {
    z-index: 4; background: #F8FAFC; color: #64748B !important;
  }
  .prg-matrix .prg-rata {
    font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px;
  }

  .prg-cell {
    font-weight: 700; min-width: 96px; border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
  }
  .prg-cell--klik { cursor: pointer; }
  .prg-cell--klik:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(5,150,105,.3);
    position: relative; z-index: 1;
  }
  .prg-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Warna dari ambang SEMENTARA di controller, bukan band resmi. */
  .prg-b1 { background: #FF0000; color: #fff; }
  .prg-b2 { background: #FFC000; color: #1F2937; }
  .prg-b3 { background: #FFFF00; color: #1F2937; }
  .prg-b4 { background: #92D050; color: #1F2937; }
  /* Sektor itu memang tidak ditugaskan ke kombinasi ini -- bukan 0%. */
  .prg-kosong {
    background: #F8FAFC; color: #CBD5E1; border-style: dashed; border-color: #E2E8F0;
  }

  .prg-bar { height: 8px; border-radius: 999px; background: #EEF2F7; overflow: hidden; }
  .prg-bar > span { display: block; height: 100%; border-radius: 999px; }
  .prg-bar1 { background: #FF0000; }
  .prg-bar2 { background: #FFC000; }
  .prg-bar3 { background: #FFFF00; }
  .prg-bar4 { background: #92D050; }

  .prg-badge1 { background: #FFE5E5; color: #B91C1C !important; }
  .prg-badge2 { background: #FFF2CC; color: #92400E !important; }
  .prg-badge3 { background: #FFFBCC; color: #854D0E !important; }
  .prg-badge4 { background: #E8F5DC; color: #3F6212 !important; }

  .prg-modal-scroll { max-height: 38vh; overflow: auto; }
  .prg-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }

  .prg-nama { max-width: 420px; white-space: normal; }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">{{ $judul }}</h6>
    <span class="text-sm text-secondary-light">
      {{ $penjelasan }} — ringkasan {{ $tabelRingkasan }}, daftar regulasi {{ $tabelDetail }}
    </span>
  </div>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="prg-tab" role="tablist">
  <li class="nav-item flex-shrink-0" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="prg-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#prg-pane-ringkasan" type="button"
            role="tab" aria-controls="prg-pane-ringkasan" aria-selected="true">Ringkasan</button>
  </li>
  <li class="nav-item flex-shrink-0" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="prg-tab-data"
            data-bs-toggle="pill" data-bs-target="#prg-pane-data" type="button"
            role="tab" aria-controls="prg-pane-data" aria-selected="false">Daftar Regulasi</button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="prg-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.pemenuhan-regulasi.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="prg-pane-data" role="tabpanel">
    @include('ohs-score-card.pemenuhan-regulasi.partials._data')
  </div>
</div>
@endsection

@section('page-scripts')
<script>
(function () {
    var root = document.querySelector('.prg-overview');

    if (!root) { return; }

    var urlOverview = root.dataset.url;
    var urlDetail = root.dataset.detailUrl;
    var lastPayload = null;

    function el(nama) { return root.querySelector('[data-prg="' + nama + '"]'); }

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

    /* Nomor band datang dari controller. Ambangnya SEMENTARA dan tinggal di
       satu tempat di sana, jadi tidak disalin ke sini. */
    function cellClass(sel) {
        if (!sel || !sel.ada) { return 'prg-kosong'; }
        return 'prg-b' + (sel.band || 1);
    }

    function badgeClass(band) {
        return 'prg-badge' + (band || 1);
    }

    function barClass(band) {
        return 'prg-bar' + (band || 1);
    }

    function currentFilters() {
        var out = {};
        root.querySelectorAll('.prg-filter').forEach(function (node) {
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
            ubin('Kepatuhan keseluruhan', fmtPct(k.rata),
                fmtNum(k.patuh) + ' dari ' + fmtNum(k.total) + ' kewajiban')
            + ubin('Masih dalam proses', fmtNum(k.proses), 'kewajiban belum tuntas')
            + ubin('Sudah 100%', fmtNum(k.kombinasi_penuh) + ' / ' + fmtNum(k.kombinasi),
                'pasangan site &amp; perusahaan')
            + ubin('Terendah', fmtPct(k.terendah), 'pada satu pasangan');
    }

    // ------------------------------------------------------------- MATRIKS
    function renderMatrix(sektor, rows) {
        var thead = root.querySelector('[data-prg="matrix"] thead');
        var tbody = root.querySelector('[data-prg="matrix"] tbody');

        thead.innerHTML = '<tr><th class="prg-site">Site</th><th class="prg-mitra">Perusahaan</th>'
            + '<th>Semua Sektor</th>'
            + sektor.map(function (s) {
                return '<th>' + escapeHtml(s.label) + '</th>';
            }).join('') + '</tr>';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="' + (sektor.length + 3)
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
                + (lewati[i] ? '' : '<td class="prg-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="prg-mitra">' + escapeHtml(row.mitra) + '</td>'
                + '<td class="prg-rata" title="' + escapeHtml(
                    fmtNum(row.patuh) + ' dari ' + fmtNum(row.total) + ' kewajiban · '
                    + row.sektor_terisi + ' sektor ditugaskan')
                + '">' + fmtPct(row.average) + '</td>';

            row.cells.forEach(function (sel, m) {
                if (!sel.ada) {
                    html += '<td class="prg-cell prg-kosong" title="'
                        + escapeHtml(row.site + ' · ' + row.mitra + ' · ' + sektor[m].label
                            + ': sektor ini tidak ditugaskan') + '">–</td>';
                    return;
                }

                var tip = row.site + ' · ' + row.mitra + ' · ' + sektor[m].label + ': '
                    + fmtNum(sel.patuh) + ' dari ' + fmtNum(sel.total) + ' kewajiban ('
                    + fmtPct(sel.pct) + ')';

                html += '<td class="prg-cell prg-cell--klik ' + cellClass(sel) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-mitra="' + escapeHtml(row.mitra) + '"'
                    + ' data-sektor="' + escapeHtml(lastPayload.sektor_kunci[m]) + '"'
                    + ' title="' + escapeHtml(tip + ' · klik untuk rincian') + '">'
                    + fmtPct(sel.pct)
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
            var lebar = Math.max(0, Math.min(100, Number(r.percent || 0)));

            return '<div class="mb-16">'
                + '<div class="d-flex align-items-center justify-content-between gap-2 mb-6">'
                + '<span class="text-sm fw-medium text-truncate" title="' + escapeHtml(r.label) + '">'
                + escapeHtml(r.label) + '</span>'
                + '<span class="' + badgeClass(r.band) + ' px-8 py-2 rounded-pill fw-medium text-xs">'
                + fmtPct(r.percent) + '</span>'
                + '</div>'
                + '<div class="prg-bar"><span class="' + barClass(r.band) + '" style="width:' + lebar + '%;"></span></div>'
                + '<span class="text-xs text-secondary-light">'
                + fmtNum(r.patuh) + ' dari ' + fmtNum(r.total) + ' kewajiban</span>'
                + '</div>';
        }).join('');
    }

    // -------------------------------------------------------------- MODAL
    var modalEl = document.getElementById('prg-modal');
    var modal = modalEl && window.bootstrap ? new window.bootstrap.Modal(modalEl) : null;

    function bukaDetail(site, mitra, sektor) {
        if (!modal) { return; }

        var params = new URLSearchParams();
        params.set('site', site);
        params.set('mitra', mitra);
        params.set('sektor', sektor);

        document.querySelector('[data-prg="modal-judul"]').textContent = site + ' · ' + mitra;
        document.querySelector('[data-prg="modal-isi"]').innerHTML =
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
                document.querySelector('[data-prg="modal-isi"]').innerHTML =
                    '<div class="text-center py-24 text-danger-main text-sm">Gagal memuat rincian.</div>';
            });
    }

    function renderDetail(d) {
        document.querySelector('[data-prg="modal-judul"]').textContent =
            d.judul + ' · ' + d.sektor;

        var html = '<div class="row gy-3 mb-16">'
            + '<div class="col-sm-6"><div class="card radius-8 border h-100"><div class="card-body p-16">'
            + '<span class="text-xs text-secondary-light d-block mb-4">Kepatuhan</span>'
            + '<h6 class="fw-semibold mb-0">' + fmtPct(d.persen) + '</h6>'
            + '<span class="text-xs text-secondary-light">'
            + fmtNum(d.patuh) + ' patuh · ' + fmtNum(d.proses) + ' dalam proses · '
            + fmtNum(d.total) + ' kewajiban</span>'
            + '</div></div></div>'
            + '<div class="col-sm-6"><div class="card radius-8 border h-100"><div class="card-body p-16">'
            + '<span class="text-xs text-secondary-light d-block mb-4">Peringkat di sektor ini</span>'
            + '<h6 class="fw-semibold mb-0">' + fmtNum(d.peringkat) + ' dari ' + fmtNum(d.dari) + '</h6>'
            + '<span class="text-xs text-secondary-light">pasangan site &amp; perusahaan</span>'
            + '</div></div></div></div>';

        if (d.sektor_lain && d.sektor_lain.length) {
            html += '<h6 class="text-sm fw-semibold mb-8">Seluruh sektor kombinasi ini</h6>'
                + '<div class="prg-modal-scroll mb-16"><table class="table table-sm mb-0">'
                + '<thead><tr><th>Sektor</th><th class="text-end">Patuh</th>'
                + '<th class="text-end">Kepatuhan</th></tr></thead><tbody>'
                + d.sektor_lain.map(function (r) {
                    if (!r.ditugaskan) {
                        return '<tr class="text-secondary-light"><td>' + escapeHtml(r.label)
                            + '</td><td class="text-end">–</td>'
                            + '<td class="text-end">tidak ditugaskan</td></tr>';
                    }

                    return '<tr' + (r.ini ? ' class="fw-semibold"' : '') + '>'
                        + '<td>' + escapeHtml(r.label) + (r.ini ? ' (sel ini)' : '') + '</td>'
                        + '<td class="text-end">' + fmtNum(r.patuh) + ' / ' + fmtNum(r.total) + '</td>'
                        + '<td class="text-end">' + fmtPct(r.persen) + '</td></tr>';
                }).join('') + '</tbody></table></div>';
        }

        if (d.sesektor && d.sesektor.length) {
            html += '<h6 class="text-sm fw-semibold mb-8">Kombinasi lain pada sektor '
                + escapeHtml(d.sektor) + '</h6>'
                + '<div class="prg-modal-scroll"><table class="table table-sm mb-0">'
                + '<thead><tr><th>Site</th><th>Perusahaan</th>'
                + '<th class="text-end">Kepatuhan</th></tr></thead><tbody>'
                + d.sesektor.map(function (s) {
                    return '<tr><td>' + escapeHtml(s.site) + '</td>'
                        + '<td>' + escapeHtml(s.mitra) + '</td>'
                        + '<td class="text-end">' + fmtPct(s.persen) + '</td></tr>';
                }).join('') + '</tbody></table></div>';
        }

        document.querySelector('[data-prg="modal-isi"]').innerHTML = html;
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap
    // ganti filter, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    matrixEl.addEventListener('click', function (e) {
        var td = e.target.closest('.prg-cell--klik');
        if (td) { bukaDetail(td.dataset.site, td.dataset.mitra, td.dataset.sektor); }
    });

    matrixEl.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') { return; }
        var td = e.target.closest('.prg-cell--klik');
        if (td) { e.preventDefault(); bukaDetail(td.dataset.site, td.dataset.mitra, td.dataset.sektor); }
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
                safe('matrix', function () { renderMatrix(json.sektor || [], json.matrix || []); });
                safe('per-site', function () { renderPanel('per-site', json.per_site); });
                safe('per-mitra', function () { renderPanel('per-mitra', json.per_mitra); });
                safe('per-sektor', function () { renderPanel('per-sektor', json.per_sektor); });

                var catatan = el('catatan');
                catatan.textContent = json.catatan || '';
                catatan.classList.toggle('d-none', !json.catatan);

                var k = json.kpi;
                el('status').textContent = fmtNum(k.kombinasi) + ' pasangan site/perusahaan · '
                    + fmtNum(k.total) + ' kewajiban · kepatuhan ' + fmtPct(k.rata);
            })
            .catch(function () {
                el('status').textContent = 'Gagal memuat data.';
            });
    }

    root.querySelectorAll('.prg-filter').forEach(function (node) {
        node.addEventListener('change', muat);
    });

    var reset = el('reset');

    if (reset) {
        reset.addEventListener('click', function () {
            root.querySelectorAll('.prg-filter').forEach(function (n) { n.value = ''; });
            muat();
        });
    }

    muat();
})();
</script>

<script>
// ---- Tab Daftar Regulasi ----------------------------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.prg-datatable');

    if (!root) { return; }

    var tableEl = root.querySelector('[data-prgd="table"]');

    if (!tableEl || typeof DataTable === 'undefined') { return; }

    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.prgd-filter'));
    var hintEl = root.querySelector('[data-prgd="hint"]');

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
        order: [[6, 'asc']],
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
                hintEl.textContent = fmtNum(json.recordsFiltered) + ' regulasi';
                return json.data || [];
            },
            error: function (xhr, error) {
                hintEl.textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Pemenuhan regulasi: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            {
                data: 'nama',
                className: 'prg-nama',
                render: function (d, type, row) {
                    if (type !== 'display') { return d; }

                    var teks = escapeHtml(d);

                    if (row.tautan) {
                        teks = '<a href="' + escapeHtml(row.tautan) + '" target="_blank"'
                            + ' rel="noopener noreferrer">' + teks + '</a>';
                    }

                    return teks + '<br><span class="text-xs text-secondary-light">'
                        + escapeHtml(row.regulasi) + '</span>';
                }
            },
            { data: 'sektor' },
            { data: 'kategori' },
            {
                data: 'total',
                className: 'text-end',
                render: function (d, type) { return type === 'display' ? fmtNum(d) : d; }
            },
            {
                data: 'patuh',
                className: 'text-end',
                render: function (d, type) { return type === 'display' ? fmtNum(d) : d; }
            },
            {
                data: 'proses',
                className: 'text-end',
                orderable: false,
                render: function (d, type, row) {
                    if (type !== 'display') { return d; }

                    // Sisa = kewajiban yang belum berstatus apa pun. Ada di 40
                    // dari 363 baris, jadi tidak boleh disembunyikan.
                    var teks = fmtNum(d);

                    if (row.sisa > 0) {
                        teks += ' <span class="text-xs text-secondary-light"'
                            + ' title="belum berstatus apa pun">(+' + fmtNum(row.sisa) + ')</span>';
                    }

                    return teks;
                }
            },
            {
                data: 'persen',
                className: 'text-end',
                render: function (d, type, row) {
                    if (type !== 'display') { return d === null ? -1 : d; }
                    if (d === null) { return '<span class="text-secondary-light">–</span>'; }

                    return '<span class="prg-badge' + (row.band || 1)
                        + ' px-8 py-2 rounded-pill fw-medium text-xs">' + fmtPct(d) + '</span>';
                }
            }
        ]
    });

    filterEls.forEach(function (node) {
        node.addEventListener('change', function () { table.ajax.reload(); });
    });

    var reset = root.querySelector('[data-prgd="reset"]');

    if (reset) {
        reset.addEventListener('click', function () {
            filterEls.forEach(function (n) { n.value = ''; });
            table.search('').draw();
        });
    }

    root.querySelectorAll('[data-prgd="export"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var params = new URLSearchParams(currentFilters());
            var search = table.search();

            if (search) { params.set('search', search); }

            params.set('format', btn.dataset.format);
            window.location.href = exportUrl + '?' + params.toString();
        });
    });

    var tab = document.querySelector('#prg-tab-data');

    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        }, { once: true });
    }
})();
</script>
@endsection
