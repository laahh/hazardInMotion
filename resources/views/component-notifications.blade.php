@extends('layouts.master')

@section('title', 'Notifications')
@section('css')
	<link rel="stylesheet" href="{{ URL::asset('build/plugins/notifications/css/lobibox.min.css') }}">

@endsection 
@section('content')
<x-page-title title="Components" pagetitle="Notifications" />
		
        <div class="card">
			<div class="card-body">
				<div class="row row-cols-auto g-3">
					<div class="col">
						<button type="button" class="btn btn-dark px-5" onclick="default_noti()">Default</button>
					</div>
					<div class="col">
						<button type="button" class="btn btn-info px-5 d-flex gap-2" onclick="info_noti()"><i class="material-icons-outlined">info</i>Info</button>
					</div>
					<div class="col">
						<button type="button" class="btn btn-warning px-5 d-flex gap-2" onclick="warning_noti()"><i class="material-icons-outlined">warning_amber</i>Warning</button>
					</div>
					<div class="col">
						<button type="button" class="btn btn-danger px-5 d-flex gap-2" onclick="error_noti()"><i class="material-icons-outlined">dangerous</i>Danger</button>
					</div>
					<div class="col">
						<button type="button" class="btn btn-success px-5 d-flex gap-2" onclick="success_noti()"><i class="material-icons-outlined">check_circle</i>Success</button>
					</div>
				</div>
				<!--end row-->
			</div>
		</div>
			<h6 class="mb-0 text-uppercase">Rounded Corners Notifications</h6>
			<hr>
			<div class="card">
				<div class="card-body">
					<div class="row row-cols-auto g-3">
						<div class="col">
							<button type="button" class="btn btn-dark px-5" onclick="round_default_noti()">Default</button>
						</div>
						<div class="col">
							<button type="button" class="btn btn-info px-5 d-flex gap-2" onclick="round_info_noti()"><i class="material-icons-outlined">info</i>Info</button>
						</div>
						<div class="col">
							<button type="button" class="btn btn-warning px-5 d-flex gap-2" onclick="round_warning_noti()"><i class="material-icons-outlined">warning_amber</i>Warning</button>
						</div>
						<div class="col">
							<button type="button" class="btn btn-danger px-5 d-flex gap-2" onclick="round_error_noti()"><i class="material-icons-outlined">dangerous</i>Danger</button>
						</div>
						<div class="col">
							<button type="button" class="btn btn-success px-5 d-flex gap-2" onclick="round_success_noti()"><i class="material-icons-outlined">check_circle</i>Success</button>
						</div>
					</div>
					<!--end row-->
				</div>
			</div>
			<h6 class="mb-0 text-uppercase">Notifications With image</h6>
			<hr>
			<div class="card">
				<div class="card-body">
					<div class="row row-cols-auto g-3">
						<div class="col">
							<button type="button" class="btn btn-dark px-5" onclick="img_default_noti()">Default</button>
						</div>
						<div class="col">
							<button type="button" class="btn btn-info px-5 d-flex gap-2" onclick="img_info_noti()"><i class="material-icons-outlined">info</i>Info</button>
						</div>
						<div class="col">
							<button type="button" class="btn btn-warning px-5 d-flex gap-2" onclick="img_warning_noti()"><i class="material-icons-outlined">warning_amber</i>Warning</button>
						</div>
						<div class="col">
							<button type="button" class="btn btn-danger px-5 d-flex gap-2" onclick="img_error_noti()"><i class="material-icons-outlined">dangerous</i>Danger</button>
						</div>
						<div class="col">
							<button type="button" class="btn btn-success px-5 d-flex gap-2" onclick="img_success_noti()"><i class="material-icons-outlined">check_circle</i>Success</button>
						</div>
					</div>
					<!--end row-->
				</div>
			</div>
			<h6 class="mb-0 text-uppercase">Alternative Position</h6>
			<hr>
			<div class="card">
				<div class="card-body">

						<div class="row row-cols-auto g-3">
							<div class="col">
								<button type="button" class="btn btn-dark px-5" onclick="pos1_default_noti()">Default</button>
							</div>
							<div class="col">
								<button type="button" class="btn btn-info px-5 d-flex gap-2" onclick="pos2_info_noti()"><i class="material-icons-outlined">info</i>Info</button>
							</div>
							<div class="col">
								<button type="button" class="btn btn-warning px-5 d-flex gap-2" onclick="pos3_warning_noti()"><i class="material-icons-outlined">warning_amber</i>Warning</button>
							</div>
							<div class="col">
								<button type="button" class="btn btn-danger px-5 d-flex gap-2" onclick="pos4_error_noti()"><i class="material-icons-outlined">dangerous</i>Danger</button>
							</div>
							<div class="col">
								<button type="button" class="btn btn-success px-5 d-flex gap-2" onclick="pos5_success_noti()"><i class="material-icons-outlined">check_circle</i>Success</button>
							</div>
						</div>
					<!--end row-->
				</div>
			</div>
@endsection 
@section('scripts')
  <!--notification js -->
	<script src="{{ URL::asset('build/plugins/notifications/js/lobibox.min.js') }}"></script>
	<script src="{{ URL::asset('build/plugins/notifications/js/notifications.min.js') }}"></script>
	<script src="{{ URL::asset('build/plugins/notifications/js/notification-custom-script.js') }}"></script>
@endsection 