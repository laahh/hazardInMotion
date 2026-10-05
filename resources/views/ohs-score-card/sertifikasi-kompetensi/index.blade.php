@extends('ohs-score-card.layouts.app')

@section('title', 'Pemenuhan Sertifikasi ' . $peran)

{{--
  SATU VIEW UNTUK DUA HALAMAN: Pemenuhan Sertifikasi Pengawas Teknis dan
  Tenaga Teknis. Keduanya identik kecuali sebutan peran dan tabel sumbernya,
  jadi $peran dan $slug yang membedakan. Nama rute dirakit dari $slug.

  MATRIKSNYA SITE x PERUSAHAAN, bukan site x bulan seperti parameter lain:
  tabel sumber tidak punya kolom bulan sama sekali.
--}}

@section('css')
<style>
  .skp-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .skp-matrix {
    width: 100%; min-width: 760px;
    border-collapse: separate; border-spacing: 4px; font-size: 12px;
  }
  .skp-matrix th, .skp-matrix td {
    padding: 8px 10px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .skp-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #64748B; font-weight: 600; font-size: 11px; border-radius: 8px;
  }
  .skp-matrix thead th.skp-th-total { background: #2E90FA !important; color: #fff !important; }
  .skp-matrix .skp-site {
    position: sticky; left: 0; z-index: 2;
    text-align: left !important; background: #F1F5F9; color: #0F172A !important;
    font-weight: 700; border-radius: 8px; min-width: 96px;
  }
  .skp-matrix thead .skp-site { z-index: 4; background: #F8FAFC; color: #64748B !important; }
  .skp-matrix .skp-total {
    font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px;
  }

  .skp-cell {
    font-weight: 700; min-width: 86px; border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    line-height: 1.15;
  }
  .skp-cell--klik { cursor: pointer; }
  .skp-cell--klik:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(5,150,105,.3);
    position: relative; z-index: 1;
  }
  .skp-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }
  .skp-cell__val { display: block; font-size: 12px; font-weight: 800; }
  .skp-cell__sub { display: block; font-size: 10px; font-weight: 600; opacity: .78; margin-top: 1px; }

  /* Warna band resmi; ambangnya sama persis dengan SCORE_BANDS di controller. */
  .skp-b1 { background: #FF0000; color: #fff; }
  .skp-b2 { background: #FFC000; color: #1F2937; }
  .skp-b3 { background: #FFFF00; color: #1F2937; }
  .skp-b4 { background: #92D050; color: #1F2937; }
  /* Perusahaan itu memang tidak punya orang di site ini — bukan nol persen. */
  .skp-kosong {
    background: #F8FAFC; color: #CBD5E1; border-style: dashed; border-color: #E2E8F0;
  }

  .skp-bar { height: 8px; border-radius: 999px; background: #EEF2F7; overflow: hidden; }
  .skp-bar > span { display: block; height: 100%; border-radius: 999px; }
  .skp-bar1 { background: #FF0000; }
  .skp-bar2 { background: #FFC000; }
  .skp-bar3 { background: #FFFF00; }
  .skp-bar4 { background: #92D050; }

  .skp-badge1 { background: #FFE5E5; color: #B91C1C !important; }
  .skp-badge2 { background: #FFF2CC; color: #92400E !important; }
  .skp-badge3 { background: #FFFBCC; color: #854D0E !important; }
  .skp-badge4 { background: #E8F5DC; color: #3F6212 !important; }

  .skp-modal-scroll { max-height: 42vh; overflow: auto; }
  .skp-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }
  .skp-teks { display: block; max-width: 420px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Pemenuhan Sertifikasi {{ $peran }}</h6>
    <span class="text-sm text-secondary-light">
      {{ $peran }} tersertifikasi dibagi total {{ mb_strtolower($peran) }}, dihitung per orang dari {{ $tabel }}
    </span>
  </div>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="skp-tab" role="tablist">
  <li class="nav-item flex-shrink-0" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="skp-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#skp-pane-ringkasan" type="button"
            role="tab" aria-controls="skp-pane-ringkasan" aria-selected="true">Ringkasan</button>
  </li>
  <li class="nav-item flex-shrink-0" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="skp-tab-data"
            data-bs-toggle="pill" data-bs-target="#skp-pane-data" type="button"
            role="tab" aria-controls="skp-pane-data" aria-selected="false">Data</button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="skp-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.sertifikasi-kompetensi.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="skp-pane-data" role="tabpanel">
    @include('ohs-score-card.sertifikasi-kompetensi.partials._data')
  </div>
</div>
@endsection

@section('page-scripts')
<script>
(function () {
    var root = document.querySelector('.skp-overview');

    if (!root) { return; }

    var urlOverview = root.dataset.url;
    var urlDetail = root.dataset.detailUrl;
    var matrixMode = 'persen';
    var lastPayload = null;
    var loaded = false;

    function el(nama) { return root.querySelector('[data-skp="' + nama + '"]'); }

    function escapeHtml(teks) {
        return String(teks === null || teks === undefined ? '' : teks)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function fmtNum(n) {
        if (n === null || n === undefined || n === '') { return '–'; }
        return Number(n).toLocaleString('id-ID');
    }

    function fmtPct(n) {
        if (n === null || n === undefined || n === '') { return '–'; }
        return Number(n).toLocaleString('id-ID', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        }) + '%';
    }

    function fmtNilai(n) {
        if (n === null || n === undefined || n === '') { return '–'; }
        return Number(n).toLocaleString('id-ID', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    // BAND SELALU DITURUNKAN DARI PERSENTASE, tidak pernah dari nilai yang
    // sudah dibulatkan: pembulatan dua desimal bisa menyeberangi batas band.
    // Ambangnya sama persis dengan SCORE_BANDS di controller.
    function bandPersen(pct) {
        var v = Number(pct);
        if (!isFinite(v)) { return 0; }
        if (v >= 80) { return 4; }
        if (v >= 60) { return 3; }
        if (v >= 50) { return 2; }
        return 1;
    }

    function cellClass(sel) {
        if (!sel || !sel.ada || sel.persen === null) { return 'skp-kosong'; }
        return 'skp-b' + bandPersen(sel.persen);
    }

    function badgeClass(pct) {
        return { 1: 'skp-badge1', 2: 'skp-badge2', 3: 'skp-badge3', 4: 'skp-badge4' }[bandPersen(pct)]
            || 'bg-neutral-200 text-secondary-light';
    }

    function barClass(pct) {
        return { 1: 'skp-bar1', 2: 'skp-bar2', 3: 'skp-bar3', 4: 'skp-bar4' }[bandPersen(pct)]
            || 'bg-neutral-400';
    }

    function currentFilters() {
        var out = {};
        root.querySelectorAll('.skp-filter').forEach(function (node) {
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
    function ubin(label, nilai, catatan, kelas) {
        return '<div class="col-xxl-3 col-sm-6">'
            + '<div class="card radius-8 border h-100"><div class="card-body p-20">'
            + '<span class="text-sm text-secondary-light d-block mb-4">' + escapeHtml(label) + '</span>'
            + '<h6 class="fw-semibold mb-4 ' + (kelas || '') + '">' + nilai + '</h6>'
            + '<span class="text-xs text-secondary-light">' + catatan + '</span>'
            + '</div></div></div>';
    }

    function renderKpi(k) {
        var catatanOrang = k.kombinasi > k.total
            ? fmtNum(k.kombinasi) + ' pasangan nama·site·perusahaan'
            : 'dari ' + fmtNum(k.baris) + ' baris sumber';

        el('kpi').innerHTML =
            ubin('Total ' + {!! json_encode(mb_strtolower($peran)) !!}, fmtNum(k.total), catatanOrang)
            + ubin('Sudah bersertifikat', fmtNum(k.bersertifikat),
                'belum: ' + fmtNum(k.belum) + ' orang')
            + ubin('Pemenuhan', fmtPct(k.persen),
                'target ' + fmtPct(k.target) + ' · '
                + (k.memenuhi_target ? 'memenuhi' : 'di bawah target'))
            + ubin('Nilai', fmtNilai(k.nilai),
                escapeHtml(k.nilai_band || '–'),
                'skp-nilai-besar');
    }

    // ------------------------------------------------------------- MATRIKS
    function renderMatrix(sites, perusahaan, rows) {
        var thead = root.querySelector('[data-skp="matrix"] thead');
        var tbody = root.querySelector('[data-skp="matrix"] tbody');

        thead.innerHTML = '<tr><th class="skp-site">Site</th>'
            + perusahaan.map(function (p) { return '<th>' + escapeHtml(p) + '</th>'; }).join('')
            + '<th class="skp-th-total">Semua</th></tr>';

        tbody.innerHTML = rows.map(function (row) {
            var html = '<tr><td class="skp-site">' + escapeHtml(row.site) + '</td>';

            row.cells.forEach(function (sel, i) {
                if (!sel.ada) {
                    html += '<td class="skp-cell skp-kosong" title="'
                        + escapeHtml(row.site + ' · ' + perusahaan[i] + ': tidak ada orang terdata')
                        + '">–</td>';
                    return;
                }

                var tip = row.site + ' · ' + perusahaan[i] + ': '
                    + fmtNum(sel.bersertifikat) + ' dari ' + fmtNum(sel.total) + ' orang bersertifikat · '
                    + fmtPct(sel.persen) + ' · Nilai ' + fmtNilai(sel.nilai)
                    + ' (' + (sel.nilai_band || '') + ')';

                html += '<td class="skp-cell skp-cell--klik ' + cellClass(sel) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-mitra="' + escapeHtml(perusahaan[i]) + '"'
                    + ' title="' + escapeHtml(tip + ' · klik untuk rincian') + '">'
                    + '<span class="skp-cell__val">'
                    + (matrixMode === 'nilai' ? fmtNilai(sel.nilai) : fmtPct(sel.persen))
                    + '</span>'
                    + '<span class="skp-cell__sub">'
                    + fmtNum(sel.bersertifikat) + '/' + fmtNum(sel.total)
                    + '</span></td>';
            });

            html += '<td class="skp-cell skp-total" title="'
                + escapeHtml(row.site + ' · semua perusahaan: ' + fmtNum(row.bersertifikat)
                    + ' dari ' + fmtNum(row.total) + ' orang') + '">'
                + '<span class="skp-cell__val">'
                + (matrixMode === 'nilai' ? fmtNilai(row.nilai) : fmtPct(row.persen))
                + '</span><span class="skp-cell__sub">'
                + fmtNum(row.bersertifikat) + '/' + fmtNum(row.total) + '</span></td>';

            return html + '</tr>';
        }).join('');

        el('matrix-subtitle').textContent = matrixMode === 'nilai'
            ? 'Nilai 1–4 dari pemenuhan sertifikasi, tiap perusahaan di tiap site'
            : 'Persentase pemenuhan sertifikasi, tiap perusahaan di tiap site';
    }

    function renderLegend() {
        var items = matrixMode === 'nilai'
            ? [
                { color: '#FF0000', label: 'Nilai 1 · <50%' },
                { color: '#FFC000', label: 'Nilai 2 · 50–<60%' },
                { color: '#FFFF00', label: 'Nilai 3 · 60–<80%' },
                { color: '#92D050', label: 'Nilai 4 · ≥80%' }
            ]
            : [
                { color: '#FF0000', label: '<50%' },
                { color: '#FFC000', label: '50–<60%' },
                { color: '#FFFF00', label: '60–<80%' },
                { color: '#92D050', label: '≥80%' }
            ];

        el('legend').innerHTML = items.map(function (it) {
            return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                + '<span class="rounded-1" style="width:14px;height:14px;background:' + it.color + ';"></span>'
                + escapeHtml(it.label) + '</span>';
        }).join('');
    }

    // -------------------------------------------------------------- PANEL
    function renderPanel(nama, rows, judulKolom) {
        if (!rows || !rows.length) {
            el(nama).innerHTML = '<span class="text-sm text-secondary-light">Tidak ada data.</span>';
            return;
        }

        el(nama).innerHTML = rows.map(function (r) {
            var lebar = Math.max(0, Math.min(100, Number(r.persen || 0)));

            return '<div class="mb-16">'
                + '<div class="d-flex align-items-center justify-content-between gap-2 mb-6">'
                + '<span class="text-sm fw-medium text-truncate" title="' + escapeHtml(r.label) + '">'
                + escapeHtml(r.label) + '</span>'
                + '<span class="' + badgeClass(r.persen) + ' px-8 py-2 rounded-pill fw-medium text-xs">'
                + fmtPct(r.persen) + ' · Nilai ' + fmtNilai(r.nilai) + '</span>'
                + '</div>'
                + '<div class="skp-bar"><span class="' + barClass(r.persen) + '" style="width:' + lebar + '%;"></span></div>'
                + '<span class="text-xs text-secondary-light">' + fmtNum(r.bersertifikat)
                + ' dari ' + fmtNum(r.total) + ' orang</span>'
                + '</div>';
        }).join('');
    }

    // -------------------------------------------------------------- MODAL
    var modalEl = document.getElementById('skp-modal');
    var modal = modalEl && window.bootstrap ? new window.bootstrap.Modal(modalEl) : null;

    function bukaDetail(site, mitra) {
        if (!modal) { return; }

        var params = new URLSearchParams(currentFilters());
        params.set('site', site);
        params.set('mitra', mitra);

        document.querySelector('[data-skp="modal-judul"]').textContent = site + ' · ' + mitra;
        document.querySelector('[data-skp="modal-isi"]').innerHTML =
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
                document.querySelector('[data-skp="modal-isi"]').innerHTML =
                    '<div class="text-center py-24 text-danger-main text-sm">Gagal memuat rincian.</div>';
            });
    }

    function renderDetail(d) {
        var n = d.nilai;

        var html = '<div class="row gy-3 mb-16">'
            + '<div class="col-sm-6"><div class="card radius-8 border h-100"><div class="card-body p-16">'
            + '<span class="text-xs text-secondary-light d-block mb-4">Pemenuhan sertifikasi</span>'
            + '<h6 class="fw-semibold mb-0">' + fmtPct(n.persen) + '</h6>'
            + '<span class="text-xs text-secondary-light">' + fmtNum(n.bersertifikat)
            + ' dari ' + fmtNum(n.total) + ' orang</span>'
            + '</div></div></div>'
            + '<div class="col-sm-6"><div class="card radius-8 border h-100"><div class="card-body p-16">'
            + '<span class="text-xs text-secondary-light d-block mb-4">Nilai</span>'
            + '<h6 class="fw-semibold mb-0">' + fmtNilai(n.nilai) + '</h6>'
            + '<span class="text-xs text-secondary-light">' + escapeHtml(n.nilai_band || '–') + '</span>'
            + '</div></div></div></div>';

        if (d.sertifikasi && d.sertifikasi.length) {
            html += '<h6 class="text-sm fw-semibold mb-8">Sertifikasi yang dimiliki</h6>'
                + '<div class="mb-16">' + d.sertifikasi.map(function (s) {
                    return '<div class="d-flex justify-content-between gap-2 text-sm py-4 border-bottom">'
                        + '<span class="skp-teks" title="' + escapeHtml(s.label) + '">' + escapeHtml(s.label) + '</span>'
                        + '<span class="fw-semibold">' + fmtNum(s.orang) + ' orang</span></div>';
                }).join('') + '</div>';
        }

        html += '<h6 class="text-sm fw-semibold mb-8">Daftar orang'
            + (d.dipotong ? ' (' + fmtNum(d.batas) + ' pertama, belum bersertifikat lebih dulu)' : '')
            + '</h6>'
            + '<div class="skp-modal-scroll"><table class="table table-sm mb-0">'
            + '<thead><tr><th>Nama</th><th class="text-center">Izin kerja</th>'
            + '<th>Sertifikasi</th><th class="text-center">Status</th></tr></thead><tbody>'
            + d.orang.map(function (o) {
                return '<tr><td>' + escapeHtml(o.nama) + '</td>'
                    + '<td class="text-center">' + fmtNum(o.izin) + '</td>'
                    + '<td><span class="skp-teks" title="' + escapeHtml(o.sertifikasi || '') + '">'
                    + escapeHtml(o.sertifikasi || '–') + '</span></td>'
                    + '<td class="text-center"><span class="'
                    + (o.bersertifikat ? 'skp-badge4' : 'skp-badge1')
                    + ' px-8 py-2 rounded-pill fw-medium text-xs">'
                    + (o.bersertifikat ? 'Bersertifikat' : 'Belum') + '</span></td></tr>';
            }).join('')
            + '</tbody></table></div>';

        document.querySelector('[data-skp="modal-isi"]').innerHTML = html;
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap
    // ganti filter atau mode, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    matrixEl.addEventListener('click', function (e) {
        var td = e.target.closest('.skp-cell--klik');
        if (td) { bukaDetail(td.dataset.site, td.dataset.mitra); }
    });

    matrixEl.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') { return; }
        var td = e.target.closest('.skp-cell--klik');
        if (td) { e.preventDefault(); bukaDetail(td.dataset.site, td.dataset.mitra); }
    });

    // Ganti mode hanya menggambar ulang dari payload terakhir.
    root.querySelectorAll('.skp-switch__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.mode === matrixMode) { return; }

            matrixMode = btn.dataset.mode;

            root.querySelectorAll('.skp-switch__btn').forEach(function (b) {
                b.classList.toggle('active', b.dataset.mode === matrixMode);
            });

            renderLegend();

            if (lastPayload) {
                renderMatrix(lastPayload.sites || [], lastPayload.perusahaan || [], lastPayload.matrix || []);
            }
        });
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
                safe('matrix', function () {
                    renderMatrix(json.sites || [], json.perusahaan || [], json.matrix || []);
                });
                safe('per-site', function () { renderPanel('per-site', json.per_site); });
                safe('per-mitra', function () { renderPanel('per-mitra', json.per_mitra); });
                safe('per-izin', function () { renderPanel('per-izin', json.per_izin); });

                el('catatan').textContent = json.catatan || '';

                var k = json.kpi;
                el('status').textContent = fmtNum(k.total) + ' orang · '
                    + fmtNum(k.bersertifikat) + ' bersertifikat · ' + fmtPct(k.persen)
                    + ' · Nilai ' + fmtNilai(k.nilai);
                loaded = true;
            })
            .catch(function () {
                el('status').textContent = 'Gagal memuat data.';
            });
    }

    root.querySelectorAll('.skp-filter').forEach(function (node) {
        node.addEventListener('change', muat);
    });

    var reset = el('reset');

    if (reset) {
        reset.addEventListener('click', function () {
            root.querySelectorAll('.skp-filter').forEach(function (n) { n.value = ''; });
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

    var root = document.querySelector('.skp-datatable');

    if (!root) { return; }

    var tableEl = root.querySelector('[data-skpd="table"]');

    if (!tableEl || typeof DataTable === 'undefined') { return; }

    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.skpd-filter'));
    var hintEl = root.querySelector('[data-skpd="hint"]');

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
                hintEl.textContent = fmtNum(json.recordsFiltered) + ' orang';

                return json.data || [];
            },
            error: function (xhr, error) {
                hintEl.textContent = 'gagal memuat';

                if (typeof console !== 'undefined' && console.error) {
                    console.error('Sertifikasi: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'nama' },
            { data: 'site' },
            { data: 'mitra' },
            {
                data: 'izin',
                className: 'text-end',
                render: function (d, type) {
                    return type === 'display' ? fmtNum(d) : d;
                }
            },
            {
                data: 'sertifikasi',
                orderable: false,
                render: function (d, type) {
                    if (type !== 'display') { return d; }

                    return d
                        ? '<span class="skp-teks" title="' + escapeHtml(d) + '">' + escapeHtml(d) + '</span>'
                        : '<span class="text-secondary-light">–</span>';
                }
            },
            {
                data: 'bersertifikat',
                className: 'text-center',
                render: function (d, type, row) {
                    if (type !== 'display') { return d ? 1 : 0; }

                    return '<span class="' + (d ? 'skp-badge4' : 'skp-badge1')
                        + ' px-8 py-2 rounded-pill fw-medium text-xs">'
                        + escapeHtml(row.status) + '</span>';
                }
            }
        ]
    });

    filterEls.forEach(function (node) {
        node.addEventListener('change', function () { table.ajax.reload(); });
    });

    var reset = root.querySelector('[data-skpd="reset"]');

    if (reset) {
        reset.addEventListener('click', function () {
            filterEls.forEach(function (n) { n.value = ''; });
            table.search('').draw();
        });
    }

    root.querySelectorAll('[data-skpd="export"]').forEach(function (btn) {
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
    var tab = document.querySelector('#skp-tab-data');

    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        }, { once: true });
    }
})();
</script>
@endsection
