{{-- Pilihan perusahaan & site untuk tab People / Org chart / Time off. Site mengikuti perusahaan (JS di overview). --}}
<div>
  <label class="form-label text-sm fw-medium mb-4">Perusahaan</label>
  <select name="pt" class="form-select form-select-sm" data-ovt-pt>
    <option value="">Semua perusahaan</option>
    @foreach ($opsi as $nama => $sites)
      <option value="{{ $nama }}" data-site="{{ json_encode($sites) }}" @selected($nama === $pt)>{{ $nama }}</option>
    @endforeach
  </select>
</div>
<div>
  <label class="form-label text-sm fw-medium mb-4">Site</label>
  <select name="site" class="form-select form-select-sm" data-ovt-site data-terpilih="{{ $site }}">
    <option value="">Semua site</option>
    @foreach ($pt !== '' ? ($opsi[$pt] ?? []) : array_values(array_unique(array_merge(...array_values($opsi ?: [[]])))) as $s)
      <option value="{{ $s }}" @selected($s === $site)>{{ $s === '-' ? 'Tanpa site' : $s }}</option>
    @endforeach
  </select>
</div>
<div>
  <label class="form-label text-sm fw-medium mb-4">Departemen</label>
  <select name="dept" class="form-select form-select-sm" data-ovt-dept>
    <option value="">Semua departemen ({{ count($depts ?? []) }})</option>
    @foreach (($depts ?? []) as $d => $n)
      <option value="{{ $d }}" @selected($d === ($dept ?? ''))>{{ $d === '-' ? 'Tanpa departemen' : $d }} · {{ number_format($n, 0, ',', '.') }}</option>
    @endforeach
  </select>
</div>
