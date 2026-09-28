@php
  /** @var \App\Models\SidRosterBannedMaster|null $item */
  $item = $item ?? null;
  $isEdit = $item !== null;
  $val = static function (string $key, mixed $default = '') use ($item): mixed {
      if (old($key) !== null) {
          return old($key);
      }
      if ($item === null) {
          return $default;
      }
      $value = $item->{$key} ?? $default;

      return $value instanceof \Illuminate\Support\Carbon ? $value->format('Y-m-d') : $value;
  };
@endphp

@if ($errors->any())
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8" role="alert">
  <ul class="mb-0 ps-18">
    @foreach ($errors->all() as $error)
      <li>{{ $error }}</li>
    @endforeach
  </ul>
</div>
@endif

<div class="row g-3">
  <div class="col-md-4">
    <label for="nik" class="form-label">NIK <span class="text-danger">*</span></label>
    <input type="text" class="form-control @error('nik') is-invalid @enderror" id="nik" name="nik" value="{{ $val('nik') }}" required maxlength="64">
    @error('nik')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label for="sid" class="form-label">SID</label>
    <input type="text" class="form-control @error('sid') is-invalid @enderror" id="sid" name="sid" value="{{ $val('sid') }}" maxlength="20">
    @error('sid')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label for="tanggal_pelanggaran" class="form-label">Tanggal Pelanggaran</label>
    <input type="date" class="form-control @error('tanggal_pelanggaran') is-invalid @enderror" id="tanggal_pelanggaran" name="tanggal_pelanggaran" value="{{ $val('tanggal_pelanggaran') }}">
    @error('tanggal_pelanggaran')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-6">
    <label for="nama" class="form-label">Nama <span class="text-danger">*</span></label>
    <input type="text" class="form-control @error('nama') is-invalid @enderror" id="nama" name="nama" value="{{ $val('nama') }}" required maxlength="255">
    @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-6">
    <label for="perusahaan" class="form-label">Perusahaan</label>
    <input type="text" class="form-control @error('perusahaan') is-invalid @enderror" id="perusahaan" name="perusahaan" value="{{ $val('perusahaan') }}" maxlength="255">
    @error('perusahaan')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-4">
    <label for="site_dedicated" class="form-label">Site Dedicated</label>
    <input type="text" class="form-control @error('site_dedicated') is-invalid @enderror" id="site_dedicated" name="site_dedicated" value="{{ $val('site_dedicated') }}" maxlength="50">
    @error('site_dedicated')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-8">
    <label for="alasan_pelanggaran" class="form-label">Alasan Pelanggaran <span class="text-danger">*</span></label>
    <textarea class="form-control @error('alasan_pelanggaran') is-invalid @enderror" id="alasan_pelanggaran" name="alasan_pelanggaran" required maxlength="500" rows="2">{{ $val('alasan_pelanggaran') }}</textarea>
    @error('alasan_pelanggaran')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  @if ($isEdit)
  <div class="col-12">
    <div class="bg-neutral-50 radius-8 p-16 text-sm text-secondary-light">
      Dibuat oleh: {{ $item->created_by ?? '-' }} &middot; {{ $item->created_at?->format('d M Y H:i') ?? '-' }}
    </div>
  </div>
  @endif

  <div class="col-12 d-flex flex-wrap gap-2 mt-8">
    <button type="submit" class="btn btn-primary-600 radius-8 px-20 py-11">
      <iconify-icon icon="solar:diskette-outline" class="icon"></iconify-icon>
      {{ $isEdit ? 'Simpan Perubahan' : 'Simpan' }}
    </button>
    <a href="{{ route('pra-operasi.roster-banned.index') }}" class="btn btn-outline-secondary radius-8 px-20 py-11">Batal</a>
  </div>
</div>
