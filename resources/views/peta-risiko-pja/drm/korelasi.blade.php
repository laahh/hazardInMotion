@extends('peta-risiko-pja._layout-drm')

@section('title', 'Alur Baseline · Peta Risiko PJA')

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Alur baseline</span></p>
  <h1 class="pr-title">Alur baseline: dari aktivitas sampai PJA</h1>
  <p class="pr-sub">Baca dari kiri ke kanan: aktivitas HIRA dipecah menjadi sub aktivitas dan bahaya-risiko, ditangkap SAP lewat label, berlaku di grup lokasi, diwarisi detail lokasi, lalu menjadi tanggung jawab PJA. Arahkan kursor ke kotak untuk menyorot jalurnya; klik untuk menaikkan seluruh jalurnya ke atas setiap kolom dan menguncinya (klik kotak lain untuk ganti, klik lagi atau Esc untuk melepas); klik dua kali untuk menjadikannya fokus.</p>
@endsection

@section('content')
<style>
  .al-alat { display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: center; margin-bottom: 14px; }
  .al-cari-wrap { position: relative; flex: 1 1 320px; max-width: 520px; }
  .al-cari { width: 100%; padding: 10px 16px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #fff; font-size: 14px; }
  .al-hasil { position: absolute; z-index: 20; top: calc(100% + 6px); left: 0; right: 0; background: #fff; border: 1px solid rgba(0, 0, 0, .1); border-radius: 14px; box-shadow: 0 16px 40px rgba(15, 23, 42, .14); max-height: 360px; overflow: auto; padding: 6px; }
  .al-hasil button { display: flex; gap: 10px; align-items: flex-start; width: 100%; text-align: left; padding: 8px 10px; border: 0; border-radius: 10px; background: transparent; cursor: pointer; font-size: 13.5px; }
  .al-hasil button:hover, .al-hasil button:focus-visible { background: #f1f4fb; outline: none; }
  .al-hasil small { display: block; color: #8a8d90; font-size: 11.5px; }
  .al-dot { flex: none; width: 10px; height: 10px; margin-top: 4px; border-radius: 3px; background: var(--c); }
  .al-alat select { padding: 8px 12px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #fff; font-size: 13px; }
  .al-dd { position: relative; }
  .al-dd summary { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #fff; font-size: 13px; font-weight: 700; cursor: pointer; list-style: none; }
  .al-dd summary::-webkit-details-marker { display: none; }
  .al-dd summary::marker { content: ''; }
  .al-dd summary .material-symbols-outlined { font-size: 18px; color: #8a8d90; }
  .al-dd[open] summary { border-color: #3952bc; color: #3952bc; }
  .al-dd[open] summary .material-symbols-outlined { color: #3952bc; transform: rotate(180deg); }
  .al-dd-panel { position: absolute; z-index: 20; top: calc(100% + 6px); left: 0; background: #fff; border: 1px solid rgba(0, 0, 0, .1); border-radius: 14px; box-shadow: 0 16px 40px rgba(15, 23, 42, .14); padding: 10px; }
  .al-kol { display: flex; flex-direction: column; gap: 2px; min-width: 190px; }
  .al-kol label { display: inline-flex; gap: 8px; align-items: center; padding: 6px 10px; border-radius: 10px; font-size: 12.5px; cursor: pointer; }
  .al-kol label:hover { background: #f1f4fb; }
  .al-tandai-cari { display: block; width: 100%; box-sizing: border-box; margin-bottom: 6px; padding: 6px 10px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); font-size: 12.5px; }
  .al-tandai-opsi { max-height: 280px; overflow: auto; min-width: 260px; }
  .al-tandai-opsi label span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .al-tandai-opsi label.al-tandai-sembunyi { display: none; }
  .al-kol input { accent-color: #3952bc; }
  .al-jejak { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; margin-bottom: 10px; font-size: 12.5px; color: #8a8d90; }
  .al-jejak button { border: 1px solid rgba(171, 173, 175, .45); background: #fff; border-radius: 999px; padding: 4px 11px; cursor: pointer; font-size: 12.5px; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .al-jejak button.kini { background: #3952bc; color: #fff; border-color: #3952bc; }
  .al-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 16px; align-items: start; }
  .al-kanvas { position: relative; background: #fff; border: 1px solid rgba(0, 0, 0, .08); border-radius: 16px; overflow: auto; max-height: 78vh; }
  .al-kanvas svg { display: block; }
  .al-muat { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(255, 255, 255, .75); font-size: 13.5px; color: #595c5e; }
  .al-kepala { font: 800 11px "Plus Jakarta Sans", system-ui, sans-serif; letter-spacing: .07em; text-transform: uppercase; fill: #595c5e; }
  .al-kepala-sub { font: 600 11px "Plus Jakarta Sans", system-ui, sans-serif; fill: #9aa0a6; }
  .al-n rect.kotak { fill: #fff; stroke: rgba(15, 23, 42, .14); stroke-width: 1; transition: opacity .15s; }
  .al-n .t1 { font: 600 12.5px "Plus Jakarta Sans", system-ui, sans-serif; fill: #1f2937; }
  .al-n .t2 { font: 500 10.5px "Plus Jakarta Sans", system-ui, sans-serif; fill: #8a8d90; }
  .al-n { cursor: pointer; transition: opacity .15s; }
  .al-n.pusat rect.kotak { stroke: #1e293b; stroke-width: 2.2; }
  .al-n.al-ditandai rect.kotak { stroke: #f59e0b; stroke-width: 2.6; fill: #fffbeb; }
  .al-n:focus-visible rect.kotak { stroke: #3952bc; stroke-width: 2.5; }
  .al-n:focus { outline: none; }
  .al-e { fill: none; stroke: rgba(100, 116, 139, .32); stroke-width: 1.2; transition: opacity .15s, stroke .15s; }
  {{-- Redup dinaikkan dari .16/.06 (keputusan user 26 Nov 2026): label & rantai kotak di luar jalur yang
       disorot tetap harus kebaca, bukan nyaris hilang — sorot jalur cukup lewat warna+tebal garis .on. --}}
  svg.sorot .al-n:not(.on) { opacity: .5; }
  svg.sorot .al-e:not(.on) { opacity: .22; }
  svg.sorot .al-e.on { stroke: #3952bc; stroke-width: 2; }
  .al-detail { background: #fff; border: 1px solid rgba(0, 0, 0, .08); border-radius: 16px; padding: 16px; position: sticky; top: 90px; }
  .al-detail h3 { margin: 0 0 6px; font-size: 11px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: #595c5e; }
  .al-detail h4 { margin: 0 0 4px; font-size: 15px; line-height: 1.35; }
  .al-detail .sub { font-size: 12.5px; color: #8a8d90; margin-bottom: 10px; }
  .al-detail ul { margin: 0 0 8px; padding: 0; list-style: none; display: grid; gap: 2px; max-height: 220px; overflow: auto; }
  .al-detail li button { display: flex; gap: 8px; align-items: flex-start; width: 100%; text-align: left; border: 0; background: transparent; padding: 5px 6px; border-radius: 8px; cursor: pointer; font-size: 12.5px; }
  .al-detail li button:hover { background: #f1f4fb; }
  .al-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; border: 0; background: #3952bc; color: #fff; font-size: 12.5px; font-weight: 600; cursor: pointer; text-decoration: none; }
  .al-btn.sek { background: #fff; color: #2c2f31; border: 1px solid rgba(171, 173, 175, .45); }
  .al-aksi { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 12px; }
  .al-leg { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(171, 173, 175, .25); font-size: 12px; color: #595c5e; }
  .al-leg i { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: var(--c); margin-right: 5px; vertical-align: -1px; }
  .al-leg b { display: block; width: 100%; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #8a8d90; }
  .al-edit-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #fff; font-size: 13px; font-weight: 700; cursor: pointer; }
  .al-pic { display: inline-block; margin: 0 4px 4px 0; padding: 3px 9px; border-radius: 999px; background: #ecfeff; border: 1px solid #a5f3fc; color: #155e75; font-size: 11.5px; }
  .al-edit-btn[aria-pressed="true"] { background: #3952bc; color: #fff; border-color: #3952bc; }
  .al-sync { display: flex; gap: 10px; align-items: center; padding: 10px 14px; border-radius: 12px; background: #fff7e6; color: #8a5300; border: 1px solid rgba(217, 119, 6, .25); font-size: 13px; margin-bottom: 10px; }
  .al-mode-edit .al-n rect.kotak { stroke-dasharray: 4 3; }
  .al-modal { width: min(680px, calc(100vw - 32px)); border: 0; border-radius: 18px; padding: 0; box-shadow: 0 30px 80px rgba(15, 23, 42, .35); }
  .al-modal::backdrop { background: rgba(15, 23, 42, .45); }
  .al-modal header { display: flex; gap: 12px; align-items: flex-start; padding: 18px 20px 12px; border-bottom: 1px solid rgba(171, 173, 175, .3); }
  .al-modal header h3 { margin: 0; font-size: 17px; line-height: 1.3; }
  .al-modal header p { margin: 4px 0 0; font-size: 12.5px; color: #8a8d90; }
  .al-modal header button { margin-left: auto; border: 0; background: transparent; cursor: pointer; color: #8a8d90; }
  .al-modal-isi { padding: 16px 20px 20px; max-height: 72vh; overflow: auto; display: grid; gap: 16px; }
  .al-f { display: grid; gap: 6px; font-size: 12px; font-weight: 700; color: #595c5e; }
  .al-f input, .al-f select, .al-f textarea { font: inherit; font-size: 14px; font-weight: 400; color: #1f2937; padding: 8px 10px; border: 1px solid rgba(171, 173, 175, .55); border-radius: 10px; }
  .al-baris { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
  .al-bagian { border-top: 1px solid rgba(171, 173, 175, .25); padding-top: 14px; display: grid; gap: 8px; }
  .al-bagian h4 { margin: 0; font-size: 12px; letter-spacing: .05em; text-transform: uppercase; color: #595c5e; }
  .al-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 6px 4px 10px; border-radius: 10px; background: #f1f5f9; border: 1px solid #e2e8f0; font-size: 12.5px; max-width: 100%; }
  .al-chip button { border: 0; background: transparent; color: #94a3b8; cursor: pointer; font-size: 16px; line-height: 1; }
  .al-chip button:hover { color: #b91c1c; }
  .al-msg { font-size: 13px; padding: 8px 12px; border-radius: 10px; }
  .al-msg.ok { background: #e9f7ee; color: #146c34; } .al-msg.err { background: #fdecef; color: #9b1c31; }
  /* Mode perbesar: seluruh alat + diagram + rincian memenuhi layar (CSS, bukan Fullscreen API, agar modal edit tetap di atas). */
  .al-wadah.penuh { position: fixed; inset: 0; z-index: 900; background: #f3f5f9; padding: 14px max(16px, env(safe-area-inset-right, 0px)) 14px max(16px, env(safe-area-inset-left, 0px)); display: flex; flex-direction: column; overflow: hidden; }
  .al-wadah.penuh .al-grid { flex: 1; min-height: 0; grid-template-rows: minmax(0, 1fr); }
  .al-wadah.penuh .al-kanvas { max-height: none; height: 100%; }
  .al-wadah.penuh .al-detail { position: static; height: 100%; overflow: auto; }
  body.al-terkunci { overflow: hidden; }
  @media (max-width: 1100px) { .al-grid { grid-template-columns: 1fr; } .al-detail { position: static; } .al-wadah.penuh .al-grid { grid-template-rows: minmax(0, 1fr) auto; } .al-wadah.penuh .al-detail { height: auto; max-height: 30vh; } }
  @media (prefers-reduced-motion: reduce) { .al-n, .al-e, .al-n rect.kotak { transition: none; } }
</style>

@if (! $siap)
  <div class="pr-card"><div class="pr-empty">Master database belum siap. Isi tabel master dulu (<code>php artisan peta-risiko:master-db --isi</code>).</div></div>
@else
  <div class="al-wadah" id="al-wadah">
  <div class="al-alat">
    <div class="al-cari-wrap">
      <input type="search" class="al-cari" id="al-cari" placeholder="Mulai dari aktivitas, bahaya, label SAP, grup lokasi, detail lokasi, atau PJA…" aria-label="Cari titik awal alur" autocomplete="off">
      <div class="al-hasil" id="al-hasil" hidden role="listbox" aria-label="Hasil pencarian"></div>
    </div>
    <details class="al-dd" id="al-kol-dd">
      <summary><span id="al-kol-label">Semua kolom</span> <span class="material-symbols-outlined">expand_more</span></summary>
      <div class="al-dd-panel"><div class="al-kol" id="al-kol" aria-label="Kolom yang ditampilkan"></div></div>
    </details>
    @php $kategoriTandai = ['aktivitas' => 'Aktivitas HIRA', 'sub' => 'Sub Aktivitas', 'grup' => 'Grup Lokasi', 'dl' => 'Detail Lokasi', 'pja' => 'PJA', 'pja_anak' => 'PJA Child']; @endphp
    @foreach ($kategoriTandai as $jenis => $nama)
      <details class="al-dd" id="al-tandai-dd-{{ $jenis }}">
        <summary><span id="al-tandai-label-{{ $jenis }}">{{ $nama }}</span> <span class="material-symbols-outlined">expand_more</span></summary>
        <div class="al-dd-panel">
          <input type="search" class="al-tandai-cari" placeholder="Cari {{ $nama }}…" autocomplete="off">
          <div class="al-kol al-tandai-opsi" id="al-tandai-opsi-{{ $jenis }}" data-jenis="{{ $jenis }}" aria-label="Tandai {{ $nama }}">
            @foreach ($dropdown[$jenis] ?? [] as $o)
              <label><input type="checkbox" value="{{ $o['id'] }}" data-label="{{ $o['label'] }}"><span>{{ $o['label'] }}</span></label>
            @endforeach
          </div>
        </div>
      </details>
    @endforeach
    <select id="al-f-site" aria-label="Site" title="Tampilkan SEMUA aktivitas yang terhubung ke site ini (mengganti alur yang sedang dibuka, bukan menyaring)">
      <option value="">Semua site</option>
      @foreach ($site as $st)
        <option value="{{ $st }}">{{ $st }}</option>
      @endforeach
    </select>
    <select id="al-f-pja" aria-label="Perusahaan" title="Tampilkan SEMUA aktivitas yang terhubung ke perusahaan ini (mengganti alur yang sedang dibuka, bukan menyaring)" style="max-width:220px">
      <option value="">Semua perusahaan</option>
      @foreach ($perusahaan as $p)
        <option value="{{ $p }}">{{ $p }}</option>
      @endforeach
    </select>
    @if ($bolehUbah)
      <button type="button" class="al-edit-btn" id="al-edit" aria-pressed="false"><span class="material-symbols-outlined" style="font-size:18px">edit</span> Mode edit</button>
    @endif
    <a class="al-edit-btn" id="al-unduh" href="{{ route('peta-risiko-pja.korelasi.unduh') }}" data-base-href="{{ route('peta-risiko-pja.korelasi.unduh') }}" title="Unduh SEMUA aktivitas (bukan cuma yang sedang difokuskan) sebagai CSV (bisa dibuka di Excel), satu baris satu bahaya-risiko — bisa jadi berkas besar"><span class="material-symbols-outlined" style="font-size:18px">download</span><span>Unduh CSV (semua)</span></a>
    <button type="button" class="al-edit-btn" id="al-penuh" aria-pressed="false" title="Perbesar diagram memenuhi layar (Esc untuk kembali)"><span class="material-symbols-outlined" style="font-size:18px">open_in_full</span><span>Perbesar</span></button>
  </div>
  <div class="al-sync" id="al-sync" hidden><span class="material-symbols-outlined">sync_problem</span><span>Master sudah diubah. Status SAP dan angka dashboard baru ikut berubah setelah <code>php artisan peta-risiko:sync</code>.</span></div>
  <div class="al-jejak" id="al-jejak" aria-label="Jejak penelusuran"></div>

  <div class="al-grid">
    <section class="al-kanvas" id="al-kanvas" aria-label="Alur baseline">
      <svg id="al-svg" role="img" aria-label="Diagram alur baseline"></svg>
      <div class="al-muat" id="al-muat">Memuat alur…</div>
      <div class="al-muat" id="al-kosong" hidden>Tidak ada data untuk kombinasi filter ini.</div>
    </section>
    <aside class="al-detail" id="al-detail" aria-live="polite">
      <h3>Rincian</h3>
      <p class="pr-meta" style="margin:0">Klik sebuah kotak untuk melihat dari mana asalnya dan ke mana mengarahnya.</p>
    </aside>
  </div>
  </div>
@endif
@endsection

@section('scripts')
@if ($siap)
<script>
(function () {
  const URL_DATA = @json(route('peta-risiko-pja.korelasi.data'));
  const URL_CARI = @json(route('peta-risiko-pja.korelasi.cari'));
  const URL_GRUP = @json(route('peta-risiko-pja.master.grup.index'));
  const URL_UBAH = @json(route('peta-risiko-pja.master.index', ['t' => 'baris']));
  const URL_SELF = @json(route('peta-risiko-pja.korelasi.index'));
  const AWAL = @json($awal);
  const NS = 'http://www.w3.org/2000/svg';
  // Kolom alur dan warnanya (warna = penanda jenis, bukan status).
  const KOLOM = {
    aktivitas: ['Aktivitas HIRA', '#16a34a'], sub: ['Sub aktivitas', '#65a30d'], bahaya: ['Bahaya-risiko', '#dc2626'],
    label: ['Label SAP', '#db2777'], grup: ['Grup lokasi', '#2563eb'], dl: ['Detail lokasi', '#60a5fa'], pja: ['PJA', '#0891b2'],
    pja_anak: ['PJA Child', '#0e7490'],
  };
  const JENIS_KOLOM = { aktivitas: 'aktivitas', sub: 'sub', bahaya: 'bahaya', obs: 'label', oak: 'label', ket: 'label', grup: 'grup', dl: 'dl', pja: 'pja', pja_anak: 'pja_anak', kelompok: 'aktivitas' };
  const WARNA_LABEL = { obs: '#db2777', oak: '#0284c7', ket: '#f59e0b' };
  const NAMA_JENIS = { aktivitas: 'Aktivitas HIRA', sub: 'Sub aktivitas', bahaya: 'Bahaya-risiko', obs: 'Label Observasi', oak: 'Label OAK', ket: 'Label Ketidaksesuaian', grup: 'Grup lokasi', dl: 'Detail lokasi', pja: 'PJA', pja_anak: 'PJA Child', kelompok: 'Kelompok aktivitas' };
  const LV = { Significant: '#b91c1c', High: '#ea580c', Medium: '#ca8a04', Low: '#15803d' };
  const STATUS = { K: ['#16a34a', 'Terkontrol'], G: ['#d97706', 'Ada gap'], T: ['#9ca3af', 'Tidak teridentifikasi'] };
  const FOKUS = ['aktivitas', 'bahaya', 'obs', 'oak', 'ket', 'grup', 'dl', 'pja', 'kelompok'];
  const H = 42, GAP = 10, ATAS = 58, LEBAR_MIN = 210;
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const potong = (s, n) => (String(s).length > n ? String(s).slice(0, n - 1) + '…' : String(s));
  const warnaSimpul = (n) => (WARNA_LABEL[n.jenis] || (KOLOM[n.kolomNama] || ['', '#64748b'])[1]);
  const tampilKolom = Object.fromEntries(Object.keys(KOLOM).map((k) => [k, true]));
  let D = null, jejak = [], posisi = {};
  // Kotak yang diklik: jalurnya (asal & tujuan) dinaikkan ke atas setiap kolom dan tetap disorot.
  let pilihan = null, edgesKini = [];

  // Dropdown multi-select (keputusan user 27 Nov 2026): cuma 6 kolom ini yang bisa disembunyikan/ditampilkan —
  // Bahaya-risiko & Label SAP sengaja tidak ikut jadi pilihan, selalu tampil.
  const KOLOM_BISA_DISEMBUNYIKAN = ['aktivitas', 'sub', 'grup', 'dl', 'pja', 'pja_anak'];
  $('al-kol').innerHTML = KOLOM_BISA_DISEMBUNYIKAN.map((k) => {
    const [nama, warna] = KOLOM[k];

    return `<label><input type="checkbox" data-kol="${k}" checked><span class="al-dot" style="--c:${warna}"></span>${nama}</label>`;
  }).join('');
  // Label tombol dropdown ikut jumlah kolom aktif — pola sama dengan "Semua site" / "Semua perusahaan".
  function perbaruiLabelKolom() {
    const n = KOLOM_BISA_DISEMBUNYIKAN.filter((k) => tampilKolom[k]).length;
    $('al-kol-label').textContent = n === KOLOM_BISA_DISEMBUNYIKAN.length ? 'Semua kolom' : n === 0 ? 'Tanpa kolom' : `${n} kolom dipilih`;
  }
  $('al-kol').querySelectorAll('input').forEach((c) => c.addEventListener('change', () => { tampilKolom[c.dataset.kol] = c.checked; perbaruiLabelKolom(); gambar(); }));
  perbaruiLabelKolom();
  // Tutup SEMUA dropdown toolbar (Kolom + 6 Tandai) saat klik di luar panelnya masing-masing.
  document.querySelectorAll('.al-dd').forEach((dd) => {
    document.addEventListener('click', (e) => { if (!e.target.closest(`#${dd.id}`)) dd.open = false; });
  });

  // Dropdown multi-select "Tandai" per kategori (keputusan user 27 Nov 2026, dikoreksi dari percobaan pertama
  // yang cuma menyorot node yang kebetulan sudah tampil — centang beberapa nilai sekaligus harus benar-benar
  // MENGGABUNGKAN pohon relasi semuanya ke satu kanvas, lihat DrmKorelasi::dariMulti). Sub Aktivitas & PJA Child
  // dipetakan ke 'aktivitas'/'pja' saat dikirim ke server (value checkbox-nya sudah id PARENT, lihat
  // daftarDropdown) karena keduanya tidak punya pembangun pohon sendiri. Label ASLI (data-label) tetap dipakai
  // utuh untuk menyorot (kelas al-ditandai di gambar()) node yang persis dicentang user, supaya beda kelihatan
  // dari node lain yang cuma ikut terbawa relasinya.
  const KUNCI_KIRIM_TANDAI = { aktivitas: 'aktivitas', sub: 'aktivitas', grup: 'grup', dl: 'dl', pja: 'pja', pja_anak: 'pja' };
  const NAMA_TANDAI = { aktivitas: 'Aktivitas HIRA', sub: 'Sub Aktivitas', grup: 'Grup Lokasi', dl: 'Detail Lokasi', pja: 'PJA', pja_anak: 'PJA Child' };
  const tandaiLabel = Object.fromEntries(KOLOM_BISA_DISEMBUNYIKAN.map((k) => [k, new Set()]));
  let fokusSebelumTandai = null;

  function terapkanTandai() {
    const payload = { aktivitas: [], grup: [], dl: [], pja: [] };
    let total = 0;
    KOLOM_BISA_DISEMBUNYIKAN.forEach((jenis) => {
      document.querySelectorAll(`#al-tandai-opsi-${jenis} input:checked`).forEach((c) => {
        payload[KUNCI_KIRIM_TANDAI[jenis]].push(c.value);
        total++;
      });
    });
    if (total === 0) {
      const f = fokusSebelumTandai;
      fokusSebelumTandai = null;
      if (f) muat(f.jenis, f.id, f.label);

      return;
    }
    if (!fokusSebelumTandai) {
      const terakhir = jejak[jejak.length - 1];
      fokusSebelumTandai = terakhir && terakhir.jenis !== 'multi' ? terakhir : (AWAL || { jenis: 'aktivitas', id: '0' });
    }
    muat('multi', JSON.stringify(payload), `${total} ditandai`);
  }

  KOLOM_BISA_DISEMBUNYIKAN.forEach((jenis) => {
    const panel = document.getElementById(`al-tandai-opsi-${jenis}`);
    if (!panel) return;
    panel.querySelectorAll('input[type="checkbox"]').forEach((c) => c.addEventListener('change', () => {
      if (c.checked) tandaiLabel[jenis].add(c.dataset.label); else tandaiLabel[jenis].delete(c.dataset.label);
      const n = tandaiLabel[jenis].size;
      $(`al-tandai-label-${jenis}`).textContent = n ? `${NAMA_TANDAI[jenis]} (${n})` : NAMA_TANDAI[jenis];
      terapkanTandai();
    }));
  });
  // Cari di dalam satu panel tandai: sembunyikan label yang tidak cocok (bukan hapus, biar centang tidak hilang).
  document.querySelectorAll('.al-tandai-cari').forEach((inp) => {
    inp.addEventListener('input', () => {
      const q = inp.value.trim().toLowerCase();
      inp.nextElementSibling.querySelectorAll('label').forEach((lb) => {
        lb.classList.toggle('al-tandai-sembunyi', q !== '' && !lb.textContent.toLowerCase().includes(q));
      });
    });
  });

  // Filter Site & Perusahaan (keputusan user 26 Nov 2026): BUKAN menyaring simpul di alur yang sedang terbuka —
  // memilih salah satu (atau dua-duanya) mengganti alur TOTAL jadi "semua aktivitas yang terhubung ke site/
  // perusahaan ini", dibaca dari server lewat jenis fokus baru 'site_perusahaan' (lihat DrmKorelasi::
  // dariSitePerusahaan). Opsi dropdown dari daftar global (dikirim server), bukan dari alur yang sedang dimuat.
  const fSite = $('al-f-site'), fPja = $('al-f-pja');
  const PEMISAH_SP = '||';
  let fokusSebelumFilter = null;
  // Tombol "Unduh" mengikuti filter yang sedang aktif di dropdown (keputusan user 26 Nov 2026) — bukan selalu
  // SEMUA aktivitas. Dipanggil setiap kali fSite/fPja berubah, baik lewat klik user maupun disetel dari muat().
  function perbaruiHrefUnduh() {
    const unduh = $('al-unduh'), base = unduh.dataset.baseHref, site = fSite.value, perusahaan = fPja.value;
    const teks = unduh.querySelector('span:last-child');
    if (!site && !perusahaan) {
      unduh.href = base;
      teks.textContent = 'Unduh CSV (semua)';
      unduh.title = 'Unduh SEMUA aktivitas (bukan cuma yang sedang difokuskan) sebagai CSV (bisa dibuka di Excel), satu baris satu bahaya-risiko — bisa jadi berkas besar';

      return;
    }
    const label = site && perusahaan ? `Site ${site} × ${perusahaan}` : site ? `Site ${site}` : `Perusahaan ${perusahaan}`;
    unduh.href = `${base}?${new URLSearchParams({ site, perusahaan })}`;
    teks.textContent = `Unduh CSV (${label})`;
    unduh.title = `Unduh aktivitas yang cocok filter ${label} sebagai CSV (bisa dibuka di Excel), satu baris satu bahaya-risiko`;
  }
  function terapkanFilterSitePerusahaan() {
    const site = fSite.value, perusahaan = fPja.value;
    if (!site && !perusahaan) {
      const f = fokusSebelumFilter;
      fokusSebelumFilter = null;
      if (f) muat(f.jenis, f.id, f.label);
      return;
    }
    if (!fokusSebelumFilter) {
      const terakhir = jejak[jejak.length - 1];
      fokusSebelumFilter = terakhir && terakhir.jenis !== 'site_perusahaan' ? terakhir : (AWAL || { jenis: 'aktivitas', id: '0' });
    }
    const label = site && perusahaan ? `Site ${site} × ${perusahaan}` : site ? `Site ${site}` : `Perusahaan ${perusahaan}`;
    muat('site_perusahaan', site + PEMISAH_SP + perusahaan, label);
  }
  [fSite, fPja].forEach((el) => el.addEventListener('change', terapkanFilterSitePerusahaan));

  // ------------------------------------------------------------ tata letak
  /** Urutan simpul per kolom: kolom pertama urut abjad, berikutnya rata-rata posisi tetangga (barycenter), dua sapuan. */
  function tataLetak(kolom, nodes, edges) {
    const per = kolom.map(() => []);
    nodes.forEach((n) => per[n.ci].push(n));
    per.forEach((L) => L.sort((a, b) => String(a.label).localeCompare(String(b.label))));
    const idx = {};
    const catat = () => per.forEach((L) => L.forEach((n, i) => { idx[n.key] = i; }));
    catat();
    const masuk = {}, keluar = {};
    edges.forEach((e) => { (masuk[e.to] ||= []).push(e.from); (keluar[e.from] ||= []).push(e.to); });
    const rata = (L) => (L && L.length ? L.reduce((s, k) => s + (idx[k] ?? 0), 0) / L.length : null);
    for (let sapu = 0; sapu < 2; sapu++) {
      for (let c = 1; c < per.length; c++) {
        per[c].forEach((n) => { n._b = rata(masuk[n.key]) ?? rata(keluar[n.key]) ?? idx[n.key]; });
        per[c].sort((a, b) => a._b - b._b || String(a.label).localeCompare(String(b.label)));
        catat();
      }
      for (let c = per.length - 2; c >= 0; c--) {
        per[c].forEach((n) => { n._b = rata(keluar[n.key]) ?? rata(masuk[n.key]) ?? idx[n.key]; });
        per[c].sort((a, b) => a._b - b._b || String(a.label).localeCompare(String(b.label)));
        catat();
      }
    }
    return per;
  }

  function el(tag, attr, parent) {
    const e = document.createElementNS(NS, tag);
    Object.entries(attr || {}).forEach(([k, v]) => e.setAttribute(k, v));
    if (parent) parent.appendChild(e);
    return e;
  }

  // ------------------------------------------------------------ gambar
  function gambar() {
    if (!D) return;
    const kolom = D.kolom.filter((k) => tampilKolom[k] && D.node.some((n) => n.kolomNama === k));
    const ci = Object.fromEntries(kolom.map((k, i) => [k, i]));
    const nodes = D.node.filter((n) => n.kolomNama in ci).map((n) => ({ ...n, ci: ci[n.kolomNama] }));
    const ada = new Set(nodes.map((n) => n.key));
    // Kolom tersembunyi: sambungkan langsung kiri ↔ kanan melewati simpul yang disembunyikan.
    const edges = jembatan(D.edge, ada);
    const per = tataLetak(kolom, nodes, edges);
    if (pilihan && !ada.has(pilihan)) pilihan = null;
    const diJalur = pilihan ? jalur(pilihan, edges).on : null;
    // Simpul di jalur pilihan naik ke atas kolom (urutan relatif tetap; sort stabil).
    if (diJalur) per.forEach((L) => L.sort((a, b) => Number(diJalur.has(b.key)) - Number(diJalur.has(a.key))));
    edgesKini = edges;
    const lebarKanvas = $('al-kanvas').clientWidth || 1000;
    const W = Math.max(LEBAR_MIN, Math.floor((lebarKanvas - 20) / Math.max(1, kolom.length)));
    const BW = W - 36, tinggi = ATAS + Math.max(1, ...per.map((L) => L.length)) * (H + GAP) + 20;
    const svg = $('al-svg');
    svg.innerHTML = '';
    svg.setAttribute('width', String(W * kolom.length + 20));
    svg.setAttribute('height', String(tinggi));
    svg.classList.remove('sorot');
    posisi = {};
    per.forEach((L, c) => L.forEach((n, i) => { posisi[n.key] = { x: 18 + c * W, y: ATAS + i * (H + GAP), n }; }));

    // Judul kolom.
    kolom.forEach((k, c) => {
      const x = 18 + c * W;
      el('rect', { x, y: 12, width: BW, height: 4, rx: 2, fill: KOLOM[k][1] }, svg);
      const t = el('text', { x, y: 34, class: 'al-kepala' }, svg);
      t.textContent = KOLOM[k][0];
      const s = el('text', { x, y: 48, class: 'al-kepala-sub' }, svg);
      const lebih = (D.potong || {})[k === 'label' ? 'label' : k] || 0;
      const nJalur = diJalur ? per[c].filter((x) => diJalur.has(x.key)).length : 0;
      s.textContent = `${per[c].length}${lebih ? ' ditampilkan · +' + lebih + ' lainnya' : ''}${diJalur ? ` · ${nJalur} di jalur` : ''}`;
    });

    // Garis dulu (di bawah kotak).
    const gGaris = el('g', {}, svg);
    edges.forEach((e) => {
      const a = posisi[e.from], b = posisi[e.to];
      if (!a || !b) return;
      const x1 = a.x + BW, y1 = a.y + H / 2, x2 = b.x, y2 = b.y + H / 2, dx = Math.max(40, (x2 - x1) / 2);
      el('path', { d: `M${x1},${y1} C${x1 + dx},${y1} ${x2 - dx},${y2} ${x2},${y2}`, class: 'al-e', 'data-from': e.from, 'data-to': e.to }, gGaris);
    });

    const maksHuruf = Math.max(14, Math.floor((BW - 26) / 6.6));
    Object.values(posisi).forEach(({ x, y, n }) => {
      const ditandai = tandaiLabel[n.jenis] && tandaiLabel[n.jenis].has(n.label);
      const g = el('g', { class: 'al-n' + (n.pusat ? ' pusat' : '') + (ditandai ? ' al-ditandai' : ''), transform: `translate(${x},${y})`, tabindex: '0', role: 'button', 'data-key': n.key,
        'aria-label': `${NAMA_JENIS[n.jenis] || n.jenis}: ${n.label}` }, svg);
      el('title', {}, g).textContent = `${NAMA_JENIS[n.jenis] || n.jenis}: ${n.label}\n${n.sub}${STATUS[n.status] ? '\nStatus SAP: ' + STATUS[n.status][1] : ''}`;
      el('rect', { class: 'kotak', width: BW, height: H, rx: 9 }, g);
      el('rect', { width: 5, height: H, rx: 2.5, fill: n.jenis === 'bahaya' ? (LV[n.kb] || '#dc2626') : warnaSimpul(n) }, g);
      const t1 = el('text', { x: 14, y: 17, class: 't1' }, g);
      t1.textContent = potong(n.label, maksHuruf);
      const t2 = el('text', { x: 14, y: 32, class: 't2' }, g);
      t2.textContent = potong(n.sub, maksHuruf + 6);
      if (STATUS[n.status]) el('circle', { cx: BW - 11, cy: H / 2, r: 5.5, fill: STATUS[n.status][0], stroke: '#fff', 'stroke-width': 1.5 }, g);
      // Selama ada kotak terpilih, kursor tidak menimpa sorot jalur pilihan (yang sudah dinaikkan ke atas).
      g.addEventListener('mouseenter', () => { if (!pilihan) sorot(n.key, edges); });
      g.addEventListener('mouseleave', () => pulihSorot());
      g.addEventListener('focus', () => { if (!pilihan) sorot(n.key, edges); });
      g.addEventListener('blur', () => pulihSorot());
      g.addEventListener('click', () => pilihJalur(n));
      g.addEventListener('dblclick', () => fokus(n));
      g.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); fokus(n); } else if (ev.key === ' ') { ev.preventDefault(); pilihJalur(n); } });
    });
    if (pilihan) sorot(pilihan, edges);
  }

  /** Relasi setelah kolom disembunyikan: simpul tersembunyi dilewati (A → tersembunyi → B menjadi A → B). */
  function jembatan(edges, ada) {
    const keluar = {};
    edges.forEach((e) => (keluar[e.from] ||= []).push(e.to));
    const hasil = new Map();
    const ikut = (asal, k, lihat) => (keluar[k] || []).forEach((t) => {
      if (lihat.has(t)) return;
      lihat.add(t);
      if (ada.has(t)) hasil.set(asal + '|' + t, { from: asal, to: t }); else ikut(asal, t, lihat);
    });
    [...ada].forEach((k) => ikut(k, k, new Set()));
    return [...hasil.values()];
  }

  /** Klik kotak: jalurnya naik ke atas & tetap disorot; klik lagi kotak yang sama untuk kembali. */
  function pilihJalur(n) {
    pilihan = pilihan === n.key ? null : n.key;
    gambar();
    $('al-kanvas').scrollTo({ top: 0, behavior: 'smooth' });
    detail(n.key, edgesKini);
    if (modeEdit) bukaEdit(n);
  }

  function lepasPilihan() {
    if (!pilihan) return false;
    pilihan = null;
    gambar();
    return true;
  }

  /** Setelah kursor pergi: kembali ke sorot pilihan (bila ada) atau tanpa sorot. */
  function pulihSorot() {
    if (pilihan && posisi[pilihan]) sorot(pilihan, edgesKini); else $('al-svg').classList.remove('sorot');
  }

  /** Simpul & relasi di jalur sebuah simpul: ke kiri (asal) dan ke kanan (tujuan). */
  function jalur(key, edges) {
    const masuk = {}, keluar = {};
    edges.forEach((e) => { (masuk[e.to] ||= []).push(e); (keluar[e.from] ||= []).push(e); });
    const on = new Set([key]), eOn = new Set();
    const jalan = (k, arah) => (arah[k] || []).forEach((e) => {
      const t = arah === masuk ? e.from : e.to;
      eOn.add(e.from + '|' + e.to);
      if (!on.has(t)) { on.add(t); jalan(t, arah); }
    });
    jalan(key, masuk);
    jalan(key, keluar);
    return { on, eOn };
  }

  /** Sorot seluruh jalur simpul. */
  function sorot(key, edges) {
    const { on, eOn } = jalur(key, edges);
    const svg = $('al-svg');
    svg.classList.add('sorot');
    svg.querySelectorAll('.al-n').forEach((g) => g.classList.toggle('on', on.has(g.dataset.key)));
    svg.querySelectorAll('.al-e').forEach((p) => p.classList.toggle('on', eOn.has(p.dataset.from + '|' + p.dataset.to)));
  }

  function detail(key, edges) {
    const p = posisi[key];
    if (!p) return;
    const n = p.n;
    const daftar = (arah) => {
      const L = edges.filter((e) => (arah === 'kiri' ? e.to === key : e.from === key)).map((e) => posisi[arah === 'kiri' ? e.from : e.to]).filter(Boolean).map((x) => x.n);
      return L.length ? `<ul>${L.map((t) => `<li><button type="button" data-key="${esc(t.key)}"><span class="al-dot" style="--c:${warnaSimpul(t)}"></span><span>${esc(t.label)}<small style="display:block;color:#8a8d90">${esc(NAMA_JENIS[t.jenis] || t.jenis)} · ${esc(t.sub)}</small></span></button></li>`).join('')}</ul>` : '<p class="pr-meta" style="margin:0 0 8px">—</p>';
    };
    const st = STATUS[n.status];
    const tautan = n.jenis === 'grup' ? `<a class="al-btn sek" href="${URL_GRUP}?gid=${encodeURIComponent(n.id)}">Edit di master grup</a>`
      : n.jenis === 'bahaya' ? `<a class="al-btn sek" href="${URL_UBAH}&ubah=${encodeURIComponent(n.id)}">Ubah baris HIRA</a>` : '';
    // PIC Perusahaan (dari PJA Child, drm_master_lokasi_pic) di Detail Lokasi — keputusan user 26 Nov 2026.
    const pic = n.jenis === 'dl' && n.pic && n.pic.length
      ? `<p class="pr-meta" style="margin:8px 0 4px"><b>PIC Perusahaan</b></p><div>${n.pic.map((p) => `<span class="al-pic">${esc(p)}</span>`).join('')}</div>` : '';
    $('al-detail').innerHTML = `<h3>${esc(NAMA_JENIS[n.jenis] || n.jenis)}</h3><h4>${esc(n.label)}</h4>
      <div class="sub">${esc(n.sub)}${st ? ` · <b style="color:${st[0]}">${st[1]}</b>` : ''}</div>
      <div class="al-aksi">${!n.pusat && FOKUS.includes(n.jenis) && n.jenis !== 'sub' ? '<button type="button" class="al-btn" id="al-fokus">Jadikan fokus</button>' : ''}${tautan}</div>
      ${pic}
      <p class="pr-meta" style="margin:8px 0 4px"><b>Berasal dari</b></p>${daftar('kiri')}
      <p class="pr-meta" style="margin:8px 0 4px"><b>Mengarah ke</b></p>${daftar('kanan')}
      <div class="al-leg"><b>Bahaya-risiko</b>${Object.entries(LV).map(([l, c]) => `<span><i style="--c:${c};border-radius:2px"></i>${l}</span>`).join('')}
        ${Object.values(STATUS).map(([c, t]) => `<span><i style="--c:${c}"></i>${t}</span>`).join('')}</div>`;
    const f = $('al-fokus');
    if (f) f.addEventListener('click', () => fokus(n));
    $('al-detail').querySelectorAll('li button').forEach((b) => b.addEventListener('click', () => {
      const t = posisi[b.dataset.key];
      if (t) { $('al-kanvas').scrollTo({ top: Math.max(0, t.y - 120), left: Math.max(0, t.x - 120), behavior: 'smooth' }); sorot(b.dataset.key, edges); detail(b.dataset.key, edges); }
    }));
  }

  function fokus(n) {
    if (n.pusat || !FOKUS.includes(n.jenis) || n.jenis === 'sub') return;
    muat(n.jenis, n.id, n.label);
  }

  async function muat(jenis, id, label) {
    $('al-muat').hidden = false;
    $('al-kosong').hidden = true;
    try {
      const r = await fetch(`${URL_DATA}?${new URLSearchParams({ jenis, id })}`, { headers: { Accept: 'application/json' } });
      const d = await r.json();
      if (!r.ok) throw new Error(d.pesan || `HTTP ${r.status}`);
      d.node.forEach((n) => { n.kolomNama = d.kolom[n.kolom]; });
      D = d;
      pilihan = null;
      // Dropdown Site/Perusahaan direset kalau alur yang dimuat BUKAN hasil filter itu sendiri (navigasi biasa:
      // klik kotak lain, pencarian, jejak, dst) — supaya tidak ada pilihan nyangkut yang tidak mewakili alur ini.
      if (jenis !== 'site_perusahaan') {
        fSite.value = '';
        fPja.value = '';
        fokusSebelumFilter = null;
      } else {
        // Sinkronkan dropdown dengan id (mis. dibuka lagi lewat klik jejak), tanpa memicu event change-nya.
        const [s, p] = id.split(PEMISAH_SP);
        fSite.value = s || '';
        fPja.value = p || '';
      }
      // Dropdown "Tandai" direset kalau alur yang dimuat BUKAN hasil gabungannya sendiri (navigasi biasa) —
      // sama alasannya dengan reset Site/Perusahaan di atas.
      if (jenis !== 'multi') {
        KOLOM_BISA_DISEMBUNYIKAN.forEach((j) => {
          tandaiLabel[j].clear();
          document.querySelectorAll(`#al-tandai-opsi-${j} input:checked`).forEach((c) => { c.checked = false; });
          $(`al-tandai-label-${j}`).textContent = NAMA_TANDAI[j];
        });
        fokusSebelumTandai = null;
      }
      perbaruiHrefUnduh();
      const pusat = d.node.find((n) => n.key === d.pusat);
      const i = jejak.findIndex((x) => x.jenis === jenis && x.id === id);
      // site_perusahaan & multi: pusat cuma item pertama yang kebetulan ketemu, bukan representasi fokusnya —
      // jejak pakai label yang dikirim pemanggilnya (terapkanFilterSitePerusahaan() / terapkanTandai()), bukan pusat.label.
      const labelJejak = jenis === 'site_perusahaan' || jenis === 'multi' ? (label || id) : (pusat ? pusat.label : (label || id));
      jejak = i >= 0 ? jejak.slice(0, i + 1) : [...jejak, { jenis, id, label: labelJejak }].slice(-8);
      gambarJejak();
      gambar();
      history.replaceState(null, '', `${URL_SELF}?${new URLSearchParams({ jenis, id })}`);
      if (pusat) detail(pusat.key, jembatan(D.edge, new Set(Object.keys(posisi))));
      $('al-kanvas').scrollTo({ top: 0, left: 0 });
    } catch (e) {
      // Diagram dikosongkan (bukan dibiarkan menampilkan hasil sebelumnya) supaya kombinasi filter yang tidak
      // beririsan tidak kelihatan seperti "masih menampilkan semua" (ditemukan user 26 Nov 2026 lewat filter
      // Site × Perusahaan yang tidak nyambung — error-nya benar, tapi diagram lama tetap nongol di kanvas).
      D = null;
      posisi = {};
      pilihan = null;
      edgesKini = [];
      $('al-svg').innerHTML = '';
      $('al-svg').removeAttribute('width');
      $('al-svg').removeAttribute('height');
      $('al-kosong').hidden = false;
      $('al-detail').innerHTML = `<h3>Rincian</h3><p class="br-red" style="margin:0">Alur gagal dimuat: ${esc(e.message)}</p>`;
    } finally {
      $('al-muat').hidden = true;
    }
  }

  function gambarJejak() {
    $('al-jejak').innerHTML = (jejak.length ? '<span>Penelusuran:</span>' : '') + jejak.map((x, i) => `<button type="button" data-i="${i}" class="${i === jejak.length - 1 ? 'kini' : ''}" title="${esc(x.label)}">${esc(potong(x.label, 30))}</button>`).join('<span aria-hidden="true">›</span>');
    $('al-jejak').querySelectorAll('button').forEach((b) => b.addEventListener('click', () => { const x = jejak[+b.dataset.i]; muat(x.jenis, x.id, x.label); }));
  }

  // Pencarian titik awal.
  let t = 0;
  const hasil = $('al-hasil');
  $('al-cari').addEventListener('input', (e) => {
    clearTimeout(t);
    const q = e.target.value.trim();
    if (q.length < 2) { hasil.hidden = true; return; }
    t = setTimeout(async () => {
      try {
        const r = await fetch(`${URL_CARI}?${new URLSearchParams({ q })}`, { headers: { Accept: 'application/json' } });
        const d = await r.json();
        hasil.innerHTML = (d.hasil || []).map((h, i) => `<button type="button" role="option" data-i="${i}"><span class="al-dot" style="--c:${WARNA_LABEL[h.jenis] || (KOLOM[JENIS_KOLOM[h.jenis]] || ['', '#999'])[1]}"></span><span>${esc(potong(h.label, 80))}<small>${esc(NAMA_JENIS[h.jenis] || h.sub)} · ${esc(h.sub)}</small></span></button>`).join('')
          || '<p class="pr-meta" style="margin:8px 10px">Tidak ditemukan.</p>';
        hasil.hidden = false;
        hasil.querySelectorAll('button').forEach((b) => b.addEventListener('click', () => { const h = d.hasil[+b.dataset.i]; hasil.hidden = true; jejak = []; muat(h.jenis, h.id, h.label); }));
      } catch (err) { hasil.innerHTML = '<p class="pr-meta" style="margin:8px 10px">Pencarian gagal.</p>'; hasil.hidden = false; }
    }, 250);
  });
  document.addEventListener('click', (e) => { if (!e.target.closest('.al-cari-wrap')) hasil.hidden = true; });
  let ubah = 0;
  window.addEventListener('resize', () => { clearTimeout(ubah); ubah = setTimeout(gambar, 200); });

  // ------------------------------------------------------------ mode edit (modal per simpul, simpan ke master)
  const BOLEH = @json($bolehUbah);
  const URL_SIMPUL = @json(route('peta-risiko-pja.korelasi.simpul'));
  const URL_SIMPAN = @json(route('peta-risiko-pja.korelasi.simpan'));
  const CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  let modeEdit = false, simpulEdit = null;
  if (BOLEH && $('al-edit')) {
    $('al-edit').addEventListener('click', () => {
      modeEdit = !modeEdit;
      $('al-edit').setAttribute('aria-pressed', String(modeEdit));
      $('al-kanvas').classList.toggle('al-mode-edit', modeEdit);
    });
  }

  function modal() {
    let d = document.getElementById('al-modal');
    if (d) return d;
    d = document.createElement('dialog');
    d.id = 'al-modal';
    d.className = 'al-modal';
    d.setAttribute('aria-labelledby', 'al-modal-judul');
    d.innerHTML = '<header><div><h3 id="al-modal-judul"></h3><p id="al-modal-sub"></p></div><button type="button" aria-label="Tutup"><span class="material-symbols-outlined">close</span></button></header><div class="al-modal-isi" id="al-modal-isi"></div>';
    document.body.appendChild(d);
    d.querySelector('header button').addEventListener('click', () => d.close());
    d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
    return d;
  }

  async function bukaEdit(n) {
    const d = modal();
    d.querySelector('#al-modal-judul').textContent = `Edit ${NAMA_JENIS[n.jenis] || n.jenis}`;
    d.querySelector('#al-modal-sub').textContent = n.label;
    const isi = d.querySelector('#al-modal-isi');
    if (!d.open) d.showModal();
    simpulEdit = n;
    // Simpul yang diedit di halaman lain / lewat simpul lain.
    if (n.jenis === 'grup') { isi.innerHTML = `<p style="margin:0">Isi grup lokasi (aktivitas & bahaya yang berlaku) diedit di halaman master grup.</p><div class="al-baris"><a class="al-btn" href="${URL_GRUP}?gid=${encodeURIComponent(n.id)}">Buka master Grup lokasi &amp; aktivitas</a></div><p class="pr-meta" style="margin:0">Untuk menambah / mengeluarkan satu bahaya dari grup ini, klik kotak bahaya-risikonya.</p>`; return; }
    if (n.jenis === 'pja') { isi.innerHTML = '<p style="margin:0">PJA penanggung jawab diatur per lokasi. Klik kotak detail lokasi untuk menambah atau melepas PJA-nya.</p>'; return; }
    if (n.jenis === 'kelompok') { isi.innerHTML = '<p style="margin:0">Kelompok aktivitas diubah dari kotak aktivitas HIRA.</p>'; return; }
    isi.innerHTML = '<p class="pr-meta" style="margin:0">Memuat…</p>';
    try {
      const r = await fetch(`${URL_SIMPUL}?${new URLSearchParams({ jenis: n.jenis, id: n.id })}`, { headers: { Accept: 'application/json' } });
      const j = await r.json();
      if (!r.ok) throw new Error(j.pesan || j.message || `HTTP ${r.status}`);
      isi.innerHTML = formEdit(n.jenis, j.data, j.opsi);
      pasangForm(isi, n, j.data);
    } catch (e) {
      isi.innerHTML = `<div class="al-msg err">${esc(e.message)}</div>`;
    }
  }

  const pilih = (nama, opsi, kini) => `<select name="${nama}">${opsi.map(([v, t]) => `<option value="${esc(v)}"${String(v) === String(kini) ? ' selected' : ''}>${esc(t)}</option>`).join('')}</select>`;
  const chip = (teks, data) => `<span class="al-chip"><span>${esc(teks)}</span><button type="button" data-lepas='${esc(JSON.stringify(data))}' aria-label="Lepas ${esc(teks)}">×</button></span>`;

  function formEdit(jenis, d, o) {
    const kel = o.kelompok.map((k) => [k.kode, k.nama]), grup = o.grup.map((g) => [g.gid, g.nama]);
    if (jenis === 'aktivitas') {
      return `<form data-aksi="aktivitas" class="al-bagian" style="border:0;padding:0"><label class="al-f">Nama aktivitas HIRA<input name="nama" maxlength="500" required value="${esc(d.nama)}"></label>
        <label class="al-f">Kelompok aktivitas (M02)${pilih('kelompok', kel, d.kelompok)}</label>
        <p class="pr-meta" style="margin:0">Berlaku untuk ${d.baris} bahaya-risiko aktivitas ini di semua grup lokasi.</p><div class="al-baris"><button class="al-btn" type="submit">Simpan</button></div></form>`;
    }
    if (jenis === 'sub') {
      return `<form data-aksi="sub" class="al-bagian" style="border:0;padding:0"><label class="al-f">Nama sub aktivitas<input name="nama" maxlength="500" value="${esc(d.sub)}"></label>
        <p class="pr-meta" style="margin:0">Berlaku untuk ${d.baris} bahaya-risiko di sub ini.</p><div class="al-baris"><button class="al-btn" type="submit">Simpan</button></div></form>`;
    }
    if (jenis === 'bahaya') {
      const label = (j, nama, ph) => `<div class="al-bagian"><h4>${nama}</h4><div class="al-baris">${(d.label[j] || []).map((l) => chip(l, { aksi: 'label', jenis: j, teks: l })).join('') || '<span class="pr-meta">Belum ada label</span>'}</div>
        <form data-aksi="label" data-jenis="${j}" class="al-baris"><input name="teks" maxlength="500" placeholder="${esc(ph)}" style="flex:1;min-width:220px;padding:7px 10px;border:1px solid rgba(171,173,175,.55);border-radius:10px"><button class="al-btn sek" type="submit">+ Tambah</button></form></div>`;
      return `<form data-aksi="bahaya" class="al-bagian" style="border:0;padding:0"><p class="pr-meta" style="margin:0">${esc(d.aktivitas)} · ${esc(d.sub || '(tanpa sub)')} · ID baris ${d.ri}</p>
          <label class="al-f">Bahaya - risiko<textarea name="bahaya" rows="2" maxlength="1000" required>${esc(d.bahaya)}</textarea></label>
          <div class="al-baris"><label class="al-f">Risiko awal${pilih('kb', o.kb.map((k) => [k, k]), d.kb)}</label>
            <label class="al-f" style="flex-direction:row;display:flex;gap:6px;align-items:center;margin-top:18px"><input type="checkbox" name="aktif"${d.aktif ? ' checked' : ''}> Aktif (masuk baseline)</label></div>
          <div class="al-baris"><button class="al-btn" type="submit">Simpan bahaya</button></div></form>`
        + (d.labelDiBaris ? label('OBS', 'Label Observasi', 'Jenis kegiatan observasi') + label('OAK', 'Label OAK', 'Aktivitas OAK / Sub aktivitas') + label('KET', 'Label Ketidaksesuaian', 'Ketidaksesuaian / Sub ketidaksesuaian') : '')
        + `<div class="al-bagian"><h4>Grup lokasi (M05)</h4><div class="al-baris">${d.grup.map((g) => chip(g.nama, { aksi: 'grup', gid: g.gid })).join('') || '<span class="pr-meta">Belum masuk grup mana pun (tidak masuk baseline)</span>'}</div>
          <form data-aksi="grup" class="al-baris">${pilih('gid', grup.filter(([g]) => !d.grup.some((x) => x.gid === g)), '')}<button class="al-btn sek" type="submit">+ Tambah ke grup</button></form></div>`;
    }
    if (['obs', 'oak', 'ket'].includes(jenis)) return formLabel(d);
    // detail lokasi
    return `<form data-aksi="dl" class="al-bagian" style="border:0;padding:0"><p class="pr-meta" style="margin:0">${esc(d.site)} · ${esc(d.lokasi)} · <b>${esc(d.detail)}</b></p>
        <div class="al-baris"><label class="al-f">Grup lokasi${pilih('gid', grup, d.gid)}</label>
          <label class="al-f" style="flex-direction:row;display:flex;gap:6px;align-items:center;margin-top:18px"><input type="checkbox" name="aktif"${d.aktif ? ' checked' : ''}> Aktif</label></div>
        <p class="pr-meta" style="margin:0">Memindah grup mengganti seluruh baseline detail lokasi ini dengan baris HIRA grup tujuan.</p>
        <div class="al-baris"><button class="al-btn" type="submit">Simpan detail lokasi</button></div></form>
      <div class="al-bagian"><h4>PJA penanggung jawab lokasi</h4><div class="al-baris">${d.pja.map((p) => chip(p, { aksi: 'pja', teks: p })).join('') || '<span class="pr-meta">Belum ada PJA</span>'}</div>
        <form data-aksi="pja" class="al-baris"><input name="teks" list="al-daftar-pja" placeholder="Pilih nama PJA…" style="flex:1;min-width:220px;padding:7px 10px;border:1px solid rgba(171,173,175,.55);border-radius:10px"><button class="al-btn sek" type="submit">+ Tambah PJA</button></form>
        <datalist id="al-daftar-pja">${o.pja.map((p) => `<option value="${esc(p)}">`).join('')}</datalist>
        <p class="pr-meta" style="margin:0">PJA berlaku untuk semua ${d.dlSeLokasi} detail lokasi di ${esc(d.site)} · ${esc(d.lokasi)}.</p></div>`;
  }

  /** Modal Label SAP: ganti nama (semua bahaya), daftar bahaya pemakai (× lepas), tambah ke bahaya lain di diagram, hapus semua. */
  function formLabel(d) {
    const NAMA = { OBS: 'Observasi', OAK: 'OAK', KET: 'Ketidaksesuaian' };
    if (!d.labelDiBaris) return '<div class="al-msg err">Label per baris belum aktif di master.</div>';
    const dipakai = new Set(d.baris.map((b) => String(b.ri)));
    const kandidat = (D ? D.node : []).filter((x) => x.jenis === 'bahaya' && !dipakai.has(String(x.id)));
    return `<form data-aksi="labelganti" class="al-bagian" style="border:0;padding:0"><label class="al-f">Teks label ${NAMA[d.jenis]}<input name="teks" maxlength="500" required value="${esc(d.teks)}"></label>
        <p class="pr-meta" style="margin:0">Mengganti teks berlaku untuk ${d.baris.length} bahaya-risiko yang memakai label ini.${d.jenis === 'KET' ? ' Format: Ketidaksesuaian / Sub ketidaksesuaian.' : ''} Teks harus sama dengan isian di laporan SAP supaya tertangkap.</p>
        <div class="al-baris"><button class="al-btn" type="submit">Simpan teks label</button></div></form>
      <div class="al-bagian"><h4>Dipakai di bahaya-risiko (${d.baris.length})</h4><div style="display:grid;gap:6px;max-height:260px;overflow:auto">${d.baris.map((b) =>
        `<div class="al-baris" style="justify-content:space-between;gap:8px"><span style="font-size:13px"><b>${esc(b.bahaya)}</b><br><span class="pr-meta">${esc(b.aktivitas)} · ${esc(b.sub || '-')} · ${esc(b.kb || '-')}</span></span>${chip('Lepas', { aksi: 'label', id: String(b.ri), jenis: d.jenis, teks: d.teks })}</div>`).join('') || '<span class="pr-meta">Belum dipakai bahaya mana pun.</span>'}</div></div>
      <div class="al-bagian"><h4>Tambahkan ke bahaya-risiko lain</h4>
        <form data-aksi="labeltambah" class="al-baris">${kandidat.length ? pilih('ri', kandidat.map((x) => [x.id, potong(x.label, 70)]), '') : '<span class="pr-meta">Semua bahaya di diagram ini sudah memakai label ini.</span>'}${kandidat.length ? '<button class="al-btn sek" type="submit">+ Tambahkan</button>' : ''}</form>
        <p class="pr-meta" style="margin:0">Pilihan diambil dari bahaya-risiko yang tampil di diagram saat ini.</p></div>
      <div class="al-bagian"><button type="button" class="al-btn" id="al-label-hapus" style="background:#b42318;justify-self:start">Hapus label dari semua bahaya (${d.baris.length})</button></div>`;
  }

  function pasangForm(isi, n, d) {
    isi.querySelectorAll('form[data-aksi]').forEach((f) => f.addEventListener('submit', (e) => {
      e.preventDefault();
      const fd = new FormData(f), aksi = f.dataset.aksi, data = { aksi, id: n.id };
      if (aksi === 'aktivitas') Object.assign(data, { nama: fd.get('nama'), kelompok: fd.get('kelompok') });
      if (aksi === 'sub') data.nama = fd.get('nama');
      if (aksi === 'bahaya') Object.assign(data, { bahaya: fd.get('bahaya'), kb: fd.get('kb'), aktif: fd.get('aktif') ? 1 : 0 });
      if (aksi === 'label') Object.assign(data, { jenis: f.dataset.jenis, teks: fd.get('teks'), tambah: 1 });
      if (aksi === 'grup') { if (!fd.get('gid')) return; Object.assign(data, { gid: fd.get('gid'), tambah: 1 }); }
      if (aksi === 'dl') {
        Object.assign(data, { gid: fd.get('gid'), aktif: fd.get('aktif') ? 1 : 0 });
        if (String(fd.get('gid')) !== String(d.gid) && !confirm('Pindahkan detail lokasi ini ke grup lain? Baseline-nya akan diganti dengan baris HIRA grup tujuan.')) return;
      }
      if (aksi === 'pja') Object.assign(data, { teks: fd.get('teks'), tambah: 1 });
      if (aksi === 'labelganti') Object.assign(data, { jenis: d.jenis, teks: fd.get('teks') });
      if (aksi === 'labeltambah') { if (!fd.get('ri')) return; Object.assign(data, { aksi: 'label', id: String(fd.get('ri')), jenis: d.jenis, teks: d.teks, tambah: 1 }); }
      if (aksi === 'labelganti' && !String(data.teks || '').trim()) return;
      if ((aksi === 'label' || aksi === 'pja') && !String(data.teks || '').trim()) return;
      kirim(data, n);
    }));
    const hapus = isi.querySelector('#al-label-hapus');
    if (hapus) hapus.addEventListener('click', () => {
      if (!confirm(`Hapus label ini dari ${d.baris.length} bahaya-risiko? Laporan SAP berlabel ini tidak akan lagi menangkap bahaya tersebut.`)) return;
      kirim({ aksi: 'labelhapus', id: n.id, jenis: d.jenis }, n);
    });
    isi.querySelectorAll('[data-lepas]').forEach((b) => b.addEventListener('click', () => {
      const x = JSON.parse(b.dataset.lepas);
      if (!confirm('Lepas hubungan ini dari master?')) return;
      // id dari tombol (mis. id bahaya di modal label) diutamakan, selain itu id simpul yang diedit.
      kirim({ id: n.id, ...x, tambah: 0 }, n);
    }));
  }

  async function kirim(data, n) {
    const isi = modal().querySelector('#al-modal-isi');
    try {
      const r = await fetch(URL_SIMPAN, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) });
      const j = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(j.pesan || (j.errors ? Object.values(j.errors).flat().join(' ') : j.message) || `HTTP ${r.status}`);
      $('al-sync').hidden = false;
      // Ganti nama sub mengubah id simpulnya ("aid|nama sub").
      // Ganti teks label mengubah id simpul label (id = teks).
      if (data.aksi === 'labelganti') n = { ...n, id: String(data.teks || '').trim().replace(/\s+/g, ' '), label: String(data.teks || '').trim() };
      if (data.aksi === 'sub') n = { ...n, id: `${String(n.id).split('|')[0]}|${String(data.nama || '').trim()}`, label: String(data.nama || '').trim() || '(tanpa sub aktivitas)' };
      // Gambar ulang alur dari master, lalu isi ulang modal dengan nilai terbaru.
      const x = jejak[jejak.length - 1];
      if (x) await muat(x.jenis, x.id, x.label);
      await bukaEdit(n);
      modal().querySelector('#al-modal-isi').insertAdjacentHTML('afterbegin', `<div class="al-msg ok">${esc(j.pesan)}</div>`);
    } catch (e) {
      isi.insertAdjacentHTML('afterbegin', `<div class="al-msg err">${esc(e.message)}</div>`);
    }
  }

  // Klik area kosong diagram melepas pilihan jalur.
  $('al-svg').addEventListener('click', (e) => { if (e.target === $('al-svg')) lepasPilihan(); });

  // ------------------------------------------------------------ perbesar / perkecil
  function aturPenuh(penuh) {
    $('al-wadah').classList.toggle('penuh', penuh);
    document.body.classList.toggle('al-terkunci', penuh);
    const b = $('al-penuh');
    b.setAttribute('aria-pressed', String(penuh));
    b.title = penuh ? 'Kembali ke ukuran biasa (Esc)' : 'Perbesar diagram memenuhi layar (Esc untuk kembali)';
    b.querySelector('.material-symbols-outlined').textContent = penuh ? 'close_fullscreen' : 'open_in_full';
    b.querySelector('span:last-child').textContent = penuh ? 'Perkecil' : 'Perbesar';
    try { sessionStorage.setItem('al-penuh', penuh ? '1' : ''); } catch (e) { /* sesi tidak tersedia */ }
    gambar();
  }
  $('al-penuh').addEventListener('click', () => aturPenuh(!$('al-wadah').classList.contains('penuh')));
  document.addEventListener('keydown', (e) => {
    // Esc menutup modal dulu (bawaan dialog); bila tidak ada modal terbuka, keluar dari mode perbesar.
    const dlg = document.getElementById('al-modal');
    if (e.key !== 'Escape' || (dlg && dlg.open)) return;
    // Esc: lepas pilihan jalur dulu, baru keluar dari mode perbesar.
    if (lepasPilihan()) return;
    if ($('al-wadah').classList.contains('penuh')) aturPenuh(false);
  });
  try { if (sessionStorage.getItem('al-penuh') === '1') aturPenuh(true); } catch (e) { /* sesi tidak tersedia */ }

  if (AWAL) muat(AWAL.jenis, String(AWAL.id));
})();
</script>
@endif
@endsection
