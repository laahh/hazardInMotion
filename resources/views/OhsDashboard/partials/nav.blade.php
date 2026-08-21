<nav class="ohs-nav">
    <a class="{{ ($page ?? '') === 'overview' ? 'is-active' : '' }}" href="{{ route('ohs-dashboard.overview') }}">Overview</a>
    <a class="{{ ($page ?? '') === 'leave' ? 'is-active' : '' }}" href="{{ route('ohs-dashboard.leave') }}">Leave & Integrated Calendar</a>
    <a class="{{ ($page ?? '') === 'events' ? 'is-active' : '' }}" href="{{ route('ohs-dashboard.events') }}">Event Maker</a>
    <a class="{{ ($page ?? '') === 'tracker' ? 'is-active' : '' }}" href="{{ route('ohs-dashboard.tracker') }}">Project & Issue Tracker</a>
    <a class="{{ ($page ?? '') === 'admin' ? 'is-active' : '' }}" href="{{ route('ohs-dashboard.admin') }}">Admin Scheduler</a>
</nav>
