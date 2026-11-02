@extends('layouts.print-document')

@section('title', 'Receive · '.$purchaseList->reference_no)

@push('styles')
  <style>
    :root { --co-brand: #940000; }
  </style>
  @include('hotel.erp.bar.partials._purchase-list-respond-picker-styles')
@endpush

@section('toolbar')
  <div class="print-doc-toolbar">
    <button type="button" class="btn btn-print-primary" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
  </div>
@endsection

@section('content')
  <div style="max-width:960px;margin:0 auto;padding:24px;">
    <h2 style="margin:0 0 4px;font-size:22px;">{{ $hotel->display_name ?: $hotel->name }}</h2>
    <p class="text-muted mb-3">{{ $purchaseList->department?->name ?? 'Department' }} · {{ $purchaseList->reference_no }}</p>
    @include('hotel.erp.bar.partials._purchase-list-respond-inner')
  </div>
@endsection

@push('scripts')
  @include('hotel.erp.bar.partials._purchase-list-respond-scripts')
@endpush
