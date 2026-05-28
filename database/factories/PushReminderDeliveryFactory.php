<?php

namespace Database\Factories;

use App\Models\PushReminderDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PushReminderDelivery>
 */
class PushReminderDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reminder_type' => PushReminderDelivery::TYPE_DAILY_READING,
            'reminder_date' => today()->toDateString(),
            'scheduled_for_at' => now(),
        ];
    }
}
