@once
  @push('styles')
    <style>.select2-container { width: 100% !important; min-width: 120px; }</style>
  @endpush
  @push('scripts')
    <script src="{{ asset('panel-assets/js/plugins/select2.min.js') }}"></script>
    <script>
      jQuery(function ($) {
        $('.select2-currency').each(function () {
          if ($(this).hasClass('select2-hidden-accessible')) return;
          $(this).select2({ width: '100%', placeholder: 'Search currency…' });
        });
      });
    </script>
  @endpush
@endonce
