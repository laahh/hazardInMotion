<style>
   .ra-page-head {
      display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between;
      gap: 1rem; margin-bottom: 1.25rem;
   }
   .ra-page-title { font-size: 1.125rem; font-weight: 700; color: #2F2F3A; line-height: 1.3; }
   .ra-page-subtitle { font-size: 0.8125rem; color: #848488; margin-top: 0.25rem; max-width: 46rem; line-height: 1.5; }
   .ra-head-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }

   .ra-btn {
      display: inline-flex; align-items: center; gap: 0.35rem;
      height: 2.375rem; padding: 0 0.95rem; border: 1px solid transparent; border-radius: 0.5rem;
      font-size: 0.8125rem; font-weight: 600; cursor: pointer; text-decoration: none;
      transition: background-color .15s ease, border-color .15s ease, color .15s ease;
   }
   .ra-btn--primary { background: #7366FF; color: #fff; }
   .ra-btn--primary:hover { background: #5E4FE8; }
   .ra-btn--ghost { background: #fff; border-color: #E6E9EB; color: #2F2F3A; }
   .ra-btn--ghost:hover { border-color: #C9CFD4; background: #F8FAFB; }
   .ra-btn--danger { background: #fff; border-color: #F3C9D6; color: #C0244F; }
   .ra-btn--danger:hover { background: #FDF2F5; border-color: #E9A9BF; }
   .ra-btn--sm { height: 2rem; padding: 0 0.7rem; font-size: 0.75rem; }

   .ra-card {
      background: #fff; border: 1px solid #E6E9EB; border-radius: 0.875rem;
      box-shadow: 0 2px 12px rgba(47, 47, 58, 0.04); padding: 1.25rem;
   }
   .ra-card + .ra-card { margin-top: 1rem; }
   .ra-card-title { font-size: 0.9375rem; font-weight: 700; color: #2F2F3A; }
   .ra-card-hint { font-size: 0.75rem; color: #848488; margin-top: 0.2rem; line-height: 1.5; }

   .ra-alert {
      border-radius: 0.75rem; padding: 0.7rem 0.95rem; font-size: 0.8125rem;
      font-weight: 500; margin-bottom: 1rem; line-height: 1.5;
   }
   .ra-alert--error { background: #FDF2F5; border: 1px solid #F3C9D6; color: #9B1C3D; }
   .ra-alert--info { background: #ECE9FF; border: 1px solid #D6CFFF; color: #4B3FBE; }
   .ra-alert ul { margin: 0.35rem 0 0; padding-left: 1.1rem; list-style: disc; }

   .ra-field { display: flex; flex-direction: column; gap: 0.3rem; }
   .ra-label { font-size: 0.75rem; font-weight: 600; color: #595C5E; }
   .ra-label span { color: #C0244F; }
   .ra-input, .ra-select, .ra-textarea {
      width: 100%; box-sizing: border-box; background: #F0F4F8; border: 1px solid transparent;
      border-radius: 0.5rem; padding: 0.55rem 0.8rem; font-size: 0.8125rem; font-weight: 500;
      color: #2F2F3A; font-family: inherit;
   }
   .ra-input:focus, .ra-select:focus, .ra-textarea:focus {
      outline: none; background: #fff; border-color: #7366FF;
   }
   .ra-input--error, .ra-select--error { border-color: #E9A9BF; background: #FDF2F5; }
   .ra-textarea { min-height: 4.5rem; resize: vertical; }
   .ra-field-error { font-size: 0.6875rem; font-weight: 600; color: #C0244F; }
   .ra-field-hint { font-size: 0.6875rem; color: #848488; }
   .ra-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 1rem; }

   .ra-switch { display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; }
   .ra-switch input { width: 1.05rem; height: 1.05rem; accent-color: #7366FF; cursor: pointer; }
   .ra-switch span { font-size: 0.8125rem; font-weight: 600; color: #2F2F3A; }

   .ra-scope-rows { display: flex; flex-direction: column; gap: 0.85rem; }
   .ra-scope-row { border: 1px solid #E6E9EB; border-radius: 0.75rem; padding: 0.95rem; background: #FAFBFC; }
   .ra-scope-row-head {
      display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.75rem;
   }
   .ra-scope-row-label { font-size: 0.75rem; font-weight: 700; color: #595C5E; letter-spacing: 0.02em; }
   .ra-scope-body { display: grid; grid-template-columns: minmax(12rem, 16rem) 1fr; gap: 1rem; }
   @media (max-width: 760px) { .ra-scope-body { grid-template-columns: 1fr; } }

   .ra-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; }
   .ra-chip {
      display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer;
      background: #fff; border: 1px solid #E6E9EB; border-radius: 999px;
      padding: 0.3rem 0.7rem; font-size: 0.75rem; font-weight: 600; color: #595C5E;
      transition: background-color .15s ease, border-color .15s ease, color .15s ease;
   }
   .ra-chip:hover { border-color: #C9CFD4; }
   .ra-chip input { width: 0.85rem; height: 0.85rem; accent-color: #7366FF; cursor: pointer; margin: 0; }
   .ra-chip:has(input:checked) { background: #ECE9FF; border-color: #BDB2FF; color: #4B3FBE; }
   .ra-chip--all:has(input:checked) { background: #E6F6EC; border-color: #A9DEBC; color: #1F7A46; }

   .ra-table-wrap { overflow-x: auto; }
   .ra-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
   .ra-table thead th {
      text-align: left; background: #F4F7F9; color: #6B7280; font-weight: 700;
      font-size: 0.625rem; letter-spacing: 0.05em; text-transform: uppercase;
      padding: 0.6rem 0.75rem; border-bottom: 1px solid #E8EAED; white-space: nowrap;
   }
   .ra-table tbody td { padding: 0.75rem; border-bottom: 1px solid #F0F2F5; vertical-align: top; }
   .ra-table tbody tr:last-child td { border-bottom: none; }
   .ra-table tbody tr:hover td { background: #FBFAFF; }
   .ra-table-empty { text-align: center; color: #848488; padding: 2.5rem 1rem; }

   .ra-identity { font-weight: 700; color: #2F2F3A; }
   .ra-identity-meta { font-size: 0.6875rem; color: #848488; font-weight: 500; margin-top: 0.15rem; }
   .ra-sid {
      display: inline-block; margin-left: 0.3rem; padding: 0.05rem 0.35rem;
      background: #ECE9FF; color: #4B3FBE; border-radius: 0.35rem;
      font-size: 0.6875rem; font-weight: 700; letter-spacing: 0.03em;
   }

   .ra-scope-list { display: flex; flex-direction: column; gap: 0.4rem; }
   .ra-scope-item { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; }
   .ra-scope-company {
      font-weight: 700; color: #2F2F3A; font-size: 0.75rem;
      background: #F0F4F8; border-radius: 0.35rem; padding: 0.1rem 0.4rem;
   }
   .ra-scope-site {
      font-size: 0.6875rem; font-weight: 600; color: #595C5E;
      border: 1px solid #E6E9EB; border-radius: 999px; padding: 0.05rem 0.45rem; background: #fff;
   }
   .ra-scope-site--all { color: #1F7A46; border-color: #A9DEBC; background: #E6F6EC; }
   .ra-scope-none { font-size: 0.75rem; color: #C0244F; font-weight: 600; }

   .ra-pill {
      display: inline-flex; align-items: center; gap: 0.3rem;
      font-size: 0.6875rem; font-weight: 700; border-radius: 999px; padding: 0.15rem 0.5rem;
   }
   .ra-pill--on { background: #E6F6EC; color: #1F7A46; }
   .ra-pill--off { background: #F1F2F4; color: #6B7280; }

   .ra-row-actions { display: flex; align-items: center; gap: 0.35rem; white-space: nowrap; }

   .ra-search { display: flex; align-items: center; gap: 0.5rem; }
   .ra-search .ra-input { min-width: 15rem; }

   .ra-form-actions {
      display: flex; align-items: center; justify-content: flex-end; gap: 0.6rem; margin-top: 1.25rem;
   }
</style>
