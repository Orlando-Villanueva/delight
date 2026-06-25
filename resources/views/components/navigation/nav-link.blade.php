{{-- Shared Navigation Link Component --}}
{{-- Reusable HTMX navigation button with icon support --}}

@props([
    'route' => null,
    'url' => null,
    'icon',
    'label',
    'variant' => 'sidebar', // 'sidebar' or 'mobile'
    'activePath' => null,
    'activePrefix' => false,
])

@php
    $finalUrl = $url ?? ($route ? route($route) : '#');
    $resolvedActivePath = $activePath ?? parse_url($finalUrl, PHP_URL_PATH) ?: '/';
@endphp

@if ($variant === 'sidebar')
    {{-- Desktop Sidebar Style --}}
    <button type="button" hx-get="{{ $finalUrl }}" hx-target="#page-container" hx-swap="innerHTML" hx-push-url="true"
        data-sidebar-nav-link title="{{ $label }}"
        x-bind:aria-current="isSidebarPathActive('{{ $resolvedActivePath }}', {{ $activePrefix ? 'true' : 'false' }}) ? 'page' : null"
        x-bind:class="{
            'bg-primary-50 text-primary-700 dark:bg-gray-700': isSidebarPathActive('{{ $resolvedActivePath }}', {{ $activePrefix ? 'true' : 'false' }}),
        }"
        {{ $attributes->merge(['class' => 'group flex w-full items-center rounded-lg p-2 text-gray-900 transition-colors hover:bg-primary-50 dark:text-white dark:hover:bg-gray-700']) }}>
        <span data-sidebar-icon-slot class="inline-flex h-6 w-10 shrink-0 items-center justify-center">
            <svg class="w-6 h-6 text-gray-600 transition duration-75 dark:text-gray-400 group-hover:text-gray-800 dark:group-hover:text-gray-200"
                x-bind:class="{ '!text-primary-600 dark:!text-primary-400': isSidebarPathActive('{{ $resolvedActivePath }}', {{ $activePrefix ? 'true' : 'false' }}) }"
                aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                viewBox="0 0 24 24">
                {!! $icon !!}
            </svg>
        </span>
        <span
            class="max-w-0 overflow-hidden whitespace-nowrap opacity-0 transition-[max-width,opacity,margin] duration-200 ease-in-out motion-reduce:transition-none xl:ms-1 xl:max-w-40 xl:opacity-100"
            x-bind:class="sidebarCollapsed ? '!max-w-0 !opacity-0 !ms-0' : '!max-w-40 !opacity-100 !ms-1'">{{ $label }}</span>
    </button>
@elseif($variant === 'mobile')
    {{-- Mobile Bottom Bar Style --}}
    <button type="button" hx-get="{{ $finalUrl }}" hx-target="#page-container" hx-swap="innerHTML"
        hx-push-url="true"
        {{ $attributes->merge(['class' => 'inline-flex flex-col items-center justify-center px-3 sm:px-5 active:bg-gray-100/50 dark:active:bg-gray-800/50 group transition-colors']) }}>
        <svg class="w-6 h-6 text-gray-600 dark:text-gray-400 group-active:text-gray-800 dark:group-active:text-gray-200 transition-colors"
            aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
            viewBox="0 0 24 24">
            {!! $icon !!}
        </svg>
        <span class="sr-only">{{ $label }}</span>
    </button>
@endif
