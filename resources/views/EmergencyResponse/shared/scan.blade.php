@extends('EmergencyResponse.layouts.app')

@section('page-title', 'Scan Kode Aset')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-none border">
                <div class="card-body text-center p-32">
                    <i class="ri-qr-scan-2-line text-primary-600" style="font-size: 3rem;"></i>
                    <h6 class="mt-16">Buka Detail via Kode Aset</h6>
                    <p class="text-secondary-light">
                        Arahkan kamera HP ke QR code pada label equipment/safety device untuk membuka detailnya langsung,
                        atau masukkan kode aset secara manual di bawah ini.
                    </p>
                    <form method="POST" action="{{ route('emergency-response.scan.lookup') }}" class="d-flex gap-2 justify-content-center">
                        @csrf
                        <input type="text" name="code" class="form-control" style="max-width: 260px;" placeholder="mis. APAR-001" required autofocus>
                        <button type="submit" class="btn btn-primary-600">Buka</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
