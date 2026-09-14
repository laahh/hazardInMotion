@extends('pnc-monitoring.layouts.app')

@section('title', 'Data IKK')

@section('content')
<div class="card shadow-none border mb-24">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <h6 class="mb-0">Data IKK</h6>
      <p class="text-secondary-light text-xs mb-0">CRUD manual atau import Excel Main Data IKK.</p>
    </div>
    <a href="{{ route('pnc-monitoring.ikk-records.create') }}" class="btn btn-primary-600 btn-sm">
      <i class="ri-add-line"></i> Tambah
    </a>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-16">
      <div class="col-lg-4">
        <form method="GET" action="{{ route('pnc-monitoring.ikk-records.excel-template') }}">
          <label class="form-label text-sm mb-1">Template Excel</label>
          <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Unduh Template</button>
        </form>
      </div>
      <div class="col-lg-8">
        <form method="POST" action="{{ route('pnc-monitoring.ikk-records.excel-import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
          @csrf
          <div class="col-8">
            <label class="form-label text-sm mb-1" for="ikk-file">Unggah .xlsx</label>
            <input type="file" name="file" id="ikk-file" class="form-control form-control-sm" accept=".xlsx,.xls" required>
          </div>
          <div class="col-4">
            <button type="submit" class="btn btn-primary-600 btn-sm w-100">Unggah</button>
          </div>
        </form>
      </div>
    </div>

    <form method="GET" class="mb-16">
      <div class="input-group">
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari nomor, site, perusahaan, jenis...">
        <button class="btn btn-primary-600" type="submit">Cari</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table bordered-table mb-0">
        <thead>
          <tr>
            <th>Nomor</th>
            <th>Jenis</th>
            <th>Site</th>
            <th>Perusahaan</th>
            <th>Minggu</th>
            <th>IPK</th>
            <th>IA</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $row)
            <tr>
              <td><strong>{{ $row->nomor }}</strong></td>
              <td>{{ $row->jenis ?? '-' }}</td>
              <td>{{ $row->site ?? '-' }}</td>
              <td>{{ $row->perusahaan ?? '-' }}</td>
              <td>{{ $row->tahun ? 'Y'.$row->tahun.' W'.($row->minggu ?? '-') : '-' }}</td>
              <td>{{ $row->ipk === null ? 'blank' : $row->ipk }}</td>
              <td>{{ $row->ia === null ? '-' : $row->ia }}</td>
              <td class="text-end">
                <a href="{{ route('pnc-monitoring.ikk-records.edit', $row) }}" class="btn btn-outline-primary-600 btn-sm">Edit</a>
                <form action="{{ route('pnc-monitoring.ikk-records.destroy', $row) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-outline-danger-600 btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-secondary-light py-24">Belum ada data IKK.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-16">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
