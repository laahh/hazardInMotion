@extends('dms.layouts.app')

@section('title', 'Upload Roster')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Upload Roster</h6>
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

<div class="row gy-4">
  <div class="col-xxl-5">
    <div class="card radius-8 border-0 shadow-sm h-100">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-4">Upload jadwal roster karyawan</h6>
        <p class="text-sm text-secondary-light mb-0">Excel satu baris per karyawan, satu kolom per tanggal. Setelah upload, roster menunggu approval.</p>
      </div>
      <div class="card-body p-24">
        <form action="{{ route('dms.master-roster.store') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-3">
          @csrf
          <div>
            <label for="rmJudul" class="form-label fw-semibold text-sm">Judul roster <span class="text-danger-600">*</span></label>
            <input type="text" id="rmJudul" name="judul" value="{{ old('judul') }}" class="form-control" maxlength="150" required
                   placeholder="mis. Roster PAMA BMO 2 — November 2026">
          </div>
          <div>
            <label for="rmFile" class="form-label fw-semibold text-sm">File Excel <span class="text-danger-600">*</span></label>
            <input type="file" id="rmFile" name="file" accept=".xlsx,.xls" class="form-control" required>
            <span class="text-xs text-secondary-light">.xlsx / .xls, maks 10 MB, maks 1 tahun (366 hari) per file. Paling mudah: download template di bawah lalu isi.</span>
          </div>
          <div>
            <label for="rmCatatan" class="form-label fw-semibold text-sm">Catatan untuk approver</label>
            <textarea id="rmCatatan" name="catatan" rows="3" class="form-control" maxlength="1000">{{ old('catatan') }}</textarea>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary-600 radius-8 d-inline-flex align-items-center gap-1">
              <iconify-icon icon="solar:upload-bold"></iconify-icon>Upload roster
            </button>
          </div>
        </form>

        <hr class="my-24">

        {{-- Template untuk rentang bulan (maks 12 bulan dalam satu sheet). --}}
        <form action="{{ route('dms.master-roster.template') }}" method="GET" class="d-flex flex-wrap align-items-end gap-2" id="rmTemplate">
          <div class="w-100">
            <label for="rmPt" class="form-label fw-semibold text-sm">Perusahaan</label>
            @if ($perusahaan === null)
              <select id="rmPt" name="perusahaan" class="form-select form-select-sm" disabled>
                <option>Daftar perusahaan belum tersedia</option>
              </select>
              <span class="text-xs text-danger-600" @if ($perusahaanGalat) title="{{ $perusahaanGalat }}" @endif>
                Data karyawan aktif belum terambil — menunggu sinkronisasi RFID berikutnya. Template kosong tetap bisa didownload.
              </span>
            @else
              <select id="rmPt" name="perusahaan" class="form-select form-select-sm">
                <option value="">— Tanpa perusahaan (template kosong + contoh) —</option>
                @foreach ($perusahaan as $p)
                  <option value="{{ $p['nama'] }}" @selected(old('perusahaan') === $p['nama'])>{{ $p['nama'] }} ({{ number_format($p['jumlah'], 0, ',', '.') }} karyawan)</option>
                @endforeach
              </select>
              <span class="text-xs text-secondary-light">Pilih perusahaan → SID, nama, jabatan &amp; site karyawan aktifnya langsung terisi; tinggal isi kode roster per tanggal.</span>
            @endif
          </div>
          <div>
            <label for="rmDari" class="form-label fw-semibold text-sm">Template dari bulan</label>
            <input type="month" id="rmDari" name="dari" value="{{ $bulanTemplate }}" class="form-control form-control-sm" required>
          </div>
          <div>
            <label for="rmSampai" class="form-label fw-semibold text-sm">sampai bulan</label>
            <input type="month" id="rmSampai" name="sampai" value="{{ $bulanTemplate }}" class="form-control form-control-sm" required>
          </div>
          <button type="submit" class="btn btn-sm btn-outline-secondary radius-8 d-inline-flex align-items-center gap-1">
            <iconify-icon icon="solar:file-download-bold"></iconify-icon>Download template
          </button>
          <div class="w-100 d-flex flex-wrap gap-2 align-items-center">
            <span class="text-xs text-secondary-light">Cepat:</span>
            @foreach ([$tahunTemplate - 1, $tahunTemplate] as $th)
              <a href="{{ route('dms.master-roster.template', ['dari' => $th.'-01', 'sampai' => $th.'-12']) }}" data-rm-cepat class="btn btn-sm btn-outline-primary-600 radius-8 py-2 px-10 text-xs">
                Setahun {{ $th }} (Jan–Des)
              </a>
            @endforeach
            <span class="text-xs text-secondary-light">maks {{ \App\Services\Dms\Roster\DmsRosterMasterService::MAKS_BULAN }} bulan per file</span>
          </div>
          <span class="w-100 text-xs text-danger-600" data-rm-galat hidden></span>
        </form>
        <script>
        // Cegah rentang terbalik / lebih dari 12 bulan sebelum download.
        (function () {
            var f = document.getElementById('rmTemplate');
            if (!f) { return; }
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
                    var pt = f.perusahaan && !f.perusahaan.disabled ? f.perusahaan.value : '';
                    if (!pt) { return; }
                    e.preventDefault();
                    var u = new URL(btn.href, location.href);
                    u.searchParams.set('perusahaan', pt);
                    location.href = u.toString();
                });
            });
        })();
        </script>

        <div class="alert alert-info bg-info-100 text-info-600 border-info-100 px-16 py-12 mt-24 mb-0 radius-8 text-sm">
          <strong>Format "ROSTER KERJA KARYAWAN":</strong> baris tanggal di judul kolom berisi tanggal asli
          (untuk bulan lain cukup ganti tanggal pertama di template); judul kolom No, SID, Nama Karyawan, Perusahaan, Jabatan, Site, Jam Check-in, Jam Check-out, Total Jam Kerja,
          lalu satu kolom per tanggal berurutan. Kolom jam boleh kosong = ikut jam bawaan kode
          ({{ collect(\App\Services\Dms\Roster\DmsRosterMasterService::KODE)->filter(fn ($k) => $k[2] !== null)->map(fn ($k, $kode) => $kode.' '.$k[2].'–'.$k[3])->implode(', ') }}).
          Kode: @foreach (\App\Services\Dms\Roster\DmsRosterMasterService::KODE as $kodeInfo => [$ketInfo])<b>{{ $kodeInfo }}</b> ({{ $ketInfo }}){{ $loop->last ? '' : ', ' }}@endforeach;
          sel kosong = tidak dijadwalkan. File dengan satu kesalahan saja ditolak utuh beserta daftar kesalahannya.
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-7">
    <div class="card radius-8 border-0 shadow-sm h-100">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-0">Riwayat upload</h6>
      </div>
      <div class="card-body p-24">
        @include('dms.roster-master._daftar', ['daftar' => $daftar])
      </div>
    </div>
  </div>
</div>
@endsection
