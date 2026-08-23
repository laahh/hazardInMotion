@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Template Notifikasi')

@section('content')
    <div class="card shadow-none border">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="mb-0">Template Notifikasi</h6>
            <div class="d-flex align-items-center gap-3">
                <form method="GET" class="navbar-search">
                    <input type="text" name="q" value="{{ $q }}" placeholder="Cari kode/nama...">
                    <iconify-icon icon="ion:search-outline" class="icon"></iconify-icon>
                </form>
                <a href="{{ route('emergency-response.master-data.notification-templates.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-2">
                    <i class="ri-add-line"></i> Tambah
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table bordered-table mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Channel</th>
                            <th>Judul</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr>
                                <td>{{ $template->code }}</td>
                                <td>{{ $template->name }}</td>
                                <td>{{ $template->channel }}</td>
                                <td>{{ $template->title }}</td>
                                <td>
                                    @if ($template->is_active)
                                        <span class="badge bg-success-focus text-success-600 px-16 py-4 radius-4">Aktif</span>
                                    @else
                                        <span class="badge bg-neutral-200 text-secondary-light px-16 py-4 radius-4">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('emergency-response.master-data.notification-templates.edit', $template) }}" class="btn btn-sm btn-outline-primary-600"><i class="ri-edit-line"></i></a>
                                    <form action="{{ route('emergency-response.master-data.notification-templates.destroy', $template) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus {{ addslashes($template->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary-light py-24">Belum ada template notifikasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-16">{{ $templates->links() }}</div>
        </div>
    </div>
@endsection
