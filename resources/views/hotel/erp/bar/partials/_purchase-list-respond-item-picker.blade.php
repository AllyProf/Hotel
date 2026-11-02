@php
  $inputClass = $inputClass ?? 'form-control form-control-sm';
  $lineIndex = $lineIndex ?? '__INDEX__';
  $itemName = trim((string) ($itemName ?? ''));
  $pickableItems = $pickableItems ?? collect();
  $matched = $itemName !== ''
    ? $pickableItems->first(fn ($i) => strcasecmp(trim($i->item_name), $itemName) === 0)
    : null;
  $displayLabel = $matched
    ? ($matched->category
      ? $matched->item_name.' — '.$matched->category->name
      : $matched->item_name)
    : $itemName;
@endphp
<div class="bar-pl-item-picker bar-respond-item-picker">
  <input type="hidden" class="js-bar-respond-item-name" name="lines[{{ $lineIndex }}][item_name]"
    value="{{ $itemName }}" required>
  <input type="text" class="{{ $inputClass }} js-bar-respond-item-search" value="{{ $displayLabel }}"
    placeholder="Search bar items…" autocomplete="off" spellcheck="false" role="combobox" aria-expanded="false">
</div>
