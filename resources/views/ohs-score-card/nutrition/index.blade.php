@extends('evaluasi-well.layouts.app')

@section('title', 'Evaluasi Nutrisi')

@section('page-scripts')
<script>
(function () {
    var severityEl = document.getElementById('nutritionAlertSeverity');
    var codeEl = document.getElementById('nutritionAlertCode');
    var table = document.getElementById('nutritionAlertsTable');
    if (!table) {
        return;
    }

    function applyFilter() {
        var severity = severityEl ? severityEl.value : '';
        var code = codeEl ? codeEl.value : '';
        table.querySelectorAll('tbody tr[data-severity]').forEach(function (row) {
            var okSeverity = !severity || row.getAttribute('data-severity') === severity;
            var okCode = !code || row.getAttribute('data-code') === code;
            row.style.display = okSeverity && okCode ? '' : 'none';
        });
    }

    if (severityEl) {
        severityEl.addEventListener('change', applyFilter);
    }
    if (codeEl) {
        codeEl.addEventListener('change', applyFilter);
    }
})();
</script>
<script>
(function () {
    var el = document.querySelector('#nutritionMacroChart');
    if (!el || typeof ApexCharts === 'undefined') {
        return;
    }

    var labels = @json($macroTrendLabels ?? []);
    var calories = @json($macroTrendCalories ?? []);
    var carbs = @json($macroTrendCarbs ?? []);

    if (!labels.length) {
        labels = ['D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7'];
        calories = [0, 0, 0, 0, 0, 0, 0];
        carbs = [0, 0, 0, 0, 0, 0, 0];
    }

    new ApexCharts(el, {
        series: [
            { name: 'Kalori', data: calories },
            { name: 'Karbohidrat (g)', data: carbs }
        ],
        colors: ['#487FFF', '#FF9F29'],
        chart: {
            type: 'bar',
            height: 320,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                columnWidth: '40%',
                endingShape: 'rounded'
            }
        },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 0, colors: ['transparent'] },
        grid: {
            show: true,
            borderColor: '#D1D5DB',
            strokeDashArray: 4,
            position: 'back'
        },
        xaxis: { categories: labels },
        yaxis: {
            labels: {
                formatter: function (value) {
                    if (value >= 1000) {
                        return (value / 1000).toFixed(1) + 'k';
                    }
                    return Math.round(value);
                }
            }
        },
        legend: { position: 'top' },
        tooltip: {
            shared: true,
            intersect: false
        }
    }).render();
})();
</script>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Evaluasi Nutrisi</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Evaluasi Nutrisi</li>
  </ul>
</div>

@unless ($connectionUp ?? false)
<div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8 d-flex align-items-start gap-2" role="alert">
  <iconify-icon icon="solar:danger-triangle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div>Koneksi BeWell tidak tersedia. Data nutrisi ditampilkan kosong hingga tunnel aktif.</div>
</div>
@endunless

<div class="alert alert-info bg-info-100 text-info-600 border-info-100 px-24 py-13 mb-24 radius-8 d-flex align-items-start gap-2" role="alert">
  <iconify-icon icon="solar:info-circle-bold" class="icon text-xl mt-1"></iconify-icon>
  <div>
    Data MCU belum terhubung. Alert klinis gula darah akan menyusul setelah API Node tersedia.
    Alert “Risiko gula (estimasi)” memakai pola karbo tinggi / nama makanan manis — bukan diagnosa MCU.
  </div>
</div>

@include('evaluasi-well.nutrition.partials._kpi', ['kpi' => $kpi ?? []])

<div class="row gy-4 mb-24">
  <div class="col-xxl-8">
    @include('evaluasi-well.nutrition.partials._alerts-table', ['alerts' => $alerts ?? []])
  </div>
  <div class="col-xxl-4">
    <div class="card h-100">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-0">Ranking Risiko</h6>
      </div>
      <div class="card-body p-24">
        <div class="table-responsive scroll-sm">
          <table class="table bordered-table mb-0">
            <thead>
              <tr>
                <th scope="col">Karyawan</th>
                <th scope="col">High</th>
                <th scope="col">Med</th>
              </tr>
            </thead>
            <tbody>
              @forelse (($riskRanking ?? []) as $row)
              <tr>
                <td>
                  <a href="{{ route('evaluasi-well.employees.show', $row['user_id']) }}" class="text-primary-light hover-text-primary">
                    {{ $row['nama'] }}
                  </a>
                  <span class="text-sm d-block text-secondary-light">{{ $row['kode_sid'] }}</span>
                </td>
                <td>{{ $row['high'] }}</td>
                <td>{{ $row['medium'] }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="3" class="text-secondary-light text-sm">Belum ada ranking risiko.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row gy-4">
  <div class="col-xxl-6">
    <div class="card h-100">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-0">Tren Makro Organisasi (7 hari)</h6>
      </div>
      <div class="card-body p-24">
        <div id="nutritionMacroChart"></div>
      </div>
    </div>
  </div>
  <div class="col-xxl-6">
    <div class="card h-100">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-0">Log Makanan Terbaru</h6>
      </div>
      <div class="card-body p-24">
        <div class="table-responsive scroll-sm">
          <table class="table bordered-table mb-0">
            <thead>
              <tr>
                <th scope="col">Makanan</th>
                <th scope="col">Karyawan</th>
                <th scope="col">Waktu</th>
              </tr>
            </thead>
            <tbody>
              @forelse (($recentFoodLogs ?? []) as $log)
              <tr>
                <td>
                  <span class="text-md d-block fw-medium text-primary-light text-truncate" style="max-width: 220px;" title="{{ $log['title'] }}">{{ $log['title'] }}</span>
                  <span class="text-sm text-secondary-light">{{ $log['subtitle'] }}</span>
                </td>
                <td>
                  <a href="{{ route('evaluasi-well.employees.show', $log['user_id']) }}" class="text-primary-light hover-text-primary">
                    {{ $log['user_name'] }}
                  </a>
                </td>
                <td>{{ $log['at'] }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="3" class="text-secondary-light text-sm">Belum ada log makanan.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
