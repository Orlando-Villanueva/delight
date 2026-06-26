@extends('layouts.authenticated')

@section('page-title', 'Log Reading')
@section('page-subtitle', 'Record today\'s Bible reading.')

@section('content')
    @fragment('page-content')
        <x-ui.page-shell width="form" id="main-content">
            <x-ui.page-header
                title="Log Reading"
                subtitle="Record today's Bible reading."
            />

            @fragment('reading-form')
                <div id="reading-log-form-container">
                    @php
                        $oldTestament = collect($books)->where('testament', 'old')->values();
                        $newTestament = collect($books)->where('testament', 'new')->values();
                        $deuterocanonicalBooks = collect($books)->where('testament', 'deuterocanonical')->values();
                        $recentBooks = collect($recentBooks ?? [])->values();

                        $initialTestament = 'old';
                        $selectedBookId = (string) ($selectedBookId ?? old('book_id', ''));
                        $selectedStartChapter = (string) ($selectedStartChapter ?? old('start_chapter', ''));
                        $selectedEndChapter = (string) ($selectedEndChapter ?? old('end_chapter', ''));
                        $selectedNotesText = (string) ($selectedNotesText ?? old('notes_text', ''));

                        if ($selectedBookId !== '') {
                            if ($newTestament->firstWhere('id', (int) $selectedBookId)) {
                                $initialTestament = 'new';
                            } elseif ($deuterocanonicalBooks->firstWhere('id', (int) $selectedBookId)) {
                                $initialTestament = 'deuterocanonical';
                            }
                        }

                        $notesExpanded = filled($selectedNotesText) || (bool) $errors->first('notes_text');
                    @endphp

                    <form
                        hx-post="{{ route('logs.store') }}"
                        hx-target="#reading-log-form-container"
                        hx-swap="outerHTML"
                        class="space-y-6"
                        x-data="readingLogForm({
                            initialTestament: @js($initialTestament),
                            initialBookId: @js($selectedBookId),
                            initialNotesOpen: @js($notesExpanded),
                            recentBooks: @js($recentBooks),
                            books: {
                                old: @js($oldTestament),
                                new: @js($newTestament),
                                deuterocanonical: @js($deuterocanonicalBooks)
                            }
                        })"
                        x-init="init()"
                    >
                        @csrf
                        @php
                            $selectedDateRead = $selectedDateRead ?? today()->toDateString();
                        @endphp

                        <fieldset class="space-y-2">
                            <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">When did you read?</legend>

                            <div
                                data-date-read-segmented-control
                                class="grid grid-cols-2 overflow-hidden rounded-lg border border-gray-300 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-700"
                            >
                                <div class="flex items-center">
                                    <input
                                        type="radio"
                                        id="today"
                                        name="date_read"
                                        value="{{ today()->toDateString() }}"
                                        {{ $selectedDateRead === today()->toDateString() ? 'checked' : '' }}
                                        class="peer sr-only"
                                    >
                                    <label
                                        for="today"
                                        class="flex w-full cursor-pointer items-center justify-center bg-transparent px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 peer-checked:bg-primary-50 peer-checked:text-primary-700 peer-checked:hover:bg-primary-50 peer-focus-visible:relative peer-focus-visible:z-10 peer-focus-visible:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-inset dark:text-gray-200 dark:hover:bg-gray-600 dark:peer-checked:bg-primary-900/30 dark:peer-checked:text-primary-300 dark:peer-checked:hover:bg-primary-900/30"
                                    >
                                        Today
                                    </label>
                                </div>

                                <div class="flex items-center border-l border-gray-300 dark:border-gray-600">
                                    <input
                                        type="radio"
                                        id="yesterday"
                                        name="date_read"
                                        value="{{ today()->subDay()->toDateString() }}"
                                        {{ $selectedDateRead === today()->subDay()->toDateString() ? 'checked' : '' }}
                                        class="peer sr-only"
                                    >
                                    <label
                                        for="yesterday"
                                        class="flex w-full cursor-pointer items-center justify-center bg-transparent px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 peer-checked:bg-primary-50 peer-checked:text-primary-700 peer-checked:hover:bg-primary-50 peer-focus-visible:relative peer-focus-visible:z-10 peer-focus-visible:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-inset dark:text-gray-200 dark:hover:bg-gray-600 dark:peer-checked:bg-primary-900/30 dark:peer-checked:text-primary-300 dark:peer-checked:hover:bg-primary-900/30"
                                    >
                                        Yesterday
                                    </label>
                                </div>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400">Forgot to log? Choose yesterday.</p>
                        </fieldset>

                        <div class="space-y-3">
                            <label for="book_id" class="form-label">
                                Bible book
                            </label>

                            <div class="relative flex">
                                <button
                                    id="testament-button"
                                    data-dropdown-toggle="testament-dropdown"
                                    data-dropdown-placement="bottom-start"
                                    class="z-10 inline-flex shrink-0 items-center rounded-s-lg border border-gray-300 bg-white px-4 py-2 text-center text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:z-10 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 dark:focus:ring-primary-600"
                                    type="button"
                                >
                                    <span x-text="testamentLabel"></span>
                                    <svg class="ms-2.5 h-2.5 w-2.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 10 6">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="2" d="m1 1 4 4 4-4" />
                                    </svg>
                                </button>

                                <div
                                    id="testament-dropdown"
                                    class="z-10 hidden w-52 divide-y divide-gray-100 rounded-lg bg-white shadow dark:divide-gray-600 dark:bg-gray-700"
                                >
                                    <ul class="py-2 text-sm text-gray-700 dark:text-gray-200" aria-labelledby="testament-button">
                                        <li>
                                            <button
                                                type="button"
                                                @click="updateTestament('old')"
                                                class="inline-flex w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-600"
                                            >
                                                <span class="inline-flex items-center">
                                                    📜 Old Testament
                                                </span>
                                            </button>
                                        </li>
                                        @if ($deuterocanonicalBooks->isNotEmpty())
                                            <li>
                                                <button
                                                    type="button"
                                                    @click="updateTestament('deuterocanonical')"
                                                    class="inline-flex w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-600"
                                                >
                                                    <span class="inline-flex items-center">
                                                        📖 Deuterocanonical
                                                    </span>
                                                </button>
                                            </li>
                                        @endif
                                        <li>
                                            <button
                                                type="button"
                                                @click="updateTestament('new')"
                                                class="inline-flex w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-600"
                                            >
                                                <span class="inline-flex items-center">
                                                    ✝️ New Testament
                                                </span>
                                            </button>
                                        </li>
                                    </ul>
                                </div>

                                <div class="relative flex-1 focus-within:z-20">
                                    <select
                                        id="book_id"
                                        name="book_id"
                                        required
                                        class="form-input -ml-px w-full rounded-s-none"
                                        aria-label="Select Bible book"
                                        x-model="selectedBook"
                                        @change="updateChapterPlaceholder($event.target.value)"
                                    >
                                        <option value="">Select a book...</option>
                                        <template x-for="book in books[testament]" :key="book.id">
                                            <option :value="book.id" x-text="book.name" :selected="book.id == selectedBook"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>

                            @if ($recentBooks->isNotEmpty())
                                <div data-recent-books class="flex max-w-full items-center gap-2 overflow-x-auto pb-1 [-webkit-overflow-scrolling:touch] ![scrollbar-width:none] [&::-webkit-scrollbar]:!hidden">
                                    <p id="recent-books-label" class="shrink-0 text-xs font-medium text-gray-500 dark:text-gray-400">
                                        Recent
                                    </p>

                                    <div class="flex min-w-0 flex-nowrap gap-2" role="group" aria-labelledby="recent-books-label">
                                        @foreach ($recentBooks as $recentBook)
                                            @php
                                                $spineClass = match ($recentBook['testament']) {
                                                    'new' => 'bg-primary-500 dark:bg-primary-400',
                                                    'deuterocanonical' => 'bg-purple-500 dark:bg-purple-400',
                                                    default => 'bg-accent-500 dark:bg-accent-400',
                                                };
                                            @endphp

                                            <button
                                                type="button"
                                                data-recent-book-suggestion="{{ $recentBook['id'] }}"
                                                @click="selectRecentBook(@js($recentBook))"
                                                x-bind:aria-pressed="selectedBook == '{{ $recentBook['id'] }}'"
                                                x-bind:class="selectedBook == '{{ $recentBook['id'] }}'
                                                    ? 'border-primary-500 bg-primary-50 text-primary-800 ring-2 ring-primary-500/25 dark:border-primary-400 dark:bg-primary-900/30 dark:text-primary-100'
                                                    : 'border-gray-200 bg-gray-50 text-gray-700 hover:border-gray-300 hover:bg-gray-100 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-100'"
                                                class="inline-flex min-h-9 max-w-[11rem] shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-left text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-gray-900"
                                            >
                                                <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full {{ $spineClass }}"></span>
                                                <span class="truncate">{{ $recentBook['name'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($errors->first('book_id'))
                                <p class="form-error" role="alert">
                                    {{ $errors->first('book_id') }}
                                </p>
                            @endif
                        </div>

                        <div>
                            <div class="grid grid-cols-2 gap-4">
                                <x-ui.input name="start_chapter" label="Start Chapter" inputmode="numeric" pattern="[0-9]*"
                                    x-bind:placeholder="startChapterPlaceholder" :value="$selectedStartChapter" required />

                                <x-ui.input name="end_chapter" label="End Chapter (Optional)" inputmode="numeric"
                                    pattern="[0-9]*" x-bind:placeholder="endChapterPlaceholder" :value="$selectedEndChapter"
                                    :error="$errors->first('end_chapter')" />
                            </div>

                            <p x-show="chapterNote" x-text="chapterNote" x-cloak class="mt-2 text-sm text-gray-500 dark:text-gray-400"></p>

                            @if ($errors->first('start_chapter'))
                                <p class="form-error mt-2">{{ $errors->first('start_chapter') }}</p>
                            @endif
                        </div>

                        <div class="space-y-3">
                            <button
                                type="button"
                                x-show="! notesOpen"
                                @click="notesOpen = true"
                                class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 transition hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                            >
                                <span aria-hidden="true">+</span>
                                Add a note or reflection
                            </button>

                            <div x-show="notesOpen" x-cloak>
                                <x-ui.textarea name="notes_text" label="Note or reflection"
                                    placeholder="Share any thoughts, insights, or questions from your reading..." :value="$selectedNotesText"
                                    rows="3" maxlength="1000" :showCounter="true" :error="$errors->first('notes_text')" />
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-6 dark:border-gray-600">
                            <div class="flex items-start">
                                <x-ui.button type="submit" variant="accent" size="lg" hx-indicator="#save-loading"
                                    class="w-full px-6 py-3 text-base font-medium shadow-sm sm:w-auto">
                                    <span id="save-loading" class="htmx-indicator hidden">
                                        <svg class="-ml-1 mr-3 h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg"
                                            fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
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
                                testamentLabel: testamentLabelFor(config.initialTestament),
                                books: config.books,
                                recentBooks: config.recentBooks,
                                selectedBook: config.initialBookId,
                                graceHelpOpen: false,
                                notesOpen: config.initialNotesOpen,
                                startChapterPlaceholder: 'e.g. 1',
                                endChapterPlaceholder: 'e.g. 5',
                                chapterNote: '',

                                init() {
                                    this.updateChapterPlaceholder(this.selectedBook);
                                },

                                updateChapterPlaceholder(bookId) {
                                    if (!bookId) {
                                        this.startChapterPlaceholder = 'e.g. 1';
                                        this.endChapterPlaceholder = 'e.g. 5';
                                        this.chapterNote = '';
                                        return;
                                    }

                                    const allBooks = [...this.books.old, ...this.books.new, ...this.books.deuterocanonical];
                                    const book = allBooks.find(b => b.id == bookId);

                                    if (book) {
                                        this.startChapterPlaceholder = '1';
                                        this.endChapterPlaceholder = `${book.chapters}`;
                                        this.chapterNote = chapterNoteFor(book);
                                    } else {
                                        this.startChapterPlaceholder = 'e.g. 1';
                                        this.endChapterPlaceholder = 'e.g. 5';
                                        this.chapterNote = '';
                                    }
                                },

                                selectRecentBook(book) {
                                    this.testament = book.testament;
                                    this.testamentLabel = testamentLabelFor(book.testament);
                                    this.selectedBook = String(book.id);
                                    this.updateChapterPlaceholder(book.id);
                                },

                                updateTestament(newTestament) {
                                    this.testament = newTestament;
                                    this.testamentLabel = testamentLabelFor(newTestament);
                                    document.getElementById('testament-button').click();
                                }
                            }
                        }

                        function testamentLabelFor(testament) {
                            if (testament === 'deuterocanonical') {
                                return '📖 Deuterocanonical';
                            }

                            return testament === 'old' ? '📜 Old Testament' : '✝️ New Testament';
                        }

                        function chapterNoteFor(book) {
                            if (book.id == 17 && book.chapters == 16) {
                                return 'Esther 11-16 are the Greek additions in Vulgate/Douay-Rheims-style numbering.';
                            }

                            if (book.id == 27 && book.chapters == 14) {
                                return 'Daniel 3 includes the Prayer of Azariah and Song of the Three Young Men; Daniel 13 is Susanna; Daniel 14 is Bel and the Dragon.';
                            }

                            return '';
                        }
                    </script>
                </div>
            @endfragment
        </x-ui.page-shell>
    @endfragment
@endsection
