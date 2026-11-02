{{-- Expects: $procWorkflow, $procLabel, $tab, plus procurement index payload keys --}}
@php
  $procW = $procWorkflow;
  $indexRoute = \App\Support\DepartmentProcurement::workflow($procW)['index_route'];
  $settingsTab = \App\Support\DepartmentProcurement::workflow($procW)['settings_tab'];
  $procTabs = ['purchase-lists', 'received', 'market', 'stock-receive'];
@endphp

<div class="tile-title-w-btn mb-2">
  <div></div>
  <p class="mb-0">
    @if(($tab ?? '') === 'purchase-lists')
      @if($purchaseListRecipientsConfigured ?? false)
        <a class="btn btn-primary btn-sm" href="{{ \App\Support\DepartmentProcurement::route($procW, 'purchase-lists.create') }}">
          <i class="fa fa-paper-plane"></i> New list
        </a>
      @else
        <a class="btn btn-primary btn-sm" href="{{ route('hotel.settings.index', ['tab' => $settingsTab]) }}">
          <i class="fa fa-cog"></i> Recipients
        </a>
      @endif
    @elseif(($tab ?? '') === 'received')
      <a class="btn btn-light border btn-sm" href="{{ route('hotel.settings.index', ['tab' => $settingsTab]) }}">
        <i class="fa fa-cog"></i> Recipient settings
      </a>
    @elseif(($tab ?? '') === 'market')
      <a class="btn btn-light border btn-sm" href="{{ route('hotel.accounts.index', ['tab' => 'bar_budgets']) }}">
        <i class="fa fa-calculator"></i> Accountant queue
      </a>
    @endif
  </p>
</div>

@php($procWorkflow = $procW)

@if(($tab ?? '') === 'purchase-lists')
  @include('hotel.erp.bar.partials._tab-purchase-lists')
@elseif(($tab ?? '') === 'received')
  @include('hotel.erp.bar.partials._tab-received-lists')
@elseif(($tab ?? '') === 'market')
  @include('hotel.erp.bar.partials._tab-market-lists')
@elseif(($tab ?? '') === 'stock-receive')
  @include('hotel.erp.bar.partials._tab-stock-receive')
@endif
