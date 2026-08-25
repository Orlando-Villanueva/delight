<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoogleTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string', 'max:8192'],
            'device_name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_token.required' => 'A Google identity token is required.',
            'id_token.string' => 'The Google identity token must be a string.',
            'id_token.max' => 'The Google identity token is too large.',
            'device_name.required' => 'A device name is required.',
            'device_name.string' => 'The device name must be a string.',
            'device_name.max' => 'The device name may not be greater than 255 characters.',
            'password.string' => 'The password must be a string.',
        ];
    }
}
