@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Kanban Work Order')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-16">
        <a href="{{ route('emergency-response.work-order.index') }}" class="text-secondary-light"><i class="ri-arrow-left-line"></i> Kembali ke daftar</a>
    </div>

    <div class="kanban-wrapper">
        <div class="d-flex align-items-start gap-24" style="overflow-x: auto;">
            @foreach ($statuses as $statusValue => $statusLabel)
                <div class="kanban-item radius-12" style="min-width: 280px;">
                    <div class="card p-0 radius-12 overflow-hidden shadow-none border">
                        <div class="card-body p-0 pb-24">
                            <div class="d-flex align-items-center gap-2 justify-content-between ps-16 pt-16 pe-16">
                                <h6 class="text-md fw-semibold mb-0">{{ $statusLabel }}</h6>
                                <span class="badge bg-neutral-200 text-secondary-light">{{ ($workOrders[$statusValue] ?? collect())->count() }}</span>
                            </div>
                            <div class="ps-16 pt-16 pe-16">
                                @forelse (($workOrders[$statusValue] ?? collect()) as $wo)
                                    <a href="{{ route('emergency-response.work-order.show', $wo) }}" class="d-block text-decoration-none">
                                        <div class="kanban-card bg-neutral-50 p-16 radius-8 mb-16">
                                            <h6 class="kanban-title text-md fw-semibold mb-8">{{ $wo->work_order_number }}</h6>
                                            <p class="kanban-desc text-secondary-light text-sm mb-8">{{ $wo->equipmentable->name ?? '-' }}</p>
                                            <span class="kanban-tag fw-semibold text-sm">{{ $wo->assignedTechnician->name ?? 'Belum ditugaskan' }}</span>
                                        </div>
                                    </a>
                                @empty
                                    <p class="text-secondary-light text-sm text-center py-16">Kosong</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
