@extends('layouts.master')

@section('title', 'CCTV Alerts Dashboard')

@section('content')
<x-page-title title="CCTV Alerts Dashboard" pagetitle="Statistik CCTV Alerts" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h5 class="mb-0">Statistik CCTV Alerts</h5>
                    <small class="text-muted">Dashboard untuk melihat statistik jumlah CCTV online dan offline</small>
                </div>
            </div>
            <div class="card-body">
                <!-- Filter Section -->
                <div class="row mb-4">
                    <div class="col-12 col-md-3 mb-3 mb-md-0">
                        <label for="filterType" class="form-label">Tipe Filter</label>
                        <select id="filterType" class="form-select rounded-3">
                            <option value="month">Bulan</option>
                            <option value="week">Minggu</option>
                            <option value="day">Hari</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 mb-3 mb-md-0">
                        <label for="filterValue" class="form-label">Periode</label>
                        <input type="month" id="filterValueMonth" class="form-control rounded-3" style="display: none;">
                        <input type="week" id="filterValueWeek" class="form-control rounded-3" style="display: none;">
                        <input type="date" id="filterValueDay" class="form-control rounded-3" style="display: none;">
                    </div>
                    <div class="col-12 col-md-3 mb-3 mb-md-0">
                        <label for="siteFilter" class="form-label">Site (Opsional)</label>
                        <select id="siteFilter" class="form-select rounded-3">
                            <option value="">Semua Site</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 d-flex align-items-end">
                        <button type="button" id="btnFilter" class="btn btn-primary rounded-3 w-100">
                            <i class="bx bx-filter"></i> Filter
                        </button>
                    </div>
                </div>

                <!-- Chart Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card rounded-4">
                            <div class="card-header">
                                <h5 class="mb-0">Line Chart Statistik</h5>
                            </div>
                            <div class="card-body">
                                <div id="chartdiv" style="width: 100%; height: 500px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DataTable Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="card rounded-4">
                            <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                                <h5 class="mb-0">Data CCTV Alerts</h5>
                                <button type="button" id="btnExportExcel" class="btn btn-success rounded-3">
                                    <i class="material-icons-outlined align-middle" style="font-size:18px;">download</i> Download Excel
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="cctvAlertsTable" class="table table-striped align-middle" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Site</th>
                                                <th>Tanggal</th>
                                                <th>Jumlah Online</th>
                                                <th>Jumlah Offline</th>
                                                <th>Message ID</th>
                                                <th>Created At</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
<script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>

<script>
$(document).ready(function() {
    let chart = null;
    let dataTable = null;
    let currentFilterType = 'month';
    let currentFilterValue = '';
    let currentSite = '';

    // Initialize filter value based on current date
    const today = new Date();
    const currentMonth = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');
    const currentWeek = getWeekString(today);
    const currentDay = today.toISOString().split('T')[0];

    $('#filterValueMonth').val(currentMonth);
    $('#filterValueWeek').val(currentWeek);
    $('#filterValueDay').val(currentDay);

    // Load sites
    loadSites();

    // Show/hide filter inputs based on filter type
    $('#filterType').on('change', function() {
        currentFilterType = $(this).val();
        $('#filterValueMonth, #filterValueWeek, #filterValueDay').hide();
        
        if (currentFilterType === 'month') {
            $('#filterValueMonth').show();
            currentFilterValue = $('#filterValueMonth').val();
        } else if (currentFilterType === 'week') {
            $('#filterValueWeek').show();
            currentFilterValue = $('#filterValueWeek').val();
        } else if (currentFilterType === 'day') {
            $('#filterValueDay').show();
            currentFilterValue = $('#filterValueDay').val();
        }
    });

    // Initialize filter display
    $('#filterType').trigger('change');

    // Filter button click
    $('#btnFilter').on('click', function() {
        currentFilterType = $('#filterType').val();
        currentSite = $('#siteFilter').val();
        
        if (currentFilterType === 'month') {
            currentFilterValue = $('#filterValueMonth').val();
        } else if (currentFilterType === 'week') {
            let weekValue = $('#filterValueWeek').val();
            // Convert HTML5 week format (YYYY-W##) to our format
            if (weekValue) {
                currentFilterValue = weekValue;
            } else {
                currentFilterValue = getWeekString(new Date());
            }
        } else if (currentFilterType === 'day') {
            currentFilterValue = $('#filterValueDay').val();
        }

        loadChart();
        reloadDataTable();
    });

    // Initialize amCharts
    function initChart() {
        // Destroy existing chart if any
        if (window.amChart) {
            window.amChart.dispose();
        }
        if (window.amRoot) {
            window.amRoot.dispose();
        }

        // Create root element
        var root = am5.Root.new("chartdiv");
        root.setThemes([am5themes_Animated.new(root)]);

        // Create chart
        chart = root.container.children.push(
            am5xy.XYChart.new(root, {
                panX: true,
                panY: true,
                wheelX: "panX",
                wheelY: "zoomX",
                layout: root.verticalLayout
            })
        );

        // Add title
        var title = chart.children.unshift(
            am5.Label.new(root, {
                text: "Statistik CCTV Online dan Offline",
                fontSize: 20,
                fontWeight: "500",
                textAlign: "center",
                x: am5.percent(50),
                centerX: am5.percent(50),
                paddingTop: 0,
                paddingBottom: 0
            })
        );

        // Create axes
        var xAxis = chart.xAxes.push(
            am5xy.CategoryAxis.new(root, {
                categoryField: "category",
                renderer: am5xy.AxisRendererX.new(root, {
                    cellStartLocation: 0.1,
                    cellEndLocation: 0.9,
                    minGridDistance: 30
                }),
                tooltip: am5.Tooltip.new(root, {})
            })
        );

        xAxis.data.setAll([]);

        var yAxis = chart.yAxes.push(
            am5xy.ValueAxis.new(root, {
                renderer: am5xy.AxisRendererY.new(root, {})
            })
        );

        // Create series for Online
        var series1 = chart.series.push(
            am5xy.LineSeries.new(root, {
                name: "Jumlah Online",
                xAxis: xAxis,
                yAxis: yAxis,
                valueYField: "online",
                categoryXField: "category",
                stroke: am5.color("#22c55e"),
                strokeWidth: 2,
                tooltip: am5.Tooltip.new(root, {
                    labelText: "{name}: {valueY}"
                })
            })
        );

        series1.data.setAll([]);

        // Add bullets with labels for Online
        series1.bullets.push(function() {
            var bulletCircle = am5.Circle.new(root, {
                radius: 4,
                fill: am5.color("#22c55e"),
                stroke: am5.color("#ffffff"),
                strokeWidth: 2
            });

            var label = am5.Label.new(root, {
                text: "{valueY}",
                fontSize: 12,
                fill: am5.color("#22c55e"),
                centerY: am5.p100,
                centerX: am5.p50,
                y: am5.p100,
                dy: -10
            });

            return am5.Bullet.new(root, {
                sprite: bulletCircle,
                label: label
            });
        });

        // Create series for Offline
        var series2 = chart.series.push(
            am5xy.LineSeries.new(root, {
                name: "Jumlah Offline",
                xAxis: xAxis,
                yAxis: yAxis,
                valueYField: "offline",
                categoryXField: "category",
                stroke: am5.color("#ef4444"),
                strokeWidth: 2,
                tooltip: am5.Tooltip.new(root, {
                    labelText: "{name}: {valueY}"
                })
            })
        );

        series2.data.setAll([]);

        // Add bullets with labels for Offline
        series2.bullets.push(function() {
            var bulletCircle = am5.Circle.new(root, {
                radius: 4,
                fill: am5.color("#ef4444"),
                stroke: am5.color("#ffffff"),
                strokeWidth: 2
            });

            var label = am5.Label.new(root, {
                text: "{valueY}",
                fontSize: 12,
                fill: am5.color("#ef4444"),
                centerY: am5.p100,
                centerX: am5.p50,
                y: am5.p100,
                dy: -10
            });

            return am5.Bullet.new(root, {
                sprite: bulletCircle,
                label: label
            });
        });

        // Add legend
        var legend = chart.children.push(
            am5.Legend.new(root, {
                centerX: am5.p50,
                x: am5.p50
            })
        );
        legend.data.setAll(chart.series.values);

        // Add cursor
        chart.set("cursor", am5xy.XYCursor.new(root, {}));

        // Store chart reference
        window.amChart = chart;
        window.amRoot = root;
    }

    // Load chart data
    function loadChart() {
        $.ajax({
            url: '{{ route("cctv-alerts-dashboard.chart-data") }}',
            method: 'GET',
            data: {
                filter_type: currentFilterType,
                filter_value: currentFilterValue,
                site: currentSite
            },
            success: function(response) {
                if (response.success && window.amChart) {
                    // Prepare data for amCharts
                    var chartData = [];
                    for (var i = 0; i < response.labels.length; i++) {
                        chartData.push({
                            category: response.labels[i],
                            online: response.datasets[0].data[i] || 0,
                            offline: response.datasets[1].data[i] || 0
                        });
                    }

                    // Update chart data
                    var xAxis = window.amChart.xAxes.getIndex(0);
                    xAxis.data.setAll(chartData);

                    var series1 = window.amChart.series.getIndex(0);
                    var series2 = window.amChart.series.getIndex(1);
                    
                    series1.data.setAll(chartData);
                    series2.data.setAll(chartData);
                }
            },
            error: function(xhr) {
                console.error('Error loading chart data:', xhr);
                alert('Gagal memuat data chart');
            }
        });
    }

    // Initialize DataTable
    function initDataTable() {
        dataTable = $('#cctvAlertsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("cctv-alerts-dashboard.data") }}',
                data: function(d) {
                    d.filter_type = currentFilterType;
                    d.filter_value = currentFilterValue;
                    d.site = currentSite;
                }
            },
            columns: [
                { data: 'id', name: 'id' },
                { data: 'site', name: 'site' },
                { data: 'tanggal', name: 'tanggal' },
                { data: 'jumlah_online', name: 'jumlah_online' },
                { data: 'jumlah_offline', name: 'jumlah_offline' },
                { data: 'message_id', name: 'message_id' },
                { data: 'created_at', name: 'created_at' }
            ],
            order: [[2, 'desc']],
            pageLength: 25,
            language: {
                processing: "Memproses...",
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                infoFiltered: "(disaring dari _MAX_ total data)",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                }
            }
        });
    }

    // Reload DataTable
    function reloadDataTable() {
        if (dataTable) {
            dataTable.ajax.reload();
        }
    }

    // Load sites
    function loadSites() {
        $.ajax({
            url: '{{ route("cctv-alerts-dashboard.sites") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const siteSelect = $('#siteFilter');
                    siteSelect.empty();
                    siteSelect.append('<option value="">Semua Site</option>');
                    response.sites.forEach(function(site) {
                        siteSelect.append('<option value="' + site + '">' + site + '</option>');
                    });
                }
            },
            error: function(xhr) {
                console.error('Error loading sites:', xhr);
            }
        });
    }

    // Helper function to get week string
    function getWeekString(date) {
        const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        const dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        const weekNo = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
        return d.getUTCFullYear() + '-W' + String(weekNo).padStart(2, '0');
    }

    // Initialize
    initDataTable();
    
    // Initialize chart after amCharts is ready
    am5.ready(function() {
        initChart();
        // Load chart data after initialization
        setTimeout(function() {
            loadChart();
        }, 500);
    });

    $('#btnExportExcel').on('click', function() {
        const params = new URLSearchParams();
        params.set('filter_type', currentFilterType || 'month');
        if (currentFilterValue) {
            params.set('filter_value', currentFilterValue);
        }
        if (currentSite) {
            params.set('site', currentSite);
        }
        window.location.href = @json(route('cctv-alerts-dashboard.export')) + '?' + params.toString();
    });
});
</script>
@endsection

