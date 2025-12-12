@extends('layouts.master')

@section('title', 'Edit Hazard Validation')

@section('content')
    <x-page-title title="Edit Hazard Validation" pagetitle="Hazard Validation Management" />

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
                    <a href="{{ route('hazard-validation.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">Kembali</a>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('hazard-validation.update', $hazardValidation->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="validator" class="form-label">Validator</label>
                            <input type="text" name="validator" id="validator" class="form-control" value="{{ old('validator', $hazardValidation->validator) }}">
                        </div>
                        <div class="mb-3">
                            <label for="tasklist" class="form-label">Tasklist <span class="text-danger">*</span></label>
                            <input type="text" name="tasklist" id="tasklist" class="form-control" value="{{ old('tasklist', $hazardValidation->tasklist) }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="tobe_concerned_hazard" class="form-label">Tobe Concerned Hazard</label>
                            <input type="text" name="tobe_concerned_hazard" id="tobe_concerned_hazard" class="form-control" value="{{ old('tobe_concerned_hazard', $hazardValidation->tobe_concerned_hazard) }}">
                        </div>
                        <div class="mb-3">
                            <label for="gr" class="form-label">GR <span class="text-danger">*</span></label>
                            <input type="text" name="gr" id="gr" class="form-control" value="{{ old('gr', $hazardValidation->gr) }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="catatan" class="form-label">Catatan</label>
                            <textarea name="catatan" id="catatan" class="form-control" rows="5">{{ old('catatan', $hazardValidation->catatan) }}</textarea>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('hazard-validation.index') }}" class="btn btn-secondary rounded-3">Batal</a>
                            <button type="submit" class="btn btn-primary rounded-3">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

