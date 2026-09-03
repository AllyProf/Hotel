@extends('layouts.app')

@section('title', 'OTA Mapping Setup')

@push('styles')
  <style>
    .ota-mapping-toolbar {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 16px;
    }
    .ota-mapping-layout {
      display: flex;
      gap: 0;
      background: #fff;
      border: 1px solid rgba(0,0,0,.08);
      min-height: 620px;
    }
    .ota-rooms-panel {
      width: 260px;
      flex: 0 0 260px;
      border-right: 1px solid rgba(0,0,0,.08);
      background: #fafafa;
    }
    .ota-rooms-panel__head {
      padding: 16px;
      border-bottom: 1px solid rgba(0,0,0,.08);
      font-weight: 700;
      background: #fff;
    }
    .ota-rooms-panel__count {
      padding: 10px 16px;
      color: #666;
      font-size: 13px;
      border-bottom: 1px solid rgba(0,0,0,.06);
    }
    .ota-room-item {
      padding: 14px 16px;
      border-bottom: 1px solid rgba(0,0,0,.06);
      background: #fff;
    }
    .ota-room-item__title {
      font-weight: 700;
      margin-bottom: 2px;
    }
    .ota-room-item__slug {
      color: #888;
      font-size: 12px;
      margin-bottom: 10px;
    }
    .ota-room-item__toggle {
      width: 100%;
      text-align: left;
      border: 1px solid rgba(0,0,0,.12);
      background: #fff;
      padding: 6px 10px;
      font-size: 12px;
      border-radius: 3px;
    }
    .ota-carousel-wrap {
      flex: 1 1 auto;
      overflow: hidden;
      position: relative;
      background: #f3f4f6;
    }
    .ota-carousel-nav {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      z-index: 2;
      width: 34px;
      height: 34px;
      border-radius: 50%;
      border: 1px solid rgba(0,0,0,.12);
      background: #fff;
      color: #333;
      box-shadow: 0 2px 8px rgba(0,0,0,.08);
    }
    .ota-carousel-nav--prev { left: 8px; }
    .ota-carousel-nav--next { right: 8px; }
    .ota-carousel {
      display: flex;
      gap: 16px;
      overflow-x: auto;
      scroll-behavior: smooth;
      padding: 20px 48px;
      min-height: 620px;
      scrollbar-width: thin;
    }
    .ota-carousel::-webkit-scrollbar { height: 8px; }
    .ota-carousel::-webkit-scrollbar-thumb { background: rgba(0,0,0,.15); border-radius: 4px; }
    .ota-card {
      flex: 0 0 300px;
      background: #fff;
      border: 1px solid rgba(0,0,0,.08);
      border-top: 4px solid var(--ota-brand);
      display: flex;
      flex-direction: column;
      min-height: 560px;
    }
    .ota-card__head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 14px;
      border-bottom: 1px solid rgba(0,0,0,.06);
    }
    .ota-card__brand {
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 0;
    }
    .ota-card__logo {
      width: auto;
      height: 22px;
      max-width: 110px;
      object-fit: contain;
      flex: 0 0 auto;
    }
    .ota-card__logo-fallback {
      width: 28px;
      height: 28px;
      border-radius: 4px;
      background: var(--ota-brand);
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      display: none;
      align-items: center;
      justify-content: center;
      flex: 0 0 28px;
    }
    .ota-card__badge {
      font-size: 10px;
      font-weight: 600;
    }
    .ota-card__name {
      font-weight: 700;
      font-size: 14px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .ota-card__controls {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-bottom: 1px solid rgba(0,0,0,.06);
    }
    .ota-card__body {
      padding: 14px;
      flex: 1 1 auto;
      display: flex;
      flex-direction: column;
    }
    .ota-card__fetch-area {
      flex: 1 1 auto;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: #888;
      border: 1px dashed rgba(0,0,0,.12);
      border-radius: 4px;
      padding: 24px 12px;
      margin: 12px 0;
      min-height: 120px;
    }
    .ota-card__fetch-area i {
      font-size: 28px;
      margin-bottom: 8px;
      opacity: .45;
    }
    .ota-stepper {
      display: flex;
      align-items: center;
      gap: 0;
    }
    .ota-stepper button {
      width: 32px;
      height: 32px;
      border: 1px solid rgba(0,0,0,.15);
      background: #fff;
      color: #333 !important;
    }
    .ota-stepper input {
      width: 52px;
      text-align: center;
      border-top: 1px solid rgba(0,0,0,.15);
      border-bottom: 1px solid rgba(0,0,0,.15);
      border-left: 0;
      border-right: 0;
      height: 32px;
    }
    .toggle-switch {
      position: relative;
      width: 42px;
      height: 22px;
    }
    .toggle-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .toggle-switch span {
      position: absolute;
      inset: 0;
      background: #ccc;
      border-radius: 22px;
      cursor: pointer;
      transition: .2s;
    }
    .toggle-switch span:before {
      content: '';
      position: absolute;
      width: 18px;
      height: 18px;
      left: 2px;
      top: 2px;
      background: #fff;
      border-radius: 50%;
      transition: .2s;
    }
    .toggle-switch input:checked + span {
      background: #940000;
    }
    .toggle-switch input:checked + span:before {
      transform: translateX(20px);
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-random"></i> OTA Mapping Setup</h1>
      <p>Connect and map your rooms to online travel agencies</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="#">OTA Mapping</a></li>
    </ul>
  </div>

  <div class="ota-mapping-toolbar">
    <button type="button" class="btn btn-secondary btn-sm" id="otaScrollPrev"><i class="fa fa-chevron-left"></i></button>
    <button type="button" class="btn btn-secondary btn-sm" id="otaScrollNext"><i class="fa fa-chevron-right"></i></button>
    <button type="button" class="btn btn-secondary btn-sm js-swal-info" data-title="Email Notifications" data-text="Configure OTA mapping email alerts from your account settings.">
      Email Notifications
    </button>
    <button type="button" class="btn btn-primary btn-sm" id="otaSyncMultipliers">
      Sync to OTAs
    </button>
  </div>

  <div class="tile p-0 overflow-hidden">
    <div class="ota-mapping-layout">
      <aside class="ota-rooms-panel">
        <div class="ota-rooms-panel__head">{{ config('app.name', 'Hotel SaaS') }} Rooms</div>
        <div class="ota-rooms-panel__count">
          {{ count($rooms) }} rooms
          @if(!empty($hotelCode))
            · CM code: <code>{{ $hotelCode }}</code>
          @endif
          @if(($configuredCount ?? 0) > 0)
            · {{ $configuredCount }} OTA{{ $configuredCount === 1 ? '' : 's' }} connected
          @endif
        </div>
        @foreach($rooms as $room)
          <div class="ota-room-item">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="ota-room-item__title">{{ $room['name'] }}</div>
                <div class="ota-room-item__slug">{{ $room['slug'] }}</div>
              </div>
              <button type="button" class="btn btn-sm btn-light" title="Remove room"><i class="fa fa-minus"></i></button>
            </div>
            <button type="button" class="ota-room-item__toggle" data-toggle="collapse" data-target="#rateplans-{{ $room['id'] }}">
              View rateplans <i class="fa fa-angle-down"></i>
            </button>
            <div class="collapse mt-2" id="rateplans-{{ $room['id'] }}">
              <ul class="small mb-0 pl-3">
                @foreach($room['rate_plans'] as $plan)
                  <li>{{ $plan['label'] }} <code class="text-muted">{{ $plan['code'] }}</code></li>
                @endforeach
              </ul>
            </div>
          </div>
        @endforeach
      </aside>

      <div class="ota-carousel-wrap">
        <button type="button" class="ota-carousel-nav ota-carousel-nav--prev" id="otaNavPrev" aria-label="Previous"><i class="fa fa-chevron-left"></i></button>
        <button type="button" class="ota-carousel-nav ota-carousel-nav--next" id="otaNavNext" aria-label="Next"><i class="fa fa-chevron-right"></i></button>

        <div class="ota-carousel" id="otaCarousel">
          @foreach($otas as $ota)
            @include('hotel.channel-manager.partials._ota-card', [
              'ota' => $ota,
              'connection' => $connections[$ota['slug']] ?? [],
            ])
          @endforeach
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var carousel = document.getElementById('otaCarousel');
      if (!carousel) return;

      function scrollByAmount(dir) {
        carousel.scrollBy({ left: dir * 320, behavior: 'smooth' });
      }

      ['otaScrollPrev', 'otaNavPrev'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', function () { scrollByAmount(-1); });
      });
      ['otaScrollNext', 'otaNavNext'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', function () { scrollByAmount(1); });
      });

      document.querySelectorAll('.js-ota-stepper').forEach(function (wrap) {
        var input = wrap.querySelector('input');
        wrap.querySelector('.ota-step-minus').addEventListener('click', function () {
          var val = parseFloat(input.value || '1');
          input.value = Math.max(0.1, (val - 0.1).toFixed(1));
        });
        wrap.querySelector('.ota-step-plus').addEventListener('click', function () {
          var val = parseFloat(input.value || '1');
          input.value = (val + 0.1).toFixed(1);
        });
      });

      document.querySelectorAll('.js-swal-info').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (typeof swal === 'function') {
            swal(btn.dataset.title || 'Info', btn.dataset.text || '', 'info');
          }
        });
      });

      var syncBtn = document.getElementById('otaSyncMultipliers');
      if (syncBtn) {
        syncBtn.addEventListener('click', function () {
          syncBtn.disabled = true;
          var originalHtml = syncBtn.innerHTML;
          syncBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Syncing…';

          fetch('{{ route('hotel.channel-manager.ota-mapping.sync-multipliers') }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({})
          })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
              syncBtn.disabled = false;
              syncBtn.innerHTML = originalHtml;

              if (typeof swal === 'function') {
                swal(
                  result.ok ? 'Sync Complete' : 'Sync Failed',
                  result.data.message || (result.ok ? 'Rate multipliers pushed.' : 'Could not sync rate multipliers.'),
                  result.ok ? 'success' : 'warning'
                );
              }
            })
            .catch(function () {
              syncBtn.disabled = false;
              syncBtn.innerHTML = originalHtml;
              if (typeof swal === 'function') swal('Sync Failed', 'Could not reach Channel Manager.', 'error');
            });
        });
      }

      document.querySelectorAll('.js-ota-fetch').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var card = btn.closest('.js-ota-card');
          var code = card.querySelector('.js-ota-hotel-code').value.trim();
          if (!code) {
            if (typeof swal === 'function') swal('Hotel Code', 'Please enter a hotel code first.', 'warning');
            return;
          }
          var area = card.querySelector('.js-ota-fetch-area');
          area.innerHTML = '<i class="fa fa-spinner fa-spin"></i><div class="mt-2">Fetching from Channel Manager…</div>';
          btn.disabled = true;

          fetch('{{ route('hotel.channel-manager.ota-mapping.fetch-property') }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ hotel_code: code })
          })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
              btn.disabled = false;
              if (!result.ok || !result.data.success) {
                area.innerHTML = '<i class="fa fa-times-circle text-danger"></i><div class="mt-2">' + (result.data.message || 'Fetch failed.') + '</div>';
                return;
              }
              var p = result.data.property || {};
              area.innerHTML =
                '<i class="fa fa-check-circle text-success"></i>' +
                '<div class="mt-2"><strong>' + (p.hotel_name || p.hotel_id || code) + '</strong></div>' +
                '<div class="small text-muted">' +
                  (p.city ? p.city + ' · ' : '') +
                  (p.room_count || 0) + ' room types · ' +
                  (p.rateplan_count || 0) + ' rate plans' +
                  (p.currency ? ' · ' + p.currency : '') +
                '</div>';
              if (p.currency && card.querySelector('.js-ota-currency')) {
                var currencySelect = card.querySelector('.js-ota-currency');
                var hasOption = Array.prototype.some.call(currencySelect.options, function (opt) { return opt.value === p.currency; });
                if (hasOption) currencySelect.value = p.currency;
              }
            })
            .catch(function () {
              btn.disabled = false;
              area.innerHTML = '<i class="fa fa-times-circle text-danger"></i><div class="mt-2">Could not reach Channel Manager.</div>';
            });
        });
      });

      document.querySelectorAll('.js-ota-submit').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var card = btn.closest('.js-ota-card');
          var slug = card.getAttribute('data-slug');
          var payload = {
            _token: '{{ csrf_token() }}',
            enabled: card.querySelector('.js-ota-enabled').checked ? 1 : 0,
            hotel_code: card.querySelector('.js-ota-hotel-code').value.trim(),
            currency: card.querySelector('.js-ota-currency').value,
            rate_multiplier: parseFloat(card.querySelector('.js-ota-multiplier').value || '1')
          };

          btn.disabled = true;

          fetch('{{ url('hotel/channel-manager/ota-mapping') }}/' + encodeURIComponent(slug), {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': payload._token
            },
            body: JSON.stringify(payload)
          })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
              btn.disabled = false;
              if (!result.ok) {
                if (typeof swal === 'function') swal('Mapping', result.data.message || 'Could not save mapping.', 'warning');
                return;
              }
              if (typeof swal === 'function') {
                swal('Mapping Saved', result.data.message || 'OTA mapping saved.', 'success');
              }
              window.location.reload();
            })
            .catch(function () {
              btn.disabled = false;
              if (typeof swal === 'function') swal('Mapping', 'Could not save mapping.', 'error');
            });
        });
      });
    })();
  </script>
@endpush
