@extends('pnc-monitoring.layouts.app')

@section('title', 'Data Commissioning')

@section('content')
<div class="card shadow-none border mb-24">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <h6 class="mb-0">Data Commissioning</h6>
      <p class="text-secondary-light text-xs mb-0">CRUD manual atau import Excel Commissioning / SPIP.</p>
    </div>
    <a href="{{ route('pnc-monitoring.commissionings.create') }}" class="btn btn-primary-600 btn-sm">
      <i class="ri-add-line"></i> Tambah
    </a>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-16">
      <div class="col-lg-4">
        <form method="GET" action="{{ route('pnc-monitoring.commissionings.excel-template') }}">
          <label class="form-label text-sm mb-1">Template Excel</label>
          <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Unduh Template</button>
        </form>
      </div>
      <div class="col-lg-8">
        <form method="POST" action="{{ route('pnc-monitoring.commissionings.excel-import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
          @csrf
          <div class="col-8">
            <label class="form-label text-sm mb-1" for="comm-file">Unggah .xlsx</label>
            <input type="file" name="file" id="comm-file" class="form-control form-control-sm" accept=".xlsx,.xls" required>
          </div>
          <div class="col-4">
            <button type="submit" class="btn btn-primary-600 btn-sm w-100">Unggah</button>
          </div>
        </form>
      </div>
    </div>

    <form method="GET" class="mb-16">
      <div class="input-group">
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari register, site, pengawas, pemilik...">
        <button class="btn btn-primary-600" type="submit">Cari</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table bordered-table mb-0">
        <thead>
          <tr>
            <th>No Register</th>
            <th>Site</th>
            <th>Pengawas</th>
            <th>Pemilik</th>
            <th>Week</th>
            <th>Temuan</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $row)
            <tr>
              <td><strong>{{ $row->no_register_spip }}</strong></td>
              <td>{{ $row->site ?? '-' }}</td>
              <td>{{ $row->nama_pengawas_teknis ?? '-' }}</td>
              <td>{{ $row->pemilik_spip ?? '-' }}</td>
              <td>{{ $row->tahun ? 'Y'.$row->tahun.' W'.($row->week ?? '-') : '-' }}</td>
              <td>{{ $row->temuan_komisioning }}</td>
              <td>{{ $row->status ?? '-' }}</td>
              <td class="text-end">
                <a href="{{ route('pnc-monitoring.commissionings.edit', $row) }}" class="btn btn-outline-primary-600 btn-sm">Edit</a>
                <form action="{{ route('pnc-monitoring.commissionings.destroy', $row) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-outline-danger-600 btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-secondary-light py-24">Belum ada data Commissioning.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-16">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
