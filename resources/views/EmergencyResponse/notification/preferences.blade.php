@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Preferensi Notifikasi')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header"><h6 class="mb-0">Preferensi Notifikasi</h6></div>
        <div class="card-body">
            <form action="{{ route('emergency-response.notification.preferences.update') }}" method="POST">
                @csrf
                <div class="form-check form-switch mb-16">
                    <input type="checkbox" name="in_app_enabled" value="1" class="form-check-input" id="pref-in-app" @checked($preference->in_app_enabled)>
                    <label class="form-check-label" for="pref-in-app">Notifikasi In-App</label>
                </div>
                <div class="form-check form-switch mb-16">
                    <input type="checkbox" name="email_enabled" value="1" class="form-check-input" id="pref-email" @checked($preference->email_enabled)>
                    <label class="form-check-label" for="pref-email">Notifikasi Email</label>
                </div>
                <button type="submit" class="btn btn-primary-600">Simpan</button>
            </form>
        </div>
    </div>
@endsection
