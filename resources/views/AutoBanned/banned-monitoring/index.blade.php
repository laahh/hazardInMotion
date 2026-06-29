@extends('AutoBanned.layouts.app')

@section('title', 'Monitoring Daily Banned')

@section('page-header')
@endsection

@push('head')
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Two+Tone" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<style>
   .ab-phppot {
      --cyan: #01b0c6;
      --blue-line: #0000ff;
      --primary: #01b0c6;
      --card-shadow: 0 1px 20px 0 rgba(69, 90, 100, 0.08);
      --text-muted: #888;
      --text-dark: #333;
      --border-light: #f1f1f1;
   }
   .ab-phppot { color: var(--text-dark); font-size: 14px; }
   .ab-phppot .material-icons-two-tone {
      font-family: 'Material Icons Two Tone';
      font-weight: normal;
      font-style: normal;
      font-size: 42px;
      line-height: 1;
      letter-spacing: normal;
      text-transform: none;
      display: inline-block;
      white-space: nowrap;
      word-wrap: normal;
      direction: ltr;
      color: var(--primary);
   }
   .ab-phppot .text-primary { color: var(--primary) !important; }

   /* ── Bootstrap-like row/col gutter ── */
   .ab-phppot .dash-row { display: flex; flex-wrap: wrap; margin: 0 -12px; }
   .ab-phppot .dash-col-6 { width: 50%; padding: 0 12px; }
   .ab-phppot .dash-col-12 { width: 100%; padding: 0 12px; }
   @media (max-width: 1199px) {
      .ab-phppot .dash-col-xl-6 { width: 100%; }
   }
   @media (min-width: 1200px) {
      .ab-phppot .dash-col-xl-6 { width: 50%; padding: 0 12px; }
   }
   @media (max-width: 767px) {
      .ab-phppot .dash-col-6 { width: 100%; }
   }

   /* ── Product card (prod-p-card) ── */
   .ab-phppot .prod-p-card {
      background: #fff;
      border: none;
      border-radius: 5px;
      box-shadow: var(--card-shadow);
      margin-bottom: 24px;
   }
   .ab-phppot .prod-p-card .card-body { padding: 20px 25px; }
   .ab-phppot .prod-p-card h6.m-b-5 {
      margin-bottom: 5px;
      font-size: 14px;
      font-weight: 400;
      color: var(--text-muted);
   }
   .ab-phppot .prod-p-card h3.mb-0 {
      font-size: 28px;
      font-weight: 600;
      color: var(--text-dark);
      margin: 0;
      line-height: 1.2;
   }
   .ab-phppot .prod-p-card .card-icon-col { flex-shrink: 0; }

   /* ── Generic card ── */
   .ab-phppot .dash-card {
      background: #fff;
      border: none;
      border-radius: 5px;
      box-shadow: var(--card-shadow);
      margin-bottom: 24px;
   }
   .ab-phppot .card-header {
      padding: 16px 20px;
      background: transparent;
      border-bottom: 1px solid var(--border-light);
   }
   .ab-phppot .card-header h5 {
      margin: 0;
      font-size: 15px;
      font-weight: 600;
      color: var(--text-dark);
   }
   .ab-phppot .card-body { padding: 20px; }
   .ab-phppot .card-body.p-0 { padding: 0; }

   /* ── Support bar widgets ── */
   .ab-phppot .support-bar { overflow: hidden; }
   .ab-phppot .support-bar .card-body.pb-0 { padding-bottom: 0; }
   .ab-phppot .support-bar h2.m-0 {
      font-size: 32px;
      font-weight: 600;
      margin: 0;
      color: var(--text-dark);
      line-height: 1.2;
   }
   .ab-phppot .support-bar .label-cyan {
      color: var(--cyan);
      font-size: 14px;
      font-weight: 400;
      display: block;
      margin-top: 2px;
   }
   .ab-phppot .support-bar .widget-desc {
      font-size: 13px;
      color: var(--text-muted);
      margin: 12px 0 16px;
   }
   .ab-phppot .card-footer {
      padding: 14px 0;
      background: #fff;
      border-top: 1px solid var(--border-light);
   }
   .ab-phppot .card-footer.border-0 { border: none; }
   .ab-phppot .card-footer.bg-cyan {
      background-color: var(--cyan) !important;
      color: #fff;
   }
   .ab-phppot .card-footer .footer-stat h4 {
      font-size: 18px;
      font-weight: 600;
      margin: 0 0 2px;
   }
   .ab-phppot .card-footer .footer-stat span {
      font-size: 12px;
      opacity: 0.9;
   }
   .ab-phppot .card-footer.bg-cyan .footer-stat h4 { color: #fff; }
   .ab-phppot .card-footer:not(.bg-cyan) .footer-stat h4 { color: var(--text-dark); }
   .ab-phppot .card-footer:not(.bg-cyan) .footer-stat span { color: var(--text-muted); opacity: 1; }
   .ab-phppot .footer-row {
      display: flex;
      text-align: center;
   }
   .ab-phppot .footer-row > div { flex: 1; }

   /* ── Monthly report ── */
   .ab-phppot .report-metrics {
      display: flex;
      gap: 2rem;
      padding-bottom: 8px;
   }
   .ab-phppot .report-metrics h3 {
      font-size: 24px;
      font-weight: 600;
      margin: 0 0 4px;
      color: var(--text-dark);
   }
   .ab-phppot .report-metrics span {
      font-size: 13px;
      color: var(--text-muted);
   }
   .ab-phppot .chart-main { height: 350px; }

   /* ── Customer satisfaction ── */
   .ab-phppot .satisfaction h6 {
      font-size: 14px;
      font-weight: 600;
      margin: 0 0 6px;
      color: var(--text-dark);
   }
   .ab-phppot .satisfaction > span {
      font-size: 13px;
      color: var(--text-muted);
      line-height: 1.6;
      display: block;
   }
   .ab-phppot .chart-pie { height: 260px; }

   /* ── Wishlist table ── */
   .ab-phppot .wishlist-table { width: 100%; border-collapse: collapse; margin: 0; }
   .ab-phppot .wishlist-table thead th {
      padding: 12px 20px;
      font-size: 12px;
      font-weight: 600;
      color: var(--text-muted);
      text-align: left;
      border-bottom: 1px solid var(--border-light);
      background: #fafafa;
      white-space: nowrap;
   }
   .ab-phppot .wishlist-table thead th.sortable { cursor: pointer; }
   .ab-phppot .wishlist-table thead th .sort-icon {
      font-size: 11px;
      margin-left: 4px;
      opacity: 0.5;
   }
   .ab-phppot .wishlist-table tbody td {
      padding: 14px 20px;
      font-size: 13px;
      border-bottom: 1px solid #f5f5f5;
      vertical-align: middle;
      color: var(--text-dark);
   }
   .ab-phppot .wishlist-table tbody tr:hover { background: #fafbfc; }
   .ab-phppot .wishlist-table tbody tr:last-child td { border-bottom: none; }
   .ab-phppot .wishlist-scroll { max-height: 340px; overflow-y: auto; }
   .ab-phppot .wishlist-scroll::-webkit-scrollbar { width: 4px; }
   .ab-phppot .wishlist-scroll::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
   .ab-phppot .item-thumb {
      width: 32px; height: 32px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 10px;
      flex-shrink: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 700;
      color: #fff;
   }
   .ab-phppot .item-desc {
      display: inline-flex;
      align-items: center;
      min-width: 0;
   }
   .ab-phppot .item-desc .name {
      font-weight: 500;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 130px;
      display: inline-block;
      vertical-align: middle;
   }
   .ab-phppot .reason-cell {
      max-width: 180px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      color: var(--text-muted);
      font-size: 12px;
   }
   .ab-phppot .badge {
      display: inline-block;
      padding: 4px 10px;
      font-size: 11px;
      font-weight: 600;
      border-radius: 4px;
      line-height: 1.2;
   }
   .ab-phppot .badge-success { background: #1de9b6; color: #fff; }
   .ab-phppot .badge-danger { background: #f44236; color: #fff; }
   .ab-phppot .badge-warning { background: #f4c22b; color: #fff; }
   .ab-phppot .badge-info { background: #3ebfea; color: #fff; }
   .ab-phppot .badge-secondary { background: #a9b4bc; color: #fff; }
   .ab-phppot .action-btns { white-space: nowrap; }
   .ab-phppot .action-btns a, .ab-phppot .action-btns span {
      display: inline-block;
      font-size: 16px;
      cursor: pointer;
      text-decoration: none;
   }
   .ab-phppot .action-btns .act-edit { color: #1de9b6; }
   .ab-phppot .action-btns .act-del { color: #f44236; margin-left: 12px; }

   .ab-phppot .list-tabs {
      display: flex;
      gap: 4px;
      margin-left: auto;
      flex-shrink: 0;
   }
   .ab-phppot .card-header-with-tabs {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
   }
   .ab-phppot .list-tab-btn {
      font-size: 12px;
      font-weight: 600;
      padding: 6px 14px;
      border-radius: 4px;
      border: none;
      background: transparent;
      color: var(--text-muted);
      cursor: pointer;
      transition: all 0.2s;
      white-space: nowrap;
   }
   .ab-phppot .list-tab-btn:hover { color: var(--cyan); background: rgba(1, 176, 198, 0.08); }
   .ab-phppot .list-tab-btn[aria-selected="true"] {
      background: var(--cyan);
      color: #fff;
   }
   .ab-phppot .list-tab-btn .tab-count {
      display: inline-block;
      margin-left: 4px;
      font-size: 10px;
      opacity: 0.85;
   }
   .ab-phppot .list-panel.hidden { display: none; }

   .ab-phppot .page-top {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 20px;
   }
   .ab-phppot .page-top h1 {
      font-size: 18px;
      font-weight: 600;
      margin: 0;
      color: var(--text-dark);
   }
   .ab-phppot .page-top p { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
   .ab-phppot .right-col-stretch { display: flex; flex-direction: column; }
   .ab-phppot .right-col-stretch > .dash-card { flex: 1; display: flex; flex-direction: column; }
   .ab-phppot .right-col-stretch .card-body { flex: 1; display: flex; flex-direction: column; }
   .ab-phppot .right-col-stretch .chart-main { flex: 1; min-height: 350px; }
</style>
@endpush

@section('content')
@php
   $periodLabel = !empty($period['filter_date'])
      ? \Carbon\Carbon::parse($period['filter_date'])->format('d M Y')
      : '—';
   $scrapedLabel = !empty($period['scraped_at'])
      ? \Carbon\Carbon::parse($period['scraped_at'])->format('d M Y H:i')
      : null;
   $logTableAvailable = $logTableAvailable ?? false;
@endphp

<div class="ab-phppot -mt-1">

   <div class="page-top">
      <div>
         <h1>Monitoring Daily Banned</h1>
         <p>{{ $periodLabel }}@if($scrapedLabel) &bull; Scrape {{ $scrapedLabel }}@endif</p>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
         @if($scrTableAvailable)
         <form method="POST" action="{{ route('auto-banned.banned-monitoring.send-email') }}" class="inline-flex items-center gap-2">
            @csrf
            <input type="hidden" name="filter_date" value="{{ $filters['filter_date'] ?? '' }}">
            <label class="inline-flex items-center gap-1 text-xs text-[#888]">
               <input type="checkbox" name="force" value="1" class="rounded border-gray-300">
               Paksa kirim
            </label>
            <button type="submit" class="inline-flex items-center gap-1 rounded bg-[#01b0c6] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#019aab]">
               Kirim Email Notifikasi
            </button>
         </form>
         @endif
         @include('AutoBanned.partials.daily-filter-bar', [
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'filterRoute' => 'auto-banned.banned-monitoring.index',
         ])
      </div>
   </div>

   @if(!$logTableAvailable)
   <div class="dash-card card-body text-sm text-[#888] mb-3">Tabel <code>sid_banned_log</code> belum tersedia — total di-banned tidak dapat dihitung.</div>
   @endif

   @include('AutoBanned.partials.banned-monitoring-body', [
      'stats' => $stats,
      'chartData' => $chartData,
      'bannedRows' => $bannedRows,
      'logRows' => $logRows,
      'scrTableAvailable' => $scrTableAvailable,
      'compact' => false,
      'sparkId' => 'ab-banned-monitoring-spark',
      'pieId' => 'ab-banned-monitoring-pie',
   ])

</div>
@endsection
