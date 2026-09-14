@extends('pnc-monitoring.layouts.app')

@section('title', $mode === 'create' ? 'Tambah IKK' : 'Edit IKK')

@section('content')
<div class="card shadow-none border">
  <div class="card-header">
    <h6 class="mb-0">{{ $mode === 'create' ? 'Tambah Data IKK' : 'Edit Data IKK' }}</h6>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ $mode === 'create' ? route('pnc-monitoring.ikk-records.store') : route('pnc-monitoring.ikk-records.update', $row) }}">
      @csrf
      @if ($mode === 'edit')
        @method('PUT')
      @endif
      <div class="row g-3">
        @php
          $fields = [
            ['nomor', 'Nomor', 'text', true],
            ['jenis', 'Jenis', 'text', false],
            ['pekerjaan', 'Pekerjaan', 'text', false],
            ['tanggal', 'Tanggal', 'date', false],
            ['minggu', 'Minggu', 'number', false],
            ['bulan', 'Bulan', 'number', false],
            ['tahun', 'Tahun', 'number', false],
            ['site', 'Site', 'text', false],
            ['mine_contractor', 'Mine Contractor', 'text', false],
            ['perusahaan', 'Perusahaan', 'text', false],
            ['finding_ia', 'Finding IA', 'number', false],
            ['finding_verlap', 'Finding Verlap', 'number', false],
            ['ia', 'IA', 'number', false],
            ['ipk', 'IPK', 'number', false],
            ['plan_okk', 'PLAN OKK', 'number', false],
            ['okk_1', 'OKK 1', 'number', false],
            ['okk_2', 'OKK 2', 'number', false],
            ['okk_3', 'OKK 3', 'number', false],
            ['okk_layer_2', 'OKK Layer 2', 'number', false],
            ['okk_layer_3', 'OKK Layer 3', 'number', false],
            ['okk_layer_4', 'OKK Layer 4', 'number', false],
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
              value="{{ old($key, $row->{$key} instanceof \Carbon\Carbon ? $row->{$key}->format('Y-m-d') : $row->{$key}) }}"
              @if($required) required @endif
            >
          </div>
        @endforeach
      </div>
      <div class="mt-24 d-flex gap-2">
        <button type="submit" class="btn btn-primary-600">Simpan</button>
        <a href="{{ route('pnc-monitoring.ikk-records.index') }}" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
