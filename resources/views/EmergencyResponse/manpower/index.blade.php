@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Manpower')

@section('content')
    <div class="row gy-4 mb-24">
        <div class="col-md-3">
            <div class="card shadow-none border h-100">
                <div class="card-body text-center p-24">
                    <i class="ri-team-line text-primary-600" style="font-size: 1.75rem;"></i>
                    <h6 class="mt-8 mb-0">{{ $totalEmployees }}</h6>
                    <p class="text-secondary-light text-sm mb-0">Total Personel Aktif</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-none border h-100">
                <div class="card-body text-center p-24">
                    <i class="ri-user-follow-line text-success-600" style="font-size: 1.75rem;"></i>
                    <h6 class="mt-8 mb-0">{{ $onDutyToday->count() }}</h6>
                    <p class="text-secondary-light text-sm mb-0">On-Duty Hari Ini</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-none border h-100">
                <div class="card-body text-center p-24">
                    <i class="ri-award-line text-warning-600" style="font-size: 1.75rem;"></i>
                    <h6 class="mt-8 mb-0">{{ $expiringTrainings->count() }}</h6>
                    <p class="text-secondary-light text-sm mb-0">Training Akan Expired (30 hari)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-none border h-100">
                <div class="card-body text-center p-24">
                    <i class="ri-shield-star-line text-danger-600" style="font-size: 1.75rem;"></i>
                    <h6 class="mt-8 mb-0">{{ $expiringCertifications->count() }}</h6>
                    <p class="text-secondary-light text-sm mb-0">Sertifikasi Akan Expired (30 hari)</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row gy-4 mb-24">
        <div class="col-md-3">
            <a href="{{ route('emergency-response.manpower.employees.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body text-center p-24">
                    <i class="ri-contacts-line text-primary-600" style="font-size: 1.5rem;"></i>
                    <p class="mt-8 mb-0 fw-semibold">Data Personel</p>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('emergency-response.manpower.attendance.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body text-center p-24">
                    <i class="ri-calendar-check-line text-primary-600" style="font-size: 1.5rem;"></i>
                    <p class="mt-8 mb-0 fw-semibold">Kehadiran</p>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('emergency-response.manpower.trainings.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body text-center p-24">
                    <i class="ri-book-2-line text-primary-600" style="font-size: 1.5rem;"></i>
                    <p class="mt-8 mb-0 fw-semibold">Katalog Training</p>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('emergency-response.manpower.certifications.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body text-center p-24">
                    <i class="ri-award-line text-primary-600" style="font-size: 1.5rem;"></i>
                    <p class="mt-8 mb-0 fw-semibold">Katalog Sertifikasi</p>
                </div>
            </a>
        </div>
    </div>

    <div class="row gy-4">
        <div class="col-md-6">
            <div class="card shadow-none border h-100">
                <div class="card-header"><h6 class="mb-0">Training Akan Expired</h6></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($expiringTrainings as $item)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $item->employee->full_name ?? '-' }} — {{ $item->training->name ?? '-' }}</span>
                                <span class="text-warning-600 text-sm">{{ $item->expires_at->format('d M Y') }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-secondary-light text-center py-16">Tidak ada.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-none border h-100">
                <div class="card-header"><h6 class="mb-0">Sertifikasi Akan Expired</h6></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($expiringCertifications as $item)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $item->employee->full_name ?? '-' }} — {{ $item->certification->name ?? '-' }}</span>
                                <span class="text-danger-600 text-sm">{{ $item->expires_at->format('d M Y') }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-secondary-light text-center py-16">Tidak ada.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
