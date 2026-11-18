@extends('peta-risiko-pja._layout-drm')

@section('title', 'Master Mapping · Peta Risiko PJA')

@section('hero-aksi')
  <a class="pr-back" href="{{ route('peta-risiko-pja.index') }}"><span class="material-symbols-outlined" style="font-size:18px">dashboard</span> Dashboard</a>
  <a class="pr-back" href="{{ route('peta-risiko-pja.master.baris.index') }}"><span class="material-symbols-outlined" style="font-size:18px">view_list</span> Master per baris</a>
  @if ($siap)
    <a class="pr-back" href="{{ route('peta-risiko-pja.master.log') }}"><span class="material-symbols-outlined" style="font-size:18px">history</span> Riwayat</a>
  @endif
@endsection

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Master Mapping</span></p>
  <h1 class="pr-title">Master mapping DRM 2.1</h1>
  <p class="pr-sub">Koreksi setiap lapisan pemetaan: baseline HIRA → grup lokasi → detail lokasi → PJA, label SAP, teks OBS/OAK, dan tema checklist. Perubahan berlaku pada <code>peta-risiko:sync</code> berikutnya.</p>
@endsection

@section('content')
<style>
  .mm-banner { display: flex; gap: 12px; align-items: flex-start; padding: 14px 18px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; line-height: 1.5; }
  .mm-banner .material-symbols-outlined { font-size: 22px; }
  .mm-banner.info { background: #eef1fd; color: #2b3a8f; border: 1px solid rgba(57, 82, 188, .2); }
  .mm-banner.warn { background: #fff7e6; color: #8a5300; border: 1px solid rgba(217, 119, 6, .25); }
  .mm-banner.ok { background: #e9f7ee; color: #146c34; border: 1px solid rgba(22, 163, 74, .25); }
  .mm-banner.err { background: #fdecef; color: #9b1c31; border: 1px solid rgba(200, 16, 46, .25); }
  .mm-banner code { background: rgba(0, 0, 0, .06); padding: 1px 6px; border-radius: 6px; }
  .mm-tabs { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; margin-bottom: 18px; }
  .mm-tab { display: flex; flex-direction: column; gap: 2px; padding: 12px 14px; border-radius: 14px; background: #fff; border: 1px solid rgba(0, 0, 0, .08); color: #2c2f31; text-decoration: none; box-shadow: 0 2px 0 rgba(0, 0, 0, .03); transition: border-color .15s, transform .15s; }
  .mm-tab:hover { border-color: #3952bc; transform: translateY(-1px); }
  .mm-tab.on { border-color: #3952bc; background: #eef1fd; box-shadow: 0 0 0 2px rgba(57, 82, 188, .15); }
  .mm-tab small { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #3952bc; }
  .mm-tab b { font-size: 13.5px; font-weight: 700; }
  .mm-tab span { font-size: 12px; color: #595c5e; }
  .mm-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: flex-start; padding: 14px 24px; border-bottom: 1px solid rgba(171, 173, 175, .2); }
  .mm-cari { display: flex; gap: 8px; align-items: center; flex: 1 1 320px; max-width: 460px; }
  .mm-filter { display: inline-flex; align-items: center; gap: 6px; padding: 4px 4px 4px 12px; border-radius: 999px; background: #f8fafc; border: 1px solid rgba(171, 173, 175, .4);
    font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #595c5e; }
  .mm-filter.on { background: #eef1fd; border-color: #3952bc; color: #3952bc; }
  .mm-filter select { border: 0; background: transparent; font-size: 13px; font-weight: 600; color: #2c2f31; text-transform: none; letter-spacing: 0; padding: 5px 6px; max-width: 220px; }
  .mm-hasil { font-size: 12.5px; font-weight: 600; color: #595c5e; }
  .mm-cari input { flex: 1; padding: 9px 14px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #f8fafc; font-size: 13.5px; }
  .mm-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 999px; border: 0; background: #3952bc; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; }
  .mm-btn:hover { background: #2b45af; }
  .mm-btn.sek { background: #fff; color: #2c2f31; border: 1px solid rgba(171, 173, 175, .45); }
  .mm-btn.bahaya { background: #fff; color: #b41340; border: 1px solid rgba(180, 19, 64, .35); padding: 6px 12px; }
  /* Modal form tambah / ubah */
  .mm-modal { width: min(880px, calc(100vw - 32px)); max-height: calc(100vh - 48px); padding: 0; border: 0; border-radius: 20px; box-shadow: 0 30px 60px -12px rgba(0, 0, 0, .35); overflow: hidden; }
  .mm-modal::backdrop { background: rgba(15, 23, 42, .45); backdrop-filter: blur(3px); }
  .mm-modal form { display: flex; flex-direction: column; max-height: calc(100vh - 48px); }
  .mm-modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 20px 24px 16px; border-bottom: 1px solid rgba(171, 173, 175, .25); background: linear-gradient(135deg, rgba(57, 82, 188, .07), rgba(114, 71, 158, .05)); }
  .mm-modal-head small { font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #3952bc; }
  .mm-modal-head h2 { margin: 4px 0 0; font-size: 19px; font-weight: 800; color: #2c2f31; letter-spacing: -.01em; }
  .mm-x { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 10px; color: #595c5e; text-decoration: none; }
  .mm-x:hover { background: rgba(0, 0, 0, .06); color: #2c2f31; }
  .mm-modal-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 24px; border-top: 1px solid rgba(171, 173, 175, .25); background: #fafbfc; }
  .mm-form { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px 18px; padding: 20px 24px; overflow-y: auto; }
  .mm-form label { grid-column: span 2; display: flex; flex-direction: column; gap: 6px; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #595c5e; }
  .mm-form label.lebar { grid-column: 1 / -1; }
  .mm-form label.lebar-2 { grid-column: span 2; }
  .mm-form input, .mm-form select, .mm-form textarea { width: 100%; box-sizing: border-box; padding: 10px 12px; border-radius: 10px; border: 1px solid rgba(171, 173, 175, .55); background: #fff; font-size: 13.5px; font-weight: 500; color: #2c2f31; text-transform: none; letter-spacing: 0; }
  .mm-form input:focus, .mm-form select:focus, .mm-form textarea:focus { outline: 2px solid rgba(57, 82, 188, .35); border-color: #3952bc; }
  .mm-form input:disabled, .mm-form select:disabled { background: #f1f5f9; color: #64748b; }
  .mm-form .cek { grid-column: span 2; flex-direction: row; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; border: 1px solid rgba(171, 173, 175, .35); background: #fafbfc; text-transform: none; font-size: 13.5px; letter-spacing: 0; color: #2c2f31; cursor: pointer; align-self: end; }
  .mm-form .cek input { width: 18px; height: 18px; flex: none; }
  .mm-cari-in { position: relative; display: block; }
  .mm-cari-in .material-symbols-outlined { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 20px; color: #94a3b8; pointer-events: none; }
  .mm-cari-in input { padding-right: 38px; }
  @media (max-width: 640px) { .mm-form { grid-template-columns: 1fr; } .mm-form label, .mm-form .cek, .mm-form label.lebar-2 { grid-column: 1 / -1; } }
  .mm-ri { display: block; margin-top: 2px; font-size: 12px; font-weight: 500; color: #595c5e; text-transform: none; letter-spacing: 0; }
  .mm-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
  .mm-badge.y { background: #dcfce7; color: #166534; }
  .mm-badge.n { background: #f1f5f9; color: #64748b; }
  .mm-tbl td { vertical-align: top; font-size: 13px; }
  .mm-tbl td .clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; max-width: 420px; }
  .mm-pag { display: flex; justify-content: space-between; align-items: center; padding: 14px 24px; font-size: 13px; color: #595c5e; }
  .mm-log { list-style: none; margin: 0; padding: 0; }
  .mm-log li { display: flex; gap: 10px; padding: 10px 24px; border-bottom: 1px solid rgba(171, 173, 175, .15); font-size: 12.5px; }
  .mm-log li:last-child { border-bottom: 0; }
  .mm-log .aksi { min-width: 64px; font-weight: 700; text-transform: uppercase; font-size: 10.5px; letter-spacing: .06em; color: #3952bc; }
</style>

@if (session('pesan'))
  <div class="mm-banner ok"><span class="material-symbols-outlined">check_circle</span><div>{{ session('pesan') }}</div></div>
@endif
@if ($errors->any())
  <div class="mm-banner err"><span class="material-symbols-outlined">error</span><div>{!! implode('<br>', array_map('e', $errors->all())) !!}</div></div>
@endif

@if (! $siap)
  <div class="mm-banner warn"><span class="material-symbols-outlined">database</span><div>
    <b>Tabel master belum dibuat.</b> Jalankan migrasi <code>2026_11_17_100000_create_drm_master_tables</code>, lalu isi dari baseline awal dengan
    <code>php artisan peta-risiko:master-db --isi</code>.</div></div>
@else
  @if (! $terisi)
    <div class="mm-banner warn"><span class="material-symbols-outlined">inventory</span><div>
      <b>Tabel master masih kosong.</b> Isi dari baseline awal (workbook master + aturan regex + PJA per lokasi) dengan
      <code>php artisan peta-risiko:master-db --isi</code>. Selama kosong, sync tetap memakai master.json.</div></div>
  @elseif ($adaPerubahanBaru)
    <div class="mm-banner warn"><span class="material-symbols-outlined">sync_problem</span><div>
      <b>Ada perubahan master setelah sync terakhir</b>{{ $syncTerakhir ? ' ('.\Carbon\Carbon::parse($syncTerakhir)->translatedFormat('d M Y H:i').')' : '' }}.
      Dashboard belum memakai perubahan ini sampai <code>php artisan peta-risiko:sync</code> dijalankan.</div></div>
  @else
    <div class="mm-banner info"><span class="material-symbols-outlined">info</span><div>
      Sync memakai tabel master ini. {{ $syncTerakhir ? 'Sync terakhir '.\Carbon\Carbon::parse($syncTerakhir)->translatedFormat('d M Y H:i').'.' : '' }}
      @unless ($bolehUbah) Anda hanya bisa melihat; hubungi admin untuk menjadi editor. @endunless</div></div>
  @endif

  <nav class="mm-tabs" aria-label="Tabel master">
    @foreach ($semua as $k => $s)
      <a class="mm-tab{{ $k === $kode ? ' on' : '' }}" href="{{ route('peta-risiko-pja.master.index', ['t' => $k]) }}" @if ($k === $kode) aria-current="page" @endif>
        <small>{{ $s['sheet'] }}</small><b>{{ $s['judul'] }}</b><span>{{ number_format($jumlah[$k] ?? 0, 0, ',', '.') }} baris</span>
      </a>
    @endforeach
  </nav>

  <div class="pr-card">
    <div class="pr-card-head"><div><b>{{ $skema['sheet'] }} · {{ $skema['judul'] }}</b><small>{{ $skema['catatan'] }}</small></div></div>
    @php
      // URL daftar dengan pencarian + filter saat ini (dipakai tombol Tambah/Ubah/tutup modal/hapus).
      $dasar = array_filter(['t' => $kode, 'q' => $q, 'f' => $filter ?: null, 'page' => request('page')], fn ($v) => $v !== null && $v !== '');
      $urlDaftar = fn (array $tambahan = []) => route('peta-risiko-pja.master.index', array_merge($dasar, $tambahan));
    @endphp
    <form class="mm-bar" method="get" action="{{ route('peta-risiko-pja.master.index') }}" id="mm-cari-form">
      <input type="hidden" name="t" value="{{ $kode }}">
      <div class="mm-cari">
        <span class="mm-cari-in" style="flex:1"><span class="material-symbols-outlined" style="left:12px;right:auto">search</span>
          <input type="search" name="q" value="{{ $q }}" id="mm-q" style="padding-left:40px" autocomplete="off"
            placeholder="Cari di semua kolom teks{{ collect($skema['kolom'])->contains(fn ($k) => $k[1] === 'int') ? ' atau ketik ID' : '' }}…" aria-label="Cari">
        </span>
      </div>
      @foreach ($kolomFilter as $kolom => [$label, $tipe, $aturan, $opsi])
        @php $daftar = $tipe === 'bool' ? ['1' => 'Ya', '0' => 'Tidak'] : (is_array($opsi['pilihan']) ? array_combine($opsi['pilihan'], $opsi['pilihan']) : ($pilihan[$opsi['pilihan']] ?? [])); @endphp
        <label class="mm-filter{{ isset($filter[$kolom]) ? ' on' : '' }}">{{ $label }}
          <select name="f[{{ $kolom }}]" data-auto>
            <option value="">Semua</option>
            @foreach ($daftar as $v => $teks)
              <option value="{{ $v }}" @selected(isset($filter[$kolom]) && (string) $filter[$kolom] === (string) $v)>{{ $teks }}</option>
            @endforeach
          </select>
        </label>
      @endforeach
      <span class="mm-hasil">{{ number_format($baris->total(), 0, ',', '.') }} hasil</span>
      @if ($q !== '' || $filter)
        <a class="mm-btn sek" href="{{ route('peta-risiko-pja.master.index', ['t' => $kode]) }}"><span class="material-symbols-outlined" style="font-size:18px">filter_alt_off</span> Reset</a>
      @endif
      <noscript><button class="mm-btn sek" type="submit">Cari</button></noscript>
      @if ($bolehUbah)
        <a class="mm-btn" style="margin-left:auto" href="{{ $urlDaftar(['tambah' => 1, 'page' => null]) }}"><span class="material-symbols-outlined" style="font-size:18px">add</span> Tambah</a>
      @endif
    </form>
    <script>
      (() => {
        // Cari otomatis saat mengetik (jeda 450 ms) dan saat filter dipilih; kembali ke halaman 1.
        const f = document.getElementById('mm-cari-form'), q = document.getElementById('mm-q');
        let t = 0;
        const kirim = () => f.submit();
        q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(kirim, 450); });
        q.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(t); kirim(); } });
        f.querySelectorAll('[data-auto]').forEach((s) => s.addEventListener('change', kirim));
        // Kursor kembali ke akhir teks cari setelah halaman dimuat ulang.
        if (q.value) { q.focus(); q.setSelectionRange(q.value.length, q.value.length); }
      })();
    </script>

    @if ($bolehUbah && ($tambah || $ubah))
      @php
        $kunci = $skema['kunci'];
        $urlTutup = $urlDaftar();
        $teksRiPeta = collect($saran['ri'] ?? [])->mapWithKeys(fn ($r) => [$r[0] => $r[1]])->all();
      @endphp
      <dialog class="mm-modal" id="mm-modal" data-tutup="{{ $urlTutup }}" aria-labelledby="mm-modal-judul">
        <form method="post" action="{{ route('peta-risiko-pja.master.simpan', ['tabel' => $kode]) }}">
          @csrf
          <input type="hidden" name="_q" value="{{ $q }}">
          @if ($ubah) <input type="hidden" name="_id" value="{{ $ubah->{$kunci} }}"> @endif
          <header class="mm-modal-head">
            <div>
              <small>{{ $skema['sheet'] }} · {{ $skema['judul'] }}</small>
              <h2 id="mm-modal-judul">{{ $ubah ? 'Ubah data · '.$ubah->{$kunci} : 'Tambah data' }}</h2>
            </div>
            <a class="mm-x" href="{{ $urlTutup }}" aria-label="Tutup"><span class="material-symbols-outlined">close</span></a>
          </header>
          <div class="mm-form">
            @foreach ($skema['kolom'] as $kolom => [$label, $tipe, $aturan, $opsi])
              @php
                $nilai = old($kolom, $ubah->{$kolom} ?? ($tipe === 'bool' ? 1 : ''));
                $kunciTerkunci = ! empty($opsi['kunci']) && $ubah;
                $daftarSaran = $saran[$kolom] ?? null;
              @endphp
              @if ($tipe === 'bool')
                <label class="cek"><input type="checkbox" name="{{ $kolom }}" value="1" @checked((bool) $nilai)> {{ $label }}</label>
              @elseif ($tipe === 'pilih')
                @php $daftar = is_array($opsi['pilihan']) ? array_combine($opsi['pilihan'], $opsi['pilihan']) : ($pilihan[$opsi['pilihan']] ?? []); @endphp
                <label>{{ $label }}
                  <select name="{{ $kolom }}" @disabled($kunciTerkunci) required>
                    <option value="">— pilih —</option>
                    @foreach ($daftar as $v => $teks)
                      <option value="{{ $v }}" @selected((string) $nilai === (string) $v)>{{ $teks }}</option>
                    @endforeach
                  </select>
                </label>
              @elseif (! empty($opsi['ri']))
                <label class="lebar">{{ $label }}
                  <span class="mm-cari-in"><span class="material-symbols-outlined">search</span>
                    <input type="text" inputmode="numeric" name="{{ $kolom }}" value="{{ $nilai }}" list="mm-dl-ri" data-ri autocomplete="off" placeholder="Ketik ID atau kata dari aktivitas/bahaya lalu pilih">
                  </span>
                  <span class="mm-ri" data-ri-teks>{{ $teksRiPeta[(int) $nilai] ?? ($nilai !== '' ? 'ID baris tidak ditemukan di M01' : '') }}</span>
                </label>
              @elseif ($tipe === 'teks_panjang')
                <label class="lebar">{{ $label }}@if (! empty($opsi['satuBaris'])) <small style="text-transform:none;font-weight:500;letter-spacing:0">satu label per baris</small>@endif
                  <textarea name="{{ $kolom }}" rows="{{ ! empty($opsi['satuBaris']) ? 8 : 3 }}" @disabled($kunciTerkunci)>{{ $nilai }}</textarea></label>
              @else
                <label class="{{ in_array($kolom, ['aktivitas', 'detail', 'teks', 'nama_pja', 'ketidaksesuaian', 'subketidaksesuaian'], true) ? 'lebar-2' : '' }}">{{ $label }}
                  @if ($daftarSaran !== null)
                    <span class="mm-cari-in"><span class="material-symbols-outlined">expand_more</span>
                      <input type="text" name="{{ $kolom }}" value="{{ $nilai }}" list="mm-dl-{{ $kolom }}" autocomplete="off" @disabled($kunciTerkunci)
                        @if (! empty($opsi['isiAid'])) data-isi-aid @endif placeholder="Pilih dari daftar atau ketik baru">
                    </span>
                    <datalist id="mm-dl-{{ $kolom }}">@foreach ($daftarSaran as $v)<option value="{{ $v }}"></option>@endforeach</datalist>
                  @else
                    <input type="{{ $tipe === 'int' ? 'number' : 'text' }}" name="{{ $kolom }}" value="{{ $nilai }}" @disabled($kunciTerkunci)>
                  @endif
                </label>
              @endif
            @endforeach
          </div>
          @if (isset($saran['ri']))
            <datalist id="mm-dl-ri">@foreach ($saran['ri'] as [$ri, $teks])<option value="{{ $ri }}" label="{{ $ri }} · {{ $teks }}">{{ $ri }} · {{ $teks }}</option>@endforeach</datalist>
          @endif
          <footer class="mm-modal-foot">
            <span class="dr-mute" style="font-size:12px">Berlaku setelah <code>peta-risiko:sync</code> berikutnya · tercatat di riwayat</span>
            <span style="display:flex;gap:10px">
              <a class="mm-btn sek" href="{{ $urlTutup }}">Batal</a>
              <button class="mm-btn" type="submit"><span class="material-symbols-outlined" style="font-size:18px">save</span> {{ $ubah ? 'Simpan perubahan' : 'Tambah' }}</button>
            </span>
          </footer>
        </form>
      </dialog>
      <script>
        (() => {
          const d = document.getElementById('mm-modal');
          if (!d) return;
          const tutup = () => { location.href = d.dataset.tutup; };
          d.addEventListener('cancel', (e) => { e.preventDefault(); tutup(); });
          d.addEventListener('click', (e) => { if (e.target === d) tutup(); });
          d.showModal();
          // Dropdown baris HIRA: tampilkan teks baris terpilih di bawah isian.
          const peta = {{ \Illuminate\Support\Js::from($teksRiPeta) }};
          d.querySelectorAll('[data-ri]').forEach((inp) => inp.addEventListener('input', () => {
            const t = inp.closest('label').querySelector('[data-ri-teks]');
            const ri = parseInt(inp.value, 10);
            t.textContent = inp.value === '' ? '' : (peta[ri] || 'ID baris tidak ditemukan di M01');
            t.style.color = peta[ri] ? '' : '#b41340';
          }));
          // Memilih aktivitas yang sudah ada mengisi ID aktivitas otomatis.
          const aid = {{ \Illuminate\Support\Js::from($saran['aid'] ?? []) }};
          d.querySelectorAll('[data-isi-aid]').forEach((inp) => inp.addEventListener('change', () => {
            const f = d.querySelector('input[name="aid"]');
            if (f && aid[inp.value] !== undefined) f.value = aid[inp.value];
          }));
        })();
      </script>
    @endif

    <div class="dr-scroll">
      <table class="pr-tbl dr-tbl mm-tbl">
        <thead><tr>
          @if (! $skema['kunciManual']) <th>ID</th> @endif
          @foreach ($skema['kolom'] as $kolom => [$label]) <th>{{ $label }}</th> @endforeach
          @if ($bolehUbah) <th class="r">Aksi</th> @endif
        </tr></thead>
        <tbody>
          @forelse ($baris as $r)
            <tr>
              @if (! $skema['kunciManual']) <td class="dr-mute">{{ $r->id }}</td> @endif
              @foreach ($skema['kolom'] as $kolom => [$label, $tipe, $aturan, $opsi])
                <td>
                  @if ($tipe === 'bool')
                    <span class="mm-badge {{ $r->{$kolom} ? 'y' : 'n' }}">{{ $r->{$kolom} ? 'Ya' : 'Tidak' }}</span>
                  @elseif (! empty($opsi['ri']))
                    <b>{{ $r->{$kolom} }}</b>
                    @if (isset($teksRi[$r->{$kolom}]))
                      <span class="mm-ri clamp">{{ $teksRi[$r->{$kolom}]->bahaya }} · {{ $teksRi[$r->{$kolom}]->aktivitas }}</span>
                    @else
                      <span class="mm-ri" style="color:#b41340">baris tidak ada di M01</span>
                    @endif
                  @elseif (($opsi['pilihan'] ?? null) && ! is_array($opsi['pilihan']))
                    {{ $pilihan[$opsi['pilihan']][(string) $r->{$kolom}] ?? $r->{$kolom} }}
                  @elseif (! empty($opsi['satuBaris']))
                    @php $daftarLabel = \App\Services\PetaRisiko\Drm\Master\DrmMasterDb::pisahLabel($r->{$kolom} ?? ''); @endphp
                    @forelse (array_slice($daftarLabel, 0, 4) as $l)
                      <span class="clamp" style="display:block;margin-bottom:3px" title="{{ $l }}">• {{ $l }}</span>
                    @empty
                      <span class="dr-mute">—</span>
                    @endforelse
                    @if (count($daftarLabel) > 4)<span class="dr-mute">+{{ count($daftarLabel) - 4 }} lainnya</span>@endif
                  @else
                    <span class="clamp" title="{{ $r->{$kolom} }}">{{ $r->{$kolom} }}</span>
                  @endif
                </td>
              @endforeach
              @if ($bolehUbah)
                <td class="r" style="white-space:nowrap">
                  <a class="mm-btn sek" style="padding:6px 12px" href="{{ $urlDaftar(['ubah' => $r->{$skema['kunci']}, 'page' => $baris->currentPage()]) }}">Ubah</a>
                  <form method="post" action="{{ route('peta-risiko-pja.master.hapus', ['tabel' => $kode, 'id' => $r->{$skema['kunci']}]) }}" style="display:inline" onsubmit="return confirm('Hapus baris ini dari master? Berlaku setelah sync berikutnya.')">
                    @csrf<input type="hidden" name="_q" value="{{ $q }}">
                    <button class="mm-btn bahaya" type="submit">Hapus</button>
                  </form>
                </td>
              @endif
            </tr>
          @empty
            <tr><td colspan="{{ count($skema['kolom']) + 2 }}" class="pr-empty">Tidak ada data{{ $q !== '' ? ' yang cocok dengan "'.$q.'"' : '' }}.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mm-pag">
      <span>{{ number_format($baris->total(), 0, ',', '.') }} baris · halaman {{ $baris->currentPage() }} dari {{ max(1, $baris->lastPage()) }}</span>
      <span style="display:flex;gap:8px">
        @if ($baris->previousPageUrl()) <a class="mm-btn sek" href="{{ $baris->previousPageUrl() }}">‹ Sebelumnya</a> @endif
        @if ($baris->nextPageUrl()) <a class="mm-btn sek" href="{{ $baris->nextPageUrl() }}">Berikutnya ›</a> @endif
      </span>
    </div>
  </div>

  <div class="pr-card">
    <div class="pr-card-head"><div><b>Perubahan terakhir</b><small>15 perubahan terbaru di semua tabel master · <a href="{{ route('peta-risiko-pja.master.log') }}">lihat semua</a></small></div></div>
    <ul class="mm-log">
      @forelse ($log as $l)
        <li><span class="aksi">{{ $l->aksi }}</span><span style="flex:1">{{ $l->tabel }} · {{ $l->kunci }}</span><span class="dr-mute">{{ $l->user_nama }} · {{ \Carbon\Carbon::parse($l->dibuat)->translatedFormat('d M Y H:i') }}</span></li>
      @empty
        <li class="dr-mute">Belum ada perubahan.</li>
      @endforelse
    </ul>
  </div>
@endif
@endsection

@section('scripts')
@include('peta-risiko-pja.drm._ui')
@endsection
