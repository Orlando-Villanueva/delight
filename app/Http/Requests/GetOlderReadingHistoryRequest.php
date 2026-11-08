<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetOlderReadingHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'before' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
