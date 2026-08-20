<div class="card h-100 radius-8 border-0">
  <div class="card-body p-24">
    <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between mb-16">
      <div>
        <h6 class="mb-2 fw-bold text-lg">{{ $controlRoom['title'] ?? 'Performa Control Room' }}</h6>
        <span class="text-sm fw-medium text-secondary-light">{{ $controlRoom['subtitle'] ?? 'Intervensi alert & lead time real time per perusahaan / site' }}</span>
      </div>
      <span class="form-select form-select-sm w-auto bg-base border text-secondary-light d-inline-block pe-none">{{ $dateLabel }}</span>
    </div>

    <div class="dms-cr-wrap">
      @if(($controlRoomColumns ?? []) === [])
        <p class="text-secondary-light text-sm mb-0 text-center py-40">Belum ada data performa control room untuk periode ini.</p>
      @else
        <table class="dms-cr-table">
          <thead>
            <tr>
              <th rowspan="2" class="dms-cr-metric">Metrik</th>
              @foreach($controlRoom['companies'] ?? [] as $company)
                <th colspan="{{ count($company['columns'] ?? []) }}" class="dms-cr-company">{{ $company['name'] ?? '-' }}</th>
              @endforeach
            </tr>
            <tr>
              @foreach($controlRoomColumns as $column)
                <th class="dms-cr-site">{{ $column['site'] ?? '-' }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @foreach($controlRoomRows as $row)
              <tr>
                <td class="dms-cr-metric">{{ $row['label'] ?? '-' }}</td>
                @foreach($controlRoomColumns as $column)
                  @php
                    $cell = ($row['cells'] ?? [])[$column['key'] ?? ''] ?? [
                      'pct_label'   => '0%',
                      'numerator'   => 0,
                      'denominator' => 0,
                      'tone'        => 'empty',
                    ];
                  @endphp
                  <td class="dms-cr-tone-{{ $cell['tone'] ?? 'empty' }}">
                    <div class="dms-cr-pct">{{ $cell['pct_label'] ?? '0%' }}</div>
                    <div class="dms-cr-frac">({{ number_format((int) ($cell['numerator'] ?? 0)) }} / {{ number_format((int) ($cell['denominator'] ?? 0)) }})</div>
                  </td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  </div>
</div>
