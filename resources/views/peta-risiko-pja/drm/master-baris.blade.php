@extends('peta-risiko-pja._layout-drm')

@section('title', 'Master per Baris · Peta Risiko PJA')

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Master per Baris</span></p>
  <h1 class="pr-title">Master HIRA ↔ SAP per baris</h1>
  <p class="pr-sub">Acuan utama: setiap baris HIRA (aktivitas · sub aktivitas · bahaya-risiko) dengan label SAP yang menangkapnya. Laporan hanya dihitung untuk baris yang labelnya tercantum di sini. Perubahan berlaku pada <code>peta-risiko:sync</code> berikutnya.</p>
@endsection

@section('content')
<style>
  .mb-banner { display: flex; gap: 12px; align-items: flex-start; padding: 14px 18px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; line-height: 1.5; }
  .mb-banner.warn { background: #fff7e6; color: #8a5300; border: 1px solid rgba(217, 119, 6, .25); }
  .mb-banner.ok { background: #e9f7ee; color: #146c34; border: 1px solid rgba(22, 163, 74, .25); }
  .mb-banner.err { background: #fdecef; color: #9b1c31; border: 1px solid rgba(200, 16, 46, .25); }
  .mb-banner code { background: rgba(0, 0, 0, .06); padding: 1px 6px; border-radius: 6px; }
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
  .mb-ikk { display: inline-block; margin-top: 4px; margin-left: 4px; padding: 1px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700; background: #fdecee; color: #9b1c31; }
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
  .mb-ubah { display: inline-block; margin-top: 6px; font-size: 11.5px; font-weight: 600; color: #3952bc; text-decoration: none; }
  .mb-ubah:hover { text-decoration: underline; }
  .mb-scroll { overflow-x: auto; }
  /* Departemen asal HIRA */
  .mb-tbl td.dep { min-width: 170px; max-width: 230px; }
  .mb-dep { display: block; font-weight: 600; font-size: 12.5px; line-height: 1.35; }
  .mb-dep + .mb-dep { margin-top: 4px; }
  .mb-hid { display: block; margin-top: 2px; font-size: 11px; color: #8a8d90; }
  .mb-hid b { font-weight: 600; color: #595c5e; }
  .mb-tag { display: inline-block; margin-left: 4px; padding: 0 6px; border-radius: 999px; font-size: 10px; font-weight: 700; vertical-align: 1px; }
  .mb-site { display: block; margin-top: 6px; font-size: 11px; color: #595c5e; }
  .mb-site b { font-weight: 600; color: #3952bc; }
  .mb-oleh { display: block; margin-top: 4px; font-size: 11px; color: #8a8d90; }
  .mb-oleh a { color: #3952bc; text-decoration: none; } .mb-oleh a:hover { text-decoration: underline; }
  .mb-unggah { display: inline-flex; align-items: center; gap: 6px; }
  .mb-unggah input[type=file] { font-size: 12.5px; max-width: 230px; }
  .mb-tag.cocok { background: #e9f7ee; color: #146c34; } .mb-tag.kosong { background: #fff7e6; color: #8a5300; } .mb-tag.hilang { background: #fdecef; color: #9b1c31; }
  /* Switch pov: Per Baris / Per Perusahaan */
  .mb-switch { display: inline-flex; border: 1px solid rgba(171, 173, 175, .45); border-radius: 999px; overflow: hidden; }
  .mb-switch-btn { border: 0; background: #fff; color: #595c5e; font-size: 12.5px; font-weight: 600; padding: 8px 14px; cursor: pointer; }
  .mb-switch-btn.mb-aktif { background: #3952bc; color: #fff; }
  tr.mb-grp td { background: #eef1f6; font-weight: 700; padding: 9px 14px; border-top: 2px solid #c7ccd6; font-size: 12.5px; color: #1f2937; }
  .mb-muat-lagi { border: 1px solid rgba(171, 173, 175, .45); background: #fff; color: #3952bc; font-size: 12.5px; font-weight: 600; padding: 9px 18px; border-radius: 999px; cursor: pointer; }
  .mb-muat-lagi:hover { background: #f1f4fd; }
</style>

@if (session('pesan'))
  <div class="mb-banner ok"><span class="material-symbols-outlined">check_circle</span><div>{{ session('pesan') }}</div></div>
@endif
@if ($errors->any())
  <div class="mb-banner err"><span class="material-symbols-outlined">error</span><div>{!! implode('<br>', array_map('e', $errors->all())) !!}</div></div>
@endif

@if ($siap && $terisi && ($hiraGagal ?? false))
  <div class="mb-banner warn"><span class="material-symbols-outlined">cloud_off</span><div>Departemen asal HIRA belum bisa dimuat (ClickHouse tidak terjangkau). Kolom departemen kosong; coba muat ulang beberapa saat lagi.</div></div>
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
        koneksi <code>{{ $diagnosis['koneksi'] }}</code> · host <code>{{ $diagnosis['host'] }}</code> · database <code>{{ $namaDb }}</code> · config cache {{ $diagnosis['configCache'] ? 'aktif' : 'tidak' }}
        @if ($diagnosis['error'])· error: <code>{{ $diagnosis['error'] }}</code>@endif</small>
    @endif
  </div></div>
@else
  <div class="pr-card">
    <div class="mb-bar">
      <div class="mb-switch" role="tablist" aria-label="Lihat dari">
        <button type="button" class="mb-switch-btn mb-aktif" data-mode="baris">Per Aktivitas</button>
        <button type="button" class="mb-switch-btn" data-mode="perusahaan">Per Perusahaan</button>
      </div>
      <select id="mb-f-kb" aria-label="Risiko awal">
        <option value="">Semua risiko</option>
        @foreach (['Significant', 'High', 'Medium', 'Low'] as $lv)
          <option value="{{ $lv }}">{{ $lv }}</option>
        @endforeach
      </select>
      <select id="mb-f-kosong" aria-label="Filter label">
        <option value="">Semua baris</option>
        <option value="OBS">Tanpa label Observasi</option>
        <option value="OAK">Tanpa label OAK</option>
        <option value="KET">Tanpa label Ketidaksesuaian</option>
        <option value="SEMUA">Tanpa label sama sekali</option>
      </select>
      <select id="mb-f-ikk" aria-label="IKK">
        <option value="">Semua (IKK/non-IKK)</option>
        <option value="YA">IKK saja</option>
        <option value="TIDAK">Bukan IKK saja</option>
      </select>
      <select id="mb-f-site" aria-label="Site">
        <option value="">Semua site</option>
        @foreach ($site as $st)
          <option value="{{ $st }}">{{ $st }}</option>
        @endforeach
      </select>
      <select id="mb-f-pja" aria-label="Perusahaan" style="max-width:220px;display:none">
        <option value="">— Pilih perusahaan —</option>
        @foreach ($perusahaan as $p)
          <option value="{{ $p }}">{{ $p }}</option>
        @endforeach
      </select>
      <select id="mb-f-dep" aria-label="Departemen asal HIRA" style="max-width:280px">
        <option value="">Semua departemen</option>
        @foreach ($departemen as $d)
          <option value="{{ $d }}">{{ $d }}</option>
        @endforeach
      </select>
      <button class="mb-btn sek" type="button" id="mb-reset">Reset filter</button>
      <a class="mb-btn sek" id="mb-unduh" href="{{ route('peta-risiko-pja.master.baris.unduh') }}" title="Unduh baris yang sedang tersaring (filter + kata cari) sebagai Excel"><span class="material-symbols-outlined" style="font-size:18px">download</span> Unduh Excel</a>
      @if ($bolehUbah)
        <form class="mb-unggah" method="post" action="{{ route('peta-risiko-pja.master.baris.unggah') }}" enctype="multipart/form-data">
          @csrf
          <input type="file" name="berkas" accept=".xlsx" required aria-label="Berkas Excel hasil unduhan yang sudah diedit">
          <button class="mb-btn" type="submit"><span class="material-symbols-outlined" style="font-size:18px">upload</span> Unggah Excel</button>
        </form>
      @endif
      @if ($bolehUbah)
        <a class="mb-btn sek" href="{{ route('peta-risiko-pja.master.index', ['t' => 'baris', 'tambah' => 1]) }}"><span class="material-symbols-outlined" style="font-size:18px">add</span> Tambah baris HIRA</a>
      @endif
    </div>

    <datalist id="mb-obs">@foreach ($saranObs as $s)<option value="{{ $s }}">@endforeach</datalist>
    <datalist id="mb-oak">@foreach ($saranOak as $s)<option value="{{ $s }}">@endforeach</datalist>
    <datalist id="mb-k">@foreach ($saranK as $s)<option value="{{ $s }}">@endforeach</datalist>
    <datalist id="mb-sk">@foreach ($saranSk as $s)<option value="{{ $s }}">@endforeach</datalist>

    <div class="mb-scroll">
      <table class="mb-tbl" id="mb-tabel" style="width:100%">
        <thead><tr>
          <th>Aktivitas</th><th>Sub aktivitas</th><th>Bahaya - risiko terkait</th><th>Departemen <small style="text-transform:none;letter-spacing:0;font-weight:500">(asal HIRA)</small></th>
          <th>Item Sumber Observasi</th><th>Item Sumber OAK</th><th>Item Sumber Ketidaksesuaian</th>
        </tr></thead>
        <tbody></tbody>
      </table>
    </div>
    {{-- Ganti pagination bernomor jadi "Muat lebih banyak" (keputusan user 27 Nov 2026) — lihat bangunTabel(). --}}
    <div style="display:flex;justify-content:center;padding:14px 0">
      <button type="button" id="mb-muat-lagi" class="mb-muat-lagi" hidden></button>
    </div>
  </div>
@endif
@endsection

@section('scripts')
@if ($siap && $terisi)
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-dt@2.1.8/css/dataTables.dataTables.min.css">
<style>
  #mb-tabel_wrapper .dt-layout-row { padding: 10px 20px; margin: 0; }
  #mb-tabel_wrapper .dt-search input { min-width: 280px; padding: 7px 12px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #f8fafc; }
  #mb-tabel_wrapper .dt-length select { border-radius: 8px; padding: 4px 8px; }
  table.mb-tbl.dataTable > thead > tr > th { padding: 10px 12px; }
</style>
{{-- DataTables 2 tetap butuh jQuery; layout _layout-drm tidak memuatnya. --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
  const ROWS = @json($rows);
  const BOLEH = @json($bolehUbah);
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const URL_TAMBAH = @json(route('peta-risiko-pja.master.baris.tambah', ['ri' => 0]));
  const URL_HAPUS = @json(route('peta-risiko-pja.master.baris.hapus', ['jenis' => 'OBS', 'id' => 0]));
  const URL_UBAH = @json(route('peta-risiko-pja.master.index', ['t' => 'baris', 'ubah' => 0]));
  const URL_UNDUH = @json(route('peta-risiko-pja.master.baris.unduh'));
  const URL_RIWAYAT = @json(route('peta-risiko-pja.master.log', ['tabel' => 'drm_master_baris']));
  const TURUNAN = @json(\App\Services\PetaRisiko\Drm\Master\DrmMasterDb::SUMBER_TURUNAN_OAK);
  const JENIS = {
    OBS: { nama: 'Observasi', dl: 'mb-obs', ph: 'Jenis kegiatan observasi' },
    OAK: { nama: 'OAK', dl: 'mb-oak', ph: 'Aktivitas OAK / Sub aktivitas' },
    KET: { nama: 'Ketidaksesuaian', dl: null, ph: null },
  };
  const PETA_ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => PETA_ESC[c]);
  // URL dibangun dari route asli (placeholder 0) agar tetap benar bila prefix route berubah.
  const urlTambah = (ri) => URL_TAMBAH.replace(/\/0\/label$/, '/' + ri + '/label');
  const urlHapus = (jenis, id) => URL_HAPUS.replace(/\/label\/OBS\/0\/hapus$/, '/label/' + encodeURIComponent(jenis) + '/' + id + '/hapus');
  const urlUbah = (ri) => URL_UBAH.replace(/ubah=0$/, 'ubah=' + ri);
  const token = () => '<input type="hidden" name="_token" value="' + esc(CSRF) + '">';

  function chip(l, ri, kategori) {
    const kls = l.sumber === TURUNAN ? 'turunan' : (l.sumber === 'manual' ? 'manual' : '');
    const sumber = l.sumber ? '<em>' + esc(l.sumber) + '</em>' : '';
    // class mb-form-hapus + data-kategori (keputusan user 27 Nov 2026): dipakai submit handler AJAX supaya
    // hapus/tambah label TIDAK reload halaman (DataTables kehilangan posisi halaman/pencarian yang sedang aktif).
    const hapus = BOLEH
      ? '<form method="post" class="mb-form-hapus" data-kategori="' + kategori + '" action="' + esc(urlHapus(l.hapus.jenis, l.hapus.id)) + '" data-konfirmasi="Hapus label ini dari baris ' + ri + '?">'
        + token() + '<input type="hidden" name="label" value="' + esc(l.label) + '">'
        + '<button type="submit" title="Hapus label" aria-label="Hapus label">×</button></form>'
      : '';
    return '<div class="mb-chip ' + kls + '"><span>' + esc(l.label) + sumber + '</span>' + hapus + '</div>';
  }

  function formTambah(jenis, ri) {
    const j = JENIS[jenis];
    const isian = jenis === 'KET'
      ? '<input name="k" list="mb-k" maxlength="300" required placeholder="Ketidaksesuaian"><input name="sk" list="mb-sk" maxlength="300" required placeholder="Sub ketidaksesuaian">'
      : '<input name="teks" list="' + j.dl + '" maxlength="500" required placeholder="' + esc(j.ph) + '">';
    return '<details class="mb-tambah"><summary>+ Tambah label ' + j.nama + '</summary>'
      + '<form method="post" class="mb-form-tambah" data-kategori="' + jenis + '" action="' + esc(urlTambah(ri)) + '">' + token()
      + '<input type="hidden" name="jenis" value="' + jenis + '">' + isian + '<button type="submit">Simpan</button></form></details>';
  }

  const kolomLabel = (jenis) => ({
    data: jenis,
    orderable: false,
    className: 'lbl',
    render: (d, type, r) => {
      if (type !== 'display') return d.map((l) => l.label).join(' | ');
      const isi = d.length ? d.map((l) => chip(l, r.ri, jenis)).join('') : '<div class="mb-kosong">Belum ada label</div>';
      return isi + (BOLEH ? formTambah(jenis, r.ri) : '');
    },
  });

  /**
   * Departemen asal baris: dari dokumen HIRA (id_hira_master master). Dokumen yang teks bahayanya cocok = sumber
   * atribut risiko saat sync (tag "cocok"); "kosong" = header HIRA ada tetapi tanpa isi; "tidak ada" = ID tidak ditemukan.
   */
  function kolomDep(d) {
    if (!d.length) return '<div class="mb-kosong">Tanpa ID HIRA</div>';
    const perDept = {};
    d.forEach((x) => { const k = x.ada ? (x.dept || '(tanpa departemen)') : '(ID HIRA tidak ditemukan)'; (perDept[k] ||= []).push(x); });
    return Object.keys(perDept).map((dep) => '<span class="mb-dep">' + esc(dep) + '</span>' + perDept[dep].map((x) =>
      '<span class="mb-hid">HIRA <b>' + x.idh + '</b>' + (x.ada ? ' · rev ' + x.rev + (x.aktif ? '' : ' · nonaktif') : '')
        + (x.cocok ? '<span class="mb-tag cocok" title="Teks bahaya baris ini ada di dokumen HIRA ini">cocok</span>' : '')
        + (x.kosong ? '<span class="mb-tag kosong" title="Dokumen HIRA ada tetapi belum berisi bahaya/site (tidak ada di mv_hira_master)">kosong</span>' : '')
        + (x.ada && x.deptAktif === false ? '<span class="mb-tag hilang" title="Departemen pemilik dokumen HIRA ini sudah dinonaktifkan di OBDS — kemungkinan dokumen yatim/duplikat, lihat dokumen lain yang masih aktif">departemen nonaktif</span>' : '')
        + (!x.ada ? '<span class="mb-tag hilang">tidak ada</span>' : '') + '</span>').join('')).join('');
  }

  // Kolom Aktivitas / Sub aktivitas / Bahaya-risiko sama persis di kedua pov (baseline-nya cuma beda: baris apa
  // adanya, atau dikelompokkan per perusahaan lewat header suntikan di drawCallback — lihat rowsUntukPerusahaan).
  const kolomABS = [
    { data: 'a', className: 'hira', render: (d, type, r) => type !== 'display' ? d + ' ' + r.ri
        : esc(d) + '<small>ID ' + r.ri + '</small>' + (BOLEH ? '<a class="mb-ubah" href="' + esc(urlUbah(r.ri)) + '">Ubah baris</a>' : '')
          + (r.u ? '<span class="mb-oleh">Diubah ' + esc(r.u[1]) + ' oleh ' + esc(r.u[0] || '-') + ' · <a href="' + esc(URL_RIWAYAT + '&kunci=' + r.ri) + '">riwayat</a></span>' : '') },
    { data: 's', className: 'hira', render: (d, type) => type === 'display' ? (d ? esc(d) : '—') : d },
    { data: 'b', className: 'hira', render: (d, type, r) => type === 'display'
        ? esc(d) + '<br><span class="mb-lv ' + esc(r.kb) + '">' + esc(r.kb || '-') + '</span>' + (r.ikk ? '<span class="mb-ikk" title="Item Sumber OAK baris ini cocok daftar aktivitas IKK (/peta-risiko-pja/master, tab Aktivitas IKK)">IKK</span>' : '')
        : d + ' ' + r.kb + (r.ikk ? ' ikk' : '') },
  ];
  const kolomLabelSet = [kolomLabel('OBS'), kolomLabel('OAK'), kolomLabel('KET')];

  function kolomsAktivitas() {
    return [
      ...kolomABS,
      { data: 'h', className: 'dep', render: (d, type, r) => type !== 'display'
          ? d.map((x) => x.dept + ' ' + x.idh).join(' ') + ' ' + r.st.join(' ')
          : kolomDep(d) + '<span class="mb-site" title="Site tempat baris ini dihitung (grup lokasi → detail lokasi; MARINE / EKSPLORASI menurut departemen HIRA)">Site: '
            + (r.st.length ? '<b>' + r.st.map(esc).join(', ') + '</b>' : '<i>tidak ada detail lokasi</i>') + '</span>' },
      ...kolomLabelSet,
    ];
  }

  // Pov Perusahaan: Departemen tetap, tapi "Site" dipersempit jadi "Lokasi" khusus lokasi tempat PERUSAHAAN itu
  // terhubung ke baris ini (bukan semua lokasi baris) — supaya asal nama perusahaan tetap bisa ditelusuri.
  function kolomsPerusahaan() {
    return [
      ...kolomABS,
      { data: 'h', className: 'dep', render: (d, type, r) => type !== 'display'
          ? d.map((x) => x.dept + ' ' + x.idh).join(' ') + ' ' + r.lok.join(' ')
          : kolomDep(d) + '<span class="mb-site" title="Lokasi tempat perusahaan ini terhubung ke baris ini">Lokasi: '
            + (r.lok.length ? '<b>' + r.lok.map(esc).join(', ') + '</b>' : '<i>tidak diketahui</i>') + '</span>' },
      ...kolomLabelSet,
    ];
  }

  /**
   * Pov Perusahaan (koreksi 2026-11-24): baseline-nya SATU perusahaan yang dipilih dulu di dropdown #mb-f-pja —
   * rantainya Perusahaan → (PJA-nya) → Lokasi & Detail Lokasi (M06) → Group Lokasi (M05) → Baris HIRA →
   * Aktivitas/Sub/Bahaya, cuma untuk perusahaan ITU, BUKAN semua perusahaan sekaligus. Dihitung per perusahaan
   * (bukan satu array gabungan semua perusahaan × semua baris — itu yang dulu bikin "total 99.767 baris" muncul
   * di info tabel padahal belum ada yang dipilih: datanya SENDIRI sudah sebesar itu sebelum difilter). Di-cache
   * per (perusahaan, site) supaya pindah-pindah dropdown ke kombinasi yang sama tidak hitung ulang.
   *
   * koreksi 2026-11-25: filter Site juga ikut membatasi lokasi PJA yang dipakai (bukan cuma jadi gerbang baris —
   * itu dulu bikin baris tetap tampil dengan lokasi dari site LAIN ikut kelihatan di kolom Lokasi, asal salah
   * satu PJA-nya kebetulan ada di site terpilih). Satu HIRA/aktivitas bisa dipakai di banyak site sekaligus
   * (departemen pemilik HIRA ≠ site lokasinya), jadi yang benar difilter adalah PJA lokasi itu sendiri: site +
   * perusahaan yang dipilih di dropdown (keputusan user 25 Nov 2026).
   */
  const CACHE_PERUSAHAAN = new Map();
  function rowsUntukPerusahaan(nama, site) {
    const kunci = nama + '|' + (site || '');
    if (CACHE_PERUSAHAAN.has(kunci)) return CACHE_PERUSAHAAN.get(kunci);
    const out = [];
    ROWS.forEach((r) => {
      const lokSet = {};
      r.pja.forEach((p) => {
        if (!p.perusahaan.includes(nama)) return;
        if (site && p.site !== site) return;
        // "Lokasi" di pov ini = lokasi + detail lokasi (M06, label SAP yang sama dipakai di /master/grup) yang
        // benar-benar menghubungkan grup baris ini ke PJA/perusahaan itu — rantai PJA → lokasi/detail lokasi →
        // aktivitas yang sudah termapping di master/grup (keputusan user 24 Nov 2026).
        lokSet[p.site + ' · ' + p.lokasi + (p.detail && p.detail.length ? ' (' + p.detail.join(', ') + ')' : '')] = true;
      });
      if (Object.keys(lokSet).length) out.push({ ...r, perusahaan: nama, lok: Object.keys(lokSet).sort() });
    });
    out.sort((a, b) => a.a.localeCompare(b.a, 'id', { sensitivity: 'base' }));
    CACHE_PERUSAHAAN.set(kunci, out);
    return out;
  }

  let mode = 'baris';
  let tabel = null;
  const fKb = document.getElementById('mb-f-kb');
  const fKosong = document.getElementById('mb-f-kosong');
  const fIkk = document.getElementById('mb-f-ikk');
  const fDep = document.getElementById('mb-f-dep');
  const fSite = document.getElementById('mb-f-site');
  const fPja = document.getElementById('mb-f-pja');
  const PESAN_PILIH_PERUSAHAAN = 'Pilih perusahaan di atas dulu untuk menampilkan Aktivitas yang terhubung lewat Lokasi & Detail Lokasinya.';

  function bangunTabel() {
    if (tabel) { tabel.destroy(); tabel = null; }
    document.querySelector('#mb-tabel tbody').innerHTML = '';
    tabel = new DataTable('#mb-tabel', {
      data: mode === 'perusahaan' ? (fPja.value ? rowsUntukPerusahaan(fPja.value, fSite.value) : []) : ROWS,
      deferRender: true,
      pageLength: 25,
      lengthMenu: [10, 25, 50, 100, 250],
      order: mode === 'perusahaan' ? [] : [[0, 'asc']],
      autoWidth: false,
      // Ganti pagination bernomor jadi tombol "Muat lebih banyak" (keputusan user 27 Nov 2026) —
      // lihat tombolMuatLagi()/aturTombolMuat() di bawah.
      layout: { bottomEnd: null },
      language: {
        search: 'Cari:', searchPlaceholder: 'aktivitas, bahaya, label, ID…',
        lengthMenu: 'Tampilkan _MENU_ baris', info: '_START_–_END_ dari _TOTAL_ baris',
        infoEmpty: 'Tidak ada baris', infoFiltered: '(disaring dari _MAX_)',
        // emptyTable: sumber data KOSONG (mode Per Perusahaan sebelum dipilih, lihat rowsUntukPerusahaan).
        // zeroRecords: sumber data ADA tapi tersaring habis oleh filter/kata cari lain.
        emptyTable: mode === 'perusahaan' && ! fPja.value ? PESAN_PILIH_PERUSAHAAN : 'Tidak ada baris.',
        zeroRecords: mode === 'perusahaan' && ! fPja.value ? PESAN_PILIH_PERUSAHAAN : 'Tidak ada baris yang cocok.',
        paginate: { first: '«', previous: '‹', next: '›', last: '»' },
      },
      columns: mode === 'perusahaan' ? kolomsPerusahaan() : kolomsAktivitas(),
      // Pov Perusahaan: suntik baris header "Perusahaan: NAMA (N baris)" di atas (data sudah 1 perusahaan saja,
      // lihat rowsUntukPerusahaan) — teknik resmi DataTables untuk grouping tanpa plugin RowGroup terpisah.
      drawCallback: function (settings) {
        if (mode === 'perusahaan') {
          const api = new DataTable.Api(settings);
          let terakhir = null;
          api.rows({ page: 'current' }).every(function () {
            const d = this.data();
            if (d.perusahaan !== terakhir) {
              const total = api.rows({ search: 'applied' }).data().toArray().filter((x) => x.perusahaan === d.perusahaan).length;
              $(this.node()).before('<tr class="mb-grp"><td colspan="7">Perusahaan: <b>' + esc(d.perusahaan)
                + '</b> <span style="font-weight:400;color:#767a7d">(' + total + ' baris)</span></td></tr>');
              terakhir = d.perusahaan;
            }
          });
        }
        aturTombolMuat();
      },
    });
    aturTombolMuat();
    DataTable.ext.search.length = 0;
    DataTable.ext.search.push((settings, _data, idx) => {
      if (settings.nTable.id !== 'mb-tabel') return true;
      const r = new DataTable.Api(settings).row(idx).data();
      if (!r) return true;
      if (fKb.value && r.kb !== fKb.value) return false;
      if (fIkk.value === 'YA' && !r.ikk) return false;
      if (fIkk.value === 'TIDAK' && r.ikk) return false;
      if (fDep.value && !r.h.some((x) => x.dept === fDep.value)) return false;
      if (fSite.value && !(mode === 'perusahaan' ? r.lok.some((l) => l.startsWith(fSite.value + ' ·')) : r.st.includes(fSite.value))) return false;
      const k = fKosong.value;
      if (k === 'SEMUA') return !r.OBS.length && !r.OAK.length && !r.KET.length;
      if (k) return !r[k].length;
      return true;
    });
  }

  const tombolMuat = document.getElementById('mb-muat-lagi');
  const TAMBAHAN_MUAT = 25;
  function aturTombolMuat() {
    if (!tabel) return;
    const info = tabel.page.info();
    const sisa = info.recordsDisplay - info.end;
    if (sisa <= 0) {
      tombolMuat.hidden = true;
      return;
    }
    tombolMuat.hidden = false;
    tombolMuat.textContent = `Muat ${Math.min(TAMBAHAN_MUAT, sisa)} lagi (${sisa} tersisa)`;
  }
  tombolMuat.addEventListener('click', () => {
    tabel.page.len(tabel.page.len() + TAMBAHAN_MUAT).draw(false);
  });

  function gantiMode(baru) {
    if (mode === baru) return;
    mode = baru;
    document.querySelectorAll('.mb-switch-btn').forEach((b) => b.classList.toggle('mb-aktif', b.dataset.mode === mode));
    fPja.style.display = mode === 'perusahaan' ? '' : 'none';
    if (mode !== 'perusahaan') fPja.value = '';
    bangunTabel();
  }
  document.querySelectorAll('.mb-switch-btn').forEach((b) => b.addEventListener('click', () => gantiMode(b.dataset.mode)));

  bangunTabel();

  // Ganti perusahaan ATAU site di mode Per Perusahaan = ganti SUMBER data tabel (lihat rowsUntukPerusahaan, site
  // ikut membatasi lokasi PJA-nya sejak koreksi 2026-11-25), jadi perlu bangunTabel() (bukan cuma tabel.draw())
  // supaya DataTables memuat ulang data barunya, bukan cuma menyaring data lama.
  fPja.addEventListener('change', () => { if (mode === 'perusahaan') bangunTabel(); });
  fSite.addEventListener('change', () => {
    if (mode === 'perusahaan') { bangunTabel(); return; }
    tabel.draw();
  });
  [fKb, fKosong, fIkk, fDep].forEach((el) => el.addEventListener('change', () => {
    tabel.settings()[0].oLanguage.sZeroRecords = mode === 'perusahaan' && ! fPja.value ? PESAN_PILIH_PERUSAHAAN : 'Tidak ada baris yang cocok.';
    tabel.draw();
  }));
  document.getElementById('mb-reset').addEventListener('click', () => {
    const adaPerusahaan = mode === 'perusahaan' && fPja.value;
    fKb.value = '';
    fKosong.value = '';
    fIkk.value = '';
    fDep.value = '';
    fSite.value = '';
    fPja.value = '';
    tabel.settings()[0].oLanguage.sZeroRecords = mode === 'perusahaan' ? PESAN_PILIH_PERUSAHAAN : 'Tidak ada baris yang cocok.';
    if (adaPerusahaan) { bangunTabel(); return; } // sumber data ikut kosong (lihat rowsUntukPerusahaan)
    tabel.search('').order(mode === 'perusahaan' ? [] : [0, 'asc']).page(0).draw();
  });
  // Unduh Excel mengikuti filter + kata cari yang sedang aktif (disaring ulang di server dengan aturan yang sama).
  document.getElementById('mb-unduh').addEventListener('click', (e) => {
    const q = new URLSearchParams();
    if (fKb.value) q.set('kb', fKb.value);
    if (fKosong.value) q.set('kosong', fKosong.value);
    if (fIkk.value) q.set('ikk', fIkk.value);
    if (fDep.value) q.set('dep', fDep.value);
    if (fSite.value) q.set('site', fSite.value);
    if (mode === 'perusahaan' && fPja.value) q.set('pja', fPja.value);
    if (tabel.search()) q.set('q', tabel.search());
    e.currentTarget.href = URL_UNDUH + (q.toString() ? '?' + q : '');
  });
  // Toast SweetAlert2 (keputusan user 27 Nov 2026), dipakai berhasil & gagal simpan/hapus label.
  const toast = Swal.mixin({ toast: true, position: 'top-end', timer: 2200, showConfirmButton: false });

  // Tambah/hapus label lewat AJAX (keputusan user 27 Nov 2026): form biasa (full page reload) membuat DataTables
  // kembali ke halaman 1 & kehilangan pencarian/urutan yang sedang aktif — menjengkelkan kalau sedang di halaman
  // jauh (mis. 30). Baris yang diubah di-invalidate+redraw tanpa reset posisi (draw(false)), bukan bangunTabel().
  document.getElementById('mb-tabel').addEventListener('submit', (e) => {
    const form = e.target;
    const pesan = form.dataset.konfirmasi;
    if (pesan && !confirm(pesan)) { e.preventDefault(); return; }
    e.preventDefault();
    const kategori = form.dataset.kategori;
    const tr = form.closest('tr');
    const baris = (kategori && tr) ? tabel.row(tr).data() : null;
    if (!baris) return;
    const tambah = form.classList.contains('mb-form-tambah');
    const tombol = form.querySelector('button[type="submit"]');
    if (tombol) tombol.disabled = true;
    const fd = new FormData(form);
    fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body: fd })
      .then((r) => r.json().then((j) => (r.ok ? j : Promise.reject(j))))
      .then((j) => {
        if (tambah) {
          baris[kategori].push(j.label);
          form.reset();
          const det = form.closest('details');
          if (det) det.open = false;
        } else {
          const teks = fd.get('label');
          baris[kategori] = baris[kategori].filter((l) => l.label !== teks);
        }
        tabel.row(tr).invalidate('data').draw(false);
        toast.fire({ icon: 'success', title: j.pesan || 'Berhasil disimpan.' });
      })
      .catch((j) => {
        toast.fire({ icon: 'error', title: (j && j.pesan) || 'Gagal menyimpan, coba lagi.' });
        if (tombol) tombol.disabled = false;
      });
  });
})();
</script>
@endif
@endsection
