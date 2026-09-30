@php
   $assignment = $assignment ?? null;
   $isEdit = $assignment !== null;
   $siteOptions = $siteOptions ?? [];
   $companyOptions = $companyOptions ?? [];
   $userOptions = $userOptions ?? [];
   $allSitesLabel = \App\Models\MonitoringSafetyEngineeringPicAssignment::ALL_SITES;

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

@if($errors->has('form'))
<div class="ra-alert ra-alert--error" role="alert">{{ $errors->first('form') }}</div>
@endif

<div class="ra-card">
   <p class="ra-card-title">Identitas PIC</p>
   <p class="ra-card-hint">
      Nama atau SID dipakai untuk mencocokkan user yang login dengan scope ini. Tautkan ke akun login
      bila ingin pencocokan pasti.
   </p>

   <div class="ra-grid" style="margin-top: 1rem;">
      <div class="ra-field">
         <label class="ra-label" for="nama">Nama PIC <span>*</span></label>
         <input type="text" id="nama" name="nama" required maxlength="255"
                class="ra-input @error('nama') ra-input--error @enderror"
                value="{{ old('nama', $assignment?->nama ?? '') }}"
                placeholder="Contoh: ASRIL WIRANATA MUHNAS">
         @error('nama')<span class="ra-field-error">{{ $message }}</span>@enderror
      </div>

      <div class="ra-field">
         <label class="ra-label" for="sid">SID</label>
         <input type="text" id="sid" name="sid" maxlength="50"
                class="ra-input @error('sid') ra-input--error @enderror"
                value="{{ old('sid', $assignment?->sid ?? '') }}"
                placeholder="Contoh: IIVK3"
                style="text-transform: uppercase;">
         @error('sid')<span class="ra-field-error">{{ $message }}</span>@enderror
      </div>

      <div class="ra-field">
         <label class="ra-label" for="jabatan">Jabatan</label>
         <input type="text" id="jabatan" name="jabatan" maxlength="255"
                class="ra-input @error('jabatan') ra-input--error @enderror"
                value="{{ old('jabatan', $assignment?->jabatan ?? '') }}"
                placeholder="Contoh: Safety Officer">
         @error('jabatan')<span class="ra-field-error">{{ $message }}</span>@enderror
      </div>

      <div class="ra-field">
         <label class="ra-label" for="user_id">Akun Login</label>
         <select id="user_id" name="user_id" class="ra-select @error('user_id') ra-select--error @enderror">
            <option value="">— Cocokkan lewat nama / SID —</option>
            @foreach($userOptions as $user)
            <option value="{{ $user['id'] }}" @selected($currentUserId === (int) $user['id'])>{{ $user['label'] }}</option>
            @endforeach
         </select>
         @error('user_id')<span class="ra-field-error">{{ $message }}</span>@enderror
      </div>
   </div>

   <div class="ra-grid" style="margin-top: 1rem;">
      <div class="ra-field">
         <label class="ra-label" for="catatan">Catatan</label>
         <textarea id="catatan" name="catatan" maxlength="500"
                   class="ra-textarea @error('catatan') ra-input--error @enderror"
                   placeholder="Opsional">{{ old('catatan', $assignment?->catatan ?? '') }}</textarea>
         @error('catatan')<span class="ra-field-error">{{ $message }}</span>@enderror
      </div>

      <div class="ra-field">
         <span class="ra-label">Status</span>
         <label class="ra-switch">
            <input type="checkbox" name="is_active" value="1" @checked($isActive)>
            <span>Aktif</span>
         </label>
         <span class="ra-field-hint">Assignment nonaktif tidak membatasi maupun memberi akses.</span>
      </div>
   </div>
</div>

<div class="ra-card">
   <div class="ra-page-head" style="margin-bottom: 0.9rem;">
      <div>
         <p class="ra-card-title">Perusahaan &amp; Site</p>
         <p class="ra-card-hint">
            Tambah satu baris per perusahaan, lalu pilih site yang dipegang. Pilih
            <strong>{{ $allSitesLabel }}</strong> bila PIC memegang seluruh site pada perusahaan itu.
         </p>
      </div>
      <button type="button" class="ra-btn ra-btn--ghost" id="ra-add-company">
         <span class="material-symbols-outlined text-[18px]">add</span>
         Tambah perusahaan
      </button>
   </div>

   @error('scopes')<div class="ra-alert ra-alert--error">{{ $message }}</div>@enderror

   <div class="ra-scope-rows" id="ra-scope-rows">
      @foreach($scopeRows as $index => $row)
      @php
         $rowCompany = trim((string) ($row['perusahaan'] ?? ''));
         $rowSites = is_array($row['sites'] ?? null) ? array_map('strval', $row['sites']) : [];
      @endphp
      <div class="ra-scope-row" data-ra-row>
         <div class="ra-scope-row-head">
            <span class="ra-scope-row-label">Perusahaan <span data-ra-ordinal>{{ $index + 1 }}</span></span>
            <button type="button" class="ra-btn ra-btn--danger ra-btn--sm" data-ra-remove>Hapus</button>
         </div>
         <div class="ra-scope-body">
            <div class="ra-field">
               <label class="ra-label">Perusahaan <span>*</span></label>
               <select name="scopes[{{ $index }}][perusahaan]" required
                       class="ra-select @error('scopes.'.$index.'.perusahaan') ra-select--error @enderror"
                       data-ra-company>
                  <option value="">— Pilih perusahaan —</option>
                  @foreach($companyOptions as $company)
                  <option value="{{ $company }}" @selected($rowCompany === $company)>{{ $company }}</option>
                  @endforeach
                  @if($rowCompany !== '' && ! in_array($rowCompany, $companyOptions, true))
                  <option value="{{ $rowCompany }}" selected>{{ $rowCompany }}</option>
                  @endif
               </select>
               @error('scopes.'.$index.'.perusahaan')<span class="ra-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="ra-field">
               <label class="ra-label">Site <span>*</span></label>
               <div class="ra-chips" data-ra-sites>
                  @foreach($siteOptions as $site)
                  @php $isAll = \App\Models\MonitoringSafetyEngineeringPicAssignment::isAllSitesLabel($site); @endphp
                  <label class="ra-chip {{ $isAll ? 'ra-chip--all' : '' }}">
                     <input type="checkbox" name="scopes[{{ $index }}][sites][]" value="{{ $site }}"
                            @checked(in_array($site, $rowSites, true))>
                     {{ $site }}
                  </label>
                  @endforeach
               </div>
               @error('scopes.'.$index.'.sites')<span class="ra-field-error">{{ $message }}</span>@enderror
            </div>
         </div>
      </div>
      @endforeach
   </div>
</div>

<template id="ra-row-template">
   <div class="ra-scope-row" data-ra-row>
      <div class="ra-scope-row-head">
         <span class="ra-scope-row-label">Perusahaan <span data-ra-ordinal>1</span></span>
         <button type="button" class="ra-btn ra-btn--danger ra-btn--sm" data-ra-remove>Hapus</button>
      </div>
      <div class="ra-scope-body">
         <div class="ra-field">
            <label class="ra-label">Perusahaan <span>*</span></label>
            <select name="scopes[__INDEX__][perusahaan]" required class="ra-select" data-ra-company>
               <option value="">— Pilih perusahaan —</option>
               @foreach($companyOptions as $company)
               <option value="{{ $company }}">{{ $company }}</option>
               @endforeach
            </select>
         </div>
         <div class="ra-field">
            <label class="ra-label">Site <span>*</span></label>
            <div class="ra-chips" data-ra-sites>
               @foreach($siteOptions as $site)
               @php $isAll = \App\Models\MonitoringSafetyEngineeringPicAssignment::isAllSitesLabel($site); @endphp
               <label class="ra-chip {{ $isAll ? 'ra-chip--all' : '' }}">
                  <input type="checkbox" name="scopes[__INDEX__][sites][]" value="{{ $site }}">
                  {{ $site }}
               </label>
               @endforeach
            </div>
         </div>
      </div>
   </div>
</template>

<div class="ra-form-actions">
   <a href="{{ route('monitoring-safety-engineering.role-access.index') }}" class="ra-btn ra-btn--ghost">Batal</a>
   <button type="submit" class="ra-btn ra-btn--primary">
      {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Assignment' }}
   </button>
</div>
