@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Laporan Work Order & Maintenance')

@section('content')
    <div class="card shadow-none border mb-24">
        <div class="card-body text-center">
            <p class="text-secondary-light text-sm mb-4">Total Biaya Maintenance Aktual</p>
            <h6 class="mb-0">Rp {{ number_format((float) $totalActualCost, 0, ',', '.') }}</h6>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">Work Order per Status</h6></div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-16">
                <div class="col-md-3">
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-8">
                        <input type="checkbox" name="only_overdue" value="1" class="form-check-input" id="only-overdue" @checked(request('only_overdue')) onchange="this.form.submit()">
                        <label class="form-check-label" for="only-overdue">Hanya yang overdue</label>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead><tr><th>No. WO</th><th>Equipment</th><th>Teknisi</th><th>Status</th><th>Target Selesai</th><th>Biaya Aktual</th></tr></thead>
                    <tbody>
                        @forelse ($workOrders as $wo)
                            <tr>
                                <td><a href="{{ route('emergency-response.work-order.show', $wo) }}">{{ $wo->work_order_number }}</a></td>
                                <td>{{ $wo->equipmentable->name ?? '-' }}</td>
                                <td>{{ $wo->assignedTechnician->name ?? '-' }}</td>
                                <td>{{ $wo->statusLabel() }}</td>
                                <td>{{ optional($wo->target_end_at)->format('d M Y') ?? '-' }}</td>
                                <td>{{ $wo->actual_cost ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $workOrders->links() }}</div>
        </div>
    </div>
@endsection
