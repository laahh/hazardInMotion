<style>
   /* ---- CRM Layout ---- */
   .crm-brand-link {
      display: inline-flex;
      align-items: center;
      gap: 0.65rem;
      text-decoration: none;
   }
   .crm-brand-logo {
      display: block;
      height: 2.5rem;
      width: auto;
      max-width: 2.75rem;
      object-fit: contain;
      flex-shrink: 0;
   }
   .crm-brand-text {
      font-family: Poppins, sans-serif;
      font-size: 1.125rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      color: #111827;
      line-height: 1.1;
      white-space: nowrap;
   }
   .crm-brand-text-accent {
      color: #111827;
   }
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

   /* ---- KPI Stat Cards ---- */
   .crm-kpi-card {
      position: relative;
      display: flex;
      flex-direction: column;
      gap: 0.95rem;
      min-height: 100%;
      padding: 1.15rem 1.2rem 1.1rem;
      overflow: hidden;
      background: #fff;
   }
   .crm-kpi-card::before {
      content: '';
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 3px;
      border-radius: 1rem 0 0 1rem;
      background: #C9CDD4;
   }
   .crm-kpi-card--total::before { background: #6B7280; }
   .crm-kpi-card--replikasi::before { background: #7366FF; }
   .crm-kpi-card--safety::before { background: #15803D; }
   .crm-kpi-card--additional::before { background: #65A30D; }

   .crm-kpi-card--clickable {
      cursor: pointer;
      transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
   }
   .crm-kpi-card--clickable:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 24px rgba(47, 47, 58, 0.08);
   }
   .crm-kpi-card--replikasi.crm-kpi-card--clickable:hover { border-color: #C9C2FF; }
   .crm-kpi-card--safety.crm-kpi-card--clickable:hover { border-color: #86EFAC; }
   .crm-kpi-card--additional.crm-kpi-card--clickable:hover { border-color: #BEF264; }
   .crm-kpi-card--clickable:focus-visible {
      outline: 2px solid #7366FF;
      outline-offset: 2px;
   }

   .crm-kpi-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.75rem;
   }
   .crm-kpi-label {
      margin: 0;
      font-size: 0.8125rem;
      font-weight: 700;
      color: #2F2F3A;
      letter-spacing: -0.01em;
      line-height: 1.3;
   }
   .crm-kpi-subtitle {
      margin: 0.2rem 0 0;
      font-size: 0.6875rem;
      font-weight: 500;
      color: #8B8F98;
      line-height: 1.3;
   }
   .crm-kpi-icon {
      width: 2.25rem;
      height: 2.25rem;
      border-radius: 0.65rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      background: #F4F5F7;
      color: #6B7280;
   }
   .crm-kpi-icon .material-symbols-outlined { font-size: 1.2rem; }
   .crm-kpi-card--replikasi .crm-kpi-icon { background: #F1EFFF; color: #7366FF; }
   .crm-kpi-card--safety .crm-kpi-icon { background: #EAF8EE; color: #15803D; }
   .crm-kpi-card--additional .crm-kpi-icon { background: #F4FCE8; color: #4D7C0F; }
   .crm-kpi-card--total .crm-kpi-icon { background: #F3F4F6; color: #4B5563; }

   .crm-kpi-value-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
   }
   .crm-kpi-value {
      margin: 0;
      font-size: 2rem;
      font-weight: 800;
      color: #1F2430;
      letter-spacing: -0.04em;
      line-height: 1;
   }
   .crm-kpi-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.28rem 0.55rem;
      border-radius: 9999px;
      background: #F4F5F7;
      color: #4B5563;
      font-size: 0.6875rem;
      font-weight: 700;
      white-space: nowrap;
   }
   .crm-kpi-card--replikasi .crm-kpi-badge { background: #F1EFFF; color: #5B52D6; }
   .crm-kpi-card--safety .crm-kpi-badge { background: #EAF8EE; color: #15803D; }
   .crm-kpi-card--additional .crm-kpi-badge { background: #F4FCE8; color: #4D7C0F; }

   .crm-kpi-metrics {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 0.45rem;
      padding: 0.65rem 0.55rem;
      border-radius: 0.7rem;
      background: #F8F9FB;
      border: 1px solid #EEF0F3;
   }
   .crm-kpi-metric {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.2rem;
      text-align: center;
      min-width: 0;
   }
   .crm-kpi-metric + .crm-kpi-metric {
      border-left: 1px solid #E6E9EB;
   }
   .crm-kpi-metric-label {
      font-size: 0.625rem;
      font-weight: 600;
      color: #8B8F98;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      line-height: 1.2;
   }
   .crm-kpi-metric-value {
      font-size: 0.9375rem;
      font-weight: 800;
      color: #2F2F3A;
      line-height: 1.1;
   }
   .crm-kpi-metric-value--info { color: #2563EB; }
   .crm-kpi-metric-value--danger { color: #DC2626; }
   .crm-kpi-metric-value--success { color: #15803D; }

   .crm-kpi-foot {
      margin-top: auto;
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
   }
   .crm-kpi-progress-track {
      height: 6px;
      border-radius: 9999px;
      background: #EEF0F3;
      overflow: hidden;
   }
   .crm-kpi-progress-fill {
      height: 100%;
      border-radius: 9999px;
      background: #6B7280;
      transition: width 0.35s ease;
   }
   .crm-kpi-card--replikasi .crm-kpi-progress-fill { background: linear-gradient(90deg, #7366FF 0%, #8B82FF 100%); }
   .crm-kpi-card--safety .crm-kpi-progress-fill { background: linear-gradient(90deg, #15803D 0%, #22C55E 100%); }
   .crm-kpi-card--additional .crm-kpi-progress-fill { background: linear-gradient(90deg, #65A30D 0%, #84CC16 100%); }
   .crm-kpi-card--total .crm-kpi-progress-fill { background: linear-gradient(90deg, #4B5563 0%, #9CA3AF 100%); }
   .crm-kpi-foot-text {
      margin: 0;
      font-size: 0.6875rem;
      font-weight: 500;
      color: #8B8F98;
   }

   /* ---- Category Trend Cards ---- */
   .crm-trend-card {
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
      padding: 1.15rem 1.2rem 1.05rem;
      position: relative;
      overflow: hidden;
   }
   .crm-trend-card::before {
      content: '';
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 3px;
      border-radius: 1rem 0 0 1rem;
      background: #C9CDD4;
   }
   .crm-trend-card--replikasi::before { background: #7366FF; }
   .crm-trend-card--safety::before { background: #15803D; }
   .crm-trend-card--additional::before { background: #65A30D; }
   .crm-trend-card-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.75rem;
   }
   .crm-trend-card-label {
      margin: 0;
      font-size: 0.875rem;
      font-weight: 800;
      color: #2F2F3A;
      letter-spacing: -0.01em;
   }
   .crm-trend-card-subtitle {
      margin: 0.2rem 0 0;
      font-size: 0.6875rem;
      font-weight: 500;
      color: #8B8F98;
   }
   .crm-trend-delta {
      display: inline-flex;
      align-items: center;
      gap: 0.15rem;
      padding: 0.28rem 0.55rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 800;
      white-space: nowrap;
   }
   .crm-trend-delta--up { background: #E8F9E5; color: #15803D; }
   .crm-trend-delta--down { background: #FEECEC; color: #DC2626; }
   .crm-trend-card-main {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 0.75rem;
   }
   .crm-trend-progress-value {
      margin: 0;
      font-size: 1.75rem;
      font-weight: 800;
      color: #1F2430;
      letter-spacing: -0.04em;
      line-height: 1;
   }
   .crm-trend-progress-label {
      margin: 0.3rem 0 0;
      font-size: 0.6875rem;
      font-weight: 600;
      color: #8B8F98;
   }
   .crm-trend-mini-metrics {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      gap: 0.3rem;
   }
   .crm-trend-chip {
      display: inline-flex;
      align-items: center;
      padding: 0.15rem 0.45rem;
      border-radius: 9999px;
      font-size: 0.625rem;
      font-weight: 700;
   }
   .crm-trend-chip--info { background: #E8F2FF; color: #2563EB; }
   .crm-trend-chip--danger { background: #FEECEC; color: #DC2626; }
   .crm-trend-chip--success { background: #E8F9E5; color: #15803D; }
   .crm-trend-chart-wrap {
      height: 150px;
      position: relative;
   }
   .crm-trend-card-foot {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.65rem 1rem;
      font-size: 0.6875rem;
      font-weight: 600;
      color: #6B7280;
      border-top: 1px solid #EEF0F3;
      padding-top: 0.7rem;
   }
   .crm-trend-card-foot strong {
      color: #2F2F3A;
      font-weight: 800;
   }
   .crm-trend-legend {
      margin-left: auto;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      color: #8B8F98;
   }
   .crm-trend-legend i {
      display: inline-block;
      width: 0.55rem;
      height: 0.55rem;
      border-radius: 9999px;
   }
   .crm-trend-legend-plan { background: #C9CDD4 !important; }

   /* ---- Phase Funnel Cards ---- */
   .crm-funnel-card {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      padding: 1.15rem 1.2rem 1.05rem;
      position: relative;
      overflow: hidden;
      background: linear-gradient(180deg, #F7FBF4 0%, #FFFFFF 42%);
   }
   .crm-funnel-card::before {
      content: '';
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 3px;
      border-radius: 1rem 0 0 1rem;
      background: #C9CDD4;
   }
   .crm-funnel-card--replikasi::before { background: #7366FF; }
   .crm-funnel-card--safety::before { background: #15803D; }
   .crm-funnel-card--additional::before { background: #65A30D; }
   .crm-funnel-card-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.75rem;
   }
   .crm-funnel-card-label {
      margin: 0;
      font-size: 0.875rem;
      font-weight: 800;
      color: #2F2F3A;
      letter-spacing: -0.01em;
   }
   .crm-funnel-card-subtitle {
      margin: 0.2rem 0 0;
      font-size: 0.6875rem;
      font-weight: 500;
      color: #8B8F98;
   }
   .crm-funnel-count {
      display: inline-flex;
      align-items: center;
      padding: 0.28rem 0.55rem;
      border-radius: 9999px;
      font-size: 0.6875rem;
      font-weight: 700;
      background: #EEF2F7;
      color: #4B5563;
      white-space: nowrap;
   }
   .crm-funnel-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 0.65rem;
   }
   .crm-funnel-legend-item {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      font-size: 0.6875rem;
      font-weight: 600;
      color: #6B7280;
   }
   .crm-funnel-swatch {
      width: 0.65rem;
      height: 0.65rem;
      border-radius: 0.15rem;
      display: inline-block;
   }
   .crm-funnel-swatch--done { background: #166534; }
   .crm-funnel-swatch--overdue { background: #DC2626; }
   .crm-funnel-swatch--progress { background: #86EFAC; }
   .crm-funnel-chart-wrap {
      position: relative;
      height: 240px;
   }
   .crm-funnel-card-foot {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
   }
   .crm-funnel-chip {
      display: inline-flex;
      align-items: center;
      padding: 0.22rem 0.5rem;
      border-radius: 0.4rem;
      font-size: 0.6875rem;
      font-weight: 700;
   }
   .crm-funnel-chip--success { background: #E8F9E5; color: #15803D; }
   .crm-funnel-chip--danger { background: #FEECEC; color: #DC2626; }
   .crm-funnel-chip--progress { background: #ECFDF3; color: #15803D; }

   /* Legacy stat-card hooks (kept for other pages) */
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
   .crm-stat-card--replikasi {
      position: relative;
      overflow: hidden;
      background:
         radial-gradient(120% 80% at 100% 0%, rgba(115, 102, 255, 0.10), transparent 55%),
         linear-gradient(180deg, #FFFFFF 0%, #FAFBFF 100%);
      border-color: #E4E0FF;
   }
   .crm-stat-card--replikasi::before {
      content: '';
      position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
      background: linear-gradient(180deg, #7366FF 0%, #3B97FF 100%);
      border-radius: 1rem 0 0 1rem;
   }
   .crm-stat-card--replikasi:hover {
      border-color: #B8AEFF;
      box-shadow: 0 12px 28px rgba(115, 102, 255, 0.16);
   }
   .crm-stat-card--safety {
      position: relative;
      overflow: hidden;
      background:
         radial-gradient(120% 80% at 100% 0%, rgba(21, 128, 61, 0.10), transparent 55%),
         linear-gradient(180deg, #FFFFFF 0%, #F7FCF8 100%);
      border-color: #D8EEDD;
   }
   .crm-stat-card--safety::before {
      content: '';
      position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
      background: linear-gradient(180deg, #15803D 0%, #51BB25 100%);
      border-radius: 1rem 0 0 1rem;
   }
   .crm-stat-card--safety:hover {
      border-color: #86EFAC;
      box-shadow: 0 12px 28px rgba(21, 128, 61, 0.14);
   }
   .crm-stat-card--safety .crm-stat-icon {
      background: #E8F9E5; color: #15803D;
   }
   .crm-stat-card--additional {
      position: relative;
      overflow: hidden;
      background:
         radial-gradient(120% 80% at 100% 0%, rgba(101, 163, 13, 0.12), transparent 55%),
         linear-gradient(180deg, #FFFFFF 0%, #FBFFF5 100%);
      border-color: #E2EFBF;
   }
   .crm-stat-card--additional::before {
      content: '';
      position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
      background: linear-gradient(180deg, #65A30D 0%, #A3E635 100%);
      border-radius: 1rem 0 0 1rem;
   }
   .crm-stat-card--additional:hover {
      border-color: #BEF264;
      box-shadow: 0 12px 28px rgba(101, 163, 13, 0.14);
   }
   .crm-stat-card--additional .crm-stat-icon {
      background: #F7FEE7; color: #4D7C0F;
   }
   .crm-stat-card-head {
      display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
      margin-bottom: 0.75rem;
   }
   .crm-stat-card-head .crm-stat-label { margin-bottom: 0; }
   .crm-stat-icon {
      width: 2rem; height: 2rem; border-radius: 0.65rem;
      display: inline-flex; align-items: center; justify-content: center;
      background: #ECE9FF; color: #7366FF; flex-shrink: 0;
   }
   .crm-stat-icon .material-symbols-outlined { font-size: 1.15rem; }
   .crm-stat-label {
      font-size: 0.8125rem; font-weight: 500; color: #848488; margin-bottom: 0.5rem;
   }
   .crm-stat-label--strong {
      font-weight: 700; color: #5B5675; letter-spacing: -0.01em;
   }
   .crm-stat-main {
      display: flex; align-items: flex-start; gap: 0.85rem;
   }
   .crm-stat-value {
      font-size: 1.75rem; font-weight: 700; color: #2F2F3A; line-height: 1.1;
   }
   .crm-stat-value--lg {
      font-size: 2rem; font-weight: 800; letter-spacing: -0.03em;
   }
   .crm-stat-meta {
      display: flex; flex-direction: column; gap: 0.15rem;
      padding-top: 0.2rem;
      font-size: 0.75rem; font-weight: 500; color: #2F2F3A; line-height: 1.25;
   }
   .crm-stat-meta--chips {
      gap: 0.35rem; padding-top: 0.1rem; min-width: 0;
   }
   .crm-stat-chip {
      display: inline-flex; align-items: center; gap: 0.35rem;
      padding: 0.18rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.6875rem; font-weight: 700; line-height: 1.2;
      white-space: nowrap;
   }
   .crm-stat-chip-dot {
      width: 0.4rem; height: 0.4rem; border-radius: 9999px; flex-shrink: 0;
   }
   .crm-stat-chip--onprogress {
      background: #E8F2FF; color: #1D4ED8;
   }
   .crm-stat-chip--onprogress .crm-stat-chip-dot { background: #3B97FF; }
   .crm-stat-chip--overdue {
      background: #FEECEC; color: #B91C1C;
   }
   .crm-stat-chip--overdue .crm-stat-chip-dot { background: #FF5B5B; }
   .crm-stat-chip--selesai {
      background: #E8F9E5; color: #15803D;
   }
   .crm-stat-chip--selesai .crm-stat-chip-dot { background: #51BB25; }
   .crm-stat-foot {
      display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
      margin-top: 0.85rem;
   }
   .crm-stat-progress {
      flex: 1; min-width: 0;
   }
   .crm-stat-progress-track {
      height: 6px; border-radius: 9999px; background: #EEF0F5; overflow: hidden;
   }
   .crm-stat-progress-fill {
      height: 100%; border-radius: 9999px;
      background: linear-gradient(90deg, #7366FF 0%, #51BB25 100%);
      transition: width 0.35s ease;
   }
   .crm-stat-progress-fill--safety {
      background: linear-gradient(90deg, #15803D 0%, #86EFAC 100%);
   }
   .crm-stat-progress-fill--additional {
      background: linear-gradient(90deg, #65A30D 0%, #BEF264 100%);
   }
   .crm-stat-progress-label {
      margin-top: 0.3rem; font-size: 0.625rem; font-weight: 600; color: #848488;
   }
   .crm-stat-hint {
      display: inline-flex; align-items: center; gap: 0.15rem;
      font-size: 0.6875rem; font-weight: 600; color: #7366FF; opacity: 0.85;
   }
   .crm-stat-card--replikasi:hover .crm-stat-hint { opacity: 1; }
   .crm-stat-trend {
      display: inline-flex; align-items: center; gap: 0.2rem;
      margin-top: 0.65rem; font-size: 0.75rem; font-weight: 600;
   }
   .crm-stat-trend--compact { margin-top: 0; flex-shrink: 0; }
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

   /* Site → Perusahaan matrix (Replikasi modal) */
   .crm-site-matrix-card {
      margin: 0.85rem 0 1rem;
      border: 1px solid #E6E9EB;
      border-radius: 0.85rem;
      overflow: hidden;
      background: #fff;
   }
   .crm-site-matrix-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.85rem 1rem;
      border-bottom: 1px solid #EEF0F3;
      background: #FAFBFC;
   }
   .crm-site-matrix-title {
      margin: 0;
      font-size: 0.875rem;
      font-weight: 800;
      color: #2F2F3A;
      letter-spacing: -0.01em;
   }
   .crm-site-matrix-subtitle {
      margin: 0.2rem 0 0;
      font-size: 0.6875rem;
      font-weight: 500;
      color: #8B8F98;
   }
   .crm-site-matrix-badge {
      flex-shrink: 0;
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.55rem;
      border-radius: 9999px;
      background: #ECE9FF;
      color: #5B52D6;
      font-size: 0.6875rem;
      font-weight: 700;
   }
   .crm-site-matrix-wrap {
      overflow-x: auto;
   }
   .crm-site-matrix-table {
      width: 100%;
      min-width: 640px;
      border-collapse: collapse;
      font-size: 0.75rem;
   }
   .crm-site-matrix-table thead th {
      background: #F4F7F9;
      color: #2F2F3A;
      font-weight: 700;
      padding: 0.55rem 0.65rem;
      border-bottom: 1px solid #E6E9EB;
      border-left: 1px solid #E6E9EB;
      white-space: nowrap;
      text-align: center;
      vertical-align: middle;
   }
   .crm-site-matrix-corner {
      text-align: left !important;
      min-width: 8.5rem;
      position: sticky;
      left: 0;
      z-index: 2;
      background: #F4F7F9 !important;
      border-left: none !important;
   }
   .crm-site-matrix-site {
      font-size: 0.75rem;
      letter-spacing: 0.01em;
   }
   .crm-site-matrix-company {
      font-size: 0.6875rem;
      font-weight: 600 !important;
      color: #5B5675 !important;
      background: #FAFBFC !important;
   }
   .crm-site-matrix-table tbody th,
   .crm-site-matrix-table tbody td {
      padding: 0.55rem 0.65rem;
      border-top: 1px solid #F0F2F5;
      border-left: 1px solid #F0F2F5;
      vertical-align: middle;
   }
   .crm-site-matrix-table tbody tr:nth-child(even) td,
   .crm-site-matrix-table tbody tr:nth-child(even) th {
      background: #FAFBFC;
   }
   .crm-site-matrix-metric {
      text-align: left;
      font-weight: 700;
      color: #2F2F3A;
      white-space: nowrap;
      position: sticky;
      left: 0;
      z-index: 1;
      background: #fff;
      border-left: none !important;
      min-width: 8.5rem;
   }
   .crm-site-matrix-table tbody tr:nth-child(even) .crm-site-matrix-metric {
      background: #FAFBFC;
   }
   .crm-site-matrix-cell {
      text-align: center;
      font-variant-numeric: tabular-nums;
      color: #2F2F3A;
   }
   .crm-site-matrix-cell--muted { color: #B0B4BC; font-weight: 500; }
   .crm-site-matrix-cell--strong { color: #2F2F3A; font-weight: 700; }
   .crm-site-matrix-cell--info { color: #2563EB; font-weight: 700; }
   .crm-site-matrix-cell--danger { color: #DC2626; font-weight: 700; }
   .crm-site-matrix-cell--success { color: #15803D; font-weight: 700; }
   .crm-site-matrix-cell--status { padding-top: 0.45rem; padding-bottom: 0.45rem; }
   .crm-site-matrix-empty { color: #B0B4BC; font-weight: 500; }
   .crm-site-matrix-hoverable {
      cursor: help;
      transition: background 0.15s ease;
   }
   .crm-site-matrix-table tbody tr:hover .crm-site-matrix-hoverable:hover,
   .crm-site-matrix-company.crm-site-matrix-hoverable:hover {
      background: #EEF2FF !important;
      outline: 1px solid #C9C2FF;
      outline-offset: -1px;
   }

   .crm-site-matrix-float-tip {
      position: fixed;
      z-index: 10050;
      display: none;
      max-height: min(360px, calc(100vh - 24px));
      overflow: auto;
      padding: 0;
      border: 1px solid #E0E3EA;
      border-radius: 0.85rem;
      background: #fff;
      box-shadow: 0 16px 40px rgba(47, 47, 58, 0.18);
   }
   .crm-site-matrix-float-tip--open { display: block; }
   .crm-site-matrix-tip-head {
      position: sticky;
      top: 0;
      z-index: 1;
      padding: 0.75rem 0.85rem 0.65rem;
      border-bottom: 1px solid #EEF0F3;
      background: #FAFBFC;
   }
   .crm-site-matrix-tip-title {
      margin: 0;
      font-size: 0.8125rem;
      font-weight: 800;
      color: #2F2F3A;
   }
   .crm-site-matrix-tip-subtitle {
      margin: 0.2rem 0 0;
      font-size: 0.6875rem;
      font-weight: 600;
      color: #8B8F98;
   }
   .crm-site-matrix-tip-list {
      list-style: none;
      margin: 0;
      padding: 0.35rem 0.55rem 0.55rem;
   }
   .crm-site-matrix-tip-item {
      padding: 0.55rem 0.4rem;
      border-bottom: 1px solid #F0F2F5;
   }
   .crm-site-matrix-tip-item:last-child { border-bottom: none; }
   .crm-site-matrix-tip-item-top {
      display: flex;
      align-items: flex-start;
      gap: 0.4rem;
   }
   .crm-site-matrix-tip-no {
      flex-shrink: 0;
      font-size: 0.6875rem;
      font-weight: 700;
      color: #8B8F98;
      line-height: 1.4;
   }
   .crm-site-matrix-tip-name {
      flex: 1;
      min-width: 0;
      font-size: 0.75rem;
      font-weight: 700;
      color: #2F2F3A;
      line-height: 1.35;
   }
   .crm-site-matrix-tip-meta {
      margin: 0.3rem 0 0 1.1rem;
      font-size: 0.6875rem;
      font-weight: 600;
      color: #6B7280;
   }
   .crm-site-matrix-tip-empty {
      padding: 0.75rem;
      text-align: center;
      color: #8B8F98;
      font-size: 0.75rem;
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
   .crm-data-table tbody tr.crm-row--expanded > td,
   .crm-table tbody tr.crm-row--expanded > td,
   .crm-modal-table-wrap .crm-data-table tbody tr.crm-row--expanded > td {
      background: #EEF2FF !important;
   }
   .crm-row-expand-icon {
      font-size: 1.1rem !important;
      color: #7366FF;
      line-height: 1;
      transition: transform 0.2s ease;
      flex-shrink: 0;
   }
   .crm-row--expanded .crm-row-expand-icon { transform: rotate(180deg); }
   .crm-row-collapse > td {
      padding: 0 !important;
      background: #F8F9FC !important;
      border-bottom: 1px solid #E6E9EB !important;
      vertical-align: top !important;
   }
   .crm-row-collapse-panel {
      max-height: 0;
      overflow: hidden;
      opacity: 0;
      transition: max-height 0.35s ease, opacity 0.25s ease, padding 0.25s ease;
      padding: 0 1rem;
   }
   .crm-row-collapse--open .crm-row-collapse-panel {
      opacity: 1;
      padding: 1rem 1.1rem 1.15rem;
   }
   .crm-row-collapse-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.85rem;
      padding-bottom: 0.75rem;
      border-bottom: 1px solid #E6E9EB;
   }
   .crm-row-collapse-title {
      margin: 0;
      font-size: 0.9375rem;
      font-weight: 800;
      color: #2F2F3A;
      line-height: 1.35;
   }
   .crm-row-collapse-subtitle {
      margin: 0.25rem 0 0;
      font-size: 0.75rem;
      color: #848488;
      font-weight: 500;
   }
   .crm-row-collapse-close {
      flex-shrink: 0;
      width: 1.75rem;
      height: 1.75rem;
      border: none;
      border-radius: 9999px;
      background: #ECE9FF;
      color: #5f52e0;
      font-size: 1.15rem;
      line-height: 1;
      cursor: pointer;
   }
   .crm-row-collapse-close:hover { background: #CFC8FF; }
   .crm-row-collapse-body .crm-detail-progress { margin-bottom: 0.85rem; }
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
   .crm-level-chip--derived {
      background: #EEF2FF;
      color: #4338CA;
      border: 1px solid #C7D2FE;
   }
   .crm-level-chip-hint {
      margin-left: 0.25rem;
      font-size: 0.625rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      opacity: 0.75;
   }

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

   /* ---- PMR Evaluation workspace ---- */
   .mse-eval-hero {
      display: grid;
      gap: 1.25rem;
      padding: 1.35rem 1.4rem;
      border-radius: 1rem;
      border: 1px solid #E6E9EB;
      background:
         linear-gradient(135deg, rgba(115, 102, 255, 0.08) 0%, rgba(255, 255, 255, 0.95) 42%, #F7FBF4 100%);
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
   }
   @media (min-width: 900px) {
      .mse-eval-hero { grid-template-columns: 1.6fr 0.9fr; align-items: center; }
   }
   .mse-eval-kicker {
      margin: 0;
      font-size: 0.6875rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #7366FF;
   }
   .mse-eval-title {
      margin: 0.35rem 0 0.45rem;
      font-size: 1.45rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: #1F2430;
      line-height: 1.2;
   }
   .mse-eval-desc {
      margin: 0;
      max-width: 42rem;
      font-size: 0.8125rem;
      line-height: 1.55;
      color: #6B7280;
   }
   .mse-eval-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem 1.1rem;
      margin-top: 0.95rem;
      font-size: 0.75rem;
      font-weight: 600;
      color: #4B5563;
   }
   .mse-eval-meta span { display: inline-flex; align-items: center; gap: 0.3rem; }
   .mse-eval-hero-score {
      padding: 1rem 1.1rem;
      border-radius: 0.9rem;
      background: #fff;
      border: 1px solid #E8EAF0;
   }
   .mse-eval-score-label {
      margin: 0;
      font-size: 0.6875rem;
      font-weight: 700;
      color: #8B8F98;
      text-transform: uppercase;
      letter-spacing: 0.04em;
   }
   .mse-eval-score-value {
      margin: 0.35rem 0 0.55rem;
      font-size: 2.35rem;
      font-weight: 800;
      letter-spacing: -0.05em;
      color: #1F2430;
      line-height: 1;
   }
   .mse-eval-score-track {
      height: 0.45rem;
      border-radius: 9999px;
      background: #EEF2F7;
      overflow: hidden;
   }
   .mse-eval-score-fill {
      height: 100%;
      border-radius: inherit;
      background: linear-gradient(90deg, #7366FF, #51BB25);
   }
   .mse-eval-score-note {
      margin: 0.55rem 0 0;
      font-size: 0.75rem;
      font-weight: 600;
      color: #6B7280;
   }

   .mse-eval-score-card {
      padding: 1rem 1.05rem;
      border-radius: 0.9rem;
      border: 1px solid #E6E9EB;
      background: #fff;
      position: relative;
      overflow: hidden;
   }
   .mse-eval-score-card::before {
      content: '';
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 3px;
   }
   .mse-eval-score-card--l3::before { background: #15803D; }
   .mse-eval-score-card--l2::before { background: #65A30D; }
   .mse-eval-score-card--l1::before { background: #CA8A04; }
   .mse-eval-score-card--none::before { background: #9CA3AF; }
   .mse-eval-score-card-hint {
      margin: 0;
      font-size: 0.625rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #9CA3AF;
   }
   .mse-eval-score-card-label {
      margin: 0.2rem 0 0;
      font-size: 0.8125rem;
      font-weight: 700;
      color: #374151;
   }
   .mse-eval-score-card-value {
      margin: 0.45rem 0 0;
      font-size: 1.75rem;
      font-weight: 800;
      letter-spacing: -0.04em;
      color: #1F2430;
      line-height: 1;
   }
   .mse-eval-score-card-pct {
      margin: 0.4rem 0 0;
      font-size: 0.6875rem;
      font-weight: 600;
      color: #8B8F98;
   }

   .mse-eval-section-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.85rem;
   }
   .mse-eval-section-head--table {
      flex-wrap: wrap;
      align-items: center;
   }
   .mse-eval-section-sub {
      margin: 0.25rem 0 0;
      font-size: 0.6875rem;
      font-weight: 500;
      color: #8B8F98;
   }
   .mse-eval-result-pill {
      display: inline-flex;
      align-items: center;
      padding: 0.2rem 0.55rem;
      border-radius: 9999px;
      font-size: 0.6875rem;
      font-weight: 700;
      white-space: nowrap;
   }
   .mse-eval-result-pill--high { background: #E8F9E5; color: #15803D; }
   .mse-eval-result-pill--mid { background: #FFF8E6; color: #B45309; }
   .mse-eval-chart-wrap { height: 220px; }
   .mse-eval-exposure {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem 1.25rem;
      margin-top: 0.85rem;
      padding-top: 0.85rem;
      border-top: 1px solid #EEF1F4;
      font-size: 0.75rem;
      color: #6B7280;
   }
   .mse-eval-exposure div { display: inline-flex; align-items: center; gap: 0.35rem; }
   .mse-eval-exposure strong { color: #1F2430; }

   .mse-eval-cohort { border-left: 3px solid var(--cohort, #7366FF); }
   .mse-eval-cohort-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.75rem;
   }
   .mse-eval-cohort-label {
      margin: 0;
      font-size: 0.9375rem;
      font-weight: 800;
      color: #1F2430;
   }
   .mse-eval-cohort-sub {
      margin: 0.2rem 0 0;
      font-size: 0.6875rem;
      font-weight: 500;
      color: #8B8F98;
   }
   .mse-eval-cohort-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.55rem;
      border-radius: 9999px;
      font-size: 0.6875rem;
      font-weight: 700;
      background: #F3F4F6;
      color: #4B5563;
   }
   .mse-eval-stack {
      display: flex;
      height: 0.55rem;
      border-radius: 9999px;
      overflow: hidden;
      background: #EEF2F7;
      margin-bottom: 0.7rem;
   }
   .mse-eval-stack i { display: block; height: 100%; }
   .mse-eval-cohort-metrics {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.35rem 0.75rem;
      font-size: 0.6875rem;
      color: #6B7280;
   }
   .mse-eval-cohort-metrics b { color: #1F2430; margin-right: 0.2rem; }
   .mse-eval-cohort-foot {
      display: flex;
      justify-content: space-between;
      margin-top: 0.75rem;
      padding-top: 0.65rem;
      border-top: 1px dashed #E6E9EB;
      font-size: 0.6875rem;
      font-weight: 600;
      color: #8B8F98;
   }

   .mse-eval-table-filters {
      display: flex;
      flex-wrap: wrap;
      gap: 0.35rem;
   }
   .mse-eval-filter-btn {
      border: 1px solid #E6E9EB;
      background: #fff;
      color: #6B7280;
      font-size: 0.6875rem;
      font-weight: 700;
      padding: 0.35rem 0.65rem;
      border-radius: 9999px;
      cursor: pointer;
   }
   .mse-eval-filter-btn.is-active {
      background: #ECE9FF;
      border-color: #C9C2FF;
      color: #5B52D6;
   }
   .mse-eval-item-meta {
      margin: 0.2rem 0 0;
      font-size: 0.6875rem;
      font-weight: 500;
      color: #9CA3AF;
   }
   .mse-eval-io {
      display: inline-flex;
      max-width: 100%;
      padding: 0.2rem 0.45rem;
      border-radius: 0.4rem;
      background: #F8FAFC;
      border: 1px solid #E8ECF1;
      font-size: 0.75rem;
      font-weight: 600;
      color: #374151;
   }
   .mse-eval-io--empty {
      background: #FFF7ED;
      border-color: #FED7AA;
      color: #C2410C;
   }
   .mse-eval-table-note {
      margin: 0.85rem 0 0;
      font-size: 0.6875rem;
      color: #8B8F98;
   }

   /* ---- Company Overview Scorecard ---- */
   .mse-co-hero {
      display: grid;
      gap: 1.25rem;
      padding: 1.35rem 1.4rem;
      border-radius: 1rem;
      border: 1px solid #E6E9EB;
      background:
         linear-gradient(135deg, rgba(115, 102, 255, 0.09) 0%, #FFFFFF 45%, #F4FBF8 100%);
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
   }
   @media (min-width: 900px) {
      .mse-co-hero { grid-template-columns: 1.55fr 1fr; align-items: stretch; }
   }
   .mse-co-kicker {
      margin: 0;
      font-size: 0.6875rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #7366FF;
   }
   .mse-co-title {
      margin: 0.35rem 0 0.45rem;
      font-size: 1.45rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: #1F2430;
      line-height: 1.2;
   }
   .mse-co-desc {
      margin: 0;
      max-width: 40rem;
      font-size: 0.8125rem;
      line-height: 1.55;
      color: #6B7280;
   }
   .mse-co-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem 1.1rem;
      margin-top: 0.95rem;
      font-size: 0.75rem;
      font-weight: 600;
      color: #4B5563;
   }
   .mse-co-meta span { display: inline-flex; align-items: center; gap: 0.3rem; }
   .mse-co-hero-score {
      padding: 1rem 1.1rem;
      border-radius: 0.9rem;
      background: #fff;
      border: 1px solid #E8EAF0;
   }
   .mse-co-score-label {
      margin: 0;
      font-size: 0.6875rem;
      font-weight: 700;
      color: #8B8F98;
      text-transform: uppercase;
      letter-spacing: 0.04em;
   }
   .mse-co-score-value {
      margin: 0.3rem 0 0.75rem;
      font-size: 2.4rem;
      font-weight: 800;
      letter-spacing: -0.05em;
      color: #1F2430;
      line-height: 1;
   }
   .mse-co-score-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.55rem 0.75rem;
   }
   .mse-co-score-grid div {
      padding: 0.45rem 0.55rem;
      border-radius: 0.55rem;
      background: #F8FAFC;
      border: 1px solid #EEF2F7;
   }
   .mse-co-score-grid b {
      display: block;
      font-size: 0.9375rem;
      font-weight: 800;
      color: #1F2430;
      line-height: 1.1;
   }
   .mse-co-score-grid span {
      font-size: 0.625rem;
      font-weight: 600;
      color: #8B8F98;
      text-transform: uppercase;
      letter-spacing: 0.03em;
   }
   .mse-co-section-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.85rem;
   }
   .mse-co-section-head--table { align-items: center; flex-wrap: wrap; }
   .mse-co-section-sub {
      margin: 0.25rem 0 0;
      font-size: 0.6875rem;
      font-weight: 500;
      color: #8B8F98;
   }
   .mse-co-chart-wrap { height: 280px; }
   .mse-co-rank-row { cursor: pointer; transition: background 0.15s ease; }
   .mse-co-rank-row:hover { background: #F8F7FF; }
   .mse-co-rank-row.is-active {
      background: #F1EFFF;
      box-shadow: inset 3px 0 0 #7366FF;
   }
   .mse-co-row-meta {
      margin: 0.15rem 0 0;
      font-size: 0.6875rem;
      font-weight: 500;
      color: #9CA3AF;
   }
   .mse-co-eff-chip {
      display: inline-flex;
      align-items: center;
      padding: 0.15rem 0.45rem;
      border-radius: 0.4rem;
      background: #ECFDF3;
      color: #15803D;
      font-size: 0.75rem;
      font-weight: 700;
   }
   .mse-co-band {
      display: inline-flex;
      align-items: center;
      padding: 0.2rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.6875rem;
      font-weight: 700;
      white-space: nowrap;
   }
   .mse-band--excellent { background: #E8F9E5; color: #15803D; }
   .mse-band--ontrack { background: #ECE9FF; color: #5B52D6; }
   .mse-band--watch { background: #FFF8E6; color: #B45309; }
   .mse-band--critical { background: #FFECEC; color: #DC2626; }
   .mse-co-details summary { list-style: none; }
   .mse-co-details summary::-webkit-details-marker { display: none; }
   .mse-co-details-summary {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      cursor: pointer;
   }
   .mse-co-details[open] .mse-co-details-summary .material-symbols-outlined {
      transform: rotate(180deg);
   }
   .mse-co-details-summary .material-symbols-outlined {
      color: #8B8F98;
      transition: transform 0.15s ease;
   }

   /* ---- PMR Effectiveness Evaluation Dashboard ---- */
   .mse-eff-header {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      justify-content: space-between;
      gap: 1rem 1.5rem;
      padding: 1.1rem 1.25rem;
      border-radius: 1rem;
      border: 1px solid #D9E2EC;
      background: linear-gradient(120deg, #F0FDFA 0%, #FFFFFF 48%, #F8FAFC 100%);
   }
   .mse-eff-kicker {
      margin: 0;
      font-size: 0.6875rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #0F766E;
   }
   .mse-eff-title {
      margin: 0.25rem 0 0.3rem;
      font-size: 1.35rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: #0F172A;
      line-height: 1.2;
   }
   .mse-eff-subtitle {
      margin: 0;
      font-size: 0.8125rem;
      color: #64748B;
      max-width: 40rem;
   }
   .mse-eff-filters {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      align-items: flex-end;
   }
   .mse-eff-kpi {
      padding: 0.95rem 1rem;
      border-radius: 0.85rem;
      border: 1px solid #E2E8F0;
      background: #fff;
      position: relative;
      overflow: hidden;
   }
   .mse-eff-kpi::before {
      content: '';
      position: absolute;
      inset: 0 auto 0 0;
      width: 4px;
   }
   .mse-eff-kpi--total::before { background: #334155; }
   .mse-eff-kpi--l1::before { background: #0891B2; }
   .mse-eff-kpi--l2::before { background: #D97706; }
   .mse-eff-kpi--l3::before { background: #BE123C; }
   .mse-eff-kpi--none::before { background: #64748B; }
   .mse-eff-kpi--upgrade::before { background: #0F766E; }
   .mse-eff-kpi--total { background: #F8FAFC; }
   .mse-eff-kpi--l1 { background: #ECFEFF; }
   .mse-eff-kpi--l2 { background: #FFFBEB; }
   .mse-eff-kpi--l3 { background: #FFF1F2; }
   .mse-eff-kpi--none { background: #F1F5F9; }
   .mse-eff-kpi--upgrade { background: #F0FDFA; }
   .mse-eff-kpi-label {
      margin: 0;
      font-size: 0.6875rem;
      font-weight: 700;
      color: #475569;
      line-height: 1.35;
      min-height: 2.1em;
   }
   .mse-eff-kpi-value {
      margin: 0.45rem 0 0.15rem;
      font-size: 1.65rem;
      font-weight: 800;
      letter-spacing: -0.04em;
      color: #0F172A;
      line-height: 1;
   }
   .mse-eff-kpi-sub {
      margin: 0;
      font-size: 0.75rem;
      font-weight: 600;
      color: #64748B;
   }
   .mse-eff-card-title {
      margin: 0 0 0.35rem;
      font-size: 0.875rem;
      font-weight: 800;
      color: #0F172A;
   }
   .mse-eff-card-sub {
      margin: 0 0 0.75rem;
      font-size: 0.6875rem;
      color: #64748B;
   }
   .mse-eff-split {
      display: grid;
      gap: 0.85rem;
   }
   @media (min-width: 640px) {
      .mse-eff-split { grid-template-columns: 0.9fr 1.1fr; align-items: center; }
   }
   .mse-eff-donut-wrap {
      position: relative;
      height: 170px;
   }
   .mse-eff-donut-center {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      pointer-events: none;
   }
   .mse-eff-donut-center span {
      font-size: 0.625rem;
      font-weight: 700;
      color: #94A3B8;
      text-transform: uppercase;
      letter-spacing: 0.04em;
   }
   .mse-eff-donut-center strong {
      font-size: 1.25rem;
      font-weight: 800;
      color: #0F172A;
      line-height: 1.1;
   }
   .mse-eff-mini-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.75rem;
   }
   .mse-eff-mini-table th {
      text-align: left;
      font-size: 0.625rem;
      font-weight: 700;
      color: #94A3B8;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      padding: 0.35rem 0.25rem;
      border-bottom: 1px solid #E2E8F0;
   }
   .mse-eff-mini-table td {
      padding: 0.4rem 0.25rem;
      border-bottom: 1px solid #F1F5F9;
      color: #334155;
      vertical-align: middle;
   }
   .mse-eff-mini-table tbody tr:nth-child(even) td { background: #F8FAFC; }
   .mse-eff-dot {
      display: inline-block;
      width: 0.5rem;
      height: 0.5rem;
      border-radius: 9999px;
      margin-right: 0.4rem;
      vertical-align: middle;
   }
   .mse-eff-dot--l1 { background: #0891B2; }
   .mse-eff-dot--l2 { background: #D97706; }
   .mse-eff-dot--l3 { background: #BE123C; }
   .mse-eff-dot--none, .mse-eff-dot--needs { background: #64748B; }
   .mse-eff-dot--effective { background: #0F766E; }
   .mse-eff-dot--partial { background: #CA8A04; }
   .mse-eff-dot--ineffective { background: #E11D48; }
   .mse-eff-matrix {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.75rem;
   }
   .mse-eff-matrix th {
      background: #F0FDFA;
      color: #0F766E;
      font-size: 0.625rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      padding: 0.55rem 0.45rem;
      border-bottom: 1px solid #CCFBF1;
      text-align: left;
   }
   .mse-eff-matrix td {
      padding: 0.55rem 0.45rem;
      border-bottom: 1px solid #E2E8F0;
      color: #334155;
   }
   .mse-eff-matrix tbody tr:nth-child(even) td { background: #F8FAFC; }
   .mse-eff-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.18rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.6875rem;
      font-weight: 700;
      white-space: nowrap;
   }
   .mse-eff-badge--effective { background: #CCFBF1; color: #0F766E; }
   .mse-eff-badge--partial { background: #FEF3C7; color: #B45309; }
   .mse-eff-badge--ineffective { background: #FFE4E6; color: #BE123C; }
   .mse-eff-badge--needs_data, .mse-eff-badge--needs { background: #E2E8F0; color: #475569; }
   .mse-eff-badge--level0 { background: #E2E8F0; color: #475569; }
   .mse-eff-badge--level1 { background: #CFFAFE; color: #0E7490; }
   .mse-eff-badge--level2 { background: #FEF3C7; color: #B45309; }
   .mse-eff-badge--level3 { background: #FFE4E6; color: #BE123C; }
   .mse-eff-flag {
      display: inline-flex;
      min-width: 4.5rem;
      justify-content: center;
      padding: 0.15rem 0.4rem;
      border-radius: 0.35rem;
      font-size: 0.6875rem;
      font-weight: 700;
   }
   .mse-eff-flag--yes { background: #FFE4E6; color: #BE123C; }
   .mse-eff-flag--no { background: #ECFDF5; color: #047857; }
   .mse-eff-bar-wrap { height: 180px; }
   .mse-eff-fokus-list { display: grid; gap: 0.65rem; }
   .mse-eff-fokus-item {
      display: flex;
      gap: 0.65rem;
      align-items: flex-start;
      padding: 0.65rem 0.7rem;
      border-radius: 0.7rem;
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
   }
   .mse-eff-fokus-icon {
      width: 1.85rem;
      height: 1.85rem;
      border-radius: 0.5rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #CCFBF1;
      color: #0F766E;
      flex-shrink: 0;
   }
   .mse-eff-fokus-icon .material-symbols-outlined { font-size: 1.05rem; }
   .mse-eff-fokus-item p {
      margin: 0;
      font-size: 0.75rem;
      font-weight: 600;
      color: #334155;
      line-height: 1.45;
   }
   .mse-eff-priority-table td { font-size: 0.8125rem; }
   .mse-eff-footnote {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 0.75rem 1.5rem;
      padding: 0.9rem 1rem;
      border-radius: 0.85rem;
      border: 1px dashed #CBD5E1;
      background: #F8FAFC;
      font-size: 0.75rem;
      color: #64748B;
      margin-bottom: 0.5rem;
   }
   .mse-eff-footnote strong { color: #0F172A; }
   .mse-eff-footnote-meta {
      display: flex;
      flex-direction: column;
      gap: 0.2rem;
      font-size: 0.6875rem;
      font-weight: 600;
      color: #94A3B8;
      text-align: right;
   }

   /* ---- Overall Perusahaan Progress Dashboard ---- */
   .mse-ov-header {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 1rem 1.5rem;
      align-items: flex-end;
      padding: 1rem 1.15rem;
      border-radius: 1rem;
      border: 1px solid #DBEAFE;
      background: linear-gradient(120deg, #EFF6FF 0%, #FFFFFF 55%, #F0FDF4 100%);
   }
   .mse-ov-title {
      margin: 0;
      font-size: 1.25rem;
      font-weight: 800;
      letter-spacing: -0.02em;
      color: #1E3A8A;
      line-height: 1.25;
   }
   .mse-ov-sub {
      margin: 0.3rem 0 0;
      font-size: 0.75rem;
      color: #64748B;
   }
   .mse-ov-header-meta {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 0.45rem;
   }
   .mse-ov-filters {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem;
      justify-content: flex-end;
   }
   .mse-ov-filters .crm-filter-select {
      min-width: 10rem;
      font-size: 0.75rem;
   }
   .mse-ov-meta-text {
      display: flex;
      flex-direction: column;
      gap: 0.15rem;
      font-size: 0.6875rem;
      font-weight: 600;
      color: #64748B;
      text-align: right;
   }
   .mse-ov-meta-text span { display: inline-flex; align-items: center; gap: 0.25rem; justify-content: flex-end; }
   .mse-ov-kpi {
      padding: 0.85rem 0.8rem;
      border-radius: 0.85rem;
      border: 1px solid #E2E8F0;
      background: #fff;
      display: flex;
      flex-direction: column;
      gap: 0.35rem;
      min-height: 7.2rem;
   }
   .mse-ov-kpi-icon {
      width: 1.85rem;
      height: 1.85rem;
      border-radius: 0.5rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
   }
   .mse-ov-kpi-icon .material-symbols-outlined { font-size: 1.05rem; }
   .mse-ov-kpi--blue .mse-ov-kpi-icon { background: #DBEAFE; color: #1D4ED8; }
   .mse-ov-kpi--slate .mse-ov-kpi-icon { background: #E2E8F0; color: #334155; }
   .mse-ov-kpi--indigo .mse-ov-kpi-icon { background: #E0E7FF; color: #4338CA; }
   .mse-ov-kpi--green .mse-ov-kpi-icon { background: #DCFCE7; color: #15803D; }
   .mse-ov-kpi--teal .mse-ov-kpi-icon { background: #CCFBF1; color: #0F766E; }
   .mse-ov-kpi--rose .mse-ov-kpi-icon { background: #FFE4E6; color: #E11D48; }
   .mse-ov-kpi--red .mse-ov-kpi-icon { background: #FEE2E2; color: #DC2626; }
   .mse-ov-kpi--emerald .mse-ov-kpi-icon { background: #D1FAE5; color: #047857; }
   .mse-ov-kpi-value {
      margin: 0;
      font-size: 1.15rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: #0F172A;
      line-height: 1.15;
   }
   .mse-ov-kpi-label {
      margin: 0;
      font-size: 0.6875rem;
      font-weight: 600;
      color: #64748B;
      line-height: 1.35;
   }
   .mse-ov-table td, .mse-ov-table th { font-size: 0.75rem; }
   .mse-ov-company-table thead th {
      white-space: nowrap;
      vertical-align: middle;
   }
   .mse-ov-col-company {
      min-width: 11rem;
      position: sticky;
      left: 2.5rem;
      z-index: 3;
      background: #7366FF;
      color: #fff;
   }
   .mse-ov-company-table thead th:first-child {
      position: sticky;
      left: 0;
      z-index: 3;
      background: #7366FF;
   }
   .mse-ov-company-table tbody td:first-child {
      position: sticky;
      left: 0;
      z-index: 1;
      background: #fff;
   }
   .mse-ov-company-table tbody td:nth-child(2) {
      position: sticky;
      left: 2.5rem;
      z-index: 1;
      background: #fff;
   }
   .mse-ov-company-table tbody tr:nth-child(even) td:first-child,
   .mse-ov-company-table tbody tr:nth-child(even) td:nth-child(2) {
      background: #FAFBFC;
   }
   .mse-ov-company-table .mse-ov-total-row td:first-child,
   .mse-ov-company-table .mse-ov-total-row td:nth-child(2) {
      background: #EFF6FF !important;
   }
   .mse-ov-company-table.crm-data-table thead th.mse-ov-group {
      font-weight: 700;
      letter-spacing: 0.03em;
      color: #fff !important;
   }
   .mse-ov-company-table.crm-data-table thead th.mse-ov-group--total {
      background: #475569 !important;
   }
   .mse-ov-company-table.crm-data-table thead th.mse-ov-group--replikasi {
      background: #5B52D6 !important;
   }
   .mse-ov-company-table.crm-data-table thead th.mse-ov-group--safety {
      background: #15803D !important;
   }
   .mse-ov-company-table.crm-data-table thead th.mse-ov-group--additional {
      background: #4D7C0F !important;
   }
   .mse-ov-total-row td { background: #EFF6FF !important; }
   .mse-prog {
      display: flex;
      align-items: center;
      gap: 0.4rem;
   }
   .mse-prog-track {
      flex: 1;
      height: 0.4rem;
      border-radius: 9999px;
      background: #E2E8F0;
      overflow: hidden;
   }
   .mse-prog-bar { height: 100%; border-radius: inherit; }
   .mse-prog-bar--green { background: #16A34A; }
   .mse-prog-bar--amber { background: #CA8A04; }
   .mse-prog-bar--orange { background: #EA580C; }
   .mse-prog-bar--red { background: #DC2626; }
   .mse-prog-pct {
      min-width: 2.4rem;
      font-size: 0.6875rem;
      font-weight: 700;
      color: #475569;
      text-align: right;
   }
   .mse-prog-track--lg { height: 0.55rem; }

   /* ---- Overall Perusahaan — monochrome professional ---- */
   .mse-ov2-mono {
      --ov2-ink: #111827;
      --ov2-ink-soft: #374151;
      --ov2-muted: #6B7280;
      --ov2-line: #E5E7EB;
      --ov2-line-soft: #F3F4F6;
      --ov2-surface: #FFFFFF;
      --ov2-surface-2: #FAFAFA;
      --ov2-fill-full: #111827;
      --ov2-fill-high: #374151;
      --ov2-fill-mid: #6B7280;
      --ov2-fill-low: #9CA3AF;
      --ov2-fill-empty: #E5E7EB;
   }
   .mse-ov2-mono .crm-card-title { color: var(--ov2-ink); }
   .mse-ov2-mono .text-crm-muted { color: var(--ov2-muted) !important; }
   .mse-ov2-card {
      border-color: var(--ov2-line) !important;
      box-shadow: none !important;
   }
   .mse-ov2-bar {
      display: flex;
      align-items: center;
      gap: 0.45rem;
   }
   .mse-ov2-bar-track {
      flex: 1;
      height: 0.35rem;
      border-radius: 9999px;
      background: var(--ov2-line-soft);
      overflow: hidden;
   }
   .mse-ov2-bar-track--lg { height: 0.5rem; }
   .mse-ov2-bar-fill {
      height: 100%;
      border-radius: inherit;
      background: var(--ov2-fill-mid);
   }
   .mse-ov2-bar-fill--full { background: var(--ov2-fill-full); }
   .mse-ov2-bar-fill--high { background: var(--ov2-fill-high); }
   .mse-ov2-bar-fill--mid { background: var(--ov2-fill-mid); }
   .mse-ov2-bar-fill--low { background: var(--ov2-fill-low); }
   .mse-ov2-bar-fill--empty { background: var(--ov2-fill-empty); width: 0 !important; }
   .mse-ov2-pct {
      min-width: 2.4rem;
      font-size: 0.7rem;
      font-weight: 650;
      color: var(--ov2-ink-soft);
      text-align: right;
   }
   .mse-ov2-chip {
      display: inline-flex;
      align-items: center;
      padding: 0.15rem 0.45rem;
      border-radius: 0.35rem;
      border: 1px solid var(--ov2-line);
      background: var(--ov2-surface);
      color: var(--ov2-ink-soft);
      font-size: 0.65rem;
      font-weight: 600;
      letter-spacing: 0.01em;
   }
   .mse-ov2-chip--strong {
      background: var(--ov2-ink);
      border-color: var(--ov2-ink);
      color: #fff;
   }
   .mse-ov2-kpi-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.75rem;
   }
   @media (min-width: 768px) {
      .mse-ov2-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
   }
   @media (min-width: 1280px) {
      .mse-ov2-kpi-grid { grid-template-columns: 1.35fr repeat(4, minmax(0, 1fr)); }
   }
   .mse-ov2-kpi {
      background: var(--ov2-surface);
      border: 1px solid var(--ov2-line);
      border-radius: 0.65rem;
      padding: 1rem 1.05rem;
   }
   .mse-ov2-kpi--primary {
      background: var(--ov2-surface-2);
      border-color: var(--ov2-line);
   }
   .mse-ov2-kpi-label {
      margin: 0;
      font-size: 0.68rem;
      font-weight: 600;
      color: var(--ov2-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
   }
   .mse-ov2-kpi-value {
      margin: 0.35rem 0 0;
      font-size: 1.55rem;
      font-weight: 700;
      color: var(--ov2-ink);
      letter-spacing: -0.03em;
      line-height: 1.1;
   }
   .mse-ov2-kpi-hint {
      margin: 0.45rem 0 0;
      font-size: 0.7rem;
      color: var(--ov2-muted);
   }
   .mse-ov2-cat {
      background: var(--ov2-surface);
      border: 1px solid var(--ov2-line);
      border-radius: 0.65rem;
      padding: 0.95rem 1.05rem;
   }
   .mse-ov2-cat-head {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--ov2-ink-soft);
   }
   .mse-ov2-cat-head .material-symbols-outlined {
      font-size: 1.05rem;
      color: var(--ov2-muted);
   }
   .mse-ov2-cat-value {
      margin: 0.45rem 0 0.55rem;
      font-size: 1.4rem;
      font-weight: 700;
      color: var(--ov2-ink);
      letter-spacing: -0.03em;
   }
   .mse-ov2-site-grid {
      display: grid;
      grid-template-columns: repeat(1, minmax(0, 1fr));
      gap: 0.75rem;
   }
   @media (min-width: 640px) {
      .mse-ov2-site-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
   }
   @media (min-width: 1100px) {
      .mse-ov2-site-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
   }
   .mse-ov2-site-card {
      border: 1px solid var(--ov2-line);
      border-radius: 0.65rem;
      padding: 0.9rem 1rem;
      background: var(--ov2-surface);
   }
   .mse-ov2-site-card-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
      margin-bottom: 0.55rem;
   }
   .mse-ov2-site-name {
      margin: 0;
      font-size: 0.92rem;
      font-weight: 700;
      color: var(--ov2-ink);
   }
   .mse-ov2-site-pct {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--ov2-ink);
   }
   .mse-ov2-site-meta {
      display: flex;
      justify-content: space-between;
      font-size: 0.7rem;
      color: var(--ov2-muted);
      margin-bottom: 0.55rem;
   }
   .mse-ov2-site-status {
      display: flex;
      flex-wrap: wrap;
      gap: 0.35rem;
      margin-bottom: 0.65rem;
   }
   .mse-ov2-site-cats {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 0.4rem;
      border-top: 1px solid var(--ov2-line-soft);
      padding-top: 0.55rem;
   }
   .mse-ov2-site-cats div {
      text-align: center;
      background: var(--ov2-surface-2);
      border: 1px solid var(--ov2-line-soft);
      border-radius: 0.4rem;
      padding: 0.35rem 0.2rem;
   }
   .mse-ov2-site-cats span {
      display: block;
      font-size: 0.62rem;
      color: var(--ov2-muted);
      font-weight: 600;
      text-transform: uppercase;
   }
   .mse-ov2-site-cats strong {
      font-size: 0.85rem;
      color: var(--ov2-ink);
   }
   .mse-ov2-chart-wrap { height: 260px; position: relative; }
   .mse-ov2-rank-list { display: flex; flex-direction: column; gap: 0.55rem; }
   .mse-ov2-rank-item {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(7rem, 0.9fr);
      gap: 0.75rem;
      align-items: center;
      padding: 0.55rem 0.65rem;
      border-radius: 0.5rem;
      background: var(--ov2-surface-2);
      border: 1px solid var(--ov2-line);
   }
   .mse-ov2-rank-name {
      display: block;
      font-size: 0.82rem;
      font-weight: 650;
      color: var(--ov2-ink);
   }
   .mse-ov2-rank-site {
      display: block;
      font-size: 0.68rem;
      color: var(--ov2-muted);
      margin-top: 0.1rem;
   }
   .mse-ov2-site-block {
      border: 1px solid var(--ov2-line);
      border-radius: 0.65rem;
      overflow: hidden;
      margin-bottom: 0.9rem;
      background: var(--ov2-surface);
   }
   .mse-ov2-site-block:last-of-type { margin-bottom: 0; }
   .mse-ov2-site-block-head {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      padding: 0.85rem 1rem;
      background: var(--ov2-surface-2);
      border-bottom: 1px solid var(--ov2-line);
   }
   @media (min-width: 768px) {
      .mse-ov2-site-block-head {
         flex-direction: row;
         align-items: center;
         justify-content: space-between;
      }
      .mse-ov2-site-block-progress { min-width: 14rem; }
   }
   .mse-ov2-site-block-title {
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
   }
   .mse-ov2-site-block-title .material-symbols-outlined {
      color: var(--ov2-muted);
      margin-top: 0.1rem;
   }
   .mse-ov2-site-block-name {
      margin: 0;
      font-size: 0.92rem;
      font-weight: 700;
      color: var(--ov2-ink);
   }
   .mse-ov2-site-block-sub {
      margin: 0.15rem 0 0;
      font-size: 0.7rem;
      color: var(--ov2-muted);
   }
   .mse-ov2-detail-table.crm-data-table thead th {
      background: #111827 !important;
      color: #fff !important;
      font-size: 0.68rem;
      font-weight: 600;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      border-radius: 0 !important;
   }
   .mse-ov2-detail-table.crm-data-table tbody td {
      color: var(--ov2-ink-soft);
      border-bottom-color: var(--ov2-line-soft);
   }
   .mse-ov2-detail-table.crm-data-table tbody tr:nth-child(even) td {
      background: var(--ov2-surface-2);
   }
   .mse-ov2-detail-table.crm-data-table tbody tr:hover td {
      background: #F9FAFB;
   }
   .mse-ov2-subtotal-row td {
      background: #F3F4F6 !important;
      color: var(--ov2-ink) !important;
      border-top: 1px solid var(--ov2-line);
   }
   .mse-ov2-mini-status {
      display: flex;
      flex-wrap: wrap;
      gap: 0.25rem;
      justify-content: center;
   }
   .mse-ov2-grand-total {
      margin-top: 1rem;
      padding: 1rem 1.1rem;
      border-radius: 0.65rem;
      background: var(--ov2-surface-2);
      border: 1px solid var(--ov2-line);
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
   }
   @media (min-width: 900px) {
      .mse-ov2-grand-total {
         flex-direction: row;
         align-items: center;
         justify-content: space-between;
      }
   }
   .mse-ov2-grand-label {
      margin: 0;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      color: var(--ov2-ink);
   }
   .mse-ov2-grand-sub {
      margin: 0.2rem 0 0;
      font-size: 0.72rem;
      color: var(--ov2-muted);
   }
   .mse-ov2-grand-metrics {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.5rem;
   }
   @media (min-width: 640px) {
      .mse-ov2-grand-metrics { grid-template-columns: repeat(4, minmax(0, 1fr)); }
   }
   .mse-ov2-grand-metric {
      background: var(--ov2-surface);
      border-radius: 0.5rem;
      padding: 0.55rem 0.7rem;
      border: 1px solid var(--ov2-line);
      min-width: 6.5rem;
   }
   .mse-ov2-grand-metric span {
      display: block;
      font-size: 0.65rem;
      color: var(--ov2-muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.03em;
   }
   .mse-ov2-grand-metric strong {
      display: block;
      margin-top: 0.15rem;
      font-size: 1.05rem;
      color: var(--ov2-ink);
   }

   .mse-ov-chart-wrap { height: 280px; }
   .mse-ov-footnote {
      padding: 0.85rem 1rem;
      border-radius: 0.75rem;
      border: 1px dashed #CBD5E1;
      background: #F8FAFC;
      font-size: 0.75rem;
      color: #64748B;
      margin-bottom: 0.25rem;
   }
   .mse-ov-footnote strong { color: #0F172A; }

   /* ---- Handsontable grid (Update Data) — Excel-like ---- */
   .crm-grid-toolbar {
      display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
      gap: 0.5rem; margin-bottom: 0.5rem;
   }
   .crm-grid-toolbar-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; }
   .crm-grid-btn {
      display: inline-flex; align-items: center; gap: 0.3rem;
      padding: 0.35rem 0.7rem; border-radius: 0.25rem;
      font-size: 0.75rem; font-weight: 600; border: 1px solid #C6C6C6;
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
      background: #fff; border: 1px solid #B4B4B4;
      border-radius: 0; overflow: visible;
      box-shadow: none;
   }
   .crm-grid-container { width: 100%; min-height: 420px; }

   /* ---- Column visibility picker ---- */
   .crm-col-picker { position: relative; }
   .crm-col-picker-panel {
      display: none; position: fixed; z-index: 2100;
      width: 18rem; max-height: min(28rem, calc(100vh - 6rem));
      background: #fff; border: 1px solid #B4B4B4; border-radius: 0.25rem;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
      overflow: hidden;
   }
   .crm-col-picker-panel--open { display: flex; flex-direction: column; }
   .crm-col-picker-head {
      display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
      padding: 0.5rem 0.75rem; border-bottom: 1px solid #E0E0E0;
      font-size: 0.75rem; font-weight: 700; color: #2F2F3A; flex-shrink: 0;
      background: #F3F3F3;
   }
   .crm-col-picker-head-actions { display: flex; gap: 0.5rem; }
   .crm-col-picker-link {
      border: none; background: none; padding: 0; cursor: pointer;
      font-size: 0.6875rem; font-weight: 600; color: #217346; text-decoration: underline;
   }
   .crm-col-picker-link:hover { color: #1a5c38; }
   .crm-col-picker-search-wrap {
      padding: 0.45rem 0.65rem 0.35rem; border-bottom: 1px solid #EFEFEF; flex-shrink: 0;
   }
   .crm-col-picker-search {
      width: 100%; border: 1px solid #C6C6C6; border-radius: 0.2rem;
      padding: 0.3rem 0.5rem; font-size: 0.75rem; color: #2F2F3A; outline: none;
   }
   .crm-col-picker-search:focus { border-color: #217346; }
   .crm-col-picker-body { overflow-y: auto; padding: 0.35rem 0.3rem; min-height: 0; flex: 1; }
   .crm-col-picker-group {
      margin: 0.45rem 0.5rem 0.15rem; font-size: 0.625rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.06em; color: #6B7280;
   }
   .crm-col-picker-group:first-child { margin-top: 0.1rem; }
   .crm-col-picker-item {
      display: flex; align-items: center; gap: 0.5rem;
      padding: 0.28rem 0.45rem; border-radius: 0.2rem; cursor: pointer;
      font-size: 0.75rem; color: #2F2F3A;
   }
   .crm-col-picker-item:hover { background: #E7F4EA; }
   .crm-col-picker-item input { accent-color: #217346; cursor: pointer; }
   .crm-col-picker-empty {
      padding: 0.75rem 0.5rem; text-align: center; font-size: 0.75rem; color: #848488;
   }
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

   #mse-record-grid .handsontable {
      font-family: Calibri, 'Segoe UI', Arial, sans-serif;
      font-size: 11px;
      color: #000;
   }
   #mse-record-grid .handsontable th,
   #mse-record-grid .handsontable td {
      border: 1px solid #D0D0D0 !important;
      white-space: nowrap !important;
      overflow: hidden !important;
      text-overflow: ellipsis !important;
      line-height: 18px !important;
      vertical-align: middle;
   }
   #mse-record-grid .handsontable tbody td {
      background: #fff;
      color: #000;
      padding: 1px 4px !important;
      height: 22px !important;
   }
   #mse-record-grid .handsontable thead th {
      background: #F2F2F2 !important;
      color: #000 !important;
      font-weight: 600 !important;
      font-size: 11px !important;
      padding: 2px 6px !important;
      text-align: center;
   }
   #mse-record-grid .handsontable thead tr:first-child th {
      background: #E7E6E6 !important;
      font-weight: 700 !important;
      text-transform: uppercase;
      letter-spacing: 0.02em;
      font-size: 10px !important;
      color: #333 !important;
   }
   #mse-record-grid .handsontable tbody tr:nth-child(even) td { background: #fff; }
   #mse-record-grid .handsontable td.area { background: #E7F4EA !important; }
   #mse-record-grid .handsontable td.current,
   #mse-record-grid .handsontable td.ht__highlight,
   #mse-record-grid .handsontable td.ht__active_highlight {
      background: #fff !important;
      box-shadow: inset 0 0 0 2px #217346;
   }
   #mse-record-grid .handsontable .htDimmed { color: #666 !important; }
   #mse-record-grid .handsontable .ht_clone_left th,
   #mse-record-grid .handsontable .ht_clone_top_inline_start_corner th,
   #mse-record-grid .handsontable .ht_clone_inline_start td {
      background: #F8F8F8 !important;
   }
   #mse-record-grid .ht-status-done { color: #217346; font-weight: 700; }
   #mse-record-grid .ht-status-progress { color: #C65911; font-weight: 600; }
   #mse-record-grid .ht-status-notyet { color: #666; }
   #mse-record-grid .ht-status-on-target { box-shadow: inset 3px 0 0 #217346; }
   #mse-record-grid .ht-status-overdue { box-shadow: inset 3px 0 0 #C00000; background: #FFF0F0 !important; }
   #mse-record-grid .ht-status-no-due { box-shadow: inset 3px 0 0 #C65911; }
   #mse-record-grid .handsontableInputHolder .handsontableInput,
   #mse-record-grid textarea.handsontableInput {
      font-family: Calibri, 'Segoe UI', Arial, sans-serif !important;
      font-size: 11px !important;
      border: 2px solid #217346 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      padding: 1px 3px !important;
      line-height: 18px !important;
   }

   #mse-record-grid .ht_clone_left .htCore td:last-of-type,
   #mse-record-grid .ht_clone_top_left_corner .htCore thead tr:last-child th:last-of-type,
   #mse-record-grid .ht_clone_inline_start .htCore td:last-of-type,
   #mse-record-grid .ht_clone_top_inline_start_corner .htCore thead tr:last-child th:last-of-type {
      box-shadow: 3px 0 6px -2px rgba(0, 0, 0, 0.12);
      border-right: 2px solid #A6A6A6 !important;
   }

   .htDropdownMenu,
   .htFiltersMenuCondition,
   .htFiltersMenuValue {
      z-index: 2050 !important;
      max-height: 280px !important;
      font-size: 12px !important;
      font-family: Calibri, 'Segoe UI', Arial, sans-serif;
   }
   .htDropdownMenu .wtHolder,
   .htFiltersMenuValue .wtHolder {
      max-height: 240px !important;
   }

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
   .crm-history-panel.crm-category-panel--safety .crm-category-summary-item--accent {
      background: #E8F9E5;
      border-color: #C6EBC0;
   }
   .crm-history-panel.crm-category-panel--safety .crm-category-modal-filters {
      background: #F7FCF8;
      border-color: #D8EEDD;
   }
   .crm-history-panel.crm-category-panel--additional .crm-category-summary-item--accent {
      background: #F7FEE7;
      border-color: #D9F99D;
   }
   .crm-history-panel.crm-category-panel--additional .crm-category-modal-filters {
      background: #FCFFF5;
      border-color: #E2EFBF;
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
   .crm-history-filters {
      display: flex; flex-wrap: wrap; gap: 0.4rem;
      padding: 0.65rem 1.25rem 0.75rem;
      border-bottom: 1px solid #EEF0F3;
      background: #FAFBFC;
   }
   .crm-history-filter {
      border: 1px solid #E6E9EB; background: #fff; color: #4B5563;
      border-radius: 9999px; padding: 0.2rem 0.7rem;
      font-size: 0.75rem; font-weight: 600; cursor: pointer;
   }
   .crm-history-filter--active {
      background: #ECE9FF; border-color: #C9C3FF; color: #5f52e0;
   }
   .crm-history-entry {
      border: 1px solid #E6E9EB; border-radius: 0.65rem; margin-bottom: 0.7rem; overflow: hidden;
   }
   .crm-history-entry-meta {
      display: flex; flex-wrap: wrap; align-items: center; gap: 0.45rem 0.85rem;
      padding: 0.55rem 0.85rem; background: #FAFBFC; border-bottom: 1px solid #E6E9EB;
      font-size: 0.75rem;
   }
   .crm-history-entry-body { padding: 0.65rem 0.85rem 0.75rem; }
   .crm-history-diff {
      display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.4rem 0.55rem;
      margin-top: 0.3rem; font-size: 0.8125rem;
   }
   .crm-history-arrow { color: #9CA3AF; }
   .crm-history-empty {
      text-align: center; color: #848488; font-size: 0.8125rem; padding: 2rem 1rem;
   }
</style>
