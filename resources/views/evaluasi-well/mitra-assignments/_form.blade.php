@php
  $assignment = $assignment ?? null;
  $isEdit = $assignment !== null;
  $siteOptions = $siteOptions ?? [];
  $companyOptions = $companyOptions ?? [];
  $userOptions = $userOptions ?? [];
  $currentUserId = (int) old('user_id', $assignment?->user_id ?? 0);
  $isActive = (bool) old('is_active', $assignment?->is_active ?? true);

  $scopeRows = old('scopes');
  if (! is_array($scopeRows) || $scopeRows === []) {
      $scopeRows = $assignment?->groupedCompanySites() ?? [];
  }
  if ($scopeRows === []) {
      $scopeRows = [['perusahaan' => '', 'sites' => []]];
  }
@endphp

@if ($errors->has('form'))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ $errors->first('form') }}
</div>
@endif

<div class="row g-3">
  <div class="col-md-6">
    <label for="user_id" class="form-label">User <span class="text-danger">*</span></label>
    <select class="form-select js-mitra-searchable @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required data-placeholder="Cari user…">
      <option value="">— Pilih user —</option>
      @foreach ($userOptions as $user)
        <option value="{{ $user['id'] }}" @selected($currentUserId === (int) $user['id'])>{{ $user['label'] }}</option>
      @endforeach
    </select>
    @error('user_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-6 d-flex align-items-end">
    <div class="form-check form-switch mb-8">
      <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked($isActive)>
      <label class="form-check-label" for="is_active">Aktif</label>
    </div>
  </div>
</div>

<div class="mt-24">
  <div class="d-flex align-items-center justify-content-between gap-2 mb-12">
    <div>
      <h6 class="fw-semibold mb-4">Perusahaan &amp; Site</h6>
      <p class="text-secondary-light text-sm mb-0">Satu assignment bisa punya banyak perusahaan; tiap perusahaan punya banyak site.</p>
    </div>
    <button type="button" class="btn btn-outline-primary-600 btn-sm radius-8" id="js-add-company">
      Tambah perusahaan
    </button>
  </div>
  @error('scopes')<div class="text-danger text-sm mb-12">{{ $message }}</div>@enderror

  <div id="js-company-rows" class="d-flex flex-column gap-16">
    @foreach ($scopeRows as $index => $row)
      @php
        $rowCompany = trim((string) ($row['perusahaan'] ?? ''));
        $rowSites = is_array($row['sites'] ?? null) ? $row['sites'] : [];
      @endphp
      <div class="js-company-row border radius-8 p-16 bg-neutral-50">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-12">
          <span class="fw-medium text-sm">Perusahaan <span class="js-company-ordinal">{{ $index + 1 }}</span></span>
          <button type="button" class="btn btn-outline-danger-600 btn-sm radius-8 js-remove-company">Hapus</button>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Perusahaan <span class="text-danger">*</span></label>
            <select class="form-select js-mitra-searchable @error('scopes.'.$index.'.perusahaan') is-invalid @enderror" name="scopes[{{ $index }}][perusahaan]" required data-placeholder="Cari perusahaan…">
              <option value="">— Pilih perusahaan —</option>
              @foreach ($companyOptions as $company)
                <option value="{{ $company }}" @selected($rowCompany === $company)>{{ $company }}</option>
              @endforeach
              @if ($rowCompany !== '' && ! in_array($rowCompany, $companyOptions, true))
                <option value="{{ $rowCompany }}" selected>{{ $rowCompany }}</option>
              @endif
            </select>
            @error('scopes.'.$index.'.perusahaan')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label class="form-label">Site <span class="text-danger">*</span></label>
            <select class="form-select js-mitra-searchable-multi @error('scopes.'.$index.'.sites') is-invalid @enderror" name="scopes[{{ $index }}][sites][]" multiple required data-placeholder="Pilih satu atau lebih site…">
              @foreach ($siteOptions as $site)
                <option value="{{ $site }}" @selected(in_array($site, $rowSites, true))>{{ $site }}</option>
              @endforeach
              @foreach ($rowSites as $site)
                @if ($site !== '' && ! in_array($site, $siteOptions, true))
                  <option value="{{ $site }}" selected>{{ $site }}</option>
                @endif
              @endforeach
            </select>
            @error('scopes.'.$index.'.sites')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @error('scopes.'.$index.'.sites.0')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>

<template id="js-company-row-template">
  <div class="js-company-row border radius-8 p-16 bg-neutral-50">
    <div class="d-flex align-items-start justify-content-between gap-2 mb-12">
      <span class="fw-medium text-sm">Perusahaan <span class="js-company-ordinal">1</span></span>
      <button type="button" class="btn btn-outline-danger-600 btn-sm radius-8 js-remove-company">Hapus</button>
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Perusahaan <span class="text-danger">*</span></label>
        <select class="form-select js-mitra-searchable" name="scopes[__INDEX__][perusahaan]" required data-placeholder="Cari perusahaan…">
          <option value="">— Pilih perusahaan —</option>
          @foreach ($companyOptions as $company)
            <option value="{{ $company }}">{{ $company }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Site <span class="text-danger">*</span></label>
        <select class="form-select js-mitra-searchable-multi" name="scopes[__INDEX__][sites][]" multiple required data-placeholder="Pilih satu atau lebih site…">
          @foreach ($siteOptions as $site)
            <option value="{{ $site }}">{{ $site }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>
</template>

<div class="d-flex align-items-center justify-content-end gap-3 mt-24">
  <a href="{{ route('evaluasi-well.mitra-assignments.index') }}" class="btn btn-outline-secondary radius-8 px-20 py-11">Batal</a>
  <button type="submit" class="btn btn-primary-600 radius-8 px-20 py-11">
    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Assignment' }}
  </button>
</div>
