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

@php $insights = $insights ?? ['up' => false]; @endphp
<div class="d-flex align-items-center gap-2 mt-4 mb-16">
  <h6 class="mb-0">Wawasan Fatigue &middot; 14 Hari Terakhir</h6>
  <span class="bg-neutral-100 text-secondary-light text-xs fw-medium px-10 py-2 rounded-pill">berakhir {{ $dateLabel }}</span>
</div>

@unless($insights['up'] ?? false)
<div class="po-warn-banner mb-24">
  <iconify-icon icon="solar:info-circle-bold" class="icon text-lg flex-shrink-0"></iconify-icon>
  <div>Data wawasan (trend, breakdown, ranking) belum tersedia &mdash; koneksi hse_automation sedang tidak aktif.</div>
</div>
@else

<div class="row gy-4 mb-4">
  <div class="col-xxl-6">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <div class="d-flex align-items-center gap-2">
          <span class="bg-primary-50 text-primary-600 w-24-px h-24-px rounded-circle d-flex justify-content-center align-items-center text-xs fw-bold flex-shrink-0">1</span>
          <h6 class="text-lg mb-0">Tren Peringatan Kelelahan Kamera (DMS)</h6>
        </div>
        <span class="text-secondary-light text-sm">Berapa banyak peringatan per hari, dan mana yang sungguhan</span>
      </div>
      <div class="card-body">
        <div id="poAlertTrendChart"></div>
        <p class="text-secondary-light text-xs mt-8 mb-0">
          <b class="text-success-600">Hijau (Dikonfirmasi Nyata)</b> = sudah dicek petugas dan memang benar mengantuk/lelah &middot;
          <b class="text-secondary-light">Abu (Alarm Palsu)</b> = ternyata bukan kelelahan (mis. silau kamera) &middot;
          <b class="text-warning-600">Kuning (Belum Diperiksa)</b> = menunggu dicek petugas &middot;
          garis biru = jumlah operator berbeda yang kena peringatan hari itu.
        </p>
      </div>
    </div>
  </div>

  <div class="col-xxl-6">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <div class="d-flex align-items-center gap-2">
          <span class="bg-primary-50 text-primary-600 w-24-px h-24-px rounded-circle d-flex justify-content-center align-items-center text-xs fw-bold flex-shrink-0">3</span>
          <h6 class="text-lg mb-0">Tren Hasil Fatigue Test (Fit to Work)</h6>
        </div>
        <span class="text-secondary-light text-sm">Proporsi Hijau/Kuning/Merah dari semua yang tes tiap hari</span>
      </div>
      <div class="card-body">
        <div id="poFtwTrendChart"></div>
        <p class="text-secondary-light text-xs mt-8 mb-0">
          Setiap batang = 100% orang yang tes hari itu. Semakin lebar warna <b class="text-warning-600">kuning</b>/<b class="text-danger-600">merah</b>, semakin banyak yang kondisinya perlu perhatian hari itu.
        </p>
      </div>
    </div>
  </div>
</div>

<div class="row gy-4 mb-4">
  <div class="col-xxl-6">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <div class="d-flex align-items-center gap-2">
          <span class="bg-primary-50 text-primary-600 w-24-px h-24-px rounded-circle d-flex justify-content-center align-items-center text-xs fw-bold flex-shrink-0">4</span>
          <h6 class="text-lg mb-0">Penyebab Paling Sering Fatigue Test Bermasalah</h6>
        </div>
        <span class="text-secondary-light text-sm">Dari {{ number_format($insights['deviation']['total'] ?? 0) }} pemeriksaan, 14 hari terakhir</span>
      </div>
      <div class="card-body">
        <div id="poDeviationChart"></div>
      </div>
    </div>
  </div>

  <div class="col-xxl-6">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <div class="d-flex align-items-center gap-2">
          <span class="bg-primary-50 text-primary-600 w-24-px h-24-px rounded-circle d-flex justify-content-center align-items-center text-xs fw-bold flex-shrink-0">5</span>
          <h6 class="text-lg mb-0">Riwayat Penyakit Kritis vs Peringatan Kelelahan</h6>
        </div>
        <span class="text-secondary-light text-sm">Apakah karyawan sakit kritis juga lebih sering kena peringatan?</span>
      </div>
      <div class="card-body d-flex flex-column justify-content-center h-100">
        @php
          $ci = $insights['criticalIllness'] ?? ['total_penyakit_kritis' => 0, 'ada_alert_fatigue' => 0];
          $ciPct = ($ci['total_penyakit_kritis'] ?? 0) > 0 ? round(($ci['ada_alert_fatigue'] / $ci['total_penyakit_kritis']) * 100, 1) : 0;
        @endphp
        <div class="d-flex align-items-center gap-3 mb-16">
          <div class="text-center flex-fill">
            <div class="fs-2 fw-bold text-primary-light">{{ number_format($ci['total_penyakit_kritis'] ?? 0) }}</div>
            <div class="text-secondary-light text-sm">karyawan pernah terkonfirmasi<br>penyakit kritis (18 bln terakhir)</div>
          </div>
          <iconify-icon icon="solar:arrow-right-bold" class="text-secondary-light text-2xl flex-shrink-0"></iconify-icon>
          <div class="text-center flex-fill">
            <div class="fs-2 fw-bold text-danger-600">{{ number_format($ci['ada_alert_fatigue'] ?? 0) }}</div>
            <div class="text-secondary-light text-sm">dari mereka juga kena peringatan<br>kelelahan DMS (14 hari terakhir)</div>
          </div>
        </div>
        <div class="progress" style="height:10px;border-radius:999px">
          <div class="progress-bar bg-danger-main" style="width:{{ min(100,$ciPct) }}%;border-radius:999px"></div>
        </div>
        <p class="text-secondary-light text-xs mt-8 mb-0">{{ $ciPct }}% dari karyawan dengan riwayat penyakit kritis juga terekam peringatan kelelahan &mdash; kandidat prioritas pemantauan.</p>
      </div>
    </div>
  </div>
</div>
@endunless

<div class="row gy-4">
  <div class="col-xxl-8">
    <div class="card h-100 radius-8 border">
      <div class="card-header border-bottom bg-transparent d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <span class="bg-primary-50 text-primary-600 w-24-px h-24-px rounded-circle d-flex justify-content-center align-items-center text-xs fw-bold flex-shrink-0">6</span>
          <h6 class="text-lg mb-0">Watchlist Operator Checkin</h6>
        </div>
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
        <div class="d-flex align-items-center gap-2">
          <span class="bg-primary-50 text-primary-600 w-24-px h-24-px rounded-circle d-flex justify-content-center align-items-center text-xs fw-bold flex-shrink-0">2</span>
          <h6 class="text-lg mb-0">Pencapaian Pengisian Fatigue Test vs Alert DMS</h6>
        </div>
        <span class="text-secondary-light text-sm">Apakah operator yang kena peringatan DMS sudah discreening hari ini?</span>
      </div>
      <div class="card-body">
        <div id="poAggregatorDmsChart"></div>
      </div>
    </div>
  </div>
</div>

@if($insights['up'] ?? false)
<div class="row gy-4 mt-1">
  <div class="col-12">
    <div class="card radius-8 border">
      <div class="card-header border-bottom bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <div class="d-flex align-items-center gap-2">
            <span class="bg-primary-50 text-primary-600 w-24-px h-24-px rounded-circle d-flex justify-content-center align-items-center text-xs fw-bold flex-shrink-0">7</span>
            <span class="bg-primary-50 text-primary-600 w-24-px h-24-px rounded-circle d-flex justify-content-center align-items-center text-xs fw-bold flex-shrink-0">8</span>
            <h6 class="text-lg mb-0">Operator Paling Sering Kena Peringatan &amp; Arah Trennya</h6>
          </div>
          <span class="text-secondary-light text-sm">10 operator dengan peringatan kelelahan terkonfirmasi terbanyak (14 hari) &middot; tren = bandingkan minggu ini vs minggu lalu</span>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive scroll-sm">
          <table class="table bordered-table mb-0">
            <thead>
              <tr>
                <th>Operator</th><th>Unit</th><th>Peringatan Terkonfirmasi (14 hari)</th>
                <th>Status Fit to Work Terkini</th><th>Arah Tren</th>
              </tr>
            </thead>
            <tbody>
              @forelse(($insights['topRepeat'] ?? []) as $op)
              @php
                $opTier = $op['ftw_tier'] ?? null;
                $opTm = $opTier !== null ? $tierMeta[$opTier] : null;
                $trendMeta = [
                  'meningkat' => ['icon' => 'solar:double-alt-arrow-up-bold', 'cls' => 'text-danger-600', 'label' => 'Meningkat'],
                  'menurun' => ['icon' => 'solar:double-alt-arrow-down-bold', 'cls' => 'text-success-600', 'label' => 'Menurun'],
                  'stabil' => ['icon' => 'solar:arrow-right-bold', 'cls' => 'text-secondary-light', 'label' => 'Stabil'],
                ][$op['trend']];
              @endphp
              <tr>
                <td><span class="fw-semibold d-block">{{ $op['nama'] }}</span><span class="text-xs text-secondary-light">{{ $op['kode_sid'] }}</span></td>
                <td class="text-sm">{{ $op['unit'] }}</td>
                <td><span class="bg-danger-focus text-danger-main px-10 py-4 rounded-pill fw-medium text-sm">{{ number_format($op['true_alert_count']) }}x</span></td>
                <td>
                  @if($opTm)
                    <span class="{{ $opTm['badge'] }} px-10 py-4 rounded-pill fw-medium text-sm">{{ $opTm['label'] }}</span>
                    @if($op['ftw_score'] !== null)<span class="text-xs text-secondary-light ms-1">({{ $op['ftw_score'] }}/10)</span>@endif
                  @else
                    <span class="bg-neutral-200 text-neutral-600 px-10 py-4 rounded-pill fw-medium text-sm">Tidak ada data 30 hari</span>
                  @endif
                </td>
                <td>
                  <span class="{{ $trendMeta['cls'] }} d-inline-flex align-items-center gap-1 fw-medium text-sm">
                    <iconify-icon icon="{{ $trendMeta['icon'] }}"></iconify-icon>{{ $trendMeta['label'] }}
                  </span>
                </td>
              </tr>
              @empty
              <tr><td colspan="5" class="text-center text-secondary-light py-5">Belum ada data pada 14 hari terakhir.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <p class="text-secondary-light text-xs px-24 pb-16 mb-0">
          <b>Cara baca kolom Tren:</b> membandingkan jumlah peringatan terkonfirmasi 7 hari terakhir vs 7 hari sebelumnya untuk orang yang sama &mdash;
          bukan prediksi otomatis, murni perbandingan riwayat supaya mudah dipantau apakah kondisinya membaik atau memburuk.
        </p>
      </div>
    </div>
  </div>
</div>
@endif
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
<script>
(function(){
  var insights = @json($insights ?? ['up' => false]);
  if (!insights.up || typeof ApexCharts === 'undefined') return;

  // Panel #1 — Tren Alert Fatigue (bar True/False/Null + line jumlah operator)
  var alertEl = document.querySelector('#poAlertTrendChart');
  if (alertEl && insights.alertTrend && insights.alertTrend.categories.length) {
    new ApexCharts(alertEl, {
      chart: { height: 300, type: 'line', toolbar: { show:false } },
      series: [
        { name: 'Dikonfirmasi Nyata', type: 'column', data: insights.alertTrend.true_count },
        { name: 'Alarm Palsu', type: 'column', data: insights.alertTrend.false_count },
        { name: 'Belum Diperiksa', type: 'column', data: insights.alertTrend.null_count },
        { name: 'Operator Berbeda', type: 'line', data: insights.alertTrend.operator_count }
      ],
      colors: ['#45B369', '#9CA3AF', '#FF9F29', '#487FFF'],
      stroke: { width: [0,0,0,3], curve: 'smooth' },
      plotOptions: { bar: { columnWidth: '70%', borderRadius: 2 } },
      fill: { opacity: [1,1,1,1] },
      markers: { size: [0,0,0,4] },
      xaxis: { categories: insights.alertTrend.categories, labels: { style: { fontSize: '10.5px' } } },
      yaxis: [
        { title: { text: 'Jumlah peringatan' } },
        { opposite: true, title: { text: 'Jumlah operator' }, seriesName: 'Operator Berbeda' }
      ],
      legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
      grid: { borderColor: '#E5E7EB', strokeDashArray: 4 },
      dataLabels: { enabled: false },
      tooltip: { shared: true, intersect: false }
    }).render();
  }

  // Panel #3 — Tren Fit to Work (100% stacked, supaya proporsi kuning/merah kelihatan)
  var ftwEl = document.querySelector('#poFtwTrendChart');
  if (ftwEl && insights.ftwTrend && insights.ftwTrend.categories.length) {
    new ApexCharts(ftwEl, {
      chart: { height: 300, type: 'bar', stacked: true, stackType: '100%', toolbar: { show:false } },
      series: [
        { name: 'Hijau', data: insights.ftwTrend.hijau },
        { name: 'Kuning', data: insights.ftwTrend.kuning },
        { name: 'Merah', data: insights.ftwTrend.merah }
      ],
      colors: ['#45B369', '#FF9F29', '#EF4A00'],
      plotOptions: { bar: { columnWidth: '75%' } },
      xaxis: { categories: insights.ftwTrend.categories, labels: { style: { fontSize: '10.5px' } } },
      yaxis: { labels: { formatter: function(v){ return Math.round(v) + '%'; } } },
      legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
      grid: { borderColor: '#E5E7EB', strokeDashArray: 4 },
      dataLabels: { enabled: false },
      tooltip: {
        shared: true, intersect: false,
        y: { formatter: function(v){ return v + ' orang'; } }
      }
    }).render();
  }

  // Panel #4 — Breakdown Deviasi Fit to Work
  var devEl = document.querySelector('#poDeviationChart');
  if (devEl && insights.deviation) {
    var d = insights.deviation;
    new ApexCharts(devEl, {
      chart: { height: 260, type: 'bar', toolbar: { show:false } },
      series: [{ name: 'Jumlah kasus', data: [d.sobriety_unfit, d.kurang_tidur, d.sakit, d.ada_tindakan_unfit] }],
      colors: ['#EF4A00'],
      plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%', distributed: false } },
      xaxis: { categories: ['Sobriety Test Tidak Fit', 'Jam Tidur < 6 Jam', 'Kondisi Sedang Sakit', 'Ada Tindakan Unfit Diberikan'] },
      dataLabels: { enabled: true, style: { colors: ['#fff'] } },
      grid: { borderColor: '#E5E7EB', strokeDashArray: 4 },
      legend: { show: false },
      tooltip: { y: { formatter: function(v){ return v + ' kasus'; } } }
    }).render();
  }
})();
</script>
@endsection
