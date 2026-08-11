@php
  $assignment = $assignment ?? null;
  $isEdit = $assignment !== null;
  $val = static function (string $key, mixed $default = '') use ($assignment): mixed {
      return old($key, $assignment?->{$key} ?? $default);
  };
  $siteOptions = $siteOptions ?? [];
  $companyOptions = $companyOptions ?? [];
  $userOptions = $userOptions ?? [];
  $currentSite = (string) $val('site');
  $currentCompany = (string) $val('perusahaan');
  $currentUserId = (int) $val('user_id', 0);
  $isActive = (bool) $val('is_active', true);
@endphp

@if ($errors->has('form'))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ $errors->first('form') }}
</div>
@endif

<div class="row g-3">
  <div class="col-md-6">
    <label for="user_id" class="form-label">User <span class="text-danger">*</span></label>
    <select class="form-select @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required>
      <option value="">— Pilih user —</option>
      @foreach ($userOptions as $user)
        <option value="{{ $user['id'] }}" @selected($currentUserId === (int) $user['id'])>{{ $user['label'] }}</option>
      @endforeach
    </select>
    @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-6">
    <label for="site" class="form-label">Site <span class="text-danger">*</span></label>
    <select class="form-select @error('site') is-invalid @enderror" id="site" name="site" required>
      <option value="">— Pilih site —</option>
      @foreach ($siteOptions as $site)
        <option value="{{ $site }}" @selected($currentSite === $site)>{{ $site }}</option>
      @endforeach
      @if ($currentSite !== '' && ! in_array($currentSite, $siteOptions, true))
        <option value="{{ $currentSite }}" selected>{{ $currentSite }}</option>
      @endif
    </select>
    @error('site')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-6">
    <label for="perusahaan" class="form-label">Perusahaan <span class="text-danger">*</span></label>
    <select class="form-select @error('perusahaan') is-invalid @enderror" id="perusahaan" name="perusahaan" required>
      <option value="">— Pilih perusahaan —</option>
      @foreach ($companyOptions as $company)
        <option value="{{ $company }}" @selected($currentCompany === $company)>{{ $company }}</option>
      @endforeach
      @if ($currentCompany !== '' && ! in_array($currentCompany, $companyOptions, true))
        <option value="{{ $currentCompany }}" selected>{{ $currentCompany }}</option>
      @endif
    </select>
    @error('perusahaan')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-6 d-flex align-items-end">
    <div class="form-check form-switch mb-8">
      <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked($isActive)>
      <label class="form-check-label" for="is_active">Aktif</label>
    </div>
  </div>
</div>

<div class="d-flex align-items-center justify-content-end gap-3 mt-24">
  <a href="{{ route('evaluasi-well.mitra-assignments.index') }}" class="btn btn-outline-secondary radius-8 px-20 py-11">Batal</a>
  <button type="submit" class="btn btn-primary-600 radius-8 px-20 py-11">
    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Assignment' }}
  </button>
</div>
