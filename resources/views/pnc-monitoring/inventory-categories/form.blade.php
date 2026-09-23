@extends('pnc-monitoring.layouts.app')

@section('title', $mode === 'create' ? 'Tambah Kategori' : 'Edit Kategori')

@section('content')
<div class="card shadow-none border">
  <div class="card-header">
    <h6 class="mb-0">{{ $mode === 'create' ? 'Tambah Kategori' : 'Edit Kategori' }}</h6>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ $mode === 'create' ? route('pnc-monitoring.inventory-categories.store') : route('pnc-monitoring.inventory-categories.update', $row) }}">
      @csrf
      @if ($mode === 'edit')
        @method('PUT')
      @endif
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label" for="code">Kode *</label>
          <input type="text" name="code" id="code" class="form-control" maxlength="5" value="{{ old('code', $row->code) }}" required>
          @error('code')<span class="text-danger-600 text-xs">{{ $message }}</span>@enderror
        </div>
        <div class="col-md-9">
          <label class="form-label" for="name">Nama *</label>
          <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $row->name) }}" required>
        </div>
        <div class="col-md-12">
          <label class="form-label" for="description">Deskripsi</label>
          <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $row->description) }}</textarea>
        </div>
      </div>
      <div class="mt-24 d-flex gap-2">
        <button type="submit" class="btn btn-primary-600">Simpan</button>
        <a href="{{ route('pnc-monitoring.inventory-categories.index') }}" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
