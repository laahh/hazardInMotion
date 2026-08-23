@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Laporan Equipment & Kondisi')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Laporan Equipment &amp; Kondisi</h6>
            <a href="{{ route('emergency-response.report.equipment.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm"><i class="ri-file-excel-2-line"></i> Export</a>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-16">
                <div class="col-md-3">
                    <select name="site_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Site</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected(request('site_id') === $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="condition" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Kondisi</option>
                        @foreach ($conditions as $value => $label)
                            <option value="{{ $value }}" @selected(request('condition') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-8">
                        <input type="checkbox" name="only_expired" value="1" class="form-check-input" id="only-expired" @checked(request('only_expired')) onchange="this.form.submit()">
                        <label class="form-check-label" for="only-expired">Hanya yang kedaluwarsa</label>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Site</th><th>Kondisi</th><th>Kedaluwarsa</th></tr></thead>
                    <tbody>
                        @forelse ($equipment as $item)
                            <tr>
                                <td>{{ $item->code }}</td>
                                <td><a href="{{ route('emergency-response.equipment.show', $item) }}">{{ $item->name }}</a></td>
                                <td>{{ $item->category->name ?? '-' }}</td>
                                <td>{{ $item->site->name ?? '-' }}</td>
                                <td>{{ $item->conditionLabel() }}</td>
                                <td>{{ optional($item->expires_at)->format('d M Y') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary-light py-24">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $equipment->links() }}</div>
        </div>
    </div>
@endsection
