@extends('OhsDashboard.layouts.app')

@section('content')
<section class="ohs-page" data-ohs-page="overview">
    <div class="ohs-page-head">
        <div>
            <h1>Overview Dashboard</h1>
            <p class="lead" id="dashboard-period">Ringkasan event, cuti, efektivitas kerja, dan status project/issue dalam satu pandangan.</p>
        </div>
    </div>
    <div class="ohs-toolbar">
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

    <article class="ohs-card">
        <div class="card-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
            <div>
                <h3>Event Status</h3>
                <p class="hint">This week dan upcoming event berdasarkan kelompok minggu</p>
            </div>
            <button type="button" class="ohs-overview-collapse-button" data-collapse-toggle data-collapse-target="event-status-content">−</button>
        </div>
        <div class="ohs-group-grid" id="event-status-content" data-collapse-target>
            @php
                $eventCards = [
                    ['key' => 'thisWeek', 'label' => 'This Week', 'tone' => 'green'],
                    ['key' => 'nextWeek', 'label' => 'Next Week', 'tone' => 'blue'],
                    ['key' => 'nextTwoWeek', 'label' => 'Next 2 Week', 'tone' => 'orange'],
                    ['key' => 'moreThanTwoWeeks', 'label' => 'More Than 2 Weeks Ahead', 'tone' => 'red'],
                ];
            @endphp
            @foreach ($eventCards as $card)
            <div class="ohs-group-card ohs-status-count-card status-{{ $card['tone'] }}" data-event-group="{{ $card['key'] }}">
                <div class="card-head">
                    <div class="ohs-status-card-copy">
                        <h3 class="ohs-status-card-title">{{ $card['label'] }}</h3>
                        <div class="ohs-status-card-period" data-period>{{ $card['key'] === 'moreThanTwoWeeks' ? 'Setelah akhir minggu kedua' : '' }}</div>
                    </div>
                    <div class="ohs-status-card-right">
                        <div class="ohs-status-card-count-box"><span class="ohs-status-card-count" data-count>0</span><span class="ohs-status-card-count-label">Events</span></div>
                        <button type="button" class="ohs-group-card-collapse-button" data-collapse-toggle data-collapse-target="event-group-{{ $card['key'] }}">−</button>
                    </div>
                </div>
                <div class="ohs-group-card-content" id="event-group-{{ $card['key'] }}" data-collapse-target>
                    <div class="ohs-list-sort-row"><label>Sort</label><select class="ohs-list-sort-select" data-sort-select><option value="dateAsc">Date: Earliest</option><option value="dateDesc">Date: Latest</option><option value="nameAsc">Name: A-Z</option></select></div>
                    <div class="ohs-item-list" data-item-list></div>
                </div>
            </div>
            @endforeach
        </div>
    </article>

    <article class="ohs-card">
        <div class="card-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
            <div>
                <h3>Leave Status &amp; Backup PIC</h3>
                <p class="hint">Status leave dan personel pengganti</p>
            </div>
            <button type="button" class="ohs-overview-collapse-button" data-collapse-toggle data-collapse-target="leave-status-content">−</button>
        </div>
        <div class="ohs-group-grid" id="leave-status-content" data-collapse-target>
            @php
                $leaveCards = [
                    ['key' => 'thisWeek', 'label' => 'Leave This Week', 'tone' => 'red', 'period' => 'Personel yang cutinya beririsan dengan minggu berjalan'],
                    ['key' => 'upcoming', 'label' => 'Upcoming Leave', 'tone' => 'orange', 'period' => 'Leave yang dimulai setelah minggu berjalan'],
                ];
            @endphp
            @foreach ($leaveCards as $card)
            <div class="ohs-group-card ohs-status-count-card status-{{ $card['tone'] }}" data-leave-group="{{ $card['key'] }}">
                <div class="card-head">
                    <div class="ohs-status-card-copy">
                        <h3 class="ohs-status-card-title">{{ $card['label'] }}</h3>
                        <div class="ohs-status-card-period">{{ $card['period'] }}</div>
                    </div>
                    <div class="ohs-status-card-right">
                        <div class="ohs-status-card-count-box"><span class="ohs-status-card-count" data-count>0</span><span class="ohs-status-card-count-label">People</span></div>
                        <button type="button" class="ohs-group-card-collapse-button" data-collapse-toggle data-collapse-target="leave-group-{{ $card['key'] }}">−</button>
                    </div>
                </div>
                <div class="ohs-group-card-content" id="leave-group-{{ $card['key'] }}" data-collapse-target>
                    <div class="ohs-list-sort-row"><label>Sort</label><select class="ohs-list-sort-select" data-sort-select><option value="dateAsc">Start Date: Earliest</option><option value="dateDesc">Start Date: Latest</option><option value="nameAsc">Employee: A-Z</option></select></div>
                    <div class="ohs-item-list" data-item-list></div>
                </div>
            </div>
            @endforeach
        </div>
    </article>

    <article class="ohs-card">
        <div class="card-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
            <div>
                <h3>Project &amp; Issue Tracker</h3>
                <p class="hint">Prioritas item On Going dan Overdue berdasarkan due date</p>
            </div>
            <div class="ohs-badges" id="overview-tracker-counts"></div>
        </div>
        <div id="overview-tracker-mount"></div>
    </article>
</section>

<button type="button" id="leaderboard-toggle" class="ohs-leaderboard-toggle" title="Leaderboard On Leave YTD">‹</button>
<div id="leaderboard-backdrop" class="ohs-leaderboard-backdrop hide"></div>
<aside id="leaderboard-sidebar" class="ohs-leaderboard-sidebar" aria-hidden="true">
    <div class="ohs-leaderboard-sidebar-head">
        <div>
            <h3>Leaderboard On Leave YTD</h3>
            <div class="hint" id="leaderboard-note"></div>
        </div>
        <button type="button" class="ohs-modal-close" id="leaderboard-close">✕</button>
    </div>
    <div class="ohs-leaderboard-sidebar-search">
        <label>Search Employee<input id="leaderboard-search" placeholder="Nama, posisi, team, atau site..."></label>
    </div>
    <div class="ohs-list-sort-row">
        <label>Sort</label>
        <select id="leaderboard-sort" class="ohs-list-sort-select">
            <option value="leaveDesc">Leave Days: Highest</option>
            <option value="effectiveDesc">Effective %: Highest</option>
            <option value="effectiveAsc">Effective %: Lowest</option>
            <option value="nameAsc">Employee: A-Z</option>
        </select>
    </div>
    <div id="leaderboard-list" class="ohs-leaderboard-sidebar-list"></div>
</aside>
@endsection
