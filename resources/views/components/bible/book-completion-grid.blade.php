@props([
    'testament' => 'Old'
])

<x-ui.card {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 border border-[#D1D7E0] dark:border-gray-700 transition-colors shadow-lg']) }}>
    <div class="p-6 lg:p-4 xl:p-6">
        <div
            x-data="bookProgressComponent(@js($oldData), @js($newData), @js($testament))"
            x-init="initialize()"
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
            <!-- Testament Content -->
            <div {{ $testament === 'New' ? 'x-cloak' : '' }}>
                <!-- Progress Section -->
                <div class="space-y-3 mb-6">
                    <!-- Testament Label and Percentage -->
                    <div class="flex items-center justify-between">
                        <span class="text-base font-medium text-gray-700 dark:text-gray-300 leading-[1.5]"
                              x-text="`${activeTestament} Testament`"></span>
                        <span class="text-lg lg:text-xl font-bold text-primary-500 leading-[1.5]"
                              x-text="`${current.testament_progress}%`"></span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-3 overflow-hidden">
                        <div
                            class="bg-primary-500 h-3 rounded-full transition-[width] duration-500 ease-in-out"
                            x-ref="progressBar"
                            style="width: 0%"
                        ></div>
                    </div>

                    <!-- Stats Summary -->
                    <div class="grid grid-cols-3 gap-2 text-center text-sm">
                        <div class="bg-success-500/10 dark:bg-success-500/20 rounded-lg py-2 px-1">
                            <div class="font-bold text-success-500 text-base lg:text-lg leading-[1.5]"
                                 x-text="current.completed_books"></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 leading-tight">completed</div>
                        </div>
                        <div class="bg-primary-500/10 dark:bg-primary-500/20 rounded-lg py-2 px-1">
                            <div class="font-bold text-primary-500 text-base lg:text-lg leading-[1.5]"
                                 x-text="current.in_progress_books"></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 leading-tight">in progress</div>
                        </div>
                        <div class="bg-gray-100 dark:bg-gray-700 rounded-lg py-2 px-1">
                            <div class="font-bold text-gray-600 dark:text-gray-400 text-base lg:text-lg leading-[1.5]"
                                 x-text="current.not_started_books"></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 leading-tight">not started</div>
                        </div>
                    </div>
                </div>

                <!-- Books Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                    <template x-for="book in current.processed_books" :key="book.name">
                        <div class="relative p-3 rounded-lg border-2 text-center transition-all duration-200 shadow-sm hover:shadow-md cursor-pointer group"
                             :class="statusClasses(book.status)"
                             :title="`${book.name}: ${book.chapters_read}/${book.chapter_count} chapters (${book.percentage}%)`">
                            <!-- Book Name -->
                            <div class="font-semibold text-sm mb-1 truncate leading-[1.5]"
                                 x-text="book.name"></div>

                            <!-- Progress Percentage -->
                            <div class="text-sm opacity-90 mb-2 leading-[1.5]"
                                 x-text="`${book.percentage}%`"></div>

                            <!-- Mini Progress Bar for In-Progress Books -->
                            <template x-if="book.status === 'in-progress'">
                                <div class="w-full bg-white/30 rounded-full h-1 overflow-hidden">
                                    <div class="bg-white h-1 transition-all duration-300"
                                         :style="`width: ${book.percentage}%`"></div>
                                </div>
                            </template>

                            <!-- Completion Badge -->
                            <template x-if="book.status === 'completed'">
                                <div class="absolute -top-1 -right-1 w-4 h-4 bg-success-500 rounded-full flex items-center justify-center">
                                    <div class="w-2 h-2 bg-white rounded-full"></div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Legend -->
                <div class="flex items-center justify-center space-x-6 mt-6 pt-4 border-t border-gray-300 dark:border-gray-600">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-success-500 rounded border-2 border-success-500"></div>
                        <span class="text-sm text-gray-600 dark:text-gray-400 leading-[1.5]">Completed</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-primary-500 rounded border-2 border-primary-500"></div>
                        <span class="text-sm text-gray-600 dark:text-gray-400 leading-[1.5]">In Progress</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-white dark:bg-gray-800 rounded border-2 border-gray-300 dark:border-gray-600"></div>
                        <span class="text-sm text-gray-600 dark:text-gray-400 leading-[1.5]">Not Started</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-ui.card>

<script>
    /**
     * Book Progress Component - Client-side testament toggling with animated progress bar.
     */
    function bookProgressComponent(oldData, newData, initialTestament = 'Old') {
        const normalizeData = (data = {}) => ({
            testament_progress: data.testament_progress ?? 0,
            processed_books: data.processed_books ?? [],
            completed_books: data.completed_books ?? 0,
            in_progress_books: data.in_progress_books ?? 0,
            not_started_books: data.not_started_books ?? 0,
        });

        return {
            activeTestament: ['Old', 'New'].includes(initialTestament) ? initialTestament : 'Old',
            testaments: {
                Old: normalizeData(oldData),
                New: normalizeData(newData),
            },

            initialize() {
                this.$watch('activeTestament', () => this.updateProgressBar());

                this.$nextTick(() => {
                    this.updateProgressBar(true);
                });
            },

            get current() {
                return this.testaments[this.activeTestament] ?? this.testaments.Old;
            },

            statusClasses(status) {
                switch (status) {
                    case 'completed':
                        return 'bg-success-500 text-white border-success-500 dark:bg-success-600 dark:border-success-600';
                    case 'in-progress':
                        return 'bg-primary-500 text-white border-primary-500';
                    default:
                        return 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-primary-500/30 dark:hover:border-primary-500/50';
                }
            },

            setTestament(testament) {
                if (this.activeTestament === testament || ! this.testaments[testament]) {
                    return;
                }

                this.activeTestament = testament;
            },

            progressPercentage(testament) {
                const dataset = this.testaments[testament];

                if (! dataset) {
                    return 0;
                }

                return Number(dataset.testament_progress) || 0;
            },

            updateProgressBar(initial = false) {
                const bar = this.$refs.progressBar;

                if (! bar) {
                    return;
                }

                const targetWidth = this.progressPercentage(this.activeTestament);

                if (initial) {
                    bar.style.width = `${targetWidth}%`;
                    return;
                }

                requestAnimationFrame(() => {
                    bar.style.width = `${targetWidth}%`;
                });
            }
        };
    }
</script>
