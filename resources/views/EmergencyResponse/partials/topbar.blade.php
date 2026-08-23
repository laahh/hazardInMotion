<div class="navbar-header">
    <div class="row align-items-center justify-content-between">
        <div class="col-auto">
            <div class="d-flex flex-wrap align-items-center gap-4">
                <button type="button" class="sidebar-toggle">
                    <iconify-icon icon="heroicons:bars-3-solid" class="icon text-2xl non-active"></iconify-icon>
                    <iconify-icon icon="iconoir:arrow-right" class="icon text-2xl active"></iconify-icon>
                </button>
                <button type="button" class="sidebar-mobile-toggle">
                    <iconify-icon icon="heroicons:bars-3-solid" class="icon"></iconify-icon>
                </button>
                <h6 class="fw-semibold mb-0 d-none d-md-block">@yield('page-title', 'Emergency Response & Safety Management System')</h6>
            </div>
        </div>
        <div class="col-auto">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <button type="button" data-theme-toggle class="w-40-px h-40-px bg-neutral-200 rounded-circle d-flex justify-content-center align-items-center">
                    <i class="ri-moon-line"></i>
                </button>

                <div class="dropdown">
                    <button class="has-indicator w-40-px h-40-px bg-neutral-200 rounded-circle d-flex justify-content-center align-items-center position-relative" type="button" data-bs-toggle="dropdown">
                        <i class="ri-notification-3-line text-primary-light text-xl"></i>
                        <span id="er-notification-badge" class="position-absolute top-0 end-0 badge rounded-pill bg-danger" style="display: none; font-size: 10px;"></span>
                    </button>
                    <div class="dropdown-menu to-top dropdown-menu-lg p-0">
                        <div class="m-16 py-12 px-16 radius-8 bg-primary-50 mb-16 d-flex align-items-center justify-content-between gap-2">
                            <h6 class="text-lg text-primary-light fw-semibold mb-0">Notifikasi</h6>
                        </div>
                        <div class="max-h-400-px overflow-y-auto scroll-sm pe-4 px-16" id="er-notification-list">
                            <p class="text-secondary-light text-sm text-center py-16 mb-0">Memuat...</p>
                        </div>
                        <div class="text-center py-12 px-16">
                            <a href="{{ \Illuminate\Support\Facades\Route::has('emergency-response.notification.index') ? route('emergency-response.notification.index') : '#' }}" class="text-primary-600 fw-semibold text-md">Lihat Semua Notifikasi</a>
                        </div>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="d-flex justify-content-center align-items-center rounded-circle" type="button" data-bs-toggle="dropdown">
                        <span class="w-40-px h-40-px bg-primary-100 text-primary-600 rounded-circle d-flex justify-content-center align-items-center fw-semibold">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        </span>
                    </button>
                    <div class="dropdown-menu to-top dropdown-menu-sm">
                        <div class="py-12 px-16 radius-8 bg-primary-50 mb-16 d-flex align-items-center justify-content-between gap-2">
                            <div>
                                <h6 class="text-lg text-primary-light fw-semibold mb-2">{{ auth()->user()->name ?? '-' }}</h6>
                                <span class="text-secondary-light fw-medium text-sm">{{ auth()->user()->roles->pluck('name')->join(', ') ?: '-' }}</span>
                            </div>
                        </div>
                        <ul class="to-top-list">
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-black px-0 py-8 hover-bg-transparent hover-text-danger d-flex align-items-center gap-3 border-0 bg-transparent w-100 text-start">
                                        <i class="ri-logout-box-line text-xl"></i> Keluar
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            var badge = document.getElementById('er-notification-badge');
            var list = document.getElementById('er-notification-list');
            var summaryUrl = '{{ route('emergency-response.api.notification.unread-summary') }}';

            function timeAgo(iso) {
                var diff = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
                if (diff < 60) return 'baru saja';
                if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
                if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
                return Math.floor(diff / 86400) + ' hari lalu';
            }

            function render(data) {
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                }

                if (!data.recent.length) {
                    list.innerHTML = '<p class="text-secondary-light text-sm text-center py-16 mb-0">Belum ada notifikasi.</p>';
                    return;
                }

                list.innerHTML = data.recent.map(function (n) {
                    var href = n.link_url || '#';
                    var unreadClass = n.is_read ? '' : 'bg-primary-50';
                    return '<a href="' + href + '" class="d-block px-0 py-12 border-bottom text-black ' + unreadClass + '">' +
                        '<h6 class="text-sm fw-semibold mb-4">' + n.title + '</h6>' +
                        '<p class="text-secondary-light text-sm mb-4 text-truncate-2">' + n.message + '</p>' +
                        '<span class="text-secondary-light text-xs">' + timeAgo(n.created_at) + '</span></a>';
                }).join('');
            }

            function poll() {
                fetch(summaryUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (res) { return res.ok ? res.json() : null; })
                    .then(function (data) { if (data) render(data); })
                    .catch(function () {});
            }

            poll();
            setInterval(poll, 20000);
        })();
    </script>
@endpush
