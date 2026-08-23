@extends('EmergencyResponse.layouts.app')

@section('page-title', $template->exists ? 'Edit Template Notifikasi' : 'Tambah Template Notifikasi')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header">
            <h6 class="mb-0">{{ $template->exists ? 'Edit' : 'Tambah' }} Template Notifikasi</h6>
        </div>
        <div class="card-body">
            <form action="{{ $template->exists ? route('emergency-response.master-data.notification-templates.update', $template) : route('emergency-response.master-data.notification-templates.store') }}" method="POST">
                @csrf
                @if ($template->exists)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" name="code" class="form-control" value="{{ old('code', $template->code) }}" required maxlength="100" placeholder="mis. apar_expiring">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $template->name) }}" required maxlength="255">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Channel</label>
                        <select name="channel" class="form-control" required>
                            @foreach ($channelOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('channel', $template->channel) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Judul Notifikasi</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $template->title) }}" required maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label">Pesan (mendukung placeholder {{ '{{variabel}}' }})</label>
                    <textarea name="message" class="form-control" rows="5">{{ old('message', $template->message) }}</textarea>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="ntpl-active" @checked(old('is_active', $template->is_active ?? true))>
                    <label class="form-check-label" for="ntpl-active">Aktif</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary-600">Simpan</button>
                    <a href="{{ route('emergency-response.master-data.notification-templates.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
