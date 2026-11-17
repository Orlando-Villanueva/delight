@props(['standalone' => false])

@if (auth()->guest() && request()->routeIs('landing', 'login', 'register', 'password.request', 'password.reset', 'privacy-policy', 'terms-of-service', 'guides.paper-bible'))
    @php
        $returnQuery = request()->query();
        unset($returnQuery['locale']);

        if (request()->routeIs('password.reset')) {
            unset($returnQuery['token']);
        }

        $returnTo = request()->getPathInfo().($returnQuery === [] ? '' : '?'.http_build_query($returnQuery));
    @endphp
    <details data-language-menu class="{{ $standalone ? 'ml-auto mr-3 mt-3' : '' }} group relative w-fit">
        <summary title="{{ __('Language') }}" class="flex min-h-11 min-w-11 cursor-pointer list-none items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-blue-500 {{ $standalone ? 'dark:text-gray-300 dark:hover:bg-gray-700' : '' }} [&::-webkit-details-marker]:hidden">
            <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18Z"/></svg>
            <span class="sr-only">{{ __('Language') }}</span>
        </summary>
        <nav aria-label="{{ __('Language') }}" class="absolute right-0 top-full z-[60] mt-2 min-w-40 rounded-xl border border-gray-200 bg-white p-1.5 text-sm shadow-lg dark:border-gray-600 dark:bg-gray-800">
            @foreach (config('localization.supported') as $locale => $language)
                <form method="POST" action="{{ route('language.update') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $locale }}">
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                    <button type="submit" lang="{{ $locale }}" aria-current="{{ app()->getLocale() === $locale ? 'true' : 'false' }}" class="flex min-h-11 w-full items-center justify-between gap-4 rounded-lg px-3 text-left font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-blue-500 dark:text-gray-200 dark:hover:bg-gray-700">
                        {{ $language }}
                        @if (app()->getLocale() === $locale)<span aria-hidden="true" class="text-blue-600 dark:text-blue-300">✓</span>@endif
                    </button>
                </form>
            @endforeach
        </nav>
    </details>
    @once
        <script src="{{ asset('language-menu.js') }}" defer></script>
    @endonce
@endif
