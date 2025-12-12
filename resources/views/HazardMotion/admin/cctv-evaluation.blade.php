@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Evaluasi Utilisasi Frekuensi Penggunaan CCTV Based on PJA Dedicated - Beraucoal')

@section('css')
<link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.css" rel="stylesheet">
<style>
    .evaluation-header {
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e5e7eb;
    }

    .evaluation-title {
        font-size: 28px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 8px;
    }

    .evaluation-subtitle {
        font-size: 16px;
        color: #6b7280;
    }

    .stats-card {
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        color: white;
        transition: transform 0.2s;
    }

    .stats-card:hover {
        transform: translateY(-4px);
    }

    .stats-card.primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .stats-card.success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .stats-card.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .stats-card.danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    .stats-card.info {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }

    .stats-number {
        font-size: 36px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .stats-label {
        font-size: 14px;
        opacity: 0.95;
        font-weight: 500;
    }

    .chart-container {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .chart-title {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f3f4f6;
    }

    .data-table {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .table-responsive {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table thead {
        background-color: #f9fafb;
    }

    .table th {
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 2px solid #e5e7eb;
    }

    .table td {
        padding: 12px;
        border-bottom: 1px solid #e5e7eb;
        color: #6b7280;
    }

    .table tbody tr:hover {
        background-color: #f9fafb;
    }

    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge-success {
        background-color: #d1fae5;
        color: #065f46;
    }

    .badge-warning {
        background-color: #fef3c7;
        color: #92400e;
    }

    .badge-danger {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .badge-info {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .insight-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .insight-title {
        font-size: 20px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .insight-content {
        color: #6b7280;
        line-height: 1.8;
    }

    .recommendation-item {
        padding: 16px;
        margin-bottom: 12px;
        border-left: 4px solid #3b82f6;
        background-color: #f9fafb;
        border-radius: 8px;
    }

    .recommendation-item.high {
        border-left-color: #ef4444;
    }

    .recommendation-item.medium {
        border-left-color: #f59e0b;
    }

    .recommendation-item.low {
        border-left-color: #10b981;
    }

    .recommendation-title {
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .recommendation-desc {
        color: #6b7280;
        font-size: 14px;
    }

    .red-flag {
        background-color: #fee2e2;
        border-left: 4px solid #ef4444;
        padding: 16px;
        margin-bottom: 12px;
        border-radius: 8px;
    }

    .red-flag-title {
        font-weight: 600;
        color: #991b1b;
        margin-bottom: 8px;
    }

    .red-flag-content {
        color: #7f1d1d;
        font-size: 14px;
    }

    .section-title {
        font-size: 22px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e5e7eb;
    }

    .progress-bar-container {
        background-color: #f3f4f6;
        border-radius: 8px;
        height: 24px;
        overflow: hidden;
        margin-top: 8px;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        font-weight: 600;
        transition: width 0.3s ease;
    }

    .progress-bar.warning {
        background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
    }

    .progress-bar.danger {
        background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
    }
</style>
@endsection

@section('content')
<div class="evaluation-header">
    <h1 class="evaluation-title">EVALUASI UTILISASI FREKUENSI PENGGUNAAN CCTV BASED ON PJA DEDICATED</h1>
    <p class="evaluation-subtitle">Dashboard analisis komprehensif penggunaan CCTV untuk tim PJA</p>
</div>

@if(isset($error))
<div class="alert alert-danger" role="alert">
    <strong>Error:</strong> {{ $error }}
</div>
@else

@php
    $summary = $data['summary'] ?? [];
    $trendline = $data['trendline'] ?? [];
    $currentWeek = $data['currentWeekUtilization'] ?? [];
    $reportingScheme = $data['reportingScheme'] ?? [];
    $totalReports = $data['totalReports'] ?? ['total' => 0, 'breakdown' => []];
    $coverageCctv = $data['coverageCctv'] ?? ['total_cctv' => 0, 'active_cctv' => 0, 'non_reporting' => []];
    $coverageLocation = $data['coverageLocation'] ?? [];
    $locationReportingScheme = $data['locationReportingScheme'] ?? [];
    $operationalArea = $data['operationalAreaUtilization'] ?? [];
    $nonReportingCctv = $data['nonReportingCctv'] ?? [];
    $recommendations = $data['recommendations'] ?? [];
@endphp

<!-- Summary Statistics -->
<div class="row">
    <div class="col-md-3">
        <div class="stats-card primary">
            <div class="stats-number">{{ number_format($summary['total_laporan'] ?? 0) }}</div>
            <div class="stats-label">Total Laporan</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card success">
            <div class="stats-number">{{ number_format($summary['utilisasi_minggu_ini'] ?? 0, 1) }}%</div>
            <div class="stats-label">Utilisasi Minggu Ini</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card info">
            <div class="stats-number">{{ $summary['cctv_aktif'] ?? 0 }}/{{ $summary['cctv_total'] ?? 0 }}</div>
            <div class="stats-label">CCTV Aktif</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card {{ ($summary['cctv_tidak_melapor'] ?? 0) > 0 ? 'danger' : 'success' }}">
            <div class="stats-number">{{ $summary['cctv_tidak_melapor'] ?? 0 }}</div>
            <div class="stats-label">CCTV Tidak Melapor</div>
        </div>
    </div>
</div>

<!-- Trendline Chart -->
<div class="chart-container">
    <h3 class="chart-title">📈 Trendline Penggunaan & Laporan CCTV</h3>
    <canvas id="trendlineChart" height="80"></canvas>
</div>

<!-- Current Week Utilization -->
<div class="row">
    <div class="col-md-6">
        <div class="data-table">
            <h3 class="section-title">📊 Prosentase Utilisasi CCTV Minggu Berjalan</h3>
            <div class="mb-3">
                <div class="d-flex justify-content-between mb-2">
                    <span>Utilisasi</span>
                    <span><strong>{{ number_format($currentWeek['utilisasi_persen'] ?? 0, 2) }}%</strong></span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar {{ ($currentWeek['utilisasi_persen'] ?? 0) < 50 ? 'danger' : (($currentWeek['utilisasi_persen'] ?? 0) < 80 ? 'warning' : '') }}" 
                         style="width: {{ min($currentWeek['utilisasi_persen'] ?? 0, 100) }}%">
                        {{ number_format($currentWeek['utilisasi_persen'] ?? 0, 1) }}%
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <p><strong>Total Laporan Minggu Ini:</strong> {{ number_format($currentWeek['total_laporan_minggu_ini'] ?? 0) }}</p>
                <p><strong>CCTV Aktif:</strong> {{ $currentWeek['cctv_aktif'] ?? 0 }} dari {{ $currentWeek['total_cctv_dedicated'] ?? 0 }} CCTV Dedicated</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="data-table">
            <h3 class="section-title">📋 Skema Pelaporan</h3>
            <canvas id="reportingSchemeChart" height="200"></canvas>
            <div class="mt-3">
                <p><strong>Total Laporan:</strong> {{ number_format($totalReports['total']) }}</p>
                <ul class="list-unstyled mt-2">
                    @foreach($totalReports['breakdown'] as $type => $count)
                    <li>
                        <span class="badge badge-info">{{ $type }}</span>: {{ number_format($count) }} laporan
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Camera Details -->
<div class="data-table">
    <h3 class="section-title">📹 Detail Laporan Per Kamera CCTV</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nomor CCTV</th>
                    <th>Lokasi</th>
                    <th>Minggu</th>
                    <th>Jumlah Laporan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($data['cameraDetails'] ?? [] as $camera)
                <tr>
                    <td><strong>{{ $camera['nomor_cctv'] ?? 'N/A' }}</strong></td>
                    <td>{{ $camera['lokasi'] ?? 'N/A' }}</td>
                    <td>{{ $camera['week_start'] ?? 'N/A' }}</td>
                    <td><span class="badge badge-success">{{ $camera['jumlah_laporan'] ?? 0 }}</span></td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted">Tidak ada data</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Coverage CCTV -->
<div class="row">
    <div class="col-md-6">
        <div class="data-table">
            <h3 class="section-title">🎯 Coverage CCTV</h3>
            <div class="mb-3">
                <p><strong>Total CCTV Dedicated:</strong> {{ $coverageCctv['total_cctv'] ?? 0 }}</p>
                <p><strong>CCTV Aktif (Bulan Ini):</strong> {{ $coverageCctv['active_cctv'] ?? 0 }}</p>
                <p><strong>CCTV Tidak Melapor:</strong> <span class="badge badge-danger">{{ count($coverageCctv['non_reporting'] ?? []) }}</span></p>
            </div>
            @if(!empty($coverageCctv['non_reporting']))
            <div class="mt-3">
                <strong>CCTV yang Tidak Melapor:</strong>
                <ul class="list-unstyled mt-2">
                    @foreach($coverageCctv['non_reporting'] as $cctv)
                    <li class="red-flag">
                        <span class="red-flag-content">{{ $cctv }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>
    <div class="col-md-6">
        <div class="data-table">
            <h3 class="section-title">📍 Coverage Detail Lokasi Tercover</h3>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Lokasi</th>
                            <th>Jumlah CCTV</th>
                            <th>Total Laporan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($coverageLocation as $location)
                        <tr>
                            <td><strong>{{ $location['lokasi'] ?? 'N/A' }}</strong></td>
                            <td><span class="badge badge-info">{{ $location['jumlah_cctv'] ?? 0 }}</span></td>
                            <td>{{ number_format($location['total_laporan'] ?? 0) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">Tidak ada data</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Location Reporting Scheme -->
<div class="data-table">
    <h3 class="section-title">🗺️ Skema Pelaporan Coverage Per Lokasi</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Lokasi</th>
                    <th>Skema Pelaporan</th>
                    <th>Jumlah Laporan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($locationReportingScheme as $location => $schemes)
                @foreach($schemes as $scheme)
                <tr>
                    <td><strong>{{ $location }}</strong></td>
                    <td><span class="badge badge-{{ $scheme['skema_pelaporan'] == 'Real Time' ? 'success' : 'info' }}">{{ $scheme['skema_pelaporan'] }}</span></td>
                    <td>{{ number_format($scheme['jumlah_laporan'] ?? 0) }}</td>
                </tr>
                @endforeach
                @empty
                <tr>
                    <td colspan="3" class="text-center text-muted">Tidak ada data</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Operational Area Utilization -->
<div class="data-table">
    <h3 class="section-title">🏭 Prosentase Utilisasi Per Area Operasional</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Area Operasional</th>
                    <th>CCTV Aktif</th>
                    <th>Total Laporan</th>
                    <th>Prosentase Utilisasi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($operationalArea as $area)
                <tr>
                    <td><strong>{{ $area['area_operasional'] ?? 'N/A' }}</strong></td>
                    <td><span class="badge badge-info">{{ $area['cctv_aktif'] ?? 0 }}</span></td>
                    <td>{{ number_format($area['total_laporan'] ?? 0) }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span>{{ number_format($area['prosentase_utilisasi'] ?? 0, 2) }}%</span>
                            <div class="progress-bar-container" style="flex: 1; max-width: 200px;">
                                <div class="progress-bar {{ ($area['prosentase_utilisasi'] ?? 0) < 10 ? 'danger' : (($area['prosentase_utilisasi'] ?? 0) < 50 ? 'warning' : '') }}" 
                                     style="width: {{ min($area['prosentase_utilisasi'] ?? 0, 100) }}%">
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted">Tidak ada data</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Non-Reporting CCTV -->
@if(!empty($nonReportingCctv))
<div class="data-table">
    <h3 class="section-title">⚠️ Daftar CCTV Dedicated PJA yang TIDAK MELAPOR Sama Sekali</h3>
    <div class="alert alert-danger">
        <strong>Peringatan:</strong> CCTV berikut ini adalah CCTV dedicated PJA yang seharusnya aktif namun tidak melaporkan sama sekali dalam 3 bulan terakhir.
    </div>
    <ul class="list-unstyled">
        @foreach($nonReportingCctv as $cctv)
        <li class="red-flag mb-2">
            <div class="red-flag-title">🚨 {{ $cctv }}</div>
            <div class="red-flag-content">CCTV ini tidak melaporkan data sama sekali dalam periode 3 bulan terakhir</div>
        </li>
        @endforeach
    </ul>
</div>
@endif

<!-- Insights & Recommendations -->
<div class="row">
    <div class="col-md-12">
        <div class="insight-card">
            <h3 class="insight-title">💡 Insight Strategis</h3>
            <div class="insight-content">
                <h5>📊 Ringkasan Kondisi Penggunaan CCTV</h5>
                <p>
                    Berdasarkan analisis data, kondisi penggunaan CCTV saat ini menunjukkan:
                </p>
                <ul>
                    <li><strong>Total Laporan:</strong> {{ number_format($summary['total_laporan'] ?? 0) }} laporan telah dibuat menggunakan CCTV dedicated PJA</li>
                    <li><strong>Utilisasi Minggu Ini:</strong> {{ number_format($summary['utilisasi_minggu_ini'] ?? 0, 1) }}% dari CCTV dedicated yang tersedia</li>
                    <li><strong>CCTV Aktif:</strong> {{ $summary['cctv_aktif'] ?? 0 }} dari {{ $summary['cctv_total'] ?? 0 }} CCTV dedicated ({{ number_format($summary['persentase_aktif'] ?? 0, 1) }}%)</li>
                    <li><strong>Red Flag:</strong> {{ $summary['cctv_tidak_melapor'] ?? 0 }} CCTV tidak melaporkan sama sekali</li>
                </ul>

                <h5 class="mt-4">🔍 Identifikasi Red Flag Utama</h5>
                <ul>
                    @if(!empty($nonReportingCctv))
                    <li><strong>CCTV Tidak Aktif:</strong> Terdapat {{ count($nonReportingCctv) }} CCTV dedicated PJA yang tidak melaporkan sama sekali dalam 3 bulan terakhir</li>
                    @endif
                    @if(($summary['utilisasi_minggu_ini'] ?? 0) < 50)
                    <li><strong>Utilisasi Rendah:</strong> Utilisasi minggu ini hanya {{ number_format($summary['utilisasi_minggu_ini'] ?? 0, 1) }}%, jauh di bawah target ideal 80%</li>
                    @endif
                    @if(($summary['persentase_aktif'] ?? 0) < 70)
                    <li><strong>CCTV Tidak Aktif:</strong> Hanya {{ number_format($summary['persentase_aktif'] ?? 0, 1) }}% CCTV yang aktif, menunjukkan masalah dalam penggunaan atau konektivitas</li>
                    @endif
                </ul>

                <h5 class="mt-4">📈 Insight Strategis</h5>
                <p>
                    Perbedaan utilisasi antara area operasional dapat disebabkan oleh beberapa faktor:
                </p>
                <ul>
                    <li><strong>Faktor Teknis:</strong> Masalah konektivitas, perawatan peralatan, atau konfigurasi sistem</li>
                    <li><strong>Faktor Sumber Daya Manusia:</strong> Kurangnya pelatihan operator, tidak adanya PIC yang jelas, atau kurangnya kesadaran akan pentingnya pelaporan</li>
                    <li><strong>Faktor Prosedur:</strong> Prosedur pelaporan yang tidak jelas atau terlalu kompleks</li>
                    <li><strong>Faktor Manajemen:</strong> Tidak adanya monitoring berkala dan sistem reward/penalty</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Recommendations -->
<div class="insight-card">
    <h3 class="insight-title">🛠️ Rekomendasi Tindakan Konkret</h3>
    <div>
        @foreach($recommendations as $rec)
        <div class="recommendation-item {{ $rec['priority'] ?? 'medium' }}">
            <div class="recommendation-title">
                @if(($rec['priority'] ?? '') == 'high')
                    🔴 Prioritas Tinggi
                @elseif(($rec['priority'] ?? '') == 'medium')
                    🟡 Prioritas Sedang
                @else
                    🟢 Prioritas Rendah
                @endif
                - {{ $rec['title'] ?? 'Rekomendasi' }}
            </div>
            <div class="recommendation-desc">{{ $rec['description'] ?? '' }}</div>
        </div>
        @endforeach
    </div>
</div>

<!-- Additional Visualization Suggestions -->
<div class="insight-card">
    <h3 class="insight-title">💡 Saran Visualisasi Tambahan</h3>
    <div class="insight-content">
        <p>Untuk meningkatkan interpretasi dashboard oleh manajer lapangan, berikut saran visualisasi tambahan:</p>
        <ul>
            <li><strong>Heatmap Lokasi:</strong> Peta panas menunjukkan area dengan utilisasi tinggi vs rendah</li>
            <li><strong>Grafik Perbandingan Perusahaan:</strong> Jika data tersedia, bandingkan utilisasi antara PT Fajar Anugerah Dinamika dan PT Bukit Makmur</li>
            <li><strong>Timeline Detail:</strong> Grafik timeline harian untuk melihat pola penggunaan CCTV</li>
            <li><strong>Dashboard Real-Time:</strong> Monitor live utilisasi CCTV dengan update otomatis setiap jam</li>
            <li><strong>Alert System:</strong> Notifikasi otomatis ketika CCTV tidak melaporkan dalam periode tertentu</li>
            <li><strong>Performance Scorecard:</strong> Skor performa per area operasional dengan indikator warna (hijau/kuning/merah)</li>
        </ul>
    </div>
</div>

@endif
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Trendline Chart
    @if(!empty($trendline))
    const trendlineCtx = document.getElementById('trendlineChart').getContext('2d');
    const trendlineData = @json($trendline);
    
    new Chart(trendlineCtx, {
        type: 'line',
        data: {
            labels: trendlineData.map(item => item.week_start || 'N/A'),
            datasets: [
                {
                    label: 'Utilisasi (%)',
                    data: trendlineData.map(item => parseFloat(item.utilisasi_persen || 0)),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    yAxisID: 'y',
                    tension: 0.4
                },
                {
                    label: 'Jumlah Laporan',
                    data: trendlineData.map(item => parseInt(item.total_laporan || 0)),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    yAxisID: 'y1',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Utilisasi (%)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Jumlah Laporan'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    enabled: true
                }
            }
        }
    });
    @endif

    // Reporting Scheme Chart
    @if(!empty($reportingScheme))
    const reportingSchemeCtx = document.getElementById('reportingSchemeChart').getContext('2d');
    const reportingSchemeData = @json($reportingScheme);
    
    new Chart(reportingSchemeCtx, {
        type: 'doughnut',
        data: {
            labels: reportingSchemeData.map(item => item.skema_pelaporan || 'Unknown'),
            datasets: [{
                data: reportingSchemeData.map(item => parseInt(item.jumlah_laporan || 0)),
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(156, 163, 175, 0.8)'
                ],
                borderColor: [
                    'rgb(16, 185, 129)',
                    'rgb(59, 130, 246)',
                    'rgb(156, 163, 175)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                },
                tooltip: {
                    enabled: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += new Intl.NumberFormat('id-ID').format(context.parsed);
                            label += ' laporan';
                            return label;
                        }
                    }
                }
            }
        }
    });
    @endif
</script>
@endsection

