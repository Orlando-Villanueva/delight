@props(['timezone', 'options'])

<div
    class="mt-6 space-y-3 border-t border-gray-200 pt-6 dark:border-gray-700">
    <div class="flex items-center justify-between gap-4">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
            <label for="reading_timezone">Time zone</label>
        </h2>
    </div>
    <p id="reading-timezone-help" class="text-sm leading-6 text-gray-600 dark:text-gray-400">
        Used for your reading days, streaks, and reminders. Set automatically and stays the same when you travel. Offsets shown are current.
    </p>
    <select id="reading_timezone" name="reading_timezone" required
        aria-describedby="reading-timezone-help{{ $errors->has('reading_timezone') ? ' reading-timezone-error' : '' }}"
        @if ($errors->has('reading_timezone')) aria-invalid="true" @endif
        class="form-input rounded-lg pr-10 shadow-none">
        @foreach ($options as $value => $label)
            <option value="{{ $value }}" @selected(old('reading_timezone', $timezone) === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('reading_timezone')
        <p id="reading-timezone-error" class="text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
    @enderror
</div>
