@extends('ohs-score-card.layouts.app')

@section('title', 'Rasio Kelayakan Kerja')

@section('css')
<style>

  /* Penanda target pada progress bar. Tidak ada padanannya di WowDash,
     jadi ditulis sendiri: satu garis tipis di posisi persentase target. */
  .rkk-track { position: relative; overflow: visible; }
  .rkk-track__target {
    position: absolute; top: -3px; bottom: -3px; width: 2px;
    background: var(--text-primary-light, #0F172A); opacity: .45;
  }

  /* ---- Matriks capaian bulanan ---- */
  .rkk-matrix-wrap { width: 100%; overflow: auto; max-height: 560px; }
  .rkk-matrix {
    width: 100%; min-width: 820px;
    border-collapse: separate; border-spacing: 3px;
    font-size: 12px;
  }
  .rkk-matrix th, .rkk-matrix td {
    padding: 7px 9px; text-align: center; vertical-align: middle; white-space: nowrap;
  }
  .rkk-matrix thead th {
    position: sticky; top: 0; z-index: 3;
    background: #F8FAFC; color: #475569;
    font-weight: 700; font-size: 11px; border-radius: 8px;
  }
  .rkk-matrix thead th.rkk-th-last { background: #2E90FA !important; color: #fff !important; }
  /* Sel site di-merge dengan rowspan, jadi diratakan ke tengah secara vertikal
     supaya labelnya berada di tengah blok site-nya. */
  .rkk-matrix .rkk-site {
    position: sticky; left: 0; z-index: 2;
    background: #EEF1E2; color: #3F4A2E !important;
    font-weight: 800; text-align: center !important;
    vertical-align: middle !important;
    border-radius: 8px; min-width: 76px;
    letter-spacing: 0.02em;
  }
  .rkk-matrix .rkk-mitra {
    position: sticky; left: 79px; z-index: 2;
    background: #fff; color: #2E6BE6 !important;
    font-weight: 700; text-align: left !important;
    border-radius: 8px; min-width: 150px;
    box-shadow: 1px 0 0 #EEF2F7;
  }
  .rkk-matrix thead .rkk-site, .rkk-matrix thead .rkk-mitra { z-index: 4; background: #F8FAFC; }
  .rkk-matrix .rkk-avg { font-weight: 800; color: #334155 !important; background: #F1F5F9; border-radius: 8px; }
  /* Kelayakan kerja: naik berarti membaik, jadi panah atas hijau. */
  .rkk-matrix .rkk-trend--up { color: #16A34A; font-weight: 800; }
  .rkk-matrix .rkk-trend--down { color: #DC2626; font-weight: 800; }
  .rkk-matrix .rkk-trend--flat { color: #94A3B8; font-weight: 800; }
  .rkk-matrix .rkk-cell {
    font-weight: 700; color: #fff; min-width: 58px;
    border-radius: 6px; border: 1px solid rgba(255,255,255,0.75);
    transition: transform .12s ease, box-shadow .12s ease;
    font-variant-numeric: tabular-nums;
  }
  .rkk-matrix .rkk-cell:hover {
    transform: scale(1.06); box-shadow: 0 0 0 2px rgba(5,150,105,.35);
    position: relative; z-index: 1;
  }
  .rkk-matrix .rkk-empty { background: #F1F5F9; color: #CBD5E1 !important; border-radius: 6px; }

  /* Hanya sel berisi angka yang bisa dibuka rinciannya. */
  .rkk-matrix .rkk-cell--klik { cursor: pointer; }
  .rkk-matrix .rkk-cell--klik:focus-visible {
    outline: 2px solid #487FFF; outline-offset: 1px; position: relative; z-index: 2;
  }

  /* Tabel panjang di dalam modal digulir sendiri. */
  .rkk-modal-scroll { max-height: 34vh; overflow: auto; }
  .rkk-modal-scroll thead th { position: sticky; top: 0; z-index: 1; background: #F8FAFC; }
  /* Gradasi persentase: angka besar hijau, karena tinggi berarti baik. */
  .rkk-t1 { background: #E0484A; }
  .rkk-t2 { background: #F08C2E; }
  .rkk-t3 { background: #F2C230; color: #1F2937 !important; }
  .rkk-t4 { background: #86C96B; }
  .rkk-t5 { background: #059669; }
  /* Mode Nilai: 4 band resmi, warnanya senada dengan badge Nilai. */
  .rkk-n1 { background: #E0484A; }
  .rkk-n2 { background: #F08C2E; }
  .rkk-n3 { background: #F2C230; color: #1F2937 !important; }
  .rkk-n4 { background: #16A34A; }

</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Rasio Kelayakan Kerja</h6>
    <div class="text-secondary-light text-sm mt-4">
      Persentase pekerja dengan hasil MCU Fit, per perusahaan di tiap site
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
    <li class="fw-medium text-primary-600">Rasio Kelayakan Kerja</li>
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
    id="rkk-tab" role="tablist">
  @foreach ($tabs as $i => $tab)
    <li class="nav-item" role="presentation">
      <button class="nav-link px-24 py-10 text-md text-center radius-8 {{ $i === 0 ? 'active' : '' }}"
              id="rkk-tab-{{ $tab['key'] }}"
              data-bs-toggle="pill" data-bs-target="#rkk-pane-{{ $tab['key'] }}"
              type="button" role="tab" aria-controls="rkk-pane-{{ $tab['key'] }}"
              aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
        {{ $tab['label'] }}
      </button>
    </li>
  @endforeach
</ul>

<div class="tab-content">
  @foreach ($tabs as $i => $tab)
    <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}"
         id="rkk-pane-{{ $tab['key'] }}" role="tabpanel">
      @include('ohs-score-card.rasio-kelayakan-kerja.partials._' . $tab['kind'],
               ['ds' => $tab['ds'], 'target' => $target])
    </div>
  @endforeach
</div>

{{-- Satu modal dipakai bersama kedua kumpulan data. Ditaruh di luar tab pane
     supaya tidak ikut tersembunyi saat berpindah tab. --}}
<div class="modal fade" id="rkk-detail-modal" tabindex="-1" aria-labelledby="rkk-detail-judul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content radius-12">
      <div class="modal-header border-bottom py-16 px-24">
        <div>
          <h6 class="modal-title text-lg fw-semibold mb-0" id="rkk-detail-judul">Rincian Bulan</h6>
          <span class="text-sm text-secondary-light" data-rkkm="subjudul"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-24"><div data-rkkm="isi"></div></div>
      <div class="modal-footer border-top py-12 px-24">
        <span class="text-sm text-secondary-light me-auto" data-rkkm="kaki"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// ---- Modal rincian satu sel matriks -----------------------------------------
var rkkModalDetail = (function () {
    'use strict';

    var el = document.getElementById('rkk-detail-modal');
    if (!el) { return null; }

    var bagian = function (n) { return el.querySelector('[data-rkkm="' + n + '"]'); };
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

    function batang(persen, target) {
        var p = persen === null ? 0 : Math.min(100, persen);
        var warna = persen === null ? 'bg-neutral-400'
            : (persen >= target ? 'bg-success-main' : persen >= target - 10 ? 'bg-warning-main' : 'bg-danger-main');
        return '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
            + '<div class="progress-bar ' + warna + ' rounded-pill" role="progressbar"'
            + ' style="width:' + p + '%" aria-valuenow="' + Math.round(p) + '"'
            + ' aria-valuemin="0" aria-valuemax="100"></div></div>';
    }

    function render(j, koordinat) {
        var c = j.sel;
        var target = j.target;
        var capai = c.persen === null ? '' : (c.memenuhi_target ? 'text-success-main' : 'text-danger-main');

        var isi = '<div class="row gy-3 mb-20">'
            + ubin('Capaian Sel', pct(c.persen), 'pekerja dengan hasil MCU Fit', capai)
            + ubin('Nilai', c.nilai === null ? '–' : c.nilai,
                   c.nilai_band === null ? 'belum ada persentase' : 'band ' + c.nilai_band)
            + ubin('Rata-rata Baris', pct(j.baris.persen),
                   num(j.baris.bulan_terisi) + ' bulan terisi · terendah ' + pct(j.baris.terendah)
                   + ' · tertinggi ' + pct(j.baris.tertinggi))
            + ubin('Dari Rincian',
                   j.rincian ? pct(j.rincian.persen) : '–',
                   j.rincian
                       ? num(j.rincian.fit) + ' Fit dari ' + num(j.rincian.total) + ' karyawan'
                       : 'kumpulan data ini tidak punya tabel rincian')
            + '</div>';

        // Dua angka yang memang berbeda, dan selisihnya bisa besar: rata-rata
        // antar bulan memberi bobot sama pada bulan berisi dua orang dan bulan
        // berisi lima ratus. Menaruhnya berdampingan tanpa keterangan akan
        // terbaca sebagai salah satunya keliru -- padahal keduanya benar.
        if (j.selisih && j.selisih.besar) {
            isi += '<div class="alert bg-warning-focus text-warning-main border-warning-main'
                + ' radius-8 px-20 py-12 mb-20 text-sm">'
                + '<strong>Dua angka ini memang berbeda ' + pct(Math.abs(j.selisih.delta)).replace('%', ' poin')
                + '.</strong> Rata-rata ' + num(j.selisih.bulan_terisi) + ' angka bulanan ('
                + pct(j.selisih.bulanan) + ') memberi bobot sama pada tiap bulan, sementara angka dari '
                + 'rincian (' + pct(j.selisih.rincian) + ') dihitung dari ' + num(j.selisih.karyawan)
                + ' karyawan sehingga bulan yang ramai berbobot lebih besar. Keduanya benar untuk '
                + 'pertanyaan yang berbeda.'
                + '</div>';
        }

        isi += '<div class="row gy-4 mb-20">'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">Riwayat baris ini</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Apakah bulan ini kebetulan buruk, atau memang begitu terus</span>'
            +     tabelRiwayat(j.riwayat, target)
            +   '</div>'
            +   '<div class="col-xxl-6">'
            +     '<h6 class="text-md fw-semibold mb-4">'
            +       (j.punya_mitra ? 'Perusahaan lain di ' + esc(j.judul.site) : 'Site lain')
            +       ' · ' + esc(j.judul.bulan) + '</h6>'
            +     '<span class="text-xs text-secondary-light d-block mb-12">'
            +       'Terendah di atas, supaya yang perlu perhatian lebih dulu terlihat</span>'
            +     tabelLabel(j.tetangga, target)
            +   '</div>'
            + '</div>';

        // Sumbu ketiga hanya bermakna kalau perusahaannya ada: menanyakan
        // "perusahaan ini di site lain" tidak masuk akal untuk subcon yang
        // barisnya memang cuma site.
        if (j.lintas_site && j.lintas_site.length > 1) {
            isi += '<h6 class="text-md fw-semibold mb-4">' + esc(j.judul.mitra)
                +    ' di site lain · ' + esc(j.judul.bulan) + '</h6>'
                + '<span class="text-xs text-secondary-light d-block mb-12">'
                +   'Apakah masalahnya milik perusahaan ini, atau milik site ini</span>'
                + tabelLabel(j.lintas_site, target)
                + '<div class="mb-20"></div>';
        }

        if (j.rincian) {
            isi += '<h6 class="text-md fw-semibold mb-4">Hasil MCU karyawan</h6>'
                + '<span class="text-xs text-secondary-light d-block mb-12">'
                +   'Dari ' + esc(j.rincian.tabel) + ' tahun ' + esc(j.rincian.tahun)
                +   ' — tabel ini tidak punya kolom bulan, jadi isinya setahun penuh '
                +   'dan <strong>sama untuk bulan mana pun</strong> di baris ini</span>'
                + ringkasHasil(j.rincian);
        }

        if (j.catatan && j.catatan.length) {
            isi += '<div class="border input-form-light radius-8 p-16 mt-20">'
                + '<h6 class="text-sm fw-semibold mb-8">Yang perlu diketahui tentang angka ini</h6>'
                + '<ul class="mb-0 ps-16">'
                + j.catatan.map(function (t) {
                    return '<li class="text-xs text-secondary-light mb-4">' + esc(t) + '</li>';
                  }).join('')
                + '</ul></div>';
        }

        bagian('isi').innerHTML = isi;
        bagian('kaki').textContent = j.judul.dataset
            + (j.punya_rincian ? '' : ' · tanpa tabel rincian karyawan');
    }

    function tabelRiwayat(baris, target) {
        if (!baris || !baris.length) {
            return '<div class="text-center text-secondary-light py-24">Tidak ada bulan lain untuk dibandingkan.</div>';
        }
        return '<div class="table-responsive rkk-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>Bulan</th><th class="text-end">Capaian</th><th class="text-end">Nilai</th>'
            +   '<th style="width:32%">&nbsp;</th>'
            + '</tr></thead><tbody>'
            + baris.map(function (r) {
                return '<tr' + (r.ini ? ' class="bg-primary-50"' : '') + '>'
                    + '<td class="text-sm' + (r.ini ? ' fw-semibold' : '') + '">' + esc(r.bulan)
                    +   (r.ini ? ' <span class="text-xs text-primary-600">(sel ini)</span>' : '') + '</td>'
                    + '<td class="text-end' + (r.ini ? ' fw-semibold' : '') + '">' + pct(r.persen) + '</td>'
                    + '<td class="text-end text-xs text-secondary-light">'
                    +   (r.nilai === null ? '–' : r.nilai) + '</td>'
                    + '<td>' + batang(r.persen, target) + '</td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function tabelLabel(baris, target) {
        if (!baris || !baris.length) {
            return '<div class="text-center text-secondary-light py-24">Tidak ada pembanding.</div>';
        }
        return '<div class="table-responsive rkk-modal-scroll">'
            + '<table class="table bordered-table sm-table mb-0"><thead><tr>'
            +   '<th>Nama</th><th class="text-end">Capaian</th><th class="text-end">Nilai</th>'
            +   '<th style="width:32%">&nbsp;</th>'
            + '</tr></thead><tbody>'
            + baris.map(function (r) {
                return '<tr' + (r.ini ? ' class="bg-primary-50"' : '') + '>'
                    + '<td class="text-sm' + (r.ini ? ' fw-semibold' : '') + '">' + esc(r.label)
                    +   (r.ini ? ' <span class="text-xs text-primary-600">(sel ini)</span>' : '') + '</td>'
                    + '<td class="text-end' + (r.ini ? ' fw-semibold' : '') + '">' + pct(r.persen) + '</td>'
                    + '<td class="text-end text-xs text-secondary-light">'
                    +   (r.nilai === null ? '–' : r.nilai) + '</td>'
                    + '<td>' + batang(r.persen, target) + '</td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function ringkasHasil(rincian) {
        var hasil = rincian.hasil || [];
        if (!hasil.length) {
            return '<div class="text-center text-secondary-light py-16">Tidak ada karyawan tercatat.</div>';
        }
        var maks = hasil[0].n || hasil[0].jumlah || 1;
        return '<div class="table-responsive"><table class="table bordered-table sm-table mb-0"><tbody>'
            + hasil.map(function (h) {
                var n = h.n !== undefined ? h.n : h.jumlah;
                return '<tr><td class="text-sm">' + esc(h.label !== undefined ? h.label : h.hasil) + '</td>'
                    + '<td class="text-end fw-semibold" style="width:72px">' + num(n) + '</td>'
                    + '<td style="width:34%"><div class="progress w-100 bg-primary-50 rounded-pill h-8-px">'
                    + '<div class="progress-bar bg-info-main rounded-pill" role="progressbar"'
                    + ' style="width:' + (n / maks * 100) + '%" aria-valuenow="' + n + '"'
                    + ' aria-valuemin="0" aria-valuemax="' + maks + '"></div></div></td></tr>';
            }).join('')
            + '</tbody></table></div>';
    }

    function buka(url, koordinat) {
        // Nomor permintaan menjaga agar jawaban yang datang terlambat untuk sel
        // yang sudah tidak dibuka lagi tidak menimpa isi modal.
        var ini = ++permintaan;

        el.querySelector('#rkk-detail-judul').textContent =
            'Rincian ' + koordinat.bulan + ' · ' + koordinat.site;
        bagian('subjudul').textContent = koordinat.mitra || 'Seluruh site ini';
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
window.rkkOverview = (function () {
    'use strict';

    var PALETTE = ['#487FFF', '#45B369', '#FF9F29', '#EF4A00', '#8252E9', '#00B8F2', '#E0484A'];

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
        if (pct >= 98) return 'rkk-t5';
        if (pct >= 90) return 'rkk-t4';
        if (pct >= 78) return 'rkk-t3';
        if (pct >= 62) return 'rkk-t2';
        return 'rkk-t1';
    }

    function nilaiClass(nilai) {
        return { 1: 'rkk-n1', 2: 'rkk-n2', 3: 'rkk-n3', 4: 'rkk-n4' }[nilai] || 'rkk-empty';
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

    return function create(root) {
        var overviewUrl = root.dataset.url;
        var filterEls = Array.prototype.slice.call(root.querySelectorAll('.rkk-filter'));
        var charts = { monthly: null, hasil: null };
        var loaded = false;

        // 'persen' atau 'nilai'. Payload terakhir disimpan supaya mengganti
        // mode cukup menggambar ulang matriks, tanpa memanggil server lagi.
        var matrixMode = 'persen';
        var lastPayload = null;

        function el(name) {
            return root.querySelector('[data-rkk="' + name + '"]');
        }

        // Delegasi di tabel, bukan di tiap sel: matriks digambar ulang setiap
        // ganti filter atau mode, dan pendengar per sel akan ikut hilang.
        // URL-nya per kumpulan data, diambil dari root masing-masing.
        var matrixEl = el('matrix');

        if (rkkModalDetail && matrixEl && root.dataset.detailUrl) {
            var bukaSel = function (td) {
                rkkModalDetail.buka(root.dataset.detailUrl, {
                    site: td.dataset.site,
                    mitra: td.dataset.mitra,
                    month: td.dataset.month,
                    bulan: td.dataset.bulan,
                    ukuran: td.dataset.ukuran,
                    nilai: td.dataset.nilai
                });
            };

            matrixEl.addEventListener('click', function (e) {
                var td = e.target.closest('.rkk-cell--klik');
                if (td && matrixEl.contains(td)) { bukaSel(td); }
            });

            matrixEl.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') { return; }
                var td = e.target.closest('.rkk-cell--klik');
                if (!td || !matrixEl.contains(td)) { return; }
                e.preventDefault();
                bukaSel(td);
            });
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

        // ---- Kartu ringkasan utama -----------------------------------------
        function renderKpi(k) {
            var cards = [
                {
                    grad: 'bg-gradient-end-1', icon: 'solar:heart-pulse-outline', dot: 'bg-primary-600',
                    label: 'Rata-rata MCU Fit',
                    value: k.rata === null ? '–' : fmtPct(k.rata),
                    foot: k.rata === null
                        ? 'Belum ada data'
                        : '<span class="' + nilaiBadgeClass(k.nilai) + ' px-1 rounded-2 fw-medium text-sm">Nilai '
                            + k.nilai + '</span> '
                            + (k.memenuhi_target ? 'Memenuhi' : 'Belum memenuhi') + ' target ' + k.target + '%'
                },
                k.dari_rincian
                    ? {
                        grad: 'bg-gradient-end-5', icon: 'solar:user-cross-outline', dot: 'bg-danger-main',
                        label: 'Belum Fit', value: fmtNum(k.unfit),
                        foot: 'Dari ' + fmtNum(k.karyawan) + ' karyawan yang sudah MCU'
                    }
                    : {
                        grad: 'bg-gradient-end-5', icon: 'solar:arrow-down-outline', dot: 'bg-danger-main',
                        label: 'Capaian Terendah',
                        value: k.terendah === null ? '–' : fmtPct(k.terendah),
                        foot: k.tertinggi === null ? 'Belum ada data' : 'Tertinggi ' + fmtPct(k.tertinggi)
                    },
                {
                    grad: 'bg-gradient-end-2', icon: 'solar:check-circle-outline', dot: 'bg-success-main',
                    label: 'Memenuhi Target', value: fmtNum(k.memenuhi) + ' / ' + fmtNum(k.kombinasi),
                    foot: 'Rata-rata bulanannya ≥ ' + k.target + '%'
                        + (k.kombinasi_kosong ? ' · ' + fmtNum(k.kombinasi_kosong) + ' lainnya belum berdata' : '')
                },
                {
                    grad: 'bg-gradient-end-3', icon: 'solar:calendar-outline', dot: 'bg-yellow',
                    label: 'Cakupan', value: fmtNum(k.bulan_count) + ' bulan',
                    foot: fmtNum(k.site_count) + ' site'
                        + (k.mitra_count ? ', ' + fmtNum(k.mitra_count) + ' perusahaan' : '')
                        + ' · ' + fmtNum(k.sel_terisi) + ' sel terisi'
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

        // ---- Capaian per site ------------------------------------------------
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
                    + '<div class="progress w-100 bg-primary-50 rounded-pill h-8-px rkk-track"'
                    +   ' title="Target ' + s.target + '%">'
                    +   '<div class="progress-bar ' + nilaiBarClass(s.nilai) + ' rounded-pill" role="progressbar"'
                    +     ' style="width:' + Math.min(100, s.percent) + '%" aria-valuenow="' + Math.round(s.percent) + '"'
                    +     ' aria-valuemin="0" aria-valuemax="100"></div>'
                    +   '<span class="rkk-track__target" style="left:' + s.target + '%"></span>'
                    + '</div>'
                    + '<span class="text-xs text-secondary-light">' + fmtNum(s.jumlah)
                    +   ' baris berdata · terendah ' + fmtPct(s.terendah) + '</span>'
                    + '</div>';
            }).join('');
        }

        // ---- Perlu perhatian -------------------------------------------------
        function renderTerendah(list, punyaMitra) {
            var body = el('terendah');
            el('terendah-kol1').textContent = punyaMitra ? 'Site & Perusahaan' : 'Site';

            if (!list.length) {
                body.innerHTML = '<tr><td colspan="3" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>';
                return;
            }
            body.innerHTML = list.map(function (t) {
                var sub = t.mitra
                    ? escapeHtml(t.mitra) + ', terendah ' + fmtPct(t.terendah)
                    : 'terendah ' + fmtPct(t.terendah);

                return '<tr>'
                    + '<td>'
                    +   '<span class="text-md fw-semibold d-block">' + escapeHtml(t.site) + '</span>'
                    +   '<span class="text-sm text-secondary-light">' + sub + '</span>'
                    + '</td>'
                    + '<td class="text-center"><span class="' + nilaiBadgeClass(t.nilai)
                    +   ' px-8 py-2 rounded-pill fw-medium text-xs">' + t.nilai + '</span></td>'
                    + '<td class="text-end fw-medium">' + fmtPct(t.percent) + '</td>'
                    + '</tr>';
            }).join('');
        }

        /** Legenda ikut mode: gradasi persentase, atau 4 band Nilai. */
        function renderLegend(punyaMitra) {
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

            var cakupan = punyaMitra ? 'tiap perusahaan di tiap site' : 'tiap site';

            el('matrix-subtitle').textContent = matrixMode === 'nilai'
                ? 'Nilai 1–4 dari persentase MCU Fit, ' + cakupan
                : 'Persentase pekerja dengan hasil MCU Fit, ' + cakupan;
        }

        function renderMatrix(months, rows, punyaMitra) {
            var table = el('matrix');
            var thead = table.querySelector('thead');
            var tbody = table.querySelector('tbody');
            var tetap = punyaMitra ? 4 : 3;

            var head = '<tr><th class="rkk-site">SITE</th>'
                + (punyaMitra ? '<th class="rkk-mitra">PERUSAHAAN</th>' : '')
                + '<th>RATA</th><th>TREND</th>';
            months.forEach(function (m, i) {
                head += '<th class="' + (i === months.length - 1 ? 'rkk-th-last' : '') + '">'
                    + escapeHtml(m.label) + '</th>';
            });
            thead.innerHTML = head + '</tr>';

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="' + (months.length + tetap) + '" class="text-center py-24 text-secondary-light">'
                    + 'Tidak ada data untuk filter ini.</td></tr>';
                return;
            }

            // Sel site digabung dengan rowspan hanya kalau ada kolom perusahaan;
            // tanpa itu satu baris sudah satu site.
            var span = {};
            var lewati = {};

            if (punyaMitra) {
                rows.forEach(function (row, i) {
                    if (i > 0 && rows[i - 1].site === row.site) {
                        lewati[i] = true;
                        return;
                    }
                    var n = 1;
                    while (i + n < rows.length && rows[i + n].site === row.site) { n++; }
                    span[i] = n;
                });
            }

            tbody.innerHTML = rows.map(function (row, i) {
                var html = '<tr>';

                if (!punyaMitra) {
                    html += '<td class="rkk-site">' + escapeHtml(row.site) + '</td>';
                } else if (!lewati[i]) {
                    html += '<td class="rkk-site" rowspan="' + span[i] + '">'
                        + escapeHtml(row.site) + '</td>';
                }

                if (punyaMitra) {
                    html += '<td class="rkk-mitra">' + escapeHtml(row.mitra) + '</td>';
                }

                if (row.average === null) {
                    html += '<td class="rkk-avg" title="Belum ada data">–</td>';
                } else {
                    var tip = fmtPct(row.average) + ' · Nilai ' + row.nilai + ' (' + row.nilai_band + ')'
                        + ' · dari ' + row.bulan_terisi + ' bulan · terendah ' + fmtPct(row.terendah);
                    html += '<td class="rkk-avg" title="' + escapeHtml(tip) + '">'
                        + (matrixMode === 'nilai' ? row.nilai : fmtPct(row.average)) + '</td>';
                }

                if (row.trend === 'up') {
                    html += '<td class="rkk-trend--up" title="Naik dari bulan sebelumnya">&uarr;</td>';
                } else if (row.trend === 'down') {
                    html += '<td class="rkk-trend--down" title="Turun dari bulan sebelumnya">&darr;</td>';
                } else if (row.trend === 'flat') {
                    html += '<td class="rkk-trend--flat" title="Sama dengan bulan sebelumnya">=</td>';
                } else {
                    html += '<td class="text-secondary-light">–</td>';
                }

                // Variabel sengaja dinamai m: i di luar sudah dipakai sebagai
                // index baris untuk perhitungan rowspan site.
                row.cells.forEach(function (cell, m) {
                    if (cell === null) {
                        html += '<td class="rkk-empty" title="' + escapeHtml(months[m].label)
                            + ': belum ada data">–</td>';
                        return;
                    }

                    // Tooltip selalu memuat kedua angka, apa pun mode
                    // tampilannya, supaya berganti mode tidak menghilangkan
                    // informasi.
                    var tipSel = row.site + (punyaMitra ? ' · ' + row.mitra : '')
                        + ' · ' + months[m].label + ': ' + fmtPct(cell.pct)
                        + ' · Nilai ' + cell.nilai + ' (' + cell.nilai_band + ')';

                    // Koordinat sel dibawa di atribut, bukan ditebak dari
                    // posisi DOM: urutan baris berubah mengikuti pengurutan.
                    // Mitra hanya ada di minecon; untuk subcon dikirim kosong
                    // dan endpoint memang tidak memintanya.
                    html += '<td class="rkk-cell rkk-cell--klik ' + cellClass(cell) + '"'
                        + ' role="button" tabindex="0"'
                        + ' data-site="' + escapeHtml(row.site) + '"'
                        + ' data-mitra="' + escapeHtml(punyaMitra ? row.mitra : '') + '"'
                        + ' data-month="' + months[m].number + '"'
                        + ' data-bulan="' + escapeHtml(months[m].label) + '"'
                        + ' data-ukuran="' + matrixMode + '"'
                        + ' data-nilai="' + escapeHtml(matrixMode === 'nilai'
                            ? 'Nilai ' + cell.nilai : fmtPct(cell.pct)) + '"'
                        + ' title="' + escapeHtml(tipSel + ' · klik untuk rincian') + '">'
                        + (matrixMode === 'nilai' ? cell.nilai : Math.round(cell.pct) + '%')
                        + '</td>';
                });

                return html + '</tr>';
            }).join('');
        }

        // ---- Hasil MCU --------------------------------------------------------
        function renderHasil(payload) {
            var node = el('chart-hasil');
            if (!node || typeof ApexCharts === 'undefined') { return; }

            if (charts.hasil) {
                charts.hasil.destroy();
                charts.hasil = null;
            }

            if (!payload.tersedia || !payload.rows.length) {
                node.innerHTML = '<p class="text-secondary-light text-sm text-center py-40 mb-0">'
                    + 'Kumpulan data ini belum punya tabel rincian hasil MCU.</p>';
                return;
            }
            node.innerHTML = '';

            var rows = payload.rows;

            charts.hasil = new ApexCharts(node, {
                series: [{ name: 'Karyawan', data: rows.map(function (r) { return r.jumlah; }) }],
                chart: { type: 'bar', height: Math.max(220, rows.length * 44 + 60), toolbar: { show: false } },
                // Hijau untuk kategori yang terhitung Fit, merah untuk yang tidak.
                colors: ['#16A34A'],
                plotOptions: {
                    bar: {
                        horizontal: true, borderRadius: 4, barHeight: '58%', distributed: true
                    }
                },
                legend: { show: false },
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
                            return fmtNum(v) + ' karyawan (' + fmtPct(r.percent) + ') — '
                                + (r.fit ? 'terhitung Fit' : 'tidak Fit');
                        }
                    }
                }
            });

            // Warna per batang mengikuti status Fit-nya, bukan urutan.
            charts.hasil.updateOptions({
                colors: rows.map(function (r) { return r.fit ? '#16A34A' : '#E0484A'; })
            }, false, false);

            charts.hasil.render();
        }

        // ---- Tren bulanan -----------------------------------------------------
        function renderMonthlyChart(payload, punyaMitra) {
            var node = el('chart-monthly');
            if (!node || typeof ApexCharts === 'undefined') { return; }

            el('monthly-sub').textContent = punyaMitra
                ? 'Persentase MCU Fit per perusahaan; garis yang menanjak berarti membaik'
                : 'Persentase MCU Fit per site; garis yang menanjak berarti membaik';

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
                xaxis: { categories: payload.labels, labels: { style: { fontSize: '11px' } } },
                yaxis: {
                    min: 0, max: 100,
                    labels: { formatter: function (v) { return Math.round(v) + '%'; } }
                },
                legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
                grid: { borderColor: '#EEF2F7', strokeDashArray: 4 },
                annotations: {
                    yaxis: [{
                        y: @json($target),
                        borderColor: '#0F172A',
                        strokeDashArray: 4,
                        label: {
                            text: 'Target ' + @json($target) + '%',
                            style: { fontSize: '10px', background: '#0F172A', color: '#fff' }
                        }
                    }]
                },
                tooltip: {
                    shared: true,
                    // WAJIB eksplisit: kombinasi shared + intersect melempar
                    // error sehingga grafiknya gagal dirender sama sekali.
                    intersect: false,
                    y: { formatter: function (v) { return v === null ? 'belum ada data' : fmtPct(v); } }
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
                    console.error('Rasio Kelayakan Kerja: panel "' + label + '" gagal dirender', err);
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
                    var punyaMitra = !!json.has_mitra;

                    safe('kpi', function () { renderKpi(json.kpi); });
                    safe('legend', function () { renderLegend(punyaMitra); });
                    safe('matrix', function () { renderMatrix(json.months || [], json.matrix || [], punyaMitra); });
                    safe('per-site', function () { renderPerSite(json.per_site || []); });
                    safe('terendah', function () { renderTerendah(json.terendah || [], punyaMitra); });
                    safe('hasil', function () { renderHasil(json.hasil_mcu || { tersedia: false, rows: [] }); });
                    safe('monthly', function () { renderMonthlyChart(json.monthly, punyaMitra); });
                    safe('catatan', function () { renderCatatan(json.catatan); });

                    var k = json.kpi;
                    el('status').textContent = (k.rata === null ? 'belum ada data' : fmtPct(k.rata) + ' MCU Fit')
                        + (k.dari_rincian ? ' dari ' + fmtNum(k.karyawan) + ' karyawan' : '')
                        + ' · ' + k.kombinasi + (punyaMitra ? ' kombinasi site/perusahaan' : ' site')
                        + ' · ' + k.bulan_count + ' bulan';
                    loaded = true;
                })
                .catch(function (err) {
                    el('status').textContent = 'gagal memuat';
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('Ringkasan Rasio Kelayakan Kerja: gagal memuat', err);
                    }
                });
        }

        filterEls.forEach(function (node) {
            node.addEventListener('change', load);
        });

        // Ganti mode hanya menggambar ulang dari payload terakhir, tidak ada
        // permintaan baru ke server, karena angka Nilai sudah ikut dikirim.
        root.querySelectorAll('.rkk-switch__btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.dataset.mode === matrixMode) { return; }

                matrixMode = btn.dataset.mode;

                root.querySelectorAll('.rkk-switch__btn').forEach(function (b) {
                    // Kelas aktifnya 'active' (bawaan nav-pills WowDash).
                    b.classList.toggle('active', b.dataset.mode === matrixMode);
                });

                if (lastPayload) {
                    var punyaMitra = !!lastPayload.has_mitra;
                    renderLegend(punyaMitra);
                    renderMatrix(lastPayload.months || [], lastPayload.matrix || [], punyaMitra);
                }
            });
        });

        el('reset').addEventListener('click', function () {
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
// ---- Tab Data ---------------------------------------------------------------
window.rkkDataTable = (function () {
    'use strict';

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

    return function create(root) {
        var tableEl = root.querySelector('[data-rkk="table"]');
        if (!tableEl || typeof DataTable === 'undefined') {
            return null;
        }
        if (DataTable.ext) {
            DataTable.ext.errMode = 'none';
        }

        var dataUrl = root.dataset.url;
        var exportUrl = root.dataset.exportUrl;
        var filterEls = Array.prototype.slice.call(root.querySelectorAll('.rkkd-filter'));
        var hintEl = root.querySelector('[data-rkk="hint"]');

        // Bentuk kolom berbeda antar kumpulan data, jadi dibaca dari atribut
        // alih-alih ditulis mati di sini.
        var kolom = JSON.parse(root.dataset.columns || '[]');

        function currentFilters() {
            var out = {};
            filterEls.forEach(function (node) {
                if (node.value) { out[node.dataset.column] = node.value; }
            });
            return out;
        }

        var columns = kolom.map(function (c) {
            var def = { data: c.key, className: c.class || '' };

            if (c.tipe === 'persen') {
                def.render = function (d, type) {
                    if (type !== 'display') { return d === null ? -1 : d; }
                    return d === null
                        ? '<span class="text-secondary-light">–</span>'
                        : '<span class="fw-semibold">' + fmtPct(d) + '</span>';
                };
            } else if (c.tipe === 'nilai') {
                def.orderable = false;
                def.render = function (d, type, row) {
                    if (type !== 'display') { return d; }
                    if (d === null) { return '<span class="text-secondary-light">–</span>'; }
                    return '<span class="' + nilaiBadgeClass(d) + ' px-8 py-2 rounded-pill fw-medium text-xs"'
                        + ' title="' + escapeHtml(row.nilai_band || '') + '">' + d + '</span>';
                };
            } else if (c.tipe === 'status') {
                def.orderable = false;
                def.render = function (d, type) {
                    if (type !== 'display') { return d; }
                    var kelas = d === 'Fit'
                        ? 'bg-success-focus text-success-main'
                        : 'bg-danger-focus text-danger-main';
                    return '<span class="' + kelas + ' px-8 py-2 rounded-pill fw-medium text-xs">'
                        + escapeHtml(d) + '</span>';
                };
            }

            return def;
        });

        var table = new DataTable(tableEl, {
            processing: true,
            serverSide: true,
            searching: true,
            // Pengurutan dimatikan: server selalu mengurutkan per site lalu id,
            // dan kolomnya berbeda antar kumpulan data.
            ordering: false,
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
                        console.error('Rasio Kelayakan Kerja: gagal memuat data', error, xhr && xhr.status);
                    }
                }
            },
            columns: columns,
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

        root.querySelector('[data-rkk="reset"]').addEventListener('click', function () {
            filterEls.forEach(function (node) { node.value = ''; });
            table.search('');
            table.ajax.reload();
        });

        root.querySelectorAll('[data-rkk="export"]').forEach(function (btn) {
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

    document.querySelectorAll('.rkk-overview').forEach(function (root) {
        panes[root.closest('.tab-pane').id] = window.rkkOverview(root);
    });

    document.querySelectorAll('.rkk-datatable').forEach(function (root) {
        var instance = window.rkkDataTable(root);
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

    document.querySelectorAll('#rkk-tab [data-bs-toggle="pill"]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function () {
            var id = (btn.dataset.bsTarget || '').replace('#', '');
            if (panes[id] && panes[id].show) { panes[id].show(); }
        });
    });
})();
</script>
@endsection
