{{-- Kartu orang di bagan Org chart. $o = ['sid','nama','jabatan'], $tingkat = nama jabatan fungsional. --}}
@php
  $nama = trim((string) ($o['nama'] ?? '')) !== '' ? $o['nama'] : $o['sid'];
  $kata = preg_split('/\s+/u', trim($nama)) ?: [];
  $inisial = mb_strtoupper(mb_substr($kata[0] ?? '', 0, 1).mb_substr($kata[1] ?? '', 0, 1));
  $warna = ['#2F5BD3', '#16712C', '#B26A00', '#7C3AED', '#C8102E', '#0E7490', '#475569'];
  $w = $warna[abs(crc32((string) $tingkat)) % count($warna)];
@endphp
<div class="oc-kartu {{ $kecil ?? false ? 'is-kecil' : '' }}" title="{{ $o['sid'] }}{{ ($o['jabatan'] ?? '') !== '' ? ' · '.$o['jabatan'] : '' }}">
  <div class="oc-kartu__kepala">
    <div class="oc-kartu__nama">{{ $nama }}</div>
    <div class="oc-kartu__jabatan">{{ $tingkat }}</div>
  </div>
  <div class="oc-kartu__isi">
    <span class="oc-avatar" style="background: {{ $w }}">{{ $inisial }}</span>
    <div class="oc-kartu__detail">
      <div>SID {{ $o['sid'] }}</div>
      @if (($o['jabatan'] ?? '') !== '')<div class="oc-kartu__sub">{{ $o['jabatan'] }}</div>@endif
    </div>
  </div>
</div>
