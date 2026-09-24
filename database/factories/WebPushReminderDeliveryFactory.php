<?php

namespace Database\Factories;

use App\Enums\ReadingReminderType;
use App\Models\User;
use App\Models\WebPushReminderDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebPushReminderDelivery>
 */
class WebPushReminderDeliveryFactory extends Factory
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
            'reminder_type' => ReadingReminderType::DailyReading->value,
            'reminder_date' => today()->toDateString(),
            'scheduled_for_at' => now(),
        ];
    }
}
