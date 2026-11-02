<script type="application/json" id="desk-payment-provider-config">@json($paymentOptions['payment_provider_config'] ?? ['bank_providers' => [], 'mobile_payment_providers' => []])</script>
<script>
  (function () {
    var paymentProviderConfig = { bank_providers: [], mobile_payment_providers: [] };
    try {
      var cfgEl = document.getElementById('desk-payment-provider-config');
      if (cfgEl) {
        paymentProviderConfig = JSON.parse(cfgEl.textContent || '{}');
      }
    } catch (e) {}

    var panel = document.querySelector('.js-desk-payment-panel');
    if (!panel) return;

    var inhouseHint = document.querySelector('.js-desk-inhouse-pay-hint');
    var waitTotalHint = panel.querySelector('.js-desk-pay-wait-total');
    var modeSelect = panel.querySelector('.js-desk-pay-mode');
    var providerWrap = panel.querySelector('.js-desk-pay-provider-wrap');
    var providerLabel = panel.querySelector('.js-desk-pay-provider-label');
    var providerSelect = panel.querySelector('.js-desk-pay-provider');
    var amountInput = panel.querySelector('.js-desk-pay-amount');
    var notesInput = panel.querySelector('.js-desk-pay-notes');
    var remainingWrap = panel.querySelector('.js-desk-pay-remaining-wrap');
    var remainingEl = panel.querySelector('.js-desk-pay-remaining');
    var currentTotal = 0;
    var walkInActive = false;
    var compactAmount = panel.getAttribute('data-compact-amount') === '1';
    var paymentCurrency = (panel.querySelector('.js-desk-pay-currency') || {}).textContent || 'TZS';
    paymentCurrency = (paymentCurrency || 'TZS').trim().toUpperCase();

    function formatPayAmount(value) {
      var v = Math.round((parseFloat(value) || 0) * 100) / 100;
      if (compactAmount) {
        if (Math.abs(v - Math.round(v)) < 0.001) {
          return String(Math.round(v));
        }
        return String(v).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
      }
      return v.toFixed(2);
    }

    function setPaymentCurrency(code) {
      if (!code) return;
      paymentCurrency = String(code).trim().toUpperCase();
      panel.querySelectorAll('.js-desk-pay-currency, .js-desk-pay-currency-suffix').forEach(function (el) {
        el.textContent = paymentCurrency;
      });
    }

    function reservationSelect() {
      return document.querySelector('.js-sw-reservation') || document.querySelector('.js-ld-reservation');
    }

    function readWalkInFromForm() {
      var res = reservationSelect();
      return !res || res.value === '';
    }

    function providerOptionsForMode(mode) {
      var value = (mode || '').toLowerCase();
      if (value.indexOf('bank') !== -1) {
        return paymentProviderConfig.bank_providers || [];
      }
      if (/mobile|m-pesa|mpesa|wallet|tigo|airtel|halopesa|upi/.test(value)) {
        return paymentProviderConfig.mobile_payment_providers || [];
      }
      return [];
    }

    function setFieldsEnabled(active) {
      [modeSelect, providerSelect, amountInput, notesInput].forEach(function (el) {
        if (el) {
          el.disabled = !active;
        }
      });
    }

    function syncProviderField(chargeable) {
      if (!modeSelect || !providerWrap || !providerSelect) return;
      var options = providerOptionsForMode(modeSelect.value);
      var previous = providerSelect.value || providerSelect.getAttribute('data-selected') || '';
      providerSelect.innerHTML = '<option value="">Select provider</option>';
      options.forEach(function (name) {
        var option = document.createElement('option');
        option.value = name;
        option.textContent = name;
        if (previous && previous === name) {
          option.selected = true;
        }
        providerSelect.appendChild(option);
      });
      providerSelect.removeAttribute('data-selected');
      var show = options.length > 0 && chargeable;
      providerWrap.classList.toggle('d-none', !show);
      providerSelect.required = show;
      if (providerLabel) {
        var modeValue = (modeSelect.value || '').toLowerCase();
        var label = modeValue.indexOf('bank') !== -1 ? 'Bank' : 'Mobile Payment Provider';
        providerLabel.innerHTML = label + (show ? ' <span class="text-danger">*</span>' : '');
      }
      if (!show) {
        providerSelect.value = '';
      }
    }

    function syncPanelVisibility() {
      var chargeable = walkInActive && currentTotal > 0.009;

      panel.style.display = walkInActive ? '' : 'none';
      if (inhouseHint) {
        inhouseHint.style.display = walkInActive ? 'none' : '';
      }
      if (waitTotalHint) {
        waitTotalHint.style.display = walkInActive && !chargeable ? '' : 'none';
      }

      setFieldsEnabled(chargeable);

      if (modeSelect) {
        modeSelect.required = chargeable;
      }
      if (amountInput) {
        amountInput.required = chargeable;
        if (chargeable && (parseFloat(amountInput.value || '0') || 0) <= 0) {
          amountInput.value = formatPayAmount(currentTotal);
        }
        if (!chargeable) {
          amountInput.value = compactAmount ? '' : '0';
        }
      }

      syncProviderField(chargeable);
      syncRemaining();
    }

    function syncRemaining() {
      if (!remainingWrap || !remainingEl || !amountInput) return;
      var paid = parseFloat(amountInput.value || '0') || 0;
      var remaining = Math.max(0, Math.round((currentTotal - paid) * 100) / 100);
      if (walkInActive && currentTotal > 0.009 && paid > 0 && remaining > 0.009) {
        remainingEl.textContent = formatPayAmount(remaining);
        remainingWrap.style.display = '';
      } else {
        remainingWrap.style.display = 'none';
      }
    }

    window.deskPaymentUpdateTotal = function (total, currency) {
      currentTotal = parseFloat(total) || 0;
      if (currency) {
        setPaymentCurrency(currency);
      }
      syncPanelVisibility();
    };

    window.deskPaymentSetWalkIn = function (isWalkIn) {
      walkInActive = !!isWalkIn;
      syncPanelVisibility();
    };

    if (modeSelect) {
      modeSelect.addEventListener('change', function () {
        syncProviderField(walkInActive && currentTotal > 0.009);
      });
    }
    if (amountInput) {
      amountInput.addEventListener('input', syncRemaining);
      amountInput.addEventListener('change', syncRemaining);
    }

    var res = reservationSelect();
    if (res) {
      res.addEventListener('change', function () {
        window.deskPaymentSetWalkIn(res.value === '');
      });
    }

    window.deskPaymentSetWalkIn(readWalkInFromForm());
  })();
</script>
