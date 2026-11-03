<?php

namespace App\Http\Requests;

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
}
