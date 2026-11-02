@php
  $lineIndex = $lineIndex ?? '__INDEX__';
  $selectedItemId = (int) ($selectedItemId ?? 0);
@endphp
<div class="bar-pl-item-select-wrap">
<select class="form-control form-control-sm js-bar-pl-item-select @if(is_numeric($lineIndex)) @error('lines.'.$lineIndex.'.item_id') is-invalid @enderror @endif"
  name="lines[{{ $lineIndex }}][item_id]" required data-placeholder="Select item…">
  <option value="">Select item…</option>
  @foreach($pickableItems as $barItem)
    @php
      $label = $barItem->category
        ? $barItem->item_name.' — '.$barItem->category->name
        : $barItem->item_name;
    @endphp
    <option value="{{ $barItem->id }}" @selected($selectedItemId === (int) $barItem->id)>{{ $label }}</option>
  @endforeach
</select>
</div>
