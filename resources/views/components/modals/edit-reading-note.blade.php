@props(['log'])

@php
    $allLogs = $log->all_logs ?? collect([$log]);
    $modalId = "edit-note-{$log->id}";
    $dateKey = $log->date_read->format('Y-m-d');
    $notesText = $log->notes_text ?? '';
    $errors = new \Illuminate\Support\MessageBag();
@endphp

<div id="{{ $modalId }}" tabindex="-1" data-modal-backdrop="static" role="dialog" aria-modal="true"
    aria-labelledby="edit-note-title-{{ $log->id }}" aria-describedby="edit-note-desc-{{ $log->id }}"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-stack-modal justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-lg max-h-full">
        <div class="relative bg-gray-50 rounded-lg shadow dark:bg-[#2f3746]">
            <button type="button"
                class="absolute top-3 end-2.5 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                data-modal-hide="{{ $modalId }}">
                <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 14 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                </svg>
                <span class="sr-only">Close modal</span>
            </button>

            <div class="p-4 md:p-5 border-b border-gray-200/80 rounded-t-lg bg-white dark:bg-[#2f3746] dark:border-white/10">
                <h3 id="edit-note-title-{{ $log->id }}" class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $log->notes_text ? 'Edit note' : 'Add a note' }}
                </h3>
                <p id="edit-note-desc-{{ $log->id }}" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $log->display_passage_text ?? $log->passage_text }} • {{ $log->date_read->format('M j, Y') }}
                </p>
            </div>

            <div id="edit-note-form-container-{{ $log->id }}" class="p-4 md:p-5 rounded-b-lg bg-gray-50 dark:bg-[#2f3746]">
                @include('components.modals.partials.edit-reading-note-form', [
                    'log' => $log,
                    'modalId' => $modalId,
                    'dateKey' => $dateKey,
                    'allLogs' => $allLogs,
                    'notesText' => $notesText,
                    'errors' => $errors,
                ])
            </div>
        </div>
    </div>
</div>
