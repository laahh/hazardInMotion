@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Master Data')

@section('content')
    <div class="row gy-4">
        @foreach ($groups as $groupName => $items)
            <div class="col-xl-4 col-md-6">
                <div class="card shadow-none border h-100">
                    <div class="card-header">
                        <h6 class="mb-0">{{ $groupName }}</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            @foreach ($items as $item)
                                <li class="list-group-item d-flex align-items-center justify-content-between">
                                    <span>{{ $item['label'] }}</span>
                                    <a href="{{ \Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route']) : '#' }}" class="btn btn-sm btn-outline-primary-600">
                                        Kelola
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
