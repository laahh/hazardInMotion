@extends('layouts.master')

@section('title', 'HSE Validation - Processing')
@section('css')
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection 
@section('content')
<x-page-title title="HSE Validation" pagetitle="Memproses Validasi" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body text-center">
                <div class="mb-4">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                
                <h4 class="mb-3">Memproses Validasi HSE dengan AI</h4>
                <p class="text-muted mb-4">Mohon tunggu, sistem sedang memvalidasi setiap baris data...</p>

                <!-- Progress Bar -->
                <div class="mb-3">
                    <div class="progress" style="height: 30px;">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                             role="progressbar" 
                             style="width: 0%" 
                             aria-valuenow="0" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <span id="progressText" class="fw-bold">0%</span>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <p class="mb-0">
                        <span id="processedCount">0</span> dari <span id="totalCount">0</span> baris diproses
                    </p>
                </div>

                <div id="statusMessage" class="text-muted">
                    Memulai proses...
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
@section('scripts')  
<script>
    const processId = '{{ $processId }}';
    let processingInterval = null;
    let progressInterval = null;
    let isRedirecting = false; // Flag untuk mencegah multiple redirects

    function updateProgress() {
        if (isRedirecting) return; // Stop jika sedang redirect
        
        fetch(`/hse-validation/progress/${processId}`)
            .then(response => response.json())
            .then(data => {
                const progress = data.progress || 0;
                const processed = data.processed || 0;
                const total = data.total || 0;

                // Update progress bar
                document.getElementById('progressBar').style.width = progress + '%';
                document.getElementById('progressBar').setAttribute('aria-valuenow', progress);
                document.getElementById('progressText').textContent = progress + '%';
                
                // Update counts
                document.getElementById('processedCount').textContent = processed;
                document.getElementById('totalCount').textContent = total;

                // Update status message
                if (data.status === 'completed' && !isRedirecting) {
                    isRedirecting = true; // Set flag untuk mencegah multiple redirects
                    document.getElementById('statusMessage').textContent = 'Proses selesai! Mengarahkan ke hasil...';
                    document.getElementById('statusMessage').className = 'text-success fw-bold';
                    clearInterval(progressInterval);
                    clearInterval(processingInterval);
                    // Pastikan progress 100%
                    document.getElementById('progressBar').style.width = '100%';
                    document.getElementById('progressBar').setAttribute('aria-valuenow', 100);
                    document.getElementById('progressText').textContent = '100%';
                    setTimeout(() => {
                        window.location.replace(`/hse-validation/results/${processId}`); // Gunakan replace bukan href untuk mencegah back button issues
                        isRedirecting = true; // Pastikan flag sudah di-set
                    }, 1500);
                } else if (data.status === 'processing') {
                    document.getElementById('statusMessage').textContent = `Memproses baris ${processed + 1} dari ${total}...`;
                } else if (data.status === 'not_found') {
                    isRedirecting = true;
                    document.getElementById('statusMessage').textContent = 'Proses tidak ditemukan. Mengarahkan ke halaman utama...';
                    document.getElementById('statusMessage').className = 'text-danger';
                    clearInterval(progressInterval);
                    clearInterval(processingInterval);
                    setTimeout(() => {
                        window.location.replace('/hse-validation');
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error fetching progress:', error);
            });
    }

    function processNextBatch() {
        if (isRedirecting) return; // Stop jika sedang redirect
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
        fetch(`/hse-validation/process-async/${processId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Process async response:', data);
            if (data.status === 'completed' && !isRedirecting) {
                isRedirecting = true; // Set flag untuk mencegah multiple redirects
                clearInterval(processingInterval);
                clearInterval(progressInterval);
                // Update UI sebelum redirect
                document.getElementById('statusMessage').textContent = 'Proses selesai! Mengarahkan ke hasil...';
                document.getElementById('statusMessage').className = 'text-success fw-bold';
                document.getElementById('progressBar').style.width = '100%';
                document.getElementById('progressBar').setAttribute('aria-valuenow', 100);
                document.getElementById('progressText').textContent = '100%';
                setTimeout(() => {
                    window.location.replace(data.redirect || `/hse-validation/results/${processId}`); // Gunakan replace
                    isRedirecting = true; // Pastikan flag sudah di-set
                }, 1500);
            } else if (data.error) {
                console.error('Process error:', data.error);
                document.getElementById('statusMessage').textContent = 'Terjadi kesalahan: ' + data.error;
                document.getElementById('statusMessage').className = 'text-danger';
            }
        })
        .catch(error => {
            console.error('Error processing:', error);
            document.getElementById('statusMessage').textContent = 'Terjadi kesalahan saat memproses. Silakan refresh halaman.';
            document.getElementById('statusMessage').className = 'text-danger';
        });
    }

    // Start processing
    document.addEventListener('DOMContentLoaded', function() {
        // Start processing batches
        processNextBatch();
        processingInterval = setInterval(processNextBatch, 2000); // Process every 2 seconds
        
        // Update progress every 500ms
        progressInterval = setInterval(updateProgress, 500);
        updateProgress(); // Initial update
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            clearInterval(processingInterval);
            clearInterval(progressInterval);
        });
    });
</script>
@endsection

