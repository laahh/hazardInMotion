@extends('layouts.master')

@section('title', 'Database Error')
@section('css')
	
@endsection 
@section('content')
<x-page-title title="Database Error" pagetitle="Connection Error" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="alert alert-danger">
                    <h5 class="alert-heading">
                        <i class="material-icons-outlined">error</i> Database Connection Error
                    </h5>
                    <hr>
                    <p class="mb-0" style="white-space: pre-line;">{{ $error }}</p>
                </div>

                <div class="alert alert-warning mt-3">
                    <h6 class="alert-heading">⚠️ Connection Timeout Detected</h6>
                    <p class="mb-2">Jika Anda melihat error "Connection timed out", ini berarti:</p>
                    <ul class="mb-0">
                        <li>SSH server tidak bisa diakses dari komputer Anda</li>
                        <li>Mungkin perlu VPN atau network connection tertentu</li>
                        <li>Atau firewall memblokir koneksi</li>
                    </ul>
                    <hr>
                    <p class="mb-0"><strong>Solusi:</strong> Hubungi admin untuk:
                        <br>1. Tambahkan IP Anda ke AWS Security Group, atau
                        <br>2. Berikan akses VPN/network yang diperlukan, atau  
                        <br>3. Cek apakah database bisa diakses langsung (tanpa SSH tunnel)
                    </p>
                </div>

                <div class="mt-4">
                    <h6>🔄 Cara Tercepat (3 Langkah Saja):</h6>
                    <div class="alert alert-info">
                        <ol class="mb-0">
                            <li><strong>Double-click file:</strong> <code>setup-ssh-tunnel.bat</code> di folder project</li>
                            <li><strong>Biarkan terminal terbuka</strong> (jangan tutup)</li>
                            <li><strong>Refresh halaman ini</strong> - Selesai! ✅</li>
                        </ol>
                    </div>
                </div>

                <div class="mt-4">
                    <h6>Quick Solution:</h6>
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="mb-3">Option 1: Use Helper Script (Easiest)</h6>
                            <p>Double-click one of these files in your project root:</p>
                            <ul>
                                <li><code>setup-ssh-tunnel.bat</code> - For Windows Command Prompt</li>
                                <li><code>setup-ssh-tunnel.ps1</code> - For PowerShell</li>
                            </ul>
                            <p class="mb-0">Keep the terminal window open, then refresh this page.</p>
                        </div>
                    </div>

                    <div class="card bg-light mt-3">
                        <div class="card-body">
                            <h6 class="mb-3">Option 2: Manual Command</h6>
                            <p>Open PowerShell or Command Prompt and run:</p>
                            <div class="bg-dark text-light p-3 rounded">
                                <code style="color: #00ff00;">
                                    ssh -N -L 5433:postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com:5432 ubuntu@13.212.87.127 -p 22 -i "C:\laragon\www\Admin\public\JumpHostVPC2.pem"
                                </code>
                            </div>
                            <p class="mb-0 mt-2">Keep the terminal open, then refresh this page.</p>
                        </div>
                    </div>

                    <div class="card bg-light mt-3">
                        <div class="card-body">
                            <h6 class="mb-3">Option 3: Check if Tunnel Already Exists</h6>
                            <p>Check if port 5433 is already in use:</p>
                            <div class="bg-dark text-light p-3 rounded">
                                <code style="color: #00ff00;">
                                    netstat -an | findstr 5433
                                </code>
                            </div>
                            <p class="mb-0 mt-2">If you see output, the tunnel is already running. Just refresh this page.</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6>Troubleshooting:</h6>
                        <ul>
                            <li>Make sure SSH client is installed: Run <code>ssh -V</code> in command prompt</li>
                            <li>Verify PEM file exists: <code>{{ config('database.connections.pgsql_ssh.ssh_pkey', 'Not set') }}</code></li>
                            <li>Test SSH connection manually first</li>
                            <li>Check Windows Firewall settings</li>
                            <li>Review Laravel logs: <code>storage\logs\laravel.log</code></li>
                        </ul>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="{{ route('database.index') }}" class="btn btn-primary">
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

@section('scripts')
	
@endsection

