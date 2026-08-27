<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Hotel SaaS Management System">
    <title>@yield('title', 'Dashboard') - {{ config('app.name', 'Hotel SaaS') }}</title>
    <link rel="stylesheet" type="text/css" href="{{ asset('panel-assets/css/main.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('panel-assets/css/brand.css') }}">
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    @stack('styles')
  </head>
  <body class="app sidebar-mini rtl">
    @include('layouts.partials._page-loader')
    @include('layouts.partials._header')
    @include(auth()->user()->isPlatformOwner() ? 'layouts.partials._sidebar' : 'layouts.partials._sidebar-hotel')

    <main class="app-content">
      @if(session()->has('impersonate_original_user'))
        <div class="alert alert-warning d-flex justify-content-between align-items-center mb-4 flex-wrap">
          <div class="mb-2 mb-md-0">
            <i class="fa fa-user-secret mr-2"></i>
            You are impersonating <strong>{{ auth()->user()->hotel?->name ?? auth()->user()->name }}</strong>.
          </div>
          <form action="{{ route('stop-impersonating') }}" method="POST" class="js-swal-confirm">
            @csrf
            <button type="submit" class="btn btn-sm btn-dark"
              data-title="Stop impersonating?"
              data-text="You will return to the platform owner account."
              data-confirm="Switch back"
              data-cancel="Stay here">
              <i class="fa fa-arrow-left mr-1"></i> Switch Back to Platform Owner
            </button>
          </form>
        </div>
      @endif
      @yield('content')
    </main>

    <script src="{{ asset('panel-assets/js/jquery-3.2.1.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/main.js') }}"></script>
    <script src="{{ asset('panel-assets/js/plugins/sweetalert.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/app-alerts.js') }}"></script>
    <script>
      (function () {
        var loader = document.getElementById('appPageLoader');
        if (!loader) return;

        function hideLoader() {
          loader.classList.add('is-done');
          loader.setAttribute('aria-busy', 'false');
        }

        if (document.readyState === 'complete' || document.readyState === 'interactive') {
          setTimeout(hideLoader, 0);
        }
        window.addEventListener('DOMContentLoaded', hideLoader);
        window.addEventListener('load', hideLoader);
        setTimeout(hideLoader, 4000);
      })();
    </script>
    @include('layouts.partials._flash-swal')
    @stack('scripts')
  </body>
</html>
