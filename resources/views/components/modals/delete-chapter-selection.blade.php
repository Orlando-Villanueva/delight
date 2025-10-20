@props(['log'])

@php
    $allLogs = $log->all_logs ?? collect([$log]);
    $isMultiChapter = $allLogs->count() > 1;
@endphp

{{-- Chapter Selection Modal (for multi-chapter ranges) --}}
<div id="delete-chapters-{{ $log->id }}" tabindex="-1" data-modal-backdrop="static" role="alertdialog"
    aria-modal="true" aria-labelledby="delete-chapters-title-{{ $log->id }}"
    aria-describedby="delete-chapters-desc-{{ $log->id }}"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-stack-modal justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full"
    x-data="{
        allChapterIds: {{ $allLogs->pluck('id')->map(fn($id) => (string) $id)->toJson() }},
        selectedChapters: [],
        selectAll() {
            this.selectedChapters = [...this.allChapterIds];
        },
        deselectAll() {
            this.selectedChapters = [];
        },
        buttonLabel() {
            if (this.selectedChapters.length === 0) {
                return 'Delete Selected';
            }
    
            const suffix = this.selectedChapters.length === 1 ? 'Chapter' : 'Chapters';
    
            return `Delete ${this.selectedChapters.length} ${suffix}`;
        }
    }" x-on:htmx:afterOnLoad="this.selectedChapters = []">
    <div class="relative p-4 w-full max-w-md max-h-full">
        {{-- Modal content --}}
        <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
            {{-- Close button --}}
            <button type="button"
                class="absolute top-3 end-2.5 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                data-modal-hide="delete-chapters-{{ $log->id }}" @click="deselectAll()">
                <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 14 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                </svg>
                <span class="sr-only">Close modal</span>
            </button>

            <form class="flex flex-col h-full" hx-delete="{{ route('logs.batchDestroy') }}"
                hx-target="#reading-day-{{ $log->date_read->format('Y-m-d') }}" hx-swap="outerHTML"
                hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'
                hx-disabled-elt=".js-delete-actions-{{ $log->id }} button">
                {{-- Modal header --}}
                <div class="p-4 md:p-5 border-b rounded-t dark:border-gray-600">
                    <h3 id="delete-chapters-title-{{ $log->id }}"
                        class="text-lg font-semibold text-gray-900 dark:text-white">
                        Select Chapters to Delete
                    </h3>
                    <p id="delete-chapters-desc-{{ $log->id }}"
                        class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ $log->display_passage_text ?? $log->passage_text }} • {{ $log->date_read->format('M j, Y') }}
                    </p>
                </div>

                {{-- Modal body --}}
                <div class="p-4 md:p-5 space-y-4 flex-1">
                    {{-- Select All / Deselect All buttons --}}
                    <div class="flex gap-2 mb-3">
                        <button type="button" @click="selectAll()"
                            class="text-xs text-blue-600 hover:underline dark:text-blue-400">
                            Select All
                        </button>
                        <span class="text-gray-300">|</span>
                        <button type="button" @click="deselectAll()"
                            class="text-xs text-blue-600 hover:underline dark:text-blue-400">
                            Deselect All
                        </button>
                    </div>

                    {{-- Chapter checkboxes --}}
                    <div class="space-y-2 max-h-60 overflow-y-auto">
                        @foreach ($allLogs->sortBy('chapter') as $chapterLog)
                            <label
                                class="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer">
                                <input type="checkbox" name="ids[]" x-model="selectedChapters"
                                    value="{{ $chapterLog->id }}"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:focus:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <span class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300">
                                    Chapter {{ $chapterLog->chapter }}
                                </span>
                            </label>
                        @endforeach
                    </div>

                    {{-- Selected count --}}
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-3" x-show="selectedChapters.length > 0">
                        <span x-text="selectedChapters.length"></span> chapter(s) selected
                    </div>
                </div>

                {{-- Modal footer --}}
                <div
                    class="js-delete-actions-{{ $log->id }} flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end p-4 md:p-5 border-t border-gray-200 rounded-b dark:border-gray-600">
                    <button data-modal-hide="delete-chapters-{{ $log->id }}" type="button" @click="deselectAll()"
                        autofocus
                        class="w-full sm:w-auto py-2.5 px-5 text-sm font-medium text-gray-900 focus-visible:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus-visible:z-10 focus-visible:ring-4 focus-visible:ring-gray-100 dark:focus-visible:ring-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        Cancel
                    </button>
                    <button type="submit" data-modal-hide="delete-chapters-{{ $log->id }}"
                        :disabled="selectedChapters.length === 0"
                        class="w-full sm:w-auto inline-flex items-center justify-center text-white bg-red-600 hover:bg-red-500 dark:hover:bg-red-500 disabled:bg-red-200 dark:disabled:bg-red-500/40 disabled:text-white/70 dark:disabled:text-white/60 disabled:cursor-not-allowed focus-visible:ring-4 focus-visible:outline-none focus-visible:ring-red-300 dark:focus-visible:ring-red-700 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        <span x-text="buttonLabel()"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
