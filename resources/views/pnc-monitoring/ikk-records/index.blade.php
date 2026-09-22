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

    @php
      $pct = fn (?float $v) => $v === null ? '-' : number_format($v * 100, 1) . '%';
    @endphp
    <div class="table-responsive">
      <table class="table bordered-table mb-0">
        <thead>
          <tr>
            <th>Jenis</th>
            <th>Nomor</th>
            <th>Pekerjaan</th>
            <th>Tanggal</th>
            <th>Minggu</th>
            <th>Bulan</th>
            <th>Site</th>
            <th>Mine Contractor</th>
            <th>Perusahaan</th>
            <th>Finding IA</th>
            <th>Finding Verlap</th>
            <th>IA</th>
            <th>IPK</th>
            <th>PLAN OKK</th>
            <th>OKK 1</th>
            <th>OKK 2</th>
            <th>OKK 3</th>
            <th>OKK Performance</th>
            <th>OKK Layer 2</th>
            <th>OKK Layer 3</th>
            <th>OKK Layer 4</th>
            <th>Performance Layer 2 Up</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $row)
            <tr>
              <td>{{ $row->jenis ?? '-' }}</td>
              <td><strong>{{ $row->nomor }}</strong></td>
              <td>{{ $row->pekerjaan ?? '-' }}</td>
              <td>{{ $row->tanggal?->format('d M Y') ?? '-' }}</td>
              <td>{{ $row->minggu ?? '-' }}</td>
              <td>{{ $row->bulan ?? '-' }}</td>
              <td>{{ $row->site ?? '-' }}</td>
              <td>{{ $row->mine_contractor ?? '-' }}</td>
              <td>{{ $row->perusahaan ?? '-' }}</td>
              <td>{{ $row->finding_ia }}</td>
              <td>{{ $row->finding_verlap }}</td>
              <td>{{ $row->ia === null ? '-' : $row->ia }}</td>
              <td>{{ $row->ipk === null ? 'blank' : $row->ipk }}</td>
              <td>{{ $row->plan_okk }}</td>
              <td>{{ $row->okk_1 }}</td>
              <td>{{ $row->okk_2 }}</td>
              <td>{{ $row->okk_3 }}</td>
              <td>{{ $pct($row->okkPerformance()) }}</td>
              <td>{{ $row->okk_layer_2 }}</td>
              <td>{{ $row->okk_layer_3 }}</td>
              <td>{{ $row->okk_layer_4 }}</td>
              <td>{{ $pct($row->layer2UpPerformance()) }}</td>
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
              <td colspan="23" class="text-center text-secondary-light py-24">Belum ada data IKK.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-16">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
