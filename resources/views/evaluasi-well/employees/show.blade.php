@extends('evaluasi-well.layouts.app')

@section('title', 'Profil Karyawan')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
    <h6 class="fw-semibold mb-0">Profil Karyawan</h6>
    <ul class="d-flex align-items-center gap-2">
        <li class="fw-medium">
            <a href="{{ route('evaluasi-well.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
                <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                Dashboard
            </a>
        </li>
        <li>-</li>
        <li class="fw-medium">Profil Karyawan</li>
    </ul>
</div>

<div class="card h-100 p-0 radius-12">
    <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-0">Profil Olahraga #{{ $userId ?? '-' }}</h6>
    </div>
    <div class="card-body p-24">
        <div class="alert alert-info bg-info-100 text-info-600 border-info-100 px-24 py-13 mb-0 radius-8 d-flex align-items-start gap-2" role="alert">
            <iconify-icon icon="solar:info-circle-bold" class="icon text-xl mt-1"></iconify-icon>
            <div>
                Halaman desain saja (template Wowdash). Data karyawan akan dihubungkan kemudian.
            </div>
        </div>
    </div>
</div>
@endsection
