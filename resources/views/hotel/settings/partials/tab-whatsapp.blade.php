@php
  $whatsapp = $settings->whatsapp ?? [];
  $isConnected = ! empty($whatsapp['facebook_connected']);
@endphp

<div class="whatsapp-panel">
  <div class="whatsapp-panel__head">
    <i class="fa fa-whatsapp"></i>
    <span>WhatsApp</span>
  </div>

  <div class="whatsapp-panel__body">
    @if($isConnected)
      <div class="whatsapp-connected-badge">
        <i class="fa fa-check-circle"></i>
        Connected with Facebook — {{ $whatsapp['facebook_page_name'] ?? 'WhatsApp Business' }}
      </div>

      <form action="{{ route('hotel.settings.whatsapp.disconnect') }}" method="POST" class="d-inline js-swal-confirm">
        @csrf
        <button type="submit" class="btn btn-secondary btn-sm"
          data-title="Disconnect WhatsApp?"
          data-text="Guest messages will stop sending until you connect again."
          data-confirm="Disconnect"
          data-cancel="Cancel">
          <i class="fa fa-unlink"></i> Disconnect
        </button>
      </form>

      <div class="whatsapp-advanced">
        <a class="btn btn-link p-0 mb-3" data-toggle="collapse" href="#whatsappAdvanced" aria-expanded="false">
          <i class="fa fa-cog"></i> Message settings
        </a>
        <div class="collapse" id="whatsappAdvanced">
          @include('hotel.settings.partials._whatsapp-config-form')
        </div>
      </div>
    @else
      <form action="{{ route('hotel.settings.whatsapp.connect') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-whatsapp-connect">
          Connect Whatsapp with Facebook
        </button>
      </form>
    @endif
  </div>
</div>
