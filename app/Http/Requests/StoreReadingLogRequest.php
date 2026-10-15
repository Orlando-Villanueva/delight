<?php

namespace App\Http\Requests;

use App\Services\BibleReferenceService;
use App\Services\ReadingCalendarService;
use App\Services\ReadingFormService;
use App\Services\ReadingLogService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\MessageBag;
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
        $errorScopes = [];
        if (isset($errors['chapter_range'])) {
            $errors['start_chapter'] = $errors['chapter_range'];
            unset($errors['chapter_range']);
            $errorScopes['start_chapter'] = 'chapter_range';
        }

        if ($this->wantsJson()) {
            return response()->json([
                'message' => ValidationException::withMessages($errors)->getMessage(),
                'errors' => $errors,
                'error_scopes' => $errorScopes,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $bibleReferenceService = $this->container->make(BibleReferenceService::class);
        $readingCalendar = $this->container->make(ReadingCalendarService::class);
        $readingFormService = $this->container->make(ReadingFormService::class);
        $yesterday = $readingCalendar->yesterdayFor($this->user())->toDateString();
        $data = array_merge([
            'books' => $bibleReferenceService->listBibleBooks(null, 'en', $this->user()->includesDeuterocanonicalBooks()),
            'errors' => new MessageBag($errors),
            'errorScopes' => $errorScopes,
            'selectedDateRead' => $this->input('date_read') === $yesterday
                ? $yesterday
                : $readingCalendar->todayFor($this->user())->toDateString(),
            'selectedBookId' => $this->scalarInputForForm('book_id'),
            'selectedStartChapter' => $this->scalarInputForForm('start_chapter'),
            'selectedEndChapter' => $this->scalarInputForForm('end_chapter'),
            'selectedNotesText' => $this->scalarInputForForm('notes_text'),
        ], $readingFormService->getFormContextData($this->user()));

        if ($this->header('HX-Request')) {
            return response(view('logs.create', $data)->fragment('reading-form'));
        }

        return response()->view('logs.create', $data);
    }

    private function scalarInputForForm(string $key): string
    {
        $value = $this->input($key, '');

        return is_scalar($value) ? (string) $value : '';
    }
}
