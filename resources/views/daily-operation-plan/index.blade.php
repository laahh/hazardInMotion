 @extends('layouts.master')

@section('title', 'Daily Operation Plan (DOP)')

@section('content')
    <x-page-title title="Daily Operation Plan (DOP)" pagetitle="Daftar DOP" />

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
                <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h5 class="mb-0">Daftar Daily Operation Plan</h5>
                        <small class="text-muted">Menampilkan {{ $dops->firstItem() ?? 0 }}-{{ $dops->lastItem() ?? 0 }} dari {{ $dops->total() }} data</small>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('daily-operation-plan.export') }}" class="btn btn-success rounded-3">
                            <i class="material-icons-outlined align-middle" style="font-size:18px;">download</i> Download Excel
                        </a>
                        <a href="{{ route('daily-operation-plan.create') }}" class="btn btn-primary rounded-3">
                            <i class="bx bx-plus"></i> Tambah DOP Baru
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-3">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tanggal</th>
                                    <th>Pekerjaan</th>
                                    <th>Unit ID</th>
                                    <th>Lokasi</th>
                                    <th>Latitude</th>
                                    <th>Longitude</th>
                                    <th>CCTV</th>
                                    <th>Foto</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($dops as $dop)
                                    <tr>
                                        <td>{{ ($dops->firstItem() ?? 0) + $loop->index }}</td>
                                        <td>{{ $dop->tanggal->format('d M Y') }}</td>
                                        <td>{{ $dop->pekerjaan }}</td>
                                        <td>{{ $dop->unit_id }}</td>
                                        <td>{{ $dop->lokasi }}</td>
                                        <td>{{ $dop->latitude ?? '-' }}</td>
                                        <td>{{ $dop->longitude ?? '-' }}</td>
                                        <td>
                                            @if($dop->cctvs->count() > 0)
                                                <span class="badge bg-info">{{ $dop->cctvs->count() }} CCTV</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($dop->foto_pekerjaan)
                                                <a href="{{ asset('storage/' . $dop->foto_pekerjaan) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bx bx-image"></i> Lihat
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input status-toggle" type="checkbox" role="switch" 
                                                    data-id="{{ $dop->id }}" 
                                                    {{ $dop->status ? 'checked' : '' }}
                                                    style="cursor: pointer; width: 3em; height: 1.5em;">
                                                <span class="status-label badge {{ $dop->status ? 'bg-success' : 'bg-secondary' }}">
                                                    {{ $dop->status ? 'ON' : 'OFF' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="{{ route('daily-operation-plan.show', $dop->id) }}" class="btn btn-sm btn-info rounded-3" title="Detail">
                                                <i class="material-icons-outlined text-white">visibility</i>
                                                </a>
                                                <a href="{{ route('daily-operation-plan.edit', $dop->id) }}" class="btn btn-sm btn-warning rounded-3" title="Edit">
                                                <i class="material-icons-outlined">edit</i>
                                                </a>
                                                <form action="{{ route('daily-operation-plan.destroy', $dop->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus DOP ini?');">
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
                                        <td colspan="11" class="text-center text-muted">Belum ada data DOP</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-column flex-lg-row gap-3">
                        <div class="text-muted">
                            Menampilkan {{ $dops->firstItem() ?? 0 }}-{{ $dops->lastItem() ?? 0 }} dari {{ $dops->total() }} data
                        </div>
                        {{ $dops->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.status-toggle').forEach(function(toggle) {
            toggle.addEventListener('change', function() {
                const dopId = this.dataset.id;
                const checkbox = this;
                const statusLabel = this.parentElement.querySelector('.status-label');
                
                fetch(`{{ url('daily-operation-plan') }}/${dopId}/toggle-status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.status) {
                            statusLabel.textContent = 'ON';
                            statusLabel.classList.remove('bg-secondary');
                            statusLabel.classList.add('bg-success');
                        } else {
                            statusLabel.textContent = 'OFF';
                            statusLabel.classList.remove('bg-success');
                            statusLabel.classList.add('bg-secondary');
                        }
                    } else {
                        checkbox.checked = !checkbox.checked;
                        alert('Gagal mengubah status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    checkbox.checked = !checkbox.checked;
                    alert('Terjadi kesalahan');
                });
            });
        });
    });
</script>
@endsection
