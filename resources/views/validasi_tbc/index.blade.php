@extends('layouts.master')

@section('title', 'Validasi TBC')

@section('content')
    <x-page-title title="Validasi TBC" pagetitle="Hazard Weekly Validation" />

   

    <div class="row mb-3">
        <div class="col-12">
            <div class="card rounded-4">
                <div class="card-header">
                    <h5 class="mb-0">Filter Week & Site</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('validasi-tbc.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="week_start" class="form-label">Week (mulai Senin)</label>
                            <input type="date"
                                   id="week_start"
                                   name="week_start"
                                   class="form-control"
                                   value="{{ $weekStart->format('Y-m-d') }}">
                            <small class="text-muted">Week dihitung dari Senin sampai Senin berikutnya.</small>
                        </div>
                        <div class="col-md-4">
                            <label for="site" class="form-label">Site</label>
                            <select name="site" id="site" class="form-select">
                                <option value="">-- Pilih Site --</option>
                               
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary rounded-3">Terapkan Filter</button>
                            <a href="" class="btn btn-outline-secondary rounded-3">Reset</a>
                        </div>
                    </form>
                    <div class="mt-3 text-muted">
                        <small>
                            Periode week: 
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card rounded-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Daftar Hazard untuk Validasi</h5>
                        <small class="text-muted">
                            Data diambil dari week & site sesuai filter. Kerangka ini menunggu query ke tabel hazard utama.
                        </small>
                    </div>
                </div>
                <div class="card-body">
                    
                        <form method="POST" action="{{ route('validasi-tbc.store') }}">
                            @csrf
                            <input type="hidden" name="week_start" value="{{ $weekStart->format('Y-m-d') }}">
                            <input type="hidden" name="site" value="{{ $selectedSite }}">

                            <div class="table-responsive">
                                <table class="table table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Valid</th>
                                            <th>Task</th>
                                            <th>Tanggal</th>
                                            <th>Site</th>
                                            <th>Lokasi</th>
                                            <th>Kategori</th>
                                            <th>Deskripsi</th>
                                            <th>GR Status</th>
                                            <th>Sub Ketidaksesuaian</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                       
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-success rounded-3">
                                    Simpan Validasi TBC
                                </button>
                            </div>
                        </form>
               
                </div>
            </div>
        </div>
    </div>
@endsection


