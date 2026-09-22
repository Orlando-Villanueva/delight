<?php

namespace Database\Factories;

use App\Enums\ReadingReminderType;
use App\Models\NativePushRegistration;
use App\Models\NativePushReminderDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NativePushReminderDelivery>
 */
class NativePushReminderDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'native_push_registration_id' => fn (): int => NativePushRegistration::factory()->create()->id,
            'reminder_type' => ReadingReminderType::DailyReading->value,
            'reminder_date' => now()->toDateString(),
            'scheduled_for_at' => now(),
            'token_hash' => fn (array $attributes): string => NativePushRegistration::query()
                ->findOrFail($attributes['native_push_registration_id'])->token_hash,
            'expo_retry_count' => 0,
            'expo_retry_at' => null,
        ];
    }
}
