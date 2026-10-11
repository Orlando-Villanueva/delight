<?php

namespace App\Http\Requests;

use App\Models\ReadingLog;
use App\Services\ReadingLogService;
use Illuminate\Foundation\Http\FormRequest;

abstract class BaseUpdateReadingNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $log = $this->route('readingLog');

        return $log instanceof ReadingLog && $this->user()?->id === $log->user_id;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'notes_text' => ['present', 'nullable', 'string', 'max:'.ReadingLogService::MAX_NOTE_LENGTH],
            'log_ids' => ['required', 'array', 'min:1', 'max:200'],
            'log_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }
}
