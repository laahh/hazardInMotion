@extends('dms.layouts.app')

@section('title', 'Evaluasi Harian - Pasca Operasi')

@php
    $kategoriMeta = [
        'baik' => ['label' => 'Baik', 'badge' => 'bg-success-focus text-success-main', 'color' => '#45B369'],
        'perlu_pembinaan' => ['label' => 'Perlu Pembinaan', 'badge' => 'bg-warning-focus text-warning-main', 'color' => '#FF9F29'],
        'kritis' => ['label' => 'Kritis', 'badge' => 'bg-danger-focus text-danger-main', 'color' => '#EF4A00'],
    ];
@endphp

@section('css')
<style>
  .peh-warn-banner{background:var(--warning-100,#fff3e0);border:1px solid var(--warning-200,#ffe0b2);color:var(--warning-600,#b45309);
    border-radius:10px;padding:12px 16px;font-size:13px;display:flex;gap:10px;align-items:flex-start;}
  .peh-row-kritis{background:rgba(239,74,0,0.05);}
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Evaluasi Harian &mdash; Pasca Operasi</h6>
    <div class="text-secondary-light text-sm mt-4">Fase 3: rekap kejadian satu hari kerja per operator &middot; {{ $dateLabel }}</div>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('pra-operasi.dashboard') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:clipboard-check-outline" class="icon text-lg"></iconify-icon>
        Pra Operasi
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Evaluasi Harian</li>
  </ul>
</div>

@unless($tableReady)
<div class="peh-warn-banner mb-24">
  <iconify-icon icon="solar:danger-circle-bold" class="icon text-lg flex-shrink-0"></iconify-icon>
  <div>
    <b>Tabel evaluasi harian belum tersedia.</b>
    Jalankan migration (<code>php artisan migrate</code>) lalu <code>php artisan pra-operasi:evaluate-day {{ $date }}</code> untuk mengisi data tanggal ini.
  </div>
</div>
@else

<form method="GET" class="row g-2 align-items-end mb-24">
  <div class="col-auto">
    <label class="form-label text-sm fw-medium mb-1">Tanggal</label>
    <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" style="min-width:150px">
  </div>
  <div class="col-auto">
    <label class="form-label text-sm fw-medium mb-1">Kategori</label>
    <select name="kategori" class="form-select form-select-sm" style="min-width:180px">
      <option value="">Semua Kategori</option>
      @foreach($kategoriLabels as $key => $label)
        <option value="{{ $key }}" @selected($kategoriFilter === $key)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-primary-600 btn-sm radius-8 px-16">
      <iconify-icon icon="solar:filter-bold" class="me-1"></iconify-icon>Terapkan
    </button>
  </div>
</form>

<div class="row gy-4 mb-4">
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
      <div class="card-body p-0">
        <span class="fw-medium text-secondary-light text-sm d-block mb-4">Total Dievaluasi</span>
        <h6 class="fw-semibold mb-0">{{ number_format($kpi['total']) }}</h6>
      </div>
    </div>
  </div>
  @foreach($kategoriMeta as $key => $meta)
  @php $delta = $kpiDelta[$key] ?? null; @endphp
  <div class="col-xxl-3 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
      <div class="card-body p-0">
        <span class="{{ $meta['badge'] }} px-10 py-2 rounded-pill text-xs fw-medium d-inline-block mb-8">{{ $meta['label'] }}</span>
        <div class="d-flex align-items-center gap-2">
          <h6 class="fw-semibold mb-0">{{ number_format($kpi[$key] ?? 0) }}</h6>
          @if($delta !== null && $delta !== 0)
            <span class="text-xs fw-medium {{ $delta > 0 ? 'text-danger-600' : 'text-success-600' }}">
              <iconify-icon icon="{{ $delta > 0 ? 'solar:arrow-up-bold' : 'solar:arrow-down-bold' }}"></iconify-icon>{{ abs($delta) }} vs kemarin
            </span>
          @elseif($delta === 0)
            <span class="text-xs text-secondary-light">&middot; sama seperti kemarin</span>
          @endif
        </div>
      </div>
    </div>
  </div>
  @endforeach
</div>

<div class="card radius-8 border mb-24">
  <div class="card-header border-bottom bg-transparent">
    <h6 class="text-lg mb-0">Tren Evaluasi Harian (14 Hari)</h6>
    <span class="text-secondary-light text-sm">Jumlah operator per kategori evaluasi, per hari</span>
  </div>
  <div class="card-body">
    <div id="pehTrendChart"></div>
  </div>
</div>

<div class="card radius-8 border">
  <div class="card-header border-bottom bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
      <h6 class="text-lg mb-0">Rincian per Operator</h6>
      <span class="text-secondary-light text-sm">{{ number_format($rows->count()) }} operator</span>
    </div>
    <a href="{{ route('pra-operasi.evaluasi-harian.export', ['date' => $date, 'kategori' => $kategoriFilter]) }}" class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
      <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
      Download CSV
    </a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive scroll-sm" style="max-height:600px;overflow-y:auto">
      <table class="table bordered-table mb-0">
        <thead>
          <tr>
            <th>Operator</th><th>Roster</th><th>Skor FT</th><th>PVT</th>
            <th>Alert (Nyata/Palsu/Belum)</th><th>Durasi Kerja</th><th>Kategori Evaluasi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
          @php $km = $kategoriMeta[$row->kategori_evaluasi] ?? $kategoriMeta['baik']; @endphp
          <tr class="{{ $row->kategori_evaluasi === 'kritis' ? 'peh-row-kritis' : '' }}">
            <td>
              <span class="fw-semibold d-block">{{ $row->nama ?? $row->kode_sid }}</span>
              <span class="text-xs text-secondary-light">{{ $row->kode_sid }} &middot; {{ $row->perusahaan ?? '-' }}</span>
            </td>
            <td class="text-sm">
              {{ $row->hari_ke !== null ? 'Hari ke-'.$row->hari_ke : '-' }}
              @if($row->shift)<span class="d-block text-xs text-secondary-light">Shift {{ $row->shift }}</span>@endif
            </td>
            <td class="text-sm">{{ $row->fatigue_score !== null ? $row->fatigue_score.'/10' : '-' }}</td>
            <td class="text-sm text-capitalize">{{ str_replace('_',' ', $row->pvt_status) }}</td>
            <td class="text-sm">
              <span class="text-danger-600 fw-medium">{{ $row->alert_nyata_count }}</span> /
              <span class="text-secondary-light">{{ $row->alert_palsu_count }}</span> /
              <span class="text-warning-600">{{ $row->alert_belum_count }}</span>
            </td>
            <td class="text-sm">{{ $row->durasi_kerja_menit !== null ? floor($row->durasi_kerja_menit/60).'j '.($row->durasi_kerja_menit%60).'m' : '-' }}</td>
            <td>
              <span class="{{ $km['badge'] }} px-10 py-4 rounded-pill fw-medium text-sm" title="{{ implode('; ', (array) ($row->alasan ?? [])) }}">{{ $km['label'] }}</span>
            </td>
          </tr>
          @empty
          <tr><td colspan="7" class="text-center text-secondary-light py-5">Belum ada evaluasi untuk tanggal/kategori ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endunless
@endsection

@section('page-scripts')
<script>
(function(){
  var el = document.querySelector('#pehTrendChart');
  if (!el || typeof ApexCharts === 'undefined') return;
  @php $trendData = $trend ?? ['categories' => [], 'baik' => [], 'perlu_pembinaan' => [], 'kritis' => []]; @endphp
  var trend = @json($trendData);
  if (!trend.categories.length) {
    el.innerHTML = '<div class="text-secondary-light text-sm text-center py-40">Belum cukup riwayat untuk tren (baru mulai dikumpulkan).</div>';
    return;
  }
  new ApexCharts(el, {
    chart: { height: 280, type: 'bar', stacked: true, toolbar: { show:false } },
    series: [
      { name: 'Baik', data: trend.baik },
      { name: 'Perlu Pembinaan', data: trend.perlu_pembinaan },
      { name: 'Kritis', data: trend.kritis }
    ],
    colors: ['#45B369', '#FF9F29', '#EF4A00'],
    plotOptions: { bar: { columnWidth: '70%', borderRadius: 2 } },
    xaxis: { categories: trend.categories, labels: { style: { fontSize: '10.5px' } } },
    legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
    grid: { borderColor: '#E5E7EB', strokeDashArray: 4 },
    dataLabels: { enabled: false }
  }).render();
})();
</script>
@endsection
