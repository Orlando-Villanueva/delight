@props(['log'])

@php
    $allLogs = $log->all_logs ?? collect([$log]);
    $isMultiChapter = $allLogs->count() > 1;
@endphp

{{-- Chapter Selection Modal (for multi-chapter ranges) --}}
<div id="delete-chapters-{{ $log->id }}" tabindex="-1" data-modal-backdrop="static"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full"
    x-data="{
        selectedChapters: [],
        toggleChapter(chapterId) {
            if (this.selectedChapters.includes(chapterId)) {
                this.selectedChapters = this.selectedChapters.filter(id => id !== chapterId);
            } else {
                this.selectedChapters.push(chapterId);
            }
        },
        selectAll() {
            this.selectedChapters = {{ $allLogs->pluck('id')->toJson() }};
        },
        deselectAll() {
            this.selectedChapters = [];
        },
        deleteSelected() {
            if (this.selectedChapters.length === 0) {
                alert('Please select at least one chapter to delete');
                return;
            }

            // Delete each selected chapter
            this.selectedChapters.forEach((chapterId, index) => {
                const url = `/logs/${chapterId}`;
                fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'HX-Request': 'true'
                    }
                }).then(response => {
                    // After last deletion, refresh the list
                    if (index === this.selectedChapters.length - 1) {
                        // Trigger HTMX refresh
                        htmx.trigger('#log-list', 'readingLogDeleted');
                        window.location.reload();
                    }
                });
            });
        }
    }">
    <div class="relative p-4 w-full max-w-md max-h-full">
        {{-- Modal content --}}
        <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
            {{-- Close button --}}
            <button type="button"
                class="absolute top-3 end-2.5 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                data-modal-hide="delete-chapters-{{ $log->id }}">
                <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                </svg>
                <span class="sr-only">Close modal</span>
            </button>

            {{-- Modal header --}}
            <div class="p-4 md:p-5 border-b rounded-t dark:border-gray-600">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Select Chapters to Delete
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $log->passage_text }} • {{ $log->date_read->format('M j, Y') }}
                </p>
            </div>

            {{-- Modal body --}}
            <div class="p-4 md:p-5 space-y-4">
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
                    @foreach($allLogs->sortBy('chapter') as $chapterLog)
                    <label class="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer">
                        <input type="checkbox"
                            x-model="selectedChapters"
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
            <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b dark:border-gray-600 gap-3">
                <button @click="deleteSelected()"
                    data-modal-hide="delete-chapters-{{ $log->id }}"
                    type="button"
                    class="text-white bg-red-600 hover:bg-red-800 focus:ring-4 focus:outline-none focus:ring-red-300 dark:focus:ring-red-800 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                    Delete Selected
                </button>
                <button data-modal-hide="delete-chapters-{{ $log->id }}"
                    type="button"
                    class="py-2.5 px-5 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
