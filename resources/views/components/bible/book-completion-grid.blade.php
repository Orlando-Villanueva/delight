@props([
    'testament' => 'Old'
])

{{-- Data is now provided by the component class through dependency injection --}}

<x-ui.card {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 border border-[#D1D7E0] dark:border-gray-700 transition-colors shadow-lg']) }}>
    <div class="p-6 lg:p-4 xl:p-6">
        <div x-data="bookProgressComponent(@js($testament), @js(route('preferences.testament')))">
            <!-- Header with Title and Testament Toggle -->
            <div class="flex items-start justify-between mb-6">
                <h3 class="text-lg lg:text-xl font-semibold text-[#4A5568] dark:text-gray-200 leading-[1.5]">
                    Bible Reading Progress
                </h3>
                
                <!-- Testament Toggle -->
                <x-bible.testament-toggle 
                    id="book-grid-testament"
                    class="ml-4 flex-shrink-0" 
                />
            </div>

            <!-- Old Testament Content -->
            <div x-show="activeTestament === 'Old'" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 {{ $testament === 'New' ? 'x-cloak' : '' }}>
                @include('partials.book-progress-content', [
                    'testament' => 'Old',
                    'processedBooks' => $oldData['processed_books'],
                    'testamentProgress' => $oldData['testament_progress'],
                    'completedBooks' => $oldData['completed_books'],
                    'inProgressBooks' => $oldData['in_progress_books'],
                    'notStartedBooks' => $oldData['not_started_books']
                ])
            </div>

            <!-- New Testament Content -->
            <div x-show="activeTestament === 'New'" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 {{ $testament === 'Old' ? 'x-cloak' : '' }}>
                @include('partials.book-progress-content', [
                    'testament' => 'New',
                    'processedBooks' => $newData['processed_books'],
                    'testamentProgress' => $newData['testament_progress'],
                    'completedBooks' => $newData['completed_books'],
                    'inProgressBooks' => $newData['in_progress_books'],
                    'notStartedBooks' => $newData['not_started_books']
                ])
            </div>
        </div>
    </div>
</x-ui.card>

<script>
    /**
     * Book Progress Component - Simple Testament Selection
     * Alpine toggles preloaded content locally and persists preference asynchronously.
     */
    function bookProgressComponent(serverDefault, preferenceUrl) {
        return {
            // State - Use server preference (from session)
            activeTestament: serverDefault,
            preferenceUrl: preferenceUrl ?? null,
            selectTestament(testament) {
                if (this.activeTestament === testament) {
                    return;
                }

                this.activeTestament = testament;

                if (!this.preferenceUrl) {
                    return;
                }

                const tokenElement = document.head.querySelector('meta[name="csrf-token"]');
                const token = tokenElement ? tokenElement.getAttribute('content') : '';

                window.fetch(this.preferenceUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ testament }),
                }).catch(() => {
                    // Silently ignore preference persistence failures
                });
            }
        };
    }
</script>
