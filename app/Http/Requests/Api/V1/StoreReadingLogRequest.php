<?php

namespace App\Http\Requests\Api\V1;

use App\Services\BibleReferenceService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReadingLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(BibleReferenceService $bibleReferenceService): array
    {
        $allowedBookIds = collect($bibleReferenceService->listBibleBooks(
            includeDeuterocanonical: $this->user()->includesDeuterocanonicalBooks()
        ))->pluck('id')->all();

        return [
            'book_id' => ['required', 'integer', Rule::in($allowedBookIds)],
            'start_chapter' => ['required', 'integer', 'min:1'],
            'end_chapter' => ['nullable', 'integer', 'min:1'],
            'date_read' => [
                'required',
                'date_format:Y-m-d',
                Rule::in([today()->toDateString(), today()->subDay()->toDateString()]),
            ],
            'notes_text' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get the validation error messages for the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'book_id.in' => 'The selected book is not available for your Bible canon.',
            'start_chapter.min' => 'The start chapter must be at least 1.',
            'end_chapter.min' => 'The end chapter must be at least 1.',
            'date_read.in' => 'The reading date must be today or yesterday.',
            'notes_text.max' => 'The notes may not be greater than 1,000 characters.',
        ];
    }

    /**
     * Perform chapter validation after the base field rules pass.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['book_id', 'start_chapter', 'end_chapter'])) {
                    return;
                }

                $bookId = (int) $this->input('book_id');
                $startChapter = (int) $this->input('start_chapter');
                $endChapter = $this->filled('end_chapter')
                    ? (int) $this->input('end_chapter')
                    : $startChapter;
                $includeDeuterocanonical = $this->user()->includesDeuterocanonicalBooks();
                $bibleReferenceService = $this->container->make(BibleReferenceService::class);

                if ($startChapter > $endChapter) {
                    $validator->errors()->add('end_chapter', 'The end chapter must be greater than or equal to the start chapter.');

                    return;
                }

                if (! $bibleReferenceService->validateChapterNumber($bookId, $startChapter, $includeDeuterocanonical)) {
                    $validator->errors()->add('start_chapter', 'The start chapter is invalid for the selected book.');
                }

                if (! $bibleReferenceService->validateChapterNumber($bookId, $endChapter, $includeDeuterocanonical)) {
                    $validator->errors()->add('end_chapter', 'The end chapter is invalid for the selected book.');
                }
            },
        ];
    }
}
