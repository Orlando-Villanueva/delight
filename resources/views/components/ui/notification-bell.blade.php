{{-- Notification Bell Dropdown Component --}}
{{-- Uses the same Flowbite pattern as the profile dropdown --}}

@props([
    'dropdownId' => 'dropdown-notifications',
    'unreadCount' => 0,
])

<div class="flex items-center">
    <button type="button"
        class="flex p-2 text-gray-400 hover:text-gray-500 rounded-full focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-700"
        aria-expanded="false" data-dropdown-toggle="{{ $dropdownId }}" data-dropdown-placement="bottom-end">
        <span class="sr-only">View notifications</span>
        <div class="relative">
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            @if ($unreadCount > 0)
                <span
                    class="absolute -top-1 -right-1 block h-2.5 w-2.5 rounded-full ring-2 ring-white bg-red-500"></span>
            @endif
        </div>
    </button>

    <div class="z-50 hidden my-4 text-base list-none bg-white divide-y divide-gray-100 rounded-lg shadow-lg border border-gray-200 dark:bg-gray-800 dark:divide-gray-700 dark:border-gray-700 w-80"
        id="{{ $dropdownId }}">
        <div hx-get="{{ route('notifications.index') }}" hx-trigger="load once" hx-swap="innerHTML" class="w-full">
            <!-- Loading State -->
            <div class="p-6 text-center text-sm text-gray-500 flex flex-col items-center justify-center gap-3">
                <svg class="animate-spin h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                <div class="font-medium text-gray-600 dark:text-gray-400">Loading notifications...</div>
            </div>
        </div>
    </div>
</div>
