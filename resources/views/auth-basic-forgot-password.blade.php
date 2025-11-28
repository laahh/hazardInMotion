@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')

  <body class="bg-forgot-password">


    <!--authentication-->

     <div class="container my-5">
        <div class="row">
           <div class="col-12 col-md-8 col-lg-6 col-xl-5 col-xxl-4 mx-auto">
            <div class="card border-3">
              <div class="card-body p-5">
                  <img src="{{ URL::asset('build/images/logo1.png') }}" class="mb-4" width="145" alt="">
                  <h4 class="fw-bold">Forgot Password?</h4>
                  <p class="mb-0">Enter your registered email ID to reset the password</p>

                  <div class="form-body mt-4">
										<form class="row g-3">
											<div class="col-12">
												<label class="form-label">Email id</label>
												<input type="text" class="form-control" name="useremail" placeholder="Enter Email">
											</div>
											<div class="col-12">
												<div class="d-grid gap-2">
                          <button type="submit" class="btn btn-primary">Send</button>
                           <a href="auth-basic-login" class="btn btn-light">Back to Login</a>
                        </div>
											</div>
										</form>
									</div>

              </div>
            </div>
           </div>
        </div><!--end row-->
     </div>
    
@endsection