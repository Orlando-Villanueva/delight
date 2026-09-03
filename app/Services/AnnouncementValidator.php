<?php

namespace App\Services;

use App\Models\Announcement;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AnnouncementValidator
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique(Announcement::class)],
            'content' => ['required', 'string'],
            'hero_image_path' => ['required', 'string', 'max:255'],
            'social_image_path' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        if (isset($input['slug']) && is_string($input['slug']) && filled($input['slug'])) {
            $input['slug'] = Str::slug($input['slug']);
        } elseif (isset($input['title']) && is_string($input['title'])) {
            $input['slug'] = Str::slug($input['title']);
        }

        if (! isset($input['starts_at']) || blank($input['starts_at'])) {
            $input['starts_at'] = now();
        }

        return Validator::make($input, $this->rules())->validate();
    }
}
