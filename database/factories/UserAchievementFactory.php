<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserAchievement>
 */
class UserAchievementFactory extends Factory
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
            'achievement_key' => 'first_reading',
            'context_key' => 'first-reading',
            'category' => 'firsts',
            'display_name' => 'First reading',
            'description' => 'You logged your first Bible reading.',
            'icon' => 'sparkles',
            'style' => 'success',
            'sort_order' => 10,
            'metadata' => [],
            'earned_at' => now(),
        ];
    }
}
