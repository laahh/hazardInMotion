@extends('EmergencyResponse.layouts.app')

@section('page-title', $template->exists ? 'Edit Template Email' : 'Tambah Template Email')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header">
            <h6 class="mb-0">{{ $template->exists ? 'Edit' : 'Tambah' }} Template Email</h6>
        </div>
        <div class="card-body">
            <form action="{{ $template->exists ? route('emergency-response.master-data.email-templates.update', $template) : route('emergency-response.master-data.email-templates.store') }}" method="POST">
                @csrf
                @if ($template->exists)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" name="code" class="form-control" value="{{ old('code', $template->code) }}" required maxlength="100" placeholder="mis. incident_new">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $template->name) }}" required maxlength="255">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Subjek Email</label>
                    <input type="text" name="subject" class="form-control" value="{{ old('subject', $template->subject) }}" required maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label">Isi Email (HTML, mendukung placeholder {{ '{{variabel}}' }})</label>
                    <textarea name="body_html" class="form-control" rows="10">{{ old('body_html', $template->body_html) }}</textarea>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="tpl-active" @checked(old('is_active', $template->is_active ?? true))>
                    <label class="form-check-label" for="tpl-active">Aktif</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary-600">Simpan</button>
                    <a href="{{ route('emergency-response.master-data.email-templates.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
