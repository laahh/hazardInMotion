@extends('pnc-monitoring.layouts.app')

@section('title', 'Katalog Jenis Alat')

@section('content')
<div class="card shadow-none border mb-24">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <h6 class="mb-0">Katalog Jenis Alat (Tool Master)</h6>
      <p class="text-secondary-light text-xs mb-0">Satu baris per jenis alat (mis. "Air Duster Gun") — dokumentasi fungsi/safety/checklist ditulis sekali di sini, dipakai bersama oleh banyak unit fisik di Unit Aset.</p>
    </div>
    <a href="{{ route('pnc-monitoring.inventory-tool-master.create') }}" class="btn btn-primary-600 btn-sm">
      <i class="ri-add-line"></i> Tambah Jenis Alat
    </a>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-16">
      <div class="col-lg-3">
        <label class="form-label text-sm mb-1">Template Excel</label>
        <form method="GET" action="{{ route('pnc-monitoring.inventory-tool-master.excel-template') }}">
          <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Unduh Template</button>
        </form>
      </div>
      <div class="col-lg-3">
        <label class="form-label text-sm mb-1">Export Data ({{ number_format($rows->total()) }} baris)</label>
        <form method="GET" action="{{ route('pnc-monitoring.inventory-tool-master.export') }}">
          <input type="hidden" name="q" value="{{ $q }}">
          <input type="hidden" name="category_id" value="{{ $categoryId }}">
          <button type="submit" class="btn btn-outline-success-600 btn-sm w-100">
            <i class="ri-file-excel-2-line"></i> Export Excel
          </button>
        </form>
      </div>
      <div class="col-lg-6">
        <form method="POST" action="{{ route('pnc-monitoring.inventory-tool-master.excel-import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
          @csrf
          <div class="col-8">
            <label class="form-label text-sm mb-1" for="tm-file">Unggah .xlsx</label>
            <input type="file" name="file" id="tm-file" class="form-control form-control-sm" accept=".xlsx,.xls" required>
          </div>
          <div class="col-4">
            <button type="submit" class="btn btn-primary-600 btn-sm w-100">Unggah</button>
          </div>
        </form>
      </div>
      <div class="col-12">
        <p class="text-secondary-light text-xs mb-0">
          Template berisi 8 sheet: <strong>KatalogAlat</strong> (data inti jenis alat) + <strong>FungsiDetail, MetodeInspeksi, FiturKeselamatan, StandarAcuan, ChecklistPemeriksaan, AturanPenggunaan, AtributTeknis</strong>
          (isi kolom "Nama Alat (Standard Name)" di tiap sheet detail sama persis dengan yang di sheet KatalogAlat). Satu kali unggah sudah langsung membuat jenis alat sekaligus seluruh detailnya.
        </p>
      </div>
    </div>

    <form method="GET" class="row g-2 mb-16">
      <div class="col-md-4">
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari nama alat...">
      </div>
      <div class="col-md-4">
        <select name="category_id" class="form-select">
          <option value="">Semua Kategori</option>
          @foreach ($categories as $cat)
            <option value="{{ $cat->category_id }}" @selected($categoryId == $cat->category_id)>{{ $cat->code }} — {{ $cat->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary-600 w-100" type="submit">Cari</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table bordered-table mb-0">
        <thead>
          <tr>
            <th>Kategori</th>
            <th>Nama Alat</th>
            <th>Sub Kategori</th>
            <th>Criticality</th>
            <th>Risk</th>
            <th>Regulasi</th>
            <th>Jml Aset</th>
            <th>Jml Checklist</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $row)
            <tr>
              <td>{{ $row->category?->code }}</td>
              <td><strong>{{ $row->standard_name }}</strong></td>
              <td>{{ $row->sub_category ?? '-' }}</td>
              <td>{{ $row->criticality ?? '-' }}</td>
              <td>{{ $row->risk_class ?? '-' }}</td>
              <td>{!! $row->is_regulated ? '<span class="badge bg-warning-focus text-warning-main">Ya</span>' : '<span class="text-secondary-light">Tidak</span>' !!}</td>
              <td>{{ number_format($row->assets_count) }}</td>
              <td>{{ number_format($row->checklist_items_count) }}</td>
              <td class="text-end">
                <a href="{{ route('pnc-monitoring.inventory-tool-master.edit', $row) }}" class="btn btn-outline-primary-600 btn-sm">Kelola</a>
                <form action="{{ route('pnc-monitoring.inventory-tool-master.destroy', $row) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus jenis alat ini?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-outline-danger-600 btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center text-secondary-light py-24">Belum ada jenis alat.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-16">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
