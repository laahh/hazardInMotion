@extends('layouts.master')

@section('title', 'Database Viewer')
@section('css')
    <link href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
@endsection 
@section('content')
<x-page-title title="Database Viewer" pagetitle="View All Tables" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">Database Tables</h5>
                        <p class="mb-0 text-muted">Total: {{ count($tables) }} tables</p>
                    </div>
                </div>

                @if(!empty($tables))
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="tablesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Table Name</th>
                                <th>Row Count</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tables as $index => $t)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <strong>{{ $t['schema'] }}.{{ $t['name'] }}</strong>
                                </td>
                                <td>
                                    @php $key = $t['schema'].'.'.$t['name']; @endphp
                                    @if(isset($tablesData[$key]) && !isset($tablesData[$key]['error']))
                                        {{ count($tablesData[$key]) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($tablesData[$key]) && !isset($tablesData[$key]['error']))
                                        <span class="badge bg-success">Success</span>
                                    @elseif(isset($tablesData[$key]['error']))
                                        <span class="badge bg-danger">Error</span>
                                    @else
                                        <span class="badge bg-warning">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('database.table', ['schema' => $t['schema'], 'tableName' => $t['name']]) }}" class="btn btn-sm btn-primary">
                                        <i class="material-icons-outlined">visibility</i> View Data
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <hr class="my-4">

                <h5 class="mb-3">Table Data Preview</h5>
                
                @foreach($tables as $t)
                    @php $key = $t['schema'].'.'.$t['name']; @endphp
                    @if(isset($tablesData[$key]) && !isset($tablesData[$key]['error']) && count($tablesData[$key]) > 0)
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">{{ $t['schema'] }}.{{ $t['name'] }} ({{ count($tablesData[$key]) }} rows)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            @php
                                                $firstRow = $tablesData[$key] instanceof \Illuminate\Support\Collection
                                                    ? $tablesData[$key]->first()
                                                    : ($tablesData[$key][0] ?? null);
                                            @endphp
                                            @if($firstRow)
                                                @foreach(array_keys((array)$firstRow) as $column)
                                                    <th>{{ $column }}</th>
                                                @endforeach
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(($tablesData[$key] instanceof \Illuminate\Support\Collection ? $tablesData[$key]->take(5) : array_slice($tablesData[$key], 0, 5)) as $row)
                                        <tr>
                                            @foreach((array)$row as $value)
                                                <td>
                                                    @if(is_array($value) || is_object($value))
                                                        {{ json_encode($value) }}
                                                    @else
                                                        {{ Str::limit($value ?? '', 50) }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                        @endforeach
                                        @if(count($tablesData[$key]) > 5)
                                        <tr>
                                            <td colspan="{{ count(array_keys((array)$firstRow)) }}" class="text-center text-muted">
                                                ... and {{ count($tablesData[$key]) - 5 }} more rows
                                            </td>
                                        </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-2">
                                <a href="{{ route('database.table', ['schema' => $t['schema'], 'tableName' => $t['name']]) }}" class="btn btn-sm btn-primary">
                                    View All Data
                                </a>
                            </div>
                        </div>
                    </div>
                    @elseif(isset($tablesData[$key]['error']))
                    <div class="alert alert-danger mb-3">
                        <strong>{{ $t['schema'] }}.{{ $t['name'] }}:</strong> {{ $tablesData[$key]['error'] }}
                    </div>
                    @endif
                @endforeach

                @else
                <div class="alert alert-info">
                    No tables found in the database.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<script>
    $(document).ready(function() {
        $('#tablesTable').DataTable({
            order: [[1, 'asc']],
            pageLength: 25,
            responsive: true
        });
    });
</script>
@endsection

