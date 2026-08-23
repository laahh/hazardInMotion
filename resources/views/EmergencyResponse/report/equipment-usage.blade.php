@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Laporan Penggunaan Equipment saat Insiden')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">Penggunaan Equipment saat Insiden</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead><tr><th>Insiden</th><th>Equipment</th><th>Jumlah</th><th>Kondisi Setelah</th><th>Waktu</th></tr></thead>
                    <tbody>
                        @forelse ($usages as $usage)
                            <tr>
                                <td><a href="{{ route('emergency-response.incident.show', $usage->incident) }}">{{ $usage->incident->incident_number ?? '-' }}</a></td>
                                <td>{{ $usage->equipmentable->name ?? '-' }} ({{ $usage->equipmentable->code ?? '-' }})</td>
                                <td>{{ $usage->quantity_used }}</td>
                                <td>{{ $usage->condition_after ?: '-' }}</td>
                                <td>{{ $usage->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $usages->links() }}</div>
        </div>
    </div>
@endsection
