


@extends('layouts.auth')

@section('title', 'Change Password')

@section('content')


  <!--authentication-->

<div class="mx-3 mx-lg-0">

  <div class="card my-5 col-xl-9 col-xxl-8 mx-auto rounded-4 overflow-hidden p-4">
    <div class="row g-4 align-items-center">
      <div class="col-lg-6 d-flex">
        <div class="card-body">
          <img src="{{ URL::asset('build/images/logo1.png') }}" class="mb-4" width="145" alt="">
          <h4 class="fw-bold">Genrate New Password</h4>
          <p class="mb-0">We received your reset password request. Please enter your new password!</p>
          <div class="form-body mt-4">
            <form class="row g-3" method="POST" action="{{ route('password.update') }}">
            @csrf
              <div class="col-12">
                <label class="form-label" for="Email">Email</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ $email ?? old('email') }}" id="Email" placeholder="Enter Email">
                @error('email')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
              </div>
              <div class="col-12">
                <label class="form-label" for="NewPassword">New Password</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="ConfirmPassword" placeholder="Confirm password">
                @error('password')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
              </div>
              <div class="col-12">
                <label class="form-label" for="ConfirmPassword">Confirm Password</label>
                <input type="password" class="form-control" name="password_confirmation" id="ConfirmPassword" placeholder="Confirm password">
                
              </div>
              <div class="col-12">
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Change Password</button>
                    <a href="{{ route('login') }}" class="btn btn-light">Back to Login</a>
                </div>
              </div>
            </form>
          </div>

      </div>
      </div>
      <div class="col-lg-6 d-lg-flex d-none">
        <div class="p-3 rounded-4 w-100 d-flex align-items-center justify-content-center bg-light">
          <img src="{{ URL::asset('build/images/auth/reset-password1.png') }}" class="img-fluid" alt="">
        </div>
      </div>

    </div><!--end row-->
  </div>

</div>

@endsection
