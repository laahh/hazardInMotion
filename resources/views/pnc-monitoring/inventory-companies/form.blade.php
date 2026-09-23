@extends('pnc-monitoring.layouts.app')

@section('title', $mode === 'create' ? 'Tambah Perusahaan' : 'Edit Perusahaan')

@section('content')
<div class="card shadow-none border">
  <div class="card-header">
    <h6 class="mb-0">{{ $mode === 'create' ? 'Tambah Perusahaan' : 'Edit Perusahaan' }}</h6>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ $mode === 'create' ? route('pnc-monitoring.inventory-companies.store') : route('pnc-monitoring.inventory-companies.update', $row) }}">
      @csrf
      @if ($mode === 'edit')
        @method('PUT')
      @endif
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label" for="name">Nama *</label>
          <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $row->name) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="type">Tipe</label>
          <input type="text" name="type" id="type" class="form-control" placeholder="Company / Rental / Contractor" value="{{ old('type', $row->type) }}">
        </div>
      </div>
      <div class="mt-24 d-flex gap-2">
        <button type="submit" class="btn btn-primary-600">Simpan</button>
        <a href="{{ route('pnc-monitoring.inventory-companies.index') }}" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
