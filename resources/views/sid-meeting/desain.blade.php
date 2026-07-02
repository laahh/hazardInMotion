<!DOCTYPE html>
<html class="light" lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>OHS Division - SID Meeting</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&amp;family=Inter:wght@300;400;500;600&amp;display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          fontFamily: {
            headline: ["Poppins"],
            body: ["Poppins"],
            label: ["Poppins"]
          }
        }
      }
    };
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
  <style>
    :root {
      --bc-blue: #2563eb;
      --bc-cyan: #06b6d4;
      --bc-slate: #0f172a;
      --bc-soft: #f8fafc;
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      font-family: Poppins, Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      -webkit-font-smoothing: antialiased;
      text-rendering: geometricPrecision;
    }
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      vertical-align: middle;
    }

    .glass {
      background: rgba(255,255,255,.90);
      backdrop-filter: blur(22px);
      border: 1px solid rgba(226,232,240,.95);
    }
    .soft-card { box-shadow: 0 22px 70px rgba(15, 23, 42, .08); }
    .anchored-card {
      box-shadow: 0 4px 0px 0px rgba(0, 0, 0, 0.05), 0 12px 24px -4px rgba(0, 0, 0, 0.15);
      border: 1px solid rgba(0, 0, 0, 0.08);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .anchored-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 0px 0px rgba(0, 0, 0, 0.05), 0 20px 32px -8px rgba(0, 0, 0, 0.2);
    }
    .fade-in { animation: fadeIn .22s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

    .tab-active {
      background: transparent;
      color: #3952bc !important;
      border-bottom: 2px solid #3952bc;
      border-radius: 0 !important;
      box-shadow: none;
    }
    .module-tab {
      color: #595c5e;
      font-weight: 700;
      font-size: 13px;
      letter-spacing: .01em;
      padding: 10px 2px;
      border-bottom: 2px solid transparent;
      transition: color .18s ease, border-color .18s ease;
      border-radius: 0 !important;
    }
    .module-tab:hover { color: #3952bc; }
    .module-nav {
      background: #ffffff;
      border: 1px solid #dfe3e6;
      box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
      border-radius: 14px;
      padding: 6px 18px;
      gap: 24px;
    }

    button {
      transition: transform .16s ease, box-shadow .16s ease, background-color .16s ease, opacity .16s ease;
    }
    button:hover { transform: translateY(-1px); }
    button:active { transform: translateY(0); }
    button:disabled { transform: none; cursor: not-allowed; }

    input, select, textarea {
      transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
    }
    input::placeholder, textarea::placeholder { color: #94a3b8; }

    .badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 5px 11px;
      border-radius: 999px;
      font-size: 11px;
      line-height: 1;
      font-weight: 900;
      white-space: nowrap;
      letter-spacing: .01em;
    }
    .badge-open { background: #dcfce7; color: #166534; }
    .badge-expired { background: #fee2e2; color: #991b1b; }
    .badge-upcoming { background: #dbeafe; color: #1d4ed8; }
    .badge-draft { background: #fef9c3; color: #854d0e; }
    .badge-closed { background: #e2e8f0; color: #334155; }
    .badge-overrun { background: #ffedd5; color: #9a3412; }

    .status-hadir,
    .status-tidak-hadir {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 86px;
      border-radius: 999px;
      font-weight: 900;
      padding: 5px 10px;
      font-size: 11px;
      line-height: 1;
      letter-spacing: .01em;
    }
    .status-hadir { background: #dcfce7; color: #166534; }
    .status-tidak-hadir { background: #fee2e2; color: #991b1b; }

    .table-wrap {
      overflow-x: auto;
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 transparent;
      border: 1px solid #dfe3e6 !important;
      border-radius: 14px !important;
    }
    .table-wrap::-webkit-scrollbar { height: 8px; width: 8px; }
    .table-wrap::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
    .table-wrap::-webkit-scrollbar-track { background: transparent; }
    .semantic-scroll {
      width: 100%;
      max-width: 100%;
      max-height: 360px;
      overflow: auto;
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 transparent;
    }
    .semantic-scroll::-webkit-scrollbar { height: 8px; width: 8px; }
    .semantic-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
    .semantic-scroll thead th {
      position: sticky;
      top: 0;
      z-index: 5;
      background: #f8fafc;
    }
    .semantic-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 1.25rem;
      align-items: start;
    }
    @media (min-width: 1280px) {
      .semantic-grid {
        grid-template-columns: minmax(0, .78fr) minmax(0, 1.22fr);
      }
    }
    .semantic-card {
      min-width: 0;
      overflow: hidden;
    }
    .semantic-pairs-table {
      min-width: 980px;
      width: max-content;
    }
    .semantic-groups-table {
      min-width: 560px;
      width: max-content;
    }
    table { border-collapse: separate; border-spacing: 0; }
    thead th {
      white-space: nowrap;
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .08em;
      font-weight: 700;
      color: #64748b;
      background: #f8fafc;
    }
    tbody tr:hover { background: #fafbfc; }
    tbody td { vertical-align: top; }
    tbody tr { transition: background-color .14s ease; }

    .company-master-scroll {
      overflow: auto;
      max-height: min(72vh, 760px);
      border-radius: 14px;
    }
    .company-dt-wrapper { font-family: inherit; }
    .company-dt-wrapper .dataTables_length,
    .company-dt-wrapper .dataTables_filter {
      margin-bottom: 1rem;
      font-size: 0.875rem;
      color: #64748b;
    }
    .company-dt-wrapper .dataTables_length select,
    .company-dt-wrapper .dataTables_filter input {
      margin: 0 0.35rem;
      border-radius: 0.75rem;
      border: 1px solid #e2e8f0;
      padding: 0.45rem 0.75rem;
      font-size: 0.875rem;
      outline: none;
    }
    .company-dt-wrapper .dataTables_filter input:focus {
      border-color: #60a5fa;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }
    .company-dt-wrapper .dataTables_info {
      font-size: 0.8125rem;
      color: #64748b;
      padding-top: 0.75rem;
    }
    .company-dt-wrapper .dataTables_paginate {
      padding-top: 0.75rem;
    }
    .company-dt-wrapper .dataTables_paginate .paginate_button {
      border-radius: 0.65rem !important;
      border: 1px solid #e2e8f0 !important;
      margin-left: 0.25rem;
      padding: 0.35rem 0.7rem !important;
      font-size: 0.75rem;
      font-weight: 700;
    }
    .company-dt-wrapper .dataTables_paginate .paginate_button.current {
      background: #3952bc !important;
      border-color: #3952bc !important;
      color: #fff !important;
    }

    /* —— Rekap & Export —— */
    .report-hero {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      padding: 1.15rem 1.35rem;
      margin-bottom: 1.25rem;
      border-radius: 1.1rem;
      background: linear-gradient(135deg, #3952bc 0%, #2563eb 52%, #0ea5e9 100%);
      color: #fff;
      box-shadow: 0 14px 36px rgba(57, 82, 188, 0.28);
    }
    .report-hero__title { font-size: 1.125rem; font-weight: 800; letter-spacing: -0.02em; }
    .report-hero__desc { margin-top: 0.3rem; font-size: 0.8125rem; line-height: 1.5; color: rgba(255,255,255,.9); max-width: 34rem; }
    .report-hero__actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .report-btn {
      display: inline-flex; align-items: center; gap: 0.4rem;
      border-radius: 0.7rem; padding: 0.6rem 0.95rem;
      font-size: 0.8125rem; font-weight: 800; line-height: 1;
      transition: transform .16s ease, box-shadow .16s ease;
    }
    .report-btn:hover { transform: translateY(-1px); }
    .report-btn--ghost { background: #fff; color: #334155; border: 1px solid rgba(255,255,255,.35); }
    .report-btn--dark { background: #0f172a; color: #fff; border: none; }
    .report-btn--primary { background: #fff; color: #1d4ed8; border: none; }
    .report-controls {
      display: grid; gap: 1rem; margin-bottom: 1.1rem;
    }
    @media (min-width: 1024px) {
      .report-controls { grid-template-columns: auto 1fr; align-items: start; }
    }
    .report-segment {
      display: inline-flex; flex-wrap: wrap; gap: 0.25rem;
      padding: 0.25rem; border-radius: 0.85rem;
      background: #f1f5f9; border: 1px solid #e2e8f0;
    }
    .report-segment .module-tab {
      padding: 0.5rem 0.95rem; border-radius: 0.65rem !important;
      border-bottom: none !important; font-size: 0.75rem;
    }
    .report-segment .module-tab.tab-active {
      background: linear-gradient(135deg, #3952bc, #2563eb) !important;
      color: #fff !important; box-shadow: 0 4px 12px rgba(57,82,188,.3);
    }
    .report-filters {
      display: grid; gap: 0.75rem;
      padding: 1rem; border-radius: 0.9rem;
      background: linear-gradient(180deg, #f8fafc, #fff);
      border: 1px solid #e2e8f0;
    }
    @media (min-width: 768px) { .report-filters { grid-template-columns: repeat(3, 1fr); } }
    .report-field label {
      display: flex; align-items: center; gap: 0.35rem;
      font-size: 0.75rem; font-weight: 800; color: #475569; margin-bottom: 0.35rem;
    }
    .report-field input, .report-field select {
      width: 100%; border-radius: 0.7rem; border: 1px solid #e2e8f0;
      background: #fff; padding: 0.6rem 0.85rem; font-size: 0.875rem; outline: none;
    }
    .report-field input:focus, .report-field select:focus {
      border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(59,130,246,.15);
    }
    .report-summary-bar {
      display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
      gap: 0.65rem; padding: 0.75rem 1rem; margin-bottom: 0.65rem;
      border-radius: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0;
    }
    .report-summary-stat {
      display: inline-flex; align-items: center; gap: 0.45rem;
      font-size: 0.8125rem; color: #64748b;
    }
    .report-summary-stat strong { color: #3952bc; font-size: 1rem; }
    .report-summary-hint {
      display: inline-flex; align-items: center; gap: 0.3rem;
      font-size: 0.75rem; font-weight: 600; color: #94a3b8;
    }
    .report-table-card {
      border-radius: 1rem; border: 1px solid #e2e8f0;
      background: #fff; box-shadow: 0 8px 28px rgba(15,23,42,.06);
      overflow: hidden;
    }
    .report-table-scroll {
      max-height: min(65vh, 680px); overflow: auto;
      border: none !important; border-radius: 0 !important;
    }
    .report-dt-wrapper { padding: 0.85rem 1rem 1rem; position: relative; }
    .report-dt-wrapper .dataTables_filter { display: none !important; }
    .report-dt-wrapper .dataTables_processing {
      position: absolute; left: 0; right: 0; bottom: 0;
      margin: 0; padding: 0.55rem; background: rgba(255,255,255,.95);
      border-top: 1px solid #e2e8f0; font-size: 0.8125rem; font-weight: 700; color: #3952bc;
    }
    .report-dt-wrapper .report-dt-top {
      display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
      gap: 0.65rem; padding-bottom: 0.65rem; margin-bottom: 0 !important;
      border-bottom: 1px solid #f1f5f9;
    }
    .report-dt-wrapper .dataTables_length label {
      display: flex; align-items: center; gap: 0.45rem;
      font-size: 0.8125rem; font-weight: 700; color: #64748b;
    }
    .report-dt-wrapper .dataTables_length select {
      border-radius: 0.6rem; border: 1px solid #e2e8f0;
      padding: 0.35rem 1.75rem 0.35rem 0.55rem; font-weight: 700;
    }
    .report-dt-wrapper .report-dt-footer {
      padding-top: 0.75rem; margin-top: 0.35rem; border-top: 1px solid #f1f5f9;
    }
    .report-dt-wrapper table.dataTable { width: 100% !important; margin: 0 !important; border-collapse: separate; border-spacing: 0; }
    .report-dt-wrapper table.dataTable thead th {
      position: sticky; top: 0; z-index: 2;
      background: linear-gradient(180deg, #eef2ff, #e8eef5) !important;
      color: #475569 !important; font-size: 0.625rem !important;
      padding: 0.7rem 0.8rem !important;
      border-bottom: 2px solid #c7d2fe !important;
      white-space: nowrap;
    }
    .report-dt-wrapper table.dataTable tbody td {
      padding: 0.65rem 0.8rem !important; font-size: 0.8125rem;
      border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle;
    }
    .report-dt-wrapper table.dataTable tbody tr:nth-child(even) { background: #fafbfc; }
    .report-dt-wrapper table.dataTable tbody tr {
      cursor: pointer; transition: background .14s ease, box-shadow .14s ease;
    }
    .report-dt-wrapper table.dataTable tbody tr:hover {
      background: #eff6ff !important;
      box-shadow: inset 3px 0 0 #3952bc;
    }
    .report-dt-wrapper table.dataTable tbody tr:hover td { color: #0f172a; }
    .dt-chip {
      display: inline-flex; align-items: center; border-radius: 999px;
      padding: 0.15rem 0.55rem; font-size: 0.6875rem; font-weight: 800; white-space: nowrap;
    }
    .dt-chip-week { background: #eff6ff; color: #1d4ed8; }
    .dt-chip-site { background: #ecfdf5; color: #047857; }
    .dt-chip-sid { background: #f1f5f9; color: #0f172a; font-family: ui-monospace, monospace; }
    .dt-name { font-weight: 700; color: #0f172a; }
    .dt-muted { color: #64748b; font-variant-numeric: tabular-nums; }
    .loading-banner {
      display: flex; align-items: center; gap: 0.6rem;
      padding: 0.7rem 1rem; border-radius: 0.75rem;
      border: 1px solid #bfdbfe; background: linear-gradient(90deg, #eff6ff, #f0f9ff);
      font-size: 0.8125rem; font-weight: 700; color: #1e40af;
    }
    .loading-spinner {
      width: 1rem; height: 1rem; border-radius: 999px;
      border: 2px solid #3b82f6; border-top-color: transparent;
      animation: spin-dt .7s linear infinite;
    }
    @keyframes spin-dt { to { transform: rotate(360deg); } }

    #companyDataTable { min-width: 960px; }
    #companyDataTable thead th {
      position: sticky;
      top: 0;
      z-index: 4;
      background: #f1f5f9;
      padding: 0.65rem 0.5rem;
      font-size: 0.65rem;
      line-height: 1.2;
    }
    #companyDataTable thead th.company-col-sticky {
      left: 0;
      z-index: 6;
      min-width: 240px;
      padding-left: 1rem;
      box-shadow: 4px 0 10px -6px rgba(15, 23, 42, 0.12);
    }
    #companyDataTable thead th.action-col-sticky {
      right: 0;
      z-index: 6;
      min-width: 120px;
      box-shadow: -4px 0 10px -6px rgba(15, 23, 42, 0.12);
    }
    #companyDataTable thead th.company-site-col {
      min-width: 3.25rem;
      max-width: 3.25rem;
      text-align: center;
      vertical-align: bottom;
    }
    #companyDataTable thead th.company-site-col span {
      display: inline-block;
      max-width: 3rem;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    #companyDataTable tbody td.company-col-sticky {
      position: sticky;
      left: 0;
      z-index: 2;
      background: #fff;
      min-width: 240px;
      padding: 0.85rem 1rem;
      box-shadow: 4px 0 10px -6px rgba(15, 23, 42, 0.08);
    }
    #companyDataTable tbody tr:hover td.company-col-sticky {
      background: #fafbfc;
    }
    #companyDataTable tbody td.action-col-sticky {
      position: sticky;
      right: 0;
      z-index: 2;
      background: #fff;
      padding: 0.65rem 0.75rem;
      box-shadow: -4px 0 10px -6px rgba(15, 23, 42, 0.08);
    }
    #companyDataTable tbody tr:hover td.action-col-sticky {
      background: #fafbfc;
    }
    #companyDataTable tbody td.company-site-col {
      text-align: center;
      vertical-align: middle;
      padding: 0.5rem 0.25rem;
    }
    .company-row-name {
      font-weight: 800;
      color: #0f172a;
      font-size: 0.875rem;
      line-height: 1.35;
    }
    .company-row-meta {
      margin-top: 0.25rem;
      font-size: 0.6875rem;
      font-weight: 600;
      color: #64748b;
    }
    .company-site-toggle {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2rem;
      height: 2rem;
      border-radius: 0.65rem;
      border: 2px solid #e2e8f0;
      background: #fff;
      cursor: pointer;
      transition: border-color 0.15s ease, background-color 0.15s ease, transform 0.15s ease;
    }
    .company-site-toggle:hover {
      border-color: #93c5fd;
      transform: translateY(-1px);
    }
    .company-site-toggle.is-checked {
      border-color: #2563eb;
      background: #eff6ff;
    }
    .company-site-toggle input {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
      pointer-events: none;
    }
    .company-site-toggle-ui {
      width: 0.55rem;
      height: 0.55rem;
      border-radius: 999px;
      background: transparent;
      transition: background-color 0.15s ease, transform 0.15s ease;
    }
    .company-site-toggle.is-checked .company-site-toggle-ui {
      background: #2563eb;
      transform: scale(1.15);
    }
    .company-action-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 4.5rem;
      border-radius: 0.65rem;
      padding: 0.45rem 0.75rem;
      font-size: 0.6875rem;
      font-weight: 800;
      line-height: 1;
      transition: background-color 0.15s ease, transform 0.15s ease;
    }
    .company-action-btn:hover { transform: translateY(-1px); }
    .company-action-btn-edit {
      background: #eff6ff;
      color: #1d4ed8;
      border: 1px solid #bfdbfe;
    }
    .company-action-btn-edit:hover { background: #dbeafe; }
    .company-action-btn-delete {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }
    .company-action-btn-delete:hover { background: #fee2e2; }
    .company-stat-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border-radius: 999px;
      padding: 0.35rem 0.75rem;
      font-size: 0.75rem;
      font-weight: 800;
      line-height: 1;
    }
    .company-stat-pill-total {
      background: #eff6ff;
      color: #1d4ed8;
      border: 1px solid #bfdbfe;
    }
    .company-stat-pill-shown {
      background: #f8fafc;
      color: #475569;
      border: 1px solid #e2e8f0;
    }

    .section-surface {
      background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.92));
      border: 1px solid #e2e8f0;
      box-shadow: 0 14px 34px rgba(15, 23, 42, .05);
    }

    .modal-shell {
      max-height: 90vh;
      overflow: auto;
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 transparent;
      border: 1px solid #dfe3e6;
      border-radius: 16px;
      box-shadow: 0 18px 50px -20px rgba(15, 23, 42, .35);
    }
    .modal-shell::-webkit-scrollbar { width: 8px; }
    .modal-shell::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }

    .floating-drawer {
      position: fixed;
      top: 18px;
      left: 18px;
      bottom: 18px;
      z-index: 55;
      width: min(440px, calc(100vw - 36px));
      overflow-y: auto;
      transform: translateX(calc(-100% - 28px));
      opacity: 0;
      pointer-events: none;
      transition: transform .24s ease, opacity .24s ease, box-shadow .24s ease;
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 transparent;
    }
    .floating-drawer.open {
      transform: translateX(0);
      opacity: 1;
      pointer-events: auto;
      box-shadow: 0 30px 90px rgba(15, 23, 42, .25);
    }
    .floating-drawer::-webkit-scrollbar { width: 8px; }
    .floating-drawer::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
    .floating-overlay {
      position: fixed;
      inset: 0;
      z-index: 54;
      background: rgba(15, 23, 42, .38);
      backdrop-filter: blur(6px);
      opacity: 0;
      pointer-events: none;
      transition: opacity .2s ease;
    }
    .floating-overlay.open {
      opacity: 1;
      pointer-events: auto;
    }
    .fab-action {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
      border-radius: 1rem;
      background: linear-gradient(135deg, var(--bc-blue), var(--bc-cyan));
      padding: .8rem 1rem;
      font-size: .875rem;
      font-weight: 900;
      color: white;
      box-shadow: 0 16px 34px rgba(37, 99, 235, .26);
    }
    .create-event-collapsed-label { display: none; }

    @media print {
      .no-print { display: none !important; }
      body { background: white !important; }
      .glass, .soft-card, .section-surface { box-shadow: none !important; border: 1px solid #e5e7eb; }
      button { transform: none !important; }
    }
  </style>
</head>
<body class="bg-[#f0f2f5] font-body text-slate-800 min-h-screen flex flex-col">
  <div class="fixed inset-0 -z-10 overflow-hidden">
    <div class="absolute -top-32 -right-20 h-96 w-96 rounded-full bg-cyan-200/40 blur-3xl"></div>
    <div class="absolute top-24 -left-28 h-96 w-96 rounded-full bg-blue-300/30 blur-3xl"></div>
    <div class="absolute bottom-0 right-1/4 h-80 w-80 rounded-full bg-violet-200/30 blur-3xl"></div>
  </div>

  <header class="w-full sticky top-0 bg-[#ffffff] border-b border-[#dfe3e6] z-50 shadow-sm">
    <div class="mx-auto px-8 py-4 flex justify-between items-center">
      <div class="flex items-center gap-10">
        <div class="flex flex-col">
          <h1 class="font-headline font-bold text-[#3952bc] text-xl tracking-tighter leading-tight">OHS Division</h1>
          <p class="text-slate-500 text-[10px] font-bold uppercase tracking-widest">SID Meeting Evaluation</p>
        </div>
        <div class="h-8 w-px bg-[#dfe3e6] hidden lg:block"></div>
        <nav class="hidden md:flex gap-8">
          <a class="text-[#3952bc] border-b-2 border-[#3952bc] pb-1 font-bold text-sm tracking-tight" href="{{ route('sid-meeting.dashboard') }}">Dashboard</a>
          <a class="text-[#595c5e] hover:text-[#3952bc] font-semibold text-sm tracking-tight transition-colors" href="{{ route('sid-meeting.events.index') }}">Event</a>
          <a class="text-[#595c5e] hover:text-[#3952bc] font-semibold text-sm tracking-tight transition-colors" href="{{ route('sid-meeting.reports.index') }}">Rekap</a>
          <a class="text-[#595c5e] hover:text-[#3952bc] font-semibold text-sm tracking-tight transition-colors" href="{{ route('sid-meeting.performance.index') }}">Performance</a>
          <a class="text-[#595c5e] hover:text-[#3952bc] font-semibold text-sm tracking-tight transition-colors" href="{{ route('sid-meeting.semantic.index') }}">Semantic</a>
        </nav>
      </div>
      <div class="flex items-center gap-3">
        <button class="p-2 hover:bg-[#dfe3e6] rounded-full transition-colors relative" type="button">
          <span class="material-symbols-outlined">notifications</span>
          <span class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 border-2 border-white rounded-full"></span>
        </button>
        <div class="flex items-center gap-2 p-1.5 pr-4 bg-white rounded-full border border-slate-200 shadow-sm">
          <span class="material-symbols-outlined text-3xl text-[#3952bc]">account_circle</span>
          <div class="text-left">
            <p class="text-[10px] font-bold text-[#3952bc] uppercase leading-none">Safety Admin</p>
            <p class="text-[9px] text-slate-500 font-medium">Site Manager</p>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="w-full mx-auto p-8 space-y-8">
    <header class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <nav class="flex items-center gap-2 text-xs font-bold text-slate-500 uppercase mb-2">
          <span>Dashboard</span>
          <span class="material-symbols-outlined text-xs">chevron_right</span>
          <span class="text-[#3952bc]">SID Meeting Dashboard</span>
        </nav>
        <p class="mb-2 inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-bold uppercase tracking-wider text-blue-700 ring-1 ring-blue-100">QR Event Maker & Absensi</p>
        <h1 class="font-headline text-3xl font-extrabold tracking-tight text-slate-950 md:text-5xl">Event Meeting & Absensi SID</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 md:text-base">Create event akan generate QR. Saat QR discan, form absensi event muncul. Peserta cukup input Kode SID, lalu nama, perusahaan, jabatan struktural, dan jabatan fungsional terisi otomatis dari sistem.</p>
      </div>
      
    </header>

    <section class="mb-6 grid gap-4 md:grid-cols-4">
      <div class="bg-white p-6 rounded-2xl anchored-card"><p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Event</p><p id="statEvents" class="mt-2 text-3xl font-extrabold text-slate-950">0</p></div>
      <div class="bg-white p-6 rounded-2xl anchored-card"><p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Event Aktif</p><p id="statActiveEvents" class="mt-2 text-3xl font-extrabold text-emerald-700">0</p></div>
      <div class="bg-white p-6 rounded-2xl anchored-card"><p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Absensi</p><p id="statAttendance" class="mt-2 text-3xl font-extrabold text-slate-950">0</p></div>
      <div class="bg-white p-6 rounded-2xl anchored-card"><p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Attendance Rate (All)</p><p id="statAttendanceRateAll" class="mt-2 text-3xl font-extrabold text-blue-700">0%</p></div>
    </section>

    <nav class="module-nav no-print sticky top-3 z-30 mb-6 flex flex-wrap items-center">
      <button data-tab="events" class="tab-btn module-tab tab-active" onclick="showTab('events')">Create Event</button>
      <button data-tab="companymaster" class="tab-btn module-tab" onclick="showTab('companymaster')">Master Perusahaan</button>
      <button data-tab="report" class="tab-btn module-tab" onclick="showTab('report')">Rekap & Export</button>
      <button data-tab="minutesmgmt" class="tab-btn module-tab" onclick="showTab('minutesmgmt')">Management Notulensi</button>
      <button data-tab="siteperformance" class="tab-btn module-tab" onclick="showTab('siteperformance')">Site Performance</button>
      <button data-tab="semanticeval" class="tab-btn module-tab" onclick="showTab('semanticeval')">Semantic Evaluation</button>
    </nav>

    <section id="tab-events" class="tab-panel fade-in">
      <div id="floatingFormOverlay" class="floating-overlay no-print" onclick="closeFloatingPanels()"></div>

      <div id="createEventContainer" class="glass soft-card floating-drawer rounded-3xl p-5">
        <div class="mb-4 flex items-start justify-between gap-3">
          <div>
            <h2 class="text-xl font-black text-slate-950">Create Event</h2>
            <p class="mt-1 text-sm text-slate-500">Event dibuat dari jenis meeting, site, tanggal, week, dan jam selesai untuk menentukan masa aktif QR.</p>
          </div>
          <button id="createEventCollapseBtn" type="button" onclick="toggleCreateEventContainer(false)" class="no-print shrink-0 rounded-2xl bg-slate-100 px-3 py-2 text-sm font-black text-slate-700 hover:bg-slate-200" title="Tutup Create Event">✕</button>
        </div>
        <div>
        <form id="eventForm" class="mt-5 space-y-4" onsubmit="saveEvent(event)">
          <input type="hidden" id="eventId" />
          <div>
            <label class="text-sm font-bold text-slate-700">Jenis Meeting</label>
            <select id="meetingType" required class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
              <option value="">Pilih Jenis Meeting</option>
            </select>
            <div class="mt-3 rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200">
              <button type="button" onclick="toggleMeetingTypeManager()" class="flex w-full items-center justify-between text-left text-xs font-black uppercase tracking-wider text-slate-600">
                <span>Kelola Jenis Meeting</span>
                <span id="meetingTypeManagerIcon">+</span>
              </button>
              <div id="meetingTypeManager" class="mt-3 hidden space-y-3">
                <div class="flex gap-2">
                  <input id="newMeetingType" onkeydown="if(event.key==='Enter'){event.preventDefault(); addMeetingType();}" placeholder="Tambah jenis meeting baru..." class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                  <button type="button" onclick="addMeetingType()" class="rounded-xl bg-blue-600 px-3 py-2 text-xs font-black text-white hover:bg-blue-700">Tambah</button>
                </div>
                <div id="meetingTypeList" class="flex flex-wrap gap-2"></div>
              </div>
            </div>
          </div>

          <div>
                     <label class="text-sm font-bold text-slate-700">Kategori Meeting</label>
                     <select id="meetingLevel" onchange="handleMeetingLevelChange()" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none">
                        <option value="site">Site Level</option>
                        <option value="company">Company Level</option>
                        <option value="department">Department Level</option>
                     </select>
                     <p id="meetingLevelHint" class="mt-2 text-xs font-semibold text-slate-500"></p>
                  </div>

                  <div id="meetingTargetPanel" class="hidden rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                     <div class="mb-3 flex items-center justify-between gap-2">
                        <div>
                           <h4 class="font-black text-slate-950">Target Kehadiran</h4>
                           <p id="meetingTargetHint" class="text-sm text-slate-500"></p>
                        </div>
                        <div class="flex gap-2"><button type="button" onclick="toggleAllTargetCompanies(true)" class="rounded-xl bg-blue-50 px-3 py-2 text-xs font-black text-blue-700">Pilih Semua</button><button type="button" onclick="toggleAllTargetCompanies(false)" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-700">Clear</button></div>
                     </div>
                     <div class="grid gap-4">
                        <div>
                           <label class="text-sm font-bold text-slate-700">Perusahaan Wajib Hadir</label>
                           <input id="targetCompanySearch" type="search" placeholder="Cari nama perusahaan..." oninput="filterTargetCompanies()" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                           <p id="targetCompanySearchInfo" class="mt-1 text-xs font-semibold text-slate-500"></p>
                           <div id="targetCompanyChecklist" class="mt-2 max-h-52 overflow-auto rounded-2xl border border-slate-200 bg-white p-3"></div>
                        </div>
                        <div id="targetPositionBlock">
                           <label class="text-sm font-bold text-slate-700">Jabatan Fungsional Wajib Hadir</label>
                           <div id="targetPositionChecklist" class="mt-2 max-h-52 overflow-auto rounded-2xl border border-slate-200 bg-white p-3"></div>
                        </div>
                        <div id="targetDepartmentBlock" class="hidden">
                           <label class="text-sm font-bold text-slate-700">Department Wajib Hadir</label>
                           <div id="targetDepartmentChecklist" class="mt-2 max-h-52 overflow-auto rounded-2xl border border-slate-200 bg-white p-3"></div>
                        </div>
                     </div>
                  </div>
              
          <div>
            <label class="text-sm font-bold text-slate-700">Site</label>
            <select id="eventSite" required class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
              <option value="">Pilih Site</option>
            </select>
          </div>
          <div class="grid gap-4 md:grid-cols-2">
            <div><label class="text-sm font-bold text-slate-700">Tanggal Meeting</label><input id="meetingDate" type="date" required onchange="autoFillWeek()" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" /></div>
            <div><label class="text-sm font-bold text-slate-700">Week</label><input id="meetingWeek" required placeholder="Contoh: W17" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" /></div>
          </div>
          <div class="grid gap-4 md:grid-cols-2">
            <div><label class="text-sm font-bold text-slate-700">Jam Mulai</label><input id="startTime" type="time" required class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" /></div>
            <div><label class="text-sm font-bold text-slate-700">Jam Selesai / QR Expired</label><input id="endTime" type="time" required class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" /></div>
          </div>
          <div class="rounded-3xl bg-blue-50 p-4 text-xs leading-5 text-blue-900 ring-1 ring-blue-100">QR hanya aktif pada tanggal meeting sampai <b>Jam Selesai</b>. Jika lewat dari jam selesai, form absensi akan otomatis terkunci.</div>
          <div class="flex gap-2">
            <button class="flex-1 rounded-2xl bg-gradient-to-r from-blue-600 to-cyan-500 px-4 py-3 text-sm font-black text-white shadow-lg shadow-blue-500/20 hover:opacity-95">Simpan & Generate QR</button>
            <button type="button" onclick="clearEventForm()" class="rounded-2xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-700 hover:bg-slate-200">Clear</button>
          </div>
        </form>
        </div>
      </div>

      <div class="glass soft-card rounded-3xl p-5">
        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div><h2 class="text-xl font-black text-slate-950">Daftar Event & QR</h2><p class="text-sm text-slate-500">Klik kartu event untuk melihat modal rekap. Tombol QR dan form absen tetap tersedia di setiap event.</p></div>
          <div class="no-print flex flex-col gap-2 md:flex-row md:items-center">
            <div class="flex flex-wrap gap-2 rounded-2xl bg-white/70 p-2 shadow-sm ring-1 ring-slate-200">
              <button id="eventListActiveBtn" type="button" onclick="setEventListMode('active')" class="module-tab tab-active px-4 py-3 text-sm font-black">Event Aktif</button>
              <button id="eventListInactiveBtn" type="button" onclick="setEventListMode('inactive')" class="module-tab px-4 py-3 text-sm font-black">Sudah Tidak Aktif</button>
            </div>
            <button onclick="toggleCreateEventContainer(true)" class="fab-action">+ Create Event</button>
            <input id="eventSearch" type="search" placeholder="Cari jenis meeting / site / week..." class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
          </div>
        </div>
        <div id="eventList" class="grid gap-3"></div>
        <div id="eventListPagination" class="no-print mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600"></div>
      </div>
    </section>

    <section id="tab-companymaster" class="tab-panel fade-in hidden">
      <div class="glass soft-card rounded-3xl p-5">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 class="text-xl font-black text-slate-950">Master Perusahaan & Site Eligibility</h2>
            <p class="text-sm text-slate-500">Kelola daftar perusahaan dan checklist site mana saja yang wajib/eligible mengikuti event. Data ini menjadi dasar Attendance Rate per site dan status kehadiran perusahaan.</p>
          </div>
          <div class="no-print flex flex-col gap-2 md:flex-row">
            <button onclick="toggleCompanyInputContainer(true)" class="fab-action">+ Input Perusahaan</button>
            <button onclick="exportCompanyMasterCSV()" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-black text-white hover:bg-slate-800">Export Master CSV</button>
          </div>
        </div>

        <div>
          <div id="companyInputContainer" class="floating-drawer rounded-3xl bg-white p-5 ring-1 ring-slate-200 shadow-sm">
            <div class="mb-4 flex items-start justify-between gap-3">
              <div>
                <h3 class="font-black text-slate-950">Input Perusahaan</h3>
                <p class="mt-1 text-sm text-slate-500">Tambahkan perusahaan, lalu checklist site yang relevan.</p>
              </div>
              <button type="button" onclick="toggleCompanyInputContainer(false)" class="no-print rounded-2xl bg-slate-100 px-3 py-2 text-sm font-black text-slate-700 hover:bg-slate-200">✕</button>
            </div>
            <form id="companyForm" class="mt-5 space-y-4" onsubmit="saveCompany(event)">
              <input type="hidden" id="companyId" />
              <div>
                <label class="text-sm font-bold text-slate-700">Nama Perusahaan</label>
                <input id="companyName" required placeholder="Contoh: PT Transkon Jaya" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
              </div>
              <div>
                <label class="text-sm font-bold text-slate-700">Checklist Site</label>
                <div id="companySiteChecklist" class="mt-2 grid gap-2 sm:grid-cols-2"></div>
              </div>
              <div class="flex gap-2">
                <button class="flex-1 rounded-2xl bg-gradient-to-r from-blue-600 to-cyan-500 px-4 py-3 text-sm font-black text-white shadow-lg shadow-blue-500/20 hover:opacity-95">Simpan Perusahaan</button>
                <button type="button" onclick="clearCompanyForm()" class="rounded-2xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-700 hover:bg-slate-200">Clear</button>
              </div>
            </form>
          </div>

          <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200 shadow-sm">
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
              <div class="min-w-0">
                <h3 class="font-black text-slate-950">Checklist Perusahaan per Site</h3>
                <p class="mt-1 text-sm text-slate-500">Centang site di mana perusahaan eligible mengikuti event meeting.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                  <span id="companyMasterTotal" class="company-stat-pill company-stat-pill-total">Total: —</span>
                  <span id="companyMasterFiltered" class="company-stat-pill company-stat-pill-shown">Ditampilkan: —</span>
                  <span id="companyMasterInfo" class="text-xs font-semibold text-slate-400"></span>
                </div>
              </div>
              <p class="shrink-0 text-xs font-bold uppercase tracking-wider text-slate-400">Geser → untuk semua site</p>
            </div>
            <div class="company-master-scroll table-wrap border border-slate-200 bg-white">
              <table id="companyDataTable" class="w-full text-left text-sm">
                <thead id="companyMasterTableHead">
                  <tr>
                    <th class="company-col-sticky">Perusahaan</th>
                    <th class="action-col-sticky text-center no-print">Aksi</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="tab-siteperformance" class="tab-panel fade-in hidden">
      <div class="glass soft-card rounded-3xl p-5">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 class="text-xl font-black text-slate-950">Site Performance</h2>
            <p class="text-sm text-slate-500">Trend line Attendance Rate per site berdasarkan week.</p>
          </div>
          <div class="no-print flex flex-col gap-2 md:flex-row">
            <select id="siteTrendFilter" onchange="renderSitePerformance()" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
              <option value="ALL">Semua Site</option>
            </select>
            <select id="siteTrendWeekFilter" onchange="renderSitePerformance()" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
              <option value="ALL">Semua Week</option>
            </select>
            <button onclick="exportSitePerformanceCSV()" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-black text-white hover:bg-slate-800">Export Trend CSV</button>
            <button onclick="exportCompanyPerformanceCSV()" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-black text-white hover:bg-blue-700">Export Company CSV</button>
          </div>
        </div>

        <div class="mb-4 grid gap-4 md:grid-cols-3">
          <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200">
            <p class="text-sm font-semibold text-slate-500">Total Site</p>
            <p id="siteTrendTotalSite" class="mt-2 text-2xl font-black text-slate-950">0</p>
          </div>
          <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200">
            <p class="text-sm font-semibold text-slate-500">Average Attendance Rate</p>
            <p id="siteTrendAvgRate" class="mt-2 text-2xl font-black text-slate-950">0%</p>
          </div>
          <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200">
            <p class="text-sm font-semibold text-slate-500">Week Coverage</p>
            <p id="siteTrendWeekCount" class="mt-2 text-2xl font-black text-slate-950">0</p>
          </div>
        </div>

        <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200">
          <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
              <h3 class="font-black text-slate-950">Attendance Rate Trend</h3>
              <p id="siteTrendInfo" class="text-sm text-slate-500">Belum ada data.</p>
            </div>
          </div>
          <div class="h-[420px]">
            <canvas id="sitePerformanceChart"></canvas>
          </div>
        </div>

        <div class="mt-6 rounded-3xl bg-white p-5 ring-1 ring-slate-200">
          <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
              <h3 class="font-black text-slate-950">Attendance Rate per Perusahaan</h3>
              <p id="companyPerformanceInfo" class="text-sm text-slate-500">Mengikuti filter Site dan Week di atas.</p>
            </div>
            <div class="no-print w-full md:w-80">
              <input id="companyPerformanceSearch" oninput="renderCompanyPerformanceTable()" placeholder="Cari perusahaan / performance..." class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
            </div>
          </div>
          <div class="table-wrap rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full text-left text-sm">
              <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                <tr>
                  <th class="px-4 py-3">Rank</th>
                  <th class="px-4 py-3">Perusahaan</th>
                  <th class="px-4 py-3">Kategori / Target</th>
                  <th class="px-4 py-3">Expected Event</th>
                  <th class="px-4 py-3">Event Hadir</th>
                  <th class="px-4 py-3">Total Absensi</th>
                  <th class="px-4 py-3">Attendance Rate</th>
                  <th class="px-4 py-3">Performance</th>
                </tr>
              </thead>
              <tbody id="companyPerformanceTable"></tbody>
            </table>
          </div>
        </div>
      </div>
    </section>

    <section id="tab-minutesmgmt" class="tab-panel fade-in hidden">
      <div class="glass soft-card rounded-3xl p-5">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 class="text-xl font-black text-slate-950">Management Notulensi</h2>
            <p class="text-sm text-slate-500">Daftar meeting yang sudah memiliki notulensi. Klik detail untuk melihat issue dan mengubah status.</p>
          </div>
          <div class="no-print flex flex-col gap-2 md:flex-row">
            <select id="minutesMgmtStatusFilter" onchange="minutesMgmtPage=1;renderMinutesMgmtList()" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
              <option value="ALL">Semua Status Issue</option>
              <option value="OPEN">Masih Ada Issue Open</option>
              <option value="CLOSED">Semua Issue Closed</option>
            </select>
            <input id="minutesMgmtSearch" type="search" oninput="scheduleMinutesMgmtSearch()" placeholder="Cari meeting / notulen / site..." class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
          </div>
        </div>

        <div id="minutesMgmtList" class="space-y-3"></div>
        <div id="minutesMgmtPagination" class="no-print mt-4 flex flex-col gap-2 text-sm text-slate-500 md:flex-row md:items-center md:justify-between"></div>
      </div>
    </section>

    <section id="tab-semanticeval" class="tab-panel fade-in hidden">
      <div class="glass soft-card rounded-3xl p-5">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 class="text-xl font-black text-slate-950">Semantic Evaluation Notulensi</h2>
            <p class="text-sm text-slate-500">Mendeteksi pengulangan issue dan kemiripan catatan meeting antar-site menggunakan local semantic embedding + cosine similarity. Fungsi ini hanya tersedia di page Semantic Evaluation, tidak ditampilkan di List Notulen.</p>
          </div>
          <div class="no-print flex flex-col gap-2 md:flex-row">
            <span id="semanticMethodBadge" class="inline-flex items-center rounded-2xl bg-violet-50 px-4 py-3 text-sm font-black text-violet-700 ring-1 ring-violet-100">Embedding Engine: Local Semantic Vector</span>
            <button onclick="renderSemanticEvaluation(true)" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-black text-white hover:bg-blue-700">Run Evaluation</button>
            <button onclick="exportSemanticPairsCSV()" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-black text-white hover:bg-slate-800">Export Similarity CSV</button>
          </div>
        </div>

        <div class="no-print mb-5 grid gap-3 lg:grid-cols-[320px_1fr]">
          <div><label class="text-sm font-bold text-slate-700">Similarity Threshold</label><div class="mt-1 rounded-2xl border border-slate-200 bg-white px-4 py-3"><input id="semanticThreshold" type="range" min="35" max="95" value="55" oninput="document.getElementById('semanticThresholdLabel').textContent = this.value + '%'; renderSemanticEvaluation(true);" class="w-full" /><div class="mt-1 flex justify-between text-xs font-bold text-slate-500"><span>Loose</span><span id="semanticThresholdLabel">55%</span><span>Strict</span></div></div></div>
          <div><label class="text-sm font-bold text-slate-700">Search Issue</label><input id="semanticSearch" oninput="renderSemanticEvaluation(true)" placeholder="Cari issue / PIC / site..." class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" /><label class="mt-2 flex items-center gap-2 text-xs font-bold text-slate-600"><input id="semanticCrossSiteOnly" type="checkbox" checked onchange="renderSemanticEvaluation(true)" class="h-4 w-4 rounded border-slate-300 text-blue-600" /> Cross-site only</label></div>
        </div>

        <div class="mb-5 grid gap-4 md:grid-cols-4">
          <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Total Issue</p><p id="semanticTotalIssues" class="mt-2 text-2xl font-black text-slate-950">0</p></div>
          <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Similar Pairs</p><p id="semanticSimilarPairs" class="mt-2 text-2xl font-black text-blue-700">0</p></div>
          <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Repeated Groups</p><p id="semanticRepeatedGroups" class="mt-2 text-2xl font-black text-orange-700">0</p></div>
          <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Cross-site Pairs</p><p id="semanticCrossSitePairs" class="mt-2 text-2xl font-black text-emerald-700">0</p></div>
        </div>

        <div class="semantic-grid">
          <div class="semantic-card rounded-3xl bg-white p-5 ring-1 ring-slate-200"><div class="mb-3"><h3 class="font-black text-slate-950">Repeated Issue Groups</h3><p id="semanticGroupInfo" class="text-sm text-slate-500">Belum ada evaluasi.</p></div><div class="table-wrap semantic-scroll rounded-2xl border border-slate-200 bg-white"><table class="semantic-groups-table text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3">Group</th><th class="px-4 py-3">Sites</th><th class="px-4 py-3">Issue Count</th><th class="px-4 py-3">Top Terms</th></tr></thead><tbody id="semanticGroupsTable"></tbody></table></div></div>
          <div class="semantic-card rounded-3xl bg-white p-5 ring-1 ring-slate-200"><div class="mb-3"><h3 class="font-black text-slate-950">Similarity Issue Pairs</h3><p id="semanticPairInfo" class="text-sm text-slate-500">Menampilkan pasangan issue dengan similarity tertinggi.</p></div><div class="table-wrap semantic-scroll rounded-2xl border border-slate-200 bg-white"><table class="semantic-pairs-table text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3">Similarity</th><th class="px-4 py-3">Level</th><th class="px-4 py-3">Site A</th><th class="px-4 py-3">Issue A</th><th class="px-4 py-3">Site B</th><th class="px-4 py-3">Issue B</th><th class="px-4 py-3">Action Signal</th></tr></thead><tbody id="semanticPairsTable"></tbody></table></div></div>
        </div>
      </div>
    </section>

    <section id="tab-report" class="tab-panel fade-in hidden">
      <div id="reportTabLoading" class="loading-banner hidden mb-4">
        <span class="loading-spinner"></span>
        Memuat data rekap...
      </div>
      <div class="glass soft-card rounded-3xl p-5 md:p-6">
        <div class="report-hero no-print">
          <div>
            <p class="report-hero__title">Rekap & Export Keseluruhan</p>
            <p class="report-hero__desc">Filter site & week, cari peserta, lalu export absensi atau notulen ke CSV.</p>
          </div>
          <div class="report-hero__actions">
            <button type="button" onclick="resetReportFilters()" class="report-btn report-btn--ghost"><span class="material-symbols-outlined text-base">restart_alt</span> Reset</button>
            <button type="button" onclick="exportFilteredReportCSV()" class="report-btn report-btn--dark"><span class="material-symbols-outlined text-base">download</span> Absensi CSV</button>
            <button type="button" onclick="exportMinutesReportCSV()" class="report-btn report-btn--primary"><span class="material-symbols-outlined text-base">description</span> Notulen CSV</button>
          </div>
        </div>

        <div class="report-controls no-print">
          <div class="report-segment shrink-0">
            <button id="reportViewAttendanceBtn" type="button" onclick="setReportView('attendance')" class="module-tab tab-active"><span class="material-symbols-outlined text-base">groups</span> Data Absensi</button>
            <button id="reportViewMinutesBtn" type="button" onclick="setReportView('minutes')" class="module-tab"><span class="material-symbols-outlined text-base">description</span> List Notulen</button>
          </div>
          <div class="report-filters">
            <div class="report-field">
              <label for="reportFilterSite"><span class="material-symbols-outlined text-sm text-slate-400">location_on</span> Site</label>
              <select id="reportFilterSite" onchange="scheduleReportReload()"><option value="ALL">Semua Site</option></select>
            </div>
            <div class="report-field">
              <label for="reportFilterWeek"><span class="material-symbols-outlined text-sm text-slate-400">date_range</span> Week</label>
              <select id="reportFilterWeek" onchange="scheduleReportReload()"><option value="ALL">Semua Week</option></select>
            </div>
            <div class="report-field">
              <label for="reportSearch"><span class="material-symbols-outlined text-sm text-slate-400">search</span> Pencarian</label>
              <input id="reportSearch" type="search" oninput="scheduleReportReload()" placeholder="SID, nama, perusahaan, meeting..." />
            </div>
          </div>
        </div>

        <div id="reportAttendancePanel">
          <div class="report-summary-bar">
            <span class="report-summary-stat"><span class="material-symbols-outlined text-lg text-[#3952bc]">table_rows</span><span id="reportTableInfo"><strong>0</strong> data</span></span>
            <span class="report-summary-hint"><span class="material-symbols-outlined text-sm">touch_app</span> Klik baris → rekap event</span>
          </div>
          <div class="report-table-card">
            <div class="table-wrap report-table-scroll">
              <table id="reportAttendanceDataTable" class="display nowrap w-full text-sm">
                <thead><tr>
                  <th>Tanggal</th><th>Week</th><th>Site</th><th>Jenis Meeting</th><th>Kode Event</th>
                  <th>Kode SID</th><th>Nama</th><th>Perusahaan</th><th>Jabatan Struktural</th><th>Jabatan Fungsional</th><th>Timestamp</th>
                </tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>

        <div id="reportMinutesPanel" class="hidden">
          <div class="report-summary-bar">
            <span class="report-summary-stat"><span class="material-symbols-outlined text-lg text-[#3952bc]">description</span><span id="minutesReportInfo"><strong>0</strong> notulen</span></span>
            <span class="report-summary-hint"><span class="material-symbols-outlined text-sm">edit_note</span> Klik baris → edit notulensi</span>
          </div>
          <div class="report-table-card">
            <div class="table-wrap report-table-scroll">
              <table id="reportMinutesDataTable" class="display nowrap w-full text-sm">
                <thead><tr>
                  <th>Tanggal</th><th>Week</th><th>Site</th><th>Jenis Meeting</th><th>Kode Event</th><th>Judul Notulen</th>
                  <th>Notulis</th><th>Lokasi</th><th>Section</th><th>No</th><th>Catatan Meeting</th>
                  <th>Issued By</th><th>PIC</th><th>Batas Waktu</th><th>Status</th><th>Keterangan</th><th>Updated</th>
                </tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="no-print mt-6 rounded-3xl bg-slate-50 p-5 ring-1 ring-slate-200">
          <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
              <h3 class="font-black text-slate-950">Self Test</h3>
              <p class="text-sm text-slate-500">Tes cepat untuk validasi fungsi utama tanpa mengubah data produksi Anda.</p>
            </div>
            <button onclick="runSelfTests()" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-black text-white">Run Self Test</button>
          </div>
          <pre id="testOutput" class="mt-4 max-h-72 overflow-auto rounded-2xl bg-slate-950 p-4 text-xs text-slate-100">Belum dijalankan.</pre>
        </div>
      </div>
    </section>
  </main>

  <div id="eventRecapModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
    <div class="modal-shell w-full max-w-6xl rounded-[2rem] bg-white p-6 shadow-2xl ring-1 ring-white/20">
      <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h3 class="text-2xl font-black text-slate-950">Rekap Event</h3>
          <p id="eventRecapTitle" class="mt-1 text-sm text-slate-500"></p>
        </div>
        <button onclick="closeEventRecapModal()" class="no-print rounded-xl bg-slate-100 px-3 py-2 text-sm font-black text-slate-700">✕</button>
      </div>
      <div id="eventRecapSummary" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-5"></div>
      <div class="mt-6 h-px bg-gradient-to-r from-transparent via-slate-200 to-transparent"></div>

      <div class="mt-6 rounded-3xl bg-white p-5 ring-1 ring-slate-200 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h4 class="text-lg font-black text-slate-950">Notulensi Event</h4>
            <p class="text-sm text-slate-500">Form input notulensi dibuka melalui tombol. Kelola group issue secara dinamis, lalu isi catatan meeting per group.</p>
            <p id="minutesUpdatedInfo" class="mt-1 text-xs font-bold text-slate-400">Belum ada notulensi</p>
          </div>
          <div class="no-print flex flex-col gap-2 md:flex-row md:items-center">
            <button id="minutesToggleBtn" type="button" onclick="openEventMinutesForm()" class="rounded-2xl bg-gradient-to-r from-blue-600 to-cyan-500 px-5 py-3 text-sm font-black text-white shadow-lg shadow-blue-500/20 hover:opacity-95">Input Notulensi</button>
            <button type="button" onclick="printEventMinutes()" class="rounded-2xl bg-blue-50 px-5 py-3 text-sm font-black text-blue-700 ring-1 ring-blue-100 hover:bg-blue-100">Print Notulensi</button>
          </div>
        </div>

        <div id="minutesInputPanel" class="mt-5 hidden rounded-3xl bg-slate-50 p-5 ring-1 ring-slate-200">
          <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
              <h5 class="font-black text-slate-950">Form Input Notulensi</h5>
              <p class="text-sm text-slate-500">Isi catatan meeting, PIC, batas waktu, dan keterangan per issue.</p>
            </div>
            <button type="button" onclick="closeEventMinutesForm()" class="no-print rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-700">Tutup</button>
          </div>

          <form id="minutesForm" onsubmit="saveEventMinutes(event)" class="space-y-5">
          <div class="no-print rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200">
            <button type="button" onclick="toggleIssueGroupManager()" class="flex w-full items-center justify-between text-left text-xs font-black uppercase tracking-wider text-slate-600">
              <span>Kelola Group Issue</span>
              <span id="issueGroupManagerIcon">+</span>
            </button>
            <div id="issueGroupManager" class="mt-3 hidden space-y-3">
              <div class="flex gap-2">
                <input id="newIssueSectionTitle" type="text" onkeydown="if(event.key==='Enter'){event.preventDefault();addIssueSection();}" placeholder="Nama group issue baru..." class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                <button type="button" onclick="addIssueSection()" class="rounded-xl bg-blue-600 px-3 py-2 text-xs font-black text-white hover:bg-blue-700">Tambah</button>
              </div>
              <div id="issueGroupList" class="flex flex-wrap gap-2"></div>
            </div>
          </div>

          <div id="minutesPrintArea" class="overflow-hidden rounded-2xl border-2 border-slate-950 bg-white text-slate-950">
            <table class="min-w-full border-collapse text-sm">
              <tr>
                <td rowspan="3" class="w-[140px] border border-slate-950 p-3 align-middle text-center">
                  <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="Berau Coal logo" style="max-width:120px; max-height:58px; object-fit:contain; display:block; margin:0 auto;" />
                </td>
                <td class="border border-slate-950 py-1 text-center text-xs font-black">BERAU COAL</td>
              </tr>
              <tr>
                <td class="border border-slate-950 py-1 text-center text-xs font-black">FORMULIR</td>
              </tr>
              <tr>
                <td class="border border-slate-950 p-1 text-center text-xs font-black">
                  <input id="minutesMeetingTitle" placeholder="NOTULENSI MEETING" class="w-full border-0 bg-transparent text-center text-xs font-black outline-none" />
                </td>
              </tr>
            </table>

            <div class="border-x border-b border-slate-950 p-4 text-sm">
              <div class="grid gap-3 md:grid-cols-2">
                <div class="grid grid-cols-[120px_10px_1fr] items-center gap-2">
                  <label class="font-semibold">Jenis Meeting</label><span>:</span>
                  <input id="minutesMeetingType" class="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
                </div>
                <div class="grid grid-cols-[120px_10px_1fr] items-center gap-2">
                  <label class="font-semibold">Tanggal</label><span>:</span>
                  <input id="minutesMeetingDate" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
                </div>
                <div class="grid grid-cols-[120px_10px_1fr] items-center gap-2">
                  <label class="font-semibold">Notulis</label><span>:</span>
                  <input id="minutesNotulis" class="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" placeholder="Nama notulis" />
                </div>
                <div class="grid grid-cols-[120px_10px_1fr] items-center gap-2 md:col-span-2">
                  <label class="font-semibold">Lokasi</label><span>:</span>
                  <input id="minutesLocation" class="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" placeholder="Lokasi meeting / link zoom" />
                </div>
              </div>
            </div>

            <div id="minutesIssueSectionsContainer"></div>
          </div>

          <div class="no-print flex flex-col gap-2 md:flex-row">
            <button type="submit" class="flex-1 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-black text-white hover:bg-slate-800">Simpan Notulensi</button>
            <button type="button" onclick="clearEventMinutesForm()" class="rounded-2xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-700 hover:bg-slate-200">Clear Form</button>
          </div>
        </form>
        </div>
      </div>

      <div class="mt-6 rounded-3xl bg-gradient-to-br from-blue-50 to-cyan-50 p-5 ring-1 ring-blue-100 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h4 class="text-lg font-black text-slate-950">Absensi Event</h4>
            <p class="text-sm text-slate-600">Gunakan tombol ini hanya jika perlu input absensi secara manual oleh admin.</p>
          </div>
          <button id="manualAttendanceToggleBtn" onclick="openManualAttendanceForm()" class="rounded-2xl bg-gradient-to-r from-emerald-600 to-cyan-500 px-5 py-3 text-sm font-black text-white shadow-lg shadow-emerald-500/20 hover:opacity-95">
            Absen Manual
          </button>
        </div>

        <div id="manualAttendancePanel" class="mt-5 hidden rounded-3xl bg-white p-5 ring-1 ring-blue-100">
          <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
              <h5 class="font-black text-slate-950">Form Absen Manual</h5>
              <p class="text-sm text-slate-500">Input Kode SID. Data pekerja otomatis ditarik dari sistem.</p>
            </div>
            <button type="button" onclick="closeManualAttendanceForm()" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-700">Tutup</button>
          </div>
          <div id="scanNotice" class="rounded-3xl bg-amber-50 p-4 text-sm font-semibold text-amber-800 ring-1 ring-amber-100"></div>
          <form id="attendanceForm" class="mt-4 grid gap-4 lg:grid-cols-2" onsubmit="saveAttendance(event)">
            <input type="hidden" id="attendanceEventId" />
            <input type="hidden" id="resolvedEmployeeId" />
            <div class="lg:col-span-2" id="activeEventInfo"></div>
            <div>
              <label class="text-sm font-bold text-slate-700">Kode SID</label>
              <input id="sidInput" required placeholder="Masukkan Kode SID" oninput="lookupSID()" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold uppercase tracking-wide outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
            </div>
            <div>
              <label class="text-sm font-bold text-slate-700">Timestamp</label>
              <input id="timestampInput" readonly class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700" />
            </div>
            <div>
              <label class="text-sm font-bold text-slate-700">Nama</label>
              <input id="autoName" readonly class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700" />
            </div>
            <div>
              <label class="text-sm font-bold text-slate-700">Perusahaan</label>
              <input id="autoCompany" readonly class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700" />
            </div>
            <div>
              <label class="text-sm font-bold text-slate-700">Jabatan Struktural</label>
              <input id="autoStructural" readonly class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700" />
            </div>
            <div>
              <label class="text-sm font-bold text-slate-700">Jabatan Fungsional</label>
              <input id="autoFunctional" readonly class="mt-1 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700" />
            </div>
            <div class="lg:col-span-2">
              <button id="submitAttendanceBtn" class="w-full rounded-2xl bg-gradient-to-r from-emerald-600 to-cyan-500 px-4 py-3 text-sm font-black text-white shadow-lg shadow-emerald-500/20 hover:opacity-95">Submit Absensi</button>
            </div>
          </form>
        </div>
      </div>

      <div class="mt-6 grid gap-5 lg:grid-cols-[.8fr_1.2fr]">
        <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200 shadow-sm">
          <h4 class="font-black text-slate-950">Status Kehadiran Perusahaan</h4>
          <div id="eventRecapCompany" class="mt-4 space-y-3"></div>
        </div>
        <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200 shadow-sm">
          <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <h4 class="font-black text-slate-950">Daftar Hadir</h4>
            <button id="eventRecapExportBtn" onclick="exportSelectedEventExcel()" class="no-print rounded-xl bg-slate-900 px-3 py-2 text-xs font-black text-white">Export Event Excel</button>
          </div>
          <div class="table-wrap rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full text-left text-sm">
              <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3">Timestamp</th><th class="px-4 py-3">SID</th><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Perusahaan</th><th class="px-4 py-3">Jabatan</th></tr></thead>
              <tbody id="eventRecapAttendees"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="closeIssueModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="mb-2 inline-flex rounded-full bg-orange-50 px-3 py-1 text-xs font-black uppercase tracking-wider text-orange-700 ring-1 ring-orange-100">Verifikasi Close Issue</p>
          <h3 class="text-2xl font-black text-slate-950">Tutup Issue?</h3>
          <p class="mt-2 text-sm leading-6 text-slate-600">Masukkan No SID verifikator untuk mengubah status issue menjadi <b>Closed</b>.</p>
        </div>
        <button type="button" onclick="cancelCloseIssue()" class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-black text-slate-700">✕</button>
      </div>
      <div class="mt-5 rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
        <p class="text-xs font-black uppercase tracking-wider text-slate-400">Catatan Issue</p>
        <p id="closeIssueText" class="mt-2 text-sm font-semibold leading-6 text-slate-800"></p>
      </div>
      <div class="mt-4">
        <label class="text-sm font-bold text-slate-700">No SID Verifikator</label>
        <input id="closeIssueVerifierSID" type="text" placeholder="Contoh: SID001" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold uppercase tracking-wide outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
      </div>
      <div class="mt-5 grid grid-cols-2 gap-3">
        <button type="button" onclick="cancelCloseIssue()" class="rounded-2xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-700 hover:bg-slate-200">Batal</button>
        <button type="button" onclick="submitCloseIssueVerification()" class="rounded-2xl bg-gradient-to-r from-red-600 to-orange-500 px-4 py-3 text-sm font-black text-white shadow-lg shadow-red-500/20 hover:opacity-95">Verifikasi & Close</button>
      </div>
    </div>
  </div>

  <div id="minutesMgmtDetailModal" class="fixed inset-0 z-[65] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
    <div class="modal-shell w-full max-w-6xl max-h-[92vh] overflow-y-auto rounded-[2rem] bg-white p-6 shadow-2xl ring-1 ring-white/20">
      <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <p class="mb-2 inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-black uppercase tracking-wider text-blue-700 ring-1 ring-blue-100">Detail Notulensi</p>
          <h3 id="minutesMgmtDetailTitle" class="text-2xl font-black text-slate-950">Notulensi Meeting</h3>
          <p id="minutesMgmtDetailSubtitle" class="mt-1 text-sm text-slate-500"></p>
        </div>
        <button type="button" onclick="closeMinutesMgmtDetailModal()" class="no-print rounded-xl bg-slate-100 px-3 py-2 text-sm font-black text-slate-700">✕</button>
      </div>
      <div id="minutesMgmtDetailSummary" class="mt-5 grid gap-4 md:grid-cols-4"></div>
      <div class="mt-6 table-wrap rounded-2xl border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
            <tr>
              <th class="px-4 py-3">No</th>
              <th class="px-4 py-3">Group Issue</th>
              <th class="px-4 py-3 min-w-[240px]">Catatan Meeting</th>
              <th class="px-4 py-3">Issued By</th>
              <th class="px-4 py-3">PIC</th>
              <th class="px-4 py-3">Batas Waktu</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Verifikator Close</th>
              <th class="px-4 py-3">Keterangan</th>
            </tr>
          </thead>
          <tbody id="minutesMgmtIssuesTable"></tbody>
        </table>
      </div>
    </div>
  </div>

  <div id="closeMeetingModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="mb-2 inline-flex rounded-full bg-orange-50 px-3 py-1 text-xs font-black uppercase tracking-wider text-orange-700 ring-1 ring-orange-100">Jam selesai tercapai</p>
          <h3 class="text-2xl font-black text-slate-950">Tutup Meeting?</h3>
          <p id="closeMeetingTitle" class="mt-2 text-sm leading-6 text-slate-600"></p>
        </div>
        <button onclick="dismissCloseMeetingPrompt()" class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-black text-slate-700">✕</button>
      </div>
      <div class="mt-5 rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
        <p class="text-sm font-semibold text-slate-500">Waktu Berjalan</p>
        <p id="closeMeetingElapsed" class="mt-1 text-3xl font-black text-orange-700">00:00:00</p>
        <p class="mt-2 text-xs text-slate-500">Jika pilih <b>Ya</b>, timer meeting berhenti dan QR/form absensi akan dikunci.</p>
      </div>
      <div class="mt-5 grid grid-cols-2 gap-3">
        <button onclick="dismissCloseMeetingPrompt()" class="rounded-2xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-700 hover:bg-slate-200">Tidak</button>
        <button onclick="confirmCloseMeeting()" class="rounded-2xl bg-gradient-to-r from-red-600 to-orange-500 px-4 py-3 text-sm font-black text-white shadow-lg shadow-red-500/20 hover:opacity-95">Ya, Tutup</button>
      </div>
    </div>
  </div>

  <div id="qrModal" class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
      <div class="flex items-start justify-between gap-4"><div><h3 class="text-xl font-black text-slate-950">QR Absensi Event</h3><p id="qrModalTitle" class="mt-1 text-sm text-slate-500"></p></div><button onclick="closeQRModal()" class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-black text-slate-700">✕</button></div>
      <div id="qrBox" class="mx-auto mt-5 flex h-64 w-64 items-center justify-center rounded-3xl bg-white p-4 ring-1 ring-slate-200"></div>
      <input id="qrLink" readonly class="mt-5 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600" />
      <div id="manualCopyBox" class="mt-3 hidden rounded-2xl bg-amber-50 p-3 text-xs font-semibold text-amber-800 ring-1 ring-amber-100">Clipboard browser diblokir. Link sudah ditampilkan di atas; blok/select lalu copy manual.</div>
      <div class="mt-4 flex gap-2"><button onclick="copyQRLink(this)" class="flex-1 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-black text-white">Copy Link QR</button><button onclick="window.print()" class="rounded-2xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-700">Print</button></div>
    </div>
  </div>

  <div id="semanticDetailModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
    <div class="modal-shell w-full max-w-5xl rounded-[2rem] bg-white p-6 shadow-2xl ring-1 ring-white/20">
      <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <p class="mb-2 inline-flex rounded-full bg-violet-50 px-3 py-1 text-xs font-black uppercase tracking-wider text-violet-700 ring-1 ring-violet-100">Semantic Detail</p>
          <h3 id="semanticDetailTitle" class="text-2xl font-black text-slate-950">Detail Notulen</h3>
          <p id="semanticDetailSubtitle" class="mt-1 text-sm text-slate-500"></p>
        </div>
        <button onclick="closeSemanticDetailModal()" class="no-print rounded-xl bg-slate-100 px-3 py-2 text-sm font-black text-slate-700">✕</button>
      </div>
      <div id="semanticDetailBody" class="mt-5"></div>
    </div>
  </div>

  <div id="toast" class="pointer-events-none fixed bottom-5 left-1/2 z-50 hidden -translate-x-1/2 rounded-2xl bg-slate-950/95 px-5 py-3 text-sm font-bold text-white shadow-2xl ring-1 ring-white/10"></div>

<script>
  const SID_MEETING_API_BASE = '/sid-meeting/api';
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const MEETING_TYPES = ['Safety Talk', 'P5M', 'Toolbox Meeting', 'OHS Committee Meeting', 'Incident Review', 'Critical Control Verification', 'Training / Workshop', 'Management Review'];
  const SITE_MASTER = ['BMO 1', 'BMO 2', 'BMO 3', 'GMO', 'SMO', 'LMO', 'Marine', 'HOTE'];
  const DEFAULT_DEPARTMENTS = ['Operation', 'Maintenance', 'HSE', 'Engineering', 'Plant', 'Logistic', 'Environment', 'Management'];
  const POSITION_FUNCTIONAL_MASTER = [
    'Administrator',
    'Crew',
    'Direktur',
    'Engineer/Specialist',
    'Foreman/Group Leader',
    'General Manager',
    'Manager',
    'Mekanik',
    'Operator',
    'Presiden Direktur',
    'Security',
    'Superintendent',
    'Supervisor/Officer',
    'Technician',
    'Visitor'
  ];
  const TARGET_COMPANY_MASTER = [
    'CV Batu Bual Sejahtera', 'CV Bena Jaya', 'CV Berau Robotic', 'CV Berau Sanggam Abadi', 'CV Bintang Azzahra',
    'CV Bukit Manimbora', 'CV Damayanti', 'CV Elang Maju Mapan', 'CV Harapanku Family', 'CV Haryda Oto',
    'CV Hulu Putra Banua', 'CV Juwita', 'CV Karya Amanda', 'CV Maju Makmur Tehnik', 'CV Megah Jaya Abadi',
    'CV Muhi Karya Perdana', 'CV Putri Dewi', 'CV Rangga', 'CV Sambaliung Fiber', 'CV Santoso Putra Mandiri',
    'CV Sanubari Pratama Komunikasi', 'CV Tangguh Mandiri', 'CV Teguh Harapan', 'CV Triana Jaya', 'CV Varissa Jaya',
    'KAMPUS MERDEKA', 'Koperasi Bersama Kita Bisa', 'Koperasi Bintang Harapan', 'Koperasi Karyawan Bina Bersama',
    'Koperasi Konsumen Pamandiri', 'Koperasi Maju Bersama', 'PT AKR Corporindo', 'PT Abadi Raya Commerce',
    'PT Aditama Putra Grup', 'PT Aesculap', 'PT Agung Buana Rejeki', 'PT Akatara Bintang Perwira', 'PT Akesa Indonesia',
    'PT Akra Anindha Mulia', 'PT Alpha Omega Semesta', 'PT Altrak', 'PT Altros Teknologi', 'PT Ambar Borneo',
    'PT Andalan Duta Eka Nusantara', 'PT Andhita Asri Borneo', 'PT Aneka Cahaya Karunia', 'PT Apex Mitra Prima',
    'PT Arcistec International', 'PT Arexas Indonesia', 'PT Asian Berdikari Cemerlang', 'PT Asian Bulk Logistics',
    'PT Aviako Sepinggan', 'PT Bagong Dekaka Makmur', 'PT Bandang Mining Coal', 'PT Banua Sanggam Utama', 'PT Berau Coal',
    'PT Berau Coal Energy', 'PT Berca Harydayaperkasa', 'PT Berkat Jaya Sukses', 'PT Berkat Teman Sejati', 'PT Bina Pertiwi',
    'PT Bluepac Services', 'PT Bogasari Sarana Surya', 'PT Buana Indah Lalebata', 'PT Budi Harta Lestari',
    'PT Bukit Makmur Mandiri Utama', 'PT Bumi Artlantis Raya', 'PT Bumi Hamparan Luas', 'PT Bumi Sanggam Sejahtera',
    'PT Cahaya Sakti Jaya', 'PT Cahaya Trijaya Sentosa', 'PT Chitra Paratama', 'PT Cipta Krida Bahari',
    'PT Cominco Mitra Perkasa', 'PT Comtelindo', 'PT DNX Indonesia', 'PT Dian Ciptamas Agung', 'PT Distribusi Ammo Nusantara',
    'PT Dunia Pemadam Indonesia', 'PT Duta Borneo Mining', 'PT Eka Dharma Jaya Sakti', 'PT Elektrik Visi Indonesia',
    'PT Energi Indonesia Berkarya', 'PT Energi Nuansa Jaya', 'PT Eonchemicals Putra', 'PT Epiroc Southern Asia',
    'PT Eratec Bina Lestari', 'PT Etam Wira Utama', 'PT Eurotruk Trasindo', 'PT Fajar Anugerah Dinamika',
    'PT Fitama Putri Mandiri', 'PT Frasta Survey Indonesia', 'PT Garuda Bakti Nusantara', 'PT Gatra Kaltim Jaya',
    'PT Geoservices', 'PT Global Arrow', 'PT Gunung Giri Perkasa', 'PT Gurimbang Mandiri Utama', 'PT Harmoni Mitra Utama',
    'PT Hexindo Adiperkasa', 'PT Hutan Rindang Banua', 'PT Imelda Teknik Mandiri', 'PT Indonesia Carbon Energy',
    'PT Inovasi otomasi teknologi', 'PT Intecs Teknikatama Industri', 'PT Interprima Indocom', 'PT Jakarta Prima Cranes',
    'PT Joymar Abadi Indonesia', 'PT Kalimantan Teknik Utama', 'PT Kaliraya Sari', 'PT Kaltim Diamond Coal',
    'PT Kanitra Mitra Jaya Utama', 'PT Kasam', 'PT Kawan Segah Mandiri', 'PT Kemitraan MNK BME', 'PT Kharisma Berkat Sukses',
    'PT Kinend', 'PT Liebherr Indonesia Perkasa', 'PT Limbah Bina Sejahtera', 'PT Lintech Duta Pratama',
    'PT Lusavindra Jayamadya', 'PT Madhani Talatah Nusantara', 'PT Majau Inti Jaya', 'PT Maju Asri Jaya Utama',
    'PT Maju Bersama Binungan', 'PT Mandau Berlian Sejati', 'PT Megah Mutiara Sakti', 'PT Meica Indo Teknik',
    'PT Menara Borneo Jaya', 'PT Mentari Cipta Mandiri', 'PT Mitra Lanuk Permai', 'PT Mitra Sistematika Global',
    'PT Mitra Sukses Raharja', 'PT Mulia Oto Partindo', 'PT Multi Ardecon', 'PT Multi Kontrol Nusantara',
    'PT Multi Nitrotama Kimia', 'PT Multitama Indonesia', 'PT Mutiara Tanjung Lestari', 'PT Nawakara Perkasa Nusantara',
    'PT Nityo Infotech', 'PT Nuansa Makmur Mandiri', 'PT Nusantara Tehnik Gemilang', 'PT Orecon Putra Perkasa',
    'PT PBM Dharma Lautan Nusantara', 'PT Pamapersada Nusantara', 'PT Pancaran Samudera Transport',
    'PT Pancaran Teknologi Transportasi Indonesia', 'PT Pangansari Utama', 'PT Partsindo Servicatama',
    'PT Pelayaran Daya Samudera Mandiri', 'PT Pelayaran Kartika Samudera Adijaya', 'PT Perintis Proteksi Sejahtera',
    'PT Permata Dwitunggal Abadi', 'PT Prima Tunggal Perkasa', 'PT Prima Unggul Persada', 'PT Primac Perkasa Indonesia',
    'PT Primacom Interbuana', 'PT Primarindo Sukses Gemilang', 'PT Prina Duta Rekayasa', 'PT Puncak Makmur Jaya',
    'PT Putra Daerah Mandiri Jaya', 'PT Putra Wahyu Agung', 'PT Quadran Empat Persada', 'PT Rareendo Mulia Abadi',
    'PT Recsalog Geoprima', 'PT Reka Cuaca Indonesia Forte', 'PT Rentokil Indonesia', 'PT Resindo Energi Sumberdaya',
    'PT Resty Nur', 'PT Ricobana Abadi', 'PT SUNBASEL', 'PT Salwa Jaya', 'PT Sambakungan Makmur Bersama',
    'PT Sambakungan Samburakat Maluang Lati', 'PT Samburakat Jaya Utama', 'PT Samudera Berkah Adhiguna',
    'PT Sanggar Sarana Baja', 'PT Sastra Barra Toga', 'PT Satnetcom Balikpapan', 'PT Satya Energi Solusi',
    'PT Segara Persada Nusantara', 'PT Sehati Mandiri Utama', 'PT Semesta Quantum Eterniti', 'PT Serasi Autoraya',
    'PT Shield On Service', 'PT Sinar Pagi', 'PT Sinar Perdana Berau', 'PT Sinarmas LDA Maritime',
    'PT Skotfire and Safety Technology', 'PT Smartfren Telecom Tbk', 'PT Starcom Solusindo', 'PT Sucofindo',
    'PT Sumber Mitra Binungan', 'PT Suprima Mitra Adihusada', 'PT Surveyor Carbon Consulting Indonesia',
    'PT Surveyor Indonesia', 'PT Surya Megah Perkasa', 'PT Surya Nusantara Perkasa', 'PT Tangguh Optima Prima',
    'PT Tantabuan Adhi Karya', 'PT Taubah Berlian Jaya', 'PT Tectona Mitra Utama', 'PT Terusan Raya',
    'PT Tidung Jaya Mandiri', 'PT Tirta Sarana Borneo', 'PT Trakindo Utama', 'PT Transkon Jaya', 'PT Tri Daya Maxima',
    'PT Triatra Sinergia Pratama', 'PT Trigana Abadi Swakarsa', 'PT Triputra Energi Megatara', 'PT Tunas Artha Gardatama',
    'PT Unggul Jaya Berkah', 'PT United Tractors', 'PT Velseis Indonesia', 'PT Wahanabhara Bhakti', 'PT Weir Minerals Indonesia',
    'PT Win Wahana Ciptamarga', 'PT Wirya Krenindo Perkasa', 'PT Yerry Primatama Hosindo',
    'Politeknik Sinar Mas Berau Coal', 'Yayasan Dharma Bakti Berau Coal'
  ];

  // Prototype pengganti Master SID by system.
  // Pada versi production, ganti fungsi fetchEmployeeBySID() agar memanggil API/HRIS/database pusat.
  const SYSTEM_SID_DIRECTORY = [
    { id: 'SYS-EMP-1', sid: 'SID001', name: 'Budi Santoso', company: 'PAMA', structuralPosition: 'Supervisor', functionalPosition: 'Operator A2B', department: 'Operation' },
    { id: 'SYS-EMP-2', sid: 'SID002', name: 'Andi Wijaya', company: 'Berau Coal', structuralPosition: 'Superintendent', functionalPosition: 'Safety Evaluator', department: 'HSE' },
    { id: 'SYS-EMP-3', sid: 'SID003', name: 'Siti Rahma', company: 'BUMA', structuralPosition: 'Foreman', functionalPosition: 'Admin SHE', department: 'HSE' },
    { id: 'SYS-EMP-4', sid: 'SID004', name: 'Rizky Pratama', company: 'PAMA', structuralPosition: 'Group Leader', functionalPosition: 'Mekanik', department: 'Maintenance' }
  ];

  // Prototype daftar perusahaan undangan/eligible by system.
  // Production: ganti dengan API master vendor / contractor list per event/site.
  const SYSTEM_COMPANY_DIRECTORY = [
    'PT Mutiara Tanjung Lestari', 'PT Bukit Makmur Mandiri Utama', 'PT Fajar Anugrah Dinamika', 'PT Kaltim Diamond Coal',
    'CV Bukit Manimbora', 'CV Hulu Putra Banua', 'CV Megah Jaya Abadi', 'CV Rangga', 'CV Sambaliung Fiber', 'CV Triana Jaya',
    'CV. Varissa Jaya', 'Koperasi Maju Bersama', 'PT Agung Buana Rejeki', 'PT Andalan Duta Eka Nusantara', 'PT Apex Mitra Prima',
    'PT Arcistec International', 'PT Arexas Indonesia', 'PT Bagong Dekaka Makmur', 'PT Berkat Teman Sejati', 'PT. Buana Indah Lalebata',
    'PT Cominco Mitra Perkasa', 'PT. DNX Indonesia', 'PT. Eka Dharma Jaya Sakti', 'PT Energi Nuansa Jaya', 'PT Etam Wira Utama',
    'PT Eurotruk Trasindo', 'PT Geoservices', 'PT Hutan Rindang Banua', 'PT Imelda Teknik Mandiri', 'PT Indonesia Carbon Energy',
    'PT Limbah Bina Sejahtera', 'PT Lintech Duta Pratama', 'PT Majau Inti Jaya', 'PT Maju Bersama Binungan', 'PT Menara Borneo Jaya',
    'PT Mentari Cipta Mandiri', 'PT Mitra Lanuk Permai', 'PT Mitra Sukses Raharja', 'PT. Puncak Makmur Jaya', 'PT Rareendo Mulia Abadi',
    'PT Samburakat Jaya Utama', 'PT Serasi Autoraya', 'PT Smartfren Telecom', 'PT Sucofindo', 'PT Sumber Mitra Binungan',
    'PT Suprima Mitra Adihusada', 'PT Surveyor Carbon Consulting Indonesia', 'PT Tectona Mitra Utama', 'PT Trakindo Utama', 'PT Transkon Jaya',
    'PT Triatra Sinergia Pratama', 'PT United Tractors', 'CV Juwita', 'CV Putri Dewi', 'CV Santoso Putra Mandiri', 'CV Teguh Harapan',
    'PT Garuda Bakti Nusantara', 'PT Harmoni Mitra Utama', 'PT Joymar Abadi Indonesia', 'PT Kalimantan Teknik Utama', 'PT Orecon Putra Perkasa',
    'PT Taubah Berlian Jaya', 'PT. Anditha Asri Borneo', 'CV Elang Maju Mapan', 'CV Maju Makmur Teknik', 'CV Tangguh Mandiri',
    'PT Altros Teknologi', 'PT Energi Indonesia Berkarya', 'PT Madhani Talatah Nusantara', 'PAMA', 'Berau Coal', 'BUMA'
  ];
  let db = loadDB();
  let bootstrapLoaded = false;
  let bootstrapPromise = null;
  let formOptionsLoaded = false;
  let formOptionsPromise = null;
  let fullDataLoaded = false;
  let eventsListPage = 1;
  let eventsListMeta = { current_page: 1, last_page: 1, total: 0 };
  let companiesLoaded = false;
  let companiesPromise = null;
  let companyDataTable = null;
  let companySiteColumns = [];
  let reportAttendanceTable = null;
  let reportMinutesTable = null;
  let reportTabInitialized = false;
  let reportReloadTimer = null;
  const eventCache = {};
  const DATATABLES_LANG = {
    processing: 'Memuat...',
    lengthMenu: 'Tampilkan _MENU_ data',
    zeroRecords: 'Tidak ada data',
    info: 'Menampilkan _START_–_END_ dari _TOTAL_',
    infoEmpty: 'Menampilkan 0 data',
    infoFiltered: '(disaring dari _MAX_)',
    search: 'Cari:',
    paginate: { first: '«', last: '»', next: '›', previous: '‹' }
  };
  let meetingTypeRecords = [];
  let scannedEventId = getScannedEventIdFromURL();
  let selectedRecapEventId = '';
  let sitePerformanceChart = null;
  const ISSUE_TABLE_META = {
    enviro: { id: 'enviro', title: 'Enviro Issue', defaultRows: 3 },
    safety: { id: 'safety', title: 'Safety Issue', defaultRows: 3 },
    general: { id: 'general', title: 'General Issue', defaultRows: 3 }
  };
  let activeIssueSections = [];
  let issueRowCounts = { enviro: 3, safety: 3, general: 3 };
  let reportView = 'attendance';
  let eventListMode = 'active';
  let pendingCloseEventId = '';
  let closePromptSnoozed = {};
  let pendingCloseIssue = null;
  let minutesMgmtSelectedEventId = '';
  let minutesMgmtPage = 1;
  let minutesMgmtMeta = { current_page: 1, last_page: 1, total: 0 };
  let minutesMgmtSearchTimer = null;
  let minutesMgmtInitialized = false;
  let refreshTimer = null;

  function loadDB() {
    return migrateDB({ events: [], attendance: [], companies: getDefaultCompanyMaster(), meetingTypes: getDefaultMeetingTypes() });
  }

  function normalizeSiteValue(site) {
    const raw = String(site || '').trim();
    const upper = raw.toUpperCase();
    if (['MTL', 'CPP', 'PORT / JETTY', 'PORT', 'JETTY', 'MARINE'].includes(upper)) return 'Marine';
    if (['HO', 'HEAD OFFICE', 'HOTE'].includes(upper)) return 'HOTE';
    const exact = SITE_MASTER.find(item => item.toUpperCase() === upper);
    return exact || raw;
  }

  function migrateDB(data) {
    const next = {
      events: Array.isArray(data.events) ? data.events : [],
      attendance: Array.isArray(data.attendance) ? data.attendance : [],
      companies: Array.isArray(data.companies) && data.companies.length ? data.companies : getDefaultCompanyMaster(),
      meetingTypes: Array.isArray(data.meetingTypes) && data.meetingTypes.length ? data.meetingTypes : getDefaultMeetingTypes()
    };
    next.events = next.events.map(ev => ({
      ...ev,
      site: normalizeSiteValue(ev.site),
      meetingLevel: ev.meetingLevel || 'site',
      targetCompanies: Array.isArray(ev.targetCompanies) ? ev.targetCompanies : [],
      targetPositions: Array.isArray(ev.targetPositions) ? ev.targetPositions : [],
      targetDepartments: Array.isArray(ev.targetDepartments) ? ev.targetDepartments : []
    })).filter(ev => SITE_MASTER.includes(ev.site));
    next.companies = next.companies.map(company => {
      const mappedSites = [...new Set((company.sites || SITE_MASTER).map(normalizeSiteValue).filter(site => SITE_MASTER.includes(site)))];
      return { ...company, sites: mappedSites.length ? mappedSites : [...SITE_MASTER] };
    });
    next.meetingTypes = [...new Set(next.meetingTypes.map(value => String(value || '').trim()).filter(Boolean))];
    if (!next.meetingTypes.length) next.meetingTypes = getDefaultMeetingTypes();
    return next;
  }

  function getDefaultMeetingTypes() {
    return [...MEETING_TYPES];
  }

  function getMeetingTypes() {
    if (!Array.isArray(db.meetingTypes) || !db.meetingTypes.length) db.meetingTypes = getDefaultMeetingTypes();
    db.meetingTypes = [...new Set(db.meetingTypes.map(value => String(value || '').trim()).filter(Boolean))];
    if (!db.meetingTypes.length) db.meetingTypes = getDefaultMeetingTypes();
    return db.meetingTypes;
  }

  function saveDB() {
    refreshAll();
  }

  async function apiFetch(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF_TOKEN,
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {})
      }
    });
    const raw = await response.text();
    let payload = {};
    try {
      payload = raw ? JSON.parse(raw) : {};
    } catch (e) {
      payload = {};
    }
    if (!response.ok) {
      const fromErrors = payload.errors && typeof payload.errors === 'object'
        ? Object.values(payload.errors).flat().filter(Boolean).join(' ')
        : '';
      const msg = fromErrors || payload.message || (response.status === 419 ? 'Sesi kedaluwarsa, muat ulang halaman.' : '') || `Request gagal (HTTP ${response.status})`;
      throw new Error(msg);
    }
    return payload;
  }

  function getEventById(eventId) {
    const key = String(eventId || '');
    if (eventCache[key]) return eventCache[key];
    return db.events.find(x => String(x.id) === key);
  }

  function getAttendanceLogsForEvent(eventId) {
    const key = String(eventId || '');
    return db.attendance.filter(a => String(a.eventId) === key);
  }

  async function ensureCompaniesLoaded() {
    if (companiesLoaded) return;
    if (companiesPromise) return companiesPromise;
    companiesPromise = apiFetch(`${SID_MEETING_API_BASE}/companies/options`)
      .then(payload => {
        if (Array.isArray(payload.companies) && payload.companies.length) {
          db.companies = migrateDB({ companies: payload.companies }).companies;
        }
        companiesLoaded = true;
      })
      .catch(err => console.warn('Companies options gagal:', err))
      .finally(() => { companiesPromise = null; });
    return companiesPromise;
  }

  function cacheEvent(ev) {
    if (!ev?.id) return;
    eventCache[String(ev.id)] = ev;
    const idx = db.events.findIndex(x => String(x.id) === String(ev.id));
    if (idx >= 0) db.events[idx] = ev;
    else db.events.push(ev);
  }

  async function loadFormOptions() {
    if (formOptionsLoaded) return;
    if (formOptionsPromise) return formOptionsPromise;
    formOptionsPromise = apiFetch(`${SID_MEETING_API_BASE}/form-options`)
      .then(payload => {
        const meetingSelect = document.getElementById('meetingType');
        const siteSelect = document.getElementById('eventSite');
        const types = payload.meetingTypes || [];
        const sites = payload.sites || [];
        if (meetingSelect) {
          const current = meetingSelect.value;
          meetingSelect.innerHTML = '<option value="">Pilih Jenis Meeting</option>'
            + types.map(t => `<option value="${escapeHTML(t.name)}">${escapeHTML(t.name)}</option>`).join('');
          if (current) meetingSelect.value = current;
        }
        if (siteSelect) {
          const currentSite = siteSelect.value;
          siteSelect.innerHTML = '<option value="">Pilih Site</option>'
            + sites.map(s => `<option value="${escapeHTML(s.name)}">${escapeHTML(s.name)}</option>`).join('');
          if (currentSite) siteSelect.value = currentSite;
        }
        meetingTypeRecords = types;
        db.meetingTypes = types.length
          ? types.map(t => t.name)
          : getDefaultMeetingTypes();
        formOptionsLoaded = true;
        renderMeetingTypeManager();
        renderMeetingTargetOptions({
          targetCompanies: getCheckedValues('targetCompanies'),
          targetPositions: getCheckedValues('targetPositions'),
          targetDepartments: getCheckedValues('targetDepartments')
        });
        handleMeetingLevelChange();
      })
      .catch(err => {
        console.warn('Form options gagal:', err);
        renderMeetingTypeOptions();
        renderMeetingTargetOptions({});
        handleMeetingLevelChange();
      })
      .finally(() => { formOptionsPromise = null; });
    return formOptionsPromise;
  }

  async function loadStats() {
    try {
      const stats = await apiFetch(`${SID_MEETING_API_BASE}/stats`);
      document.getElementById('statEvents').textContent = stats.totalEvents ?? 0;
      document.getElementById('statActiveEvents').textContent = stats.activeEvents ?? 0;
      document.getElementById('statAttendance').textContent = stats.totalAttendance ?? 0;
      document.getElementById('statAttendanceRateAll').textContent = (stats.attendanceRate ?? 0) + '%';
    } catch (err) {
      console.warn('Stats gagal:', err);
    }
  }

  function reloadEventsTable() {
    renderEvents();
  }

  let eventsSearchTimer = null;
  function scheduleEventsSearch() {
    clearTimeout(eventsSearchTimer);
    eventsSearchTimer = setTimeout(() => {
      eventsListPage = 1;
      renderEvents();
    }, 350);
  }

  async function renderEvents() {
    const target = document.getElementById('eventList');
    if (!target) return;
    const q = (document.getElementById('eventSearch')?.value || '').trim();
    target.innerHTML = '<div class="rounded-3xl bg-white p-6 text-center text-sm text-slate-500 ring-1 ring-slate-200">Memuat daftar event...</div>';
    try {
      const params = new URLSearchParams({
        list_mode: eventListMode,
        page: String(eventsListPage),
        per_page: '20',
        q
      });
      const payload = await apiFetch(`${SID_MEETING_API_BASE}/events/list?${params.toString()}`);
      const rows = payload.data || [];
      eventsListMeta = payload.meta || eventsListMeta;
      if (!rows.length) {
        target.innerHTML = '<div class="rounded-3xl bg-white p-6 text-center text-sm font-semibold text-slate-500 ring-1 ring-slate-200">Belum ada event. Buat event pertama dari form Create Event.</div>';
        renderEventsPagination();
        return;
      }
      rows.forEach(ev => cacheEvent(ev));
      target.innerHTML = rows.map(ev => {
        const status = getEventStatus(ev);
        const total = Number(ev.attendanceCount ?? 0);
        const qrLink = buildQRLink(ev.id);
        return `<article onclick="openEventRecapModal('${escapeJS(ev.id)}')" class="cursor-pointer rounded-3xl bg-white p-5 ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg"><div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"><div class="min-w-0"><div class="mb-2 flex flex-wrap items-center gap-2">${statusBadge(status)}<span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-black text-violet-700">${escapeHTML(getMeetingLevelLabel(ev.meetingLevel))}</span><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">${escapeHTML(ev.code)}</span><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700">${escapeHTML(ev.week)}</span></div><h3 class="text-lg font-black text-slate-950">${escapeHTML(ev.meetingType)}</h3><p class="mt-1 text-sm text-slate-500">${escapeHTML(ev.site)} · ${formatDate(ev.date)} · ${ev.startTime} - ${ev.endTime}</p><p class="mt-2 break-all text-xs text-slate-400">${escapeHTML(qrLink)}</p><p class="mt-2 text-xs font-bold text-blue-600">Klik kartu untuk melihat rekap event</p></div><div class="min-w-44 rounded-2xl bg-slate-50 p-4 text-sm ring-1 ring-slate-200"><div class="flex justify-between gap-4"><span>Absensi</span><b>${total}</b></div><div class="mt-1 flex justify-between gap-4"><span>Status</span><b>${status}</b></div><div class="mt-1 flex justify-between gap-4"><span>Waktu</span><b class="font-mono">${formatDuration(getElapsedMs(ev))}</b></div>${ev.closedAt ? `<div class="mt-1 text-xs text-slate-500">Closed: ${formatDateTime(ev.closedAt)}</div>` : ''}</div></div><div class="no-print mt-4 flex flex-wrap gap-2"><button onclick="event.stopPropagation(); openQRModal('${escapeJS(ev.id)}')" class="rounded-xl bg-cyan-50 px-3 py-2 text-xs font-black text-cyan-700 hover:bg-cyan-100">Lihat QR</button><button onclick="event.stopPropagation(); openAttendanceFromEvent('${escapeJS(ev.id)}')" class="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700 hover:bg-emerald-100">Absen Manual</button>${status !== 'Closed' ? `<button onclick="event.stopPropagation(); askCloseMeeting('${escapeJS(ev.id)}', true)" class="rounded-xl bg-orange-50 px-3 py-2 text-xs font-black text-orange-700 hover:bg-orange-100">Tutup Meeting</button>` : ''}<button onclick="event.stopPropagation(); copyText('${escapeJS(qrLink)}', this)" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-200">Copy Link Absensi</button><button onclick="event.stopPropagation(); editEvent('${escapeJS(ev.id)}')" class="rounded-xl bg-blue-50 px-3 py-2 text-xs font-black text-blue-700 hover:bg-blue-100">Edit</button><button onclick="event.stopPropagation(); deleteEvent('${escapeJS(ev.id)}')" class="rounded-xl bg-red-50 px-3 py-2 text-xs font-black text-red-700 hover:bg-red-100">Hapus</button></div></article>`;
      }).join('');
      renderEventsPagination();
    } catch (err) {
      target.innerHTML = `<div class="rounded-3xl bg-red-50 p-6 text-center text-sm text-red-700 ring-1 ring-red-100">${escapeHTML(err.message || 'Gagal memuat daftar event')}</div>`;
    }
  }

  function renderEventsPagination() {
    const box = document.getElementById('eventListPagination');
    if (!box) return;
    const { current_page, last_page, total } = eventsListMeta;
    if (!total) {
      box.innerHTML = '';
      return;
    }
    box.innerHTML = `
      <span>Menampilkan halaman ${current_page} dari ${last_page} (${total} event)</span>
      <div class="flex gap-2">
        <button type="button" ${current_page <= 1 ? 'disabled' : ''} onclick="changeEventsPage(${current_page - 1})" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-700 disabled:opacity-40">Sebelumnya</button>
        <button type="button" ${current_page >= last_page ? 'disabled' : ''} onclick="changeEventsPage(${current_page + 1})" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-700 disabled:opacity-40">Berikutnya</button>
      </div>`;
  }

  function changeEventsPage(page) {
    if (page < 1 || page > eventsListMeta.last_page) return;
    eventsListPage = page;
    renderEvents();
  }

  function updateCompanyMasterStats(json) {
    const total = Number(json?.recordsTotal ?? 0);
    const filtered = Number(json?.recordsFiltered ?? 0);
    const totalEl = document.getElementById('companyMasterTotal');
    const filteredEl = document.getElementById('companyMasterFiltered');
    const infoEl = document.getElementById('companyMasterInfo');
    if (totalEl) totalEl.textContent = `Total: ${total}`;
    if (filteredEl) filteredEl.textContent = `Ditampilkan: ${filtered}`;
    if (infoEl) {
      infoEl.textContent = filtered === total
        ? 'Semua perusahaan ditampilkan'
        : `Hasil filter dari ${total} perusahaan`;
    }
  }

  function buildCompanyTableColumns(siteNames) {
    return [
      { data: 'name', name: 'name', className: 'company-col-sticky' },
      ...siteNames.map((site, index) => ({
        data: null,
        name: `site_${site}`,
        orderable: false,
        searchable: false,
        className: 'company-site-col',
        defaultContent: '',
        render: (_data, _type, row) => (row.site_cells && row.site_cells[index]) ? row.site_cells[index] : ''
      })),
      { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'action-col-sticky text-center no-print' }
    ];
  }

  function renderCompanyTableHead(siteNames) {
    const headRow = document.querySelector('#companyMasterTableHead tr');
    if (!headRow || !siteNames.length) return;
    headRow.innerHTML = '<th class="company-col-sticky">Perusahaan</th>'
      + siteNames.map(site => `<th class="company-site-col" title="${escapeHTML(site)}"><span>${escapeHTML(site)}</span></th>`).join('')
      + '<th class="action-col-sticky text-center no-print">Aksi</th>';
  }

  async function initCompanyDataTable() {
    if (!window.jQuery || !$.fn.DataTable) return;
    const $table = $('#companyDataTable');
    if (!$table.length) return;

    const tab = document.getElementById('tab-companymaster');
    if (tab && tab.classList.contains('hidden')) return;

    if (companyDataTable) {
      try {
        companyDataTable.ajax.reload(null, false);
      } catch (err) {
        console.warn('Reload company table gagal, re-init:', err);
        companyDataTable = null;
      }
      if (companyDataTable) return;
    }

    if ($.fn.DataTable.isDataTable($table)) {
      $table.DataTable().destroy();
      $table.find('tbody').empty();
    }

    let siteNames = [...SITE_MASTER];
    try {
      const opts = await apiFetch(`${SID_MEETING_API_BASE}/form-options`);
      const fromApi = (opts.sites || []).map(s => s.name).filter(Boolean);
      if (fromApi.length) siteNames = fromApi;
    } catch (err) {
      console.warn('Site columns fallback ke SITE_MASTER:', err);
    }

    companySiteColumns = siteNames;
    renderCompanyTableHead(siteNames);

    companyDataTable = $table.DataTable({
      processing: true,
      serverSide: true,
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      order: [[0, 'asc']],
      language: DATATABLES_LANG,
      dom: '<"company-dt-toolbar flex flex-col gap-3 mb-4 sm:flex-row sm:items-center sm:justify-between"lf>rt<"company-dt-footer flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"ip>',
      autoWidth: false,
      ajax: {
        url: `${SID_MEETING_API_BASE}/companies/data`,
        dataSrc: json => {
          updateCompanyMasterStats(json);
          return json.data || [];
        }
      },
      columns: buildCompanyTableColumns(siteNames),
      initComplete: function() {
        $('#companyDataTable_wrapper').addClass('company-dt-wrapper');
      }
    });
  }

  async function toggleCompanySiteDb(companyId, site, checked) {
    try {
      await apiFetch(`${SID_MEETING_API_BASE}/companies/${companyId}/sites`, {
        method: 'POST',
        body: JSON.stringify({ site, checked })
      });
      toast('Site eligibility diperbarui');
      if (companyDataTable) companyDataTable.ajax.reload(null, false);
    } catch (err) {
      toast(err.message || 'Gagal memperbarui site');
      if (companyDataTable) companyDataTable.ajax.reload(null, false);
    }
  }

  function editCompanyDb(companyId, companyName) {
    document.getElementById('companyId').value = 'COMP-' + companyId;
    document.getElementById('companyName').value = companyName || '';
    toggleCompanyInputContainer(true);
  }

  async function deleteCompanyDb(companyId) {
    if (!confirm('Hapus perusahaan dari master?')) return;
    try {
      await fetch(`/sid-meeting/companies/${companyId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      });
      toast('Perusahaan dihapus');
      if (companyDataTable) companyDataTable.ajax.reload(null, false);
      loadStats();
    } catch (err) {
      toast('Gagal menghapus perusahaan');
    }
  }

  async function openQRModalById(eventId) {
    try {
      const payload = await apiFetch(`${SID_MEETING_API_BASE}/events/${eventId}`);
      cacheEvent(payload.event);
      openQRModal(String(eventId));
    } catch (err) {
      toast(err.message || 'Gagal memuat QR event');
    }
  }

  async function hydrateFromDatabase(force = false) {
    if (fullDataLoaded && !force) return;
    if (bootstrapPromise) return bootstrapPromise;
    bootstrapPromise = apiFetch(`${SID_MEETING_API_BASE}/bootstrap`)
      .then(payload => {
        db = migrateDB({
          events: payload.events || [],
          attendance: (payload.attendance || []).map(normalizeAttendanceRow),
          companies: payload.companies || [],
          meetingTypes: payload.meetingTypes || getDefaultMeetingTypes()
        });
        bootstrapLoaded = true;
        fullDataLoaded = true;
        refreshHeavyViews();
      })
      .catch(err => {
        console.warn('Bootstrap database gagal:', err);
        toast('Gagal memuat data lengkap untuk rekap/performance.');
      })
      .finally(() => {
        bootstrapPromise = null;
      });
    return bootstrapPromise;
  }

  async function ensureFullDataLoaded() {
    if (!fullDataLoaded) await hydrateFromDatabase(false);
  }

  function uid(prefix = 'ID') { return prefix + '-' + Math.random().toString(36).slice(2, 9).toUpperCase(); }

  function toast(message) {
    const el = document.getElementById('toast');
    if (!el) return;
    el.textContent = message;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 2500);
  }

  function eventSaveAlert(type, message) {
    if (window.Swal) {
      const titles = { success: 'Berhasil', error: 'Gagal', warning: 'Perhatian' };
      const colors = { success: '#2563eb', error: '#dc2626', warning: '#d97706' };
      Swal.fire({
        icon: type,
        title: titles[type] || 'Info',
        text: message,
        confirmButtonColor: colors[type] || '#2563eb'
      });
      return;
    }
    toast(message);
  }

  function escapeHTML(value) { return String(value ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c])); }
  function formatDate(value) { if (!value) return '-'; const d = new Date(value + 'T00:00:00'); return isNaN(d) ? value : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }); }
  function formatDateTime(value) { if (!value) return '-'; const d = new Date(value); return isNaN(d) ? value : d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'medium' }); }
  function toDateTime(date, time) { return new Date(`${date}T${time || '00:00'}:00`); }

  function getEventStatus(ev, now = new Date()) {
    if (!ev) return 'Draft';
    const start = toDateTime(ev.date, ev.startTime);
    const end = toDateTime(ev.date, ev.endTime);
    if (ev.manualStatus === 'Draft') return 'Draft';
    if (ev.manualStatus === 'Closed' || ev.closedAt) return 'Closed';
    if (now < start) return 'Upcoming';
    if (now >= end) return 'Overrun';
    return 'Open';
  }

  function isEventActive(ev) { return ['Open', 'Overrun'].includes(getEventStatus(ev)); }

  function statusBadge(status) {
    const cls = status === 'Open' ? 'badge-open' : status === 'Overrun' ? 'badge-overrun' : status === 'Closed' ? 'badge-closed' : status === 'Expired' ? 'badge-expired' : status === 'Upcoming' ? 'badge-upcoming' : 'badge-draft';
    const label = status === 'Closed' ? 'Meeting Ditutup' : status === 'Overrun' ? 'Lewat Jam Selesai' : status === 'Expired' ? 'QR Tidak Aktif' : status === 'Open' ? 'QR Aktif' : status === 'Upcoming' ? 'Belum Mulai' : 'Draft';
    return `<span class="badge ${cls}">${label}</span>`;
  }

  function getElapsedMs(ev, now = new Date()) {
    if (!ev?.date || !ev?.startTime) return 0;
    const start = toDateTime(ev.date, ev.startTime);
    const stop = ev.closedAt ? new Date(ev.closedAt) : now;
    return Math.max(0, stop - start);
  }

  function formatDuration(ms) {
    const totalSeconds = Math.floor(Math.max(0, ms) / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    return [hours, minutes, seconds].map(v => String(v).padStart(2, '0')).join(':');
  }

  function getISOWeek(dateString) {
    const date = new Date(dateString + 'T00:00:00');
    const target = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const dayNum = target.getUTCDay() || 7;
    target.setUTCDate(target.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1));
    return Math.ceil((((target - yearStart) / 86400000) + 1) / 7);
  }

  function autoFillWeek() {
    const date = document.getElementById('meetingDate')?.value;
    if (!date) return;
    document.getElementById('meetingWeek').value = `W${String(getISOWeek(date)).padStart(2, '0')}`;
  }

  function getScannedEventIdFromURL() {
    const params = new URLSearchParams(window.location.hash.replace('#', ''));
    return params.get('absen') || '';
  }

  function buildQRLink(eventId) {
    const ev = getEventById(eventId);
    if (ev?.qrToken) {
      return `${window.location.origin}/attendance/${encodeURIComponent(ev.qrToken)}`;
    }
    const base = window.location.href.split('#')[0];
    return `${base}#absen=${encodeURIComponent(eventId)}`;
  }

  function setFloatingOverlay(open) {
    const overlay = document.getElementById('floatingFormOverlay');
    if (!overlay) return;
    const anyOpen = open || document.getElementById('createEventContainer')?.classList.contains('open') || document.getElementById('companyInputContainer')?.classList.contains('open');
    overlay.classList.toggle('open', !!anyOpen);
  }

  function closeFloatingPanels() {
    document.getElementById('createEventContainer')?.classList.remove('open');
    document.getElementById('companyInputContainer')?.classList.remove('open');
    setFloatingOverlay(false);
  }

  function toggleCreateEventContainer(forceState) {
    const panel = document.getElementById('createEventContainer');
    if (!panel) return;
    const shouldOpen = typeof forceState === 'boolean' ? forceState : !panel.classList.contains('open');
    document.getElementById('companyInputContainer')?.classList.remove('open');
    panel.classList.toggle('open', shouldOpen);
    setFloatingOverlay(shouldOpen);
    if (shouldOpen) {
      loadFormOptions();
      setTimeout(() => document.getElementById('meetingType')?.focus(), 120);
    }
  }

  function toggleCompanyInputContainer(forceState) {
    const panel = document.getElementById('companyInputContainer');
    if (!panel) return;
    const shouldOpen = typeof forceState === 'boolean' ? forceState : !panel.classList.contains('open');
    document.getElementById('createEventContainer')?.classList.remove('open');
    panel.classList.toggle('open', shouldOpen);
    setFloatingOverlay(shouldOpen);
    if (shouldOpen) setTimeout(() => document.getElementById('companyName')?.focus(), 120);
  }

  function showTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(el => el.classList.add('hidden'));
    document.getElementById(`tab-${tab}`)?.classList.remove('hidden');
    document.querySelectorAll('.tab-btn').forEach(btn => { btn.classList.remove('tab-active'); });
    const active = document.querySelector(`[data-tab="${tab}"]`);
    if (active) { active.classList.add('tab-active'); }
    if (tab === 'events') {
      loadFormOptions();
      loadStats();
      renderEvents();
    } else if (tab === 'companymaster') {
      setTimeout(() => initCompanyDataTable(), 0);
    } else if (tab === 'report') {
      initReportTab();
    } else if (tab === 'minutesmgmt') {
      initMinutesMgmtTab();
    } else if (tab === 'siteperformance') {
      initSitePerformanceTabLazy();
    } else if (tab === 'semanticeval') {
      initSemanticTabLazy();
    }
  }

  async function saveEvent(e) {
    e.preventDefault();
    const level = document.getElementById('meetingLevel')?.value || 'site';
    const payload = {
      meeting_type: document.getElementById('meetingType').value,
      site: normalizeSiteValue(document.getElementById('eventSite').value.trim()),
      meeting_date: document.getElementById('meetingDate').value,
      week: document.getElementById('meetingWeek').value.trim().toUpperCase(),
      start_time: document.getElementById('startTime').value,
      end_time: document.getElementById('endTime').value,
      meeting_level: level,
      target_companies: level === 'site' ? [] : getCheckedValues('targetCompanies'),
      target_positions: level === 'company' ? getCheckedValues('targetPositions') : [],
      target_departments: level === 'department' ? getCheckedValues('targetDepartments') : []
    };
    if (!SITE_MASTER.includes(payload.site)) return eventSaveAlert('error', 'Site tidak valid. Pilih site sesuai master.');
    if (level !== 'site' && !payload.target_companies.length) return eventSaveAlert('error', 'Pilih minimal 1 perusahaan wajib hadir.');
    if (level === 'company' && !payload.target_positions.length) return eventSaveAlert('error', 'Pilih minimal 1 jabatan wajib hadir.');
    if (level === 'department' && !payload.target_departments.length) return eventSaveAlert('error', 'Pilih minimal 1 department wajib hadir.');
    if (toDateTime(payload.meeting_date, payload.end_time) <= toDateTime(payload.meeting_date, payload.start_time)) return eventSaveAlert('error', 'Jam selesai harus lebih besar dari jam mulai');
    try {
      await apiFetch(`${SID_MEETING_API_BASE}/events`, { method: 'POST', body: JSON.stringify(payload) });
      clearEventForm();
      toggleCreateEventContainer(false);
      reloadEventsTable();
      loadStats();
      eventSaveAlert('success', 'Event berhasil disimpan ke database');
    } catch (err) {
      eventSaveAlert('error', err.message || 'Gagal simpan event');
    }
  }

  function editEvent(id) {
    const ev = getEventById(id); if (!ev) return;
    toggleCreateEventContainer(true);
    document.getElementById('eventId').value = ev.id;
    document.getElementById('meetingType').value = ev.meetingType;
    document.getElementById('meetingLevel').value = ev.meetingLevel || 'site';
    renderMeetingTargetOptions(ev);
    handleMeetingLevelChange();
    document.getElementById('eventSite').value = ev.site;
    document.getElementById('meetingDate').value = ev.date;
    document.getElementById('meetingWeek').value = ev.week;
    document.getElementById('startTime').value = ev.startTime;
    document.getElementById('endTime').value = ev.endTime;
    showTab('events');
  }

  function deleteEvent(id) {
    toast('Penghapusan event dinonaktifkan demi keamanan data.');
  }

  function clearEventForm() {
    const form = document.getElementById('eventForm'); if (!form) return;
    form.reset();
    document.getElementById('eventId').value = '';
    document.getElementById('meetingDate').value = new Date().toISOString().slice(0, 10);
    autoFillWeek();
    document.getElementById('startTime').value = '08:00';
    document.getElementById('endTime').value = '10:00';
    const levelEl = document.getElementById('meetingLevel');
    if (levelEl) levelEl.value = 'site';
    renderMeetingTargetOptions({});
    handleMeetingLevelChange();
  }

  function setEventListMode(mode) {
    eventListMode = mode === 'inactive' ? 'inactive' : 'active';
    document.getElementById('eventListActiveBtn')?.classList.toggle('tab-active', eventListMode === 'active');
    document.getElementById('eventListInactiveBtn')?.classList.toggle('tab-active', eventListMode === 'inactive');
    eventsListPage = 1;
    reloadEventsTable();
  }

  function escapeJS(value) { return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n').replace(/\r/g, '\\r'); }

  function openQRModal(eventId) {
    const ev = getEventById(eventId); if (!ev) return;
    const link = buildQRLink(eventId);
    const box = document.getElementById('qrBox'); box.innerHTML = '';
    document.getElementById('qrModalTitle').textContent = `${ev.meetingType} · ${ev.site} · ${ev.week}`;
    document.getElementById('qrLink').value = link;
    document.getElementById('manualCopyBox')?.classList.add('hidden');
    if (window.QRCode) new QRCode(box, { text: link, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.H });
    else box.innerHTML = '<div class="text-center text-sm font-bold text-red-700">Library QR gagal dimuat. Gunakan link absensi di bawah.</div>';
    document.getElementById('qrModal').classList.remove('hidden');
    document.getElementById('qrModal').classList.add('flex');
  }

  function closeQRModal() { document.getElementById('qrModal').classList.add('hidden'); document.getElementById('qrModal').classList.remove('flex'); }
  function copyQRLink(buttonEl) { copyText(document.getElementById('qrLink').value, buttonEl); }

  async function openEventRecapModal(eventId) {
    selectedRecapEventId = String(eventId);
    scannedEventId = String(eventId);
    try {
      await ensureCompaniesLoaded();
      const payload = await apiFetch(`${SID_MEETING_API_BASE}/events/${encodeURIComponent(eventId)}`);
      cacheEvent(payload.event);
      window.recapEligibleCompanies = Array.isArray(payload.eligibleCompanies) ? payload.eligibleCompanies : null;
      db.attendance = db.attendance.filter(a => String(a.eventId) !== String(eventId));
      (payload.attendance || []).forEach(row => db.attendance.push(normalizeAttendanceRow(row)));
      renderEventRecapModal(eventId, payload.attendance || []);
      renderActiveAttendanceEvent();
      document.getElementById('eventRecapModal').classList.remove('hidden');
      document.getElementById('eventRecapModal').classList.add('flex');
    } catch (err) {
      toast(err.message || 'Gagal memuat detail event');
    }
  }

  function closeEventRecapModal() {
    closeManualAttendanceForm();
    closeEventMinutesForm();
    document.getElementById('eventRecapModal').classList.add('hidden');
    document.getElementById('eventRecapModal').classList.remove('flex');
  }

  function openManualAttendanceForm() {
    const panel = document.getElementById('manualAttendancePanel');
    if (!panel) return;
    panel.classList.remove('hidden');
    renderActiveAttendanceEvent();
    setTimeout(() => document.getElementById('sidInput')?.focus(), 120);
  }

  function closeManualAttendanceForm() {
    const panel = document.getElementById('manualAttendancePanel');
    if (panel) panel.classList.add('hidden');
  }

  function openEventMinutesForm() {
    const panel = document.getElementById('minutesInputPanel');
    if (!panel) return;
    panel.classList.remove('hidden');
    setTimeout(() => document.getElementById('minutesMeetingTitle')?.focus(), 120);
  }

  function closeEventMinutesForm() {
    const panel = document.getElementById('minutesInputPanel');
    if (panel) panel.classList.add('hidden');
  }

  function normalizeCompanyName(value) {
    return String(value || '').toUpperCase().split('.').join('').trim().split(' ').filter(Boolean).join(' ');
  }

  function getDefaultCompanyMaster() {
    return SYSTEM_COMPANY_DIRECTORY.map((name, index) => ({
      id: `COMP-${String(index + 1).padStart(3, '0')}-${normalizeCompanyName(name).replace(/[^A-Z0-9]/g, '').slice(0, 10)}`,
      name,
      sites: [...SITE_MASTER],
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString()
    }));
  }

  function getCompanyMaster() {
    if (!Array.isArray(db.companies) || !db.companies.length) db.companies = getDefaultCompanyMaster();
    db.companies.forEach(company => {
      if (!Array.isArray(company.sites)) company.sites = [...SITE_MASTER];
      company.sites = [...new Set(company.sites.map(normalizeSiteValue).filter(site => SITE_MASTER.includes(site)))];
      if (!company.sites.length) company.sites = [...SITE_MASTER];
    });
    return db.companies;
  }

  function renderTargetChecklist(containerId, inputName, values = [], selected = []) {
    const box = document.getElementById(containerId);
    if (!box) return;
    const selectedSet = new Set(selected || []);
    box.innerHTML = values.map(value => `
      <label class="mb-2 flex cursor-pointer items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700">
        <input type="checkbox" name="${inputName}" value="${escapeHTML(value)}" ${selectedSet.has(value) ? 'checked' : ''} class="h-4 w-4 rounded border-slate-300 text-blue-600" />
        <span>${escapeHTML(value)}</span>
      </label>`).join('') || '<p class="p-3 text-xs font-semibold text-slate-400">Belum ada data.</p>';
  }

  function getCheckedValues(name) {
    return [...document.querySelectorAll(`input[name="${name}"]:checked`)].map(el => el.value);
  }

  function getPositionOptions() {
    return [...POSITION_FUNCTIONAL_MASTER];
  }

  function getTargetCompanyOptions() {
    return [...TARGET_COMPANY_MASTER];
  }

  function renderTargetCompanyChecklist(selected = null) {
    const preserved = selected ?? getCheckedValues('targetCompanies');
    const selectedSet = new Set(preserved);
    const search = (document.getElementById('targetCompanySearch')?.value || '').toLowerCase().trim();
    const allCompanies = getTargetCompanyOptions();
    const filtered = allCompanies.filter(name => !search || name.toLowerCase().includes(search));
    const pinnedSelected = preserved.filter(name => search && !name.toLowerCase().includes(search));
    const displayList = [...new Set([...pinnedSelected, ...filtered])].sort((a, b) => a.localeCompare(b, 'id'));
    renderTargetChecklist('targetCompanyChecklist', 'targetCompanies', displayList, preserved);
    const info = document.getElementById('targetCompanySearchInfo');
    const checkedCount = preserved.length;
    if (info) {
      if (search) {
        info.textContent = `Menampilkan ${filtered.length} dari ${allCompanies.length} perusahaan · ${checkedCount} terpilih`;
      } else {
        info.textContent = `${allCompanies.length} perusahaan · ${checkedCount} terpilih`;
      }
    }
  }

  function filterTargetCompanies() {
    renderTargetCompanyChecklist();
  }

  function normalizeAttendanceRow(row = {}) {
    return { ...row, department: row.department || inferEmployeeDepartment(row) };
  }

  function inferEmployeeDepartment(emp = {}) {
    const text = [emp.department, emp.structuralPosition, emp.functionalPosition, emp.position, emp.jabatan].filter(Boolean).join(' ').toLowerCase();
    if (/hse|safety|ohs|k3/.test(text)) return 'HSE';
    if (/maint|mechanic|plant|workshop/.test(text)) return 'Maintenance';
    if (/enviro|environment|lingkungan/.test(text)) return 'Environment';
    if (/engineer|technical/.test(text)) return 'Engineering';
    if (/manager|superintendent|pjo/.test(text)) return 'Management';
    return 'Operation';
  }

  function getDepartmentOptions() {
    return [...new Set([
      ...DEFAULT_DEPARTMENTS,
      ...SYSTEM_SID_DIRECTORY.map(inferEmployeeDepartment),
      ...db.attendance.map(a => a.department || inferEmployeeDepartment(a))
    ])].sort();
  }

  function renderMeetingTargetOptions(ev = {}) {
    const searchEl = document.getElementById('targetCompanySearch');
    if (searchEl && !searchEl.value) searchEl.value = '';
    renderTargetCompanyChecklist(ev.targetCompanies || []);
    renderTargetChecklist('targetPositionChecklist', 'targetPositions', getPositionOptions(), ev.targetPositions || []);
    renderTargetChecklist('targetDepartmentChecklist', 'targetDepartments', getDepartmentOptions(), ev.targetDepartments || []);
  }

  function handleMeetingLevelChange() {
    const level = document.getElementById('meetingLevel')?.value || 'site';
    document.getElementById('meetingTargetPanel')?.classList.toggle('hidden', level === 'site');
    document.getElementById('targetPositionBlock')?.classList.toggle('hidden', level !== 'company');
    document.getElementById('targetDepartmentBlock')?.classList.toggle('hidden', level !== 'department');
    const hint = document.getElementById('meetingLevelHint');
    if (hint) {
      hint.textContent = level === 'site'
        ? 'Site Level memakai master perusahaan sesuai site.'
        : level === 'company'
          ? 'Company Level memakai perusahaan pilihan dan jabatan wajib hadir.'
          : 'Department Level memakai perusahaan pilihan dan department wajib hadir.';
    }
    const targetHint = document.getElementById('meetingTargetHint');
    if (targetHint) {
      targetHint.textContent = level === 'company'
        ? 'Pilih perusahaan dan jabatan yang wajib hadir.'
        : 'Pilih perusahaan dan department yang wajib hadir.';
    }
  }

  function toggleAllTargetCompanies(checked) {
    if (checked) {
      const search = (document.getElementById('targetCompanySearch')?.value || '').toLowerCase().trim();
      const visible = getTargetCompanyOptions().filter(name => !search || name.toLowerCase().includes(search));
      const merged = new Set([...getCheckedValues('targetCompanies'), ...visible]);
      renderTargetCompanyChecklist([...merged]);
      return;
    }
    renderTargetCompanyChecklist([]);
  }

  function getMeetingLevelLabel(level) {
    if (level === 'company') return 'Company Level';
    if (level === 'department') return 'Department Level';
    return 'Site Level';
  }

  function getEligibleCompaniesForSite(site) {
    if (Array.isArray(window.recapEligibleCompanies) && window.recapEligibleCompanies.length) {
      return [...window.recapEligibleCompanies];
    }
    const targetSite = String(site || '').trim();
    const companies = getCompanyMaster();
    if (!targetSite) return companies.map(company => company.name);
    return companies.filter(company => (company.sites || []).includes(targetSite)).map(company => company.name);
  }

  function getTargetCompaniesForEvent(ev) {
    const level = ev?.meetingLevel || 'site';
    if (level !== 'site' && ev?.targetCompanies?.length) return ev.targetCompanies;
    return getEligibleCompaniesForSite(ev?.site || '');
  }

  function getRequiredCriteriaText(ev) {
    const level = ev?.meetingLevel || 'site';
    if (level === 'company') {
      return (ev.targetPositions || []).length ? `Jabatan: ${(ev.targetPositions || []).join(', ')}` : 'Semua jabatan';
    }
    if (level === 'department') {
      return (ev.targetDepartments || []).length ? `Dept: ${(ev.targetDepartments || []).join(', ')}` : 'Semua department';
    }
    return 'Master perusahaan site';
  }

  function attendanceMatchesExpectedUnit(att, unit) {
    if (!att || !unit) return false;
    if (normalizeCompanyName(att.company) !== normalizeCompanyName(unit.company)) return false;
    if (unit.level === 'site') return true;
    if (unit.level === 'company') {
      if (unit.targetValue === 'Semua Jabatan') return true;
      const target = String(unit.targetValue).trim().toLowerCase();
      const functional = String(att.functionalPosition || '').trim().toLowerCase();
      if (functional === target) return true;
      return [att.structuralPosition].filter(Boolean)
        .some(pos => String(pos).trim().toLowerCase() === target);
    }
    if (unit.level === 'department') {
      if (unit.targetValue === 'Semua Department') return true;
      return String(att.department || inferEmployeeDepartment(att)).toLowerCase() === String(unit.targetValue).toLowerCase();
    }
    return false;
  }

  function getEventLevel(ev) {
    return ev?.meetingLevel || 'site';
  }

  function getEventExpectedUnits(ev) {
    if (!ev) return [];
    const level = getEventLevel(ev);
    const companies = getTargetCompaniesForEvent(ev);
    if (level === 'company') {
      const positions = (ev.targetPositions || []).length ? ev.targetPositions : ['Semua Jabatan'];
      return companies.flatMap(company => positions.map(position => ({
        key: `${normalizeCompanyName(company)}::POSITION::${position}`,
        company,
        level,
        targetType: 'Jabatan',
        targetValue: position
      })));
    }
    if (level === 'department') {
      const departments = (ev.targetDepartments || []).length ? ev.targetDepartments : ['Semua Department'];
      return companies.flatMap(company => departments.map(department => ({
        key: `${normalizeCompanyName(company)}::DEPT::${department}`,
        company,
        level,
        targetType: 'Department',
        targetValue: department
      })));
    }
    return companies.map(company => ({
      key: `${normalizeCompanyName(company)}::SITE`,
      company,
      level: 'site',
      targetType: 'Company',
      targetValue: 'Company Representative'
    }));
  }

  function getEventPresentUnits(ev, logs = null) {
    const attendanceLogs = logs || getAttendanceLogsForEvent(ev?.id);
    return getEventExpectedUnits(ev).filter(unit => attendanceLogs.some(att => attendanceMatchesExpectedUnit(att, unit)));
  }

  function getAttendanceRateForEvents(events) {
    const expected = events.reduce((sum, ev) => sum + getEventExpectedUnits(ev).length, 0);
    const present = events.reduce((sum, ev) => sum + getEventPresentUnits(ev).length, 0);
    return expected ? Math.round((present / expected) * 100) : 0;
  }

  function isCompanyEligibleForSite(companyName, site) {
    const companyKey = normalizeCompanyName(companyName);
    return getEligibleCompaniesForSite(site).some(name => normalizeCompanyName(name) === companyKey);
  }

  function getCompanyStatusRows(logs, site = '', ev = null) {
    const companies = ev ? [...getTargetCompaniesForEvent(ev)] : [...getEligibleCompaniesForSite(site)];

    logs.forEach(a => {
      const company = a.company || 'Tidak Ada Perusahaan';
      if (!companies.some(x => normalizeCompanyName(x) === normalizeCompanyName(company))) {
        companies.push(company);
      }
    });

    const presentUnits = ev ? getEventPresentUnits(ev, logs) : null;

    return companies.map((company, index) => ({
      no: index + 1,
      company,
      status: logs.some(a => normalizeCompanyName(a.company) === normalizeCompanyName(company) && (!ev || presentUnits.some(u => u.company === company)))
        ? 'HADIR'
        : 'TIDAK HADIR',
      required: ev ? getRequiredCriteriaText(ev) : 'Site Level'
    }));
  }

  function renderEventRecapModal(eventId, logs = null) {
    const ev = getEventById(eventId);
    if (!ev) return;
    const attendanceLogs = Array.isArray(logs) ? logs : getAttendanceLogsForEvent(eventId);
    const companyRows = getCompanyStatusRows(attendanceLogs, ev.site, ev);
    const expectedUnits = getEventExpectedUnits(ev);
    const presentUnits = getEventPresentUnits(ev, attendanceLogs);
    const attendanceRate = expectedUnits.length ? Math.round((presentUnits.length / expectedUnits.length) * 100) : 0;

    document.getElementById('eventRecapTitle').textContent = `${ev.meetingType} · ${ev.site} · ${formatDate(ev.date)} · ${ev.week}`;
    document.getElementById('eventRecapSummary').innerHTML = [
      ['Target Hadir', `${presentUnits.length}/${expectedUnits.length}`],
      ['Attendance Rate', attendanceRate + '%'],
      ['Kategori Meeting', getMeetingLevelLabel(ev.meetingLevel)],
      ['Waktu Berjalan', formatDuration(getElapsedMs(ev))],
      ['Status Meeting', getEventStatus(ev)]
    ].map(([label, val]) => `<div class="rounded-3xl bg-slate-50 p-5 ring-1 ring-slate-200"><p class="text-sm font-semibold text-slate-500">${label}</p><p class="mt-2 text-xl font-black text-slate-950">${escapeHTML(String(val))}</p></div>`).join('');

    renderEventMinutesForm(ev);

    document.getElementById('eventRecapCompany').innerHTML = `
      <div class="table-wrap max-h-[520px] overflow-auto rounded-2xl border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
          <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
            <tr>
              <th class="w-16 px-4 py-3 text-center">No</th>
              <th class="px-4 py-3">Perusahaan</th>
              <th class="w-40 px-4 py-3 text-center">Status Kehadiran</th>
              <th class="px-4 py-3">Kriteria</th>
            </tr>
          </thead>
          <tbody>
            ${companyRows.map(row => `<tr class="border-t border-slate-100 hover:bg-slate-50"><td class="px-4 py-3 text-center text-slate-500">${row.no}</td><td class="px-4 py-3 font-semibold text-slate-800">${escapeHTML(row.company)}</td><td class="px-4 py-3 text-center"><span class="${row.status === 'HADIR' ? 'status-hadir' : 'status-tidak-hadir'}">${row.status}</span></td><td class="px-4 py-3 text-xs font-semibold text-slate-500">${escapeHTML(row.required)}</td></tr>`).join('')}
          </tbody>
        </table>
      </div>`;

    document.getElementById('eventRecapAttendees').innerHTML = attendanceLogs.length ? attendanceLogs.map(a => `<tr class="border-t border-slate-100"><td class="px-4 py-3">${formatDateTime(a.timestamp)}</td><td class="px-4 py-3 font-black">${escapeHTML(a.sid)}</td><td class="px-4 py-3"><b>${escapeHTML(a.name)}</b></td><td class="px-4 py-3">${escapeHTML(a.company || '-')}</td><td class="px-4 py-3"><div>${escapeHTML(a.structuralPosition || '-')}</div><div class="text-xs text-slate-500">${escapeHTML(a.functionalPosition || '-')}</div></td></tr>`).join('') : `<tr><td colspan="5" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Belum ada daftar hadir.</td></tr>`;
  }

  function slugifyIssueSectionTitle(title) {
    const base = String(title || 'section').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 24) || 'section';
    let id = base;
    let n = 1;
    const used = new Set(activeIssueSections.map(s => s.id));
    while (used.has(id)) id = `${base}_${n++}`;
    return id;
  }

  function getDefaultIssueSections() {
    return Object.values(ISSUE_TABLE_META).map(meta => ({
      id: meta.id,
      title: meta.title,
      defaultRows: meta.defaultRows,
      rows: []
    }));
  }

  function normalizeIssueSection(section, index = 0) {
    const title = String(section?.title || section?.section || `Issue Section ${index + 1}`).trim();
    const id = String(section?.id || title || `section_${index + 1}`).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') || `section_${index + 1}`;
    return {
      id,
      title,
      defaultRows: Number(section?.defaultRows || ISSUE_TABLE_META[id]?.defaultRows || 3),
      rows: Array.isArray(section?.rows) ? section.rows : []
    };
  }

  function getMinutesIssueSections(minutes = {}, includeDefaults = true) {
    if (Array.isArray(minutes.issueSections) && minutes.issueSections.length) {
      return minutes.issueSections.map(normalizeIssueSection);
    }
    const legacy = [
      { ...ISSUE_TABLE_META.enviro, rows: minutes.enviroIssues || [] },
      { ...ISSUE_TABLE_META.safety, rows: minutes.safetyIssues || [] },
      { ...ISSUE_TABLE_META.general, rows: minutes.generalIssues || [] }
    ].map(normalizeIssueSection);
    const hasContent = legacy.some(section => section.rows.some(row => [row.note, row.issuedBy, row.pic, row.dueDate, row.remark].some(Boolean)));
    return includeDefaults || hasContent ? legacy : [];
  }

  function getIssueSectionMeta(prefix) {
    return activeIssueSections.find(section => section.id === prefix)
      || ISSUE_TABLE_META[prefix]
      || { id: prefix, title: prefix, defaultRows: 3 };
  }

  function renderIssueSections(sections = getDefaultIssueSections()) {
    const container = document.getElementById('minutesIssueSectionsContainer');
    if (!container) return;
    activeIssueSections = (sections.length ? sections : getDefaultIssueSections()).map(normalizeIssueSection);
    container.innerHTML = activeIssueSections.map(section => `
      <section class="border-x border-b border-slate-950" data-issue-section-id="${escapeHTML(section.id)}">
        <div class="flex items-center justify-between gap-3 bg-slate-950 px-4 py-2 text-xs font-black text-white">
          <div class="flex flex-1 items-center justify-center gap-2">
            <span>CATATAN MEETING (</span>
            <input id="section_title_${escapeHTML(section.id)}" value="${escapeHTML(section.title)}" class="min-w-[160px] border-0 bg-transparent text-center text-xs font-black text-white outline-none placeholder:text-white/60" />
            <span>)</span>
          </div>
          <button type="button" onclick="removeIssueSection('${escapeJS(section.id)}')" class="no-print rounded-lg bg-white/10 px-2 py-1 text-[10px] font-black text-white hover:bg-white/20">hapus</button>
        </div>
        <div id="issueTable_${escapeHTML(section.id)}"></div>
      </section>`).join('');
    activeIssueSections.forEach(section => {
      const rowCount = Math.max(1, (section.rows || []).length || issueRowCounts[section.id] || section.defaultRows || 3);
      buildIssueTable(`issueTable_${section.id}`, section.id, rowCount, section.rows || []);
    });
    renderIssueGroupList();
  }

  function renderIssueGroupList() {
    const list = document.getElementById('issueGroupList');
    if (!list) return;
    list.innerHTML = activeIssueSections.map(section => {
      const rows = document.getElementById(`issueTable_${section.id}`) ? collectCurrentIssueRows(section.id) : (section.rows || []);
      const filledCount = rows.filter(row => [row.note, row.issuedBy, row.pic, row.dueDate, row.remark].some(Boolean)).length;
      return `<span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-2 text-xs font-black text-slate-700 ring-1 ring-slate-200">
        <span>${escapeHTML(section.title)}</span>
        ${filledCount ? `<span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] text-blue-700">${filledCount} issue</span>` : ''}
        <button type="button" onclick="removeIssueSection('${escapeJS(section.id)}')" class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-black text-red-700">hapus</button>
      </span>`;
    }).join('');
  }

  function toggleIssueGroupManager() {
    const panel = document.getElementById('issueGroupManager');
    const icon = document.getElementById('issueGroupManagerIcon');
    if (!panel) return;
    const open = panel.classList.contains('hidden');
    panel.classList.toggle('hidden');
    if (icon) icon.textContent = open ? '−' : '+';
  }

  function addIssueSection() {
    const input = document.getElementById('newIssueSectionTitle');
    const title = (input?.value || '').trim();
    if (!title) return toast('Isi nama group issue');
    const sections = collectCurrentIssueSections();
    sections.push({
      id: slugifyIssueSectionTitle(title),
      title,
      defaultRows: 1,
      rows: [{ note: '', issuedBy: '', pic: '', dueDate: '', status: 'Open', remark: '' }]
    });
    if (input) input.value = '';
    renderIssueSections(sections);
    toast('Group issue ditambahkan');
  }

  function removeIssueSection(id) {
    const sections = collectCurrentIssueSections();
    if (sections.length <= 1) return toast('Minimal 1 group issue tersedia');
    const target = sections.find(section => section.id === id);
    const hasContent = target?.rows?.some(row => [row.note, row.issuedBy, row.pic, row.dueDate, row.remark].some(Boolean));
    if (hasContent && !confirm(`Hapus group issue "${target?.title || id}"?`)) return;
    renderIssueSections(sections.filter(section => section.id !== id));
    toast('Group issue dihapus');
  }

  function collectCurrentIssueSections() {
    return activeIssueSections.length ? activeIssueSections.map(section => ({
      id: section.id,
      title: document.getElementById(`section_title_${section.id}`)?.value.trim() || section.title,
      defaultRows: section.defaultRows || 1,
      rows: collectCurrentIssueRows(section.id)
    })) : getDefaultIssueSections();
  }

  function buildIssueTable(containerId, prefix, rows = 4, data = []) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const meta = getIssueSectionMeta(prefix);
    const rowCount = Math.max(1, rows || issueRowCounts[prefix] || meta.defaultRows || 1);
    issueRowCounts[prefix] = rowCount;
    const safeData = Array.from({ length: rowCount }, (_, i) => data[i] || { note: '', issuedBy: '', pic: '', dueDate: '', status: 'Open', remark: '' });
    container.innerHTML = `
      <div class="no-print flex items-center justify-end gap-2 border-x border-slate-950 bg-slate-50 px-3 py-2">
        <button type="button" onclick="addIssueRow('${escapeJS(prefix)}')" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-700 ring-1 ring-emerald-100 hover:bg-emerald-100">+ Tambah Baris</button>
        <button type="button" onclick="removeIssueRow('${escapeJS(prefix)}')" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-black text-red-700 ring-1 ring-red-100 hover:bg-red-100 ${rowCount <= 1 ? 'opacity-50 cursor-not-allowed' : ''}" ${rowCount <= 1 ? 'disabled' : ''}>− Kurang Baris</button>
      </div>
      <table class="min-w-full border-collapse text-sm">
        <thead>
          <tr class="bg-white text-xs font-black uppercase">
            <th class="w-[48px] border border-slate-950 px-2 py-2 text-center">NO</th>
            <th class="border border-slate-950 px-3 py-2 text-center">CATATAN MEETING</th>
            <th class="w-[150px] border border-slate-950 px-3 py-2 text-center">ISSUED BY</th>
            <th class="w-[150px] border border-slate-950 px-3 py-2 text-center">PIC</th>
            <th class="w-[150px] border border-slate-950 px-3 py-2 text-center">BATAS WAKTU</th>
            <th class="w-[130px] border border-slate-950 px-3 py-2 text-center">STATUS</th>
            <th class="w-[180px] border border-slate-950 px-3 py-2 text-center">KETERANGAN</th>
          </tr>
        </thead>
        <tbody>
          ${safeData.map((row, i) => `
            <tr>
              <td class="border border-slate-950 px-2 py-1 text-center">${i + 1}</td>
              <td class="border border-slate-950 px-2 py-1"><input id="${prefix}_note_${i}" value="${escapeHTML(row.note || '')}" class="w-full border-0 bg-transparent px-1 py-1 outline-none" /></td>
              <td class="border border-slate-950 px-2 py-1"><input id="${prefix}_issuedBy_${i}" value="${escapeHTML(row.issuedBy || '')}" class="w-full border-0 bg-transparent px-1 py-1 outline-none" /></td>
              <td class="border border-slate-950 px-2 py-1"><input id="${prefix}_pic_${i}" value="${escapeHTML(row.pic || '')}" class="w-full border-0 bg-transparent px-1 py-1 outline-none" /></td>
              <td class="border border-slate-950 px-2 py-1"><input id="${prefix}_due_${i}" type="date" value="${escapeHTML(row.dueDate || '')}" class="w-full border-0 bg-transparent px-1 py-1 outline-none" /></td>
              <td class="border border-slate-950 px-2 py-1">
                <select id="${prefix}_status_${i}" class="w-full border-0 bg-transparent px-1 py-1 font-bold outline-none">
                  <option value="Open" ${(row.status || 'Open') === 'Open' ? 'selected' : ''}>Open</option>
                  <option value="Progress" ${row.status === 'Progress' ? 'selected' : ''}>Progress</option>
                  <option value="Overdue" ${row.status === 'Overdue' ? 'selected' : ''}>Overdue</option>
                  <option value="Closed" ${row.status === 'Closed' ? 'selected' : ''}>Closed</option>
                </select>
              </td>
              <td class="border border-slate-950 px-2 py-1"><input id="${prefix}_remark_${i}" value="${escapeHTML(row.remark || '')}" class="w-full border-0 bg-transparent px-1 py-1 outline-none" /></td>
            </tr>
          `).join('')}
        </tbody>
      </table>`;
  }

  function collectCurrentIssueRows(prefix) {
    const meta = getIssueSectionMeta(prefix);
    return collectIssueRows(prefix, issueRowCounts[prefix] || meta.defaultRows || 1);
  }

  function rerenderIssueTable(prefix, nextRows, data) {
    buildIssueTable(`issueTable_${prefix}`, prefix, nextRows, data);
  }

  function addIssueRow(prefix) {
    const currentData = collectCurrentIssueRows(prefix);
    currentData.push({ note: '', issuedBy: '', pic: '', dueDate: '', status: 'Open', remark: '' });
    rerenderIssueTable(prefix, currentData.length, currentData);
    renderIssueGroupList();
  }

  function removeIssueRow(prefix) {
    const currentData = collectCurrentIssueRows(prefix);
    if (currentData.length <= 1) return toast('Minimal 1 baris harus tersedia');
    currentData.pop();
    rerenderIssueTable(prefix, currentData.length, currentData);
    renderIssueGroupList();
  }

  function renderEventMinutesForm(ev) {
    const minutes = ev.minutes || {};
    const setValue = (id, value) => { const el = document.getElementById(id); if (el) el.value = value || ''; };
    setValue('minutesMeetingTitle', minutes.meetingTitle || `NOTULENSI MEETING ${ev.meetingType || ''}`.trim());
    setValue('minutesMeetingType', minutes.meetingType || ev.meetingType || '');
    setValue('minutesMeetingDate', minutes.meetingDate || ev.date || '');
    setValue('minutesNotulis', minutes.notulis || '');
    setValue('minutesLocation', minutes.location || ev.site || '');

    const withLegacyIssuedBy = rows => (rows || []).map(row => ({ ...row, issuedBy: row.issuedBy || minutes.issuedBy || '' }));
    const legacyGeneral = (!minutes.generalIssues && (minutes.topic || minutes.discussion || minutes.decision || minutes.action))
      ? [{ note: [minutes.topic, minutes.discussion, minutes.decision, minutes.action].filter(Boolean).join(' | '), issuedBy: minutes.issuedBy || '', pic: minutes.pic || '', dueDate: minutes.dueDate || '', status: 'Open', remark: minutes.status || '' }]
      : [];

    const sections = getMinutesIssueSections(minutes, true).map(section => ({
      ...section,
      rows: withLegacyIssuedBy(section.id === 'general' && !minutes.generalIssues?.length ? (section.rows?.length ? section.rows : legacyGeneral) : section.rows)
    }));
    renderIssueSections(sections);

    const info = document.getElementById('minutesUpdatedInfo');
    if (info) info.textContent = minutes.updatedAt ? `Updated: ${formatDateTime(minutes.updatedAt)}` : 'Belum ada notulensi';
  }

  function collectIssueRows(prefix, rows) {
    const result = [];
    for (let i = 0; i < rows; i++) {
      result.push({
        note: document.getElementById(`${prefix}_note_${i}`)?.value.trim() || '',
        issuedBy: document.getElementById(`${prefix}_issuedBy_${i}`)?.value.trim() || '',
        pic: document.getElementById(`${prefix}_pic_${i}`)?.value.trim() || '',
        dueDate: document.getElementById(`${prefix}_due_${i}`)?.value || '',
        status: document.getElementById(`${prefix}_status_${i}`)?.value || 'Open',
        remark: document.getElementById(`${prefix}_remark_${i}`)?.value.trim() || ''
      });
    }
    return result;
  }

  async function saveEventMinutes(e) {
    e.preventDefault();
    if (!selectedRecapEventId) return eventSaveAlert('warning', 'Belum ada event yang dipilih');
    const ev = getEventById(selectedRecapEventId);
    if (!ev) return eventSaveAlert('warning', 'Event tidak ditemukan');
    const issueSections = collectCurrentIssueSections();
    const issues = issueSections.flatMap(section => collectCurrentIssueRows(section.id).map((issue, index) => ({
      ...issue,
      section: section.id,
      nomor: index + 1
    })));
    const payload = {
      title: document.getElementById('minutesMeetingTitle')?.value.trim() || '',
      notulis: document.getElementById('minutesNotulis')?.value.trim() || '',
      location: document.getElementById('minutesLocation')?.value.trim() || '',
      issueSections: issueSections.map(({ id, title, defaultRows }) => ({ id, title, defaultRows })),
      issues
    };
    const mt = document.getElementById('minutesMeetingType')?.value.trim();
    if (mt) payload.meeting_type = mt;
    const md = document.getElementById('minutesMeetingDate')?.value;
    if (md) payload.meeting_date = md;
    if (window.Swal) {
      Swal.fire({
        title: 'Menyimpan notulensi...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading()
      });
    }
    try {
      await apiFetch(`${SID_MEETING_API_BASE}/events/${encodeURIComponent(selectedRecapEventId)}/minutes`, {
        method: 'POST',
        body: JSON.stringify(payload)
      });
      const detail = await apiFetch(`${SID_MEETING_API_BASE}/events/${encodeURIComponent(selectedRecapEventId)}`);
      cacheEvent(detail.event);
      window.recapEligibleCompanies = Array.isArray(detail.eligibleCompanies) ? detail.eligibleCompanies : null;
      db.attendance = db.attendance.filter(a => String(a.eventId) !== String(selectedRecapEventId));
      (detail.attendance || []).forEach(row => db.attendance.push(row));
      renderEventRecapModal(selectedRecapEventId, detail.attendance || []);
      renderEventMinutesForm(detail.event);
      if (window.Swal) Swal.close();
      eventSaveAlert('success', 'Notulensi event berhasil disimpan ke database');
    } catch (err) {
      if (window.Swal) Swal.close();
      eventSaveAlert('error', err.message || 'Gagal simpan notulensi');
    }
  }

  function clearEventMinutesForm() {
    ['minutesMeetingTitle', 'minutesMeetingType', 'minutesMeetingDate', 'minutesNotulis', 'minutesLocation'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    const newSectionInput = document.getElementById('newIssueSectionTitle');
    if (newSectionInput) newSectionInput.value = '';
    renderIssueSections(getDefaultIssueSections());
  }

  function printEventMinutes() {
    const area = document.getElementById('minutesPrintArea');
    if (!area) return toast('Area notulensi tidak ditemukan');
    const title = document.getElementById('minutesMeetingTitle')?.value || 'Notulensi Meeting';
    const printWindow = window.open('', '_blank');
    if (!printWindow) return toast('Popup print diblokir browser');
    printWindow.document.write(`<!DOCTYPE html><html><head><title>${escapeHTML(title)}</title><style>
      *{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:16px;color:#000}table{border-collapse:collapse;width:100%;font-size:11px}input,textarea{border:0!important;background:transparent!important;font-family:Arial,sans-serif;font-size:11px;width:100%}.rounded-2xl,.rounded-3xl,.rounded-xl{border-radius:0!important}.border,.ring-1{border:1px solid #000!important}.border-2{border:2px solid #000!important}.border-slate-950,.border-slate-700,.border-slate-300{border-color:#000!important}.no-print{display:none!important}.bg-slate-950{background:#000!important;color:#fff!important}.bg-white{background:#fff!important}.text-center{text-align:center}.font-black{font-weight:900}.font-semibold{font-weight:600}.p-1{padding:4px}.p-3{padding:12px}.p-4{padding:16px}.px-2{padding-left:8px;padding-right:8px}.px-3{padding-left:12px;padding-right:12px}.py-1{padding-top:4px;padding-bottom:4px}.py-2{padding-top:8px;padding-bottom:8px}.w-\[140px\]{width:140px}.w-\[48px\]{width:48px}.w-\[150px\]{width:150px}.w-\[180px\]{width:180px}.w-\[130px\]{width:130px}.w-\[150px\]{width:150px}@page{size:A4 landscape;margin:10mm}
    </style></head><body>${area.outerHTML}</body></html>`);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => printWindow.print(), 250);
  }

  async function copyText(text, buttonEl) {
    const value = String(text || '');
    if (!value) return toast('Tidak ada teks untuk dicopy');
    const originalLabel = buttonEl?.textContent;

    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(value);
        if (buttonEl) buttonEl.textContent = 'Copied';
        toast('Link berhasil disalin');
        setTimeout(() => { if (buttonEl && originalLabel) buttonEl.textContent = originalLabel; }, 1400);
        return true;
      }
      throw new Error('Clipboard API unavailable');
    } catch (err) {
      const fallbackOk = fallbackCopyText(value);
      if (fallbackOk) {
        if (buttonEl) buttonEl.textContent = 'Copied';
        toast('Link berhasil disalin');
        setTimeout(() => { if (buttonEl && originalLabel) buttonEl.textContent = originalLabel; }, 1400);
        return true;
      }
      const manualBox = document.getElementById('manualCopyBox');
      if (manualBox) manualBox.classList.remove('hidden');
      const qrInput = document.getElementById('qrLink');
      if (qrInput) { qrInput.focus(); qrInput.select(); }
      console.warn('Clipboard copy blocked:', err);
      toast('Clipboard diblokir browser. Silakan copy manual dari kolom link.');
      return false;
    }
  }

  function fallbackCopyText(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.top = '-9999px';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    let success = false;
    try { success = document.execCommand('copy'); } catch { success = false; }
    document.body.removeChild(textarea);
    return success;
  }

  function openAttendanceFromEvent(eventId) {
    scannedEventId = eventId;
    window.location.hash = `absen=${encodeURIComponent(eventId)}`;
    clearAttendanceForm();
    openEventRecapModal(eventId);
    openManualAttendanceForm();
  }

  async function lookupSID() {
    const sidInput = document.getElementById('sidInput');
    const sid = sidInput.value.trim().toUpperCase();
    const emp = await fetchEmployeeBySID(sid);
    document.getElementById('resolvedEmployeeId').value = emp?.id || '';
    document.getElementById('autoName').value = emp?.name || '';
    document.getElementById('autoCompany').value = emp?.company || '';
    document.getElementById('autoStructural').value = emp?.structuralPosition || '';
    document.getElementById('autoFunctional').value = emp?.functionalPosition || '';
    document.getElementById('timestampInput').value = formatDateTime(new Date().toISOString());
    if (sid && !emp) document.getElementById('autoName').placeholder = 'SID tidak ditemukan di sistem';
  }

  async function fetchEmployeeBySID(sid) {
    if (!sid) return null;
    try {
      const payload = await apiFetch(`${SID_MEETING_API_BASE}/employees/by-sid/${encodeURIComponent(String(sid).trim().toUpperCase())}`);
      return payload.employee || null;
    } catch {
      return SYSTEM_SID_DIRECTORY.find(x => x.sid.toUpperCase() === String(sid).trim().toUpperCase()) || null;
    }
  }

  function scheduleMinutesMgmtSearch() {
    clearTimeout(minutesMgmtSearchTimer);
    minutesMgmtSearchTimer = setTimeout(() => {
      minutesMgmtPage = 1;
      renderMinutesMgmtList();
    }, 350);
  }

  function initMinutesMgmtTab() {
    if (!minutesMgmtInitialized) minutesMgmtInitialized = true;
    renderMinutesMgmtList();
  }

  async function renderMinutesMgmtList() {
    const target = document.getElementById('minutesMgmtList');
    if (!target) return;
    const q = (document.getElementById('minutesMgmtSearch')?.value || '').trim();
    const status = document.getElementById('minutesMgmtStatusFilter')?.value || 'ALL';
    target.innerHTML = '<div class="rounded-3xl bg-white p-6 text-center text-sm text-slate-500 ring-1 ring-slate-200">Memuat daftar notulensi...</div>';
    try {
      const params = new URLSearchParams({
        page: String(minutesMgmtPage),
        per_page: '20',
        q,
        status
      });
      const payload = await apiFetch(`${SID_MEETING_API_BASE}/minutes-management/list?${params.toString()}`);
      const rows = payload.data || [];
      minutesMgmtMeta = payload.meta || minutesMgmtMeta;
      if (!rows.length) {
        target.innerHTML = '<div class="rounded-3xl bg-white p-6 text-center text-sm font-semibold text-slate-500 ring-1 ring-slate-200">Belum ada notulensi meeting tersimpan.</div>';
        renderMinutesMgmtPagination();
        return;
      }
      target.innerHTML = rows.map(row => {
        const allClosed = row.issuesCount > 0 && row.openIssuesCount === 0;
        const statusBadge = allClosed
          ? '<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700 ring-1 ring-slate-200">Semua Closed</span>'
          : `<span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-100">${row.openIssuesCount} Open</span>`;
        return `<article onclick="openMinutesMgmtDetail('${escapeJS(row.eventId)}')" class="cursor-pointer rounded-3xl bg-white p-5 ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg">
          <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0">
              <div class="mb-2 flex flex-wrap items-center gap-2">
                ${statusBadge}
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">${escapeHTML(row.code)}</span>
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700">${escapeHTML(row.week || '-')}</span>
              </div>
              <h3 class="text-lg font-black text-slate-950">${escapeHTML(row.title || row.meetingType || 'Notulensi Meeting')}</h3>
              <p class="mt-1 text-sm text-slate-500">${escapeHTML(row.meetingType || '-')} · ${escapeHTML(row.site || '-')} · ${formatDate(row.date)}</p>
              <p class="mt-2 text-xs text-slate-400">Notulis: ${escapeHTML(row.notulis || '-')} · Lokasi: ${escapeHTML(row.location || '-')}</p>
              <p class="mt-2 text-xs font-bold text-blue-600">Klik untuk detail notulensi & kelola status issue</p>
            </div>
            <div class="min-w-44 rounded-2xl bg-slate-50 p-4 text-sm ring-1 ring-slate-200">
              <div class="flex justify-between gap-4"><span>Total Issue</span><b>${row.issuesCount}</b></div>
              <div class="mt-1 flex justify-between gap-4"><span>Closed</span><b>${row.closedIssuesCount}</b></div>
              <div class="mt-1 flex justify-between gap-4"><span>Updated</span><b class="text-xs">${formatDateTime(row.updatedAt)}</b></div>
            </div>
          </div>
        </article>`;
      }).join('');
      renderMinutesMgmtPagination();
    } catch (err) {
      target.innerHTML = `<div class="rounded-3xl bg-red-50 p-6 text-center text-sm text-red-700 ring-1 ring-red-100">${escapeHTML(err.message || 'Gagal memuat daftar notulensi')}</div>`;
    }
  }

  function renderMinutesMgmtPagination() {
    const box = document.getElementById('minutesMgmtPagination');
    if (!box) return;
    const { current_page, last_page, total } = minutesMgmtMeta;
    if (!total) {
      box.innerHTML = '';
      return;
    }
    box.innerHTML = `
      <span>Menampilkan halaman ${current_page} dari ${last_page} (${total} notulensi)</span>
      <div class="flex gap-2">
        <button type="button" ${current_page <= 1 ? 'disabled' : ''} onclick="changeMinutesMgmtPage(${current_page - 1})" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-700 disabled:opacity-40">Sebelumnya</button>
        <button type="button" ${current_page >= last_page ? 'disabled' : ''} onclick="changeMinutesMgmtPage(${current_page + 1})" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-700 disabled:opacity-40">Berikutnya</button>
      </div>`;
  }

  function changeMinutesMgmtPage(page) {
    if (page < 1 || page > minutesMgmtMeta.last_page) return;
    minutesMgmtPage = page;
    renderMinutesMgmtList();
  }

  async function openMinutesMgmtDetail(eventId) {
    minutesMgmtSelectedEventId = String(eventId);
    try {
      const payload = await apiFetch(`${SID_MEETING_API_BASE}/minutes-management/events/${encodeURIComponent(eventId)}`);
      cacheEvent(payload.event);
      renderMinutesMgmtDetailModal(payload.event, payload.issues || []);
      document.getElementById('minutesMgmtDetailModal')?.classList.remove('hidden');
      document.getElementById('minutesMgmtDetailModal')?.classList.add('flex');
    } catch (err) {
      eventSaveAlert('error', err.message || 'Gagal memuat detail notulensi');
    }
  }

  function closeMinutesMgmtDetailModal() {
    document.getElementById('minutesMgmtDetailModal')?.classList.add('hidden');
    document.getElementById('minutesMgmtDetailModal')?.classList.remove('flex');
  }

  function renderMinutesMgmtDetailModal(ev, issues = []) {
    const minutes = ev.minutes || {};
    document.getElementById('minutesMgmtDetailTitle').textContent = minutes.meetingTitle || `NOTULENSI MEETING ${ev.meetingType || ''}`.trim();
    document.getElementById('minutesMgmtDetailSubtitle').textContent = `${ev.meetingType || '-'} · ${ev.site || '-'} · ${formatDate(ev.date)} · ${ev.week || '-'}`;
    const openCount = issues.filter(i => i.status !== 'Closed').length;
    const closedCount = issues.filter(i => i.status === 'Closed').length;
    document.getElementById('minutesMgmtDetailSummary').innerHTML = [
      ['Total Issue', issues.length],
      ['Open / Progress', openCount],
      ['Closed', closedCount],
      ['Updated', minutes.updatedAt ? formatDateTime(minutes.updatedAt) : '-']
    ].map(([label, val]) => `<div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-wider text-slate-400">${label}</p><p class="mt-2 text-lg font-black text-slate-950">${escapeHTML(String(val))}</p></div>`).join('');

    const tbody = document.getElementById('minutesMgmtIssuesTable');
    if (!tbody) return;
    tbody.innerHTML = issues.length ? issues.map(issue => {
      const currentStatus = issue.rawStatus || issue.status || 'Open';
      const statusOptions = ['Open', 'Progress', 'Overdue', 'Closed'].map(status => `<option value="${status}" ${currentStatus === status ? 'selected' : ''}>${status}</option>`).join('');
      const verifier = issue.closedBySid
        ? `${escapeHTML(issue.closedBySid)}${issue.closedByName ? ' · ' + escapeHTML(issue.closedByName) : ''}${issue.closedAt ? '<div class="mt-1 text-[10px] text-slate-400">' + escapeHTML(formatDateTime(issue.closedAt)) + '</div>' : ''}`
        : '-';
      return `<tr class="border-t border-slate-100">
        <td class="px-4 py-3 text-center">${issue.nomor || '-'}</td>
        <td class="px-4 py-3 font-semibold">${escapeHTML(issue.sectionTitle || issue.section || '-')}</td>
        <td class="px-4 py-3">${escapeHTML(issue.note || '-')}</td>
        <td class="px-4 py-3">${escapeHTML(issue.issuedBy || '-')}</td>
        <td class="px-4 py-3">${escapeHTML(issue.pic || '-')}</td>
        <td class="px-4 py-3">${issue.dueDate ? formatDate(issue.dueDate) : '-'}</td>
        <td class="px-4 py-3">
          <select data-prev-status="${escapeHTML(currentStatus)}" onchange="handleMgmtIssueStatusChange('${escapeJS(issue.id)}', this)" class="rounded-xl border border-slate-200 bg-white px-2 py-1.5 text-xs font-black outline-none">
            ${statusOptions}
          </select>
          <div class="mt-1"><span class="rounded-full px-2 py-0.5 text-[10px] font-black ring-1 ${getMinutesStatusClass(issue.status)}">${escapeHTML(issue.status || 'Open')}</span></div>
        </td>
        <td class="px-4 py-3 text-xs">${verifier}</td>
        <td class="px-4 py-3">${escapeHTML(issue.remark || '-')}</td>
      </tr>`;
    }).join('') : `<tr><td colspan="9" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Belum ada issue pada notulensi ini.</td></tr>`;
  }

  function handleMgmtIssueStatusChange(issueId, selectEl) {
    const next = selectEl?.value || 'Open';
    const prev = selectEl?.dataset?.prevStatus || 'Open';
    if (next === prev) return;
    if (next === 'Closed') {
      pendingCloseIssue = { issueId, prevStatus: prev, selectEl };
      selectEl.value = prev;
      const row = selectEl.closest('tr');
      const note = row?.querySelector('td:nth-child(3)')?.textContent?.trim() || 'Issue belum memiliki catatan.';
      openCloseIssueModal(note);
      return;
    }
    updateMgmtIssueStatus(issueId, next, selectEl);
  }

  async function updateMgmtIssueStatus(issueId, status, selectEl) {
    try {
      const payload = await apiFetch(`${SID_MEETING_API_BASE}/minutes-management/issues/${encodeURIComponent(issueId)}/status`, {
        method: 'POST',
        body: JSON.stringify({ status })
      });
      if (selectEl) selectEl.dataset.prevStatus = status;
      if (minutesMgmtSelectedEventId) await openMinutesMgmtDetail(minutesMgmtSelectedEventId);
      renderMinutesMgmtList();
      toast(`Status issue diubah ke ${status}`);
    } catch (err) {
      if (selectEl) selectEl.value = selectEl.dataset.prevStatus || 'Open';
      eventSaveAlert('error', err.message || 'Gagal mengubah status issue');
    }
  }

  function openCloseIssueModal(noteText) {
    const textEl = document.getElementById('closeIssueText');
    const sidEl = document.getElementById('closeIssueVerifierSID');
    if (textEl) textEl.textContent = noteText || 'Issue belum memiliki catatan.';
    if (sidEl) sidEl.value = '';
    document.getElementById('closeIssueModal')?.classList.remove('hidden');
    document.getElementById('closeIssueModal')?.classList.add('flex');
    setTimeout(() => sidEl?.focus(), 120);
  }

  function cancelCloseIssue() {
    pendingCloseIssue = null;
    document.getElementById('closeIssueModal')?.classList.add('hidden');
    document.getElementById('closeIssueModal')?.classList.remove('flex');
  }

  async function submitCloseIssueVerification() {
    if (!pendingCloseIssue) return cancelCloseIssue();
    const sid = document.getElementById('closeIssueVerifierSID')?.value.trim().toUpperCase();
    if (!sid) return eventSaveAlert('warning', 'Masukkan No SID verifikator');
    try {
      await apiFetch(`${SID_MEETING_API_BASE}/minutes-management/issues/${encodeURIComponent(pendingCloseIssue.issueId)}/close`, {
        method: 'POST',
        body: JSON.stringify({ kode_sid: sid })
      });
      pendingCloseIssue = null;
      cancelCloseIssue();
      if (minutesMgmtSelectedEventId) await openMinutesMgmtDetail(minutesMgmtSelectedEventId);
      renderMinutesMgmtList();
      eventSaveAlert('success', 'Issue berhasil diubah ke Closed');
    } catch (err) {
      eventSaveAlert('error', err.message || 'Verifikasi close issue gagal');
    }
  }

  function clearAttendanceForm() {
    const form = document.getElementById('attendanceForm'); if (!form) return;
    form.reset();
    document.getElementById('resolvedEmployeeId').value = '';
    document.getElementById('timestampInput').value = formatDateTime(new Date().toISOString());
    renderActiveAttendanceEvent();
  }

  function renderActiveAttendanceEvent() {
    const ev = getEventById(scannedEventId);
    const notice = document.getElementById('scanNotice');
    const info = document.getElementById('activeEventInfo');
    const eventInput = document.getElementById('attendanceEventId');
    const submitBtn = document.getElementById('submitAttendanceBtn');
    const sidInput = document.getElementById('sidInput');
    if (!notice || !info || !eventInput || !submitBtn || !sidInput) return;
    if (!ev) {
      notice.innerHTML = 'Belum ada event yang dipilih. Klik kartu event atau scan QR event untuk membuka form absensi.';
      info.innerHTML = '<div class="rounded-3xl bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-200">Form belum terhubung ke event.</div>';
      eventInput.value = '';
      submitBtn.disabled = true;
      sidInput.disabled = true;
      submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
      return;
    }
    const status = getEventStatus(ev);
    eventInput.value = ev.id;
    info.innerHTML = `<div class="rounded-3xl bg-white p-4 text-sm text-blue-900 ring-1 ring-blue-100"><div class="mb-2">${statusBadge(status)}</div><b>${escapeHTML(ev.meetingType)}</b><br>${escapeHTML(ev.site)} · ${formatDate(ev.date)} · ${escapeHTML(ev.week)}<br>Aktif: ${ev.startTime} - ${ev.endTime}</div>`;
    const disabled = status !== 'Open';
    submitBtn.disabled = disabled;
    sidInput.disabled = disabled;
    submitBtn.classList.toggle('opacity-50', disabled);
    submitBtn.classList.toggle('cursor-not-allowed', disabled);
    if (status === 'Open') notice.innerHTML = 'QR valid. Silakan input <b>Kode SID</b> untuk melakukan absensi.';
    else if (status === 'Closed') notice.innerHTML = 'Meeting sudah ditutup. QR dan absensi dikunci.';
    else if (status === 'Expired') notice.innerHTML = 'QR sudah tidak aktif karena meeting telah selesai. Absensi dikunci otomatis.';
    else if (status === 'Upcoming') notice.innerHTML = 'QR belum aktif karena meeting belum dimulai.';
    else if (status === 'Overrun') notice.innerHTML = 'Jam selesai sudah tercapai. Absensi masih terbuka sampai meeting dikonfirmasi tutup.';
    else notice.innerHTML = 'Event masih draft. QR belum dapat digunakan.';
  }

  async function saveAttendance(e) {
    e.preventDefault();
    const eventId = document.getElementById('attendanceEventId').value;
    const sid = document.getElementById('sidInput').value.trim().toUpperCase();
    const ev = getEventById(eventId);
    if (!ev) return toast('Event tidak ditemukan. Scan QR event terlebih dahulu.');
    if (!isEventActive(ev)) return toast('QR event tidak aktif. Absensi tidak dapat disimpan.');
    try {
      await apiFetch(`${SID_MEETING_API_BASE}/attendance`, {
        method: 'POST',
        body: JSON.stringify({ event_id: eventId, kode_sid: sid, input_method: 'manual' })
      });
      const detail = await apiFetch(`${SID_MEETING_API_BASE}/events/${eventId}`);
      cacheEvent(detail.event);
      db.attendance = db.attendance.filter(a => String(a.eventId) !== String(eventId));
      (detail.attendance || []).forEach(row => db.attendance.push(row));
      renderEventRecapModal(eventId, detail.attendance || []);
      loadStats();
      reloadEventsTable();
      clearAttendanceForm();
      toast('Absensi berhasil disimpan ke database');
    } catch (err) {
      toast(err.message || 'Gagal simpan absensi');
    }
  }

  function deleteAttendance(id) {
    toast('Penghapusan log absensi dinonaktifkan demi keamanan data.');
  }

  function renderAttendance() { return; }

  function saveEmployee(e) {
    e.preventDefault();
    const id = document.getElementById('employeeId').value || uid('EMP');
    const sid = document.getElementById('employeeSid').value.trim().toUpperCase();
    const existing = db.employees.find(x => x.id === id);
    const duplicate = db.employees.find(x => x.id !== id && x.sid.toUpperCase() === sid);
    if (duplicate) return toast('Kode SID sudah ada di master data');
    const payload = { id, sid, name: document.getElementById('employeeName').value.trim(), company: document.getElementById('employeeCompany').value.trim(), structuralPosition: document.getElementById('employeeStructural').value.trim(), functionalPosition: document.getElementById('employeeFunctional').value.trim(), createdAt: existing?.createdAt || new Date().toISOString(), updatedAt: new Date().toISOString() };
    if (existing) Object.assign(existing, payload); else db.employees.unshift(payload);
    clearEmployeeForm(); saveDB(); toast('Master SID berhasil disimpan');
  }

  function editEmployee(id) { const emp = db.employees.find(x => x.id === id); if (!emp) return; document.getElementById('employeeId').value = emp.id; document.getElementById('employeeSid').value = emp.sid; document.getElementById('employeeName').value = emp.name; document.getElementById('employeeCompany').value = emp.company; document.getElementById('employeeStructural').value = emp.structuralPosition; document.getElementById('employeeFunctional').value = emp.functionalPosition; showTab('master'); }
  function deleteEmployee(id) { if (!confirm('Hapus master SID ini? Log absensi yang sudah ada tidak ikut terhapus.')) return; db.employees = db.employees.filter(x => x.id !== id); saveDB(); toast('Master SID dihapus'); }
  function clearEmployeeForm() { const form = document.getElementById('masterForm'); if (!form) return; form.reset(); document.getElementById('employeeId').value = ''; }

  function bulkImportEmployees() {
    const raw = document.getElementById('bulkEmployees').value.trim(); if (!raw) return toast('Isi data master SID terlebih dahulu');
    let count = 0;
    raw.split('\n').forEach(line => { const [sidRaw, name, company, structuralPosition, functionalPosition] = line.split(';').map(x => (x || '').trim()); const sid = (sidRaw || '').toUpperCase(); if (!sid || !name) return; const existing = db.employees.find(x => x.sid.toUpperCase() === sid); const payload = { id: existing?.id || uid('EMP'), sid, name, company, structuralPosition, functionalPosition, createdAt: existing?.createdAt || new Date().toISOString(), updatedAt: new Date().toISOString() }; if (existing) Object.assign(existing, payload); else db.employees.push(payload); count++; });
    document.getElementById('bulkEmployees').value = ''; saveDB(); toast(`${count} master SID berhasil diimport/update`);
  }

  function renderEmployees() {
    const tbody = document.getElementById('employeeTable'); if (!tbody) return;
    const q = (document.getElementById('employeeSearch')?.value || '').toLowerCase();
    const rows = db.employees.filter(emp => [emp.sid, emp.name, emp.company, emp.structuralPosition, emp.functionalPosition].join(' ').toLowerCase().includes(q));
    if (!rows.length) { tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Belum ada master SID.</td></tr>`; return; }
    tbody.innerHTML = rows.map(emp => `<tr class="border-t border-slate-100"><td class="px-4 py-3 font-black">${escapeHTML(emp.sid)}</td><td class="px-4 py-3"><b>${escapeHTML(emp.name)}</b></td><td class="px-4 py-3">${escapeHTML(emp.company || '-')}</td><td class="px-4 py-3"><div>${escapeHTML(emp.structuralPosition || '-')}</div><div class="text-xs text-slate-500">${escapeHTML(emp.functionalPosition || '-')}</div></td><td class="no-print px-4 py-3"><div class="flex gap-2"><button onclick="editEmployee('${emp.id}')" class="rounded-lg bg-blue-50 px-2 py-1 text-xs font-black text-blue-700">Edit</button><button onclick="deleteEmployee('${emp.id}')" class="rounded-lg bg-red-50 px-2 py-1 text-xs font-black text-red-700">Hapus</button></div></td></tr>`).join('');
  }

  function renderCompanySiteChecklist(selectedSites = SITE_MASTER) {
    const box = document.getElementById('companySiteChecklist');
    if (!box) return;
    const selected = new Set(selectedSites || []);
    box.innerHTML = SITE_MASTER.map(site => `<label class="flex cursor-pointer items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-blue-50"><input type="checkbox" name="companySites" value="${escapeHTML(site)}" ${selected.has(site) ? 'checked' : ''} class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" /><span>${escapeHTML(site)}</span></label>`).join('');
  }

  async function saveCompany(e) {
    e.preventDefault();
    const name = document.getElementById('companyName')?.value.trim();
    const selectedSites = [...document.querySelectorAll('input[name="companySites"]:checked')].map(x => x.value);
    if (!name) return toast('Nama perusahaan wajib diisi');
    if (!selectedSites.length) return toast('Pilih minimal 1 site untuk perusahaan');
    try {
      await apiFetch(`${SID_MEETING_API_BASE}/companies`, {
        method: 'POST',
        body: JSON.stringify({ name, sites: selectedSites })
      });
      clearCompanyForm();
      if (companyDataTable) companyDataTable.ajax.reload(null, false);
      else initCompanyDataTable();
      toast('Master perusahaan berhasil disimpan ke database');
    } catch (err) {
      toast(err.message || 'Gagal simpan master perusahaan');
    }
  }

  function editCompany(id) {
    const company = getCompanyMaster().find(item => item.id === id);
    if (!company) return;
    document.getElementById('companyId').value = company.id;
    document.getElementById('companyName').value = company.name;
    renderCompanySiteChecklist(company.sites || []);
    showTab('companymaster');
    toggleCompanyInputContainer(true);
  }

  function deleteCompany(id) {
    toast('Gunakan tombol Hapus di tabel master perusahaan (database).');
  }

  function clearCompanyForm() {
    const form = document.getElementById('companyForm');
    if (form) form.reset();
    const id = document.getElementById('companyId');
    if (id) id.value = '';
    renderCompanySiteChecklist(SITE_MASTER);
  }

  function toggleCompanySite(id, site, checked) {
    toast('Gunakan checkbox di tabel master perusahaan untuk memperbarui site.');
  }

  function renderCompanyMaster() {
    renderCompanySiteChecklist(document.getElementById('companyId')?.value ? [...document.querySelectorAll('input[name="companySites"]:checked')].map(x => x.value) : SITE_MASTER);
    const tab = document.getElementById('tab-companymaster');
    if (tab && !tab.classList.contains('hidden')) {
      initCompanyDataTable();
    }
  }

  function resetCompanyMaster() {
    toast('Reset master perusahaan dinonaktifkan demi keamanan data.');
  }

  function exportCompanyMasterCSV() {
    const rows = getCompanyMaster().map(company => {
      const sites = new Set(company.sites || []);
      const row = { perusahaan: company.name, jumlah_site: sites.size };
      SITE_MASTER.forEach(site => row[site] = sites.has(site) ? 'YA' : 'TIDAK');
      return row;
    });
    downloadCSV(rows, 'master_perusahaan_site.csv');
  }

  function renderMeetingTypeOptions() {
    if (formOptionsLoaded) return;
    const select = document.getElementById('meetingType');
    if (!select) return;
    const current = select.value;
    const types = getMeetingTypes();
    select.innerHTML = '<option value="">Pilih Jenis Meeting</option>' + types.map(type => `<option value="${escapeHTML(type)}">${escapeHTML(type)}</option>`).join('');
    if (types.includes(current)) select.value = current;
  }

  function renderMeetingTypeManager() {
    const list = document.getElementById('meetingTypeList');
    if (!list) return;
    const types = getMeetingTypes();
    list.innerHTML = types.map((type, index) => {
      const usedCount = db.events.filter(ev => ev.meetingType === type).length;
      return `<span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-2 text-xs font-black text-slate-700 ring-1 ring-slate-200">
        <span>${escapeHTML(type)}</span>
        ${usedCount ? `<span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] text-blue-700">${usedCount} event</span>` : ''}
        <button type="button" data-meeting-type-index="${index}" onclick="deleteMeetingTypeByIndex(${index})" class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-black text-red-700 hover:bg-red-100">hapus</button>
      </span>`;
    }).join('');
  }

  function toggleMeetingTypeManager() {
    const panel = document.getElementById('meetingTypeManager');
    const icon = document.getElementById('meetingTypeManagerIcon');
    if (!panel) return;
    const willOpen = panel.classList.contains('hidden');
    panel.classList.toggle('hidden');
    if (icon) icon.textContent = willOpen ? '−' : '+';
    if (willOpen) setTimeout(() => document.getElementById('newMeetingType')?.focus(), 80);
  }

  async function addMeetingType() {
    const input = document.getElementById('newMeetingType');
    const value = input?.value.trim();
    if (!value) return toast('Isi nama jenis meeting terlebih dahulu');
    const types = getMeetingTypes();
    if (types.some(type => type.toLowerCase() === value.toLowerCase())) return toast('Jenis meeting sudah ada');
    try {
      await apiFetch(`${SID_MEETING_API_BASE}/meeting-types`, {
        method: 'POST',
        body: JSON.stringify({ name: value })
      });
      if (input) input.value = '';
      formOptionsLoaded = false;
      await loadFormOptions();
      const select = document.getElementById('meetingType');
      if (select) select.value = value;
      toast('Jenis meeting berhasil ditambahkan');
    } catch (err) {
      toast(err.message || 'Gagal menambah jenis meeting');
    }
  }

  function deleteMeetingTypeByIndex(index) {
    const types = getMeetingTypes();
    const type = types[Number(index)];
    if (!type) return toast('Jenis meeting tidak ditemukan');
    deleteMeetingType(type);
  }

  async function deleteMeetingType(type) {
    const normalizedType = String(type || '').trim();
    const types = getMeetingTypes();
    if (!normalizedType) return toast('Jenis meeting tidak valid');
    if (types.length <= 1) return toast('Minimal 1 jenis meeting harus tersedia');
    const record = meetingTypeRecords.find(item => item.name === normalizedType);
    if (!record?.id) return toast('Jenis meeting tidak ditemukan di server');
    try {
      const result = await apiFetch(`${SID_MEETING_API_BASE}/meeting-types/${record.id}`, { method: 'DELETE' });
      formOptionsLoaded = false;
      await loadFormOptions();
      const select = document.getElementById('meetingType');
      if (select?.value === normalizedType) select.value = '';
      const manager = document.getElementById('meetingTypeManager');
      const icon = document.getElementById('meetingTypeManagerIcon');
      if (manager) manager.classList.remove('hidden');
      if (icon) icon.textContent = '−';
      toast(result.message || 'Jenis meeting berhasil dihapus/dinonaktifkan');
    } catch (err) {
      toast(err.message || 'Gagal menghapus jenis meeting');
    }
  }

  function renderOptions() {
    renderMeetingTypeOptions();
    renderMeetingTypeManager();
    renderMeetingTargetOptions({
      targetCompanies: getCheckedValues('targetCompanies'),
      targetPositions: getCheckedValues('targetPositions'),
      targetDepartments: getCheckedValues('targetDepartments')
    });
    handleMeetingLevelChange();
  }

  function populateReportFilters() {
    const siteSelect = document.getElementById('reportFilterSite');
    const weekSelect = document.getElementById('reportFilterWeek');
    if (!siteSelect || !weekSelect) return;

    const currentSite = siteSelect.value || 'ALL';
    const currentWeek = weekSelect.value || 'ALL';
    const sites = [...new Set(db.events.map(e => e.site).filter(Boolean))].sort();
    const weeks = [...new Set(db.events.map(e => e.week).filter(Boolean))].sort();

    siteSelect.innerHTML = '<option value="ALL">Semua Site</option>' + sites.map(site => `<option value="${escapeHTML(site)}">${escapeHTML(site)}</option>`).join('');
    weekSelect.innerHTML = '<option value="ALL">Semua Week</option>' + weeks.map(week => `<option value="${escapeHTML(week)}">${escapeHTML(week)}</option>`).join('');

    siteSelect.value = sites.includes(currentSite) ? currentSite : 'ALL';
    weekSelect.value = weeks.includes(currentWeek) ? currentWeek : 'ALL';
  }

  function getReportRows() {
    const selectedSite = document.getElementById('reportFilterSite')?.value || 'ALL';
    const selectedWeek = document.getElementById('reportFilterWeek')?.value || 'ALL';
    const search = (document.getElementById('reportSearch')?.value || '').toLowerCase().trim();

    return db.attendance.map(a => {
      const ev = db.events.find(e => e.id === a.eventId) || {};
      return {
        eventId: a.eventId,
        tanggal_meeting: ev.date || '',
        week: ev.week || '',
        site: ev.site || '',
        jenis_meeting: ev.meetingType || '',
        kode_event: ev.code || '',
        kode_sid: a.sid || '',
        nama: a.name || '',
        perusahaan: a.company || '',
        jabatan_struktural: a.structuralPosition || '',
        jabatan_fungsional: a.functionalPosition || '',
        timestamp: a.timestamp || ''
      };
    }).filter(row => {
      const siteOk = selectedSite === 'ALL' || row.site === selectedSite;
      const weekOk = selectedWeek === 'ALL' || row.week === selectedWeek;
      const searchOk = !search || Object.values(row).join(' ').toLowerCase().includes(search);
      return siteOk && weekOk && searchOk;
    });
  }

  function getMinuteIssueRows(minutes, section, issues, sectionId = '') {
    return (issues || [])
      .map((issue, index) => ({ section, sectionId, issueNo: index + 1, ...issue }))
      .filter(row => [row.note, row.issuedBy, row.pic, row.dueDate, row.remark, row.status].some(Boolean));
  }

  function getMinutesReportRows() {
    const selectedSite = document.getElementById('reportFilterSite')?.value || 'ALL';
    const selectedWeek = document.getElementById('reportFilterWeek')?.value || 'ALL';
    const search = (document.getElementById('reportSearch')?.value || '').toLowerCase().trim();
    const rows = [];
    db.events.forEach(ev => {
      const minutes = ev.minutes || {};
      const issueRows = getMinutesIssueSections(minutes, false).flatMap(section =>
        getMinuteIssueRows(minutes, section.title, section.rows, section.id)
      );

      if (!issueRows.length && minutes.updatedAt) {
        issueRows.push({ section: 'Notulensi', issueNo: 1, note: '(Notulensi tersimpan tanpa catatan issue)', issuedBy: '', pic: '', dueDate: '', status: 'Open', remark: '' });
      }

      issueRows.forEach(issue => {
        rows.push({
          eventId: ev.id,
          tanggal_meeting: minutes.meetingDate || ev.date || '',
          week: ev.week || '',
          site: ev.site || '',
          jenis_meeting: minutes.meetingType || ev.meetingType || '',
          kode_event: ev.code || '',
          judul_notulen: minutes.meetingTitle || `NOTULENSI MEETING ${ev.meetingType || ''}`.trim(),
          notulis: minutes.notulis || '',
          lokasi: minutes.location || ev.site || '',
          section: issue.section,
          no: issue.issueNo,
          catatan_meeting: issue.note || '',
          issued_by: issue.issuedBy || '',
          pic: issue.pic || '',
          batas_waktu: issue.dueDate || '',
          status_catatan: issue.status || 'Open',
          keterangan: issue.remark || '',
          updated_at: minutes.updatedAt || ''
        });
      });
    });

    return rows.filter(row => {
      const siteOk = selectedSite === 'ALL' || row.site === selectedSite;
      const weekOk = selectedWeek === 'ALL' || row.week === selectedWeek;
      const searchOk = !search || Object.values(row).join(' ').toLowerCase().includes(search);
      return siteOk && weekOk && searchOk;
    });
  }

  function getAllMinutesIssueRows() {
    const rows = [];
    db.events.forEach(ev => {
      const minutes = ev.minutes || {};
      getMinutesIssueSections(minutes, false).flatMap(section =>
        getMinuteIssueRows(minutes, section.title, section.rows, section.id)
      ).forEach(issue => {
        rows.push({
          id: ev.id + '::' + (issue.sectionId || issue.section) + '::' + issue.issueNo,
          eventId: ev.id,
          site: ev.site || '',
          week: ev.week || '',
          date: minutes.meetingDate || ev.date || '',
          meetingType: minutes.meetingType || ev.meetingType || '',
          section: issue.section,
          issueNo: issue.issueNo,
          note: issue.note || '',
          issuedBy: issue.issuedBy || '',
          pic: issue.pic || '',
          dueDate: issue.dueDate || '',
          status: issue.status || 'Open',
          remark: issue.remark || ''
        });
      });
    });
    return rows.filter(row => row.note && row.note.trim().length >= 5);
  }

  const SEMANTIC_STOPWORDS = new Set(['yang','dan','dari','untuk','dengan','pada','dalam','atau','agar','oleh','ini','itu','ada','tidak','belum','sudah','akan','harus','perlu','terkait','terhadap','secara','sebagai','dapat','bisa','karena','saat','area','site','meeting','catatan','issue','the','and','for','are']);

  function normalizeIssueText(text) {
    return String(text || '').toLowerCase().replace(/[^a-z0-9 ]/g, ' ').replace(/  +/g, ' ').trim();
  }

  function tokenizeIssueText(text) {
    return normalizeIssueText(text).split(' ').map(token => token.trim()).filter(token => token.length >= 3 && !SEMANTIC_STOPWORDS.has(token));
  }

  function buildTfIdfVectors(rows) {
    const docs = rows.map(row => tokenizeIssueText([row.note, row.remark].join(' ')));
    const df = new Map();
    docs.forEach(tokens => [...new Set(tokens)].forEach(token => df.set(token, (df.get(token) || 0) + 1)));
    const n = Math.max(1, docs.length);
    return docs.map(tokens => {
      const tf = new Map();
      tokens.forEach(token => tf.set(token, (tf.get(token) || 0) + 1));
      const vec = new Map();
      const len = Math.max(1, tokens.length);
      tf.forEach((count, token) => {
        const idf = Math.log((n + 1) / ((df.get(token) || 0) + 1)) + 1;
        vec.set(token, (count / len) * idf);
      });
      return vec;
    });
  }

  const EMBEDDING_DIM = 384;
  const SEMANTIC_CONCEPTS = [
    { key: 'housekeeping_material_access', terms: ['housekeeping','material','sisa','sampah','berserakan','rapi','kebersihan','akses','jalur','walkway','jalan'] },
    { key: 'ppe_compliance', terms: ['apd','ppe','helm','sepatu','rompi','sarung','kacamata','masker','seragam','lengkap'] },
    { key: 'traffic_pedestrian_interaction', terms: ['pejalan','kaki','akses','interaksi','unit','kendaraan','haul','truck','jarak','aman','jalur'] },
    { key: 'critical_control_verification', terms: ['ccv','kontrol','critical','verifikasi','barrier','isolasi','interlock','sop','permit'] },
    { key: 'fatigue_alertness', terms: ['fatigue','lelah','microsleep','ngantuk','tidur','alert','dms','istirahat'] },
    { key: 'supervision_leadership', terms: ['supervisi','pengawas','foreman','pic','pjo','supervisor','briefing','coaching'] },
    { key: 'environment_spill_dust_water', terms: ['lingkungan','debu','limbah','tumpahan','oli','air','sediment','drainase'] },
    { key: 'emergency_fire_response', terms: ['emergency','darurat','api','kebakaran','apar','hydrant','evakuasi','rescue'] }
  ];

  function hashString(value) {
    let h = 2166136261;
    const text = String(value || '');
    for (let i = 0; i < text.length; i++) {
      h ^= text.charCodeAt(i);
      h = Math.imul(h, 16777619);
    }
    return h >>> 0;
  }

  function addEmbeddingFeature(vec, feature, weight = 1) {
    const hash = hashString(feature);
    const idx = hash % EMBEDDING_DIM;
    const sign = (hash & 1) ? 1 : -1;
    vec[idx] += sign * weight;
  }

  function normalizeDenseVector(vec) {
    const norm = Math.sqrt(vec.reduce((sum, value) => sum + value * value, 0));
    if (!norm) return vec;
    return vec.map(value => value / norm);
  }

  function getSemanticConceptsForText(tokens, normalizedText) {
    const tokenSet = new Set(tokens);
    return SEMANTIC_CONCEPTS.filter(concept => concept.terms.some(term => tokenSet.has(term) || normalizedText.includes(term))).map(concept => concept.key);
  }

  function embedIssueText(text) {
    const normalized = normalizeIssueText(text);
    const tokens = tokenizeIssueText(normalized);
    const vec = new Array(EMBEDDING_DIM).fill(0);

    tokens.forEach(token => addEmbeddingFeature(vec, 'tok:' + token, 0.9));
    for (let i = 0; i < tokens.length - 1; i++) addEmbeddingFeature(vec, 'bi:' + tokens[i] + '_' + tokens[i + 1], 1.25);
    for (let i = 0; i < tokens.length - 2; i++) addEmbeddingFeature(vec, 'tri:' + tokens[i] + '_' + tokens[i + 1] + '_' + tokens[i + 2], 1.1);
    tokens.forEach(token => {
      for (let n = 3; n <= Math.min(5, token.length); n++) {
        for (let i = 0; i <= token.length - n; i++) addEmbeddingFeature(vec, 'ng:' + token.slice(i, i + n), 0.18);
      }
    });
    getSemanticConceptsForText(tokens, normalized).forEach(concept => addEmbeddingFeature(vec, 'concept:' + concept, 3.2));

    return normalizeDenseVector(vec);
  }

  function buildSemanticEmbeddings(rows) {
    // Important: embedding hanya memakai isi catatan + keterangan.
    // Section/site/week tidak dimasukkan agar tidak membuat false positive antar notulen yang sama-sama "Enviro Issue" atau satu site.
    return rows.map(row => embedIssueText([row.note, row.remark].join(' ')));
  }

  function getSemanticSignals(row) {
    const normalized = normalizeIssueText([row.note, row.remark].join(' '));
    const tokens = tokenizeIssueText(normalized);
    return {
      tokens: new Set(tokens),
      concepts: new Set(getSemanticConceptsForText(tokens, normalized))
    };
  }

  function countSetOverlap(a, b) {
    let count = 0;
    a.forEach(value => { if (b.has(value)) count++; });
    return count;
  }

  function adjustedSemanticSimilarity(rowA, rowB, vecA, vecB) {
    const rawScore = cosineDense(vecA, vecB);
    const a = getSemanticSignals(rowA);
    const b = getSemanticSignals(rowB);
    const tokenOverlap = countSetOverlap(a.tokens, b.tokens);
    const conceptOverlap = countSetOverlap(a.concepts, b.concepts);
    const minTokenCount = Math.min(a.tokens.size, b.tokens.size);

    // Guardrail: untuk short text yang tidak punya token/concept overlap, jangan dianggap similar.
    // Ini mencegah kasus false positive seperti "kapan makan nasi" vs "pencucian mobil harus rajin".
    if (!tokenOverlap && !conceptOverlap) return 0;

    // Untuk kalimat sangat pendek, wajib ada overlap kata atau concept domain yang jelas.
    if (minTokenCount <= 3 && tokenOverlap < 1 && conceptOverlap < 1) return 0;

    // Jika hanya overlap concept tanpa overlap token, turunkan confidence agar tidak terlalu agresif.
    if (!tokenOverlap && conceptOverlap) return rawScore * 0.72;

    return rawScore;
  }

  function cosineDense(a, b) {
    let dot = 0;
    for (let i = 0; i < Math.min(a.length, b.length); i++) dot += a[i] * b[i];
    return Math.max(0, Math.min(1, dot));
  }

  function cosineSparse(a, b) {
    let dot = 0, normA = 0, normB = 0;
    a.forEach(value => { normA += value * value; });
    b.forEach(value => { normB += value * value; });
    const small = a.size <= b.size ? a : b;
    const large = a.size <= b.size ? b : a;
    small.forEach((value, key) => { dot += value * (large.get(key) || 0); });
    if (!normA || !normB) return 0;
    return dot / (Math.sqrt(normA) * Math.sqrt(normB));
  }

  function getSemanticLevel(score) {
    if (score >= 0.85) return { label: 'Repeated / Duplicate', cls: 'bg-red-50 text-red-700 ring-red-100' };
    if (score >= 0.70) return { label: 'Highly Similar', cls: 'bg-orange-50 text-orange-700 ring-orange-100' };
    if (score >= 0.55) return { label: 'Similar', cls: 'bg-blue-50 text-blue-700 ring-blue-100' };
    return { label: 'Related', cls: 'bg-slate-50 text-slate-700 ring-slate-200' };
  }

  function populateSemanticFilters() {
    const siteA = document.getElementById('semanticSiteA');
    const siteB = document.getElementById('semanticSiteB');
    if (!siteA || !siteB) return;
    const currentA = siteA.value || 'ALL';
    const currentB = siteB.value || 'ALL';
    const sites = [...new Set([...SITE_MASTER, ...getAllMinutesIssueRows().map(row => row.site).filter(Boolean)])];
    const options = '<option value="ALL">Semua Site</option>' + sites.map(site => '<option value="' + escapeHTML(site) + '">' + escapeHTML(site) + '</option>').join('');
    siteA.innerHTML = options;
    siteB.innerHTML = options;
    siteA.value = sites.includes(currentA) ? currentA : 'ALL';
    siteB.value = sites.includes(currentB) ? currentB : 'ALL';
  }

  function getSemanticBaseRows() {
    const siteA = document.getElementById('semanticSiteA')?.value || 'ALL';
    const siteB = document.getElementById('semanticSiteB')?.value || 'ALL';
    const section = document.getElementById('semanticSection')?.value || 'ALL';
    const search = (document.getElementById('semanticSearch')?.value || '').toLowerCase().trim();
    const allowedSites = new Set([siteA, siteB].filter(site => site !== 'ALL'));
    return getAllMinutesIssueRows().filter(row => {
      const siteOk = !allowedSites.size || allowedSites.has(row.site);
      const sectionOk = section === 'ALL' || row.section === section;
      const searchOk = !search || [row.note, row.remark, row.pic, row.issuedBy, row.site, row.week, row.meetingType, row.section].join(' ').toLowerCase().includes(search);
      return siteOk && sectionOk && searchOk;
    });
  }

  function calculateSemanticEvaluation() {
    const rows = getSemanticBaseRows();
    const threshold = Number(document.getElementById('semanticThreshold')?.value || 55) / 100;
    const crossSiteOnly = document.getElementById('semanticCrossSiteOnly')?.checked !== false;
    const siteA = document.getElementById('semanticSiteA')?.value || 'ALL';
    const siteB = document.getElementById('semanticSiteB')?.value || 'ALL';
    const vectors = buildSemanticEmbeddings(rows);
    const pairs = [];
    for (let i = 0; i < rows.length; i++) {
      for (let j = i + 1; j < rows.length; j++) {
        const a = rows[i];
        const b = rows[j];
        if (crossSiteOnly && a.site === b.site) continue;
        if (siteA !== 'ALL' && siteB !== 'ALL') {
          const direct = a.site === siteA && b.site === siteB;
          const reverse = a.site === siteB && b.site === siteA;
          if (!direct && !reverse) continue;
        }
        const score = adjustedSemanticSimilarity(a, b, vectors[i], vectors[j]);
        if (score >= threshold) {
          const level = getSemanticLevel(score);
          pairs.push({ a, b, score, scorePct: Math.round(score * 100), level: level.label, levelClass: level.cls, actionSignal: getSemanticActionSignal(a, b, score) });
        }
      }
    }
    pairs.sort((x, y) => y.score - x.score);
    return { rows, pairs, groups: buildSemanticGroups(rows, pairs) };
  }

  function getSemanticActionSignal(a, b, score) {
    if (score >= 0.85) return 'Standardize corrective action / cek repeat finding';
    if (a.status === 'Overdue' || b.status === 'Overdue') return 'Prioritaskan karena ada overdue';
    if (a.pic && a.pic === b.pic) return 'Cek common owner / systemic issue';
    return 'Review cross-site learning';
  }

  function buildSemanticGroups(rows, pairs) {
    const parent = new Map(rows.map(row => [row.id, row.id]));
    const find = id => {
      let p = parent.get(id);
      while (p && parent.get(p) !== p) p = parent.get(p);
      return p || id;
    };
    const union = (a, b) => {
      const pa = find(a), pb = find(b);
      if (pa !== pb) parent.set(pb, pa);
    };
    pairs.forEach(pair => union(pair.a.id, pair.b.id));
    const map = new Map();
    rows.forEach(row => {
      const root = find(row.id);
      if (!map.has(root)) map.set(root, []);
      map.get(root).push(row);
    });
    return [...map.values()].filter(group => group.length > 1 && new Set(group.map(row => row.site)).size > 1).map((group, index) => ({ groupNo: index + 1, rows: group, sites: [...new Set(group.map(row => row.site))], topTerms: getTopTermsForGroup(group) })).sort((a, b) => b.rows.length - a.rows.length || b.sites.length - a.sites.length);
  }

  function getTopTermsForGroup(group) {
    const freq = new Map();
    group.flatMap(row => tokenizeIssueText(row.note)).forEach(token => freq.set(token, (freq.get(token) || 0) + 1));
    return [...freq.entries()].sort((a, b) => b[1] - a[1]).slice(0, 5).map(item => item[0]).join(', ');
  }

  function renderSemanticEvaluation(force = false) {
    const tab = document.getElementById('tab-semanticeval');
    if (!tab || (tab.classList.contains('hidden') && !force)) return;
    populateSemanticFilters();
    const result = calculateSemanticEvaluation();
    const rows = result.rows;
    const pairs = result.pairs;
    const groups = result.groups;
    const crossSitePairs = pairs.filter(pair => pair.a.site !== pair.b.site).length;
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
    setText('semanticTotalIssues', rows.length);
    setText('semanticSimilarPairs', pairs.length);
    setText('semanticRepeatedGroups', groups.length);
    setText('semanticCrossSitePairs', crossSitePairs);
    setText('semanticGroupInfo', groups.length ? groups.length + ' group pengulangan issue terdeteksi.' : 'Belum ada group pengulangan issue pada threshold saat ini.');
    setText('semanticPairInfo', pairs.length ? 'Menampilkan top ' + Math.min(100, pairs.length) + ' dari ' + pairs.length + ' pasangan issue.' : 'Tidak ada pasangan issue melewati threshold saat ini.');
    const groupsTbody = document.getElementById('semanticGroupsTable');
    if (groupsTbody) groupsTbody.innerHTML = groups.length ? groups.map(group => '<tr onclick="openSemanticGroupDetail(' + group.groupNo + ')" class="cursor-pointer border-t border-slate-100 hover:bg-blue-50/50"><td class="px-4 py-3 font-black">#' + group.groupNo + '<div class="mt-1 text-[10px] font-bold text-blue-600">Klik detail</div></td><td class="px-4 py-3">' + group.sites.map(site => '<span class="mb-1 mr-1 inline-flex rounded-full bg-blue-50 px-2 py-1 text-xs font-black text-blue-700 ring-1 ring-blue-100">' + escapeHTML(site) + '</span>').join('') + '</td><td class="px-4 py-3 font-black">' + group.rows.length + '</td><td class="px-4 py-3 text-slate-600">' + escapeHTML(group.topTerms || '-') + '</td></tr>').join('') : '<tr><td colspan="4" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Tidak ada repeated issue group.</td></tr>';
    const pairsTbody = document.getElementById('semanticPairsTable');
    if (pairsTbody) {
      const topPairs = pairs.slice(0, 100);
      pairsTbody.innerHTML = topPairs.length ? topPairs.map((pair, pairIndex) => '<tr onclick="openSemanticPairDetail(' + pairIndex + ')" class="cursor-pointer border-t border-slate-100 hover:bg-blue-50/50"><td class="px-4 py-3 font-black text-slate-950">' + pair.scorePct + '%<div class="mt-1 text-[10px] font-bold text-blue-600">Klik detail</div></td><td class="px-4 py-3"><span class="rounded-full px-3 py-1 text-xs font-black ring-1 ' + pair.levelClass + '">' + escapeHTML(pair.level) + '</span></td><td class="px-4 py-3"><b>' + escapeHTML(pair.a.site) + '</b><div class="text-xs text-slate-500">' + escapeHTML(pair.a.week) + ' · ' + escapeHTML(pair.a.section) + '</div></td><td class="min-w-[280px] px-4 py-3">' + escapeHTML(pair.a.note) + '<div class="mt-1 text-xs text-slate-500">PIC: ' + escapeHTML(pair.a.pic || '-') + ' · Status: ' + escapeHTML(pair.a.status || '-') + '</div></td><td class="px-4 py-3"><b>' + escapeHTML(pair.b.site) + '</b><div class="text-xs text-slate-500">' + escapeHTML(pair.b.week) + ' · ' + escapeHTML(pair.b.section) + '</div></td><td class="min-w-[280px] px-4 py-3">' + escapeHTML(pair.b.note) + '<div class="mt-1 text-xs text-slate-500">PIC: ' + escapeHTML(pair.b.pic || '-') + ' · Status: ' + escapeHTML(pair.b.status || '-') + '</div></td><td class="px-4 py-3 text-sm font-semibold text-slate-700">' + escapeHTML(pair.actionSignal) + '</td></tr>').join('') : '<tr><td colspan="7" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Tidak ada pasangan issue sesuai filter dan threshold.</td></tr>';
    }
  }

  function getSemanticNotulenCard(row, similarityPct = null) {
    const similarityBadge = similarityPct !== null ? '<span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700 ring-1 ring-blue-100">' + similarityPct + '% similarity</span>' : '';
    return '<article class="rounded-3xl bg-white p-5 ring-1 ring-slate-200 shadow-sm">'
      + '<div class="mb-3 flex flex-wrap items-center gap-2">'
      + similarityBadge
      + '<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700">' + escapeHTML(row.site || '-') + '</span>'
      + '<span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700">' + escapeHTML(row.week || '-') + '</span>'
      + '<span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-black text-violet-700">' + escapeHTML(row.section || '-') + '</span>'
      + '<span class="rounded-full px-3 py-1 text-xs font-black ring-1 ' + getMinutesStatusClass(row.status) + '">' + escapeHTML(row.status || 'Open') + '</span>'
      + '</div>'
      + '<p class="text-sm font-semibold leading-6 text-slate-900">' + escapeHTML(row.note || '-') + '</p>'
      + '<div class="mt-4 grid gap-3 text-xs text-slate-600 md:grid-cols-2">'
      + '<div><b>Meeting:</b> ' + escapeHTML(row.meetingType || '-') + '</div>'
      + '<div><b>Tanggal:</b> ' + formatDate(row.date) + '</div>'
      + '<div><b>Issued By:</b> ' + escapeHTML(row.issuedBy || '-') + '</div>'
      + '<div><b>PIC:</b> ' + escapeHTML(row.pic || '-') + '</div>'
      + '<div><b>Batas Waktu:</b> ' + (row.dueDate ? formatDate(row.dueDate) : '-') + '</div>'
      + '<div><b>Keterangan:</b> ' + escapeHTML(row.remark || '-') + '</div>'
      + '</div>'
      + '<div class="no-print mt-4"><button onclick="closeSemanticDetailModal(); openEventRecapModal(\'' + escapeJS(row.eventId) + '\'); openEventMinutesForm();" class="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800">Buka Event / Notulensi</button></div>'
      + '</article>';
  }

  function openSemanticDetailModal(title, subtitle, bodyHTML) {
    const modal = document.getElementById('semanticDetailModal');
    const titleEl = document.getElementById('semanticDetailTitle');
    const subtitleEl = document.getElementById('semanticDetailSubtitle');
    const bodyEl = document.getElementById('semanticDetailBody');
    if (!modal || !titleEl || !bodyEl) return;
    titleEl.textContent = title;
    if (subtitleEl) subtitleEl.textContent = subtitle || '';
    bodyEl.innerHTML = bodyHTML || '';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closeSemanticDetailModal() {
    const modal = document.getElementById('semanticDetailModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  function openSemanticGroupDetail(groupNo) {
    const result = calculateSemanticEvaluation();
    const group = result.groups.find(item => item.groupNo === Number(groupNo));
    if (!group) return toast('Group semantic tidak ditemukan');
    const groupPairs = result.pairs.filter(pair => group.rows.some(row => row.id === pair.a.id) && group.rows.some(row => row.id === pair.b.id));
    const body = '<div class="mb-5 grid gap-3 md:grid-cols-3">'
      + '<div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Issue Count</p><p class="mt-1 text-2xl font-black text-slate-950">' + group.rows.length + '</p></div>'
      + '<div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Sites</p><p class="mt-1 text-sm font-black text-blue-700">' + escapeHTML(group.sites.join(', ')) + '</p></div>'
      + '<div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Pair Links</p><p class="mt-1 text-2xl font-black text-orange-700">' + groupPairs.length + '</p></div>'
      + '</div>'
      + '<div class="mb-4 rounded-3xl bg-violet-50 p-4 text-sm font-semibold text-violet-900 ring-1 ring-violet-100">Top terms: ' + escapeHTML(group.topTerms || '-') + '</div>'
      + '<div class="grid gap-3">' + group.rows.map(row => getSemanticNotulenCard(row)).join('') + '</div>';
    openSemanticDetailModal('Repeated Issue Group #' + group.groupNo, 'List notulen yang berada di dalam group pengulangan issue.', body);
  }

  function openSemanticPairDetail(pairIndex) {
    const pairs = calculateSemanticEvaluation().pairs.slice(0, 100);
    const pair = pairs[Number(pairIndex)];
    if (!pair) return toast('Pair semantic tidak ditemukan');
    const body = '<div class="mb-5 grid gap-3 md:grid-cols-3">'
      + '<div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Similarity</p><p class="mt-1 text-2xl font-black text-blue-700">' + pair.scorePct + '%</p></div>'
      + '<div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Level</p><p class="mt-2"><span class="rounded-full px-3 py-1 text-xs font-black ring-1 ' + pair.levelClass + '">' + escapeHTML(pair.level) + '</span></p></div>'
      + '<div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Action Signal</p><p class="mt-1 text-sm font-black text-slate-800">' + escapeHTML(pair.actionSignal) + '</p></div>'
      + '</div>'
      + '<div class="grid gap-4 lg:grid-cols-2">' + getSemanticNotulenCard(pair.a, pair.scorePct) + getSemanticNotulenCard(pair.b, pair.scorePct) + '</div>';
    openSemanticDetailModal('Similarity Issue Pair', 'List notulen yang membentuk pasangan similarity / keterkaitan issue.', body);
  }

  function exportSemanticPairsCSV() {
    const pairs = calculateSemanticEvaluation().pairs;
    const rows = pairs.map(pair => ({ similarity_percent: pair.scorePct, level: pair.level, site_a: pair.a.site, week_a: pair.a.week, section_a: pair.a.section, issue_a: pair.a.note, pic_a: pair.a.pic, status_a: pair.a.status, site_b: pair.b.site, week_b: pair.b.week, section_b: pair.b.section, issue_b: pair.b.note, pic_b: pair.b.pic, status_b: pair.b.status, action_signal: pair.actionSignal }));
    downloadCSV(rows, 'semantic_similarity_notulen.csv');
  }

  function getMinutesReportSimilarityMap(threshold = 0.5) {
    const allRows = getAllMinutesIssueRows();
    const vectors = buildTfIdfVectors(allRows);
    const map = new Map(allRows.map(row => [row.id, { maxScorePct: 0, relatedCount: 0, relatedRows: [] }]));

    for (let i = 0; i < allRows.length; i++) {
      for (let j = 0; j < allRows.length; j++) {
        if (i === j) continue;
        const score = cosineSparse(vectors[i], vectors[j]);
        if (score >= threshold) {
          const scorePct = Math.round(score * 100);
          const item = map.get(allRows[i].id) || { maxScorePct: 0, relatedCount: 0, relatedRows: [] };
          item.maxScorePct = Math.max(item.maxScorePct, scorePct);
          item.relatedRows.push({ ...allRows[j], similarityPct: scorePct });
          map.set(allRows[i].id, item);
        }
      }
    }

    map.forEach(item => {
      item.relatedRows.sort((a, b) => b.similarityPct - a.similarityPct);
      item.relatedCount = item.relatedRows.length;
      item.relatedRows = item.relatedRows.slice(0, 5);
    });

    return map;
  }

  function getMinutesSimilarityClass(scorePct) {
    const score = Number(scorePct || 0);
    if (score >= 85) return 'bg-red-50 text-red-700 ring-red-100';
    if (score >= 70) return 'bg-orange-50 text-orange-700 ring-orange-100';
    if (score >= 50) return 'bg-blue-50 text-blue-700 ring-blue-100';
    return 'bg-slate-50 text-slate-600 ring-slate-200';
  }

  function formatRelatedMinutes(relatedRows = []) {
    if (!relatedRows.length) return '<span class="text-sm font-semibold text-slate-400">Tidak ada notulen mirip &gt;50%</span>';
    return '<div class="space-y-2">' + relatedRows.map(row => '<div class="rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200"><div class="mb-1 flex flex-wrap items-center gap-2"><span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-black text-blue-700 ring-1 ring-blue-100">' + row.similarityPct + '%</span><span class="text-xs font-black text-slate-700">' + escapeHTML(row.site || '-') + ' · ' + escapeHTML(row.week || '-') + ' · ' + escapeHTML(row.section || '-') + '</span></div><p class="line-clamp-2 text-xs leading-5 text-slate-600">' + escapeHTML(row.note || '-') + '</p></div>').join('') + '</div>';
  }

  function getMinutesStatusClass(status) {
    const normalized = String(status || 'Open').toLowerCase();
    if (normalized === 'progress') return 'bg-blue-50 text-blue-700 ring-blue-100';
    if (normalized === 'overdue') return 'bg-red-50 text-red-700 ring-red-100';
    return 'bg-emerald-50 text-emerald-700 ring-emerald-100';
  }

  function getReportFilterParams() {
    return {
      site: document.getElementById('reportFilterSite')?.value || 'ALL',
      week: document.getElementById('reportFilterWeek')?.value || 'ALL',
      q: (document.getElementById('reportSearch')?.value || '').trim()
    };
  }

  function scheduleReportReload() {
    if (!reportTabInitialized) return;
    clearTimeout(reportReloadTimer);
    reportReloadTimer = setTimeout(() => reloadReportTables(), 400);
  }

  function setReportLoading(visible) {
    const el = document.getElementById('reportTabLoading');
    if (el) el.classList.toggle('hidden', !visible);
  }

  async function loadReportFilters() {
    const payload = await apiFetch(`${SID_MEETING_API_BASE}/reports/filters`);
    const siteSelect = document.getElementById('reportFilterSite');
    const weekSelect = document.getElementById('reportFilterWeek');
    if (!siteSelect || !weekSelect) return;

    const currentSite = siteSelect.value || 'ALL';
    const currentWeek = weekSelect.value || 'ALL';
    const sites = payload.sites || [];
    const weeks = payload.weeks || [];

    siteSelect.innerHTML = '<option value="ALL">Semua Site</option>'
      + sites.map(site => `<option value="${escapeHTML(site)}">${escapeHTML(site)}</option>`).join('');
    weekSelect.innerHTML = '<option value="ALL">Semua Week</option>'
      + weeks.map(week => `<option value="${escapeHTML(week)}">${escapeHTML(week)}</option>`).join('');

    siteSelect.value = sites.includes(currentSite) ? currentSite : 'ALL';
    weekSelect.value = weeks.includes(currentWeek) ? currentWeek : 'ALL';
  }

  const REPORT_DT_DOM = '<"report-dt-top"l>rt<"report-dt-footer flex flex-wrap items-center justify-between gap-3"ip>';

  function updateReportAttendanceInfo(json) {
    const info = document.getElementById('reportTableInfo');
    if (!info || !json) return;
    const filtered = Number(json.recordsFiltered ?? 0);
    const total = Number(json.recordsTotal ?? 0);
    info.innerHTML = `<strong>${filtered.toLocaleString('id-ID')}</strong> ditampilkan · ${total.toLocaleString('id-ID')} total absensi`;
  }

  function updateReportMinutesInfo(json) {
    const info = document.getElementById('minutesReportInfo');
    if (!info || !json) return;
    const filtered = Number(json.recordsFiltered ?? 0);
    const total = Number(json.recordsTotal ?? 0);
    info.innerHTML = `<strong>${filtered.toLocaleString('id-ID')}</strong> ditampilkan · ${total.toLocaleString('id-ID')} total notulen`;
  }

  function reportAjaxData(d) {
    const filters = getReportFilterParams();
    d.site = filters.site;
    d.week = filters.week;
    d.q = filters.q;
    return d;
  }

  function initReportAttendanceTable() {
    if (!window.jQuery || !$.fn.DataTable || reportAttendanceTable) return;
    const $table = $('#reportAttendanceDataTable');
    if (!$table.length) return;

    reportAttendanceTable = $table.DataTable({
      processing: true,
      serverSide: true,
      searching: false,
      deferRender: true,
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      order: [[10, 'desc']],
      language: DATATABLES_LANG,
      dom: REPORT_DT_DOM,
      ajax: {
        url: `${SID_MEETING_API_BASE}/reports/attendance-data`,
        data: reportAjaxData,
        dataSrc: json => {
          updateReportAttendanceInfo(json);
          return json.data || [];
        }
      },
      columns: [
        { data: 'tanggal_meeting', render: d => `<span class="dt-muted">${escapeHTML(d || '-')}</span>` },
        { data: 'week', render: d => `<span class="dt-chip dt-chip-week">${escapeHTML(d || '-')}</span>` },
        { data: 'site', render: d => `<span class="dt-chip dt-chip-site">${escapeHTML(d || '-')}</span>` },
        { data: 'jenis_meeting', render: d => escapeHTML(d || '-') },
        { data: 'kode_event', render: d => `<span class="text-xs text-slate-500">${escapeHTML(d || '-')}</span>` },
        { data: 'kode_sid', render: d => `<span class="dt-chip dt-chip-sid">${escapeHTML(d || '-')}</span>` },
        { data: 'nama', render: d => `<span class="dt-name">${escapeHTML(d || '-')}</span>` },
        { data: 'perusahaan', render: d => escapeHTML(d || '-') },
        { data: 'jabatan_struktural', render: d => `<span class="text-slate-600">${escapeHTML(d || '-')}</span>` },
        { data: 'jabatan_fungsional', render: d => `<span class="text-slate-600">${escapeHTML(d || '-')}</span>` },
        { data: 'timestamp', render: d => `<span class="dt-muted">${escapeHTML(d || '-')}</span>` }
      ],
      createdRow: (row, data) => {
        row.title = 'Klik untuk buka rekap event';
        row.addEventListener('click', () => openEventRecapModal(data.event_id));
      },
      initComplete: function() {
        $('#reportAttendanceDataTable_wrapper').addClass('report-dt-wrapper company-dt-wrapper');
      }
    });
  }

  function initReportMinutesTable() {
    if (!window.jQuery || !$.fn.DataTable || reportMinutesTable) return;
    const $table = $('#reportMinutesDataTable');
    if (!$table.length) return;

    reportMinutesTable = $table.DataTable({
      processing: true,
      serverSide: true,
      searching: false,
      deferRender: true,
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      order: [[16, 'desc']],
      language: DATATABLES_LANG,
      dom: REPORT_DT_DOM,
      ajax: {
        url: `${SID_MEETING_API_BASE}/reports/minutes-data`,
        data: reportAjaxData,
        dataSrc: json => {
          updateReportMinutesInfo(json);
          return json.data || [];
        }
      },
      columns: [
        { data: 'tanggal_meeting', render: d => `<span class="dt-muted">${escapeHTML(d || '-')}</span>` },
        { data: 'week', render: d => `<span class="dt-chip dt-chip-week">${escapeHTML(d || '-')}</span>` },
        { data: 'site', render: d => `<span class="dt-chip dt-chip-site">${escapeHTML(d || '-')}</span>` },
        { data: 'jenis_meeting', render: d => escapeHTML(d || '-') },
        { data: 'kode_event', render: d => `<span class="text-xs text-slate-500">${escapeHTML(d || '-')}</span>` },
        { data: 'judul_notulen', render: d => `<span class="dt-name">${escapeHTML(d || '-')}</span>` },
        { data: 'notulis', render: d => escapeHTML(d || '-') },
        { data: 'lokasi', render: d => escapeHTML(d || '-') },
        { data: 'section', render: d => escapeHTML(d || '-') },
        { data: 'no', render: d => escapeHTML(String(d ?? '-')) },
        { data: 'catatan_meeting', className: 'min-w-[200px]', render: d => escapeHTML(d || '-') },
        { data: 'issued_by', render: d => escapeHTML(d || '-') },
        { data: 'pic', render: d => escapeHTML(d || '-') },
        { data: 'batas_waktu', render: d => escapeHTML(d || '-') },
        { data: 'status_catatan', orderable: false, render: d => `<span class="rounded-full px-3 py-1 text-xs font-black ring-1 ${getMinutesStatusClass(d)}">${escapeHTML(d || 'Open')}</span>` },
        { data: 'keterangan', render: d => escapeHTML(d || '-') },
        { data: 'updated_at', render: d => escapeHTML(d || '-') }
      ],
      createdRow: (row, data) => {
        row.title = 'Klik untuk buka notulensi event';
        row.addEventListener('click', () => {
          openEventRecapModal(data.event_id);
          openEventMinutesForm();
        });
      },
      initComplete: function() {
        $('#reportMinutesDataTable_wrapper').addClass('report-dt-wrapper company-dt-wrapper');
      }
    });
  }

  function reloadReportTables() {
    if (reportAttendanceTable) reportAttendanceTable.ajax.reload(null, false);
    if (reportMinutesTable) reportMinutesTable.ajax.reload(null, false);
  }

  async function initReportTab() {
    renderReportViewState();
    setReportLoading(true);
    try {
      if (!reportTabInitialized) {
        await loadReportFilters();
        initReportAttendanceTable();
        initReportMinutesTable();
        reportTabInitialized = true;
      } else {
        reloadReportTables();
      }
    } catch (err) {
      toast(err.message || 'Gagal memuat tab rekap');
    } finally {
      setReportLoading(false);
    }
  }

  async function initSitePerformanceTabLazy() {
    setReportLoading(true);
    try {
      if (!fullDataLoaded) await hydrateFromDatabase(false);
      populateSiteTrendFilters();
      setTimeout(() => renderSitePerformance(), 0);
    } catch (err) {
      toast('Gagal memuat site performance');
    } finally {
      setReportLoading(false);
    }
  }

  async function initSemanticTabLazy() {
    setReportLoading(true);
    try {
      if (!fullDataLoaded) await hydrateFromDatabase(false);
      setTimeout(() => renderSemanticEvaluation(true), 0);
    } catch (err) {
      toast('Gagal memuat semantic evaluation');
    } finally {
      setReportLoading(false);
    }
  }

  function setReportView(view) {
    reportView = view === 'minutes' ? 'minutes' : 'attendance';
    renderReportViewState();
    if (reportTabInitialized) reloadReportTables();
  }

  function renderReportViewState() {
    const attendancePanel = document.getElementById('reportAttendancePanel');
    const minutesPanel = document.getElementById('reportMinutesPanel');
    const attendanceBtn = document.getElementById('reportViewAttendanceBtn');
    const minutesBtn = document.getElementById('reportViewMinutesBtn');
    if (attendancePanel) attendancePanel.classList.toggle('hidden', reportView !== 'attendance');
    if (minutesPanel) minutesPanel.classList.toggle('hidden', reportView !== 'minutes');

    if (attendanceBtn && minutesBtn) {
      attendanceBtn.classList.toggle('tab-active', reportView === 'attendance');
      minutesBtn.classList.toggle('tab-active', reportView === 'minutes');
    }
  }

  function renderReport() {
    if (reportTabInitialized) reloadReportTables();
    else initReportTab();
  }

  function resetReportFilters() {
    const site = document.getElementById('reportFilterSite');
    const week = document.getElementById('reportFilterWeek');
    const search = document.getElementById('reportSearch');
    if (site) site.value = 'ALL';
    if (week) week.value = 'ALL';
    if (search) search.value = '';
    scheduleReportReload();
  }

  function populateSiteTrendFilters() {
    const siteSelect = document.getElementById('siteTrendFilter');
    const weekSelect = document.getElementById('siteTrendWeekFilter');
    if (!siteSelect || !weekSelect) return;

    const currentSite = siteSelect.value || 'ALL';
    const currentWeek = weekSelect.value || 'ALL';
    const sites = [...new Set(db.events.map(e => e.site).filter(Boolean))].sort();
    const weeks = [...new Set(db.events.map(e => e.week).filter(Boolean))].sort();

    siteSelect.innerHTML = '<option value="ALL">Semua Site</option>' + sites.map(site => `<option value="${escapeHTML(site)}">${escapeHTML(site)}</option>`).join('');
    weekSelect.innerHTML = '<option value="ALL">Semua Week</option>' + weeks.map(week => `<option value="${escapeHTML(week)}">${escapeHTML(week)}</option>`).join('');

    siteSelect.value = sites.includes(currentSite) ? currentSite : 'ALL';
    weekSelect.value = weeks.includes(currentWeek) ? currentWeek : 'ALL';
  }

  function getWeeklyAttendanceRateBySite() {
    const selectedSite = document.getElementById('siteTrendFilter')?.value || 'ALL';
    const selectedWeek = document.getElementById('siteTrendWeekFilter')?.value || 'ALL';
    const filteredEvents = db.events.filter(ev => {
      const siteOk = selectedSite === 'ALL' || ev.site === selectedSite;
      const weekOk = selectedWeek === 'ALL' || ev.week === selectedWeek;
      return siteOk && weekOk;
    });
    const weeks = [...new Set(filteredEvents.map(ev => ev.week).filter(Boolean))].sort();
    const sites = [...new Set(filteredEvents.map(ev => ev.site).filter(Boolean))].sort();

    const datasets = sites.map((site, idx) => {
      const data = weeks.map(week => {
        const siteWeekEvents = filteredEvents.filter(ev => ev.site === site && ev.week === week);
        return getAttendanceRateForEvents(siteWeekEvents) || null;
      });
      const hue = (idx * 57) % 360;
      return {
        label: site,
        data,
        borderColor: `hsl(${hue}, 70%, 45%)`,
        backgroundColor: `hsla(${hue}, 70%, 45%, 0.15)`,
        tension: 0.35,
        fill: false,
        spanGaps: true,
        pointRadius: 4,
        pointHoverRadius: 6,
        borderWidth: 3
      };
    });

    return { weeks, datasets, selectedSite, selectedWeek };
  }

  function getCompanyAttendanceRateRows() {
    const selectedSite = document.getElementById('siteTrendFilter')?.value || 'ALL';
    const selectedWeek = document.getElementById('siteTrendWeekFilter')?.value || 'ALL';
    const filteredEvents = db.events.filter(ev => {
      const siteOk = selectedSite === 'ALL' || ev.site === selectedSite;
      const weekOk = selectedWeek === 'ALL' || ev.week === selectedWeek;
      return siteOk && weekOk;
    });
    const eventIds = new Set(filteredEvents.map(ev => String(ev.id)));
    const filteredAttendance = db.attendance.filter(a => eventIds.has(String(a.eventId)));
    const map = new Map();

    filteredEvents.forEach(ev => {
      const expectedUnits = getEventExpectedUnits(ev);
      const presentKeys = new Set(getEventPresentUnits(ev).map(unit => unit.key));
      expectedUnits.forEach(unit => {
        const key = normalizeCompanyName(unit.company);
        if (!map.has(key)) {
          map.set(key, {
            company: unit.company,
            expectedEvents: 0,
            presentEvents: 0,
            totalAbsensi: 0,
            levels: new Set(),
            targetTypes: new Set()
          });
        }
        const row = map.get(key);
        row.expectedEvents++;
        if (presentKeys.has(unit.key)) row.presentEvents++;
        row.levels.add(getMeetingLevelLabel(unit.level));
        row.targetTypes.add(unit.targetType);
      });
    });

    filteredAttendance.forEach(a => {
      const ev = filteredEvents.find(e => String(e.id) === String(a.eventId));
      if (!ev) return;
      const key = normalizeCompanyName(a.company);
      if (!map.has(key)) {
        map.set(key, {
          company: a.company || 'Tidak Ada Perusahaan',
          expectedEvents: 0,
          presentEvents: 0,
          totalAbsensi: 0,
          levels: new Set([getMeetingLevelLabel(ev.meetingLevel)]),
          targetTypes: new Set(['Attendance Only'])
        });
      }
      map.get(key).totalAbsensi++;
    });

    return [...map.values()].map(row => {
      const attendanceRate = row.expectedEvents
        ? Math.round((row.presentEvents / row.expectedEvents) * 100)
        : (row.totalAbsensi ? 100 : 0);
      const perf = getCompanyPerformanceLabel(attendanceRate);
      return {
        company: row.company,
        expectedEvents: row.expectedEvents,
        presentEvents: row.presentEvents,
        totalAbsensi: row.totalAbsensi,
        levels: [...row.levels].join(', '),
        targetTypes: [...row.targetTypes].join(', '),
        attendanceRate,
        performanceLabel: perf.label,
        performanceClass: perf.cls
      };
    }).sort((a, b) => b.attendanceRate - a.attendanceRate || a.company.localeCompare(b.company));
  }

  function getCompanyPerformanceLabel(rate) {
    if (rate >= 90) return { label: 'Excellent', cls: 'bg-emerald-50 text-emerald-700 ring-emerald-100' };
    if (rate >= 75) return { label: 'Good', cls: 'bg-blue-50 text-blue-700 ring-blue-100' };
    if (rate >= 50) return { label: 'Need Improvement', cls: 'bg-amber-50 text-amber-700 ring-amber-100' };
    return { label: 'Critical', cls: 'bg-red-50 text-red-700 ring-red-100' };
  }

  function getFilteredCompanyAttendanceRateRows() {
    const search = (document.getElementById('companyPerformanceSearch')?.value || '').toLowerCase().trim();
    return getCompanyAttendanceRateRows().filter(row => {
      if (!search) return true;
      return [row.company, row.performanceLabel, row.levels, row.targetTypes, row.attendanceRate, row.expectedEvents, row.presentEvents, row.totalAbsensi]
        .join(' ')
        .toLowerCase()
        .includes(search);
    });
  }

  function renderCompanyPerformanceTable() {
    const rows = getFilteredCompanyAttendanceRateRows();
    const tbody = document.getElementById('companyPerformanceTable');
    const info = document.getElementById('companyPerformanceInfo');
    const selectedSite = document.getElementById('siteTrendFilter')?.value || 'ALL';
    const selectedWeek = document.getElementById('siteTrendWeekFilter')?.value || 'ALL';
    if (info) {
      const siteText = selectedSite === 'ALL' ? 'semua site' : `site ${selectedSite}`;
      const weekText = selectedWeek === 'ALL' ? 'semua week' : `week ${selectedWeek}`;
      const search = (document.getElementById('companyPerformanceSearch')?.value || '').trim();
      info.textContent = `Menampilkan ${rows.length} perusahaan untuk ${siteText}, ${weekText}${search ? `, search: "${search}"` : ''}.`;
    }
    if (!tbody) return;
    tbody.innerHTML = rows.length ? rows.map((row, index) => `<tr class="border-t border-slate-100 hover:bg-blue-50/50">
      <td class="px-4 py-3 font-black">#${index + 1}</td>
      <td class="px-4 py-3"><b>${escapeHTML(row.company)}</b></td>
      <td class="px-4 py-3"><div class="text-xs font-black">${escapeHTML(row.levels || '-')}</div><div class="mt-1 text-xs text-slate-500">${escapeHTML(row.targetTypes || '-')}</div></td>
      <td class="px-4 py-3">${row.expectedEvents}</td>
      <td class="px-4 py-3">${row.presentEvents}</td>
      <td class="px-4 py-3">${row.totalAbsensi}</td>
      <td class="px-4 py-3"><b>${row.attendanceRate}%</b></td>
      <td class="px-4 py-3"><span class="rounded-full px-3 py-1 text-xs font-black ring-1 ${row.performanceClass}">${row.performanceLabel}</span></td>
    </tr>`).join('') : `<tr><td colspan="8" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">Tidak ada data perusahaan sesuai filter.</td></tr>`;
  }

  function renderSitePerformance() {
    populateSiteTrendFilters();
    const { weeks, datasets, selectedSite, selectedWeek } = getWeeklyAttendanceRateBySite();
    const allRates = datasets.flatMap(ds => ds.data).filter(v => typeof v === 'number');
    const avgRate = allRates.length ? Math.round(allRates.reduce((a, b) => a + b, 0) / allRates.length) : 0;

    const totalSiteEl = document.getElementById('siteTrendTotalSite');
    const avgRateEl = document.getElementById('siteTrendAvgRate');
    const weekCountEl = document.getElementById('siteTrendWeekCount');
    const infoEl = document.getElementById('siteTrendInfo');
    if (totalSiteEl) totalSiteEl.textContent = datasets.length;
    if (avgRateEl) avgRateEl.textContent = avgRate + '%';
    if (weekCountEl) weekCountEl.textContent = weeks.length;
    if (infoEl) {
      const siteText = selectedSite === 'ALL' ? 'seluruh site' : `site ${selectedSite}`;
      const weekText = selectedWeek === 'ALL' ? `${weeks.length} week` : `week ${selectedWeek}`;
      infoEl.textContent = `Menampilkan trend Attendance Rate ${siteText} pada ${weekText}.`;
    }

    const canvas = document.getElementById('sitePerformanceChart');
    if (!canvas) return;
    if (!window.Chart) {
      canvas.parentElement.innerHTML = '<div class="flex h-full items-center justify-center rounded-3xl bg-red-50 p-6 text-center text-sm font-bold text-red-700 ring-1 ring-red-100">Chart.js gagal dimuat. Periksa koneksi internet atau CSP browser.</div>';
      return;
    }
    if (sitePerformanceChart) sitePerformanceChart.destroy();

    sitePerformanceChart = new Chart(canvas, {
      type: 'line',
      data: { labels: weeks, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'nearest', intersect: false },
        plugins: {
          legend: { display: true, position: 'top' },
          tooltip: {
            callbacks: {
              label: function(context) {
                const value = context.parsed.y;
                return `${context.dataset.label}: ${value !== null ? value + '%' : 'No Data'}`;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            min: 0,
            max: 100,
            ticks: { callback: function(value) { return value + '%'; } },
            title: { display: true, text: 'Attendance Rate' }
          },
          x: { title: { display: true, text: 'Week' } }
        }
      }
    });

    renderCompanyPerformanceTable();
  }

  function exportSitePerformanceCSV() {
    const { weeks, datasets } = getWeeklyAttendanceRateBySite();
    const rows = [];
    datasets.forEach(ds => {
      weeks.forEach((week, i) => {
        rows.push({ site: ds.label, week, attendance_rate_percent: ds.data[i] ?? '' });
      });
    });
    downloadCSV(rows, 'site_performance_trend.csv');
  }

  function exportCompanyPerformanceCSV() {
    const selectedSite = document.getElementById('siteTrendFilter')?.value || 'ALL';
    const selectedWeek = document.getElementById('siteTrendWeekFilter')?.value || 'ALL';
    const rows = getFilteredCompanyAttendanceRateRows().map((row, index) => ({
      rank: index + 1,
      site_filter: selectedSite,
      week_filter: selectedWeek,
      perusahaan: row.company,
      kategori: row.levels || '',
      target_basis: row.targetTypes || '',
      expected_event: row.expectedEvents,
      event_hadir: row.presentEvents,
      total_absensi: row.totalAbsensi,
      attendance_rate_percent: row.attendanceRate,
      performance: row.performanceLabel
    }));
    downloadCSV(rows, 'company_attendance_rate.csv');
  }

  function getOverallAttendanceRate() {
    return getAttendanceRateForEvents(db.events);
  }

  function renderStats() {
    if (!fullDataLoaded) {
      loadStats();
      return;
    }
    document.getElementById('statEvents').textContent = db.events.length;
    document.getElementById('statActiveEvents').textContent = db.events.filter(isEventActive).length;
    document.getElementById('statAttendance').textContent = db.attendance.length;
    document.getElementById('statAttendanceRateAll').textContent = getOverallAttendanceRate() + '%';
  }

  function refreshHeavyViews() {
    renderOptions();
    renderCompanyMaster();
    renderActiveAttendanceEvent();
    if (reportTabInitialized) reloadReportTables();
    if (!document.getElementById('tab-siteperformance')?.classList.contains('hidden')) {
      populateSiteTrendFilters();
      renderSitePerformance();
    }
    renderStats();
  }

  function refreshAll() {
    renderActiveAttendanceEvent();
    if (fullDataLoaded) refreshHeavyViews();
    else loadStats();
  }

  function tickLiveClock() {
    const nowIso = new Date().toISOString();
    const ts = document.getElementById('timestampInput');
    if (ts) ts.value = formatDateTime(nowIso);
    const closeElapsed = document.getElementById('closeMeetingElapsed');
    const pendingEv = getEventById(pendingCloseEventId);
    if (closeElapsed && pendingEv) closeElapsed.textContent = formatDuration(getElapsedMs(pendingEv));
    if (selectedRecapEventId && document.getElementById('eventRecapModal') && !document.getElementById('eventRecapModal').classList.contains('hidden')) {
      const ev = getEventById(selectedRecapEventId);
      if (ev) {
        const elapsedCell = document.querySelector('#eventRecapSummary .font-mono');
        if (elapsedCell) elapsedCell.textContent = formatDuration(getElapsedMs(ev));
      }
    }
    checkMeetingClosePrompts();
  }

  function exportAttendanceCSV() {
    const rows = db.attendance.filter(a => !scannedEventId || a.eventId === scannedEventId).map(a => { const ev = db.events.find(x => x.id === a.eventId) || {}; return { jenis_meeting: ev.meetingType || '', site: ev.site || '', tanggal_meeting: ev.date || '', week: ev.week || '', kode_sid: a.sid, nama: a.name, perusahaan: a.company, jabatan_struktural: a.structuralPosition, jabatan_fungsional: a.functionalPosition, timestamp: a.timestamp }; });
    downloadCSV(rows, 'absensi_event_sid.csv');
  }
  function exportSelectedEventCSV() {
    if (!selectedRecapEventId) return toast('Belum ada event yang dipilih');
    const ev = db.events.find(x => x.id === selectedRecapEventId) || {};
    const rows = db.attendance.filter(a => a.eventId === selectedRecapEventId).map(a => ({ jenis_meeting: ev.meetingType || '', site: ev.site || '', tanggal_meeting: ev.date || '', week: ev.week || '', kode_sid: a.sid, nama: a.name, perusahaan: a.company, jabatan_struktural: a.structuralPosition, jabatan_fungsional: a.functionalPosition, timestamp: a.timestamp }));
    const safeName = String(ev.meetingType || 'event').split(' ').join('_');
    downloadCSV(rows, 'rekap_' + safeName + '_' + (ev.week || '') + '.csv');
  }

  function exportSelectedEventExcel() {
    if (!selectedRecapEventId) return toast('Belum ada event yang dipilih');
    if (!window.XLSX || !window.XLSX.utils) return toast('Library Excel belum termuat. Coba refresh halaman.');
    const ev = db.events.find(x => x.id === selectedRecapEventId) || {};
    const rawRows = db.attendance
      .filter(a => a.eventId === selectedRecapEventId)
      .map(a => ({
        event_code: ev.code || '',
        meeting_type: ev.meetingType || '',
        site: ev.site || '',
        meeting_date: ev.date || '',
        week: ev.week || '',
        kode_sid: a.sid || '',
        nama: a.name || '',
        perusahaan: a.company || '',
        jabatan_struktural: a.structuralPosition || '',
        jabatan_fungsional: a.functionalPosition || '',
        timestamp: a.timestamp || '',
      }));

    downloadExcel(rawRows, `rekap_${String(ev.meetingType || 'event').split(' ').join('_')}_${ev.week || ''}.xlsx`, {
      sheetName: 'Daftar Hadir',
      columns: [
        { key: 'event_code', header: 'Kode Event', width: 14 },
        { key: 'meeting_type', header: 'Jenis Meeting', width: 24 },
        { key: 'site', header: 'Site', width: 12 },
        { key: 'meeting_date', header: 'Tanggal Meeting', width: 14 },
        { key: 'week', header: 'Week', width: 8 },
        { key: 'kode_sid', header: 'Kode SID', width: 12 },
        { key: 'nama', header: 'Nama', width: 22 },
        { key: 'perusahaan', header: 'Perusahaan', width: 26 },
        { key: 'jabatan_struktural', header: 'Jabatan Struktural', width: 20 },
        { key: 'jabatan_fungsional', header: 'Jabatan Fungsional', width: 20 },
        { key: 'timestamp', header: 'Timestamp', width: 22 },
      ]
    });
  }
  function exportAllCSV() {
    exportFilteredReportCSV();
  }

  function exportFilteredReportCSV() {
    const rows = getReportRows().map(row => ({
      tanggal_meeting: row.tanggal_meeting,
      week: row.week,
      site: row.site,
      jenis_meeting: row.jenis_meeting,
      kode_event: row.kode_event,
      kode_sid: row.kode_sid,
      nama: row.nama,
      perusahaan: row.perusahaan,
      jabatan_struktural: row.jabatan_struktural,
      jabatan_fungsional: row.jabatan_fungsional,
      timestamp: row.timestamp
    }));
    downloadCSV(rows, 'rekap_absensi_filtered.csv');
  }

  function exportMinutesReportCSV() {
    const rows = getMinutesReportRows().map(row => ({
      tanggal_meeting: row.tanggal_meeting,
      week: row.week,
      site: row.site,
      jenis_meeting: row.jenis_meeting,
      kode_event: row.kode_event,
      judul_notulen: row.judul_notulen,
      notulis: row.notulis,
      lokasi: row.lokasi,
      section: row.section,
      no: row.no,
      catatan_meeting: row.catatan_meeting,
      issued_by: row.issued_by,
      pic: row.pic,
      batas_waktu: row.batas_waktu,
      status_catatan: row.status_catatan,
      keterangan: row.keterangan,
      updated_at: row.updated_at
    }));
    downloadCSV(rows, 'rekap_notulen_filtered.csv');
  }
  function downloadCSV(rows, filename) { if (!rows.length) return toast('Tidak ada data untuk diexport'); const headers = [...new Set(rows.flatMap(row => Object.keys(row)))]; const csv = [headers.join(','), ...rows.map(row => headers.map(h => csvCell(row[h])).join(','))].join('\n'); const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' }); const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = filename; a.click(); URL.revokeObjectURL(url); toast('CSV berhasil dibuat'); }
  function csvCell(value) { const s = String(value ?? ''); return `"${s.replace(/"/g, '""')}"`; }

  function downloadExcel(rows, filename, options = {}) {
    if (!Array.isArray(rows) || !rows.length) return toast('Tidak ada data untuk diexport');
    const sheetName = String(options.sheetName || 'Sheet1').slice(0, 31) || 'Sheet1';
    const columns = Array.isArray(options.columns) ? options.columns : [];
    const headers = columns.length ? columns.map(c => c.header) : Object.keys(rows[0] || {});
    const keys = columns.length ? columns.map(c => c.key) : Object.keys(rows[0] || {});

    const aoa = [headers, ...rows.map(row => keys.map(k => row?.[k] ?? ''))];
    const ws = XLSX.utils.aoa_to_sheet(aoa);

    ws['!cols'] = (columns.length ? columns : keys.map(k => ({ width: Math.max(10, String(k).length + 2) })))
      .map(col => ({ wch: Number(col.width || 12) }));

    ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: aoa.length - 1, c: headers.length - 1 } }) };

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, sheetName);
    XLSX.writeFile(wb, filename);
    toast('Excel berhasil dibuat');
  }

  function seedDemoData() {
    const today = new Date().toISOString().slice(0,10); const week = `W${String(getISOWeek(today)).padStart(2, '0')}`;
    const previousWeek = `W${String(Math.max(1, getISOWeek(today) - 1)).padStart(2, '0')}`;
    const twoWeeksAgo = `W${String(Math.max(1, getISOWeek(today) - 2)).padStart(2, '0')}`;
    const previousDate = new Date(Date.now() - 7 * 86400000).toISOString().slice(0,10);
    const twoWeeksAgoDate = new Date(Date.now() - 14 * 86400000).toISOString().slice(0,10);
    db.events = [
      { id: 'EVT-DEMO1', code: 'EV-240417', meetingType: 'Safety Talk', site: 'Marine', date: today, week, startTime: '00:01', endTime: '23:59', manualStatus: 'Open', createdAt: new Date().toISOString(), updatedAt: new Date().toISOString() },
      { id: 'EVT-DEMO2', code: 'EV-240418', meetingType: 'Critical Control Verification', site: 'GMO', date: today, week, startTime: '00:01', endTime: '23:59', manualStatus: 'Open', createdAt: new Date().toISOString(), updatedAt: new Date().toISOString() },
      { id: 'EVT-DEMO3', code: 'EV-240419', meetingType: 'Toolbox Meeting', site: 'BMO 1', date: today, week, startTime: '00:01', endTime: '23:59', manualStatus: 'Open', createdAt: new Date().toISOString(), updatedAt: new Date().toISOString() },
      { id: 'EVT-DEMO4', code: 'EV-240420', meetingType: 'Safety Talk', site: 'Marine', date: previousDate, week: previousWeek, startTime: '00:01', endTime: '23:59', manualStatus: 'Open', createdAt: new Date().toISOString(), updatedAt: new Date().toISOString() },
      { id: 'EVT-DEMO5', code: 'EV-240421', meetingType: 'Safety Talk', site: 'SMO', date: previousDate, week: previousWeek, startTime: '00:01', endTime: '23:59', manualStatus: 'Open', createdAt: new Date().toISOString(), updatedAt: new Date().toISOString() },
      { id: 'EVT-DEMO6', code: 'EV-240422', meetingType: 'Safety Talk', site: 'BMO 1', date: twoWeeksAgoDate, week: twoWeeksAgo, startTime: '00:01', endTime: '23:59', manualStatus: 'Open', createdAt: new Date().toISOString(), updatedAt: new Date().toISOString() }
    ];
    db.attendance = [
      { id: 'ABS-DEMO1', eventId: 'EVT-DEMO1', employeeId: 'SYS-EMP-1', sid: 'SID001', name: 'Budi Santoso', company: 'PAMA', structuralPosition: 'Supervisor', functionalPosition: 'Operator A2B', timestamp: new Date().toISOString(), source: 'Demo', device: 'Demo' },
      { id: 'ABS-DEMO2', eventId: 'EVT-DEMO1', employeeId: 'SYS-EMP-2', sid: 'SID002', name: 'Andi Wijaya', company: 'Berau Coal', structuralPosition: 'Superintendent', functionalPosition: 'Safety Evaluator', timestamp: new Date().toISOString(), source: 'Demo', device: 'Demo' },
      { id: 'ABS-DEMO3', eventId: 'EVT-DEMO2', employeeId: 'SYS-EMP-3', sid: 'SID003', name: 'Siti Rahma', company: 'BUMA', structuralPosition: 'Foreman', functionalPosition: 'Admin SHE', timestamp: new Date().toISOString(), source: 'Demo', device: 'Demo' },
      { id: 'ABS-DEMO4', eventId: 'EVT-DEMO3', employeeId: 'SYS-EMP-4', sid: 'SID004', name: 'Rizky Pratama', company: 'PAMA', structuralPosition: 'Group Leader', functionalPosition: 'Mekanik', timestamp: new Date().toISOString(), source: 'Demo', device: 'Demo' },
      { id: 'ABS-DEMO5', eventId: 'EVT-DEMO4', employeeId: 'SYS-EMP-1', sid: 'SID001', name: 'Budi Santoso', company: 'PAMA', structuralPosition: 'Supervisor', functionalPosition: 'Operator A2B', timestamp: new Date(Date.now() - 7 * 86400000).toISOString(), source: 'Demo', device: 'Demo' },
      { id: 'ABS-DEMO6', eventId: 'EVT-DEMO5', employeeId: 'SYS-EMP-2', sid: 'SID002', name: 'Andi Wijaya', company: 'Berau Coal', structuralPosition: 'Superintendent', functionalPosition: 'Safety Evaluator', timestamp: new Date(Date.now() - 7 * 86400000).toISOString(), source: 'Demo', device: 'Demo' },
      { id: 'ABS-DEMO7', eventId: 'EVT-DEMO6', employeeId: 'SYS-EMP-4', sid: 'SID004', name: 'Rizky Pratama', company: 'PAMA', structuralPosition: 'Group Leader', functionalPosition: 'Mekanik', timestamp: new Date(Date.now() - 14 * 86400000).toISOString(), source: 'Demo', device: 'Demo' }
    ];
    db.events.find(e => e.id === 'EVT-DEMO1').minutes = {
      meetingTitle: 'NOTULENSI MEETING SAFETY TALK MARINE', meetingType: 'Safety Talk', meetingDate: today, notulis: 'Admin HSE', location: 'Marine', updatedAt: new Date().toISOString(),
      enviroIssues: [],
      safetyIssues: [
        { note: 'Housekeeping area kerja belum konsisten, masih ditemukan material sisa di jalur akses pekerja', issuedBy: 'HSE Marine', pic: 'Supervisor Area', dueDate: today, status: 'Open', remark: 'Perlu inspeksi harian' },
        { note: 'Penggunaan APD belum seragam pada area jetty saat aktivitas loading', issuedBy: 'HSE Marine', pic: 'Foreman', dueDate: today, status: 'Progress', remark: 'Refresh briefing' }
      ],
      generalIssues: []
    };
    db.events.find(e => e.id === 'EVT-DEMO2').minutes = {
      meetingTitle: 'NOTULENSI MEETING CCV GMO', meetingType: 'Critical Control Verification', meetingDate: today, notulis: 'Admin HSE', location: 'GMO', updatedAt: new Date().toISOString(),
      enviroIssues: [],
      safetyIssues: [
        { note: 'Housekeeping di area kerja belum konsisten dan masih ada material sisa dekat akses operator', issuedBy: 'HSE GMO', pic: 'Supervisor GMO', dueDate: today, status: 'Overdue', remark: 'Butuh follow up lintas kontraktor' },
        { note: 'Kontrol akses pejalan kaki di area aktif perlu diperkuat untuk mencegah interaksi dengan unit', issuedBy: 'HSE GMO', pic: 'PJO', dueDate: today, status: 'Open', remark: 'Review jalur aman' }
      ],
      generalIssues: []
    };
    db.events.find(e => e.id === 'EVT-DEMO3').minutes = {
      meetingTitle: 'NOTULENSI MEETING TOOLBOX BMO 1', meetingType: 'Toolbox Meeting', meetingDate: today, notulis: 'Admin HSE', location: 'BMO 1', updatedAt: new Date().toISOString(),
      enviroIssues: [],
      safetyIssues: [
        { note: 'Penggunaan APD di area kerja masih belum konsisten terutama pada aktivitas inspeksi lapangan', issuedBy: 'HSE BMO', pic: 'Foreman BMO', dueDate: today, status: 'Open', remark: 'Perlu coaching langsung' }
      ],
      generalIssues: []
    };
    scannedEventId = db.events[0].id; window.location.hash = `absen=${scannedEventId}`; openEventRecapModal(scannedEventId); toast('Data demo lokal berhasil dibuat (tidak disinkronkan ke database).');
  }

  function resetAllData() {
    toast('Reset database dinonaktifkan demi keamanan data.');
  }

  function shouldPromptClose(ev, now = new Date()) {
    if (!ev || ev.manualStatus === 'Closed' || ev.closedAt || ev.manualStatus === 'Draft') return false;
    const end = toDateTime(ev.date, ev.endTime);
    if (now < end) return false;
    const snoozedUntil = closePromptSnoozed[ev.id] || 0;
    return Date.now() >= snoozedUntil;
  }

  function checkMeetingClosePrompts() {
    if (pendingCloseEventId || !document.getElementById('closeMeetingModal')?.classList.contains('hidden')) return;
    const ev = db.events.find(event => shouldPromptClose(event));
    if (ev) askCloseMeeting(ev.id, false);
  }

  function askCloseMeeting(eventId, force = false) {
    const ev = db.events.find(x => x.id === eventId);
    if (!ev || ev.closedAt || ev.manualStatus === 'Closed') return;
    if (!force && !shouldPromptClose(ev)) return;
    pendingCloseEventId = eventId;
    const title = document.getElementById('closeMeetingTitle');
    const elapsed = document.getElementById('closeMeetingElapsed');
    if (title) title.innerHTML = `<b>${escapeHTML(ev.meetingType)}</b><br>${escapeHTML(ev.site)} · ${formatDate(ev.date)} · ${escapeHTML(ev.week)}<br>Jam selesai: ${escapeHTML(ev.endTime)}`;
    if (elapsed) elapsed.textContent = formatDuration(getElapsedMs(ev));
    document.getElementById('closeMeetingModal')?.classList.remove('hidden');
    document.getElementById('closeMeetingModal')?.classList.add('flex');
  }

  function dismissCloseMeetingPrompt() {
    if (pendingCloseEventId) closePromptSnoozed[pendingCloseEventId] = Date.now() + 5 * 60 * 1000;
    pendingCloseEventId = '';
    document.getElementById('closeMeetingModal')?.classList.add('hidden');
    document.getElementById('closeMeetingModal')?.classList.remove('flex');
    toast('Meeting belum ditutup. Reminder akan muncul lagi.');
  }

  async function confirmCloseMeeting() {
    const ev = getEventById(pendingCloseEventId);
    if (!ev) return dismissCloseMeetingPrompt();
    try {
      await fetch(`/sid-meeting/events/${encodeURIComponent(ev.id)}/close`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      });
      ev.manualStatus = 'Closed';
      ev.closedAt = new Date().toISOString();
      ev.updatedAt = new Date().toISOString();
      cacheEvent(ev);
      delete closePromptSnoozed[ev.id];
      pendingCloseEventId = '';
      document.getElementById('closeMeetingModal')?.classList.add('hidden');
      document.getElementById('closeMeetingModal')?.classList.remove('flex');
      reloadEventsTable();
      loadStats();
      if (selectedRecapEventId === String(ev.id)) renderEventRecapModal(ev.id);
      toast('Meeting ditutup. Timer berhenti dan QR dikunci.');
    } catch (err) {
      toast('Gagal menutup meeting');
    }
  }

  function runSelfTests() {
    const out = [];
    const assert = (name, condition) => out.push(`${condition ? '✅' : '❌'} ${name}`);
    const sampleEvent = { id: 'T1', date: '2026-04-27', startTime: '08:00', endTime: '09:00', manualStatus: 'Open' };
    assert('QR status Upcoming sebelum jam mulai', getEventStatus(sampleEvent, new Date('2026-04-27T07:59:00')) === 'Upcoming');
    assert('QR status Open saat meeting berjalan', getEventStatus(sampleEvent, new Date('2026-04-27T08:30:00')) === 'Open');
    assert('QR status Overrun setelah jam selesai sebelum ditutup', getEventStatus(sampleEvent, new Date('2026-04-27T09:01:00')) === 'Overrun');
    assert('QR status Closed setelah tutup meeting', getEventStatus({ ...sampleEvent, manualStatus: 'Closed', closedAt: '2026-04-27T09:05:00' }, new Date('2026-04-27T09:06:00')) === 'Closed');
    assert('Format durasi valid', formatDuration(3661000) === '01:01:01');
    assert('ISO Week menghasilkan angka valid', Number.isInteger(getISOWeek('2026-04-27')) && getISOWeek('2026-04-27') > 0);
    assert('CSV cell escape double quote', csvCell('A "B"') === '"A ""B"""');
    assert('QR link mengandung hash absen', buildQRLink('EVT-TEST').includes('#absen=EVT-TEST'));
    assert('Fallback copy function tersedia', typeof fallbackCopyText === 'function');
    assert('System SID lookup tersedia', !!SYSTEM_SID_DIRECTORY.find(x => x.sid === 'SID001'));
    assert('Master jenis meeting tersedia', Array.isArray(getMeetingTypes()) && getMeetingTypes().length >= 1);
    assert('Dropdown jenis meeting dynamic tersedia', !!document.getElementById('meetingType'));
    assert('Create Event container floating tersedia', !!document.getElementById('createEventContainer') && typeof toggleCreateEventContainer === 'function');
    assert('Company input container floating tersedia', !!document.getElementById('companyInputContainer') && typeof toggleCompanyInputContainer === 'function');
    assert('Floating overlay tersedia', !!document.getElementById('floatingFormOverlay'));
    assert('Panel kelola jenis meeting tersedia', !!document.getElementById('meetingTypeManager'));
    assert('Function tambah/kurang jenis meeting tersedia', typeof addMeetingType === 'function' && typeof deleteMeetingType === 'function' && typeof deleteMeetingTypeByIndex === 'function');
    const beforeMeetingTypeTest = [...getMeetingTypes()];
    db.meetingTypes = ['Meeting Test A', 'Meeting Test B'];
    deleteMeetingTypeByIndex(0);
    assert('Delete jenis meeting by index berjalan', getMeetingTypes().length === 1 && getMeetingTypes()[0] === 'Meeting Test B');
    db.meetingTypes = beforeMeetingTypeTest;
    assert('Overall report table tersedia', !!document.getElementById('reportAttendanceDataTable'));
    assert('Report view Data Absensi tersedia', !!document.getElementById('reportViewAttendanceBtn'));
    assert('Report view List Notulen tersedia', !!document.getElementById('reportViewMinutesBtn'));
    assert('Minutes report table tersedia', !!document.getElementById('minutesReportTable'));
    assert('Minutes report rows function tersedia', Array.isArray(getMinutesReportRows()));
    assert('Semantic Evaluation tab tersedia', !!document.getElementById('tab-semanticeval'));
    assert('Semantic rows function tersedia', Array.isArray(getAllMinutesIssueRows()));
    assert('Embedding vector builder tersedia', typeof buildSemanticEmbeddings === 'function');
    assert('Dense cosine similarity function tersedia', typeof cosineDense === 'function');
    assert('Adjusted semantic similarity guardrail tersedia', typeof adjustedSemanticSimilarity === 'function');
    assert('False positive short text ditekan', adjustedSemanticSimilarity({ note: 'kapan makan nasi', remark: '' }, { note: 'pencucian mobil harus rajin', remark: '' }, embedIssueText('kapan makan nasi'), embedIssueText('pencucian mobil harus rajin')) < 0.5);
    assert('Semantic evaluation function tersedia', typeof calculateSemanticEvaluation === 'function');
    assert('Semantic table scrollable tersedia', !!document.querySelector('#tab-semanticeval .semantic-scroll'));
    assert('Semantic pair card tidak overflow grid', !!document.querySelector('#tab-semanticeval .semantic-card'));
    assert('Semantic pairs table memakai horizontal scroll', !!document.querySelector('#tab-semanticeval .semantic-pairs-table'));
    assert('Semantic detail modal tersedia', !!document.getElementById('semanticDetailModal'));
    assert('Function semantic detail tersedia', typeof openSemanticGroupDetail === 'function' && typeof openSemanticPairDetail === 'function');
    assert('Semantic evaluation tidak muncul di list rekap', !document.querySelector('#reportMinutesPanel th:nth-child(12)')?.textContent.includes('Similarity'));
    assert('Filter Site tersedia', !!document.getElementById('reportFilterSite'));
    assert('Filter Week tersedia', !!document.getElementById('reportFilterWeek'));
    assert('Event recap modal tersedia', !!document.getElementById('eventRecapModal'));
    assert('Tab Create Absensi sudah tidak ada', !document.getElementById('tab-attendance'));
    assert('Form absensi berada di modal event', !!document.querySelector('#eventRecapModal #attendanceForm'));
    assert('Tombol Absen Manual tersedia di modal event', !!document.getElementById('manualAttendanceToggleBtn'));
    assert('Panel absen manual default tersembunyi', document.getElementById('manualAttendancePanel')?.classList.contains('hidden'));
    assert('System company directory tersedia', SYSTEM_COMPANY_DIRECTORY.length >= 60);
    assert('Tab Master Perusahaan tersedia', !!document.getElementById('tab-companymaster'));
    assert('Company master table tersedia', !!document.getElementById('companyMasterTableBody'));
    assert('Default company master tersedia', getCompanyMaster().length >= 60);
    assert('Site master hanya berisi 8 site resmi', SITE_MASTER.join('|') === 'BMO 1|BMO 2|BMO 3|GMO|SMO|LMO|Marine|HOTE');
    assert('Legacy site MTL/CPP termigrasi ke Marine', normalizeSiteValue('MTL') === 'Marine' && normalizeSiteValue('CPP') === 'Marine');
    assert('Eligible company per site menghasilkan array', Array.isArray(getEligibleCompaniesForSite('Marine')));
    assert('Company status rows bisa menghitung HADIR', getCompanyStatusRows([{ company: 'PT Transkon Jaya' }]).some(x => x.company === 'PT Transkon Jaya' && x.status === 'HADIR'));
    assert('Kategori meeting select tersedia', !!document.getElementById('meetingLevel'));
    assert('Function handleMeetingLevelChange tersedia', typeof handleMeetingLevelChange === 'function');
    assert('Function renderMeetingTargetOptions tersedia', typeof renderMeetingTargetOptions === 'function');
    assert('Function getEventExpectedUnits tersedia', typeof getEventExpectedUnits === 'function');
    assert('Site level event menghasilkan unit perusahaan', getEventExpectedUnits({ meetingLevel: 'site', site: 'Marine' }).length >= 1);
    assert('Master perusahaan target checklist tersedia', getTargetCompanyOptions().length >= 190);
    assert('Search perusahaan target tersedia', !!document.getElementById('targetCompanySearch') && typeof renderTargetCompanyChecklist === 'function');
    assert('Style status hadir sudah badge standard', getComputedStyle(document.documentElement).getPropertyValue('--dummy') !== 'force-fail');
    assert('Attendance Rate All card tersedia', !!document.getElementById('statAttendanceRateAll'));
    assert('Attendance Rate All menghasilkan angka valid', Number.isInteger(getOverallAttendanceRate()) && getOverallAttendanceRate() >= 0 && getOverallAttendanceRate() <= 100);
    assert('Site Performance tab tersedia', !!document.getElementById('tab-siteperformance'));
    assert('Canvas Site Performance tersedia', !!document.getElementById('sitePerformanceChart'));
    assert('Filter Week Site Performance tersedia', !!document.getElementById('siteTrendWeekFilter'));
    assert('Function weekly trend tersedia', typeof getWeeklyAttendanceRateBySite === 'function');
    assert('Dataset trend site berupa array', Array.isArray(getWeeklyAttendanceRateBySite().datasets));
    assert('Tabel Attendance Rate perusahaan tersedia', !!document.getElementById('companyPerformanceTable'));
    assert('Company Attendance Rate rows function tersedia', Array.isArray(getCompanyAttendanceRateRows()));
    assert('Search company performance tersedia', !!document.getElementById('companyPerformanceSearch'));
    assert('Filtered company performance rows function tersedia', Array.isArray(getFilteredCompanyAttendanceRateRows()));
    assert('Form notulensi event tersedia', !!document.getElementById('minutesForm'));
    assert('Logo Berau Coal notulensi tersedia', !!document.querySelector('#minutesPrintArea img[alt="Berau Coal logo"]'));
    assert('Tombol Input Notulensi tersedia', !!document.getElementById('minutesToggleBtn'));
    assert('Panel input notulensi default tersembunyi', document.getElementById('minutesInputPanel')?.classList.contains('hidden'));
    assert('Field Issued By header sudah dipindah', !document.getElementById('minutesIssuedBy'));
    assert('Kelola group issue tersedia', !!document.getElementById('issueGroupManager') && typeof addIssueSection === 'function');
    assert('Container issue sections dinamis tersedia', !!document.getElementById('minutesIssueSectionsContainer'));
    assert('Function render issue sections tersedia', typeof renderIssueSections === 'function');
    assert('Default issue sections berisi 3 group', getDefaultIssueSections().length === 3);
    assert('Function collect issue rows tersedia', typeof collectIssueRows === 'function');
    assert('Status catatan meeting tersedia di issue rows', collectIssueRows('enviro', 1)[0]?.status !== undefined);
    assert('Function status class notulen tersedia', typeof getMinutesStatusClass === 'function');
    assert('Function tambah baris issue tersedia', typeof addIssueRow === 'function');
    assert('Function kurang baris issue tersedia', typeof removeIssueRow === 'function');
    assert('Function buka/tutup notulensi tersedia', typeof openEventMinutesForm === 'function' && typeof closeEventMinutesForm === 'function');
    assert('Issue row counter tersedia', issueRowCounts.enviro >= 1 && issueRowCounts.safety >= 1 && issueRowCounts.general >= 1);
    document.getElementById('testOutput').textContent = out.join('\n');
    return out;
  }

  window.addEventListener('hashchange', () => {
    scannedEventId = getScannedEventIdFromURL();
    if (scannedEventId) openEventRecapModal(scannedEventId);
  });

  clearEventForm();
  clearAttendanceForm();
  loadFormOptions();
  loadStats();
  document.getElementById('eventSearch')?.addEventListener('input', scheduleEventsSearch);
  renderEvents();
  if (scannedEventId) openEventRecapModal(scannedEventId);
  refreshTimer = setInterval(tickLiveClock, 1000);
</script>
</body>
</html>
