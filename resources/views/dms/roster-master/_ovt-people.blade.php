{{-- Tab People: direktori karyawan aktif + status roster. Dimuat lewat AJAX (dms.master-roster.overview.people). --}}
@php $angka = static fn (int $n): string => number_format($n, 0, ',', '.'); @endphp
<div class="rov-kartu">
  <div class="rov-kartu__kepala">
    <div>
      <h6 class="rov-kartu__judul">People</h6>
      <p class="rov-kartu__sub">Karyawan aktif beserta departemen, jabatan, dan status roster masing-masing.</p>
    </div>
    <form class="d-flex flex-wrap align-items-end gap-12 ovt-filter" data-ovt-form="people">
      @include('dms.roster-master._ovt-filter')
      <div>
        <label class="form-label text-sm fw-medium mb-4">Cari</label>
        <input type="search" name="q" value="{{ $cari }}" class="form-control form-control-sm" placeholder="SID, nama, departemen…">
      </div>
      <button type="submit" class="btn btn-sm btn-primary-600 px-16">Terapkan</button>
    </form>
  </div>

  @if (! $tersedia)
    <div class="p-24 text-center text-secondary-light">Data karyawan aktif tidak bisa dibaca: ClickHouse OBDS (koneksi <code>clickhouse_obds</code>) tidak terjangkau dari server ini.</div>
  @else
    <div class="table-responsive">
      <table class="table mb-0 ovt-tabel">
        <thead>
          <tr>
            <th class="ps-24">Karyawan</th>
            <th>Perusahaan · Site</th>
            <th>Departemen</th>
            <th>Jabatan</th>
            <th>Permit</th>
            <th class="pe-24">Roster</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($baris as $k)
            @php
              $st = $statusSid[$k['sid']] ?? null;
              [$kelas, $teks] = match (true) {
                  $st === null => ['is-belum', 'Belum ada'],
                  $st['otomatis'] => ['is-oto', 'Otomatis'],
                  $st['disetujui'] => ['is-sudah', 'Disetujui'],
                  default => ['is-menunggu', 'Menunggu approval'],
              };
            @endphp
            <tr>
              <td class="ps-24">
                <div class="fw-semibold text-primary-light">{{ $k['nama'] ?: '—' }}</div>
                <div class="text-xs text-secondary-light">{{ $k['sid'] }}@if ($k['mulai']) · mulai {{ \Carbon\CarbonImmutable::parse($k['mulai'])->format('d/m/Y') }}@endif</div>
              </td>
              <td>
                <div class="text-sm">{{ $k['perusahaan'] }}</div>
                <div class="text-xs text-secondary-light">{{ $k['site'] !== '' ? $k['site'] : 'Tanpa site' }}</div>
              </td>
              <td class="text-sm">{{ $k['departemen'] !== '' ? $k['departemen'] : '—' }}@if ($k['divisi'] !== '')<div class="text-xs text-secondary-light">{{ $k['divisi'] }}</div>@endif</td>
              <td class="text-sm">{{ $k['fungsional'] !== '' ? $k['fungsional'] : ($k['jabatan'] ?: '—') }}@if ($k['fungsional'] !== '' && $k['jabatan'] !== '')<div class="text-xs text-secondary-light">{{ $k['jabatan'] }}</div>@endif</td>
              <td>
                @if ($k['permit'] !== '')
                  <span class="ovt-pil {{ strtoupper($k['permit']) === 'PASSED' ? 'is-sudah' : 'is-belum' }}">{{ $k['permit'] }}</span>
                @else
                  <span class="text-secondary-light">—</span>
                @endif
              </td>
              <td class="pe-24"><span class="ovt-pil {{ $kelas }}">{{ $teks }}</span></td>
            </tr>
          @empty
            <tr><td colspan="6" class="p-24 text-center text-secondary-light">Tidak ada karyawan yang cocok dengan filter ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @php $jumlahHal = max(1, (int) ceil($total / $perHalaman)); @endphp
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-12 px-24 py-16 ovt-kaki">
      <span class="text-sm text-secondary-light">{{ $angka($total === 0 ? 0 : ($halaman - 1) * $perHalaman + 1) }}–{{ $angka(min($total, $halaman * $perHalaman)) }} dari {{ $angka($total) }} karyawan</span>
      @if ($jumlahHal > 1)
        <div class="d-flex gap-8">
          @if ($halaman > 1)
            <button type="button" class="btn btn-sm btn-outline-neutral" data-ovt-hal="{{ $halaman - 1 }}">‹ Sebelumnya</button>
          @endif
          <span class="text-sm align-self-center">Halaman {{ $halaman }} / {{ $jumlahHal }}</span>
          @if ($halaman < $jumlahHal)
            <button type="button" class="btn btn-sm btn-outline-neutral" data-ovt-hal="{{ $halaman + 1 }}">Berikutnya ›</button>
          @endif
        </div>
      @endif
    </div>
  @endif
</div>
