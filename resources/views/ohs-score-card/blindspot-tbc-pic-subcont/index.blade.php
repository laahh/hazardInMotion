@extends('ohs-score-card.layouts.app')

@section('title', '% Blindspot TBC dengan PIC Subcontractor')

@section('css')
<style>

  /* ---- Matriks temuan ----
     Skala warnanya terbalik dari halaman capaian: nol yang hijau, karena
     target parameter ini memang tidak ada temuan sama sekali. */
  .bps-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .bps-matrix {
    width: 100%; min-width: 760px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .bps-matrix th, .bps-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .bps-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .bps-matrix thead th.bps-th-last { background: #2E90FA !important; color: #fff !important; }
  .bps-matrix .bps-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .bps-matrix .bps-mitra {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 190px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .bps-matrix thead .bps-site, .bps-matrix thead .bps-mitra { z-index: 4; background: #F8FAFC; }
  .bps-matrix .bps-total { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  /* Temuan bertambah berarti memburuk, jadi panah atas merah. */
  .bps-matrix .bps-trend--up { color: #DC2626; font-weight: 800; }
  .bps-matrix .bps-trend--down { color: #16A34A; font-weight: 800; }
  .bps-matrix .bps-trend--flat { color: #94A3B8; font-weight: 800; }
  .bps-matrix .bps-cell {
    font-weight: 700; color: #fff; min-width: 54px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    font-variant-numeric: tabular-nums;
  }
  .bps-matrix .bps-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(220,38,38,.3);
    position: relative; z-index: 1;
  }
  /* Keterangan deviasi panjang-panjang; dipotong agar baris tabel tetap rapi. */
  .bps-keterangan {
    display: block; max-width: 380px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }

  /* Nol adalah keadaan yang diinginkan, jadi hijau — bukan sel kosong abu-abu. */
  .bps-k0 { background: #16A34A; }
  .bps-k1 { background: #F2C230; color: #1F2937 !important; }
  .bps-k2 { background: #F08C2E; }
  .bps-k3 { background: #E0484A; }

  /* Seluruh sel bisa dibuka, termasuk yang bernilai nol: nol di sini berarti
     "tidak ada temuan", sebuah hasil yang baik, dan itu pun layak dijelaskan. */
  .bps-matrix .bps-cell--klik { cursor: pointer; }
  .bps-matrix .bps-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Daftar temuan di dalam modal digulir sendiri. */
  .bps-modal-scroll { max-height: 42vh; overflow: auto; }
  .bps-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">% Blindspot TBC dengan PIC Subcontractor</h6>
    <div class="text-secondary-light text-sm mt-4">
      Temuan TBC di area subkontraktor yang justru dilaporkan pihak lain — targetnya nol
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
    <li class="fw-medium text-primary-600">% Blindspot TBC dengan PIC Subcontractor</li>
  </ul>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="bps-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="bps-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#bps-pane-ringkasan"
            type="button" role="tab" aria-controls="hp-pane-ringkasan" aria-selected="true">
      Ringkasan
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="bps-tab-data"
            data-bs-toggle="pill" data-bs-target="#bps-pane-data"
            type="button" role="tab" aria-controls="hp-pane-data" aria-selected="false">
      Data
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="bps-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.blindspot-tbc-pic-subcont.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="bps-pane-data" role="tabpanel">
    @include('ohs-score-card.blindspot-tbc-pic-subcont.partials._data')
  </div>
</div>

{{-- Modal rincian satu sel. Ditaruh di luar tab pane supaya tidak ikut
     tersembunyi saat berpindah tab. --}}
<div class="modal fade" id="bps-detail-modal" tabindex="-1" aria-labelledby="bps-detail-judul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content radius-12">
      <div class="modal-header border-bottom py-16 px-24">
        <div>
          <h6 class="modal-title text-lg fw-semibold mb-0" id="bps-detail-judul">Rincian Bulan</h6>
          <span class="text-sm text-secondary-light" data-bpsm="subjudul"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24"><div data-bpsm="isi"></div></div>
      <div class="modal-footer border-top py-12 px-24">
        <span class="text-sm text-secondary-light me-auto" data-bpsm="kaki"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// ---- Modal rincian satu sel matriks -----------------------------------------
var bpsModalDetail = (function () {
    'use strict';

    var el = document.getElementById('bps-detail-modal');
    if (!el) { return null; }

    var bagian = function (n) { return el.querySelector('[data-bpsm="' + n + '"]'); };
    var permintaan = 0;

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function num(v) { return Number(v || 0).toLocaleString('id-ID'); }

    function ubin(label, nilai, catatan, kelas) {
        return '<div class="col-md-6">'
            + '<div class="border input-form-light radius-8 p-16 h-100">'
            +   '<span class="text-sm text-secondary-light d-block">' + esc(label) + '</span>'
            +   '<h6 class="fw-semibold mt-8 mb-4 ' + (kelas || '') + '">' + nilai + '</h6>'
            +   '<span class="text-xs text-secondary-light">' + esc(catatan) + '</span>'
            + '</div></div>';
    }

    function render(j, koordinat) {
        var r = j.ringkas;

        var isi = '<div class="row gy-3 mb-20">'
            + ubin('Temuan di sel ini', esc(koordinat.nilai || num(r.temuan)),
                   'dari matriks Temuan per Bulan',
                   r.temuan === 0 ? 'text-success-main' : '')
            + ubin('Total Temuan', num(r.temuan),
                   num(r.pic) + ' PIC kecolongan · ' + num(r.pelapor) + ' pelapor dari '
                   + num(r.perusahaan_pelapor) + ' perusahaan')
            + '</div>';

        // Nol di halaman ini BUKAN data yang hilang melainkan hasil yang
        // diinginkan: tidak ada temuan blindspot di sel itu. Sel nol pun bisa
        // diklik, dan jawabannya harus berbunyi begitu -- bukan modal kosong
        // yang membuat pembaca mengira datanya belum masuk.
        if (!j.baris.length) {
            bagian('isi').innerHTML = isi
                + '<div class="alert bg-success-focus text-success-main border-success-main'
                + ' radius-8 px-20 py-16 mb-0 text-center">'
                + '<strong>Tidak ada temuan blindspot di bulan ini.</strong><br>'
                + '<span class="text-sm">Itu keadaan yang diinginkan parameter ini — '
                + 'bukan data yang belum masuk.</span>'
                + '</div>';
            bagian('kaki').textContent = '';
            return;
        }

        isi += '<div class="row gy-4 mb-20">'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">Perusahaan yang menangkap</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Blindspot berarti temuan ini didapat pihak lain, bukan pengawas areanya sendiri</span>'
            +     daftarRingkas(j.per_pelapor, 'perusahaan')
            +   '</div>'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">PIC</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Pengawas subcontractor yang areanya paling banyak luput di bulan ini</span>'
            +     daftarPic(j.per_pic)
            +   '</div>'
            + '</div>';

        isi += '<h6 class="text-md fw-semibold mb-12">Daftar temuan</h6>'
            + '<div class="row gy-2 gx-2 align-items-end mb-12">'
            +   '<div class="col-sm-8"><input type="text" class="form-control form-control-sm radius-8"'
            +     ' placeholder="Cari task, PIC, pelapor, deskripsi…" data-bpsm="cari"></div>'
            +   '<div class="col-sm-4 text-sm-end"><span class="text-sm text-secondary-light"'
            +     ' data-bpsm="hitung"></span></div>'
            + '</div>'
            + '<div class="table-responsive bps-modal-scroll">'
            +   '<table class="table bordered-table sm-table mb-0" data-bpsm="tabel"><thead><tr>'
            +     '<th>Task</th><th>PIC area</th><th>Ditemukan oleh</th><th>Deskripsi</th>'
            +   '</tr></thead><tbody>'
            +   j.baris.map(function (b) {
                    return '<tr data-cari="'
                        + esc((b.task + ' ' + b.pic + ' ' + b.sid_pic + ' ' + b.pelapor + ' '
                               + b.pelapor_perusahaan + ' ' + b.deskripsi).toLowerCase()) + '">'
                        + '<td class="text-xs">' + esc(b.task || '-') + '</td>'
                        + '<td><span class="text-sm d-block">' + esc(b.pic || '-') + '</span>'
                        +   '<span class="text-xs text-secondary-light">' + esc(b.sid_pic || '-') + '</span></td>'
                        + '<td><span class="text-sm d-block">' + esc(b.pelapor || '-') + '</span>'
                        +   '<span class="text-xs text-secondary-light">'
                        +   esc(b.pelapor_perusahaan || '-') + '</span></td>'
                        + '<td><span class="text-sm text-secondary-light">'
                        +   esc(b.deskripsi || '-') + '</span></td>'
                        + '</tr>';
                }).join('')
            +   '</tbody></table></div>';

        bagian('isi').innerHTML = isi;
        bagian('kaki').textContent = j.terpotong
            ? 'Menampilkan ' + num(j.batas) + ' temuan pertama dari ' + num(r.temuan) + '.'
            : num(r.temuan) + ' temuan tercatat di sel ini.';

        pasangPencarian();
    }

    function daftarRingkas(baris, kunci) {
        if (!baris || !baris.length) {
            return '<div class="text-center text-secondary-light py-16">Tidak ada data.</div>';
        }
        var maks = baris[0].n || 1;
        return '<div class="table-responsive"><table class="table bordered-table sm-table mb-0"><tbody>'
            + baris.map(function (b) {
                return '<tr><td class="text-sm">' + esc(b[kunci]) + '</td>'
                    + '<td class="text-end fw-semibold" style="width:64px">' + num(b.n) + '</td>'
                    + '<td style="width:34%"><div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                    + '<div class="progress-bar bg-warning-main rounded-pill" role="progressbar"'
                    + ' style="width:' + (b.n / maks * 100) + '%" aria-valuenow="' + b.n + '"'
                    + ' aria-valuemin="0" aria-valuemax="' + maks + '"></div></div></td></tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function daftarPic(baris) {
        if (!baris || !baris.length) {
            return '<div class="text-center text-secondary-light py-16">Tidak ada data.</div>';
        }
        var maks = baris[0].n || 1;
        return '<div class="table-responsive"><table class="table bordered-table sm-table mb-0"><tbody>'
            + baris.map(function (b) {
                return '<tr><td><span class="text-sm d-block">' + esc(b.pic) + '</span>'
                    + '<span class="text-xs text-secondary-light">' + esc(b.sid) + '</span></td>'
                    + '<td class="text-end fw-semibold" style="width:64px">' + num(b.n) + '</td>'
                    + '<td style="width:34%"><div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                    + '<div class="progress-bar bg-danger-main rounded-pill" role="progressbar"'
                    + ' style="width:' + (b.n / maks * 100) + '%" aria-valuenow="' + b.n + '"'
                    + ' aria-valuemin="0" aria-valuemax="' + maks + '"></div></div></td></tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    /** Pencarian dikerjakan di baris yang sudah ada, tanpa ke server lagi. */
    function pasangPencarian() {
        var cari = bagian('cari');
        var hitung = bagian('hitung');
        var semua = Array.prototype.slice.call(el.querySelectorAll('[data-bpsm="tabel"] tbody tr'));

        function terapkan() {
            var teks = (cari.value || '').trim().toLowerCase();
            var tampil = 0;

            semua.forEach(function (tr) {
                var cocok = !teks || tr.dataset.cari.indexOf(teks) !== -1;
                tr.classList.toggle('d-none', !cocok);
                if (cocok) { tampil++; }
            });

            hitung.textContent = tampil === semua.length
                ? semua.length + ' temuan'
                : tampil + ' dari ' + semua.length + ' temuan';
        }

        cari.addEventListener('input', terapkan);
        terapkan();
    }

    function buka(url, koordinat) {
        // Nomor permintaan menjaga agar jawaban yang datang terlambat untuk sel
        // yang sudah tidak dibuka lagi tidak menimpa isi modal.
        var ini = ++permintaan;

        el.querySelector('#bps-detail-judul').textContent =
            'Rincian ' + koordinat.bulan + ' · ' + koordinat.site;
        bagian('subjudul').textContent = koordinat.mitra || 'Seluruh perusahaan di site ini';
        bagian('kaki').textContent = '';
        bagian('isi').innerHTML = '<div class="text-center text-secondary-light py-40">'
            + '<div class="spinner-border spinner-border-sm text-primary-600 me-2" role="status"></div>'
            + 'Memuat rincian…</div>';

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }

        var q = new URLSearchParams({
            site: koordinat.site, mitra: koordinat.mitra || '', month: koordinat.month
        });

        fetch(url + '?' + q.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.json();
            })
            .then(function (j) {
                if (ini !== permintaan) { return; }
                if (!j.ok) {
                    bagian('isi').innerHTML = '<div class="alert alert-danger bg-danger-focus'
                        + ' border-danger-main text-danger-main radius-8 px-20 py-12 mb-0">'
                        + esc(j.pesan || 'Rincian tidak bisa dimuat.') + '</div>';
                    return;
                }
                render(j, koordinat);
            })
            .catch(function (err) {
                if (ini !== permintaan) { return; }
                bagian('isi').innerHTML = '<div class="alert alert-danger bg-danger-focus'
                    + ' border-danger-main text-danger-main radius-8 px-20 py-12 mb-0">'
                    + 'Permintaan ke server gagal. ' + esc(err && err.message) + '</div>';
            });
    }

    return { buka: buka };
})();
</script>
<script>
// ---- Tab Ringkasan ----------------------------------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.bps-overview');
    if (!root) { return; }

    var PALETTE = ['#0F172A', '#E0484A', '#FF9F29', '#F2C230', '#8252E9', '#487FFF', '#45B369'];

    var overviewUrl = root.dataset.url;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.bps-filter'));
    var charts = { monthly: null, 'chart-pic': null, 'chart-pelapor': null };
    var loaded = false;

    function el(name) {
        return root.querySelector('[data-bps="' + name + '"]');
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap ganti
    // filter, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    if (bpsModalDetail && matrixEl && root.dataset.detailUrl) {
        var bukaSel = function (td) {
            bpsModalDetail.buka(root.dataset.detailUrl, {
                site: td.dataset.site,
                mitra: td.dataset.mitra,
                month: td.dataset.month,
                bulan: td.dataset.bulan,
                nilai: td.dataset.nilai
            });
        };

        matrixEl.addEventListener('click', function (e) {
            var td = e.target.closest('.bps-cell--klik');
            if (td && matrixEl.contains(td)) { bukaSel(td); }
        });

        matrixEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var td = e.target.closest('.bps-cell--klik');
            if (!td || !matrixEl.contains(td)) { return; }
            e.preventDefault();
            bukaSel(td);
        });
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

    // Nol hijau, dan makin banyak temuan makin merah.
    function tierClass(jumlah) {
        if (jumlah <= 0) return 'bps-k0';
        if (jumlah === 1) return 'bps-k1';
        if (jumlah === 2) return 'bps-k2';
        return 'bps-k3';
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
                grad: 'bg-gradient-end-5', icon: 'solar:eye-closed-outline', dot: 'bg-danger-main',
                label: 'Total Temuan', value: fmtNum(k.temuan),
                foot: k.bulan_terburuk
                    ? 'Terbanyak di ' + escapeHtml(k.bulan_terburuk) + ' (' + fmtNum(k.temuan_terburuk) + ')'
                    : 'Tidak ada temuan sama sekali'
            },
            {
                grad: 'bg-gradient-end-2', icon: 'solar:calendar-outline', dot: 'bg-success-main',
                label: 'Bulan Tanpa Temuan', value: fmtNum(k.bulan_bersih) + ' / ' + fmtNum(k.bulan_count),
                foot: 'Rata-rata ' + fmtNum(k.rata_per_bulan) + ' temuan per bulan'
            },
            {
                grad: 'bg-gradient-end-3', icon: 'solar:buildings-outline', dot: 'bg-warning-main',
                label: 'Perusahaan Terdampak', value: fmtNum(k.mitra_count),
                foot: fmtNum(k.kombinasi) + ' pasangan site &amp; perusahaan'
            },
            {
                grad: 'bg-gradient-end-1', icon: 'solar:map-point-outline', dot: 'bg-primary-600',
                label: 'Site Terdampak', value: fmtNum(k.site_count),
                foot: 'Dalam ' + fmtNum(k.bulan_count) + ' bulan yang tercakup'
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

    // ---- Temuan per site ----------------------------------------------------
    function renderPerSite(list) {
        var host = el('per-site');
        if (!list.length) {
            host.innerHTML = '<p class="text-secondary-light text-sm text-center py-24 mb-0">'
                + 'Tidak ada temuan untuk filter ini.</p>';
            return;
        }

        var puncak = Math.max.apply(null, list.map(function (s) { return s.jumlah; })) || 1;

        host.innerHTML = list.map(function (s, i) {
            return '<div class="' + (i ? 'mt-20' : '') + '">'
                + '<div class="d-flex align-items-center justify-content-between mb-8">'
                +   '<span class="text-sm fw-semibold">' + escapeHtml(s.site) + '</span>'
                +   '<span class="text-sm fw-medium text-secondary-light">' + fmtNum(s.jumlah) + ' temuan</span>'
                + '</div>'
                + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                +   '<div class="progress-bar bg-danger-main rounded-pill" role="progressbar"'
                +     ' style="width:' + (s.jumlah / puncak * 100) + '%" aria-valuenow="' + s.jumlah + '"'
                +     ' aria-valuemin="0" aria-valuemax="' + puncak + '"></div>'
                + '</div>'
                + '<span class="text-xs text-secondary-light">' + fmtPct(s.percent)
                +   ' dari seluruh temuan · ' + fmtNum(s.lawan) + ' perusahaan</span>'
                + '</div>';
        }).join('');
    }

    // ---- Peringkat perusahaan ----------------------------------------------
    function renderPerMitra(list) {
        var body = el('per-mitra');
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-secondary-light py-24">'
                + 'Tidak ada temuan untuk filter ini.</td></tr>';
            return;
        }
        body.innerHTML = list.map(function (m) {
            return '<tr>'
                + '<td><span class="text-md fw-medium">' + escapeHtml(m.mitra) + '</span>'
                +   '<span class="d-block text-xs text-secondary-light">' + fmtNum(m.lawan) + ' site</span></td>'
                + '<td class="text-end fw-semibold">' + fmtNum(m.jumlah) + '</td>'
                + '<td class="text-end text-secondary-light">' + fmtPct(m.percent) + '</td>'
                + '</tr>';
        }).join('');
    }

    function renderLegend() {
        var items = [
            { color: '#16A34A', label: 'tidak ada temuan' },
            { color: '#F2C230', label: '1 temuan' },
            { color: '#F08C2E', label: '2 temuan' },
            { color: '#E0484A', label: '3 atau lebih' }
        ];

        el('legend').innerHTML = items.map(function (it) {
            return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                + '<span class="rounded-1" style="width:14px;height:14px;background:' + it.color + ';"></span>'
                + escapeHtml(it.label) + '</span>';
        }).join('');
    }

    function renderMatrix(months, rows) {
        var table = el('matrix');
        var thead = table.querySelector('thead');
        var tbody = table.querySelector('tbody');

        var head = '<tr><th class="bps-site">SITE</th><th class="bps-mitra">PERUSAHAAN PIC</th>'
            + '<th>TOTAL</th><th>TREND</th>';
        months.forEach(function (m, i) {
            head += '<th class="' + (i === months.length - 1 ? 'bps-th-last' : '') + '">'
                + escapeHtml(m.label) + '</th>';
        });
        thead.innerHTML = head + '</tr>';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="' + (months.length + 4) + '" class="text-center py-24 text-secondary-light">'
                + 'Tidak ada temuan untuk filter ini — dan itu kabar baik.</td></tr>';
            return;
        }

        // Sel site digabung dengan rowspan: baris sudah dikelompokkan per site
        // dari server, jadi cukup menghitung panjang blok yang berurutan.
        var span = {};
        var lewati = {};
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
                + (lewati[i] ? '' : '<td class="bps-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="bps-mitra">' + escapeHtml(row.mitra) + '</td>'
                + '<td class="bps-total" title="' + escapeHtml(row.bulan_kena + ' bulan kedapatan, '
                    + row.bulan_bersih + ' bulan bersih') + '">' + fmtNum(row.total) + '</td>';

            if (row.trend === 'up') {
                html += '<td class="bps-trend--up" title="Bertambah dari bulan sebelumnya">&uarr;</td>';
            } else if (row.trend === 'down') {
                html += '<td class="bps-trend--down" title="Berkurang dari bulan sebelumnya">&darr;</td>';
            } else if (row.trend === 'flat') {
                html += '<td class="bps-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
            } else {
                html += '<td class="text-secondary-light">–</td>';
            }

            // Variabel sengaja dinamai m: i di luar sudah dipakai sebagai index
            // baris untuk perhitungan rowspan site.
            row.cells.forEach(function (jumlah, m) {
                var tip = row.site + ' · ' + row.mitra + ' · ' + months[m].label + ': '
                    + (jumlah === 0 ? 'tidak ada temuan' : fmtNum(jumlah) + ' temuan');

                // Koordinat sel dibawa di atribut, bukan ditebak dari posisi
                // DOM: urutan baris berubah mengikuti pengurutan per site.
                html += '<td class="bps-cell bps-cell--klik ' + tierClass(jumlah) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-mitra="' + escapeHtml(row.mitra) + '"'
                    + ' data-month="' + months[m].number + '"'
                    + ' data-bulan="' + escapeHtml(months[m].label) + '"'
                    + ' data-nilai="' + escapeHtml(fmtNum(jumlah)) + '"'
                    + ' title="' + escapeHtml(tip + ' · klik untuk rincian') + '">'
                    + fmtNum(jumlah) + '</td>';
            });

            return html + '</tr>';
        }).join('');
    }

    // ---- Grafik -------------------------------------------------------------
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
            // Garis "Semua site" dibuat lebih tebal karena itu angka utamanya.
            stroke: { curve: 'smooth', width: payload.series.map(function (s, i) { return i === 0 ? 4 : 2; }) },
            markers: { size: 4, hover: { size: 5 } },
            dataLabels: { enabled: false },
            xaxis: { categories: payload.labels, labels: { style: { fontSize: '11px' } } },
            yaxis: {
                min: 0,
                title: { text: 'Temuan', style: { fontSize: '11px' } },
                labels: { formatter: function (v) { return fmtNum(Math.round(v)); } }
            },
            legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            tooltip: {
                shared: true,
                // WAJIB eksplisit: kombinasi shared + intersect melempar error
                // sehingga grafiknya gagal dirender sama sekali.
                intersect: false,
                y: { formatter: function (v) { return fmtNum(v) + ' temuan'; } }
            }
        });
        charts.monthly.render();
    }

    function renderCatatan(text) {
        el('note').textContent = text || '';
        el('note-wrap').classList.toggle('d-none', !text);
    }

    /** Batang mendatar: nama PIC panjang-panjang, jadi lebih terbaca begini. */
    function renderPicChart(rows) {
        var node = el('chart-pic');
        if (!node || typeof ApexCharts === 'undefined') { return; }

        if (charts['chart-pic']) {
            charts['chart-pic'].destroy();
            charts['chart-pic'] = null;
        }

        if (!rows.length) {
            node.innerHTML = '<p class="text-secondary-light text-sm text-center py-40 mb-0">'
                + 'Tidak ada data.</p>';
            return;
        }
        node.innerHTML = '';

        charts['chart-pic'] = new ApexCharts(node, {
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
        charts['chart-pic'].render();
    }

    /** Donat asal pelapor: tidak satu pun dari perusahaan PIC-nya sendiri. */
    function renderPelaporChart(rows) {
        var node = el('chart-pelapor');
        if (!node || typeof ApexCharts === 'undefined') { return; }

        if (charts['chart-pelapor']) {
            charts['chart-pelapor'].destroy();
            charts['chart-pelapor'] = null;
        }

        var values = rows.map(function (r) { return r.jumlah; });
        var total = values.reduce(function (a, b) { return a + b; }, 0);

        if (!values.length || total === 0) {
            node.innerHTML = '<p class="text-secondary-light text-sm text-center py-40 mb-0">'
                + 'Tidak ada data.</p>';
            return;
        }
        node.innerHTML = '';

        charts['chart-pelapor'] = new ApexCharts(node, {
            series: values,
            labels: rows.map(function (r) { return r.label; }),
            colors: ['#487FFF', '#45B369', '#FF9F29', '#8252E9', '#00B8F2', '#E0484A'],
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
        charts['chart-pelapor'].render();
    }

    /** Membungkus renderer agar kegagalan satu panel tidak menjatuhkan sisanya. */
    function safe(label, fn) {
        try {
            fn();
        } catch (err) {
            if (typeof console !== 'undefined' && console.error) {
                console.error('Blindspot PIC Subcon: panel "' + label + '" gagal dirender', err);
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
                safe('kpi', function () { renderKpi(json.kpi); });
                safe('legend', renderLegend);
                safe('matrix', function () { renderMatrix(json.months || [], json.matrix || []); });
                safe('per-site', function () { renderPerSite(json.per_site || []); });
                safe('per-mitra', function () { renderPerMitra(json.per_mitra || []); });
                safe('monthly', function () { renderMonthlyChart(json.monthly); });
                safe('pic', function () { renderPicChart(json.per_pic || []); });
                safe('pelapor', function () { renderPelaporChart(json.per_pelapor || []); });
                safe('catatan', function () { renderCatatan(json.catatan); });

                var k = json.kpi;
                el('status').textContent = fmtNum(k.temuan) + ' temuan · ' + k.kombinasi
                    + ' pasangan site/perusahaan · ' + k.bulan_count + ' bulan, '
                    + k.bulan_bersih + ' di antaranya tanpa temuan';
                loaded = true;
            })
            .catch(function (err) {
                el('status').textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Ringkasan Blindspot PIC Subcon: gagal memuat', err);
                }
            });
    }

    filterEls.forEach(function (node) {
        node.addEventListener('change', load);
    });

    el('reset').addEventListener('click', function () {
        filterEls.forEach(function (node) { node.value = ''; });
        load();
    });

    load();

    var tab = document.querySelector('#bps-tab-ringkasan');
    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            if (!loaded) { load(); return; }
            // ApexCharts tidak bisa mengukur elemen yang sedang tersembunyi,
            // jadi ukurannya dihitung ulang saat tab kembali tampil.
            if (charts.monthly && typeof charts.monthly.windowResizeHandler === 'function') {
                charts.monthly.windowResizeHandler();
            }
        });
    }
})();
</script>
<script>
// ---- Tab Data ---------------------------------------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.bps-datatable');
    if (!root) { return; }

    var tableEl = root.querySelector('[data-bps="table"]');
    if (!tableEl || typeof DataTable === 'undefined') { return; }
    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.bpsd-filter'));
    var hintEl = root.querySelector('[data-bps="hint"]');

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
                hintEl.textContent = fmtNum(json.recordsFiltered) + ' baris';
                return json.data || [];
            },
            error: function (xhr, error) {
                hintEl.textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Blindspot PIC Subcon: gagal memuat data', error, xhr && xhr.status);
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
                    return '<span class="bps-keterangan" title="' + escapeHtml(d) + '">'
                        + escapeHtml(d || '-') + '</span>';
                }
            },
            // Bulan tersimpan sebagai kode M01-M12, jadi mengurutkannya di
            // tingkat SQL tetap benar secara kalender; yang dikirim ke tampilan
            // sudah berupa nama bulan Indonesia.
            { data: 'bulan', orderable: false },
            { data: 'task', className: 'text-end' },
            {
                data: 'jumlah',
                className: 'text-end d-none',
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    var kelas = d > 1 ? 'bg-danger-focus text-danger-main' : 'bg-warning-focus text-warning-main';
                    return '<span class="' + kelas + ' px-8 py-2 rounded-pill fw-medium text-xs">'
                        + escapeHtml(fmtNum(d)) + '</span>';
                }
            }
        ],
        language: {
            processing: 'Memuat…',
            lengthMenu: 'Tampilkan _MENU_ baris',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
            infoEmpty: 'Tidak ada temuan',
            infoFiltered: '(disaring dari _MAX_ baris)',
            search: 'Cari:',
            zeroRecords: 'Tidak ada temuan untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    filterEls.forEach(function (node) {
        node.addEventListener('change', function () { table.ajax.reload(); });
    });

    root.querySelector('[data-bps="reset"]').addEventListener('click', function () {
        filterEls.forEach(function (node) { node.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    root.querySelectorAll('[data-bps="export"]').forEach(function (btn) {
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
    var tab = document.querySelector('#bps-tab-data');
    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        });
    }
})();
</script>
@endsection
