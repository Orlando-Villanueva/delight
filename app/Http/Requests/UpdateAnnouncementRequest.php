<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use App\Services\AnnouncementValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(AnnouncementValidator $announcementValidator): array
    {
        /** @var Announcement $announcement */
        $announcement = $this->route('announcement');

        return $announcementValidator->rules($announcement);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge(['slug' => Str::slug((string) $this->input('slug'))]);
        } elseif ($this->filled('title')) {
            $this->merge(['slug' => Str::slug((string) $this->input('title'))]);
        }

        if (! $this->filled('starts_at')) {
            $this->merge(['starts_at' => now()]);
        }
    }
}
