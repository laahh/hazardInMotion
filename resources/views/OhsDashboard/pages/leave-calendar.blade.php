@extends('OhsDashboard.layouts.app')

@section('content')
<section class="ohs-page" data-ohs-page="leave">
    <div class="ohs-page-head">
        <h1>Leave & Integrated Calendar</h1>
        <button type="button" class="btn-primary" id="btn-create-leave">+ Create Leave Request</button>
    </div>
    <div class="ohs-filters">
        <label>Team <select id="cal-team"><option>All Teams</option></select></label>
        <label>Site <select id="cal-site"><option>All Sites</option></select></label>
        <label>Search <input id="cal-search" type="search" placeholder="Nama, NPK, assignment..."></label>
        <div class="ohs-view-toggle">
            <button type="button" data-view="WEEK" class="is-active">Week</button>
            <button type="button" data-view="MONTH">Month</button>
            <button type="button" data-view="YEAR">Year</button>
        </div>
        <button type="button" id="cal-prev">Prev</button>
        <button type="button" id="cal-today">Today</button>
        <button type="button" id="cal-next">Next</button>
    </div>
    <p class="ohs-legend">
        <span class="lg leave"></span> Leave
        <span class="lg event"></span> Event
        <span class="lg project"></span> Project
        <span class="lg issue"></span> Issue
        <span class="lg acting"></span> ACTING
    </p>
    <div id="calendar-meta" class="ohs-muted"></div>
    <div id="calendar-grid" class="ohs-calendar"></div>
</section>
@endsection
