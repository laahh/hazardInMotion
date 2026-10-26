@if (session('success'))
<div class="alert alert-success bg-success-100 text-success-600 border-success-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ session('success') }}
</div>
@endif

@if (session('error'))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8" role="alert">
  {{ session('error') }}
</div>
@endif

@if ($errors->any())
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8" role="alert">
  <ul class="mb-0 ps-18">
    @foreach ($errors->all() as $err)
      <li>{{ $err }}</li>
    @endforeach
  </ul>
</div>
@endif

@if (session('galat_roster'))
<div class="alert alert-danger bg-danger-100 text-danger-600 border-danger-100 px-24 py-13 mb-24 radius-8" role="alert">
  <div class="fw-semibold mb-8">File ditolak — perbaiki lalu upload ulang:</div>
  <ul class="mb-0 ps-18">
    @foreach (session('galat_roster') as $err)
      <li>{{ $err }}</li>
    @endforeach
  </ul>
</div>
@endif
