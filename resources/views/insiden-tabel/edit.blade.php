@extends('layouts.master')

@section('title', 'Edit Insiden')

@section('content')
    <x-page-title title="Edit Insiden" pagetitle="Insiden Tabel" />

    <div class="card rounded-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Form Edit Insiden</h5>
            <a href="{{ route('insiden-tabel.index') }}" class="btn btn-outline-secondary rounded-3">Kembali</a>
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-warning rounded-4">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('insiden-tabel.update', $insiden) }}" class="mt-3">
                @csrf
                @method('PUT')
                @include('insiden-tabel.partials.form')
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('insiden-tabel.index') }}" class="btn btn-light rounded-3">Batal</a>
                    <button type="submit" class="btn btn-primary rounded-3">Perbarui</button>
                </div>
            </form>
        </div>
    </div>
@endsection

