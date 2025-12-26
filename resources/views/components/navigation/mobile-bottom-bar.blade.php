{{-- Mobile Bottom Navigation Bar Component --}}
{{-- Flowbite-based application bar with floating action button --}}

<div
    class="fixed z-50 w-full h-16 max-w-lg -translate-x-1/2 bg-white/80 backdrop-blur-md border border-gray-200 rounded-full bottom-4 left-1/2 dark:bg-gray-800/80 dark:border-gray-700 shadow-xl lg:hidden">
    <div class="grid h-full max-w-lg grid-cols-3 mx-auto">
        <!-- Dashboard Button (Left) -->
        <x-navigation.nav-link route="dashboard" label="Dashboard" variant="mobile" class="rounded-s-full">
            <x-slot:icon>
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 15v4m6-6v6m6-4v4m6-6v6M3 11l6-5 6 5 5.5-5.5" />
            </x-slot:icon>
        </x-navigation.nav-link>

        <!-- Log Reading FAB (Center) -->
        <div class="flex items-center justify-center">
            <button type="button" hx-get="{{ route('logs.create') }}" hx-target="#page-container" hx-swap="innerHTML"
                hx-push-url="true"
                class="inline-flex items-center justify-center w-10 h-10 font-medium bg-accent-500 rounded-full hover:bg-accent-600 group focus:ring-4 focus:ring-accent-300 focus:outline-none dark:focus:ring-accent-800">
                <svg class="w-5 h-5 text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24"
                    height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M5 12h14m-7 7V5" />
                </svg>
                <span class="sr-only">Log Reading</span>
            </button>
        </div>

        <!-- Reading History Button (Right) -->
        <x-navigation.nav-link route="logs.index" label="Reading History" variant="mobile" class="rounded-e-full">
            <x-slot:icon>
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3M3.22302 14C4.13247 18.008 7.71683 21 12 21c4.9706 0 9-4.0294 9-9 0-4.97056-4.0294-9-9-9-3.72916 0-6.92858 2.26806-8.29409 5.5M7 9H3V5" />
            </x-slot:icon>
        </x-navigation.nav-link>
    </div>
</div>
