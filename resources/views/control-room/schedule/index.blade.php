@extends('control-room.layouts.app')

@section('page-title', 'Jadwal Rencana')

@section('content')
    <div class="card shadow-none border mb-24">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <form method="GET" id="site-filter-form">
                        <label class="form-label text-sm mb-1">Site</label>
                        <select name="site" class="form-control" onchange="this.form.submit()">
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

    <div class="card shadow-none border mb-24">
        <div class="card-body">
            <div id="schedule-calendar"></div>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">Alat Minggu (Salin &amp; Kunci)</h6></div>
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
                    <form method="POST" action="{{ route('control-room.schedule.lock', ['week' => 0]) }}" class="row g-2 align-items-end" id="lock-form">
                        @csrf
                        <input type="hidden" name="site_code" value="{{ $site->value }}">
                        <div class="col-4">
                            <label class="form-label text-sm mb-1">Tahun</label>
                            <input type="number" name="year" class="form-control form-control-sm" value="{{ now()->isoWeekYear() }}" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label text-sm mb-1">Minggu</label>
                            <input type="number" class="form-control form-control-sm" min="1" max="53" value="{{ now()->isoWeek() }}" required id="lock-week-input">
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-warning-600 btn-sm w-100" onclick="return confirm('Kunci minggu ini sebagai baseline?');">Kunci Minggu</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

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
                            <datalist id="personnel-options">
                                @foreach ($personnel as $person)
                                    <option value="{{ $person->emp_name }} ({{ $person->sid }})"></option>
                                @endforeach
                            </datalist>
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
@endsection

@push('styles')
    <link href="{{ asset('build/plugins/fullcalendar/css/main.min.css') }}" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('build/plugins/fullcalendar/js/main.min.js') }}"></script>
    <script>
        (function () {
            var siteCode = @json($site->value);
            var eventsUrl = @json(route('control-room.schedule.events'));
            var csrfToken = @json(csrf_token());

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

                events: function (info, successCallback, failureCallback) {
                    var url = eventsUrl + '?site=' + encodeURIComponent(siteCode)
                        + '&start=' + encodeURIComponent(info.startStr)
                        + '&end=' + encodeURIComponent(info.endStr);

                    fetch(url, { headers: { Accept: 'application/json' } })
                        .then(function (res) { return res.json(); })
                        .then(successCallback)
                        .catch(failureCallback);
                },

                dateClick: function (info) {
                    document.getElementById('modal-date-input').value = info.dateStr;
                    document.getElementById('modal-date-label').textContent = info.dateStr;
                    new bootstrap.Modal(document.getElementById('addScheduleModal')).show();
                },

                eventClick: function (info) {
                    if (info.event.extendedProps.locked) {
                        alert('Jadwal ini sudah dikunci, tidak bisa dihapus dari sini.');
                        return;
                    }

                    if (!confirm('Hapus jadwal ' + info.event.extendedProps.personnel + ' (' + info.event.extendedProps.shift + ')?')) {
                        return;
                    }

                    fetch(info.event.extendedProps.deleteUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: '_method=DELETE',
                    }).then(function (res) {
                        if (res.ok) {
                            calendar.refetchEvents();
                        } else {
                            res.json().then(function (data) {
                                alert(data.message || 'Gagal menghapus jadwal.');
                            });
                        }
                    });
                },
            });

            calendar.render();

            var lockForm = document.getElementById('lock-form');
            var lockFormBaseAction = lockForm.action;
            lockForm.addEventListener('submit', function () {
                var week = document.getElementById('lock-week-input').value;
                lockForm.action = lockFormBaseAction.replace('/0/lock', '/' + week + '/lock');
            });
        })();
    </script>
@endpush
