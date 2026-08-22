@extends('OhsDashboard.layouts.app')

@section('content')
<section class="ohs-page" data-ohs-page="leave">
    <div class="ohs-page-head">
        <div>
            <h1>Leave & Integrated Calendar</h1>
            <p class="lead">Kelola cuti, handover acting PIC, dan lihat assignment event/project di kalender terpadu.</p>
        </div>
        <button type="button" class="btn-primary" id="btn-create-leave">+ Create Leave</button>
    </div>
    <div class="ohs-toolbar">
        <div class="ohs-filters">
            <label>Team <select id="cal-team"><option>All Teams</option></select></label>
            <label>Site <select id="cal-site"><option>All Sites</option></select></label>
            <label>Search <input id="cal-search" type="search" placeholder="Nama, NPK, assignment..."></label>
            <label>Year <select id="cal-year"></select></label>
            <div class="ohs-view-toggle">
                <button type="button" data-view="WEEK" class="is-active">Week</button>
                <button type="button" data-view="MONTH">Month</button>
                <button type="button" data-view="YEAR">Year</button>
            </div>
            <button type="button" id="cal-prev">Prev</button>
            <button type="button" id="cal-today">Today</button>
            <button type="button" id="cal-next">Next</button>
        </div>
    </div>
    <p class="ohs-legend">
        <span><i class="lg leave"></i> Leave</span>
        <span><i class="lg event"></i> Event</span>
        <span><i class="lg project"></i> Project</span>
        <span><i class="lg issue"></i> Issue</span>
        <span><i class="lg acting"></i> ACTING</span>
    </p>
    <div id="calendar-meta" class="ohs-muted"></div>
    <div id="calendar-grid" class="ohs-calendar"></div>
    <div id="leave-counts" class="ohs-badges"></div>
    <article class="ohs-card">
        <h3>Leave Requests</h3>
        <div class="ohs-table-wrap">
            <table class="ohs-table" id="leave-table">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
    </article>
</section>
@endsection
