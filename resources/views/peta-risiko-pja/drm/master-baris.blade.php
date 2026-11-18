@extends('peta-risiko-pja._layout-drm')

@section('title', 'Master per Baris · Peta Risiko PJA')

@section('hero-aksi')
  <a class="pr-back" href="{{ route('peta-risiko-pja.index') }}"><span class="material-symbols-outlined" style="font-size:18px">dashboard</span> Dashboard</a>
  <a class="pr-back" href="{{ route('peta-risiko-pja.master.grup.index') }}"><span class="material-symbols-outlined" style="font-size:18px">account_tree</span> Grup lokasi & aktivitas</a>
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
        koneksi <code>{{ $diagnosis['koneksi'] }}</code> · host <code>{{ $diagnosis['host'] }}</code> · database <code>{{ $namaDb }}</code> · config cache {{ $diagnosis['configCache'] ? 'aktif' : 'tidak' }}
        @if ($diagnosis['error'])· error: <code>{{ $diagnosis['error'] }}</code>@endif</small>
    @endif
  </div></div>
@else
  <div class="pr-card">
    <div class="mb-bar">
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
      <button class="mb-btn sek" type="button" id="mb-reset">Reset filter</button>
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
          <th>Aktivitas</th><th>Sub aktivitas</th><th>Bahaya - risiko terkait</th>
          <th>Item Sumber Observasi</th><th>Item Sumber OAK</th><th>Item Sumber Ketidaksesuaian</th>
        </tr></thead>
        <tbody></tbody>
      </table>
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
<script>
(function () {
  const ROWS = @json($rows);
  const BOLEH = @json($bolehUbah);
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const URL_TAMBAH = @json(route('peta-risiko-pja.master.baris.tambah', ['ri' => 0]));
  const URL_HAPUS = @json(route('peta-risiko-pja.master.baris.hapus', ['jenis' => 'OBS', 'id' => 0]));
  const URL_UBAH = @json(route('peta-risiko-pja.master.index', ['t' => 'baris', 'ubah' => 0]));
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

  function chip(l, ri) {
    const kls = l.sumber === TURUNAN ? 'turunan' : (l.sumber === 'manual' ? 'manual' : '');
    const sumber = l.sumber ? '<em>' + esc(l.sumber) + '</em>' : '';
    const hapus = BOLEH
      ? '<form method="post" action="' + esc(urlHapus(l.hapus.jenis, l.hapus.id)) + '" data-konfirmasi="Hapus label ini dari baris ' + ri + '?">'
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
      + '<form method="post" action="' + esc(urlTambah(ri)) + '">' + token()
      + '<input type="hidden" name="jenis" value="' + jenis + '">' + isian + '<button type="submit">Simpan</button></form></details>';
  }

  const kolomLabel = (jenis) => ({
    data: jenis,
    orderable: false,
    className: 'lbl',
    render: (d, type, r) => {
      if (type !== 'display') return d.map((l) => l.label).join(' | ');
      const isi = d.length ? d.map((l) => chip(l, r.ri)).join('') : '<div class="mb-kosong">Belum ada label</div>';
      return isi + (BOLEH ? formTambah(jenis, r.ri) : '');
    },
  });

  const tabel = new DataTable('#mb-tabel', {
    data: ROWS,
    deferRender: true,
    stateSave: true,
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100, 250],
    order: [[0, 'asc']],
    autoWidth: false,
    language: {
      search: 'Cari:', searchPlaceholder: 'aktivitas, bahaya, label, ID…',
      lengthMenu: 'Tampilkan _MENU_ baris', info: '_START_–_END_ dari _TOTAL_ baris',
      infoEmpty: 'Tidak ada baris', infoFiltered: '(disaring dari _MAX_)', zeroRecords: 'Tidak ada baris yang cocok.',
      paginate: { first: '«', previous: '‹', next: '›', last: '»' },
    },
    columns: [
      { data: 'a', className: 'hira', render: (d, type, r) => type !== 'display' ? d + ' ' + r.ri
          : esc(d) + '<small>ID ' + r.ri + '</small>' + (BOLEH ? '<a class="mb-ubah" href="' + esc(urlUbah(r.ri)) + '">Ubah baris</a>' : '') },
      { data: 's', className: 'hira', render: (d, type) => type === 'display' ? (d ? esc(d) : '—') : d },
      { data: 'b', className: 'hira', render: (d, type, r) => type === 'display'
          ? esc(d) + '<br><span class="mb-lv ' + esc(r.kb) + '">' + esc(r.kb || '-') + '</span>' : d + ' ' + r.kb },
      kolomLabel('OBS'), kolomLabel('OAK'), kolomLabel('KET'),
    ],
  });

  const fKb = document.getElementById('mb-f-kb');
  const fKosong = document.getElementById('mb-f-kosong');
  DataTable.ext.search.push((settings, _data, idx) => {
    if (settings.nTable.id !== 'mb-tabel') return true;
    const r = ROWS[idx];
    if (fKb.value && r.kb !== fKb.value) return false;
    const k = fKosong.value;
    if (k === 'SEMUA') return !r.OBS.length && !r.OAK.length && !r.KET.length;
    if (k) return !r[k].length;
    return true;
  });
  [fKb, fKosong].forEach((el) => el.addEventListener('change', () => tabel.draw()));
  document.getElementById('mb-reset').addEventListener('click', () => {
    fKb.value = '';
    fKosong.value = '';
    tabel.search('').order([0, 'asc']).page(0).draw();
  });
  document.getElementById('mb-tabel').addEventListener('submit', (e) => {
    const pesan = e.target.dataset.konfirmasi;
    if (pesan && !confirm(pesan)) e.preventDefault();
  });
})();
</script>
@endif
@endsection
