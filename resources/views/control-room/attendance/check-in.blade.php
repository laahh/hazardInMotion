@extends('control-room.layouts.app')

@section('page-title', 'Absen — Check-in')

@section('content')
    <form method="GET" class="card shadow-none border mb-24">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-sm mb-1">Site</label>
                    <select name="site" class="form-control" onchange="this.form.submit()">
                        @foreach ($sites as $siteOption)
                            <option value="{{ $siteOption->value }}" @selected($site === $siteOption)>{{ $siteOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">Check-in Absen — {{ $site->label() }}</h6></div>
        <div class="card-body">
            <form method="POST" action="{{ route('control-room.attendance.check-in') }}" id="check-in-form">
                @csrf
                <input type="hidden" name="site_code" value="{{ $site->value }}">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-sm mb-1">Personil (ketik nama atau SID untuk mencari)</label>
                        <input
                            type="text"
                            name="personnel_source_key"
                            list="personnel-options"
                            class="form-control @error('personnel_source_key') is-invalid @enderror"
                            placeholder="Ketik nama atau SID..."
                            value="{{ old('personnel_source_key', $defaultPersonnelSourceKey) }}"
                            autocomplete="off"
                            required
                        >
                        <datalist id="personnel-options">
                            @foreach ($personnel as $person)
                                <option value="{{ $person->emp_name }} ({{ $person->sid }})"></option>
                            @endforeach
                        </datalist>
                        @error('personnel_source_key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <p class="text-secondary-light text-xs mt-4 mb-0">
                            {{ $personnel->count() }} personil terdaftar di site ini. Kalau nama tidak muncul di saran,
                            ketik SID secara langsung.
                        </p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-sm mb-1">Status Kehadiran</label>
                        <select name="status" id="status-select" class="form-control">
                            <option value="hadir_sesuai_jadwal">Hadir Sesuai Jadwal</option>
                            <option value="hadir_menggantikan">Hadir Menggantikan</option>
                            <option value="tidak_hadir">Tidak Hadir</option>
                        </select>
                    </div>

                    <div class="col-md-6" id="replacing-field" style="display:none">
                        <label class="form-label text-sm mb-1">SID yang Digantikan</label>
                        <input type="text" name="replacing_source_key" list="personnel-options" class="form-control" value="{{ old('replacing_source_key') }}">
                    </div>

                    <div class="col-md-12" id="absence-reason-field" style="display:none">
                        <label class="form-label text-sm mb-1">Alasan Tidak Hadir</label>
                        <textarea name="absence_reason" class="form-control">{{ old('absence_reason') }}</textarea>
                    </div>

                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary-600">Catat Absen</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var statusSelect = document.getElementById('status-select');
            var replacingField = document.getElementById('replacing-field');
            var absenceReasonField = document.getElementById('absence-reason-field');

            function toggleFields() {
                replacingField.style.display = statusSelect.value === 'hadir_menggantikan' ? '' : 'none';
                absenceReasonField.style.display = statusSelect.value === 'tidak_hadir' ? '' : 'none';
            }

            statusSelect.addEventListener('change', toggleFields);
            toggleFields();
        })();
    </script>
@endpush
