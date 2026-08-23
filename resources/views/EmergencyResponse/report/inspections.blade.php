@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Laporan Inspeksi')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">Laporan Inspeksi per Periode</h6></div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-16">
                <div class="col-md-3"><input type="date" name="date_from" value="{{ $dateFrom->format('Y-m-d') }}" class="form-control"></div>
                <div class="col-md-3"><input type="date" name="date_to" value="{{ $dateTo->format('Y-m-d') }}" class="form-control"></div>
                <div class="col-md-3">
                    <div class="form-check mt-8">
                        <input type="checkbox" name="only_non_compliant" value="1" class="form-check-input" id="only-nc" @checked(request('only_non_compliant'))>
                        <label class="form-check-label" for="only-nc">Hanya ada temuan tidak sesuai</label>
                    </div>
                </div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary-600 w-100">Terapkan</button></div>
            </form>
            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead><tr><th>No. Inspeksi</th><th>Target</th><th>Site</th><th>Inspector</th><th>Status</th><th>Waktu</th></tr></thead>
                    <tbody>
                        @forelse ($inspections as $inspection)
                            <tr>
                                <td><a href="{{ route('emergency-response.inspection.show', $inspection) }}">{{ $inspection->inspection_number }}</a></td>
                                <td>{{ $inspection->target->name ?? '-' }}</td>
                                <td>{{ $inspection->site->name ?? '-' }}</td>
                                <td>{{ $inspection->inspector->name ?? '-' }}</td>
                                <td>{{ $inspection->statusLabel() }}</td>
                                <td>{{ optional($inspection->inspected_at)->format('d M Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $inspections->links() }}</div>
        </div>
    </div>
@endsection
