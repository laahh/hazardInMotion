@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Laporan Kehadiran Personel')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">Kehadiran Personel per Periode</h6></div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-16">
                <div class="col-md-3"><input type="date" name="date_from" value="{{ $dateFrom->format('Y-m-d') }}" class="form-control"></div>
                <div class="col-md-3"><input type="date" name="date_to" value="{{ $dateTo->format('Y-m-d') }}" class="form-control"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary-600 w-100">Terapkan</button></div>
            </form>
            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead><tr><th>Tanggal</th><th>Nama</th><th>Shift</th><th>Status</th><th>Check-in</th><th>Check-out</th></tr></thead>
                    <tbody>
                        @forelse ($attendance as $item)
                            <tr>
                                <td>{{ $item->date->format('d M Y') }}</td>
                                <td>{{ $item->employee->full_name ?? '-' }}</td>
                                <td>{{ $item->shift->name ?? '-' }}</td>
                                <td>{{ $item->statusLabel() }}</td>
                                <td>{{ optional($item->check_in_at)->format('H:i') ?? '-' }}</td>
                                <td>{{ optional($item->check_out_at)->format('H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $attendance->links() }}</div>
        </div>
    </div>
@endsection
