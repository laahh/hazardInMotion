@extends('control-room.layouts.bare')

@section('page-title', 'Absensi Control Room')

@php
    $rosterByShift = $roster->groupBy(fn ($plan) => $plan->shift_code->value);
@endphp

@section('content')
    <div class="ocr-wrap">
        <div class="ocr-hero">
            <div class="ocr-hero-top"></div>
            <div class="ocr-hero-body">
                <span class="ocr-badge">
                    <span class="material-symbols-outlined" style="font-size:15px">lock_open</span>
                    Control Room
                </span>
                <h1>Absensi Control Room</h1>
                <p class="ocr-lead">Isi SID Anda. Tanggal terisi otomatis dari jadwal jaga. Lampirkan bukti (unggah file atau foto langsung).</p>
                <span class="ocr-pill">
                    <span class="material-symbols-outlined" style="font-size:16px">calendar_today</span>
                    {{ $dutyDateLabel }}
                </span>
            </div>
        </div>

        <div class="ocr-steps" aria-hidden="true">
            <div class="ocr-step is-active" id="step-dot-1"></div>
            <div class="ocr-step" id="step-dot-2"></div>
            <div class="ocr-step" id="step-dot-3"></div>
        </div>

        @if (session('success'))
            <div class="ocr-alert ocr-alert-ok" role="status">
                <strong>Absensi tercatat</strong>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="ocr-alert ocr-alert-error" role="alert" tabindex="-1" id="ocr-gf-error-summary">
                <strong>Periksa kembali:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            id="ocr-absensi-form"
            method="POST"
            action="{{ route('control-room.attendance.form.store') }}"
            enctype="multipart/form-data"
            novalidate
        >
            @csrf
            <input type="hidden" name="tanggal" id="tanggal" value="{{ old('tanggal', $defaultTanggal) }}">
            <input type="hidden" id="nama" name="nama" value="{{ old('nama') }}">

            <section class="ocr-card" id="section-sid">
                <h2 class="ocr-card-title"><span class="ocr-num">1</span> Cari SID Anda</h2>
                <div class="ocr-field" style="margin-bottom:0">
                    <label for="sid">Nomor SID <span class="ocr-req">*</span></label>
                    <div class="ocr-row">
                        <input
                            class="ocr-input ocr-input-mono"
                            type="text"
                            id="sid"
                            name="sid"
                            value="{{ old('sid') }}"
                            placeholder="Contoh: C5BXK"
                            required
                            maxlength="100"
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                        >
                        <button type="button" class="ocr-btn ocr-btn-secondary" id="btn-lookup" style="white-space:nowrap">
                            <span class="material-symbols-outlined">search</span>
                            Cari
                        </button>
                    </div>
                    <p class="ocr-hint">Ketik SID persis seperti di kartu identitas, lalu tekan <strong>Cari</strong>.</p>
                    <p id="sid-status" class="ocr-hint" style="margin-top:.5rem" role="status" aria-live="polite"></p>
                    @error('sid')
                        <p class="ocr-hint" style="color:#dc2626">{{ $message }}</p>
                    @enderror
                </div>
                <div id="sid-preview" class="ocr-preview">
                    <div class="ocr-preview-grid">
                        <div><span>Nama</span><strong id="pv-nama">—</strong></div>
                        <div><span>Tanggal jaga</span><strong id="tanggal-display">{{ $dutyDateLabel }}</strong></div>
                        <div><span>Shift</span><strong id="shift-display">{{ $currentShift->label() }}</strong></div>
                        <div><span>Status</span><strong id="pv-status">Menunggu SID</strong></div>
                    </div>
                </div>
            </section>

            <div class="ocr-alert ocr-alert-error" id="ocr-not-scheduled" role="alert" hidden>
                <strong>Tidak dapat absen</strong>
                <p id="ocr-not-scheduled-text">Anda tidak dijadwalkan hari ini jadi tidak bisa absen. Jika ada perubahan, hubungi admin.</p>
            </div>

            <section class="ocr-card" id="section-jadwal">
                <h2 class="ocr-card-title"><span class="ocr-num">2</span> Jadwal jaga</h2>
                <div class="ocr-alert ocr-alert-info" style="margin:0">
                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:-4px">event_available</span>
                    Tanggal diisi otomatis dari roster Control Room. Hanya personil di daftar bawah yang dapat absen.
                </div>
            </section>

            <section class="ocr-card" id="section-bukti">
                <h2 class="ocr-card-title"><span class="ocr-num">3</span> Upload Bukti (Evidence)</h2>
                <div class="ocr-alert ocr-alert-info" style="margin-top:0">
                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:-4px">info</span>
                    Bisa unggah file atau ambil foto langsung. JPG, PNG, WEBP, atau PDF. Maksimal <strong>5 MB</strong>.
                </div>

                <input
                    type="file"
                    id="bukti"
                    name="bukti"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    class="ocr-sr"
                    required
                >
                <input
                    id="bukti-camera-fallback"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    class="ocr-sr"
                    tabindex="-1"
                >

                <div class="ocr-dropzone is-disabled" id="dropzone" role="button" tabindex="0">
                    <span class="material-symbols-outlined ocr-dropzone-icon">cloud_upload</span>
                    <p class="ocr-dropzone-title">Ketuk untuk pilih file</p>
                    <p class="ocr-dropzone-sub">atau seret &amp; lepas file ke sini</p>
                    <p class="ocr-file-name" id="ocr-absensi-file-label"></p>
                </div>
                <img id="ocr-absensi-preview" class="ocr-thumb" alt="Pratinjau bukti" hidden>

                <div class="ocr-btn-row">
                    <button type="button" class="ocr-btn ocr-btn-secondary" id="ocr-bukti-camera" disabled>
                        <span class="material-symbols-outlined">photo_camera</span>
                        Ambil foto
                    </button>
                </div>

                <div class="ocr-camera" id="ocr-camera-panel" hidden>
                    <video id="ocr-camera-video" autoplay playsinline muted></video>
                    <div class="ocr-camera-bar">
                        <button type="button" class="ocr-btn ocr-btn-primary" id="ocr-camera-shot">Ambil</button>
                        <button type="button" class="ocr-btn ocr-btn-secondary" id="ocr-camera-cancel">Batal</button>
                    </div>
                    <p class="ocr-camera-status" id="ocr-camera-status" role="status"></p>
                </div>
                <canvas id="ocr-camera-canvas" hidden></canvas>
                @error('bukti')
                    <p class="ocr-hint" style="color:#dc2626">{{ $message }}</p>
                @enderror

                <button type="submit" class="ocr-btn ocr-btn-primary ocr-btn-block" id="ocr-absensi-submit" disabled style="margin-top:1rem">
                    <span class="material-symbols-outlined">send</span>
                    Kirim absensi
                </button>
                <button type="reset" class="ocr-btn ocr-btn-secondary ocr-btn-block" id="ocr-absensi-clear" style="margin-top:.65rem">
                    Hapus formulir
                </button>
            </section>
        </form>

        <aside class="ocr-card" aria-labelledby="ocr-roster-title">
            <h2 class="ocr-card-title" id="ocr-roster-title">
                <span class="material-symbols-outlined" style="color:#3952bc">groups</span>
                Personil jaga hari ini
            </h2>
            <p class="ocr-hint" style="margin-top:-.35rem">{{ $dutyDateLabel }} · hanya nama di daftar ini yang dapat absen.</p>

            @forelse ($rosterByShift as $shiftCode => $people)
                <div class="ocr-roster-group">
                    <h3>{{ $people->first()->shift_code->label() }}</h3>
                    <ul>
                        @foreach ($people as $plan)
                            <li>
                                <span class="ocr-roster-name">{{ $plan->personnel_name_snapshot }}</span>
                                <span class="ocr-roster-sid">{{ $plan->personnel_source_key }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="ocr-hint" style="margin-top:1rem">Belum ada jadwal Control Room untuk hari ini.</p>
            @endforelse
        </aside>

        <p class="ocr-footer">
            PT Berau Coal · Control Room (Pengawasan OCR)<br>
            Form ini hanya untuk personil yang dijadwalkan jaga hari ini.
        </p>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var form = document.getElementById('ocr-absensi-form');
            var submit = document.getElementById('ocr-absensi-submit');
            var clearBtn = document.getElementById('ocr-absensi-clear');
            var lookupBtn = document.getElementById('btn-lookup');
            var defaultDate = @json($defaultTanggal);
            var defaultDateLabel = @json($dutyDateLabel);
            var defaultShiftLabel = @json($currentShift->label());
            var lookupUrl = @json($lookupUrl);
            var notScheduledMessage = @json(\App\Services\ControlRoom\ControlRoomDutyRosterService::NOT_SCHEDULED_MESSAGE);
            var sidInput = document.getElementById('sid');
            var namaInput = document.getElementById('nama');
            var sidStatus = document.getElementById('sid-status');
            var tanggalInput = document.getElementById('tanggal');
            var tanggalDisplay = document.getElementById('tanggal-display');
            var shiftDisplay = document.getElementById('shift-display');
            var pvNama = document.getElementById('pv-nama');
            var pvStatus = document.getElementById('pv-status');
            var sidPreview = document.getElementById('sid-preview');
            var notScheduled = document.getElementById('ocr-not-scheduled');
            var notScheduledText = document.getElementById('ocr-not-scheduled-text');
            var fileInput = document.getElementById('bukti');
            var cameraFallback = document.getElementById('bukti-camera-fallback');
            var dropzone = document.getElementById('dropzone');
            var cameraBtn = document.getElementById('ocr-bukti-camera');
            var fileLabel = document.getElementById('ocr-absensi-file-label');
            var preview = document.getElementById('ocr-absensi-preview');
            var cameraPanel = document.getElementById('ocr-camera-panel');
            var cameraVideo = document.getElementById('ocr-camera-video');
            var cameraShot = document.getElementById('ocr-camera-shot');
            var cameraCancel = document.getElementById('ocr-camera-cancel');
            var cameraStatus = document.getElementById('ocr-camera-status');
            var cameraCanvas = document.getElementById('ocr-camera-canvas');
            var step1 = document.getElementById('step-dot-1');
            var step2 = document.getElementById('step-dot-2');
            var step3 = document.getElementById('step-dot-3');
            var lookupTimer = null;
            var lookupSeq = 0;
            var scheduled = false;
            var cameraStream = null;

            function setStatus(text, color) {
                sidStatus.textContent = text || '';
                sidStatus.style.color = color || '#64748b';
            }

            function setSteps(level) {
                [step1, step2, step3].forEach(function (dot, i) {
                    dot.classList.toggle('is-done', i < level);
                    dot.classList.toggle('is-active', i === level);
                });
            }

            function setScheduled(ok, message) {
                scheduled = !!ok;
                submit.disabled = !scheduled;
                submit.style.opacity = scheduled ? '' : '0.55';
                submit.style.cursor = scheduled ? '' : 'not-allowed';
                cameraBtn.disabled = !scheduled;
                dropzone.classList.toggle('is-disabled', !scheduled);
                notScheduled.hidden = scheduled || !message;
                if (!scheduled && message) {
                    notScheduledText.textContent = message;
                }
                if (ok) {
                    setSteps(2);
                } else if ((sidInput.value || '').trim().length >= 2) {
                    setSteps(0);
                    step1.classList.add('is-active');
                } else {
                    setSteps(0);
                }
            }

            function resetDutyDisplay() {
                tanggalInput.value = defaultDate;
                tanggalDisplay.textContent = defaultDateLabel;
                shiftDisplay.textContent = defaultShiftLabel;
                pvNama.textContent = '—';
                pvStatus.textContent = 'Menunggu SID';
                sidPreview.classList.remove('is-visible');
            }

            function resetPreview() {
                fileLabel.textContent = '';
                dropzone.classList.remove('has-file');
                preview.hidden = true;
                preview.removeAttribute('src');
            }

            function assignFile(file, label) {
                if (!file) {
                    resetPreview();
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('Ukuran file terlalu besar. Maksimal 5 MB.');
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
                fileLabel.textContent = '✓ ' + (label || file.name);
                dropzone.classList.add('has-file');
                setSteps(2);
                step3.classList.add('is-active');
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
                if (!scheduled) {
                    return;
                }
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
                    setStatus(sid.length ? 'Lanjutkan mengetik SID…' : '', '#64748b');
                    return;
                }

                var seq = ++lookupSeq;
                lookupBtn.disabled = true;
                setStatus('Mencari data…', '#64748b');

                fetch(lookupUrl + '?sid=' + encodeURIComponent(sid), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        lookupBtn.disabled = false;
                        if (seq !== lookupSeq) {
                            return;
                        }
                        if (!data || !data.found) {
                            namaInput.value = '';
                            setScheduled(false, '');
                            resetDutyDisplay();
                            setStatus('SID tidak ditemukan atau personil tidak aktif.', '#dc2626');
                            return;
                        }

                        namaInput.value = data.name || '';
                        pvNama.textContent = data.name || '—';
                        if (data.tanggal) {
                            tanggalInput.value = data.tanggal;
                        }
                        if (data.tanggalLabel) {
                            tanggalDisplay.textContent = data.tanggalLabel;
                        }
                        shiftDisplay.textContent = data.shiftLabel || defaultShiftLabel;
                        sidPreview.classList.add('is-visible');
                        step1.classList.add('is-done');
                        step2.classList.add('is-active');

                        if (data.scheduled) {
                            pvStatus.textContent = 'Dijadwalkan';
                            setScheduled(true, '');
                            setStatus(data.message || 'Jadwal jaga dikenali.', '#059669');
                            return;
                        }

                        pvStatus.textContent = 'Tidak dijadwalkan';
                        setScheduled(false, data.message || notScheduledMessage);
                        setStatus(data.message || notScheduledMessage, '#dc2626');
                    })
                    .catch(function () {
                        lookupBtn.disabled = false;
                        if (seq !== lookupSeq) {
                            return;
                        }
                        namaInput.value = '';
                        setScheduled(false, '');
                        setStatus('Koneksi gagal. Periksa internet lalu coba lagi.', '#dc2626');
                    });
            }

            lookupBtn.addEventListener('click', lookupSid);
            sidInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    lookupSid();
                }
            });
            sidInput.addEventListener('input', function () {
                window.clearTimeout(lookupTimer);
                lookupTimer = window.setTimeout(lookupSid, 450);
            });

            dropzone.addEventListener('click', function () {
                if (!scheduled) {
                    return;
                }
                fileInput.click();
            });
            dropzone.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (scheduled) {
                        fileInput.click();
                    }
                }
            });
            dropzone.addEventListener('dragover', function (e) {
                if (!scheduled) {
                    return;
                }
                e.preventDefault();
                dropzone.classList.add('is-dragover');
            });
            dropzone.addEventListener('dragleave', function () {
                dropzone.classList.remove('is-dragover');
            });
            dropzone.addEventListener('drop', function (e) {
                e.preventDefault();
                dropzone.classList.remove('is-dragover');
                if (!scheduled || !e.dataTransfer.files.length) {
                    return;
                }
                assignFile(e.dataTransfer.files[0]);
            });

            cameraBtn.addEventListener('click', startCamera);
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
                    assignFile(new File([blob], 'bukti-kamera.jpg', { type: 'image/jpeg' }), 'Foto kamera');
                    stopCamera();
                }, 'image/jpeg', 0.86);
            });

            fileInput.addEventListener('change', function () {
                assignFile(fileInput.files && fileInput.files[0]);
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
                    fileLabel.textContent = 'Pilih file bukti atau ambil foto terlebih dahulu.';
                    fileLabel.style.color = '#dc2626';
                    return;
                }
                submit.disabled = true;
                submit.innerHTML = '<span class="material-symbols-outlined">hourglass_top</span> Mengirim…';
            });

            clearBtn.addEventListener('click', function () {
                window.setTimeout(function () {
                    namaInput.value = '';
                    setStatus('', '#64748b');
                    resetDutyDisplay();
                    resetPreview();
                    stopCamera();
                    setScheduled(false, '');
                    submit.innerHTML = '<span class="material-symbols-outlined">send</span> Kirim absensi';
                    fileInput.value = '';
                    cameraFallback.value = '';
                    fileLabel.style.color = '';
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
