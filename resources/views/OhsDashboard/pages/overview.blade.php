@extends('OhsDashboard.layouts.app')

@section('content')
<section class="ohs-page" data-ohs-page="overview">
    <div class="ohs-page-head">
        <h1>Overview Dashboard</h1>
        <div class="ohs-filters" id="overview-filters">
            <label>Team
                <select id="filter-team"><option>All Teams</option></select>
            </label>
            <label>Site
                <select id="filter-site"><option>All Sites</option></select>
            </label>
            <label>Year
                <select id="filter-year"></select>
            </label>
            <button type="button" class="btn-primary" id="btn-refresh">Refresh</button>
        </div>
    </div>
    <div id="overview-kpis" class="ohs-kpis"></div>
    <div class="ohs-grid-2">
        <article class="ohs-card" id="overview-events"></article>
        <article class="ohs-card" id="overview-leave"></article>
    </div>
    <article class="ohs-card" id="overview-effectiveness"></article>
    <article class="ohs-card" id="overview-leaderboard"></article>
    <article class="ohs-card" id="overview-trackers"></article>
</section>
@endsection
