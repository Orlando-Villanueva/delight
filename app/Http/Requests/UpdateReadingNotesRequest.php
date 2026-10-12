<?php

namespace App\Http\Requests;

use App\Models\ReadingLog;
use App\Services\ReadingLogService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;

class UpdateReadingNotesRequest extends BaseUpdateReadingNoteRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['log_ids.*'][] = Rule::exists('reading_logs', 'id')
            ->where('user_id', $this->user()?->id);

        return $rules;
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
        $logIds = $this->input('log_ids', []);

        $allLogs = app(ReadingLogService::class)->getLogsForNoteForm(
            $user,
            $readingLog,
            is_array($logIds) ? $logIds : []
        );

        $response = response()
            ->view('components.modals.partials.edit-reading-note-form', [
                'log' => $readingLog,
                'modalId' => "edit-note-{$readingLog->id}",
                'dateKey' => $readingLog->date_read->format('Y-m-d'),
                'allLogs' => $allLogs,
                'notesText' => is_string($notesText) ? $notesText : '',
                'errors' => $errors,
            ], 422)
            ->header('HX-Retarget', "#edit-note-form-container-{$readingLog->id}")
            ->header('HX-Reswap', 'outerHTML');

        throw new HttpResponseException($response);
    }
}
