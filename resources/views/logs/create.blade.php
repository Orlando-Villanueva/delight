@extends('layouts.authenticated')

@section('page-title', 'Log Reading')
@section('page-subtitle', 'Record your Bible reading progress')

@section('content')
    @fragment('page-content')
        <div class="flex-1">
            <div id="main-content">
                <div class="max-w-2xl mx-auto sm:px-20 lg:px-32">
                    @fragment('reading-form')
                        <div id="reading-log-form-container">
                            @php
                                $oldTestament = collect($books)->where('testament', 'old')->values();
                                $newTestament = collect($books)->where('testament', 'new')->values();

                                $initialTestament = 'old';
                                $oldBookId = old('book_id');

                                if ($oldBookId) {
                                    // Find which testament the old book ID belongs to
                                    if ($newTestament->firstWhere('id', $oldBookId)) {
                                        $initialTestament = 'new';
                                    }
                                }
                            @endphp

                            <form hx-post="{{ route('logs.store') }}" hx-target="#reading-log-form-container" hx-swap="outerHTML"
                                class="space-y-6" x-data="readingLogForm({
                                    initialTestament: '{{ $initialTestament }}',
                                    initialBookId: '{{ old('book_id') }}',
                                    books: {
                                        old: {{ $oldTestament->toJson() }},
                                        new: {{ $newTestament->toJson() }}
                                    }
                                })" x-init="init()">
                                @csrf

                                <!-- Date Selection: Today or Yesterday -->
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">When did you
                                        read?</label>
                                    <div class="space-y-3">
                                        <div class="flex items-center">
                                            <input type="radio" id="today" name="date_read"
                                                value="{{ today()->toDateString() }}"
                                                {{ old('date_read', today()->toDateString()) == today()->toDateString() ? 'checked' : '' }}
                                                class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                            <label for="today"
                                                class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                📖 Today ({{ today()->format('M d, Y') }})
                                            </label>
                                        </div>

                                        @if (isset($allowYesterday) && $allowYesterday)
                                            <div class="flex items-center">
                                                <input type="radio" id="yesterday" name="date_read"
                                                    value="{{ today()->subDay()->toDateString() }}"
                                                    {{ old('date_read') == today()->subDay()->toDateString() ? 'checked' : '' }}
                                                    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                                <label for="yesterday"
                                                    class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    📅 Yesterday ({{ today()->subDay()->format('M d, Y') }}) - <span
                                                        class="text-gray-500 dark:text-gray-400 italic">I forgot to log
                                                        it</span>
                                                </label>
                                            </div>
                                        @elseif(isset($allowYesterday))
                                            {{-- Show why yesterday is not available --}}
                                            <div class="flex items-center opacity-50">
                                                <input type="radio" disabled
                                                    class="h-4 w-4 text-gray-400 border-gray-300 dark:border-gray-600 cursor-not-allowed">
                                                <label
                                                    class="ml-3 block text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                                                    📅 Yesterday ({{ today()->subDay()->format('M d, Y') }}) -
                                                    @if (isset($hasReadYesterday) &&
                                                            !$hasReadYesterday &&
                                                            isset($currentStreak) &&
                                                            $currentStreak > 0 &&
                                                            isset($hasReadToday) &&
                                                            !$hasReadToday)
                                                        <span class="italic">Would break your
                                                            {{ $currentStreak }}-day streak</span>
                                                    @else
                                                        <span class="italic">Already logged</span>
                                                    @endif
                                                </label>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        💡 <strong>Grace Period:</strong> You can log today's reading or catch up on
                                        yesterday if you forgot.
                                    </div>
                                </div>

                                <!-- Bible Book Selection -->
                                <div class="space-y-2 max-w-md">
                                    <label class="form-label">
                                        📚 Bible Book
                                    </label>

                                    <div class="flex relative">
                                        <!-- Testament Dropdown Button -->
                                        <button id="testament-button" data-dropdown-toggle="testament-dropdown"
                                            data-dropdown-placement="bottom-start"
                                            class="shrink-0 inline-flex items-center py-2 px-4 text-sm font-medium text-center text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-s-lg hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 focus:z-10 dark:focus:ring-primary-600 shadow-sm z-10"
                                            type="button">
                                            <span x-text="testamentLabel"></span>
                                            <svg class="w-2.5 h-2.5 ms-2.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                                fill="none" viewBox="0 0 10 6">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="2" d="m1 1 4 4 4-4" />
                                            </svg>
                                        </button>

                                        <!-- Testament Dropdown Menu -->
                                        <div id="testament-dropdown"
                                            class="z-10 hidden bg-white dark:bg-gray-700 divide-y divide-gray-100 dark:divide-gray-600 rounded-lg shadow w-52">
                                            <ul class="py-2 text-sm text-gray-700 dark:text-gray-200"
                                                aria-labelledby="testament-button">
                                                <li>
                                                    <button type="button" @click="updateTestament('old')"
                                                        class="inline-flex w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">
                                                        <span class="inline-flex items-center">
                                                            📜 Old Testament
                                                        </span>
                                                    </button>
                                                </li>
                                                <li>
                                                    <button type="button" @click="updateTestament('new')"
                                                        class="inline-flex w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">
                                                        <span class="inline-flex items-center">
                                                            ✝️ New Testament
                                                        </span>
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>

                                        <!-- Book Select List -->
                                        <div class="flex-1 relative focus-within:z-20">
                                            <select name="book_id" required class="form-input rounded-s-none -ml-px w-full"
                                                aria-label="Select Bible book" x-model="selectedBook"
                                                @change="updateChapterPlaceholder($event.target.value)">
                                                <option value="">Select a book...</option>
                                                <template x-for="book in books[testament]" :key="book.id">
                                                    <option :value="book.id" x-text="book.name"
                                                        :selected="book.id == selectedBook"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>

                                    @if ($errors->first('book_id'))
                                        <p class="form-error" role="alert">
                                            {{ $errors->first('book_id') }}
                                        </p>
                                    @endif
                                </div>

                                <!-- Chapter Input -->
                                <div class="max-w-md">
                                    <div class="grid grid-cols-2 gap-4">
                                        <x-ui.input name="start_chapter" label="Start Chapter" inputmode="numeric" pattern="[0-9]*"
                                            x-bind:placeholder="startChapterPlaceholder" :value="old('start_chapter')" required />

                                        <x-ui.input name="end_chapter" label="End Chapter (Optional)" inputmode="numeric"
                                            pattern="[0-9]*" x-bind:placeholder="endChapterPlaceholder" :value="old('end_chapter')"
                                            :error="$errors->first('end_chapter')" />
                                    </div>

                                    @if ($errors->first('start_chapter'))
                                        <p class="form-error mt-2">{{ $errors->first('start_chapter') }}</p>
                                    @endif
                                </div>

                                <!-- Notes Section -->
                                <x-ui.textarea name="notes_text" label="Notes (Optional)"
                                    placeholder="Share any thoughts, insights, or questions from your reading..." :value="old('notes_text')"
                                    rows="4" maxlength="1000" :showCounter="true" :error="$errors->first('notes_text')" />

                                <!-- Form Actions -->
                                <div class="pt-6 border-t border-gray-200 dark:border-gray-600">
                                    <div class="flex items-start">
                                        <x-ui.button type="submit" variant="accent" size="lg" hx-indicator="#save-loading"
                                            class="w-full sm:w-auto px-6 py-3 text-base font-medium shadow-sm">
                                            <span id="save-loading" class="htmx-indicator hidden">
                                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                    </path>
                                                </svg>
                                                Saving...
                                            </span>
                                            <span class="htmx-indicator-hidden">Log Reading</span>
                                        </x-ui.button>
                                    </div>

                                    @if (isset($successMessage) || session('success'))
                                        <p class="form-success mt-4">✅ {{ $successMessage ?? session('success') }}</p>
                                    @endif
                                </div>
                            </form>

                            <style>
                                /* HTMX loading indicator styles */
                                .htmx-indicator {
                                    display: none;
                                }

                                .htmx-request .htmx-indicator {
                                    display: flex;
                                    pointer-events: all;
                                }

                                .htmx-request .htmx-indicator-hidden {
                                    display: none !important;
                                }
                            </style>

                            <script>
                                function readingLogForm(config) {
                                    return {
                                        testament: config.initialTestament,
                                        testamentLabel: config.initialTestament === 'old' ? '📜 Old Testament' : '✝️ New Testament',
                                        books: config.books,
                                        selectedBook: config.initialBookId,
                                        startChapterPlaceholder: 'e.g. 1',
                                        endChapterPlaceholder: 'e.g. 5',

                                        init() {
                                            this.updateChapterPlaceholder(this.selectedBook);
                                        },

                                        updateChapterPlaceholder(bookId) {
                                            if (!bookId) {
                                                this.startChapterPlaceholder = 'e.g. 1';
                                                this.endChapterPlaceholder = 'e.g. 5';
                                                return;
                                            }

                                            const allBooks = [...this.books.old, ...this.books.new];
                                            const book = allBooks.find(b => b.id == bookId);

                                            if (book) {
                                                this.startChapterPlaceholder = '1';
                                                this.endChapterPlaceholder = `${book.chapters}`;
                                            } else {
                                                this.startChapterPlaceholder = 'e.g. 1';
                                                this.endChapterPlaceholder = 'e.g. 5';
                                            }
                                        },

                                        updateTestament(newTestament) {
                                            this.testament = newTestament;
                                            this.testamentLabel = newTestament === 'old' ? '📜 Old Testament' : '✝️ New Testament';
                                            // Close the dropdown by simulating a click on the button that controls it
                                            document.getElementById('testament-button').click();
                                        }
                                    }
                                }
                            </script>
                        </div>
                    @endfragment
                </div>
            </div>
        </div>
    @endfragment
@endsection
