@extends('dms.layouts.app')

@section('title', 'Kepatuhan Roster Karyawan (Live)')

@section('css')
<link rel="stylesheet" href="{{ asset('dms-assets/roster-compliance/roster-compliance.css') }}">
<link rel="stylesheet" href="{{ asset('dms-assets/roster-compliance/roster-compliance-live.css') }}">
@endsection

@php
    /** Tautan yang mempertahankan seluruh filter aktif. */
    $withQuery = fn (array $ubah) => request()->fullUrlWithQuery($ubah + ['page' => 1]);
    $sortUrl = function (string $key) use ($filters, $withQuery) {
        $dir = ($filters['sort'] ?? '') === $key && ($filters['dir'] ?? 'asc') === 'asc' ? 'desc' : 'asc';

        return $withQuery(['sort' => $key, 'dir' => $dir]);
    };
    $sortArrow = fn (string $key) => ($filters['sort'] ?? '') !== $key
        ? ''
        : (($filters['dir'] ?? 'asc') === 'asc' ? '▲' : '▼');

    $ambangReg1 = ($ambang['kerja_beruntun'] ?? 13) + 1;
    $ambangReg2 = $ambang['onsite'] ?? 71;
    $ambangReg3 = $ambang['cuti_min'] ?? 12;
    $ambangWajib = $ambang['wajib_cuti'] ?? 71;
    $ambangCuti = $ambang['off_ke_cuti'] ?? 5;

    $total = $agregat['total'] ?? 0;
    $pct = fn (int $x): string => $total > 0 ? number_format($x / $total * 100, 1, ',', '.').'%' : '0%';

    $statusBadge = [
        'Shift Pagi' => 'bg-primary-100 text-primary-600',
        'Shift Malam' => 'text-white',
        'Overshift' => 'bg-warning-100 text-warning-600',
        'Off' => 'bg-neutral-200 text-neutral-600',
        'Cuti' => 'bg-success-100 text-success-600',
    ];
@endphp

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Kepatuhan Roster Karyawan <span class="text-success-600">&middot; Live</span></h6>
    <div class="text-secondary-light text-sm mt-4">
      @if ($up)
        Data s/d <b>{{ \Carbon\Carbon::parse($meta['hari_terakhir'])->translatedFormat('d M Y') }}</b>
        @if ($meta['disinkron'] !== '')
          &middot; sinkron terakhir {{ \Carbon\Carbon::parse($meta['disinkron'])->translatedFormat('d M Y H:i') }}
        @endif
        &middot; {{ number_format($meta['jumlah'], 0, ',', '.') }} karyawan dalam populasi
      @else
        Belum ada data roster tersinkron.
      @endif
    </div>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        DMS
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Kepatuhan Roster (Live)</li>
  </ul>
</div>

<div class="alert alert-info bg-info-100 text-info-600 border-info-100 px-24 py-13 mb-24 radius-8 d-flex gap-2 align-items-start" role="alert">
  <iconify-icon icon="solar:info-circle-bold" class="icon text-lg flex-shrink-0 mt-2"></iconify-icon>
  <div class="text-sm">
    Pola roster dirakit dari <b>scan gate RFID PASSED</b> (<code>bcsid.mv_checkinout_rfid</code>): check-in pertama
    &lt;12:00 = Shift Pagi, &ge;12:00 = Shift Malam, tanpa check-in = Off, dan Off lebih dari {{ $ambangCuti }} hari
    berturut-turut dianggap fase Cuti.
    <b>Asumsi:</b> karyawan tap gate saat awal &amp; akhir cuti (mess di area site), sehingga hari tanpa scan
    dihitung off/cuti &mdash; scan yang terlewat bisa memunculkan flag palsu. Verifikasi flag merah sebelum tindakan.
  </div>
</div>

@if (! $up)
  <div class="card radius-8 border-0 shadow-sm">
    <div class="card-body text-center py-64">
      <iconify-icon icon="solar:database-outline" class="text-2xl text-secondary-light mb-16 d-block"></iconify-icon>
      <h6 class="mb-8">Data roster {{ $tahun }} belum tersedia</h6>
      <p class="text-secondary-light text-sm mb-16">
        Jalankan backfill sekali untuk menarik scan RFID sejak 1 Januari, lalu jadwal rutin akan menjaganya tetap mutakhir.
      </p>
      <pre class="bg-neutral-50 border radius-8 d-inline-block text-start px-16 py-12 mb-0"><code>php artisan dms:sync-roster-rfid --full</code></pre>
    </div>
  </div>
@else

{{-- ===================== KONTRAKTOR + FILTER ===================== --}}
<div class="card radius-8 border-0 shadow-sm mb-24">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-4">
          <h6 class="text-lg fw-semibold mb-0">Filter Data Roster</h6>
        </div>
        <p class="text-sm text-secondary-light mb-0">
          Pilih kontraktor, lalu persempit dengan site, kategori jabatan, status, atau periode
        </p>
      </div>
      <a href="{{ route('dms.roster-compliance.wajib-cuti', request()->query()) }}"
         class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
        <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
        Download CSV
      </a>
    </div>
  </div>
  <div class="card-body p-24">

    <div class="mb-20">
      <label class="form-label text-sm fw-medium mb-6">Perusahaan / Kontraktor</label>
      <div class="d-flex flex-wrap gap-2">
        <a href="{{ $withQuery(['pt' => '']) }}"
           class="rk-co-pill text-decoration-none {{ $filters['pt'] === '' ? 'is-active' : '' }}">
          Semua Perusahaan <span class="n">{{ number_format($agregat['total'], 0, ',', '.') }}</span>
        </a>
        @foreach ($perPt as $p)
          <a href="{{ $withQuery(['pt' => $p['kode']]) }}"
             class="rk-co-pill text-decoration-none {{ $filters['pt'] === $p['kode'] ? 'is-active' : '' }}"
             title="{{ $p['perusahaan'] }}">
            {{ $p['kode'] }} <span class="n">{{ number_format($p['total'], 0, ',', '.') }}</span>
          </a>
        @endforeach
      </div>
    </div>

    <form method="GET" action="{{ route('dms.roster-compliance') }}">
      <input type="hidden" name="pt" value="{{ $filters['pt'] }}">
      <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
      <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
      <div class="bg-neutral-50 border radius-8 p-16">
        <div class="row g-3 align-items-end">
          <div class="col-xl-3 col-md-4 col-sm-6">
            <label for="rkSearch" class="form-label text-sm fw-medium mb-6">Cari Karyawan</label>
            <input type="search" id="rkSearch" name="q" value="{{ $filters['q'] }}"
                   class="form-control form-control-sm" placeholder="Nama, SID, jabatan&hellip;">
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkSiteSelect" class="form-label text-sm fw-medium mb-6">Site</label>
            <select id="rkSiteSelect" name="site" class="form-select form-select-sm">
              <option value="">Semua Site</option>
              @foreach ($siteTersedia as $s)
                <option value="{{ $s }}" @selected($filters['site'] === $s)>{{ $s }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkJabSelect" class="form-label text-sm fw-medium mb-6">Kategori Jabatan</label>
            <select id="rkJabSelect" name="kategori" class="form-select form-select-sm">
              <option value="">Semua Kategori</option>
              @foreach ($kategoriTersedia as $k)
                <option value="{{ $k }}" @selected($filters['kategori'] === $k)>{{ $k }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkRosterSelect" class="form-label text-sm fw-medium mb-6">Roster</label>
            <select id="rkRosterSelect" name="roster" class="form-select form-select-sm">
              <option value="">Semua Roster</option>
              @foreach ($rosterTersedia as $r)
                <option value="{{ $r }}" @selected($filters['roster'] === (string) $r)>Roster ke-{{ $r }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkStatusSelect" class="form-label text-sm fw-medium mb-6">Status Kini</label>
            <select id="rkStatusSelect" name="status" class="form-select form-select-sm">
              <option value="">Semua Status Kini</option>
              @foreach ($statusTersedia as $s)
                <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ $s }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkNoteSelect" class="form-label text-sm fw-medium mb-6">Catatan</label>
            <select id="rkNoteSelect" name="note" class="form-select form-select-sm">
              <option value="">Semua Catatan</option>
              <option value="pel" @selected($filters['note'] === 'pel')>Pelanggaran</option>
              <option value="wajib" @selected($filters['note'] === 'wajib')>Wajib Cuti</option>
              <option value="map" @selected($filters['note'] === 'map')>Mapping</option>
            </select>
          </div>

          <div class="col-12"><hr class="my-4 text-neutral-200"></div>

          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkPeriodSelect" class="form-label text-sm fw-medium mb-6">Periode</label>
            <select id="rkPeriodSelect" name="periode" class="form-select form-select-sm">
              <option value="">YTD penuh</option>
              <option value="kuartal" @selected($filters['periode'] === 'kuartal')>Kuartal</option>
              <option value="bulan" @selected($filters['periode'] === 'bulan')>Bulan</option>
              <option value="minggu" @selected($filters['periode'] === 'minggu')>Minggu</option>
              <option value="custom" @selected($filters['periode'] === 'custom')>Rentang tanggal</option>
            </select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkPeriodValue" class="form-label text-sm fw-medium mb-6">Nilai Periode</label>
            <select id="rkPeriodValue" name="nilai" class="form-select form-select-sm">
              <option value="">&mdash;</option>
              @if ($filters['periode'] === 'bulan')
                @foreach ($periode['bulan'] as $b)
                  <option value="{{ $b }}" @selected($filters['nilai'] === (string) $b)>
                    {{ \Carbon\Carbon::create($tahun, $b, 1)->translatedFormat('F') }}
                  </option>
                @endforeach
              @elseif ($filters['periode'] === 'minggu')
                @foreach ($periode['minggu'] as $w)
                  <option value="{{ $w }}" @selected($filters['nilai'] === (string) $w)>Minggu ke-{{ $w }}</option>
                @endforeach
              @else
                @for ($q = 1; $q <= 4; $q++)
                  <option value="{{ $q }}" @selected($filters['nilai'] === (string) $q)>Q{{ $q }}</option>
                @endfor
              @endif
            </select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkDateFrom" class="form-label text-sm fw-medium mb-6">Rentang Dari</label>
            <input type="date" id="rkDateFrom" name="dari" value="{{ $filters['dari'] }}" class="form-control form-control-sm">
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="rkDateTo" class="form-label text-sm fw-medium mb-6">Sampai</label>
            <input type="date" id="rkDateTo" name="sampai" value="{{ $filters['sampai'] }}" class="form-control form-control-sm">
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary-600 radius-8 flex-grow-1">Terapkan</button>
            <a href="{{ route('dms.roster-compliance') }}" class="btn btn-sm btn-outline-primary-600 radius-8">Reset</a>
          </div>
        </div>
      </div>
      <div class="text-secondary-light text-sm mt-12">
        Periode aktif: <b>{{ $periode['label'] }}</b>.
        Kolom <i>On-site YTD</i> &amp; <i>Cuti Min</i> tetap dihitung 1 Jan s/d
        {{ \Carbon\Carbon::parse($meta['hari_terakhir'])->translatedFormat('d M') }}, tidak terpengaruh periode.
      </div>
    </form>
  </div>
</div>

{{-- ============ KARTU RINGKASAN (sparkline) + TREN MINGGUAN ============ --}}
@php
  $kartu = [
    ['label' => 'Total Karyawan Terpantau', 'value' => $agregat['total'],
     'sub' => ($filters['pt'] === '' ? 'Semua perusahaan' : $filters['pt']).($filters['site'] !== '' ? ' · '.$filters['site'] : ''),
     'bg' => 'var(--primary-600)', 'icon' => 'solar:users-group-rounded-bold',
     'grad' => 'bg-gradient-end-1', 'spark' => 'rkSparkTotal', 'extra' => null],
    ['label' => 'Pelanggaran Regulasi (YTD)', 'value' => $agregat['pelanggaran'],
     'sub' => '<span class="bg-danger-focus px-1 rounded-2 fw-medium text-danger-main text-sm">'.$pct($agregat['pelanggaran']).'</span> dari populasi',
     'bg' => 'var(--danger-main)', 'icon' => 'solar:danger-triangle-bold',
     'grad' => 'bg-gradient-end-5', 'spark' => 'rkSparkPel', 'extra' => 'reg'],
    ['label' => 'Tidak Sesuai Mapping Shift', 'value' => $agregat['mapping'],
     'sub' => 'Peringatan, bukan pelanggaran',
     'bg' => 'var(--warning-main)', 'icon' => 'solar:shield-warning-bold',
     'grad' => 'bg-gradient-end-3', 'spark' => 'rkSparkMap',
     'extra' => '<p class="text-sm mb-0 mt-8"><span class="bg-warning-focus px-1 rounded-2 fw-medium text-warning-main text-sm">'.$pct($agregat['mapping']).'</span> dari populasi</p>'],
    ['label' => 'Wajib Cuti Sekarang', 'value' => $agregat['wajib_cuti'],
     'sub' => 'Sedang on-site &gt;'.$ambangWajib.' hari berjalan, belum cuti',
     'bg' => '#be123c', 'icon' => 'solar:calendar-mark-bold',
     'grad' => 'bg-gradient-end-4', 'spark' => 'rkSparkWajib',
     'extra' => '<p class="text-sm mb-0 mt-8"><span class="bg-danger-focus px-1 rounded-2 fw-medium text-danger-main text-sm">'.$pct($agregat['wajib_cuti']).'</span> dari populasi</p>'],
  ];
@endphp

<div class="row gy-4 mb-24">
  <div class="col-xxl-8">
    <div class="row gy-4 h-100">
      @foreach ($kartu as $k)
        <div class="col-xxl-6 col-sm-6">
          <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 {{ $k['grad'] }}">
            <div class="card-body p-0">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                <div class="d-flex align-items-center gap-2">
                  <span class="w-48-px h-48-px flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle"
                        style="background:{{ $k['bg'] }}">
                    <iconify-icon icon="{{ $k['icon'] }}" class="icon text-xl"></iconify-icon>
                  </span>
                  <div>
                    <span class="mb-2 fw-medium text-secondary-light text-sm d-block">{{ $k['label'] }}</span>
                    <h6 class="fw-semibold mb-0">{{ number_format($k['value'], 0, ',', '.') }}</h6>
                  </div>
                </div>
                <div id="{{ $k['spark'] }}" class="remove-tooltip-title rounded-tooltip-value"></div>
              </div>
              <p class="text-sm mb-0 text-secondary-light">{!! $k['sub'] !!}</p>
              @if ($k['extra'] === 'reg')
                <div class="mt-8 d-flex flex-column gap-1 text-xs">
                  <div class="d-flex align-items-center justify-content-between gap-2">
                    <span class="text-secondary-light">On-site &gt;{{ $ambangReg2 }} hr tanpa cuti</span>
                    <span class="bg-danger-focus text-danger-main px-8 py-0 rounded-pill fw-semibold">{{ number_format($agregat['reg2'], 0, ',', '.') }}</span>
                  </div>
                  <div class="d-flex align-items-center justify-content-between gap-2">
                    <span class="text-secondary-light">Cuti &lt;{{ $ambangReg3 }} hari</span>
                    <span class="bg-danger-focus text-danger-main px-8 py-0 rounded-pill fw-semibold">{{ number_format($agregat['reg3'], 0, ',', '.') }}</span>
                  </div>
                  <div class="d-flex align-items-center justify-content-between gap-2">
                    <span class="text-secondary-light">Kerja &ge;{{ $ambangReg1 }} hari beruntun</span>
                    <span class="bg-danger-focus text-danger-main px-8 py-0 rounded-pill fw-semibold">{{ number_format($agregat['reg1'], 0, ',', '.') }}</span>
                  </div>
                </div>
              @elseif ($k['extra'])
                {!! $k['extra'] !!}
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  <div class="col-xxl-4 d-flex">
    <div class="card h-100 w-100 radius-8 border-0 shadow-sm d-flex flex-column overflow-hidden">
      <div class="card-body p-24 d-flex flex-column flex-grow-1" style="min-height:0">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between flex-shrink-0">
          <div>
            <h6 class="mb-1 fw-bold text-lg">Tren Pelanggaran (%)</h6>
            <span class="text-sm fw-medium text-secondary-light">Per minggu (Senin&ndash;Minggu)</span>
          </div>
          <div class="text-end" id="rkTrendHead"></div>
        </div>
        <div id="rkTrendChart" class="mt-12 flex-grow-1" style="min-height:0"></div>
      </div>
    </div>
  </div>
</div>

{{-- ============ HEATMAP HARIAN + PERINGKAT + DISTRIBUSI ============ --}}
<div class="row gy-4 mb-24">
  <div class="col-xxl-8 d-flex">
    <div class="card h-100 w-100 radius-8 border-0 shadow-sm">
      <div class="card-body p-24 d-flex flex-column h-100">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-16">
          <div class="d-flex align-items-start gap-3" style="min-width:0">
            <span class="w-48-px h-48-px bg-danger-main text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle">
              <iconify-icon icon="mdi:calendar-month-outline" class="icon text-xl"></iconify-icon>
            </span>
            <div style="min-width:0">
              <h6 class="text-lg fw-semibold mb-4">Pola Pelanggaran Harian</h6>
              <p class="text-sm text-secondary-light mb-0">Hari apa pelanggaran roster paling sering terjadi?</p>
            </div>
          </div>
        </div>
        <div id="rkDayHeatmap" class="flex-grow-1"></div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4">
    <div class="row gy-4">
      <div class="col-12">
        <div class="card radius-8 border-0 shadow-sm">
          <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="text-lg fw-semibold mb-0">Peringkat Pelanggaran</h6>
            <span class="text-sm text-secondary-light">{{ $filters['pt'] === '' ? 'Per kontraktor' : 'Per site' }}</span>
          </div>
          <div class="card-body p-24" id="rkTopList"></div>
        </div>
      </div>

      <div class="col-12">
        <div class="card radius-8 border-0 shadow-sm">
          <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="text-lg fw-semibold mb-0">Distribusi Status Kini</h6>
            <span class="text-sm text-secondary-light">Hari terakhir periode</span>
          </div>
          <div class="card-body p-24">
            <div id="rkDonutChart"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ===================== REKAP PER SITE ===================== --}}
<div class="row gy-4 mb-24">
  <div class="col-12 d-flex">
    <div class="card h-100 w-100 radius-8 border-0 shadow-sm">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-4">Rekap per Site</h6>
        <p class="text-sm text-secondary-light mb-0">Total, pelanggaran YTD, dan yang wajib segera dicutikan</p>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table bordered-table mb-0 rk-table">
            <thead>
              <tr>
                <th>Site</th>
                <th class="text-end">Total</th>
                <th class="text-end">Pelanggaran (YTD)</th>
                <th class="text-end">Wajib Cuti (Kini)</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($perSite as $s)
                <tr>
                  <td><b>{{ $s['site'] }}</b></td>
                  <td class="text-end">{{ number_format($s['total'], 0, ',', '.') }}</td>
                  <td class="text-end text-danger-600 fw-semibold">{{ number_format($s['pelanggaran'], 0, ',', '.') }}</td>
                  <td class="text-end fw-semibold" style="color:#be123c">{{ number_format($s['wajib_cuti'], 0, ',', '.') }}</td>
                </tr>
              @endforeach
              <tr style="border-top:2px solid var(--neutral-400,#9ca3af)">
                <td><b>TOTAL</b></td>
                <td class="text-end"><b>{{ number_format($agregat['total'], 0, ',', '.') }}</b></td>
                <td class="text-end text-danger-600 fw-bold">{{ number_format($agregat['pelanggaran'], 0, ',', '.') }}</td>
                <td class="text-end fw-bold" style="color:#be123c">{{ number_format($agregat['wajib_cuti'], 0, ',', '.') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ===================== TABEL + PANEL DETAIL ===================== --}}
<div class="row gy-4">
  <div class="col-xxl-8 d-flex">
    <div class="card h-100 w-100 radius-8 border-0 shadow-sm rk-master-card">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
          <div>
            <div class="d-flex align-items-center gap-2 mb-4">
              <h6 class="text-lg fw-semibold mb-0">Daftar Karyawan</h6>
              <span class="bg-primary-50 text-primary-600 text-sm fw-medium px-12 py-2 rounded-pill">
                {{ number_format($paginasi['total_baris'], 0, ',', '.') }} karyawan
              </span>
            </div>
            <p class="text-sm text-secondary-light mb-0">Klik satu baris untuk melihat timeline harian &amp; riwayat alert DMS</p>
          </div>
        </div>
      </div>
      <div class="card-body p-0 d-flex flex-column">
        <div class="table-responsive rk-table-scroll">
          <table class="table bordered-table mb-0 rk-table">
            <thead>
              <tr>
                <th scope="col"><a class="rkl-sortlink" href="{{ $sortUrl('sid') }}">SID <span class="ar">{{ $sortArrow('sid') }}</span></a></th>
                <th scope="col" class="rk-col-name"><a class="rkl-sortlink" href="{{ $sortUrl('nama') }}">Karyawan <span class="ar">{{ $sortArrow('nama') }}</span></a></th>
                <th scope="col" class="text-center"><a class="rkl-sortlink" href="{{ $sortUrl('roster') }}">Roster <span class="ar">{{ $sortArrow('roster') }}</span></a></th>
                <th scope="col" class="text-center"><a class="rkl-sortlink" href="{{ $sortUrl('onAll') }}">On-site&nbsp;YTD <span class="ar">{{ $sortArrow('onAll') }}</span></a></th>
                <th scope="col" class="text-center"><a class="rkl-sortlink" href="{{ $sortUrl('cutiMin') }}">Cuti&nbsp;Min <span class="ar">{{ $sortArrow('cutiMin') }}</span></a></th>
                <th scope="col"><a class="rkl-sortlink" href="{{ $sortUrl('status') }}">Status Kini <span class="ar">{{ $sortArrow('status') }}</span></a></th>
                <th scope="col" class="text-center">Alert&nbsp;DMS</th>
              </tr>
            </thead>
            <tbody id="rkTableBody">
              @forelse ($baris as $r)
                @php
                  $badOn = ! $r['longgar'] && $r['onAll'] > $ambangReg2;
                  $badCuti = ! $r['longgar'] && $r['cutiMin'] > 0 && $r['cutiMin'] < $ambangReg3;
                @endphp
                <tr data-sid="{{ $r['sid'] }}">
                  <td class="fw-medium">{{ $r['sid'] }}</td>
                  <td class="rk-col-name" title="{{ $r['nama'] }} — {{ $r['jabatan'] }} · {{ $r['perusahaan'] }} {{ $r['site'] }}">
                    <span class="rk-name">{{ $r['nama'] }}</span>
                    <span class="rk-sub">{{ $r['kategori'] }} &middot; {{ $r['kode_pt'] }} {{ $r['site'] }}</span>
                  </td>
                  <td class="text-center">{{ $r['roster'] }}</td>
                  <td class="text-center">
                    {{ $r['onAll'] }}@if ($badOn) <span class="rk-flag-red">⚠</span>@endif
                  </td>
                  <td class="text-center">
                    {{ $r['cutiMin'] }}@if ($badCuti) <span class="rk-flag-red">⚠</span>@endif
                  </td>
                  <td>
                    <span class="{{ $statusBadge[$r['status']] ?? '' }} px-10 py-4 rounded-pill fw-medium text-xs d-inline-flex align-items-center"
                          @if ($r['status'] === 'Shift Malam') style="background:#1e3a8a" @endif>
                      {{ $r['status'] }}
                      @if ($r['merah'])
                        <iconify-icon icon="solar:danger-triangle-bold" class="rk-flag-red ms-4" title="Pelanggaran regulasi"></iconify-icon>
                      @elseif ($r['kuning'])
                        <iconify-icon icon="solar:shield-warning-bold" class="rk-flag-yel ms-4" title="Tidak sesuai mapping shift"></iconify-icon>
                      @endif
                    </span>
                  </td>
                  <td class="text-center" data-alert-sid="{{ strtoupper($r['sid']) }}">
                    <span class="spinner-border spinner-border-sm text-secondary-light" style="width:12px;height:12px" role="status"></span>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center text-secondary-light py-48">
                    Tidak ada karyawan yang cocok dengan filter ini.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer bg-base border-top py-12 px-24 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="text-secondary-light text-sm">
          Hal {{ $paginasi['halaman'] }}/{{ $paginasi['total_halaman'] }}
          &middot; menampilkan {{ number_format($paginasi['dari'], 0, ',', '.') }}&ndash;{{ number_format($paginasi['sampai'], 0, ',', '.') }}
        </span>
        <div class="d-flex gap-2">
          @if ($paginasi['halaman'] > 1)
            <a href="{{ request()->fullUrlWithQuery(['page' => $paginasi['halaman'] - 1]) }}" class="btn btn-sm btn-outline-primary-600 radius-8">&laquo; Sebelumnya</a>
          @else
            <button class="btn btn-sm btn-outline-primary-600 radius-8" disabled>&laquo; Sebelumnya</button>
          @endif
          @if ($paginasi['halaman'] < $paginasi['total_halaman'])
            <a href="{{ request()->fullUrlWithQuery(['page' => $paginasi['halaman'] + 1]) }}" class="btn btn-sm btn-outline-primary-600 radius-8">Berikutnya &raquo;</a>
          @else
            <button class="btn btn-sm btn-outline-primary-600 radius-8" disabled>Berikutnya &raquo;</button>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4 d-flex">
    <div class="card h-100 w-100 radius-8 border-0 shadow-sm">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-4">Rincian Karyawan</h6>
        <p class="text-sm text-secondary-light mb-0">Timeline harian, flag pelanggaran, dan alert DMS</p>
      </div>
      <div class="card-body p-24" id="rkDetailWrap">
        <div class="rk-detail-empty">
          <iconify-icon icon="solar:user-hand-up-outline" class="text-2xl mb-8 d-block"></iconify-icon>
          Pilih satu baris karyawan untuk melihat rincian hariannya.
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ===================== PARAMETER PER KONTRAKTOR ===================== --}}
<div class="card radius-8 border-0 shadow-sm mt-24">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-4">Parameter per Kontraktor</h6>
    <p class="text-sm text-secondary-light mb-0">
      Blok roster hanya memengaruhi flag kuning MAP-1; ambang merah seragam untuk semua PT
    </p>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive" style="max-height:340px;overflow-y:auto">
      <table class="table bordered-table mb-0 rk-param-table">
        <thead>
          <tr>
            <th>PT</th>
            <th>Nama Lengkap</th>
            <th>Siklus Roster</th>
            <th>Pola Shift</th>
            <th class="text-center">Maks Blok Kerja</th>
            <th class="text-center">Karyawan</th>
          </tr>
        </thead>
        <tbody>
          @php $daftarPt = config('dms_roster.perusahaan', []); @endphp
          @foreach ($perPt as $p)
            @php
              $cfg = $daftarPt[$p['perusahaan']] ?? null;
              $thr = (int) ($cfg['thr'] ?? config('dms_roster.thr_default', 7));
            @endphp
            <tr>
              <td class="fw-semibold">{{ $p['kode'] }}</td>
              <td>{{ $p['perusahaan'] }}</td>
              <td>{{ $cfg['roster'] ?? '—' }}</td>
              <td>
                {{ $cfg['shift'] ?? '—' }}
                @if (! empty($cfg['shift_detail']))
                  <div class="text-secondary-light" style="font-size:10.5px">{{ $cfg['shift_detail'] }}</div>
                @endif
              </td>
              <td class="text-center">{{ $thr - 1 }} hr</td>
              <td class="text-center">{{ number_format($p['total'], 0, ',', '.') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- ===================== KETERANGAN ATURAN ===================== --}}
<div class="card radius-8 border-0 shadow-sm mt-24">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-0">Keterangan Aturan</h6>
  </div>
  <div class="card-body p-24">
    <div class="row gy-3">
      <div class="col-md-6">
        <div class="d-flex align-items-start gap-2 mb-8">
          <span class="w-32-px h-32-px bg-danger-focus text-danger-main radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">
            <iconify-icon icon="solar:danger-triangle-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <h6 class="text-md fw-semibold mb-4">Pelanggaran regulasi (merah)</h6>
            <ul class="text-sm text-secondary-light mb-0 ps-16">
              <li>On-site tanpa cuti &gt;{{ $ambangReg2 }} hari</li>
              <li>Kerja beruntun (Pagi/Malam tanpa off) &ge;{{ $ambangReg1 }} hari</li>
              <li>Durasi cuti &lt;{{ $ambangReg3 }} hari</li>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="d-flex align-items-start gap-2 mb-8">
          <span class="w-32-px h-32-px bg-warning-focus text-warning-main radius-8 d-inline-flex align-items-center justify-content-center flex-shrink-0">
            <iconify-icon icon="solar:shield-warning-bold" class="icon"></iconify-icon>
          </span>
          <div>
            <h6 class="text-md fw-semibold mb-4">Peringatan mapping (kuning)</h6>
            <ul class="text-sm text-secondary-light mb-0 ps-16">
              <li>Blok kerja melebihi standar roster PT</li>
              <li>Urutan / pergantian shift tidak sesuai mapping</li>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-16 py-12 mb-0 radius-8 text-sm" role="alert">
          Kategori <b>Operator Transportasi Massal</b> &amp; <b>Mekanik</b> dikecualikan dari aturan on-site/cuti
          karena pola kerjanya berbeda.
        </div>
      </div>
    </div>
  </div>
</div>
@endif
@endsection

@section('scripts')
@if ($up)
<script>
(function () {
  var SERI = @json($seri);
  var HARI_ISO = @json(array_column($hari, 'iso'));
  var TOP = @json($topPelanggaran);
  var STATUS = @json($agregat['status']);
  var TOTAL = @json($agregat['total']);
  var TAHUN = @json($tahun);
  var DETAIL_URL = @json(route('dms.roster-compliance.detail', ['sid' => '__SID__']));
  var ALERT_COUNTS_URL = @json(route('dms.roster-compliance-static.alert-counts'));
  var ALERT_TIMELINE_BASE = @json(url('dms/roster-compliance-static/alerts'));
  var ALERT_UNTIL = @json($meta['hari_terakhir']);

  var MON = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  var MON_LONG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  var HARI_NAMA = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  var STATUS_ORDER = ['Shift Pagi', 'Shift Malam', 'Overshift', 'Off', 'Cuti'];
  var STATUS_COLOR = { 'Shift Pagi': '#60a5fa', 'Shift Malam': '#1e3a8a', Overshift: '#d97706', Off: '#94a3b8', Cuti: '#12a150' };
  var ALERT_META = {
    sudah: { cls: 'bg-success-100 text-success-600', label: 'Ditangani' },
    proses: { cls: 'bg-warning-100 text-warning-600', label: 'Proses' },
    belum: { cls: 'bg-danger-100 text-danger-600', label: 'Belum' },
  };

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /* ---------------- Sparkline kartu KPI ---------------- */
  function spark(id, data, color) {
    var el = document.getElementById(id);
    if (!el || !window.ApexCharts || !data || data.length < 2) return;
    new ApexCharts(el, {
      chart: { type: 'area', height: 52, width: 92, sparkline: { enabled: true }, animations: { enabled: false } },
      series: [{ name: 'Minggu', data: data }],
      stroke: { curve: 'smooth', width: 2 },
      colors: [color],
      fill: { type: 'gradient', gradient: { shadeIntensity: 0.4, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 100] } },
      tooltip: {
        x: { formatter: function (_v, o) { return 'Minggu ' + (o.dataPointIndex + 1) + ' · ' + (SERI.minggu[o.dataPointIndex] || ''); } },
        y: { formatter: function (v) { return v.toLocaleString('id') + ' orang'; } },
      },
    }).render();
  }

  /* ---------------- Chart tren mingguan ---------------- */
  function renderTrend() {
    var el = document.getElementById('rkTrendChart');
    if (!el || !window.ApexCharts) return;

    var data = SERI.persen || [];
    var last = data.length ? data[data.length - 1] : 0;
    var prev = data.length > 1 ? data[data.length - 2] : last;
    var delta = +(last - prev).toFixed(1);

    var head = document.getElementById('rkTrendHead');
    if (head) {
      var up = delta > 0;
      head.innerHTML = '<h6 class="mb-1 fw-bold text-lg">' + last.toFixed(1).replace('.', ',') + '%</h6>' +
        '<span class="' + (up ? 'bg-danger-focus text-danger-main' : 'bg-success-focus text-success-main') +
        ' ps-12 pe-12 pt-2 pb-2 rounded-2 fw-medium text-sm">' +
        (up ? '+' : '') + delta.toFixed(1).replace('.', ',') + ' pp</span>';
    }

    new ApexCharts(el, {
      chart: { type: 'area', height: 240, toolbar: { show: false }, animations: { enabled: false } },
      series: [{ name: 'Pelanggaran', data: data }],
      xaxis: {
        categories: SERI.minggu || [],
        labels: { rotate: -45, style: { fontSize: '10px', colors: '#9ca3af' }, hideOverlappingLabels: true },
        axisBorder: { show: false }, axisTicks: { show: false },
      },
      yaxis: { labels: { formatter: function (v) { return v.toFixed(0) + '%'; }, style: { fontSize: '11px', colors: '#9ca3af' } } },
      stroke: { curve: 'smooth', width: 2 },
      colors: ['#487FFF'],
      fill: { type: 'gradient', gradient: { shadeIntensity: 0.4, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
      dataLabels: { enabled: false },
      grid: { borderColor: '#eef1f6', strokeDashArray: 4 },
      tooltip: { y: { formatter: function (v) { return v.toFixed(1).replace('.', ',') + '% populasi'; } } },
    }).render();
  }

  /* ---------------- Heatmap hari-kerja x minggu ---------------- */
  function heatLevel(v, max) {
    if (!v || !max) return 0;
    var r = v / max;
    if (r <= 0.25) return 1;
    if (r <= 0.5) return 2;
    if (r <= 0.75) return 3;
    return 4;
  }

  function renderHeatmap() {
    var el = document.getElementById('rkDayHeatmap');
    if (!el) return;
    var harian = SERI.harian || [];
    if (!harian.length) { el.innerHTML = '<div class="text-secondary-light text-sm py-24 text-center">Tidak ada data pada rentang ini.</div>'; return; }

    var weekOf = [], w = -1, colLabel = [];
    for (var i = 0; i < HARI_ISO.length; i++) {
      var dt = new Date(HARI_ISO[i] + 'T00:00:00Z');
      if (i === 0 || dt.getUTCDay() === 1) { w++; colLabel[w] = dt.getUTCDate() + ' ' + MON[dt.getUTCMonth()]; }
      weekOf[i] = w;
    }
    var cols = w + 1, max = SERI.max_harian || 0;

    var grid = [];
    for (var d = 0; d < 7; d++) { grid.push(new Array(cols)); for (var c = 0; c < cols; c++) grid[d][c] = null; }
    for (var j = 0; j < HARI_ISO.length; j++) {
      var dt2 = new Date(HARI_ISO[j] + 'T00:00:00Z');
      grid[(dt2.getUTCDay() + 6) % 7][weekOf[j]] = { iso: HARI_ISO[j], v: harian[j] || 0 };
    }

    var labels = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    var rows = '';
    for (var d2 = 0; d2 < 7; d2++) {
      var cells = '';
      for (var c2 = 0; c2 < cols; c2++) {
        var cell = grid[d2][c2];
        if (!cell) { cells += '<span class="rk-hm-cell is-empty"></span>'; continue; }
        var dt3 = new Date(cell.iso + 'T00:00:00Z');
        var title = HARI_NAMA[dt3.getUTCDay()] + ', ' + dt3.getUTCDate() + ' ' + MON_LONG[dt3.getUTCMonth()] +
          ' — ' + cell.v.toLocaleString('id') + ' karyawan ter-flag';
        cells += '<span class="rk-hm-cell lvl-' + heatLevel(cell.v, max) + '" title="' + esc(title) + '"></span>';
      }
      rows += '<div class="rk-hm-row"><span class="rk-hm-label">' + labels[d2] + '</span>' + cells + '</div>';
    }

    var axis = '<div class="rk-hm-row rk-hm-axis"><span class="rk-hm-label"></span>';
    for (var c3 = 0; c3 < cols; c3++) {
      axis += '<span class="rk-hm-cell rk-hm-tick">' + (c3 % 4 === 0 ? '<b>' + esc(colLabel[c3] || '') + '</b>' : '') + '</span>';
    }
    axis += '</div>';

    el.innerHTML = '<div class="rk-hm-scroll"><div class="rk-hm-grid">' + rows + axis + '</div></div>' +
      '<div class="d-flex align-items-center gap-2 mt-16 flex-wrap text-xs text-secondary-light">' +
      '<span>Jumlah karyawan ter-flag</span>' +
      '<span class="d-inline-flex align-items-center gap-1"><i class="rk-hm-key lvl-0"></i>0</span>' +
      '<span class="d-inline-flex align-items-center gap-1"><i class="rk-hm-key lvl-1"></i>rendah</span>' +
      '<span class="d-inline-flex align-items-center gap-1"><i class="rk-hm-key lvl-2"></i></span>' +
      '<span class="d-inline-flex align-items-center gap-1"><i class="rk-hm-key lvl-3"></i></span>' +
      '<span class="d-inline-flex align-items-center gap-1"><i class="rk-hm-key lvl-4"></i>tinggi (maks ' +
      max.toLocaleString('id') + ')</span></div>';
  }

  /* ---------------- Daftar peringkat berbar ---------------- */
  function renderTopList() {
    var el = document.getElementById('rkTopList');
    if (!el) return;
    if (!TOP.length) { el.innerHTML = '<div class="text-secondary-light text-sm py-24 text-center">Tidak ada pelanggaran pada filter ini.</div>'; return; }

    var maxV = TOP[0].value || 1;
    var palette = ['#ef4a00', '#487FFF', '#12a150', '#9333ea', '#d97706', '#0ea5e9'];
    el.innerHTML = TOP.map(function (t, i) {
      var color = palette[i % palette.length];
      return '<div class="d-flex align-items-center gap-3 mb-16">' +
        '<span class="w-40-px h-40-px rounded-circle text-white d-flex align-items-center justify-content-center flex-shrink-0 fw-semibold text-sm" style="background:' + color + '">' +
        esc(String(t.label).slice(0, 2).toUpperCase()) + '</span>' +
        '<div class="flex-grow-1" style="min-width:0">' +
        '<div class="text-sm fw-medium text-truncate mb-4" title="' + esc(t.label) + '">' + esc(t.label) + '</div>' +
        '<div class="rk-top-track"><span class="rk-top-fill" style="width:' + Math.max(4, (t.value / maxV) * 100) + '%;background:' + color + '"></span></div>' +
        '</div>' +
        '<span class="fw-semibold text-md flex-shrink-0">' + t.value.toLocaleString('id') + '</span>' +
        '</div>';
    }).join('');
  }

  /* ---------------- Donat distribusi status ---------------- */
  function renderDonut() {
    var el = document.getElementById('rkDonutChart');
    if (!el || !window.ApexCharts) return;
    new ApexCharts(el, {
      chart: { type: 'donut', height: 260 },
      labels: STATUS_ORDER,
      series: STATUS_ORDER.map(function (s) { return STATUS[s] || 0; }),
      colors: STATUS_ORDER.map(function (s) { return STATUS_COLOR[s]; }),
      legend: { position: 'bottom', fontSize: '12px' },
      dataLabels: { enabled: true, formatter: function (v) { return v.toFixed(0) + '%'; } },
      plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total', formatter: function () { return TOTAL.toLocaleString('id'); } } } } } },
      tooltip: { y: { formatter: function (v) { return v.toLocaleString('id') + ' orang'; } } },
    }).render();
  }

  /* ---------------- Alert DMS per baris tabel ---------------- */
  function alertCell(v) {
    if (v === 'x') return '<span class="text-secondary-light" title="Data alert DMS tidak tersedia saat ini">&mdash;</span>';
    if (!v) return '<span class="text-secondary-light">0</span>';
    return '<span class="bg-danger-100 text-danger-600 px-8 py-2 rounded-pill fw-medium text-xs">' + v + '</span>';
  }

  function loadAlertCounts() {
    var cells = document.querySelectorAll('#rkTableBody [data-alert-sid]');
    if (!cells.length) return;
    var sids = [];
    cells.forEach(function (c) { if (sids.indexOf(c.dataset.alertSid) < 0) sids.push(c.dataset.alertSid); });

    var qs = new URLSearchParams();
    sids.forEach(function (s) { qs.append('sids[]', s); });
    qs.set('until', ALERT_UNTIL);

    fetch(ALERT_COUNTS_URL + '?' + qs.toString(), { headers: { Accept: 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); })
      .then(function (data) {
        var counts = data.counts || {}, ok = data.available !== false;
        cells.forEach(function (c) {
          c.innerHTML = alertCell(ok ? (counts[c.dataset.alertSid] || 0) : 'x');
        });
      })
      .catch(function () { cells.forEach(function (c) { c.innerHTML = alertCell('x'); }); });
  }

  /* ---------------- Panel detail karyawan ---------------- */
  var panel = document.getElementById('rkDetailWrap');
  var body = document.getElementById('rkTableBody');
  var aktif = null;

  function stat(label, value, bad) {
    return '<div class="col-6"><div class="rk-stat-mini' + (bad ? ' is-bad' : '') + '">' +
      '<div class="k">' + esc(label) + '</div><div class="v">' + value + '</div></div></div>';
  }

  function heatStrip(hari) {
    var ruler = '', days = '', prevMonth = '';
    hari.forEach(function (h) {
      var isMonthStart = h.iso.slice(0, 7) !== prevMonth;
      prevMonth = h.iso.slice(0, 7);
      ruler += '<span class="' + (isMonthStart ? 'mstart' : '') + '">' +
        (isMonthStart ? '<b>' + MON[parseInt(h.iso.slice(5, 7), 10) - 1] + '</b>' : '') + '</span>';
      var cls = 'd rk-d-' + h.kode + (h.merah ? ' rk-d-red' : (h.kuning ? ' rk-d-yel' : '')) + (isMonthStart ? ' mstart' : '');
      var title = h.label + ' — ' + h.kode_label + ' (hari ke-' + h.hari_ke + ')' +
        (h.jam_in ? '\nCheck-in ' + h.jam_in + (h.gate_in ? ' @ ' + h.gate_in : '') : '') +
        (h.jam_out ? '\nCheck-out ' + h.jam_out : '') + (h.durasi ? '\nDurasi ' + h.durasi : '') +
        (h.merah ? '\n⚠ ' + h.merah : (h.kuning ? '\n⚠ ' + h.kuning : ''));
      days += '<span class="' + cls + '" title="' + esc(title) + '"></span>';
    });
    return '<div class="rk-heat-outer"><div class="rk-heat-inner">' +
      '<div class="rk-heat-ruler">' + ruler + '</div><div class="rk-heat-strip">' + days + '</div></div></div>';
  }

  function flagList(hari) {
    var items = hari.filter(function (h) { return h.merah || h.kuning; });
    if (!items.length) return '<div class="text-secondary-light text-sm text-center py-16">Tidak ada flag pada rentang ini.</div>';
    return '<div class="d-flex flex-column gap-2 rk-flaglist-scroll">' + items.slice(-30).reverse().map(function (h) {
      var cls = h.merah ? 'border-danger-100 bg-danger-100 text-danger-600' : 'border-warning-100 bg-warning-100 text-warning-600';
      return '<div class="border radius-8 px-12 py-8 text-sm ' + cls + '"><b>' + esc(h.label) + '</b> — ' + esc(h.merah || h.kuning) + '</div>';
    }).join('') + '</div>';
  }

  function renderDetail(d) {
    var badgeStyle = d.status === 'Shift Malam' ? ' style="background:#1e3a8a"' : '';
    var badgeCls = {
      'Shift Pagi': 'bg-primary-100 text-primary-600', 'Shift Malam': 'text-white',
      'Overshift': 'bg-warning-100 text-warning-600', 'Off': 'bg-neutral-200 text-neutral-600',
      'Cuti': 'bg-success-100 text-success-600',
    }[d.status] || '';

    var alertBox = d.pelanggaran.length
      ? '<div class="alert-danger bg-danger-100 text-danger-600 border-danger-100 border px-16 py-10 radius-8 mb-16 text-sm">' +
        '<iconify-icon icon="solar:danger-triangle-bold" class="icon me-1 align-middle"></iconify-icon><b>Ada pelanggaran regulasi</b> — lihat daftar flag di bawah.</div>'
      : '<div class="alert-success bg-success-100 text-success-600 border-success-100 border px-16 py-10 radius-8 mb-16 text-sm">' +
        '<iconify-icon icon="solar:check-circle-bold" class="icon me-1 align-middle"></iconify-icon>Tidak ada flag pada rentang ini.</div>';

    panel.innerHTML =
      '<div class="d-flex align-items-start justify-content-between gap-2 mb-16">' +
      '<div><span class="text-secondary-light text-sm">' + esc(d.sid) + '</span>' +
      '<h6 class="mb-0 mt-4">' + esc(d.nama) + '</h6>' +
      '<div class="d-flex flex-wrap gap-2 mt-8">' +
      '<span class="bg-neutral-100 text-secondary-light px-10 py-2 radius-8 text-xs fw-medium">' + esc(d.perusahaan) + '</span>' +
      '<span class="bg-neutral-100 text-secondary-light px-10 py-2 radius-8 text-xs fw-medium">' + esc(d.site || '—') + '</span>' +
      '<span class="bg-neutral-100 text-secondary-light px-10 py-2 radius-8 text-xs fw-medium">' + esc(d.jabatan || '—') + '</span>' +
      '<span class="bg-neutral-100 text-secondary-light px-10 py-2 radius-8 text-xs fw-medium">Roster ke-' + d.roster + '</span>' +
      (d.longgar ? '<span class="bg-info-100 text-info-600 px-10 py-2 radius-8 text-xs fw-medium">Kategori longgar (' + esc(d.kategori) + ')</span>' : '') +
      '</div></div>' +
      '<span class="' + badgeCls + ' px-16 py-6 radius-8 fw-semibold text-sm"' + badgeStyle + '>' + esc(d.status) + '</span>' +
      '</div>' + alertBox +
      '<div class="row g-2 mb-16">' +
      stat('On-site berjalan', d.onCur + ' hr', d.wajibCuti) +
      stat('Kerja beruntun maks', d.onNoOff + ' hr', false) +
      stat('On-site maks (YTD)', d.onAll + ' hr', false) +
      stat('Cuti min (YTD)', (d.cutiMin || 0) + ' hr', false) +
      '</div>' +
      '<h6 class="text-sm fw-semibold text-secondary-light text-uppercase mb-8">Timeline Pola Kerja ' + TAHUN + '</h6>' +
      '<div class="mb-8">' + heatStrip(d.hari) + '</div>' +
      '<div class="d-flex flex-wrap gap-3 text-xs text-secondary-light mb-24">' +
      '<span><i class="rk-legend-dot" style="background:#60a5fa"></i>Pagi</span>' +
      '<span><i class="rk-legend-dot" style="background:#1e3a8a"></i>Malam</span>' +
      '<span><i class="rk-legend-dot" style="background:#e2e6ea"></i>Off</span>' +
      '<span><i class="rk-legend-dot" style="background:#12a150"></i>Cuti</span>' +
      '</div>' +
      '<h6 class="text-sm fw-semibold text-secondary-light text-uppercase mb-8">Riwayat Flag</h6>' + flagList(d.hari) +
      '<h6 class="text-sm fw-semibold text-secondary-light text-uppercase mt-24 mb-8">Alert DMS (30 Hari Terakhir)</h6>' +
      '<div id="rkAlertTimeline"><div class="text-center text-secondary-light py-16"><div class="spinner-border spinner-border-sm text-primary-600" role="status"></div></div></div>';

    loadAlertTimeline(d.sid);
  }

  function loadAlertTimeline(sid) {
    var el = document.getElementById('rkAlertTimeline');
    if (!el) return;
    fetch(ALERT_TIMELINE_BASE + '/' + encodeURIComponent(sid) + '?until=' + encodeURIComponent(ALERT_UNTIL),
      { headers: { Accept: 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); })
      .then(function (data) {
        var list = data.timeline || [];
        if (data.available === false) { el.innerHTML = '<div class="text-secondary-light text-sm text-center py-16">Data alert DMS tidak tersedia saat ini.</div>'; return; }
        if (!list.length) { el.innerHTML = '<div class="text-secondary-light text-sm text-center py-16">Tidak ada alert DMS pada 30 hari terakhir.</div>'; return; }
        el.innerHTML = '<div class="d-flex flex-column gap-2 rk-flaglist-scroll">' + list.map(function (a) {
          var meta = ALERT_META[a.status] || ALERT_META.belum;
          return '<div class="d-flex align-items-center justify-content-between border radius-8 px-12 py-8">' +
            '<div><div class="text-sm fw-medium">' + esc(a.name) + '</div>' +
            '<div class="text-xs text-secondary-light">' + esc(a.date) + '</div></div>' +
            '<span class="' + meta.cls + ' px-10 py-4 rounded-pill fw-medium text-xs">' + meta.label + '</span></div>';
        }).join('') + '</div>';
      })
      .catch(function () { el.innerHTML = '<div class="text-secondary-light text-sm text-center py-16">Gagal memuat alert DMS.</div>'; });
  }

  if (body) {
    body.addEventListener('click', function (e) {
      var tr = e.target.closest('tr[data-sid]');
      if (!tr) return;
      if (aktif) aktif.classList.remove('is-selected');
      tr.classList.add('is-selected');
      aktif = tr;

      panel.innerHTML = '<div class="rk-detail-empty"><div class="spinner-border text-primary-600" role="status"></div>' +
        '<div class="mt-12">Memuat rincian&hellip;</div></div>';

      fetch(DETAIL_URL.replace('__SID__', encodeURIComponent(tr.dataset.sid)) + '?tahun=' + encodeURIComponent(TAHUN),
        { headers: { Accept: 'application/json' } })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(renderDetail)
        .catch(function (err) {
          panel.innerHTML = '<div class="alert-danger bg-danger-100 text-danger-600 border-danger-100 border px-12 py-8 radius-8 text-sm">' +
            'Gagal memuat rincian: ' + esc(err.message) + '</div>';
        });
    });
  }

  spark('rkSparkTotal', SERI.total, '#487FFF');
  spark('rkSparkPel', SERI.pel, '#ef4a00');
  spark('rkSparkMap', SERI.map, '#ff9f29');
  spark('rkSparkWajib', SERI.wajib, '#be123c');
  renderTrend();
  renderHeatmap();
  renderTopList();
  renderDonut();
  loadAlertCounts();
})();
</script>
@endif
@endsection
