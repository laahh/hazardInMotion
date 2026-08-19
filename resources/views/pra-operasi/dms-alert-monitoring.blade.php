@extends('dms.layouts.app')

@section('title', 'Monitoring Alert DMS L1/L2')

@php
    $kpis = $kpis ?? [];
    $summary = $summary ?? ['total' => 0, 'l1_reviewed' => 0, 'l1_confirmed' => 0, 'l1_dismissed' => 0, 'l1_belum' => 0, 'l2_reviewed' => 0, 'l2_confirmed' => 0, 'post_event_eligible' => 0];
    $postEvent = $postEvent ?? ['total' => 0];
    $qaSummary = $qaSummary ?? ['population' => 0, 'target_sample_size' => 0, 'total_sampled' => 0, 'total_audited' => 0, 'pending' => 0, 'benar_dismiss' => 0, 'false_negative' => 0, 'tidak_jelas' => 0, 'false_negative_rate' => null, 'estimated_false_negative_count' => null];
    $slovin = $slovin ?? ['population' => 0, 'margin_of_error' => 0.05, 'target_sample_size' => 0, 'l1_reviewed' => 0, 'l2_reviewed' => 0, 'post_event' => 0];
    $funnel = $funnel ?? [];
    $quadrant = $quadrant ?? [];
    $byUnit = $byUnit ?? [];
    $byOperator = $byOperator ?? [];
    $neverPostEvent = $neverPostEvent ?? ['window_days' => 90, 'total_dengan_alert' => 0, 'total_belum_post_event' => 0, 'persentase' => 0.0];
    $qaPending = $qaPending ?? [];
    $turnaround = $turnaround ?? [];
    $filters = $filters ?? ['start' => '', 'end' => ''];
    $l1Pct = $summary['total'] > 0 ? round($summary['l1_reviewed'] / $summary['total'] * 100, 1) : 0;
    $l2Pct = $summary['total'] > 0 ? round($summary['l2_reviewed'] / $summary['total'] * 100, 1) : 0;
    $confirmRate = $summary['l1_reviewed'] > 0 ? round($summary['l1_confirmed'] / $summary['l1_reviewed'] * 100, 1) : 0;
    $postEventRatio = $summary['total'] > 0 ? round(($postEvent['total'] ?? 0) / $summary['total'] * 100, 1) : 0.0;
    $funnelMax = 1;
    foreach ($funnel as $funnelRow) {
        $funnelMax = max($funnelMax, (int) ($funnelRow['count'] ?? 0));
    }
    $verdictUrl = route('pra-operasi.dms-monitoring.qa-sample.verdict');
    $marginPct = ($slovin['margin_of_error'] ?? 0.05) * 100;
@endphp

@section('css')
<style>
  .dam-warn-banner{background:var(--warning-100,#fff3e0);border:1px solid var(--warning-200,#ffe0b2);color:var(--warning-600,#b45309);
    border-radius:10px;padding:12px 16px;font-size:13px;display:flex;gap:10px;align-items:flex-start;}
  .dam-ok-banner{background:var(--success-100,#e8f8ee);border:1px solid var(--success-200,#bdeccf);color:var(--success-700,#0f7a3d);
    border-radius:10px;padding:12px 16px;font-size:13px;display:flex;gap:10px;align-items:flex-start;}
  .dam-section{margin-top:36px;margin-bottom:16px;}
  .dam-section-title{font-size:16px;font-weight:700;color:var(--text-primary-light,#111827);margin-bottom:2px;}
  .dam-section-sub{font-size:12.5px;color:var(--text-secondary-light);max-width:760px;}
  .dam-section-icon{width:34px;height:34px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;background:var(--primary-50,#eef4ff);color:var(--primary-600,#487FFF);font-size:17px;margin-right:10px;flex-shrink:0;}
  .dam-funnel-row{display:flex;align-items:center;gap:10px;}
  .dam-funnel-bar{height:34px;border-radius:8px;background:var(--primary-600,#487FFF);display:flex;align-items:center;padding:0 12px;color:#fff;font-weight:600;font-size:12.5px;white-space:nowrap;transition:width .3s ease;}
  .dam-funnel-label{width:150px;flex-shrink:0;font-size:12.5px;color:var(--text-secondary-light);}
  .dam-funnel-drop{font-size:11px;color:var(--danger-600,#b91c1c);flex-shrink:0;width:110px;}
  .dam-stat-mini{background:var(--neutral-50,#f8fafc);border-radius:8px;padding:10px 12px;}
  .dam-stat-mini .k{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary-light);font-weight:600;}
  .dam-stat-mini .v{font-size:18px;font-weight:700;margin-top:3px;color:var(--text-primary-light);}
  .dam-qa-row.is-audited{background:rgba(69,179,105,0.06);}
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Monitoring Alert DMS — L1 &amp; L2</h6>
    <div class="text-secondary-light text-sm mt-4">Unit beroperasi, rasio per unit/orang, funnel RFID, Post Event Slovin, dan QA false negatif · {{ $dateLabel }}</div>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('pra-operasi.dashboard') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:clipboard-check-outline" class="icon text-lg"></iconify-icon>
        Pra Operasi
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Monitoring Alert DMS</li>
  </ul>
</div>

@if(session('status'))
<div class="dam-ok-banner mb-24"><iconify-icon icon="solar:check-circle-bold" class="icon text-lg flex-shrink-0"></iconify-icon><div>{{ session('status') }}</div></div>
@endif
@if(session('error'))
<div class="dam-warn-banner mb-24"><iconify-icon icon="solar:danger-circle-bold" class="icon text-lg flex-shrink-0"></iconify-icon><div>{{ session('error') }}</div></div>
@endif

@unless($up)
<div class="dam-warn-banner mb-24">
  <iconify-icon icon="solar:danger-circle-bold" class="icon text-lg flex-shrink-0"></iconify-icon>
  <div>Koneksi ke hse_automation (bcsid.mv_dms_alert) tidak tersedia saat ini. Kartu di bawah menampilkan angka kosong sampai koneksi tersambung.</div>
</div>
@endunless

<form method="GET" class="row g-2 align-items-end mb-24">
  <div class="col-auto">
    <label class="form-label text-sm fw-medium mb-1">Dari Tanggal</label>
    <input type="date" name="start" value="{{ $filters['start'] }}" class="form-control form-control-sm" style="min-width:150px">
  </div>
  <div class="col-auto">
    <label class="form-label text-sm fw-medium mb-1">Sampai Tanggal</label>
    <input type="date" name="end" value="{{ $filters['end'] }}" class="form-control form-control-sm" style="min-width:150px">
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-primary-600 btn-sm radius-8 px-16">
      <iconify-icon icon="solar:filter-bold" class="me-1"></iconify-icon>Terapkan
    </button>
  </div>
</form>

<div class="row gy-4">
  @foreach($kpis as $kpi)
  <div class="col-xxl-4 col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 {{ $kpi['gradient'] }}">
      <div class="card-body p-0">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
          <div class="d-flex align-items-center gap-2">
            <span class="mb-0 w-48-px h-48-px {{ $kpi['bg'] }} flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
              <iconify-icon icon="{{ $kpi['icon'] }}" class="icon"></iconify-icon>
            </span>
            <div>
              <span class="mb-2 fw-medium text-secondary-light text-sm">{{ $kpi['label'] }}</span>
              <h6 class="fw-semibold mb-0">{{ $kpi['value'] }}</h6>
            </div>
          </div>
        </div>
        <p class="text-sm mb-0 text-secondary-light">{{ $kpi['hint'] }}</p>
      </div>
    </div>
  </div>
  @endforeach
</div>

<div class="row g-3 mt-8">
  <div class="col-xxl col-md-4 col-6">
    <div class="dam-stat-mini"><div class="k">Direview L1</div><div class="v">{{ number_format($summary['l1_reviewed']) }} <span class="text-sm fw-medium text-secondary-light">({{ $l1Pct }}%)</span></div></div>
  </div>
  <div class="col-xxl col-md-4 col-6">
    <div class="dam-stat-mini"><div class="k">Direview L2</div><div class="v">{{ number_format($summary['l2_reviewed']) }} <span class="text-sm fw-medium text-secondary-light">({{ $l2Pct }}%)</span></div></div>
  </div>
  <div class="col-xxl col-md-4 col-6">
    <div class="dam-stat-mini"><div class="k">Konfirmasi Nyata L1</div><div class="v text-danger-600">{{ $confirmRate }}%</div></div>
  </div>
  <div class="col-xxl col-md-4 col-6">
    <div class="dam-stat-mini"><div class="k">Belum Direview L1</div><div class="v text-warning-600">{{ number_format($summary['l1_belum']) }}</div></div>
  </div>
  <div class="col-xxl col-md-4 col-6">
    <div class="dam-stat-mini"><div class="k">Post Event / Alert</div><div class="v text-primary-600">{{ $postEventRatio }}%</div></div>
  </div>
  <div class="col-xxl col-md-4 col-6">
    <div class="dam-stat-mini"><div class="k">Eligible Post Event</div><div class="v">{{ number_format($summary['post_event_eligible']) }}</div></div>
  </div>
</div>

<div class="dam-section">
  <div class="d-flex align-items-center">
    <span class="dam-section-icon"><iconify-icon icon="solar:filter-bold"></iconify-icon></span>
    <div>
      <div class="dam-section-title">Funnel Layer — Layer Mana yang Paling Banyak "Bocor"</div>
      <div class="dam-section-sub">Membandingkan populasi orang di tiap tahap: checkin RFID → punya alert DMS → direview L1 → direview L2 → Post Event. Drop-off paling besar menunjukkan layer mana yang paling perlu diperbaiki.</div>
    </div>
  </div>
</div>

<div class="card radius-8 border">
  <div class="card-body">
    <div class="d-flex flex-column gap-2">
      @forelse($funnel as $i => $f)
      @php
        $pct = $funnelMax > 0 ? max(4, (int) round(($f['count'] / $funnelMax) * 100)) : 4;
        $prev = $i > 0 ? (int) ($funnel[$i - 1]['count'] ?? 0) : null;
        $drop = $prev !== null && $prev > 0 ? round((1 - $f['count'] / $prev) * 100, 1) : null;
      @endphp
      <div class="dam-funnel-row">
        <span class="dam-funnel-label">{{ $f['label'] }}</span>
        <div class="flex-grow-1">
          <div class="dam-funnel-bar" style="width:{{ $pct }}%">{{ number_format($f['count']) }}</div>
        </div>
        <span class="dam-funnel-drop">{{ $drop !== null ? '-'.$drop.'%' : '' }}</span>
      </div>
      @empty
      <p class="text-secondary-light text-sm mb-0">Tidak ada data funnel untuk periode ini.</p>
      @endforelse
    </div>
    <p class="text-secondary-light text-xs mt-16 mb-0">Persentase merah = penurunan dari tahap sebelumnya. Kalau penurunan terbesar ada di "Punya Alert DMS", masalahnya di cakupan/kalibrasi perangkat DMS, bukan di kinerja reviewer L1/L2.</p>
  </div>
</div>

<div class="dam-section">
  <div class="d-flex align-items-center">
    <span class="dam-section-icon"><iconify-icon icon="solar:ranking-bold"></iconify-icon></span>
    <div>
      <div class="dam-section-title">Rasio Alert per Unit &amp; per Orang</div>
      <div class="dam-section-sub">20 teratas, diurutkan dari jumlah alert terbanyak — untuk melihat unit/DMS bermasalah atau operator yang perlu perhatian khusus.</div>
    </div>
  </div>
</div>

<div class="row gy-4">
  <div class="col-xxl-6">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <h6 class="text-lg mb-0">Rasio Alert per Unit</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive scroll-sm" style="max-height:420px;overflow-y:auto">
          <table class="table bordered-table mb-0">
            <thead><tr><th>Unit</th><th>Site</th><th>Total</th><th>Terkonfirmasi</th></tr></thead>
            <tbody>
              @forelse($byUnit as $row)
              <tr>
                <td class="fw-medium">{{ $row['unit'] }}</td>
                <td class="text-sm">{{ $row['site'] }}</td>
                <td>{{ number_format($row['total']) }}</td>
                <td><span class="bg-danger-focus text-danger-main px-8 py-2 rounded-pill text-xs fw-medium">{{ number_format($row['confirmed']) }}</span></td>
              </tr>
              @empty
              <tr><td colspan="4" class="text-center text-secondary-light py-5">Tidak ada data.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xxl-6">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <h6 class="text-lg mb-0">Rasio Alert per Orang</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive scroll-sm" style="max-height:420px;overflow-y:auto">
          <table class="table bordered-table mb-0">
            <thead><tr><th>Operator</th><th>Kode SID</th><th>Total</th><th>Terkonfirmasi</th></tr></thead>
            <tbody>
              @forelse($byOperator as $row)
              <tr>
                <td class="fw-medium">{{ $row['nama'] }}</td>
                <td class="text-sm">{{ $row['kode_sid'] }}</td>
                <td>{{ number_format($row['total']) }}</td>
                <td><span class="bg-danger-focus text-danger-main px-8 py-2 rounded-pill text-xs fw-medium">{{ number_format($row['confirmed']) }}</span></td>
              </tr>
              @empty
              <tr><td colspan="4" class="text-center text-secondary-light py-5">Tidak ada data.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="dam-section">
  <div class="d-flex align-items-center">
    <span class="dam-section-icon"><iconify-icon icon="solar:chart-square-bold"></iconify-icon></span>
    <div>
      <div class="dam-section-title">Cakupan Sampling vs Rumus Slovin</div>
      <div class="dam-section-sub">Ukuran sampel minimum (n) dihitung dari populasi total alert (N) pakai rumus Slovin (margin of error {{ $marginPct }}%), dibandingkan dengan jumlah yang benar-benar direview di tiap layer termasuk Post Event.</div>
    </div>
  </div>
</div>

<div class="row gy-4">
  <div class="col-xxl-7">
    <div class="card radius-8 border h-100">
      <div class="card-body">
        <div class="row g-3 mb-16">
          <div class="col-4"><div class="dam-stat-mini"><div class="k">Populasi (N)</div><div class="v">{{ number_format($slovin['population']) }}</div></div></div>
          <div class="col-4"><div class="dam-stat-mini"><div class="k">Target Sampel Slovin (n)</div><div class="v text-primary-600">{{ number_format($slovin['target_sample_size']) }}</div></div></div>
          <div class="col-4"><div class="dam-stat-mini"><div class="k">Post Event Terkirim</div><div class="v">{{ number_format($slovin['post_event']) }}</div></div></div>
        </div>
        <div id="damSlovinChart"></div>
      </div>
    </div>
  </div>
  <div class="col-xxl-5">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <h6 class="text-lg mb-0">Orang yang Belum Pernah Post Event</h6>
        <span class="text-secondary-light text-sm">Window {{ $neverPostEvent['window_days'] }} hari terakhir</span>
      </div>
      <div class="card-body d-flex flex-column justify-content-center">
        <div class="d-flex align-items-center gap-3 mb-16">
          <div class="text-center flex-fill">
            <div class="fs-2 fw-bold text-primary-light">{{ number_format($neverPostEvent['total_dengan_alert']) }}</div>
            <div class="text-secondary-light text-sm">orang punya alert</div>
          </div>
          <iconify-icon icon="solar:arrow-right-bold" class="text-secondary-light text-2xl flex-shrink-0"></iconify-icon>
          <div class="text-center flex-fill">
            <div class="fs-2 fw-bold text-danger-600">{{ number_format($neverPostEvent['total_belum_post_event']) }}</div>
            <div class="text-secondary-light text-sm">belum pernah kena Post Event</div>
          </div>
        </div>
        <div class="progress" style="height:10px;border-radius:999px">
          <div class="progress-bar bg-danger-main" style="width:{{ min(100, $neverPostEvent['persentase']) }}%;border-radius:999px"></div>
        </div>
        <p class="text-secondary-light text-xs mt-8 mb-0">{{ $neverPostEvent['persentase'] }}% dari orang yang punya alert dalam {{ $neverPostEvent['window_days'] }} hari terakhir tidak pernah tercatat Post Event sama sekali.</p>
      </div>
    </div>
  </div>
</div>

<div class="dam-section">
  <div class="d-flex align-items-center">
    <span class="dam-section-icon"><iconify-icon icon="solar:shield-check-bold"></iconify-icon></span>
    <div>
      <div class="dam-section-title">QA Sampling — Kesesuaian Post Event (False Negatif L1)</div>
      <div class="dam-section-sub">Satu-satunya cara tahu false negatif L1 (alert di-dismiss padahal seharusnya Post Event): audit ulang manual atas sampel dismiss L1. Ukuran sampel dari rumus Slovin. Tandai tiap baris setelah dicek ulang videonya.</div>
    </div>
  </div>
</div>

<div class="card radius-8 border">
  <div class="card-header border-bottom bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex flex-wrap gap-3 text-sm">
      <span>Sampel: <b>{{ number_format($qaSummary['total_sampled']) }}</b> / target <b>{{ number_format($qaSummary['target_sample_size']) }}</b></span>
      <span>Sudah diaudit: <b>{{ number_format($qaSummary['total_audited']) }}</b></span>
      <span>Benar dismiss (FP valid): <b class="text-success-600">{{ number_format($qaSummary['benar_dismiss']) }}</b></span>
      <span>False negative: <b class="text-danger-600">{{ number_format($qaSummary['false_negative']) }}</b></span>
      @if($qaSummary['false_negative_rate'] !== null)
      <span>Rate: <b class="text-danger-600">{{ $qaSummary['false_negative_rate'] }}%</b> (estimasi populasi: {{ number_format($qaSummary['estimated_false_negative_count']) }} alert)</span>
      @endif
    </div>
    <form method="POST" action="{{ route('pra-operasi.dms-monitoring.qa-sample.generate') }}">
      @csrf
      <input type="hidden" name="start" value="{{ $filters['start'] }}">
      <input type="hidden" name="end" value="{{ $filters['end'] }}">
      <button type="submit" class="btn btn-sm btn-primary-600 radius-8">
        <iconify-icon icon="solar:magic-stick-3-bold" class="me-1"></iconify-icon>Generate Sampel QA Periode Ini
      </button>
    </form>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive scroll-sm" style="max-height:480px;overflow-y:auto">
      <table class="table bordered-table mb-0" id="damQaTable">
        <thead>
          <tr><th>Waktu Deteksi</th><th>Operator</th><th>Pelanggaran</th><th>Unit</th><th>Site</th><th>Verdict</th></tr>
        </thead>
        <tbody>
          @forelse($qaPending as $sample)
          <tr class="dam-qa-row" data-sample-id="{{ $sample['id'] }}">
            <td class="text-sm">{{ $sample['waktu_deteksi'] ?? '-' }}</td>
            <td class="text-sm">{{ $sample['kode_sid'] ?? '-' }}</td>
            <td class="text-sm">{{ $sample['nama_pelanggaran'] ?? '-' }}</td>
            <td class="text-sm">{{ $sample['unit'] ?? '-' }}</td>
            <td class="text-sm">{{ $sample['site'] ?? '-' }}</td>
            <td>
              <div class="btn-group btn-group-sm dam-qa-verdict-group">
                <button type="button" class="btn btn-outline-success dam-qa-verdict-btn" data-verdict="benar_dismiss">Benar Dismiss</button>
                <button type="button" class="btn btn-outline-danger dam-qa-verdict-btn" data-verdict="false_negative">False Negative</button>
                <button type="button" class="btn btn-outline-secondary dam-qa-verdict-btn" data-verdict="tidak_jelas">Tidak Jelas</button>
              </div>
            </td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-center text-secondary-light py-5">Belum ada sampel pending untuk periode ini — klik "Generate Sampel QA" di atas.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="dam-section">
  <div class="d-flex align-items-center">
    <span class="dam-section-icon"><iconify-icon icon="solar:widget-5-bold"></iconify-icon></span>
    <div>
      <div class="dam-section-title">Kuadran Kategori Pelanggaran</div>
      <div class="dam-section-sub">Sumbu-X = volume alert, sumbu-Y = persentase dikonfirmasi nyata oleh L1. Kanan-atas = volume tinggi &amp; nyata; kanan-bawah = volume tinggi tapi kebanyakan false alarm.</div>
    </div>
  </div>
</div>

<div class="card radius-8 border">
  <div class="card-body">
    <div id="damQuadrantChart"></div>
  </div>
</div>

<div class="dam-section">
  <div class="d-flex align-items-center">
    <span class="dam-section-icon"><iconify-icon icon="solar:headphones-round-sound-bold"></iconify-icon></span>
    <div>
      <div class="dam-section-title">Performa Control Room</div>
      <div class="dam-section-sub">Rata-rata waktu tanggap dari deteksi alert sampai direview, per site.</div>
    </div>
  </div>
</div>

<div class="card radius-8 border">
  <div class="card-body p-0">
    <div class="table-responsive scroll-sm">
      <table class="table bordered-table mb-0">
        <thead><tr><th>Site</th><th>Total Direview L1</th><th>Rata-rata Waktu Tanggap L1</th><th>Rata-rata Waktu Tanggap L2</th></tr></thead>
        <tbody>
          @forelse($turnaround as $row)
          <tr>
            <td class="fw-medium">{{ $row['site'] }}</td>
            <td>{{ number_format($row['total_direview']) }}</td>
            <td class="text-sm">{{ $row['avg_menit_l1'] !== null ? number_format($row['avg_menit_l1'], 1).' menit' : '-' }}</td>
            <td class="text-sm">{{ $row['avg_menit_l2'] !== null ? number_format($row['avg_menit_l2'], 1).' menit' : '-' }}</td>
          </tr>
          @empty
          <tr><td colspan="4" class="text-center text-secondary-light py-5">Tidak ada data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@section('page-scripts')
<script>
(function(){
  if (typeof ApexCharts === 'undefined') return;

  var quadrant = @json($quadrant);
  var quadEl = document.querySelector('#damQuadrantChart');
  if (quadEl && quadrant.length) {
    var totals = quadrant.map(function(q){ return q.total; }).concat([1]);
    var maxTotal = Math.max.apply(null, totals);
    new ApexCharts(quadEl, {
      chart: { type: 'bubble', height: 380, toolbar: { show:false } },
      series: [{ name: 'Kategori', data: quadrant.map(function(q){
        return { x: q.total, y: q.confirmation_rate, z: Math.max(6, Math.round(q.confirmed / (maxTotal||1) * 40)) };
      }) }],
      colors: ['#487FFF'],
      xaxis: { title: { text: 'Volume Alert (Total)' }, labels: { style: { fontSize: '10.5px' } } },
      yaxis: { title: { text: '% Dikonfirmasi Nyata (L1)' }, min: 0, max: 100 },
      grid: { borderColor: '#E5E7EB', strokeDashArray: 4 },
      dataLabels: { enabled: false },
      tooltip: {
        custom: function(opts){
          var d = quadrant[opts.dataPointIndex];
          if (!d) return '';
          return '<div style="padding:10px 13px;font-size:12px;line-height:1.6">' +
            '<div style="font-weight:600;margin-bottom:3px">' + d.nama_pelanggaran + '</div>' +
            '<div>Total: <b>' + d.total.toLocaleString() + '</b></div>' +
            '<div>Terkonfirmasi: <b>' + d.confirmed.toLocaleString() + '</b></div>' +
            '<div>Tingkat konfirmasi: <b>' + d.confirmation_rate + '%</b></div></div>';
        }
      }
    }).render();
  }

  var slovin = @json($slovin);
  var slovinEl = document.querySelector('#damSlovinChart');
  if (slovinEl && slovin) {
    new ApexCharts(slovinEl, {
      chart: { type: 'bar', height: 220, toolbar: { show:false } },
      series: [{ name: 'Jumlah', data: [slovin.target_sample_size, slovin.l1_reviewed, slovin.l2_reviewed, slovin.post_event] }],
      colors: ['#487FFF'],
      plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%' } },
      xaxis: { categories: ['Target Slovin (n)', 'Direview L1', 'Direview L2', 'Post Event'] },
      dataLabels: { enabled: true },
      grid: { borderColor: '#E5E7EB', strokeDashArray: 4 },
      legend: { show: false }
    }).render();
  }
})();
</script>
<script>
(function(){
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
  var verdictUrl = @json($verdictUrl);

  document.querySelectorAll('.dam-qa-verdict-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var row = btn.closest('.dam-qa-row');
      var sampleId = row.getAttribute('data-sample-id');
      var verdict = btn.getAttribute('data-verdict');
      var group = row.querySelectorAll('.dam-qa-verdict-btn');
      group.forEach(function(b){ b.disabled = true; });

      fetch(verdictUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ sample_id: sampleId, verdict: verdict })
      }).then(function(r){ return r.json(); }).then(function(){
        row.classList.add('is-audited');
        row.style.opacity = '0.5';
        row.querySelector('td:last-child').innerHTML = '<span class="text-success-600 text-sm"><iconify-icon icon="solar:check-circle-bold"></iconify-icon> Tersimpan</span>';
      }).catch(function(){
        alert('Gagal menyimpan verdict. Coba lagi.');
        group.forEach(function(b){ b.disabled = false; });
      });
    });
  });
})();
</script>
@endsection
