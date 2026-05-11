@props([
    'width' => 'medium',
])

@php
    $widthClass = match ($width) {
        'narrow' => 'max-w-2xl',
        'form' => 'max-w-md',
        'medium' => 'max-w-3xl',
        'list' => 'max-w-4xl',
        'wide' => 'max-w-7xl',
        'full' => 'max-w-none',
        default => 'max-w-3xl',
    };
@endphp

<div {{ $attributes->class(['mx-auto flex w-full flex-col gap-6', $widthClass]) }}>
    {{ $slot }}
</div>
