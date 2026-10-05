@extends('ohs-score-card.layouts.app')

@section('title', 'Deviasi Rekayasa Engineering Overspeed')

@section('css')
<style>

  /* ---- Matriks pelanggar ----
     Skala warnanya terbalik dari halaman capaian: nol yang hijau, karena
     target parameter ini memang tidak ada pelanggar sama sekali. */
  .osp-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .osp-matrix {
    width: 100%; min-width: 760px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .osp-matrix th, .osp-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .osp-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .osp-matrix thead th.osp-th-last { background: #2E90FA !important; color: #fff !important; }
  .osp-matrix .osp-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .osp-matrix .osp-mitra {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 190px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .osp-matrix thead .osp-site, .osp-matrix thead .osp-mitra { z-index: 4; background: #F8FAFC; }
  .osp-matrix .osp-total { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  /* Pelanggar bertambah berarti memburuk, jadi panah atas merah. */
  .osp-matrix .osp-trend--up { color: #DC2626; font-weight: 800; }
  .osp-matrix .osp-trend--down { color: #16A34A; font-weight: 800; }
  .osp-matrix .osp-trend--flat { color: #94A3B8; font-weight: 800; }
  .osp-matrix .osp-cell {
    font-weight: 700; color: #fff; min-width: 54px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    font-variant-numeric: tabular-nums;
  }
  .osp-matrix .osp-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(220,38,38,.3);
    position: relative; z-index: 1;
  }
  /* Setiap sel bisa dibuka rinciannya, termasuk yang bernilai nol: nol di sini
     berarti "tidak ada pelanggar", bukan "belum ada data". */
  .osp-matrix .osp-cell--klik { cursor: pointer; }
  .osp-matrix .osp-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Tabel konteks di dalam modal digulir sendiri agar modalnya tidak memanjang. */
  .osp-modal-scroll { max-height: 34vh; overflow: auto; }
  .osp-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }

  /* Nol adalah keadaan yang diinginkan, jadi hijau — bukan sel kosong abu-abu. */
  .osp-k0 { background: #16A34A; }
  .osp-k1 { background: #F2C230; color: #1F2937 !important; }
  .osp-k2 { background: #F08C2E; }
  .osp-k3 { background: #E0484A; }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Deviasi Rekayasa Engineering Overspeed</h6>
    <div class="text-secondary-light text-sm mt-4">
      Karyawan yang kedapatan melanggar batas kecepatan, per perusahaan di tiap site — targetnya nol
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
    <li class="fw-medium text-primary-600">Deviasi Overspeed</li>
  </ul>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="osp-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="osp-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#osp-pane-ringkasan"
            type="button" role="tab" aria-controls="osp-pane-ringkasan" aria-selected="true">
      Ringkasan
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="osp-tab-data"
            data-bs-toggle="pill" data-bs-target="#osp-pane-data"
            type="button" role="tab" aria-controls="osp-pane-data" aria-selected="false">
      Data
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="osp-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.pelanggaran-overspeed.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="osp-pane-data" role="tabpanel">
    @include('ohs-score-card.pelanggaran-overspeed.partials._data')
  </div>
</div>

{{-- Modal rincian satu sel. Ditaruh di luar tab pane supaya tidak ikut
     tersembunyi saat berpindah tab. --}}
<div class="modal fade" id="osp-detail-modal" tabindex="-1" aria-labelledby="osp-detail-judul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content radius-12">
      <div class="modal-header border-bottom py-16 px-24">
        <div>
          <h6 class="modal-title text-lg fw-semibold mb-0" id="osp-detail-judul">Rincian Bulan</h6>
          <span class="text-sm text-secondary-light" data-ospm="subjudul"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24"><div data-ospm="isi"></div></div>
      <div class="modal-footer border-top py-12 px-24">
        <span class="text-sm text-secondary-light me-auto" data-ospm="kaki"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// ---- Modal rincian satu sel matriks -----------------------------------------
var ospModalDetail = (function () {
    'use strict';

    var el = document.getElementById('osp-detail-modal');
    if (!el) { return null; }

    var bagian = function (n) { return el.querySelector('[data-ospm="' + n + '"]'); };
    var permintaan = 0;

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function num(v) { return Number(v || 0).toLocaleString('id-ID'); }

    function pct(v) {
        return v === null || v === undefined
            ? '–'
            : Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
    }

    function ubin(label, nilai, catatan, kelas) {
        return '<div class="col-xxl-3 col-md-6">'
            + '<div class="border input-form-light radius-8 p-16 h-100">'
            +   '<span class="text-sm text-secondary-light d-block">' + esc(label) + '</span>'
            +   '<h6 class="fw-semibold mt-8 mb-4 ' + (kelas || '') + '">' + nilai + '</h6>'
            +   '<span class="text-xs text-secondary-light">' + esc(catatan) + '</span>'
            + '</div></div>';
    }

    // Cacah tidak punya batas atas seperti persentase, jadi panjang batang
    // diukur terhadap angka terbesar di tabel yang sama. Nol tidak digambar
    // sebagai batang kosong: di halaman ini nol itu kabar baik, bukan ketiadaan.
    function batang(jumlah, puncak) {
        if (!jumlah) {
            return '<span class="text-xs text-success-main fw-medium">bersih</span>';
        }

        var lebar = puncak > 0 ? Math.max(6, jumlah / puncak * 100) : 0;

        return '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
            + '<div class="progress-bar bg-danger-main rounded-pill" role="progressbar"'
            + ' style="width:' + lebar + '%" aria-valuenow="' + jumlah + '"'
            + ' aria-valuemin="0" aria-valuemax="' + puncak + '"></div></div>';
    }

    function puncakDari(list, kunci) {
        return list.reduce(function (maks, r) { return Math.max(maks, r[kunci] || 0); }, 0);
    }

    function render(j) {
        var c = j.sel;
        var p = j.pasangan;

        // Sel nol harus berbunyi, bukan tampil kosong: nol di parameter ini
        // berarti tidak ada pelanggar, dan itu persis targetnya.
        var isi = c.bersih
            ? '<div class="alert alert-success bg-success-focus border-success-main text-success-main'
                + ' radius-8 px-20 py-12 mb-20 d-flex align-items-start gap-2">'
                + '<iconify-icon icon="solar:check-circle-outline" class="icon text-xl flex-shrink-0"></iconify-icon>'
                + '<span class="text-sm">Tidak ada pelanggar overspeed di <strong>' + esc(j.judul.mitra)
                + '</strong> · ' + esc(j.judul.site) + ' pada ' + esc(j.judul.bulan)
                + '. Nol adalah target parameter ini, jadi sel ini sudah sesuai target.</span></div>'
            : '<div class="alert alert-danger bg-danger-focus border-danger-main text-danger-main'
                + ' radius-8 px-20 py-12 mb-20 d-flex align-items-start gap-2">'
                + '<iconify-icon icon="solar:speedometer-outline" class="icon text-xl flex-shrink-0"></iconify-icon>'
                + '<span class="text-sm"><strong>' + num(c.jumlah) + ' karyawan berbeda</strong> di '
                + esc(j.judul.mitra) + ' · ' + esc(j.judul.site) + ' kedapatan overspeed pada '
                + esc(j.judul.bulan) + '. Ini cacah PELANGGAR, bukan cacah pelanggaran: satu orang '
                + 'yang melanggar berkali-kali dalam sebulan tetap terhitung satu.</span></div>';

        // Keempat kartu memperbesar lingkup selangkah demi selangkah: sel ini,
        // pasangan ini sepanjang tahun, se-site bulan ini, lalu seluruh site.
        // Tidak ada kartu "Nilai": parameter ini cacah tanpa penyebut, jadi
        // band Nilai 1-4 tidak bisa diturunkan dan tidak boleh dikarang.
        isi += '<div class="row gy-3 mb-20">'
            + ubin('Pelanggar Bulan Ini', num(c.jumlah),
                c.bersih
                    ? 'tidak ada pelanggar — target nol tercapai'
                    : 'karyawan berbeda (SID unik) yang kedapatan · target nol',
                c.bersih ? 'text-success-main' : 'text-danger-main')
            + ubin('Pasangan Ini, ' + num(p.bulan_count) + ' Bulan', num(p.total),
                p.total
                    ? 'kedapatan di ' + num(p.bulan_kena) + ' bulan, bersih di ' + num(p.bulan_bersih)
                        + ' bulan · terbanyak ' + p.bulan_puncak + ' (' + num(p.puncak) + ')'
                    : 'tidak pernah kedapatan sama sekali sepanjang bulan yang tercakup',
                p.total ? '' : 'text-success-main')
            + ubin('Se-site ' + j.judul.site, num(j.site_bulan.jumlah),
                num(j.site_bulan.mitra_count) + ' perusahaan kedapatan di ' + j.judul.bulan
                    + ' · porsi sel ini ' + pct(c.porsi_site))
            + ubin('Seluruh Site', num(j.semua_bulan.jumlah),
                num(j.semua_bulan.site_count) + ' site kedapatan di ' + j.judul.bulan
                    + ' · porsi sel ini ' + pct(c.porsi_semua))
            + '</div>';

        isi += '<div class="row gy-4 mb-24">'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">Riwayat ' + esc(j.judul.mitra)
            +       ' di ' + esc(j.judul.site) + '</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Apakah bulan ini kebetulan buruk, atau memang berulang</span>'
            +     tabelRiwayat(j)
            +   '</div>'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">Perusahaan di ' + esc(j.judul.site)
            +       ' · ' + esc(j.judul.bulan) + '</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Apakah pelanggarnya milik satu perusahaan atau merata se-site</span>'
            +     tabelSebulan(j)
            +   '</div>'
            + '</div>';

        // Panel PIC approval: sumbu ketiga yang hanya dipunyai parameter ini.
        isi += '<h6 class="text-md fw-semibold mb-4">PIC Approval di ' + esc(j.judul.site) + '</h6>'
            + '<span class="text-xs text-secondary-light d-block mb-12">'
            + 'Tiap PIC terikat satu site, jadi ini rincian di dalam site — bukan pemotongan baru. '
            + 'Daftarnya diambil dari seluruh bulan supaya sel bersih pun tetap menampilkan siapa PIC-nya.'
            + '</span>'
            + tabelPic(j);

        bagian('isi').innerHTML = isi;
        bagian('kaki').textContent = 'Sumbernya hanya menyimpan cacah SID unik per site, PIC, '
            + 'perusahaan, dan bulan — tidak ada daftar orangnya, jadi tidak ada tabel rincian. '
            + 'Karena SID-nya tidak tersimpan, penjumlahan antar bulan bisa menghitung orang yang '
            + 'sama lebih dari sekali dan itu tidak bisa dikurangkan di sini.';
    }

    function tabelRiwayat(j) {
        if (!j.riwayat.length) {
            return '<div class="text-center text-secondary-light py-24">Tidak ada bulan untuk dibandingkan.</div>';
        }

        var puncak = puncakDari(j.riwayat, 'jumlah');

        return '<div class="table-responsive osp-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>Bulan</th><th class="text-end">Pelanggar</th><th style="width:44%">Sebaran</th>'
            + '</tr></thead><tbody>'
            + j.riwayat.map(function (r) {
                return '<tr' + (r.ini ? ' class="bg-primary-50"' : '') + '>'
                    + '<td class="text-sm' + (r.ini ? ' fw-semibold' : '') + '">' + esc(r.bulan)
                    +   (r.ini ? ' <span class="text-xs text-primary-600">(sel ini)</span>' : '') + '</td>'
                    + '<td class="text-end' + (r.jumlah ? ' fw-semibold' : ' text-secondary-light') + '">'
                    +   num(r.jumlah) + '</td>'
                    + '<td>' + batang(r.jumlah, puncak) + '</td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function tabelSebulan(j) {
        if (!j.sebulan.length) {
            return '<div class="text-center text-secondary-light py-24">Tidak ada perusahaan di site ini.</div>';
        }

        var puncak = puncakDari(j.sebulan, 'jumlah');

        return '<div class="table-responsive osp-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>Perusahaan</th><th class="text-end">Pelanggar</th><th style="width:38%">Sebaran</th>'
            + '</tr></thead><tbody>'
            + j.sebulan.map(function (r) {
                return '<tr' + (r.ini ? ' class="bg-primary-50"' : '') + '>'
                    + '<td class="text-sm' + (r.ini ? ' fw-semibold' : '') + '">' + esc(r.mitra)
                    +   (r.ini ? ' <span class="text-xs text-primary-600">(sel ini)</span>' : '') + '</td>'
                    + '<td class="text-end' + (r.jumlah ? ' fw-semibold' : ' text-secondary-light') + '">'
                    +   num(r.jumlah) + '</td>'
                    + '<td>' + batang(r.jumlah, puncak) + '</td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function tabelPic(j) {
        if (!j.pic.length) {
            return '<div class="text-center text-secondary-light py-24">'
                + 'Tidak ada PIC approval yang pernah kedapatan di site ini.</div>';
        }

        return '<div class="table-responsive osp-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>PIC Approval</th>'
            +   '<th class="text-end">Dari ' + esc(j.judul.mitra) + '</th>'
            +   '<th class="text-end">Se-site ' + esc(j.judul.bulan) + '</th>'
            +   '<th class="text-end">Total ' + num(j.pasangan.bulan_count) + ' bulan</th>'
            +   '<th class="text-end">Perusahaan</th>'
            + '</tr></thead><tbody>'
            + j.pic.map(function (r) {
                return '<tr' + (r.ini ? ' class="bg-primary-50"' : '') + '>'
                    + '<td class="text-sm' + (r.ini ? ' fw-semibold' : '') + '">' + esc(r.pic)
                    +   (r.ini ? ' <span class="text-xs text-primary-600">(approval sel ini)</span>' : '') + '</td>'
                    + '<td class="text-end' + (r.sel ? ' fw-semibold text-danger-main' : ' text-secondary-light')
                    +   '">' + num(r.sel) + '</td>'
                    + '<td class="text-end' + (r.bulan_ini ? '' : ' text-secondary-light') + '">'
                    +   num(r.bulan_ini) + '</td>'
                    + '<td class="text-end text-secondary-light">' + num(r.total) + '</td>'
                    + '<td class="text-end text-secondary-light">' + num(r.mitra_count) + '</td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function buka(url, koordinat) {
        // Nomor permintaan menjaga agar jawaban yang datang terlambat untuk sel
        // yang sudah tidak dibuka lagi tidak menimpa isi modal.
        var ini = ++permintaan;

        el.querySelector('#osp-detail-judul').textContent =
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

    var root = document.querySelector('.osp-overview');
    if (!root) { return; }

    var PALETTE = ['#0F172A', '#E0484A', '#FF9F29', '#F2C230', '#8252E9', '#487FFF', '#45B369'];

    var overviewUrl = root.dataset.url;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.osp-filter'));
    var charts = { monthly: null };
    var loaded = false;

    function el(name) {
        return root.querySelector('[data-osp="' + name + '"]');
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap ganti
    // filter, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    if (ospModalDetail && matrixEl && root.dataset.detailUrl) {
        var bukaSel = function (td) {
            ospModalDetail.buka(root.dataset.detailUrl, {
                site: td.dataset.site,
                mitra: td.dataset.mitra,
                month: td.dataset.month,
                bulan: td.dataset.bulan
            });
        };

        matrixEl.addEventListener('click', function (e) {
            var td = e.target.closest('.osp-cell--klik');
            if (td && matrixEl.contains(td)) { bukaSel(td); }
        });

        matrixEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var td = e.target.closest('.osp-cell--klik');
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

    // Nol hijau, dan makin banyak pelanggar makin merah.
    function tierClass(jumlah) {
        if (jumlah <= 0) return 'osp-k0';
        if (jumlah === 1) return 'osp-k1';
        if (jumlah === 2) return 'osp-k2';
        return 'osp-k3';
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
                grad: 'bg-gradient-end-5', icon: 'solar:speedometer-outline', dot: 'bg-danger-main',
                label: 'Total Pelanggar', value: fmtNum(k.temuan),
                foot: k.bulan_terburuk
                    ? 'Terbanyak di ' + escapeHtml(k.bulan_terburuk) + ' (' + fmtNum(k.temuan_terburuk) + ')'
                    : 'Tidak ada pelanggar sama sekali'
            },
            {
                grad: 'bg-gradient-end-2', icon: 'solar:calendar-outline', dot: 'bg-success-main',
                label: 'Bulan Tanpa Pelanggar', value: fmtNum(k.bulan_bersih) + ' / ' + fmtNum(k.bulan_count),
                foot: 'Rata-rata ' + fmtNum(k.rata_per_bulan) + ' pelanggar per bulan · '
                    + fmtNum(k.baris) + ' baris kedapatan'
            },
            {
                grad: 'bg-gradient-end-3', icon: 'solar:buildings-outline', dot: 'bg-warning-main',
                label: 'Perusahaan Terdampak', value: fmtNum(k.mitra_count),
                foot: fmtNum(k.kombinasi) + ' pasangan site &amp; perusahaan'
            },
            {
                grad: 'bg-gradient-end-1', icon: 'solar:user-check-rounded-outline', dot: 'bg-primary-600',
                label: 'PIC Approval', value: fmtNum(k.pic_count),
                foot: fmtNum(k.site_count) + ' site terdampak dalam '
                    + fmtNum(k.bulan_count) + ' bulan yang tercakup'
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

    // ---- Pelanggar per site -------------------------------------------------
    function renderPerSite(list) {
        var host = el('per-site');
        if (!list.length) {
            host.innerHTML = '<p class="text-secondary-light text-sm text-center py-24 mb-0">'
                + 'Tidak ada pelanggar untuk filter ini.</p>';
            return;
        }

        var puncak = Math.max.apply(null, list.map(function (s) { return s.jumlah; })) || 1;

        host.innerHTML = list.map(function (s, i) {
            return '<div class="' + (i ? 'mt-20' : '') + '">'
                + '<div class="d-flex align-items-center justify-content-between mb-8">'
                +   '<span class="text-sm fw-semibold">' + escapeHtml(s.site) + '</span>'
                +   '<span class="text-sm fw-medium text-secondary-light">' + fmtNum(s.jumlah) + ' pelanggar</span>'
                + '</div>'
                + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                +   '<div class="progress-bar bg-danger-main rounded-pill" role="progressbar"'
                +     ' style="width:' + (s.jumlah / puncak * 100) + '%" aria-valuenow="' + s.jumlah + '"'
                +     ' aria-valuemin="0" aria-valuemax="' + puncak + '"></div>'
                + '</div>'
                + '<span class="text-xs text-secondary-light">' + fmtPct(s.percent)
                +   ' dari seluruh pelanggar · ' + fmtNum(s.lawan) + ' perusahaan</span>'
                + '</div>';
        }).join('');
    }

    // ---- Peringkat perusahaan ----------------------------------------------
    function renderPerMitra(list) {
        var body = el('per-mitra');
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-secondary-light py-24">'
                + 'Tidak ada pelanggar untuk filter ini.</td></tr>';
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

    // ---- Pelanggar per PIC approval -----------------------------------------
    function renderPerPic(list) {
        var host = el('per-pic');
        if (!list.length) {
            host.innerHTML = '<div class="col-12 text-center text-secondary-light py-24">'
                + 'Tidak ada pelanggar untuk filter ini.</div>';
            return;
        }

        var puncak = Math.max.apply(null, list.map(function (p) { return p.jumlah; })) || 1;

        host.innerHTML = list.map(function (p) {
            return '<div class="col-xxl-4 col-md-6">'
                + '<div class="border input-form-light radius-8 p-16 h-100">'
                +   '<div class="d-flex align-items-center justify-content-between gap-2 mb-12">'
                +     '<span class="text-md fw-semibold">' + escapeHtml(p.pic) + '</span>'
                +     '<span class="bg-neutral-200 text-secondary-light px-8 py-2 rounded-pill'
                +       ' fw-medium text-xs">' + escapeHtml(p.site) + '</span>'
                +   '</div>'
                +   '<h6 class="mb-8 fw-semibold">' + fmtNum(p.jumlah) + ' pelanggar</h6>'
                +   '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px mb-8">'
                +     '<div class="progress-bar bg-danger-main rounded-pill" role="progressbar"'
                +       ' style="width:' + (p.jumlah / puncak * 100) + '%" aria-valuenow="' + p.jumlah + '"'
                +       ' aria-valuemin="0" aria-valuemax="' + puncak + '"></div>'
                +   '</div>'
                +   '<span class="text-sm text-secondary-light">' + fmtPct(p.percent)
                +     ' dari seluruhnya · ' + fmtNum(p.mitra_count) + ' perusahaan · '
                +     fmtNum(p.bulan_count) + ' bulan</span>'
                + '</div></div>';
        }).join('');
    }

    function renderLegend() {
        var items = [
            { color: '#16A34A', label: 'tidak ada pelanggar' },
            { color: '#F2C230', label: '1 pelanggar' },
            { color: '#F08C2E', label: '2 pelanggar' },
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

        var head = '<tr><th class="osp-site">SITE</th><th class="osp-mitra">PERUSAHAAN</th>'
            + '<th>TOTAL</th><th>TREND</th>';
        months.forEach(function (m, i) {
            head += '<th class="' + (i === months.length - 1 ? 'osp-th-last' : '') + '">'
                + escapeHtml(m.label) + '</th>';
        });
        thead.innerHTML = head + '</tr>';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="' + (months.length + 4) + '" class="text-center py-24 text-secondary-light">'
                + 'Tidak ada pelanggar untuk filter ini — dan itu kabar baik.</td></tr>';
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
                + (lewati[i] ? '' : '<td class="osp-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="osp-mitra">' + escapeHtml(row.mitra) + '</td>'
                + '<td class="osp-total" title="' + escapeHtml(row.bulan_kena + ' bulan kedapatan, '
                    + row.bulan_bersih + ' bulan bersih') + '">' + fmtNum(row.total) + '</td>';

            if (row.trend === 'up') {
                html += '<td class="osp-trend--up" title="Bertambah dari bulan sebelumnya">&uarr;</td>';
            } else if (row.trend === 'down') {
                html += '<td class="osp-trend--down" title="Berkurang dari bulan sebelumnya">&darr;</td>';
            } else if (row.trend === 'flat') {
                html += '<td class="osp-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
            } else {
                html += '<td class="text-secondary-light">–</td>';
            }

            // Variabel sengaja dinamai m: i di luar sudah dipakai sebagai index
            // baris untuk perhitungan rowspan site.
            row.cells.forEach(function (jumlah, m) {
                var tip = row.site + ' · ' + row.mitra + ' · ' + months[m].label + ': '
                    + (jumlah === 0 ? 'tidak ada pelanggar' : fmtNum(jumlah) + ' pelanggar');

                // Koordinat sel dibawa di atribut, bukan ditebak dari posisi
                // DOM: urutan baris berubah mengikuti pengurutan per site.
                html += '<td class="osp-cell osp-cell--klik ' + tierClass(jumlah) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-mitra="' + escapeHtml(row.mitra) + '"'
                    + ' data-month="' + months[m].number + '"'
                    + ' data-bulan="' + escapeHtml(months[m].label) + '"'
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
                title: { text: 'Pelanggar', style: { fontSize: '11px' } },
                labels: { formatter: function (v) { return fmtNum(Math.round(v)); } }
            },
            legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            tooltip: {
                shared: true,
                // WAJIB eksplisit: kombinasi shared + intersect melempar error
                // sehingga grafiknya gagal dirender sama sekali.
                intersect: false,
                y: { formatter: function (v) { return fmtNum(v) + ' pelanggar'; } }
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
                console.error('Deviasi Overspeed: panel "' + label + '" gagal dirender', err);
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
                safe('per-pic', function () { renderPerPic(json.per_pic || []); });
                safe('monthly', function () { renderMonthlyChart(json.monthly); });
                safe('catatan', function () { renderCatatan(json.catatan); });

                var k = json.kpi;
                el('status').textContent = fmtNum(k.temuan) + ' pelanggar · ' + k.kombinasi
                    + ' pasangan site/perusahaan · ' + k.pic_count + ' PIC · '
                    + k.bulan_count + ' bulan, ' + k.bulan_bersih + ' di antaranya bersih';
                loaded = true;
            })
            .catch(function (err) {
                el('status').textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Ringkasan Deviasi Overspeed: gagal memuat', err);
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

    var tab = document.querySelector('#osp-tab-ringkasan');
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

    var root = document.querySelector('.osp-datatable');
    if (!root) { return; }

    var tableEl = root.querySelector('[data-osp="table"]');
    if (!tableEl || typeof DataTable === 'undefined') { return; }
    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.ospd-filter'));
    var hintEl = root.querySelector('[data-osp="hint"]');

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
                    console.error('Deviasi Overspeed: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'site' },
            { data: 'pic' },
            { data: 'mitra' },
            // Bulan tersimpan sebagai nama bulan Inggris, jadi mengurutkannya
            // di SQL hanya menghasilkan urutan abjad yang menyesatkan.
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
            infoEmpty: 'Tidak ada kedapatan',
            infoFiltered: '(disaring dari _MAX_ baris)',
            search: 'Cari:',
            zeroRecords: 'Tidak ada kedapatan untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    filterEls.forEach(function (node) {
        node.addEventListener('change', function () { table.ajax.reload(); });
    });

    root.querySelector('[data-osp="reset"]').addEventListener('click', function () {
        filterEls.forEach(function (node) { node.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    root.querySelectorAll('[data-osp="export"]').forEach(function (btn) {
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
    var tab = document.querySelector('#osp-tab-data');
    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        });
    }
})();
</script>
@endsection
