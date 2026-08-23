@php
    $rr = function (string $name, array $params = []) {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name, $params) : '#';
    };
@endphp
<aside class="sidebar">
    <button type="button" class="sidebar-close-btn">
        <iconify-icon icon="radix-icons:cross-2"></iconify-icon>
    </button>
    <div>
        <a href="{{ $rr('emergency-response.dashboard') }}" class="sidebar-logo">
            <img src="https://besentry-dev.beraucoal.co.id/build/images/logo-removebg.png" alt="Berau Coal" style="max-height: 40px; width: auto;">
        </a>
    </div>
    <div class="sidebar-menu-area">
        <ul class="sidebar-menu" id="sidebar-menu">
            <li class="sidebar-menu-group-title">Emergency Response &amp; Safety</li>
            <li>
                <a href="{{ $rr('emergency-response.dashboard') }}">
                    <i class="ri-dashboard-line menu-icon"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('emergency-response.equipment.index') }}">
                    <i class="ri-fire-line menu-icon"></i>
                    <span>Emergency Equipment</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('emergency-response.safety-device.index') }}">
                    <i class="ri-shield-check-line menu-icon"></i>
                    <span>Safety Device</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('emergency-response.inspection.index') }}">
                    <i class="ri-clipboard-line menu-icon"></i>
                    <span>Inspeksi</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('emergency-response.incident.index') }}">
                    <i class="ri-alarm-warning-line menu-icon"></i>
                    <span>Incident Reporting</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('emergency-response.response.index') }}">
                    <i class="ri-truck-line menu-icon"></i>
                    <span>Emergency Response</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('emergency-response.maintenance.index') }}">
                    <i class="ri-tools-line menu-icon"></i>
                    <span>Maintenance</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('emergency-response.work-order.index') }}">
                    <i class="ri-file-list-3-line menu-icon"></i>
                    <span>Work Order</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('emergency-response.notification.index') }}">
                    <i class="ri-notification-3-line menu-icon"></i>
                    <span>Notifikasi &amp; Alert</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('emergency-response.manpower.index') }}">
                    <i class="ri-team-line menu-icon"></i>
                    <span>Manpower</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('emergency-response.report.index') }}">
                    <i class="ri-bar-chart-2-line menu-icon"></i>
                    <span>Laporan</span>
                </a>
            </li>

            <li class="sidebar-menu-group-title">Administrasi</li>
            <li>
                <a href="{{ $rr('emergency-response.master-data.index') }}">
                    <i class="ri-database-2-line menu-icon"></i>
                    <span>Master Data</span>
                </a>
            </li>
            {{-- <li>
                <a href="{{ $rr('role-permission.index') }}">
                    <i class="ri-shield-user-line menu-icon"></i>
                    <span>User &amp; Role</span>
                </a>
            </li> --}}
            <li>
                <a href="{{ $rr('emergency-response.audit-log.index') }}">
                    <i class="ri-history-line menu-icon"></i>
                    <span>Audit Log</span>
                </a>
            </li>
            <li>
                <a href="{{ $rr('emergency-response.settings.index') }}">
                    <i class="ri-settings-3-line menu-icon"></i>
                    <span>Pengaturan</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
