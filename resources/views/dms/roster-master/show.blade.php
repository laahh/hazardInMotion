@extends('dms.layouts.app')

@section('title', 'Roster — '.$upload->judul)

@section('css')
@include('dms.roster-master._grid-css')
<style>

  /* Tanda tangan approver (signature_pad). */
  .rm-ttd-kanvas { position: relative; height: 200px; border: 1.5px dashed #94A3B8; border-radius: 10px; background: #fff; touch-action: none; }
  .rm-ttd-kanvas canvas { position: absolute; inset: 0; width: 100%; height: 100%; border-radius: 10px; cursor: crosshair; }
  .rm-ttd-garis { position: absolute; left: 24px; right: 24px; bottom: 44px; border-bottom: 1px solid #CBD5E1; pointer-events: none; }
  .rm-ttd-petunjuk { position: absolute; left: 0; right: 0; bottom: 18px; text-align: center; font-size: 12px; color: #94A3B8; pointer-events: none; }
  .rm-ttd-pratinjau { max-height: 160px; max-width: 100%; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px; background: #fff; }
  .rm-ttd-hasil img { max-height: 110px; max-width: 280px; display: block; }
  /* Panel approval (Setujui / Tolak). */
  .rm-panel { display: flex; flex-direction: column; border: 1px solid #E2E8F0; border-radius: 12px; padding: 18px 20px; background: #fff; }
  .rm-panel__kepala { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
  .rm-panel__ikon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
  .rm-panel__judul { font-weight: 700; font-size: 14.5px; color: #0F172A; line-height: 1.3; }
  .rm-panel__sub { font-size: 12px; color: #64748B; }
  .rm-panel__kaki { margin-top: auto; padding-top: 16px; display: flex; justify-content: flex-end; }
  .rm-label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
  /* Tema memberi tinggi tetap pada .form-control — textarea dikembalikan ke tinggi isinya. */
  .rm-textarea { height: auto !important; min-height: 76px; resize: vertical; font-size: 13.5px; line-height: 1.45; padding: 10px 12px; }
  .rm-seg { display: inline-flex; background: #F1F5F9; border: 1px solid #E2E8F0; border-radius: 10px; padding: 3px; }
  .rm-seg__opsi { display: inline-flex; align-items: center; gap: 6px; border: 0; background: transparent; padding: 6px 12px; border-radius: 8px;
                  font-size: 12.5px; font-weight: 600; color: #64748B; cursor: pointer; white-space: nowrap; }
  .rm-seg__opsi.is-aktif { background: #fff; color: #1D4ED8; box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
  .rm-seg__opsi:focus-visible { outline: 2px solid #487fff; outline-offset: 1px; }
  .rm-aksi-teks { display: inline-flex; align-items: center; gap: 4px; border: 0; background: none; padding: 0; font-size: 12.5px; font-weight: 600; color: #475569; cursor: pointer; }
  .rm-aksi-teks:hover { text-decoration: underline; }
  @media (max-width: 575px) { .rm-panel__kaki .btn { width: 100%; } }
</style>
@endsection

@section('content')
@php
  $badge = ['PENDING' => ['Menunggu approval', 'warning'], 'APPROVED' => ['Disetujui', 'success'], 'REJECTED' => ['Ditolak', 'danger']];
  [$label, $warna] = $badge[$upload->status] ?? [$upload->status, 'secondary'];
  $hariSingkat = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
  $bulanSingkat = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">{{ $upload->judul }}</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium"><a href="{{ route('dms.master-roster.approval') }}" class="hover-text-primary">Approval Roster</a></li>
    <li>-</li>
    <li class="fw-medium">Detail</li>
  </ul>
</div>

@include('dms.roster-master._alert')

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div class="rm-kepala-teks">
        <h6 class="text-lg fw-semibold mb-4">Jadwal per karyawan</h6>
        @include('dms.roster-master._legenda')
      </div>
      <form method="GET" action="{{ route('dms.master-roster.show', $upload->id) }}" class="rm-cari">
        <input type="search" name="q" value="{{ $cari }}" class="form-control form-control-sm" placeholder="Cari SID / nama… (Enter)" aria-label="Cari SID atau nama">
      </form>
    </div>
  </div>
  <div class="card-body rm-isi">
    <div class="table-responsive rm-gulir">
      @include('dms.roster-master._grid', ['karyawanGrid' => $grid['karyawan'], 'tanggal' => $tanggal, 'nomorAwal' => ($halaman - 1) * 25])
    </div>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-16">
      <span class="text-sm text-secondary-light">{{ number_format($grid['total'], 0, ',', '.') }} karyawan · hal {{ $halaman }}/{{ $totalHalaman }}</span>
      <div class="d-flex gap-2 rm-hal">
        <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman <= 1 ? 'disabled' : '' }}"
           href="{{ request()->fullUrlWithQuery(['hal' => $halaman - 1]) }}">&laquo; Sebelumnya</a>
        <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman >= $totalHalaman ? 'disabled' : '' }}"
           href="{{ request()->fullUrlWithQuery(['hal' => $halaman + 1]) }}">Berikutnya &raquo;</a>
      </div>
    </div>
  </div>
</div>

{{-- ══════════ APPROVAL + TANDA TANGAN (di bawah tabel jadwal) ══════════ --}}
<div class="card radius-8 border-0 shadow-sm mt-24" id="rmApproval">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-4 d-flex align-items-center gap-2">
          Approval &amp; tanda tangan
          <span class="badge bg-{{ $warna }}-focus text-{{ $warna }}-main">{{ $label }}</span>
        </h6>
        <p class="text-sm text-secondary-light mb-0">
          {{ $upload->judul }} &middot; {{ $upload->periode_mulai->format('d/m/Y') }} – {{ $upload->periode_selesai->format('d/m/Y') }}
          &middot; {{ number_format($upload->jumlah_karyawan, 0, ',', '.') }} karyawan
          &middot; {{ implode(', ', $ptUpload) }}
          &middot; diupload {{ $upload->diupload_oleh }} {{ $upload->created_at?->format('d/m/Y H:i') }}
          &middot; <a href="{{ route('dms.master-roster.file', $upload->id) }}" class="text-primary-600">file asli</a>
        </p>
      </div>
      @if ($upload->status !== 'APPROVED')
        <form action="{{ route('dms.master-roster.destroy', $upload->id) }}" method="POST"
              onsubmit="return confirm('Hapus roster ini beserta seluruh jadwalnya?');">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-sm btn-link text-danger-600 p-0">Hapus roster ini</button>
        </form>
      @endif
    </div>
  </div>
  <div class="card-body p-24">
    @if ($upload->isPending() && ! $bolehReview)
      {{-- Assign Role: approve berlaku untuk seluruh isi file, jadi semua perusahaannya harus di-assign. --}}
      <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-16 py-12 mb-0 radius-8 text-sm">
        Roster ini memuat perusahaan yang tidak di-assign ke akun Anda
        ({{ implode(', ', $ptUpload) }}), jadi Anda hanya bisa melihatnya.
        Approval dilakukan oleh user yang di-assign ke seluruh perusahaan tersebut.
      </div>
    @elseif ($upload->isPending())
      @if ($tumpang > 0)
        <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-12 py-8 mb-16 radius-8 text-sm">
          {{ number_format($tumpang, 0, ',', '.') }} jadwal di roster ini bertumpuk dengan roster lain yang sudah disetujui
          (SID &amp; tanggal sama). Bila disetujui, roster ini yang berlaku untuk tanggal-tanggal itu.
        </div>
      @endif

      <div class="row g-4">
        {{-- Setujui: wajib tanda tangan (digambar atau upload). --}}
        <div class="col-xl-8">
          <form action="{{ route('dms.master-roster.approve', $upload->id) }}" method="POST" enctype="multipart/form-data" id="rmFormSetuju" class="rm-panel h-100">
            @csrf
            <input type="hidden" name="ttd_mode" value="gambar" data-ttd-mode>
            <input type="hidden" name="ttd_data" data-ttd-data>

            <div class="rm-panel__kepala">
              <span class="rm-panel__ikon bg-success-focus text-success-main"><iconify-icon icon="solar:check-circle-bold"></iconify-icon></span>
              <div class="flex-grow-1">
                <div class="rm-panel__judul">Setujui roster</div>
                <div class="rm-panel__sub">Tanda tangan approver wajib diisi</div>
              </div>
              <div class="rm-seg" role="tablist" aria-label="Cara tanda tangan">
                <button type="button" class="rm-seg__opsi is-aktif" data-ttd-tab="gambar" aria-pressed="true">
                  <iconify-icon icon="solar:pen-bold"></iconify-icon><span>Gambar</span>
                </button>
                <button type="button" class="rm-seg__opsi" data-ttd-tab="upload" aria-pressed="false">
                  <iconify-icon icon="solar:upload-bold"></iconify-icon><span>Upload gambar</span>
                </button>
              </div>
            </div>

            <div data-ttd-panel="gambar">
              <div class="rm-ttd-kanvas">
                <canvas data-ttd-canvas aria-label="Area tanda tangan"></canvas>
                <span class="rm-ttd-garis" aria-hidden="true"></span>
                <span class="rm-ttd-petunjuk" data-ttd-petunjuk>Tanda tangan di sini (mouse, jari, atau stylus)</span>
              </div>
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-8">
                <span class="text-xs text-secondary-light d-inline-flex align-items-center gap-1">
                  <iconify-icon icon="solar:user-linear"></iconify-icon>{{ auth()->user()?->name }} &middot; {{ now()->format('d/m/Y') }}
                </span>
                <div class="d-flex gap-3">
                  <button type="button" class="rm-aksi-teks" data-ttd-undo>
                    <iconify-icon icon="solar:undo-left-round-linear"></iconify-icon>Batalkan goresan
                  </button>
                  <button type="button" class="rm-aksi-teks text-danger-600" data-ttd-hapus>
                    <iconify-icon icon="solar:eraser-linear"></iconify-icon>Hapus
                  </button>
                </div>
              </div>
            </div>

            <div data-ttd-panel="upload" hidden>
              <input type="file" name="ttd_file" accept="image/png,image/jpeg" class="form-control" data-ttd-file>
              <span class="d-block text-xs text-secondary-light mt-4">PNG / JPG, maks 2 MB — sebaiknya latar putih atau transparan.</span>
              <img alt="Pratinjau tanda tangan" class="rm-ttd-pratinjau mt-8" data-ttd-pratinjau hidden>
            </div>

            <label class="rm-label mt-16" for="rmCatatanSetuju">Catatan persetujuan <span class="fw-normal text-secondary-light">(opsional)</span></label>
            <textarea id="rmCatatanSetuju" name="catatan_review" rows="3" class="form-control rm-textarea" maxlength="1000"
                      placeholder="Mis. sudah dicek sesuai rencana kerja site">{{ old('catatan_review') }}</textarea>

            <span class="d-block text-sm text-danger-600 mt-8" data-ttd-galat hidden></span>
            <div class="rm-panel__kaki">
              <button type="submit" class="btn btn-success-600 radius-8 d-inline-flex align-items-center justify-content-center gap-2 px-20">
                <iconify-icon icon="solar:check-circle-bold"></iconify-icon>Setujui &amp; tanda tangani
              </button>
            </div>
          </form>
        </div>

        {{-- Tolak: cukup alasan. --}}
        <div class="col-xl-4">
          <form action="{{ route('dms.master-roster.reject', $upload->id) }}" method="POST" class="rm-panel h-100">
            @csrf
            <div class="rm-panel__kepala">
              <span class="rm-panel__ikon bg-danger-focus text-danger-main"><iconify-icon icon="solar:close-circle-bold"></iconify-icon></span>
              <div>
                <div class="rm-panel__judul">Tolak roster</div>
                <div class="rm-panel__sub">Pengupload perlu memperbaiki lalu upload ulang</div>
              </div>
            </div>

            <label class="rm-label" for="rmAlasanTolak">Alasan penolakan <span class="text-danger-600">*</span></label>
            <textarea id="rmAlasanTolak" name="catatan_review" rows="6" class="form-control rm-textarea" maxlength="1000"
                      placeholder="Mis. jadwal SID 10023451 tanggal 15–20 belum sesuai pola D/N" required></textarea>

            <div class="rm-panel__kaki">
              <button type="submit" class="btn btn-outline-danger radius-8 w-100 d-inline-flex align-items-center justify-content-center gap-2"
                      onclick="return confirm('Tolak roster ini?');">
                <iconify-icon icon="solar:close-circle-bold"></iconify-icon>Tolak roster
              </button>
            </div>
          </form>
        </div>
      </div>
    @elseif ($upload->status === 'APPROVED')
      <div class="d-flex flex-wrap gap-24 align-items-end">
        <div class="rm-ttd-hasil">
          <span class="d-block text-xs text-secondary-light mb-4">Disetujui oleh</span>
          @if ($upload->ttd_path)
            <img src="{{ route('dms.master-roster.ttd', $upload->id) }}" alt="Tanda tangan {{ $upload->direview_oleh }}">
          @else
            <span class="text-secondary-light text-sm">(tanpa tanda tangan)</span>
          @endif
          <span class="d-block fw-semibold mt-4">{{ $upload->direview_oleh }}</span>
          <span class="d-block text-xs text-secondary-light">
            {{ $upload->direview_pada?->format('d/m/Y H:i') }}
            @if ($upload->ttd_sumber) &middot; {{ $upload->ttd_sumber === 'UPLOAD' ? 'gambar di-upload' : 'digambar' }} @endif
          </span>
        </div>
        @if ($upload->catatan_review)
          <div class="text-sm"><span class="d-block text-secondary-light text-xs">Catatan</span>{{ $upload->catatan_review }}</div>
        @endif
      </div>
    @else
      <p class="text-sm mb-0">Ditolak oleh <b>{{ $upload->direview_oleh }}</b> · {{ $upload->direview_pada?->format('d/m/Y H:i') }}
        @if ($upload->catatan_review) — <i>{{ $upload->catatan_review }}</i> @endif</p>
    @endif
  </div>
</div>
@endsection

@section('page-scripts')
@if ($upload->isPending() && $bolehReview)
{{-- signature_pad (szimek) — tanda tangan digambar di canvas, disimpan PNG. --}}
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    'use strict';

    var form = document.getElementById('rmFormSetuju');
    if (!form) { return; }

    var canvas = form.querySelector('[data-ttd-canvas]');
    var mode = form.querySelector('[data-ttd-mode]');
    var data = form.querySelector('[data-ttd-data]');
    var file = form.querySelector('[data-ttd-file]');
    var pratinjau = form.querySelector('[data-ttd-pratinjau]');
    var petunjuk = form.querySelector('[data-ttd-petunjuk]');
    var galat = form.querySelector('[data-ttd-galat]');

    function tampilGalat(teks) {
        galat.textContent = teks;
        galat.hidden = !teks;
    }

    if (typeof SignaturePad === 'undefined') {
        tampilGalat('Library tanda tangan gagal dimuat (cek koneksi internet). Gunakan tab "Upload gambar".');
        return;
    }

    var pad = new SignaturePad(canvas, { backgroundColor: 'rgba(255,255,255,0)', penColor: '#0F172A', minWidth: 0.8, maxWidth: 2.6 });
    pad.addEventListener('beginStroke', function () { petunjuk.hidden = true; tampilGalat(''); });

    // Canvas mengikuti lebar kotak & kerapatan layar; goresan dipertahankan saat diubah ukurannya.
    function sesuaikan() {
        var rasio = Math.max(window.devicePixelRatio || 1, 1);
        var goresan = pad.toData();
        canvas.width = canvas.offsetWidth * rasio;
        canvas.height = canvas.offsetHeight * rasio;
        canvas.getContext('2d').scale(rasio, rasio);
        pad.clear();
        pad.fromData(goresan);
    }
    window.addEventListener('resize', sesuaikan);
    sesuaikan();

    form.querySelector('[data-ttd-hapus]').addEventListener('click', function () { pad.clear(); petunjuk.hidden = false; });
    form.querySelector('[data-ttd-undo]').addEventListener('click', function () {
        var goresan = pad.toData();
        goresan.pop();
        pad.fromData(goresan);
        petunjuk.hidden = goresan.length > 0;
    });

    form.querySelectorAll('[data-ttd-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var pilih = btn.dataset.ttdTab;
            mode.value = pilih;
            form.querySelectorAll('[data-ttd-tab]').forEach(function (b) {
                var aktif = b === btn;
                b.classList.toggle('is-aktif', aktif);
                b.setAttribute('aria-pressed', aktif ? 'true' : 'false');
            });
            form.querySelectorAll('[data-ttd-panel]').forEach(function (p) { p.hidden = p.dataset.ttdPanel !== pilih; });
            if (pilih === 'gambar') { sesuaikan(); }
            tampilGalat('');
        });
    });

    file.addEventListener('change', function () {
        var f = file.files && file.files[0];
        if (!f) { pratinjau.hidden = true; return; }
        if (!/^image\/(png|jpeg)$/.test(f.type)) { tampilGalat('File harus PNG atau JPG.'); file.value = ''; return; }
        if (f.size > 2 * 1024 * 1024) { tampilGalat('File tanda tangan maksimal 2 MB.'); file.value = ''; return; }
        tampilGalat('');
        pratinjau.src = URL.createObjectURL(f);
        pratinjau.hidden = false;
    });

    form.addEventListener('submit', function (e) {
        if (mode.value === 'gambar') {
            if (pad.isEmpty()) { e.preventDefault(); tampilGalat('Tanda tangan belum digambar.'); return; }
            data.value = pad.toDataURL('image/png');
            file.value = '';
        } else {
            if (!file.files || !file.files.length) { e.preventDefault(); tampilGalat('Pilih file gambar tanda tangan.'); return; }
            data.value = '';
        }
        if (!window.confirm('Setujui roster ini dengan tanda tangan tersebut?')) { e.preventDefault(); }
    });
})();
</script>
@endif
@endsection
