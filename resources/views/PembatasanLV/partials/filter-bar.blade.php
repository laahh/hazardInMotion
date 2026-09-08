{{--
    Filter picker: site, tanggal, control room.

    Variabel:
    - $sites         (iterable)
    - $controlRooms (iterable)
    - $filters       ['site' => ?, 'tanggal' => ?, 'control_room' => ?]
--}}
@php
   $filters = $filters ?? ['site' => '', 'tanggal' => now()->toDateString(), 'control_room' => ''];
   $siteLabel = $filters['site'] !== '' ? $filters['site'] : 'Semua Site';
   $tanggalLabel = \Carbon\Carbon::parse($filters['tanggal'])->format('d M Y');
   $controlRoomLabel = $filters['control_room'] !== '' ? $filters['control_room'] : 'Semua Control Room';
   $pickerBtnClass = 'plv-filter-pill inline-flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left sm:w-auto sm:min-w-[12.5rem]';
   $dropdownClass = 'plv-filter-dropdown hidden absolute right-0 top-full z-[80] mt-2 max-h-64 w-72 overflow-y-auto rounded-xl border border-slate-200 bg-white py-2';
   $optionClass = 'flex w-full items-center px-4 py-2.5 text-left text-sm font-medium text-slate-700 transition-colors duration-200 hover:bg-slate-50';
@endphp
<div class="flex flex-wrap items-center justify-start lg:justify-end gap-3">
<form method="GET" action="{{ route('pembatasan-lv.index') }}" id="plv-filter-form" class="relative z-50 flex flex-wrap items-center gap-3">
   <div class="relative z-10" data-plv-filter-wrap>
      <button type="button" data-plv-filter-toggle class="{{ $pickerBtnClass }}" aria-haspopup="listbox" aria-expanded="false">
         <span class="material-symbols-outlined text-primary text-xl shrink-0">location_on</span>
         <span class="flex min-w-0 flex-1 flex-col items-start leading-tight gap-0.5">
            <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant/80">Site</span>
            <span id="plv-site-label" class="truncate text-sm font-bold text-on-background">{{ $siteLabel }}</span>
         </span>
         <span class="material-symbols-outlined text-on-surface-variant/45 text-xl shrink-0">expand_more</span>
      </button>
      <div class="{{ $dropdownClass }}" data-plv-filter-menu role="listbox">
         <button type="button" class="{{ $optionClass }} plv-filter-option" data-name="site" data-value="" data-label="Semua Site">Semua Site</button>
         @foreach($sites as $site)
         <button type="button" class="{{ $optionClass }} plv-filter-option {{ $filters['site'] === $site ? 'bg-primary/5 text-primary' : '' }}" data-name="site" data-value="{{ $site }}" data-label="{{ $site }}">{{ $site }}</button>
         @endforeach
      </div>
      <input type="hidden" name="site" id="plv-filter-site" value="{{ $filters['site'] }}"/>
   </div>

   <div class="relative" data-plv-filter-wrap>
      <button type="button" id="plv-open-tanggal" class="{{ $pickerBtnClass }}" aria-haspopup="dialog">
         <span class="material-symbols-outlined text-primary text-xl shrink-0">calendar_month</span>
         <span class="flex min-w-0 flex-1 flex-col items-start leading-tight gap-0.5">
            <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant/80">Tanggal</span>
            <span id="plv-tanggal-label" class="truncate text-sm font-bold text-on-background">{{ $tanggalLabel }}</span>
         </span>
         <span class="material-symbols-outlined text-on-surface-variant/45 text-xl shrink-0">expand_more</span>
      </button>
      <input type="date" name="tanggal" id="plv-filter-tanggal" value="{{ $filters['tanggal'] }}" class="pointer-events-none absolute opacity-0" tabindex="-1" aria-hidden="true"/>
   </div>

   <div class="relative z-10" data-plv-filter-wrap>
      <button type="button" data-plv-filter-toggle class="{{ $pickerBtnClass }}" aria-haspopup="listbox" aria-expanded="false">
         <span class="material-symbols-outlined text-primary text-xl shrink-0">meeting_room</span>
         <span class="flex min-w-0 flex-1 flex-col items-start leading-tight gap-0.5">
            <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant/80">Control Room</span>
            <span id="plv-control-room-label" class="truncate text-sm font-bold text-on-background">{{ $controlRoomLabel }}</span>
         </span>
         <span class="material-symbols-outlined text-on-surface-variant/45 text-xl shrink-0">expand_more</span>
      </button>
      <div class="{{ $dropdownClass }}" data-plv-filter-menu role="listbox">
         <button type="button" class="{{ $optionClass }} plv-filter-option" data-name="control_room" data-value="" data-label="Semua Control Room">Semua Control Room</button>
         @foreach($controlRooms as $controlRoom)
         <button type="button" class="{{ $optionClass }} plv-filter-option {{ $filters['control_room'] === $controlRoom ? 'bg-primary/5 text-primary' : '' }}" data-name="control_room" data-value="{{ $controlRoom }}" data-label="{{ $controlRoom }}">{{ $controlRoom }}</button>
         @endforeach
      </div>
      <input type="hidden" name="control_room" id="plv-filter-control-room" value="{{ $filters['control_room'] }}"/>
   </div>

   @if($filters['site'] || $filters['control_room'] || request()->has('tanggal'))
   <a href="{{ route('pembatasan-lv.index') }}" class="plv-filter-pill inline-flex items-center justify-center rounded-xl px-3 py-3 text-on-surface-variant" title="Reset filter">
      <span class="material-symbols-outlined text-xl">restart_alt</span>
   </a>
   @endif
</form>

   <div class="flex items-center gap-2 shrink-0">
      <button type="button" data-plv-open-inputasi="lv" class="plv-add-btn plv-add-btn--lv" title="Tambah inputasi LV">
         <span class="material-symbols-outlined text-lg">local_shipping</span>
         Tambah LV
      </button>
      <button type="button" data-plv-open-inputasi="orang" class="plv-add-btn plv-add-btn--orang" title="Tambah inputasi orang">
         <span class="material-symbols-outlined text-lg">person_add</span>
         Tambah Orang
      </button>
   </div>
</div>

@push('scripts')
<script>
(function () {
   var form = document.getElementById('plv-filter-form');
   if (!form) return;

   function closeAllMenus(except) {
      form.querySelectorAll('[data-plv-filter-menu]').forEach(function (menu) {
         if (menu === except) return;
         menu.classList.add('hidden');
         var wrap = menu.closest('[data-plv-filter-wrap]');
         if (wrap) wrap.classList.remove('z-50');
         var toggle = menu.parentElement && menu.parentElement.querySelector('[data-plv-filter-toggle]');
         if (toggle) toggle.setAttribute('aria-expanded', 'false');
      });
   }

   form.querySelectorAll('[data-plv-filter-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
         e.stopPropagation();
         var wrap = btn.closest('[data-plv-filter-wrap]');
         var menu = wrap && wrap.querySelector('[data-plv-filter-menu]');
         var isOpen = menu && !menu.classList.contains('hidden');
         closeAllMenus();
         if (menu && !isOpen) {
            menu.classList.remove('hidden');
            if (wrap) wrap.classList.add('z-50');
            btn.setAttribute('aria-expanded', 'true');
         }
      });
   });

   form.querySelectorAll('.plv-filter-option').forEach(function (opt) {
      opt.addEventListener('click', function () {
         var name = opt.getAttribute('data-name');
         var value = opt.getAttribute('data-value') || '';
         var label = opt.getAttribute('data-label') || '';
         var input = form.querySelector('[name="' + name + '"]');
         if (input) input.value = value;
         if (name === 'site') {
            var crInput = form.querySelector('[name="control_room"]');
            if (crInput) crInput.value = '';
            var crLabel = document.getElementById('plv-control-room-label');
            if (crLabel) crLabel.textContent = 'Semua Control Room';
         }
         var labelEl = document.getElementById('plv-site-label');
         if (name === 'control_room') labelEl = document.getElementById('plv-control-room-label');
         if (name === 'site') labelEl = document.getElementById('plv-site-label');
         if (labelEl) labelEl.textContent = label;
         closeAllMenus();
         form.submit();
      });
   });

   var tanggalBtn = document.getElementById('plv-open-tanggal');
   var tanggalInput = document.getElementById('plv-filter-tanggal');
   if (tanggalBtn && tanggalInput) {
      tanggalBtn.addEventListener('click', function () {
         closeAllMenus();
         if (typeof tanggalInput.showPicker === 'function') {
            tanggalInput.showPicker();
         } else {
            tanggalInput.classList.remove('pointer-events-none');
            tanggalInput.focus();
            tanggalInput.click();
            tanggalInput.classList.add('pointer-events-none');
         }
      });
      tanggalInput.addEventListener('change', function () {
         var labelEl = document.getElementById('plv-tanggal-label');
         if (labelEl && tanggalInput.value) {
            var parts = tanggalInput.value.split('-');
            var months = ['Jan','Peb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            if (parts.length === 3) {
               labelEl.textContent = parseInt(parts[2], 10) + ' ' + months[parseInt(parts[1], 10) - 1] + ' ' + parts[0];
            }
         }
         form.submit();
      });
   }

   document.addEventListener('click', function () {
      closeAllMenus();
   });
})();
</script>
@endpush
