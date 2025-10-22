@props(['log'])

@php
// Determine if this is a multi-chapter entry
$allLogs = $log->all_logs ?? collect([$log]);
$isMultiChapter = $allLogs->count() > 1;
$modalId = $isMultiChapter ? "delete-chapters-{$log->id}" : "delete-confirmation-{$log->id}";
$editModalId = "edit-note-{$log->id}";
@endphp

<div class="group relative block p-4 bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow dark:bg-gray-800 dark:border-gray-700">
    {{-- Top Section: Passage + Time + Trash Icon --}}
    <div class="flex items-center justify-between gap-3">
        {{-- Passage and Time Info --}}
        <div class="flex-1 min-w-0">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white leading-tight mb-1">
                {{ $log->display_passage_text ?? $log->passage_text }}
            </h3>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Logged at {{ $log->created_at->format('g:i A') }}
            </div>
        </div>

        <div class="flex items-center gap-1">
            {{-- Edit Notes Icon --}}
            <button type="button"
                data-modal-target="{{ $editModalId }}"
                data-modal-toggle="{{ $editModalId }}"
                class="relative z-10 flex-shrink-0 p-2 text-gray-300 hover:text-primary-600 dark:text-gray-600 dark:hover:text-primary-400 transition-colors rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 cursor-pointer"
                title="{{ $log->notes_text ? 'Edit note' : 'Add note' }}"
                aria-label="{{ $log->notes_text ? 'Edit note' : 'Add note' }}">
                <svg class="w-5 h-5 pointer-events-none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.304 4.844 2.852 2.852M7 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-4.5m2.409-9.91a2.017 2.017 0 0 1 0 2.853l-6.844 6.844L8 14l.713-3.565 6.844-6.844a2.015 2.015 0 0 1 2.852 0Z"/>
                </svg>
            </button>

            {{-- Trash Icon - Vertically centered with passage/time info --}}
            <button type="button"
                data-modal-target="{{ $modalId }}"
                data-modal-toggle="{{ $modalId }}"
                class="relative z-10 flex-shrink-0 p-2 text-gray-300 hover:text-red-600 dark:text-gray-600 dark:hover:text-red-400 transition-colors rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 cursor-pointer"
                title="Delete reading"
                aria-label="Delete reading">
                <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </div>
    </div>

    {{-- Notes Section --}}
    @if($log->notes_text)
        <div class="border-t border-gray-100 dark:border-gray-700 pt-3 mt-3">
            <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap leading-relaxed italic">{{ $log->notes_text }}</p>
        </div>
    @endif
</div>
