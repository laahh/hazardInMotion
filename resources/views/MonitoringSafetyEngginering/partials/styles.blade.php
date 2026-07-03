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
      border-radius: 0.75rem;
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
   .mse-overdue-item { display: flex; justify-content: space-between; gap: 1rem; padding: 0.15rem 0; }
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
</style>
