@extends('peta-risiko-pja._layout-drm')

@section('title', 'Master per Baris · Peta Risiko PJA')

@section('hero-aksi')
  <a class="pr-back" href="{{ route('peta-risiko-pja.index') }}"><span class="material-symbols-outlined" style="font-size:18px">dashboard</span> Dashboard</a>
  <a class="pr-back" href="{{ route('peta-risiko-pja.master.index') }}"><span class="material-symbols-outlined" style="font-size:18px">table_view</span> Tabel master</a>
  @if ($siap)
    <a class="pr-back" href="{{ route('peta-risiko-pja.master.log') }}"><span class="material-symbols-outlined" style="font-size:18px">history</span> Riwayat</a>
  @endif
@endsection

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Master per Baris</span></p>
  <h1 class="pr-title">Master HIRA ↔ SAP per baris</h1>
  <p class="pr-sub">Acuan utama: setiap baris HIRA (aktivitas · sub aktivitas · bahaya-risiko) dengan label SAP yang menangkapnya. Laporan hanya dihitung untuk baris yang labelnya tercantum di sini. Perubahan berlaku pada <code>peta-risiko:sync</code> berikutnya.</p>
@endsection

@section('content')
<style>
  .mb-banner { display: flex; gap: 12px; align-items: flex-start; padding: 14px 18px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; line-height: 1.5; }
  .mb-banner.info { background: #eef1fd; color: #2b3a8f; border: 1px solid rgba(57, 82, 188, .2); }
  .mb-banner.warn { background: #fff7e6; color: #8a5300; border: 1px solid rgba(217, 119, 6, .25); }
  .mb-banner.ok { background: #e9f7ee; color: #146c34; border: 1px solid rgba(22, 163, 74, .25); }
  .mb-banner.err { background: #fdecef; color: #9b1c31; border: 1px solid rgba(200, 16, 46, .25); }
  .mb-banner code { background: rgba(0, 0, 0, .06); padding: 1px 6px; border-radius: 6px; }
  .mb-ring { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 16px; }
  .mb-ring div { background: #fff; border: 1px solid rgba(0, 0, 0, .08); border-radius: 14px; padding: 12px 14px; }
  .mb-ring small { display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #595c5e; }
  .mb-ring b { font-size: 22px; font-weight: 800; color: #2c2f31; }
  .mb-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 20px; border-bottom: 1px solid rgba(171, 173, 175, .2); }
  .mb-bar input[type=search] { flex: 1 1 280px; max-width: 420px; padding: 9px 14px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #f8fafc; font-size: 13.5px; }
  .mb-bar select { padding: 8px 12px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #fff; font-size: 13px; }
  .mb-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 999px; border: 0; background: #3952bc; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; }
  .mb-btn.sek { background: #fff; color: #2c2f31; border: 1px solid rgba(171, 173, 175, .45); }
  .mb-tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
  .mb-tbl th { position: sticky; top: 0; background: #f8fafc; text-align: left; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #595c5e; padding: 10px 12px; border-bottom: 1px solid rgba(171, 173, 175, .3); }
  .mb-tbl td { vertical-align: top; padding: 10px 12px; border-bottom: 1px solid rgba(171, 173, 175, .18); }
  .mb-tbl td.hira { min-width: 150px; max-width: 220px; }
  .mb-tbl td.hira small { display: block; color: #8a8d90; font-size: 11.5px; margin-top: 2px; }
  .mb-tbl td.lbl { min-width: 230px; }
  .mb-lv { display: inline-block; margin-top: 4px; padding: 1px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700; background: #f3f4f6; color: #4b5563; }
  .mb-lv.Significant { background: #fdecee; color: #b91c1c; } .mb-lv.High { background: #fff0e3; color: #b45309; }
  .mb-lv.Medium { background: #fef8e1; color: #92650a; } .mb-lv.Low { background: #eef8f0; color: #15803d; }
  .mb-chip { display: flex; align-items: flex-start; gap: 6px; margin: 0 0 6px; padding: 5px 6px 5px 10px; border-radius: 10px; background: #f1f5f9; border: 1px solid #e2e8f0; font-size: 12px; line-height: 1.35; }
  .mb-chip.turunan { background: #fffbeb; border-color: #fde68a; }
  .mb-chip.manual { background: #ecfdf5; border-color: #a7f3d0; }
  .mb-chip span { flex: 1; }
  .mb-chip em { display: block; font-style: normal; font-size: 10px; font-weight: 700; letter-spacing: .04em; color: #92650a; text-transform: uppercase; }
  .mb-chip.manual em { color: #047857; }
  .mb-chip form { margin: 0; }
  .mb-chip button { border: 0; background: transparent; color: #94a3b8; cursor: pointer; font-size: 16px; line-height: 1; padding: 0 2px; }
  .mb-chip button:hover { color: #b91c1c; }
  .mb-kosong { color: #b0b3b6; font-size: 12px; font-style: italic; }
  .mb-tambah summary { cursor: pointer; font-size: 12px; font-weight: 600; color: #3952bc; list-style: none; }
  .mb-tambah summary::-webkit-details-marker { display: none; }
  .mb-tambah form { display: flex; flex-direction: column; gap: 6px; margin-top: 6px; }
  .mb-tambah input { padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(171, 173, 175, .55); font-size: 12.5px; }
  .mb-tambah button { align-self: flex-start; padding: 5px 12px; border-radius: 999px; border: 0; background: #3952bc; color: #fff; font-size: 12px; font-weight: 600; cursor: pointer; }
  .mb-pag { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; font-size: 13px; color: #595c5e; }
  .mb-scroll { overflow-x: auto; }
  @media (max-width: 760px) { .mb-ring { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>

@if (session('pesan'))
  <div class="mb-banner ok"><span class="material-symbols-outlined">check_circle</span><div>{{ session('pesan') }}</div></div>
@endif
@if ($errors->any())
  <div class="mb-banner err"><span class="material-symbols-outlined">error</span><div>{!! implode('<br>', array_map('e', $errors->all())) !!}</div></div>
@endif

@if (! $siap || ! $terisi)
  <div class="mb-banner warn"><span class="material-symbols-outlined">database</span><div>
    <b>Master database belum siap</b> di database <code>{{ $namaDb }}</code>:
    {{ $siap ? 'tabel master sudah ada tetapi masih kosong.' : 'tabel drm_master_* belum ada (belum dimigrasi).' }}
    Pastikan perintah dijalankan pada aplikasi yang memakai database yang sama dengan halaman ini, lalu
    {{ $siap ? '' : 'jalankan migrasi, ' }}isi dengan <code>php artisan peta-risiko:master-db --isi</code> dan
    <code>php artisan peta-risiko:master-db --turunkan-oak</code>.
    @if ($bolehUbah)
      <br><small>Diagnosis proses web: server <code>{{ $diagnosis['server'] }}</code> · folder <code>{{ $diagnosis['folder'] }}</code> ·
        koneksi <code>{{ $diagnosis['koneksi'] }}</code> · database <code>{{ $namaDb }}</code> · config cache {{ $diagnosis['configCache'] ? 'aktif' : 'tidak' }}
        @if ($diagnosis['error'])· error: <code>{{ $diagnosis['error'] }}</code>@endif</small>
    @endif
  </div></div>
@else
  <div class="mb-banner info"><span class="material-symbols-outlined">info</span><div>
    Label <b>kuning</b> = turunan kurasi aktivitas OAK (periksa, hapus yang tidak sesuai). Label <b>hijau</b> = ditambah manual.
    Label OAK ditulis <code>Aktivitas OAK / Sub aktivitas</code>; label OBS = jenis kegiatan observasi; huruf besar/kecil, spasi, dan tanda baca diabaikan saat dicocokkan.
    @unless ($bolehUbah) Anda hanya bisa melihat; hubungi admin untuk menjadi editor. @endunless</div></div>

  <div class="mb-ring">
    <div><small>Baris HIRA aktif</small><b>{{ number_format($jumlah['baris'], 0, ',', '.') }}</b></div>
    <div><small>Baris berlabel Observasi</small><b>{{ number_format($jumlah['OBS'], 0, ',', '.') }}</b></div>
    <div><small>Baris berlabel OAK</small><b>{{ number_format($jumlah['OAK'], 0, ',', '.') }}</b></div>
    <div><small>Baris berlabel Ketidaksesuaian</small><b>{{ number_format($jumlah['KET'], 0, ',', '.') }}</b></div>
  </div>

  <div class="pr-card">
    <form class="mb-bar" method="get" action="{{ route('peta-risiko-pja.master.baris.index') }}">
      <input type="search" name="q" value="{{ $q }}" placeholder="Cari aktivitas, sub aktivitas, bahaya, atau ID baris…" aria-label="Cari">
      <select name="kb" aria-label="Risiko awal">
        <option value="">Semua risiko</option>
        @foreach (['Significant', 'High', 'Medium', 'Low'] as $lv)
          <option value="{{ $lv }}" @selected($kb === $lv)>{{ $lv }}</option>
        @endforeach
      </select>
      <select name="kosong" aria-label="Filter label">
        <option value="">Semua baris</option>
        <option value="OBS" @selected($kosong === 'OBS')>Tanpa label Observasi</option>
        <option value="OAK" @selected($kosong === 'OAK')>Tanpa label OAK</option>
        <option value="KET" @selected($kosong === 'KET')>Tanpa label Ketidaksesuaian</option>
        <option value="SEMUA" @selected($kosong === 'SEMUA')>Tanpa label sama sekali</option>
      </select>
      <button class="mb-btn" type="submit"><span class="material-symbols-outlined" style="font-size:18px">search</span> Terapkan</button>
      @if ($q !== '' || $kb !== '' || $kosong !== '')
        <a class="mb-btn sek" href="{{ route('peta-risiko-pja.master.baris.index') }}">Reset</a>
      @endif
    </form>

    <datalist id="mb-obs">@foreach ($saranObs as $s)<option value="{{ $s }}">@endforeach</datalist>
    <datalist id="mb-oak">@foreach ($saranOak as $s)<option value="{{ $s }}">@endforeach</datalist>
    <datalist id="mb-k">@foreach ($saranK as $s)<option value="{{ $s }}">@endforeach</datalist>
    <datalist id="mb-sk">@foreach ($saranSk as $s)<option value="{{ $s }}">@endforeach</datalist>

    <div class="mb-scroll">
      <table class="mb-tbl">
        <thead><tr>
          <th>Aktivitas</th><th>Sub aktivitas</th><th>Bahaya - risiko terkait</th>
          <th>Item Sumber Observasi</th><th>Item Sumber OAK</th><th>Item Sumber Ketidaksesuaian</th>
        </tr></thead>
        <tbody>
        @forelse ($baris as $b)
          @php
            $obs = $teks[$b->ri.'|OBS'] ?? collect();
            $oak = $teks[$b->ri.'|OAK'] ?? collect();
            $ket = $label[$b->ri] ?? collect();
            $kelas = fn ($s) => ($s ?? '') === \App\Services\PetaRisiko\Drm\Master\DrmMasterDb::SUMBER_TURUNAN_OAK ? 'turunan' : (($s ?? '') === 'manual' ? 'manual' : '');
          @endphp
          <tr>
            <td class="hira">{{ $b->aktivitas }}<small>ID {{ $b->ri }}</small></td>
            <td class="hira">{{ $b->sub ?: '—' }}</td>
            <td class="hira">{{ $b->bahaya }}<br><span class="mb-lv {{ $b->kb }}">{{ $b->kb ?: '-' }}</span></td>
            @foreach ([['OBS', $obs, 'mb-obs'], ['OAK', $oak, 'mb-oak']] as [$jenis, $daftar, $dl])
              <td class="lbl">
                @forelse ($daftar as $t)
                  <div class="mb-chip {{ $kelas($t->sumber ?? null) }}"><span>{{ $t->teks }}
                    @if (($t->sumber ?? '') !== '')<em>{{ $t->sumber }}</em>@endif</span>
                    @if ($bolehUbah)
                      <form method="post" action="{{ route('peta-risiko-pja.master.baris.hapus', ['jenis' => 'teks', 'id' => $t->id]) }}" onsubmit="return confirm('Hapus label ini dari baris {{ $b->ri }}?')">@csrf
                        <button type="submit" title="Hapus label" aria-label="Hapus label">×</button></form>
                    @endif
                  </div>
                @empty
                  <div class="mb-kosong">Belum ada label</div>
                @endforelse
                @if ($bolehUbah)
                  <details class="mb-tambah"><summary>+ Tambah label {{ $jenis === 'OBS' ? 'Observasi' : 'OAK' }}</summary>
                    <form method="post" action="{{ route('peta-risiko-pja.master.baris.tambah', ['ri' => $b->ri]) }}">@csrf
                      <input type="hidden" name="jenis" value="{{ $jenis }}">
                      <input name="teks" list="{{ $dl }}" maxlength="500" required placeholder="{{ $jenis === 'OBS' ? 'Jenis kegiatan observasi' : 'Aktivitas OAK / Sub aktivitas' }}">
                      <button type="submit">Simpan</button>
                    </form>
                  </details>
                @endif
              </td>
            @endforeach
            <td class="lbl">
              @forelse ($ket as $l)
                <div class="mb-chip {{ ($l->sumber ?? '') === 'manual' ? 'manual' : '' }}"><span>{{ $l->ketidaksesuaian }} / {{ $l->subketidaksesuaian }}
                  @if (($l->sumber ?? '') !== '')<em>{{ $l->sumber }}</em>@endif</span>
                  @if ($bolehUbah)
                    <form method="post" action="{{ route('peta-risiko-pja.master.baris.hapus', ['jenis' => 'label', 'id' => $l->id]) }}" onsubmit="return confirm('Hapus label ini dari baris {{ $b->ri }}?')">@csrf
                      <button type="submit" title="Hapus label" aria-label="Hapus label">×</button></form>
                  @endif
                </div>
              @empty
                <div class="mb-kosong">Belum ada label</div>
              @endforelse
              @if ($bolehUbah)
                <details class="mb-tambah"><summary>+ Tambah label Ketidaksesuaian</summary>
                  <form method="post" action="{{ route('peta-risiko-pja.master.baris.tambah', ['ri' => $b->ri]) }}">@csrf
                    <input type="hidden" name="jenis" value="KET">
                    <input name="k" list="mb-k" maxlength="300" required placeholder="Ketidaksesuaian">
                    <input name="sk" list="mb-sk" maxlength="300" required placeholder="Sub ketidaksesuaian">
                    <button type="submit">Simpan</button>
                  </form>
                </details>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="mb-kosong" style="padding:24px">Tidak ada baris yang cocok.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="mb-pag">
      <span>{{ number_format($baris->total(), 0, ',', '.') }} baris · halaman {{ $baris->currentPage() }} dari {{ max(1, $baris->lastPage()) }}</span>
      <span style="display:flex;gap:8px">
        @if ($baris->previousPageUrl()) <a class="mb-btn sek" href="{{ $baris->previousPageUrl() }}">‹ Sebelumnya</a> @endif
        @if ($baris->nextPageUrl()) <a class="mb-btn sek" href="{{ $baris->nextPageUrl() }}">Berikutnya ›</a> @endif
      </span>
    </div>
  </div>
@endif
@endsection
