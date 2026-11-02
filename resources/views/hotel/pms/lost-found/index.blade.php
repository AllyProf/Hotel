@extends('layouts.app')

@section('title', 'Lost & Found')

@push('styles')
  @include('hotel.partials.brand-tab-styles')
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-search"></i> PMS <small class="text-muted">Lost &amp; Found</small></h1>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">Lost &amp; Found</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-title-w-btn">
          <div>
            <h3 class="title mb-1">Found property register</h3>
            <p class="text-muted small mb-0">
              Housekeeping and front desk share this list. For items linked to a room, recent guests for that room are shown below each row.
              Mark items claimed when a guest collects them.
              @if($openCount > 0)
                <span class="badge badge-warning ml-1">{{ $openCount }} in storage</span>
              @endif
            </p>
          </div>
          <p class="mb-0">
            <button type="button" class="btn btn-primary icon-btn" data-toggle="modal" data-target="#mhLostFoundModal">
              <i class="fa fa-plus"></i> Log found item
            </button>
          </p>
        </div>

        <div class="tile-body">
          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif
          @if(session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
          @endif

          <ul class="nav nav-pills pms-brand-tabs mb-4">
            @foreach(['open' => 'In storage', 'done' => 'Claimed / disposed', 'all' => 'All'] as $fKey => $fLabel)
              <li class="nav-item">
                <a class="nav-link {{ $filter === $fKey ? 'active' : '' }}"
                  href="{{ route('hotel.lost-found.index', ['filter' => $fKey]) }}">{{ $fLabel }}</a>
              </li>
            @endforeach
          </ul>

          @include('hotel.erp.maintenance-hk.partials.lost-found', [
            'items' => $items,
            'lostFoundFilter' => $filter,
            'guestHints' => $guestHints ?? [],
            'notificationByItem' => $notificationByItem ?? [],
            'showGuestHints' => true,
          ])
        </div>
      </div>
    </div>
  </div>

  @include('hotel.erp.maintenance-hk.partials.new-lost-found-modal')
@endsection

@push('scripts')
  <script>
    (function () {
      document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-lf-use-guest');
        if (!btn) return;
        var ref = btn.getAttribute('data-item-ref') || '';
        var name = btn.getAttribute('data-guest-name') || '';
        var form = document.querySelector('.js-lf-claim-form[data-item-ref="' + ref + '"]');
        if (!form || !name) return;
        var input = form.querySelector('.js-lf-claimed-by');
        if (input) {
          input.value = name;
          input.focus();
        }
      });
    })();
  </script>
@endpush
