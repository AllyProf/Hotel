<div class="modal fade" id="posPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="POST" action="#" id="posPaymentForm">
      @csrf
      <div class="modal-content">
        <div class="modal-header pos-modal-header">
          <h5 class="modal-title">Add Payment</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="pos-modal-divider"></div>
          <div class="form-group">
            <label class="control-label">Type of Payment</label>
            <select name="payment_type" class="form-control" id="posPaymentType">
              @foreach($paymentTypes as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label class="control-label">Amount</label>
            <input type="number" step="{{ ($currencyDisplay['decimals'] ?? 2) > 0 ? '0.01' : '1' }}" min="0" name="amount" class="form-control" id="posPaymentAmount" readonly>
          </div>
          <div class="form-group d-none" id="posRoomBillGroup">
            <label class="control-label">Select Room</label>
            <select name="cm_reservation_id" class="form-control" id="posRoomBillSelect">
              <option value="">Choose in-house guest…</option>
              @foreach($reservations as $res)
                @php
                  $room = $res->roomUnit?->room_number ?? $res->room_no ?? '';
                  $guest = $res->guestName();
                @endphp
                <option value="{{ $res->id }}">{{ $room }} — {{ $guest }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group d-none" id="posCompanyBillGroup">
            <label class="control-label">Select Company</label>
            <select name="hotel_company_id" class="form-control" id="posCompanyBillSelect">
              <option value="">Choose company…</option>
              @forelse($companies ?? [] as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
              @empty
                <option value="" disabled>No companies — add under PMS → Companies</option>
              @endforelse
            </select>
          </div>
          <div class="form-group">
            <label class="control-label">Comments</label>
            <textarea name="comments" class="form-control" rows="2" id="posPaymentComments"></textarea>
          </div>
        </div>
        <div class="modal-footer pos-modal-footer">
          <button type="button" class="btn btn-pos-outline" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit</button>
        </div>
      </div>
    </form>
  </div>
</div>
