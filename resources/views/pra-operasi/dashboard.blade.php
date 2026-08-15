@extends('dms.layouts.app')

@section('title', 'Pra Operasi')

@php
    $tierMeta = [
        'hijau' => ['label' => 'Hijau', 'badge' => 'bg-success-focus text-success-main', 'shape' => 'circle', 'color' => '#45B369'],
        'kuning' => ['label' => 'Kuning', 'badge' => 'bg-warning-focus text-warning-main', 'shape' => 'triangle', 'color' => '#FF9F29'],
        'merah' => ['label' => 'Merah', 'badge' => 'bg-danger-focus text-danger-main', 'shape' => 'diamond', 'color' => '#EF4A00'],
        'belum' => ['label' => 'Belum', 'badge' => 'bg-neutral-200 text-neutral-600', 'shape' => 'square', 'color' => '#9CA3AF'],
    ];
    $pvtMeta = [
        'lulus' => ['label' => 'Lulus', 'badge' => 'bg-success-focus text-success-main'],
        'tidak_lulus' => ['label' => 'Tidak Lulus', 'badge' => 'bg-danger-focus text-danger-main'],
        'belum' => ['label' => 'Belum', 'badge' => 'bg-neutral-200 text-neutral-600'],
    ];
    $fatigueOf = static fn (array $row): string => $row['fatigue_done'] ? ($row['fatigue_tier'] ?? 'hijau') : 'belum';
@endphp

@section('css')
<style>
  .po-mockup-badge {
    display:inline-flex;align-items:center;gap:6px;margin-left:10px;font-size:10.5px;font-weight:600;
    text-transform:uppercase;letter-spacing:.05em;background:var(--success-100,#e8f8ee);color:var(--success-600,#0f7a3d);
    border:1px solid var(--success-200,#bdeccf);padding:4px 10px;border-radius:999px;
  }
  .po-warn-banner{background:var(--warning-100,#fff3e0);border:1px solid var(--warning-200,#ffe0b2);color:var(--warning-600,#b45309);
    border-radius:10px;padding:12px 16px;font-size:13px;display:flex;gap:10px;align-items:flex-start;}
  .po-table tbody tr.is-merah{background:rgba(239,74,0,0.05);}
  .po-table tbody tr.is-belum{background:rgba(156,163,175,0.06);}
  .po-shape{flex:none;display:block;}
  .po-matrix{display:grid;grid-template-columns:96px repeat(3,110px);gap:8px;}
  .po-matrix-axis{font-size:11px;color:var(--text-secondary-light);font-weight:600;display:flex;align-items:center;}
  .po-matrix-col{font-size:11px;color:var(--text-secondary-light);font-weight:600;text-align:center;align-self:end;padding-bottom:4px;}
  .po-matrix-cell{border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;padding:10px 4px;}
  .po-matrix-cell .count{font-size:19px;font-weight:700;}
  .po-matrix-cell .label{font-size:9px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;opacity:.85;}
  .po-cell-hijau{background:var(--success-focus);color:var(--success-600,#166534);}
  .po-cell-kuning{background:var(--warning-focus);color:var(--warning-600,#b45309);}
  .po-cell-merah{background:var(--danger-focus);color:var(--danger-600,#b91c1c);}
  .po-cell-belum{background:#eef0f2;color:#475569;}
  .po-param-group{font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary-light);font-weight:700;margin-top:14px;margin-bottom:6px;}
  .po-param-item{display:flex;align-items:center;gap:8px;font-size:13px;padding:6px 0;border-bottom:1px dashed var(--neutral-200,#e5e7eb);}
  .po-param-item:last-child{border-bottom:none;}
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Pra Operasi<span class="po-mockup-badge"><iconify-icon icon="solar:wifi-router-bold"></iconify-icon>Data Live &middot; hse_automation</span></h6>
    <div class="text-secondary-light text-sm mt-4">Checkin RFID Operator &rarr; Fatigue Test (Fit to Work) &rarr; PVT &middot; {{ $dateLabel }}</div>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        DMS
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Pra Operasi</li>
  </ul>
</div>

@unless($rfidUp)
<div class="po-warn-banner mb-24">
  <iconify-icon icon="solar:danger-circle-bold" class="icon text-lg flex-shrink-0"></iconify-icon>
  <div>
    <b>Koneksi checkin RFID (hse_automation) tidak tersedia saat ini.</b>
    Data di bawah kosong sampai koneksi tersambung kembali &mdash; ini bukan berarti tidak ada operator checkin hari ini.
  </div>
</div>
@endunless

@if($rfidUp && !$pvtUp)
<div class="po-warn-banner mb-24">
  <iconify-icon icon="solar:info-circle-bold" class="icon text-lg flex-shrink-0"></iconify-icon>
  <div><b>Koneksi status PVT (BeWell) tidak tersedia.</b> Kolom PVT akan tampil "Belum" untuk semua baris sampai tunnel BeWell aktif kembali.</div>
</div>
@endif

<form method="GET" class="row g-2 align-items-end mb-24">
  <div class="col-auto">
    <label class="form-label text-sm fw-medium mb-1">Tanggal</label>
    <input type="date" name="date" value="{{ $filters['date'] }}" class="form-control form-control-sm" style="min-width:150px">
  </div>
  <div class="col-auto">
    <label class="form-label text-sm fw-medium mb-1">Perusahaan</label>
    <select name="company" class="form-select form-select-sm" style="min-width:220px">
      <option value="">Semua Perusahaan</option>
      @foreach($companyOptions as $c)
        <option value="{{ $c }}" @selected($filters['company'] === $c)>{{ $c }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-auto">
    <label class="form-label text-sm fw-medium mb-1">Status Fatigue</label>
    <select name="fatigue_status" class="form-select form-select-sm" style="min-width:150px">
      <option value="">Semua</option>
      <option value="belum" @selected($filters['fatigue_status']==='belum')>Belum</option>
      <option value="hijau" @selected($filters['fatigue_status']==='hijau')>Hijau</option>
      <option value="kuning" @selected($filters['fatigue_status']==='kuning')>Kuning</option>
      <option value="merah" @selected($filters['fatigue_status']==='merah')>Merah</option>
    </select>
  </div>
  <div class="col-auto">
    <label class="form-label text-sm fw-medium mb-1">Status PVT</label>
    <select name="pvt_status" class="form-select form-select-sm" style="min-width:150px">
      <option value="">Semua</option>
      <option value="belum" @selected($filters['pvt_status']==='belum')>Belum</option>
      <option value="lulus" @selected($filters['pvt_status']==='lulus')>Lulus</option>
      <option value="tidak_lulus" @selected($filters['pvt_status']==='tidak_lulus')>Tidak Lulus</option>
    </select>
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-primary-600 btn-sm radius-8 px-16">
      <iconify-icon icon="solar:filter-bold" class="me-1"></iconify-icon>Terapkan
    </button>
  </div>
</form>

<div class="row gy-4 mb-4">
  <div class="col-xxl col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="w-48-px h-48-px flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle" style="background:var(--primary-600)">
            <iconify-icon icon="solar:users-group-rounded-bold" class="icon text-xl"></iconify-icon>
          </span>
          <div><span class="mb-2 fw-medium text-secondary-light text-sm d-block">Operator Checkin</span><h6 class="fw-semibold mb-0">{{ number_format($kpi['checkin']) }}</h6></div>
        </div>
        <p class="text-sm mb-0 text-secondary-light">{{ $filters['company'] ?: 'Semua perusahaan' }}</p>
      </div>
    </div>
  </div>
  <div class="col-xxl col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="w-48-px h-48-px flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle" style="background:#9CA3AF">
            <iconify-icon icon="solar:clipboard-list-bold" class="icon text-xl"></iconify-icon>
          </span>
          <div><span class="mb-2 fw-medium text-secondary-light text-sm d-block">Belum Fatigue Test</span><h6 class="fw-semibold mb-0">{{ number_format($kpi['fatigue_belum']) }}</h6></div>
        </div>
        <p class="text-sm mb-0 text-secondary-light">belum mengisi Fit to Work</p>
      </div>
    </div>
  </div>
  <div class="col-xxl col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="w-48-px h-48-px flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle" style="background:var(--danger-main)">
            <iconify-icon icon="solar:danger-triangle-bold" class="icon text-xl"></iconify-icon>
          </span>
          <div><span class="mb-2 fw-medium text-secondary-light text-sm d-block">Fatigue Merah</span><h6 class="fw-semibold mb-0">{{ number_format($kpi['fatigue_merah']) }}</h6></div>
        </div>
        <p class="text-sm mb-0 text-secondary-light">skor kesiapan &le; 4</p>
      </div>
    </div>
  </div>
  <div class="col-xxl col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="w-48-px h-48-px flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle" style="background:#9CA3AF">
            <iconify-icon icon="solar:cursor-bold" class="icon text-xl"></iconify-icon>
          </span>
          <div><span class="mb-2 fw-medium text-secondary-light text-sm d-block">Belum PVT</span><h6 class="fw-semibold mb-0">{{ number_format($kpi['pvt_belum']) }}</h6></div>
        </div>
        <p class="text-sm mb-0 text-secondary-light">{{ $pvtUp ? 'dari operator checkin' : 'status tidak tersedia' }}</p>
      </div>
    </div>
  </div>
  <div class="col-xxl col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="w-48-px h-48-px flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle" style="background:var(--warning-main)">
            <iconify-icon icon="solar:eye-scan-bold" class="icon text-xl"></iconify-icon>
          </span>
          <div><span class="mb-2 fw-medium text-secondary-light text-sm d-block">Ada Alert DMS Fatigue</span><h6 class="fw-semibold mb-0">{{ number_format($kpi['ada_alert_dms']) }}</h6></div>
        </div>
        <p class="text-sm mb-0 text-secondary-light">Menutup Mata/Menguap/Menunduk</p>
      </div>
    </div>
  </div>
  <div class="col-xxl col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="w-48-px h-48-px flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle" style="background:var(--cyan)">
            <iconify-icon icon="solar:map-point-wave-bold" class="icon text-xl"></iconify-icon>
          </span>
          <div><span class="mb-2 fw-medium text-secondary-light text-sm d-block">Masih di Site</span><h6 class="fw-semibold mb-0">{{ number_format($kpi['masih_di_site']) }}</h6></div>
        </div>
        <p class="text-sm mb-0 text-secondary-light">{{ number_format($kpi['sudah_checkout']) }} sudah checkout</p>
      </div>
    </div>
  </div>
</div>

<div class="row gy-4">
  <div class="col-xxl-8">
    <div class="card h-100 radius-8 border">
      <div class="card-header border-bottom bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="text-lg mb-0">Watchlist Operator Checkin</h6>
        <span class="text-secondary-light text-sm">
          {{ number_format($totalRows) }} operator
          @if($truncated) &middot; menampilkan {{ count($rows) }} teratas berdasarkan prioritas risiko @endif
        </span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive scroll-sm" style="max-height:600px;overflow-y:auto">
          <table class="table bordered-table mb-0 po-table">
            <thead>
              <tr>
                <th>Kode SID</th><th>Operator</th><th>Perusahaan</th><th>Checkin</th><th>Checkout</th>
                <th>Fatigue Test</th><th>PVT</th><th>Alert DMS</th>
              </tr>
            </thead>
            <tbody>
              @forelse($rows as $row)
              @php $ft = $fatigueOf($row); $tm = $tierMeta[$ft]; $pm = $pvtMeta[$row['pvt_status']]; @endphp
              <tr class="{{ $ft==='merah' ? 'is-merah' : ($ft==='belum' ? 'is-belum' : '') }}">
                <td class="fw-medium">{{ $row['kode_sid'] }}</td>
                <td>
                  <span class="fw-semibold d-block">{{ $row['nama'] }}</span>
                  <span class="text-xs text-secondary-light">{{ $row['jabatan'] }}</span>
                </td>
                <td class="text-sm">{{ $row['perusahaan'] }}</td>
                <td class="text-sm">{{ \Illuminate\Support\Carbon::parse($row['checked_in_at'])->translatedFormat('d M H:i') }}</td>
                <td class="text-sm">
                  @if(!empty($row['checked_out_at']))
                    {{ \Illuminate\Support\Carbon::parse($row['checked_out_at'])->translatedFormat('d M H:i') }}
                  @else
                    <span class="bg-neutral-200 text-neutral-600 px-8 py-2 rounded-pill text-xs fw-medium">Belum keluar</span>
                  @endif
                </td>
                <td>
                  <span class="{{ $tm['badge'] }} px-10 py-4 rounded-pill fw-medium text-sm">{{ $tm['label'] }}</span>
                  @if($row['fatigue_score'] !== null)<span class="text-xs text-secondary-light ms-1">({{ $row['fatigue_score'] }}/10)</span>@endif
                </td>
                <td><span class="{{ $pm['badge'] }} px-10 py-4 rounded-pill fw-medium text-sm">{{ $pm['label'] }}</span></td>
                <td>
                  @if($row['dms_alert_count'] > 0)
                    <span class="bg-danger-focus text-danger-main px-10 py-4 rounded-pill fw-medium text-sm">{{ $row['dms_alert_count'] }}x</span>
                  @else
                    <span class="text-secondary-light text-sm">-</span>
                  @endif
                </td>
              </tr>
              @empty
              <tr><td colspan="8" class="text-center text-secondary-light py-5">Tidak ada operator checkin untuk filter ini.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4">
    <div class="card h-100 radius-8 border">
      <div class="card-header border-bottom bg-transparent">
        <h6 class="text-lg mb-0">Parameter Pengecekan Pra Operasi</h6>
        <span class="text-secondary-light text-sm">Wajib dijalani sebelum operator mengoperasikan unit</span>
      </div>
      <div class="card-body">
        @php $grouped = collect($checklistParams)->groupBy('group'); @endphp
        @foreach($grouped as $group => $items)
          <div class="po-param-group">{{ $group }}</div>
          @foreach($items as $item)
          <div class="po-param-item">
            <iconify-icon icon="solar:check-circle-outline" class="text-primary-600"></iconify-icon>
            {{ $item['label'] }}
          </div>
          @endforeach
        @endforeach
      </div>
    </div>
  </div>
</div>

<div class="row gy-4 mt-1">
  <div class="col-xxl-6">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <h6 class="text-lg mb-0">Matriks Fatigue Test &times; PVT</h6>
        <span class="text-secondary-light text-sm">Kondisi kelengkapan pengecekan pra operasi</span>
      </div>
      <div class="card-body">
        <div class="po-matrix">
          <div></div><div class="po-matrix-col">Belum PVT</div><div class="po-matrix-col">PVT Tidak Lulus</div><div class="po-matrix-col">PVT Lulus</div>
          @foreach(['belum'=>'Belum','merah'=>'Merah','kuning'=>'Kuning','hijau'=>'Hijau'] as $fKey => $fLabel)
          <div class="po-matrix-axis">{{ $fLabel }}</div>
          @foreach(['belum','tidak_lulus','lulus'] as $pKey)
            @php
              $cell = collect($matrix)->firstWhere(fn($m) => $m['fatigue']===$fKey && $m['pvt']===$pKey);
              $count = $cell['count'] ?? 0;
            @endphp
            <div class="po-matrix-cell po-cell-{{ $fKey }}">
              <span class="count">{{ $count }}</span>
              <span class="label">orang</span>
            </div>
          @endforeach
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-6">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <h6 class="text-lg mb-0">Pencapaian Pengisian Fatigue Test &times; Alert DMS</h6>
        <span class="text-secondary-light text-sm">Apakah operator yang ter-alert DMS sudah discreening?</span>
      </div>
      <div class="card-body">
        <div id="poAggregatorDmsChart"></div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script>
(function(){
  var el = document.querySelector('#poAggregatorDmsChart');
  if (!el || typeof ApexCharts === 'undefined') return;

  var data = @json($aggregatorVsDms);

  new ApexCharts(el, {
    chart: { type:'bar', height:280, stacked:true, toolbar:{show:false} },
    series: [
      { name:'Sudah Isi (Aman)', data:[data.ada_alert.sudah_isi - data.ada_alert.merah, data.tidak_ada_alert.sudah_isi - data.tidak_ada_alert.merah] },
      { name:'Sudah Isi (Merah)', data:[data.ada_alert.merah, data.tidak_ada_alert.merah] },
      { name:'Belum Isi', data:[data.ada_alert.belum_isi, data.tidak_ada_alert.belum_isi] }
    ],
    colors: ['#45B369', '#EF4A00', '#9CA3AF'],
    plotOptions: { bar: { horizontal:true, borderRadius:4, barHeight:'45%' } },
    dataLabels: { enabled:true },
    xaxis: { categories:['Ada Alert DMS Fatigue', 'Tidak Ada Alert DMS'] },
    legend: { position:'top', horizontalAlign:'left', fontSize:'12px' },
    grid: { borderColor:'#E5E7EB', strokeDashArray:4 }
  }).render();
})();
</script>
@endsection
