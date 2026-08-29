@php
   $ctx = $formContext ?? [];
   $oldLv = old('tipe') === 'lv';
   $controlRooms = collect($ctx['control_rooms'] ?? []);
   $selectedControlRoom = $oldLv ? old('control_room', $ctx['control_room'] ?? '') : ($ctx['control_room'] ?? '');
@endphp

<form method="POST" action="{{ route('pembatasan-lv.inputasi.lv.store') }}" id="plv-inputasi-lv-form" class="flex min-h-0 flex-1 flex-col">
   @csrf
   <input type="hidden" name="tipe" value="lv"/>

   <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5 space-y-4">
      @if($errors->any() && old('tipe') === 'lv')
      <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
         <p class="font-semibold mb-1">Periksa input berikut:</p>
         <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $err)
               <li>{{ $err }}</li>
            @endforeach
         </ul>
      </div>
      @endif

      @if($controlRooms->isEmpty() && $selectedControlRoom === '')
      <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
         Akun Anda belum terdaftar sebagai pengawas control room. Hubungi admin untuk mendaftarkan email/nama Anda di Master Data → Pengawas Control Room.
      </div>
      @endif

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
         {{-- Shift (otomatis) --}}
         <div>
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Shift</label>
            <div class="flex h-[42px] items-center rounded-xl border border-outline-variant/20 bg-[#eef2ff] px-3 text-sm font-bold text-primary">
               {{ $oldLv ? 'Shift '.old('shift', $ctx['shift'] ?? 1) : ($ctx['shift_label'] ?? 'Shift 1') }}
            </div>
            <p class="mt-1 text-[11px] text-on-surface-variant">06:00–18:00 = Shift 1 · 18:00–06:00 = Shift 2 ({{ $ctx['timezone'] ?? config('app.timezone') }})</p>
         </div>

         {{-- Status --}}
         <div>
            <label for="lv-status" class="block text-xs font-bold text-on-surface-variant mb-1">Status <span class="text-error">*</span></label>
            <select id="lv-status" name="status" required class="w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15">
               <option value="">Pilih Status</option>
               <option value="schedule" @selected($oldLv && old('status') === 'schedule')>Schedule</option>
               <option value="unschedule" @selected($oldLv && old('status') === 'unschedule')>Unschedule</option>
            </select>
         </div>

         {{-- Check-in --}}
         <div>
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Check-in</label>
            <div class="flex h-[42px] items-center rounded-xl border border-outline-variant/20 bg-[#f8fafc] px-3 text-sm font-medium text-on-surface">
               {{ $oldLv ? old('checkin_display', $ctx['checkin_display'] ?? now()->format('d M Y H:i')) : ($ctx['checkin_display'] ?? now()->format('d M Y H:i')) }}
            </div>
            <p class="mt-1 text-[11px] text-on-surface-variant">Tanggal & jam otomatis saat simpan</p>
         </div>

         {{-- Creator --}}
         <div>
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Creator</label>
            <div class="flex h-[42px] items-center rounded-xl border border-outline-variant/20 bg-[#f8fafc] px-3 text-sm font-medium text-on-surface">
               {{ $oldLv ? old('creator_name', $ctx['creator_name'] ?? '—') : ($ctx['creator_name'] ?? '—') }}
            </div>
         </div>

         {{-- Control Room --}}
         <div class="md:col-span-2">
            <label for="lv-control-room" class="block text-xs font-bold text-on-surface-variant mb-1">Control Room <span class="text-error">*</span></label>
            @if($controlRooms->count() <= 1)
            <div class="flex h-[42px] items-center rounded-xl border border-outline-variant/20 bg-[#f8fafc] px-3 text-sm font-medium text-on-surface">
               {{ $selectedControlRoom !== '' ? $selectedControlRoom : '— Tidak terdaftar sebagai pengawas control room —' }}
            </div>
            <input type="hidden" id="lv-control-room" name="control_room" value="{{ $selectedControlRoom }}"/>
            @else
            <select id="lv-control-room" name="control_room" required class="w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15">
               @foreach($controlRooms as $room)
               <option value="{{ $room }}" @selected($selectedControlRoom === $room)>{{ $room }}</option>
               @endforeach
            </select>
            @endif
            <p class="mt-1 text-[11px] text-on-surface-variant">Diisi otomatis dari penugasan pengawas control room Anda</p>
         </div>

         {{-- Nama Driver --}}
         <div class="plv-combobox-wrap" data-plv-combobox data-url="{{ route('pembatasan-lv.inputasi.options.drivers') }}" data-name="nama_driver" data-hidden="driver_ref" data-placeholder="Cari nama driver…">
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Nama Driver <span class="text-error">*</span></label>
            <div class="relative">
               <input type="text" name="nama_driver" autocomplete="off" required value="{{ $oldLv ? old('nama_driver') : '' }}" placeholder="Cari nama driver…" class="plv-combobox-input w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15"/>
               <input type="hidden" name="driver_ref" value="{{ $oldLv ? old('driver_ref') : '' }}"/>
               <ul class="plv-combobox-list absolute z-[210] mt-1 hidden max-h-52 w-full overflow-y-auto rounded-xl border border-outline-variant/30 bg-white py-1 shadow-lg"></ul>
            </div>
            <p class="mt-1 text-[11px] text-on-surface-variant">Data dari bep_vw_safety_karyawan_aktif (cari nama, SID, atau NIK — min. 2 karakter)</p>
         </div>

         {{-- No Unit --}}
         <div class="plv-combobox-wrap md:col-span-2" data-plv-combobox data-url="{{ route('pembatasan-lv.inputasi.options.units') }}" data-name="no_lambung" data-hidden="id_unit" data-value-key="no_lambung" data-id-key="id_unit" data-placeholder="Cari no lambung…">
            <label class="block text-xs font-bold text-on-surface-variant mb-1">No Unit <span class="text-error">*</span></label>
            <div class="relative">
               <input type="text" name="no_lambung" autocomplete="off" required value="{{ $oldLv ? old('no_lambung') : '' }}" placeholder="Cari no lambung…" class="plv-combobox-input w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15"/>
               <input type="hidden" name="id_unit" value="{{ $oldLv ? old('id_unit') : '' }}"/>
               <ul class="plv-combobox-list absolute z-[210] mt-1 hidden max-h-52 w-full overflow-y-auto rounded-xl border border-outline-variant/30 bg-white py-1 shadow-lg"></ul>
            </div>
         </div>

         {{-- Lokasi --}}
         <div class="plv-combobox-wrap" data-plv-combobox data-url="{{ route('pembatasan-lv.inputasi.options.lokasi') }}" data-name="lokasi" data-value-key="value" data-id-key="value" data-target-detail="#plv-detail-lokasi-wrap" data-placeholder="Cari lokasi…">
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Lokasi <span class="text-error">*</span></label>
            <div class="relative">
               <input type="text" name="lokasi" autocomplete="off" required value="{{ $oldLv ? old('lokasi') : '' }}" placeholder="Cari lokasi…" class="plv-combobox-input w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15"/>
               <ul class="plv-combobox-list absolute z-[210] mt-1 hidden max-h-52 w-full overflow-y-auto rounded-xl border border-outline-variant/30 bg-white py-1 shadow-lg"></ul>
            </div>
         </div>

         {{-- Detail Lokasi --}}
         <div id="plv-detail-lokasi-wrap" class="plv-combobox-wrap md:col-span-2" data-plv-combobox data-url="{{ route('pembatasan-lv.inputasi.options.detail-lokasi') }}" data-name="detail_lokasi" data-value-key="value" data-id-key="value" data-lokasi-param="lokasi" data-placeholder="Cari detail lokasi…">
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Detail Lokasi</label>
            <div class="relative">
               <input type="text" name="detail_lokasi" autocomplete="off" value="{{ $oldLv ? old('detail_lokasi') : '' }}" placeholder="Pilih lokasi terlebih dahulu…" class="plv-combobox-input w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15"/>
               <ul class="plv-combobox-list absolute z-[210] mt-1 hidden max-h-52 w-full overflow-y-auto rounded-xl border border-outline-variant/30 bg-white py-1 shadow-lg"></ul>
            </div>
         </div>

         {{-- Aktivitas --}}
         <div class="plv-combobox-wrap md:col-span-2 lg:col-span-3" data-plv-combobox data-url="{{ route('pembatasan-lv.inputasi.options.aktivitas') }}" data-name="aktivitas" data-value-key="value" data-id-key="value" data-allow-custom="1" data-placeholder="Pilih atau ketik aktivitas…">
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Aktivitas</label>
            <div class="relative">
               <input type="text" name="aktivitas" autocomplete="off" value="{{ $oldLv ? old('aktivitas') : '' }}" placeholder="Pilih atau ketik aktivitas…" class="plv-combobox-input w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15"/>
               <ul class="plv-combobox-list absolute z-[210] mt-1 hidden max-h-52 w-full overflow-y-auto rounded-xl border border-outline-variant/30 bg-white py-1 shadow-lg"></ul>
            </div>
            @if(($aktivitasOptions ?? collect())->isNotEmpty())
            <datalist id="plv-aktivitas-fallback">
               @foreach($aktivitasOptions as $aktivitas)
               <option value="{{ $aktivitas }}"></option>
               @endforeach
            </datalist>
            @endif
         </div>
      </div>

      <div id="plv-kapasitas-banner" class="hidden rounded-xl border px-4 py-3 text-sm" role="status"></div>

      <div>
         <label for="lv-catatan" class="block text-xs font-bold text-on-surface-variant mb-1">Catatan</label>
         <textarea id="lv-catatan" name="catatan" rows="3" placeholder="Catatan tambahan (opsional)" class="w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15">{{ $oldLv ? old('catatan') : '' }}</textarea>
      </div>
   </div>

   <div class="shrink-0 flex flex-wrap justify-end gap-3 border-t border-outline-variant/20 bg-[#f8fafc] px-6 py-4">
      <button type="button" data-plv-inputasi-close class="inline-flex items-center justify-center rounded-xl border border-outline-variant/30 bg-white px-5 py-2.5 text-sm font-bold text-on-surface hover:bg-white">Batal</button>
      <button type="submit" id="plv-lv-submit-btn" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-md hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50" @disabled($controlRooms->isEmpty() && $selectedControlRoom === '')>
         <span class="material-symbols-outlined text-lg">save</span>
         Simpan Inputasi LV
      </button>
   </div>
</form>

@push('scripts')
<script>
(function () {
   function escapeHtml(value) {
      return String(value ?? '')
         .replace(/&/g, '&amp;')
         .replace(/</g, '&lt;')
         .replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;');
   }

   function debounce(fn, ms) {
      var timer;
      return function () {
         var args = arguments;
         var ctx = this;
         clearTimeout(timer);
         timer = setTimeout(function () { fn.apply(ctx, args); }, ms);
      };
   }

   var lvForm = document.getElementById('plv-inputasi-lv-form');
   if (!lvForm) return;

   lvForm.querySelectorAll('[data-plv-combobox]').forEach(function (wrap) {
      var url = wrap.getAttribute('data-url');
      var nameField = wrap.querySelector('[name="' + wrap.getAttribute('data-name') + '"]');
      var visibleInput = wrap.querySelector('.plv-combobox-input');
      var list = wrap.querySelector('.plv-combobox-list');
      var hiddenExtra = wrap.getAttribute('data-hidden') ? wrap.querySelector('[name="' + wrap.getAttribute('data-hidden') + '"]') : null;
      var valueKey = wrap.getAttribute('data-value-key') || 'label';
      var idKey = wrap.getAttribute('data-id-key') || 'id';
      var allowCustom = wrap.getAttribute('data-allow-custom') === '1';
      var lokasiParam = wrap.getAttribute('data-lokasi-param');
      var detailTarget = wrap.getAttribute('data-target-detail');
      var lastItems = [];

      if (!url || !nameField || !visibleInput || !list) return;

      if (nameField !== visibleInput) {
         visibleInput = nameField;
      }

      function syncVisible() {
         /* single field — no sync needed */
      }

      function setValue(item) {
         var label = item ? (item.label || item[valueKey] || item.nama || '') : visibleInput.value.trim();
         var idVal = item ? (item[idKey] || item.id || '') : '';
         visibleInput.value = label;
         if (hiddenExtra) hiddenExtra.value = idVal;
         list.classList.add('hidden');
         if (detailTarget && label) {
            var detailWrap = lvForm.querySelector(detailTarget);
            if (detailWrap) {
               var detailInput = detailWrap.querySelector('[name="detail_lokasi"]');
               if (detailInput) detailInput.value = '';
            }
         }
         document.dispatchEvent(new CustomEvent('plv-lokasi-changed'));
      }

      function renderList(items) {
         lastItems = items || [];
         if (!lastItems.length) {
            list.innerHTML = '<li class="px-3 py-2 text-xs text-on-surface-variant">Tidak ada data</li>';
            list.classList.remove('hidden');
            return;
         }
         list.innerHTML = lastItems.map(function (item, idx) {
            var label = escapeHtml(item.label || item[valueKey] || item.nama || '');
            var sub = escapeHtml(item.subtitle || '');
            return '<li><button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-[#f1f5f9]" data-plv-combobox-idx="' + idx + '">' +
               '<span class="font-medium text-on-background">' + label + '</span>' +
               (sub ? '<span class="block text-xs text-on-surface-variant">' + sub + '</span>' : '') +
            '</button></li>';
         }).join('');
         list.classList.remove('hidden');
      }

      var fetchOptions = debounce(function () {
         var q = visibleInput.value.trim();
         var fetchUrl = new URL(url, window.location.origin);
         if (q) fetchUrl.searchParams.set('q', q);
         if (lokasiParam) {
            var lokasiInput = lvForm.querySelector('input[name="lokasi"]');
            if (lokasiInput && lokasiInput.value) {
               fetchUrl.searchParams.set('lokasi', lokasiInput.value);
            }
         }
         fetch(fetchUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (json) { renderList(json.data || []); })
            .catch(function () { renderList([]); });
      }, 250);

      visibleInput.addEventListener('focus', fetchOptions);
      visibleInput.addEventListener('input', function () {
         if (hiddenExtra) hiddenExtra.value = '';
         fetchOptions();
      });
      visibleInput.addEventListener('blur', function () {
         setTimeout(function () { list.classList.add('hidden'); }, 150);
      });

      list.addEventListener('mousedown', function (e) {
         e.preventDefault();
         var btn = e.target.closest('[data-plv-combobox-idx]');
         if (!btn) return;
         var item = lastItems[parseInt(btn.getAttribute('data-plv-combobox-idx'), 10)];
         if (item) {
            setValue(item);
            document.dispatchEvent(new CustomEvent('plv-lokasi-changed'));
         }
      });
   });

   var kapasitasUrl = @json(route('pembatasan-lv.inputasi.kapasitas-lokasi'));
   var kapasitasBanner = document.getElementById('plv-kapasitas-banner');
   var submitBtn = document.getElementById('plv-lv-submit-btn');
   var lokasiInput = lvForm.querySelector('input[name="lokasi"]');
   var detailInput = lvForm.querySelector('input[name="detail_lokasi"]');

   function renderKapasitas(data) {
      if (!kapasitasBanner) return;

      if (!data || !data.has_batas) {
         kapasitasBanner.classList.add('hidden');
         kapasitasBanner.className = 'hidden rounded-xl border px-4 py-3 text-sm';
         if (submitBtn && !submitBtn.hasAttribute('data-cr-disabled')) {
            submitBtn.disabled = false;
         }
         return;
      }

      kapasitasBanner.classList.remove('hidden');
      var label = data.lokasi || '';
      if (data.detail_lokasi && data.detail_lokasi !== data.lokasi) {
         label += ' • ' + data.detail_lokasi;
      }

      if (data.can_input) {
         kapasitasBanner.className = 'rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900';
         kapasitasBanner.innerHTML = '<span class="font-bold">Kapasitas lokasi:</span> ' + escapeHtml(label) +
            ' — <span class="font-bold">' + data.terpakai + '/' + data.batas_lv + '</span> LV di area' +
            (data.tersisa !== null ? ' (tersisa <span class="font-bold">' + data.tersisa + '</span>)' : '');
         if (submitBtn && !submitBtn.hasAttribute('data-cr-disabled')) {
            submitBtn.disabled = false;
         }
      } else {
         kapasitasBanner.className = 'rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900';
         kapasitasBanner.innerHTML = '<span class="font-bold">Kapasitas penuh:</span> ' + escapeHtml(label) +
            ' — <span class="font-bold">' + data.terpakai + '/' + data.batas_lv + '</span> LV masih di area. Tunggu checkout sebelum input baru.';
         if (submitBtn) submitBtn.disabled = true;
      }
   }

   var fetchKapasitas = debounce(function () {
      if (!lokasiInput || !lokasiInput.value.trim()) {
         renderKapasitas(null);
         return;
      }
      var url = new URL(kapasitasUrl, window.location.origin);
      url.searchParams.set('lokasi', lokasiInput.value.trim());
      if (detailInput && detailInput.value.trim()) {
         url.searchParams.set('detail_lokasi', detailInput.value.trim());
      }
      fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
         .then(function (res) { return res.json(); })
         .then(function (json) { renderKapasitas(json.data || null); })
         .catch(function () { renderKapasitas(null); });
   }, 300);

   if (lokasiInput) {
      lokasiInput.addEventListener('input', fetchKapasitas);
      lokasiInput.addEventListener('blur', fetchKapasitas);
   }
   if (detailInput) {
      detailInput.addEventListener('input', fetchKapasitas);
      detailInput.addEventListener('blur', fetchKapasitas);
   }
   document.addEventListener('plv-lokasi-changed', fetchKapasitas);

   if (submitBtn && (submitBtn.disabled || @json($controlRooms->isEmpty() && $selectedControlRoom === ''))) {
      submitBtn.setAttribute('data-cr-disabled', '1');
   }

   fetchKapasitas();
})();
</script>
@endpush
