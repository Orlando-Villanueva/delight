<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'include_deuterocanonical' => ['nullable', 'boolean'],
            'daily_reading_reminder_enabled' => ['nullable', 'boolean'],
            'streak_warning_enabled' => ['nullable', 'boolean'],
            'preferred_locale' => ['sometimes', 'required', 'string', Rule::in(array_keys(config('localization.supported')))],
            'reading_timezone' => ['sometimes', 'required', 'string', 'timezone:all_with_bc'],
        ];
    }
}
