{{-- Desktop Navbar Component --}}
{{-- Flowbite-based navbar with logo and profile dropdown --}}

<nav class="fixed top-0 z-stack-nav w-full bg-white border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700">
    <div class="px-3 py-3 lg:px-5 lg:pl-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center justify-start rtl:justify-end">
                <a href="{{ route('dashboard') }}" hx-get="{{ route('dashboard') }}" hx-target="#page-container"
                    hx-push-url="true" class="flex ms-2 md:me-24">
                    <img src="{{ asset('images/logo-64.png') }}?v={{ config('app.asset_version') }}" class="h-8 me-3"
                        alt="{{ config('app.name') }} Logo" />
                    <span
                        class="self-center text-xl font-semibold sm:text-2xl whitespace-nowrap text-gray-900 dark:text-white">{{ config('app.name') }}</span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <!-- Log Reading Button Pill -->
                <button type="button" hx-get="{{ route('logs.create') }}" hx-target="#page-container"
                    hx-swap="innerHTML" hx-push-url="true"
                    class="hidden lg:inline-flex text-white bg-accent-500 hover:bg-accent-600 focus:outline-none focus:ring-4 focus:ring-accent-300 font-medium rounded-full text-sm px-5 py-2 text-center dark:bg-accent-600 dark:hover:bg-accent-700 dark:focus:ring-accent-800">
                    Log Reading
                </button>

                <!-- Notification Bell -->
                <x-ui.notification-bell />

                <!-- Profile Dropdown -->
                <x-navigation.profile-dropdown dropdown-id="dropdown-user" size="default" />
            </div>
        </div>
    </div>
</nav>