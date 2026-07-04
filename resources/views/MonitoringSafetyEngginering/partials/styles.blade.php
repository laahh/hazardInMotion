<style>
   .mse-badge {
      display: inline-flex;
      align-items: center;
      border-radius: 0.375rem;
      padding: 0.125rem 0.5rem;
      font-size: 10px;
      font-weight: 600;
      border: 1px solid rgba(30, 58, 95, 0.12);
      background: rgba(30, 58, 95, 0.06);
      color: #1e3a5f;
   }
   .mse-card {
      background: #ffffff;
      border: 1px solid rgba(30, 58, 95, 0.08);
      border-radius: 0.875rem;
      box-shadow: 0 1px 3px rgba(26, 35, 50, 0.04), 0 6px 20px -4px rgba(30, 58, 95, 0.07);
   }
   .mse-kpi-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 2.75rem;
      height: 2.75rem;
      border-radius: 9999px;
      flex-shrink: 0;
   }
   .mse-kpi-icon--navy { background: linear-gradient(135deg, #1e3a5f, #2d5a8e); color: #fff; }
   .mse-kpi-icon--blue { background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; }
   .mse-kpi-icon--green { background: linear-gradient(135deg, #15803d, #22c55e); color: #fff; }
   .mse-kpi-icon--lime { background: linear-gradient(135deg, #65a30d, #84cc16); color: #fff; }
   .mse-progress-track {
      height: 5px;
      border-radius: 9999px;
      background: #e2e8f0;
      overflow: hidden;
   }
   .mse-progress-fill {
      height: 100%;
      border-radius: 9999px;
      transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
   }
   .mse-progress-fill--blue { background: linear-gradient(90deg, #2563eb, #3b82f6); }
   .mse-progress-fill--green { background: linear-gradient(90deg, #15803d, #22c55e); }
   .mse-progress-fill--lime { background: linear-gradient(90deg, #65a30d, #84cc16); }
   .mse-filter-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border-radius: 0.5rem;
      padding: 0.45rem 0.85rem;
      font-size: 0.75rem;
      font-weight: 600;
      border: 1px solid #d1d5db;
      background: #fff;
      color: #374151;
      transition: border-color 0.2s, box-shadow 0.2s;
   }
   .mse-filter-btn:hover { border-color: #1e3a5f; box-shadow: 0 1px 4px rgba(30, 58, 95, 0.08); }
   .mse-filter-btn--active {
      background: #1e3a5f;
      border-color: #1e3a5f;
      color: #fff;
   }
   .mse-overdue-box {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 0.625rem;
      padding: 0.65rem 1rem;
      font-size: 0.6875rem;
   }
   .mse-overdue-value { font-weight: 700; color: #b91c1c; }
   .mse-overdue-value--zero { color: #9ca3af; }
   .mse-table-wrap { overflow-x: auto; }
   .mse-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8125rem; }
   .mse-table thead th {
      background: #1e3a5f;
      color: #fff;
      font-weight: 600;
      font-size: 0.6875rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      padding: 0.75rem 0.875rem;
      text-align: left;
      white-space: nowrap;
   }
   .mse-table thead th:first-child { border-radius: 0.5rem 0 0 0; }
   .mse-table thead th:last-child { border-radius: 0 0.5rem 0 0; }
   .mse-table tbody td {
      padding: 0.7rem 0.875rem;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: middle;
   }
   .mse-table tbody tr:nth-child(even) td { background: #f8fafc; }
   .mse-table tbody tr:hover td { background: #eff6ff; }
   .mse-pct {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 3rem;
      padding: 0.2rem 0.5rem;
      border-radius: 0.375rem;
      font-size: 0.75rem;
      font-weight: 700;
   }
   .mse-pct--green { background: #dcfce7; color: #15803d; }
   .mse-pct--amber { background: #fef3c7; color: #b45309; }
   .mse-pct--orange { background: #ffedd5; color: #c2410c; }
   .mse-pct--red { background: #fee2e2; color: #b91c1c; }
   .mse-overdue-cell { font-weight: 700; }
   .mse-overdue-cell--active { background: #fef2f2; color: #b91c1c; border-radius: 0.375rem; padding: 0.15rem 0.5rem; }
   .mse-section-title {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 700;
      font-size: 1rem;
      color: #1e3a5f;
   }
   .mse-section-accent {
      width: 0.5rem;
      height: 1.25rem;
      border-radius: 0.125rem;
      background: #1e3a5f;
      flex-shrink: 0;
   }
   .mse-sidebar-header {
      padding: 0.75rem 1rem;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #fff;
      border-radius: 0.625rem 0.625rem 0 0;
   }
   .mse-sidebar-header--navy { background: #1e3a5f; }
   .mse-sidebar-header--green { background: #15803d; }
   .mse-sidebar-body {
      padding: 1rem 1.125rem;
      font-size: 0.8125rem;
      line-height: 1.6;
      color: #374151;
   }
   .mse-sidebar-body ul { list-style: disc; padding-left: 1.125rem; }
   .mse-sidebar-body li { margin-bottom: 0.5rem; }
   .mse-sidebar-body li:last-child { margin-bottom: 0; }
   .mse-category-tab {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.4rem 0.85rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      border: 1px solid #e5e7eb;
      background: #fff;
      color: #6b7280;
      transition: all 0.2s;
      cursor: pointer;
      text-decoration: none;
   }
   .mse-category-tab:hover { border-color: #1e3a5f; color: #1e3a5f; }
   .mse-category-tab--active {
      background: #1e3a5f;
      border-color: #1e3a5f;
      color: #fff;
   }
   .mse-filter-select {
      background: #fff;
      border: 1px solid #d1d5db;
      border-radius: 0.5rem;
      padding: 0.45rem 0.75rem;
      font-size: 0.75rem;
      font-weight: 600;
      color: #374151;
   }
   .mse-filter-select:focus {
      outline: none;
      border-color: #1e3a5f;
      box-shadow: 0 0 0 2px rgba(30, 58, 95, 0.12);
   }
   .mse-form-label {
      display: block;
      font-size: 0.6875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #595c5e;
      margin-bottom: 0.35rem;
   }
   .mse-form-input {
      width: 100%;
      background: #fff;
      border: 1px solid #d1d5db;
      border-radius: 0.5rem;
      padding: 0.55rem 0.75rem;
      font-size: 0.8125rem;
      font-weight: 500;
      color: #374151;
   }
   .mse-form-input:focus {
      outline: none;
      border-color: #1e3a5f;
      box-shadow: 0 0 0 2px rgba(30, 58, 95, 0.12);
   }
   .mse-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border-radius: 0.5rem;
      padding: 0.55rem 1rem;
      font-size: 0.8125rem;
      font-weight: 700;
      transition: opacity 0.2s;
      text-decoration: none;
   }
   .mse-btn:hover { opacity: 0.92; }
   .mse-btn--primary { background: #1e3a5f; color: #fff; border: none; cursor: pointer; }
   .mse-btn--secondary { background: #fff; border: 1px solid #d1d5db; color: #374151; }
   .mse-alert-info {
      border: 1px solid #bfdbfe;
      background: #eff6ff;
      color: #1e40af;
      border-radius: 0.625rem;
      padding: 0.75rem 1rem;
      font-size: 0.8125rem;
   }

   /* ---- Page header (white base) ---- */
   .mse-page-header {
      position: relative;
      background: #ffffff;
      border: 1px solid rgba(30, 58, 95, 0.08);
      border-radius: 1rem;
      padding: 1.35rem 1.75rem 1.5rem;
      box-shadow: 0 1px 3px rgba(26, 35, 50, 0.04), 0 6px 20px -4px rgba(30, 58, 95, 0.06);
   }
   .mse-page-header::before {
      content: "";
      position: absolute;
      left: 1.75rem;
      right: 1.75rem;
      bottom: 0;
      height: 3px;
      border-radius: 9999px;
      background: linear-gradient(90deg, #15803d, #22c55e 45%, #2563eb);
   }
   .mse-page-header-eyebrow {
      display: flex;
      align-items: center;
      gap: 0.35rem;
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      color: #8a8f93;
      margin-bottom: 0.35rem;
   }

   /* ---- KPI cards ---- */
   .mse-kpi-card {
      position: relative;
      overflow: hidden;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
   }
   .mse-kpi-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 28px -8px rgba(30, 58, 95, 0.18);
   }
   .mse-kpi-card::before {
      content: "";
      position: absolute;
      inset: 0 0 auto 0;
      height: 3px;
      background: var(--mse-kpi-accent, #1e3a5f);
   }
   .mse-kpi-card--navy { --mse-kpi-accent: linear-gradient(90deg, #1e3a5f, #2d5a8e); }
   .mse-kpi-card--blue { --mse-kpi-accent: linear-gradient(90deg, #2563eb, #3b82f6); }
   .mse-kpi-card--green { --mse-kpi-accent: linear-gradient(90deg, #15803d, #22c55e); }
   .mse-kpi-card--lime { --mse-kpi-accent: linear-gradient(90deg, #65a30d, #84cc16); }
   .mse-kpi-card::before { background: var(--mse-kpi-accent); }

   /* ---- Chart cards ---- */
   .mse-chart-card { padding: 1.15rem 1.25rem 1.25rem; display: flex; flex-direction: column; gap: 0.75rem; }
   .mse-chart-card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem; }
   .mse-chart-title { font-weight: 700; font-size: 0.8125rem; color: #1e3a5f; }
   .mse-chart-subtitle { font-size: 0.6875rem; color: #8a8f93; font-weight: 500; margin-top: 0.1rem; }
   .mse-chart-canvas-wrap { position: relative; width: 100%; }
   .mse-donut-center {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      pointer-events: none;
   }
   .mse-donut-center-value { font-size: 1.5rem; font-weight: 800; color: #1e3a5f; line-height: 1; }
   .mse-donut-center-label { font-size: 0.625rem; font-weight: 600; color: #8a8f93; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 0.2rem; }
   .mse-chart-legend { display: flex; flex-wrap: wrap; gap: 0.4rem 0.9rem; }
   .mse-chart-legend-item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.6875rem; font-weight: 600; color: #4b5563; }
   .mse-chart-legend-dot { width: 0.55rem; height: 0.55rem; border-radius: 9999px; flex-shrink: 0; }
   .mse-chart-legend-value { color: #1a2332; font-weight: 700; }

   /* ---- Overdue mini bars ---- */
   .mse-overdue-item { display: flex; flex-direction: column; gap: 0.2rem; padding: 0.3rem 0; }
   .mse-overdue-row { display: flex; justify-content: space-between; gap: 1rem; }
   .mse-overdue-track { height: 4px; border-radius: 9999px; background: #f1f2f4; overflow: hidden; }
   .mse-overdue-fill { height: 100%; border-radius: 9999px; background: linear-gradient(90deg, #b91c1c, #ef4444); transition: width 0.6s ease; }
   .mse-overdue-fill--zero { background: #d1d5db; }

   /* ---- Status pills (mitra kerja / effectiveness tables) ---- */
   .mse-status {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.2rem 0.6rem;
      border-radius: 9999px;
      font-size: 0.6875rem;
      font-weight: 700;
      white-space: nowrap;
   }
   .mse-status::before { content: ""; width: 0.4rem; height: 0.4rem; border-radius: 9999px; background: currentColor; flex-shrink: 0; }
   .mse-status--ontrack { background: #dbeafe; color: #1d4ed8; }
   .mse-status--acceleration { background: #ffedd5; color: #c2410c; }
   .mse-status--critical { background: #fee2e2; color: #b91c1c; }
   .mse-status--closed { background: #dcfce7; color: #15803d; }
   .mse-status--neutral { background: #f1f5f9; color: #475569; }

   /* ---- Table header color variants ---- */
   .mse-table--red thead th { background: #b91c1c; }
   .mse-table--green thead th { background: #15803d; }
   .mse-table--amber thead th { background: #b45309; }
   .mse-table--compact td, .mse-table--compact th { padding: 0.55rem 0.7rem; font-size: 0.75rem; }

   /* ---- Matrix / reference table ---- */
   .mse-matrix-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
   .mse-matrix-table th {
      background: #f1f5f9;
      color: #1e3a5f;
      font-weight: 700;
      text-align: left;
      padding: 0.55rem 0.75rem;
      border: 1px solid #e2e8f0;
      white-space: nowrap;
   }
   .mse-matrix-table td { padding: 0.55rem 0.75rem; border: 1px solid #e2e8f0; color: #374151; vertical-align: top; }
   .mse-matrix-table tbody tr:nth-child(even) td { background: #f8fafc; }

   /* ---- Mini stat band (compact KPI strip for summary pages) ---- */
   .mse-stat-mini {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      padding: 0.9rem 1rem;
   }
   .mse-stat-mini-value { font-size: 1.5rem; font-weight: 800; color: #1e3a5f; line-height: 1; }
   .mse-stat-mini-label { font-size: 0.6875rem; font-weight: 600; color: #8a8f93; margin-top: 0.15rem; }

   /* ---- Level chip (Turun 1/2/3 Tangga, Naik Level, dsb) ---- */
   .mse-level-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.2rem 0.55rem;
      border-radius: 0.375rem;
      font-size: 0.6875rem;
      font-weight: 700;
   }
   .mse-level-chip--good { background: #dcfce7; color: #15803d; }
   .mse-level-chip--warn { background: #fef3c7; color: #b45309; }
   .mse-level-chip--bad { background: #fee2e2; color: #b91c1c; }
   .mse-level-chip--neutral { background: #f1f5f9; color: #475569; }

   /* ---- Dashboard enhancements ---- */
   .mse-dash-fade-in {
      animation: mseDashFadeIn 0.45s ease both;
   }
   .mse-dash-fade-in--1 { animation-delay: 0.04s; }
   .mse-dash-fade-in--2 { animation-delay: 0.08s; }
   .mse-dash-fade-in--3 { animation-delay: 0.12s; }
   .mse-dash-fade-in--4 { animation-delay: 0.16s; }
   @keyframes mseDashFadeIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
   }

   .mse-dash-filter-panel {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      border: 1px solid rgba(30, 58, 95, 0.1);
      border-radius: 0.875rem;
      padding: 0.85rem 1rem;
   }
   .mse-dash-filter-panel-title {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.625rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: #1e3a5f;
      margin-bottom: 0.65rem;
   }
   .mse-dash-filter-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.25rem 0.6rem;
      border-radius: 9999px;
      font-size: 0.625rem;
      font-weight: 600;
      background: rgba(30, 58, 95, 0.07);
      color: #1e3a5f;
      border: 1px solid rgba(30, 58, 95, 0.1);
   }

   .mse-dash-overdue-alert {
      background: linear-gradient(135deg, #fff5f5 0%, #ffffff 60%);
      border: 1px solid rgba(185, 28, 28, 0.15);
      border-radius: 0.875rem;
      padding: 0.85rem 1rem;
      min-width: 15rem;
   }
   .mse-dash-overdue-alert--clean {
      background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 60%);
      border-color: rgba(21, 128, 61, 0.15);
   }
   .mse-dash-overdue-total {
      font-size: 1.75rem;
      font-weight: 800;
      line-height: 1;
      color: #b91c1c;
   }
   .mse-dash-overdue-total--zero { color: #15803d; }

   .mse-dash-insight-band {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.75rem;
   }
   @media (min-width: 640px) {
      .mse-dash-insight-band { grid-template-columns: repeat(4, minmax(0, 1fr)); }
   }
   .mse-dash-insight-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.85rem 1rem;
      border-radius: 0.75rem;
      background: rgba(30, 58, 95, 0.03);
      border: 1px solid rgba(30, 58, 95, 0.06);
      transition: background 0.2s, border-color 0.2s;
   }
   .mse-dash-insight-item:hover {
      background: rgba(30, 58, 95, 0.05);
      border-color: rgba(30, 58, 95, 0.1);
   }
   .mse-dash-insight-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 2.25rem;
      height: 2.25rem;
      border-radius: 0.625rem;
      flex-shrink: 0;
      font-size: 1.1rem;
   }
   .mse-dash-insight-value {
      font-size: 1.25rem;
      font-weight: 800;
      color: #1e3a5f;
      line-height: 1.1;
   }
   .mse-dash-insight-label {
      font-size: 0.625rem;
      font-weight: 600;
      color: #8a8f93;
      margin-top: 0.1rem;
   }

   .mse-dash-kpi-ring {
      position: relative;
      width: 3.25rem;
      height: 3.25rem;
      flex-shrink: 0;
   }
   .mse-dash-kpi-ring svg {
      width: 100%;
      height: 100%;
      transform: rotate(-90deg);
   }
   .mse-dash-kpi-ring-track {
      fill: none;
      stroke: #e2e8f0;
      stroke-width: 3;
   }
   .mse-dash-kpi-ring-fill {
      fill: none;
      stroke-width: 3;
      stroke-linecap: round;
      transition: stroke-dashoffset 1s cubic-bezier(0.4, 0, 0.2, 1);
   }
   .mse-dash-kpi-ring-center {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.625rem;
      font-weight: 800;
      color: #1e3a5f;
   }

   .mse-dash-section-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.25rem;
   }
   .mse-dash-section-head-title {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 700;
      font-size: 0.875rem;
      color: #1e3a5f;
   }
   .mse-dash-section-head-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 1.75rem;
      height: 1.75rem;
      border-radius: 0.5rem;
      background: rgba(30, 58, 95, 0.08);
      color: #1e3a5f;
   }

   .mse-dash-progress-mini {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.25rem;
      min-width: 4.5rem;
   }
   .mse-dash-progress-mini-track {
      width: 100%;
      height: 4px;
      border-radius: 9999px;
      background: #e2e8f0;
      overflow: hidden;
   }
   .mse-dash-progress-mini-fill {
      height: 100%;
      border-radius: 9999px;
      transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
   }
   .mse-dash-progress-mini-fill--green { background: linear-gradient(90deg, #15803d, #22c55e); }
   .mse-dash-progress-mini-fill--amber { background: linear-gradient(90deg, #b45309, #f59e0b); }
   .mse-dash-progress-mini-fill--orange { background: linear-gradient(90deg, #c2410c, #f97316); }
   .mse-dash-progress-mini-fill--red { background: linear-gradient(90deg, #b91c1c, #ef4444); }

   .mse-dash-table-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.85rem 1.25rem;
      background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
      border-bottom: 1px solid #f1f5f9;
   }
   .mse-dash-item-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.3rem 0.65rem;
      border-radius: 9999px;
      font-size: 0.6875rem;
      font-weight: 700;
      background: rgba(30, 58, 95, 0.08);
      color: #1e3a5f;
   }
   .mse-dash-category-tab {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.45rem 0.9rem;
      border-radius: 0.625rem;
      font-size: 0.75rem;
      font-weight: 600;
      border: 1px solid #e5e7eb;
      background: #fff;
      color: #6b7280;
      transition: all 0.2s;
      text-decoration: none;
   }
   .mse-dash-category-tab:hover {
      border-color: #1e3a5f;
      color: #1e3a5f;
      box-shadow: 0 2px 8px rgba(30, 58, 95, 0.08);
   }
   .mse-dash-category-tab--active {
      background: linear-gradient(135deg, #1e3a5f, #2d5a8e);
      border-color: transparent;
      color: #fff;
      box-shadow: 0 4px 12px rgba(30, 58, 95, 0.25);
   }
   .mse-dash-category-tab-count {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 1.25rem;
      height: 1.25rem;
      padding: 0 0.3rem;
      border-radius: 9999px;
      font-size: 0.625rem;
      font-weight: 700;
      background: rgba(0, 0, 0, 0.08);
   }
   .mse-dash-category-tab--active .mse-dash-category-tab-count {
      background: rgba(255, 255, 255, 0.2);
   }

   .mse-table tbody tr.mse-dash-row--overdue td:first-child {
      box-shadow: inset 3px 0 0 #ef4444;
   }
   .mse-table tbody tr.mse-dash-row--complete td:first-child {
      box-shadow: inset 3px 0 0 #22c55e;
   }
   .mse-table tfoot td {
      padding: 0.75rem 0.875rem;
      background: #f1f5f9;
      border-top: 2px solid #e2e8f0;
      font-weight: 700;
      font-size: 0.75rem;
      color: #1e3a5f;
   }

   .mse-dash-empty-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 3rem 1.5rem;
      color: #8a8f93;
   }
   .mse-dash-empty-state .material-symbols-outlined {
      font-size: 2.5rem;
      opacity: 0.4;
   }

   .mse-dash-sidebar-point {
      display: flex;
      gap: 0.6rem;
      margin-bottom: 0.55rem;
      font-size: 0.8125rem;
      line-height: 1.55;
      color: #374151;
   }
   .mse-dash-sidebar-point:last-child { margin-bottom: 0; }
   .mse-dash-sidebar-point-icon {
      flex-shrink: 0;
      width: 1.25rem;
      height: 1.25rem;
      border-radius: 9999px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.625rem;
      font-weight: 800;
      margin-top: 0.15rem;
   }
   .mse-dash-sidebar-point-icon--navy { background: #dbeafe; color: #1e3a5f; }
   .mse-dash-sidebar-point-icon--green { background: #dcfce7; color: #15803d; }

   .mse-dash-todo-item {
      display: flex;
      gap: 0.65rem;
      align-items: flex-start;
      padding: 0.55rem 0;
      border-bottom: 1px solid #f1f5f9;
      font-size: 0.8125rem;
      line-height: 1.55;
      color: #374151;
   }
   .mse-dash-todo-item:last-child { border-bottom: none; padding-bottom: 0; }
   .mse-dash-todo-num {
      flex-shrink: 0;
      width: 1.35rem;
      height: 1.35rem;
      border-radius: 0.375rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.625rem;
      font-weight: 800;
      background: linear-gradient(135deg, #15803d, #22c55e);
      color: #fff;
   }

   .mse-dash-hero-kpi {
      background: linear-gradient(135deg, #1e3a5f 0%, #2d5a8e 55%, #1e3a5f 100%);
      color: #fff;
      border: none;
      position: relative;
      overflow: hidden;
   }
   .mse-dash-hero-kpi::after {
      content: "";
      position: absolute;
      top: -40%;
      right: -15%;
      width: 10rem;
      height: 10rem;
      border-radius: 9999px;
      background: rgba(255, 255, 255, 0.06);
      pointer-events: none;
   }
   .mse-dash-hero-kpi .mse-dash-insight-label { color: rgba(255, 255, 255, 0.7); }
   .mse-dash-hero-kpi .mse-dash-kpi-ring-track { stroke: rgba(255, 255, 255, 0.2); }
   .mse-dash-hero-kpi .mse-dash-kpi-ring-center { color: #fff; }
</style>
