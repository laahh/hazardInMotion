@extends('peta-risiko-pja._layout-drm')

@section('title', 'Master Grup Lokasi & Aktivitas · Peta Risiko PJA')

@section('hero-aksi')
  <a class="pr-back" href="{{ route('peta-risiko-pja.index') }}"><span class="material-symbols-outlined" style="font-size:18px">dashboard</span> Dashboard</a>
  <a class="pr-back" href="{{ route('peta-risiko-pja.master.baris.index') }}"><span class="material-symbols-outlined" style="font-size:18px">table_rows</span> Master per baris</a>
  @if ($siap)
    <a class="pr-back" href="{{ route('peta-risiko-pja.master.log') }}"><span class="material-symbols-outlined" style="font-size:18px">history</span> Riwayat</a>
  @endif
@endsection

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Grup lokasi & aktivitas</span></p>
  <h1 class="pr-title">Grup lokasi & aktivitas HIRA</h1>
  <p class="pr-sub">Aktivitas HIRA yang berlaku di setiap grup lokasi (M05) beserta kelompok aktivitasnya (M02). Semua detail lokasi di grup mewarisi baris di sini sebagai baseline. Perubahan berlaku pada <code>peta-risiko:sync</code> berikutnya.</p>
@endsection

@section('content')
<style>
  .mg-banner { display: flex; gap: 12px; align-items: flex-start; padding: 14px 18px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; line-height: 1.5; }
  .mg-banner.ok { background: #e9f7ee; color: #146c34; border: 1px solid rgba(22, 163, 74, .25); }
  .mg-banner.err { background: #fdecef; color: #9b1c31; border: 1px solid rgba(200, 16, 46, .25); }
  .mg-banner.warn { background: #fff7e6; color: #8a5300; border: 1px solid rgba(217, 119, 6, .25); }
  .mg-top { display: grid; grid-template-columns: minmax(0, 320px) minmax(0, 1fr); gap: 16px; align-items: start; }
  .mg-grup { display: grid; gap: 4px; max-height: 70vh; overflow: auto; padding: 8px; }
  .mg-grup a { display: flex; justify-content: space-between; gap: 8px; padding: 9px 12px; border-radius: 10px; color: #2c2f31; text-decoration: none; font-size: 13.5px; }
  .mg-grup a:hover { background: #f1f4fb; }
  .mg-grup a.on { background: #3952bc; color: #fff; }
  .mg-grup a small { font-size: 11.5px; opacity: .75; white-space: nowrap; }
  .mg-grup a.kosong:not(.on) { color: #9aa0a6; }
  .mg-ring { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
  .mg-ring div { background: #fff; border: 1px solid rgba(0, 0, 0, .08); border-radius: 14px; padding: 12px 14px; }
  .mg-ring small { display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #595c5e; }
  .mg-ring b { font-size: 22px; font-weight: 800; color: #2c2f31; }
  .mg-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 18px; border-bottom: 1px solid rgba(171, 173, 175, .2); }
  .mg-bar input, .mg-bar select { padding: 8px 12px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #f8fafc; font-size: 13px; }
  .mg-bar input[type=search] { flex: 1 1 240px; }
  .mg-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 999px; border: 0; background: #3952bc; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; white-space: nowrap; }
  .mg-btn.sek { background: #fff; color: #2c2f31; border: 1px solid rgba(171, 173, 175, .45); }
  .mg-btn.bahaya { background: #fff; color: #b42318; border: 1px solid rgba(180, 35, 24, .35); }
  .mg-kel { padding: 10px 18px; background: #f8fafc; border-top: 1px solid rgba(171, 173, 175, .2); font-size: 12px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #595c5e; }
  .mg-akt > summary { display: flex; gap: 12px; align-items: center; padding: 11px 18px; cursor: pointer; list-style: none; border-top: 1px solid rgba(171, 173, 175, .15); }
  .mg-akt > summary::-webkit-details-marker { display: none; }
  .mg-akt > summary::before { content: "▸"; color: #3952bc; transition: transform .2s; }
  .mg-akt[open] > summary::before { transform: rotate(90deg); }
  .mg-akt > summary:hover { background: #f6f8fd; }
  .mg-akt .nama { flex: 1; min-width: 0; font-size: 13.5px; }
  .mg-akt .nama small { display: block; color: #8a8d90; font-size: 11.5px; }
  .mg-chip { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background: #eef1f5; color: #475569; white-space: nowrap; }
  .mg-chip.lain { background: #fff7e6; color: #8a5300; }
  .mg-isi { padding: 6px 18px 16px 40px; background: #fbfcfe; }
  .mg-isi form.kel { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 6px 0 10px; font-size: 13px; }
  .mg-isi select { padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(171, 173, 175, .55); font-size: 13px; }
  .mg-tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
  .mg-tbl th { text-align: left; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #595c5e; padding: 8px 10px; border-bottom: 1px solid rgba(171, 173, 175, .3); }
  .mg-tbl td { padding: 8px 10px; border-bottom: 1px solid rgba(171, 173, 175, .15); vertical-align: top; }
  .mg-lv { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700; background: #f3f4f6; color: #4b5563; }
  .mg-lv.Significant { background: #fdecee; color: #b91c1c; } .mg-lv.High { background: #fff0e3; color: #b45309; }
  .mg-lv.Medium { background: #fef8e1; color: #92650a; } .mg-lv.Low { background: #eef8f0; color: #15803d; }
  .mg-aksi { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 10px; font-size: 13px; }
  .mg-dl { columns: 3 260px; font-size: 12.5px; color: #475569; padding: 4px 18px 16px; }
  .mg-dl div { break-inside: avoid; padding: 2px 0; }
  .mg-kosong { padding: 24px 18px; color: #8a8d90; font-size: 13px; }
  @media (max-width: 900px) { .mg-top { grid-template-columns: 1fr; } .mg-grup { max-height: 260px; } }
</style>

@if (session('pesan'))
  <div class="mg-banner ok"><span class="material-symbols-outlined">check_circle</span><div>{{ session('pesan') }}</div></div>
@endif
@if ($errors->any())
  <div class="mg-banner err"><span class="material-symbols-outlined">error</span><div>{!! implode('<br>', array_map('e', $errors->all())) !!}</div></div>
@endif

@if (! $siap)
  <div class="mg-banner warn"><span class="material-symbols-outlined">database</span><div>Master database belum siap. Isi dulu tabel master (<code>php artisan peta-risiko:master-db --isi</code>) sebelum memakai halaman ini.</div></div>
@else
  <div class="mg-top">
    <div class="pr-card" style="padding:0">
      <div class="pr-card-head"><div><b>Grup lokasi</b><small>Baris HIRA · detail lokasi</small></div></div>
      <nav class="mg-grup" aria-label="Pilih grup lokasi">
        @foreach ($daftarGrup as $g)
          <a href="{{ route('peta-risiko-pja.master.grup.index', ['gid' => $g['gid']]) }}" class="{{ $g['gid'] === $gid ? 'on' : '' }} {{ $g['baris'] ? '' : 'kosong' }}" @if ($g['gid'] === $gid) aria-current="page" @endif>
            <span>{{ $g['nama'] }}</span><small>{{ number_format($g['baris'], 0, ',', '.') }} · {{ number_format($g['dl'], 0, ',', '.') }} DL</small>
          </a>
        @endforeach
      </nav>
    </div>

    <div>
      <div class="mg-ring">
        <div><small>Baris HIRA di grup</small><b>{{ number_format($grupAktif['baris'] ?? 0, 0, ',', '.') }}</b></div>
        <div><small>Aktivitas HIRA</small><b>{{ number_format($grupAktif['aktivitas'] ?? 0, 0, ',', '.') }}</b></div>
        <div><small>Detail lokasi</small><b>{{ number_format($grupAktif['dl'] ?? 0, 0, ',', '.') }}</b></div>
      </div>

      <div class="pr-card" style="padding:0">
        <div class="mg-bar">
          <input type="search" id="mg-cari" placeholder="Cari aktivitas, sub aktivitas, atau bahaya…" aria-label="Cari aktivitas">
          <select id="mg-f-kel" aria-label="Saring kelompok">
            <option value="">Semua kelompok</option>
            @foreach ($kelompok as $kode => $nama)
              @if (collect($aktivitas)->contains('kelompok', $kode))<option value="{{ $kode }}">{{ $nama }}</option>@endif
            @endforeach
          </select>
          @if ($bolehUbah)
            <form method="post" action="{{ route('peta-risiko-pja.master.grup.tambah', ['gid' => $gid]) }}" style="display:flex;gap:8px;flex:1 1 320px" onsubmit="return mgTambah(this)">@csrf
              <input list="mg-luar" id="mg-tambah-teks" placeholder="Tambah aktivitas ke grup ini…" aria-label="Tambah aktivitas" style="flex:1">
              <input type="hidden" name="aid" id="mg-tambah-aid">
              <button class="mg-btn" type="submit"><span class="material-symbols-outlined" style="font-size:18px">add</span> Tambah</button>
            </form>
            <datalist id="mg-luar">@foreach ($luar as $a)<option value="{{ $a['aktivitas'] }} · #{{ $a['aid'] }}" data-aid="{{ $a['aid'] }}">{{ $kelompok[$a['kelompok']] ?? $a['kelompok'] }} · {{ $a['sisa'] }} baris</option>@endforeach</datalist>
          @endif
        </div>

        <div id="mg-daftar">
          @php $kelSebelum = null; @endphp
          @forelse ($aktivitas as $a)
            @if ($a['kelompok'] !== $kelSebelum)
              @php $kelSebelum = $a['kelompok']; $nKel = collect($aktivitas)->where('kelompok', $a['kelompok']); @endphp
              <div class="mg-kel" data-kel="{{ $a['kelompok'] }}">{{ $kelompok[$a['kelompok']] ?? $a['kelompok'] }} · {{ $nKel->count() }} aktivitas · {{ $nKel->sum(fn ($x) => count($x['baris'])) }} baris</div>
            @endif
            <details class="mg-akt" data-kel="{{ $a['kelompok'] }}" data-cari="{{ mb_strtolower($a['aktivitas'].' '.collect($a['baris'])->map(fn ($b) => $b['sub'].' '.$b['bahaya'])->implode(' ')) }}">
              <summary>
                <div class="nama"><b>{{ $a['aktivitas'] }}</b><small>ID aktivitas {{ $a['aid'] }}</small></div>
                <span class="mg-chip">{{ count($a['baris']) }}{{ count($a['baris']) < $a['total'] ? ' dari '.$a['total'] : '' }} baris</span>
                @foreach ($a['lain'] as $l)<span class="mg-chip lain" title="Baris aktivitas ini juga ada di grup lain">{{ $l['nama'] }} · {{ $l['n'] }}</span>@endforeach
              </summary>
              <div class="mg-isi">
                @if ($bolehUbah)
                  <form class="kel" method="post" action="{{ route('peta-risiko-pja.master.grup.kelompok', ['aid' => $a['aid']]) }}">@csrf
                    <input type="hidden" name="gid" value="{{ $gid }}">
                    <label for="kel-{{ $a['aid'] }}">Kelompok aktivitas</label>
                    <select name="kelompok" id="kel-{{ $a['aid'] }}">
                      @foreach ($kelompok as $kode => $nama)<option value="{{ $kode }}" @selected($kode === $a['kelompok'])>{{ $nama }}</option>@endforeach
                    </select>
                    <button class="mg-btn sek" type="submit">Simpan kelompok</button>
                    <span class="pr-meta">berlaku untuk semua baris aktivitas ini di semua grup</span>
                  </form>
                @else
                  <p class="pr-meta" style="margin:6px 0 10px">Kelompok: {{ $kelompok[$a['kelompok']] ?? $a['kelompok'] }}</p>
                @endif
                <form method="post" action="{{ route('peta-risiko-pja.master.grup.baris', ['gid' => $gid]) }}" onsubmit="return mgKonfirmasi(event, this)">@csrf
                  <div class="pr-scroll" style="overflow-x:auto">
                    <table class="mg-tbl">
                      <thead><tr>@if ($bolehUbah)<th style="width:34px"><input type="checkbox" class="mg-semua" aria-label="Pilih semua baris aktivitas ini"></th>@endif<th>ID</th><th>Sub aktivitas</th><th>Bahaya - risiko</th><th>Risiko awal</th></tr></thead>
                      <tbody>
                        @foreach ($a['baris'] as $b)
                          <tr>@if ($bolehUbah)<td><input type="checkbox" name="ri[]" value="{{ $b['ri'] }}" aria-label="Pilih baris {{ $b['ri'] }}"></td>@endif
                            <td>{{ $b['ri'] }}</td><td>{{ $b['sub'] ?: '—' }}</td><td>{{ $b['bahaya'] }}@unless ($b['aktif'])<span class="mg-chip" style="margin-left:6px">nonaktif</span>@endunless</td>
                            <td><span class="mg-lv {{ $b['kb'] }}">{{ $b['kb'] ?: '-' }}</span></td></tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                  @if ($bolehUbah)
                    <div class="mg-aksi">
                      <span class="pr-meta">Baris terpilih:</span>
                      <select name="tujuan" aria-label="Grup tujuan">
                        <option value="">Pindah ke grup…</option>
                        @foreach ($daftarGrup as $g)@if ($g['gid'] !== $gid)<option value="{{ $g['gid'] }}">{{ $g['nama'] }}</option>@endif @endforeach
                      </select>
                      <button class="mg-btn sek" type="submit" name="aksi" value="pindah"><span class="material-symbols-outlined" style="font-size:17px">move_down</span> Pindahkan</button>
                      <button class="mg-btn bahaya" type="submit" name="aksi" value="hapus"><span class="material-symbols-outlined" style="font-size:17px">remove_circle</span> Keluarkan dari grup</button>
                    </div>
                  @endif
                </form>
              </div>
            </details>
          @empty
            <div class="mg-kosong">Grup ini belum punya baris HIRA. Semua detail lokasinya tidak punya baseline. Tambahkan aktivitas yang dikerjakan di grup ini.</div>
          @endforelse
          <div class="mg-kosong" id="mg-tak-cocok" hidden>Tidak ada aktivitas yang cocok.</div>
        </div>
      </div>

      <details class="pr-card" style="padding:0;margin-top:16px">
        <summary class="pr-card-head" style="cursor:pointer"><div><b>Detail lokasi di grup ini ({{ count($dl) }})</b><small>Semua mewarisi baris HIRA di atas</small></div></summary>
        <div class="mg-dl">@forelse ($dl as $d)<div>{{ $d['site'] }} · {{ $d['lokasi'] }} · <b>{{ $d['detail'] }}</b></div>@empty<div>Tidak ada detail lokasi aktif.</div>@endforelse</div>
      </details>
      @unless ($bolehUbah)<p class="pr-meta" style="margin-top:10px">Anda hanya bisa melihat; hubungi admin untuk menjadi editor master.</p>@endunless
    </div>
  </div>
@endif
@endsection

@section('scripts')
@if ($siap)
<script>
  (function () {
    const cari = document.getElementById('mg-cari'), fKel = document.getElementById('mg-f-kel');
    function saring() {
      const q = cari.value.trim().toLowerCase(), k = fKel.value;
      let ada = 0;
      document.querySelectorAll('.mg-akt').forEach((d) => {
        const tampil = (!k || d.dataset.kel === k) && (!q || d.dataset.cari.includes(q));
        d.hidden = !tampil;
        if (tampil) ada++;
        if (tampil && q) d.open = true;
      });
      document.querySelectorAll('.mg-kel').forEach((h) => {
        h.hidden = ![...document.querySelectorAll(`.mg-akt[data-kel="${h.dataset.kel}"]`)].some((d) => !d.hidden);
      });
      document.getElementById('mg-tak-cocok').hidden = ada > 0;
    }
    cari.addEventListener('input', saring);
    fKel.addEventListener('change', saring);
    document.querySelectorAll('.mg-semua').forEach((c) => c.addEventListener('change', () => {
      c.closest('table').querySelectorAll('input[name="ri[]"]').forEach((x) => { x.checked = c.checked; });
    }));
  })();

  /** Isi aid dari pilihan datalist; tolak teks yang tidak ada di daftar. */
  function mgTambah(form) {
    const teks = document.getElementById('mg-tambah-teks').value;
    const opsi = [...document.querySelectorAll('#mg-luar option')].find((o) => o.value === teks);
    if (!opsi) { alert('Pilih aktivitas dari daftar saran.'); return false; }
    document.getElementById('mg-tambah-aid').value = opsi.dataset.aid;
    return confirm('Tambahkan semua baris aktivitas ini ke grup?');
  }

  function mgKonfirmasi(e, form) {
    const aksi = e.submitter ? e.submitter.value : '';
    const n = form.querySelectorAll('input[name="ri[]"]:checked').length;
    if (!n) { alert('Pilih minimal satu baris.'); return false; }
    if (aksi === 'pindah') {
      const t = form.querySelector('select[name="tujuan"]');
      if (!t.value) { alert('Pilih grup tujuan.'); return false; }
      return confirm(`Pindahkan ${n} baris ke grup "${t.options[t.selectedIndex].text}"?`);
    }
    return confirm(`Keluarkan ${n} baris dari grup ini? Baris yang tidak ada di grup mana pun tidak masuk baseline.`);
  }
</script>
@endif
@endsection
