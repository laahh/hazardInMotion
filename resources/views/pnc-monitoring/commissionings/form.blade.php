@extends('pnc-monitoring.layouts.app')

@section('title', $mode === 'create' ? 'Tambah Commissioning' : 'Edit Commissioning')

@section('content')
<div class="card shadow-none border">
  <div class="card-header">
    <h6 class="mb-0">{{ $mode === 'create' ? 'Tambah Data Commissioning' : 'Edit Data Commissioning' }}</h6>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ $mode === 'create' ? route('pnc-monitoring.commissionings.store') : route('pnc-monitoring.commissionings.update', $row) }}">
      @csrf
      @if ($mode === 'edit')
        @method('PUT')
      @endif
      <div class="row g-3">
        @php
          $fields = [
            ['no_register_spip', 'No Register SPIP', 'text', true],
            ['site', 'Site', 'text', false],
            ['detail_jenis_spip', 'Detail Jenis SPIP', 'text', false],
            ['keterangan_sko', 'Keterangan SKO', 'text', false],
            ['nama_pengawas_teknis', 'Nama Pengawas Teknis', 'text', false],
            ['permohonan_dokumen_1', 'Permohonan Dokumen 1', 'date', false],
            ['week', 'Week', 'number', false],
            ['tahun', 'Tahun', 'number', false],
            ['pemilik_spip', 'Pemilik SPIP', 'text', false],
            ['pengelola_spip', 'Pengelola SPIP', 'text', false],
            ['temuan_komisioning', 'Temuan Komisioning', 'number', false],
            ['status_komisioning', 'Status Komisioning', 'text', false],
            ['alasan_reject', 'Alasan Reject', 'text', false],
            ['status', 'Status', 'text', false],
            ['performance_sko', 'Performance SKO', 'number', false],
          ];
        @endphp
        @foreach ($fields as [$key, $label, $type, $required])
          <div class="col-md-4">
            <label class="form-label" for="{{ $key }}">{{ $label }}@if($required) * @endif</label>
            <input
              type="{{ $type }}"
              name="{{ $key }}"
              id="{{ $key }}"
              class="form-control"
              step="{{ $key === 'performance_sko' ? '0.0001' : null }}"
              value="{{ old($key, $row->{$key} instanceof \Carbon\Carbon ? $row->{$key}->format('Y-m-d') : $row->{$key}) }}"
              @if($required) required @endif
            >
          </div>
        @endforeach
        <div class="col-12">
          <label class="form-label" for="keterangan">Keterangan</label>
          <textarea name="keterangan" id="keterangan" class="form-control" rows="3">{{ old('keterangan', $row->keterangan) }}</textarea>
        </div>
      </div>
      <div class="mt-24 d-flex gap-2">
        <button type="submit" class="btn btn-primary-600">Simpan</button>
        <a href="{{ route('pnc-monitoring.commissionings.index') }}" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
