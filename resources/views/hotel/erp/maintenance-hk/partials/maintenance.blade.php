@php
  $orders = $payload['orders'] ?? collect();
  $statuses = $ui['maintenance']['statuses'] ?? [];
@endphp

<div class="table-responsive">
  <table class="table table-hover table-bordered">
    <thead>
      <tr>
        <th>Room</th>
        <th>Title</th>
        <th>Category</th>
        <th>Priority</th>
        <th>Status</th>
        <th>Blocks room</th>
        <th>Assigned</th>
        <th>Opened</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($orders as $order)
        <tr>
          <td>{{ $order->room_no ?: '—' }}</td>
          <td><strong>{{ $order->title }}</strong></td>
          <td>{{ $order->categoryLabel() }}</td>
          <td>{{ $order->priorityLabel() }}</td>
          <td>
            @include('hotel.erp.maintenance-hk.partials.status-badge', ['status' => $order->status, 'label' => $order->statusLabel()])
          </td>
          <td>{{ $order->blocks_room ? 'Yes' : 'No' }}</td>
          <td>{{ $order->assignee?->name ?? '—' }}</td>
          <td>{{ $order->opened_at?->format('d/m/Y H:i') ?? '—' }}</td>
          <td class="text-nowrap">
            @if($order->isOpen())
              <form method="POST" action="{{ route('hotel.maintenance-hk.maintenance.update', $order) }}" class="form-inline">
                @csrf
                <select name="status" class="form-control form-control-sm mr-2 mb-2 mb-sm-0">
                  @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" {{ $order->status === $value ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary mb-2 mb-sm-0">Update</button>
              </form>
            @else
              <span class="text-muted small">{{ $order->completed_at?->format('d/m/Y H:i') ?? '—' }}</span>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="9" class="text-center text-muted py-4">No maintenance work orders yet.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
