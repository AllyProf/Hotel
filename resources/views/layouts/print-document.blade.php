<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Print') — {{ config('app.name', 'Hotel SaaS') }}</title>
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
      *, *::before, *::after { box-sizing: border-box; }

      html, body {
        margin: 0;
        padding: 0;
        background: #fff;
        color: #111;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        font-size: 14px;
        line-height: 1.45;
      }

      .print-doc-toolbar {
        max-width: 820px;
        margin: 0 auto 16px;
        padding: 16px 24px 0;
        text-align: center;
      }

      .print-doc-body {
        max-width: 820px;
        margin: 0 auto;
        padding: 24px;
      }

      .table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
      }

      .table th,
      .table td {
        padding: 8px 10px;
        border: 1px solid #dee2e6;
        vertical-align: top;
      }

      .text-right { text-align: right; }
      .text-muted { color: #6c757d; }
      .mb-0 { margin-bottom: 0; }
      .mb-1 { margin-bottom: 0.25rem; }
      .mt-2 { margin-top: 0.5rem; }
      .mt-3 { margin-top: 1rem; }

      .badge {
        display: inline-block;
        padding: 0.2em 0.45em;
        font-size: 11px;
        font-weight: 600;
        border-radius: 3px;
      }

      .badge-warning {
        background: #ffc107;
        color: #212529;
      }

      .btn {
        display: inline-block;
        padding: 8px 22px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        border-radius: 4px;
        border: 1px solid transparent;
        cursor: pointer;
        margin: 0 4px;
      }

      .btn-print-primary {
        background: #940000;
        border-color: #940000;
        color: #fff;
      }

      .btn-print-light {
        background: #fff;
        border-color: #dee2e6;
        color: #333;
      }

      @media print {
        @page {
          margin: 12mm;
          size: auto;
        }

        html, body {
          background: #fff !important;
        }

        .print-doc-toolbar {
          display: none !important;
        }

        .print-doc-body {
          max-width: none;
          width: 100%;
          margin: 0;
          padding: 0;
        }

        .co-print-wrap {
          border: none !important;
          box-shadow: none !important;
          padding: 0 !important;
          max-width: none !important;
        }

        .co-print-table thead th {
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
          color-adjust: exact !important;
        }

        .co-laundry-block {
          break-inside: avoid;
          page-break-inside: avoid;
        }

        a[href]::after {
          content: none !important;
        }
      }
    </style>
    @stack('styles')
  </head>
  <body>
    @yield('toolbar')
    <div class="print-doc-body">
      @yield('content')
    </div>
    @stack('scripts')
  </body>
</html>
