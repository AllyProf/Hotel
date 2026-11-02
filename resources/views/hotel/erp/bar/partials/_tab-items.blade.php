@php
  $pieceUnit = $packages->first(fn ($p) => strtoupper((string) ($p->abbreviation ?? '')) === 'PIC')
    ?? $packages->first();
  $pieceLabel = strtoupper((string) ($pieceUnit?->abbreviation ?: 'PIC'));
@endphp

@if($categories->isEmpty() || $packages->isEmpty())
  <div class="alert alert-warning mb-3 py-2">
    Set up categories and units in
    <a href="{{ route('hotel.services.show', $service) }}">menu setup</a>.
  </div>
@endif

<div class="table-responsive">
  <table class="table table-hover table-bordered mb-0">
    <thead>
      <tr>
        <th>Item</th>
        <th>Category</th>
        <th>Received by</th>
        <th>{{ $pieceLabel }} / receive</th>
        <th>Selling methods</th>
        <th>Status</th>
        <th style="width:120px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $barItem)
        <tr>
          <td><strong>{{ $barItem->item_name }}</strong></td>
          <td>{{ $barItem->category?->name ?? '—' }}</td>
          <td>
            @if($barItem->package)
              {{ $barItem->package->name }}@if($barItem->package->abbreviation) ({{ $barItem->package->abbreviation }})@endif
            @else
              —
            @endif
          </td>
          <td class="text-center">
            @if($barItem->pieces_per_receive_unit !== null)
              {{ rtrim(rtrim(number_format((float) $barItem->pieces_per_receive_unit, 2, '.', ''), '0'), '.') }}
            @else
              —
            @endif
          </td>
          <td>
            @if($barItem->sellOptions->isNotEmpty())
              @foreach($barItem->sellOptions as $sellOpt)
                <span class="badge badge-light border mr-1 mb-1">
                  {{ $sellOpt->package?->abbreviation ?: $sellOpt->package?->name ?: '—' }}
                  @if($sellOpt->pieces_per_sell_unit !== null)
                    · {{ rtrim(rtrim(number_format((float) $sellOpt->pieces_per_sell_unit, 2, '.', ''), '0'), '.') }} pc
                  @endif
                </span>
              @endforeach
            @elseif($barItem->sellingPackage)
              <span class="badge badge-light border">
                {{ $barItem->sellingPackage->abbreviation ?: $barItem->sellingPackage->name }}
              </span>
            @else
              —
            @endif
          </td>
          <td>
            @if($barItem->is_active)
              <span class="badge badge-success">Active</span>
            @else
              <span class="badge badge-secondary">Inactive</span>
            @endif
          </td>
          <td class="text-nowrap">
            <a href="{{ route('hotel.bar.items.edit', $barItem) }}" class="btn btn-sm btn-primary">Edit</a>
            <form method="POST" action="{{ route('hotel.bar.items.destroy', $barItem) }}" class="d-inline js-swal-delete">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-sm btn-danger"
                data-title="Remove item?"
                data-text="Remove {{ $barItem->item_name }} from the bar menu?">
                Delete
              </button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="text-center text-muted py-4">
            No items yet. <a href="{{ route('hotel.bar.items.create') }}">Register item</a>.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
