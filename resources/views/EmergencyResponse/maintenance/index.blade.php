@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Maintenance')

@section('content')
    <div class="row gy-4">
        <div class="col-md-4">
            <div class="card shadow-none border h-100">
                <div class="card-body text-center p-32">
                    <i class="ri-file-list-3-line text-primary-600" style="font-size: 2rem;"></i>
                    <h6 class="mt-16">Work Order</h6>
                    <p class="text-secondary-light text-sm">Kelola permintaan perbaikan, kalibrasi, dan servis.</p>
                    <a href="{{ route('emergency-response.work-order.index') }}" class="btn btn-primary-600 btn-sm">Buka</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-none border h-100">
                <div class="card-body text-center p-32">
                    <i class="ri-calendar-check-line text-primary-600" style="font-size: 2rem;"></i>
                    <h6 class="mt-16">Jadwal Preventive Maintenance</h6>
                    <p class="text-secondary-light text-sm">Atur jadwal maintenance berulang per equipment.</p>
                    <a href="{{ route('emergency-response.maintenance.schedules.index') }}" class="btn btn-primary-600 btn-sm">Buka</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-none border h-100">
                <div class="card-body text-center p-32">
                    <i class="ri-tools-line text-primary-600" style="font-size: 2rem;"></i>
                    <h6 class="mt-16">Spare Part</h6>
                    <p class="text-secondary-light text-sm">Kelola master data spare part.</p>
                    <a href="{{ route('emergency-response.maintenance.spare-parts.index') }}" class="btn btn-primary-600 btn-sm">Buka</a>
                </div>
            </div>
        </div>
    </div>
@endsection
