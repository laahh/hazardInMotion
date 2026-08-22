@extends('OhsDashboard.layouts.app')

@section('content')
<section class="ohs-page" data-ohs-page="events">
    <div class="ohs-page-head">
        <div>
            <h1>Event Maker</h1>
            <p class="lead">Buat event, pantau kesiapan, bagikan QR absensi, dan catat notulensi plus action item.</p>
        </div>
        <button type="button" class="btn-primary" id="btn-create-event">+ Create Event</button>
    </div>
    <div class="ohs-toolbar">
        <div class="ohs-filters">
            <label>Team PIC <select id="ev-team"><option>All Teams</option></select></label>
            <label>Site PIC <select id="ev-site"><option>All Sites</option></select></label>
        </div>
    </div>
    <div id="event-badges" class="ohs-badges"></div>
    <article class="ohs-card">
        <div class="ohs-table-wrap">
            <table class="ohs-table" id="event-table">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
    </article>
</section>
@endsection
