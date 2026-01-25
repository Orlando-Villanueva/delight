{{-- Shared Navigation Link Component --}}
{{-- Reusable HTMX navigation button with icon support --}}

@props([
    'route' => null,
    'url' => null,
    'icon',
    'label',
    'variant' => 'sidebar', // 'sidebar' or 'mobile'
])

@php
    $finalUrl = $url ?? ($route ? route($route) : '#');
@endphp

@if ($variant === 'sidebar')
    {{-- Desktop Sidebar Style --}}
    <button type="button" hx-get="{{ $finalUrl }}" hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
        {{ $attributes->merge(['class' => 'w-full flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-primary-50 dark:hover:bg-gray-700 group transition-colors']) }}>
        <svg class="w-6 h-6 text-gray-600 transition duration-75 dark:text-gray-400 group-hover:text-gray-800 dark:group-hover:text-gray-200"
            aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
            viewBox="0 0 24 24">
            {!! $icon !!}
        </svg>
        <span class="ms-3">{{ $label }}</span>
    </button>
@elseif($variant === 'mobile')
    {{-- Mobile Bottom Bar Style --}}
    <button type="button" hx-get="{{ $finalUrl }}" hx-target="#page-container" hx-swap="innerHTML"
        hx-push-url="true"
        {{ $attributes->merge(['class' => 'inline-flex flex-col items-center justify-center px-5 active:bg-gray-100/50 dark:active:bg-gray-800/50 group transition-colors']) }}>
        <svg class="w-6 h-6 text-gray-600 dark:text-gray-400 group-active:text-gray-800 dark:group-active:text-gray-200 transition-colors"
            aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
            viewBox="0 0 24 24">
            {!! $icon !!}
        </svg>
        <span class="sr-only">{{ $label }}</span>
    </button>
@endif
