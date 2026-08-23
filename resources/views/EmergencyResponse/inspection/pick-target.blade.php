@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Inspeksi Baru')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">1. Pilih Site</h6></div>
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <select name="site_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Pilih Site --</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected($siteId === $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    @if ($siteId)
        <div class="card shadow-none border mt-24">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">2. Pilih Equipment / Safety Device</h6>
                <a href="{{ route('emergency-response.scan.index') }}" class="btn btn-sm btn-outline-secondary"><i class="ri-qr-scan-2-line"></i> atau Scan QR</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table bordered-table mb-0">
                        <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                            @forelse ($targets as $target)
                                <tr>
                                    <td>{{ $target['code'] }}</td>
                                    <td>{{ $target['name'] }}</td>
                                    <td>{{ $target['type'] === 'equipment' ? 'Emergency Equipment' : 'Safety Device' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('emergency-response.inspection.create', ['type' => $target['type'], 'id' => $target['id']]) }}" class="btn btn-sm btn-primary-600">
                                            Mulai Inspeksi
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-secondary-light py-24">Tidak ada equipment/safety device di site ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
