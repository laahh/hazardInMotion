<!DOCTYPE html>
<html lang="id">
<head>
   <meta charset="utf-8"/>
   <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
   <meta name="csrf-token" content="{{ csrf_token() }}"/>
   <title>Form Inspeksi Emergency Equipment — PT Berau Coal</title>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
   <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
   <style>
      :root {
         --brand: #b4451f;
         --brand-dark: #93340f;
         --brand-soft: rgba(180, 69, 31, 0.08);
         --ok: #059669;
         --ok-soft: #ecfdf5;
         --warn: #b45309;
         --warn-soft: #fffbeb;
         --danger: #dc2626;
         --danger-soft: #fef2f2;
         --ink: #1e293b;
         --muted: #64748b;
         --line: #e2e8f0;
         --surface: #ffffff;
         --bg: linear-gradient(160deg, #fff7ed 0%, #f8fafc 45%, #f1f5f9 100%);
      }
      * { box-sizing: border-box; }
      body {
         margin: 0; min-height: 100dvh; font-family: 'Inter', system-ui, sans-serif;
         color: var(--ink); background: var(--bg); -webkit-font-smoothing: antialiased;
      }
      .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
      .wrap { max-width: 640px; margin: 0 auto; padding: 1.25rem 1rem 3rem; }
      .hero { border-radius: 20px; overflow: hidden; background: var(--surface); box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 16px 40px -12px rgba(180,69,31,.18); margin-bottom: 1rem; }
      .hero-top { height: 6px; background: linear-gradient(90deg, var(--brand), #d97706); }
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
      .field-pair { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
      .input, .textarea, select.input { width: 100%; border: 1.5px solid var(--line); border-radius: 12px; background: #fafbfc; padding: .8rem .95rem; font: inherit; font-size: .93rem; transition: border-color .2s, box-shadow .2s; }
      .input:focus, .textarea:focus { outline: none; border-color: rgba(180,69,31,.45); box-shadow: 0 0 0 3px var(--brand-soft); background: #fff; }
      .textarea { resize: vertical; min-height: 4.5rem; }
      .search-row { display: flex; gap: .5rem; }
      .search-row .input { flex: 1; }
      .btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; border: 0; border-radius: 12px; font-weight: 700; font-size: .9rem; cursor: pointer; text-decoration: none; }
      .btn-primary { background: linear-gradient(135deg, var(--brand), var(--brand-dark)); color: #fff; padding: .85rem 1.1rem; }
      .btn-primary:disabled { opacity: .55; cursor: not-allowed; }
      .btn-search { background: var(--ink); color: #fff; padding: 0 1.1rem; }
      .btn-ghost { background: #f1f5f9; color: #334155; padding: .5rem .8rem; font-size: .8rem; }
      .btn-block { width: 100%; padding: .95rem 1.1rem; margin-top: .25rem; }
      .lookup-msg { font-size: .85rem; margin-top: .6rem; padding: .65rem .8rem; border-radius: 10px; display: none; }
      .lookup-msg.is-error { display: block; background: var(--danger-soft); color: var(--danger); }
      .lookup-msg.is-info { display: block; background: #eff6ff; color: #1d4ed8; }
      .option-list { margin-top: .75rem; display: flex; flex-direction: column; gap: .5rem; }
      .option-item { border: 1.5px solid var(--line); border-radius: 12px; padding: .75rem .85rem; cursor: pointer; transition: border-color .15s, background .15s; }
      .option-item:hover { border-color: rgba(180,69,31,.4); }
      .option-item.is-selected { border-color: var(--brand); background: var(--brand-soft); }
      .option-item .o-nama { font-weight: 700; font-size: .9rem; }
      .option-item .o-code { font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace; font-size: .76rem; color: var(--brand); margin-top: .2rem; word-break: break-all; }
      .option-item .o-meta { font-size: .78rem; color: var(--muted); margin-top: .25rem; line-height: 1.45; }
      .form-section { display: none; }
      .form-section.is-visible { display: block; }
      .item { border: 1.5px solid var(--line); border-radius: 14px; padding: .95rem; margin-bottom: .7rem; }
      .item.is-flagged { border-color: #fca5a5; background: var(--danger-soft); }
      .item-text { font-weight: 600; font-size: .88rem; line-height: 1.45; margin-bottom: .7rem; }
      .item-no { color: var(--muted); font-weight: 700; margin-right: .2rem; }
      .choices { display: grid; grid-template-columns: repeat(3, 1fr); gap: .4rem; }
      .choice { position: relative; }
      .choice input { position: absolute; opacity: 0; inset: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; }
      .choice span { display: block; text-align: center; padding: .6rem .3rem; border: 1.5px solid var(--line); border-radius: 10px; font-size: .78rem; font-weight: 700; color: var(--muted); background: #fafbfc; transition: all .15s; }
      .choice input:checked + span.c-ok { border-color: var(--ok); background: var(--ok-soft); color: var(--ok); }
      .choice input:checked + span.c-no { border-color: var(--danger); background: var(--danger-soft); color: var(--danger); }
      .choice input:checked + span.c-na { border-color: var(--warn); background: var(--warn-soft); color: var(--warn); }
      .choice input:focus-visible + span { box-shadow: 0 0 0 3px var(--brand-soft); }
      .item-extra { margin-top: .6rem; }
      .item-extra .textarea { min-height: 3rem; font-size: .86rem; }
      .photo-row { margin-top: .5rem; }
      .photo-row input[type=file] { width: 100%; font-size: .82rem; }
      .sig-wrap { border: 1.5px dashed var(--line); border-radius: 12px; background: #fafbfc; padding: .5rem; }
      canvas { width: 100%; height: 160px; touch-action: none; display: block; border-radius: 8px; background: #fff; }
      .geo { font-size: .78rem; color: var(--muted); margin-top: .6rem; display: flex; align-items: center; gap: .3rem; }
      .error-text { color: var(--danger); font-size: .78rem; margin-top: .35rem; }
      .footer-note { text-align: center; font-size: .78rem; color: var(--muted); margin-top: 1rem; }
      .website-field { position: absolute; left: -9999px; top: -9999px; }
      .target-recap { background: #f8fafc; border-radius: 12px; padding: .85rem; font-size: .84rem; }
      .target-recap div { display: flex; justify-content: space-between; gap: 1rem; padding: .25rem 0; }
      .target-recap span { color: var(--muted); }
      .target-recap strong { color: var(--ink); font-weight: 700; text-align: right; word-break: break-word; }
   </style>
</head>
<body>
<div class="wrap">
   <div class="hero">
      <div class="hero-top"></div>
      <div class="hero-body">
         <span class="badge"><span class="material-symbols-outlined" style="font-size:14px">fire_extinguisher</span> Emergency Response</span>
         <h1>Form Inspeksi Peralatan</h1>
         <p class="lead">
            Scan QR di stiker peralatan atau cari UUID/No Registrasi-nya, lalu isi checklist di tempat.
            Jawaban <strong>Tidak Sesuai</strong> otomatis jadi temuan untuk ditindaklanjuti.
         </p>
      </div>
   </div>

   @if ($errors->any())
   <div class="card" style="border-color:#fecaca;background:var(--danger-soft);">
      <div style="font-weight:700;color:var(--danger);margin-bottom:.4rem;">Periksa kembali isian Anda:</div>
      <ul style="margin:0;padding-left:1.1rem;color:var(--danger);font-size:.85rem;">
         @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
         @endforeach
      </ul>
   </div>
   @endif

   <div class="card">
      <div class="card-title"><span class="num">1</span> Cari peralatan yang diinspeksi</div>
      <label for="ins-search">UUID, No Registrasi, atau nama peralatan <span class="req">*</span></label>
      <div class="search-row">
         <input type="text" id="ins-search" class="input" placeholder="mis. PMO-Fire Equipment-Hose-1" value="{{ $prefillQuery }}" autocomplete="off">
         <button type="button" id="ins-search-btn" class="btn btn-search">
            <span class="material-symbols-outlined" style="font-size:18px">search</span>
         </button>
      </div>
      <div id="ins-lookup-msg" class="lookup-msg"></div>
      <div id="ins-options" class="option-list"></div>
   </div>

   <form id="ins-form" action="{{ route('er-inspection.public.store') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="target_type" id="ins-target-type" value="{{ old('target_type') }}">
      <input type="hidden" name="target_id" id="ins-target-id" value="{{ old('target_id') }}">
      <input type="hidden" name="checklist_template_id" id="ins-template-id" value="{{ old('checklist_template_id') }}">
      <input type="hidden" name="latitude" id="ins-lat" value="{{ old('latitude') }}">
      <input type="hidden" name="longitude" id="ins-lng" value="{{ old('longitude') }}">
      <input type="hidden" name="signature_data" id="ins-signature">
      <div class="website-field" aria-hidden="true">
         <label for="website">Website</label>
         <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
      </div>

      <div id="ins-form-section" class="form-section">
         <div class="card">
            <div class="card-title"><span class="num">2</span> Peralatan terpilih</div>
            <div class="target-recap" id="ins-target-recap"></div>
         </div>

         <div class="card">
            <div class="card-title"><span class="num">3</span> Identitas inspector</div>
            <div class="field">
               <label for="inspector_name">Nama Inspector <span class="req">*</span></label>
               <input type="text" class="input" id="inspector_name" name="inspector_name" value="{{ old('inspector_name') }}" maxlength="100" required>
            </div>
            <div class="field-pair">
               <div class="field">
                  <label for="inspector_nik">NIK</label>
                  <input type="text" class="input" id="inspector_nik" name="inspector_nik" value="{{ old('inspector_nik') }}" maxlength="30" inputmode="numeric">
               </div>
               <div class="field">
                  <label for="inspector_sid">SID</label>
                  <input type="text" class="input" id="inspector_sid" name="inspector_sid" value="{{ old('inspector_sid') }}" maxlength="20" autocapitalize="characters" placeholder="mis. P8GTB">
               </div>
            </div>
            <div class="field">
               <label for="inspector_phone">Nomor WhatsApp <span class="req">*</span></label>
               <input type="tel" class="input" id="inspector_phone" name="inspector_phone" value="{{ old('inspector_phone') }}" placeholder="08xxxxxxxxxx" maxlength="20" inputmode="numeric" required>
               <div class="hint">Dipakai admin kalau hasil inspeksi perlu dikonfirmasi.</div>
            </div>
         </div>

         <div class="card">
            <div class="card-title"><span class="num">4</span> Checklist <span id="ins-template-name" style="font-weight:500;color:var(--muted);"></span></div>
            <div id="ins-items"></div>
         </div>

         <div class="card">
            <div class="card-title"><span class="num">5</span> Kesimpulan &amp; tanda tangan</div>
            <div class="field">
               <label for="condition_result">Kondisi Hasil Observasi</label>
               <select class="input" id="condition_result" name="condition_result">
                  <option value="">-- Pilih --</option>
                  @foreach ($conditions as $value => $label)
                     <option value="{{ $value }}" @selected(old('condition_result') === $value)>{{ $label }}</option>
                  @endforeach
               </select>
            </div>
            <div class="field">
               <label for="notes">Catatan Umum</label>
               <textarea class="textarea" id="notes" name="notes" maxlength="1000" placeholder="Catatan tambahan hasil inspeksi...">{{ old('notes') }}</textarea>
            </div>
            <div class="field" style="margin-bottom:.5rem;">
               <label>Tanda Tangan Inspector</label>
               <div class="sig-wrap"><canvas id="ins-sig-pad"></canvas></div>
               <button type="button" id="ins-sig-clear" class="btn btn-ghost" style="margin-top:.5rem;">
                  <span class="material-symbols-outlined" style="font-size:16px">backspace</span> Hapus
               </button>
            </div>
            <div class="geo">
               <span class="material-symbols-outlined" style="font-size:16px">my_location</span>
               <span id="ins-geo-status">Lokasi GPS: mendeteksi...</span>
            </div>
         </div>

         <button type="submit" id="ins-submit-btn" class="btn btn-primary btn-block">
            <span class="material-symbols-outlined" style="font-size:18px">task_alt</span>
            Kirim Hasil Inspeksi
         </button>
      </div>
   </form>

   <p class="footer-note">PT Berau Coal &middot; Modul Emergency Response</p>
</div>

<script>
(function () {
    var searchInput = document.getElementById('ins-search');
    var searchBtn = document.getElementById('ins-search-btn');
    var lookupMsg = document.getElementById('ins-lookup-msg');
    var optionsEl = document.getElementById('ins-options');
    var formSection = document.getElementById('ins-form-section');
    var itemsEl = document.getElementById('ins-items');
    var recapEl = document.getElementById('ins-target-recap');
    var templateNameEl = document.getElementById('ins-template-name');
    var targetTypeInput = document.getElementById('ins-target-type');
    var targetIdInput = document.getElementById('ins-target-id');
    var templateIdInput = document.getElementById('ins-template-id');
    var lookupUrl = @json(route('er-inspection.public.lookup'));

    var COMPLIANCE = [
        { value: 'sesuai', label: 'Sesuai', cls: 'c-ok' },
        { value: 'tidak_sesuai', label: 'Tidak Sesuai', cls: 'c-no' },
        { value: 'tidak_berlaku', label: 'Tidak Berlaku', cls: 'c-na' }
    ];

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function showMessage(text, type) {
        lookupMsg.textContent = text;
        lookupMsg.className = 'lookup-msg is-' + type;
    }

    function resetSelection() {
        targetTypeInput.value = '';
        targetIdInput.value = '';
        templateIdInput.value = '';
        itemsEl.innerHTML = '';
        recapEl.innerHTML = '';
        templateNameEl.textContent = '';
        formSection.classList.remove('is-visible');
    }

    function renderRecap(opt) {
        var rows = [
            ['UUID', opt.code],
            ['Nama', opt.name],
            ['Jenis', opt.type_label],
            ['Kategori', opt.category],
            ['SITE', opt.site],
            ['Kondisi tercatat', opt.condition]
        ];
        Object.keys(opt.details || {}).forEach(function (key) {
            rows.push([key, opt.details[key]]);
        });

        recapEl.innerHTML = rows
            .filter(function (row) { return row[1]; })
            .map(function (row) {
                return '<div><span>' + escapeHtml(row[0]) + '</span><strong>' + escapeHtml(row[1]) + '</strong></div>';
            }).join('');
    }

    function renderItems(template) {
        templateNameEl.textContent = '· ' + template.name;
        itemsEl.innerHTML = '';

        template.items.forEach(function (item, index) {
            var wrap = document.createElement('div');
            wrap.className = 'item';

            var required = item.is_required ? ' <span class="req">*</span>' : '';
            var html = '<input type="hidden" name="items[' + index + '][checklist_template_item_id]" value="' + escapeHtml(item.id) + '">'
                + '<div class="item-text"><span class="item-no">' + (index + 1) + '.</span>' + escapeHtml(item.text) + required + '</div>';

            if (item.answer_type === 'compliance') {
                html += '<div class="choices">' + COMPLIANCE.map(function (choice) {
                    return '<label class="choice">'
                        + '<input type="radio" name="items[' + index + '][answer_value]" value="' + choice.value + '"' + (item.is_required ? ' required' : '') + '>'
                        + '<span class="' + choice.cls + '">' + choice.label + '</span></label>';
                }).join('') + '</div>';
            } else if (item.answer_type === 'measurement') {
                html += '<input type="number" step="any" inputmode="decimal" class="input" name="items[' + index + '][answer_value]" placeholder="Nilai pengukuran"' + (item.is_required ? ' required' : '') + '>';
            } else {
                html += '<textarea class="textarea" name="items[' + index + '][answer_value]" rows="2" maxlength="500" placeholder="Jawaban..."' + (item.is_required ? ' required' : '') + '></textarea>';
            }

            html += '<div class="item-extra">'
                + '<textarea class="textarea" name="items[' + index + '][notes]" rows="1" maxlength="500" placeholder="Catatan (opsional)"></textarea>'
                + '<div class="photo-row"><input type="file" name="items[' + index + '][photo]" accept="image/*" capture="environment"></div>'
                + '</div>';

            wrap.innerHTML = html;

            // Item "tidak sesuai" ditandai merah supaya terlihat sebelum dikirim.
            wrap.addEventListener('change', function (e) {
                if (e.target.type !== 'radio') { return; }
                wrap.classList.toggle('is-flagged', e.target.value === 'tidak_sesuai');
            });

            itemsEl.appendChild(wrap);
        });
    }

    function selectOption(opt, el) {
        document.querySelectorAll('.option-item').forEach(function (n) { n.classList.remove('is-selected'); });
        if (el) { el.classList.add('is-selected'); }

        targetTypeInput.value = opt.type;
        targetIdInput.value = opt.id;
        templateIdInput.value = opt.template.id;

        renderRecap(opt);
        renderItems(opt.template);

        formSection.classList.add('is-visible');
        formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderOptions(options) {
        optionsEl.innerHTML = '';
        options.forEach(function (opt) {
            var el = document.createElement('div');
            el.className = 'option-item';
            var meta = [opt.type_label, opt.category, opt.site].filter(Boolean).map(escapeHtml).join(' &middot; ');
            el.innerHTML = '<div class="o-nama">' + escapeHtml(opt.name) + '</div>'
                + '<div class="o-code">' + escapeHtml(opt.code) + '</div>'
                + '<div class="o-meta">' + meta + '</div>';
            el.addEventListener('click', function () { selectOption(opt, el); });
            optionsEl.appendChild(el);
        });

        // Satu hasil: langsung dipilih, supaya scan QR tidak perlu klik lagi.
        if (options.length === 1) {
            selectOption(options[0], optionsEl.firstChild);
        }
    }

    function doLookup() {
        var q = searchInput.value.trim();
        optionsEl.innerHTML = '';
        resetSelection();

        if (q === '') {
            showMessage('Ketik UUID, No Registrasi, atau nama peralatan.', 'info');
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
        if (e.key === 'Enter') { e.preventDefault(); doLookup(); }
    });

    // --- GPS ---
    var geoStatus = document.getElementById('ins-geo-status');
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (pos) {
            document.getElementById('ins-lat').value = pos.coords.latitude;
            document.getElementById('ins-lng').value = pos.coords.longitude;
            geoStatus.textContent = 'Lokasi GPS: ' + pos.coords.latitude.toFixed(5) + ', ' + pos.coords.longitude.toFixed(5);
        }, function () {
            geoStatus.textContent = 'Lokasi GPS: tidak tersedia (izin ditolak).';
        });
    } else {
        geoStatus.textContent = 'Lokasi GPS: tidak didukung browser ini.';
    }

    // --- Tanda tangan ---
    var canvas = document.getElementById('ins-sig-pad');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var hasSignature = false;

    function sizeCanvas() {
        var ratio = window.devicePixelRatio || 1;
        var rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#1e293b';
    }
    sizeCanvas();

    function point(e) {
        var rect = canvas.getBoundingClientRect();
        var p = e.touches ? e.touches[0] : e;
        return { x: p.clientX - rect.left, y: p.clientY - rect.top };
    }
    function start(e) { drawing = true; hasSignature = true; var p = point(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
    function move(e) { if (!drawing) { return; } var p = point(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
    function end() { drawing = false; }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);

    document.getElementById('ins-sig-clear').addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasSignature = false;
    });

    document.getElementById('ins-form').addEventListener('submit', function (e) {
        if (!targetIdInput.value) {
            e.preventDefault();
            showMessage('Cari dan pilih peralatan terlebih dahulu.', 'error');
            searchInput.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        if (hasSignature) {
            document.getElementById('ins-signature').value = canvas.toDataURL('image/png');
        }
        document.getElementById('ins-submit-btn').disabled = true;
    });

    if (searchInput.value.trim() !== '') {
        doLookup();
    }
})();
</script>
</body>
</html>
