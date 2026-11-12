{{-- Tab Org chart: perusahaan → site → departemen → tingkat jabatan → orang. Dimuat lewat AJAX. --}}
@php
  $angka = static fn (int $n): string => number_format($n, 0, ',', '.');
  $denganOrang = $pt !== '';
  $batasOrang = 40;
@endphp
<div class="rov-kartu">
  <div class="rov-kartu__kepala">
    <div>
      <h6 class="rov-kartu__judul">Org chart</h6>
      <p class="rov-kartu__sub">Susunan karyawan aktif per perusahaan → site → departemen → tingkat jabatan ({{ $angka($total) }} orang).
        {{ $denganOrang ? '' : 'Pilih satu perusahaan untuk melihat nama orangnya.' }}
        @if ($sumber === 'tahap8') <span class="text-warning-main">Departemen belum tersedia (ClickHouse tidak terjangkau).</span> @endif
      </p>
    </div>
    <form class="d-flex flex-wrap align-items-end gap-12 ovt-filter" data-ovt-form="org">
      @include('dms.roster-master._ovt-filter')
      <button type="submit" class="btn btn-sm btn-primary-600 px-16">Terapkan</button>
    </form>
  </div>

  @if (! $tersedia)
    <div class="p-24 text-center text-secondary-light">Daftar karyawan aktif belum tersedia.</div>
  @elseif ($pohon === [])
    <div class="p-24 text-center text-secondary-light">Tidak ada karyawan untuk filter ini.</div>
  @else
    <div class="ovt-org p-24">
      @foreach ($pohon as $p)
        <details class="ovt-node ovt-node--pt" @if ($denganOrang || count($pohon) === 1) open @endif>
          <summary><iconify-icon icon="solar:buildings-2-bold" class="ovt-node__ikon"></iconify-icon><span class="ovt-node__nama">{{ $p['nama'] }}</span><span class="ovt-node__jml">{{ $angka($p['jumlah']) }}</span></summary>
          <div class="ovt-node__anak">
            @foreach ($p['site'] as $s)
              <details class="ovt-node ovt-node--site" @if ($denganOrang && count($p['site']) <= 3) open @endif>
                <summary><iconify-icon icon="solar:map-point-bold" class="ovt-node__ikon"></iconify-icon><span class="ovt-node__nama">{{ $s['nama'] === '-' ? 'Tanpa site' : $s['nama'] }}</span><span class="ovt-node__jml">{{ $angka($s['jumlah']) }}</span></summary>
                <div class="ovt-node__anak">
                  @foreach ($s['departemen'] as $d)
                    <details class="ovt-node ovt-node--dept">
                      <summary><iconify-icon icon="solar:users-group-two-rounded-bold" class="ovt-node__ikon"></iconify-icon><span class="ovt-node__nama">{{ $d['nama'] }}</span><span class="ovt-node__jml">{{ $angka($d['jumlah']) }}</span></summary>
                      <div class="ovt-node__anak">
                        @foreach ($d['jabatan'] as $j)
                          <div class="ovt-jab">
                            <div class="ovt-jab__kepala"><span>{{ $j['nama'] }}</span><span class="ovt-node__jml">{{ $angka($j['jumlah']) }}</span></div>
                            @if ($denganOrang)
                              <div class="ovt-jab__orang">
                                @foreach (array_slice($j['orang'], 0, $batasOrang) as $o)
                                  <span class="ovt-orang" title="{{ $o['sid'] }}{{ $o['jabatan'] !== '' ? ' · '.$o['jabatan'] : '' }}">{{ $o['nama'] ?: $o['sid'] }}</span>
                                @endforeach
                                @if ($j['jumlah'] > $batasOrang)
                                  <span class="ovt-orang is-lain">+{{ $angka($j['jumlah'] - $batasOrang) }} lainnya</span>
                                @endif
                              </div>
                            @endif
                          </div>
                        @endforeach
                      </div>
                    </details>
                  @endforeach
                </div>
              </details>
            @endforeach
          </div>
        </details>
      @endforeach
    </div>
  @endif
</div>
