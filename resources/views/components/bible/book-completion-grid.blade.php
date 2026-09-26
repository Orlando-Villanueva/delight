@props([
    'testament' => 'Old',
])

<x-ui.card
    {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 mb-0 border border-[#D1D7E0] dark:border-gray-700 transition-colors shadow-lg']) }}>
    <div class="p-6 lg:p-4 xl:p-6">
        <div x-data="bookProgressComponent(@js($oldData), @js($newData), @js($deuterocanonicalData), @js($testament), @js($overallData))">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-lg lg:text-xl font-semibold text-gray-900 dark:text-gray-100 leading-[1.5]">
                    Bible Reading Progress
                </h3>
                <div class="hidden sm:block">
                    <x-bible.testament-toggle id="book-grid-testament-desktop" variant="desktop" :show-deuterocanonical="$deuterocanonicalData !== null" />
                </div>
            </div>

            <!-- Overall Bible progress -->
            <div class="mb-5">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-base font-medium text-gray-600 dark:text-gray-300 leading-[1.5]"
                        x-text="overallProgressLabel()"></span>
                    <span class="text-lg lg:text-xl font-bold text-gray-900 dark:text-white leading-[1.5]"
                        x-text="`${overallProgressPercent().toFixed(1)}%`"></span>
                </div>
                <div class="mt-2 w-full bg-gray-200 dark:bg-gray-600 rounded-full h-3 overflow-hidden"
                    role="progressbar"
                    :aria-label="`${overallProgressLabel()} progress`"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-valuenow="overallProgressPercent()"
                    :aria-valuetext="`${overallProgressPercent().toFixed(1)} percent`">
                    <div class="bg-primary-500 h-3 rounded-full transition-[width] duration-500 ease-in-out"
                        :style="`width: ${overallProgressPercent()}%`"></div>
                </div>
                <div class="mt-4 sm:hidden">
                    <x-bible.testament-toggle id="book-grid-testament" variant="mobile" :show-deuterocanonical="$deuterocanonicalData !== null" />
                </div>
            </div>

            <!-- Testament Content -->
            <div {{ $testament === 'New' ? 'x-cloak' : '' }}>
                <div class="space-y-4 mb-6">
                    <!-- Compact selected-testament status summary -->
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-gray-600 dark:text-gray-400"
                        role="group" aria-label="Book status counts">
                        <span class="inline-flex items-center gap-2">
                            <span aria-hidden="true" class="h-2 w-2 rounded-full bg-success-500"></span>
                            <span x-text="`${current.completed_books} completed`"></span>
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span aria-hidden="true" class="h-2 w-2 rounded-full bg-primary-500"></span>
                            <span x-text="`${current.in_progress_books} in progress`"></span>
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span aria-hidden="true" class="h-2 w-2 rounded-full bg-gray-400 dark:bg-gray-500"></span>
                            <span x-text="`${current.not_started_books} not started`"></span>
                        </span>
                    </div>
                </div>

                <!-- Books Grid -->
                <div class="book-completion-grid grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700" role="list">
                    <template x-for="book in current.processed_books" :key="book.name">
                        <div role="listitem" class="flex min-h-[5.25rem] flex-col justify-center gap-1 border-r border-b border-gray-200 p-2 text-center dark:border-gray-700"
                            :class="statusClasses(book.status)"
                            :title="bookDetailsLabel(book)"
                            :aria-label="bookDetailsLabel(book)">
                            <div class="relative flex min-h-8 w-full items-center justify-center">
                                <div class="w-full min-w-0 break-words px-8 text-center text-sm font-semibold leading-tight"
                                    x-text="book.name"></div>

                                <!-- The badge does not affect the centered title or tile height. -->
                                <template x-if="book.completed_count > 0">
                                    <span aria-hidden="true" class="absolute right-1 top-1/2 inline-flex h-6 min-w-6 -translate-y-1/2 items-center justify-center rounded-full bg-white px-1 text-[10px] font-bold leading-none text-gray-800 ring-1 ring-gray-300 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-500">
                                        <span x-text="book.completed_count > 1 ? `×${book.completed_count}` : book.completed_count"></span>
                                    </span>
                                </template>
                            </div>

                            <div class="text-sm leading-[1.5]"
                                x-text="book.completed_count > 0 ? `${book.next_completion_chapters}/${book.chapter_count} to next` : `${book.chapters_read}/${book.chapter_count} chapters`"></div>

                            <!-- Progress toward the next whole-book completion -->
                            <div class="h-1 w-full overflow-hidden rounded-full" :class="progressTrackClasses(book.status)">
                                <div class="h-1 rounded-full transition-[width] duration-300" :class="progressFillClasses(book.status)"
                                    :style="`width: ${book.completed_count > 0 ? book.next_completion_progress_percent : book.percentage}%`"></div>
                            </div>
                        </div>
                    </template>
                </div>

            </div>
        </div>
    </div>
</x-ui.card>

<script>
    /**
     * Book Progress Component - Client-side testament toggling.
     */
    function bookProgressComponent(oldData, newData, deuterocanonicalData = null, initialTestament = 'Old', overallData = {}) {
        const normalizeData = (data = {}) => ({
            processed_books: data.processed_books ?? [],
            completed_books: data.completed_books ?? 0,
            in_progress_books: data.in_progress_books ?? 0,
            not_started_books: data.not_started_books ?? 0,
        });

        const availableTestaments = deuterocanonicalData ? ['Old', 'Deuterocanonical', 'New'] : ['Old', 'New'];
        const testaments = {
            Old: normalizeData(oldData),
        };

        if (deuterocanonicalData) {
            testaments.Deuterocanonical = normalizeData(deuterocanonicalData);
        }

        testaments.New = normalizeData(newData);

        return {
            activeTestament: availableTestaments.includes(initialTestament) ? initialTestament : 'Old',
            overall: overallData,
            testaments,

            get current() {
                return this.testaments[this.activeTestament] ?? this.testaments.Old;
            },

            overallProgressPercent() {
                return Number(this.overall.bible_completions > 0
                    ? this.overall.next_completion_progress_percent
                    : this.overall.first_coverage_percent) || 0;
            },

            overallProgressLabel() {
                const currentRead = Number(this.overall.bible_completions) + 1;

                return currentRead === 1 ? 'First Bible read' : `${this.ordinal(currentRead)} Bible read`;
            },

            ordinal(number) {
                const lastTwoDigits = number % 100;
                const suffix = lastTwoDigits >= 11 && lastTwoDigits <= 13
                    ? 'th'
                    : ({ 1: 'st', 2: 'nd', 3: 'rd' }[number % 10] ?? 'th');

                return `${number}${suffix}`;
            },

            bookDetailsLabel(book) {
                if (book.completed_count > 0) {
                    return `${book.name}: completed ${book.completed_count} ${book.completed_count === 1 ? 'time' : 'times'}; ${book.next_completion_chapters}/${book.chapter_count} chapters toward the next completion`;
                }

                return `${book.name}: ${book.chapters_read}/${book.chapter_count} chapters read at least once (${book.percentage}%)`;
            },

            statusClasses(status) {
                switch (status) {
                    case 'completed':
                        return 'bg-green-50 text-green-900 dark:bg-green-950 dark:text-green-100';
                    case 'in-progress':
                        return 'bg-blue-50 text-blue-900 dark:bg-blue-950 dark:text-blue-100';
                    default:
                        return 'bg-white text-gray-700 dark:bg-gray-800 dark:text-gray-300';
                }
            },

            progressTrackClasses(status) {
                switch (status) {
                    case 'completed':
                        return 'bg-green-200 dark:bg-green-800';
                    case 'in-progress':
                        return 'bg-blue-200 dark:bg-blue-800';
                    default:
                        return 'bg-gray-200 dark:bg-gray-700';
                }
            },

            progressFillClasses(status) {
                switch (status) {
                    case 'completed':
                        return 'bg-green-600 dark:bg-green-300';
                    case 'in-progress':
                        return 'bg-blue-600 dark:bg-blue-300';
                    default:
                        return 'bg-gray-500 dark:bg-gray-400';
                }
            },

            setTestament(testament) {
                if (this.activeTestament === testament || !this.testaments[testament]) {
                    return;
                }

                this.activeTestament = testament;
            },
        };
    }
</script>
