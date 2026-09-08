<?php

namespace Database\Factories;

use App\Models\NativeReminderPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NativeReminderPreference> */
class NativeReminderPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'enabled' => false,
            'timezone' => 'America/Toronto',
        ];
    }
}
