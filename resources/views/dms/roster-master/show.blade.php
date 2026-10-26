@extends('dms.layouts.app')

@section('title', 'Roster — '.$upload->judul)

@section('css')
<style>
  /* Tabel jadwal meniru template Excel "ROSTER KERJA KARYAWAN". */
  .rm-xl { border-collapse: separate; border-spacing: 0; font-size: 11.5px; font-family: Calibri, 'Segoe UI', sans-serif; }
  .rm-xl th, .rm-xl td { white-space: nowrap; border-right: 1px solid #BFBFBF; border-bottom: 1px solid #BFBFBF; padding: 3px 6px; }
  .rm-xl tr > :first-child { border-left: 1px solid #BFBFBF; }
  .rm-xl thead tr:first-child > * { border-top: 1px solid #BFBFBF; }
  .rm-xl thead th { position: sticky; z-index: 2; text-align: center; font-weight: 700; }
  .rm-xl thead tr:nth-child(1) th { top: 0; }
  .rm-xl thead tr:nth-child(2) th { top: 24px; }
  .rm-xl thead tr:nth-child(3) th { top: 46px; }
  .rm-xl .rm-h-id { background: #1F3864; color: #fff; vertical-align: middle; white-space: normal; line-height: 1.25; }
  .rm-xl .rm-h-tgl { background: #fff; color: #C00000; height: 24px; min-width: 38px; }
  .rm-xl .rm-h-hari, .rm-xl .rm-h-bln { background: #D9E1F2; color: #1F3864; height: 22px; }
  .rm-xl .rm-minggu { background: #C00000 !important; color: #fff !important; }
  .rm-xl .rm-c-id { background: #FFF2CC; color: #1F2937; }
  .rm-xl .rm-c-no, .rm-xl .rm-c-sid, .rm-xl .rm-c-jam { text-align: center; }
  /* Tiga kolom pertama (No, SID, Nama) tetap terlihat saat digulir ke kanan. */
  .rm-xl .rm-s1, .rm-xl .rm-s2, .rm-xl .rm-s3 { position: sticky; z-index: 1; }
  .rm-xl thead .rm-s1, .rm-xl thead .rm-s2, .rm-xl thead .rm-s3 { z-index: 4; }
  .rm-xl .rm-s1 { left: 0; min-width: 40px; }
  .rm-xl .rm-s2 { left: 40px; min-width: 76px; }
  .rm-xl .rm-s3 { left: 116px; min-width: 190px; box-shadow: 2px 0 0 #8EA9DB; }
  .rm-xl .rm-c-kode { padding: 0; text-align: center; }
  .rm-sel { min-width: 38px; height: 22px; line-height: 22px; text-align: center; font-weight: 700; font-size: 11px; }
  .rm-xl .rm-bulan { border-left: 2px solid #1F3864 !important; }
  /* Warna kode sama dengan template Excel (DmsRosterMasterService::KODE). */
  @foreach (\App\Services\Dms\Roster\DmsRosterMasterService::KODE as $kodeCss => [, $warnaCss])
  .rm-{{ $kodeCss }} { background: #{{ $warnaCss }}; color: #1F2937; }
  @endforeach
  /* Kode lama (sebelum D1–D13 / N1–N7) tetap berwarna. */
  .rm-DS { background: #FFE699; color: #1F2937; }
  .rm-NS { background: #9DC3E6; color: #1F2937; }
  .rm-lain { background: #EDEDED; color: #1F2937; }
  .rm-kosong { background: #fff; }
  .rm-libur { color: #DC2626 !important; }

  /* Tanda tangan approver (signature_pad). */
  .rm-ttd-kanvas { position: relative; height: 200px; border: 1.5px dashed #94A3B8; border-radius: 10px; background: #fff; touch-action: none; }
  .rm-ttd-kanvas canvas { position: absolute; inset: 0; width: 100%; height: 100%; border-radius: 10px; cursor: crosshair; }
  .rm-ttd-garis { position: absolute; left: 24px; right: 24px; bottom: 44px; border-bottom: 1px solid #CBD5E1; pointer-events: none; }
  .rm-ttd-petunjuk { position: absolute; left: 0; right: 0; bottom: 18px; text-align: center; font-size: 12px; color: #94A3B8; pointer-events: none; }
  .rm-ttd-pratinjau { max-height: 160px; max-width: 100%; border: 1px solid #E2E8F0; border-radius: 8px; padding: 6px; background: #fff; }
  .rm-ttd-hasil img { max-height: 110px; max-width: 280px; display: block; }
  .rm-tolak { border-left: 1px solid #EEF2F6; padding-left: 24px; }
  @media (max-width: 1199px) { .rm-tolak { border-left: 0; padding-left: 0; border-top: 1px solid #EEF2F6; padding-top: 16px; } }
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
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h6 class="text-lg fw-semibold mb-4">Jadwal per karyawan</h6>
        <div class="d-flex flex-wrap gap-3 text-xs text-secondary-light">
          @foreach (\App\Services\Dms\Roster\DmsRosterMasterService::legenda() as [$kodeLeg, $ketLeg, $warnaLeg, $mLeg, $kLeg])
            <span><span class="rm-sel d-inline-flex align-items-center justify-content-center px-4" style="width:auto;min-width:30px;height:18px;background:#{{ $warnaLeg }}">{{ $kodeLeg }}</span> {{ $ketLeg }}@if ($mLeg) <span class="text-secondary-light">({{ $mLeg }}–{{ $kLeg }})</span>@endif</span>
          @endforeach
          <span><span class="rm-sel rm-kosong d-inline-block align-middle" style="width:30px;height:18px"></span> tidak dijadwalkan</span>
        </div>
      </div>
      <form method="GET" action="{{ route('dms.master-roster.show', $upload->id) }}">
        <input type="search" name="q" value="{{ $cari }}" class="form-control form-control-sm" placeholder="Cari SID / nama… (Enter)">
      </form>
    </div>
  </div>
  <div class="card-body p-24">
    <div class="table-responsive" style="max-height:70vh">
      @php
        $kelasKode = static fn (?string $kode): string => $kode === null ? 'rm-kosong'
            : (isset(\App\Services\Dms\Roster\DmsRosterMasterService::KODE[$kode]) || in_array($kode, ['DS', 'NS'], true) ? 'rm-'.$kode : 'rm-lain');
        $nomorAwal = ($halaman - 1) * 25;
      @endphp
      <table class="rm-xl">
        <thead>
          <tr>
            <th rowspan="3" class="rm-h-id rm-s1">No</th>
            <th rowspan="3" class="rm-h-id rm-s2">SID</th>
            <th rowspan="3" class="rm-h-id rm-s3">Nama Karyawan</th>
            <th rowspan="3" class="rm-h-id" style="min-width:160px">Perusahaan</th>
            <th rowspan="3" class="rm-h-id" style="min-width:140px">Jabatan</th>
            <th rowspan="3" class="rm-h-id" style="min-width:64px">Site</th>
            <th rowspan="3" class="rm-h-id" style="min-width:64px">Jam Kerja Awal</th>
            <th rowspan="3" class="rm-h-id" style="min-width:64px">Jam Kerja Akhir</th>
            @foreach ($tanggal as $t)
              <th class="rm-h-tgl {{ $t->dayOfWeek === 0 ? 'rm-minggu' : '' }} {{ $t->day === 1 && ! $loop->first ? 'rm-bulan' : '' }}" title="{{ $t->format('d/m/Y') }}">{{ $t->format('d') }}</th>
            @endforeach
          </tr>
          <tr>
            @foreach ($tanggal as $t)
              <th class="rm-h-hari {{ $t->dayOfWeek === 0 ? 'rm-minggu' : '' }} {{ $t->day === 1 && ! $loop->first ? 'rm-bulan' : '' }}">{{ $hariSingkat[$t->dayOfWeek] }}</th>
            @endforeach
          </tr>
          <tr>
            @foreach ($tanggal as $t)
              <th class="rm-h-bln {{ $t->dayOfWeek === 0 ? 'rm-minggu' : '' }} {{ $t->day === 1 && ! $loop->first ? 'rm-bulan' : '' }}">{{ $bulanSingkat[$t->month] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @forelse ($grid['karyawan'] as $k)
            <tr>
              <td class="rm-c-id rm-c-no rm-s1">{{ $nomorAwal + $loop->iteration }}</td>
              <td class="rm-c-id rm-c-sid rm-s2">{{ $k['sid'] }}</td>
              <td class="rm-c-id rm-s3">{{ $k['nama'] ?? '—' }}</td>
              <td class="rm-c-id">{{ $k['perusahaan'] ?? '—' }}</td>
              <td class="rm-c-id">{{ $k['jabatan'] ?? '—' }}</td>
              <td class="rm-c-id">{{ $k['site'] ?? '—' }}</td>
              <td class="rm-c-id rm-c-jam">{{ $k['jam_awal'] ?? '' }}</td>
              <td class="rm-c-id rm-c-jam">{{ $k['jam_akhir'] ?? '' }}</td>
              @foreach ($tanggal as $t)
                @php
                  $h = $k['jadwal'][$t->toDateString()] ?? null;
                  $kode = $h['kode'] ?? null;
                  $jamTeks = $h && $h['masuk'] ? ' · '.$h['masuk'].'–'.$h['keluar'].' ('.round($h['menit'] / 60, 2).' jam)' : '';
                @endphp
                <td class="rm-c-kode {{ $t->day === 1 && ! $loop->first ? 'rm-bulan' : '' }}"><div class="rm-sel {{ $kelasKode($kode) }}" title="{{ $t->format('d/m/Y') }} — {{ $kode ?? 'tidak dijadwalkan' }}{{ $jamTeks }}">{{ $kode }}</div></td>
              @endforeach
            </tr>
          @empty
            <tr><td colspan="{{ count($tanggal) + 8 }}" class="text-secondary-light py-16">Tidak ada karyawan yang cocok.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-16">
      <span class="text-sm text-secondary-light">{{ number_format($grid['total'], 0, ',', '.') }} karyawan · hal {{ $halaman }}/{{ $totalHalaman }}</span>
      <div class="d-flex gap-2">
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
    @if ($upload->isPending())
      @if ($tumpang > 0)
        <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-12 py-8 mb-16 radius-8 text-sm">
          {{ number_format($tumpang, 0, ',', '.') }} jadwal di roster ini bertumpuk dengan roster lain yang sudah disetujui
          (SID &amp; tanggal sama). Bila disetujui, roster ini yang berlaku untuk tanggal-tanggal itu.
        </div>
      @endif

      <div class="row gy-4">
        <div class="col-xl-7">
          <form action="{{ route('dms.master-roster.approve', $upload->id) }}" method="POST" enctype="multipart/form-data" id="rmFormSetuju">
            @csrf
            <input type="hidden" name="ttd_mode" value="gambar" data-ttd-mode>
            <input type="hidden" name="ttd_data" data-ttd-data>

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-8">
              <span class="fw-semibold text-sm">Tanda tangan approver <span class="text-danger-600">*</span></span>
              <div class="btn-group btn-group-sm" role="tablist" aria-label="Cara tanda tangan">
                <button type="button" class="btn btn-primary-600" data-ttd-tab="gambar" aria-pressed="true">
                  <iconify-icon icon="solar:pen-bold" class="align-middle"></iconify-icon> Gambar
                </button>
                <button type="button" class="btn btn-outline-primary-600" data-ttd-tab="upload" aria-pressed="false">
                  <iconify-icon icon="solar:upload-bold" class="align-middle"></iconify-icon> Upload gambar
                </button>
              </div>
            </div>

            <div data-ttd-panel="gambar">
              <div class="rm-ttd-kanvas">
                <canvas data-ttd-canvas aria-label="Area tanda tangan"></canvas>
                <span class="rm-ttd-garis" aria-hidden="true"></span>
                <span class="rm-ttd-petunjuk" data-ttd-petunjuk>Tanda tangan di sini (mouse, jari, atau stylus)</span>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-8">
                <span class="text-xs text-secondary-light">{{ auth()->user()?->name }} &middot; {{ now()->format('d/m/Y') }}</span>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-ttd-undo>Batalkan goresan</button>
                  <button type="button" class="btn btn-sm btn-outline-danger radius-8" data-ttd-hapus>Hapus</button>
                </div>
              </div>
            </div>

            <div data-ttd-panel="upload" hidden>
              <input type="file" name="ttd_file" accept="image/png,image/jpeg" class="form-control" data-ttd-file>
              <span class="text-xs text-secondary-light">PNG / JPG, maks 2 MB — sebaiknya latar putih atau transparan.</span>
              <img alt="Pratinjau tanda tangan" class="rm-ttd-pratinjau mt-8" data-ttd-pratinjau hidden>
            </div>

            <textarea name="catatan_review" rows="2" class="form-control form-control-sm mt-16" maxlength="1000" placeholder="Catatan persetujuan (opsional)">{{ old('catatan_review') }}</textarea>
            <span class="d-block text-sm text-danger-600 mt-8" data-ttd-galat hidden></span>
            <button type="submit" class="btn btn-success-600 radius-8 mt-12 d-inline-flex align-items-center gap-1">
              <iconify-icon icon="solar:check-circle-bold"></iconify-icon>Setujui &amp; tanda tangani
            </button>
          </form>
        </div>

        <div class="col-xl-5">
          <form action="{{ route('dms.master-roster.reject', $upload->id) }}" method="POST" class="rm-tolak">
            @csrf
            <span class="fw-semibold text-sm d-block mb-8">Tolak roster</span>
            <textarea name="catatan_review" rows="4" class="form-control form-control-sm mb-8" maxlength="1000" placeholder="Alasan penolakan (wajib)" required></textarea>
            <button type="submit" class="btn btn-outline-danger radius-8 d-inline-flex align-items-center gap-1"
                    onclick="return confirm('Tolak roster ini?');">
              <iconify-icon icon="solar:close-circle-bold"></iconify-icon>Tolak
            </button>
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
@if ($upload->isPending())
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
                b.classList.toggle('btn-primary-600', aktif);
                b.classList.toggle('btn-outline-primary-600', !aktif);
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
