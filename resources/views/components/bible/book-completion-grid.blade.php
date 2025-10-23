@props([
    'testament' => 'Old'
])

<x-ui.card {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 border border-[#D1D7E0] dark:border-gray-700 transition-colors shadow-lg']) }}>
    <div class="p-6 lg:p-4 xl:p-6">
        <div
            x-data="bookProgressComponent(@js($oldData['testament_progress']), @js($newData['testament_progress']))"
            x-init="init()"
        >
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
                    'notStartedBooks' => $oldData['not_started_books'],
                    'progressRef' => 'oldProgressBar'
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
                    'notStartedBooks' => $newData['not_started_books'],
                    'progressRef' => 'newProgressBar'
                ])
            </div>
        </div>
    </div>
</x-ui.card>

<script>
    /**
     * Book Progress Component - Client-side testament toggling with animated progress bar.
     */
    function bookProgressComponent(oldProgress, newProgress) {
        return {
            activeTestament: 'Old',
            progressValues: {
                Old: Number(oldProgress) || 0,
                New: Number(newProgress) || 0,
            },

            setTestament(testament) {
                if (this.activeTestament === testament) {
                    return;
                }

                const previousTestament = this.activeTestament;
                const previousValue = this.progressValues[previousTestament];
                const targetRef = testament === 'Old' ? 'oldProgressBar' : 'newProgressBar';
                const targetBar = this.$refs[targetRef];

                if (targetBar) {
                    targetBar.style.width = `${previousValue}%`;
                    void targetBar.offsetWidth;
                }

                this.activeTestament = testament;

                this.$nextTick(() => {
                    this.animateProgressBar(testament);
                });
            },

            animateProgressBar(testament) {
                const refName = testament === 'Old' ? 'oldProgressBar' : 'newProgressBar';
                const bar = this.$refs[refName];

                if (! bar) {
                    return;
                }

                bar.style.width = `${this.progressValues[testament]}%`;
            },

            init() {
                const oldBar = this.$refs.oldProgressBar;
                const newBar = this.$refs.newProgressBar;

                if (oldBar) {
                    oldBar.style.width = `${this.progressValues.Old}%`;
                }

                if (newBar) {
                    newBar.style.width = `${this.progressValues.New}%`;
                }
            }
        };
    }
</script>