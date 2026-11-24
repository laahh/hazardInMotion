@extends('peta-risiko-pja._layout-drm')

@section('title', 'Riwayat Master Mapping · Peta Risiko PJA')

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Master Mapping</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Riwayat</span></p>
  <h1 class="pr-title">Riwayat perubahan master</h1>
  <p class="pr-sub">Semua penambahan, perubahan, dan penghapusan di tabel master mapping, beserta nilai sebelum dan sesudahnya.</p>
@endsection

@section('content')
@php
  $namaTabel = collect($semua)->mapWithKeys(fn ($s) => [$s['tabel'] => $s['sheet'].' · '.$s['judul']])->all();
  $ringkas = function (?string $json) {
      if ($json === null) return '—';
      $d = json_decode($json, true) ?: [];
      unset($d['created_at'], $d['updated_at'], $d['diubah_oleh']);
      // Nilai banyak baris (label, grup lokasi) dipisah " | " supaya terbaca dalam satu sel.
      return collect($d)->map(fn ($v, $k) => $k.': '.(is_bool($v) ? ($v ? 'ya' : 'tidak') : (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : str_replace("\n", ' | ', (string) $v))))->implode(' · ');
  };
@endphp
<div class="pr-card">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:14px 20px;border-bottom:1px solid rgba(171,173,175,.2)">
    <select name="tabel" aria-label="Tabel" style="padding:8px 12px;border-radius:999px;border:1px solid rgba(171,173,175,.45);font-size:13px">
      <option value="">Semua tabel</option>
      @foreach ($namaTabel as $t => $n)
        <option value="{{ $t }}" @selected(($filter['tabel'] ?? '') === $t)>{{ $n }}</option>
      @endforeach
    </select>
    <input type="text" name="kunci" value="{{ $filter['kunci'] ?? '' }}" placeholder="Kunci / ID baris" aria-label="Kunci" style="padding:8px 12px;border-radius:999px;border:1px solid rgba(171,173,175,.45);font-size:13px;width:150px">
    <input type="text" name="user" value="{{ $filter['user'] ?? '' }}" placeholder="Nama pengguna" aria-label="Pengguna" style="padding:8px 12px;border-radius:999px;border:1px solid rgba(171,173,175,.45);font-size:13px;width:180px">
    <select name="aksi" aria-label="Aksi" style="padding:8px 12px;border-radius:999px;border:1px solid rgba(171,173,175,.45);font-size:13px">
      <option value="">Semua aksi</option>
      @foreach ($aksiAda as $a)
        <option value="{{ $a }}" @selected(($filter['aksi'] ?? '') === $a)>{{ $a }}</option>
      @endforeach
    </select>
    <button type="submit" class="pr-back" style="cursor:pointer">Saring</button>
    @if (array_filter($filter))<a class="pr-back" href="{{ route('peta-risiko-pja.master.log') }}">Reset</a>@endif
  </form>
  <div class="dr-scroll">
    <table class="pr-tbl dr-tbl">
      <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Tabel · kunci</th><th>Sebelum</th><th>Sesudah</th></tr></thead>
      <tbody>
        @forelse ($log as $l)
          <tr>
            <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($l->dibuat)->translatedFormat('d M Y H:i') }}</td>
            <td>{{ $l->user_nama }}</td>
            <td><b style="text-transform:uppercase;font-size:11px;letter-spacing:.06em;color:var(--primary)">{{ $l->aksi }}</b></td>
            <td>{{ $namaTabel[$l->tabel] ?? $l->tabel }}<br><span class="dr-mute">{{ $l->kunci }}</span></td>
            <td><div class="pr-clamp" style="max-width:360px;font-size:12px">{{ $ringkas($l->sebelum) }}</div></td>
            <td><div class="pr-clamp" style="max-width:360px;font-size:12px">{{ $ringkas($l->sesudah) }}</div></td>
          </tr>
        @empty
          <tr><td colspan="6" class="pr-empty">Belum ada perubahan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 24px;font-size:13px;color:#595c5e">
    <span>{{ number_format($log->total(), 0, ',', '.') }} perubahan</span>
    <span style="display:flex;gap:8px">
      @if ($log->previousPageUrl()) <a class="pr-back" href="{{ $log->previousPageUrl() }}">‹ Sebelumnya</a> @endif
      @if ($log->nextPageUrl()) <a class="pr-back" href="{{ $log->nextPageUrl() }}">Berikutnya ›</a> @endif
    </span>
  </div>
</div>
@endsection

@section('scripts')
@include('peta-risiko-pja.drm._ui')
@endsection
