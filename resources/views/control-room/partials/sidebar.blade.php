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
                <a href="{{ $rr('control-room.dashboard') }}" class="{{ request()->routeIs('control-room.dashboard') ? 'active-page' : '' }}">
                    <i class="ri-dashboard-line menu-icon"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('control-room.schedule.index') }}" class="{{ request()->routeIs('control-room.schedule.*') ? 'active-page' : '' }}">
                    <i class="ri-calendar-check-line menu-icon"></i>
                    <span>Jadwal Rencana</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('control-room.qr-code.index') }}" class="{{ request()->routeIs('control-room.qr-code.*') ? 'active-page' : '' }}">
                    <i class="ri-qr-code-line menu-icon"></i>
                    <span>QR Code</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('control-room.attendance.form') }}" class="{{ request()->routeIs('control-room.attendance.form*') ? 'active-page' : '' }}">
                    <i class="ri-camera-line menu-icon"></i>
                    <span>Form Absensi</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('control-room.attendance.index') }}" class="{{ request()->routeIs('control-room.attendance.index') || request()->routeIs('control-room.attendance.show') ? 'active-page' : '' }}">
                    <i class="ri-user-follow-line menu-icon"></i>
                    <span>Rekap Absen</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('control-room.sap.index') }}" class="{{ request()->routeIs('control-room.sap.*') ? 'active-page' : '' }}">
                    <i class="ri-file-list-3-line menu-icon"></i>
                    <span>Data SAP</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('control-room.tbc-validations.index') }}" class="{{ request()->routeIs('control-room.tbc-validations.*') ? 'active-page' : '' }}">
                    <i class="ri-shield-star-line menu-icon"></i>
                    <span>Validasi TBC</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('control-room.data-quality.index') }}" class="{{ request()->routeIs('control-room.data-quality.*') ? 'active-page' : '' }}">
                    <i class="ri-shield-check-line menu-icon"></i>
                    <span>Data Quality</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('control-room.tutorial.index') }}" class="{{ request()->routeIs('control-room.tutorial.*') ? 'active-page' : '' }}">
                    <i class="ri-book-open-line menu-icon"></i>
                    <span>Panduan Indentifikasi Hazard</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
