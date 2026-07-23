@php
    $alerts = $alerts ?? [];
@endphp
<div class="card h-100">
  <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h6 class="text-lg fw-semibold mb-0">Alert Nutrisi (7 hari)</h6>
    <div class="d-flex align-items-center gap-2">
      <select id="nutritionAlertSeverity" class="form-select form-select-sm w-auto bg-base border text-secondary-light">
        <option value="">Semua severity</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
      </select>
      <select id="nutritionAlertCode" class="form-select form-select-sm w-auto bg-base border text-secondary-light">
        <option value="">Semua jenis</option>
        <option value="calorie_over">Kalori berlebih</option>
        <option value="carb_over">Karbohidrat tinggi</option>
        <option value="protein_under">Protein kurang</option>
        <option value="score_poor">Skor buruk</option>
        <option value="log_inconsistent">Log tidak konsisten</option>
        <option value="sugar_risk_estimate">Risiko gula (estimasi)</option>
      </select>
    </div>
  </div>
  <div class="card-body p-24">
    <div class="table-responsive scroll-sm" style="max-height: 420px;">
      <table class="table bordered-table mb-0" id="nutritionAlertsTable">
        <thead>
          <tr>
            <th scope="col">Karyawan</th>
            <th scope="col">Alert</th>
            <th scope="col">Severity</th>
            <th scope="col">Bukti</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($alerts as $alert)
          <tr data-severity="{{ $alert['severity'] }}" data-code="{{ $alert['code'] }}">
            <td>
              <a href="{{ route('evaluasi-well.employees.show', $alert['user_id']) }}" class="text-primary-light hover-text-primary fw-medium">
                {{ $alert['nama'] }}
              </a>
              <span class="text-sm d-block fw-normal text-secondary-light">{{ $alert['kode_sid'] }} · {{ $alert['divisi'] }}</span>
            </td>
            <td>{{ $alert['title'] }}</td>
            <td>
              @if ($alert['severity'] === 'high')
                <span class="bg-danger-focus text-danger-main px-16 py-4 rounded-pill fw-medium text-sm">High</span>
              @elseif ($alert['severity'] === 'medium')
                <span class="bg-warning-focus text-warning-main px-16 py-4 rounded-pill fw-medium text-sm">Medium</span>
              @else
                <span class="bg-info-focus text-info-main px-16 py-4 rounded-pill fw-medium text-sm">Low</span>
              @endif
            </td>
            <td class="text-sm text-secondary-light">{{ $alert['evidence'] }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="4" class="text-secondary-light text-sm">Belum ada alert nutrisi pada jendela 7 hari.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
