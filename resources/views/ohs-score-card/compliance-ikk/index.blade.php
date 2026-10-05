@extends('ohs-score-card.layouts.app')

@section('title', 'Kesesuaian Implementasi IKK')

@section('css')
<style>

  /* Penanda target pada progress bar. Tidak ada padanannya di WowDash,
     jadi ditulis sendiri: satu garis tipis di posisi persentase target. */
  .ikk-track { position: relative; overflow: visible; }
  .ikk-track__target {
    position: absolute; top: -3px; bottom: -3px; width: 2px;
    background: var(--text-primary-light, #0F172A); opacity: .45;
  }

  /* ---- Matriks capaian bulanan ---- */
  .ikk-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .ikk-matrix {
    width: 100%; min-width: 820px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .ikk-matrix th, .ikk-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .ikk-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .ikk-matrix thead th.ikk-th-last { background: #2E90FA !important; color: #fff !important; }
  /* Sel site di-merge dengan rowspan, jadi diratakan ke tengah secara vertikal
     supaya labelnya berada di tengah blok site-nya. */
  .ikk-matrix .ikk-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .ikk-matrix .ikk-pic {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 96px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .ikk-matrix thead .ikk-site, .ikk-matrix thead .ikk-pic { z-index: 4; background: #F8FAFC; }
  .ikk-matrix .ikk-avg { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  /* Kinerja: naik berarti membaik, jadi panah atas hijau. */
  .ikk-matrix .ikk-trend--up { color: #16A34A; font-weight: 800; }
  .ikk-matrix .ikk-trend--down { color: #DC2626; font-weight: 800; }
  .ikk-matrix .ikk-trend--flat { color: #94A3B8; font-weight: 800; }
  .ikk-matrix .ikk-cell {
    font-weight: 700; color: #fff; min-width: 58px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    font-variant-numeric: tabular-nums;
  }
  .ikk-matrix .ikk-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(5,150,105,.35);
    position: relative; z-index: 1;
  }
  .ikk-matrix .ikk-empty { background: #F1F5F9; color: #CBD5E1 !important; border-radius: 6px; }

  /* Hanya sel berisi angka yang bisa dibuka rinciannya. */
  .ikk-matrix .ikk-cell--klik { cursor: pointer; }
  .ikk-matrix .ikk-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Tabel konteks di dalam modal digulir sendiri agar modalnya tidak memanjang. */
  .ikk-modal-scroll { max-height: 34vh; overflow: auto; }
  .ikk-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }
  /* Daftar izin bisa berisi ratusan baris, jadi digulir lebih panjang. */
  .ikk-izin-scroll { max-height: 46vh; overflow: auto; }
  .ikk-izin-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }
  /* Nama pekerjaan dan lokasi panjang-panjang; dipotong agar baris tetap rapi. */
  .ikk-teks { display: block; max-width: 340px; }
  /* Gradasi persentase: angka besar hijau, karena di sini tinggi berarti baik. */
  .ikk-t1 { background: #E0484A; }
  .ikk-t2 { background: #F08C2E; }
  .ikk-t3 { background: #F2C230; color: #1F2937 !important; }
  .ikk-t4 { background: #86C96B; }
  .ikk-t5 { background: #059669; }
  /* Mode Nilai: 4 band resmi, warnanya senada dengan kartu perusahaan. */
  .ikk-n1 { background: #E0484A; }
  .ikk-n2 { background: #F08C2E; }
  .ikk-n3 { background: #F2C230; color: #1F2937 !important; }
  .ikk-n4 { background: #16A34A; }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Kesesuaian Implementasi IKK</h6>
    <div class="text-secondary-light text-sm mt-4">
      Persentase izin kerja khusus yang sudah dilengkapi OKK, per perusahaan pemilik di tiap site
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
    <li class="fw-medium text-primary-600">Coverage Area Kritis</li>
  </ul>
</div>

<ul class="nav nav-pills style-three pill-tab border input-form-light p-0 radius-8 bg-neutral-50 d-inline-flex mb-24"
    id="ikk-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8 active" id="ikk-tab-ringkasan"
            data-bs-toggle="pill" data-bs-target="#ikk-pane-ringkasan"
            type="button" role="tab" aria-controls="ikk-pane-ringkasan" aria-selected="true">
      Ringkasan
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link px-24 py-10 text-md text-center radius-8" id="ikk-tab-data"
            data-bs-toggle="pill" data-bs-target="#ikk-pane-data"
            type="button" role="tab" aria-controls="ikk-pane-data" aria-selected="false">
      Data
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="ikk-pane-ringkasan" role="tabpanel">
    @include('ohs-score-card.compliance-ikk.partials._ringkasan')
  </div>
  <div class="tab-pane fade" id="ikk-pane-data" role="tabpanel">
    @include('ohs-score-card.compliance-ikk.partials._data')
  </div>
</div>

{{-- Modal rincian satu sel. Ditaruh di luar tab pane supaya tidak ikut
     tersembunyi saat berpindah tab. --}}
<div class="modal fade" id="ikk-detail-modal" tabindex="-1" aria-labelledby="ikk-detail-judul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content radius-12">
      <div class="modal-header border-bottom py-16 px-24">
        <div>
          <h6 class="modal-title text-lg fw-semibold mb-0" id="ikk-detail-judul">Rincian Bulan</h6>
          <span class="text-sm text-secondary-light" data-ikkm="subjudul"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24"><div data-ikkm="isi"></div></div>
      <div class="modal-footer border-top py-12 px-24">
        <span class="text-sm text-secondary-light me-auto" data-ikkm="kaki"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// ---- Modal rincian satu sel matriks -----------------------------------------
var ikkModalDetail = (function () {
    'use strict';

    var el = document.getElementById('ikk-detail-modal');
    if (!el) { return null; }

    var bagian = function (n) { return el.querySelector('[data-ikkm="' + n + '"]'); };
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

        // Keempat kartu satu keluarga: Ber-OKK + Belum = IPK Terbit, dan
        // Persentase = Ber-OKK / IPK Terbit. Semuanya dari sumber yang
        // sama dengan matriksnya, jadi tidak mungkin bertentangan dengan selnya.
        //
        // SATUANNYA LOKASI-MINGGU, bukan IPK seperti Coverage Daily:
        // penyebut parameter ini dihitung per minggu, jadi satu lokasi yang
        // terdaftar empat minggu bernilai empat, bukan dua puluh delapan.
        var isi = '<div class="row gy-3 mb-20">'
            + ubin('IPK Terbit', num(c.terdaftar), 'izin kerja khusus yang terbit di bulan ini')
            + ubin('Sudah ber-OKK', num(c.tercover), 'izin yang OKK-nya sudah ada', 'text-success-main')
            + ubin('Belum ber-OKK', num(c.belum), 'izin yang OKK-nya belum ada',
                   c.belum ? 'text-danger-main' : '')
            + ubin('Persentase', pct(c.persen),
                   c.terdaftar
                       ? num(c.tercover) + ' dibagi ' + num(c.terdaftar) + ' · target ' + target + '%'
                       : 'tidak ada IPK terdaftar',
                   capai)
            + '</div>';

        if (j.site && j.site.terdaftar) {
            isi += '<p class="text-sm text-secondary-light mb-20">Seluruh site ' + esc(j.judul.site)
                + ' pada ' + esc(j.judul.bulan) + ': <span class="fw-semibold">' + pct(j.site.persen)
                + '</span> (' + num(j.site.tercover) + ' dari ' + num(j.site.terdaftar) + ' IPK).</p>';
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

        // Pecahan per dimensi izin, lalu daftar izinnya sendiri. Keduanya
        // dari sumber yang sama dengan selnya, jadi angkanya tidak mungkin
        // bertentangan dengan kartu di atas.
        if (j.per_departemen && j.per_departemen.length) {
            isi += '<div class="row gy-4 mt-4 mb-20">'
                +   '<div class="col-xxl-6">'
                +     '<h6 class="text-md fw-semibold mb-4">Per departemen</h6>'
                +     '<span class="text-xs text-secondary-light d-block mb-12">'
                +       'Yang paling banyak belum ber-OKK di atas</span>'
                +     tabelPecahan(j.per_departemen, 'Departemen')
                +   '</div>'
                +   '<div class="col-xxl-6">'
                +     '<h6 class="text-md fw-semibold mb-4">Per lokasi</h6>'
                +     '<span class="text-xs text-secondary-light d-block mb-12">'
                +       'Sepuluh teratas menurut jumlah izin yang belum ber-OKK</span>'
                +     tabelPecahan(j.per_lokasi, 'Lokasi')
                +   '</div>'
                + '</div>';
        }

        isi += daftarIzin(j);

        bagian('isi').innerHTML = isi;

        var n = (j.baris || []).length;
        bagian('kaki').textContent = j.terpotong
            ? 'Menampilkan ' + num(j.batas) + ' izin pertama dari ' + num(c.terdaftar) + '.'
            : num(n) + ' izin kerja tercatat di sel ini — tiap izin dihitung sekali.';

        pasangPencarian();
    }

    /** Pecahan kepatuhan per satu dimensi izin. */
    function tabelPecahan(baris, judulKolom) {
        if (!baris || !baris.length) {
            return '<div class="text-center text-secondary-light py-16">Tidak ada data.</div>';
        }

        return '<div class="table-responsive ikk-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>' + esc(judulKolom) + '</th><th class="text-end">Belum</th>'
            +   '<th class="text-end">IPK</th><th class="text-end">Capaian</th>'
            + '</tr></thead><tbody>'
            + baris.map(function (b) {
                return '<tr' + (b.belum_okk > 0 ? ' class="bg-warning-focus"' : '') + '>'
                    + '<td><span class="text-sm ikk-teks">' + esc(b.label) + '</span></td>'
                    + '<td class="text-end fw-semibold ' + (b.belum_okk > 0 ? 'text-danger-main' : '')
                    +   '">' + num(b.belum_okk) + '</td>'
                    + '<td class="text-end text-secondary-light">' + num(b.izin) + '</td>'
                    + '<td class="text-end">' + pct(b.persen) + '</td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    /** Daftar izin kerja di sel ini; yang belum ber-OKK didahulukan. */
    function daftarIzin(j) {
        var baris = j.baris || [];

        if (!baris.length) {
            return '<h6 class="text-md fw-semibold mb-12 mt-20">Daftar izin kerja</h6>'
                + '<div class="text-center text-secondary-light py-24">'
                + 'Tidak ada izin kerja tercatat untuk kombinasi ini.</div>';
        }

        return '<h6 class="text-md fw-semibold mb-4 mt-20">Daftar izin kerja</h6>'
            + '<span class="text-xs text-secondary-light d-block mb-12">'
            +   'Satu baris per IPK; yang belum ber-OKK ditaruh di atas</span>'
            + '<div class="row gy-2 gx-2 align-items-end mb-12">'
            +   '<div class="col-sm-8"><input type="text" class="form-control form-control-sm radius-8"'
            +     ' placeholder="Cari kode, pekerjaan, departemen, lokasi…" data-ikkm="cari"></div>'
            +   '<div class="col-sm-4 text-sm-end"><span class="text-sm text-secondary-light"'
            +     ' data-ikkm="hitung"></span></div>'
            + '</div>'
            + '<div class="table-responsive ikk-izin-scroll">'
            +   '<table class="table bordered-table sm-table mb-0" data-ikkm="tabel"><thead><tr>'
            +     '<th>Kode</th><th>Pekerjaan</th><th>Departemen</th><th>Lokasi</th>'
            +     '<th>Mulai</th><th class="text-center">OKK</th>'
            +   '</tr></thead><tbody>'
            +   baris.map(function (b) {
                    return '<tr data-cari="'
                        + esc((b.kode + ' ' + b.nama + ' ' + b.departemen + ' '
                               + b.lokasi + ' ' + b.lokasi_detail).toLowerCase()) + '"'
                        + (b.ada_okk ? '' : ' class="bg-danger-focus"') + '>'
                        + '<td class="text-xs">' + esc(b.kode) + '</td>'
                        + '<td><span class="text-sm ikk-teks">' + esc(b.nama || '-') + '</span></td>'
                        + '<td><span class="text-xs text-secondary-light ikk-teks">'
                        +   esc(b.departemen || '-') + '</span></td>'
                        + '<td><span class="text-sm d-block">' + esc(b.lokasi || '-') + '</span>'
                        +   '<span class="text-xs text-secondary-light">'
                        +   esc(b.lokasi_detail || '-') + '</span></td>'
                        + '<td class="text-xs text-secondary-light">' + esc(b.tanggal || '-') + '</td>'
                        + '<td class="text-center">'
                        +   (b.ada_okk
                                ? '<span class="bg-success-focus text-success-main px-8 py-2 radius-4 text-xs">ada</span>'
                                : '<span class="bg-danger-focus text-danger-main px-8 py-2 radius-4 text-xs fw-semibold">belum</span>')
                        + '</td>'
                        + '</tr>';
                }).join('')
            +   '</tbody></table></div>';
    }

    /** Pencarian dikerjakan di baris yang sudah ada, tanpa ke server lagi. */
    function pasangPencarian() {
        var cari = bagian('cari');
        var hitung = bagian('hitung');

        if (!cari || !hitung) { return; }

        var semua = Array.prototype.slice.call(
            el.querySelectorAll('[data-ikkm="tabel"] tbody tr')
        );

        function terapkan() {
            var teks = (cari.value || '').trim().toLowerCase();
            var tampil = 0;

            semua.forEach(function (tr) {
                var cocok = !teks || tr.dataset.cari.indexOf(teks) !== -1;
                tr.classList.toggle('d-none', !cocok);
                if (cocok) { tampil++; }
            });

            hitung.textContent = tampil === semua.length
                ? semua.length + ' izin'
                : tampil + ' dari ' + semua.length + ' izin';
        }

        cari.addEventListener('input', terapkan);
        terapkan();
    }

    function tabelRiwayat(j, target) {
        if (!j.riwayat.length) {
            return '<div class="text-center text-secondary-light py-24">Tidak ada bulan lain untuk dibandingkan.</div>';
        }

        return '<div class="table-responsive ikk-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>Bulan</th><th class="text-end">Ber-OKK</th>'
            +   '<th class="text-end">IPK</th><th style="width:34%">Capaian</th>'
            + '</tr></thead><tbody>'
            + j.riwayat.map(function (r) {
                // Judul memuat tahun kalau tahunnya diketahui ("April 2026"),
                // tanpa tahun kalau tidak. Dicocokkan dua-duanya, tanpa tahun
                // yang ditulis mati di dalam kode.
                var ini = r.bulan === j.judul.bulan || r.bulan.indexOf(j.judul.bulan + ' ') === 0;
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

        return '<div class="table-responsive ikk-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>Perusahaan</th><th class="text-end">Ber-OKK</th>'
            +   '<th class="text-end">IPK</th><th style="width:34%">Capaian</th>'
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

        el.querySelector('#ikk-detail-judul').textContent =
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

// ---- Tab Ringkasan ----------------------------------------------------------
(function () {
    'use strict';

    var root = document.querySelector('.ikk-overview');
    if (!root) { return; }

    // Warna seri grafik diambil dari palet WowDash yang sudah dipakai
    // dashboard lain di aplikasi ini, bukan palet baru.
    var PALETTE = ['#487FFF', '#45B369', '#FF9F29', '#EF4A00', '#8252E9', '#00B8F2', '#E0484A'];

    var overviewUrl = root.dataset.url;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.ikk-filter'));
    var charts = { monthly: null };
    var loaded = false;

    // 'persen' atau 'nilai'. Payload terakhir disimpan supaya mengganti mode
    // cukup menggambar ulang matriks, tanpa memanggil server lagi.
    var matrixMode = 'persen';
    var lastPayload = null;

    function el(name) {
        return root.querySelector('[data-ikk="' + name + '"]');
    }

    // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap ganti
    // filter atau mode, dan pendengar per sel akan ikut hilang.
    var matrixEl = el('matrix');

    if (ikkModalDetail && matrixEl && root.dataset.detailUrl) {
        var bukaSel = function (td) {
            ikkModalDetail.buka(root.dataset.detailUrl, {
                site: td.dataset.site,
                pic: td.dataset.pic,
                month: td.dataset.month,
                bulan: td.dataset.bulan
            });
        };

        matrixEl.addEventListener('click', function (e) {
            var td = e.target.closest('.ikk-cell--klik');
            if (td && matrixEl.contains(td)) { bukaSel(td); }
        });

        matrixEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var td = e.target.closest('.ikk-cell--klik');
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
        if (pct >= 98) return 'ikk-t5';
        if (pct >= 90) return 'ikk-t4';
        if (pct >= 78) return 'ikk-t3';
        if (pct >= 62) return 'ikk-t2';
        return 'ikk-t1';
    }

    // Mode Nilai memakai 4 band resmi, bukan gradasi di atas, supaya warna sel
    // tidak pernah bertentangan dengan angka Nilai-nya.
    function nilaiClass(nilai) {
        return { 1: 'ikk-n1', 2: 'ikk-n2', 3: 'ikk-n3', 4: 'ikk-n4' }[nilai] || 'ikk-empty';
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
                grad: 'bg-gradient-end-1', icon: 'solar:map-point-outline', dot: 'bg-primary-600',
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
                foot: fmtNum(k.site_count) + ' site, ' + fmtNum(k.pic_count) + ' PIC · '
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
                + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px ikk-track"'
                +   ' title="Target ' + s.target + '%">'
                +   '<div class="progress-bar ' + nilaiBarClass(s.nilai) + ' rounded-pill" role="progressbar"'
                +     ' style="width:' + Math.min(100, s.percent) + '%" aria-valuenow="' + Math.round(s.percent) + '"'
                +     ' aria-valuemin="0" aria-valuemax="100"></div>'
                +   '<span class="ikk-track__target" style="left:' + s.target + '%"></span>'
                + '</div>'
                + '<span class="text-xs text-secondary-light">' + fmtNum(s.jumlah)
                +   ' PIC · ' + fmtNum(s.tercover) + ' dari ' + fmtNum(s.terdaftar) + ' IPK</span>'
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
                +     fmtNum(p.tercover) + ' dari ' + fmtNum(p.terdaftar) + ' IPK</span>'
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
                +     ' · ' + fmtNum(t.tercover) + '/' + fmtNum(t.terdaftar) + ' IPK</span>'
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
            ? 'Nilai 1–4 dari kesesuaian IKK, tiap perusahaan di tiap site'
            : 'Persentase IPK yang sudah ber-OKK, tiap perusahaan di tiap site';
    }

    function renderMatrix(months, rows) {
        var table = el('matrix');
        var thead = table.querySelector('thead');
        var tbody = table.querySelector('tbody');

        var head = '<tr><th class="ikk-site">SITE</th><th class="ikk-pic">PERUSAHAAN</th>'
            + '<th>RATA</th><th>TREND</th>';
        months.forEach(function (m, i) {
            head += '<th class="' + (i === months.length - 1 ? 'ikk-th-last' : '') + '">'
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
                + (lewati[i] ? '' : '<td class="ikk-site" rowspan="' + span[i] + '">'
                    + escapeHtml(row.site) + '</td>')
                + '<td class="ikk-pic">' + escapeHtml(row.pic) + '</td>';

            if (row.average === null) {
                html += '<td class="ikk-avg" title="Belum ada data">–</td>';
            } else {
                var tipRata = fmtPct(row.average) + ' · Nilai ' + row.nilai + ' (' + row.nilai_band + ')'
                    + ' · ' + fmtNum(row.tercover) + ' dari ' + fmtNum(row.terdaftar) + ' IPK'
                    + ' · ' + row.bulan_terisi + ' bulan';
                html += '<td class="ikk-avg" title="' + escapeHtml(tipRata) + '">'
                    + (matrixMode === 'nilai' ? row.nilai : fmtPct(row.average)) + '</td>';
            }

            if (row.trend === 'up') {
                html += '<td class="ikk-trend--up" title="Naik dari bulan sebelumnya">&uarr;</td>';
            } else if (row.trend === 'down') {
                html += '<td class="ikk-trend--down" title="Turun dari bulan sebelumnya">&darr;</td>';
            } else if (row.trend === 'flat') {
                html += '<td class="ikk-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
            } else {
                html += '<td class="text-secondary-light">–</td>';
            }

            // Variabel sengaja dinamai m, bukan i: i di luar sudah dipakai
            // sebagai index baris untuk perhitungan rowspan site.
            row.cells.forEach(function (cell, m) {
                if (cell === null) {
                    html += '<td class="ikk-empty" title="' + escapeHtml(months[m].label)
                        + ': belum ada data">–</td>';
                    return;
                }

                // Tooltip selalu memuat kedua angka, apa pun mode tampilannya,
                // supaya berganti mode tidak menghilangkan informasi.
                var tip = row.site + ' · ' + row.pic + ' · ' + months[m].label + ': '
                    + fmtPct(cell.pct) + ' · Nilai ' + cell.nilai + ' (' + cell.nilai_band + ')'
                    + ' · ' + fmtNum(cell.tercover) + ' dari ' + fmtNum(cell.terdaftar) + ' IPK';

                // Koordinat sel dibawa di atribut, bukan ditebak dari posisi
                // DOM: urutan baris berubah mengikuti pengurutan per site.
                html += '<td class="ikk-cell ikk-cell--klik ' + cellClass(cell) + '"'
                    + ' role="button" tabindex="0"'
                    + ' data-site="' + escapeHtml(row.site) + '"'
                    + ' data-pic="' + escapeHtml(row.pic) + '"'
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
                console.error('Coverage Area Kritis: panel "' + label + '" gagal dirender', err);
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
                    + ' · ' + fmtNum(k.tercover) + ' dari ' + fmtNum(k.terdaftar) + ' IPK · '
                    + k.kombinasi + ' kombinasi site/PIC · ' + k.bulan_count + ' bulan';
                loaded = true;
            })
            .catch(function (err) {
                el('status').textContent = 'gagal memuat';
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Ringkasan Coverage Area Kritis: gagal memuat', err);
                }
            });
    }

    filterEls.forEach(function (node) {
        node.addEventListener('change', load);
    });

    // Ganti mode hanya menggambar ulang dari payload terakhir, tidak ada
    // permintaan baru ke server, karena angka Nilai sudah ikut dikirim.
    root.querySelectorAll('.ikk-switch__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.dataset.mode === matrixMode) { return; }

            matrixMode = btn.dataset.mode;

            root.querySelectorAll('.ikk-switch__btn').forEach(function (b) {
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

    var tab = document.querySelector('#ikk-tab-ringkasan');
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

    var root = document.querySelector('.ikk-datatable');
    if (!root) { return; }

    var tableEl = root.querySelector('[data-ikk="table"]');
    if (!tableEl || typeof DataTable === 'undefined') { return; }
    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    var dataUrl = root.dataset.url;
    var exportUrl = root.dataset.exportUrl;
    var filterEls = Array.prototype.slice.call(root.querySelectorAll('.ikkd-filter'));
    var hintEl = root.querySelector('[data-ikk="hint"]');

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
                    console.error('Coverage Area Kritis: gagal memuat data', error, xhr && xhr.status);
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

    root.querySelector('[data-ikk="reset"]').addEventListener('click', function () {
        filterEls.forEach(function (node) { node.value = ''; });
        table.search('');
        table.ajax.reload();
    });

    root.querySelectorAll('[data-ikk="export"]').forEach(function (btn) {
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
    var tab = document.querySelector('#ikk-tab-data');
    if (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            table.columns.adjust();
        });
    }
})();
</script>
@endsection
