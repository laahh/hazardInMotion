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

    if (! function_exists('shape_svg')) {
        function shape_svg(string $kind, string $color, int $size = 9): string
        {
            $c = $size / 2;
            return match ($kind) {
                'circle' => sprintf('<svg width="%1$d" height="%1$d"><circle cx="%2$s" cy="%2$s" r="%3$s" fill="%4$s"/></svg>', $size, $c, $c - 1, $color),
                'triangle' => sprintf('<svg width="%1$d" height="%1$d"><polygon points="%2$s,1 %3$d,%3$d 1,%3$d" fill="%4$s"/></svg>', $size, $c, $size - 1, $color),
                'diamond' => sprintf('<svg width="%1$d" height="%1$d"><polygon points="%2$s,0.5 %3$s,%2$s %2$s,%3$s 0.5,%2$s" fill="%4$s"/></svg>', $size, $c, $size - 0.5, $color),
                'square' => sprintf('<svg width="%1$d" height="%1$d"><rect x="1" y="1" width="%2$d" height="%2$d" fill="%3$s"/></svg>', $size, $size - 2, $color),
                default => '',
            };
        }
    }
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
  .po-row-clickable{cursor:pointer;transition:background .12s ease;}
  .po-row-clickable:hover{background:rgba(72,127,255,0.06) !important;}
  .po-row-clickable:focus-visible{outline:2px solid var(--primary-600);outline-offset:-2px;}
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
        <p class="text-sm mb-0 text-secondary-light">
          belum mengisi Fit to Work
          @if(($kpi['fatigue_belum_terlambat'] ?? 0) > 0)
            &middot; <span class="text-danger-600 fw-semibold">{{ $kpi['fatigue_belum_terlambat'] }} terlambat</span> (&gt;1 jam sejak checkin)
          @endif
        </p>
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
  <div class="col-xxl col-sm-6">
    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
      <div class="card-body p-0">
        <div class="d-flex align-items-center gap-2 mb-8">
          <span class="w-48-px h-48-px flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle" style="background:#8B5CF6">
            <iconify-icon icon="solar:calendar-mark-bold" class="icon text-xl"></iconify-icon>
          </span>
          <div><span class="mb-2 fw-medium text-secondary-light text-sm d-block">Roster Tinggi</span><h6 class="fw-semibold mb-0">{{ number_format($kpi['roster_tinggi']) }}</h6></div>
        </div>
        <p class="text-sm mb-0 text-secondary-light">hari kerja ke-7 atau lebih tanpa jeda</p>
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
        <p class="text-secondary-light text-xs mt-8 mb-4">
          <b class="text-success-600">Hijau (True Alert)</b> = sudah dicek petugas dan memang benar mengantuk/lelah &middot;
          <b class="text-secondary-light">Abu (False Alert)</b> = ternyata bukan kelelahan (mis. silau kamera) &middot;
          <b class="text-warning-600">Kuning (Belum Diperiksa)</b> = menunggu dicek petugas.
        </p>
        <div id="poAlertOperatorChart" class="mt-8"></div>
        <p class="text-secondary-light text-xs mt-4 mb-0">Jumlah operator berbeda yang kena peringatan hari itu (skalanya beda dari grafik di atas, jadi ditampilkan terpisah).</p>
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
                <th>Level Risiko</th><th>Kode SID</th><th>Operator</th><th>Roster</th><th>Perusahaan</th><th>Checkin</th><th>Checkout</th>
                <th>Fatigue Test</th><th>PVT</th><th>Alert DMS</th>
              </tr>
            </thead>
            <tbody>
              @forelse($rows as $row)
              @php
                $ft = $fatigueOf($row);
                $tm = $tierMeta[$ft];
                $pm = $pvtMeta[$row['pvt_status']];
                $rt = $row['risk_tier'] ?? 'kuning';
                $rm = $tierMeta[$rt];
              @endphp
              <tr class="po-row-clickable {{ $ft==='merah' ? 'is-merah' : ($ft==='belum' ? 'is-belum' : '') }}"
                  data-po-sid="{{ $row['kode_sid'] }}" data-po-date="{{ $filters['date'] }}"
                  data-po-reasons="{{ json_encode($row['risk_reasons'] ?? []) }}" role="button" tabindex="0">
                <td>
                  <span class="{{ $rm['badge'] }} px-10 py-4 rounded-pill fw-semibold text-sm d-inline-flex align-items-center gap-1">{!! shape_svg($rm['shape'], $rm['color']) !!}{{ $rm['label'] }}</span>
                </td>
                <td class="fw-medium">{{ $row['kode_sid'] }}</td>
                <td>
                  <span class="fw-semibold d-block">{{ $row['nama'] }}</span>
                  <span class="text-xs text-secondary-light">{{ $row['jabatan'] }}</span>
                  @if($row['evaluasi_kemarin'])
                    @php $ek = $row['evaluasi_kemarin']; $ekMeta = ['baik'=>['Baik','bg-success-focus text-success-main'],'perlu_pembinaan'=>['Perlu Pembinaan','bg-warning-focus text-warning-main'],'kritis'=>['Kritis','bg-danger-focus text-danger-main']][$ek['kategori']] ?? null; @endphp
                    @if($ekMeta)
                    <span class="{{ $ekMeta[1] }} px-8 py-2 rounded-pill text-xs fw-medium d-inline-block mt-2" title="{{ implode('; ', $ek['alasan']) }}">Kemarin: {{ $ekMeta[0] }}</span>
                    @endif
                  @endif
                </td>
                <td class="text-sm">
                  @if($row['hari_ke'] !== null)
                    <span class="{{ $row['roster_tinggi'] ? 'text-danger-600 fw-semibold' : '' }}">Hari ke-{{ $row['hari_ke'] }}</span>
                  @else
                    <span class="text-secondary-light">-</span>
                  @endif
                  @if($row['shift'])
                    <span class="d-block text-xs text-secondary-light">Shift {{ $row['shift'] }}</span>
                  @endif
                  @if(!empty($row['roster_code']))
                    <span class="d-block text-xs text-secondary-light">Roster {{ $row['roster_code'] }}</span>
                  @endif
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
              <tr><td colspan="10" class="text-center text-secondary-light py-5">Tidak ada operator checkin untuk filter ini.</td></tr>
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

<div class="row gy-4 mt-1">
  <div class="col-12">
    <div class="card radius-8 border">
      <div class="card-header border-bottom bg-transparent">
        <h6 class="text-lg mb-0">Kesiapan Menyeluruh &mdash; Fatigue Test &times; Roster</h6>
        <span class="text-secondary-light text-sm">Apakah Kuning/Merah terkonsentrasi di hari-hari akhir roster? (pola kelelahan akumulatif)</span>
      </div>
      <div class="card-body">
        <div class="d-flex gap-24 flex-wrap">
          @foreach(['hijau'=>'Hijau','kuning'=>'Kuning','merah'=>'Merah'] as $tKey => $tLabel)
          @php $tMeta = $tierMeta[$tKey]; @endphp
          <div style="min-width:180px">
            <div class="d-flex align-items-center gap-2 mb-8">
              <span class="{{ $tMeta['badge'] }} px-8 py-2 rounded-pill fw-semibold text-xs">{{ $tLabel }}</span>
            </div>
            @foreach(['1-3','4-6','7+'] as $grp)
              @php
                $cell = collect($rosterMatrix)->firstWhere(fn($m) => $m['tier']===$tKey && $m['kelompok']===$grp);
                $count = $cell['count'] ?? 0;
              @endphp
              <div class="d-flex align-items-center justify-content-between text-sm py-4 border-bottom">
                <span class="text-secondary-light">Hari ke-{{ $grp }}</span>
                <span class="fw-semibold {{ $grp === '7+' && $count > 0 ? 'text-danger-600' : '' }}">{{ $count }} orang</span>
              </div>
            @endforeach
          </div>
          @endforeach
        </div>
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

<div class="offcanvas offcanvas-end" tabindex="-1" id="poOperatorDrawer" style="width:480px">
  <div class="offcanvas-header border-bottom">
    <div>
      <span class="text-secondary-light text-sm mono" id="poDrawerSid">-</span>
      <h6 class="mb-0 mt-2" id="poDrawerName">-</h6>
      <div class="d-flex flex-wrap gap-2 mt-8">
        <div id="poDrawerRiskBadge"></div>
        <span id="poDrawerRosterBadge" class="bg-neutral-100 text-secondary-light px-10 py-2 rounded-8 text-xs fw-medium"></span>
        <span id="poDrawerKemarinBadge"></span>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
  </div>
  <div class="offcanvas-body">
    <div id="poDrawerLoading" class="text-center text-secondary-light py-40">
      <div class="spinner-border spinner-border-sm text-primary-600 mb-8" role="status"></div>
      <div class="text-sm">Memuat profil operator...</div>
    </div>
    <div id="poDrawerContent" class="d-none">

      <div class="mb-24">
        <h6 class="text-sm fw-semibold text-secondary-light text-uppercase mb-8">Kenapa Level Risiko Ini?</h6>
        <ul id="poDrawerReasons" class="ps-16 mb-0 text-sm"></ul>
      </div>

      <div id="poDrawerIllnessBanner" class="d-none mb-24"></div>

      <div class="mb-24">
        <h6 class="text-sm fw-semibold text-secondary-light text-uppercase mb-8">Tren Fatigue Test Personal (30 Hari)</h6>
        <div id="poDrawerTrendChart"></div>
        <p id="poDrawerBaselineNote" class="text-xs text-secondary-light mt-8 mb-0"></p>
      </div>

      <div class="mb-24">
        <div class="d-flex align-items-center justify-content-between mb-8">
          <h6 class="text-sm fw-semibold text-secondary-light text-uppercase mb-0">Riwayat Evaluasi Harian (90 Hari)</h6>
        </div>
        <div id="poDrawerEvalHeatmap" class="d-flex flex-wrap gap-2"></div>
        <p class="text-xs text-secondary-light mt-8 mb-0">Tiap kotak = 1 hari kerja &middot; hijau=Baik, kuning=Perlu Pembinaan, merah=Kritis, abu=tidak checkin</p>
      </div>

      <div class="mb-24">
        <h6 class="text-sm fw-semibold text-secondary-light text-uppercase mb-8">Riwayat PVT (30 Hari)</h6>
        <div id="poDrawerPvtList" class="d-flex flex-column gap-2" style="max-height:200px;overflow-y:auto"></div>
      </div>

      <div>
        <div class="d-flex align-items-center justify-content-between mb-8">
          <h6 class="text-sm fw-semibold text-secondary-light text-uppercase mb-0">Riwayat Alert DMS (30 Hari)</h6>
          <span id="poDrawerAlertSummary" class="text-xs text-secondary-light"></span>
        </div>
        <div id="poDrawerAlertList" class="d-flex flex-column gap-2" style="max-height:320px;overflow-y:auto"></div>
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
<script>
(function(){
  var insights = @json($insights ?? ['up' => false]);
  if (!insights.up || typeof ApexCharts === 'undefined') return;

  // Panel #1 — Tren Alert Fatigue (bar True/False/Null + line jumlah operator)
  var alertEl = document.querySelector('#poAlertTrendChart');
  if (alertEl && insights.alertTrend && insights.alertTrend.categories.length) {
    new ApexCharts(alertEl, {
      chart: { height: 260, type: 'bar', stacked: true, toolbar: { show:false } },
      series: [
        { name: 'True Alert', data: insights.alertTrend.true_count },
        { name: 'False Alert', data: insights.alertTrend.false_count },
        { name: 'Belum Diperiksa', data: insights.alertTrend.null_count }
      ],
      colors: ['#45B369', '#9CA3AF', '#FF9F29'],
      plotOptions: { bar: { columnWidth: '70%', borderRadius: 2 } },
      xaxis: { categories: insights.alertTrend.categories, labels: { style: { fontSize: '10.5px' } } },
      yaxis: { title: { text: 'Jumlah peringatan' } },
      legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px' },
      grid: { borderColor: '#E5E7EB', strokeDashArray: 4 },
      dataLabels: { enabled: false },
      tooltip: { shared: true, intersect: false }
    }).render();
  }

  var alertOpEl = document.querySelector('#poAlertOperatorChart');
  if (alertOpEl && insights.alertTrend && insights.alertTrend.categories.length) {
    new ApexCharts(alertOpEl, {
      chart: { height: 120, type: 'area', toolbar: { show:false }, sparkline: { enabled: false } },
      series: [{ name: 'Operator Berbeda', data: insights.alertTrend.operator_count }],
      colors: ['#487FFF'],
      stroke: { width: 2.5, curve: 'smooth' },
      fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.02, stops: [0,90,100] } },
      xaxis: { categories: insights.alertTrend.categories, labels: { style: { fontSize: '10px' } } },
      yaxis: { labels: { style: { fontSize: '10px' } } },
      grid: { borderColor: '#E5E7EB', strokeDashArray: 4 },
      dataLabels: { enabled: false },
      markers: { size: 0, hover: { size: 4 } },
      tooltip: { y: { formatter: function(v){ return v + ' operator'; } } }
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
<script>
(function(){
  var drawerEl = document.getElementById('poOperatorDrawer');
  if (!drawerEl || typeof bootstrap === 'undefined') return;

  var drawer = new bootstrap.Offcanvas(drawerEl);
  var profileUrlBase = @json(url('/pra-operasi/operator'));
  var tierMeta = {
    hijau: { label: 'Hijau', badge: 'bg-success-focus text-success-main' },
    kuning: { label: 'Kuning', badge: 'bg-warning-focus text-warning-main' },
    merah: { label: 'Merah', badge: 'bg-danger-focus text-danger-main' }
  };
  var alertStatusMeta = {
    nyata: { label: 'True Alert', cls: 'bg-success-focus text-success-main' },
    palsu: { label: 'False Alert', cls: 'bg-neutral-200 text-neutral-600' },
    belum: { label: 'Belum Diperiksa', cls: 'bg-warning-focus text-warning-main' }
  };
  var currentChart = null;

  function escapeHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function openDrawer(sid, nama, riskTier, date) {
    document.getElementById('poDrawerSid').textContent = sid;
    document.getElementById('poDrawerName').textContent = nama;
    var rt = tierMeta[riskTier] || tierMeta.kuning;
    document.getElementById('poDrawerRiskBadge').innerHTML =
      '<span class="' + rt.badge + ' px-12 py-4 rounded-pill fw-semibold text-sm">Level Risiko: ' + rt.label + '</span>';

    document.getElementById('poDrawerLoading').classList.remove('d-none');
    document.getElementById('poDrawerContent').classList.add('d-none');
    drawer.show();

    fetch(profileUrlBase + '/' + encodeURIComponent(sid) + '?date=' + encodeURIComponent(date))
      .then(function(r){ return r.json(); })
      .then(renderProfile)
      .catch(function(){
        document.getElementById('poDrawerLoading').innerHTML = '<div class="text-danger-600 text-sm">Gagal memuat profil operator.</div>';
      });
  }

  var evalKategoriMeta = {
    baik: { label: 'Baik', color: '#45B369' },
    perlu_pembinaan: { label: 'Perlu Pembinaan', color: '#FF9F29' },
    kritis: { label: 'Kritis', color: '#EF4A00' }
  };

  function renderProfile(profile) {
    document.getElementById('poDrawerLoading').classList.add('d-none');
    document.getElementById('poDrawerContent').classList.remove('d-none');

    // Alasan skor risiko diisi dari luar (data-po-reasons) karena tidak dikirim ulang oleh endpoint profil.
    var reasonsEl = document.getElementById('poDrawerReasons');
    if (reasonsEl.dataset.filled !== '1') {
      // fallback kosong; diisi oleh caller lewat window.__poCurrentReasons
    }
    if (window.__poCurrentReasons) {
      reasonsEl.innerHTML = window.__poCurrentReasons.map(function(r){ return '<li>' + escapeHtml(r) + '</li>'; }).join('');
    }

    // Roster (hari ke-N + shift)
    var rosterEl = document.getElementById('poDrawerRosterBadge');
    var roster = profile.roster || {};
    if (roster.hari_ke !== null && roster.hari_ke !== undefined) {
      rosterEl.textContent = 'Hari ke-' + roster.hari_ke +
        (roster.shift ? ' · Shift ' + roster.shift : '') +
        (roster.roster_code ? ' · Roster ' + roster.roster_code : '');
    } else {
      rosterEl.textContent = '';
    }

    // Evaluasi kemarin
    var kemarinEl = document.getElementById('poDrawerKemarinBadge');
    if (profile.evaluasiKemarin) {
      var ekMeta = evalKategoriMeta[profile.evaluasiKemarin.kategori];
      if (ekMeta) {
        kemarinEl.innerHTML = '<span class="px-10 py-2 rounded-8 text-xs fw-medium" style="background:' + ekMeta.color + '22;color:' + ekMeta.color + '" title="' +
          escapeHtml((profile.evaluasiKemarin.alasan || []).join('; ')) + '">Kemarin: ' + ekMeta.label + '</span>';
      } else {
        kemarinEl.innerHTML = '';
      }
    } else {
      kemarinEl.innerHTML = '';
    }

    // Heatmap riwayat evaluasi harian (90 hari)
    var heatmapEl = document.getElementById('poDrawerEvalHeatmap');
    var evalHistory = profile.evaluasiHistory || [];
    if (!evalHistory.length) {
      heatmapEl.innerHTML = '<div class="text-secondary-light text-sm">Belum ada riwayat evaluasi harian.</div>';
    } else {
      var byDate = {};
      evalHistory.forEach(function(h){ byDate[h.date] = h.kategori; });
      var cells = [];
      var cursor = new Date(evalHistory[0].date + 'T00:00:00');
      var end = new Date(evalHistory[evalHistory.length - 1].date + 'T00:00:00');
      while (cursor <= end) {
        var key = cursor.toISOString().slice(0, 10);
        var kategori = byDate[key];
        var meta = evalKategoriMeta[kategori];
        var color = meta ? meta.color : '#E5E7EB';
        var title = meta ? (key + ': ' + meta.label) : (key + ': tidak checkin');
        cells.push('<span title="' + escapeHtml(title) + '" style="width:14px;height:14px;border-radius:3px;background:' + color + ';display:inline-block"></span>');
        cursor.setDate(cursor.getDate() + 1);
      }
      heatmapEl.innerHTML = cells.join('');
    }

    // Banner penyakit kritis
    var illnessEl = document.getElementById('poDrawerIllnessBanner');
    if (profile.criticalIllness && profile.criticalIllness.has_critical_illness) {
      var followed = profile.criticalIllness.followed_up;
      illnessEl.classList.remove('d-none');
      illnessEl.innerHTML =
        '<div class="' + (followed ? 'bg-success-100 text-success-600 border-success-100' : 'bg-danger-100 text-danger-600 border-danger-100') + ' border px-16 py-13 rounded-8 d-flex gap-2 align-items-start text-sm">' +
          '<iconify-icon icon="solar:heart-pulse-bold" class="icon text-lg flex-shrink-0 mt-2"></iconify-icon>' +
          '<span>Riwayat penyakit kritis terkonfirmasi <b>' + escapeHtml(profile.criticalIllness.confirmed_date || '-') + '</b>. ' +
          (followed ? 'Sudah ada Fatigue Test follow-up sejak tanggal itu.' : '<b>Belum ada Fatigue Test follow-up</b> sejak tanggal itu.') +
          '</span></div>';
    } else {
      illnessEl.classList.add('d-none');
      illnessEl.innerHTML = '';
    }

    // Grafik tren Fatigue Test personal + pita baseline
    var history = profile.fatigueHistory || [];
    var baseline = profile.baseline;
    var chartEl = document.getElementById('poDrawerTrendChart');
    var noteEl = document.getElementById('poDrawerBaselineNote');
    if (currentChart) { currentChart.destroy(); currentChart = null; }

    if (history.length === 0) {
      chartEl.innerHTML = '<div class="text-secondary-light text-sm text-center py-24">Belum ada riwayat Fatigue Test pada 30 hari terakhir.</div>';
      noteEl.textContent = '';
    } else if (typeof ApexCharts !== 'undefined') {
      var categories = history.map(function(h){ return h.date.slice(5); });
      var scores = history.map(function(h){ return h.score; });
      var annotations = { yaxis: [] };
      if (baseline) {
        annotations.yaxis.push({
          y: baseline.mean, borderColor: '#9CA3AF', strokeDashArray: 4,
          label: { text: 'Baseline ' + baseline.mean, style: { background: '#9CA3AF', color: '#fff', fontSize: '10px' } }
        });
        noteEl.textContent = 'Baseline dihitung dari ' + baseline.n + ' tes sebelumnya: rata-rata ' + baseline.mean + ', deviasi ' + baseline.std + '.';
      } else {
        noteEl.textContent = 'Riwayat belum cukup (minimal 5 tes) untuk menghitung baseline personal.';
      }

      currentChart = new ApexCharts(chartEl, {
        chart: { height: 200, type: 'line', toolbar: { show:false } },
        series: [{ name: 'Skor Kesiapan', data: scores }],
        colors: ['#487FFF'],
        stroke: { curve: 'smooth', width: 2.5 },
        markers: { size: 3 },
        xaxis: { categories: categories, labels: { style: { fontSize: '10px' } } },
        yaxis: { min: 0, max: 10 },
        annotations: annotations,
        grid: { borderColor: '#E5E7EB', strokeDashArray: 4 },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: function(v){ return v + '/10'; } } }
      });
      currentChart.render();
    }

    // Riwayat PVT
    var pvtListEl = document.getElementById('poDrawerPvtList');
    var pvtStatusMeta = {
      lulus: { label: 'Lulus', cls: 'bg-success-focus text-success-main' },
      tidak_lulus: { label: 'Tidak Lulus', cls: 'bg-danger-focus text-danger-main' },
      belum: { label: 'Belum Tes', cls: 'bg-neutral-200 text-neutral-600' }
    };
    if (!profile.pvtHistory || profile.pvtHistory.length === 0) {
      pvtListEl.innerHTML = '<div class="text-secondary-light text-sm text-center py-16">Tidak ada riwayat PVT pada 30 hari terakhir.</div>';
    } else {
      pvtListEl.innerHTML = profile.pvtHistory.map(function(p){
        var meta = pvtStatusMeta[p.status] || pvtStatusMeta.belum;
        return '<div class="d-flex align-items-center justify-content-between border rounded-8 px-12 py-8">' +
          '<div><div class="text-sm fw-medium">' + escapeHtml(p.tested_at) + '</div>' +
          (p.mean_rt_ms !== null ? '<div class="text-xs text-secondary-light">Mean RT ' + p.mean_rt_ms + 'ms &middot; Lapses ' + (p.lapses ?? '-') + '</div>' : '') + '</div>' +
          '<span class="' + meta.cls + ' px-10 py-4 rounded-pill fw-medium text-xs">' + meta.label + '</span>' +
          '</div>';
      }).join('');
    }

    // Riwayat alert
    var listEl = document.getElementById('poDrawerAlertList');
    var summaryEl = document.getElementById('poDrawerAlertSummary');
    var s = profile.alertSummary || { nyata:0, palsu:0, belum:0, total:0 };
    summaryEl.textContent = s.total + ' alert · ' + s.nyata + ' true alert · ' + s.belum + ' belum diperiksa';

    if (!profile.alertTimeline || profile.alertTimeline.length === 0) {
      listEl.innerHTML = '<div class="text-secondary-light text-sm text-center py-16">Tidak ada alert fatigue pada 30 hari terakhir.</div>';
    } else {
      listEl.innerHTML = profile.alertTimeline.map(function(a){
        var meta = alertStatusMeta[a.status] || alertStatusMeta.belum;
        return '<div class="d-flex align-items-center justify-content-between border rounded-8 px-12 py-8">' +
          '<div><div class="text-sm fw-medium">' + escapeHtml(a.name) + '</div><div class="text-xs text-secondary-light">' + escapeHtml(a.date) + '</div></div>' +
          '<span class="' + meta.cls + ' px-10 py-4 rounded-pill fw-medium text-xs">' + meta.label + '</span>' +
          '</div>';
      }).join('');
    }
  }

  document.querySelectorAll('.po-row-clickable').forEach(function(tr){
    tr.addEventListener('click', function(){
      var sid = tr.getAttribute('data-po-sid');
      var date = tr.getAttribute('data-po-date');
      var nama = tr.querySelector('.fw-semibold').textContent;
      var riskBadge = tr.querySelector('td:first-child span');
      var riskTier = 'kuning';
      if (riskBadge) {
        var txt = riskBadge.textContent.trim().toLowerCase();
        if (txt.indexOf('merah') !== -1) riskTier = 'merah';
        else if (txt.indexOf('hijau') !== -1) riskTier = 'hijau';
      }
      window.__poCurrentReasons = null;
      var reasonsAttr = tr.getAttribute('data-po-reasons');
      if (reasonsAttr) {
        try { window.__poCurrentReasons = JSON.parse(reasonsAttr); } catch (e) { window.__poCurrentReasons = null; }
      }
      document.getElementById('poDrawerReasons').innerHTML = window.__poCurrentReasons
        ? window.__poCurrentReasons.map(function(r){ return '<li>' + escapeHtml(r) + '</li>'; }).join('')
        : '';
      openDrawer(sid, nama, riskTier, date);
    });
    tr.addEventListener('keydown', function(e){
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); tr.click(); }
    });
  });
})();
</script>
@endsection
