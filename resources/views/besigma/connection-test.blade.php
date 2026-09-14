@extends('layouts.master')

@section('title', 'Tes Koneksi Besigma')

@section('content')
@php
    $connected = (bool) ($probe['connected'] ?? false);
    $target = $probe['target'] ?? [];
    $schema = is_array($probe['schema'] ?? null) ? $probe['schema'] : [];
    $tables = is_array($probe['tables'] ?? null) ? $probe['tables'] : [];
    $boundaryTables = array_values(array_filter(
        $schema,
        static fn (array $table): bool => str_contains(strtolower($table['name'] ?? ''), 'boundar')
    ));
    $schemaJson = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<x-page-title title="Besigma" pagetitle="Tes Koneksi Direct RDS" />

<div class="alert {{ $connected ? 'alert-success' : 'alert-danger' }} d-flex align-items-start gap-2" role="alert">
    <i class="material-icons-outlined">{{ $connected ? 'check_circle' : 'error_outline' }}</i>
    <div>
        @if ($connected)
            <strong>Koneksi berhasil.</strong> Postgres direct RDS
            <code>{{ ($target['host'] ?? 'PG_HOST').':'.($target['port'] ?? 5432) }}/{{ $target['database'] ?? ($probe['database'] ?? 'besigma_db') }}</code>
            sebagai <code>{{ $probe['username'] ?? ($target['username'] ?? '—') }}</code>.
            Katalog: {{ count($tables) }} objek (tabel/view), {{ count($boundaryTables) }} terkait boundary.
        @else
            <strong>Koneksi gagal.</strong> {{ $probe['error'] ?? 'Tidak dapat terhubung ke Postgres OLAP besigma.' }}
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
                <h6 class="mb-0">Hasil tes Postgres OLAP</h6>
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
                            <th>TCP {{ ($target['host'] ?? 'PG_HOST').':'.($target['port'] ?? 5432) }}</th>
                            <td>{{ ($probe['tcp_reachable'] ?? false) ? 'Terbuka' : 'Tidak merespons' }}</td>
                        </tr>
                        <tr>
                            <th>Driver</th>
                            <td><code>{{ $target['driver'] ?? 'pgsql' }}</code></td>
                        </tr>
                        <tr>
                            <th>Database</th>
                            <td><code>{{ $probe['database'] ?? ($target['database'] ?? '—') }}</code></td>
                        </tr>
                        <tr>
                            <th>User Postgres</th>
                            <td><code>{{ $probe['username'] ?? ($target['username'] ?? '—') }}</code></td>
                        </tr>
                        <tr>
                            <th>Search path</th>
                            <td><code>{{ $target['search_path'] ?? 'public' }}</code></td>
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
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="{{ route('besigma.connection-test') }}" class="btn btn-primary">Tes ulang</a>
                    <a href="{{ route('besigma.connection-test.text') }}" class="btn btn-outline-secondary">Unduh teks katalog</a>
                    <a href="{{ route('besigma.connection-test.json') }}" class="btn btn-outline-secondary">Unduh JSON katalog</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0">Target .env (direct RDS)</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-3">
                    <tbody>
                        <tr>
                            <th class="w-40">Mode</th>
                            <td><code>{{ $target['mode'] ?? 'direct' }}</code> (sama seperti RFID)</td>
                        </tr>
                        <tr>
                            <th>PG_HOST / BESIGMA_DB_HOST</th>
                            <td><code>{{ $target['host'] ?? 'PG_HOST' }}</code></td>
                        </tr>
                        <tr>
                            <th>PG_PORT / BESIGMA_DB_PORT</th>
                            <td><code>{{ $target['port'] ?? 5432 }}</code></td>
                        </tr>
                        <tr>
                            <th>BESIGMA_DB_DATABASE</th>
                            <td><code>{{ $target['database'] ?? 'besigma_db' }}</code></td>
                        </tr>
                        <tr>
                            <th>BESIGMA_DB_USERNAME</th>
                            <td><code>{{ $target['username'] ?? 'safety_evaluator_2' }}</code></td>
                        </tr>
                    </tbody>
                </table>
                <p class="text-muted small mb-2">Besigma <strong>direct RDS</strong> — jangan pakai <code>127.0.0.1:3307</code> (MySQL lama) atau <code>:5433</code> (tunnel):</p>
                <pre class="bg-light p-3 rounded small mb-3">BESIGMA_DB_HOST=postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com
BESIGMA_DB_PORT=5432
BESIGMA_DB_DATABASE=besigma
BESIGMA_DB_USERNAME=safety_evaluator_2
BESIGMA_DB_PASSWORD=safety123</pre>
                @if (isset($rfidDirect))
                    <div class="alert {{ ($rfidDirect['ok'] ?? false) ? 'alert-success' : 'alert-warning' }} py-2 small mb-0">
                        @if ($rfidDirect['ok'] ?? false)
                            RFID direct (<code>pgsql_direct</code> / {{ $rfidDirect['database'] ?? 'hse_automation' }}) hidup.
                            @if (! $connected)
                                Jaringan RDS OK — cek database <code>besigma_db</code> atau hak user.
                            @endif
                        @else
                            RFID direct juga gagal — app server belum bisa reach RDS (security group / VPN).
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($connected && $schema !== [])
<div class="card mt-3">
    <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h6 class="mb-0">Katalog tabel besigma</h6>
            <p class="text-muted small mb-0">Semua tabel/view di search_path. Jumlah baris = perkiraan Postgres (reltuples), bukan COUNT(*).</p>
        </div>
        <input type="search" id="besigma-table-filter" class="form-control form-control-sm" style="max-width: 260px" placeholder="Cari tabel / kolom…">
    </div>
    <div class="card-body">
        @if ($boundaryTables !== [])
            <p class="small text-muted mb-2">Terkait boundary</p>
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach ($boundaryTables as $table)
                    <a class="badge bg-primary text-decoration-none" href="#besigma-table-{{ $table['name'] }}">{{ $table['name'] }}</a>
                @endforeach
            </div>
        @endif

        <p class="small text-muted mb-2">Semua objek ({{ count($schema) }})</p>
        <div class="d-flex flex-wrap gap-2 mb-4" id="besigma-table-chips">
            @foreach ($schema as $table)
                <a class="badge bg-light text-dark border text-decoration-none" href="#besigma-table-{{ $table['name'] }}" data-table-chip="{{ strtolower($table['name']) }}">{{ $table['name'] }}</a>
            @endforeach
        </div>

        <div class="accordion" id="besigma-schema">
            @foreach ($schema as $index => $table)
                @php
                    $searchHay = strtolower($table['name'].' '.implode(' ', array_column($table['columns'] ?? [], 'name')).' '.implode(' ', array_column($table['columns'] ?? [], 'type')));
                    $isBoundary = str_contains(strtolower($table['name']), 'boundar');
                @endphp
                <div class="accordion-item" id="besigma-table-{{ $table['name'] }}" data-table-search="{{ $searchHay }}">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#besigma-cols-{{ $index }}">
                            <span class="me-2">
                                <code>{{ $table['name'] }}</code>
                                @if ($isBoundary)
                                    <span class="badge bg-primary">boundary</span>
                                @endif
                            </span>
                            <span class="text-muted small">
                                {{ $table['type'] }}
                                @if (!empty($table['engine'])) · {{ $table['engine'] }} @endif
                                · ~{{ number_format((int) ($table['approx_rows'] ?? 0), 0, ',', '.') }} baris
                                · {{ count($table['columns'] ?? []) }} kolom
                            </span>
                        </button>
                    </h2>
                    <div id="besigma-cols-{{ $index }}" class="accordion-collapse collapse" data-bs-parent="#besigma-schema">
                        <div class="accordion-body p-0">
                            @if ($table['comment'] !== '')
                                <p class="small text-muted px-3 pt-3 mb-0">{{ $table['comment'] }}</p>
                            @endif
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Kolom</th>
                                            <th>Tipe</th>
                                            <th>Null</th>
                                            <th>Key</th>
                                            <th>Default</th>
                                            <th>Extra</th>
                                            <th>Komentar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($table['columns'] as $column)
                                            <tr>
                                                <td><code>{{ $column['name'] }}</code></td>
                                                <td><code>{{ $column['type'] }}</code></td>
                                                <td>{{ $column['nullable'] ? 'YES' : 'NO' }}</td>
                                                <td>{{ $column['key'] !== '' ? $column['key'] : '—' }}</td>
                                                <td class="text-break">{{ $column['default'] === null ? '—' : $column['default'] }}</td>
                                                <td>{{ $column['extra'] !== '' ? $column['extra'] : '—' }}</td>
                                                <td>{{ $column['comment'] !== '' ? $column['comment'] : '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-muted">Tidak ada kolom.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <div>
            <h6 class="mb-0">Teks ringkas (salin ke chat)</h6>
            <p class="text-muted small mb-0">Semua tabel + kolom. Tempel di percakapan supaya skema Besigma bisa dipakai di `/isc/maps`.</p>
        </div>
        <button type="button" class="btn btn-sm btn-primary" id="besigma-copy-text">Salin teks</button>
    </div>
    <div class="card-body">
        <pre class="bg-light p-3 rounded small mb-0" id="besigma-schema-text" style="max-height: 420px; overflow: auto;">{{ $schemaText }}</pre>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h6 class="mb-0">JSON katalog (untuk disalin)</h6>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="besigma-copy-schema">Salin JSON</button>
    </div>
    <div class="card-body">
        <pre class="bg-light p-3 rounded small mb-0" id="besigma-schema-json" style="max-height: 420px; overflow: auto;">{{ $schemaJson }}</pre>
    </div>
</div>
@elseif ($connected)
<div class="alert alert-warning mt-3">Koneksi hidup, tetapi information_schema tidak mengembalikan tabel.</div>
@endif
@endsection

@section('scripts')
<script>
(function () {
  var input = document.getElementById('besigma-table-filter');
  var items = document.querySelectorAll('[data-table-search]');
  var chips = document.querySelectorAll('[data-table-chip]');
  if (input) {
    input.addEventListener('input', function () {
      var q = (input.value || '').toLowerCase().trim();
      items.forEach(function (el) {
        var hay = el.getAttribute('data-table-search') || '';
        el.hidden = q !== '' && hay.indexOf(q) === -1;
      });
      chips.forEach(function (el) {
        var name = el.getAttribute('data-table-chip') || '';
        el.hidden = q !== '' && name.indexOf(q) === -1;
      });
    });
  }
  function bindCopy(btnId, elId, doneLabel) {
    var copyBtn = document.getElementById(btnId);
    var el = document.getElementById(elId);
    if (!copyBtn || !el) {
      return;
    }
    copyBtn.addEventListener('click', function () {
      navigator.clipboard.writeText(el.textContent || '').then(function () {
        var original = copyBtn.textContent;
        copyBtn.textContent = doneLabel;
        setTimeout(function () { copyBtn.textContent = original; }, 1500);
      });
    });
  }
  bindCopy('besigma-copy-text', 'besigma-schema-text', 'Tersalin');
  bindCopy('besigma-copy-schema', 'besigma-schema-json', 'Tersalin');
})();
</script>
@endsection
