@php
  $tasks = $payload['tasks'] ?? collect();
@endphp

<div class="table-responsive">
  <table class="table table-hover table-bordered">
    <thead>
      <tr>
        <th>Room</th>
        <th>Room type</th>
        <th>Task</th>
        <th>Priority</th>
        <th>Status</th>
        <th>Assigned</th>
        <th>Due</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($tasks as $task)
        <tr>
          <td><strong>{{ $task->room_no }}</strong></td>
          <td>{{ $task->room_type_name ?? '—' }}</td>
          <td>{{ $task->taskTypeLabel() }}</td>
          <td>{{ $task->priorityLabel() }}</td>
          <td>
            @include('hotel.erp.maintenance-hk.partials.status-badge', ['status' => $task->status, 'label' => $task->statusLabel()])
          </td>
          <td>{{ $task->assignee?->name ?? '—' }}</td>
          <td>{{ $task->due_at?->format('d/m/Y H:i') ?? '—' }}</td>
          <td class="text-nowrap">
            @if($task->isOpen())
              <form method="POST" action="{{ route('hotel.maintenance-hk.hk.complete', $task) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary">Mark cleaned</button>
              </form>
            @else
              <span class="text-muted small">{{ $task->completed_at?->format('d/m/Y H:i') ?? '—' }}</span>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="text-center text-muted py-4">
            No housekeeping tasks for this view. Dirty rooms from check-out appear here automatically.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
