<script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
<script>
  jQuery(function ($) {
    var $form = $('#barPurchaseListForm');
    var tbody = document.querySelector('.js-bar-pl-lines');
    var template = document.getElementById('barPurchaseListLineTemplate');
    var addBtn = document.querySelector('.js-bar-pl-add-line');

    function destroyItemSelect2($sel) {
      if ($sel.length && $sel.hasClass('select2-hidden-accessible')) {
        $sel.select2('destroy');
      }
    }

    function initItemSelect2($sel) {
      if (!$sel.length || !$.fn.select2) {
        return;
      }
      destroyItemSelect2($sel);
      $sel.select2({
        width: '100%',
        placeholder: $sel.data('placeholder') || 'Select item…',
        allowClear: true,
        dropdownParent: $(document.body),
      });
    }

    function initRowItemSelect(row) {
      initItemSelect2($(row).find('.js-bar-pl-item-select'));
    }

    function initAllItemSelects() {
      if (!tbody) {
        return;
      }
      $(tbody).find('.js-bar-pl-item-select').each(function () {
        initItemSelect2($(this));
      });
    }

    function reindexLineNames(row, index) {
      row.querySelectorAll('select[name^="lines["], input[name^="lines["]').forEach(function (el) {
        var suffix = el.name.replace(/^lines\[\d+\]/, '');
        el.name = 'lines[' + index + ']' + suffix;
      });
    }

    function reindex() {
      if (!tbody) {
        return;
      }
      $(tbody).find('.js-bar-pl-item-select').each(function () {
        destroyItemSelect2($(this));
      });
      tbody.querySelectorAll('.js-bar-pl-line').forEach(function (row, index) {
        reindexLineNames(row, index);
      });
      initAllItemSelects();
    }

    function addEmptyLine() {
      if (!template || !tbody) {
        return;
      }
      var index = tbody.querySelectorAll('.js-bar-pl-line').length;
      var html = template.innerHTML.replace(/__INDEX__/g, String(index));
      var wrap = document.createElement('tbody');
      wrap.innerHTML = html.trim();
      var row = wrap.firstElementChild;
      tbody.appendChild(row);
      initRowItemSelect(row);
    }

    if (tbody) {
      tbody.querySelectorAll('.js-bar-pl-line').forEach(initRowItemSelect);
    }

    if (addBtn) {
      addBtn.addEventListener('click', addEmptyLine);
    }

    function normalizeQtyText(value) {
      return String(value || '').trim().replace(/,/g, '');
    }

    function updateQtyChangeHint(input) {
      var prev = input.getAttribute('data-previous-qty');
      if (!prev) {
        return;
      }
      var hint = input.parentElement.querySelector('.bar-pl-qty-change');
      if (!hint) {
        return;
      }
      var cur = normalizeQtyText(input.value);
      if (cur === '' || cur === normalizeQtyText(prev)) {
        hint.classList.add('d-none');
        return;
      }
      hint.textContent = prev + '-' + cur;
      hint.classList.remove('d-none');
    }

    if (tbody) {
      tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('bar-pl-qty-input')) {
          updateQtyChangeHint(e.target);
        }
      });
      tbody.querySelectorAll('.bar-pl-qty-input').forEach(updateQtyChangeHint);
    }

    if (tbody) {
      tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-bar-pl-remove-line');
        if (!btn) {
          return;
        }
        var rows = tbody.querySelectorAll('.js-bar-pl-line');
        if (rows.length <= 1) {
          return;
        }
        var row = btn.closest('.js-bar-pl-line');
        destroyItemSelect2($(row).find('.js-bar-pl-item-select'));
        row.remove();
        reindex();
      });
    }
  });
</script>
