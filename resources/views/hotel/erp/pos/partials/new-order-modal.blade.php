@php
  $posUi = config('hotel_pos.ui', []);
  $inHouseGuestOptions = collect($reservations ?? [])->map(function ($res) {
    $room = trim((string) ($res->roomUnit?->room_number ?? $res->room_no ?? ''));
    $guest = trim((string) $res->guestName());
    $label = $room !== '' ? 'Room '.$room.' — '.$guest : $guest;

    return [
      'id' => (int) $res->id,
      'label' => $label,
      'room' => $room,
      'guest' => $guest,
    ];
  })->values();
@endphp
@inject('posOrderService', 'App\Services\Pos\PosOrderService')

<div class="modal fade pos-new-order-modal" id="posNewOrderModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog pos-new-order-dialog" role="document">
    <form method="POST" action="{{ route('hotel.pos.orders.store') }}" id="posNewOrderForm">
      @csrf
      <input type="hidden" name="_method" value="POST" id="posFormMethodSpoof">
      <input type="hidden" id="posEditOrderId" value="">
      <input type="hidden" name="hotel_pms_service_id" value="{{ $service->id }}">
      <input type="hidden" name="hotel_department_id" value="{{ $department->id }}">
      <input type="hidden" name="from" value="{{ $from->format('Y-m-d') }}">
      <input type="hidden" name="to" value="{{ $to->format('Y-m-d') }}">
      <input type="hidden" name="is_complimentary" id="posComplimentaryFlag" value="0">
      <div class="modal-content">
        <div class="modal-header pos-new-order-head">
          <h5 class="modal-title" id="posNewOrderModalTitle">{{ $posUi['new_order'] ?? 'New Order' }}</h5>
          <div class="pos-new-order-head-actions">
            <a href="{{ route('hotel.services.show', $service) }}" class="btn btn-pos-outline btn-sm" target="_blank" rel="noopener">
              {{ $posUi['add_new_service'] ?? 'Add New Service' }}
            </a>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
        </div>
        <div class="modal-body">
          <div class="pos-modal-divider"></div>

          <div class="pos-guest-panel mb-3">
            <div class="pos-guest-panel__head">
              <span class="pos-guest-panel__title">{{ $posUi['guest_type'] ?? 'Guest' }}</span>
              <div class="pos-guest-type-row" role="group" aria-label="{{ $posUi['guest_type'] ?? 'Guest' }}">
                <label class="pos-guest-type-option">
                  <input type="radio" name="guest_type" value="walk_in" class="js-pos-guest-type" checked>
                  <span>{{ $posUi['guest_walk_in'] ?? 'Walk-in' }}</span>
                </label>
                <label class="pos-guest-type-option">
                  <input type="radio" name="guest_type" value="in_house" class="js-pos-guest-type">
                  <span>{{ $posUi['guest_in_house'] ?? 'In-house guest' }}</span>
                </label>
              </div>
            </div>
            <div class="row pos-guest-panel__fields align-items-end no-gutters">
              <div class="col-md-4 col-lg-4 pr-md-2">
                <div class="pos-guest-field js-pos-guest-walk-in-fields">
                  <label class="control-label" for="posWalkInLabel">
                    {{ $posUi['guest_walk_in_name'] ?? 'Name' }}
                    <span class="text-muted font-weight-normal">{{ $posUi['guest_walk_in_name_hint'] ?? 'Optional' }}</span>
                  </label>
                  <input type="text" class="form-control" name="walk_in_label" id="posWalkInLabel" maxlength="120"
                    placeholder="{{ $posUi['guest_walk_in_name_placeholder'] ?? 'Walk-in guest name' }}">
                </div>
                <div class="pos-guest-field js-pos-guest-inhouse-fields d-none">
                  <label class="control-label" for="posInHouseGuestSearch">
                    {{ $posUi['guest_select_room'] ?? 'Guest' }}
                    <span class="text-muted font-weight-normal">{{ $posUi['guest_select_room_hint'] ?? 'Search checked-in' }}</span>
                  </label>
                  <div class="pos-service-cell pos-guest-search-cell">
                    <input type="search" class="form-control" id="posInHouseGuestSearch" autocomplete="off"
                      placeholder="{{ $posUi['guest_select_room_placeholder'] ?? 'Search room or guest name…' }}"
                      @if($inHouseGuestOptions->isEmpty()) disabled @endif>
                    <ul class="list-group pos-item-suggestions" id="posInHouseGuestSuggestions"></ul>
                    <input type="hidden" name="cm_reservation_id" id="posInHouseGuestId" value="">
                  </div>
                </div>
              </div>
              <div class="col-md-4 col-lg-4 px-md-2">
                <div class="pos-guest-field">
                  <label class="control-label" for="posOrderDetails">{{ $posUi['details'] ?? 'Details' }}</label>
                  <input type="text" class="form-control" name="details" id="posOrderDetails"
                    placeholder="{{ $posUi['details_placeholder'] ?? 'Table 10, extra notes…' }}" maxlength="255">
                </div>
              </div>
              <div class="col-md-4 col-lg-4 pl-md-2">
                <div class="pos-guest-field">
                  <label class="control-label" for="posWaiterUserId">
                    {{ $posUi['waiter'] ?? 'Waiter' }}
                    @if(!($requireWaiterOnOrder ?? false))
                      <span class="text-muted font-weight-normal">{{ $posUi['waiter_optional_hint'] ?? 'Optional' }}</span>
                    @endif
                  </label>
                  <select class="form-control" name="waiter_user_id" id="posWaiterUserId"
                    @if($requireWaiterOnOrder ?? false) required @endif
                    @if(($waiters ?? collect())->isEmpty()) disabled @endif>
                    <option value="">{{ $posUi['select_waiter'] ?? 'Select waiter' }}</option>
                    @foreach($waiters ?? [] as $waiterUser)
                      <option value="{{ $waiterUser->id }}"
                        @selected((int) ($defaultWaiterUserId ?? 0) === (int) $waiterUser->id)>
                        {{ $posOrderService->waiterOptionLabel($waiterUser) }}
                      </option>
                    @endforeach
                  </select>
                  @if(($waiters ?? collect())->isEmpty())
                    <small class="text-muted d-block mt-1">Add active staff under HR → Team to assign waiters.</small>
                  @endif
                </div>
              </div>
            </div>
          </div>

          <div class="pos-line-entry-grid mb-3">
            <div class="pos-line-field">
              <label class="control-label">{{ $posUi['services_category'] ?? 'Services Category' }}</label>
              <select class="form-control" id="posLineCategory" required>
                <option value="">{{ $posUi['select_category'] ?? 'Select category' }}</option>
                @forelse($catalog['categories'] as $cat)
                  <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                @empty
                  <option value="" disabled>{{ $posUi['no_categories_hint'] ?? 'No categories with active items yet.' }}</option>
                @endforelse
              </select>
            </div>
            <div class="pos-line-field pos-service-cell">
              <label class="control-label">{{ $posUi['service'] ?? 'Item' }}</label>
              <input type="text" class="form-control" id="posLineServiceSearch" autocomplete="off"
                placeholder="{{ $posUi['service_search_placeholder'] ?? 'Search item…' }}">
              <ul class="list-group pos-item-suggestions" id="posItemSuggestions"></ul>
              <input type="hidden" id="posLineItemId">
              <input type="hidden" id="posLineCatalogKey">
              <input type="hidden" id="posLineCategoryName">
              <input type="hidden" id="posLinePackageId">
              <input type="hidden" id="posLineUnitLabel">
            </div>
            <div class="pos-line-field pos-line-field-qty">
              <label class="control-label">{{ $posUi['qty'] ?? 'Qty' }}</label>
              <input type="number" step="any" min="0.01" class="form-control" id="posLineQty" value="1">
            </div>
            <div class="pos-line-field pos-line-field-money">
              <label class="control-label">{{ $posUi['amount'] ?? 'Amount' }}</label>
              <input type="text" class="form-control pos-readonly-field" id="posLineAmount" readonly tabindex="-1"
                value="{{ $posOrderService->formatDisplayAmount(0, $currency) }}">
            </div>
            <div class="pos-line-field pos-line-field-money">
              <label class="control-label">{{ $posUi['tax'] ?? 'Tax' }}</label>
              <input type="text" class="form-control pos-readonly-field" id="posLineTax" readonly tabindex="-1"
                value="{{ $posOrderService->formatDisplayAmount(0, $currency) }}">
            </div>
            <div class="pos-line-field pos-line-field-money">
              <label class="control-label">{{ $posUi['total'] ?? 'Total' }}</label>
              <input type="text" class="form-control pos-readonly-field" id="posLineTotal" readonly tabindex="-1"
                value="{{ $posOrderService->formatDisplayAmount(0, $currency) }}">
            </div>
            <div class="pos-line-field pos-line-field-add">
              <button type="button" class="btn btn-pos pos-add-line-btn" id="posAddLineBtn" title="Add line">
                <i class="fa fa-plus"></i>
              </button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered pos-lines-table mb-0" id="posLinesTable">
              <thead>
                <tr>
                  <th>{{ $posUi['table_service_name'] ?? 'Service Name' }}</th>
                  <th class="text-right">{{ $posUi['table_rate'] ?? 'Rate' }}</th>
                  <th class="text-right">{{ $posUi['table_qty'] ?? 'Qty' }}</th>
                  <th class="text-right">{{ $posUi['table_amt'] ?? 'Amt' }}</th>
                  <th class="text-right">{{ $posUi['table_tax'] ?? 'Tax' }}</th>
                  <th class="text-right">{{ $posUi['table_total'] ?? 'Total' }}</th>
                  <th class="pos-lines-actions-col"></th>
                </tr>
              </thead>
              <tbody></tbody>
              <tfoot>
                <tr>
                  <td>{{ $posUi['table_grand_total'] ?? 'Total :' }}</td>
                  <td></td>
                  <td class="text-right" id="posFootQty">0</td>
                  <td class="text-right" id="posFootAmt">{{ $posOrderService->formatDisplayAmount(0, $currency) }}</td>
                  <td class="text-right" id="posFootTax">{{ $posOrderService->formatDisplayAmount(0, $currency) }}</td>
                  <td class="text-right" id="posFootTotal">{{ $posOrderService->formatDisplayAmount(0, $currency) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <div class="modal-footer pos-modal-footer">
          <button type="button" class="btn btn-pos-outline" data-dismiss="modal">{{ $posUi['cancel'] ?? 'Cancel' }}</button>
          <button type="submit" class="btn btn-primary px-4" id="posOrderSubmitBtn">{{ $posUi['submit'] ?? 'Submit' }}</button>
        </div>
      </div>
    </form>
  </div>
</div>
