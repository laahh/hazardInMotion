@php
   $filters = $filters ?? ['filter_date' => '', 'site' => '', 'perusahaan' => '', 'pipeline_stage' => '', 'sid' => '', 'nama' => '', 'q' => ''];
   $filterOptions = $filterOptions ?? ['dates' => collect(), 'sites' => collect(), 'perusahaan' => collect(), 'pipelineStages' => collect()];

   $dateLabel = $filters['filter_date'] !== '' ? \Carbon\Carbon::parse($filters['filter_date'])->format('d M Y') : 'Semua Tanggal';
   $siteLabel = $filters['site'] !== '' ? $filters['site'] : 'Semua Site';
   $perusahaanLabel = $filters['perusahaan'] !== '' ? $filters['perusahaan'] : 'Semua Perusahaan';
   $stageLabel = ($filters['pipeline_stage'] ?? '') === '' || ($filters['pipeline_stage'] ?? '') === 'all'
      ? 'Semua Tahapan'
      : (collect($filterOptions['pipelineStages'])->firstWhere('value', $filters['pipeline_stage'])['label'] ?? 'Semua Tahapan');

   $pickerBtnClass = 'inline-flex w-full items-center gap-2.5 rounded-2xl border border-outline-variant/15 bg-white/80 backdrop-blur-sm px-3.5 py-2.5 text-left shadow-sm transition-all duration-300 hover:border-primary/20 hover:shadow-md sm:w-auto sm:min-w-[10.5rem]';
   $dropdownClass = 'ab-filter-dropdown hidden absolute left-0 right-auto top-full z-50 mt-2 max-h-64 w-72 overflow-y-auto rounded-2xl border border-outline-variant/15 bg-white py-2 shadow-lg sm:left-auto sm:right-0';
   $optionClass = 'flex w-full items-center px-4 py-2.5 text-left text-sm font-medium text-on-surface transition-colors duration-200 hover:bg-primary/[0.04]';
@endphp

@php
   $filterRoute = $filterRoute ?? 'auto-banned.pipeline-monitoring.index';
@endphp

<form method="GET" action="{{ route($filterRoute) }}" id="ab-pipeline-filter-form" class="flex flex-wrap items-center justify-end gap-2.5">
   @if(($filters['sid'] ?? '') !== '')
   <input type="hidden" name="sid" value="{{ $filters['sid'] }}"/>
   @endif
   @if(($filters['nama'] ?? '') !== '')
   <input type="hidden" name="nama" value="{{ $filters['nama'] }}"/>
   @endif
   @if(($filters['q'] ?? '') !== '')
   <input type="hidden" name="q" value="{{ $filters['q'] }}"/>
   @endif
   @if(($filters['ban_scope'] ?? '') !== '')
   <input type="hidden" name="ban_scope" value="{{ $filters['ban_scope'] }}"/>
   @endif
   @foreach($preserveParams ?? [] as $paramKey => $paramValue)
   @if($paramValue !== '' && $paramValue !== null)
   <input type="hidden" name="{{ $paramKey }}" value="{{ $paramValue }}"/>
   @endif
   @endforeach

   <div class="relative" data-ab-filter-wrap>
      <button type="button" data-ab-filter-toggle class="{{ $pickerBtnClass }}" aria-haspopup="listbox" aria-expanded="false">
         <span class="material-symbols-outlined text-primary/80 text-lg shrink-0">calendar_today</span>
         <span class="flex min-w-0 flex-1 flex-col items-start leading-tight">
            <span class="text-[9px] font-semibold uppercase tracking-wider text-on-surface-variant/70">Tanggal</span>
            <span id="ab-pipeline-date-label" class="truncate text-xs font-semibold text-on-surface">{{ $dateLabel }}</span>
         </span>
         <span class="material-symbols-outlined text-on-surface-variant/50 text-lg shrink-0">expand_more</span>
      </button>
      <div class="{{ $dropdownClass }}" data-ab-filter-menu role="listbox">
         <button type="button" class="{{ $optionClass }} ab-filter-option" data-name="filter_date" data-value="" data-label="Semua Tanggal">Semua Tanggal</button>
         @foreach($filterOptions['dates'] as $date)
         @php $formatted = \Carbon\Carbon::parse($date)->format('d M Y'); @endphp
         <button type="button" class="{{ $optionClass }} ab-filter-option" data-name="filter_date" data-value="{{ $date }}" data-label="{{ $formatted }}">{{ $formatted }}</button>
         @endforeach
      </div>
      <input type="hidden" name="filter_date" id="ab-pipeline-filter-date" value="{{ $filters['filter_date'] }}"/>
   </div>

   <div class="relative" data-ab-filter-wrap>
      <button type="button" data-ab-filter-toggle class="{{ $pickerBtnClass }}" aria-haspopup="listbox" aria-expanded="false">
         <span class="material-symbols-outlined text-primary/80 text-lg shrink-0">location_on</span>
         <span class="flex min-w-0 flex-1 flex-col items-start leading-tight">
            <span class="text-[9px] font-semibold uppercase tracking-wider text-on-surface-variant/70">Site</span>
            <span id="ab-pipeline-site-label" class="truncate text-xs font-semibold text-on-surface max-w-[8rem]">{{ $siteLabel }}</span>
         </span>
         <span class="material-symbols-outlined text-on-surface-variant/50 text-lg shrink-0">expand_more</span>
      </button>
      <div class="{{ $dropdownClass }}" data-ab-filter-menu role="listbox">
         <button type="button" class="{{ $optionClass }} ab-filter-option" data-name="site" data-value="" data-label="Semua Site">Semua Site</button>
         @foreach($filterOptions['sites'] as $site)
         <button type="button" class="{{ $optionClass }} ab-filter-option" data-name="site" data-value="{{ $site }}" data-label="{{ $site }}">{{ $site }}</button>
         @endforeach
      </div>
      <input type="hidden" name="site" id="ab-pipeline-filter-site" value="{{ $filters['site'] }}"/>
   </div>

   <div class="relative" data-ab-filter-wrap>
      <button type="button" data-ab-filter-toggle class="{{ $pickerBtnClass }}" aria-haspopup="listbox" aria-expanded="false">
         <span class="material-symbols-outlined text-primary/80 text-lg shrink-0">domain</span>
         <span class="flex min-w-0 flex-1 flex-col items-start leading-tight">
            <span class="text-[9px] font-semibold uppercase tracking-wider text-on-surface-variant/70">Perusahaan</span>
            <span id="ab-pipeline-perusahaan-label" class="truncate text-xs font-semibold text-on-surface max-w-[8rem]">{{ $perusahaanLabel }}</span>
         </span>
         <span class="material-symbols-outlined text-on-surface-variant/50 text-lg shrink-0">expand_more</span>
      </button>
      <div class="{{ $dropdownClass }}" data-ab-filter-menu role="listbox">
         <button type="button" class="{{ $optionClass }} ab-filter-option" data-name="perusahaan" data-value="" data-label="Semua Perusahaan">Semua Perusahaan</button>
         @foreach($filterOptions['perusahaan'] as $perusahaan)
         <button type="button" class="{{ $optionClass }} ab-filter-option" data-name="perusahaan" data-value="{{ $perusahaan }}" data-label="{{ $perusahaan }}">{{ $perusahaan }}</button>
         @endforeach
      </div>
      <input type="hidden" name="perusahaan" id="ab-pipeline-filter-perusahaan" value="{{ $filters['perusahaan'] }}"/>
   </div>

   <div class="relative" data-ab-filter-wrap>
      <button type="button" data-ab-filter-toggle class="{{ $pickerBtnClass }}" aria-haspopup="listbox" aria-expanded="false">
         <span class="material-symbols-outlined text-primary/80 text-lg shrink-0">timeline</span>
         <span class="flex min-w-0 flex-1 flex-col items-start leading-tight">
            <span class="text-[9px] font-semibold uppercase tracking-wider text-on-surface-variant/70">Tahapan</span>
            <span id="ab-pipeline-stage-label" class="truncate text-xs font-semibold text-on-surface max-w-[8rem]">{{ $stageLabel }}</span>
         </span>
         <span class="material-symbols-outlined text-on-surface-variant/50 text-lg shrink-0">expand_more</span>
      </button>
      <div class="{{ $dropdownClass }}" data-ab-filter-menu role="listbox">
         @foreach($filterOptions['pipelineStages'] as $stage)
         <button type="button" class="{{ $optionClass }} ab-filter-option" data-name="pipeline_stage" data-value="{{ $stage['value'] }}" data-label="{{ $stage['label'] }}">{{ $stage['label'] }}</button>
         @endforeach
      </div>
      <input type="hidden" name="pipeline_stage" id="ab-pipeline-filter-stage" value="{{ $filters['pipeline_stage'] }}"/>
   </div>
</form>

@push('scripts')
<script>
(function () {
   var form = document.getElementById('ab-pipeline-filter-form');
   if (!form) return;

   var labelMap = {
      filter_date: 'ab-pipeline-date-label',
      site: 'ab-pipeline-site-label',
      perusahaan: 'ab-pipeline-perusahaan-label',
      pipeline_stage: 'ab-pipeline-stage-label'
   };

   function closeAllMenus(except) {
      form.querySelectorAll('[data-ab-filter-menu]').forEach(function (menu) {
         if (menu === except) return;
         menu.classList.add('hidden');
         var toggle = menu.parentElement && menu.parentElement.querySelector('[data-ab-filter-toggle]');
         if (toggle) toggle.setAttribute('aria-expanded', 'false');
      });
   }

   form.querySelectorAll('[data-ab-filter-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
         e.stopPropagation();
         var menu = btn.parentElement.querySelector('[data-ab-filter-menu]');
         var isOpen = menu && !menu.classList.contains('hidden');
         closeAllMenus();
         if (menu && !isOpen) {
            menu.classList.remove('hidden');
            btn.setAttribute('aria-expanded', 'true');
         }
      });
   });

   form.querySelectorAll('.ab-filter-option').forEach(function (opt) {
      opt.addEventListener('click', function () {
         var name = opt.getAttribute('data-name');
         var value = opt.getAttribute('data-value') || '';
         var label = opt.getAttribute('data-label') || '';
         var input = form.querySelector('[name="' + name + '"]');
         if (input) input.value = value;
         var labelId = labelMap[name];
         var labelEl = labelId ? document.getElementById(labelId) : null;
         if (labelEl) labelEl.textContent = label;
         closeAllMenus();
         form.submit();
      });
   });

   document.addEventListener('click', function () {
      closeAllMenus();
   });
})();
</script>
@endpush
