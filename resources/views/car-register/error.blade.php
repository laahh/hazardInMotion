@extends('layouts.master')

@section('title', 'Error - Car Register')
@section('content')
<x-page-title title="Car Register" pagetitle="Error" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="alert alert-danger">
                    <h5 class="alert-heading">
                        <i class="material-icons-outlined">error</i> Error Loading Car Register Data
                    </h5>
                    <hr>
                    <p class="mb-0" style="white-space: pre-line;">{{ $error }}</p>
                </div>

                @if(strpos($error, 'SSH tunnel') !== false || strpos($error, 'timeout') !== false || strpos($error, 'Connection') !== false)
                <div class="alert alert-warning mt-3">
                    <h6 class="alert-heading">⚠️ SSH Tunnel Required</h6>
                    <p class="mb-2">Database PostgreSQL tidak bisa diakses langsung. Anda perlu setup SSH tunnel terlebih dahulu.</p>
                    <hr>
                    <p class="mb-0"><strong>Solusi Cepat:</strong>
                        <br>1. Double-click file <code>setup-ssh-tunnel.bat</code> di folder project
                        <br>2. Biarkan terminal terbuka (jangan tutup)
                        <br>3. Refresh halaman ini
                    </p>
                </div>

                <div class="mt-4">
                    <h6>🔄 Cara Setup SSH Tunnel (3 Langkah):</h6>
                    <div class="alert alert-info">
                        <ol class="mb-0">
                            <li><strong>Double-click file:</strong> <code>setup-ssh-tunnel.bat</code> di folder project</li>
                            <li><strong>Biarkan terminal terbuka</strong> (jangan tutup)</li>
                            <li><strong>Refresh halaman ini</strong> - Selesai! ✅</li>
                        </ol>
                    </div>
                </div>

                <div class="mt-4">
                    <h6>Alternatif: Manual Command</h6>
                    <div class="card bg-light">
                        <div class="card-body">
                            <p>Buka PowerShell atau Command Prompt dan jalankan:</p>
                            <div class="bg-dark text-light p-3 rounded">
                                <code style="color: #00ff00;">
                                    ssh -N -L 5433:postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com:5432 ubuntu@13.212.87.127 -p 22
                                </code>
                            </div>
                            <p class="mb-0 mt-2">Jika menggunakan SSH key, tambahkan: <code>-i "path\to\your\key.pem"</code></p>
                            <p class="mb-0 mt-2">Biarkan terminal terbuka, lalu refresh halaman ini.</p>
                        </div>
                    </div>
                </div>
                @endif

                <div class="mt-3">
                    <a href="{{ route('car-register.index') }}" class="btn btn-primary">
                        <i class="material-icons-outlined">refresh</i> Try Again
                    </a>
                    <a href="{{ url('/') }}" class="btn btn-secondary">
                        <i class="material-icons-outlined">home</i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

