<form action="{{ route('hotel.settings.update') }}" method="POST">
  @csrf
  @method('PUT')
  <input type="hidden" name="tab" value="whatsapp">

  <label class="animated-checkbox d-block mb-3">
    <input type="checkbox" name="enabled" value="1" {{ ($whatsapp['enabled'] ?? false) ? 'checked' : '' }}>
    <span class="label-text">Enable WhatsApp messaging</span>
  </label>

  <div class="row">
    <div class="col-md-6">
      <div class="form-group">
        <label class="control-label">Sender Number</label>
        <input class="form-control" type="text" name="sender_number" value="{{ $whatsapp['sender_number'] ?? '' }}" placeholder="+255...">
      </div>
    </div>
  </div>

  <h5 class="mt-3 mb-2">Message Triggers</h5>
  <label class="animated-checkbox d-block"><input type="checkbox" name="booking_confirmation" value="1" {{ ($whatsapp['booking_confirmation'] ?? true) ? 'checked' : '' }}><span class="label-text">Send on booking confirmation</span></label>
  <label class="animated-checkbox d-block"><input type="checkbox" name="checkin_reminder" value="1" {{ ($whatsapp['checkin_reminder'] ?? true) ? 'checked' : '' }}><span class="label-text">Send check-in reminder</span></label>
  <label class="animated-checkbox d-block mb-3"><input type="checkbox" name="checkout_reminder" value="1" {{ ($whatsapp['checkout_reminder'] ?? false) ? 'checked' : '' }}><span class="label-text">Send check-out reminder</span></label>

  <h5 class="mb-2">Message Templates</h5>
  <div class="form-group">
    <label class="control-label">Booking confirmation</label>
    <textarea class="form-control" rows="2" name="template_booking">{{ $whatsapp['template_booking'] ?? '' }}</textarea>
  </div>
  <div class="form-group">
    <label class="control-label">Check-in reminder</label>
    <textarea class="form-control" rows="2" name="template_checkin">{{ $whatsapp['template_checkin'] ?? '' }}</textarea>
  </div>

  <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Save message settings</button>
</form>
