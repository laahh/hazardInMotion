@php
  $opts = $filterOptions ?? ['sites' => [], 'companies' => [], 'divisions' => []];
  $f = $filters ?? ['site' => '', 'company' => '', 'division' => '', 'mcu_severity' => '', 'lab_type' => ''];
@endphp
<div class="bg-neutral-50 border radius-8 p-16 mb-20">
  <div class="row g-3 align-items-end">
    <div class="col-xl-2 col-md-4 col-sm-6">
      <label for="hn-site" class="form-label text-sm fw-medium mb-6">Site</label>
      <select id="hn-site" class="form-select form-select-sm">
        <option value="">Semua Site</option>
        @foreach (($opts['sites'] ?? []) as $site)
          <option value="{{ $site }}" @selected(($f['site'] ?? '') === $site)>{{ $site }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
      <label for="hn-company" class="form-label text-sm fw-medium mb-6">Perusahaan</label>
      <select id="hn-company" class="form-select form-select-sm">
        <option value="">Semua Perusahaan</option>
        @foreach (($opts['companies'] ?? []) as $company)
          <option value="{{ $company }}" @selected(($f['company'] ?? '') === $company)>{{ $company }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
      <label for="hn-division" class="form-label text-sm fw-medium mb-6">Divisi</label>
      <input
        id="hn-division"
        type="search"
        list="hn-division-options"
        class="form-control form-control-sm"
        placeholder="Cari divisi..."
        value="{{ $f['division'] ?? '' }}"
        autocomplete="off"
      >
      <datalist id="hn-division-options">
        @foreach (($opts['divisions'] ?? []) as $division)
          <option value="{{ $division }}"></option>
        @endforeach
      </datalist>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
      <label for="hn-mcu-severity" class="form-label text-sm fw-medium mb-6">Severity MCU</label>
      <select id="hn-mcu-severity" class="form-select form-select-sm">
        <option value="">Semua</option>
        <option value="warn" @selected(($f['mcu_severity'] ?? '') === 'warn')>Waspada</option>
        <option value="high" @selected(($f['mcu_severity'] ?? '') === 'high')>Tinggi</option>
      </select>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
      <label for="hn-lab-type" class="form-label text-sm fw-medium mb-6">Jenis Temuan</label>
      <select id="hn-lab-type" class="form-select form-select-sm">
        <option value="">Semua</option>
        <option value="glucose" @selected(($f['lab_type'] ?? '') === 'glucose')>Gula darah</option>
        <option value="cholesterol" @selected(($f['lab_type'] ?? '') === 'cholesterol')>Kolesterol</option>
        <option value="triglyceride" @selected(($f['lab_type'] ?? '') === 'triglyceride')>Trigliserida</option>
        <option value="uric_acid" @selected(($f['lab_type'] ?? '') === 'uric_acid')>Asam urat</option>
      </select>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
      <div class="d-flex gap-2">
        <button type="button" id="hn-reset-btn" class="btn btn-sm btn-outline-secondary w-100">Reset</button>
        <button type="button" id="hn-apply-btn" class="btn btn-sm btn-primary-600 w-100">Filter</button>
      </div>
    </div>
  </div>
</div>
