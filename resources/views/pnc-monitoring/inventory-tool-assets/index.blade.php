@extends('pnc-monitoring.layouts.app')

@section('title', 'Unit Aset Inventory Tools')

@section('content')
<div class="card shadow-none border mb-24">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <h6 class="mb-0">Unit Aset (Master Data)</h6>
      <p class="text-secondary-light text-xs mb-0">Unit fisik alat — tiap baris satu unit, terhubung ke Katalog Jenis Alat.</p>
    </div>
    <a href="{{ route('pnc-monitoring.inventory-tool-assets.create') }}" class="btn btn-primary-600 btn-sm">
      <i class="ri-add-line"></i> Tambah
    </a>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-16">
      <div class="col-lg-3">
        <label class="form-label text-sm mb-1">Template Excel</label>
        <form method="GET" action="{{ route('pnc-monitoring.inventory-tool-assets.excel-template') }}">
          <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Unduh Template</button>
        </form>
      </div>
      <div class="col-lg-3">
        <label class="form-label text-sm mb-1">Export Data ({{ number_format($rows->total()) }} baris)</label>
        <form method="GET" action="{{ route('pnc-monitoring.inventory-tool-assets.export') }}">
          <input type="hidden" name="q" value="{{ $q }}">
          <input type="hidden" name="status" value="{{ $status }}">
          <button type="submit" class="btn btn-outline-success-600 btn-sm w-100">
            <i class="ri-file-excel-2-line"></i> Export Excel
          </button>
        </form>
      </div>
      <div class="col-lg-6">
        <form method="POST" action="{{ route('pnc-monitoring.inventory-tool-assets.excel-import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
          @csrf
          <div class="col-8">
            <label class="form-label text-sm mb-1" for="asset-file">Unggah .xlsx</label>
            <input type="file" name="file" id="asset-file" class="form-control form-control-sm" accept=".xlsx,.xls" required>
          </div>
          <div class="col-4">
            <button type="submit" class="btn btn-primary-600 btn-sm w-100">Unggah</button>
          </div>
        </form>
      </div>
    </div>

    <form method="GET" class="row g-2 mb-16">
      <div class="col-md-5">
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari nama alat, inventory ID, brand, serial number, lokasi...">
      </div>
      <div class="col-md-4">
        <select name="status" class="form-select">
          <option value="">Semua Status</option>
          @foreach ($statuses as $opt)
            <option value="{{ $opt }}" @selected($status === $opt)>{{ $opt }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary-600 w-100" type="submit">Cari</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table bordered-table mb-0">
        <thead>
          <tr>
            <th>Inventory ID</th>
            <th>Nama Alat</th>
            <th>Brand / Model</th>
            <th>Serial Number</th>
            <th>Status</th>
            <th>Kondisi</th>
            <th>Lokasi</th>
            <th>Owner</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $row)
            <tr>
              <td>{{ $row->inventory_id ?? '-' }}</td>
              <td><strong>{{ $row->toolMaster?->standard_name }}</strong><br><span class="text-xs text-secondary-light">{{ $row->toolMaster?->category?->name }}</span></td>
              <td>{{ trim(($row->brand ?? '').' '.($row->model ?? '')) ?: '-' }}</td>
              <td>{{ $row->serial_number ?? '-' }}</td>
              <td>
                @php
                  $badge = match($row->status_availability) {
                    'Available' => 'bg-success-focus text-success-main',
                    'Checked-out' => 'bg-warning-focus text-warning-main',
                    'In Repair' => 'bg-info-focus text-info-main',
                    'Scrapped' => 'bg-danger-focus text-danger-main',
                    default => 'bg-neutral-200 text-neutral-600',
                  };
                @endphp
                <span class="badge {{ $badge }}">{{ $row->status_availability }}</span>
              </td>
              <td>{{ $row->condition ?? '-' }}</td>
              <td>{{ $row->location_detail ?? '-' }}</td>
              <td>{{ $row->ownerCompany?->name ?? '-' }}</td>
              <td class="text-end">
                <a href="{{ route('pnc-monitoring.inventory-tool-assets.edit', $row) }}" class="btn btn-outline-primary-600 btn-sm">Edit</a>
                <form action="{{ route('pnc-monitoring.inventory-tool-assets.destroy', $row) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus unit aset ini?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-outline-danger-600 btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center text-secondary-light py-24">Belum ada unit aset.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-16">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
