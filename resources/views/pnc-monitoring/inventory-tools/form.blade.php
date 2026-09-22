@extends('pnc-monitoring.layouts.app')

@section('title', $mode === 'create' ? 'Tambah Inventory Tools' : 'Edit Inventory Tools')

@section('content')
<div class="card shadow-none border">
  <div class="card-header">
    <h6 class="mb-0">{{ $mode === 'create' ? 'Tambah Data Inventory Tools' : 'Edit Data Inventory Tools' }}</h6>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ $mode === 'create' ? route('pnc-monitoring.inventory-tools.store') : route('pnc-monitoring.inventory-tools.update', $row) }}">
      @csrf
      @if ($mode === 'edit')
        @method('PUT')
      @endif

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label" for="category">Kategori *</label>
          <select name="category" id="category" class="form-select" required>
            <option value="">Pilih kategori</option>
            @foreach ($categories as $opt)
              <option value="{{ $opt }}" @selected(old('category', $row->category) === $opt)>{{ $opt }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="nama_alat">Nama Alat *</label>
          <input type="text" name="nama_alat" id="nama_alat" class="form-control" value="{{ old('nama_alat', $row->nama_alat) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="asset_id">Asset ID</label>
          <input type="text" name="asset_id" id="asset_id" class="form-control" value="{{ old('asset_id', $row->asset_id) }}">
        </div>

        @foreach ([
          ['sub_kategori', 'Sub Kategori / Jenis', 'text'],
          ['brand', 'Brand', 'text'],
          ['model', 'Model', 'text'],
          ['serial_number', 'Serial Number', 'text'],
          ['tahun_pembuatan', 'Tahun Pembuatan', 'text'],
          ['power_source', 'Power Source', 'text'],
          ['kapasitas_rating', 'Kapasitas / Rating', 'text'],
          ['site', 'Site', 'text'],
          ['lokasi_detail', 'Lokasi Detail (Rack/Bin/Locker)', 'text'],
          ['pic', 'PIC / Assigned To', 'text'],
          ['qty_on_hand', 'Qty On Hand', 'number'],
        ] as [$key, $label, $type])
          <div class="col-md-4">
            <label class="form-label" for="{{ $key }}">{{ $label }}</label>
            <input type="{{ $type }}" name="{{ $key }}" id="{{ $key }}" class="form-control" value="{{ old($key, $row->{$key}) }}">
          </div>
        @endforeach

        <div class="col-md-4">
          <label class="form-label" for="status_ketersediaan">Status Ketersediaan</label>
          <select name="status_ketersediaan" id="status_ketersediaan" class="form-select">
            <option value="">Pilih status</option>
            @foreach ($statuses as $opt)
              <option value="{{ $opt }}" @selected(old('status_ketersediaan', $row->status_ketersediaan) === $opt)>{{ $opt }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="condition">Condition</label>
          <select name="condition" id="condition" class="form-select">
            <option value="">Pilih condition</option>
            @foreach ($conditions as $opt)
              <option value="{{ $opt }}" @selected(old('condition', $row->condition) === $opt)>{{ $opt }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="inspection_result">Inspection Result</label>
          <select name="inspection_result" id="inspection_result" class="form-select">
            <option value="">-</option>
            @foreach (['Pass', 'Fail', 'Conditional'] as $opt)
              <option value="{{ $opt }}" @selected(old('inspection_result', $row->inspection_result) === $opt)>{{ $opt }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-12"><hr class="my-8"></div>

        <div class="col-md-3">
          <label class="form-label" for="calibration_required">Calibration Required?</label>
          <select name="calibration_required" id="calibration_required" class="form-select">
            <option value="">-</option>
            <option value="1" @selected(old('calibration_required', $row->calibration_required) == 1)>Ya</option>
            <option value="0" @selected(old('calibration_required', $row->calibration_required) === false)>Tidak</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="last_calibration_date">Last Calibration Date</label>
          <input type="date" name="last_calibration_date" id="last_calibration_date" class="form-control" value="{{ old('last_calibration_date', $row->last_calibration_date?->format('Y-m-d')) }}">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="calibration_due_date">Calibration Due Date</label>
          <input type="date" name="calibration_due_date" id="calibration_due_date" class="form-control" value="{{ old('calibration_due_date', $row->calibration_due_date?->format('Y-m-d')) }}">
        </div>

        <div class="col-md-3">
          <label class="form-label" for="inspection_required">Inspection Required?</label>
          <select name="inspection_required" id="inspection_required" class="form-select">
            <option value="">-</option>
            <option value="1" @selected(old('inspection_required', $row->inspection_required) == 1)>Ya</option>
            <option value="0" @selected(old('inspection_required', $row->inspection_required) === false)>Tidak</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="last_inspection_date">Last Inspection Date</label>
          <input type="date" name="last_inspection_date" id="last_inspection_date" class="form-control" value="{{ old('last_inspection_date', $row->last_inspection_date?->format('Y-m-d')) }}">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="next_inspection_due">Next Inspection Due</label>
          <input type="date" name="next_inspection_due" id="next_inspection_due" class="form-control" value="{{ old('next_inspection_due', $row->next_inspection_due?->format('Y-m-d')) }}">
        </div>

        <div class="col-md-3">
          <label class="form-label" for="pm_required">PM Required?</label>
          <select name="pm_required" id="pm_required" class="form-select">
            <option value="">-</option>
            <option value="1" @selected(old('pm_required', $row->pm_required) == 1)>Ya</option>
            <option value="0" @selected(old('pm_required', $row->pm_required) === false)>Tidak</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="last_pm_date">Last PM Date</label>
          <input type="date" name="last_pm_date" id="last_pm_date" class="form-control" value="{{ old('last_pm_date', $row->last_pm_date?->format('Y-m-d')) }}">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="next_pm_due">Next PM Due</label>
          <input type="date" name="next_pm_due" id="next_pm_due" class="form-control" value="{{ old('next_pm_due', $row->next_pm_due?->format('Y-m-d')) }}">
        </div>

        <div class="col-12">
          <label class="form-label" for="catatan">Catatan</label>
          <textarea name="catatan" id="catatan" class="form-control" rows="3">{{ old('catatan', $row->catatan) }}</textarea>
        </div>
      </div>

      <div class="mt-24 d-flex gap-2">
        <button type="submit" class="btn btn-primary-600">Simpan</button>
        <a href="{{ route('pnc-monitoring.inventory-tools.index') }}" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
