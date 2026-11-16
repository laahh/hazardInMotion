@extends('peta-risiko-pja._layout-drm')

@section('title', $ringkas['nama'].' · Beranda PJA')

@section('hero-aksi')
  <a class="pr-back" href="{{ route('peta-risiko-pja.index', ['tab' => 'pja', 'site' => $ringkas['site']]) }}"><span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Semua PJA</a>
@endsection

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Beranda PJA · {{ $ringkas['site'] }}</span></p>
  <h1 class="pr-title">{{ $ringkas['nama'] }}</h1>
  <p class="pr-sub" id="dr-sub">Data sampai {{ \Carbon\Carbon::parse($meta['sampai'])->translatedFormat('d M Y') }} · periode minggu kalender (Minggu–Sabtu)</p>
@endsection

@include('peta-risiko-pja.drm._beranda-style')

@section('content')
  @include('peta-risiko-pja.drm._beranda-pja-isi')
@endsection

@section('scripts')
@include('peta-risiko-pja.drm._ui')
@include('peta-risiko-pja.drm._beranda-pja-script')
<script>
  BerandaPJA({
    meta: {{ \Illuminate\Support\Js::from(\Illuminate\Support\Arr::only($meta, ['sampai', 'jendela', 'kolomBahaya', 'proses', 'beranda'])) }},
    id: {{ \Illuminate\Support\Js::from((string) $ringkas['id']) }},
  });
</script>
@endsection
