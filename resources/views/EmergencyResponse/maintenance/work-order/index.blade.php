@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Work Order')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Work Order</h6>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('emergency-response.work-order.kanban') }}" class="btn btn-outline-secondary btn-sm"><i class="ri-layout-column-line"></i> Kanban</a>
                <a href="{{ route('emergency-response.work-order.calendar') }}" class="btn btn-outline-secondary btn-sm"><i class="ri-calendar-line"></i> Kalender</a>
                <a href="{{ route('emergency-response.work-order.export') }}" class="btn btn-outline-secondary btn-sm"><i class="ri-file-excel-2-line"></i> Export</a>
                <a href="{{ route('emergency-response.work-order.create') }}" class="btn btn-primary-600 btn-sm"><i class="ri-add-line"></i> Buat Work Order</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-16">
                <div class="col-md-3">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari no. WO...">
                </div>
                <div class="col-md-3">
                    <select name="work_type" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Jenis Pekerjaan</option>
                        @foreach ($workTypes as $value => $label)
                            <option value="{{ $value }}" @selected(request('work_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="site_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Site</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected(request('site_id') === $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-primary-600 w-100"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead>
                        <tr><th>No. WO</th><th>Equipment</th><th>Jenis</th><th>Site</th><th>Teknisi</th><th>Status</th><th>Target Selesai</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($workOrders as $wo)
                            <tr>
                                <td><a href="{{ route('emergency-response.work-order.show', $wo) }}">{{ $wo->work_order_number }}</a></td>
                                <td>{{ $wo->equipmentable->name ?? '-' }}</td>
                                <td>{{ $wo->workTypeLabel() }}</td>
                                <td>{{ $wo->site->name ?? '-' }}</td>
                                <td>{{ $wo->assignedTechnician->name ?? '-' }}</td>
                                <td><span class="badge bg-info-focus text-info-600 px-16 py-4 radius-4">{{ $wo->statusLabel() }}</span></td>
                                <td>{{ optional($wo->target_end_at)->format('d M Y') ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('emergency-response.work-order.show', $wo) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-eye-line"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-secondary-light py-24">Belum ada work order.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $workOrders->links() }}</div>
        </div>
    </div>
@endsection
