<div class="max-w-4xl mx-auto pb-20 md:pb-4">
    {{-- Reading Log Content Container --}}
    <div id="reading-content" class="relative">
        {{-- Loading Indicator - Only covers the logs area --}}
        <div id="loading"
            class="htmx-indicator hidden absolute inset-0 bg-white dark:bg-gray-900 bg-opacity-90 dark:bg-opacity-90 flex items-center justify-center z-10"
            aria-hidden="true">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
            <span class="ml-3 text-gray-600 dark:text-gray-400">Loading readings...</span>
        </div>

        @include('partials.reading-log-list', compact('logs'))
    </div>
</div>
