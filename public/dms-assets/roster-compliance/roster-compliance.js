/**
 * Kepatuhan Roster Karyawan — /dms/roster-compliance-static
 *
 * Mesin aturan (toC / katOf / isLonggar / compute) adalah port 1:1 dari
 * referensi yang dipelajari (dashboard eksternal "Kepatuhan Roster Karyawan"):
 * pola harian per karyawan (P=Pagi, M=Malam, o=Off, c=Cuti) dihitung dari
 * scan gate PASSED, lalu diperiksa terhadap 3 aturan pelanggaran (merah) dan
 * 2 aturan peringatan (kuning). Data sumber adalah snapshot statis (lihat
 * data.json — hasil ekstraksi 1x, sesuai kesepakatan agar tidak menyambung
 * live ke database untuk fitur ini). Tampilan & interaksi ditulis ulang agar
 * konsisten dengan halaman DMS lain (kartu KPI, tabel WowDash, panel detail).
 */
(function () {
  "use strict";

  var scriptEl = document.currentScript;
  var ASSET_BASE = (scriptEl && scriptEl.dataset.base) || '/dms-assets/roster-compliance';
  var ALERT_COUNTS_URL = scriptEl && scriptEl.dataset.alertCountsUrl;
  var ALERT_TIMELINE_BASE = scriptEl && scriptEl.dataset.alertTimelineBase;

  var els = {
    loading: document.getElementById('rkLoading'),
    error: document.getElementById('rkError'),
    app: document.getElementById('rkApp'),
    gen: document.getElementById('rkGenLabel'),
    coPills: document.getElementById('rkCoPills'),
    siteSelect: document.getElementById('rkSiteSelect'),
    rosterSelect: document.getElementById('rkRosterSelect'),
    jabSelect: document.getElementById('rkJabSelect'),
    statusSelect: document.getElementById('rkStatusSelect'),
    noteSelect: document.getElementById('rkNoteSelect'),
    search: document.getElementById('rkSearch'),
    periodSelect: document.getElementById('rkPeriodSelect'),
    dateFrom: document.getElementById('rkDateFrom'),
    dateTo: document.getElementById('rkDateTo'),
    resetRange: document.getElementById('rkResetRange'),
    kpiRow: document.getElementById('rkKpiRow'),
    donut: document.getElementById('rkDonutChart'),
    siteAggWrap: document.getElementById('rkSiteAggWrap'),
    siteAgg: document.getElementById('rkSiteAgg'),
    tableBody: document.getElementById('rkTableBody'),
    tableNote: document.getElementById('rkTableNote'),
    pageInfo: document.getElementById('rkPageInfo'),
    prev: document.getElementById('rkPrev'),
    next: document.getElementById('rkNext'),
    detail: document.getElementById('rkDetailWrap'),
    exportBtn: document.getElementById('rkExportBtn'),
    params: document.getElementById('rkParamsBody'),
  };
  if (!els.app) return;

  var MON = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  var MON_LONG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  var HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  var SHIFT_LABEL = { P: 'Shift Pagi', M: 'Shift Malam', o: 'Off', c: 'Cuti' };
  var STATUS_ORDER = ['Shift Pagi', 'Shift Malam', 'Overshift', 'Off', 'Cuti'];
  var STATUS_COLOR = { 'Shift Pagi': '#60a5fa', 'Shift Malam': '#1e3a8a', Overshift: '#d97706', Off: '#94a3b8', Cuti: '#12a150' };
  var STATUS_BADGE = {
    'Shift Pagi': 'bg-primary-100 text-primary-600',
    'Shift Malam': 'text-white', // dark navy, inline style
    Overshift: 'bg-warning-100 text-warning-600',
    Off: 'bg-neutral-200 text-neutral-600',
    Cuti: 'bg-success-100 text-success-600',
  };
  var SHIFT_INFO = {
    PAMA: ['6:1 6:1', '6 Pagi - 1 Off, 6 Malam - 1 Off'],
    MTL: ['6:6:1', '6 Siang - 6 Malam - 1 Off'],
    MTN: ['6:6:1', '6 Siang - 6 Malam - 1 Off'],
    BUMA: ['3:3:1', '3 Siang - 3 Malam - 1 Off'],
    KDC: ['7:6:1', '7 Siang - 6 Malam - 1 Off'],
    FAD: ['3:3:1', '3 Siang - 3 Malam - 1 Off'],
    BAR: ['3:3:1', '3 Siang - 3 Malam - 1 Off'],
  };
  var ALERT_STATUS_META = {
    nyata: { label: 'True Alert', cls: 'bg-danger-100 text-danger-600' },
    palsu: { label: 'False Alert', cls: 'bg-neutral-200 text-neutral-600' },
    belum: { label: 'Belum Diperiksa', cls: 'bg-warning-100 text-warning-600' },
  };

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  /* ---------------------------------------------------------------------
   * Mesin aturan — port 1:1
   * ------------------------------------------------------------------- */
  var D = null, JABCAT = {}, NDAY = 0;

  function katOf(j) { return JABCAT[j] || 'Lainnya'; }
  function isLonggar(jab) {
    var k = katOf(jab);
    return k === 'Operator Transportasi Massal' || k === 'Mekanik';
  }
  /** Runtun "o" (off) > 5 hari berturut dianggap fase Cuti. */
  function toC(pola) {
    var a = pola.split(''), i = 0, n = a.length;
    while (i < n) {
      if (a[i] === 'o') {
        var j = i;
        while (j < n && a[j] === 'o') j++;
        if (j - i > 5) { for (var k = i; k < j; k++) a[k] = 'c'; }
        i = j;
      } else i++;
    }
    return a.join('');
  }

  /**
   * @param sid,nama,jab,pola data mentah 1 karyawan; M = config perusahaan
   *   {thr, cutimin, map}; r0/r1 = indeks rentang tanggal yang sedang aktif.
   */
  function compute(sid, nama, jab, pola, M, r0, r1) {
    var kat = katOf(jab), LG = isLonggar(jab);
    var p = toC(pola), n = p.length;
    if (r0 == null) r0 = 0;
    if (r1 == null) r1 = n - 1;
    var red = new Array(n).fill(''), yel = new Array(n).fill(''), dayno = new Array(n).fill(1);
    var maxblock = M.thr - 1, f1 = false, f2 = false, f3 = false, i;
    for (i = 0; i < n; i++) dayno[i] = (i > 0 && p[i] === p[i - 1]) ? dayno[i - 1] + 1 : 1;

    var rosterAt = new Array(n);
    (function () {
      var cyc = 1;
      for (var i2 = 0; i2 < n; i2++) {
        rosterAt[i2] = cyc;
        if (p[i2] === 'c' && (i2 + 1 >= n || p[i2 + 1] !== 'c')) cyc++;
      }
    })();

    // Flag lintas-periode: on-site tanpa cuti > 71 hari (merah)
    i = 0;
    while (i < n) {
      if (p[i] !== 'c') {
        var j = i;
        while (j < n && p[j] !== 'c') j++;
        var len = j - i;
        if (len > 71 && !LG) { f2 = true; for (var k2 = i; k2 < j; k2++) red[k2] = 'On-site ' + len + ' hr belum cuti (>71) — PELANGGARAN'; }
        i = j;
      } else i++;
    }
    // Blok kerja: >13 hari beruntun (merah) + mapping shift (kuning)
    i = 0;
    while (i < n) {
      if (p[i] === 'P' || p[i] === 'M') {
        var j2 = i;
        while (j2 < n && (p[j2] === 'P' || p[j2] === 'M')) j2++;
        var len2 = j2 - i;
        if (len2 > 13 && !LG) {
          f1 = true;
          for (var k3 = i; k3 < j2; k3++) { red[k3] = 'Kerja beruntun ' + len2 + ' hr (>13) — PELANGGARAN'; dayno[k3] = k3 - i + 1; }
        } else {
          if (len2 > maxblock && !LG) {
            for (var k4 = i; k4 < j2; k4++) if (!red[k4] && !yel[k4]) yel[k4] = 'Kerja beruntun ' + len2 + ' hr — melebihi blok roster (' + maxblock + ')';
          }
          if (M.map === 'pama') {
            for (var k5 = i + 1; k5 < j2; k5++) if (p[k5] !== p[k5 - 1]) { if (!red[k5] && !yel[k5]) yel[k5] = 'Wajib OFF saat ganti shift (Pagi↔Malam)'; }
          } else {
            var sm = false;
            for (var k6 = i; k6 < j2; k6++) {
              if (p[k6] === 'M') sm = true;
              else if (p[k6] === 'P' && sm) { if (!red[k6] && !yel[k6]) yel[k6] = 'Urutan shift tak sesuai (Pagi setelah Malam)'; }
            }
          }
        }
        i = j2;
      } else i++;
    }
    // Cuti < 12 hari (merah)
    i = 0;
    while (i < n) {
      if (p[i] === 'c') {
        var j3 = i;
        while (j3 < n && p[j3] === 'c') j3++;
        var len3 = j3 - i;
        if (len3 < 12 && !LG) { f3 = true; for (var k7 = i; k7 < j3; k7++) red[k7] = 'Cuti ' + len3 + ' hr (<12) — PELANGGARAN'; }
        i = j3;
      } else i++;
    }

    var hasRed = false, hasYel = false;
    for (i = r0; i <= r1; i++) { if (red[i]) hasRed = true; if (yel[i]) hasYel = true; }
    var cats = { red: hasRed, map: hasYel };

    var curCyc = rosterAt[r1], roster = curCyc;
    var pagi = 0, malam = 0, off = 0, cutiCur = 0, onNoOff = 0, cr = 0, crs = -1;
    var onS = -1, onE = -1;
    for (i = 0; i <= r1; i++) {
      if (rosterAt[i] !== curCyc) continue;
      var ch = p[i];
      if (ch === 'P' || ch === 'M') {
        if (ch === 'P') pagi++; else malam++;
        if (cr === 0) crs = i;
        cr++;
        if (cr > onNoOff) { onNoOff = cr; onS = crs; onE = i; }
      } else if (ch === 'o') { off++; cr = 0; }
      else { cutiCur++; cr = 0; }
    }
    var hadir = pagi + malam + off;
    var last = p[r1], status;
    if (last === 'c') status = 'Cuti';
    else if (last === 'o') status = 'Off';
    else {
      var k = r1, run = 0;
      while (k >= 0 && (p[k] === 'P' || p[k] === 'M')) { run++; k--; }
      status = run >= 8 ? 'Overshift' : (last === 'P' ? 'Shift Pagi' : 'Shift Malam');
    }

    // YTD [0,n): on-site maks & cuti min, lepas dari rentang aktif
    var onAll = 0, onAllR = 0, onAllS = -1, onAllE = -1;
    i = 0;
    while (i < n) {
      if (p[i] !== 'c') {
        var j4 = i;
        while (j4 < n && p[j4] !== 'c') j4++;
        var len4 = j4 - i;
        if (len4 > onAll) { onAll = len4; onAllR = rosterAt[i]; onAllS = i; onAllE = j4 - 1; }
        i = j4;
      } else i++;
    }
    var cutiMin = 0, cutiMinR = 0, cutiMinS = -1, cutiMinE = -1;
    i = 0;
    while (i < n) {
      if (p[i] === 'c') {
        var j5 = i;
        while (j5 < n && p[j5] === 'c') j5++;
        var len5 = j5 - i;
        if (cutiMin === 0 || len5 < cutiMin) { cutiMin = len5; cutiMinR = rosterAt[i]; cutiMinS = i; cutiMinE = j5 - 1; }
        i = j5;
      } else i++;
    }
    var onCur = 0, onCurS = -1;
    (function () {
      var k8 = r1;
      while (k8 >= 0 && p[k8] !== 'c') k8--;
      onCur = r1 - k8;
      onCurS = k8 + 1;
    })();

    return {
      sid: sid, nama: nama, jab: jab, p: p, dayno: dayno, roster: roster,
      onCur: onCur, onCurS: onCurS, hadir: hadir, pagi: pagi, malam: malam, off: off, cutiCur: cutiCur,
      onNoOff: onNoOff, onS: onS, onE: onE,
      onAll: onAll, onAllR: onAllR, onAllS: onAllS, onAllE: onAllE,
      cutiMin: cutiMin, cutiMinR: cutiMinR, cutiMinS: cutiMinS, cutiMinE: cutiMinE,
      status: status, cats: cats, red: red, yel: yel, longgar: LG, kat: kat,
      everRed: red.some(function (x) { return !!x; }),
      everYel: yel.some(function (x) { return !!x; }),
      redK1: f1, redK2: f2, redK3: f3,
    };
  }

  /* ---------------------------------------------------------------------
   * Util tanggal / rentang
   * ------------------------------------------------------------------- */
  function fmtD(i) {
    if (i == null || i < 0) return '';
    var dt = new Date(D.dISO[i] + 'T00:00:00Z');
    return dt.getUTCDate() + ' ' + MON[dt.getUTCMonth()];
  }
  function fmtLong(i) {
    var dt = new Date(D.dISO[i] + 'T00:00:00Z');
    return dt.getUTCDate() + ' ' + MON_LONG[dt.getUTCMonth()] + ' ' + dt.getUTCFullYear();
  }
  function monthRange(m) {
    var s = -1, e = -1;
    for (var i = 0; i < NDAY; i++) if ((+D.dISO[i].slice(5, 7)) === m) { if (s < 0) s = i; e = i; }
    return [s, e];
  }
  function quarterRange(q) {
    var m0 = (q - 1) * 3 + 1, s = -1, e = -1;
    for (var i = 0; i < NDAY; i++) { var m = +D.dISO[i].slice(5, 7); if (m >= m0 && m <= m0 + 2) { if (s < 0) s = i; e = i; } }
    return [s, e];
  }

  /* ---------------------------------------------------------------------
   * State
   * ------------------------------------------------------------------- */
  var state = {
    co: '__ALL__', site: '', roster: '', jab: '', status: '', note: '', q: '',
    r0: 0, r1: 0, page: 0, pageSize: 25, sortKey: 'nama', sortDir: 1,
    selectedId: null,
  };
  var ROWS = [], VIEW = [], AGG = null;
  var donutChart = null;
  var ALERT_UNTIL = null;
  var alertCounts = {}; // UPPER(sid) -> jumlah alert 30 hari (atau 'x' kalau gagal dimuat)
  var alertCountsInFlight = {};
  var alertTimelineCache = {};

  function monthsPresent() {
    var set = {};
    for (var i = 0; i < NDAY; i++) set[+D.dISO[i].slice(5, 7)] = true;
    return Object.keys(set).map(Number).sort(function (a, b) { return a - b; });
  }
  function quartersPresent() {
    var set = {};
    for (var i = 0; i < NDAY; i++) set[Math.floor((+D.dISO[i].slice(5, 7) - 1) / 3) + 1] = true;
    return Object.keys(set).map(Number).sort(function (a, b) { return a - b; });
  }

  /* ---------------------------------------------------------------------
   * Boot
   * ------------------------------------------------------------------- */
  Promise.all([
    fetch(ASSET_BASE + '/data.json').then(function (r) { if (!r.ok) throw new Error('data.json ' + r.status); return r.json(); }),
    fetch(ASSET_BASE + '/jabcat.json').then(function (r) { if (!r.ok) throw new Error('jabcat.json ' + r.status); return r.json(); }),
  ]).then(function (res) {
    D = res[0]; JABCAT = res[1]; NDAY = D.dISO.length;
    state.r0 = 0; state.r1 = NDAY - 1;
    ALERT_UNTIL = D.dISO[NDAY - 1];
    boot();
  }).catch(function (e) {
    console.error(e);
    if (els.loading) els.loading.classList.add('d-none');
    if (els.error) els.error.classList.remove('d-none');
  });

  function boot() {
    els.loading.classList.add('d-none');
    els.app.classList.remove('d-none');
    els.gen.textContent = 'Dibuat ' + D.gen + ' · ' + fmtLong(0) + ' – ' + fmtLong(NDAY - 1) + ' · 7 PT mitra kerja · populasi Operator/Driver & Mekanik lapangan';

    renderCoPills();
    fillPeriodSelect();
    wireEvents();
    recompute();
  }

  function renderCoPills() {
    var html = '<button type="button" class="rk-co-pill is-active" data-co="__ALL__">★ Semua Perusahaan<span class="n">(' + D.order.length + ' PT)</span></button>';
    html += D.order.map(function (co) {
      var c = D.companies[co];
      var total = 0;
      for (var s in c.sites) total += c.sites[s].length;
      return '<button type="button" class="rk-co-pill" data-co="' + co + '">' + co + '<span class="n">(' + total + ')</span></button>';
    }).join('');
    els.coPills.innerHTML = html;
  }

  function fillPeriodSelect() {
    var html = '<option value="">Seluruh Periode (YTD)</option>';
    html += '<optgroup label="Per Kuartal">' + quartersPresent().map(function (q) {
      return '<option value="q' + q + '">Kuartal ' + q + '</option>';
    }).join('') + '</optgroup>';
    html += '<optgroup label="Per Bulan">' + monthsPresent().map(function (m) {
      return '<option value="m' + m + '">' + MON_LONG[m - 1] + '</option>';
    }).join('') + '</optgroup>';
    els.periodSelect.innerHTML = html;
    els.dateFrom.value = D.dISO[0];
    els.dateTo.value = D.dISO[NDAY - 1];
  }

  /* ---------------------------------------------------------------------
   * Build rows sesuai perusahaan/site terpilih
   * ------------------------------------------------------------------- */
  function allSitesAcrossCompanies() {
    var set = {};
    D.order.forEach(function (co) { for (var s in D.companies[co].sites) set[s] = true; });
    return Object.keys(set).sort();
  }

  function buildRows() {
    var out = [];
    if (state.co === '__ALL__') {
      D.order.forEach(function (co) {
        var c = D.companies[co];
        for (var site in c.sites) {
          if (state.site && site !== state.site) continue;
          c.sites[site].forEach(function (r) {
            var o = compute(r[0], r[1], r[2], r[3], c, state.r0, state.r1);
            o.co = co; o.site = site; o.id = r[4];
            out.push(o);
          });
        }
      });
    } else {
      var c2 = D.companies[state.co];
      if (!c2) return out;
      for (var site2 in c2.sites) {
        if (state.site && site2 !== state.site) continue;
        c2.sites[site2].forEach(function (r) {
          var o = compute(r[0], r[1], r[2], r[3], c2, state.r0, state.r1);
          o.co = state.co; o.site = site2; o.id = r[4];
          out.push(o);
        });
      }
    }
    return out;
  }

  function fillSiteSelect() {
    var sites = state.co === '__ALL__' ? allSitesAcrossCompanies() : Object.keys(D.companies[state.co].sites);
    var cur = els.siteSelect.value;
    els.siteSelect.innerHTML = '<option value="">Semua Site</option>' + sites.map(function (s) {
      return '<option value="' + escapeHtml(s) + '">' + escapeHtml(s) + '</option>';
    }).join('');
    els.siteSelect.value = sites.indexOf(cur) >= 0 ? cur : '';
    state.site = els.siteSelect.value;
  }

  function fillDependentSelects() {
    var rosterSet = {}, jabSet = {};
    ROWS.forEach(function (r) { rosterSet[r.roster] = true; if (r.kat) jabSet[r.kat] = true; });
    var rosterVals = Object.keys(rosterSet).map(Number).sort(function (a, b) { return a - b; });
    var jabVals = Object.keys(jabSet).sort();
    var curR = els.rosterSelect.value, curJ = els.jabSelect.value;
    els.rosterSelect.innerHTML = '<option value="">Semua Roster</option>' + rosterVals.map(function (v) {
      return '<option value="' + v + '">Roster ke-' + v + '</option>';
    }).join('');
    els.jabSelect.innerHTML = '<option value="">Semua Jabatan</option>' + jabVals.map(function (v) {
      return '<option value="' + escapeHtml(v) + '">' + escapeHtml(v) + '</option>';
    }).join('');
    els.rosterSelect.value = rosterVals.map(String).indexOf(curR) >= 0 ? curR : '';
    els.jabSelect.value = jabVals.indexOf(curJ) >= 0 ? curJ : '';
    state.roster = els.rosterSelect.value;
    state.jab = els.jabSelect.value;
  }

  /* ---------------------------------------------------------------------
   * Recompute pipeline: rows -> aggregate -> filter/sort -> render
   * ------------------------------------------------------------------- */
  function recompute() {
    fillSiteSelect();
    ROWS = buildRows();
    fillDependentSelects();

    var total = ROWS.length;
    var cur = {}; STATUS_ORDER.forEach(function (x) { cur[x] = 0; });
    var npel = 0, npel1 = 0, npel2 = 0, npel3 = 0, nmap = 0, nwajib = 0;
    ROWS.forEach(function (r) {
      cur[r.status] = (cur[r.status] || 0) + 1;
      if (r.everRed) npel++;
      if (r.redK1) npel1++;
      if (r.redK2) npel2++;
      if (r.redK3) npel3++;
      if (r.everYel) nmap++;
      if (r.onCur > 71 && !r.longgar) nwajib++;
    });
    AGG = { total: total, cur: cur, npel: npel, npel1: npel1, npel2: npel2, npel3: npel3, nmap: nmap, nwajib: nwajib };

    renderKpi();
    renderDonut();
    renderSiteAgg();
    renderParams();
    applyFilters();
  }

  function pct(x) { return AGG.total ? ((x / AGG.total) * 100).toFixed(1) + '%' : '0%'; }

  function kpiCard(label, value, sub, iconBg, icon, extraHtml) {
    return '<div class="col-xxl-3 col-sm-6">' +
      '<div class="card p-3 shadow-2 radius-8 border input-form-light h-100">' +
      '<div class="card-body p-0">' +
      '<div class="d-flex align-items-center gap-2 mb-8">' +
      '<span class="w-48-px h-48-px flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle" style="background:' + iconBg + '">' +
      '<iconify-icon icon="' + icon + '" class="icon text-xl"></iconify-icon></span>' +
      '<div><span class="mb-2 fw-medium text-secondary-light text-sm d-block">' + label + '</span>' +
      '<h6 class="fw-semibold mb-0">' + value + '</h6></div></div>' +
      '<p class="text-sm mb-0 text-secondary-light">' + sub + '</p>' + (extraHtml || '') +
      '</div></div></div>';
  }

  function renderKpi() {
    var A = AGG;
    els.kpiRow.innerHTML =
      kpiCard('Total Karyawan Terpantau', A.total.toLocaleString('id'), (state.co === '__ALL__' ? 'Semua perusahaan' : state.co) + (state.site ? ' · ' + state.site : ''), 'var(--primary-600)', 'solar:users-group-rounded-bold') +
      kpiCard('Pelanggaran Regulasi (YTD)', A.npel.toLocaleString('id'), pct(A.npel) + ' dari populasi', 'var(--danger-main)', 'solar:danger-triangle-bold',
        '<div class="mt-8 text-xs" style="line-height:1.7">' +
        '<div>On-site &gt;71 hr tanpa cuti: <b class="text-danger-600">' + A.npel2.toLocaleString('id') + '</b></div>' +
        '<div>Cuti &lt;12 hari: <b class="text-danger-600">' + A.npel3.toLocaleString('id') + '</b></div>' +
        '<div>Kerja &gt;13 hari beruntun: <b class="text-danger-600">' + A.npel1.toLocaleString('id') + '</b></div></div>') +
      kpiCard('Tidak Sesuai Mapping Shift', A.nmap.toLocaleString('id'), pct(A.nmap) + ' · peringatan, bukan pelanggaran', 'var(--warning-main)', 'solar:shield-warning-bold') +
      kpiCard('Wajib Cuti Sekarang', A.nwajib.toLocaleString('id'), 'Sedang on-site &gt;71 hari berjalan, belum cuti', 'var(--wajib-color, #be123c)', 'solar:calendar-mark-bold');
  }

  function renderDonut() {
    if (!window.ApexCharts) return;
    var series = STATUS_ORDER.map(function (s) { return AGG.cur[s] || 0; });
    var options = {
      chart: { type: 'donut', height: 260 },
      labels: STATUS_ORDER,
      series: series,
      colors: STATUS_ORDER.map(function (s) { return STATUS_COLOR[s]; }),
      legend: { position: 'bottom', fontSize: '12px' },
      dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(0) + '%'; } },
      plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total', formatter: function () { return AGG.total.toLocaleString('id'); } } } } } },
      tooltip: { y: { formatter: function (v) { return v.toLocaleString('id') + ' orang'; } } },
    };
    if (donutChart) { donutChart.destroy(); donutChart = null; }
    donutChart = new ApexCharts(els.donut, options);
    donutChart.render();
  }

  function renderSiteAgg() {
    if (state.co !== '__ALL__') { els.siteAggWrap.classList.add('d-none'); return; }
    els.siteAggWrap.classList.remove('d-none');
    var order = ['BMO 1', 'BMO 2', 'BMO 3', 'GMO', 'LMO', 'SMO'];
    var agg = {};
    D.order.forEach(function (co) {
      var c = D.companies[co];
      for (var site in c.sites) {
        if (state.site && site !== state.site) continue;
        c.sites[site].forEach(function (r) {
          var o = compute(r[0], r[1], r[2], r[3], c, state.r0, state.r1);
          if (!agg[site]) agg[site] = { tot: 0, pel: 0, waj: 0 };
          agg[site].tot++;
          if (o.everRed) agg[site].pel++;
          if (o.onCur > 71 && !o.longgar) agg[site].waj++;
        });
      }
    });
    var sites = order.filter(function (s) { return agg[s]; }).concat(Object.keys(agg).filter(function (s) { return order.indexOf(s) < 0; }));
    var tt = 0, tp = 0, tw = 0;
    var rows = sites.map(function (s) {
      var x = agg[s]; tt += x.tot; tp += x.pel; tw += x.waj;
      return '<tr><td><b>' + escapeHtml(s) + '</b></td><td class="text-end">' + x.tot.toLocaleString('id') + '</td>' +
        '<td class="text-end text-danger-600 fw-semibold">' + x.pel.toLocaleString('id') + '</td>' +
        '<td class="text-end fw-semibold" style="color:#be123c">' + x.waj.toLocaleString('id') + '</td></tr>';
    }).join('');
    els.siteAgg.innerHTML = '<table class="table bordered-table mb-0 rk-table"><thead><tr><th>Site</th><th class="text-end">Total</th><th class="text-end">Pelanggaran (YTD)</th><th class="text-end">Wajib Cuti (Kini)</th></tr></thead><tbody>' +
      rows + '<tr style="border-top:2px solid var(--neutral-400,#9ca3af)"><td><b>TOTAL</b></td><td class="text-end"><b>' + tt.toLocaleString('id') + '</b></td>' +
      '<td class="text-end text-danger-600 fw-bold">' + tp.toLocaleString('id') + '</td><td class="text-end fw-bold" style="color:#be123c">' + tw.toLocaleString('id') + '</td></tr></tbody></table>';
  }

  function renderParams() {
    var companies = state.co === '__ALL__' ? D.order : [state.co];
    els.params.innerHTML = companies.map(function (co) {
      var c = D.companies[co], si = SHIFT_INFO[co] || ['-', '-'];
      return '<tr><td class="fw-semibold">' + co + '</td><td>' + escapeHtml(c.full) + '</td><td>' + escapeHtml(c.roster) + '</td>' +
        '<td>' + si[0] + '<div class="text-secondary-light" style="font-size:10.5px">' + si[1] + '</div></td>' +
        '<td class="text-center">' + c.thr + '</td><td class="text-center">' + c.cutimin + ' hr</td></tr>';
    }).join('');
  }

  /* ---------------------------------------------------------------------
   * Filter + sort + paginate + render table
   * ------------------------------------------------------------------- */
  var STR_KEYS = { sid: 1, nama: 1, jab: 1, co: 1, site: 1, status: 1 };

  function applyFilters() {
    var t = (state.q || '').trim().toLowerCase();
    VIEW = ROWS.filter(function (r) {
      if (state.status && r.status !== state.status) return false;
      if (state.note === 'pel' && !r.cats.red) return false;
      if (state.note === 'wajib' && !(r.onCur > 71 && !r.longgar)) return false;
      if (state.note === 'map' && !r.cats.map) return false;
      if (state.roster && r.roster !== +state.roster) return false;
      if (state.jab && r.kat !== state.jab) return false;
      if (t && r.nama.toLowerCase().indexOf(t) < 0 && (r.sid || '').toLowerCase().indexOf(t) < 0) return false;
      return true;
    });
    VIEW.sort(function (a, b) {
      var k = state.sortKey, res;
      if (STR_KEYS[k]) res = (a[k] || '').localeCompare(b[k] || '');
      else res = (a[k] || 0) - (b[k] || 0);
      return res * state.sortDir;
    });
    state.page = 0;
    renderTable();
    renderDetail();
  }

  function statusBadge(r) {
    var badgeStyle = r.status === 'Shift Malam' ? ' style="background:#1e3a8a"' : '';
    var flag = r.cats.red
      ? '<iconify-icon icon="solar:danger-triangle-bold" class="rk-flag-red ms-4" title="Pelanggaran regulasi"></iconify-icon>'
      : (r.cats.map ? '<iconify-icon icon="solar:shield-warning-bold" class="rk-flag-yel ms-4" title="Tidak sesuai mapping shift"></iconify-icon>' : '');
    return '<span class="' + (STATUS_BADGE[r.status] || '') + ' px-10 py-4 rounded-pill fw-medium text-xs d-inline-flex align-items-center"' + badgeStyle + '>' + r.status + flag + '</span>';
  }

  function alertCountCellHtml(upperSid) {
    var v = alertCounts[upperSid];
    if (v === undefined) return '<span class="spinner-border spinner-border-sm text-secondary-light" style="width:12px;height:12px" role="status"></span>';
    if (v === 'x') return '<span class="text-secondary-light" title="Data alert DMS tidak tersedia saat ini">&mdash;</span>';
    if (v === 0) return '<span class="text-secondary-light">0</span>';
    return '<span class="bg-danger-100 text-danger-600 px-8 py-2 rounded-pill fw-medium text-xs">' + v + '</span>';
  }

  /** Ambil jumlah alert DMS 30 hari untuk SID di halaman tabel yang sedang tampil (batch, di-cache per SID). */
  function fetchAlertCountsForPage(rows) {
    if (!ALERT_COUNTS_URL || !ALERT_UNTIL) return;
    var need = [];
    rows.forEach(function (r) {
      var upper = (r.sid || '').toUpperCase();
      if (upper && alertCounts[upper] === undefined && !alertCountsInFlight[upper]) need.push(upper);
    });
    if (!need.length) return;
    need.forEach(function (s) { alertCountsInFlight[s] = true; });

    var qs = new URLSearchParams();
    need.forEach(function (s) { qs.append('sids[]', s); });
    qs.set('until', ALERT_UNTIL);

    fetch(ALERT_COUNTS_URL + '?' + qs.toString())
      .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); })
      .then(function (data) {
        var counts = data.counts || {};
        var available = data.available !== false;
        need.forEach(function (s) {
          alertCounts[s] = !available ? 'x' : (counts.hasOwnProperty(s) ? counts[s] : 0);
          delete alertCountsInFlight[s];
        });
        need.forEach(function (s) {
          els.tableBody.querySelectorAll('[data-alert-sid="' + s + '"]').forEach(function (cell) {
            cell.innerHTML = alertCountCellHtml(s);
          });
        });
      })
      .catch(function () {
        need.forEach(function (s) { alertCounts[s] = 'x'; delete alertCountsInFlight[s]; });
        need.forEach(function (s) {
          els.tableBody.querySelectorAll('[data-alert-sid="' + s + '"]').forEach(function (cell) {
            cell.innerHTML = alertCountCellHtml(s);
          });
        });
      });
  }

  function renderTable() {
    var st = state.page * state.pageSize;
    var pg = VIEW.slice(st, st + state.pageSize);
    els.tableNote.textContent = VIEW.length.toLocaleString('id') + ' karyawan pada kombinasi filter ini';
    els.tableBody.innerHTML = pg.map(function (r) {
      var upperSid = (r.sid || '').toUpperCase();
      return '<tr class="' + (r.id === state.selectedId ? 'is-selected' : '') + '" data-id="' + escapeHtml(r.id) + '">' +
        '<td class="fw-medium">' + escapeHtml(r.sid) + '</td>' +
        '<td class="rk-col-name" title="' + escapeHtml(r.nama + ' — ' + r.jab + ' · ' + r.co + ' ' + r.site) + '"><span class="rk-name">' + escapeHtml(r.nama) + '</span><span class="rk-sub">' + escapeHtml(r.jab) + ' &middot; ' + escapeHtml(r.co) + ' ' + escapeHtml(r.site) + '</span></td>' +
        '<td class="text-center">' + r.roster + '</td>' +
        '<td class="text-center">' + r.onAll + (r.onAll > 71 && !r.longgar ? ' <span class="rk-flag-red">⚠</span>' : '') + '</td>' +
        '<td class="text-center">' + r.cutiMin + (r.cutiMin && r.cutiMin < 12 && !r.longgar ? ' <span class="rk-flag-red">⚠</span>' : '') + '</td>' +
        '<td>' + statusBadge(r) + '</td>' +
        '<td class="text-center" data-alert-sid="' + escapeHtml(upperSid) + '">' + alertCountCellHtml(upperSid) + '</td>' +
        '</tr>';
    }).join('') || '<tr><td colspan="7" class="text-center text-secondary-light py-5">Tidak ada karyawan pada kombinasi filter ini.</td></tr>';

    els.tableBody.querySelectorAll('tr[data-id]').forEach(function (tr, idx) {
      tr.addEventListener('click', function () { state.selectedId = pg[idx].id; renderTable(); renderDetail(); });
    });

    var pages = Math.max(1, Math.ceil(VIEW.length / state.pageSize));
    els.pageInfo.textContent = 'Hal ' + (state.page + 1) + '/' + pages;
    els.prev.disabled = state.page === 0;
    els.next.disabled = state.page >= pages - 1;

    fetchAlertCountsForPage(pg);
  }

  /* ---------------------------------------------------------------------
   * Detail panel
   * ------------------------------------------------------------------- */
  function heatStrip(r) {
    var ruler = '', days = '', prevMonth = '';
    for (var i = state.r0; i <= state.r1; i++) {
      var iso = D.dISO[i];
      var isMonthStart = iso.slice(0, 7) !== prevMonth;
      prevMonth = iso.slice(0, 7);
      ruler += '<span class="' + (isMonthStart ? 'mstart' : '') + '">' + (isMonthStart ? '<b>' + MON[+iso.slice(5, 7) - 1] + '</b>' : '') + '</span>';
      var cls = 'd rk-d-' + r.p[i] + (r.red[i] ? ' rk-d-red' : (r.yel[i] ? ' rk-d-yel' : '')) + (isMonthStart ? ' mstart' : '');
      var dt = new Date(iso + 'T00:00:00Z');
      var title = HARI[dt.getUTCDay()] + ', ' + dt.getUTCDate() + ' ' + MON_LONG[dt.getUTCMonth()] + ' — ' + SHIFT_LABEL[r.p[i]] + ' (hari ke-' + r.dayno[i] + ')' + (r.red[i] ? ' — ' + r.red[i] : (r.yel[i] ? ' — ' + r.yel[i] : ''));
      days += '<span class="' + cls + '" title="' + escapeHtml(title) + '"></span>';
    }
    return '<div class="rk-heat-outer"><div class="rk-heat-inner">' +
      '<div class="rk-heat-ruler">' + ruler + '</div>' +
      '<div class="rk-heat-strip">' + days + '</div>' +
      '</div></div>';
  }

  function flagList(r) {
    var items = [];
    for (var i = state.r0; i <= state.r1; i++) {
      if (r.red[i] && (i === state.r0 || r.red[i] !== r.red[i - 1])) items.push({ i: i, sev: 'red', text: r.red[i] });
      else if (r.yel[i] && !r.red[i] && (i === state.r0 || (r.yel[i] !== r.yel[i - 1] || !!r.red[i - 1]))) items.push({ i: i, sev: 'yel', text: r.yel[i] });
    }
    if (!items.length) return '<div class="text-secondary-light text-sm text-center py-16">Tidak ada flag pada rentang ini.</div>';
    return '<div class="d-flex flex-column gap-2 rk-flaglist-scroll">' + items.slice(0, 30).map(function (it) {
      var cls = it.sev === 'red' ? 'border-danger-100 bg-danger-100 text-danger-600' : 'border-warning-100 bg-warning-100 text-warning-600';
      return '<div class="border rounded-8 px-12 py-8 text-sm ' + cls + '"><b>' + fmtD(it.i) + '</b> — ' + escapeHtml(it.text) + '</div>';
    }).join('') + '</div>' + (items.length > 30 ? '<div class="text-secondary-light text-xs mt-8">+' + (items.length - 30) + ' kejadian lain pada rentang ini.</div>' : '');
  }

  function renderDetail() {
    var r = VIEW.find(function (x) { return x.id === state.selectedId; }) || VIEW[0];
    if (!r) {
      els.detail.innerHTML = '<div class="rk-detail-empty"><iconify-icon icon="solar:user-cross-outline" class="text-4xl mb-8 d-block"></iconify-icon>Tidak ada karyawan terpilih pada filter ini.</div>';
      return;
    }
    state.selectedId = r.id;
    var badgeStyle = r.status === 'Shift Malam' ? ' style="background:#1e3a8a"' : '';
    var stats = [
      ['On-site (roster kini)', r.hadir + ' hr <span class="text-secondary-light">(P:' + r.pagi + ' M:' + r.malam + ' Off:' + r.off + ')</span>'],
      ['Cuti (roster kini)', r.cutiCur + ' hr'],
      ['On-site beruntun kini', r.onCur + ' hr' + (r.onCur > 71 && !r.longgar ? ' <span class="rk-flag-red">⚠ wajib cuti</span>' : '')],
      ['On-site maks (YTD)', r.onAll + ' hr' + (r.onAll > 71 && !r.longgar ? ' <span class="rk-flag-red">⚠</span>' : '') + (r.onAll ? ' <span class="text-secondary-light">· roster ke-' + r.onAllR + ' · ' + fmtD(r.onAllS) + '–' + fmtD(r.onAllE) + '</span>' : '')],
      ['Cuti min (YTD)', (r.cutiMin || '-') + (r.cutiMin ? ' hr' + (r.cutiMin < 12 && !r.longgar ? ' <span class="rk-flag-red">⚠</span>' : '') + ' <span class="text-secondary-light">· roster ke-' + r.cutiMinR + ' · ' + fmtD(r.cutiMinS) + '–' + fmtD(r.cutiMinE) + '</span>' : '')],
      ['Kerja beruntun terpanjang', r.onNoOff + ' hr' + (r.onNoOff ? ' <span class="text-secondary-light">· ' + fmtD(r.onS) + '–' + fmtD(r.onE) + '</span>' : '')],
    ];

    els.detail.innerHTML =
      '<div class="d-flex align-items-start justify-content-between gap-2 mb-16">' +
      '<div><span class="text-secondary-light text-sm">' + escapeHtml(r.sid) + '</span>' +
      '<h6 class="mb-0 mt-4">' + escapeHtml(r.nama) + '</h6>' +
      '<div class="d-flex flex-wrap gap-2 mt-8">' +
      '<span class="bg-neutral-100 text-secondary-light px-10 py-2 rounded-8 text-xs fw-medium">' + escapeHtml(r.co) + '</span>' +
      '<span class="bg-neutral-100 text-secondary-light px-10 py-2 rounded-8 text-xs fw-medium">' + escapeHtml(r.site) + '</span>' +
      '<span class="bg-neutral-100 text-secondary-light px-10 py-2 rounded-8 text-xs fw-medium">' + escapeHtml(r.jab) + '</span>' +
      '<span class="bg-neutral-100 text-secondary-light px-10 py-2 rounded-8 text-xs fw-medium">Roster ke-' + r.roster + '</span>' +
      (r.longgar ? '<span class="bg-info-100 text-info-600 px-10 py-2 rounded-8 text-xs fw-medium">Kategori longgar (' + escapeHtml(r.kat) + ')</span>' : '') +
      '</div></div>' +
      '<span class="' + (STATUS_BADGE[r.status] || '') + ' px-14 py-6 rounded-8 fw-semibold text-sm"' + badgeStyle + '>' + r.status + '</span>' +
      '</div>' +
      (r.cats.red ? '<div class="alert-danger bg-danger-100 text-danger-600 border-danger-100 border px-14 py-10 rounded-8 mb-16 text-sm"><iconify-icon icon="solar:danger-triangle-bold" class="icon me-1 align-middle"></iconify-icon><b>Ada pelanggaran regulasi</b> pada rentang yang ditampilkan — lihat daftar flag di bawah.</div>' :
        (r.cats.map ? '<div class="alert-warning bg-warning-100 text-warning-600 border-warning-100 border px-14 py-10 rounded-8 mb-16 text-sm"><iconify-icon icon="solar:shield-warning-bold" class="icon me-1 align-middle"></iconify-icon>Ada pola tidak sesuai mapping shift pada rentang ini (peringatan).</div>' :
          '<div class="alert-success bg-success-100 text-success-600 border-success-100 border px-14 py-10 rounded-8 mb-16 text-sm"><iconify-icon icon="solar:check-circle-bold" class="icon me-1 align-middle"></iconify-icon>Tidak ada flag pada rentang ini.</div>')) +
      '<div class="row g-2 mb-16">' + stats.map(function (s) {
        var bad = /rk-flag-red/.test(s[1]);
        return '<div class="col-6"><div class="rk-stat-mini' + (bad ? ' is-bad' : '') + '"><div class="k">' + s[0] + '</div><div class="v">' + s[1] + '</div></div></div>';
      }).join('') + '</div>' +
      '<h6 class="text-sm fw-semibold text-secondary-light text-uppercase mb-8">Timeline Pola Kerja</h6>' +
      '<div class="mb-8">' + heatStrip(r) + '</div>' +
      '<div class="d-flex flex-wrap gap-3 text-xs text-secondary-light mb-24">' +
      '<span><i class="rk-legend-dot" style="background:#60a5fa"></i>Pagi</span>' +
      '<span><i class="rk-legend-dot" style="background:#1e3a8a"></i>Malam</span>' +
      '<span><i class="rk-legend-dot" style="background:#e2e6ea"></i>Off</span>' +
      '<span><i class="rk-legend-dot" style="background:#12a150"></i>Cuti</span>' +
      '<span>Kotak bertepi <b class="rk-flag-red">merah</b>/<b class="rk-flag-yel">kuning</b> = ada flag hari itu</span>' +
      '</div>' +
      '<h6 class="text-sm fw-semibold text-secondary-light text-uppercase mb-8">Riwayat Flag (rentang aktif)</h6>' +
      flagList(r) +
      '<h6 class="text-sm fw-semibold text-secondary-light text-uppercase mt-24 mb-8">Alert DMS (30 Hari Terakhir)</h6>' +
      '<div id="rkAlertTimeline">' + alertTimelineLoadingHtml() + '</div>';

    loadAlertTimeline(r.sid);
  }

  /* ---------------------------------------------------------------------
   * Alert DMS per orang (live, bukan bagian dari snapshot roster) — lihat
   * RosterComplianceAlertController / PraOperasiDmsAlertReader.
   * ------------------------------------------------------------------- */
  function alertTimelineLoadingHtml() {
    return '<div class="text-center text-secondary-light py-16"><div class="spinner-border spinner-border-sm text-primary-600" role="status"></div></div>';
  }

  function renderAlertTimeline(list, available) {
    if (available === false) return '<div class="text-secondary-light text-sm text-center py-16">Data alert DMS tidak tersedia saat ini.</div>';
    if (!list.length) return '<div class="text-secondary-light text-sm text-center py-16">Tidak ada alert DMS pada 30 hari terakhir.</div>';
    return '<div class="d-flex flex-column gap-2 rk-flaglist-scroll">' + list.map(function (a) {
      var meta = ALERT_STATUS_META[a.status] || ALERT_STATUS_META.belum;
      return '<div class="d-flex align-items-center justify-content-between border rounded-8 px-12 py-8">' +
        '<div><div class="text-sm fw-medium">' + escapeHtml(a.name) + '</div><div class="text-xs text-secondary-light">' + escapeHtml(a.date) + '</div></div>' +
        '<span class="' + meta.cls + ' px-10 py-4 rounded-pill fw-medium text-xs">' + meta.label + '</span>' +
        '</div>';
    }).join('') + '</div>';
  }

  function loadAlertTimeline(sid) {
    var container = document.getElementById('rkAlertTimeline');
    if (!container) return;
    if (!ALERT_TIMELINE_BASE || !ALERT_UNTIL) {
      container.innerHTML = '<div class="text-secondary-light text-sm text-center py-16">Alert DMS tidak tersedia.</div>';
      return;
    }
    var upper = (sid || '').toUpperCase();
    if (alertTimelineCache[upper]) {
      var cached = alertTimelineCache[upper];
      container.innerHTML = renderAlertTimeline(cached.list, cached.available);
      return;
    }
    var url = ALERT_TIMELINE_BASE + '/' + encodeURIComponent(sid) + '?until=' + encodeURIComponent(ALERT_UNTIL);
    fetch(url)
      .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); })
      .then(function (data) {
        if (state.selectedId == null || (VIEW.find(function (x) { return x.id === state.selectedId; }) || {}).sid !== sid) return;
        var entry = { list: data.timeline || [], available: data.available !== false };
        alertTimelineCache[upper] = entry;
        var stillThere = document.getElementById('rkAlertTimeline');
        if (stillThere) stillThere.innerHTML = renderAlertTimeline(entry.list, entry.available);
      })
      .catch(function () {
        var stillThere = document.getElementById('rkAlertTimeline');
        if (stillThere) stillThere.innerHTML = '<div class="text-danger-600 text-sm text-center py-8">Gagal memuat alert DMS.</div>';
      });
  }

  /* ---------------------------------------------------------------------
   * CSV export (ringkasan — sesuai kolom yang ditampilkan)
   * ------------------------------------------------------------------- */
  function csvCell(x) { x = (x == null ? '' : ('' + x)); return /[",\n\r]/.test(x) ? '"' + x.replace(/"/g, '""') + '"' : x; }
  function buildCsv() {
    var H = ['Perusahaan', 'Site', 'SID', 'Nama', 'Jabatan', 'Roster', 'On-site(kini)', 'Pagi', 'Malam', 'Off', 'Cuti', 'On-site maks YTD', 'Cuti min YTD', 'Status', 'Catatan'];
    var rows = VIEW.map(function (r) {
      var notes = [];
      if (r.cats.red) notes.push('Pelanggaran regulasi');
      if (r.cats.map) notes.push('Tidak sesuai mapping');
      return [r.co, r.site, r.sid, r.nama, r.jab, r.roster, r.hadir, r.pagi, r.malam, r.off, r.cutiCur, r.onAll, r.cutiMin, r.status, notes.join(' | ')];
    });
    return '﻿' + [H].concat(rows).map(function (row) { return row.map(csvCell).join(','); }).join('\r\n');
  }
  function downloadCsv() {
    var blob = new Blob([buildCsv()], { type: 'text/csv;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'kepatuhan_roster_' + (state.co === '__ALL__' ? 'ALL' : state.co) + '_' + D.dISO[state.r0] + '_sd_' + D.dISO[state.r1] + '.csv';
    document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  }

  /* ---------------------------------------------------------------------
   * Wire up events
   * ------------------------------------------------------------------- */
  function wireEvents() {
    els.coPills.addEventListener('click', function (e) {
      var b = e.target.closest('[data-co]');
      if (!b) return;
      state.co = b.getAttribute('data-co');
      state.site = '';
      els.coPills.querySelectorAll('.rk-co-pill').forEach(function (p) { p.classList.toggle('is-active', p === b); });
      recompute();
    });
    els.siteSelect.addEventListener('change', function () { state.site = els.siteSelect.value; recompute(); });
    els.rosterSelect.addEventListener('change', function () { state.roster = els.rosterSelect.value; applyFilters(); });
    els.jabSelect.addEventListener('change', function () { state.jab = els.jabSelect.value; applyFilters(); });
    els.statusSelect.addEventListener('change', function () { state.status = els.statusSelect.value; applyFilters(); });
    els.noteSelect.addEventListener('change', function () { state.note = els.noteSelect.value; applyFilters(); });
    els.search.addEventListener('input', function () { state.q = els.search.value; applyFilters(); });

    els.periodSelect.addEventListener('change', function () {
      var v = els.periodSelect.value;
      var range = [0, NDAY - 1];
      if (v.charAt(0) === 'q') range = quarterRange(+v.slice(1));
      else if (v.charAt(0) === 'm') range = monthRange(+v.slice(1));
      state.r0 = range[0] < 0 ? 0 : range[0];
      state.r1 = range[1] < 0 ? NDAY - 1 : range[1];
      els.dateFrom.value = D.dISO[state.r0];
      els.dateTo.value = D.dISO[state.r1];
      recompute();
    });
    function onCustomRange() {
      var a = D.dISO.indexOf(els.dateFrom.value), b = D.dISO.indexOf(els.dateTo.value);
      if (a < 0) a = 0;
      if (b < 0) b = NDAY - 1;
      if (a > b) { var t = a; a = b; b = t; }
      state.r0 = a; state.r1 = b;
      els.periodSelect.value = '';
      recompute();
    }
    els.dateFrom.addEventListener('change', onCustomRange);
    els.dateTo.addEventListener('change', onCustomRange);
    els.resetRange.addEventListener('click', function () {
      state.r0 = 0; state.r1 = NDAY - 1;
      els.periodSelect.value = '';
      els.dateFrom.value = D.dISO[0]; els.dateTo.value = D.dISO[NDAY - 1];
      recompute();
    });

    els.prev.addEventListener('click', function () { if (state.page > 0) { state.page--; renderTable(); } });
    els.next.addEventListener('click', function () {
      var pages = Math.ceil(VIEW.length / state.pageSize);
      if (state.page < pages - 1) { state.page++; renderTable(); }
    });

    document.querySelectorAll('th[data-k]').forEach(function (th) {
      th.addEventListener('click', function () {
        var k = th.getAttribute('data-k');
        if (state.sortKey === k) state.sortDir = -state.sortDir;
        else { state.sortKey = k; state.sortDir = STR_KEYS[k] ? 1 : -1; }
        document.querySelectorAll('th[data-k] .rk-ar').forEach(function (s) { s.remove(); });
        var sp = document.createElement('span');
        sp.className = 'rk-ar'; sp.style.marginLeft = '4px';
        sp.textContent = state.sortDir > 0 ? '▲' : '▼';
        th.appendChild(sp);
        applyFilters();
      });
    });

    els.exportBtn.addEventListener('click', downloadCsv);
  }
})();
