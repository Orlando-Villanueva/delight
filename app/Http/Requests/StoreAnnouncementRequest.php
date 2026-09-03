<?php

namespace App\Http\Requests;

use App\Services\AnnouncementValidator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(AnnouncementValidator $announcementValidator): array
    {
        return $announcementValidator->rules();
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('starts_at')) {
            $this->merge(['starts_at' => now()]);
        }
    }
}
