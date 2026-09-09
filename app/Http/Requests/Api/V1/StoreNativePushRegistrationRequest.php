<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

class StoreNativePushRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentAccessToken() instanceof PersonalAccessToken;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'expo_push_token' => [
                'bail', 'required', 'string', 'max:255',
                'regex:/\A(?:(?:Expo|Exponent)PushToken\[[A-Za-z0-9_-]+\]|[a-zA-Z0-9]{8}(?:-[a-zA-Z0-9]{4}){3}-[a-zA-Z0-9]{12})\z/',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['expo_push_token.regex' => 'The notification address must be a valid Expo push token.'];
    }
}
