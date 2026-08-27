@foreach(['success' => 'Success', 'warning' => 'Notice', 'error' => 'Error'] as $key => $title)
  @if(session($key))
    @push('scripts')
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          if (typeof swal !== 'function') {
            return;
          }

          swal({
            title: @json($title),
            text: @json(session($key)),
            type: @json($key === 'warning' ? 'warning' : ($key === 'error' ? 'error' : 'success')),
          });
        });
      </script>
    @endpush
  @endif
@endforeach
