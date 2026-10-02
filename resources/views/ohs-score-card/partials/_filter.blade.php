@php
    $fromVal = date('Y-m-d', strtotime($filters['from'] ?? 'now'));
    $toVal = date('Y-m-d', strtotime($filters['to'] ?? 'now'));
@endphp
<form method="GET" action="{{ $action }}" class="row gy-3 align-items-end">
    <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label text-sm text-secondary-light mb-8">Dari Tanggal</label>
        <input type="date" name="from" value="{{ $fromVal }}" class="form-control radius-8">
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label text-sm text-secondary-light mb-8">Sampai Tanggal</label>
        <input type="date" name="to" value="{{ $toVal }}" class="form-control radius-8">
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <label class="form-label text-sm text-secondary-light mb-8">Perusahaan</label>
        <select name="perusahaan" class="form-select radius-8">
            <option value="">Semua</option>
            @foreach(($options['perusahaan'] ?? []) as $item)
                <option value="{{ $item }}" @selected(($filters['perusahaan'] ?? null) === $item)>{{ $item }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <label class="form-label text-sm text-secondary-light mb-8">Site</label>
        <select name="site" class="form-select radius-8">
            <option value="">Semua</option>
            @foreach(($options['site'] ?? []) as $item)
                <option value="{{ $item }}" @selected(($filters['site'] ?? null) === $item)>{{ $item }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <label class="form-label text-sm text-secondary-light mb-8">Divisi</label>
        <select name="divisi" class="form-select radius-8">
            <option value="">Semua</option>
            @foreach(($options['divisi'] ?? []) as $item)
                <option value="{{ $item }}" @selected(($filters['divisi'] ?? null) === $item)>{{ $item }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-lg-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary radius-8 flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1">
            <iconify-icon icon="iconoir:filter" class="icon"></iconify-icon>
            Terapkan
        </button>
        <a href="{{ $action }}" class="btn btn-outline-secondary radius-8 d-inline-flex align-items-center justify-content-center" title="Reset filter">
            <iconify-icon icon="solar:restart-bold" class="icon"></iconify-icon>
        </a>
    </div>
</form>
