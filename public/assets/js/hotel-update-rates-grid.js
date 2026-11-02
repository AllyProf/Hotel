(function () {
  var root = document.getElementById('updateRatesPage');
  if (!root) {
    return;
  }

  var indexUrl = root.getAttribute('data-rates-index-url') || '';
  var datePicker = document.getElementById('startDatePicker');
  var dateLabel = document.querySelector('.rates-date-nav .date-label');
  var resetBtn = document.getElementById('resetRatesBtn');
  var table = document.getElementById('updateRatesTable');
  var scrollEl = document.getElementById('ratesGridScroll');
  var form = document.getElementById('updateRatesForm');
  var dirtyBadge = document.getElementById('ratesDirtyBadge');
  var allowLeave = false;

  function normalizeRateValue(value) {
    if (value === null || value === undefined) {
      return '';
    }
    var trimmed = String(value).trim();
    if (trimmed === '') {
      return '';
    }
    var num = Number(trimmed);
    return Number.isFinite(num) ? String(Math.trunc(num)) : trimmed;
  }

  function isRateInputDirty(input) {
    return normalizeRateValue(input.value) !== normalizeRateValue(input.getAttribute('data-original') || '');
  }

  function isToggleDirty(input) {
    var original = input.getAttribute('data-original') === '1';
    return input.checked !== original;
  }

  function refreshDirtyState() {
    var dirtyCount = 0;

    if (table) {
      table.querySelectorAll('.js-rate-input').forEach(function (input) {
        var dirty = isRateInputDirty(input);
        input.classList.toggle('is-dirty', dirty);
        if (dirty) {
          dirtyCount += 1;
        }
      });

      table.querySelectorAll('.js-dynamic-toggle').forEach(function (input) {
        var dirty = isToggleDirty(input);
        input.classList.toggle('is-dirty', dirty);
        if (dirty) {
          dirtyCount += 1;
        }
      });
    }

    if (dirtyBadge) {
      if (dirtyCount > 0) {
        dirtyBadge.textContent = dirtyCount + ' unsaved';
        dirtyBadge.classList.remove('d-none');
      } else {
        dirtyBadge.textContent = '';
        dirtyBadge.classList.add('d-none');
      }
    }

    return dirtyCount;
  }

  if (dateLabel && datePicker && indexUrl) {
    dateLabel.addEventListener('click', function () {
      if (datePicker.showPicker) {
        datePicker.showPicker();
      } else {
        datePicker.click();
      }
    });
    datePicker.addEventListener('change', function () {
      if (!this.value) {
        return;
      }
      if (refreshDirtyState() > 0 && !window.confirm('You have unsaved rate changes. Leave this page?')) {
        this.value = this.defaultValue;
        return;
      }
      allowLeave = true;
      window.location.href = indexUrl + '?start_date=' + encodeURIComponent(this.value);
    });
  }

  if (resetBtn && table) {
    resetBtn.addEventListener('click', function () {
      table.querySelectorAll('.js-rate-input').forEach(function (input) {
        input.value = input.getAttribute('data-original') || '';
      });
      table.querySelectorAll('.js-dynamic-toggle').forEach(function (cb) {
        cb.checked = cb.getAttribute('data-original') === '1';
      });
      refreshDirtyState();
    });
  }

  if (table) {
    table.addEventListener('input', function (event) {
      if (event.target && event.target.classList.contains('js-rate-input')) {
        refreshDirtyState();
      }
    });

    table.addEventListener('change', function (event) {
      if (event.target && event.target.classList.contains('js-dynamic-toggle')) {
        refreshDirtyState();
      }
    });

    initRateCellKeyboardNav(table);
  }

  if (form) {
    form.addEventListener('submit', function () {
      allowLeave = true;
    });
  }

  window.addEventListener('beforeunload', function (event) {
    if (allowLeave || refreshDirtyState() === 0) {
      return;
    }
    event.preventDefault();
    event.returnValue = '';
  });

  document.querySelectorAll('.rates-date-nav .nav-btn').forEach(function (link) {
    link.addEventListener('click', function (event) {
      if (refreshDirtyState() === 0) {
        allowLeave = true;
        return;
      }
      if (!window.confirm('You have unsaved rate changes. Leave this page?')) {
        event.preventDefault();
      } else {
        allowLeave = true;
      }
    });
  });

  if (scrollEl) {
    scrollEl.setAttribute('tabindex', '0');
  }

  refreshDirtyState();

  function initRateCellKeyboardNav(gridTable) {
    var inputs = Array.prototype.slice.call(gridTable.querySelectorAll('.js-rate-input'));
    if (inputs.length === 0) {
      return;
    }

    var colsPerRow = 0;
    var firstRow = gridTable.querySelector('tr.rates-row-plan');
    if (firstRow) {
      colsPerRow = firstRow.querySelectorAll('.js-rate-input').length;
    }
    if (colsPerRow < 1) {
      colsPerRow = 1;
    }

    inputs.forEach(function (input, index) {
      input.addEventListener('keydown', function (event) {
        var targetIndex = null;

        if (event.key === 'Enter') {
          event.preventDefault();
          targetIndex = index + colsPerRow;
          if (targetIndex >= inputs.length) {
            targetIndex = index + 1;
          }
        } else if (event.key === 'ArrowRight') {
          event.preventDefault();
          targetIndex = index + 1;
        } else if (event.key === 'ArrowLeft') {
          event.preventDefault();
          targetIndex = index - 1;
        } else if (event.key === 'ArrowDown') {
          event.preventDefault();
          targetIndex = index + colsPerRow;
        } else if (event.key === 'ArrowUp') {
          event.preventDefault();
          targetIndex = index - colsPerRow;
        }

        if (targetIndex === null || targetIndex < 0 || targetIndex >= inputs.length) {
          return;
        }

        inputs[targetIndex].focus();
        if (typeof inputs[targetIndex].select === 'function') {
          inputs[targetIndex].select();
        }
      });
    });
  }
})();
