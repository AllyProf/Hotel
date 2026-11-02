@php
  $sheet = $payload['attendance'] ?? [];
  $workDate = $filters['work_date'] ?? now()->format('Y-m-d');
@endphp

<form method="GET" action="{{ route('hotel.hr.index') }}" class="hr-toolbar">
  <input type="hidden" name="tab" value="attendance">
  <label class="mb-0 mr-2 small font-weight-bold">Date</label>
  <input type="date" name="work_date" class="form-control" style="width:auto;" value="{{ $workDate }}">
  <button type="submit" class="btn btn-hr-outline">Load</button>
</form>

<div class="hr-content">
  @if($sheet === [])
    <p class="text-muted py-4">No active staff to mark attendance.</p>
  @else
    <div class="table-responsive">
      <table class="table table-bordered table-hover hr-table mb-0">
        <thead>
          <tr>
            <th>Staff</th>
            <th>Department</th>
            <th>Status</th>
            <th>Notes</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($sheet as $row)
            @php
              $member = $row['user'];
              $entry = $row['entry'];
              $currentStatus = $entry?->status ?? 'present';
            @endphp
            <tr>
              <td><strong>{{ $member->name }}</strong></td>
              <td>{{ $member->department?->name ?? '—' }}</td>
              <td colspan="3">
                <form method="POST" action="{{ route('hotel.hr.attendance.store') }}" class="form-inline flex-wrap js-swal-confirm" style="gap:8px;">
                  @csrf
                  <input type="hidden" name="work_date" value="{{ $workDate }}">
                  <input type="hidden" name="user_id" value="{{ $member->id }}">
                  <select name="status" class="form-control form-control-sm" required>
                    @foreach($attendanceStatuses as $value => $label)
                      <option value="{{ $value }}" {{ $currentStatus === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                  <input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes" maxlength="255"
                    value="{{ $entry?->notes }}">
                  <button type="submit" class="btn btn-sm btn-hr" data-title="Save attendance?" data-text="Update status for {{ $member->name }}?" data-confirm="Save">Save</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
