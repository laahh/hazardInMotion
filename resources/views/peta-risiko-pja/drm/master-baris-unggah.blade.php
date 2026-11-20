@extends('peta-risiko-pja._layout-drm')

@section('title', 'Pratinjau Unggah · Master per Baris')

@section('hero-aksi')
  <a class="pr-back" href="{{ route('peta-risiko-pja.master.baris.index') }}"><span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Master per baris</a>
@endsection

@section('hero')
  <p class="pr-kicker"><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Master per Baris</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Pratinjau unggah</span></p>
  <h1 class="pr-title">Pratinjau perubahan</h1>
  <p class="pr-sub">Berkas <b>{{ $berkas }}</b>. Belum ada yang disimpan: periksa perubahan di bawah, lalu tekan <b>Terapkan</b>. Pratinjau berlaku 30 menit.</p>
@endsection

@section('content')
@php
  $p = $pratinjau;
  $nama = ['aktivitas' => 'Aktivitas', 'sub' => 'Sub aktivitas', 'bahaya' => 'Bahaya - risiko',
      'OBS' => 'Item Sumber Observasi', 'OAK' => 'Item Sumber OAK', 'KET' => 'Item Sumber Ketidaksesuaian'];
  $kosong = fn ($v) => $v === '' ? '(kosong)' : $v;
@endphp
<style>
  .mu-ringkas { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 16px; }
  .mu-ringkas div { background: #fff; border-radius: 14px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); border-left: 4px solid var(--c); }
  .mu-ringkas small { display: block; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #595c5e; }
  .mu-ringkas b { display: block; font-size: 26px; font-weight: 800; margin-top: 2px; color: var(--c); }
  .mu-tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
  .mu-tbl th { text-align: left; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #595c5e; padding: 10px 12px; background: #f8fafc; border-bottom: 1px solid rgba(171, 173, 175, .3); }
  .mu-tbl td { vertical-align: top; padding: 10px 12px; border-bottom: 1px solid rgba(171, 173, 175, .18); }
  .mu-tbl small { display: block; color: #8a8d90; font-size: 11.5px; }
  .mu-ubah { margin: 0 0 6px; }
  .mu-ubah > b { display: block; font-size: 11px; letter-spacing: .04em; text-transform: uppercase; color: #595c5e; }
  .mu-dari { color: #9b1c31; text-decoration: line-through; } .mu-ke { color: #146c34; font-weight: 600; }
  .mu-chip { display: inline-block; margin: 2px 4px 2px 0; padding: 2px 8px; border-radius: 8px; font-size: 12px; }
  .mu-chip.tambah { background: #e9f7ee; color: #146c34; } .mu-chip.hapus { background: #fdecef; color: #9b1c31; text-decoration: line-through; }
  .mu-warn { display: block; margin-top: 4px; font-size: 12px; color: #8a5300; }
  .mu-aksi { display: flex; gap: 10px; flex-wrap: wrap; padding: 16px 20px; border-top: 1px solid rgba(171, 173, 175, .2); }
  .mu-btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 999px; border: 0; background: #3952bc; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; }
  .mu-btn.sek { background: #fff; color: #2c2f31; border: 1px solid rgba(171, 173, 175, .45); }
  .mu-btn:disabled { opacity: .5; cursor: not-allowed; }
</style>

<div class="mu-ringkas">
  <div style="--c:#3952bc"><small>Baris di berkas</small><b>{{ number_format($p['total'], 0, ',', '.') }}</b></div>
  <div style="--c:#1a7f37"><small>Akan diubah</small><b>{{ number_format(count($p['ubah']), 0, ',', '.') }}</b></div>
  <div style="--c:#8a8d90"><small>Tidak berubah</small><b>{{ number_format($p['tetap'], 0, ',', '.') }}</b></div>
  <div style="--c:#a07a00"><small>Konflik (dilewati)</small><b>{{ number_format(count($p['konflik']), 0, ',', '.') }}</b></div>
  <div style="--c:#b42318"><small>Galat (dilewati)</small><b>{{ number_format(count($p['galat']), 0, ',', '.') }}</b></div>
</div>

<div class="pr-card">
  <div class="pr-card-head"><div><b>Perubahan yang akan diterapkan</b><small>Kolom yang tidak tercantum tidak berubah. Merah = sebelum / dihapus, hijau = sesudah / ditambah.</small></div></div>
  <div class="dr-scroll">
    <table class="mu-tbl">
      <thead><tr><th style="width:90px">Baris Excel</th><th style="min-width:220px">Baris HIRA</th><th>Perubahan</th></tr></thead>
      <tbody>
        @forelse ($p['ubah'] as $u)
          <tr>
            <td>{{ $u['baris'] }}<small>ID {{ $u['ri'] }}</small></td>
            <td>{{ $u['aktivitas'] }}<small>{{ $u['bahaya'] }}</small></td>
            <td>
              @foreach ($u['ubah'] as $k => $c)
                <div class="mu-ubah"><b>{{ $nama[$k] ?? $k }}</b>
                  @if (array_key_exists('dari', $c))
                    <span class="mu-dari">{{ $kosong($c['dari']) }}</span> → <span class="mu-ke">{{ $kosong($c['ke']) }}</span>
                  @else
                    @foreach ($c['tambah'] as $v)<span class="mu-chip tambah">+ {{ $v }}</span>@endforeach
                    @foreach ($c['hapus'] as $v)<span class="mu-chip hapus">− {{ $v }}</span>@endforeach
                  @endif
                </div>
              @endforeach
              @foreach ($u['peringatan'] as $w)<span class="mu-warn">⚠ {{ $w }}</span>@endforeach
            </td>
          </tr>
        @empty
          <tr><td colspan="3" class="pr-empty">Tidak ada perubahan di berkas ini.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mu-aksi">
    @if ($bolehUbah)
      <form method="post" action="{{ route('peta-risiko-pja.master.baris.terapkan', $token) }}" onsubmit="return confirm('Terapkan {{ count($p['ubah']) }} perubahan ke master?')">
        @csrf
        <button class="mu-btn" type="submit" @disabled(! $p['ubah'])><span class="material-symbols-outlined" style="font-size:18px">check</span> Terapkan {{ count($p['ubah']) }} perubahan</button>
      </form>
    @endif
    <a class="mu-btn sek" href="{{ route('peta-risiko-pja.master.baris.index') }}">Batal</a>
  </div>
</div>

@if ($p['konflik'] || $p['galat'])
  <div class="pr-card" style="margin-top:16px">
    <div class="pr-card-head"><div><b>Dilewati</b><small>Baris ini tidak diubah. Perbaiki di Excel lalu unggah ulang.</small></div></div>
    <div class="dr-scroll">
      <table class="mu-tbl">
        <thead><tr><th style="width:90px">Baris Excel</th><th style="min-width:220px">Baris HIRA</th><th>Alasan</th></tr></thead>
        <tbody>
          @foreach ([...array_map(fn ($x) => [...$x, 'jenis' => 'Konflik'], $p['konflik']), ...array_map(fn ($x) => [...$x, 'jenis' => 'Galat'], $p['galat'])] as $x)
            <tr>
              <td>{{ $x['baris'] }}<small>ID {{ $x['ri'] ?: '–' }}</small></td>
              <td>{{ $x['aktivitas'] ?? '–' }}<small>{{ $x['bahaya'] ?? '' }}</small></td>
              <td><b style="color:{{ $x['jenis'] === 'Konflik' ? '#a07a00' : '#b42318' }}">{{ $x['jenis'] }}:</b> {{ $x['pesan'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif
@endsection

@section('scripts')
@include('peta-risiko-pja.drm._ui')
@endsection
