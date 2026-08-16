@extends('dms.layouts.app')

@section('title', 'Fatigue Baseline Monitor')

@section('css')
<style>
  .fm-warn-banner{background:var(--warning-100,#fff3e0);border:1px solid var(--warning-200,#ffe0b2);color:var(--warning-600,#b45309);
    border-radius:10px;padding:12px 16px;font-size:13px;display:flex;gap:10px;align-items:flex-start;}
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

  .fm-pred-grid { display: flex; flex-wrap: wrap; gap: 10px; }
  .fm-pred-cell {
    flex: 1 1 160px; border-radius: 10px; cursor: pointer; padding: 14px; display: flex; flex-direction: column; gap: 4px;
    transition: transform .15s ease, box-shadow .15s ease; border: 1px solid transparent;
  }
  .fm-pred-cell:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(15,23,42,0.1); }
  .fm-pred-cell.is-active { outline: 2px solid var(--primary-600, #487FFF); outline-offset: 2px; }
  .fm-pred-cell .count { font-size: 22px; font-weight: 700; }
  .fm-pred-cell .label { font-size: 12px; font-weight: 600; }
  .fm-pred-cell.p-already { background: var(--danger-main); color: #fff; }
  .fm-pred-cell.p-within7 { background: var(--danger-focus); color: var(--danger-600, #b91c1c); }
  .fm-pred-cell.p-within30 { background: var(--warning-focus); color: var(--warning-600, #b45309); }
  .fm-pred-cell.p-aman { background: var(--success-focus); color: var(--success-600, #166534); }

  .fm-prediction-panel { border-radius: 8px; padding: 12px 14px; }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Fatigue Baseline Monitor</h6>
    <div class="text-secondary-light text-sm mt-4">Baseline personal per operator dari pola alert DMS &middot; {{ $dateLabel }}</div>
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

<div id="fmWarnBanner" class="fm-warn-banner mb-24 {{ $up ? 'd-none' : '' }}">
  <iconify-icon icon="solar:danger-circle-bold" class="icon text-lg flex-shrink-0"></iconify-icon>
  <div>Koneksi ke hse_automation (bcsid.mv_dms_alert) tidak tersedia saat ini.</div>
</div>

@if($up && $shownCount === 0)
<div class="text-center text-secondary-light py-64">
  <iconify-icon icon="solar:chart-2-outline" class="text-4xl mb-8 d-block"></iconify-icon>
  Belum ada operator dengan pola alert fatigue yang cukup (minimal {{ $params['minAlertDays'] }} hari punya alert dalam {{ $params['lookbackDays'] }} hari terakhir).
</div>
@else

@if($truncated)
<div class="text-secondary-light text-sm mb-16">
  <iconify-icon icon="solar:info-circle-bold" class="align-middle me-1"></iconify-icon>
  Menampilkan top {{ number_format($shownCount) }} operator paling berisiko dari total {{ number_format($totalCandidates) }} yang memenuhi kriteria (minimal {{ $params['minAlertDays'] }} hari punya alert dalam {{ $params['lookbackDays'] }} hari terakhir).
</div>
@endif

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
                <th scope="col">Prediksi Kapan Fatigue</th>
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

        <div id="fmDetailPredictionPanel" class="fm-prediction-panel mb-16"></div>

        <div class="row g-2 mb-16" id="fmDetailStats"></div>

        <div id="fmApexChart"></div>
        <p class="text-secondary-light text-sm mt-8 mb-0" style="line-height:1.6">
          Pita kuning = 1&ndash;2&sigma; dari baseline pribadi ({{ $params['lookbackDays'] - $params['trendDays'] }} hari sebelum tren), pita merah = &gt;2&sigma;.
          Garis putus-putus ungu = EWMA (&alpha;={{ $params['alpha'] }}).
        </p>

        <div class="mt-16" id="fmDetailRecommend"></div>
      </div>
    </div>
  </div>
</div>

<div class="card radius-8 border mt-4">
  <div class="card-header border-bottom bg-transparent">
    <h6 class="text-lg mb-0">Distribusi Prediksi &mdash; Kapan Kemungkinan Fatigue</h6>
    <span class="text-secondary-light text-sm">Proyeksi tren linear {{ $params['trendDays'] }} hari terakhir terhadap ambang {{ $params['thresholdSigma'] }}&sigma;, horizon maks {{ \App\Services\Dms\FatigueTrendCalculator::PREDICTION_HORIZON_DAYS }} hari</span>
  </div>
  <div class="card-body">
    <div class="fm-pred-grid" id="fmPredGrid"></div>
  </div>
</div>

<div class="d-flex flex-wrap gap-3 mt-24 pt-16 border-top text-secondary-light text-sm">
  <span><b class="text-primary-light">Parameter model</b></span>
  <span>Window baseline = {{ $params['lookbackDays'] - $params['trendDays'] }} hari (sebelum tren)</span>
  <span>Window tren/proyeksi = {{ $params['trendDays'] }} hari terakhir</span>
  <span>EWMA &alpha; = {{ $params['alpha'] }}</span>
  <span>Ambang: Kuning &gt;1&sigma; &middot; Merah &gt;2&sigma;</span>
  <span>Alert disaring: hanya terkonfirmasi nyata (review L1)</span>
  <span>Kategori fatigue: Menutup Mata, Menguap, Menunduk</span>
  <span class="text-warning-600 fw-semibold">&mdash; prediksi adalah proyeksi tren linear sederhana, bukan forecast tervalidasi &mdash; gunakan sebagai sinyal awal, bukan kepastian.</span>
</div>
@endif
@endsection

@section('page-scripts')
<script>
(function(){
  "use strict";
  const OPERATORS = @json($operators);
  if (!OPERATORS.length) return;

  const RISK_LABEL = { low:'Low', medium:'Medium', high:'High', extreme:'Extreme' };
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
    medium: 'Catat pada log mingguan supervisor &mdash; belum perlu intervensi langsung, pantau proyeksi tren beberapa hari ke depan.',
    high: 'Pantau ketat &mdash; percepat proses review L2 dan informasikan ke supervisor lapangan sebelum shift berikutnya, proyeksi menunjukkan ambang tercapai dalam waktu dekat.',
    extreme: 'Tarik dari unit pada kesempatan pertama, wajib istirahat, dan lakukan wawancara langsung oleh supervisor/approver DMS.'
  };
  const PREDICTION_META = {
    already_over: { cls:'bg-danger-100 text-danger-600', icon:'solar:danger-triangle-bold' },
    projected: { cls:'bg-warning-100 text-warning-600', icon:'solar:clock-circle-bold' },
    no_trend: { cls:'bg-success-100 text-success-600', icon:'solar:check-circle-bold' },
    no_imminent: { cls:'bg-success-100 text-success-600', icon:'solar:check-circle-bold' },
    insufficient_data: { cls:'bg-neutral-100 text-secondary-light', icon:'solar:info-circle-bold' }
  };

  const SITES = ['ALL', ...Array.from(new Set(OPERATORS.map(o=>o.site))).sort()];
  const state = { site:'ALL', predBucket:null, selected: OPERATORS[0].id };

  function escapeHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function shapeSvg(kind, color, size){
    size = size || 10; const c = size/2;
    if(kind==='circle') return `<svg class="fm-shape" width="${size}" height="${size}"><circle cx="${c}" cy="${c}" r="${c-1}" fill="${color}"/></svg>`;
    if(kind==='triangle') return `<svg class="fm-shape" width="${size}" height="${size}"><polygon points="${c},1 ${size-1},${size-1} 1,${size-1}" fill="${color}"/></svg>`;
    if(kind==='diamond') return `<svg class="fm-shape" width="${size}" height="${size}"><polygon points="${c},0.5 ${size-0.5},${c} ${c},${size-0.5} 0.5,${c}" fill="${color}"/></svg>`;
    return '';
  }

  function predictionBucket(op) {
    if (op.prediction.status === 'already_over') return 'already';
    if (op.prediction.status === 'projected' && op.prediction.days <= 7) return 'within7';
    if (op.prediction.status === 'projected') return 'within30';
    return 'aman';
  }

  function predictionShortLabel(op) {
    if (op.prediction.status === 'already_over') return 'Sudah lewat ambang';
    if (op.prediction.status === 'projected') return '~' + op.prediction.days + ' hari lagi (' + op.prediction.date + ')';
    if (op.prediction.status === 'insufficient_data') return 'Data belum cukup';
    return 'Tidak mendesak';
  }

  /* ---- site chips ---- */
  const chipsEl = document.getElementById('fmSiteChips');
  chipsEl.innerHTML = SITES.map(s=>`<button type="button" class="fm-chip" data-site="${escapeHtml(s)}">${s==='ALL'?'Semua Site':escapeHtml(s)}</button>`).join('');
  chipsEl.addEventListener('click', e=>{
    const b = e.target.closest('[data-site]'); if(!b) return;
    state.site = b.getAttribute('data-site'); state.predBucket = null; render();
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
    const merah = siteSet.filter(o=>o.riskBucket==='extreme'||o.riskBucket==='high').length;
    const kuning = siteSet.filter(o=>o.riskBucket==='medium').length;
    const proj7 = siteSet.filter(o=>o.prediction.status==='projected' && o.prediction.days<=7).length;
    document.getElementById('fmKpiRow').innerHTML =
      kpiCard('Operator Dipantau', siteSet.length, state.site==='ALL' ? 'Seluruh site' : state.site, 'var(--primary-600)', 'solar:users-group-rounded-bold') +
      kpiCard('Tier Merah &mdash; Butuh Tindakan', merah, 'z-score &gt; 2&sigma; dari baseline pribadi', 'var(--danger-main)', 'solar:danger-triangle-bold') +
      kpiCard('Tier Kuning &mdash; Pantau Ketat', kuning, '1&sigma;&ndash;2&sigma; dari baseline pribadi', 'var(--warning-main)', 'solar:shield-warning-bold') +
      kpiCard('Diproyeksi &le;7 Hari Lagi', proj7, 'Berdasarkan tren alert terkini', 'var(--cyan)', 'solar:clock-circle-bold');
  }

  /* ---- table ---- */
  function renderTable(list){
    document.getElementById('fmWatchlistNote').textContent = `${list.length} operator diurutkan berdasarkan risiko`;
    const tbody = document.getElementById('fmTableBody');
    tbody.innerHTML = list.map(o=>{
      const tm = TIER_META[o.tier];
      return `<tr class="${o.id===state.selected?'is-selected':''}" data-id="${escapeHtml(o.id)}">
        <td class="fw-medium">${escapeHtml(o.sid)}</td>
        <td><span class="fm-op-name">${escapeHtml(o.nama)}</span><span class="fm-op-sub">${escapeHtml(o.site)}</span></td>
        <td>${escapeHtml(o.unit)}</td>
        <td>${o.rate.toFixed(1)}/hari</td>
        <td>${o.mean !== null ? o.mean.toFixed(2)+'&plusmn;'+o.std.toFixed(2) : '-'}</td>
        <td>${o.z !== null ? (o.z>=0?'+':'')+o.z.toFixed(1)+'&sigma;' : '-'}</td>
        <td class="text-sm">${escapeHtml(predictionShortLabel(o))}</td>
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
          statsEl=document.getElementById('fmDetailStats'), recEl=document.getElementById('fmDetailRecommend'),
          predEl=document.getElementById('fmDetailPredictionPanel');

    if(!op){
      idEl.textContent=''; nameEl.textContent='Tidak ada operator terpilih'; tagsEl.innerHTML=''; riskEl.innerHTML='';
      statsEl.innerHTML=''; recEl.innerHTML=''; predEl.innerHTML='';
      if(chart){ chart.destroy(); chart=null; }
      return;
    }
    state.selected = op.id;
    idEl.textContent = op.sid;
    nameEl.textContent = op.nama;
    tagsEl.innerHTML = `<span class="bg-neutral-100 text-secondary-light px-10 py-2 rounded-8 text-xs fw-medium">${escapeHtml(op.site)}</span><span class="bg-neutral-100 text-secondary-light px-10 py-2 rounded-8 text-xs fw-medium">${escapeHtml(op.unit)}</span>`;
    const ra = RISK_ALERT[op.riskBucket] || RISK_ALERT.low;
    riskEl.innerHTML = `<span class="${ra.cls} px-14 py-6 rounded-8 fw-semibold text-sm d-inline-flex align-items-center gap-1" ${ra.style?`style="${ra.style}"`:''}><iconify-icon icon="${ra.icon}" class="icon"></iconify-icon>${RISK_LABEL[op.riskBucket] || '-'} risk</span>`;

    const pm = PREDICTION_META[op.prediction.status] || PREDICTION_META.insufficient_data;
    predEl.className = 'fm-prediction-panel mb-16 ' + pm.cls;
    predEl.innerHTML = `<div class="d-flex gap-2 align-items-start">
      <iconify-icon icon="${pm.icon}" class="icon text-lg flex-shrink-0 mt-2"></iconify-icon>
      <div><div class="text-xs fw-semibold text-uppercase mb-2" style="opacity:.75">Prediksi Kapan Fatigue</div>
      <div class="fw-semibold">${escapeHtml(op.prediction.message)}</div></div>
    </div>`;

    const stats = [
      ['Baseline Pribadi', op.mean !== null ? op.mean.toFixed(2)+' &plusmn; '+op.std.toFixed(2)+' /hari' : '-'],
      ['Rate Hari Ini', op.rate.toFixed(1)+' /hari'],
      ['Z-score', op.z !== null ? (op.z>=0?'+':'')+op.z.toFixed(2)+'&sigma;' : '-'],
      ['EWMA (&alpha;=0,2)', op.ewma[op.ewma.length-1].toFixed(2)+' /hari']
    ];
    statsEl.innerHTML = stats.map(([k,v])=>`<div class="col-6"><div class="fm-stat-mini"><div class="k">${k}</div><div class="v">${v}</div></div></div>`).join('');

    recEl.innerHTML = `<div class="${ra.cls} px-16 py-13 rounded-8 d-flex gap-2 align-items-start text-sm" ${ra.style?`style="${ra.style}"`:''}>
      <iconify-icon icon="${ra.icon}" class="icon text-lg flex-shrink-0 mt-4"></iconify-icon>
      <span><b>Rekomendasi &mdash; ${RISK_LABEL[op.riskBucket] || '-'}:</b> ${RECOMMEND[op.riskBucket] || RECOMMEND.low}</span>
    </div>`;

    renderChart(op);
  }

  function renderChart(op){
    const n = op.hist.length;
    const categories = op.dates.map(d => {
      const parts = d.split('-');
      return parts[2] + '/' + parts[1];
    });
    const baselineMean = op.mean !== null ? op.mean : Math.min(...op.hist);
    const baselineStd = op.std !== null ? op.std : 0;
    const chartMax = Math.max(baselineMean + 2.6*baselineStd, Math.max(...op.hist), Math.max(...op.ewma), 1) * 1.12;
    const bandTop = baselineMean + baselineStd, bandMid = baselineMean + 2*baselineStd;

    const options = {
      chart: { type:'line', height: 300, toolbar:{show:false}, animations:{ enabled:true, easing:'easeinout', speed:400 } },
      series: [
        { name:'Alert per hari (aktual)', type:'area', data: op.hist },
        { name:'EWMA (α=0,2)', type:'line', data: op.ewma }
      ],
      colors: ['#487FFF', '#8252E9'],
      stroke: { curve:'smooth', width:[2.5, 2], dashArray:[0, 5] },
      fill: { type:['gradient','solid'], gradient:{ shadeIntensity:1, opacityFrom:0.3, opacityTo:0.02, stops:[0,90,100] } },
      grid: { borderColor:'#E5E7EB', strokeDashArray:4, padding:{ left:8, right:8 } },
      xaxis: { categories, labels:{ style:{ fontSize:'9.5px' }, rotate:-45 }, axisBorder:{show:false}, axisTicks:{show:false}, tickAmount: 12 },
      yaxis: { min:0, max: chartMax, labels:{ formatter:v=>v.toFixed(1), style:{ fontSize:'10.5px' } } },
      markers: { size:0, hover:{ size:5 }, strokeWidth:2, strokeColors:'#fff' },
      legend: { fontSize:'12px', position:'top', horizontalAlign:'left' },
      annotations: op.mean !== null ? {
        yaxis: [
          { y: bandTop, y2: bandMid, borderColor:'transparent', fillColor:'#FF9F29', opacity:0.12 },
          { y: bandMid, y2: chartMax, borderColor:'transparent', fillColor:'#EF4A00', opacity:0.10 },
          { y: baselineMean, borderColor:'#9CA3AF', strokeDashArray:4,
            label:{ text:'Baseline '+baselineMean.toFixed(2), position:'left', style:{ background:'#9CA3AF', color:'#fff', fontSize:'10px' } } }
        ]
      } : {},
      tooltip: {
        shared:true, intersect:false,
        custom: function({ series, dataPointIndex }){
          const label = op.dates[dataPointIndex];
          const z = op.std ? ((op.hist[dataPointIndex]-op.mean)/op.std).toFixed(1) : '-';
          return `<div style="padding:10px 13px;font-size:12px;line-height:1.6">
            <div style="font-weight:600;margin-bottom:3px">${label}</div>
            <div>Alert&nbsp;: <b>${series[0][dataPointIndex].toFixed(0)}</b>/hari</div>
            <div>EWMA&nbsp;&nbsp;: <b>${series[1][dataPointIndex].toFixed(2)}</b>/hari</div>
            <div>Z-score: <b>${z}&sigma;</b></div>
          </div>`;
        }
      }
    };

    if(chart){ chart.destroy(); }
    chart = new ApexCharts(document.querySelector('#fmApexChart'), options);
    chart.render();
  }

  /* ---- distribusi prediksi ---- */
  function renderPredictionGrid(siteSet){
    const grid = document.getElementById('fmPredGrid');
    const buckets = [
      { key:'already', cls:'p-already', label:'Sudah Lewat Ambang', pred:o=>o.prediction.status==='already_over' },
      { key:'within7', cls:'p-within7', label:'&le;7 Hari Lagi', pred:o=>o.prediction.status==='projected' && o.prediction.days<=7 },
      { key:'within30', cls:'p-within30', label:'8&ndash;30 Hari Lagi', pred:o=>o.prediction.status==='projected' && o.prediction.days>7 },
      { key:'aman', cls:'p-aman', label:'Tidak Ada Tren Mendesak', pred:o=>['no_trend','no_imminent','insufficient_data'].includes(o.prediction.status) }
    ];
    grid.innerHTML = buckets.map(b=>{
      const count = siteSet.filter(b.pred).length;
      const active = state.predBucket===b.key ? 'is-active' : '';
      return `<div class="fm-pred-cell ${b.cls} ${active}" data-bucket="${b.key}">
        <span class="count">${count}</span>
        <span class="label">${b.label}</span>
      </div>`;
    }).join('');
    grid.querySelectorAll('.fm-pred-cell').forEach(el=>{
      el.addEventListener('click', ()=>{
        const key = el.getAttribute('data-bucket');
        state.predBucket = state.predBucket===key ? null : key;
        render();
      });
    });
  }

  const BUCKET_PREDICATES = {
    already: o=>o.prediction.status==='already_over',
    within7: o=>o.prediction.status==='projected' && o.prediction.days<=7,
    within30: o=>o.prediction.status==='projected' && o.prediction.days>7,
    aman: o=>['no_trend','no_imminent','insufficient_data'].includes(o.prediction.status)
  };

  /* ---- main render ---- */
  function render(){
    document.querySelectorAll('#fmSiteChips .fm-chip').forEach(b=>{
      b.classList.toggle('is-active', b.getAttribute('data-site')===state.site);
    });
    const siteSet = state.site==='ALL' ? OPERATORS : OPERATORS.filter(o=>o.site===state.site);
    renderKPI(siteSet);
    renderPredictionGrid(siteSet);
    const tableSet = state.predBucket ? siteSet.filter(BUCKET_PREDICATES[state.predBucket]) : siteSet;
    const sorted = [...tableSet].sort((a,b)=> b.tier-a.tier || (b.z ?? -999)-(a.z ?? -999));
    if(!sorted.find(o=>o.id===state.selected)) state.selected = sorted[0] ? sorted[0].id : null;
    renderTable(sorted);
    renderDetail(sorted);
  }

  render();
})();
</script>
@endsection
