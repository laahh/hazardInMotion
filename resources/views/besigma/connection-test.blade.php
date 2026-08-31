@extends('layouts.master')

@section('title', 'Tes Koneksi Besigma')

@section('content')
@php
    $connected = (bool) ($probe['connected'] ?? false);
    $tunnel = $probe['tunnel'] ?? [];
@endphp

<x-page-title title="Besigma" pagetitle="Tes Koneksi Jumphost" />

<div class="alert {{ $connected ? 'alert-success' : 'alert-danger' }} d-flex align-items-start gap-2" role="alert">
    <i class="material-icons-outlined">{{ $connected ? 'check_circle' : 'error_outline' }}</i>
    <div>
        @if ($connected)
            <strong>Koneksi berhasil.</strong> Laravel masuk ke Besigma lewat <code>{{ ($tunnel['local_host'] ?? '127.0.0.1').':'.($tunnel['local_port'] ?? 3307) }}</code>.
        @else
            <strong>Koneksi gagal.</strong> {{ $probe['error'] ?? 'Tidak dapat terhubung ke besigma_db.' }}
            @if (!empty($probe['hint']))
                <div class="mt-1">{{ $probe['hint'] }}</div>
            @endif
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0">Hasil tes MySQL</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="w-40">Status</th>
                            <td>
                                @if ($connected)
                                    <span class="badge bg-success">Connected</span>
                                @else
                                    <span class="badge bg-danger">Failed</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>TCP ke host Laravel</th>
                            <td>{{ ($probe['tcp_reachable'] ?? false) ? 'Terbuka' : 'Tidak merespons' }}</td>
                        </tr>
                        <tr>
                            <th>File key PEM</th>
                            <td>{{ ($probe['key_exists'] ?? false) ? 'Ada' : 'Tidak ditemukan' }}</td>
                        </tr>
                        <tr>
                            <th>Database</th>
                            <td><code>{{ $probe['database'] ?? '—' }}</code></td>
                        </tr>
                        <tr>
                            <th>User MySQL</th>
                            <td><code>{{ $probe['username'] ?? '—' }}</code></td>
                        </tr>
                        <tr>
                            <th>Versi server</th>
                            <td>{{ $probe['version'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Waktu server</th>
                            <td>{{ $probe['server_time'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Latency query</th>
                            <td>{{ isset($probe['latency_ms']) ? $probe['latency_ms'].' ms' : '—' }}</td>
                        </tr>
                        <tr>
                            <th>Jumlah tabel</th>
                            <td>{{ $probe['table_count'] ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <a href="{{ route('besigma.connection-test') }}" class="btn btn-primary mt-3">
                    Tes ulang
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0">Jalur jumphost</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-3">
                    <tbody>
                        <tr>
                            <th class="w-40">Local (Laravel)</th>
                            <td><code>{{ ($tunnel['local_host'] ?? '127.0.0.1').':'.($tunnel['local_port'] ?? 3307) }}</code></td>
                        </tr>
                        <tr>
                            <th>Jump host</th>
                            <td><code>{{ ($tunnel['ssh_user'] ?? '').'@'.($tunnel['ssh_host'] ?? '').':'.($tunnel['ssh_port'] ?? 22) }}</code></td>
                        </tr>
                        <tr>
                            <th>Remote MySQL</th>
                            <td><code>{{ ($tunnel['remote_host'] ?? '').':'.($tunnel['remote_port'] ?? 3306) }}</code></td>
                        </tr>
                        <tr>
                            <th>Private key</th>
                            <td class="text-break"><code>{{ $tunnel['ssh_pkey'] ?? '—' }}</code></td>
                        </tr>
                    </tbody>
                </table>
                <p class="text-muted small mb-2">App server tidak tembus MySQL langsung. Di Linux, buka tunnel lalu arahkan Laravel ke localhost:</p>
                <pre class="bg-light p-3 rounded small mb-0">bash setup-ssh-tunnel-besigma.sh
BESIGMA_DB_HOST=127.0.0.1
BESIGMA_DB_PORT=3307</pre>
            </div>
        </div>
    </div>
</div>

@if (!empty($probe['tables']))
<div class="card mt-3">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0">Sampel tabel (maks 30)</h6>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            @foreach ($probe['tables'] as $table)
                <span class="badge bg-light text-dark border">{{ $table }}</span>
            @endforeach
        </div>
    </div>
</div>
@endif
@endsection
