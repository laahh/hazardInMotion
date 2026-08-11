@extends('evaluasi-well.layouts.app')

@section('title', 'Manajemen User')

@section('css')
<style>
  .eu-table-wrap {
    width: 100%;
    overflow-x: auto;
  }

  .eu-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
  }

  .eu-table th,
  .eu-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
    text-align: left;
    color: #111827;
    font-size: 0.9rem;
  }

  .eu-table thead th {
    background: #f3f4f6;
    font-weight: 600;
    white-space: nowrap;
  }

  .eu-table tbody tr:hover {
    background: #f9fafb;
  }

  .eu-table .eu-actions {
    white-space: nowrap;
    width: 1%;
  }

  .eu-pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    justify-content: space-between;
    margin-top: 1rem;
  }

  .eu-pagination .page-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
  }

  .eu-pagination .page-links a,
  .eu-pagination .page-links span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.6rem;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    text-decoration: none;
    color: #111827;
    background: #fff;
  }

  .eu-pagination .page-links span.current {
    background: #487fff;
    border-color: #487fff;
    color: #fff;
  }
</style>
@endsection

@section('content')
@php
  $f = $filters ?? ['q' => '', 'site' => '', 'company' => '', 'division' => '', 'status' => ''];
  $opts = $filterOptions ?? ['sites' => [], 'companies' => [], 'divisions' => [], 'statuses' => ['AKTIF', 'NONAKTIF']];
  $employees = $employees ?? [];
  $total = (int) ($total ?? 0);
  $page = (int) ($page ?? 1);
  $perPage = (int) ($perPage ?? 15);
  $lastPage = (int) ($lastPage ?? 1);
  $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
  $to = min($total, $page * $perPage);

  $queryBase = array_filter([
      'q' => $f['q'] ?? '',
      'site' => $f['site'] ?? '',
      'company' => $f['company'] ?? '',
      'division' => $f['division'] ?? '',
      'status' => $f['status'] ?? '',
      'per_page' => $perPage,
  ], static fn ($v) => $v !== null && $v !== '');
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Manajemen User</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Manajemen User</li>
  </ul>
</div>

@if (session('success'))
<div class="alert alert-success bg-success-100 text-success-600 border-success-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ session('success') }}
</div>
@endif

@if (session('import_errors'))
<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8" role="alert">
  <div class="fw-semibold mb-8">Beberapa baris gagal/dilewati:</div>
  <ul class="mb-0 ps-18">
    @foreach (session('import_errors') as $err)
      <li>{{ $err }}</li>
    @endforeach
  </ul>
</div>
@endif

@if ($errors->has('form'))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ $errors->first('form') }}
</div>
@endif

@unless ($connectionUp ?? false)
<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8" role="alert">
  Koneksi BeWell tidak tersedia. Pastikan tunnel BeWell aktif.
</div>
@endunless

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-4">
          <h6 class="text-lg fw-semibold mb-0">Daftar Karyawan BeWell</h6>
          <span class="bg-primary-50 text-primary-600 text-sm fw-medium px-12 py-2 rounded-pill">{{ number_format($total) }}</span>
        </div>
        <p class="text-sm text-secondary-light mb-0">Tambah / edit profil. Password login = Kode SID.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <form action="{{ route('evaluasi-well.users.sync-hse') }}" method="POST" class="d-inline" onsubmit="return confirm('Jalankan sync HSE? Karyawan baru akan ditambahkan; SID yang sudah ada tidak diubah.');">
          @csrf
          <button type="submit" class="btn btn-outline-success-600 radius-8 px-16 py-10" @disabled(!($connectionUp ?? false))>
            Sync dari HSE
          </button>
        </form>
        <a href="{{ route('evaluasi-well.users.import-template') }}" class="btn btn-outline-secondary radius-8 px-16 py-10">
          Template Excel
        </a>
        <a href="{{ route('evaluasi-well.users.import-form') }}" class="btn btn-outline-primary-600 radius-8 px-16 py-10">
          Upload Excel
        </a>
        <a href="{{ route('evaluasi-well.users.create') }}" class="btn btn-primary-600 radius-8 px-16 py-10">
          + Tambah Karyawan
        </a>
      </div>
    </div>
  </div>

  <div class="card-body p-24">
    <form method="GET" action="{{ route('evaluasi-well.users.index') }}" class="bg-neutral-50 border radius-8 p-16 mb-20">
      <div class="row g-3 align-items-end">
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="eu-q" class="form-label text-sm fw-medium mb-6">Cari</label>
          <input type="search" id="eu-q" name="q" class="form-control form-control-sm" value="{{ $f['q'] ?? '' }}" placeholder="Nama / SID / NIK">
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="eu-site" class="form-label text-sm fw-medium mb-6">Site</label>
          <select id="eu-site" name="site" class="form-select form-select-sm">
            <option value="">Semua Site</option>
            @foreach ($opts['sites'] as $site)
              <option value="{{ $site }}" @selected(($f['site'] ?? '') === $site)>{{ $site }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="eu-company" class="form-label text-sm fw-medium mb-6">Perusahaan</label>
          <select id="eu-company" name="company" class="form-select form-select-sm">
            <option value="">Semua Perusahaan</option>
            @foreach ($opts['companies'] as $company)
              <option value="{{ $company }}" @selected(($f['company'] ?? '') === $company)>{{ $company }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="eu-division" class="form-label text-sm fw-medium mb-6">Divisi</label>
          <input type="search" id="eu-division" name="division" class="form-control form-control-sm" value="{{ $f['division'] ?? '' }}" placeholder="Cari divisi...">
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <label for="eu-status" class="form-label text-sm fw-medium mb-6">Status</label>
          <select id="eu-status" name="status" class="form-select form-select-sm">
            <option value="">Semua</option>
            @foreach ($opts['statuses'] as $status)
              <option value="{{ $status }}" @selected(($f['status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
          <div class="d-flex gap-2">
            <a href="{{ route('evaluasi-well.users.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            <button type="submit" class="btn btn-sm btn-primary-600 w-100">Filter</button>
          </div>
        </div>
      </div>
    </form>

    <div class="eu-table-wrap">
      <table class="eu-table">
        <thead>
          <tr>
            <th>Aksi</th>
            <th>Nama / NIK</th>
            <th>Kode SID</th>
            <th>Site</th>
            <th>Perusahaan</th>
            <th>Divisi</th>
            <th>Departemen</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($employees as $row)
            <tr>
              <td class="eu-actions">
                <a href="{{ route('evaluasi-well.users.edit', $row['id']) }}" class="btn btn-sm btn-primary-600">Edit</a>
              </td>
              <td>
                <a href="{{ route('evaluasi-well.users.edit', $row['id']) }}" class="fw-semibold text-primary-light">{{ $row['nama'] }}</a>
                <div class="text-secondary-light text-sm">{{ $row['nik'] }}</div>
              </td>
              <td>{{ $row['kode_sid'] }}</td>
              <td>{{ $row['site'] }}</td>
              <td>{{ $row['company'] }}</td>
              <td>{{ $row['divisi'] }}</td>
              <td>{{ $row['departement'] }}</td>
              <td>{{ $row['status_karyawan'] }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-secondary-light py-24">Tidak ada data karyawan.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="eu-pagination">
      <div class="text-sm text-secondary-light">
        Menampilkan {{ number_format($from) }}–{{ number_format($to) }} dari {{ number_format($total) }} data
      </div>
      <div class="page-links">
        @if ($page > 1)
          <a href="{{ route('evaluasi-well.users.index', array_merge($queryBase, ['page' => $page - 1])) }}">‹</a>
        @endif

        @for ($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++)
          @if ($p === $page)
            <span class="current">{{ $p }}</span>
          @else
            <a href="{{ route('evaluasi-well.users.index', array_merge($queryBase, ['page' => $p])) }}">{{ $p }}</a>
          @endif
        @endfor

        @if ($page < $lastPage)
          <a href="{{ route('evaluasi-well.users.index', array_merge($queryBase, ['page' => $page + 1])) }}">›</a>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
