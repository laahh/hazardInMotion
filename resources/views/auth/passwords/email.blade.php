

@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')

  <!--authentication-->

<div class="mx-3 mx-lg-0">

  <div class="card my-5 col-xl-9 col-xxl-8 mx-auto rounded-4 overflow-hidden p-4">
    <div class="row g-4 align-items-center">
      <div class="col-lg-6 d-flex">
        <div class="card-body">
          <img src="{{ URL::asset('build/images/logo1.png') }}" class="mb-4" width="145" alt="">
          <h4 class="fw-bold">Forgot Password?</h4>
          <p class="mb-0">Enter your registered email ID to reset the password</p>

          <div class="form-body mt-4">
        @if (session('status'))
            <div class="alert alert-success" role="alert">
                {{ session('status') }}
            </div>
        @endif
            <form class="row g-3" method="POST" action="{{ route('password.email') }}">
            @csrf
              <div class="col-12 ">
                <label class="form-label">Email id</label>
                <input type="text" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="Enter Email">
                @error('email')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
              </div>
              <div class="col-12">
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary">Send</button>
                   <a href="{{ route('login') }}" class="btn btn-light">Back to Login</a>
                </div>
              </div>
            </form>
          </div>

      </div>
      </div>
      <div class="col-lg-6 d-lg-flex d-none">
        <div class="p-3 rounded-4 w-100 d-flex align-items-center justify-content-center bg-light">
          <img src="{{ URL::asset('build/images/auth/forgot-password1.png') }}" class="img-fluid" alt="">
        </div>
      </div>

    </div><!--end row-->
  </div>

</div>

@endsection

