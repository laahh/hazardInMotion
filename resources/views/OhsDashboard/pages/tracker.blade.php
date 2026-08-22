@extends('OhsDashboard.layouts.app')

@section('content')
<section class="ohs-page" data-ohs-page="tracker">
    <div class="ohs-page-head">
        <div>
            <h1>Project & Issue Tracker</h1>
            <p class="lead">Jika ada sub task, progress parent = rata-rata sub task. Tanpa sub task, progress diupdate langsung pada parent.</p>
        </div>
        <button type="button" class="btn-primary" id="btn-create-tracker">+ Create Tracker</button>
    </div>
    <div class="ohs-toolbar">
        <div class="ohs-filters">
            <label>Type
                <select id="tr-type">
                    <option>All Types</option>
                    <option>Project</option>
                    <option>Issue</option>
                </select>
            </label>
            <label>Status
                <select id="tr-status">
                    <option>All Status</option>
                    <option>On Going</option>
                    <option>Overdue</option>
                    <option>Closed</option>
                </select>
            </label>
            <label>Department <select id="tr-dept"><option>All Departments</option></select></label>
            <label>Site <select id="tr-site"><option>All Sites</option></select></label>
            <label>Search <input id="tr-search" type="search" placeholder="Nama, PIC, site..."></label>
            <button type="button" class="btn-primary" id="tr-refresh">Refresh</button>
        </div>
    </div>
    <div id="tracker-counts" class="ohs-badges"></div>
    <p class="hint" style="margin:-6px 0 12px;">Klik header kolom untuk sort, gunakan kotak pencarian per kolom untuk filter, dan tombol Expand untuk membuka detail sub task &amp; update log.</p>
    <article class="ohs-card">
        <div id="tracker-table-mount"></div>
    </article>
</section>
@endsection
