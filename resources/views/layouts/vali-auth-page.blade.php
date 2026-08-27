@extends('layouts.auth')

@section('title', $title)

@section('content')
{!! $content !!}
@endsection

@push('scripts')
{!! $scripts !!}
@endpush
