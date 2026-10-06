@extends('ohs-score-card.layouts.app')

@section('title', $judul)

{{--
  Kesiapan Alat Emergency.

  SUMBU MATRIKSNYA SITE x KATEGORI ALAT, bukan site x perusahaan seperti
  halaman parameter lain: kolom perusahaan_pemilik di inventaris kosong pada
  80% baris, jadi memakainya hanya menghasilkan satu kolom besar tanpa nama.

  PENYEBUT TIAP SEL SAMA SEPANJANG BULAN -- yaitu cacah alat di inventaris --
  sehingga persentasenya bisa dibandingkan antar bulan tanpa tertipu oleh
  jumlah alat yang diperiksa pada bulan itu.
--}}

@section('css')
<style>
  .kae-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .kae-matrix {
    width: 100%; min-width: 860px;
    border-collapse: separate; border-spacing: 4px; font-size: 12px;
  }
  .kae-matrix th, .kae-matrix td {
    padding: 8px 10px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .kae-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #64748B; font-weight: 600; font-size: 11px; border-radius: 8px;
  }
  .kae-matrix thead th.kae-th-last { background: #2E90FA !important; color: #fff !important; }
  .kae-matrix .kae-site, .kae-matrix .kae-kategori {
    position: sticky; z-index: 2; text-align: left !important;
    background: #F1F5F9; color: #0F172A !important; border-radius: 8px;
  }
  .kae-matrix .kae-site { left: 0; font-weight: 700; min-width: 128px; }
  .kae-matrix .kae-kategori { left: 128px; font-weight: 600; min-width: 190px; }
  .kae-matrix thead .kae-site, .kae-matrix thead .kae-kategori {
    z-index: 4; background: #F8FAFC; color: #64748B !important;
  }
  .kae-matrix .kae-rata {
    font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px;
  }
  .kae-matrix .kae-trend--up { color: #16A34A; font-weight: 800; }
  .kae-matrix .kae-trend--down { color: #DC2626; font-weight: 800; }
  .kae-matrix .kae-trend--flat { color: #94A3B8; font-weight: 800; }

  .kae-cell {
    font-weight: 700; min-width: 68px; border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
  }
  .kae-cell--klik { cursor: pointer; }
  .kae-cell--klik:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(5,150,105,.3);
    position: relative; z-index: 1;
  }
  .kae-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Warna band resmi: <80 merah, 80-<90 jingga, 90-<98 kuning, 98-100 hijau. */
  .kae-b1 { background: #FF0000; color: #fff; }
  .kae-b2 { background: #FFC000; color: #1F2937; }
  .kae-b3 { background: #FFFF00; color: #1F2937; }
  .kae-b4 { background: #92D050; color: #1F2937; }
  /* Belum ada lembar periksanya pada bulan itu -- bukan 0%. */
  .kae-kosong {
    background: #F8FAFC; color: #CBD5E1; border-style: dashed; border-color: #E2E8F0;
  }

  .kae-bar { height: 8px; border-radius: 999px; background: #EEF2F7; overflow: hidden; }
  .kae-bar > span { display: block; height: 100%; border-radius: 999px; }
  .kae-bar1 { background: #FF0000; }
  .kae-bar2 { background: #FFC000; }
  .kae-bar3 { background: #FFFF00; }
  .kae-bar4 { background: #92D050; }

  .kae-badge1 { background: #FFE5E5; color: #B91C1C !important; }
  .kae-badge2 { background: #FFF2CC; color: #92400E !important; }
  .kae-badge3 { background: #FFFBCC; color: #854D0E !important; }
  .kae-badge4 { background: #E8F5DC; color: #3F6212 !important; }

  .kae-modal-scroll { max-height: 38vh; overflow: auto; }
  .kae-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">{{ $judul }}</h6>
    <span class="text-sm text-secondary-light">
      {{ $penjelasan }} — baseline {{ $tabelInventaris }}, pemeriksaan {{ $tabelInspeksi }}
    </span>
  </div>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="kae-tab" role="tablist">
  <li class="nav-item flex-shrink-0" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="kae-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#kae-pane-ringkasan" type="button"
            role="tab" aria-controls="kae-pane-ringkasan" aria-selected="true">Ringkasan</button>
  </li>
  <li class="nav-item flex-shrink-0" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="kae-tab-data"
            data-bs-toggle="pill" data-bs-target="#kae-pane-data" type="button"
            role="tab" aria-controls="kae-pane-data" aria-selected="false">Data</button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="kae-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.kesiapan-alat-emergency.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="kae-pane-data" role="tabpanel">
    @include('ohs-score-card.kesiapan-alat-emergency.partials._data')
  </div>
</div>
@endsection

@section('page-scripts')
<script>
(function () {
    var root = document.querySelector('.kae-overview');

    if (!root) { return; }

    var urlOverview = root.dataset.url;
    var urlDetail = root.dataset.detailUrl;
    var matrixMode = 'persen';
    var lastPayload = null;

    function el(nama) { return root.querySelector('[data-kae="' + nama + '"]'); }

    function escapeHtml(teks) {
        return String(teks === null || teks === undefined ? '' : teks)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function fmtNum(n) {
        if (n === null || n === undefined || n === '') { return '–'; }
        return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
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

    /* Nomor band datang dari angka Nilai yang sudah dihitung controller,
       bukan dihitung ulang dari persentase di sini: satu sumber kebenaran. */
    function bandDariNilai(nilai) {
        var v = Number(nilai);
        if (!isFinite(v)) { return 0; }
        return Math.max(1, Math.min(4, Math.floor(v)));
    }

    function cellClass(sel) {
        if (!sel || !sel.ada) { return 'kae-kosong'; }
        return 'kae-b' + bandDariNilai(sel.nilai);
    }

    function badgeClass(nilai) {
        return { 1: 'kae-badge1', 2: 'kae-badge2', 3: 'kae-badge3', 4: 'kae-badge4' }[bandDariNilai(nilai)]
            || 'bg-neutral-200 text-secondary-light';
    }

    function barClass(nilai) {
        return { 1: 'kae-bar1', 2: 'kae-bar2', 3: 'kae-bar3', 4: 'kae-bar4' }[bandDariNilai(nilai)]
            || 'bg-neutral-400';
    }

    function currentFilters() {
        var out = {};
        root.querySelectorAll('.kae-filter').forEach(function (node) {
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
            ubin('Kesiapan keseluruhan', fmtPct(k.rata),
                'dari ' + fmtNum(k.alat) + ' alat di inventaris · target ' + fmtPct(k.target))
            + ubin('Nilai', fmtNilai(k.nilai), escapeHtml(k.nilai_band || '–'))
            + ubin('Memenuhi target', fmtNum(k.kombinasi_memenuhi) + ' / ' + fmtNum(k.kombinasi),
                'pasangan site &amp; kategori alat')
            + ubin('Terendah', fmtPct(k.terendah),
                fmtNum(k.bulan_count) + ' bulan terdata');
    }

    // ------------------------------------------------------------- MATRIKS
    function renderMatrix(months, rows) {
        var thead = root.querySelector('[data-kae="matrix"] thead');
        var tbody = root.querySelector('[data-kae="matrix"] tbody');

        thead.innerHTML = '<tr><th class="kae-site">Site</th><th class="kae-kategori">Kategori Alat</th>'
            + '<th>Rata</th><th>Tren</th>'
            + months.map(function (m, i) {
                return '<th class="' + (i === months.length - 1 ? 'kae-th-last' : '') + '">'
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
                + (lewati[i] ? '' : '<td class="kae-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="kae-kategori">' + escapeHtml(row.kategori) + '</td>'
                + '<td class="kae-rata" title="' + escapeHtml(
                    fmtPct(row.average) + ' · Nilai ' + fmtNilai(row.nilai)
                    + ' (' + (row.nilai_band || '') + ') · ' + row.total + ' alat · '
                    + row.bulan_terisi + ' bulan terisi')
                + '">'
                + (matrixMode === 'nilai' ? fmtNilai(row.nilai) : fmtPct(row.average)) + '</td>';

            if (row.trend === 'up') {
                html += '<td class="kae-trend--up" title="Naik dari bulan sebelumnya">&uarr;</td>';
            } else if (row.trend === 'down') {
                html += '<td class="kae-trend--down" title="Turun dari bulan sebelumnya">&darr;</td>';
            } else if (row.trend === 'flat') {
                html += '<td class="kae-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
            } else {
                html += '<td class="text-secondary-light">–</td>';
            }

            row.cells.forEach(function (sel, m) {
                if (!sel.ada) {
                    html += '<td class="kae-cell kae-kosong" title="'
                        + escapeHtml(row.site + ' · ' + row.kategori + ' · ' + months[m].label
                            + ': belum ada lembar periksa') + '">–</td>';
                    return;
                }

                var tip = row.site + ' · ' + row.kategori + ' · ' + months[m].label + ': '
                    + sel.siap + ' dari ' + sel.total + ' alat siap (' + fmtPct(sel.pct)
                    + ') · Nilai ' + fmtNilai(sel.nilai) + ' (' + (sel.nilai_band || '') + ')';

                html += '<td class="kae-cell kae-cell--klik ' + cellClass(sel) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-kategori="' + escapeHtml(row.kategori) + '"'
                    + ' data-bulan="' + months[m].number + '"'
                    + ' title="' + escapeHtml(tip + ' · klik untuk rincian') + '">'
                    + (matrixMode === 'nilai' ? fmtNilai(sel.nilai) : Math.round(sel.pct) + '%')
                    + '</td>';
            });

            return html + '</tr>';
        }).join('');

        el('matrix-subtitle').textContent = matrixMode === 'nilai'
            ? 'Nilai 1–4 tiap kategori alat di tiap site'
            : 'Persentase alat siap tiap kategori di tiap site';
    }

    function renderLegend() {
        var warna = { 1: '#FF0000', 2: '#FFC000', 3: '#FFFF00', 4: '#92D050' };
        var legenda = JSON.parse(root.dataset.legenda || '[]');

        el('legend').innerHTML = legenda.map(function (it) {
            return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                + '<span class="rounded-1" style="width:14px;height:14px;background:'
                + warna[it.nilai] + ';"></span>'
                + escapeHtml((matrixMode === 'nilai' ? 'Nilai ' + it.nilai + ' · ' : '') + it.label)
                + '</span>';
        }).join('');
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
                + '<span class="' + badgeClass(r.nilai) + ' px-8 py-2 rounded-pill fw-medium text-xs">'
                + fmtPct(r.percent) + ' · Nilai ' + fmtNilai(r.nilai) + '</span>'
                + '</div>'
                + '<div class="kae-bar"><span class="' + barClass(r.nilai) + '" style="width:' + lebar + '%;"></span></div>'
                + '<span class="text-xs text-secondary-light">' + fmtNum(r.sel) + ' sel terisi</span>'
                + '</div>';
        }).join('');
    }

    // -------------------------------------------------------------- MODAL
    var modalEl = document.getElementById('kae-modal');
    var modal = modalEl && window.bootstrap ? new window.bootstrap.Modal(modalEl) : null;

    function bukaDetail(site, kategori, bulan) {
        if (!modal) { return; }

        var params = new URLSearchParams(currentFilters());
        params.set('site', site);
        params.set('kategori', kategori);
        params.set('bulan', bulan);

        document.querySelector('[data-kae="modal-judul"]').textContent = site + ' · ' + kategori;
        document.querySelector('[data-kae="modal-isi"]').innerHTML =
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
                document.querySelector('[data-kae="modal-isi"]').innerHTML =
                    '<div class="text-center py-24 text-danger-main text-sm">Gagal memuat rincian.</div>';
            });
    }

    function renderDetail(d) {
        document.querySelector('[data-kae="modal-judul"]').textContent = d.judul + ' · ' + d.bulan;

        var html = '<div class="row gy-3 mb-16">'
            + '<div class="col-sm-6"><div class="card radius-8 border h-100"><div class="card-body p-16">'
            + '<span class="text-xs text-secondary-light d-block mb-4">Kesiapan</span>'
            + '<h6 class="fw-semibold mb-0">' + fmtPct(d.persen) + '</h6>'
            + '<span class="text-xs text-secondary-light">'
            + fmtNum(d.siap) + ' siap dari ' + fmtNum(d.total) + ' alat di inventaris</span>'
            + '</div></div></div>'
            + '<div class="col-sm-6"><div class="card radius-8 border h-100"><div class="card-body p-16">'
            + '<span class="text-xs text-secondary-light d-block mb-4">Nilai</span>'
            + '<h6 class="fw-semibold mb-0">' + fmtNilai(d.nilai) + '</h6>'
            + '<span class="text-xs text-secondary-light">' + escapeHtml(d.nilai_band || '–')
            + ' · ' + fmtNum(d.diperiksa) + ' alat punya lembar periksa'
            + (d.tanpa_lembar > 0 ? ', ' + fmtNum(d.tanpa_lembar) + ' tidak' : '')
            + '</span></div></div></div></div>';

        if (d.riwayat && d.riwayat.length) {
            html += '<h6 class="text-sm fw-semibold mb-8">Riwayat kombinasi ini</h6>'
                + '<div class="kae-modal-scroll mb-16"><table class="table table-sm mb-0">'
                + '<thead><tr><th>Bulan</th><th class="text-end">Kesiapan</th>'
                + '<th class="text-center">Nilai</th></tr></thead><tbody>'
                + d.riwayat.map(function (r) {
                    return '<tr' + (r.ini ? ' class="fw-semibold"' : '') + '>'
                        + '<td>' + escapeHtml(r.label) + (r.ini ? ' (sel ini)' : '') + '</td>'
                        + '<td class="text-end">' + fmtPct(r.persen) + '</td>'
                        + '<td class="text-center"><span class="' + badgeClass(r.nilai)
                        + ' px-8 py-2 rounded-pill fw-medium text-xs">' + fmtNilai(r.nilai)
                        + '</span></td></tr>';
                }).join('') + '</tbody></table></div>';
        }

        if (d.belum_siap && d.belum_siap.length) {
            html += '<h6 class="text-sm fw-semibold mb-8">Alat yang belum siap pada '
                + escapeHtml(d.bulan) + '</h6>'
                + '<div class="kae-modal-scroll"><table class="table table-sm mb-0">'
                + '<thead><tr><th>No Registrasi</th><th>Peralatan</th>'
                + '<th class="text-end">Hari Good</th><th>Alasan</th></tr></thead><tbody>'
                + d.belum_siap.map(function (s) {
                    return '<tr><td>' + escapeHtml(s.no_registrasi) + '</td>'
                        + '<td>' + escapeHtml(s.nama) + '</td>'
                        + '<td class="text-end">' + fmtNum(s.hari_good) + ' / ' + fmtNum(s.hari_isi) + '</td>'
                        + '<td class="text-xs">' + escapeHtml(s.alasan) + '</td></tr>';
                }).join('') + '</tbody></table></div>';

            if (d.belum_siap_dipotong) {
                html += '<span class="text-xs text-secondary-light d-block mt-8">'
                    + 'Daftar dipotong; lihat tab Data untuk seluruhnya.</span>';
            }
        } else {
            html += '<span class="text-sm text-secondary-light">'
                + 'Seluruh alat yang punya lembar periksa pada bulan ini berstatus siap.</span>';
        }

        document.querySelector('[data-kae="modal-isi"]').innerHTML = html;
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap
    // ganti filter atau mode, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    matrixEl.addEventListener('click', function (e) {
        var td = e.target.closest('.kae-cell--klik');
        if (td) { bukaDetail(td.dataset.site, td.dataset.kategori, td.dataset.bulan); }
    });

    matrixEl.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') { return; }
        var td = e.target.closest('.kae-cell--klik');
        if (td) { e.preventDefault(); bukaDetail(td.dataset.site, td.dataset.kategori, td.dataset.bulan); }
    });

    root.querySelectorAll('.kae-switch__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.mode === matrixMode) { return; }

            matrixMode = btn.dataset.mode;

            root.querySelectorAll('.kae-switch__btn').forEach(function (b) {
                b.classList.toggle('active', b.dataset.mode === matrixMode);
            });

            renderLegend();

            if (lastPayload) {
                renderMatrix(lastPayload.months || [], lastPayload.matrix || []);
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
                safe('matrix', function () { renderMatrix(json.months || [], json.matrix || []); });
                safe('per-site', function () { renderPanel('per-site', json.per_site); });
                safe('per-kategori', function () { renderPanel('per-kategori', json.per_kategori); });

                var catatan = el('catatan');
                catatan.textContent = json.catatan || '';
                catatan.classList.toggle('d-none', !json.catatan);

                var k = json.kpi;
                el('status').textContent = fmtNum(k.alat) + ' alat · '
                    + fmtNum(k.kombinasi) + ' pasangan site/kategori · '
                    + fmtNum(k.bulan_count) + ' bulan · kesiapan ' + fmtPct(k.rata)
                    + ' · Nilai ' + fmtNilai(k.nilai);
            })
            .catch(function () {
                el('status').textContent = 'Gagal memuat data.';
            });
    }

    root.querySelectorAll('.kae-filter').forEach(function (node) {
        node.addEventListener('change', muat);
    });

    var reset = el('reset');

    if (reset) {
        reset.addEventListener('click', function () {
            root.querySelectorAll('.kae-filter').forEach(function (n) { n.value = ''; });
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

    var root = document.querySelector('.kae-datatable');

    if (!root) { return; }

    var tableEl = root.querySelector('[data-kaed="table"]');

    if (!tableEl || typeof DataTable === 'undefined') { return; }

    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.kaed-filter'));
    var hintEl = root.querySelector('[data-kaed="hint"]');

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmtNum(value) {
        return Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
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
                hintEl.textContent = fmtNum(json.recordsFiltered) + ' baris';
                return json.data || [];
            },
            error: function (xhr, error) {
                hintEl.textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Kesiapan alat emergency: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'no_registrasi' },
            { data: 'nama' },
            { data: 'site' },
            { data: 'kategori' },
            // Bulan tersimpan sebagai nama, jadi urutannya dipegang nomor bulan.
            {
                data: 'bulan',
                orderable: false,
                render: function (d, type, row) {
                    return type === 'display' ? escapeHtml(d) : row.bulan_nomor;
                }
            },
            {
                data: 'hari_good',
                className: 'text-end',
                render: function (d, type, row) {
                    return type === 'display' ? fmtNum(d) + ' / ' + fmtNum(row.hari_isi) : d;
                }
            },
            {
                data: 'siap',
                className: 'text-center',
                orderable: false,
                render: function (d, type, row) {
                    if (type !== 'display') { return d ? 1 : 0; }

                    return '<span class="' + (d ? 'kae-badge4' : 'kae-badge1')
                        + ' px-8 py-2 rounded-pill fw-medium text-xs"'
                        + ' title="' + escapeHtml(row.alasan || '') + '">'
                        + (d ? 'Siap' : 'Belum') + '</span>';
                }
            },
            { data: 'alasan', orderable: false, className: 'text-xs' }
        ]
    });

    filterEls.forEach(function (node) {
        node.addEventListener('change', function () { table.ajax.reload(); });
    });

    var reset = root.querySelector('[data-kaed="reset"]');

    if (reset) {
        reset.addEventListener('click', function () {
            filterEls.forEach(function (n) { n.value = ''; });
            table.search('').draw();
        });
    }

    root.querySelectorAll('[data-kaed="export"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var params = new URLSearchParams(currentFilters());
            var search = table.search();

            if (search) { params.set('search', search); }

            params.set('format', btn.dataset.format);
            window.location.href = exportUrl + '?' + params.toString();
        });
    });

    var tab = document.querySelector('#kae-tab-data');

    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        }, { once: true });
    }
})();
</script>
@endsection
