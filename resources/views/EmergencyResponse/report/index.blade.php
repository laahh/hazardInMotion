@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Laporan')

@php
    $reports = [
        ['label' => 'Equipment & Kondisi', 'desc' => 'Daftar equipment, kondisi, dan yang kedaluwarsa.', 'route' => 'emergency-response.report.equipment', 'icon' => 'ri-fire-line'],
        ['label' => 'Inspeksi', 'desc' => 'Rekap inspeksi per periode & hasil tidak sesuai.', 'route' => 'emergency-response.report.inspections', 'icon' => 'ri-clipboard-line'],
        ['label' => 'Insiden', 'desc' => 'Rekap insiden per jenis/lokasi/keparahan & response time.', 'route' => 'emergency-response.report.incidents', 'icon' => 'ri-alarm-warning-line'],
        ['label' => 'Penggunaan Equipment saat Insiden', 'desc' => 'Equipment/safety device yang dipakai saat insiden.', 'route' => 'emergency-response.report.equipment-usage', 'icon' => 'ri-tools-line'],
        ['label' => 'Work Order & Maintenance', 'desc' => 'Work order per status, overdue, dan biaya.', 'route' => 'emergency-response.report.work-orders', 'icon' => 'ri-file-list-3-line'],
        ['label' => 'Kehadiran Personel', 'desc' => 'Rekap kehadiran per periode.', 'route' => 'emergency-response.report.attendance', 'icon' => 'ri-calendar-check-line'],
        ['label' => 'Training & Sertifikasi', 'desc' => 'Status training dan sertifikasi personel.', 'route' => 'emergency-response.report.training-certification', 'icon' => 'ri-award-line'],
    ];
@endphp

@section('content')
    <div class="row gy-4">
        @foreach ($reports as $report)
            <div class="col-md-4">
                <a href="{{ route($report['route']) }}" class="card shadow-none border h-100 text-decoration-none">
                    <div class="card-body p-24">
                        <i class="{{ $report['icon'] }} text-primary-600" style="font-size: 1.75rem;"></i>
                        <h6 class="mt-12 mb-4">{{ $report['label'] }}</h6>
                        <p class="text-secondary-light text-sm mb-0">{{ $report['desc'] }}</p>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endsection
