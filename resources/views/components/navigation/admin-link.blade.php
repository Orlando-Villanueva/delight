@props(['compact' => false])

<a href="{{ route('filament.admin.pages.admin-home') }}" title="Admin"
    {{ $attributes->class([
        'group flex w-full items-center rounded-lg text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800',
        'gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100/80 dark:text-gray-300 dark:hover:bg-gray-700/50' => $compact,
        'p-2 font-medium text-gray-900 hover:bg-primary-50 dark:text-white dark:hover:bg-gray-700' => ! $compact,
    ]) }}>
    <span @class(['inline-flex shrink-0 items-center justify-center', 'h-4 w-4 opacity-70' => $compact, 'h-6 w-10' => ! $compact])>
        <svg @class(['h-4 w-4' => $compact, 'h-6 w-6 text-gray-600 dark:text-gray-400' => ! $compact])
            aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 19h16M6 17V9m4 8V5m4 12v-4m4 4V7" />
        </svg>
    </span>
    <span @if (! $compact)
        class="max-w-0 overflow-hidden whitespace-nowrap opacity-0 transition-[max-width,opacity,margin] duration-200 ease-in-out motion-reduce:transition-none xl:ms-1 xl:max-w-40 xl:opacity-100"
        x-bind:class="sidebarCollapsed ? '!max-w-0 !opacity-0 !ms-0' : '!max-w-40 !opacity-100 !ms-1'"
        @endif>Admin</span>
    <span @class(['ms-auto shrink-0 text-gray-500 dark:text-gray-400', 'hidden xl:inline-flex' => ! $compact])
        @if (! $compact) x-bind:class="sidebarCollapsed ? '!hidden' : '!inline-flex'" @endif>
        <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6 6 6-6 6" />
        </svg>
    </span>
</a>
