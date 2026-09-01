<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function ($) {
  var select2Lang = {
    noResults: function () { return 'Tidak ditemukan'; },
    searching: function () { return 'Mencari…'; }
  };

  function initSingle($el) {
    if ($el.hasClass('select2-hidden-accessible')) {
      $el.select2('destroy');
    }
    $el.select2({
      width: '100%',
      placeholder: $el.data('placeholder') || 'Cari…',
      allowClear: true,
      language: select2Lang
    });
  }

  function initMulti($el) {
    if ($el.hasClass('select2-hidden-accessible')) {
      $el.select2('destroy');
    }
    $el.select2({
      width: '100%',
      placeholder: $el.data('placeholder') || 'Pilih site…',
      closeOnSelect: false,
      language: select2Lang
    });
  }

  function initRow($row) {
    $row.find('.js-mitra-searchable').each(function () {
      initSingle($(this));
    });
    $row.find('.js-mitra-searchable-multi').each(function () {
      initMulti($(this));
    });
  }

  function reindexRows() {
    $('#js-company-rows .js-company-row').each(function (index) {
      var $row = $(this);
      $row.find('.js-company-ordinal').text(index + 1);
      $row.find('select.js-mitra-searchable').attr('name', 'scopes[' + index + '][perusahaan]');
      $row.find('select.js-mitra-searchable-multi').attr('name', 'scopes[' + index + '][sites][]');
    });
    $('#js-company-rows .js-remove-company').prop('disabled', $('#js-company-rows .js-company-row').length <= 1);
  }

  $('#js-company-rows .js-company-row').each(function () {
    initRow($(this));
  });
  initSingle($('#user_id'));
  reindexRows();

  $('#js-add-company').on('click', function () {
    var template = document.getElementById('js-company-row-template');
    if (!template) {
      return;
    }
    var index = $('#js-company-rows .js-company-row').length;
    var html = template.innerHTML.replaceAll('__INDEX__', String(index));
    var $row = $(html);
    $('#js-company-rows').append($row);
    initRow($row);
    reindexRows();
  });

  $('#js-company-rows').on('click', '.js-remove-company', function () {
    if ($('#js-company-rows .js-company-row').length <= 1) {
      return;
    }
    $(this).closest('.js-company-row').remove();
    reindexRows();
  });
})(jQuery);
</script>
