<?php

namespace Database\Factories;

use App\Enums\OnboardingStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OnboardingStepEvent>
 */
class OnboardingStepEventFactory extends Factory
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
            'step' => fake()->randomElement(array_map(
                fn (OnboardingStep $step) => $step->value,
                OnboardingStep::cases()
            )),
            'occurred_at' => fake()->dateTimeBetween('-2 days', 'now'),
            'metadata' => null,
        ];
    }
}
