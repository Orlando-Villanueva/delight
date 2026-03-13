<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChurnRecoveryCampaign>
 */
class ChurnRecoveryCampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-3 days', 'now');
        $observedUntil = (clone $startedAt)->modify('+7 days');

        return [
            'user_id' => User::factory(),
            'campaign_key' => 'inactive_30_60_followup',
            'cohort' => 'inactive_30_60_days',
            'variant' => fake()->randomElement(['current_flow_control', 'two_touch_followup']),
            'started_at' => $startedAt,
            'observed_until' => $observedUntil,
            'reactivated_at' => null,
            'completed_at' => null,
            'last_touch_sent_at' => null,
        ];
    }
}
