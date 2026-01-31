<div id="onboarding-modal" tabindex="-1" data-modal-backdrop="static" role="dialog" aria-modal="true" aria-labelledby="onboarding-title" hx-history="false"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-stack-modal justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-lg max-h-full transition-all duration-300 transform scale-95 opacity-0 animate-in fade-in zoom-in-95 fill-mode-forwards">
        <div class="relative bg-white rounded-xl shadow-2xl dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-6 sm:p-8">
                {{-- Header --}}
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <h3 id="onboarding-title" class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                        Welcome to Delight!
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Building a consistent Bible reading habit starts with one chapter.
                    </p>
                </div>

                {{-- Steps --}}
                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-6 mb-8 border border-gray-100 dark:border-gray-700/50">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-4">How it works:</h4>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <span class="flex-shrink-0 flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold mr-3 mt-0.5">1</span>
                            <span class="text-gray-700 dark:text-gray-300">Read a chapter from your Bible.</span>
                        </li>
                        <li class="flex items-start">
                            <span class="flex-shrink-0 flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold mr-3 mt-0.5">2</span>
                            <span class="text-gray-700 dark:text-gray-300">Log what you read here in the app.</span>
                        </li>
                        <li class="flex items-start">
                            <span class="flex-shrink-0 flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold mr-3 mt-0.5">3</span>
                            <span class="text-gray-700 dark:text-gray-300">Watch your progress and streak grow!</span>
                        </li>
                    </ul>
                </div>

                {{-- Actions --}}
                <div class="space-y-4">
                    <button hx-get="{{ route('logs.create') }}" 
                       hx-target="#page-container"
                       hx-swap="innerHTML"
                       hx-push-url="true"
                       class="flex items-center justify-center w-full px-6 py-3.5 text-base font-medium text-white bg-accent-500 hover:bg-accent-600 hover:scale-[1.02] active:scale-[0.98] rounded-xl transition-all shadow-md">
                        Log Your First Reading
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                        </svg>
                    </button>
                    
                    <div class="text-center">
                        <span class="text-sm text-gray-500 dark:text-gray-500">or</span>
                        <button hx-get="{{ route('plans.index') }}" 
                           hx-target="#page-container"
                           hx-swap="innerHTML"
                           hx-push-url="true"
                           class="ml-1 text-sm font-medium text-primary-500 dark:text-primary-400 hover:underline">
                            start with a reading plan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('onboarding-modal');
        if (modalEl) {
            const modal = new Modal(modalEl, {
                backdrop: 'static',
                closable: false,
                onHide: () => {
                    // Modal cannot be dismissed without action
                }
            });
            modal.show();
            
            // If not available on window, try to import or use a fallback if possible
            // But since this is specific to onboarding, we can manually apply the class if needed
            // However, the best approach is to ensure the global patch runs or manually add the class
            
            // Check if the global patch has run on this modal's backdrop
            if (modal._backdropEl && !modal._backdropEl.classList.contains('z-stack-backdrop')) {
                modal._backdropEl.classList.add('z-stack-backdrop');
                modal._backdropEl.classList.remove('z-40'); // Remove default Flowbite z-index
            }
            
            // Store modal instance on the element for later access
            modalEl._flowbiteModal = modal;
            
            // Trigger animation
            setTimeout(() => {
                const inner = modalEl.querySelector('.relative.p-4');
                if (inner) {
                    inner.classList.remove('scale-95', 'opacity-0');
                    inner.classList.add('scale-100', 'opacity-100');
                }
            }, 10);
            
            // Hide modal and backdrop when HTMX content is ready to swap
            // This minimizes flash while ensuring backdrop is properly removed
            document.addEventListener('htmx:beforeSwap', function(event) {
                // Only hide if the request is coming from a navigation action (hx-get with hx-push-url)
                const triggerElement = event.detail && event.detail.requestConfig ? event.detail.requestConfig.elt : null;
                const response = event.detail ? event.detail.xhr : null;
                if (triggerElement && response && response.status === 200 && triggerElement.hasAttribute('hx-push-url')) {
                    modal.hide();

                    // Remove any leftover backdrops
                    document.querySelectorAll('[modal-backdrop]').forEach(function(backdrop) {
                        backdrop.remove();
                    });

                    // Also restore body scroll
                    document.body.classList.remove('overflow-hidden');
                }
            });
        }
    });

</script>