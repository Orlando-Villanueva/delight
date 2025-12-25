{{-- Shared Profile Dropdown Component --}}
{{-- Used in both desktop and mobile navigation --}}

@props([
    'dropdownId' => 'dropdown-user',
    'size' => 'default', // 'default' (w-9 h-9) or 'small' (w-8 h-8)
])

@php
    $user = auth()->user();
    $avatarUrl = $user?->avatar_url;
    $initial = $user && $user->name ? mb_strtoupper(mb_substr($user->name, 0, 1)) : 'U';
@endphp

<div class="flex items-center">
    <button type="button"
        class="flex text-sm bg-primary-500 rounded-full focus:ring-4 focus:ring-primary-300 dark:focus:ring-primary-600"
        aria-expanded="false" data-dropdown-toggle="{{ $dropdownId }}" data-dropdown-placement="bottom-end">
        <span class="sr-only">Open user menu</span>
        <div @class([
            'rounded-full flex items-center justify-center text-white font-medium overflow-hidden',
            'w-9 h-9 text-base' => $size === 'default',
            'w-8 h-8 text-sm' => $size === 'small',
        ])>
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $user?->name }} avatar" class="w-full h-full object-cover"
                    loading="lazy" referrerpolicy="no-referrer">
            @else
                {{ $initial }}
            @endif
        </div>
    </button>

    <div id="{{ $dropdownId }}"
        class="z-50 hidden my-4 text-base list-none bg-white divide-y divide-gray-100 rounded-lg shadow-lg border border-gray-200 dark:bg-gray-800 dark:divide-gray-700 dark:border-gray-700 w-56">
        <div class="px-5 py-4 bg-gray-50/50 dark:bg-gray-800/50 rounded-t-lg" role="none">
            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate" role="none">
                {{ $user->name ?? 'User' }}
            </p>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate mt-0.5" role="none">
                {{ $user->email ?? '' }}
            </p>
        </div>
        <ul class="py-2" role="none">
            <li>
                <a href="{{ route('feedback.create') }}"
                    class="flex items-center gap-2 px-5 py-2.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white transition-colors"
                    role="menuitem">
                    <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                    Feedback
                </a>
            </li>
            <li>
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button type="submit"
                        class="w-full flex items-center gap-2 px-5 py-2.5 text-sm text-destructive-600 hover:bg-destructive-50 dark:text-destructive-400 dark:hover:bg-destructive-900/30 transition-colors font-medium"
                        role="menuitem">
                        <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Sign out
                    </button>
                </form>
            </li>
        </ul>
    </div>
</div>
