{{--
  Tabel jadwal per karyawan bergaya template Excel "ROSTER KERJA KARYAWAN".
  Dipakai halaman Detail roster & Overview Roster. Butuh _grid-css.

  $karyawanGrid  list karyawan: sid, nama, perusahaan, jabatan, site, jam_awal, jam_akhir,
                 jadwal[Y-m-d] = [kode, masuk, keluar, menit]
  $tanggal       list CarbonImmutable — kolom hari
  $nomorAwal     nomor urut sebelum baris pertama (paginasi)
--}}
@php
  $hariSingkat = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
  $bulanSingkat = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  $nomorAwal = $nomorAwal ?? 0;
@endphp
      @php
        $kelasKode = static fn (?string $kode): string => $kode === null ? 'rm-kosong'
            : (isset(\App\Services\Dms\Roster\DmsRosterMasterService::KODE[$kode]) || in_array($kode, ['DS', 'NS'], true) ? 'rm-'.$kode : 'rm-lain');
      @endphp
      <table class="rm-xl">
        <thead>
          <tr>
            <th rowspan="3" class="rm-h-id rm-s1 rm-hp-sembunyi">No</th>
            <th rowspan="3" class="rm-h-id rm-s2 rm-hp-sembunyi">SID</th>
            <th rowspan="3" class="rm-h-id rm-s3">Nama Karyawan</th>
            <th rowspan="3" class="rm-h-id rm-opsional rm-w-pt">Perusahaan</th>
            <th rowspan="3" class="rm-h-id rm-opsional rm-w-jab">Jabatan</th>
            <th rowspan="3" class="rm-h-id rm-opsional rm-w-kecil">Site</th>
            <th rowspan="3" class="rm-h-id rm-opsional rm-w-kecil">Jam Kerja Awal</th>
            <th rowspan="3" class="rm-h-id rm-opsional rm-w-kecil">Jam Kerja Akhir</th>
            @foreach ($tanggal as $t)
              <th class="rm-h-tgl {{ $t->dayOfWeek === 0 ? 'rm-minggu' : '' }} {{ $t->day === 1 && ! $loop->first ? 'rm-bulan' : '' }}" title="{{ $t->format('d/m/Y') }}">{{ $t->format('d') }}</th>
            @endforeach
          </tr>
          <tr>
            @foreach ($tanggal as $t)
              <th class="rm-h-hari {{ $t->dayOfWeek === 0 ? 'rm-minggu' : '' }} {{ $t->day === 1 && ! $loop->first ? 'rm-bulan' : '' }}">{{ $hariSingkat[$t->dayOfWeek] }}</th>
            @endforeach
          </tr>
          <tr>
            @foreach ($tanggal as $t)
              <th class="rm-h-bln {{ $t->dayOfWeek === 0 ? 'rm-minggu' : '' }} {{ $t->day === 1 && ! $loop->first ? 'rm-bulan' : '' }}">{{ $bulanSingkat[$t->month] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @forelse ($karyawanGrid as $k)
            <tr>
              <td class="rm-c-id rm-c-no rm-s1 rm-hp-sembunyi">{{ $nomorAwal + $loop->iteration }}</td>
              <td class="rm-c-id rm-c-sid rm-s2 rm-hp-sembunyi">{{ $k['sid'] }}</td>
              <td class="rm-c-id rm-c-nama rm-s3">
                <span class="rm-nama" title="{{ $k['nama'] }}">{{ $k['nama'] ?? '—' }}</span>
                {{-- Layar sempit: kolom identitas lain disembunyikan, isinya diringkas di sini. --}}
                <span class="rm-ringkas">
                  <span class="rm-ringkas-sid">{{ $k['sid'] }} &middot; </span>{{ $k['perusahaan'] ?? '—' }} &middot; {{ $k['jabatan'] ?? '—' }} &middot; {{ $k['site'] ?? '—' }}@if ($k['jam_awal']) &middot; {{ $k['jam_awal'] }}–{{ $k['jam_akhir'] }}@endif
                </span>
              </td>
              <td class="rm-c-id rm-opsional"><span class="rm-potong rm-w-pt" title="{{ $k['perusahaan'] }}">{{ $k['perusahaan'] ?? '—' }}</span></td>
              <td class="rm-c-id rm-opsional"><span class="rm-potong rm-w-jab" title="{{ $k['jabatan'] }}">{{ $k['jabatan'] ?? '—' }}</span></td>
              <td class="rm-c-id rm-opsional">{{ $k['site'] ?? '—' }}</td>
              <td class="rm-c-id rm-c-jam rm-opsional">{{ $k['jam_awal'] ?? '' }}</td>
              <td class="rm-c-id rm-c-jam rm-opsional">{{ $k['jam_akhir'] ?? '' }}</td>
              @foreach ($tanggal as $t)
                @php
                  $h = $k['jadwal'][$t->toDateString()] ?? null;
                  $kode = $h['kode'] ?? null;
                  $jamTeks = $h && $h['masuk'] ? ' · '.$h['masuk'].'–'.$h['keluar'].' ('.round($h['menit'] / 60, 2).' jam)' : '';
                @endphp
                <td class="rm-c-kode {{ $t->day === 1 && ! $loop->first ? 'rm-bulan' : '' }}"><div class="rm-sel {{ $kelasKode($kode) }} {{ ($h['menunggu'] ?? false) ? 'rm-menunggu' : '' }}" title="{{ $t->format('d/m/Y') }} — {{ $kode ?? 'tidak dijadwalkan' }}{{ $jamTeks }}{{ ($h['menunggu'] ?? false) ? ' · roster masih menunggu approval' : '' }}">{{ $kode }}</div></td>
              @endforeach
            </tr>
          @empty
            <tr><td colspan="{{ count($tanggal) + 8 }}" class="text-secondary-light py-16">Tidak ada karyawan yang cocok.</td></tr>
          @endforelse
        </tbody>
      </table>
