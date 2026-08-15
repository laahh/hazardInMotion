@extends('dms.layouts.app')

@section('title', 'Fatigue Baseline Monitor')

@php
    // ---------------------------------------------------------------------
    // MOCKUP DATA — dummy, deterministik (bukan dari database).
    // Begitu desain disetujui, ganti blok ini dengan query ke bcsid.dms_alert
    // (baseline per driver_sid per shift) dan hapus badge "Data Dummy" di header.
    // ---------------------------------------------------------------------
    function fmGenerateHistory(string $seed, int $n, float $mean, float $std, float $startFactor, float $endFactor, float $noise): array
    {
        mt_srand(crc32($seed));
        $start = $mean + $startFactor * $std;
        $end = $mean + $endFactor * $std;
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $t = $i / ($n - 1);
            $base = $start + ($end - $start) * $t;
            $wobble = ((mt_rand(0, 1000) / 1000) - 0.5) * 2 * $noise * $std;
            $v = $base + $wobble;
            if ($v < 0) {
                $v = abs($v * 0.4);
            }
            $out[] = round($v, 3);
        }
        $out[$n - 1] = round($end, 3);
        mt_srand();
        return $out;
    }

    function fmEwma(array $hist, float $alpha): array
    {
        $out = [];
        $prev = null;
        foreach ($hist as $i => $v) {
            $prev = $i === 0 ? $v : $alpha * $v + (1 - $alpha) * $prev;
            $out[] = round($prev, 3);
        }
        return $out;
    }

    $N = 24;
    $ALPHA = 0.2;

    $rawOperators = [
        ['sid' => 'X7QRT', 'nama' => 'Rudi Hartono', 'site' => 'LMO', 'unit' => 'Heavy Dump Truck', 'mean' => 0.15, 'std' => 0.06, 'sf' => -0.6, 'ef' => 6.2, 'freq' => 2, 'sev' => 2, 'noise' => 0.55],
        ['sid' => 'M2VDA', 'nama' => 'Slamet Wijaya', 'site' => 'LMO', 'unit' => 'Heavy Dump Truck', 'mean' => 0.20, 'std' => 0.07, 'sf' => -0.4, 'ef' => 1.9, 'freq' => 1, 'sev' => 2, 'noise' => 0.6],
        ['sid' => 'D9KRP', 'nama' => 'Yusuf Maulana', 'site' => 'BMO 1', 'unit' => 'Prime Mover', 'mean' => 0.22, 'std' => 0.06, 'sf' => -0.3, 'ef' => 3.3, 'freq' => 2, 'sev' => 1, 'noise' => 0.6],
        ['sid' => 'B4LNC', 'nama' => 'Hendra Saputra', 'site' => 'BMO 1', 'unit' => 'Double Trailer', 'mean' => 0.18, 'std' => 0.08, 'sf' => 0.1, 'ef' => 0.25, 'freq' => 0, 'sev' => 1, 'noise' => 0.7],
        ['sid' => 'F6WZE', 'nama' => 'Dian Permata', 'site' => 'BMO 2', 'unit' => 'Light Vehicle', 'mean' => 0.10, 'std' => 0.05, 'sf' => -0.2, 'ef' => 1.4, 'freq' => 1, 'sev' => 0, 'noise' => 0.7],
        ['sid' => 'K1PXM', 'nama' => 'Wahyu Setiadi', 'site' => 'GMO', 'unit' => 'Heavy Dump Truck', 'mean' => 0.19, 'std' => 0.06, 'sf' => -0.5, 'ef' => 1.6, 'freq' => 1, 'sev' => 2, 'noise' => 0.6],
        ['sid' => 'T8HDV', 'nama' => 'Bambang Kurniawan', 'site' => 'GMO', 'unit' => 'Bus', 'mean' => 0.06, 'std' => 0.03, 'sf' => 0.2, 'ef' => -0.3, 'freq' => 0, 'sev' => 0, 'noise' => 0.8],
        ['sid' => 'Q3JBS', 'nama' => 'Irfan Hidayat', 'site' => 'SMO', 'unit' => 'Prime Mover', 'mean' => 0.21, 'std' => 0.07, 'sf' => -0.4, 'ef' => 3.0, 'freq' => 2, 'sev' => 1, 'noise' => 0.6],
        ['sid' => 'R5NPY', 'nama' => 'Taufik Ramadhan', 'site' => 'SMO', 'unit' => 'Light Vehicle', 'mean' => 0.09, 'std' => 0.04, 'sf' => -0.3, 'ef' => 2.2, 'freq' => 2, 'sev' => 0, 'noise' => 0.7],
    ];

    $riskLookup = [
        0 => ['low', 'low', 'medium'],
        1 => ['low', 'medium', 'high'],
        2 => ['medium', 'high', 'extreme'],
    ];

    $operators = [];
    foreach ($rawOperators as $o) {
        $hist = fmGenerateHistory($o['sid'], $N, $o['mean'], $o['std'], $o['sf'], $o['ef'], $o['noise']);
        $ewma = fmEwma($hist, $ALPHA);
        $rate = end($hist);
        $z = round(($rate - $o['mean']) / $o['std'], 2);
        $risk = $riskLookup[$o['freq']][$o['sev']];
        $operators[] = array_merge($o, [
            'id' => $o['sid'],
            'hist' => $hist,
            'ewma' => $ewma,
            'rate' => $rate,
            'z' => $z,
            'risk' => $risk,
        ]);
    }
@endphp

@section('css')
<style>
  .fm-mockup-badge {
    display: inline-flex; align-items: center; gap: 6px; margin-left: 10px;
    font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em;
    background: var(--warning-100, #fff3e0); color: var(--warning-600, #b45309);
    border: 1px solid var(--warning-200, #ffe0b2); padding: 4px 10px; border-radius: 999px;
  }
  .fm-chip { border: 1px solid var(--neutral-300, #d1d5db); background: #fff; color: var(--text-secondary-light);
    font-size: 12.5px; font-weight: 600; border-radius: 999px; padding: 7px 16px; cursor: pointer;
    transition: background .15s ease, color .15s ease, border-color .15s ease; }
  .fm-chip:hover { border-color: var(--primary-600, #487FFF); color: var(--primary-600, #487FFF); }
  .fm-chip.is-active { background: var(--primary-600, #487FFF); border-color: var(--primary-600, #487FFF); color: #fff; }

  .fm-stat-mini { background: var(--neutral-50, #f8fafc); border-radius: 8px; padding: 10px 12px; }
  .fm-stat-mini .k { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: var(--text-secondary-light); font-weight: 600; }
  .fm-stat-mini .v { font-size: 16.5px; font-weight: 700; margin-top: 3px; color: var(--text-primary-light); }

  .fm-table tbody tr { cursor: pointer; transition: background .15s ease; }
  .fm-table tbody tr:hover { background: var(--neutral-50, #f8fafc); }
  .fm-table tbody tr.is-selected { background: var(--primary-50, #eef4ff); box-shadow: inset 3px 0 0 var(--primary-600, #487FFF); }
  .fm-op-name { font-weight: 600; display: block; color: var(--text-primary-light); }
  .fm-op-sub { font-size: 11px; color: var(--text-secondary-light); }
  .fm-shape { flex: none; display: block; }

  .fm-matrix-grid { display: grid; grid-template-columns: 84px repeat(3, 96px); grid-auto-rows: 28px 72px 72px 72px; gap: 8px; }
  .fm-matrix-axis { font-size: 11px; color: var(--text-secondary-light); font-weight: 600; display: flex; align-items: center; }
  .fm-matrix-col { font-size: 11px; color: var(--text-secondary-light); font-weight: 600; text-align: center; align-self: end; padding-bottom: 4px; }
  .fm-matrix-cell {
    border-radius: 10px; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px;
    transition: transform .15s ease, box-shadow .15s ease; border: 1px solid transparent;
  }
  .fm-matrix-cell:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(15,23,42,0.1); }
  .fm-matrix-cell.is-active { outline: 2px solid var(--primary-600, #487FFF); outline-offset: 2px; }
  .fm-matrix-cell .count { font-size: 20px; font-weight: 700; }
  .fm-matrix-cell .label { font-size: 9.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; opacity: .9; }
  .fm-matrix-cell.m-low { background: var(--success-focus); color: var(--success-600, #166534); }
  .fm-matrix-cell.m-medium { background: var(--warning-focus); color: var(--warning-600, #b45309); }
  .fm-matrix-cell.m-high { background: var(--danger-focus); color: var(--danger-600, #b91c1c); }
  .fm-matrix-cell.m-extreme { background: var(--danger-main); color: #fff; }
  .fm-legend-row { display: flex; align-items: center; gap: 9px; font-size: 12.5px; color: var(--text-secondary-light); }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Fatigue Baseline Monitor<span class="fm-mockup-badge">Mockup &middot; Data Dummy</span></h6>
    <div class="text-secondary-light text-sm mt-4">Baseline personal per operator dari pola alert DMS &middot; dihitung tiap shift selesai</div>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        DMS
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Fatigue Baseline</li>
  </ul>
</div>

<div class="d-flex flex-wrap gap-2 mb-24" id="fmSiteChips"></div>

<div class="row gy-4 mb-4" id="fmKpiRow"></div>

<div class="row gy-4">
  <div class="col-xxl-7">
    <div class="card h-100 radius-8 border">
      <div class="card-header border-bottom bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="text-lg mb-0">Watchlist Operator</h6>
        <span class="text-secondary-light text-sm" id="fmWatchlistNote"></span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive scroll-sm" style="max-height:540px;overflow-y:auto">
          <table class="table bordered-table mb-0 fm-table">
            <thead>
              <tr>
                <th scope="col">Kode SID</th>
                <th scope="col">Operator</th>
                <th scope="col">Unit</th>
                <th scope="col">Rate&nbsp;skrg</th>
                <th scope="col">Baseline</th>
                <th scope="col">Z-score</th>
                <th scope="col">Tren&nbsp;4pk</th>
                <th scope="col">Tier</th>
              </tr>
            </thead>
            <tbody id="fmTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xxl-5">
    <div class="card h-100 radius-8 border">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-16">
          <div>
            <span class="text-secondary-light text-sm" id="fmDetailId"></span>
            <h6 class="mb-0 mt-4" id="fmDetailName"></h6>
            <div class="d-flex flex-wrap gap-2 mt-8" id="fmDetailTags"></div>
          </div>
          <div id="fmDetailRiskPill"></div>
        </div>

        <div class="row g-2 mb-16" id="fmDetailStats"></div>

        <div id="fmApexChart"></div>
        <p class="text-secondary-light text-sm mt-8 mb-0" style="line-height:1.6">
          Pita kuning = 1&ndash;2&sigma; dari baseline pribadi (30 shift terakhir), pita merah = &gt;2&sigma;.
          Garis putus-putus ungu = EWMA (&alpha;=0,2).
        </p>

        <div class="mt-16" id="fmDetailRecommend"></div>
      </div>
    </div>
  </div>
</div>

<div class="card radius-8 border mt-4">
  <div class="card-header border-bottom bg-transparent">
    <h6 class="text-lg mb-0">Matriks Risiko &mdash; Kekerapan &times; Keparahan</h6>
    <span class="text-secondary-light text-sm">Metodologi selaras HIRA (kal_matrix_resiko)</span>
  </div>
  <div class="card-body d-flex flex-wrap gap-4">
    <div class="fm-matrix-grid" id="fmMatrixGrid"></div>
    <div class="d-flex flex-column gap-3 justify-content-center" style="min-width:230px">
      <div class="fm-legend-row"><span class="w-16-px h-16-px rounded-circle d-inline-block" style="background:var(--success-main)"></span> Low &mdash; monitoring rutin</div>
      <div class="fm-legend-row"><span class="w-16-px h-16-px rounded-circle d-inline-block" style="background:var(--warning-main)"></span> Medium &mdash; catat &amp; review mingguan</div>
      <div class="fm-legend-row"><span class="w-16-px h-16-px rounded-circle d-inline-block" style="background:var(--danger-main)"></span> High &mdash; pantau ketat, percepat review L2</div>
      <div class="fm-legend-row"><span class="w-16-px h-16-px rounded-circle d-inline-block" style="background:#6b0f2d"></span> Extreme &mdash; tarik dari unit, wajib istirahat</div>
    </div>
  </div>
</div>

<div class="d-flex flex-wrap gap-3 mt-24 pt-16 border-top text-secondary-light text-sm">
  <span><b class="text-primary-light">Parameter model</b></span>
  <span>Window baseline W = 30 shift</span>
  <span>EWMA &alpha; = 0,2</span>
  <span>Ambang: Kuning &gt;1&sigma; &middot; Merah &gt;2&sigma;</span>
  <span>Alert disaring: hanya lolos review L1+L2</span>
  <span>Kategori fatigue: Menutup Mata, Menguap, Menunduk</span>
  <span class="text-warning-600 fw-semibold">&mdash; seluruh nama, kode SID, dan angka pada halaman ini adalah data dummy untuk keperluan desain.</span>
</div>
@endsection

@section('page-scripts')
<script>
(function(){
  "use strict";
  const OPERATORS = @json($operators);
  const RISK_LABEL = { low:'Low', medium:'Medium', high:'High', extreme:'Extreme' };
  const RISK_LOOKUP = { 0:{0:'low',1:'low',2:'medium'}, 1:{0:'low',1:'medium',2:'high'}, 2:{0:'medium',1:'high',2:'extreme'} };
  const SEV_LABEL = ['Rendah','Sedang','Tinggi'];
  const TIER_META = {
    0:{ label:'Hijau', badgeClass:'bg-success-focus text-success-main', shape:'circle', color:'#45B369' },
    1:{ label:'Kuning', badgeClass:'bg-warning-focus text-warning-main', shape:'triangle', color:'#FF9F29' },
    2:{ label:'Merah', badgeClass:'bg-danger-focus text-danger-main', shape:'diamond', color:'#EF4A00' }
  };
  const RISK_ALERT = {
    low:    { cls:'alert-success bg-success-100 text-success-600 border-success-100', icon:'solar:check-circle-bold' },
    medium: { cls:'alert-warning bg-warning-100 text-warning-600 border-warning-100', icon:'solar:clock-circle-bold' },
    high:   { cls:'alert-danger bg-danger-100 text-danger-600 border-danger-100', icon:'solar:shield-warning-bold' },
    extreme:{ cls:'text-white', icon:'solar:danger-triangle-bold', style:'background:var(--danger-main)' }
  };
  const RECOMMEND = {
    low: 'Tidak ada tindakan tambahan &mdash; lanjutkan monitoring rutin melalui siklus review normal.',
    medium: 'Catat pada log mingguan supervisor &mdash; belum perlu intervensi langsung, pantau tren 2&ndash;3 shift ke depan.',
    high: 'Pantau ketat &mdash; percepat proses review L2 dan informasikan ke supervisor lapangan sebelum shift berikutnya.',
    extreme: 'Tarik dari unit pada kesempatan pertama, wajib istirahat, dan lakukan wawancara langsung oleh supervisor/approver DMS.'
  };
  const SITES = ['ALL','LMO','BMO 1','BMO 2','GMO','SMO'];

  const state = { site:'ALL', cell:null, selected: OPERATORS[0].id };

  function shapeSvg(kind, color, size){
    size = size || 10; const c = size/2;
    if(kind==='circle') return `<svg class="fm-shape" width="${size}" height="${size}"><circle cx="${c}" cy="${c}" r="${c-1}" fill="${color}"/></svg>`;
    if(kind==='triangle') return `<svg class="fm-shape" width="${size}" height="${size}"><polygon points="${c},1 ${size-1},${size-1} 1,${size-1}" fill="${color}"/></svg>`;
    if(kind==='diamond') return `<svg class="fm-shape" width="${size}" height="${size}"><polygon points="${c},0.5 ${size-0.5},${c} ${c},${size-0.5} 0.5,${c}" fill="${color}"/></svg>`;
    return '';
  }

  function sparkline(hist, dotColor){
    const w=76,h=26,pad=2;
    const slice = hist.slice(-10);
    const min=Math.min(...slice), max=Math.max(...slice), span=(max-min)||1;
    const pts = slice.map((v,i)=>{
      const x = pad + (i/(slice.length-1))*(w-2*pad);
      const y = h-pad-((v-min)/span)*(h-2*pad);
      return [x,y];
    });
    const d = pts.map((p,i)=>(i===0?'M':'L')+p[0].toFixed(1)+','+p[1].toFixed(1)).join(' ');
    const last = pts[pts.length-1];
    return `<svg width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">
      <path d="${d}" fill="none" stroke="#d1d5db" stroke-width="1.4"/>
      <circle cx="${last[0].toFixed(1)}" cy="${last[1].toFixed(1)}" r="3" fill="${dotColor}" stroke="#fff" stroke-width="1.5"/>
    </svg>`;
  }

  /* ---- site chips ---- */
  const chipsEl = document.getElementById('fmSiteChips');
  chipsEl.innerHTML = SITES.map(s=>`<button type="button" class="fm-chip" data-site="${s}">${s==='ALL'?'Semua Site':s}</button>`).join('');
  chipsEl.addEventListener('click', e=>{
    const b = e.target.closest('[data-site]'); if(!b) return;
    state.site = b.getAttribute('data-site'); state.cell = null; render();
  });

  /* ---- kpi ---- */
  function kpiCard(label, value, sub, iconBg, icon){
    return `<div class="col-xxl-3 col-sm-6">
      <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
        <div class="card-body p-0">
          <div class="d-flex align-items-center gap-2 mb-8">
            <span class="w-48-px h-48-px flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle" style="background:${iconBg}">
              <iconify-icon icon="${icon}" class="icon text-xl"></iconify-icon>
            </span>
            <div>
              <span class="mb-2 fw-medium text-secondary-light text-sm d-block">${label}</span>
              <h6 class="fw-semibold mb-0">${value}</h6>
            </div>
          </div>
          <p class="text-sm mb-0 text-secondary-light">${sub}</p>
        </div>
      </div>
    </div>`;
  }
  function renderKPI(siteSet){
    const merah = siteSet.filter(o=>o.freq===2).length;
    const kuning = siteSet.filter(o=>o.freq===1).length;
    document.getElementById('fmKpiRow').innerHTML =
      kpiCard('Operator Dipantau', siteSet.length, state.site==='ALL' ? 'Seluruh site' : state.site, 'var(--primary-600)', 'solar:users-group-rounded-bold') +
      kpiCard('Tier Merah &mdash; Butuh Tindakan', merah, 'z-score &gt; 2&sigma; dari baseline pribadi', 'var(--danger-main)', 'solar:danger-triangle-bold') +
      kpiCard('Tier Kuning &mdash; Pantau Ketat', kuning, '1&sigma;&ndash;2&sigma; dari baseline pribadi', 'var(--warning-main)', 'solar:shield-warning-bold') +
      kpiCard('Alert Belum Tertaut Operator', '1.842', '30 hari terakhir &middot; driver_sid kosong', 'var(--cyan)', 'mdi:link-off');
  }

  /* ---- table ---- */
  function renderTable(list){
    document.getElementById('fmWatchlistNote').textContent = `${list.length} operator diurutkan berdasarkan risiko`;
    const tbody = document.getElementById('fmTableBody');
    tbody.innerHTML = list.map(o=>{
      const tm = TIER_META[o.freq];
      return `<tr class="${o.id===state.selected?'is-selected':''}" data-id="${o.id}">
        <td class="fw-medium">${o.sid}</td>
        <td><span class="fm-op-name">${o.nama}</span><span class="fm-op-sub">${o.site}</span></td>
        <td>${o.unit}</td>
        <td>${o.rate.toFixed(2)}</td>
        <td>${o.mean.toFixed(2)}&plusmn;${o.std.toFixed(2)}</td>
        <td>${o.z>=0?'+':''}${o.z.toFixed(1)}&sigma;</td>
        <td>${sparkline(o.hist, tm.color)}</td>
        <td><span class="${tm.badgeClass} px-12 py-4 rounded-pill fw-medium text-sm d-inline-flex align-items-center gap-1">${shapeSvg(tm.shape, tm.color, 9)}${tm.label}</span></td>
      </tr>`;
    }).join('') || `<tr><td colspan="8" class="text-center text-secondary-light py-5">Tidak ada operator pada kombinasi filter ini.</td></tr>`;

    tbody.querySelectorAll('tr[data-id]').forEach(tr=>{
      tr.addEventListener('click', ()=>{ state.selected = tr.getAttribute('data-id'); render(); });
    });
  }

  /* ---- detail + apexcharts ---- */
  let chart = null;
  function renderDetail(list){
    const op = OPERATORS.find(o=>o.id===state.selected && list.some(l=>l.id===o.id)) || list[0];
    const idEl=document.getElementById('fmDetailId'), nameEl=document.getElementById('fmDetailName'),
          tagsEl=document.getElementById('fmDetailTags'), riskEl=document.getElementById('fmDetailRiskPill'),
          statsEl=document.getElementById('fmDetailStats'), recEl=document.getElementById('fmDetailRecommend');

    if(!op){
      idEl.textContent=''; nameEl.textContent='Tidak ada operator terpilih'; tagsEl.innerHTML=''; riskEl.innerHTML=''; statsEl.innerHTML=''; recEl.innerHTML='';
      if(chart){ chart.destroy(); chart=null; }
      return;
    }
    state.selected = op.id;
    idEl.textContent = op.sid;
    nameEl.textContent = op.nama;
    tagsEl.innerHTML = `<span class="bg-neutral-100 text-secondary-light px-10 py-2 rounded-8 text-xs fw-medium">${op.site}</span><span class="bg-neutral-100 text-secondary-light px-10 py-2 rounded-8 text-xs fw-medium">${op.unit}</span><span class="bg-neutral-100 text-secondary-light px-10 py-2 rounded-8 text-xs fw-medium">Keparahan: ${SEV_LABEL[op.sev]}</span>`;
    const ra = RISK_ALERT[op.risk];
    riskEl.innerHTML = `<span class="${ra.cls} px-14 py-6 rounded-8 fw-semibold text-sm d-inline-flex align-items-center gap-1" ${ra.style?`style="${ra.style}"`:''}><iconify-icon icon="${ra.icon}" class="icon"></iconify-icon>${RISK_LABEL[op.risk]} risk</span>`;

    const stats = [
      ['Baseline Pribadi', op.mean.toFixed(2)+' &plusmn; '+op.std.toFixed(2)+' /jam'],
      ['Rate Shift Ini', op.rate.toFixed(2)+' /jam'],
      ['Z-score', (op.z>=0?'+':'')+op.z.toFixed(2)+'&sigma;'],
      ['EWMA (&alpha;=0,2)', op.ewma[op.ewma.length-1].toFixed(2)+' /jam']
    ];
    statsEl.innerHTML = stats.map(([k,v])=>`<div class="col-6"><div class="fm-stat-mini"><div class="k">${k}</div><div class="v">${v}</div></div></div>`).join('');

    recEl.innerHTML = `<div class="${ra.cls} px-16 py-13 rounded-8 d-flex gap-2 align-items-start text-sm" ${ra.style?`style="${ra.style}"`:''}>
      <iconify-icon icon="${ra.icon}" class="icon text-lg flex-shrink-0 mt-4"></iconify-icon>
      <span><b>Rekomendasi &mdash; ${RISK_LABEL[op.risk]}:</b> ${RECOMMEND[op.risk]}</span>
    </div>`;

    renderChart(op);
  }

  function renderChart(op){
    const n = op.hist.length;
    const categories = op.hist.map((_,i)=> i===n-1 ? 'Ini' : ('-'+(n-1-i)));
    const chartMax = Math.max(op.mean + 2.6*op.std, Math.max(...op.hist), Math.max(...op.ewma)) * 1.12;
    const bandTop = op.mean + op.std, bandMid = op.mean + 2*op.std;

    const options = {
      chart: { type:'line', height: 300, toolbar:{show:false}, animations:{ enabled:true, easing:'easeinout', speed:400 } },
      series: [
        { name:'Rate aktual (alert/jam)', type:'area', data: op.hist },
        { name:'EWMA (α=0,2)', type:'line', data: op.ewma }
      ],
      colors: ['#487FFF', '#8252E9'],
      stroke: { curve:'smooth', width:[2.5, 2], dashArray:[0, 5] },
      fill: { type:['gradient','solid'], gradient:{ shadeIntensity:1, opacityFrom:0.3, opacityTo:0.02, stops:[0,90,100] } },
      grid: { borderColor:'#E5E7EB', strokeDashArray:4, padding:{ left:8, right:8 } },
      xaxis: { categories, labels:{ style:{ fontSize:'10.5px' } }, axisBorder:{show:false}, axisTicks:{show:false} },
      yaxis: { min:0, max: chartMax, labels:{ formatter:v=>v.toFixed(2), style:{ fontSize:'10.5px' } } },
      markers: { size:0, hover:{ size:5 }, strokeWidth:2, strokeColors:'#fff' },
      legend: { fontSize:'12px', position:'top', horizontalAlign:'left' },
      annotations: {
        yaxis: [
          { y: bandTop, y2: bandMid, borderColor:'transparent', fillColor:'#FF9F29', opacity:0.12 },
          { y: bandMid, y2: chartMax, borderColor:'transparent', fillColor:'#EF4A00', opacity:0.10 },
          { y: op.mean, borderColor:'#9CA3AF', strokeDashArray:4,
            label:{ text:'Baseline '+op.mean.toFixed(2), position:'left', style:{ background:'#9CA3AF', color:'#fff', fontSize:'10px' } } }
        ]
      },
      tooltip: {
        shared:true, intersect:false,
        custom: function({ series, dataPointIndex }){
          const z = ((op.hist[dataPointIndex]-op.mean)/op.std).toFixed(1);
          const label = categories[dataPointIndex]==='Ini' ? 'Shift ini' : ('Shift '+categories[dataPointIndex]);
          return `<div style="padding:10px 13px;font-size:12px;line-height:1.6">
            <div style="font-weight:600;margin-bottom:3px">${label}</div>
            <div>Rate&nbsp;&nbsp;: <b>${series[0][dataPointIndex].toFixed(2)}</b>/jam</div>
            <div>EWMA&nbsp;: <b>${series[1][dataPointIndex].toFixed(2)}</b>/jam</div>
            <div>Z-score: <b>${z>=0?'+':''}${z}&sigma;</b></div>
          </div>`;
        }
      }
    };

    if(chart){ chart.destroy(); }
    chart = new ApexCharts(document.querySelector('#fmApexChart'), options);
    chart.render();
  }

  /* ---- matrix ---- */
  function renderMatrix(siteSet){
    const grid = document.getElementById('fmMatrixGrid');
    let html = `<div></div><div class="fm-matrix-col">Rendah</div><div class="fm-matrix-col">Sedang</div><div class="fm-matrix-col">Tinggi</div>`;
    const rowLabels = { 2:'Merah', 1:'Kuning', 0:'Hijau' };
    for(let f=2; f>=0; f--){
      html += `<div class="fm-matrix-axis">${rowLabels[f]}</div>`;
      for(let s=0; s<3; s++){
        const count = siteSet.filter(o=>o.freq===f && o.sev===s).length;
        const risk = RISK_LOOKUP[f][s];
        const active = state.cell && state.cell.f===f && state.cell.s===s ? 'is-active' : '';
        html += `<div class="fm-matrix-cell m-${risk} ${active}" data-f="${f}" data-s="${s}">
          <span class="count">${count}</span>
          <span class="label">${RISK_LABEL[risk]}</span>
        </div>`;
      }
    }
    grid.innerHTML = html;
    grid.querySelectorAll('.fm-matrix-cell').forEach(el=>{
      el.addEventListener('click', ()=>{
        const f = +el.getAttribute('data-f'), s = +el.getAttribute('data-s');
        state.cell = (state.cell && state.cell.f===f && state.cell.s===s) ? null : { f, s };
        render();
      });
    });
  }

  /* ---- main render ---- */
  function render(){
    document.querySelectorAll('#fmSiteChips .fm-chip').forEach(b=>{
      b.classList.toggle('is-active', b.getAttribute('data-site')===state.site);
    });
    const siteSet = state.site==='ALL' ? OPERATORS : OPERATORS.filter(o=>o.site===state.site);
    renderKPI(siteSet);
    renderMatrix(siteSet);
    const tableSet = state.cell ? siteSet.filter(o=>o.freq===state.cell.f && o.sev===state.cell.s) : siteSet;
    const sorted = [...tableSet].sort((a,b)=> b.freq-a.freq || b.z-a.z);
    if(!sorted.find(o=>o.id===state.selected)) state.selected = sorted[0] ? sorted[0].id : null;
    renderTable(sorted);
    renderDetail(sorted);
  }

  render();
})();
</script>
@endsection
