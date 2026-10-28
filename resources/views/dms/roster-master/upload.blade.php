@extends('dms.layouts.app')

@section('title', 'Upload Roster')

@section('css')
@include('dms.roster-master._ui-css')
<style>
  .rup-langkah { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
  .rup-langkah > * { min-width: 0; }
  .rup-isi { padding: 20px 24px 24px; }
  .rup-nomor { width: 28px; height: 28px; border-radius: 8px; display: inline-grid; place-items: center; font-size: 13px; font-weight: 700; background: #EEF2FF; color: #4F46E5; flex: none; }
  .rup-kepala { display: flex; gap: 12px; align-items: flex-start; }
  .rup-label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; display: block; }
  .rup-bantu { font-size: 12px; color: #64748B; margin-top: 6px; display: block; }
  .rup-isi .form-control, .rup-isi .form-select { font-size: 14px; }
  .rup-baris { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 12px; }
  .rup-bulan { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
  .rup-cepat { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 12px; font-size: 12px; color: #64748B; }
  .rup-cepat a { padding: 4px 10px; border-radius: 999px; border: 1px solid #E2E8F0; color: #334155; font-weight: 600; }
  .rup-cepat a:hover { border-color: #C7D2FE; color: #4F46E5; }
  .rup-aksi { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid #F1F5F9; }

  /* Area file: input asli menutupi seluruh kotak, jadi klik & drag-drop tetap bawaan browser. */
  .rup-drop { position: relative; border: 1.5px dashed #CBD5E1; border-radius: 12px; background: #F8FAFC; padding: 22px 16px; text-align: center; transition: border-color .15s ease, background .15s ease; }
  .rup-drop:hover, .rup-drop.is-seret { border-color: #6366F1; background: #EEF2FF; }
  .rup-drop.is-terisi { border-style: solid; border-color: #A5B4FC; background: #F5F7FF; }
  .rup-drop input[type=file] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
  .rup-drop__ikon { display: block; width: fit-content; margin: 0 auto; font-size: 28px; color: #6366F1; }
  .rup-drop__judul { font-size: 14px; font-weight: 600; color: #0F172A; margin-top: 4px; word-break: break-all; }
  .rup-drop__sub { font-size: 12px; color: #64748B; margin-top: 2px; }
  .rup-drop:focus-within { outline: 2px solid #6366F1; outline-offset: 2px; }

  @media (max-width: 991.98px) { .rup-langkah { grid-template-columns: 1fr; } }
  @media (max-width: 575.98px) {
    .rup-isi { padding: 16px; }
    .rup-baris, .rup-bulan { grid-template-columns: 1fr; }
  }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Upload Roster</h6>
    <p class="text-sm text-secondary-light mb-0 mt-4">Download template, isi kode roster per tanggal, lalu upload untuk di-approve.</p>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>DMS
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Master Roster</li>
    <li>-</li>
    <li class="fw-medium">Upload Roster</li>
  </ul>
</div>

@include('dms.roster-master._alert')
@include('dms.roster-master._ringkas')

<div class="rup-langkah">
  {{-- Langkah 1: template (maks 12 bulan dalam satu sheet). --}}
  <div class="rov-kartu">
    <div class="rov-kartu__kepala">
      <div class="rup-kepala">
        <span class="rup-nomor">1</span>
        <div>
          <div class="rov-kartu__judul">Download template</div>
          <p class="rov-kartu__sub">Pilih perusahaan → SID, nama, jabatan &amp; site karyawan aktif langsung terisi.</p>
        </div>
      </div>
    </div>
    <div class="rup-isi">
      <form action="{{ route('dms.master-roster.template', absolute: false) }}" method="GET" id="rmTemplate">
        <div class="mb-16">
          @if ($perusahaan === null)
            <label for="rmPt" class="rup-label">Perusahaan</label>
            <select id="rmPt" name="perusahaan" class="form-select" disabled>
              <option>Daftar perusahaan belum tersedia</option>
            </select>
            <span class="rup-bantu text-danger-600">
              Daftar perusahaan gagal diambil dari bep_vw_safety_karyawan_aktif{{ $perusahaanGalat ? ': '.\Illuminate\Support\Str::limit($perusahaanGalat, 160) : '.' }}
              <a href="{{ route('dms.master-roster.upload', ['segarkan_pt' => 1]) }}" class="fw-semibold text-primary-600 ms-4">Coba ambil lagi</a>
            </span>
            <span class="rup-bantu">Template tanpa perusahaan tetap bisa didownload.</span>
          @else
            <div class="rup-baris">
              <div>
                <label for="rmPt" class="rup-label">Perusahaan</label>
                <select id="rmPt" name="perusahaan" class="form-select">
                  <option value="">— Tanpa perusahaan (template kosong + contoh) —</option>
                  @foreach ($perusahaan as $p)
                    <option value="{{ $p['nama'] }}" data-site='@json($p['site'] ?? [])'>{{ $p['nama'] }} ({{ number_format($p['jumlah'], 0, ',', '.') }} karyawan)</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label for="rmSite" class="rup-label">Site</label>
                <select id="rmSite" name="site" class="form-select" disabled>
                  <option value="">Semua site</option>
                </select>
              </div>
            </div>
          @endif
        </div>

        <div class="rup-bulan">
          <div>
            <label for="rmDari" class="rup-label">Dari bulan</label>
            <input type="month" id="rmDari" name="dari" value="{{ $bulanTemplate }}" class="form-control" required>
          </div>
          <div>
            <label for="rmSampai" class="rup-label">Sampai bulan</label>
            <input type="month" id="rmSampai" name="sampai" value="{{ $bulanTemplate }}" class="form-control" required>
          </div>
        </div>
        <div class="rup-cepat">
          <span>Cepat:</span>
          @foreach ([$tahunTemplate - 1, $tahunTemplate] as $th)
            <a href="{{ route('dms.master-roster.template', ['dari' => $th.'-01', 'sampai' => $th.'-12']) }}" data-rm-cepat>Setahun {{ $th }}</a>
          @endforeach
          <span>· maks {{ \App\Services\Dms\Roster\DmsRosterMasterService::MAKS_BULAN }} bulan per file</span>
        </div>
        <span class="rup-bantu text-danger-600" data-rm-galat hidden></span>

        <div class="rup-aksi">
          <span class="text-xs text-secondary-light">Isi kode roster (D1–D13, N1–N7, OFF, CT, S, I) per tanggal.</span>
          <button type="submit" class="btn btn-outline-primary-600 radius-8 d-inline-flex align-items-center gap-2">
            <iconify-icon icon="solar:file-download-bold"></iconify-icon>Download template
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Langkah 2: upload. --}}
  <div class="rov-kartu">
    <div class="rov-kartu__kepala">
      <div class="rup-kepala">
        <span class="rup-nomor">2</span>
        <div>
          <div class="rov-kartu__judul">Upload roster</div>
          <p class="rov-kartu__sub">Satu baris per karyawan, satu kolom per tanggal. Setelah upload, roster menunggu approval.</p>
        </div>
      </div>
    </div>
    <div class="rup-isi">
      <form action="{{ route('dms.master-roster.store', absolute: false) }}" method="POST" enctype="multipart/form-data" id="rmUpload">
        @csrf
        <div class="mb-16">
          <label for="rmJudul" class="rup-label">Judul roster <span class="text-danger-600">*</span></label>
          <input type="text" id="rmJudul" name="judul" value="{{ old('judul') }}" class="form-control" maxlength="150" required
                 placeholder="mis. Roster PAMA BMO 2 — November 2026">
        </div>
        <div class="mb-16">
          <span class="rup-label">File Excel <span class="text-danger-600">*</span></span>
          <div class="rup-drop" data-rup-drop>
            <input type="file" id="rmFile" name="file" accept=".xlsx,.xls" required aria-label="File Excel roster">
            <iconify-icon icon="solar:file-text-bold-duotone" class="rup-drop__ikon"></iconify-icon>
            <div class="rup-drop__judul" data-rup-nama>Klik atau seret file Excel ke sini</div>
            <div class="rup-drop__sub" data-rup-info>.xlsx / .xls · maks 10 MB · maks 1 tahun (366 hari) per file</div>
          </div>
        </div>
        <div>
          <label for="rmCatatan" class="rup-label">Catatan untuk approver <span class="fw-normal text-secondary-light">(opsional)</span></label>
          <textarea id="rmCatatan" name="catatan" rows="2" class="form-control" maxlength="1000"
                    placeholder="mis. roster site BMO 2 untuk November">{{ old('catatan') }}</textarea>
        </div>
        <div class="rup-aksi">
          <span class="text-xs text-secondary-light">Di-approve oleh user yang di-assign ke perusahaan ini.</span>
          <button type="submit" class="btn btn-primary-600 radius-8 d-inline-flex align-items-center gap-2" data-rup-kirim>
            <iconify-icon icon="solar:upload-bold"></iconify-icon><span>Upload roster</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="rov-kartu rup-riwayat">
  <div class="rov-kartu__kepala">
    <div>
      <div class="rov-kartu__judul">Riwayat upload</div>
      <p class="rov-kartu__sub">Upload terbaru di atas. Klik <b>Review</b> / <b>Lihat</b> untuk membuka jadwalnya.</p>
    </div>
  </div>
  <div class="p-16">
    @include('dms.roster-master._daftar', ['daftar' => $daftar])
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// Template: pilihan site mengikuti perusahaan; cegah rentang terbalik / lebih dari 12 bulan.
(function () {
    'use strict';
    var f = document.getElementById('rmTemplate');
    if (!f) { return; }

    // Pilihan site mengikuti perusahaan terpilih (daftar site dibawa data-site).
    var pt = document.getElementById('rmPt');
    var site = document.getElementById('rmSite');
    if (pt && site) {
        var isiSite = function () {
            var opsi = pt.options[pt.selectedIndex];
            var daftar = [];
            try { daftar = JSON.parse((opsi && opsi.dataset.site) || '[]'); } catch (err) { daftar = []; }
            site.innerHTML = '<option value="">Semua site</option>';
            daftar.forEach(function (s) {
                var o = document.createElement('option');
                o.value = s.nama;
                o.textContent = (s.nama === '-' ? '(tanpa site)' : s.nama) + ' (' + Number(s.jumlah).toLocaleString('id-ID') + ')';
                site.appendChild(o);
            });
            site.disabled = pt.value === '' || daftar.length === 0;
        };
        pt.addEventListener('change', isiSite);
        isiSite();
    }
    f.addEventListener('submit', function (e) {
        var d = f.dari.value.split('-'), s = f.sampai.value.split('-');
        var selisih = (+s[0] - +d[0]) * 12 + (+s[1] - +d[1]);
        var pesan = selisih < 0 ? 'Bulan akhir harus sama dengan atau sesudah bulan awal.'
            : (selisih >= {{ \App\Services\Dms\Roster\DmsRosterMasterService::MAKS_BULAN }} ? 'Rentang maksimal {{ \App\Services\Dms\Roster\DmsRosterMasterService::MAKS_BULAN }} bulan.' : '');
        var el = f.querySelector('[data-rm-galat]');
        el.textContent = pesan;
        el.hidden = pesan === '';
        if (pesan) { e.preventDefault(); }
    });
    // Tombol "Setahun …" ikut membawa perusahaan yang sedang dipilih.
    f.querySelectorAll('[data-rm-cepat]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var ptNilai = f.perusahaan && !f.perusahaan.disabled ? f.perusahaan.value : '';
            if (!ptNilai) { return; }
            e.preventDefault();
            var u = new URL(btn.href, location.href);
            u.searchParams.set('perusahaan', ptNilai);
            if (f.site && !f.site.disabled && f.site.value) { u.searchParams.set('site', f.site.value); }
            location.href = u.toString();
        });
    });
})();

// Upload: tampilkan nama file terpilih & kunci tombol setelah dikirim, supaya
// klik berulang tidak membuat upload ganda.
(function () {
    'use strict';
    var form = document.getElementById('rmUpload');
    if (!form) { return; }
    var drop = form.querySelector('[data-rup-drop]');
    var input = document.getElementById('rmFile');
    var nama = form.querySelector('[data-rup-nama]');
    var info = form.querySelector('[data-rup-info]');
    var infoAwal = info.textContent;

    input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        drop.classList.toggle('is-terisi', !!f);
        nama.textContent = f ? f.name : 'Klik atau seret file Excel ke sini';
        info.textContent = f ? (f.size / 1024 / 1024).toFixed(2).replace('.', ',') + ' MB · klik untuk ganti file' : infoAwal;
    });
    ['dragenter', 'dragover'].forEach(function (ev) { input.addEventListener(ev, function () { drop.classList.add('is-seret'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { input.addEventListener(ev, function () { drop.classList.remove('is-seret'); }); });

    form.addEventListener('submit', function () {
        var btn = form.querySelector('[data-rup-kirim]');
        btn.disabled = true;
        btn.querySelector('span').textContent = 'Mengupload…';
    });
})();
</script>
@endsection
