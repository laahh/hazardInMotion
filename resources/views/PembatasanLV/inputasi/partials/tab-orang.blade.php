@php
   $ctx = $formContext ?? [];
   $oldOrang = old('tipe') === 'orang';
   $controlRooms = collect($ctx['control_rooms'] ?? []);
   $selectedControlRoom = $oldOrang ? old('control_room', $ctx['control_room'] ?? '') : ($ctx['control_room'] ?? '');
@endphp

<form method="POST" action="{{ route('pembatasan-lv.inputasi.orang.store') }}" id="plv-inputasi-orang-form" class="flex min-h-0 flex-1 flex-col">
   @csrf
   <input type="hidden" name="tipe" value="orang"/>

   <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5 space-y-4">
      @if($errors->any() && old('tipe') === 'orang')
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
         {{-- Shift --}}
         <div>
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Shift</label>
            <div class="flex h-[42px] items-center rounded-xl border border-outline-variant/20 bg-[#eef2ff] px-3 text-sm font-bold text-primary">
               {{ $oldOrang ? 'Shift '.old('shift', $ctx['shift'] ?? 1) : ($ctx['shift_label'] ?? 'Shift 1') }}
            </div>
            <p class="mt-1 text-[11px] text-on-surface-variant">06:00–18:00 = Shift 1 · 18:00–06:00 = Shift 2 ({{ $ctx['timezone'] ?? config('app.timezone') }})</p>
         </div>

         {{-- Status --}}
         <div>
            <label for="orang-status" class="block text-xs font-bold text-on-surface-variant mb-1">Status <span class="text-error">*</span></label>
            <select id="orang-status" name="status" required class="w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15">
               <option value="">Pilih Status</option>
               <option value="schedule" @selected($oldOrang && old('status') === 'schedule')>Schedule</option>
               <option value="unschedule" @selected($oldOrang && old('status') === 'unschedule')>Unschedule</option>
            </select>
         </div>

         {{-- Check-in --}}
         <div>
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Check-in</label>
            <div class="flex h-[42px] items-center rounded-xl border border-outline-variant/20 bg-[#f8fafc] px-3 text-sm font-medium text-on-surface">
               {{ $oldOrang ? old('checkin_display', $ctx['checkin_display'] ?? now()->format('d M Y H:i')) : ($ctx['checkin_display'] ?? now()->format('d M Y H:i')) }}
            </div>
            <p class="mt-1 text-[11px] text-on-surface-variant">Tanggal & jam otomatis saat simpan</p>
         </div>

         {{-- Creator --}}
         <div>
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Creator</label>
            <div class="flex h-[42px] items-center rounded-xl border border-outline-variant/20 bg-[#f8fafc] px-3 text-sm font-medium text-on-surface">
               {{ $oldOrang ? old('creator_name', $ctx['creator_name'] ?? '—') : ($ctx['creator_name'] ?? '—') }}
            </div>
         </div>

         {{-- Control Room --}}
         <div class="md:col-span-2">
            <label for="orang-control-room" class="block text-xs font-bold text-on-surface-variant mb-1">Control Room <span class="text-error">*</span></label>
            @if($controlRooms->count() <= 1)
            <div class="flex h-[42px] items-center rounded-xl border border-outline-variant/20 bg-[#f8fafc] px-3 text-sm font-medium text-on-surface">
               {{ $selectedControlRoom !== '' ? $selectedControlRoom : '— Tidak terdaftar sebagai pengawas control room —' }}
            </div>
            <input type="hidden" id="orang-control-room" name="control_room" value="{{ $selectedControlRoom }}"/>
            @else
            <select id="orang-control-room" name="control_room" required class="w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15">
               @foreach($controlRooms as $room)
               <option value="{{ $room }}" @selected($selectedControlRoom === $room)>{{ $room }}</option>
               @endforeach
            </select>
            @endif
            <p class="mt-1 text-[11px] text-on-surface-variant">Diisi otomatis dari penugasan pengawas control room Anda</p>
         </div>

         {{-- SID --}}
         <div class="plv-orang-combobox-wrap" data-plv-orang-combobox data-url="{{ route('pembatasan-lv.inputasi.options.sid') }}" data-field="sid">
            <label class="block text-xs font-bold text-on-surface-variant mb-1">SID <span class="text-error">*</span></label>
            <div class="relative">
               <input type="text" name="sid" id="orang-sid" autocomplete="off" required value="{{ $oldOrang ? old('sid') : '' }}" placeholder="Cari kode SID…" class="plv-orang-combobox-input w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15"/>
               <ul class="plv-orang-combobox-list absolute z-[210] mt-1 hidden max-h-52 w-full overflow-y-auto rounded-xl border border-outline-variant/30 bg-white py-1 shadow-lg"></ul>
            </div>
            <p class="mt-1 text-[11px] text-on-surface-variant">Data dari bep_vw_wp_karyawan (cari SID, nama, atau NIK — min. 2 karakter)</p>
         </div>

         {{-- Nama (auto) --}}
         <div>
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Nama</label>
            <div id="orang-nama-display" class="flex min-h-[42px] items-center rounded-xl border border-outline-variant/20 bg-[#f8fafc] px-3 text-sm font-medium text-on-surface">
               {{ $oldOrang ? old('nama', '—') : '—' }}
            </div>
            <input type="hidden" name="nama" id="orang-nama" value="{{ $oldOrang ? old('nama') : '' }}"/>
         </div>

         {{-- Perusahaan (auto) --}}
         <div>
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Perusahaan</label>
            <div id="orang-perusahaan-display" class="flex min-h-[42px] items-center rounded-xl border border-outline-variant/20 bg-[#f8fafc] px-3 text-sm font-medium text-on-surface">
               {{ $oldOrang ? old('nama_perusahaan', '—') : '—' }}
            </div>
            <input type="hidden" name="nama_perusahaan" id="orang-perusahaan" value="{{ $oldOrang ? old('nama_perusahaan') : '' }}"/>
         </div>

         {{-- NIK (auto) --}}
         <div>
            <label class="block text-xs font-bold text-on-surface-variant mb-1">NIK</label>
            <div id="orang-nik-display" class="flex min-h-[42px] items-center rounded-xl border border-outline-variant/20 bg-[#f8fafc] px-3 text-sm font-medium text-on-surface">
               {{ $oldOrang ? old('nik', '—') : '—' }}
            </div>
            <input type="hidden" name="nik" id="orang-nik" value="{{ $oldOrang ? old('nik') : '' }}"/>
         </div>

         {{-- Dept (auto) --}}
         <div>
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Dept</label>
            <div id="orang-dept-display" class="flex min-h-[42px] items-center rounded-xl border border-outline-variant/20 bg-[#f8fafc] px-3 text-sm font-medium text-on-surface">
               {{ $oldOrang ? old('dept', '—') : '—' }}
            </div>
            <input type="hidden" name="dept" id="orang-dept" value="{{ $oldOrang ? old('dept') : '' }}"/>
            <p class="mt-1 text-[11px] text-on-surface-variant">Dari departement / dept_dic / Dept_Mainkon</p>
         </div>

         {{-- Lokasi --}}
         <div class="plv-orang-combobox-wrap" data-plv-orang-combobox data-url="{{ route('pembatasan-lv.inputasi.options.lokasi') }}" data-field="lokasi" data-target-detail="#plv-orang-detail-lokasi-wrap">
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Lokasi <span class="text-error">*</span></label>
            <div class="relative">
               <input type="text" name="lokasi" autocomplete="off" required value="{{ $oldOrang ? old('lokasi') : '' }}" placeholder="Cari lokasi…" class="plv-orang-combobox-input w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15"/>
               <ul class="plv-orang-combobox-list absolute z-[210] mt-1 hidden max-h-52 w-full overflow-y-auto rounded-xl border border-outline-variant/30 bg-white py-1 shadow-lg"></ul>
            </div>
         </div>

         {{-- Detail Lokasi --}}
         <div id="plv-orang-detail-lokasi-wrap" class="plv-orang-combobox-wrap md:col-span-2" data-plv-orang-combobox data-url="{{ route('pembatasan-lv.inputasi.options.detail-lokasi') }}" data-field="detail_lokasi" data-lokasi-param="lokasi">
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Detail Lokasi</label>
            <div class="relative">
               <input type="text" name="detail_lokasi" autocomplete="off" value="{{ $oldOrang ? old('detail_lokasi') : '' }}" placeholder="Pilih lokasi terlebih dahulu…" class="plv-orang-combobox-input w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15"/>
               <ul class="plv-orang-combobox-list absolute z-[210] mt-1 hidden max-h-52 w-full overflow-y-auto rounded-xl border border-outline-variant/30 bg-white py-1 shadow-lg"></ul>
            </div>
         </div>

         {{-- Aktivitas --}}
         <div class="plv-orang-combobox-wrap md:col-span-2 lg:col-span-3" data-plv-orang-combobox data-url="{{ route('pembatasan-lv.inputasi.options.aktivitas') }}" data-field="aktivitas" data-allow-custom="1">
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Aktivitas</label>
            <div class="relative">
               <input type="text" name="aktivitas" autocomplete="off" value="{{ $oldOrang ? old('aktivitas') : '' }}" placeholder="Pilih atau ketik aktivitas…" class="plv-orang-combobox-input w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15"/>
               <ul class="plv-orang-combobox-list absolute z-[210] mt-1 hidden max-h-52 w-full overflow-y-auto rounded-xl border border-outline-variant/30 bg-white py-1 shadow-lg"></ul>
            </div>
         </div>
      </div>

      <div>
         <label for="orang-catatan" class="block text-xs font-bold text-on-surface-variant mb-1">Catatan</label>
         <textarea id="orang-catatan" name="catatan" rows="3" placeholder="Keterangan tambahan (opsional)" class="w-full rounded-xl border border-outline-variant/30 bg-[#f8fafc] px-3 py-2.5 text-sm outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15">{{ $oldOrang ? old('catatan') : '' }}</textarea>
      </div>
   </div>

   <div class="shrink-0 flex flex-wrap justify-end gap-3 border-t border-outline-variant/20 bg-[#f8fafc] px-6 py-4">
      <button type="button" data-plv-inputasi-close class="inline-flex items-center justify-center rounded-xl border border-outline-variant/30 bg-white px-5 py-2.5 text-sm font-bold text-on-surface hover:bg-white">Batal</button>
      <button type="submit" id="plv-orang-submit-btn" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-md hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50" @disabled($controlRooms->isEmpty() && $selectedControlRoom === '')>
         <span class="material-symbols-outlined text-lg">save</span>
         Simpan Inputasi Orang
      </button>
   </div>
</form>

@push('scripts')
<script>
(function () {
   var form = document.getElementById('plv-inputasi-orang-form');
   if (!form) return;

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

   function displayOrDash(value) {
      return value && String(value).trim() !== '' ? String(value).trim() : '—';
   }

   function fillKaryawan(item) {
      var nama = item ? (item.nama || '') : '';
      var perusahaan = item ? (item.nama_perusahaan || '') : '';
      var nik = item ? (item.nik || '') : '';
      var dept = item ? (item.dept || '') : '';

      document.getElementById('orang-nama').value = nama;
      document.getElementById('orang-perusahaan').value = perusahaan;
      document.getElementById('orang-nik').value = nik;
      document.getElementById('orang-dept').value = dept;
      document.getElementById('orang-nama-display').textContent = displayOrDash(nama);
      document.getElementById('orang-perusahaan-display').textContent = displayOrDash(perusahaan);
      document.getElementById('orang-nik-display').textContent = displayOrDash(nik);
      document.getElementById('orang-dept-display').textContent = displayOrDash(dept);
   }

   function clearKaryawan() {
      fillKaryawan(null);
   }

   form.querySelectorAll('[data-plv-orang-combobox]').forEach(function (wrap) {
      var url = wrap.getAttribute('data-url');
      var field = wrap.getAttribute('data-field');
      var input = wrap.querySelector('.plv-orang-combobox-input');
      var list = wrap.querySelector('.plv-orang-combobox-list');
      var allowCustom = wrap.getAttribute('data-allow-custom') === '1';
      var lokasiParam = wrap.getAttribute('data-lokasi-param');
      var detailTarget = wrap.getAttribute('data-target-detail');
      var lastItems = [];

      if (!url || !input || !list) return;

      function setValue(item) {
         if (field === 'sid' && item) {
            input.value = item.sid || item.label || item.id || '';
            fillKaryawan(item);
         } else if (item) {
            input.value = item.label || item.value || item.sid || '';
         }
         list.classList.add('hidden');

         if (detailTarget && field === 'lokasi') {
            var detailWrap = form.querySelector(detailTarget);
            if (detailWrap) {
               var detailInput = detailWrap.querySelector('input[name="detail_lokasi"]');
               if (detailInput) detailInput.value = '';
            }
         }
      }

      function renderList(items) {
         lastItems = items || [];
         if (!lastItems.length) {
            list.innerHTML = '<li class="px-3 py-2 text-xs text-on-surface-variant">Tidak ada data</li>';
            list.classList.remove('hidden');
            return;
         }
         list.innerHTML = lastItems.map(function (item, idx) {
            var label = escapeHtml(item.label || item.value || item.sid || item.nama || '');
            var sub = escapeHtml(item.subtitle || '');
            return '<li><button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-[#f1f5f9]" data-plv-orang-idx="' + idx + '">' +
               '<span class="font-medium text-on-background">' + label + '</span>' +
               (sub ? '<span class="block text-xs text-on-surface-variant">' + sub + '</span>' : '') +
            '</button></li>';
         }).join('');
         list.classList.remove('hidden');
      }

      var fetchOptions = debounce(function () {
         var q = input.value.trim();
         var fetchUrl = new URL(url, window.location.origin);
         if (q) fetchUrl.searchParams.set('q', q);
         if (lokasiParam) {
            var lokasiInput = form.querySelector('input[name="lokasi"]');
            if (lokasiInput && lokasiInput.value) {
               fetchUrl.searchParams.set('lokasi', lokasiInput.value);
            }
         }
         fetch(fetchUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (json) { renderList(json.data || []); })
            .catch(function () { renderList([]); });
      }, 250);

      input.addEventListener('focus', fetchOptions);
      input.addEventListener('input', function () {
         if (field === 'sid') clearKaryawan();
         fetchOptions();
      });
      input.addEventListener('blur', function () {
         setTimeout(function () { list.classList.add('hidden'); }, 150);
      });

      list.addEventListener('mousedown', function (e) {
         e.preventDefault();
         var btn = e.target.closest('[data-plv-orang-idx]');
         if (!btn) return;
         var item = lastItems[parseInt(btn.getAttribute('data-plv-orang-idx'), 10)];
         if (item) setValue(item);
      });
   });

   var submitBtn = document.getElementById('plv-orang-submit-btn');
   if (submitBtn && (submitBtn.disabled || @json($controlRooms->isEmpty() && $selectedControlRoom === ''))) {
      submitBtn.setAttribute('data-cr-disabled', '1');
   }
})();
</script>
@endpush
