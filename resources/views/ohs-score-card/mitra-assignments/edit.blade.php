@extends('evaluasi-well.layouts.app')

@section('title', 'Edit Assignment Mitra')

@section('css')
@include('evaluasi-well.mitra-assignments._form-assets')
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Edit Assignment Mitra</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.mitra-assignments.index') }}" class="hover-text-primary">Assignment Mitra</a>
    </li>
    <li>-</li>
    <li class="fw-medium">Edit</li>
  </ul>
</div>

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-0">Form Assignment</h6>
  </div>
  <div class="card-body p-24">
    <form action="{{ route('evaluasi-well.mitra-assignments.update', $assignment->id) }}" method="POST" class="needs-validation" novalidate>
      @csrf
      @method('PUT')
      @include('evaluasi-well.mitra-assignments._form')
    </form>
  </div>
</div>
@endsection

@section('scripts')
@include('evaluasi-well.mitra-assignments._form-scripts')
@endsection
