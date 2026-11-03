<?php

namespace App\Http\Requests;

use App\Services\BibleReferenceService;
use App\Services\ReadingCalendarService;
use App\Services\ReadingLogService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class StoreReadingLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(BibleReferenceService $bibleReferenceService, ReadingCalendarService $readingCalendar): array
    {
        $today = $readingCalendar->todayFor($this->user())->toDateString();
        $yesterday = $readingCalendar->yesterdayFor($this->user())->toDateString();
        $allowedBookIds = collect($bibleReferenceService->listBibleBooks(
            includeDeuterocanonical: $this->user()->includesDeuterocanonicalBooks()
        ))->pluck('id')->all();

        return [
            'book_id' => ['required', 'integer', Rule::in($allowedBookIds)],
            'start_chapter' => 'required|integer|min:1',
            'end_chapter' => 'nullable|integer|min:1',
            'date_read' => "required|date|in:{$today},{$yesterday}",
            'notes_text' => 'nullable|string|max:'.ReadingLogService::MAX_NOTE_LENGTH,
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $start = (int) $this->input('start_chapter');
                $end = $this->input('end_chapter') !== null ? (int) $this->input('end_chapter') : $start;
                $bibleReferenceService = $this->container->make(BibleReferenceService::class);

                if ($start > $end || ! $bibleReferenceService->validateChapterRange(
                    $this->input('book_id'), $start, $end, $this->user()->includesDeuterocanonicalBooks()
                )) {
                    $validator->errors()->add('chapter_range', 'Invalid chapter range for the selected book.');
                }
            },
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException($this->validationResponse($validator->errors()->toArray()));
    }

    /** @param array<string, array<int, string>> $errors */
    public function validationResponse(array $errors): Response
    {
        $readingCalendar = $this->container->make(ReadingCalendarService::class);
        $errorScopes = [];
        if (isset($errors['chapter_range'])) {
            $errors['start_chapter'] = $errors['chapter_range'];
            unset($errors['chapter_range']);
            $errorScopes['start_chapter'] = 'chapter_range';
        }

        if ($this->wantsJson()) {
            $today = $readingCalendar->todayFor($this->user())->toDateString();
            $yesterday = $readingCalendar->yesterdayFor($this->user())->toDateString();

            return response()->json([
                'message' => ValidationException::withMessages($errors)->getMessage(),
                'errors' => $errors,
                'error_scopes' => $errorScopes,
                'form' => [
                    'dates' => ['today' => $today, 'yesterday' => $yesterday],
                    'values' => [
                        'date_read' => $this->input('date_read') === $yesterday ? $yesterday : $today,
                    ],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return redirect()->route('logs.create')->withErrors($errors);
    }
}
