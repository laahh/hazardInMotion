@extends('control-room.layouts.bare')

@section('page-title', 'Absensi')

@section('content')
    <main class="ocr-gf">
        <form
            class="ocr-gf-form"
            method="POST"
            action="{{ route('control-room.attendance.form.store') }}"
            enctype="multipart/form-data"
            id="ocr-absensi-form"
        >
            @csrf

            <section class="ocr-gf-card ocr-gf-card--title" aria-labelledby="ocr-gf-title">
                <div class="ocr-gf-accent" aria-hidden="true"></div>
                <h1 id="ocr-gf-title">Absensi</h1>
                <p class="ocr-gf-desc">Formulir kehadiran Control Room (Pengawasan OCR). Isi SID, pilih tanggal, lalu unggah bukti.</p>
                <p class="ocr-gf-meta">* Menunjukkan pertanyaan yang wajib diisi</p>
            </section>

            @if (session('success'))
                <div class="ocr-gf-card ocr-gf-banner is-success" role="status">
                    <strong>Respons tersimpan.</strong>
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="ocr-gf-card ocr-gf-banner is-error" role="alert" tabindex="-1" id="ocr-gf-error-summary">
                    <strong>Ada masalah pada formulir</strong>
                    <ul>
                        @foreach ($errors->keys() as $key)
                            <li><a href="#{{ $key }}">{{ $errors->first($key) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="ocr-gf-card{{ $errors->has('sid') ? ' is-invalid' : '' }}">
                <label class="ocr-gf-question" for="sid">
                    SID <span class="ocr-gf-req" aria-hidden="true">*</span>
                </label>
                <p class="ocr-gf-help" id="sid-help">Ketik kode SID personil yang hadir.</p>
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
                    placeholder="Jawaban Anda"
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
                <label class="ocr-gf-question" for="tanggal">
                    Tanggal <span class="ocr-gf-req" aria-hidden="true">*</span>
                </label>
                <p class="ocr-gf-help" id="tanggal-help">Tanggal kehadiran yang dicatat.</p>
                <input
                    id="tanggal"
                    name="tanggal"
                    type="date"
                    value="{{ old('tanggal', $defaultTanggal) }}"
                    max="{{ $defaultTanggal }}"
                    class="ocr-gf-input ocr-gf-input--date{{ $errors->has('tanggal') ? ' is-invalid' : '' }}"
                    required
                    aria-required="true"
                    aria-describedby="tanggal-help{{ $errors->has('tanggal') ? ' tanggal-error' : '' }}"
                >
                @error('tanggal')
                    <p class="ocr-gf-error" id="tanggal-error"><i class="ri-error-warning-fill" aria-hidden="true"></i> {{ $message }}</p>
                @enderror
            </section>

            <section class="ocr-gf-card{{ $errors->has('bukti') ? ' is-invalid' : '' }}">
                <p class="ocr-gf-question" id="bukti-label">
                    Bukti <span class="ocr-gf-req" aria-hidden="true">*</span>
                </p>
                <p class="ocr-gf-help" id="bukti-help">Unggah foto atau dokumen kehadiran. JPG, PNG, WEBP, atau PDF — maksimal 5 MB.</p>
                <label class="ocr-gf-file" for="bukti">
                    <input
                        id="bukti"
                        name="bukti"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        capture="environment"
                        required
                        aria-required="true"
                        aria-labelledby="bukti-label"
                        aria-describedby="bukti-help{{ $errors->has('bukti') ? ' bukti-error' : '' }}"
                    >
                    <span class="ocr-gf-file-btn" aria-hidden="true"><i class="ri-add-line"></i> Tambahkan file</span>
                    <span class="ocr-gf-file-name" id="ocr-absensi-file-label">Belum ada file dipilih</span>
                </label>
                <img id="ocr-absensi-preview" class="ocr-gf-preview" alt="Pratinjau bukti" hidden>
                @error('bukti')
                    <p class="ocr-gf-error" id="bukti-error"><i class="ri-error-warning-fill" aria-hidden="true"></i> {{ $message }}</p>
                @enderror
            </section>

            <div class="ocr-gf-actions">
                <button type="submit" class="ocr-gf-submit" id="ocr-absensi-submit">Kirim</button>
                <button type="reset" class="ocr-gf-clear" id="ocr-absensi-clear">Hapus formulir</button>
            </div>

            <p class="ocr-gf-footnote">Jangan kirim sandi melalui formulir ini.</p>
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
            var clearBtn = document.getElementById('ocr-absensi-clear');
            var defaultDate = @json($defaultTanggal);
            var lookupUrl = @json($lookupUrl);
            var sidInput = document.getElementById('sid');
            var namaInput = document.getElementById('nama');
            var sidStatus = document.getElementById('sid-status');
            var lookupTimer = null;
            var lookupSeq = 0;

            function setStatus(text, tone) {
                sidStatus.textContent = text || '';
                sidStatus.className = 'ocr-gf-status' + (tone ? ' ' + tone : '');
            }

            function clearNama() {
                namaInput.value = '';
            }

            function lookupSid() {
                var sid = (sidInput.value || '').trim().toUpperCase();
                sidInput.value = sid;

                if (sid.length < 2) {
                    clearNama();
                    setStatus(sid.length ? 'Lanjutkan mengetik SID...' : '', sid.length ? 'is-wait' : '');
                    return;
                }

                var seq = ++lookupSeq;
                setStatus('Mencari nama...', 'is-wait');

                fetch(lookupUrl + '?sid=' + encodeURIComponent(sid), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (seq !== lookupSeq) {
                            return;
                        }
                        if (data && data.found) {
                            namaInput.value = data.name || '';
                            setStatus('Nama ditemukan.', 'is-ok');
                            return;
                        }
                        clearNama();
                        setStatus('SID tidak ditemukan atau personil tidak aktif.', 'is-miss');
                    })
                    .catch(function () {
                        if (seq !== lookupSeq) {
                            return;
                        }
                        clearNama();
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

            if ((sidInput.value || '').trim().length >= 2) {
                lookupSid();
            }
            var summary = document.getElementById('ocr-gf-error-summary');

            function resetPreview() {
                label.textContent = 'Belum ada file dipilih';
                preview.hidden = true;
                preview.removeAttribute('src');
            }

            input.addEventListener('change', function () {
                var file = input.files && input.files[0];
                if (!file) {
                    resetPreview();
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
                submit.textContent = 'Mengirim...';
            });

            clearBtn.addEventListener('click', function () {
                window.setTimeout(function () {
                    document.getElementById('tanggal').value = defaultDate;
                    namaInput.value = '';
                    setStatus('', '');
                    resetPreview();
                    submit.disabled = false;
                    submit.textContent = 'Kirim';
                }, 0);
            });

            if (summary) {
                summary.focus();
            }
        })();
    </script>
@endpush
