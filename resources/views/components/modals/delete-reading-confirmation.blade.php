@props(['log'])

{{-- Delete Confirmation Modal --}}
<div id="delete-confirmation-{{ $log->id }}" tabindex="-1" data-modal-backdrop="static" role="alertdialog"
    aria-modal="true" aria-labelledby="delete-title-{{ $log->id }}" aria-describedby="delete-desc-{{ $log->id }}"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-stack-modal justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-md max-h-full">
        {{-- Modal content --}}
        <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
            {{-- Close button --}}
            <button type="button"
                class="absolute top-3 end-2.5 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                data-modal-hide="delete-confirmation-{{ $log->id }}">
                <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 14 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                </svg>
                <span class="sr-only">Close modal</span>
            </button>

            {{-- Modal body --}}
            <div class="p-4 md:p-5 text-center">
                <svg class="mx-auto mb-4 text-red-600 dark:text-red-500 w-12 h-12" aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 11V6m0 8h.01M19 10a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ $log->display_passage_text ?? $log->passage_text }}
                </p>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-300">
                    {{ $log->date_read->format('M j, Y') }}
                </p>
                <h3 id="delete-title-{{ $log->id }}" class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">
                    Delete this reading?
                </h3>
                <p id="delete-desc-{{ $log->id }}" class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                    This action cannot be undone.
                </p>

                {{-- Action buttons --}}
                <div class="js-delete-actions-{{ $log->id }} flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
                    <button data-modal-hide="delete-confirmation-{{ $log->id }}" type="button" autofocus
                        class="w-full sm:w-auto py-2.5 px-5 text-sm font-medium text-gray-900 focus-visible:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus-visible:z-10 focus-visible:ring-4 focus-visible:ring-gray-100 dark:focus-visible:ring-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        Cancel
                    </button>
                    <button hx-delete="{{ route('logs.destroy', $log->id) }}"
                        hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'
                        hx-target="#reading-day-{{ $log->date_read->format('Y-m-d') }}" hx-swap="outerHTML"
                        hx-disabled-elt=".js-delete-actions-{{ $log->id }} button"
                        data-modal-hide="delete-confirmation-{{ $log->id }}" type="button"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-500 dark:hover:bg-red-500 disabled:bg-red-200 dark:disabled:bg-red-500/40 disabled:text-white/70 dark:disabled:text-white/60 focus-visible:ring-4 focus-visible:outline-none focus-visible:ring-red-300 dark:focus-visible:ring-red-700 rounded-lg">
                        Yes, delete it
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
