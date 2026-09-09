<?php

namespace Database\Factories;

use App\Models\NativePushRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NativePushRegistration> */
class NativePushRegistrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'personal_access_token_id' => fn (): int => User::factory()->create()
                ->createToken('Android', ['mobile'])->accessToken->id,
            'expo_push_token' => 'ExpoPushToken['.fake()->uuid().']',
            'token_hash' => fn (array $attributes): string => hash('sha256', $attributes['expo_push_token']),
        ];
    }
}
