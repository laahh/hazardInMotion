@extends('control-room.layouts.app')

@section('page-title', 'Jadwal Rencana')

@section('content')
    <form method="GET" class="card shadow-none border mb-24">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-sm mb-1">Site</label>
                    <select name="site" class="form-control">
                        @foreach ($sites as $siteOption)
                            <option value="{{ $siteOption->value }}" @selected($site === $siteOption)>{{ $siteOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-sm mb-1">Tahun</label>
                    <input type="number" name="year" value="{{ $year }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-sm mb-1">Minggu</label>
                    <input type="number" name="week" value="{{ $week }}" min="1" max="53" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary-600 w-100">Terapkan Filter</button>
                </div>
                <div class="col-md-3 text-md-end">
                    <a href="{{ route('control-room.schedule.changes') }}" class="text-primary-600 text-sm">Lihat riwayat perubahan &rarr;</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-none border mb-24">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0">Jadwal Minggu {{ $week }} / {{ $year }} — {{ $site->label() }}</h6>
            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('control-room.schedule.copy') }}" class="d-flex gap-2 align-items-center">
                    @csrf
                    <input type="hidden" name="site_code" value="{{ $site->value }}">
                    <input type="hidden" name="from_year" value="{{ $year }}">
                    <input type="hidden" name="from_week_number" value="{{ $week }}">
                    <input type="hidden" name="to_year" value="{{ $year }}">
                    <input type="number" name="to_week_number" class="form-control form-control-sm" style="width:80px" placeholder="Ke minggu" required>
                    <button type="submit" class="btn btn-outline-primary btn-sm">Salin ke Minggu Lain</button>
                </form>
                <form method="POST" action="{{ route('control-room.schedule.lock', $week) }}" onsubmit="return confirm('Kunci minggu ini sebagai baseline? Perubahan setelah dikunci wajib disertai alasan.');">
                    @csrf
                    <input type="hidden" name="site_code" value="{{ $site->value }}">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <button type="submit" class="btn btn-warning-600 btn-sm">Kunci Minggu Ini</button>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Shift</th>
                            <th>Personil</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($plans as $slotKey => $slotPlans)
                            @foreach ($slotPlans as $plan)
                                <tr>
                                    <td>{{ $plan->date->translatedFormat('D, d M Y') }}</td>
                                    <td><span class="badge bg-info-focus text-info-600 px-8 py-2 radius-4">{{ $plan->shift_code->label() }}</span></td>
                                    <td>{{ $plan->personnel_name_snapshot }} <span class="text-secondary-light text-xs">({{ $plan->personnel_source_key }})</span></td>
                                    <td>
                                        @if ($plan->isLocked())
                                            <span class="badge bg-warning-focus text-warning-600 px-8 py-2 radius-4">Locked</span>
                                        @else
                                            <span class="badge bg-neutral-200 text-neutral-600 px-8 py-2 radius-4">Draft</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @unless ($plan->isLocked())
                                            <form method="POST" action="{{ route('control-room.schedule.destroy', $plan) }}" class="d-inline" onsubmit="return confirm('Hapus baris jadwal ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                                            </form>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-secondary-light py-24">Belum ada jadwal untuk minggu ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">Tambah Assignment (bisa banyak baris, satu submit)</h6></div>
        <div class="card-body">
            <form method="POST" action="{{ route('control-room.schedule.bulk') }}" id="bulk-assign-form">
                @csrf
                <input type="hidden" name="site_code" value="{{ $site->value }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="week_number" value="{{ $week }}">

                <div id="bulk-rows"></div>

                <template id="bulk-row-template">
                    <div class="row g-2 align-items-end mb-8 bulk-row">
                        <div class="col-md-3">
                            <label class="form-label text-sm mb-1">Tanggal</label>
                            <input type="date" name="__NAME__[date]" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-sm mb-1">Shift</label>
                            <select name="__NAME__[shift_code]" class="form-control" required>
                                <option value="S1">Shift 1</option>
                                <option value="S2">Shift 2</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-sm mb-1">Personil</label>
                            <select name="__NAME__[personnel_source_key]" class="form-control" required>
                                <option value="">-- pilih --</option>
                                @foreach ($personnel as $person)
                                    <option value="{{ $person->sid }}">{{ $person->emp_name }} ({{ $person->sid }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-row">Hapus Baris</button>
                        </div>
                    </div>
                </template>

                <button type="button" class="btn btn-outline-primary btn-sm mb-16" id="add-row">+ Tambah Baris</button>
                <div>
                    <button type="submit" class="btn btn-primary-600">Simpan Semua</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var container = document.getElementById('bulk-rows');
            var template = document.getElementById('bulk-row-template');
            var addBtn = document.getElementById('add-row');
            var index = 0;

            function addRow() {
                var html = template.innerHTML.replaceAll('__NAME__', 'assignments[' + index + ']');
                var wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                var row = wrapper.firstElementChild;
                row.querySelector('.remove-row').addEventListener('click', function () {
                    row.remove();
                });
                container.appendChild(row);
                index++;
            }

            addBtn.addEventListener('click', addRow);
            addRow();
        })();
    </script>
@endpush
