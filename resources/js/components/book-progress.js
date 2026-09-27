/**
 * Book progress state for switching the visible Bible testament.
 */
export function bookProgressComponent(oldData, newData, deuterocanonicalData = null, initialTestament = 'Old', overallData = {}) {
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
