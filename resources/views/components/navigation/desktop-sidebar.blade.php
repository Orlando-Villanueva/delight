{{-- Desktop Sidebar Navigation Component --}}
{{-- Flowbite-based sidebar with HTMX navigation and hover states only --}}

<aside class="w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 lg:pt-16 flex flex-col">
    <div class="flex-1 px-3 py-4 overflow-y-auto">
        <!-- Navigation Menu -->
        <ul class="space-y-2 font-medium">
            <li>
                <x-navigation.nav-link route="dashboard" label="Dashboard" variant="sidebar">
                    <x-slot:icon>
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 15v4m6-6v6m6-4v4m6-6v6M3 11l6-5 6 5 5.5-5.5" />
                    </x-slot:icon>
                </x-navigation.nav-link>
            </li>
            <li>
                <x-navigation.nav-link route="logs.create" label="Log Reading" variant="sidebar">
                    <x-slot:icon>
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6.03v13m0-13c-2.819-.831-4.715-1.076-8.029-1.023A.99.99 0 0 0 3 6v11c0 .563.466 1.014 1.03 1.007 3.122-.043 5.018.212 7.97 1.023m0-13c2.819-.831 4.715-1.076 8.029-1.023A.99.99 0 0 1 21 6v11c0 .563-.466 1.014-1.03 1.007-3.122-.043-5.018.212-7.97 1.023" />
                    </x-slot:icon>
                </x-navigation.nav-link>
            </li>
            <li>
                <x-navigation.nav-link route="logs.index" label="Reading History" variant="sidebar">
                    <x-slot:icon>
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3M3.22302 14C4.13247 18.008 7.71683 21 12 21c4.9706 0 9-4.0294 9-9 0-4.97056-4.0294-9-9-9-3.72916 0-6.92858 2.26806-8.29409 5.5M7 9H3V5" />
                    </x-slot:icon>
                </x-navigation.nav-link>
            </li>
        </ul>
    </div>

    <!-- Feedback Link (Isolated at bottom) -->
    <div class="p-3 border-t border-gray-200 dark:border-gray-700">
        <x-navigation.nav-link route="feedback.create" label="Feedback" variant="sidebar">
            <x-slot:icon>
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 9h5m3 0h2M7 12h2m3 0h5M5 5h14a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1h-6.616a1 1 0 0 0-.67.257l-2.88 2.592A.5.5 0 0 1 8 18.477V17a1 1 0 0 0-1-1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
            </x-slot:icon>
        </x-navigation.nav-link>
    </div>
</aside>
