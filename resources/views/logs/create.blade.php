@extends('layouts.authenticated')

@section('page-title', 'Log Reading')
@section('page-subtitle', 'Record today\'s Bible reading.')

@section('content')
    @fragment('page-content')
        <x-ui.page-shell width="form" id="main-content">
            <x-ui.page-header title="Log Reading" subtitle="Record today's Bible reading." />

            @fragment('reading-form')
                @php
                    $values = [
                        'book_id' => (string) ($selectedBookId ?? old('book_id', '')),
                        'start_chapter' => (string) ($selectedStartChapter ?? old('start_chapter', '')),
                        'end_chapter' => (string) ($selectedEndChapter ?? old('end_chapter', '')),
                        'date_read' => $selectedDateRead ?? $readingToday->toDateString(),
                        'notes_text' => (string) ($selectedNotesText ?? old('notes_text', '')),
                    ];
                    $hasRangeError = ($errorScopes['start_chapter'] ?? null) === 'chapter_range';
                    $messages = __('reading-log');
                    $props = [
                        'userId' => (string) auth()->id(),
                        'locale' => app()->getLocale(),
                        'submitUrl' => route('logs.store'),
                        'historyUrl' => route('logs.index'),
                        'achievementsUrl' => route('achievements.index'),
                        'achievementBadgeBaseUrl' => asset('images/achievements'),
                        'csrfToken' => csrf_token(),
                        'books' => array_values($books),
                        'recentBooks' => array_values($recentBooks ?? []),
                        'dates' => ['today' => $readingToday->toDateString(), 'yesterday' => $readingToday->subDay()->toDateString()],
                        'values' => $values,
                        'errors' => $errors->toArray(),
                        'errorScopes' => $errorScopes ?? [],
                        'successMessage' => $successMessage ?? session('success', ''),
                        'messages' => $messages,
                    ];
                @endphp
                <div id="reading-log-form-container" data-reading-log-island
                    data-props="{{ json_encode($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                    x-ignore hx-disable hx-history="false">
                    {{-- Native fallback remains usable while the Vue bundle loads or is unavailable. --}}
                    <form method="POST" action="{{ route('logs.store') }}" class="space-y-6">
                        @csrf
                        <fieldset class="space-y-2">
                            <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $messages['when'] }}</legend>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach (['today', 'yesterday'] as $day)
                                    <label class="form-label">
                                        <input type="radio" id="{{ $day }}" name="date_read" value="{{ $props['dates'][$day] }}" @checked($values['date_read'] === $props['dates'][$day])>
                                        {{ $messages[$day] }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $messages['date_help'] }}</p>
                            @if ($errors->first('date_read')) <p class="form-error" role="alert">{{ $errors->first('date_read') }}</p> @endif
                        </fieldset>
                        <div>
                            <label for="book_id" class="form-label">{{ $messages['book'] }}</label>
                            <select id="book_id" name="book_id" required class="form-input" aria-label="{{ $messages['book_label'] }}">
                                <option value="">{{ $messages['select_book'] }}</option>
                                @foreach (collect($books)->groupBy('testament') as $collection => $collectionBooks)
                                    <optgroup label="{{ $messages['collections'][$collection] }}">
                                        @foreach ($collectionBooks as $book)
                                            <option value="{{ $book['id'] }}" @selected((string) $book['id'] === $values['book_id'])>{{ $book['name'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @if ($errors->first('book_id')) <p class="form-error" role="alert">{{ $errors->first('book_id') }}</p> @endif
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <x-ui.input name="start_chapter" :label="$messages['start']" inputmode="numeric" pattern="[0-9]*" :value="$values['start_chapter']" :error="$hasRangeError ? null : $errors->first('start_chapter')" :aria-invalid="$hasRangeError ? 'true' : null" :aria-describedby="$hasRangeError ? 'chapter-range-error' : null" required />
                            <x-ui.input name="end_chapter" :label="$messages['end']" inputmode="numeric" pattern="[0-9]*" :value="$values['end_chapter']" :error="$errors->first('end_chapter')" :aria-invalid="$hasRangeError ? 'true' : null" :aria-describedby="$hasRangeError ? 'chapter-range-error' : null" />
                        </div>
                        @if ($hasRangeError)
                            <p id="chapter-range-error" class="form-error" role="alert">{{ $errors->first('start_chapter') }}</p>
                        @endif
                        <x-ui.textarea name="notes_text" :label="$messages['note']" :value="$values['notes_text']" rows="3" maxlength="1000" :error="$errors->first('notes_text')" />
                        <div class="pt-0">
                            <x-ui.button type="submit" variant="accent" size="lg" class="w-full px-6 py-3 text-base font-medium shadow-sm sm:w-auto">{{ $messages['save'] }}</x-ui.button>
                            @if ($props['successMessage']) <p class="form-success mt-4">{{ $props['successMessage'] }}</p> @endif
                        </div>
                    </form>
                </div>
            @endfragment
        </x-ui.page-shell>
    @endfragment
@endsection
