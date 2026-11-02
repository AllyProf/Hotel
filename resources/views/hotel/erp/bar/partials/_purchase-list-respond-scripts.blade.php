@if(! ($locked ?? false))
  <script>
    (function () {
      var catalogEl = document.getElementById('barRespondCatalogData');
      var catalog = catalogEl ? JSON.parse(catalogEl.textContent || '[]') : [];
      var tbody = document.querySelector('.js-bar-respond-lines');
      var template = document.getElementById('barRespondLineTemplate');
      var addBtn = document.querySelector('.js-bar-respond-add-line');
      var menu = document.getElementById('barRespondItemSuggestions');
      var activeSearch = null;
      if (!tbody) return;

      if (menu && menu.parentElement !== document.body) {
        document.body.appendChild(menu);
      }

      var catalogByName = {};
      catalog.forEach(function (item) {
        catalogByName[String(item.name || '').toLowerCase()] = item;
      });

      function normalizeQuery(value) {
        return String(value || '').trim().toLowerCase();
      }

      function filterCatalog(query) {
        var q = normalizeQuery(query);
        if (!catalog.length) {
          return [];
        }
        if (q.length < 1) {
          return catalog.slice(0, 25);
        }
        return catalog.filter(function (item) {
          return item.label.toLowerCase().indexOf(q) !== -1
            || item.name.toLowerCase().indexOf(q) !== -1
            || (item.category && item.category.toLowerCase().indexOf(q) !== -1);
        }).slice(0, 25);
      }

      function hideMenu() {
        if (!menu) return;
        menu.classList.add('d-none');
        menu.innerHTML = '';
        activeSearch = null;
        document.querySelectorAll('.js-bar-respond-item-search').forEach(function (input) {
          input.setAttribute('aria-expanded', 'false');
        });
      }

      function positionMenu(input) {
        if (!menu || !input) return;
        var rect = input.getBoundingClientRect();
        menu.style.left = rect.left + 'px';
        menu.style.top = (rect.bottom + 2) + 'px';
        menu.style.width = Math.max(rect.width, 240) + 'px';
      }

      function showMenu(input, matches) {
        if (!menu) return;
        activeSearch = input;
        input.setAttribute('aria-expanded', matches.length ? 'true' : 'false');
        menu.innerHTML = '';
        if (!matches.length) {
          menu.classList.add('d-none');
          return;
        }
        matches.forEach(function (item, index) {
          var li = document.createElement('li');
          li.setAttribute('role', 'option');
          li.dataset.itemName = item.name;
          li.textContent = item.label;
          if (index === 0) li.classList.add('is-active');
          menu.appendChild(li);
        });
        positionMenu(input);
        menu.classList.remove('d-none');
      }

      function applyCatalogItem(row, item) {
        if (!row || !item) return;
        var nameInput = row.querySelector('.js-bar-respond-item-name');
        var searchInput = row.querySelector('.js-bar-respond-item-search');
        if (nameInput) nameInput.value = item.name;
        if (searchInput) searchInput.value = item.label;
        if (item.unit) {
          var unitSelect = row.querySelector('select[name*="[unit]"]');
          if (unitSelect) {
            var found = false;
            Array.prototype.forEach.call(unitSelect.options, function (opt) {
              if (opt.value === item.unit) {
                unitSelect.value = item.unit;
                found = true;
              }
            });
            if (!found && item.unit) {
              var opt = document.createElement('option');
              opt.value = item.unit;
              opt.textContent = item.unit;
              opt.selected = true;
              unitSelect.appendChild(opt);
            }
          }
        }
        if (item.expectedPrice !== null && item.expectedPrice !== undefined) {
          var costInput = row.querySelector('.js-bar-respond-cost');
          if (costInput && costInput.value === '') {
            costInput.value = String(item.expectedPrice);
          }
        }
        hideMenu();
        refreshTotals();
      }

      function bindPicker(picker) {
        if (!picker || picker.dataset.bound) return;
        picker.dataset.bound = '1';
        var searchInput = picker.querySelector('.js-bar-respond-item-search');
        var nameInput = picker.querySelector('.js-bar-respond-item-name');
        if (!searchInput || !nameInput) return;

        searchInput.addEventListener('focus', function () {
          showMenu(searchInput, filterCatalog(searchInput.value));
        });

        searchInput.addEventListener('input', function () {
          nameInput.value = searchInput.value.trim();
          showMenu(searchInput, filterCatalog(searchInput.value));
        });

        searchInput.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') {
            hideMenu();
            return;
          }
          if (!menu || menu.classList.contains('d-none')) return;
          var options = menu.querySelectorAll('li');
          if (!options.length) return;
          var activeIndex = 0;
          options.forEach(function (li, idx) {
            if (li.classList.contains('is-active')) activeIndex = idx;
          });
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            options[activeIndex].classList.remove('is-active');
            activeIndex = (activeIndex + 1) % options.length;
            options[activeIndex].classList.add('is-active');
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            options[activeIndex].classList.remove('is-active');
            activeIndex = (activeIndex - 1 + options.length) % options.length;
            options[activeIndex].classList.add('is-active');
          } else if (e.key === 'Enter') {
            var active = menu.querySelector('li.is-active');
            if (active && active.dataset.itemName) {
              e.preventDefault();
              var row = picker.closest('.js-bar-respond-line');
              var item = catalogByName[String(active.dataset.itemName).toLowerCase()];
              applyCatalogItem(row, item);
            }
          }
        });

        searchInput.addEventListener('blur', function () {
          window.setTimeout(function () {
            if (!activeSearch || activeSearch !== searchInput) return;
            hideMenu();
          }, 150);
        });
      }

      function bindRow(row) {
        bindPicker(row.querySelector('.bar-respond-item-picker'));
      }

      if (menu) {
        menu.addEventListener('mousedown', function (e) {
          e.preventDefault();
        });
        menu.addEventListener('click', function (e) {
          var li = e.target.closest('li[data-item-name]');
          if (!li || !activeSearch) return;
          var row = activeSearch.closest('.js-bar-respond-line');
          var item = catalogByName[String(li.dataset.itemName).toLowerCase()];
          applyCatalogItem(row, item);
        });
      }

      document.addEventListener('click', function (e) {
        if (e.target.closest('.bar-respond-item-picker') || e.target.closest('#barRespondItemSuggestions')) {
          return;
        }
        hideMenu();
      });

      window.addEventListener('scroll', function () {
        if (activeSearch) positionMenu(activeSearch);
      }, true);
      window.addEventListener('resize', function () {
        if (activeSearch) positionMenu(activeSearch);
      });

      function lineTotal(row) {
        var qty = parseInt(row.querySelector('.js-bar-respond-qty')?.value || '0', 10);
        var cost = parseFloat(row.querySelector('.js-bar-respond-cost')?.value || '');
        if (!qty || isNaN(cost) || row.querySelector('.js-bar-respond-cost')?.value === '') {
          return null;
        }
        return Math.round(qty * cost * 100) / 100;
      }

      function refreshTotals() {
        var grand = 0;
        var hasAny = false;
        tbody.querySelectorAll('.js-bar-respond-line').forEach(function (row) {
          var total = lineTotal(row);
          var el = row.querySelector('.js-bar-respond-line-total');
          if (total === null) {
            if (el) el.textContent = '—';
          } else {
            hasAny = true;
            grand += total;
            if (el) el.textContent = total.toFixed(2);
          }
        });
        var grandEl = document.querySelector('.js-bar-respond-grand-total');
        if (grandEl) grandEl.textContent = hasAny ? grand.toFixed(2) : '0.00';
      }

      function reindex() {
        tbody.querySelectorAll('.js-bar-respond-line').forEach(function (row, index) {
          row.querySelectorAll('[name^="lines["]').forEach(function (input) {
            input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
          });
          delete row.querySelector('.bar-respond-item-picker')?.dataset.bound;
        });
        tbody.querySelectorAll('.js-bar-respond-line').forEach(bindRow);
      }

      tbody.addEventListener('input', function () {
        refreshTotals();
      });
      tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-bar-respond-remove');
        if (!btn) return;
        if (tbody.querySelectorAll('.js-bar-respond-line').length <= 1) return;
        btn.closest('.js-bar-respond-line').remove();
        reindex();
        refreshTotals();
      });

      tbody.querySelectorAll('.js-bar-respond-line').forEach(bindRow);
      refreshTotals();

      if (addBtn && template) {
        addBtn.addEventListener('click', function () {
          var index = tbody.querySelectorAll('.js-bar-respond-line').length;
          var html = template.innerHTML.replace(/__INDEX__/g, String(index));
          var wrap = document.createElement('tbody');
          wrap.innerHTML = html.trim();
          var newRow = wrap.firstElementChild;
          tbody.appendChild(newRow);
          reindex();
          bindRow(newRow);
          refreshTotals();
          var search = newRow.querySelector('.js-bar-respond-item-search');
          if (search) {
            search.focus();
            showMenu(search, filterCatalog(''));
          }
        });
      }
    })();
  </script>
@endif
