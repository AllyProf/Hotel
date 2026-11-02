@foreach(['success' => ['title' => 'Success', 'type' => 'success'], 'warning' => ['title' => 'Notice', 'type' => 'warning'], 'error' => ['title' => 'Error', 'type' => 'error']] as $key => $meta)
  @if(session($key))
    @push('scripts')
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          if (window.AppAlerts && AppAlerts.toast) {
            AppAlerts.toast.{{ $meta['type'] }}(@json($meta['title']), @json(session($key)));
            return;
          }

          if (typeof swal === 'function') {
            swal({
              title: @json($meta['title']),
              text: @json(session($key)),
              type: @json($meta['type']),
            });
          }
        });
      </script>
    @endpush
  @endif
@endforeach
