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
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="edit-schedule-form">
                    @csrf
                    @method('PUT')

                    <div class="modal-header">
                        <h6 class="modal-title">Ubah Jadwal — <span id="edit-modal-date-label"></span></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-16">
                            <label class="form-label text-sm mb-1">Shift</label>
                            <select name="shift_code" id="edit-shift-select" class="form-control" required>
                                <option value="S1">Shift 1</option>
                                <option value="S2">Shift 2</option>
                            </select>
                        </div>
                        <div class="mb-16">
                            <label class="form-label text-sm mb-1">Personil (ketik nama atau SID)</label>
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
                            <i class="ri-lock-line"></i> Jadwal ini sudah dikunci sebagai baseline — perubahan akan
                            tetap tercatat di riwayat (lihat "Riwayat perubahan").
                        </p>
                        <div id="edit-reason-wrapper" style="display:none">
                            <label class="form-label text-sm mb-1">Alasan Perubahan (wajib)</label>
                            <textarea name="reason" id="edit-reason-input" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-danger" id="edit-delete-btn">Hapus Jadwal</button>
                        <div>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary-600">Simpan Perubahan</button>
                        </div>
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

            var editModalEl = document.getElementById('editScheduleModal');
            var editModal = new bootstrap.Modal(editModalEl);
            var editForm = document.getElementById('edit-schedule-form');
            var editShiftSelect = document.getElementById('edit-shift-select');
            var editPersonnelInput = document.getElementById('edit-personnel-input');
            var editReasonWrapper = document.getElementById('edit-reason-wrapper');
            var editReasonInput = document.getElementById('edit-reason-input');
            var editLockedNote = document.getElementById('edit-locked-note');
            var editDeleteBtn = document.getElementById('edit-delete-btn');
            var editModalDateLabel = document.getElementById('edit-modal-date-label');
            var currentDeleteUrl = null;

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
                    } else {
                        res.json().then(function (data) {
                            alert(data.message || 'Gagal menghapus jadwal.');
                        });
                    }
                });
            }
            editDeleteBtn.addEventListener('click', deleteCurrentSchedule);

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
                    var color = arg.event.backgroundColor || '#333';
                    var title = arg.event.title.replace(/</g, '&lt;');

                    return {
                        html:
                            '<div class="d-flex align-items-center gap-1 px-4 py-2" style="overflow:hidden;">' +
                            '<span class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center flex-shrink-0" ' +
                            'style="width:16px;height:16px;font-size:9px;font-weight:700;color:' + color + ';">' + initial + '</span>' +
                            '<span class="text-truncate" style="font-size:11px;">' + title + '</span>' +
                            '</div>',
                    };
                },

                dateClick: function (info) {
                    document.getElementById('modal-date-input').value = info.dateStr;
                    document.getElementById('modal-date-label').textContent = info.dateStr;
                    new bootstrap.Modal(document.getElementById('addScheduleModal')).show();
                },

                eventClick: function (info) {
                    var props = info.event.extendedProps;

                    editForm.action = props.updateUrl;
                    editModalDateLabel.textContent = info.event.startStr;
                    editShiftSelect.value = props.shift;
                    editPersonnelInput.value = props.personnel + ' (' + props.personnelSourceKey + ')';
                    editReasonInput.value = '';
                    editReasonWrapper.style.display = props.locked ? '' : 'none';
                    editReasonInput.required = props.locked;
                    editLockedNote.style.display = props.locked ? '' : 'none';
                    editDeleteBtn.style.display = props.locked ? 'none' : '';
                    currentDeleteUrl = props.deleteUrl;

                    editModal.show();
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
