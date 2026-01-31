@props([
    'action',
    'target',
    'select',
    'buttonText',
    'showIcon' => false,
])

<div id="first-reading-celebration" class="py-12 text-center" data-is-first-reading="true">
    <div class="inline-flex items-center justify-center w-24 h-24 mb-6 bg-gradient-to-br from-yellow-100 to-amber-200 dark:from-yellow-900 dark:to-amber-800 rounded-full shadow-lg">
        <span class="text-5xl">🎉</span>
    </div>
    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-3">
        You've started! 1 down, 365 to go
    </h2>
    <p class="text-lg text-gray-600 dark:text-gray-400 mb-8 max-w-md mx-auto">
        Your Bible reading journey begins now. Keep the momentum going!
    </p>
    <button type="button"
        hx-get="{{ $action }}"
        hx-target="{{ $target }}"
        hx-swap="outerHTML"
        hx-select="{{ $select }}"
        class="inline-flex items-center px-8 py-4 text-lg font-semibold text-white bg-primary-600 hover:bg-primary-700 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:focus:ring-offset-gray-800">
        @if ($showIcon)
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
        @endif
        {{ $buttonText }}
    </button>
    <script>
        if (typeof window.confetti === 'function') {
            setTimeout(function() {
                window.confetti({
                    particleCount: 200,
                    spread: 100,
                    origin: { y: 0.4 }
                });
            }, 100);
        }
    </script>
</div>
