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
      <p class="rov-kartu__sub">Susunan karyawan aktif per perusahaan → site → departemen → tingkat jabatan ({{ $angka($total) }} orang{{ ($dept ?? '') !== '' ? ' · departemen '.(($dept === '-') ? 'tanpa departemen' : $dept) : '' }}).
        {{ $denganOrang ? '' : 'Pilih satu perusahaan untuk melihat nama orangnya.' }}
      </p>
    </div>
    <form class="d-flex flex-wrap align-items-end gap-12 ovt-filter" data-ovt-form="org">
      @include('dms.roster-master._ovt-filter')
      <button type="submit" class="btn btn-sm btn-primary-600 px-16">Terapkan</button>
    </form>
  </div>

  @if (! $tersedia)
    <div class="p-24 text-center text-secondary-light">Data karyawan aktif tidak bisa dibaca: ClickHouse OBDS tidak terjangkau dari server ini.</div>
  @elseif ($pohon === [])
    <div class="p-24 text-center text-secondary-light">Tidak ada karyawan untuk filter ini.</div>
  @else
    @if ($denganOrang)
      @php $batasAnggota = 8; $batasKepala = 3; @endphp
      <div class="oc-alat px-24 pt-16">
        <div class="oc-mode" role="group" aria-label="Tampilan org chart">
          <button type="button" class="is-aktif" data-oc-mode="bagan"><iconify-icon icon="solar:structure-linear"></iconify-icon>Bagan</button>
          <button type="button" data-oc-mode="daftar"><iconify-icon icon="solar:list-linear"></iconify-icon>Daftar</button>
        </div>
        <div class="oc-zoom" data-oc-zoom-alat>
          <button type="button" data-oc-zoom="-1" aria-label="Perkecil">−</button>
          <span data-oc-zoom-nilai>100%</span>
          <button type="button" data-oc-zoom="1" aria-label="Perbesar">+</button>
          <button type="button" data-oc-zoom="0" class="oc-zoom__pas">Pas layar</button>
        </div>
      </div>

      {{-- Bagan: PT → site → departemen; kartu teratas = jabatan tertinggi di departemen, anggota berjajar ke bawah. --}}
      <div class="oc-kanvas" data-oc-kanvas>
        <div class="oc-pohon" data-oc-pohon>
          @foreach ($pohon as $p)
            <ul>
              <li>
                <div class="oc-simpul is-pt"><iconify-icon icon="solar:buildings-2-bold"></iconify-icon><div><div class="oc-simpul__nama">{{ $p['nama'] }}</div><div class="oc-simpul__jml">{{ $angka($p['jumlah']) }} karyawan</div></div></div>
                <ul>
                  @foreach ($p['site'] as $s)
                    <li>
                      <div class="oc-simpul is-site"><iconify-icon icon="solar:map-point-bold"></iconify-icon><div><div class="oc-simpul__nama">{{ $s['nama'] === '-' ? 'Tanpa site' : $s['nama'] }}</div><div class="oc-simpul__jml">{{ $angka($s['jumlah']) }} orang · {{ count($s['departemen']) }} departemen</div></div></div>
                      <ul>
                        @foreach ($s['departemen'] as $d)
                          @php
                            $atas = $d['jabatan'][0] ?? ['nama' => '', 'orang' => []];
                            $kepala = array_slice($atas['orang'], 0, $batasKepala);
                            $anggota = [];
                            foreach (array_slice($atas['orang'], $batasKepala) as $o) { $anggota[] = [$o, $atas['nama']]; }
                            foreach (array_slice($d['jabatan'], 1) as $j) { foreach ($j['orang'] as $o) { $anggota[] = [$o, $j['nama']]; } }
                          @endphp
                          <li>
                            <div class="oc-dept">
                              <div class="oc-dept__judul">{{ $d['nama'] }} <span>{{ $angka($d['jumlah']) }}</span></div>
                              @foreach ($kepala as $o)
                                @include('dms.roster-master._ovt-org-kartu', ['o' => $o, 'tingkat' => $atas['nama']])
                              @endforeach
                              @if ($anggota !== [])
                                <div class="oc-anggota">
                                  @foreach (array_slice($anggota, 0, $batasAnggota) as [$o, $tingkat])
                                    @include('dms.roster-master._ovt-org-kartu', ['o' => $o, 'tingkat' => $tingkat, 'kecil' => true])
                                  @endforeach
                                  @if (count($anggota) > $batasAnggota)
                                    <div class="oc-lain">+{{ $angka(count($anggota) - $batasAnggota) }} lainnya</div>
                                  @endif
                                </div>
                              @endif
                            </div>
                          </li>
                        @endforeach
                      </ul>
                    </li>
                  @endforeach
                </ul>
              </li>
            </ul>
          @endforeach
        </div>
      </div>
    @endif

    <div class="ovt-org p-24" @if ($denganOrang) data-oc-daftar hidden @endif>
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
