@extends('pnc-monitoring.layouts.app')

@section('title', 'Data Inventory Tools')

@section('content')
<div class="card shadow-none border mb-24">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <h6 class="mb-0">Data Inventory Tools</h6>
      <p class="text-secondary-light text-xs mb-0">CRUD manual atau import Excel Master Data Inventory Tools.</p>
    </div>
    <a href="{{ route('pnc-monitoring.inventory-tools.create') }}" class="btn btn-primary-600 btn-sm">
      <i class="ri-add-line"></i> Tambah
    </a>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-16">
      <div class="col-lg-4">
        <form method="GET" action="{{ route('pnc-monitoring.inventory-tools.excel-template') }}">
          <label class="form-label text-sm mb-1">Template Excel</label>
          <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Unduh Template</button>
        </form>
      </div>
      <div class="col-lg-8">
        <form method="POST" action="{{ route('pnc-monitoring.inventory-tools.excel-import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
          @csrf
          <div class="col-8">
            <label class="form-label text-sm mb-1" for="inventory-file">Unggah .xlsx</label>
            <input type="file" name="file" id="inventory-file" class="form-control form-control-sm" accept=".xlsx,.xls" required>
          </div>
          <div class="col-4">
            <button type="submit" class="btn btn-primary-600 btn-sm w-100">Unggah</button>
          </div>
        </form>
      </div>
    </div>

    <form method="GET" class="row g-2 mb-16 align-items-end">
      <div class="col-lg-5">
        <label class="form-label text-xs text-secondary-light text-uppercase">Cari</label>
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari nama alat, asset ID, brand, SN, site...">
      </div>
      <div class="col-lg-3">
        <label class="form-label text-xs text-secondary-light text-uppercase">Kategori</label>
        <select name="category" class="form-select">
          <option value="">Semua</option>
          @foreach ($categories as $opt)
            <option value="{{ $opt }}" @selected($category === $opt)>{{ $opt }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-lg-2">
        <label class="form-label text-xs text-secondary-light text-uppercase">Status</label>
        <select name="status" class="form-select">
          <option value="">Semua</option>
          @foreach ($statuses as $opt)
            <option value="{{ $opt }}" @selected($status === $opt)>{{ $opt }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-lg-2">
        <button class="btn btn-primary-600 w-100" type="submit">Filter</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table bordered-table mb-0">
        <thead>
          <tr>
            <th>Nama Alat</th>
            <th>Kategori</th>
            <th>Asset ID</th>
            <th>Site</th>
            <th>Status</th>
            <th>Condition</th>
            <th>Qty</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $row)
            <tr>
              <td><strong>{{ $row->nama_alat }}</strong></td>
              <td>{{ $row->category }}</td>
              <td>{{ $row->asset_id ?? '-' }}</td>
              <td>{{ $row->site ?? '-' }}</td>
              <td>{{ $row->status_ketersediaan ?? '-' }}</td>
              <td>{{ $row->condition ?? '-' }}</td>
              <td>{{ $row->qty_on_hand }}</td>
              <td class="text-end">
                <a href="{{ route('pnc-monitoring.inventory-tools.edit', $row) }}" class="btn btn-outline-primary-600 btn-sm">Edit</a>
                <form action="{{ route('pnc-monitoring.inventory-tools.destroy', $row) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-outline-danger-600 btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-secondary-light py-24">Belum ada data Inventory Tools.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-16">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
