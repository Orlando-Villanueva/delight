{{-- Mobile Bottom Navigation Bar Component --}}
{{-- Uniform icon navigation with accent-colored Log button --}}

<div
    class="fixed z-50 w-full h-16 max-w-lg -translate-x-1/2 bg-white/80 backdrop-blur-md border border-gray-200 rounded-full bottom-4 left-1/2 dark:bg-gray-800/80 dark:border-gray-700 shadow-xl lg:hidden">
    <div class="grid h-full max-w-lg grid-cols-4 mx-auto">
        {{-- Dashboard --}}
        <x-navigation.nav-link route="dashboard" label="Dashboard" variant="mobile" class="rounded-s-full">
            <x-slot:icon>
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 15v4m6-6v6m6-4v4m6-6v6M3 11l6-5 6 5 5.5-5.5" />
            </x-slot:icon>
        </x-navigation.nav-link>

        {{-- Log Reading (accent colored) --}}
        <button type="button" hx-get="{{ route('logs.create') }}" hx-target="#page-container" hx-swap="innerHTML"
            hx-push-url="true"
            class="inline-flex flex-col items-center justify-center px-5 active:bg-gray-100/50 dark:active:bg-gray-800/50 group transition-colors">
            <svg class="w-6 h-6 text-accent-500 dark:text-accent-400 group-active:text-accent-600 dark:group-active:text-accent-300 transition-colors"
                aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                viewBox="0 0 24 24">
                {{-- Plus circle icon --}}
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 7.757v8.486M7.757 12h8.486M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <span class="sr-only">Log Reading</span>
        </button>

        {{-- Reading Plans --}}
        <x-navigation.nav-link :route="$smartPlansRoute" label="Plans" variant="mobile">
            <x-slot:icon>
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4" />
            </x-slot:icon>
        </x-navigation.nav-link>

        {{-- Reading History --}}
        <x-navigation.nav-link route="logs.index" label="History" variant="mobile" class="rounded-e-full">
            <x-slot:icon>
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3M3.22302 14C4.13247 18.008 7.71683 21 12 21c4.9706 0 9-4.0294 9-9 0-4.97056-4.0294-9-9-9-3.72916 0-6.92858 2.26806-8.29409 5.5M7 9H3V5" />
            </x-slot:icon>
        </x-navigation.nav-link>
    </div>
</div>
