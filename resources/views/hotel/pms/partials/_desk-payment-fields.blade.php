@php
  $paymentOptions = $paymentOptions ?? [];
  $modes = $paymentOptions['walk_in_payment_modes'] ?? ($paymentOptions['payment_modes'] ?? ['Cash']);
  $defaultMode = old('payment_mode', $paymentOptions['default_payment_mode'] ?? 'Cash');
  if (! in_array($defaultMode, $modes, true)) {
    $defaultMode = $modes[0] ?? 'Cash';
  }
  $currency = $paymentOptions['currency'] ?? 'TZS';
  $deskPaymentHideIntro = ! empty($deskPaymentHideIntro);
  $deskPaymentHideInhouseHint = ! empty($deskPaymentHideInhouseHint);
  $deskPaymentCompactAmount = ! empty($deskPaymentCompactAmount);
  $amountPaidDefault = old('amount_paid');
  if ($amountPaidDefault === null || $amountPaidDefault === '') {
    $amountPaidDefault = $deskPaymentCompactAmount ? '' : 0;
  }
@endphp

<div class="js-desk-payment-panel sw-panel mb-3" style="display:none;"
  @if($deskPaymentCompactAmount) data-compact-amount="1" @endif>
  <p class="sw-panel__title mb-1"><i class="fa fa-money"></i> Walk-in payment</p>
  @if(! $deskPaymentHideIntro)
    <p class="text-muted small mb-3 js-desk-pay-intro">Record how the external guest paid (method, amount, reference).</p>
  @endif
  <p class="text-warning small mb-3 js-desk-pay-wait-total" style="display:none;">
    <i class="fa fa-info-circle"></i> Set the rate and items above first — payment fields activate when there is an amount to collect.
  </p>
  <div class="row">
    <div class="col-md-3">
      <div class="form-group">
        <label class="control-label">Payment method <span class="text-danger js-desk-pay-required">*</span></label>
        <select class="form-control js-desk-pay-mode" name="payment_mode">
          @foreach($modes as $mode)
            <option value="{{ $mode }}" {{ $defaultMode === $mode ? 'selected' : '' }}>{{ $mode }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="col-md-3 js-desk-pay-provider-wrap d-none">
      <div class="form-group">
        <label class="control-label js-desk-pay-provider-label">Provider</label>
        <select class="form-control js-desk-pay-provider" name="payment_provider"
          data-selected="{{ old('payment_provider') }}">
          <option value="">Select provider</option>
        </select>
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-group">
        <label class="control-label">Amount paid <span class="text-danger js-desk-pay-amount-required">*</span></label>
        <div class="input-group">
          <input type="number" step="{{ $deskPaymentCompactAmount ? 'any' : '0.01' }}" min="0" class="form-control js-desk-pay-amount" name="amount_paid"
            value="{{ $amountPaidDefault }}" placeholder="{{ $deskPaymentCompactAmount ? '0' : '' }}">
          <div class="input-group-append">
            <span class="input-group-text js-desk-pay-currency">{{ $currency }}</span>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-group">
        <label class="control-label">Payment reference / notes</label>
        <input type="text" class="form-control js-desk-pay-notes" name="payment_notes" maxlength="500"
          value="{{ old('payment_notes') }}" placeholder="Receipt no., M-Pesa ref…">
      </div>
    </div>
    <div class="col-md-12">
      <p class="text-muted small mb-0 js-desk-pay-remaining-wrap" style="display:none;">
        Balance still due: <strong class="js-desk-pay-remaining">0.00</strong> <span class="js-desk-pay-currency-suffix">{{ $currency }}</span>
      </p>
    </div>
  </div>
</div>

@if(! $deskPaymentHideInhouseHint)
  <p class="text-muted small js-desk-inhouse-pay-hint mb-3" style="display:none;">
    <i class="fa fa-bed"></i> In-house guest — payment is collected on the room folio (no walk-in payment here).
  </p>
@endif
