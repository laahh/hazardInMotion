@extends('OhsDashboard.layouts.checkin')

@section('content')
<main class="ohs-checkin" data-event-id="{{ $eventId }}">
    <header class="ohs-checkin-header">
        <span class="ohs-mark">OHS</span>
        <h1>Absensi Event</h1>
        <p id="checkin-event-name">Memuat event...</p>
    </header>
    <section class="ohs-checkin-card">
        <p id="checkin-meta" class="ohs-muted"></p>
        <label>Cari nama / NPK
            <input id="checkin-q" type="search" placeholder="Ketik minimal 2 karakter" autocomplete="off">
        </label>
        <ul id="checkin-results" class="ohs-search-list"></ul>
        <div id="checkin-message"></div>
    </section>
</main>
@endsection
