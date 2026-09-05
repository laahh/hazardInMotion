<?php
$rr = function (string $name, array $params = []) {
    return \Illuminate\Support\Facades\Route::has($name) ? route($name, $params) : '#';
};
?>
<aside class="sidebar">
    <button type="button" class="sidebar-close-btn">
        <iconify-icon icon="radix-icons:cross-2"></iconify-icon>
    </button>
    <div>
        <a href="{{ $rr('control-room.dashboard') }}" class="sidebar-logo">
            <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="Berau Coal" style="max-height: 40px; width: auto;">
        </a>
    </div>
    <div class="sidebar-menu-area">
        <ul class="sidebar-menu" id="sidebar-menu">
            <li class="sidebar-menu-group-title">Control Room</li>
            <li>
                <a href="{{ $rr('control-room.dashboard') }}">
                    <i class="ri-dashboard-line menu-icon"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('control-room.schedule.index') }}">
                    <i class="ri-calendar-check-line menu-icon"></i>
                    <span>Jadwal Rencana</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('control-room.attendance.index') }}">
                    <i class="ri-user-follow-line menu-icon"></i>
                    <span>Absen</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('control-room.sap.index') }}">
                    <i class="ri-file-list-3-line menu-icon"></i>
                    <span>Data SAP</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('control-room.data-quality.index') }}">
                    <i class="ri-shield-check-line menu-icon"></i>
                    <span>Data Quality</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
