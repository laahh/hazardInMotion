@extends('dms.layouts.app')

@section('title', 'Kepatuhan Roster Karyawan (Live)')

@section('css')
<link rel="stylesheet" href="{{ asset('dms-assets/roster-compliance/roster-compliance.css') }}">
<link rel="stylesheet" href="{{ asset('dms-assets/roster-compliance/roster-compliance-live.css') }}">
@endsection

@php
    /** Tautan pengurutan kolom — mempertahankan seluruh filter aktif. */
    $sortUrl = function (string $key) use ($filters) {
        $dir = ($filters['sort'] ?? '') === $key && ($filters['dir'] ?? 'asc') === 'asc' ? 'desc' : 'asc';

        return request()->fullUrlWithQuery(['sort' => $key, 'dir' => $dir, 'page' => 1]);
    };
    $sortArrow = function (string $key) use ($filters) {
        if (($filters['sort'] ?? '') !== $key) {
            return '';
        }

        return ($filters['dir'] ?? 'asc') === 'asc' ? '▲' : '▼';
    };
    $ambangReg1 = ($ambang['kerja_beruntun'] ?? 13) + 1;
    $ambangReg2 = $ambang['onsite'] ?? 71;
    $ambangReg3 = $ambang['cuti_min'] ?? 12;
    $ambangWajib = $ambang['wajib_cuti'] ?? 71;
    $ambangOvershift = $ambang['overshift'] ?? 8;
    $ambangCuti = $ambang['off_ke_cuti'] ?? 5;
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

<div class="alert-info bg-info-100 text-info-600 border-info-100 border px-16 py-13 rounded-8 mb-24 text-sm d-flex gap-2 align-items-start">
  <iconify-icon icon="solar:info-circle-bold" class="icon text-lg flex-shrink-0 mt-2"></iconify-icon>
  <div>
    Pola roster dirakit dari <b>scan gate RFID PASSED</b> (<code>bcsid.mv_checkinout_rfid</code>): check-in pertama
    &lt;12:00 = Shift Pagi, &ge;12:00 = Shift Malam, tanpa check-in = Off, dan Off lebih dari {{ $ambangCuti }} hari
    berturut-turut dianggap fase Cuti.
    <b>Asumsi:</b> karyawan tap gate saat awal &amp; akhir cuti (mess di area site), sehingga hari tanpa scan
    dihitung off/cuti &mdash; scan yang terlewat bisa memunculkan flag palsu. Verifikasi flag merah sebelum tindakan.
    Rule berbasis jam (durasi shift, jeda istirahat) belum diterapkan.
  </div>
</div>

@if (! $up)
  <div class="card radius-8 border">
    <div class="card-body text-center py-64">
      <iconify-icon icon="solar:database-outline" class="text-4xl text-secondary-light mb-16 d-block"></iconify-icon>
      <h6 class="mb-8">Data roster {{ $tahun }} belum tersedia</h6>
      <p class="text-secondary-light text-sm mb-16">
        Jalankan backfill sekali untuk menarik scan RFID sejak 1 Januari, lalu jadwal rutin akan menjaganya tetap mutakhir.
      </p>
      <pre class="bg-neutral-50 border radius-8 d-inline-block text-start px-16 py-12 mb-0"><code>php artisan dms:sync-roster-rfid --full</code></pre>
    </div>
  </div>
@else

{{-- ============================ KARTU RINGKASAN ============================ --}}
<div class="row gy-3 mb-24">
  @php
    $kartu = [
      ['label' => 'Total karyawan', 'nilai' => $agregat['total'], 'bad' => false, 'break' => null],
      ['label' => 'Pelanggaran (YTD)', 'nilai' => $agregat['pelanggaran'], 'bad' => $agregat['pelanggaran'] > 0,
       'break' => 'reg'],
      ['label' => 'Wajib cuti (saat ini)', 'nilai' => $agregat['wajib_cuti'], 'bad' => $agregat['wajib_cuti'] > 0,
       'break' => null, 'sub' => 'on-site berjalan > '.$ambangWajib.' hr'],
      ['label' => 'Overshift (kini)', 'nilai' => $agregat['status']['Overshift'] ?? 0, 'bad' => false,
       'break' => null, 'sub' => '≥ '.$ambangOvershift.' hr kerja beruntun'],
      ['label' => 'Cuti (kini)', 'nilai' => $agregat['status']['Cuti'] ?? 0, 'bad' => false, 'break' => null],
      ['label' => 'Off (kini)', 'nilai' => $agregat['status']['Off'] ?? 0, 'bad' => false, 'break' => null],
    ];
    $pct = fn (int $x): string => $agregat['total'] > 0
      ? number_format($x / $agregat['total'] * 100, 1, ',', '.').'%'
      : '0%';
  @endphp

  @foreach ($kartu as $k)
    <div class="col-xxl-2 col-lg-4 col-sm-6">
      <div class="card radius-8 border h-100 rkl-kpi {{ $k['bad'] ? 'is-bad' : '' }}">
        <div class="card-body px-16 py-13">
          <div class="n">{{ number_format($k['nilai'], 0, ',', '.') }}</div>
          <div class="l">
            {{ $k['label'] }}
            @if ($k['label'] !== 'Total karyawan')
              <span class="text-secondary-light">&middot; {{ $pct((int) $k['nilai']) }}</span>
            @endif
            @if (! empty($k['sub']))
              <span class="d-block text-secondary-light" style="font-size:10.5px">{{ $k['sub'] }}</span>
            @endif
          </div>
          @if ($k['break'] === 'reg')
            <div class="rkl-kpi-break">
              On-site &gt; {{ $ambangReg2 }} hr: <b>{{ number_format($agregat['reg2'], 0, ',', '.') }}</b> ({{ $pct($agregat['reg2']) }})<br>
              Cuti &lt; {{ $ambangReg3 }} hr: <b>{{ number_format($agregat['reg3'], 0, ',', '.') }}</b> ({{ $pct($agregat['reg3']) }})<br>
              Shift ≥ {{ $ambangReg1 }} hr beruntun: <b>{{ number_format($agregat['reg1'], 0, ',', '.') }}</b> ({{ $pct($agregat['reg1']) }})
            </div>
          @endif
        </div>
      </div>
    </div>
  @endforeach
</div>

{{-- ====================== DISTRIBUSI STATUS + REKAP SITE ====================== --}}
<div class="row gy-4 mb-24">
  <div class="col-xxl-5">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <h6 class="text-lg mb-0">Distribusi Status Kini</h6>
        <span class="text-secondary-light text-sm">Kondisi pada {{ \Carbon\Carbon::parse($periode['sampai'])->translatedFormat('d M Y') }}</span>
      </div>
      <div class="card-body">
        @php
          $maks = max(1, ...array_values($agregat['status']));
          $warna = ['Shift Pagi' => 'P', 'Shift Malam' => 'M', 'Overshift' => 'O', 'Off' => 'o', 'Cuti' => 'c'];
        @endphp
        @foreach ($statusTersedia as $s)
          @php $v = $agregat['status'][$s] ?? 0; @endphp
          <div class="rkl-bar-row">
            <span class="lbl">{{ $s }}</span>
            <span class="rkl-bar-track">
              <span class="rkl-bar-fill rkl-fill-{{ $warna[$s] ?? 'o' }}" style="width: {{ max(1, (int) round($v / $maks * 100)) }}%"></span>
            </span>
            <span class="val">{{ number_format($v, 0, ',', '.') }} ({{ $pct($v) }})</span>
          </div>
        @endforeach

        <div class="mt-16 pt-12 border-top text-sm text-secondary-light">
          <span class="rk-legend-dot rk-d-P"></span>P Pagi
          <span class="rk-legend-dot rk-d-M ms-12"></span>M Malam
          <span class="rk-legend-dot rk-d-o ms-12"></span>o Off (1&ndash;{{ $ambangCuti }} hr)
          <span class="rk-legend-dot rk-d-c ms-12"></span>c Cuti (&gt;{{ $ambangCuti }} hr)
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-7">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="text-lg mb-0">Rekap per Site</h6>
        <a href="{{ route('dms.roster-compliance.wajib-cuti', request()->query()) }}" class="btn btn-sm btn-danger-600">
          <iconify-icon icon="solar:download-minimalistic-outline" class="align-middle me-1"></iconify-icon>Wajib cuti (CSV)
        </a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table bordered-table mb-0 rk-param-table">
            <thead>
              <tr>
                <th>Site</th>
                <th class="text-center">Total</th>
                <th class="text-center">Pelanggaran (YTD)</th>
                <th class="text-center">Wajib cuti (saat ini)</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($perSite as $s)
                <tr>
                  <td><b>{{ $s['site'] }}</b></td>
                  <td class="text-center rkl-num">{{ number_format($s['total'], 0, ',', '.') }}</td>
                  <td class="text-center rkl-num text-danger-600 fw-semibold">{{ number_format($s['pelanggaran'], 0, ',', '.') }}</td>
                  <td class="text-center rkl-num fw-semibold" style="color:var(--danger-main,#ef4a00)">{{ number_format($s['wajib_cuti'], 0, ',', '.') }}</td>
                </tr>
              @endforeach
              <tr style="border-top:2px solid var(--neutral-400,#9ca3af)">
                <td><b>TOTAL</b></td>
                <td class="text-center rkl-num"><b>{{ number_format($agregat['total'], 0, ',', '.') }}</b></td>
                <td class="text-center rkl-num text-danger-600 fw-bold">{{ number_format($agregat['pelanggaran'], 0, ',', '.') }}</td>
                <td class="text-center rkl-num fw-bold" style="color:var(--danger-main,#ef4a00)">{{ number_format($agregat['wajib_cuti'], 0, ',', '.') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ================================ FILTER ================================ --}}
<div class="card radius-8 border mb-24">
  <div class="card-body py-12">
    <form method="GET" action="{{ route('dms.roster-compliance') }}">
      <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
      <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
      <div class="row g-2 align-items-center">
        <div class="col-lg-3">
          <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm"
                 placeholder="Cari nama / SID / PT / jabatan&hellip;">
        </div>
        <div class="col-lg-2">
          <select name="pt" class="form-select form-select-sm">
            <option value="">Semua perusahaan</option>
            @foreach ($perPt as $p)
              <option value="{{ $p['kode'] }}" @selected($filters['pt'] === $p['kode'])>
                {{ $p['perusahaan'] }} ({{ number_format($p['total'], 0, ',', '.') }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-2">
          <select name="site" class="form-select form-select-sm">
            <option value="">Semua site</option>
            @foreach ($siteTersedia as $s)
              <option value="{{ $s }}" @selected($filters['site'] === $s)>{{ $s }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-2">
          <select name="kategori" class="form-select form-select-sm">
            <option value="">Semua kategori jabatan</option>
            @foreach ($kategoriTersedia as $k)
              <option value="{{ $k }}" @selected($filters['kategori'] === $k)>{{ $k }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-2">
          <select name="status" class="form-select form-select-sm">
            <option value="">Semua status kini</option>
            @foreach ($statusTersedia as $s)
              <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ $s }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-1">
          <select name="roster" class="form-select form-select-sm">
            <option value="">Roster</option>
            @foreach ($rosterTersedia as $r)
              <option value="{{ $r }}" @selected($filters['roster'] === (string) $r)>ke-{{ $r }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-lg-2">
          <select name="note" class="form-select form-select-sm">
            <option value="">Semua catatan</option>
            <option value="pel" @selected($filters['note'] === 'pel')>Ada pelanggaran (merah)</option>
            <option value="map" @selected($filters['note'] === 'map')>Tidak sesuai mapping (kuning)</option>
            <option value="wajib" @selected($filters['note'] === 'wajib')>Wajib cuti (saat ini)</option>
          </select>
        </div>
        <div class="col-lg-2">
          <select name="periode" class="form-select form-select-sm">
            <option value="">Periode: YTD penuh</option>
            <option value="kuartal" @selected($filters['periode'] === 'kuartal')>Kuartal</option>
            <option value="bulan" @selected($filters['periode'] === 'bulan')>Bulan</option>
            <option value="minggu" @selected($filters['periode'] === 'minggu')>Minggu</option>
            <option value="custom" @selected($filters['periode'] === 'custom')>Rentang tanggal</option>
          </select>
        </div>
        <div class="col-lg-1">
          <select name="nilai" class="form-select form-select-sm">
            <option value="">—</option>
            @if ($filters['periode'] === 'bulan')
              @foreach ($periode['bulan'] as $b)
                <option value="{{ $b }}" @selected($filters['nilai'] === (string) $b)>
                  {{ \Carbon\Carbon::create($tahun, $b, 1)->translatedFormat('M') }}
                </option>
              @endforeach
            @elseif ($filters['periode'] === 'minggu')
              @foreach ($periode['minggu'] as $w)
                <option value="{{ $w }}" @selected($filters['nilai'] === (string) $w)>W{{ $w }}</option>
              @endforeach
            @else
              @for ($q = 1; $q <= 4; $q++)
                <option value="{{ $q }}" @selected($filters['nilai'] === (string) $q)>Q{{ $q }}</option>
              @endfor
            @endif
          </select>
        </div>
        <div class="col-lg-2">
          <input type="date" name="dari" value="{{ $filters['dari'] }}" class="form-control form-control-sm">
        </div>
        <div class="col-lg-2">
          <input type="date" name="sampai" value="{{ $filters['sampai'] }}" class="form-control form-control-sm">
        </div>
        <div class="col-lg-3 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-primary-600 flex-grow-1">Terapkan</button>
          <a href="{{ route('dms.roster-compliance') }}" class="btn btn-sm btn-outline-neutral-600">Reset</a>
        </div>
      </div>
      <div class="text-secondary-light text-sm mt-8">
        Periode aktif: <b>{{ $periode['label'] }}</b>.
        Kolom <i>On-site maks</i> &amp; <i>Cuti min</i> tetap YTD (1 Jan s/d {{ \Carbon\Carbon::parse($meta['hari_terakhir'])->translatedFormat('d M') }}), tidak terpengaruh periode.
      </div>
    </form>
  </div>
</div>

{{-- ========================= TABEL + PANEL DETAIL ========================= --}}
<div class="row gy-4">
  <div class="col-xxl-8">
    <div class="card h-100 radius-8 border rk-master-card">
      <div class="card-header border-bottom bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="text-lg mb-0">Daftar Karyawan</h6>
        <span class="text-secondary-light text-sm">
          {{ number_format($paginasi['total_baris'], 0, ',', '.') }} baris &middot; klik baris untuk rincian harian
        </span>
      </div>
      <div class="card-body p-0 d-flex flex-column">
        <div class="table-responsive rk-table-scroll">
          <table class="table bordered-table mb-0 rk-table">
            <thead>
              <tr>
                <th>#</th>
                <th><a class="rkl-sortlink" href="{{ $sortUrl('sid') }}">SID <span class="ar">{{ $sortArrow('sid') }}</span></a></th>
                <th class="rk-col-name"><a class="rkl-sortlink" href="{{ $sortUrl('nama') }}">Karyawan <span class="ar">{{ $sortArrow('nama') }}</span></a></th>
                <th><a class="rkl-sortlink" href="{{ $sortUrl('site') }}">Site <span class="ar">{{ $sortArrow('site') }}</span></a></th>
                <th class="text-center"><a class="rkl-sortlink" href="{{ $sortUrl('roster') }}">Roster <span class="ar">{{ $sortArrow('roster') }}</span></a></th>
                <th class="text-center"><a class="rkl-sortlink" href="{{ $sortUrl('onNoOff') }}">Shift maks <span class="ar">{{ $sortArrow('onNoOff') }}</span></a></th>
                <th class="text-center"><a class="rkl-sortlink" href="{{ $sortUrl('onAll') }}">On-site maks <span class="ar">{{ $sortArrow('onAll') }}</span></a></th>
                <th class="text-center"><a class="rkl-sortlink" href="{{ $sortUrl('cutiMin') }}">Cuti min <span class="ar">{{ $sortArrow('cutiMin') }}</span></a></th>
                <th><a class="rkl-sortlink" href="{{ $sortUrl('status') }}">Status <span class="ar">{{ $sortArrow('status') }}</span></a></th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody id="rklBody">
              @forelse ($baris as $i => $r)
                @php
                  $badShift = ! $r['longgar'] && $r['onNoOff'] > ($ambang['kerja_beruntun'] ?? 13);
                  $badOn = ! $r['longgar'] && $r['onAll'] > $ambangReg2;
                  $badCuti = ! $r['longgar'] && $r['cutiMin'] > 0 && $r['cutiMin'] < $ambangReg3;
                  $pill = [
                    'Shift Pagi' => 'c-ShiftPagi', 'Shift Malam' => 'c-ShiftMalam',
                    'Overshift' => 'c-Overshift', 'Off' => 'c-Off', 'Cuti' => 'c-Cuti',
                  ][$r['status']] ?? 'c-Off';
                @endphp
                <tr data-sid="{{ $r['sid'] }}">
                  <td class="rkl-num">{{ $paginasi['dari'] + $i }}</td>
                  <td class="text-sm" style="font-family:monospace">{{ $r['sid'] }}</td>
                  <td class="rk-col-name">
                    <span class="rk-name" title="{{ $r['nama'] }}">{{ $r['nama'] }}</span>
                    <span class="rk-sub" title="{{ $r['jabatan'] }} · {{ $r['perusahaan'] }}">
                      {{ $r['kategori'] }} &middot; {{ $r['kode_pt'] }}
                      @if ($r['longgar'])
                        <span class="text-success-600">&middot; longgar</span>
                      @endif
                    </span>
                  </td>
                  <td class="text-sm">{{ $r['site'] !== '' ? $r['site'] : '—' }}</td>
                  <td class="text-center rkl-num">{{ $r['roster'] }}</td>
                  <td class="text-center rkl-num {{ $badShift ? 'rkl-cellbad' : '' }}">
                    @if ($r['onNoOff'] > 0)
                      {{ $badShift ? '⚠ ' : '' }}{{ $r['onNoOff'] }} hr
                      <span class="rkl-sub">{{ $r['rentang_onNoOff'] ?? '' }}</span>
                    @else
                      <span class="text-secondary-light">—</span>
                    @endif
                  </td>
                  <td class="text-center rkl-num {{ $badOn ? 'rkl-cellbad' : '' }}">
                    @if ($r['onAll'] > 0)
                      {{ $badOn ? '⚠ ' : '' }}{{ $r['onAll'] }} hr
                      <span class="rkl-sub">roster ke-{{ $r['onAllR'] }} &middot; {{ $r['rentang_onAll'] ?? '' }}</span>
                      @if ($r['wajib'])
                        <span class="rkl-sub fw-bold" style="color:var(--danger-main,#ef4a00)">⚠ wajib cuti — {{ $r['onCur'] }} hr berjalan</span>
                      @endif
                    @else
                      <span class="text-secondary-light">—</span>
                    @endif
                  </td>
                  <td class="text-center rkl-num {{ $badCuti ? 'rkl-cellbad' : '' }}">
                    @if ($r['cutiMin'] > 0)
                      {{ $badCuti ? '⚠ ' : '' }}{{ $r['cutiMin'] }} hr
                      <span class="rkl-sub">roster ke-{{ $r['cutiMinR'] }} &middot; {{ $r['rentang_cutiMin'] ?? '' }}</span>
                    @else
                      <span class="text-secondary-light">—</span>
                    @endif
                  </td>
                  <td><span class="pill {{ $pill }}">{{ $r['status'] }}</span></td>
                  <td class="text-sm">
                    @if ($r['merah'])
                      <span class="rk-flag-red d-block">⚠ Pelanggaran regulasi</span>
                    @endif
                    @if ($r['kuning'])
                      <span class="rk-flag-yel d-block">Tidak sesuai mapping</span>
                    @endif
                    @if (! $r['merah'] && ! $r['kuning'])
                      <span class="text-secondary-light">—</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="10" class="text-center text-secondary-light py-48">
                    Tidak ada karyawan yang cocok dengan filter ini.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center justify-content-between bg-transparent flex-wrap gap-2">
        <span class="text-secondary-light text-sm">
          Hal {{ $paginasi['halaman'] }}/{{ $paginasi['total_halaman'] }}
          &middot; menampilkan {{ number_format($paginasi['dari'], 0, ',', '.') }}&ndash;{{ number_format($paginasi['sampai'], 0, ',', '.') }}
        </span>
        <div class="d-flex gap-2">
          @if ($paginasi['halaman'] > 1)
            <a href="{{ request()->fullUrlWithQuery(['page' => $paginasi['halaman'] - 1]) }}" class="btn btn-sm btn-outline-neutral-600">&laquo; Sebelumnya</a>
          @else
            <button class="btn btn-sm btn-outline-neutral-600" disabled>&laquo; Sebelumnya</button>
          @endif
          @if ($paginasi['halaman'] < $paginasi['total_halaman'])
            <a href="{{ request()->fullUrlWithQuery(['page' => $paginasi['halaman'] + 1]) }}" class="btn btn-sm btn-outline-neutral-600">Berikutnya &raquo;</a>
          @else
            <button class="btn btn-sm btn-outline-neutral-600" disabled>Berikutnya &raquo;</button>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-4">
    <div class="card h-100 radius-8 border">
      <div class="card-header border-bottom bg-transparent">
        <h6 class="text-lg mb-0">Rincian Harian</h6>
        <span class="text-secondary-light text-sm">Gate, jam scan, dan flag per hari</span>
      </div>
      <div class="card-body" id="rklDetail">
        <div class="rk-detail-empty">Pilih satu baris karyawan untuk melihat timeline hariannya.</div>
      </div>
    </div>
  </div>
</div>

{{-- ============================ RULE & PARAMETER ============================ --}}
<div class="row gy-4 mt-4">
  <div class="col-xxl-6">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <h6 class="text-lg mb-0">Rule yang Diterapkan</h6>
        <span class="text-secondary-light text-sm">Ambang dari <code>config/dms_roster.php</code></span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table bordered-table mb-0 rkl-rule-table">
            <thead>
              <tr><th>Kode</th><th>Rule</th><th>Kondisi aktual</th><th>Longgar</th></tr>
            </thead>
            <tbody>
              <tr>
                <td><span class="rkl-badge-red">REG-1</span></td>
                <td>Kerja beruntun</td>
                <td>Run shift P/M tanpa off <b>&gt; {{ $ambang['kerja_beruntun'] ?? 13 }}</b> hari (≥ {{ $ambangReg1 }} hr)</td>
                <td>Dikecualikan</td>
              </tr>
              <tr>
                <td><span class="rkl-badge-red">REG-2</span></td>
                <td>On-site belum cuti</td>
                <td>Run on-site (P/M/off pendek) <b>&gt; {{ $ambangReg2 }}</b> hari</td>
                <td>Dikecualikan</td>
              </tr>
              <tr>
                <td><span class="rkl-badge-red">REG-3</span></td>
                <td>Cuti terlalu pendek</td>
                <td>Blok cuti <b>&lt; {{ $ambangReg3 }}</b> hari</td>
                <td>Dikecualikan</td>
              </tr>
              <tr>
                <td><span class="rkl-badge-yel">MAP-1</span></td>
                <td>Melebihi blok roster</td>
                <td>Run kerja &gt; (blok roster PT) tapi belum kena REG-1</td>
                <td>Dikecualikan</td>
              </tr>
              <tr>
                <td><span class="rkl-badge-yel">MAP-2</span></td>
                <td>Wajib off saat ganti shift</td>
                <td>PAMA: pergantian Pagi↔Malam tanpa off di antaranya</td>
                <td>Tetap berlaku</td>
              </tr>
              <tr>
                <td><span class="rkl-badge-yel">MAP-3</span></td>
                <td>Urutan shift terbalik</td>
                <td>Non-PAMA: shift Pagi muncul setelah Malam dalam satu run</td>
                <td>Tetap berlaku</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-16 py-12 text-secondary-light" style="font-size:11.5px">
          Ambang REG di atas mereplikasi <b>kode</b> referensi safety-roster, bukan label UI-nya, agar angka halaman ini
          bisa diadu langsung dengan snapshot statis. Kategori <b>Operator Transportasi Massal</b> dan <b>Mekanik</b>
          dibebaskan dari REG-1/2/3 dan MAP-1.
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-6">
    <div class="card radius-8 border h-100">
      <div class="card-header border-bottom bg-transparent">
        <h6 class="text-lg mb-0">Parameter per Kontraktor</h6>
        <span class="text-secondary-light text-sm">Blok roster hanya memengaruhi flag kuning MAP-1</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="max-height:340px;overflow-y:auto">
          <table class="table bordered-table mb-0 rkl-rule-table">
            <thead>
              <tr><th>PT</th><th>Roster</th><th>Pola shift</th><th class="text-center">Blok</th><th class="text-center">Karyawan</th></tr>
            </thead>
            <tbody>
              @php $daftarPt = config('dms_roster.perusahaan', []); @endphp
              @foreach ($perPt as $p)
                @php
                  $cfg = $daftarPt[$p['perusahaan']] ?? null;
                  $thr = (int) ($cfg['thr'] ?? config('dms_roster.thr_default', 7));
                @endphp
                <tr>
                  <td>
                    <b>{{ $p['kode'] }}</b>
                    <span class="rkl-sub">{{ $p['perusahaan'] }}</span>
                  </td>
                  <td>{{ $cfg['roster'] ?? '—' }}</td>
                  <td>
                    {{ $cfg['shift'] ?? '—' }}
                    @if (! empty($cfg['shift_detail']))
                      <span class="rkl-sub">{{ $cfg['shift_detail'] }}</span>
                    @endif
                  </td>
                  <td class="text-center rkl-num">{{ $thr - 1 }} hr</td>
                  <td class="text-center rkl-num">{{ number_format($p['total'], 0, ',', '.') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="px-16 py-12 text-secondary-light" style="font-size:11.5px">
          PT tanpa parameter khusus memakai default blok {{ (int) config('dms_roster.thr_default', 7) - 1 }} hari
          dan rule urutan shift <i>block</i>.
        </div>
      </div>
    </div>
  </div>
</div>
@endif
@endsection

@section('scripts')
<script>
(function () {
  const body = document.getElementById('rklBody');
  const panel = document.getElementById('rklDetail');
  if (!body || !panel) return;

  const detailUrl = @json(route('dms.roster-compliance.detail', ['sid' => '__SID__']));
  const tahun = @json($tahun);
  const KODE_CLASS = { P: 'rkl-d-P', M: 'rkl-d-M', o: 'rkl-d-o', c: 'rkl-d-c' };
  const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

  const esc = (s) => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');

  let aktif = null;

  function stat(label, nilai, bad) {
    return '<div class="rk-stat-mini' + (bad ? ' is-bad' : '') + '"><div class="k">' + esc(label) +
      '</div><div class="v">' + esc(nilai) + '</div></div>';
  }

  /** Strip satu kotak per hari + penggaris bulan di atasnya. */
  function strip(hari) {
    let ruler = '';
    let cells = '';
    let bulanSebelum = '';

    hari.forEach(function (h) {
      const bulan = h.iso.slice(0, 7);
      const ganti = bulan !== bulanSebelum;
      const kuartal = ganti && (parseInt(h.iso.slice(5, 7), 10) - 1) % 3 === 0;
      const sep = ganti ? (kuartal ? ' sep-q' : ' sep-mo') : '';
      bulanSebelum = bulan;

      ruler += '<i class="' + sep.trim() + '">' +
        (ganti ? '<b>' + BULAN[parseInt(h.iso.slice(5, 7), 10) - 1] + '</b>' : '') + '</i>';

      const flag = h.merah ? ' rkl-d-red' : (h.kuning ? ' rkl-d-yel' : '');
      let judul = h.label + ' — ' + h.kode_label + ' hari ke-' + h.hari_ke;
      if (h.jam_in) judul += '\nCheck-in ' + h.jam_in + (h.gate_in ? ' @ ' + h.gate_in : '');
      if (h.jam_out) judul += '\nCheck-out ' + h.jam_out + (h.gate_out ? ' @ ' + h.gate_out : '');
      if (h.durasi) judul += '\nDurasi ' + h.durasi;
      if (h.merah) judul += '\n⚠ ' + h.merah;
      else if (h.kuning) judul += '\n⚠ ' + h.kuning;

      cells += '<i class="' + (KODE_CLASS[h.kode] || 'rkl-d-o') + flag + sep +
        '" title="' + esc(judul) + '"></i>';
    });

    return '<div class="rkl-heat-wrap"><div class="rkl-heat">' +
      '<div class="rkl-heat-ruler">' + ruler + '</div>' +
      '<div class="rkl-heat-strip">' + cells + '</div></div></div>';
  }

  function render(d) {
    const flagged = d.hari.filter(function (h) { return h.merah || h.kuning; });
    // Hanya 40 hari ter-flag terakhir supaya panel tidak membengkak.
    const tampil = flagged.slice(-40).reverse();

    let html = '<div class="mb-12">' +
      '<div class="fw-semibold">' + esc(d.nama) + '</div>' +
      '<div class="text-secondary-light text-sm">' + esc(d.sid) + ' &middot; ' + esc(d.jabatan || '—') + '</div>' +
      '<div class="text-secondary-light text-sm">' + esc(d.perusahaan) + (d.site ? ' &middot; ' + esc(d.site) : '') + '</div>' +
      (d.longgar ? '<span class="text-success-600 text-sm">Kategori regulasi longgar — REG-1/2/3 &amp; MAP-1 dikecualikan</span>' : '') +
      '</div>';

    html += '<div class="rkl-detail-metrics mb-12">' +
      stat('Status kini', d.status, false) +
      stat('Roster ke-', d.roster, false) +
      stat('On-site berjalan', d.onCur + ' hr', d.wajibCuti) +
      stat('Shift kerja maks', d.onNoOff + ' hr', false) +
      stat('On-site maks YTD', d.onAll + ' hr', false) +
      stat('Cuti min YTD', (d.cutiMin || 0) + ' hr', false) +
      '</div>';

    if (d.wajibCuti) {
      html += '<div class="alert-danger bg-danger-100 text-danger-600 border-danger-100 border px-12 py-8 rounded-8 text-sm mb-12">' +
        '⚠ Wajib segera dicutikan — on-site berjalan ' + d.onCur + ' hari.</div>';
    }

    if (d.pelanggaran.length) {
      html += '<div class="mb-12"><div class="text-sm fw-semibold mb-6">Rule terpicu (YTD)</div>';
      d.pelanggaran.forEach(function (p) {
        html += '<div class="rkl-flagday is-red">' + esc(p) + '</div>';
      });
      html += '</div>';
    }

    html += '<div class="text-sm fw-semibold mb-6">Timeline harian ' + tahun + '</div>' + strip(d.hari);

    html += '<div class="text-sm fw-semibold mt-12 mb-6">Hari ter-flag' +
      (flagged.length > tampil.length ? ' (' + tampil.length + ' terbaru dari ' + flagged.length + ')' : '') +
      '</div><div class="rk-flaglist-scroll">';
    if (!tampil.length) {
      html += '<div class="text-secondary-light text-sm">Tidak ada hari ter-flag.</div>';
    } else {
      tampil.forEach(function (h) {
        html += '<div class="rkl-flagday ' + (h.merah ? 'is-red' : 'is-yel') + '">' +
          '<b>' + esc(h.label) + '</b> — ' + esc(h.merah || h.kuning) + '</div>';
      });
    }
    html += '</div>';

    panel.innerHTML = html;
  }

  body.addEventListener('click', function (e) {
    const tr = e.target.closest('tr[data-sid]');
    if (!tr) return;

    if (aktif) aktif.classList.remove('is-selected');
    tr.classList.add('is-selected');
    aktif = tr;

    panel.innerHTML = '<div class="rk-detail-empty"><div class="spinner-border text-primary-600" role="status"></div>' +
      '<div class="mt-12">Memuat rincian&hellip;</div></div>';

    const url = detailUrl.replace('__SID__', encodeURIComponent(tr.dataset.sid)) + '?tahun=' + encodeURIComponent(tahun);

    fetch(url, { headers: { Accept: 'application/json' } })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(render)
      .catch(function (err) {
        panel.innerHTML = '<div class="alert-danger bg-danger-100 text-danger-600 border-danger-100 border ' +
          'px-12 py-8 rounded-8 text-sm">Gagal memuat rincian: ' + esc(err.message) + '</div>';
      });
  });
})();
</script>
@endsection
