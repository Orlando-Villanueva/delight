<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Late Logging Grace: Only allow today or yesterday
        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();

        return [
            'book_id' => 'required|integer|min:1|max:66',
            'start_chapter' => 'required|integer|min:1',
            'end_chapter' => 'nullable|integer|min:1',
            'date_read' => "required|date|in:{$today},{$yesterday}",
            'notes_text' => 'nullable|string|max:1000',
        ];
    }
}
