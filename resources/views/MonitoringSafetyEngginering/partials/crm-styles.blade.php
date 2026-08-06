<style>
   /* ---- CRM Layout ---- */
   .crm-avatar {
      display: flex; align-items: center; justify-content: center;
      width: 2rem; height: 2rem; border-radius: 9999px;
      background: linear-gradient(135deg, #7366FF, #a599ff);
      color: #fff; font-size: 0.625rem; font-weight: 700; flex-shrink: 0;
   }
   .crm-avatar--lg { width: 2.5rem; height: 2.5rem; font-size: 0.75rem; }
   .crm-nav-link {
      display: flex; align-items: center; gap: 0.65rem;
      padding: 0.6rem 0.85rem; border-radius: 0.625rem;
      font-size: 0.8125rem; font-weight: 500; color: #848488;
      text-decoration: none; transition: all 0.2s;
   }
   .crm-nav-link:hover { background: #F4F7F9; color: #7366FF; }
   .crm-nav-link--active {
      background: #ECE9FF; color: #7366FF; font-weight: 600;
   }
   .crm-promo-card {
      background: linear-gradient(135deg, #ECE9FF 0%, #f8f7ff 100%);
      border-radius: 0.875rem; padding: 1rem;
      border: 1px solid rgba(115, 102, 255, 0.12);
   }
   .crm-promo-btn {
      display: inline-block; padding: 0.45rem 0.85rem;
      background: #7366FF; color: #fff; border-radius: 0.5rem;
      font-size: 0.6875rem; font-weight: 600; text-decoration: none;
      transition: opacity 0.2s;
   }
   .crm-promo-btn:hover { opacity: 0.9; }
   .crm-topbar { box-shadow: 0 1px 4px rgba(47, 47, 58, 0.04); }
   .crm-icon-btn {
      display: inline-flex; align-items: center; justify-content: center;
      width: 2.25rem; height: 2.25rem; border-radius: 0.5rem;
      color: #848488; background: transparent; border: none; cursor: pointer;
      transition: background 0.2s, color 0.2s;
   }
   .crm-icon-btn:hover { background: #F4F7F9; color: #7366FF; }
   .crm-search-wrap { position: relative; }
   .crm-search-icon {
      position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%);
      font-size: 1.125rem; color: #848488; pointer-events: none;
   }
   .crm-search-input {
      width: 100%; padding: 0.55rem 0.85rem 0.55rem 2.5rem;
      background: #F4F7F9; border: 1px solid transparent; border-radius: 9999px;
      font-size: 0.8125rem; color: #2F2F3A; outline: none;
   }
   .crm-search-input:focus { border-color: rgba(115, 102, 255, 0.3); background: #fff; }

   /* ---- CRM Cards ---- */
   .crm-card {
      background: #fff; border-radius: 0.875rem;
      border: 1px solid #E6E9EB;
      box-shadow: 0 2px 12px rgba(47, 47, 58, 0.04);
      padding: 1.25rem;
   }
   .crm-stat-card { padding: 1.35rem 1.25rem; }
   .crm-stat-card--clickable {
      cursor: pointer; transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
   }
   .crm-stat-card--clickable:hover {
      border-color: #CFC8FF;
      box-shadow: 0 8px 24px rgba(115, 102, 255, 0.12);
      transform: translateY(-1px);
   }
   .crm-stat-card--clickable:focus-visible {
      outline: 2px solid #7366FF; outline-offset: 2px;
   }
   .crm-stat-label {
      font-size: 0.8125rem; font-weight: 500; color: #848488; margin-bottom: 0.5rem;
   }
   .crm-stat-main {
      display: flex; align-items: flex-start; gap: 0.85rem;
   }
   .crm-stat-value {
      font-size: 1.75rem; font-weight: 700; color: #2F2F3A; line-height: 1.1;
   }
   .crm-stat-meta {
      display: flex; flex-direction: column; gap: 0.15rem;
      padding-top: 0.2rem;
      font-size: 0.75rem; font-weight: 500; color: #2F2F3A; line-height: 1.25;
   }
   .crm-stat-trend {
      display: inline-flex; align-items: center; gap: 0.2rem;
      margin-top: 0.65rem; font-size: 0.75rem; font-weight: 600;
   }
   .crm-stat-trend--up { color: #51BB25; }
   .crm-stat-trend--down { color: #FF5B5B; }
   .crm-category-summary {
      display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.55rem;
      margin-bottom: 0.85rem;
   }
   @media (min-width: 640px) {
      .crm-category-summary { grid-template-columns: repeat(4, minmax(0, 1fr)); }
   }
   .crm-category-summary-item {
      border: 1px solid #E8EAED; border-radius: 0.65rem; background: #F8F9FB;
      padding: 0.7rem 0.85rem; min-width: 0;
   }
   .crm-category-summary-item--accent {
      background: #F3F1FF; border-color: #DDD8FF;
   }
   .crm-category-summary-label {
      display: block; font-size: 0.625rem; font-weight: 700; color: #848488;
      text-transform: uppercase; letter-spacing: 0.04em;
   }
   .crm-category-summary-value {
      display: block; margin-top: 0.25rem; font-size: 0.9375rem; font-weight: 700;
      color: #2F2F3A; line-height: 1.25;
   }
   .crm-category-summary-value--lg {
      font-size: 1.25rem; font-weight: 800; letter-spacing: -0.02em;
   }
   .crm-category-panel { width: min(760px, 100%); }
   .crm-category-panel--xl .crm-history-body {
      padding: 1.1rem 1.4rem 1.35rem;
   }
   .crm-category-panel--xl .crm-category-chart-wrap {
      height: 270px;
   }
   .crm-category-panel--xl .crm-category-chart-wrap--pie {
      height: 280px;
   }
   .crm-category-modal-filters {
      display: flex;
      flex-wrap: wrap;
      gap: 0.65rem 0.85rem;
      align-items: flex-end;
      margin-bottom: 0.9rem;
      padding: 0.75rem 0.85rem;
      border: 1px solid #E8EAED;
      border-radius: 0.75rem;
      background: #F8F9FB;
   }
   .crm-category-modal-filter {
      display: flex;
      flex-direction: column;
      gap: 0.3rem;
      min-width: 160px;
      flex: 1 1 180px;
      max-width: 260px;
   }
   .crm-category-modal-filter label {
      font-size: 0.6875rem;
      font-weight: 700;
      color: #848488;
      text-transform: uppercase;
      letter-spacing: 0.04em;
   }
   .crm-category-modal-filter select {
      width: 100%;
      appearance: none;
      border: 1px solid #E6E9EB;
      background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23848488' d='M1 1l5 5 5-5'/%3E%3C/svg%3E") no-repeat right 0.75rem center;
      border-radius: 0.55rem;
      padding: 0.5rem 2rem 0.5rem 0.75rem;
      font-size: 0.8125rem;
      font-weight: 600;
      color: #2F2F3A;
      outline: none;
   }
   .crm-category-modal-filter select:focus {
      border-color: #CFC8FF;
      box-shadow: 0 0 0 3px rgba(115, 102, 255, 0.12);
   }
   .crm-category-charts {
      display: grid;
      grid-template-columns: 1fr;
      gap: 0.85rem;
      margin-bottom: 1rem;
   }
   @media (min-width: 900px) {
      .crm-category-charts {
         grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr) minmax(0, 1.1fr);
      }
   }
   .crm-category-chart-card {
      border: 1px solid #E8EAED;
      border-radius: 0.75rem;
      background: #FCFCFD;
      padding: 0.85rem 0.95rem 0.7rem;
      min-height: 0;
   }
   .crm-category-chart-title {
      font-size: 0.75rem;
      font-weight: 700;
      color: #2F2F3A;
      margin-bottom: 0.55rem;
      letter-spacing: -0.01em;
   }
   .crm-category-chart-wrap {
      position: relative;
      height: 210px;
   }
   .crm-category-chart-wrap--pie { height: 220px; }
   .crm-category-section-title {
      font-size: 0.8125rem;
      font-weight: 700;
      color: #2F2F3A;
      margin: 0.15rem 0 0.65rem;
   }
   .crm-status-pill {
      display: inline-flex;
      align-items: center;
      padding: 0.15rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.6875rem;
      font-weight: 700;
      line-height: 1.3;
      white-space: nowrap;
   }
   .crm-status-pill--onprogress { background: #E8F2FF; color: #2563eb; }
   .crm-status-pill--overdue { background: #FEECEC; color: #dc2626; }
   .crm-status-pill--selesai { background: #E8F9E5; color: #15803d; }
   .crm-category-hint {
      display: none;
   }
   .crm-modal-toolbar {
      display: flex; flex-wrap: wrap; align-items: center; gap: 0.45rem;
      margin-bottom: 0.85rem;
   }
   .crm-modal-stat-pill {
      display: inline-flex; align-items: center; gap: 0.4rem;
      padding: 0.4rem 0.7rem; border-radius: 9999px;
      background: #F4F7F9; border: 1px solid #E8EAED;
      font-size: 0.75rem; color: #2F2F3A; font-weight: 600;
   }
   .crm-modal-stat-pill strong {
      font-weight: 800; color: #5B4FE0;
   }
   .crm-modal-stat-pill--soft {
      background: #F8F9FB; color: #6B7280; font-weight: 500;
   }
   .crm-modal-table-wrap {
      border: 1px solid #E8EAED; border-radius: 0.75rem; overflow: hidden;
      background: #fff; max-height: min(52vh, 480px); overflow-y: auto;
   }
   .crm-modal-table-wrap .crm-data-table { font-size: 0.8125rem; }
   .crm-modal-table-wrap .crm-data-table thead th {
      position: sticky; top: 0; z-index: 1;
      background: #F4F7F9; color: #6B7280; font-weight: 700;
      font-size: 0.625rem; letter-spacing: 0.04em;
      padding: 0.55rem 0.75rem; border-bottom: 1px solid #E8EAED;
   }
   .crm-modal-table-wrap .crm-data-table thead th:first-child,
   .crm-modal-table-wrap .crm-data-table thead th:last-child { border-radius: 0; }
   .crm-modal-table-wrap .crm-data-table tbody td {
      padding: 0.7rem 0.75rem; border-bottom: 1px solid #F0F2F5;
      vertical-align: middle;
   }
   .crm-modal-table-wrap .crm-data-table tbody tr:nth-child(even) td { background: #FCFCFD; }
   .crm-modal-table-wrap .crm-data-table tbody tr:last-child td { border-bottom: none; }
   .crm-modal-table-wrap .crm-data-table tbody tr.crm-row--clickable:hover td {
      background: #F5F3FF;
   }
   .crm-modal-name {
      display: flex; flex-direction: column; align-items: flex-start; gap: 0.3rem;
      font-weight: 600; color: #2F2F3A; line-height: 1.35;
   }
   .crm-modal-name-top {
      display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem;
   }
   .crm-modal-name-title { font-weight: 700; }
   .crm-modal-meta {
      font-size: 0.6875rem; color: #848488; font-weight: 500; line-height: 1.3;
   }
   .crm-modal-badge {
      display: inline-flex; align-items: center;
      padding: 0.08rem 0.4rem; border-radius: 9999px;
      font-size: 0.5625rem; font-weight: 700; letter-spacing: 0.02em;
      background: #F3F1FF; color: #5B4FE0; text-transform: lowercase;
      flex-shrink: 0;
   }
   .crm-modal-badge--warn {
      background: #FFF7ED; color: #C2410C;
   }
   .crm-modal-chip {
      display: inline-flex; align-items: center; justify-content: center;
      max-width: 100%;
      padding: 0.2rem 0.5rem; border-radius: 0.4rem;
      font-size: 0.6875rem; font-weight: 600; line-height: 1.25;
      background: #EEF2FF; color: #4338CA;
      white-space: normal; text-align: center;
   }
   .crm-modal-chip--muted {
      background: #F4F7F9; color: #4B5563; font-weight: 600;
   }
   .crm-modal-chip--ok {
      background: #ECFDF5; color: #047857;
   }
   .crm-modal-empty {
      text-align: center; color: #848488; font-size: 0.8125rem;
      padding: 2rem 1rem;
   }
   .crm-modal-col-no { width: 2.5rem; color: #9CA3AF !important; }
   .crm-modal-col-side { width: 9.5rem; }

   .crm-card-title {
      font-size: 0.9375rem; font-weight: 600; color: #2F2F3A; margin-bottom: 1rem;
   }
   .crm-chart-wrap { position: relative; height: 220px; }
   .crm-donut-center {
      position: absolute; inset: 0;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      pointer-events: none;
   }
   .crm-donut-total-label { font-size: 0.6875rem; color: #848488; font-weight: 500; }
   .crm-donut-total-value { font-size: 1.375rem; font-weight: 700; color: #2F2F3A; line-height: 1.2; }

   .crm-legend { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 0.75rem; }
   .crm-legend-item {
      display: flex; align-items: center; gap: 0.4rem;
      font-size: 0.75rem; color: #848488; font-weight: 500;
   }
   .crm-legend-dot { width: 0.5rem; height: 0.5rem; border-radius: 9999px; flex-shrink: 0; }

   /* ---- Application progress list ---- */
   .crm-app-stack {
      display: flex; height: 6px; border-radius: 9999px; overflow: hidden; margin-bottom: 1.25rem;
   }
   .crm-app-stack-seg { height: 100%; transition: width 0.6s ease; }
   .crm-app-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.55rem 0; border-bottom: 1px solid #F4F7F9;
   }
   .crm-app-row:last-child { border-bottom: none; }
   .crm-app-row-left { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; color: #2F2F3A; font-weight: 500; }
   .crm-app-row-pct { font-size: 0.8125rem; font-weight: 600; color: #2F2F3A; }

   /* ---- Table (Recruitment style) ---- */
   .crm-table { width: 100%; border-collapse: collapse; }
   .crm-table thead th {
      text-align: left; font-size: 0.6875rem; font-weight: 600;
      color: #848488; text-transform: uppercase; letter-spacing: 0.04em;
      padding: 0 0.5rem 0.75rem; border-bottom: 1px solid #E6E9EB;
   }
   .crm-table tbody td {
      padding: 0.75rem 0.5rem; font-size: 0.8125rem; color: #2F2F3A;
      border-bottom: 1px solid #F4F7F9; vertical-align: middle;
   }
   .crm-table tbody tr:last-child td { border-bottom: none; }
   .crm-table-name {
      display: flex; align-items: center; gap: 0.65rem; font-weight: 500;
   }
   .crm-table-avatar {
      width: 2rem; height: 2rem; border-radius: 9999px;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.625rem; font-weight: 700; color: #fff; flex-shrink: 0;
   }
   .crm-status-dot {
      display: inline-flex; align-items: center; gap: 0.35rem;
      font-size: 0.75rem; font-weight: 500;
   }
   .crm-status-dot::before {
      content: ""; width: 0.45rem; height: 0.45rem; border-radius: 9999px; flex-shrink: 0;
   }
   .crm-status-dot--purple::before { background: #7366FF; }
   .crm-status-dot--red::before { background: #FF5B5B; }
   .crm-status-dot--yellow::before { background: #FFAA05; }
   .crm-status-dot--green::before { background: #51BB25; }
   .crm-status-dot--orange::before { background: #FFAA05; }

   /* ---- Filter bar ---- */
   .crm-filter-bar {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, max-content));
      align-items: end;
      gap: 1.25rem 1.75rem;
      background: #fff;
      border: 1px solid #E6E9EB;
      border-radius: 0.875rem;
      padding: 1.15rem 1.5rem;
      margin-bottom: 1.25rem;
      box-shadow: 0 2px 12px rgba(47, 47, 58, 0.04);
   }
   @media (max-width: 1023px) {
      .crm-filter-bar {
         grid-template-columns: repeat(2, minmax(0, 1fr));
      }
   }
   @media (max-width: 639px) {
      .crm-filter-bar {
         grid-template-columns: 1fr;
         gap: 1rem;
      }
   }
   .crm-filter-field {
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
      min-width: 0;
   }
   .crm-filter-field--bar { min-width: 9.5rem; }
   .crm-filter-field--company { min-width: 11.5rem; }
   .crm-filter-field--week { min-width: 5.5rem; }
   .crm-filter-field--period { min-width: 18.5rem; }
   .crm-filter-label {
      display: block;
      font-size: 0.625rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #999;
      line-height: 1;
   }
   .crm-filter-select {
      appearance: none;
      -webkit-appearance: none;
      width: 100%;
      height: 2.375rem;
      box-sizing: border-box;
      background: #F0F4F8;
      border: none;
      border-radius: 0.5rem;
      padding: 0.55rem 2rem 0.55rem 0.85rem;
      font-size: 0.8125rem;
      font-weight: 500;
      color: #2F2F3A;
      cursor: pointer;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23848488' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.65rem center;
      transition: background-color 0.2s, box-shadow 0.2s;
   }
   .crm-filter-select:hover { background-color: #E8EEF4; }
   .crm-filter-select:focus {
      outline: none;
      box-shadow: 0 0 0 2px rgba(115, 102, 255, 0.18);
      background-color: #fff;
   }
   .crm-filter-date-range {
      display: flex;
      align-items: center;
      gap: 0.45rem;
   }
   .crm-filter-date-box {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.35rem;
      width: 7.85rem;
      height: 2.375rem;
      padding: 0 0.55rem 0 0.65rem;
      background: #fff;
      border: 1px solid #374151;
      border-radius: 0.25rem;
      cursor: pointer;
      transition: border-color 0.15s, box-shadow 0.15s;
   }
   .crm-filter-date-box:hover { border-color: #1F2937; }
   .crm-filter-date-box:focus-within {
      border-color: #7366FF;
      box-shadow: 0 0 0 2px rgba(115, 102, 255, 0.12);
   }
   .crm-filter-date-display {
      flex: 1;
      font-size: 0.8125rem;
      font-weight: 500;
      color: #2F2F3A;
      line-height: 1;
      white-space: nowrap;
      pointer-events: none;
      user-select: none;
   }
   .crm-filter-date-input {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      cursor: pointer;
      border: none;
      background: transparent;
   }
   .crm-filter-date-input::-webkit-calendar-picker-indicator {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      margin: 0;
      padding: 0;
      cursor: pointer;
   }
   .crm-filter-date-icon {
      flex-shrink: 0;
      width: 0.95rem;
      height: 0.95rem;
      color: #848488;
      pointer-events: none;
   }
   .crm-filter-date-sep {
      flex-shrink: 0;
      font-size: 0.8125rem;
      font-weight: 500;
      color: #848488;
      line-height: 1;
      user-select: none;
      padding: 0 0.05rem;
   }

   /* ---- Category tabs ---- */
   .crm-cat-tabs { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
   .crm-cat-tab {
      padding: 0.4rem 0.85rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;
      border: 1px solid #E6E9EB; background: #fff; color: #848488; text-decoration: none;
      transition: all 0.2s;
   }
   .crm-cat-tab:hover { border-color: #7366FF; color: #7366FF; }
   .crm-cat-tab--active { background: #7366FF; border-color: #7366FF; color: #fff; }
   .crm-cat-tab-count {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 1.25rem; height: 1.25rem; padding: 0 0.3rem;
      border-radius: 9999px; font-size: 0.625rem; font-weight: 700;
      background: rgba(0, 0, 0, 0.08);
   }
   .crm-cat-tab--active .crm-cat-tab-count { background: rgba(255, 255, 255, 0.2); }

   /* ---- Full data table ---- */
   .crm-data-table-wrap { overflow-x: auto; }
   .crm-data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8125rem; }
   .crm-data-table thead th {
      background: #7366FF; color: #fff; font-weight: 600; font-size: 0.6875rem;
      text-transform: uppercase; letter-spacing: 0.04em;
      padding: 0.7rem 0.875rem; text-align: left; white-space: nowrap;
   }
   .crm-data-table thead th:first-child { border-radius: 0.5rem 0 0 0; }
   .crm-data-table thead th:last-child { border-radius: 0 0.5rem 0 0; }
   .crm-data-table tbody td {
      padding: 0.65rem 0.875rem; border-bottom: 1px solid #F4F7F9; vertical-align: middle;
   }
   .crm-data-table tbody tr:nth-child(even) td { background: #FAFBFC; }
   .crm-data-table tbody tr:hover td { background: #F4F7F9; }
   .crm-data-table tbody tr.crm-row--review-week td { background: #EEF2FF; }
   .crm-data-table tbody tr.crm-row--review-week:hover td { background: #E0E7FF; }
   .crm-review-week-badge {
      display: inline-block;
      margin-left: 0.375rem;
      padding: 0.125rem 0.375rem;
      border-radius: 9999px;
      background: #7366FF;
      color: #fff;
      font-size: 0.625rem;
      font-weight: 600;
      vertical-align: middle;
   }
   .crm-data-table tbody tr.crm-row--clickable,
   .crm-table tbody tr.crm-row--clickable { cursor: pointer; }
   .crm-data-table tbody tr.crm-row--clickable:hover td,
   .crm-table tbody tr.crm-row--clickable:hover td { background: #EEF2FF; }
   .crm-detail-panel { width: min(860px, 100%); }
   .crm-detail-progress {
      display: flex; align-items: center; gap: 1rem;
      padding: 0.85rem 1rem; margin-bottom: 1rem;
      border-radius: 0.65rem; background: #ECE9FF;
   }
   .crm-detail-progress-value { font-size: 1.75rem; font-weight: 800; color: #7366FF; line-height: 1; }
   .crm-detail-progress-meta { font-size: 0.8125rem; color: #5f52e0; font-weight: 600; }
   .crm-detail-section { margin-bottom: 1rem; }
   .crm-detail-section-title {
      font-size: 0.75rem; font-weight: 800; text-transform: uppercase;
      letter-spacing: 0.05em; color: #7366FF; margin-bottom: 0.5rem;
   }
   .crm-detail-grid {
      display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.65rem 1rem;
   }
   @media (min-width: 768px) {
      .crm-detail-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
   }
   .crm-detail-label { display: block; font-size: 0.65rem; color: #848488; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
   .crm-detail-value { display: block; font-size: 0.8125rem; color: #2F2F3A; font-weight: 600; margin-top: 0.15rem; word-break: break-word; }
   .crm-detail-flags { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
   .crm-detail-flag {
      font-size: 0.6875rem; font-weight: 700; padding: 0.25rem 0.55rem; border-radius: 9999px;
      background: #F4F7F9; color: #848488;
   }
   .crm-detail-flag--on { background: #E8F9E5; color: #2d8a15; }
   .crm-detail-phase-table {
      width: 100%; border-collapse: collapse; font-size: 0.75rem;
      border: 1px solid #E6E9EB; border-radius: 0.5rem; overflow: hidden;
   }
   .crm-detail-phase-table thead th {
      background: #F4F7F9; color: #848488; font-weight: 700; text-align: left;
      padding: 0.55rem 0.75rem; border-bottom: 1px solid #E6E9EB;
   }
   .crm-detail-phase-table tbody td {
      padding: 0.55rem 0.75rem; border-bottom: 1px solid #F4F7F9; color: #2F2F3A;
   }
   .crm-detail-phase-table tbody tr:last-child td { border-bottom: none; }
   .crm-detail-text { font-size: 0.8125rem; color: #2F2F3A; line-height: 1.55; white-space: pre-wrap; }
   .crm-pct {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 2.75rem; padding: 0.15rem 0.45rem; border-radius: 0.375rem;
      font-size: 0.6875rem; font-weight: 700;
   }
   .crm-pct--green { background: #E8F9E5; color: #51BB25; }
   .crm-pct--amber { background: #FFF8E6; color: #FFAA05; }
   .crm-pct--orange { background: #FFF0E6; color: #FF8A00; }
   .crm-pct--red { background: #FFECEC; color: #FF5B5B; }

   /* ---- Effectiveness / shared extras ---- */
   .crm-filter-bar--compact {
      grid-template-columns: minmax(0, max-content) minmax(0, max-content);
   }
   .crm-filter-bar--single {
      grid-template-columns: minmax(0, max-content);
   }
   @media (max-width: 639px) {
      .crm-filter-bar--compact { grid-template-columns: 1fr; }
   }
   .crm-filter-field--company-wide { min-width: 13rem; }

   .crm-level-chip {
      display: inline-flex; align-items: center; gap: 0.3rem;
      padding: 0.2rem 0.55rem; border-radius: 0.375rem;
      font-size: 0.6875rem; font-weight: 700; white-space: nowrap;
   }
   .crm-level-chip--good { background: #E8F9E5; color: #51BB25; }
   .crm-level-chip--warn { background: #FFF8E6; color: #FFAA05; }
   .crm-level-chip--bad { background: #FFECEC; color: #FF5B5B; }
   .crm-level-chip--neutral { background: #F4F7F9; color: #848488; }

   .crm-matrix-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
   .crm-matrix-table th {
      background: #F4F7F9; color: #2F2F3A; font-weight: 700;
      text-align: left; padding: 0.55rem 0.75rem;
      border: 1px solid #E6E9EB; white-space: nowrap;
   }
   .crm-matrix-table td {
      padding: 0.55rem 0.75rem; border: 1px solid #E6E9EB;
      color: #2F2F3A; vertical-align: top; font-size: 0.75rem;
   }
   .crm-matrix-table tbody tr:nth-child(even) td { background: #FAFBFC; }
   .crm-risk-matrix-table th,
   .crm-risk-matrix-table td { vertical-align: middle; }
   .crm-risk-matrix-row-label {
      background: #fff !important; font-weight: 600; color: #2F2F3A;
      text-align: left; min-width: 220px; white-space: normal;
   }
   .crm-risk-matrix-table tbody tr:nth-child(even) .crm-risk-matrix-row-label { background: #FAFBFC !important; }
   .crm-risk-matrix-count {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 1.75rem; height: 1.75rem; padding: 0 0.45rem;
      border-radius: 0.45rem; background: #ECE9FF; color: #5f52e0;
      font-weight: 700; font-size: 0.8125rem;
   }
   .crm-risk-matrix-cell--clickable { cursor: pointer; }
   .crm-risk-matrix-cell--clickable:hover { background: #EEF2FF !important; }
   .crm-risk-matrix-cell--clickable:focus-visible { outline: 2px solid #7366FF; outline-offset: -2px; }

   .crm-badge {
      display: inline-flex; align-items: center; gap: 0.3rem;
      padding: 0.3rem 0.65rem; border-radius: 9999px;
      font-size: 0.6875rem; font-weight: 700;
      background: rgba(115, 102, 255, 0.1); color: #7366FF;
   }

   .crm-sidebar-header {
      padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.05em; color: #fff;
   }
   .crm-sidebar-header--purple { background: #7366FF; }
   .crm-sidebar-header--green { background: #51BB25; }
   .crm-sidebar-body { padding: 1rem 1.125rem; font-size: 0.8125rem; line-height: 1.6; color: #374151; }

   .crm-sidebar-point {
      display: flex; gap: 0.6rem; margin-bottom: 0.55rem;
      font-size: 0.8125rem; line-height: 1.55; color: #374151;
   }
   .crm-sidebar-point:last-child { margin-bottom: 0; }
   .crm-sidebar-point-num {
      flex-shrink: 0; width: 1.25rem; height: 1.25rem; border-radius: 9999px;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.625rem; font-weight: 800; background: #ECE9FF; color: #7366FF;
   }
   .crm-todo-item {
      display: flex; gap: 0.65rem; align-items: flex-start;
      padding: 0.55rem 0; border-bottom: 1px solid #F4F7F9;
      font-size: 0.8125rem; line-height: 1.55; color: #374151;
   }
   .crm-todo-item:last-child { border-bottom: none; padding-bottom: 0; }
   .crm-todo-num {
      flex-shrink: 0; width: 1.35rem; height: 1.35rem; border-radius: 0.375rem;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.625rem; font-weight: 800;
      background: linear-gradient(135deg, #7366FF, #9b93ff); color: #fff;
   }

   .crm-insight-band {
      display: grid;
      grid-template-columns: repeat(1, minmax(0, 1fr));
      gap: 0.75rem;
   }
   @media (min-width: 640px) {
      .crm-insight-band { grid-template-columns: repeat(3, minmax(0, 1fr)); }
   }
   .crm-insight-item {
      display: flex; align-items: center; gap: 0.75rem;
      padding: 0.85rem 1rem; border-radius: 0.75rem;
      border: 1px solid transparent;
   }
   .crm-insight-item--purple { background: rgba(115, 102, 255, 0.05); border-color: rgba(115, 102, 255, 0.08); }
   .crm-insight-item--red { background: rgba(255, 91, 91, 0.05); border-color: rgba(255, 91, 91, 0.08); }
   .crm-insight-item--green { background: rgba(81, 187, 37, 0.05); border-color: rgba(81, 187, 37, 0.08); }
   .crm-insight-icon {
      width: 2.25rem; height: 2.25rem; border-radius: 0.625rem;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
   }
   .crm-insight-icon--purple { background: #ECE9FF; color: #7366FF; }
   .crm-insight-icon--red { background: #FFECEC; color: #FF5B5B; }
   .crm-insight-icon--green { background: #E8F9E5; color: #51BB25; }
   .crm-insight-value { font-size: 1.25rem; font-weight: 800; color: #2F2F3A; line-height: 1.1; }
   .crm-insight-label { font-size: 0.625rem; font-weight: 600; color: #848488; margin-top: 0.1rem; }

   /* ---- Handsontable grid (Update Data) ---- */
   .crm-grid-toolbar {
      display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
      gap: 0.75rem; margin-bottom: 1rem;
   }
   .crm-grid-toolbar-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
   .crm-grid-btn {
      display: inline-flex; align-items: center; gap: 0.35rem;
      padding: 0.45rem 0.9rem; border-radius: 0.5rem;
      font-size: 0.75rem; font-weight: 600; border: 1px solid #E6E9EB;
      background: #fff; color: #2F2F3A; cursor: pointer; transition: all 0.15s;
   }
   .crm-grid-btn:hover { border-color: #7366FF; color: #7366FF; }
   .crm-grid-btn--primary {
      background: #7366FF; border-color: #7366FF; color: #fff;
   }
   .crm-grid-btn--primary:hover { background: #5f52e0; border-color: #5f52e0; color: #fff; }
   .crm-grid-btn:disabled { opacity: 0.55; cursor: not-allowed; }
   .crm-grid-status {
      font-size: 0.75rem; font-weight: 600; color: #848488;
   }
   .crm-grid-status--success { color: #51BB25; }
   .crm-grid-status--error { color: #FF5B5B; }
   .crm-grid-wrap {
      background: #fff; border: 1px solid #E6E9EB; border-radius: 0.75rem;
      overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
   }
   .crm-grid-container { width: 100%; min-height: 420px; }

   /* ---- Column visibility picker ---- */
   .crm-col-picker { position: relative; }
   .crm-col-picker-panel {
      display: none; position: absolute; top: calc(100% + 0.4rem); left: 0; z-index: 40;
      width: 17rem; max-height: 22rem;
      background: #fff; border: 1px solid #E6E9EB; border-radius: 0.75rem;
      box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14);
      overflow: hidden;
   }
   .crm-col-picker-panel--open { display: flex; flex-direction: column; }
   .crm-col-picker-head {
      display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
      padding: 0.65rem 0.85rem; border-bottom: 1px solid #EEF0F3;
      font-size: 0.75rem; font-weight: 700; color: #2F2F3A; flex-shrink: 0;
   }
   .crm-col-picker-head-actions { display: flex; gap: 0.5rem; }
   .crm-col-picker-link {
      border: none; background: none; padding: 0; cursor: pointer;
      font-size: 0.6875rem; font-weight: 600; color: #7366FF; text-decoration: underline;
   }
   .crm-col-picker-link:hover { color: #5f52e0; }
   .crm-col-picker-body { overflow-y: auto; padding: 0.5rem 0.35rem; }
   .crm-col-picker-group {
      margin: 0.5rem 0.5rem 0.2rem; font-size: 0.625rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.06em; color: #9CA3AF;
   }
   .crm-col-picker-group:first-child { margin-top: 0.1rem; }
   .crm-col-picker-item {
      display: flex; align-items: center; gap: 0.55rem;
      padding: 0.35rem 0.5rem; border-radius: 0.5rem; cursor: pointer;
      font-size: 0.75rem; color: #2F2F3A;
   }
   .crm-col-picker-item:hover { background: #F4F7F9; }
   .crm-col-picker-item input { accent-color: #7366FF; cursor: pointer; }
   .crm-grid-alert {
      padding: 0.65rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem;
      margin-bottom: 1rem; display: none;
   }
   .crm-grid-alert--show { display: block; }
   .crm-grid-alert--info { background: #ECE9FF; color: #5f52e0; border: 1px solid rgba(115,102,255,0.2); }
   .crm-grid-alert--success { background: #E8F9E5; color: #2d8a15; border: 1px solid rgba(81,187,37,0.25); }
   .crm-grid-alert--error { background: #FFECEC; color: #c93b3b; border: 1px solid rgba(255,91,91,0.25); }
   .crm-page-header { margin-bottom: 1.25rem; }
   .crm-page-title { font-size: 1.25rem; font-weight: 800; color: #2F2F3A; }
   .crm-page-subtitle { font-size: 0.8125rem; color: #848488; margin-top: 0.25rem; }

   #mse-record-grid .handsontable { font-family: 'Poppins', sans-serif; font-size: 0.75rem; }
   #mse-record-grid .handsontable th {
      background: #F4F7F9 !important; color: #2F2F3A !important;
      font-weight: 700 !important; border-color: #E6E9EB !important;
   }
   #mse-record-grid .handsontable td { border-color: #E6E9EB !important; color: #2F2F3A; }
   #mse-record-grid .handsontable tbody tr:nth-child(even) td { background: #FAFBFC; }
   #mse-record-grid .handsontable .htDimmed { color: #848488 !important; }
   #mse-record-grid .ht-status-done { color: #51BB25; font-weight: 700; }
   #mse-record-grid .ht-status-progress { color: #FFAA05; font-weight: 600; }
   #mse-record-grid .ht-status-notyet { color: #848488; }
   #mse-record-grid .ht-status-on-target { box-shadow: inset 3px 0 0 #51BB25; }
   #mse-record-grid .ht-status-overdue { box-shadow: inset 3px 0 0 #FF5B5B; background: #FFF8F8 !important; }
   #mse-record-grid .ht-status-no-due { box-shadow: inset 3px 0 0 #FFAA05; }

   #mse-record-grid .ht_clone_left .htCore td:last-of-type,
   #mse-record-grid .ht_clone_top_left_corner .htCore thead tr:last-child th:last-of-type {
      box-shadow: 4px 0 8px -2px rgba(47, 47, 58, 0.12);
      border-right: 2px solid #D8DCE0 !important;
      z-index: 2;
   }
   #mse-record-grid .ht_clone_left .htCore tbody tr:nth-child(even) td { background: #FAFBFC; }

   .crm-grid-legend {
      display: flex; flex-wrap: wrap; align-items: center; gap: 1rem;
      font-size: 0.75rem; color: #2F2F3A;
   }
   .crm-grid-legend-item { display: inline-flex; align-items: center; gap: 0.4rem; }
   .crm-grid-legend-dot {
      width: 0.65rem; height: 0.65rem; border-radius: 9999px; display: inline-block;
   }
   .crm-grid-legend-dot--on-target { background: #51BB25; }
   .crm-grid-legend-dot--overdue { background: #FF5B5B; }

   .crm-history-modal {
      position: fixed; inset: 0; z-index: 1200;
      display: none; align-items: center; justify-content: center;
      background: rgba(15, 23, 42, 0.48); padding: 0.35rem;
      backdrop-filter: blur(2px);
   }
   #mse-record-detail-modal { z-index: 1210; }
   .crm-history-modal--open { display: flex; }
   .crm-history-panel {
      width: min(720px, 100%); max-height: 88vh;
      background: #fff; border-radius: 1rem;
      border: 1px solid #E6E9EB; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
      display: flex; flex-direction: column; overflow: hidden;
   }
   /* Spesifisitas lebih tinggi agar tidak tertimpa .crm-history-panel */
   .crm-history-panel.crm-category-panel {
      width: min(760px, 100%);
   }
   .crm-history-panel.crm-category-panel--xl {
      width: min(1680px, 99vw);
      max-height: 98vh;
   }
   .crm-history-header {
      display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid #EEF0F3;
      background: #fff;
   }
   .crm-history-title {
      font-size: 1.05rem; font-weight: 800; color: #2F2F3A; line-height: 1.3;
      letter-spacing: -0.01em; max-width: 36rem;
   }
   .crm-history-subtitle {
      font-size: 0.75rem; color: #848488; margin-top: 0.25rem; line-height: 1.4;
   }
   .crm-history-close {
      border: 1px solid #E8EAED; background: #F8F9FB; border-radius: 0.6rem;
      width: 2rem; height: 2rem; flex-shrink: 0;
      cursor: pointer; color: #6B7280; font-size: 1.05rem; line-height: 1;
      transition: background 0.15s ease, color 0.15s ease;
   }
   .crm-history-close:hover {
      background: #EEF0F3; color: #2F2F3A;
   }
   .crm-history-body { padding: 1rem 1.25rem 1.2rem; overflow-y: auto; }
   .crm-history-batch {
      border: 1px solid #E6E9EB; border-radius: 0.65rem; margin-bottom: 0.85rem; overflow: hidden;
   }
   .crm-history-batch-head {
      display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1rem;
      padding: 0.65rem 0.85rem; background: #FAFBFC; border-bottom: 1px solid #E6E9EB;
      font-size: 0.75rem;
   }
   .crm-history-week {
      font-weight: 700; color: #7366FF; background: #ECE9FF;
      padding: 0.15rem 0.5rem; border-radius: 9999px;
   }
   .crm-history-action {
      font-weight: 700; text-transform: uppercase; font-size: 0.65rem; letter-spacing: 0.05em;
      padding: 0.15rem 0.45rem; border-radius: 0.35rem;
   }
   .crm-history-action--created { background: #E8F9E5; color: #2d8a15; }
   .crm-history-action--updated { background: #ECE9FF; color: #5f52e0; }
   .crm-history-meta { color: #848488; }
   .crm-history-change {
      display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem;
      padding: 0.55rem 0.85rem; font-size: 0.75rem; border-bottom: 1px solid #F4F7F9;
   }
   .crm-history-change:last-child { border-bottom: none; }
   .crm-history-field { font-weight: 600; color: #2F2F3A; }
   .crm-history-old { color: #848488; text-decoration: line-through; word-break: break-word; }
   .crm-history-new { color: #7366FF; font-weight: 600; word-break: break-word; }
   .crm-history-empty {
      text-align: center; color: #848488; font-size: 0.8125rem; padding: 2rem 1rem;
   }
</style>
