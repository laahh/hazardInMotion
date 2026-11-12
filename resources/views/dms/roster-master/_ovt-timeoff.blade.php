{{-- Tab Time off: hari cuti / sakit / izin dari kode roster CT, S, I (roster disetujui). Dimuat lewat AJAX.
     Tanpa perusahaan terpilih: jumlah per perusahaan saja (ringan); detail per orang setelah memilih perusahaan. --}}
@php
  $angka = static fn (int $n): string => number_format($n, 0, ',', '.');
  $label = \App\Services\Dms\Roster\DmsRosterPeopleService::LABEL_KODE;
  $hariIni = now()->toDateString();
@endphp
<div class="rov-kartu">
  <div class="rov-kartu__kepala">
    <div>
      <h6 class="rov-kartu__judul">Time off</h6>
      <p class="rov-kartu__sub">Hari cuti (CT), sakit (S), dan izin (I) dari roster yang disetujui, {{ $bulan->locale('id')->translatedFormat('F Y') }}.
        {{ $pt === '' ? 'Pilih satu perusahaan untuk melihat kalender per orang.' : '' }}</p>
    </div>
    <form class="d-flex flex-wrap align-items-end gap-12 ovt-filter" data-ovt-form="time-off">
      @include('dms.roster-master._ovt-filter')
      <div>
        <label class="form-label text-sm fw-medium mb-4">Bulan</label>
        <input type="month" name="bulan" value="{{ $bulan->format('Y-m') }}" class="form-control form-control-sm">
      </div>
      <button type="submit" class="btn btn-sm btn-primary-600 px-16">Terapkan</button>
    </form>
  </div>

  @if (! $tersedia)
    <div class="p-24 text-center text-secondary-light">Data karyawan aktif tidak bisa dibaca: ClickHouse OBDS tidak terjangkau dari server ini.</div>
  @elseif ($data === null)
    {{-- Semua perusahaan: peringkat jumlah hari tidak masuk per perusahaan (nama sesuai file roster). --}}
    @if ($perPt === [])
      <div class="p-24 text-center text-secondary-light">Tidak ada hari cuti, sakit, atau izin di roster disetujui pada bulan ini.</div>
    @else
      <div class="table-responsive">
        <table class="table mb-0 ovt-tabel">
          <thead>
            <tr>
              <th class="ps-24">Perusahaan (di roster)</th>
              <th class="text-end">Orang</th>
              @foreach ($label as $k => $l)<th class="text-end">{{ $l }} ({{ $k }})</th>@endforeach
              <th class="text-end pe-24">Total hari</th>
            </tr>
          </thead>
          <tbody>
            @foreach (array_slice($perPt, 0, 50) as $r)
              <tr>
                <td class="ps-24 text-sm">{{ $r['perusahaan'] }}</td>
                <td class="text-end">{{ $angka($r['orang']) }}</td>
                @foreach ($label as $k => $l)<td class="text-end">{{ $angka($r[$k]) }}</td>@endforeach
                <td class="text-end fw-semibold pe-24">{{ $angka($r['total']) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if (count($perPt) > 50)
        <p class="text-xs text-secondary-light px-24 py-12 mb-0">Menampilkan 50 perusahaan teratas dari {{ $angka(count($perPt)) }}.</p>
      @endif
    @endif
  @else
    @php $orang = $data['orang']; @endphp
    <div class="ovt-ringkas px-24 pt-20">
      <div class="ovt-ringkas__kartu"><span class="ovt-ringkas__angka">{{ $angka(count($orang)) }}</span><span class="ovt-ringkas__label">orang tidak masuk<br><small>dari {{ $angka($populasi) }} karyawan</small></span></div>
      @foreach ($label as $k => $l)
        <div class="ovt-ringkas__kartu is-{{ strtolower($k) }}"><span class="ovt-ringkas__angka">{{ $angka($data['total'][$k] ?? 0) }}</span><span class="ovt-ringkas__label">hari {{ strtolower($l) }}<br><small>kode {{ $k }}</small></span></div>
      @endforeach
    </div>

    @if ($orang === [])
      <div class="p-24 text-center text-secondary-light">Tidak ada hari cuti, sakit, atau izin di roster disetujui pada bulan ini untuk filter ini.</div>
    @else
      <div class="table-responsive px-24 pt-16">
        <table class="ovt-kal">
          <thead>
            <tr>
              <th class="ovt-kal__nama">Karyawan</th>
              @foreach ($tanggal as $t)
                <th class="{{ $t->isWeekend() ? 'is-libur' : '' }} {{ $t->toDateString() === $hariIni ? 'is-kini' : '' }}" title="{{ $t->locale('id')->translatedFormat('l, d F Y') }}">{{ $t->day }}</th>
              @endforeach
              <th class="ovt-kal__jml">Hari</th>
            </tr>
          </thead>
          <tbody>
            @foreach (array_slice($orang, 0, 200) as $o)
              <tr>
                <td class="ovt-kal__nama">
                  <div class="fw-semibold text-sm text-primary-light">{{ $o['nama'] ?: $o['sid'] }}</div>
                  <div class="text-xs text-secondary-light">{{ $o['sid'] }} · {{ $o['departemen'] !== '' ? $o['departemen'] : $o['perusahaan'] }}</div>
                </td>
                @foreach ($tanggal as $t)
                  @php $kode = $o['hari'][$t->toDateString()] ?? null; @endphp
                  <td class="{{ $kode ? 'is-'.strtolower($kode) : '' }} {{ $t->isWeekend() ? 'is-libur' : '' }}" @if ($kode) title="{{ $label[$kode] }} · {{ $t->format('d/m') }}" @endif>{{ $kode ?? '' }}</td>
                @endforeach
                <td class="ovt-kal__jml">{{ array_sum($o['jumlah']) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        @if (count($orang) > 200)
          <p class="text-xs text-secondary-light mt-8">Menampilkan 200 orang dengan hari tidak masuk terbanyak dari {{ $angka(count($orang)) }}. Persempit dengan filter site.</p>
        @endif
      </div>

      <div class="px-24 py-20">
        <h6 class="text-md fw-semibold mb-12">Per departemen</h6>
        <div class="table-responsive">
          <table class="table mb-0 ovt-tabel">
            <thead><tr><th>Departemen</th><th class="text-end">Orang tidak masuk</th><th class="text-end">Karyawan</th><th class="text-end">Total hari</th></tr></thead>
            <tbody>
              @foreach (array_slice($data['per_departemen'], 0, 30) as $d)
                <tr>
                  <td class="text-sm">{{ $d['nama'] }}</td>
                  <td class="text-end">{{ $angka($d['tidak_masuk']) }}</td>
                  <td class="text-end text-secondary-light">{{ $angka($d['karyawan']) }}</td>
                  <td class="text-end fw-semibold">{{ $angka($d['hari']) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  @endif
</div>
