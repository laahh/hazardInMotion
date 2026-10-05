@extends('ohs-score-card.layouts.app')

@section('title', 'Kinerja Pengawasan Control Room DMS')

@section('css')
<style>

  /* Penanda target pada progress bar. Tidak ada padanannya di WowDash,
     jadi ditulis sendiri: satu garis tipis di posisi persentase target. */
  .kcr-track { position: relative; overflow: visible; }
  .kcr-track__target {
    position: absolute; top: -3px; bottom: -3px; width: 2px;
    background: var(--text-primary-light, #0F172A); opacity: .45;
  }

  /* ---- Matriks capaian bulanan ---- */
  .kcr-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .kcr-matrix {
    width: 100%; min-width: 820px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .kcr-matrix th, .kcr-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .kcr-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .kcr-matrix thead th.kcr-th-last { background: #2E90FA !important; color: #fff !important; }
  /* Sel site di-merge dengan rowspan, jadi diratakan ke tengah secara vertikal
     supaya labelnya berada di tengah blok site-nya. */
  .kcr-matrix .kcr-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .kcr-matrix .kcr-mitra {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 96px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .kcr-matrix thead .kcr-site, .kcr-matrix thead .kcr-mitra { z-index: 4; background: #F8FAFC; }
  .kcr-matrix .kcr-avg { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  /* Kinerja: naik berarti membaik, jadi panah atas hijau. */
  .kcr-matrix .kcr-trend--up { color: #16A34A; font-weight: 800; }
  .kcr-matrix .kcr-trend--down { color: #DC2626; font-weight: 800; }
  .kcr-matrix .kcr-trend--flat { color: #94A3B8; font-weight: 800; }
  .kcr-matrix .kcr-cell {
    font-weight: 700; color: #fff; min-width: 58px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    font-variant-numeric: tabular-nums;
  }
  .kcr-matrix .kcr-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(5,150,105,.35);
    position: relative; z-index: 1;
  }
  .kcr-matrix .kcr-empty { background: #F1F5F9; color: #CBD5E1 !important; border-radius: 6px; }

  /* Hanya sel berisi angka yang bisa dibuka rinciannya; sel strip memang tidak
     punya apa-apa untuk diurai. */
  .kcr-matrix .kcr-cell--klik { cursor: pointer; }
  .kcr-matrix .kcr-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Tabel konteks di dalam modal digulir sendiri agar modalnya tidak memanjang. */
  .kcr-modal-scroll { max-height: 32vh; overflow: auto; }
  .kcr-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }

  /* Gradasi persentase: angka besar hijau, karena di sini tinggi berarti baik. */
  .kcr-t1 { background: #E0484A; }
  .kcr-t2 { background: #F08C2E; }
  .kcr-t3 { background: #F2C230; color: #1F2937 !important; }
  .kcr-t4 { background: #86C96B; }
  .kcr-t5 { background: #059669; }
  /* Mode Nilai: 4 band resmi, warnanya senada dengan kartu perusahaan. */
  .kcr-n1 { background: #E0484A; }
  .kcr-n2 { background: #F08C2E; }
  .kcr-n3 { background: #F2C230; color: #1F2937 !important; }
  .kcr-n4 { background: #16A34A; }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Kinerja Pengawasan Control Room DMS</h6>
    <div class="text-secondary-light text-sm mt-4">
      Persentase kinerja pengawas control room DMS per perusahaan di tiap site
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
    <li class="fw-medium text-primary-600">Kinerja Pengawasan Control Room DMS</li>
  </ul>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="kcr-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="kcr-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#kcr-pane-ringkasan"
            type="button" role="tab" aria-controls="kcr-pane-ringkasan" aria-selected="true">
      Ringkasan
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="kcr-tab-data"
            data-bs-toggle="pill" data-bs-target="#kcr-pane-data"
            type="button" role="tab" aria-controls="kcr-pane-data" aria-selected="false">
      Data
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="kcr-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.kinerja-control-room-dms.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="kcr-pane-data" role="tabpanel">
    @include('ohs-score-card.kinerja-control-room-dms.partials._data')
  </div>
</div>

{{-- Modal rincian satu sel. Ditaruh di luar tab pane supaya tidak ikut
     tersembunyi saat berpindah tab. --}}
<div class="modal fade" id="kcr-detail-modal" tabindex="-1" aria-labelledby="kcr-detail-judul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content radius-12">
      <div class="modal-header border-bottom py-16 px-24">
        <div>
          <h6 class="modal-title text-lg fw-semibold mb-0" id="kcr-detail-judul">Rincian Bulan</h6>
          <span class="text-sm text-secondary-light" data-kcrm="subjudul"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24"><div data-kcrm="isi"></div></div>
      <div class="modal-footer border-top py-12 px-24">
        <span class="text-sm text-secondary-light me-auto" data-kcrm="kaki"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// ---- Modal rincian satu sel matriks -----------------------------------------
var kcrModalDetail = (function () {
    'use strict';

    var el = document.getElementById('kcr-detail-modal');
    if (!el) { return null; }

    var bagian = function (n) { return el.querySelector('[data-kcrm="' + n + '"]'); };
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

    /**
     * Kolom pembanding sebuah baris panel. Baris tanpa persentase TIDAK diberi
     * batang sama sekali: batang sepanjang nol akan terbaca sebagai capaian 0%,
     * padahal artinya justru tidak ada angkanya. Keterangannya pun dibedakan,
     * karena "barisnya ada tapi kosong" bukan hal yang sama dengan "tidak ada
     * barisnya".
     */
    function banding(baris, target) {
        if (baris.persen === null || baris.persen === undefined) {
            return '<span class="text-xs text-secondary-light fst-italic">'
                + (baris.ada_baris ? 'persentase belum diisi' : 'tidak ada baris di bulan ini')
                + '</span>';
        }

        var p = Math.min(100, baris.persen);
        var warna = baris.persen >= target ? 'bg-success-main'
            : baris.persen >= target - 10 ? 'bg-warning-main' : 'bg-danger-main';

        return '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
            + '<div class="progress-bar ' + warna + ' rounded-pill" role="progressbar"'
            + ' style="width:' + p + '%" aria-valuenow="' + Math.round(p) + '"'
            + ' aria-valuemin="0" aria-valuemax="100"></div></div>';
    }

    /** Capaian satu baris panel, dengan strip yang tidak bisa dikira nol. */
    function capaianSel(baris) {
        return baris.persen === null || baris.persen === undefined
            ? '<span class="text-secondary-light">–</span>'
            : pct(baris.persen);
    }

    function render(j) {
        var c = j.sel;
        var target = j.target;
        var r = j.peringkat;
        var k = j.kelengkapan;
        var kosong = c.persen === null;
        var capai = kosong ? '' : (c.memenuhi_target ? 'text-success-main' : 'text-danger-main');

        // Kartunya BUKAN "sekian dari sekian" seperti halaman Coverage:
        // lead_kinerja_control_room_dms cuma menyimpan persentase, tanpa
        // pembilang maupun penyebut, jadi selnya memang tidak bisa diurai.
        var selisih = c.selisih === null ? '–'
            : (c.selisih >= 0 ? '+' : '') + Number(c.selisih).toLocaleString('id-ID',
                { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' poin';

        var isi = '<div class="row gy-3 mb-20">'
            + ubin('Capaian', kosong ? 'Tidak ada data' : pct(c.persen),
                   kosong
                       ? (c.ada_baris
                           ? 'barisnya ada, persentasenya belum diisi'
                           : 'tidak ada baris untuk bulan ini')
                       : 'kinerja pengawas control room DMS',
                   capai)
            + ubin('Nilai', c.nilai === null ? '–' : c.nilai,
                   c.nilai_band === null ? 'tidak dinilai tanpa persentase' : 'band ' + c.nilai_band)
            + ubin('Selisih ke Target', selisih,
                   kosong ? 'target ' + target + '% · belum bisa dibandingkan' : 'target ' + target + '%',
                   capai)
            + ubin('Peringkat Bulan Ini',
                   r.posisi === null ? '–' : 'ke-' + r.posisi,
                   r.posisi === null
                       ? 'sel tanpa persentase tidak ikut diperingkat'
                       : 'dari ' + r.dari + ' sel berangka · rata-rata ' + pct(r.rata))
            + '</div>';

        // Penyebut peringkat sengaja diterangkan: 22 pasangan site x
        // perusahaan tidak semuanya punya angka tiap bulan, dan tanpa kalimat
        // ini "dari sekian sel" mudah dikira seluruh pasangan.
        isi += '<p class="text-sm text-secondary-light mb-20">Di ' + esc(j.judul.bulan) + ', '
            + '<span class="fw-semibold">' + num(k.berangka) + '</span> dari ' + num(k.pasangan)
            + ' pasangan site &amp; perusahaan punya persentase'
            + (k.tanpa_persen ? ' · ' + num(k.tanpa_persen) + ' ada barisnya tetapi kosong' : '')
            + (k.tanpa_baris ? ' · ' + num(k.tanpa_baris) + ' tidak ada barisnya' : '')
            + '.</p>';

        isi += '<div class="row gy-4 mb-20">'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">Riwayat ' + esc(j.judul.mitra)
            +       ' di ' + esc(j.judul.site) + '</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Apakah bulan ini kebetulan buruk, atau memang begitu terus</span>'
            +     tabelDaftar(j.riwayat, 'bulan', 'Bulan', target, j.judul.bulan)
            +   '</div>'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">Seluruh perusahaan di ' + esc(j.judul.site)
            +       ' · ' + esc(j.judul.bulan) + '</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Apakah masalahnya milik satu perusahaan atau menyeluruh</span>'
            +     tabelDaftar(j.sebulan, 'mitra', 'Perusahaan', target, null)
            +   '</div>'
            + '</div>';

        // Panel ketiga: tiap perusahaan di parameter ini bekerja di 2 sampai 5
        // site, jadi capaiannya di site lain memisahkan "perusahaannya lemah"
        // dari "sitenya yang bermasalah". Disembunyikan kalau memang cuma satu
        // site, karena tidak ada yang bisa dibandingkan.
        if (j.lintas_site && j.lintas_site.length > 1) {
            isi += '<h6 class="text-md fw-semibold mb-4">' + esc(j.judul.mitra)
                +    ' di site lain · ' + esc(j.judul.bulan) + '</h6>'
                + '<span class="text-xs text-secondary-light d-block mb-12">'
                +   'Apakah perusahaan ini lemah di mana-mana, atau hanya di site ini</span>'
                + tabelDaftar(j.lintas_site, 'site', 'Site', target, null);
        }

        bagian('isi').innerHTML = isi;
        bagian('kaki').textContent = 'Sumber parameter ini hanya menyimpan persentase — tidak ada '
            + 'pembilang dan penyebut untuk diurai — jadi yang ditampilkan konteks di sekeliling sel, '
            + 'semuanya dari tabel yang sama dengan matriks. Strip berarti tidak ada datanya, bukan nol.';
    }

    /**
     * Satu bentuk tabel untuk ketiga panel. Kolom pertamanya berganti nama
     * mengikuti sumbu yang sedang dibandingkan, dan baris tanpa persentase
     * tetap ditulis supaya pembanding yang kosong tidak lenyap diam-diam.
     */
    function tabelDaftar(baris, kunci, judulKolom, target, sorotLabel) {
        if (!baris || !baris.length) {
            return '<div class="text-center text-secondary-light py-24">Tidak ada pembanding.</div>';
        }

        return '<div class="table-responsive kcr-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>' + esc(judulKolom) + '</th><th class="text-end">Capaian</th>'
            +   '<th style="width:40%">&nbsp;</th>'
            + '</tr></thead><tbody>'
            + baris.map(function (b) {
                var ini = b.ini === true || (sorotLabel !== null && b[kunci] === sorotLabel);
                return '<tr' + (ini ? ' class="bg-primary-50"' : '') + '>'
                    + '<td class="text-sm' + (ini ? ' fw-semibold' : '') + '">' + esc(b[kunci])
                    +   (ini ? ' <span class="text-xs text-primary-600">(sel ini)</span>' : '') + '</td>'
                    + '<td class="text-end' + (ini ? ' fw-semibold' : '') + '">' + capaianSel(b) + '</td>'
                    + '<td>' + banding(b, target) + '</td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function buka(url, koordinat) {
        // Nomor permintaan menjaga agar jawaban yang datang terlambat untuk sel
        // yang sudah tidak dibuka lagi tidak menimpa isi modal.
        var ini = ++permintaan;

        el.querySelector('#kcr-detail-judul').textContent =
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

    var root = document.querySelector('.kcr-overview');
    if (!root) { return; }

    // Warna seri grafik diambil dari palet WowDash yang sudah dipakai
    // dashboard lain di aplikasi ini, bukan palet baru.
    var PALETTE = ['#487FFF', '#45B369', '#FF9F29', '#EF4A00', '#8252E9', '#00B8F2', '#E0484A'];

    var overviewUrl = root.dataset.url;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.kcr-filter'));
    var charts = { monthly: null };
    var loaded = false;

    // 'persen' atau 'nilai'. Payload terakhir disimpan supaya mengganti mode
    // cukup menggambar ulang matriks, tanpa memanggil server lagi.
    var matrixMode = 'persen';
    var lastPayload = null;

    function el(name) {
        return root.querySelector('[data-kcr="' + name + '"]');
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap ganti
    // filter atau mode, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    if (kcrModalDetail && matrixEl && root.dataset.detailUrl) {
        var bukaSel = function (td) {
            kcrModalDetail.buka(root.dataset.detailUrl, {
                site: td.dataset.site,
                mitra: td.dataset.mitra,
                month: td.dataset.month,
                bulan: td.dataset.bulan
            });
        };

        matrixEl.addEventListener('click', function (e) {
            var td = e.target.closest('.kcr-cell--klik');
            if (td && matrixEl.contains(td)) { bukaSel(td); }
        });

        matrixEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var td = e.target.closest('.kcr-cell--klik');
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

    // Gradasi warna untuk mode Persentase: 5 tingkat, lebih halus daripada
    // band Nilai sehingga perbedaan antar bulan lebih mudah terlihat.
    function tierClass(pct) {
        if (pct >= 98) return 'kcr-t5';
        if (pct >= 90) return 'kcr-t4';
        if (pct >= 78) return 'kcr-t3';
        if (pct >= 62) return 'kcr-t2';
        return 'kcr-t1';
    }

    // Mode Nilai memakai 4 band resmi, bukan gradasi di atas, supaya warna sel
    // tidak pernah bertentangan dengan angka Nilai-nya.
    function nilaiClass(nilai) {
        return { 1: 'kcr-n1', 2: 'kcr-n2', 3: 'kcr-n3', 4: 'kcr-n4' }[nilai] || 'kcr-empty';
    }

    // Kelas badge mengikuti sistem warna WowDash (bg-*-focus + text-*-main),
    // bukan warna inline, supaya ikut tema dan konsisten dengan modul lain.
    function nilaiBadgeClass(nilai) {
        return {
            1: 'bg-danger-focus text-danger-main',
            2: 'bg-warning-focus text-warning-main',
            3: 'bg-info-focus text-info-main',
            4: 'bg-success-focus text-success-main'
        }[nilai] || 'bg-neutral-200 text-secondary-light';
    }

    function nilaiBarClass(nilai) {
        return {
            1: 'bg-danger-main', 2: 'bg-warning-main',
            3: 'bg-info-main', 4: 'bg-success-main'
        }[nilai] || 'bg-neutral-400';
    }

    function cellClass(cell) {
        return matrixMode === 'nilai' ? nilaiClass(cell.nilai) : tierClass(cell.pct);
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
                grad: 'bg-gradient-end-1', icon: 'solar:monitor-outline', dot: 'bg-primary-600',
                label: 'Rata-rata Kinerja',
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
                foot: 'Pasangan site &amp; perusahaan yang rata-ratanya ≥ ' + k.target + '%'
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
                foot: fmtNum(k.site_count) + ' site, ' + fmtNum(k.mitra_count) + ' perusahaan · '
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
                + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px kcr-track"'
                +   ' title="Target ' + s.target + '%">'
                +   '<div class="progress-bar ' + nilaiBarClass(s.nilai) + ' rounded-pill" role="progressbar"'
                +     ' style="width:' + Math.min(100, s.percent) + '%" aria-valuenow="' + Math.round(s.percent) + '"'
                +     ' aria-valuemin="0" aria-valuemax="100"></div>'
                +   '<span class="kcr-track__target" style="left:' + s.target + '%"></span>'
                + '</div>'
                + '<span class="text-xs text-secondary-light">' + fmtNum(s.jumlah)
                +   ' perusahaan · terendah ' + fmtPct(s.terendah) + '</span>'
                + '</div>';
        }).join('');
    }

    // ---- Capaian per perusahaan --------------------------------------------
    function renderPerMitra(list) {
        var host = el('per-mitra');
        if (!list.length) {
            host.innerHTML = '<div class="col-12 text-center text-secondary-light py-24">'
                + 'Tidak ada data untuk filter ini.</div>';
            return;
        }
        host.innerHTML = list.map(function (p) {
            return '<div class="col-xxl-4 col-md-6">'
                + '<div class="border input-form-light radius-8 p-16 h-100">'
                +   '<div class="d-flex align-items-center justify-content-between gap-2 mb-12">'
                +     '<span class="text-md fw-semibold">' + escapeHtml(p.mitra) + '</span>'
                +     '<span class="' + nilaiBadgeClass(p.nilai) + ' px-8 py-2 rounded-pill fw-medium text-xs">Nilai '
                +       p.nilai + '</span>'
                +   '</div>'
                +   '<h6 class="mb-8 fw-semibold">' + fmtPct(p.percent) + '</h6>'
                +   '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px mb-8">'
                +     '<div class="progress-bar ' + nilaiBarClass(p.nilai) + ' rounded-pill" role="progressbar"'
                +       ' style="width:' + Math.min(100, p.percent) + '%" aria-valuenow="' + Math.round(p.percent) + '"'
                +       ' aria-valuemin="0" aria-valuemax="100"></div>'
                +   '</div>'
                +   '<span class="text-sm text-secondary-light">' + fmtNum(p.jumlah) + ' site · terendah '
                +     fmtPct(p.terendah) + '</span>'
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
                +   '<span class="text-sm text-secondary-light">' + escapeHtml(t.mitra)
                +     ', terendah ' + fmtPct(t.terendah) + '</span>'
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
                { color: '#E0484A', label: 'Nilai 1 · <80%' },
                { color: '#F08C2E', label: 'Nilai 2 · 80–90%' },
                { color: '#F2C230', label: 'Nilai 3 · 90–98%' },
                { color: '#16A34A', label: 'Nilai 4 · 98–100%' }
            ]
            : [
                { color: '#E0484A', label: '<62%' },
                { color: '#F08C2E', label: '62–78%' },
                { color: '#F2C230', label: '78–90%' },
                { color: '#86C96B', label: '90–98%' },
                { color: '#059669', label: '≥98%' }
            ];

        el('legend').innerHTML = items.map(function (it) {
            return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                + '<span class="rounded-1" style="width:14px;height:14px;background:' + it.color + ';"></span>'
                + escapeHtml(it.label) + '</span>';
        }).join('');

        el('matrix-subtitle').textContent = matrixMode === 'nilai'
            ? 'Nilai 1–4 dari persentase kinerja, tiap perusahaan di tiap site'
            : 'Persentase kinerja pengawas control room, tiap perusahaan di tiap site';
    }

    function renderMatrix(months, rows) {
        var table = el('matrix');
        var thead = table.querySelector('thead');
        var tbody = table.querySelector('tbody');

        var head = '<tr><th class="kcr-site">SITE</th><th class="kcr-mitra">PERUSAHAAN</th>'
            + '<th>RATA</th><th>TREND</th>';
        months.forEach(function (m, i) {
            head += '<th class="' + (i === months.length - 1 ? 'kcr-th-last' : '') + '">'
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
                + (lewati[i] ? '' : '<td class="kcr-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="kcr-mitra">' + escapeHtml(row.mitra) + '</td>';

            if (row.average === null) {
                html += '<td class="kcr-avg" title="Belum ada data">–</td>';
            } else {
                var tipRata = fmtPct(row.average) + ' · Nilai ' + row.nilai + ' (' + row.nilai_band + ')'
                    + ' · dari ' + row.bulan_terisi + ' bulan · terendah ' + fmtPct(row.terendah);
                html += '<td class="kcr-avg" title="' + escapeHtml(tipRata) + '">'
                    + (matrixMode === 'nilai' ? row.nilai : fmtPct(row.average)) + '</td>';
            }

            if (row.trend === 'up') {
                html += '<td class="kcr-trend--up" title="Naik dari bulan sebelumnya">&uarr;</td>';
            } else if (row.trend === 'down') {
                html += '<td class="kcr-trend--down" title="Turun dari bulan sebelumnya">&darr;</td>';
            } else if (row.trend === 'flat') {
                html += '<td class="kcr-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
            } else {
                html += '<td class="text-secondary-light">–</td>';
            }

            // Variabel sengaja dinamai m, bukan i: i di luar sudah dipakai
            // sebagai index baris untuk perhitungan rowspan site.
            row.cells.forEach(function (cell, m) {
                if (cell === null) {
                    html += '<td class="kcr-empty" title="' + escapeHtml(months[m].label)
                        + ': belum ada data">–</td>';
                    return;
                }

                // Tooltip selalu memuat kedua angka, apa pun mode tampilannya,
                // supaya berganti mode tidak menghilangkan informasi.
                var tip = row.site + ' · ' + row.mitra + ' · ' + months[m].label + ': '
                    + fmtPct(cell.pct) + ' · Nilai ' + cell.nilai + ' (' + cell.nilai_band + ')';

                // Koordinat sel dibawa di atribut, bukan ditebak dari posisi
                // DOM: urutan baris berubah mengikuti pengurutan per site.
                html += '<td class="kcr-cell kcr-cell--klik ' + cellClass(cell) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-mitra="' + escapeHtml(row.mitra) + '"'
                    + ' data-month="' + months[m].number + '"'
                    + ' data-bulan="' + escapeHtml(months[m].label) + '"'
                    + ' title="' + escapeHtml(tip + ' · klik untuk rincian') + '">'
                    + (matrixMode === 'nilai' ? cell.nilai : Math.round(cell.pct) + '%')
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
                console.error('Kinerja Control Room: panel "' + label + '" gagal dirender', err);
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
                safe('per-mitra', function () { renderPerMitra(json.per_mitra || []); });
                safe('terendah', function () { renderTerendah(json.terendah || []); });
                safe('monthly', function () { renderMonthlyChart(json.monthly); });
                safe('catatan', function () { renderCatatan(json.catatan); });

                var k = json.kpi;
                el('status').textContent = (k.rata === null ? 'belum ada data' : fmtPct(k.rata) + ' rata-rata')
                    + ' · ' + k.kombinasi + ' kombinasi site/perusahaan · ' + k.bulan_count + ' bulan';
                loaded = true;
            })
            .catch(function (err) {
                el('status').textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Ringkasan Kinerja Control Room DMS: gagal memuat', err);
                }
            });
    }

    filterEls.forEach(function (node) {
        node.addEventListener('change', load);
    });

    // Ganti mode hanya menggambar ulang dari payload terakhir, tidak ada
    // permintaan baru ke server, karena angka Nilai sudah ikut dikirim.
    root.querySelectorAll('.kcr-switch__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.mode === matrixMode) { return; }

            matrixMode = btn.dataset.mode;

            root.querySelectorAll('.kcr-switch__btn').forEach(function (b) {
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

    var tab = document.querySelector('#kcr-tab-ringkasan');
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

    var root = document.querySelector('.kcr-datatable');
    if (!root) { return; }

    var tableEl = root.querySelector('[data-kcr="table"]');
    if (!tableEl || typeof DataTable === 'undefined') { return; }
    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.kcrd-filter'));
    var hintEl = root.querySelector('[data-kcr="hint"]');

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

    function nilaiBadgeClass(nilai) {
        return {
            1: 'bg-danger-focus text-danger-main',
            2: 'bg-warning-focus text-warning-main',
            3: 'bg-info-focus text-info-main',
            4: 'bg-success-focus text-success-main'
        }[nilai] || 'bg-neutral-200 text-secondary-light';
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
                    console.error('Kinerja Control Room DMS: gagal memuat data', error, xhr && xhr.status);
                }
            }
        },
        columns: [
            { data: 'site' },
            { data: 'mitra' },
            // Bulan tersimpan sebagai nama bulan Inggris, jadi mengurutkannya
            // hanya menghasilkan urutan abjad yang menyesatkan.
            { data: 'bulan', orderable: false },
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
                    return '<span class="' + nilaiBadgeClass(d) + ' px-8 py-2 rounded-pill fw-medium text-xs"'
                        + ' title="' + escapeHtml(row.nilai_band) + '">' + d + '</span>';
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

    root.querySelector('[data-kcr="reset"]').addEventListener('click', function () {
        filterEls.forEach(function (node) { node.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    root.querySelectorAll('[data-kcr="export"]').forEach(function (btn) {
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
    var tab = document.querySelector('#kcr-tab-data');
    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        });
    }
})();
</script>
@endsection
