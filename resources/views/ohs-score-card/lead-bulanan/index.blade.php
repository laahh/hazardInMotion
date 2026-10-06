@extends('ohs-score-card.layouts.app')

@section('title', $judul)

{{--
  SATU VIEW UNTUK TIGA HALAMAN: Utilisasi BeSigma, Penuntasan Pengendalian
  Rekayasa, dan Pelaksanaan Sobriety Test. Ketiganya berbentuk sama -- satu
  baris per site, perusahaan, dan bulan -- jadi yang membedakan hanya $judul,
  $slug, dan ambang band yang dikirim lewat $legenda.

  AMBANG BAND TIDAK DITULIS ULANG DI JS: warnanya diambil dari nilai yang sudah
  dihitung controller, karena ketiga halaman ini punya band yang berbeda-beda
  (Penuntasan Rekayasa bahkan memberi nilai tertinggi pada capaian di ATAS
  100%). Menyalin ambangnya ke JS akan mengundang ketidakcocokan.
--}}

@section('css')
<style>
  .lbn-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .lbn-matrix {
    width: 100%; min-width: 860px;
    border-collapse: separate; border-spacing: 4px; font-size: 12px;
  }
  .lbn-matrix th, .lbn-matrix td {
    padding: 8px 10px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .lbn-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #64748B; font-weight: 600; font-size: 11px; border-radius: 8px;
  }
  .lbn-matrix thead th.lbn-th-last { background: #2E90FA !important; color: #fff !important; }
  .lbn-matrix .lbn-site, .lbn-matrix .lbn-mitra {
    position: sticky; z-index: 2; text-align: left !important;
    background: #F1F5F9; color: #0F172A !important; border-radius: 8px;
  }
  .lbn-matrix .lbn-site { left: 0; font-weight: 700; min-width: 84px; }
  .lbn-matrix .lbn-mitra { left: 84px; font-weight: 600; min-width: 190px; }
  .lbn-matrix thead .lbn-site, .lbn-matrix thead .lbn-mitra {
    z-index: 4; background: #F8FAFC; color: #64748B !important;
  }
  .lbn-matrix .lbn-rata {
    font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px;
  }
  .lbn-matrix .lbn-trend--up { color: #16A34A; font-weight: 800; }
  .lbn-matrix .lbn-trend--down { color: #DC2626; font-weight: 800; }
  .lbn-matrix .lbn-trend--flat { color: #94A3B8; font-weight: 800; }

  .lbn-cell {
    font-weight: 700; min-width: 68px; border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
  }
  .lbn-cell--klik { cursor: pointer; }
  .lbn-cell--klik:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(5,150,105,.3);
    position: relative; z-index: 1;
  }
  .lbn-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Warna band resmi. Nomor bandnya datang dari controller, bukan dihitung
     ulang di sini, karena tiap halaman punya ambang yang berbeda. */
  .lbn-b1 { background: #FF0000; color: #fff; }
  .lbn-b2 { background: #FFC000; color: #1F2937; }
  .lbn-b3 { background: #FFFF00; color: #1F2937; }
  .lbn-b4 { background: #92D050; color: #1F2937; }
  /* Ada angkanya, tapi band resminya belum bisa dihitung. */
  .lbn-tanpa-band { background: #E2E8F0; color: #475569; }
  /* Belum ada datanya pada bulan itu -- bukan 0%. */
  .lbn-kosong {
    background: #F8FAFC; color: #CBD5E1; border-style: dashed; border-color: #E2E8F0;
  }

  .lbn-bar { height: 8px; border-radius: 999px; background: #EEF2F7; overflow: hidden; }
  .lbn-bar > span { display: block; height: 100%; border-radius: 999px; }
  .lbn-bar1 { background: #FF0000; }
  .lbn-bar2 { background: #FFC000; }
  .lbn-bar3 { background: #FFFF00; }
  .lbn-bar4 { background: #92D050; }

  .lbn-badge1 { background: #FFE5E5; color: #B91C1C !important; }
  .lbn-badge2 { background: #FFF2CC; color: #92400E !important; }
  .lbn-badge3 { background: #FFFBCC; color: #854D0E !important; }
  .lbn-badge4 { background: #E8F5DC; color: #3F6212 !important; }

  .lbn-modal-scroll { max-height: 38vh; overflow: auto; }
  .lbn-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }
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
    id="lbn-tab" role="tablist">
  <li class="nav-item flex-shrink-0" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="lbn-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#lbn-pane-ringkasan" type="button"
            role="tab" aria-controls="lbn-pane-ringkasan" aria-selected="true">Ringkasan</button>
  </li>
  <li class="nav-item flex-shrink-0" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="lbn-tab-data"
            data-bs-toggle="pill" data-bs-target="#lbn-pane-data" type="button"
            role="tab" aria-controls="lbn-pane-data" aria-selected="false">Data</button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="lbn-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.lead-bulanan.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="lbn-pane-data" role="tabpanel">
    @include('ohs-score-card.lead-bulanan.partials._data')
  </div>
</div>
@endsection

@section('page-scripts')
<script>
(function () {
    var root = document.querySelector('.lbn-overview');

    if (!root) { return; }

    var urlOverview = root.dataset.url;
    var urlDetail = root.dataset.detailUrl;
    var pecahan = JSON.parse(root.dataset.pecahan || '["Pembilang","Penyebut"]');
    /* Sebagian parameter yang memakai view ini satuannya cacah, bukan persen;
       angkanya tidak boleh diberi tanda persen. */
    var satuan = root.dataset.satuan || '%';
    var matrixMode = 'persen';
    var lastPayload = null;

    function el(nama) { return root.querySelector('[data-lbn="' + nama + '"]'); }

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

        if (satuan !== '%') {
            return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 0 });
        }

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

    /* Nomor band datang dari controller lewat angka Nilai-nya. Ketiga halaman
       yang memakai view ini punya ambang berbeda -- Penuntasan Rekayasa bahkan
       memberi nilai tertinggi di ATAS 100% -- jadi menghitung ulang band dari
       persentase di sini pasti salah untuk salah satunya. */
    function bandDariNilai(nilai) {
        var v = Number(nilai);
        if (!isFinite(v)) { return 0; }
        return Math.max(1, Math.min(4, Math.floor(v)));
    }

    function cellClass(sel) {
        if (!sel || !sel.ada) { return 'lbn-kosong'; }
        // Ada angkanya tetapi band resminya belum bisa dihitung: dibedakan dari
        // sel yang memang belum ada datanya.
        if (sel.nilai === null) { return 'lbn-tanpa-band'; }

        return 'lbn-b' + bandDariNilai(sel.nilai);
    }

    function badgeClass(nilai) {
        return { 1: 'lbn-badge1', 2: 'lbn-badge2', 3: 'lbn-badge3', 4: 'lbn-badge4' }[bandDariNilai(nilai)]
            || 'bg-neutral-200 text-secondary-light';
    }

    function barClass(nilai) {
        return { 1: 'lbn-bar1', 2: 'lbn-bar2', 3: 'lbn-bar3', 4: 'lbn-bar4' }[bandDariNilai(nilai)]
            || 'bg-neutral-400';
    }

    function currentFilters() {
        var out = {};
        root.querySelectorAll('.lbn-filter').forEach(function (node) {
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
        var adaBand = JSON.parse(root.dataset.legenda || '[]').length > 0;

        // Tanpa band, ubin "Nilai" dan "Memenuhi target" tidak ada artinya:
        // targetnya berbasis persen sedangkan angkanya cacah, jadi hasilnya
        // selalu nol dan hanya menyesatkan.
        if (!adaBand) {
            el('kpi').innerHTML =
                ubin('Rata-rata per sel', fmtPct(k.rata),
                    'dari ' + fmtNum(k.sel_terisi) + ' sel terisi')
                + ubin('Pasangan site &amp; perusahaan', fmtNum(k.kombinasi),
                    fmtNum(k.bulan_count) + ' bulan terdata')
                + ubin('Terendah', fmtPct(k.terendah), 'pada satu sel')
                + ubin('Nilai', '–', 'band resmi belum bisa dihitung');

            return;
        }

        el('kpi').innerHTML =
            ubin('Rata-rata capaian', fmtPct(k.rata),
                'dari ' + fmtNum(k.sel_terisi) + ' sel terisi · target ' + fmtPct(k.target))
            + ubin('Nilai', fmtNilai(k.nilai), escapeHtml(k.nilai_band || '–'))
            + ubin('Memenuhi target', fmtNum(k.kombinasi_memenuhi) + ' / ' + fmtNum(k.kombinasi),
                'pasangan site &amp; perusahaan')
            + ubin('Terendah', fmtPct(k.terendah),
                fmtNum(k.bulan_count) + ' bulan terdata');
    }

    // ------------------------------------------------------------- MATRIKS
    function renderMatrix(months, rows) {
        var thead = root.querySelector('[data-lbn="matrix"] thead');
        var tbody = root.querySelector('[data-lbn="matrix"] tbody');

        thead.innerHTML = '<tr><th class="lbn-site">Site</th><th class="lbn-mitra">Perusahaan</th>'
            + '<th>Rata</th><th>Tren</th>'
            + months.map(function (m, i) {
                return '<th class="' + (i === months.length - 1 ? 'lbn-th-last' : '') + '">'
                    + escapeHtml(m.short) + '</th>';
            }).join('') + '</tr>';

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
                + (lewati[i] ? '' : '<td class="lbn-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="lbn-mitra">' + escapeHtml(row.mitra) + '</td>'
                + '<td class="lbn-rata" title="' + escapeHtml(
                    fmtPct(row.average) + ' · Nilai ' + fmtNilai(row.nilai)
                    + ' (' + (row.nilai_band || '') + ') · ' + row.bulan_terisi + ' bulan terisi')
                + '">'
                + (matrixMode === 'nilai' ? fmtNilai(row.nilai) : fmtPct(row.average)) + '</td>';

            if (row.trend === 'up') {
                html += '<td class="lbn-trend--up" title="Naik dari bulan sebelumnya">&uarr;</td>';
            } else if (row.trend === 'down') {
                html += '<td class="lbn-trend--down" title="Turun dari bulan sebelumnya">&darr;</td>';
            } else if (row.trend === 'flat') {
                html += '<td class="lbn-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
            } else {
                html += '<td class="text-secondary-light">–</td>';
            }

            row.cells.forEach(function (sel, m) {
                if (!sel.ada) {
                    html += '<td class="lbn-cell lbn-kosong" title="'
                        + escapeHtml(row.site + ' · ' + row.mitra + ' · ' + months[m].label
                            + ': belum ada data') + '">–</td>';
                    return;
                }

                var tip = row.site + ' · ' + row.mitra + ' · ' + months[m].label + ': '
                    + fmtPct(sel.pct) + ' · Nilai ' + fmtNilai(sel.nilai)
                    + ' (' + (sel.nilai_band || '') + ')';

                html += '<td class="lbn-cell lbn-cell--klik ' + cellClass(sel) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-mitra="' + escapeHtml(row.mitra) + '"'
                    + ' data-bulan="' + months[m].number + '"'
                    + ' title="' + escapeHtml(tip + ' · klik untuk rincian') + '">'
                    + (matrixMode === 'nilai' ? fmtNilai(sel.nilai) : Math.round(sel.pct) + '%')
                    + '</td>';
            });

            return html + '</tr>';
        }).join('');

        el('matrix-subtitle').textContent = matrixMode === 'nilai'
            ? 'Nilai 1–4 tiap perusahaan di tiap site'
            : 'Persentase capaian tiap perusahaan di tiap site';
    }

    function renderLegend() {
        var warna = { 1: '#FF0000', 2: '#FFC000', 3: '#FFFF00', 4: '#92D050' };
        var legenda = JSON.parse(root.dataset.legenda || '[]');

        if (!legenda.length) {
            el('legend').innerHTML = '<span class="d-inline-flex align-items-center gap-1 text-xs"'
                + ' style="color:#64748B;"><span class="rounded-1"'
                + ' style="width:14px;height:14px;background:#E2E8F0;"></span>'
                + 'band resmi belum bisa dihitung — lihat catatan di bawah</span>';

            return;
        }

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
                + '<div class="lbn-bar"><span class="' + barClass(r.nilai) + '" style="width:' + lebar + '%;"></span></div>'
                + '<span class="text-xs text-secondary-light">' + fmtNum(r.sel) + ' sel terisi</span>'
                + '</div>';
        }).join('');
    }

    // -------------------------------------------------------------- MODAL
    var modalEl = document.getElementById('lbn-modal');
    var modal = modalEl && window.bootstrap ? new window.bootstrap.Modal(modalEl) : null;

    function bukaDetail(site, mitra, bulan) {
        if (!modal) { return; }

        var params = new URLSearchParams(currentFilters());
        params.set('site', site);
        params.set('mitra', mitra);
        params.set('bulan', bulan);

        document.querySelector('[data-lbn="modal-judul"]').textContent = site + ' · ' + mitra;
        document.querySelector('[data-lbn="modal-isi"]').innerHTML =
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
                document.querySelector('[data-lbn="modal-isi"]').innerHTML =
                    '<div class="text-center py-24 text-danger-main text-sm">Gagal memuat rincian.</div>';
            });
    }

    function renderDetail(d) {
        document.querySelector('[data-lbn="modal-judul"]').textContent =
            d.judul + ' · ' + d.bulan;

        var html = '<div class="row gy-3 mb-16">'
            + '<div class="col-sm-6"><div class="card radius-8 border h-100"><div class="card-body p-16">'
            + '<span class="text-xs text-secondary-light d-block mb-4">Capaian</span>'
            + '<h6 class="fw-semibold mb-0">' + fmtPct(d.persen) + '</h6>'
            + '<span class="text-xs text-secondary-light">'
            + escapeHtml(d.label_pecahan[0]) + ' ' + fmtNum(d.pembilang) + ' · '
            + escapeHtml(d.label_pecahan[1]) + ' ' + fmtNum(d.penyebut) + '</span>'
            + '</div></div></div>'
            + '<div class="col-sm-6"><div class="card radius-8 border h-100"><div class="card-body p-16">'
            + '<span class="text-xs text-secondary-light d-block mb-4">Nilai</span>'
            + '<h6 class="fw-semibold mb-0">' + fmtNilai(d.nilai) + '</h6>'
            + '<span class="text-xs text-secondary-light">' + escapeHtml(d.nilai_band || '–')
            + ' · peringkat ' + fmtNum(d.peringkat) + ' dari ' + fmtNum(d.dari) + ' bulan ini</span>'
            + '</div></div></div></div>';

        if (d.riwayat && d.riwayat.length) {
            html += '<h6 class="text-sm fw-semibold mb-8">Riwayat kombinasi ini</h6>'
                + '<div class="lbn-modal-scroll mb-16"><table class="table table-sm mb-0">'
                + '<thead><tr><th>Bulan</th><th class="text-end">Capaian</th>'
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

        if (d.sebulan && d.sebulan.length) {
            html += '<h6 class="text-sm fw-semibold mb-8">Kombinasi lain pada ' + escapeHtml(d.bulan) + '</h6>'
                + '<div class="lbn-modal-scroll"><table class="table table-sm mb-0">'
                + '<thead><tr><th>Site</th><th>Perusahaan</th><th class="text-end">Capaian</th></tr></thead><tbody>'
                + d.sebulan.map(function (s) {
                    return '<tr><td>' + escapeHtml(s.site) + '</td>'
                        + '<td>' + escapeHtml(s.mitra) + '</td>'
                        + '<td class="text-end">' + fmtPct(s.persen) + '</td></tr>';
                }).join('') + '</tbody></table></div>';
        }

        document.querySelector('[data-lbn="modal-isi"]').innerHTML = html;
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap
    // ganti filter atau mode, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    matrixEl.addEventListener('click', function (e) {
        var td = e.target.closest('.lbn-cell--klik');
        if (td) { bukaDetail(td.dataset.site, td.dataset.mitra, td.dataset.bulan); }
    });

    matrixEl.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') { return; }
        var td = e.target.closest('.lbn-cell--klik');
        if (td) { e.preventDefault(); bukaDetail(td.dataset.site, td.dataset.mitra, td.dataset.bulan); }
    });

    // Parameter tanpa band tidak punya mode Nilai sama sekali, jadi
    // sakelarnya disembunyikan alih-alih menawarkan tampilan kosong.
    if (JSON.parse(root.dataset.legenda || '[]').length === 0) {
        var sakelar = root.querySelector('[data-lbn="switch"]');

        if (sakelar) { sakelar.classList.add('d-none'); }
    }

    root.querySelectorAll('.lbn-switch__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.mode === matrixMode) { return; }

            matrixMode = btn.dataset.mode;

            root.querySelectorAll('.lbn-switch__btn').forEach(function (b) {
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
                safe('per-mitra', function () { renderPanel('per-mitra', json.per_mitra); });

                var catatan = el('catatan');
                catatan.textContent = json.catatan || '';
                catatan.classList.toggle('d-none', !json.catatan);

                var k = json.kpi;
                el('status').textContent = fmtNum(k.kombinasi) + ' pasangan site/perusahaan · '
                    + fmtNum(k.bulan_count) + ' bulan · rata-rata ' + fmtPct(k.rata)
                    + ' · Nilai ' + fmtNilai(k.nilai);
            })
            .catch(function () {
                el('status').textContent = 'Gagal memuat data.';
            });
    }

    root.querySelectorAll('.lbn-filter').forEach(function (node) {
        node.addEventListener('change', muat);
    });

    var reset = el('reset');

    if (reset) {
        reset.addEventListener('click', function () {
            root.querySelectorAll('.lbn-filter').forEach(function (n) { n.value = ''; });
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

    var root = document.querySelector('.lbn-datatable');

    if (!root) { return; }

    var tableEl = root.querySelector('[data-lbnd="table"]');

    if (!tableEl || typeof DataTable === 'undefined') { return; }

    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.lbnd-filter'));
    var hintEl = root.querySelector('[data-lbnd="hint"]');

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmtNum(value) {
        return Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }

    function fmtPct(value) {
        if (value === null || value === undefined) { return '–'; }
        return Number(value).toLocaleString('id-ID', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        }) + '%';
    }

    function badgeClass(nilai) {
        var v = Number(nilai);
        if (!isFinite(v)) { return 'bg-neutral-200 text-secondary-light'; }
        return 'lbn-badge' + Math.max(1, Math.min(4, Math.floor(v)));
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
                    console.error('Lead bulanan: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'site' },
            { data: 'mitra' },
            // Bulan tersimpan sebagai nama, jadi urutannya dipegang nomor bulan.
            {
                data: 'bulan',
                orderable: false,
                render: function (d, type, row) {
                    return type === 'display' ? escapeHtml(d) : row.bulan_nomor;
                }
            },
            {
                data: 'pembilang',
                className: 'text-end',
                orderable: false,
                render: function (d, type) { return type === 'display' ? fmtNum(d) : d; }
            },
            {
                data: 'penyebut',
                className: 'text-end',
                orderable: false,
                render: function (d, type) { return type === 'display' ? fmtNum(d) : d; }
            },
            {
                data: 'persen',
                className: 'text-end',
                render: function (d, type) { return type === 'display' ? fmtPct(d) : d; }
            },
            {
                data: 'nilai',
                className: 'text-center',
                orderable: false,
                render: function (d, type, row) {
                    if (type !== 'display') { return d; }
                    if (d === null) { return '<span class="text-secondary-light">–</span>'; }

                    return '<span class="' + badgeClass(d) + ' px-8 py-2 rounded-pill fw-medium text-xs"'
                        + ' title="' + escapeHtml(row.nilai_band || '') + '">'
                        + Number(d).toLocaleString('id-ID', {
                            minimumFractionDigits: 2, maximumFractionDigits: 2
                        })
                        + '</span>';
                }
            }
        ]
    });

    filterEls.forEach(function (node) {
        node.addEventListener('change', function () { table.ajax.reload(); });
    });

    var reset = root.querySelector('[data-lbnd="reset"]');

    if (reset) {
        reset.addEventListener('click', function () {
            filterEls.forEach(function (n) { n.value = ''; });
            table.search('').draw();
        });
    }

    root.querySelectorAll('[data-lbnd="export"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var params = new URLSearchParams(currentFilters());
            var search = table.search();

            if (search) { params.set('search', search); }

            params.set('format', btn.dataset.format);
            window.location.href = exportUrl + '?' + params.toString();
        });
    });

    var tab = document.querySelector('#lbn-tab-data');

    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        }, { once: true });
    }
})();
</script>
@endsection
