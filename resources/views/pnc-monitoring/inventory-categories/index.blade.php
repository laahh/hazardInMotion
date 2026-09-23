@extends('pnc-monitoring.layouts.app')

@section('title', 'Kategori Inventory Tools')

@section('content')
<div class="card shadow-none border">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <h6 class="mb-0">Kategori Inventory Tools</h6>
      <p class="text-secondary-light text-xs mb-0">Daftar kategori (Common Tools, Special Tools, dst) dipakai sebagai referensi Katalog Alat.</p>
    </div>
    <a href="{{ route('pnc-monitoring.inventory-categories.create') }}" class="btn btn-primary-600 btn-sm">
      <i class="ri-add-line"></i> Tambah
    </a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table bordered-table mb-0">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama</th>
            <th>Deskripsi</th>
            <th>Jumlah Jenis Alat</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $row)
            <tr>
              <td><strong>{{ $row->code }}</strong></td>
              <td>{{ $row->name }}</td>
              <td>{{ $row->description ?? '-' }}</td>
              <td>{{ number_format($row->tool_masters_count) }}</td>
              <td class="text-end">
                <a href="{{ route('pnc-monitoring.inventory-categories.edit', $row) }}" class="btn btn-outline-primary-600 btn-sm">Edit</a>
                <form action="{{ route('pnc-monitoring.inventory-categories.destroy', $row) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kategori ini?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-outline-danger-600 btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-secondary-light py-24">Belum ada kategori.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
