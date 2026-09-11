@extends('control-room.layouts.app')

@section('page-title', 'Jadwal Rencana')

@php
    $previousWeek = now()->subWeek();
@endphp

@section('content')
    <div class="card shadow-none border mb-24">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <form method="GET" id="site-filter-form" action="{{ route('control-room.schedule.index') }}">
                        <label class="form-label text-sm mb-1" for="ocr-page-site">Site kalender</label>
                        <select name="site" id="ocr-page-site" class="form-control" onchange="this.form.submit()">
                            @foreach ($sites as $siteOption)
                                <option value="{{ $siteOption->value }}" @selected($site === $siteOption)>{{ $siteOption->label() }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="col-md-8 text-md-end">
                    <a href="{{ route('control-room.schedule.changes') }}" class="text-primary-600 text-sm">Riwayat perubahan &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-none border mb-24" id="ocr-schedule-upload">
        <div class="card-header">
            <h6 class="mb-0">Upload Excel per Minggu</h6>
            <p class="text-secondary-light text-xs mb-0">Pilih site di form unduh, lalu unduh template minggu itu (sheet Jadwal + daftar SID Personil). Isi kolom kuning <strong>sid</strong>; kolom <strong>site</strong> punya dropdown semua site. Unggah ke site yang sama.</p>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-6">
                    <form method="GET" action="{{ route('control-room.schedule.excel-template') }}" class="row g-2 align-items-end">
                        <div class="col-12 col-sm-4">
                            <label class="form-label text-sm mb-1" for="ocr-template-site">Site</label>
                            <select name="site" id="ocr-template-site" class="form-control form-control-sm" required>
                                @foreach ($sites as $siteOption)
                                    <option value="{{ $siteOption->value }}" @selected($site === $siteOption)>{{ $siteOption->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-sm-3">
                            <label class="form-label text-sm mb-1">Tahun</label>
                            <input type="number" name="year" class="form-control form-control-sm" value="{{ now()->isoWeekYear() }}" min="2020" max="2100" required>
                        </div>
                        <div class="col-6 col-sm-3">
                            <label class="form-label text-sm mb-1">Minggu ISO</label>
                            <input type="number" name="week_number" class="form-control form-control-sm" min="1" max="53" value="{{ now()->isoWeek() }}" required>
                        </div>
                        <div class="col-12 col-sm-2">
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Unduh Template</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-6">
                    <form method="POST" action="{{ route('control-room.schedule.excel-import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="site_code" value="{{ $site->value }}">
                        <div class="col-3">
                            <label class="form-label text-sm mb-1">Tahun</label>
                            <input type="number" name="year" class="form-control form-control-sm" value="{{ old('year', now()->isoWeekYear()) }}" min="2020" max="2100" required>
                        </div>
                        <div class="col-3">
                            <label class="form-label text-sm mb-1">Minggu ISO</label>
                            <input type="number" name="week_number" class="form-control form-control-sm" min="1" max="53" value="{{ old('week_number', now()->isoWeek()) }}" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label text-sm mb-1">File .xlsx</label>
                            <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" required>
                        </div>
                        <div class="col-2">
                            <button type="submit" class="btn btn-primary-600 btn-sm w-100">Unggah</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-none border mb-24">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h6 class="mb-0">Kalender Jadwal — {{ $site->label() }}</h6>
                <p class="text-secondary-light text-xs mb-0">Hanya menampilkan jadwal site <strong>{{ $site->value }}</strong>. Tanggal kosong tidak bisa diisi satuan — unggah template satu minggu (Minggu–Sabtu).</p>
            </div>
            <div class="d-flex flex-wrap align-items-end gap-2">
                <div style="min-width: 180px;">
                    <label class="form-label text-sm mb-1" for="ocr-calendar-site">Filter site</label>
                    <select id="ocr-calendar-site" class="form-control form-control-sm" aria-label="Filter kalender berdasarkan site">
                        @foreach ($sites as $siteOption)
                            <option value="{{ $siteOption->value }}" @selected($site === $siteOption)>{{ $siteOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="btn-group">
                    <a
                        id="ocr-share-week"
                        class="btn btn-primary-600 btn-sm"
                        href="{{ route('control-room.schedule.share', ['site' => $site->value, 'year' => $shareYear, 'week' => $shareWeek, 'scope' => 'site']) }}"
                    >
                        <i class="ri-share-forward-line"></i> Bagikan minggu
                    </a>
                    <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="visually-hidden">Opsi bagikan</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" id="ocr-share-week-site" href="{{ route('control-room.schedule.share', ['site' => $site->value, 'year' => $shareYear, 'week' => $shareWeek, 'scope' => 'site']) }}">Poster site ini</a>
                        </li>
                        <li>
                            <a class="dropdown-item" id="ocr-share-week-all" href="{{ route('control-room.schedule.share', ['site' => $site->value, 'year' => $shareYear, 'week' => $shareWeek, 'scope' => 'all']) }}">Poster semua site</a>
                        </li>
                        <li>
                            <a class="dropdown-item" id="ocr-share-week-xlsx" href="{{ route('control-room.schedule.share.excel', ['site' => $site->value, 'year' => $shareYear, 'week' => $shareWeek, 'scope' => 'site']) }}">Unduh Excel site ini</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div id="schedule-calendar"></div>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">Alat Minggu (Salin, Kunci, Hapus)</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('control-room.schedule.copy') }}" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="site_code" value="{{ $site->value }}">
                        <div class="col-4">
                            <label class="form-label text-sm mb-1">Dari (Tahun/Minggu)</label>
                            <div class="d-flex gap-1">
                                <input type="number" name="from_year" class="form-control form-control-sm" value="{{ now()->isoWeekYear() }}" required>
                                <input type="number" name="from_week_number" class="form-control form-control-sm" min="1" max="53" value="{{ now()->isoWeek() }}" required>
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label text-sm mb-1">Ke (Tahun/Minggu)</label>
                            <div class="d-flex gap-1">
                                <input type="number" name="to_year" class="form-control form-control-sm" value="{{ now()->isoWeekYear() }}" required>
                                <input type="number" name="to_week_number" class="form-control form-control-sm" min="1" max="53" required>
                            </div>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">Salin Minggu</button>
                        </div>
                    </form>
                </div>
                <div class="col-md-6">
                    <form method="POST" action="{{ route('control-room.schedule.lock') }}" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="site_code" value="{{ $site->value }}">
                        <div class="col-4">
                            <label class="form-label text-sm mb-1">Tahun</label>
                            <input type="number" name="year" class="form-control form-control-sm" value="{{ now()->isoWeekYear() }}" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label text-sm mb-1">Minggu</label>
                            <input type="number" name="week_number" class="form-control form-control-sm" min="1" max="53" value="{{ now()->isoWeek() }}" required>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-warning-600 btn-sm w-100" onclick="return confirm('Kunci minggu ini sebagai baseline?');">Kunci Minggu</button>
                        </div>
                    </form>
                </div>
                <div class="col-12">
                    <form method="POST" action="{{ route('control-room.schedule.destroy-week') }}" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="site_code" value="{{ $site->value }}">
                        <div class="col-md-3">
                            <label class="form-label text-sm mb-1">Tahun</label>
                            <input type="number" name="year" class="form-control form-control-sm" value="{{ $previousWeek->isoWeekYear() }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-sm mb-1">Minggu</label>
                            <input type="number" name="week_number" class="form-control form-control-sm" min="1" max="53" value="{{ $previousWeek->isoWeek() }}" required>
                        </div>
                        <div class="col-md-3">
                            <button
                                type="submit"
                                class="btn btn-outline-danger btn-sm w-100"
                                onclick="return confirm('Hapus SEMUA jadwal site ini di minggu tersebut? Termasuk yang sudah dikunci dan minggu yang sudah lewat. Absen tidak ikut terhapus. Tidak bisa dibatalkan.');"
                            >
                                Hapus Minggu
                            </button>
                        </div>
                        <div class="col-md-3">
                            <p class="text-secondary-light text-xs mb-0">Default: minggu lalu. Berlaku untuk site yang sedang dipilih.</p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <datalist id="personnel-options">
        @foreach ($personnel as $person)
            <option value="{{ $person->emp_name }} · {{ $person->site_dedicated }} ({{ $person->sid }})"></option>
        @endforeach
    </datalist>

    <div class="modal fade" id="addScheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('control-room.schedule.bulk') }}">
                    @csrf
                    <input type="hidden" name="site_code" value="{{ $site->value }}">
                    <input type="hidden" name="assignments[0][date]" id="modal-date-input">

                    <div class="modal-header">
                        <h6 class="modal-title">Tambah Jadwal — <span id="modal-date-label"></span></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-16">
                            <label class="form-label text-sm mb-1">Shift</label>
                            <select name="assignments[0][shift_code]" class="form-control" required>
                                <option value="S1">Shift 1</option>
                                <option value="S2">Shift 2</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label text-sm mb-1">Personil (ketik nama atau SID)</label>
                            <input
                                type="text"
                                name="assignments[0][personnel_source_key]"
                                list="personnel-options"
                                class="form-control"
                                placeholder="Ketik nama atau SID..."
                                autocomplete="off"
                                required
                            >
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary-600">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="edit-schedule-form">
                    @csrf
                    @method('PUT')

                    <div class="modal-header">
                        <h6 class="modal-title">Ubah Jadwal — <span id="edit-modal-date-label"></span></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert py-8 px-12 mb-16 text-xs" id="edit-form-alert" style="display:none" role="alert"></div>
                        <p class="text-xs text-secondary-light mb-16">
                            Saat ini: <strong id="edit-current-person">—</strong>
                        </p>
                        <div class="mb-16">
                            <label class="form-label text-sm mb-1">Shift</label>
                            <select name="shift_code" id="edit-shift-select" class="form-control" required>
                                <option value="S1">Shift 1</option>
                                <option value="S2">Shift 2</option>
                            </select>
                        </div>
                        <div class="mb-16">
                            <label class="form-label text-sm mb-1">Personil baru (ketik nama atau SID)</label>
                            <input
                                type="text"
                                name="personnel_source_key"
                                id="edit-personnel-input"
                                list="personnel-options"
                                class="form-control"
                                autocomplete="off"
                                required
                            >
                        </div>
                        <p class="text-warning-600 text-xs mb-8" id="edit-locked-note" style="display:none">
                            <i class="ri-lock-line"></i> Jadwal ini sudah dikunci sebagai baseline — alasan perubahan wajib diisi.
                        </p>
                        <div class="mb-16" id="edit-reason-wrapper">
                            <label class="form-label text-sm mb-1">
                                Alasan perubahan
                                <span id="edit-reason-required-mark" class="text-danger-600" style="display:none">*</span>
                            </label>
                            <textarea name="reason" id="edit-reason-input" class="form-control" rows="2" placeholder="Contoh: cuti, sakit, ganti shift. Wajib jika jadwal terkunci."></textarea>
                        </div>
                        <div class="border-top pt-16">
                            <div class="d-flex align-items-center justify-content-between mb-8">
                                <label class="form-label text-sm mb-0">Riwayat ganti personil</label>
                                <span class="text-xs text-secondary-light" id="edit-history-count"></span>
                            </div>
                            <p class="text-xs text-secondary-light mb-8">Sebelumnya siapa menjadi siapa pada tanggal &amp; shift ini.</p>
                            <div id="edit-history-list" class="ocr-sched-history"></div>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-danger" id="edit-delete-btn">Hapus Jadwal</button>
                        <div>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary-600" id="edit-save-btn">Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link href="{{ asset('build/plugins/fullcalendar/css/main.min.css') }}" rel="stylesheet">
    <style>
        /* WowDash memaksa .fc-event ke primary-50 + FullCalendar memakai textColor putih.
           Override scoped: chip pastel, teks gelap supaya nama terbaca. */
        #schedule-calendar .ocr-sched-event--S1,
        #schedule-calendar .ocr-sched-event--S1 .fc-event-main,
        #schedule-calendar .ocr-sched-event--S1 .fc-event-title,
        #schedule-calendar .ocr-sched-event--S1 .ocr-sched-title {
            background-color: #dbeafe !important;
            border-color: #93c5fd !important;
            color: #1e3a8a !important;
        }
        #schedule-calendar .ocr-sched-event--S2,
        #schedule-calendar .ocr-sched-event--S2 .fc-event-main,
        #schedule-calendar .ocr-sched-event--S2 .fc-event-title,
        #schedule-calendar .ocr-sched-event--S2 .ocr-sched-title {
            background-color: #ffedd5 !important;
            border-color: #fdba74 !important;
            color: #9a3412 !important;
        }
        #schedule-calendar .ocr-sched-event .ocr-sched-title {
            font-weight: 600;
        }
        #schedule-calendar .ocr-sched-event .fc-event-main {
            background-color: transparent !important;
        }
        #schedule-calendar tr.ocr-sched-event--S1,
        #schedule-calendar tr.ocr-sched-event--S1 a {
            color: #1e3a8a !important;
        }
        #schedule-calendar tr.ocr-sched-event--S2,
        #schedule-calendar tr.ocr-sched-event--S2 a {
            color: #9a3412 !important;
        }
        #schedule-calendar .ocr-sched-event--changed {
            box-shadow: inset 3px 0 0 #2563eb;
        }
        #schedule-calendar td.ocr-sched-day--empty {
            cursor: default;
            background: #fafafa;
        }
        #schedule-calendar td.ocr-sched-day--empty:hover {
            background: #f3f4f6;
        }
        #schedule-calendar td.ocr-sched-day--filled {
            cursor: pointer;
        }
        .ocr-sched-history-item {
            border-left: 3px solid #93c5fd;
            padding: 8px 12px;
            margin-bottom: 8px;
            background: #f8fafc;
            border-radius: 0 6px 6px 0;
        }
        .ocr-sched-history-arrow {
            font-weight: 600;
            color: #1e3a8a;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('build/plugins/fullcalendar/js/main.min.js') }}"></script>
    <script>
        (function () {
            var siteCode = @json($site->value);
            var eventsUrl = @json(route('control-room.schedule.events'));
            var csrfToken = @json(csrf_token());
            var scheduleIndexUrl = @json(route('control-room.schedule.index'));
            var shareUrl = @json(route('control-room.schedule.share'));
            var shareExcelUrl = @json(route('control-room.schedule.share.excel'));

            var editModalEl = document.getElementById('editScheduleModal');
            var editModal = new bootstrap.Modal(editModalEl);
            var editForm = document.getElementById('edit-schedule-form');
            var editShiftSelect = document.getElementById('edit-shift-select');
            var editPersonnelInput = document.getElementById('edit-personnel-input');
            var editReasonInput = document.getElementById('edit-reason-input');
            var editReasonRequiredMark = document.getElementById('edit-reason-required-mark');
            var editLockedNote = document.getElementById('edit-locked-note');
            var editDeleteBtn = document.getElementById('edit-delete-btn');
            var editSaveBtn = document.getElementById('edit-save-btn');
            var editModalDateLabel = document.getElementById('edit-modal-date-label');
            var editCurrentPerson = document.getElementById('edit-current-person');
            var editHistoryList = document.getElementById('edit-history-list');
            var editHistoryCount = document.getElementById('edit-history-count');
            var editFormAlert = document.getElementById('edit-form-alert');
            var currentDeleteUrl = null;
            var currentChangesUrl = null;

            function isoWeekFromMonday(date) {
                var d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
                var dayNum = d.getUTCDay() || 7;
                d.setUTCDate(d.getUTCDate() + 4 - dayNum);
                var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
                var week = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
                return { year: d.getUTCFullYear(), week: week };
            }

            function controlRoomWeek(date) {
                var local = new Date(date.getFullYear(), date.getMonth(), date.getDate());
                var sunday = new Date(local);
                sunday.setDate(local.getDate() - local.getDay());
                var monday = new Date(sunday);
                monday.setDate(sunday.getDate() + 1);
                return isoWeekFromMonday(monday);
            }

            function shareHref(base, week, scope) {
                return base + '?site=' + encodeURIComponent(siteCode)
                    + '&year=' + week.year
                    + '&week=' + week.week
                    + '&scope=' + scope;
            }

            function syncShareLinks(date) {
                var week = controlRoomWeek(date);
                var siteLink = shareHref(shareUrl, week, 'site');
                var allLink = shareHref(shareUrl, week, 'all');
                var xlsxLink = shareHref(shareExcelUrl, week, 'site');
                var weekBtn = document.getElementById('ocr-share-week');
                var siteBtn = document.getElementById('ocr-share-week-site');
                var allBtn = document.getElementById('ocr-share-week-all');
                var xlsxBtn = document.getElementById('ocr-share-week-xlsx');
                if (weekBtn) {
                    weekBtn.href = siteLink;
                }
                if (siteBtn) {
                    siteBtn.href = siteLink;
                }
                if (allBtn) {
                    allBtn.href = allLink;
                }
                if (xlsxBtn) {
                    xlsxBtn.href = xlsxLink;
                }
            }

            function dateHasEvents(cal, dateStr) {
                return cal.getEvents().some(function (event) {
                    return (event.startStr || '').slice(0, 10) === dateStr;
                });
            }

            function markScheduledDays(cal) {
                var dates = {};
                cal.getEvents().forEach(function (event) {
                    var key = (event.startStr || '').slice(0, 10);
                    if (key) {
                        dates[key] = true;
                    }
                });
                cal.el.querySelectorAll('td[data-date]').forEach(function (cell) {
                    var date = cell.getAttribute('data-date');
                    var filled = !!dates[date];
                    cell.classList.toggle('ocr-sched-day--empty', !filled);
                    cell.classList.toggle('ocr-sched-day--filled', filled);
                });
            }

            function remindWeeklyInput() {
                window.alert('Tanggal ini belum ada jadwal. Input harus sekaligus untuk satu minggu (Minggu–Sabtu) lewat unggah template Excel, bukan per tanggal.');
                var upload = document.getElementById('ocr-schedule-upload');
                if (upload) {
                    upload.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function showEditAlert(type, message) {
                editFormAlert.className = 'alert py-8 px-12 mb-16 text-xs alert-' + type;
                editFormAlert.textContent = message;
                editFormAlert.style.display = '';
            }

            function hideEditAlert() {
                editFormAlert.style.display = 'none';
                editFormAlert.textContent = '';
            }

            function firstErrorMessage(data) {
                if (data && data.errors) {
                    var keys = Object.keys(data.errors);
                    if (keys.length && data.errors[keys[0]] && data.errors[keys[0]][0]) {
                        return data.errors[keys[0]][0];
                    }
                }
                return (data && data.message) || 'Gagal menyimpan perubahan.';
            }

            function renderHistory(payload) {
                var items = (payload && payload.history) || [];
                if (payload && payload.current) {
                    editCurrentPerson.textContent = payload.current;
                }
                editHistoryCount.textContent = items.length ? items.length + ' perubahan' : '';
                if (!items.length) {
                    editHistoryList.innerHTML = '<p class="text-xs text-secondary-light mb-0">Belum ada pergantian tercatat.</p>';
                    return;
                }

                editHistoryList.innerHTML = items.map(function (item) {
                    return (
                        '<div class="ocr-sched-history-item">' +
                        '<div class="ocr-sched-history-arrow text-sm">' + escapeHtml(item.summary) + '</div>' +
                        '<div class="text-xs text-secondary-light mt-4">' +
                        escapeHtml(item.at) + ' · ' + escapeHtml(item.by) +
                        (item.reason ? ' · ' + escapeHtml(item.reason) : '') +
                        '</div>' +
                        '</div>'
                    );
                }).join('');
            }

            function loadHistory(url) {
                if (!url) {
                    renderHistory({ history: [] });
                    return;
                }
                editHistoryList.innerHTML = '<p class="text-xs text-secondary-light mb-0">Memuat riwayat…</p>';
                fetch(url, { headers: { Accept: 'application/json' } })
                    .then(function (res) { return res.json(); })
                    .then(renderHistory)
                    .catch(function () {
                        editHistoryList.innerHTML = '<p class="text-xs text-danger-600 mb-0">Gagal memuat riwayat.</p>';
                    });
            }

            function deleteCurrentSchedule() {
                if (!currentDeleteUrl) {
                    return;
                }
                if (!confirm('Hapus jadwal ini?')) {
                    return;
                }

                fetch(currentDeleteUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: '_method=DELETE',
                }).then(function (res) {
                    if (res.ok) {
                        editModal.hide();
                        calendar.refetchEvents();
                        return;
                    }

                    calendar.refetchEvents();
                    res.json().then(function (data) {
                        alert(data.message || 'Gagal menghapus jadwal.');
                    }).catch(function () {
                        alert('Jadwal tidak ditemukan. Mungkin sudah dihapus.');
                    });
                });
            }
            editDeleteBtn.addEventListener('click', deleteCurrentSchedule);

            editForm.addEventListener('submit', function (e) {
                e.preventDefault();
                hideEditAlert();
                editSaveBtn.disabled = true;

                var body = new FormData(editForm);

                fetch(editForm.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body,
                }).then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, status: res.status, data: data };
                    }).catch(function () {
                        return { ok: res.ok, status: res.status, data: {} };
                    });
                }).then(function (result) {
                    editSaveBtn.disabled = false;
                    if (result.ok) {
                        renderHistory(result.data);
                        if (result.data.personnelSourceKey && result.data.current) {
                            editPersonnelInput.value = result.data.current;
                        }
                        if (result.data.shift) {
                            editShiftSelect.value = result.data.shift;
                        }
                        editReasonInput.value = '';
                        showEditAlert('success', result.data.message || 'Jadwal diperbarui.');
                        calendar.refetchEvents();
                        return;
                    }

                    showEditAlert('danger', firstErrorMessage(result.data));
                    if (result.status === 404) {
                        calendar.refetchEvents();
                    }
                }).catch(function () {
                    editSaveBtn.disabled = false;
                    showEditAlert('danger', 'Gagal menyimpan perubahan.');
                });
            });

            var calendarEl = document.getElementById('schedule-calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,listMonth',
                },
                initialView: 'dayGridMonth',
                locale: 'id',
                firstDay: 1,
                height: 'auto',
                dayMaxEvents: true,
                eventDisplay: 'block',
                datesSet: function (info) {
                    markScheduledDays(info.view.calendar);
                    syncShareLinks(info.view.calendar.getDate());
                },
                eventsSet: function () {
                    if (calendar) {
                        markScheduledDays(calendar);
                    }
                },

                events: function (info, successCallback, failureCallback) {
                    var url = eventsUrl + '?site=' + encodeURIComponent(siteCode)
                        + '&start=' + encodeURIComponent(info.startStr)
                        + '&end=' + encodeURIComponent(info.endStr);

                    fetch(url, { headers: { Accept: 'application/json' } })
                        .then(function (res) { return res.json(); })
                        .then(successCallback)
                        .catch(failureCallback);
                },

                eventContent: function (arg) {
                    var props = arg.event.extendedProps;
                    var initial = (props.personnel || '?').trim().charAt(0).toUpperCase();
                    var accent = props.accent || arg.event.textColor || '#111827';
                    var title = arg.event.title.replace(/</g, '&lt;');
                    var changedMark = props.changesCount
                        ? '<i class="ri-history-line flex-shrink-0" title="Ada riwayat ganti personil" style="font-size:11px;"></i>'
                        : '';

                    return {
                        html:
                            '<div class="d-flex align-items-center gap-1 px-4 py-2" style="overflow:hidden;color:inherit;">' +
                            '<span class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center flex-shrink-0" ' +
                            'style="width:16px;height:16px;font-size:9px;font-weight:700;color:' + accent + ';">' + initial + '</span>' +
                            '<span class="text-truncate ocr-sched-title" style="font-size:11px;color:' + accent + ';font-weight:600;">' + title + '</span>' +
                            changedMark +
                            '</div>',
                    };
                },

                dateClick: function (info) {
                    if (!dateHasEvents(info.view.calendar, info.dateStr)) {
                        remindWeeklyInput();
                        return;
                    }

                    document.getElementById('modal-date-input').value = info.dateStr;
                    document.getElementById('modal-date-label').textContent = info.dateStr;
                    new bootstrap.Modal(document.getElementById('addScheduleModal')).show();
                },

                eventClick: function (info) {
                    var props = info.event.extendedProps;
                    var currentLabel = props.personnel + ' (' + props.personnelSourceKey + ')';

                    hideEditAlert();
                    editForm.action = props.updateUrl;
                    editModalDateLabel.textContent = info.event.startStr;
                    editShiftSelect.value = props.shift;
                    editPersonnelInput.value = currentLabel;
                    editCurrentPerson.textContent = currentLabel;
                    editReasonInput.value = '';
                    editReasonInput.required = !!props.locked;
                    editReasonRequiredMark.style.display = props.locked ? '' : 'none';
                    editLockedNote.style.display = props.locked ? '' : 'none';
                    editDeleteBtn.style.display = props.locked ? 'none' : '';
                    currentDeleteUrl = props.deleteUrl;
                    currentChangesUrl = props.changesUrl;
                    loadHistory(currentChangesUrl);

                    editModal.show();
                },
            });

            calendar.render();
            syncShareLinks(calendar.getDate());

            var calendarSite = document.getElementById('ocr-calendar-site');
            if (calendarSite) {
                calendarSite.addEventListener('change', function () {
                    var next = calendarSite.value;
                    if (!next || next === siteCode) {
                        return;
                    }
                    var url = new URL(scheduleIndexUrl, window.location.origin);
                    url.searchParams.set('site', next);
                    window.location.assign(url.toString());
                });
            }
        })();
    </script>
@endpush
