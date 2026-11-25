@extends('peta-risiko-pja._layout-drm')

@section('title', 'Master Grup Lokasi & Aktivitas · Peta Risiko PJA')

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Grup lokasi & aktivitas</span></p>
  <h1 class="pr-title">Grup lokasi & aktivitas HIRA</h1>
  <p class="pr-sub">Setiap baris HIRA (aktivitas · sub aktivitas · bahaya-risiko) dengan grup lokasi (M05) tempatnya berlaku sebagai baseline, dan kelompok aktivitasnya (M02). Satu baris bisa dipakai di lebih dari satu grup. Perubahan berlaku pada <code>peta-risiko:sync</code> berikutnya.</p>
@endsection

@section('content')
<style>
  .mg-banner { display: flex; gap: 12px; align-items: flex-start; padding: 14px 18px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; line-height: 1.5; }
  .mg-banner.ok { background: #e9f7ee; color: #146c34; border: 1px solid rgba(22, 163, 74, .25); }
  .mg-banner.err { background: #fdecef; color: #9b1c31; border: 1px solid rgba(200, 16, 46, .25); }
  .mg-banner.warn { background: #fff7e6; color: #8a5300; border: 1px solid rgba(217, 119, 6, .25); }
  .mg-banner code { background: rgba(0, 0, 0, .06); padding: 1px 6px; border-radius: 6px; }
  .mg-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 20px; border-bottom: 1px solid rgba(171, 173, 175, .2); }
  .mg-bar select { padding: 8px 12px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #fff; font-size: 13px; }
  .mg-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 999px; border: 0; background: #3952bc; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; white-space: nowrap; }
  .mg-btn.sek { background: #fff; color: #2c2f31; border: 1px solid rgba(171, 173, 175, .45); }
  .mg-btn.bahaya { background: #fff; color: #b42318; border: 1px solid rgba(180, 35, 24, .35); }
  .mg-tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
  .mg-tbl th { position: sticky; top: 0; background: #f8fafc; text-align: left; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #595c5e; padding: 10px 12px; border-bottom: 1px solid rgba(171, 173, 175, .3); }
  .mg-tbl td { vertical-align: top; padding: 10px 12px; border-bottom: 1px solid rgba(171, 173, 175, .18); }
  .mg-tbl td.hira { min-width: 150px; max-width: 220px; }
  .mg-tbl td.hira small { display: block; color: #8a8d90; font-size: 11.5px; margin-top: 2px; }
  .mg-tbl td.kel { min-width: 190px; }
  .mg-tbl td.grp { min-width: 230px; }
  .mg-lv { display: inline-block; margin-top: 4px; padding: 1px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700; background: #f3f4f6; color: #4b5563; }
  .mg-lv.Significant { background: #fdecee; color: #b91c1c; } .mg-lv.High { background: #fff0e3; color: #b45309; }
  .mg-lv.Medium { background: #fef8e1; color: #92650a; } .mg-lv.Low { background: #eef8f0; color: #15803d; }
  .mg-nonaktif { display: inline-block; margin-left: 6px; padding: 0 6px; border-radius: 999px; font-size: 10px; font-weight: 700; background: #f3f4f6; color: #8a8d90; }
  .mg-chip { display: flex; align-items: flex-start; gap: 6px; margin: 0 0 6px; padding: 5px 6px 5px 10px; border-radius: 10px; background: #f1f5f9; border: 1px solid #e2e8f0; font-size: 12px; line-height: 1.35; }
  .mg-chip span { flex: 1; }
  .mg-chip form { margin: 0; }
  .mg-chip button { border: 0; background: transparent; color: #94a3b8; cursor: pointer; font-size: 16px; line-height: 1; padding: 0 2px; }
  .mg-chip button:hover { color: #b91c1c; }
  .mg-kosong { color: #b0b3b6; font-size: 12px; font-style: italic; }
  .mg-tambah summary { cursor: pointer; font-size: 12px; font-weight: 600; color: #3952bc; list-style: none; }
  .mg-tambah summary::-webkit-details-marker { display: none; }
  .mg-tambah form { display: flex; flex-direction: column; gap: 6px; margin-top: 6px; }
  .mg-tambah select { padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(171, 173, 175, .55); font-size: 12.5px; }
  .mg-tambah button { align-self: flex-start; padding: 5px 12px; border-radius: 999px; border: 0; background: #3952bc; color: #fff; font-size: 12px; font-weight: 600; cursor: pointer; }
  .mg-kel-form select { padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(171, 173, 175, .55); font-size: 12.5px; min-width: 170px; }
  .mg-scroll { overflow-x: auto; }
  .mg-bulk { display: none; flex-wrap: wrap; gap: 10px; align-items: center; padding: 10px 20px; background: #eef1fd; border-bottom: 1px solid rgba(57, 82, 188, .18); font-size: 13px; }
  .mg-bulk.on { display: flex; }
  .mg-bulk select { padding: 7px 10px; border-radius: 8px; border: 1px solid rgba(171, 173, 175, .45); font-size: 13px; }
  .mg-dl { columns: 3 260px; font-size: 12.5px; color: #475569; padding: 4px 18px 16px; }
  .mg-dl div { break-inside: avoid; padding: 2px 0; }
  .mg-tbl td.hira2 { min-width: 160px; max-width: 220px; }
  /* Chip "Asal HIRA" + isi modal (sama konsep dengan Dashboard → Per Kelompok Aktivitas). */
  .dr-hira { display: inline-flex; flex-direction: column; align-items: flex-start; gap: 1px; max-width: 190px; padding: 4px 8px; border: 1px solid rgba(57, 82, 188, .2);
    border-radius: 8px; background: #f5f7ff; font-size: 12px; font-weight: 600; color: #2c2f31; cursor: pointer; text-align: left; }
  .dr-hira:hover, .dr-hira:focus-visible { border-color: #3952bc; outline: none; }
  .dr-hira small { max-width: 172px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #595c5e; font-size: 11px; }
  .dr-hira-n { color: #3952bc; font-weight: 700; }
  .dr-hira-kosong { color: #9a5b00; font-weight: 700; }
  .dr-hira-dok { margin-bottom: 14px; }
  .dr-hira-dok-k { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 6px; font-size: 14px; }
  .dr-hira-dok th { text-align: left; color: #595c5e; font-weight: 600; }
  .dr-pl-tag { display: inline-block; padding: 1px 7px; border-radius: 999px; font-size: 11px; font-weight: 600; margin-left: 4px; background: #eef1fd; color: #3952bc; }
  .dr-pl-tag.milik { background: #e6f4ea; color: #1e7a3c; }
  .br-red { color: #b42318; font-weight: 700; }
  .dr-mute { color: #595c5e; font-size: 13px; }
  .dr-tbl td, .dr-tbl th { padding: 10px 14px; }
  .dr-tbl td { font-size: 14px; }
</style>

@if (session('pesan'))
  <div class="mg-banner ok"><span class="material-symbols-outlined">check_circle</span><div>{{ session('pesan') }}</div></div>
@endif
@if ($errors->any())
  <div class="mg-banner err"><span class="material-symbols-outlined">error</span><div>{!! implode('<br>', array_map('e', $errors->all())) !!}</div></div>
@endif
{{-- Pesan hasil aksi AJAX (tambah/keluarkan grup, ubah kelompok) tanpa reload halaman — lihat tampilkanPesan() di scripts. --}}
<div class="mg-banner" id="mg-flash" style="display:none"><span class="material-symbols-outlined" id="mg-flash-ikon"></span><div id="mg-flash-teks"></div></div>

@if (! $siap)
  <div class="mg-banner warn"><span class="material-symbols-outlined">database</span><div>Master database belum siap. Isi dulu tabel master (<code>php artisan peta-risiko:master-db --isi</code>) sebelum memakai halaman ini.</div></div>
@else
  <div class="pr-card" style="padding:0">
    <div class="mg-bar">
      <input type="search" id="mg-cari" placeholder="Cari aktivitas, sub aktivitas, atau bahaya…" aria-label="Cari" style="flex:1 1 240px;max-width:420px;padding:9px 14px;border-radius:999px;border:1px solid rgba(171,173,175,.45);background:#f8fafc;font-size:13.5px">
      <select id="mg-f-kb" aria-label="Risiko awal">
        <option value="">Semua risiko</option>
        @foreach (['Significant', 'High', 'Medium', 'Low'] as $lv)
          <option value="{{ $lv }}">{{ $lv }}</option>
        @endforeach
      </select>
      <select id="mg-f-kel" aria-label="Kelompok aktivitas">
        <option value="">Semua kelompok</option>
        @foreach ($kelompok as $kode => $nama)
          <option value="{{ $kode }}">{{ $nama }}</option>
        @endforeach
      </select>
      <select id="mg-f-grup" aria-label="Grup lokasi">
        <option value="">Semua grup</option>
        <option value="__TANPA__">Tanpa grup</option>
        @foreach ($daftarGrup as $g)
          <option value="{{ $g['gid'] }}">{{ $g['nama'] }} ({{ number_format($g['baris'], 0, ',', '.') }})</option>
        @endforeach
      </select>
      <select id="mg-f-site" aria-label="Site">
        <option value="">Semua site</option>
        @foreach ($site as $st)
          <option value="{{ $st }}">{{ $st }}</option>
        @endforeach
      </select>
      <select id="mg-f-pja" aria-label="Perusahaan" style="max-width:220px">
        <option value="">Semua perusahaan</option>
        @foreach ($perusahaan as $p)
          <option value="{{ $p }}">{{ $p }}</option>
        @endforeach
      </select>
      <button class="mg-btn sek" type="button" id="mg-reset">Reset filter</button>
      <a class="mg-btn sek" id="mg-unduh" href="{{ route('peta-risiko-pja.master.grup.unduh') }}" title="Unduh baris yang sedang tersaring (filter + kata cari) sebagai CSV (bisa dibuka di Excel), satu baris satu grup/site/perusahaan (tidak digabung)"><span class="material-symbols-outlined" style="font-size:18px">download</span> Unduh CSV</a>
    </div>

    <div class="mg-bulk" id="mg-bulk">
      <span id="mg-bulk-n"></span>
      @if ($bolehUbah)
        <form method="post" id="mg-bulk-tambah">@csrf
          <select name="gidTarget" aria-label="Grup tujuan (tambah)" required>
            <option value="">Tambahkan ke grup…</option>
            @foreach ($daftarGrup as $g)<option value="{{ $g['gid'] }}">{{ $g['nama'] }}</option>@endforeach
          </select>
          <button class="mg-btn sek" type="submit"><span class="material-symbols-outlined" style="font-size:17px">add</span> Tambahkan</button>
        </form>
        <form method="post" id="mg-bulk-hapus">@csrf
          <select name="gidTarget" aria-label="Grup sumber (keluarkan)" required>
            <option value="">Keluarkan dari grup…</option>
            @foreach ($daftarGrup as $g)<option value="{{ $g['gid'] }}">{{ $g['nama'] }}</option>@endforeach
          </select>
          <button class="mg-btn bahaya" type="submit"><span class="material-symbols-outlined" style="font-size:17px">remove_circle</span> Keluarkan</button>
        </form>
      @endif
    </div>

    <div class="mg-scroll">
      <table class="mg-tbl" id="mg-tabel" style="width:100%">
        <thead><tr>
          @if ($bolehUbah)<th style="width:30px"><input type="checkbox" id="mg-semua" aria-label="Pilih semua baris yang tampil"></th>@endif
          <th>Aktivitas</th><th>Sub aktivitas</th><th>Bahaya - risiko</th><th>Asal HIRA</th><th>Kelompok aktivitas</th><th>Grup lokasi</th>
        </tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <div class="pr-card" style="margin-top:16px;display:none" id="mg-dl-card">
    <div class="pr-card-head"><div><b id="mg-dl-judul">Detail lokasi</b><small>Semua mewarisi baris HIRA grup ini</small></div></div>
    <div class="mg-dl" id="mg-dl-isi"></div>
  </div>
  @unless ($bolehUbah)<p class="pr-meta" style="margin-top:10px">Anda hanya bisa melihat; hubungi admin untuk menjadi editor master.</p>@endunless
@endif
@endsection

@section('scripts')
@if ($siap)
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-dt@2.1.8/css/dataTables.dataTables.min.css">
<style>
  #mg-tabel_wrapper .dt-layout-row { padding: 10px 20px; margin: 0; }
  #mg-tabel_wrapper .dt-length select { border-radius: 8px; padding: 4px 8px; }
  table.mg-tbl.dataTable > thead > tr > th { padding: 10px 12px; }
</style>
{{-- DataTables 2 tetap butuh jQuery; layout _layout-drm tidak memuatnya. --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js"></script>
<script>
(function () {
  const ROWS = @json($rows);
  const DAFTAR_GRUP = @json($daftarGrup);
  const DL_PER_GRUP = @json($dlPerGrup);
  const KELOMPOK = @json($kelompok);
  const BOLEH = @json($bolehUbah);
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const URL_TAMBAH = @json(route('peta-risiko-pja.master.grup.tambah', ['gid' => 0]));
  const URL_HAPUS = @json(route('peta-risiko-pja.master.grup.baris', ['gid' => 0]));
  const URL_KELOMPOK = @json(route('peta-risiko-pja.master.grup.kelompok', ['aid' => 0]));
  const URL_HIRA = @json(route('peta-risiko-pja.hira.sumber'));
  const URL_UNDUH = @json(route('peta-risiko-pja.master.grup.unduh'));
  const PETA_ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => PETA_ESC[c]);
  const urlTambah = (gid) => URL_TAMBAH.replace(/\/0\/tambah$/, '/' + gid + '/tambah');
  const urlHapus = (gid) => URL_HAPUS.replace(/\/0\/baris$/, '/' + gid + '/baris');
  const urlKelompok = (aid) => URL_KELOMPOK.replace(/\/0\/kelompok$/, '/' + aid + '/kelompok');
  const token = () => '<input type="hidden" name="_token" value="' + esc(CSRF) + '">';
  const riHidden = (ri) => '<input type="hidden" name="ri[]" value="' + ri + '">';

  // ---- Asal HIRA (dokumen id_hira_master) per baris, sama pola dengan Dashboard → Per Kelompok Aktivitas. ----
  const hiraCache = {};

  /** Ambil asal HIRA untuk ri yang belum ada di cache (maks 300 per permintaan). */
  async function muatHira(ris) {
    const perlu = [...new Set(ris)].filter((r) => !(r in hiraCache));
    for (let i = 0; i < perlu.length; i += 300) {
      const potong = perlu.slice(i, i + 300);
      const r = await fetch(`${URL_HIRA}?ri=${potong.join(',')}`, { headers: { Accept: 'application/json' } });
      const j = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(j.pesan || `Gagal memuat HIRA (HTTP ${r.status}).`);
      potong.forEach((h) => { hiraCache[h] = (j.baris || {})[h] || []; });
    }
  }

  /** Isi sel [data-hira] di dalam el: chip "id · rev" + departemen singkat; klik → detail dokumen. */
  function isiHira(el, ris) {
    muatHira(ris).then(() => {
      el.querySelectorAll('td[data-hira]').forEach((td) => {
        const L = hiraCache[td.dataset.hira] || [], d = L[0];
        if (!d) { td.innerHTML = '<span class="dr-mute">-</span>'; return; }
        td.innerHTML = `<button type="button" class="dr-hira" aria-haspopup="dialog" title="Lihat dokumen HIRA">
            <span><b>${esc(d.idh)}</b>${d.ada ? ` · rev ${esc(d.rev)}` : ''}${L.length > 1 ? ` <span class="dr-hira-n">+${L.length - 1}</span>` : ''}</span>
            <small>${d.ada ? (d.kosong ? '<span class="dr-hira-kosong">kosong</span> ' : '') + esc(d.dept || '-') : 'tidak ditemukan di HIRA'}</small></button>`;
        td.querySelector('button').addEventListener('click', (e) => { e.stopPropagation(); bukaHira(td.dataset.hira, td.dataset.bahaya || ''); });
      });
    }).catch((e) => {
      el.querySelectorAll('td[data-hira]').forEach((td) => { td.innerHTML = `<span class="br-red" title="${esc(e.message)}">gagal</span>`; });
    });
  }

  function dialogHira() {
    let d = document.getElementById('dr-item-modal');
    if (d) return d;
    d = document.createElement('dialog');
    d.id = 'dr-item-modal';
    d.className = 'dr-item-modal';
    d.setAttribute('aria-labelledby', 'dr-item-judul');
    d.innerHTML = `<div class="dr-item-kepala"><h3 id="dr-item-judul"></h3>
        <button type="button" class="dr-item-tutup" aria-label="Tutup"><span class="material-symbols-outlined">close</span></button></div>
      <div class="dr-item-isi" id="dr-item-isi"></div>`;
    document.body.appendChild(d);
    d.querySelector('.dr-item-tutup').addEventListener('click', () => d.close());
    d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
    return d;
  }

  function bukaHira(ri, bahaya) {
    const L = hiraCache[ri] || [], d = dialogHira(), isi = d.querySelector('#dr-item-isi');
    d.querySelector('#dr-item-judul').textContent = `Asal HIRA${bahaya ? ' · ' + bahaya : ''}`;
    const baris = (k, v) => (v ? `<tr><th style="width:170px">${k}</th><td>${v}</td></tr>` : '');
    isi.innerHTML = L.map((x) => `<div class="dr-hira-dok">
        <div class="dr-hira-dok-k"><b>HIRA ${esc(x.idh)}</b>${x.ada ? ` · rev ${esc(x.rev)}` : ''}
          ${x.cocok ? '<span class="dr-pl-tag milik">memuat bahaya ini</span>' : x.kosong ? '<span class="dr-pl-tag" style="background:#fff4e5;color:#9a5b00">dokumen kosong</span>' : x.ada ? '<span class="dr-pl-tag">tidak memuat teks bahaya ini</span>' : ''}
          ${x.ada ? (x.aktif ? '<span class="dr-pl-tag">aktif</span>' : '<span class="dr-pl-tag" style="background:#fdecea;color:#b42318">tidak aktif</span>') : ''}</div>
        ${x.kosong ? '<p class="dr-mute" style="margin:0 0 6px">Dokumen ini ada di HIRA (hira_master) tetapi belum berisi bahaya-risiko, site, maupun section, sehingga tidak muncul di mv_hira_master. Nilai risiko baris baseline ini tidak berasal dari dokumen ini.</p>' : ''}
        ${x.ada ? `<table class="pr-tbl dr-tbl"><tbody>
          ${baris('Departemen', esc(x.dept))}${baris('Aktivitas spesifik', esc(x.spesifik))}
          ${baris('Aktivitas · sub', esc([x.aktivitas, x.sub].filter(Boolean).join(' · ')))}${baris('Site', esc((x.site || []).join(', ')))}
          ${baris('Bagian', esc(x.bagian))}${baris('Perusahaan', esc(x.perusahaan))}${baris('Klasifikasi', esc(x.rutin))}${baris('Diperbarui', esc(x.diperbarui))}
        </tbody></table>` : '<p class="dr-mute">ID ini tercatat di master baseline tetapi tidak ditemukan di HIRA (hira_master maupun mv_hira_master).</p>'}</div>`).join('')
      || '<p class="dr-mute">Baris baseline ini tidak punya id_hira_master di master.</p>';
    if (!d.open) d.showModal();
  }

  function chipGrup(g, ri) {
    const hapus = BOLEH
      ? '<form method="post" action="' + esc(urlHapus(g.gid)) + '" data-aksi="hapus" data-ri="' + ri + '" data-gid="' + g.gid + '"'
        + ' data-konfirmasi="Keluarkan baris ' + ri + ' dari grup ' + esc(g.nama) + '?">'
        + token() + riHidden(ri) + '<button type="submit" title="Keluarkan dari grup" aria-label="Keluarkan dari grup">×</button></form>'
      : '';
    return '<div class="mg-chip"><span>' + esc(g.nama) + '</span>' + hapus + '</div>';
  }

  function formTambahGrup(ri, dikecualikan) {
    const opsi = DAFTAR_GRUP.filter((g) => !dikecualikan.includes(g.gid))
      .map((g) => '<option value="' + g.gid + '">' + esc(g.nama) + '</option>').join('');
    if (!opsi) return '';
    return '<details class="mg-tambah"><summary>+ Tambah ke grup</summary>'
      + '<form method="post" action="' + esc(URL_TAMBAH) + '" data-aksi="tambah" data-ri="' + ri + '">' + token()
      + riHidden(ri) + '<select name="gid"><option value="">Pilih grup…</option>' + opsi + '</select>'
      + '<button type="submit">Tambahkan</button></form></details>';
  }

  const kolomGrup = { data: 'grup', orderable: false, className: 'grp', render: (d, type, r) => {
    if (type !== 'display') return d.map((g) => g.nama).join(' | ');
    const isi = d.length ? d.map((g) => chipGrup(g, r.ri)).join('') : '<div class="mg-kosong">Belum ada grup</div>';
    return isi + (BOLEH ? formTambahGrup(r.ri, d.map((g) => g.gid)) : '');
  } };

  // Asal HIRA: chip "id · rev" + departemen singkat (diisi lewat AJAX setelah draw, lihat isiHira); klik → detail dokumen.
  const kolomHira = { data: 'ri', orderable: false, className: 'hira2',
    render: (d, type) => type === 'display' ? '<span class="dr-mute">…</span>' : d,
    createdCell: (td, _d, r) => { td.dataset.hira = r.ri; td.dataset.bahaya = r.b; } };

  const kolomKelompok = { data: 'kelompok', className: 'kel', render: (d, type, r) => {
    if (type !== 'display') return (KELOMPOK[d] || d);
    if (!BOLEH) return esc(KELOMPOK[d] || d);
    const opsi = Object.entries(KELOMPOK).map(([kode, nama]) => '<option value="' + kode + '"' + (kode === d ? ' selected' : '') + '>' + esc(nama) + '</option>').join('');
    return '<form method="post" class="mg-kel-form" action="' + esc(urlKelompok(r.aid)) + '" data-aksi="kelompok" data-aid="' + r.aid + '"'
      + ' data-konfirmasi="Ubah kelompok aktivitas ini? Berlaku untuk semua baris aktivitas ini di semua grup.">'
      + token() + '<select name="kelompok" onchange="this.form.requestSubmit()">' + opsi + '</select></form>';
  } };

  const tabel = new DataTable('#mg-tabel', {
    data: ROWS,
    deferRender: true,
    stateSave: true,
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100, 250],
    order: [[BOLEH ? 1 : 0, 'asc']],
    autoWidth: false,
    language: {
      search: 'Cari:', searchPlaceholder: 'aktivitas, bahaya…',
      lengthMenu: 'Tampilkan _MENU_ baris', info: '_START_–_END_ dari _TOTAL_ baris',
      infoEmpty: 'Tidak ada baris', infoFiltered: '(disaring dari _MAX_)', zeroRecords: 'Tidak ada baris yang cocok.',
      paginate: { first: '«', previous: '‹', next: '›', last: '»' },
    },
    columns: [
      ...(BOLEH ? [{ data: 'ri', orderable: false, className: 'mg-cek', render: (d, type) => type === 'display' ? '<input type="checkbox" class="mg-baris" value="' + d + '" aria-label="Pilih baris ' + d + '">' : d }] : []),
      { data: 'a', className: 'hira', render: (d, type, r) => type !== 'display' ? d + ' ' + r.ri
          : esc(d) + '<small>ID ' + r.ri + '</small>' + (r.aktif ? '' : '<span class="mg-nonaktif">nonaktif</span>') },
      { data: 's', className: 'hira', render: (d, type) => type === 'display' ? (d ? esc(d) : '—') : d },
      { data: 'b', className: 'hira', render: (d, type, r) => type === 'display'
          ? esc(d) + '<br><span class="mg-lv ' + esc(r.kb) + '">' + esc(r.kb || '-') + '</span>' : d + ' ' + r.kb },
      kolomHira, kolomKelompok, kolomGrup,
    ],
    // Isi chip "Asal HIRA" halaman yang sedang tampil setiap draw (termasuk draw pertama saat tabel dibuat;
    // .on('draw', …) SETELAH new DataTable() sudah kelewat draw pertama karena draw itu terjadi sinkron di konstruktor).
    drawCallback: function () {
      const tds = document.querySelectorAll('#mg-tabel td[data-hira]');
      if (tds.length) isiHira(document.getElementById('mg-tabel'), [...tds].map((td) => td.dataset.hira));
    },
  });

  // Tabel disaring di bawah search bawaan DataTables; filter kustom (risiko, kelompok, grup/tanpa grup, site/perusahaan).
  const fKb = document.getElementById('mg-f-kb');
  const fKel = document.getElementById('mg-f-kel');
  const fGrup = document.getElementById('mg-f-grup');
  const fSite = document.getElementById('mg-f-site');
  const fPja = document.getElementById('mg-f-pja');
  DataTable.ext.search.push((settings, _data, idx) => {
    if (settings.nTable.id !== 'mg-tabel') return true;
    const r = ROWS[idx];
    if (fKb.value && r.kb !== fKb.value) return false;
    if (fKel.value && r.kelompok !== fKel.value) return false;
    if (fGrup.value === '__TANPA__') return !r.grup.length;
    if (fGrup.value && !r.grup.some((g) => String(g.gid) === fGrup.value)) return false;
    // Site & Perusahaan harus cocok di LOKASI YANG SAMA (bukan dicek terpisah — lihat koreksi "Per Perusahaan"
    // Master per Baris 24-25 Nov 2026, menggabungkan rata bisa membuat site+perusahaan yang sebenarnya tak
    // terkait kelihatan seolah berpasangan).
    if ((fSite.value || fPja.value) && !r.lok.some((l) =>
      (!fSite.value || l.site === fSite.value) && (!fPja.value || l.perusahaan.includes(fPja.value)))) return false;
    return true;
  });

  function tampilkanDl() {
    const gid = fGrup.value;
    const card = document.getElementById('mg-dl-card');
    if (!gid || gid === '__TANPA__') { card.style.display = 'none'; return; }
    const g = DAFTAR_GRUP.find((x) => String(x.gid) === gid);
    const dl = DL_PER_GRUP[gid] || [];
    document.getElementById('mg-dl-judul').textContent = 'Detail lokasi · ' + (g ? g.nama : gid) + ' (' + dl.length + ')';
    document.getElementById('mg-dl-isi').innerHTML = dl.length
      ? dl.map((d) => '<div>' + esc(d.site) + ' · ' + esc(d.lokasi) + ' · <b>' + esc(d.detail) + '</b></div>').join('')
      : '<div>Tidak ada detail lokasi aktif.</div>';
    card.style.display = '';
  }

  [fKb, fKel, fGrup, fSite, fPja].forEach((el) => el.addEventListener('change', () => { tabel.draw(); if (el === fGrup) tampilkanDl(); }));
  document.getElementById('mg-reset').addEventListener('click', () => {
    fKb.value = ''; fKel.value = ''; fGrup.value = ''; fSite.value = ''; fPja.value = '';
    tampilkanDl();
    tabel.search('').order([BOLEH ? 1 : 0, 'asc']).page(0).draw();
  });

  // Unduh CSV mengikuti filter + kata cari yang sedang aktif (disaring ulang di server dengan aturan yang sama).
  document.getElementById('mg-unduh').addEventListener('click', (e) => {
    const q = new URLSearchParams();
    if (fKb.value) q.set('kb', fKb.value);
    if (fKel.value) q.set('kelompok', fKel.value);
    if (fGrup.value) q.set('grup', fGrup.value);
    if (fSite.value) q.set('site', fSite.value);
    if (fPja.value) q.set('pja', fPja.value);
    if (tabel.search()) q.set('q', tabel.search());
    e.currentTarget.href = URL_UNDUH + (q.toString() ? '?' + q : '');
  });

  // Deep-link dari Alur baseline (korelasi): ?gid=X membuka grup itu langsung.
  const qGid = new URLSearchParams(location.search).get('gid');
  if (qGid && [...fGrup.options].some((o) => o.value === qGid)) { fGrup.value = qGid; tabel.draw(); tampilkanDl(); }

  if (!BOLEH) return;

  // ---- Semua form (hapus/tambah/ubah kelompok/bulk) dikirim lewat AJAX — tidak ada reload, halaman tetap di
  // posisi & filter yang sama (keputusan user 24 Nov 2026: reload itu "kritikal", bikin kehilangan posisi/filter).
  const flash = document.getElementById('mg-flash');
  let flashTimer;
  function tampilkanPesan(teks, jenis) {
    flash.className = 'mg-banner ' + jenis;
    flash.querySelector('.material-symbols-outlined').textContent = jenis === 'ok' ? 'check_circle' : 'error';
    flash.querySelector('div').textContent = teks;
    flash.style.display = 'flex';
    clearTimeout(flashTimer);
    flashTimer = setTimeout(() => { flash.style.display = 'none'; }, jenis === 'ok' ? 4000 : 8000);
  }

  async function kirim(url, formData) {
    const r = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: formData });
    const j = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(j.pesan || j.message || `Gagal (HTTP ${r.status}).`);
    return j;
  }

  function namaGrup(gid) { return (DAFTAR_GRUP.find((g) => String(g.gid) === String(gid)) || {}).nama || ('#' + gid); }
  function tambahGrupKe(ri, gid) {
    const r = ROWS.find((x) => x.ri === ri);
    if (r && !r.grup.some((g) => g.gid === gid)) { r.grup.push({ gid, nama: namaGrup(gid) }); r.grup.sort((a, b) => a.nama.localeCompare(b.nama, 'id')); }
  }
  function hapusGrupDari(ri, gid) {
    const r = ROWS.find((x) => x.ri === ri);
    if (r) r.grup = r.grup.filter((g) => g.gid !== gid);
  }
  /** Gambar ulang HANYA baris yang datanya berubah, tanpa reset halaman/urutan/pencarian yang sedang aktif. */
  function segarkanBaris(risBerubah) {
    const set = new Set(risBerubah);
    tabel.rows((idx, data) => set.has(data.ri)).invalidate('data');
    tabel.draw(false);
  }

  document.getElementById('mg-tabel').addEventListener('submit', async (e) => {
    const form = e.target;
    e.preventDefault();
    const pesan = form.dataset.konfirmasi;
    if (pesan && !confirm(pesan)) return;
    const aksi = form.dataset.aksi;
    try {
      if (aksi === 'hapus') {
        const ri = Number(form.dataset.ri), gid = Number(form.dataset.gid);
        const j = await kirim(form.action, new FormData(form));
        hapusGrupDari(ri, gid);
        segarkanBaris([ri]);
        tampilkanPesan(j.pesan, 'ok');
      } else if (aksi === 'tambah') {
        const gid = form.gid.value;
        if (!gid) { alert('Pilih grup tujuan.'); return; }
        const ri = Number(form.dataset.ri);
        const j = await kirim(urlTambah(gid), new FormData(form));
        tambahGrupKe(ri, Number(gid));
        segarkanBaris([ri]);
        tampilkanPesan(j.pesan, 'ok');
      } else if (aksi === 'kelompok') {
        const aid = Number(form.dataset.aid), kelompok = form.kelompok.value;
        const j = await kirim(form.action, new FormData(form));
        const risBerubah = ROWS.filter((r) => r.aid === aid).map((r) => { r.kelompok = kelompok; return r.ri; });
        segarkanBaris(risBerubah);
        tampilkanPesan(j.pesan, 'ok');
      }
    } catch (err) {
      tampilkanPesan(err.message, 'err');
    }
  });

  const cekSemua = document.getElementById('mg-semua');
  const bulkBar = document.getElementById('mg-bulk');
  function baitSemua() {
    return [...document.querySelectorAll('#mg-tabel tbody .mg-baris:checked')];
  }
  function perbaruiBulk() {
    const n = baitSemua().length;
    bulkBar.classList.toggle('on', n > 0);
    document.getElementById('mg-bulk-n').textContent = n + ' baris terpilih';
  }
  document.getElementById('mg-tabel').addEventListener('change', (e) => {
    if (e.target.classList.contains('mg-baris') || e.target === cekSemua) {
      if (e.target === cekSemua) document.querySelectorAll('#mg-tabel tbody .mg-baris').forEach((c) => { c.checked = cekSemua.checked; });
      perbaruiBulk();
    }
  });
  tabel.on('draw', () => { if (cekSemua) cekSemua.checked = false; perbaruiBulk(); });

  bulkBar.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const ri = baitSemua().map((c) => Number(c.value));
    if (!ri.length) { alert('Pilih minimal satu baris.'); return; }
    const gid = Number(form.gidTarget.value);
    if (!gid) { alert('Pilih grup.'); return; }
    const tambah = form.id === 'mg-bulk-tambah';
    if (!confirm(ri.length + ' baris akan ' + (tambah ? 'ditambahkan ke' : 'dikeluarkan dari') + ' grup "' + namaGrup(gid) + '". Lanjutkan?')) return;
    const fd = new FormData(form);
    ri.forEach((r) => fd.append('ri[]', r));
    try {
      const j = await kirim(tambah ? urlTambah(gid) : urlHapus(gid), fd);
      ri.forEach((r) => (tambah ? tambahGrupKe(r, gid) : hapusGrupDari(r, gid)));
      form.reset();
      segarkanBaris(ri);
      tampilkanPesan(j.pesan, 'ok');
    } catch (err) {
      tampilkanPesan(err.message, 'err');
    }
  });
})();
</script>
@endif
@endsection
