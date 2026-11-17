@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">
        <div class="text-6xl font-bold text-primary-500 mb-4">{{ $status }}</div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-6">{{ __(match ($status) { 419 => 'Page Expired', 429 => 'Too Many Requests', 503 => 'Service Unavailable' }) }}</h1>
        <a href="{{ route('landing') }}" class="inline-flex items-center justify-center px-4 py-2 bg-primary-500 text-white rounded-md">{{ __('Go Home') }}</a>
    </div>
</div>
@endsection
