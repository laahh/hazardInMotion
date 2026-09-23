@extends('pnc-monitoring.layouts.app')

@section('title', 'Perusahaan')

@section('content')
<div class="card shadow-none border">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <h6 class="mb-0">Perusahaan</h6>
      <p class="text-secondary-light text-xs mb-0">Referensi perusahaan pemilik/penyewa aset (Company/Rental/Contractor).</p>
    </div>
    <a href="{{ route('pnc-monitoring.inventory-companies.create') }}" class="btn btn-primary-600 btn-sm">
      <i class="ri-add-line"></i> Tambah
    </a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table bordered-table mb-0">
        <thead>
          <tr>
            <th>Nama</th>
            <th>Tipe</th>
            <th>Jumlah Aset</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $row)
            <tr>
              <td><strong>{{ $row->name }}</strong></td>
              <td>{{ $row->type ?? '-' }}</td>
              <td>{{ number_format($row->assets_count) }}</td>
              <td class="text-end">
                <a href="{{ route('pnc-monitoring.inventory-companies.edit', $row) }}" class="btn btn-outline-primary-600 btn-sm">Edit</a>
                <form action="{{ route('pnc-monitoring.inventory-companies.destroy', $row) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus perusahaan ini?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-outline-danger-600 btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-secondary-light py-24">Belum ada perusahaan.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
