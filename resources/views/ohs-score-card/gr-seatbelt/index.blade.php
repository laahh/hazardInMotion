@extends('ohs-score-card.layouts.app')

@section('title', 'GR Seatbelt')

@section('css')
<style>

  /* ---- Matriks temuan ----
     Skala warnanya terbalik dari halaman capaian: nol yang hijau, karena
     target parameter ini memang tidak ada pelanggaran sama sekali. */
  .hp-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .hp-matrix {
    width: 100%; min-width: 760px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .hp-matrix th, .hp-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .hp-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .hp-matrix thead th.hp-th-last { background: #2E90FA !important; color: #fff !important; }
  .hp-matrix .hp-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .hp-matrix .hp-mitra {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 190px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .hp-matrix thead .hp-site, .hp-matrix thead .hp-mitra { z-index: 4; background: #F8FAFC; }
  .hp-matrix .hp-total { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  /* Pelanggaran bertambah berarti memburuk, jadi panah atas merah. */
  .hp-matrix .hp-trend--up { color: #DC2626; font-weight: 800; }
  .hp-matrix .hp-trend--down { color: #16A34A; font-weight: 800; }
  .hp-matrix .hp-trend--flat { color: #94A3B8; font-weight: 800; }
  .hp-matrix .hp-cell {
    font-weight: 700; color: #fff; min-width: 54px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    font-variant-numeric: tabular-nums;
  }
  .hp-matrix .hp-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(220,38,38,.3);
    position: relative; z-index: 1;
  }
  /* Semua sel bisa dibuka rinciannya, termasuk yang nol: nol di sini adalah
     jawaban "tidak ada pelanggaran", bukan sel tanpa data. */
  .hp-matrix .hp-cell--klik { cursor: pointer; }
  .hp-matrix .hp-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Tabel konteks di dalam modal digulir sendiri agar modalnya tidak memanjang. */
  .grs-modal-scroll { max-height: 34vh; overflow: auto; }
  .grs-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }

  /* Nol adalah keadaan yang diinginkan, jadi hijau — bukan sel kosong abu-abu. */
  /* Warna band resmi; ambangnya sama persis dengan SCORE_BANDS di
     controller. Nol adalah hasil terbaik, jadi hijau. */
  .hp-k4 { background: #92D050; color: #1F2937 !important; }
  .hp-k3 { background: #FFFF00; color: #1F2937 !important; }
  .hp-k2 { background: #FFC000; color: #1F2937 !important; }
  .hp-k1 { background: #FF0000; }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">GR Seatbelt</h6>
    <div class="text-secondary-light text-sm mt-4">
      Pelanggaran Golden Rules seatbelt yang tercatat per perusahaan di tiap site — targetnya nol
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
    <li class="fw-medium text-primary-600">GR Seatbelt</li>
  </ul>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="hp-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="hp-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#hp-pane-ringkasan"
            type="button" role="tab" aria-controls="hp-pane-ringkasan" aria-selected="true">
      Ringkasan
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="hp-tab-data"
            data-bs-toggle="pill" data-bs-target="#hp-pane-data"
            type="button" role="tab" aria-controls="hp-pane-data" aria-selected="false">
      Data
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="hp-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.gr-seatbelt.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="hp-pane-data" role="tabpanel">
    @include('ohs-score-card.gr-seatbelt.partials._data')
  </div>
</div>

{{-- Modal rincian satu sel. Ditaruh di luar tab pane supaya tidak ikut
     tersembunyi saat berpindah tab. --}}
<div class="modal fade" id="grs-detail-modal" tabindex="-1" aria-labelledby="grs-detail-judul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content radius-12">
      <div class="modal-header border-bottom py-16 px-24">
        <div>
          <h6 class="modal-title text-lg fw-semibold mb-0" id="grs-detail-judul">Rincian Bulan</h6>
          <span class="text-sm text-secondary-light" data-grsm="subjudul"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24"><div data-grsm="isi"></div></div>
      <div class="modal-footer border-top py-12 px-24">
        <span class="text-sm text-secondary-light me-auto" data-grsm="kaki"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// ---- Modal rincian satu sel matriks -----------------------------------------
//
// Yang diukur parameter ini cacah pelanggaran dengan target nol, bukan
// persentase, jadi modalnya tidak memuat capaian, Nilai 1-4, maupun selisih ke
// target persen — tidak satu pun dari itu ada penyebutnya di sumber.
//
// SEL BERNILAI 0 DIJAWAB PENUH, tidak dikosongkan. Nol di halaman ini berarti
// "tidak ada pelanggaran tercatat", sebuah hasil yang bagus, bukan "data belum
// masuk" seperti di halaman capaian.
var grsModalDetail = (function () {
    'use strict';

    var el = document.getElementById('grs-detail-modal');
    if (!el) { return null; }

    var bagian = function (n) { return el.querySelector('[data-grsm="' + n + '"]'); };
    var permintaan = 0;

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function num(v) { return Number(v || 0).toLocaleString('id-ID'); }

    function kataBulan(n, satuan) {
        return num(n) + ' ' + satuan;
    }

    function ubin(label, nilai, catatan, kelas) {
        return '<div class="col-xxl-3 col-md-6">'
            + '<div class="border input-form-light radius-8 p-16 h-100">'
            +   '<span class="text-sm text-secondary-light d-block">' + esc(label) + '</span>'
            +   '<h6 class="fw-semibold mt-8 mb-4 ' + (kelas || '') + '">' + nilai + '</h6>'
            +   '<span class="text-xs text-secondary-light">' + esc(catatan) + '</span>'
            + '</div></div>';
    }

    /** Batang mini supaya perbandingan antar baris terbaca tanpa membaca angka. */
    function batang(jumlah, puncak) {
        var lebar = puncak > 0 ? Math.round(jumlah / puncak * 100) : 0;
        return '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
            + '<div class="progress-bar ' + (jumlah > 0 ? 'bg-danger-main' : 'bg-success-main')
            + ' rounded-pill" role="progressbar" style="width:' + (jumlah > 0 ? lebar : 0) + '%"'
            + ' aria-valuenow="' + jumlah + '" aria-valuemin="0" aria-valuemax="'
            + (puncak || 1) + '"></div></div>';
    }

    /** Angka sel apa adanya, dengan nol yang sengaja dibaca sebagai "bersih". */
    function angka(jumlah) {
        return jumlah > 0
            ? '<span class="fw-semibold text-danger-main">' + num(jumlah) + '</span>'
            : '<span class="text-success-main fw-semibold">0</span>';
    }

    /** Spanduk pembuka: nol dan bukan nol menceritakan hal yang berbeda. */
    function spanduk(j) {
        var r = j.rekam;

        if (j.sel.bersih) {
            var lanjut = r.bersih_beruntun > 1
                ? ' Pasangan ini sudah ' + kataBulan(r.bersih_beruntun, 'bulan berturut-turut')
                  + ' tanpa pelanggaran sampai ' + j.judul.bulan + '.'
                : '';

            return '<div class="alert bg-success-focus border-success-main text-success-main'
                + ' radius-8 px-20 py-12 mb-20 d-flex align-items-start gap-2">'
                + '<iconify-icon icon="solar:check-circle-outline" class="icon text-xl flex-shrink-0"></iconify-icon>'
                + '<span class="text-sm">Tidak ada pelanggaran seatbelt tercatat di ' + esc(j.judul.bulan)
                +   ' untuk ' + esc(j.judul.mitra) + ' di ' + esc(j.judul.site) + '.'
                +   ' Nol adalah target parameter ini, jadi sel ini kosong karena bersih —'
                +   ' bukan karena datanya belum masuk.' + esc(lanjut) + '</span></div>';
        }

        return '<div class="alert bg-danger-focus border-danger-main text-danger-main'
            + ' radius-8 px-20 py-12 mb-20 d-flex align-items-start gap-2">'
            + '<iconify-icon icon="mdi:seatbelt" class="icon text-xl flex-shrink-0"></iconify-icon>'
            + '<span class="text-sm">' + esc(kataBulan(j.sel.jumlah, 'pelanggaran seatbelt'))
            +   ' tercatat di ' + esc(j.judul.bulan) + ' untuk ' + esc(j.judul.mitra)
            +   ' di ' + esc(j.judul.site) + '. Targetnya nol, jadi angka berapa pun di atas nol'
            +   ' adalah selisih ke target.</span></div>';
    }

    function render(j) {
        var r = j.rekam;
        var s = j.site_bulan;
        var p = j.peringkat;

        // Keempat kartu menjawab empat pertanyaan yang berbeda: berapa di sel
        // ini, berapa se-site pada bulan yang sama, seburuk apa posisinya di
        // antara perusahaan lain, dan seperti apa rekam jejaknya. Tidak ada
        // kartu capaian/Nilai/selisih-persen: cacah tidak punya penyebut.
        var isi = spanduk(j)
            + '<div class="row gy-3 mb-20">'
            + ubin('Pelanggaran Bulan Ini', num(j.sel.jumlah),
                   j.sel.bersih ? 'sesuai target — targetnya nol' : 'target parameter ini nol',
                   j.sel.bersih ? 'text-success-main' : 'text-danger-main')
            + ubin('Se-Site ' + j.judul.bulan, num(s.jumlah),
                   s.kedapatan + ' dari ' + s.pernah + ' perusahaan di ' + j.judul.site
                   + ' kedapatan bulan ini',
                   s.jumlah ? '' : 'text-success-main')
            + ubin('Posisi di Site',
                   p.posisi === null ? 'Bersih' : 'ke-' + num(p.posisi),
                   p.posisi === null
                       ? 'tidak ikut menyumbang pelanggaran bulan ini'
                       : 'dari ' + p.dari + ' perusahaan yang kedapatan, terbanyak lebih dulu',
                   p.posisi === 1 ? 'text-danger-main' : (p.posisi === null ? 'text-success-main' : ''))
            + ubin('Bulan Bersih', num(r.bulan_bersih) + ' / ' + num(r.bulan_count),
                   r.terakhir_kena
                       ? 'total ' + num(r.total) + ' pelanggaran · terakhir kedapatan ' + r.terakhir_kena
                       : 'belum pernah kedapatan sepanjang periode ini',
                   r.bulan_kena ? '' : 'text-success-main')
            + '</div>';

        var lintas = (j.lintas_site || []).length > 1;
        var kolom = lintas ? 'col-xxl-4' : 'col-xxl-6';

        isi += '<div class="row gy-4">'
            +   '<div class="' + kolom + '">'
            +     '<h6 class="text-md fw-semibold mb-4">Riwayat ' + esc(j.judul.mitra)
            +       ' di ' + esc(j.judul.site) + '</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Sekali kejadian atau berulang tiap bulan</span>'
            +     tabel(j.riwayat, 'bulan', 'Bulan')
            +   '</div>'
            +   '<div class="' + kolom + '">'
            +     '<h6 class="text-md fw-semibold mb-4">Perusahaan di ' + esc(j.judul.site)
            +       ' · ' + esc(j.judul.bulan) + '</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Satu perusahaan saja atau memang se-site</span>'
            +     tabel(j.sebulan, 'mitra', 'Perusahaan')
            +   '</div>';

        if (lintas) {
            isi += '<div class="' + kolom + '">'
                +   '<h6 class="text-md fw-semibold mb-4">' + esc(j.judul.mitra)
                +     ' di Site Lain · ' + esc(j.judul.bulan) + '</h6>'
                +   '<span class="text-xs text-secondary-light d-block mb-12">'
                +     'Melekat pada perusahaannya atau pada site ini saja</span>'
                +   tabel(j.lintas_site, 'site', 'Site')
                + '</div>';
        }

        bagian('isi').innerHTML = isi + '</div>';
        bagian('kaki').textContent = 'Sumbernya sudah berupa cacah task, tanpa tabel rincian '
            + 'per pelanggaran, jadi yang ditampilkan konteks di sekeliling sel — semuanya '
            + 'dari sumber yang sama dengan matriks. Baris bernilai 0 berarti bersih pada '
            + 'bulan itu, bukan belum ada datanya.';
    }

    /**
     * Satu tabel konteks. Baris bernilai 0 sengaja ikut tampil: tanpa baris
     * nol, "bersih" tidak bisa dibedakan dari "tidak terdaftar".
     */
    function tabel(daftar, kunci, judulKolom) {
        if (!daftar || !daftar.length) {
            return '<div class="text-center text-secondary-light py-24">Tidak ada pembanding.</div>';
        }

        var puncak = Math.max.apply(null, daftar.map(function (r) { return r.jumlah; })) || 0;

        return '<div class="table-responsive grs-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>' + esc(judulKolom) + '</th><th class="text-end">Pelanggaran</th>'
            +   '<th style="width:34%">Porsi</th>'
            + '</tr></thead><tbody>'
            + daftar.map(function (r) {
                return '<tr' + (r.ini ? ' class="bg-primary-50"' : '') + '>'
                    + '<td class="text-sm' + (r.ini ? ' fw-semibold' : '') + '">' + esc(r[kunci])
                    +   (r.ini ? ' <span class="text-xs text-primary-600">(sel ini)</span>' : '') + '</td>'
                    + '<td class="text-end">' + angka(r.jumlah) + '</td>'
                    + '<td>' + batang(r.jumlah, puncak)
                    +   '<span class="text-xs text-secondary-light">'
                    +     (r.jumlah > 0 ? esc(kataBulan(r.jumlah, 'pelanggaran')) : 'bersih')
                    +   '</span></td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function buka(url, koordinat) {
        // Nomor permintaan menjaga agar jawaban yang datang terlambat untuk sel
        // yang sudah tidak dibuka lagi tidak menimpa isi modal.
        var ini = ++permintaan;

        el.querySelector('#grs-detail-judul').textContent =
            'Rincian ' + koordinat.bulan + ' · ' + koordinat.site;
        bagian('subjudul').textContent = koordinat.mitra;
        bagian('kaki').textContent = '';
        bagian('isi').innerHTML = '<div class="text-center text-secondary-light py-40">'
            + '<div class="spinner-border spinner-border-sm text-primary-600 me-2" role="status"></div>'
            + 'Memuat rincian…</div>';

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }

        var q = new URLSearchParams({
            site: koordinat.site, mitra: koordinat.mitra, month: koordinat.month
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
                render(j);
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

// ---- Tab Ringkasan ----------------------------------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.hp-overview');
    if (!root) { return; }

    var PALETTE = ['#0F172A', '#E0484A', '#FF9F29', '#F2C230', '#8252E9', '#487FFF', '#45B369'];

    var overviewUrl = root.dataset.url;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.hp-filter'));
    var charts = { monthly: null };
    var loaded = false;

    function el(name) {
        return root.querySelector('[data-hp="' + name + '"]');
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap ganti
    // filter, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    if (grsModalDetail && matrixEl && root.dataset.detailUrl) {
        var bukaSel = function (td) {
            grsModalDetail.buka(root.dataset.detailUrl, {
                site: td.dataset.site,
                mitra: td.dataset.mitra,
                month: td.dataset.month,
                bulan: td.dataset.bulan
            });
        };

        matrixEl.addEventListener('click', function (e) {
            var td = e.target.closest('.hp-cell--klik');
            if (td && matrixEl.contains(td)) { bukaSel(td); }
        });

        matrixEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var td = e.target.closest('.hp-cell--klik');
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
        return { 1: 'hp-k1', 2: 'hp-k2', 3: 'hp-k3', 4: 'hp-k4' }[bandCacah(jumlah)] || 'hp-k4';
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
                grad: 'bg-gradient-end-5', icon: 'mdi:seatbelt', dot: 'bg-danger-main',
                label: 'Total Pelanggaran', value: fmtNum(k.temuan),
                foot: k.bulan_terburuk
                    ? 'Terbanyak di ' + escapeHtml(k.bulan_terburuk) + ' (' + fmtNum(k.temuan_terburuk) + ')'
                    : 'Tidak ada pelanggaran sama sekali'
            },
            {
                grad: 'bg-gradient-end-2', icon: 'solar:calendar-outline', dot: 'bg-success-main',
                label: 'Bulan Tanpa Pelanggaran', value: fmtNum(k.bulan_bersih) + ' / ' + fmtNum(k.bulan_count),
                foot: 'Rata-rata ' + fmtNum(k.rata_per_bulan) + ' pelanggaran per bulan'
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
                + 'Tidak ada pelanggaran untuk filter ini.</p>';
            return;
        }

        var puncak = Math.max.apply(null, list.map(function (s) { return s.jumlah; })) || 1;

        host.innerHTML = list.map(function (s, i) {
            return '<div class="' + (i ? 'mt-20' : '') + '">'
                + '<div class="d-flex align-items-center justify-content-between mb-8">'
                +   '<span class="text-sm fw-semibold">' + escapeHtml(s.site) + '</span>'
                +   '<span class="text-sm fw-medium text-secondary-light">' + fmtNum(s.jumlah) + ' pelanggaran</span>'
                + '</div>'
                + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                +   '<div class="progress-bar bg-danger-main rounded-pill" role="progressbar"'
                +     ' style="width:' + (s.jumlah / puncak * 100) + '%" aria-valuenow="' + s.jumlah + '"'
                +     ' aria-valuemin="0" aria-valuemax="' + puncak + '"></div>'
                + '</div>'
                + '<span class="text-xs text-secondary-light">' + fmtPct(s.percent)
                +   ' dari seluruh pelanggaran · ' + fmtNum(s.lawan) + ' perusahaan</span>'
                + '</div>';
        }).join('');
    }

    // ---- Peringkat perusahaan ----------------------------------------------
    function renderPerMitra(list) {
        var body = el('per-mitra');
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-secondary-light py-24">'
                + 'Tidak ada pelanggaran untuk filter ini.</td></tr>';
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
                { color: '#FF0000', label: 'lebih dari 5 pelanggaran' },
                { color: '#FFC000', label: '4-5 pelanggaran' },
                { color: '#FFFF00', label: '1-3 pelanggaran' },
                { color: '#92D050', label: 'tidak ada pelanggaran' }
            ];

        el('legend').innerHTML = items.map(function (it) {
            return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                + '<span class="rounded-1" style="width:14px;height:14px;background:' + it.color + ';"></span>'
                + escapeHtml(it.label) + '</span>';
        }).join('');
    }

    // Ganti mode hanya menggambar ulang dari payload terakhir: angka Nilai
    // sudah ikut dikirim, jadi tidak perlu meminta ulang ke server.
    root.querySelectorAll('.hp-switch__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.mode === matrixMode) { return; }

            matrixMode = btn.dataset.mode;

            root.querySelectorAll('.hp-switch__btn').forEach(function (b) {
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

        var head = '<tr><th class="hp-site">SITE</th><th class="hp-mitra">PERUSAHAAN PIC</th>'
            + '<th>TOTAL</th><th>TREND</th>';
        months.forEach(function (m, i) {
            head += '<th class="' + (i === months.length - 1 ? 'hp-th-last' : '') + '">'
                + escapeHtml(m.label) + '</th>';
        });
        thead.innerHTML = head + '</tr>';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="' + (months.length + 4) + '" class="text-center py-24 text-secondary-light">'
                + 'Tidak ada pelanggaran untuk filter ini — dan itu kabar baik.</td></tr>';
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
                + (lewati[i] ? '' : '<td class="hp-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="hp-mitra">' + escapeHtml(row.mitra) + '</td>'
                + '<td class="hp-total" title="' + escapeHtml(row.bulan_kena + ' bulan kedapatan, '
                    + row.bulan_bersih + ' bulan bersih') + '">' + (matrixMode === 'nilai' ? row.nilai : fmtNum(row.total)) + '</td>';

            if (row.trend === 'up') {
                html += '<td class="hp-trend--up" title="Bertambah dari bulan sebelumnya">&uarr;</td>';
            } else if (row.trend === 'down') {
                html += '<td class="hp-trend--down" title="Berkurang dari bulan sebelumnya">&darr;</td>';
            } else if (row.trend === 'flat') {
                html += '<td class="hp-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
            } else {
                html += '<td class="text-secondary-light">–</td>';
            }

            // Variabel sengaja dinamai m: i di luar sudah dipakai sebagai index
            // baris untuk perhitungan rowspan site.
            row.cells.forEach(function (jumlah, m) {
                var tip = row.site + ' · ' + row.mitra + ' · ' + months[m].label + ': '
                    + (jumlah === 0 ? 'tidak ada pelanggaran' : fmtNum(jumlah) + ' pelanggaran');

                // Koordinat sel dibawa di atribut, bukan ditebak dari posisi
                // DOM: urutan baris berubah mengikuti pengurutan per site.
                html += '<td class="hp-cell hp-cell--klik ' + tierClass(jumlah) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-mitra="' + escapeHtml(row.mitra) + '"'
                    + ' data-month="' + months[m].number + '"'
                    + ' data-bulan="' + escapeHtml(months[m].label) + '"'
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
                title: { text: 'Pelanggaran', style: { fontSize: '11px' } },
                labels: { formatter: function (v) { return fmtNum(Math.round(v)); } }
            },
            legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            tooltip: {
                shared: true,
                // WAJIB eksplisit: kombinasi shared + intersect melempar error
                // sehingga grafiknya gagal dirender sama sekali.
                intersect: false,
                y: { formatter: function (v) { return fmtNum(v) + ' pelanggaran'; } }
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
                console.error('GR Seatbelt: panel "' + label + '" gagal dirender', err);
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
                el('status').textContent = fmtNum(k.temuan) + ' temuan · ' + k.kombinasi
                    + ' pasangan site/perusahaan · ' + k.bulan_count + ' bulan, '
                    + k.bulan_bersih + ' di antaranya tanpa temuan';
                loaded = true;
            })
            .catch(function (err) {
                el('status').textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Ringkasan GR Seatbelt: gagal memuat', err);
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

    var tab = document.querySelector('#hp-tab-ringkasan');
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

    var root = document.querySelector('.hp-datatable');
    if (!root) { return; }

    var tableEl = root.querySelector('[data-hp="table"]');
    if (!tableEl || typeof DataTable === 'undefined') { return; }
    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.hpd-filter'));
    var hintEl = root.querySelector('[data-hp="hint"]');

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
                    console.error('GR Seatbelt: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'site' },
            { data: 'mitra' },
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
            infoEmpty: 'Tidak ada pelanggaran',
            infoFiltered: '(disaring dari _MAX_ baris)',
            search: 'Cari:',
            zeroRecords: 'Tidak ada pelanggaran untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    filterEls.forEach(function (node) {
        node.addEventListener('change', function () { table.ajax.reload(); });
    });

    root.querySelector('[data-hp="reset"]').addEventListener('click', function () {
        filterEls.forEach(function (node) { node.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    root.querySelectorAll('[data-hp="export"]').forEach(function (btn) {
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
    var tab = document.querySelector('#hp-tab-data');
    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        });
    }
})();
</script>
@endsection
