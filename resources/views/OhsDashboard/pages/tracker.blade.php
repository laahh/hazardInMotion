@extends('OhsDashboard.layouts.app')

@section('content')
<section class="ohs-page" data-ohs-page="tracker">
    <div class="ohs-page-head">
        <h1>Project & Issue Tracker</h1>
        <button type="button" class="btn-primary" id="btn-create-tracker">+ Create Tracker</button>
    </div>
    <p class="ohs-muted">Jika ada Sub Task, progress parent = rata-rata Sub Task. Tanpa Sub Task, progress diupdate langsung pada parent.</p>
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
        <label>Search <input id="tr-search" type="search"></label>
        <button type="button" class="btn-primary" id="tr-refresh">Refresh</button>
    </div>
    <div id="tracker-counts" class="ohs-badges"></div>
    <article class="ohs-card">
        <div class="ohs-table-wrap">
            <table class="ohs-table" id="tracker-table">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
        <div id="tracker-pager" class="ohs-pager"></div>
    </article>
</section>
@endsection
