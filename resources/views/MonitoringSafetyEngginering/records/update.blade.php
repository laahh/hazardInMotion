@extends('MonitoringSafetyEngginering.layouts.crm')

@section('title', 'Update Data — Monitoring Safety Engineering')

@push('head')
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('MonitoringSafetyEngginering.partials.crm-styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable@14.6.1/dist/handsontable.full.min.css">
@endpush

@section('content')
<div class="crm-page-header">
   <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-crm-muted mb-1">Monitoring Safety Engineering</p>
   <h1 class="crm-page-title">Update Data Rekayasa</h1>
   <p class="crm-page-subtitle">
      Edit data langsung di grid spreadsheet. Semua {{ count($gridConfig['columns'] ?? []) }} kolom tabel
      <strong>monitoring_safety_engineering_records</strong> ditampilkan dengan grouped header.
   </p>
</div>

@unless($tablesReady)
<div class="crm-grid-alert crm-grid-alert--show crm-grid-alert--error" role="alert">
   <span class="material-symbols-outlined text-sm align-middle mr-1">warning</span>
   Tabel database belum tersedia. Jalankan migration terlebih dahulu.
</div>
@endunless

<div id="mse-grid-alert" class="crm-grid-alert" role="status"></div>

<!-- <form id="mse-grid-filter" method="GET" action="{{ route('monitoring-safety-engineering.data-update.index') }}" class="crm-filter-bar crm-filter-bar--single mb-4">
   <div class="crm-filter-field">
      <label class="crm-filter-label" for="mse-filter-year">Tahun Periode</label>
      <select id="mse-filter-year" name="period_year" class="crm-filter-select">
         @foreach($planYears as $year)
         <option value="{{ $year }}" @selected((int) $periodYear === (int) $year)>{{ $year }}</option>
         @endforeach
      </select>
   </div>
   <div class="crm-filter-field flex items-end">
      <button type="submit" class="crm-grid-btn">
         <span class="material-symbols-outlined text-base">filter_alt</span>
         Terapkan Filter
      </button>
   </div>
</form> -->

<div class="crm-grid-toolbar">
   <div class="crm-grid-toolbar-actions">
      <button type="button" id="mse-btn-reload" class="crm-grid-btn" @disabled(! $tablesReady)>
         <span class="material-symbols-outlined text-base">refresh</span>
         Muat Ulang
      </button>
      <button type="button" id="mse-btn-add-row" class="crm-grid-btn" @disabled(! $tablesReady)>
         <span class="material-symbols-outlined text-base">add</span>
         Tambah Baris
      </button>
      <button type="button" id="mse-btn-save" class="crm-grid-btn crm-grid-btn--primary" @disabled(! $tablesReady)>
         <span class="material-symbols-outlined text-base">save</span>
         Simpan Perubahan
      </button>
      <button type="button" id="mse-btn-history" class="crm-grid-btn" @disabled(! $tablesReady) title="Riwayat perubahan baris terpilih">
         <span class="material-symbols-outlined text-base">history</span>
         Riwayat
      </button>
   </div>
   <p id="mse-grid-status" class="crm-grid-status">Siap</p>
</div>

<div class="crm-grid-legend mb-3">
   <span class="crm-grid-legend-item"><span class="crm-grid-legend-dot crm-grid-legend-dot--on-target"></span> On Target (update ≤ due date)</span>
   <span class="crm-grid-legend-item"><span class="crm-grid-legend-dot crm-grid-legend-dot--overdue"></span> Overdue (update &gt; due date)</span>
   <span class="crm-grid-legend-item text-crm-muted">Klik kanan baris → Riwayat Perubahan · Semua edit tercatat per minggu (Review W)</span>
</div>

<div class="crm-grid-wrap">
   <div id="mse-record-grid" class="crm-grid-container"></div>
</div>

<div id="mse-history-modal" class="crm-history-modal" role="dialog" aria-modal="true" aria-labelledby="mse-history-title">
   <div class="crm-history-panel">
      <div class="crm-history-header">
         <div>
            <p id="mse-history-title" class="crm-history-title">Riwayat Perubahan</p>
            <p id="mse-history-subtitle" class="crm-history-subtitle">—</p>
         </div>
         <button type="button" id="mse-history-close" class="crm-history-close" aria-label="Tutup">&times;</button>
      </div>
      <div id="mse-history-body" class="crm-history-body">
         <p class="crm-history-empty">Memuat riwayat...</p>
      </div>
   </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/handsontable@14.6.1/dist/handsontable.full.min.js"></script>
<script>
(function () {
   const tablesReady = @json($tablesReady);
   const periodYear = @json($periodYear);
   const gridConfig = @json($gridConfig);
   const recordsUrl = @json(route('monitoring-safety-engineering.data-update.records'));
   const saveUrl = @json(route('monitoring-safety-engineering.data-update.save'));
   const historyUrlTemplate = @json(route('monitoring-safety-engineering.data-update.history', ['recordId' => 0]));
   const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

   const statusFields = new Set([
      'kajian_teknis_status',
      'pengadaan_status',
      'uji_coba_status',
      'standardisasi_status',
   ]);

   const alertEl = document.getElementById('mse-grid-alert');
   const statusEl = document.getElementById('mse-grid-status');
   const container = document.getElementById('mse-record-grid');
   const historyModal = document.getElementById('mse-history-modal');
   const historyBody = document.getElementById('mse-history-body');
   const historySubtitle = document.getElementById('mse-history-subtitle');
   const historyBtn = document.getElementById('mse-btn-history');

   let hot = null;
   let selectedRecordId = null;

   function historyUrl(recordId) {
      return historyUrlTemplate.replace('/0/history', '/' + recordId + '/history');
   }

   function escapeHtml(text) {
      return String(text ?? '')
         .replace(/&/g, '&amp;')
         .replace(/</g, '&lt;')
         .replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;');
   }

   function updateSelectedRecord(rowIndex) {
      if (!hot || rowIndex === null || rowIndex === undefined || rowIndex < 0) {
         selectedRecordId = null;
         if (historyBtn) historyBtn.disabled = true;
         return;
      }

      const rowData = hot.getSourceDataAtRow(rowIndex) || {};
      selectedRecordId = rowData.id ? parseInt(rowData.id, 10) : null;
      if (historyBtn) historyBtn.disabled = !selectedRecordId;
   }

   function renderHistory(payload) {
      if (!historyBody) return;

      if (!payload.batches || payload.batches.length === 0) {
         historyBody.innerHTML = '<p class="crm-history-empty">' + escapeHtml(payload.message || 'Belum ada riwayat perubahan untuk record ini.') + '</p>';
         return;
      }

      historyBody.innerHTML = payload.batches.map(function (batch) {
         const actionClass = batch.action === 'created' ? 'crm-history-action--created' : 'crm-history-action--updated';
         const actionLabel = batch.action === 'created' ? 'Dibuat' : 'Diupdate';
         const changesHtml = (batch.changes || []).map(function (change) {
            const oldVal = change.old_value === null || change.old_value === '' ? '—' : change.old_value;
            const newVal = change.new_value === null || change.new_value === '' ? '—' : change.new_value;

            return '<div class="crm-history-change">'
               + '<div class="crm-history-field">' + escapeHtml(change.field_label) + '</div>'
               + '<div class="crm-history-old">' + escapeHtml(oldVal) + '</div>'
               + '<div class="crm-history-new">' + escapeHtml(newVal) + '</div>'
               + '</div>';
         }).join('');

         return '<div class="crm-history-batch">'
            + '<div class="crm-history-batch-head">'
            + '<span class="crm-history-week">' + escapeHtml(batch.review_week) + '</span>'
            + '<span class="crm-history-action ' + actionClass + '">' + actionLabel + '</span>'
            + '<span class="crm-history-meta">' + escapeHtml(batch.changed_at) + '</span>'
            + '<span class="crm-history-meta">oleh ' + escapeHtml(batch.changed_by_name) + '</span>'
            + '<span class="crm-history-meta">' + (batch.changes || []).length + ' field</span>'
            + '</div>'
            + changesHtml
            + '</div>';
      }).join('');
   }

   async function openHistoryModal(recordId) {
      if (!recordId || !historyModal) return;

      historyModal.classList.add('crm-history-modal--open');
      if (historyBody) historyBody.innerHTML = '<p class="crm-history-empty">Memuat riwayat...</p>';

      try {
         const response = await fetch(historyUrl(recordId), { headers: { Accept: 'application/json' } });
         const payload = await response.json();

         if (!response.ok) {
            throw new Error(payload.message || 'Gagal memuat riwayat.');
         }

         if (historySubtitle) {
            historySubtitle.textContent = (payload.pengendalian_rekayasa || 'Record #' + recordId)
               + ' · ' + (payload.site || '') + ' · ' + (payload.perusahaan || '')
               + ' · ' + (payload.total_changes || 0) + ' perubahan';
         }

         renderHistory(payload);
      } catch (error) {
         if (historyBody) {
            historyBody.innerHTML = '<p class="crm-history-empty">' + escapeHtml(error.message || 'Gagal memuat riwayat.') + '</p>';
         }
      }
   }

   function closeHistoryModal() {
      historyModal?.classList.remove('crm-history-modal--open');
   }

   function showAlert(message, type) {
      if (!alertEl) return;
      alertEl.textContent = message;
      alertEl.className = 'crm-grid-alert crm-grid-alert--show crm-grid-alert--' + type;
   }

   function hideAlert() {
      if (!alertEl) return;
      alertEl.className = 'crm-grid-alert';
      alertEl.textContent = '';
   }

   function setStatus(text, tone) {
      if (!statusEl) return;
      statusEl.textContent = text;
      statusEl.className = 'crm-grid-status' + (tone ? ' crm-grid-status--' + tone : '');
   }

   const statusComplianceMap = {
      kajian_teknis_status: 'kajian_teknis_status_compliance',
      pengadaan_status: 'pengadaan_status_compliance',
      uji_coba_status: 'uji_coba_status_compliance',
      standardisasi_status: 'standardisasi_status_compliance',
   };

   const statusChangedAtMap = {
      kajian_teknis_status: 'kajian_teknis_status_changed_at',
      pengadaan_status: 'pengadaan_status_changed_at',
      uji_coba_status: 'uji_coba_status_changed_at',
      standardisasi_status: 'standardisasi_status_changed_at',
   };

   function statusRenderer(instance, td, row, col, prop, value) {
      Handsontable.renderers.TextRenderer.apply(this, arguments);
      const normalized = String(value ?? '').toLowerCase().replace(/\s+/g, '_');
      const rowData = instance.getSourceDataAtRow(row) || {};

      td.classList.remove(
         'ht-status-done', 'ht-status-progress', 'ht-status-notyet',
         'ht-status-on-target', 'ht-status-overdue', 'ht-status-no-due'
      );

      if (normalized === 'done') {
         td.classList.add('ht-status-done');
      } else if (normalized === 'in_progress' || normalized === 'in progress') {
         td.classList.add('ht-status-progress');
      } else {
         td.classList.add('ht-status-notyet');
      }

      const complianceKey = statusComplianceMap[prop];
      const changedAtKey = statusChangedAtMap[prop];
      const compliance = complianceKey ? rowData[complianceKey] : null;
      const changedAt = changedAtKey ? rowData[changedAtKey] : null;

      if (compliance === 'on_target') {
         td.classList.add('ht-status-on-target');
      } else if (compliance === 'overdue') {
         td.classList.add('ht-status-overdue');
      } else if (compliance === 'no_due_date') {
         td.classList.add('ht-status-no-due');
      }

      if (changedAt) {
         const complianceLabel = compliance === 'on_target'
            ? 'On Target'
            : (compliance === 'overdue' ? 'Overdue' : 'Tanpa Due Date');
         td.title = 'Update terakhir: ' + changedAt + ' · ' + complianceLabel;
      } else {
         td.removeAttribute('title');
      }
   }

   function buildColumns() {
      return gridConfig.columns.map(function (col) {
         const cfg = {
            data: col.key,
            readOnly: Boolean(col.read_only),
         };

         if (col.width) {
            cfg.width = col.width;
         }

         if (col.type === 'dropdown') {
            cfg.type = 'dropdown';
            cfg.source = gridConfig.dropdowns[col.key] || [];
            cfg.strict = false;
            cfg.allowInvalid = true;
         } else if (col.type === 'date') {
            cfg.type = 'date';
            cfg.dateFormat = 'YYYY-MM-DD';
            cfg.correctFormat = true;
            cfg.allowEmpty = true;
         } else if (col.type === 'numeric') {
            cfg.type = 'numeric';
            cfg.allowEmpty = true;
         } else {
            cfg.type = 'text';
         }

         if (statusFields.has(col.key)) {
            cfg.renderer = statusRenderer;
         }

         return cfg;
      });
   }

   function buildNestedHeaders() {
      const groupRow = (gridConfig.nested_headers[0] || []).map(function (group) {
         return { label: group.label, colspan: group.colspan };
      });
      const labelRow = gridConfig.columns.map(function (col) {
         return col.label;
      });

      return [groupRow, labelRow];
   }

   function initGrid() {
      if (!tablesReady || !container || typeof Handsontable === 'undefined') {
         return;
      }

      hot = new Handsontable(container, {
         data: [],
         columns: buildColumns(),
         nestedHeaders: buildNestedHeaders(),
         fixedColumnsLeft: gridConfig.fixed_columns_left || 0,
         rowHeaders: true,
         stretchH: 'none',
         autoWrapRow: true,
         autoWrapCol: true,
         manualColumnResize: true,
         manualRowResize: true,
         contextMenu: {
            items: {
               row_above: {},
               row_below: {},
               remove_row: {},
               sep1: '---------',
               mse_history: {
                  name: 'Riwayat Perubahan',
                  callback: function (_key, selection) {
                     const row = selection?.[0]?.start?.row;
                     if (row === undefined || !hot) return;
                     const rowData = hot.getSourceDataAtRow(row) || {};
                     if (rowData.id) openHistoryModal(parseInt(rowData.id, 10));
                  },
                  disabled: function () {
                     const sel = hot?.getSelectedLast?.();
                     if (!sel) return true;
                     const rowData = hot.getSourceDataAtRow(sel[0]) || {};
                     return !rowData.id;
                  },
               },
               sep2: '---------',
               copy: {},
               cut: {},
            },
         },
         dropdownMenu: true,
         filters: true,
         columnSorting: true,
         licenseKey: 'non-commercial-and-evaluation',
         height: 520,
         width: '100%',
         className: 'htMiddle',
         afterChange: function () {
            setStatus('Ada perubahan belum disimpan', '');
         },
         afterSelection: function (_row, _col, _row2, _col2) {
            const sel = hot?.getSelectedLast?.();
            updateSelectedRecord(sel ? sel[0] : null);
         },
      });
   }

   async function loadRecords() {
      if (!hot) return;

      hideAlert();
      setStatus('Memuat data...', '');

      try {
         const response = await fetch(recordsUrl + '?period_year=' + encodeURIComponent(periodYear), {
            headers: { Accept: 'application/json' },
         });
         const payload = await response.json();

         if (!response.ok) {
            throw new Error(payload.message || 'Gagal memuat data.');
         }

         hot.loadData(payload.data || []);
         setStatus((payload.data || []).length + ' baris · Tahun ' + periodYear, 'success');
      } catch (error) {
         showAlert(error.message || 'Gagal memuat data.', 'error');
         setStatus('Gagal memuat', 'error');
      }
   }

   function buildEmptyRow() {
      const row = {};
      gridConfig.columns.forEach(function (col) {
         row[col.key] = null;
      });
      row.period_year = periodYear;
      row.sort_order = hot ? hot.countRows() + 1 : 1;
      row.row_no = hot ? hot.countRows() + 1 : 1;
      row.terkait_hazard = 'Tidak';
      row.terkait_insiden = 'Tidak';
      row.potensi_peningkatan_efektivitas = 'Tidak';
      return row;
   }

   function addRow() {
      if (!hot) return;
      const data = hot.getSourceData();
      data.push(buildEmptyRow());
      hot.loadData(data);
      hot.scrollViewportTo(data.length - 1, 0);
      setStatus('Baris baru ditambahkan — simpan untuk persist', '');
   }

   async function saveRecords() {
      if (!hot) return;

      hideAlert();
      setStatus('Menyimpan...', '');

      const rows = hot.getSourceData().filter(function (row) {
         if (!row) return false;
         const pengendalian = String(row.pengendalian_rekayasa ?? '').trim();
         const site = String(row.site ?? '').trim();
         const perusahaan = String(row.perusahaan ?? '').trim();
         return pengendalian !== '' || site !== '' || perusahaan !== '' || row.id;
      });

      if (rows.length === 0) {
         showAlert('Tidak ada baris valid untuk disimpan.', 'error');
         setStatus('Tidak ada data', 'error');
         return;
      }

      const saveBtn = document.getElementById('mse-btn-save');
      if (saveBtn) saveBtn.disabled = true;

      try {
         const response = await fetch(saveUrl, {
            method: 'POST',
            headers: {
               'Content-Type': 'application/json',
               Accept: 'application/json',
               'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
               period_year: periodYear,
               rows: rows,
            }),
         });

         const payload = await response.json();

         if (!response.ok && !(payload.created || payload.updated)) {
            const errorLines = (payload.errors || []).join('\n');
            throw new Error(payload.message + (errorLines ? '\n' + errorLines : ''));
         }

         let message = payload.message || 'Data berhasil disimpan.';
         if (payload.errors && payload.errors.length > 0) {
            message += ' Peringatan: ' + payload.errors.join(' | ');
            showAlert(message, 'info');
         } else {
            showAlert(message, 'success');
         }

         await loadRecords();
      } catch (error) {
         showAlert(error.message || 'Gagal menyimpan data.', 'error');
         setStatus('Gagal menyimpan', 'error');
      } finally {
         if (saveBtn) saveBtn.disabled = !tablesReady;
      }
   }

   document.getElementById('mse-btn-reload')?.addEventListener('click', loadRecords);
   document.getElementById('mse-btn-add-row')?.addEventListener('click', addRow);
   document.getElementById('mse-btn-save')?.addEventListener('click', saveRecords);
   historyBtn?.addEventListener('click', function () {
      if (selectedRecordId) openHistoryModal(selectedRecordId);
   });
   document.getElementById('mse-history-close')?.addEventListener('click', closeHistoryModal);
   historyModal?.addEventListener('click', function (event) {
      if (event.target === historyModal) closeHistoryModal();
   });
   document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeHistoryModal();
   });

   initGrid();
   if (tablesReady) {
      loadRecords();
   }
})();
</script>
@endpush
