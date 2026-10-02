@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Inspeksi')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Inspeksi</h6>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('emergency-response.inspection.findings.index') }}" class="btn btn-outline-secondary btn-sm"><i class="ri-alert-line"></i> Temuan Terbuka</a>
                <a href="{{ route('er-inspection.public.form') }}" target="_blank" class="btn btn-primary-600 btn-sm"><i class="ri-external-link-line"></i> Buka Form Inspeksi</a>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-info bg-info-focus text-info-600 border-0 d-flex align-items-center gap-2 mb-16" role="alert">
                <i class="ri-information-line"></i>
                <span class="text-sm">
                    Pengisian inspeksi dilakukan di form terpisah
                    <a href="{{ route('er-inspection.public.form') }}" target="_blank" class="fw-semibold text-info-600">/form/inspeksi-emergency</a>
                    &mdash; bisa dibuka dari HP tanpa login, atau lewat scan QR di stiker peralatan.
                    Halaman ini untuk review dan approval hasilnya.
                </span>
            </div>

            <form method="GET" class="row g-2 mb-16">
                <div class="col-md-3">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor inspeksi...">
                </div>
                <div class="col-md-3">
                    <select name="site_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Site</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected(request('site_id') === $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-primary-600 w-100"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead>
                        <tr>
                            <th>No. Inspeksi</th>
                            <th>Target</th>
                            <th>Site</th>
                            <th>Inspector</th>
                            <th>Status</th>
                            <th>Waktu</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($inspections as $inspection)
                            <tr>
                                <td>{{ $inspection->inspection_number }}</td>
                                <td>{{ $inspection->target->name ?? '-' }} <span class="text-secondary-light text-sm">({{ $inspection->target->code ?? '-' }})</span></td>
                                <td>{{ $inspection->site->name ?? '-' }}</td>
                                <td>
                                    {{ $inspection->inspectorLabel() ?? '-' }}
                                    @if ($inspection->isPublicSubmission())
                                        <span class="badge bg-warning-focus text-warning-600 px-8 py-2 radius-4 ms-1" title="Dikirim lewat form publik tanpa login">Form</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-info-focus text-info-600 px-16 py-4 radius-4">{{ $inspection->statusLabel() }}</span></td>
                                <td>{{ optional($inspection->inspected_at)->format('d M Y H:i') ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('emergency-response.inspection.show', $inspection) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-eye-line"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary-light py-24">Belum ada data inspeksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $inspections->links() }}</div>
        </div>
    </div>
@endsection
