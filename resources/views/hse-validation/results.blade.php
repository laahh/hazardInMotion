@extends('layouts.master')

@section('title', 'HSE Validation Results')
@section('css')
    <link href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
    <script>
        // Definisikan fungsi secara global SEBELUM konten di-render
        window.handleImageError = function(img) {
            console.log('Image failed to load:', img.src);
            
            // Coba load langsung dari URL asli jika menggunakan proxy
            if (img.src.includes('/hse-validation/image-proxy')) {
                // Jika sudah pernah dicoba, tampilkan placeholder
                if (img.dataset.retryAttempted === 'true') {
                    window.showImagePlaceholder(img, 'Gambar tidak dapat dimuat');
                    return;
                }
                img.dataset.retryAttempted = 'true';
                img.src = img.dataset.originalUrl || img.dataset.imageUrl;
                return;
            }
            
            // Jika masih gagal, tampilkan placeholder
            window.showImagePlaceholder(img, 'Gambar tidak dapat dimuat');
        };

        window.showImagePlaceholder = function(img, message) {
            img.style.display = 'none';
            var placeholder = document.createElement('div');
            placeholder.className = 'd-flex flex-column align-items-center justify-content-center bg-light border rounded p-2';
            placeholder.style.width = '200px';
            placeholder.style.height = '200px';
            placeholder.style.minWidth = '200px';
            placeholder.style.minHeight = '200px';
            placeholder.style.cursor = 'pointer';
            placeholder.onclick = function() {
                var url = img.dataset.originalUrl || img.dataset.imageUrl;
                if (url) {
                    window.open(url, '_blank');
                }
            };
            placeholder.innerHTML = '<i class="ri-image-line" style="font-size: 48px; color: #ccc;"></i><small class="text-muted mt-2 text-center">' + message + '</small><small class="text-primary mt-1" style="font-size: 11px;">Klik untuk membuka link</small>';
            img.parentNode.appendChild(placeholder);
        };

        window.handleImageLoad = function(img) {
            // Hide loading indicator jika ada
            var parentRow = img.closest('tr');
            if (parentRow) {
                var rowIndex = Array.from(parentRow.parentNode.children).indexOf(parentRow);
                var loadingEl = document.getElementById('loading-' + (rowIndex - 1)); // -1 karena header row
                if (loadingEl) {
                    loadingEl.style.display = 'none';
                }
            }
        };

        window.showImageModal = function(imageUrl, description) {
            var modalImage = document.getElementById('modalImage');
            var modalImageLink = document.getElementById('modalImageLink');
            var modalDescription = document.getElementById('modalDescription');
            
            if (!modalImage || !modalImageLink || !modalDescription) {
                console.error('Modal elements not found');
                return;
            }
            
            // Reset image
            modalImage.src = '';
            modalImage.onerror = function() {
                this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0ic2Fucy1zZXJpZiIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkdhbWJhciB0aWRhayBkYXBhdCBkaW11YXQ8L3RleHQ+PC9zdmc+';
                modalDescription.textContent = 'Gambar tidak dapat dimuat. Silakan klik tombol "Buka di Tab Baru" untuk melihat gambar.';
            };
            
            modalImage.src = imageUrl;
            modalImageLink.href = imageUrl;
            modalDescription.textContent = description || 'Tidak ada deskripsi';
            
            var modalElement = document.getElementById('imageModal');
            if (modalElement) {
                var modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        };
    </script>
@endsection 
@section('content')
<x-page-title title="HSE Validation" pagetitle="Hasil Validasi" />

<div class="row">
    <div class="col-12">
        <div class="card rounded-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0 fw-bold">Hasil Validasi HSE</h5>
                        <p class="mb-0 text-muted">Total: {{ count($results) }} baris data</p>
                    </div>
                    <div>
                        <a href="{{ route('hse-validation.download') }}" class="btn btn-success">
                            <i class="ri-download-2-line me-1"></i> Download Excel
                        </a>
                        <a href="{{ route('hse-validation.index') }}" class="btn btn-secondary">
                            <i class="ri-arrow-left-line me-1"></i> Kembali
                        </a>
                    </div>
                </div>

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="resultsTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Deskripsi</th>
                                <th>Url Photo</th>
                                <th>Main Category</th>
                                <th>Sub Category</th>
                                <th>TBC</th>
                                <th>PSPP</th>
                                <th>GR</th>
                                <th>Incident</th>
                                <th>Justifikasi</th>
                                <th>Confidence</th>
                                <th>Match Found</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results as $result)
                            <tr>
                                <td>{{ $result['row_number'] }}</td>
                                <td>
                                    <div style="max-width: 300px; word-wrap: break-word;">
                                        {{ $result['deskripsi'] }}
                                    </div>
                                </td>
                                <td>
                                    @if($result['url_photo'])
                                        @php
                                            $photoUrl = trim($result['url_photo']);
                                            $photoUrlEncoded = htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8');
                                            $descriptionEncoded = htmlspecialchars($result['deskripsi'] ?? '', ENT_QUOTES, 'UTF-8');
                                            // Gunakan proxy jika URL dari domain eksternal
                                            $useProxy = !empty($photoUrl) && (strpos($photoUrl, 'http://') === 0 || strpos($photoUrl, 'https://') === 0);
                                            $imageSrc = $useProxy ? route('hse-validation.image-proxy', ['url' => urlencode($photoUrl)]) : $photoUrl;
                                        @endphp
                                        <div class="position-relative d-inline-block" style="min-width: 200px; min-height: 200px;">
                                            <img src="{{ $imageSrc }}" 
                                                 alt="Photo Temuan" 
                                                 class="img-thumbnail" 
                                                 style="max-width: 200px; max-height: 200px; min-width: 200px; min-height: 200px; cursor: pointer; object-fit: cover; display: block;"
                                                 data-image-url="{{ $photoUrlEncoded }}"
                                                 data-description="{{ $descriptionEncoded }}"
                                                 data-original-url="{{ $photoUrl }}"
                                                 loading="lazy"
                                                 onerror="handleImageError(this)"
                                                 onload="handleImageLoad(this)">
                                            <div class="position-absolute top-0 end-0 bg-primary text-white rounded-circle p-1" style="cursor: pointer; z-index: 10;" onclick="showImageModal('{{ $photoUrlEncoded }}', '{{ $descriptionEncoded }}')" title="Klik untuk memperbesar">
                                                <i class="ri-zoom-in-line" style="font-size: 12px;"></i>
                                            </div>
                                            <div class="position-absolute top-50 start-50 translate-middle text-muted" style="display: none;" id="loading-{{ $loop->index }}">
                                                <div class="spinner-border spinner-border-sm" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $result['validasi_main_category'] ?? '-' }}</td>
                                <td>{{ $result['validasi_sub_category'] ?? '-' }}</td>
                                <td>
                                    @if($result['validasi_TBC'])
                                        <span class="badge bg-warning">Ya</span>
                                    @else
                                        <span class="badge bg-secondary">Tidak</span>
                                    @endif
                                </td>
                                <td>
                                    @if($result['validasi_PSPP'])
                                        <span class="badge bg-info">Ya</span>
                                    @else
                                        <span class="badge bg-secondary">Tidak</span>
                                    @endif
                                </td>
                                <td>
                                    @if($result['validasi_GR'])
                                        <span class="badge bg-danger">Ya</span>
                                    @else
                                        <span class="badge bg-secondary">Tidak</span>
                                    @endif
                                </td>
                                <td>
                                    @if($result['validasi_Incident'])
                                        <span class="badge bg-dark">Ya</span>
                                    @else
                                        <span class="badge bg-secondary">Tidak</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="max-width: 300px; word-wrap: break-word;">
                                        {{ $result['validasi_justifikasi'] ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $confidence = $result['validasi_confidence'] ?? 0;
                                        $confidencePercent = round($confidence * 100, 1);
                                    @endphp
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar 
                                            @if($confidencePercent >= 80) bg-success
                                            @elseif($confidencePercent >= 50) bg-warning
                                            @else bg-danger
                                            @endif" 
                                            role="progressbar" 
                                            style="width: {{ $confidencePercent }}%"
                                            aria-valuenow="{{ $confidencePercent }}" 
                                            aria-valuemin="0" 
                                            aria-valuemax="100">
                                            {{ $confidencePercent }}%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($result['match_found'])
                                        <span class="badge bg-success">Ya</span>
                                    @else
                                        <span class="badge bg-secondary">Tidak</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
@section('scripts')  
    <script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
    
    <!-- Modal untuk menampilkan foto besar -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">Foto Temuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Foto" class="img-fluid" style="max-height: 70vh;">
                    <p id="modalDescription" class="mt-3 text-muted"></p>
                </div>
                <div class="modal-footer">
                    <a id="modalImageLink" href="" target="_blank" class="btn btn-primary">
                        <i class="ri-external-link-line me-1"></i>Buka di Tab Baru
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fungsi sudah didefinisikan di section css di atas, jadi tidak perlu didefinisikan lagi di sini
        $(document).ready(function() {
            $('#resultsTable').DataTable({
                pageLength: 25,
                order: [[0, 'asc']],
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ baris per halaman",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ baris",
                    infoEmpty: "Menampilkan 0 sampai 0 dari 0 baris",
                    infoFiltered: "(disaring dari _MAX_ total baris)",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "Selanjutnya",
                        previous: "Sebelumnya"
                    }
                }
            });
        });
    </script>
@endsection

