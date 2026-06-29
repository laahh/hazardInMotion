@extends('layouts.master')

@section('title', 'CCTV P2H Checklist')

@section('content')
    <x-page-title title="CCTV P2H Checklist" pagetitle="Daftar P2H Checklist" />

    <div class="row">
        <div class="col-12">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show rounded-4" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show rounded-4" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card rounded-4">
                <div class="card-header">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                        <div>
                            <h5 class="mb-0">Daftar CCTV P2H Checklist</h5>
                            <small class="text-muted">Menampilkan {{ $checklists->firstItem() ?? 0 }}-{{ $checklists->lastItem() ?? 0 }} dari {{ $checklists->total() }} data</small>
                        </div>
                        <a href="{{ route('cctv-p2h-checklist.export', request()->query()) }}" class="btn btn-success rounded-3">
                            <i class="material-icons-outlined align-middle" style="font-size:18px;">download</i> Download Excel
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filter Section -->
                    <form method="GET" action="{{ route('cctv-p2h-checklist.index') }}" class="mb-4">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label for="control_room" class="form-label">Control Room</label>
                                <select name="control_room" id="control_room" class="form-select">
                                    <option value="">Semua</option>
                                    @foreach($controlRooms as $room)
                                        <option value="{{ $room }}" {{ request('control_room') == $room ? 'selected' : '' }}>{{ $room }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="shift" class="form-label">Shift</label>
                                <select name="shift" id="shift" class="form-select">
                                    <option value="">Semua</option>
                                    <option value="1" {{ request('shift') == '1' ? 'selected' : '' }}>Shift 1</option>
                                    <option value="2" {{ request('shift') == '2' ? 'selected' : '' }}>Shift 2</option>
                                    <option value="3" {{ request('shift') == '3' ? 'selected' : '' }}>Shift 3</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">Semua</option>
                                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="verified" {{ request('status') == 'verified' ? 'selected' : '' }}>Verified</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="start_date" class="form-label">Dari Tanggal</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ request('start_date') }}">
                            </div>
                            <div class="col-md-2">
                                <label for="end_date" class="form-label">Sampai Tanggal</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ request('end_date') }}">
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="material-icons-outlined">search</i> Filter
                                    </button>
                                    <a href="{{ route('cctv-p2h-checklist.index') }}" class="btn btn-secondary">
                                        <i class="material-icons-outlined">refresh</i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-3">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tanggal</th>
                                    <th>Control Room</th>
                                    <th>Shift</th>
                                    <th>Nama Pengawas</th>
                                    <th>Jenis CCTV</th>
                                    <th>Pemeriksaan Fisik</th>
                                    <th>Pemeriksaan Fungsi</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($checklists as $checklist)
                                    <tr>
                                        <td>{{ ($checklists->firstItem() ?? 0) + $loop->index }}</td>
                                        <td>{{ $checklist->tanggal_pemeriksaan->format('d M Y') }}</td>
                                        <td>{{ $checklist->control_room }}</td>
                                        <td>
                                            <span class="badge bg-info">Shift {{ $checklist->shift }}</span>
                                        </td>
                                        <td>{{ $checklist->nama_pengawas }}</td>
                                        <td>
                                            @if($checklist->jenis_cctv && is_array($checklist->jenis_cctv))
                                                @foreach($checklist->jenis_cctv as $jenis)
                                                    <span class="badge bg-secondary me-1">{{ $jenis }}</span>
                                                @endforeach
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($checklist->pemeriksaan_fisik && is_array($checklist->pemeriksaan_fisik))
                                                <span class="badge bg-primary">{{ count($checklist->pemeriksaan_fisik) }} item</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($checklist->pemeriksaan_fungsi && is_array($checklist->pemeriksaan_fungsi))
                                                <span class="badge bg-primary">{{ count($checklist->pemeriksaan_fungsi) }} item</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($checklist->status == 'completed')
                                                <span class="badge bg-success">Completed</span>
                                            @elseif($checklist->status == 'verified')
                                                <span class="badge bg-primary">Verified</span>
                                            @else
                                                <span class="badge bg-warning">Draft</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="{{ route('cctv-p2h-checklist.show', $checklist->id) }}" class="btn btn-sm btn-info rounded-3" title="Detail">
                                                    <i class="material-icons-outlined text-white">visibility</i>
                                                </a>
                                                <form action="{{ route('cctv-p2h-checklist.destroy', $checklist->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data P2H ini?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger rounded-3" title="Hapus">
                                                        <i class="material-icons-outlined">delete</i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">Belum ada data P2H Checklist</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-column flex-lg-row gap-3">
                        <div class="text-muted">
                            Menampilkan {{ $checklists->firstItem() ?? 0 }}-{{ $checklists->lastItem() ?? 0 }} dari {{ $checklists->total() }} data
                        </div>
                        {{ $checklists->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
