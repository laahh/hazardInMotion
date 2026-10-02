@php
  /** @var array<string,mixed>|null $employee */
  $employee = $employee ?? null;
  $isEdit = $employee !== null;
  $val = static function (string $key, mixed $default = '') use ($employee): mixed {
      return old($key, $employee[$key] ?? $default);
  };
@endphp

<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8 d-flex align-items-start gap-2" role="alert">
  <iconify-icon icon="solar:danger-triangle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div>
    Perubahan ini menulis langsung ke <strong>database produksi BeWell</strong> (<code>employee_profiles</code>).
    Password login otomatis di-set sama dengan <strong>Kode SID</strong> (bcrypt), sesuai instruksi app BeWell.
  </div>
</div>

@if ($errors->has('form'))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ $errors->first('form') }}
</div>
@endif

<div class="row g-3">
  <div class="col-md-6">
    <label for="nama" class="form-label">Nama <span class="text-danger">*</span></label>
    <input type="text" class="form-control @error('nama') is-invalid @enderror" id="nama" name="nama" value="{{ $val('nama') }}" required maxlength="255">
    @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-3">
    <label for="kode_sid" class="form-label">Kode SID <span class="text-danger">*</span></label>
    <input type="text" class="form-control @error('kode_sid') is-invalid @enderror" id="kode_sid" name="kode_sid" value="{{ $val('kode_sid') }}" required maxlength="64">
    @error('kode_sid')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-3">
    <label for="nik" class="form-label">NIK</label>
    <input type="text" class="form-control @error('nik') is-invalid @enderror" id="nik" name="nik" value="{{ $val('nik') }}" maxlength="64">
    @error('nik')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-4">
    <label for="status_karyawan" class="form-label">Status <span class="text-danger">*</span></label>
    @php $currentStatus = (string) $val('status_karyawan', 'AKTIF'); @endphp
    <select class="form-select @error('status_karyawan') is-invalid @enderror" id="status_karyawan" name="status_karyawan" required>
      @foreach (($statusOptions ?? ['AKTIF', 'NONAKTIF']) as $status)
        <option value="{{ $status }}" @selected($currentStatus === $status)>{{ $status }}</option>
      @endforeach
      @if ($currentStatus !== '' && ! in_array($currentStatus, $statusOptions ?? ['AKTIF', 'NONAKTIF'], true))
        <option value="{{ $currentStatus }}" selected>{{ $currentStatus }}</option>
      @endif
    </select>
    @error('status_karyawan')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label for="site" class="form-label">Site</label>
    <input type="text" class="form-control @error('site') is-invalid @enderror" id="site" name="site" value="{{ $val('site') }}" maxlength="100">
    @error('site')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label for="usia" class="form-label">Usia</label>
    <input type="number" class="form-control @error('usia') is-invalid @enderror" id="usia" name="usia" value="{{ $val('usia') }}" min="0" max="120">
    @error('usia')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-4">
    <label for="divisi" class="form-label">Divisi</label>
    <input type="text" class="form-control @error('divisi') is-invalid @enderror" id="divisi" name="divisi" value="{{ $val('divisi') }}" maxlength="255">
    @error('divisi')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label for="departement" class="form-label">Departemen</label>
    <input type="text" class="form-control @error('departement') is-invalid @enderror" id="departement" name="departement" value="{{ $val('departement') }}" maxlength="255">
    @error('departement')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label for="dept_dic" class="form-label">Dept DIC</label>
    <input type="text" class="form-control @error('dept_dic') is-invalid @enderror" id="dept_dic" name="dept_dic" value="{{ $val('dept_dic') }}" maxlength="255">
    @error('dept_dic')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-6">
    <label for="nama_perusahaan" class="form-label">Nama Perusahaan</label>
    <input type="text" class="form-control @error('nama_perusahaan') is-invalid @enderror" id="nama_perusahaan" name="nama_perusahaan" value="{{ $val('nama_perusahaan') }}" maxlength="255">
    @error('nama_perusahaan')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-3">
    <label for="id_perusahaan" class="form-label">ID Perusahaan</label>
    <input type="number" class="form-control @error('id_perusahaan') is-invalid @enderror" id="id_perusahaan" name="id_perusahaan" value="{{ $val('id_perusahaan') }}" min="0">
    @error('id_perusahaan')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-3">
    <label for="masa_kerja" class="form-label">Masa Kerja</label>
    <input type="text" class="form-control @error('masa_kerja') is-invalid @enderror" id="masa_kerja" name="masa_kerja" value="{{ $val('masa_kerja') }}" maxlength="100">
    @error('masa_kerja')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-4">
    <label for="jabatan_fungsional" class="form-label">Jabatan Fungsional</label>
    <input type="text" class="form-control @error('jabatan_fungsional') is-invalid @enderror" id="jabatan_fungsional" name="jabatan_fungsional" value="{{ $val('jabatan_fungsional') }}" maxlength="255">
    @error('jabatan_fungsional')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label for="jabatan_struktural" class="form-label">Jabatan Struktural</label>
    <input type="text" class="form-control @error('jabatan_struktural') is-invalid @enderror" id="jabatan_struktural" name="jabatan_struktural" value="{{ $val('jabatan_struktural') }}" maxlength="255">
    @error('jabatan_struktural')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label for="level_jabatan" class="form-label">Level Jabatan</label>
    <input type="text" class="form-control @error('level_jabatan') is-invalid @enderror" id="level_jabatan" name="level_jabatan" value="{{ $val('level_jabatan') }}" maxlength="100">
    @error('level_jabatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-4">
    <label for="kategori" class="form-label">Kategori</label>
    <input type="text" class="form-control @error('kategori') is-invalid @enderror" id="kategori" name="kategori" value="{{ $val('kategori') }}" maxlength="100">
    @error('kategori')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label for="kategori_karyawan" class="form-label">Kategori Karyawan</label>
    <input type="text" class="form-control @error('kategori_karyawan') is-invalid @enderror" id="kategori_karyawan" name="kategori_karyawan" value="{{ $val('kategori_karyawan') }}" maxlength="100">
    @error('kategori_karyawan')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label for="membership_tier" class="form-label">Membership Tier</label>
    <input type="text" class="form-control @error('membership_tier') is-invalid @enderror" id="membership_tier" name="membership_tier" value="{{ $val('membership_tier') }}" maxlength="100">
    @error('membership_tier')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-6">
    <label for="avatar_url" class="form-label">Avatar URL</label>
    <input type="text" class="form-control @error('avatar_url') is-invalid @enderror" id="avatar_url" name="avatar_url" value="{{ $val('avatar_url') }}" maxlength="500" placeholder="https://...">
    @error('avatar_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-6">
    <label for="foto" class="form-label">Foto (path/URL)</label>
    <input type="text" class="form-control @error('foto') is-invalid @enderror" id="foto" name="foto" value="{{ $val('foto') }}" maxlength="500">
    @error('foto')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  @if ($isEdit)
  <div class="col-12">
    <div class="bg-neutral-50 radius-8 p-16 text-sm text-secondary-light">
      <div class="fw-semibold text-primary-light mb-8">Audit login (read-only)</div>
      <div class="row g-2">
        <div class="col-md-3">Last login: {{ $employee['last_login_at'] ?? '-' }}</div>
        <div class="col-md-3">Login count: {{ $employee['login_count'] ?? '-' }}</div>
        <div class="col-md-3">Last IP: {{ $employee['last_login_ip'] ?? '-' }}</div>
        <div class="col-md-3">Platform: {{ $employee['last_platform'] ?? '-' }}</div>
      </div>
    </div>
  </div>
  @endif

  <div class="col-12 d-flex flex-wrap gap-2 mt-8">
    <button type="submit" class="btn btn-primary-600 radius-8 px-20 py-11">
      <iconify-icon icon="solar:diskette-outline" class="icon"></iconify-icon>
      {{ $isEdit ? 'Simpan Perubahan' : 'Simpan' }}
    </button>
    <a href="{{ route('evaluasi-well.users.index') }}" class="btn btn-outline-secondary radius-8 px-20 py-11">Batal</a>
  </div>
</div>
