<?php

namespace App\Http\Requests;

use App\Models\ReadingLog;
use Illuminate\Foundation\Http\FormRequest;

abstract class BaseDeleteReadingChaptersRequest extends FormRequest
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
            'group_ids' => ['required', 'array', 'min:1', 'max:200'],
            'group_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'log_ids' => ['required', 'array', 'min:1', 'max:200'],
            'log_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }
}
