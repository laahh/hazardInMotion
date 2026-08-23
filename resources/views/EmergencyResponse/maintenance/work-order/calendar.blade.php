@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Kalender Maintenance')

@php
    $days = [];
    $cursor = $start->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $lastCell = $end->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
    while ($cursor->lte($lastCell)) {
        $days[] = $cursor->copy();
        $cursor->addDay();
    }
@endphp

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex align-items-center justify-content-between">
            <a href="{{ route('emergency-response.work-order.calendar', ['month' => $prevMonth->month, 'year' => $prevMonth->year]) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-arrow-left-s-line"></i></a>
            <h6 class="mb-0">{{ $start->translatedFormat('F Y') }}</h6>
            <a href="{{ route('emergency-response.work-order.calendar', ['month' => $nextMonth->month, 'year' => $nextMonth->year]) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-arrow-right-s-line"></i></a>
        </div>
        <div class="card-body">
            <div class="row row-cols-7 g-2 mb-2 text-center fw-semibold text-secondary-light">
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dayName)
                    <div class="col">{{ $dayName }}</div>
                @endforeach
            </div>
            <div class="row row-cols-7 g-2">
                @foreach ($days as $day)
                    <div class="col">
                        <div class="border rounded p-8 h-100 {{ $day->month !== $start->month ? 'bg-neutral-50' : '' }}" style="min-height: 100px;">
                            <div class="text-sm fw-semibold {{ $day->isToday() ? 'text-primary-600' : 'text-secondary-light' }}">{{ $day->day }}</div>
                            @foreach (($workOrders[$day->format('Y-m-d')] ?? []) as $wo)
                                <a href="{{ route('emergency-response.work-order.show', $wo) }}" class="d-block text-truncate text-sm badge bg-warning-focus text-warning-600 px-8 py-2 radius-4 mt-4">
                                    {{ $wo->work_order_number }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
