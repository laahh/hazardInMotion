@extends('control-room.layouts.app')

@section('page-title', 'Dashboard')

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
            </div>
        </div>
    </form>

    <div class="card shadow-none border">
        <div class="card-body text-center py-40">
            <i class="ri-tools-line text-4xl text-secondary-light mb-16 d-block"></i>
            <h6 class="mb-8">Panel KPI belum tersedia</h6>
            <p class="text-secondary-light text-sm mb-0">
                Menunggu Fase 4 (normalisasi SAP) dan Fase 5 (agregasi) — keduanya menunggu verifikasi T0.1
                (apakah <code>mv_inspeksi_hazard</code> dkk benar ada di Postgres HSE). Lihat <code>plan-OCR.md</code>
                bagian 0.5 poin 2 dan Lampiran D pertanyaan #25.
            </p>
        </div>
    </div>
@endsection
