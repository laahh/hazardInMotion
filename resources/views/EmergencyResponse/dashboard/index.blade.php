@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Dashboard')

@section('content')
    <form method="GET" class="card shadow-none border mb-24">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label text-sm mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" value="{{ $dateFrom->format('Y-m-d') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-sm mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" value="{{ $dateTo->format('Y-m-d') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-sm mb-1">Site</label>
                    <select name="site_id" class="form-control">
                        <option value="">Semua Site</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected($filters['siteId'] === $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-sm mb-1">Departemen</label>
                    <select name="department_id" class="form-control">
                        <option value="">Semua Departemen</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($filters['departmentId'] === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-sm mb-1">Kategori Equipment</label>
                    <select name="equipment_category_id" class="form-control">
                        <option value="">Semua Kategori</option>
                        @foreach ($equipmentCategories as $category)
                            <option value="{{ $category->id }}" @selected($filters['equipmentCategoryId'] === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary-600 w-100">Terapkan Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="row gy-4 mb-24">
        <div class="col-xxl-3 col-sm-6">
            <a href="{{ route('emergency-response.equipment.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body p-20">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div><p class="fw-medium text-primary-light mb-1">Total Emergency Equipment</p><h6 class="mb-0">{{ $totalEquipment }}</h6></div>
                        <div class="w-50-px h-50-px bg-primary-100 text-primary-600 rounded-circle d-flex justify-content-center align-items-center"><i class="ri-fire-line text-2xl"></i></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xxl-3 col-sm-6">
            <a href="{{ route('emergency-response.safety-device.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body p-20">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div><p class="fw-medium text-primary-light mb-1">Total Safety Device</p><h6 class="mb-0">{{ $totalSafetyDevice }}</h6></div>
                        <div class="w-50-px h-50-px bg-success-100 text-success-600 rounded-circle d-flex justify-content-center align-items-center"><i class="ri-shield-check-line text-2xl"></i></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xxl-3 col-sm-6">
            <a href="{{ route('emergency-response.equipment.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body p-20">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div><p class="fw-medium text-primary-light mb-1">Equipment Kedaluwarsa</p><h6 class="mb-0">{{ $expiredEquipment }}</h6></div>
                        <div class="w-50-px h-50-px bg-danger-100 text-danger-600 rounded-circle d-flex justify-content-center align-items-center"><i class="ri-error-warning-line text-2xl"></i></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xxl-3 col-sm-6">
            <a href="{{ route('emergency-response.inspection.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body p-20">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div><p class="fw-medium text-primary-light mb-1">Inspeksi Bulan Ini</p><h6 class="mb-0">{{ $inspectionsThisMonth }} <span class="text-sm text-warning-600">({{ $overdueInspectionSchedules }} overdue)</span></h6></div>
                        <div class="w-50-px h-50-px bg-info-100 text-info-600 rounded-circle d-flex justify-content-center align-items-center"><i class="ri-clipboard-line text-2xl"></i></div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row gy-4 mb-24">
        @foreach (\App\Models\EmergencyResponse\Incident\Incident::STATUSES as $value => $label)
            <div class="col-xxl-2 col-sm-6">
                <a href="{{ route('emergency-response.incident.index', ['status' => $value]) }}" class="card shadow-none border h-100 text-decoration-none">
                    <div class="card-body p-20 text-center">
                        <p class="fw-medium text-primary-light mb-1">{{ $label }}</p>
                        <h6 class="mb-0">{{ $incidentsByStatus[$value] ?? 0 }}</h6>
                    </div>
                </a>
            </div>
        @endforeach
        <div class="col-xxl-2 col-sm-6">
            <div class="card shadow-none border h-100">
                <div class="card-body p-20 text-center">
                    <p class="fw-medium text-primary-light mb-1">Rata-rata Response Time</p>
                    <h6 class="mb-0">{{ $avgResponseTime !== null ? $avgResponseTime.' menit' : '-' }}</h6>
                </div>
            </div>
        </div>
    </div>

    <div class="row gy-4 mb-24">
        <div class="col-xxl-2 col-sm-6">
            <a href="{{ route('emergency-response.work-order.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body p-20 text-center"><p class="fw-medium text-primary-light mb-1">Work Order Aktif</p><h6 class="mb-0">{{ $activeWorkOrders }}</h6></div>
            </a>
        </div>
        <div class="col-xxl-2 col-sm-6">
            <a href="{{ route('emergency-response.work-order.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body p-20 text-center"><p class="fw-medium text-primary-light mb-1">Work Order Overdue</p><h6 class="mb-0 text-danger-600">{{ $overdueWorkOrders }}</h6></div>
            </a>
        </div>
        <div class="col-xxl-2 col-sm-6">
            <a href="{{ route('emergency-response.maintenance.schedules.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body p-20 text-center"><p class="fw-medium text-primary-light mb-1">Maintenance Jatuh Tempo (30 hr)</p><h6 class="mb-0">{{ $maintenanceDueSoon }}</h6></div>
            </a>
        </div>
        <div class="col-xxl-2 col-sm-6">
            <a href="{{ route('emergency-response.manpower.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body p-20 text-center"><p class="fw-medium text-primary-light mb-1">Sertifikasi Akan Expired</p><h6 class="mb-0">{{ $certificationsExpiringSoon }}</h6></div>
            </a>
        </div>
        <div class="col-xxl-2 col-sm-6">
            <a href="{{ route('emergency-response.manpower.attendance.index') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body p-20 text-center"><p class="fw-medium text-primary-light mb-1">Personel On-Duty</p><h6 class="mb-0">{{ $onDutyToday }}</h6></div>
            </a>
        </div>
        <div class="col-xxl-2 col-sm-6">
            <a href="{{ route('emergency-response.notification.alerts') }}" class="card shadow-none border h-100 text-decoration-none">
                <div class="card-body p-20 text-center"><p class="fw-medium text-primary-light mb-1">Inspeksi Schedule Overdue</p><h6 class="mb-0">{{ $overdueInspectionSchedules }}</h6></div>
            </a>
        </div>
    </div>

    <div class="row gy-4 mb-24">
        <div class="col-lg-6">
            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Tren Insiden Bulanan</h6></div>
                <div class="card-body"><div id="chart-incident-trend"></div></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Tren Response Time (menit)</h6></div>
                <div class="card-body"><div id="chart-response-trend"></div></div>
            </div>
        </div>
    </div>

    <div class="row gy-4 mb-24">
        <div class="col-lg-4">
            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Kondisi Equipment</h6></div>
                <div class="card-body"><div id="chart-equipment-condition"></div></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Peta Persebaran Equipment &amp; Insiden</h6></div>
                <div class="card-body">
                    <div id="er-map" style="height: 360px; border-radius: 8px;"></div>
                    <p class="text-secondary-light text-xs mt-8 mb-0">Peta membutuhkan koneksi internet keluar (tile OpenStreetMap). Jika tidak tampil, gunakan daftar koordinat di bawah.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row gy-4">
        <div class="col-lg-6">
            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Insiden Terbaru</h6></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($recentIncidents as $incident)
                            <li class="list-group-item d-flex justify-content-between">
                                <a href="{{ route('emergency-response.incident.show', $incident) }}">{{ $incident->incident_number }} — {{ $incident->incidentType->name ?? '-' }}</a>
                                <span class="badge bg-info-focus text-info-600 px-8 py-2 radius-4">{{ $incident->statusLabel() }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-secondary-light text-center py-16">Tidak ada insiden.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-none border">
                <div class="card-header"><h6 class="mb-0">Alert Terbaru</h6></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($recentAlerts as $alert)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $alert->alert_type }} — {{ $alert->alertable->name ?? $alert->alertable->work_order_number ?? '-' }}</span>
                                <span class="text-secondary-light text-sm">{{ $alert->created_at->format('d M Y') }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-secondary-light text-center py-16">Tidak ada alert.</li>
                        @endforelse
                    </ul>
                    <div class="p-16"><a href="{{ route('emergency-response.notification.alerts') }}" class="text-primary-600 text-sm">Lihat semua alert →</a></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function () {
            var incidentTrend = @json($incidentTrend);
            var responseTrend = @json($responseTimeTrend);
            var equipmentCondition = @json($equipmentByCondition);

            if (window.ApexCharts) {
                new ApexCharts(document.getElementById('chart-incident-trend'), {
                    chart: { type: 'line', height: 260, toolbar: { show: false } },
                    series: [{ name: 'Insiden', data: Object.values(incidentTrend) }],
                    xaxis: { categories: Object.keys(incidentTrend) },
                    colors: ['#dc3545'],
                }).render();

                new ApexCharts(document.getElementById('chart-response-trend'), {
                    chart: { type: 'line', height: 260, toolbar: { show: false } },
                    series: [{ name: 'Menit', data: Object.values(responseTrend).map(function (v) { return v ? Math.round(v) : 0; }) }],
                    xaxis: { categories: Object.keys(responseTrend) },
                    colors: ['#0d6efd'],
                }).render();

                new ApexCharts(document.getElementById('chart-equipment-condition'), {
                    chart: { type: 'donut', height: 260 },
                    series: Object.values(equipmentCondition),
                    labels: Object.keys(equipmentCondition),
                }).render();
            }

            if (window.L) {
                var map = L.map('er-map').setView([-2.5, 117], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                var equipmentPins = @json($equipmentPins);
                var safetyDevicePins = @json($safetyDevicePins);
                var incidentPins = @json($incidentPins);
                var bounds = [];

                equipmentPins.forEach(function (item) {
                    var marker = L.marker([item.latitude, item.longitude]).addTo(map).bindPopup('Equipment: ' + item.name + ' (' + item.code + ')');
                    bounds.push([item.latitude, item.longitude]);
                });
                safetyDevicePins.forEach(function (item) {
                    var marker = L.marker([item.latitude, item.longitude]).addTo(map).bindPopup('Safety Device: ' + item.name + ' (' + item.code + ')');
                    bounds.push([item.latitude, item.longitude]);
                });
                incidentPins.forEach(function (item) {
                    var marker = L.circleMarker([item.latitude, item.longitude], { radius: 8, color: '#dc3545' }).addTo(map).bindPopup('Insiden: ' + item.incident_number + ' (' + item.status + ')');
                    bounds.push([item.latitude, item.longitude]);
                });

                if (bounds.length) {
                    map.fitBounds(bounds, { maxZoom: 12 });
                }
            }
        })();
    </script>
@endpush
