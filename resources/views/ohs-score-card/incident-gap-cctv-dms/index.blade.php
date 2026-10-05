@extends('ohs-score-card.layouts.app')

@section('title', 'Incident dengan Gap Coverage CCTV & DMS')

@section('css')
<style>

  /* ---- Matriks insiden ----
     Skala warnanya terbalik dari halaman capaian: nol yang hijau, karena
     target parameter ini memang tidak ada insiden sama sekali. */
  .gap-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .gap-matrix {
    width: 100%; min-width: 760px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .gap-matrix th, .gap-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .gap-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .gap-matrix thead th.gap-th-last { background: #2E90FA !important; color: #fff !important; }
  .gap-matrix .gap-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .gap-matrix .gap-mitra {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 190px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .gap-matrix thead .gap-site, .gap-matrix thead .gap-mitra { z-index: 4; background: #F8FAFC; }
  .gap-matrix .gap-total { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  /* Insiden bertambah berarti memburuk, jadi panah atas merah. */
  .gap-matrix .gap-trend--up { color: #DC2626; font-weight: 800; }
  .gap-matrix .gap-trend--down { color: #16A34A; font-weight: 800; }
  .gap-matrix .gap-trend--flat { color: #94A3B8; font-weight: 800; }
  .gap-matrix .gap-cell {
    font-weight: 700; color: #fff; min-width: 54px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    font-variant-numeric: tabular-nums;
  }
  .gap-matrix .gap-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(220,38,38,.3);
    position: relative; z-index: 1;
  }
  /* Sel nol pun bisa dibuka: di halaman ini nol adalah hasil pengukuran,
     bukan data yang hilang, dan modalnya memang menerangkan itu. */
  .gap-matrix .gap-cell--klik { cursor: pointer; }
  .gap-matrix .gap-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Daftar deviasi di dalam modal digulir sendiri. */
  .gap-modal-scroll { max-height: 40vh; overflow: auto; }
  .gap-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }
  /* Keterangan deviasi panjang-panjang; dipotong agar baris tabel tetap rapi. */
  .gap-keterangan {
    display: block; max-width: 380px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }

  /* Nol adalah keadaan yang diinginkan, jadi hijau — bukan sel kosong abu-abu. */
  /* Warna band resmi; ambangnya sama persis dengan SCORE_BANDS di
     controller. Nol adalah hasil terbaik, jadi hijau. */
  .gap-k4 { background: #92D050; color: #1F2937 !important; }
  .gap-k3 { background: #FFFF00; color: #1F2937 !important; }
  .gap-k2 { background: #FFC000; color: #1F2937 !important; }
  .gap-k1 { background: #FF0000; }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Incident dengan Gap Coverage CCTV & Gap pada DMS</h6>
    <div class="text-secondary-light text-sm mt-4">
      Insiden yang terjadi saat coverage CCTV atau DMS sedang bercelah — targetnya nol
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
    <li class="fw-medium text-primary-600">Incident dengan Gap Coverage CCTV & Gap pada DMS</li>
  </ul>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="gap-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="gap-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#gap-pane-ringkasan"
            type="button" role="tab" aria-controls="hp-pane-ringkasan" aria-selected="true">
      Ringkasan
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="gap-tab-data"
            data-bs-toggle="pill" data-bs-target="#gap-pane-data"
            type="button" role="tab" aria-controls="hp-pane-data" aria-selected="false">
      Data
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="gap-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.incident-gap-cctv-dms.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="gap-pane-data" role="tabpanel">
    @include('ohs-score-card.incident-gap-cctv-dms.partials._data')
  </div>
</div>

{{-- Modal rincian satu sel matriks.
     Ditaruh di luar .gap-overview dan di luar tab pane supaya tidak ikut
     tersembunyi saat tab Data yang aktif. --}}
<div class="modal fade" id="gap-detail-modal" tabindex="-1" aria-labelledby="gap-detail-judul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content radius-12">
      <div class="modal-header border-bottom py-16 px-24">
        <div>
          <h6 class="modal-title text-lg fw-semibold mb-0" id="gap-detail-judul">Gap di Lapisan Mana</h6>
          <span class="text-sm text-secondary-light" data-gapm="subjudul"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24"><div data-gapm="isi"></div></div>
      <div class="modal-footer border-top py-12 px-24">
        <span class="text-sm text-secondary-light me-auto" data-gapm="kaki"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// ---- Modal rincian satu sel matriks -----------------------------------------
var gapModalDetail = (function () {
    'use strict';

    var el = document.getElementById('gap-detail-modal');
    if (!el) { return null; }

    var bagian = function (n) { return el.querySelector('[data-gapm="' + n + '"]'); };
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

    /**
     * Pecahan satu lapisan. Parameter ini berbutir LAPISAN, bukan insiden:
     * satu insiden bisa punya beberapa baris deviasi, jadi pecahan inilah yang
     * menjawab "gap-nya di lapisan mana", bukan sekadar daftar insiden.
     */
    function daftarLapis(baris, warna) {
        if (!baris || !baris.length) {
            return '<div class="text-center text-secondary-light py-16">Tidak ada data.</div>';
        }
        var maks = baris[0].jumlah || 1;
        return '<div class="table-responsive"><table class="table bordered-table sm-table mb-0"><tbody>'
            + baris.map(function (b) {
                return '<tr><td class="text-sm">' + esc(b.label) + '</td>'
                    + '<td class="text-end fw-semibold" style="width:64px">' + num(b.jumlah) + '</td>'
                    + '<td class="text-end text-xs text-secondary-light" style="width:64px">'
                    +   Number(b.percent).toFixed(1) + '%</td>'
                    + '<td style="width:30%"><div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                    + '<div class="progress-bar ' + warna + ' rounded-pill" role="progressbar"'
                    + ' style="width:' + (b.jumlah / maks * 100) + '%" aria-valuenow="' + b.jumlah + '"'
                    + ' aria-valuemin="0" aria-valuemax="' + maks + '"></div></div></td></tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function render(j, koordinat) {
        var r = j.ringkas;

        var isi = '<div class="row gy-3 mb-20">'
            + ubin('Insiden dengan Gap', esc(koordinat.nilai || num(r.insiden)),
                   'nilai sel yang diklik, dari tabel ringkasan')
            + ubin('Baris Deviasi', num(r.deviasi),
                   r.rata_deviasi === null
                       ? 'dari tabel rincian'
                       : 'rata-rata ' + r.rata_deviasi + ' baris per insiden')
            + '</div>';

        // Ringkasan mencacah INSIDEN, rincian mencacah BARIS LAPISAN. Keduanya
        // memang tidak sama, jadi selisihnya diterangkan -- bukan disembunyikan
        // -- supaya tidak terbaca sebagai data yang hilang.
        if (r.selisih !== 0) {
            isi += '<div class="alert bg-info-focus text-info-main border-info-main'
                + ' radius-8 px-20 py-12 mb-20 text-sm">'
                + 'Ringkasan mencacah <strong>insiden</strong> (' + num(r.insiden) + '), '
                + 'rincian mencacah <strong>baris lapisan</strong> (' + num(r.deviasi) + '). '
                + 'Satu insiden bisa punya beberapa baris deviasi, jadi kedua angka ini '
                + 'memang berbeda dan keduanya benar.'
                + '</div>';
        }

        if (!j.baris.length) {
            bagian('isi').innerHTML = isi
                + '<div class="text-center text-secondary-light py-24">'
                + 'Tidak ada baris deviasi di tabel rincian untuk kombinasi ini.</div>';
            bagian('kaki').textContent = '';
            return;
        }

        isi += '<div class="row gy-4 mb-20">'
            +   '<div class="col-xxl-4">'
            +     '<h6 class="text-md fw-semibold mb-4">Activity</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Kegiatan saat gap terjadi</span>'
            +     daftarLapis(j.per_alat, 'bg-info-main')
            +   '</div>'
            +   '<div class="col-xxl-4">'
            +     '<h6 class="text-md fw-semibold mb-4">Klasifikasi</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Jenis ketidaksesuaian yang tercatat</span>'
            +     daftarLapis(j.per_klasifikasi, 'bg-warning-main')
            +   '</div>'
            +   '<div class="col-xxl-4">'
            +     '<h6 class="text-md fw-semibold mb-4">Status</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Status lapisan pada saat kejadian</span>'
            +     daftarLapis(j.per_status, 'bg-danger-main')
            +   '</div>'
            + '</div>';

        isi += '<h6 class="text-md fw-semibold mb-12">Daftar deviasi</h6>'
            + '<div class="row gy-2 gx-2 align-items-end mb-12">'
            +   '<div class="col-sm-8"><input type="text" class="form-control form-control-sm radius-8"'
            +     ' placeholder="Cari activity, klasifikasi, status, keterangan…" data-gapm="cari"></div>'
            +   '<div class="col-sm-4 text-sm-end"><span class="text-sm text-secondary-light"'
            +     ' data-gapm="hitung"></span></div>'
            + '</div>'
            + '<div class="table-responsive gap-modal-scroll">'
            +   '<table class="table bordered-table sm-table mb-0" data-gapm="tabel"><thead><tr>'
            +     '<th>Activity</th><th>Klasifikasi</th><th>Status</th>'
            +     '<th class="text-end">Insiden</th><th>Keterangan</th>'
            +   '</tr></thead><tbody>'
            +   j.baris.map(function (b) {
                    return '<tr data-cari="'
                        + esc((b.activity + ' ' + b.klasifikasi + ' ' + b.status + ' '
                               + b.keterangan).toLowerCase()) + '">'
                        + '<td><span class="text-sm">' + esc(b.activity || '-') + '</span></td>'
                        + '<td><span class="text-sm">' + esc(b.klasifikasi || '-') + '</span></td>'
                        + '<td><span class="text-sm">' + esc(b.status || '-') + '</span></td>'
                        + '<td class="text-end fw-semibold">' + num(b.jumlah) + '</td>'
                        + '<td><span class="text-sm text-secondary-light gap-keterangan">'
                        +   esc(b.keterangan || '-') + '</span></td>'
                        + '</tr>';
                }).join('')
            +   '</tbody></table></div>';

        bagian('isi').innerHTML = isi;
        bagian('kaki').textContent = j.terpotong
            ? 'Menampilkan ' + num(j.batas) + ' baris pertama dari ' + num(r.deviasi) + '.'
            : num(r.deviasi) + ' baris deviasi tercatat di sel ini.';

        pasangPencarian();
    }

    /** Pencarian dikerjakan di baris yang sudah ada, tanpa ke server lagi. */
    function pasangPencarian() {
        var cari = bagian('cari');
        var hitung = bagian('hitung');
        var semua = Array.prototype.slice.call(el.querySelectorAll('[data-gapm="tabel"] tbody tr'));

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

        el.querySelector('#gap-detail-judul').textContent =
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

    var root = document.querySelector('.gap-overview');
    if (!root) { return; }

    var PALETTE = ['#0F172A', '#E0484A', '#FF9F29', '#F2C230', '#8252E9', '#487FFF', '#45B369'];

    var overviewUrl = root.dataset.url;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.gap-filter'));
    var charts = {
        monthly: null,
        'chart-klasifikasi': null,
        'chart-status': null,
        'chart-activity': null
    };
    var loaded = false;

    function el(name) {
        return root.querySelector('[data-gap="' + name + '"]');
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap ganti
    // filter, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    if (gapModalDetail && matrixEl && root.dataset.detailUrl) {
        var bukaSel = function (td) {
            gapModalDetail.buka(root.dataset.detailUrl, {
                site: td.dataset.site,
                mitra: td.dataset.mitra,
                month: td.dataset.month,
                bulan: td.dataset.bulan,
                nilai: td.dataset.nilai
            });
        };

        matrixEl.addEventListener('click', function (e) {
            var td = e.target.closest('.gap-cell--klik');
            if (td && matrixEl.contains(td)) { bukaSel(td); }
        });

        matrixEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var td = e.target.closest('.gap-cell--klik');
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

    // Nol hijau, dan makin banyak insiden makin merah.
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
        return { 1: 'gap-k1', 2: 'gap-k2', 3: 'gap-k3', 4: 'gap-k4' }[bandCacah(jumlah)] || 'gap-k4';
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
                grad: 'bg-gradient-end-5', icon: 'solar:videocamera-outline', dot: 'bg-danger-main',
                label: 'Total Insiden', value: fmtNum(k.insiden),
                foot: k.bulan_terburuk
                    ? 'Terbanyak di ' + escapeHtml(k.bulan_terburuk) + ' (' + fmtNum(k.insiden_terburuk) + ')'
                    : 'Tidak ada insiden sama sekali'
            },
            {
                grad: 'bg-gradient-end-2', icon: 'solar:calendar-outline', dot: 'bg-success-main',
                label: 'Bulan Tanpa Insiden', value: fmtNum(k.bulan_bersih) + ' / ' + fmtNum(k.bulan_count),
                foot: 'Rata-rata ' + fmtNum(k.rata_per_bulan) + ' insiden per bulan'
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

    // ---- Insiden per site ----------------------------------------------------
    function renderPerSite(list) {
        var host = el('per-site');
        if (!list.length) {
            host.innerHTML = '<p class="text-secondary-light text-sm text-center py-24 mb-0">'
                + 'Tidak ada insiden untuk filter ini.</p>';
            return;
        }

        var puncak = Math.max.apply(null, list.map(function (s) { return s.jumlah; })) || 1;

        host.innerHTML = list.map(function (s, i) {
            return '<div class="' + (i ? 'mt-20' : '') + '">'
                + '<div class="d-flex align-items-center justify-content-between mb-8">'
                +   '<span class="text-sm fw-semibold">' + escapeHtml(s.site) + '</span>'
                +   '<span class="text-sm fw-medium text-secondary-light">' + fmtNum(s.jumlah) + ' insiden</span>'
                + '</div>'
                + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                +   '<div class="progress-bar bg-danger-main rounded-pill" role="progressbar"'
                +     ' style="width:' + (s.jumlah / puncak * 100) + '%" aria-valuenow="' + s.jumlah + '"'
                +     ' aria-valuemin="0" aria-valuemax="' + puncak + '"></div>'
                + '</div>'
                + '<span class="text-xs text-secondary-light">' + fmtPct(s.percent)
                +   ' dari seluruh insiden · ' + fmtNum(s.lawan) + ' perusahaan</span>'
                + '</div>';
        }).join('');
    }

    // ---- Peringkat perusahaan ----------------------------------------------
    function renderPerMitra(list) {
        var body = el('per-mitra');
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-secondary-light py-24">'
                + 'Tidak ada insiden untuk filter ini.</td></tr>';
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
                { color: '#FF0000', label: 'lebih dari 5 insiden' },
                { color: '#FFC000', label: '4-5 insiden' },
                { color: '#FFFF00', label: '1-3 insiden' },
                { color: '#92D050', label: 'tidak ada insiden' }
            ];

        el('legend').innerHTML = items.map(function (it) {
            return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                + '<span class="rounded-1" style="width:14px;height:14px;background:' + it.color + ';"></span>'
                + escapeHtml(it.label) + '</span>';
        }).join('');
    }

    // Ganti mode hanya menggambar ulang dari payload terakhir: angka Nilai
    // sudah ikut dikirim, jadi tidak perlu meminta ulang ke server.
    root.querySelectorAll('.gap-switch__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.mode === matrixMode) { return; }

            matrixMode = btn.dataset.mode;

            root.querySelectorAll('.gap-switch__btn').forEach(function (b) {
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

        var head = '<tr><th class="gap-site">SITE</th><th class="gap-mitra">PERUSAHAAN PIC</th>'
            + '<th>TOTAL</th><th>TREND</th>';
        months.forEach(function (m, i) {
            head += '<th class="' + (i === months.length - 1 ? 'gap-th-last' : '') + '">'
                + escapeHtml(m.label) + '</th>';
        });
        thead.innerHTML = head + '</tr>';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="' + (months.length + 4) + '" class="text-center py-24 text-secondary-light">'
                + 'Tidak ada insiden untuk filter ini — dan itu kabar baik.</td></tr>';
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
                + (lewati[i] ? '' : '<td class="gap-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="gap-mitra">' + escapeHtml(row.mitra) + '</td>'
                + '<td class="gap-total" title="' + escapeHtml(row.bulan_kena + ' bulan kedapatan, '
                    + row.bulan_bersih + ' bulan bersih') + '">' + (matrixMode === 'nilai' ? row.nilai : fmtNum(row.total)) + '</td>';

            if (row.trend === 'up') {
                html += '<td class="gap-trend--up" title="Bertambah dari bulan sebelumnya">&uarr;</td>';
            } else if (row.trend === 'down') {
                html += '<td class="gap-trend--down" title="Berkurang dari bulan sebelumnya">&darr;</td>';
            } else if (row.trend === 'flat') {
                html += '<td class="gap-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
            } else {
                html += '<td class="text-secondary-light">–</td>';
            }

            // Variabel sengaja dinamai m: i di luar sudah dipakai sebagai index
            // baris untuk perhitungan rowspan site.
            row.cells.forEach(function (jumlah, m) {
                var tip = row.site + ' · ' + row.mitra + ' · ' + months[m].label + ': '
                    + (jumlah === 0 ? 'tidak ada insiden' : fmtNum(jumlah) + ' insiden');

                // Koordinat sel dibawa di atribut, bukan ditebak dari posisi
                // DOM: urutan baris ikut berubah tiap kali filter diganti.
                html += '<td class="gap-cell gap-cell--klik ' + tierClass(jumlah) + '"'
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
                title: { text: 'Insiden', style: { fontSize: '11px' } },
                labels: { formatter: function (v) { return fmtNum(Math.round(v)); } }
            },
            legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            tooltip: {
                shared: true,
                // WAJIB eksplisit: kombinasi shared + intersect melempar error
                // sehingga grafiknya gagal dirender sama sekali.
                intersect: false,
                y: { formatter: function (v) { return fmtNum(v) + ' insiden'; } }
            }
        });
        charts.monthly.render();
    }

    function renderCatatan(text) {
        el('note').textContent = text || '';
        el('note-wrap').classList.toggle('d-none', !text);
    }

    /**
     * Tiga rincian dari tabel detail: klasifikasi deviasi, status layer, dan
     * jenis alat. Ketiganya batang mendatar karena labelnya panjang-panjang.
     */
    function renderRincian(name, rows, warna) {
        var node = el(name);
        if (!node || typeof ApexCharts === 'undefined') { return; }

        if (charts[name]) {
            charts[name].destroy();
            charts[name] = null;
        }

        if (!rows.length) {
            node.innerHTML = '<p class="text-secondary-light text-sm text-center py-40 mb-0">'
                + 'Tidak ada data.</p>';
            return;
        }
        node.innerHTML = '';

        charts[name] = new ApexCharts(node, {
            series: [{ name: 'Deviasi', data: rows.map(function (r) { return r.jumlah; }) }],
            // Tingginya ikut jumlah batang supaya label tidak berimpitan saat
            // klasifikasinya banyak.
            chart: { type: 'bar', height: Math.max(200, rows.length * 34 + 60), toolbar: { show: false } },
            colors: [warna],
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '58%' } },
            dataLabels: {
                enabled: true,
                formatter: function (v) { return fmtNum(v); },
                style: { fontSize: '11px', colors: ['#fff'] }
            },
            xaxis: {
                categories: rows.map(function (r) { return r.label; }),
                labels: { style: { fontSize: '11px' }, formatter: function (v) { return fmtNum(v); } }
            },
            yaxis: { labels: { style: { fontSize: '11px' }, maxWidth: 260 } },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            tooltip: {
                y: {
                    formatter: function (v, opts) {
                        var r = rows[opts.dataPointIndex] || {};
                        return fmtNum(v) + ' deviasi (' + fmtPct(r.percent) + ')';
                    }
                }
            }
        });
        charts[name].render();
    }

    /** Membungkus renderer agar kegagalan satu panel tidak menjatuhkan sisanya. */
    function safe(label, fn) {
        try {
            fn();
        } catch (err) {
            if (typeof console !== 'undefined' && console.error) {
                console.error('Gap CCTV & DMS: panel "' + label + '" gagal dirender', err);
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
                safe('klasifikasi', function () {
                    renderRincian('chart-klasifikasi', json.per_klasifikasi || [], '#E0484A');
                });
                safe('status', function () {
                    renderRincian('chart-status', json.per_status || [], '#FF9F29');
                });
                safe('activity', function () {
                    renderRincian('chart-activity', json.per_activity || [], '#487FFF');
                });
                safe('catatan', function () { renderCatatan(json.catatan); });

                var k = json.kpi;
                el('status').textContent = fmtNum(k.insiden) + ' insiden · ' + k.kombinasi
                    + ' pasangan site/perusahaan · ' + k.bulan_count + ' bulan, '
                    + k.bulan_bersih + ' di antaranya tanpa insiden';
                loaded = true;
            })
            .catch(function (err) {
                el('status').textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Ringkasan Gap CCTV & DMS: gagal memuat', err);
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

    var tab = document.querySelector('#gap-tab-ringkasan');
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

    var root = document.querySelector('.gap-datatable');
    if (!root) { return; }

    var tableEl = root.querySelector('[data-gap="table"]');
    if (!tableEl || typeof DataTable === 'undefined') { return; }
    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.gapd-filter'));
    var hintEl = root.querySelector('[data-gap="hint"]');

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
                    console.error('Gap CCTV & DMS: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'site' },
            { data: 'mitra' },
            { data: 'status' },
            { data: 'klasifikasi' },
            {
                data: 'keterangan',
                orderable: false,
                render: function (d, type) {
                    if (type !== 'display') { return d; }
                    // Teks penuh tetap ada di title; yang dipotong hanya
                    // tampilannya, supaya tinggi baris tidak meledak.
                    return '<span class="gap-keterangan" title="' + escapeHtml(d) + '">'
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
            infoEmpty: 'Tidak ada insiden',
            infoFiltered: '(disaring dari _MAX_ baris)',
            search: 'Cari:',
            zeroRecords: 'Tidak ada insiden untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    filterEls.forEach(function (node) {
        node.addEventListener('change', function () { table.ajax.reload(); });
    });

    root.querySelector('[data-gap="reset"]').addEventListener('click', function () {
        filterEls.forEach(function (node) { node.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    root.querySelectorAll('[data-gap="export"]').forEach(function (btn) {
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
    var tab = document.querySelector('#gap-tab-data');
    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        });
    }
})();
</script>
@endsection
