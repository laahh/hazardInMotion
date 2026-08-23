@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Laporan Training & Sertifikasi')

@section('content')
    <div class="row gy-4">
        <div class="col-lg-6">
            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Training Personel</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table bordered-table mb-0">
                            <thead><tr><th>Nama</th><th>Training</th><th>Expired</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse ($trainings as $item)
                                    <tr>
                                        <td>{{ $item->employee->full_name ?? '-' }}</td>
                                        <td>{{ $item->training->name ?? '-' }}</td>
                                        <td>{{ $item->expires_at->format('d M Y') }}</td>
                                        <td>{{ $item->statusLabel() }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-16">{{ $trainings->links() }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Sertifikasi Personel</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table bordered-table mb-0">
                            <thead><tr><th>Nama</th><th>Sertifikasi</th><th>Expired</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse ($certifications as $item)
                                    <tr>
                                        <td>{{ $item->employee->full_name ?? '-' }}</td>
                                        <td>{{ $item->certification->name ?? '-' }}</td>
                                        <td>{{ $item->expires_at->format('d M Y') }}</td>
                                        <td>{{ $item->statusLabel() }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-16">{{ $certifications->links() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
