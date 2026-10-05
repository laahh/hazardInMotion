@extends('ohs-score-card.layouts.app')

@section('title', 'Blindspot TBC')

@section('css')
<style>

  /* ---- Matriks temuan ----
     Rupanya sengaja disamakan dengan matriks di halaman Ratio TBC & GR,
     tetapi skala warnanya terbalik: di sini angka besar berarti buruk. */
  .bs-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .bs-matrix {
    width: 100%; min-width: 760px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .bs-matrix th, .bs-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .bs-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .bs-matrix thead th.bs-th-last { background: #2E90FA !important; color: #fff !important; }
  .bs-matrix .bs-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .bs-matrix .bs-mitra {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 150px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .bs-matrix thead .bs-site, .bs-matrix thead .bs-mitra { z-index: 4; background: #F8FAFC; }
  .bs-matrix .bs-total { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  /* Untuk blindspot, naik berarti memburuk, jadi panah atas diwarnai merah. */
  .bs-matrix .bs-trend--up { color: #DC2626; font-weight: 800; }
  .bs-matrix .bs-trend--down { color: #16A34A; font-weight: 800; }
  .bs-matrix .bs-trend--flat { color: #94A3B8; font-weight: 800; }
  .bs-matrix .bs-cell {
    font-weight: 700; color: #fff; min-width: 54px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    font-variant-numeric: tabular-nums;
  }
  .bs-matrix .bs-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(220,38,38,.3);
    position: relative; z-index: 1;
  }
  .bs-matrix .bs-empty { background: #F1F5F9; color: #CBD5E1 !important; border-radius: 6px; }
  /* Di matriks persentase, sel tanpa angka ditandai N/A hijau: tidak adanya
     persentase blindspot di sini dibaca sebagai keadaan yang tidak bermasalah,
     bukan lubang data yang perlu diwaspadai. */
  .bs-matrix .bs-na { background: #16A34A; color: #fff !important; border-radius: 6px; }

  /* Hanya sel berisi angka yang bisa dibuka rinciannya. */
  .bs-matrix .bs-cell--klik { cursor: pointer; }
  .bs-matrix .bs-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Daftar temuan di dalam modal digulir sendiri. */
  .bs-modal-scroll { max-height: 42vh; overflow: auto; }
  .bs-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }
  /* Nol temuan itu kabar baik, jadi warnanya hijau, bukan abu-abu kosong. */
  /* Warna band mengikuti lembar penilaian resmi. Arahnya terbalik dari
     parameter Ratio: di sini 0-5% yang hijau (Nilai 4) dan di atas 15% yang
     merah (Nilai 1), karena blindspot makin kecil makin baik. Kuning dan
     hijau muda memakai teks gelap agar tetap terbaca. */
  .bs-n1 { background: #FF0000; }
  .bs-n2 { background: #FFC000; color: #1F2937 !important; }
  .bs-n3 { background: #FFFF00; color: #1F2937 !important; }
  .bs-n4 { background: #92D050; color: #1F2937 !important; }

  /* Skala cacah temuan tetap gradasi lama: satuannya bukan persen, jadi band
     5/10/15% tidak berlaku di sana. */
  .bs-k0 { background: #16A34A; }
  .bs-k1 { background: #86C96B; }
  .bs-k2 { background: #F2C230; color: #1F2937 !important; }
  .bs-k3 { background: #F08C2E; }
  .bs-k4 { background: #E0484A; }

  /* Deskripsi temuan panjang-panjang; dipotong agar baris tabel tetap rapi. */
  .bs-deskripsi {
    display: block; max-width: 420px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Blindspot TBC</h6>
    <div class="text-secondary-light text-sm mt-4">
      Temuan TBC di area sebuah perusahaan yang justru dilaporkan pihak lain
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
    <li class="fw-medium text-primary-600">Blindspot TBC</li>
  </ul>
</div>

@php
  // Satu tab per (kumpulan data x jenis panel); tab pertama yang aktif.
  $tabs = [];
  foreach ($datasets as $ds) {
      $tabs[] = ['key' => $ds['slug'] . '-ringkasan', 'label' => 'Ringkasan ' . $ds['label'], 'kind' => 'ringkasan', 'ds' => $ds];
      $tabs[] = ['key' => $ds['slug'] . '-data', 'label' => 'Data ' . $ds['label'], 'kind' => 'data', 'ds' => $ds];
  }
@endphp

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="bs-tab" role="tablist">
  @foreach ($tabs as $i => $tab)
    <li class="nav-item" role="presentation">
      <button class="nav-link px-24 py-10 text-md text-center radius-8 {{ $i === 0 ? 'active' : '' }}"
              id="bs-tab-{{ $tab['key'] }}"
              data-bs-toggle="pill" data-bs-target="#bs-pane-{{ $tab['key'] }}"
              type="button" role="tab" aria-controls="bs-pane-{{ $tab['key'] }}"
              aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
        {{ $tab['label'] }}
      </button>
    </li>
  @endforeach
</ul>

<div class="tab-content">
  @foreach ($tabs as $i => $tab)
    <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}"
         id="bs-pane-{{ $tab['key'] }}" role="tabpanel">
      @include('ohs-score-card.blindspot-tbc.partials._' . $tab['kind'], ['ds' => $tab['ds']])
    </div>
  @endforeach
</div>

{{-- Satu modal dipakai bersama kedua kumpulan data dan kedua matriks bulanan.
     Ditaruh di luar tab pane supaya tidak ikut tersembunyi. --}}
<div class="modal fade" id="bs-detail-modal" tabindex="-1" aria-labelledby="bs-detail-judul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content radius-12">
      <div class="modal-header border-bottom py-16 px-24">
        <div>
          <h6 class="modal-title text-lg fw-semibold mb-0" id="bs-detail-judul">Rincian Bulan</h6>
          <span class="text-sm text-secondary-light" data-bsm="subjudul"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24"><div data-bsm="isi"></div></div>
      <div class="modal-footer border-top py-12 px-24">
        <span class="text-sm text-secondary-light me-auto" data-bsm="kaki"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection


@section('page-scripts')
<script>
// ---- Modal rincian satu sel matriks bulanan ---------------------------------
var bsModalDetail = (function () {
    'use strict';

    var el = document.getElementById('bs-detail-modal');
    if (!el) { return null; }

    var bagian = function (n) { return el.querySelector('[data-bsm="' + n + '"]'); };
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

        // Nilai sel yang diklik ikut ditampilkan karena dua matriks berbagi
        // modal ini: satu berisi persentase dari tabel bulanan, satu berisi
        // cacah temuan dari tabel rincian. Tanpa disebut, pembaca tidak tahu
        // angka mana yang baru saja dia klik.
        var labelSel = koordinat.ukuran === 'persen'
            ? 'Persentase blindspot'
            : 'Temuan di sel ini';

        var isi = '<div class="row gy-3 mb-20">'
            + ubin(labelSel, esc(koordinat.nilai || '–'),
                   koordinat.ukuran === 'persen'
                       ? 'dari matriks persentase'
                       : 'dari matriks jumlah temuan')
            + ubin('Total Temuan', num(r.temuan),
                   num(r.pic) + ' PIC kecolongan · ' + num(r.pelapor) + ' pelapor dari '
                   + num(r.perusahaan_pelapor) + ' perusahaan')
            + '</div>';

        if (!j.baris.length) {
            bagian('isi').innerHTML = isi
                + '<div class="text-center text-secondary-light py-24">'
                + 'Tidak ada temuan di tabel rincian untuk kombinasi ini.'
                + (koordinat.ukuran === 'persen'
                    ? ' Angka di sel berasal dari tabel bulanan yang terpisah, jadi tetap sahih.'
                    : '')
                + '</div>';
            bagian('kaki').textContent = '';
            return;
        }

        isi += '<div class="row gy-4 mb-20">'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">Perusahaan yang menangkap</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Blindspot berarti temuan ini didapat pihak lain, bukan pengawas areanya sendiri</span>'
            +     daftarRingkas(j.per_pelapor, 'perusahaan', r.temuan)
            +   '</div>'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">PIC</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Pengawas yang areanya paling banyak luput di bulan ini</span>'
            +     daftarPic(j.per_pic, r.temuan)
            +   '</div>'
            + '</div>';

        isi += '<h6 class="text-md fw-semibold mb-12">Daftar temuan</h6>'
            + '<div class="row gy-2 gx-2 align-items-end mb-12">'
            +   '<div class="col-sm-8"><input type="text" class="form-control form-control-sm radius-8"'
            +     ' placeholder="Cari task, PIC, pelapor, deskripsi…" data-bsm="cari"></div>'
            +   '<div class="col-sm-4 text-sm-end"><span class="text-sm text-secondary-light"'
            +     ' data-bsm="hitung"></span></div>'
            + '</div>'
            + '<div class="table-responsive bs-modal-scroll">'
            +   '<table class="table bordered-table sm-table mb-0" data-bsm="tabel"><thead><tr>'
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

    function daftarRingkas(baris, kunci, total) {
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

    function daftarPic(baris, total) {
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
        var semua = Array.prototype.slice.call(el.querySelectorAll('[data-bsm="tabel"] tbody tr'));

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

        el.querySelector('#bs-detail-judul').textContent =
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

// ---- Tab Ringkasan ----------------------------------------------------------
// Satu pabrik, dipakai untuk tiap kumpulan data. Semua pencarian elemen
// dilakukan di dalam root agar dua salinan tidak saling menimpa.
window.bsOverview = (function () {
    'use strict';

    var PALETTE = ['#E0484A', '#FF9F29', '#F2C230', '#8252E9', '#487FFF', '#00B8F2', '#45B369', '#EF4A00'];

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

    // Skala warna matriks persentase. Berbeda dari halaman Ratio TBC & GR:
    // di sini angka kecil yang hijau, karena yang diukur adalah yang luput.
    // Band datang DARI SERVER (score_bands), tidak ditulis ulang di sini,
    // supaya definisi di layar tidak bisa menyimpang dari yang dipakai
    // controller. Diisi saat payload pertama tiba.
    var BANDS = [];

    function bandUntuk(pct) {
        var v = Number(pct);
        if (!isFinite(v)) { return null; }

        for (var i = 0; i < BANDS.length; i++) {
            if (v <= BANDS[i].atas) { return BANDS[i]; }
        }

        return BANDS.length ? BANDS[BANDS.length - 1] : null;
    }

    /** Nilai berkoma; rumusnya sama persis dengan scoreBandFor() di PHP. */
    function nilaiUntuk(pct) {
        var b = bandUntuk(pct);
        if (!b) { return null; }
        if (b.nilai >= 4) { return 4; }

        var rentang = b.atas - b.bawah;
        if (rentang <= 0) { return b.nilai; }

        // Jarak dari batas ATAS: makin kecil persennya, makin tinggi nilainya.
        var n = b.nilai + (b.atas - Number(pct)) / rentang;

        return Math.round(Math.min(n, b.nilai + 0.99) * 100) / 100;
    }

    function fmtNilai(nilai) {
        if (nilai === null || nilai === undefined) { return '–'; }
        return Number(nilai).toLocaleString('id-ID', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    /**
     * Memasang band dari server dan menyusun legendanya sekalian, supaya
     * keterangan warna di layar selalu mengikuti definisi di controller.
     */
    function setBands(daftar) {
        BANDS = (daftar || []).map(function (b) {
            return {
                bawah: Number(b.bawah),
                atas: Number(b.atas),
                nilai: Number(b.nilai),
                label: String(b.label),
                warna: String(b.warna)
            };
        });

        // Legenda diurutkan dari yang terburuk supaya terbaca seperti tangga
        // risiko: merah dulu, hijau terakhir.
        LEGENDA.persen = BANDS.slice().sort(function (a, b) {
            return a.nilai - b.nilai;
        }).map(function (b) {
            return { color: b.warna, label: 'Nilai ' + b.nilai + ' · ' + b.label };
        });
    }

    function tierPersen(pct) {
        var b = bandUntuk(pct);
        return b ? 'bs-n' + b.nilai : 'bs-k0';
    }

    /** Skala untuk cacah temuan; arahnya sama, hanya satuannya berbeda. */
    function tierTemuan(jumlah) {
        if (jumlah <= 0) return 'bs-k0';
        if (jumlah <= 2) return 'bs-k1';
        if (jumlah <= 5) return 'bs-k2';
        if (jumlah <= 10) return 'bs-k3';
        return 'bs-k4';
    }

    return function create(root) {
        var overviewUrl = root.dataset.url;
        var charts = { pic: null, pelapor: null, monthly: null };
        var loaded = false;

        var filterEls = Array.prototype.slice.call(root.querySelectorAll('.bs-filter'));
        var statusEl = root.querySelector('[data-bs-el="status"]');

        function el(name) {
            return root.querySelector('[data-bs-el="' + name + '"]');
        }

        function currentFilters() {
            var out = {};
            filterEls.forEach(function (node) {
                if (node.value) { out[node.dataset.column] = node.value; }
            });
            return out;
        }

        // ---- Kartu ringkasan utama -----------------------------------------
        function renderKpi(k) {
            var cards = [
                k.ukuran === 'persen'
                    ? {
                        grad: 'bg-gradient-end-5', icon: 'solar:eye-closed-outline', dot: 'bg-danger-main',
                        label: 'Rata-rata Blindspot', value: fmtPct(k.rata_persen),
                        foot: 'Tertinggi ' + fmtPct(k.puncak_persen) + ' di antara ' + fmtNum(k.kombinasi)
                            + ' pasangan site &amp; perusahaan'
                    }
                    : {
                        grad: 'bg-gradient-end-5', icon: 'solar:eye-closed-outline', dot: 'bg-danger-main',
                        label: 'Temuan Blindspot', value: fmtNum(k.temuan),
                        foot: 'Persentase resminya belum tersedia, jadi yang dihitung cacah temuan'
                    },
                k.ukuran === 'persen'
                    ? {
                        grad: 'bg-gradient-end-3', icon: 'solar:danger-triangle-outline', dot: 'bg-warning-main',
                        label: 'Di Atas Ambang', value: fmtNum(k.di_atas_ambang),
                        foot: 'Rata-ratanya lebih dari ' + k.ambang + '%'
                    }
                    : {
                        grad: 'bg-gradient-end-3', icon: 'solar:buildings-outline', dot: 'bg-warning-main',
                        label: 'Perusahaan PIC', value: fmtNum(k.temuan_mitra),
                        foot: fmtNum(k.temuan_kombinasi) + ' pasangan site &amp; perusahaan'
                    },
                {
                    grad: 'bg-gradient-end-1', icon: 'solar:calendar-outline', dot: 'bg-primary-600',
                    label: 'Cakupan', value: fmtNum(k.bulan_count) + ' bulan',
                    foot: fmtNum(k.site_count) + ' site, ' + fmtNum(k.mitra_count) + ' perusahaan PIC'
                },
                k.ukuran === 'persen'
                    ? {
                        grad: 'bg-gradient-end-2', icon: 'solar:clipboard-list-outline', dot: 'bg-yellow',
                        label: 'Temuan Tercatat', value: fmtNum(k.temuan),
                        foot: fmtNum(k.pic_count) + ' PIC, dari ' + fmtNum(k.temuan_kombinasi)
                            + ' pasangan yang sudah ada rinciannya'
                    }
                    : {
                        grad: 'bg-gradient-end-2', icon: 'solar:user-id-outline', dot: 'bg-yellow',
                        label: 'PIC Terlibat', value: fmtNum(k.pic_count),
                        foot: fmtNum(k.pelapor_count) + ' perusahaan pelapor'
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

        // ---- Rata-rata per site ---------------------------------------------
        function renderPerSite(list, k) {
            var host = el('per-site');
            var persen = k.ukuran === 'persen';
            var angka = function (v) { return persen ? fmtPct(v) : fmtNum(v) + ' temuan'; };

            el('per-site-judul').textContent = persen ? 'Rata-rata per Site' : 'Temuan per Site';
            el('per-site-sub').textContent = persen
                ? 'Ambang ' + k.ambang + '%'
                : 'Dihitung dari cacah temuan';

            if (!list.length) {
                host.innerHTML = '<p class="text-secondary-light text-sm text-center py-24 mb-0">Tidak ada data.</p>';
                return;
            }

            var puncak = Math.max.apply(null, list.map(function (s) { return s.nilai; })) || 1;

            host.innerHTML = list.map(function (s, i) {
                return '<div class="' + (i ? 'mt-20' : '') + '">'
                    + '<div class="d-flex align-items-center justify-content-between mb-8">'
                    +   '<span class="text-sm fw-semibold">' + escapeHtml(s.site) + '</span>'
                    +   '<span class="text-sm fw-medium text-secondary-light">' + angka(s.nilai) + '</span>'
                    + '</div>'
                    + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                    +   '<div class="progress-bar ' + (s.di_atas_ambang ? 'bg-danger-main' : 'bg-success-main')
                    +     ' rounded-pill" role="progressbar"'
                    +     ' style="width:' + (s.nilai / puncak * 100) + '%" aria-valuenow="' + s.nilai + '"'
                    +     ' aria-valuemin="0" aria-valuemax="' + puncak + '"></div>'
                    + '</div>'
                    + '<span class="text-xs text-secondary-light">' + fmtNum(s.jumlah)
                    +   ' perusahaan · tertinggi ' + angka(s.puncak)
                    +   (s.di_atas_ambang ? ' · di atas ambang ' + k.ambang + '%' : '') + '</span>'
                    + '</div>';
            }).join('');
        }

        // ---- Peringkat perusahaan PIC ---------------------------------------
        function renderPerMitra(list, k) {
            var body = el('per-mitra');
            var persen = k.ukuran === 'persen';
            var angka = function (v) { return persen ? fmtPct(v) : fmtNum(v); };

            el('per-mitra-kol1').textContent = persen ? 'Rata-rata' : 'Temuan';
            el('per-mitra-sub').textContent = persen
                ? 'Diurutkan dari persentase tertinggi'
                : 'Diurutkan dari temuan terbanyak';

            if (!list.length) {
                body.innerHTML = '<tr><td colspan="3" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>';
                return;
            }
            body.innerHTML = list.map(function (m) {
                var kelas = m.di_atas_ambang
                    ? 'bg-danger-focus text-danger-main'
                    : 'bg-success-focus text-success-main';

                return '<tr>'
                    + '<td><span class="text-md fw-medium">' + escapeHtml(m.mitra) + '</span>'
                    +   '<span class="d-block text-xs text-secondary-light">' + fmtNum(m.jumlah)
                    +   ' site</span></td>'
                    + '<td class="text-end"><span class="' + kelas
                    +   ' px-8 py-2 rounded-pill fw-medium text-xs">' + angka(m.nilai) + '</span></td>'
                    + '<td class="text-end text-secondary-light">' + angka(m.puncak) + '</td>'
                    + '</tr>';
            }).join('');
        }

        var LEGENDA = {
            // Diisi dari score_bands saat payload tiba; lihat setBands().
            persen: [],
            temuan: [
                { color: '#16A34A', label: 'tidak ada temuan' },
                { color: '#86C96B', label: '1–2' },
                { color: '#F2C230', label: '3–5' },
                { color: '#F08C2E', label: '6–10' },
                { color: '#E0484A', label: 'lebih dari 10' }
            ]
        };

        function renderLegend(jenis) {
            el('legend-' + jenis).innerHTML = LEGENDA[jenis].map(function (it) {
                return '<span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;">'
                    + '<span class="rounded-1" style="width:14px;height:14px;background:' + it.color + ';"></span>'
                    + escapeHtml(it.label) + '</span>';
            }).join('');
        }

        /**
         * Kartu mana yang mendapat tempat utama.
         *
         * Kalau tabel bulanannya belum terisi, matriks persentase hanya akan
         * jadi kotak kosong besar di posisi paling menonjol; dalam keadaan itu
         * matriks temuan yang naik ke atas, dan kartu persentase disembunyikan
         * karena keterangannya sudah ada di peringatan di bagian filter.
         */
        function aturTataLetak(ukuran) {
            var persenCard = el('kartu-persen');
            var temuanCard = el('kartu-temuan');
            var utama = el('slot-utama');
            var kedua = el('slot-kedua');
            var barisKedua = el('baris-kedua');

            if (ukuran === 'persen') {
                utama.appendChild(persenCard);
                kedua.appendChild(temuanCard);
                barisKedua.classList.remove('d-none');
                el('temuan-sub').textContent =
                    'Cacah temuan — ukuran yang berbeda dari persentase di atas, jadi ditampilkan terpisah';
            } else {
                utama.appendChild(temuanCard);
                kedua.appendChild(persenCard);
                barisKedua.classList.add('d-none');
                el('temuan-sub').textContent =
                    'Cacah temuan; persentase resmi belum tersedia untuk kumpulan data ini';
            }
        }

        // Delegasi di kedua tabel matriks, bukan di tiap sel: matriks
        // digambar ulang setiap ganti filter, dan pendengar per sel ikut hilang.
        ['persen', 'temuan'].forEach(function (nama) {
            var tabel = el(nama);
            if (!bsModalDetail || !tabel || !root.dataset.detailUrl) { return; }

            var bukaSel = function (td) {
                bsModalDetail.buka(root.dataset.detailUrl, {
                    site: td.dataset.site,
                    mitra: td.dataset.mitra,
                    month: td.dataset.month,
                    bulan: td.dataset.bulan,
                    ukuran: td.dataset.ukuran,
                    nilai: td.dataset.nilai
                });
            };

            tabel.addEventListener('click', function (e) {
                var td = e.target.closest('.bs-cell--klik');
                if (td && tabel.contains(td)) { bukaSel(td); }
            });

            tabel.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') { return; }
                var td = e.target.closest('.bs-cell--klik');
                if (!td || !tabel.contains(td)) { return; }
                e.preventDefault();
                bukaSel(td);
            });
        });

        /**
         * Satu penggambar untuk dua matriks: persentase dan cacah temuan.
         * Keduanya sebentuk, yang berbeda hanya cara memformat angkanya.
         */
        function renderMatrix(name, payload, opsi) {
            var table = el(name);
            var thead = table.querySelector('thead');
            var tbody = table.querySelector('tbody');
            var months = payload.months || [];
            var rows = payload.rows || [];

            if (!payload.tersedia) {
                thead.innerHTML = '';
                tbody.innerHTML = '<tr><td class="text-center py-24 text-secondary-light">'
                    + 'Tabel ' + escapeHtml(payload.tabel) + ' belum berisi data untuk filter ini.'
                    + '</td></tr>';
                return;
            }

            var head = '<tr><th class="bs-site">SITE</th><th class="bs-mitra">PERUSAHAAN PIC</th>'
                + '<th>' + opsi.ringkasLabel + '</th><th>TREND</th>';
            months.forEach(function (m, i) {
                head += '<th class="' + (i === months.length - 1 ? 'bs-th-last' : '') + '">'
                    + escapeHtml(m.label) + '</th>';
            });
            thead.innerHTML = head + '</tr>';

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="' + (months.length + 4) + '" class="text-center py-24 text-secondary-light">'
                    + 'Tidak ada data untuk filter ini.</td></tr>';
                return;
            }

            // Sel site digabung dengan rowspan: satu sel untuk semua perusahaan
            // di site yang sama. Baris sudah dikelompokkan per site dari
            // server, jadi cukup menghitung panjang blok yang berurutan.
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
                    + (lewati[i] ? '' : '<td class="bs-site" rowspan="' + span[i] + '">'
                        + escapeHtml(row.site) + '</td>')
                    + '<td class="bs-mitra">' + escapeHtml(row.mitra) + '</td>'
                    + '<td class="bs-total" title="' + escapeHtml(opsi.ringkasTip(row)) + '">'
                    +   opsi.ringkas(row) + '</td>';

                if (row.trend === 'up') {
                    html += '<td class="bs-trend--up" title="Naik dari bulan sebelumnya, berarti memburuk">&uarr;</td>';
                } else if (row.trend === 'down') {
                    html += '<td class="bs-trend--down" title="Turun dari bulan sebelumnya, berarti membaik">&darr;</td>';
                } else if (row.trend === 'flat') {
                    html += '<td class="bs-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
                } else {
                    html += '<td class="text-secondary-light">–</td>';
                }

                row.cells.forEach(function (value, m) {
                    if (value === null) {
                        // Perender ini dipakai dua matriks, jadi tampilan sel
                        // kosongnya ditentukan pemanggil, bukan diseragamkan.
                        html += opsi.kosong
                            ? '<td class="bs-na" title="' + escapeHtml(months[m].label)
                                + ': tidak ada persentase blindspot">N/A</td>'
                            : '<td class="bs-empty" title="' + escapeHtml(months[m].label)
                                + ': tidak ada data">–</td>';
                        return;
                    }

                    var tip = row.site + ' · ' + row.mitra + ' · ' + months[m].label + ': '
                        + opsi.sel(value) + ' ' + opsi.satuan;

                    // Koordinat sel dibawa di atribut, bukan ditebak dari
                    // posisi DOM: urutan baris berubah mengikuti pengurutan.
                    // Kedua matriks memakai perender ini, jadi keduanya ikut
                    // bisa diklik dan membuka rincian yang sama.
                    html += '<td class="bs-cell bs-cell--klik ' + opsi.tier(value) + '"'
                        + ' role="button" tabindex="0"'
                        + ' data-site="' + escapeHtml(row.site) + '"'
                        + ' data-mitra="' + escapeHtml(row.mitra) + '"'
                        + ' data-month="' + months[m].number + '"'
                        + ' data-bulan="' + escapeHtml(months[m].label) + '"'
                        + ' data-ukuran="' + escapeHtml(name) + '"'
                        + ' data-nilai="' + escapeHtml(opsi.sel(value)) + '"'
                        + ' title="' + escapeHtml(tip + ' · klik untuk rincian') + '">'
                        + opsi.sel(value) + '</td>';
                });

                return html + '</tr>';
            }).join('');
        }

        // ---- Grafik ---------------------------------------------------------
        function destroyChart(key) {
            if (charts[key]) {
                charts[key].destroy();
                charts[key] = null;
            }
        }

        function emptyChart(node, text) {
            node.innerHTML = '<p class="text-secondary-light text-sm text-center py-40 mb-0">'
                + escapeHtml(text || 'Tidak ada data.') + '</p>';
        }

        /** Batang mendatar: nama PIC panjang-panjang, jadi lebih terbaca begini. */
        function renderPicChart(rows) {
            var node = el('chart-pic');
            if (!node || typeof ApexCharts === 'undefined') { return; }

            destroyChart('pic');

            if (!rows.length) { emptyChart(node); return; }
            node.innerHTML = '';

            charts.pic = new ApexCharts(node, {
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
            charts.pic.render();
        }

        function renderPelaporChart(rows) {
            var node = el('chart-pelapor');
            if (!node || typeof ApexCharts === 'undefined') { return; }

            destroyChart('pelapor');

            var values = rows.map(function (r) { return r.jumlah; });
            var total = values.reduce(function (a, b) { return a + b; }, 0);
            if (!values.length || total === 0) { emptyChart(node); return; }
            node.innerHTML = '';

            charts.pelapor = new ApexCharts(node, {
                series: values,
                labels: rows.map(function (r) { return r.label; }),
                colors: PALETTE,
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
            charts.pelapor.render();
        }

        function renderMonthlyChart(payload, k) {
            var node = el('chart-monthly');
            if (!node || typeof ApexCharts === 'undefined') { return; }

            var persen = k.ukuran === 'persen';

            el('monthly-sub').textContent = persen
                ? 'Persentase blindspot per perusahaan PIC; garis yang menanjak berarti memburuk'
                : 'Jumlah temuan per perusahaan PIC; garis yang menanjak berarti memburuk';

            destroyChart('monthly');

            if (!payload.series.length) { emptyChart(node); return; }
            node.innerHTML = '';

            charts.monthly = new ApexCharts(node, {
                series: payload.series,
                chart: { type: 'line', height: 320, toolbar: { show: false }, zoom: { enabled: false } },
                colors: PALETTE,
                stroke: { curve: 'smooth', width: 3 },
                // Bulan tanpa data dikirim null; dibiarkan putus, bukan
                // disambung ke nol yang akan terbaca sebagai "tidak ada blindspot".
                forecastDataPoints: { count: 0 },
                markers: { size: 4, hover: { size: 5 } },
                dataLabels: { enabled: false },
                xaxis: {
                    categories: payload.labels,
                    labels: { style: { fontSize: '11px' } }
                },
                yaxis: {
                    min: 0,
                    title: { text: persen ? 'Blindspot' : 'Temuan', style: { fontSize: '11px' } },
                    labels: {
                        formatter: function (v) {
                            return persen ? Math.round(v) + '%' : fmtNum(Math.round(v));
                        }
                    }
                },
                legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
                grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
                tooltip: {
                    shared: true,
                    // WAJIB eksplisit: kombinasi shared + intersect melempar
                    // error sehingga grafiknya gagal dirender sama sekali.
                    intersect: false,
                    y: {
                        formatter: function (v) {
                            if (v === null) { return 'tidak ada data'; }
                            return persen ? fmtPct(v) : fmtNum(v) + ' temuan';
                        }
                    }
                }
            });
            charts.monthly.render();
        }

        /**
         * Keterangan dari server: ditampilkan apa adanya, atau disembunyikan
         * ketika tidak ada yang perlu diterangkan.
         */
        function renderCatatan(text) {
            var wrap = el('note-wrap');
            if (!wrap) { return; }

            el('note').textContent = text || '';
            wrap.classList.toggle('d-none', !text);
        }

        /** Membungkus renderer agar kegagalan satu panel tidak menjatuhkan sisanya. */
        function safe(label, fn) {
            try {
                fn();
            } catch (err) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Blindspot: panel "' + label + '" gagal dirender', err);
                }
            }
        }

        // 'persen' atau 'nilai'. Payload terakhir disimpan supaya mengganti
        // mode cukup menggambar ulang matriks, tanpa memanggil server lagi.
        var modePersen = 'persen';
        var lastPayload = null;

        /**
         * Matriks persentase, digambar menurut mode yang sedang dipilih.
         * Warna selnya SAMA di kedua mode -- keduanya berasal dari band yang
         * sama -- yang berganti hanya angka yang ditulis di dalam sel.
         */
        function renderPersen() {
            var kosong = { tersedia: false, tabel: '-', months: [], rows: [] };
            var nilaiMode = modePersen === 'nilai';

            renderMatrix('persen', (lastPayload && lastPayload.persen) || kosong, {
                // Hanya matriks ini yang memakai N/A hijau.
                kosong: true,
                ringkasLabel: 'RATA',
                satuan: nilaiMode ? 'nilai' : 'blindspot',
                tier: tierPersen,
                sel: function (v) {
                    return nilaiMode ? fmtNilai(nilaiUntuk(v)) : Number(v).toFixed(1) + '%';
                },
                ringkas: function (row) {
                    if (row.average === null) { return '–'; }
                    return nilaiMode ? fmtNilai(nilaiUntuk(row.average)) : fmtPct(row.average);
                },
                ringkasTip: function (row) {
                    if (row.average === null) { return 'Belum ada data'; }

                    var b = bandUntuk(row.average);

                    return 'Rata-rata ' + fmtPct(row.average)
                        + ', tertinggi ' + fmtPct(row.puncak)
                        + (b ? ' · Nilai ' + fmtNilai(nilaiUntuk(row.average))
                               + ' (' + b.label + ')' : '');
                }
            });

            var sub = el('persen-sub');
            if (sub) {
                sub.textContent = nilaiMode
                    ? 'Nilai 1–4 dari persentase blindspot; makin kecil persentasenya makin tinggi nilainya'
                    : 'Persentase blindspot tiap perusahaan di tiap site; makin besar makin banyak yang luput';
            }
        }

        function load() {
            var params = new URLSearchParams(currentFilters());
            statusEl.textContent = 'memuat…';

            fetch(overviewUrl + (params.toString() ? '?' + params.toString() : ''), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (res) {
                    if (!res.ok) { throw new Error('HTTP ' + res.status); }
                    return res.json();
                })
                .then(function (json) {
                    var kosong = { tersedia: false, tabel: '-', months: [], rows: [] };

                    // Tiap panel dibungkus sendiri: satu panel yang gagal tidak
                    // boleh membuat seluruh dashboard tampak kosong.
                    lastPayload = json;
                    safe('tata-letak', function () { aturTataLetak(json.ukuran); });
                    safe('kpi', function () { renderKpi(json.kpi); });
                    safe('bands', function () { setBands(json.score_bands || []); });
                    safe('legend', function () {
                        renderLegend('persen');
                        renderLegend('temuan');
                    });
                    safe('persen', renderPersen);
                    safe('temuan', function () {
                        renderMatrix('temuan', json.temuan || kosong, {
                            ringkasLabel: 'TOTAL', satuan: 'temuan', tier: tierTemuan,
                            sel: function (v) { return fmtNum(v); },
                            ringkas: function (row) { return fmtNum(row.total); },
                            ringkasTip: function (row) {
                                return 'Rata-rata ' + row.rata + ' temuan per bulan, tertinggi ' + row.puncak;
                            }
                        });
                    });
                    safe('per-site', function () { renderPerSite(json.per_site || [], json.kpi); });
                    safe('per-mitra', function () { renderPerMitra(json.per_mitra || [], json.kpi); });
                    safe('pic', function () { renderPicChart(json.per_pic || []); });
                    safe('pelapor', function () { renderPelaporChart(json.per_pelapor || []); });
                    safe('monthly', function () { renderMonthlyChart(json.monthly, json.kpi); });
                    safe('catatan', function () { renderCatatan(json.catatan); });

                    var k = json.kpi;
                    statusEl.textContent = (k.ukuran === 'persen'
                        ? fmtPct(k.rata_persen) + ' rata-rata · ' + k.kombinasi + ' kombinasi site/perusahaan'
                        : 'persentase resmi belum tersedia · ' + k.temuan_kombinasi + ' kombinasi site/perusahaan')
                        + ' · ' + k.bulan_count + ' bulan · ' + fmtNum(k.temuan) + ' temuan tercatat';
                    loaded = true;
                })
                .catch(function (err) {
                    statusEl.textContent = 'gagal memuat';
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('Ringkasan Blindspot TBC: gagal memuat', err);
                    }
                });
        }

        filterEls.forEach(function (node) {
            node.addEventListener('change', load);
        });

        // Ganti mode hanya menggambar ulang dari payload terakhir; tidak ada
        // permintaan baru ke server karena persentasenya sudah ada di tangan.
        root.querySelectorAll('.bs-switch__btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.dataset.mode === modePersen) { return; }

                modePersen = btn.dataset.mode;

                root.querySelectorAll('.bs-switch__btn').forEach(function (b) {
                    b.classList.toggle('active', b.dataset.mode === modePersen);
                });

                renderPersen();
            });
        });

        root.querySelector('[data-bs-el="reset"]').addEventListener('click', function () {
            filterEls.forEach(function (node) { node.value = ''; });
            load();
        });

        return {
            load: load,
            // Dipanggil saat tab-nya ditampilkan: ApexCharts tidak bisa
            // mengukur elemen yang sedang tersembunyi.
            show: function () {
                if (!loaded) { load(); return; }
                Object.keys(charts).forEach(function (k) {
                    if (charts[k] && typeof charts[k].windowResizeHandler === 'function') {
                        charts[k].windowResizeHandler();
                    }
                });
            }
        };
    };
})();
</script>
<script>
// ---- Tab Data Temuan --------------------------------------------------------
window.bsDataTable = (function () {
    'use strict';

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmtNum(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

    return function create(root) {
        var tableEl = root.querySelector('[data-bs-el="table"]');
        if (!tableEl || typeof DataTable === 'undefined') {
            return null;
        }
        if (DataTable.ext) {
            DataTable.ext.errMode = 'none';
        }

        var dataUrl = root.dataset.url;
        var exportUrl = root.dataset.exportUrl;
        var filterEls = Array.prototype.slice.call(root.querySelectorAll('.bsd-filter'));
        var hintEl = root.querySelector('[data-bs-el="hint"]');

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
                    hintEl.textContent = fmtNum(json.recordsFiltered) + ' temuan';
                    return json.data || [];
                },
                error: function (xhr, error) {
                    hintEl.textContent = 'gagal memuat';
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('Blindspot TBC: gagal memuat data', error, xhr && xhr.status);
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
                        return '<span class="bs-deskripsi" title="' + escapeHtml(d) + '">'
                            + escapeHtml(d || '-') + '</span>';
                    }
                },
                {
                    data: 'bulan',
                    // Bulan tersimpan sebagai nama bulan Inggris, jadi
                    // mengurutkannya hanya menghasilkan urutan abjad.
                    orderable: false,
                    render: function (d, type, row) {
                        if (type !== 'display') { return d; }
                        return escapeHtml(d) + ' <span class="text-xs text-secondary-light">'
                            + escapeHtml(row.tahun) + '</span>';
                    }
                },
                { data: 'task', className: 'text-end' }
            ],
            language: {
                processing: 'Memuat…',
                lengthMenu: 'Tampilkan _MENU_ baris',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ temuan',
                infoEmpty: 'Tidak ada temuan',
                infoFiltered: '(disaring dari _MAX_ temuan)',
                search: 'Cari:',
                zeroRecords: 'Tidak ada temuan untuk filter ini.',
                paginate: { first: '«', last: '»', next: '›', previous: '‹' }
            }
        });

        filterEls.forEach(function (node) {
            node.addEventListener('change', function () { table.ajax.reload(); });
        });

        root.querySelector('[data-bs-el="reset"]').addEventListener('click', function () {
            filterEls.forEach(function (node) { node.value = ''; });
            table.search('');
            table.ajax.reload();
        });

        root.querySelectorAll('[data-bs-el="export"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var params = new URLSearchParams(currentFilters());
                var search = table.search();
                if (search) { params.set('search', search); }
                params.set('format', btn.dataset.format);
                window.location.href = exportUrl + '?' + params.toString();
            });
        });

        return {
            // Tabel dibangun saat pane-nya masih tersembunyi, jadi lebar kolom
            // dihitung ulang begitu tab-nya pertama kali dibuka.
            show: function () { table.columns.adjust(); }
        };
    };
})();
</script>
<script>
// ---- Perakitan tab ----------------------------------------------------------
(function () {
    'use strict';

    var panes = {};

    document.querySelectorAll('.bs-overview').forEach(function (root) {
        panes[root.closest('.tab-pane').id] = window.bsOverview(root);
    });

    document.querySelectorAll('.bs-datatable').forEach(function (root) {
        var instance = window.bsDataTable(root);
        if (instance) {
            panes[root.closest('.tab-pane').id] = instance;
        }
    });

    // Hanya pane yang aktif sejak awal yang langsung dimuat; sisanya menunggu
    // tab-nya dibuka, supaya halaman tidak menembak empat query sekaligus.
    var active = document.querySelector('.tab-pane.active');
    if (active && panes[active.id] && panes[active.id].load) {
        panes[active.id].load();
    }

    document.querySelectorAll('#bs-tab [data-bs-toggle="pill"]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function () {
            var id = (btn.dataset.bsTarget || '').replace('#', '');
            if (panes[id] && panes[id].show) { panes[id].show(); }
        });
    });
})();
</script>
@endsection
