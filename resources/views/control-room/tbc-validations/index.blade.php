@extends('control-room.layouts.app')

@section('page-title', 'Validasi TBC')

@push('styles')
    <link rel="stylesheet" href="{{ asset('wowdash-admin/assets/css/control-room-dashboard.css') }}?v={{ filemtime(public_path('wowdash-admin/assets/css/control-room-dashboard.css')) }}">
@endpush

@section('content')
    <div class="card shadow-none border mb-24">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h6 class="mb-0">Validasi TBC</h6>
                <p class="text-secondary-light text-xs mb-0">Upload Excel atau input manual. Tasklist wajib dan unik — sama dengan id laporan SAP (Hazard/Inspeksi) agar masuk Ratio TBC dashboard.</p>
            </div>
            <a href="{{ route('control-room.tbc-validations.create') }}" class="btn btn-primary-600 btn-sm">
                <i class="ri-add-line"></i> Tambah
            </a>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-4">
                    <form method="GET" action="{{ route('control-room.tbc-validations.excel-template') }}">
                        <label class="form-label text-sm mb-1">Template Excel</label>
                        <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Unduh Template</button>
                    </form>
                </div>
                <div class="col-lg-8">
                    <form method="POST" action="{{ route('control-room.tbc-validations.excel-import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-8">
                            <label class="form-label text-sm mb-1" for="ocr-tbc-file">Unggah .xlsx</label>
                            <input type="file" name="file" id="ocr-tbc-file" class="form-control form-control-sm" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" required>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary-600 btn-sm w-100">Unggah</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-header">
            <form method="GET" action="{{ route('control-room.tbc-validations.index') }}" class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label text-sm mb-1" for="ocr-tbc-q">Cari</label>
                    <input type="search" name="q" id="ocr-tbc-q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Tasklist, validator, SID, kronologi…">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">Terapkan</button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Tasklist</th>
                            <th>No Alert</th>
                            <th>Validator</th>
                            <th>TobeConcernedHazard</th>
                            <th>GR</th>
                            <th>SID Pekerja</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row->tasklist }}</td>
                                <td>{{ $row->no_alert ?: '—' }}</td>
                                <td>{{ $row->validator ?: '—' }}</td>
                                <td>{{ $row->to_be_concerned_hazard ?: '—' }}</td>
                                <td>{{ $row->gr ?: '—' }}</td>
                                <td>{{ $row->sid_pekerja_terlibat ?: '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('control-room.tbc-validations.edit', $row) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('control-room.tbc-validations.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Hapus validasi TBC {{ $row->tasklist }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-secondary-light py-24">Belum ada validasi TBC. Unduh template, isi Tasklist, lalu unggah.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($rows->hasPages())
            <div class="card-footer">{{ $rows->links() }}</div>
        @endif
    </div>
@endsection
