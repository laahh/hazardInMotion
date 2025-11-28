@extends('layouts.master')

@section('title', 'Table: ' . $tableName)
@section('css')
    <link href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
@endsection 
@section('content')
<x-page-title title="Table Viewer" pagetitle="{{ $tableName }}" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">{{ $tableName }}</h5>
                        <p class="mb-0 text-muted">Total: {{ count($data) }} rows</p>
                    </div>
                    <div>
                        <a href="{{ route('database.index') }}" class="btn btn-sm btn-secondary">
                            <i class="material-icons-outlined">arrow_back</i> Back to Tables
                        </a>
                    </div>
                </div>

                @if(count($data) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="dataTable">
                        <thead>
                            <tr>
                                @foreach($columns as $column)
                                    <th>{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data as $row)
                            <tr>
                                @foreach($columns as $column)
                                    <td>
                                        @php
                                            $value = is_object($row) ? $row->{$column} : $row[$column] ?? null;
                                        @endphp
                                        @if(is_array($value) || is_object($value))
                                            <pre class="mb-0" style="max-width: 300px; overflow: auto;">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                                        @elseif(is_bool($value))
                                            {{ $value ? 'true' : 'false' }}
                                        @elseif(is_null($value))
                                            <span class="text-muted">NULL</span>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-info">
                    No data found in this table.
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
        $('#dataTable').DataTable({
            order: [],
            pageLength: 25,
            responsive: true,
            scrollX: true,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        });
    });
</script>
@endsection

