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
      Edit data langsung seperti spreadsheet. Semua {{ count($gridConfig['columns'] ?? []) }} kolom tabel
      <strong>monitoring_safety_engineering_records</strong> ditampilkan dengan grouped header — kolom bisa disembunyikan lewat tombol "Kolom".
      @if(! empty($picScope['scoped']))
         <br>
         Tampilan dibatasi PIC
         <strong>{{ $picScope['nama'] ?? auth()->user()->name }}</strong>
         @if(! empty($picScope['sid'])) ({{ $picScope['sid'] }}) @endif
         —
         @if(! empty($picScope['all_sites']))
            semua site
         @else
            {{ implode(', ', $picScope['sites'] ?? []) }}
         @endif
         / {{ implode(', ', $picScope['companies'] ?? []) }}.
      @endif
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
         <option value="" @selected($periodYear === null)>Semua Tahun</option>
         @foreach($planYears as $year)
         <option value="{{ $year }}" @selected($periodYear !== null && (int) $periodYear === (int) $year)>{{ $year }}</option>
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
      <div class="crm-col-picker">
         <button type="button" id="mse-btn-columns" class="crm-grid-btn" @disabled(! $tablesReady)>
            <span class="material-symbols-outlined text-base">view_column</span>
            Kolom
         </button>
         <div id="mse-col-picker-panel" class="crm-col-picker-panel" role="dialog" aria-label="Tampilkan Kolom">
            <div class="crm-col-picker-head">
               <span>Tampilkan Kolom</span>
               <div class="crm-col-picker-head-actions">
                  <button type="button" id="mse-col-show-all" class="crm-col-picker-link">Semua</button>
                  <button type="button" id="mse-col-hide-all" class="crm-col-picker-link">Sembunyikan Semua</button>
               </div>
            </div>
            <div class="crm-col-picker-search-wrap">
               <input type="search" id="mse-col-picker-search" class="crm-col-picker-search" placeholder="Cari kolom..." autocomplete="off">
            </div>
            <div id="mse-col-picker-body" class="crm-col-picker-body"></div>
         </div>
      </div>
   </div>
   <p id="mse-grid-status" class="crm-grid-status">Siap</p>
</div>

<div class="crm-grid-legend mb-3">
   <span class="crm-grid-legend-item"><span class="crm-grid-legend-dot crm-grid-legend-dot--on-target"></span> On Target (update ≤ due date)</span>
   <span class="crm-grid-legend-item"><span class="crm-grid-legend-dot crm-grid-legend-dot--overdue"></span> Overdue (update &gt; due date)</span>
   <span class="crm-grid-legend-item text-crm-muted">Klik kanan → Riwayat baris / kolom · Hanya sel yang berubah yang disimpan dan dicatat</span>
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
      <div id="mse-history-filters" class="crm-history-filters" hidden>
         <button type="button" id="mse-history-filter-all" class="crm-history-filter crm-history-filter--active" data-field="">Semua kolom</button>
         <button type="button" id="mse-history-filter-field" class="crm-history-filter" hidden></button>
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
   const currentYear = @json($currentYear);
   const gridConfig = @json($gridConfig);
   const picScope = @json($picScope ?? ['scoped' => false]);
   const recordsUrl = @json(route('monitoring-safety-engineering.data-update.records'));
   const saveUrl = @json(route('monitoring-safety-engineering.data-update.save'));
   const historyUrlTemplate = @json(route('monitoring-safety-engineering.data-update.history', ['recordId' => 0]));
   const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
   const columnVisibilityStorageKey = 'mse-data-update-hidden-columns';

   const statusFields = new Set([
      'kajian_teknis_status',
      'pengadaan_status',
      'uji_coba_status',
      'standardisasi_status',
   ]);
   const valueFilterKeys = new Set(['site', 'perusahaan', 'sumber_rekayasa', 'pelaksana_rekayasa']);
   let lastHeaderProp = null;

   function currentHeaderProp() {
      const instance = hot;
      if (!instance) return lastHeaderProp;
      const filtersPlugin = instance.getPlugin('filters');
      const selected = filtersPlugin && typeof filtersPlugin.getSelectedColumn === 'function'
         ? filtersPlugin.getSelectedColumn()
         : null;
      if (selected && typeof selected.visualIndex === 'number' && selected.visualIndex >= 0) {
         const prop = instance.colToProp(selected.visualIndex);
         if (typeof prop === 'string') return prop;
      }
      return lastHeaderProp;
   }

   function isValueFilterColumn() {
      const prop = currentHeaderProp();
      return typeof prop === 'string' && valueFilterKeys.has(prop);
   }

   const alertEl = document.getElementById('mse-grid-alert');
   const statusEl = document.getElementById('mse-grid-status');
   const container = document.getElementById('mse-record-grid');
   const historyModal = document.getElementById('mse-history-modal');
   const historyBody = document.getElementById('mse-history-body');
   const historySubtitle = document.getElementById('mse-history-subtitle');
   const historyBtn = document.getElementById('mse-btn-history');
   const historyFilters = document.getElementById('mse-history-filters');
   const historyFilterAll = document.getElementById('mse-history-filter-all');
   const historyFilterField = document.getElementById('mse-history-filter-field');
   const editableFields = new Set(gridConfig.editable_fields || gridConfig.columns.map(function (col) { return col.key; }));
   const fieldLabels = {};
   (gridConfig.columns || []).forEach(function (col) {
      fieldLabels[col.key] = col.label;
   });

   let hot = null;
   let selectedRecordId = null;
   let selectedFieldKey = null;
   let historyRecordId = null;
   let historyField = null;
   let lastHistoryColumnField = null;
   const dirtyById = new Map();

   function historyUrl(recordId, field) {
      let url = historyUrlTemplate.replace('/0/history', '/' + recordId + '/history');
      if (field) {
         url += '?field=' + encodeURIComponent(field);
      }
      return url;
   }

   function dirtyCount() {
      let count = 0;
      dirtyById.forEach(function (fields) {
         count += fields.size;
      });
      return count;
   }

   function markDirty(visualRow, prop) {
      if (!hot || !editableFields.has(prop)) return;
      const physical = hot.toPhysicalRow(visualRow);
      const rowData = hot.getSourceData()[physical];
      if (!rowData || !rowData.id) return;
      const id = parseInt(rowData.id, 10);
      if (!id) return;
      if (!dirtyById.has(id)) dirtyById.set(id, new Set());
      dirtyById.get(id).add(prop);
   }

   function clearDirtyForIds(ids) {
      ids.forEach(function (id) {
         dirtyById.delete(id);
      });
   }

   function escapeHtml(text) {
      return String(text ?? '')
         .replace(/&/g, '&amp;')
         .replace(/</g, '&lt;')
         .replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;');
   }

   function updateSelectedRecord(rowIndex, colIndex) {
      if (!hot || rowIndex === null || rowIndex === undefined || rowIndex < 0) {
         selectedRecordId = null;
         selectedFieldKey = null;
         if (historyBtn) historyBtn.disabled = true;
         return;
      }

      const physical = hot.toPhysicalRow(rowIndex);
      const rowData = hot.getSourceData()[physical] || {};
      selectedRecordId = rowData.id ? parseInt(rowData.id, 10) : null;
      const prop = typeof colIndex === 'number' && colIndex >= 0 ? hot.colToProp(colIndex) : null;
      selectedFieldKey = typeof prop === 'string' ? prop : null;
      if (historyBtn) historyBtn.disabled = !selectedRecordId;
   }

   function renderHistory(payload) {
      if (!historyBody) return;

      const entries = payload.entries || [];
      if (entries.length === 0) {
         historyBody.innerHTML = '<p class="crm-history-empty">' + escapeHtml(payload.message || 'Belum ada riwayat perubahan untuk filter ini.') + '</p>';
         return;
      }

      historyBody.innerHTML = entries.map(function (entry) {
         const oldVal = entry.old_value === null || entry.old_value === '' ? '—' : entry.old_value;
         const newVal = entry.new_value === null || entry.new_value === '' ? '—' : entry.new_value;
         const actionClass = entry.action === 'created' ? 'crm-history-action--created' : 'crm-history-action--updated';
         const actionLabel = entry.action === 'created' ? 'Dibuat' : 'Diupdate';

         return '<article class="crm-history-entry">'
            + '<div class="crm-history-entry-meta">'
            + '<span class="crm-history-week">' + escapeHtml(entry.review_week || '') + '</span>'
            + '<span class="crm-history-action ' + actionClass + '">' + actionLabel + '</span>'
            + '<span class="crm-history-meta">' + escapeHtml(entry.changed_at || '') + '</span>'
            + '<span class="crm-history-meta">oleh ' + escapeHtml(entry.changed_by_name || 'Sistem') + '</span>'
            + '</div>'
            + '<div class="crm-history-entry-body">'
            + '<div class="crm-history-field">' + escapeHtml(entry.field_label || entry.field_name || '') + '</div>'
            + '<div class="crm-history-diff">'
            + '<span class="crm-history-old">' + escapeHtml(oldVal) + '</span>'
            + '<span class="crm-history-arrow" aria-hidden="true">→</span>'
            + '<span class="crm-history-new">' + escapeHtml(newVal) + '</span>'
            + '</div>'
            + '</div>'
            + '</article>';
      }).join('');
   }

   function syncHistoryFilters(activeField) {
      if (!historyFilters) return;
      historyFilters.hidden = false;
      historyFilterAll?.classList.toggle('crm-history-filter--active', !activeField);

      if (lastHistoryColumnField && historyFilterField) {
         historyFilterField.hidden = false;
         historyFilterField.dataset.field = lastHistoryColumnField;
         historyFilterField.textContent = 'Kolom: ' + (fieldLabels[lastHistoryColumnField] || lastHistoryColumnField);
         historyFilterField.classList.toggle('crm-history-filter--active', activeField === lastHistoryColumnField);
      } else if (historyFilterField) {
         historyFilterField.hidden = true;
         historyFilterField.classList.remove('crm-history-filter--active');
      }
   }

   async function openHistoryModal(recordId, field) {
      if (!recordId || !historyModal) return;

      if (historyRecordId !== recordId) {
         lastHistoryColumnField = null;
      }
      historyRecordId = recordId;
      historyField = field || null;
      if (historyField) {
         lastHistoryColumnField = historyField;
      }
      historyModal.classList.add('crm-history-modal--open');
      if (historyBody) historyBody.innerHTML = '<p class="crm-history-empty">Memuat riwayat...</p>';
      syncHistoryFilters(historyField);

      try {
         const response = await fetch(historyUrl(recordId, historyField), { headers: { Accept: 'application/json' } });
         const payload = await response.json();

         if (!response.ok) {
            throw new Error(payload.message || 'Gagal memuat riwayat.');
         }

         if (historySubtitle) {
            const fieldNote = payload.field_label ? (' · ' + payload.field_label) : '';
            historySubtitle.textContent = (payload.pengendalian_rekayasa || 'Record #' + recordId)
               + ' · ' + (payload.site || '') + ' · ' + (payload.perusahaan || '')
               + fieldNote
               + ' · ' + (payload.total_changes || 0) + ' perubahan';
         }

         if (payload.field_label && historyFilterField && historyField) {
            historyFilterField.textContent = 'Kolom: ' + payload.field_label;
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
            cfg.wordWrap = false;
         } else if (col.type === 'date') {
            cfg.type = 'date';
            cfg.dateFormat = 'YYYY-MM-DD';
            cfg.correctFormat = true;
            cfg.allowEmpty = true;
            cfg.wordWrap = false;
         } else if (col.type === 'numeric') {
            cfg.type = 'numeric';
            cfg.allowEmpty = true;
            cfg.wordWrap = false;
         } else {
            cfg.type = 'text';
            cfg.wordWrap = false;
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

   function loadHiddenColumns() {
      try {
         const raw = window.localStorage.getItem(columnVisibilityStorageKey);
         const parsed = raw ? JSON.parse(raw) : [];
         return Array.isArray(parsed) ? parsed.filter(function (n) { return typeof n === 'number'; }) : [];
      } catch (error) {
         return [];
      }
   }

   function saveHiddenColumns(columns) {
      try {
         window.localStorage.setItem(columnVisibilityStorageKey, JSON.stringify(columns));
      } catch (error) {
         // localStorage unavailable — ignore, preference just won't persist.
      }
   }

   function computeGridHeight() {
      const toolbar = document.querySelector('.crm-grid-wrap');
      const top = toolbar ? toolbar.getBoundingClientRect().top : 260;
      return Math.max(420, Math.floor(window.innerHeight - top - 32));
   }

   function initGrid() {
      if (!tablesReady || !container || typeof Handsontable === 'undefined') {
         return;
      }

      hot = new Handsontable(container, {
         data: [],
         columns: buildColumns(),
         nestedHeaders: buildNestedHeaders(),
         fixedColumnsStart: gridConfig.fixed_columns_left || 0,
         rowHeaders: true,
         colHeaders: true,
         stretchH: 'none',
         wordWrap: false,
         autoRowSize: false,
         autoColumnSize: false,
         rowHeights: 22,
         autoWrapRow: true,
         autoWrapCol: true,
         enterMoves: { row: 1, col: 0 },
         tabMoves: { row: 0, col: 1 },
         fillHandle: true,
         manualColumnResize: true,
         manualRowResize: true,
         hiddenColumns: {
            columns: loadHiddenColumns(),
            indicators: true,
         },
         contextMenu: {
            items: {
               row_above: {},
               row_below: {},
               remove_row: {},
               sep1: '---------',
               mse_history: {
                  name: 'Riwayat baris',
                  callback: function (_key, selection) {
                     const row = selection?.[0]?.start?.row;
                     if (row === undefined || !hot) return;
                     const physical = hot.toPhysicalRow(row);
                     const rowData = hot.getSourceData()[physical] || {};
                     if (rowData.id) openHistoryModal(parseInt(rowData.id, 10), null);
                  },
                  disabled: function () {
                     const sel = hot?.getSelectedLast?.();
                     if (!sel) return true;
                     const physical = hot.toPhysicalRow(sel[0]);
                     const rowData = hot.getSourceData()[physical] || {};
                     return !rowData.id;
                  },
               },
               mse_history_field: {
                  name: 'Riwayat kolom ini',
                  callback: function (_key, selection) {
                     const start = selection?.[0]?.start;
                     if (!start || !hot) return;
                     const physical = hot.toPhysicalRow(start.row);
                     const rowData = hot.getSourceData()[physical] || {};
                     const prop = hot.colToProp(start.col);
                     if (rowData.id && typeof prop === 'string') {
                        openHistoryModal(parseInt(rowData.id, 10), prop);
                     }
                  },
                  disabled: function () {
                     const sel = hot?.getSelectedLast?.();
                     if (!sel) return true;
                     const physical = hot.toPhysicalRow(sel[0]);
                     const rowData = hot.getSourceData()[physical] || {};
                     const prop = hot.colToProp(sel[1]);
                     return !rowData.id || typeof prop !== 'string' || !editableFields.has(prop);
                  },
               },
               sep2: '---------',
               hidden_columns_hide: {},
               hidden_columns_show: {},
               sep3: '---------',
               copy: {},
               cut: {},
            },
         },
         dropdownMenu: {
            items: {
               filter_by_value: {
                  hidden: function () {
                     return !isValueFilterColumn();
                  },
               },
               filter_by_condition: {
                  hidden: function () {
                     return isValueFilterColumn();
                  },
               },
               filter_operators: {
                  hidden: function () {
                     return isValueFilterColumn();
                  },
               },
               filter_action_bar: {},
            },
         },
         filters: true,
         columnSorting: true,
         licenseKey: 'non-commercial-and-evaluation',
         height: computeGridHeight(),
         width: '100%',
         className: 'htMiddle htLeft',
         afterOnCellMouseDown: function (_event, coords) {
            if (!hot || !coords || coords.col < 0 || coords.row >= 0) return;
            const prop = hot.colToProp(coords.col);
            if (typeof prop === 'string') lastHeaderProp = prop;
         },
         afterChange: function (changes, source) {
            if (!changes || source === 'loadData') return;
            changes.forEach(function (change) {
               const row = change[0];
               const prop = change[1];
               const oldVal = change[2];
               const newVal = change[3];
               if (oldVal === newVal) return;
               markDirty(row, prop);
            });
            const cells = dirtyCount();
            setStatus(cells > 0 ? (cells + ' sel belum disimpan') : 'Ada perubahan belum disimpan', '');
         },
         afterSelection: function (row, col) {
            updateSelectedRecord(row, col);
         },
         afterRenderer: function (td, _row, _col, prop, value) {
            if (statusFields.has(prop)) return;
            const text = value === null || value === undefined ? '' : String(value);
            if (text.length > 24) {
               td.title = text;
            } else {
               td.removeAttribute('title');
            }
         },
         afterHideColumns: function (_current, hidden) {
            saveHiddenColumns(hidden);
            renderColumnPicker();
         },
         afterUnhideColumns: function (_current, hidden) {
            saveHiddenColumns(hidden);
            renderColumnPicker();
         },
      });

      renderColumnPicker();

      window.addEventListener('resize', function () {
         if (!hot) return;
         hot.updateSettings({ height: computeGridHeight() });
      });
   }

   async function loadRecords() {
      if (!hot) return;

      hideAlert();
      setStatus('Memuat data...', '');

      try {
         const query = periodYear === null || periodYear === undefined
            ? ''
            : '?period_year=' + encodeURIComponent(periodYear);
         const response = await fetch(recordsUrl + query, {
            headers: { Accept: 'application/json' },
         });
         const payload = await response.json();

         if (!response.ok) {
            throw new Error(payload.message || 'Gagal memuat data.');
         }

         const rows = payload.data || [];
         hot.loadData(rows);
         dirtyById.clear();
         const yearLabel = periodYear === null || periodYear === undefined ? 'Semua Tahun' : ('Tahun ' + periodYear);
         setStatus(rows.length + ' baris · ' + yearLabel, 'success');
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
      row.period_year = periodYear === null || periodYear === undefined ? currentYear : periodYear;
      row.sort_order = hot ? hot.countRows() + 1 : 1;
      row.row_no = hot ? hot.countRows() + 1 : 1;
      row.terkait_hazard = 'Tidak';
      row.terkait_insiden = 'Tidak';
      row.potensi_peningkatan_efektivitas = 'Tidak';
      if (picScope && picScope.scoped) {
         if (picScope.lock_site && picScope.sites && picScope.sites.length === 1) {
            row.site = picScope.sites[0];
         }
         if (picScope.lock_perusahaan && picScope.companies && picScope.companies.length === 1) {
            row.perusahaan = picScope.companies[0];
         }
      }
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

   function isNewRowFilled(row) {
      if (!row || row.id) return false;
      const pengendalian = String(row.pengendalian_rekayasa ?? '').trim();
      const site = String(row.site ?? '').trim();
      const perusahaan = String(row.perusahaan ?? '').trim();
      return pengendalian !== '' && site !== '' && perusahaan !== '';
   }

   function pickRowFields(row, fields) {
      const payload = { client_index: fields.client_index };
      if (row.id) payload.id = row.id;
      fields.keys.forEach(function (key) {
         if (Object.prototype.hasOwnProperty.call(row, key)) {
            payload[key] = row[key];
         }
      });
      return payload;
   }

   function collectSaveRows() {
      const source = hot.getSourceData() || [];
      const rows = [];

      source.forEach(function (row, physicalIndex) {
         if (!row) return;

         if (!row.id) {
            if (!isNewRowFilled(row)) return;
            const keys = (gridConfig.editable_fields || []).slice();
            keys.push('row_no', 'sort_order', 'period_year');
            rows.push(pickRowFields(row, { client_index: physicalIndex, keys: keys }));
            return;
         }

         const id = parseInt(row.id, 10);
         const dirty = dirtyById.get(id);
         if (!dirty || dirty.size === 0) return;

         rows.push(pickRowFields(row, {
            client_index: physicalIndex,
            keys: Array.from(dirty),
         }));
      });

      return rows;
   }

   async function saveRecords() {
      if (!hot) return;

      hideAlert();
      const rows = collectSaveRows();

      if (rows.length === 0) {
         showAlert('Tidak ada perubahan untuk disimpan.', 'info');
         setStatus('Tidak ada perubahan', '');
         return;
      }

      setStatus('Menyimpan ' + rows.length + ' baris...', '');

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
               period_year: periodYear === null || periodYear === undefined ? currentYear : periodYear,
               rows: rows,
            }),
         });

         const payload = await response.json();

         if (!response.ok && !(payload.created || payload.updated)) {
            const errorLines = (payload.errors || []).join('\n');
            throw new Error(payload.message + (errorLines ? '\n' + errorLines : ''));
         }

         const source = hot.getSourceData();
         const savedIds = [];
         (payload.saved || []).forEach(function (item) {
            const index = item.client_index;
            if (typeof index !== 'number' || !source[index]) return;
            source[index].id = item.id;
            savedIds.push(parseInt(item.id, 10));
         });
         clearDirtyForIds(savedIds);
         hot.render();

         let message = payload.message || 'Data berhasil disimpan.';
         if (payload.errors && payload.errors.length > 0) {
            message += ' Peringatan: ' + payload.errors.join(' | ');
            showAlert(message, 'info');
            setStatus('Tersimpan sebagian', '');
         } else {
            showAlert(message, 'success');
            const remaining = dirtyCount();
            setStatus(remaining > 0 ? remaining + ' sel belum disimpan' : 'Tersimpan', 'success');
         }
      } catch (error) {
         showAlert(error.message || 'Gagal menyimpan data.', 'error');
         setStatus('Gagal menyimpan', 'error');
      } finally {
         if (saveBtn) saveBtn.disabled = !tablesReady;
      }
   }

   function columnGroupForIndex(index) {
      const groups = gridConfig.nested_headers[0] || [];
      let offset = 0;
      for (let i = 0; i < groups.length; i++) {
         const span = groups[i].colspan || 1;
         if (index < offset + span) return groups[i].label;
         offset += span;
      }
      return '';
   }

   function renderColumnPicker() {
      const body = document.getElementById('mse-col-picker-body');
      if (!body || !hot) return;

      const plugin = hot.getPlugin('hiddenColumns');
      const hidden = new Set(plugin.getHiddenColumns());
      const query = String(document.getElementById('mse-col-picker-search')?.value || '')
         .trim()
         .toLowerCase();
      let currentGroup = null;
      let html = '';
      let visibleCount = 0;

      gridConfig.columns.forEach(function (col, index) {
         const group = columnGroupForIndex(index);
         const haystack = (group + ' ' + col.label).toLowerCase();
         if (query && haystack.indexOf(query) === -1) return;

         if (group !== currentGroup) {
            currentGroup = group;
            html += '<p class="crm-col-picker-group">' + escapeHtml(group) + '</p>';
         }
         const checked = hidden.has(index) ? '' : 'checked';
         html += '<label class="crm-col-picker-item">'
            + '<input type="checkbox" data-col-index="' + index + '" ' + checked + '>'
            + '<span>' + escapeHtml(col.label) + '</span>'
            + '</label>';
         visibleCount += 1;
      });

      body.innerHTML = visibleCount === 0
         ? '<p class="crm-col-picker-empty">Kolom tidak ditemukan.</p>'
         : html;
   }

   function positionColumnPanel() {
      const panel = document.getElementById('mse-col-picker-panel');
      const trigger = document.getElementById('mse-btn-columns');
      if (!panel || !trigger) return;

      const rect = trigger.getBoundingClientRect();
      const width = panel.offsetWidth || 288;
      const left = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8);
      let top = rect.bottom + 6;
      const maxHeight = Math.min(448, window.innerHeight - 24);
      if (top + Math.min(panel.scrollHeight || 320, maxHeight) > window.innerHeight - 8) {
         top = Math.max(8, rect.top - Math.min(panel.offsetHeight || 320, maxHeight) - 6);
      }

      panel.style.top = top + 'px';
      panel.style.left = left + 'px';
   }

   function toggleColumnPanel(forceState) {
      const panel = document.getElementById('mse-col-picker-panel');
      if (!panel) return;
      const shouldOpen = forceState !== undefined ? forceState : !panel.classList.contains('crm-col-picker-panel--open');
      panel.classList.toggle('crm-col-picker-panel--open', shouldOpen);
      if (shouldOpen) {
         positionColumnPanel();
         document.getElementById('mse-col-picker-search')?.focus();
      }
   }

   document.getElementById('mse-btn-columns')?.addEventListener('click', function (event) {
      event.stopPropagation();
      toggleColumnPanel();
   });

   document.getElementById('mse-col-picker-search')?.addEventListener('input', function () {
      renderColumnPicker();
   });

   window.addEventListener('resize', function () {
      const panel = document.getElementById('mse-col-picker-panel');
      if (panel?.classList.contains('crm-col-picker-panel--open')) {
         positionColumnPanel();
      }
   });

   document.getElementById('mse-col-picker-body')?.addEventListener('change', function (event) {
      const target = event.target;
      if (!hot || !(target instanceof HTMLInputElement) || target.type !== 'checkbox') return;
      const index = parseInt(target.getAttribute('data-col-index') || '', 10);
      if (Number.isNaN(index)) return;
      const plugin = hot.getPlugin('hiddenColumns');
      if (target.checked) {
         plugin.showColumn(index);
      } else {
         plugin.hideColumn(index);
      }
      hot.render();
   });

   document.getElementById('mse-col-show-all')?.addEventListener('click', function () {
      if (!hot) return;
      hot.getPlugin('hiddenColumns').showColumns(gridConfig.columns.map(function (_c, i) { return i; }));
      hot.render();
      renderColumnPicker();
   });

   document.getElementById('mse-col-hide-all')?.addEventListener('click', function () {
      if (!hot) return;
      hot.getPlugin('hiddenColumns').hideColumns(gridConfig.columns.map(function (_c, i) { return i; }));
      hot.render();
      renderColumnPicker();
   });

   document.addEventListener('click', function (event) {
      const panel = document.getElementById('mse-col-picker-panel');
      const trigger = document.getElementById('mse-btn-columns');
      if (!panel || !panel.classList.contains('crm-col-picker-panel--open')) return;
      if (panel.contains(event.target) || trigger?.contains(event.target)) return;
      toggleColumnPanel(false);
   });

   document.getElementById('mse-btn-reload')?.addEventListener('click', loadRecords);
   document.getElementById('mse-btn-add-row')?.addEventListener('click', addRow);
   document.getElementById('mse-btn-save')?.addEventListener('click', saveRecords);
   historyBtn?.addEventListener('click', function () {
      if (selectedRecordId) openHistoryModal(selectedRecordId, null);
   });
   historyFilterAll?.addEventListener('click', function () {
      if (historyRecordId) openHistoryModal(historyRecordId, null);
   });
   historyFilterField?.addEventListener('click', function () {
      const field = historyFilterField.dataset.field;
      if (historyRecordId && field) openHistoryModal(historyRecordId, field);
   });
   document.getElementById('mse-history-close')?.addEventListener('click', closeHistoryModal);
   historyModal?.addEventListener('click', function (event) {
      if (event.target === historyModal) closeHistoryModal();
   });
   document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
         closeHistoryModal();
         toggleColumnPanel(false);
      }
   });

   initGrid();
   if (tablesReady) {
      loadRecords();
   }
})();
</script>
@endpush
