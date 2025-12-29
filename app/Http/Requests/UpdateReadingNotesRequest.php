<?php

namespace App\Http\Requests;

use App\Models\ReadingLog;
use App\Services\ReadingLogService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;

class UpdateReadingNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $readingLog = $this->route('readingLog');

        if (! $readingLog instanceof ReadingLog) {
            return false;
        }

        return $this->user()?->id === $readingLog->user_id;
    }

    public function rules(): array
    {
        return [
            'notes_text' => ['nullable', 'string', 'max:1000'],
            'log_ids' => ['nullable', 'array'],
            'log_ids.*' => [
                'integer',
                Rule::exists('reading_logs', 'id')
                    ->where('user_id', $this->user()?->id),
            ],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $readingLog = $this->route('readingLog');
        $user = $this->user();

        if (! $readingLog instanceof ReadingLog || ! $user) {
            parent::failedValidation($validator);
        }

        $errors = new MessageBag($validator->errors()->toArray());
        $notesText = $this->input('notes_text', '');

        $allLogs = app(ReadingLogService::class)->getLogsForNoteUpdate(
            $user,
            $readingLog,
            $this->input('log_ids', [])
        );

        $response = response()
            ->view('components.modals.partials.edit-reading-note-form', [
                'log' => $readingLog,
                'modalId' => "edit-note-{$readingLog->id}",
                'dateKey' => $readingLog->date_read->format('Y-m-d'),
                'allLogs' => $allLogs,
                'notesText' => $notesText,
                'errors' => $errors,
            ], 422)
            ->header('HX-Retarget', "#edit-note-form-container-{$readingLog->id}")
            ->header('HX-Reswap', 'outerHTML');

        throw new HttpResponseException($response);
    }
}
