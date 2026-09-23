@extends('pnc-monitoring.layouts.app')

@section('title', $mode === 'create' ? 'Tambah Unit Aset' : 'Edit Unit Aset')

@section('content')
<div class="card shadow-none border">
  <div class="card-header">
    <h6 class="mb-0">{{ $mode === 'create' ? 'Tambah Unit Aset' : 'Edit Unit Aset' }}</h6>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ $mode === 'create' ? route('pnc-monitoring.inventory-tool-assets.store') : route('pnc-monitoring.inventory-tool-assets.update', $row) }}">
      @csrf
      @if ($mode === 'edit')
        @method('PUT')
      @endif
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="tool_master_id">Nama Alat (Jenis) *</label>
          <select name="tool_master_id" id="tool_master_id" class="form-select" required>
            <option value="">Pilih...</option>
            @foreach ($toolMasters as $tm)
              <option value="{{ $tm->tool_master_id }}" @selected(old('tool_master_id', $row->tool_master_id) == $tm->tool_master_id)>{{ $tm->standard_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="inventory_id">Inventory ID / Asset Tag</label>
          <input type="text" name="inventory_id" id="inventory_id" class="form-control" value="{{ old('inventory_id', $row->inventory_id) }}">
          @error('inventory_id')<div class="text-danger-600 text-xs">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
          <label class="form-label" for="status_availability">Status Ketersediaan</label>
          <select name="status_availability" id="status_availability" class="form-select">
            @foreach ($statuses as $opt)
              <option value="{{ $opt }}" @selected(old('status_availability', $row->status_availability ?? 'Available') === $opt)>{{ $opt }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label" for="brand">Brand</label>
          <input type="text" name="brand" id="brand" class="form-control" value="{{ old('brand', $row->brand) }}">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="model">Model</label>
          <input type="text" name="model" id="model" class="form-control" value="{{ old('model', $row->model) }}">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="serial_number">Serial Number</label>
          <input type="text" name="serial_number" id="serial_number" class="form-control" value="{{ old('serial_number', $row->serial_number) }}">
        </div>

        <div class="col-md-3">
          <label class="form-label" for="year_made">Tahun Pembuatan</label>
          <input type="number" name="year_made" id="year_made" class="form-control" value="{{ old('year_made', $row->year_made) }}">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="power_source">Power Source</label>
          <input type="text" name="power_source" id="power_source" class="form-control" value="{{ old('power_source', $row->power_source) }}">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="condition">Kondisi</label>
          <select name="condition" id="condition" class="form-select">
            <option value="">-</option>
            @foreach ($conditions as $opt)
              <option value="{{ $opt }}" @selected(old('condition', $row->condition) === $opt)>{{ $opt }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="owner_type">Owner Type</label>
          <input type="text" name="owner_type" id="owner_type" class="form-control" placeholder="Company / Rental / Contractor" value="{{ old('owner_type', $row->owner_type) }}">
        </div>

        <div class="col-md-6">
          <label class="form-label" for="location_detail">Lokasi Detail</label>
          <input type="text" name="location_detail" id="location_detail" class="form-control" value="{{ old('location_detail', $row->location_detail) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="owner_company_id">Owner Company</label>
          <select name="owner_company_id" id="owner_company_id" class="form-select">
            <option value="">-</option>
            @foreach ($companies as $c)
              <option value="{{ $c->company_id }}" @selected(old('owner_company_id', $row->owner_company_id) == $c->company_id)>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label" for="purchase_date">Tanggal Pembelian</label>
          <input type="date" name="purchase_date" id="purchase_date" class="form-control" value="{{ old('purchase_date', $row->purchase_date?->format('Y-m-d')) }}">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="purchase_price">Harga Pembelian</label>
          <input type="number" step="0.01" name="purchase_price" id="purchase_price" class="form-control" value="{{ old('purchase_price', $row->purchase_price) }}">
        </div>
        <div class="col-md-12">
          <label class="form-label" for="notes">Catatan</label>
          <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes', $row->notes) }}</textarea>
        </div>
      </div>
      <div class="mt-24 d-flex gap-2">
        <button type="submit" class="btn btn-primary-600">Simpan</button>
        <a href="{{ route('pnc-monitoring.inventory-tool-assets.index') }}" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
