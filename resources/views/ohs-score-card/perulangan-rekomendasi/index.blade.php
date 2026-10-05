@extends('ohs-score-card.layouts.app')

@section('title', 'Perulangan rekomendasi hasil investigasi')

@section('css')
<style>

  /* ---- Matriks perulangan ----
     Skala warnanya terbalik dari halaman capaian: nol yang hijau, karena
     target parameter ini memang tidak ada perulangan sama sekali. */
  .rek-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .rek-matrix {
    width: 100%; min-width: 760px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .rek-matrix th, .rek-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .rek-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .rek-matrix thead th.rek-th-last { background: #2E90FA !important; color: #fff !important; }
  .rek-matrix .rek-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .rek-matrix .rek-mitra {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 190px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .rek-matrix thead .rek-site, .rek-matrix thead .rek-mitra { z-index: 4; background: #F8FAFC; }
  .rek-matrix .rek-total { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  /* Perulangan bertambah berarti memburuk, jadi panah atas merah. */
  .rek-matrix .rek-trend--up { color: #DC2626; font-weight: 800; }
  .rek-matrix .rek-trend--down { color: #16A34A; font-weight: 800; }
  .rek-matrix .rek-trend--flat { color: #94A3B8; font-weight: 800; }
  .rek-matrix .rek-cell {
    font-weight: 700; color: #fff; min-width: 54px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    font-variant-numeric: tabular-nums;
  }
  .rek-matrix .rek-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(220,38,38,.3);
    position: relative; z-index: 1;
  }
  /* Keterangan deviasi panjang-panjang; dipotong agar baris tabel tetap rapi. */
  .rek-keterangan {
    display: block; max-width: 380px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }

  /* Nol adalah keadaan yang diinginkan, jadi hijau — bukan sel kosong abu-abu. */
  /* Warna band resmi; ambangnya sama persis dengan SCORE_BANDS di
     controller. Nol adalah hasil terbaik, jadi hijau. */
  .rek-k4 { background: #92D050; color: #1F2937 !important; }
  .rek-k3 { background: #FFFF00; color: #1F2937 !important; }
  .rek-k2 { background: #FFC000; color: #1F2937 !important; }
  .rek-k1 { background: #FF0000; }

  /* Seluruh sel bisa dibuka, termasuk yang bernilai nol: nol di sini berarti
     "tidak ada rekomendasi yang terulang", sebuah hasil yang baik. */
  .rek-matrix .rek-cell--klik { cursor: pointer; }
  .rek-matrix .rek-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Tabel panjang di dalam modal digulir sendiri. */
  .rek-modal-scroll { max-height: 38vh; overflow: auto; }
  .rek-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }
  /* Teks tindakan dan keterangan panjang-panjang; dipotong agar baris rapi. */
  .rek-teks { display: block; max-width: 520px; }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Perulangan rekomendasi hasil investigasi</h6>
    <div class="text-secondary-light text-sm mt-4">
      Rekomendasi hasil investigasi yang ternyata terulang lagi — targetnya nol
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
    <li class="fw-medium text-primary-600">Perulangan rekomendasi hasil investigasi</li>
  </ul>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="rek-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="rek-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#rek-pane-ringkasan"
            type="button" role="tab" aria-controls="hp-pane-ringkasan" aria-selected="true">
      Ringkasan
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="rek-tab-data"
            data-bs-toggle="pill" data-bs-target="#rek-pane-data"
            type="button" role="tab" aria-controls="hp-pane-data" aria-selected="false">
      Data
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="rek-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.perulangan-rekomendasi.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="rek-pane-data" role="tabpanel">
    @include('ohs-score-card.perulangan-rekomendasi.partials._data')
  </div>
</div>

{{-- Modal rincian satu sel. Ditaruh di luar tab pane supaya tidak ikut
     tersembunyi saat berpindah tab. --}}
<div class="modal fade" id="rek-detail-modal" tabindex="-1" aria-labelledby="rek-detail-judul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content radius-12">
      <div class="modal-header border-bottom py-16 px-24">
        <div>
          <h6 class="modal-title text-lg fw-semibold mb-0" id="rek-detail-judul">Rincian Bulan</h6>
          <span class="text-sm text-secondary-light" data-rekm="subjudul"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24"><div data-rekm="isi"></div></div>
      <div class="modal-footer border-top py-12 px-24">
        <span class="text-sm text-secondary-light me-auto" data-rekm="kaki"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// ---- Modal rincian satu sel matriks -----------------------------------------
var rekModalDetail = (function () {
    'use strict';

    var el = document.getElementById('rek-detail-modal');
    if (!el) { return null; }

    var bagian = function (n) { return el.querySelector('[data-rekm="' + n + '"]'); };
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
            + ubin('Perulangan di sel ini', esc(koordinat.nilai || num(r.nilai_sel)),
                   'nilai sel dari matriks bulanan',
                   r.nilai_sel === 0 ? 'text-success-main' : '')
            + ubin('Butir Tindakan', num(r.butir),
                   num(r.butir_unik) + ' butir berbeda · ' + num(r.rekomendasi)
                   + ' rekomendasi · ' + num(r.rincian) + ' baris rincian')
            + '</div>';

        // Nol berarti tidak ada rekomendasi yang terulang -- justru keadaan
        // yang diinginkan parameter ini, bukan data yang belum masuk.
        if (!j.baris.length) {
            bagian('isi').innerHTML = isi
                + '<div class="alert bg-success-focus text-success-main border-success-main'
                + ' radius-8 px-20 py-16 mb-0 text-center">'
                + '<strong>Tidak ada rekomendasi yang terulang di bulan ini.</strong><br>'
                + '<span class="text-sm">Itu keadaan yang diinginkan parameter ini — '
                + 'bukan data yang belum masuk.</span>'
                + '</div>';
            bagian('kaki').textContent = '';
            return;
        }

        // Inti parameter ini: butir tindakan yang MUNCUL LAGI di sel lain.
        // 'lain' menghitung berapa sel lain meminta tindakan yang sama, jadi
        // itulah yang diurutkan paling atas -- bukan sekadar yang terbanyak.
        if (r.butir_berulang > 0) {
            isi += '<div class="alert bg-warning-focus text-warning-main border-warning-main'
                + ' radius-8 px-20 py-12 mb-20 text-sm">'
                + '<strong>' + num(r.butir_berulang) + ' butir tindakan</strong> di sel ini juga '
                + 'diminta di sel lain. Butir yang terus diminta ulang menandakan '
                + 'tindakan sebelumnya belum menutup masalahnya.'
                + '</div>';
        }

        isi += '<h6 class="text-md fw-semibold mb-4">Butir tindakan yang terulang</h6>'
            + '<span class="text-xs text-secondary-light d-block mb-12">'
            +   'Diurutkan dari yang paling sering muncul lagi di sel lain</span>'
            + daftarTindakan(j.per_tindakan);

        isi += '<h6 class="text-md fw-semibold mb-4 mt-20">Rekomendasi di sel ini</h6>'
            + '<span class="text-xs text-secondary-light d-block mb-12">'
            +   'Dikelompokkan menurut keterangannya</span>'
            + daftarRekomendasi(j.per_rekomendasi);

        isi += '<h6 class="text-md fw-semibold mb-12 mt-20">Daftar baris rincian</h6>'
            + '<div class="row gy-2 gx-2 align-items-end mb-12">'
            +   '<div class="col-sm-8"><input type="text" class="form-control form-control-sm radius-8"'
            +     ' placeholder="Cari activity, keterangan, tindakan…" data-rekm="cari"></div>'
            +   '<div class="col-sm-4 text-sm-end"><span class="text-sm text-secondary-light"'
            +     ' data-rekm="hitung"></span></div>'
            + '</div>'
            + '<div class="table-responsive rek-modal-scroll">'
            +   '<table class="table bordered-table sm-table mb-0" data-rekm="tabel"><thead><tr>'
            +     '<th>Activity</th><th>Keterangan</th><th>Tindakan perbaikan</th>'
            +     '<th class="text-end">Jumlah</th>'
            +   '</tr></thead><tbody>'
            +   j.baris.map(function (b) {
                    return '<tr data-cari="'
                        + esc((b.activity + ' ' + b.keterangan + ' ' + b.tindakan).toLowerCase()) + '">'
                        + '<td><span class="text-sm">' + esc(b.activity || '-') + '</span></td>'
                        + '<td><span class="text-sm text-secondary-light rek-teks">'
                        +   esc(b.keterangan || '-') + '</span></td>'
                        + '<td><span class="text-sm text-secondary-light rek-teks">'
                        +   esc(b.tindakan || '-') + '</span></td>'
                        + '<td class="text-end fw-semibold">' + num(b.jumlah) + '</td>'
                        + '</tr>';
                }).join('')
            +   '</tbody></table></div>';

        bagian('isi').innerHTML = isi;
        bagian('kaki').textContent = j.terpotong
            ? 'Menampilkan ' + num(j.batas) + ' baris pertama dari ' + num(r.rincian) + '.'
            : num(r.rincian) + ' baris rincian tercatat di sel ini.';

        pasangPencarian();
    }

    function daftarTindakan(baris) {
        if (!baris || !baris.length) {
            return '<div class="text-center text-secondary-light py-16">Tidak ada butir tindakan.</div>';
        }

        return '<div class="table-responsive rek-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>Butir tindakan</th><th class="text-end">Di sel ini</th>'
            +   '<th class="text-end">Sel lain</th><th>Muncul juga di</th>'
            + '</tr></thead><tbody>'
            + baris.map(function (b) {
                var sorot = b.lain > 0 ? ' class="bg-warning-focus"' : '';
                return '<tr' + sorot + '>'
                    + '<td><span class="text-sm rek-teks">' + esc(b.tindakan) + '</span></td>'
                    + '<td class="text-end fw-semibold">' + num(b.n) + '</td>'
                    + '<td class="text-end fw-semibold ' + (b.lain > 0 ? 'text-warning-main' : '')
                    +   '">' + num(b.lain) + '</td>'
                    + '<td><span class="text-xs text-secondary-light">'
                    +   (b.sel_lain && b.sel_lain.length
                        ? esc(b.sel_lain.join(' · '))
                          + (b.lain > b.sel_lain.length
                              ? ' · +' + num(b.lain - b.sel_lain.length) + ' lagi' : '')
                        : '—')
                    +   '</span></td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function daftarRekomendasi(baris) {
        if (!baris || !baris.length) {
            return '<div class="text-center text-secondary-light py-16">Tidak ada rekomendasi.</div>';
        }
        var maks = baris[0].baris || 1;
        return '<div class="table-responsive rek-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><tbody>'
            + baris.map(function (b) {
                return '<tr><td><span class="text-sm rek-teks">' + esc(b.keterangan) + '</span></td>'
                    + '<td class="text-end fw-semibold" style="width:72px">' + num(b.baris) + '</td>'
                    + '<td class="text-end text-xs text-secondary-light" style="width:88px">'
                    +   num(b.butir) + ' butir</td>'
                    + '<td style="width:26%"><div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                    + '<div class="progress-bar bg-info-main rounded-pill" role="progressbar"'
                    + ' style="width:' + (b.baris / maks * 100) + '%" aria-valuenow="' + b.baris + '"'
                    + ' aria-valuemin="0" aria-valuemax="' + maks + '"></div></div></td></tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    /** Pencarian dikerjakan di baris yang sudah ada, tanpa ke server lagi. */
    function pasangPencarian() {
        var cari = bagian('cari');
        var hitung = bagian('hitung');
        var semua = Array.prototype.slice.call(el.querySelectorAll('[data-rekm="tabel"] tbody tr'));

        function terapkan() {
            var teks = (cari.value || '').trim().toLowerCase();
            var tampil = 0;

            semua.forEach(function (tr) {
                var cocok = !teks || tr.dataset.cari.indexOf(teks) !== -1;
                tr.classList.toggle('d-none', !cocok);
                if (cocok) { tampil++; }
            });

            hitung.textContent = tampil === semua.length
                ? semua.length + ' baris'
                : tampil + ' dari ' + semua.length + ' baris';
        }

        cari.addEventListener('input', terapkan);
        terapkan();
    }

    function buka(url, koordinat) {
        // Nomor permintaan menjaga agar jawaban yang datang terlambat untuk sel
        // yang sudah tidak dibuka lagi tidak menimpa isi modal.
        var ini = ++permintaan;

        el.querySelector('#rek-detail-judul').textContent =
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

    var root = document.querySelector('.rek-overview');
    if (!root) { return; }

    var PALETTE = ['#0F172A', '#E0484A', '#FF9F29', '#F2C230', '#8252E9', '#487FFF', '#45B369'];

    var overviewUrl = root.dataset.url;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.rek-filter'));
    var charts = { monthly: null };
    var loaded = false;

    function el(name) {
        return root.querySelector('[data-rek="' + name + '"]');
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap ganti
    // filter, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    if (rekModalDetail && matrixEl && root.dataset.detailUrl) {
        var bukaSel = function (td) {
            rekModalDetail.buka(root.dataset.detailUrl, {
                site: td.dataset.site,
                mitra: td.dataset.mitra,
                month: td.dataset.month,
                bulan: td.dataset.bulan,
                nilai: td.dataset.nilai
            });
        };

        matrixEl.addEventListener('click', function (e) {
            var td = e.target.closest('.rek-cell--klik');
            if (td && matrixEl.contains(td)) { bukaSel(td); }
        });

        matrixEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var td = e.target.closest('.rek-cell--klik');
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

    // Nol hijau, dan makin banyak perulangan makin merah.
    var matrixMode = 'jumlah';
    var lastPayload = null;

    // BAND SELALU DITURUNKAN DARI CACAHNYA, tidak pernah dari angka Nilai.
    // Ambangnya sama persis dengan SCORE_BANDS di controller.
    function bandCacah(jumlah) {
        var v = Number(jumlah);
        if (!isFinite(v)) { return 0; }
        if (v <= 0) { return 4; }
        if (v <= 3) { return 3; }
        if (v <= 5) { return 2; }
        return 1;
    }

    function tierClass(jumlah) {
        return { 1: 'rek-k1', 2: 'rek-k2', 3: 'rek-k3', 4: 'rek-k4' }[bandCacah(jumlah)] || 'rek-k4';
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
                grad: 'bg-gradient-end-5', icon: 'solar:refresh-outline', dot: 'bg-danger-main',
                label: 'Total Perulangan', value: fmtNum(k.perulangan),
                foot: k.bulan_terburuk
                    ? 'Terbanyak di ' + escapeHtml(k.bulan_terburuk) + ' (' + fmtNum(k.perulangan_terburuk) + ')'
                    : 'Tidak ada perulangan sama sekali'
            },
            {
                grad: 'bg-gradient-end-2', icon: 'solar:calendar-outline', dot: 'bg-success-main',
                label: 'Bulan Tanpa Perulangan', value: fmtNum(k.bulan_bersih) + ' / ' + fmtNum(k.bulan_count),
                foot: 'Rata-rata ' + fmtNum(k.rata_per_bulan) + ' perulangan per bulan'
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

    // ---- Perulangan per site ----------------------------------------------------
    function renderPerSite(list) {
        var host = el('per-site');
        if (!list.length) {
            host.innerHTML = '<p class="text-secondary-light text-sm text-center py-24 mb-0">'
                + 'Tidak ada perulangan untuk filter ini.</p>';
            return;
        }

        var puncak = Math.max.apply(null, list.map(function (s) { return s.jumlah; })) || 1;

        host.innerHTML = list.map(function (s, i) {
            return '<div class="' + (i ? 'mt-20' : '') + '">'
                + '<div class="d-flex align-items-center justify-content-between mb-8">'
                +   '<span class="text-sm fw-semibold">' + escapeHtml(s.site) + '</span>'
                +   '<span class="text-sm fw-medium text-secondary-light">' + fmtNum(s.jumlah) + ' perulangan</span>'
                + '</div>'
                + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                +   '<div class="progress-bar bg-danger-main rounded-pill" role="progressbar"'
                +     ' style="width:' + (s.jumlah / puncak * 100) + '%" aria-valuenow="' + s.jumlah + '"'
                +     ' aria-valuemin="0" aria-valuemax="' + puncak + '"></div>'
                + '</div>'
                + '<span class="text-xs text-secondary-light">' + fmtPct(s.percent)
                +   ' dari seluruh perulangan · ' + fmtNum(s.lawan) + ' perusahaan</span>'
                + '</div>';
        }).join('');
    }

    // ---- Peringkat perusahaan ----------------------------------------------
    function renderPerMitra(list) {
        var body = el('per-mitra');
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-secondary-light py-24">'
                + 'Tidak ada perulangan untuk filter ini.</td></tr>';
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
        // Warna sel sama di kedua mode, jadi legendanya hanya berganti kata.
        var items = matrixMode === 'nilai'
            ? [
                { color: '#FF0000', label: 'Nilai 1 \· lebih dari 5' },
                { color: '#FFC000', label: 'Nilai 2 \· 4-5' },
                { color: '#FFFF00', label: 'Nilai 3 \· 1-3' },
                { color: '#92D050', label: 'Nilai 4 \· tidak ada' }
            ]
            : [
                { color: '#FF0000', label: 'lebih dari 5 perulangan' },
                { color: '#FFC000', label: '4-5 perulangan' },
                { color: '#FFFF00', label: '1-3 perulangan' },
                { color: '#92D050', label: 'tidak ada perulangan' }
            ];

        el('legend').innerHTML = items.map(function (it) {
            return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                + '<span class="rounded-1" style="width:14px;height:14px;background:' + it.color + ';"></span>'
                + escapeHtml(it.label) + '</span>';
        }).join('');
    }

    // Ganti mode hanya menggambar ulang dari payload terakhir: angka Nilai
    // sudah ikut dikirim, jadi tidak perlu meminta ulang ke server.
    root.querySelectorAll('.rek-switch__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.mode === matrixMode) { return; }

            matrixMode = btn.dataset.mode;

            root.querySelectorAll('.rek-switch__btn').forEach(function (b) {
                b.classList.toggle('active', b.dataset.mode === matrixMode);
            });

            renderLegend();

            if (lastPayload) {
                renderMatrix(lastPayload.months || [], lastPayload.matrix || []);
            }
        });
    });

    function renderMatrix(months, rows) {
        var table = el('matrix');
        var thead = table.querySelector('thead');
        var tbody = table.querySelector('tbody');

        var head = '<tr><th class="rek-site">SITE</th><th class="rek-mitra">PERUSAHAAN PIC</th>'
            + '<th>TOTAL</th><th>TREND</th>';
        months.forEach(function (m, i) {
            head += '<th class="' + (i === months.length - 1 ? 'rek-th-last' : '') + '">'
                + escapeHtml(m.label) + '</th>';
        });
        thead.innerHTML = head + '</tr>';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="' + (months.length + 4) + '" class="text-center py-24 text-secondary-light">'
                + 'Tidak ada perulangan untuk filter ini — dan itu kabar baik.</td></tr>';
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
                + (lewati[i] ? '' : '<td class="rek-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="rek-mitra">' + escapeHtml(row.mitra) + '</td>'
                + '<td class="rek-total" title="' + escapeHtml(row.bulan_kena + ' bulan kedapatan, '
                    + row.bulan_bersih + ' bulan bersih') + '">' + (matrixMode === 'nilai' ? row.nilai : fmtNum(row.total)) + '</td>';

            if (row.trend === 'up') {
                html += '<td class="rek-trend--up" title="Bertambah dari bulan sebelumnya">&uarr;</td>';
            } else if (row.trend === 'down') {
                html += '<td class="rek-trend--down" title="Berkurang dari bulan sebelumnya">&darr;</td>';
            } else if (row.trend === 'flat') {
                html += '<td class="rek-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
            } else {
                html += '<td class="text-secondary-light">–</td>';
            }

            // Variabel sengaja dinamai m: i di luar sudah dipakai sebagai index
            // baris untuk perhitungan rowspan site.
            row.cells.forEach(function (jumlah, m) {
                var tip = row.site + ' · ' + row.mitra + ' · ' + months[m].label + ': '
                    + (jumlah === 0 ? 'tidak ada perulangan' : fmtNum(jumlah) + ' perulangan');

                // Koordinat sel dibawa di atribut, bukan ditebak dari posisi
                // DOM: urutan baris berubah mengikuti pengurutan per site.
                html += '<td class="rek-cell rek-cell--klik ' + tierClass(jumlah) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-mitra="' + escapeHtml(row.mitra) + '"'
                    + ' data-month="' + months[m].number + '"'
                    + ' data-bulan="' + escapeHtml(months[m].label) + '"'
                    + ' data-nilai="' + escapeHtml(fmtNum(jumlah)) + '"'
                    + ' title="' + escapeHtml(tip + ' · klik untuk rincian') + '">'
                    + (matrixMode === 'nilai' ? bandCacah(jumlah) : fmtNum(jumlah)) + '</td>';
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
                title: { text: 'Perulangan', style: { fontSize: '11px' } },
                labels: { formatter: function (v) { return fmtNum(Math.round(v)); } }
            },
            legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            tooltip: {
                shared: true,
                // WAJIB eksplisit: kombinasi shared + intersect melempar error
                // sehingga grafiknya gagal dirender sama sekali.
                intersect: false,
                y: { formatter: function (v) { return fmtNum(v) + ' perulangan'; } }
            }
        });
        charts.monthly.render();
    }

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
                console.error('Perulangan Rekomendasi: panel "' + label + '" gagal dirender', err);
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
                lastPayload = json;
                safe('legend', renderLegend);
                safe('matrix', function () { renderMatrix(json.months || [], json.matrix || []); });
                safe('per-site', function () { renderPerSite(json.per_site || []); });
                safe('per-mitra', function () { renderPerMitra(json.per_mitra || []); });
                safe('monthly', function () { renderMonthlyChart(json.monthly); });
                safe('catatan', function () { renderCatatan(json.catatan); });

                var k = json.kpi;
                el('status').textContent = fmtNum(k.perulangan) + ' perulangan · ' + k.kombinasi
                    + ' pasangan site/perusahaan · ' + k.bulan_count + ' bulan, '
                    + k.bulan_bersih + ' di antaranya tanpa perulangan';
                loaded = true;
            })
            .catch(function (err) {
                el('status').textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Ringkasan Perulangan Rekomendasi: gagal memuat', err);
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

    var tab = document.querySelector('#rek-tab-ringkasan');
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

    var root = document.querySelector('.rek-datatable');
    if (!root) { return; }

    var tableEl = root.querySelector('[data-rek="table"]');
    if (!tableEl || typeof DataTable === 'undefined') { return; }
    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.rekd-filter'));
    var hintEl = root.querySelector('[data-rek="hint"]');

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
                    console.error('Perulangan Rekomendasi: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'site' },
            { data: 'mitra' },
            {
                data: 'keterangan',
                orderable: false,
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    // Teks penuh tetap ada di title; yang dipotong hanya
                    // tampilannya, supaya tinggi baris tidak meledak.
                    return '<span class="rek-keterangan" title="' + escapeHtml(d) + '">'
                        + escapeHtml(d || '-') + '</span>';
                }
            },
            {
                data: 'tindakan',
                orderable: false,
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    return '<span class="rek-keterangan" title="' + escapeHtml(d) + '">'
                        + escapeHtml(d || '-') + '</span>';
                }
            },
            // Bulan tersimpan sebagai kode M01-M12, jadi mengurutkannya di
            // tingkat SQL tetap benar secara kalender; yang dikirim ke tampilan
            // sudah berupa nama bulan Indonesia.
            { data: 'bulan', orderable: false },
            {
                data: 'jumlah',
                className: 'text-end',
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
            infoEmpty: 'Tidak ada perulangan',
            infoFiltered: '(disaring dari _MAX_ baris)',
            search: 'Cari:',
            zeroRecords: 'Tidak ada perulangan untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    filterEls.forEach(function (node) {
        node.addEventListener('change', function () { table.ajax.reload(); });
    });

    root.querySelector('[data-rek="reset"]').addEventListener('click', function () {
        filterEls.forEach(function (node) { node.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    root.querySelectorAll('[data-rek="export"]').forEach(function (btn) {
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
    var tab = document.querySelector('#rek-tab-data');
    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        });
    }
})();
</script>
@endsection
