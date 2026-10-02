@extends('evaluasi-well.layouts.app')

@section('title', 'Assignment Mitra')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Assignment Mitra Kerja</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.mitra.index') }}" class="hover-text-primary">Mitra Kerja</a>
    </li>
    <li>-</li>
    <li class="fw-medium">Assignment</li>
  </ul>
</div>

@if (session('success'))
<div class="alert alert-success bg-success-100 text-success-600 border-success-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ session('success') }}
</div>
@endif

@if ($errors->has('form'))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ $errors->first('form') }}
</div>
@endif

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center justify-content-between gap-2 flex-wrap">
    <h6 class="text-lg fw-semibold mb-0">Daftar Assignment</h6>
    <a href="{{ route('evaluasi-well.mitra-assignments.create') }}" class="btn btn-primary-600 radius-8 px-16 py-10">
      Tambah Assignment
    </a>
  </div>
  <div class="card-body p-24">
    <div class="table-responsive">
      <table class="table bordered-table mb-0">
        <thead>
          <tr>
            <th>User</th>
            <th>Perusahaan &amp; Site</th>
            <th>Status</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($assignments as $row)
            @php
              $companies = $row->groupedCompanySites();
            @endphp
            <tr>
              <td>
                <div class="fw-medium">{{ $row->user?->name ?? '-' }}</div>
                <div class="text-secondary-light text-sm">{{ $row->user?->email ?? '' }}</div>
              </td>
              <td>
                @if ($companies === [])
                  <span class="text-secondary-light">-</span>
                @else
                  <div class="d-flex flex-column gap-6">
                    @foreach ($companies as $company)
                      <div>
                        <div class="fw-medium">{{ $company['perusahaan'] }}</div>
                        <div class="text-secondary-light text-sm">{{ implode(', ', $company['sites']) }}</div>
                      </div>
                    @endforeach
                  </div>
                @endif
              </td>
              <td>
                @if ($row->is_active)
                  <span class="bg-success-focus text-success-main px-12 py-4 radius-4 fw-medium text-sm">Aktif</span>
                @else
                  <span class="bg-neutral-200 text-secondary-light px-12 py-4 radius-4 fw-medium text-sm">Nonaktif</span>
                @endif
              </td>
              <td class="text-end text-nowrap">
                <a href="{{ route('evaluasi-well.mitra-assignments.edit', $row->id) }}" class="btn btn-outline-primary-600 btn-sm radius-8">Edit</a>
                <form action="{{ route('evaluasi-well.mitra-assignments.destroy', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus assignment ini?');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-outline-danger-600 btn-sm radius-8">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-secondary-light py-24">Belum ada assignment mitra.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
