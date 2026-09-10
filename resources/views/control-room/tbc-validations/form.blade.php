@extends('control-room.layouts.app')

@section('page-title', $mode === 'create' ? 'Tambah Validasi TBC' : 'Edit Validasi TBC')

@php
    $longFields = ['catatan', 'blindspot_terlapor_bc', 'kronologi_singkat', 'detail_rootcause_aktual', 'tindakan_perbaikan_aktual'];
@endphp

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-0">{{ $mode === 'create' ? 'Tambah Validasi TBC' : 'Edit Validasi TBC #'.$row->id }}</h6>
                <p class="text-secondary-light text-xs mb-0">Tasklist wajib dan unik.</p>
            </div>
            <a href="{{ route('control-room.tbc-validations.index') }}" class="text-primary-600 text-sm">Kembali</a>
        </div>
        <div class="card-body">
            <form
                method="POST"
                action="{{ $mode === 'create' ? route('control-room.tbc-validations.store') : route('control-room.tbc-validations.update', $row) }}"
            >
                @csrf
                @if ($mode === 'edit')
                    @method('PUT')
                @endif
                <div class="row g-3">
                    @foreach ($fields as $field)
                        @php
                            $key = $field['key'];
                            $value = old($key, $row->{$key} ?? '');
                            $isLong = in_array($key, $longFields, true);
                        @endphp
                        <div class="{{ $isLong ? 'col-12' : 'col-md-6' }}">
                            <label class="form-label text-sm mb-1" for="ocr-tbc-{{ $key }}">{{ $field['label'] }}</label>
                            @if ($key === 'no_alert' && $noAlertOptions !== [])
                                <select name="{{ $key }}" id="ocr-tbc-{{ $key }}" class="form-control">
                                    <option value="">—</option>
                                    @foreach ($noAlertOptions as $option)
                                        <option value="{{ $option }}" @selected((string) $value === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @elseif ($key === 'rootcause_aktual' && $rootcauseOptions !== [])
                                <select name="{{ $key }}" id="ocr-tbc-{{ $key }}" class="form-control">
                                    <option value="">—</option>
                                    @foreach ($rootcauseOptions as $option)
                                        <option value="{{ $option }}" @selected((string) $value === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @elseif ($isLong)
                                <textarea name="{{ $key }}" id="ocr-tbc-{{ $key }}" rows="3" class="form-control">{{ $value }}</textarea>
                            @else
                                <input type="text" name="{{ $key }}" id="ocr-tbc-{{ $key }}" value="{{ $value }}" class="form-control" @required($key === 'tasklist')>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-end gap-2 mt-24">
                    <a href="{{ route('control-room.tbc-validations.index') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary-600">{{ $mode === 'create' ? 'Simpan' : 'Perbarui' }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
