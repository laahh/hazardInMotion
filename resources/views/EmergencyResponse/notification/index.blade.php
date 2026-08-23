@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Notifikasi')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Notifikasi Saya</h6>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('emergency-response.notification.alerts') }}" class="btn btn-outline-secondary btn-sm"><i class="ri-alert-line"></i> Alert Sistem</a>
                <a href="{{ route('emergency-response.notification.preferences') }}" class="btn btn-outline-secondary btn-sm"><i class="ri-settings-3-line"></i> Preferensi</a>
                <form action="{{ route('emergency-response.notification.mark-all-read') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary-600 btn-sm">Tandai Semua Dibaca</button>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @forelse ($notifications as $notification)
                    <li class="list-group-item d-flex align-items-start justify-content-between gap-3 {{ $notification->is_read ? '' : 'bg-primary-50' }}">
                        <div>
                            <h6 class="mb-4 text-md">{{ $notification->title }}</h6>
                            <p class="mb-4 text-sm text-secondary-light">{{ $notification->message }}</p>
                            <span class="text-secondary-light text-xs">{{ $notification->created_at->format('d M Y H:i') }}</span>
                            @if ($notification->link_url)
                                <a href="{{ $notification->link_url }}" class="ms-8 text-sm">Lihat detail →</a>
                            @endif
                        </div>
                        @unless ($notification->is_read)
                            <form action="{{ route('emergency-response.notification.mark-read', $notification) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Tandai Dibaca</button>
                            </form>
                        @endunless
                    </li>
                @empty
                    <li class="list-group-item text-secondary-light text-center py-24">Belum ada notifikasi.</li>
                @endforelse
            </ul>
            <div class="p-16">{{ $notifications->links() }}</div>
        </div>
    </div>
@endsection
