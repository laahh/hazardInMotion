@extends('OhsDashboard.layouts.app')

@section('content')
<section class="ohs-page" data-ohs-page="leave">
    <div class="ohs-page-head">
        <div>
            <h1>Leave & Integrated Calendar</h1>
            <p class="lead">Satu timeline per orang untuk Leave, Event, Project, dan Issue. Assignment Event, Project, dan Issue berpindah sementara ke Backup PIC selama periode leave.</p>
        </div>
        <button type="button" class="btn-primary" id="btn-create-leave">+ Create Leave Request</button>
    </div>
    <article class="ohs-card">
        <div class="ohs-toolbar">
            <div class="ohs-filters">
                <label>Team <select id="cal-team"><option>All Teams</option></select></label>
                <label>Site <select id="cal-site"><option>All Sites</option></select></label>
                <label>Search <input id="cal-search" type="search" placeholder="Employee, event, project, issue, PIC, site..."></label>
            </div>
            <div class="ohs-filters">
                <button type="button" class="btn-ghost" id="cal-prev">←</button>
                <button type="button" class="btn-ghost" id="cal-today">Today</button>
                <button type="button" class="btn-ghost" id="cal-next">→</button>
                <div id="cal-range-title" class="ohs-range-title"></div>
                <div class="ohs-view-toggle">
                    <button type="button" data-view="WEEK" class="is-active">Week</button>
                    <button type="button" data-view="MONTH">Month</button>
                    <button type="button" data-view="YEAR">Year</button>
                </div>
            </div>
        </div>
        <p class="ohs-legend">
            <span><i class="lg leave"></i> Leave</span>
            <span><i class="lg event"></i> Event</span>
            <span><i class="lg project"></i> Project</span>
            <span><i class="lg issue"></i> Issue</span>
            <span><span class="ohs-legend-acting">ACTING</span> Temporary Event / Project / Issue assignment to Backup PIC</span>
        </p>
        <div id="calendar-status" class="ohs-muted" style="padding:0 0 10px;">Ready</div>
        <div class="ohs-calendar-wrap">
            <div id="calendar-head" class="ohs-cal-head"></div>
            <div id="calendar-grid" class="ohs-cal-grid"></div>
        </div>
    </article>
    <article class="ohs-card">
        <h3>Leave Requests</h3>
        <div class="ohs-toolbar">
            <div class="ohs-filters">
                <label>Year <select id="cal-year"></select></label>
            </div>
        </div>
        <div id="leave-counts" class="ohs-badges"></div>
        <div class="ohs-table-wrap">
            <table class="ohs-table" id="leave-table">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
    </article>
</section>
@endsection
