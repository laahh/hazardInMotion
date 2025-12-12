@extends('layouts.master')

@section('title', 'Edit Baseline PJA')

@section('content')
    <x-page-title title="Edit Baseline PJA" pagetitle="Baseline PJA Management" />

    <div class="row">
        <div class="col-12">
            @if (session('success'))
                <div class="alert alert-success rounded-4">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger rounded-4">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-warning rounded-4">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-8 mx-auto">
            <div class="card rounded-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Edit Data</h5>
                    <a href="{{ route('baseline-pja.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">Kembali</a>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('baseline-pja.update', $baselinePja->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="site" class="form-label">Site</label>
                            <input type="text" name="site" id="site" class="form-control" value="{{ old('site', $baselinePja->site) }}">
                        </div>
                        <div class="mb-3">
                            <label for="perusahaan" class="form-label">Perusahaan</label>
                            <input type="text" name="perusahaan" id="perusahaan" class="form-control" value="{{ old('perusahaan', $baselinePja->perusahaan) }}">
                        </div>
                        <div class="mb-3">
                            <label for="id_lokasi" class="form-label">ID Lokasi</label>
                            <input type="text" name="id_lokasi" id="id_lokasi" class="form-control" value="{{ old('id_lokasi', $baselinePja->id_lokasi) }}">
                        </div>
                        <div class="mb-3">
                            <label for="lokasi" class="form-label">Lokasi</label>
                            <input type="text" name="lokasi" id="lokasi" class="form-control" value="{{ old('lokasi', $baselinePja->lokasi) }}">
                        </div>
                        <div class="mb-3">
                            <label for="id_pja" class="form-label">ID PJA</label>
                            <input type="text" name="id_pja" id="id_pja" class="form-control" value="{{ old('id_pja', $baselinePja->id_pja) }}">
                        </div>
                        <div class="mb-3">
                            <label for="pja" class="form-label">PJA</label>
                            <input type="text" name="pja" id="pja" class="form-control" value="{{ old('pja', $baselinePja->pja) }}">
                        </div>
                        <div class="mb-3">
                            <label for="tipe_pja" class="form-label">Tipe PJA</label>
                            <input type="text" name="tipe_pja" id="tipe_pja" class="form-control" value="{{ old('tipe_pja', $baselinePja->tipe_pja) }}">
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('baseline-pja.index') }}" class="btn btn-secondary rounded-3">Batal</a>
                            <button type="submit" class="btn btn-primary rounded-3">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

