@extends('layouts.app')

@section('title', $ui['title'] ?? 'Services')

@push('styles')
  @include('hotel.pms.services.partials._card-styles')
  <style>
    .sv-header {
      padding: 18px 20px;
      border-bottom: 3px solid var(--sv-brand);
    }

    .sv-header h3 {
      margin: 0;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .btn-sv-manage {
      display: block;
      background: var(--sv-brand) !important;
      border: 1px solid var(--sv-brand) !important;
      color: #fff !important;
      font-size: 12px;
      font-weight: 600;
      padding: 5px 14px;
      border-radius: 3px;
      width: 100%;
      text-align: center;
      text-decoration: none !important;
      margin-bottom: 6px;
    }

    .btn-sv-manage:hover {
      background: var(--sv-brand-dark) !important;
      border-color: var(--sv-brand-dark) !important;
      color: #fff !important;
    }

    .sv-add-form--compact .form-control {
      margin-bottom: 6px;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-th-large"></i> {{ $ui['title'] ?? 'Services' }}</h1>
      <p>Manage hotel services, pricing, and categories</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Services' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile sv-page">
        @if(session('success'))
          <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>
        @endif

        <div class="sv-header">
          <h3>{{ $ui['title'] ?? 'Services' }}</h3>
        </div>

        <div class="sv-section">
          <h4 class="sv-section-title"><i class="fa fa-building"></i> Services</h4>
          <div class="sv-template-grid">
            @foreach($cards as $card)
              <div class="sv-template-card {{ $card['is_added'] ? 'is-added' : '' }}">
                <div class="sv-template-card__icon">
                  <i class="{{ $card['icon'] }}"></i>
                </div>
                <p class="sv-template-card__name">{{ $card['name'] }}</p>
                @if($card['type'] === 'custom')
                  <span class="sv-badge-custom">Custom</span>
                @endif
                <div class="sv-template-card__actions">
                  @if($card['is_added'] && $card['service_id'])
                    <a href="{{ route('hotel.services.show', $card['service_id']) }}" class="btn btn-sv-manage">
                      Manage
                    </a>
                    <form method="POST" action="{{ route('hotel.services.destroy', $card['service_id']) }}"
                      class="js-swal-delete">
                      @csrf
                      @method('DELETE')
                      <button type="button" class="btn btn-sv-remove"
                        data-title="Remove service?"
                        data-text="Remove {{ $card['name'] }} from your hotel services?"
                        data-confirm="Yes, remove it"
                        data-cancel="Cancel">
                        Remove
                      </button>
                    </form>
                  @elseif($card['type'] === 'template')
                    <form method="POST" action="{{ route('hotel.services.from-template') }}" class="js-swal-add">
                      @csrf
                      <input type="hidden" name="template_key" value="{{ $card['template_key'] }}">
                      <button type="submit" class="btn btn-sv-outline"
                        data-title="Add service?"
                        data-text="Add {{ $card['name'] }} to your hotel services?"
                        data-confirm="Yes, add it"
                        data-cancel="Cancel">
                        Add
                      </button>
                    </form>
                  @endif
                </div>
              </div>
            @endforeach

            <div class="sv-template-card sv-template-card--add">
              <div class="sv-template-card__icon">
                <i class="fa fa-plus"></i>
              </div>
              <form method="POST" action="{{ route('hotel.services.store') }}" class="sv-add-form js-swal-add">
                @csrf
                <input type="text" class="form-control" name="name" placeholder="New service" required maxlength="120"
                  value="{{ old('name') }}">
                <button type="submit" class="btn btn-sv"
                  data-title="Add custom service?"
                  data-text="Add :name as a custom service?"
                  data-confirm="Yes, add it"
                  data-cancel="Cancel">
                  Add
                </button>
              </form>
              @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
