@props([
    'log',
    'modalId',
    'dateKey',
    'allLogs',
    'notesText' => '',
    'errors' => null,
])

@php
    $errorBag = $errors instanceof \Illuminate\Support\MessageBag
        ? $errors
        : new \Illuminate\Support\MessageBag();
    $notesValue = $notesText ?? '';
@endphp

<form hx-post="{{ route('logs.notes.update', $log->id) }}"
    hx-target="#reading-day-{{ $dateKey }}"
    hx-swap="outerHTML"
    hx-disabled-elt="#edit-note-actions-{{ $log->id }} button"
    hx-on::after-request="if (event.detail.successful) { document.body.dispatchEvent(new CustomEvent('hideModal', { detail: { id: '{{ $modalId }}' } })); }"
    class="space-y-4">
    @csrf
    @method('patch')

    @foreach ($allLogs as $chapterLog)
        <input type="hidden" name="log_ids[]" value="{{ $chapterLog->id }}">
    @endforeach

    <x-ui.textarea name="notes_text" label="Reading note" placeholder="Add any reflections, insights, or questions..."
        :value="$notesValue" rows="5" maxlength="1000" :showCounter="true"
        :error="$errorBag->first('notes_text')" />

    @if($allLogs->count() > 1)
        <p class="text-xs text-gray-500 dark:text-gray-400">
            This note will be applied to all {{ $allLogs->count() }} chapters in this reading session.
        </p>
    @endif

    <div id="edit-note-actions-{{ $log->id }}" class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <button type="button" data-modal-hide="{{ $modalId }}"
            class="w-full sm:w-auto py-2.5 px-5 text-sm font-medium text-gray-900 focus-visible:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus-visible:z-10 focus-visible:ring-4 focus-visible:ring-gray-100 dark:focus-visible:ring-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
            Cancel
        </button>
        <button type="submit"
            class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400 focus-visible:ring-4 focus-visible:outline-none focus-visible:ring-primary-300 dark:focus-visible:ring-primary-700 rounded-lg">
            Save note
        </button>
    </div>
</form>
