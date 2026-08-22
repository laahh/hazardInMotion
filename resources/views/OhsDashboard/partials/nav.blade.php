<nav class="ohs-nav">
    <a class="{{ ($page ?? '') === 'overview' ? 'is-active' : '' }}" href="{{ route('ohs-dashboard.overview') }}" title="Overview Dashboard">Overview</a>
    <a class="{{ ($page ?? '') === 'leave' ? 'is-active' : '' }}" href="{{ route('ohs-dashboard.leave') }}" title="Leave & Integrated Calendar">Leave & Integrated Calendar</a>
    <a class="{{ ($page ?? '') === 'events' ? 'is-active' : '' }}" href="{{ route('ohs-dashboard.events') }}" title="Event Maker">Event Maker</a>
    <a class="{{ ($page ?? '') === 'tracker' ? 'is-active' : '' }}" href="{{ route('ohs-dashboard.tracker') }}" title="Project & Issue Tracker">Project & Issue Tracker</a>
    <a class="{{ ($page ?? '') === 'admin' ? 'is-active' : '' }}" href="{{ route('ohs-dashboard.admin') }}" title="Admin Scheduler">Admin Scheduler</a>
</nav>
