<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Book your stay') — {{ $hotel->display_name ?: $hotel->name }}</title>
    <link rel="stylesheet" type="text/css" href="{{ asset('panel-assets/css/main.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('panel-assets/css/brand.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/admin.css') }}">
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
      /* Public booking: full-width content (no sidebar). */
      body.booking-public .app-content {
        margin-left: 0 !important;
      }

      body.booking-public .app-header__logo {
        font-family: 'Century Gothic', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        font-size: 17px;
        font-weight: 700;
        text-align: left;
        text-transform: uppercase;
        letter-spacing: 0.04em;
      }

      body.booking-public .app-header {
        padding-left: 0;
      }
    </style>
    @stack('styles')
  </head>
  <body class="app sidebar-mini booking-public">
    <header class="app-header">
      <a class="app-header__logo" href="{{ route('book.show', $bookingEngine['public_slug'] ?? '') }}">
        {{ $hotel->display_name ?: $hotel->name }}
      </a>
    </header>

    <main class="app-content">
      @php($beSettings = $beSettings ?? [])

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if(isset($errors) && $errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0 pl-3">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @yield('content')
    </main>

    <script src="{{ asset('panel-assets/js/jquery-3.2.1.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/main.js') }}"></script>
    @stack('scripts')
  </body>
</html>
