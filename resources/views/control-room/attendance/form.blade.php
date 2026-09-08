@extends('control-room.layouts.bare')

@section('page-title', 'Absensi Control Room')

@php
    $rosterByShift = $roster->groupBy(fn ($plan) => $plan->shift_code->value);
@endphp

@section('content')
    <main class="ocr-gf">
        <form
            class="ocr-gf-form"
            method="POST"
            action="{{ route('control-room.attendance.form.store') }}"
            enctype="multipart/form-data"
            id="ocr-absensi-form"
            novalidate
        >
            @csrf
            <input type="hidden" name="tanggal" id="tanggal" value="{{ old('tanggal', $defaultTanggal) }}">

            <section class="ocr-gf-card ocr-gf-card--title" aria-labelledby="ocr-gf-title">
                <p class="ocr-gf-kicker">Control Room · Pengawasan OCR</p>
                <h1 id="ocr-gf-title">Absensi jaga</h1>
                <p class="ocr-gf-desc">Isi SID. Tanggal terisi otomatis dari jadwal jaga. Unggah file atau ambil foto sebagai bukti.</p>
            </section>

            @if (session('success'))
                <div class="ocr-gf-card ocr-gf-banner is-success" role="status">
                    <strong>Absensi tercatat</strong>
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="ocr-gf-card ocr-gf-banner is-error" role="alert" tabindex="-1" id="ocr-gf-error-summary">
                    <strong>Absensi belum dapat diproses</strong>
                    <ul>
                        @foreach ($errors->keys() as $key)
                            <li><a href="#{{ $key }}">{{ $errors->first($key) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="ocr-gf-card ocr-gf-banner is-block" id="ocr-not-scheduled" role="alert" hidden>
                <strong>Tidak dapat absen</strong>
                <p id="ocr-not-scheduled-text">Anda tidak dijadwalkan hari ini jadi tidak bisa absen. Jika ada perubahan, hubungi admin.</p>
            </div>

            <section class="ocr-gf-card{{ $errors->has('sid') ? ' is-invalid' : '' }}">
                <label class="ocr-gf-question" for="sid">
                    SID <span class="ocr-gf-req" aria-hidden="true">*</span>
                </label>
                <p class="ocr-gf-help" id="sid-help">Ketik kode SID. Nama dan jadwal akan dicek otomatis.</p>
                <input
                    id="sid"
                    name="sid"
                    type="text"
                    inputmode="text"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    value="{{ old('sid') }}"
                    class="ocr-gf-input{{ $errors->has('sid') ? ' is-invalid' : '' }}"
                    placeholder="Contoh: C5BXK"
                    required
                    aria-required="true"
                    aria-describedby="sid-help{{ $errors->has('sid') ? ' sid-error' : '' }}"
                >
                @error('sid')
                    <p class="ocr-gf-error" id="sid-error"><i class="ri-error-warning-fill" aria-hidden="true"></i> {{ $message }}</p>
                @enderror
                <p class="ocr-gf-status" id="sid-status" role="status" aria-live="polite"></p>
            </section>

            <section class="ocr-gf-card" id="ocr-gf-nama-card">
                <label class="ocr-gf-question" for="nama">Nama</label>
                <p class="ocr-gf-help" id="nama-help">Terisi otomatis setelah SID dikenali.</p>
                <input
                    id="nama"
                    name="nama"
                    type="text"
                    value="{{ old('nama') }}"
                    class="ocr-gf-input"
                    placeholder="Menunggu SID..."
                    readonly
                    tabindex="-1"
                    aria-readonly="true"
                    aria-describedby="nama-help"
                >
            </section>

            <section class="ocr-gf-card{{ $errors->has('tanggal') ? ' is-invalid' : '' }}">
                <p class="ocr-gf-question" id="tanggal-label">
                    Tanggal jaga <span class="ocr-gf-req" aria-hidden="true">*</span>
                </p>
                <p class="ocr-gf-help" id="tanggal-help">Diisi otomatis sesuai jadwal Control Room yang berjalan.</p>
                <p class="ocr-gf-value" id="tanggal-display" aria-labelledby="tanggal-label" aria-describedby="tanggal-help">{{ $dutyDateLabel }}</p>
                <p class="ocr-gf-shift" id="shift-display">Shift berjalan: {{ $currentShift->label() }}</p>
                @error('tanggal')
                    <p class="ocr-gf-error" id="tanggal-error"><i class="ri-error-warning-fill" aria-hidden="true"></i> {{ $message }}</p>
                @enderror
            </section>

            <section class="ocr-gf-card{{ $errors->has('bukti') ? ' is-invalid' : '' }}" id="ocr-bukti-card">
                <p class="ocr-gf-question" id="bukti-label">
                    Bukti <span class="ocr-gf-req" aria-hidden="true">*</span>
                </p>
                <p class="ocr-gf-help" id="bukti-help">Unggah file atau ambil foto langsung. JPG, PNG, WEBP, atau PDF — maksimal 5 MB.</p>

                <div class="ocr-gf-bukti-actions">
                    <button type="button" class="ocr-gf-btn ocr-gf-btn--ghost" id="ocr-bukti-upload" aria-describedby="bukti-help">
                        <i class="ri-upload-2-line" aria-hidden="true"></i> Unggah file
                    </button>
                    <button type="button" class="ocr-gf-btn ocr-gf-btn--ghost" id="ocr-bukti-camera">
                        <i class="ri-camera-line" aria-hidden="true"></i> Ambil foto
                    </button>
                </div>

                <input
                    id="bukti"
                    name="bukti"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    required
                    aria-required="true"
                    aria-labelledby="bukti-label"
                    aria-describedby="bukti-help{{ $errors->has('bukti') ? ' bukti-error' : '' }}"
                    hidden
                >
                <input
                    id="bukti-camera-fallback"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    hidden
                    tabindex="-1"
                >

                <p class="ocr-gf-file-name" id="ocr-absensi-file-label">Belum ada bukti</p>
                <img id="ocr-absensi-preview" class="ocr-gf-preview" alt="Pratinjau bukti" hidden>

                <div class="ocr-gf-camera" id="ocr-camera-panel" hidden>
                    <video id="ocr-camera-video" autoplay playsinline muted></video>
                    <div class="ocr-gf-camera-bar">
                        <button type="button" class="ocr-gf-btn ocr-gf-btn--solid" id="ocr-camera-shot">Ambil</button>
                        <button type="button" class="ocr-gf-btn ocr-gf-btn--ghost" id="ocr-camera-cancel">Batal</button>
                    </div>
                    <p class="ocr-gf-help" id="ocr-camera-status" role="status"></p>
                </div>
                <canvas id="ocr-camera-canvas" hidden></canvas>

                @error('bukti')
                    <p class="ocr-gf-error" id="bukti-error"><i class="ri-error-warning-fill" aria-hidden="true"></i> {{ $message }}</p>
                @enderror
            </section>

            <div class="ocr-gf-actions">
                <button type="submit" class="ocr-gf-submit" id="ocr-absensi-submit" disabled>Kirim absensi</button>
                <button type="reset" class="ocr-gf-clear" id="ocr-absensi-clear">Hapus formulir</button>
            </div>
        </form>

        <aside class="ocr-gf-card ocr-gf-roster" aria-labelledby="ocr-roster-title">
            <p class="ocr-gf-kicker">Hari ini</p>
            <h2 id="ocr-roster-title">Personil jaga Control Room</h2>
            <p class="ocr-gf-help">{{ $dutyDateLabel }} · hanya nama di daftar ini yang dapat absen.</p>

            @forelse ($rosterByShift as $shiftCode => $people)
                <div class="ocr-gf-roster-group">
                    <h3>{{ $people->first()->shift_code->label() }}</h3>
                    <ul>
                        @foreach ($people as $plan)
                            <li>
                                <span class="ocr-gf-roster-name">{{ $plan->personnel_name_snapshot }}</span>
                                <span class="ocr-gf-roster-sid">{{ $plan->personnel_source_key }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="ocr-gf-empty">Belum ada jadwal Control Room untuk hari ini.</p>
            @endforelse
        </aside>
    </main>
@endsection

@push('scripts')
    <script>
        (function () {
            var form = document.getElementById('ocr-absensi-form');
            var submit = document.getElementById('ocr-absensi-submit');
            var clearBtn = document.getElementById('ocr-absensi-clear');
            var defaultDate = @json($defaultTanggal);
            var defaultDateLabel = @json($dutyDateLabel);
            var lookupUrl = @json($lookupUrl);
            var notScheduledMessage = @json(\App\Services\ControlRoom\ControlRoomDutyRosterService::NOT_SCHEDULED_MESSAGE);
            var sidInput = document.getElementById('sid');
            var namaInput = document.getElementById('nama');
            var sidStatus = document.getElementById('sid-status');
            var tanggalInput = document.getElementById('tanggal');
            var tanggalDisplay = document.getElementById('tanggal-display');
            var shiftDisplay = document.getElementById('shift-display');
            var notScheduled = document.getElementById('ocr-not-scheduled');
            var notScheduledText = document.getElementById('ocr-not-scheduled-text');
            var fileInput = document.getElementById('bukti');
            var cameraFallback = document.getElementById('bukti-camera-fallback');
            var uploadBtn = document.getElementById('ocr-bukti-upload');
            var cameraBtn = document.getElementById('ocr-bukti-camera');
            var fileLabel = document.getElementById('ocr-absensi-file-label');
            var preview = document.getElementById('ocr-absensi-preview');
            var cameraPanel = document.getElementById('ocr-camera-panel');
            var cameraVideo = document.getElementById('ocr-camera-video');
            var cameraShot = document.getElementById('ocr-camera-shot');
            var cameraCancel = document.getElementById('ocr-camera-cancel');
            var cameraStatus = document.getElementById('ocr-camera-status');
            var cameraCanvas = document.getElementById('ocr-camera-canvas');
            var lookupTimer = null;
            var lookupSeq = 0;
            var scheduled = false;
            var cameraStream = null;

            function setStatus(text, tone) {
                sidStatus.textContent = text || '';
                sidStatus.className = 'ocr-gf-status' + (tone ? ' ' + tone : '');
            }

            function setScheduled(ok, message) {
                scheduled = !!ok;
                submit.disabled = !scheduled;
                notScheduled.hidden = scheduled || !message;
                if (!scheduled && message) {
                    notScheduledText.textContent = message;
                }
                uploadBtn.disabled = !scheduled;
                cameraBtn.disabled = !scheduled;
            }

            function resetDutyDisplay() {
                tanggalInput.value = defaultDate;
                tanggalDisplay.textContent = defaultDateLabel;
                shiftDisplay.textContent = 'Shift berjalan: {{ $currentShift->label() }}';
            }

            function resetPreview() {
                fileLabel.textContent = 'Belum ada bukti';
                preview.hidden = true;
                preview.removeAttribute('src');
            }

            function assignFile(file, label) {
                if (!file) {
                    resetPreview();
                    return;
                }
                fileInput.setAttribute('name', 'bukti');
                cameraFallback.removeAttribute('name');
                try {
                    var transfer = new DataTransfer();
                    transfer.items.add(file);
                    fileInput.files = transfer.files;
                } catch (err) {
                    if (cameraFallback.files && cameraFallback.files[0] === file) {
                        fileInput.removeAttribute('name');
                        cameraFallback.setAttribute('name', 'bukti');
                    }
                }
                fileLabel.textContent = label || file.name;
                if (file.type.indexOf('image/') === 0) {
                    preview.src = URL.createObjectURL(file);
                    preview.hidden = false;
                } else {
                    preview.hidden = true;
                    preview.removeAttribute('src');
                }
            }

            function stopCamera() {
                if (cameraStream) {
                    cameraStream.getTracks().forEach(function (track) { track.stop(); });
                    cameraStream = null;
                }
                cameraVideo.srcObject = null;
                cameraPanel.hidden = true;
                cameraStatus.textContent = '';
            }

            function startCamera() {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    cameraFallback.click();
                    return;
                }
                cameraStatus.textContent = 'Menyalakan kamera...';
                cameraPanel.hidden = false;
                navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
                    audio: false,
                }).then(function (stream) {
                    cameraStream = stream;
                    cameraVideo.srcObject = stream;
                    cameraStatus.textContent = 'Posisikan bukti, lalu tekan Ambil.';
                }).catch(function () {
                    stopCamera();
                    cameraFallback.click();
                });
            }

            function lookupSid() {
                var sid = (sidInput.value || '').trim().toUpperCase();
                sidInput.value = sid;

                if (sid.length < 2) {
                    namaInput.value = '';
                    setScheduled(false, '');
                    resetDutyDisplay();
                    setStatus(sid.length ? 'Lanjutkan mengetik SID...' : '', sid.length ? 'is-wait' : '');
                    return;
                }

                var seq = ++lookupSeq;
                setStatus('Mengecek jadwal...', 'is-wait');

                fetch(lookupUrl + '?sid=' + encodeURIComponent(sid), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (seq !== lookupSeq) {
                            return;
                        }
                        if (!data || !data.found) {
                            namaInput.value = '';
                            setScheduled(false, '');
                            resetDutyDisplay();
                            setStatus('SID tidak ditemukan atau personil tidak aktif.', 'is-miss');
                            return;
                        }

                        namaInput.value = data.name || '';
                        if (data.tanggal) {
                            tanggalInput.value = data.tanggal;
                        }
                        if (data.tanggalLabel) {
                            tanggalDisplay.textContent = data.tanggalLabel;
                        }
                        if (data.shiftLabel) {
                            shiftDisplay.textContent = data.shiftLabel;
                        } else {
                            resetDutyDisplay();
                        }

                        if (data.scheduled) {
                            setScheduled(true, '');
                            setStatus(data.message || 'Jadwal jaga dikenali.', 'is-ok');
                            return;
                        }

                        setScheduled(false, data.message || notScheduledMessage);
                        setStatus(data.message || notScheduledMessage, 'is-miss');
                    })
                    .catch(function () {
                        if (seq !== lookupSeq) {
                            return;
                        }
                        namaInput.value = '';
                        setScheduled(false, '');
                        setStatus('Gagal mencari nama. Coba lagi.', 'is-miss');
                    });
            }

            sidInput.addEventListener('input', function () {
                window.clearTimeout(lookupTimer);
                lookupTimer = window.setTimeout(lookupSid, 400);
            });
            sidInput.addEventListener('blur', function () {
                window.clearTimeout(lookupTimer);
                lookupSid();
            });

            uploadBtn.addEventListener('click', function () {
                if (uploadBtn.disabled) {
                    return;
                }
                fileInput.click();
            });
            cameraBtn.addEventListener('click', function () {
                if (cameraBtn.disabled) {
                    return;
                }
                startCamera();
            });
            cameraCancel.addEventListener('click', stopCamera);
            cameraShot.addEventListener('click', function () {
                if (!cameraStream) {
                    return;
                }
                var width = cameraVideo.videoWidth || 1280;
                var height = cameraVideo.videoHeight || 720;
                cameraCanvas.width = width;
                cameraCanvas.height = height;
                cameraCanvas.getContext('2d').drawImage(cameraVideo, 0, 0, width, height);
                cameraCanvas.toBlob(function (blob) {
                    if (!blob) {
                        cameraStatus.textContent = 'Gagal mengambil foto. Coba unggah file.';
                        return;
                    }
                    var file = new File([blob], 'bukti-kamera.jpg', { type: 'image/jpeg' });
                    assignFile(file, 'Foto kamera');
                    stopCamera();
                }, 'image/jpeg', 0.86);
            });

            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0];
                assignFile(file);
            });
            cameraFallback.addEventListener('change', function () {
                var file = cameraFallback.files && cameraFallback.files[0];
                assignFile(file, file ? file.name : '');
            });

            form.addEventListener('submit', function (e) {
                if (!scheduled) {
                    e.preventDefault();
                    setScheduled(false, notScheduledMessage);
                    return;
                }
                if (!(fileInput.files && fileInput.files[0]) && !(cameraFallback.files && cameraFallback.files[0])) {
                    e.preventDefault();
                    fileLabel.textContent = 'Bukti wajib diunggah atau diambil lewat kamera.';
                    return;
                }
                submit.disabled = true;
                submit.textContent = 'Mengirim...';
            });

            clearBtn.addEventListener('click', function () {
                window.setTimeout(function () {
                    namaInput.value = '';
                    setStatus('', '');
                    resetDutyDisplay();
                    resetPreview();
                    stopCamera();
                    setScheduled(false, '');
                    submit.textContent = 'Kirim absensi';
                    fileInput.value = '';
                    cameraFallback.value = '';
                }, 0);
            });

            window.addEventListener('pagehide', stopCamera);
            var summary = document.getElementById('ocr-gf-error-summary');
            if (summary) {
                summary.focus();
            }
            if ((sidInput.value || '').trim().length >= 2) {
                lookupSid();
            } else {
                setScheduled(false, '');
            }
        })();
    </script>
@endpush
