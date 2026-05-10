@props([
    'icon' => 'trophy',
    'label' => null,
    'size' => 'md',
    'state' => 'earned',
])

@php
    $assets = [
        'sparkles' => 'badge-first-reading.png',
        'calendar' => 'badge-calendar.png',
        'calendar-check' => 'badge-calendar.png',
        'book-open' => 'badge-book-completed.png',
        'library' => 'badge-library.png',
        'trending-up' => 'badge-progress.png',
        'flame' => 'badge-streak.png',
        'target' => 'badge-target.png',
        'chart' => 'badge-progress.png',
        'trophy' => 'badge-trophy.png',
    ];

    $sizeClasses = [
        'xs' => 'size-8',
        'sm' => 'size-10',
        'md' => 'size-12',
        'lg' => 'size-14',
        'xl' => 'size-16',
    ];

    $image = $assets[$icon] ?? $assets['trophy'];
    $stateClasses = match ($state) {
        'locked' => 'grayscale opacity-55',
        'muted' => 'grayscale opacity-80',
        default => 'opacity-100',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center']) }}>
    <img
        src="{{ asset('images/achievements/'.$image) }}"
        alt="{{ $label ? $label.' badge' : '' }}"
        class="{{ $sizeClasses[$size] ?? $sizeClasses['md'] }} {{ $stateClasses }} rounded-full object-contain drop-shadow-sm"
        @if (! $label) aria-hidden="true" @endif
        loading="lazy"
    >
</span>
