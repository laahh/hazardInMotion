@extends('control-room.layouts.bare')

@section('page-title', 'Absensi')

@section('content')
    <main class="ocr-absensi">
        <form
            class="ocr-absensi-card"
            method="POST"
            action="{{ route('control-room.attendance.form.store') }}"
            enctype="multipart/form-data"
            id="ocr-absensi-form"
        >
            @csrf

            <header class="ocr-absensi-header">
                <h1>Absensi</h1>
                <p>Isi SID, tanggal, dan unggah bukti kehadiran.</p>
            </header>

            <div class="ocr-absensi-fields">
                @if (session('success'))
                    <div class="ocr-absensi-alert is-success" role="status">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="ocr-absensi-alert is-error" role="alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="ocr-absensi-field">
                    <label for="sid">SID</label>
                    <input
                        id="sid"
                        name="sid"
                        type="text"
                        inputmode="text"
                        autocomplete="off"
                        autocapitalize="characters"
                        value="{{ old('sid') }}"
                        class="@error('sid') is-invalid @enderror"
                        placeholder="Contoh: CVGTS"
                        required
                    >
                    @error('sid')
                        <span class="ocr-absensi-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="ocr-absensi-field">
                    <label for="tanggal">Tanggal</label>
                    <input
                        id="tanggal"
                        name="tanggal"
                        type="date"
                        value="{{ old('tanggal', $defaultTanggal) }}"
                        max="{{ $defaultTanggal }}"
                        class="@error('tanggal') is-invalid @enderror"
                        required
                    >
                    @error('tanggal')
                        <span class="ocr-absensi-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="ocr-absensi-field">
                    <label for="bukti">Bukti</label>
                    <label class="ocr-absensi-upload" for="bukti">
                        <input
                            id="bukti"
                            name="bukti"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            capture="environment"
                            required
                        >
                        <span class="ocr-absensi-upload-icon" aria-hidden="true"><i class="ri-camera-line"></i></span>
                        <span class="ocr-absensi-upload-title" id="ocr-absensi-file-label">Ambil foto atau pilih file</span>
                        <span class="ocr-absensi-upload-hint">JPG, PNG, WEBP, atau PDF · maks. 5 MB</span>
                    </label>
                    <img id="ocr-absensi-preview" class="ocr-absensi-preview" alt="Pratinjau bukti" hidden>
                    @error('bukti')
                        <span class="ocr-absensi-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="ocr-absensi-actions">
                <button type="submit" class="ocr-absensi-submit" id="ocr-absensi-submit">
                    Kirim Absensi
                </button>
            </div>
        </form>
    </main>
@endsection

@push('scripts')
    <script>
        (function () {
            var input = document.getElementById('bukti');
            var label = document.getElementById('ocr-absensi-file-label');
            var preview = document.getElementById('ocr-absensi-preview');
            var form = document.getElementById('ocr-absensi-form');
            var submit = document.getElementById('ocr-absensi-submit');

            input.addEventListener('change', function () {
                var file = input.files && input.files[0];
                if (!file) {
                    label.textContent = 'Ambil foto atau pilih file';
                    preview.hidden = true;
                    preview.removeAttribute('src');
                    return;
                }

                label.textContent = file.name;
                if (file.type.indexOf('image/') === 0) {
                    preview.src = URL.createObjectURL(file);
                    preview.hidden = false;
                } else {
                    preview.hidden = true;
                    preview.removeAttribute('src');
                }
            });

            form.addEventListener('submit', function () {
                submit.disabled = true;
                submit.textContent = 'Menyimpan...';
            });
        })();
    </script>
@endpush
