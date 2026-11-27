@extends('peta-risiko-pja._layout-drm')

@section('title', 'Lokasi & PIC · Peta Risiko PJA')

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Lokasi &amp; PIC</span></p>
  <h1 class="pr-title">Lokasi &rarr; PJA &rarr; PIC Perusahaan</h1>
  <p class="pr-sub">Satu baris = satu Detail Lokasi. PJA &amp; PIC Perusahaan melekat di level lokasi (site + lokasi) lalu diwariskan ke semua detlok-nya — PJA dari <code>drm_master_lokasi_pja</code>, PIC Perusahaan dari personil PJA Mitra Kerja-nya saja (<code>drm_master_lokasi_pic</code>), bukan dari PJA BC. Halaman ini murni untuk dilihat.</p>
@endsection

@section('content')
<style>
  .mp-banner { display: flex; gap: 12px; align-items: flex-start; padding: 14px 18px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; line-height: 1.5; background: #fff7e6; color: #8a5300; border: 1px solid rgba(217, 119, 6, .25); }
  .mp-banner code { background: rgba(0, 0, 0, .06); padding: 1px 6px; border-radius: 6px; }
  .mp-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 20px; border-bottom: 1px solid rgba(171, 173, 175, .2); }
  .mp-bar select, .mp-bar input[type="search"] { padding: 8px 12px; border-radius: 999px; border: 1px solid rgba(171, 173, 175, .45); background: #fff; font-size: 13px; }
  .mp-tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
  .mp-tbl th { position: sticky; top: 0; background: #f8fafc; text-align: left; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #595c5e; padding: 10px 12px; border-bottom: 1px solid rgba(171, 173, 175, .3); }
  .mp-tbl td { vertical-align: top; padding: 10px 12px; border-bottom: 1px solid rgba(171, 173, 175, .18); }
  .mp-tbl td.site { min-width: 90px; font-weight: 600; }
  .mp-tbl td.lok { min-width: 160px; }
  .mp-tbl td.pja, .mp-tbl td.pic { min-width: 220px; }
  .mp-chip-pja { display: inline-block; margin: 0 4px 4px 0; padding: 3px 9px; border-radius: 999px; background: #eef1fb; border: 1px solid #c7d0f5; color: #3952bc; font-size: 11.5px; }
  .mp-chip-pic { display: inline-block; margin: 0 4px 4px 0; padding: 3px 9px; border-radius: 999px; background: #ecfeff; border: 1px solid #a5f3fc; color: #155e75; font-size: 11.5px; }
  .mp-kosong { color: #b0b3b6; font-size: 12px; font-style: italic; }
  .mp-scroll { max-height: 72vh; overflow: auto; }
</style>

@if (! $siap)
  <div class="mp-banner"><span class="material-symbols-outlined">database</span><div>Master database belum siap. Isi dulu tabel master (<code>php artisan peta-risiko:master-db --isi</code>) sebelum memakai halaman ini.</div></div>
@else
  <div class="pr-card" style="padding:0">
    <div class="mp-bar">
      <input type="search" id="mp-cari" placeholder="Cari site, lokasi, PJA, atau perusahaan…" aria-label="Cari" style="flex:1 1 260px;max-width:420px;background:#f8fafc">
      <select id="mp-f-site" aria-label="Site">
        <option value="">Semua site</option>
        @foreach ($site as $st)
          <option value="{{ $st }}">{{ $st }}</option>
        @endforeach
      </select>
      <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#475569"><input type="checkbox" id="mp-f-tanpa-pic"> Hanya tanpa PIC</label>
    </div>
    <div class="mp-scroll">
      <table class="mp-tbl" id="mp-tabel" style="width:100%">
        <thead><tr><th>Site</th><th>Lokasi</th><th>Detail Lokasi</th><th>PJA</th><th>PIC Perusahaan</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
@endif
@endsection

@section('scripts')
@if ($siap)
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-dt@2.1.8/css/dataTables.dataTables.min.css">
<style>
  #mp-tabel_wrapper .dt-layout-row { padding: 10px 20px; margin: 0; }
  #mp-tabel_wrapper .dt-length select { border-radius: 8px; padding: 4px 8px; }
  #mp-tabel_wrapper .dt-search { display: none; }
</style>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js"></script>
<script>
(function () {
  const ROWS = @json($rows);
  const PETA_ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => PETA_ESC[c]);
  const $ = (id) => document.getElementById(id);

  const kolomChip = (kelas) => ({ render: (d, type) => {
    if (type !== 'display') return d.join(' | ');
    return d.length ? d.map((x) => '<span class="' + kelas + '">' + esc(x) + '</span>').join('') : '<span class="mp-kosong">—</span>';
  } });

  const tabel = new DataTable('#mp-tabel', {
    data: ROWS,
    deferRender: true,
    stateSave: true,
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100, 250],
    order: [[0, 'asc'], [1, 'asc']],
    autoWidth: false,
    language: {
      search: 'Cari:', searchPlaceholder: 'site, lokasi, PJA…',
      lengthMenu: 'Tampilkan _MENU_ baris', info: '_START_–_END_ dari _TOTAL_ lokasi',
      infoEmpty: 'Tidak ada lokasi', infoFiltered: '(disaring dari _MAX_)', zeroRecords: 'Tidak ada lokasi yang cocok.',
      paginate: { first: '«', previous: '‹', next: '›', last: '»' },
    },
    columns: [
      { data: 'site', className: 'site' },
      { data: 'lokasi', className: 'lok' },
      { data: 'detail', className: 'lok' },
      { data: 'pja', className: 'pja', orderable: false, ...kolomChip('mp-chip-pja') },
      { data: 'pic', className: 'pic', orderable: false, ...kolomChip('mp-chip-pic') },
    ],
  });

  const fCari = $('mp-cari'), fSite = $('mp-f-site'), fTanpaPic = $('mp-f-tanpa-pic');
  let ketikTimer = null;
  fCari.addEventListener('input', () => { clearTimeout(ketikTimer); ketikTimer = setTimeout(() => tabel.search(fCari.value).draw(), 200); });
  fSite.addEventListener('change', () => { tabel.column(0).search(fSite.value ? '^' + fSite.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$' : '', true, false).draw(); });

  DataTable.ext.search.push((settings, data, idx, rowData) => {
    if (settings.nTable !== tabel.table().node()) return true;
    if (!fTanpaPic.checked) return true;
    return rowData.pic.length === 0;
  });
  fTanpaPic.addEventListener('change', () => tabel.draw());
})();
</script>
@endif
@endsection
