<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(2, true),
            'hero_image_path' => null,
            'social_image_path' => null,
            'is_draft' => false,
            'starts_at' => now(),
            'ends_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_draft' => true,
            'email_broadcast_authorized_at' => null,
            'email_audience_finalized_at' => null,
            'email_broadcast_completed_at' => null,
        ]);
    }
}
