<!DOCTYPE html>
<html lang="id">
<head>
   <meta charset="utf-8"/>
   <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
   <meta name="csrf-token" content="{{ csrf_token() }}"/>
   <title>Pengajuan Bukti Treatment — PT Berau Coal</title>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
   <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
   <style>
      :root {
         --brand: #3952bc;
         --brand-dark: #2b45af;
         --brand-soft: rgba(57, 82, 188, 0.08);
         --ok: #059669;
         --danger: #dc2626;
         --ink: #1e293b;
         --muted: #64748b;
         --line: #e2e8f0;
         --surface: #ffffff;
         --bg: linear-gradient(160deg, #eef2ff 0%, #f8fafc 45%, #f1f5f9 100%);
      }
      * { box-sizing: border-box; }
      body {
         margin: 0;
         min-height: 100dvh;
         font-family: 'Inter', system-ui, sans-serif;
         color: var(--ink);
         background: var(--bg);
         -webkit-font-smoothing: antialiased;
      }
      .material-symbols-outlined {
         font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24;
         vertical-align: middle;
      }
      .wrap { max-width: 680px; margin: 0 auto; padding: 1.25rem 1rem 3rem; }
      .hero {
         border-radius: 20px;
         overflow: hidden;
         background: var(--surface);
         box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 16px 40px -12px rgba(57,82,188,.18);
         margin-bottom: 1rem;
      }
      .hero-top {
         height: 6px;
         background: linear-gradient(90deg, var(--brand), #72479e);
      }
      .hero-body { padding: 1.75rem 1.5rem 1.5rem; }
      .badge {
         display: inline-flex; align-items: center; gap: .35rem;
         font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
         color: var(--brand); background: var(--brand-soft); border-radius: 999px; padding: .35rem .75rem;
      }
      h1 { margin: .85rem 0 0; font-size: clamp(1.5rem, 4vw, 1.85rem); line-height: 1.2; font-weight: 800; }
      .lead { margin: .65rem 0 0; color: var(--muted); font-size: .95rem; line-height: 1.55; }
      .steps {
         display: flex; gap: .5rem; margin: 1.25rem 0 1rem;
      }
      .step-dot {
         flex: 1; height: 4px; border-radius: 999px; background: #e2e8f0; transition: background .35s ease;
      }
      .step-dot.is-active, .step-dot.is-done { background: var(--brand); }
      .card {
         background: var(--surface);
         border: 1px solid var(--line);
         border-radius: 18px;
         padding: 1.35rem 1.25rem;
         box-shadow: 0 8px 24px -16px rgba(15,23,42,.12);
         margin-bottom: .85rem;
      }
      .card-title {
         display: flex; align-items: center; gap: .5rem;
         font-size: .95rem; font-weight: 700; margin: 0 0 1rem;
      }
      .num {
         width: 1.65rem; height: 1.65rem; border-radius: 999px;
         display: inline-flex; align-items: center; justify-content: center;
         background: var(--brand-soft); color: var(--brand); font-size: .75rem; font-weight: 800;
      }
      label { display: block; font-size: .82rem; font-weight: 600; margin-bottom: .4rem; color: #334155; }
      .req { color: var(--danger); }
      .hint { font-size: .78rem; color: var(--muted); margin-top: .35rem; line-height: 1.45; }
      .field { margin-bottom: 1rem; }
      .input, .textarea {
         width: 100%; border: 1.5px solid var(--line); border-radius: 12px;
         background: #fafbfc; padding: .85rem 1rem; font: inherit; font-size: .95rem;
         transition: border-color .2s, box-shadow .2s;
      }
      .input:focus, .textarea:focus {
         outline: none; border-color: rgba(57,82,188,.45);
         box-shadow: 0 0 0 4px rgba(57,82,188,.12); background: #fff;
      }
      .input-mono { font-family: ui-monospace, monospace; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
      .textarea { min-height: 120px; resize: vertical; }
      .row { display: flex; gap: .65rem; align-items: stretch; }
      .row .input { flex: 1; }
      .btn {
         border: 0; border-radius: 12px; cursor: pointer; font: inherit; font-weight: 700;
         display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
         transition: transform .15s ease, opacity .15s ease;
      }
      .btn:active { transform: scale(.98); }
      .btn-primary {
         background: linear-gradient(135deg, var(--brand), var(--brand-dark));
         color: #fff; padding: .9rem 1.25rem; box-shadow: 0 10px 24px -8px rgba(57,82,188,.55);
      }
      .btn-secondary {
         background: #f1f5f9; color: #475569; padding: .85rem 1rem; border: 1px solid var(--line);
      }
      .btn-block { width: 100%; }
      .alert {
         border-radius: 12px; padding: .85rem 1rem; font-size: .88rem; line-height: 1.45; margin-bottom: 1rem;
      }
      .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
      .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
      .preview {
         display: none; border-radius: 14px; background: #f8fafc; border: 1px dashed #cbd5e1;
         padding: 1rem; margin-top: .75rem;
      }
      .preview.is-visible { display: block; }
      .preview-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .65rem; font-size: .86rem; }
      .preview-grid span { display: block; color: var(--muted); font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
      .preview-grid strong { display: block; margin-top: .15rem; font-weight: 600; color: var(--ink); }
      .dropzone {
         border: 2px dashed #cbd5e1; border-radius: 16px; background: #fafbfc;
         padding: 1.5rem 1rem; text-align: center; cursor: pointer; transition: .2s;
      }
      .dropzone.is-dragover { border-color: var(--brand); background: var(--brand-soft); }
      .dropzone.has-file { border-style: solid; border-color: rgba(5,150,105,.35); background: #ecfdf5; }
      .dropzone-icon { font-size: 2rem; color: var(--brand); }
      .dropzone-title { margin: .5rem 0 0; font-weight: 700; font-size: .95rem; }
      .dropzone-sub { margin: .25rem 0 0; color: var(--muted); font-size: .82rem; }
      .file-name { margin-top: .65rem; font-size: .82rem; font-weight: 600; color: var(--ok); word-break: break-all; }
      .footer-note { text-align: center; color: var(--muted); font-size: .78rem; margin-top: 1.25rem; line-height: 1.5; }
      .hp { position: absolute; left: -9999px; opacity: 0; height: 0; width: 0; overflow: hidden; }
      .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
      .period-pill {
         display: inline-flex; align-items: center; gap: .35rem; margin-top: .75rem;
         padding: .4rem .75rem; border-radius: 999px; background: #f1f5f9; font-size: .78rem; font-weight: 600; color: #475569;
      }
      @media (max-width: 520px) {
         .preview-grid { grid-template-columns: 1fr; }
         .row { flex-direction: column; }
      }
   </style>
</head>
<body>
@php
   $acceptAttr = implode(',', array_map(static fn (string $m): string => '.'.$m, $allowedMimes));
   $periodLabel = trim(($period['week'] ?? '').' · '.($period['year'] ?? ''), ' ·');
@endphp

<div class="wrap">
   <div class="hero">
      <div class="hero-top"></div>
      <div class="hero-body">
         <span class="badge">
            <span class="material-symbols-outlined" style="font-size:15px">lock_open</span>
            Tanpa login
         </span>
         <h1>Form Bukti Treatment Banned</h1>
         <p class="lead">Isi formulir ini jika SID Anda terbanned dan sudah melakukan perbaikan. Lampirkan bukti (foto/dokumen) agar tim Safety dapat memproses.</p>
         @if($periodLabel !== '')
         <div class="period-pill">
            <span class="material-symbols-outlined" style="font-size:16px">calendar_month</span>
          
         </div>
         @endif
      </div>
   </div>

   <div class="steps" aria-hidden="true">
      <div class="step-dot is-active" id="step-dot-1"></div>
      <div class="step-dot" id="step-dot-2"></div>
      <div class="step-dot" id="step-dot-3"></div>
   </div>

   @if($errors->any())
   <div class="alert alert-error">
      <strong>Periksa kembali:</strong>
      <ul style="margin:.5rem 0 0 1.1rem;padding:0">
         @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
   </div>
   @endif

   <form id="treatment-form" method="POST" action="{{ route('auto-banned.public.treatment.store') }}" enctype="multipart/form-data" novalidate>
      @csrf
      <input type="hidden" name="week" value="{{ old('week', $period['week'] ?? '') }}"/>
      <input type="hidden" name="year" value="{{ old('year', $period['year'] ?? '') }}"/>
      <div class="hp" aria-hidden="true">
         <label for="website">Website</label>
         <input type="text" name="website" id="website" tabindex="-1" autocomplete="off"/>
      </div>

      {{-- Step 1: SID --}}
      <section class="card" id="section-sid">
         <h2 class="card-title"><span class="num">1</span> Cari SID Anda</h2>
         <div class="field">
            <label for="sid">Nomor SID <span class="req">*</span></label>
            <div class="row">
               <input class="input input-mono" type="text" id="sid" name="sid" value="{{ old('sid', $prefillSid) }}" placeholder="Contoh: U8WAP" required maxlength="64" autocomplete="off"/>
               <button type="button" class="btn btn-secondary" id="btn-lookup" style="white-space:nowrap">
                  <span class="material-symbols-outlined">search</span>
                  Cari
               </button>
            </div>
            <p class="hint">Ketik SID persis seperti di kartu identitas / SAP, lalu tekan <strong>Cari</strong>.</p>
            <p id="lookup-msg" class="hint" style="margin-top:.5rem"></p>
         </div>
         <div id="sid-preview" class="preview {{ old('sid') ? 'is-visible' : '' }}">
            <div class="preview-grid">
               <div><span>Nama</span><strong id="pv-karyawan">—</strong></div>
               <div><span>Perusahaan</span><strong id="pv-perusahaan">—</strong></div>
               <div><span>Site</span><strong id="pv-site">—</strong></div>
               <div><span>Alasan Banned</span><strong id="pv-reason">—</strong></div>
            </div>
         </div>
         <div class="field" id="banned-log-wrap" style="display:none;margin-top:1rem;margin-bottom:0">
            <label for="banned_log_ref">Riwayat Banned <span class="req">*</span></label>
            <select class="input" id="banned_log_ref" name="banned_log_ref">
               <option value="">— Pilih setelah cari SID —</option>
            </select>
            <p class="hint">Pilih riwayat banned harian atau mingguan (automasi SUCCESS) yang ingin diajukan unban.</p>
         </div>
      </section>

      {{-- Step 2: Ringkasan perbaikan --}}
      <section class="card" id="section-perbaikan">
         <h2 class="card-title"><span class="num">2</span> Perbaikan yang Dilakukan</h2>
         <div class="field">
            <label for="no_hp">Nomor HP / WhatsApp <span class="req">*</span></label>
            <input class="input" type="tel" id="no_hp" name="no_hp" value="{{ old('no_hp') }}" placeholder="Contoh: 081234567890" required maxlength="32" inputmode="tel" autocomplete="tel"/>
            <p class="hint">Nomor aktif untuk dihubungi terkait pengajuan ini.</p>
         </div>
         <div class="field" style="margin-bottom:0">
            <label for="alasan_pengajuan">Apa yang sudah diperbaiki? <span class="req">*</span></label>
            <textarea class="textarea" id="alasan_pengajuan" name="alasan_pengajuan" placeholder="Contoh: Sudah koordinasi dengan HSE, SAP sudah diperbaiki, lampiran screenshot SAP terbaru." required maxlength="2000">{{ old('alasan_pengajuan') }}</textarea>
            <p class="hint">Jelaskan singkat tindakan perbaikan yang sudah dilakukan.</p>
         </div>
      </section>

      {{-- Step 3: Upload --}}
      <section class="card" id="section-upload">
         <h2 class="card-title"><span class="num">3</span> Upload Bukti (Evidence)</h2>
         <div class="alert alert-info" style="margin-top:0">
            <span class="material-symbols-outlined" style="font-size:18px;vertical-align:-4px">info</span>
            Bisa foto (JPG/PNG), PDF, Word, atau Excel. Maksimal <strong>{{ $maxUploadMb }} MB</strong>.
         </div>
         <div class="field">
            <input type="file" id="evidence_file" name="evidence_file" accept="{{ $acceptAttr }},image/*,application/pdf" class="sr-only"/>
            <div class="dropzone" id="dropzone" role="button" tabindex="0" aria-controls="evidence_file">
               <div class="dropzone-icon material-symbols-outlined">cloud_upload</div>
               <p class="dropzone-title">Ketuk untuk pilih file</p>
               <p class="dropzone-sub">atau seret & lepas file ke sini</p>
               <p class="file-name" id="file-name"></p>
            </div>
         </div>
         <button type="submit" class="btn btn-primary btn-block" id="btn-submit">
            <span class="material-symbols-outlined">send</span>
            Kirim Bukti Treatment
         </button>
      </section>
   </form>

   <p class="footer-note">
      PT Berau Coal · Safety App<br/>
      Form ini aman — data hanya digunakan untuk verifikasi treatment banned.
   </p>
</div>

<script>
(function () {
   var lookupUrl = @json(route('auto-banned.public.treatment.lookup-sid'));
   var week = document.querySelector('input[name="week"]').value;
   var year = document.querySelector('input[name="year"]').value;
   var maxUploadBytes = @json((int) config('auto_banned.treatment.max_upload_kb', 10240) * 1024);
   var maxUploadMb = @json((int) ceil(((int) config('auto_banned.treatment.max_upload_kb', 10240)) / 1024));

   var sidInput = document.getElementById('sid');
   var lookupBtn = document.getElementById('btn-lookup');
   var lookupMsg = document.getElementById('lookup-msg');
   var preview = document.getElementById('sid-preview');
   var bannedLogWrap = document.getElementById('banned-log-wrap');
   var bannedLogSelect = document.getElementById('banned_log_ref');
   var oldBannedLogRef = @json(old('banned_log_ref'));
   var dropzone = document.getElementById('dropzone');
   var fileInput = document.getElementById('evidence_file');
   var fileNameEl = document.getElementById('file-name');
   var form = document.getElementById('treatment-form');
   var submitBtn = document.getElementById('btn-submit');
   var canSubmitTreatment = true;

   function setSubmitEnabled(enabled) {
      canSubmitTreatment = enabled;
      if (!submitBtn) return;
      submitBtn.disabled = !enabled;
      submitBtn.style.opacity = enabled ? '' : '0.55';
      submitBtn.style.cursor = enabled ? '' : 'not-allowed';
   }

   function setBannedLogOptions(options) {
      if (!bannedLogWrap || !bannedLogSelect) return;
      bannedLogSelect.innerHTML = '<option value="">— Pilih riwayat banned —</option>';
      if (!options || !options.length) {
         bannedLogWrap.style.display = 'none';
         bannedLogSelect.removeAttribute('required');
         return;
      }
      options.forEach(function (opt) {
         var option = document.createElement('option');
         option.value = String(opt.value || ((opt.type || 'daily') + '-' + opt.id));
         option.textContent = opt.label;
         bannedLogSelect.appendChild(option);
      });
      bannedLogWrap.style.display = 'block';
      bannedLogSelect.setAttribute('required', 'required');
      if (oldBannedLogRef) bannedLogSelect.value = String(oldBannedLogRef);
   }

   function setPreview(data) {
      document.getElementById('pv-karyawan').textContent = data.karyawan || '—';
      document.getElementById('pv-perusahaan').textContent = data.perusahaan || '—';
      document.getElementById('pv-site').textContent = data.site_dedicated || '—';
      document.getElementById('pv-reason').textContent = data.banned_reason || '—';
      preview.classList.add('is-visible');
      document.getElementById('step-dot-1').classList.add('is-done');
      document.getElementById('step-dot-2').classList.add('is-active');
   }

   function lookupSid() {
      var sid = sidInput.value.trim();
      if (!sid) {
         lookupMsg.textContent = 'Masukkan SID terlebih dahulu.';
         lookupMsg.style.color = '#dc2626';
         return;
      }
      lookupMsg.textContent = 'Mencari data…';
      lookupMsg.style.color = '#64748b';
      lookupBtn.disabled = true;

      fetch(lookupUrl + '?' + new URLSearchParams({ sid: sid, week: week, year: year }), {
         headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
         .then(function (r) { return r.json(); })
         .then(function (payload) {
            lookupBtn.disabled = false;
            if (!payload.found) {
               lookupMsg.textContent = payload.message || 'SID tidak ditemukan.';
               lookupMsg.style.color = '#dc2626';
               preview.classList.remove('is-visible');
               setBannedLogOptions([]);
               setSubmitEnabled(false);
               return;
            }
            lookupMsg.textContent = payload.message || 'Data ditemukan ✓';
            lookupMsg.style.color = payload.can_submit === false ? '#b45309' : '#059669';
            setPreview(payload.data || {});
            setBannedLogOptions(payload.banned_log_options || []);
            setSubmitEnabled(payload.can_submit !== false);
         })
         .catch(function () {
            lookupBtn.disabled = false;
            lookupMsg.textContent = 'Koneksi gagal. Periksa internet lalu coba lagi.';
            lookupMsg.style.color = '#dc2626';
         });
   }

   lookupBtn.addEventListener('click', lookupSid);
   sidInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); lookupSid(); }
   });

   if (sidInput.value.trim()) lookupSid();

   dropzone.addEventListener('click', function () { fileInput.click(); });
   dropzone.addEventListener('dragover', function (e) {
      e.preventDefault();
      dropzone.classList.add('is-dragover');
   });
   dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('is-dragover'); });
   dropzone.addEventListener('drop', function (e) {
      e.preventDefault();
      dropzone.classList.remove('is-dragover');
      if (e.dataTransfer.files.length) {
         fileInput.files = e.dataTransfer.files;
         showFileName();
      }
   });
   fileInput.addEventListener('change', showFileName);

   function showFileName() {
      if (fileInput.files && fileInput.files[0]) {
         var file = fileInput.files[0];
         if (file.size > maxUploadBytes) {
            fileInput.value = '';
            fileNameEl.textContent = '';
            dropzone.classList.remove('has-file');
            alert('Ukuran file terlalu besar (' + (file.size / 1024 / 1024).toFixed(1) + ' MB). Maksimal ' + maxUploadMb + ' MB.');
            return;
         }
         fileNameEl.textContent = '✓ ' + file.name;
         dropzone.classList.add('has-file');
         document.getElementById('step-dot-2').classList.add('is-done');
         document.getElementById('step-dot-3').classList.add('is-active');
      }
   }

   form.addEventListener('submit', function (e) {
      if (!canSubmitTreatment) {
         e.preventDefault();
         alert('SID ini belum memiliki riwayat banned berstatus SUCCESS yang dapat diajukan.');
         return;
      }
      if (!fileInput.files || !fileInput.files.length) {
         e.preventDefault();
         alert('Pilih file bukti terlebih dahulu.');
         return;
      }
      if (fileInput.files[0].size > maxUploadBytes) {
         e.preventDefault();
         alert('Ukuran file terlalu besar. Maksimal ' + maxUploadMb + ' MB.');
         return;
      }
      if (bannedLogSelect && bannedLogSelect.hasAttribute('required') && !bannedLogSelect.value) {
         e.preventDefault();
         alert('Pilih riwayat banned terlebih dahulu.');
         return;
      }
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="material-symbols-outlined">hourglass_top</span> Mengirim…';
   });
})();
</script>
</body>
</html>
