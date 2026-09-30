<script>
(function () {
   var container = document.getElementById('ra-scope-rows');
   var template = document.getElementById('ra-row-template');
   var addButton = document.getElementById('ra-add-company');
   var allSitesLabel = @json(\App\Models\MonitoringSafetyEngineeringPicAssignment::ALL_SITES);

   if (!container) {
      return;
   }

   function rows() {
      return Array.prototype.slice.call(container.querySelectorAll('[data-ra-row]'));
   }

   function isAllSitesValue(value) {
      var key = String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');
      return ['all site', 'all sites', 'allsite', 'semua site', 'all'].indexOf(key) !== -1;
   }

   function reindex() {
      var list = rows();

      list.forEach(function (row, index) {
         var ordinal = row.querySelector('[data-ra-ordinal]');
         if (ordinal) {
            ordinal.textContent = String(index + 1);
         }

         var company = row.querySelector('[data-ra-company]');
         if (company) {
            company.name = 'scopes[' + index + '][perusahaan]';
         }

         row.querySelectorAll('[data-ra-sites] input[type="checkbox"]').forEach(function (input) {
            input.name = 'scopes[' + index + '][sites][]';
         });
      });

      list.forEach(function (row) {
         var remove = row.querySelector('[data-ra-remove]');
         if (remove) {
            remove.disabled = list.length <= 1;
         }
      });
   }

   // "ALL SITE" dan site spesifik saling eksklusif dalam satu perusahaan.
   function syncAllSites(row, changed) {
      var inputs = Array.prototype.slice.call(row.querySelectorAll('[data-ra-sites] input[type="checkbox"]'));
      var allInput = inputs.filter(function (input) { return isAllSitesValue(input.value); })[0];

      if (!allInput) {
         return;
      }

      if (changed === allInput && allInput.checked) {
         inputs.forEach(function (input) {
            if (input !== allInput) {
               input.checked = false;
            }
         });

         return;
      }

      if (changed !== allInput && changed.checked) {
         allInput.checked = false;
      }
   }

   container.addEventListener('change', function (event) {
      var input = event.target;
      if (!input.matches('[data-ra-sites] input[type="checkbox"]')) {
         return;
      }

      var row = input.closest('[data-ra-row]');
      if (row) {
         syncAllSites(row, input);
      }
   });

   container.addEventListener('click', function (event) {
      var button = event.target.closest('[data-ra-remove]');
      if (!button || rows().length <= 1) {
         return;
      }

      var row = button.closest('[data-ra-row]');
      if (row) {
         row.remove();
         reindex();
      }
   });

   if (addButton && template) {
      addButton.addEventListener('click', function () {
         var index = rows().length;
         var markup = template.innerHTML.split('__INDEX__').join(String(index));
         var holder = document.createElement('div');
         holder.innerHTML = markup.trim();

         var row = holder.firstElementChild;
         if (row) {
            container.appendChild(row);
            reindex();
            var select = row.querySelector('[data-ra-company]');
            if (select) {
               select.focus();
            }
         }
      });
   }

   var sidInput = document.getElementById('sid');
   if (sidInput) {
      sidInput.addEventListener('blur', function () {
         sidInput.value = sidInput.value.trim().toUpperCase();
      });
   }

   document.querySelector('form[data-ra-form]')?.addEventListener('submit', function (event) {
      var invalid = rows().filter(function (row) {
         return row.querySelectorAll('[data-ra-sites] input[type="checkbox"]:checked').length === 0;
      });

      if (invalid.length > 0) {
         event.preventDefault();
         window.alert('Setiap perusahaan harus punya minimal satu site (pilih "' + allSitesLabel + '" untuk semua site).');
         invalid[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
   });

   reindex();
})();
</script>
