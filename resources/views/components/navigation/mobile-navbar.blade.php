{{-- Mobile Navbar Component --}}
{{-- Flowbite-based navbar that scrolls with content (not fixed) --}}

<nav class="lg:hidden bg-white border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center">
                    <img src="{{ asset('images/logo-64.png') }}?v={{ config('app.asset_version') }}"
                        srcset="{{ asset('images/logo-64.png') }}?v={{ config('app.asset_version') }} 1x, {{ asset('images/logo-64-2x.png') }}?v={{ config('app.asset_version') }} 2x"
                        alt="{{ config('app.name') }} Logo" class="w-full h-full object-contain" />
                </div>
                <h1 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-white">
                    {{ config('app.name') }}
                </h1>
            </div>

            <!-- Mobile User Menu -->
            <div class="flex items-center gap-3">
                <x-ui.notification-bell dropdown-id="dropdown-notifications-mobile" />
                <x-navigation.profile-dropdown dropdown-id="dropdown-user-mobile" size="small" />
            </div>
        </div>
    </div>
</nav>
