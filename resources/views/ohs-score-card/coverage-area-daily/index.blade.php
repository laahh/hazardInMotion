@extends('ohs-score-card.layouts.app')

@section('title', 'Coverage Daily')

@section('css')
<style>

  /* Penanda target pada progress bar. Tidak ada padanannya di WowDash,
     jadi ditulis sendiri: satu garis tipis di posisi persentase target. */
  .cvd-track { position: relative; overflow: visible; }
  .cvd-track__target {
    position: absolute; top: -3px; bottom: -3px; width: 2px;
    background: var(--text-primary-light, #0F172A); opacity: .45;
  }

  /* ---- Matriks capaian bulanan ---- */
  .cvd-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .cvd-matrix {
    width: 100%; min-width: 820px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .cvd-matrix th, .cvd-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .cvd-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .cvd-matrix thead th.cvd-th-last { background: #2E90FA !important; color: #fff !important; }
  /* Sel site di-merge dengan rowspan, jadi diratakan ke tengah secara vertikal
     supaya labelnya berada di tengah blok site-nya. */
  .cvd-matrix .cvd-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .cvd-matrix .cvd-pic {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 96px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .cvd-matrix thead .cvd-site, .cvd-matrix thead .cvd-pic { z-index: 4; background: #F8FAFC; }
  .cvd-matrix .cvd-avg { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  /* Kinerja: naik berarti membaik, jadi panah atas hijau. */
  .cvd-matrix .cvd-trend--up { color: #16A34A; font-weight: 800; }
  .cvd-matrix .cvd-trend--down { color: #DC2626; font-weight: 800; }
  .cvd-matrix .cvd-trend--flat { color: #94A3B8; font-weight: 800; }
  .cvd-matrix .cvd-cell {
    font-weight: 700; color: #fff; min-width: 58px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    font-variant-numeric: tabular-nums;
  }
  .cvd-matrix .cvd-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(5,150,105,.35);
    position: relative; z-index: 1;
  }
  .cvd-matrix .cvd-empty { background: #F1F5F9; color: #CBD5E1 !important; border-radius: 6px; }

  /* Hanya sel berisi angka yang bisa dibuka rinciannya. */
  .cvd-matrix .cvd-cell--klik { cursor: pointer; }
  .cvd-matrix .cvd-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Tabel konteks di dalam modal digulir sendiri agar modalnya tidak memanjang. */
  .cvd-modal-scroll { max-height: 34vh; overflow: auto; }
  .cvd-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }
  /* Gradasi persentase: angka besar hijau, karena di sini tinggi berarti baik. */
  /* Warna band resmi. Mode Persentase dan Nilai memakai ambang yang sama,
     jadi satu sel tidak pernah berganti warna ketika modenya diganti. */
  .cvd-t1, .cvd-n1 { background: #FF0000; }
  .cvd-t2, .cvd-n2 { background: #FFC000; color: #1F2937 !important; }
  .cvd-t3, .cvd-n3 { background: #FFFF00; color: #1F2937 !important; }
  .cvd-t4, .cvd-n4 { background: #92D050; color: #1F2937 !important; }

  /* Lencana dan batang memakai band yang sama persis dengan sel. */
  .cvd-b1 { background: #FFE5E5; color: #B91C1C !important; }
  .cvd-b2 { background: #FFF2CC; color: #92400E !important; }
  .cvd-b3 { background: #FFFBCC; color: #854D0E !important; }
  .cvd-b4 { background: #E8F5DC; color: #3F6212 !important; }
  .cvd-bar1 { background: #FF0000; }
  .cvd-bar2 { background: #FFC000; }
  .cvd-bar3 { background: #FFFF00; }
  .cvd-bar4 { background: #92D050; }
  /* Mode Nilai: 4 band resmi, warnanya senada dengan kartu perusahaan. */

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Coverage Daily</h6>
    <div class="text-secondary-light text-sm mt-4">
      Persentase lokasi-hari yang tercakup pengawasan harian, per PIC maincon di tiap site
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
    <li class="fw-medium text-primary-600">Coverage Daily</li>
  </ul>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="cvd-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="cvd-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#cvd-pane-ringkasan"
            type="button" role="tab" aria-controls="cvd-pane-ringkasan" aria-selected="true">
      Ringkasan
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="cvd-tab-data"
            data-bs-toggle="pill" data-bs-target="#cvd-pane-data"
            type="button" role="tab" aria-controls="cvd-pane-data" aria-selected="false">
      Data
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="cvd-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.coverage-area-daily.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="cvd-pane-data" role="tabpanel">
    @include('ohs-score-card.coverage-area-daily.partials._data')
  </div>
</div>

{{-- Modal rincian satu sel. Ditaruh di luar tab pane supaya tidak ikut
     tersembunyi saat berpindah tab. --}}
<div class="modal fade" id="cvd-detail-modal" tabindex="-1" aria-labelledby="cvd-detail-judul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content radius-12">
      <div class="modal-header border-bottom py-16 px-24">
        <div>
          <h6 class="modal-title text-lg fw-semibold mb-0" id="cvd-detail-judul">Rincian Bulan</h6>
          <span class="text-sm text-secondary-light" data-cvm="subjudul"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24"><div data-cvm="isi"></div></div>
      <div class="modal-footer border-top py-12 px-24">
        <span class="text-sm text-secondary-light me-auto" data-cvm="kaki"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection


@section('page-scripts')
<script>
// ---- Tab Ringkasan ----------------------------------------------------------
// ---- Modal rincian satu sel matriks -----------------------------------------
var cvdModalDetail = (function () {
    'use strict';

    var el = document.getElementById('cvd-detail-modal');
    if (!el) { return null; }

    var bagian = function (n) { return el.querySelector('[data-cvm="' + n + '"]'); };
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

    /** Batang mini agar perbandingan antar baris terbaca tanpa membaca angkanya. */
    function batang(persen, target) {
        var p = persen === null ? 0 : Math.min(100, persen);
        var warna = persen === null ? 'bg-neutral-400'
            : (persen >= target ? 'bg-success-main' : persen >= target - 10 ? 'bg-warning-main' : 'bg-danger-main');
        return '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
            + '<div class="progress-bar ' + warna + ' rounded-pill" role="progressbar"'
            + ' style="width:' + p + '%" aria-valuenow="' + Math.round(p) + '"'
            + ' aria-valuemin="0" aria-valuemax="100"></div></div>';
    }

    function render(j) {
        var c = j.sel;
        var target = j.target;
        var capai = c.persen === null ? '' : (c.memenuhi_target ? 'text-success-main' : 'text-danger-main');

        // Keempat kartu satu keluarga: Tercakup + Belum = Terdaftar, dan
        // Persentase = Tercakup / Terdaftar. Semuanya dari tabel ringkasan yang
        // sama dengan matriksnya, jadi tidak mungkin bertentangan dengan selnya.
        var isi = '<div class="row gy-3 mb-20">'
            + ubin('Terdaftar', num(c.terdaftar), 'lokasi-hari yang harus dicakup')
            + ubin('Tercakup', num(c.tercover), 'sudah dikunjungi', 'text-success-main')
            + ubin('Belum Tercakup', num(c.belum), 'luput dari pengawasan harian',
                   c.belum ? 'text-danger-main' : '')
            + ubin('Persentase', pct(c.persen),
                   c.terdaftar
                       ? num(c.tercover) + ' dibagi ' + num(c.terdaftar) + ' · target ' + target + '%'
                       : 'tidak ada lokasi terdaftar',
                   capai)
            + '</div>';

        if (j.site && j.site.terdaftar) {
            isi += '<p class="text-sm text-secondary-light mb-20">Seluruh site ' + esc(j.judul.site)
                + ' pada ' + esc(j.judul.bulan) + ': <span class="fw-semibold">' + pct(j.site.persen)
                + '</span> (' + num(j.site.tercover) + ' dari ' + num(j.site.terdaftar) + ' lokasi-hari).</p>';
        }

        isi += '<div class="row gy-4">'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">Riwayat ' + esc(j.judul.pic) + ' di ' + esc(j.judul.site) + '</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Apakah bulan ini kebetulan buruk, atau memang begitu terus</span>'
            +     tabelRiwayat(j, target)
            +   '</div>'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">Seluruh PIC di ' + esc(j.judul.site)
            +       ' · ' + esc(j.judul.bulan) + '</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Apakah masalahnya milik satu PIC atau menyeluruh</span>'
            +     tabelSebulan(j, target)
            +   '</div>'
            + '</div>';

        bagian('isi').innerHTML = isi;
        bagian('kaki').textContent = 'Parameter ini belum punya tabel rincian per lokasi, '
            + 'jadi yang ditampilkan konteks di sekeliling sel — semuanya dari sumber yang sama dengan matriks.';
    }

    function tabelRiwayat(j, target) {
        if (!j.riwayat.length) {
            return '<div class="text-center text-secondary-light py-24">Tidak ada bulan lain untuk dibandingkan.</div>';
        }

        return '<div class="table-responsive cvd-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>Bulan</th><th class="text-end">Tercakup</th>'
            +   '<th class="text-end">Terdaftar</th><th style="width:34%">Capaian</th>'
            + '</tr></thead><tbody>'
            + j.riwayat.map(function (r) {
                var ini = r.bulan === j.judul.bulan + ' 2026' || r.bulan.indexOf(j.judul.bulan) === 0;
                return '<tr' + (ini ? ' class="bg-primary-50"' : '') + '>'
                    + '<td class="text-sm' + (ini ? ' fw-semibold' : '') + '">' + esc(r.bulan) + '</td>'
                    + '<td class="text-end">' + num(r.tercover) + '</td>'
                    + '<td class="text-end text-secondary-light">' + num(r.terdaftar) + '</td>'
                    + '<td>' + batang(r.persen, target)
                    +   '<span class="text-xs text-secondary-light">' + pct(r.persen) + '</span></td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function tabelSebulan(j, target) {
        if (!j.sebulan.length) {
            return '<div class="text-center text-secondary-light py-24">Tidak ada PIC lain di site ini.</div>';
        }

        return '<div class="table-responsive cvd-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>PIC</th><th class="text-end">Tercakup</th>'
            +   '<th class="text-end">Terdaftar</th><th style="width:34%">Capaian</th>'
            + '</tr></thead><tbody>'
            + j.sebulan.map(function (r) {
                return '<tr' + (r.ini ? ' class="bg-primary-50"' : '') + '>'
                    + '<td class="text-sm' + (r.ini ? ' fw-semibold' : '') + '">' + esc(r.pic)
                    +   (r.ini ? ' <span class="text-xs text-primary-600">(sel ini)</span>' : '') + '</td>'
                    + '<td class="text-end">' + num(r.tercover) + '</td>'
                    + '<td class="text-end text-secondary-light">' + num(r.terdaftar) + '</td>'
                    + '<td>' + batang(r.persen, target)
                    +   '<span class="text-xs text-secondary-light">' + pct(r.persen) + '</span></td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function buka(url, koordinat) {
        // Nomor permintaan menjaga agar jawaban yang datang terlambat untuk sel
        // yang sudah tidak dibuka lagi tidak menimpa isi modal.
        var ini = ++permintaan;

        el.querySelector('#cvd-detail-judul').textContent =
            'Rincian ' + koordinat.bulan + ' · ' + koordinat.site;
        bagian('subjudul').textContent = 'PIC ' + koordinat.pic;
        bagian('kaki').textContent = '';
        bagian('isi').innerHTML = '<div class="text-center text-secondary-light py-40">'
            + '<div class="spinner-border spinner-border-sm text-primary-600 me-2" role="status"></div>'
            + 'Memuat rincian…</div>';

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }

        var q = new URLSearchParams({
            site: koordinat.site, pic: koordinat.pic, month: koordinat.month
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

(function () {
    'use strict';

    var root = document.querySelector('.cvd-overview');
    if (!root) { return; }

    // Warna seri grafik diambil dari palet WowDash yang sudah dipakai
    // dashboard lain di aplikasi ini, bukan palet baru.
    var PALETTE = ['#487FFF', '#45B369', '#FF9F29', '#EF4A00', '#8252E9', '#00B8F2', '#E0484A'];

    var overviewUrl = root.dataset.url;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.cvd-filter'));
    var charts = { monthly: null };
    var loaded = false;

    // 'persen' atau 'nilai'. Payload terakhir disimpan supaya mengganti mode
    // cukup menggambar ulang matriks, tanpa memanggil server lagi.
    var matrixMode = 'persen';
    var lastPayload = null;

    function el(name) {
        return root.querySelector('[data-cvd="' + name + '"]');
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

    // BAND SELALU DITURUNKAN DARI PERSENTASE, tidak pernah dari nilai yang
    // sudah dibulatkan: pembulatan dua desimal bisa menyeberangi batas band.
    // Ambangnya sama persis dengan SCORE_BANDS di controller.
    function bandPersen(pct) {
        var v = Number(pct);
        if (!isFinite(v)) { return 0; }
        if (v >= 98) { return 4; }
        if (v >= 96) { return 3; }
        if (v >= 94) { return 2; }
        return 1;
    }

    /** Nilai ditampilkan dengan koma, mis. "3,50". */
    function fmtNilai(nilai) {
        if (nilai === null || nilai === undefined || nilai === '') { return '–'; }
        return Number(nilai).toLocaleString('id-ID', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    function tierClass(pct) {
        return { 1: 'cvd-t1', 2: 'cvd-t2', 3: 'cvd-t3', 4: 'cvd-t4' }[bandPersen(pct)] || 'cvd-empty';
    }

    function nilaiClass(pct) {
        return { 1: 'cvd-n1', 2: 'cvd-n2', 3: 'cvd-n3', 4: 'cvd-n4' }[bandPersen(pct)] || 'cvd-empty';
    }

    // Kelas badge mengikuti sistem warna WowDash (bg-*-focus + text-*-main),
    // bukan warna inline, supaya ikut tema dan konsisten dengan modul lain.
    function nilaiBadgeClass(pct) {
        var v = Number(pct);
        var b = !isFinite(v) ? 0 : (v >= 98 ? 4 : v >= 96 ? 3 : v >= 94 ? 2 : 1);

        return {
            1: 'cvd-b1', 2: 'cvd-b2', 3: 'cvd-b3', 4: 'cvd-b4'
        }[b] || 'bg-neutral-200 text-secondary-light';
    }

    function nilaiBarClass(pct) {
        var v = Number(pct);
        var b = !isFinite(v) ? 0 : (v >= 98 ? 4 : v >= 96 ? 3 : v >= 94 ? 2 : 1);

        return {
            1: 'cvd-bar1', 2: 'cvd-bar2', 3: 'cvd-bar3', 4: 'cvd-bar4'
        }[b] || 'bg-neutral-400';
    }

    function cellClass(cell) {
        return (matrixMode === 'nilai' ? nilaiClass : tierClass)(cell.pct);
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap ganti
    // filter atau mode, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    if (cvdModalDetail && matrixEl && root.dataset.detailUrl) {
        var bukaSel = function (td) {
            cvdModalDetail.buka(root.dataset.detailUrl, {
                site: td.dataset.site,
                pic: td.dataset.pic,
                month: td.dataset.month,
                bulan: td.dataset.bulan
            });
        };

        matrixEl.addEventListener('click', function (e) {
            var td = e.target.closest('.cvd-cell--klik');
            if (td && matrixEl.contains(td)) { bukaSel(td); }
        });

        matrixEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var td = e.target.closest('.cvd-cell--klik');
            if (!td || !matrixEl.contains(td)) { return; }
            e.preventDefault();
            bukaSel(td);
        });
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
                grad: 'bg-gradient-end-1', icon: 'solar:map-outline', dot: 'bg-primary-600',
                label: 'Coverage Berbobot',
                value: k.rata === null ? '–' : fmtPct(k.rata),
                foot: k.rata === null
                    ? 'Belum ada data'
                    : '<span class="' + nilaiBadgeClass(k.nilai) + ' px-1 rounded-2 fw-medium text-sm">Nilai '
                        + k.nilai + '</span> '
                        + (k.memenuhi_target ? 'Memenuhi' : 'Belum memenuhi') + ' target ' + k.target + '%'
            },
            {
                grad: 'bg-gradient-end-2', icon: 'solar:check-circle-outline', dot: 'bg-success-main',
                label: 'Memenuhi Target', value: fmtNum(k.memenuhi) + ' / ' + fmtNum(k.kombinasi),
                foot: 'Pasangan site &amp; PIC yang capaiannya ≥ ' + k.target + '%'
                    + (k.kombinasi_kosong
                        ? ' · ' + fmtNum(k.kombinasi_kosong) + ' pasangan lain belum berdata'
                        : '')
            },
            {
                grad: 'bg-gradient-end-5', icon: 'solar:arrow-down-outline', dot: 'bg-danger-main',
                label: 'Capaian Terendah',
                value: k.terendah === null ? '–' : fmtPct(k.terendah),
                foot: k.tertinggi === null ? 'Belum ada data' : 'Tertinggi ' + fmtPct(k.tertinggi)
            },
            {
                grad: 'bg-gradient-end-3', icon: 'solar:calendar-outline', dot: 'bg-yellow',
                label: 'Cakupan', value: fmtNum(k.bulan_count) + ' bulan',
                foot: fmtNum(k.site_count) + ' site, ' + fmtNum(k.pic_count) + ' PIC maincon · '
                    + fmtNum(k.sel_terisi) + ' sel terisi'
                    + (k.sel_kosong ? ', ' + fmtNum(k.sel_kosong) + ' kosong' : '')
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

    // ---- Capaian per site ---------------------------------------------------
    function renderPerSite(list) {
        var host = el('per-site');
        if (!list.length) {
            host.innerHTML = '<p class="text-secondary-light text-sm text-center py-24 mb-0">Tidak ada data.</p>';
            return;
        }
        host.innerHTML = list.map(function (s, i) {
            return '<div class="' + (i ? 'mt-20' : '') + '">'
                + '<div class="d-flex align-items-center justify-content-between mb-8">'
                +   '<span class="text-sm fw-semibold">' + escapeHtml(s.site) + '</span>'
                +   '<span class="text-sm fw-medium text-secondary-light">' + fmtPct(s.percent) + '</span>'
                + '</div>'
                + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px cvd-track"'
                +   ' title="Target ' + s.target + '%">'
                +   '<div class="progress-bar ' + nilaiBarClass(s.nilai) + ' rounded-pill" role="progressbar"'
                +     ' style="width:' + Math.min(100, s.percent) + '%" aria-valuenow="' + Math.round(s.percent) + '"'
                +     ' aria-valuemin="0" aria-valuemax="100"></div>'
                +   '<span class="cvd-track__target" style="left:' + s.target + '%"></span>'
                + '</div>'
                + '<span class="text-xs text-secondary-light">' + fmtNum(s.jumlah)
                +   ' PIC · ' + fmtNum(s.tercover) + ' dari ' + fmtNum(s.terdaftar) + ' lokasi-hari</span>'
                + '</div>';
        }).join('');
    }

    // ---- Capaian per perusahaan --------------------------------------------
    function renderPerPic(list) {
        var host = el('per-pic');
        if (!list.length) {
            host.innerHTML = '<div class="col-12 text-center text-secondary-light py-24">'
                + 'Tidak ada data untuk filter ini.</div>';
            return;
        }
        host.innerHTML = list.map(function (p) {
            return '<div class="col-xxl-4 col-md-6">'
                + '<div class="border input-form-light radius-8 p-16 h-100">'
                +   '<div class="d-flex align-items-center justify-content-between gap-2 mb-12">'
                +     '<span class="text-md fw-semibold">' + escapeHtml(p.pic) + '</span>'
                +     '<span class="' + nilaiBadgeClass(p.nilai) + ' px-8 py-2 rounded-pill fw-medium text-xs">Nilai '
                +       p.nilai + '</span>'
                +   '</div>'
                +   '<h6 class="mb-8 fw-semibold">' + fmtPct(p.percent) + '</h6>'
                +   '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px mb-8">'
                +     '<div class="progress-bar ' + nilaiBarClass(p.nilai) + ' rounded-pill" role="progressbar"'
                +       ' style="width:' + Math.min(100, p.percent) + '%" aria-valuenow="' + Math.round(p.percent) + '"'
                +       ' aria-valuemin="0" aria-valuemax="100"></div>'
                +   '</div>'
                +   '<span class="text-sm text-secondary-light">' + fmtNum(p.jumlah) + ' site · '
                +     fmtNum(p.tercover) + ' dari ' + fmtNum(p.terdaftar) + ' lokasi-hari</span>'
                + '</div></div>';
        }).join('');
    }

    // ---- Perlu perhatian ----------------------------------------------------
    function renderTerendah(list) {
        var body = el('terendah');
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>';
            return;
        }
        body.innerHTML = list.map(function (t) {
            return '<tr>'
                + '<td>'
                +   '<span class="text-md fw-semibold d-block">' + escapeHtml(t.site) + '</span>'
                +   '<span class="text-sm text-secondary-light">' + escapeHtml(t.pic)
                +     ' · ' + fmtNum(t.tercover) + '/' + fmtNum(t.terdaftar) + ' lokasi-hari</span>'
                + '</td>'
                + '<td class="text-center"><span class="' + nilaiBadgeClass(t.nilai)
                +   ' px-8 py-2 rounded-pill fw-medium text-xs">' + t.nilai + '</span></td>'
                + '<td class="text-end fw-medium">' + fmtPct(t.percent) + '</td>'
                + '</tr>';
        }).join('');
    }

    /** Legenda ikut mode: gradasi persentase, atau 4 band Nilai. */
    function renderLegend() {
        var items = matrixMode === 'nilai'
                        ? [
                { color: '#FF0000', label: 'Nilai 1 · <94%' },
                { color: '#FFC000', label: 'Nilai 2 · 94–<96%' },
                { color: '#FFFF00', label: 'Nilai 3 · 96–<98%' },
                { color: '#92D050', label: 'Nilai 4 · 98–100%' }
            ]
            : [
                { color: '#FF0000', label: '<94%' },
                { color: '#FFC000', label: '94–<96%' },
                { color: '#FFFF00', label: '96–<98%' },
                { color: '#92D050', label: '98–100%' }
            ];

        el('legend').innerHTML = items.map(function (it) {
            return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                + '<span class="rounded-1" style="width:14px;height:14px;background:' + it.color + ';"></span>'
                + escapeHtml(it.label) + '</span>';
        }).join('');

        el('matrix-subtitle').textContent = matrixMode === 'nilai'
            ? 'Nilai 1–4 dari coverage harian, tiap PIC maincon di tiap site'
            : 'Persentase lokasi-hari tercakup, tiap PIC maincon di tiap site';
    }

    function renderMatrix(months, rows) {
        var table = el('matrix');
        var thead = table.querySelector('thead');
        var tbody = table.querySelector('tbody');

        var head = '<tr><th class="cvd-site">SITE</th><th class="cvd-pic">PERUSAHAAN</th>'
            + '<th>RATA</th><th>TREND</th>';
        months.forEach(function (m, i) {
            head += '<th class="' + (i === months.length - 1 ? 'cvd-th-last' : '') + '">'
                + escapeHtml(m.label) + '</th>';
        });
        thead.innerHTML = head + '</tr>';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="' + (months.length + 4) + '" class="text-center py-24 text-secondary-light">'
                + 'Tidak ada data untuk filter ini.</td></tr>';
            return;
        }

        // Sel site digabung dengan rowspan: satu sel untuk semua perusahaan di
        // site yang sama. Baris sudah dikelompokkan per site dari server, jadi
        // cukup menghitung panjang blok yang berurutan.
        var span = {};   // index baris pertama tiap blok -> jumlah baris
        var lewati = {}; // index baris yang tidak menulis sel site
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
                + (lewati[i] ? '' : '<td class="cvd-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="cvd-pic">' + escapeHtml(row.pic) + '</td>';

            if (row.average === null) {
                html += '<td class="cvd-avg" title="Belum ada data">–</td>';
            } else {
                var tipRata = fmtPct(row.average) + ' · Nilai ' + fmtNilai(row.nilai) + ' (' + row.nilai_band + ')'
                    + ' · ' + fmtNum(row.tercover) + ' dari ' + fmtNum(row.terdaftar) + ' lokasi-hari'
                    + ' · ' + row.bulan_terisi + ' bulan';
                html += '<td class="cvd-avg" title="' + escapeHtml(tipRata) + '">'
                    + (matrixMode === 'nilai' ? fmtNilai(row.nilai) : fmtPct(row.average)) + '</td>';
            }

            if (row.trend === 'up') {
                html += '<td class="cvd-trend--up" title="Naik dari bulan sebelumnya">&uarr;</td>';
            } else if (row.trend === 'down') {
                html += '<td class="cvd-trend--down" title="Turun dari bulan sebelumnya">&darr;</td>';
            } else if (row.trend === 'flat') {
                html += '<td class="cvd-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
            } else {
                html += '<td class="text-secondary-light">–</td>';
            }

            // Variabel sengaja dinamai m, bukan i: i di luar sudah dipakai
            // sebagai index baris untuk perhitungan rowspan site.
            row.cells.forEach(function (cell, m) {
                if (cell === null) {
                    html += '<td class="cvd-empty" title="' + escapeHtml(months[m].label)
                        + ': belum ada data">–</td>';
                    return;
                }

                // Tooltip selalu memuat kedua angka, apa pun mode tampilannya,
                // supaya berganti mode tidak menghilangkan informasi.
                var tip = row.site + ' · ' + row.pic + ' · ' + months[m].label + ': '
                    + fmtPct(cell.pct) + ' · Nilai ' + fmtNilai(cell.nilai) + ' (' + cell.nilai_band + ')'
                    + ' · ' + fmtNum(cell.tercover) + ' dari ' + fmtNum(cell.terdaftar) + ' lokasi-hari';

                // Koordinat sel dibawa di atribut, bukan ditebak dari posisi
                // DOM: urutan baris berubah mengikuti pengurutan per site.
                html += '<td class="cvd-cell cvd-cell--klik ' + cellClass(cell) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-pic="' + escapeHtml(row.pic) + '"'
                    + ' data-month="' + months[m].number + '"'
                    + ' data-bulan="' + escapeHtml(months[m].label) + '"'
                    + ' title="' + escapeHtml(tip + ' · klik untuk rincian') + '">'
                    + (matrixMode === 'nilai' ? fmtNilai(cell.nilai) : Math.round(cell.pct) + '%')
                    + '</td>';
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
            stroke: { curve: 'smooth', width: 3 },
            markers: { size: 4, hover: { size: 5 } },
            dataLabels: { enabled: false },
            xaxis: {
                categories: payload.labels,
                labels: { style: { fontSize: '11px' } }
            },
            yaxis: {
                min: 0, max: 100,
                labels: { formatter: function (v) { return Math.round(v) + '%'; } }
            },
            legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
            grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
            annotations: {
                yaxis: [{
                    y: {{ $target }},
                    borderColor: '#0F172A',
                    strokeDashArray: 4,
                    label: {
                        text: 'Target {{ $target }}%',
                        style: { fontSize: '10px', background: '#0F172A', color: '#fff' }
                    }
                }]
            },
            tooltip: {
                shared: true,
                // WAJIB eksplisit: kombinasi shared + intersect melempar error
                // sehingga grafiknya gagal dirender sama sekali.
                intersect: false,
                y: { formatter: function (v) { return v === null ? 'belum ada data' : fmtPct(v); } }
            }
        });
        charts.monthly.render();
    }

    /** Keterangan dari server, atau disembunyikan kalau tidak ada. */
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
                console.error('Coverage Daily: panel "' + label + '" gagal dirender', err);
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
                lastPayload = json;

                safe('kpi', function () { renderKpi(json.kpi); });
                safe('legend', renderLegend);
                safe('matrix', function () { renderMatrix(json.months || [], json.matrix || []); });
                safe('per-site', function () { renderPerSite(json.per_site || []); });
                safe('per-pic', function () { renderPerPic(json.per_pic || []); });
                safe('terendah', function () { renderTerendah(json.terendah || []); });
                safe('monthly', function () { renderMonthlyChart(json.monthly); });
                safe('catatan', function () { renderCatatan(json.catatan); });

                var k = json.kpi;
                el('status').textContent = (k.rata === null ? 'belum ada data' : fmtPct(k.rata) + ' tercakup')
                    + ' · ' + fmtNum(k.tercover) + ' dari ' + fmtNum(k.terdaftar) + ' lokasi-hari · '
                    + k.kombinasi + ' kombinasi site/PIC · ' + k.bulan_count + ' bulan';
                loaded = true;
            })
            .catch(function (err) {
                el('status').textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Ringkasan Coverage Daily: gagal memuat', err);
                }
            });
    }

    filterEls.forEach(function (node) {
        node.addEventListener('change', load);
    });

    // Ganti mode hanya menggambar ulang dari payload terakhir, tidak ada
    // permintaan baru ke server, karena angka Nilai sudah ikut dikirim.
    root.querySelectorAll('.cvd-switch__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.mode === matrixMode) { return; }

            matrixMode = btn.dataset.mode;

            root.querySelectorAll('.cvd-switch__btn').forEach(function (b) {
                // Kelas aktifnya 'active' (bawaan nav-pills WowDash),
                // bukan kelas buatan sendiri.
                b.classList.toggle('active', b.dataset.mode === matrixMode);
            });

            renderLegend();

            if (lastPayload) {
                renderMatrix(lastPayload.months || [], lastPayload.matrix || []);
            }
        });
    });

    el('reset').addEventListener('click', function () {
        filterEls.forEach(function (node) { node.value = ''; });
        load();
    });

    load();

    var tab = document.querySelector('#cvd-tab-ringkasan');
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

    var root = document.querySelector('.cvd-datatable');
    if (!root) { return; }

    var tableEl = root.querySelector('[data-cvd="table"]');
    if (!tableEl || typeof DataTable === 'undefined') { return; }
    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.cvdd-filter'));
    var hintEl = root.querySelector('[data-cvd="hint"]');

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

    function nilaiBadgeClass(pct) {
        var v = Number(pct);
        var b = !isFinite(v) ? 0 : (v >= 98 ? 4 : v >= 96 ? 3 : v >= 94 ? 2 : 1);

        return {
            1: 'cvd-b1', 2: 'cvd-b2', 3: 'cvd-b3', 4: 'cvd-b4'
        }[b] || 'bg-neutral-200 text-secondary-light';
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
                    console.error('Coverage Daily: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'site' },
            { data: 'pic' },
            // Bulan tersimpan sebagai teks "April 2026", jadi mengurutkannya di
            // SQL hanya menghasilkan urutan abjad yang menyesatkan.
            { data: 'bulan', orderable: false },
            {
                data: 'tercover',
                className: 'text-end',
                render: function (d, type) {
                    return type === 'display' ? fmtNum(d) : d;
                }
            },
            {
                data: 'terdaftar',
                className: 'text-end',
                render: function (d, type) {
                    return type === 'display'
                        ? '<span class="text-secondary-light">' + fmtNum(d) + '</span>'
                        : d;
                }
            },
            {
                data: 'persen',
                className: 'text-end',
                render: function (d, type) {
                    if (type !== 'display') { return d === null ? -1 : d; }
                    return d === null
                        ? '<span class="text-secondary-light">–</span>'
                        : '<span class="fw-semibold">' + fmtPct(d) + '</span>';
                }
            },
            {
                data: 'nilai',
                className: 'text-center',
                orderable: false,
                render: function (d, type, row) {
                    if (type !== 'display') { return d; }
                    if (d === null) { return '<span class="text-secondary-light">–</span>'; }
                    // Warnanya dari persentase baris ini, bukan dari angka
                    // nilainya: nilai 1-4 kalau dibaca sebagai persen selalu
                    // jatuh ke band terbawah dan semua lencana memerah.
                    return '<span class="' + nilaiBadgeClass(row.persen) + ' px-8 py-2 rounded-pill fw-medium text-xs"'
                        + ' title="' + escapeHtml(row.nilai_band) + '">'
                        + Number(d).toLocaleString('id-ID', {
                            minimumFractionDigits: 2, maximumFractionDigits: 2
                        })
                        + '</span>';
                }
            },
            {
                data: 'keterangan',
                orderable: false,
                render: function (d, type, row) {
                    if (type !== 'display') { return d; }
                    var kelas = row.persen === null
                        ? 'text-secondary-light'
                        : (row.memenuhi_target ? 'text-success-main' : 'text-danger-main');
                    return '<span class="' + kelas + ' fw-medium text-sm">' + escapeHtml(d) + '</span>';
                }
            }
        ],
        language: {
            processing: 'Memuat…',
            lengthMenu: 'Tampilkan _MENU_ baris',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
            infoEmpty: 'Tidak ada baris',
            infoFiltered: '(disaring dari _MAX_ baris)',
            search: 'Cari:',
            zeroRecords: 'Tidak ada baris untuk filter ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });

    filterEls.forEach(function (node) {
        node.addEventListener('change', function () { table.ajax.reload(); });
    });

    root.querySelector('[data-cvd="reset"]').addEventListener('click', function () {
        filterEls.forEach(function (node) { node.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    root.querySelectorAll('[data-cvd="export"]').forEach(function (btn) {
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
    var tab = document.querySelector('#cvd-tab-data');
    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        });
    }
})();
</script>
@endsection
