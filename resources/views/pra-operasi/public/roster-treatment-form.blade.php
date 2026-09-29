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
         margin: 0; min-height: 100dvh; font-family: 'Inter', system-ui, sans-serif;
         color: var(--ink); background: var(--bg); -webkit-font-smoothing: antialiased;
      }
      .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
      .wrap { max-width: 640px; margin: 0 auto; padding: 1.25rem 1rem 3rem; }
      .hero { border-radius: 20px; overflow: hidden; background: var(--surface); box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 16px 40px -12px rgba(57,82,188,.18); margin-bottom: 1rem; }
      .hero-top { height: 6px; background: linear-gradient(90deg, var(--brand), #72479e); }
      .hero-body { padding: 1.6rem 1.4rem 1.35rem; }
      .badge { display: inline-flex; align-items: center; gap: .35rem; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--brand); background: var(--brand-soft); border-radius: 999px; padding: .35rem .75rem; }
      h1 { margin: .8rem 0 0; font-size: clamp(1.35rem, 4vw, 1.6rem); line-height: 1.2; font-weight: 800; }
      .lead { margin: .6rem 0 0; color: var(--muted); font-size: .92rem; line-height: 1.55; }
      .card { background: var(--surface); border: 1px solid var(--line); border-radius: 18px; padding: 1.3rem 1.2rem; box-shadow: 0 8px 24px -16px rgba(15,23,42,.12); margin-bottom: .85rem; }
      .card-title { display: flex; align-items: center; gap: .5rem; font-size: .93rem; font-weight: 700; margin: 0 0 1rem; }
      .num { width: 1.6rem; height: 1.6rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: var(--brand-soft); color: var(--brand); font-size: .74rem; font-weight: 800; flex-shrink: 0; }
      label { display: block; font-size: .82rem; font-weight: 600; margin-bottom: .4rem; color: #334155; }
      .req { color: var(--danger); }
      .hint { font-size: .78rem; color: var(--muted); margin-top: .35rem; line-height: 1.45; }
      .field { margin-bottom: 1rem; }
      .input, .textarea { width: 100%; border: 1.5px solid var(--line); border-radius: 12px; background: #fafbfc; padding: .8rem .95rem; font: inherit; font-size: .93rem; transition: border-color .2s, box-shadow .2s; }
      .input:focus, .textarea:focus { outline: none; border-color: rgba(57,82,188,.45); box-shadow: 0 0 0 3px var(--brand-soft); background: #fff; }
      .textarea { resize: vertical; min-height: 4.5rem; }
      .search-row { display: flex; gap: .5rem; }
      .search-row .input { flex: 1; }
      .btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; border: 0; border-radius: 12px; font-weight: 700; font-size: .9rem; cursor: pointer; text-decoration: none; }
      .btn-primary { background: linear-gradient(135deg, var(--brand), var(--brand-dark)); color: #fff; padding: .85rem 1.1rem; }
      .btn-primary:disabled { opacity: .55; cursor: not-allowed; }
      .btn-search { background: var(--ink); color: #fff; padding: 0 1.1rem; }
      .btn-block { width: 100%; padding: .95rem 1.1rem; margin-top: .25rem; }
      .lookup-msg { font-size: .85rem; margin-top: .6rem; padding: .65rem .8rem; border-radius: 10px; display: none; }
      .lookup-msg.is-error { display: block; background: #fef2f2; color: var(--danger); }
      .lookup-msg.is-info { display: block; background: #eff6ff; color: #1d4ed8; }
      .option-list { margin-top: .75rem; display: flex; flex-direction: column; gap: .5rem; }
      .option-item { border: 1.5px solid var(--line); border-radius: 12px; padding: .75rem .85rem; cursor: pointer; transition: border-color .15s, background .15s; }
      .option-item:hover { border-color: rgba(57,82,188,.4); }
      .option-item.is-selected { border-color: var(--brand); background: var(--brand-soft); }
      .option-item .o-nama { font-weight: 700; font-size: .9rem; }
      .option-item .o-meta { font-size: .78rem; color: var(--muted); margin-top: .15rem; }
      .option-item .o-alasan { font-size: .8rem; color: #334155; margin-top: .35rem; }
      .form-section { display: none; }
      .form-section.is-visible { display: block; }
      .file-input-wrap { border: 1.5px dashed var(--line); border-radius: 12px; padding: 1rem; text-align: center; background: #fafbfc; }
      .file-input-wrap input[type=file] { width: 100%; }
      .error-text { color: var(--danger); font-size: .78rem; margin-top: .35rem; }
      .footer-note { text-align: center; font-size: .78rem; color: var(--muted); margin-top: 1rem; }
      .website-field { position: absolute; left: -9999px; top: -9999px; }
   </style>
</head>
<body>
<div class="wrap">
   <div class="hero">
      <div class="hero-top"></div>
      <div class="hero-body">
         <span class="badge"><span class="material-symbols-outlined" style="font-size:14px">shield</span> Roster Banned</span>
         <h1>Pengajuan Bukti Treatment</h1>
         <p class="lead">
            Upload bukti tindakan perbaikan (treatment) atas pelanggaran yang tercatat.
            @if ($periodLabel)
               Periode: <strong>{{ $periodLabel }}</strong>.
            @endif
            Pengajuan akan direview oleh tim admin.
         </p>
      </div>
   </div>

   @if ($errors->any())
   <div class="card" style="border-color:#fecaca;background:#fef2f2;">
      <div style="font-weight:700;color:var(--danger);margin-bottom:.4rem;">Periksa kembali isian Anda:</div>
      <ul style="margin:0;padding-left:1.1rem;color:var(--danger);font-size:.85rem;">
         @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
         @endforeach
      </ul>
   </div>
   @endif

   <div class="card">
      <div class="card-title"><span class="num">1</span> Cari data pelanggaran Anda</div>
      <label for="rt-search">NIK atau SID <span class="req">*</span></label>
      <div class="search-row">
         <input type="text" id="rt-search" class="input" placeholder="Ketik NIK atau SID..." value="{{ $prefillQuery }}" autocomplete="off">
         <button type="button" id="rt-search-btn" class="btn btn-search">
            <span class="material-symbols-outlined" style="font-size:18px">search</span>
         </button>
      </div>
      <div id="rt-lookup-msg" class="lookup-msg"></div>
      <div id="rt-options" class="option-list"></div>
   </div>

   <form id="rt-form" action="{{ route('roster-treatment.public.store') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="master_id" id="rt-master-id" value="{{ old('master_id') }}">
      <div class="website-field" aria-hidden="true">
         <label for="website">Website</label>
         <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
      </div>

      <div id="rt-form-section" class="form-section {{ old('master_id') ? 'is-visible' : '' }}">
         <div class="card">
            <div class="card-title"><span class="num">2</span> Detail treatment</div>

            <div class="field">
               <label for="submitted_by">Nama Pengaju <span class="req">*</span></label>
               <input type="text" class="input" id="submitted_by" name="submitted_by" value="{{ old('submitted_by') }}" maxlength="100" required>
            </div>

            <div class="field">
               <label for="whatsapp">Nomor WhatsApp <span class="req">*</span></label>
               <input type="tel" class="input" id="whatsapp" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="08xxxxxxxxxx" maxlength="20" inputmode="numeric" required>
               <div class="hint">Dipakai untuk mengirim notifikasi kalau pengajuan Anda ditolak.</div>
            </div>

            <div class="field">
               <label for="tanggal_treatment">Tanggal Treatment</label>
               <input type="date" class="input" id="tanggal_treatment" name="tanggal_treatment" value="{{ old('tanggal_treatment') }}">
            </div>

            <div class="field">
               <label for="periode_cuti_mulai">Tanggal Periode Cuti</label>
               <input type="date" class="input" id="periode_cuti_mulai" name="periode_cuti_mulai" value="{{ old('periode_cuti_mulai') }}">
               <div class="hint" id="periode-cuti-preview">Pilih tanggal mulai — periode cuti 14 hari akan otomatis dihitung.</div>
            </div>

            <div class="field">
               <label for="catatan">Catatan</label>
               <textarea class="textarea" id="catatan" name="catatan" maxlength="500" placeholder="Jelaskan singkat tindakan perbaikan yang sudah dilakukan...">{{ old('catatan') }}</textarea>
            </div>

            <div class="field">
               <label for="evidence_file">File Bukti Treatment <span class="req">*</span></label>
               <div class="file-input-wrap">
                  <input type="file" id="evidence_file" name="evidence_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx,.xls" required>
               </div>
               <div class="hint">Format: PDF, JPG/PNG, Word, atau Excel. Maks. 10 MB.</div>
            </div>
         </div>

         <button type="submit" id="rt-submit-btn" class="btn btn-primary btn-block">
            <span class="material-symbols-outlined" style="font-size:18px">upload</span>
            Kirim Pengajuan
         </button>
      </div>
   </form>

   <p class="footer-note">PT Berau Coal &middot; Modul Roster Banned</p>
</div>

<script>
(function () {
    var searchInput = document.getElementById('rt-search');
    var searchBtn = document.getElementById('rt-search-btn');
    var lookupMsg = document.getElementById('rt-lookup-msg');
    var optionsEl = document.getElementById('rt-options');
    var masterIdInput = document.getElementById('rt-master-id');
    var formSection = document.getElementById('rt-form-section');
    var lookupUrl = @json(route('roster-treatment.public.lookup'));

    var CUTI_DAYS = 14;
    var MONTHS_ID = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    var periodeMulaiInput = document.getElementById('periode_cuti_mulai');
    var periodePreview = document.getElementById('periode-cuti-preview');

    function formatTanggalId(date) {
        return date.getDate() + ' ' + MONTHS_ID[date.getMonth()] + ' ' + date.getFullYear();
    }

    function updatePeriodePreview() {
        if (!periodeMulaiInput.value) {
            periodePreview.textContent = 'Pilih tanggal mulai — periode cuti 14 hari akan otomatis dihitung.';
            return;
        }
        var start = new Date(periodeMulaiInput.value + 'T00:00:00');
        var end = new Date(start);
        end.setDate(end.getDate() + CUTI_DAYS);
        periodePreview.textContent = 'Periode cuti: ' + formatTanggalId(start) + ' s.d. ' + formatTanggalId(end) + ' (' + CUTI_DAYS + ' hari).';
    }

    periodeMulaiInput.addEventListener('change', updatePeriodePreview);
    updatePeriodePreview();

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function showMessage(text, type) {
        lookupMsg.textContent = text;
        lookupMsg.className = 'lookup-msg is-' + type;
    }

    function renderOptions(options) {
        optionsEl.innerHTML = '';
        options.forEach(function (opt) {
            var el = document.createElement('div');
            el.className = 'option-item';
            el.dataset.id = opt.id;
            el.innerHTML = '<div class="o-nama">' + escapeHtml(opt.nama) + ' <span style="color:var(--muted);font-weight:500;">(' + escapeHtml(opt.sid || opt.nik) + ')</span></div>'
                + '<div class="o-meta">' + escapeHtml(opt.perusahaan || '-') + ' &middot; ' + escapeHtml(opt.site_dedicated || '-') + (opt.tanggal_pelanggaran ? ' &middot; ' + escapeHtml(opt.tanggal_pelanggaran) : '') + '</div>'
                + '<div class="o-alasan">' + escapeHtml(opt.alasan_pelanggaran) + '</div>';
            el.addEventListener('click', function () {
                document.querySelectorAll('.option-item').forEach(function (n) { n.classList.remove('is-selected'); });
                el.classList.add('is-selected');
                masterIdInput.value = opt.id;
                formSection.classList.add('is-visible');
                formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            optionsEl.appendChild(el);
        });
    }

    function doLookup() {
        var q = searchInput.value.trim();
        optionsEl.innerHTML = '';
        formSection.classList.remove('is-visible');
        masterIdInput.value = '';

        if (q === '') {
            showMessage('Ketik NIK atau SID Anda, lalu tekan Cari.', 'info');
            return;
        }

        showMessage('Mencari...', 'info');
        searchBtn.disabled = true;

        fetch(lookupUrl + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                searchBtn.disabled = false;
                if (!json.found) {
                    showMessage(json.message || 'Data tidak ditemukan.', 'error');
                    return;
                }
                showMessage(json.message || 'Data ditemukan.', 'info');
                renderOptions(json.options || []);
            })
            .catch(function () {
                searchBtn.disabled = false;
                showMessage('Gagal menghubungi server. Coba lagi.', 'error');
            });
    }

    searchBtn.addEventListener('click', doLookup);
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            doLookup();
        }
    });

    document.getElementById('rt-form').addEventListener('submit', function (e) {
        if (!masterIdInput.value) {
            e.preventDefault();
            showMessage('Cari dan pilih data pelanggaran Anda terlebih dahulu.', 'error');
            searchInput.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        document.getElementById('rt-submit-btn').disabled = true;
    });

    if (searchInput.value.trim() !== '') {
        doLookup();
    }
})();
</script>
</body>
</html>
