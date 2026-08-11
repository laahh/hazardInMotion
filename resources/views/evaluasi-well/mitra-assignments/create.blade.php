@extends('evaluasi-well.layouts.app')

@section('title', 'Tambah Assignment Mitra')

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
  .select2-container .select2-selection--single {
    height: 42px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 6px 8px;
  }
  .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 28px;
    color: #111827;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px;
  }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Tambah Assignment Mitra</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('evaluasi-well.mitra-assignments.index') }}" class="hover-text-primary">Assignment Mitra</a>
    </li>
    <li>-</li>
    <li class="fw-medium">Tambah</li>
  </ul>
</div>

<div class="card radius-8 border-0 shadow-sm">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-0">Form Assignment</h6>
  </div>
  <div class="card-body p-24">
    <form action="{{ route('evaluasi-well.mitra-assignments.store') }}" method="POST" class="needs-validation" novalidate>
      @csrf
      @include('evaluasi-well.mitra-assignments._form')
    </form>
  </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function ($) {
  $('.js-mitra-searchable').each(function () {
    var $el = $(this);
    $el.select2({
      width: '100%',
      placeholder: $el.data('placeholder') || 'Cari…',
      allowClear: true,
      language: {
        noResults: function () { return 'Tidak ditemukan'; },
        searching: function () { return 'Mencari…'; }
      }
    });
  });
})(jQuery);
</script>
@endsection
